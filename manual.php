<?php
// manual.php (Root - Unified System User Manual)
require_once __DIR__ . '/config/session.php';

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$is_admin) {
    define('ALLOW_GUEST_MANUAL', true);
    require_once __DIR__ . '/vhv/manual.php';
    exit();
}

// ตรวจสอบบทบาทผู้ใช้จากเซสชันเพื่อตั้งแท็บและปุ่มย้อนกลับให้สอดคล้องโดยอัตโนมัติ (เฉพาะเมื่อล็อกอินเป็นแอดมิน)
$default_tab = 'admin';
$back_url = 'admin/index.php';
if (isset($_SESSION['is_visitor']) && $_SESSION['is_visitor'] === true) {
    $user_role_label = 'เจ้าหน้าที่ (โหมดผู้มาเยือน)';
} else {
    $user_role_label = 'ผู้ดูแลระบบ/เจ้าหน้าที่ (' . htmlspecialchars($_SESSION['admin_username']) . ')';
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คู่มือการใช้งานระบบคัดกรอง NCDs Portal - อำเภอตาลสุม</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --color-primary-rgb: 13, 44, 84;
            --color-green-rgb: 16, 185, 129;
            --color-yellow-rgb: 245, 158, 11;
            --color-red-rgb: 239, 68, 68;
        }

        /* Force allow parent scroll tracking to enable CSS position: sticky */
        html,
        body {
            overflow: visible !important;
            overflow-x: clip !important;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-base);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .manual-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px 80px 20px;
        }

        .manual-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 32px 24px;
            border-radius: var(--border-radius);
            background: var(--bg-card);
            box-shadow: var(--neumorph-flat);
            position: relative;
            overflow: hidden;
        }

        .manual-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #1e40af, var(--color-green), #ef4444);
        }

        .manual-header img {
            width: 90px;
            height: auto;
            margin-bottom: 16px;
            filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.15));
        }

        .manual-header h1 {
            font-size: 34px;
            margin: 10px 0;
            color: var(--text-primary);
            font-weight: 800;
        }

        .manual-header p {
            color: var(--text-secondary);
            font-size: 16px;
            margin: 4px 0 20px 0;
            font-weight: 600;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--bg-darker);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 14.5px;
            font-weight: 800;
            color: var(--color-primary);
            box-shadow: var(--neumorph-inset);
        }

        /* Neumorphic Navigation Tabs */
        .manual-tabs {
            display: flex;
            gap: 20px;
            margin-bottom: 35px;
            background-color: var(--bg-card);
            padding: 8px;
            border-radius: 30px;
            box-shadow: var(--neumorph-inset);
        }

        .manual-tab-btn {
            flex: 1;
            padding: 20px 24px;
            font-size: 19px;
            font-weight: 800;
            color: var(--text-secondary);
            background: none;
            border: none;
            cursor: pointer;
            border-radius: 24px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .manual-tab-btn.active {
            background-color: var(--bg-main);
            color: var(--color-primary);
            box-shadow: var(--neumorph-flat);
        }

        .manual-tab-btn svg {
            width: 24px;
            height: 24px;
            stroke-width: 2.5;
        }

        /* Layout Grid */
        .manual-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            align-items: start;
        }

        /* Sidebar Navigation & Sticky Behavior */
        .sidebar-nav {
            background-color: var(--bg-card);
            padding: 24px;
            border-radius: var(--border-radius);
            box-shadow: var(--neumorph-flat);
            position: sticky;
            top: 20px;
            align-self: start;
            max-height: calc(100vh - 60px);
            overflow-y: auto;
            scrollbar-width: none;
            /* Hide scrollbar for clean aesthetics */
        }

        .sidebar-nav::-webkit-scrollbar {
            display: none;
            /* Hide scrollbar for Chrome/Safari */
        }

        @media (max-width: 992px) {
            .manual-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            /* Transform sidebar to floating horizontal sticky bar on mobile */
            .sidebar-nav {
                position: sticky;
                top: 12px;
                z-index: 1000;
                background-color: rgba(238, 242, 247, 0.96);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                padding: 10px 14px;
                margin: 0 0 24px 0; /* Align with layout margins, no negative margins */
                border-radius: 20px;
                box-shadow: var(--neumorph-flat);
                max-height: none;
                overflow-y: visible;
                border: 1px solid rgba(255, 255, 255, 0.5);
            }

            .sidebar-nav h3 {
                display: none !important;
                /* Hide header text on mobile to save vertical space */
            }

            .sidebar-menu {
                display: flex;
                flex-direction: row;
                overflow-x: auto;
                white-space: nowrap;
                gap: 12px;
                padding: 4px 6px;
                scrollbar-width: none;
                /* Hide horizontal scrollbar */
                -webkit-overflow-scrolling: touch;
                position: relative; /* Ensure offsetParent for offsetLeft is this container */
            }

            .sidebar-menu::-webkit-scrollbar {
                display: none;
            }

            .sidebar-menu li {
                margin-bottom: 0;
                display: inline-block;
            }

            .sidebar-menu a {
                padding: 8px 16px;
                font-size: 13.5px;
                border-radius: 50px;
                background-color: var(--bg-card);
                box-shadow: var(--neumorph-flat);
            }

            .sidebar-menu a:hover,
            .sidebar-menu a.active {
                padding-left: 16px !important;
                /* Lock padding stretch on mobile horizontal scroll */
                box-shadow: var(--neumorph-inset);
            }

            /* Responsive overrides for smaller viewports to make reading easier on mobile */
            .manual-wrapper {
                padding: 15px 10px 80px 10px;
            }

            .manual-header {
                padding: 24px 16px;
                margin-bottom: 24px;
            }

            .manual-header h1 {
                font-size: 25px;
            }

            .manual-header p {
                font-size: 14.5px;
                margin-bottom: 16px;
            }

            .manual-tabs {
                gap: 10px;
                margin-bottom: 25px;
                padding: 6px;
                border-radius: 20px;
            }

            .manual-tab-btn {
                padding: 14px 12px;
                font-size: 14.5px;
                border-radius: 16px;
                gap: 6px;
            }

            .manual-tab-btn svg {
                width: 18px;
                height: 18px;
            }

            .content-card {
                padding: 22px 14px;
                border-radius: 20px;
                box-shadow: none; /* Soften shadows on mobile margins */
                background-color: transparent;
            }

            section {
                padding: 24px 16px;
                margin-bottom: 24px;
                border-radius: 20px;
                background-color: var(--bg-card);
                box-shadow: var(--neumorph-flat);
                scroll-margin-top: 95px !important; /* Offset for mobile sticky menu to prevent content clipping */
            }

            .section-title {
                font-size: 19px;
                gap: 10px;
                margin-bottom: 18px;
            }

            .title-icon-container {
                width: 38px;
                height: 38px;
                border-radius: 10px;
            }

            .title-icon-container svg {
                width: 18px;
                height: 18px;
            }

            .step-item {
                padding-left: 42px;
                margin-bottom: 24px;
            }

            .step-number {
                width: 30px;
                height: 30px;
                font-size: 13.5px;
                top: 0;
            }

            .step-item::before {
                left: 14px;
                top: 32px;
                bottom: -20px;
            }

            .step-content h4 {
                font-size: 15.5px;
            }

            .alert-box {
                padding: 16px;
                gap: 12px;
                margin: 20px 0;
                border-radius: 16px;
            }

            .alert-box svg {
                width: 24px;
                height: 24px;
            }

            .alert-title {
                font-size: 15px;
            }

            .alert-desc {
                font-size: 13.5px;
            }

            table.manual-table th, 
            table.manual-table td {
                padding: 10px 12px;
                font-size: 13px;
            }

            .manual-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 14px;
                padding: 4px;
                margin: 16px 0;
            }
        }

        .sidebar-nav h3 {
            font-size: 17px;
            font-weight: 800;
            color: var(--color-primary);
            margin-bottom: 16px;
            border-bottom: 2px solid var(--bg-darker);
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 8px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14.5px;
            font-weight: 600;
            border-radius: 14px;
            transition: all 0.2s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: var(--bg-darker);
            color: var(--color-primary);
            font-weight: 800;
            padding-left: 18px;
            box-shadow: var(--neumorph-inset);
        }

        .sidebar-menu svg {
            width: 18px;
            height: 18px;
            stroke-width: 2.2;
            flex-shrink: 0;
        }

        /* Content Area */
        .content-card {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Custom Alert Blocks */
        .alert-box {
            padding: 22px;
            border-radius: 20px;
            margin: 24px 0;
            display: flex;
            gap: 16px;
            box-shadow: var(--neumorph-flat);
            align-items: flex-start;
        }

        .alert-box-info {
            background-color: rgba(13, 44, 84, 0.04);
            border-left: 6px solid var(--color-primary);
        }

        .alert-box-info svg {
            stroke: var(--color-primary);
        }

        .alert-box-success {
            background-color: rgba(16, 185, 129, 0.04);
            border-left: 6px solid var(--color-green);
        }

        .alert-box-success svg {
            stroke: var(--color-green);
        }

        .alert-box-warning {
            background-color: rgba(245, 158, 11, 0.04);
            border-left: 6px solid var(--color-yellow);
        }

        .alert-box-warning svg {
            stroke: var(--color-yellow);
        }

        .alert-box-danger {
            background-color: rgba(239, 68, 68, 0.04);
            border-left: 6px solid var(--color-red);
        }

        .alert-box-danger svg {
            stroke: var(--color-red);
        }

        .alert-title {
            font-weight: 800;
            font-size: 16px;
            margin-bottom: 6px;
            color: var(--text-primary);
        }

        .alert-desc {
            font-size: 14.5px;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.6;
        }

        .alert-box svg {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
        }

        /* Section Styling */
        section {
            background-color: var(--bg-card);
            padding: 35px;
            border-radius: var(--border-radius);
            box-shadow: var(--neumorph-flat);
            margin-bottom: 35px;
            scroll-margin-top: 30px;
            border: 1px solid transparent;
            transition: border-color var(--transition-speed);
        }

        section:hover {
            border-color: rgba(13, 44, 84, 0.05);
        }

        section:last-of-type {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--color-primary);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 2px solid rgba(13, 44, 84, 0.08);
            padding-bottom: 12px;
        }

        /* Custom Section Header Icon Container */
        .title-icon-container {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background-color: var(--bg-card);
            box-shadow: var(--neumorph-flat);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .title-icon-container svg {
            width: 24px;
            height: 24px;
            stroke-width: 2.2;
            stroke: var(--color-primary);
        }

        .section-title span.title-text {
            flex-grow: 1;
        }

        .section-title span.number {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-muted);
            background-color: var(--bg-darker);
            padding: 4px 10px;
            border-radius: 8px;
            box-shadow: var(--neumorph-inset);
        }

        /* Step by Step list */
        .step-list {
            margin: 24px 0;
            padding-left: 0;
            list-style: none;
        }

        .step-item {
            position: relative;
            padding-left: 50px;
            margin-bottom: 28px;
        }

        .step-item::before {
            content: '';
            position: absolute;
            left: 17px;
            top: 36px;
            bottom: -24px;
            width: 2px;
            background-color: var(--bg-darker);
        }

        .step-item:last-child::before {
            display: none;
        }

        .step-number {
            position: absolute;
            left: 0;
            top: 2px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--bg-card);
            box-shadow: var(--neumorph-flat);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 15px;
            color: var(--color-primary);
            z-index: 2;
            border: 2px solid var(--color-primary);
        }

        .step-content h4 {
            margin: 0 0 8px 0;
            font-size: 17px;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .step-content p {
            margin: 0;
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Custom Table */
        .manual-table-container {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--neumorph-inset);
            background-color: var(--bg-card);
            padding: 8px;
            margin: 24px 0;
        }

        table.manual-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.manual-table th,
        table.manual-table td {
            padding: 14px 18px;
            text-align: left;
            font-size: 14.5px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }

        table.manual-table th {
            background-color: var(--bg-darker);
            color: var(--color-primary);
            font-weight: 800;
            border-radius: 10px;
        }

        table.manual-table tbody tr:last-child td {
            border-bottom: none;
        }

        .btn-manual-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 800;
            color: var(--color-primary);
            background-color: var(--bg-card);
            border-radius: 50px;
            text-decoration: none;
            box-shadow: var(--neumorph-flat);
            transition: all var(--transition-speed);
            margin-bottom: 24px;
            border: 1.5px solid transparent;
        }

        .btn-manual-back:hover {
            transform: translateY(-2px);
            border-color: var(--color-primary);
        }

        .btn-manual-back:active {
            box-shadow: var(--neumorph-inset);
            transform: scale(0.98);
        }

        /* Highlight text span */
        .hl-text {
            background-color: rgba(13, 44, 84, 0.08);
            color: var(--color-primary);
            padding: 2px 6px;
            border-radius: 6px;
            font-weight: bold;
            font-family: monospace;
        }

        .hl-green {
            background-color: rgba(16, 185, 129, 0.12);
            color: #047857;
            padding: 2px 6px;
            border-radius: 6px;
            font-weight: bold;
        }

        .hl-red {
            background-color: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
            padding: 2px 6px;
            border-radius: 6px;
            font-weight: bold;
        }

        /* VHV accessibility support overrides */
        .vhv-accessibility .step-content p,
        .vhv-accessibility p,
        .vhv-accessibility li {
            font-size: 15.5px;
        }

        /* Back to top floating button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 48px;
            height: 48px;
            background-color: var(--color-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(13, 44, 84, 0.25);
            border: 1px solid var(--border-color);
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all var(--transition-speed) ease-in-out;
        }
        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }
        .back-to-top:hover {
            background-color: var(--color-accent);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(13, 44, 84, 0.35);
        }
        .back-to-top:active {
            transform: translateY(-1px);
        }

        /* Back to Dashboard floating button */
        .back-to-dashboard {
            position: fixed;
            bottom: 30px;
            right: 90px;
            padding: 0 18px;
            height: 48px;
            background-color: var(--color-accent);
            color: white !important;
            text-decoration: none;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(13, 44, 84, 0.25);
            border: 1px solid var(--border-color);
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all var(--transition-speed) ease-in-out;
            white-space: nowrap;
        }
        .back-to-dashboard.show {
            opacity: 1;
            visibility: visible;
        }
        .back-to-dashboard:hover {
            background-color: var(--color-primary);
            color: white !important;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(13, 44, 84, 0.35);
        }
        .back-to-dashboard:active {
            transform: translateY(-1px);
        }
    </style>
