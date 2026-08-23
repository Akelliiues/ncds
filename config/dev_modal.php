<?php
// config/dev_modal.php
date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$is_vhv_context = isset($_SESSION['vhv_id'])
    || (isset($_SERVER['SCRIPT_NAME']) && strpos(strtolower($_SERVER['SCRIPT_NAME']), 'vhv') !== false)
    || (isset($_SERVER['PHP_SELF']) && strpos(strtolower($_SERVER['PHP_SELF']), 'vhv') !== false)
    || (isset($_SERVER['REQUEST_URI']) && strpos(strtolower($_SERVER['REQUEST_URI']), 'vhv') !== false);

function get_system_last_update_modal()
{
    $last_update = null;
    if (function_exists('shell_exec')) {
        $git_time = @shell_exec('git log -1 --format=%ct 2>/dev/null');
        if ($git_time) {
            $last_update = intval(trim($git_time));
        }
    }
    if (!$last_update) {
        $max_time = 0;
        $paths = [
            '*.php',
            'admin/*.php',
            'vhv/*.php',
            'api/*.php',
            'assets/css/*.css',
            'assets/js/*.js'
        ];
        foreach ($paths as $path) {
            $files = glob(__DIR__ . '/../' . $path);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        $mtime = filemtime($file);
                        if ($mtime > $max_time) {
                            $max_time = $mtime;
                        }
                    }
                }
            }
        }
        $last_update = $max_time ? $max_time : filemtime(__FILE__);
    }
    return $last_update;
}

function get_system_build_number_modal($last_update_ts)
{
    $commit_count = null;
    if (function_exists('shell_exec')) {
        $count = @shell_exec('git rev-list --count HEAD 2>/dev/null');
        if ($count !== null && trim($count) !== '') {
            $commit_count = trim($count);
        }
    }
    $time_code = date('ymd.Hi', $last_update_ts);
    if ($commit_count) {
        return "{$commit_count}.{$time_code}";
    }
    return $time_code;
}

$last_update_ts = get_system_last_update_modal();
$build_number = get_system_build_number_modal($last_update_ts);

// Dynamic path prefix depending on execution directory context
$path_prefix = '';
if (file_exists('assets/aboutus.png')) {
    $path_prefix = '';
} else {
    $path_prefix = '../';
}
?>

