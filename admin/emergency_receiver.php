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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚨 NCDs Red Alert Station - ศูนย์รับสัญญาณวิกฤตฉุกเฉิน</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --emergency-red: #DC2626;
            --emergency-dark: #0B0F19;
        }

        body {
            margin: 0;
            padding: 0;
            background: #090D16;
            color: #F8FAFC;
            font-family: 'Prompt', 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .station-header {
            background: #111827;
            border-bottom: 2px solid #1F2937;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
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

        .station-container {
            flex: 1;
            padding: 24px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }

        .status-hero {
            background: linear-gradient(135deg, #1F2937 0%, #111827 100%);
            border: 1.5px solid #374151;
            border-radius: 24px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }

        .status-hero.alerting {
            background: linear-gradient(135deg, #991B1B 0%, #450A0A 100%);
            border-color: #DC2626;
            box-shadow: 0 0 50px rgba(220, 38, 38, 0.5);
            animation: bg-flash 1s infinite alternate;
        }

        @keyframes bg-flash {
            0% { border-color: #DC2626; }
            100% { border-color: #F87171; }
        }

        .alert-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }

        .alert-item-card {
            background: #111827;
            border: 1.5px solid #374151;
            border-radius: 20px;
            padding: 20px;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .alert-item-card.pending {
            border-color: #DC2626;
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.12) 0%, #111827 100%);
            box-shadow: 0 8px 24px rgba(220, 38, 38, 0.25);
        }

        .alert-item-card.acknowledged {
            border-color: #F59E0B;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, #111827 100%);
        }

        /* Fullscreen Overlay Popup */
        #emergency-popup-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999999;
            background: rgba(11, 15, 25, 0.92);
            backdrop-filter: blur(15px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .emergency-modal-card {
            background: #1E293B;
            border: 3px solid #DC2626;
            border-radius: 28px;
            max-width: 580px;
            width: 100%;
            padding: 30px;
            box-shadow: 0 0 80px rgba(220, 38, 38, 0.6);
            color: white;
            text-align: center;
            animation: pop-bounce 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes pop-bounce {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .btn-action-glow {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            border: none;
            padding: 16px 28px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 6px 25px rgba(220, 38, 38, 0.5);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
        }

        .btn-action-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(220, 38, 38, 0.7);
        }

        .tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
        }

        .tag-danger { background: rgba(220, 38, 38, 0.2); color: #F87171; border: 1px solid #DC2626; }
        .tag-warning { background: rgba(245, 158, 11, 0.2); color: #FBBF24; border: 1px solid #F59E0B; }
        .tag-success { background: rgba(16, 185, 129, 0.2); color: #34D399; border: 1px solid #10B981; }
    </style>
</head>
<body>

    <!-- Station Top Bar -->
    <header class="station-header">
        <div class="station-title">
            <div id="station-pulsing-dot" class="pulsing-dot"></div>
            <div>
                <h1 style="margin: 0; font-size: 18px; font-weight: 900; display: flex; align-items: center; gap: 8px; color: #FFFFFF;">
                    <span>🚨 NCDs Red Alert Station</span>
                    <span style="font-size: 11px; background: #DC2626; color: white; padding: 2px 8px; border-radius: 6px;">STANDALONE</span>
                </h1>
                <div style="font-size: 12px; color: #94A3B8; margin-top: 2px;">
                    ศูนย์รับสัญญาณเคสวิกฤตฉุกเฉินประจำ รพ.สต. • เด้งเตือนสด Realtime 24 ชม.
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <!-- Health Center Selector -->
            <select id="select-hoscode" onchange="changeHoscode(this.value)" style="background: #1E293B; color: #F8FAFC; border: 1px solid #374151; padding: 8px 14px; border-radius: 12px; font-size: 13px; font-weight: 700; outline: none;">
                <option value="ALL">ทุก รพ.สต. (ภาพรวมอำเภอ)</option>
                <?php foreach ($hc_names as $code => $name): ?>
                    <option value="<?= $code ?>" <?= $selected_hoscode == $code ? 'selected' : '' ?>>
                        [<?= $code ?>] <?= $name ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="button" onclick="testAudio()" id="btn-audio-toggle" style="background: #1E293B; border: 1px solid #374151; color: #F8FAFC; padding: 8px 14px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer;">
                🔊 ทดสอบเสียงไซเรน
            </button>

            <button type="button" onclick="simulateTestAlert()" style="background: linear-gradient(135deg, #DC2626, #991B1B); border: none; color: white; padding: 8px 16px; border-radius: 12px; font-size: 13px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);">
                ⚡ จำลองยิงสัญญาณฉุกเฉิน
            </button>

            <a href="../tools/red_alert_station/NCDs_RedAlert_Station.exe" download style="background: linear-gradient(135deg, #10B981, #059669); color: white; text-decoration: none; padding: 8px 14px; border-radius: 12px; font-size: 13px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);">
                <span>📥 ดาวน์โหลดแอป (.exe)</span>
            </a>

            <a href="critical_referrals.php" style="background: #3B82F6; color: white; text-decoration: none; padding: 8px 14px; border-radius: 12px; font-size: 13px; font-weight: 800;">
                📋 บอร์ดจัดการเคสส่งต่อ
            </a>
        </div>
    </header>

    <!-- Main Live Container -->
    <main class="station-container">
        
        <!-- Live Status Hero Banner -->
        <div id="status-hero" class="status-hero">
            <div id="status-icon" style="font-size: 54px; margin-bottom: 12px;">🟢</div>
            <h2 id="status-headline" style="margin: 0 0 8px 0; font-size: 24px; font-weight: 900;">
                สถานีพร้อมรับสัญญาณฉุกเฉิน (Active Standby)
            </h2>
            <p id="status-sub" style="margin: 0; font-size: 14px; color: #94A3B8;">
                เชื่อมต่อระบบ Realtime Dispatcher แล้ว • กำลังเฝ้าระวังเคสความดัน/น้ำตาลวิกฤต และอาการ Stroke/STEMI
            </p>
            <div style="margin-top: 18px; display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; background: rgba(0,0,0,0.3); padding: 6px 14px; border-radius: 50px; border: 1px solid #374151;">
                <span>⏱️ สตรีมข้อมูลสดล่าสุด:</span>
                <span id="last-ping-time" style="color: #10B981; font-weight: 800;">เชื่อมต่อแล้ว</span>
            </div>
        </div>

        <!-- Section: Active Critical Feeds -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 17px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                <span>📡</span> รายการเคสวิกฤตที่ส่งเข้ามา (Live Emergency Feeds)
            </h3>
            <span id="active-count-badge" class="tag-pill tag-success">0 เคสรอรับเรื่อง</span>
        </div>

        <!-- Alert Cards Grid -->
        <div id="alert-cards-container" class="alert-card-grid">
            <!-- Dynamic Content -->
            <div style="grid-column: 1/-1; text-align: center; padding: 40px 20px; color: #64748B; background: #111827; border-radius: 20px; border: 1px dashed #374151;">
                <div style="font-size: 36px; margin-bottom: 10px;">🛡️</div>
                <div style="font-size: 15px; font-weight: 700;">ยังไม่มีเคสวิกฤตฉุกเฉินในขณะนี้</div>
                <div style="font-size: 13px; color: #475569; margin-top: 4px;">เมื่อ อสม. คัดกรองพบเคสวิกฤต สัญญาณจะเด้งขึ้นมาพร้อมเสียงเตือนทันที</div>
            </div>
        </div>
    </main>

    <!-- Fullscreen Emergency Pop-up Modal (Always-on-top style) -->
    <div id="emergency-popup-overlay">
        <div class="emergency-modal-card">
            <div style="font-size: 64px; animation: bounce 0.6s infinite alternate;">🚨</div>
            <div class="tag-pill tag-danger" style="margin-bottom: 8px; font-size: 14px; padding: 6px 14px;">
                ⚠️ สัญญาณเตือนวิกฤตฉุกเฉิน (CRITICAL RED ALERT)
            </div>
            
            <h2 id="modal-patient-name" style="margin: 6px 0 10px 0; font-size: 26px; font-weight: 900; color: #F87171;">
                คุณ... (อายุ ... ปี)
            </h2>
            
            <div style="background: rgba(0,0,0,0.35); border-radius: 16px; padding: 16px; margin-bottom: 18px; border: 1px solid rgba(220, 38, 38, 0.4); text-align: left;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <div style="font-size: 11.5px; color: #94A3B8;">🩺 ความดันโลหิต</div>
                        <div id="modal-bp-val" style="font-size: 22px; font-weight: 900; color: #F87171;">-</div>
                    </div>
                    <div>
                        <div style="font-size: 11.5px; color: #94A3B8;">🩸 น้ำตาล DTX</div>
                        <div id="modal-dtx-val" style="font-size: 22px; font-weight: 900; color: #FBBF24;">-</div>
                    </div>
                </div>
                <div style="font-size: 13px; margin-bottom: 6px;">
                    <strong>📍 ที่อยู่:</strong> <span id="modal-address">-</span>
                </div>
                <div style="font-size: 13px; margin-bottom: 6px;">
                    <strong>⚠️ ภาวะวิกฤต:</strong> <span id="modal-crisis-type" style="color: #F87171; font-weight: 800;">-</span>
                </div>
                <div style="font-size: 13px; color: #CBD5E1;">
                    <strong>👩‍⚕️ อสม. ผู้แจ้ง:</strong> <span id="modal-vhv-info">-</span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button type="button" id="btn-modal-ack" onclick="acknowledgeCurrentAlert()" class="btn-action-glow">
                    <span>🔕 กดรับทราบเคส (หยุดเสียงไซเรน)</span>
                </button>
                <div style="display: flex; gap: 10px;">
                    <a id="modal-btn-map" href="#" target="_blank" style="flex: 1; padding: 12px; background: #3B82F6; color: white; text-decoration: none; border-radius: 12px; font-weight: 800; font-size: 13.5px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                        🗺️ เปิดแผนที่ GPS นำทาง
                    </a>
                    <a id="modal-btn-refer" href="critical_referrals.php" style="flex: 1; padding: 12px; background: #10B981; color: white; text-decoration: none; border-radius: 12px; font-weight: 800; font-size: 13.5px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                        🏥 สั่งส่งต่อ รพ. (JHCIS)
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
        let eventSource = null;

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
                
                // Modulate frequency for emergency siren sweep (800Hz <-> 1200Hz)
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
                document.getElementById('btn-audio-toggle').innerText = '🔊 ทดสอบเสียงไซเรน';
            } else {
                startSirenSound();
                document.getElementById('btn-audio-toggle').innerText = '⏹️ หยุดเสียงทดสอบ';
                setTimeout(() => {
                    if (isSirenPlaying && !activeCrisisAlertId) {
                        stopSirenSound();
                        document.getElementById('btn-audio-toggle').innerText = '🔊 ทดสอบเสียงไซเรน';
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
            if (pendingCrisis) {
                document.getElementById('status-hero').classList.add('alerting');
                document.getElementById('status-icon').innerText = '🚨';
                document.getElementById('status-headline').innerText = `พบเคสวิกฤตฉุกเฉิน! (${pendingCrisis.patient_name})`;
                document.getElementById('status-sub').innerText = `ค่าความดัน SBP ${pendingCrisis.sbp || '-'} / DBP ${pendingCrisis.dbp || '-'} | น้ำตาล DTX ${pendingCrisis.dtx || '-'}`;
                document.getElementById('station-pulsing-dot').className = 'pulsing-dot active-crisis';

                // Trigger Fullscreen Pop-up Modal & Siren
                showEmergencyPopup(pendingCrisis);
                startSirenSound();
            } else {
                document.getElementById('status-hero').classList.remove('alerting');
                document.getElementById('status-icon').innerText = '🟢';
                document.getElementById('status-headline').innerText = 'สถานีพร้อมรับสัญญาณฉุกเฉิน (Active Standby)';
                document.getElementById('status-sub').innerText = 'เชื่อมต่อระบบ Realtime Dispatcher แล้ว • กำลังเฝ้าระวังเคสวิกฤต 24 ชม.';
                document.getElementById('station-pulsing-dot').className = 'pulsing-dot';
                
                hideEmergencyPopup();
                stopSirenSound();
            }

            if (alerts.length === 0) {
                container.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px 20px; color: #64748B; background: #111827; border-radius: 20px; border: 1px dashed #374151;">
                        <div style="font-size: 36px; margin-bottom: 10px;">🛡️</div>
                        <div style="font-size: 15px; font-weight: 700;">ยังไม่มีเคสวิกฤตฉุกเฉินในขณะนี้</div>
                        <div style="font-size: 13px; color: #475569; margin-top: 4px;">เมื่อ อสม. คัดกรองพบเคสวิกฤต สัญญาณจะเด้งขึ้นมาพร้อมเสียงเตือนทันที</div>
                    </div>
                `;
                return;
            }

            let html = '';
            alerts.forEach(a => {
                const isPending = a.alert_status === 'pending';
                const statusTag = isPending 
                    ? `<span class="tag-pill tag-danger">🚨 รอรับเรื่องด่วน</span>`
                    : `<span class="tag-pill tag-warning">⏳ รับทราบแล้ว (${a.acknowledged_by || 'จนท.'})</span>`;

                const mapLink = (a.latitude && a.longitude)
                    ? `https://www.google.com/maps?q=${a.latitude},${a.longitude}`
                    : `https://www.google.com/maps/search/อำเภอตาลสุม+อุบลราชธานี`;

                html += `
                    <div class="alert-item-card ${a.alert_status}">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <div>
                                <div style="font-size: 11px; color: #94A3B8;">รหัสเคส #${a.alert_id} • ${a.created_at}</div>
                                <h4 style="margin: 4px 0 0 0; font-size: 17px; font-weight: 900; color: #F8FAFC;">
                                    ${a.patient_name} ${a.age ? `(${a.age} ปี)` : ''}
                                </h4>
                            </div>
                            ${statusTag}
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: rgba(0,0,0,0.3); border-radius: 12px; padding: 10px; margin-bottom: 12px;">
                            <div>
                                <div style="font-size: 11px; color: #94A3B8;">ความดัน</div>
                                <div style="font-size: 16px; font-weight: 900; color: ${a.sbp >= 180 ? '#F87171' : '#34D399'};">
                                    ${a.sbp ? `${a.sbp}/${a.dbp}` : '-'}
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 11px; color: #94A3B8;">น้ำตาล DTX</div>
                                <div style="font-size: 16px; font-weight: 900; color: ${a.dtx >= 300 ? '#F87171' : '#FBBF24'};">
                                    ${a.dtx ? `${a.dtx} mg%` : '-'}
                                </div>
                            </div>
                        </div>

                        <div style="font-size: 12.5px; color: #CBD5E1; margin-bottom: 12px;">
                            <div>📍 บ้านเลขที่ ${a.house_no || '-'} ม.${a.moo || '-'} (รพ.สต. ${a.hoscode})</div>
                            <div style="color: #F87171; font-weight: 700; margin-top: 4px;">⚠️ ${a.crisis_type} ${a.red_flags ? `(${a.red_flags})` : ''}</div>
                            <div style="color: #94A3B8; margin-top: 4px;">👩‍⚕️ อสม. ${a.vhv_name || '-'} ${a.vhv_phone ? `(${a.vhv_phone})` : ''}</div>
                        </div>

                        <div style="display: flex; gap: 8px;">
                            ${isPending ? `
                                <button type="button" onclick="ackAlertById(${a.alert_id})" style="flex: 1; padding: 10px; background: #DC2626; color: white; border: none; border-radius: 10px; font-weight: 800; font-size: 12.5px; cursor: pointer;">
                                    🔕 รับเรื่อง
                                </button>
                            ` : ''}
                            <a href="${mapLink}" target="_blank" style="flex: 1; padding: 10px; background: #1E293B; color: #94A3B8; text-align: center; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 12.5px; border: 1px solid #374151;">
                                🗺️ แผนที่
                            </a>
                            <a href="critical_referrals.php?alert_id=${a.alert_id}" style="flex: 1; padding: 10px; background: #3B82F6; color: white; text-align: center; text-decoration: none; border-radius: 10px; font-weight: 800; font-size: 12.5px;">
                                🏥 ส่งต่อ
                            </a>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        function showEmergencyPopup(alert) {
            activeCrisisAlertId = alert.alert_id;
            document.getElementById('modal-patient-name').innerText = `${alert.patient_name} ${alert.age ? `(อายุ ${alert.age} ปี)` : ''}`;
            document.getElementById('modal-bp-val').innerText = alert.sbp ? `${alert.sbp}/${alert.dbp} mmHg` : '-';
            document.getElementById('modal-dtx-val').innerText = alert.dtx ? `${alert.dtx} mg/dL` : '-';
            document.getElementById('modal-address').innerText = `บ้านเลขที่ ${alert.house_no || '-'} หมู่ ${alert.moo || '-'} (รพ.สต. ${alert.hoscode})`;
            document.getElementById('modal-crisis-type').innerText = `${alert.crisis_type} ${alert.red_flags ? `• ${alert.red_flags}` : ''}`;
            document.getElementById('modal-vhv-info').innerText = `${alert.vhv_name || '-'} ${alert.vhv_phone ? `(${alert.vhv_phone})` : ''}`;

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
