<?php
// admin/profile.php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$username = $_SESSION['admin_username'];
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

$message = '';
$error = '';

// Fetch current details
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if (!$admin) {
        $admin = [
            'admin_name' => 'ผู้มาเยือน (Visitor)',
            'username' => 'visitor',
            'hoscode' => null
        ];
    }
} catch (\Throwable $e) {
    $error = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $e->getMessage();
}

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $admin_name = trim($_POST['admin_name'] ?? '');
        if (empty($admin_name)) {
            $error = 'กรุณากรอกชื่อ-นามสกุล';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE admin_users SET admin_name = ? WHERE username = ?");
                $stmt->execute([$admin_name, $username]);
                $message = 'อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว';
                // Refresh data
                $admin['admin_name'] = $admin_name;
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
                // Verify old password
                $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE username = ?");
                $stmt->execute([$username]);
                $hash = $stmt->fetchColumn();

                if ($hash && password_verify($old_password, $hash)) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE username = ?");
                    $stmt->execute([$new_hash, $username]);
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

$admin_title = $admin_hoscode ? ($admin['admin_name'] ?? 'แอดมินสาขา') : 'ผู้ดูแลระบบหลัก';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลส่วนตัว & เปลี่ยนรหัสผ่าน - NCDs Prevention Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }
    </style>
</head>

<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="margin-bottom: 4px;">แก้ไขข้อมูลส่วนตัว & เปลี่ยนรหัสผ่าน</h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 15px;">
            สำหรับผู้ใช้งาน: <strong style="color: var(--color-accent);"><?= htmlspecialchars($admin_title) ?>
                (<?= htmlspecialchars($username) ?>)</strong>
        </p>

        <?php if ($message): ?>
            <div
                style="background-color: rgba(16, 185, 129, 0.15); border: 1px solid var(--color-green); color: var(--color-green); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div
                style="background-color: rgba(239, 68, 68, 0.15); border: 1px solid var(--color-red); color: var(--color-red); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Edit Profile Name -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-top: 0; margin-bottom: 20px;">
                    📝 ข้อมูลส่วนตัว
                </h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label class="form-label"
                            style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">ชื่อผู้ใช้งาน
                            (Username)</label>
                        <input type="text" class="form-select" value="<?= htmlspecialchars($username) ?>" readonly
                            style="background-color: rgba(0, 0, 0, 0.1); cursor: not-allowed; font-weight: normal; color: var(--text-muted);">
                    </div>

                    <div class="form-group">
                        <label class="form-label"
                            style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">รหัส
                            รพ.สต. (HOSCODE)</label>
                        <input type="text" class="form-select"
                            value="<?= htmlspecialchars($admin_hoscode ?: 'ทั้งหมด') ?>" readonly
                            style="background-color: rgba(0, 0, 0, 0.1); cursor: not-allowed; font-weight: normal; color: var(--text-muted);">
                    </div>

                    <div class="form-group">
                        <label class="form-label"
                            style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">ชื่อ-นามสกุล
                            ผู้ดูแลระบบ</label>
                        <input type="text" name="admin_name" class="form-control"
                            value="<?= htmlspecialchars($admin['admin_name'] ?? '') ?>" required
                            placeholder="ระบุชื่อ-นามสกุล">
                    </div>

                    <button type="submit" class="btn-primary"
                        style="width: 100%; height: 50px; border-radius: 25px; border: none; background: var(--color-green); color: white; font-weight: bold; cursor: pointer; box-shadow: var(--neumorph-flat); margin-top: 10px;">
                        บันทึกข้อมูลส่วนตัว
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-top: 0; margin-bottom: 20px;">
                    🔑 เปลี่ยนรหัสผ่านใหม่
                </h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label"
                            style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">รหัสผ่านเดิม</label>
                        <input type="password" name="old_password" class="form-control" required
                            placeholder="ป้อนรหัสผ่านปัจจุบัน" autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label"
                            style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">รหัสผ่านใหม่</label>
                        <input type="password" name="new_password" class="form-control" required
                            placeholder="รหัสผ่านใหม่ต้องมีความยาวพอประมาณ" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label"
                            style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">ยืนยันรหัสผ่านใหม่อีกครั้ง</label>
                        <input type="password" name="confirm_password" class="form-control" required
                            placeholder="ป้อนรหัสผ่านใหม่อีกครั้ง" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn-primary"
                        style="width: 100%; height: 50px; border-radius: 25px; border: none; background: var(--color-primary); color: white; font-weight: bold; cursor: pointer; box-shadow: var(--neumorph-flat); margin-top: 10px;">
                        ยืนยันเปลี่ยนรหัสผ่าน
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>