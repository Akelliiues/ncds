<?php
// admin/messages.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_title = function_exists('get_admin_title') ? get_admin_title() : 'ผู้ดูแลระบบ';
$is_super_admin = !empty($_SESSION['is_super_admin']);
$hc_names = function_exists('get_health_units') ? get_health_units() : [];

$tambons = [];
try {
    $stmt = $pdo->query("SELECT sub_district_code, CONCAT('ตำบล', sub_district_name) FROM sub_districts ORDER BY sub_district_code ASC");
    $tambons = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (\Exception $e) {
    $tambons = [
        '341801' => 'ตำบลตาลสุม',
        '341802' => 'ตำบลสำโรง',
        '341803' => 'ตำบลจิกเทิง',
        '341804' => 'ตำบลหนองกุง',
        '341805' => 'ตำบลนาคาย',
        '341806' => 'ตำบลคำหว้า'
    ];
}

// Fetch all messages
$messages = [];
try {
    $sql = "
        SELECT m.*, 
               (SELECT COUNT(*) FROM system_message_reads r WHERE r.message_id = m.message_id) AS read_count
        FROM system_messages m
        ORDER BY m.created_at DESC
        LIMIT 100
    ";
    $messages = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $messages = [];
}

// Stats
$totalMessages = count($messages);
$urgentCount = 0;
$vhvTargetCount = 0;
$staffTargetCount = 0;
foreach ($messages as $m) {
    if ($m['priority'] === 'urgent' || $m['priority'] === 'emergency') $urgentCount++;
    if ($m['target_type'] === 'all_vhv') $vhvTargetCount++;
    if ($m['target_type'] === 'all_staff') $staffTargetCount++;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ศูนย์ข้อความ & ประกาศ - NCDs Portal อำเภอ<?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .msg-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .msg-grid {
                grid-template-columns: 1fr;
            }
        }
        .preset-btn {
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .preset-btn:hover {
            border-color: var(--color-primary);
            color: var(--color-primary);
            transform: translateY(-1px);
        }
        .msg-card-item {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 16px;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s;
        }
        .msg-card-item:hover {
            border-color: var(--color-primary);
        }
        .stat-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 16px;
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
    </style>
</head>
<body class="admin-body">
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <!-- Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
            <div>
                <h2 style="color: var(--color-accent); margin: 0; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    📢 ศูนย์ข้อความ & การแจ้งเตือนประกาศ
                </h2>
                <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 14px;">
                    ส่งข่าวสาร ประกาศรณรงค์ และแจ้งเตือนด่วนไปยัง อสม. และเจ้าหน้าที่ รพ.สต. ทั่วอำเภอ
                </p>
            </div>
            <div>
                <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: var(--color-primary); border: 1px solid rgba(59, 130, 246, 0.3); padding: 6px 14px; font-size: 13px;">
                    ผู้ส่ง: <?= htmlspecialchars($admin_title) ?>
                </span>
            </div>
        </div>

        <!-- Top Statistics -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 24px;">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3B82F6;">📢</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">ข้อความประกาศทั้งหมด</div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--text-primary);"><?= number_format($totalMessages) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">🩺</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">ส่งถึง อสม.</div>
                    <div style="font-size: 22px; font-weight: 800; color: #10B981;"><?= number_format($vhvTargetCount) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">🏥</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">ส่งถึง เจ้าหน้าที่</div>
                    <div style="font-size: 22px; font-weight: 800; color: #F59E0B;"><?= number_format($staffTargetCount) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #EF4444;">🚨</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">ด่วน / วิกฤต</div>
                    <div style="font-size: 22px; font-weight: 800; color: #EF4444;"><?= number_format($urgentCount) ?></div>
                </div>
            </div>
        </div>

        <div class="msg-grid">
            <!-- Left: Create Announcement Form -->
            <div class="card-dark" style="padding: 20px; background: var(--bg-card); border-radius: 20px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color);">
                <h3 style="margin-top: 0; margin-bottom: 16px; color: var(--color-accent); font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    ✍️ สร้างข้อความประกาศใหม่
                </h3>

                <!-- Quick Presets -->
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700; margin-bottom: 6px;">💡 ข้อความตัวอย่างสำเร็จรูป (แตะเพื่อกรอกอัตโนมัติ):</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                        <button type="button" class="preset-btn" onclick="applyPreset('dpac_r2')">📢 รณรงค์ DPAC รอบ 2</button>
                        <button type="button" class="preset-btn" onclick="applyPreset('monthly_due')">⏰ เตือนส่งคัดกรองประจำเดือน</button>
                        <button type="button" class="preset-btn" onclick="applyPreset('critical_bp')">🚨 เตือนความดันวิกฤต</button>
                        <button type="button" class="preset-btn" onclick="applyPreset('sleep_1n')">😴 สุขภาพการนอน 1น.</button>
                    </div>
                </div>

                <form id="sendMessageForm" onsubmit="handleSendMessage(event)">
                    <div style="margin-bottom: 14px;">
                        <label class="form-label" style="font-weight: 700; color: var(--text-primary); font-size: 13.5px; display: block; margin-bottom: 6px;">
                            หัวข้อประกาศ / เรื่อง <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="title" id="msg_title" class="form-input-text" required placeholder="เช่น แจ้งเตือนรณรงค์ติดตามกลุ่มเสี่ยง DPAC รอบ 2" style="width: 100%; border-radius: 12px; padding: 10px 14px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 14px;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                        <div>
                            <label class="form-label" style="font-weight: 700; color: var(--text-primary); font-size: 13.5px; display: block; margin-bottom: 6px;">
                                กลุ่มเป้าหมายผู้รับ
                            </label>
                            <select name="target_type" id="msg_target_type" onchange="toggleTargetDetails(this.value)" style="width: 100%; border-radius: 12px; padding: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 13.5px; font-weight: 600;">
                                <option value="all">🌐 ทุกคนในระบบ</option>
                                <option value="all_vhv" selected>🩺 อสม. ทุกคน</option>
                                <option value="all_staff">🏥 เจ้าหน้าที่ รพ.สต. ทุกแห่ง</option>
                                <option value="hcode">📍 เฉพาะ รพ.สต. ที่ระบุ</option>
                                <option value="sub_district">🏘️ เฉพาะ ตำบล ที่ระบุ</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-weight: 700; color: var(--text-primary); font-size: 13.5px; display: block; margin-bottom: 6px;">
                                ระดับความสำคัญ
                            </label>
                            <select name="priority" id="msg_priority" style="width: 100%; border-radius: 12px; padding: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 13.5px; font-weight: 600;">
                                <option value="normal" selected>🟢 ปกติ (Normal)</option>
                                <option value="urgent">🟡 ด่วน (Urgent)</option>
                                <option value="emergency">🔴 ด่วนที่สุด (Emergency)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Target Health Center Selector -->
                    <div id="target_hcode_box" style="display: none; margin-bottom: 14px;">
                        <label class="form-label" style="font-weight: 700; color: var(--text-primary); font-size: 13px; display: block; margin-bottom: 6px;">เลือก รพ.สต. เป้าหมาย</label>
                        <select name="target_hcode" id="msg_target_hcode" style="width: 100%; border-radius: 12px; padding: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 13.5px;">
                            <option value="">-- เลือก รพ.สต. --</option>
                            <?php foreach ($hc_names as $code => $name): ?>
                                <option value="<?= htmlspecialchars($code) ?>">[<?= htmlspecialchars($code) ?>] <?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Target Sub-district Selector -->
                    <div id="target_sub_district_box" style="display: none; margin-bottom: 14px;">
                        <label class="form-label" style="font-weight: 700; color: var(--text-primary); font-size: 13px; display: block; margin-bottom: 6px;">เลือก ตำบล เป้าหมาย</label>
                        <select name="target_sub_district" id="msg_target_sub_district" style="width: 100%; border-radius: 12px; padding: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 13.5px;">
                            <option value="">-- เลือกตำบล --</option>
                            <?php foreach ($tambons as $code => $name): ?>
                                <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="form-label" style="font-weight: 700; color: var(--text-primary); font-size: 13.5px; display: block; margin-bottom: 6px;">
                            เนื้อหาข้อความ / รายละเอียดประกาศ <span style="color:#EF4444;">*</span>
                        </label>
                        <textarea name="message_body" id="msg_body" rows="5" required placeholder="กรอกเนื้อหาข้อความที่ต้องการประกาศหรือแจ้งเตือน..." style="width: 100%; border-radius: 12px; padding: 12px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 14px; line-height: 1.5; resize: vertical;"></textarea>
                    </div>

                    <button type="submit" id="btnSubmitMsg" class="btn-giant btn-giant-primary" style="margin: 0; padding: 14px; font-size: 15px; border-radius: 14px; width: 100%;">
                        🚀 ส่งข้อความประกาศทันที
                    </button>
                </form>
            </div>

            <!-- Right: Sent Broadcast History -->
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                    <h3 style="margin: 0; color: var(--color-accent); font-size: 18px; font-weight: 800;">
                        📋 ประวัติการส่งประกาศ (ล่าสุด)
                    </h3>
                    <button type="button" onclick="window.location.reload()" style="background: none; border: none; color: var(--color-primary); font-size: 13px; font-weight: 700; cursor: pointer;">
                        🔄 รีเฟรช
                    </button>
                </div>

                <div style="display: flex; flex-direction: column; gap: 12px; max-height: 600px; overflow-y: auto; padding-right: 4px;">
                    <?php if (empty($messages)): ?>
                        <div class="msg-card-item" style="text-align: center; color: var(--text-muted); padding: 40px 16px;">
                            <div style="font-size: 32px; margin-bottom: 8px;">📭</div>
                            <div style="font-size: 15px; font-weight: 700;">ยังไม่มีประวัติการส่งข้อความ</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $m): ?>
                            <?php
                                $prioBadge = 'background:rgba(16,185,129,0.15); color:#10B981; border:1px solid rgba(16,185,129,0.3);';
                                $prioLabel = 'ปกติ';
                                if ($m['priority'] === 'urgent') {
                                    $prioBadge = 'background:rgba(245,158,11,0.15); color:#D97706; border:1px solid rgba(245,158,11,0.3);';
                                    $prioLabel = '⚠️ ด่วน';
                                } elseif ($m['priority'] === 'emergency') {
                                    $prioBadge = 'background:rgba(239,68,68,0.15); color:#DC2626; border:1px solid rgba(239,68,68,0.3);';
                                    $prioLabel = '🚨 ด่วนที่สุด';
                                }

                                $targetLabel = 'ทุกคนในระบบ';
                                if ($m['target_type'] === 'all_vhv') $targetLabel = 'อสม. ทุกคน';
                                elseif ($m['target_type'] === 'all_staff') $targetLabel = 'จนท. รพ.สต. ทุกแห่ง';
                                elseif ($m['target_type'] === 'hcode') $targetLabel = 'รพ.สต. ' . ($m['target_hcode'] ?? '');
                                elseif ($m['target_type'] === 'sub_district') $targetLabel = $tambons[$m['target_sub_district']] ?? 'ตำบลเป้าหมาย';
                            ?>
                            <div class="msg-card-item" id="msg-card-<?= $m['message_id'] ?>">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px;">
                                    <div style="font-size: 15px; font-weight: 800; color: var(--text-primary);">
                                        <?= htmlspecialchars($m['title']) ?>
                                    </div>
                                    <div>
                                        <span style="font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 10px; <?= $prioBadge ?>">
                                            <?= $prioLabel ?>
                                        </span>
                                    </div>
                                </div>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 0 0 10px 0; line-height: 1.4; white-space: pre-line;">
                                    <?= htmlspecialchars($m['message_body']) ?>
                                </p>
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: var(--text-muted); border-top: 1px dashed var(--border-color); padding-top: 8px;">
                                    <div>
                                        <span>🎯 ถึง: <strong><?= htmlspecialchars($targetLabel) ?></strong></span> • 
                                        <span>👁️ อ่านแล้ว: <strong><?= number_format($m['read_count']) ?></strong> คน</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span>🕒 <?= htmlspecialchars(substr($m['created_at'], 0, 16)) ?></span>
                                        <button type="button" onclick="deleteMessage(<?= $m['message_id'] ?>)" style="background: none; border: none; color: #EF4444; font-weight: 700; cursor: pointer; padding: 0 4px;" title="ลบข้อความ">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const presets = {
            dpac_r2: {
                title: 'แจ้งเตือนรณรงค์ติดตามกลุ่มเสี่ยง DPAC รอบ 2',
                target_type: 'all_vhv',
                priority: 'urgent',
                body: 'ขอความร่วมมือ อสม. ทุกท่าน ร่วมลงพื้นที่ติดตามเยี่ยมบ้านและประเมินพฤติกรรมกลุ่มเสี่ยง DPAC รอบที่ 2 เพื่อติดตามผลค่าน้ำตาล ความดัน และคุณภาพการนอนหลับ 1น. ร่วมกันค่ะ'
            },
            monthly_due: {
                title: 'แจ้งเตือนส่งผลการคัดกรองสุขภาพประจำเดือน',
                target_type: 'all_vhv',
                priority: 'normal',
                body: 'เรียน อสม. ทุกท่าน ขอความกรุณาเร่งบันทึกผลการคัดกรองโรคเรื้อรัง (เบาหวาน/ความดัน) ประจำเดือนให้ครบถ้วน เพื่อให้ รพ.สต. ประมวลผลและวางแผนดูแลสุขภาพต่อไป'
            },
            critical_bp: {
                title: 'เฝ้าระวังผู้มีภาวะความดันโลหิตสูงวิกฤต (≥180/110 mmHg)',
                target_type: 'all',
                priority: 'emergency',
                body: 'หาก อสม. ลงพื้นที่และพบประชาชนมีค่าความดันตัวบน ≥180 หรือตัวล่าง ≥110 mmHg กรุณาประสานเจ้าหน้าที่ รพ.สต. หรือโทร 1669 ทันทีเพื่อความปลอดภัยของชาวบ้านค่ะ'
            },
            sleep_1n: {
                title: 'รณรงค์สำรวจและแนะนำสุขอนามัยการนอนหลับ (1น.)',
                target_type: 'all_vhv',
                priority: 'normal',
                body: 'การนอนหลับมีผลโดยตรงต่อการควบคุมความดันและน้ำตาล ขอให้ อสม. ชวนคุยประเมินพฤติกรรมการนอนหลับของชาวบ้าน (หลับสนิท / หลับๆ ตื่นๆ / หลับยาก) ในการคัดกรองทุกครั้งนะคะ'
            }
        };

        function applyPreset(key) {
            const p = presets[key];
            if (!p) return;
            document.getElementById('msg_title').value = p.title;
            document.getElementById('msg_target_type').value = p.target_type;
            document.getElementById('msg_priority').value = p.priority;
            document.getElementById('msg_body').value = p.body;
            toggleTargetDetails(p.target_type);
        }

        function toggleTargetDetails(val) {
            document.getElementById('target_hcode_box').style.display = (val === 'hcode') ? 'block' : 'none';
            document.getElementById('target_sub_district_box').style.display = (val === 'sub_district') ? 'block' : 'none';
        }

        function handleSendMessage(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitMsg');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = 'กำลังส่งข้อความ... ⌛';

            const form = document.getElementById('sendMessageForm');
            const formData = new FormData(form);
            formData.append('action', 'send_message');

            fetch('../api/messages.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('ส่งข้อความประกาศเรียบร้อยแล้ว!');
                    window.location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                    btn.disabled = false;
                    btn.innerText = originalText;
                }
            })
            .catch(err => {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + err);
                btn.disabled = false;
                btn.innerText = originalText;
            });
        }

        function deleteMessage(msgId) {
            if (!confirm('คุณต้องการลบข้อความประกาศนี้ใช่หรือไม่?')) return;

            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'delete_message', message_id: msgId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const card = document.getElementById('msg-card-' + msgId);
                    if (card) card.remove();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(err => alert('เชื่อมต่อล้มเหลว: ' + err));
        }
    </script>
</body>
</html>
