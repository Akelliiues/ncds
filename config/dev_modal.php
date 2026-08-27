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
        padding: 24px;
        opacity: 0;
        transition: opacity 0.25s ease;
        box-sizing: border-box;
        cursor: pointer;
    }

    .dev-modal-overlay.show {
        opacity: 1;
    }

    .dev-modal-container {
        background: var(--bg-card, #ffffff);
        border: 1px solid var(--border-color, rgba(226, 232, 240, 0.85));
        border-radius: 28px;
        width: 100%;
        max-width: 640px;
        box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.45);
        position: relative;
        overflow: hidden;
        transform: scale(0.94);
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        box-sizing: border-box;
        padding: 32px 32px 28px 32px;
        cursor: default;
        text-align: center;
    }

    .dev-modal-overlay.show .dev-modal-container {
        transform: scale(1);
    }

    .dev-modal-close {
        position: absolute;
        top: 18px;
        right: 20px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--bg-main, rgba(0, 0, 0, 0.05));
        border: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
        color: var(--text-muted, #94a3b8);
        font-size: 20px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        z-index: 10;
    }

    .dev-modal-close:hover {
        background: #ef4444;
        color: #ffffff;
        border-color: #ef4444;
        transform: rotate(90deg);
    }

    .dev-app-header {
        display: flex;
        align-items: center;
        gap: 18px;
        text-align: left;
        margin-bottom: 20px;
        padding-bottom: 18px;
        border-bottom: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
    }

    .dev-app-logo {
        width: 78px;
        height: 78px;
        object-fit: contain;
        border-radius: 20px;
        filter: drop-shadow(0 6px 16px rgba(13, 44, 84, 0.18));
        flex-shrink: 0;
        background: var(--bg-card);
        border: 1px solid var(--border-color, rgba(0,0,0,0.06));
    }

    .dev-app-title {
        font-size: 21px;
        font-weight: 900;
        margin: 0;
        color: var(--text-primary, #0f172a);
        line-height: 1.25;
    }

    .dev-app-subtitle {
        font-size: 13.5px;
        color: var(--color-accent, #0284c7);
        font-weight: 700;
        margin: 4px 0 0 0;
    }

    .dev-profile-section {
        display: flex;
        align-items: center;
        gap: 18px;
        text-align: left;
        background: var(--bg-main, #f8fafc);
        padding: 18px 20px;
        border-radius: 20px;
        width: 100%;
        box-sizing: border-box;
        border: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
        margin-bottom: 18px;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.03);
    }

    .dev-avatar {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--color-primary, #0284c7);
        box-shadow: 0 4px 14px rgba(2, 132, 199, 0.25);
        flex-shrink: 0;
    }

    .dev-profile-info {
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .dev-badge {
        background: linear-gradient(135deg, rgba(2, 132, 199, 0.15), rgba(14, 165, 233, 0.15));
        color: #0284c7;
        font-size: 11px;
        font-weight: 800;
        padding: 3px 10px;
        border-radius: 8px;
        display: inline-block;
        margin-bottom: 4px;
        width: fit-content;
        border: 1px solid rgba(2, 132, 199, 0.25);
    }

    .dev-name {
        font-size: 16.5px;
        font-weight: 900;
        margin: 0;
        color: var(--text-primary, #0f172a);
        line-height: 1.3;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .verified-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        background: var(--bg-card, #ffffff);
        border-radius: 50%;
        box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.08), -2px -2px 5px rgba(255, 255, 255, 0.9);
        cursor: help;
        padding: 2px;
        box-sizing: border-box;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        flex-shrink: 0;
    }

    .verified-badge:hover {
        transform: scale(1.2);
        box-shadow: 0 0 10px rgba(114, 205, 39, 0.45);
    }

    .verified-badge svg {
        width: 100%;
        height: 100%;
        display: block;
    }

    [data-theme="dark"] .verified-badge {
        background: #1e293b;
        box-shadow: 2px 2px 6px rgba(0, 0, 0, 0.5), -1px -1px 3px rgba(255, 255, 255, 0.05);
    }

    [data-theme="dark"] .verified-badge svg circle,
    [data-theme="dark"] .verified-badge svg path {
        stroke: #84cc16;
    }

    .dev-title {
        font-size: 13px;
        color: var(--text-secondary, #64748b);
        margin: 2px 0 0 0;
        line-height: 1.4;
        font-weight: 600;
    }

    /* System Feature Highlights for PC Screen */
    .dev-features-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-bottom: 18px;
    }

    .dev-feature-item {
        background: var(--bg-main, #f8fafc);
        border: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
        padding: 10px 12px;
        border-radius: 14px;
        text-align: center;
    }

    .dev-feature-icon {
        font-size: 16px;
        margin-bottom: 3px;
    }

    .dev-feature-label {
        font-size: 11.5px;
        font-weight: 800;
        color: var(--text-primary, #0f172a);
    }

    .dev-feature-sub {
        font-size: 10.5px;
        color: var(--text-muted, #94a3b8);
        margin-top: 1px;
    }

    .dev-footer-info {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        font-size: 12px;
        color: var(--text-muted, #94a3b8);
        padding-top: 12px;
        border-top: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
        flex-wrap: wrap;
    }

    .dev-version-tag {
        font-weight: 800;
        color: var(--text-primary, #0f172a);
        background: var(--bg-main, rgba(0, 0, 0, 0.05));
        border: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
        padding: 5px 12px;
        border-radius: 10px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .dev-dismiss-hint {
        font-size: 11.5px;
        color: var(--text-muted, #94a3b8);
        font-weight: 600;
    }
</style>

<div id="dev-portal-modal" class="dev-modal-overlay" style="display: none;" onclick="closeDevModal()">
    <div class="dev-modal-container" onclick="event.stopPropagation()">
        <button type="button" class="dev-modal-close" onclick="closeDevModal()" title="ปิดหน้าต่าง">&times;</button>

        <!-- Developer & App Info Header (Wide PC Layout) -->
        <div class="dev-app-header">
            <img src="<?= $path_prefix ?>assets/aboutus.png" alt="App Logo" class="dev-app-logo">
            <div>
                <h2 class="dev-app-title">NCDs Prevention & Dispatcher Portal</h2>
                <p class="dev-app-subtitle">สำนักงานสาธารณสุขอำเภอ<?= htmlspecialchars($district_display) ?> • จังหวัดอุบลราชธานี</p>
            </div>
        </div>

        <!-- Developer Profile Card -->
        <div class="dev-profile-section">
            <img src="<?= $path_prefix ?>assets/developer.jpg" alt="Developer Avatar" class="dev-avatar">
            <div class="dev-profile-info">
                <span class="dev-badge">Solo Developer & System Architect • ผู้พัฒนาระบบ</span>
                <h4 class="dev-name">นายบุญธรรม พันธ์ใหญ่ <span class="verified-badge" title="ผู้พัฒนาระบบที่ได้รับการรับรอง (Verified Developer)">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="9" stroke="#72cd27" stroke-width="2.3" stroke-linecap="round" stroke-dasharray="41 5 4 5" transform="rotate(-30 12 12)" />
                        <path d="M8.5 12.2l2.3 2.3 5.2-5.5" stroke="#72cd27" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span></h4>
                <p class="dev-title">นักวิชาการคอมพิวเตอร์ปฏิบัติการ</p>
                <p class="dev-title">สำนักงานสาธารณสุขอำเภอ<?= htmlspecialchars($district_display) ?> จังหวัดอุบลราชธานี</p>
            </div>
        </div>

        <!-- System Architecture & Capabilities Grid (PC Spec) -->
        <div class="dev-features-grid">
            <div class="dev-feature-item">
                <div class="dev-feature-icon">📡</div>
                <div class="dev-feature-label">Realtime Dispatch</div>
                <div class="dev-feature-sub">เฝ้าระวังเคสวิกฤตสด 24 ชม.</div>
            </div>
            <div class="dev-feature-item">
                <div class="dev-feature-icon">🛡️</div>
                <div class="dev-feature-label">PDPA Compliant</div>
                <div class="dev-feature-sub">คุ้มครองข้อมูลสุขภาพระดับสูง</div>
            </div>
            <div class="dev-feature-item">
                <div class="dev-feature-icon">🏥</div>
                <div class="dev-feature-label">JHCIS / HIS Sync</div>
                <div class="dev-feature-sub">ส่งต่อข้อมูลสู่ รพ. รวดเร็ว</div>
            </div>
        </div>

        <!-- Footer System Version & Build Info -->
        <div class="dev-footer-info">
            <span class="dev-version-tag">
                <span style="color: #10B981;">●</span> v2.95 (Stable Build <?= htmlspecialchars($build_number) ?>)
            </span>
            <span class="dev-dismiss-hint">คลิกด้านนอก หรือกดปุ่ม ✕ เพื่อปิด</span>
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

        // Auto-close after 8 seconds (longer on PC to comfortably read)
        devModalAutoCloseTimer = setTimeout(function() {
            closeDevModal();
        }, 8000);
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
        }, 200);
    }
</script>