<?php
// vhv/manual.php (Mobile-Optimized VHV & Citizen Manual with Claymorphism & Neumorphism)
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['vhv_id']) && !defined('ALLOW_GUEST_MANUAL')) {
    header("Location: ../index.php");
    exit();
}

$is_vhv = isset($_SESSION['vhv_id']);
$vhvName = $is_vhv ? $_SESSION['vhv_name'] : '';

$path_prefix = defined('ALLOW_GUEST_MANUAL') ? '' : '../';
$back_url = defined('ALLOW_GUEST_MANUAL') ? ($is_vhv ? 'vhv/index.php' : 'index.php') : 'index.php';

require_once __DIR__ . '/../config/db.php';
$district = defined('DISTRICT_NAME') ? DISTRICT_NAME : 'ตาลสุม';
$province = defined('PROVINCE_NAME') ? PROVINCE_NAME : 'อุบลราชธานี';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NCDs Portal">
    <meta name="application-name" content="NCDs Portal">
    <meta name="theme-color" content="#0d2c54">
    <title>คู่มือการใช้งานระบบ NCDs Portal - อำเภอ<?= htmlspecialchars($district) ?></title>
    <link rel="stylesheet" href="<?= $path_prefix ?>assets/css/style.css">
    <link rel="apple-touch-icon" href="<?= $path_prefix ?>assets/icon.png">
    <link rel="manifest" href="<?= $path_prefix ?>manifest.json">
    <script src="<?= $path_prefix ?>assets/js/app.js"></script>

    <style>
        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-base);
            min-height: 100vh;
            padding-bottom: 40px;
        }

        .mobile-wrapper {
            max-width: 540px;
            margin: 0 auto;
            padding: 14px 16px 40px 16px;
        }

        /* Top Bar */
        .manual-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .btn-back-pill {
            color: var(--color-accent);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-card);
            padding: 8px 16px;
            border-radius: 50px;
            box-shadow: var(--neumorph-flat);
            transition: transform 0.2s;
        }
        .btn-back-pill:active {
            transform: scale(0.95);
            box-shadow: var(--neumorph-inset);
        }
    </style>
</head>
<body class="vhv-accessibility">
    <div class="mobile-wrapper">

        <!-- Top Navigation -->
        <div class="manual-top-bar">
            <a href="<?= htmlspecialchars($back_url) ?>" class="btn-back-pill">
                ← ย้อนกลับ
            </a>
            <span style="font-weight: 800; color: var(--color-accent); font-size: 13.5px;">
                NCDs Portal
            </span>
        </div>

        <?php include __DIR__ . '/manual_partial.php'; ?>

    </div>
</body>
</html>
