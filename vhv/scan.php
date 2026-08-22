<?php
// vhv/scan.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/demo_banner.php';

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

$presetHid = $_GET['hid'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <script>
        // Immediately apply theme before rendering
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อสม. ตาลสุม - สแกน QR Code ประจำบ้าน</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <script src="../assets/js/app.js"></script>
    <style>
        /* QR reader container */
        #reader {
            width: 100%;
            min-height: 280px;
            border-radius: var(--border-radius);
            overflow: hidden;
            background-color: var(--bg-darker);
            box-shadow: var(--neumorph-inset);
            position: relative;
        }
        #reader video {
            border-radius: var(--border-radius);
        }
        /* Override html5-qrcode built-in button */
        #reader__dashboard_section_csr button {
            background: var(--color-primary) !important;
            color: white !important;
            border: none !important;
            padding: 10px 20px !important;
            border-radius: 8px !important;
            font-size: 15px !important;
            font-weight: 700 !important;
            margin-top: 8px !important;
            cursor: pointer !important;
        }
        /* Camera status overlay */
        #camera-status {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 12px;
            text-align: center;
        }
        #camera-status.loading {
            background: rgba(30, 64, 175, 0.08);
            border: 2px dashed var(--color-primary);
        }
        #camera-status.error {
            background: rgba(239, 68, 68, 0.08);
            border: 2px solid var(--color-red);
        }
        #camera-status.warning {
            background: rgba(245, 158, 11, 0.08);
            border: 2px solid var(--color-yellow);
        }
        #camera-status.success {
            background: rgba(16, 185, 129, 0.08);
            border: 2px solid var(--color-green);
        }
        .spinner {
            width: 44px;
            height: 44px;
            border: 4px solid rgba(59,130,246,0.2);
            border-top-color: var(--color-primary);
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
            margin-bottom: 14px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .status-icon { font-size: 52px; margin-bottom: 12px; }
        .status-title { font-size: 17px; font-weight: 800; margin: 0 0 6px; }
        .status-desc  { font-size: 13px; line-height: 1.6; margin: 0 0 14px; color: var(--text-secondary); }
        .btn-retry {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, var(--color-primary), #1d4ed8);
            color: white; border: none; padding: 12px 24px;
            border-radius: var(--border-radius); font-size: 15px; font-weight: 800;
            cursor: pointer; box-shadow: 0 4px 12px rgba(59,130,246,0.3);
            transition: transform 0.15s;
        }
        .btn-retry:active { transform: scale(0.97); }
    </style>
</head>
<body class="vhv-accessibility">
<div class="mobile-wrapper">

    <!-- Header -->
    <div class="vhv-header">
        <h3 style="color:var(--color-accent);margin:0;font-size:16px;">สแกนรหัสประจำบ้าน</h3>
        <p style="color:var(--text-secondary);margin:4px 0 0;font-size:14px;">
            สแกนการ์ด QR Code ที่ติดหน้าบ้านเพื่อเข้าสู่การคัดกรอง
        </p>
        <div id="gps-warning" style="display:none; background: rgba(245,158,11,0.12); border: 1px solid var(--color-yellow); color: var(--color-yellow); padding: 10px; border-radius: 12px; font-size: 13px; margin-top: 10px; font-weight: bold; text-align: center; box-shadow: var(--neumorph-inset);">
            ⚠️ อุปกรณ์ปิดรับพิกัด หรือถูกปฏิเสธสิทธิ์เข้าถึงตำแหน่ง (GPS)<br><span style="font-size: 11.5px; font-weight: 500; opacity: 0.95;">กรุณาอนุญาตให้เข้าถึงตำแหน่งในเบราว์เซอร์เพื่อใช้ส่งข้อมูลจริง</span>
        </div>
    </div>

    <?php if (DemoDataProvider::isDemoMode()): ?>
    <!-- Demo Sandbox QR Simulation Card (ครอบคลุมหมู่ 1 - 5) -->
    <div class="card-dark" style="margin-bottom: 20px; border: 2px dashed #3b82f6; background: rgba(59, 130, 246, 0.05); border-radius: 16px; padding: 14px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-weight: 800; color: #3b82f6; font-size: 14.5px; display: flex; align-items: center; gap: 6px;">
                🧪 แผงจำลองการสแกน QR Code (แยกตามหมู่ 1 - 5)
            </span>
            <span style="font-size: 11px; background: #3b82f6; color: white; padding: 2px 8px; border-radius: 9999px; font-weight: bold;">โหมดทดสอบ</span>
        </div>
        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 12px; line-height: 1.4;">
            ทดสอบสแกนหรือส่อง QR จำลองตามเงื่อนไขที่กำหนดของแต่ละหมู่:
        </p>
        
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <!-- Scenario 1: หมู่ 1 (ได้รับมอบหมาย / หน้าหลัก) -->
            <div style="padding: 10px; border-radius: 10px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.35);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                    <strong style="color: var(--color-green, #10B981); font-size: 13px;">🟢 หมู่ 1: ได้รับมอบหมาย (สแกนผ่าน)</strong>
                    <span style="font-size: 11px; color: var(--color-green, #10B981); font-weight: bold;">บ้าน 12/1 (นายสมชาย)</span>
                </div>
                <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 8px;">
                    เคสในเขตรับผิดชอบ (กดคัดกรองได้ทั้งจากหน้าหลัก หรือสแกน QR ก็ผ่าน)
                </div>
                <div style="display: flex; gap: 6px;">
                    <button type="button" onclick="simulateScan('DEMO_HOUSE_12_1')" class="btn-action" style="flex: 1; padding: 8px 10px; font-size: 12px; font-weight: bold; background: var(--color-green, #10B981); color: white; border: none; border-radius: 8px; cursor: pointer;">
                        ⚡ จำลองสแกนผ่าน
                    </button>
                    <button type="button" onclick="showQrModal('DEMO_HOUSE_12_1', 'หมู่ 1: บ้าน 12/1 (นายสมชาย ใจดี)')" style="padding: 8px 10px; font-size: 12px; font-weight: bold; background: rgba(255,255,255,0.1); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                        📷 ส่อง QR
                    </button>
                </div>
            </div>

            <!-- Scenario 2: หมู่ 2 (Bypass สแกนได้ทั้งหมด) -->
            <div style="padding: 10px; border-radius: 10px; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.35);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                    <strong style="color: var(--color-primary, #3B82F6); font-size: 13px;">🔵 หมู่ 2: Bypass สแกนผ่านทั้งหมด</strong>
                    <span style="font-size: 11px; color: var(--color-primary, #3B82F6); font-weight: bold;">บ้าน 88 (นายบุญมี)</span>
                </div>
                <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 8px;">
                    Bypass QR Code ให้สแกนบ้านในหมู่ 2 ได้ทั้งหมด และเข้าสู่การคัดกรองได้ปกติ
                </div>
                <div style="display: flex; gap: 6px;">
                    <button type="button" onclick="simulateScan('DEMO_HOUSE_88_2')" class="btn-action" style="flex: 1; padding: 8px 10px; font-size: 12px; font-weight: bold; background: var(--color-primary, #3B82F6); color: white; border: none; border-radius: 8px; cursor: pointer;">
                        ⚡ จำลองสแกนผ่าน (ม.2)
                    </button>
                    <button type="button" onclick="showQrModal('DEMO_HOUSE_88_2', 'หมู่ 2: บ้าน 88 (นายบุญมี มีโชค)')" style="padding: 8px 10px; font-size: 12px; font-weight: bold; background: rgba(255,255,255,0.1); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                        📷 ส่อง QR
                    </button>
                </div>
            </div>

            <!-- Scenario 3: หมู่ 3 (ล็อกเพราะยังไม่ได้รับมอบหมายงาน) -->
            <div style="padding: 10px; border-radius: 10px; background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.35);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                    <strong style="color: #D97706; font-size: 13px;">🟡 หมู่ 3: ยังไม่ได้รับมอบหมาย (ติดล็อก)</strong>
                    <span style="font-size: 11px; color: #D97706; font-weight: bold;">บ้าน 15/3 (นายวิชัย)</span>
                </div>
                <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 8px;">
                    ระบบจะล็อกการคัดกรองทันทีเนื่องจากยังไม่มีการมอบหมายงานในระบบ อสม.
                </div>
                <div style="display: flex; gap: 6px;">
                    <button type="button" onclick="simulateScan('DEMO_HOUSE_15_3')" class="btn-action" style="flex: 1; padding: 8px 10px; font-size: 12px; font-weight: bold; background: #F59E0B; color: white; border: none; border-radius: 8px; cursor: pointer;">
                        ⚡ จำลองสแกน (ล็อกมอบหมาย)
                    </button>
                    <button type="button" onclick="showQrModal('DEMO_HOUSE_15_3', 'หมู่ 3: บ้าน 15/3 (นายวิชัย มั่นคง - ยังไม่มอบหมาย)')" style="padding: 8px 10px; font-size: 12px; font-weight: bold; background: rgba(255,255,255,0.1); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                        📷 ส่อง QR
                    </button>
                </div>
            </div>

            <!-- Scenario 4: หมู่ 4 และ 5 (ล็อกเพราะสแกนข้ามเขต) -->
            <div style="padding: 10px; border-radius: 10px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.35);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                    <strong style="color: var(--color-red, #EF4444); font-size: 13px;">🔴 หมู่ 4 & 5: สแกนข้ามเขต (ติดล็อก PDPA)</strong>
                    <span style="font-size: 11px; color: var(--color-red, #EF4444); font-weight: bold;">บ้าน 54 (ม.4) / 9/1 (ม.5)</span>
                </div>
                <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 8px;">
                    ระบบจะล็อกการคัดกรองและแจ้งเตือนว่าสแกนข้ามเขตพื้นที่รับผิดชอบ
                </div>
                <div style="display: flex; gap: 6px;">
                    <button type="button" onclick="simulateScan('DEMO_HOUSE_54_4')" class="btn-action" style="flex: 1; padding: 8px 10px; font-size: 12px; font-weight: bold; background: var(--color-red, #EF4444); color: white; border: none; border-radius: 8px; cursor: pointer;">
                        ⚡ จำลองสแกน (ม.4 ข้ามเขต)
                    </button>
                    <button type="button" onclick="simulateScan('DEMO_HOUSE_9_5')" class="btn-action" style="flex: 1; padding: 8px 10px; font-size: 12px; font-weight: bold; background: #DC2626; color: white; border: none; border-radius: 8px; cursor: pointer;">
                        ⚡ จำลองสแกน (ม.5 ข้ามเขต)
                    </button>
                    <button type="button" onclick="showQrModal('DEMO_HOUSE_54_4', 'หมู่ 4: บ้าน 54 (นายมานพ - ข้ามเขต)')" style="padding: 8px 10px; font-size: 12px; font-weight: bold; background: rgba(255,255,255,0.1); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                        📷 ส่อง QR
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dynamic PDPA / Assignment Lock overlay -->
    <div id="pdpa-lock-screen" style="display:none;" class="card-dark">
        <div style="text-align:center;padding:20px 0;">
            <span id="locked-icon" style="font-size:64px;display:block;margin-bottom:16px;">🔒</span>
            <h2 id="locked-title" style="color:var(--color-red, #EF4444);font-weight:800;font-size:21px;margin-bottom:10px;">ล็อคข้อมูล (PDPA)</h2>
            <p id="locked-desc" style="color:var(--text-primary);font-size:15px;line-height:1.5;margin-bottom:16px;font-weight:600;">
                รหัส <strong id="locked-hid"></strong> อยู่นอกเขตรับผิดชอบ หรือยังไม่มีการมอบหมายงานในระบบ
            </p>
            <div id="locked-notice" style="background:rgba(239,68,68,0.1);border:1px solid var(--color-red, #EF4444);color:var(--text-secondary);padding:12px;border-radius:var(--border-radius);font-size:13px;text-align:left;margin-bottom:20px;line-height:1.4;">
                ⚠️ ระบบได้บันทึกการพยายามเข้าถึงและส่งแจ้งเตือนไปยัง สสอ.ตาลสุมแล้ว
            </div>
            <button onclick="resetScanner()" class="btn-giant btn-giant-primary">🔄 สแกนใหม่อีกครั้ง</button>
        </div>
    </div>

    <!-- Scanner area -->
    <div id="scanner-area">
        <!-- Status box (loading/error/success feedback) -->
        <div id="camera-status" class="loading">
            <div class="spinner"></div>
            <p class="status-title" style="color:var(--color-primary);">กำลังโหลดระบบสแกน QR…</p>
            <p class="status-desc">กรุณารอสักครู่</p>
        </div>

        <!-- QR reader (hidden until camera opens) -->
        <div id="reader" style="display:none;"></div>

        <!-- Manual input -->
        <div class="card-dark" style="margin-top:16px;text-align:center;">
            <p style="color:var(--text-secondary);font-size:14px;margin:0 0 10px;">
                หากกล้องไม่ทำงาน สามารถกรอกรหัสบ้าน (HID) หรือเลขบัตร (CID) ด้วยตนเอง:
            </p>
            <div style="display:flex;gap:8px;">
                <input type="text"
                       id="manual-hid"
                       class="input-large"
                       style="height:50px;font-size:17px;flex-grow:1;"
                       placeholder="รหัส HID หรือเลขบัตรประชาชน"
                       value="<?= htmlspecialchars($presetHid) ?>"
                       inputmode="numeric">
                <button onclick="checkManualHid()"
                        class="numpad-btn btn-action"
                        style="height:50px;width:90px;margin-top:0;font-size:15px;border-radius:var(--border-radius);">
                    ตรวจสอบ
                </button>
            </div>
        </div>
    </div>

    <!-- Bottom nav -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-link">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            หน้าแรก
        </a>
        <a href="scan.php" class="nav-link nav-scan-fab fab-scan-pulse active">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
            <span>สแกนบ้าน</span>
        </a>
        <a href="leaderboard.php" class="nav-link">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            กระดานคะแนน
        </a>
        <a href="profile.php" class="nav-link">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            ข้อมูลส่วนตัว
        </a>
    </div>
</div>

<script>
/* =====================================================
   scan.php — QR Scanner with robust error handling
   ===================================================== */
let scanner   = null;   // Html5Qrcode instance
let gpsLat    = null;
let gpsLng    = null;
let libLoaded = false;

// ---------- UI helpers ----------
function setStatus(type, iconHtml, title, desc, extra = '') {
    const box = document.getElementById('camera-status');
    box.className = type;        // 'loading' | 'error' | 'warning' | 'success'
    box.style.display = 'flex';
    const titleColor = {
        loading: 'var(--color-primary)',
        error:   'var(--color-red)',
        warning: 'var(--color-yellow)',
        success: 'var(--color-green)'
    }[type] || 'var(--text-primary)';
    box.innerHTML = `
        ${iconHtml}
        <p class="status-title" style="color:${titleColor};">${title}</p>
        <p class="status-desc">${desc}</p>
        ${extra}
    `;
}

function hideStatus() {
    document.getElementById('camera-status').style.display = 'none';
}

function showReader() {
    document.getElementById('reader').style.display = 'block';
}

function hideReader() {
    document.getElementById('reader').style.display = 'none';
}

// ---------- Camera init ----------
function startCamera() {
    setStatus('loading',
        '<div class="spinner"></div>',
        'กำลังเปิดกล้อง…',
        'กรุณาอนุญาตการใช้งานกล้องเมื่อเบราว์เซอร์ถาม'
    );

    // Guard: library must be loaded
    if (typeof Html5Qrcode === 'undefined') {
        setStatus('error',
            '<span class="status-icon">📵</span>',
            'โหลดระบบสแกนไม่สำเร็จ',
            'ไม่สามารถโหลดไลบรารีสแกน QR ได้ อาจเกิดจากอินเทอร์เน็ตขัดข้อง',
            '<button class="btn-retry" onclick="location.reload()">🔄 โหลดหน้าใหม่</button>'
        );
        return;
    }

    // Guard: HTTPS required for camera
    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        setStatus('error',
            '<span class="status-icon">🔐</span>',
            'ต้องใช้การเชื่อมต่อแบบ HTTPS',
            'กล้องใช้งานได้เฉพาะบนเว็บไซต์ที่ใช้ HTTPS เท่านั้น กรุณาติดต่อผู้ดูแลระบบ'
        );
        return;
    }

    // Guard: mediaDevices API must exist
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        setStatus('error',
            '<span class="status-icon">📷</span>',
            'เบราว์เซอร์ไม่รองรับการใช้กล้อง',
            'กรุณาเปิดด้วย Chrome หรือ Safari เวอร์ชันล่าสุด',
            '<button class="btn-retry" onclick="location.reload()">🔄 ลองอีกครั้ง</button>'
        );
        return;
    }

    // Stop any previous scanner
    if (scanner) {
        scanner.stop().catch(() => {}).finally(() => {
            scanner = null;
            initScanner();
        });
    } else {
        initScanner();
    }
}

function initScanner() {
    showReader();
    scanner = new Html5Qrcode('reader');

    const config = {
        fps: 10,
        qrbox: { width: 240, height: 240 },
        aspectRatio: 1.0,
        showTorchButtonIfSupported: true,
        formatsToSupport: [ Html5QrcodeSupportedFormats.QR_CODE ]
    };

    scanner.start(
        { facingMode: 'environment' },
        config,
        onScanSuccess,
        () => { /* ignore per-frame failures */ }
    ).then(() => {
        hideStatus();   // camera open — hide status box
        startGpsTracking();
    }).catch(err => {
        hideReader();
        handleCameraError(err);
    });
}

// ---------- Error handling ----------
function handleCameraError(err) {
    const e = (err || '').toString().toLowerCase();

    if (e.includes('notallowed') || e.includes('permission') || e.includes('denied')) {
        setStatus('warning',
            '<span class="status-icon">🚫</span>',
            'ถูกปฏิเสธการใช้กล้อง',
            '📱 <strong>Android Chrome:</strong> แตะไอคอน 🔒 ด้านบน → กล้อง → อนุญาต<br>' +
            '🍎 <strong>iPhone Safari:</strong> การตั้งค่า → Safari → กล้อง → อนุญาต<br><br>' +
            'แล้วกดปุ่มลองอีกครั้ง',
            '<button class="btn-retry" onclick="startCamera()">🔄 ลองอีกครั้ง</button>'
        );

    } else if (e.includes('notfound') || e.includes('devicenotfound')) {
        setStatus('error',
            '<span class="status-icon">📷</span>',
            'ไม่พบกล้องในอุปกรณ์',
            'อุปกรณ์อาจไม่มีกล้อง หรือกล้องถูกใช้งานโดยแอปอื่น กรุณาปิดแอปอื่นที่ใช้กล้องแล้วลองใหม่',
            '<button class="btn-retry" onclick="startCamera()">🔄 ลองอีกครั้ง</button>'
        );

    } else if (e.includes('notreadable') || e.includes('trackstart')) {
        setStatus('error',
            '<span class="status-icon">📵</span>',
            'กล้องกำลังถูกใช้งานอยู่',
            'LINE, Facebook หรือแอปอื่นอาจเปิดกล้องอยู่ กรุณาปิดแอปเหล่านั้นแล้วลองใหม่',
            '<button class="btn-retry" onclick="startCamera()">🔄 ลองอีกครั้ง</button>'
        );

    } else if (e.includes('overconstrained') || e.includes('constraint')) {
        // Back camera failed → try front camera
        setStatus('warning',
            '<span class="status-icon">🤳</span>',
            'กำลังลองสลับเป็นกล้องหน้า…',
            'กล้องหลังไม่พร้อมใช้งาน กำลังลองกล้องหน้าแทน'
        );
        showReader();
        scanner = new Html5Qrcode('reader');
        scanner.start(
            { facingMode: 'user' },
            { fps: 10, qrbox: { width: 220, height: 220 } },
            onScanSuccess,
            () => {}
        ).then(() => {
            hideStatus();
            startGpsTracking();
        }).catch(() => {
            hideReader();
            setStatus('error',
                '<span class="status-icon">📷</span>',
                'เปิดกล้องทั้งสองด้านไม่สำเร็จ',
                'กรุณาใช้วิธีกรอกรหัสบ้านด้านล่างแทน',
                '<button class="btn-retry" onclick="startCamera()">🔄 ลองอีกครั้ง</button>'
            );
        });

    } else {
        setStatus('error',
            '<span class="status-icon">⚠️</span>',
            'เปิดกล้องไม่สำเร็จ',
            'กรุณาลองใหม่ หรือกรอกรหัสบ้านด้านล่างแทน<br><small style="color:var(--text-muted);">' + err + '</small>',
            '<button class="btn-retry" onclick="startCamera()">🔄 ลองอีกครั้ง</button>'
        );
    }
}

// ---------- Scan success ----------
function onScanSuccess(decodedText) {
    // Parse HID from plain text or URL
    let hid = decodedText.trim();
    if (hid.includes('hid=')) {
        try {
            const qs = hid.split('?')[1] || hid.split('hid=')[1];
            hid = new URLSearchParams(qs.startsWith('hid=') ? qs : 'hid=' + qs).get('hid') || hid;
        } catch(e) {}
    }

    // Beep / vibrate feedback
    if (navigator.vibrate) navigator.vibrate(100);

    // Stop camera
    if (scanner) {
        scanner.stop().catch(() => {}).finally(() => { scanner = null; });
    }
    hideReader();

    setStatus('success',
        '<span class="status-icon">✅</span>',
        'สแกนสำเร็จ! กำลังตรวจสอบสิทธิ์…',
        'กรุณารอสักครู่'
    );

    validateHouseAssignment(hid);
}

// ---------- Manual input ----------
function checkManualHid() {
    const hid = document.getElementById('manual-hid').value.trim();
    if (hid.length < 5) {
        alert('กรุณากรอกรหัสบ้าน HID ที่ถูกต้อง');
        return;
    }
    if (scanner) scanner.stop().catch(() => {});
    setStatus('loading',
        '<div class="spinner"></div>',
        'กำลังตรวจสอบรหัสบ้าน…',
        'กรุณารอสักครู่'
    );
    validateHouseAssignment(hid);
}

// ---------- Validate via API ----------
async function validateHouseAssignment(hid) {
    // Offline fallback
    if (!navigator.onLine) {
        const cache = [
            ...JSON.parse(localStorage.getItem('vhv_pending_tasks')   || '[]'),
            ...JSON.parse(localStorage.getItem('vhv_completed_tasks') || '[]'),
            ...JSON.parse(localStorage.getItem('vhv_dpac_tasks')      || '[]'),
            ...JSON.parse(localStorage.getItem('vhv_completed_dpac_tasks') || '[]')
        ];
        const match = cache.find(t => String(t.hid) === String(hid) || String(t.cid) === String(hid));
        if (match) { goToForm(hid); } else { showLock(hid); }
        return;
    }

    // หากยังจับพิกัดไม่ได้ ให้พยายามดึงพิกัดแบบเร่งด่วน ณ วินาทีนี้ (สูงสุด 2.0 วินาที)
    if (!gpsLat || !gpsLng) {
        try {
            const location = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(
                    p => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
                    reject,
                    { timeout: 2000, maximumAge: 30000, enableHighAccuracy: false } // ปิด High Accuracy ใน fallback ด่วนเพื่อให้ได้พิกัดเร็วขึ้นจากเสาสัญญาณ/WiFi
                );
            });
            gpsLat = location.lat;
            gpsLng = location.lng;
            document.getElementById('gps-warning').style.display = 'none';
        } catch (e) {
            // ดึงพิกัดไม่ได้ ให้ข้ามเพื่อไม่ทำให้แอปค้าง
        }
    }

    fetch('../api/check_qrcode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ hid, lat: gpsLat || 0, lng: gpsLng || 0 })
    })
    .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
    .then(data => {
        if (data.status === 'success') { 
            goToForm(hid); 
        } else { 
            showLock(hid, data.message, data.lock_title, data.sub_message, data.error_code); 
        }
    })
    .catch(() => {
        // Network error: try local cache
        const pending = JSON.parse(localStorage.getItem('vhv_pending_tasks') || '[]');
        const match   = pending.find(t => String(t.hid) === String(hid) || String(t.cid) === String(hid));
        if (match) {
            goToForm(hid);
        } else {
            setStatus('error',
                '<span class="status-icon">🌐</span>',
                'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์',
                'กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต แล้วลองใหม่',
                '<button class="btn-retry" onclick="resetScanner()">🔄 ลองสแกนใหม่</button>'
            );
        }
    });
}

