<?php
// vhv/index.php
require_once __DIR__ . '/../config/session.php';

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
$db_error = '';

try {
    $pendingStmt = $pdo->prepare("
        SELECT a.assignment_id, a.assignment_status, p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo, p.sex, p.birth, p.need_screen_dm, p.need_screen_ht, p.health_status_origin,
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
        WHERE a.vhv_id = ? AND a.budget_year = 2026 AND a.assignment_status = 'pending'
        ORDER BY LENGTH(p.house_no), p.house_no
    ");
    $pendingStmt->execute([$vhvId]);
    $pendingTasks = $pendingStmt->fetchAll();

    $completedStmt = $pdo->prepare("
        SELECT a.assignment_id, a.assignment_status, p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo, p.sex, p.birth,
               sr.sys_bp1, sr.dia_bp1, sr.sys_bp2, sr.dia_bp2, sr.dtx_value, sr.dtx_type,
               sr.weight, sr.height, sr.waist, sr.bmi, sr.diet_risk, sr.exercise_risk,
               sr.stress_risk, sr.smoking_risk, sr.alcohol_risk, sr.cv_risk_score,
               sr.skipped_reason, sr.advice_given,
               ht.sbp as base_sbp, ht.dbp as base_dbp, dm.bslevel as base_bslevel
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        LEFT JOIN screening_results sr ON a.assignment_id = sr.assignment_id
        LEFT JOIN staging_hdc_ht ht ON p.cid = ht.cid
        LEFT JOIN staging_hdc_dm dm ON p.cid = dm.cid
        WHERE a.vhv_id = ? AND a.budget_year = 2026 AND a.assignment_status IN ('completed', 'skipped')
        ORDER BY a.assigned_at DESC
    ");
    $completedStmt->execute([$vhvId]);
    $completedTasks = $completedStmt->fetchAll();

    // Fetch DPAC followups
    $dpacStmt = $pdo->prepare("
        SELECT f.followup_id, f.round_number, f.status, f.skip_count, e.risk_type,
               p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        JOIN target_population p ON e.cid = p.cid
        WHERE f.vhv_id = ? AND f.status = 'pending'
        ORDER BY p.moo, p.house_no
    ");
    $dpacStmt->execute([$vhvId]);
    $dpacTasks = $dpacStmt->fetchAll();

    // Fetch completed DPAC followups
    $completedDpacStmt = $pdo->prepare("
        SELECT f.followup_id, f.round_number, f.completed_at, e.risk_type,
               f.weight, f.height, f.waist, f.bp_sys, f.bp_dia, f.fbs, f.health_risk_level, f.advice_given,
               p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.sex, p.birth,
               ht.sbp as base_sbp, ht.dbp as base_dbp, dm.bslevel as base_bslevel
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        JOIN target_population p ON e.cid = p.cid
        LEFT JOIN staging_hdc_ht ht ON p.cid = ht.cid
        LEFT JOIN staging_hdc_dm dm ON p.cid = dm.cid
        WHERE f.vhv_id = ? AND f.status = 'completed'
        ORDER BY f.completed_at DESC
    ");
    $completedDpacStmt->execute([$vhvId]);
    $completedDpacTasks = $completedDpacStmt->fetchAll();

    // Check if the current VHV has submitted the satisfaction survey
    $hasSubmittedSurvey = false;
    try {
        $surveyCheck = $pdo->prepare("SELECT COUNT(*) FROM vhv_survey_participants WHERE vhv_id = ? AND budget_year = 2026");
        $surveyCheck->execute([$vhvId]);
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
    <title>NCDs by อสม.อำเภอ<?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <script src="../assets/js/app.js"></script>
    <style>
        .tabs {
            display: flex;
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 6px;
            margin-bottom: 20px;
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
            font-size: 15px;
            font-weight: 800;
            padding: 12px 6px;
            cursor: pointer;
            border-radius: calc(var(--border-radius) - 6px);
            transition: all var(--transition-speed);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .tab-btn.active {
            background-color: var(--bg-main);
            color: var(--color-accent);
            box-shadow: var(--neumorph-flat);
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
        <?php if (!empty($db_error)): ?>
            <div style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 20px; font-weight: bold; font-size: 15px; text-align: center;">
                เกิดข้อผิดพลาดในการโหลดข้อมูล: <?= htmlspecialchars($db_error) ?>
            </div>
        <?php endif; ?>

        <!-- VHV Info Header -->
        <div class="vhv-header" style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px; padding: 20px 16px; position: relative;">
            <?php if (!$hasSubmittedSurvey): ?>
                <button id="survey-banner" onclick="openSurveyModal()" style="position: absolute; top: 16px; right: 16px; background: linear-gradient(135deg, var(--color-yellow) 0%, #d97706 100%); color: white; border: none; border-radius: 50%; width: 46px; height: 46px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4); cursor: pointer; z-index: 10; font-size: 22px; animation: pulse-yellow-ring 2s infinite, float-bubble 2s ease-in-out infinite;" title="ทำแบบประเมินรับโบนัส 5 แต้ม! 🎁">
                    🎁
                </button>
            <?php endif; ?>

            <a href="../about.php" onclick="openDevModal(event)" title="เกี่ยวกับระบบและผู้พัฒนา" style="flex-shrink: 0;">
                <img src="../assets/icon.png" alt="NCDs Prevention Logo" style="width: 60px; height: 60px; border-radius: 14px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
            </a>
            <div style="flex-grow: 1; min-width: 200px;">
                <h3 style="color: var(--color-accent); margin: 0; font-size: 14px; font-weight: 800; letter-spacing: 0.5px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; text-size-adjust: none; -webkit-text-size-adjust: none;">
                    <span style="white-space: nowrap;">อสม. ประจำบ้าน<?= DISTRICT_NAME ?></span>
                    <a href="manual.php" style="color: var(--color-accent); text-decoration: none; font-size: 13px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; background: rgba(30, 64, 175, 0.08); padding: 4px 10px; border-radius: 50px; white-space: nowrap; text-size-adjust: none; -webkit-text-size-adjust: none;">
                        📖 คู่มือการใช้งาน
                    </a>
                </h3>
                <h2 style="color: var(--text-primary); margin: 4px 0; font-size: 20px; font-weight: 800; word-break: break-word;"><?= htmlspecialchars($vhvName) ?></h2>
                <p style="color: var(--text-secondary); margin: 0; font-size: 13px; text-size-adjust: none; -webkit-text-size-adjust: none; line-height: 1.4;">
                    หมู่ที่ <?= $vhvMoo ?> • สังกัดรพ.สต. [<?= htmlspecialchars($hoscode) ?>]
                    <?php if ($isLeader == 1): ?>
                        • <span style="color: var(--color-accent); font-weight: bold;">ประธาน อสม. หมู่บ้าน</span>
                    <?php elseif ($isLeader == 2): ?>
                        • <span style="color: #a855f7; font-weight: bold; background: rgba(168,85,247,0.1); padding: 2px 6px; border-radius: 4px;">🏆 ประธาน อสม. ตำบล</span>
                    <?php elseif ($isLeader >= 3): ?>
                        • <span style="color: #ec4899; font-weight: bold; background: rgba(236,72,153,0.1); padding: 2px 6px; border-radius: 4px;">👑 ประธาน อสม. อำเภอ</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Leader Password Reset Tool -->
        <?php if ($isLeader && !empty($subVhvs)): ?>
            <div class="card-dark" style="padding: 16px;">
                <h4 style="color: var(--color-accent); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; font-size: 18px; font-weight: 800;">
                    🔑 รีเซ็ตรหัสผ่าน อสม. <?php if ($isLeader == 1): ?>ในหมู่บ้าน<?php elseif ($isLeader == 2): ?>ในตำบล<?php else: ?>ในอำเภอ<?php endif; ?>
                </h4>
                <div style="display: flex; gap: 12px;">
                    <select id="reset_target_vhv" class="form-select" style="flex-grow: 1; height: 48px; font-size: 15px;">
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
                    <button onclick="resetPassword()" class="numpad-btn btn-action" style="height: 48px; width: 120px; font-size: 14px; margin-top: 0; border-radius: var(--border-radius); font-weight: 800;">
                        รีเซ็ต "1234"
                    </button>
                </div>
                <div id="reset-result" style="margin-top: 8px; font-size: 14px; text-align: center; font-weight: bold;"></div>
            </div>
        <?php endif; ?>



        <!-- Task Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('pending-list', this)">
                งานค้าง (<?= count($pendingTasks) ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('dpac-list', this)" style="color: #b91c1c;">
                DPAC (<?= count($dpacTasks) ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('completed-list', this)">
                เสร็จสิ้น/ข้าม (<?= count($completedTasks) + count($completedDpacTasks) ?>)
            </button>
        </div>

        <!-- Pending Tasks List -->
        <div id="pending-list" class="tab-content">
            <?php if (empty($pendingTasks)): ?>
                <div class="card-dark" style="text-align: center; padding: 36px 20px; box-shadow: var(--neumorph-flat); margin-top: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; border-radius: var(--border-radius); overflow: hidden; position: relative;">
                    <!-- Floating celebratory background elements -->
                    <div style="position: absolute; top: 10px; left: 10%; font-size: 24px; opacity: 0.15; animation: float-bubble 4s ease-in-out infinite;">✨</div>
                    <div style="position: absolute; bottom: 15px; right: 8%; font-size: 28px; opacity: 0.15; animation: float-bubble 5s ease-in-out infinite 1s;">❤️</div>
                    <div style="position: absolute; top: 20%; right: 12%; font-size: 20px; opacity: 0.12; animation: float-bubble 6s ease-in-out infinite 0.5s;">🩺</div>
                    <div style="position: absolute; bottom: 30%; left: 15%; font-size: 22px; opacity: 0.12; animation: float-bubble 4.5s ease-in-out infinite 1.5s;">💪</div>
                    
                    <!-- Pulse badge -->
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); border: 2px solid rgba(16, 185, 129, 0.25); display: flex; align-items: center; justify-content: center; box-shadow: var(--neumorph-inset); position: relative; animation: pulse-green-ring 2.5s infinite;">
                        <span style="font-size: 38px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">🏆</span>
                    </div>
                    
                    <div>
                        <h4 style="color: var(--color-green); font-size: 18px; font-weight: 800; margin: 0 0 6px 0; letter-spacing: 0.5px; text-size-adjust: none; -webkit-text-size-adjust: none;">ภารกิจคัดกรองสำเร็จครบถ้วน!</h4>
                        <p style="font-size: 14px; color: var(--text-primary); font-weight: bold; margin: 0 0 4px 0; line-height: 1.5; text-size-adjust: none; -webkit-text-size-adjust: none;">ไม่มีงานค้างในเขตรับผิดชอบของคุณ</p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 0; line-height: 1.4; text-size-adjust: none; -webkit-text-size-adjust: none;">ขอบคุณที่เป็นส่วนสำคัญในการร่วมดูแลสุขภาพชุมชนอำเภอ<?= DISTRICT_NAME ?></p>
                    </div>

                    <!-- Shortcut Action -->
                    <a href="leaderboard.php" style="margin-top: 6px; display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: rgba(30, 64, 175, 0.08); border-radius: 50px; text-decoration: none; color: var(--color-accent); font-weight: 800; font-size: 13px; box-shadow: var(--neumorph-flat); transition: all 0.3s ease; text-size-adjust: none; -webkit-text-size-adjust: none;" onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
                        🥇 ดูแต้มสะสมและตรารางวัล อสม.
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($pendingTasks as $pt): ?>
                    <div class="task-card" data-assignment-id="<?= $pt['assignment_id'] ?>" data-hid="<?= htmlspecialchars($pt['hid'] ?? '') ?>" data-cid="<?= htmlspecialchars($pt['cid']) ?>" onclick="openTestModal('<?= htmlspecialchars($pt['house_no']) ?>', '<?= htmlspecialchars($pt['hid'] ?? '') ?>', '<?= htmlspecialchars($pt['cid']) ?>')">
                        <div class="task-info">
                            <h4>บ้านเลขที่ <?= htmlspecialchars($pt['house_no']) ?></h4>
                            <p>ผู้รับคัดกรอง: <?= htmlspecialchars($pt['first_name'] . ' ' . $pt['last_name']) ?></p>
                            <p style="font-size: 12px; margin-top: 4px; color: var(--text-muted);">
                                สิทธิ์การคัดกรอง: 
                                <?php if ($pt['need_screen_dm']): ?>
                                    <span style="color: var(--color-accent);">DM</span>
                                  <?php endif; ?>
                                <?php if ($pt['need_screen_ht']): ?>
                                    <span style="color: var(--color-primary); margin-left: 5px;">HT</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <svg width="24" height="24" fill="none" stroke="var(--border-color)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"></path></svg>
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
                กระดานคะแนน
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
        const isSandboxMode = <?= isSandboxMode($hoscode) ? 'true' : 'false' ?>;
        let currentTestHid = '';
        let currentTestCid = '';
        function openTestModal(houseNo, hid, cid) {
            if (!isSandboxMode) {
                alert("⚠️ ระบบทำงานในโหมดใช้งานจริง: กรุณากดปุ่ม 'สแกนบ้าน' ด้านล่างเพื่อสแกน QR Code ประจำบ้านเป้าหมายและเริ่มทำการคัดกรอง");
                return;
            }
            document.getElementById('test-house-no').textContent = houseNo;
            currentTestHid = hid;
            currentTestCid = cid;
            document.getElementById('test-modal').style.display = 'flex';
        }
        function closeTestModal() {
            document.getElementById('test-modal').style.display = 'none';
        }
        document.getElementById('btn-enter-test').onclick = function() {
            if (currentTestHid) {
                window.location.href = 'screening_form.php?hid=' + currentTestHid;
            } else {
                window.location.href = 'screening_form.php?cid=' + currentTestCid;
            }
        };
        function switchTab(tabId, btn) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            // Remove active from all buttons
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
            });
            // Show selected tab & set button active
            document.getElementById(tabId).style.display = 'block';
            btn.classList.add('active');
        }

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
            const tabs = document.querySelectorAll('.tab-btn');
            if (tabs.length === 3) {
                // Pending Tab
                const pendingTab = tabs[0];
                const pendingMatch = pendingTab.textContent.match(/\((\d+)\)/);
                if (pendingMatch) {
                    const current = parseInt(pendingMatch[1]);
                    pendingTab.textContent = `งานค้าง (${current + pendingCountAdjust})`;
                }
                
                // DPAC Tab
                const dpacTab = tabs[1];
                const dpacMatch = dpacTab.textContent.match(/\((\d+)\)/);
                if (dpacMatch) {
                    const current = parseInt(dpacMatch[1]);
                    dpacTab.textContent = `DPAC (${current + dpacCountAdjust})`;
                }

                // Completed Tab
                const completedTab = tabs[2];
                const completedMatch = completedTab.textContent.match(/\((\d+)\)/);
                if (completedMatch) {
                    const current = parseInt(completedMatch[1]);
                    completedTab.textContent = `เสร็จสิ้น/ข้าม (${current + completedCountAdjust})`;
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

    <?php include_once __DIR__ . '/../config/dev_modal.php'; ?>
</body>
</html>
