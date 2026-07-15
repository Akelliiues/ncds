<?php
// admin/update.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !empty($_SESSION['admin_hoscode'])) {
    header("Location: index.php");
    exit();
}

// Prevent any caching of this update script (LiteSpeed, Cloudflare, browser cache)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once __DIR__ . '/../config/db.php';
$admin_title = get_admin_title();
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

// Load local changelog
$local_changelog_file = __DIR__ . '/../changelog.json';
$local_changelog = [];
if (file_exists($local_changelog_file)) {
    $local_changelog = json_decode(file_get_contents($local_changelog_file), true) ?: [];
}
$local_version = !empty($local_changelog) ? $local_changelog[0] : ['title' => 'ไม่พบข้อมูลเวอร์ชัน', 'date' => '', 'type' => 'info'];

$update_available = false;
$remote_version = null;
$error = null;
$success = null;
$remote_changelog = [];

$new_updates_list = [];

// Fetch remote changelog
try {
    $ctx = stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: PHP-AutoUpdater\r\n",
            "timeout" => 10
        ]
    ]);
    $remote_content = @file_get_contents('https://raw.githubusercontent.com/Akelliiues/ncds/main/changelog.json?t=' . time(), false, $ctx);
    if ($remote_content) {
        $remote_changelog = json_decode($remote_content, true);
        if (!empty($remote_changelog)) {
            $remote_version = $remote_changelog[0];
            if ($remote_version['title'] !== $local_version['title'] || $remote_version['date'] !== $local_version['date']) {
                $update_available = true;

                // Get list of new updates since local version
                foreach ($remote_changelog as $remote_item) {
                    if ($remote_item['title'] === $local_version['title'] && $remote_item['date'] === $local_version['date']) {
                        break;
                    }
                    $new_updates_list[] = $remote_item;
                }
            }
        }
    } else {
        $error = "ไม่สามารถตรวจหาเวอชันล่าสุดได้เนื่องจากปัญหาการเชื่อมต่ออินเทอร์เน็ต (หรือ GitHub บล็อกการร้องขอ)";
    }
} catch (Exception $e) {
    $error = "เกิดข้อผิดพลาดในการตรวจสอบรุ่น: " . $e->getMessage();
}

// Handle Auto Update Action
if (isset($_POST['trigger_update']) && $update_available) {
    $temp_zip = __DIR__ . '/../config/update_temp.zip';
    try {
        // 1. Download ZIP
        $zip_url = 'https://github.com/Akelliiues/ncds/archive/refs/heads/main.zip';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $zip_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-AutoUpdater');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $zip_data = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception("ดาวน์โหลดไฟล์ติดตั้งล่าช้าหรือล้มเหลว: " . curl_error($ch));
        }
        curl_close($ch);

        if (file_put_contents($temp_zip, $zip_data) === false) {
            throw new Exception("ไม่สามารถบันทึกไฟล์อัปเดตลงเครื่องได้ กรุณาตรวจสอบสิทธิ์การเขียนเขียนไฟล์ในระบบ (Permission)");
        }

        // 2. Extract ZIP
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($temp_zip) === TRUE) {
                $extract_path = __DIR__ . '/../';

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $parts = explode('/', $filename);
                    array_shift($parts); // Remove GitHub repository name (e.g. ncds-main)
                    $relative_name = implode('/', $parts);

                    if (empty($relative_name)) continue;

                    // Skip config files to prevent overwriting health office details
                    if (strpos($relative_name, 'config/db_config.php') !== false || strpos($relative_name, 'config/line_config.php') !== false) {
                        continue;
                    }
                    if (strpos($relative_name, '.git/') !== false) {
                        continue;
                    }

                    $target_file = $extract_path . $relative_name;

                    if (substr($filename, -1) === '/') {
                        if (!is_dir($target_file)) {
                            @mkdir($target_file, 0755, true);
                        }
                    } else {
                        $dir = dirname($target_file);
                        if (!is_dir($dir)) {
                            @mkdir($dir, 0755, true);
                        }
                        if (@copy("zip://{$temp_zip}#{$filename}", $target_file) === false) {
                            throw new Exception("ไม่สามารถเขียนไฟล์ทับได้ (Permission Error) ที่ไฟล์: " . $relative_name . " (กรุณาตั้งค่าสิทธิ์ให้เป็น 0755 หรือ 0777)");
                        }
                    }
                }
                $zip->close();
                @unlink($temp_zip);

                // Clear OPcache and stat cache to ensure fresh PHP files and file systems are loaded
                if (function_exists('opcache_reset')) {
                    @opcache_reset();
                }
                clearstatcache();

                $success = "ระบบได้รับการอัปเกรดเป็นเวอร์ชันใหม่เรียบร้อยแล้ว!";
                $update_available = false;
                $_SESSION['installed_updates'] = $new_updates_list;

                // Reload local changelog
                if (file_exists($local_changelog_file)) {
                    $local_changelog = json_decode(file_get_contents($local_changelog_file), true) ?: [];
                    $local_version = !empty($local_changelog) ? $local_changelog[0] : ['title' => 'อัปเกรดสำเร็จ', 'date' => '', 'type' => 'info'];
                }
            } else {
                throw new Exception("ไม่สามารถคลี่ไฟล์อัปเดต ZIP ได้");
            }
        } else {
            throw new Exception("เซิร์ฟเวอร์ไม่ได้เปิดใช้งานคลาส ZipArchive ของ PHP");
        }
    } catch (Exception $e) {
        $error = "การอัปเดตระบบขัดข้อง: " . $e->getMessage();
        if (file_exists($temp_zip)) {
            @unlink($temp_zip);
        }
    }
}

