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
$is_super_admin = (!isset($admin_hoscode) || empty($admin_hoscode));
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

// Fetch messages based on role
$messages = [];
try {
    if ($is_super_admin) {
        // ผู้ดูแลระดับอำเภอและ Admin หลัก: มองเห็นประกาศทั้งหมดจากทุก รพ.สต. และส่วนกลาง
        $sql = "
            SELECT m.*, 
                   (SELECT COUNT(*) FROM system_message_reads r WHERE r.message_id = m.message_id) AS read_count
            FROM system_messages m
            ORDER BY m.created_at DESC
            LIMIT 100
        ";
        $messages = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // แอดมิน รพ.สต.: มองเห็นเฉพาะประกาศของ รพ.สต. ตนเอง และประกาศจากผู้รับผิดชอบระดับอำเภอ/แอดมินหลัก (ไม่เห็น รพ.สต. อื่น)
        $admin_username = $_SESSION['admin_username'] ?? '';
        $sql = "
            SELECT m.*, 
                   (SELECT COUNT(*) FROM system_message_reads r WHERE r.message_id = m.message_id) AS read_count
            FROM system_messages m
            WHERE (m.sender_hcode = :admin_hoscode)
               OR (m.sender_username = :admin_username)
               OR (
                   (m.sender_hcode IS NULL OR m.sender_role = 'super_admin')
                   AND (
                       m.target_type IN ('all', 'all_staff', 'all_vhv')
                       OR m.target_hcode = :admin_hoscode_target
                   )
               )
            ORDER BY m.created_at DESC
            LIMIT 100
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':admin_hoscode' => $admin_hoscode,
            ':admin_username' => $admin_username,
            ':admin_hoscode_target' => $admin_hoscode
        ]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
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
    if ($m['target_type'] === 'all_vhv' || ($m['target_type'] === 'hcode' && $m['target_hcode'] == $admin_hoscode)) $vhvTargetCount++;
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
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr);
            gap: 20px;
            align-items: start;
        }
        .msg-grid > div {
            min-width: 0;
        }
        @media (max-width: 960px) {
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
            padding: 14px 16px;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s;
            min-width: 0;
            box-sizing: border-box;
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
            <div style="display: flex; align-items: center; gap: 8px;">
                <button type="button" onclick="markAllAdminRead()" class="btn-dash-action" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--color-primary); padding: 7px 14px; border-radius: 12px; font-size: 13px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: var(--neumorph-flat);">
                    ✅ อ่านแล้วทั้งหมด
                </button>
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
                    <div id="stat-total-count" style="font-size: 22px; font-weight: 800; color: var(--text-primary);"><?= number_format($totalMessages) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">🩺</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">ส่งถึง อสม.</div>
                    <div id="stat-vhv-count" style="font-size: 22px; font-weight: 800; color: #10B981;"><?= number_format($vhvTargetCount) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">🏥</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">ส่งถึง เจ้าหน้าที่</div>
                    <div id="stat-staff-count" style="font-size: 22px; font-weight: 800; color: #F59E0B;"><?= number_format($staffTargetCount) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #EF4444;">🚨</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">ด่วน / วิกฤต</div>
                    <div id="stat-urgent-count" style="font-size: 22px; font-weight: 800; color: #EF4444;"><?= number_format($urgentCount) ?></div>
                </div>
            </div>
        </div>

        <div class="msg-grid">
            <!-- Left: Create Announcement Form -->
            <div class="card-dark" style="padding: 20px; background: var(--bg-card); border-radius: 20px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color);">
                <h3 style="margin-top: 0; margin-bottom: 16px; color: var(--color-accent); font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    ✍️ สร้างข้อความประกาศใหม่
                </h3>

                <!-- Quick Categorized Presets Dropdown Selector -->
                <div style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 14px; padding: 12px 14px; margin-bottom: 16px;">
                    <div style="font-size: 13px; color: var(--text-primary); font-weight: 800; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                        <span>💡 ชุดข้อความตัวอย่างสำเร็จรูป (เลือกเพื่อกรอกอัตโนมัติ):</span>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <!-- Dropdown 1: หมวดหมู่ -->
                        <div>
                            <label style="font-size: 11.5px; font-weight: 700; color: var(--text-secondary); display: block; margin-bottom: 4px;">
                                1. เลือกหมวดหมู่ข่าว
                            </label>
                            <select id="preset_category" onchange="onPresetCategoryChange(this.value)" style="width: 100%; border-radius: 10px; padding: 8px 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 12.5px; font-weight: 700;">
                                <option value="">-- เลือกหมวดหมู่ --</option>
                                <option value="screening">🩺 1. งานคัดกรอง NCDs & ติดตามกลุ่มเสี่ยง</option>
                                <option value="emergency">🚨 2. เฝ้าระวังภาวะฉุกเฉิน & ค่าวิกฤต</option>
                                <option value="behavior">😴 3. สุขอนามัย 3อ. 2ส. 1น. & ปรับพฤติกรรม</option>
                                <option value="gamification">🎁 4. แต้มสะสม รางวัล & ภารกิจ อสม.</option>
                                <option value="admin_train">📢 5. การประชุม อบรม & ธุรการ อสม.</option>
                            </select>
                        </div>

                        <!-- Dropdown 2: เทมเพลตข้อความ -->
                        <div>
                            <label style="font-size: 11.5px; font-weight: 700; color: var(--text-secondary); display: block; margin-bottom: 4px;">
                                2. เลือกหัวข้อตัวอย่าง
                            </label>
                            <select id="preset_template" onchange="onPresetTemplateChange(this.value)" disabled style="width: 100%; border-radius: 10px; padding: 8px 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 12.5px; font-weight: 600;">
                                <option value="">-- กรุณาเลือกหมวดหมู่ก่อน --</option>
                            </select>
                        </div>
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
                            <?php if ($is_super_admin): ?>
                                <select name="target_type" id="msg_target_type" onchange="toggleTargetDetails(this.value)" style="width: 100%; border-radius: 12px; padding: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 13.5px; font-weight: 600;">
                                    <option value="all">🌐 ทุกคนในระบบ</option>
                                    <option value="all_vhv" selected>🩺 อสม. ทุกคน (ทั้งอำเภอ)</option>
                                    <option value="all_staff">🏥 เจ้าหน้าที่ รพ.สต. ทุกแห่ง</option>
                                    <option value="hcode">📍 เฉพาะ รพ.สต. ที่ระบุ</option>
                                    <option value="sub_district">🏘️ เฉพาะ ตำบล ที่ระบุ</option>
                                </select>
                            <?php else: ?>
                                <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 12px; padding: 9px 12px; font-size: 13px; font-weight: 700; color: #2563EB; display: flex; align-items: center; gap: 6px; height: 42px; box-sizing: border-box;">
                                    <span>🩺</span>
                                    <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">อสม. ในเขตรับผิดชอบ</span>
                                </div>
                                <input type="hidden" name="target_type" id="msg_target_type" value="hcode">
                                <input type="hidden" name="target_hcode" id="msg_target_hcode" value="<?= htmlspecialchars($admin_hoscode) ?>">
                            <?php endif; ?>
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

                    <?php if ($is_super_admin): ?>
                        <!-- Target Health Center Selector (Super Admin Only) -->
                        <div id="target_hcode_box" style="display: none; margin-bottom: 14px;">
                            <label class="form-label" style="font-weight: 700; color: var(--text-primary); font-size: 13px; display: block; margin-bottom: 6px;">เลือก รพ.สต. เป้าหมาย</label>
                            <select name="target_hcode" id="msg_target_hcode" style="width: 100%; border-radius: 12px; padding: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 13.5px;">
                                <option value="">-- เลือก รพ.สต. --</option>
                                <?php foreach ($hc_names as $code => $name): ?>
                                    <option value="<?= htmlspecialchars($code) ?>">[<?= htmlspecialchars($code) ?>] <?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Target Sub-district Selector (Super Admin Only) -->
                        <div id="target_sub_district_box" style="display: none; margin-bottom: 14px;">
                            <label class="form-label" style="font-weight: 700; color: var(--text-primary); font-size: 13px; display: block; margin-bottom: 6px;">เลือก ตำบล เป้าหมาย</label>
                            <select name="target_sub_district" id="msg_target_sub_district" style="width: 100%; border-radius: 12px; padding: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 13.5px;">
                                <option value="">-- เลือกตำบล --</option>
                                <?php foreach ($tambons as $code => $name): ?>
                                    <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

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

                <div id="sent-history-list" style="display: flex; flex-direction: column; gap: 12px; max-height: 600px; overflow-y: auto; padding-right: 4px;">
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

                                // Sender Badge (ระบุผู้ส่งมุมล่างซ้าย)
                                $isSenderDistrict = ($m['sender_role'] === 'super_admin' || empty($m['sender_hcode']));
                                if ($isSenderDistrict) {
                                    $senderBadge = '<span style="background: rgba(124, 58, 237, 0.12); color: #7C3AED; border: 1px solid rgba(124, 58, 237, 0.25); font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 6px; display: inline-flex; align-items: center;">สสอ.ตาลสุม</span>';
                                } else {
                                    $hosDisplayName = $hc_names[$m['sender_hcode']] ?? ($m['sender_name'] ?: ('รพ.สต. ' . $m['sender_hcode']));
                                    $senderBadge = '<span style="background: rgba(13, 148, 136, 0.12); color: #0D9488; border: 1px solid rgba(13, 148, 136, 0.25); font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 6px; display: inline-flex; align-items: center;">' . htmlspecialchars($hosDisplayName) . '</span>';
                                }

                                // ข่าวเกิน 1 วัน ให้ยุบหัวข้อและเนื้อหาเหลือแถวเดียว พร้อมต่อท้ายด้วย ...
                                $isOlderThan1Day = (time() - strtotime($m['created_at'])) > 86400;
                            ?>
                            <div class="msg-card-item <?= $isOlderThan1Day ? 'msg-collapsed' : '' ?>" id="msg-card-<?= $m['message_id'] ?>" data-priority="<?= htmlspecialchars($m['priority']) ?>" data-target-type="<?= htmlspecialchars($m['target_type']) ?>" onclick="toggleMsgExpand(<?= $m['message_id'] ?>, event)" style="cursor: pointer;">
                                <!-- แถวที่ 1: หัวข้อข่าว (ถ้ายาวเกินแถวให้แทนด้วย ...) -->
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; min-width: 0;">
                                    <div class="msg-title-text" style="font-size: 14.5px; font-weight: 800; color: var(--text-primary); flex: 1; min-width: 0; line-height: 1.35; <?= $isOlderThan1Day ? 'white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' : '' ?>" title="<?= htmlspecialchars($m['title']) ?>">
                                        <?= htmlspecialchars($m['title']) ?>
                                    </div>
                                    <div style="flex-shrink: 0;">
                                        <span style="font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 10px; <?= $prioBadge ?>">
                                            <?= $prioLabel ?>
                                        </span>
                                    </div>
                                </div>
                                <!-- แถวที่ 2: ยุบเนื้อหาเหลือแถวเดียว (ถ้ายาวให้แทนที่ด้วย ...) -->
                                <div class="msg-body-text" style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px; line-height: 1.4; min-width: 0; <?= $isOlderThan1Day ? 'white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' : 'white-space: pre-line;' ?>" title="<?= $isOlderThan1Day ? htmlspecialchars($m['message_body']) : '' ?>">
                                    <?= htmlspecialchars($m['message_body']) ?>
                                </div>
                                <!-- แถวสุดท้ายของการ์ด: ไม่ต้องยุบ แสดงผู้ส่ง ผู้รับ วันเวลา และปุ่มลบครบถ้วน -->
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: var(--text-muted); border-top: 1px dashed rgba(13,44,84,0.12); padding-top: 8px; flex-wrap: wrap; gap: 8px; min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <?= $senderBadge ?>
                                        <span>ถึง: <strong><?= htmlspecialchars($targetLabel) ?></strong></span> • 
                                        <span>👁️ อ่านแล้ว: <strong><?= number_format($m['read_count']) ?></strong> คน</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px; margin-left: auto;">
                                        <span>🕒 <?= htmlspecialchars(substr($m['created_at'], 0, 16)) ?></span>
                                        <?php if ($is_super_admin): ?>
                                            <button type="button" onclick="event.stopPropagation(); deleteMessage(<?= $m['message_id'] ?>)" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #EF4444; border-radius: 8px; padding: 3px 9px; font-size: 11.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'" title="ลบข้อความประกาศนี้ (เฉพาะ Admin หลัก)">
                                                <span>🗑️</span>
                                            </button>
                                        <?php endif; ?>
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
        const categorizedPresets = {
            screening: {
                name: '🩺 1. งานคัดกรอง NCDs & ติดตามกลุ่มเสี่ยง',
                items: {
                    ncd_launch: {
                        name: '📢 รณรงค์เปิดคัดกรอง NCDs รอบใหม่',
                        title: 'รณรงค์เปิดการคัดกรองเบาหวาน-ความดันโลหิต รอบใหม่',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'เรียน อสม. ทุกท่าน ขอเชิญชวนร่วมรณรงค์ลงพื้นที่คัดกรองสุขภาพค้นหาโรคเบาหวานและความดันโลหิตในประชาชนกลุ่มเป้าหมายอายุ 35 ปีขึ้นไปในเขตรับผิดชอบ เพื่อการดูแลสุขภาพเชิงรุกและค้นหาผู้มีภาวะเสี่ยงตั้งแต่ระยะแรกเริ่มค่ะ'
                    },
                    dpac_r2: {
                        name: '🏃‍♂️ ติดตามประเมินพฤติกรรมกลุ่มเสี่ยง DPAC รอบ 2',
                        title: 'แจ้งเตือนรณรงค์ติดตามกลุ่มเสี่ยง DPAC รอบ 2',
                        target_type: 'all_vhv',
                        priority: 'urgent',
                        body: 'ขอความร่วมมือ อสม. ทุกท่าน ร่วมลงพื้นที่ติดตามเยี่ยมบ้านและประเมินพฤติกรรมกลุ่มเสี่ยง DPAC รอบที่ 2 เพื่อติดตามผลค่าน้ำตาล ความดัน และคุณภาพการนอนหลับ 1น. ร่วมกันค่ะ'
                    },
                    monthly_due: {
                        name: '⏰ เตือนเร่งรัดส่งผลคัดกรองประจำเดือน',
                        title: 'แจ้งเตือนส่งผลการคัดกรองสุขภาพประจำเดือน',
                        target_type: 'all_vhv',
                        priority: 'urgent',
                        body: 'เรียน อสม. ทุกท่าน ขอความกรุณาเร่งบันทึกผลการคัดกรองโรคเรื้อรัง (เบาหวาน/ความดัน) ประจำเดือนให้ครบถ้วน เพื่อให้ รพ.สต. ประมวลผลและวางแผนดูแลสุขภาพต่อไปค่ะ'
                    },
                    hba1c_check: {
                        name: '🩸 นัดตรวจค่าน้ำตาลสะสม (HbA1c) กลุ่มสงสัยป่วย',
                        title: 'นัดหมายประชาชนกลุ่มสงสัยป่วยเข้ารับการตรวจยืนยันค่าน้ำตาลสะสม (HbA1c)',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'ขอความอนุเคราะห์ อสม. ประสานแจ้งประชาชนกลุ่มสงสัยป่วยโรคเบาหวาน (ค่าน้ำตาลปลายนิ้ว ≥126 mg/dL) ให้เข้ารับการตรวจเลือดทางห้องปฏิบัติการ ณ รพ.สต. ในวันนัดหมาย โดยงดน้ำและอาหารหลัง 20.00 น. ก่อนวันตรวจค่ะ'
                    },
                    dpac_camp: {
                        name: '🎪 เชิญกลุ่มเสี่ยงร่วมกิจกรรม DPAC สัญจร',
                        title: 'ขอเชิญประชาชนกลุ่มเสี่ยงเข้าร่วมกิจกรรมปรับเปลี่ยนพฤติกรรมสุขภาพ DPAC สัญจร',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'รพ.สต. ขอเชิญ อสม. นำประชาชนกลุ่มเสี่ยงเบาหวานและความดันโลหิต เข้าร่วมอบรมปรับเปลี่ยนพฤติกรรมสุขภาพ "กินถูกสุข ลดเค็ม ออกกำลังกาย หลับสนิท" ณ ศาลาประชาคมหมู่บ้าน ในวันและเวลาที่กำหนดค่ะ'
                    }
                }
            },
            emergency: {
                name: '🚨 2. เฝ้าระวังภาวะฉุกเฉิน & ค่าวิกฤต',
                items: {
                    critical_bp: {
                        name: '🚨 เฝ้าระวังความดันโลหิตสูงวิกฤต (≥180/110 mmHg)',
                        title: 'เฝ้าระวังผู้มีภาวะความดันโลหิตสูงวิกฤต (≥180/110 mmHg)',
                        target_type: 'all',
                        priority: 'emergency',
                        body: 'หาก อสม. ลงพื้นที่และพบประชาชนมีค่าความดันตัวบน ≥180 หรือตัวล่าง ≥110 mmHg กรุณาให้นั่งพัก 15 นาทีแล้ววัดซ้ำ หากค่ายังสูงอยู่ให้ประสานเจ้าหน้าที่ รพ.สต. หรือโทร 1669 ทันทีเพื่อความปลอดภัยของชาวบ้านค่ะ'
                    },
                    critical_dtx: {
                        name: '🩸 เฝ้าระวังค่าน้ำตาลสูงวิกฤต / ภาวะน้ำตาลต่ำ',
                        title: 'เฝ้าระวังค่าน้ำตาลในเลือดสูงวิกฤต (≥200 mg/dL) และภาวะน้ำตาลต่ำเฉียบพลัน',
                        target_type: 'all',
                        priority: 'emergency',
                        body: 'หากพบผู้ป่วยหรือกลุ่มเสี่ยงมีค่าน้ำตาลปลายนิ้ว ≥200 mg/dL หรือมีอาการน้ำตาลต่ำ (เหงื่อแตก มือสั่น หน้ามืด ตาลาย สับสน ใจสั่น) ให้ดื่มน้ำหวานหรืออมลูกอมทันที และรีบประสานส่งต่อ รพ.สต. หรือ รพ.ตาลสุม โดยด่วนค่ะ'
                    },
                    stroke_fast: {
                        name: '🧠 สังเกตอาการเตือนโรคหลอดเลือดสมอง (FAST)',
                        title: 'เตือนภัยสัญญาณโรคหลอดเลือดสมองเฉียบพลัน (FAST)',
                        target_type: 'all_vhv',
                        priority: 'emergency',
                        body: 'ฝาก อสม. สังเกตอาการ F-A-S-T ในชุมชน: F (Face หน้าเบี้ยว มุมปากตก), A (Arm แขนขาอ่อนแรงยกไม่ขึ้น), S (Speech พูดไม่ชัด ลิ้นแข็ง), T (Time รีบโทร 1669 ทันทีภายใน 4.5 ชั่วโมง) เพื่อช่วยชีวิตและลดความพิการค่ะ'
                    },
                    chest_pain: {
                        name: '❤️ สัญญาณเตือนกล้ามเนื้อหัวใจขาดเลือด',
                        title: 'เฝ้าระวังอาการเจ็บแน่นหน้าอกรุนแรง (กล้ามเนื้อหัวใจขาดเลือด)',
                        target_type: 'all_vhv',
                        priority: 'emergency',
                        body: 'หากพบชาวบ้านมีอาการแน่นหน้าอกคล้ายของหนักทับ หายใจไม่ออก เจ็บร้าวไปที่กรามหรือแขนซ้าย เหงื่อแตก ห้ามปล่อยให้นอนพักเด็ดขาด ให้โทรเรียกรถพยาบาล 1669 ทันทีค่ะ'
                    }
                }
            },
            behavior: {
                name: '😴 3. สุขอนามัย 3อ. 2ส. 1น. & ปรับพฤติกรรม',
                items: {
                    sleep_1n: {
                        name: '😴 รณรงค์สุขอนามัยการนอนหลับ (1น.)',
                        title: 'รณรงค์สำรวจและแนะนำสุขอนามัยการนอนหลับ (1น.)',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'การนอนหลับมีผลโดยตรงต่อการควบคุมความดันและน้ำตาล ขอให้ อสม. ชวนคุยประเมินพฤติกรรมการนอนหลับของชาวบ้าน (หลับสนิท / หลับๆ ตื่นๆ / หลับยาก) ในการคัดกรองทุกครั้ง และแนะนำให้นอนก่อน 22.00 น. ไม่เล่นมือถือก่อนนอนนะคะ'
                    },
                    less_sweet_salt: {
                        name: '🥗 รณรงค์ลดหวาน มัน เค็ม ในมื้ออาหาร',
                        title: 'ขับเคลื่อนหมู่บ้านสุขภาวะ: ชวนชาวบ้าน ลดหวาน มัน เค็ม ตามหลัก 3อ. 2ส. 1น.',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'ขอความร่วมมือ อสม. ประชาสัมพันธ์และชวนชาวบ้านปรับปรุงอาหารในครัวเรือน ลดการใส่ผงชูรส น้ำปลา และน้ำตาล เน้นทานผักผลไม้รสไม่หวาน เพื่อสุขภาพไตและหลอดเลือดที่แข็งแรงค่ะ'
                    },
                    exercise_150min: {
                        name: '🏃‍♀️ ส่งเสริมการออกกำลังกาย 150 นาที/สัปดาห์',
                        title: 'ชวนคนตาลสุมขยับกาย ออกกำลังกายสะสมสัปดาห์ละ 150 นาที',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'การเดินเร็ว แกว่งแขน ปั่นจักรยาน หรือทำงานบ้านต่อเนื่องวันละ 30 นาที ช่วยลดระดับน้ำตาลและความดันได้อย่างมีประสิทธิภาพ ขอให้ อสม. ชวนชาวบ้านออกกำลังกายร่วมกันในหมู่บ้านค่ะ'
                    },
                    quit_smoke_alcohol: {
                        name: '🚭 ส่งเสริมการลด ละ เลิกบุหรี่และสุรา (2ส.)',
                        title: 'รณรงค์ส่งเสริมการลด ละ เลิกบุหรี่และเครื่องดื่มแอลกอฮอล์ (2ส.)',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'บุหรี่และสุราเป็นปัจจัยเสี่ยงสำคัญที่ทำให้หลอดเลือดแข็งตัวและเกิดภาวะแทรกซ้อนรุนแรง ขอให้ อสม. ชวนพูดคุยและแนะนำผู้ที่ต้องการเลิก สามารถขอรับคำปรึกษาได้ที่ รพ.สต. และสายด่วน 1600 ค่ะ'
                    }
                }
            },
            gamification: {
                name: '🎁 4. แต้มสะสม รางวัล & ภารกิจ อสม.',
                items: {
                    points_rewards: {
                        name: '🏆 สะสมแต้มคัดกรองแลกของรางวัล',
                        title: 'สะสมแต้มคะแนนไว้รอแลกของรางวัลกันนะคะ 💚',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'แต้มคัดกรองสำหรับการสะสมแลกของรางวัลจะเพิ่มเป็นเท่าตัวในแต่ละรอบ ยิ่งคัดกรองและติดตามครบถ้วน แต้มยิ่งสะสมได้มาก สามารถตรวจสอบแต้มได้ที่เมนู "ของรางวัล" ในระบบได้ตลอดเวลาค่ะ'
                    },
                    double_points_event: {
                        name: '⚡ กิจกรรมแต้มพิเศษ (Double Points) สัปดาห์นี้',
                        title: 'กิจกรรมพิเศษ! รับแต้มคัดกรอง x2 เมื่อบันทึกผลกลุ่มเสี่ยง DPAC ครบถ้วน',
                        target_type: 'all_vhv',
                        priority: 'urgent',
                        body: 'สัปดาห์นี้มีแคมเปญพิเศษ อสม. ที่ลงพื้นที่บันทึกผลการคัดกรองและติดตามกลุ่มเสี่ยง DPAC ครบถ้วนทุกข้อ จะได้รับคะแนนสะสมพิเศษ 2 เท่าทันที รีบชวนกันสะสมแต้มแลกของรางวัลชิ้นใหญ่นะคะ!'
                    },
                    vhv_leaderboard_star: {
                        name: '⭐ ประกาศเกียรติคุณ อสม. ยอดเยี่ยมประจำสัปดาห์',
                        title: 'ขอชื่นชมและแสดงความยินดีกับ อสม. ผลงานคัดกรองยอดเยี่ยมประจำสัปดาห์',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'สสอ. ตาลสุม และ รพ.สต. ขอขอบคุณพี่น้อง อสม. ทุกท่านที่ทุ่มเทปฏิบัติงานอย่างเข้มแข็ง ขอแสดงความยินดีกับ อสม. ที่มียอดคัดกรองสูงสุดติดอันดับ Leaderboard ประจำสัปดาห์นี้ค่ะ!'
                    },
                    claim_gift_box: {
                        name: '🎁 นัดรับของรางวัลและเปิดกล่องของขวัญ',
                        title: 'เปิดให้แลกรับของรางวัลและเปิดกล่องของขวัญ ณ รพ.สต.',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'อสม. ที่สะสมแต้มครบตามเกณฑ์ สามารถนำแต้มมาแลกของรางวัลสุขภาพ เช่น เสื้อกิลเลต์ อสม., เครื่องวัดความดัน, ของที่ระลึก ได้ที่ รพ.สต. ในวันประชุมประจำเดือนนะคะ'
                    }
                }
            },
            admin_train: {
                name: '📢 5. การประชุม อบรม & ธุรการ อสม.',
                items: {
                    monthly_meeting: {
                        name: '📅 นัดหมายประชุมประจำเดือน อสม.',
                        title: 'แจ้งนัดหมายการประชุมประจำเดือน อสม. ณ รพ.สต.',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'ขอเรียนเชิญ อสม. ทุกท่าน เข้าร่วมการประชุมประจำเดือน เพื่อรับทราบนโยบาย ติดตามงานคัดกรองสุขภาพ และแลกเปลี่ยนปัญหาการทำงานในพื้นที่ ณ ห้องประชุม รพ.สต. ในวันและเวลาที่นัดหมายค่ะ'
                    },
                    device_training: {
                        name: '🔧 อบรมทบทวนเทคนิคการใช้เครื่องมือตรวจสุขภาพ',
                        title: 'ขอเชิญ อสม. เข้ารับการอบรมทบทวนการใช้เครื่องวัดความดันและตรวจน้ำตาลปลายนิ้ว',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'รพ.สต. ขอเชิญ อสม. เข้าร่วมทบทวนเทคนิคการตรวจวัดความดันโลหิตที่ถูกต้อง การเจาะน้ำตาลปลายนิ้วอย่างปลอดภัย และการดูแลรักษาอุปกรณ์ เพื่อความแม่นยำในการคัดกรองสุขภาพชาวบ้านค่ะ'
                    },
                    compensation_report: {
                        name: '💵 แจ้งส่งเอกสารรายงานและเบิกค่าป่วยการ',
                        title: 'แจ้งกำหนดการส่งรายงานผลการปฏิบัติงาน อสม. เพื่อเบิกจ่ายค่าป่วยการ',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'ขอความกรุณา อสม. ทุกท่าน ตรวจสอบและส่งรายงานผลการปฏิบัติงานประจำเดือน (Smart อสม. / บันทึกคัดกรอง NCDs) ให้เรียบร้อยภายในวันที่ 25 ของเดือน เพื่อดำเนินการเบิกจ่ายค่าป่วยการตามระเบียบค่ะ'
                    },
                    app_update_tip: {
                        name: '📱 แนะนำฟีเจอร์ใหม่ในแอป NCDs Portal',
                        title: 'แนะนำการใช้งานฟีเจอร์ใหม่ในระบบ NCDs Portal สำหรับ อสม.',
                        target_type: 'all_vhv',
                        priority: 'normal',
                        body: 'ระบบ NCDs Portal ได้เพิ่มระบบบันทึกสุขภาพการนอน 1น., ระบบสะสมแต้มรางวัล และระบบแจ้งข่าวเตือนด่วน ขอให้ อสม. ทุกท่านเปิดใช้งานและสามารถสอบถามวิธีใช้งานเพิ่มเติมได้ที่เจ้าหน้าที่ รพ.สต. ค่ะ'
                    }
                }
            }
        };

        function onPresetCategoryChange(catKey) {
            const templateSelect = document.getElementById('preset_template');
            if (!templateSelect) return;

            templateSelect.innerHTML = '<option value="">-- เลือกหัวข้อตัวอย่าง --</option>';
            if (!catKey || !categorizedPresets[catKey]) {
                templateSelect.disabled = true;
                templateSelect.innerHTML = '<option value="">-- กรุณาเลือกหมวดหมู่ก่อน --</option>';
                return;
            }

            const items = categorizedPresets[catKey].items;
            for (const key in items) {
                const opt = document.createElement('option');
                opt.value = catKey + ':' + key;
                opt.textContent = items[key].name;
                templateSelect.appendChild(opt);
            }
            templateSelect.disabled = false;
        }

        function onPresetTemplateChange(val) {
            if (!val) return;
            const parts = val.split(':');
            const catKey = parts[0];
            const itemKey = parts[1];
            if (!categorizedPresets[catKey] || !categorizedPresets[catKey].items[itemKey]) return;

            const p = categorizedPresets[catKey].items[itemKey];
            document.getElementById('msg_title').value = p.title;

            const targetSelect = document.getElementById('msg_target_type');
            if (targetSelect && targetSelect.tagName === 'SELECT') {
                targetSelect.value = p.target_type;
                toggleTargetDetails(p.target_type);
            }

            document.getElementById('msg_priority').value = p.priority;
            document.getElementById('msg_body').value = p.body;
        }

        function toggleTargetDetails(val) {
            const hBox = document.getElementById('target_hcode_box');
            const sBox = document.getElementById('target_sub_district_box');
            if (hBox) hBox.style.display = (val === 'hcode') ? 'block' : 'none';
            if (sBox) sBox.style.display = (val === 'sub_district') ? 'block' : 'none';
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
            if (!confirm('คุณต้องการลบข้อความประกาศนี้ออกจากระบบใช่หรือไม่?\n(ข้อความจะถูกลบออกจากกล่องข้อความของผู้รับทุกคนทันที)')) return;

            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'delete_message', message_id: msgId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const card = document.getElementById('msg-card-' + msgId);
                    if (card) {
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            card.remove();
                            recalculateStats();
                        }, 300);
                    }
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(err => alert('เชื่อมต่อล้มเหลว: ' + err));
        }

        function recalculateStats() {
            const cards = document.querySelectorAll('.msg-card-item');
            let total = 0;
            let vhv = 0;
            let staff = 0;
            let urgent = 0;

            cards.forEach(card => {
                const priority = card.getAttribute('data-priority');
                const targetType = card.getAttribute('data-target-type');
                if (priority !== null || targetType !== null) {
                    total++;
                    if (targetType === 'all_vhv') vhv++;
                    if (targetType === 'all_staff') staff++;
                    if (priority === 'urgent' || priority === 'emergency') urgent++;
                }
            });

            const elTotal = document.getElementById('stat-total-count');
            const elVhv = document.getElementById('stat-vhv-count');
            const elStaff = document.getElementById('stat-staff-count');
            const elUrgent = document.getElementById('stat-urgent-count');

            if (elTotal) elTotal.innerText = total.toLocaleString();
            if (elVhv) elVhv.innerText = vhv.toLocaleString();
            if (elStaff) elStaff.innerText = staff.toLocaleString();
            if (elUrgent) elUrgent.innerText = urgent.toLocaleString();

            const historyList = document.getElementById('sent-history-list');
            if (historyList && total === 0) {
                historyList.innerHTML = `
                    <div class="msg-card-item" style="text-align: center; color: var(--text-muted); padding: 40px 16px;">
                        <div style="font-size: 32px; margin-bottom: 8px;">📭</div>
                        <div style="font-size: 15px; font-weight: 700;">ยังไม่มีประวัติการส่งข้อความ</div>
                    </div>
                `;
            }
        }

        function markAllAdminRead() {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'mark_all_read' })
            })
            .then(r => r.json())
            .then(() => {
                const badge = document.getElementById('admin-unread-badge');
                if (badge) badge.style.display = 'none';
            })
            .catch(() => {});
        }

        function toggleMsgExpand(msgId, e) {
            if (e && e.target.closest('button')) return; // Ignore click on delete button
            const card = document.getElementById('msg-card-' + msgId);
            if (!card) return;
            const titleEl = card.querySelector('.msg-title-text');
            const bodyEl = card.querySelector('.msg-body-text');
            if (!titleEl || !bodyEl) return;

            const isCollapsed = bodyEl.style.whiteSpace === 'nowrap' || card.classList.contains('msg-collapsed');
            if (isCollapsed) {
                // Expand
                card.classList.remove('msg-collapsed');
                titleEl.style.whiteSpace = 'normal';
                titleEl.style.overflow = 'visible';
                titleEl.style.textOverflow = 'clip';
                bodyEl.style.whiteSpace = 'pre-line';
                bodyEl.style.overflow = 'visible';
                bodyEl.style.textOverflow = 'clip';
            } else {
                // Collapse
                card.classList.add('msg-collapsed');
                titleEl.style.whiteSpace = 'nowrap';
                titleEl.style.overflow = 'hidden';
                titleEl.style.textOverflow = 'ellipsis';
                bodyEl.style.whiteSpace = 'nowrap';
                bodyEl.style.overflow = 'hidden';
                bodyEl.style.textOverflow = 'ellipsis';
            }
        }

        // Auto mark all incoming messages as read when admin enters messages.php
        document.addEventListener('DOMContentLoaded', () => {
            markAllAdminRead();
        });
    </script>
</body>
</html>