function goToForm(hid) {
    if (/^\d{13}$/.test(hid)) {
        window.location.href = 'screening_form.php?cid=' + encodeURIComponent(hid);
    } else {
        window.location.href = 'screening_form.php?hid=' + encodeURIComponent(hid);
    }
}

function showLock(hid, message, lockTitle, subMessage, errorCode) {
    document.getElementById('scanner-area').style.display = 'none';
    document.getElementById('pdpa-lock-screen').style.display = 'block';
    
    document.getElementById('locked-hid').textContent = hid;
    
    const iconEl = document.getElementById('locked-icon');
    const titleEl = document.getElementById('locked-title');
    const descEl = document.getElementById('locked-desc');
    const noticeEl = document.getElementById('locked-notice');
    
    if (errorCode === 'UNASSIGNED_TASK') {
        if (iconEl) iconEl.textContent = '🔒';
        if (titleEl) {
            titleEl.textContent = lockTitle || 'ยังไม่ได้รับมอบหมายงาน (หมู่ 3)';
            titleEl.style.color = '#D97706';
        }
    } else {
        if (iconEl) iconEl.textContent = '🚫';
        if (titleEl) {
            titleEl.textContent = lockTitle || 'สแกนข้ามเขตรับผิดชอบ (PDPA)';
            titleEl.style.color = 'var(--color-red, #EF4444)';
        }
    }
    
    if (descEl) {
        descEl.innerHTML = message || `รหัส <strong>${hid}</strong> อยู่นอกเขตรับผิดชอบ หรือยังไม่มีการมอบหมายงานในระบบ`;
    }
    if (noticeEl) {
        const prefix = (errorCode === 'UNASSIGNED_TASK' ? 'ℹ️ ' : '⚠️ ');
        noticeEl.innerHTML = prefix + (subMessage || 'ระบบได้บันทึกการพยายามเข้าถึงและปฏิบัติตามมาตรการ PDPA');
    }
}

