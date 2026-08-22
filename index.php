<?php
// index.php (Root - Unified Login & Role Dispatcher)
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

// Exit demo mode if requested
if (isset($_GET['exit_demo'])) {
    unset($_SESSION['is_demo_mode']);
    unset($_SESSION['demo_role']);
    session_destroy();
    header("Location: index.php");
    exit();
}

// Handle Demo Sandbox Role Selection
if (isset($_GET['demo_role']) || isset($_POST['demo_role'])) {
    $demoRole = trim($_GET['demo_role'] ?? $_POST['demo_role'] ?? '');
    $_SESSION['is_demo_mode'] = true;
    $_SESSION['demo_role'] = $demoRole;

    if ($demoRole === 'vhv') {
        $_SESSION['vhv_id'] = 'DEMO_1001';
        $_SESSION['vhv_name'] = 'อสม. ใจดี มีสุข (โหมดจำลอง)';
        $_SESSION['vhv_moo'] = 1;
        $_SESSION['vhid_code'] = '34100101';
        $_SESSION['hoscode'] = '99999';
        $_SESSION['is_leader'] = 0;
        $_SESSION['is_hl_coach'] = 0;
        header("Location: vhv/index.php");
        exit();
    } elseif ($demoRole === 'staff') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = 'demo_staff';
        $_SESSION['admin_hoscode'] = '99999';
        $_SESSION['is_visitor'] = false;
        header("Location: admin/index.php");
        exit();
    } elseif ($demoRole === 'admin') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = 'demo_admin';
        $_SESSION['admin_hoscode'] = null;
        $_SESSION['is_visitor'] = false;
        header("Location: admin/index.php");
        exit();
    }
}

// If already logged in, redirect to respective dashboard
if (isset($_SESSION['vhv_id'])) {
    header("Location: vhv/index.php");
    exit();
} elseif (isset($_SESSION['admin_logged_in'])) {
    header("Location: admin/index.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_connected = false;
    $db_error = '';
    try {
        $allow_db_failure = true;
        require_once __DIR__ . '/config/db.php';
        $db_connected = true;
    } catch (\Throwable $e) {
        $db_error = $e->getMessage();
    }

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกรหัสประจำตัว หรือชื่อผู้ใช้ และรหัสผ่าน';
    } else {
        // 1. Check Admin Credentials (Staff / Administrator role)
        $is_admin = false;
        $admin_hoscode = null;

        if ($db_connected) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
                $stmt->execute([strtolower($username)]);
                $admin_db = $stmt->fetch();
                if ($admin_db && password_verify($password, $admin_db['password_hash'])) {
                    if (isset($admin_db['status']) && $admin_db['status'] === 'suspended') {
                        $error = 'บัญชีผู้ใช้งานนี้ถูกระงับสิทธิ์การใช้งานชั่วคราว';
                    } else {
                        $is_admin = true;
                        $admin_hoscode = $admin_db['hoscode'];
                    }
                }
            } catch (\Throwable $e) {
                // Fail silently and use fallback
            }
        }

        // Fallback checks (if database query didn't match or failed)
        if (!$is_admin && empty($error)) {
            if (strtolower($username) === 'visitor' && $password === '123456') {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = 'visitor';
                $_SESSION['admin_hoscode'] = null; // แอดมินหลัก (เข้าดูได้ทุก รพ.สต.)
                $_SESSION['is_visitor'] = true;
                header("Location: admin/index.php");
                exit();
            }
        }

        if ($is_admin) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = strtolower($username);
            $_SESSION['admin_hoscode'] = $admin_hoscode;
            header("Location: admin/index.php");
            exit();
        } else {
            // 2. Check VHV Credentials (อสม. role)
            if (!$db_connected) {
                $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูลระบบ: ' . $db_error;
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM vhv_users WHERE vhv_id = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();

                    // ป้องกันการใช้งานบัญชี อสม. ทดสอบเมื่อปิด Sandbox Mode
                    if ($user && !isSandboxMode($user['hoscode']) && in_array($user['vhv_id'], ['1001', '1002', '1003'])) {
                        $user = false;
                    }

                    if ($user && password_verify($password, $user['password_hash'])) {
                        // Check approval status
                        if (isset($user['approved']) && $user['approved'] == 0) {
                            $error = 'บัญชี อสม. นี้อยู่ระหว่างรอการอนุมัติการใช้งานจากผู้ดูแลระบบ';
                        } else {
                            $_SESSION['vhv_id'] = $user['vhv_id'];
                            $_SESSION['vhv_name'] = $user['vhv_name'];
                            $_SESSION['vhv_moo'] = $user['vhv_moo'];
                            $_SESSION['vhid_code'] = $user['vhid_code'];
                            $_SESSION['hoscode'] = $user['hoscode'];
                            $_SESSION['is_leader'] = intval($user['is_leader']);
                            $_SESSION['is_hl_coach'] = (bool) $user['is_hl_coach'];

                            header("Location: vhv/index.php");
                            exit();
                        }
                    } else {
                        $error = 'ชื่อผู้ใช้/รหัส อสม. หรือ รหัสผ่านไม่ถูกต้อง';
                    }
                } catch (\PDOException $e) {
                    $error = 'เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>เข้าสู่ระบบ NCDs <?= DISTRICT_NAME ?> - คัดกรอง ดูแล ป้องกันเพื่อสุขภาพที่ดีอย่างยั่งยืน</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="vhv/manifest.json">
    <style>
        html, body {
            overflow: hidden;
            height: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-base);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 12px;
        }

        .login-brand h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 4px 0;
        }

        .login-brand span {
            color: var(--color-accent);
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .brand-logo {
            width: 80px;
            height: auto;
            margin-bottom: 8px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.12));
        }
    </style>
