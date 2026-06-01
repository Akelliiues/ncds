<?php
// vhv/scan.php
session_start();

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

$presetHid = $_GET['hid'] ?? ''; // Support fallback if loaded with query parameter directly
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อสม. นครตาลสุม - สแกน QR Code ประจำบ้าน</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- HTML5 QR Code CDN -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="../assets/js/app.js"></script>
</head>
<body class="vhv-accessibility">
    <div class="mobile-wrapper">
        <!-- VHV Header -->
        <div class="vhv-header">
            <h3 style="color: var(--color-accent); margin: 0; font-size: 16px;">สแกนรหัสประจำบ้าน</h3>
            <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 14px;">กรุณาสแกนการ์ด QR Code ที่ติดหน้าบ้านเพื่อเข้าสู่การคัดกรอง</p>
        </div>

        <!-- Lock Screen / PDPA Guard Overlay -->
        <div id="pdpa-lock-screen" style="display: none;" class="card-dark">
            <div style="text-align: center; padding: 20px 0;">
                <span style="font-size: 64px; display: block; margin-bottom: 20px;">🔒</span>
                <h2 style="color: var(--color-red); font-weight: 800; font-size: 24px; margin-bottom: 12px;">ล็อคข้อมูล (PDPA Lock)</h2>
                <p style="color: var(--text-primary); font-size: 18px; line-height: 1.6; margin-bottom: 24px;">
                    บ้านเลขที่นี้ <strong id="locked-hid"></strong> อยู่นอกเขตพื้นที่ความรับผิดชอบของคุณ หรือยังไม่ได้จับคู่มอบหมายงานระบบ
                </p>
                <div style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid var(--color-red); color: var(--text-secondary); padding: 12px; border-radius: var(--border-radius); font-size: 14px; text-align: left; margin-bottom: 24px;">
                    ⚠️ <strong>การคุ้มครองข้อมูลส่วนบุคคล:</strong> ข้อมูลบ้านรายนี้ถูกล็อคเป็น Read-Only ตามมาตรการความปลอดภัย และระบบได้บันทึกการพยายามเข้าถึงที่ผิดปกติส่งไปยังศูนย์ควบคุม สสอ. ตาลสุม เรียบร้อยแล้ว
                </div>
                <a href="index.php" class="btn-giant btn-giant-primary">กลับหน้าแดชบอร์ด</a>
            </div>
        </div>

        <!-- Normal Scanning Area -->
        <div id="scanner-area">
            <div id="reader" style="width: 100%; border: none; border-radius: var(--border-radius); overflow: hidden; background-color: var(--bg-darker); box-shadow: var(--neumorph-inset);"></div>
            
            <div class="card-dark" style="margin-top: 20px; text-align: center;">
                <p style="color: var(--text-secondary); font-size: 15px; margin: 0 0 12px 0;">หากไม่สามารถใช้กล้องสแกนได้ สามารถกรอกรหัสบ้าน (HID) หรือเลขบัตรประชาชน (CID) ด้วยตนเอง:</p>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="manual-hid" class="input-large" style="height: 50px; font-size: 18px; flex-grow: 1;" placeholder="กรอกรหัสบ้าน HID หรือเลขบัตรประชาชน" value="<?= htmlspecialchars($presetHid) ?>" inputmode="numeric">
                    <button onclick="checkManualHid()" class="numpad-btn btn-action" style="height: 50px; width: 90px; margin-top: 0; font-size: 16px; border-radius: var(--border-radius);">ตรวจสอบ</button>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation Bar -->
        <div class="bottom-nav">
            <a href="index.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                หน้าแรก
            </a>
            <a href="scan.php" class="nav-link nav-scan-fab fab-scan-pulse active">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                <span>สแกนบ้าน</span>
            </a>
            <a href="leaderboard.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                กระดานคะแนน
            </a>
            <a href="profile.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                ข้อมูลส่วนตัว
            </a>
        </div>
    </div>

    <script>
        let html5QrcodeScanner = null;

        document.addEventListener("DOMContentLoaded", function() {
            // Get location coordinate parameters (needed for PDPA Handshake validation)
            let currentLat = null;
            let currentLng = null;

            getCurrentLocation().then(coords => {
                currentLat = coords.lat;
                currentLng = coords.lng;
            }).catch(err => {
                console.warn("Unable to get GPS coords for scanner handshake:", err);
            });

            // Initialize camera scanner
            html5QrcodeScanner = new Html5Qrcode("reader");
            
            const config = { fps: 10, qrbox: { width: 250, height: 250 } };
            
            html5QrcodeScanner.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanFailure
            ).catch(err => {
                console.error("Camera initialisation error:", err);
            });

            function onScanSuccess(decodedText, decodedResult) {
                // Parse HID from scanned text
                // It can be a raw HID (15 digits) or a URL containing ?hid=[HID]
                let hid = decodedText;
                if (decodedText.includes('hid=')) {
                    const urlParams = new URLSearchParams(decodedText.split('?')[1]);
                    hid = urlParams.get('hid');
                }

                // Stop camera after successful scan to save resource
                html5QrcodeScanner.stop().then(() => {
                    validateHouseAssignment(hid, currentLat, currentLng);
                });
            }

            function onScanFailure(error) {
                // Ignore scanning failures to prevent logging overload
            }
        });

        function checkManualHid() {
            const hid = document.getElementById("manual-hid").value.trim();
            if (hid.length < 5) {
                alert("กรุณากรอกรหัสบ้าน HID ที่ถูกต้อง");
                return;
            }

            getCurrentLocation().then(coords => {
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.stop().catch(() => {});
                }
                validateHouseAssignment(hid, coords.lat, coords.lng);
            }).catch(err => {
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.stop().catch(() => {});
                }
                validateHouseAssignment(hid, null, null);
            });
        }

        function validateHouseAssignment(hid, lat, lng) {
            if (!navigator.onLine) {
                // Offline verification: check local cache
                const pending = JSON.parse(localStorage.getItem('vhv_pending_tasks') || '[]');
                const completed = JSON.parse(localStorage.getItem('vhv_completed_tasks') || '[]');
                const dpac = JSON.parse(localStorage.getItem('vhv_dpac_tasks') || '[]');
                const completedDpac = JSON.parse(localStorage.getItem('vhv_completed_dpac_tasks') || '[]');
                
                const allTasks = [...pending, ...completed, ...dpac, ...completedDpac];
                const match = allTasks.find(t => 
                    String(t.hid) === String(hid) || 
                    String(t.cid) === String(hid)
                );
                
                if (match) {
                    if (/^\d{13}$/.test(hid)) {
                        window.location.href = 'screening_form.php?cid=' + encodeURIComponent(hid);
                    } else {
                        window.location.href = 'screening_form.php?hid=' + encodeURIComponent(hid);
                    }
                } else {
                    document.getElementById('scanner-area').style.display = 'none';
                    document.getElementById('pdpa-lock-screen').style.display = 'block';
                    document.getElementById('locked-hid').innerText = hid;
                }
                return;
            }

            // Send to check_qrcode API (online)
            fetch('../api/check_qrcode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'hid': hid,
                    'lat': lat || 0,
                    'lng': lng || 0
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Valid assignment, redirect to screening form
                    if (/^\d{13}$/.test(hid)) {
                        window.location.href = 'screening_form.php?cid=' + encodeURIComponent(hid);
                    } else {
                        window.location.href = 'screening_form.php?hid=' + encodeURIComponent(hid);
                    }
                } else {
                    // Invalid/Cross-District Lock, show lock screen overlay
                    document.getElementById('scanner-area').style.display = 'none';
                    document.getElementById('pdpa-lock-screen').style.display = 'block';
                    document.getElementById('locked-hid').innerText = hid;
                }
            })
            .catch(err => {
                alert("เกิดข้อผิดพลาดในการตรวจสอบสิทธิ์ความปลอดภัย: " + err);
                window.location.reload();
            });
        }
    </script>
</body>
</html>
