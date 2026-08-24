<?php
// admin/emergency_receiver.php - NCDs Red Alert Desktop Station (ศูนย์รับสัญญาณวิกฤตฉุกเฉินประจำ รพ.สต.)
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$is_super_admin = !empty($_SESSION['is_super_admin']);
$hc_names = function_exists('get_health_units') ? get_health_units() : [];

$selected_hoscode = $_GET['hoscode'] ?? $admin_hoscode ?? '07758';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <script>
        (function() {
            window.name = "ncd_red_alert_station_tab";
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔰 NCDs Red Alert Station - ศูนย์รับสัญญาณวิกฤตฉุกเฉิน</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --emergency-red: #DC2626;
            --emergency-dark-red: #991B1B;
        }

        body {
            margin: 0;
            padding: 0;
            background: var(--bg-main, #eef2f7);
            color: var(--text-primary, #0d2c54);
            font-family: var(--font-base, 'Sarabun', sans-serif);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .station-header {
            background: var(--bg-card, #ffffff);
            border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.06));
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .station-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pulsing-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 12px #10B981;
            animation: pulse-green 1.8s infinite ease-in-out;
            flex-shrink: 0;
        }

        .pulsing-dot.active-crisis {
            background: #DC2626;
            box-shadow: 0 0 20px #DC2626;
            animation: pulse-red 0.8s infinite ease-in-out;
        }

        @keyframes pulse-green {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.6; }
        }

        @keyframes pulse-red {
            0%, 100% { transform: scale(1); opacity: 1; filter: brightness(1.3); }
            50% { transform: scale(1.5); opacity: 0.7; filter: brightness(0.9); }
        }

        @keyframes emergencyBeaconPulse {
            0% {
                transform: scale(1);
                box-shadow: inset 2px 2px 4px rgba(255,255,255,0.7), 0 0 0 0 rgba(255, 255, 255, 0.75), 0 6px 18px rgba(0,0,0,0.3);
            }
            50% {
                transform: scale(1.08);
                box-shadow: inset 2px 2px 4px rgba(255,255,255,0.9), 0 0 0 12px rgba(255, 255, 255, 0), 0 10px 25px rgba(220,38,38,0.6);
            }
            100% {
                transform: scale(1);
                box-shadow: inset 2px 2px 4px rgba(255,255,255,0.7), 0 0 0 0 rgba(255, 255, 255, 0), 0 6px 18px rgba(0,0,0,0.3);
            }
        }

        .station-container {
            flex: 1;
            padding: 24px;
            max-width: 1240px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }

        .status-hero {
            background: var(--bg-card, #ffffff);
            border: 1.5px solid var(--border-color, rgba(0,0,0,0.06));
            border-radius: 26px;
            padding: 28px 24px;
            text-align: center;
            box-shadow: var(--neumorph-flat);
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .status-hero.alerting {
            background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%) !important;
            border-color: #EF4444;
            color: #FFFFFF !important;
            box-shadow: 0 15px 40px rgba(220, 38, 38, 0.45);
        }

        .status-hero.alerting p {
            color: rgba(255, 255, 255, 0.92) !important;
        }

        .alert-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 22px;
        }

        .alert-item-card {
            background: var(--bg-card, #ffffff);
            border: 1.5px solid var(--border-color, rgba(0,0,0,0.06));
            border-radius: 24px;
            padding: 22px;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
            box-shadow: var(--neumorph-flat);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .alert-item-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 32px rgba(0,0,0,0.09);
        }

        .alert-item-card.pending {
            border-color: rgba(220, 38, 38, 0.55);
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.05) 0%, var(--bg-card) 100%);
            box-shadow: 0 10px 28px rgba(220, 38, 38, 0.18), var(--neumorph-flat);
        }

        .alert-item-card.acknowledged {
            border-color: rgba(245, 158, 11, 0.45);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.04) 0%, var(--bg-card) 100%);
        }

        /* Fullscreen Overlay Popup */
        #emergency-popup-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999999;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .emergency-modal-card {
            background: var(--bg-card, #ffffff);
            border: 3px solid #DC2626;
            border-radius: 28px;
            max-width: 580px;
            width: 100%;
            padding: 30px 26px;
            box-shadow: 0 25px 70px rgba(220, 38, 38, 0.5);
            color: var(--text-primary, #0d2c54);
            text-align: center;
            animation: pop-bounce 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes pop-bounce {
            0% { transform: scale(0.85); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .btn-action-glow {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            border: none;
            padding: 15px 24px;
            border-radius: 16px;
            font-size: 15.5px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
        }

        .btn-action-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(220, 38, 38, 0.6);
        }

        .tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
        }

        .tag-danger { background: rgba(220, 38, 38, 0.12); color: #DC2626; border: 1px solid rgba(220, 38, 38, 0.3); }
        .tag-warning { background: rgba(245, 158, 11, 0.12); color: #D97706; border: 1px solid rgba(245, 158, 11, 0.3); }
        .tag-success { background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); }
        .tag-blue { background: rgba(37, 99, 235, 0.12); color: #2563EB; border: 1px solid rgba(37, 99, 235, 0.3); }

        [data-theme="dark"] .tag-danger { background: rgba(220, 38, 38, 0.22); color: #F87171; border-color: rgba(220, 38, 38, 0.4); }
        [data-theme="dark"] .tag-warning { background: rgba(245, 158, 11, 0.22); color: #FBBF24; border-color: rgba(245, 158, 11, 0.4); }
        [data-theme="dark"] .tag-success { background: rgba(16, 185, 129, 0.22); color: #34D399; border-color: rgba(16, 185, 129, 0.4); }
        [data-theme="dark"] .tag-blue { background: rgba(56, 189, 248, 0.2); color: #38BDF8; border-color: rgba(56, 189, 248, 0.4); }

        .btn-station-ctrl {
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border-color, #CBD5E1);
            color: var(--text-primary, #0d2c54);
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-station-ctrl:hover {
            transform: translateY(-2px);
            background: var(--bg-darker, #f1f5f9);
        }
    </style>
</head>
<body>

    <!-- Station Top Bar (Hospital Emergency Dispatcher Header) -->
    <header class="station-header">
        <div class="station-title">
            <div id="station-pulsing-dot" class="pulsing-dot"></div>
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h1 style="margin: 0; font-size: 18px; font-weight: 900; color: var(--text-primary);">
                        NCDs Red Alert Station
                    </h1>
                    <span style="font-size: 10.5px; background: #DC2626; color: white; padding: 2px 8px; border-radius: 6px; font-weight: 800;">LIVE DISPATCH</span>
                </div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                    ศูนย์รับสัญญาณเคสวิกฤตฉุกเฉินประจำ รพ.สต. • เฝ้าระวังสด Realtime 24 ชม.
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <!-- Health Center Selector -->
            <select id="select-hoscode" onchange="changeHoscode(this.value)" style="background: var(--bg-darker); color: var(--text-primary); border: 1.5px solid var(--border-color, #CBD5E1); padding: 8px 14px; border-radius: 12px; font-size: 13px; font-weight: 700; outline: none; box-shadow: var(--neumorph-inset);">
                <option value="ALL">ทุก รพ.สต. (ภาพรวมอำเภอ)</option>
                <?php foreach ($hc_names as $code => $name): ?>
                    <option value="<?= $code ?>" <?= $selected_hoscode == $code ? 'selected' : '' ?>>
                        [<?= $code ?>] <?= $name ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Audio Siren Test Button -->
            <button type="button" onclick="testAudio()" id="btn-audio-toggle" class="btn-station-ctrl">
                <span class="neu-disc-icon xs disc-blue">🔊</span>
                <span>ทดสอบเสียงไซเรน</span>
            </button>

            <!-- Test Alert Trigger -->
            <button type="button" onclick="simulateTestAlert()" style="background: linear-gradient(135deg, #DC2626, #B91C1C); border: none; color: white; padding: 8px 16px; border-radius: 12px; font-size: 13px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35); display: inline-flex; align-items: center; gap: 8px;">
                <span class="neu-disc-icon xs" style="background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.4); box-shadow: none;">⚡</span>
                <span>จำลองยิงสัญญาณฉุกเฉิน</span>
            </button>

            <!-- Safe ZIP Download Link -->
            <a href="download_station.php?format=zip" class="btn-station-ctrl" style="background: linear-gradient(135deg, #10B981, #059669); color: white; border: none; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);" title="ดาวน์โหลดโปรแกรม NCDs Red Alert Station (ไฟล์ .ZIP ปลอดภัย ไม่โดนบล็อก)">
                <span class="neu-disc-icon xs" style="background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.4); box-shadow: none;">📥</span>
                <span>ดาวน์โหลดแอป (.zip)</span>
            </a>

            <!-- Referral Board -->
            <a href="critical_referrals.php" onclick="openOrFocusTab('critical_referrals.php', 'ncd_critical_referrals_tab'); return false;" class="btn-station-ctrl" style="background: #2563EB; color: white; border: none; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);">
                <span class="neu-disc-icon xs" style="background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.4); box-shadow: none;">📋</span>
                <span>บอร์ดส่งต่อ</span>
            </a>

            <!-- Theme Toggle Button (Icon only matching main navbar) -->
            <button id="theme-toggle-btn" class="btn-station-ctrl" onclick="toggleTheme()" style="width: 38px; height: 38px; padding: 0; border-radius: 50%; justify-content: center;" title="สลับโหมด มืด/สว่าง">
                <!-- Sun Icon -->
                <svg id="theme-toggle-sun" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="display: none;">
                    <circle cx="12" cy="12" r="5"></circle>
                    <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
                </svg>
                <!-- Moon Icon -->
                <svg id="theme-toggle-moon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path>
                </svg>
            </button>

            <!-- Home Dashboard Link -->
            <a href="index.php" class="btn-station-ctrl" title="กลับสู่หน้าแดชบอร์ดหลัก">
                <span class="neu-disc-icon xs disc-blue">🏠</span>
                <span>หน้าหลัก</span>
            </a>
        </div>
    </header>

    <!-- Main Live Container -->
    <main class="station-container">
        
        <!-- Live Status Hero Banner (Standby vs Active Alerting) -->
        <div id="status-hero" class="status-hero">
            <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                <div id="status-icon-container" class="neu-disc-icon" style="width: 72px; height: 72px; min-width: 72px; background: radial-gradient(circle at 35% 35%, #34D399 0%, #10B981 70%, #047857 100%); color: #fff; border: 2.5px solid rgba(255, 255, 255, 0.85); box-shadow: inset 2px 2px 4px rgba(255,255,255,0.7), 0 6px 18px rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center;">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: block; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.25));">
                        <path d="M24 4 L40 10 V22 C40 32.5 33.2 41.8 24 45 C14.8 41.8 8 32.5 8 22 V10 L24 4 Z" fill="#FFFFFF"/>
                        <path d="M24 8 L36 12.5 V22 C36 30.5 30.8 38 24 40.8 C17.2 38 12 30.5 12 22 V12.5 L24 8 Z" fill="#E6FDF5"/>
                        <path d="M16 23 L22 29 L32 17" stroke="#059669" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <h2 id="status-headline" style="margin: 0 0 6px 0; font-size: 22px; font-weight: 900; color: var(--text-primary);">
                สถานีพร้อมรับสัญญาณฉุกเฉิน (Active Standby)
            </h2>
            <p id="status-sub" style="margin: 0 0 14px 0; font-size: 14px; color: var(--text-secondary); max-width: 600px; margin-left: auto; margin-right: auto; line-height: 1.5;">
                เชื่อมต่อระบบ Realtime Dispatcher แล้ว • กำลังเฝ้าระวังเคสความดัน/น้ำตาลวิกฤต และภาวะฉุกเฉินตลอด 24 ชม.
            </p>
            <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; background: var(--bg-darker); padding: 6px 16px; border-radius: 50px; box-shadow: var(--neumorph-inset); border: 1px solid var(--border-color, transparent);">
                <div class="pulsing-dot" style="width: 10px; height: 10px;"></div>
                <span style="color: var(--text-muted);">อัปเดตสตรีมข้อมูลสดล่าสุด:</span>
                <span id="last-ping-time" style="color: #10B981; font-weight: 800;">เชื่อมต่อแล้ว</span>
            </div>
        </div>

        <!-- Section: Active Critical Feeds Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 10px; color: var(--text-primary);">
                <span class="neu-disc-icon xs disc-blue">📡</span> 
                <span>รายการเคสวิกฤตที่ส่งเข้ามา (Live Emergency Feeds)</span>
            </h3>
            <span id="active-count-badge" class="tag-pill tag-success">0 เคสรอรับเรื่อง</span>
        </div>

        <!-- Alert Cards Grid -->
        <div id="alert-cards-container" class="alert-card-grid">
            <!-- Initial Empty State -->
            <div style="grid-column: 1/-1; text-align: center; padding: 48px 20px; background: var(--bg-card); border-radius: 24px; box-shadow: var(--neumorph-flat); border: 1px dashed var(--border-color, #CBD5E1);">
                <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                    <div class="neu-disc-icon lg disc-green" style="width: 60px; height: 60px; font-size: 28px;">
                        🛡️
                    </div>
                </div>
                <div style="font-size: 16px; font-weight: 800; color: var(--text-primary);">ยังไม่มีเคสวิกฤตฉุกเฉินในขณะนี้</div>
                <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">เมื่อ อสม. คัดกรองพบเคสวิกฤต สัญญาณและข้อมูลจะเด้งขึ้นมาพร้อมเสียงเตือนทันที</div>
            </div>
        </div>
    </main>

    <!-- Fullscreen Emergency Pop-up Modal (Modern Clean Glassmorphism) -->
    <div id="emergency-popup-overlay">
        <div class="emergency-modal-card">
            <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                <div class="neu-disc-icon" style="width: 76px; height: 76px; min-width: 76px; background: radial-gradient(circle at 35% 35%, #EF4444 0%, #DC2626 70%, #991B1B 100%); color: #fff; border: 3px solid rgba(255, 255, 255, 0.9); box-shadow: inset 2px 2px 4px rgba(255,255,255,0.7), 0 8px 24px rgba(220,38,38,0.45); display: flex; align-items: center; justify-content: center; animation: emergencyBeaconPulse 1.8s infinite;">
                    <svg width="54" height="54" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: block; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.35));">
                        <line x1="24" y1="3" x2="24" y2="8" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="10" y1="8" x2="14" y2="12" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="38" y1="8" x2="34" y2="12" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="3" y1="22" x2="8" y2="22" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="45" y1="22" x2="40" y2="22" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <path d="M14 26 C14 15, 34 15, 34 26 Z" fill="#FFFFFF"/>
                        <path d="M28 18 C31 20, 32 23, 31 25" stroke="rgba(220, 38, 38, 0.65)" stroke-width="2.2" stroke-linecap="round" fill="none"/>
                        <rect x="10" y="26" width="28" height="5" rx="2.5" fill="#1E293B"/>
                        <text x="24" y="42" text-anchor="middle" font-family="'Outfit', 'Sarabun', sans-serif" font-weight="900" font-size="11.5" fill="#FFFFFF" letter-spacing="1.2">SOS</text>
                    </svg>
                </div>
            </div>

            <div class="tag-pill tag-danger" style="margin-bottom: 8px; font-size: 13.5px; padding: 6px 16px;">
                <span class="neu-disc-icon xs disc-red" style="width: 20px; height: 20px; font-size: 11px;">⚠️</span>
                <span>สัญญาณเตือนวิกฤตฉุกเฉิน (CRITICAL RED ALERT)</span>
            </div>
            
            <h2 id="modal-patient-name" style="margin: 8px 0 12px 0; font-size: 24px; font-weight: 900; color: #DC2626;">
                คุณ... (อายุ ... ปี)
            </h2>
            
            <div style="background: var(--bg-darker); border-radius: 18px; padding: 16px; margin-bottom: 18px; border: 1.5px solid rgba(220, 38, 38, 0.35); text-align: left; box-shadow: var(--neumorph-inset);">
                <!-- Time of Alert Header in Modal -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px dashed var(--border-color, rgba(0,0,0,0.1));">
                    <span style="font-size: 12px; color: var(--text-muted); font-weight: 700;">เวลาที่แจ้งเข้ามา:</span>
                    <span id="modal-alert-time" style="font-size: 13px; font-weight: 900; color: #DC2626; background: rgba(220, 38, 38, 0.12); padding: 3px 10px; border-radius: 8px;">-</span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div style="background: var(--bg-card); padding: 10px 12px; border-radius: 14px; box-shadow: var(--neumorph-flat);">
                        <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                            <span class="neu-disc-icon xs disc-red" style="width: 22px; height: 22px; font-size: 11px;">🩺</span>
                            <span>ความดันโลหิต</span>
                        </div>
                        <div id="modal-bp-val" style="font-size: 22px; font-weight: 900; color: #DC2626;">-</div>
                    </div>
                    <div style="background: var(--bg-card); padding: 10px 12px; border-radius: 14px; box-shadow: var(--neumorph-flat);">
                        <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                            <span class="neu-disc-icon xs disc-yellow" style="width: 22px; height: 22px; font-size: 11px;">🩸</span>
                            <span>น้ำตาล DTX</span>
                        </div>
                        <div id="modal-dtx-val" style="font-size: 22px; font-weight: 900; color: #D97706;">-</div>
                    </div>
                </div>
                <div style="font-size: 13px; margin-bottom: 6px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <span class="neu-disc-icon xs disc-blue" style="width: 22px; height: 22px; font-size: 11px;">📍</span>
                    <div><strong>ที่อยู่:</strong> <span id="modal-address">-</span></div>
                </div>
                <div style="font-size: 13px; margin-bottom: 6px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <span class="neu-disc-icon xs disc-red" style="width: 22px; height: 22px; font-size: 11px;">⚠️</span>
                    <div><strong>ภาวะวิกฤต:</strong> <span id="modal-crisis-type" style="color: #DC2626; font-weight: 800;">-</span></div>
                </div>
                <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                    <span class="neu-disc-icon xs disc-green" style="width: 22px; height: 22px; font-size: 11px;">👩‍⚕️</span>
                    <div><strong>อสม. ผู้แจ้ง:</strong> <span id="modal-vhv-info">-</span></div>
                </div>

                <!-- Prominent Callback Phone Box -->
                <div style="background: rgba(16, 185, 129, 0.12); border: 1.5px solid #10B981; border-radius: 14px; padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="neu-disc-icon sm disc-green" style="font-size: 15px;">📱</span>
                        <div>
                            <div style="font-size: 11px; color: #059669; font-weight: 800;">เบอร์โทรติดต่อกลับด่วน:</div>
                            <div style="font-size: 16px; font-weight: 900; color: var(--text-primary); letter-spacing: 0.5px;">
                                <span id="modal-contact-phone">-</span> <span id="modal-contact-type" style="font-size: 11.5px; font-weight: normal; color: var(--text-secondary);"></span>
                            </div>
                        </div>
                    </div>
                    <a id="modal-btn-call" href="#" style="background: #10B981; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);">
                        <span>📞 โทรทันที</span>
                    </a>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button type="button" id="btn-modal-ack" onclick="acknowledgeCurrentAlert()" class="btn-action-glow">
                    <span class="neu-disc-icon xs" style="background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.4); box-shadow: none;">🔕</span>
                    <span>กดรับทราบเคส (หยุดเสียงไซเรน)</span>
                </button>
                <div style="display: flex; gap: 10px;">
                    <a id="modal-btn-map" href="#" target="_blank" style="flex: 1; padding: 12px; background: #2563EB; color: white; text-decoration: none; border-radius: 14px; font-weight: 800; font-size: 13.5px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);">
                        <span class="neu-disc-icon xs disc-blue">🗺️</span>
                        <span>เปิดแผนที่ GPS</span>
                    </a>
                    <a id="modal-btn-refer" href="critical_referrals.php" onclick="openOrFocusTab(this.href, 'ncd_critical_referrals_tab'); return false;" style="flex: 1; padding: 12px; background: #10B981; color: white; text-decoration: none; border-radius: 14px; font-weight: 800; font-size: 13.5px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                        <span class="neu-disc-icon xs disc-green">🏥</span>
                        <span>สั่งส่งต่อ รพ. (JHCIS)</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Web Audio Siren Synthesizer & SSE Client -->
    <script>
        let currentHoscode = '<?= htmlspecialchars($selected_hoscode) ?>';
        let audioCtx = null;
        let sirenOscillator = null;
        let isSirenPlaying = false;
        let activeCrisisAlertId = null;

        // ----------------------------------------------------
        // Cross-Tab Navigator & Smart Focus Manager
        // ----------------------------------------------------
        const MY_TAB_NAME = "ncd_red_alert_station_tab";
        window.name = MY_TAB_NAME;

        function openOrFocusTab(url, targetTabName) {
            // 1. Post signal via BroadcastChannel to notify existing tab to focus & load url
            try {
                const bc = new BroadcastChannel('ncd_tab_channel');
                bc.postMessage({
                    action: 'focus_and_navigate',
                    target: targetTabName,
                    url: url,
                    timestamp: Date.now()
                });
            } catch(e) {}

            // 2. Write to localStorage for cross-tab fallback event
            try {
                localStorage.setItem('ncd_focus_tab_signal', JSON.stringify({
                    target: targetTabName,
                    url: url,
                    timestamp: Date.now()
                }));
            } catch(e) {}

            // 3. Open or focus the named window
            const targetWin = window.open(url, targetTabName);
            if (targetWin) {
                try {
                    targetWin.focus();
                } catch(e) {}
            }
        }

        // Setup incoming cross-tab focus listener
        (function setupCrossTabFocusListener() {
            function handleTabFocus(data) {
                if (!data || data.target !== MY_TAB_NAME) return;
                try {
                    window.focus();
                } catch(e) {}

                if (data.url) {
                    const currentUrl = window.location.href;
                    const targetUrl = new URL(data.url, window.location.origin).href;
                    if (currentUrl !== targetUrl && data.url.indexOf('emergency_receiver.php') !== -1) {
                        window.location.href = data.url;
                    }
                }

                // Temporary title visual cue
                const originalTitle = document.title.replace(/⚡ \[สลับมาแท็บนี้\] /g, '');
                document.title = "⚡ [สลับมาแท็บนี้] " + originalTitle;
                setTimeout(() => {
                    document.title = originalTitle;
                }, 2500);
            }

            try {
                const bc = new BroadcastChannel('ncd_tab_channel');
                bc.onmessage = (event) => {
                    if (event.data && event.data.action === 'focus_and_navigate') {
                        handleTabFocus(event.data);
                    }
                };
            } catch(e) {}

            window.addEventListener('storage', (e) => {
                if (e.key === 'ncd_focus_tab_signal' && e.newValue) {
                    try {
                        const payload = JSON.parse(e.newValue);
                        if (Date.now() - payload.timestamp < 3000) {
                            handleTabFocus(payload);
                        }
                    } catch(err) {}
                }
            });
        })();

        // Formats alert timestamp to prominent Thai display with elapsed time indicator
        function formatAlertTimeThai(dateStr) {
            if (!dateStr) return { fullTime: '-', timeAgo: '', dateText: '' };
            const cleanStr = dateStr.replace(/-/g, '/');
            const alertDate = new Date(cleanStr);
            const now = new Date();
            const diffSec = Math.max(0, Math.floor((now - alertDate) / 1000));
            
            const hours = alertDate.getHours().toString().padStart(2, '0');
            const mins = alertDate.getMinutes().toString().padStart(2, '0');
            const timeFormatted = `${hours}:${mins} น.`;

            let timeAgo = '';
            if (diffSec < 45) {
                timeAgo = 'เมื่อสักครู่';
            } else if (diffSec < 3600) {
                timeAgo = `${Math.floor(diffSec / 60)} นาทีที่แล้ว`;
            } else if (diffSec < 86400) {
                timeAgo = `${Math.floor(diffSec / 3600)} ชม. ที่แล้ว`;
            } else {
                timeAgo = `${alertDate.getDate()}/${alertDate.getMonth()+1}`;
            }

            return {
                fullTime: timeFormatted,
                timeAgo: timeAgo,
                dateText: dateStr
            };
        }

        // Theme Toggle Functionality (Synced with main navbar)
        function updateThemeButtonUI(theme) {
            const sun = document.getElementById('theme-toggle-sun');
            const moon = document.getElementById('theme-toggle-moon');
            if (!sun || !moon) return;
            if (theme === 'dark') {
                sun.style.display = 'block';
                moon.style.display = 'none';
            } else {
                sun.style.display = 'none';
                moon.style.display = 'block';
            }
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateThemeButtonUI(next);
        }

        // Web Audio Siren Synthesizer
        function startSirenSound() {
            if (isSirenPlaying) return;
            try {
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }

                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();

                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(800, audioCtx.currentTime);
                
                // Modulate frequency for emergency siren sweep (800Hz <-> 1300Hz)
                const now = audioCtx.currentTime;
                for (let i = 0; i < 60; i++) {
                    osc.frequency.linearRampToValueAtTime(1300, now + (i * 0.8) + 0.4);
                    osc.frequency.linearRampToValueAtTime(750, now + (i * 0.8) + 0.8);
                }

                gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                osc.connect(gain);
                gain.connect(audioCtx.destination);

                osc.start();
                sirenOscillator = osc;
                isSirenPlaying = true;
            } catch (e) {
                console.warn('Audio play prevented:', e);
            }
        }

        function stopSirenSound() {
            if (sirenOscillator) {
                try {
                    sirenOscillator.stop();
                    sirenOscillator.disconnect();
                } catch(e) {}
                sirenOscillator = null;
            }
            isSirenPlaying = false;
        }

        function testAudio() {
            if (isSirenPlaying) {
                stopSirenSound();
                document.getElementById('btn-audio-toggle').innerHTML = '<span class="neu-disc-icon xs disc-blue">🔊</span><span>ทดสอบเสียงไซเรน</span>';
            } else {
                startSirenSound();
                document.getElementById('btn-audio-toggle').innerHTML = '<span class="neu-disc-icon xs disc-red">⏹️</span><span>หยุดเสียงทดสอบ</span>';
                setTimeout(() => {
                    if (isSirenPlaying && !activeCrisisAlertId) {
                        stopSirenSound();
                        document.getElementById('btn-audio-toggle').innerHTML = '<span class="neu-disc-icon xs disc-blue">🔊</span><span>ทดสอบเสียงไซเรน</span>';
                    }
                }, 3000);
            }
        }

        // Change Hoscode
        function changeHoscode(val) {
            window.location.href = `emergency_receiver.php?hoscode=${val}`;
        }

        // Render Alerts List
        function renderAlertCards(alerts) {
            const container = document.getElementById('alert-cards-container');
            const pendingCount = alerts.filter(a => a.alert_status === 'pending').length;
            
            const badge = document.getElementById('active-count-badge');
            badge.innerText = `${pendingCount} เคสรอรับเรื่อง`;
            badge.className = pendingCount > 0 ? 'tag-pill tag-danger' : 'tag-pill tag-success';

            // Check if there is any pending crisis
            const pendingCrisis = alerts.find(a => a.alert_status === 'pending');
            const statusHero = document.getElementById('status-hero');
            const statusIconContainer = document.getElementById('status-icon-container');

            if (pendingCrisis) {
                statusHero.classList.add('alerting');
                statusIconContainer.style.background = 'radial-gradient(circle at 35% 35%, #EF4444 0%, #DC2626 70%, #991B1B 100%)';
                statusIconContainer.style.animation = 'emergencyBeaconPulse 1.8s infinite';
                statusIconContainer.innerHTML = `
                    <svg width="52" height="52" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: block; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.35));">
                        <line x1="24" y1="3" x2="24" y2="8" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="10" y1="8" x2="14" y2="12" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="38" y1="8" x2="34" y2="12" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="3" y1="22" x2="8" y2="22" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="45" y1="22" x2="40" y2="22" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <path d="M14 26 C14 15, 34 15, 34 26 Z" fill="#FFFFFF"/>
                        <path d="M28 18 C31 20, 32 23, 31 25" stroke="rgba(220, 38, 38, 0.65)" stroke-width="2.2" stroke-linecap="round" fill="none"/>
                        <rect x="10" y="26" width="28" height="5" rx="2.5" fill="#1E293B"/>
                        <text x="24" y="42" text-anchor="middle" font-family="'Outfit', 'Sarabun', sans-serif" font-weight="900" font-size="11.5" fill="#FFFFFF" letter-spacing="1.2">SOS</text>
                    </svg>
                `;
                document.getElementById('status-headline').innerText = `⚠️ พบสัญญาณแจ้งเหตุวิกฤต! (${pendingCrisis.patient_name})`;
                document.getElementById('status-sub').innerText = `ค่าความดัน SBP ${pendingCrisis.sbp || '-'} / DBP ${pendingCrisis.dbp || '-'} | น้ำตาล DTX ${pendingCrisis.dtx || '-'} mg% • ต้องการการดูแลฉุกเฉินทันที`;
                document.getElementById('station-pulsing-dot').className = 'pulsing-dot active-crisis';

                // Trigger Fullscreen Pop-up Modal & Siren
                showEmergencyPopup(pendingCrisis);
                startSirenSound();
            } else {
                statusHero.classList.remove('alerting');
                statusIconContainer.style.background = 'radial-gradient(circle at 35% 35%, #34D399 0%, #10B981 70%, #047857 100%)';
                statusIconContainer.style.animation = 'none';
                statusIconContainer.innerHTML = `
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: block; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.25));">
                        <path d="M24 4 L40 10 V22 C40 32.5 33.2 41.8 24 45 C14.8 41.8 8 32.5 8 22 V10 L24 4 Z" fill="#FFFFFF"/>
                        <path d="M24 8 L36 12.5 V22 C36 30.5 30.8 38 24 40.8 C17.2 38 12 30.5 12 22 V12.5 L24 8 Z" fill="#E6FDF5"/>
                        <path d="M16 23 L22 29 L32 17" stroke="#059669" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                `;
                document.getElementById('status-headline').innerText = 'สถานีพร้อมรับสัญญาณฉุกเฉิน (Active Standby)';
                document.getElementById('status-sub').innerText = 'เชื่อมต่อระบบ Realtime Dispatcher แล้ว • กำลังเฝ้าระวังเคสความดัน/น้ำตาลวิกฤต และภาวะฉุกเฉินตลอด 24 ชม.';
                document.getElementById('station-pulsing-dot').className = 'pulsing-dot';
                
                hideEmergencyPopup();
                stopSirenSound();
            }

            if (alerts.length === 0) {
                container.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 48px 20px; background: var(--bg-card); border-radius: 24px; box-shadow: var(--neumorph-flat); border: 1px dashed var(--border-color, #CBD5E1);">
                        <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                            <div class="neu-disc-icon lg disc-green" style="width: 60px; height: 60px; font-size: 28px;">
                                🛡️
                            </div>
                        </div>
                        <div style="font-size: 16px; font-weight: 800; color: var(--text-primary);">ยังไม่มีเคสวิกฤตฉุกเฉินในขณะนี้</div>
                        <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">เมื่อ อสม. คัดกรองพบเคสวิกฤต สัญญาณและข้อมูลจะเด้งขึ้นมาพร้อมเสียงเตือนทันที</div>
                    </div>
                `;
                return;
            }

            let html = '';
            alerts.forEach(a => {
                const isPending = a.alert_status === 'pending';
                const timeInfo = formatAlertTimeThai(a.created_at);

                const statusTag = isPending 
                    ? `<span class="tag-pill tag-danger"><span class="neu-disc-icon xs disc-red" style="width:18px;height:18px;font-size:10px;">🚨</span> <span>รอรับเรื่องด่วน</span></span>`
                    : `<span class="tag-pill tag-warning"><span class="neu-disc-icon xs disc-yellow" style="width:18px;height:18px;font-size:10px;">⏳</span> <span>รับเรื่องแล้ว (${a.acknowledged_by || 'จนท.'})</span></span>`;

                const mapLink = (a.latitude && a.longitude)
                    ? `https://www.google.com/maps?q=${a.latitude},${a.longitude}`
                    : `https://www.google.com/maps/search/อำเภอตาลสุม+อุบลราชธานี`;

                html += `
                    <div class="alert-item-card ${a.alert_status}">
                        <div>
                            <!-- Top Info & Prominent Alert Time Header -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1.5px dashed var(--border-color, rgba(0,0,0,0.08));">
                                <div>
                                    <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 700;">รหัสเคส #${a.alert_id}</div>
                                    <h4 style="margin: 3px 0 0 0; font-size: 18px; font-weight: 900; color: var(--text-primary); letter-spacing: -0.2px;">
                                        ${a.patient_name} ${a.age ? `<span style="font-size: 14px; font-weight: 700; color: var(--text-secondary);">(${a.age} ปี)</span>` : ''}
                                    </h4>
                                </div>

                                <!-- Highly Visible Time Capsule -->
                                <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                    <div style="background: ${isPending ? 'rgba(220, 38, 38, 0.12)' : 'var(--bg-darker)'}; border: 1.5px solid ${isPending ? '#DC2626' : 'var(--border-color)'}; color: ${isPending ? '#DC2626' : 'var(--text-primary)'}; padding: 4px 10px; border-radius: 12px; font-weight: 900; font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px; box-shadow: var(--neumorph-flat);">
                                        <span class="neu-disc-icon xs ${isPending ? 'disc-red' : 'disc-blue'}" style="width: 20px; height: 20px; font-size: 10.5px;">🕒</span>
                                        <span>${timeInfo.fullTime}</span>
                                        <span style="font-size: 11px; opacity: 0.85; font-weight: 700;">(${timeInfo.timeAgo})</span>
                                    </div>
                                    ${statusTag}
                                </div>
                            </div>

                            <!-- Vital Signs Highlight Tiles -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: var(--bg-darker); border-radius: 16px; padding: 12px; margin-bottom: 14px; box-shadow: var(--neumorph-inset);">
                                <div style="background: var(--bg-card); padding: 10px 12px; border-radius: 14px; box-shadow: var(--neumorph-flat);">
                                    <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 3px;">
                                        <span class="neu-disc-icon xs disc-red" style="width: 20px; height: 20px; font-size: 10.5px;">🩺</span>
                                        <span>ความดันโลหิต</span>
                                    </div>
                                    <div style="font-size: 17px; font-weight: 900; color: ${a.sbp >= 180 ? '#DC2626' : '#10B981'};">
                                        ${a.sbp ? `${a.sbp}/${a.dbp}` : '-'} <span style="font-size: 11px; font-weight: 700; color: var(--text-muted);">mmHg</span>
                                    </div>
                                </div>
                                <div style="background: var(--bg-card); padding: 10px 12px; border-radius: 14px; box-shadow: var(--neumorph-flat);">
                                    <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 3px;">
                                        <span class="neu-disc-icon xs disc-yellow" style="width: 20px; height: 20px; font-size: 10.5px;">🩸</span>
                                        <span>น้ำตาล DTX</span>
                                    </div>
                                    <div style="font-size: 17px; font-weight: 900; color: ${a.dtx >= 300 ? '#DC2626' : '#D97706'};">
                                        ${a.dtx ? `${a.dtx}` : '-'} <span style="font-size: 11px; font-weight: 700; color: var(--text-muted);">mg%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Location & Symptoms Details -->
                            <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px; line-height: 1.45; display: flex; flex-direction: column; gap: 6px;">
                                <div style="display: flex; align-items: flex-start; gap: 8px;">
                                    <span class="neu-disc-icon xs disc-blue" style="width: 22px; height: 22px; font-size: 11px; margin-top: 1px;">📍</span>
                                    <div><strong>ที่อยู่:</strong> บ้านเลขที่ ${a.house_no || '-'} ม.${a.moo || '-'} (รพ.สต. ${a.hoscode})</div>
                                </div>
                                <div style="display: flex; align-items: flex-start; gap: 8px;">
                                    <span class="neu-disc-icon xs disc-red" style="width: 22px; height: 22px; font-size: 11px; margin-top: 1px;">⚠️</span>
                                    <div style="color: #DC2626; font-weight: 800;">${a.crisis_type} ${a.red_flags ? `(${a.red_flags})` : ''}</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="neu-disc-icon xs disc-green" style="width: 22px; height: 22px; font-size: 11px;">👩‍⚕️</span>
                                    <div style="color: var(--text-muted);"><strong>อสม. ผู้แจ้ง:</strong> ${a.vhv_name || '-'} ${a.vhv_phone ? `(${a.vhv_phone})` : ''}</div>
                                </div>
                            </div>

                            <!-- Callback Phone Highlight Pill -->
                            ${(a.contact_phone || a.vhv_phone) ? `
                                <div style="background: rgba(16, 185, 129, 0.12); border: 1.5px solid #10B981; border-radius: 14px; padding: 8px 12px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="neu-disc-icon xs disc-green" style="width: 24px; height: 24px; font-size: 12px;">📱</span>
                                        <div style="font-size: 12.5px; color: var(--text-primary);">
                                            <strong>${a.contact_phone || a.vhv_phone}</strong> <span style="font-size: 11px; color: var(--text-muted);">(${a.contact_type === 'relative' ? 'ญาติ/ผู้ป่วย' : 'อสม.'})</span>
                                        </div>
                                    </div>
                                    <a href="tel:${a.contact_phone || a.vhv_phone}" style="background: #10B981; color: white; text-decoration: none; padding: 5px 12px; border-radius: 8px; font-size: 11.5px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 3px 8px rgba(16, 185, 129, 0.35);">
                                        <span>📞 โทรออก</span>
                                    </a>
                                </div>
                            ` : ''}
                        </div>

                        <!-- Action Buttons Row -->
                        <div style="display: flex; gap: 8px; margin-top: 6px;">
                            ${isPending ? `
                                <button type="button" onclick="ackAlertById(${a.alert_id})" style="flex: 1.2; padding: 10px 12px; background: #DC2626; color: white; border: none; border-radius: 12px; font-weight: 800; font-size: 12.5px; cursor: pointer; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35); display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    <span class="neu-disc-icon xs" style="background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.4); box-shadow: none; width: 20px; height: 20px; font-size: 10.5px;">🔕</span>
                                    <span>รับเรื่อง</span>
                                </button>
                            ` : ''}
                            <a href="${mapLink}" target="_blank" style="flex: 1; padding: 10px 12px; background: var(--bg-card); color: var(--text-primary); text-align: center; text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 12.5px; border: 1px solid var(--border-color, #CBD5E1); box-shadow: var(--neumorph-flat); display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <span class="neu-disc-icon xs disc-blue" style="width: 20px; height: 20px; font-size: 10.5px;">🗺️</span>
                                <span>แผนที่</span>
                            </a>
                            <a href="critical_referrals.php?alert_id=${a.alert_id}" onclick="openOrFocusTab(this.href, 'ncd_critical_referrals_tab'); return false;" style="flex: 1; padding: 10px 12px; background: #2563EB; color: white; text-align: center; text-decoration: none; border-radius: 12px; font-weight: 800; font-size: 12.5px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35); display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <span class="neu-disc-icon xs" style="background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.4); box-shadow: none; width: 20px; height: 20px; font-size: 10.5px;">🏥</span>
                                <span>ส่งต่อ</span>
                            </a>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        function showEmergencyPopup(alert) {
            activeCrisisAlertId = alert.alert_id;
            const timeInfo = formatAlertTimeThai(alert.created_at);
            
            document.getElementById('modal-patient-name').innerText = `${alert.patient_name} ${alert.age ? `(อายุ ${alert.age} ปี)` : ''}`;
            document.getElementById('modal-alert-time').innerText = `🕒 ${timeInfo.fullTime} (${timeInfo.timeAgo})`;
            document.getElementById('modal-bp-val').innerText = alert.sbp ? `${alert.sbp}/${alert.dbp} mmHg` : '-';
            document.getElementById('modal-dtx-val').innerText = alert.dtx ? `${alert.dtx} mg/dL` : '-';
            document.getElementById('modal-address').innerText = `บ้านเลขที่ ${alert.house_no || '-'} หมู่ ${alert.moo || '-'} (รพ.สต. ${alert.hoscode})`;
            document.getElementById('modal-crisis-type').innerText = `${alert.crisis_type} ${alert.red_flags ? `• ${alert.red_flags}` : ''}`;
            document.getElementById('modal-vhv-info').innerText = `${alert.vhv_name || '-'} ${alert.vhv_phone ? `(${alert.vhv_phone})` : ''}`;

            const modalReferBtn = document.getElementById('modal-btn-refer');
            if (modalReferBtn) {
                modalReferBtn.href = `critical_referrals.php?alert_id=${alert.alert_id}`;
                modalReferBtn.target = 'ncd_critical_referrals_tab';
            }

            const contactPhone = alert.contact_phone || alert.vhv_phone || '';
            const contactType = (alert.contact_type === 'relative') ? 'เบอร์ญาติ/ผู้ป่วย' : 'เบอร์ อสม.';
            document.getElementById('modal-contact-phone').innerText = contactPhone || 'ไม่มีเบอร์';
            document.getElementById('modal-contact-type').innerText = contactPhone ? `(${contactType})` : '';
            const btnCall = document.getElementById('modal-btn-call');
            if (contactPhone) {
                btnCall.href = `tel:${contactPhone}`;
                btnCall.style.display = 'inline-flex';
            } else {
                btnCall.style.display = 'none';
            }

            if (alert.latitude && alert.longitude) {
                document.getElementById('modal-btn-map').href = `https://www.google.com/maps?q=${alert.latitude},${alert.longitude}`;
            } else {
                document.getElementById('modal-btn-map').href = `https://www.google.com/maps/search/อำเภอตาลสุม+อุบลราชธานี`;
            }

            document.getElementById('emergency-popup-overlay').style.display = 'flex';
        }

        function hideEmergencyPopup() {
            document.getElementById('emergency-popup-overlay').style.display = 'none';
            activeCrisisAlertId = null;
        }

        function acknowledgeCurrentAlert() {
            if (!activeCrisisAlertId) {
                hideEmergencyPopup();
                stopSirenSound();
                return;
            }
            ackAlertById(activeCrisisAlertId);
        }

        function ackAlertById(alertId) {
            const formData = new FormData();
            formData.append('action', 'acknowledge_alert');
            formData.append('alert_id', alertId);
            formData.append('staff_name', 'เจ้าหน้าที่ รพ.สต.');

            fetch('../api/emergency_alert.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    stopSirenSound();
                    hideEmergencyPopup();
                    fetchActiveAlerts();
                }
            })
            .catch(err => console.error(err));
        }

        // Live Fetch / Fallback Polling
        function fetchActiveAlerts() {
            fetch(`../api/emergency_alert.php?action=get_active_alerts&hoscode=${currentHoscode}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('last-ping-time').innerText = new Date().toLocaleTimeString('th-TH');
                        renderAlertCards(data.alerts || []);
                    }
                })
                .catch(err => console.error('Poll error:', err));
        }

        // Simulate Test Alert
        function simulateTestAlert() {
            const formData = new FormData();
            formData.append('action', 'trigger_alert');
            formData.append('hoscode', currentHoscode === 'ALL' ? '07758' : currentHoscode);
            formData.append('target_cid', '3340500123456');
            formData.append('patient_name', 'นายสมชาย ใจกล้า (เคสทดสอบสัญญาณ)');
            formData.append('age', '67');
            formData.append('house_no', '99/1');
            formData.append('moo', '3');
            formData.append('sub_district_code', '341601');
            formData.append('latitude', '15.4321');
            formData.append('longitude', '104.9876');
            formData.append('crisis_type', 'ht_crisis (ความดันโลหิตสูงวิกฤต)');
            formData.append('sbp', '215');
            formData.append('dbp', '120');
            formData.append('dtx', '340');
            formData.append('red_flags', 'ปวดศีรษะรุนแรง, ตาพร่ามัว, แขนขาอ่อนแรง');
            formData.append('vhv_name', 'อสม. สมศรี (ทดสอบ)');
            formData.append('vhv_phone', '089-123-4567');

            fetch('../api/emergency_alert.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    fetchActiveAlerts();
                } else {
                    alert('ข้อผิดพลาด: ' + res.message);
                }
            });
        }

        // Initialize Live Loop
        document.addEventListener('DOMContentLoaded', () => {
            // Apply current theme UI icon/label
            const savedTheme = localStorage.getItem('theme') || 'light';
            updateThemeButtonUI(savedTheme);

            fetchActiveAlerts();
            // Regular poll every 3.5 seconds
            setInterval(fetchActiveAlerts, 3500);

            // User gesture unlock for audio
            document.body.addEventListener('click', () => {
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (audioCtx && audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
            }, { once: true });
        });
    </script>
</body>
</html>
