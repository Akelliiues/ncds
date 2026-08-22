<?php
// qr.php - PDPA Security & External Scanner Interceptor
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/demo_data.php';
require_once __DIR__ . '/config/demo_banner.php';

$code = $_GET['code'] ?? $_GET['hid'] ?? $_GET['cid'] ?? '';
$isVhvLoggedIn = isset($_SESSION['vhv_id']);
$isDemo = DemoDataProvider::isDemoMode();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบความปลอดภัย PDPA - อสม. ตาลสุม</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <style>
        .pdpa-card {
            max-width: 440px;
            margin: 20px auto;
            background: var(--bg-card);
            border-radius: 24px;
            padding: 24px 20px;
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color, rgba(255,255,255,0.12));
            text-align: center;
            box-sizing: border-box;
        }
        .pdpa-shield-badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #DC2626, #991B1B);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            color: white;
            margin: 0 auto 16px auto;
            box-shadow: 0 10px 25px -5px rgba(220, 38, 38, 0.4);
            animation: pulse-shield 2s infinite;
        }
        @keyframes pulse-shield {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .pdpa-title {
            font-size: 20px;
            font-weight: 900;
            color: var(--color-red, #DC2626);
            margin: 0 0 6px 0;
            line-height: 1.3;
        }
        .pdpa-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 700;
            margin: 0 0 18px 0;
        }
        .pdpa-info-box {
            background: rgba(220, 38, 38, 0.06);
            border: 1.5px solid rgba(220, 38, 38, 0.3);
            border-radius: 16px;
            padding: 16px;
            text-align: left;
            margin-bottom: 20px;
            box-shadow: var(--neumorph-inset);
        }
        .pdpa-rule-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: var(--text-primary);
            margin-bottom: 10px;
            line-height: 1.45;
        }
        .pdpa-rule-item:last-child {
            margin-bottom: 0;
        }
        .pdpa-badge-pill {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(220, 38, 38, 0.15);
            color: #DC2626;
            margin-bottom: 12px;
            border: 1px solid rgba(220, 38, 38, 0.3);
        }
    </style>
</head>
<body class="vhv-accessibility" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box; background: var(--bg-darker);">

<div class="pdpa-card">
    <div class="pdpa-shield-badge">
        🔒
    </div>

    <span class="pdpa-badge-pill">🛡️ PDPA Protected System</span>

    <h2 class="pdpa-title">การเข้าถึงถูกจำกัดสิทธิ์</h2>
    <p class="pdpa-subtitle">พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562</p>

    <div class="pdpa-info-box">
        <div class="pdpa-rule-item">
            <span style="font-size: 18px; flex-shrink: 0;">🚫</span>
            <div>
                <strong>ไม่อนุญาตให้เปิดผ่านแอปภายนอก:</strong><br>
                การสแกนผ่านกล้องโทรศัพท์ทั่วไป, LINE หรือเบราว์เซอร์อื่น ไม่สามารถเข้าถึงข้อมูลสุขภาพได้
            </div>
        </div>
        <div class="pdpa-rule-item">
            <span style="font-size: 18px; flex-shrink: 0;">🩺</span>
            <div>
                <strong>ต้องสแกนด้วยแอป อสม. ตาลสุม เท่านั้น:</strong><br>
                การคัดกรองสุขภาพประจำบ้าน ต้องดำเนินการโดย อสม. ที่ได้รับมอบหมาย ผ่านตัวสแกนภายในแอปพลิเคชันเพื่อตรวจสอบพิกัด GPS
            </div>
        </div>
        <div class="pdpa-rule-item">
            <span style="font-size: 18px; flex-shrink: 0;">⚖️</span>
            <div>
                <strong>การคุ้มครองข้อมูลสุขภาพ:</strong><br>
                ข้อมูลประวัติสุขภาพจัดเป็นข้อมูลส่วนบุคคลอ่อนไหว (Sensitive Data) ห้ามบุคคลภายนอกเข้าถึงโดยไม่ได้รับอนุญาต
            </div>
        </div>
    </div>

    <?php if ($isVhvLoggedIn): ?>
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 14px; font-weight: 600;">
            ✅ ท่านเข้าสู่ระบบในฐานะ อสม. อยู่แล้ว<br>กรุณากดปุ่มด้านล่างเพื่อเปิดตัวสแกนของแอป:
        </p>
        <a href="vhv/scan.php<?= !empty($code) ? '?hid=' . urlencode($code) : '' ?>" class="btn-giant btn-giant-primary" style="display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; border-radius: 16px; padding: 14px; font-size: 16px; margin-bottom: 10px;">
            <span>📱 เปิดตัวสแกนในแอป อสม.</span>
        </a>
        <a href="vhv/index.php" class="btn-giant btn-giant-secondary" style="display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; border-radius: 16px; padding: 12px; font-size: 14px;">
            <span>🏠 กลับหน้าหลัก อสม.</span>
        </a>
    <?php else: ?>
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 14px; font-weight: 600;">
            หากท่านเป็น <strong>อสม. ผู้รับผิดชอบ</strong> กรุณาเข้าสู่ระบบเพื่อดำเนินการ:
        </p>
        <a href="index.php<?= !empty($code) ? '?target_hid=' . urlencode($code) : '' ?>" class="btn-giant btn-giant-primary" style="display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; border-radius: 16px; padding: 14px; font-size: 16px; margin-bottom: 10px;">
            <span>🔐 เข้าสู่ระบบ อสม. ตาลสุม</span>
        </a>
        <a href="about.php" style="font-size: 12.5px; color: var(--text-muted); text-decoration: none; font-weight: 600; display: inline-block; margin-top: 8px;">
            ℹ️ เกี่ยวกับระบบคัดกรองสุขภาพและมาตรการ PDPA
        </a>
    <?php endif; ?>
</div>

</body>
</html>
