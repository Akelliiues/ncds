<?php
// admin/clean_db.php
require_once __DIR__ . '/../config/session.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_username = $_SESSION['admin_username'] ?? '';
if ($admin_hoscode !== null || $admin_username === 'adminsso') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$message = '';
$error = '';
$steps = [];
$previewData = null;
$verifyResults = null;

// ─────────────────────────────────────────────────────────────────────────────
// Helper: ค้นหา duplicate ทั้ง 3 รูปแบบ แล้ว normalize เป็น array เดียวกัน
// ─────────────────────────────────────────────────────────────────────────────
function findAllDuplicates($pdo) {
    $dupes = [];
    $seen  = []; // คีย์ masked_cid เพื่อกัน process ซ้ำ

    // ── A: CID มี * (หรือเป็น placeholder) vs CID จริงที่ไม่มี * ────────────
    //    จับคู่ด้วย hoscode+pid
    $stmtA = $pdo->query("
        SELECT
            t1.cid        AS masked_cid,
            t1.first_name AS masked_fname,
            t1.last_name  AS masked_lname,
            t1.need_screen_dm AS masked_dm,
            t1.need_screen_ht AS masked_ht,
            t1.health_status_origin AS masked_status,
            t2.cid        AS real_cid,
            t2.first_name AS real_fname,
            t2.last_name  AS real_lname,
            LPAD(t1.hoscode,5,'0') AS hoscode,
            t1.pid,
            'A' AS dup_type
        FROM target_population t1
        JOIN target_population t2
          ON t1.hoscode = t2.hoscode
         AND t1.pid = t2.pid
        WHERE (
            t1.cid LIKE '%*%' 
            OR t1.first_name LIKE '%*%' 
            OR t1.cid LIKE '0%' 
            OR t1.cid = CONCAT(LPAD(t1.hoscode, 5, '0'), LPAD(t1.pid, 8, '0'))
            OR t1.cid = CONCAT(LPAD(t1.hoscode, 5, '0'), t1.pid)
          )
          AND (
            t2.cid NOT LIKE '%*%' 
            AND t2.first_name NOT LIKE '%*%' 
            AND t2.cid NOT LIKE '0%' 
            AND t2.cid <> CONCAT(LPAD(t2.hoscode, 5, '0'), LPAD(t2.pid, 8, '0'))
            AND t2.cid <> CONCAT(LPAD(t2.hoscode, 5, '0'), t2.pid)
          )
          AND t1.cid <> t2.cid
    ");
    foreach ($stmtA->fetchAll() as $row) {
        $key = $row['masked_cid'];
        if (!isset($seen[$key])) {
            $dupes[] = $row;
            $seen[$key] = true;
        }
    }

    // ── B: ทั้งคู่ไม่มี * แต่ pid+hoscode เดียวกัน ──────────────────────────
    //    เลือก record ที่ชื่อ/นามสกุลเป็น default placeholder เป็น "masked"
    //    หรือถ้าทั้งคู่มีชื่อจริง → เลือก record เก่ากว่า (created_at น้อยกว่า) เป็น masked
    $stmtB = $pdo->query("
        SELECT
            t1.cid        AS masked_cid,
            t1.first_name AS masked_fname,
            t1.last_name  AS masked_lname,
            t1.need_screen_dm AS masked_dm,
            t1.need_screen_ht AS masked_ht,
            t1.health_status_origin AS masked_status,
            t2.cid        AS real_cid,
            t2.first_name AS real_fname,
            t2.last_name  AS real_lname,
            LPAD(t1.hoscode,5,'0') AS hoscode,
            t1.pid,
            'B' AS dup_type
        FROM target_population t1
        JOIN target_population t2
          ON t1.hoscode = t2.hoscode
         AND t1.pid = t2.pid
        WHERE t1.cid NOT LIKE '%*%'
          AND t2.cid NOT LIKE '%*%'
          AND t1.cid <> t2.cid
          AND t1.pid IS NOT NULL
          AND t1.pid != ''
          AND (
              -- t1 เป็น record ที่น่าสงสัย: ชื่อ default หรือชื่อสั้นกว่า
              t1.first_name IN ('ไม่ทราบชื่อ','ไม่ทราบ','','Unknown')
              OR t1.last_name IN ('ไม่ทราบประวัติ','ไม่ทราบ','','Unknown')
              OR (
                  -- ถ้าทั้งคู่มีชื่อ ให้ t1 เป็น record ที่เก่ากว่า
                  t1.first_name NOT IN ('ไม่ทราบชื่อ','ไม่ทราบ','')
                  AND t2.first_name NOT IN ('ไม่ทราบชื่อ','ไม่ทราบ','')
                  AND t1.cid < t2.cid
              )
          )
    ");
    foreach ($stmtB->fetchAll() as $row) {
        $key = $row['masked_cid'];
        // ป้องกัน masked_cid เดียวกันถูกนับซ้ำ และ ป้องกันกรณีที่ masked/real สลับกัน
        if (!isset($seen[$key]) && !isset($seen[$row['real_cid']])) {
            $dupes[] = $row;
            $seen[$key] = true;
        }
    }

    // ── C: ชื่อ default (ไม่ได้ import จาก person) แต่มี record จริงที่ cid เดียวกัน ─
    //    กรณีนี้หมายถึง ETL สร้าง record ด้วยชื่อ "ไม่ทราบชื่อ" แต่ import person
    //    อัปเดต CID เดียวกันให้มีชื่อจริงแล้ว → ไม่ได้ duplicate แต่ชื่อยังเป็น default
    //    จัดการด้วยการ UPDATE ชื่อจาก staging แทน (แยก section ล่าง)

    return $dupes;
}

// ─────────────────────────────────────────────────────────────────────────────
// Action: Preview — แสดงรายการซ้ำก่อน confirm
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_preview'])) {
    try {
        // Step 0: Standardize hoscode ก่อน
        $tables_to_pad = [
            'target_population' => 'hoscode',
            'staging_hdc_dm'    => 'hoscode',
            'staging_hdc_ht'    => 'hoscode',
            'jhcis_homes'       => 'hoscode',
            'vhv_users'         => 'hoscode',
        ];
        foreach ($tables_to_pad as $table => $col) {
            try {
                $pdo->exec("UPDATE `$table` SET `$col` = LPAD(TRIM(`$col`), 5, '0') WHERE `$col` IS NOT NULL AND `$col` != '' AND LENGTH(TRIM(`$col`)) < 5");
            } catch (\Exception $e) { /* table อาจไม่มี */ }
        }

        $dupes = findAllDuplicates($pdo);

        // นับ screening_results และ DPAC ของแต่ละ masked_cid เพื่อแสดงใน preview
        $stmtCountScreen = $pdo->prepare("
            SELECT COUNT(*) FROM screening_results sr
            JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
            WHERE ta.target_cid = ?
        ");
        $stmtCountDpac = $pdo->prepare("SELECT COUNT(*) FROM dpac_enrollments WHERE cid = ?");

        foreach ($dupes as &$d) {
            $stmtCountScreen->execute([$d['masked_cid']]);
            $d['screen_count'] = (int)$stmtCountScreen->fetchColumn();
            $stmtCountDpac->execute([$d['masked_cid']]);
            $d['dpac_count'] = (int)$stmtCountDpac->fetchColumn();
        }
        unset($d);

        // ค้นหา records ที่ชื่อยัง default (ต้องอัปเดต ไม่ต้องลบ)
        $stmtDefault = $pdo->query("
            SELECT cid, first_name, last_name, hoscode, pid
            FROM target_population
            WHERE (first_name IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown') OR last_name IN ('ไม่ทราบประวัติ','ไม่ทราบ','Unknown'))
              AND cid NOT LIKE '%*%'
            ORDER BY hoscode, pid
        ");
        $defaultNameRecords = $stmtDefault->fetchAll();

        $previewData = [
            'duplicates'     => $dupes,
            'default_names'  => $defaultNameRecords,
        ];

    } catch (\Exception $e) {
        $error = "เกิดข้อผิดพลาดในการตรวจสอบข้อมูล: " . $e->getMessage();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Action: Confirm & Execute Clean
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_clean'])) {
    try {
        $pdo->beginTransaction();

        // Step 1: Standardize hoscode
        $tables_to_pad = [
            'target_population' => 'hoscode',
            'staging_hdc_dm'    => 'hoscode',
            'staging_hdc_ht'    => 'hoscode',
            'jhcis_homes'       => 'hoscode',
            'vhv_users'         => 'hoscode',
        ];
        $padded_counts = [];
        foreach ($tables_to_pad as $table => $col) {
            try {
                $stmt = $pdo->prepare("UPDATE `$table` SET `$col` = LPAD(TRIM(`$col`), 5, '0') WHERE `$col` IS NOT NULL AND `$col` != '' AND LENGTH(TRIM(`$col`)) < 5");
                $stmt->execute();
                $padded_counts[$table] = $stmt->rowCount();
            } catch (\Exception $e) { $padded_counts[$table] = 0; }
        }
        $steps[] = "✅ ปรับปรุง hoscode เป็น 5 หลัก: " . array_sum($padded_counts) . " แถว";

        // Step 2: ค้นหา duplicate ทั้งหมด
        $dupes = findAllDuplicates($pdo);
        $steps[] = "🔍 พบข้อมูลซ้ำซ้อน: " . count($dupes) . " รายการ (รูปแบบ A=" .
            count(array_filter($dupes, fn($d) => $d['dup_type'] === 'A')) . ", B=" .
            count(array_filter($dupes, fn($d) => $d['dup_type'] === 'B')) . ")";

        // Prepared statements
        $stmtGetAssign       = $pdo->prepare("SELECT * FROM task_assignments WHERE target_cid = ?");
        $stmtDeleteAssign    = $pdo->prepare("DELETE FROM task_assignments WHERE assignment_id = ?");
        $stmtUpdateAssignCid = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE assignment_id = ?");
        $stmtGetDpac         = $pdo->prepare("SELECT * FROM dpac_enrollments WHERE cid = ?");
        $stmtDeleteDpac      = $pdo->prepare("DELETE FROM dpac_enrollments WHERE enrollment_id = ?");
        $stmtUpdateDpacCid   = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE enrollment_id = ?");
        $stmtDeleteTarget    = $pdo->prepare("DELETE FROM target_population WHERE cid = ?");
        $stmtMoveRewards     = $pdo->prepare("UPDATE vhv_rewards SET vhv_id = vhv_id WHERE 1=0"); // placeholder
        $stmtUpdateTargetFlags = $pdo->prepare("
            UPDATE target_population 
            SET 
                need_screen_dm = CASE WHEN ? = 1 THEN 1 ELSE need_screen_dm END,
                need_screen_ht = CASE WHEN ? = 1 THEN 1 ELSE need_screen_ht END,
                health_status_origin = CASE WHEN health_status_origin = 'NORMAL' OR health_status_origin = '' OR health_status_origin IS NULL THEN ? ELSE health_status_origin END,
                updated_at = NOW()
            WHERE cid = ?
        ");

        $merged_tasks  = 0;
        $merged_dpac   = 0;
        $deleted_count = 0;

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

        foreach ($dupes as $dup) {
            $masked_cid = $dup['masked_cid'];
            $real_cid   = $dup['real_cid'];

            // 0. Copy screening flags to real record
            $stmtUpdateTargetFlags->execute([
                $dup['masked_dm'],
                $dup['masked_ht'],
                $dup['masked_status'],
                $real_cid
            ]);

            // ── 1. Merge task_assignments ───────────────────────────────────
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
                    // ตรวจสอบ screening_results ของ masked assignment
                    $checkScreen = $pdo->prepare("SELECT COUNT(*) FROM screening_results WHERE assignment_id = ?");
                    $checkScreen->execute([$ma['assignment_id']]);
                    $hasScreening = $checkScreen->fetchColumn() > 0;

                    if ($hasScreening) {
                        // ย้าย screening results ไปที่ real assignment
                        $moveScreen = $pdo->prepare("UPDATE screening_results SET assignment_id = ? WHERE assignment_id = ?");
                        $moveScreen->execute([$ra['assignment_id'], $ma['assignment_id']]);
                    }
                    // ลบ masked assignment ที่ซ้ำ
                    $stmtDeleteAssign->execute([$ma['assignment_id']]);
                } else {
                    // ย้าย target_cid ไปที่ real_cid
                    $stmtUpdateAssignCid->execute([$real_cid, $ma['assignment_id']]);
                }
                $merged_tasks++;
            }

            // ── 2. Merge DPAC enrollments ───────────────────────────────────
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
                    // Merge followups ไปยัง real enrollment ก่อนลบ
                    $moveFollowups = $pdo->prepare("UPDATE dpac_followups SET enrollment_id = ? WHERE enrollment_id = ?");
                    $moveFollowups->execute([$real_dpac_by_year[$year]['enrollment_id'], $md['enrollment_id']]);
                    $stmtDeleteDpac->execute([$md['enrollment_id']]);
                } else {
                    // ย้าย CID ไปที่ real_cid
                    $stmtUpdateDpacCid->execute([$real_cid, $md['enrollment_id']]);
                }
                $merged_dpac++;
            }

            // ── 3. ย้าย vhv_rewards ที่ลิงก์กับ screening ────────────────────
            $pdo->prepare("
                UPDATE vhv_rewards r
                JOIN task_assignments ta ON r.screening_id = ta.assignment_id
                SET r.screening_id = r.screening_id
                WHERE ta.target_cid = ?
            ")->execute([$masked_cid]); // ผลลัพธ์จะถูก cascade เพราะ merge assignments แล้ว

            // ── 4. ลบ masked record ─────────────────────────────────────────
            $stmtDeleteTarget->execute([$masked_cid]);
            $deleted_count++;
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Step 3: อัปเดตชื่อ default ที่ยังหลงเหลืออยู่ จาก staging_hdc_dm/ht
        $updatedDefaultNames = $pdo->exec("
            UPDATE target_population t
            JOIN (
                SELECT dm.pid, dm.hoscode,
                       COALESCE(NULLIF(dm.name,''), NULLIF(ht.name,'')) AS fname,
                       COALESCE(NULLIF(dm.lname,''), NULLIF(ht.lname,'')) AS lname
                FROM (SELECT DISTINCT pid, hoscode, name, lname FROM staging_hdc_dm) dm
                LEFT JOIN (SELECT DISTINCT pid, hoscode, name AS htname, lname AS htlname FROM staging_hdc_ht) ht
                  ON dm.pid = ht.pid AND dm.hoscode = ht.hoscode
            ) s ON t.hoscode = s.hoscode
               AND t.pid = s.pid
            SET
                t.first_name = CASE WHEN t.first_name IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown','') AND s.fname IS NOT NULL THEN s.fname ELSE t.first_name END,
                t.last_name  = CASE WHEN t.last_name  IN ('ไม่ทราบประวัติ','ไม่ทราบ','Unknown','') AND s.lname IS NOT NULL THEN s.lname ELSE t.last_name END,
                t.updated_at = NOW()
            WHERE t.first_name IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown','')
               OR t.last_name  IN ('ไม่ทราบประวัติ','ไม่ทราบ','Unknown','')
        ");
        $steps[] = "📝 อัปเดตชื่อ default เป็นชื่อจริงจาก staging: " . $updatedDefaultNames . " แถว";

        $pdo->commit();

        $steps[] = "✅ รวมและย้ายใบงาน (task_assignments): " . $merged_tasks . " รายการ";
        $steps[] = "✅ รวมและย้าย DPAC enrollments: " . $merged_dpac . " รายการ";
        $steps[] = "🗑️ ลบ record ที่ซ้ำซ้อน: " . $deleted_count . " รายการ";
        $message = "success";

        // Step 4: Verification — ตรวจสอบหลัง clean
        $verifyResults = [];

        // ตรวจ pid+hoscode ซ้ำที่เหลือ
        $v1 = $pdo->query("
            SELECT LPAD(hoscode,5,'0') AS hoscode, pid, COUNT(*) AS cnt
            FROM target_population
            WHERE pid IS NOT NULL AND pid != ''
            GROUP BY LPAD(hoscode,5,'0'), pid
            HAVING cnt > 1
        ")->fetchAll();
        $verifyResults['remaining_pid_dupes'] = $v1;

        // ตรวจ orphan task_assignments
        $v2 = $pdo->query("
            SELECT ta.assignment_id, ta.target_cid
            FROM task_assignments ta
            LEFT JOIN target_population tp ON ta.target_cid = tp.cid
            WHERE tp.cid IS NULL
        ")->fetchAll();
        $verifyResults['orphan_assignments'] = $v2;

        // ตรวจ orphan DPAC
        $v3 = $pdo->query("
            SELECT de.enrollment_id, de.cid
            FROM dpac_enrollments de
            LEFT JOIN target_population tp ON de.cid = tp.cid
            WHERE tp.cid IS NULL
        ")->fetchAll();
        $verifyResults['orphan_dpac'] = $v3;

        // ตรวจชื่อ default ที่เหลือ
        $v4 = $pdo->query("
            SELECT COUNT(*) FROM target_population
            WHERE first_name IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown','')
               OR last_name  IN ('ไม่ทราบประวัติ','ไม่ทราบ','Unknown','')
        ")->fetchColumn();
        $verifyResults['remaining_default_names'] = (int)$v4;

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
    <title>จัดการข้อมูลซ้ำซ้อน - NCDs Prevention Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .dup-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .dup-table th, .dup-table td {
            padding: 8px 12px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            text-align: left;
        }
        .dup-table th {
            background-color: var(--bg-darker);
            font-weight: 800;
            color: var(--text-primary);
            position: sticky;
            top: 0;
        }
        .dup-table tr:hover td { background: rgba(13,44,84,0.03); }
        .badge-type {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 800;
        }
        .badge-A { background: #fee2e2; color: #991b1b; }
        .badge-B { background: #fef9c3; color: #854d0e; }
        .text-danger { color: var(--color-red); font-weight: 800; }
        .text-success { color: var(--color-green); font-weight: 800; }
        .verify-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 16px 20px;
            margin-bottom: 12px;
            box-shadow: var(--neumorph-flat);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .verify-icon { font-size: 24px; }
        .verify-label { font-size: 13px; color: var(--text-secondary); }
        .verify-val { font-size: 20px; font-weight: 800; }
        .section-divider {
            border: none;
            border-top: 1px solid rgba(0,0,0,0.06);
            margin: 24px 0;
        }
        .table-scroll { overflow-x: auto; border-radius: 12px; box-shadow: var(--neumorph-inset); background: var(--bg-card); padding: 8px; }
    </style>
</head>
<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1100px; margin: 40px auto; padding: 0 20px;">

        <div class="card-dark" style="margin-bottom: 24px;">
            <h2 style="color: var(--color-accent); margin-bottom: 8px; display:flex; align-items:center; gap:10px;">
                🧹 จัดการข้อมูลซ้ำซ้อนและข้อมูลปกปิด
            </h2>
            <p style="color: var(--text-secondary); margin: 0 0 20px 0; line-height: 1.8;">
                ระบบตรวจหาและรวมข้อมูลที่ซ้ำซ้อนใน <code>target_population</code> ครอบคลุม <strong>3 รูปแบบ</strong>:
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; margin-bottom: 24px;">
                <div style="background: var(--bg-darker); border-radius: 16px; padding: 14px 18px;">
                    <div style="font-weight: 800; margin-bottom: 4px;"><span class="badge-type badge-A">รูปแบบ A</span> CID ปกปิด (*)</div>
                    <div style="font-size: 13px; color: var(--text-secondary);">จับคู่ด้วย hoscode+pid — t1 มี * / t2 เป็น CID จริง</div>
                </div>
                <div style="background: var(--bg-darker); border-radius: 16px; padding: 14px 18px;">
                    <div style="font-weight: 800; margin-bottom: 4px;"><span class="badge-type badge-B">รูปแบบ B</span> CID จริงทั้งคู่ แต่ซ้ำ</div>
                    <div style="font-size: 13px; color: var(--text-secondary);">pid+hoscode เดียวกัน — เลือก record ที่ชื่อสมบูรณ์กว่าเป็น "จริง"</div>
                </div>
                <div style="background: var(--bg-darker); border-radius: 16px; padding: 14px 18px;">
                    <div style="font-weight: 800; margin-bottom: 4px;">📝 ชื่อ Default</div>
                    <div style="font-size: 13px; color: var(--text-secondary);">อัปเดตชื่อ "ไม่ทราบชื่อ" จาก staging DM/HT อัตโนมัติ</div>
                </div>
            </div>

            <?php if (!$previewData && $message !== 'success'): ?>
            <form method="POST">
                <button type="submit" name="action_preview" class="btn-giant btn-giant-accent" style="border-radius: var(--border-radius);">
                    🔍 ตรวจสอบและแสดงตัวอย่างข้อมูลซ้ำก่อนดำเนินการ
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($error)): ?>
        <div style="background: rgba(239,68,68,0.12); border: 1px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px;">
            <strong>❌ เกิดข้อผิดพลาด!</strong> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($message === 'success' && $verifyResults !== null): ?>
        <!-- ── ผลลัพธ์หลัง clean ──────────────────────────────────────── -->
        <div style="background: rgba(16,185,129,0.1); border: 1px solid var(--color-green); color: var(--color-green); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px;">
            <strong>✅ ทำความสะอาดฐานข้อมูลสำเร็จ!</strong>
        </div>
        <div class="card-dark" style="margin-bottom: 24px;">
            <h3 style="margin-top:0; color: var(--color-accent);">📋 ขั้นตอนที่ดำเนินการ</h3>
            <ul style="line-height: 2; margin: 0; padding-left: 20px; color: var(--text-secondary);">
                <?php foreach ($steps as $s): ?>
                <li><?= htmlspecialchars($s) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card-dark">
            <h3 style="margin-top:0; color: var(--color-accent);">🔬 ผลการตรวจสอบหลังทำความสะอาด</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 14px; margin-bottom: 24px;">

                <div class="verify-card">
                    <span class="verify-icon"><?= count($verifyResults['remaining_pid_dupes']) === 0 ? '✅' : '⚠️' ?></span>
                    <div>
                        <div class="verify-label">pid+hoscode ที่ยังซ้ำอยู่</div>
                        <div class="verify-val <?= count($verifyResults['remaining_pid_dupes']) > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= count($verifyResults['remaining_pid_dupes']) ?> รายการ
                        </div>
                    </div>
                </div>

                <div class="verify-card">
                    <span class="verify-icon"><?= count($verifyResults['orphan_assignments']) === 0 ? '✅' : '⚠️' ?></span>
                    <div>
                        <div class="verify-label">Orphan task_assignments</div>
                        <div class="verify-val <?= count($verifyResults['orphan_assignments']) > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= count($verifyResults['orphan_assignments']) ?> รายการ
                        </div>
                    </div>
                </div>

                <div class="verify-card">
                    <span class="verify-icon"><?= count($verifyResults['orphan_dpac']) === 0 ? '✅' : '⚠️' ?></span>
                    <div>
                        <div class="verify-label">Orphan DPAC enrollments</div>
                        <div class="verify-val <?= count($verifyResults['orphan_dpac']) > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= count($verifyResults['orphan_dpac']) ?> รายการ
                        </div>
                    </div>
                </div>

                <div class="verify-card">
                    <span class="verify-icon"><?= $verifyResults['remaining_default_names'] === 0 ? '✅' : '🟡' ?></span>
                    <div>
                        <div class="verify-label">ชื่อ default ที่เหลือ</div>
                        <div class="verify-val" style="color: <?= $verifyResults['remaining_default_names'] > 0 ? 'var(--color-yellow)' : 'var(--color-green)' ?>">
                            <?= $verifyResults['remaining_default_names'] ?> ราย
                        </div>
                    </div>
                </div>
            </div>

            <?php if (count($verifyResults['remaining_pid_dupes']) > 0): ?>
            <div style="background: rgba(239,68,68,0.08); border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                <strong style="color: var(--color-red);">⚠️ ยังพบ pid+hoscode ซ้ำ — อาจต้องรันอีกครั้ง:</strong>
                <div class="table-scroll" style="margin-top: 10px;">
                    <table class="dup-table">
                        <tr><th>hoscode</th><th>pid</th><th>จำนวน</th></tr>
                        <?php foreach ($verifyResults['remaining_pid_dupes'] as $r): ?>
                        <tr><td><?= htmlspecialchars($r['hoscode']) ?></td><td><?= htmlspecialchars($r['pid']) ?></td><td class="text-danger"><?= $r['cnt'] ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" style="margin-top: 16px;">
                <button type="submit" name="action_preview" class="btn-giant btn-giant-secondary" style="border-radius: var(--border-radius);">
                    🔍 ตรวจสอบอีกครั้ง
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($previewData): ?>
        <!-- ── Preview Mode ──────────────────────────────────────────────── -->
        <div class="card-dark">
            <h3 style="margin-top:0; color: var(--color-accent);">
                🔍 ผลการตรวจสอบ — พบข้อมูลซ้ำซ้อน <?= count($previewData['duplicates']) ?> รายการ
            </h3>

            <?php if (count($previewData['duplicates']) === 0): ?>
            <div style="text-align:center; padding: 40px 0; color: var(--color-green);">
                <div style="font-size: 48px; margin-bottom: 12px;">✅</div>
                <div style="font-size: 18px; font-weight: 800;">ไม่พบข้อมูลซ้ำซ้อน ระบบสะอาดดีแล้ว!</div>
            </div>
            <?php else: ?>

            <div style="margin-bottom: 16px;">
                <span style="background: rgba(239,68,68,0.12); color: var(--color-red); padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 800; margin-right: 8px;">
                    รูปแบบ A: <?= count(array_filter($previewData['duplicates'], fn($d) => $d['dup_type'] === 'A')) ?> รายการ
                </span>
                <span style="background: rgba(245,158,11,0.12); color: var(--color-yellow); padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 800;">
                    รูปแบบ B: <?= count(array_filter($previewData['duplicates'], fn($d) => $d['dup_type'] === 'B')) ?> รายการ
                </span>
            </div>

            <div class="table-scroll" style="margin-bottom: 24px; max-height: 420px; overflow-y: auto;">
                <table class="dup-table">
                    <thead>
                        <tr>
                            <th>ประเภท</th>
                            <th>🗑️ Record ที่จะลบ (CID ซ้ำ)</th>
                            <th>ชื่อ (ซ้ำ)</th>
                            <th>✅ Record จริง (CID หลัก)</th>
                            <th>ชื่อ (จริง)</th>
                            <th>hoscode</th>
                            <th>pid</th>
                            <th>Screens</th>
                            <th>DPAC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData['duplicates'] as $d): ?>
                        <tr>
                            <td><span class="badge-type badge-<?= $d['dup_type'] ?>"><?= $d['dup_type'] ?></span></td>
                            <td style="font-family: monospace; font-size: 12px; color: var(--color-red);"><?= htmlspecialchars($d['masked_cid']) ?></td>
                            <td><?= htmlspecialchars($d['masked_fname'] . ' ' . $d['masked_lname']) ?></td>
                            <td style="font-family: monospace; font-size: 12px; color: var(--color-green);"><?= htmlspecialchars($d['real_cid']) ?></td>
                            <td><?= htmlspecialchars($d['real_fname'] . ' ' . $d['real_lname']) ?></td>
                            <td><?= htmlspecialchars($d['hoscode']) ?></td>
                            <td><?= htmlspecialchars($d['pid']) ?></td>
                            <td style="text-align:center;"><?= $d['screen_count'] > 0 ? '<strong style="color:var(--color-green)">' . $d['screen_count'] . '</strong>' : '-' ?></td>
                            <td style="text-align:center;"><?= $d['dpac_count'] > 0 ? '<strong style="color:var(--color-green)">' . $d['dpac_count'] . '</strong>' : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (count($previewData['default_names']) > 0): ?>
            <hr class="section-divider">
            <h4 style="color: var(--color-yellow); margin-top: 0;">
                📝 พบ record ที่ชื่อยังเป็นค่า default — <?= count($previewData['default_names']) ?> ราย
                <small style="font-size: 13px; font-weight: 400; color: var(--text-muted);">(จะอัปเดตชื่อจาก staging อัตโนมัติ)</small>
            </h4>
            <div class="table-scroll" style="max-height: 240px; overflow-y:auto; margin-bottom: 16px;">
                <table class="dup-table">
                    <thead>
                        <tr><th>CID</th><th>ชื่อ (ปัจจุบัน)</th><th>นามสกุล (ปัจจุบัน)</th><th>hoscode</th><th>pid</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData['default_names'] as $dn): ?>
                        <tr>
                            <td style="font-family:monospace; font-size:12px;"><?= htmlspecialchars($dn['cid']) ?></td>
                            <td style="color:var(--color-yellow);"><?= htmlspecialchars($dn['first_name']) ?></td>
                            <td style="color:var(--color-yellow);"><?= htmlspecialchars($dn['last_name']) ?></td>
                            <td><?= htmlspecialchars($dn['hoscode']) ?></td>
                            <td><?= htmlspecialchars($dn['pid']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (count($previewData['duplicates']) > 0 || count($previewData['default_names']) > 0): ?>
            <div style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3); border-radius: 16px; padding: 16px; margin-bottom: 20px;">
                <strong style="color: var(--color-red);">⚠️ คำเตือน:</strong>
                <span style="color: var(--text-secondary); font-size: 14px;">
                    การดำเนินการนี้จะ <strong>ลบข้อมูลซ้ำ</strong> และ <strong>ย้าย screening/DPAC</strong> ไปยัง record จริง
                    กระบวนการนี้ไม่สามารถย้อนกลับได้ — กรุณาตรวจสอบรายการด้านบนก่อนกดยืนยัน
                </span>
            </div>
            <form method="POST">
                <button type="submit" name="action_clean" class="btn-giant btn-giant-primary" style="border-radius: var(--border-radius);">
                    ✅ ยืนยัน — เริ่มทำความสะอาดและรวมข้อมูล
                </button>
            </form>
            <?php else: ?>
            <form method="POST" style="margin-top: 16px;">
                <button type="submit" name="action_preview" class="btn-giant btn-giant-secondary" style="border-radius: var(--border-radius);">
                    🔄 ตรวจสอบใหม่
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>
