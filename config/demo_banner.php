<?php
// config/demo_banner.php - แถบแจ้งเตือนลอยสำหรับโหมด Demo Sandbox
if (isset($_SESSION['is_demo_mode']) && $_SESSION['is_demo_mode'] === true):
    $roleName = 'อสม. (หมอคนที่ 1)';
    if (($_SESSION['demo_role'] ?? '') === 'staff') {
        $roleName = 'เจ้าหน้าที่ รพ.สต.';
    } elseif (($_SESSION['demo_role'] ?? '') === 'admin') {
        $roleName = 'ผู้ดูแลระบบ (Admin/สสอ.)';
    }
?>
<div id="demo-mode-floating-banner" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 99999;
    background: linear-gradient(90deg, #1E293B 0%, #0F172A 100%);
    border-bottom: 2px solid #F59E0B;
    color: #FFFFFF;
    padding: 8px 14px;
    font-size: 13px;
    font-family: var(--font-base, sans-serif);
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
">
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <span style="background: #F59E0B; color: #1E293B; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px; text-transform: uppercase;">
            🧪 Demo Mode
        </span>
        <span>ท่านกำลังอยู่ใน <strong>โหมดจำลองบทบาท: <?= htmlspecialchars($roleName) ?></strong> (ใช้ข้อมูลสมมติ ไม่กระทบฐานข้อมูลจริง)</span>
    </div>
    <div style="display: flex; align-items: center; gap: 8px;">
        <a href="../index.php?exit_demo=1" style="
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #EF4444;
            color: #FCA5A5;
            padding: 3px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            white-space: nowrap;
        ">
            🚪 ออกจากโหมดทดลอง
        </a>
    </div>
</div>
<style>
    /* ปรับ margin-top ให้เนื้อหาไม่โดน banner บัง */
    body {
        padding-top: 42px !important;
    }
</style>
<?php endif; ?>
