<?php
// config/dev_modal.php

$last_update_str = '2 กรกฎาคม 2569';
$build_number = '2.6.0';
$system_updates = [];

// Dynamic path prefix depending on execution directory context
$path_prefix = '';
if (file_exists('assets/aboutus.png')) {
    $path_prefix = '';
} else {
    $path_prefix = '../';
}

$json_file = __DIR__ . '/../changelog.json';
if (file_exists($json_file)) {
    $json_data = json_decode(file_get_contents($json_file), true);
    if (is_array($json_data)) {
        $count = 0;
        foreach ($json_data as $item) {
            $system_updates[] = [
                'title' => $item['title'] ?? '',
                'date' => $item['date'] ?? '',
                'type' => $item['type'] ?? 'feature'
            ];
            $count++;
            if ($count >= 3) break; // Limit to top 3
        }
    }
}
?>

<style>
.dev-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(11, 15, 25, 0.75);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    box-sizing: border-box;
}
.dev-modal-overlay.show {
    opacity: 1;
}
.dev-modal-container {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 28px;
    width: 90%;
    max-width: 820px;
    max-height: 90vh;
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6);
    position: relative;
    overflow: hidden;
    transform: scale(0.9);
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-sizing: border-box;
}
.dev-modal-overlay.show .dev-modal-container {
    transform: scale(1);
}
.dev-modal-close {
    position: absolute;
    top: 18px;
    right: 22px;
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 32px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 20;
    line-height: 1;
}
.dev-modal-close:hover {
    color: #ef4444;
    transform: scale(1.1);
}
.dev-modal-body {
    display: flex;
    flex-direction: row;
    box-sizing: border-box;
}
.dev-modal-left {
    flex: 1;
    padding: 40px;
    background: rgba(13, 44, 84, 0.03);
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    justify-content: center;
    box-sizing: border-box;
}
.dev-modal-right {
    flex: 1.25;
    padding: 40px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-sizing: border-box;
}
.dev-app-logo-wrapper {
    position: relative;
    display: inline-block;
}
.dev-app-logo {
    width: 90px;
    height: 90px;
    object-fit: contain;
    border-radius: 20px;
    filter: drop-shadow(0 8px 20px rgba(13, 44, 84, 0.15));
    transition: transform 0.3s ease;
}
.dev-app-logo:hover {
    transform: scale(1.05) rotate(2deg);
}
.dev-app-title {
    font-size: 21px;
    font-weight: 800;
    margin: 18px 0 6px 0;
    color: var(--text-primary);
    line-height: 1.3;
}
.dev-app-subtitle {
    font-size: 13.5px;
    color: var(--color-accent);
    font-weight: 800;
    letter-spacing: 0.5px;
    margin: 0;
}
.dev-divider {
    width: 60px;
    height: 3px;
    background: var(--color-primary);
    margin: 20px 0;
    border-radius: 2px;
}
.dev-profile-section {
    display: flex;
    align-items: center;
    gap: 16px;
    text-align: left;
    background: var(--bg-main);
    padding: 16px;
    border-radius: 20px;
    width: 100%;
    box-sizing: border-box;
    border: 1px solid var(--border-color);
}
.dev-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border-color);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    flex-shrink: 0;
}
.dev-badge {
    background: rgba(2, 132, 199, 0.1);
    color: #0284c7;
    font-size: 10.5px;
    font-weight: 800;
    padding: 3px 9px;
    border-radius: 7px;
    display: inline-block;
    margin-bottom: 4px;
    text-transform: uppercase;
}
.dev-name {
    font-size: 15px;
    font-weight: 800;
    margin: 0;
    color: var(--text-primary);
    line-height: 1.3;
}
.dev-title {
    font-size: 12.5px;
    color: var(--text-secondary);
    margin: 2px 0 0 0;
    line-height: 1.3;
}
.dev-section-title {
    font-size: 16px;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dev-updates-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-bottom: 24px;
}
.dev-update-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.dev-update-icon {
    font-size: 16px;
    flex-shrink: 0;
    margin-top: 2px;
}
.dev-update-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.dev-update-text {
    font-size: 13.5px;
    margin: 0;
    color: var(--text-primary);
    line-height: 1.45;
    font-weight: 600;
}
.dev-update-date {
    font-size: 11px;
    color: var(--text-muted);
}
.dev-version-info {
    display: flex;
    justify-content: space-between;
    font-size: 12.5px;
    color: var(--text-secondary);
    background: var(--bg-main);
    padding: 10px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    border: 1px solid var(--border-color);
}
.dev-btn-ok {
    background: var(--color-primary);
    color: #ffffff;
    border: none;
    border-radius: 14px;
    padding: 14px;
    font-weight: 800;
    font-size: 14.5px;
    cursor: pointer;
    transition: all var(--transition-speed);
    width: 100%;
    box-shadow: 0 4px 12px rgba(13, 44, 84, 0.2);
}
.dev-btn-ok:hover {
    background: var(--color-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(13, 44, 84, 0.3);
}

/* Responsiveness */
@media (max-width: 768px) {
    .dev-modal-body {
        flex-direction: column;
    }
    .dev-modal-left {
        border-right: none;
        border-bottom: 1px solid var(--border-color);
        padding: 30px 24px;
    }
    .dev-modal-right {
        padding: 30px 24px;
    }
    .dev-modal-container {
        max-height: 95vh;
        overflow-y: auto;
    }
    .dev-divider {
        margin: 14px 0;
    }
}
</style>

<div id="dev-portal-modal" class="dev-modal-overlay" style="display: none;" onclick="closeDevModal()">
    <div class="dev-modal-container" onclick="handleContainerClick(event)">
        <button class="dev-modal-close" onclick="closeDevModal()">&times;</button>
        
        <div class="dev-modal-body">
            <!-- Left Side: Profile and App Logo -->
            <div class="dev-modal-left">
                <div class="dev-app-logo-wrapper">
                    <img src="<?= $path_prefix ?>assets/aboutus.png" alt="App Logo" class="dev-app-logo">
                </div>
                <h2 class="dev-app-title">NCDs Prevention Portal</h2>
                <p class="dev-app-subtitle">สำนักงานสาธารณสุขอำเภอตาลสุม</p>
                
                <div class="dev-divider"></div>
                
                <div class="dev-profile-section">
                    <img src="<?= $path_prefix ?>assets/developer.jpg" alt="Developer Avatar" class="dev-avatar">
                    <div class="dev-profile-info">
                        <span class="dev-badge">ผู้พัฒนาระบบ</span>
                        <h4 class="dev-name">นายบุญธรรม พันธ์ใหญ่</h4>
                        <p class="dev-title">นักวิชาการคอมพิวเตอร์ สสอ.ตาลสุม</p>
                    </div>
                </div>
            </div>
            
            <!-- Right Side: System Info & Updates -->
            <div class="dev-modal-right">
                <div>
                    <h3 class="dev-section-title">✨ บันทึกการปรับปรุงล่าสุด</h3>
                    
                    <div class="dev-updates-list">
                        <?php foreach ($system_updates as $up): 
                            $icon = '🚀';
                            if ($up['type'] === 'fix') $icon = '🔧';
                            elseif ($up['type'] === 'security') $icon = '🔒';
                        ?>
                            <div class="dev-update-item">
                                <span class="dev-update-icon"><?= $icon ?></span>
                                <div class="dev-update-content">
                                    <p class="dev-update-text"><?= htmlspecialchars($up['title']) ?></p>
                                    <span class="dev-update-date">อัปเดตเมื่อ: <?= htmlspecialchars($up['date']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div>
                    <div class="dev-version-info">
                        <span>เวอร์ชัน: <strong>2.6.0</strong></span>
                        <span>สสอ.ตาลสุม</span>
                    </div>
                    
                    <button class="dev-btn-ok" onclick="closeDevModal()">ตกลง เข้าสู่ระบบ</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById('dev-portal-modal');
    if (!modal) return;
    
    // Check local storage for daily limit
    const today = new Date().toDateString();
    const lastShown = localStorage.getItem('ncd_dev_modal_last_shown');
    
    if (lastShown !== today) {
        modal.style.display = 'flex';
        // Force reflow
        modal.offsetHeight;
        modal.classList.add('show');
        
        // Disable body scroll
        document.body.style.overflow = 'hidden';

        // สำหรับ อสม. ให้ปิดเองอัตโนมัติภายใน 6 วินาที
        const isVhv = window.location.pathname.includes('/vhv/');
        if (isVhv) {
            setTimeout(function() {
                closeDevModal();
            }, 6000);
        }
    }
});

function handleContainerClick(e) {
    const isVhv = window.location.pathname.includes('/vhv/');
    if (isVhv) {
        // อสม. แตะตรงไหนก็ได้เพื่อปิด
        closeDevModal();
    } else {
        // แอดมินสามารถคลิกอ่านข้อความโดยไม่ปิด (คลิกข้างนอกค่อยปิด)
        e.stopPropagation();
    }
}

function openDevModal(e) {
    if (e) e.preventDefault();
    const modal = document.getElementById('dev-portal-modal');
    if (!modal) return;
    modal.style.display = 'flex';
    modal.offsetHeight; // Force reflow
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDevModal() {
    const modal = document.getElementById('dev-portal-modal');
    if (!modal || !modal.classList.contains('show')) return;
    
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        
        // Save showing timestamp
        const today = new Date().toDateString();
        localStorage.setItem('ncd_dev_modal_last_shown', today);
    }, 400);
}
</script>
