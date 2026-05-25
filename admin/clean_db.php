<?php
// admin/clean_db.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
if ($admin_hoscode !== null) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$message = '';
$error = '';
$steps = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clean'])) {
    try {
        $pdo->beginTransaction();

        // Step 1: Standardize hoscode to 5 digits in all tables
        $tables_to_pad = [
            'target_population' => 'hoscode',
            'staging_hdc_dm' => 'hoscode',
            'staging_hdc_ht' => 'hoscode',
            'jhcis_homes' => 'hoscode',
            'vhv_users' => 'hoscode',
            'admin_users' => 'hoscode'
        ];

        $padded_counts = [];
        foreach ($tables_to_pad as $table => $col) {
            $checkCol = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            if ($checkCol->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE `$table` SET `$col` = LPAD(TRIM(`$col`), 5, '0') WHERE `$col` IS NOT NULL AND `$col` != '' AND LENGTH(TRIM(`$col`)) < 5");
                $stmt->execute();
                $padded_counts[$table] = $stmt->rowCount();
            }
        }
        $steps[] = "ปรับปรุงรหัสหน่วยบริการ (hoscode) เป็น 5 หลักสำเร็จ: " . json_encode($padded_counts, JSON_UNESCAPED_UNICODE);

        // Step 2: Find duplicates in target_population
        $stmtFind = $pdo->query("
            SELECT t1.cid AS masked_cid, t1.first_name AS masked_fname, t1.last_name AS masked_lname,
                   t2.cid AS real_cid, t2.first_name AS real_fname, t2.last_name AS real_lname,
                   t1.hoscode, t1.pid
            FROM target_population t1
            JOIN target_population t2 ON LPAD(t1.hoscode, 5, '0') = LPAD(t2.hoscode, 5, '0') AND t1.pid = t2.pid
            WHERE t1.cid LIKE '%*%' AND t2.cid NOT LIKE '%*%'
        ");
        $duplicates = $stmtFind->fetchAll();

        $merged_tasks = 0;
        $merged_dpac = 0;
        $deleted_masked_targets = 0;

        // Prepared statements for merging
        $stmtGetAssign = $pdo->prepare("SELECT * FROM task_assignments WHERE target_cid = ?");
        $stmtDeleteAssign = $pdo->prepare("DELETE FROM task_assignments WHERE assignment_id = ?");
        $stmtUpdateAssignCid = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE assignment_id = ?");
        
        $stmtGetDpac = $pdo->prepare("SELECT * FROM dpac_enrollments WHERE cid = ?");
        $stmtDeleteDpac = $pdo->prepare("DELETE FROM dpac_enrollments WHERE enrollment_id = ?");
        $stmtUpdateDpacCid = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE enrollment_id = ?");

        $stmtDeleteTarget = $pdo->prepare("DELETE FROM target_population WHERE cid = ?");

        // Temporarily disable foreign keys to allow merging and cleanup
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

        foreach ($duplicates as $dup) {
            $masked_cid = $dup['masked_cid'];
            $real_cid = $dup['real_cid'];

            // 1. Merge task assignments
            $stmtGetAssign->execute([$masked_cid]);
            $masked_assigns = $stmtGetAssign->fetchAll();

            $stmtGetAssign->execute([$real_cid]);
            $real_assigns = $stmtGetAssign->fetchAll();

            $real_by_year = [];
            foreach ($real_assigns as $ra) {
                $real_by_year[$ra['budget_year']] = $ra;
            }

            foreach ($masked_assigns as $ma) {
                $year = $ma['budget_year'];
                if (isset($real_by_year[$year])) {
                    $ra = $real_by_year[$year];
                    if ($ma['assignment_status'] === 'completed' && $ra['assignment_status'] !== 'completed') {
                        // Transfer screening results to the real assignment if any exist
                        $moveScreening = $pdo->prepare("UPDATE screening_results SET assignment_id = ? WHERE assignment_id = ?");
                        $moveScreening->execute([$ma['assignment_id'], $ra['assignment_id']]);
                        
                        $stmtDeleteAssign->execute([$ra['assignment_id']]);
                        $stmtUpdateAssignCid->execute([$real_cid, $ma['assignment_id']]);
                    } else {
                        // Otherwise, just delete the masked assignment
                        // Move screening results of masked assignment to real one if any exist
                        $checkScreening = $pdo->prepare("SELECT COUNT(*) FROM screening_results WHERE assignment_id = ?");
                        $checkScreening->execute([$ma['assignment_id']]);
                        if ($checkScreening->fetchColumn() > 0) {
                            $moveScreening = $pdo->prepare("UPDATE screening_results SET assignment_id = ? WHERE assignment_id = ?");
                            $moveScreening->execute([$ra['assignment_id'], $ma['assignment_id']]);
                        }
                        $stmtDeleteAssign->execute([$ma['assignment_id']]);
                    }
                } else {
                    $stmtUpdateAssignCid->execute([$real_cid, $ma['assignment_id']]);
                }
                $merged_tasks++;
            }

            // 2. Merge DPAC enrollments
            $stmtGetDpac->execute([$masked_cid]);
            $masked_dpac_list = $stmtGetDpac->fetchAll();

            $stmtGetDpac->execute([$real_cid]);
            $real_dpac_list = $stmtGetDpac->fetchAll();

            $real_dpac_by_year = [];
            foreach ($real_dpac_list as $rd) {
                $real_dpac_by_year[$rd['budget_year']] = $rd;
            }

            foreach ($masked_dpac_list as $md) {
                $year = $md['budget_year'];
                if (isset($real_dpac_by_year[$year])) {
                    $stmtDeleteDpac->execute([$md['enrollment_id']]);
                } else {
                    $stmtUpdateDpacCid->execute([$real_cid, $md['enrollment_id']]);
                }
                $merged_dpac++;
            }

            // 3. Delete the masked target record
            $stmtDeleteTarget->execute([$masked_cid]);
            $deleted_masked_targets++;
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        $pdo->commit();
        $message = "ทำความสะอาดฐานข้อมูลและรวมข้อมูลเรียบร้อยแล้ว!";
        $steps[] = "พบข้อมูลซ้ำซ้อน: " . count($duplicates) . " รายการ";
        $steps[] = "รวมและย้ายใบงาน (task_assignments): " . $merged_tasks . " รายการ";
        $steps[] = "รวมและย้ายการเข้าร่วมโครงการ DPAC: " . $merged_dpac . " รายการ";
        $steps[] = "ลบข้อมูลเป้าหมายแบบปกปิดที่ซ้ำซ้อน: " . $deleted_masked_targets . " รายการ";

    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "เกิดข้อผิดพลาดในการทำความสะอาดฐานข้อมูล: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ล้างฐานข้อมูลประชากรซ้ำซ้อน - NCDs Prevention Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 800px; margin: 40px auto; padding: 0 20px;">
        <div class="card-dark" style="text-align: center;">
            <h2 style="color: var(--color-accent); margin-bottom: 20px;">🧹 จัดการข้อมูลซ้ำซ้อนและข้อมูลปกปิด</h2>
            <p style="color: var(--text-secondary); text-align: left; line-height: 1.8; margin-bottom: 30px;">
                สคริปต์นี้มีหน้าที่แก้ไขปัญหาที่เกิดจากความไม่เข้ากันของรหัสหน่วยบริการ (hoscode) และรวบรวมเรคอร์ดที่ซ้ำซ้อนระหว่างข้อมูล HDC แบบปกปิด กับข้อมูลจริงจาก JHCIS 
            </p>
            <div style="text-align: left; margin-bottom: 30px; box-shadow: var(--neumorph-inset); background-color: var(--bg-card); padding: 20px 40px; border-radius: var(--border-radius); border: none;">
                <h4 style="margin-top: 0; color: var(--text-primary);">การทำงานของระบบ:</h4>
                <ol style="color: var(--text-secondary); line-height: 2;">
                    <li>ปรับแต่งข้อมูลรหัสหน่วยบริการ (hoscode) ให้เป็น 5 หลักเสมอในทุกตารางที่เกี่ยวข้อง</li>
                    <li>ค้นหาและจับคู่ข้อมูลบุคคลในกลุ่มเป้าหมายที่มีรหัส hoscode และ pid ตรงกัน แต่มีสองเรคอร์ด (แบบมีเครื่องหมายปกปิด และแบบข้อมูลเต็ม)</li>
                    <li>ย้ายข้อมูลประวัติการมอบหมายงานและข้อมูลโครงการ DPAC จากเรคอร์ดปกปิดไปที่เรคอร์ดตัวจริง</li>
                    <li>ลบเรคอร์ดปกปิดที่ซ้ำซ้อนทิ้ง ป้องกันไม่ให้เกิดปัญหาชื่อและข้อมูลซ้ำซ้อนในหน้ารายงาน</li>
                </ol>
            </div>

            <?php if (!empty($message)): ?>
                <div style="background-color: rgba(16, 185, 129, 0.15); border: 1px solid var(--color-green); color: var(--color-green); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px; text-align: left;">
                    <strong>สำเร็จ!</strong> <?= htmlspecialchars($message) ?><br><br>
                    <strong>ขั้นตอนที่ดำเนินการ:</strong>
                    <ul style="margin-top: 10px; margin-bottom: 0;">
                        <?php foreach ($steps as $step): ?>
                            <li><?= htmlspecialchars($step) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div style="background-color: rgba(239, 68, 68, 0.15); border: 1px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px; text-align: left;">
                    <strong>ล้มเหลว!</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <button type="submit" name="clean" class="btn-giant btn-giant-primary" style="border-radius: var(--border-radius);">
                    เริ่มกระบวนการทำความสะอาดและจัดระเบียบฐานข้อมูล
                </button>
            </form>
        </div>
    </div>
</body>
</html>
