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
            unset($_SESSION['is_demo_mode']);
            unset($_SESSION['demo_role']);
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
                            unset($_SESSION['is_demo_mode']);
                            unset($_SESSION['demo_role']);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NCDs Portal">
    <meta name="application-name" content="NCDs Portal">
    <meta name="theme-color" content="#0d2c54">
    <title>เข้าสู่ระบบ NCDs <?= DISTRICT_NAME ?> - คัดกรอง ดูแล ป้องกันเพื่อสุขภาพที่ดีอย่างยั่งยืน</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="apple-touch-icon" href="assets/icon.png">
    <link rel="manifest" href="manifest.json">
    <script src="assets/js/app.js"></script>
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
            <img src="assets/icon.png" alt="NCDs Portal Logo" class="brand-logo">
            <span>สำนักงานสาธารณสุขอำเภอ<?= DISTRICT_NAME ?></span>
            <h1>ระบบคัดกรอง NCDs Portal</h1>
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

            <!-- Citizen Self-Screening Portal Entry -->
            <div style="margin-top: 12px; margin-bottom: 6px;">
                <a href="self_screening.php" style="display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(16, 185, 129, 0.08)); border: 1.5px solid rgba(59, 130, 246, 0.3); padding: 12px 14px; border-radius: 16px; text-decoration: none; transition: transform 0.2s; box-shadow: var(--neumorph-flat);">
                    <div style="display: flex; align-items: center; gap: 10px; text-align: left;">
                        <span style="font-size: 26px;">🩺</span>
                        <div>
                            <div style="color: var(--color-primary); font-size: 14px; font-weight: 800;">ประเมินสุขภาพตนเองเบื้องต้น</div>
                            <div style="color: var(--text-secondary); font-size: 11.5px;">เช็คความเสี่ยงความดัน-เบาหวานด้วยตัวเอง</div>
                        </div>
                    </div>
                    <span style="color: var(--color-primary); font-weight: 800; font-size: 16px;">→</span>
                </a>
            </div>

            <!-- Collapsible Demo Sandbox Mode Trigger -->
            <div style="margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--border-color, rgba(148, 163, 184, 0.25)); text-align: center;">
                <button type="button" id="btn-toggle-demo" onclick="toggleDemoSelector()" style="
                    background: rgba(245, 158, 11, 0.07);
                    border: 1px solid rgba(245, 158, 11, 0.35);
                    color: #D97706;
                    border-radius: 20px;
                    padding: 7px 16px;
                    font-size: 13px;
                    font-weight: 700;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 7px;
                    transition: all 0.2s ease;
                    outline: none;
                " onmouseover="this.style.background='rgba(245, 158, 11, 0.14)'" onmouseout="this.style.background='rgba(245, 158, 11, 0.07)'">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2"/><path d="M8.5 2h7"/><path d="M7 16h10"/></svg>
                    <span>ทดลองใช้งานระบบ (Virtual Mode)</span>
                    <span id="demo-chevron" style="font-size: 10px; transition: transform 0.2s ease;">▼</span>
                </button>

                <!-- Expandable Role Selection -->
                <div id="demo-options-container" style="display: none; margin-top: 14px;">
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 8px; font-weight: 600;">
                        เลือกบทบาทเพื่อจำลองใช้งานด้วยข้อมูลสมมติ (Mockup Data):
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <a href="index.php?demo_role=vhv" style="
                            flex: 1;
                            background: var(--demo-vhv-bg, rgba(16, 185, 129, 0.08));
                            border: 1.5px solid var(--color-green, #10B981);
                            color: var(--color-green, #10B981);
                            padding: 10px 8px;
                            border-radius: 14px;
                            font-size: 13px;
                            font-weight: 700;
                            text-decoration: none;
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            gap: 5px;
                            transition: transform 0.15s ease, box-shadow 0.15s ease;
                            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.15);
                        " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                            <div style="
                                width: 36px;
                                height: 36px;
                                border-radius: 50%;
                                background: rgba(16, 185, 129, 0.15);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            ">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M16 11h6"/></svg>
                            </div>
                            <span>อสม.</span>
                        </a>
                        <a href="index.php?demo_role=staff" style="
                            flex: 1;
                            background: var(--demo-staff-bg, rgba(59, 130, 246, 0.08));
                            border: 1.5px solid var(--color-primary, #3B82F6);
                            color: var(--color-primary, #3B82F6);
                            padding: 10px 8px;
                            border-radius: 14px;
                            font-size: 13px;
                            font-weight: 700;
                            text-decoration: none;
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            gap: 5px;
                            transition: transform 0.15s ease, box-shadow 0.15s ease;
                            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.15);
                        " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                            <div style="
                                width: 36px;
                                height: 36px;
                                border-radius: 50%;
                                background: rgba(59, 130, 246, 0.15);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            ">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"/><path d="M10 9h4"/><path d="M12 7v4"/></svg>
                            </div>
                            <span>เจ้าหน้าที่ รพ.สต.</span>
                        </a>
                    </div>
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

    <script>
        function toggleDemoSelector() {
            const container = document.getElementById('demo-options-container');
            const chevron = document.getElementById('demo-chevron');
            if (container.style.display === 'none' || container.style.display === '') {
                container.style.display = 'block';
                chevron.innerText = '▲';
            } else {
                container.style.display = 'none';
                chevron.innerText = '▼';
            }
        }
    </script>
</body>

</html>