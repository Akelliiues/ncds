<?php
// admin/citizen_health_dashboard.php - แดชบอร์ดภาพรวมสุขภาพและพฤติกรรมประชาชนระดับอำเภอ (100% Anonymous & Privacy-First)
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/demo_data.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_title = function_exists('get_admin_title') ? get_admin_title() : 'ผู้ดูแลระบบ';
$is_super_admin = !empty($_SESSION['is_super_admin']);

$activeBudgetYear = isset($_GET['budget_year']) && is_numeric($_GET['budget_year'])
    ? (int)$_GET['budget_year']
    : (isset($_SESSION['active_budget_year']) ? (int)$_SESSION['active_budget_year'] : (function_exists('get_current_budget_year') ? get_current_budget_year() : 2026));

$selected_subdistrict = $_GET['sub_district_code'] ?? '';
$date_filter = $_GET['date_filter'] ?? 'all'; // all, 30d, 90d, ytd

$subdistricts = [
    '341801' => 'ตำบลตาลสุม',
    '341802' => 'ตำบลสำโรง',
    '341803' => 'ตำบลจิกเทิง',
    '341804' => 'ตำบลหนองกุง',
    '341805' => 'ตำบลนาคาย',
    '341806' => 'ตำบลคำหว้า'
];

try {
    $stmt = $pdo->query("SELECT sub_district_code, CONCAT('ตำบล', sub_district_name) FROM sub_districts ORDER BY sub_district_code ASC");
    $fetched = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($fetched)) {
        $subdistricts = $fetched;
    }
} catch (\Exception $e) {}

// Build SQL where clause
$whereClauses = ["budget_year = ?"];
$params = [$activeBudgetYear];

if (!empty($selected_subdistrict)) {
    $whereClauses[] = "sub_district_code = ?";
    $params[] = $selected_subdistrict;
}

if ($date_filter === '30d') {
    $whereClauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($date_filter === '90d') {
    $whereClauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
} elseif ($date_filter === 'ytd') {
    $whereClauses[] = "YEAR(created_at) = YEAR(CURDATE())";
}

$whereSql = implode(" AND ", $whereClauses);

// 1. Overall Aggregates & KPIs
$kpi = [
    'total' => 0,
    'green' => 0,
    'yellow' => 0,
    'red' => 0,
    'avg_score' => 0,
    'male_count' => 0,
    'female_count' => 0
];

try {
    $kpiStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN risk_level = 'green' THEN 1 ELSE 0 END) AS green,
            SUM(CASE WHEN risk_level = 'yellow' THEN 1 ELSE 0 END) AS yellow,
            SUM(CASE WHEN risk_level = 'red' THEN 1 ELSE 0 END) AS red,
            ROUND(AVG(risk_points), 1) AS avg_score,
            SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) AS male_count,
            SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) AS female_count
        FROM citizen_self_screenings
        WHERE $whereSql
    ");
    $kpiStmt->execute($params);
    $kpiRow = $kpiStmt->fetch(PDO::FETCH_ASSOC);
    if ($kpiRow && $kpiRow['total'] > 0) {
        $kpi = [
            'total' => (int)$kpiRow['total'],
            'green' => (int)$kpiRow['green'],
            'yellow' => (int)$kpiRow['yellow'],
            'red' => (int)$kpiRow['red'],
            'avg_score' => (float)$kpiRow['avg_score'],
            'male_count' => (int)$kpiRow['male_count'],
            'female_count' => (int)$kpiRow['female_count']
        ];
    }
} catch (\Exception $e) {}

// Fallback seed calculation if empty and in local/demo environment
if ($kpi['total'] === 0) {
    $kpi = [
        'total' => 0,
        'green' => 0,
        'yellow' => 0,
        'red' => 0,
        'avg_score' => 0,
        'male_count' => 0,
        'female_count' => 0
    ];
}

// 2. Behavioral Factors Breakdown (3อ. 2ส. 1น.)
$habits = [
    'sweet' => ['low' => 0, 'med' => 0, 'high' => 0],
    'salt' => ['low' => 0, 'med' => 0, 'high' => 0],
    'veggie' => ['good' => 0, 'poor' => 0],
    'exercise' => ['regular' => 0, 'some' => 0, 'sedentary' => 0],
    'sleep' => ['good' => 0, 'poor' => 0],
    'substance' => ['none' => 0, 'some' => 0, 'regular' => 0],
    'shape' => ['thin' => 0, 'slim' => 0, 'chubby' => 0, 'obese' => 0],
    'family' => ['no' => 0, 'yes' => 0],
    'age' => ['young' => 0, 'middle' => 0, 'senior' => 0]
];

