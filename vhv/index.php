<?php
// vhv/index.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/demo_banner.php';

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$vhvId = $_SESSION['vhv_id'];
$vhvName = $_SESSION['vhv_name'];
$vhvMoo = $_SESSION['vhv_moo'];
$vhidCode = $_SESSION['vhid_code'];
$isLeader = $_SESSION['is_leader'];
$hoscode = $_SESSION['hoscode'];
$isHlCoach = $_SESSION['is_hl_coach'] ?? false;

// Fetch assigned tasks for budget year 2026
// Grouped by status
$pendingTasks = [];
$completedTasks = [];
$completedDpacTasks = [];
$dpacTasks = [];
$subVhvs = [];
require_once __DIR__ . '/../config/demo_data.php';

if (DemoDataProvider::isDemoMode()) {
    $demoTasks = DemoDataProvider::getDemoVhvTasks();
    $pendingTasks = $demoTasks['pending'];
    $dpacTasks = $demoTasks['dpac'];
    $completedTasks = $demoTasks['completed'];
    $skippedTasks = $demoTasks['skipped'] ?? [];
} else {
    try {
        $isSandboxVal = isSandboxMode($hoscode) ? 1 : 0;
        $currentBudgetYear = function_exists('get_current_budget_year') ? get_current_budget_year() : 2026;

    $pendingStmt = $pdo->prepare("
        SELECT a.assignment_id, a.assignment_status, a.round_number, p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo, p.sex, p.birth, p.need_screen_dm, p.need_screen_ht, p.health_status_origin,
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
               ) AS last_dtx_type,
               (SELECT sr.care_level FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1) AS last_care_level,
               (SELECT sr.next_visit_date FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1) AS last_next_visit_date,
               (SELECT sr.health_progress FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1) AS last_health_progress,
               (SELECT sr.sleep_quality FROM screening_results sr LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id WHERE (sr.target_cid = p.cid OR ta.target_cid = p.cid) ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1) AS last_sleep_quality
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.vhv_id = ? AND a.budget_year = ? AND a.assignment_status = 'pending' AND COALESCE(a.is_sandbox, 0) = ?
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
        ORDER BY LENGTH(p.house_no), p.house_no
    ");
    $pendingStmt->execute([$vhvId, $currentBudgetYear, $isSandboxVal]);
    $pendingTasks = $pendingStmt->fetchAll();

    $completedStmt = $pdo->prepare("
        SELECT a.assignment_id, a.assignment_status, a.round_number, p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo, p.sex, p.birth,
               sr.sys_bp1, sr.dia_bp1, sr.sys_bp2, sr.dia_bp2, sr.dtx_value, sr.dtx_type,
               sr.weight, sr.height, sr.waist, sr.bmi, sr.diet_risk, sr.exercise_risk,
               sr.stress_risk, sr.smoking_risk, sr.alcohol_risk, sr.cv_risk_score,
               sr.skipped_reason, sr.advice_given,
               sr.sleep_quality, sr.care_level, sr.next_visit_date, sr.guidance_summary, sr.health_progress,
               ht.sbp as base_sbp, ht.dbp as base_dbp, dm.bslevel as base_bslevel
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        LEFT JOIN screening_results sr ON a.assignment_id = sr.assignment_id
        LEFT JOIN staging_hdc_ht ht ON p.cid = ht.cid
        LEFT JOIN staging_hdc_dm dm ON p.cid = dm.cid
        WHERE a.vhv_id = ? AND a.budget_year = ? AND a.assignment_status IN ('completed', 'skipped') AND COALESCE(a.is_sandbox, 0) = ?
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
        ORDER BY a.assigned_at DESC
    ");
    $completedStmt->execute([$vhvId, $currentBudgetYear, $isSandboxVal]);
    $completedTasks = $completedStmt->fetchAll();

    // Fetch DPAC followups
    $dpacStmt = $pdo->prepare("
        SELECT f.followup_id, f.round_number, f.status, f.skip_count, f.sleep_quality, f.care_level, f.next_visit_date, f.health_progress, e.risk_type,
               p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        JOIN target_population p ON e.cid = p.cid
        WHERE f.vhv_id = ? AND f.status = 'pending' AND COALESCE(f.is_sandbox, 0) = ?
        ORDER BY f.round_number ASC, f.assigned_at ASC
    ");
    $dpacStmt->execute([$vhvId, $isSandboxVal]);
    $dpacTasks = $dpacStmt->fetchAll();

    // Fetch completed DPAC followups
    $completedDpacStmt = $pdo->prepare("
        SELECT f.followup_id, f.round_number, f.status, f.completed_at, f.weight, f.height, f.waist, f.bp_sys, f.bp_dia, f.fbs, f.health_risk_level, f.advice_given,
               f.sleep_quality, f.care_level, f.next_visit_date, f.guidance_summary, f.health_progress,
               e.risk_type, p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        JOIN target_population p ON e.cid = p.cid
        WHERE f.vhv_id = ? AND f.status = 'completed' AND COALESCE(f.is_sandbox, 0) = ?
        ORDER BY f.completed_at DESC
    ");
    $completedDpacStmt->execute([$vhvId, $isSandboxVal]);
    $completedDpacTasks = $completedDpacStmt->fetchAll();

    // Check if the current VHV has submitted the satisfaction survey
    $hasSubmittedSurvey = false;
    try {
        $surveyCheck = $pdo->prepare("SELECT COUNT(*) FROM vhv_survey_participants WHERE vhv_id = ? AND budget_year = ?");
        $surveyCheck->execute([$vhvId, $currentBudgetYear]);
        $hasSubmittedSurvey = ($surveyCheck->fetchColumn() > 0);
    } catch (\Throwable $e) {}

    // If leader, fetch other VHVs for password reset based on rank
    if ($isLeader) {
        $hc_names = get_health_units();
        if ($isLeader == 1) {
            // Village level: same village code (vhid_code)
            $subStmt = $pdo->prepare("SELECT vhv_id, vhv_name, vhv_moo FROM vhv_users WHERE vhid_code = ? AND vhv_id != ? ORDER BY vhv_name ASC");
            $subStmt->execute([$vhidCode, $vhvId]);
        } elseif ($isLeader == 2) {
            // Sub-district level: same tambon prefix (first 6 characters of vhid_code)
            $tambonPrefix = substr($vhidCode, 0, 6);
            $subStmt = $pdo->prepare("SELECT vhv_id, vhv_name, vhv_moo, hoscode FROM vhv_users WHERE vhid_code LIKE ? AND vhv_id != ? ORDER BY vhv_name ASC");
            $subStmt->execute([$tambonPrefix . '%', $vhvId]);
        } else {
            // District level: all other VHVs (covers all tambons)
            $subStmt = $pdo->prepare("SELECT vhv_id, vhv_name, vhv_moo, hoscode FROM vhv_users WHERE vhv_id != ? ORDER BY vhv_name ASC");
            $subStmt->execute([$vhvId]);
        }
        $subVhvs = $subStmt->fetchAll();
    }
} catch (\Throwable $e) {
    $db_error = $e->getMessage();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NCDs Portal">
    <meta name="application-name" content="NCDs Portal">
    <meta name="theme-color" content="#0d2c54">
    <title>NCDs by อสม.อำเภอ<?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="apple-touch-icon" href="../assets/icon.png">
    <link rel="manifest" href="manifest.json">
    <script src="../assets/js/app.js?v=<?= time() ?>"></script>
    <script src="../assets/js/clinical_guidance.js?v=<?= time() ?>"></script>
    <style>
        html, body {
            height: 100%;
            height: 100dvh;
            overflow: hidden;
        }

        body.vhv-accessibility {
            height: 100%;
            height: 100dvh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .mobile-wrapper {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 10px 14px 0 14px;
            display: flex;
            flex-direction: column;
            height: 100%;
            height: 100dvh;
            overflow: hidden;
            position: relative;
            box-sizing: border-box;
        }

        .vhv-top-section {
            flex-shrink: 0;
            z-index: 10;
        }

        .tabs {
            display: flex;
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 5px;
            margin-bottom: 10px;
            box-shadow: var(--neumorph-inset);

            /* Prevent accessibility text scaling from breaking main tab selectors */
            text-size-adjust: none;
            -webkit-text-size-adjust: none;
            -moz-text-size-adjust: none;
            -ms-text-size-adjust: none;
        }
        .tab-btn {
            flex: 1;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 14.5px;
            font-weight: 800;
            padding: 10px 4px;
            cursor: pointer;
            border-radius: calc(var(--border-radius) - 6px);
            transition: all var(--transition-speed);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
        }
        .tab-btn.active {
            background-color: var(--bg-main);
            color: var(--color-accent);
            box-shadow: var(--neumorph-flat);
        }
        .tab-content {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
            padding-bottom: 96px; /* Room for floating bottom nav */
            box-sizing: border-box;
        }
        .tab-content::-webkit-scrollbar,
        #messages-list-container::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }
        .task-card {
            background-color: var(--bg-card);
            border: none;
            border-radius: var(--border-radius);
            padding: 16px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--neumorph-flat);
            cursor: pointer;
            transition: all var(--transition-speed);
            position: relative;
            overflow: hidden;
        }
        .task-card:active {
            box-shadow: var(--neumorph-inset);
            transform: scale(0.98);
        }
        .task-card-watermark {
            position: absolute;
            right: 42px;
            bottom: -35px;
            font-size: 110px;
            font-weight: 900;
            color: rgba(185, 28, 28, 0.05);
            pointer-events: none;
            user-select: none;
            font-family: 'Outfit', sans-serif;
            z-index: 1;
            line-height: 1;
        }
        .task-info {
            position: relative;
            z-index: 2;
            min-width: 0;
            flex: 1;
        }
        .task-card > div:last-child {
            position: relative;
            z-index: 2;
            flex-shrink: 0;
        }
        .task-info h4 {
            margin: 0 0 6px 0;
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 800;
        }
        .task-info p {
            margin: 0;
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 600;
        }
        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 800;
            box-shadow: var(--neumorph-flat);
        }

        @keyframes float-bubble {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(8deg); }
        }
        @keyframes pulse-green-ring {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4), var(--neumorph-inset);
            }
            70% {
                box-shadow: 0 0 0 14px rgba(16, 185, 129, 0), var(--neumorph-inset);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0), var(--neumorph-inset);
            }
        }
        @keyframes pulse-yellow-ring {
            0% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5);
            }
            70% {
                box-shadow: 0 0 0 12px rgba(245, 158, 11, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
            }
        }
    </style>
