<?php
// admin/cleanup_rewards.php
require_once __DIR__ . '/../config/session.php';

// Force super-admin authorization
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !empty($_SESSION['admin_hoscode'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
$admin_title = get_admin_title();

$message = '';
$affectedRows = 0;
$triggered = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_cleanup'])) {
    try {
        $pdo->beginTransaction();
        
        // Execute cleanup query to delete older duplicate rewards for the same assignment_id
        $stmt = $pdo->query("
            DELETE r1 FROM vhv_rewards r1
            INNER JOIN vhv_rewards r2 
                ON r1.assignment_id = r2.assignment_id 
                AND r1.reward_id < r2.reward_id
            WHERE r1.assignment_id IS NOT NULL
        ");
        
        $affectedRows = $stmt->rowCount();
        $pdo->commit();
        
        $message = "ทำความสะอาดฐานข้อมูลสำเร็จ! ล้างแต้มคัดกรองที่ซ้ำซ้อนออกทั้งหมด <strong>" . number_format($affectedRows) . "</strong> รายการเรียบร้อยแล้ว";
        $triggered = true;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "เกิดข้อผิดพลาดในการทำความสะอาดข้อมูล: " . htmlspecialchars($e->getMessage());
        $triggered = true;
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบทำความสะอาดคะแนนสะสม - NCDs Prevention Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
        }

        .cleanup-card {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--neumorph-flat);
            max-width: 600px;
            margin: 50px auto;
            text-align: center;
        }

        .alert-info {
            background-color: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            color: var(--text-primary);
            padding: 15px;
            border-radius: 8px;
            text-align: left;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.6;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-left: 4px solid #10b981;
            color: var(--color-green);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 15px;
        }

        .btn-run {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: bold;
            color: white;
            background-color: var(--color-red);
            border: none;
            border-radius: 30px;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s;
        }

        .btn-run:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }
    </style>
</head>

<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <div class="cleanup-card">
            <h2 style="color: var(--color-accent); margin-bottom: 20px;">🧹 ระบบทำความสะอาดคะแนนสะสม อสม.</h2>
            <p style="color: var(--text-secondary); margin-bottom: 25px;">
                สำหรับล้างคะแนนคัดกรองที่บันทึกซ้ำซ้อน/ปั๊มแต้มในระบบให้เหลือ 1 แต้มต่อ 1 ใบงานการมอบหมายงาน
            </p>

            <?php if ($triggered): ?>
                <div class="alert-success">
                    <?= $message ?>
                </div>
                <a href="leaderboard.php" class="btn-primary" style="display: inline-block; text-decoration: none; padding: 10px 25px; border-radius: 20px;">
                    🏆 ไปที่ตารางคะแนน (Leaderboard)
                </a>
            <?php else: ?>
                <div class="alert-info">
                    <strong>คำชี้แจงก่อนทำความสะอาดข้อมูล:</strong><br>
                    1. ระบบจะค้นหาและล้างแต้มที่ซ้ำซ้อนในตารางรางวัล โดยคงเหลือไว้เฉพาะแต้มใบงานล่าสุดใบเดียวต่อหนึ่งเป้าหมาย<br>
                    2. คะแนนรวมของ อสม. บนตาราง Leaderboard และหน้าสรุปผลงานจะถูกปรับให้ถูกต้องทันทีหลังรันระบบนี้<br>
                    3. การล้างข้อมูลนี้มีความปลอดภัยและไม่กระทบต่อฐานข้อมูลประวัติหรือผลการตรวจสุขภาพใด ๆ ของประชาชน
                </div>

                <form method="POST">
                    <button type="submit" name="run_cleanup" class="btn-run" onclick="return confirm('ยืนยันที่จะทำความสะอาดฐานข้อมูลคะแนนสะสมหรือไม่?')">
                        🚀 เริ่มทำความสะอาดข้อมูลทันที
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
