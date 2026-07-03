<?php
// admin/resolve_all_duplicates.php
require_once __DIR__ . '/../config/session.php';

// ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

// เฉพาะ super admin เท่านั้น (ไม่มีการล็อค hoscode)
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_username = $_SESSION['admin_username'] ?? '';
if ($admin_hoscode !== null || $admin_username === 'adminsso') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$message = '';
$error = '';
$mergedCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_auto_merge'])) {
    try {
        $pdo->beginTransaction();

        // 1. ค้นหาคู่ข้อมูลที่ทับซ้อนกันระหว่าง Mock CID และ Real CID (วัดจาก hoscode + pid เดียวกัน)
        $stmt = $pdo->query("
            SELECT 
                t1.cid AS mock_cid, 
                t2.cid AS real_cid, 
                t1.hoscode, 
                t1.pid
            FROM target_population t1
            JOIN target_population t2 
              ON t1.hoscode = t2.hoscode 
             AND t1.pid = t2.pid
            WHERE (
                t1.cid LIKE '%*%' 
                OR t1.cid LIKE '0%' 
                OR t1.cid = CONCAT(LPAD(t1.hoscode, 5, '0'), LPAD(t1.pid, 8, '0'))
            )
            AND t2.cid NOT LIKE '%*%'
            AND t2.cid NOT LIKE '0%'
            AND t2.cid <> CONCAT(LPAD(t2.hoscode, 5, '0'), LPAD(t2.pid, 8, '0'))
        ");
        $pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($pairs)) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            
            $stmtUpdateAssign = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE target_cid = ?");
            $stmtUpdateDpac = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE cid = ?");
            $stmtMergeMock = $pdo->prepare("
                UPDATE target_population t_real
                JOIN target_population t_mock ON t_mock.cid = ?
                SET 
                    t_real.need_screen_dm = CASE WHEN t_mock.need_screen_dm = 1 THEN 1 ELSE t_real.need_screen_dm END,
                    t_real.need_screen_ht = CASE WHEN t_mock.need_screen_ht = 1 THEN 1 ELSE t_real.need_screen_ht END,
                    t_real.health_status_origin = CASE WHEN t_real.health_status_origin = 'NORMAL' OR t_real.health_status_origin = '' OR t_real.health_status_origin IS NULL THEN t_mock.health_status_origin ELSE t_real.health_status_origin END
                WHERE t_real.cid = ?
            ");
            $stmtDeleteMock = $pdo->prepare("DELETE FROM target_population WHERE cid = ?");

            foreach ($pairs as $match) {
                // ย้ายสถานะและประวัติจาก mock ไปหา real
                $stmtMergeMock->execute([$match['mock_cid'], $match['real_cid']]);
                
                // อัปเดตตารางเชื่อมโยงอื่นๆ
                $stmtUpdateAssign->execute([$match['real_cid'], $match['mock_cid']]);
                $stmtUpdateDpac->execute([$match['real_cid'], $match['mock_cid']]);
                
                // ลบตัว mock ทิ้ง
                $stmtDeleteMock->execute([$match['mock_cid']]);
                $mergedCount++;
            }
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        }

        $pdo->commit();
        $message = "สำเร็จ! ดำเนินการควบรวมข้อมูลอัตโนมัติเรียบร้อยแล้ว ทั้งหมด " . $mergedCount . " รายการ";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// นับจำนวนข้อมูลทับซ้อนที่รอการควบรวม
$pendingCount = 0;
try {
    $stmtCount = $pdo->query("
        SELECT COUNT(*) 
        FROM target_population t1
        JOIN target_population t2 
          ON t1.hoscode = t2.hoscode 
         AND t1.pid = t2.pid
        WHERE (
            t1.cid LIKE '%*%' 
            OR t1.cid LIKE '0%' 
            OR t1.cid = CONCAT(LPAD(t1.hoscode, 5, '0'), LPAD(t1.pid, 8, '0'))
        )
        AND t2.cid NOT LIKE '%*%'
        AND t2.cid NOT LIKE '0%'
        AND t2.cid <> CONCAT(LPAD(t2.hoscode, 5, '0'), LPAD(t2.pid, 8, '0'))
    ");
    $pendingCount = $stmtCount->fetchColumn();
} catch (\Exception $e) {}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบกู้คืนและควบรวมข้อมูลเป้าหมายอัตโนมัติ - NCDs Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #10b981;
            --color-primary-dark: #059669;
            --color-accent: #3b82f6;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --font-family: 'Outfit', 'Sarabun', sans-serif;
            --border-radius: 16px;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-primary);
            font-family: var(--font-family);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .container {
            width: 100%;
            max-width: 600px;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.4);
        }

        h1 {
            font-size: 24px;
            font-weight: 800;
            margin-top: 0;
            margin-bottom: 8px;
            text-align: center;
            background: linear-gradient(135deg, #3b82f6, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .status-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 30px;
            text-align: center;
        }

        .status-num {
            font-size: 48px;
            font-weight: 800;
            color: var(--color-primary);
            line-height: 1;
            margin-bottom: 6px;
        }

        .status-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .btn-action {
            display: block;
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
            filter: brightness(1.1);
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .alert {
            padding: 14px 18px;
            border-radius: var(--border-radius);
            margin-bottom: 24px;
            font-weight: 600;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--color-primary);
            color: var(--color-primary);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
        }

        .info-list {
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-secondary);
            padding-left: 20px;
            line-height: 1.6;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 24px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ระบบล้างข้อมูลซ้ำซ้อนและกู้คืนข้อมูลเป้าหมาย</h1>
        <div class="subtitle">สแกนและควบรวม Mock CID กับเลขบัตรประชาชนจริงให้ถูกต้องครบทุก รพ.สต.</div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                🎉 <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="status-box">
            <div class="status-num"><?= number_format($pendingCount) ?></div>
            <div class="status-label">รายการข้อมูลจำลอง (Mock CID) ที่รอควบรวมเข้ากับข้อมูลจริง</div>
        </div>

        <form action="" method="POST">
            <?php if ($pendingCount > 0): ?>
                <button type="submit" name="action_auto_merge" class="btn-action">
                    🚀 เริ่มต้นสแกนและควบรวมข้อมูลอัตโนมัติ
                </button>
            <?php else: ?>
                <button type="button" class="btn-action" style="background: var(--border-color); cursor: not-allowed; opacity: 0.6;" disabled>
                    ✅ ข้อมูลในระบบจับคู่สมบูรณ์เรียบร้อยแล้ว
                </button>
            <?php endif; ?>
        </form>

        <ul class="info-list">
            <li>ระบบจะจับคู่ Mock CID (รหัสจำลองจากการนำเข้า HDC) เข้ากับ CID จริง (จากการนำเข้าไฟล์ Person ของแต่ละ รพ.สต.)</li>
            <li>ข้อมูลการมอบหมายงาน อสม. และประวัติผลคัดกรองทั้งหมดจะถูกย้ายเข้าเลขบัตรประชาชนจริงโดยอัตโนมัติ</li>
            <li>ลบตัวจำลอง (Mock) ที่ซ้ำซ้อนทิ้ง ป้องกันข้อมูลทับซ้อนและชื่อคนเดียวกันแต่ขึ้นหลายแถว</li>
            <li>ชื่อและนามสกุลจะกลับมาแสดงเต็มรูปแบบตามแฟ้ม Person ที่นำเข้าสมบูรณ์</li>
        </ul>

        <a href="index.php" class="back-link">← กลับสู่หน้าหลักแดชบอร์ด</a>
    </div>
</body>
</html>