</head>
<body class="vhv-accessibility">
    <div class="mobile-wrapper">
        <div class="vhv-top-section">
            <?php if (!empty($db_error)): ?>
                <div style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 12px; border-radius: var(--border-radius); margin-bottom: 10px; font-weight: bold; font-size: 14px; text-align: center;">
                    เกิดข้อผิดพลาดในการโหลดข้อมูล: <?= htmlspecialchars($db_error) ?>
                </div>
            <?php endif; ?>

            <!-- VHV Info Header (Compact Layout, Large Logo, Reduced Height) -->
            <div class="vhv-header" style="display: flex; align-items: center; gap: 14px; padding: 10px 14px; margin-bottom: 10px; border-radius: var(--border-radius); position: relative; background: var(--bg-card); box-shadow: var(--neumorph-flat);">
                <?php if (!$hasSubmittedSurvey): ?>
                    <button id="survey-banner" onclick="openSurveyModal()" style="position: absolute; top: 8px; right: 10px; background: none; border: none; cursor: pointer; z-index: 10; font-size: 24px; animation: float-bubble 2s ease-in-out infinite; padding: 0; outline: none; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="ทำแบบประเมินรับโบนัส 5 แต้ม! 🎁">
                        🎁
                    </button>
                <?php endif; ?>

                <!-- Large Prominent Logo with Install Badge -->
                <a href="javascript:void(0)" onclick="openAppInstallModal(event)" title="แตะเพื่อติดตั้งแอปพลิเคชันลงเครื่อง หรือดูข้อมูลระบบ" style="flex-shrink: 0; position: relative; display: inline-block; text-decoration: none;">
                    <img src="../assets/icon.png" alt="NCDs Portal Logo" style="width: 62px; height: 62px; border-radius: 16px; box-shadow: 0 6px 14px rgba(0,0,0,0.12); display: block; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform='scale(1)'">
                    <span style="position: absolute; bottom: -2px; right: -2px; background: #10b981; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 11px; border: 2px solid var(--bg-card); box-shadow: 0 2px 6px rgba(0,0,0,0.25);" title="ติดตั้งแอป">📲</span>
                </a>

                <!-- Compact VHV Info Column -->
                <div style="flex-grow: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 2px;">
                        <span style="color: var(--color-accent); font-size: 12px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            อสม. ประจำบ้าน<?= DISTRICT_NAME ?>
                        </span>
                        <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                            <button type="button" id="btn-notification-bell" onclick="openMessagesModal()" style="position: relative; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 50%; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; color: var(--color-primary); font-size: 14px; padding: 0;" title="การแจ้งเตือนและข่าวสาร">
                                🔔
                                <span id="unread-msg-badge" style="display:none; position:absolute; top:-4px; right:-4px; background:#EF4444; color:white; font-size:9px; font-weight:800; border-radius:50%; width:16px; height:16px; line-height:16px; text-align:center;">0</span>
                            </button>
                            <button type="button" id="btn-top-manual" onclick="showManualView()" style="color: var(--color-accent); font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 3px; background: rgba(30, 64, 175, 0.08); padding: 4px 10px; border-radius: 50px; white-space: nowrap; border: none; cursor: pointer; transition: all 0.2s;" title="เปิดคู่มือการใช้งาน">
                                📖 คู่มือ
                            </button>
                        </div>
                    </div>

                    <h2 style="color: var(--text-primary); margin: 0 0 2px 0; font-size: 18px; font-weight: 800; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($vhvName) ?></h2>

                    <p style="color: var(--text-secondary); margin: 0; font-size: 12px; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        หมู่ <?= $vhvMoo ?> • รพ.สต. [<?= htmlspecialchars($hoscode) ?>]
                        <?php if ($isLeader == 1): ?>
                            • <span style="color: var(--color-accent); font-weight: bold;">ประธานหมู่บ้าน</span>
                        <?php elseif ($isLeader == 2): ?>
                            • <span style="color: #a855f7; font-weight: bold; background: rgba(168,85,247,0.1); padding: 1px 4px; border-radius: 4px;">🏆 ประธานตำบล</span>
                        <?php elseif ($isLeader >= 3): ?>
                            • <span style="color: #ec4899; font-weight: bold; background: rgba(236,72,153,0.1); padding: 1px 4px; border-radius: 4px;">👑 ประธานอำเภอ</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Leader Password Reset Tool -->
            <?php if ($isLeader && !empty($subVhvs)): ?>
                <div class="card-dark" style="padding: 12px; margin-bottom: 10px;">
                    <h4 style="color: var(--color-accent); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; font-size: 15px; font-weight: 800;">
                        🔑 รีเซ็ตรหัสผ่าน อสม. <?php if ($isLeader == 1): ?>ในหมู่บ้าน<?php elseif ($isLeader == 2): ?>ในตำบล<?php else: ?>ในอำเภอ<?php endif; ?>
                    </h4>
                    <div style="display: flex; gap: 8px;">
                        <select id="reset_target_vhv" class="form-select" style="flex-grow: 1; height: 42px; font-size: 14px;">
                            <option value="">-- เลือก อสม. --</option>
                            <?php foreach ($subVhvs as $sv): ?>
                                <?php 
                                $suffix = '';
                                if ($isLeader == 1) {
                                    $suffix = ' (หมู่ ' . $sv['vhv_moo'] . ')';
                                } else {
                                    $hcName = $hc_names[$sv['hoscode']] ?? $sv['hoscode'];
                                    $suffix = ' (หมู่ ' . $sv['vhv_moo'] . ' - ' . $hcName . ')';
                                }
                                ?>
                                <option value="<?= $sv['vhv_id'] ?>"><?= htmlspecialchars($sv['vhv_name'] . $suffix) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="resetPassword()" class="numpad-btn btn-action" style="height: 42px; width: 110px; font-size: 13px; margin-top: 0; border-radius: var(--border-radius); font-weight: 800;">
                            รีเซ็ต "1234"
                        </button>
                    </div>
                    <div id="reset-result" style="margin-top: 6px; font-size: 13px; text-align: center; font-weight: bold;"></div>
                </div>
            <?php endif; ?>

            <?php if (DemoDataProvider::isDemoMode()): ?>
            <!-- Demo Sandbox Guide Card -->
            <div class="card-dark" style="margin-bottom: 10px; border: 1.5px dashed #3b82f6; background: rgba(59, 130, 246, 0.05); padding: 10px 12px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                    <span style="font-weight: 800; color: #3b82f6; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                        🧪 ตัวอย่างจำลองการทำงาน อสม.
                    </span>
                    <span style="font-size: 10px; background: #3b82f6; color: white; padding: 1px 6px; border-radius: 9999px; font-weight: bold;">Demo</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); gap: 8px;">
                    <div style="padding: 8px; border-radius: 8px; background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.3);">
                        <strong style="color: var(--color-green); font-size: 12px; display: block; margin-bottom: 2px;">แบบที่ 1: เข้าคัดกรองทันที</strong>
                        <p style="font-size: 11px; color: var(--text-muted); margin: 0 0 4px 0;">แตะที่การ์ดรายชื่อเป้าหมายด้านล่างเพื่อตรวจคัดกรอง</p>
                        <span style="font-size: 10.5px; color: var(--color-green); font-weight: bold;">👉 แตะรายการด้านล่าง</span>
                    </div>
                    <div style="padding: 8px; border-radius: 8px; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.3);">
                        <strong style="color: #3b82f6; font-size: 12px; display: block; margin-bottom: 2px;">แบบที่ 2: สแกน QR จำลอง</strong>
                        <p style="font-size: 11px; color: var(--text-muted); margin: 0 0 4px 0;">ทดสอบเคสสแกนผ่าน และเคสล็อค PDPA</p>
                        <a href="scan.php" style="font-size: 10.5px; color: #3b82f6; font-weight: bold; text-decoration: none;">📷 หน้าสแกน QR →</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Task Tabs (Original 3 Tabs) -->
            <div class="tabs">
                <button class="tab-btn active" id="tab-btn-pending" onclick="switchTab('pending-list', this)">
                    งานค้าง (<?= count($pendingTasks) ?>)
                </button>
                <button class="tab-btn" id="tab-btn-dpac" onclick="switchTab('dpac-list', this)" style="color: #b91c1c;">
                    DPAC (<?= count($dpacTasks) ?>)
                </button>
                <button class="tab-btn" id="tab-btn-completed" onclick="switchTab('completed-list', this)">
                    เสร็จสิ้น/ข้าม (<?= count($completedTasks) + count($completedDpacTasks) ?>)
                </button>
            </div>
        </div>

        <!-- Pending Tasks List -->
        <div id="pending-list" class="tab-content">
            <?php if (empty($pendingTasks)): ?>
                <div class="card-dark" style="text-align: center; padding: 24px 18px; box-shadow: var(--neumorph-flat); margin-top: 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; border-radius: var(--border-radius); overflow: hidden; position: relative;">
                    <!-- Floating celebratory background elements -->
                    <div style="position: absolute; top: 10px; left: 10%; font-size: 22px; opacity: 0.15; animation: float-bubble 4s ease-in-out infinite;">✨</div>
                    <div style="position: absolute; bottom: 15px; right: 8%; font-size: 26px; opacity: 0.15; animation: float-bubble 5s ease-in-out infinite 1s;">❤️</div>
                    <div style="position: absolute; top: 20%; right: 12%; font-size: 18px; opacity: 0.12; animation: float-bubble 6s ease-in-out infinite 0.5s;">🩺</div>
                    <div style="position: absolute; bottom: 30%; left: 15%; font-size: 20px; opacity: 0.12; animation: float-bubble 4.5s ease-in-out infinite 1.5s;">💪</div>
                    
                    <!-- Pulse badge -->
                    <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); border: 2px solid rgba(16, 185, 129, 0.25); display: flex; align-items: center; justify-content: center; box-shadow: var(--neumorph-inset); position: relative; animation: pulse-green-ring 2.5s infinite;">
                        <span style="font-size: 34px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">🏆</span>
                    </div>
                    
                    <div>
                        <h4 style="color: var(--color-green); font-size: 17px; font-weight: 800; margin: 0 0 4px 0; letter-spacing: 0.5px; text-size-adjust: none; -webkit-text-size-adjust: none;">ภารกิจคัดกรองสำเร็จครบถ้วน!</h4>
                        <p style="font-size: 13.5px; color: var(--text-primary); font-weight: bold; margin: 0 0 3px 0; line-height: 1.4; text-size-adjust: none; -webkit-text-size-adjust: none;">ไม่มีงานค้างในเขตรับผิดชอบของคุณ</p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 0; line-height: 1.3; text-size-adjust: none; -webkit-text-size-adjust: none;">ขอบคุณที่เป็นส่วนสำคัญในการร่วมดูแลสุขภาพชุมชน</p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 0; line-height: 1.3; text-size-adjust: none; -webkit-text-size-adjust: none;">ชาวอำเภอ<?= DISTRICT_NAME ?></p>
                    </div>

                    <!-- Shortcut Action: Self-Health Assessment for VHV -->
                    <a href="../self_screening.php" onclick="if(typeof showPageLoading==='function'){showPageLoading('ประเมินความเสี่ยงตนเอง', 'กำลังเตรียมแบบคัดกรองสุขภาพ อสม....', '🌱', '../self_screening.php'); return false;}" style="margin-top: 4px; display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(59, 130, 246, 0.12)); border: 1.5px solid rgba(16, 185, 129, 0.35); border-radius: 50px; text-decoration: none; color: var(--color-green, #10b981); font-weight: 800; font-size: 13.5px; box-shadow: var(--neumorph-flat); transition: all 0.3s ease; text-size-adjust: none; -webkit-text-size-adjust: none;" onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
                        🌱 ประเมินความเสี่ยงสุขภาพตนเอง
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($pendingTasks as $pt): ?>
                    <div class="task-card" data-assignment-id="<?= $pt['assignment_id'] ?>" data-hid="<?= htmlspecialchars($pt['hid'] ?? '') ?>" data-cid="<?= htmlspecialchars($pt['cid']) ?>" onclick="openTestModal('<?= htmlspecialchars($pt['house_no']) ?>', '<?= htmlspecialchars($pt['hid'] ?? '') ?>', '<?= htmlspecialchars($pt['cid']) ?>')">
                        <div class="task-info">
                            <h4>บ้านเลขที่ <?= htmlspecialchars($pt['house_no']) ?></h4>
                            <p>ผู้รับคัดกรอง: <?= htmlspecialchars($pt['first_name'] . ' ' . $pt['last_name']) ?></p>
                            
                            <!-- Badges: Care Level, Next Visit, Progress, Sleep -->
                            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px;">
                                <?php if (!empty($pt['last_care_level'])): ?>
                                    <?php 
                                        $cl = $pt['last_care_level'];
                                        $clBadge = 'background:rgba(16,185,129,0.15); color:#10B981; border:1px solid rgba(16,185,129,0.3);';
                                        $clText = '🟢 ดูแลปกติ';
                                        if ($cl === 'fair') { $clBadge = 'background:rgba(245,158,11,0.15); color:#D97706; border:1px solid rgba(245,158,11,0.3);'; $clText = '🟡 ดูแลพิเศษ'; }
                                        elseif ($cl === 'poor') { $clBadge = 'background:rgba(249,115,22,0.15); color:#EA580C; border:1px solid rgba(249,115,22,0.3);'; $clText = '🟠 ดูแลมากพิเศษ'; }
                                        elseif ($cl === 'critical') { $clBadge = 'background:rgba(239,68,68,0.15); color:#DC2626; border:1px solid rgba(239,68,68,0.3);'; $clText = '🔴 เร่งด่วน'; }
                                    ?>
                                    <span style="font-size: 10.5px; font-weight: 700; padding: 2px 6px; border-radius: 8px; <?= $clBadge ?>">
                                        <?= $clText ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($pt['last_health_progress'])): ?>
                                    <?php
                                        $hp = $pt['last_health_progress'];
                                        if ($hp === 'improved') echo '<span style="font-size: 10.5px; font-weight: 700; padding: 2px 6px; border-radius: 8px; background:rgba(16,185,129,0.15); color:#10B981; border:1px solid rgba(16,185,129,0.3);">🟢 ดีขึ้น</span>';
                                        elseif ($hp === 'worsened') echo '<span style="font-size: 10.5px; font-weight: 700; padding: 2px 6px; border-radius: 8px; background:rgba(239,68,68,0.15); color:#DC2626; border:1px solid rgba(239,68,68,0.3);">🔴 ต้องระวัง</span>';
                                        elseif ($hp === 'stable') echo '<span style="font-size: 10.5px; font-weight: 700; padding: 2px 6px; border-radius: 8px; background:rgba(245,158,11,0.15); color:#D97706; border:1px solid rgba(245,158,11,0.3);">🟡 ทรงตัว</span>';
                                    ?>
                                <?php endif; ?>

                                <?php if (!empty($pt['last_sleep_quality'])): ?>
                                    <?php 
                                        $sq = $pt['last_sleep_quality'];
                                        if ($sq === 'poor') echo '<span style="font-size: 10.5px; padding: 2px 5px; border-radius: 8px; background:rgba(239,68,68,0.1); color:#DC2626;" title="นอนไม่ค่อยหลับ">😫 หลับยาก</span>';
                                        elseif ($sq === 'restless') echo '<span style="font-size: 10.5px; padding: 2px 5px; border-radius: 8px; background:rgba(245,158,11,0.1); color:#D97706;" title="หลับๆ ตื่นๆ">🥱 หลับไม่สนิท</span>';
                                    ?>
                                <?php endif; ?>

                                <?php if (!empty($pt['last_next_visit_date'])): ?>
                                    <?php
                                        $nvd = strtotime($pt['last_next_visit_date']);
                                        $today = strtotime(date('Y-m-d'));
                                        $diffDays = round(($nvd - $today) / 86400);
                                        if ($diffDays < 0) {
                                            echo '<span style="font-size: 10.5px; font-weight: 800; padding: 2px 6px; border-radius: 8px; background:#FEE2E2; color:#DC2626; border:1px solid #FCA5A5;">⚠️ เกินนัด ' . abs($diffDays) . ' วัน</span>';
                                        } elseif ($diffDays == 0) {
                                            echo '<span style="font-size: 10.5px; font-weight: 800; padding: 2px 6px; border-radius: 8px; background:#FEF3C7; color:#D97706; border:1px solid #FCD34D;">📅 ครบกำหนดวันนี้</span>';
                                        } else {
                                            echo '<span style="font-size: 10.5px; font-weight: 600; padding: 2px 6px; border-radius: 8px; background:#F1F5F9; color:#64748B;">📅 อีก ' . $diffDays . ' วัน</span>';
                                        }
                                    ?>
                                <?php endif; ?>
                            </div>

                            <p style="font-size: 12px; margin-top: 4px; color: var(--text-muted);">
                                สิทธิ์การคัดกรอง: 
                                <?php if ($pt['need_screen_dm']): ?>
                                    <span style="color: var(--color-accent);">DM</span>
                                <?php endif; ?>
                                <?php if ($pt['need_screen_ht']): ?>
                                    <span style="color: var(--color-primary); margin-left: 5px;">HT</span>
                                <?php endif; ?>
                                <?php if (intval($pt['round_number'] ?? 1) > 1): ?>
                                    <span style="background-color: rgba(99, 102, 241, 0.15); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.3); padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: bold; margin-left: 6px;">
                                        🔄 คัดกรองซ้ำ ครั้งที่ <?= $pt['round_number'] ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div style="display: flex; align-items: center; gap: 4px;">
                            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: var(--color-green, #10B981); font-size: 12px; font-weight: 700; padding: 5px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 4px; box-shadow: none;">
                                📋 คัดกรอง ➔
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Completed Tasks List -->
        <div id="completed-list" class="tab-content" style="display: none;">
            <?php if (empty($completedTasks) && empty($completedDpacTasks)): ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                    ยังไม่มีประวัติการคัดกรองที่บันทึก
                </div>
            <?php else: ?>
                <?php foreach ($completedTasks as $ct): ?>
                    <?php if ($ct['assignment_status'] === 'completed'): ?>
                        <div class="task-card" data-assignment-id="<?= $ct['assignment_id'] ?>" onclick="showScreeningDetail(<?= htmlspecialchars(json_encode($ct, JSON_UNESCAPED_UNICODE)) ?>)" style="opacity: 0.9;">
                    <?php else: ?>
                        <div class="task-card" data-assignment-id="<?= $ct['assignment_id'] ?>" data-hid="<?= htmlspecialchars($ct['hid'] ?? '') ?>" data-cid="<?= htmlspecialchars($ct['cid']) ?>" onclick="openTestModal('<?= htmlspecialchars($ct['house_no']) ?>', '<?= htmlspecialchars($ct['hid'] ?? '') ?>', '<?= htmlspecialchars($ct['cid']) ?>')" style="opacity: 0.9;">
                    <?php endif; ?>
                        <div class="task-info">
                            <h4>บ้านเลขที่ <?= htmlspecialchars($ct['house_no']) ?></h4>
                            <p>ผู้รับคัดกรอง: <?= htmlspecialchars($ct['first_name'] . ' ' . $ct['last_name']) ?></p>
                            <?php if ($ct['assignment_status'] === 'completed'): ?>
                                <p style="color: var(--color-green); font-size: 13px; font-weight: bold;">
                                    ✅ คัดกรองสำเร็จเรียบร้อย (คลิกเพื่อดูรายละเอียด)
                                </p>
                            <?php else: ?>
                                <p style="color: var(--color-yellow); font-size: 13px; font-weight: bold;">
                                    ⚠️ ข้ามชั่วคราว: <?= htmlspecialchars($ct['skipped_reason'] ?: 'ไม่อยู่บ้าน') ?> (คลิกเพื่อแก้ไข/คัดกรองใหม่)
                                </p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($ct['assignment_status'] === 'completed'): ?>
                                <span class="badge" style="background-color: rgba(16,185,129,0.2); color: var(--color-green); box-shadow: none;">สำเร็จ</span>
                            <?php else: ?>
                                <span class="badge" style="background-color: rgba(245,158,11,0.2); color: var(--color-yellow); box-shadow: none;">ข้าม</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($completedDpacTasks as $cdt): ?>
                    <div class="task-card" onclick="showDpacDetail(<?= htmlspecialchars(json_encode($cdt, JSON_UNESCAPED_UNICODE)) ?>)" style="opacity: 0.9; border-left: 4px solid #b91c1c; cursor: pointer;">
                        <div class="task-card-watermark"><?= $cdt['round_number'] ?></div>
                        <div class="task-info">
                            <h4>บ้านเลขที่ <?= htmlspecialchars($cdt['house_no']) ?></h4>
                            <p>ผู้รับการติดตาม: <?= htmlspecialchars($cdt['first_name'] . ' ' . $cdt['last_name']) ?></p>
                            <p style="color: var(--color-green); font-size: 13px; font-weight: bold;">
                                ✅ ติดตาม DPAC รอบที่ <?= $cdt['round_number'] ?> สำเร็จเรียบร้อย (คลิกเพื่อดูรายละเอียด)
                            </p>
                            <p style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                วันที่ติดตาม: <?= htmlspecialchars($cdt['completed_at']) ?>
                            </p>
                        </div>
                        <div>
                            <span class="badge" style="background-color: rgba(16,185,129,0.2); color: var(--color-green); box-shadow: none;">สำเร็จ (DPAC)</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- DPAC Followup List -->
        <div id="dpac-list" class="tab-content" style="display: none;">
            <?php if (empty($dpacTasks)): ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                    ไม่มีงานติดตามปรับเปลี่ยนพฤติกรรม (DPAC) ในขณะนี้
                </div>
            <?php else: ?>
                <?php foreach ($dpacTasks as $dt): ?>
                    <div class="task-card" data-followup-id="<?= $dt['followup_id'] ?>" onclick="window.location.href='dpac_form.php?fid=<?= $dt['followup_id'] ?>'" style="border-left: 4px solid #b91c1c;">
                        <div class="task-card-watermark"><?= $dt['round_number'] ?></div>
                        <div class="task-info">
                            <h4>บ้านเลขที่ <?= htmlspecialchars($dt['house_no']) ?></h4>
                            <p><?= htmlspecialchars($dt['first_name'] . ' ' . $dt['last_name']) ?></p>
                            <p style="font-size: 13px; color: #b91c1c; font-weight: bold; margin-top: 4px; display: flex; align-items: center; flex-wrap: wrap; gap: 6px;">
                                <span>📌 รอบติดตามที่ <?= $dt['round_number'] ?> (เสี่ยง <?= $dt['risk_type'] ?>)</span>
                                <?php if (($dt['skip_count'] ?? 0) > 0): ?>
                                    <span style="display: inline-block; background-color: #eab308; color: #0f172a; font-size: 11px; padding: 1px 8px; border-radius: 50px; font-weight: 800; border: 1px solid rgba(234, 179, 8, 0.4);">
                                        ⚠️ ข้ามแล้ว <?= $dt['skip_count'] ?>/3 ครั้ง
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <svg width="24" height="24" fill="none" stroke="#b91c1c" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"></path></svg>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pure Manual Content Tab -->
        <div id="manual-list" class="tab-content" style="display: none;">
            <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">

                <!-- 1. การติดตั้งแอป -->
                <details style="background: var(--bg-card); border-radius: 16px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); overflow: hidden;">
                    <summary style="padding: 14px 16px; font-weight: 800; font-size: 14.5px; color: var(--text-primary); cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">📲</span>
                            <span>1. การติดตั้งแอป "NCDs Portal" ลงมือถือ</span>
                        </span>
                        <span style="font-size: 12px; color: var(--color-accent);">▼</span>
                    </summary>
                    <div style="padding: 0 16px 16px 16px; font-size: 13px; line-height: 1.6; color: var(--text-secondary); border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <p style="margin: 0 0 8px 0;">แอป NCDs Portal เป็นระบบ Progressive Web App (PWA) ติดตั้งได้ฟรี ทันที ไม่มีไฟล์หนัก:</p>
                        <div style="background: rgba(59, 130, 246, 0.08); border-radius: 12px; padding: 10px 12px; margin-bottom: 10px; border-left: 3px solid #3b82f6;">
                            <strong style="color: var(--text-primary); display: block; margin-bottom: 2px;">🤖 มือถือ Android (Google Chrome):</strong>
                            แตะที่รูปโลโก้หน้าหลัก หรือกดปุ่ม 3 จุดมุมขวาบนของ Chrome แล้วเลือก <strong>"ติดตั้งแอป" (Install App)</strong> หรือ "เพิ่มลงในหน้าจอหลัก"
                        </div>
                        <div style="background: rgba(16, 185, 129, 0.08); border-radius: 12px; padding: 10px 12px; border-left: 3px solid #10b981;">
                            <strong style="color: var(--text-primary); display: block; margin-bottom: 2px;">🍎 iPhone / iPad (Safari):</strong>
                            กดปุ่ม <strong>แชร์ (Share ⎋)</strong> ด้านล่างจอ แล้วเลือก <strong>"เพิ่มไปยังหน้าจอโฮม" (Add to Home Screen ⊞)</strong>
                        </div>
                    </div>
                </details>

                <!-- 2. การตรวจคัดกรองสุขภาพ (สำหรับ อสม.) -->
                <details style="background: var(--bg-card); border-radius: 16px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); overflow: hidden;">
                    <summary style="padding: 14px 16px; font-weight: 800; font-size: 14.5px; color: var(--text-primary); cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">🩺</span>
                            <span>2. การตรวจคัดกรองสุขภาพ (สำหรับ อสม.)</span>
                        </span>
                        <span style="font-size: 12px; color: var(--color-accent);">▼</span>
                    </summary>
                    <div style="padding: 0 16px 16px 16px; font-size: 13px; line-height: 1.6; color: var(--text-secondary); border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <ol style="margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 8px;">
                            <li><strong>สแกนบ้าน หรือเลือกงานค้าง:</strong> กดปุ่ม "สแกนบ้าน" เพื่อสแกน QR Code หน้าบ้าน หรือแตะรายชื่อในแท็บ "งานค้าง"</li>
                            <li><strong>ป้อนค่าวัดด้วยแป้น Numpad:</strong> กรอกน้ำหนัก ส่วนสูง รอบเอว ความดันโลหิต (SYS/DIA) และค่าน้ำตาลปลายนิ้ว (DTX) ได้สะดวกรวดเร็ว</li>
                            <li><strong>บันทึก & รับแต้ม:</strong> กดบันทึกเพื่อประเมินผลและรับแต้มสะสมทันที (ทั่วไป 10 แต้ม, เสี่ยงสูง 15 แต้ม)</li>
                        </ol>
                    </div>
                </details>

                <!-- 3. ระบบโค้ชเสียงพูดแนะนำสุขภาพ -->
                <details style="background: var(--bg-card); border-radius: 16px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); overflow: hidden;">
                    <summary style="padding: 14px 16px; font-weight: 800; font-size: 14.5px; color: var(--text-primary); cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">🎙️</span>
                            <span>3. โค้ชเสียงพูดแนะนำสุขภาพ (Voice Coach)</span>
                        </span>
                        <span style="font-size: 12px; color: var(--color-accent);">▼</span>
                    </summary>
                    <div style="padding: 0 16px 16px 16px; font-size: 13px; line-height: 1.6; color: var(--text-secondary); border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <p style="margin: 0 0 6px 0;">หลังบันทึกคัดกรองเสร็จ ให้แตะปุ่มสีเขียว <strong>"เปิดเสียงคุณหมอสรุปผล"</strong> เพื่อให้ระบบอ่านสรุปผลตรวจและคำแนะนำให้ชาวบ้านฟังทันที</p>
                        <p style="margin: 0; color: #d97706; font-weight: 700;">⚠️ ระบบจะเตือนอาหารโซเดียมสูงเฉพาะถิ่นตาลสุม เช่น น้ำปลาร้าต้มสุก, แจ่วบอง, แกงหน่อไม้, และแกงอ่อม</p>
                    </div>
                </details>

                <!-- 4. งานติดตามปรับเปลี่ยนพฤติกรรม DPAC (3 รอบ) -->
                <details style="background: var(--bg-card); border-radius: 16px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); overflow: hidden;">
                    <summary style="padding: 14px 16px; font-weight: 800; font-size: 14.5px; color: var(--text-primary); cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">❤️</span>
                            <span>4. งานติดตามปรับเปลี่ยนพฤติกรรม (DPAC 3 รอบ)</span>
                        </span>
                        <span style="font-size: 12px; color: var(--color-accent);">▼</span>
                    </summary>
                    <div style="padding: 0 16px 16px 16px; font-size: 13px; line-height: 1.6; color: var(--text-secondary); border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <p style="margin: 0 0 6px 0;">สำหรับกลุ่มเสี่ยงเบาหวาน/ความดัน ระบบจะสร้างงานติดตาม DPAC 3 ครั้ง (รอบ 1, 2, 3) ในแท็บ <strong>"DPAC"</strong></p>
                        <ul style="margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 6px;">
                            <li>ติดตามพฤติกรรม 3อ. 2ส. (อาหาร, ออกกำลังกาย, อารมณ์, บุหรี่, สุรา)</li>
                            <li>หากเป้าหมายไม่อยู่บ้าน สามารถกด <strong>"ข้ามชั่วคราว"</strong> ได้สูงสุด 3 ครั้ง เพื่อติดตามใหม่ภายหลัง</li>
                        </ul>
                    </div>
                </details>

                <!-- 5. การแจ้งเหตุวิกฤตด่วน Red Alert Fast-Track -->
                <details style="background: var(--bg-card); border-radius: 16px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); overflow: hidden;">
                    <summary style="padding: 14px 16px; font-weight: 800; font-size: 14.5px; color: var(--text-primary); cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">🚨</span>
                            <span>5. การแจ้งเหตุวิกฤตด่วน (Red Alert Fast-Track)</span>
                        </span>
                        <span style="font-size: 12px; color: var(--color-accent);">▼</span>
                    </summary>
                    <div style="padding: 0 16px 16px 16px; font-size: 13px; line-height: 1.6; color: var(--text-secondary); border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <div style="background: rgba(239, 68, 68, 0.08); border-radius: 12px; padding: 10px 12px; border-left: 3px solid #ef4444; margin-bottom: 8px;">
                            <strong style="color: #dc2626; display: block; margin-bottom: 2px;">เกณฑ์ค่าวัดวิกฤต (Crisis Threshold):</strong>
                            • ความดัน SYS ≥ 180 หรือ DIA ≥ 110 mmHg<br>
                            • น้ำตาล DTX / FBS ≥ 200 mg/dL
                        </div>
                        <p style="margin: 0;">ระบบจะส่งเสียงไซเรนฉุกเฉินไปยัง รพ.สต. และโรงพยาบาลตาลสุม พร้อมปุ่มโทรด่วน <strong>1669</strong> และเบอร์ รพ.สต. ทันที</p>
                    </div>
                </details>

                <!-- 6. สิทธิ์ประธาน อสม. & การรีเซ็ตรหัสผ่าน -->
                <details style="background: var(--bg-card); border-radius: 16px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); overflow: hidden;">
                    <summary style="padding: 14px 16px; font-weight: 800; font-size: 14.5px; color: var(--text-primary); cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">👑</span>
                            <span>6. สิทธิ์ประธาน อสม. & การรีเซ็ตรหัสผ่าน</span>
                        </span>
                        <span style="font-size: 12px; color: var(--color-accent);">▼</span>
                    </summary>
                    <div style="padding: 0 16px 16px 16px; font-size: 13px; line-height: 1.6; color: var(--text-secondary); border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <ul style="margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 6px;">
                            <li><strong>ประธานหมู่บ้าน:</strong> รีเซ็ตรหัสผ่านให้ อสม. ในหมู่บ้านเดียวกันเป็น <code>1234</code> ได้จากกล่องเครื่องมือหน้าแรก</li>
                            <li><strong>ประธานตำบล / ประธานอำเภอ:</strong> รีเซ็ตรหัสผ่านให้ อสม. ในเขตตำบลหรืออำเภอได้ครอบคลุม</li>
                        </ul>
                    </div>
                </details>

                <!-- 7. ผลตรวจสุขภาพ 4 ด้าน & ลายน้ำบอกแนวโน้ม -->
                <details style="background: var(--bg-card); border-radius: 16px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); overflow: hidden;">
                    <summary style="padding: 14px 16px; font-weight: 800; font-size: 14.5px; color: var(--text-primary); cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">📊</span>
                            <span>7. การอ่านผลตรวจ 4 ด้าน & แนวโน้มสุขภาพ</span>
                        </span>
                        <span style="font-size: 12px; color: var(--color-accent);">▼</span>
                    </summary>
                    <div style="padding: 0 16px 16px 16px; font-size: 13px; line-height: 1.6; color: var(--text-secondary); border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <p style="margin: 0 0 6px 0;">หน้าสรุปผลตรวจสุขภาพแสดงการ์ด 4 ด้าน: ความดันโลหิต, น้ำตาลในเลือด, รูปร่าง/BMI, และรอบเอว</p>
                        <p style="margin: 0;">
                            • <strong>ลูกศรสีแดงชี้ขึ้น (↗):</strong> ค่าวัดสูงขึ้นกว่ารอบก่อน (ต้องเฝ้าระวัง)<br>
                            • <strong>ลูกศรสีเขียวชี้ลง (↘):</strong> สุขภาพดีขึ้นหรือค่าวัดลดลงสู่เกณฑ์ปกติ
                        </p>
                    </div>
                </details>

                <!-- 8. คำถามที่พบบ่อย (FAQ) -->
                <details style="background: var(--bg-card); border-radius: 16px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); overflow: hidden;">
                    <summary style="padding: 14px 16px; font-weight: 800; font-size: 14.5px; color: var(--text-primary); cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">💡</span>
                            <span>8. คำถามที่พบบ่อย (FAQ)</span>
                        </span>
                        <span style="font-size: 12px; color: var(--color-accent);">▼</span>
                    </summary>
                    <div style="padding: 0 16px 16px 16px; font-size: 13px; line-height: 1.6; color: var(--text-secondary); border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <p style="margin: 0 0 6px 0;"><strong>Q: เปลี่ยนโหมดมืด/สว่างอย่างไร?</strong><br>แตะปุ่ม 🌙 / ☀️ มุมขวาบนของจอเพื่อเปลี่ยนธีม</p>
                        <p style="margin: 0 0 6px 0;"><strong>Q: ลืมรหัสผ่านทำอย่างไร?</strong><br>แจ้งประธาน อสม. ประจำหมู่บ้าน/ตำบล เพื่อรีเซ็ตรหัสผ่านเป็น <code>1234</code></p>
                        <p style="margin: 0;"><strong>Q: แต้มสะสมเอาไปทำอะไร?</strong><br>ใช้แข่งขันอันดับใน Leaderboard และแลกของรางวัลในเมนูคะแนน & รางวัล</p>
                    </div>
                </details>

            </div>
        </div>

        <!-- Bottom Navigation Bar -->
        <div class="bottom-nav">
            <a href="index.php" class="nav-link active">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                หน้าแรก
            </a>
            <a href="scan.php" class="nav-link nav-scan-fab fab-scan-pulse">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                <span>สแกนบ้าน</span>
            </a>
            <a href="leaderboard.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                คะแนน & รางวัล
            </a>
            <a href="profile.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                ข้อมูลส่วนตัว
            </a>
        </div>
    </div>

    <!-- Test Modal -->
    <div id="test-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="card-dark" style="width: 90%; max-width: 400px; padding: 24px;">
            <h3 style="color: var(--color-accent); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                โหมดทดสอบคัดกรอง
            </h3>
            <p style="color: var(--text-secondary); margin-bottom: 20px; font-size: 15px; line-height: 1.5;">
                คุณต้องการเข้าสู่หน้าคัดกรองของ บ้านเลขที่ <span id="test-house-no" style="color: white; font-weight: 800; font-size: 16px;"></span> โดยไม่ผ่านการสแกน QR Code ใช่หรือไม่?
            </p>
            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="closeTestModal()" class="btn-giant btn-giant-secondary" style="flex: 1; margin: 0; font-size: 16px;">ยกเลิก</button>
                <button type="button" id="btn-enter-test" class="btn-giant btn-giant-primary" style="flex: 1; margin: 0; font-size: 16px;">เข้าคัดกรอง</button>
            </div>
        </div>
    </div>

    <script>
        const isSandboxMode = <?= (isSandboxMode($hoscode) || DemoDataProvider::isDemoMode()) ? 'true' : 'false' ?>;
        let currentTestHid = '';
        let currentTestCid = '';
        function openTestModal(houseNo, hid, cid) {
            if (isSandboxMode) {
                // โหมดจำลอง / แซนด์บ็อกซ์: เข้าสู่หน้าตรวจคัดกรองได้ทันทีโดยตรง
                if (cid) {
                    window.location.href = 'screening_form.php?cid=' + encodeURIComponent(cid);
                } else if (hid) {
                    window.location.href = 'screening_form.php?hid=' + encodeURIComponent(hid);
                }
                return;
            }
            alert("⚠️ ระบบทำงานในโหมดใช้งานจริง: กรุณากดปุ่ม 'สแกนบ้าน' ด้านล่างเพื่อสแกน QR Code ประจำบ้านเป้าหมายและเริ่มทำการคัดกรอง");
        }
        document.getElementById('btn-enter-test').onclick = function() {
            if (currentTestHid) {
                window.location.href = 'screening_form.php?hid=' + currentTestHid;
            } else {
                window.location.href = 'screening_form.php?cid=' + currentTestCid;
            }
        };
        function switchTab(tabId, btn) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            // Remove active from task tab buttons
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
            });
            // Reset top manual button
            const topManualBtn = document.getElementById('btn-top-manual');
            if (topManualBtn) {
                topManualBtn.style.background = 'rgba(30, 64, 175, 0.08)';
                topManualBtn.style.boxShadow = 'none';
            }
            // Show selected tab & set button active
            const target = document.getElementById(tabId);
            if (target) {
                target.style.display = 'block';
                target.scrollTop = 0;
            }
            if (btn) btn.classList.add('active');
        }

        function showManualView() {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            // Remove active from task tab buttons
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
            });
            // Highlight top manual button
            const topManualBtn = document.getElementById('btn-top-manual');
            if (topManualBtn) {
                topManualBtn.style.background = 'var(--bg-main)';
                topManualBtn.style.boxShadow = 'var(--neumorph-flat)';
            }
            // Show manual list pane
            const mList = document.getElementById('manual-list');
            if (mList) {
                mList.style.display = 'block';
                mList.scrollTop = 0;
            }
        }

        // Auto-select tab if specified in URL query
        (function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam === 'manual' || tabParam === 'manual-list') {
                showManualView();
            } else if (tabParam === 'dpac' || tabParam === 'dpac-list') {
                const btn = document.getElementById('tab-btn-dpac');
                if (btn) switchTab('dpac-list', btn);
            } else if (tabParam === 'completed' || tabParam === 'completed-list') {
                const btn = document.getElementById('tab-btn-completed');
                if (btn) switchTab('completed-list', btn);
            }
        })();

        function resetPassword() {
            const vhvId = document.getElementById('reset_target_vhv').value;
            const resDiv = document.getElementById('reset-result');
            
            if (!vhvId) {
                resDiv.style.color = 'var(--color-red)';
                resDiv.innerText = 'กรุณาเลือก อสม. ที่ต้องการรีเซ็ต';
                return;
            }

            resDiv.style.color = 'var(--text-secondary)';
            resDiv.innerText = 'กำลังดำเนินการ...';

            fetch('../api/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'reset_password',
                    'target_vhv_id': vhvId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    resDiv.style.color = 'var(--color-green)';
                    resDiv.innerText = 'สำเร็จ! รหัสผ่านถูกรีเซ็ตเป็น "1234" แล้ว';
                } else {
                    resDiv.style.color = 'var(--color-red)';
                    resDiv.innerText = 'ล้มเหลว: ' + data.message;
                }
            })
            .catch(err => {
                resDiv.style.color = 'var(--color-red)';
                resDiv.innerText = 'เกิดข้อผิดพลาดทางเทคนิค';
            });
        }

        function openHistoryDetailModal() {
            document.getElementById('history-detail-modal').style.display = 'flex';
        }
        function closeHistoryDetailModal() {
            document.getElementById('history-detail-modal').style.display = 'none';
        }

        function showScreeningDetail(data) {
            document.getElementById('modal-type-title').innerText = '📊 รายละเอียดผลการคัดกรอง';
            
            let infoHtml = `
                <strong style="color: var(--text-primary); font-size: 16px;">${data.first_name} ${data.last_name}</strong>
                <p style="margin: 4px 0 0; font-size: 14px; color: var(--text-secondary);">บ้านเลขที่ ${data.house_no} หมู่ที่ ${data.moo}</p>
                <p style="margin: 4px 0 0; font-size: 12px; color: var(--text-muted);">วันที่คัดกรอง: ${data.completed_at || '-'}</p>
            `;
            document.getElementById('modal-resident-info').innerHTML = infoHtml;
            
            let bpText = `${data.sys_bp1}/${data.dia_bp1}`;
            if (data.sys_bp2) bpText += ` (ครั้งที่ 2: ${data.sys_bp2}/${data.dia_bp2})`;
            
            let dtxText = data.dtx_value ? `${data.dtx_value} mg/dL (${data.dtx_type === 'fpg' ? 'งดอาหาร (FPG)' : 'ไม่ได้งด (RPG)'})` : 'ไม่ได้ตรวจ';
            
            let measHtml = `
                <h4 style="margin: 0 0 8px 0; color: var(--color-accent); font-size: 15px;">📏 ผลการวัดร่างกาย</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 14px; color: var(--text-primary);">
                    <div>น้ำหนัก: <strong>${data.weight || '-'} กก.</strong></div>
                    <div>ส่วนสูง: <strong>${data.height || '-'} ซม.</strong></div>
                    <div>รอบเอว: <strong>${data.waist || '-'} นิ้ว</strong></div>
                    <div>BMI: <strong>${data.bmi || '-'}</strong></div>
                </div>
                <div style="margin-top: 10px; font-size: 14px; color: var(--text-primary);">
                    <div>ความดันโลหิต: <strong>${bpText} mmHg</strong></div>
                    <div>ระดับน้ำตาล (DTX): <strong>${dtxText}</strong></div>
                    <div style="margin-top: 4px;">Thai CV Risk: <strong style="color: var(--color-primary);">${data.cv_risk_score || '0'}%</strong></div>
                </div>
            `;
            document.getElementById('modal-measurements').innerHTML = measHtml;
            
            let compHtml = '<h4 style="margin: 12px 0 8px 0; color: var(--color-accent); font-size: 15px; border-top: 1px solid var(--border-color); padding-top: 12px;">🔄 เปรียบเทียบกับค่าตั้งต้น</h4>';
            let improvements = 0;
            let worsenings = 0;
            let comparedAny = false;
            
            if (data.sys_bp1 && data.base_sbp) {
                comparedAny = true;
                let diff = data.sys_bp1 - data.base_sbp;
                let diffText = diff > 0 ? `📈 เพิ่มขึ้น +${diff}` : (diff < 0 ? `📉 ลดลง ${diff}` : 'คงเดิม');
                let status = diff < 0 ? '🟢 ดีขึ้น' : (diff > 0 ? '🔴 แย่ลง' : '⚪ คงที่');
                if (diff < 0) improvements++;
                if (diff > 0) worsenings++;
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-primary);">ตัวบนความดัน (SYS): ${data.base_sbp} -> ${data.sys_bp1} mmHg (${diffText}) | <strong>${status}</strong></div>`;
            }
            
            if (data.dtx_value && data.base_bslevel) {
                comparedAny = true;
                let diff = data.dtx_value - data.base_bslevel;
                let diffText = diff > 0 ? `📈 เพิ่มขึ้น +${diff}` : (diff < 0 ? `📉 ลดลง ${diff}` : 'คงเดิม');
                let status = diff < 0 ? '🟢 ดีขึ้น' : (diff > 0 ? '🔴 แย่ลง' : '⚪ คงที่');
                if (diff < 0) improvements++;
                if (diff > 0) worsenings++;
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-primary);">ระดับน้ำตาล: ${data.base_bslevel} -> ${data.dtx_value} mg/dL (${diffText}) | <strong>${status}</strong></div>`;
            }
            
            if (!comparedAny) {
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-muted); font-style: italic;">ไม่มีข้อมูลค่าตั้งต้นสำหรับการเปรียบเทียบ</div>`;
            }
            
            let summaryText = '⚪ ทรงตัว (ไม่มีการเปลี่ยนแปลงมีนัยสำคัญ)';
            let summaryColor = 'var(--text-secondary)';
            if (improvements > worsenings) {
                summaryText = '🟢 ดีขึ้น (การควบคุมสุขภาพดีขึ้น)';
                summaryColor = 'var(--color-green)';
            } else if (worsenings > improvements) {
                summaryText = '🔴 แย่ลง (ควรระมัดระวังและปรับเปลี่ยนพฤติกรรม)';
                summaryColor = 'var(--color-red)';
            }
            
            compHtml += `
                <div style="margin-top: 12px; padding: 10px; background-color: var(--bg-darker); border-radius: 8px; text-align: center; font-weight: bold; color: ${summaryColor}; font-size: 15px; border: 1px solid var(--border-color);">
                    สรุปผลการประเมิน: ${summaryText}
                </div>
            `;
            document.getElementById('modal-comparison').innerHTML = compHtml;
            
            document.getElementById('modal-advice').innerHTML = `
                <strong style="color: var(--color-green); font-size: 14px; display: block; margin-bottom: 4px;">💡 คำแนะนำโดย อสม.:</strong>
                <p style="margin: 0; font-size: 14px; color: var(--text-primary); line-height: 1.5; font-weight: 700;">${data.advice_given || 'ไม่ระบุ/ไม่มีคำแนะนำเพิ่มเติม'}</p>
            `;
            
            openHistoryDetailModal();
        }

        function showDpacDetail(data) {
            document.getElementById('modal-type-title').innerText = `📈 ติดตาม DPAC (รอบที่ ${data.round_number})`;
            
            let infoHtml = `
                <strong style="color: var(--text-primary); font-size: 16px;">${data.first_name} ${data.last_name}</strong>
                <p style="margin: 4px 0 0; font-size: 14px; color: var(--text-secondary);">บ้านเลขที่ ${data.house_no} หมู่ที่ ${data.moo}</p>
                <p style="margin: 4px 0 0; font-size: 12px; color: var(--text-muted);">วันที่ติดตาม: ${data.completed_at || '-'}</p>
            `;
            document.getElementById('modal-resident-info').innerHTML = infoHtml;
            
            let bpText = data.bp_sys ? `${data.bp_sys}/${data.bp_dia}` : 'ไม่ได้วัด';
            let fbsText = data.fbs ? `${data.fbs} mg/dL` : 'ไม่ได้ตรวจ';
            
            let measHtml = `
                <h4 style="margin: 0 0 8px 0; color: var(--color-accent); font-size: 15px;">📏 ผลการติดตามร่างกาย</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 14px; color: var(--text-primary);">
                    <div>น้ำหนัก: <strong>${data.weight || '-'} กก.</strong></div>
                    <div>ส่วนสูง: <strong>${data.height || '-'} ซม.</strong></div>
                    <div>รอบเอว: <strong>${data.waist || '-'} นิ้ว</strong></div>
                </div>
                <div style="margin-top: 10px; font-size: 14px; color: var(--text-primary);">
                    <div>ความดันโลหิต: <strong>${bpText} mmHg</strong></div>
                    <div>ระดับน้ำตาล (FBS): <strong>${fbsText}</strong></div>
                    <div style="margin-top: 4px;">ผลการประเมิน: <strong style="color: var(--color-primary);">${data.health_risk_level || '-'}</strong></div>
                </div>
            `;
            document.getElementById('modal-measurements').innerHTML = measHtml;
            
            let compHtml = '<h4 style="margin: 12px 0 8px 0; color: var(--color-accent); font-size: 15px; border-top: 1px solid var(--border-color); padding-top: 12px;">🔄 เปรียบเทียบกับค่าตั้งต้น</h4>';
            let improvements = 0;
            let worsenings = 0;
            let comparedAny = false;
            
            if (data.bp_sys && data.base_sbp) {
                comparedAny = true;
                let diff = data.bp_sys - data.base_sbp;
                let diffText = diff > 0 ? `📈 เพิ่มขึ้น +${diff}` : (diff < 0 ? `📉 ลดลง ${diff}` : 'คงเดิม');
                let status = diff < 0 ? '🟢 ดีขึ้น' : (diff > 0 ? '🔴 แย่ลง' : '⚪ คงที่');
                if (diff < 0) improvements++;
                if (diff > 0) worsenings++;
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-primary);">ตัวบนความดัน (SYS): ${data.base_sbp} -> ${data.bp_sys} mmHg (${diffText}) | <strong>${status}</strong></div>`;
            }
            
            if (data.fbs && data.base_bslevel) {
                comparedAny = true;
                let diff = data.fbs - data.base_bslevel;
                let diffText = diff > 0 ? `📈 เพิ่มขึ้น +${diff}` : (diff < 0 ? `📉 ลดลง ${diff}` : 'คงเดิม');
                let status = diff < 0 ? '🟢 ดีขึ้น' : (diff > 0 ? '🔴 แย่ลง' : '⚪ คงที่');
                if (diff < 0) improvements++;
                if (diff > 0) worsenings++;
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-primary);">ระดับน้ำตาล: ${data.base_bslevel} -> ${data.fbs} mg/dL (${diffText}) | <strong>${status}</strong></div>`;
            }
            
            if (!comparedAny) {
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-muted); font-style: italic;">ไม่มีข้อมูลค่าตั้งต้นสำหรับการเปรียบเทียบ</div>`;
            }
            
            let summaryText = '⚪ ทรงตัว (ไม่มีการเปลี่ยนแปลงมีนัยสำคัญ)';
            let summaryColor = 'var(--text-secondary)';
            if (improvements > worsenings) {
                summaryText = '🟢 ดีขึ้น (การควบคุมสุขภาพดีขึ้น)';
                summaryColor = 'var(--color-green)';
            } else if (worsenings > improvements) {
                summaryText = '🔴 แย่ลง (ควรระมัดระวังและปรับเปลี่ยนพฤทีพรรม)';
                summaryColor = 'var(--color-red)';
            }
            
            compHtml += `
                <div style="margin-top: 12px; padding: 10px; background-color: var(--bg-darker); border-radius: 8px; text-align: center; font-weight: bold; color: ${summaryColor}; font-size: 15px; border: 1px solid var(--border-color);">
                    สรุปผลการประเมิน: ${summaryText}
                </div>
            `;
            document.getElementById('modal-comparison').innerHTML = compHtml;
            
            document.getElementById('modal-advice').innerHTML = `
                <strong style="color: var(--color-green); font-size: 14px; display: block; margin-bottom: 4px;">💡 คำแนะนำโดย อสม.:</strong>
                <p style="margin: 0; font-size: 14px; color: var(--text-primary); line-height: 1.5; font-weight: 700;">${data.advice_given || 'ไม่ระบุ/ไม่มีคำแนะนำเพิ่มเติม'}</p>
            `;
            
            openHistoryDetailModal();
        }
    </script>

    <!-- History Detail Modal Overlay -->
    <div id="history-detail-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(13, 44, 84, 0.4); backdrop-filter: blur(5px); z-index: 9999; align-items: center; justify-content: center;">
        <div class="card-dark" style="width: 90%; max-width: 460px; max-height: 90vh; overflow-y: auto; background: var(--bg-main); box-shadow: var(--neumorph-flat); border-radius: 28px; padding: 24px; color: var(--text-primary);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1.5px solid var(--border-color); padding-bottom: 12px;">
                <h3 id="modal-type-title" style="color: var(--color-accent); font-size: 20px; font-weight: 800; margin: 0;">รายละเอียด</h3>
                <button type="button" onclick="closeHistoryDetailModal()" style="background: none; border: none; color: var(--text-secondary); font-size: 24px; cursor: pointer; font-weight: bold; line-height: 1;">✕</button>
            </div>
            
            <div id="modal-resident-info" style="margin-bottom: 16px; background-color: var(--bg-darker); padding: 14px; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
            </div>

            <div id="modal-measurements" style="margin-bottom: 16px; background-color: var(--bg-card); padding: 14px; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
            </div>

            <div id="modal-comparison" style="margin-bottom: 16px; background-color: var(--bg-card); padding: 14px; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
            </div>

            <div id="modal-advice" style="margin-bottom: 24px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--color-green); padding: 14px; border-radius: var(--border-radius);">
            </div>

            <button type="button" onclick="closeHistoryDetailModal()" class="btn-giant btn-giant-primary" style="margin: 0; width: 100%; border-radius: var(--border-radius);">ปิดหน้าต่าง</button>
        </div>
    </div>

    <script>
        // Store dashboard data in localStorage for offline availability
        if (navigator.onLine) {
            localStorage.setItem('vhv_pending_tasks', JSON.stringify(<?= json_encode($pendingTasks, JSON_UNESCAPED_UNICODE) ?>));
            localStorage.setItem('vhv_completed_tasks', JSON.stringify(<?= json_encode($completedTasks, JSON_UNESCAPED_UNICODE) ?>));
            localStorage.setItem('vhv_dpac_tasks', JSON.stringify(<?= json_encode($dpacTasks, JSON_UNESCAPED_UNICODE) ?>));
            localStorage.setItem('vhv_completed_dpac_tasks', JSON.stringify(<?= json_encode($completedDpacTasks, JSON_UNESCAPED_UNICODE) ?>));
        }

        // Apply offline state modifications to UI dynamically
        document.addEventListener('DOMContentLoaded', () => {
            const queue = JSON.parse(localStorage.getItem('offline_submissions') || '[]');
            if (queue.length === 0) return;

            let pendingCountAdjust = 0;
            let dpacCountAdjust = 0;
            let completedCountAdjust = 0;

            queue.forEach(item => {
                if (item._type === 'screening' || item._type === 'skip_case') {
                    // Find the pending task card
                    const card = document.querySelector(`.task-card[data-assignment-id="${item.assignment_id}"]`);
                    if (card) {
                        pendingCountAdjust--;
                        completedCountAdjust++;
                        
                        // We modify the card UI
                        card.removeAttribute('onclick');
                        const infoDiv = card.querySelector('.task-info');
                        const badgeDiv = card.querySelector('div:last-child');
                        
                        if (item._type === 'screening') {
                            infoDiv.innerHTML = `
                                <h4>${infoDiv.querySelector('h4').innerHTML}</h4>
                                <p>${infoDiv.querySelector('p').innerHTML}</p>
                                <p style="color: var(--color-yellow); font-size: 13px; font-weight: bold;">
                                    ⏳ บันทึกแล้ว (รอส่งข้อมูลเข้าระบบ)
                                </p>
                            `;
                            badgeDiv.innerHTML = '<span class="badge" style="background-color: rgba(245,158,11,0.2); color: var(--color-yellow); box-shadow: none;">รอส่งข้อมูล</span>';
                        } else {
                            infoDiv.innerHTML = `
                                <h4>${infoDiv.querySelector('h4').innerHTML}</h4>
                                <p>${infoDiv.querySelector('p').innerHTML}</p>
                                <p style="color: var(--color-yellow); font-size: 13px; font-weight: bold;">
                                    ⏳ ข้ามเคสแล้ว (รอส่งข้อมูลเข้าระบบ)
                                </p>
                            `;
                            badgeDiv.innerHTML = '<span class="badge" style="background-color: rgba(245,158,11,0.2); color: var(--color-yellow); box-shadow: none;">รอส่งข้อมูล</span>';
                        }
                        
                        // Move card to Completed list
                        const completedList = document.getElementById('completed-list');
                        const emptyNotice = completedList.querySelector('div[style*="text-align: center"]');
                        if (emptyNotice) emptyNotice.remove();
                        completedList.appendChild(card);
                    }
                } else if (item._type === 'dpac') {
                    // Find the pending DPAC task card
                    const card = document.querySelector(`.task-card[data-followup-id="${item.followup_id}"]`);
                    if (card) {
                        dpacCountAdjust--;
                        completedCountAdjust++;
                        
                        card.removeAttribute('onclick');
                        const infoDiv = card.querySelector('.task-info');
                        const badgeDiv = card.querySelector('div:last-child');
                        
                        infoDiv.innerHTML = `
                            <h4>${infoDiv.querySelector('h4').innerHTML}</h4>
                            <p>${infoDiv.querySelector('p').innerHTML}</p>
                            <p style="color: var(--color-yellow); font-size: 13px; font-weight: bold;">
                                ⏳ ติดตาม DPAC แล้ว (รอส่งข้อมูลเข้าระบบ)
                            </p>
                        `;
                        badgeDiv.innerHTML = '<span class="badge" style="background-color: rgba(245,158,11,0.2); color: var(--color-yellow); box-shadow: none;">รอส่งข้อมูล</span>';
                        
                        // Move card to Completed list
                        const completedList = document.getElementById('completed-list');
                        const emptyNotice = completedList.querySelector('div[style*="text-align: center"]');
                        if (emptyNotice) emptyNotice.remove();
                        completedList.appendChild(card);
                    }
                } else if (item._type === 'skip_dpac_case') {
                    // Find the pending DPAC task card
                    const card = document.querySelector(`.task-card[data-followup-id="${item.followup_id}"]`);
                    if (card) {
                        const infoDiv = card.querySelector('.task-info');
                        if (infoDiv) {
                            const pTag = infoDiv.querySelector('p[style*="display: flex"]');
                            if (pTag) {
                                // Add or update warning badge in UI
                                let badge = pTag.querySelector('span[style*="background-color: #eab308"]');
                                if (badge) {
                                    badge.innerHTML = `⏳ ข้ามชั่วคราว (รอส่งข้อมูล)`;
                                } else {
                                    pTag.innerHTML += `
                                        <span style="display: inline-block; background-color: #eab308; color: #0f172a; font-size: 11px; padding: 1px 8px; border-radius: 50px; font-weight: 800; border: 1px solid rgba(234, 179, 8, 0.4);">
                                            ⏳ ข้ามชั่วคราว (รอส่งข้อมูล)
                                        </span>
                                    `;
                                }
                            }
                        }
                    }
                }
            });

            // Adjust tab counts dynamically
            const pendingTab = document.getElementById('tab-btn-pending');
            if (pendingTab) {
                const pendingMatch = pendingTab.textContent.match(/\((\d+)\)/);
                if (pendingMatch) {
                    const current = parseInt(pendingMatch[1]);
                    pendingTab.textContent = `งานค้าง (${current + pendingCountAdjust})`;
                }
            }
            
            const dpacTab = document.getElementById('tab-btn-dpac');
            if (dpacTab) {
                const dpacMatch = dpacTab.textContent.match(/\((\d+)\)/);
                if (dpacMatch) {
                    const current = parseInt(dpacMatch[1]);
                    dpacTab.textContent = `DPAC (${current + dpacCountAdjust})`;
                }
            }

            const completedTab = document.getElementById('tab-btn-completed');
            if (completedTab) {
                const completedMatch = completedTab.textContent.match(/\((\d+)\)/);
                if (completedMatch) {
                    const current = parseInt(completedMatch[1]);
                    completedTab.textContent = `เสร็จ/ข้าม (${current + completedCountAdjust})`;
                }
            }
        });
    </script>

    <?php if (!$hasSubmittedSurvey): ?>
    <!-- canvas-confetti library -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <!-- Satisfaction Survey Modal -->
    <div id="surveyModal" class="survey-modal">
        <div class="survey-modal-content">
            <div class="survey-header">
                <h3 style="margin: 0; color: var(--color-accent); font-weight: 800; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                    📝 ชวน อสม. ประเมินความพึงพอใจ
                </h3>
                <button onclick="closeSurveyModal()" style="background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer; padding: 4px;">&times;</button>
            </div>
            <div class="survey-body">
                <p style="margin: 0 0 20px 0; font-size: 13.5px; color: var(--text-secondary); line-height: 1.5;">
                    ช่วยเคาะคะแนนสั้น ๆ เพื่อปรับปรุงแอปเราให้ดียิ่งขึ้นครับ (ประเมินแล้วได้รับแต้มโบนัสสะสมพิเศษ <strong>+5 แต้ม</strong> ทันที! 🏆)
                </p>

                <!-- Q1 PEOU -->
                <div style="margin-bottom: 16px;">
                    <label class="survey-q-title">📱 1. หน้าจอสวยงาม ตัวหนังสือใหญ่ เมนูกดง่ายไม่สับสน</label>
                    <div class="survey-emoji-container" data-question="peou">
                        <button type="button" class="survey-emoji-btn btn-score-1" data-value="1" onclick="setRating('peou', 1)">😭</button>
                        <button type="button" class="survey-emoji-btn btn-score-2" data-value="2" onclick="setRating('peou', 2)">😞</button>
                        <button type="button" class="survey-emoji-btn btn-score-3" data-value="3" onclick="setRating('peou', 3)">😐</button>
                        <button type="button" class="survey-emoji-btn btn-score-4" data-value="4" onclick="setRating('peou', 4)">🙂</button>
                        <button type="button" class="survey-emoji-btn btn-score-5" data-value="5" onclick="setRating('peou', 5)">😍</button>
                        <span id="desc-peou" class="survey-emoji-desc"></span>
                    </div>
                </div>

                <!-- Q2 SQ -->
                <div style="margin-bottom: 16px;">
                    <label class="survey-q-title">⚡ 2. แอปทำงานไว โหลดหน้าฟอร์มและบันทึกเสร็จเร็ว ไม่ค้างบ่อย</label>
                    <div class="survey-emoji-container" data-question="sq">
                        <button type="button" class="survey-emoji-btn btn-score-1" data-value="1" onclick="setRating('sq', 1)">😭</button>
                        <button type="button" class="survey-emoji-btn btn-score-2" data-value="2" onclick="setRating('sq', 2)">😞</button>
                        <button type="button" class="survey-emoji-btn btn-score-3" data-value="3" onclick="setRating('sq', 3)">😐</button>
                        <button type="button" class="survey-emoji-btn btn-score-4" data-value="4" onclick="setRating('sq', 4)">🙂</button>
                        <button type="button" class="survey-emoji-btn btn-score-5" data-value="5" onclick="setRating('sq', 5)">😍</button>
                        <span id="desc-sq" class="survey-emoji-desc"></span>
                    </div>
                </div>

                <!-- Q3 IQ -->
                <div style="margin-bottom: 16px;">
                    <label class="survey-q-title">📍 3. รายชื่อชาวบ้านกลุ่มเสี่ยงและพิกัดบ้าน แสดงได้แม่นยำถูกต้อง</label>
                    <div class="survey-emoji-container" data-question="iq">
                        <button type="button" class="survey-emoji-btn btn-score-1" data-value="1" onclick="setRating('iq', 1)">😭</button>
                        <button type="button" class="survey-emoji-btn btn-score-2" data-value="2" onclick="setRating('iq', 2)">😞</button>
                        <button type="button" class="survey-emoji-btn btn-score-3" data-value="3" onclick="setRating('iq', 3)">😐</button>
                        <button type="button" class="survey-emoji-btn btn-score-4" data-value="4" onclick="setRating('iq', 4)">🙂</button>
                        <button type="button" class="survey-emoji-btn btn-score-5" data-value="5" onclick="setRating('iq', 5)">😍</button>
                        <span id="desc-iq" class="survey-emoji-desc"></span>
                    </div>
                </div>

                <!-- Q4 PU -->
                <div style="margin-bottom: 16px;">
                    <label class="survey-q-title">📝 4. ช่วยให้เดินคัดกรองสะดวก สบายกว่าการเขียนกระดาษแบบเดิม</label>
                    <div class="survey-emoji-container" data-question="pu">
                        <button type="button" class="survey-emoji-btn btn-score-1" data-value="1" onclick="setRating('pu', 1)">😭</button>
                        <button type="button" class="survey-emoji-btn btn-score-2" data-value="2" onclick="setRating('pu', 2)">😞</button>
                        <button type="button" class="survey-emoji-btn btn-score-3" data-value="3" onclick="setRating('pu', 3)">😐</button>
                        <button type="button" class="survey-emoji-btn btn-score-4" data-value="4" onclick="setRating('pu', 4)">🙂</button>
                        <button type="button" class="survey-emoji-btn btn-score-5" data-value="5" onclick="setRating('pu', 5)">😍</button>
                        <span id="desc-pu" class="survey-emoji-desc"></span>
                    </div>
                </div>

                <!-- Q5 BI -->
                <div style="margin-bottom: 24px;">
                    <label class="survey-q-title">🥰 5. อสม. พึงพอใจในภาพรวม และอยากใช้งานระบบนี้อีกในปีถัดไป</label>
                    <div class="survey-emoji-container" data-question="bi">
                        <button type="button" class="survey-emoji-btn btn-score-1" data-value="1" onclick="setRating('bi', 1)">😭</button>
                        <button type="button" class="survey-emoji-btn btn-score-2" data-value="2" onclick="setRating('bi', 2)">😞</button>
                        <button type="button" class="survey-emoji-btn btn-score-3" data-value="3" onclick="setRating('bi', 3)">😐</button>
                        <button type="button" class="survey-emoji-btn btn-score-4" data-value="4" onclick="setRating('bi', 4)">🙂</button>
                        <button type="button" class="survey-emoji-btn btn-score-5" data-value="5" onclick="setRating('bi', 5)">😍</button>
                        <span id="desc-bi" class="survey-emoji-desc"></span>
                    </div>
                </div>

                <!-- Quick Tags (Multi-select) -->
                <div>
                    <label class="survey-q-title">ข้อความเสนอแนะเพิ่มเติมที่ตรงใจ (กดเลือกได้มากกว่า 1 ข้อ)</label>
                    <div class="survey-tag-grid" id="survey-tags">
                        <button type="button" class="survey-tag-btn tag-positive" data-tag="ใช้งานง่ายมาก" onclick="toggleSurveyTag(this)">💚 ใช้งานง่ายมาก</button>
                        <button type="button" class="survey-tag-btn tag-positive" data-tag="โหลดข้อมูลรวดเร็ว" onclick="toggleSurveyTag(this)">🚀 โหลดข้อมูลรวดเร็ว</button>
                        <button type="button" class="survey-tag-btn tag-positive" data-tag="สะสมแต้มสนุกเร้าใจ" onclick="toggleSurveyTag(this)">🏆 สะสมแต้มสนุกเร้าใจ</button>
                        <button type="button" class="survey-tag-btn tag-positive" data-tag="แผนที่แม่นยำมาก" onclick="toggleSurveyTag(this)">📍 แผนที่แม่นยำมาก</button>
                        <button type="button" class="survey-tag-btn tag-negative" data-tag="ตัวหนังสือเล็กเกินไป" onclick="toggleSurveyTag(this)">🔎 ตัวหนังสือเล็กเกินไป</button>
                        <button type="button" class="survey-tag-btn tag-negative" data-tag="แอปพลิเคชันค้างบ่อย" onclick="toggleSurveyTag(this)">⚠️ แอปพลิเคชันค้างบ่อย</button>
                        <button type="button" class="survey-tag-btn tag-negative" data-tag="ไม่มีเน็ตแล้วส่งงานยาก" onclick="toggleSurveyTag(this)">📶 ไม่มีเน็ตแล้วส่งงานยาก</button>
                        <button type="button" class="survey-tag-btn tag-negative" data-tag="ปุ่มกดยากเล็กน้อย" onclick="toggleSurveyTag(this)">🖐️ ปุ่มกดยากเล็กน้อย</button>
                    </div>
                </div>
            </div>
            <div class="survey-footer">
                <button type="button" onclick="closeSurveyModal()" class="btn-cancel" style="padding: 10px 20px; font-size: 15px; font-weight: 800; border-radius: var(--border-radius); border: 1px solid var(--border-color); background: none; color: var(--text-secondary); cursor: pointer;">ยกเลิก</button>
                <button type="button" onclick="submitSurvey()" class="btn-primary" style="padding: 10px 24px; font-size: 15px; font-weight: 800; border-radius: var(--border-radius); border: none; background-color: var(--color-accent); color: white; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                    ส่งแบบประเมิน 🚀
                </button>
            </div>
        </div>
    </div>

    <!-- Styles for survey modal -->
    <style>
        .survey-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 16px;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .survey-modal-content {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid var(--border-color);
        }
        @keyframes modalFadeIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .survey-header {
            padding: 20px 20px 10px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .survey-body {
            padding: 20px;
        }
        .survey-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .survey-q-title {
            display: block;
            font-size: 14.5px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 6px 0;
        }
        .survey-emoji-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 6px;
            margin-bottom: 14px;
        }
        .survey-emoji-btn {
            font-size: 26px;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            filter: grayscale(100%);
            opacity: 0.5;
            padding: 0;
            box-shadow: var(--neumorph-inset);
        }
        .survey-emoji-btn:hover {
            opacity: 0.8;
            filter: grayscale(50%);
            transform: scale(1.1);
        }
        .survey-emoji-btn.active {
            filter: grayscale(0%);
            opacity: 1;
            transform: scale(1.2);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }
        .survey-emoji-btn.active.btn-score-1 {
            background-color: rgba(239, 68, 68, 0.2);
            border-color: #ef4444;
            box-shadow: 0 0 12px rgba(239, 68, 68, 0.4);
        }
        .survey-emoji-btn.active.btn-score-2 {
            background-color: rgba(249, 115, 22, 0.2);
            border-color: #f97316;
            box-shadow: 0 0 12px rgba(249, 115, 22, 0.4);
        }
        .survey-emoji-btn.active.btn-score-3 {
            background-color: rgba(234, 179, 8, 0.2);
            border-color: #eab308;
            box-shadow: 0 0 12px rgba(234, 179, 8, 0.4);
        }
        .survey-emoji-btn.active.btn-score-4 {
            background-color: rgba(132, 204, 22, 0.2);
            border-color: #84cc16;
            box-shadow: 0 0 12px rgba(132, 204, 22, 0.4);
        }
        .survey-emoji-btn.active.btn-score-5 {
            background-color: rgba(16, 185, 129, 0.2);
            border-color: #10b981;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.5);
            animation: bounce-emoji 0.5s ease;
        }
        @keyframes bounce-emoji {
            0%, 100% { transform: scale(1.2); }
            50% { transform: scale(1.4); }
        }
        .survey-emoji-desc {
            margin-left: 12px;
            font-weight: 800;
            font-size: 13.5px;
            transition: all 0.2s;
        }
        .survey-tag-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        .survey-tag-btn {
            background-color: var(--bg-main);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 12px 8px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: var(--neumorph-inset);
        }
        .survey-tag-btn:active {
            transform: scale(0.96);
        }
        .survey-tag-btn.tag-positive.active {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: white !important;
            border-color: #059669 !important;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3) !important;
        }
        .survey-tag-btn.tag-negative.active {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: white !important;
            border-color: #d97706 !important;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3) !important;
        }
    </style>

    <!-- Javascript logic for survey modal -->
    <script>
        const surveyRatings = {
            peou: 0,
            sq: 0,
            iq: 0,
            pu: 0,
            bi: 0
        };
        const selectedSurveyTags = [];

        function openSurveyModal() {
            document.getElementById('surveyModal').style.display = 'flex';
        }

        function closeSurveyModal() {
            document.getElementById('surveyModal').style.display = 'none';
        }

        function setRating(question, value) {
            surveyRatings[question] = value;
            const container = document.querySelector(`.survey-emoji-container[data-question="${question}"]`);
            const stars = container.querySelectorAll('.survey-emoji-btn');
            stars.forEach(star => {
                const starVal = parseInt(star.getAttribute('data-value'));
                if (starVal === value) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });

            // Dynamic friendly description
            const descText = {
                1: '😭 แย่มาก',
                2: '😞 ปรับปรุง',
                3: '😐 ปานกลาง',
                4: '🙂 ดี',
                5: '😍 ดีมากสุดใจ!'
            };
            const descColors = {
                1: '#ef4444',
                2: '#f97316',
                3: '#eab308',
                4: '#84cc16',
                5: '#10b981'
            };
            const descEl = document.getElementById('desc-' + question);
            if (descEl) {
                descEl.innerText = descText[value] || '';
                descEl.style.color = descColors[value] || 'var(--text-secondary)';
            }
        }

        function toggleSurveyTag(btn) {
            const tag = btn.getAttribute('data-tag');
            btn.classList.toggle('active');
            if (btn.classList.contains('active')) {
                if (!selectedSurveyTags.includes(tag)) {
                    selectedSurveyTags.push(tag);
                }
            } else {
                const index = selectedSurveyTags.indexOf(tag);
                if (index > -1) {
                    selectedSurveyTags.splice(index, 1);
                }
            }
        }

        function submitSurvey() {
            // Validation
            for (const q in surveyRatings) {
                if (surveyRatings[q] === 0) {
                    alert('กรุณาประเมินให้ครบถ้วนทั้ง 5 หัวข้อคำถาม');
                    return;
                }
            }

            const btnPrimary = document.querySelector('#surveyModal .btn-primary');
            const originalText = btnPrimary.innerHTML;
            btnPrimary.disabled = true;
            btnPrimary.innerHTML = 'กำลังบันทึก... ⌛';

            fetch('../api/submit_survey.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=utf-8'
                },
                body: JSON.stringify({
                    peou: surveyRatings.peou,
                    sq: surveyRatings.sq,
                    iq: surveyRatings.iq,
                    pu: surveyRatings.pu,
                    bi: surveyRatings.bi,
                    tags: selectedSurveyTags
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Confetti!
                    if (window.confetti) {
                        confetti({
                            particleCount: 150,
                            spread: 80,
                            origin: { y: 0.6 }
                        });
                    }
                    
                    alert(data.message);
                    closeSurveyModal();
                    
                    // Remove banner from UI
                    const banner = document.getElementById('survey-banner');
                    if (banner) {
                        banner.style.transition = 'all 0.5s ease';
                        banner.style.opacity = '0';
                        banner.style.height = '0';
                        banner.style.padding = '0';
                        banner.style.margin = '0';
                        setTimeout(() => banner.remove(), 500);
                    }
                } else {
                    alert(data.message);
                    btnPrimary.disabled = false;
                    btnPrimary.innerHTML = originalText;
                }
            })
            .catch(err => {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์: ' + err.message);
                btnPrimary.disabled = false;
                btnPrimary.innerHTML = originalText;
            });
        }
    </script>
    <?php endif; ?>

    <!-- System Messages & Broadcast Notification Modal (Balanced 80vh Floating Card, Clean Aesthetic) -->
    <div id="messages-modal" onclick="if(event.target === this) closeMessagesModal()" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(6px); z-index: 9999; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box;">
        <div class="card-dark" onclick="event.stopPropagation()" style="width: 100%; max-width: 440px; height: 80vh; max-height: 80vh; display: flex; flex-direction: column; background: var(--bg-card); border-radius: 22px; box-shadow: 0 20px 45px rgba(0,0,0,0.25); border: 1px solid var(--border-color); overflow: hidden; padding: 0;">
            
            <!-- Header (Fixed Top with Spacious Title and Mark-All-Read Icon) -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--border-color); background: var(--bg-darker); flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                    <span style="font-size: 20px; flex-shrink: 0;">🔔</span>
                    <h3 style="margin: 0; font-size: 16.5px; font-weight: 800; color: var(--text-primary); white-space: nowrap; letter-spacing: -0.2px;">
                        การแจ้งเตือน & ข่าวสาร
                    </h3>
                </div>
                <div style="flex-shrink: 0; display: flex; align-items: center;">
                    <button type="button" onclick="markAllMessagesRead()" style="background: rgba(2, 132, 199, 0.08); border: 1px solid rgba(2, 132, 199, 0.25); color: var(--color-accent); width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;" title="ทำเครื่องหมายว่าอ่านแล้วทั้งหมด">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L7 17l-5-5M22 10l-7.5 7.5L13 16"></path></svg>
                    </button>
                </div>
            </div>

            <!-- Messages List (Scrollable, Hidden Scrollbar) -->
            <div id="messages-list-container" style="flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 10px; scrollbar-width: none; -ms-overflow-style: none; -webkit-overflow-scrolling: touch;">
                <div style="text-align: center; color: var(--text-muted); padding: 40px 0;">กำลังโหลดข้อความ...</div>
            </div>

            <!-- Footer (Clean Bottom Close Button) -->
            <div style="padding: 10px 16px; border-top: 1px solid var(--border-color); background: var(--bg-darker); flex-shrink: 0;">
                <button type="button" onclick="closeMessagesModal()" class="btn-giant btn-giant-secondary" style="margin: 0; height: 42px; font-size: 14.5px; font-weight: 800; border-radius: 12px; width: 100%; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center;">
                    ปิด
                </button>
            </div>

        </div>
    </div>

    <script>
        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function formatThaiRelativeTime(dateString) {
            if (!dateString) return '';
            const normalized = dateString.replace(' ', 'T');
            const msgDate = new Date(normalized);
            const now = new Date();
            const diffMs = now - msgDate;
            const diffSec = Math.floor(diffMs / 1000);
            const diffMin = Math.floor(diffSec / 60);
            const diffHours = Math.floor(diffMin / 60);
            const diffDays = Math.floor(diffHours / 24);

            if (isNaN(diffMs) || diffSec < 60) {
                return 'เมื่อสักครู่';
            } else if (diffMin < 60) {
                return `${diffMin} นาทีที่แล้ว`;
            } else if (diffHours < 24) {
                return `${diffHours} ชั่วโมงที่แล้ว`;
            } else if (diffDays === 1) {
                return 'เมื่อวานนี้';
            } else if (diffDays < 30) {
                return `${diffDays} วันที่แล้ว`;
            } else {
                const months = Math.floor(diffDays / 30);
                return `${months} เดือนที่แล้ว`;
            }
        }

        window._expandedMessageId = null;

        function handleMessageClick(msgId, isUnread) {
            if (isUnread) {
                window._expandedMessageId = msgId;
                markMessageRead(msgId);
            } else {
                if (window._expandedMessageId === msgId) {
                    window._expandedMessageId = null;
                } else {
                    window._expandedMessageId = msgId;
                }
                renderMessagesList(window._cachedMessages || []);
            }
        }

        // Messaging Client for VHV
        function fetchMessages() {
            fetch('../api/messages.php?action=get_messages')
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        const badge = document.getElementById('unread-msg-badge');
                        if (badge) {
                            if (data.unread_count > 0) {
                                badge.innerText = data.unread_count > 99 ? '99+' : data.unread_count;
                                badge.style.display = 'block';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                        window._cachedMessages = data.messages || [];
                        renderMessagesList(data.messages || []);
                    }
                })
                .catch(() => {});
        }

        function renderMessagesList(messages) {
            const container = document.getElementById('messages-list-container');
            if (!container) return;

            if (!messages || messages.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; color: var(--text-muted); padding: 50px 15px; margin: auto;">
                        <div style="font-size: 40px; margin-bottom: 10px;">📭</div>
                        <div style="font-size: 15px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">ไม่มีข้อความแจ้งเตือนใหม่</div>
                        <div style="font-size: 12.5px; color: var(--text-secondary);">เมื่อมีประกาศหรือข่าวสารจาก สสอ. หรือ รพ.สต. จะแสดงที่นี่</div>
                    </div>
                `;
                return;
            }

            // Always sort latest news first (created_at DESC, then message_id DESC)
            const sortedMessages = [...messages].sort((a, b) => {
                const dateA = new Date((a.created_at || '').replace(' ', 'T')).getTime() || 0;
                const dateB = new Date((b.created_at || '').replace(' ', 'T')).getTime() || 0;
                if (dateB !== dateA) return dateB - dateA;
                return (b.message_id || 0) - (a.message_id || 0);
            });

            // If all messages are read and none is currently expanded, expand the latest one by default!
            const hasUnread = sortedMessages.some(m => !m.is_read || m.is_read == 0);
            if (!hasUnread && window._expandedMessageId === null && sortedMessages.length > 0) {
                window._expandedMessageId = sortedMessages[0].message_id;
            }

            let html = '';
            sortedMessages.forEach(msg => {
                const isUnread = !msg.is_read || msg.is_read == 0;
                const isUrgent = msg.priority === 'urgent' || msg.priority === 'emergency';
                const isExpanded = isUnread || (window._expandedMessageId === msg.message_id);

                // Relative time string & sender
                const timeAgo = formatThaiRelativeTime(msg.created_at);
                const senderName = escapeHtml(msg.sender_name || 'ผู้ดูแลระบบ');

                // Card Styling: Unread vs Read
                let cardBg = isUnread 
                    ? 'linear-gradient(135deg, rgba(2, 132, 199, 0.08), rgba(14, 165, 233, 0.03))' 
                    : 'var(--bg-card)';
                let cardBorder = isUnread 
                    ? 'border: 1.5px solid rgba(2, 132, 199, 0.35); border-left: 5px solid var(--color-accent);' 
                    : (isExpanded ? 'border: 1px solid var(--border-color); border-left: 4px solid var(--color-accent);' : 'border: 1px solid var(--border-color); border-left: 4px solid #cbd5e1;');
                
                if (isUrgent && isUnread) {
                    cardBorder = 'border: 1.5px solid rgba(239, 68, 68, 0.35); border-left: 5px solid #EF4444;';
                    cardBg = 'linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(248, 113, 113, 0.03))';
                }

                let statusBadge = '';
                if (isUnread) {
                    if (msg.priority === 'emergency') {
                        statusBadge = '<span style="background: linear-gradient(135deg, #ef4444, #dc2626); color:#ffffff; font-size:10px; font-weight:800; padding:2px 7px; border-radius:999px; box-shadow: 0 2px 5px rgba(220,38,38,0.3); white-space: nowrap;">🚨 ด่วนที่สุด</span>';
                    } else if (msg.priority === 'urgent') {
                        statusBadge = '<span style="background: linear-gradient(135deg, #f59e0b, #d97706); color:#ffffff; font-size:10px; font-weight:800; padding:2px 7px; border-radius:999px; box-shadow: 0 2px 5px rgba(217,119,6,0.3); white-space: nowrap;">⚠️ ด่วน</span>';
                    } else {
                        statusBadge = '<span style="background: linear-gradient(135deg, #0284c7, #0369a1); color:#ffffff; font-size:10px; font-weight:800; padding:2px 7px; border-radius:999px; box-shadow: 0 2px 5px rgba(2,132,199,0.25); white-space: nowrap;">✨ ข่าวใหม่</span>';
                    }
                } else {
                    const expandIcon = isExpanded ? '▲' : '▼';
                    statusBadge = `<span style="background: rgba(148, 163, 184, 0.15); color: #64748b; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 6px; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">✓ อ่านแล้ว <span style="font-size: 8px;">${expandIcon}</span></span>`;
                }

                let titleColor = isUnread ? 'var(--text-primary)' : (isExpanded ? 'var(--text-primary)' : 'var(--text-secondary)');
                let bodyColor = isUnread ? 'var(--text-primary)' : (isExpanded ? 'var(--text-secondary)' : 'var(--text-muted)');
                let cardOpacity = isUnread ? '1' : (isExpanded ? '1' : '0.84');

                // Body content: expanded full or collapsed 1-line preview with ellipsis ...
                let bodyHtml = '';
                if (isExpanded) {
                    bodyHtml = `<p style="font-size: 13px; color: ${bodyColor}; margin: 0 0 8px 0; line-height: 1.45; white-space: pre-line; word-break: break-word;">${escapeHtml(msg.message_body)}</p>`;
                } else {
                    bodyHtml = `<p style="font-size: 12.5px; color: ${bodyColor}; margin: 0 0 6px 0; line-height: 1.35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; word-break: break-word;">${escapeHtml(msg.message_body)}</p>`;
                }

                html += `
                    <div onclick="handleMessageClick(${msg.message_id}, ${isUnread ? 'true' : 'false'})" style="background: ${cardBg}; border-radius: 14px; padding: ${isExpanded ? '12px 14px' : '10px 12px'}; box-shadow: var(--neumorph-flat); ${cardBorder} opacity: ${cardOpacity}; cursor: pointer; transition: all 0.2s ease; position: relative;">
                        <!-- Header Row: Title & Badge -->
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 4px;">
                            <div style="display: flex; align-items: center; gap: 6px; min-width: 0; flex: 1;">
                                ${isUnread ? '<span style="width: 8px; height: 8px; border-radius: 50%; background: #0284c7; display: inline-block; flex-shrink: 0; box-shadow: 0 0 6px #0284c7;"></span>' : ''}
                                <strong style="font-size: 14px; color: ${titleColor}; font-weight: ${isUnread || isExpanded ? '800' : '700'}; line-height: 1.35; white-space: ${isExpanded ? 'normal' : 'nowrap'}; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(msg.title)}</strong>
                            </div>
                            <div style="flex-shrink: 0;">${statusBadge}</div>
                        </div>

                        <!-- Message Body -->
                        ${bodyHtml}

                        <!-- Footer Row: Relative Time & Sender -->
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: var(--text-muted); border-top: 1px dashed rgba(13,44,84,0.08); padding-top: 5px; margin-top: 2px;">
                            <span>🕒 ส่งเมื่อ ${timeAgo}</span>
                            <span style="font-weight: 600; color: var(--text-secondary);">โดย ${senderName}</span>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        function openMessagesModal() {
            document.getElementById('messages-modal').style.display = 'flex';
            fetchMessages();
        }

        function closeMessagesModal() {
            document.getElementById('messages-modal').style.display = 'none';
        }

        function markMessageRead(msgId) {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'mark_read', message_id: msgId })
            }).then(() => fetchMessages());
        }

        function markAllMessagesRead() {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'mark_all_read' })
            }).then(() => {
                window._expandedMessageId = null;
                fetchMessages();
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchMessages();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const m = document.getElementById('messages-modal');
                if (m && m.style.display !== 'none') closeMessagesModal();
            }
        });
    </script>

    <?php include_once __DIR__ . '/../config/dev_modal.php'; ?>
</body>
</html>
