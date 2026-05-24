<?php
// vhv/screening_form.php
session_start();

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$hid = $_GET['hid'] ?? '';
$cid = $_GET['cid'] ?? '';

if (empty($hid) && empty($cid)) {
    header("Location: scan.php");
    exit();
}

$vhvId = $_SESSION['vhv_id'];

// Fetch residents based on hid or cid
if (!empty($hid)) {
    $residentsStmt = $pdo->prepare("
        SELECT p.*, a.assignment_id
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        WHERE p.hid = ? AND a.vhv_id = ? AND a.budget_year = 2026 AND a.assignment_status = 'pending'
    ");
    $residentsStmt->execute([$hid, $vhvId]);
    $residents = $residentsStmt->fetchAll();

    if (empty($residents)) {
        $historyStmt = $pdo->prepare("
            SELECT p.*, a.assignment_status
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            WHERE p.hid = ? AND a.vhv_id = ? AND a.budget_year = 2026
        ");
        $historyStmt->execute([$hid, $vhvId]);
        $history = $historyStmt->fetchAll();
    }
} else {
    $residentsStmt = $pdo->prepare("
        SELECT p.*, a.assignment_id
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        WHERE p.cid = ? AND a.vhv_id = ? AND a.budget_year = 2026 AND a.assignment_status = 'pending'
    ");
    $residentsStmt->execute([$cid, $vhvId]);
    $residents = $residentsStmt->fetchAll();

    if (empty($residents)) {
        $historyStmt = $pdo->prepare("
            SELECT p.*, a.assignment_status
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            WHERE p.cid = ? AND a.vhv_id = ? AND a.budget_year = 2026
        ");
        $historyStmt->execute([$cid, $vhvId]);
        $history = $historyStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ฟอร์มคัดกรองโรคเรื้อรัง - อสม. ตาลสุม</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/app.js"></script>
    <style>
        .resident-card {
            background-color: var(--bg-card);
            border: none;
            border-radius: var(--border-radius);
            padding: 18px;
            margin-bottom: 16px;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all var(--transition-speed);
        }
        .resident-card.selected {
            box-shadow: var(--neumorph-inset);
            background-color: var(--bg-darker);
        }
        .step-section {
            display: none;
        }
        .step-section.active {
            display: block;
        }
        .form-label-big {
            font-size: 20px;
            font-weight: 800;
            color: var(--color-accent);
            margin-bottom: 16px;
            display: block;
        }
        .numpad-drawer {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%) translateY(100%);
            width: 100%;
            max-width: 480px;
            background-color: var(--bg-card);
            border: none;
            z-index: 2000;
            padding: 24px;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 -10px 40px rgba(13, 44, 84, 0.1);
            border-top-left-radius: 32px;
            border-top-right-radius: 32px;
        }
        .numpad-drawer.open {
            transform: translateX(-50%) translateY(0);
        }
        .numpad-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(13, 44, 84, 0.15);
            backdrop-filter: blur(4px);
            z-index: 1999;
            display: none;
        }

        /* Toggle groups grids */
        .toggle-group-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        .toggle-group-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        .toggle-group-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        .toggle-label {
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
        }
    </style>
</head>
<body class="vhv-accessibility">
    <div class="mobile-wrapper" style="padding-bottom: 100px;">
        <div class="vhv-header">
            <h3 style="color: var(--color-accent); margin: 0; font-size: 16px;">แบบคัดกรอง บ้านเลขที่ <?= htmlspecialchars($residents[0]['house_no'] ?? $history[0]['house_no'] ?? '') ?></h3>
            <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 14px;">รหัสบ้าน HID: <?= htmlspecialchars($hid) ?></p>
        </div>

        <?php if (empty($residents)): ?>
            <div class="card-dark" style="text-align: center; padding: 40px 20px;">
                <span style="font-size: 48px; display: block; margin-bottom: 16px;">✅</span>
                <h3 style="color: var(--color-green); font-size: 22px; margin-bottom: 8px;">คัดกรองเรียบร้อยแล้ว</h3>
                <p style="color: var(--text-secondary); margin-bottom: 24px;">สมาชิกทั้งหมดในบ้านเลขที่นี้ได้รับการคัดกรองเสร็จสิ้นเรียบร้อยแล้วในรอบปีงบประมาณนี้</p>
                <a href="index.php" class="btn-giant btn-giant-primary">กลับหน้าหลัก</a>
            </div>
        <?php else: ?>
            <form id="screening-form" action="" method="POST">
                <input type="hidden" name="assignment_id" id="assignment_id" value="">
                <input type="hidden" name="screening_lat" id="screening_lat" value="">
                <input type="hidden" name="screening_lng" id="screening_lng" value="">

                <!-- STEP 1: Select Resident -->
                <div id="step-resident" class="step-section active">
                    <span class="form-label-big">1. เลือกบุคคลที่ต้องการคัดกรอง</span>
                    <?php foreach ($residents as $r): ?>
                        <div class="resident-card" onclick="selectResident(<?= $r['assignment_id'] ?>, '<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>', '<?= $r['sex'] ?>', '<?= $r['birth'] ?>', <?= $r['need_screen_dm'] ? 'true' : 'false' ?>, <?= $r['need_screen_ht'] ? 'true' : 'false' ?>, <?= (float)($r['latitude'] ?? 0) ?>, <?= (float)($r['longitude'] ?? 0) ?>, this)">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong style="font-size: 18px; color: var(--text-primary);"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                                    <p style="margin: 4px 0 0 0; font-size: 14px; color: var(--text-secondary);">
                                        เพศ: <?= $r['sex'] == '1' ? 'ชาย' : 'หญิง' ?> • อายุ: <?= date_diff(date_create($r['birth']), date_create('today'))->y ?> ปี
                                    </p>
                                    <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-muted);">
                                        สิทธิ์การตรวจ: 
                                        <?= $r['need_screen_dm'] ? '<span style="color:var(--color-accent)">เบาหวาน</span>' : '<s>เบาหวาน (ตรวจแล้ว/ป่วยแล้ว)</s>' ?>
                                        •
                                        <?= $r['need_screen_ht'] ? '<span style="color:var(--color-primary)">ความดัน</span>' : '<s>ความดัน (ตรวจแล้ว/ป่วยแล้ว)</s>' ?>
                                    </p>
                                </div>
                                <span style="font-size: 24px; color: var(--border-color);" class="select-indicator">⚪</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <button type="button" onclick="nextStep('step-vital')" class="btn-giant btn-giant-primary" id="btn-next-resident" style="margin-top: 20px; display: none;">
                        ถัดไป (คัดกรองร่างกาย) →
                    </button>
                    
                    <button type="button" onclick="openSkipModal()" class="btn-giant btn-giant-secondary" style="margin-top: 12px;">
                        ไม่อยู่บ้าน / ทำนา (ข้ามเคส)
                    </button>
                </div>

                <!-- STEP 2: Vital Signs & Measurements (Consolidated) -->
                <div id="step-vital" class="step-section">
                    <div class="card-dark" style="padding: 16px; margin-bottom: 20px;">
                        <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">ชื่อผู้รับการคัดกรอง:</span>
                        <div id="selected-resident-name" style="font-size: 20px; font-weight: 800; color: var(--color-accent); margin-top: 4px;"></div>
                    </div>

                    <!-- GPS Mock Testing Tool -->
                    <div class="card-dark neumorph-flat" style="padding: 16px; margin-bottom: 20px; border: 1.5px dashed var(--color-primary); border-radius: var(--border-radius);">
                        <span style="color: var(--color-accent); font-size: 15px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                            🛰️ เครื่องมือจำลองพิกัด (GPS Mock System)
                        </span>
                        <p style="color: var(--text-secondary); font-size: 13px; margin: 4px 0 12px 0;">
                            ใช้สำหรับทดสอบการกดสิทธิ์อัตโนมัติ (Auto-Pass ใน 100 เมตร) หรือการตั้งแจ้งพิกัดคลาดเคลื่อน
                        </p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <button type="button" id="btn-gps-home" class="btn-gps-mock neumorph-inset" onclick="mockGps('home')" style="border: none; padding: 10px; border-radius: var(--border-radius); font-size: 14px; font-weight: 700; cursor: pointer; color: var(--color-green); background: var(--bg-darker); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; height: auto;">
                                <span>📍 ที่บ้านเป้าหมาย</span>
                                <small style="font-weight: normal; color: var(--text-muted); font-size: 11px;">(Auto-Pass <= 100ม.)</small>
                            </button>
                            <button type="button" id="btn-gps-drift" class="btn-gps-mock neumorph-flat" onclick="mockGps('drift')" style="border: none; padding: 10px; border-radius: var(--border-radius); font-size: 14px; font-weight: 700; cursor: pointer; color: var(--color-red); background: var(--bg-card); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; height: auto;">
                                <span>⚠️ นอกรัศมีบ้าน (Drift)</span>
                                <small style="font-weight: normal; color: var(--text-muted); font-size: 11px;">(รออนุมัติ > 100ม.)</small>
                            </button>
                        </div>
                        <div id="gps-status-info" style="margin-top: 10px; font-size: 12px; color: var(--text-secondary); text-align: center; font-weight: 600;">
                            พิกัดปัจจุบัน: รอโหลดจากระบบ...
                        </div>
                    </div>

                    <!-- Measurements (Scroll Picker) -->
                    <span class="form-label-big" style="font-size: 18px; margin-top: 10px;">📏 ข้อมูลร่างกาย</span>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 6px;">น้ำหนัก (กก.)</label>
                            <input type="text" name="weight" id="weight" class="input-large" readonly onclick="openScrollPicker('weight', 'น้ำหนัก (กก.)', 30, 150, 60.0)" placeholder="0.0">
                        </div>
                        <div>
                            <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 6px;">ส่วนสูง (ซม.)</label>
                            <input type="text" name="height" id="height" class="input-large" readonly onclick="openScrollPicker('height', 'ส่วนสูง (ซม.)', 100, 220, 160.0)" placeholder="0.0">
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 6px;">รอบเอว (นิ้ว)</label>
                        <input type="text" name="waist" id="waist" class="input-large" readonly onclick="openScrollPicker('waist', 'รอบเอว (นิ้ว)', 20, 60, 30.0)" placeholder="0.0">
                    </div>

                    <!-- BMI Auto-Display -->
                    <div class="neumorph-inset" style="padding: 20px; border-radius: var(--border-radius); margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="color: var(--text-secondary); font-size: 14px; font-weight: 600;">ค่าดัชนีมวลกาย (BMI)</span>
                            <div id="bmi-display" style="font-size: 26px; font-weight: 800; color: var(--color-primary); margin-top: 4px;">0.00</div>
                        </div>
                        <div id="bmi-status" class="badge" style="font-size: 14px; padding: 6px 12px; color: var(--text-secondary);">
                            รอป้อนข้อมูล
                        </div>
                    </div>

                    <!-- Blood Pressure section -->
                    <div id="section-bp" style="margin-bottom: 24px;">
                        <span style="color: var(--text-primary); font-size: 18px; font-weight: 800; display: block; margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">🩺 วัดความดันโลหิต</span>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <label style="font-size: 13px; color: var(--text-secondary);">ครั้งที่ 1 ตัวบน (SYS)</label>
                                <input type="text" name="sys_bp1" id="sys_bp1" class="input-large" readonly onclick="openNumPad('sys_bp1', 'ความดันตัวบน SYS1')" placeholder="0">
                            </div>
                            <div>
                                <label style="font-size: 13px; color: var(--text-secondary);">ครั้งที่ 1 ตัวล่าง (DIA)</label>
                                <input type="text" name="dia_bp1" id="dia_bp1" class="input-large" readonly onclick="openNumPad('dia_bp1', 'ความดันตัวล่าง DIA1')" placeholder="0">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label style="font-size: 13px; color: var(--text-secondary);">ครั้งที่ 2 ตัวบน (SYS)</label>
                                <input type="text" name="sys_bp2" id="sys_bp2" class="input-large" readonly onclick="openNumPad('sys_bp2', 'ความดันตัวบน SYS2')" placeholder="0">
                            </div>
                            <div>
                                <label style="font-size: 13px; color: var(--text-secondary);">ครั้งที่ 2 ตัวล่าง (DIA)</label>
                                <input type="text" name="dia_bp2" id="dia_bp2" class="input-large" readonly onclick="openNumPad('dia_bp2', 'ความดันตัวล่าง DIA2')" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <!-- Blood Sugar DTX section -->
                    <div id="section-dtx" style="margin-bottom: 24px;">
                        <span style="color: var(--text-primary); font-size: 18px; font-weight: 800; display: block; margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">🩸 วัดระดับน้ำตาลในเลือด (DTX)</span>
                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 13px; color: var(--text-secondary); display: block; margin-bottom: 6px;">สถานะเจาะเลือด</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                <label class="toggle-item toggle-green" style="height: 45px;">
                                    <input type="radio" name="dtx_type" value="fpg" checked>
                                    <span class="toggle-label" style="padding: 10px 4px; font-size: 14px;">งดน้ำ/อาหาร (FPG)</span>
                                </label>
                                <label class="toggle-item toggle-yellow" style="height: 45px;">
                                    <input type="radio" name="dtx_type" value="rpg">
                                    <span class="toggle-label" style="padding: 10px 4px; font-size: 14px;">ไม่ได้งดอาหาร (RPG)</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <input type="text" name="dtx_value" id="dtx_value" class="input-large" readonly onclick="openNumPad('dtx_value', 'ระดับน้ำตาล DTX')" placeholder="0">
                        </div>
                    </div>

                    <!-- Behavior Toggles (3อ. 2ส.) -->
                    <span class="form-label-big" style="font-size: 18px; margin-top: 10px; border-top: 1px solid var(--border-color); padding-top: 15px;">🥗 พฤติกรรมสุขภาพ (3อ. 2ส.)</span>

                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 8px;">🥬 อาหาร (เน้นรสหวาน มัน เค็ม)</label>
                        <div class="toggle-group-4">
                            <label class="toggle-item toggle-green">
                                <input type="radio" name="diet_risk" value="green" checked>
                                <span class="toggle-label"><span class="dot"></span>🟢 ปกติ</span>
                            </label>
                            <label class="toggle-item toggle-yellow">
                                <input type="radio" name="diet_risk" value="yellow">
                                <span class="toggle-label"><span class="dot"></span>🟡 ชอบหวาน</span>
                            </label>
                            <label class="toggle-item toggle-orange">
                                <input type="radio" name="diet_risk" value="orange">
                                <span class="toggle-label"><span class="dot"></span>🟠 ชอบมัน</span>
                            </label>
                            <label class="toggle-item toggle-red">
                                <input type="radio" name="diet_risk" value="red">
                                <span class="toggle-label"><span class="dot"></span>🔴 ชอบเค็ม/ปลาร้า</span>
                            </label>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 8px;">🏃‍♂️ การออกกำลังกาย</label>
                        <div class="toggle-group-2">
                            <label class="toggle-item toggle-green">
                                <input type="radio" name="exercise_risk" value="green" checked>
                                <span class="toggle-label"><span class="dot"></span>🟢 ถึง 150 นาที/สัปดาห์</span>
                            </label>
                            <label class="toggle-item toggle-red">
                                <input type="radio" name="exercise_risk" value="red">
                                <span class="toggle-label"><span class="dot"></span>🔴 ไม่ค่อยได้ออก</span>
                            </label>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 8px;">🧠 ระดับความเครียด</label>
                        <div class="toggle-group-3">
                            <label class="toggle-item toggle-green">
                                <input type="radio" name="stress_risk" value="green" checked>
                                <span class="toggle-label"><span class="dot"></span>🟢 น้อย/ไม่มี</span>
                            </label>
                            <label class="toggle-item toggle-yellow">
                                <input type="radio" name="stress_risk" value="yellow">
                                <span class="toggle-label"><span class="dot"></span>🟡 ปานกลาง</span>
                            </label>
                            <label class="toggle-item toggle-red">
                                <input type="radio" name="stress_risk" value="red">
                                <span class="toggle-label"><span class="dot"></span>🔴 เครียดสูง</span>
                            </label>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 8px;">🚬 การสูบบุหรี่</label>
                        <div class="toggle-group-3">
                            <label class="toggle-item toggle-green">
                                <input type="radio" name="smoking_risk" value="green" checked>
                                <span class="toggle-label"><span class="dot"></span>🟢 ไม่สูบ</span>
                            </label>
                            <label class="toggle-item toggle-yellow">
                                <input type="radio" name="smoking_risk" value="yellow">
                                <span class="toggle-label"><span class="dot"></span>🟡 เคยสูบแต่เลิกแล้ว</span>
                            </label>
                            <label class="toggle-item toggle-red">
                                <input type="radio" name="smoking_risk" value="red">
                                <span class="toggle-label"><span class="dot"></span>🔴 ยังสูบอยู่</span>
                            </label>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 8px;">🍺 การดื่มแอลกอฮอล์</label>
                        <div class="toggle-group-3">
                            <label class="toggle-item toggle-green">
                                <input type="radio" name="alcohol_risk" value="green" checked>
                                <span class="toggle-label"><span class="dot"></span>🟢 ไม่ดื่ม</span>
                            </label>
                            <label class="toggle-item toggle-yellow">
                                <input type="radio" name="alcohol_risk" value="yellow">
                                <span class="toggle-label"><span class="dot"></span>🟡 ดื่มนานๆ ครั้ง</span>
                            </label>
                            <label class="toggle-item toggle-red">
                                <input type="radio" name="alcohol_risk" value="red">
                                <span class="toggle-label"><span class="dot"></span>🔴 ดื่มประจำ</span>
                            </label>
                        </div>
                    </div>

                    <!-- Thai CV Risk Score Card -->
                    <div style="background-color: var(--bg-darker); border: 2px solid var(--border-color); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px; text-align: center;">
                        <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">ประเมินความเสี่ยงโรคหัวใจและหลอดเลือด (Thai CV Risk)</span>
                        <div id="cv-risk-display" style="font-size: 40px; font-weight: 800; color: var(--color-green); margin: 8px 0;">0.00%</div>
                        <div id="cv-risk-status" style="font-size: 15px; color: var(--text-secondary);">ความเสี่ยงต่ำมาก</div>
                    </div>

                    <div style="display: flex; gap: 12px; margin-top: 30px;">
                        <button type="button" onclick="nextStep('step-resident')" class="btn-giant btn-giant-secondary" style="flex: 1; margin-bottom: 0;">← ย้อนกลับ</button>
                        <button type="button" onclick="submitScreening()" class="btn-giant btn-giant-success" style="flex: 1; margin-bottom: 0; background: linear-gradient(135deg, var(--color-green), #059669); color: white;">บันทึกส่งงาน</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <!-- Skip Case Modal Overlay -->
        <div id="skip-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(13, 44, 84, 0.3); backdrop-filter: blur(4px); z-index: 3000; align-items: center; justify-content: center;">
            <div class="card-dark" style="width: 90%; max-width: 420px; margin: 0 auto; background: var(--bg-main); box-shadow: var(--neumorph-flat); border-radius: 28px; padding: 24px;">
                <h3 style="color: var(--color-accent); text-align: center; margin-bottom: 8px; font-size: 22px; font-weight: 800;">ข้ามเคสชั่วคราว</h3>
                <p style="color: var(--text-secondary); text-align: center; font-size: 14px; margin-bottom: 20px; line-height: 1.5;">ระบุเหตุผลที่ข้ามเคส (ยังได้ +1 คะแนนสะสม แต่อันดับใบงานจะพักรอคัดกรองใหม่)</p>
                
                <div class="skip-reasons-list" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px;">
                    <button type="button" class="btn-skip-reason neumorph-inset" onclick="selectSkipReason('ไปทำนา/ไปทำงานนอกบ้าน', this)" style="background: var(--bg-darker); color: var(--color-primary); border: none; padding: 16px; border-radius: var(--border-radius); text-align: left; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 12px; width: 100%; transition: all 0.2s;">
                        <span style="font-size: 20px;">🌾</span> ไปทำนา/ไปทำงานนอกบ้าน
                    </button>
                    <button type="button" class="btn-skip-reason neumorph-flat" onclick="selectSkipReason('ป่วยติดเตียง/ไม่สะดวกตรวจ', this)" style="background: var(--bg-card); color: var(--text-primary); border: none; padding: 16px; border-radius: var(--border-radius); text-align: left; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 12px; width: 100%; transition: all 0.2s;">
                        <span style="font-size: 20px;">🛏️</span> ป่วยติดเตียง/ไม่สะดวกตรวจ
                    </button>
                    <button type="button" class="btn-skip-reason neumorph-flat" onclick="selectSkipReason('เจ้าตัวปฏิเสธการตรวจ', this)" style="background: var(--bg-card); color: var(--text-primary); border: none; padding: 16px; border-radius: var(--border-radius); text-align: left; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 12px; width: 100%; transition: all 0.2s;">
                        <span style="font-size: 20px;">🔕</span> เจ้าตัวปฏิเสธการตรวจ
                    </button>
                </div>
                <input type="hidden" id="skip_reason" value="ไปทำนา/ไปทำงานนอกบ้าน">

                <div style="display: flex; gap: 12px;">
                    <button type="button" onclick="closeSkipModal()" class="btn-giant btn-giant-secondary" style="flex: 1; height: 50px; font-size: 16px; margin-bottom: 0; border-radius: var(--border-radius);">ยกเลิก</button>
                    <button type="button" onclick="submitSkipCase()" class="btn-giant btn-giant-primary" style="flex: 1; height: 50px; font-size: 16px; margin-bottom: 0; border-radius: var(--border-radius); background: var(--color-primary); color: white;">ยืนยันข้ามเคส</button>
                </div>
            </div>
        </div>

        <!-- Zero-Typing Keyboard Drawers -->
        <div class="numpad-overlay" id="numpad-overlay" onclick="closeNumPad()"></div>
        <div class="numpad-drawer" id="numpad-drawer">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <span id="numpad-title" style="color: var(--color-accent); font-weight: bold; font-size: 18px;">แป้นพิมพ์ตัวเลข</span>
                <button type="button" onclick="closeNumPad()" style="background: none; border: none; color: var(--text-secondary); font-size: 24px; cursor: pointer;">✕</button>
            </div>
            <div id="numpad-container"></div>
            <button type="button" onclick="closeNumPad()" class="btn-giant btn-giant-success" style="margin-top: 16px; margin-bottom: 0; background: linear-gradient(135deg, var(--color-green), #059669); color: white; height: 50px; font-size: 18px;">ตกลง</button>
        </div>

        <!-- Scroll Picker Drawer -->
        <div class="numpad-overlay" id="picker-overlay" onclick="closeScrollPicker()"></div>
        <div class="numpad-drawer" id="picker-drawer">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <span id="picker-title" style="color: var(--color-accent); font-weight: bold; font-size: 18px;">เลือกค่า</span>
                <button type="button" onclick="closeScrollPicker()" style="background: none; border: none; color: var(--text-secondary); font-size: 24px; cursor: pointer;">✕</button>
            </div>
            <div class="scroll-picker-container">
                <div class="scroll-picker-indicator"></div>
                <div class="scroll-picker-wheel" id="picker-integer-wheel" onscroll="handleWheelScroll('integer')"></div>
                <span style="font-size: 32px; font-weight: bold; color: var(--text-primary); margin: 0 10px;">.</span>
                <div class="scroll-picker-wheel" id="picker-decimal-wheel" onscroll="handleWheelScroll('decimal')"></div>
            </div>
            <button type="button" onclick="confirmScrollPicker()" class="btn-giant btn-giant-success" style="margin-top: 16px; margin-bottom: 0; background: linear-gradient(135deg, var(--color-green), #059669); color: white; height: 50px; font-size: 18px;">ตกลง</button>
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
            <a href="../logout.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                ออกระบบ
            </a>
        </div>
    </div>

    <script>
        let selectedResident = null;
        let activeNumPad = null;
        let currentPickerInputId = null;
        let gpsLocation = { lat: 0, lng: 0 };
        let homeLat = 0;
        let homeLng = 0;

        document.addEventListener("DOMContentLoaded", function() {
            // Get current location coordinates asynchronously
            getCurrentLocation().then(coords => {
                gpsLocation.lat = coords.lat;
                gpsLocation.lng = coords.lng;
                document.getElementById('screening_lat').value = coords.lat;
                document.getElementById('screening_lng').value = coords.lng;
                document.getElementById('gps-status-info').innerHTML = `📍 พิกัดปัจจุบันจาก GPS: ${coords.lat.toFixed(6)}, ${coords.lng.toFixed(6)}`;
            }).catch(err => {
                console.error("GPS coords capture failed:", err);
                document.getElementById('gps-status-info').innerHTML = `⚠️ ไม่สามารถจับพิกัด GPS ได้ (ใช้พิกัดจำลองทดแทน)`;
            });

            // Set up BMI calculation triggers
            const w = document.getElementById('weight');
            const h = document.getElementById('height');
            [w, h].forEach(input => {
                input.addEventListener('input', calculateBmi);
            });

            // Set up CV Risk Score triggers
            const sbpInput = document.getElementById('sys_bp1');
            const dtxInput = document.getElementById('dtx_value');
            
            [sbpInput, dtxInput].forEach(el => {
                el.addEventListener('input', calculateCvRisk);
            });
            
            document.querySelectorAll('input[name="smoking_risk"]').forEach(radio => {
                radio.addEventListener('change', calculateCvRisk);
            });
        });

        function selectSkipReason(reason, elem) {
            // Reset all buttons in list
            document.querySelectorAll('.btn-skip-reason').forEach(btn => {
                btn.classList.remove('neumorph-inset');
                btn.classList.add('neumorph-flat');
                btn.style.background = 'var(--bg-card)';
                btn.style.color = 'var(--text-primary)';
            });
            
            // Highlight selected button
            elem.classList.remove('neumorph-flat');
            elem.classList.add('neumorph-inset');
            elem.style.background = 'var(--bg-darker)';
            elem.style.color = 'var(--color-primary)';
            
            // Set value
            document.getElementById('skip_reason').value = reason;
        }

        function mockGps(mode) {
            const btnHome = document.getElementById('btn-gps-home');
            const btnDrift = document.getElementById('btn-gps-drift');
            const infoDiv = document.getElementById('gps-status-info');
            
            btnHome.classList.remove('neumorph-inset');
            btnHome.classList.add('neumorph-flat');
            btnHome.style.background = 'var(--bg-card)';
            btnHome.style.color = 'var(--text-primary)';
            
            btnDrift.classList.remove('neumorph-inset');
            btnDrift.classList.add('neumorph-flat');
            btnDrift.style.background = 'var(--bg-card)';
            btnDrift.style.color = 'var(--text-primary)';
            
            let currentLat = homeLat;
            let currentLng = homeLng;
            
            if (currentLat === 0 || currentLng === 0) {
                // Fallback to central Tal Sum coords if no coordinates registered
                currentLat = 15.4300;
                currentLng = 104.9800;
            }
            
            if (mode === 'home') {
                btnHome.classList.add('neumorph-inset');
                btnHome.classList.remove('neumorph-flat');
                btnHome.style.background = 'var(--bg-darker)';
                btnHome.style.color = 'var(--color-green)';
                
                gpsLocation.lat = currentLat;
                gpsLocation.lng = currentLng;
                
                infoDiv.innerHTML = `📍 จำลองพิกัด: อยู่ที่บ้านเป้าหมาย (${currentLat.toFixed(6)}, ${currentLng.toFixed(6)})`;
            } else {
                btnDrift.classList.add('neumorph-inset');
                btnDrift.classList.remove('neumorph-flat');
                btnDrift.style.background = 'var(--bg-darker)';
                btnDrift.style.color = 'var(--color-red)';
                
                // Shift coords by ~130 meters (0.0011 lat drift)
                gpsLocation.lat = currentLat + 0.0011;
                gpsLocation.lng = currentLng + 0.0005;
                
                infoDiv.innerHTML = `🛰️ จำลองพิกัด: พิกัดคลาดเคลื่อนไป 130 เมตร (${gpsLocation.lat.toFixed(6)}, ${gpsLocation.lng.toFixed(6)})`;
            }
            
            document.getElementById('screening_lat').value = gpsLocation.lat;
            document.getElementById('screening_lng').value = gpsLocation.lng;
        }

        function selectResident(assignId, name, sex, birth, needDm, needHt, latVal, lngVal, card) {
            // Deselect all
            document.querySelectorAll('.resident-card').forEach(c => {
                c.classList.remove('selected');
                c.querySelector('.select-indicator').innerText = '⚪';
            });

            // Select active
            card.classList.add('selected');
            card.querySelector('.select-indicator').innerText = '🟡';

            // Store resident info
            const birthDate = new Date(birth);
            const age = new Date().getFullYear() - birthDate.getFullYear();
            
            selectedResident = {
                assignmentId: assignId,
                name: name,
                sex: sex,
                age: age,
                needDm: needDm,
                needHt: needHt,
                homeLat: latVal,
                homeLng: lngVal
            };

            document.getElementById('assignment_id').value = assignId;
            document.getElementById('selected-resident-name').innerText = name;
            
            // Set home coordinates for GPS mock checks
            homeLat = parseFloat(latVal);
            homeLng = parseFloat(lngVal);
            mockGps('home');

            // Toggle sub-sections based on requirements
            const bpSection = document.getElementById('section-bp');
            const dtxSection = document.getElementById('section-dtx');

            bpSection.style.display = needHt ? 'block' : 'none';
            dtxSection.style.display = needDm ? 'block' : 'none';

            // Show next button
            document.getElementById('btn-next-resident').style.display = 'block';

            // Auto-transition to next step (Zero-Typing 3-Click Flow: Click 1)
            setTimeout(() => {
                nextStep('step-vital');
            }, 250);
        }

        function nextStep(stepId) {
            document.querySelectorAll('.step-section').forEach(s => {
                s.classList.remove('active');
            });
            document.getElementById(stepId).classList.add('active');
            window.scrollTo(0,0);
        }

        // Zero-Typing Num Pad functions
        function openNumPad(inputId, title) {
            document.getElementById('numpad-title').innerText = title;
            document.getElementById('numpad-overlay').style.display = 'block';
            document.getElementById('numpad-drawer').classList.add('open');

            activeNumPad = new VhvNumPad(inputId, 'numpad-container');
            const currentVal = document.getElementById(inputId).value;
            activeNumPad.setValue(currentVal || '');
        }

        function closeNumPad() {
            document.getElementById('numpad-overlay').style.display = 'none';
            document.getElementById('numpad-drawer').classList.remove('open');
            activeNumPad = null;
        }

        // Zero-Typing Scroll Picker functions
        function openScrollPicker(inputId, title, minVal, maxVal, defaultVal) {
            currentPickerInputId = inputId;
            document.getElementById('picker-title').innerText = title;
            
            // Show overlay & drawer
            document.getElementById('picker-overlay').style.display = 'block';
            document.getElementById('picker-drawer').classList.add('open');
            
            // Populate integer wheel
            const intWheel = document.getElementById('picker-integer-wheel');
            let intHtml = '<div class="scroll-picker-item" data-val=""></div>'; // Empty top
            for (let i = minVal; i <= maxVal; i++) {
                intHtml += `<div class="scroll-picker-item" data-val="${i}">${i}</div>`;
            }
            intHtml += '<div class="scroll-picker-item" data-val=""></div>'; // Empty bottom
            intWheel.innerHTML = intHtml;
            
            // Populate decimal wheel
            const decWheel = document.getElementById('picker-decimal-wheel');
            let decHtml = '<div class="scroll-picker-item" data-val=""></div>'; // Empty top
            for (let i = 0; i <= 9; i++) {
                decHtml += `<div class="scroll-picker-item" data-val="${i}">${i}</div>`;
            }
            decHtml += '<div class="scroll-picker-item" data-val=""></div>'; // Empty bottom
            decWheel.innerHTML = decHtml;
            
            // Get current value or use default
            const input = document.getElementById(inputId);
            let currentVal = parseFloat(input.value);
            if (isNaN(currentVal) || currentVal <= 0) {
                currentVal = defaultVal;
            }
            
            const intPart = Math.floor(currentVal);
            const decPart = Math.round((currentVal - intPart) * 10);
            
            // Scroll to current/default values with a short timeout to allow DOM to render
            setTimeout(() => {
                scrollWheelToValue(intWheel, intPart);
                scrollWheelToValue(decWheel, decPart);
                
                // Set initial active state highlights
                handleWheelScroll('integer');
                handleWheelScroll('decimal');
            }, 50);
        }

        function scrollWheelToValue(wheelEl, value) {
            const items = wheelEl.querySelectorAll('.scroll-picker-item');
            for (let i = 1; i < items.length - 1; i++) {
                if (parseInt(items[i].dataset.val, 10) === parseInt(value, 10)) {
                    wheelEl.scrollTop = (i - 1) * 40;
                    break;
                }
            }
        }

        function handleWheelScroll(type) {
            const wheel = document.getElementById(`picker-${type}-wheel`);
            if (!wheel) return;
            
            const items = wheel.querySelectorAll('.scroll-picker-item');
            if (items.length === 0) return;
            
            const idx = Math.round(wheel.scrollTop / 40) + 1;
            
            if (idx >= 1 && idx < items.length - 1) {
                items.forEach((item, i) => {
                    if (i === idx) {
                        item.classList.add('selected');
                    } else {
                        item.classList.remove('selected');
                    }
                });
                
                // Real-time value update to inputs
                updatePickerInputValueFromWheels();
            }
        }

        function updatePickerInputValueFromWheels() {
            if (!currentPickerInputId) return;
            
            const intWheel = document.getElementById('picker-integer-wheel');
            const decWheel = document.getElementById('picker-decimal-wheel');
            
            const intIdx = Math.round(intWheel.scrollTop / 40) + 1;
            const decIdx = Math.round(decWheel.scrollTop / 40) + 1;
            
            const intItems = intWheel.querySelectorAll('.scroll-picker-item');
            const decItems = decWheel.querySelectorAll('.scroll-picker-item');
            
            if (intItems[intIdx] && decItems[decIdx]) {
                const intVal = intItems[intIdx].dataset.val;
                const decVal = decItems[decIdx].dataset.val;
                
                if (intVal !== "" && decVal !== "") {
                    const finalVal = `${intVal}.${decVal}`;
                    const input = document.getElementById(currentPickerInputId);
                    input.value = finalVal;
                    
                    // Dispatch input event to trigger BMI and other risk calculations
                    const event = new Event('input', { bubbles: true });
                    input.dispatchEvent(event);
                }
            }
        }

        function closeScrollPicker() {
            document.getElementById('picker-overlay').style.display = 'none';
            document.getElementById('picker-drawer').classList.remove('open');
            currentPickerInputId = null;
        }

        function confirmScrollPicker() {
            updatePickerInputValueFromWheels();
            closeScrollPicker();
        }

        function calculateBmi() {
            const w = parseFloat(document.getElementById('weight').value);
            const h = parseFloat(document.getElementById('height').value);
            const display = document.getElementById('bmi-display');
            const status = document.getElementById('bmi-status');

            if (w > 0 && h > 0) {
                const bmi = w / Math.pow(h / 100, 2);
                display.innerText = bmi.toFixed(2);
                
                if (bmi < 18.5) {
                    status.innerText = 'น้ำหนักน้อย';
                    status.style.backgroundColor = 'rgba(2, 132, 199, 0.2)';
                    status.style.color = 'var(--color-primary)';
                } else if (bmi < 23) {
                    status.innerText = 'ปกติ';
                    status.style.backgroundColor = 'rgba(16, 185, 129, 0.2)';
                    status.style.color = 'var(--color-green)';
                } else if (bmi < 25) {
                    status.innerText = 'ท้วม';
                    status.style.backgroundColor = 'rgba(245, 158, 11, 0.2)';
                    status.style.color = 'var(--color-yellow)';
                } else {
                    status.innerText = 'อ้วน';
                    status.style.backgroundColor = 'rgba(239, 68, 68, 0.2)';
                    status.style.color = 'var(--color-red)';
                }
            } else {
                display.innerText = '0.00';
                status.innerText = 'รอป้อนข้อมูล';
                status.style.backgroundColor = 'var(--border-color)';
                status.style.color = 'var(--text-secondary)';
            }
            calculateCvRisk();
        }

        // Simplified Thai CV Risk Calculator Matrix
        function calculateCvRisk() {
            if (!selectedResident) return;

            const age = selectedResident.age;
            const sex = selectedResident.sex; // '1' = Male, '2' = Female
            const sbp = parseFloat(document.getElementById('sys_bp1').value) || 120;
            const dtx = parseFloat(document.getElementById('dtx_value').value) || 90;
            
            // Check if patient already has diabetes
            const hasDm = !selectedResident.needDm || dtx >= 126;

            // Smoking
            let isSmoker = false;
            const smokingVal = document.querySelector('input[name="smoking_risk"]:checked').value;
            if (smokingVal === 'red') {
                isSmoker = true;
            }

            // Calculation Logic (Simplified model mapping typical Thai CV Risk equation)
            let baseRisk = 1.2;

            // Age impact
            if (age >= 40 && age < 50) baseRisk += 2.0;
            else if (age >= 50 && age < 60) baseRisk += 5.5;
            else if (age >= 60) baseRisk += 12.0;

            // Sex & Smoking impact
            if (sex === '1') { // Male
                baseRisk += 1.5;
                if (isSmoker) baseRisk += 4.5;
            } else { // Female
                if (isSmoker) baseRisk += 2.5;
            }

            // Diabetes impact
            if (hasDm) {
                baseRisk += 6.0;
            }

            // SBP impact
            if (sbp >= 140 && sbp < 160) baseRisk += 2.5;
            else if (sbp >= 160) baseRisk += 7.0;

            // Limit score between 0% and 100%
            const finalScore = Math.min(100, Math.max(0.5, baseRisk));

            // Display
            const display = document.getElementById('cv-risk-display');
            const status = document.getElementById('cv-risk-status');

            display.innerText = finalScore.toFixed(2) + '%';

            if (finalScore < 5) {
                display.style.color = 'var(--color-green)';
                status.innerText = 'ความเสี่ยงต่ำ (< 5%)';
            } else if (finalScore < 10) {
                display.style.color = 'var(--color-yellow)';
                status.innerText = 'ความเสี่ยงปานกลาง (5-9%)';
            } else {
                display.style.color = 'var(--color-red)';
                status.innerText = '🚨 ความเสี่ยงสูง (≥ 10%)';
            }
        }

        // Submit Screening Data
        function submitScreening() {
            const form = document.getElementById('screening-form');
            const formData = new FormData(form);
            
            // Add custom action parameter
            formData.append('action', 'save_screening');
            formData.append('cv_risk_score', parseFloat(document.getElementById('cv-risk-display').innerText));

            // Send to save_screening endpoint
            fetch('../api/save_screening.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert("บันทึกการคัดกรองและส่งการ์ดประเมินไปยังครอบครัวทาง LINE เรียบร้อยแล้ว! อสม. ได้รับ +1 คะแนนสะสม");
                    window.location.href = 'index.php';
                } else {
                    alert("เกิดข้อผิดพลาดในการบันทึก: " + data.message);
                }
            })
            .catch(err => {
                alert("เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย: " + err);
            });
        }

        // Skip case controls
        function openSkipModal() {
            if (!selectedResident) {
                alert("กรุณาเลือกบุคคลที่ต้องการข้ามเคสก่อน");
                return;
            }
            document.getElementById('skip-modal').style.display = 'flex';
        }

        function closeSkipModal() {
            document.getElementById('skip-modal').style.display = 'none';
        }

        function submitSkipCase() {
            const reason = document.getElementById('skip_reason').value;
            const assignId = document.getElementById('assignment_id').value;

            fetch('../api/save_screening.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'skip_case',
                    'assignment_id': assignId,
                    'skipped_reason': reason,
                    'lat': gpsLocation.lat,
                    'lng': gpsLocation.lng
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert("ข้ามงานชั่วคราวเรียบร้อย อสม. ได้รับ +1 แต้มสะสม!");
                    window.location.href = 'index.php';
                } else {
                    alert("เกิดข้อผิดพลาด: " + data.message);
                }
            })
            .catch(err => {
                alert("เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย");
            });
        }
    </script>
</body>
</html>
