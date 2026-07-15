<?php
// admin/navbar.php
$current_page = basename($_SERVER['PHP_SELF']);
// Determine if super admin
$is_super_admin = (!isset($admin_hoscode) || empty($admin_hoscode)) && (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] !== 'adminsso');

$is_core_active = in_array($current_page, ['index.php', 'profile.php', 'leaderboard.php']);
$is_targets_active = in_array($current_page, ['target_manager.php', 'dpac_manager.php']);
$is_work_active = in_array($current_page, ['assignment.php', 'vhv_approval.php', 'print_qr.php']);
$is_reports_active = in_array($current_page, ['analytics.php', 'reports.php', 'security_log.php']);
$is_system_active = in_array($current_page, ['import_hdc.php', 'process_etl.php', 'resolve_all_duplicates.php', 'db_manager.php', 'user_manager.php', 'unit_house_manager.php', 'update.php']);
?>
<script>
    // Immediately apply theme before rendering
    (function() {
        const theme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>
<style>
.btn-theme-toggle:hover {
    background-color: var(--bg-darker) !important;
}
    /* Premium Categorized Dropdowns Style */
    .admin-nav-links {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .nav-dropdown {
        position: relative;
        display: inline-block;
    }

    .nav-dropbtn {
        background-color: var(--bg-card);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        padding: 9px 16px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 13.5px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: var(--neumorph-flat);
        transition: all var(--transition-speed);
        box-sizing: border-box;
    }

    .nav-dropbtn:hover {
        color: var(--color-accent) !important;
        border-color: var(--color-accent);
        transform: translateY(-1px);
    }

    .nav-dropbtn.active {
        background-color: var(--color-primary);
        color: #ffffff !important;
        border-color: var(--color-primary);
        box-shadow: none;
    }

    .nav-dropbtn .chevron {
        transition: transform var(--transition-speed);
        opacity: 0.7;
    }

    .nav-dropdown:hover .nav-dropbtn .chevron {
        transform: rotate(180deg);
    }

    .nav-dropdown-content {
        position: absolute;
        top: 100%;
        left: 0;
        right: auto;
        margin-top: 6px;
        background-color: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(13, 44, 84, 0.12);
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(13, 44, 84, 0.12), 0 1px 8px rgba(0, 0, 0, 0.05);
        min-width: 240px;
        opacity: 0;
        pointer-events: none;
        transform: translateY(10px);
        transition: all 0.2s ease;
        z-index: 2000;
        padding: 8px 0;
    }

    /* Right-align the last dropdown to prevent overflowing the right edge of the screen */
    .nav-dropdown:last-of-type .nav-dropdown-content {
        left: auto;
        right: 0;
    }

    /* Invisible bridge to prevent dropdown closing when moving mouse from button to content */
    .nav-dropdown-content::before {
        content: '';
        position: absolute;
        top: -12px;
        left: 0;
        right: 0;
        height: 12px;
        background: transparent;
    }

    .nav-dropdown:hover .nav-dropdown-content {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
    }

    .nav-dropdown-content a {
        color: #4b5563 !important; /* High contrast dark grey text on light background */
        text-decoration: none !important;
        padding: 10px 18px !important;
        margin: 0 !important; /* Override any global margins */
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important; /* Left-align the contents */
        gap: 12px !important;
        font-size: 13.5px !important;
        font-weight: 600 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        background: transparent !important;
        width: 100% !important;
        text-align: left !important;
        box-sizing: border-box !important;
        transition: all 0.15s ease !important;
    }

    /* Enforce uniform icon sizing and center alignment to make sure the text labels align perfectly */
    .nav-dropdown-content a svg {
        width: 16px !important;
        height: 16px !important;
        min-width: 16px !important;
        min-height: 16px !important;
        flex-shrink: 0 !important;
        margin: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .nav-dropdown-content a:hover {
        background: rgba(13, 44, 84, 0.08) !important; /* Soft navy background on hover */
        color: #0d2c54 !important; /* Premium navy text for high contrast */
        transform: none !important;
    }

    .nav-dropdown-content a.active {
        background-color: var(--color-primary) !important;
        color: #ffffff !important;
    }

    /* Standalone logout button circle */
    .btn-logout-circle {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background-color: var(--bg-card);
        box-shadow: var(--neumorph-flat);
        color: var(--color-red) !important;
        transition: all var(--transition-speed);
        margin-left: 5px;
    }

    .btn-logout-circle:hover {
        color: #ffffff !important;
        background-color: var(--color-red) !important;
        transform: translateY(-2px);
    }

    /* Global Back to top floating button for Admin pages */
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
</style>
<?php if (isset($_SESSION['is_visitor']) && $_SESSION['is_visitor'] === true): ?>
    <div class="no-print" style="background: linear-gradient(90deg, #f59e0b, #d97706); color: white; text-align: center; padding: 10px 20px; font-weight: 800; font-size: 14px; letter-spacing: 0.5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 0 0 12px 12px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; animation: slideDown 0.5s ease;">
        <span>👁️ <strong>ระบบอยู่ในโหมดผู้มาเยือน (Visitor Mode - Read Only)</strong> : คุณสามารถเรียกดูสถิติและข้อมูลต่างๆ ได้ครบถ้วน แต่จะไม่สามารถเพิ่ม แก้ไข ลบข้อมูล หรือประมวลผลระบบได้</span>
    </div>
    <style>
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        /* Disable submit and action buttons visually for visitor */
        button[type="submit"], input[type="submit"], .btn-danger, .btn-action, .btn-save, .admin-btn-action, form button, .button-action, button.numpad-btn {
            opacity: 0.65;
            cursor: not-allowed !important;
            pointer-events: none !important;
        }
    </style>
<?php endif; ?>
<div class="admin-navbar no-print">
    <a href="index.php" class="admin-logo" style="display: flex; align-items: center; gap: 10px;">
        <img src="../assets/icon.png" alt="Logo" style="height: 35px; width: 35px;">
        <span>NCDs Prevention Portal</span>
        <?php if (isset($_SESSION['is_visitor']) && $_SESSION['is_visitor'] === true): ?>
            <span style="background-color: rgba(245, 158, 11, 0.15); color: #d97706; border: 1.5px solid rgba(217, 119, 6, 0.4); padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; box-shadow: inset 1px 1px 3px rgba(0,0,0,0.05); margin-left: 5px;">
                👁️ โหมดผู้มาเยือน
            </span>
        <?php endif; ?>
    </a>
    <div class="admin-nav-links">
        <!-- 1. General/Core Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-dropbtn <?= $is_core_active ? 'active' : '' ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>ทั่วไป</span>
                <svg class="chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <path d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div class="nav-dropdown-content">
                <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                    </svg>
                    แดชบอร์ดสรุปผล
                </a>
                <a href="leaderboard.php" class="<?= $current_page == 'leaderboard.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                    กระดานคะแนน อสม.
                </a>
                <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    ข้อมูลส่วนตัว / เปลี่ยนรหัส
                </a>
                <a href="../manual.php">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    คู่มือการใช้งานระบบ
                </a>
                <a href="../about.php" onclick="openDevModal(event)">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    เกี่ยวกับระบบ
                </a>
            </div>
        </div>

        <!-- 2. Targets Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-dropbtn <?= $is_targets_active ? 'active' : '' ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span>เป้าหมาย</span>
                <svg class="chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <path d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div class="nav-dropdown-content">
                <a href="target_manager.php" class="<?= $current_page == 'target_manager.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    จัดการประชากรเป้าหมาย
                </a>
                <a href="dpac_manager.php" class="<?= $current_page == 'dpac_manager.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    จัดการโครงการ DPAC
                </a>
            </div>
        </div>

        <!-- 3. Work & VHVs Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-dropbtn <?= $is_work_active ? 'active' : '' ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <span>งาน & อสม.</span>
                <svg class="chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <path d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div class="nav-dropdown-content">
                <a href="assignment.php" class="<?= $current_page == 'assignment.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    มอบหมายงาน อสม.
                </a>
                <a href="vhv_approval.php" class="<?= $current_page == 'vhv_approval.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    จัดการผู้ใช้ อสม.
                </a>
                <a href="print_qr.php" class="<?= $current_page == 'print_qr.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                    พิมพ์ QR Code บ้าน
                </a>
            </div>
        </div>

        <!-- 4. Reports & Analytics Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-dropbtn <?= $is_reports_active ? 'active' : '' ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>วิเคราะห์ & รายงาน</span>
                <svg class="chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <path d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div class="nav-dropdown-content">
                <a href="analytics.php" class="<?= $current_page == 'analytics.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12z" />
                    </svg>
                    วิเคราะห์เชิงลึก (Analytics)
                </a>
                <a href="reports.php" class="<?= $current_page == 'reports.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    รายงานและการพิมพ์
                </a>
                <a href="security_log.php" class="<?= $current_page == 'security_log.php' ? 'active' : '' ?>" style="white-space: nowrap !important;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0110 0v4" />
                    </svg>
                    บันทึกความปลอดภัย (Secure Log)
                </a>
            </div>
        </div>

        <!-- 5. System Dropdown -->
        <div class="nav-dropdown">
            <button class="nav-dropbtn <?= $is_system_active ? 'active' : '' ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <span>จัดการระบบ</span>
                <svg class="chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <path d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div class="nav-dropdown-content">
                <?php if ($is_super_admin): ?>
                    <a href="import_hdc.php" class="<?= $current_page == 'import_hdc.php' ? 'active' : '' ?>">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        นำเข้าข้อมูล HDC
                    </a>
                    <a href="process_etl.php" class="<?= $current_page == 'process_etl.php' ? 'active' : '' ?>">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path>
                        </svg>
                        ประมวลผล ETL
                    </a>
                    <a href="resolve_all_duplicates.php" class="<?= $current_page == 'resolve_all_duplicates.php' ? 'active' : '' ?>">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        ควบรวมข้อมูลเป้าหมายซ้ำซ้อน
                    </a>
                <?php endif; ?>
                <a href="db_manager.php" class="<?= $current_page == 'db_manager.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                    </svg>
                    จัดการฐานข้อมูลระบบ / Sandbox
                </a>
                <?php if ($is_super_admin): ?>
                    <a href="user_manager.php" class="<?= $current_page == 'user_manager.php' ? 'active' : '' ?>">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 7a4 4 0 11-8 0 4 4 0 018 0zm7-3a3 3 0 010 6M21 21v-2a4 4 0 00-3-3.87"></path>
                        </svg>
                        จัดการผู้ใช้งานระบบ
                    </a>
                <?php endif; ?>
                <a href="unit_house_manager.php" class="<?= $current_page == 'unit_house_manager.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    จัดการหน่วยบริการ & บ้าน
                </a>
                <a href="update.php" class="<?= $current_page == 'update.php' ? 'active' : '' ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5" />
                    </svg>
                    อัปเดตระบบ (Update)
                </a>
            </div>
        </div>

        <!-- Theme Toggle Button -->
        <button id="theme-toggle-btn" class="btn-theme-toggle" onclick="toggleTheme()" style="background: none; border: none; cursor: pointer; color: var(--text-primary); display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; transition: background 0.3s; margin-right: 10px;" title="สลับโหมด มืด/สว่าง">
            <!-- Sun Icon -->
            <svg id="theme-toggle-sun" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="display: none;">
                <circle cx="12" cy="12" r="5"></circle>
                <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
            </svg>
            <!-- Moon Icon -->
            <svg id="theme-toggle-moon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path>
            </svg>
        </button>

        <!-- 6. Standalone Logout button -->
        <a href="../logout.php" class="btn-logout-circle" data-tooltip="ออกจากระบบ">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
        </a>
    </div>
</div>

<!-- Floating Back to Top Button -->
<button onclick="scrollToTop()" id="backToTopBtn" class="back-to-top no-print" title="กลับขึ้นบนสุด">
    ▲
</button>

<script>
    // Back to Top functionality
    window.addEventListener('scroll', () => {
        const backToTopBtn = document.getElementById("backToTopBtn");
        if (backToTopBtn) {
            const scrollTop = document.body.scrollTop || document.documentElement.scrollTop;
            if (scrollTop > 300) {
                backToTopBtn.classList.add("show");
            } else {
                backToTopBtn.classList.remove("show");
            }
        }
    });

    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // Toggle theme functionality
    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeIcons(newTheme);
        // Reload to let charts and other dynamic assets re-render
        window.location.reload();
    }

    function updateThemeIcons(theme) {
        const sunIcon = document.getElementById('theme-toggle-sun');
        const moonIcon = document.getElementById('theme-toggle-moon');
        if (sunIcon && moonIcon) {
            if (theme === 'dark') {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            } else {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            }
        }
    }

    // Run on load
    window.addEventListener('DOMContentLoaded', () => {
        const theme = localStorage.getItem('theme') || 'light';
        updateThemeIcons(theme);
    });
</script>
<?php include_once __DIR__ . '/../config/dev_modal.php'; ?>