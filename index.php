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
        $_SESSION['vhv_name'] = 'อสม. ใจดี มีสุข (จำลอง สสอ.)';
        $_SESSION['vhv_moo'] = 1;
        $_SESSION['vhid_code'] = '34100101';
        $_SESSION['hoscode'] = '00325';
        $_SESSION['is_leader'] = 0;
        $_SESSION['is_hl_coach'] = 0;
        header("Location: vhv/index.php");
        exit();
    } elseif ($demoRole === 'staff') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = 'demo_staff';
        $_SESSION['admin_hoscode'] = '00325';
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
            if (in_array(strtolower($username), ['visitor', 'executive']) && $password === '123456') {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = strtolower($username);
                $_SESSION['admin_hoscode'] = null; // แอดมินหลัก (เข้าดูได้ทุก รพ.สต.)
                $_SESSION['is_executive'] = true;
                $_SESSION['is_visitor'] = true;
                $_SESSION['admin_role'] = 'executive';
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

            // Determine if executive role
            if ((isset($admin_db['role']) && $admin_db['role'] === 'executive') || in_array(strtolower($username), ['executive', 'visitor'])) {
                $_SESSION['is_executive'] = true;
                $_SESSION['is_visitor'] = true;
                $_SESSION['admin_role'] = 'executive';
            } else {
                $_SESSION['is_executive'] = false;
                $_SESSION['is_visitor'] = false;
                $_SESSION['admin_role'] = 'admin';
            }

            if (function_exists('logUserActivity')) {
                logUserActivity('AUTH', 'เข้าสู่ระบบ (เจ้าหน้าที่ รพ.สต.)', 'เข้าใช้งานแดชบอร์ดจัดการระบบ');
            }

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

                            if (function_exists('logUserActivity')) {
                                logUserActivity('AUTH', 'เข้าสู่ระบบ (อสม.)', 'เข้าใช้งานระบบคัดกรองภาคสนาม');
                            }

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
        :root {
            --public-primary: #0284c7;
            --public-accent: #0ea5e9;
            --public-cyan: #06b6d4;
            --public-green: #10b981;
            --public-amber: #f59e0b;
            --public-rose: #f43f5e;

            /* Neumorphic Soft Light Palette */
            --neu-base: #ebf0f7;
            --neu-card-bg: #ebf0f7;
            --neu-sunken-bg: #e2eaf4;
            --neu-surface-subtle: #f0f5fc;
            --neu-border: rgba(255, 255, 255, 0.75);
            
            --neu-raised: 10px 10px 22px #cad5e2, -10px -10px 22px #ffffff;
            --neu-raised-sm: 5px 5px 12px #cad5e2, -5px -5px 12px #ffffff;
            --neu-raised-xs: 3px 3px 8px #cad5e2, -3px -3px 8px #ffffff;
            --neu-inset: inset 3.5px 3.5px 7px #cad5e2, inset -3.5px -3.5px 7px #ffffff;
            --neu-inset-sm: inset 2px 2px 4px #cad5e2, inset -2px -2px 4px #ffffff;
            
            --text-primary: #0d2c54;
            --text-secondary: #4b5563;
            --text-muted: #8c9ba8;
        }

        /* ซ่อน scrollbar แนวตั้ง แต่ยังคง scroll หน้าจอได้ตามปกติ */
        html, body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }

        html::-webkit-scrollbar,
        body::-webkit-scrollbar,
        *::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }

        body {
            background-color: var(--neu-base);
            color: var(--text-primary);
            font-family: 'Prompt', 'Outfit', 'Sarabun', system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            margin: auto;
            position: relative;
        }

        /* Top Theme Bar */
        .login-top-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 12px;
            gap: 10px;
        }

        .neu-icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            background: var(--neu-card-bg);
            box-shadow: var(--neu-raised-sm);
            border: 1px solid var(--neu-border);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .neu-icon-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--neu-raised);
        }

        .neu-icon-btn:active {
            transform: scale(0.96);
            box-shadow: var(--neu-inset-sm);
        }

        /* Neumorphic Brand Logo Plate */
        .brand-logo-plate {
            width: 76px;
            height: 76px;
            border-radius: 24px;
            background: var(--neu-card-bg);
            box-shadow: var(--neu-raised);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            border: 1px solid var(--neu-border);
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .brand-logo-plate:hover {
            transform: scale(1.06);
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        /* Neumorphic Card Main */
        .neu-login-card {
            background: var(--neu-card-bg);
            box-shadow: var(--neu-raised);
            border-radius: 28px;
            padding: 26px 24px;
            border: 1px solid var(--neu-border);
            position: relative;
            overflow: hidden;
            transition: box-shadow 0.3s ease;
        }

        /* Neumorphic Inset Input Field */
        .neu-input-group {
            position: relative;
            margin-bottom: 16px;
        }

        .neu-input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .neu-input-field {
            width: 100%;
            padding: 13px 16px 13px 46px;
            border-radius: 18px;
            border: 1px solid transparent;
            background: var(--neu-sunken-bg);
            box-shadow: var(--neu-inset);
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
            font-weight: 600;
            outline: none;
            box-sizing: border-box;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .neu-input-field:focus {
            border-color: rgba(2, 132, 199, 0.4);
            box-shadow: var(--neu-inset), 0 0 0 3px rgba(2, 132, 199, 0.25);
        }

        .neu-input-field:focus + .neu-input-icon {
            color: var(--public-primary);
        }

        /* Neumorphic Primary Submit Button */
        .neu-btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--public-primary, #0284c7), #0ea5e9);
            color: #ffffff;
            border: none;
            padding: 13px;
            border-radius: 18px;
            font-size: 15px;
            font-family: inherit;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 4px 4px 12px rgba(2, 132, 199, 0.4), -3px -3px 8px rgba(255, 255, 255, 0.8);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 4px;
            margin-bottom: 14px;
        }

        [data-theme="dark"] .neu-btn-submit {
            box-shadow: 4px 4px 12px rgba(0, 0, 0, 0.5), -2px -2px 6px rgba(255, 255, 255, 0.05);
        }

        .neu-btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 6px 6px 16px rgba(2, 132, 199, 0.5), -4px -4px 10px rgba(255, 255, 255, 0.9);
        }

        .neu-btn-submit:active {
            transform: scale(0.98);
            box-shadow: inset 2px 2px 6px rgba(0, 0, 0, 0.3);
        }

        /* Action Link Cards (Self Screening, Open Data) */
        .neu-action-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--neu-card-bg);
            box-shadow: var(--neu-raised-sm);
            border: 1px solid var(--neu-border);
            padding: 12px 14px;
            border-radius: 18px;
            text-decoration: none;
            margin-bottom: 10px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .neu-action-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--neu-raised);
        }

        .neu-action-card:active {
            transform: scale(0.98);
            box-shadow: var(--neu-inset-sm);
        }

        .neu-action-icon-plate {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: var(--neu-card-bg);
            box-shadow: var(--neu-raised-xs);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            border: 1px solid var(--neu-border);
        }

        /* Virtual Sandbox Pill */
        .neu-pill-btn {
            background: var(--neu-card-bg);
            box-shadow: var(--neu-raised-xs);
            border: 1px solid var(--neu-border);
            color: #d97706;
            border-radius: 50px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            outline: none;
        }

        .neu-pill-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--neu-raised-sm);
        }

        .neu-pill-btn:active {
            transform: scale(0.97);
            box-shadow: var(--neu-inset-sm);
        }

        .neu-badge-role {
            flex: 1;
            background: var(--neu-card-bg);
            box-shadow: var(--neu-raised-sm);
            border: 1px solid var(--neu-border);
            padding: 12px 10px;
            border-radius: 18px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .neu-badge-role:hover {
            transform: translateY(-2px);
            box-shadow: var(--neu-raised);
        }

        .neu-badge-role:active {
            transform: scale(0.97);
            box-shadow: var(--neu-inset-sm);
        }

        /* Modern Page Transition Loading Overlay */
        #page-loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            flex-direction: column;
            opacity: 0;
            transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        #page-loading-overlay.active {
            display: flex;
            opacity: 1;
        }

        .loading-modal-card {
            background: var(--neu-card-bg);
            border: 1px solid var(--neu-border);
            border-radius: 28px;
            padding: 32px 36px;
            box-shadow: var(--neu-raised-lg);
            text-align: center;
            max-width: 340px;
            width: 88%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            transform: scale(0.92);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        #page-loading-overlay.active .loading-modal-card {
            transform: scale(1);
        }

        .loading-spinner-ring {
            position: relative;
            width: 66px;
            height: 66px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loading-spinner-ring::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 4px solid rgba(2, 132, 199, 0.15);
            border-top-color: var(--public-primary, #0284c7);
            border-right-color: #38bdf8;
            animation: ringSpin 0.9s cubic-bezier(0.55, 0.15, 0.45, 0.85) infinite;
        }

        .loading-pulse-icon {
            font-size: 28px;
            animation: iconPulse 1.5s ease-in-out infinite;
        }

        .loading-progress-track {
            width: 100%;
            height: 6px;
            background: var(--neu-sunken-bg);
            box-shadow: var(--neu-inset-sm);
            border-radius: 9999px;
            overflow: hidden;
            margin-top: 4px;
            position: relative;
        }

        .loading-progress-bar {
            position: absolute;
            height: 100%;
            width: 45%;
            background: linear-gradient(90deg, #0284c7, #38bdf8, #10b981);
            border-radius: 9999px;
            animation: shimmerSlide 1.3s ease-in-out infinite;
        }

        @keyframes ringSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.18); }
        }

        @keyframes shimmerSlide {
            0% { left: -45%; width: 30%; }
            50% { left: 35%; width: 50%; }
            100% { left: 105%; width: 30%; }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Brand Header -->
        <div class="login-brand" onclick="openDevModal(event)" title="คลิกเพื่อดูรายละเอียดระบบและทีมพัฒนา"
            style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 16px; cursor: pointer;">
            <div class="brand-logo-plate">
                <img src="assets/icon.png" alt="NCDs Portal Logo" class="brand-logo">
            </div>
            <span style="color: var(--color-accent); font-size: 11.5px; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 2px;">สำนักงานสาธารณสุขอำเภอ<?= DISTRICT_NAME ?> จังหวัด<?= PROVINCE_NAME ?></span>
            <h1 style="font-size: 21px; font-weight: 900; color: var(--text-primary); margin: 2px 0;">ระบบคัดกรอง NCDs Portal</h1>
        </div>

        <!-- Main Neumorphic Card -->
        <div class="neu-login-card">
            <?php if (!empty($error)): ?>
                <div style="background: rgba(239, 68, 68, 0.1); box-shadow: var(--neu-inset-sm); color: #ef4444; padding: 10px 14px; border-radius: 16px; margin-bottom: 16px; font-size: 13.5px; text-align: center; font-weight: 800; border: 1px solid rgba(239, 68, 68, 0.25);">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php" onsubmit="showPageLoading('กำลังเข้าสู่ระบบ', 'กำลังตรวจสอบสิทธิ์การใช้งาน...', '🔐');">
                <!-- Username Input (Sunken Inset) -->
                <div class="neu-input-group">
                    <input type="text" name="username" id="username" class="neu-input-field"
                        placeholder="ชื่อผู้ใช้งาน / รหัส อสม." required autocomplete="username">
                    <div class="neu-input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                </div>

                <!-- Password Input (Sunken Inset) -->
                <div class="neu-input-group" style="margin-bottom: 20px;">
                    <input type="password" name="password" id="password" class="neu-input-field"
                        placeholder="รหัสผ่านเข้าใช้งาน" required autocomplete="current-password">
                    <div class="neu-input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                </div>

                <!-- Submit Button (Floating Convex) -->
                <button type="submit" class="neu-btn-submit">
                    <span>เข้าสู่ระบบ</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                </button>
            </form>

            <!-- Register Link -->
            <div style="text-align: center; margin-bottom: 16px;">
                <a href="vhv/register.php" style="color: var(--color-accent); text-decoration: none; font-weight: 800; font-size: 13.5px; display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 12px; transition: opacity 0.2s;">
                    <?= render_neu_icon('doctor', 'xs', 'disc-blue') ?>
                    <span>ลงทะเบียน อสม. ใหม่</span>
                </a>
            </div>

            <!-- Citizen Self-Screening Portal Entry (Neumorphic Card) -->
            <a href="self_screening.php" onclick="showPageLoading('ประเมินสุขภาพด้วยตนเอง', 'กำลังเตรียมแบบคัดกรองความดัน-เบาหวาน...', '🩺', 'self_screening.php'); return false;" class="neu-action-card">
                <div style="display: flex; align-items: center; gap: 12px; text-align: left;">
                    <div class="neu-action-icon-plate">
                        <img src="assets/img/health_check_icon.png?v=20260824_1" alt="ตรวจสุขภาพตนเอง" style="width: 28px; height: 28px; object-fit: contain;">
                    </div>
                    <div>
                        <div style="color: var(--color-primary); font-size: 13.5px; font-weight: 800;">
                            ประเมินสุขภาพง่ายๆ ด้วยตัวเอง
                        </div>
                        <div style="color: var(--text-secondary); font-size: 11.5px;">เช็คความเสี่ยงความดัน-เบาหวาน 1 นาทีรู้ผล</div>
                    </div>
                </div>
                <div class="neu-action-icon-plate" style="width: 32px; height: 32px; font-size: 14px; color: var(--color-primary);">
                    👉
                </div>
            </a>

            <!-- Public Open Data & Executive Cockpit Entry (Neumorphic Card) -->
            <a href="public_dashboard.php" onclick="showPageLoading('ศูนย์ข้อมูลสุขภาพ NCDs', 'กำลังประมวลผลสถิติและผลการคัดกรอง...', '📊', 'public_dashboard.php'); return false;" class="neu-action-card">
                <div style="display: flex; align-items: center; gap: 12px; text-align: left;">
                    <div class="neu-action-icon-plate" style="color: #0284c7;">
                        📊
                    </div>
                    <div>
                        <div style="color: var(--color-primary, #0284c7); font-size: 13.5px; font-weight: 800;">
                            ศูนย์ข้อมูลสถิติสุขภาพ NCDs
                        </div>
                        <div style="color: var(--text-secondary); font-size: 11.5px;">ผลงานคัดกรองและสถิติภาพรวมอำเภอ</div>
                    </div>
                </div>
                <div class="neu-action-icon-plate" style="width: 32px; height: 32px; font-size: 14px; color: #0284c7;">
                    📈
                </div>
            </a>

            <!-- Collapsible Demo Sandbox Mode Trigger -->
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px dashed rgba(148, 163, 184, 0.35); text-align: center;">
                <button type="button" id="btn-toggle-demo" onclick="toggleDemoSelector()" class="neu-pill-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2"/><path d="M8.5 2h7"/><path d="M7 16h10"/></svg>
                    <span>ทดลองใช้งานระบบ (Virtual Mode)</span>
                    <span id="demo-chevron" style="font-size: 10px; transition: transform 0.2s ease;">▼</span>
                </button>

                <!-- Expandable Role Selection -->
                <div id="demo-options-container" style="display: none; margin-top: 14px;">
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 10px; font-weight: 600;">
                        เลือกบทบาทเพื่อจำลองใช้งานด้วยข้อมูลสมมติ (Mockup Data):
                    </div>
                    <div style="display: flex; gap: 12px; justify-content: center;">
                        <a href="index.php?demo_role=vhv" class="neu-badge-role" style="color: #10b981;">
                            <div class="neu-action-icon-plate" style="color: #10b981; width: 40px; height: 40px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M16 11h6"/></svg>
                            </div>
                            <span>อสม.</span>
                        </a>
                        <a href="index.php?demo_role=staff" class="neu-badge-role" style="color: #0284c7;">
                            <div class="neu-action-icon-plate" style="color: #0284c7; width: 40px; height: 40px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"/><path d="M10 9h4"/><path d="M12 7v4"/></svg>
                            </div>
                            <span>เจ้าหน้าที่ รพ.สต.</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Info -->
        <div style="text-align: center; margin-top: 18px; color: var(--text-muted); font-size: 11.5px; line-height: 1.5;">

            <div style="margin-top: 8px; display: flex; justify-content: center; gap: 12px; align-items: center; flex-wrap: wrap;">
                <a href="about.php" style="color: var(--color-accent); text-decoration: none; font-weight: 700;">
                    ℹ️ เกี่ยวกับผู้พัฒนา & ข้อมูลระบบ
                </a>
                <span style="color: var(--text-muted); opacity: 0.5;">|</span>
                <a href="manual.php" style="color: var(--color-accent); text-decoration: none; font-weight: 700;">
                    📖 คู่มือการใช้งานระบบ
                </a>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/config/dev_modal.php'; ?>

    <!-- Fullscreen Loading Overlay for Smooth Transitions -->
    <div id="page-loading-overlay">
        <div class="loading-modal-card">
            <div class="loading-spinner-ring">
                <span class="loading-pulse-icon" id="loading-icon">📊</span>
            </div>
            <div>
                <div style="font-size: 16px; font-weight: 800; color: var(--text-primary); margin-bottom: 5px;" id="loading-title">
                    ศูนย์ข้อมูลสุขภาพ NCDs
                </div>
                <div style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.5;" id="loading-subtitle">
                    กำลังประมวลผลสถิติและผลการคัดกรอง...
                </div>
            </div>
            <div class="loading-progress-track">
                <div class="loading-progress-bar"></div>
            </div>
        </div>
    </div>

    <script>
        function showPageLoading(title, subtitle, icon, targetUrl) {
            const overlay = document.getElementById('page-loading-overlay');
            if (!overlay) return;
            if (title) document.getElementById('loading-title').innerText = title;
            if (subtitle) document.getElementById('loading-subtitle').innerText = subtitle;
            if (icon) document.getElementById('loading-icon').innerText = icon;
            
            overlay.style.display = 'flex';
            requestAnimationFrame(() => {
                overlay.classList.add('active');
            });

            if (targetUrl) {
                setTimeout(() => {
                    window.location.href = targetUrl;
                }, 50);
            }
        }

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