function resetScanner() {
    document.getElementById('pdpa-lock-screen').style.display = 'none';
    document.getElementById('scanner-area').style.display = 'block';
    startCamera();
}

// ---------- GPS (background) ----------
let gpsWatchId = null;

function startGpsTracking() {
    if (gpsWatchId !== null) return; // Already tracking
    if (!navigator.geolocation) {
        document.getElementById('gps-warning').style.display = 'block';
        return;
    }
    gpsWatchId = navigator.geolocation.watchPosition(
        p => {
            gpsLat = p.coords.latitude;
            gpsLng = p.coords.longitude;
            document.getElementById('gps-warning').style.display = 'none';
        },
        err => {
            console.error("GPS watchPosition error:", err);
            // แสดงเตือนเมื่อปฏิเสธสิทธิ์หรือปิด GPS
            if (err.code === 1 || err.code === 2) {
                document.getElementById('gps-warning').style.display = 'block';
            }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
    );
}

function getCurrentLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) { reject('not_supported'); return; }
        navigator.geolocation.getCurrentPosition(
            p => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
            err => reject(err),
            { timeout: 6000, maximumAge: 15000, enableHighAccuracy: true }
        );
    });
}

function simulateScan(mockHid) {
    if (scanner) {
        scanner.stop().catch(() => {}).finally(() => {
            scanner = null;
        });
    }
    hideReader();
    setStatus('loading',
        '<div class="spinner"></div>',
        'กำลังจำลองการสแกนรหัส: ' + mockHid + '…',
        'กรุณารอสักครู่'
    );
    setTimeout(() => {
        validateHouseAssignment(mockHid);
    }, 400);
}

