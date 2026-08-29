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
$district_display = defined('DISTRICT_NAME') ? DISTRICT_NAME : 'ตาลสุม';

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
        background-color: rgba(11, 15, 25, 0.72);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        transition: opacity 0.25s ease;
        box-sizing: border-box;
        cursor: pointer;
        user-select: none;
    }

    .dev-modal-overlay.show {
        opacity: 1;
    }

    .dev-modal-container {
        background: var(--bg-card, #ffffff);
        border: 1px solid var(--border-color, rgba(226, 232, 240, 0.85));
        border-radius: 28px;
        width: 100%;
        max-width: 440px;
        box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.45);
        position: relative;
        overflow: hidden;
        transform: scale(0.94);
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        box-sizing: border-box;
        padding: 24px 20px 20px 20px;
        cursor: pointer;
        text-align: center;
    }

    .dev-modal-overlay.show .dev-modal-container {
        transform: scale(1);
    }

    .dev-app-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        margin-bottom: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
    }

    .dev-app-logo {
        width: 64px;
        height: 64px;
        object-fit: contain;
        border-radius: 18px;
        filter: drop-shadow(0 6px 14px rgba(13, 44, 84, 0.15));
        background: var(--bg-card);
        border: 1px solid var(--border-color, rgba(0,0,0,0.06));
        margin-bottom: 8px;
    }

    .dev-app-title {
        font-size: 20px;
        font-weight: 900;
        margin: 0;
        color: var(--text-primary, #0f172a);
        line-height: 1.25;
        letter-spacing: -0.02em;
    }

    .dev-app-subtitle {
        font-size: 13px;
        color: var(--color-accent, #0284c7);
        font-weight: 700;
        margin: 3px 0 0 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    .dev-profile-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        background: var(--bg-main, #f8fafc);
        padding: 16px 16px 14px 16px;
        border-radius: 20px;
        width: 100%;
        box-sizing: border-box;
        border: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
        margin-bottom: 14px;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.02);
    }

    .dev-badge {
        background: linear-gradient(135deg, rgba(2, 132, 199, 0.12), rgba(14, 165, 233, 0.12));
        color: #0284c7;
        font-size: 11px;
        font-weight: 800;
        padding: 3px 12px;
        border-radius: 20px;
        display: inline-block;
        margin-bottom: 10px;
        border: 1px solid rgba(2, 132, 199, 0.22);
        letter-spacing: 0.02em;
    }

    .dev-avatar {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--color-primary, #0284c7);
        box-shadow: 0 6px 16px rgba(2, 132, 199, 0.25);
        margin-bottom: 8px;
    }

    .dev-name {
        font-size: 17px;
        font-weight: 900;
        margin: 0;
        color: var(--text-primary, #0f172a);
        line-height: 1.3;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .verified-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        background: var(--bg-card, #ffffff);
        border-radius: 50%;
        box-shadow: 1px 1px 4px rgba(0, 0, 0, 0.1);
        padding: 1px;
        box-sizing: border-box;
        flex-shrink: 0;
    }

    .verified-badge svg {
        width: 100%;
        height: 100%;
        display: block;
    }

    [data-theme="dark"] .verified-badge {
        background: #1e293b;
        box-shadow: 1px 1px 4px rgba(0, 0, 0, 0.5);
    }

    [data-theme="dark"] .verified-badge svg circle,
    [data-theme="dark"] .verified-badge svg path {
        stroke: #84cc16;
    }

    .dev-title {
        font-size: 13px;
        color: var(--text-secondary, #64748b);
        margin: 3px 0 0 0;
        line-height: 1.35;
        font-weight: 600;
        word-break: keep-all;
    }

    .dev-title-sub {
        font-size: 12px;
        color: var(--text-muted, #94a3b8);
        margin: 2px 0 0 0;
        line-height: 1.35;
        font-weight: 500;
        word-break: keep-all;
    }

    /* System Feature Highlights */
    .dev-features-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-bottom: 14px;
    }

    .dev-feature-item {
        background: var(--bg-main, #f8fafc);
        border: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
        padding: 8px 6px;
        border-radius: 14px;
        text-align: center;
    }

    .dev-feature-icon {
        font-size: 16px;
        margin-bottom: 2px;
    }

    .dev-feature-label {
        font-size: 11px;
        font-weight: 800;
        color: var(--text-primary, #0f172a);
        white-space: nowrap;
    }

    .dev-feature-sub {
        font-size: 10px;
        color: var(--text-muted, #94a3b8);
        margin-top: 1px;
        white-space: nowrap;
    }

    .dev-footer-info {
        display: flex;
        align-items: center;
        justify-content: center;
        padding-top: 6px;
    }

    .dev-version-tag {
        font-weight: 800;
        color: var(--text-primary, #0f172a);
        background: var(--bg-main, rgba(0, 0, 0, 0.04));
        border: 1px solid var(--border-color, rgba(0, 0, 0, 0.06));
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11.5px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
</style>

<div id="dev-portal-modal" class="dev-modal-overlay" style="display: none;" onclick="closeDevModal()">
    <div class="dev-modal-container" onclick="closeDevModal()">

        <!-- Developer & App Info Header -->
        <div class="dev-app-header">
            <img src="<?= $path_prefix ?>assets/aboutus.png" alt="App Logo" class="dev-app-logo">
            <h2 class="dev-app-title">NCDs Portal</h2>
            <p class="dev-app-subtitle">อำเภอ<?= htmlspecialchars($district_display) ?> • อุบลราชธานี</p>
        </div>

        <!-- Developer Profile Card -->
        <div class="dev-profile-section">
            <span class="dev-badge">Solo Developer & System Architect</span>
            <img src="<?= $path_prefix ?>assets/developer.jpg" alt="Developer Avatar" class="dev-avatar">
            
            <h4 class="dev-name">
                นายบุญธรรม พันธ์ใหญ่
                <span class="verified-badge" title="ผู้พัฒนาระบบที่ได้รับการรับรอง (Verified Developer)">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="9" stroke="#72cd27" stroke-width="2.3" stroke-linecap="round" stroke-dasharray="41 5 4 5" transform="rotate(-30 12 12)" />
                        <path d="M8.5 12.2l2.3 2.3 5.2-5.5" stroke="#72cd27" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </h4>
            <p class="dev-title">นักวิชาการคอมพิวเตอร์ปฏิบัติการ</p>
            <p class="dev-title-sub">สำนักงานสาธารณสุขอำเภอ<?= htmlspecialchars($district_display) ?></p>
        </div>

        <!-- System Architecture & Capabilities Grid -->
        <div class="dev-features-grid">
            <div class="dev-feature-item">
                <div class="dev-feature-icon">📡</div>
                <div class="dev-feature-label">Realtime</div>
                <div class="dev-feature-sub">เฝ้าระวังความเสี่ยง</div>
            </div>
            <div class="dev-feature-item">
                <div class="dev-feature-icon">🛡️</div>
                <div class="dev-feature-label">PDPA</div>
                <div class="dev-feature-sub">คุ้มครองข้อมูล</div>
            </div>
            <div class="dev-feature-item">
                <div class="dev-feature-icon">🏥</div>
                <div class="dev-feature-label">JHCIS Sync</div>
                <div class="dev-feature-sub">เชื่อมระบบงานปฐมภูมิ</div>
            </div>
        </div>

        <!-- Footer System Version & Build Info -->
        <div class="dev-footer-info">
            <span class="dev-version-tag">
                <span style="color: #10B981;">●</span> v3.0 (Build <?= htmlspecialchars($build_number) ?>)
            </span>
        </div>
    </div>
</div>

<script>
    let devModalAutoCloseTimer = null;

    document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById('dev-portal-modal');
        if (!modal) return;

        // Check local storage for daily limit
        const today = new Date().toDateString();
        const lastShown = localStorage.getItem('ncd_dev_modal_last_shown_v2');

        if (lastShown !== today) {
            openDevModal();
        }
    });

    function openDevModal(e) {
        if (e && e.stopPropagation) e.stopPropagation();
        if (e && e.preventDefault) e.preventDefault();
        const modal = document.getElementById('dev-portal-modal');
        if (!modal) return;

        modal.style.display = 'flex';
        modal.offsetHeight; // Force reflow
        modal.classList.add('show');

        // Add global click/touch dismissal listener on window so any touch closes it
        setTimeout(() => {
            window.addEventListener('click', handleGlobalDevModalClose, { capture: true, once: true });
            window.addEventListener('touchstart', handleGlobalDevModalClose, { capture: true, once: true });
        }, 80);

        // Clear existing auto-close timer if any
        if (devModalAutoCloseTimer) {
            clearTimeout(devModalAutoCloseTimer);
        }

        // Auto-close after 8 seconds
        devModalAutoCloseTimer = setTimeout(function() {
            closeDevModal();
        }, 8000);
    }

    function handleGlobalDevModalClose(e) {
        closeDevModal();
    }

    function closeDevModal() {
        const modal = document.getElementById('dev-portal-modal');
        if (!modal || !modal.classList.contains('show')) return;

        window.removeEventListener('click', handleGlobalDevModalClose, { capture: true });
        window.removeEventListener('touchstart', handleGlobalDevModalClose, { capture: true });

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
        }, 200);
    }

    // Support keyboard ESC to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDevModal();
        }
    });
</script>