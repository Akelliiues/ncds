<?php
// vhv/screening_form.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

$isShell = isset($_GET['shell']) && $_GET['shell'] === 'true';
$hid = $_GET['hid'] ?? '';
$cid = $_GET['cid'] ?? '';

if (!$isShell && empty($hid) && empty($cid)) {
    header("Location: scan.php");
    exit();
}

$vhvId = $_SESSION['vhv_id'];
$hoscode = $_SESSION['hoscode'] ?? null;
$residents = [];
$history = [];

if (!$isShell) {
    require_once __DIR__ . '/../config/db.php';
    
    // Auto-assign in Sandbox Mode if no assignment exists yet
    if (isSandboxMode($hoscode)) {
        if (!empty($hid)) {
            $checkStmt = $pdo->prepare("SELECT cid FROM target_population WHERE hid = ?");
            $checkStmt->execute([$hid]);
            $targets = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($targets)) {
                $ins = $pdo->prepare("INSERT IGNORE INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status, is_sandbox) VALUES (?, ?, 2026, 'pending', 1)");
                foreach ($targets as $tc) {
                    $ins->execute([$tc, $vhvId]);
                }
            }
        } elseif (!empty($cid)) {
            $checkStmt = $pdo->prepare("SELECT cid FROM target_population WHERE cid = ? LIMIT 1");
            $checkStmt->execute([$cid]);
            $pop = $checkStmt->fetch();
            if ($pop) {
                $ins = $pdo->prepare("INSERT IGNORE INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status, is_sandbox) VALUES (?, ?, 2026, 'pending', 1)");
                $ins->execute([$cid, $vhvId]);
            }
        }
    }

    // Fetch residents based on hid or cid
    $isSandboxVal = isSandboxMode($hoscode) ? 1 : 0;
    if (!empty($hid)) {
        $residentsStmt = $pdo->prepare("
            SELECT p.*, a.assignment_id,
                   COALESCE(
                       (SELECT sr.sys_bp1 FROM screening_results sr JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE ta.target_cid = p.cid AND ta.assignment_status = 'completed' ORDER BY sr.created_at DESC LIMIT 1),
                       (SELECT ht.sbp FROM staging_hdc_ht ht WHERE ht.cid = p.cid ORDER BY ht.imported_at DESC LIMIT 1)
                   ) AS last_sbp,
                   COALESCE(
                       (SELECT sr.dia_bp1 FROM screening_results sr JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE ta.target_cid = p.cid AND ta.assignment_status = 'completed' ORDER BY sr.created_at DESC LIMIT 1),
                       (SELECT ht.dbp FROM staging_hdc_ht ht WHERE ht.cid = p.cid ORDER BY ht.imported_at DESC LIMIT 1)
                   ) AS last_dbp,
                   COALESCE(
                       (SELECT sr.dtx_value FROM screening_results sr JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE ta.target_cid = p.cid AND ta.assignment_status = 'completed' ORDER BY sr.created_at DESC LIMIT 1),
                       (SELECT dm.bslevel FROM staging_hdc_dm dm WHERE dm.cid = p.cid ORDER BY dm.imported_at DESC LIMIT 1)
                   ) AS last_dtx,
                   COALESCE(
                       (SELECT sr.dtx_type FROM screening_results sr JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE ta.target_cid = p.cid AND ta.assignment_status = 'completed' ORDER BY sr.created_at DESC LIMIT 1),
                       'fpg'
                   ) AS last_dtx_type
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            WHERE CAST(p.hid AS UNSIGNED) = CAST(? AS UNSIGNED) AND a.vhv_id = ? AND a.budget_year = 2026 AND a.assignment_status IN ('pending', 'skipped') AND a.is_sandbox = ?
              AND (
                  (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
                  OR 
                  (p.need_screen_dm = 0 AND p.need_screen_ht = 0 AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
              )
        ");
        $residentsStmt->execute([$hid, $vhvId, $isSandboxVal]);
        $residents = $residentsStmt->fetchAll();

        if (empty($residents)) {
            $historyStmt = $pdo->prepare("
                SELECT p.*, a.assignment_status
                FROM task_assignments a
                JOIN target_population p ON a.target_cid = p.cid
                WHERE CAST(p.hid AS UNSIGNED) = CAST(? AS UNSIGNED) AND a.vhv_id = ? AND a.budget_year = 2026 AND a.is_sandbox = ?
                  AND (
                      (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
                      OR 
                      (p.need_screen_dm = 0 AND p.need_screen_ht = 0 AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
                  )
            ");
            $historyStmt->execute([$hid, $vhvId, $isSandboxVal]);
            $history = $historyStmt->fetchAll();
        }
    } else {
        $residentsStmt = $pdo->prepare("
            SELECT p.*, a.assignment_id,
                   COALESCE(
                       (SELECT sr.sys_bp1 FROM screening_results sr JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE ta.target_cid = p.cid AND ta.assignment_status = 'completed' ORDER BY sr.created_at DESC LIMIT 1),
                       (SELECT ht.sbp FROM staging_hdc_ht ht WHERE ht.cid = p.cid ORDER BY ht.imported_at DESC LIMIT 1)
                   ) AS last_sbp,
                   COALESCE(
                       (SELECT sr.dia_bp1 FROM screening_results sr JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE ta.target_cid = p.cid AND ta.assignment_status = 'completed' ORDER BY sr.created_at DESC LIMIT 1),
                       (SELECT ht.dbp FROM staging_hdc_ht ht WHERE ht.cid = p.cid ORDER BY ht.imported_at DESC LIMIT 1)
                   ) AS last_dbp,
                   COALESCE(
                       (SELECT sr.dtx_value FROM screening_results sr JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE ta.target_cid = p.cid AND ta.assignment_status = 'completed' ORDER BY sr.created_at DESC LIMIT 1),
                       (SELECT dm.bslevel FROM staging_hdc_dm dm WHERE dm.cid = p.cid ORDER BY dm.imported_at DESC LIMIT 1)
                   ) AS last_dtx,
                   COALESCE(
                       (SELECT sr.dtx_type FROM screening_results sr JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE ta.target_cid = p.cid AND ta.assignment_status = 'completed' ORDER BY sr.created_at DESC LIMIT 1),
                       'fpg'
                   ) AS last_dtx_type
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            WHERE p.cid = ? AND a.vhv_id = ? AND a.budget_year = 2026 AND a.assignment_status IN ('pending', 'skipped') AND a.is_sandbox = ?
              AND (
                  (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
                  OR 
                  (p.need_screen_dm = 0 AND p.need_screen_ht = 0 AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
              )
        ");
        $residentsStmt->execute([$cid, $vhvId, $isSandboxVal]);
        $residents = $residentsStmt->fetchAll();

        if (empty($residents)) {
            $historyStmt = $pdo->prepare("
                SELECT p.*, a.assignment_status
                FROM task_assignments a
                JOIN target_population p ON a.target_cid = p.cid
                WHERE p.cid = ? AND a.vhv_id = ? AND a.budget_year = 2026 AND a.is_sandbox = ?
                  AND (
                      (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
                      OR 
                      (p.need_screen_dm = 0 AND p.need_screen_ht = 0 AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
                  )
            ");
            $historyStmt->execute([$cid, $vhvId, $isSandboxVal]);
            $history = $historyStmt->fetchAll();
        }
    }
}
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
        .btn-advice-chip {
            background-color: var(--bg-card);
            color: var(--text-primary);
            border: none;
            padding: 12px 16px;
            border-radius: var(--border-radius);
            text-align: left;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            transition: all var(--transition-speed);
            box-shadow: var(--neumorph-flat);
        }
        .btn-advice-chip.selected {
            background-color: var(--bg-darker) !important;
            color: var(--color-green) !important;
            box-shadow: var(--neumorph-inset) !important;
        }
        .advice-image-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 10px;
        }
        @media (max-width: 576px) {
            .advice-image-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
        }
        @media (max-width: 380px) {
            .advice-image-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 6px;
            }
        }
        .advice-image-card {
            position: relative;
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            border: 3px solid transparent;
            transition: all var(--transition-speed);
            aspect-ratio: 1 / 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .advice-image-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }
        .advice-image-card:hover img {
            transform: scale(1.04);
        }
        .advice-image-card.selected {
            border-color: var(--color-green);
            box-shadow: var(--neumorph-inset), 0 0 12px rgba(16, 185, 129, 0.4);
        }
        .advice-image-card .checkmark-overlay {
            position: absolute;
            top: 8px;
            right: 8px;
            background-color: var(--color-green);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            z-index: 2;
        }
        .advice-image-card.selected .checkmark-overlay {
            opacity: 1;
            transform: scale(1);
        }
    </style>
</head>
<body class="vhv-accessibility">
    <div class="mobile-wrapper" style="padding-bottom: 100px;">
        <div class="vhv-header">
            <h3 style="color: var(--color-accent); margin: 0; font-size: 16px;">แบบคัดกรอง บ้านเลขที่ <?= htmlspecialchars($residents[0]['house_no'] ?? $history[0]['house_no'] ?? '') ?></h3>
            <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 14px;">รหัสบ้าน HID: <?= htmlspecialchars($hid) ?></p>
        </div>

        <?php if (empty($residents) && !$isShell): ?>
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
                    
                    <div id="residents-container">
                    <?php if (!$isShell): ?>
                        <?php foreach ($residents as $r): ?>
                            <div class="resident-card" onclick="selectResident(<?= $r['assignment_id'] ?>, '<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>', '<?= $r['sex'] ?>', '<?= $r['birth'] ?>', <?= $r['need_screen_dm'] ? 'true' : 'false' ?>, <?= $r['need_screen_ht'] ? 'true' : 'false' ?>, '<?= htmlspecialchars($r['health_status_origin'] ?? 'NORMAL') ?>', <?= (float)($r['latitude'] ?? 0) ?>, <?= (float)($r['longitude'] ?? 0) ?>, <?= $r['last_sbp'] !== null ? (int)$r['last_sbp'] : 'null' ?>, <?= $r['last_dbp'] !== null ? (int)$r['last_dbp'] : 'null' ?>, <?= $r['last_dtx'] !== null ? (int)$r['last_dtx'] : 'null' ?>, '<?= htmlspecialchars($r['last_dtx_type'] ?? 'fpg') ?>', this)">
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
                    <?php endif; ?>
                    </div>
                    
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

                    <?php if (isSandboxMode($hoscode) && isset($_GET['debug']) && $_GET['debug'] === 'true'): ?>
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
                    <?php endif; ?>

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
                        <div id="last-bp-info" class="card-dark neumorph-inset" style="padding: 10px 14px; font-size: 13.5px; color: var(--color-primary); font-weight: 700; margin-bottom: 14px; display: none; border-radius: var(--border-radius);"></div>
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
                    <div id="section-dtx" style="display: none; margin-bottom: 24px;">
                        <span style="color: var(--text-primary); font-size: 18px; font-weight: 800; display: block; margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">🩸 วัดระดับน้ำตาลในเลือด (DTX)</span>
                        <div id="last-dtx-info" class="card-dark neumorph-inset" style="padding: 10px 14px; font-size: 13.5px; color: var(--color-accent); font-weight: 700; margin-bottom: 14px; display: none; border-radius: var(--border-radius);"></div>
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
                        <div id="cv-risk-status" style="font-size: 15px; color: var(--text-secondary); font-weight: bold; margin-bottom: 12px;">ความเสี่ยงต่ำมาก</div>
                        
                        <!-- Details of BP and DTX used in calculation -->
                        <div id="cv-risk-details" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; border-top: 1px dashed var(--border-color); padding-top: 10px; font-size: 13px; text-align: left; color: var(--text-secondary); margin-top: 8px;">
                            <div>🩺 ความดันที่ใช้: <strong id="cv-risk-bp-val" style="color: var(--text-primary);">-</strong></div>
                            <div>🩸 ค่าน้ำตาลที่ใช้: <strong id="cv-risk-dtx-val" style="color: var(--text-primary);">-</strong></div>
                        </div>
                    </div>

                    <!-- VHV Advice Given (Preset Selection) -->
                    <div style="margin-bottom: 24px;">
                        <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 6px;">💡 คำแนะนำโดย อสม.</label>
                        <textarea name="advice_given" id="advice_given" class="input-large" style="height: 80px; resize: none; width: 100%; font-size: 15px; background-color: var(--bg-darker); border: 2px solid var(--border-color); color: var(--text-primary); border-radius: var(--border-radius); padding: 10px;" readonly placeholder="กรุณาคลิกเลือกคำแนะนำจากปุ่มด้านล่าง (ไม่ต้องพิมพ์)..."></textarea>
                        
                        <div class="advice-image-grid">
                            <div class="advice-image-card" data-text="ลดเค็ม งดซอส/ปลาร้า" onclick="toggleAdviceCard(this)">
                                <div class="checkmark-overlay">✓</div>
                                <img src="../assets/img/advice/reduce_salt.jpg" alt="ลดเค็ม งดซอส/ปลาร้า" loading="lazy">
                            </div>
                            <div class="advice-image-card" data-text="ผ่อนคลาย พักผ่อนให้พอ" onclick="toggleAdviceCard(this)">
                                <div class="checkmark-overlay">✓</div>
                                <img src="../assets/img/advice/relax.jpg" alt="ผ่อนคลาย พักผ่อนให้พอ" loading="lazy">
                            </div>
                            <div class="advice-image-card" data-text="ออกกำลังกาย 30 นาที/วัน" onclick="toggleAdviceCard(this)">
                                <div class="checkmark-overlay">✓</div>
                                <img src="../assets/img/advice/exercise.jpg" alt="ออกกำลังกาย 30 นาที/วัน" loading="lazy">
                            </div>
                            <div class="advice-image-card" data-text="งดบุหรี่ & แอลกอฮอล์" onclick="toggleAdviceCard(this)">
                                <div class="checkmark-overlay">✓</div>
                                <img src="../assets/img/advice/no_smoking_alcohol.jpg" alt="งดบุหรี่ & แอลกอฮอล์" loading="lazy">
                            </div>
                            <div class="advice-image-card" data-text="ดื่มน้ำเปล่า 6-8 แก้ว/วัน" onclick="toggleAdviceCard(this)">
                                <div class="checkmark-overlay">✓</div>
                                <img src="../assets/img/advice/drink_water.jpg" alt="ดื่มน้ำเปล่า 6-8 แก้ว/วัน" loading="lazy">
                            </div>
                            <div class="advice-image-card" data-text="เพิ่มผักใบเขียว ธัญพืช" onclick="toggleAdviceCard(this)">
                                <div class="checkmark-overlay">✓</div>
                                <img src="../assets/img/advice/more_veggies.jpg" alt="เพิ่มผักใบเขียว ธัญพืช" loading="lazy">
                            </div>
                            <div class="advice-image-card" data-text="พบแพทย์ตามนัดสม่ำเสมอ" onclick="toggleAdviceCard(this)">
                                <div class="checkmark-overlay">✓</div>
                                <img src="../assets/img/advice/meet_doctor.jpg" alt="พบแพทย์ตามนัดสม่ำเสมอ" loading="lazy">
                            </div>
                            <div class="advice-image-card" data-text="เลี่ยงของมัน ของทอด" onclick="toggleAdviceCard(this)">
                                <div class="checkmark-overlay">✓</div>
                                <img src="../assets/img/advice/avoid_fried.jpg" alt="เลี่ยงของมัน ของทอด" loading="lazy">
                            </div>
                            <div class="advice-image-card" data-text="ทานยาต่อเนื่องตามแพทย์สั่ง" onclick="toggleAdviceCard(this)">
                                <div class="checkmark-overlay">✓</div>
                                <img src="../assets/img/advice/take_medicine.jpg" alt="ทานยาต่อเนื่องตามแพทย์สั่ง" loading="lazy">
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; margin-top: 30px;">
                        <button type="button" onclick="nextStep('step-resident')" class="btn-giant btn-giant-secondary" style="flex: 1; margin-bottom: 0;">← ย้อนกลับ</button>
                        <button type="button" onclick="submitScreening()" class="btn-giant btn-giant-success" style="flex: 1; margin-bottom: 0; background: linear-gradient(135deg, var(--color-green), #059669); color: white;">บันทึกส่งงาน</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <!-- HL-Coach Guidance Modal -->
        <div id="hl-coach-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(13, 44, 84, 0.4); backdrop-filter: blur(5px); z-index: 4000; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 20px; width: 90%; max-width: 450px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto;">
                
                <!-- Modal Header -->
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(251, 191, 36, 0.2); border: 2px solid #fbbf24; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 12px;">✨</div>
                    <h3 style="color: var(--color-accent); font-size: 20px; font-weight: 800; margin: 0;">คัมภีร์แนะนำ HL-Coach</h3>
                    <p style="color: var(--text-secondary); font-size: 14px; margin: 4px 0 0;">คำแนะนำสำหรับผู้นำการปรับเปลี่ยนพฤติกรรม</p>
                </div>

                <!-- Access & Understand -->
                <div style="background: var(--bg-body); border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: var(--neumorph-inset);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <div style="width: 24px; height: 24px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">1</div>
                        <strong style="color: var(--color-accent); font-size: 15px;">Access & Understand (ประเมินผล)</strong>
                    </div>
                    <div id="hl-risk-badge" style="display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 14px; font-weight: bold; margin-bottom: 8px;">
                        <!-- Injected by JS -->
                    </div>
                    <p style="font-size: 13px; color: var(--text-secondary); margin: 0;" id="hl-risk-desc">
                        <!-- Injected by JS -->
                    </p>
                </div>

                <!-- Appraise -->
                <div style="background: var(--bg-body); border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: var(--neumorph-inset);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <div style="width: 24px; height: 24px; border-radius: 50%; background: var(--color-yellow); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">2</div>
                        <strong style="color: var(--color-accent); font-size: 15px;">Appraise (ชวนคุยประเมินความพร้อม)</strong>
                    </div>
                    <p style="font-size: 14px; color: var(--text-primary); margin: 0; line-height: 1.5; font-style: italic;">
                        "ตา/ยาย เห็นผลที่ออกมาไหมครับ/คะ? คิดว่าตัวเองจะไหวไหมถ้าเรามาลองปรับเรื่องการกิน หรือการขยับร่างกายกันสักนิด เพื่อให้รอบหน้าผลมันดีขึ้น?"
                    </p>
                </div>

                <!-- Apply -->
                <div style="background: var(--bg-body); border-radius: 12px; padding: 16px; margin-bottom: 24px; box-shadow: var(--neumorph-inset);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <div style="width: 24px; height: 24px; border-radius: 50%; background: var(--color-green); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">3</div>
                        <strong style="color: var(--color-accent); font-size: 15px;">Apply (แนะนำเทคนิค 3อ. 2ส.)</strong>
                    </div>
                    <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: var(--text-primary); line-height: 1.6;" id="hl-apply-list">
                        <!-- Injected by JS -->
                    </ul>
                </div>

                <button type="button" onclick="closeHlCoachModal()" class="btn-giant btn-giant-success" style="width: 100%; margin: 0; background: linear-gradient(135deg, var(--color-green), #059669); color: white; font-size: 16px; border-radius: 12px;">
                    ยืนยันการให้คำแนะนำ & จบงาน
                </button>
            </div>
        </div>

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

        <!-- Critical Value Alert Modal Overlay -->
        <div id="critical-alert-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 5000; align-items: center; justify-content: center; padding: 16px;">
            <div class="card-dark" style="width: 90%; max-width: 480px; background: #0f172a; border: 2px solid var(--color-red); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); border-radius: 24px; padding: 24px; color: var(--text-primary); text-align: left; animation: fadeIn 0.3s ease;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: rgba(239, 68, 68, 0.2); color: var(--color-red); font-size: 24px; flex-shrink: 0;">
                        🚨
                    </div>
                    <div>
                        <h3 style="color: var(--color-red); margin: 0; font-size: 20px; font-weight: 800;">ตรวจพบสัญญาณชีพสูงวิกฤต!</h3>
                        <p style="margin: 4px 0 0 0; color: var(--text-secondary); font-size: 13px;">(Critical Value Alert)</p>
                    </div>
                </div>
                
                <div id="critical-alert-values" style="background: rgba(239, 68, 68, 0.1); border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; border-left: 4px solid var(--color-red); font-size: 15px; font-weight: bold; color: white;">
                    <!-- Will be populated dynamically -->
                </div>

                <div style="margin-bottom: 24px;">
                    <h4 style="color: var(--text-primary); font-size: 15px; font-weight: bold; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        📋 คำแนะนำการปฐมพยาบาลเบื้องต้น:
                    </h4>
                    <div id="critical-alert-advice" style="font-size: 14px; line-height: 1.6; color: var(--text-secondary); display: flex; flex-direction: column; gap: 12px; max-height: 250px; overflow-y: auto; padding-right: 8px;">
                        <!-- Will be populated dynamically -->
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="button" onclick="closeCriticalModal()" class="btn-giant btn-giant-secondary" style="flex: 1; height: 50px; font-size: 16px; margin-bottom: 0; border-radius: 12px; border: 1px solid var(--border-color); color: var(--text-primary); cursor: pointer; background: transparent;">
                        ✕ ปิดเพื่อแก้ไขค่า
                    </button>
                    <button type="button" id="btn-confirm-critical-save" class="btn-giant btn-giant-danger" style="flex: 1; height: 50px; font-size: 16px; margin-bottom: 0; border-radius: 12px; background: var(--color-red); color: white; border: none; font-weight: bold; cursor: pointer;">
                        ✅ ยืนยันบันทึกข้อมูล
                    </button>
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
            <!-- Number Display Box -->
            <div id="numpad-display-box" style="background-color: var(--bg-main); border: 2px solid var(--color-primary); border-radius: 12px; padding: 15px; text-align: center; font-size: 36px; font-weight: 800; color: var(--color-accent); margin-bottom: 16px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.5); min-height: 70px; display: flex; align-items: center; justify-content: center; letter-spacing: 2px;">
                0
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
        const isSandboxMode = <?= isSandboxMode($hoscode) ? 'true' : 'false' ?>;
        let selectedResident = null;
        let activeNumPad = null;
        let currentPickerInputId = null;
        let gpsLocation = { lat: 0, lng: 0 };
        let homeLat = 0;
        let homeLng = 0;

        function updateLocalTask(assignmentId, newStatus, skippedReason = '') {
            const pending = JSON.parse(localStorage.getItem('vhv_pending_tasks') || '[]');
            const completed = JSON.parse(localStorage.getItem('vhv_completed_tasks') || '[]');
            
            const idx = pending.findIndex(t => String(t.assignment_id) === String(assignmentId));
            if (idx !== -1) {
                const task = pending[idx];
                task.assignment_status = newStatus;
                if (newStatus === 'skipped') {
                    task.skipped_reason = skippedReason;
                }
                
                // Remove from pending
                pending.splice(idx, 1);
                
                // Push to completed
                completed.push(task);
                
                localStorage.setItem('vhv_pending_tasks', JSON.stringify(pending));
                localStorage.setItem('vhv_completed_tasks', JSON.stringify(completed));
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            const isShell = <?= $isShell ? 'true' : 'false' ?>;
            
            // Offline/Shell initialization
            if (isShell || !navigator.onLine) {
                // Get URL search parameters
                const urlParams = new URLSearchParams(window.location.search);
                const hidVal = urlParams.get('hid');
                const cidVal = urlParams.get('cid');
                
                // Update title and hid details in UI
                if (hidVal) {
                    document.querySelector('.vhv-header h3').innerText = `แบบคัดกรอง บ้านเลขที่ (ออฟไลน์)`;
                    document.querySelector('.vhv-header p').innerText = `รหัสบ้าน HID: ${hidVal}`;
                } else if (cidVal) {
                    document.querySelector('.vhv-header h3').innerText = `แบบคัดกรอง บุคคล (ออฟไลน์)`;
                    document.querySelector('.vhv-header p').innerText = `รหัสประจำตัว CID: ${cidVal}`;
                }
                
                // Load tasks from localStorage
                const pending = JSON.parse(localStorage.getItem('vhv_pending_tasks') || '[]');
                const completed = JSON.parse(localStorage.getItem('vhv_completed_tasks') || '[]');
                
                // Find matching residents
                let matchedResidents = [];
                if (hidVal) {
                    matchedResidents = pending.filter(t => String(t.hid) === String(hidVal));
                } else if (cidVal) {
                    matchedResidents = pending.filter(t => String(t.cid) === String(cidVal));
                }
                
                const container = document.getElementById('residents-container');
                container.innerHTML = ''; // Clear skeleton
                
                if (matchedResidents.length === 0) {
                    // Check if already completed
                    let completedMatch = [];
                    if (hidVal) {
                        completedMatch = completed.filter(t => String(t.hid) === String(hidVal));
                    } else if (cidVal) {
                        completedMatch = completed.filter(t => String(t.cid) === String(cidVal));
                    }
                    
                    if (completedMatch.length > 0) {
                        container.innerHTML = `
                            <div class="card-dark" style="text-align: center; padding: 40px 20px;">
                                <span style="font-size: 48px; display: block; margin-bottom: 16px;">✅</span>
                                <h3 style="color: var(--color-green); font-size: 22px; margin-bottom: 8px;">คัดกรองเรียบร้อยแล้ว</h3>
                                <p style="color: var(--text-secondary); margin-bottom: 24px;">สมาชิกทั้งหมดในบ้านเลขที่นี้ได้รับการคัดกรองเสร็จสิ้นเรียบร้อยแล้วในรอบปีงบประมาณนี้</p>
                                <a href="index.php" class="btn-giant btn-giant-primary">กลับหน้าหลัก</a>
                            </div>
                        `;
                        // Hide next/skip buttons
                        document.querySelector('button[onclick="openSkipModal()"]').style.display = 'none';
                        return;
                    } else {
                        container.innerHTML = `
                            <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                                ⚠️ ไม่พบข้อมูลผู้รับคัดกรองออฟไลน์สำหรับรหัสนี้
                            </div>
                        `;
                        return;
                    }
                }
                
                // Render matched resident cards
                matchedResidents.forEach(r => {
                    const birthDate = new Date(r.birth);
                    const age = new Date().getFullYear() - birthDate.getFullYear();
                    
                    const card = document.createElement('div');
                    card.className = 'resident-card';
                    card.onclick = function() {
                        selectResident(
                            r.assignment_id, 
                            `${r.first_name} ${r.last_name}`, 
                            r.sex, 
                            r.birth, 
                            r.need_screen_dm == 1, 
                            r.need_screen_ht == 1, 
                            parseFloat(r.latitude || 0), 
                            parseFloat(r.longitude || 0), 
                            r.last_sbp !== undefined ? r.last_sbp : null,
                            r.last_dbp !== undefined ? r.last_dbp : null,
                            r.last_dtx !== undefined ? r.last_dtx : null,
                            r.last_dtx_type || 'fpg',
                            card
                        );
                    };
                    
                    card.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 18px; color: var(--text-primary);">${r.first_name} ${r.last_name}</strong>
                                <p style="margin: 4px 0 0 0; font-size: 14px; color: var(--text-secondary);">
                                    เพศ: ${r.sex == '1' ? 'ชาย' : 'หญิง'} • อายุ: ${age} ปี
                                </p>
                                <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-muted);">
                                    สิทธิ์การตรวจ: 
                                    ${r.need_screen_dm == 1 ? '<span style="color:var(--color-accent)">เบาหวาน</span>' : '<s>เบาหวาน (ตรวจแล้ว/ป่วยแล้ว)</s>'}
                                    •
                                    ${r.need_screen_ht == 1 ? '<span style="color:var(--color-primary)">ความดัน</span>' : '<s>ความดัน (ตรวจแล้ว/ป่วยแล้ว)</s>'}
                                </p>
                            </div>
                            <span style="font-size: 24px; color: var(--border-color);" class="select-indicator">⚪</span>
                        </div>
                    `;
                    container.appendChild(card);
                });
                
                if (matchedResidents[0]) {
                    document.querySelector('.vhv-header h3').innerText = `แบบคัดกรอง บ้านเลขที่ ${matchedResidents[0].house_no} (ออฟไลน์)`;
                }
            }

            // Get current location coordinates asynchronously, and keep it updated via watchPosition in production mode
            if (!isSandboxMode) {
                if (navigator.geolocation) {
                    navigator.geolocation.watchPosition(
                        position => {
                            gpsLocation.lat = position.coords.latitude;
                            gpsLocation.lng = position.coords.longitude;
                            document.getElementById('screening_lat').value = position.coords.latitude;
                            document.getElementById('screening_lng').value = position.coords.longitude;
                            const infoDiv = document.getElementById('gps-status-info');
                            if (infoDiv) {
                                infoDiv.innerHTML = `📍 พิกัดปัจจุบันจาก GPS: ${position.coords.latitude.toFixed(6)}, ${position.coords.longitude.toFixed(6)}`;
                            }
                        },
                        err => {
                            console.error("GPS coords capture failed:", err);
                            const infoDiv = document.getElementById('gps-status-info');
                            if (infoDiv) {
                                infoDiv.innerHTML = `⚠️ ไม่สามารถจับพิกัด GPS ได้`;
                            }
                        },
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
                    );
                }
            } else {
                // Sandbox mode or fallback: capture once and allow Mock GPS tool
                getCurrentLocation().then(coords => {
                    gpsLocation.lat = coords.lat;
                    gpsLocation.lng = coords.lng;
                    document.getElementById('screening_lat').value = coords.lat;
                    document.getElementById('screening_lng').value = coords.lng;
                    const infoDiv = document.getElementById('gps-status-info');
                    if (infoDiv) {
                        infoDiv.innerHTML = `📍 พิกัดปัจจุบันจาก GPS: ${coords.lat.toFixed(6)}, ${coords.lng.toFixed(6)}`;
                    }
                }).catch(err => {
                    console.error("GPS coords capture failed:", err);
                    const infoDiv = document.getElementById('gps-status-info');
                    if (infoDiv) {
                        infoDiv.innerHTML = `⚠️ ไม่สามารถจับพิกัด GPS ได้`;
                    }
                });
            }

            // Set up BMI calculation triggers
            const w = document.getElementById('weight');
            const h = document.getElementById('height');
            [w, h].forEach(input => {
                input.addEventListener('input', calculateBmi);
            });

            // Set up CV Risk Score triggers
            const sbp1Input = document.getElementById('sys_bp1');
            const dbp1Input = document.getElementById('dia_bp1');
            const sbp2Input = document.getElementById('sys_bp2');
            const dbp2Input = document.getElementById('dia_bp2');
            const dtxInput = document.getElementById('dtx_value');
            
            [sbp1Input, sbp2Input, dtxInput].forEach(el => {
                if (el) el.addEventListener('input', calculateCvRisk);
            });
            
            [sbp1Input, dbp1Input, sbp2Input, dbp2Input, dtxInput].forEach(el => {
                if (el) {
                    el.addEventListener('input', function() {
                        isCriticalAcknowledged = false;
                    });
                }
            });
            
            document.querySelectorAll('input[name="dtx_type"]').forEach(radio => {
                radio.addEventListener('change', calculateCvRisk);
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
            if (!isSandboxMode) return;
            const btnHome = document.getElementById('btn-gps-home');
            const btnDrift = document.getElementById('btn-gps-drift');
            const infoDiv = document.getElementById('gps-status-info');
            
            if (!btnHome || !btnDrift || !infoDiv) return;
            
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

        function selectResident(assignId, name, sex, birth, needDm, needHt, origin, latVal, lngVal, lastSbp, lastDbp, lastDtx, lastDtxType, card) {
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
                origin: origin,
                homeLat: latVal,
                homeLng: lngVal,
                lastSbp: lastSbp ? parseInt(lastSbp) : null,
                lastDbp: lastDbp ? parseInt(lastDbp) : null,
                lastDtx: lastDtx ? parseInt(lastDtx) : null,
                lastDtxType: lastDtxType || 'fpg'
            };

            document.getElementById('assignment_id').value = assignId;
            document.getElementById('selected-resident-name').innerText = name;
            
            // Set home coordinates for GPS mock checks
            homeLat = parseFloat(latVal);
            homeLng = parseFloat(lngVal);
            if (isSandboxMode) {
                mockGps('home');
            }

            // Toggle sub-sections based on requirements
            const bpSection = document.getElementById('section-bp');
            const dtxSection = document.getElementById('section-dtx');

            bpSection.style.display = needHt ? 'block' : 'none';
            const showDtx = needDm || (origin === 'DM_ONLY' || origin === 'BOTH');
            dtxSection.style.display = showDtx ? 'block' : 'none';

            // Display historical BP and DTX values in UI
            const lastBpInfo = document.getElementById('last-bp-info');
            const lastDtxInfo = document.getElementById('last-dtx-info');

            if (lastBpInfo) {
                if (selectedResident.lastSbp && selectedResident.lastDbp) {
                    lastBpInfo.innerHTML = `⏳ ค่าความดันโลหิตล่าสุด: <strong style="color: var(--text-primary);">${selectedResident.lastSbp}/${selectedResident.lastDbp} mmHg</strong>`;
                    lastBpInfo.style.display = 'block';
                } else {
                    lastBpInfo.innerHTML = `⏳ ไม่มีประวัติค่าความดันเดิม`;
                    lastBpInfo.style.display = 'block';
                }
            }

            if (lastDtxInfo) {
                if (selectedResident.lastDtx) {
                    const typeName = selectedResident.lastDtxType === 'fpg' ? 'งดอาหาร' : 'ไม่ได้งดอาหาร';
                    lastDtxInfo.innerHTML = `⏳ ค่าน้ำตาลในเลือดล่าสุด: <strong style="color: var(--text-primary);">${selectedResident.lastDtx} mg/dL (${typeName})</strong>`;
                    lastDtxInfo.style.display = 'block';
                } else {
                    lastDtxInfo.innerHTML = `⏳ ไม่มีประวัติค่าน้ำตาลเดิม`;
                    lastDtxInfo.style.display = 'block';
                }
            }

            // Show next button
            document.getElementById('btn-next-resident').style.display = 'block';

            // Trigger initial calculations
            calculateCvRisk();
            calculateBmi();

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

            activeNumPad = new VhvNumPad(inputId, 'numpad-container', 'numpad-display-box');
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
            
            const sbp1 = parseFloat(document.getElementById('sys_bp1').value) || 0;
            const sbp2 = parseFloat(document.getElementById('sys_bp2').value) || 0;
            
            let sbp = 120;
            let usingHistoricalBp = false;
            
            if (sbp1 > 0 && sbp2 > 0) {
                sbp = (sbp1 + sbp2) / 2;
            } else if (sbp1 > 0) {
                sbp = sbp1;
            } else if (sbp2 > 0) {
                sbp = sbp2;
            } else if (selectedResident.lastSbp > 0) {
                sbp = selectedResident.lastSbp;
                usingHistoricalBp = true;
            }
            
            const dtxValInput = parseFloat(document.getElementById('dtx_value').value) || 0;
            let dtx = 90;
            let usingHistoricalDtx = false;
            
            if (dtxValInput > 0) {
                dtx = dtxValInput;
            } else if (selectedResident.lastDtx > 0) {
                dtx = selectedResident.lastDtx;
                usingHistoricalDtx = true;
            }
            
            const dtxType = dtxValInput > 0 
                ? (document.querySelector('input[name="dtx_type"]:checked')?.value || 'fpg')
                : (selectedResident.lastDtxType || 'fpg');
            
            // Check if patient already has diabetes or screens positive for diabetes
            const hasDm = (selectedResident.origin === 'DM_ONLY' || selectedResident.origin === 'BOTH') || 
                          (selectedResident.needDm && (dtxType === 'fpg' ? dtx >= 126 : dtx >= 200));

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

            // Update detailed BP and DTX helper labels
            const bpValDisplay = document.getElementById('cv-risk-bp-val');
            const dtxValDisplay = document.getElementById('cv-risk-dtx-val');

            if (bpValDisplay) {
                if (sbp1 > 0 || sbp2 > 0) {
                    const dia1 = parseFloat(document.getElementById('dia_bp1').value) || 0;
                    const dia2 = parseFloat(document.getElementById('dia_bp2').value) || 0;
                    const dispBp = (sbp1 > 0 && sbp2 > 0) 
                        ? `${Math.round(sbp1)}/${Math.round(dia1)} และ ${Math.round(sbp2)}/${Math.round(dia2)}` 
                        : (sbp1 > 0 ? `${Math.round(sbp1)}/${Math.round(dia1)}` : `${Math.round(sbp2)}/${Math.round(dia2)}`);
                    bpValDisplay.innerText = `${dispBp} mmHg`;
                } else if (usingHistoricalBp && selectedResident.lastSbp > 0) {
                    bpValDisplay.innerText = `${selectedResident.lastSbp}/${selectedResident.lastDbp} mmHg (ประวัติเดิม)`;
                } else {
                    bpValDisplay.innerText = 'รอวัดความดัน';
                }
            }

            if (dtxValDisplay) {
                if (dtxValInput > 0) {
                    const dtxTypeName = dtxType === 'fpg' ? 'งดอาหาร' : 'ไม่ได้งดอาหาร';
                    dtxValDisplay.innerText = `${Math.round(dtxValInput)} mg/dL (${dtxTypeName})`;
                } else if (usingHistoricalDtx && selectedResident.lastDtx > 0) {
                    const histTypeName = selectedResident.lastDtxType === 'fpg' ? 'งดอาหาร' : 'ไม่ได้งดอาหาร';
                    dtxValDisplay.innerText = `${Math.round(selectedResident.lastDtx)} mg/dL (${histTypeName}) (ประวัติเดิม)`;
                } else {
                    dtxValDisplay.innerText = 'รอตรวจน้ำตาล';
                }
            }
        }

        // Submit Screening Data
        function submitScreening() {
            if (!selectedResident) {
                alert("กรุณาเลือกผู้รับการคัดกรอง");
                return;
            }

            // Verify GPS coordinates (Skip verification ONLY if in Sandbox Mode)
            if (!isSandboxMode) {
                const latVal = parseFloat(document.getElementById('screening_lat').value) || 0;
                const lngVal = parseFloat(document.getElementById('screening_lng').value) || 0;
                if (latVal === 0 || lngVal === 0) {
                    alert("⚠️ ไม่พบพิกัดตำแหน่งมือถือของท่าน\n\nกรุณาเปิดระบบ GPS ในโทรศัพท์มือถือ หรือกดปุ่ม 'อนุญาต' (Allow) สิทธิ์ระบุพิกัดที่มุมจอ จากนั้นรอสักครู่จนกว่าจะขึ้นพิกัดตัวเลขตรงแถบ 📍 ด้านบน แล้วกดบันทึกส่งงานใหม่อีกครั้งครับ");
                    return;
                }
            }

            // Validation logic
            const w = parseFloat(document.getElementById('weight').value) || 0;
            const h = parseFloat(document.getElementById('height').value) || 0;
            if (w <= 0 || h <= 0) {
                alert("กรุณากรอกข้อมูล น้ำหนัก และ ส่วนสูง ให้ครบถ้วน");
                return;
            }

            if (selectedResident.needHt) {
                const sys1 = parseInt(document.getElementById('sys_bp1').value) || 0;
                const dia1 = parseInt(document.getElementById('dia_bp1').value) || 0;
                if (sys1 <= 0 || dia1 <= 0) {
                    alert("กรุณากรอกค่าความดันโลหิต (ตัวบนและตัวล่าง) ให้ครบถ้วน");
                    return;
                }
            }

            /* ซ่อนส่วนค่าน้ำตาลไว้ชั่วคราวตามที่ผู้ใช้ร้องขอ จึงข้ามการตรวจสอบนี้
            if (selectedResident.needDm) {
                const dtx = parseInt(document.getElementById('dtx_value').value) || 0;
                if (dtx <= 0) {
                    alert("กรุณากรอกระดับน้ำตาลในเลือด (DTX)");
                    return;
                }
            }
            */

            if (!checkCriticalValues()) {
                return;
            }

            const form = document.getElementById('screening-form');
            const formData = new FormData(form);
            
            // Add custom action parameter
            formData.append('action', 'save_screening');
            formData.append('cv_risk_score', parseFloat(document.getElementById('cv-risk-display').innerText));

            // Check if offline
            if (!navigator.onLine) {
                const serialized = {};
                formData.forEach((value, key) => {
                    serialized[key] = value;
                });
                serialized.cv_risk_score = parseFloat(document.getElementById('cv-risk-display').innerText);
                serialized._timestamp = Date.now();
                serialized._type = 'screening';
                serialized._residentName = selectedResident.name;
                
                const queue = JSON.parse(localStorage.getItem('offline_submissions') || '[]');
                queue.push(serialized);
                localStorage.setItem('offline_submissions', JSON.stringify(queue));
                
                updateLocalTask(selectedResident.assignmentId, 'completed');
                
                showToast("บันทึกข้อมูลคัดกรองในเครื่องเรียบร้อยแล้ว (โหมดออฟไลน์)", "warning");
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);
                return;
            }

            // Send to save_screening endpoint
            fetch('../api/save_screening.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.is_hl_coach) {
                        showHlCoachModal(data.hl_risk_level);
                    } else {
                        alert("บันทึกการคัดกรองเรียบร้อยแล้ว! อสม. ได้รับ +1 คะแนนสะสม");
                        window.location.href = 'index.php';
                    }
                } else {
                    alert("เกิดข้อผิดพลาดในการบันทึก: " + data.message);
                }
            })
            .catch(err => {
                alert("เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย: " + err);
            });
        }

        let isCriticalAcknowledged = false;

        function showCriticalModal(sbp, dbp, dtx, hasCriticalBp, hasCriticalDtx) {
            const valuesDiv = document.getElementById('critical-alert-values');
            const adviceDiv = document.getElementById('critical-alert-advice');
            
            let valHtml = '';
            let adviceHtml = '';

            if (hasCriticalBp) {
                valHtml += `❤️ ความดันโลหิตสูงวิกฤต: ${sbp}/${dbp} mmHg<br>`;
                adviceHtml += `
                    <div>
                        <strong style="color: white; display: block; margin-bottom: 4px;">🩸 ภาวะความดันโลหิตสูงวิกฤต (Hypertensive Crisis):</strong>
                        1. <strong>จัดท่าทาง:</strong> ให้นั่งพักในท่าที่สบาย เอนหลังได้ ในที่สงบ อากาศถ่ายเทสะดวก พัก 15 นาที แล้วค่อยวัดซ้ำ<br>
                        2. <strong>ห้ามออกกำลังกาย:</strong> งดการทำกิจกรรมเคลื่อนไไหลร่างกายรุนแรง ห้ามดื่มน้ำเย็นจัด ชา กาแฟ หรือสูบบุหรี่<br>
                        3. <strong>สังเกตอาการอันตราย:</strong> หากมีอาการปวดศีรษะรุนแรง ตาพร่ามัว เจ็บแน่นหน้าอก หายใจหอบเหนื่อย ปากเบี้ยว หน้าเบี้ยว หรือแขนขาอ่อนแรง <strong>ให้รีบแจ้งเจ้าหน้าที่ รพ.สต. หรือโทรสายด่วน 1669 ส่งโรงพยาบาลทันที!</strong>
                    </div>
                `;
            }

            if (hasCriticalDtx) {
                valHtml += `🍭 ระดับน้ำตาลในเลือดสูงวิกฤต: ${dtx} mg/dL<br>`;
                adviceHtml += `
                    <div>
                        <strong style="color: white; display: block; margin-bottom: 4px;">🍬 ภาวะระดับน้ำตาลในเลือดสูงวิกฤต (Severe Hyperglycemia):</strong>
                        1. <strong>ดื่มน้ำสะอาด:</strong> ให้ดื่มน้ำเปล่าปริมาณมากๆ เพื่อช่วยขับน้ำตาลส่วนเกินออกจากร่างกายผ่านทางปัสสาวะ (หลีกเลี่ยงน้ำหวานหรือแอลกอฮอล์)<br>
                        2. <strong>สังเกตอาการขาดน้ำ/คีโตนคั่ง:</strong> เช่น กระหายน้ำรุนแรง ปัสสาวะบ่อย ซึมลง สับสน มึนงง อ่อนเพลียมาก คลื่นไส้ อาเจียน หายใจหอบลึก หรือลมหายใจมีกลิ่นคล้ายผลไม้<br>
                        3. <strong>ส่งแพทย์ด่วน:</strong> หากมีอาการซึมลง สับสน หรืออาเจียน <strong>ให้รีบนำส่งสถานพยาบาลหรือโทร 1669 ทันที!</strong>
                    </div>
                `;
            }

            valuesDiv.innerHTML = valHtml;
            adviceDiv.innerHTML = adviceHtml;
            
            document.getElementById('critical-alert-modal').style.display = 'flex';
        }

        function closeCriticalModal() {
            document.getElementById('critical-alert-modal').style.display = 'none';
        }

        function checkCriticalValues() {
            if (isCriticalAcknowledged) {
                return true;
            }

            const sbp1 = parseInt(document.getElementById('sys_bp1').value) || 0;
            const dbp1 = parseInt(document.getElementById('dia_bp1').value) || 0;
            const sbp2 = parseInt(document.getElementById('sys_bp2').value) || 0;
            const dbp2 = parseInt(document.getElementById('dia_bp2').value) || 0;
            const dtx = parseInt(document.getElementById('dtx_value').value) || 0;

            const sbpMax = Math.max(sbp1, sbp2);
            const dbpMax = Math.max(dbp1, dbp2);

            let hasCriticalBp = sbpMax >= 180 || dbpMax >= 110;
            let hasCriticalDtx = dtx >= 300;

            if (hasCriticalBp || hasCriticalDtx) {
                showCriticalModal(sbpMax, dbpMax, dtx, hasCriticalBp, hasCriticalDtx);
                return false;
            }

            return true;
        }

        document.getElementById('btn-confirm-critical-save').onclick = function() {
            isCriticalAcknowledged = true;
            closeCriticalModal();
            submitScreening();
        };

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

            // Verify GPS coordinates (Skip verification ONLY if in Sandbox Mode)
            if (!isSandboxMode) {
                const latVal = parseFloat(gpsLocation.lat) || 0;
                const lngVal = parseFloat(gpsLocation.lng) || 0;
                if (latVal === 0 || lngVal === 0) {
                    alert("⚠️ ไม่พบพิกัดตำแหน่งมือถือของท่าน\n\nกรุณาเปิดระบบ GPS ในโทรศัพท์มือถือ หรือกดปุ่ม 'อนุญาต' (Allow) สิทธิ์ระบุพิกัดที่มุมจอ เพื่อทำการส่งเรื่องข้ามเคสครับ");
                    return;
                }
            }

            if (!navigator.onLine) {
                const data = {
                    'action': 'skip_case',
                    'assignment_id': assignId,
                    'skipped_reason': reason,
                    'lat': gpsLocation.lat,
                    'lng': gpsLocation.lng,
                    '_timestamp': Date.now(),
                    '_type': 'skip_case',
                    '_residentName': selectedResident.name
                };
                
                const queue = JSON.parse(localStorage.getItem('offline_submissions') || '[]');
                queue.push(data);
                localStorage.setItem('offline_submissions', JSON.stringify(queue));
                
                updateLocalTask(assignId, 'skipped', reason);
                
                showToast("บันทึกการข้ามเคสในเครื่องเรียบร้อยแล้ว (โหมดออฟไลน์)", "warning");
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);
                return;
            }

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

        function toggleAdviceCard(card) {
            card.classList.toggle('selected');
            
            const selected = [];
            document.querySelectorAll('.advice-image-card.selected').forEach(activeCard => {
                selected.push(activeCard.getAttribute('data-text'));
            });
            
            document.getElementById('advice_given').value = selected.join(', ');
        }
    </script>
</body>
</html>
