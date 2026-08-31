<?php
$stationSetupPath = __DIR__ . DIRECTORY_SEPARATOR . 'NCDs_RedAlert_Station_Setup.exe';
$stationSetupAvailable = is_file($stationSetupPath);
$stationSetupSize = $stationSetupAvailable ? filesize($stationSetupPath) : 0;
?>
<style>
    .station-download-modal[hidden] { display: none !important; }
    .station-download-modal { position: fixed; inset: 0; z-index: 100000; display: grid; place-items: center; padding: 20px; background: rgba(15, 35, 58, .46); backdrop-filter: blur(5px); }
    .station-download-dialog { width: min(520px, 100%); border: 0; border-radius: 24px; padding: 24px; color: #173555; background: #edf3f9; box-shadow: 0 18px 42px rgba(24,52,81,.28); font-family: "Leelawadee UI", Tahoma, sans-serif; }
    .station-download-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; }
    .station-download-title { margin: 0; font-size: 1.35rem; font-weight: 800; line-height: 1.35; }
    .station-download-subtitle { margin: 6px 0 0; color: #60758b; font-size: .96rem; line-height: 1.55; }
    .station-download-close { flex: 0 0 42px; width: 42px; height: 42px; border: 0; border-radius: 14px; color: #526980; background: #edf3f9; box-shadow: 5px 5px 12px #cbd6e1, -5px -5px 12px #fff; cursor: pointer; font-size: 1.35rem; }
    .station-download-info { margin: 20px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .station-download-item { min-width: 0; padding: 13px 15px; border-radius: 16px; background: #e7eef6; box-shadow: inset 2px 2px 5px #cbd6e1, inset -2px -2px 5px #fff; }
    .station-download-item small { display: block; color: #718399; margin-bottom: 3px; }
    .station-download-item strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .station-download-note { margin: 0 0 20px; padding: 13px 15px; border-radius: 15px; color: #6e4d13; background: #fff4d8; line-height: 1.55; font-size: .92rem; }
    .station-download-error { color: #a92b38; background: #ffe7ea; }
    .station-download-actions { display: grid; grid-template-columns: 1fr 1.45fr; gap: 12px; }
    .station-download-action { min-height: 48px; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 15px; padding: 10px 18px; font: inherit; font-weight: 800; text-decoration: none; cursor: pointer; color: #405a74; background: #edf3f9; box-shadow: 5px 5px 12px #cbd6e1, -5px -5px 12px #fff; }
    .station-download-action.primary { color: #fff; background: #4169e8; box-shadow: 5px 5px 13px rgba(43,75,162,.3), -4px -4px 10px #fff; }
    .station-download-action.disabled { opacity: .5; pointer-events: none; }
    @media (max-width: 540px) { .station-download-info, .station-download-actions { grid-template-columns: 1fr; } .station-download-dialog { padding: 20px; } }
</style>

<div id="station-download-modal" class="station-download-modal" role="dialog" aria-modal="true" aria-labelledby="station-download-title" hidden>
    <section class="station-download-dialog">
        <div class="station-download-head">
            <div>
                <h2 id="station-download-title" class="station-download-title">ดาวน์โหลด Red Alert Station</h2>
                <p class="station-download-subtitle">Version 1.0 สำหรับติดตั้งหรืออัปเกรดบนเครื่องรับแจ้งเตือน</p>
            </div>
            <button type="button" class="station-download-close" onclick="closeStationDownloadModal()" aria-label="ปิด">×</button>
        </div>
        <div class="station-download-info">
            <div class="station-download-item"><small>Build</small><strong>202608312200</strong></div>
            <div class="station-download-item"><small>ขนาดไฟล์</small><strong><?= $stationSetupAvailable ? number_format($stationSetupSize / 1024, 0) . ' KB' : 'ไม่พบไฟล์' ?></strong></div>
        </div>
        <?php if ($stationSetupAvailable): ?>
            <p class="station-download-note">ก่อนติดตั้ง ให้ปิด Red Alert Station ที่กำลังทำงาน รวมถึงไอคอนบริเวณมุมขวาล่างของ Windows</p>
        <?php else: ?>
            <p class="station-download-note station-download-error">ไม่พบตัวติดตั้งบนเซิร์ฟเวอร์ กรุณาแจ้งผู้ดูแลระบบ</p>
        <?php endif; ?>
        <div class="station-download-actions">
            <button type="button" class="station-download-action" onclick="closeStationDownloadModal()">ยกเลิก</button>
            <a class="station-download-action primary<?= $stationSetupAvailable ? '' : ' disabled' ?>" href="download_station.php?download=1" download>ดาวน์โหลด</a>
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
    document.addEventListener('click', function (event) {
        const modal = document.getElementById('station-download-modal');
        if (modal && event.target === modal) closeStationDownloadModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeStationDownloadModal();
    });
</script>
