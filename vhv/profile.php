<?php
// vhv/profile.php
session_start();

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$vhvId = $_SESSION['vhv_id'];
$message = '';
$error = '';

// Fetch current VHV details
try {
    $stmt = $pdo->prepare("SELECT * FROM vhv_users WHERE vhv_id = ?");
    $stmt->execute([$vhvId]);
    $vhv = $stmt->fetch();
    
    if (!$vhv) {
        header("Location: ../logout.php");
        exit();
    }
} catch (\Throwable $e) {
    $error = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $e->getMessage();
}

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $vhv_name = trim($_POST['vhv_name'] ?? '');
        if (empty($vhv_name)) {
            $error = 'กรุณากรอกชื่อ-นามสกุล';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE vhv_users SET vhv_name = ? WHERE vhv_id = ?");
                $stmt->execute([$vhv_name, $vhvId]);
                $_SESSION['vhv_name'] = $vhv_name; // Update session
                $vhv['vhv_name'] = $vhv_name;
                $message = 'อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว';
            } catch (\Throwable $e) {
                $error = 'ไม่สามารถบันทึกข้อมูลได้: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'กรุณากรอกข้อมูลรหัสผ่านให้ครบถ้วน';
        } elseif ($new_password !== $confirm_password) {
            $error = 'รหัสผ่านใหม่ไม่ตรงกัน';
        } else {
            try {
                // Verify old password (support default "1234" check too)
                $old_hash = $vhv['password_hash'];
                $old_password_correct = false;
                if ($old_password === '1234' || password_verify($old_password, $old_hash)) {
                    $old_password_correct = true;
                }
                
                if ($old_password_correct) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE vhv_users SET password_hash = ? WHERE vhv_id = ?");
                    $stmt->execute([$new_hash, $vhvId]);
                    $message = 'เปลี่ยนรหัสผ่านสำเร็จเรียบร้อยแล้ว';
                } else {
                    $error = 'รหัสผ่านเดิมไม่ถูกต้อง';
                }
            } catch (\Throwable $e) {
                $error = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลส่วนตัว - อสม. นครตาลสุม</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 800;
            color: var(--text-secondary);
            font-size: 15px;
        }
    </style>
</head>
<body class="vhv-accessibility">
    <div class="mobile-wrapper">
        <!-- VHV Info Header -->
        <div class="vhv-header" style="margin-bottom: 24px;">
            <h3 style="color: var(--color-accent); margin: 0; font-size: 16px; font-weight: 800;">ข้อมูลส่วนตัว อสม.</h3>
            <h2 style="color: var(--text-primary); margin: 6px 0; font-size: 22px; font-weight: 800;"><?= htmlspecialchars($vhv['vhv_name']) ?></h2>
            <p style="color: var(--text-secondary); margin: 0; font-size: 14px;">
                หมู่ที่ <?= $vhv['vhv_moo'] ?> • เบอร์โทร/ไอดี: <?= htmlspecialchars($vhvId) ?>
            </p>
        </div>

        <?php if ($message): ?>
            <div style="background-color: rgba(16, 185, 129, 0.15); border: 2px solid var(--color-green); color: var(--color-green); padding: 16px; border-radius: var(--border-radius); margin-bottom: 20px; font-weight: bold; font-size: 15px; text-align: center;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 20px; font-weight: bold; font-size: 15px; text-align: center;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Edit profile card -->
        <div class="card-dark" style="margin-bottom: 24px; padding: 20px;">
            <h3 style="color: var(--color-accent); margin-top: 0; margin-bottom: 16px; font-size: 18px; font-weight: 800;">
                📝 ข้อมูลผู้ใช้งาน
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label class="form-label">เบอร์โทรศัพท์ (ไอดีเข้าระบบ)</label>
                    <input type="text" class="form-select" value="<?= htmlspecialchars($vhvId) ?>" readonly style="background-color: rgba(0,0,0,0.05); color: var(--text-muted); font-weight: normal; cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label class="form-label">ชื่อ-นามสกุล อสม.</label>
                    <input type="text" name="vhv_name" class="form-control" value="<?= htmlspecialchars($vhv['vhv_name']) ?>" required placeholder="ระบุชื่อ-นามสกุล">
                </div>

                <button type="submit" class="btn-giant btn-giant-primary" style="margin-top: 10px; width: 100%;">
                    บันทึกข้อมูลส่วนตัว
                </button>
            </form>
        </div>

        <!-- Change password card -->
        <div class="card-dark" style="margin-bottom: 24px; padding: 20px;">
            <h3 style="color: var(--color-accent); margin-top: 0; margin-bottom: 16px; font-size: 18px; font-weight: 800;">
                🔑 เปลี่ยนรหัสผ่านใหม่
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label class="form-label">รหัสผ่านเดิม</label>
                    <input type="password" name="old_password" class="form-control" required placeholder="ป้อนรหัสเดิม (เริ่มแรกคือ 1234)" autocomplete="current-password">
                </div>

                <div class="form-group">
                    <label class="form-label">รหัสผ่านใหม่</label>
                    <input type="password" name="new_password" class="form-control" required placeholder="รหัสผ่านใหม่" autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label class="form-label">ยืนยันรหัสผ่านใหม่อีกครั้ง</label>
                    <input type="password" name="confirm_password" class="form-control" required placeholder="ยืนยันรหัสใหม่" autocomplete="new-password">
                </div>

                <button type="submit" class="btn-giant btn-giant-secondary" style="margin-top: 10px; width: 100%;">
                    เปลี่ยนรหัสผ่าน
                </button>
            </form>
        </div>

        <a href="../logout.php" style="margin-top: 10px; margin-bottom: 30px; width: 100%; text-align: center; text-decoration: none; display: block; line-height: 52px; background-color: var(--color-red); color: white; border-radius: var(--border-radius); font-weight: 800; box-shadow: var(--neumorph-flat);">
            ออกจากระบบ (Log Out)
        </a>

        <!-- Bottom Navigation Bar -->
        <div class="bottom-nav">
            <a href="index.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                หน้าแรก
            </a>
            <a href="scan.php" class="nav-link nav-scan-fab fab-scan-pulse">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                <span>สแกนบ้าน</span>
            </a>
            <a href="leaderboard.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                กระดานคะแนน
            </a>
            <a href="profile.php" class="nav-link active">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                ข้อมูลส่วนตัว
            </a>
        </div>
    </div>
</body>
</html>