$current_page = 'update.php';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปเดตระบบคัดกรอง NCD - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .update-card {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--neumorph-flat);
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .update-card {
                grid-template-columns: 1fr;
            }
        }

        .version-box {
            background: var(--bg-main);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--neumorph-inset);
            text-align: center;
        }

        .version-label {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .version-title {
            font-size: 17px;
            font-weight: bold;
            color: var(--text-primary);
            line-height: 1.4;
            margin-bottom: 5px;
        }

        .version-date {
            font-size: 13.5px;
            color: var(--color-accent);
            font-weight: bold;
        }

        .btn-update {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: linear-gradient(135deg, #4f46e5 0%, #2563eb 100%);
            color: white !important;
            border: none;
            padding: 18px 36px;
            font-size: 18px;
            font-weight: 800;
            border-radius: 16px;
            cursor: pointer;
            width: 100%;
            max-width: 550px;
            margin-top: 15px;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-update:not(:disabled) {
            background: linear-gradient(135deg, #ff9800 0%, #f44336 100%);
            /* Orange-Red warning gradient */
            box-shadow: 0 10px 20px rgba(244, 67, 54, 0.35);
            animation: pulse-glow 2s infinite alternate;
        }

        @keyframes pulse-glow {
            0% {
                box-shadow: 0 8px 16px rgba(244, 67, 54, 0.35);
                transform: scale(1);
            }

            100% {
                box-shadow: 0 12px 28px rgba(244, 67, 54, 0.65);
                transform: scale(1.02);
            }
        }

        .btn-update:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 15px 35px rgba(244, 67, 54, 0.7);
            background: linear-gradient(135deg, #ffa726 0%, #ff5252 100%);
        }

        .btn-update:disabled {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            color: #94a3b8 !important;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        .changelog-box {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--neumorph-inset);
        }

        .log-item {
            border-left: 3px solid var(--color-primary);
            padding-left: 15px;
            margin-bottom: 20px;
        }

        .log-item:last-child {
            margin-bottom: 0;
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .log-type {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .log-date {
            font-size: 12.5px;
            color: var(--text-muted);
        }

        .log-title {
            font-size: 14.5px;
            line-height: 1.5;
            color: var(--text-primary);
        }

        .alert-box {
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 25px;
            font-weight: 500;
            font-size: 15px;
            line-height: 1.5;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1.5px solid #ef4444;
            color: #ef4444;
        }

        .alert-success {
            background-color: rgba(34, 197, 94, 0.15);
            border: 1.5px solid #22c55e;
            color: #22c55e;
        }

        /* Custom Confirmation Modal Styles */
        .custom-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .modal-overlay {
            position: absolute;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .modal-content {
            position: relative;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            max-width: 480px;
            width: 90%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            text-align: center;
            animation: modal-anim 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modal-anim {
            from {
                transform: scale(0.9) translateY(20px);
                opacity: 0;
            }

            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        .modal-icon {
            font-size: 50px;
            margin-bottom: 15px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 12px 0;
        }

        .modal-message {
            font-size: 14.5px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .modal-warning {
            background-color: rgba(245, 158, 11, 0.08);
            border-left: 4px solid #f59e0b;
            color: #d97706;
            font-size: 13px;
            padding: 12px 16px;
            border-radius: 8px;
            text-align: left;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-modal-cancel {
            background: rgba(148, 163, 184, 0.15);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 10px 20px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 10px;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .btn-modal-cancel:hover {
            background: rgba(148, 163, 184, 0.25);
        }

        .btn-modal-confirm {
            background: linear-gradient(135deg, #ff9800 0%, #f44336 100%);
            color: white;
            border: none;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(244, 67, 54, 0.3);
            transition: all var(--transition-speed);
        }

        .btn-modal-confirm:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(244, 67, 54, 0.5);
        }
    </style>
</head>

<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1000px; margin: 40px auto; padding: 0 20px;">
        <h2 style="color: var(--color-accent); margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
            🔄 ระบบอัปเกรดฟีเจอร์อัตโนมัติ (Auto Updater)
        </h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 15px;">
            คุณสามารถกดอัปเดตระบบเพื่อรับฟีเจอร์และรายงานใหม่ล่าสุดจากนักพัฒนาส่วนกลางได้ทันที โดยไม่มีการลบข้อมูลรหัสเชื่อมต่อฐานข้อมูลของคุณ
        </p>

        <?php if ($error): ?>
            <div class="alert-box alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-box alert-success" style="text-align: left;">
                <h4 style="margin: 0 0 10px 0; color: #166534; font-size: 16px; font-weight: 800;">
                    🎉 <?= htmlspecialchars($success) ?>
                </h4>
                <?php
                $installed = $_SESSION['installed_updates'] ?? [];
                unset($_SESSION['installed_updates']); // Clear after showing
                if (!empty($installed)):
                ?>
                    <div style="margin-top: 15px; border-top: 1px dashed rgba(22, 101, 52, 0.25); padding-top: 12px;">
                        <strong style="font-size: 13.5px; color: #166534; display: block; margin-bottom: 8px;">📋 รายการปรับปรุงที่ติดตั้งสำเร็จ:</strong>
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <?php foreach ($installed as $item): ?>
                                <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #14532d; line-height: 1.5;">
                                    <span style="color: #22c55e; font-weight: bold; flex-shrink: 0;">✔️</span>
                                    <span><?= htmlspecialchars($item['title']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="update-card">
            <div class="version-box">
                <div class="version-label">เวอร์ชันปัจจุบันของเครื่องคุณ</div>
                <div class="version-title"><?= htmlspecialchars($local_version['title']) ?></div>
                <div class="version-date">🗓️ วันที่แก้ไข: <?= htmlspecialchars($local_version['date'] ?: 'ไม่ระบุ') ?></div>
            </div>

            <div class="version-box">
                <div class="version-label">เวอร์ชันล่าสุดบน Server ต้นทาง</div>
                <?php if ($remote_version): ?>
                    <div class="version-title"><?= htmlspecialchars($remote_version['title']) ?></div>
                    <div class="version-date">🗓️ วันที่แก้ไข: <?= htmlspecialchars($remote_version['date']) ?></div>
                <?php else: ?>
                    <div class="version-title" style="color: var(--text-muted);">ไม่สามารถตรวจสอบได้</div>
                    <div class="version-date" style="color: var(--text-muted);">กรุณาตรวจสอบอินเทอร์เน็ต</div>
                <?php endif; ?>
            </div>
        </div>

        <div style="text-align: center; margin-bottom: 40px;">
            <form method="POST" action="update.php" id="update-form" onsubmit="return confirmUpdate(event)">
                <?php if ($update_available): ?>
                    <button type="submit" name="trigger_update" class="btn-update" id="update-btn">
                        🚀 ตรวจพบเวอร์ชันใหม่! คลิกเพื่ออัปเดต 🆙
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-update" disabled style="background-color: var(--border-color); color: var(--text-muted); cursor: not-allowed;">
                        ✔️ ระบบของคุณเป็นรุ่นล่าสุดแล้ว ไม่ต้องอัปเดตเพิ่มเติม 💯
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($update_available && !empty($new_updates_list)): ?>
            <div class="changelog-box" style="border: 2px dashed var(--color-primary); background-color: rgba(99, 102, 241, 0.03);">
                <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--color-primary); border-bottom: 1.5px solid var(--border-color); padding-bottom: 10px;">
                    ✨ รายการฟีเจอร์และการปรับปรุงที่จะได้รับการติดตั้ง (New Features to Install)
                </h3>
                <div>
                    <?php
                    foreach ($new_updates_list as $log):
                        $bg = 'rgba(156, 163, 175, 0.15)';
                        $fg = '#9ca3af';
                        if (($log['type'] ?? '') === 'fix') {
                            $bg = 'rgba(239, 68, 68, 0.15)';
                            $fg = '#ef4444';
                        } elseif (($log['type'] ?? '') === 'feature') {
                            $bg = 'rgba(16, 185, 129, 0.15)';
                            $fg = '#10b981';
                        }
                    ?>
                        <div class="log-item">
                            <div class="log-header">
                                <span class="log-type" style="background-color: <?= $bg ?>; color: <?= $fg ?>;"><?= htmlspecialchars(strtoupper($log['type'] ?? 'info')) ?></span>
                                <span class="log-date"><?= htmlspecialchars($log['date']) ?></span>
                            </div>
                            <div class="log-title" style="font-weight: bold;"><?= htmlspecialchars($log['title']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif (!empty($remote_changelog)): ?>
            <div class="changelog-box">
                <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--color-accent); border-bottom: 1.5px solid var(--border-color); padding-bottom: 10px;">
                    📜 ประวัติการปรับปรุงระบบที่ผ่านมา (Changelog)
                </h3>
                <div>
                    <?php
                    $show_limit = min(5, count($remote_changelog));
                    for ($j = 0; $j < $show_limit; $j++):
                        $log = $remote_changelog[$j];
                        $bg = 'rgba(156, 163, 175, 0.15)';
                        $fg = '#9ca3af';
                        if (($log['type'] ?? '') === 'fix') {
                            $bg = 'rgba(239, 68, 68, 0.15)';
                            $fg = '#ef4444';
                        } elseif (($log['type'] ?? '') === 'feature') {
                            $bg = 'rgba(16, 185, 129, 0.15)';
                            $fg = '#10b981';
                        }
                    ?>
                        <div class="log-item">
                            <div class="log-header">
                                <span class="log-type" style="background-color: <?= $bg ?>; color: <?= $fg ?>;"><?= htmlspecialchars(strtoupper($log['type'] ?? 'info')) ?></span>
                                <span class="log-date"><?= htmlspecialchars($log['date']) ?></span>
                            </div>
                            <div class="log-title"><?= htmlspecialchars($log['title']) ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Custom Confirmation Modal & Progress Bar -->
    <div id="confirm-modal" class="custom-modal" style="display: none;">
        <div class="modal-overlay" id="modal-overlay-el" onclick="closeConfirmModal()"></div>
        <div class="modal-content" style="max-width: 500px;">
            <!-- Phase 1: Confirm -->
            <div id="modal-confirm-view">
                <div class="modal-icon">⚠️</div>
                <h3 class="modal-title">ยืนยันการอัปเดตระบบ</h3>
                <p class="modal-message">
                    คุณต้องการเริ่มดำเนินการดาวน์โหลดและติดตั้งตัวอัปเกรดระบบ NCDs Portal รุ่นล่าสุดใช่หรือไม่?
                </p>
                <div class="modal-warning">
                    <strong>🔒 ความมั่นใจในการอัปเดตระบบ:</strong>
                    <ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 12.5px; line-height: 1.6;">
                        <li><strong>ข้อมูลคัดกรองปลอดภัย 100%:</strong> ประวัติการบันทึกคัดกรอง ผลงาน อสม. พิกัดตำแหน่งบ้าน และข้อมูลเดิมทั้งหมดในฐานข้อมูลจะไม่สูญหายหรือเสียหายแน่นอน</li>
                        <li><strong>ทำงานได้ต่อเนื่องอย่างไร้รอยต่อ:</strong> อสม. และเจ้าหน้าที่ยังคงสแกนคัดกรอง ส่งการ์ดความห่วงใย หรือใช้งานระบบได้ปกติทันทีโดยไม่ต้องตั้งค่าใหม่</li>
                        <li><strong>ปกป้องค่าเชื่อมต่อของพื้นที่:</strong> ค่ารหัสผ่านเชื่อมต่อฐานข้อมูล และข้อมูลระบบการเชื่อมต่อสิทธิ์ส่งข้อความแจ้งเตือน (LINE API/Token) เดิมของหน่วยงานท่านจะได้รับการละเว้นและปกป้องไว้ไม่ถูกเขียนทับแน่นอน</li>
                    </ul>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-modal-cancel" onclick="closeConfirmModal()">ยกเลิก</button>
                    <button type="button" class="btn-modal-confirm" onclick="proceedWithUpdate()">🚀 เริ่มการอัปเดต</button>
                </div>
            </div>

            <!-- Phase 2: Progress -->
            <div id="modal-progress-view" style="display: none; padding: 15px 0;">
                <div class="modal-icon">⚙️</div>
                <h3 class="modal-title" style="margin-bottom: 5px;">กำลังดำเนินการอัปเดตระบบ</h3>
                <p id="progress-status" class="modal-message" style="font-size: 13.5px; margin-bottom: 25px;">
                    กำลังเตรียมระบบและเริ่มเชื่อมต่อสิทธิ์ดาวน์โหลด...
                </p>

                <!-- Progress bar container -->
                <div style="background-color: var(--border-color); border-radius: 9999px; height: 12px; width: 100%; overflow: hidden; margin-bottom: 10px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                    <div id="progress-bar-fill" style="background: linear-gradient(90deg, #ff9800 0%, #22c55e 100%); height: 100%; width: 0%; border-radius: 9999px; transition: width 0.3s ease;"></div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: bold; color: var(--text-secondary);">
                    <span>ความคืบหน้าการติดตั้ง</span>
                    <span id="progress-percent-lbl">0%</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        let isConfirmed = false;

        function confirmUpdate(e) {
            if (isConfirmed) {
                return true;
            }
            e.preventDefault();
            // Open the custom modal
            document.getElementById('confirm-modal').style.display = 'flex';
            return false;
        }

        function closeConfirmModal() {
            if (document.getElementById('modal-progress-view').style.display === 'block') {
                return; // Disallow closing during update progress
            }
            document.getElementById('confirm-modal').style.display = 'none';
        }

        function proceedWithUpdate() {
            // Switch views
            document.getElementById('modal-confirm-view').style.display = 'none';
            document.getElementById('modal-progress-view').style.display = 'block';

            // Remove click overlay close handler for safety
            document.getElementById('modal-overlay-el').onclick = null;

            const bar = document.getElementById('progress-bar-fill');
            const statusText = document.getElementById('progress-status');
            const percentText = document.getElementById('progress-percent-lbl');

            // Disable original button as fallback
            const mainBtn = document.getElementById('update-btn');
            if (mainBtn) {
                mainBtn.disabled = true;
                mainBtn.innerHTML = '⏳ กำลังอัปเดตระบบ กรุณาห้ามปิดหน้าจอนี้...';
            }

            // Simulate progression smoothly up to 92%
            let percent = 0;
            const progressTimer = setInterval(() => {
                if (percent < 92) {
                    percent += Math.floor(Math.random() * 6) + 3;
                    if (percent > 92) percent = 92;

                    bar.style.width = percent + '%';
                    percentText.innerText = percent + '%';

                    if (percent < 25) {
                        statusText.innerText = '📥 กำลังดาวน์โหลดแพ็กเกจระบบจาก Server ส่วนกลาง...';
                    } else if (percent < 55) {
                        statusText.innerText = '📦 ดาวน์โหลดเสร็จสิ้น กำลังแกะไฟล์และคัดลอกไฟล์ลงโฟลเดอร์...';
                    } else if (percent < 80) {
                        statusText.innerText = '⚙️ กำลังทำการตรวจสอบโครงสร้างฐานข้อมูลและตารางงาน...';
                    } else {
                        statusText.innerText = '🧹 ไฟล์ได้รับการอัปเดตแล้ว กำลังรีเซ็ตแคชและประมวลผล...';
                    }
                }
            }, 300);

            // Execute actual POST request via fetch API
            const formData = new FormData();
            formData.append('trigger_update', '1');

            fetch('update.php?t=' + new Date().getTime(), {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('การเชื่อมต่อฝั่งเซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง: ' + response.status);
                    }
                    return response.text();
                })
                .then(htmlResponse => {
                    clearInterval(progressTimer);

                    // Finalize to 100%
                    bar.style.width = '100%';
                    percentText.innerText = '100%';
                    statusText.innerText = '🎉 อัปเกรดระบบสำเร็จ! กำลังรีโหลดหน้าจอ...';

                    // Wait for animation to finish, then write the new HTML to document
                    setTimeout(() => {
                        document.open();
                        document.write(htmlResponse);
                        document.close();
                    }, 600);
                })
                .catch(error => {
                    clearInterval(progressTimer);
                    statusText.innerText = '❌ เกิดข้อผิดพลาดในการอัปเดต: ' + error.message;
                    statusText.style.color = '#ef4444';
                    percentText.innerText = 'ล้มเหลว';

                    // Allow closing the modal to see the error page in case of failure
                    document.getElementById('modal-overlay-el').onclick = closeConfirmModal;
                    if (mainBtn) {
                        mainBtn.disabled = false;
                        mainBtn.innerHTML = '🚀 ลองอัปเดตใหม่อีกครั้ง';
                    }
                });
        }
    </script>
</body>

</html>