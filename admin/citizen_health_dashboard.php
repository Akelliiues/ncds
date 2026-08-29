<?php
// admin/citizen_health_dashboard.php - แดชบอร์ดภาพรวมสุขภาพและพฤติกรรมประชาชนระดับอำเภอ (100% Anonymous & Privacy-First)
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cache.php';
require_once __DIR__ . '/../config/demo_data.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_title = function_exists('get_admin_title') ? get_admin_title() : 'ผู้ดูแลระบบ';
$is_super_admin = !empty($_SESSION['is_super_admin']);

$activeBudgetYear = isset($_GET['budget_year']) && is_numeric($_GET['budget_year'])
    ? (int)$_GET['budget_year']
    : (isset($_SESSION['active_budget_year']) ? (int)$_SESSION['active_budget_year'] : (function_exists('get_current_budget_year') ? get_current_budget_year() : 2026));

$date_filter = $_GET['date_filter'] ?? 'all'; // all, 30d, 90d, ytd

// Build SQL where clause
$whereClauses = ["budget_year = ?"];
$params = [$activeBudgetYear];

if ($date_filter === '30d') {
    $whereClauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($date_filter === '90d') {
    $whereClauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
} elseif ($date_filter === 'ytd') {
    $whereClauses[] = "YEAR(created_at) = YEAR(CURDATE())";
}

$whereSql = implode(" AND ", $whereClauses);

// 1. Overall Aggregates & KPIs (Cached)
$citizenCacheKey = "citizen_health_db_by{$activeBudgetYear}_df{$date_filter}";
$citizenData = NcdCache::remember($citizenCacheKey, 60, function() use ($pdo, $whereSql, $params) {
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

// 4. Fetch Anonymous Logs for Pagination & Analytics
$recentLogs = [];
try {
    $logStmt = $pdo->prepare("
        SELECT id, gender, age_group, body_shape, sweet_habit, salt_habit, veggie_habit, 
               exercise_habit, sleep_habit, substance_habit, family_history, risk_points, 
               risk_level, sub_district_code, created_at
        FROM citizen_self_screenings
        WHERE $whereSql
        ORDER BY created_at DESC
        LIMIT 1000
    ");
    $logStmt->execute($params);
    $recentLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $recentLogs = [];
}

    return compact('kpi', 'habits', 'ageMatrix', 'recentLogs');
});

if (is_array($citizenData)) {
    extract($citizenData);
}

// Percent helpers
$tot = max(1, $kpi['total']);
$pctGreen = round(($kpi['green'] / $tot) * 100, 1);
$pctYellow = round(($kpi['yellow'] / $tot) * 100, 1);
$pctRed = round(($kpi['red'] / $tot) * 100, 1);

$totGender = max(1, $kpi['male_count'] + $kpi['female_count']);
$pctMale = round(($kpi['male_count'] / $totGender) * 100, 1);
$pctFemale = round(($kpi['female_count'] / $totGender) * 100, 1);

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
            padding: 8px 16px 60px 16px;
        }

        /* Top Header & Context Bar */
        .dash-header {
            background: var(--bg-card);
            border-radius: var(--card-radius);
            padding: 14px 20px;
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .dash-title-group h1 {
            font-size: 19px;
            font-weight: 900;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.2;
        }

        .dash-title-group p {
            margin: 0;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .privacy-pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
            padding: 3px 9px;
            border-radius: 9999px;
            font-size: 11.5px;
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
            gap: 8px;
            flex-wrap: wrap;
        }

        .dash-select {
            background: var(--bg-main);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 12.5px;
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
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        .btn-dash-action:hover {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        /* ============================================== */
        /* 🍱 BENTO GRID DESIGN SYSTEM (PREMIUM DASHBOARD) */
        /* ============================================== */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }

        .bento-card {
            background: var(--bg-card);
            border-radius: 24px;
            padding: 22px 24px;
            box-shadow: 0 10px 30px rgba(13, 44, 84, 0.04), 0 1px 3px rgba(0, 0, 0, 0.02);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-sizing: border-box;
        }
        .bento-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 40px rgba(13, 44, 84, 0.08);
            border-color: rgba(59, 130, 246, 0.35);
        }
        [data-theme="dark"] .bento-card {
            background: #1e293b;
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
        }
        [data-theme="dark"] .bento-card:hover {
            border-color: rgba(56, 189, 248, 0.35);
            box-shadow: 0 18px 44px rgba(0, 0, 0, 0.5);
        }

        .bento-span-3 { grid-column: span 3; }
        .bento-span-4 { grid-column: span 4; }
        .bento-span-5 { grid-column: span 5; }
        .bento-span-6 { grid-column: span 6; }
        .bento-span-7 { grid-column: span 7; }
        .bento-span-8 { grid-column: span 8; }
        .bento-span-12 { grid-column: span 12; }

        @media (max-width: 1100px) {
            .bento-span-3, .bento-span-4, .bento-span-5, .bento-span-6, .bento-span-7, .bento-span-8 { grid-column: span 12; }
        }

        .bento-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .bento-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .bento-badge {
            font-size: 11.5px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 9999px;
            background: var(--bg-darker);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            white-space: nowrap;
        }

        /* 3อ. 2ส. 1น. Interactive Cards */
        .bento-habits-subgrid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
        @media (max-width: 768px) {
            .bento-habits-subgrid { grid-template-columns: 1fr; }
        }
        .bento-habit-tile {
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        [data-theme="dark"] .bento-habit-tile {
            background: rgba(15, 23, 42, 0.6);
            border-color: rgba(255, 255, 255, 0.08);
        }
        .habit-label-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
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

        /* Neumorphic Pagination Bar */
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }
        .pagination-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            font-weight: 700;
            font-size: 12.5px;
            padding: 6px 12px;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s ease;
            min-width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .pagination-btn:hover:not(:disabled) {
            background: var(--bg-darker);
            border-color: #2563eb;
            color: #2563eb;
            transform: translateY(-1px);
        }
        .pagination-btn.active {
            background: #2563eb;
            color: #ffffff !important;
            border-color: #2563eb;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.35);
        }
        .pagination-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .pagination-info {
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 700;
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
            .bento-card { box-shadow: none !important; border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body class="dashboard-body">

    <?php require_once __DIR__ . '/navbar.php'; ?>

    <div class="dash-container">
        
        <!-- Header & Filter Bar -->
        <div class="dash-header">
            <div class="dash-title-group" style="display: flex; align-items: center; gap: 14px;">
                <?= render_neu_icon('mobile-health', 'md', 'disc-blue') ?>
                <div>
                    <h1>
                        <span>สถิติประเมินสุขภาพตนเองของประชาชน (ระดับอำเภอ)</span>
                    </h1>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 3px; flex-wrap: wrap;">
                        <span class="privacy-pill-badge">
                            <?= render_neu_icon('shield-check', 'xs', 'text-green') ?> ไม่ขอชื่อ CID เบอร์โทรศัพท์ หรือที่อยู่
                        </span>
                        <p style="font-size: 12px;">
                            ศูนย์ข้อมูลสุขภาพและพฤติกรรมเสี่ยง 3อ. 2ส. 1น. อำเภอ<?= DISTRICT_NAME ?> จังหวัดอุบลราชธานี
                        </p>
                    </div>
                </div>
            </div>

            <!-- Filter Controls -->
            <form method="GET" action="citizen_health_dashboard.php" class="dash-filter-bar no-print">
                <select name="budget_year" class="dash-select" onchange="this.form.submit()">
                    <option value="2026" <?= $activeBudgetYear == 2026 ? 'selected' : '' ?>>ปีงบประมาณ 2569 (2026)</option>
                    <option value="2025" <?= $activeBudgetYear == 2025 ? 'selected' : '' ?>>ปีงบประมาณ 2568 (2025)</option>
                </select>

                <select name="date_filter" class="dash-select" onchange="this.form.submit()">
                    <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>ทุกช่วงเวลา</option>
                    <option value="30d" <?= $date_filter === '30d' ? 'selected' : '' ?>>30 วันล่าสุด</option>
                    <option value="90d" <?= $date_filter === '90d' ? 'selected' : '' ?>>90 วันล่าสุด</option>
                    <option value="ytd" <?= $date_filter === 'ytd' ? 'selected' : '' ?>>ปีนี้ (YTD)</option>
                </select>

                <button type="button" class="btn-dash-action" onclick="window.print()">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg> พิมพ์รายงาน
                </button>
                <button type="button" class="btn-dash-action" onclick="exportCitizenLogsToCSV()">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> ส่งออก CSV
                </button>
            </form>
        </div>

        <!-- ============================================== -->
        <!-- 🍱 MAIN BENTO GRID DASHBOARD -->
        <!-- ============================================== -->
        <div class="bento-grid">

            <!-- 1. Bento Hero: Total Assessments & Demographics Overview (Span 6) -->
            <div class="bento-card bento-span-6" style="background: linear-gradient(145deg, var(--bg-card), rgba(59, 130, 246, 0.04));">
                <div>
                    <div class="bento-header">
                        <h3 class="bento-title">
                            <?= render_neu_icon('users-group', 'sm', 'disc-blue') ?>
                            <span>ผู้ประเมินสุขภาพสะสม</span>
                        </h3>
                        <span class="bento-badge" style="display: inline-flex; align-items: center; gap: 6px;">
                            <span class="pulsing-dot" style="width: 8px; height: 8px; display: inline-block;"></span>
                            <span>ข้อมูลล่าสุด</span>
                        </span>
                    </div>

                    <!-- Total Counter Hero Row -->
                    <div style="margin: 6px 0 16px 0;">
                        <div style="font-size: 44px; font-weight: 900; line-height: 1; color: #2563eb; letter-spacing: -1px; display: flex; align-items: baseline; gap: 6px;">
                            <?= number_format($kpi['total']) ?>
                            <span style="font-size: 16px; font-weight: 800; color: var(--text-secondary);">คน</span>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px; display: flex; align-items: center; gap: 6px;">
                            <span class="neu-disc-icon xs disc-green" style="width: 18px; height: 18px; font-size: 10px;">🛡️</span>
                            <span>ประเมินตนเองผ่าน NCDs Portal (ไม่ขอตัวระบุโดยตรง)</span>
                        </div>
                    </div>

                    <!-- Gender Demographic Cards (Infographic Percentage-Filled Neumorphic Silhouettes) -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <!-- Male Liquid-Fill Silhouette Tile -->
                        <div style="background: var(--bg-main); border: 1.5px solid rgba(59, 130, 246, 0.25); border-radius: 20px; padding: 14px 14px; box-shadow: var(--neumorph-flat); display: flex; align-items: center; gap: 14px; position: relative; overflow: hidden;">
                            <!-- Liquid Silhouette SVG -->
                            <div style="flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                <svg width="50" height="92" viewBox="0 0 60 120" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 3px 6px rgba(59, 130, 246, 0.2));">
                                    <defs>
                                        <linearGradient id="maleLiquidGrad" x1="0" y1="100%" x2="0" y2="0%">
                                            <stop offset="0%" stop-color="#2563eb"/>
                                            <stop offset="<?= max(2, min(98, $pctMale)) ?>%" stop-color="#3b82f6"/>
                                            <stop offset="<?= max(2, min(98, $pctMale)) ?>%" stop-color="var(--bg-darker, #cbd5e1)"/>
                                            <stop offset="100%" stop-color="var(--bg-darker, #e2e8f0)"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="M30 4 C35.5 4 40 8.5 40 14 C40 19.5 35.5 24 30 24 C24.5 24 20 19.5 20 14 C20 8.5 24.5 4 30 4 Z M18 29 C14 30 10 34 8 38 C6 43 7 59 7 62 C7 64 9.5 65 11.5 64 C13.5 63 14 60 14.5 54 L16 40 L19 40 L19 70 L19 112 C19 115 21.5 117 24.5 117 C27.5 117 30 115 30 112 L30 72 L32 72 L32 112 C32 115 34.5 117 37.5 117 C40.5 117 43 115 43 112 L43 70 L43 40 L46 40 L47.5 54 C48 60 48.5 63 50.5 64 C52.5 65 55 64 55 62 C55 59 56 43 54 38 C52 34 48 30 44 29 C39 28 35 27.5 31 27.5 C27 27.5 23 28 18 29 Z" 
                                          fill="url(#maleLiquidGrad)" 
                                          stroke="#FFFFFF" 
                                          stroke-width="3.5" 
                                          stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <!-- Stat Details -->
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="font-size: 12px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">เพศชาย</span>
                                </div>
                                <div style="font-size: 24px; font-weight: 900; color: #2563eb; line-height: 1.1; display: flex; align-items: baseline; gap: 4px;">
                                    <?= number_format($kpi['male_count']) ?>
                                    <span style="font-size: 13px; font-weight: 700; color: var(--text-secondary);">คน</span>
                                </div>
                                <div style="margin-top: 8px;">
                                    <span style="display: inline-block; background: rgba(59, 130, 246, 0.15); color: #2563eb; font-weight: 900; font-size: 13px; padding: 3px 9px; border-radius: 8px;">
                                        <?= $pctMale ?>%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Female Liquid-Fill Silhouette Tile -->
                        <div style="background: var(--bg-main); border: 1.5px solid rgba(236, 72, 153, 0.25); border-radius: 20px; padding: 14px 14px; box-shadow: var(--neumorph-flat); display: flex; align-items: center; gap: 14px; position: relative; overflow: hidden;">
                            <!-- Liquid Silhouette SVG -->
                            <div style="flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                <svg width="50" height="92" viewBox="0 0 60 120" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 3px 6px rgba(236, 72, 153, 0.2));">
                                    <defs>
                                        <linearGradient id="femaleLiquidGrad" x1="0" y1="100%" x2="0" y2="0%">
                                            <stop offset="0%" stop-color="#db2777"/>
                                            <stop offset="<?= max(2, min(98, $pctFemale)) ?>%" stop-color="#ec4899"/>
                                            <stop offset="<?= max(2, min(98, $pctFemale)) ?>%" stop-color="var(--bg-darker, #cbd5e1)"/>
                                            <stop offset="100%" stop-color="var(--bg-darker, #e2e8f0)"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="M30 4 C35.5 4 40 8.5 40 14 C40 19.5 35.5 24 30 24 C24.5 24 20 19.5 20 14 C20 8.5 24.5 4 30 4 Z M16 20 C14 24 12 28 10 29 C12 30 15 29 17 27 L18 29 C14 31 10 35 8 40 C6 45 7 58 7 61 C7 63 9.5 64 11.5 63 C13.5 62 14 59 14.5 53 L16 41 L18 41 L12 84 C11 86 12.5 88 15 88 L23 88 L23 112 C23 115 25.5 117 28.5 117 C30 117 31 115 31 112 L31 88 L31 88 L31 112 C31 115 32 117 33.5 117 C36.5 117 39 115 39 112 L39 88 L47 88 C49.5 88 51 86 50 84 L44 41 L46 41 L47.5 53 C48 59 48.5 62 50.5 63 C52.5 64 55 63 55 61 C55 58 56 45 54 40 C52 35 48 31 44 29 L45 27 C47 29 50 30 52 29 C50 28 48 24 46 20 C44 23 42 25 40 26 C37 27 34 27.5 31 27.5 C28 27.5 25 27 22 26 C20 25 18 23 16 20 Z" 
                                          fill="url(#femaleLiquidGrad)" 
                                          stroke="#FFFFFF" 
                                          stroke-width="3.5" 
                                          stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <!-- Stat Details -->
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="font-size: 12px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">เพศหญิง</span>
                                </div>
                                <div style="font-size: 24px; font-weight: 900; color: #db2777; line-height: 1.1; display: flex; align-items: baseline; gap: 4px;">
                                    <?= number_format($kpi['female_count']) ?>
                                    <span style="font-size: 13px; font-weight: 700; color: var(--text-secondary);">คน</span>
                                </div>
                                <div style="margin-top: 8px;">
                                    <span style="display: inline-block; background: rgba(236, 72, 153, 0.15); color: #db2777; font-weight: 900; font-size: 13px; padding: 3px 9px; border-radius: 8px;">
                                        <?= $pctFemale ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- District Average Risk Score Meter -->
                <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 18px; padding: 14px 16px; margin-top: 2px; box-shadow: var(--neumorph-inset);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="neu-disc-icon xs disc-purple" style="width: 26px; height: 26px; font-size: 13px;">⚖️</span>
                            <span style="font-size: 13px; font-weight: 800; color: var(--text-primary);">คะแนนเสี่ยงเฉลี่ยระดับอำเภอ</span>
                        </div>
                        <span style="color: #7c3aed; font-size: 17px; font-weight: 900; letter-spacing: -0.2px;">
                            <?= number_format($kpi['avg_score'], 1) ?> <span style="font-size: 12px; opacity: 0.7; color: var(--text-muted);">/ 25</span>
                        </span>
                    </div>
                    <div style="height: 8px; background: var(--bg-darker); border-radius: 9999px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                        <div style="height: 100%; width: <?= min(100, max(5, ($kpi['avg_score'] / 25) * 100)) ?>%; background: linear-gradient(90deg, #10b981 0%, #f59e0b 50%, #ef4444 100%); border-radius: 9999px;"></div>
                    </div>
                </div>
            </div>

            <!-- 2. Bento Card: Risk Stratification Donut (Span 6) -->
            <div class="bento-card bento-span-6">
                <div class="bento-header">
                    <h3 class="bento-title">
                        <?= render_neu_icon('heart-pulse', 'sm', 'disc-green') ?>
                        <span>สัดส่วนระดับความเสี่ยงสุขภาพภาพรวม</span>
                    </h3>
                    <span class="bento-badge">N = <?= number_format($kpi['total']) ?> คน</span>
                </div>
                <div style="position: relative; height: 200px; margin: 6px 0;">
                    <canvas id="riskPieChart"></canvas>
                </div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 12px; text-align: center;">
                    <div style="background: rgba(16, 185, 129, 0.08); border: 1.5px solid rgba(16, 185, 129, 0.25); border-radius: 16px; padding: 10px 6px;">
                        <div style="color: #10b981; font-weight: 900; font-size: 17px;"><?= $pctGreen ?>%</div>
                        <div style="font-size: 12px; color: var(--text-secondary); font-weight: 800; white-space: nowrap; margin-top: 2px;">🟢 สุขภาพดี</div>
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; margin-top: 1px;"><?= number_format($kpi['green']) ?> คน</div>
                    </div>
                    <div style="background: rgba(245, 158, 11, 0.08); border: 1.5px solid rgba(245, 158, 11, 0.25); border-radius: 16px; padding: 10px 6px;">
                        <div style="color: #f59e0b; font-weight: 900; font-size: 17px;"><?= $pctYellow ?>%</div>
                        <div style="font-size: 12px; color: var(--text-secondary); font-weight: 800; white-space: nowrap; margin-top: 2px;">🟡 เริ่มเสี่ยง</div>
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; margin-top: 1px;"><?= number_format($kpi['yellow']) ?> คน</div>
                    </div>
                    <div style="background: rgba(239, 68, 68, 0.08); border: 1.5px solid rgba(239, 68, 68, 0.25); border-radius: 16px; padding: 10px 6px;">
                        <div style="color: #ef4444; font-weight: 900; font-size: 17px;"><?= $pctRed ?>%</div>
                        <div style="font-size: 12px; color: var(--text-secondary); font-weight: 800; white-space: nowrap; margin-top: 2px;">🔴 เสี่ยงสูง</div>
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; margin-top: 1px;"><?= number_format($kpi['red']) ?> คน</div>
                    </div>
                </div>
            </div>

            <!-- 4. Bento Card: Strategic Policy Recommendation (Span 12) -->
            <div class="bento-card bento-span-12" style="background: linear-gradient(135deg, rgba(37, 99, 235, 0.06), rgba(16, 185, 129, 0.06)); border-left: 5px solid #2563eb;">
                <div style="display: flex; align-items: flex-start; gap: 16px;">
                    <div style="flex-shrink: 0; padding-top: 2px;">
                        <?= render_neu_icon('first-aid', 'lg', 'text-blue') ?>
                    </div>
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
                            <h3 style="margin: 0; font-size: 16.5px; font-weight: 900; color: #1e40af;">
                                ข้อค้นพบทางระบาดวิทยา & ข้อเสนอแนะเชิงนโยบายสาธารณสุขอำเภอ<?= DISTRICT_NAME ?>
                            </h3>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <span class="bento-badge" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; border-color: rgba(239, 68, 68, 0.25);">🧂 ชอบเค็ม <?= $highSaltPct ?>%</span>
                                <span class="bento-badge" style="background: rgba(245, 158, 11, 0.12); color: #d97706; border-color: rgba(245, 158, 11, 0.25);">🥤 ติดหวาน <?= $highSweetPct ?>%</span>
                                <span class="bento-badge" style="background: rgba(236, 72, 153, 0.12); color: #db2777; border-color: rgba(236, 72, 153, 0.25);">🪑 เนือยนิ่ง <?= $sedentaryPct ?>%</span>
                                <span class="bento-badge" style="background: rgba(249, 115, 22, 0.12); color: #ea580c; border-color: rgba(249, 115, 22, 0.25);">⚖️ อ้วนลงพุง <?= $obesePct ?>%</span>
                            </div>
                        </div>
                        <p style="margin: 0; font-size: 13.5px; color: var(--text-primary); line-height: 1.6;">
                            <?php if ($kpi['total'] > 0): ?>
                                จากสถิติภาพรวมของประชาชนที่ร่วมประเมินตนเอง พบพฤติกรรมเสี่ยงสำคัญที่ต้องเร่งเฝ้าระวังเชิงรุกในพื้นที่<br>
                                👉 <strong>มาตรการขับเคลื่อนที่แนะนำ:</strong> มอบหมาย อสม. และทีม รพ.สต. เร่งรณรงค์โครงการ <em>"เค็มน้อย อร่อยได้ & ชวนขยับกายวันละ 30 นาที"</em> พร้อมเชื่อมโยงผู้ที่มีความเสี่ยงสูง (กลุ่มสีแดง) เข้าสู่คลินิก DPAC เพื่อรับการตรวจยืนยันค่าน้ำตาลและความดันโลหิตต่อไป
                            <?php else: ?>
                                ยังไม่มีข้อมูลสถิติผลการประเมินในเงื่อนไขตัวกรองนี้ ประชาชนสามารถเริ่มประเมินตนเองได้ฟรีที่หน้า <a href="../self_screening.php" target="_blank" style="color: #2563eb; font-weight: 800;">แบบประเมินสุขภาพตนเอง</a>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- 5. Bento Card: 3อ. 2ส. 1น. Risk Radar / Bar Matrix (Span 7) -->
            <div class="bento-card bento-span-7">
                <div class="bento-header">
                    <h3 class="bento-title">
                        <?= render_neu_icon('nutrition', 'sm', 'text-green') ?>
                        <span>พฤติกรรมสุขภาพรอบด้าน (3อ. 2ส. 1น. Prevalence Matrix)</span>
                    </h3>
                    <span class="bento-badge">% สัดส่วนพฤติกรรมเสี่ยง</span>
                </div>
                <div style="position: relative; height: 290px;">
                    <canvas id="habitsBarChart"></canvas>
                </div>
            </div>

            <!-- 6. Bento Card: Age Group Risk Distribution (Span 5) -->
            <div class="bento-card bento-span-5">
                <div class="bento-header">
                    <h3 class="bento-title" style="white-space: nowrap;">
                        <?= render_neu_icon('users-group', 'sm', 'text-purple') ?>
                        <span>การกระจายความเสี่ยงจำแนกตามช่วงวัย</span>
                    </h3>
                    <span class="bento-badge" style="white-space: nowrap; flex-shrink: 0;">3 ช่วงวัย</span>
                </div>
                <div style="position: relative; height: 290px;">
                    <canvas id="ageRiskChart"></canvas>
                </div>
            </div>

            <!-- 7. Bento Card: Deep-Dive 3อ. 2ส. 1น. Nutrition & Lifestyle Progress Trackers (Span 12) -->
            <div class="bento-card bento-span-12">
                <div class="bento-header">
                    <h3 class="bento-title">
                        <?= render_neu_icon('capsules', 'sm', 'text-yellow') ?>
                        <span>รายละเอียดเจาะลึกพฤติกรรมโภชนาการและการใช้ชีวิต (3อ. 2ส. 1น. Breakdown)</span>
                    </h3>
                    <span class="bento-badge">6 หมวดหมู่พฤติกรรม</span>
                </div>

                <div class="bento-habits-subgrid">
                    <!-- 1. Sweet Habit -->
                    <div class="bento-habit-tile">
                        <div class="habit-label-row">
                            <span style="display: inline-flex; align-items: center; gap: 8px; font-weight: 800;">
                                <?= render_neu_icon('sugar-sweet', 'xs', 'text-yellow') ?>
                                1. การบริโภคหวาน (ชาหวาน/น้ำอัดลม)
                            </span>
                            <span style="color: var(--text-secondary); font-size: 11.5px;">
                                ติดหวาน <?= round((($habits['sweet']['high']??0)/$tot)*100, 1) ?>% | บางวัน <?= round((($habits['sweet']['med']??0)/$tot)*100, 1) ?>% | น้ำเปล่า <?= round((($habits['sweet']['low']??0)/$tot)*100, 1) ?>%
                            </span>
                        </div>
                        <div class="habit-bar-track">
                            <div class="habit-bar-segment" style="width: <?= (($habits['sweet']['low']??0)/$tot)*100 ?>%; background: #10b981;" title="ดื่มน้ำเปล่าเป็นหลัก"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['sweet']['med']??0)/$tot)*100 ?>%; background: #f59e0b;" title="ดื่มบ้างบางวัน"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['sweet']['high']??0)/$tot)*100 ?>%; background: #ef4444;" title="ติดหวานเกือบทุกวัน"></div>
                        </div>
                    </div>

                    <!-- 2. Salt Habit -->
                    <div class="bento-habit-tile">
                        <div class="habit-label-row">
                            <span style="display: inline-flex; align-items: center; gap: 8px; font-weight: 800;">
                                <?= render_neu_icon('salt-sodium', 'xs', 'text-blue') ?>
                                2. การบริโภคเค็ม/โซเดียม (น้ำปลา/ปลาร้า/ของทอด)
                            </span>
                            <span style="color: var(--text-secondary); font-size: 11.5px;">
                                เค็มจัด <?= round((($habits['salt']['high']??0)/$tot)*100, 1) ?>% | ปานกลาง <?= round((($habits['salt']['med']??0)/$tot)*100, 1) ?>% | จืด <?= round((($habits['salt']['low']??0)/$tot)*100, 1) ?>%
                            </span>
                        </div>
                        <div class="habit-bar-track">
                            <div class="habit-bar-segment" style="width: <?= (($habits['salt']['low']??0)/$tot)*100 ?>%; background: #10b981;" title="รสจืด"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['salt']['med']??0)/$tot)*100 ?>%; background: #f59e0b;" title="ปานกลาง"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['salt']['high']??0)/$tot)*100 ?>%; background: #ef4444;" title="เค็มจัด/ซดน้ำแกง"></div>
                        </div>
                    </div>

                    <!-- 3. Veggie Habit -->
                    <div class="bento-habit-tile">
                        <div class="habit-label-row">
                            <span style="display: inline-flex; align-items: center; gap: 8px; font-weight: 800;">
                                <?= render_neu_icon('nutrition', 'xs', 'text-green') ?>
                                3. การรับประทานผักและผลไม้
                            </span>
                            <span style="color: var(--text-secondary); font-size: 11.5px;">
                                กินทุกมื้อ <?= round((($habits['veggie']['good']??0)/$tot)*100, 1) ?>% | กินน้อย/ไม่ค่อยกิน <?= round((($habits['veggie']['poor']??0)/$tot)*100, 1) ?>%
                            </span>
                        </div>
                        <div class="habit-bar-track">
                            <div class="habit-bar-segment" style="width: <?= (($habits['veggie']['good']??0)/$tot)*100 ?>%; background: #10b981;" title="กินทุกมื้อ"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['veggie']['poor']??0)/$tot)*100 ?>%; background: #ef4444;" title="กินน้อย/ไม่ค่อยกิน"></div>
                        </div>
                    </div>

                    <!-- 4. Exercise Habit -->
                    <div class="bento-habit-tile">
                        <div class="habit-label-row">
                            <span style="display: inline-flex; align-items: center; gap: 8px; font-weight: 800;">
                                <?= render_neu_icon('exercise', 'xs', 'text-green') ?>
                                4. การออกกำลังกาย & ขยับร่างกาย
                            </span>
                            <span style="color: var(--text-secondary); font-size: 11.5px;">
                                ประจำ <?= round((($habits['exercise']['regular']??0)/$tot)*100, 1) ?>% | มีบ้าง <?= round((($habits['exercise']['some']??0)/$tot)*100, 1) ?>% | เนือยนิ่ง <?= round((($habits['exercise']['sedentary']??0)/$tot)*100, 1) ?>%
                            </span>
                        </div>
                        <div class="habit-bar-track">
                            <div class="habit-bar-segment" style="width: <?= (($habits['exercise']['regular']??0)/$tot)*100 ?>%; background: #10b981;" title="เป็นประจำ"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['exercise']['some']??0)/$tot)*100 ?>%; background: #f59e0b;" title="มีบ้าง"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['exercise']['sedentary']??0)/$tot)*100 ?>%; background: #ef4444;" title="แทบไม่ออก/นั่งนาน"></div>
                        </div>
                    </div>

                    <!-- 5. Sleep Quality -->
                    <div class="bento-habit-tile">
                        <div class="habit-label-row">
                            <span style="display: inline-flex; align-items: center; gap: 8px; font-weight: 800;">
                                <?= render_neu_icon('sleep', 'xs', 'text-purple') ?>
                                5. คุณภาพการนอนหลับและการพักผ่อน
                            </span>
                            <span style="color: var(--text-secondary); font-size: 11.5px;">
                                สนิทดี <?= round((($habits['sleep']['good']??0)/$tot)*100, 1) ?>% | หลับยาก/ไม่พอ <?= round((($habits['sleep']['poor']??0)/$tot)*100, 1) ?>%
                            </span>
                        </div>
                        <div class="habit-bar-track">
                            <div class="habit-bar-segment" style="width: <?= (($habits['sleep']['good']??0)/$tot)*100 ?>%; background: #10b981;" title="หลับสนิท"></div>
                            <div class="habit-bar-segment" style="width: <?= (($habits['sleep']['poor']??0)/$tot)*100 ?>%; background: #ef4444;" title="หลับยาก/นอนไม่พอ"></div>
                        </div>
                    </div>

                    <!-- 6. Substance Habit -->
                    <div class="bento-habit-tile">
                        <div class="habit-label-row">
                            <span style="display: inline-flex; align-items: center; gap: 8px; font-weight: 800;">
                                <?= render_neu_icon('no-substance', 'xs', 'text-red') ?>
                                6. บุหรี่และสุรา (2ส.)
                            </span>
                            <span style="color: var(--text-secondary); font-size: 11.5px;">
                                ไม่แตะ <?= round((($habits['substance']['none']??0)/$tot)*100, 1) ?>% | สังสรรค์ <?= round((($habits['substance']['some']??0)/$tot)*100, 1) ?>% | ประจำ <?= round((($habits['substance']['regular']??0)/$tot)*100, 1) ?>%
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

            <!-- 8. Bento Card: Citizen Self-Screening Logs Table (Span 12) -->
            <div class="bento-card bento-span-12">
                <div class="bento-header" style="flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 class="bento-title">
                            <?= render_neu_icon('clipboard-record', 'sm', 'text-navy') ?>
                            <span>บันทึกสถิติการประเมินสุขภาพตนเอง (Citizen Self-Screening Logs)</span>
                        </h3>
                        <p style="margin: 3px 0 0 0; font-size: 12.5px; color: var(--text-secondary);">
                            สถิติจากแบบประเมินตนเองที่ไม่ขอชื่อ CID เบอร์โทรศัพท์ หรือที่อยู่
                        </p>
                    </div>
                    <div class="no-print" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <!-- Search Input -->
                        <div style="position: relative;">
                            <input type="text" id="logSearchInput" oninput="handleLogSearch()" placeholder="🔍 ค้นหาผล, วัย, พฤติกรรม..." class="dash-select" style="width: 220px; padding-right: 28px;">
                            <button type="button" id="clearSearchBtn" onclick="clearLogSearch()" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 13px; font-weight: bold;">✕</button>
                        </div>
                        
                        <!-- Page Size Selector -->
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label for="logPageSizeSelect" style="font-size: 12.5px; font-weight: 700; color: var(--text-secondary); white-space: nowrap;">แสดง:</label>
                            <select id="logPageSizeSelect" class="dash-select" onchange="changeLogPageSize(this.value)" style="padding: 7px 10px; font-size: 12.5px;">
                                <option value="10" selected>10 รายการ / หน้า</option>
                                <option value="25">25 รายการ / หน้า</option>
                                <option value="50">50 รายการ / หน้า</option>
                                <option value="100">100 รายการ / หน้า</option>
                                <option value="all">ทั้งหมด</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="anon-table" id="anonLogsTable">
                        <thead>
                            <tr>
                                <th style="width: 45px; text-align: center;">#</th>
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
                                <th style="text-align: center;">คะแนน</th>
                                <th style="text-align: center;">ผลการประเมิน</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <!-- Populated dynamically via renderLogsTable() -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Bar -->
                <div id="logsPaginationBar" class="pagination-bar no-print" style="margin-top: 16px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <!-- Populated dynamically via renderLogsPagination() -->
                </div>
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
                    hoverBackgroundColor: ['#059669', '#d97706', '#dc2626'],
                    borderWidth: 2,
                    borderColor: isDark ? '#1e293b' : '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const val = context.raw || 0;
                                const total = <?= max(1, $kpi['total']) ?>;
                                const pct = ((val / total) * 100).toFixed(1);
                                return ` ${context.label}: ${val.toLocaleString()} คน (${pct}%)`;
                            }
                        }
                    }
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

        // 4. Age Group Risk Stacked Chart
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
                        labels: { 
                            color: textColor, 
                            font: { size: 11.5, weight: 'bold' },
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                }
            }
        });

        // ====================================================
        // 📋 DYNAMIC PAGINATION & REAL-TIME SEARCH FOR LOGS
        // ====================================================
        const allLogsData = <?= json_encode($recentLogs, JSON_UNESCAPED_UNICODE) ?>;
        let filteredLogsData = [...allLogsData];
        let currentLogPage = 1;
        let currentLogPageSize = 10;

        const ageMap = { 'young': '< 35 ปี', 'middle': '35-59 ปี', 'senior': '60+ ปี' };
        const shapeMap = { 'thin': 'ผอม/น้อย', 'slim': 'สมส่วน', 'chubby': 'เริ่มมีพุง', 'obese': 'อ้วนลงพุง' };
        const genderMap = { 'male': 'ชาย', 'female': 'หญิง' };

        function renderLogsTable() {
            const tbody = document.getElementById('logsTableBody');
            if (!tbody) return;

            if (filteredLogsData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="14" style="text-align: center; padding: 36px 20px; color: var(--text-muted); font-size: 13.5px; font-weight: 600;">
                            🔍 ไม่พบข้อมูลการประเมินที่ตรงกับคำค้นหา
                        </td>
                    </tr>
                `;
                renderLogsPagination();
                return;
            }

            const startIndex = currentLogPageSize === 'all' ? 0 : (currentLogPage - 1) * currentLogPageSize;
            const endIndex = currentLogPageSize === 'all' ? filteredLogsData.length : Math.min(startIndex + currentLogPageSize, filteredLogsData.length);
            const pageData = filteredLogsData.slice(startIndex, endIndex);

            let html = '';
            pageData.forEach((log, idx) => {
                const rowNumber = startIndex + idx + 1;
                const rClass = log.risk_level === 'green' ? 'risk-badge-green' : (log.risk_level === 'yellow' ? 'risk-badge-yellow' : 'risk-badge-red');
                const rText = log.risk_level === 'green' ? '🟢 สุขภาพดี' : (log.risk_level === 'yellow' ? '🟡 เริ่มเสี่ยง' : '🔴 เสี่ยงสูง');
                
                // Format Thai date
                let dateStr = '-';
                if (log.created_at) {
                    const dt = new Date(log.created_at.replace(/-/g, '/'));
                    const d = dt.getDate().toString().padStart(2, '0');
                    const m = (dt.getMonth() + 1).toString().padStart(2, '0');
                    const y = dt.getFullYear();
                    const hh = dt.getHours().toString().padStart(2, '0');
                    const mm = dt.getMinutes().toString().padStart(2, '0');
                    dateStr = `${d}/${m}/${y} ${hh}:${mm}`;
                }

                html += `
                    <tr>
                        <td style="text-align: center; font-weight: 700; color: var(--text-muted); font-size: 12px;">${rowNumber}</td>
                        <td style="white-space: nowrap; font-size: 12px; color: var(--text-secondary); font-weight: 600;">${dateStr}</td>
                        <td style="font-weight: 700;">${genderMap[log.gender] || log.gender || '-'}</td>
                        <td>${ageMap[log.age_group] || log.age_group || '-'}</td>
                        <td>${shapeMap[log.body_shape] || log.body_shape || '-'}</td>
                        <td>${log.sweet_habit === 'high' ? '🔴 ติดหวาน' : (log.sweet_habit === 'med' ? '🟡 ดื่มบ้าง' : '🟢 น้ำเปล่า')}</td>
                        <td>${log.salt_habit === 'high' ? '🔴 เค็มจัด' : (log.salt_habit === 'med' ? '🟡 ปานกลาง' : '🟢 รสจืด')}</td>
                        <td>${log.veggie_habit === 'good' ? '🟢 กินทุกมื้อ' : '🔴 กินน้อย'}</td>
                        <td>${log.exercise_habit === 'regular' ? '🟢 ประจำ' : (log.exercise_habit === 'some' ? '🟡 มีบ้าง' : '🔴 นั่งนาน')}</td>
                        <td>${log.sleep_habit === 'good' ? '🟢 หลับสนิท' : '🔴 หลับยาก'}</td>
                        <td>${log.substance_habit === 'none' ? '🟢 ไม่แตะ' : (log.substance_habit === 'some' ? '🟡 สังสรรค์' : '🔴 ประจำ')}</td>
                        <td>${log.family_history === 'yes' ? '⚠️ มี' : 'ไม่มี'}</td>
                        <td style="text-align: center; font-weight: 900; color: var(--text-primary); font-size: 13.5px;">${parseInt(log.risk_points) || 0}</td>
                        <td style="text-align: center;">
                            <span class="risk-badge-sm ${rClass}">
                                ${rText}
                            </span>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            renderLogsPagination();
        }

        function renderLogsPagination() {
            const bar = document.getElementById('logsPaginationBar');
            if (!bar) return;

            const total = filteredLogsData.length;
            if (total === 0) {
                bar.innerHTML = '';
                return;
            }

            if (currentLogPageSize === 'all') {
                bar.innerHTML = `
                    <span class="pagination-info">แสดงทั้งหมด <strong>${total.toLocaleString()}</strong> รายการ</span>
                `;
                return;
            }

            const totalPages = Math.max(1, Math.ceil(total / currentLogPageSize));
            const startItem = (currentLogPage - 1) * currentLogPageSize + 1;
            const endItem = Math.min(currentLogPage * currentLogPageSize, total);

            let paginationBtnsHtml = `
                <button class="pagination-btn" onclick="goToLogPage(1)" ${currentLogPage === 1 ? 'disabled' : ''} title="หน้าแรก">«</button>
                <button class="pagination-btn" onclick="goToLogPage(${currentLogPage - 1})" ${currentLogPage === 1 ? 'disabled' : ''} title="ก่อนหน้า">‹</button>
            `;

            let startP = Math.max(1, currentLogPage - 2);
            let endP = Math.min(totalPages, currentLogPage + 2);
            
            if (startP > 1) {
                paginationBtnsHtml += `<button class="pagination-btn" onclick="goToLogPage(1)">1</button>`;
                if (startP > 2) paginationBtnsHtml += `<span style="padding: 0 4px; color: var(--text-muted); font-size: 12px;">...</span>`;
            }
            
            for (let p = startP; p <= endP; p++) {
                paginationBtnsHtml += `
                    <button class="pagination-btn ${p === currentLogPage ? 'active' : ''}" onclick="goToLogPage(${p})">${p}</button>
                `;
            }
            
            if (endP < totalPages) {
                if (endP < totalPages - 1) paginationBtnsHtml += `<span style="padding: 0 4px; color: var(--text-muted); font-size: 12px;">...</span>`;
                paginationBtnsHtml += `<button class="pagination-btn" onclick="goToLogPage(${totalPages})">${totalPages}</button>`;
            }

            paginationBtnsHtml += `
                <button class="pagination-btn" onclick="goToLogPage(${currentLogPage + 1})" ${currentLogPage === totalPages ? 'disabled' : ''} title="ถัดไป">›</button>
                <button class="pagination-btn" onclick="goToLogPage(${totalPages})" ${currentLogPage === totalPages ? 'disabled' : ''} title="หน้าสุดท้าย">»</button>
            `;

            bar.innerHTML = `
                <span class="pagination-info">
                    แสดง <strong>${startItem.toLocaleString()}–${endItem.toLocaleString()}</strong> จาก <strong>${total.toLocaleString()}</strong> รายการ (หน้า ${currentLogPage} จาก ${totalPages})
                </span>
                <div class="pagination-controls">
                    ${paginationBtnsHtml}
                </div>
            `;
        }

        function goToLogPage(page) {
            const totalPages = Math.max(1, Math.ceil(filteredLogsData.length / currentLogPageSize));
            if (page < 1 || page > totalPages) return;
            currentLogPage = page;
            renderLogsTable();
        }

        function changeLogPageSize(newSize) {
            currentLogPageSize = newSize === 'all' ? 'all' : parseInt(newSize, 10);
            currentLogPage = 1;
            renderLogsTable();
        }

        function handleLogSearch() {
            const search = document.getElementById('logSearchInput').value.trim().toLowerCase();
            const clearBtn = document.getElementById('clearSearchBtn');
            if (clearBtn) clearBtn.style.display = search ? 'block' : 'none';

            if (!search) {
                filteredLogsData = [...allLogsData];
            } else {
                filteredLogsData = allLogsData.filter(log => {
                    const rText = log.risk_level === 'green' ? 'สุขภาพดี เขียว green' : (log.risk_level === 'yellow' ? 'เริ่มเสี่ยง เหลือง yellow' : 'เสี่ยงสูง แดง red');
                    const genderText = genderMap[log.gender] || '';
                    const ageText = ageMap[log.age_group] || '';
                    const shapeText = shapeMap[log.body_shape] || '';
                    const searchStr = `${log.created_at} ${genderText} ${ageText} ${shapeText} ${rText} ${log.risk_points} ${log.sweet_habit} ${log.salt_habit}`.toLowerCase();
                    return searchStr.includes(search);
                });
            }
            currentLogPage = 1;
            renderLogsTable();
        }

        function clearLogSearch() {
            const input = document.getElementById('logSearchInput');
            if (input) input.value = '';
            handleLogSearch();
        }

        // Export All Filtered Records to CSV
        function exportCitizenLogsToCSV() {
            if (!filteredLogsData || filteredLogsData.length === 0) {
                alert('⚠️ ไม่มีข้อมูลสำหรับการส่งออก');
                return;
            }

            let csv = '\uFEFF'; // UTF-8 BOM for Excel support
            const headers = ['ลำดับ', 'วัน-เวลาประเมิน', 'เพศ', 'ช่วงวัย', 'รูปร่าง & พุง', 'ของหวาน', 'ของเค็ม', 'การกินผัก', 'ออกกำลังกาย', 'การนอน', 'บุหรี่/สุรา', 'กรรมพันธุ์', 'คะแนน', 'ผลการประเมิน'];
            csv += headers.map(h => `"${h}"`).join(',') + '\r\n';

            filteredLogsData.forEach((log, idx) => {
                const rText = log.risk_level === 'green' ? 'สุขภาพดี' : (log.risk_level === 'yellow' ? 'เริ่มเสี่ยง' : 'เสี่ยงสูง');
                const sweetText = log.sweet_habit === 'high' ? 'ติดหวาน' : (log.sweet_habit === 'med' ? 'ดื่มบ้าง' : 'น้ำเปล่า');
                const saltText = log.salt_habit === 'high' ? 'เค็มจัด' : (log.salt_habit === 'med' ? 'ปานกลาง' : 'รสจืด');
                const veggieText = log.veggie_habit === 'good' ? 'กินทุกมื้อ' : 'กินน้อย';
                const exerciseText = log.exercise_habit === 'regular' ? 'ประจำ' : (log.exercise_habit === 'some' ? 'มีบ้าง' : 'นั่งนาน');
                const sleepText = log.sleep_habit === 'good' ? 'หลับสนิท' : 'หลับยาก';
                const substanceText = log.substance_habit === 'none' ? 'ไม่แตะ' : (log.substance_habit === 'some' ? 'สังสรรค์' : 'ประจำ');
                const famText = log.family_history === 'yes' ? 'มี' : 'ไม่มี';

                const cols = [
                    idx + 1,
                    log.created_at || '-',
                    genderMap[log.gender] || log.gender || '-',
                    ageMap[log.age_group] || log.age_group || '-',
                    shapeMap[log.body_shape] || log.body_shape || '-',
                    sweetText,
                    saltText,
                    veggieText,
                    exerciseText,
                    sleepText,
                    substanceText,
                    famText,
                    parseInt(log.risk_points) || 0,
                    rText
                ];
                csv += cols.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',') + '\r\n';
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.setAttribute('download', 'citizen_health_screening_report_<?= date('Ymd_His') ?>.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Initialize Table on Load
        document.addEventListener('DOMContentLoaded', () => {
            renderLogsTable();
        });
        // Immediate fallback in case DOMContentLoaded already fired
        renderLogsTable();
    </script>
</body>
</html>
