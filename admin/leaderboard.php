<?php
// admin/leaderboard.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/gamification_config.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

$admin_username = $_SESSION['admin_username'] ?? '';

$hc_names = get_health_units();

$tambon_names = [];
try {
    $stmt = $pdo->query("SELECT sub_district_code, CONCAT('ตำบล', sub_district_name) FROM sub_districts ORDER BY sub_district_code ASC");
    $tambon_names = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (\Exception $e) {
    // Fallback
    $tambon_names = [
        '342001' => 'ตำบลตาลสุม',
        '342002' => 'ตำบลสำโรง',
        '342003' => 'ตำบลจิกเทิง',
        '342004' => 'ตำบลหนองกุง',
        '342005' => 'ตำบลนาคาย',
        '342006' => 'ตำบลคำหว้า'
    ];
}

$hospitalTambons = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT hoscode, sub_district_code FROM villages WHERE hoscode IS NOT NULL AND hoscode != ''");
    $hospitalTambons = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (\Exception $e) {
    // Fallback
}

// Gamification & Leaderboard settings
$gamificationConfig = get_gamification_config();
$defaultGamificationConfig = get_default_gamification_config();

// Fetch all VHVs with their points breakdown and progress subqueries for achievements
$isSandboxVal = isSandboxMode($admin_hoscode) ? 1 : 0;

$sql = "
    SELECT 
        u.vhv_id, 
        u.vhv_name, 
        u.vhv_moo, 
        u.vhid_code,
        u.hoscode, 
        u.is_hl_coach,
        u.approved,
        (
            SELECT COALESCE(SUM(r.points_earned), 0)
            FROM vhv_rewards r
            JOIN task_assignments ta ON r.assignment_id = ta.assignment_id
            WHERE r.vhv_id = u.vhv_id 
              AND r.approval_status IN ('approved', 'waiting') 
              AND r.followup_id IS NULL
              AND r.is_sandbox = :is_sandbox1
        ) as screening_points,
        (
            SELECT COALESCE(SUM(r.points_earned), 0)
            FROM vhv_rewards r
            JOIN dpac_followups f ON r.followup_id = f.followup_id
            WHERE r.vhv_id = u.vhv_id 
              AND r.approval_status IN ('approved', 'waiting') 
              AND r.followup_id IS NOT NULL
              AND r.is_sandbox = :is_sandbox2
        ) as dpac_points,
        (
            SELECT COALESCE(SUM(CASE WHEN (r.followup_id IS NULL AND r.assignment_id IS NULL) OR (r.followup_id IS NULL AND ta.assignment_id IS NOT NULL) OR (r.followup_id IS NOT NULL AND f.followup_id IS NOT NULL) THEN r.points_earned ELSE 0 END), 0)
            FROM vhv_rewards r
            LEFT JOIN task_assignments ta ON r.assignment_id = ta.assignment_id
            LEFT JOIN dpac_followups f ON r.followup_id = f.followup_id
            WHERE r.vhv_id = u.vhv_id 
              AND r.approval_status IN ('approved', 'waiting') 
              AND r.is_sandbox = :is_sandbox3
        ) as total_points,
        (SELECT COUNT(*) FROM task_assignments WHERE vhv_id = u.vhv_id AND budget_year = 2026 AND is_sandbox = :is_sandbox4) as total_assigned,
        (SELECT COUNT(*) FROM task_assignments ta WHERE ta.vhv_id = u.vhv_id AND ta.budget_year = 2026 AND (ta.assignment_status = 'completed' OR EXISTS (SELECT 1 FROM screening_results sr WHERE sr.assignment_id = ta.assignment_id OR (sr.target_cid = ta.target_cid AND (sr.round_number = ta.round_number OR sr.round_number IS NULL)))) AND ta.is_sandbox = :is_sandbox5) as completed,
        (SELECT COUNT(*) FROM vhv_rewards WHERE vhv_id = u.vhv_id AND approval_status = 'waiting' AND is_sandbox = :is_sandbox6) as waiting_rewards
    FROM vhv_users u
    WHERE u.approved = 1
    ORDER BY total_points DESC, u.vhv_name ASC
";

require_once __DIR__ . '/../config/demo_data.php';

$error = '';
if (DemoDataProvider::isDemoMode()) {
    $vhv_list = DemoDataProvider::getDemoLeaderboard();
} else {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'is_sandbox1' => $isSandboxVal,
            'is_sandbox2' => $isSandboxVal,
            'is_sandbox3' => $isSandboxVal,
            'is_sandbox4' => $isSandboxVal,
            'is_sandbox5' => $isSandboxVal,
            'is_sandbox6' => $isSandboxVal
        ]);
        $vhv_list = $stmt->fetchAll();
    } catch (\PDOException $e) {
        $vhv_list = [];
        $error = "เกิดข้อผิดพลาดในการโหลดข้อมูล: " . $e->getMessage();
    }
}

// Calculate summary stats
$total_vhvs = count($vhv_list);
$active_vhvs = 0;
$total_points = 0;
$top_points = 0;
$top_vhv_name = '-';

