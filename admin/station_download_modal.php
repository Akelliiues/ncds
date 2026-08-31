<?php
$stationSetupPath = __DIR__ . DIRECTORY_SEPARATOR . 'NCDs_RedAlert_Station_Setup.exe';
$stationSetupAvailable = is_file($stationSetupPath);
$stationSetupSize = $stationSetupAvailable ? filesize($stationSetupPath) : 0;
$stationSetupSha256 = $stationSetupAvailable ? hash_file('sha256', $stationSetupPath) : '';
?>
<style>
    .station-download-modal[hidden] { display: none !important; }
    .station-download-modal { 
        position: fixed; 
        inset: 0; 
        z-index: 100000; 
        display: grid; 
        place-items: center; 
        padding: 24px; 
        background: rgba(15, 23, 42, 0.65); 
        backdrop-filter: blur(8px); 
        animation: fadeIn 0.2s ease-out;
    }
    .station-download-dialog { 
        width: min(840px, 96vw); 
        border: 0; 
        border-radius: 28px; 
        padding: 32px 36px; 
        color: #1e293b; 
        background: #edf3f9; 
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.4), 0 0 0 1px rgba(255,255,255,0.7); 
        font-family: "Prompt", "Sarabun", "Leelawadee UI", sans-serif; 
        max-height: 92vh;
        overflow-y: auto;
    }
    .station-download-head { 
        display: flex; 
        align-items: flex-start; 
        justify-content: space-between; 
        gap: 20px; 
        padding-bottom: 20px;
        border-bottom: 1.5px solid rgba(203, 213, 225, 0.7);
    }
    .station-download-title { 
        margin: 0; 
        font-size: 1.55rem; 
        font-weight: 900; 
        line-height: 1.3; 
        color: #0f172a;
    }
    .station-download-subtitle { 
        margin: 6px 0 0; 
        color: #475569; 
        font-size: 1rem; 
        line-height: 1.5; 
    }
    .station-download-close { 
        flex: 0 0 44px; 
        width: 44px; 
        height: 44px; 
        border: 0; 
        border-radius: 14px; 
        color: #64748b; 
        background: #edf3f9; 
        box-shadow: 5px 5px 12px #cbd6e1, -5px -5px 12px #fff; 
        cursor: pointer; 
        font-size: 1.5rem; 
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .station-download-close:hover {
        color: #0f172a;
        box-shadow: inset 2px 2px 5px #cbd6e1, inset -2px -2px 5px #fff;
    }
    .station-profiles-grid {
        margin: 22px 0 18px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .profile-card {
        background: #e6eef7;
        border-radius: 20px;
        padding: 20px 22px;
        box-shadow: inset 2.5px 2.5px 6px #cbd6e1, inset -2.5px -2.5px 6px #ffffff;
        border: 1.5px solid rgba(255,255,255,0.8);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .profile-card.hub {
        border-top: 4px solid #2563eb;
    }
    .profile-card.subcenter {
        border-top: 4px solid #059669;
    }
    .profile-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 900;
        font-size: 14.5px;
        margin-bottom: 10px;
    }
    .profile-badge.hub { color: #1d4ed8; }
    .profile-badge.subcenter { color: #047857; }
    .profile-desc {
        font-size: 13px;
        color: #334155;
        line-height: 1.55;
    }
    .station-download-info { 
        margin: 18px 0; 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 12px; 
    }
    .station-download-item { 
        min-width: 0; 
        padding: 14px 18px; 
        border-radius: 18px; 
        background: #e6eef7; 
        box-shadow: inset 2px 2px 5px #cbd6e1, inset -2px -2px 5px #fff; 
    }
    .station-download-item small { 
        display: block; 
        color: #64748b; 
        margin-bottom: 4px; 
        font-weight: 800; 
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .station-download-item strong { 
        display: block; 
        font-size: 14px;
        color: #0f172a;
        overflow: hidden; 
        text-overflow: ellipsis; 
        white-space: nowrap; 
    }
    .station-download-sha-box { 
        margin-bottom: 18px; 
        padding: 16px 20px; 
        border-radius: 20px; 
        background: #e6eef7; 
        box-shadow: inset 2.5px 2.5px 6px #cbd6e1, inset -2.5px -2.5px 6px #fff; 
    }
    .station-download-sha-head { 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        margin-bottom: 8px; 
    }
    .btn-copy-sha { 
        background: #edf3f9; 
        border: 1px solid #cbd6e1; 
        border-radius: 10px; 
        padding: 5px 12px; 
        font-size: 12px; 
        font-weight: 800; 
        color: #2563eb; 
        cursor: pointer; 
        display: inline-flex; 
        align-items: center; 
        gap: 5px; 
        box-shadow: 2px 2px 5px #cbd6e1, -2px -2px 5px #fff; 
        transition: all 0.2s;
    }
    .btn-copy-sha:hover { 
        background: #2563eb; 
        color: #fff; 
        border-color: #2563eb; 
    }
    .station-download-sha-code { 
        display: block; 
        font-family: "JetBrains Mono", "Consolas", monospace; 
        font-size: 12.5px; 
        font-weight: 600;
        color: #0f172a; 
        word-break: break-all; 
        background: #ffffff; 
        padding: 10px 14px; 
        border-radius: 12px; 
        border: 1px solid rgba(203, 213, 225, 0.8); 
        user-select: all; 
        line-height: 1.45; 
        box-shadow: inset 1px 1px 3px rgba(0,0,0,0.05);
    }
    .station-download-note { 
        margin: 0 0 24px; 
        padding: 14px 18px; 
        border-radius: 18px; 
        color: #854d0e; 
        background: #fef9c3; 
        border: 1px solid #fef08a;
        line-height: 1.55; 
        font-size: 0.95rem; 
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .station-download-error { color: #991b1b; background: #fee2e2; border-color: #fecaca; }
    .station-download-actions { 
        display: flex; 
        align-items: center; 
        justify-content: flex-end; 
        gap: 14px; 
    }
    .station-download-action { 
        min-height: 52px; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        border: 0; 
        border-radius: 18px; 
        padding: 12px 28px; 
        font: inherit; 
        font-size: 15px;
        font-weight: 800; 
        text-decoration: none; 
        cursor: pointer; 
        color: #334155; 
        background: #edf3f9; 
        box-shadow: 6px 6px 14px #cbd6e1, -6px -6px 14px #fff; 
        transition: all 0.2s;
    }
    .station-download-action:hover {
        box-shadow: 3px 3px 8px #cbd6e1, -3px -3px 8px #fff;
    }
    .station-download-action.primary { 
        color: #fff; 
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); 
        box-shadow: 6px 6px 16px rgba(37, 99, 235, 0.35), -4px -4px 12px #fff; 
    }
    .station-download-action.primary:hover {
        background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    }
    .station-download-action.disabled { opacity: .5; pointer-events: none; }
    @media (max-width: 768px) { 
        .station-profiles-grid { grid-template-columns: 1fr; }
        .station-download-info { grid-template-columns: 1fr; }
        .station-download-actions { flex-direction: column-reverse; }
        .station-download-action { width: 100%; }
        .station-download-dialog { padding: 22px; }
    }
</style>

<div id="station-download-modal" class="station-download-modal" role="dialog" aria-modal="true" aria-labelledby="station-download-title" hidden>
    <section class="station-download-dialog">
        <div class="station-download-head">
            <div>
                <h2 id="station-download-title" class="station-download-title">
                    🚨 ดาวน์โหลด NCDs Red Alert Station
                </h2>
                <p class="station-download-subtitle">โปรแกรมตัวรับสัญญาณแจ้งเหตุวิกฤตฉุกเฉินบนคอมพิวเตอร์ Windows (Official Release)</p>
            </div>
            <button type="button" class="station-download-close" onclick="closeStationDownloadModal()" aria-label="ปิด">×</button>
        </div>

        <!-- 2 Clear Deployment Profiles Cards -->
        <div class="station-profiles-grid">
            <!-- Profile 1: Main Dispatch Hub -->
            <div class="profile-card hub">
                <div>
                    <div class="profile-badge hub">
                        <span style="font-size: 20px;">🏢</span>
                        <span>1. สถานีหลัก แม่ข่าย (Hub Dispatcher)</span>
                    </div>
                    <div class="profile-desc">
                        <div style="font-weight: 800; color: #0f172a; margin-bottom: 4px;">สำหรับ: สสอ.ตาลสุม และ รพ.ตาลสุม</div>
                        <div>• <strong>การตั้งค่าสังกัด:</strong> เลือก <code>ALL - ศูนย์กลาง</code> หรือ <code>00325/10957</code></div>
                        <div>• <strong>สิทธิ์การทำงาน:</strong> ดักจับสัญญาณเตือนภัยเคสวิกฤต Fast-Track ภาพรวมทุก รพ.สต. ทั้งอำเภอแบบ Real-time</div>
                    </div>
                </div>
                <div style="margin-top: 14px; padding-top: 10px; border-top: 1px dashed rgba(203, 213, 225, 0.8); font-size: 11.5px; color: #1e40af; font-weight: 700;">
                    ✓ รับแจ้งเตือนทั้งอำเภอ • มอนิเตอร์ Fast-Track ภาพรวม
                </div>
            </div>

            <!-- Profile 2: Health Center -->
            <div class="profile-card subcenter">
                <div>
                    <div class="profile-badge subcenter">
                        <span style="font-size: 20px;">🏥</span>
                        <span>2. รพ.สต. ในสังกัด (Sub-district Health Center)</span>
                    </div>
                    <div class="profile-desc">
                        <div style="font-weight: 800; color: #0f172a; margin-bottom: 4px;">สำหรับ: เจ้าหน้าที่ประจำ รพ.สต. 7 แห่งในอำเภอ</div>
                        <div>• <strong>การตั้งค่าสังกัด:</strong> เลือกรหัส รพ.สต. ตนเอง (<code>03751 - 03757</code>)</div>
                        <div>• <strong>สิทธิ์การทำงาน:</strong> รับแจ้งเตือนเฉพาะเคสในตำบลตนเอง พร้อมปุ่มสั่งส่งต่อที่ซิงค์เข้าฐานข้อมูล JHCIS</div>
                    </div>
                </div>
                <div style="margin-top: 14px; padding-top: 10px; border-top: 1px dashed rgba(203, 213, 225, 0.8); font-size: 11.5px; color: #047857; font-weight: 700;">
                    ✓ รับเตือนเฉพาะตำบล • ซิงค์ visitrefer ลง JHCIS อัตโนมัติ
                </div>
            </div>
        </div>

        <!-- Meta Info Grid -->
        <div class="station-download-info">
            <div class="station-download-item">
                <small>เวอร์ชันโปรแกรม</small>
                <strong>Version 1.0 (Build 202608312200)</strong>
            </div>
            <div class="station-download-item">
                <small>ขนาดไฟล์ติดตั้ง (Setup Size)</small>
                <strong><?= $stationSetupAvailable ? number_format($stationSetupSize / 1024, 0) . ' KB' : 'ไม่พบไฟล์' ?></strong>
            </div>
            <div class="station-download-item">
                <small>ระบบปฏิบัติการที่รองรับ</small>
                <strong>Windows 10 / 11 (64-bit)</strong>
            </div>
        </div>

        <!-- SHA-256 Checksum Card -->
        <?php if ($stationSetupAvailable && !empty($stationSetupSha256)): ?>
            <div class="station-download-sha-box">
                <div class="station-download-sha-head">
                    <span style="color: #334155; font-weight: 800; font-size: 12.5px; display: flex; align-items: center; gap: 6px;">
                        <span>🔐</span> <span>SHA-256 Checksum (สำหรับตรวจสอบความถูกต้องของไฟล์):</span>
                    </span>
                    <button type="button" class="btn-copy-sha" onclick="copyStationSha256('<?= htmlspecialchars($stationSetupSha256) ?>')" id="btn-copy-sha-status">
                        📋 คัดลอก Checksum
                    </button>
                </div>
                <code class="station-download-sha-code" id="station-sha256-text"><?= htmlspecialchars($stationSetupSha256) ?></code>
            </div>
        <?php endif; ?>

        <?php if ($stationSetupAvailable): ?>
            <div class="station-download-note">
                <span style="font-size: 20px;">💡</span>
                <span><strong>คำแนะนำ:</strong> ดาวน์โหลดไฟล์ติดตั้งเดียวกันนี้เพื่อติดตั้งบนเครื่องคอมพิวเตอร์ จากนั้นเปิดเมนู <strong>Settings</strong> เพื่อเลือกสังกัดตามหน่วยบริการของท่านได้ทันที</span>
            </div>
        <?php else: ?>
            <div class="station-download-note station-download-error">
                <span style="font-size: 20px;">⚠️</span>
                <span>ไม่พบไฟล์ตัวติดตั้งบนเซิร์ฟเวอร์ กรุณาติดต่อผู้ดูแลระบบเพื่ออัปเดตไฟล์</span>
            </div>
        <?php endif; ?>

        <!-- Footer Actions -->
        <div class="station-download-actions">
            <button type="button" class="station-download-action" onclick="closeStationDownloadModal()">ปิดหน้าต่าง</button>
            <a class="station-download-action primary<?= $stationSetupAvailable ? '' : ' disabled' ?>" href="download_station.php?download=1" download>
                <span style="margin-right: 8px; font-size: 18px;">📥</span> ดาวน์โหลดตัวติดตั้ง NCDs_RedAlert_Station_Setup.exe
            </a>
        </div>
    </section>
</div>
<script>
    function openStationDownloadModal() {
        const modal = document.getElementById('station-download-modal');
        if (!modal) return;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        const closeButton = modal.querySelector('.station-download-close');
        if (closeButton) closeButton.focus();
    }
    function closeStationDownloadModal() {
        const modal = document.getElementById('station-download-modal');
        if (!modal) return;
        modal.hidden = true;
        document.body.style.overflow = '';
    }
    function copyStationSha256(text) {
        if (!navigator.clipboard) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        } else {
            navigator.clipboard.writeText(text);
        }
        const btn = document.getElementById('btn-copy-sha-status');
        if (btn) {
            btn.innerHTML = '✅ คัดลอกแล้ว';
            btn.style.color = '#10b981';
            setTimeout(() => {
                btn.innerHTML = '📋 คัดลอก';
                btn.style.color = '';
            }, 2000);
        }
    }
    document.addEventListener('click', function (event) {
        const modal = document.getElementById('station-download-modal');
        if (modal && event.target === modal) closeStationDownloadModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeStationDownloadModal();
    });
</script>