function showQrModal(mockHid, title) {
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(mockHid);
    document.getElementById('demo-qr-title').textContent = title;
    document.getElementById('demo-qr-img').src = qrUrl;
    document.getElementById('demo-qr-code-txt').textContent = mockHid;
    document.getElementById('demo-qr-modal').style.display = 'flex';
}

function closeQrModal() {
    document.getElementById('demo-qr-modal').style.display = 'none';
}

// ---------- Bootstrap ----------
document.addEventListener('DOMContentLoaded', () => {
    // โหลดพิกัด GPS แบบเบื้องหลังพร้อมหน่วงเวลา 1.5 วินาที เพื่อเลี่ยงการแย่งสิทธิ์กับกล้องตอนโหลดหน้าแรก
    setTimeout(startGpsTracking, 1500);

    // Load html5-qrcode library dynamically (versioned URL for stability)
    const LIB_URL = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';

    // Timeout: if lib doesn't load in 8s, show error
    const libTimeout = setTimeout(() => {
        if (!libLoaded) {
            setStatus('error',
                '<span class="status-icon">📵</span>',
                'โหลดระบบสแกนช้าเกินไป',
                'การเชื่อมต่ออาจช้า กรุณาโหลดหน้าใหม่ หรือกรอกรหัสบ้านด้านล่างแทน',
                '<button class="btn-retry" onclick="location.reload()">🔄 โหลดหน้าใหม่</button>'
            );
        }
    }, 8000);

    const script = document.createElement('script');
    script.src   = LIB_URL;
    script.onload = () => {
        libLoaded = true;
        clearTimeout(libTimeout);
        startCamera();
    };
    script.onerror = () => {
        clearTimeout(libTimeout);
        setStatus('error',
            '<span class="status-icon">📵</span>',
            'โหลดระบบสแกน QR ไม่สำเร็จ',
            'ไม่สามารถโหลดจาก CDN ได้ กรุณาตรวจสอบการเชื่อมต่อ แล้วโหลดหน้าใหม่ หรือกรอกรหัสด้วยตนเองด้านล่าง',
            '<button class="btn-retry" onclick="location.reload()">🔄 โหลดหน้าใหม่</button>'
        );
    };
    document.head.appendChild(script);

    <?php if (!empty($presetHid)): ?>
    document.getElementById('manual-hid').value = '<?= htmlspecialchars($presetHid) ?>';
    <?php endif; ?>
});
</script>

