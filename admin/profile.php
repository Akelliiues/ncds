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
    <div class="admin-navbar">
        <a href="index.php" class="admin-logo">NCDs Prevention Portal - Tansum</a>
        <div class="admin-nav-links">
            <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" data-tooltip="แดชบอร์ด">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            </a>
            <?php if (!$admin_hoscode): ?>
                <a href="import_hdc.php" class="<?= basename($_SERVER['PHP_SELF']) == 'import_hdc.php' ? 'active' : '' ?>" data-tooltip="นำเข้าข้อมูล HDC">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                </a>
                <a href="process_etl.php" class="<?= basename($_SERVER['PHP_SELF']) == 'process_etl.php' ? 'active' : '' ?>" data-tooltip="ประมวลผล ETL">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                </a>
            <?php endif; ?>
            <a href="hdc_list.php" class="<?= basename($_SERVER['PHP_SELF']) == 'hdc_list.php' ? 'active' : '' ?>" data-tooltip="คัดกรองความเสี่ยง HDC">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </a>
            <a href="dpac_manager.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dpac_manager.php' ? 'active' : '' ?>" data-tooltip="จัดการโครงการ DPAC">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </a>
            <a href="assignment.php" class="<?= basename($_SERVER['PHP_SELF']) == 'assignment.php' ? 'active' : '' ?>" data-tooltip="มอบหมายงาน อสม.">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
            </a>
            <a href="print_qr.php" class="<?= basename($_SERVER['PHP_SELF']) == 'print_qr.php' ? 'active' : '' ?>" data-tooltip="พิมพ์ QR Code บ้าน">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
            </a>
            <a href="vhv_approval.php" class="<?= basename($_SERVER['PHP_SELF']) == 'vhv_approval.php' ? 'active' : '' ?>" data-tooltip="จัดการผู้ใช้ อสม.">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </a>
            <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" data-tooltip="ข้อมูลส่วนตัว / เปลี่ยนรหัสผ่าน">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </a>
            <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" data-tooltip="รายงานและการพิมพ์">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </a>
            <a href="../logout.php" data-tooltip="ออกจากระบบ" style="color: var(--color-red) !important;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </a>
        </div>
    </div>

    <div style="max-width: 1000px; margin: 40px auto; padding: 0 20px;">
        <h2 style="margin-bottom: 4px;">แก้ไขข้อมูลส่วนตัว & เปลี่ยนรหัสผ่าน</h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 15px;">
            สำหรับผู้ใช้งาน: <strong style="color: var(--color-accent);"><?= htmlspecialchars($admin_title) ?> (<?= htmlspecialchars($username) ?>)</strong>
        </p>

        <?php if ($message): ?>
            <div style="background-color: rgba(16, 185, 129, 0.15); border: 1px solid var(--color-green); color: var(--color-green); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background-color: rgba(239, 68, 68, 0.15); border: 1px solid var(--color-red); color: var(--color-red); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Edit Profile Name -->
            <div class="card-dark">
                <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-top: 0; margin-bottom: 20px;">
                    📝 ข้อมูลส่วนตัว
                </h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">ชื่อผู้ใช้งาน (Username)</label>
                        <input type="text" class="form-select" value="<?= htmlspecialchars($username) ?>" readonly style="background-color: rgba(0, 0, 0, 0.1); cursor: not-allowed; font-weight: normal; color: var(--text-muted);">
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">รหัส รพ.สต. (HOSCODE)</label>
                        <input type="text" class="form-select" value="<?= htmlspecialchars($admin_hoscode ?: 'ทั้งหมด') ?>" readonly style="background-color: rgba(0, 0, 0, 0.1); cursor: not-allowed; font-weight: normal; color: var(--text-muted);">
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">ชื่อ-นามสกุล ผู้ดูแลระบบ</label>
                        <input type="text" name="admin_name" class="form-control" value="<?= htmlspecialchars($admin['admin_name'] ?? '') ?>" required placeholder="ระบุชื่อ-นามสกุล">
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; height: 50px; border-radius: 25px; border: none; background: var(--color-green); color: white; font-weight: bold; cursor: pointer; box-shadow: var(--neumorph-flat); margin-top: 10px;">
                        บันทึกข้อมูลส่วนตัว
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card-dark">
                <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-top: 0; margin-bottom: 20px;">
                    🔑 เปลี่ยนรหัสผ่านใหม่
                </h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">รหัสผ่านเดิม</label>
                        <input type="password" name="old_password" class="form-control" required placeholder="ป้อนรหัสผ่านปัจจุบัน" autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">รหัสผ่านใหม่</label>
                        <input type="password" name="new_password" class="form-control" required placeholder="รหัสผ่านใหม่ต้องมีความยาวพอประมาณ" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">ยืนยันรหัสผ่านใหม่อีกครั้ง</label>
                        <input type="password" name="confirm_password" class="form-control" required placeholder="ป้อนรหัสผ่านใหม่อีกครั้ง" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; height: 50px; border-radius: 25px; border: none; background: var(--color-primary); color: white; font-weight: bold; cursor: pointer; box-shadow: var(--neumorph-flat); margin-top: 10px;">
                        ยืนยันเปลี่ยนรหัสผ่าน
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
