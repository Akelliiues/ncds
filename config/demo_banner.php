<?php
// config/demo_banner.php - แถบแจ้งเตือนลอยสำหรับโหมด Demo Sandbox ปรากฏทุกหน้ารายการเมนู
// 1. แถบแจ้งเตือนโหมดจำลองมุมมอง อสม. สำหรับเจ้าหน้าที่ (Impersonation Mode)
if (!empty($_SESSION['is_admin_impersonating'])):
    $impersonatedVhvName = $_SESSION['vhv_name'] ?? 'อสม.';
    $impersonatedMoo = $_SESSION['vhv_moo'] ?? '';
    $adminUsername = $_SESSION['impersonator_admin']['admin_username'] ?? 'เจ้าหน้าที่';
    
    // คำนวณ URL สำหรับกลับสู่ระบบแอดมิน
    $exitImpUrl = 'admin/exit_impersonation.php';
    if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/vhv/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false) {
        $exitImpUrl = '../admin/exit_impersonation.php';
    }
?>
<div id="vhv-impersonation-floating-banner" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 999999;
    background: linear-gradient(90deg, #0d2c54 0%, #0369a1 100%);
    border-bottom: 2.5px solid #38bdf8;
    color: #FFFFFF;
    padding: 8px 16px;
    font-size: 13px;
    font-family: 'Sarabun', var(--font-base, sans-serif);
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 16px rgba(0,0,0,0.4);
    box-sizing: border-box;
">
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <span style="background: linear-gradient(135deg, #38bdf8, #0284c7); color: #ffffff; font-size: 11px; font-weight: 900; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 6px rgba(56,189,248,0.4);">
            👁️ VHV View
        </span>
        <span style="font-size: 13px; font-weight: 600; color: #F8FAFC;">
            <strong>มุมมอง อสม.: <?= htmlspecialchars($impersonatedVhvName) ?></strong> <?= $impersonatedMoo ? '(หมู่ที่ ' . htmlspecialchars($impersonatedMoo) . ')' : '' ?> <span style="color: #93c5fd; font-size: 12px; font-weight: 400;">(เข้าดูโดยเจ้าหน้าที่: <?= htmlspecialchars($adminUsername) ?>)</span>
        </span>
    </div>
    <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
        <a href="<?= htmlspecialchars($exitImpUrl) ?>" style="
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            border: 1.5px solid rgba(255, 255, 255, 0.6);
            color: #FFFFFF;
            padding: 4px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 800;
            font-size: 12px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
        " onmouseover="this.style.background='rgba(255,255,255,0.35)'; this.style.borderColor='#FFFFFF';" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'; this.style.borderColor='rgba(255, 255, 255, 0.6)';">
            <span>↩️</span> <span>กลับสู่ระบบเจ้าหน้าที่</span>
        </a>
    </div>
</div>
<style>
    body {
        padding-top: 46px !important;
    }
</style>
<?php
// 2. แถบแจ้งเตือนโหมด Demo Sandbox
elseif (isset($_SESSION['is_demo_mode']) && $_SESSION['is_demo_mode'] === true):
    $roleName = 'อสม. (หมอคนที่ 1)';
    if (($_SESSION['demo_role'] ?? '') === 'staff') {
        $roleName = 'เจ้าหน้าที่ รพ.สต.';
    } elseif (($_SESSION['demo_role'] ?? '') === 'admin') {
        $roleName = 'ผู้ดูแลระบบ (Admin/สสอ.)';
    }
    
    // คำนวณ URL สำหรับออกจากโหมด Demo ให้ถูกต้องทุกระดับชั้นของโฟลเดอร์
    $exitUrl = 'index.php?exit_demo=1';
    if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/vhv/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false) {
        $exitUrl = '../index.php?exit_demo=1';
    }
?>
<div id="demo-mode-floating-banner" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 999999;
    background: linear-gradient(90deg, #1E293B 0%, #0F172A 100%);
    border-bottom: 2.5px solid #F59E0B;
    color: #FFFFFF;
    padding: 8px 16px;
    font-size: 13px;
    font-family: 'Sarabun', var(--font-base, sans-serif);
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 16px rgba(0,0,0,0.4);
    box-sizing: border-box;
">
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <span style="background: linear-gradient(135deg, #F59E0B, #D97706); color: #0F172A; font-size: 11px; font-weight: 900; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 6px rgba(245,158,11,0.4);">
            Virtual Mode
        </span>
        <span style="font-size: 13px; font-weight: 600; color: #F8FAFC;">
            <strong>โหมดจำลองบทบาท: <?= htmlspecialchars($roleName) ?></strong> <span style="color: #94A3B8; font-size: 12px; font-weight: 400;">(ใช้ข้อมูลจำลอง Mockup Data)</span>
        </span>
    </div>
    <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
        <a href="<?= htmlspecialchars($exitUrl) ?>" style="
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.25), rgba(185, 28, 28, 0.35));
            border: 1.5px solid #EF4444;
            color: #FCA5A5;
            padding: 4px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        " onmouseover="this.style.background='#EF4444'; this.style.color='#FFFFFF';" onmouseout="this.style.background='rgba(239, 68, 68, 0.25)'; this.style.color='#FCA5A5';">
            <span>🚪</span> <span>ออกจากโหมดทดลอง</span>
        </a>
    </div>
</div>
<style>
    /* ปรับ padding-top ของ body เพื่อป้องกันไม่ให้ แถบแจ้งเตือนลอย บังเมนูหรือเนื้อหา */
    body {
        padding-top: 46px !important;
    }
</style>
<?php endif; ?>