<!-- Demo QR Modal -->
<div id="demo-qr-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center; padding:20px;">
    <div class="card-dark" style="max-width:320px; width:100%; text-align:center; padding:24px; position:relative; border-radius:16px; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
        <button onclick="closeQrModal()" style="position:absolute; top:12px; right:12px; background:none; border:none; color:var(--text-secondary); font-size:20px; cursor:pointer;">✕</button>
        <h4 id="demo-qr-title" style="margin:0 0 12px; color:var(--color-primary); font-size:15px;">QR Code จำลอง</h4>
        <div style="background:white; padding:12px; border-radius:12px; display:inline-block; margin-bottom:12px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
            <img id="demo-qr-img" src="" alt="Demo QR Code" style="width:190px; height:190px; display:block;">
        </div>
        <p style="font-size:12px; color:var(--text-muted); margin:0 0 14px; line-height:1.4;">
            รหัส: <code id="demo-qr-code-txt" style="color:var(--color-accent); font-weight:bold;"></code><br>
            (สามารถใช้โทรศัพท์อีกเครื่องส่อง หรือกดปุ่มจำลองได้ทันที)
        </p>
        <button onclick="simulateScan(document.getElementById('demo-qr-code-txt').textContent); closeQrModal();" class="btn-action" style="width:100%; padding:10px; font-weight:bold; font-size:13px; background:var(--color-primary); color:white; border:none; border-radius:8px; cursor:pointer;">
            ⚡ จำลองสแกนรหัสนี้ทันที
        </button>
    </div>
</div>

</body>
</html>
