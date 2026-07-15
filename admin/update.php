<?php
// admin/update.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

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

// Fetch remote changelog
try {
    $ctx = stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: PHP-AutoUpdater\r\n",
            "timeout" => 10
        ]
    ]);
    $remote_content = @file_get_contents('https://raw.githubusercontent.com/Akelliiues/ncds/main/changelog.json', false, $ctx);
    if ($remote_content) {
        $remote_changelog = json_decode($remote_content, true);
        if (!empty($remote_changelog)) {
            $remote_version = $remote_changelog[0];
            if ($remote_version['title'] !== $local_version['title'] || $remote_version['date'] !== $local_version['date']) {
                $update_available = true;
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
                    if (strpos($relative_name, 'config/db_config.php') !== false) {
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
                        @copy("zip://{$temp_zip}#{$filename}", $target_file);
                    }
                }
                $zip->close();
                @unlink($temp_zip);
                
                $success = "ระบบได้รับการอัปเกรดเป็นเวอร์ชันใหม่เรียบร้อยแล้ว!";
                $update_available = false;
                
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
            gap: 10px;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
            color: white !important;
            border: none;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
            box-shadow: var(--neumorph-flat);
            transition: all var(--transition-speed);
        }
        .btn-update:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(99, 102, 241, 0.4);
        }
        .btn-update:disabled {
            background: #cbd5e1;
            color: #94a3b8 !important;
            cursor: not-allowed;
            box-shadow: none;
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
            <div class="alert-box alert-success">🎉 <?= htmlspecialchars($success) ?></div>
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
            <form method="POST" action="update.php" onsubmit="showLoading()">
                <?php if ($update_available): ?>
                    <button type="submit" name="trigger_update" class="btn-update" id="update-btn">
                        🚀 ตรวจพบเวอร์ชันใหม่! คลิกเพื่ออัปเดตระบบทันที
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-update" disabled style="background-color: var(--border-color); color: var(--text-muted); cursor: not-allowed;">
                        ✅ ระบบของคุณเป็นรุ่นล่าสุดแล้ว ไม่ต้องอัปเดตเพิ่มเติม
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($remote_changelog)): ?>
            <div class="changelog-box">
                <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--color-accent); border-bottom: 1.5px solid var(--border-color); padding-bottom: 10px;">
                    📜 รายละเอียดการปรับปรุงรุ่นล่าสุด (Changelog)
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

    <script>
        function showLoading() {
            const btn = document.getElementById('update-btn');
            btn.disabled = true;
            btn.innerHTML = '⏳ กำลังดาวน์โหลดและคลี่ไฟล์โปรแกรม กรุณารอสักครู่...';
        }
    </script>
</body>
</html>