<style>
    .dev-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: rgba(11, 15, 25, 0.65);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        opacity: 0;
        transition: opacity 0.2s ease;
        box-sizing: border-box;
        cursor: pointer;
    }

    .dev-modal-overlay.show {
        opacity: 1;
    }

    .dev-modal-container {
        background: var(--bg-card, #ffffff);
        border: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
        border-radius: 24px;
        width: 100%;
        max-width: 420px;
        box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.4);
        position: relative;
        overflow: hidden;
        transform: scale(0.92);
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        box-sizing: border-box;
        padding: 24px 20px 20px 20px;
        cursor: pointer;
        text-align: center;
    }

    .dev-modal-overlay.show .dev-modal-container {
        transform: scale(1);
    }

    .dev-modal-close {
        position: absolute;
        top: 14px;
        right: 16px;
        background: none;
        border: none;
        color: var(--text-muted, #94a3b8);
        font-size: 26px;
        font-weight: bold;
        cursor: pointer;
        transition: color 0.15s ease;
        line-height: 1;
        z-index: 10;
    }

    .dev-modal-close:hover {
        color: #ef4444;
    }

    .dev-app-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
    }

    .dev-app-logo {
        width: 68px;
        height: 68px;
        object-fit: contain;
        border-radius: 16px;
        filter: drop-shadow(0 6px 14px rgba(13, 44, 84, 0.15));
    }

    .dev-app-title {
        font-size: 18px;
        font-weight: 800;
        margin: 0;
        color: var(--text-primary, #0f172a);
        line-height: 1.3;
    }

    .dev-app-subtitle {
        font-size: 12.5px;
        color: var(--color-accent, #0284c7);
        font-weight: 700;
        margin: 0;
    }

    .dev-divider {
        width: 48px;
        height: 3px;
        background: var(--color-primary, #0284c7);
        margin: 14px auto;
        border-radius: 2px;
        opacity: 0.8;
    }

    .dev-profile-section {
        display: flex;
        align-items: center;
        gap: 14px;
        text-align: left;
        background: var(--bg-main, #f8fafc);
        padding: 14px 16px;
        border-radius: 18px;
        width: 100%;
        box-sizing: border-box;
        border: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
        margin-bottom: 16px;
    }

    .dev-avatar {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--color-primary, #0284c7);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        flex-shrink: 0;
    }

    .dev-profile-info {
        display: flex;
        flex-direction: column;
    }

    .dev-badge {
        background: rgba(2, 132, 199, 0.12);
        color: #0284c7;
        font-size: 10px;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 6px;
        display: inline-block;
        margin-bottom: 3px;
        width: fit-content;
    }

    .dev-name {
        font-size: 14.5px;
        font-weight: 800;
        margin: 0;
        color: var(--text-primary, #0f172a);
        line-height: 1.3;
    }

    .dev-title {
        font-size: 12px;
        color: var(--text-secondary, #64748b);
        margin: 2px 0 0 0;
        line-height: 1.3;
    }

    .dev-footer-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        font-size: 11.5px;
        color: var(--text-muted, #94a3b8);
    }

    .dev-version-tag {
        font-weight: 700;
        color: var(--text-secondary, #64748b);
        background: rgba(0, 0, 0, 0.04);
        padding: 3px 10px;
        border-radius: 10px;
    }

    .dev-dismiss-hint {
        font-size: 11px;
        color: #0284c7;
        font-weight: 600;
        animation: pulseHint 2s infinite ease-in-out;
    }

    @keyframes pulseHint {
        0%, 100% { opacity: 0.7; }
        50% { opacity: 1; }
    }
</style>

<div id="dev-portal-modal" class="dev-modal-overlay" style="display: none;" onclick="closeDevModal()">
    <div class="dev-modal-container" onclick="closeDevModal()">
        <button class="dev-modal-close" onclick="closeDevModal()">&times;</button>

        <!-- Compact Developer Info Card -->
        <div class="dev-app-header">
            <img src="<?= $path_prefix ?>assets/aboutus.png" alt="App Logo" class="dev-app-logo">
            <h2 class="dev-app-title">NCDs Portal</h2>
            <p class="dev-app-subtitle">สำนักงานสาธารณสุขอำเภอ<?= DISTRICT_NAME ?></p>
        </div>

        <div class="dev-divider"></div>

        <div class="dev-profile-section">
            <img src="<?= $path_prefix ?>assets/developer.jpg" alt="Developer Avatar" class="dev-avatar">
            <div class="dev-profile-info">
                <span class="dev-badge">ผู้พัฒนาระบบ</span>
                <h4 class="dev-name">นายบุญธรรม พันธ์ใหญ่</h4>
                <p class="dev-title">นักวิชาการคอมพิวเตอร์ </p>
                <p class="dev-title">สำนักงานสาธารณสุขอำเภอตาลสุม</p>
                <p class="dev-title">จังหวัดอุบลราชธานี</p>
            </div>
        </div>

        <div class="dev-footer-info">
            <span class="dev-version-tag">v2.6.0 (Build <?= htmlspecialchars($build_number) ?>)</span>
            <span class="dev-dismiss-hint">⏱️ หายไปเองใน 5 วินาที (หรือแตะที่ใดก็ได้เพื่อปิด)</span>
        </div>
    </div>
</div>

<script>
    let devModalAutoCloseTimer = null;

    document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById('dev-portal-modal');
        if (!modal) return;

        // Check local storage for daily limit (or new version key)
        const today = new Date().toDateString();
        const lastShown = localStorage.getItem('ncd_dev_modal_last_shown_v2');

        if (lastShown !== today) {
            openDevModal();
        }
    });

    function openDevModal(e) {
        if (e) e.preventDefault();
        const modal = document.getElementById('dev-portal-modal');
        if (!modal) return;

        modal.style.display = 'flex';
        modal.offsetHeight; // Force reflow
        modal.classList.add('show');

        // Clear existing auto-close timer if any
        if (devModalAutoCloseTimer) {
            clearTimeout(devModalAutoCloseTimer);
        }

        // Auto-close after 5 seconds
        devModalAutoCloseTimer = setTimeout(function() {
            closeDevModal();
        }, 5000);
    }

    function closeDevModal() {
        const modal = document.getElementById('dev-portal-modal');
        if (!modal || !modal.classList.contains('show')) return;

        if (devModalAutoCloseTimer) {
            clearTimeout(devModalAutoCloseTimer);
            devModalAutoCloseTimer = null;
        }

        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';

            // Save showing timestamp so it doesn't pop up again today unless clicked manually
            const today = new Date().toDateString();
            localStorage.setItem('ncd_dev_modal_last_shown_v2', today);
        }, 180);
    }
</script>