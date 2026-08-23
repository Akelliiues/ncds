<?php
// vhv/screening_form.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/demo_banner.php';
require_once __DIR__ . '/../config/demo_data.php';

$isShell = isset($_GET['shell']) && $_GET['shell'] === 'true';
$hid = $_GET['hid'] ?? '';
$cid = $_GET['cid'] ?? '';
$code = !empty($hid) ? $hid : $cid;

if (!isset($_SESSION['vhv_id'])) {
    $redirectUrl = '../qr.php' . (!empty($code) ? '?code=' . urlencode($code) : '');
    header("Location: " . $redirectUrl);
    exit();
}

if (!$isShell && empty($hid) && empty($cid)) {
    header("Location: scan.php");
    exit();
}

$vhvId = $_SESSION['vhv_id'];
$hoscode = $_SESSION['hoscode'] ?? null;
$residents = [];
$history = [];

if (DemoDataProvider::isDemoMode()) {
    $allTargets = DemoDataProvider::getMockTargets();
    if (!empty($cid)) {
        $filtered = array_values(array_filter($allTargets, function($r) use ($cid) { return $r['cid'] === $cid; }));
        $residents = !empty($filtered) ? $filtered : [$allTargets[0]];
    } elseif (!empty($hid)) {
        $cleanHid = trim(preg_replace('/^(บ้านเลขที่|บ้าน|ม\.)\s*/u', '', $hid));
        if ($hid === 'DEMO_HOUSE_12_1' || $hid === 'DEMO_HID_1') $cleanHid = '12/1';
        elseif ($hid === 'DEMO_HOUSE_88_2') $cleanHid = '88';
        elseif ($hid === 'DEMO_HOUSE_101_2') $cleanHid = '101';
        elseif ($hid === 'DEMO_HOUSE_15_3') $cleanHid = '15/3';
        elseif ($hid === 'DEMO_HOUSE_54_4') $cleanHid = '54';
        elseif ($hid === 'DEMO_HOUSE_9_5') $cleanHid = '9/1';
        
        $filtered = array_values(array_filter($allTargets, function($r) use ($hid, $cleanHid) { 
            return $r['house_no'] === $hid || $r['house_no'] === $cleanHid || 
                   $r['cid'] === $hid || $r['cid'] === $cleanHid || 
                   (isset($r['assignment_id']) && ($r['assignment_id'] === $hid || $r['assignment_id'] === $cleanHid)); 
        }));
        $residents = !empty($filtered) ? $filtered : [$allTargets[0]];
    } else {
        $residents = DemoDataProvider::getDemoVhvTasks()['pending'];
    }

    foreach ($residents as &$res) {
        if (empty($res['assignment_id'])) {
            $res['assignment_id'] = 'DEMO_ASSIGN_' . substr($res['cid'], -2);
        }
    }
    unset($res);
} elseif (!$isShell) {
    // Auto-assign task if no pending assignment exists yet
    $currentBudgetYear = function_exists('get_current_budget_year') ? get_current_budget_year() : 2026;
    $isSandboxVal = isSandboxMode($hoscode) ? 1 : 0;
    if (!empty($hid)) {
        $checkStmt = $pdo->prepare("SELECT cid FROM target_population WHERE CAST(hid AS UNSIGNED) = CAST(? AS UNSIGNED)");
        $checkStmt->execute([$hid]);
        $targets = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($targets)) {
            $ins = $pdo->prepare("INSERT IGNORE INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status, is_sandbox) VALUES (?, ?, ?, 'pending', ?)");
            foreach ($targets as $tc) {
                $ins->execute([$tc, $vhvId, $currentBudgetYear, $isSandboxVal]);
            }
        }
    } elseif (!empty($cid)) {
        $checkStmt = $pdo->prepare("SELECT cid FROM target_population WHERE cid = ? LIMIT 1");
        $checkStmt->execute([$cid]);
        $pop = $checkStmt->fetch();
        if ($pop) {
            $ins = $pdo->prepare("INSERT IGNORE INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status, is_sandbox) VALUES (?, ?, ?, 'pending', ?)");
            $ins->execute([$cid, $vhvId, $currentBudgetYear, $isSandboxVal]);
        }
    }

    // Fetch residents based on hid or cid
    $isSandboxVal = isSandboxMode($hoscode) ? 1 : 0;
    if (!empty($hid)) {
        $residentsStmt = $pdo->prepare("
            SELECT p.*, a.assignment_id, a.round_number,
                   COALESCE(
                       (SELECT sr.sys_bp1 FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1),
                       (SELECT ht.sbp FROM staging_hdc_ht ht WHERE ht.cid = p.cid ORDER BY ht.imported_at DESC LIMIT 1)
                   ) AS last_sbp,
                   COALESCE(
                       (SELECT sr.dia_bp1 FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1),
                       (SELECT ht.dbp FROM staging_hdc_ht ht WHERE ht.cid = p.cid ORDER BY ht.imported_at DESC LIMIT 1)
                   ) AS last_dbp,
                   COALESCE(
                       (SELECT sr.dtx_value FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1),
                       (SELECT dm.bslevel FROM staging_hdc_dm dm WHERE dm.cid = p.cid ORDER BY dm.imported_at DESC LIMIT 1)
                   ) AS last_dtx,
                   COALESCE(
                       (SELECT sr.dtx_type FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1),
                       'fpg'
                   ) AS last_dtx_type
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            WHERE CAST(p.hid AS UNSIGNED) = CAST(? AS UNSIGNED) AND a.vhv_id = ? AND a.budget_year = ? AND a.assignment_status IN ('pending', 'skipped') AND a.is_sandbox = ?
              AND (
                  (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
                  OR 
                  (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
                  OR
                  p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
                  OR
                  COALESCE(p.is_manual, 0) = 1
                  OR
                  a.assignment_id IS NOT NULL
              )
        ");
        $residentsStmt->execute([$hid, $vhvId, $currentBudgetYear, $isSandboxVal]);
        $residents = $residentsStmt->fetchAll();

        if (empty($residents)) {
            $historyStmt = $pdo->prepare("
                SELECT p.*, a.assignment_status
                FROM task_assignments a
                JOIN target_population p ON a.target_cid = p.cid
                WHERE CAST(p.hid AS UNSIGNED) = CAST(? AS UNSIGNED) AND a.vhv_id = ? AND a.budget_year = ? AND a.is_sandbox = ?
                  AND (
                      (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
                      OR 
                      (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
                      OR
                      p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
                      OR
                      COALESCE(p.is_manual, 0) = 1
                      OR
                      a.assignment_id IS NOT NULL
                  )
            ");
            $historyStmt->execute([$hid, $vhvId, $currentBudgetYear, $isSandboxVal]);
            $history = $historyStmt->fetchAll();
        }
    } else {
        $residentsStmt = $pdo->prepare("
            SELECT p.*, a.assignment_id, a.round_number,
                   COALESCE(
                       (SELECT sr.sys_bp1 FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1),
                       (SELECT ht.sbp FROM staging_hdc_ht ht WHERE ht.cid = p.cid ORDER BY ht.imported_at DESC LIMIT 1)
                   ) AS last_sbp,
                   COALESCE(
                       (SELECT sr.dia_bp1 FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1),
                       (SELECT ht.dbp FROM staging_hdc_ht ht WHERE ht.cid = p.cid ORDER BY ht.imported_at DESC LIMIT 1)
                   ) AS last_dbp,
                   COALESCE(
                       (SELECT sr.dtx_value FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1),
                       (SELECT dm.bslevel FROM staging_hdc_dm dm WHERE dm.cid = p.cid ORDER BY dm.imported_at DESC LIMIT 1)
                   ) AS last_dtx,
                   COALESCE(
                       (SELECT sr.dtx_type FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1),
                       'fpg'
                   ) AS last_dtx_type
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            WHERE p.cid = ? AND a.vhv_id = ? AND a.budget_year = ? AND a.assignment_status IN ('pending', 'skipped') AND a.is_sandbox = ?
              AND (
                  (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
                  OR 
                  (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
                  OR
                  p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
                  OR
                  COALESCE(p.is_manual, 0) = 1
                  OR
                  a.assignment_id IS NOT NULL
              )
        ");
        $residentsStmt->execute([$cid, $vhvId, $currentBudgetYear, $isSandboxVal]);
        $residents = $residentsStmt->fetchAll();

        if (empty($residents)) {
            $historyStmt = $pdo->prepare("
                SELECT p.*, a.assignment_status
                FROM task_assignments a
                JOIN target_population p ON a.target_cid = p.cid
                WHERE p.cid = ? AND a.vhv_id = ? AND a.budget_year = ? AND a.is_sandbox = ?
                  AND (
                      (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
                      OR 
                      (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
                      OR
                      p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
                      OR
                      COALESCE(p.is_manual, 0) = 1
                      OR
                      a.assignment_id IS NOT NULL
                  )
            ");
            $historyStmt->execute([$cid, $vhvId, $currentBudgetYear, $isSandboxVal]);
            $history = $historyStmt->fetchAll();
        }
    }
}

$isDemo = DemoDataProvider::isDemoMode();
$activeResident = !empty($residents) ? $residents[0] : null;
$activeName = $activeResident ? ($activeResident['first_name'] . ' ' . $activeResident['last_name']) : '';
$activeAssignId = $activeResident ? ($activeResident['assignment_id'] ?? 'DEMO_ASSIGN_1') : '';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NCDs ตาลสุม">
    <meta name="theme-color" content="#0d2c54">
    <title>ฟอร์มคัดกรองโรคเรื้อรัง - อสม. ตาลสุม</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="apple-touch-icon" href="../assets/icon.png">
    <link rel="manifest" href="manifest.json">
    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/clinical_guidance.js"></script>
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

        /* Modern 3อ. 2ส. Lifestyle Selector Cards */
        .behavior-grid-2x2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .behavior-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .behavior-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-bottom: 20px;
        }
        .behavior-card-item {
            position: relative;
            cursor: pointer;
            user-select: none;
            display: block;
        }
        .behavior-card-item input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .behavior-card-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 12px 6px;
            min-height: 80px;
            background-color: var(--bg-card);
            border: 1.5px solid var(--border-color, rgba(148, 163, 184, 0.2));
            border-radius: 16px;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            box-sizing: border-box;
        }
        .behavior-card-icon {
            font-size: 24px;
            line-height: 1;
            margin-bottom: 5px;
            transition: transform 0.2s ease;
        }
        .behavior-card-title {
            font-size: 13px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.25;
            letter-spacing: -0.2px;
        }
        .behavior-card-desc {
            font-size: 10.5px;
            color: var(--text-muted);
            margin-top: 2px;
            line-height: 1.2;
            font-weight: 600;
        }

        /* Checked State Styling by Color Tier */
        .behavior-green input[type="radio"]:checked + .behavior-card-box {
            background: rgba(16, 185, 129, 0.08) !important;
            border-color: #10B981 !important;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25), var(--neumorph-inset) !important;
        }
        .behavior-green input[type="radio"]:checked + .behavior-card-box .behavior-card-title {
            color: #10B981 !important;
        }
        .behavior-green input[type="radio"]:checked + .behavior-card-box .behavior-card-icon {
            transform: scale(1.18);
        }

        .behavior-yellow input[type="radio"]:checked + .behavior-card-box {
            background: rgba(245, 158, 11, 0.08) !important;
            border-color: #F59E0B !important;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.25), var(--neumorph-inset) !important;
        }
        .behavior-yellow input[type="radio"]:checked + .behavior-card-box .behavior-card-title {
            color: #D97706 !important;
        }
        .behavior-yellow input[type="radio"]:checked + .behavior-card-box .behavior-card-icon {
            transform: scale(1.18);
        }

        .behavior-orange input[type="radio"]:checked + .behavior-card-box {
            background: rgba(234, 88, 12, 0.08) !important;
            border-color: #EA580C !important;
            box-shadow: 0 4px 14px rgba(234, 88, 12, 0.25), var(--neumorph-inset) !important;
        }
        .behavior-orange input[type="radio"]:checked + .behavior-card-box .behavior-card-title {
            color: #EA580C !important;
        }
        .behavior-orange input[type="radio"]:checked + .behavior-card-box .behavior-card-icon {
            transform: scale(1.18);
        }

        .behavior-red input[type="radio"]:checked + .behavior-card-box {
            background: rgba(220, 38, 38, 0.08) !important;
            border-color: #DC2626 !important;
            box-shadow: 0 4px 14px rgba(220, 38, 38, 0.25), var(--neumorph-inset) !important;
        }
        .behavior-red input[type="radio"]:checked + .behavior-card-box .behavior-card-title {
            color: #DC2626 !important;
        }
        .behavior-red input[type="radio"]:checked + .behavior-card-box .behavior-card-icon {
            transform: scale(1.18);
        }

        /* Toggle groups legacy */
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

        <?php 
        $isDemo = DemoDataProvider::isDemoMode();
        $activeResident = (!empty($residents)) ? $residents[0] : null;
        $activeName = $activeResident ? htmlspecialchars($activeResident['first_name'] . ' ' . $activeResident['last_name']) : 'สมชาย ใจดี (จำลอง)';
        $activeAssignId = $activeResident ? $activeResident['assignment_id'] : 'DEMO_ASSIGN_1';
        ?>
        <?php if (empty($residents) && !$isShell && !$isDemo): ?>
            <div class="card-dark" style="text-align: center; padding: 40px 20px;">
                <span style="font-size: 48px; display: block; margin-bottom: 16px;">✅</span>
                <h3 style="color: var(--color-green); font-size: 22px; margin-bottom: 8px;">คัดกรองเรียบร้อยแล้ว</h3>
                <p style="color: var(--text-secondary); margin-bottom: 24px;">สมาชิกทั้งหมดในบ้านเลขที่นี้ได้รับการคัดกรองเสร็จสิ้นเรียบร้อยแล้วในรอบการคัดกรองนี้</p>
                <a href="index.php" class="btn-giant btn-giant-primary">กลับหน้าหลัก</a>
            </div>
        <?php else: ?>
            <form id="screening-form" action="" method="POST">
                <input type="hidden" name="assignment_id" id="assignment_id" value="<?= $isDemo ? $activeAssignId : '' ?>">
                <input type="hidden" name="screening_lat" id="screening_lat" value="<?= $isDemo ? '15.430000' : '' ?>">
                <input type="hidden" name="screening_lng" id="screening_lng" value="<?= $isDemo ? '104.980000' : '' ?>">

                <!-- STEP 1: Select Resident -->
                <div id="step-resident" class="step-section <?= $isDemo ? '' : 'active' ?>">
                    <span class="form-label-big">1. เลือกบุคคลที่ต้องการคัดกรอง</span>
                    
                    <div id="residents-container">
                    <?php if (!$isShell): ?>
                        <?php foreach ($residents as $r): ?>
                            <div class="resident-card <?= ($isDemo && $r['assignment_id'] === $activeAssignId) ? 'selected' : '' ?>" onclick="selectResident('<?= $r['assignment_id'] ?>', '<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name'], ENT_QUOTES) ?>', '<?= $r['sex'] ?>', '<?= $r['birth'] ?>', <?= $r['need_screen_dm'] ? 'true' : 'false' ?>, <?= $r['need_screen_ht'] ? 'true' : 'false' ?>, '<?= htmlspecialchars($r['health_status_origin'] ?? 'NORMAL', ENT_QUOTES) ?>', <?= (float)($r['latitude'] ?? 0) ?>, <?= (float)($r['longitude'] ?? 0) ?>, <?= $r['last_sbp'] !== null ? (int)$r['last_sbp'] : 'null' ?>, <?= $r['last_dbp'] !== null ? (int)$r['last_dbp'] : 'null' ?>, <?= $r['last_dtx'] !== null ? (int)$r['last_dtx'] : 'null' ?>, '<?= htmlspecialchars($r['last_dtx_type'] ?? 'fpg', ENT_QUOTES) ?>', this)">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="font-size: 18px; color: var(--text-primary);"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                                        <?php if (intval($r['round_number'] ?? 1) > 1): ?>
                                            <span style="background-color: rgba(99, 102, 241, 0.15); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.3); padding: 2px 6px; border-radius: 10px; font-size: 12px; font-weight: bold; margin-left: 6px;">
                                                🔄 คัดกรองซ้ำ ครั้งที่ <?= $r['round_number'] ?>
                                            </span>
                                        <?php endif; ?>
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
                                    <span style="font-size: 24px; color: var(--border-color);" class="select-indicator"><?= ($isDemo && $r['assignment_id'] === $activeAssignId) ? '🟡' : '⚪' ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                    
                    <button type="button" onclick="nextStep('step-vital')" class="btn-giant btn-giant-primary" id="btn-next-resident" style="margin-top: 20px; display: <?= $isDemo ? 'block' : 'none' ?>;">
                        ถัดไป (คัดกรองร่างกาย) →
                    </button>
                    
                    <button type="button" onclick="openSkipModal()" class="btn-giant btn-giant-secondary" style="margin-top: 12px;">
                        ไม่อยู่บ้าน / ทำนา (ข้ามเคส)
                    </button>
                </div>

                <!-- STEP 2: Vital Signs & Measurements (Consolidated) -->
                <div id="step-vital" class="step-section <?= $isDemo ? 'active' : '' ?>">
                    <div class="card-dark" style="padding: 16px; margin-bottom: 20px;">
                        <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">ชื่อผู้รับการคัดกรอง:</span>
                        <div id="selected-resident-name" style="font-size: 20px; font-weight: 800; color: var(--color-accent); margin-top: 4px;"><?= $isDemo ? $activeName : '' ?></div>
                        <?php if ($isDemo && !empty($activeResident)): ?>
                        <div style="margin-top: 6px; font-size: 12px; color: var(--text-muted); display: flex; gap: 8px; flex-wrap: wrap;">
                            <span>บ้านเลขที่ <?= htmlspecialchars($activeResident['house_no']) ?> ม.<?= htmlspecialchars($activeResident['moo']) ?></span>
                            <span>•</span>
                            <span>รอบที่ <?= htmlspecialchars($activeResident['round_number'] ?? 1) ?></span>
                            <?php if (!empty($activeResident['last_sbp'])): ?>
                            <span>•</span>
                            <span style="color: var(--color-primary); font-weight: 600;">ค่าเดิม: <?= $activeResident['last_sbp'] ?>/<?= $activeResident['last_dbp'] ?> mmHg, DTX: <?= $activeResident['last_dtx'] ?> mg/dL</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($isDemo): ?>
                    <!-- Demo Quick Preset Testing Toolbar -->
                    <div class="card-dark" style="padding: 14px; margin-bottom: 20px; border: 1.5px dashed #3B82F6; background: rgba(59, 130, 246, 0.04); border-radius: var(--border-radius);">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #3B82F6; font-size: 13.5px; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                                🧪 ข้อมูลจำลองเพื่อเปรียบเทียบผล
                            </span>
                            <span style="font-size: 10px; background: #3B82F6; color: white; padding: 2px 6px; border-radius: 9999px; font-weight: bold;">1-Click</span>
                        </div>
                        <p style="color: var(--text-secondary); font-size: 12px; margin: 0 0 10px 0; line-height: 1.4;">
                            เลือกชุดค่าตรวจเพื่อทดสอบการประเมินความเสี่ยงและเปรียบเทียบผล:
                        </p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <button type="button" onclick="applyDemoPreset('normal')" style="padding: 8px 6px; border-radius: 10px; border: 1px solid rgba(16, 185, 129, 0.4); background: rgba(16, 185, 129, 0.08); color: #10B981; font-size: 12px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                🟢 สุขภาพปกติ (118/76)
                            </button>
                            <button type="button" onclick="applyDemoPreset('risk')" style="padding: 8px 6px; border-radius: 10px; border: 1px solid rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.08); color: #D97706; font-size: 12px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                🟡 กลุ่มเสี่ยง (134/86)
                            </button>
                            <button type="button" onclick="applyDemoPreset('high_risk')" style="padding: 8px 6px; border-radius: 10px; border: 1px solid rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.08); color: #EF4444; font-size: 12px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                🟠 สงสัยป่วย (158/96)
                            </button>
                            <button type="button" onclick="applyDemoPreset('critical')" style="padding: 8px 6px; border-radius: 10px; border: 1px solid rgba(220, 38, 38, 0.4); background: rgba(220, 38, 38, 0.12); color: #B91C1C; font-size: 12px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                🔴 วิกฤตด่วน (185/112)
                            </button>
                        </div>
                        <?php if (intval($activeResident['round_number'] ?? 1) >= 2 || (isset($activeResident['health_case']) && $activeResident['health_case'] === 'round2_comparison')): ?>
                        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(59, 130, 246, 0.25); display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <button type="button" onclick="applyDemoPreset('round2_improved')" style="padding: 8px 6px; border-radius: 10px; border: 1.5px solid #10B981; background: #10B981; color: white; font-size: 11.5px; font-weight: 800; cursor: pointer;">
                                📈 รอบ 2: ผลดีขึ้นชัดเจน
                            </button>
                            <button type="button" onclick="applyDemoPreset('round2_worsened')" style="padding: 8px 6px; border-radius: 10px; border: 1.5px solid #F59E0B; background: #F59E0B; color: white; font-size: 11.5px; font-weight: 800; cursor: pointer;">
                                ⚠️ รอบ 2: ผลแย่ลง/เสี่ยงขึ้น
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

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
                            <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 6px;">น้ำหนัก (กก.) <span style="color: var(--color-red);">*</span></label>
                            <input type="number" step="0.1" inputmode="decimal" name="weight" id="weight" class="input-large" value="" oninput="calculateBmi()" onclick="openScrollPicker('weight', 'น้ำหนัก (กก.)', 30, 150, 60.0)" placeholder="0.0">
                        </div>
                        <div>
                            <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 6px;">ส่วนสูง (ซม.) <span style="color: var(--color-red);">*</span></label>
                            <input type="number" step="0.1" inputmode="decimal" name="height" id="height" class="input-large" value="" oninput="calculateBmi()" onclick="openScrollPicker('height', 'ส่วนสูง (ซม.)', 100, 220, 160.0)" placeholder="0.0">
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 15px; font-weight: 600; display: block; margin-bottom: 6px;">รอบเอว (นิ้ว) <span style="color: var(--color-red);">*</span></label>
                        <input type="number" step="0.1" inputmode="decimal" name="waist" id="waist" class="input-large" value="" oninput="calculateCvRisk()" onclick="openScrollPicker('waist', 'รอบเอว (นิ้ว)', 20, 60, 30.0)" placeholder="0.0">
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
                        <span style="color: var(--text-primary); font-size: 18px; font-weight: 800; display: block; margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">🩺 วัดความดันโลหิต <span style="color: var(--color-red); font-size: 14px;">*</span></span>
                        <div id="last-bp-info" class="card-dark neumorph-inset" style="padding: 10px 14px; font-size: 13.5px; color: var(--color-primary); font-weight: 700; margin-bottom: 14px; display: none; border-radius: var(--border-radius);"></div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <label style="font-size: 13px; color: var(--text-secondary);">ครั้งที่ 1 ตัวบน (SYS) <span style="color: var(--color-red);">*</span></label>
                                <input type="number" inputmode="numeric" name="sys_bp1" id="sys_bp1" class="input-large" value="" oninput="calculateCvRisk()" onclick="openNumPad('sys_bp1', 'ความดันตัวบน SYS1')" placeholder="0">
                            </div>
                            <div>
                                <label style="font-size: 13px; color: var(--text-secondary);">ครั้งที่ 1 ตัวล่าง (DIA) <span style="color: var(--color-red);">*</span></label>
                                <input type="number" inputmode="numeric" name="dia_bp1" id="dia_bp1" class="input-large" value="" oninput="calculateCvRisk()" onclick="openNumPad('dia_bp1', 'ความดันตัวล่าง DIA1')" placeholder="0">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label style="font-size: 13px; color: var(--text-secondary);">ครั้งที่ 2 ตัวบน (SYS) (ถ้ามี)</label>
                                <input type="number" inputmode="numeric" name="sys_bp2" id="sys_bp2" class="input-large" value="" oninput="calculateCvRisk()" onclick="openNumPad('sys_bp2', 'ความดันตัวบน SYS2')" placeholder="0">
                            </div>
                            <div>
                                <label style="font-size: 13px; color: var(--text-secondary);">ครั้งที่ 2 ตัวล่าง (DIA) (ถ้ามี)</label>
                                <input type="number" inputmode="numeric" name="dia_bp2" id="dia_bp2" class="input-large" value="" oninput="calculateCvRisk()" onclick="openNumPad('dia_bp2', 'ความดันตัวล่าง DIA2')" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <!-- Blood Sugar DTX section -->
                    <div id="section-dtx" style="display: <?= $isDemo ? 'block' : 'none' ?>; margin-bottom: 24px;">
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
                            <input type="number" inputmode="numeric" name="dtx_value" id="dtx_value" class="input-large" value="" oninput="calculateCvRisk()" onclick="openNumPad('dtx_value', 'ระดับน้ำตาล DTX')" placeholder="0">
                        </div>
                    </div>

                    <!-- Behavior Toggles (3อ. 2ส.) -->
                    <span class="form-label-big" style="font-size: 18px; margin-top: 10px; border-top: 1px solid var(--border-color); padding-top: 15px;">🥗 พฤติกรรมสุขภาพ (3อ. 2ส.)</span>

                    <!-- 1. อาหาร (Food Behavior - 2x2 Grid) -->
                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 14.5px; font-weight: 700; display: block; margin-bottom: 8px;">🥬 อาหาร (เน้นรสหวาน มัน เค็ม)</label>
                        <div class="behavior-grid-2x2">
                            <label class="behavior-card-item behavior-green">
                                <input type="radio" name="diet_risk" value="green" checked>
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🥗</span>
                                    <div class="behavior-card-title">ทานปกติ</div>
                                    <div class="behavior-card-desc">ครบ 5 หมู่ / ไม่จัด</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-yellow">
                                <input type="radio" name="diet_risk" value="yellow">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🍰</span>
                                    <div class="behavior-card-title">ชอบหวาน</div>
                                    <div class="behavior-card-desc">ขนม / น้ำหวาน / ชา</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-orange">
                                <input type="radio" name="diet_risk" value="orange">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🍟</span>
                                    <div class="behavior-card-title">ชอบมัน</div>
                                    <div class="behavior-card-desc">ของทอด / แกงกะทิ</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-red">
                                <input type="radio" name="diet_risk" value="red">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🧂</span>
                                    <div class="behavior-card-title">ชอบเค็ม / ปลาร้า</div>
                                    <div class="behavior-card-desc">รสจัด / แจ่วบอง / ซดน้ำ</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 2. การออกกำลังกาย (Exercise - 2 Grid) -->
                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 14.5px; font-weight: 700; display: block; margin-bottom: 8px;">🏃‍♂️ การออกกำลังกาย</label>
                        <div class="behavior-grid-2">
                            <label class="behavior-card-item behavior-green">
                                <input type="radio" name="exercise_risk" value="green" checked>
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🏃‍♂️</span>
                                    <div class="behavior-card-title">ออกกำลังสม่ำเสมอ</div>
                                    <div class="behavior-card-desc">≥ 150 นาที/สัปดาห์</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-red">
                                <input type="radio" name="exercise_risk" value="red">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🛋️</span>
                                    <div class="behavior-card-title">ไม่ค่อยได้ออก</div>
                                    <div class="behavior-card-desc">นั่งนาน / เคลื่อนไหวน้อย</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 3. ระดับความเครียด (Stress - 3 Grid) -->
                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 14.5px; font-weight: 700; display: block; margin-bottom: 8px;">🧠 ระดับความเครียด</label>
                        <div class="behavior-grid-3">
                            <label class="behavior-card-item behavior-green">
                                <input type="radio" name="stress_risk" value="green" checked>
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">😊</span>
                                    <div class="behavior-card-title">น้อย/ไม่มี</div>
                                    <div class="behavior-card-desc">ผ่อนคลายดี</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-yellow">
                                <input type="radio" name="stress_risk" value="yellow">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">😐</span>
                                    <div class="behavior-card-title">ปานกลาง</div>
                                    <div class="behavior-card-desc">เครียดบางครั้ง</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-red">
                                <input type="radio" name="stress_risk" value="red">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">😫</span>
                                    <div class="behavior-card-title">เครียดสูง</div>
                                    <div class="behavior-card-desc">นอนไม่หลับ</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 4. การสูบบุหรี่ (Smoking - 3 Grid) -->
                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 14.5px; font-weight: 700; display: block; margin-bottom: 8px;">🚬 การสูบบุหรี่</label>
                        <div class="behavior-grid-3">
                            <label class="behavior-card-item behavior-green">
                                <input type="radio" name="smoking_risk" value="green" checked>
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🚭</span>
                                    <div class="behavior-card-title">ไม่สูบ</div>
                                    <div class="behavior-card-desc">ไม่เคยสูบ</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-yellow">
                                <input type="radio" name="smoking_risk" value="yellow">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🌿</span>
                                    <div class="behavior-card-title">เลิกแล้ว</div>
                                    <div class="behavior-card-desc">หยุดสูบแล้ว</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-red">
                                <input type="radio" name="smoking_risk" value="red">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🚬</span>
                                    <div class="behavior-card-title">ยังสูบอยู่</div>
                                    <div class="behavior-card-desc">สูบเป็นประจำ</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 5. การดื่มแอลกอฮอล์ (Alcohol - 3 Grid) -->
                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 14.5px; font-weight: 700; display: block; margin-bottom: 8px;">🍺 การดื่มแอลกอฮอล์</label>
                        <div class="behavior-grid-3">
                            <label class="behavior-card-item behavior-green">
                                <input type="radio" name="alcohol_risk" value="green" checked>
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🥛</span>
                                    <div class="behavior-card-title">ไม่ดื่ม</div>
                                    <div class="behavior-card-desc">งดของมึนเมา</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-yellow">
                                <input type="radio" name="alcohol_risk" value="yellow">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🥂</span>
                                    <div class="behavior-card-title">นานๆ ครั้ง</div>
                                    <div class="behavior-card-desc">เฉพาะงานเลี้ยง</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-red">
                                <input type="radio" name="alcohol_risk" value="red">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🍺</span>
                                    <div class="behavior-card-title">ดื่มประจำ</div>
                                    <div class="behavior-card-desc">ดื่มบ่อย/ติดสุรา</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 6. พฤติกรรมการนอนหลับ (Sleep Quality - 3 Grid) -->
                    <div style="margin-bottom: 20px;">
                        <label style="color: var(--text-secondary); font-size: 14.5px; font-weight: 700; display: block; margin-bottom: 8px;">😴 พฤติกรรมการนอนหลับ (1น.)</label>
                        <div class="behavior-grid-3">
                            <label class="behavior-card-item behavior-green">
                                <input type="radio" name="sleep_quality" value="good" checked>
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">😴</span>
                                    <div class="behavior-card-title">หลับสนิทดี</div>
                                    <div class="behavior-card-desc">พักผ่อนเพียงพอ</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-yellow">
                                <input type="radio" name="sleep_quality" value="restless">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">🥱</span>
                                    <div class="behavior-card-title">หลับๆ ตื่นๆ</div>
                                    <div class="behavior-card-desc">ตื่นบ่อย/ไม่สนิท</div>
                                </div>
                            </label>
                            <label class="behavior-card-item behavior-red">
                                <input type="radio" name="sleep_quality" value="poor">
                                <div class="behavior-card-box">
                                    <span class="behavior-card-icon">😫</span>
                                    <div class="behavior-card-title">นอนไม่ค่อยหลับ</div>
                                    <div class="behavior-card-desc">หลับยาก/นอนน้อย</div>
                                </div>
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
        const isSandboxMode = <?= (isSandboxMode($hoscode) || DemoDataProvider::isDemoMode()) ? 'true' : 'false' ?>;
        let selectedResident = null;
        let activeNumPad = null;
        let currentPickerInputId = null;
        let gpsLocation = { lat: 15.4300, lng: 104.9800 };
        let homeLat = 15.4300;
        let homeLng = 104.9800;

        function getCurrentLocation() {
            return new Promise((resolve) => {
                if (!navigator.geolocation) {
                    resolve({ lat: 15.4300, lng: 104.9800 });
                    return;
                }
                navigator.geolocation.getCurrentPosition(
                    p => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
                    err => resolve({ lat: 15.4300, lng: 104.9800 }),
                    { timeout: 3000, maximumAge: 15000, enableHighAccuracy: false }
                );
            });
        }

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
            const isDemo = <?= $isDemo ? 'true' : 'false' ?>;
            
            // Offline/Shell initialization (Skip completely in Demo mode)
            if (!isDemo && (isShell || !navigator.onLine)) {
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
                                <p style="color: var(--text-secondary); margin-bottom: 24px;">สมาชิกทั้งหมดในบ้านเลขที่นี้ได้รับการคัดกรองเสร็จสิ้นเรียบร้อยแล้วในรอบการคัดกรองนี้</p>
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
            let currentLat = homeLat;
            let currentLng = homeLng;
            if (!currentLat || !currentLng || currentLat === 0) {
                currentLat = 15.4300;
                currentLng = 104.9800;
            }
            
            if (mode === 'home') {
                gpsLocation.lat = currentLat;
                gpsLocation.lng = currentLng;
            } else {
                gpsLocation.lat = currentLat + 0.0011;
                gpsLocation.lng = currentLng + 0.0005;
            }
            
            const latEl = document.getElementById('screening_lat');
            const lngEl = document.getElementById('screening_lng');
            if (latEl) latEl.value = gpsLocation.lat;
            if (lngEl) lngEl.value = gpsLocation.lng;

            const btnHome = document.getElementById('btn-gps-home');
            const btnDrift = document.getElementById('btn-gps-drift');
            const infoDiv = document.getElementById('gps-status-info');
            
            if (btnHome && btnDrift && infoDiv) {
                if (mode === 'home') {
                    btnHome.classList.add('neumorph-inset');
                    btnHome.classList.remove('neumorph-flat');
                    btnDrift.classList.remove('neumorph-inset');
                    btnDrift.classList.add('neumorph-flat');
                    btnHome.style.background = 'var(--bg-darker)';
                    btnHome.style.color = 'var(--color-green)';
                    btnDrift.style.background = 'var(--bg-card)';
                    btnDrift.style.color = 'var(--text-primary)';
                    infoDiv.innerHTML = `📍 จำลองพิกัด: อยู่ที่บ้านเป้าหมาย (${currentLat.toFixed(6)}, ${currentLng.toFixed(6)})`;
                } else {
                    btnDrift.classList.add('neumorph-inset');
                    btnDrift.classList.remove('neumorph-flat');
                    btnHome.classList.remove('neumorph-inset');
                    btnHome.classList.add('neumorph-flat');
                    btnDrift.style.background = 'var(--bg-darker)';
                    btnDrift.style.color = 'var(--color-red)';
                    btnHome.style.background = 'var(--bg-card)';
                    btnHome.style.color = 'var(--text-primary)';
                    infoDiv.innerHTML = `🛰️ จำลองพิกัด: พิกัดคลาดเคลื่อนไป 130 เมตร (${gpsLocation.lat.toFixed(6)}, ${gpsLocation.lng.toFixed(6)})`;
                }
            }
        }

        function fillDemoVitals(preset) {
            if (preset === 'normal') {
                document.getElementById('weight').value = '60.0';
                document.getElementById('height').value = '165.0';
                document.getElementById('waist').value = '30.0';
                document.getElementById('sys_bp1').value = '118';
                document.getElementById('dia_bp1').value = '76';
                document.getElementById('sys_bp2').value = '116';
                document.getElementById('dia_bp2').value = '74';
                const dtx = document.getElementById('dtx_value');
                if (dtx) dtx.value = '95';
            } else if (preset === 'risk') {
                document.getElementById('weight').value = '68.0';
                document.getElementById('height').value = '162.0';
                document.getElementById('waist').value = '33.0';
                document.getElementById('sys_bp1').value = '136';
                document.getElementById('dia_bp1').value = '86';
                document.getElementById('sys_bp2').value = '132';
                document.getElementById('dia_bp2').value = '84';
                const dtx = document.getElementById('dtx_value');
                if (dtx) dtx.value = '115';
            } else {
                document.getElementById('weight').value = '75.0';
                document.getElementById('height').value = '160.0';
                document.getElementById('waist').value = '36.0';
                document.getElementById('sys_bp1').value = '158';
                document.getElementById('dia_bp1').value = '96';
                document.getElementById('sys_bp2').value = '154';
                document.getElementById('dia_bp2').value = '94';
                const dtx = document.getElementById('dtx_value');
                if (dtx) dtx.value = '165';
            }
            calculateBmi();
            calculateCvRisk();
        }

        function selectResident(assignId, name, sex, birth, needDm, needHt, origin, latVal, lngVal, lastSbp, lastDbp, lastDtx, lastDtxType, card) {
            // Deselect all
            document.querySelectorAll('.resident-card').forEach(c => {
                c.classList.remove('selected');
                const ind = c.querySelector('.select-indicator');
                if (ind) ind.innerText = '⚪';
            });

            // Select active
            if (card && card.classList) {
                card.classList.add('selected');
                const ind = card.querySelector ? card.querySelector('.select-indicator') : null;
                if (ind) ind.innerText = '🟡';
            }

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

        // Inline VhvNumPad Class to guarantee zero external dependency failure
        if (typeof VhvNumPad === 'undefined') {
            class VhvNumPad {
                constructor(inputId, padContainerId, displayBoxId = null) {
                    this.input = document.getElementById(inputId);
                    this.container = document.getElementById(padContainerId);
                    this.displayBox = displayBoxId ? document.getElementById(displayBoxId) : null;
                    this.currentValue = '';
                    if (this.input && this.container) {
                        this.init();
                    }
                }

                init() {
                    this.container.innerHTML = `
                        <div class="numpad-grid">
                            <button type="button" class="numpad-btn" data-val="1">1</button>
                            <button type="button" class="numpad-btn" data-val="2">2</button>
                            <button type="button" class="numpad-btn" data-val="3">3</button>
                            <button type="button" class="numpad-btn" data-val="4">4</button>
                            <button type="button" class="numpad-btn" data-val="5">5</button>
                            <button type="button" class="numpad-btn" data-val="6">6</button>
                            <button type="button" class="numpad-btn" data-val="7">7</button>
                            <button type="button" class="numpad-btn" data-val="8">8</button>
                            <button type="button" class="numpad-btn" data-val="9">9</button>
                            <button type="button" class="numpad-btn btn-action" data-val=".">.</button>
                            <button type="button" class="numpad-btn" data-val="0">0</button>
                            <button type="button" class="numpad-btn btn-action" data-val="del">⌫</button>
                        </div>
                    `;

                    this.container.querySelectorAll('.numpad-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            const val = btn.getAttribute('data-val');
                            this.handlePress(val);
                        });
                    });
                }

                handlePress(val) {
                    if (val === 'del') {
                        this.currentValue = this.currentValue.slice(0, -1);
                    } else if (val === '.') {
                        if (!this.currentValue.includes('.')) {
                            this.currentValue += '.';
                        }
                    } else {
                        if (this.currentValue.length < 6) {
                            this.currentValue += val;
                        }
                    }
                    this.updateDisplay();
                }

                setValue(val) {
                    this.currentValue = (val !== null && val !== undefined) ? val.toString() : '';
                    this.updateDisplay();
                }
                
                updateDisplay() {
                    if (this.input) {
                        this.input.value = this.currentValue;
                        this.input.dispatchEvent(new Event('input'));
                    }
                    if (this.displayBox) {
                        this.displayBox.innerText = this.currentValue || '0';
                    }
                    calculateBmi();
                    calculateCvRisk();
                }
            }
            window.VhvNumPad = VhvNumPad;
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
            const smokingVal = document.querySelector('input[name="smoking_risk"]:checked')?.value || 'green';
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
        function applyDemoPreset(type) {
            function setRadio(name, val) {
                const el = document.querySelector(`input[name="${name}"][value="${val}"]`);
                if (el) { el.checked = true; }
            }

            if (type === 'normal') {
                const w = document.getElementById('weight'); if (w) w.value = '58.0';
                const h = document.getElementById('height'); if (h) h.value = '165.0';
                const wst = document.getElementById('waist'); if (wst) wst.value = '28.0';
                const s1 = document.getElementById('sys_bp1'); if (s1) s1.value = '118';
                const d1 = document.getElementById('dia_bp1'); if (d1) d1.value = '76';
                const s2 = document.getElementById('sys_bp2'); if (s2) s2.value = '116';
                const d2 = document.getElementById('dia_bp2'); if (d2) d2.value = '74';
                const dtx = document.getElementById('dtx_value'); if (dtx) dtx.value = '92';
                setRadio('diet_risk', 'green');
                setRadio('exercise_risk', 'green');
                setRadio('stress_risk', 'green');
                setRadio('smoking_risk', 'green');
                setRadio('alcohol_risk', 'green');
            } else if (type === 'risk') {
                const w = document.getElementById('weight'); if (w) w.value = '72.0';
                const h = document.getElementById('height'); if (h) h.value = '160.0';
                const wst = document.getElementById('waist'); if (wst) wst.value = '34.0';
                const s1 = document.getElementById('sys_bp1'); if (s1) s1.value = '134';
                const d1 = document.getElementById('dia_bp1'); if (d1) d1.value = '86';
                const s2 = document.getElementById('sys_bp2'); if (s2) s2.value = '132';
                const d2 = document.getElementById('dia_bp2'); if (d2) d2.value = '84';
                const dtx = document.getElementById('dtx_value'); if (dtx) dtx.value = '115';
                setRadio('diet_risk', 'yellow');
                setRadio('exercise_risk', 'red');
                setRadio('stress_risk', 'yellow');
                setRadio('smoking_risk', 'green');
                setRadio('alcohol_risk', 'yellow');
            } else if (type === 'high_risk') {
                const w = document.getElementById('weight'); if (w) w.value = '80.0';
                const h = document.getElementById('height'); if (h) h.value = '158.0';
                const wst = document.getElementById('waist'); if (wst) wst.value = '37.0';
                const s1 = document.getElementById('sys_bp1'); if (s1) s1.value = '158';
                const d1 = document.getElementById('dia_bp1'); if (d1) d1.value = '96';
                const s2 = document.getElementById('sys_bp2'); if (s2) s2.value = '155';
                const d2 = document.getElementById('dia_bp2'); if (d2) d2.value = '94';
                const dtx = document.getElementById('dtx_value'); if (dtx) dtx.value = '175';
                setRadio('diet_risk', 'orange');
                setRadio('exercise_risk', 'red');
                setRadio('stress_risk', 'red');
                setRadio('smoking_risk', 'yellow');
                setRadio('alcohol_risk', 'yellow');
            } else if (type === 'critical') {
                const w = document.getElementById('weight'); if (w) w.value = '85.0';
                const h = document.getElementById('height'); if (h) h.value = '155.0';
                const wst = document.getElementById('waist'); if (wst) wst.value = '40.0';
                const s1 = document.getElementById('sys_bp1'); if (s1) s1.value = '185';
                const d1 = document.getElementById('dia_bp1'); if (d1) d1.value = '112';
                const s2 = document.getElementById('sys_bp2'); if (s2) s2.value = '182';
                const d2 = document.getElementById('dia_bp2'); if (d2) d2.value = '110';
                const dtx = document.getElementById('dtx_value'); if (dtx) dtx.value = '310';
                setRadio('diet_risk', 'red');
                setRadio('exercise_risk', 'red');
                setRadio('stress_risk', 'red');
                setRadio('smoking_risk', 'red');
                setRadio('alcohol_risk', 'red');
            } else if (type === 'round2_improved') {
                const w = document.getElementById('weight'); if (w) w.value = '65.0';
                const h = document.getElementById('height'); if (h) h.value = '165.0';
                const wst = document.getElementById('waist'); if (wst) wst.value = '31.0';
                const s1 = document.getElementById('sys_bp1'); if (s1) s1.value = '122';
                const d1 = document.getElementById('dia_bp1'); if (d1) d1.value = '78';
                const s2 = document.getElementById('sys_bp2'); if (s2) s2.value = '120';
                const d2 = document.getElementById('dia_bp2'); if (d2) d2.value = '76';
                const dtx = document.getElementById('dtx_value'); if (dtx) dtx.value = '102';
                setRadio('diet_risk', 'green');
                setRadio('exercise_risk', 'green');
                setRadio('stress_risk', 'green');
                setRadio('smoking_risk', 'green');
                setRadio('alcohol_risk', 'green');
            } else if (type === 'round2_worsened') {
                const w = document.getElementById('weight'); if (w) w.value = '82.0';
                const h = document.getElementById('height'); if (h) h.value = '165.0';
                const wst = document.getElementById('waist'); if (wst) wst.value = '38.0';
                const s1 = document.getElementById('sys_bp1'); if (s1) s1.value = '168';
                const d1 = document.getElementById('dia_bp1'); if (d1) d1.value = '102';
                const s2 = document.getElementById('sys_bp2'); if (s2) s2.value = '165';
                const d2 = document.getElementById('dia_bp2'); if (d2) d2.value = '100';
                const dtx = document.getElementById('dtx_value'); if (dtx) dtx.value = '215';
                setRadio('diet_risk', 'red');
                setRadio('exercise_risk', 'red');
                setRadio('stress_risk', 'red');
                setRadio('smoking_risk', 'red');
                setRadio('alcohol_risk', 'red');
            }
            if (typeof calculateBmi === 'function') calculateBmi();
            if (typeof calculateCvRisk === 'function') calculateCvRisk();
        }

        function submitScreening() {
            if (!selectedResident) {
                alert("⚠️ กรุณาเลือกบุคคลที่ต้องการคัดกรองก่อน");
                nextStep('step-resident');
                return;
            }

            // 1. ตรวจสอบข้อมูลร่างกาย: น้ำหนัก และ ส่วนสูง (บังคับห้ามเว้นว่าง)
            const w = parseFloat(document.getElementById('weight').value) || 0;
            const h = parseFloat(document.getElementById('height').value) || 0;
            if (w <= 0 || h <= 0) {
                alert("⚠️ กรุณากรอกข้อมูล 'น้ำหนัก (กก.)' และ 'ส่วนสูง (ซม.)' ให้ครบถ้วน");
                const targetInput = document.getElementById(w <= 0 ? 'weight' : 'height');
                if (targetInput) {
                    targetInput.focus();
                    targetInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            // 2. ตรวจสอบข้อมูลรอบเอว (บังคับห้ามเว้นว่าง)
            const waist = parseFloat(document.getElementById('waist').value) || 0;
            if (waist <= 0) {
                alert("⚠️ กรุณากรอกข้อมูล 'รอบเอว (นิ้ว)' ให้ครบถ้วน");
                const waistInput = document.getElementById('waist');
                if (waistInput) {
                    waistInput.focus();
                    waistInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            // 3. ตรวจสอบค่าความดันโลหิต (บังคับตัวบน SYS และตัวล่าง DIA ครั้งที่ 1)
            const sys1 = parseInt(document.getElementById('sys_bp1').value) || 0;
            const dia1 = parseInt(document.getElementById('dia_bp1').value) || 0;
            if (sys1 <= 0 || dia1 <= 0) {
                alert("⚠️ กรุณากรอก 'ค่าความดันโลหิต ครั้งที่ 1 (ตัวบน และ ตัวล่าง)' ให้ครบถ้วน");
                const bpInput = document.getElementById(sys1 <= 0 ? 'sys_bp1' : 'dia_bp1');
                if (bpInput) {
                    bpInput.focus();
                    bpInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            // 4. ตรวจสอบค่าน้ำตาลในเลือด DTX (ถ้ามีสิทธิ์คัดกรองเบาหวาน หรือเปิดตรวจ DTX)
            const dtxSection = document.getElementById('section-dtx');
            const isDtxVisible = dtxSection && dtxSection.style.display !== 'none';
            if (selectedResident.needDm || isDtxVisible) {
                const dtxVal = parseInt(document.getElementById('dtx_value').value) || 0;
                if (dtxVal <= 0) {
                    alert("⚠️ กรุณากรอก 'ระดับน้ำตาลในเลือด (DTX)' ของผู้รับการคัดกรอง");
                    const dtxInput = document.getElementById('dtx_value');
                    if (dtxInput) {
                        dtxInput.focus();
                        dtxInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }
            }

            // 5. ตรวจสอบคำแนะนำโดย อสม. (ต้องเลือกอย่างน้อย 1 รายการ)
            const adviceText = (document.getElementById('advice_given')?.value || '').trim();
            const selectedAdviceCards = document.querySelectorAll('.advice-image-card.selected');
            if (!adviceText && selectedAdviceCards.length === 0) {
                alert("⚠️ กรุณาคลิกเลือก 'คำแนะนำโดย อสม. (3อ. 2ส.)' จากการ์ดรูปภาพด้านล่างอย่างน้อย 1 รายการ ก่อนกดบันทึกส่งงานครับ");
                const advSec = document.getElementById('advice_given');
                if (advSec) {
                    advSec.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            // 6. ตรวจสอบพิกัด GPS (เฉพาะโหมดบันทึกจริง)
            if (!isSandboxMode) {
                const latVal = parseFloat(document.getElementById('screening_lat').value) || 0;
                const lngVal = parseFloat(document.getElementById('screening_lng').value) || 0;
                if (latVal === 0 || lngVal === 0) {
                    alert("⚠️ ไม่พบพิกัดตำแหน่งมือถือของท่าน\n\nกรุณาเปิดระบบ GPS ในโทรศัพท์มือถือ หรือกดปุ่ม 'อนุญาต' (Allow) สิทธิ์ระบุพิกัดที่มุมจอ จากนั้นรอสักครู่จนกว่าจะขึ้นพิกัดตัวเลขตรงแถบ 📍 ด้านบน แล้วกดบันทึกส่งงานใหม่อีกครั้งครับ");
                    return;
                }
            } else {
                if (!document.getElementById('screening_lat').value) {
                    document.getElementById('screening_lat').value = '15.430000';
                    document.getElementById('screening_lng').value = '104.980000';
                }
            }

            if (!isSandboxMode && !checkCriticalValues()) {
                return;
            }

            const form = document.getElementById('screening-form');
            const formData = new FormData(form);
            
            // Add custom action parameter
            formData.append('action', 'save_screening');
            formData.append('cv_risk_score', parseFloat(document.getElementById('cv-risk-display').innerText));

            // Run ClinicalGuidance analysis for 4 care levels, sleep quality, and health progress
            const sbp1 = parseInt(document.getElementById('sys_bp1').value) || 0;
            const dbp1 = parseInt(document.getElementById('dia_bp1').value) || 0;
            const dtx = parseInt(document.getElementById('dtx_value').value) || null;
            const wt = parseFloat(document.getElementById('weight').value) || null;
            const ht = parseFloat(document.getElementById('height').value) || null;
            const wst = parseFloat(document.getElementById('waist').value) || null;
            const sleepVal = document.querySelector('input[name="sleep_quality"]:checked')?.value || 'good';

            let prevData = null;
            if (typeof selectedResident !== 'undefined' && selectedResident && selectedResident.lastSbp) {
                prevData = {
                    bp_sys: selectedResident.lastSbp,
                    bp_dia: selectedResident.lastDbp,
                    fbs: selectedResident.lastDtx,
                    weight: null
                };
            }

            const guidanceResult = (typeof ClinicalGuidance !== 'undefined') ? ClinicalGuidance.analyze({
                bp_sys: sbp1,
                bp_dia: dbp1,
                fbs: dtx,
                weight: wt,
                height: ht,
                waist: wst,
                sleep_quality: sleepVal,
                previous_data: prevData
            }) : null;

            if (guidanceResult) {
                formData.append('care_level', guidanceResult.care_level);
                formData.append('next_visit_date', guidanceResult.next_visit_date);
                formData.append('health_progress', guidanceResult.health_progress);
                formData.append('guidance_summary', guidanceResult.what_to_say);
            }

            // Check if offline
            if (!navigator.onLine) {
                const serialized = {};
                formData.forEach((value, key) => {
                    serialized[key] = value;
                });
                serialized.cv_risk_score = parseFloat(document.getElementById('cv-risk-display').innerText);
                serialized._timestamp = Date.now();
                serialized._type = 'screening';
                serialized._residentName = selectedResident ? selectedResident.name : '';
                
                const queue = JSON.parse(localStorage.getItem('offline_submissions') || '[]');
                queue.push(serialized);
                localStorage.setItem('offline_submissions', JSON.stringify(queue));
                
                if (selectedResident && selectedResident.assignmentId) {
                    updateLocalTask(selectedResident.assignmentId, 'completed');
                }
                
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
                    if (guidanceResult) {
                        data.guidanceResult = guidanceResult;
                    }
                    showCounselingSummaryModal(data);
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

        const btnConfirmCrit = document.getElementById('btn-confirm-critical-save');
        if (btnConfirmCrit) {
            btnConfirmCrit.onclick = function() {
                isCriticalAcknowledged = true;
                closeCriticalModal();
                submitScreening();
            };
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

        // Full-Screen Counseling Summary Modal Implementation (Modern Health Certificate UI)
        function showCounselingSummaryModal(data) {
            const meta = data.summary_metadata || {};
            const resName = meta.resident_name || (selectedResident ? selectedResident.name : 'ผู้รับการคัดกรอง');
            
            document.getElementById('summary-resident-name').innerText = resName;
            
            // Hero Overall Health Card
            const heroCard = document.getElementById('summary-hero-card');
            const heroIcon = document.getElementById('summary-hero-icon');
            const heroTitle = document.getElementById('summary-hero-title');
            const heroDesc = document.getElementById('summary-hero-desc');

            const riskLevel = meta.risk_level || 'green';
            if (riskLevel === 'red' || riskLevel === 'critical') {
                // RED (แดง - วิกฤตด่วน)
                heroCard.style.background = 'linear-gradient(135deg, #DC2626 0%, #991B1B 100%)';
                heroCard.style.boxShadow = '0 10px 25px -5px rgba(220, 38, 38, 0.4)';
                heroIcon.innerText = '🚨';
                heroTitle.innerText = 'ระดับวิกฤต (ต้องพบแพทย์ทันที)';
                heroDesc.innerText = 'พบค่าสัญญาณชีพสูงวิกฤต เสี่ยงภาวะแทรกซ้อน แนะนำส่งต่อแพทย์ รพ.สต. หรือโทร 1669 ด่วน';
            } else if (riskLevel === 'orange' || riskLevel === 'high_risk' || riskLevel === 'suspect') {
                // ORANGE (ส้ม - เสี่ยงสูง สงสัยป่วย)
                heroCard.style.background = 'linear-gradient(135deg, #EA580C 0%, #C2410C 100%)';
                heroCard.style.boxShadow = '0 10px 25px -5px rgba(234, 88, 12, 0.4)';
                heroIcon.innerText = '🟠';
                heroTitle.innerText = 'กลุ่มเสี่ยงสูง (สงสัยป่วย - ควรพบแพทย์)';
                heroDesc.innerText = 'ความดันหรือน้ำตาลสูงเกินเกณฑ์มาตรฐาน ควรได้รับการตรวจยืนยันสภาวะโรคที่ รพ.สต.';
            } else if (riskLevel === 'yellow' || riskLevel === 'risk') {
                // YELLOW (เหลือง - กลุ่มเสี่ยง เริ่มสูง)
                heroCard.style.background = 'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)';
                heroCard.style.boxShadow = '0 10px 25px -5px rgba(245, 158, 11, 0.35)';
                heroIcon.innerText = '🟡';
                heroTitle.innerText = 'กลุ่มเสี่ยง (เริ่มสูง - ปรับพฤติกรรม)';
                heroDesc.innerText = 'ความดันหรือน้ำตาลเริ่มสูงกว่าเกณฑ์เล็กน้อย ควรปรับเปลี่ยนพฤติกรรมตามหลัก 3อ. 2ส.';
            } else {
                // GREEN (เขียว - สุขภาพปกติ)
                heroCard.style.background = 'linear-gradient(135deg, #10B981 0%, #059669 100%)';
                heroCard.style.boxShadow = '0 10px 25px -5px rgba(16, 185, 129, 0.35)';
                heroIcon.innerText = '🟢';
                heroTitle.innerText = 'สุขภาพปกติ (เกณฑ์ดีเยี่ยม)';
                heroDesc.innerText = 'ค่าความดันโลหิตและระดับน้ำตาลอยู่ในเกณฑ์มาตรฐาน รักษาสุขภาพแข็งแรงต่อเนื่อง';
            }

            // 4 Health Cards Grid
            const grid = document.getElementById('summary-results-grid');
            const sbpVal = meta.sbp || 0;
            const dbpVal = meta.dbp || 0;
            const dtxVal = meta.dtx || 0;
            const bmiVal = meta.bmi || 0;
            const waistVal = meta.waist || 0;

            // BP Status
            let bpBadge = 'ปกติ';
            let bpSub = 'อยู่ในเกณฑ์ดี';
            let bpColor = '#10B981';
            let bpBg = 'rgba(16, 185, 129, 0.08)';
            let bpBorder = 'rgba(16, 185, 129, 0.35)';
            if (sbpVal >= 160 || dbpVal >= 100) {
                bpBadge = 'สูงมาก';
                bpSub = 'ควรพบแพทย์ รพ.สต.';
                bpColor = '#DC2626';
                bpBg = 'rgba(220, 38, 38, 0.08)';
                bpBorder = 'rgba(220, 38, 38, 0.35)';
            } else if (sbpVal >= 140 || dbpVal >= 90) {
                bpBadge = 'เริ่มสูง';
                bpSub = 'กลุ่มเสี่ยงความดัน';
                bpColor = '#EA580C';
                bpBg = 'rgba(234, 88, 12, 0.08)';
                bpBorder = 'rgba(234, 88, 12, 0.35)';
            } else if (sbpVal >= 120 || dbpVal >= 80) {
                bpBadge = 'ค่อนข้างสูง';
                bpSub = 'ระวังอาหารเค็ม';
                bpColor = '#F59E0B';
                bpBg = 'rgba(245, 158, 11, 0.08)';
                bpBorder = 'rgba(245, 158, 11, 0.35)';
            }

            // DTX Status
            let dtxBadge = 'ปกติ';
            let dtxSub = 'ระดับน้ำตาลดีเยี่ยม';
            let dtxColor = '#10B981';
            let dtxBg = 'rgba(16, 185, 129, 0.08)';
            let dtxBorder = 'rgba(16, 185, 129, 0.35)';
            if (dtxVal >= 126) {
                dtxBadge = 'สงสัยเบาหวาน';
                dtxSub = 'ควรตรวจยืนยันที่ รพ.สต.';
                dtxColor = '#DC2626';
                dtxBg = 'rgba(220, 38, 38, 0.08)';
                dtxBorder = 'rgba(220, 38, 38, 0.35)';
            } else if (dtxVal >= 100) {
                dtxBadge = 'เริ่มสูง';
                dtxSub = 'กลุ่มเสี่ยงเบาหวาน';
                dtxColor = '#F59E0B';
                dtxBg = 'rgba(245, 158, 11, 0.08)';
                dtxBorder = 'rgba(245, 158, 11, 0.35)';
            } else if (dtxVal <= 0) {
                dtxBadge = 'ไม่ได้ตรวจ';
                dtxSub = 'ไม่มีข้อมูลค่าน้ำตาล';
                dtxColor = '#94A3B8';
                dtxBg = 'rgba(148, 163, 184, 0.06)';
                dtxBorder = 'rgba(148, 163, 184, 0.2)';
            }

            // BMI Status
            let bmiBadge = 'สมส่วน';
            let bmiSub = 'น้ำหนักมาตรฐาน';
            let bmiColor = '#10B981';
            let bmiBg = 'rgba(16, 185, 129, 0.08)';
            let bmiBorder = 'rgba(16, 185, 129, 0.35)';
            if (bmiVal >= 25) {
                bmiBadge = 'อ้วน';
                bmiSub = 'ควรควบคุมอาหาร';
                bmiColor = '#DC2626';
                bmiBg = 'rgba(220, 38, 38, 0.08)';
                bmiBorder = 'rgba(220, 38, 38, 0.35)';
            } else if (bmiVal >= 23) {
                bmiBadge = 'เริ่มท้วม';
                bmiSub = 'น้ำหนักเกินเกณฑ์';
                bmiColor = '#F59E0B';
                bmiBg = 'rgba(245, 158, 11, 0.08)';
                bmiBorder = 'rgba(245, 158, 11, 0.35)';
            } else if (bmiVal <= 0) {
                bmiBadge = 'ไม่มีข้อมูล';
                bmiSub = 'ไม่ได้ระบุน้ำหนัก';
                bmiColor = '#94A3B8';
                bmiBg = 'rgba(148, 163, 184, 0.06)';
                bmiBorder = 'rgba(148, 163, 184, 0.2)';
            }

            // Waist Status
            let waistBadge = 'ปกติ';
            let waistSub = 'รอบเอวมาตรฐาน';
            let waistColor = '#10B981';
            let waistBg = 'rgba(16, 185, 129, 0.08)';
            let waistBorder = 'rgba(16, 185, 129, 0.35)';
            if (waistVal >= 36) {
                waistBadge = 'เสี่ยงลงพุง';
                waistSub = 'รอบเอวเกินเกณฑ์';
                waistColor = '#F59E0B';
                waistBg = 'rgba(245, 158, 11, 0.08)';
                waistBorder = 'rgba(245, 158, 11, 0.35)';
            } else if (waistVal <= 0) {
                waistBadge = 'ไม่ได้วัด';
                waistSub = 'ไม่มีข้อมูลรอบเอว';
                waistColor = '#94A3B8';
                waistBg = 'rgba(148, 163, 184, 0.06)';
                waistBorder = 'rgba(148, 163, 184, 0.2)';
            }

            grid.innerHTML = `
                <div style="background: ${bpBg}; border: 1.5px solid ${bpBorder}; border-radius: 16px; padding: 12px 10px; text-align: center;">
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700; margin-bottom: 2px;">🩺 ความดันโลหิต</div>
                    <div style="font-size: 20px; font-weight: 900; color: ${bpColor}; line-height: 1.2; letter-spacing: -0.3px;">${bpBadge}</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px; font-weight: 500;">${bpSub}</div>
                </div>
                <div style="background: ${dtxBg}; border: 1.5px solid ${dtxBorder}; border-radius: 16px; padding: 12px 10px; text-align: center;">
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700; margin-bottom: 2px;">🩸 น้ำตาลในเลือด</div>
                    <div style="font-size: 20px; font-weight: 900; color: ${dtxColor}; line-height: 1.2; letter-spacing: -0.3px;">${dtxBadge}</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px; font-weight: 500;">${dtxSub}</div>
                </div>
                <div style="background: ${bmiBg}; border: 1.5px solid ${bmiBorder}; border-radius: 16px; padding: 12px 10px; text-align: center;">
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700; margin-bottom: 2px;">⚖️ รูปร่าง / BMI</div>
                    <div style="font-size: 20px; font-weight: 900; color: ${bmiColor}; line-height: 1.2; letter-spacing: -0.3px;">${bmiBadge}</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px; font-weight: 500;">${bmiSub}</div>
                </div>
                <div style="background: ${waistBg}; border: 1.5px solid ${waistBorder}; border-radius: 16px; padding: 12px 10px; text-align: center;">
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700; margin-bottom: 2px;">📏 สัดส่วนรอบเอว</div>
                    <div style="font-size: 20px; font-weight: 900; color: ${waistColor}; line-height: 1.2; letter-spacing: -0.3px;">${waistBadge}</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px; font-weight: 500;">${waistSub}</div>
                </div>
            `;

            // Trend & Comparison Section with Dynamic Large Background Arrow Watermark
            const trendBgIcon = document.getElementById('summary-trend-bg-icon');
            const trendBadge = document.getElementById('summary-trend-badge');
            const trendDetailsContainer = document.getElementById('summary-trend-details');
            
            let detailList = [];
            if (meta.trend_status === 'improved') {
                trendBadge.innerText = '📈 สุขภาพดีขึ้น (ค่าลดลง)';
                trendBadge.style.background = 'rgba(16, 185, 129, 0.14)';
                trendBadge.style.color = '#10B981';
                trendBadge.style.border = '1px solid rgba(16, 185, 129, 0.4)';
                if (trendBgIcon) {
                    trendBgIcon.innerHTML = '↘'; // แนวโน้มลดลง / สุขภาพดีขึ้น
                    trendBgIcon.style.color = '#10B981';
                    trendBgIcon.style.opacity = '0.18';
                }
                detailList = [
                    { icon: '✓', color: '#10B981', text: 'ความดันโลหิตและสุขภาพโดยรวมปรับตัวดีขึ้น' },
                    { icon: '✓', color: '#10B981', text: 'การดูแลสุขภาพตนเองได้ผลลัพธ์เป็นที่น่าพอใจ' }
                ];
            } else if (meta.trend_status === 'worsened') {
                trendBadge.innerText = '⚠️ เฝ้าระวัง (ค่าตรวจสูงขึ้น)';
                trendBadge.style.background = 'rgba(245, 158, 11, 0.16)';
                trendBadge.style.color = '#D97706';
                trendBadge.style.border = '1px solid rgba(245, 158, 11, 0.5)';
                if (trendBgIcon) {
                    trendBgIcon.innerHTML = '↗'; // แนวโน้มสูงขึ้น / เฝ้าระวัง
                    trendBgIcon.style.color = '#EA580C';
                    trendBgIcon.style.opacity = '0.20';
                }
                detailList = [
                    { icon: '⚠️', color: '#EA580C', text: 'ค่าตรวจมีแนวโน้มสูงขึ้นกว่าครั้งก่อน' },
                    { icon: '💡', color: '#3B82F6', text: 'แนะนำเพิ่มการปรับเปลี่ยนพฤติกรรม 3อ. 2ส. อย่างใกล้ชิด' }
                ];
            } else if (meta.trend_status === 'first_round') {
                trendBadge.innerText = '✨ บันทึกรอบแรก';
                trendBadge.style.background = 'rgba(59, 130, 246, 0.12)';
                trendBadge.style.color = '#2563EB';
                trendBadge.style.border = '1px solid rgba(59, 130, 246, 0.35)';
                if (trendBgIcon) {
                    trendBgIcon.innerHTML = '🎯';
                    trendBgIcon.style.color = '#3B82F6';
                    trendBgIcon.style.opacity = '0.12';
                }
                detailList = [
                    { icon: '✓', color: '#3B82F6', text: 'บันทึกเป็นฐานข้อมูลประเมินสุขภาพประจำปีเรียบร้อย' },
                    { icon: '✓', color: '#3B82F6', text: 'ใช้เปรียบเทียบผลกับครั้งถัดไป' }
                ];
            } else {
                trendBadge.innerText = '⚖️ สุขภาพทรงตัว';
                trendBadge.style.background = 'rgba(100, 116, 139, 0.12)';
                trendBadge.style.color = '#64748B';
                trendBadge.style.border = '1px solid rgba(100, 116, 139, 0.35)';
                if (trendBgIcon) {
                    trendBgIcon.innerHTML = '➔';
                    trendBgIcon.style.color = '#64748B';
                    trendBgIcon.style.opacity = '0.12';
                }
                detailList = [
                    { icon: '✓', color: '#10B981', text: 'ระดับความดันและค่าน้ำตาลทรงตัวใกล้เคียงเดิม' },
                    { icon: '✓', color: '#10B981', text: 'รักษาวินัยการดูแลสุขภาพอย่างสม่ำเสมอ' }
                ];
            }

            let detailHtml = '';
            detailList.forEach(d => {
                detailHtml += `
                    <div style="font-size: 12.5px; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; line-height: 1.4;">
                        <span style="color: ${d.color}; font-weight: 900; font-size: 13.5px;">${d.icon}</span> 
                        <span>${d.text}</span>
                    </div>
                `;
            });
            trendDetailsContainer.innerHTML = detailHtml;

            // Key Actions (3อ. 2ส.)
            const adviceContainer = document.getElementById('summary-advice-container');
            const adviceList = meta.advice_list || [];
            let adviceHtml = '';
            adviceList.forEach(item => {
                adviceHtml += `
                    <div style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 12px; background: var(--bg-darker); border-left: 3.5px solid var(--color-green, #10B981); margin-bottom: 5px; box-shadow: var(--neumorph-inset);">
                        <span style="font-size: 22px; line-height: 1; flex-shrink: 0;">${item.icon || '💡'}</span>
                        <div>
                            <div style="font-weight: 800; font-size: 13.5px; color: var(--text-primary);">${item.title}</div>
                            <div style="font-size: 11.5px; color: var(--text-secondary); line-height: 1.3;">${item.desc}</div>
                        </div>
                    </div>
                `;
            });
            adviceContainer.innerHTML = adviceHtml;

            document.getElementById('summary-next-date').innerText = meta.next_appointment || (data.guidanceResult ? data.guidanceResult.next_visit_thai : '-');

            // Render Clinical Guidance Card with Voice Coach Speaker
            const guidanceContainer = document.getElementById('summary-guidance-container');
            if (guidanceContainer && typeof ClinicalGuidance !== 'undefined') {
                let gRes = data.guidanceResult;
                if (!gRes) {
                    const sleepVal = document.querySelector('input[name="sleep_quality"]:checked')?.value || 'good';
                    gRes = ClinicalGuidance.analyze({
                        bp_sys: meta.sbp || parseInt(document.getElementById('sys_bp1')?.value) || 120,
                        bp_dia: meta.dbp || parseInt(document.getElementById('dia_bp1')?.value) || 80,
                        fbs: meta.dtx || parseInt(document.getElementById('dtx_value')?.value) || 100,
                        weight: meta.weight || parseFloat(document.getElementById('weight')?.value) || 60,
                        height: meta.height || parseFloat(document.getElementById('height')?.value) || 160,
                        waist: meta.waist || parseFloat(document.getElementById('waist')?.value) || 80,
                        sleep_quality: sleepVal
                    });
                }
                guidanceContainer.innerHTML = ClinicalGuidance.renderGuidanceCard(gRes);
                guidanceContainer.style.display = 'block';
            }

            document.getElementById('counseling-summary-modal').style.display = 'block';
            window.scrollTo(0,0);
        }

        function closeCounselingSummaryAndFinish() {
            document.getElementById('counseling-summary-modal').style.display = 'none';
            window.location.href = 'index.php';
        }

        document.addEventListener('DOMContentLoaded', () => {
            <?php if ($isDemo && !empty($residents)): ?>
            const r = <?= json_encode($residents[0], JSON_UNESCAPED_UNICODE) ?>;
            const birthDate = new Date(r.birth);
            const age = new Date().getFullYear() - birthDate.getFullYear();
            
            selectedResident = {
                assignmentId: r.assignment_id,
                name: `${r.first_name} ${r.last_name}`,
                sex: r.sex,
                age: age,
                needDm: true,
                needHt: true,
                origin: r.health_status_origin || 'BOTH',
                homeLat: parseFloat(r.latitude || 15.4300),
                homeLng: parseFloat(r.longitude || 104.9800),
                lastSbp: r.last_sbp ? parseInt(r.last_sbp) : 135,
                lastDbp: r.last_dbp ? parseInt(r.last_dbp) : 85,
                lastDtx: r.last_dtx ? parseInt(r.last_dtx) : 118,
                lastDtxType: r.last_dtx_type || 'fpg',
                roundNumber: parseInt(r.round_number || 1)
            };
            calculateBmi();
            calculateCvRisk();
            <?php else: ?>
            const cards = document.querySelectorAll('.resident-card');
            if (cards.length === 1) {
                cards[0].click();
            }
            <?php endif; ?>
        });
    </script>

    <!-- Full-Screen Counseling Summary Modal (Premium Health Certificate Design) -->
    <div id="counseling-summary-modal" style="
        position: fixed;
        inset: 0;
        z-index: 999999;
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        color: var(--text-primary, #0d2c54);
        overflow-y: auto;
        display: none;
        padding: 16px 12px 32px 12px;
        box-sizing: border-box;
        font-family: var(--font-base, sans-serif);
    ">
        <div style="
            max-width: 430px; 
            margin: 0 auto; 
            background: var(--bg-card); 
            border-radius: 22px; 
            box-shadow: 0 20px 45px -10px rgba(0,0,0,0.3), var(--neumorph-flat); 
            border: 1px solid var(--border-color, rgba(255,255,255,0.12)); 
            padding: 16px;
            box-sizing: border-box;
        ">
            <!-- Clean Top Bar Header -->
            <div style="
                display: flex; 
                align-items: center; 
                justify-content: space-between; 
                margin-bottom: 14px; 
                padding-bottom: 12px; 
                border-bottom: 1px solid var(--border-color, rgba(148, 163, 184, 0.2));
            ">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 12px; background: linear-gradient(135deg, var(--color-accent, #0d2c54), var(--color-primary, #3B82F6)); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; box-shadow: 0 4px 10px rgba(13, 44, 84, 0.15);">
                        🩺
                    </div>
                    <div>
                        <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); letter-spacing: 0.2px;">สรุปผลการคัดกรองสุขภาพ</div>
                        <div id="summary-resident-name" style="font-size: 15px; color: var(--color-accent, #0d2c54); font-weight: 800; line-height: 1.2;">คุณ...</div>
                    </div>
                </div>
                <span style="font-size: 11.5px; font-weight: 800; color: #10B981; background: rgba(16, 185, 129, 0.12); padding: 5px 10px; border-radius: 10px; border: 1px solid rgba(16, 185, 129, 0.3);">
                    บันทึกสำเร็จ ✅
                </span>
            </div>

            <!-- Hero Overall Health Status Banner (Risk-Themed Gradient) -->
            <div id="summary-hero-card" style="
                border-radius: 18px; 
                padding: 16px 18px; 
                margin-bottom: 16px; 
                color: #FFFFFF; 
                text-align: center;
                transition: all 0.3s ease;
                box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.35);
            ">
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 5px;">
                    <span id="summary-hero-icon" style="font-size: 24px;">🟢</span>
                    <span id="summary-hero-title" style="font-size: 19px; font-weight: 900; letter-spacing: -0.3px; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">สุขภาพปกติ (เกณฑ์ดีเยี่ยม)</span>
                </div>
                <div id="summary-hero-desc" style="font-size: 13px; opacity: 0.95; line-height: 1.5; font-weight: 500; text-wrap: balance; word-break: keep-all; text-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                    ค่าความดันและน้ำตาลอยู่ในเกณฑ์มาตรฐาน สุขภาพแข็งแรงดี
                </div>
            </div>

            <!-- Clinical Guidance & Decision Support Container -->
            <div id="summary-guidance-container" style="margin-bottom: 16px;"></div>

            <!-- 4 Health Cards Grid -->
            <div style="margin-bottom: 16px;">
                <div style="font-size: 12.5px; font-weight: 800; color: var(--text-secondary); display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                    <span>📊</span> <span>ผลตรวจสุขภาพ 4 ด้าน</span>
                </div>
                <div id="summary-results-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <!-- 4 Metric Cards -->
                </div>
            </div>

            <!-- Comparison with Previous Round (Dynamic Watermark Arrow) -->
            <div id="summary-trend-card" style="
                background: var(--bg-darker); 
                border-radius: 16px; 
                padding: 12px 14px; 
                margin-bottom: 16px; 
                box-shadow: var(--neumorph-inset); 
                border: 1px solid var(--border-color, transparent);
                position: relative;
                overflow: hidden;
            ">
                <!-- Large Watermark Arrow / Icon Background -->
                <div id="summary-trend-bg-icon" style="
                    position: absolute;
                    right: 8px;
                    bottom: -8px;
                    font-size: 78px;
                    font-weight: 900;
                    line-height: 1;
                    pointer-events: none;
                    user-select: none;
                    opacity: 0.15;
                    color: var(--color-green, #10B981);
                    transition: all 0.3s ease;
                    z-index: 0;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                ">
                    ↘
                </div>

                <div style="position: relative; z-index: 1;">
                    <div style="font-size: 12px; font-weight: 800; color: var(--text-secondary); display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="display: flex; align-items: center; gap: 5px;"><span>🔄</span> <span>ผลเปรียบเทียบจากรอบก่อน</span></span>
                        <span id="summary-trend-badge" style="font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 8px;">📈 ดีขึ้น</span>
                    </div>
                    <div id="summary-trend-details" style="display: flex; flex-direction: column; gap: 5px;">
                        <!-- Trend Comparison Details -->
                    </div>
                </div>
            </div>

            <!-- Key Lifestyle Actions (3อ. 2ส.) -->
            <div style="margin-bottom: 18px;">
                <div style="font-size: 12.5px; font-weight: 800; color: var(--color-green, #10B981); display: flex; align-items: center; gap: 5px; margin-bottom: 8px;">
                    <span>💡</span> <span>ข้อแนะนำการดูแลสุขภาพ (3อ. 2ส.)</span>
                </div>
                <div id="summary-advice-container" style="display: flex; flex-direction: column; gap: 6px;">
                    <!-- Advice Items -->
                </div>
                <div style="margin-top: 10px; font-size: 12px; color: var(--text-secondary); text-align: center; border-top: 1px dashed var(--border-color, rgba(148, 163, 184, 0.25)); padding-top: 8px; font-weight: 600;">
                    📅 นัดติดตามผลครั้งถัดไป: <strong id="summary-next-date" style="color: var(--color-accent, #0d2c54); font-weight: 800;">-</strong>
                </div>
            </div>

            <!-- CTA Finish Button -->
            <button type="button" onclick="closeCounselingSummaryAndFinish()" class="btn-giant btn-giant-primary" style="margin: 0; padding: 14px; font-size: 15.5px; border-radius: 16px; width: 100%; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35); font-weight: 800;">
                ✅ รับทราบผลตรวจ และเสร็จสิ้นงาน
            </button>
        </div>
    </div>
</body>
</html>