</head>

<body class="vhv-accessibility">
    <div class="login-container">
        <div class="login-brand"
            style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 12px;">
            <img src="assets/icon.png" alt="NCDs Prevention Logo" class="brand-logo">
            <span>สำนักงานสาธารณสุขอำเภอ<?= DISTRICT_NAME ?></span>
            <h1>ระบบคัดกรอง NCD Portal</h1>
        </div>

        <div class="card-dark" style="margin-bottom: 0; padding: 20px;">
            <h3 style="text-align: center; margin-top: 0; margin-bottom: 16px; color: var(--color-accent); font-weight: 800; font-size: 18px;">
                ลงชื่อเข้าใช้งานระบบ</h3>

            <?php if (!empty($error)): ?>
                <div
                    style="background-color: rgba(239, 68, 68, 0.15); border: 1.5px solid var(--color-red); color: var(--color-red); padding: 8px; border-radius: var(--border-radius); margin-bottom: 12px; font-size: 13.5px; text-align: center; font-weight: bold;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div style="margin-bottom: 12px;">
                    <label for="username"
                        style="display: block; margin-bottom: 6px; color: var(--text-secondary); font-weight: 600; font-size: 13.5px;">ชื่อผู้ใช้ หรือ รหัส อสม.</label>
                    <input type="text" name="username" id="username" class="input-large"
                        placeholder="ชื่อผู้ใช้งาน / รหัส อสม. 10 หลัก" required autocomplete="username" style="padding: 10px 14px; font-size: 14px; height: auto;">
                </div>

                <div style="margin-bottom: 18px;">
                    <label for="password"
                        style="display: block; margin-bottom: 6px; color: var(--text-secondary); font-weight: 600; font-size: 13.5px;">รหัสผ่าน</label>
                    <input type="password" name="password" id="password" class="input-large"
                        placeholder="รหัสผ่านเข้าใช้งาน" required autocomplete="current-password" style="padding: 10px 14px; font-size: 14px; height: auto;">
                </div>

                <button type="submit" class="btn-giant btn-giant-primary" style="margin-bottom: 12px; padding: 12px; font-size: 16px; height: auto;">
                    เข้าสู่ระบบ
                </button>
            </form>
            <div style="text-align: center; margin-bottom: 12px;">
                <a href="vhv/register.php"
                    style="color: var(--color-accent); text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block;">
                    📝 ลงทะเบียน อสม. ใหม่
                </a>
            </div>

            <!-- Demo Sandbox Mode Selector -->
            <div style="margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--border-color, #334155); text-align: center;">
                <div style="font-size: 12px; font-weight: 700; color: #F59E0B; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 4px;">
                    <span>🧪</span> <span>ทดลองใช้งานเสมือนจริง (Demo Sandbox)</span>
                </div>
                <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                    <a href="index.php?demo_role=vhv" style="
                        flex: 1 1 30%;
                        min-width: 90px;
                        background: rgba(16, 185, 129, 0.12);
                        border: 1px solid #10B981;
                        color: #10B981;
                        padding: 6px 4px;
                        border-radius: 8px;
                        font-size: 11px;
                        font-weight: 700;
                        text-decoration: none;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        gap: 2px;
                    ">
                        <span style="font-size: 14px;">🟩</span>
                        <span>อสม.</span>
                    </a>
                    <a href="index.php?demo_role=staff" style="
                        flex: 1 1 30%;
                        min-width: 90px;
                        background: rgba(59, 130, 246, 0.12);
                        border: 1px solid #3B82F6;
                        color: #60A5FA;
                        padding: 6px 4px;
                        border-radius: 8px;
                        font-size: 11px;
                        font-weight: 700;
                        text-decoration: none;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        gap: 2px;
                    ">
                        <span style="font-size: 14px;">🟦</span>
                        <span>รพ.สต.</span>
                    </a>
                    <a href="index.php?demo_role=admin" style="
                        flex: 1 1 30%;
                        min-width: 90px;
                        background: rgba(239, 68, 68, 0.12);
                        border: 1px solid #EF4444;
                        color: #FCA5A5;
                        padding: 6px 4px;
                        border-radius: 8px;
                        font-size: 11px;
                        font-weight: 700;
                        text-decoration: none;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        gap: 2px;
                    ">
                        <span style="font-size: 14px;">🟥</span>
                        <span>Admin</span>
                    </a>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 16px; color: var(--text-muted); font-size: 11px; line-height: 1.4;">
            ระบบจัดการคัดกรองโรคเรื้อรังเชิงรุก NCDs 2026<br>
            อำเภอ<?= DISTRICT_NAME ?> จังหวัด<?= PROVINCE_NAME ?><br>
            <div style="margin-top: 6px; display: flex; justify-content: center; gap: 12px; align-items: center;">
                <a href="about.php" onclick="openDevModal(event)" style="color: var(--color-accent); text-decoration: none; font-weight: bold;">
                    ℹ️ เกี่ยวกับผู้พัฒนา
                </a>
                <span style="color: var(--border-color); font-size: 10px;">|</span>
                <a href="manual.php" style="color: var(--color-accent); text-decoration: none; font-weight: bold;">
                    📖 คู่มือการใช้งานระบบ
                </a>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/config/dev_modal.php'; ?>
</body>

</html>