</head>

<body class="vhv-accessibility">
    <div class="manual-wrapper">

        <!-- Back Button -->
        <a href="<?= htmlspecialchars($back_url) ?>" class="btn-manual-back">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            <span>กลับไปหน้าควบคุมระบบ</span>
        </a>

        <!-- Manual Header -->
        <div class="manual-header">
            <img src="assets/icon.png" alt="NCDs Prevention Logo">
            <h1>📖 คู่มือการใช้งานระบบ NCD Portal</h1>
            <p>ระบบจัดการคัดกรองโรคเรื้อรังเชิงรุก อำเภอตาลสุม จังหวัดอุบลราชธานี</p>
            <div class="role-badge">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span>สถานะล็อกอิน: <?= $user_role_label ?></span>
            </div>
        </div>

        <!-- Manual Tab Switcher -->
        <div class="manual-tabs">
            <button class="manual-tab-btn <?= $default_tab === 'vhv' ? 'active' : '' ?>"
                onclick="switchManualTab('vhv', this)">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                    </path>
                </svg>
                <span>คู่มือสำหรับ อสม. (VHV)</span>
            </button>
            <button class="manual-tab-btn <?= $default_tab === 'admin' ? 'active' : '' ?>"
                onclick="switchManualTab('admin', this)">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                    </path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <span>คู่มือสำหรับเจ้าหน้าที่ (Admin)</span>
            </button>
        </div>

        <!-- Manual Layout Grid -->
        <div class="manual-layout">

            <!-- Sidebar Navigation -->
            <div class="sidebar-nav">
                <!-- VHV Menu Sidebar -->
                <div id="vhv-sidebar" class="sidebar-content"
                    style="display: <?= $default_tab === 'vhv' ? 'block' : 'none' ?>;">
                    <h3>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"
                            viewBox="0 0 24 24">
                            <path d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        สารบัญ คู่มือ/การใช้งาน
                    </h3>
                    <ul class="sidebar-menu">
                        <li><a href="#vhv-login" class="active" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                                    </path>
                                </svg>1. ลงทะเบียน & เข้าระบบ</a></li>
                        <li><a href="#vhv-dashboard" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                    </path>
                                </svg>2. แดชบอร์ด & รายงานผล</a></li>
                        <li><a href="#vhv-scan" onclick="handleMenuClick(this)"><svg fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>3. การสแกนคิวอาร์โค้ด</a></li>
                        <li><a href="#vhv-screen-flow" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                    </path>
                                </svg>4. ขั้นตอนคัดกรอง 2 ขั้นตอน</a></li>
                        <li><a href="#vhv-dpac" onclick="handleMenuClick(this)"><svg fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                    </path>
                                </svg>5. การติดตามงาน DPAC</a></li>
                        <li><a href="#vhv-offline" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-3.536 4.978 4.978 0 011.414-3.536m0 0L5.636 5.636m3.536 9.9L6.343 18.364m0 0L3 21">
                                    </path>
                                </svg>6. การใช้งานออฟไลน์</a></li>
                        <li><a href="#vhv-leader" onclick="handleMenuClick(this)"><svg fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z">
                                    </path>
                                </svg>7. สิทธิ์ประธาน อสม.</a></li>
                        <li><a href="#vhv-leaderboard" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5a2 2 0 10-2 2h2zm-2 4h4M7 14h10">
                                    </path>
                                </svg>8. ระบบกระดานผลงาน</a></li>
                    </ul>
                </div>

                <!-- Admin Menu Sidebar -->
                <div id="admin-sidebar" class="sidebar-content"
                    style="display: <?= $default_tab === 'admin' ? 'block' : 'none' ?>;">
                    <h3>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"
                            viewBox="0 0 24 24">
                            <path d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        สารบัญ เจ้าหน้าที่
                    </h3>
                    <ul class="sidebar-menu">
                        <li><a href="#admin-roles" class="active" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                    </path>
                                </svg>1. สิทธิ์บัญชีผู้ใช้</a></li>
                        <li><a href="#admin-vhv-approval" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                    </path>
                                </svg>2. การอนุมัติสิทธิ์ อสม.</a></li>
                        <li><a href="#admin-targets" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>3. นำเข้าข้อมูล HDC</a></li>
                        <li><a href="#admin-assignment" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                                    </path>
                                </svg>4. จัดการเป้าหมาย & มอบงาน</a></li>
                        <li><a href="#admin-qr-print" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>5. การพิมพ์ QR Code</a></li>
                        <li><a href="#admin-dpac-mg" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                    </path>
                                </svg>6. การจัดการโครงการ DPAC</a></li>
                        <li><a href="#admin-analytics" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M16 8v8m-4-5v5m-4-2v2M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12z">
                                    </path>
                                </svg>7. วิเคราะห์เชิงลึก & รายงาน</a></li>
                        <li><a href="#admin-db-maintenance" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4">
                                    </path>
                                </svg>8. การบำรุงรักษาฐานข้อมูล</a></li>
                        <li><a href="#admin-user-manager" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                    </path>
                                </svg>9. การจัดการผู้ใช้งานระบบ</a></li>
                        <li><a href="#admin-unit-house" onclick="handleMenuClick(this)"><svg fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                    </path>
                                </svg>10. จัดการหน่วยบริการ & บ้าน</a></li>
                    </ul>
                </div>
            </div>

            <!-- Content Area Card -->
            <div class="content-card">

                <!-- TAB VHV CONTENT -->
                <div id="vhv-content" class="tab-content <?= $default_tab === 'vhv' ? 'active' : '' ?>">

                    <!-- Section: login -->
                    <section id="vhv-login">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">1. การลงทะเบียน อสม. ใหม่ และการเข้าสู่ระบบ</span>
                            <span class="number">VHV-01</span>
                        </h2>
                        <p>การเข้าสู่ระบบ อสม. มีความปลอดภัยและตรวจสอบความเป็นบุคคลจริง
                            เพื่อป้องกันข้อมูลสุขภาพที่ละเอียดอ่อนของผู้รับการคัดกรองในตำบลและหมู่บ้านต่างๆ</p>

                        <div class="alert-box alert-box-info">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path
                                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z">
                                </path>
                            </svg>
                            <div>
                                <div class="alert-title">ข้อมูลสำคัญสำหรับรหัสผ่านเริ่มต้น</div>
                                <p class="alert-desc">อสม. ทุกคนที่ลงทะเบียนใหม่ จะได้รับรหัสผ่านเริ่มต้นระบบคือ <span
                                        class="hl-text">1234</span> โดยหลังจากเข้าสู่ระบบครั้งแรกแล้ว
                                    ระบบขอแนะนำให้เปลี่ยนรหัสผ่านเพื่อความปลอดภัยทางข้อมูล</p>
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>ลงทะเบียนกรณีเป็น อสม. รายใหม่</h4>
                                    <p>กดปุ่ม <span class="hl-text">📝 ลงทะเบียน อสม. ใหม่</span> ในหน้าแรก
                                        จากนั้นเลือกคำนำหน้าชื่อ กรอกชื่อจริง นามสกุล และเบอร์โทรศัพท์
                                        (ซึ่งจะใช้เป็นชื่อผู้ใช้ในการเข้าสู่ระบบ)</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ระบุเขตรับผิดชอบตามข้อมูลทะเบียน อสม.</h4>
                                    <p>เลือก <strong>ตำบล</strong> และ <strong>หมู่บ้านรับผิดชอบ</strong> ของท่าน
                                        หากเป็นตำบลที่มีเขตรับผิดชอบทับซ้อนหรือแบ่งหน่วยงานสาธารณสุขดูแล
                                        ระบบจะแสดงตัวเลือก <strong>หน่วยบริการ (รพ.สต.)</strong>
                                        เพิ่มเติมเพื่อให้เลือกหน่วยบริการสังกัดที่ถูกต้อง</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>การอนุมัติการใช้งานจากแอดมิน</h4>
                                    <p>เมื่อลงชื่อสมัครสำเร็จ บัญชีของท่านจะอยู่ในสถานะ "รอการอนุมัติการใช้งาน"
                                        หากเจ้าหน้าที่สาธารณสุขประจำหน่วยบริการต้นสังกัดทำการอนุมัติข้อมูลผ่านหน้าเว็บของเจ้าหน้าที่แล้ว
                                        ท่านจะสามารถเข้าสู่ระบบเพื่อใช้งานคัดกรองได้ทันที</p>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <!-- Section: dashboard -->
                    <section id="vhv-dashboard">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">2. แดชบอร์ดหลัก และการจำแนกประเภทงาน</span>
                            <span class="number">VHV-02</span>
                        </h2>
                        <p>เมื่อล็อกอินเข้าสู่ระบบเรียบร้อย อสม. จะพบกับหน้ารายการสรุปงานของตัวเอง ซึ่งแบ่งออกเป็น 3
                            แท็บหลัก เพื่อความสะดวกและไม่สับสนในการทำงาน:</p>

                        <div class="manual-table-container">
                            <table class="manual-table">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">แท็บเมนูงาน</th>
                                        <th>คำอธิบายหน้าที่และการประยุกต์ใช้</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong class="hl-text">งานค้าง</strong></td>
                                        <td>รายชื่อประชากรเป้าหมายในหมู่บ้านรับผิดชอบของท่านที่ยังไม่เคยได้รับการคัดกรองโรคเรื้อรัง
                                            (เบาหวาน/ความดัน) ในปีงบประมาณ 2026 นี้</td>
                                    </tr>
                                    <tr>
                                        <td><strong class="hl-text" style="color: #b91c1c;">DPAC</strong></td>
                                        <td>รายชื่อผู้มีสิทธิ์หรือกลุ่มเสี่ยงโรคเรื้อรังที่เข้าเกณฑ์ต้องได้รับการติดตามพฤติกรรม
                                            (Diet and Physical Activity Clinic) รอบปัจจุบัน
                                            เพื่อปรับเปลี่ยนนิสัยการรับประทานอาหาร การออกกำลังกาย และอารมณ์</td>
                                    </tr>
                                    <tr>
                                        <td><strong class="hl-text" style="color: #10b981;">เสร็จสิ้น/ข้าม</strong></td>
                                        <td>ประวัติรายชื่อที่ดำเนินการแล้ว ทั้งที่ทำสำเร็จเรียบร้อย
                                            หรือเคสที่จำเป็นต้องข้ามการคัดกรองไปชั่วคราว (เช่น ไม่อยู่บ้าน
                                            ย้ายถิ่นฐานชั่วคราว หรือไม่ยอมรับการคัดกรอง)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Section: scan -->
                    <section id="vhv-scan">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">3. การใช้งานสแกนคิวอาร์โค้ด (QR Code Scanning)</span>
                            <span class="number">VHV-03</span>
                        </h2>
                        <p>เพื่อตอบสนองการลงพื้นที่คัดกรองอย่างรวดเร็ว ระบบมีฟังก์ชัน <span
                                class="hl-text">สแกนบ้าน</span> อยู่ตรงกลางของปุ่มนำทางด้านล่าง
                            (ปุ่มกลมสีน้ำเงินพร้อมไฟกะพริบแจ้งเตือน)</p>

                        <div class="alert-box alert-box-success">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <div class="alert-title">สะดวกกว่าโดยไม่ต้องคีย์ข้อมูลค้นหา!</div>
                                <p class="alert-desc">ในขณะปฏิบัติงาน อสม.
                                    สามารถถือเครื่องสมาร์ทโฟนเดินไปที่หน้าบ้านเป้าหมาย แล้วสแกนแผ่น QR Code
                                    ที่ติดหน้าบ้าน หรือสแกน QR Code ประจำตัวบุคคล
                                    ระบบจะนำท่านเข้าสู่ฟอร์มคัดกรองของบุคคลนั้นทันทีโดยไม่ต้องพิมพ์ค้นหาชื่อให้เสียเวลา
                                </p>
                            </div>
                        </div>

                        <div
                            style="background-color: var(--bg-darker); padding: 20px; border-radius: 16px; margin: 15px 0;">
                            <strong
                                style="color: var(--color-primary); font-size: 15px; display: block; margin-bottom: 8px;">💡
                                คำแนะนำการใช้งานกล้อง:</strong>
                            <p style="margin: 0; font-size: 14px; line-height: 1.5; color: var(--text-secondary);">
                                เมื่อเข้าหน้าสแกนครั้งแรก ให้กดอนุญาตให้เบราว์เซอร์เข้าถึงกล้องถ่ายภาพของมือถือ
                                จากนั้นถือโทรศัพท์ให้อยู่ในแนวตั้ง ห่างจาก QR Code ประมาณ 20-30 เซนติเมตร
                                เพื่อให้อุปกรณ์ตรวจจับภาพได้ดีที่สุด</p>
                        </div>
                    </section>

                    <!-- Section: screen flow -->
                    <section id="vhv-screen-flow">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">4. ขั้นตอนการบันทึกคัดกรอง 2 ขั้นตอน (Zero-Typing Flow)</span>
                            <span class="number">VHV-04</span>
                        </h2>
                        <p>ฟอร์มคัดกรองเบาหวาน/ความดันโลหิต ออกแบบมาด้วยแนวคิด <span class="hl-text">Zero-Typing
                                (ลดการคีย์ข้อความ)</span>
                            โดยเน้นการใช้นิ้วคลิกหรือจิ้มเพื่อตอบคำถามอย่างรวดเร็วผ่านโครงสร้าง 2 ขั้นตอนดังนี้:</p>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>ขั้นตอนที่ 1: เลือกและยืนยันข้อมูลผู้รับการคัดกรอง (Resident Setup)</h4>
                                    <p>ตรวจสอบและยืนยันชื่อ-นามสกุล อายุ และบ้านเลขที่ของผู้รับบริการบนหน้าจอ
                                        ว่าถูกต้องตรงตัวบุคคลที่คัดกรองหรือไม่</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ขั้นตอนที่ 2: บันทึกข้อมูลสัญญาณชีพและค่าตรวจร่างกาย (Vital Signs & Measurements)</h4>
                                    <p>
                                        • <strong>สัดส่วนร่างกาย</strong>: บันทึกน้ำหนัก (กก.), ส่วนสูง (ซม.) และรอบเอว
                                        (นิ้ว) ระบบจะคำนวณและแสดงระดับ <span class="hl-text">ดัชนีมวลกาย (BMI)</span>
                                        ให้เห็นทันที<br>
                                        • <strong>ความดันโลหิต (Blood Pressure)</strong>: กรอกค่าความดันตัวบน (SYS)
                                        และตัวล่าง (DIA) ครั้งที่ 1 หากค่าที่ได้อยู่ในระดับสูงผิดปกติ
                                        ระบบจะบังคับให้พักและวัดครั้งที่ 2 แล้วกรอกเพิ่มเติมตามหลักทางการแพทย์<br>
                                        • <strong>ระดับน้ำตาลในเลือด (Blood Sugar DTX)</strong>:
                                        บันทึกค่าระดับน้ำตาลปลายเจาะนิ้ว พร้อมเลือกประเภทการตรวจว่าเป็นการงดอาหารมาตรวจ
                                        (FPG) หรือไม่ได้งดอาหาร (RPG)<br>
                                        • <strong>แบบคัดกรองพฤติกรรมเสี่ยง</strong>: ประเมินพฤติกรรมหลัก 5 อ. ได้แก่
                                        การกินอาหารรสจัด เสี่ยงออกกำลังกาย ความเครียด การสูบบุหรี่
                                        และเครื่องดื่มแอลกอฮอล์<br>
                                        • <strong>การประเมินความเสี่ยงโรคหัวใจและหลอดเลือด (Thai CV Risk)</strong>:
                                        บันทึกระดับความเสี่ยงเป็นร้อยละ (%)
                                    </p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>ขั้นตอนที่ 3: ระบบแปรผลและการเลือกคำแนะนำสุขภาพจากรูปภาพ (Auto-Evaluation & Presets)</h4>
                                    <p>
                                        • <strong>การแปรผลลัพธ์สุขภาพอัตโนมัติ</strong>: หลังจากกรอกข้อมูลผลวัดร่างกาย ระบบจะนำค่าความดันโลหิตและระดับน้ำตาลไปประมวลผลแปรระดับความเสี่ยงสุขภาพให้เห็นทันทีบนหน้าจอ อสม. สามารถแจ้งสถานะสุขภาพเสี่ยงแก่ผู้รับบริการได้อย่างสะดวกรวดเร็ว<br>
                                        • <strong>การเลือกข้อความแนะนำผ่านรูปภาพ 9 รายการ</strong>: อสม. ไม่ต้องเสียเวลาพิมพ์ข้อความคำแนะนำเอง ระบบจัดทำ <strong>ไอคอนภาพคำแนะนำสุขภาพยอดนิยม 9 รายการ</strong> เพื่อให้ อสม. แตะเลือกรูปภาพที่เหมาะสม และบันทึกคำแนะนำสุขภาพลงในใบรายงานทันที ได้แก่:<br>
                                        &nbsp;&nbsp;&nbsp;&nbsp;1. 🧂 <em>ลดเค็ม งดซอส/ปลาร้า</em> (สำหรับผู้มีระดับความดันโลหิตสูง)<br>
                                        &nbsp;&nbsp;&nbsp;&nbsp;2. 💤 <em>ผ่อนคลาย พักผ่อนให้พอ</em> (เพื่อช่วยผ่อนคลายระบบประสาทและหัวใจ)<br>
                                        &nbsp;&nbsp;&nbsp;&nbsp;3. 🏃 <em>ออกกำลังกาย 30 นาที/วัน</em> (ส่งเสริมการใช้พลังงานและลด BMI)<br>
                                        &nbsp;&nbsp;&nbsp;&nbsp;4. 🚭 <em>งดบุหรี่ & แอลกอฮอล์</em> (ลดความเสี่ยงหลอดเลือดสมองและหัวใจอุดตัน)<br>
                                        &nbsp;&nbsp;&nbsp;&nbsp;5. 💧 <em>ดื่มน้ำเปล่า 6-8 แก้ว/วัน</em> (ช่วยในการขับของเสียและการไหลเวียนเลือด)<br>
                                        &nbsp;&nbsp;&nbsp;&nbsp;6. 🥦 <em>เพิ่มผักใบเขียว ธัญพืช</em> (เพิ่มกากใยอาหารและช่วยลดน้ำตาลในเลือด)<br>
                                        &nbsp;&nbsp;&nbsp;&nbsp;7. 🩺 <em>พบแพทย์ตามนัดสม่ำเสมอ</em> (ย้ำเตือนสำหรับกลุ่มเป้าหมายที่มีประวัติต้องติดตาม)<br>
                                        &nbsp;&nbsp;&nbsp;&nbsp;8. 🍳 <em>เลี่ยงของมัน ของทอด</em> (เพื่อลดการสะสมไขมันและปรับปรุง BMI)<br>
                                        &nbsp;&nbsp;&nbsp;&nbsp;9. 💊 <em>ทานยาต่อเนื่องตามแพทย์สั่ง</em> (ส่งเสริมวินัยในการดูแลสุขภาพตนเอง)
                                    </p>
                                </div>
                            </li>
                        </ul>

                        <div class="alert-box alert-box-success" style="margin: 20px 0;">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                </path>
                                <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <div>
                                <div class="alert-title">📍 ระบบบันทึกและส่งพิกัดภูมิศาสตร์อัตโนมัติ (Automatic GPS
                                    Capture)</div>
                                <p class="alert-desc">
                                    เมื่อ อสม. ลงพื้นที่คัดกรองและกดปุ่มบันทึกส่งงาน
                                    <strong>ระบบจะทำการดึงข้อมูลพิกัดตำแหน่งทางภูมิศาสตร์ (GPS coordinates) ณ
                                        ตำแหน่งที่ลงพื้นที่จริงโดยอัตโนมัติและส่งขึ้นสู่เซิร์ฟเวอร์</strong>
                                    เพื่อประโยชน์ในการนำข้อมูลไปวิเคราะห์ประมวลผลสร้างเป็น
                                    <strong>แผนที่ความร้อนด้านสุขภาพ (Health Heatmap)</strong>
                                    ช่วยให้แพทย์และเจ้าหน้าที่ รพ.สต. ในอำเภอตาลสุม
                                    เห็นการกระจายตัวและความหนาแน่นของผู้ที่ได้รับการคัดกรองและพิกัดกลุ่มเสี่ยงโรคเบาหวาน/ความดันโลหิตสูงอย่างถูกต้อง
                                    แม่นยำ และมีประสิทธิภาพสูงสุดสำหรับการดูแลเชิงพื้นที่
                                </p>
                            </div>
                        </div>

                        <div class="alert-box alert-box-warning">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                </path>
                            </svg>
                            <div>
                                <div class="alert-title">การข้ามการคัดกรอง (Skip Case)</div>
                                <p class="alert-desc">กรณีเป้าหมายไม่อยู่บ้าน ปฏิเสธ หรือย้ายถิ่นฐาน อสม. สามารถกดปุ่ม
                                    <span class="hl-text" style="color: var(--color-yellow);">ข้ามเคส</span>
                                    และระบุสาเหตุเพื่อให้ระบบเก็บประวัติและสามารถวนกลับมาคัดกรองใหม่ภายหลังได้
                                    แทนที่จะค้างงานไว้ในระบบ
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Section: dpac -->
                    <section id="vhv-dpac">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">5. การติดตามโครงการปรับเปลี่ยนพฤติกรรมสุขภาพ (DPAC Followup)</span>
                            <span class="number">VHV-05</span>
                        </h2>
                        <p>สำหรับบุคคลที่ผลการคัดกรองรอบแรกจัดอยู่ใน "กลุ่มเสี่ยงโรคเรื้อรัง"
                            เจ้าหน้าที่สาธารณสุขจะลงทะเบียนเข้าสู่โครงการปรับเปลี่ยนพฤติกรรมสุขภาพ (DPAC)
                            โดยส่งงานมอบหมายให้ อสม. ดำเนินการติดตามพฤติกรรมเป็นระยะ (ตามรอบความถี่ 1-3 ครั้ง)</p>

                        <div class="alert-box alert-box-info">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <div class="alert-title">การกรอกติดตามผล DPAC</div>
                                <p class="alert-desc">อสม. เข้าทำรายการติดตามที่แท็บ <span class="hl-text"
                                        style="color: #b91c1c;">DPAC</span> โดยตรวจสอบข้อมูลเสี่ยงด้านโภชนาการ
                                    และการออกกำลังกาย แล้วประเมินผลตามรอบ พร้อมวัดน้ำหนัก ความดัน
                                    และระดับน้ำตาลเพื่อรายงานความก้าวหน้าทางสุขภาพของกลุ่มเสี่ยง</p>
                            </div>
                        </div>
                    </section>

                    <!-- Section: offline -->
                    <section id="vhv-offline">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-3.536 4.978 4.978 0 011.414-3.536m0 0L5.636 5.636m3.536 9.9L6.343 18.364m0 0L3 21">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">6. การเข้าคัดกรองในพื้นที่อับสัญญาณอินเทอร์เน็ต (Offline
                                Mode)</span>
                            <span class="number">VHV-06</span>
                        </h2>
                        <p>เพื่อแก้ไขปัญหาการลงพื้นที่ในจุดที่ไม่มีสัญญาณอินเทอร์เน็ตในบางหมู่บ้านของอำเภอตาลสุม ระบบ
                            NCD Portal มีเทคโนโลยีเก็บข้อมูลออฟไลน์อัตโนมัติ (PWA - Service Worker):</p>

                        <div class="alert-box alert-box-success">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <div class="alert-title">การทำงานของโหมดออฟไลน์ (Offline Auto-Sync)</div>
                                <p class="alert-desc">
                                    • เมื่อลงพื้นที่แล้วไม่มีเน็ต อสม.
                                    ยังสามารถเปิดหน้าบันทึกและสแกนคัดกรองได้ตามปกติ<br>
                                    • ข้อมูลคัดกรองที่บันทึกจะถูกเก็บลงความจำในอุปกรณ์ของท่านชั่วคราว<br>
                                    • เมื่อมือถือจับสัญญาณอินเทอร์เน็ตได้หรือท่านกลับเข้าบ้านที่มี Wi-Fi
                                    ระบบจะตรวจจับและทำการอัปโหลดข้อมูลที่บันทึกค้างไว้ส่งไปยังคลาวด์ของแอดมินโดยอัตโนมัติทันที!
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Section: leader -->
                    <section id="vhv-leader">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">7. ฟังก์ชันกู้คืนและรีเซ็ตรหัสผ่านสำหรับ "ประธาน อสม."</span>
                            <span class="number">VHV-07</span>
                        </h2>
                        <p>for อสม. ที่ดำรงบทบาทเป็น <span class="hl-text">ประธาน อสม. ประจำหมู่บ้าน</span>
                            จะได้รับสิทธิ์ในการช่วยเหลืออำนวยความสะดวกให้สมาชิกในทีมที่ลืมรหัสผ่านใช้งาน</p>

                        <div class="alert-box alert-box-warning">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                </path>
                            </svg>
                            <div>
                                <div class="alert-title">วิธีรีเซ็ตรหัสผ่านให้สมาชิก อสม.</div>
                                <p class="alert-desc">ที่ด้านบนสุดของหน้าแดชบอร์ดหลักของประธาน อสม. จะมีกล่อง <span
                                        class="hl-text">🔑 รีเซ็ตรหัสผ่าน อสม. ในหมู่บ้าน</span> ให้ประธาน อสม.
                                    เลือกรายชื่อ อสม. ที่ลืมรหัสผ่าน แล้วกดปุ่ม "รีเซ็ต 1234"
                                    ระบบจะล้างรหัสผ่านเดิมและตั้งเป็นรหัสผ่านเริ่มต้นทันที
                                    โดยไม่ต้องรอให้แอดมินหรือเจ้าหน้าที่ รพ.สต. ดำเนินการให้</p>
                            </div>
                        </div>
                    </section>

                    <!-- Section: leaderboard -->
                    <section id="vhv-leaderboard">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5a2 2 0 10-2 2h2zm-2 4h4M7 14h10">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">8. ระบบกระดานคะแนนและอันดับผลงาน (Leaderboard)</span>
                            <span class="number">VHV-08</span>
                        </h2>
                        <p>อสม.
                            สามารถเปิดหน้ากระดานคะแนนเพื่อตรวจสอบลำดับการผลงานคัดกรองของตนเองเปรียบเทียบกับเพื่อนร่วมงานคนอื่นๆ
                            ในพื้นที่ตำบลและอำเภอ เพื่อเป็นเกียรติและสร้างแรงจูงใจในการดำเนินงานเชิงรุกเพื่อชุมชน</p>
                    </section>

                </div>

                <!-- TAB ADMIN CONTENT -->
                <div id="admin-content" class="tab-content <?= $default_tab === 'admin' ? 'active' : '' ?>">

                    <!-- Section: admin-roles -->
                    <section id="admin-roles">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">1. สิทธิ์บัญชีผู้ใช้ระบบ (Access Control Roles)</span>
                            <span class="number">ADM-01</span>
                        </h2>
                        <p>การเข้าใช้งานในระบบแอดมินหรือหลังบ้าน มีการแบ่งระดับชั้นข้อมูลตามความรับผิดชอบอย่างเคร่งครัด
                            ดังนี้:</p>

                        <div class="manual-table-container">
                            <table class="manual-table">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">ระดับสิทธิ์</th>
                                        <th>สิทธิ์และขอบเขตในการมองเห็นและจัดการข้อมูล</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong class="hl-text">Super Admin</strong></td>
                                        <td>ผู้ดูแลระบบระดับอำเภอ (สสอ.) เข้าถึงข้อมูลได้ทุก รพ.สต.
                                            และโรงพยาบาลในอำเภอตาลสุม มีสิทธิ์เต็มที่ในการนำเข้าประชากรเป้าหมาย HDC,
                                            จัดการโครงสร้างฐานข้อมูล, อนุมัติแอดมิน และตรวจสอบ ETL</td>
                                    </tr>
                                    <tr>
                                        <td><strong class="hl-text">Area Admin</strong></td>
                                        <td>เจ้าหน้าที่ประจำ รพ.สต. หรือโรงพยาบาลแต่ละแห่ง เช่น เจ้าหน้าที่ รพ.สต.
                                            หนองกุง จะเห็นและจัดการข้อมูลได้เฉพาะ อสม.
                                            และเป้าหมายประชากรในเขตรับผิดชอบของตนเองเท่านั้น</td>
                                    </tr>
                                    <tr>
                                        <td><strong class="hl-text" style="color: var(--color-yellow);">Visitor
                                                Mode</strong></td>
                                        <td>บัญชีผู้มาเยือน (ล็อกอินด้วย <span class="hl-text">visitor / 123456</span>)
                                            สำหรับนักวิจัยหรือผู้ประเมินภายนอก สามารถดูสถิติ กราฟ แผนที่
                                            และรายงานได้ทั้งหมดแบบ **อ่านอย่างเดียว (Read-Only)**
                                            ไม่สามารถบันทึกหรือทำลายข้อมูลระบบได้</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Section: admin-vhv-approval -->
                    <section id="admin-vhv-approval">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">2. การตรวจสอบและอนุมัติเปิดใช้งานสำหรับ อสม.</span>
                            <span class="number">ADM-02</span>
                        </h2>
                        <p>เพื่อป้องกันบุคคลภายนอกสวมสิทธิ์เข้ามาดูข้อมูลผู้ป่วย เมื่อมี อสม. มาลงทะเบียนสมัครใหม่
                            เจ้าหน้าที่สาธารณสุขของ รพ.สต. นั้นๆ จะต้องดำเนินการตรวจสอบสิทธิ์:</p>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>เข้าสู่เมนูอนุมัติ อสม.</h4>
                                    <p>ไปที่เมนู <strong>งาน & อสม.</strong> > <a href="admin/vhv_approval.php"
                                            class="hl-text">จัดการผู้ใช้ อสม.</a></p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ตรวจสอบความถูกต้องและกดยืนยัน</h4>
                                    <p>ระบบจะลิสต์รายชื่อ อสม. ที่เพิ่งลงทะเบียนมาใหม่ ตรวจทานชื่อ-นามสกุล
                                        และหมู่บ้านของเขา หากถูกต้อง ให้กดปุ่มอนุมัติเปิดสิทธิ์ บัญชี อสม.
                                        ดังกล่าวจะเปลี่ยนเป็นสถานะ <span class="hl-green">อนุมัติแล้ว</span>
                                        ทันทีและเริ่มต้นใช้งานได้</p>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <!-- Section: admin-targets -->
                    <section id="admin-targets">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                            </span>
                            <span class="title-text">3. การจัดเตรียมนำเข้าประชากรเป้าหมาย HDC และ ETL</span>
                            <span class="number">ADM-03</span>
                        </h2>
                        <p>หัวใจสำคัญของระบบคัดกรอง NCD คือความพร้อมของรายชื่อประชากรเป้าหมายในพื้นที่อำเภอตาลสุม
                            โดยมีกระบวนการดึงข้อมูลจากฐานกลางกระทรวงสาธารณสุข (HDC) ดังนี้:</p>

                        <div class="alert-box alert-box-danger">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                </path>
                            </svg>
                            <div>
                                <div class="alert-title">เฉพาะสิทธิ์ผู้ดูแลระบบระดับอำเภอ (Super Admin) เท่านั้น</div>
                                <p class="alert-desc">การนำเข้าไฟล์ข้อมูลและการสั่งประมวลผลระบบเพื่อแปลงรูปแบบข้อมูล
                                    (ETL Process) ส่งผลต่อทรัพยากรเครื่องเซิร์ฟเวอร์อย่างสูง
                                    ควรทำในช่วงเวลาที่ไม่มีผู้ใช้งานจำนวนมาก</p>
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>การอัปโหลดไฟล์นำเข้าข้อมูล HDC</h4>
                                    <p>ไปที่เมนู <strong>จัดการระบบ</strong> > <a href="admin/import_hdc.php"
                                            class="hl-text">นำเข้าข้อมูล HDC</a>
                                        เลือกไฟล์รายชื่อประชากรจากโปรแกรมระบบข้อมูลโรงพยาบาล (เช่น 43 แฟ้ม หรือ HDC XLS)
                                        เพื่ออัปโหลดเข้าสู่ staging zone ของระบบ</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>การประมวลผล ETL (Extract, Transform, Load)</h4>
                                    <p>หลังจากนำเข้าข้อมูลดิบ ให้เข้าเมนู <a href="admin/process_etl.php"
                                            class="hl-text">ประมวลผล ETL</a>
                                        เพื่อสั่งการให้ระบบทำการคัดแยกประชากรที่เข้าเกณฑ์เสี่ยง เบาหวาน/ความดัน
                                        และส่งรายชื่อเข้าเป็นกลุ่มเป้าหมายให้ อสม. พร้อมเริ่มคัดกรอง</p>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <!-- Section: admin-assignment -->
                    <section id="admin-assignment">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">4. การมอบหมายกลุ่มเป้าหมายคัดกรองให้ อสม. (Work Assignment)</span>
                            <span class="number">ADM-04</span>
                        </h2>
                        <p>เพื่อไม่ให้งานซ้ำซ้อนและแยกแยะความรับผิดชอบอย่างชัดเจน แอดมิน รพ.สต.
                            จะต้องดำเนินการมอบหมายงานแก่ อสม. ในหมู่บ้านที่ตนเองสังกัด:</p>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>เข้าสู่ฟังก์ชันมอบหมายงาน</h4>
                                    <p>ไปที่ <strong>งาน & อสม.</strong> > <a href="admin/assignment.php"
                                            class="hl-text">มอบหมายงาน อสม.</a></p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>มอบหมายงานตามแผนที่การดูแล</h4>
                                    <p>เลือกหมู่บ้าน จากนั้นระบบจะแสดงรายชื่อประชากรที่อยู่ในเขตและรายชื่อ อสม.
                                        ที่สังกัดในหมู่นั้นๆ เลือกจับคู่มอบหมายเป้าหมายให้ อสม. รายบุคคล
                                        เพื่อให้รายชื่อเป้าหมายนั้นวิ่งเข้าไปแสดงผลบนหน้าแอปพลิเคชันมือถือของ อสม.
                                        คนนั้นแบบทันที</p>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <!-- Section: admin-qr-print -->
                    <section id="admin-qr-print">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">5. การพิมพ์ QR Code ประจำตัวผู้ป่วยและติดหน้าบ้าน</span>
                            <span class="number">ADM-05</span>
                        </h2>
                        <p>ระบบสร้างภาพคิวอาร์โค้ดเฉพาะบุคคลและหลังคาเรือนของอำเภอตาลสุม เพื่อให้ อสม.
                            นำไปใช้งานคัดกรองแบบรวดเร็ว</p>
                        <p>แอดมินสามารถเปิดเมนู <a href="admin/print_qr.php" class="hl-text">พิมพ์ QR Code บ้าน</a>
                            จากนั้นเลือกรุ่นรหัสหน่วยบริการ/หมู่บ้าน
                            แล้วกดคำสั่งสร้างเพื่อดาวน์โหลดหรือสั่งพิมพ์สติกเกอร์/แผ่นพับนำไปแจกจ่าย อสม.
                            ในการนำไปติดที่หน้าบ้านแต่ละหลังคาเรือนในชุมชน</p>
                    </section>

                    <!-- Section: admin-dpac-mg -->
                    <section id="admin-dpac-mg">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">6. การจัดการและติดตามโครงการ DPAC (Diet & Activity Clinic)</span>
                            <span class="number">ADM-06</span>
                        </h2>
                        <p>ผู้ที่มีผลประเมินร่างกายตกเกณฑ์เสี่ยง หรือมีความดันและระดับน้ำตาลสูงปานกลาง
                            แอดมินสามารถลงทะเบียนคนเหล่านั้นเข้าระบบเพื่อเปิดโครงการปรับเปลี่ยนพฤติกรรม โดยเข้าไปที่ <a
                                href="admin/dpac_manager.php" class="hl-text">จัดการโครงการ DPAC</a>
                            เพื่อดูสถิติการส่งงานติดตามพฤติกรรมรายรอบ (รอบที่ 1, 2, 3) ของ อสม.
                            และเปรียบเทียบกราฟการลดน้ำหนัก ลดพุง และความดันโลหิตของผู้เข้าร่วมโครงการทั้งหมด</p>
                    </section>

                    <!-- Section: admin-analytics -->
                    <section id="admin-analytics">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M16 8v8m-4-5v5m-4-2v2M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12z">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">7. วิเคราะห์ข้อมูลเชิงลึก แผนที่สุขภาพ และรายงานสรุป</span>
                            <span class="number">ADM-07</span>
                        </h2>
                        <p>แอดมินสาธารณสุขและผู้อำนวยการ รพ.สต. สามารถใช้ประโยชน์จากหน้าแสดงผลข้อมูลอัจฉริยะ (Data
                            Visualization) สองส่วนหลัก:</p>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>ระบบวิเคราะห์เชิงลึก (Advanced Analytics)</h4>
                                    <p>ไปที่เมนู <a href="admin/analytics.php" class="hl-text">วิเคราะห์เชิงลึก
                                            (Analytics)</a> เพื่อเรียกดู <strong>แผนที่ความร้อนเชิงระบาดวิทยา (Heatmap
                                            GIS Grid)</strong> ซึ่งประมวลผลข้อมูลเชิงพื้นที่จากพิกัดตำแหน่งจริงที่ อสม.
                                        ส่งเข้ามาอัตโนมัติขณะบันทึกข้อมูล
                                        ทำให้ระบบได้ตำแหน่งผู้ได้รับการคัดกรองและกลุ่มเสี่ยงเบาหวาน/ความดันตามตำแหน่งหลังคาเรือนจริงในอำเภอตาลสุมที่ชัดเจนและแม่นยำสูง
                                        พร้อมสถิติกราฟวิเคราะห์พีระมิดประชากรเสี่ยงแบบเรียลไทม์</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ระบบรายงานและข้อมูลพิมพ์เสนอผู้บริหาร</h4>
                                    <p>ไปที่หน้า <a href="admin/reports.php" class="hl-text">รายงานและการพิมพ์</a>
                                        เพื่อสร้างตารางรายงานข้อมูลแยกรายตำบล รายหมู่บ้าน
                                        สรุปอัตราความครอบคลุมการคัดกรอง และอัตราการเจ็บป่วย สำหรับเซฟเป็นไฟล์ PDF/Excel
                                        หรือสั่งพิมพ์เพื่อเป็นหลักฐานเชิงสถิติต่อไป</p>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <!-- Section: admin-db-maintenance -->
                    <section id="admin-db-maintenance">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4">
                                    </path>
                                </svg>
                            </span>
                            <span class="title-text">8. ระบบสำรองข้อมูลและการดูแลความสะอาดฐานข้อมูล</span>
                            <span class="number">ADM-08</span>
                        </h2>
                        <p>เพื่อความยั่งยืนและความสมบูรณ์แบบของฐานข้อมูลระบบ Super Admin สามารถเข้าไปที่เมนู <a
                                href="admin/db_manager.php" class="hl-text">จัดการฐานข้อมูลระบบ</a> เพื่อทำการแบ็กอัป
                            (Backup) ข้อมูลตารางการบันทึกคัดกรอง
                            หรือกดปุ่มฟื้นฟูระบบฐานข้อมูลกรณีมีข้อมูลสับสนหรือข้อมูลทดสอบที่ต้องการล้างทิ้ง</p>
                    </section>

                    <!-- Section: admin-user-manager -->
                    <section id="admin-user-manager">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </span>
                            <span class="title-text">9. การจัดการผู้ใช้งานระบบ (User Management)</span>
                            <span class="number">ADM-09</span>
                        </h2>
                        <p>เพื่อให้ผู้ดูแลระบบสูงสุดสามารถจัดการบัญชีรายชื่อผู้ใช้ระดับเจ้าหน้าที่ (Admin/Staff) ทั้งระบบได้อย่างสะดวกรวดเร็ว โดยแยกส่วนออกจากการจัดการบัญชีของ อสม. โดยตรง</p>

                        <div class="alert-box alert-box-warning">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <div>
                                <div class="alert-title">ขอบเขตสิทธิ์การเข้าถึงเมนู</div>
                                <p class="alert-desc">เมนูนี้เปิดให้ใช้งานเฉพาะสิทธิ์ <strong>Super Admin</strong> เท่านั้น (บัญชีที่ไม่ใช่ adminsso และไม่มีรหัสหน่วยบริการผูกอยู่) ส่วนบัญชี Area Admin (รพ.สต.) และบัญชีผู้มาเยือน (Visitor) จะไม่เห็นเมนูนี้และไม่สามารถเข้าถึงหน้าเว็บได้</p>
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>เข้าสู่เมนูจัดการผู้ใช้งาน</h4>
                                    <p>ไปที่เมนู <strong>จัดการระบบ</strong> > <a href="admin/user_manager.php" class="hl-text">จัดการผู้ใช้งานระบบ</a> เพื่อเข้าสู่แผงควบคุมหลักสำหรับการบริหารจัดการบัญชีเจ้าหน้าที่</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>เพิ่มบัญชีผู้ใช้งานระบบรายใหม่</h4>
                                    <p>กรอกชื่อผู้ใช้ (Username ภาษาอังกฤษ/ตัวเลข), ชื่อ-นามสกุลจริง, เลือกสังกัดหน่วยบริการสาธารณสุข (หากต้องการให้บัญชีนั้นมีสิทธิ์ Super Admin ให้เลือก <em>"ไม่มีสังกัดหน่วยบริการ (Super Admin)"</em>) และกำหนดรหัสผ่านเพื่อบันทึกสร้างบัญชี</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>แก้ไขข้อมูล หรือเปลี่ยนรหัสผ่าน</h4>
                                    <p>สามารถกดปุ่ม <span class="hl-text">แก้ไข</span> ในรายการตารางเพื่อปรับปรุงชื่อ-นามสกุล หรือสังกัดโรงพยาบาล/รพ.สต. และสามารถรีเซ็ตรหัสผ่านใหม่ได้ทันที (หากต้องการใช้รหัสผ่านเดิม ให้ปล่อยช่องรหัสผ่านว่างไว้ขณะบันทึก)</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">4</span>
                                <div class="step-content">
                                    <h4>ระงับสิทธิ์ชั่วคราว หรือลบบัญชีถาวร</h4>
                                    <p>
                                        • <strong>ระงับสิทธิ์ (Suspend)</strong>: กดปุ่มระงับสิทธิ์ บัญชีผู้ใช้นั้นจะไม่สามารถล็อกอินเข้าสู่ระบบได้ชั่วคราว จนกว่าจะกดเปิดสิทธิ์กลับมาใหม่ (<span class="hl-green">เปิดสิทธิ์</span>)<br>
                                        • <strong>ลบผู้ใช้ (Delete)</strong>: ลบบัญชีผู้ใช้งานที่ไม่มีการใช้งานแล้วออกจากระบบโดยถาวร
                                    </p>
                                </div>
                            </li>
                        </ul>

                        <div class="alert-box alert-box-success">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            <div>
                                <div class="alert-title">กลไกความปลอดภัยและระบบล็อกป้องกันข้อผิดพลาด</div>
                                <p class="alert-desc">
                                    1. <strong>ป้องกันการล็อกตัวเองนอกระบบ (Self-lockout prevention)</strong>: ระบบจะไม่อนุญาตให้บัญชีที่กำลังล็อกอินทำงานอยู่กดลบหรือระงับสิทธิ์บัญชีของตนเองโดยเด็ดขาด<br>
                                    2. <strong>ปกป้องบัญชีผู้ดูแลระบบหลัก</strong>: ระบบไม่อนุญาตให้ระงับสิทธิ์หรือลบบัญชีหลักชื่อ <span class="hl-text">admin</span> เพื่อรักษาสิทธิ์สูงสุดคงไว้ในระบบเสมอ
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Section: admin-unit-house -->
                    <section id="admin-unit-house">
                        <h2 class="section-title">
                            <span class="title-icon-container">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </span>
                            <span class="title-text">10. การจัดการหน่วยบริการ, ตำบล, หมู่บ้าน และบ้านเรือน</span>
                            <span class="number">ADM-10</span>
                        </h2>
                        <p>แผงควบคุมศูนย์กลางในการบริหารจัดการข้อมูลโครงสร้างทางภูมิศาสตร์ของระบบคัดกรอง ประกอบด้วยการจัดการหน่วยบริการ (รพ.สต.), ข้อมูลตำบล, หมู่บ้าน และเลขที่บ้าน เพื่อให้สอดรับกันทั้งอำเภอตาลสุม</p>

                        <div class="alert-box alert-box-warning">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <div>
                                <div class="alert-title">เข้าถึงโดย Super Admin เท่านั้น</div>
                                <p class="alert-desc">การเพิ่ม ลบ หรือแก้ไขข้อมูลโครงสร้างหลักเหล่านี้ส่งผลกระทบต่อประชากรเป้าหมายและการทำงานของ อสม. ทั่วทั้งระบบ จึงอนุญาตให้สิทธิ์ <strong>Super Admin</strong> ดำเนินการเท่านั้น</p>
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>เข้าสู่ระบบการจัดการโครงสร้าง</h4>
                                    <p>ไปที่เมนู <strong>จัดการระบบ</strong> > <a href="admin/unit_house_manager.php" class="hl-text">จัดการหน่วยบริการ & บ้าน</a> ระบบจะแสดงส่วนการทำงาน 4 แท็บให้เลือกใช้งานตามวัตถุประสงค์</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>แท็บ 1: จัดการหน่วยบริการ (Health Units)</h4>
                                    <p>ใช้สำหรับเพิ่ม แก้ไข และลบหน่วยบริการสังกัด เช่น โรงพยาบาล หรือ รพ.สต. ในพื้นที่ โดยใช้รหัสหน่วยบริการ 5 หลัก (HOSCODE) และระบุชื่อหน่วยงาน (ระบบจะล็อกป้องกันไม่ให้ลบหน่วยบริการที่มีข้อมูลหมู่บ้านเชื่อมโยงอยู่)</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>แท็บ 2: จัดการตำบล (Sub-districts)</h4>
                                    <p>ใช้สำหรับลงทะเบียนรหัสตำบล 6 หลัก และชื่อตำบลในอำเภอตาลสุม (เช่น รหัส 340602 ชื่อตำบล สำโรง) เพื่อเป็นข้อมูลรากฐานในการคำนวณรหัสหมู่บ้านและรหัสบัตรคิวอาร์โค้ด</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">4</span>
                                <div class="step-content">
                                    <h4>แท็บ 3: จัดการหมู่บ้าน (Villages)</h4>
                                    <p>ระบุชื่อหมู่บ้าน เลือกว่าอยู่ภายใต้ <strong>ตำบล</strong> ใด, เป็น <strong>หมู่ที่</strong> เท่าไร และอยู่ภายใต้ความรับผิดชอบของ <strong>หน่วยบริการ</strong> ใด<br>
                                    💡 <strong>ระบบคำนวณรหัสหมู่บ้านอัตโนมัติ (VHID)</strong>: ระบบจะนำรหัสตำบลมารวมกับลำดับหมู่ที่ให้อย่างถูกต้อง (เช่น ตำบลจิกเทิง 340603 หมู่ที่ 1 จะได้รหัสหมู่บ้านเป็น 34060301) โดยระบบจะช่วยตรวจสอบป้องกันปัญหารหัสซ้ำซ้อนให้เอง</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">5</span>
                                <div class="step-content">
                                    <h4>แท็บ 4: จัดการบ้านเรือน/หลังคาเรือน (Houses)</h4>
                                    <p>
                                        • <strong>ระบบตัวกรองแบบลำดับขั้น (Cascading Dropdowns)</strong>: เมื่อเลือกหน่วยบริการ ระบบจะกรองรายชื่อหมู่บ้านเฉพาะที่ขึ้นตรงกับหน่วยบริการนั้นๆ มาให้เลือกทันที ช่วยให้การค้นหา ค้นเลขที่บ้าน และเพิ่มบ้านใหม่ทำได้รวดเร็ว แม่นยำ และเป็นสัดส่วน<br>
                                        • <strong>การเพิ่ม/แก้ไขแมนนวล</strong>: สามารถกำหนดรหัสบ้าน (HID), บ้านเลขที่, และป้อนพิกัดตำแหน่งภูมิศาสตร์ ละติจูด/ลองจิจูด เพื่อใช้ในการคำนวณแผนที่ความร้อน GIS<br>
                                        • <strong>ระบบตรวจสอบความปลอดภัยของข้อมูล</strong>: ระบบจะปฏิเสธการลบข้อมูลบ้านเรือน หรือปฏิเสธการเปลี่ยนรหัสบ้าน หากหลังคาเรือนนั้นๆ มีประชากรเป้าหมายเชื่อมโยงอาศัยอยู่ เพื่อรักษาความสมบูรณ์และถูกต้องของฐานข้อมูล
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </section>

                </div>

            </div>
        </div>

        <!-- System Service Units Reference Block -->
        <div class="card-dark" style="margin-top: 40px; padding: 30px;">
            <h3
                style="color: var(--color-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                    </path>
                </svg>
                <span>รายชื่อหน่วยบริการสาธารณสุขในระบบอำเภอตาลสุม จังหวัดอุบลราชธานี</span>
            </h3>
            <p style="color: var(--text-secondary); margin-bottom: 20px; line-height: 1.6;">
                ข้อมูลการเชื่อมโยงระบบการคัดกรองนี้ ผูกติดกับหน่วยบริการทางสาธารณสุขอย่างถูกต้องตามกฎการลงทะเบียน อสม.
                และแผนที่รับผิดชอบบ้านเรือนเป้าหมาย โดยประกอบด้วย <strong>1 โรงพยาบาลปฐมภูมิ</strong> และ <strong>7
                    โรงพยาบาลส่งเสริมสุขภาพตำบล (รพ.สต.)</strong> ดังต่อไปนี้:</p>

            <div class="manual-table-container">
                <table class="manual-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">รหัสหน่วย</th>
                            <th style="width: 40%;">ชื่อหน่วยบริการสาธารณสุข</th>
                            <th style="width: 20%;">ตำบลหลัก</th>
                            <th>หมู่บ้านในเขตรับผิดชอบ (ตัวอย่าง)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong class="hl-text">10957</strong></td>
                            <td>โรงพยาบาลตาลสุม (กลุ่มงานบริการด้านปฐมภูมิ)</td>
                            <td>ตาลสุม</td>
                            <td>หมู่ 1 บ้านม่วงโคน, หมู่ 2 บ้านดอนรังกา, หมู่ 3 บ้านนาห้วยแคน, หมู่ 5 บ้านนามน, หมู่ 10,
                                หมู่ 11, หมู่ 12</td>
                        </tr>
                        <tr>
                            <td><strong class="hl-text">03751</strong></td>
                            <td>รพ.สต. ดอนพันชาด</td>
                            <td>ตาลสุม</td>
                            <td>หมู่ 4 บ้านดอนพันชาด, หมู่ 6 บ้านดอนตะลี, หมู่ 7 บ้านปากห้วย, หมู่ 8, หมู่ 9, หมู่ 13,
                                หมู่ 14, หมู่ 15</td>
                        </tr>
                        <tr>
                            <td><strong class="hl-text">03752</strong></td>
                            <td>รพ.สต. บ้านสำโรง (ถ่ายโอน สังกัด อบจ.)</td>
                            <td>สำโรง</td>
                            <td>หมู่ 1 บ้านสำโรงใหญ่, หมู่ 2 บ้านสำโรงกลาง, หมู่ 3 บ้านนาโพธิ์, หมู่ 4, หมู่ 5, หมู่ 6,
                                หมู่ 7, หมู่ 8</td>
                        </tr>
                        <tr>
                            <td><strong class="hl-text">03753</strong></td>
                            <td>รพ.สต. บ้านจิกเทิง (ถ่ายโอน สังกัด อบจ.)</td>
                            <td>จิกเทิง</td>
                            <td>หมู่ 1 บ้านจิกเทิง, หมู่ 2 บ้านจิกลุ่ม, หมู่ 3 บ้านเชียงแก้ว, หมู่ 4, หมู่ 5, หมู่ 6,
                                หมู่ 7, หมู่ 8, หมู่ 9</td>
                        </tr>
                        <tr>
                            <td><strong class="hl-text">03754</strong></td>
                            <td>รพ.สต. บ้านหนองกุง (ถ่ายโอน สังกัด อบจ.)</td>
                            <td>หนองกุง</td>
                            <td>หมู่ 1 บ้านหนองกุงใหญ่, หมู่ 2 บ้านหนองกุงน้อย, หมู่ 3 บ้านคำแคน, หมู่ 4, หมู่ 5, หมู่
                                6, หมู่ 7, หมู่ 8</td>
                        </tr>
                        <tr>
                            <td><strong class="hl-text">03755</strong></td>
                            <td>รพ.สต. นาคาย</td>
                            <td>นาคาย</td>
                            <td>หมู่ 1 บ้านนาคาย, หมู่ 2 บ้านโนนจิก, หมู่ 3 บ้านหนองเป็ด, หมู่ 4, หมู่ 5, หมู่ 6</td>
                        </tr>
                        <tr>
                            <td><strong class="hl-text">03756</strong></td>
                            <td>รพ.สต. คำหนามแท่ง</td>
                            <td>นาคาย</td>
                            <td>หมู่ 7 บ้านโคกคล้าย, หมู่ 8 บ้านคำหนามแท่ง, หมู่ 9 บ้านคำผักหนอก, หมู่ 10, หมู่ 11, หมู่
                                12, หมู่ 13</td>
                        </tr>
                        <tr>
                            <td><strong class="hl-text">03757</strong></td>
                            <td>รพ.สต. คำหว้า</td>
                            <td>คำหว้า</td>
                            <td>หมู่ 1 บ้านคำหว้า, หมู่ 2, หมู่ 3 บ้านห้วยดู่, หมู่ 4 บ้านนาทมเหนือ, หมู่ 5 บ้านไฮหย่อง,
                                หมู่ 6 บ้านนาทมใต้</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- JavaScript to handle navigation & interactive elements -->
    <script>
        function switchManualTab(role, button) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            // Show current role tab content
            document.getElementById(role + '-content').classList.add('active');

            // Deactivate all buttons & activate current
            document.querySelectorAll('.manual-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            button.classList.add('active');

            // Hide/Show corresponding sidebar
            document.querySelectorAll('.sidebar-content').forEach(sidebar => {
                sidebar.style.display = 'none';
            });
            document.getElementById(role + '-sidebar').style.display = 'block';

            // Reset active menu link on the sidebar
            const activeSidebar = document.getElementById(role + '-sidebar');
            const firstLink = activeSidebar.querySelector('.sidebar-menu a');

            // Deactivate all sidebar links
            document.querySelectorAll('.sidebar-menu a').forEach(a => {
                a.classList.remove('active');
            });

            if (firstLink) {
                firstLink.classList.add('active');
                // Scroll to target smoothly on mobile view if needed
                const targetId = firstLink.getAttribute('href').substring(1);
                const targetEl = document.getElementById(targetId);
                if (targetEl) {
                    targetEl.scrollIntoView({ behavior: 'smooth' });
                }
            }
        }

        function handleMenuClick(link) {
            // Prevent default behavior to maintain smooth scroll safely
            const e = window.event;
            if (e) {
                e.preventDefault();
            }

            // Remove active class from all links inside both sidebars
            document.querySelectorAll('.sidebar-menu a').forEach(a => {
                a.classList.remove('active');
            });

            // Set active current link
            link.classList.add('active');

            // Smooth Scroll to target element
            const targetId = link.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({ behavior: 'smooth' });
            }

            // Center the clicked menu item on mobile horizontal scroll
            if (window.innerWidth <= 992) {
                const menuContainer = link.closest('.sidebar-menu');
                if (menuContainer) {
                    const offsetLeft = link.offsetLeft;
                    const containerWidth = menuContainer.clientWidth;
                    const linkWidth = link.clientWidth;
                    menuContainer.scrollTo({
                        left: offsetLeft - (containerWidth / 2) + (linkWidth / 2),
                        behavior: 'smooth'
                    });
                }
            }
        }

        // Highlight active section on scroll
        document.addEventListener('scroll', () => {
            const sections = document.querySelectorAll('.tab-content.active section');
            const scrollPos = window.scrollY || window.pageYOffset || document.documentElement.scrollTop;

            sections.forEach(section => {
                const isMobile = window.innerWidth <= 992;
                const offsetThreshold = isMobile ? 120 : 60; // Larger threshold on mobile due to sticky bar
                
                const sectionTop = section.offsetTop - offsetThreshold;
                const sectionHeight = section.offsetHeight;
                const sectionId = section.getAttribute('id');

                if (scrollPos >= sectionTop && scrollPos < sectionTop + sectionHeight) {
                    const activeLink = document.querySelector(`.sidebar-content[style*="display: block"] .sidebar-menu a[href="#${sectionId}"]`);
                    if (activeLink && !activeLink.classList.contains('active')) {
                        document.querySelectorAll('.sidebar-menu a').forEach(a => {
                            a.classList.remove('active');
                        });
                        activeLink.classList.add('active');

                        // Scroll active menu item into view horizontally on mobile
                        if (isMobile) {
                            const menuContainer = activeLink.closest('.sidebar-menu');
                            if (menuContainer) {
                                const offsetLeft = activeLink.offsetLeft;
                                const containerWidth = menuContainer.clientWidth;
                                const linkWidth = activeLink.clientWidth;
                                menuContainer.scrollTo({
                                    left: offsetLeft - (containerWidth / 2) + (linkWidth / 2),
                                    behavior: 'smooth'
                                });
                            }
                        }
                    }
                }
            });
        });

        // Back to Top & Back to Dashboard functionality
        window.addEventListener('scroll', () => {
            const backToTopBtn = document.getElementById("backToTopBtn");
            const backToDashboardBtn = document.getElementById("backToDashboardBtn");
            
            const scrollTop = document.body.scrollTop || document.documentElement.scrollTop;
            const scrollHeight = document.documentElement.scrollHeight;
            const clientHeight = document.documentElement.clientHeight;

            if (backToTopBtn) {
                if (scrollTop > 300) {
                    backToTopBtn.classList.add("show");
                } else {
                    backToTopBtn.classList.remove("show");
                }
            }

            if (backToDashboardBtn) {
                // Show when scrolled to near the bottom (within 150px)
                if (scrollTop + clientHeight >= scrollHeight - 150) {
                    backToDashboardBtn.classList.add("show");
                } else {
                    backToDashboardBtn.classList.remove("show");
                }
            }
        });

        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    </script>

    <!-- Floating Action Buttons -->
    <a href="<?= htmlspecialchars($back_url) ?>" id="backToDashboardBtn" class="back-to-dashboard" title="กลับไปหน้าควบคุม">
        💻 กลับไปหน้าควบคุม
    </a>
    <button onclick="scrollToTop()" id="backToTopBtn" class="back-to-top" title="กลับขึ้นบนสุด">
        ▲
    </button>
</body>

</html>