foreach ($vhv_list as $vhv) {
    if ($vhv['approved']) {
        $active_vhvs++;
    }
    $points = (float) $vhv['total_points'];
    $total_points += $points;
    if ($points > $top_points) {
        $top_points = $points;
        $top_vhv_name = $vhv['vhv_name'];
    }
}
$avg_points = $total_vhvs > 0 ? round($total_points / $total_vhvs, 1) : 0;
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กระดานคะแนน อสม. ระดับอำเภอ - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-main);
        }

        /* Stats grid spacing */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card-premium {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--neumorph-flat);
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            overflow: hidden;
            transition: all var(--transition-speed);
        }

        .stat-card-premium:hover {
            transform: translateY(-2px);
        }

        .stat-card-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--color-primary);
        }

        .stat-card-premium.accent::before {
            background: var(--color-accent);
        }

        .stat-card-premium.success::before {
            background: var(--color-green);
        }

        .stat-card-premium.warning::before {
            background: var(--color-yellow);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background-color: var(--bg-darker);
            box-shadow: var(--neumorph-inset);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 13.5px;
            color: var(--text-secondary);
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
        }

        /* Filter Panel */
        .filter-panel {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--neumorph-flat);
            padding: 24px;
            margin-bottom: 24px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: flex-end;
        }

        /* Modal specific styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-container {
            background: var(--bg-card);
            border-radius: 16px;
            box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.25), 0 0 0 1px var(--border-color);
            width: 100%;
            max-width: 760px;
            max-height: 88vh;
            overflow-y: auto;
            animation: modalSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        .modal-header-premium {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-main);
            border-radius: 16px 16px 0 0;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-close-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: color var(--transition-speed);
        }

        .modal-close-btn:hover {
            color: var(--color-red);
        }

        /* Settings Tabs */
        .settings-tab-nav {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid var(--border-color);
            padding: 0 24px;
            background: var(--bg-main);
            overflow-x: auto;
        }

        .settings-tab-btn {
            padding: 12px 18px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .settings-tab-btn:hover {
            color: var(--color-primary);
        }

        .settings-tab-btn.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
            background: rgba(59, 130, 246, 0.05);
        }

        .settings-tab-pane {
            display: none;
        }

        .settings-tab-pane.active {
            display: block;
        }

        .setting-field-card {
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }

        .setting-field-card:hover {
            border-color: rgba(59, 130, 246, 0.4);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .baseline-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
            display: block;
        }

        .btn-reset-section {
            background: rgba(239, 68, 68, 0.08);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.25);
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-reset-section:hover {
            background: #dc2626;
            color: white;
        }

        /* Rank badges */
        .rank-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14.5px;
            box-shadow: var(--neumorph-inset);
        }

        .rank-gold {
            background-color: #fef3c7;
            color: #d97706;
            border: 2px solid #fbbf24;
            box-shadow: 0 4px 8px rgba(251, 191, 36, 0.25);
        }

        .rank-silver {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 2px solid #9ca3af;
            box-shadow: 0 4px 8px rgba(156, 163, 175, 0.2);
        }

        .rank-bronze {
            background-color: #ffedd5;
            color: #c2410c;
            border: 2px solid #f97316;
            box-shadow: 0 4px 8px rgba(249, 115, 22, 0.2);
        }

        .rank-normal {
            background-color: var(--bg-darker);
            color: var(--text-secondary);
        }

        /* Hoverable row action */
        .btn-view-logs {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--color-primary);
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 800;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all var(--transition-speed);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view-logs:hover {
            border-color: var(--color-primary);
            transform: translateY(-1px);
            color: var(--color-accent);
        }

        .btn-view-logs:active {
            box-shadow: var(--neumorph-inset);
            transform: scale(0.97);
        }

        /* Allow word wrapping in table headers, but prevent wrapping in table body cells for clean Excel-like records */
        table.admin-table th {
            white-space: normal !important;
        }

        table.admin-table td {
            white-space: nowrap !important;
        }

        .btn-view-logs-icon {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--color-primary);
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all var(--transition-speed);
            font-size: 15px;
        }

        .btn-view-logs-icon:hover {
            border-color: var(--color-primary);
            color: var(--color-accent);
            transform: translateY(-1px);
        }

        .btn-view-logs-icon:active {
            box-shadow: var(--neumorph-inset);
            transform: scale(0.95);
        }

        /* Print formatting */
        @media print {
            .no-print,
            .admin-navbar,
            .stats-grid,
            .filter-panel,
            .btn-view-logs-icon {
                display: none !important;
            }

            body, .admin-body {
                background: white !important;
                color: black !important;
                font-family: 'Sarabun', 'Prompt', sans-serif !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .print-only-header {
                display: block !important;
            }

            .card-dark {
                box-shadow: none !important;
                border: none !important;
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Excel-like grid table style */
            .admin-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 10px !important;
                border: 1px solid #000000 !important;
            }

            .admin-table th,
            .admin-table td {
                border: 1px solid #000000 !important;
                padding: 6px 8px !important;
                font-size: 11px !important;
                color: black !important;
                background: transparent !important;
                text-shadow: none !important;
                box-shadow: none !important;
            }

            .admin-table th {
                background-color: #f2f2f2 !important;
                font-weight: bold !important;
                text-align: center !important;
                white-space: normal !important;
            }

            .admin-table td {
                white-space: nowrap !important;
            }

            .rank-badge {
                border: none !important;
                background: transparent !important;
                box-shadow: none !important;
                width: auto !important;
                height: auto !important;
                display: inline !important;
                font-weight: bold !important;
                color: black !important;
            }

            .admin-table td span {
                background: transparent !important;
                border: none !important;
                color: black !important;
                padding: 0 !important;
                font-size: 10px !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">

        <!-- Header for Screen Mode (hidden in print) -->
        <div class="no-print"
            style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
            <div>
                <h2 style="color: var(--color-accent); margin-top: 0; margin-bottom: 8px;">🏆 กระดานคะแนน อสม. ทั้งอำเภอ
                </h2>
                <p style="color: var(--text-secondary); margin: 0;">
                    ติดตาม จัดลำดับ และวิเคราะห์ผลการสะสมแต้มของ อสม. ทุกตำบลในอำเภอ<?= DISTRICT_NAME ?>
                </p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php if ($admin_hoscode === null): ?>
                    <button onclick="openGamificationModal()" class="btn-giant btn-giant-primary"
                        style="margin: 0; padding: 10px 18px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
                        ⚙️ จัดการกระดาน & ฉายาเกียรติยศ
                    </button>
                <?php endif; ?>
                <button onclick="window.print()" class="btn-giant btn-giant-secondary"
                    style="margin: 0; padding: 10px 20px; font-size: 14.5px; display: inline-flex; align-items: center; gap: 8px;">
                    🖨️ พิมพ์รายงานกระดาน
                </button>
            </div>
        </div>

        <!-- Header for Print Mode (hidden in screen) -->
        <div class="print-only-header" style="display: none;">
            <h2 style="text-align: center; margin: 0 0 6px 0; font-size: 20px; color: black; font-weight: bold;">รายงานทำเนียบผลงานและแต้มสะสม อสม. อำเภอ<?= DISTRICT_NAME ?></h2>
            <p style="text-align: center; margin: 0 0 24px 0; font-size: 12px; color: #444;">ข้อมูล ณ วันที่ <?= date('d/m/Y H:i') ?> น. • เรียงลำดับจากแต้มรวมสูงสุด</p>
        </div>

        <?php if (!empty($error)): ?>
            <div
                style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px; font-weight: bold;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Overview Widgets -->
        <div class="stats-grid">
            <div class="stat-card-premium">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <span class="stat-label">อสม. ลงทะเบียนทั้งหมด</span>
                    <span class="stat-value"><?= number_format($total_vhvs) ?> คน</span>
                </div>
            </div>
            <div class="stat-card-premium success">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <span class="stat-label">อสม. อนุมัติสิทธิ์แล้ว</span>
                    <span class="stat-value"><?= number_format($active_vhvs) ?> คน</span>
                </div>
            </div>
            <div class="stat-card-premium warning">
                <div class="stat-icon">🪙</div>
                <div class="stat-info">
                    <span class="stat-label">แต้มสะสมรวมทั้งอำเภอ</span>
                    <span class="stat-value"><?= number_format($total_points) ?> แต้ม</span>
                </div>
            </div>
            <div class="stat-card-premium accent">
                <div class="stat-icon">🌟</div>
                <div class="stat-info">
                    <span class="stat-label">ค่าเฉลี่ยแต้มต่อ อสม.</span>
                    <span class="stat-value"><?= $avg_points ?> แต้ม</span>
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel no-print">
            <h3
                style="color: var(--color-primary); margin-top: 0; margin-bottom: 18px; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                🔍 ตัวกรองและการค้นหาข้อมูลแบบละเอียด
            </h3>
            <div class="filter-grid">
                <!-- Search Input -->
                <div style="flex-grow: 1;">
                    <label class="modal-form-label" style="font-size: 13.5px;" for="search-input">ค้นหา อสม. (ชื่อ-สกุล
                        หรือ รหัส)</label>
                    <input type="text" id="search-input" class="form-input-text" placeholder="ระบุคำค้นหา..."
                        style="box-shadow: var(--neumorph-inset); text-align: left; height: 40px; margin-bottom: 0;">
                </div>

                <!-- Filter Tambon -->
                <div>
                    <label class="modal-form-label" style="font-size: 13.5px;" for="filter-tambon">ตำบล</label>
                    <select id="filter-tambon" class="form-select"
                        style="box-shadow: var(--neumorph-inset); height: 40px;"
                        onchange="updateHospitalFilterOptions()">
                        <option value="">-- ทุกตำบล --</option>
                        <?php foreach ($tambon_names as $code => $name): ?>
                            <option value="<?= $code ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Hoscode -->
                <div>
                    <label class="modal-form-label" style="font-size: 13.5px;"
                        for="filter-hoscode">หน่วยบริการที่สังกัด</label>
                    <select id="filter-hoscode" class="form-select"
                        style="box-shadow: var(--neumorph-inset); height: 40px;">
                        <option value="">-- ทุกหน่วยบริการ --</option>
                        <?php foreach ($hc_names as $code => $name): ?>
                            <option value="<?= $code ?>" <?= ($admin_hoscode === $code) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Coach Status -->
                <div>
                    <label class="modal-form-label" style="font-size: 13.5px;" for="filter-coach">สถานะ HL-Coach</label>
                    <select id="filter-coach" class="form-select"
                        style="box-shadow: var(--neumorph-inset); height: 40px;">
                        <option value="">-- ทั้งหมด --</option>
                        <option value="coach">เฉพาะ HL-Coach</option>
                        <option value="member">อสม. สมาชิกทั่วไป</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <label class="modal-form-label" style="font-size: 13.5px;" for="sort-by">เรียงลำดับตาม</label>
                    <select id="sort-by" class="form-select" style="box-shadow: var(--neumorph-inset); height: 40px;">
                        <option value="total_points">คะแนนรวมสูงสุด</option>
                        <option value="screening_points">คะแนนคัดกรองสูงสุด</option>
                        <option value="dpac_points">คะแนนติดตาม DPAC สูงสุด</option>
                        <option value="vhv_name">เรียงตามชื่อ ก-ฮ</option>
                    </select>
                </div>

                <!-- Reset Button -->
                <div>
                    <button type="button" onclick="resetFilters()" class="btn-giant btn-giant-secondary"
                        style="height: 40px; line-height: 40px; margin: 0; padding: 0 16px; font-size: 13.5px; width: 100%;">
                        รีเซ็ตตัวกรอง
                    </button>
                </div>
            </div>
        </div>

        <!-- Leaderboard Table Container -->
        <div class="card-dark" style="padding: 24px;">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 8px;">
                <h3 style="color: var(--color-accent); margin: 0; font-size: 18px; font-weight: 800;">
                    📊 ตารางวิเคราะห์แต้มสะสม อสม. (<span id="results-count"><?= $total_vhvs ?></span> รายการ)
                </h3>
                <span id="filtered-label"
                    style="font-size: 13px; color: var(--text-secondary); font-weight: bold; background: rgba(13, 44, 84, 0.05); padding: 4px 10px; border-radius: 8px; box-shadow: var(--neumorph-inset); display: none;">
                    กรองข้อมูลอยู่
                </span>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 70px; text-align: center;">อันดับ</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th style="text-align: center;">หมู่</th>
                            <th>ตำบล</th>
                            <th>หน่วยบริการสังกัด</th>
                            <th style="text-align: right; width: 120px;">แต้มคัดกรอง DM/HT</th>
                            <th style="text-align: right; width: 120px;">แต้มติดตาม DPAC</th>
                            <th style="text-align: right; width: 110px; font-weight: 800; color: var(--color-accent);">
                                แต้มสะสมรวม</th>
                            <th style="text-align: center; width: 120px;">สถานะพิเศษ</th>
                            <th style="width: 80px; text-align: center;" class="no-print">ประวัติแต้ม</th>
                        </tr>
                    </thead>
                    <tbody id="leaderboard-tbody">
                        <!-- Dynamic Row Injection -->
                    </tbody>
                </table>
            </div>

            <div id="no-data-msg"
                style="display: none; text-align: center; color: var(--text-secondary); padding: 40px; font-weight: bold;">
                ❌ ไม่พบข้อมูล อสม. ตามเงื่อนไขการกรองข้างต้น
            </div>
        </div>

    </div>

    <!-- VHV Details Drilldown Modal -->
    <div id="logs-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header-premium">
                <div>
                    <h3 id="modal-vhv-name"
                        style="margin: 0; color: var(--color-primary); font-size: 19px; font-weight: 800;">
                        ประวัติสะสมแต้ม</h3>
                    <p id="modal-vhv-info"
                        style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-secondary); font-weight: bold;">
                    </p>
                </div>
                <button onclick="closeLogsModal()" class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="stats-grid" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div
                        style="background-color: var(--bg-main); border: 1px solid var(--border-color); padding: 16px; border-radius: 12px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                        <span
                            style="font-size: 13px; color: var(--text-secondary); font-weight: bold; display: block; margin-bottom: 6px;">แต้มรวมงานคัดกรอง</span>
                        <div id="modal-stat-screening"
                            style="font-size: 26px; font-weight: 800; color: var(--color-primary);">0.00</div>
                    </div>
                    <div
                        style="background-color: var(--bg-main); border: 1px solid var(--border-color); padding: 16px; border-radius: 12px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                        <span
                            style="font-size: 13px; color: var(--text-secondary); font-weight: bold; display: block; margin-bottom: 6px;">แต้มรวมงานติดตาม
                            DPAC</span>
                        <div id="modal-stat-dpac"
                            style="font-size: 26px; font-weight: 800; color: var(--color-primary);">0.00</div>
                    </div>
                </div>

                <h4
                    style="margin-top: 0; margin-bottom: 12px; color: var(--text-primary); font-size: 15px; font-weight: 800;">
                    รายการการบันทึกงานที่ได้รับการอนุมัติ (Audit Log)</h4>

                <div class="table-responsive" style="max-height: 280px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 10px;">
                    <table class="admin-table" style="font-size: 13.5px; margin: 0; width: 100%;">
                        <thead>
                            <tr>
                                <th>วัน-เวลาบันทึก</th>
                                <th>กิจกรรมสาธารณสุข</th>
                                <th>ผู้รับบริการ</th>
                                <th>เลขบัตรประชาชน (CID)</th>
                                <th style="text-align: right; width: 90px;">แต้มได้รับ</th>
                            </tr>
                        </thead>
                        <tbody id="modal-logs-tbody">
                            <!-- Dynamic logs injection -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                style="padding: 16px 24px; background-color: var(--bg-main); border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; border-radius: 0 0 16px 16px;">
                <button onclick="closeLogsModal()" class="btn-giant btn-giant-secondary"
                    style="margin: 0; padding: 9px 24px; font-size: 14px; width: auto; background-color: #64748b; color: white; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                    ปิดหน้าต่าง
                </button>
            </div>
        </div>
    </div>

    <!-- Gamification & Leaderboard Settings Modal -->
    <div id="gamification-modal" class="modal-overlay">
        <div class="modal-container" style="max-width: 860px;">
            <div class="modal-header-premium">
                <div>
                    <h3 style="margin: 0; color: var(--color-primary); font-size: 19px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        ⚙️ จัดการกระดานคะแนน ฉายาเกียรติยศ & เงื่อนไขแต้มสะสม
                    </h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-secondary);">
                        ปรับแต่งฉายา อสม., สโลแกน รพ.สต., กฎการคำนวณแต้ม และการแสดงผลหน้าบ้านอย่างอิสระ
                    </p>
                </div>
                <button onclick="closeGamificationModal()" class="modal-close-btn">&times;</button>
            </div>

            <!-- Tab Nav -->
            <div class="settings-tab-nav">
                <button type="button" class="settings-tab-btn active" onclick="switchGamificationTab('tab-titles', this)">
                    🏆 1. ฉายา อสม.
                </button>
                <button type="button" class="settings-tab-btn" onclick="switchGamificationTab('tab-hospitals', this)">
                    🏥 2. ฉายา & สโลแกน รพ.สต.
                </button>
                <button type="button" class="settings-tab-btn" onclick="switchGamificationTab('tab-scoring', this)">
                    🪙 3. กฎการคิดแต้มคัดกรอง
                </button>
                <button type="button" class="settings-tab-btn" onclick="switchGamificationTab('tab-display', this)">
                    👁️ 4. การแสดงผลหน้าบ้าน
                </button>
            </div>

            <div class="modal-body" style="max-height: 58vh; overflow-y: auto;">
                <!-- Tab 1: Titles -->
                <div id="tab-titles" class="settings-tab-pane active">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                        <h4 style="margin: 0; font-size: 15px; color: var(--color-primary); font-weight: 800;">
                            👑 ฉายาระดับสูงสุด (Top 1 - 5)
                        </h4>
                        <button type="button" onclick="resetGamificationSection('top5_titles')" class="btn-reset-section">
                            🔄 คืนค่าฉายา Top 5 เป็นค่าเริ่มต้น
                        </button>
                    </div>
                    <div id="top5-inputs-container"></div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                        <h4 style="margin: 0; font-size: 15px; color: var(--color-primary); font-weight: 800;">
                            🎖️ ฉายากลุ่มอันดับ (อันดับ 6 - 50 แบ่งกลุ่มละ 5 อันดับ)
                        </h4>
                        <button type="button" onclick="resetGamificationSection('tier_titles')" class="btn-reset-section">
                            🔄 คืนค่าฉายากลุ่มเป็นค่าเริ่มต้น
                        </button>
                    </div>
                    <div id="tier-inputs-container"></div>
                </div>

                <!-- Tab 2: Hospitals -->
                <div id="tab-hospitals" class="settings-tab-pane">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                        <h4 style="margin: 0; font-size: 15px; color: var(--color-primary); font-weight: 800;">
                            🏥 ฉายาเกียรติยศและสโลแกนประจำ รพ.สต.
                        </h4>
                        <button type="button" onclick="resetGamificationSection('hospital_titles')" class="btn-reset-section">
                            🔄 คืนค่าฉายา รพ.สต. เป็นค่าเริ่มต้น
                        </button>
                    </div>
                    <p style="font-size: 13px; color: var(--text-secondary); margin-top: -6px; margin-bottom: 16px;">
                        ข้อความนี้จะแสดงเป็น Badge พิเศษประจำหน่วยบริการบนกระดานคะแนน เพื่อสร้างแรงบันดาลใจและเอกลักษณ์เฉพาะตำบล
                    </p>
                    <div id="hospital-inputs-container"></div>
                </div>

                <!-- Tab 3: Scoring -->
                <div id="tab-scoring" class="settings-tab-pane">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                        <h4 style="margin: 0; font-size: 15px; color: var(--color-primary); font-weight: 800;">
                            🪙 กฎการคิดแต้มงานคัดกรองและการติดตาม
                        </h4>
                        <button type="button" onclick="resetGamificationSection('scoring_rules')" class="btn-reset-section">
                            🔄 คืนค่ากฎแต้ม เป็นค่าเริ่มต้น
                        </button>
                    </div>

                    <div class="setting-field-card">
                        <label style="font-weight: bold; font-size: 14px; display: block; margin-bottom: 8px; color: var(--text-primary);">
                            รูปแบบการให้แต้มงานคัดกรอง NCDs (DM/HT):
                        </label>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 13.5px; font-weight: 600;">
                                <input type="radio" name="scoring_mode" value="progressive" id="scoring-mode-prog" onchange="toggleScoringModeInputs()">
                                <span>🟢 <strong>แต้มทวีคูณตามเลขรอบ (Progressive Rewards)</strong> <span style="color: var(--text-secondary); font-weight: normal;">— รอบ 1 = 1 แต้ม, รอบ 2 = 2 แต้ม, รอบ 3 = 3 แต้ม, รอบ N = N แต้ม</span></span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 13.5px; font-weight: 600;">
                                <input type="radio" name="scoring_mode" value="custom" id="scoring-mode-custom" onchange="toggleScoringModeInputs()">
                                <span>🔵 <strong>กำหนดแต้มรายรอบอิสระ (Custom per Round)</strong> <span style="color: var(--text-secondary); font-weight: normal;">— ระบุจำนวนแต้มของแต่ละรอบด้วยตนเอง</span></span>
                            </label>
                        </div>

                        <div id="custom-round-points-box" style="display: none; margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--border-color);">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px;">
                                <div>
                                    <label style="font-size: 12.5px; font-weight: bold; display: block; margin-bottom: 4px;">รอบที่ 1 (Baseline):</label>
                                    <input type="number" step="0.25" min="0.25" id="round-pts-1" class="form-input-text" style="height: 38px;">
                                </div>
                                <div>
                                    <label style="font-size: 12.5px; font-weight: bold; display: block; margin-bottom: 4px;">รอบที่ 2:</label>
                                    <input type="number" step="0.25" min="0.25" id="round-pts-2" class="form-input-text" style="height: 38px;">
                                </div>
                                <div>
                                    <label style="font-size: 12.5px; font-weight: bold; display: block; margin-bottom: 4px;">รอบที่ 3:</label>
                                    <input type="number" step="0.25" min="0.25" id="round-pts-3" class="form-input-text" style="height: 38px;">
                                </div>
                                <div>
                                    <label style="font-size: 12.5px; font-weight: bold; display: block; margin-bottom: 4px;">รอบที่ 4:</label>
                                    <input type="number" step="0.25" min="0.25" id="round-pts-4" class="form-input-text" style="height: 38px;">
                                </div>
                                <div>
                                    <label style="font-size: 12.5px; font-weight: bold; display: block; margin-bottom: 4px;">รอบที่ 5 ขึ้นไป:</label>
                                    <input type="number" step="0.25" min="0.25" id="round-pts-5" class="form-input-text" style="height: 38px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="setting-field-card">
                        <label for="dpac-pts-input" style="font-weight: bold; font-size: 14px; display: block; margin-bottom: 6px; color: var(--text-primary);">
                            🏃 แต้มงานติดตามปรับเปลี่ยนพฤติกรรม DPAC (ต่อครั้ง):
                        </label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="number" step="0.25" min="0.25" id="dpac-pts-input" class="form-input-text" style="width: 140px; height: 38px; margin: 0;">
                            <span style="font-size: 13.5px; color: var(--text-secondary);">แต้มต่อการติดตามสำเร็จ 1 ครั้ง</span>
                        </div>
                        <span class="baseline-hint">ค่าเริ่มต้นของระบบ: 1.00 แต้ม</span>
                    </div>

                    <div class="setting-field-card" style="background: rgba(245, 158, 11, 0.08); border-color: rgba(245, 158, 11, 0.3);">
                        <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="sync-past-points-checkbox" style="margin-top: 3px; transform: scale(1.2);">
                            <div>
                                <span style="font-weight: bold; font-size: 13.5px; color: #b45309;">⚡ ปรับปรุงแต้มผลงานย้อนหลังทั้งหมดให้ตรงกับกฎใหม่ทันที (Sync Past Rewards)</span>
                                <p style="margin: 3px 0 0 0; font-size: 12px; color: #92400e;">
                                    หากเลือกตัวเลือกนี้ ระบบจะคำนวณแต้มสะสมของงานคัดกรองและ DPAC ในอดีตทั้งหมดใหม่ตามอัตราแต้มที่กำหนดด้านบน
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Tab 4: Display -->
                <div id="tab-display" class="settings-tab-pane">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                        <h4 style="margin: 0; font-size: 15px; color: var(--color-primary); font-weight: 800;">
                            👁️ การควบคุมการแสดงผลบนกระดานคะแนน
                        </h4>
                        <button type="button" onclick="resetGamificationSection('display_settings')" class="btn-reset-section">
                            🔄 คืนค่าการแสดงผล เป็นค่าเริ่มต้น
                        </button>
                    </div>

                    <div class="setting-field-card">
                        <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                            <div>
                                <span style="font-weight: bold; font-size: 14px; color: var(--text-primary);">แสดงฉายาเกียรติยศ อสม. บนตารางคะแนน</span>
                                <span class="baseline-hint">แสดง Badge ฉายาตามอันดับ 1 - 50 ในคอลัมน์สถานะพิเศษ</span>
                            </div>
                            <input type="checkbox" id="display-vhv-titles-toggle" style="transform: scale(1.3); cursor: pointer;">
                        </label>
                    </div>

                    <div class="setting-field-card">
                        <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                            <div>
                                <span style="font-weight: bold; font-size: 14px; color: var(--text-primary);">แสดงฉายาและสโลแกนประจำ รพ.สต.</span>
                                <span class="baseline-hint">แสดงสโลแกนเกียรติยศใต้ชื่อหน่วยบริการที่สังกัด</span>
                            </div>
                            <input type="checkbox" id="display-hospital-titles-toggle" style="transform: scale(1.3); cursor: pointer;">
                        </label>
                    </div>

                    <div class="setting-field-card">
                        <label for="display-top-limit-select" style="font-weight: bold; font-size: 14px; display: block; margin-bottom: 6px; color: var(--text-primary);">
                            จำนวนอันดับที่แสดงบน Top Leaderboard:
                        </label>
                        <select id="display-top-limit-select" class="form-select" style="max-width: 250px; height: 40px; margin: 0;">
                            <option value="10">Top 10 อันดับแรก</option>
                            <option value="20">Top 20 อันดับแรก</option>
                            <option value="50">Top 50 อันดับแรก (ค่าเริ่มต้น)</option>
                            <option value="100">Top 100 อันดับแรก</option>
                            <option value="0">แสดงทั้งหมดทุกอันดับ</option>
                        </select>
                        <span class="baseline-hint">ค่าเริ่มต้นของระบบ: Top 50 อันดับแรก</span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div style="padding: 16px 24px; background-color: var(--bg-main); border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; border-radius: 0 0 16px 16px; flex-wrap: wrap; gap: 12px;">
                <button type="button" onclick="resetAllGamificationSettings()" class="btn-reset-section" style="padding: 9px 16px;">
                    🔄 คืนค่าเริ่มต้นทั้งหมดของระบบ (Reset All)
                </button>
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="closeGamificationModal()" class="btn-giant btn-giant-secondary" style="margin: 0; padding: 9px 20px; font-size: 14px; width: auto; background-color: #64748b; color: white; border-radius: 8px; font-weight: 600; cursor: pointer; border: none;">
                        ยกเลิก
                    </button>
                    <button type="button" onclick="saveGamificationSettings()" class="btn-giant btn-giant-primary" style="margin: 0; padding: 9px 24px; font-size: 14px; width: auto; background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 8px; font-weight: bold; cursor: pointer; border: none; box-shadow: 0 4px 10px rgba(16,185,129,0.25);">
                        💾 บันทึกการตั้งค่า
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Load data from PHP
        const allVhvs = <?= json_encode($vhv_list, JSON_UNESCAPED_UNICODE) ?>;
        const hcNames = <?= json_encode($hc_names, JSON_UNESCAPED_UNICODE) ?>;
        const tambonNames = <?= json_encode($tambon_names, JSON_UNESCAPED_UNICODE) ?>;
        const loggedInHoscode = <?= json_encode($admin_hoscode, JSON_UNESCAPED_UNICODE) ?>;
        
        // Gamification Settings state
        let gamificationConfig = <?= json_encode($gamificationConfig, JSON_UNESCAPED_UNICODE) ?>;
        const defaultGamificationConfig = <?= json_encode($defaultGamificationConfig, JSON_UNESCAPED_UNICODE) ?>;

        // Dynamic rank title mapping based on active configuration
        function getRankTitle(rank) {
            if (rank <= 0 || rank > 50) return '';
            if (gamificationConfig.display_settings && gamificationConfig.display_settings.show_vhv_titles === false) {
                return '';
            }

            // Top 1-5
            if (rank >= 1 && rank <= 5) {
                return (gamificationConfig.top5_titles && gamificationConfig.top5_titles[rank]) 
                    ? gamificationConfig.top5_titles[rank] 
                    : (defaultGamificationConfig.top5_titles[rank] || '');
            }

            // Group tiers 6-50
            const groupIndex = Math.floor((rank - 6) / 5) + 1;
            const suffixIndex = (rank - 6) % 5;

            const baseTitles = gamificationConfig.tier_titles || defaultGamificationConfig.tier_titles;
            const suffixes = gamificationConfig.tier_suffixes || defaultGamificationConfig.tier_suffixes;

            const base = baseTitles[groupIndex] || defaultGamificationConfig.tier_titles[groupIndex];
            const suffix = suffixes[suffixIndex] || defaultGamificationConfig.tier_suffixes[suffixIndex];

            if (base && suffix) {
                return base + ' ' + suffix;
            }
            return base || '';
        }

        // Set up event listeners for filters
        document.getElementById('search-input').addEventListener('input', renderLeaderboard);
        document.getElementById('filter-tambon').addEventListener('change', renderLeaderboard);
        document.getElementById('filter-hoscode').addEventListener('change', renderLeaderboard);
        document.getElementById('filter-coach').addEventListener('change', renderLeaderboard);
        document.getElementById('sort-by').addEventListener('change', renderLeaderboard);

        // Map hospital to tambon prefix
        const hospitalTambons = <?= json_encode($hospitalTambons) ?>;

        // When Tambon changes, filter hospital options
        function updateHospitalFilterOptions() {
            const selectedTambon = document.getElementById('filter-tambon').value;
            const hoscodeSelect = document.getElementById('filter-hoscode');
            const currentSelected = hoscodeSelect.value;

            // Clear current options except "All"
            hoscodeSelect.innerHTML = '<option value="">-- ทุกหน่วยบริการ --</option>';

            for (const [code, name] of Object.entries(hcNames)) {
                if (!selectedTambon || hospitalTambons[code] === selectedTambon) {
                    const opt = document.createElement('option');
                    opt.value = code;
                    opt.textContent = name;
                    if (code === currentSelected) opt.selected = true;
                    hoscodeSelect.appendChild(opt);
                }
            }
        }

        function resetFilters() {
            document.getElementById('search-input').value = '';
            document.getElementById('filter-tambon').value = '';
            document.getElementById('filter-coach').value = '';
            document.getElementById('sort-by').value = 'total_points';

            // Restore original hospital options based on logged-in constraints
            updateHospitalFilterOptions();
            document.getElementById('filter-hoscode').value = loggedInHoscode || '';

            renderLeaderboard();
        }

        // Achievement and milestone badges helper
        function getAchievementsHtml(vhv) {
            let html = '';
            const screen = parseFloat(vhv.screening_points) || 0;
            const dpac = parseFloat(vhv.dpac_points) || 0;

            if (screen >= 50) {
                html += '<span title="ยอดนักคัดกรอง 50+ แต้ม" style="margin-left: 5px; cursor: help;">🏆</span>';
            } else if (screen >= 20) {
                html += '<span title="นักคัดกรองดีเด่น 20+ แต้ม" style="margin-left: 5px; cursor: help;">⭐</span>';
            }

            if (dpac >= 30) {
                html += '<span title="ผู้พิทักษ์พฤติกรรม DPAC 30+ แต้ม" style="margin-left: 5px; cursor: help;">❤️‍🔥</span>';
            } else if (dpac >= 10) {
                html += '<span title="นักติดตาม DPAC 10+ แต้ม" style="margin-left: 5px; cursor: help;">🌱</span>';
            }

            return html;
        }

        // Main client-side sorting and filtering engine
        function renderLeaderboard() {
            const tbody = document.getElementById('leaderboard-tbody');
            tbody.innerHTML = '';

            const searchTerm = document.getElementById('search-input').value.trim().toLowerCase();
            const tambonFilter = document.getElementById('filter-tambon').value;
            const hoscodeFilter = document.getElementById('filter-hoscode').value;
            const coachFilter = document.getElementById('filter-coach').value;
            const sortBy = document.getElementById('sort-by').value;

            // Filter logic
            const filtered = allVhvs.filter(vhv => {
                // Search query
                if (searchTerm) {
                    const nameMatch = vhv.vhv_name.toLowerCase().includes(searchTerm);
                    const idMatch = vhv.vhv_id.toLowerCase().includes(searchTerm);
                    const mooMatch = `หมู่ ${parseInt(vhv.vhv_moo)}`.includes(searchTerm);
                    if (!nameMatch && !idMatch && !mooMatch) return false;
                }

                // Tambon filter
                if (tambonFilter) {
                    const vhvTambon = vhv.vhid_code ? vhv.vhid_code.substring(0, 6) : '';
                    if (vhvTambon !== tambonFilter) return false;
                }

                // Hoscode filter
                if (hoscodeFilter && vhv.hoscode !== hoscodeFilter) {
                    return false;
                }

                // Coach filter
                if (coachFilter === 'coach' && !vhv.is_hl_coach) return false;
                if (coachFilter === 'member' && vhv.is_hl_coach) return false;

                return true;
            });

            // Sort logic
            filtered.sort((a, b) => {
                if (sortBy === 'total_points') {
                    return parseFloat(b.total_points) - parseFloat(a.total_points);
                } else if (sortBy === 'screening_points') {
                    return parseFloat(b.screening_points) - parseFloat(a.screening_points);
                } else if (sortBy === 'dpac_points') {
                    return parseFloat(b.dpac_points) - parseFloat(a.dpac_points);
                } else if (sortBy === 'vhv_name') {
                    return a.vhv_name.localeCompare(b.vhv_name, 'th');
                }
                return 0;
            });

            // Handle no data
            if (filtered.length === 0) {
                document.getElementById('no-data-msg').style.display = 'block';
                return;
            } else {
                document.getElementById('no-data-msg').style.display = 'none';
            }

            // Top Limit Display
            let displayList = filtered;
            const topLimit = gamificationConfig.display_settings ? parseInt(gamificationConfig.display_settings.top_board_limit) : 50;
            if (topLimit > 0 && !searchTerm && !tambonFilter && !hoscodeFilter && !coachFilter) {
                displayList = filtered.slice(0, topLimit);
            }

            document.getElementById('results-count').textContent = filtered.length;

            // Render Rows
            displayList.forEach((vhv, idx) => {
                const rankNum = idx + 1;
                let rankHtml = '';

                if (rankNum === 1) {
                    rankHtml = `<span class="rank-badge rank-gold" title="อันดับ 1">🥇</span>`;
                } else if (rankNum === 2) {
                    rankHtml = `<span class="rank-badge rank-silver" title="อันดับ 2">🥈</span>`;
                } else if (rankNum === 3) {
                    rankHtml = `<span class="rank-badge rank-bronze" title="อันดับ 3">🥉</span>`;
                } else {
                    rankHtml = `<span class="rank-badge rank-normal">${rankNum}</span>`;
                }

                const hosName = hcNames[vhv.hoscode] || vhv.hoscode || '-';
                const vhvTambonCode = vhv.vhid_code ? vhv.vhid_code.substring(0, 6) : '';
                const tambonName = (tambonNames[vhvTambonCode] || 'ไม่ระบุ').replace(/^ตำบล/, '');

                let badges = '';
                if (vhv.is_hl_coach) {
                    badges += `<span style="color: #fbbf24; font-weight: bold; background: rgba(251,191,36,0.1); padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-right: 4px; border: 1px solid rgba(251,191,36,0.2);">✨ HL-Coach</span>`;
                }

                const rankTitle = getRankTitle(rankNum);
                if (rankTitle) {
                    badges += `<span style="color: var(--color-accent); font-weight: bold; background: rgba(13, 44, 84, 0.05); padding: 2px 6px; border-radius: 4px; font-size: 11px; box-shadow: var(--neumorph-inset);">${escapeHtml(rankTitle)}</span>`;
                }

                // Hospital slogan badge
                let hosTitleHtml = '';
                if (gamificationConfig.display_settings && gamificationConfig.display_settings.show_hospital_titles !== false) {
                    const hosTitle = gamificationConfig.hospital_titles ? gamificationConfig.hospital_titles[vhv.hoscode] : '';
                    if (hosTitle) {
                        hosTitleHtml = `<div style="font-size: 11px; color: #0284c7; font-weight: bold; margin-top: 2px;">${escapeHtml(hosTitle)}</div>`;
                    }
                }

                const totalPtsFormatted = parseFloat(vhv.total_points).toFixed(2).replace(/\.00$/, '');
                const screeningPtsFormatted = parseFloat(vhv.screening_points).toFixed(2).replace(/\.00$/, '');
                const dpacPtsFormatted = parseFloat(vhv.dpac_points).toFixed(2).replace(/\.00$/, '');

                const row = document.createElement('tr');
                if (loggedInHoscode && vhv.hoscode === loggedInHoscode) {
                    row.style.backgroundColor = 'rgba(13, 44, 84, 0.02)';
                }

                row.innerHTML = `
                    <td style="text-align: center;">${rankHtml}</td>
                    <td style="font-weight: 800; color: var(--text-primary);">${escapeHtml(vhv.vhv_name)}${getAchievementsHtml(vhv)}</td>
                    <td style="text-align: center; font-weight: bold;">${parseInt(vhv.vhv_moo)}</td>
                    <td style="white-space: nowrap;">${escapeHtml(tambonName)}</td>
                    <td style="font-size: 13.5px; color: var(--text-secondary); white-space: nowrap;">
                        ${escapeHtml(hosName)}
                        ${hosTitleHtml}
                    </td>
                    <td style="text-align: right; font-weight: 600;">${screeningPtsFormatted}</td>
                    <td style="text-align: right; font-weight: 600;">${dpacPtsFormatted}</td>
                    <td style="text-align: right; font-weight: 800; color: var(--color-accent); font-size: 15px;">${totalPtsFormatted}</td>
                    <td style="text-align: center;">${badges || '-'}</td>
                    <td style="text-align: center;" class="no-print">
                        <button onclick="openLogsModal('${vhv.vhv_id}')" class="btn-view-logs-icon" title="ดูประวัติแต้ม">
                            🔍
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // HTML escaping helper
        function escapeHtml(str) {
            if (!str) return '';
            return str.toString().replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Gamification Settings Modal Operations
        function openGamificationModal() {
            renderGamificationSettingsUI();
            document.getElementById('gamification-modal').style.display = 'flex';
        }

        function closeGamificationModal() {
            document.getElementById('gamification-modal').style.display = 'none';
        }

        function switchGamificationTab(tabId, btn) {
            document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.settings-tab-pane').forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const targetPane = document.getElementById(tabId);
            if (targetPane) targetPane.classList.add('active');
        }

        function toggleScoringModeInputs() {
            const isCustom = document.getElementById('scoring-mode-custom').checked;
            document.getElementById('custom-round-points-box').style.display = isCustom ? 'block' : 'none';
        }

        function renderGamificationSettingsUI() {
            // Tab 1: Top 5 Titles
            const top5Box = document.getElementById('top5-inputs-container');
            top5Box.innerHTML = '';
            for (let r = 1; r <= 5; r++) {
                const currentVal = (gamificationConfig.top5_titles && gamificationConfig.top5_titles[r]) || defaultGamificationConfig.top5_titles[r] || '';
                const defaultVal = defaultGamificationConfig.top5_titles[r] || '';
                
                const card = document.createElement('div');
                card.className = 'setting-field-card';
                card.innerHTML = `
                    <label style="font-size: 13.5px; font-weight: bold; display: block; margin-bottom: 4px;">
                        อันดับที่ ${r}:
                    </label>
                    <input type="text" id="top5-input-${r}" class="form-input-text" value="${escapeHtml(currentVal)}" style="height: 38px; margin-bottom: 2px;">
                    <span class="baseline-hint">ค่าเริ่มต้นเดิม: ${escapeHtml(defaultVal)}</span>
                `;
                top5Box.appendChild(card);
            }

            // Tab 1: Tier Titles
            const tierBox = document.getElementById('tier-inputs-container');
            tierBox.innerHTML = '';
            for (let g = 1; g <= 9; g++) {
                const startRank = (g - 1) * 5 + 6;
                const endRank = startRank + 4;
                const currentVal = (gamificationConfig.tier_titles && gamificationConfig.tier_titles[g]) || defaultGamificationConfig.tier_titles[g] || '';
                const defaultVal = defaultGamificationConfig.tier_titles[g] || '';

                const card = document.createElement('div');
                card.className = 'setting-field-card';
                card.innerHTML = `
                    <label style="font-size: 13.5px; font-weight: bold; display: block; margin-bottom: 4px;">
                        กลุ่มอันดับ ${startRank} - ${endRank}:
                    </label>
                    <input type="text" id="tier-input-${g}" class="form-input-text" value="${escapeHtml(currentVal)}" style="height: 38px; margin-bottom: 2px;">
                    <span class="baseline-hint">ค่าเริ่มต้นเดิม: ${escapeHtml(defaultVal)}</span>
                `;
                tierBox.appendChild(card);
            }

            // Tab 2: Hospital Titles
            const hosBox = document.getElementById('hospital-inputs-container');
            hosBox.innerHTML = '';
            for (const [code, name] of Object.entries(hcNames)) {
                const currentVal = (gamificationConfig.hospital_titles && gamificationConfig.hospital_titles[code]) || defaultGamificationConfig.hospital_titles[code] || '';
                const defaultVal = defaultGamificationConfig.hospital_titles[code] || '';

                const card = document.createElement('div');
                card.className = 'setting-field-card';
                card.innerHTML = `
                    <label style="font-size: 13.5px; font-weight: bold; display: block; margin-bottom: 4px;">
                        ${escapeHtml(name)} <span style="font-family: monospace; font-size: 12px; color: var(--text-secondary);">(${escapeHtml(code)})</span>:
                    </label>
                    <input type="text" id="hospital-input-${code}" class="form-input-text" value="${escapeHtml(currentVal)}" placeholder="ระบุฉายา/สโลแกนประจำ รพ.สต..." style="height: 38px; margin-bottom: 2px;">
                    <span class="baseline-hint">ค่าเริ่มต้นเดิม: ${escapeHtml(defaultVal || '-')}</span>
                `;
                hosBox.appendChild(card);
            }

            // Tab 3: Scoring Rules
            const scoring = gamificationConfig.scoring_rules || defaultGamificationConfig.scoring_rules;
            if (scoring.mode === 'custom') {
                document.getElementById('scoring-mode-custom').checked = true;
            } else {
                document.getElementById('scoring-mode-prog').checked = true;
            }
            toggleScoringModeInputs();

            const rPts = scoring.round_points || defaultGamificationConfig.scoring_rules.round_points;
            document.getElementById('round-pts-1').value = rPts[1] || 1;
            document.getElementById('round-pts-2').value = rPts[2] || 2;
            document.getElementById('round-pts-3').value = rPts[3] || 3;
            document.getElementById('round-pts-4').value = rPts[4] || 4;
            document.getElementById('round-pts-5').value = rPts[5] || 5;

            document.getElementById('dpac-pts-input').value = scoring.dpac_points || 1.00;
            document.getElementById('sync-past-points-checkbox').checked = false;

            // Tab 4: Display Settings
            const display = gamificationConfig.display_settings || defaultGamificationConfig.display_settings;
            document.getElementById('display-vhv-titles-toggle').checked = display.show_vhv_titles !== false;
            document.getElementById('display-hospital-titles-toggle').checked = display.show_hospital_titles !== false;
            document.getElementById('display-top-limit-select').value = display.top_board_limit !== undefined ? display.top_board_limit : 50;
        }

        function collectGamificationConfigFromUI() {
            const config = JSON.parse(JSON.stringify(gamificationConfig));

            // Tab 1: Top 5
            config.top5_titles = {};
            for (let r = 1; r <= 5; r++) {
                const el = document.getElementById(`top5-input-${r}`);
                if (el) config.top5_titles[r] = el.value.trim();
            }

            // Tab 1: Tiers
            config.tier_titles = {};
            for (let g = 1; g <= 9; g++) {
                const el = document.getElementById(`tier-input-${g}`);
                if (el) config.tier_titles[g] = el.value.trim();
            }

            // Tab 2: Hospitals
            config.hospital_titles = {};
            for (const code of Object.keys(hcNames)) {
                const el = document.getElementById(`hospital-input-${code}`);
                if (el) config.hospital_titles[code] = el.value.trim();
            }

            // Tab 3: Scoring
            const isCustom = document.getElementById('scoring-mode-custom').checked;
            config.scoring_rules = {
                mode: isCustom ? 'custom' : 'progressive',
                round_points: {
                    1: parseFloat(document.getElementById('round-pts-1').value) || 1,
                    2: parseFloat(document.getElementById('round-pts-2').value) || 2,
                    3: parseFloat(document.getElementById('round-pts-3').value) || 3,
                    4: parseFloat(document.getElementById('round-pts-4').value) || 4,
                    5: parseFloat(document.getElementById('round-pts-5').value) || 5
                },
                dpac_points: parseFloat(document.getElementById('dpac-pts-input').value) || 1
            };

            // Tab 4: Display
            config.display_settings = {
                show_vhv_titles: document.getElementById('display-vhv-titles-toggle').checked,
                show_hospital_titles: document.getElementById('display-hospital-titles-toggle').checked,
                top_board_limit: parseInt(document.getElementById('display-top-limit-select').value)
            };

            return config;
        }

        function saveGamificationSettings() {
            const configToSave = collectGamificationConfigFromUI();
            const syncPoints = document.getElementById('sync-past-points-checkbox').checked;

            fetch('../api/gamification_settings.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    config: configToSave,
                    sync_points: syncPoints
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    gamificationConfig = data.active_config;
                    renderLeaderboard();
                    closeGamificationModal();
                    alert('✅ ' + data.message);
                    if (syncPoints) {
                        window.location.reload();
                    }
                } else {
                    alert('⚠️ เกิดข้อผิดพลาด: ' + (data.message || 'ไม่สามารถบันทึกได้'));
                }
            })
            .catch(err => {
                alert('⚠️ เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            });
        }

        function resetGamificationSection(section) {
            if (!confirm('ยืนยันการคืนค่าเริ่มต้นของส่วนนี้หรือไม่?')) return;

            fetch('../api/gamification_settings.php?action=reset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ section: section })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    gamificationConfig = data.active_config;
                    renderGamificationSettingsUI();
                    renderLeaderboard();
                    alert('✅ คืนค่าเริ่มต้นของส่วนนี้เรียบร้อยแล้ว');
                } else {
                    alert('⚠️ ' + data.message);
                }
            })
            .catch(err => {
                alert('⚠️ เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            });
        }

        function resetAllGamificationSettings() {
            if (!confirm('⚠️ คำเตือน: คุณต้องการคืนค่าเริ่มต้นทั้งหมดของระบบ (ฉายา, สโลแกน รพ.สต., กฎแต้ม และการแสดงผล) ใช่หรือไม่?')) {
                return;
            }

            fetch('../api/gamification_settings.php?action=reset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ section: 'all' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    gamificationConfig = data.active_config;
                    renderGamificationSettingsUI();
                    renderLeaderboard();
                    alert('✅ คืนค่าเริ่มต้นทั้งหมดของระบบเรียบร้อยแล้ว');
                } else {
                    alert('⚠️ ' + data.message);
                }
            })
            .catch(err => {
                alert('⚠️ เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            });
        }

        // Modal Operations
        function openLogsModal(vhvId) {
            const modal = document.getElementById('logs-modal');
            const tbody = document.getElementById('modal-logs-tbody');

            // Clean modal state
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">⌛ กำลังโหลดประวัติ...</td></tr>';
            document.getElementById('modal-vhv-name').textContent = 'กำลังดึงข้อมูล...';
            document.getElementById('modal-vhv-info').textContent = '';
            document.getElementById('modal-stat-screening').textContent = '0.00';
            document.getElementById('modal-stat-dpac').textContent = '0.00';

            modal.style.display = 'flex';

            // Fetch AJAX data
            fetch(`../api/get_vhv_rewards.php?vhv_id=${encodeURIComponent(vhvId)}`)
                .then(response => {
                    return response.text().then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (!response.ok) {
                                throw new Error(data.message || `เซิร์ฟเวอร์ส่งคืนรหัส ${response.status}`);
                            }
                            return data;
                        } catch (e) {
                            if (!response.ok) {
                                try {
                                    const data = JSON.parse(text.substring(text.indexOf('{')));
                                    throw new Error(data.message || `เซิร์ฟเวอร์ขัดข้อง (HTTP ${response.status})`);
                                } catch (inner) {
                                    throw new Error(`เซิร์ฟเวอร์ขัดข้อง (HTTP ${response.status})`);
                                }
                            }
                            const jsonStart = text.indexOf('{');
                            if (jsonStart !== -1) {
                                try {
                                    return JSON.parse(text.substring(jsonStart));
                                } catch (innerE) { }
                            }
                            throw new Error('รูปแบบข้อมูลจากเซิร์ฟเวอร์ไม่ถูกต้อง');
                        }
                    });
                })
                .then(data => {
                    if (data.status === 'success') {
                        // Set headers
                        document.getElementById('modal-vhv-name').textContent = `🏆 ประวัติคะแนน: ${data.vhv.vhv_name}`;
                        document.getElementById('modal-vhv-info').textContent = `หมู่ที่ ${data.vhv.vhv_moo} | ${data.vhv.hospital_name} | รหัส อสม. ${vhvId}`;

                        let screeningTotal = 0;
                        let dpacTotal = 0;

                        tbody.innerHTML = '';
                        if (data.rewards.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-secondary);">ไม่พบประวัติคะแนนที่ได้รับอนุมัติ</td></tr>';
                        } else {
                            data.rewards.forEach(log => {
                                const pts = parseFloat(log.points_earned);
                                if (log.activity_type === 'screening') {
                                    screeningTotal += pts;
                                } else {
                                    dpacTotal += pts;
                                }

                                const dateStr = new Date(log.created_at).toLocaleDateString('th-TH', {
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });

                                const activityLabel = log.activity_type === 'screening'
                                    ? '<span style="color: var(--color-green); font-weight: bold;">🏥 คัดกรอง DM/HT</span>'
                                    : '<span style="color: var(--color-accent); font-weight: bold;">❤️ ติดตาม DPAC</span>';

                                const fullName = log.first_name 
                                    ? `${escapeHtml(log.first_name)} ${escapeHtml(log.last_name)}` 
                                    : '<span style="color: var(--text-muted); font-style: italic;">แต้มสะสมได้รับการคุ้มครอง (เปลี่ยนผู้รับมอบหมาย/ข้อมูลประวัติเดิมถูกลบ)</span>';
                                const cidStr = log.cid ? escapeHtml(log.cid) : '-';

                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>${dateStr}</td>
                                    <td>${activityLabel}</td>
                                    <td style="font-weight: bold; color: var(--text-primary);">${fullName}</td>
                                    <td style="font-family: monospace;">${cidStr}</td>
                                    <td style="text-align: right; font-weight: bold; color: var(--color-accent);">${pts.toFixed(2).replace(/\.00$/, '')}</td>
                                `;
                                tbody.appendChild(row);
                            });
                        }

                        document.getElementById('modal-stat-screening').textContent = screeningTotal.toFixed(2).replace(/\.00$/, '');
                        document.getElementById('modal-stat-dpac').textContent = dpacTotal.toFixed(2).replace(/\.00$/, '');
                    } else {
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--color-red);">⚠️ ข้อผิดพลาด: ${escapeHtml(data.message)}</td></tr>`;
                    }
                })
                .catch(err => {
                    tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--color-red);">⚠️ ${escapeHtml(err.message)}</td></tr>`;
                });
        }

        function closeLogsModal() {
            document.getElementById('logs-modal').style.display = 'none';
        }

        // Close modal when clicking outside contents
        window.onclick = function (event) {
            const logsModal = document.getElementById('logs-modal');
            const gamificationModal = document.getElementById('gamification-modal');
            if (event.target === logsModal) {
                closeLogsModal();
            } else if (event.target === gamificationModal) {
                closeGamificationModal();
            }
        };

        // Initialize display
        updateHospitalFilterOptions();
        renderLeaderboard();
    </script>
</body>

</html>