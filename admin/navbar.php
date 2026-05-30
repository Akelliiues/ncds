<?php
// admin/navbar.php
$current_page = basename($_SERVER['PHP_SELF']);
// Determine if super admin
$is_super_admin = !isset($admin_hoscode) || empty($admin_hoscode);
?>
<div class="admin-navbar no-print">
    <a href="index.php" class="admin-logo" style="display: flex; align-items: center; gap: 10px;">
        <img src="../assets/icon.png" alt="Logo" style="height: 35px; width: 35px; border-radius: 50%; border: 1.5px solid rgba(255, 255, 255, 0.2);">
        <span>NCDs Prevention Portal</span>
    </a>
    <div class="admin-nav-links">
        <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>" data-tooltip="แดชบอร์ด">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                </path>
            </svg>
        </a>
        <?php if ($is_super_admin): ?>
            <a href="import_hdc.php" class="<?= $current_page == 'import_hdc.php' ? 'active' : '' ?>"
                data-tooltip="นำเข้าข้อมูล HDC">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
            </a>
            <a href="process_etl.php" class="<?= $current_page == 'process_etl.php' ? 'active' : '' ?>"
                data-tooltip="ประมวลผล ETL">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path>
                </svg>
            </a>
            <a href="db_manager.php" class="<?= $current_page == 'db_manager.php' ? 'active' : '' ?>"
                data-tooltip="จัดการฐานข้อมูลระบบ">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path
                        d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4">
                    </path>
                </svg>
            </a>
        <?php endif; ?>
        <a href="target_manager.php" class="<?= $current_page == 'target_manager.php' ? 'active' : '' ?>"
            data-tooltip="จัดการกลุ่มเป้าหมาย">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                </path>
            </svg>
        </a>
        <a href="hdc_list.php" class="<?= $current_page == 'hdc_list.php' ? 'active' : '' ?>"
            data-tooltip="คัดกรองความเสี่ยง HDC">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                </path>
            </svg>
        </a>
        <a href="dpac_manager.php" class="<?= $current_page == 'dpac_manager.php' ? 'active' : '' ?>"
            data-tooltip="จัดการโครงการ DPAC">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </a>
        <a href="assignment.php" class="<?= $current_page == 'assignment.php' ? 'active' : '' ?>"
            data-tooltip="มอบหมายงาน อสม.">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
            </svg>
        </a>
        <a href="print_qr.php" class="<?= $current_page == 'print_qr.php' ? 'active' : '' ?>"
            data-tooltip="พิมพ์ QR Code บ้าน">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                </path>
            </svg>
        </a>
        <a href="reports.php" class="<?= $current_page == 'reports.php' ? 'active' : '' ?>"
            data-tooltip="รายงานและการพิมพ์">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                </path>
            </svg>
        </a>

        <a href="vhv_approval.php" class="<?= $current_page == 'vhv_approval.php' ? 'active' : '' ?>"
            data-tooltip="จัดการผู้ใช้ อสม.">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                </path>
            </svg>
        </a>
        <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>"
            data-tooltip="ข้อมูลส่วนตัว / เปลี่ยนรหัสผ่าน">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
        </a>
        <a href="../logout.php" data-tooltip="ออกจากระบบ" style="color: var(--color-red) !important;">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                </path>
            </svg>
        </a>
    </div>
</div>