try {
    $hStmt = $pdo->prepare("
        SELECT 
            sweet_habit, COUNT(*) as c FROM citizen_self_screenings WHERE $whereSql GROUP BY sweet_habit
    ");
    $hStmt->execute($params);
    while ($r = $hStmt->fetch(PDO::FETCH_ASSOC)) { if (isset($habits['sweet'][$r['sweet_habit']])) $habits['sweet'][$r['sweet_habit']] = (int)$r['c']; }

    $hStmt = $pdo->prepare("SELECT salt_habit, COUNT(*) as c FROM citizen_self_screenings WHERE $whereSql GROUP BY salt_habit");
    $hStmt->execute($params);
    while ($r = $hStmt->fetch(PDO::FETCH_ASSOC)) { if (isset($habits['salt'][$r['salt_habit']])) $habits['salt'][$r['salt_habit']] = (int)$r['c']; }

    $hStmt = $pdo->prepare("SELECT veggie_habit, COUNT(*) as c FROM citizen_self_screenings WHERE $whereSql GROUP BY veggie_habit");
    $hStmt->execute($params);
    while ($r = $hStmt->fetch(PDO::FETCH_ASSOC)) { if (isset($habits['veggie'][$r['veggie_habit']])) $habits['veggie'][$r['veggie_habit']] = (int)$r['c']; }

    $hStmt = $pdo->prepare("SELECT exercise_habit, COUNT(*) as c FROM citizen_self_screenings WHERE $whereSql GROUP BY exercise_habit");
    $hStmt->execute($params);
    while ($r = $hStmt->fetch(PDO::FETCH_ASSOC)) { if (isset($habits['exercise'][$r['exercise_habit']])) $habits['exercise'][$r['exercise_habit']] = (int)$r['c']; }

    $hStmt = $pdo->prepare("SELECT sleep_habit, COUNT(*) as c FROM citizen_self_screenings WHERE $whereSql GROUP BY sleep_habit");
    $hStmt->execute($params);
    while ($r = $hStmt->fetch(PDO::FETCH_ASSOC)) { if (isset($habits['sleep'][$r['sleep_habit']])) $habits['sleep'][$r['sleep_habit']] = (int)$r['c']; }

    $hStmt = $pdo->prepare("SELECT substance_habit, COUNT(*) as c FROM citizen_self_screenings WHERE $whereSql GROUP BY substance_habit");
    $hStmt->execute($params);
    while ($r = $hStmt->fetch(PDO::FETCH_ASSOC)) { if (isset($habits['substance'][$r['substance_habit']])) $habits['substance'][$r['substance_habit']] = (int)$r['c']; }

    $hStmt = $pdo->prepare("SELECT body_shape, COUNT(*) as c FROM citizen_self_screenings WHERE $whereSql GROUP BY body_shape");
    $hStmt->execute($params);
    while ($r = $hStmt->fetch(PDO::FETCH_ASSOC)) { if (isset($habits['shape'][$r['body_shape']])) $habits['shape'][$r['body_shape']] = (int)$r['c']; }

    $hStmt = $pdo->prepare("SELECT family_history, COUNT(*) as c FROM citizen_self_screenings WHERE $whereSql GROUP BY family_history");
    $hStmt->execute($params);
    while ($r = $hStmt->fetch(PDO::FETCH_ASSOC)) { if (isset($habits['family'][$r['family_history']])) $habits['family'][$r['family_history']] = (int)$r['c']; }

    $hStmt = $pdo->prepare("SELECT age_group, COUNT(*) as c FROM citizen_self_screenings WHERE $whereSql GROUP BY age_group");
    $hStmt->execute($params);
    while ($r = $hStmt->fetch(PDO::FETCH_ASSOC)) { if (isset($habits['age'][$r['age_group']])) $habits['age'][$r['age_group']] = (int)$r['c']; }
} catch (\Exception $e) {}

// 3. Age Group Cross Tabulation with Risk Levels
$ageMatrix = [
    'young' => ['green' => 0, 'yellow' => 0, 'red' => 0, 'total' => 0],
    'middle' => ['green' => 0, 'yellow' => 0, 'red' => 0, 'total' => 0],
    'senior' => ['green' => 0, 'yellow' => 0, 'red' => 0, 'total' => 0]
];
try {
    $mStmt = $pdo->prepare("
        SELECT age_group, risk_level, COUNT(*) as cnt 
        FROM citizen_self_screenings 
        WHERE $whereSql 
        GROUP BY age_group, risk_level
    ");
    $mStmt->execute($params);
    while ($row = $mStmt->fetch(PDO::FETCH_ASSOC)) {
        $ag = $row['age_group'];
        $rl = $row['risk_level'];
        $cnt = (int)$row['cnt'];
        if (isset($ageMatrix[$ag][$rl])) {
            $ageMatrix[$ag][$rl] = $cnt;
            $ageMatrix[$ag]['total'] += $cnt;
        }
    }
} catch (\Exception $e) {}

// 4. Fetch Recent 50 Anonymous Logs
$recentLogs = [];
try {
    $logStmt = $pdo->prepare("
        SELECT id, gender, age_group, body_shape, sweet_habit, salt_habit, veggie_habit, 
               exercise_habit, sleep_habit, substance_habit, family_history, risk_points, 
               risk_level, sub_district_code, created_at
        FROM citizen_self_screenings
        WHERE $whereSql
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $logStmt->execute($params);
    $recentLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $recentLogs = [];
}

// Percent helpers
$tot = max(1, $kpi['total']);
$pctGreen = round(($kpi['green'] / $tot) * 100, 1);
$pctYellow = round(($kpi['yellow'] / $tot) * 100, 1);
$pctRed = round(($kpi['red'] / $tot) * 100, 1);

// Key district insights calculation
$highSaltPct = round((($habits['salt']['high'] ?? 0) / $tot) * 100, 1);
$highSweetPct = round((($habits['sweet']['high'] ?? 0) / $tot) * 100, 1);
$sedentaryPct = round((($habits['exercise']['sedentary'] ?? 0) / $tot) * 100, 1);
$poorSleepPct = round((($habits['sleep']['poor'] ?? 0) / $tot) * 100, 1);
$obesePct = round((($habits['shape']['obese'] ?? 0) / $tot) * 100, 1);
$poorVeggiePct = round((($habits['veggie']['poor'] ?? 0) / $tot) * 100, 1);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>สถิติประเมินสุขภาพตนเองของประชาชน - NCDs Portal อำเภอ<?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --card-radius: 20px;
        }

        .dash-container {
            max-width: 1380px;
            margin: 0 auto;
            padding: 20px 16px 80px 16px;
        }

        /* Top Header & Context Bar */
        .dash-header {
            background: var(--bg-card);
            border-radius: var(--card-radius);
            padding: 22px 24px;
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .dash-title-group h1 {
            font-size: 22px;
            font-weight: 900;
            color: var(--text-primary);
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dash-title-group p {
            margin: 0;
            font-size: 13.5px;
            color: var(--text-secondary);
        }

        .privacy-pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 12.5px;
            font-weight: 800;
        }
        [data-theme="dark"] .privacy-pill-badge {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border-color: rgba(52, 211, 153, 0.35);
        }

        /* Filters Controls */
        .dash-filter-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .dash-select {
            background: var(--bg-main);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            outline: none;
            transition: all 0.2s;
        }
        .dash-select:focus {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .btn-dash-action {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-dash-action:hover {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        /* KPI Metric Cards Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .kpi-card {
            background: var(--bg-card);
            border-radius: var(--card-radius);
            padding: 16px 18px;
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--card-accent, #3b82f6);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--neumorph-hover);
        }
        .kpi-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }
        .kpi-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.3;
        }
        .kpi-value {
            font-size: 38px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.5px;
            margin: 6px 0;
            color: var(--text-primary);
            text-align: right;
            display: flex;
            justify-content: flex-end;
            align-items: baseline;
        }
        .kpi-sub {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.35;
            margin-top: 4px;
        }

        /* 2-Column Analytics Grid */
        .dash-section-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .dash-card {
            background: var(--bg-card);
            border-radius: var(--card-radius);
            padding: 22px;
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color);
        }

        .col-4 { grid-column: span 4; }
        .col-6 { grid-column: span 6; }
        .col-8 { grid-column: span 8; }
        .col-12 { grid-column: span 12; }

        @media (max-width: 1024px) {
            .col-4, .col-6, .col-8 { grid-column: span 12; }
        }

        .dash-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .dash-card-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* 3อ. 2ส. 1น. Interactive Cards */
        .habit-progress-item {
            margin-bottom: 14px;
        }
        .habit-progress-item:last-child {
            margin-bottom: 0;
        }
        .habit-label-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
        .habit-bar-track {
            height: 10px;
            background: var(--bg-darker);
            border-radius: 9999px;
            overflow: hidden;
            display: flex;
        }
        .habit-bar-segment {
            height: 100%;
            transition: width 0.4s ease;
        }

        /* Policy Recommendation Banner */
        .policy-insight-box {
            background: linear-gradient(135deg, rgba(13, 44, 84, 0.05), rgba(59, 130, 246, 0.08));
            border-left: 6px solid #2563eb;
            border-radius: 18px;
            padding: 18px 20px;
            margin-bottom: 24px;
        }
        [data-theme="dark"] .policy-insight-box {
            background: rgba(30, 41, 59, 0.5);
            border-left-color: #38bdf8;
        }

        /* Log Table */
        .table-responsive {
            overflow-x: auto;
            margin-top: 10px;
        }
        .anon-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .anon-table th {
            background: var(--bg-darker);
            color: var(--text-secondary);
            font-weight: 800;
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .anon-table th:first-child { border-top-left-radius: 12px; }
        .anon-table th:last-child { border-top-right-radius: 12px; }
        .anon-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
        }
        .anon-table tr:hover td {
            background: rgba(59, 130, 246, 0.04);
        }

        .risk-badge-sm {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 800;
        }
        .risk-badge-green { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .risk-badge-yellow { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .risk-badge-red { background: rgba(239, 68, 68, 0.15); color: #dc2626; }

        @media print {
            .no-print, .dash-filter-bar, .admin-navbar, .back-to-top { display: none !important; }
            body { background: white !important; color: black !important; }
            .dash-container { max-width: 100% !important; padding: 0 !important; }
            .dash-card, .kpi-card { box-shadow: none !important; border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body class="dashboard-body">

    <?php require_once __DIR__ . '/navbar.php'; ?>

    <div class="dash-container">
        
        <!-- Header & Filter Bar -->
        <div class="dash-header">
            <div class="dash-title-group">
                <h1 style="display: flex; align-items: center; gap: 12px;">
                    <?= render_neu_icon('mobile-health', 'lg', 'text-navy') ?>
                    <span>สถิติประเมินสุขภาพตนเองของประชาชน (ระดับอำเภอ)</span>
                </h1>
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 6px; flex-wrap: wrap;">
                    <span class="privacy-pill-badge">
                        <?= render_neu_icon('shield-check', 'xs', 'text-green') ?> ปลอดภัย ไม่เก็บข้อมูลส่วนบุคคล
                    </span>
                    <p>
                        ศูนย์ข้อมูลสุขภาพและพฤติกรรมเสี่ยง 3อ. 2ส. 1น. อำเภอ<?= DISTRICT_NAME ?> จังหวัดอุบลราชธานี
                    </p>
                </div>
            </div>

            <!-- Filter Controls -->
            <form method="GET" action="citizen_health_dashboard.php" class="dash-filter-bar no-print">
                <select name="budget_year" class="dash-select" onchange="this.form.submit()">
                    <option value="2026" <?= $activeBudgetYear == 2026 ? 'selected' : '' ?>>ปีงบประมาณ 2569 (2026)</option>
                    <option value="2025" <?= $activeBudgetYear == 2025 ? 'selected' : '' ?>>ปีงบประมาณ 2568 (2025)</option>
                </select>

                <select name="sub_district_code" class="dash-select" onchange="this.form.submit()">
                    <option value="">ทุกตำบล (ภาพรวมอำเภอ)</option>
                    <?php foreach ($subdistricts as $sCode => $sName): ?>
                        <option value="<?= htmlspecialchars($sCode) ?>" <?= $selected_subdistrict == $sCode ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="date_filter" class="dash-select" onchange="this.form.submit()">
                    <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>ทุกช่วงเวลา</option>
                    <option value="30d" <?= $date_filter === '30d' ? 'selected' : '' ?>>30 วันล่าสุด</option>
                    <option value="90d" <?= $date_filter === '90d' ? 'selected' : '' ?>>90 วันล่าสุด</option>
                    <option value="ytd" <?= $date_filter === 'ytd' ? 'selected' : '' ?>>ปีนี้ (YTD)</option>
                </select>

                <button type="button" class="btn-dash-action" onclick="window.print()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg> พิมพ์รายงาน
                </button>
                <button type="button" class="btn-dash-action" onclick="exportCitizenLogsToCSV()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> ส่งออก CSV
                </button>
            </form>
        </div>

        <!-- 1. KPI Metric Cards -->
        <div class="kpi-grid">
            <!-- Total Screened -->
            <div class="kpi-card" style="--card-accent: #3b82f6;">
                <div class="kpi-header">
                    <?= render_neu_icon('users-group', 'sm', 'disc-blue') ?>
                    <span class="kpi-title">ผู้ประเมินสุขภาพสะสม</span>
                </div>
                <div class="kpi-value" style="color: #3b82f6;">
                    <?= number_format($kpi['total']) ?> <span style="font-size: 14px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">  คน</span>
                </div>
                <div class="kpi-sub">
                    ชาย <?= number_format($kpi['male_count']) ?> คน | หญิง <?= number_format($kpi['female_count']) ?> คน
                </div>
            </div>

            <!-- Green: Low Risk -->
            <div class="kpi-card" style="--card-accent: #10b981;">
                <div class="kpi-header">
                    <?= render_neu_icon('heart-pulse', 'sm', 'disc-green') ?>
                    <span class="kpi-title">กลุ่มสุขภาพดีมาก (เสี่ยงต่ำ)</span>
                </div>
                <div class="kpi-value" style="color: #10b981;">
                    <?= number_format($kpi['green']) ?> <span style="font-size: 14px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">  คน</span>
                </div>
                <div class="kpi-sub">
                    คิดเป็น <strong><?= $pctGreen ?>%</strong> ของผู้ประเมิน
                </div>
            </div>

            <!-- Yellow: Moderate Risk -->
            <div class="kpi-card" style="--card-accent: #f59e0b;">
                <div class="kpi-header">
                    <?= render_neu_icon('thermometer', 'sm', 'disc-yellow') ?>
                    <span class="kpi-title">เริ่มมีสัญญาณเสี่ยง</span>
                </div>
                <div class="kpi-value" style="color: #f59e0b;">
                    <?= number_format($kpi['yellow']) ?> <span style="font-size: 14px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">  คน</span>
                </div>
                <div class="kpi-sub">
                    คิดเป็น <strong><?= $pctYellow ?>%</strong> (ควรปรับพฤติกรรม)
                </div>
            </div>

            <!-- Red: High Risk -->
            <div class="kpi-card" style="--card-accent: #ef4444;">
                <div class="kpi-header">
                    <?= render_neu_icon('warning-alert', 'sm', 'disc-red') ?>
                    <span class="kpi-title">กลุ่มความเสี่ยงสูง</span>
                </div>
                <div class="kpi-value" style="color: #ef4444;">
                    <?= number_format($kpi['red']) ?> <span style="font-size: 14px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">  คน</span>
                </div>
                <div class="kpi-sub">
                    คิดเป็น <strong><?= $pctRed ?>%</strong> (ควรตรวจคัดกรองจริง)
                </div>
            </div>

            <!-- Avg Risk Score -->
            <div class="kpi-card" style="--card-accent: #8b5cf6;">
                <div class="kpi-header">
                    <?= render_neu_icon('weight-scale', 'sm', 'disc-purple') ?>
                    <span class="kpi-title">คะแนนเสี่ยงเฉลี่ยระดับอำเภอ</span>
                </div>
                <div class="kpi-value" style="color: #8b5cf6;">
                    <?= number_format($kpi['avg_score'], 1) ?> <span style="font-size: 14px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">  คะแนน</span>
                </div>
                <div class="kpi-sub">
                    คะแนนเต็ม 25 (ค่ายิ่งต่ำยิ่งสุขภาพดี)
                </div>
            </div>
        </div>

        <!-- Strategic Policy Recommendation Banner -->
        <div class="policy-insight-box">
            <div style="display: flex; align-items: flex-start; gap: 16px;">
                <?= render_neu_icon('first-aid', 'lg', 'text-blue') ?>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 800; color: #1e40af;">
                        ข้อค้นพบทางระบาดวิทยา & ข้อเสนอแนะเชิงนโยบายสาธารณสุขอำเภอ<?= DISTRICT_NAME ?>
                    </h3>
                    <p style="margin: 0; font-size: 13.5px; color: var(--text-primary); line-height: 1.55;">
                        <?php if ($kpi['total'] > 0): ?>
                            จากสถิติภาพรวมของประชาชนที่ร่วมประเมินสุขภาพตนเอง พบว่า 
                            <?php 
                                $findings = [];
                                if ($highSaltPct >= 25) $findings[] = "มีการบริโภคเค็ม/โซเดียมสูงถึง <strong>{$highSaltPct}%</strong>";
                                if ($highSweetPct >= 25) $findings[] = "ติดหวาน/น้ำหวานเป็นประจำ <strong>{$highSweetPct}%</strong>";
                                if ($sedentaryPct >= 30) $findings[] = "มีพฤติกรรมเนือยนิ่ง/ขาดการออกกำลังกาย <strong>{$sedentaryPct}%</strong>";
                                if ($obesePct >= 20) $findings[] = "มีภาวะอ้วนลงพุงชัดเจน <strong>{$obesePct}%</strong>";
                                echo implode(", ", $findings);
                            ?>
                            <br>
                            👉 <strong>มาตรการขับเคลื่อนที่แนะนำ:</strong> มอบหมาย อสม. และทีม รพ.สต. เร่งรณรงค์โครงการ <em>"เค็มน้อย อร่อยได้ & ชวนขยับกายวันละ 30 นาที"</em> พร้อมเชื่อมโยงผู้ที่มีความเสี่ยงสูงเข้าสู่คลินิก DPAC เพื่อรับการตรวจยืนยันค่าน้ำตาลและความดันโลหิตต่อไป
                        <?php else: ?>
                            ยังไม่มีข้อมูลสถิติผลการประเมินในเงื่อนไขตัวกรองนี้ ประชาชนสามารถเริ่มประเมินตนเองได้ฟรีที่หน้า <a href="../self_screening.php" target="_blank" style="color: #2563eb; font-weight: 800;">แบบประเมินสุขภาพตนเอง</a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- 2. Charts Section (3อ. 2ส. 1น. Behavioral Breakdown) -->
        <div class="dash-section-grid">
            
            <!-- Chart 1: Risk Level Proportion Donut Chart -->
            <div class="dash-card col-4">
                <div class="dash-card-header">
                    <h3 class="dash-card-title">
                        <?= render_neu_icon('heart-pulse', 'sm', 'text-blue') ?>
                        <span>สัดส่วนระดับความเสี่ยงสุขภาพ</span>
                    </h3>
                    <span style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">N = <?= number_format($kpi['total']) ?></span>
                </div>
                <div style="position: relative; height: 260px;">
                    <canvas id="riskPieChart"></canvas>
                </div>
                <div style="display: flex; justify-content: space-around; margin-top: 14px; text-align: center;">
                    <div>
                        <div style="color: #10b981; font-weight: 800; font-size: 16px;"><?= $pctGreen ?>%</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">สุขภาพดี (เขียว)</div>
                    </div>
                    <div>
                        <div style="color: #f59e0b; font-weight: 800; font-size: 16px;"><?= $pctYellow ?>%</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">เริ่มเสี่ยง (เหลือง)</div>
                    </div>
                    <div>
                        <div style="color: #ef4444; font-weight: 800; font-size: 16px;"><?= $pctRed ?>%</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">เสี่ยงสูง (แดง)</div>
                    </div>
                </div>
            </div>

            <!-- Chart 2: 3อ. 2ส. 1น. Risk Radar / Bar Matrix -->
            <div class="dash-card col-8">
                <div class="dash-card-header">
                    <h3 class="dash-card-title">
                        <?= render_neu_icon('nutrition', 'sm', 'text-green') ?>
                        <span>พฤติกรรมสุขภาพรอบด้าน (3อ. 2ส. 1น. Prevalence Matrix)</span>
                    </h3>
                    <span style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">% สัดส่วนพฤติกรรมเสี่ยง</span>
                </div>
                <div style="position: relative; height: 280px;">
                    <canvas id="habitsBarChart"></canvas>
                </div>
            </div>

            <!-- Chart 3: Age Group vs Risk Profile -->
            <div class="dash-card col-6">
                <div class="dash-card-header">
                    <h3 class="dash-card-title">
                        <?= render_neu_icon('users-group', 'sm', 'text-purple') ?>
                        <span>การกระจายความเสี่ยงจำแนกตามช่วงวัย</span>
                    </h3>
                </div>
                <div style="position: relative; height: 260px;">
                    <canvas id="ageRiskChart"></canvas>
                </div>
            </div>

            <!-- Chart 4: Nutrition & Lifestyle Detail Progress Bars -->
            <div class="dash-card col-6">
                <div class="dash-card-header">
                    <h3 class="dash-card-title">
                        <?= render_neu_icon('capsules', 'sm', 'text-yellow') ?>
                        <span>สรุปสัดส่วนพฤติกรรมโภชนาการและการใช้ชีวิต</span>
                    </h3>
                </div>
                <div>
                    <!-- Sweet Habit -->
                    <div class="habit-progress-item">
                        <div class="habit-label-row">
                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                <?= render_neu_icon('sugar-sweet', 'xs', 'text-yellow') ?>
                                พฤติกรรมบริโภคหวาน (ชาหวาน/น้ำอัดลม)
                            </span>
                            <span style="color: var(--text-secondary); font-size: 12px;">
                                ติดหวาน <?= round((($habits['sweet']['high']??0)/$tot)*100, 1) ?>% | ปานกลาง <?= round((($habits['sweet']['med']??0)/$tot)*100, 1) ?>% | น้ำเปล่า <?= round((($habits['sweet']['low']??0)/$tot)*100, 1) ?>%
                            </span>
                        </div>
                        <div class="habit-bar-track">
                            <div class="habit-bar-segment" style="width: <?= (($habits['sweet']['low']??0)/$tot)*100 ?>%; background: #10b981;" title="ดื่มน้ำเปล่าเป็นหลัก"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['sweet']['med']??0)/$tot)*100 ?>%; background: #f59e0b;" title="ดื่มบ้างบางวัน"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['sweet']['high']??0)/$tot)*100 ?>%; background: #ef4444;" title="ติดหวานเกือบทุกวัน"></div>
                        </div>
                    </div>

                    <!-- Salt Habit -->
                    <div class="habit-progress-item">
                        <div class="habit-label-row">
                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                <?= render_neu_icon('salt-sodium', 'xs', 'text-blue') ?>
                                พฤติกรรมบริโภคเค็ม/โซเดียม (น้ำปลา/ปลาร้า/ของทอด)
                            </span>
                            <span style="color: var(--text-secondary); font-size: 12px;">
                                เค็มจัด <?= round((($habits['salt']['high']??0)/$tot)*100, 1) ?>% | ปานกลาง <?= round((($habits['salt']['med']??0)/$tot)*100, 1) ?>% | จืด <?= round((($habits['salt']['low']??0)/$tot)*100, 1) ?>%
                            </span>
                        </div>
                        <div class="habit-bar-track">
                            <div class="habit-bar-segment" style="width: <?= (($habits['salt']['low']??0)/$tot)*100 ?>%; background: #10b981;" title="รสจืด"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['salt']['med']??0)/$tot)*100 ?>%; background: #f59e0b;" title="ปานกลาง"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['salt']['high']??0)/$tot)*100 ?>%; background: #ef4444;" title="เค็มจัด/ซดน้ำแกง"></div>
                        </div>
                    </div>

                    <!-- Exercise Habit -->
                    <div class="habit-progress-item">
                        <div class="habit-label-row">
                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                <?= render_neu_icon('exercise', 'xs', 'text-green') ?>
                                การออกกำลังกาย & ขยับร่างกาย
                            </span>
                            <span style="color: var(--text-secondary); font-size: 12px;">
                                ประจำ <?= round((($habits['exercise']['regular']??0)/$tot)*100, 1) ?>% | ขยับบ้าง <?= round((($habits['exercise']['some']??0)/$tot)*100, 1) ?>% | เนือยนิ่ง <?= round((($habits['exercise']['sedentary']??0)/$tot)*100, 1) ?>%
                            </span>
                        </div>
                        <div class="habit-bar-track">
                            <div class="habit-bar-segment" style="width: <?= (($habits['exercise']['regular']??0)/$tot)*100 ?>%; background: #10b981;" title="เป็นประจำ"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['exercise']['some']??0)/$tot)*100 ?>%; background: #f59e0b;" title="มีบ้าง"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['exercise']['sedentary']??0)/$tot)*100 ?>%; background: #ef4444;" title="แทบไม่ออก/นั่งนาน"></div>
                        </div>
                    </div>

                    <!-- Sleep Quality -->
                    <div class="habit-progress-item">
                        <div class="habit-label-row">
                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                <?= render_neu_icon('sleep', 'xs', 'text-purple') ?>
                                คุณภาพการนอนหลับและการพักผ่อน
                            </span>
                            <span style="color: var(--text-secondary); font-size: 12px;">
                                สนิทดี <?= round((($habits['sleep']['good']??0)/$tot)*100, 1) ?>% | หลับยาก/ไม่พอ <?= round((($habits['sleep']['poor']??0)/$tot)*100, 1) ?>%
                            </span>
                        </div>
                        <div class="habit-bar-track">
                            <div class="habit-bar-segment" style="width: <?= (($habits['sleep']['good']??0)/$tot)*100 ?>%; background: #10b981;" title="หลับสนิท"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['sleep']['poor']??0)/$tot)*100 ?>%; background: #ef4444;" title="หลับยาก/นอนไม่พอ"></div>
                        </div>
                    </div>

                    <!-- Substance Habit -->
                    <div class="habit-progress-item">
                        <div class="habit-label-row">
                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                <?= render_neu_icon('no-substance', 'xs', 'text-red') ?>
                                บุหรี่และสุรา (2ส.)
                            </span>
                            <span style="color: var(--text-secondary); font-size: 12px;">
                                ไม่แตะ <?= round((($habits['substance']['none']??0)/$tot)*100, 1) ?>% | งานเลี้ยง <?= round((($habits['substance']['some']??0)/$tot)*100, 1) ?>% | ประจำ <?= round((($habits['substance']['regular']??0)/$tot)*100, 1) ?>%
                            </span>
                        </div>
                        <div class="habit-bar-track">
                            <div class="habit-bar-segment" style="width: <?= (($habits['substance']['none']??0)/$tot)*100 ?>%; background: #10b981;" title="ไม่สูบ/ไม่ดื่ม"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['substance']['some']??0)/$tot)*100 ?>%; background: #f59e0b;" title="สังสรรค์"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['substance']['regular']??0)/$tot)*100 ?>%; background: #ef4444;" title="สูบ/ดื่มประจำ"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- 3. Citizen Self-Screening Logs Table -->
        <div class="dash-card col-12">
            <div class="dash-card-header">
                <div>
                    <h3 class="dash-card-title">
                        <?= render_neu_icon('clipboard-record', 'sm', 'text-navy') ?>
                        <span>บันทึกสถิติการประเมินสุขภาพตนเองล่าสุด (Citizen Self-Screening Logs)</span>
                    </h3>
                    <p style="margin: 3px 0 0 0; font-size: 12.5px; color: var(--text-secondary);">
                        แสดง 50 รายการล่าสุดจากการประเมินตนเองของประชาชน (ไม่เก็บข้อมูลส่วนบุคคล ปลอดภัย 100%)
                    </p>
                </div>
                <div class="no-print">
                    <input type="text" id="logSearchInput" onkeyup="filterLogTable()" placeholder="🔍 ค้นหากลุ่มเสี่ยง, วัย..." class="dash-select" style="width: 220px;">
                </div>
            </div>

            <div class="table-responsive">
                <table class="anon-table" id="anonLogsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>วัน-เวลาประเมิน</th>
                            <th>เพศ</th>
                            <th>ช่วงวัย</th>
                            <th>รูปร่าง & พุง</th>
                            <th>ของหวาน</th>
                            <th>ของเค็ม</th>
                            <th>การกินผัก</th>
                            <th>ออกกำลังกาย</th>
                            <th>การนอน</th>
                            <th>บุหรี่/สุรา</th>
                            <th>กรรมพันธุ์</th>
                            <th>คะแนน</th>
                            <th>ผลการประเมิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentLogs)): ?>
                            <?php foreach ($recentLogs as $idx => $log): ?>
                                <?php
                                    $rClass = $log['risk_level'] === 'green' ? 'risk-badge-green' : ($log['risk_level'] === 'yellow' ? 'risk-badge-yellow' : 'risk-badge-red');
                                    $rText = $log['risk_level'] === 'green' ? '🟢 สุขภาพดี' : ($log['risk_level'] === 'yellow' ? '🟡 เริ่มเสี่ยง' : '🔴 เสี่ยงสูง');
                                    $ageMap = ['young' => '< 35 ปี', 'middle' => '35-59 ปี', 'senior' => '60+ ปี'];
                                    $shapeMap = ['thin' => 'ผอม/น้อย', 'slim' => 'สมส่วน', 'chubby' => 'เริ่มมีพุง', 'obese' => 'อ้วนลงพุง'];
                                    $genderMap = ['male' => 'ชาย', 'female' => 'หญิง'];
                                ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td style="white-space: nowrap; font-size: 12px; color: var(--text-secondary);">
                                        <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td><?= $genderMap[$log['gender']] ?? '-' ?></td>
                                    <td><?= $ageMap[$log['age_group']] ?? $log['age_group'] ?></td>
                                    <td><?= $shapeMap[$log['body_shape']] ?? $log['body_shape'] ?></td>
                                    <td><?= $log['sweet_habit'] === 'high' ? '🔴 ติดหวาน' : ($log['sweet_habit'] === 'med' ? '🟡 ดื่มบ้าง' : '🟢 น้ำเปล่า') ?></td>
                                    <td><?= $log['salt_habit'] === 'high' ? '🔴 เค็มจัด' : ($log['salt_habit'] === 'med' ? '🟡 ปานกลาง' : '🟢 รสจืด') ?></td>
                                    <td><?= $log['veggie_habit'] === 'good' ? '🟢 กินทุกมื้อ' : '🔴 กินน้อย' ?></td>
                                    <td><?= $log['exercise_habit'] === 'regular' ? '🟢 ประจำ' : ($log['exercise_habit'] === 'some' ? '🟡 มีบ้าง' : '🔴 นั่งนาน') ?></td>
                                    <td><?= $log['sleep_habit'] === 'good' ? '🟢 หลับสนิท' : '🔴 หลับยาก' ?></td>
                                    <td><?= $log['substance_habit'] === 'none' ? '🟢 ไม่แตะ' : ($log['substance_habit'] === 'some' ? '🟡 สังสรรค์' : '🔴 ประจำ') ?></td>
                                    <td><?= $log['family_history'] === 'yes' ? '⚠️ มี' : 'ไม่มี' ?></td>
                                    <td style="font-weight: 800;"><?= (int)$log['risk_points'] ?></td>
                                    <td>
                                        <span class="risk-badge-sm <?= $rClass ?>">
                                            <?= $rText ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="14" style="text-align: center; padding: 30px; color: var(--text-secondary);">
                                    ยังไม่มีข้อมูลการประเมินในเงื่อนไขตัวกรองนี้
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        // Theme Colors
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDark ? '#cbd5e1' : '#475569';
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';

        // 1. Risk Proportion Pie Chart
        const ctxRisk = document.getElementById('riskPieChart').getContext('2d');
        new Chart(ctxRisk, {
            type: 'doughnut',
            data: {
                labels: ['สุขภาพดีมาก (เขียว)', 'เริ่มมีสัญญาณเสี่ยง (เหลือง)', 'ความเสี่ยงสูง (แดง)'],
                datasets: [{
                    data: [<?= $kpi['green'] ?>, <?= $kpi['yellow'] ?>, <?= $kpi['red'] ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 2,
                    borderColor: isDark ? '#1e293b' : '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                cutout: '70%'
            }
        });

        // 2. Habits Bar Chart (3อ. 2ส. 1น.)
        const ctxHabits = document.getElementById('habitsBarChart').getContext('2d');
        new Chart(ctxHabits, {
            type: 'bar',
            data: {
                labels: [
                    'ชอบเค็ม/โซเดียมสูง',
                    'ติดหวาน/น้ำหวาน',
                    'ไม่ค่อยกินผัก',
                    'เนือยนิ่ง/ไม่ออกกำลังกาย',
                    'นอนหลับไม่พอ/หลับยาก',
                    'ดื่มสุรา/สูบบุหรี่',
                    'อ้วนลงพุงชัดเจน',
                    'มีพันธุกรรมในครอบครัว'
                ],
                datasets: [{
                    label: 'สัดส่วนประชาชนที่มีพฤติกรรมเสี่ยง (%)',
                    data: [
                        <?= $highSaltPct ?>,
                        <?= $highSweetPct ?>,
                        <?= $poorVeggiePct ?>,
                        <?= $sedentaryPct ?>,
                        <?= $poorSleepPct ?>,
                        <?= round(((($habits['substance']['regular']??0) + ($habits['substance']['some']??0)) / $tot) * 100, 1) ?>,
                        <?= $obesePct ?>,
                        <?= round((($habits['family']['yes']??0) / $tot) * 100, 1) ?>
                    ],
                    backgroundColor: [
                        '#ef4444',
                        '#f59e0b',
                        '#eab308',
                        '#ec4899',
                        '#8b5cf6',
                        '#6366f1',
                        '#f97316',
                        '#64748b'
                    ],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { color: textColor, callback: v => v + '%' },
                        grid: { color: gridColor }
                    },
                    x: {
                        ticks: { color: textColor, font: { size: 11, weight: 'bold' } },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // 3. Age Group Risk Stacked Chart
        const ctxAge = document.getElementById('ageRiskChart').getContext('2d');
        new Chart(ctxAge, {
            type: 'bar',
            data: {
                labels: ['วัยหนุ่มสาว (< 35 ปี)', 'วัยทำงาน (35-59 ปี)', 'ผู้สูงอายุ (60+ ปี)'],
                datasets: [
                    {
                        label: 'สุขภาพดี (เขียว)',
                        data: [<?= $ageMatrix['young']['green'] ?>, <?= $ageMatrix['middle']['green'] ?>, <?= $ageMatrix['senior']['green'] ?>],
                        backgroundColor: '#10b981',
                        borderRadius: 6
                    },
                    {
                        label: 'เริ่มเสี่ยง (เหลือง)',
                        data: [<?= $ageMatrix['young']['yellow'] ?>, <?= $ageMatrix['middle']['yellow'] ?>, <?= $ageMatrix['senior']['yellow'] ?>],
                        backgroundColor: '#f59e0b',
                        borderRadius: 6
                    },
                    {
                        label: 'เสี่ยงสูง (แดง)',
                        data: [<?= $ageMatrix['young']['red'] ?>, <?= $ageMatrix['middle']['red'] ?>, <?= $ageMatrix['senior']['red'] ?>],
                        backgroundColor: '#ef4444',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true,
                        ticks: { color: textColor, font: { weight: 'bold' } },
                        grid: { display: false }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { color: textColor, precision: 0 },
                        grid: { color: gridColor }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: textColor, font: { size: 12, weight: 'bold' } }
                    }
                }
            }
        });

        // Live Table Search Filter
        function filterLogTable() {
            const input = document.getElementById('logSearchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('anonLogsTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const text = tr[i].textContent || tr[i].innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    tr[i].style.display = '';
                } else {
                    tr[i].style.display = 'none';
                }
            }
        }

        // Export to CSV Function
        function exportCitizenLogsToCSV() {
            const table = document.getElementById('anonLogsTable');
            let csv = '\uFEFF'; // UTF-8 BOM for Excel support
            for (let row of table.rows) {
                let cols = [];
                for (let cell of row.cells) {
                    let cellText = cell.innerText.replace(/"/g, '""').trim();
                    cols.push('"' + cellText + '"');
                }
                csv += cols.join(',') + '\r\n';
            }
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.setAttribute('download', 'citizen_health_screening_report_<?= date('Ymd_His') ?>.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
