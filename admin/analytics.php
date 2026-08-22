<?php
// admin/analytics.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$isSandboxVal = isSandboxMode($admin_hoscode ?: null) ? 1 : 0;

$hc_names = get_health_units();


$admin_title = get_admin_title();

require_once __DIR__ . '/../config/demo_data.php';

if (DemoDataProvider::isDemoMode()) {
    $mockExec = DemoDataProvider::getMockExecutiveMetrics();
    $mockAnalytics = DemoDataProvider::getMockAnalyticsData();

    $availableBudgetYears = [2026, 2025];
    $defaultBudgetYear = 2026;
    $selectedBudgetYear = 2026;
    $selectedRound = 0;
    $dateFrom = '';
    $dateTo = '';
    $allowedHoscodes = ['99999'];
    $selectedHoscode = '';
    $hoscodes = ['99999'];
    $inPlaceholders = '?';

    $beforeAfterData = $mockAnalytics['beforeAfterData'] ?? [];
    $ncdBeforeAfterData = $mockAnalytics['ncdMultiRoundData'] ?? [];
    $funnelData = ['total_targets' => 250, 'r1_completed' => 185, 'r2_assigned' => 70, 'r2_completed' => 64, 'r3_completed' => 7];
    $actionCounts = ['overdue' => 2, 'awaiting_r2' => 115, 'high_risk_unassigned' => 4];
    $overdueActionRows = [
        [
            'assignment_id' => 'DEMO_OVERDUE_1',
            'round_number' => 2,
            'assigned_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'cid' => '9999900000005',
            'first_name' => 'วิชัย',
            'last_name' => 'มั่นคง (จำลอง)',
            'house_no' => '15/3',
            'moo' => '3',
            'hoscode' => '99999',
            'vhv_name' => 'อสม. บุญทัน เจริญดี (จำลอง)',
            'waiting_days' => 10
        ]
    ];
    $dataQuality = ['missing_body' => 0, 'missing_vitals' => 0, 'missing_geo' => 0, 'round_mismatch' => 0, 'orphan_results' => 0, 'mock_cid' => 0];

    $prevalenceList = [
        ['name' => 'บ้านตาลสุม (จำลอง)', 'total_screened' => 50, 'risk_count' => 12, 'rate' => 24.0],
        ['name' => 'บ้านดอนใหญ่ (จำลอง)', 'total_screened' => 60, 'risk_count' => 15, 'rate' => 25.0],
        ['name' => 'บ้านโคกสว่าง (จำลอง)', 'total_screened' => 45, 'risk_count' => 10, 'rate' => 22.2]
    ];
    $highestPrevalence = $prevalenceList;
    $improvementList = [
        ['name' => 'บ้านตาลสุม (จำลอง)', 'completed' => 42, 'improved' => 30, 'rate' => 71.4],
        ['name' => 'บ้านดอนใหญ่ (จำลอง)', 'completed' => 45, 'improved' => 32, 'rate' => 71.1],
        ['name' => 'บ้านโคกสว่าง (จำลอง)', 'completed' => 32, 'improved' => 22, 'rate' => 68.8]
    ];
    $bestImprovement = $improvementList;
    $concerningAreas = array_reverse($improvementList);

    $villageImprovementData = [
        ['hoscode' => '99999', 'moo' => '1', 'total_enrolled' => 12, 'completed_followups' => 10, 'improved_count' => 8],
        ['hoscode' => '99999', 'moo' => '2', 'total_enrolled' => 15, 'completed_followups' => 12, 'improved_count' => 9],
        ['hoscode' => '99999', 'moo' => '3', 'total_enrolled' => 8, 'completed_followups' => 6, 'improved_count' => 4],
        ['hoscode' => '99999', 'moo' => '4', 'total_enrolled' => 6, 'completed_followups' => 4, 'improved_count' => 3],
        ['hoscode' => '99999', 'moo' => '5', 'total_enrolled' => 5, 'completed_followups' => 3, 'improved_count' => 2]
    ];
    $spatialPrevalenceData = [];

    $historyRecords = [
        ['cid' => '9999900000001', 'latitude' => 15.4312, 'longitude' => 104.9823, 'risk_level' => 'MODERATE', 'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))],
        ['cid' => '9999900000002', 'latitude' => 15.4354, 'longitude' => 104.9856, 'risk_level' => 'HIGH', 'created_at' => date('Y-m-d H:i:s', strtotime('-8 days'))],
        ['cid' => '9999900000003', 'latitude' => 15.4289, 'longitude' => 104.9790, 'risk_level' => 'HIGH', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))],
        ['cid' => '9999900000004', 'latitude' => 15.4380, 'longitude' => 104.9912, 'risk_level' => 'NORMAL', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))]
    ];
    $mapTargets = [
        ['cid' => '9999900000001', 'first_name' => 'สมชาย', 'last_name' => 'ใจดี (จำลอง)', 'house_no' => '12/1', 'moo' => '1', 'hoscode' => '99999', 'latitude' => 15.4312, 'longitude' => 104.9823],
        ['cid' => '9999900000002', 'first_name' => 'สมศรี', 'last_name' => 'สุขสรรค์ (จำลอง)', 'house_no' => '45/2', 'moo' => '1', 'hoscode' => '99999', 'latitude' => 15.4354, 'longitude' => 104.9856],
        ['cid' => '9999900000003', 'first_name' => 'บุญมี', 'last_name' => 'มีโชค (จำลอง)', 'house_no' => '88', 'moo' => '2', 'hoscode' => '99999', 'latitude' => 15.4289, 'longitude' => 104.9790],
        ['cid' => '9999900000004', 'first_name' => 'ทองสุข', 'last_name' => 'สดใส (จำลอง)', 'house_no' => '101', 'moo' => '2', 'hoscode' => '99999', 'latitude' => 15.4380, 'longitude' => 104.9912]
    ];
    $mapCenterLat = 15.4325;
    $mapCenterLng = 104.9845;
    $mapInitialZoom = 13;

    $monthlyTrend = [
        ['month_year' => '2025-10', 'high_risk' => 8, 'moderate_risk' => 14],
        ['month_year' => '2025-11', 'high_risk' => 12, 'moderate_risk' => 18],
        ['month_year' => '2025-12', 'high_risk' => 15, 'moderate_risk' => 20],
        ['month_year' => '2026-01', 'high_risk' => 10, 'moderate_risk' => 16],
        ['month_year' => '2026-02', 'high_risk' => 6, 'moderate_risk' => 12]
    ];

    $surveyStats = [
        'count' => 45,
        'peou_mean' => 4.65,
        'peou_sd' => 0.48,
        'sq_mean' => 4.72,
        'sq_sd' => 0.45,
        'iq_mean' => 4.80,
        'iq_sd' => 0.40,
        'pu_mean' => 4.68,
        'pu_sd' => 0.49,
        'bi_mean' => 4.85,
        'bi_sd' => 0.36,
        'total_mean' => 4.74,
        'total_sd' => 0.43
    ];
    $tagsCount = [
        'ใช้งานง่าย' => 38,
        'สะดวก รวดเร็ว' => 35,
        'ไม่ต้องพกกระดาษ' => 32,
        'ระบบแจ้งเตือนชัดเจน' => 28,
        'ข้อมูลถูกต้อง' => 25
    ];

    $highConversionCount = 2;
    $topConversionRisks = [
        [
            'cid' => '9999900000005',
            'name' => 'วิชัย มั่นคง (จำลอง)',
            'house_no' => '15/3',
            'moo' => '3',
            'village' => 'บ้านโคกสว่าง (จำลอง)',
            'hc' => 'รพ.สต. ตาลสุม (จำลอง)',
            'age' => 68,
            'score' => 85,
            'factors' => 'ความดันเริ่มสูง (162/98), น้ำตาลเจาะเสี่ยงสูง (210 mg/dL), BMI น้ำหนักเกิน (26.4), กลุ่มอายุ 45+'
        ],
        [
            'cid' => '9999900000002',
            'name' => 'สมศรี สุขสรรค์ (จำลอง)',
            'house_no' => '45/2',
            'moo' => '1',
            'village' => 'บ้านตาลสุม (จำลอง)',
            'hc' => 'รพ.สต. ตาลสุม (จำลอง)',
            'age' => 58,
            'score' => 75,
            'factors' => 'ความดันเริ่มสูง (158/96), น้ำตาลเจาะเสี่ยงสูง (175 mg/dL), มีประวัติ NCD ครอบครัว'
        ]
    ];

    $vhvImpactData = [
        ['assigned_vhv' => 'DEMO_1001', 'vhv_name' => 'อสม. สมชาย ใจดี (จำลอง)', 'hoscode' => '99999', 'village' => '1', 'total_screened' => 42, 'risk_found' => 8, 'total_dpac_enrolled' => 12, 'dpac_improved_count' => 9],
        ['assigned_vhv' => 'DEMO_1002', 'vhv_name' => 'อสม. สายสมร มีสุข (จำลอง)', 'hoscode' => '99999', 'village' => '2', 'total_screened' => 45, 'risk_found' => 10, 'total_dpac_enrolled' => 15, 'dpac_improved_count' => 11],
        ['assigned_vhv' => 'DEMO_1003', 'vhv_name' => 'อสม. บุญทัน เจริญดี (จำลอง)', 'hoscode' => '99999', 'village' => '3', 'total_screened' => 32, 'risk_found' => 6, 'total_dpac_enrolled' => 8, 'dpac_improved_count' => 6]
    ];
    $avgYieldRate = 20.2;
    $totalRiskFoundAll = 24;
    $totalScreenedAll = 119;

    $ncdTotalAnalyzed = count($ncdBeforeAfterData);
    $ncdImprovedBpCount = 2;
    $ncdImprovedDtxCount = 2;

    $totalAnalyzed = count($beforeAfterData);
    $improvedBpCount = 3;
    $improvedFbsCount = 3;
    $totalHtCases = 3;
    $totalDmCases = 3;

    $avgSbpBefore = 160.7;
    $avgSbpAfter = 136.7;
    $avgDbpBefore = 97.3;
    $avgDbpAfter = 86.0;
    $avgFbsBefore = 193.3;
    $avgFbsAfter = 142.7;

    $pctBpImprovement = 100.0;
    $pctFbsImprovement = 100.0;

    $beforeHigh = 3;
    $beforeModerate = 0;
    $beforeNormal = 0;
    $afterHigh = 0;
    $afterModerate = 3;
    $afterNormal = 0;

    $ageGroups = ['35-44 ปี', '45-54 ปี', '55-64 ปี', '65 ปีขึ้นไป', 'ไม่ทราบอายุ'];
    $maleTotal = [25, 40, 35, 20, 0];
    $maleScreened = [18, 30, 26, 16, 0];
    $femaleTotal = [30, 45, 40, 15, 0];
    $femaleScreened = [24, 36, 30, 12, 0];

    $retentionData = ['total_enrolled' => 35, 'round1_count' => 35, 'round2_count' => 28, 'round3_count' => 14, 'round4_count' => 6];
    $dropoutList = [
        [
            'enrollment_id' => 'DEMO_ENROLL_99',
            'risk_type' => 'BOTH',
            'enroll_date' => date('Y-m-d', strtotime('-90 days')),
            'cid' => '9999900000006',
            'first_name' => 'อำนวย',
            'last_name' => 'รวยรื่น (จำลอง)',
            'house_no' => '22',
            'moo' => '3',
            'hoscode' => '99999',
            'max_round' => 1,
            'last_date' => date('Y-m-d', strtotime('-45 days')),
            'days_since_last' => 45,
            'assigned_vhv' => 'อสม. บุญทัน เจริญดี (จำลอง)'
        ]
    ];
} else {
    if ($admin_hoscode) {
        $hoscodes = get_query_hoscodes($admin_hoscode);
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
    } else {
        $valid_hoscodes = get_query_hoscodes();
        $inPlaceholders = implode(',', array_fill(0, count($valid_hoscodes), '?'));
        $hoscodes = $valid_hoscodes;
    }

    // Shared analytics filters. The unit selector can only narrow the units that
    // the signed-in administrator is already allowed to access.
    $allowedHoscodes = $hoscodes;
    $selectedHoscode = trim((string)($_GET['hoscode'] ?? ''));
    if ($selectedHoscode !== '' && in_array($selectedHoscode, $allowedHoscodes, true)) {
        $hoscodes = [$selectedHoscode];
        $inPlaceholders = '?';
    }

    $availableBudgetYears = [];
    try {
        $availableBudgetYears = $pdo->query("
            SELECT DISTINCT budget_year FROM task_assignments WHERE budget_year IS NOT NULL
            UNION SELECT DISTINCT budget_year FROM dpac_enrollments WHERE budget_year IS NOT NULL
            ORDER BY budget_year DESC
        ")->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Throwable $e) {}
    $defaultBudgetYear = !empty($availableBudgetYears) ? (int)$availableBudgetYears[0] : (int)date('Y');
    $selectedBudgetYear = isset($_GET['budget_year']) && ctype_digit((string)$_GET['budget_year'])
        ? (int)$_GET['budget_year'] : $defaultBudgetYear;
    $selectedRound = isset($_GET['round']) && ctype_digit((string)$_GET['round']) ? max(0, (int)$_GET['round']) : 0;
    $dateFromInput = (string)($_GET['date_from'] ?? '');
    $dateToInput = (string)($_GET['date_to'] ?? '');
    $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromInput) ? $dateFromInput : '';
    $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToInput) ? $dateToInput : '';

    // 1. Before-After Query for DPAC progress tracking
    $beforeAfterStmt = $pdo->prepare("
        SELECT 
            e.enrollment_id,
            e.risk_type,
            p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
            f1.bp_sys AS sbp_before, f1.bp_dia AS dbp_before, f1.fbs AS fbs_before, f1.health_risk_level AS risk_before,
            fl.bp_sys AS sbp_after, fl.bp_dia AS dbp_after, fl.fbs AS fbs_after, fl.health_risk_level AS risk_after,
            fl.round_number AS latest_round
        FROM dpac_enrollments e
        JOIN target_population p ON e.cid = p.cid
        JOIN dpac_followups f1 ON e.enrollment_id = f1.enrollment_id AND f1.round_number = 1 AND f1.status = 'completed' AND f1.is_sandbox = ?
        JOIN dpac_followups fl ON e.enrollment_id = fl.enrollment_id AND fl.status = 'completed' AND fl.is_sandbox = ?
        JOIN (
            SELECT enrollment_id, MAX(round_number) as max_round
            FROM dpac_followups
            WHERE status = 'completed' AND is_sandbox = ?
            GROUP BY enrollment_id
        ) max_f ON fl.enrollment_id = max_f.enrollment_id AND fl.round_number = max_f.max_round
        WHERE max_f.max_round > 1 AND e.budget_year = ? AND p.hoscode IN ($inPlaceholders)
        ORDER BY fl.completed_at DESC
    ");
    $beforeAfterStmt->execute(array_merge([$isSandboxVal, $isSandboxVal, $isSandboxVal, $selectedBudgetYear], $hoscodes));
    $beforeAfterData = $beforeAfterStmt->fetchAll(PDO::FETCH_ASSOC);

    // NCD Multi-Round Re-screening Before-After Query (Round 1 vs Latest Round)
    $ncdBeforeAfterStmt = $pdo->prepare("
        SELECT 
            p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
            s1.sys_bp1 AS sbp_r1, s1.dia_bp1 AS dbp_r1, s1.dtx_value AS dtx_r1, s1.bmi AS bmi_r1, s1.created_at AS date_r1,
            sl.sys_bp1 AS sbp_latest, sl.dia_bp1 AS dbp_latest, sl.dtx_value AS dtx_latest, sl.bmi AS bmi_latest, sl.created_at AS date_latest,
            al.round_number AS latest_round
        FROM target_population p
        JOIN task_assignments a1 ON p.cid = a1.target_cid AND a1.round_number = 1 AND a1.assignment_status = 'completed' AND a1.budget_year = ? AND a1.is_sandbox = ?
        JOIN screening_results s1 ON a1.assignment_id = s1.assignment_id AND s1.is_sandbox = ?
        JOIN task_assignments al ON p.cid = al.target_cid AND al.round_number > 1 AND al.assignment_status = 'completed' AND al.budget_year = ? AND al.is_sandbox = ?
        JOIN screening_results sl ON al.assignment_id = sl.assignment_id AND sl.is_sandbox = ?
        JOIN (
            SELECT target_cid, MAX(round_number) as max_round
            FROM task_assignments
            WHERE assignment_status = 'completed' AND budget_year = ? AND is_sandbox = ?
            GROUP BY target_cid
        ) max_a ON al.target_cid = max_a.target_cid AND al.round_number = max_a.max_round
        WHERE (? = 0 OR al.round_number = ?)
          AND (? = '' OR DATE(sl.created_at) >= ?)
          AND (? = '' OR DATE(sl.created_at) <= ?)
          AND p.hoscode IN ($inPlaceholders)
        ORDER BY sl.created_at DESC
    ");
    $ncdBeforeAfterStmt->execute(array_merge([
        $selectedBudgetYear, $isSandboxVal, $isSandboxVal,
        $selectedBudgetYear, $isSandboxVal, $isSandboxVal,
        $selectedBudgetYear, $isSandboxVal,
        $selectedRound, $selectedRound,
        $dateFrom, $dateFrom, $dateTo, $dateTo
    ], $hoscodes));
    $ncdBeforeAfterData = $ncdBeforeAfterStmt->fetchAll(PDO::FETCH_ASSOC);

    // Operational funnel and action queues for the selected budget year.
$funnelData = ['total_targets' => 0, 'r1_completed' => 0, 'r2_assigned' => 0, 'r2_completed' => 0, 'r3_completed' => 0];
$actionCounts = ['overdue' => 0, 'awaiting_r2' => 0, 'high_risk_unassigned' => 0];
$overdueActionRows = [];
$dataQuality = ['missing_body' => 0, 'missing_vitals' => 0, 'missing_geo' => 0, 'round_mismatch' => 0, 'orphan_results' => 0, 'mock_cid' => 0];
try {
    $funnelStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.cid) AS total_targets,
               COUNT(DISTINCT CASE WHEN ta.round_number = 1 AND ta.assignment_status = 'completed' THEN p.cid END) AS r1_completed,
               COUNT(DISTINCT CASE WHEN ta.round_number = 2 THEN p.cid END) AS r2_assigned,
               COUNT(DISTINCT CASE WHEN ta.round_number = 2 AND ta.assignment_status = 'completed' THEN p.cid END) AS r2_completed,
               COUNT(DISTINCT CASE WHEN ta.round_number >= 3 AND ta.assignment_status = 'completed' THEN p.cid END) AS r3_completed
        FROM target_population p
        LEFT JOIN task_assignments ta ON ta.target_cid = p.cid AND ta.budget_year = ? AND ta.is_sandbox = ?
        WHERE p.hoscode IN ($inPlaceholders) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ");
    $funnelStmt->execute(array_merge([$selectedBudgetYear, $isSandboxVal], $hoscodes));
    $funnelData = array_merge($funnelData, $funnelStmt->fetch(PDO::FETCH_ASSOC) ?: []);

    $overdueStmt = $pdo->prepare("SELECT COUNT(DISTINCT ta.assignment_id) FROM task_assignments ta JOIN target_population p ON p.cid=ta.target_cid WHERE ta.budget_year=? AND ta.is_sandbox=? AND ta.assignment_status='pending' AND ta.round_number>=2 AND ta.assigned_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND p.hoscode IN ($inPlaceholders)");
    $overdueStmt->execute(array_merge([$selectedBudgetYear, $isSandboxVal], $hoscodes));
    $actionCounts['overdue'] = (int)$overdueStmt->fetchColumn();
    $overdueListStmt = $pdo->prepare("
        SELECT ta.assignment_id, ta.round_number, ta.assigned_at,
               p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
               v.vhv_name, DATEDIFF(NOW(), ta.assigned_at) AS waiting_days
        FROM task_assignments ta
        JOIN target_population p ON p.cid=ta.target_cid
        LEFT JOIN vhv_users v ON v.vhv_id=ta.vhv_id
        WHERE ta.budget_year=? AND ta.is_sandbox=? AND ta.assignment_status='pending'
          AND ta.round_number>=2 AND ta.assigned_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND p.hoscode IN ($inPlaceholders)
        ORDER BY ta.assigned_at ASC LIMIT 10
    ");
    $overdueListStmt->execute(array_merge([$selectedBudgetYear, $isSandboxVal], $hoscodes));
    $overdueActionRows = $overdueListStmt->fetchAll(PDO::FETCH_ASSOC);

    $awaitingStmt = $pdo->prepare("SELECT COUNT(DISTINCT a1.target_cid) FROM task_assignments a1 JOIN target_population p ON p.cid=a1.target_cid WHERE a1.budget_year=? AND a1.is_sandbox=? AND a1.round_number=1 AND a1.assignment_status='completed' AND p.hoscode IN ($inPlaceholders) AND NOT EXISTS (SELECT 1 FROM task_assignments a2 WHERE a2.target_cid=a1.target_cid AND a2.budget_year=a1.budget_year AND a2.is_sandbox=a1.is_sandbox AND a2.round_number>=2)");
    $awaitingStmt->execute(array_merge([$selectedBudgetYear, $isSandboxVal], $hoscodes));
    $actionCounts['awaiting_r2'] = (int)$awaitingStmt->fetchColumn();

    $highStmt = $pdo->prepare("SELECT COUNT(DISTINCT COALESCE(sr.target_cid,ta.target_cid)) FROM screening_results sr JOIN task_assignments ta ON ta.assignment_id=sr.assignment_id JOIN target_population p ON p.cid=COALESCE(sr.target_cid,ta.target_cid) WHERE ta.budget_year=? AND ta.is_sandbox=? AND sr.is_sandbox=? AND p.hoscode IN ($inPlaceholders) AND (sr.cv_risk_score>=10 OR sr.sys_bp1>=140 OR sr.dia_bp1>=90 OR sr.dtx_value>=126) AND NOT EXISTS (SELECT 1 FROM task_assignments nx WHERE nx.target_cid=ta.target_cid AND nx.budget_year=ta.budget_year AND nx.is_sandbox=ta.is_sandbox AND nx.round_number>ta.round_number)");
    $highStmt->execute(array_merge([$selectedBudgetYear, $isSandboxVal, $isSandboxVal], $hoscodes));
    $actionCounts['high_risk_unassigned'] = (int)$highStmt->fetchColumn();

    $qualityStmt = $pdo->prepare("
        SELECT
          COUNT(DISTINCT CASE WHEN sr.weight IS NULL OR sr.height IS NULL OR sr.waist IS NULL THEN sr.screening_id END) AS missing_body,
          COUNT(DISTINCT CASE WHEN sr.sys_bp1 IS NULL AND sr.dia_bp1 IS NULL AND sr.dtx_value IS NULL THEN sr.screening_id END) AS missing_vitals,
          COUNT(DISTINCT CASE WHEN sr.screening_lat IS NULL OR sr.screening_lng IS NULL OR sr.screening_lat=0 OR sr.screening_lng=0 THEN sr.screening_id END) AS missing_geo,
          COUNT(DISTINCT CASE WHEN COALESCE(sr.round_number,1) <> COALESCE(ta.round_number,1) THEN sr.screening_id END) AS round_mismatch
        FROM screening_results sr
        JOIN task_assignments ta ON ta.assignment_id=sr.assignment_id
        JOIN target_population p ON p.cid=ta.target_cid
        WHERE ta.budget_year=? AND ta.is_sandbox=? AND sr.is_sandbox=?
          AND (?=0 OR ta.round_number=?)
          AND (?='' OR DATE(sr.created_at)>=?) AND (?='' OR DATE(sr.created_at)<=?)
          AND p.hoscode IN ($inPlaceholders)
    ");
    $qualityStmt->execute(array_merge([$selectedBudgetYear, $isSandboxVal, $isSandboxVal, $selectedRound, $selectedRound, $dateFrom, $dateFrom, $dateTo, $dateTo], $hoscodes));
    $dataQuality = array_merge($dataQuality, $qualityStmt->fetch(PDO::FETCH_ASSOC) ?: []);

    $orphanStmt = $pdo->prepare("SELECT COUNT(*) FROM screening_results sr LEFT JOIN task_assignments ta ON ta.assignment_id=sr.assignment_id LEFT JOIN target_population p ON p.cid=sr.target_cid WHERE sr.is_sandbox=? AND ta.assignment_id IS NULL AND p.hoscode IN ($inPlaceholders)");
    $orphanStmt->execute(array_merge([$isSandboxVal], $hoscodes));
    $dataQuality['orphan_results'] = (int)$orphanStmt->fetchColumn();
    $mockStmt = $pdo->prepare("SELECT COUNT(*) FROM target_population p WHERE p.hoscode IN ($inPlaceholders) AND (p.cid LIKE '%*%' OR p.cid LIKE '0%' OR p.cid=CONCAT(LPAD(p.hoscode,5,'0'),LPAD(p.pid,8,'0')))");
    $mockStmt->execute($hoscodes);
    $dataQuality['mock_cid'] = (int)$mockStmt->fetchColumn();
} catch (\Throwable $e) {
    // Keep the page usable on installations that have not received every optional column yet.
}

$ncdTotalAnalyzed = count($ncdBeforeAfterData);
$ncdImprovedBpCount = 0;
$ncdImprovedDtxCount = 0;

foreach ($ncdBeforeAfterData as $row) {
    if ($row['sbp_r1'] !== null && $row['sbp_latest'] !== null && $row['sbp_latest'] < $row['sbp_r1']) {
        $ncdImprovedBpCount++;
    }
    if ($row['dtx_r1'] !== null && $row['dtx_latest'] !== null && $row['dtx_latest'] < $row['dtx_r1']) {
        $ncdImprovedDtxCount++;
    }
}
$totalAnalyzed = count($beforeAfterData);
$improvedBpCount = 0;
$improvedFbsCount = 0;
$totalHtCases = 0;
$totalDmCases = 0;

$sbpBeforeSum = 0;
$sbpAfterSum = 0;
$dbpBeforeSum = 0;
$dbpAfterSum = 0;
$fbsBeforeSum = 0;
$fbsAfterSum = 0;

$highToHigh = 0;
$highToModerate = 0;
$highToNormal = 0;
$moderateToHigh = 0;
$moderateToModerate = 0;
$moderateToNormal = 0;
$normalToHigh = 0;
$normalToModerate = 0;
$normalToNormal = 0;

foreach ($beforeAfterData as $row) {
    $hasHt = in_array($row['risk_type'], ['HT', 'BOTH']);
    $hasDm = in_array($row['risk_type'], ['DM', 'BOTH']);

    if ($hasHt && !empty($row['sbp_before']) && !empty($row['sbp_after'])) {
        $totalHtCases++;
        $sbpBeforeSum += $row['sbp_before'];
        $sbpAfterSum += $row['sbp_after'];
        $dbpBeforeSum += $row['dbp_before'];
        $dbpAfterSum += $row['dbp_after'];

        if ($row['sbp_after'] < $row['sbp_before'] || ($row['sbp_after'] < 140 && $row['dbp_after'] < 90)) {
            $improvedBpCount++;
        }
    }

    if ($hasDm && !empty($row['fbs_before']) && !empty($row['fbs_after'])) {
        $totalDmCases++;
        $fbsBeforeSum += $row['fbs_before'];
        $fbsAfterSum += $row['fbs_after'];

        if ($row['fbs_after'] < $row['fbs_before'] || $row['fbs_after'] < 126) {
            $improvedFbsCount++;
        }
    }

    $before = $row['risk_before'];
    $after = $row['risk_after'];

    if ($before === 'เสี่ยงสูง') {
        if ($after === 'เสี่ยงสูง') $highToHigh++;
        elseif ($after === 'เสี่ยง') $highToModerate++;
        else $highToNormal++;
    } elseif ($before === 'เสี่ยง') {
        if ($after === 'เสี่ยงสูง') $moderateToHigh++;
        elseif ($after === 'เสี่ยง') $moderateToModerate++;
        else $moderateToNormal++;
    } else {
        if ($after === 'เสี่ยงสูง') $normalToHigh++;
        elseif ($after === 'เสี่ยง') $normalToModerate++;
        else $normalToNormal++;
    }
}

$avgSbpBefore = $totalHtCases > 0 ? round($sbpBeforeSum / $totalHtCases, 1) : 0;
$avgSbpAfter = $totalHtCases > 0 ? round($sbpAfterSum / $totalHtCases, 1) : 0;
$avgDbpBefore = $totalHtCases > 0 ? round($dbpBeforeSum / $totalHtCases, 1) : 0;
$avgDbpAfter = $totalHtCases > 0 ? round($dbpAfterSum / $totalHtCases, 1) : 0;

$avgFbsBefore = $totalDmCases > 0 ? round($fbsBeforeSum / $totalDmCases, 1) : 0;
$avgFbsAfter = $totalDmCases > 0 ? round($fbsAfterSum / $totalDmCases, 1) : 0;

$pctBpImprovement = $totalHtCases > 0 ? round(($improvedBpCount / $totalHtCases) * 100, 1) : 0;
$pctFbsImprovement = $totalDmCases > 0 ? round(($improvedFbsCount / $totalDmCases) * 100, 1) : 0;

// Risk evaluation matrix counts
$beforeHigh = $highToHigh + $highToModerate + $highToNormal;
$beforeModerate = $moderateToHigh + $moderateToModerate + $moderateToNormal;
$beforeNormal = $normalToHigh + $normalToModerate + $normalToNormal;

$afterHigh = $highToHigh + $moderateToHigh + $normalToHigh;
$afterModerate = $highToModerate + $moderateToModerate + $normalToModerate;
$afterNormal = $highToNormal + $moderateToNormal + $normalToNormal;

// 2. Risk Reduction Rate by Village (moo)
$villageImprovementStmt = $pdo->prepare("
    SELECT 
        p.hoscode, p.moo,
        COUNT(DISTINCT e.enrollment_id) as total_enrolled,
        SUM(CASE WHEN max_f.max_round > 1 THEN 1 ELSE 0 END) as completed_followups,
        SUM(CASE 
            WHEN max_f.max_round > 1 AND (
                (f1.health_risk_level = 'เสี่ยงสูง' AND fl.health_risk_level IN ('เสี่ยง', 'ปกติ')) OR
                (f1.health_risk_level = 'เสี่ยง' AND fl.health_risk_level = 'ปกติ') OR
                (f1.bp_sys > fl.bp_sys AND f1.bp_sys >= 140) OR
                (f1.fbs > fl.fbs AND f1.fbs >= 126)
            ) THEN 1 ELSE 0 
        END) as improved_count
    FROM dpac_enrollments e
    JOIN target_population p ON e.cid = p.cid
    LEFT JOIN dpac_followups f1 ON e.enrollment_id = f1.enrollment_id AND f1.round_number = 1 AND f1.status = 'completed'
    LEFT JOIN dpac_followups fl ON e.enrollment_id = fl.enrollment_id AND fl.status = 'completed'
    LEFT JOIN (
        SELECT enrollment_id, MAX(round_number) as max_round
        FROM dpac_followups
        WHERE status = 'completed'
        GROUP BY enrollment_id
    ) max_f ON fl.enrollment_id = max_f.enrollment_id AND fl.round_number = max_f.max_round
    WHERE p.hoscode IN ($inPlaceholders) AND CAST(p.moo AS UNSIGNED) > 0
    GROUP BY p.hoscode, p.moo
    ORDER BY p.hoscode, p.moo
");
$villageImprovementStmt->execute($hoscodes);
$villageImprovementData = $villageImprovementStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Temporal Heatmap Data (Screens & DPAC followups)
$historyStmt = $pdo->prepare("
    SELECT 
        p.cid, p.latitude, p.longitude,
        combined.risk_level, combined.created_at
    FROM (
        SELECT a.target_cid,
               CASE 
                   WHEN (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) THEN 'HIGH'
                   WHEN ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 'MODERATE'
                   ELSE 'NORMAL'
               END AS risk_level,
               s.created_at
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        WHERE a.assignment_status = 'completed'

        UNION ALL

        SELECT e.cid AS target_cid,
               CASE 
                   WHEN f.health_risk_level = 'เสี่ยงสูง' THEN 'HIGH'
                   WHEN f.health_risk_level = 'เสี่ยง' THEN 'MODERATE'
                   ELSE 'NORMAL'
               END AS risk_level,
               f.completed_at AS created_at
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        WHERE f.status = 'completed'
    ) AS combined
    JOIN target_population p ON combined.target_cid = p.cid
    WHERE p.latitude IS NOT NULL AND p.longitude IS NOT NULL AND p.hoscode IN ($inPlaceholders)
    ORDER BY combined.created_at ASC
");
$historyStmt->execute($hoscodes);
$historyRecords = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Unique targets with coordinates for the map
$mapTargetsStmt = $pdo->prepare("
    SELECT cid, first_name, last_name, house_no, moo, hoscode, latitude, longitude
    FROM target_population
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND hoscode IN ($inPlaceholders)
");
$mapTargetsStmt->execute($hoscodes);
$mapTargets = $mapTargetsStmt->fetchAll(PDO::FETCH_ASSOC);

// Map centroid calculation (filtered to Tansum District boundaries to ignore incorrect coordinate entries)
$latSum = 0;
$lngSum = 0;
$coordCount = 0;
foreach ($mapTargets as $t) {
    if ($t['latitude'] && $t['longitude']) {
        $lat = floatval($t['latitude']);
        $lng = floatval($t['longitude']);
        // Bounding box for Tansum, Ubon Ratchathani (Lat: 15.1 to 15.6, Lng: 104.8 to 105.3)
        if ($lat >= 15.1 && $lat <= 15.6 && $lng >= 104.8 && $lng <= 105.3) {
            $latSum += $lat;
            $lngSum += $lng;
            $coordCount++;
        }
    }
}
$mapCenterLat = $coordCount > 0 ? $latSum / $coordCount : 15.4294;
$mapCenterLng = $coordCount > 0 ? $lngSum / $coordCount : 104.9922;
$mapInitialZoom = $coordCount > 0 ? 13 : 12;

// 4. Spatial Prevalence Analysis (Prevalence Hotspots)
$spatialPrevalenceStmt = $pdo->prepare("
    SELECT 
        p.hoscode, p.moo,
        COUNT(DISTINCT a.assignment_id) as total_screened,
        SUM(CASE WHEN (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) THEN 1 ELSE 0 END) as high_risk,
        SUM(CASE WHEN NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) 
                  AND ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 1 ELSE 0 END) as moderate_risk
    FROM task_assignments a
    JOIN target_population p ON a.target_cid = p.cid
    JOIN screening_results s ON a.assignment_id = s.assignment_id
    WHERE a.assignment_status = 'completed' AND p.hoscode IN ($inPlaceholders) AND CAST(p.moo AS UNSIGNED) > 0
    GROUP BY p.hoscode, p.moo
");
$spatialPrevalenceStmt->execute($hoscodes);
$spatialPrevalenceData = $spatialPrevalenceStmt->fetchAll(PDO::FETCH_ASSOC);

$prevalenceList = [];
foreach ($spatialPrevalenceData as $row) {
    $total_screened = intval($row['total_screened']);
    if ($total_screened === 0) continue;

    $risk_count = intval($row['high_risk']) + intval($row['moderate_risk']);
    $rate = ($risk_count / $total_screened) * 100;

    $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($hoscode_villages[$row['hoscode']]['tambon'], $row['moo']);
    $village_name = $village_only ?: 'หมู่ที่ ' . $row['moo'];

    $display_name = $admin_hoscode ? $village_name : ($hc_names[$row['hoscode']] ?? $row['hoscode']) . " (" . $village_name . ")";

    $prevalenceList[] = [
        'name' => $display_name,
        'total_screened' => $total_screened,
        'risk_count' => $risk_count,
        'rate' => $rate
    ];
}

// Sort by rate DESC for highest prevalence hotspots
usort($prevalenceList, function ($a, $b) {
    return $b['rate'] <=> $a['rate'];
});
$highestPrevalence = array_slice($prevalenceList, 0, 3);

$improvementList = [];
foreach ($villageImprovementData as $row) {
    $completed = intval($row['completed_followups']);
    if ($completed === 0) continue;

    $improved = intval($row['improved_count']);
    $rate = ($improved / $completed) * 100;

    $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($hoscode_villages[$row['hoscode']]['tambon'], $row['moo']);
    $village_name = $village_only ?: 'หมู่ที่ ' . $row['moo'];

    $display_name = $admin_hoscode ? $village_name : ($hc_names[$row['hoscode']] ?? $row['hoscode']) . " (" . $village_name . ")";

    $improvementList[] = [
        'name' => $display_name,
        'completed' => $completed,
        'improved' => $improved,
        'rate' => $rate
    ];
}

// Sort by rate DESC for best improvement
usort($improvementList, function ($a, $b) {
    return $b['rate'] <=> $a['rate'];
});
$bestImprovement = array_slice($improvementList, 0, 3);

// Sort by rate ASC for concerning areas (lowest improvement rate)
$tempList = $improvementList;
usort($tempList, function ($a, $b) {
    return $a['rate'] <=> $b['rate'];
});
$concerningAreas = array_slice($tempList, 0, 3);


// 5. Monthly Trend data for forecasting
$monthlyTrendStmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(combined.created_at, '%Y-%m') as month_year,
        SUM(CASE WHEN combined.risk_level = 'HIGH' THEN 1 ELSE 0 END) as high_risk,
        SUM(CASE WHEN combined.risk_level = 'MODERATE' THEN 1 ELSE 0 END) as moderate_risk
    FROM (
        SELECT s.created_at,
               CASE 
                   WHEN (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) THEN 'HIGH'
                   WHEN ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 'MODERATE'
                   ELSE 'NORMAL'
               END AS risk_level
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.assignment_status = 'completed' AND p.hoscode IN ($inPlaceholders)

        UNION ALL

        SELECT f.completed_at AS created_at,
               CASE 
                   WHEN f.health_risk_level = 'เสี่ยงสูง' THEN 'HIGH'
                   WHEN f.health_risk_level = 'เสี่ยง' THEN 'MODERATE'
                   ELSE 'NORMAL'
               END AS risk_level
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        JOIN target_population p ON e.cid = p.cid
        WHERE f.status = 'completed' AND p.hoscode IN ($inPlaceholders)
    ) AS combined
    GROUP BY DATE_FORMAT(combined.created_at, '%Y-%m')
    ORDER BY month_year ASC
");
$monthlyTrendStmt->execute(array_merge($hoscodes, $hoscodes));
$monthlyTrend = $monthlyTrendStmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// R2R Satisfaction Survey Stats Calculation
// ==========================================
$surveyStats = [
    'count' => 0,
    'peou_mean' => 0,
    'peou_sd' => 0,
    'sq_mean' => 0,
    'sq_sd' => 0,
    'iq_mean' => 0,
    'iq_sd' => 0,
    'pu_mean' => 0,
    'pu_sd' => 0,
    'bi_mean' => 0,
    'bi_sd' => 0,
    'total_mean' => 0,
    'total_sd' => 0
];

$tagsCount = [];

try {
    // Query survey responses for current clinics (hoscodes)
    $surveyStmt = $pdo->prepare("
        SELECT score_peou, score_sq, score_iq, score_pu, score_bi, selected_tags
        FROM vhv_surveys
        WHERE hoscode IN ($inPlaceholders)
    ");
    $surveyStmt->execute($hoscodes);
    $surveyResponses = $surveyStmt->fetchAll(PDO::FETCH_ASSOC);

    $surveyStats['count'] = count($surveyResponses);

    if ($surveyStats['count'] > 0) {
        $peous = [];
        $sqs = [];
        $iqs = [];
        $pus = [];
        $bis = [];
        $totals = [];

        foreach ($surveyResponses as $sr) {
            $peou = intval($sr['score_peou']);
            $sq = intval($sr['score_sq']);
            $iq = intval($sr['score_iq']);
            $pu = intval($sr['score_pu']);
            $bi = intval($sr['score_bi']);

            $peous[] = $peou;
            $sqs[] = $sq;
            $iqs[] = $iq;
            $pus[] = $pu;
            $bis[] = $bi;

            // Total score per response is average of the 5 aspects
            $totals[] = ($peou + $sq + $iq + $pu + $bi) / 5;

            // Count tags
            $tagsList = json_decode($sr['selected_tags'] ?? '[]', true);
            if (is_array($tagsList)) {
                foreach ($tagsList as $tag) {
                    $tagsCount[$tag] = ($tagsCount[$tag] ?? 0) + 1;
                }
            }
        }

        // Helper function to calculate mean and sample standard deviation
        $calcStats = function ($arr) {
            $n = count($arr);
            if ($n === 0) return ['mean' => 0, 'sd' => 0];
            $mean = array_sum($arr) / $n;

            $variance = 0;
            if ($n > 1) {
                $sum_sq = 0;
                foreach ($arr as $x) {
                    $sum_sq += pow($x - $mean, 2);
                }
                $variance = $sum_sq / ($n - 1);
            }
            $sd = sqrt($variance);
            return ['mean' => $mean, 'sd' => $sd];
        };

        $statsPeou = $calcStats($peous);
        $statsSq = $calcStats($sqs);
        $statsIq = $calcStats($iqs);
        $statsPu = $calcStats($pus);
        $statsBi = $calcStats($bis);
        $statsTotal = $calcStats($totals);

        $surveyStats['peou_mean'] = $statsPeou['mean'];
        $surveyStats['peou_sd'] = $statsPeou['sd'];
        $surveyStats['sq_mean'] = $statsSq['mean'];
        $surveyStats['sq_sd'] = $statsSq['sd'];
        $surveyStats['iq_mean'] = $statsIq['mean'];
        $surveyStats['iq_sd'] = $statsIq['sd'];
        $surveyStats['pu_mean'] = $statsPu['mean'];
        $surveyStats['pu_sd'] = $statsPu['sd'];
        $surveyStats['bi_mean'] = $statsBi['mean'];
        $surveyStats['bi_sd'] = $statsBi['sd'];
        $surveyStats['total_mean'] = $statsTotal['mean'];
        $surveyStats['total_sd'] = $statsTotal['sd'];

        // Sort tags by frequency
        arsort($tagsCount);
    }
} catch (\Throwable $e) {
    // Fail silently
}

// =========================================================================
// 6. Predictive Disease Conversion Risk (Moderate risk targets at risk of turning into new NCD cases)
// =========================================================================
$predictiveRaw = [];
try {
    $predictiveStmt = $pdo->prepare("
        SELECT 
            p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
            CASE WHEN p.birth IS NOT NULL AND p.birth != '0000-00-00' THEN TIMESTAMPDIFF(YEAR, p.birth, CURRENT_DATE) END AS age,
            s.sys_bp1, s.dia_bp1, s.dtx_value, s.bmi, s.family_history, s.created_at AS screen_date
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.assignment_status = 'completed'
          AND a.budget_year = ? AND a.is_sandbox = ? AND s.is_sandbox = ?
          AND (? = 0 OR a.round_number = ?)
          AND (? = '' OR DATE(s.created_at) >= ?)
          AND (? = '' OR DATE(s.created_at) <= ?)
          AND p.hoscode IN ($inPlaceholders)
          AND (
              (s.sys_bp1 >= 120 AND s.sys_bp1 < 140) OR 
              (s.dia_bp1 >= 80 AND s.dia_bp1 < 90) OR 
              (s.dtx_value >= 100 AND s.dtx_value < 126) OR
              p.health_status_origin = 'NORMAL' OR
              p.need_screen_dm = 1 OR p.need_screen_ht = 1
          )
        ORDER BY s.created_at DESC
    ");
    $predictiveStmt->execute(array_merge([$selectedBudgetYear, $isSandboxVal, $isSandboxVal, $selectedRound, $selectedRound, $dateFrom, $dateFrom, $dateTo, $dateTo], $hoscodes));
    $predictiveRaw = $predictiveStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $ex) {
    try {
        $predictiveStmt = $pdo->prepare("
            SELECT 
                p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
                CASE WHEN p.birth IS NOT NULL AND p.birth != '0000-00-00' THEN TIMESTAMPDIFF(YEAR, p.birth, CURRENT_DATE) END AS age,
                s.sys_bp1, s.dia_bp1, s.dtx_value, s.bmi, s.family_history, s.created_at AS screen_date
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            LEFT JOIN screening_results s ON a.assignment_id = s.assignment_id
            WHERE a.assignment_status = 'completed'
              AND a.budget_year = ? AND a.is_sandbox = ?
              AND (? = 0 OR a.round_number = ?)
              AND (? = '' OR DATE(s.created_at) >= ?)
              AND (? = '' OR DATE(s.created_at) <= ?)
              AND p.hoscode IN ($inPlaceholders)
            ORDER BY a.assignment_id DESC
            LIMIT 50
        ");
        $predictiveStmt->execute(array_merge([$selectedBudgetYear, $isSandboxVal, $selectedRound, $selectedRound, $dateFrom, $dateFrom, $dateTo, $dateTo], $hoscodes));
        $predictiveRaw = $predictiveStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e2) {}
}

$conversionRiskList = [];
$highConversionCount = 0;
$seenCids = [];

foreach ($predictiveRaw as $row) {
    if (isset($seenCids[$row['cid']])) continue;
    $seenCids[$row['cid']] = true;

    $score = 35; // Base risk score for targets under active monitoring
    $factors = [];
    
    $sys = floatval($row['sys_bp1'] ?? 0);
    $dia = floatval($row['dia_bp1'] ?? 0);
    $dtx = floatval($row['dtx_value'] ?? 0);
    $bmi = floatval($row['bmi'] ?? 0);
    
    if ($sys >= 130 || $dia >= 85) {
        $score += 25;
        $factors[] = "ความดันเริ่มสูง (" . intval($sys) . "/" . intval($dia) . ")";
    } elseif ($sys >= 120 || $dia >= 80) {
        $score += 15;
        $factors[] = "ความดันโซนเฝ้าระวัง (" . intval($sys) . "/" . intval($dia) . ")";
    }

    if ($dtx >= 110) {
        $score += 25;
        $factors[] = "น้ำตาลเจาะเสี่ยงสูง (" . intval($dtx) . " mg/dL)";
    } elseif ($dtx >= 100) {
        $score += 15;
        $factors[] = "น้ำตาลโซนเฝ้าระวัง (" . intval($dtx) . " mg/dL)";
    }

    if ($bmi >= 25) {
        $score += 15;
        $factors[] = "BMI น้ำหนักเกิน (" . number_format($bmi, 1) . ")";
    }

    if (!empty($row['family_history']) && !in_array($row['family_history'], ['NONE', 'ไม่มี', '0', ''])) {
        $score += 10;
        $factors[] = "มีประวัติ NCD ครอบครัว";
    }

    $age = isset($row['age']) ? (int)$row['age'] : null;
    if ($age !== null && $age >= 45) {
        $score += 10;
        $factors[] = "กลุ่มอายุ 45+";
    }

    $score = min(95, $score);

    if ($score >= 50) {
        $highConversionCount++;
    }

    $v_name = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($hoscode_villages[$row['hoscode']]['tambon'] ?? '', $row['moo']);

    $conversionRiskList[] = [
        'cid' => $row['cid'],
        'name' => trim($row['first_name'] . ' ' . $row['last_name']),
        'house_no' => $row['house_no'],
        'moo' => $row['moo'],
        'village' => $v_name ?: 'หมู่ที่ ' . $row['moo'],
        'hc' => $hc_names[$row['hoscode']] ?? $row['hoscode'],
        'age' => $age,
        'score' => $score,
        'factors' => !empty($factors) ? implode(', ', $factors) : 'มีประวัติเฝ้าระวังพฤติกรรมสุขภาพ'
    ];
}

usort($conversionRiskList, function($a, $b) {
    return $b['score'] <=> $a['score'];
});
$topConversionRisks = array_slice($conversionRiskList, 0, 10);

// =========================================================================
// 7. VHV Quality & Impact Analytics
// =========================================================================
$vhvImpactData = [];
try {
    $vhvImpactStmt = $pdo->prepare("
        SELECT 
            a.assigned_vhv,
            COALESCE(v.vhv_name, u.full_name, a.assigned_vhv) AS vhv_name,
            COALESCE(v.hoscode, u.hoscode, p.hoscode) AS hoscode,
            COALESCE(v.vhv_moo, u.village, p.moo) AS village,
            COUNT(DISTINCT a.assignment_id) as total_screened,
            SUM(CASE WHEN (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) THEN 1 ELSE 0 END) as risk_found,
            COUNT(DISTINCT e.enrollment_id) as total_dpac_enrolled,
            SUM(CASE 
                WHEN max_f.max_round > 1 AND (
                    (f1.health_risk_level = 'เสี่ยงสูง' AND fl.health_risk_level IN ('เสี่ยง', 'ปกติ')) OR
                    (f1.health_risk_level = 'เสี่ยง' AND fl.health_risk_level = 'ปกติ') OR
                    (f1.bp_sys > fl.bp_sys AND f1.bp_sys >= 140) OR
                    (f1.fbs > fl.fbs AND f1.fbs >= 126)
                ) THEN 1 ELSE 0
            END) as dpac_improved_count
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        LEFT JOIN screening_results s ON a.assignment_id = s.assignment_id
        LEFT JOIN vhvs v ON (a.assigned_vhv = v.vhv_name OR a.assigned_vhv = v.vhv_id)
        LEFT JOIN users u ON (a.assigned_vhv = u.full_name OR a.assigned_vhv = u.username)
        LEFT JOIN dpac_enrollments e ON a.target_cid = e.cid
        LEFT JOIN dpac_followups f1 ON e.enrollment_id = f1.enrollment_id AND f1.round_number = 1 AND f1.status = 'completed'
        LEFT JOIN dpac_followups fl ON e.enrollment_id = fl.enrollment_id AND fl.status = 'completed'
        LEFT JOIN (
            SELECT enrollment_id, MAX(round_number) as max_round
            FROM dpac_followups
            WHERE status = 'completed'
            GROUP BY enrollment_id
        ) max_f ON fl.enrollment_id = max_f.enrollment_id AND fl.round_number = max_f.max_round
        WHERE a.assignment_status = 'completed' 
          AND a.assigned_vhv IS NOT NULL 
          AND a.assigned_vhv != ''
          AND p.hoscode IN ($inPlaceholders)
        GROUP BY a.assigned_vhv
        ORDER BY dpac_improved_count DESC, risk_found DESC, total_screened DESC
        LIMIT 5
    ");
    $vhvImpactStmt->execute($hoscodes);
    $vhvImpactData = $vhvImpactStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

$totalScreenedAll = 0;
$totalRiskFoundAll = 0;
foreach ($vhvImpactData as $vi) {
    $totalScreenedAll += intval($vi['total_screened']);
    $totalRiskFoundAll += intval($vi['risk_found']);
}
$avgYieldRate = $totalScreenedAll > 0 ? round(($totalRiskFoundAll / $totalScreenedAll) * 100, 1) : 0;

// =========================================================================
// 8. Age-Gender Pyramid & Screening Gap
// =========================================================================
$ageGroups = ['35-44 ปี', '45-54 ปี', '55-64 ปี', '65 ปีขึ้นไป', 'ไม่ทราบอายุ'];
$maleTotal = array_fill(0, count($ageGroups), 0);
$maleScreened = array_fill(0, count($ageGroups), 0);
$femaleTotal = array_fill(0, count($ageGroups), 0);
$femaleScreened = array_fill(0, count($ageGroups), 0);


try {
    $pyramidStmt = $pdo->prepare("
        SELECT 
            COALESCE(p.sex, '1') as sex,
            CASE 
                WHEN (p.birth IS NOT NULL AND p.birth != '0000-00-00' AND TIMESTAMPDIFF(YEAR, p.birth, CURRENT_DATE) BETWEEN 35 AND 44) THEN '35-44 ปี'
                WHEN (p.birth IS NOT NULL AND p.birth != '0000-00-00' AND TIMESTAMPDIFF(YEAR, p.birth, CURRENT_DATE) BETWEEN 45 AND 54) THEN '45-54 ปี'
                WHEN (p.birth IS NOT NULL AND p.birth != '0000-00-00' AND TIMESTAMPDIFF(YEAR, p.birth, CURRENT_DATE) BETWEEN 55 AND 64) THEN '55-64 ปี'
                WHEN (p.birth IS NOT NULL AND p.birth != '0000-00-00' AND TIMESTAMPDIFF(YEAR, p.birth, CURRENT_DATE) >= 65) THEN '65 ปีขึ้นไป'
                ELSE 'ไม่ทราบอายุ'
            END AS age_group,
            COUNT(DISTINCT p.cid) as total_target,
            COUNT(DISTINCT CASE WHEN a.assignment_status = 'completed' THEN p.cid END) as screened_count
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.budget_year = ? AND a.is_sandbox = ?
        WHERE p.hoscode IN ($inPlaceholders)
        GROUP BY sex, age_group
    ");
    $pyramidStmt->execute(array_merge([$selectedBudgetYear, $isSandboxVal], $hoscodes));
    $pyramidRaw = $pyramidStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pyramidRaw as $row) {
        $gIdx = array_search($row['age_group'], $ageGroups);
        if ($gIdx !== false) {
            $isMale = (in_array((string)$row['sex'], ['1', 'M', 'ชาย', 'male'], true));
            if ($isMale) {
                $maleTotal[$gIdx] += intval($row['total_target']);
                $maleScreened[$gIdx] += intval($row['screened_count']);
            } else {
                $femaleTotal[$gIdx] += intval($row['total_target']);
                $femaleScreened[$gIdx] += intval($row['screened_count']);
            }
        }
    }
} catch (\Throwable $e) {}

// =========================================================================
// 9. DPAC Retention & Dropout Analytics
// =========================================================================
$retentionData = ['total_enrolled' => 0, 'round1_count' => 0, 'round2_count' => 0, 'round3_count' => 0, 'round4_count' => 0];
try {
    $retentionStmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT e.enrollment_id) as total_enrolled,
            SUM(CASE WHEN f1.completed_count >= 1 THEN 1 ELSE 0 END) as round1_count,
            SUM(CASE WHEN f2.completed_count >= 1 THEN 1 ELSE 0 END) as round2_count,
            SUM(CASE WHEN f3.completed_count >= 1 THEN 1 ELSE 0 END) as round3_count,
            SUM(CASE WHEN f4.completed_count >= 1 THEN 1 ELSE 0 END) as round4_count
        FROM dpac_enrollments e
        JOIN target_population p ON e.cid = p.cid
        LEFT JOIN (SELECT enrollment_id, COUNT(*) as completed_count FROM dpac_followups WHERE round_number = 1 AND status = 'completed' GROUP BY enrollment_id) f1 ON e.enrollment_id = f1.enrollment_id
        LEFT JOIN (SELECT enrollment_id, COUNT(*) as completed_count FROM dpac_followups WHERE round_number = 2 AND status = 'completed' GROUP BY enrollment_id) f2 ON e.enrollment_id = f2.enrollment_id
        LEFT JOIN (SELECT enrollment_id, COUNT(*) as completed_count FROM dpac_followups WHERE round_number = 3 AND status = 'completed' GROUP BY enrollment_id) f3 ON e.enrollment_id = f3.enrollment_id
        LEFT JOIN (SELECT enrollment_id, COUNT(*) as completed_count FROM dpac_followups WHERE round_number = 4 AND status = 'completed' GROUP BY enrollment_id) f4 ON e.enrollment_id = f4.enrollment_id
        WHERE p.hoscode IN ($inPlaceholders)
    ");
    $retentionStmt->execute($hoscodes);
    $res = $retentionStmt->fetch(PDO::FETCH_ASSOC);
    if ($res) $retentionData = $res;
} catch (\Throwable $e) {}

$dropoutList = [];
try {
    $dropoutStmt = $pdo->prepare("
        SELECT 
            e.enrollment_id, e.risk_type, e.created_at AS enroll_date,
            p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
            max_f.max_round, max_f.last_date,
            DATEDIFF(CURRENT_DATE, max_f.last_date) AS days_since_last,
            a.assigned_vhv
        FROM dpac_enrollments e
        JOIN target_population p ON e.cid = p.cid
        JOIN (
            SELECT enrollment_id, MAX(round_number) as max_round, MAX(completed_at) as last_date
            FROM dpac_followups
            WHERE status = 'completed'
            GROUP BY enrollment_id
        ) max_f ON e.enrollment_id = max_f.enrollment_id
        LEFT JOIN task_assignments a ON p.cid = a.target_cid
        WHERE e.status = 'active' 
          AND max_f.max_round < 4 
          AND DATEDIFF(CURRENT_DATE, max_f.last_date) >= 30
          AND p.hoscode IN ($inPlaceholders)
        ORDER BY days_since_last DESC
        LIMIT 10
    ");
    $dropoutStmt->execute($hoscodes);
    $dropoutList = $dropoutStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบวิเคราะห์ข้อมูลเชิงลึก (Advanced Analytics) - NCDs Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Leaflet Map CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        #temporal-map {
            height: 450px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            background: var(--bg-darker);
            margin-bottom: 16px;
            z-index: 1;
        }

        .play-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-darker);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 12px 24px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-control {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-control:hover {
            border-color: var(--color-accent);
            color: var(--color-accent);
            background: rgba(14, 165, 233, 0.05);
        }

        .btn-control.active-play {
            background: var(--color-accent);
            color: white;
            border-color: var(--color-accent);
            box-shadow: 0 0 10px rgba(14, 165, 233, 0.4);
        }

        .quarter-indicator {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .quarter-badge {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quarter-badge.active {
            background: rgba(34, 197, 94, 0.15);
            color: var(--color-green);
            border-color: var(--color-green);
        }

        .stats-badge-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .stats-badge-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .stats-badge-card .num {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .stats-badge-card .label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: bold;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stats-badge-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body class="admin-body dashboard-page">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: rgba(14, 165, 233, 0.15); color: #0ea5e9;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12z" />
                </svg>
            </span>
            ระบบวิเคราะห์ข้อมูลเชิงลึก (Advanced Analytics)
        </h2>
        <!-- Executive Actions Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 12px; flex-wrap: wrap;" class="no-print">
            <div style="font-size: 14px; font-weight: bold; color: var(--text-secondary);">
                📊 รายงานวิเคราะห์ข้อมูลเชิงลึกขั้นสูง (Advanced Analytics Suite)
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="exportR2RCSV()" class="btn-control" style="background: rgba(14, 165, 233, 0.12); color: #0ea5e9; border-color: rgba(14, 165, 233, 0.3);">
                    📥 ดาวน์โหลดชุดข้อมูล R2R (CSV)
                </button>
                <button type="button" onclick="window.print()" class="btn-control" style="background: rgba(34, 197, 94, 0.12); color: #22c55e; border-color: rgba(34, 197, 94, 0.3);">
                    🖨️ พิมพ์สรุปภาพรวมผู้บริหาร (Print Brief)
                </button>
            </div>
        </div>

        <!-- Shared analytics filters -->
        <form method="GET" class="card-dark no-print" style="margin-bottom:24px; padding:18px; display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; align-items:end;">
            <label style="font-size:12px; color:var(--text-secondary);">ปีงบประมาณ
                <select name="budget_year" class="form-control" style="margin-top:6px; width:100%;">
                    <?php foreach ($availableBudgetYears ?: [$defaultBudgetYear] as $year): ?>
                        <option value="<?= (int)$year ?>" <?= (int)$year === $selectedBudgetYear ? 'selected' : '' ?>><?= (int)$year ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!$admin_hoscode): ?>
            <label style="font-size:12px; color:var(--text-secondary);">หน่วยบริการ
                <select name="hoscode" class="form-control" style="margin-top:6px; width:100%;">
                    <option value="">ทุกหน่วยบริการ</option>
                    <?php foreach ($allowedHoscodes as $code): ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= $selectedHoscode === $code ? 'selected' : '' ?>><?= htmlspecialchars($hc_names[$code] ?? $code) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <label style="font-size:12px; color:var(--text-secondary);">รอบคัดกรอง
                <select name="round" class="form-control" style="margin-top:6px; width:100%;">
                    <option value="0">ทุกรอบ</option>
                    <?php for ($r = 1; $r <= 10; $r++): ?>
                        <option value="<?= $r ?>" <?= $selectedRound === $r ? 'selected' : '' ?>>รอบที่ <?= $r ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label style="font-size:12px; color:var(--text-secondary);">ตั้งแต่วันที่
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-control" style="margin-top:6px; width:100%;">
            </label>
            <label style="font-size:12px; color:var(--text-secondary);">ถึงวันที่
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="form-control" style="margin-top:6px; width:100%;">
            </label>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn-control" style="flex:1;">ใช้ตัวกรอง</button>
                <a href="analytics.php" class="btn-control" style="text-decoration:none; text-align:center;">ล้าง</a>
            </div>
            <div style="grid-column:1/-1; font-size:11px; color:var(--text-muted);">
                ปีงบประมาณและหน่วยบริการใช้กับสรุปงานหลายรอบ งานติดตาม และการเปรียบเทียบ NCD/DPAC ส่วนตัวกรองรอบและวันที่ใช้กับรายละเอียด NCD คะแนนจัดลำดับติดตาม และคุณภาพข้อมูล
            </div>
        </form>

        <!-- Multi-round operational funnel -->
        <div class="card-dark" style="margin-bottom:24px; padding:22px;">
            <h3 style="margin:0 0 16px; color:var(--color-accent); font-size:17px;">เส้นทางผลงานคัดกรองหลายรอบ ปีงบประมาณ <?= $selectedBudgetYear ?></h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px;">
                <?php
                $funnelCards = [
                    ['เป้าหมาย', $funnelData['total_targets'], '#64748b'],
                    ['รอบ 1 สำเร็จ', $funnelData['r1_completed'], '#22c55e'],
                    ['มอบหมายรอบ 2', $funnelData['r2_assigned'], '#0ea5e9'],
                    ['รอบ 2 สำเร็จ', $funnelData['r2_completed'], '#3b82f6'],
                    ['รอบ 3+ สำเร็จ', $funnelData['r3_completed'], '#8b5cf6']
                ];
                foreach ($funnelCards as $fc): ?>
                    <div style="padding:14px; border-radius:12px; background:<?= $fc[2] ?>12; border:1px solid <?= $fc[2] ?>33;">
                        <div style="font-size:12px; color:var(--text-secondary); font-weight:700;"><?= $fc[0] ?></div>
                        <div style="font-size:24px; font-weight:900; color:<?= $fc[2] ?>; margin-top:5px;"><?= number_format((int)$fc[1]) ?> <small style="font-size:11px;">ราย</small></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Action queue -->
        <div style="margin-bottom:24px;">
            <h3 style="font-size:17px; color:var(--color-accent); margin:0 0 12px;">งานที่ควรดำเนินการต่อ</h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px;">
                <a href="assignment.php" class="card-dark" style="text-decoration:none; border-left:4px solid #ef4444;">
                    <div style="font-size:13px; color:var(--text-secondary);">ผลเสี่ยงสูงที่ยังไม่มีรอบถัดไป</div>
                    <div class="stat-val" style="color:#ef4444;"><?= number_format($actionCounts['high_risk_unassigned']) ?> <small>ราย</small></div>
                </a>
                <a href="assignment.php" class="card-dark" style="text-decoration:none; border-left:4px solid #f59e0b;">
                    <div style="font-size:13px; color:var(--text-secondary);">งานรอบ 2+ รอดำเนินการเกิน 7 วัน</div>
                    <div class="stat-val" style="color:#f59e0b;"><?= number_format($actionCounts['overdue']) ?> <small>งาน</small></div>
                </a>
                <a href="assignment.php" class="card-dark" style="text-decoration:none; border-left:4px solid #0ea5e9;">
                    <div style="font-size:13px; color:var(--text-secondary);">รอบ 1 สำเร็จแต่ยังไม่มีรอบติดตาม</div>
                    <div class="stat-val" style="color:#0ea5e9;"><?= number_format($actionCounts['awaiting_r2']) ?> <small>ราย</small></div>
                </a>
            </div>
            <?php if (!empty($overdueActionRows)): ?>
            <div class="card-dark" style="margin-top:12px; padding:16px;">
                <div style="font-size:13px; font-weight:800; color:#f59e0b; margin-bottom:10px;">10 งานที่รอนานที่สุด</div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead><tr><th>ชื่อ-นามสกุล</th><th>พื้นที่</th><th>รอบ</th><th>อสม.</th><th style="text-align:right;">รอมาแล้ว</th></tr></thead>
                        <tbody>
                        <?php foreach ($overdueActionRows as $job): ?>
                            <tr>
                                <td><?= htmlspecialchars(trim($job['first_name'].' '.$job['last_name'])) ?></td>
                                <td>บ้านเลขที่ <?= htmlspecialchars($job['house_no'] ?: '-') ?> หมู่ <?= (int)$job['moo'] ?></td>
                                <td>รอบ <?= (int)$job['round_number'] ?></td>
                                <td><?= htmlspecialchars($job['vhv_name'] ?: '-') ?></td>
                                <td style="text-align:right; color:#f59e0b; font-weight:800;"><?= (int)$job['waiting_days'] ?> วัน</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Data quality -->
        <div class="card-dark" style="margin-bottom:30px; padding:22px;">
            <h3 style="font-size:17px; color:var(--color-accent); margin:0 0 6px;">คุณภาพข้อมูลที่ใช้วิเคราะห์</h3>
            <p style="font-size:12px; color:var(--text-muted); margin:0 0 14px;">จำนวนรายการที่ควรตรวจสอบ ข้อมูลเดิมจะไม่ถูกแก้ไขอัตโนมัติจากหน้านี้</p>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px;">
                <?php
                $qualityCards = [
                    ['น้ำหนัก/ส่วนสูง/รอบเอวไม่ครบ', $dataQuality['missing_body']],
                    ['Vital sign ไม่ครบ', $dataQuality['missing_vitals']],
                    ['ไม่มีพิกัดบันทึก', $dataQuality['missing_geo']],
                    ['รอบผลไม่ตรงใบงาน', $dataQuality['round_mismatch']],
                    ['ผลไม่มีใบงานอ้างอิง', $dataQuality['orphan_results']],
                    ['CID จำลอง/ต้องตรวจสอบ', $dataQuality['mock_cid']]
                ];
                foreach ($qualityCards as $qc): ?>
                    <div style="padding:12px; background:rgba(100,116,139,.08); border:1px solid var(--border-color); border-radius:10px;">
                        <div style="font-size:11px; color:var(--text-muted); min-height:32px;"><?= htmlspecialchars($qc[0]) ?></div>
                        <div style="font-size:21px; font-weight:900; color:<?= (int)$qc[1] > 0 ? '#f59e0b' : '#22c55e' ?>;"><?= number_format((int)$qc[1]) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Module 1: Predictive Disease Conversion Risk -->
        <div class="card-dark" style="margin-bottom: 30px; padding: 24px; border-top: 4px solid #f59e0b;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <h3 style="color: #f59e0b; margin: 0; font-size: 17px; display: flex; align-items: center; gap: 8px;">
                    <span>🎯 คะแนนจัดลำดับความเร่งด่วนในการติดตาม</span>
                </h3>
                <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: bold;">
                    ควรติดตามเร่งด่วน: <?= number_format($highConversionCount) ?> ราย
                </span>
            </div>
            <p style="color: var(--text-secondary); font-size: 13.5px; margin-bottom: 16px; line-height: 1.6;">
                คะแนนนี้ใช้จัดลำดับงานจากค่า BP/DTX, BMI, ประวัติครอบครัว และอายุที่มีอยู่ในระบบ ไม่ใช่ความน่าจะเป็นที่จะป่วยและยังไม่ได้ผ่านการตรวจสอบเป็นแบบจำลองทางคลินิก
            </p>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="text-align: center; width: 60px;">ลำดับ</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>หน่วยบริการ / หมู่บ้าน</th>
                            <th style="text-align: center;">อายุ</th>
                            <th style="text-align: center;">คะแนนติดตาม</th>
                            <th>ปัจจัยเสี่ยงหลัก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topConversionRisks)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 20px;">ไม่พบประชากรกลุ่มเสี่ยงเฝ้าระวังสูงในระบบ</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topConversionRisks as $idx => $r): ?>
                                <tr>
                                    <td style="text-align: center; font-weight: bold;"><?= $idx + 1 ?></td>
                                    <td style="font-weight: bold; color: var(--text-primary);"><?= htmlspecialchars($r['name']) ?></td>
                                    <td><?= htmlspecialchars($r['hc']) ?> (<?= htmlspecialchars($r['village']) ?> บ้านเลขที่ <?= htmlspecialchars($r['house_no']) ?>)</td>
                                    <td style="text-align: center;"><?= $r['age'] !== null ? $r['age'] . ' ปี' : '-' ?></td>
                                    <td style="text-align: center;">
                                        <span style="background: <?= $r['score'] >= 60 ? 'rgba(239, 68, 68, 0.15)' : 'rgba(245, 158, 11, 0.15)' ?>; color: <?= $r['score'] >= 60 ? '#ef4444' : '#f59e0b' ?>; padding: 4px 10px; border-radius: 12px; font-weight: bold;">
                                            <?= $r['score'] ?> คะแนน
                                        </span>
                                    </td>
                                    <td style="font-size: 12.5px; color: var(--text-secondary);"><?= htmlspecialchars($r['factors']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AI-Powered Executive Summary Diagnostic Card -->
        <div class="card-dark" style="margin-bottom: 30px; border-left: 4px solid var(--color-primary); padding: 24px;">
            <h3 style="color: var(--color-primary); margin-top: 0; margin-bottom: 16px; font-size: 17px; display: flex; align-items: center; gap: 8px;">
                <span>🔮 บทวิเคราะห์เชิงรุกและชี้เป้าทางระบาดวิทยา (Spatial & Predictive Insights)</span>
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 20px;">
                <!-- Hotspots Card -->
                <div style="background: rgba(239, 68, 68, 0.05); padding: 16px; border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.15);">
                    <div style="font-weight: bold; color: #ef4444; font-size: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <span>🔴 จุดวิกฤตชุกชุมกลุ่มเสี่ยงสูงสุด (Prevalence Hotspots)</span>
                    </div>
                    <ul style="margin: 0; padding-left: 20px; font-size: 13.5px; color: var(--text-secondary); line-height: 1.8;">
                        <?php if (empty($highestPrevalence)): ?>
                            <li>ยังไม่มีข้อมูลการประเมินคัดกรอง</li>
                        <?php else: ?>
                            <?php foreach ($highestPrevalence as $idx => $p): ?>
                                <li>
                                    <strong>อันดับ <?= $idx + 1 ?>:</strong> <?= htmlspecialchars($p['name']) ?>
                                    <span style="color: #ef4444; font-weight: bold;"><?= number_format($p['rate'], 1) ?>%</span>
                                    <span style="font-size: 11px; color: var(--text-muted);">(พบเสี่ยง <?= $p['risk_count'] ?> จาก <?= $p['total_screened'] ?> ราย)</span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Best Improvement -->
                <div style="background: rgba(34, 197, 94, 0.05); padding: 16px; border-radius: 12px; border: 1px solid rgba(34, 197, 94, 0.15);">
                    <div style="font-weight: bold; color: #22c55e; font-size: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <span>🟢 พื้นที่แนวโน้มพัฒนาการสุขภาพสูงสุด (Most Improved)</span>
                    </div>
                    <ul style="margin: 0; padding-left: 20px; font-size: 13.5px; color: var(--text-secondary); line-height: 1.8;">
                        <?php if (empty($bestImprovement)): ?>
                            <li>ยังไม่มีข้อมูลผู้เข้าร่วมโครงการติดตามผล</li>
                        <?php else: ?>
                            <?php foreach ($bestImprovement as $idx => $bi): ?>
                                <li>
                                    <strong>อันดับ <?= $idx + 1 ?>:</strong> <?= htmlspecialchars($bi['name']) ?>
                                    <span style="color: #22c55e; font-weight: bold;"><?= number_format($bi['rate'], 1) ?>%</span>
                                    <span style="font-size: 11px; color: var(--text-muted);">(ดีขึ้น <?= $bi['improved'] ?> จาก <?= $bi['completed'] ?> ราย)</span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Concerning Areas -->
                <div style="background: rgba(245, 158, 11, 0.05); padding: 16px; border-radius: 12px; border: 1px solid rgba(245, 158, 11, 0.15);">
                    <div style="font-weight: bold; color: #f59e0b; font-size: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <span>⚠️ พื้นที่ที่ยังทรงตัว/ควรเฝ้าระวังเพิ่ม (Concerning Areas)</span>
                    </div>
                    <ul style="margin: 0; padding-left: 20px; font-size: 13.5px; color: var(--text-secondary); line-height: 1.8;">
                        <?php if (empty($concerningAreas)): ?>
                            <li>ยังไม่มีข้อมูลผู้เข้าร่วมโครงการติดตามผล</li>
                        <?php else: ?>
                            <?php foreach ($concerningAreas as $idx => $ca): ?>
                                <li>
                                    <strong>อันดับ <?= $idx + 1 ?>:</strong> <?= htmlspecialchars($ca['name']) ?>
                                    <span style="color: #f59e0b; font-weight: bold;"><?= number_format($ca['rate'], 1) ?>%</span>
                                    <span style="font-size: 11px; color: var(--text-muted);">(ดีขึ้น <?= $ca['improved'] ?> จาก <?= $ca['completed'] ?> ราย)</span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div style="margin-top: 18px; font-size: 13px; color: var(--text-muted); line-height: 1.6; border-top: 1px dashed var(--border-color); padding-top: 12px;">
                💡 <strong>คำแนะนำเชิงกลยุทธ์:</strong>
                <?php if (!empty($highestPrevalence)): ?>
                    ควรพิจารณาส่งทีมแพทย์เคลื่อนที่เร็วหรือจัดสรรงบประมาณลงตรวจคัดกรองซ้ำ ณ <strong><?= htmlspecialchars($highestPrevalence[0]['name']) ?></strong> เนื่องจากพบอัตราความชุกกลุ่มเสี่ยง/ป่วยสูงที่สุด และควรส่ง อสม. ประกบแนะนำการปรับเปลี่ยนพฤติกรรมในเขต
                <?php endif; ?>
                <?php if (!empty($concerningAreas)): ?>
                    <strong><?= htmlspecialchars($concerningAreas[0]['name']) ?></strong> เพื่อปรับแผนโภชนาการและการออกกำลังกายใหม่ เนื่องจากมีอัตราสุขภาพพัฒนาดีขึ้นค่อนข้างต่ำ
                <?php endif; ?>
            </div>
        </div>

        <!-- AI-Powered Trend Forecasting Card -->
        <div class="card-dark" style="margin-bottom: 30px; padding: 24px;">
            <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(236, 72, 153, 0.15); color: #ec4899;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </span>
                <span>แนวโน้มเชิงเส้นจากข้อมูลกลุ่มเสี่ยงรายเดือน</span>
            </h3>
            <div id="chart-forecast" style="margin-bottom: 20px;"></div>

            <div style="background: rgba(14, 165, 233, 0.05); padding: 16px; border-radius: 12px; border: 1px solid rgba(14, 165, 233, 0.15); font-size: 13.5px; color: var(--text-secondary); line-height: 1.6;" id="forecast-insights">
                🔍 <strong>คำอธิบาย:</strong>
                <span id="forecast-insight-text">กำลังคำนวณแนวโน้มจากประวัติที่มีในระบบ ข้อมูลส่วนนี้ไม่ใช่การพยากรณ์การวินิจฉัยโรค</span>
            </div>
        </div>

        <!-- DPAC Intervention Outcome Summary Cards -->
        <h3 style="color: var(--color-accent); margin-bottom: 16px; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <span>🔄 ประสิทธิผลการปรับเปลี่ยนพฤติกรรมกลุ่มเสี่ยง (DPAC Outcomes)</span>
            <span style="font-size: 13px; font-weight: normal; color: var(--text-secondary);">(ประเมินเปรียบเทียบ Round 1 vs ล่าสุด ของผู้ติดตาม 2 รอบขึ้นไป รวม <?= number_format($totalAnalyzed) ?> ราย)</span>
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card-dark" style="border-left: 4px solid var(--color-accent);">
                <div style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-bottom: 8px;">ค่าความดันตัวบนเฉลี่ย (Systolic BP)</div>
                <div style="display: flex; align-items: baseline; gap: 8px;">
                    <span style="font-size: 26px; font-weight: 800; color: var(--text-primary);"><?= $avgSbpBefore ?></span>
                    <span style="color: var(--text-muted); font-size: 13px;">→</span>
                    <span style="font-size: 26px; font-weight: 800; color: var(--color-green);"><?= $avgSbpAfter ?></span>
                    <span style="font-size: 13px; color: var(--text-muted); margin-left: 4px;">mmHg</span>
                </div>
                <div style="font-size: 12px; margin-top: 8px; color: var(--color-green); font-weight: bold;">
                    📉 ลดลงเฉลี่ย <?= $avgSbpBefore > $avgSbpAfter ? round($avgSbpBefore - $avgSbpAfter, 1) : 0 ?> mmHg
                </div>
            </div>

            <div class="card-dark" style="border-left: 4px solid #a78bfa;">
                <div style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-bottom: 8px;">ค่าน้ำตาลในเลือดเฉลี่ย (FBS)</div>
                <div style="display: flex; align-items: baseline; gap: 8px;">
                    <span style="font-size: 26px; font-weight: 800; color: var(--text-primary);"><?= $avgFbsBefore ?></span>
                    <span style="color: var(--text-muted); font-size: 13px;">→</span>
                    <span style="font-size: 26px; font-weight: 800; color: var(--color-green);"><?= $avgFbsAfter ?></span>
                    <span style="font-size: 13px; color: var(--text-muted); margin-left: 4px;">mg/dL</span>
                </div>
                <div style="font-size: 12px; margin-top: 8px; color: var(--color-green); font-weight: bold;">
                    📉 ลดลงเฉลี่ย <?= $avgFbsBefore > $avgFbsAfter ? round($avgFbsBefore - $avgFbsAfter, 1) : 0 ?> mg/dL
                </div>
            </div>

            <div class="card-dark" style="border-left: 4px solid var(--color-green);">
                <div style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-bottom: 8px;">ควบคุมความดันสำเร็จ / ดีขึ้น</div>
                <div class="stat-val" style="color: var(--color-green);">
                    <?= $pctBpImprovement ?>%
                    <span style="font-size: 14px; color: var(--text-secondary); font-weight: normal;">(<?= $improvedBpCount ?>/<?= $totalHtCases ?> ราย)</span>
                </div>
                <div style="font-size: 12px; margin-top: 8px; color: var(--text-muted);">
                    มีระดับความดันลดลงจากเดิมหรือกลับสู่สภาวะปกติ
                </div>
            </div>

            <div class="card-dark" style="border-left: 4px solid var(--color-green);">
                <div style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-bottom: 8px;">ควบคุมค่าน้ำตาลสำเร็จ / ดีขึ้น</div>
                <div class="stat-val" style="color: var(--color-green);">
                    <?= $pctFbsImprovement ?>%
                    <span style="font-size: 14px; color: var(--text-secondary); font-weight: normal;">(<?= $improvedFbsCount ?>/<?= $totalDmCases ?> ราย)</span>
                </div>
                <div style="font-size: 12px; margin-top: 8px; color: var(--text-muted);">
                    ระดับน้ำตาลในเลือดลดลงจากเดิมหรือควบคุมได้ดี
                </div>
            </div>
        </div>

        <!-- Dynamic Clinical Guidance based on overall DPAC Outcomes -->
        <div style="background: rgba(16, 185, 129, 0.05); padding: 18px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2); font-size: 13.5px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 25px;">
            💡 <strong>คำแนะนำ (Health Feedback Loop):</strong>
            <?php
            $bp_diff = $avgSbpBefore - $avgSbpAfter;
            $fbs_diff = $avgFbsBefore - $avgFbsAfter;

            if ($pctBpImprovement >= 60 && $pctFbsImprovement >= 60) {
                echo "อัตราการดีขึ้นของกลุ่มเสี่ยงความดันโลหิตสูง (" . $pctBpImprovement . "%) และเบาหวาน (" . $pctFbsImprovement . "%) <span style='color:var(--color-green); font-weight:bold;'>ผ่านเกณฑ์มาตรฐานยอดเยี่ยม (>= 60%)</span> แนะนำให้เจ้าหน้าที่ รพ.สต. และ อสม. รักษาระดับความถี่ในการติดตามพฤติกรรมนี้ต่อไป";
            } else {
                echo "อัตราการควบคุมได้ดีหรือดีขึ้นของกลุ่มเบาหวานหรือความดันโลหิตสูง <span style='color:var(--color-red); font-weight:bold;'>ยังต่ำกว่าเกณฑ์ความสำเร็จเป้าหมาย (60%)</span> แนะนำให้เจ้าหน้าที่และ อสม. ร่วมกันจัดอบรมทบทวนหลัก 3อ. 2ส. และลงเยี่ยมบ้านวัดค่าสัญญาณชีพแบบใกล้ชิดเป็นกรณีพิเศษ";
            }

            if ($bp_diff > 0 || $fbs_diff > 0) {
                echo " โดยภาพรวมประชากรกลุ่มเสี่ยงมีค่าความดันโลหิตบนลดลงเฉลี่ย " . number_format(max(0, $bp_diff), 1) . " mmHg และน้ำตาลลดลงเฉลี่ย " . number_format(max(0, $fbs_diff), 1) . " mg/dL แสดงถึงประสิทธิภาพการใส่ใจควบคุมสุขภาพส่วนบุคคลที่พัฒนาขึ้นอย่างเห็นได้ชัด";
            }
            ?>
        </div>

        <div class="dashboard-grid">
            <div class="card-dark">
                <h4 style="color: var(--color-accent); margin-bottom: 16px; font-size: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">📊 เปรียบเทียบผลตรวจเฉลี่ย ก่อน-หลัง ร่วมโครงการ</h4>
                <div id="chart-outcome-comparison"></div>
            </div>
            <div class="card-dark">
                <h4 style="color: var(--color-accent); margin-bottom: 16px; font-size: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">📈 อัตราการเปลี่ยนแปลงความรุนแรงของกลุ่มเสี่ยง (Risk Transition)</h4>
                <div id="chart-risk-transition"></div>
            </div>
        </div>

        <!-- Module 2: VHV Quality & Health Impact -->
        <div class="card-dark" style="margin-bottom: 30px; padding: 24px; border-top: 4px solid #10b981;">
            <h3 style="color: #10b981; margin-top: 0; margin-bottom: 16px; font-size: 17px; display: flex; align-items: center; gap: 8px;">
                <span>🏆 ประสิทธิผลและคุณภาพการปฏิบัติงาน อสม. (VHV Quality & Health Impact)</span>
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 16px; margin-bottom: 20px;">
                <div style="background: rgba(16, 185, 129, 0.06); padding: 16px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2); text-align: center;">
                    <div style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-bottom: 4px;">อัตราคัดกรองพบกลุ่มเสี่ยงเฉลี่ย (Screening Yield)</div>
                    <div style="font-size: 26px; font-weight: 800; color: #10b981;"><?= $avgYieldRate ?>%</div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">(พบเสี่ยง <?= number_format($totalRiskFoundAll) ?> ราย จากที่สแกน <?= number_format($totalScreenedAll) ?> ราย)</div>
                </div>
            </div>

            <h4 style="color: var(--text-primary); font-size: 14px; margin-bottom: 12px; font-weight: bold;">🥇 5 อันดับ อสม. ดีเด่นด้านผลสัมฤทธิ์สุขภาพ (Health Impact Champion VHVs)</h4>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="text-align: center; width: 60px;">อันดับ</th>
                            <th>ชื่อ อสม.</th>
                            <th>หน่วยบริการ / สังกัด</th>
                            <th style="text-align: right;">คัดกรอง (ราย)</th>
                            <th style="text-align: right;">พบเสี่ยงจริง (ราย)</th>
                            <th style="text-align: right;">อัตรา Yield (%)</th>
                            <th style="text-align: right;">ลูกบ้านสุขภาพดีขึ้น (ราย)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vhvImpactData)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 20px;">ยังไม่มีข้อมูลผลงาน อสม.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vhvImpactData as $idx => $v): ?>
                                <?php 
                                $yRate = $v['total_screened'] > 0 ? round(($v['risk_found'] / $v['total_screened']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td style="text-align: center; font-weight: bold;"><?= $idx + 1 ?></td>
                                    <td style="font-weight: bold; color: var(--color-accent);"><?= htmlspecialchars($v['vhv_name']) ?></td>
                                    <td><?= htmlspecialchars($hc_names[$v['hoscode']] ?? $v['hoscode']) ?> (<?= htmlspecialchars($v['village'] ?? '-') ?>)</td>
                                    <td style="text-align: right;"><?= number_format($v['total_screened']) ?></td>
                                    <td style="text-align: right; font-weight: bold;"><?= number_format($v['risk_found']) ?></td>
                                    <td style="text-align: right; color: var(--color-accent); font-weight: bold;"><?= $yRate ?>%</td>
                                    <td style="text-align: right; color: #10b981; font-weight: 800; font-size: 15px;"><?= number_format($v['dpac_improved_count']) ?> ราย</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modules 3 & 4 Grid: Demographic Pyramid & DPAC Retention -->
        <div class="dashboard-grid">
            <div class="card-dark">
                <h4 style="color: var(--color-accent); margin-bottom: 16px; font-size: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                    📊 ปิรามิดครอบคลุมการคัดกรองตามช่วงอายุและเพศ (Demographic Equity)
                </h4>
                <div id="chart-age-gender-pyramid"></div>
                <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 10px; line-height: 1.5; background: rgba(14,165,233,0.05); padding: 10px; border-radius: 8px;">
                    💡 <strong>การวิเคราะห์ความเท่าเทียม:</strong> ช่วยระบุกลุ่มวัยเป้าหมายที่ตกสำรวจ (เช่น กลุ่มชายวัยทำงาน 35-44 ปี) เพื่อจัดรอบลงสแกนคัดกรองสเปเชียลคลินิกนอกเวลาราชการ
                </div>
            </div>

            <div class="card-dark">
                <h4 style="color: #ec4899; margin-bottom: 16px; font-size: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                    📉 การคงอยู่ในระบบและการหลุดติดตาม (DPAC Retention Funnel)
                </h4>
                <div id="chart-dpac-retention"></div>
                
                <?php if (!empty($dropoutList)): ?>
                    <h5 style="color: #ef4444; font-size: 13px; margin: 16px 0 8px 0; font-weight: bold;">⚠️ รายชื่อเฝ้าระวังหลุดติดตามเกิน 30 วัน (Dropout Alarm Top 5)</h5>
                    <div style="max-height: 180px; overflow-y: auto;">
                        <table class="admin-table" style="font-size: 12px;">
                            <thead>
                                <tr>
                                    <th>ชื่อผู้รับการติดตาม</th>
                                    <th>ขาดนัด</th>
                                    <th>อสม. สังกัด</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($dropoutList, 0, 5) as $d): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></strong> (หมู่ <?= $d['moo'] ?>)</td>
                                        <td style="color: #ef4444; font-weight: bold;"><?= $d['days_since_last'] ?> วัน</td>
                                        <td><?= htmlspecialchars($d['assigned_vhv'] ?: 'ยังไม่มอบหมาย') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Geographic Temporal Map Playback Section -->
        <h3 style="color: var(--color-accent); margin-bottom: 16px; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <span>⏱️ แผนที่ระบุระดับพิกัดความเสี่ยงรายไตรมาส (Temporal Geographic Risk Heatmap)</span>
        </h3>

        <div class="play-controls">
            <button type="button" id="btn-play" onclick="togglePlay()" class="btn-control">
                ▶️ เล่นภาพเคลื่อนไหว
            </button>
            <div style="font-size: 15px; font-weight: bold; color: var(--color-accent);" id="active-quarter-title">
                ไตรมาส 1 (ต.ค. - ธ.ค. 2568)
            </div>
            <div class="quarter-indicator">
                <?php foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $idx => $qName): ?>
                    <span class="quarter-badge <?= $idx === 0 ? 'active' : '' ?>" onclick="updateMapForQuarter(<?= $idx ?>)">
                        <?= $qName ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stats-badge-container">
            <div class="stats-badge-card" style="border-top: 4px solid #6b7280;">
                <div class="num" id="stat-unscreened" style="color: #9ca3af;">0 ราย</div>
                <div class="label">⚪ ยังไม่ตรวจ</div>
            </div>
            <div class="stats-badge-card" style="border-top: 4px solid var(--color-green);">
                <div class="num" id="stat-normal" style="color: var(--color-green);">0 ราย</div>
                <div class="label">🟢 กลุ่มปกติ / คุมได้</div>
            </div>
            <div class="stats-badge-card" style="border-top: 4px solid var(--color-yellow);">
                <div class="num" id="stat-moderate" style="color: var(--color-yellow);">0 ราย</div>
                <div class="label">🟡 เสี่ยงปานกลาง</div>
            </div>
            <div class="stats-badge-card" style="border-top: 4px solid var(--color-red);">
                <div class="num" id="stat-high" style="color: var(--color-red);">0 ราย</div>
                <div class="label">🔴 กลุ่มเสี่ยงสูง / ป่วย</div>
            </div>
        </div>

        <div id="temporal-map"></div>

        <!-- Village scorecard table -->
        <div class="card-dark" style="margin-top: 30px;">
            <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(34, 197, 94, 0.15); color: #22c55e;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </span>
                <span>อัตราการลดความเสี่ยงรายพื้นที่หมู่บ้าน (Risk Reduction Rate by Village)</span>
            </h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>โรงพยาบาลส่งเสริมสุขภาพตำบล</th>
                            <th>หมู่บ้าน</th>
                            <th>หมู่</th>
                            <th style="text-align: right;">จำนวนในโครงการ (ราย)</th>
                            <th style="text-align: right;">ติดตามประเมิน 2 รอบขึ้นไป</th>
                            <th style="text-align: right;">จำนวนที่ค่าสุขภาพดีขึ้น (ราย)</th>
                            <th style="text-align: right;">อัตราสำเร็จ (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($villageImprovementData)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 24px;">ยังไม่มีข้อมูลผลลัพธ์การเข้าร่วมโครงการ</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($villageImprovementData as $vi): ?>
                                <?php
                                $village_only = $hoscode_villages[$vi['hoscode']]['villages'][intval($vi['moo'])] ?? (isset($hoscode_villages[$vi['hoscode']]['tambon']) ? get_village_only_name($hoscode_villages[$vi['hoscode']]['tambon'], $vi['moo']) : ('หมู่ที่ ' . $vi['moo']));
                                $hcName = $hc_names[$vi['hoscode']] ?? $vi['hoscode'];
                                $successRate = $vi['completed_followups'] > 0 ? ($vi['improved_count'] / $vi['completed_followups']) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($hcName) ?></td>
                                    <td><?= htmlspecialchars($village_only ?: 'หมู่ที่ ' . $vi['moo']) ?></td>
                                    <td><?= htmlspecialchars($vi['moo']) ?></td>
                                    <td style="text-align: right; font-weight: bold;"><?= number_format($vi['total_enrolled']) ?></td>
                                    <td style="text-align: right;"><?= number_format($vi['completed_followups']) ?></td>
                                    <td style="text-align: right; color: var(--color-green); font-weight: bold;"><?= number_format($vi['improved_count']) ?></td>
                                    <td style="text-align: right;">
                                        <span style="background-color: <?= $successRate >= 50 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(245, 158, 11, 0.15)' ?>; color: <?= $successRate >= 50 ? 'var(--color-green)' : 'var(--color-yellow)' ?>; padding: 4px 10px; border-radius: 12px; font-weight: bold;">
                                            <?= number_format($successRate, 2) ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Strategic Localized Feedback Guide -->
            <?php
            $poorVillages = [];
            foreach ($villageImprovementData as $vi) {
                $completed = intval($vi['completed_followups']);
                if ($completed >= 2) {
                    $improved = intval($vi['improved_count']);
                    $rate = ($improved / $completed) * 100;
                    if ($rate < 50) {
                        $vname = $hoscode_villages[$vi['hoscode']]['villages'][intval($vi['moo'])] ?? (isset($hoscode_villages[$vi['hoscode']]['tambon']) ? get_village_only_name($hoscode_villages[$vi['hoscode']]['tambon'], $vi['moo']) : ('หมู่ที่ ' . $vi['moo']));
                        $poorVillages[] = [
                            'name' => $vname ?: 'หมู่ที่ ' . $vi['moo'],
                            'hc' => $hc_names[$vi['hoscode']] ?? $vi['hoscode'],
                            'rate' => $rate
                        ];
                    }
                }
            }
            if (!empty($poorVillages)):
            ?>
                <div style="background: rgba(245, 158, 11, 0.05); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 158, 11, 0.2); font-size: 13.5px; color: var(--text-secondary); line-height: 1.6; margin-top: 20px;">
                    ⚠️ <strong>ข้อเสนอแนะเชิงรุกจำแนกรายพื้นที่ (Targeted Localized Interventions):</strong>
                    ตรวจพบหมู่บ้านที่มีอัตราสำเร็จในการปรับพฤติกรรมต่ำกว่าเกณฑ์ความสำเร็จ (ต่ำกว่า 50% และมีเคสเสร็จสิ้นมากกว่า 2 ราย) ที่ต้องเฝ้าระวัง:
                    <ul style="margin: 8px 0 0 0; padding-left: 20px; line-height: 1.8;">
                        <?php foreach (array_slice($poorVillages, 0, 3) as $pv): ?>
                            <li><strong><?= htmlspecialchars($pv['hc']) ?> (<?= htmlspecialchars($pv['name']) ?>):</strong> อัตราสำเร็จเพียง <?= number_format($pv['rate'], 1) ?>% <span style="color: var(--color-yellow); font-weight: bold;">(ควรจัดโปรแกรมทบทวนความรู้ อสม. ผู้รับผิดชอบ และนัดหมาย อสม. เพื่อลงพื้นที่สุ่มวัดสัญญาณชีพและประเมินพฤติกรรมโภชนาการรายหลังคาเรือนโดยตรงเพื่อหาสาเหตุร่วมกัน)</span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- R2R User Satisfaction & System Usability Evaluation Section -->
    <h3 style="color: var(--color-accent); margin: 30px 0 16px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
        <span>📋 สรุปผลการประเมินความพึงพอใจผู้ใช้งาน</span>
    </h3>

    <div class="card-dark" style="margin-bottom: 25px;">
        <?php if ($surveyStats['count'] === 0): ?>
            <div style="text-align: center; color: var(--text-secondary); padding: 40px 20px;">
                <div style="font-size: 48px; margin-bottom: 12px;">📝</div>
                <h4 style="margin: 0; font-size: 16px; font-weight: 800;">ยังไม่มีข้อมูลผลการตอบแบบประเมินความพึงพอใจ</h4>
                <p style="margin: 6px 0 0 0; font-size: 13px; opacity: 0.8;">ระบบจะเริ่มวิเคราะห์เมื่อ อสม. ในเครือข่ายประเมินความพึงพอใจหลังส่งงานคัดกรอง</p>
            </div>
        <?php else: ?>
            <?php
            // Helper function to interpret Likert Scale score
            if (!function_exists('interpretLikert')) {
                function interpretLikert($score)
                {
                    if ($score >= 4.50) return "<span style='color: var(--color-green); font-weight: bold;'>มากที่สุด</span>";
                    if ($score >= 3.50) return "<span style='color: var(--color-green);'>มาก</span>";
                    if ($score >= 2.50) return "<span style='color: var(--color-yellow);'>ปานกลาง</span>";
                    if ($score >= 1.50) return "<span style='color: var(--color-red);'>น้อย</span>";
                    return "<span style='color: var(--color-red); font-weight: bold;'>น้อยที่สุด</span>";
                }
            }
            ?>
            <div class="survey-r2r-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div style="background: rgba(37, 99, 235, 0.05); padding: 16px; border-radius: 12px; border: 1px solid rgba(37, 99, 235, 0.15); text-align: center;">
                    <div style="color: var(--text-secondary); font-size: 12.5px; font-weight: bold; margin-bottom: 4px;">จำนวนผู้ตอบแบบประเมิน (n)</div>
                    <div style="font-size: 28px; font-weight: 800; color: var(--color-accent);"><?= number_format($surveyStats['count']) ?> <span style="font-size: 14px; font-weight: normal; color: var(--text-secondary);">ราย</span></div>
                </div>
                <div style="background: rgba(16, 185, 129, 0.05); padding: 16px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.15); text-align: center;">
                    <div style="color: var(--text-secondary); font-size: 12.5px; font-weight: bold; margin-bottom: 4px;">คะแนนเฉลี่ยภาพรวม (Mean - X̄)</div>
                    <div style="font-size: 28px; font-weight: 800; color: var(--color-green);"><?= number_format($surveyStats['total_mean'], 2) ?> <span style="font-size: 14px; font-weight: normal; color: var(--text-secondary);">/ 5.00</span></div>
                </div>
                <div style="background: rgba(245, 158, 11, 0.05); padding: 16px; border-radius: 12px; border: 1px solid rgba(245, 158, 11, 0.15); text-align: center;">
                    <div style="color: var(--text-secondary); font-size: 12.5px; font-weight: bold; margin-bottom: 4px;">ส่วนเบี่ยงเบนมาตรฐาน (S.D.)</div>
                    <div style="font-size: 28px; font-weight: 800; color: var(--color-yellow);"><?= number_format($surveyStats['total_sd'], 2) ?></div>
                </div>
                <div style="background: rgba(168, 85, 247, 0.05); padding: 16px; border-radius: 12px; border: 1px solid rgba(168, 85, 247, 0.15); text-align: center; display: flex; flex-direction: column; justify-content: center;">
                    <div style="color: var(--text-secondary); font-size: 12.5px; font-weight: bold; margin-bottom: 4px;">ระดับความพึงพอใจรวม</div>
                    <div style="font-size: 20px; font-weight: 800;"><?= interpretLikert($surveyStats['total_mean']) ?></div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <h4 style="margin: 0; font-size: 14px; color: var(--text-primary); font-weight: 800;">📋 ตารางแสดงผลสถิติความพึงพอใจแยกตามรายมิติ (TAM Framework)</h4>
                <button type="button" onclick="copyR2RTable()" style="background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); padding: 6px 12px; border-radius: 6px; font-size: 12.5px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='var(--bg-main)'">
                    📋 คัดลอกตาราง (R2R Copy)
                </button>
            </div>

            <div class="table-responsive">
                <table class="admin-table" id="r2r-stat-table">
                    <thead>
                        <tr>
                            <th>ประเด็นการประเมินตามกรอบแนวคิด (TAM Framework)</th>
                            <th style="text-align: center; width: 140px;">ค่าเฉลี่ย (Mean - X̄)</th>
                            <th style="text-align: center; width: 140px;">ส่วนเบี่ยงเบนมาตรฐาน (S.D.)</th>
                            <th style="text-align: center; width: 140px;">ระดับความพึงพอใจ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>1. ด้านการรับรู้ความง่ายในการใช้งานแอปพลิเคชัน (Perceived Ease of Use - PEOU):</strong> ความสะดวก ปุ่มกด เมนูสอดคล้อง</td>
                            <td style="text-align: center; font-weight: bold; color: var(--color-accent);"><?= number_format($surveyStats['peou_mean'], 2) ?></td>
                            <td style="text-align: center;"><?= number_format($surveyStats['peou_sd'], 2) ?></td>
                            <td style="text-align: center;"><?= interpretLikert($surveyStats['peou_mean']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>2. ด้านคุณภาพระบบประมวลผล (System Quality - SQ):</strong> ความรวดเร็วในการแสดงฟอร์ม บันทึกข้อมูลไม่ค้าง</td>
                            <td style="text-align: center; font-weight: bold; color: var(--color-accent);"><?= number_format($surveyStats['sq_mean'], 2) ?></td>
                            <td style="text-align: center;"><?= number_format($surveyStats['sq_sd'], 2) ?></td>
                            <td style="text-align: center;"><?= interpretLikert($surveyStats['sq_mean']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>3. ด้านคุณภาพข้อมูลสารสนเทศ (Information Quality - IQ):</strong> ความถูกต้องและครบถ้วนของข้อมูลเป้าหมายและพิกัดบ้าน</td>
                            <td style="text-align: center; font-weight: bold; color: var(--color-accent);"><?= number_format($surveyStats['iq_mean'], 2) ?></td>
                            <td style="text-align: center;"><?= number_format($surveyStats['iq_sd'], 2) ?></td>
                            <td style="text-align: center;"><?= interpretLikert($surveyStats['iq_mean']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>4. ด้านประโยชน์และการประยุกต์ใช้ (Perceived Usefulness - PU):</strong> การช่วยลดภาระงาน ลดการใช้กระดาษ และลดเวลา</td>
                            <td style="text-align: center; font-weight: bold; color: var(--color-accent);"><?= number_format($surveyStats['pu_mean'], 2) ?></td>
                            <td style="text-align: center;"><?= number_format($surveyStats['pu_sd'], 2) ?></td>
                            <td style="text-align: center;"><?= interpretLikert($surveyStats['pu_mean']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>5. ด้านความตั้งใจในการใช้งานต่อเนื่อง (Behavioral Intention - BI):</strong> ความพึงพอใจโดยรวมและโอกาสใช้สนับสนุนในปีถัดไป</td>
                            <td style="text-align: center; font-weight: bold; color: var(--color-accent);"><?= number_format($surveyStats['bi_mean'], 2) ?></td>
                            <td style="text-align: center;"><?= number_format($surveyStats['bi_sd'], 2) ?></td>
                            <td style="text-align: center;"><?= interpretLikert($surveyStats['bi_mean']) ?></td>
                        </tr>
                        <tr style="background-color: rgba(37, 99, 235, 0.05); font-weight: bold; border-top: 2px solid var(--border-color);">
                            <td>สรุปผลรวมทุกด้าน</td>
                            <td style="text-align: center; font-weight: bold; color: var(--color-green); font-size: 15px;"><?= number_format($surveyStats['total_mean'], 2) ?></td>
                            <td style="text-align: center; font-size: 15px;"><?= number_format($surveyStats['total_sd'], 2) ?></td>
                            <td style="text-align: center; font-size: 15px;"><?= interpretLikert($surveyStats['total_mean']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Popular Quick Tags list -->
            <?php if (!empty($tagsCount)): ?>
                <h4 style="margin: 24px 0 12px 0; font-size: 14px; color: var(--text-primary); font-weight: 800;">💬 ข้อคิดเห็นด่วนยอดนิยมจาก อสม. (Quick Feedback Tags)</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                    <?php foreach (array_slice($tagsCount, 0, 8) as $tag => $cnt): ?>
                        <?php
                        $percentage = ($cnt / $surveyStats['count']) * 100;
                        // Color theme based on positive/negative keywords
                        $isNegative = in_array($tag, ['ตัวหนังสือเล็กเกินไป', 'แอปพลิเคชันค้างบ่อย', 'ไม่มีเน็ตแล้วส่งงานยาก', 'ปุ่มกดยากเล็กน้อย']);
                        $barColor = $isNegative ? 'var(--color-yellow)' : 'var(--color-accent)';
                        ?>
                        <div style="background: var(--bg-main); padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border-color);">
                            <div style="display: flex; justify-content: space-between; font-size: 13.5px; font-weight: 800; margin-bottom: 6px;">
                                <span style="color: var(--text-primary);"><?= htmlspecialchars($tag) ?></span>
                                <span style="color: <?= $barColor ?>;"><?= number_format($percentage, 1) ?>% (<?= $cnt ?> ราย)</span>
                            </div>
                            <div style="background-color: var(--border-color); height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="background-color: <?= $barColor ?>; width: <?= $percentage ?>%; height: 100%; border-radius: 4px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Trend data for forecasting
        const trendRawData = <?= json_encode($monthlyTrend) ?>;

        // Function to run Linear Regression and project next N months
        function forecastTrend(data, N = 3) {
            if (data.length === 0) return {
                months: [],
                actual: [],
                forecast: [],
                slope: 0
            };

            // Sort chronologically
            data.sort((a, b) => a.month_year.localeCompare(b.month_year));

            const months = data.map(d => d.month_year);
            const actual = data.map(d => parseInt(d.high_risk) + parseInt(d.moderate_risk));

            // Fallback if data is too small
            if (data.length < 2) {
                const lastVal = actual[0] || 0;
                const forecast = [lastVal];
                const nextMonths = [];
                if (months.length > 0) {
                    let [y, m] = months[0].split('-').map(Number);
                    for (let i = 1; i <= N; i++) {
                        m++;
                        if (m > 12) {
                            m = 1;
                            y++;
                        }
                        nextMonths.push(`${y}-${String(m).padStart(2, '0')}`);
                        forecast.push(lastVal);
                    }
                }
                return {
                    months: [...months, ...nextMonths],
                    actual: [...actual, ...Array(N).fill(null)],
                    forecast: [...Array(months.length - 1).fill(null), lastVal, ...forecast.slice(1)],
                    slope: 0
                };
            }

            // Least Squares linear regression: y = mx + c
            const x = Array.from({
                length: actual.length
            }, (_, i) => i);
            const n = actual.length;

            const sumX = x.reduce((a, b) => a + b, 0);
            const sumY = actual.reduce((a, b) => a + b, 0);
            const sumXY = x.reduce((sum, xi, i) => sum + xi * actual[i], 0);
            const sumXX = x.reduce((sum, xi) => sum + xi * xi, 0);

            const denominator = (n * sumXX - sumX * sumX);
            const slope = denominator === 0 ? 0 : (n * sumXY - sumX * sumY) / denominator;
            const intercept = (sumY - slope * sumX) / n;

            // Generate actual & forecast lines
            const forecast = [];
            for (let i = 0; i < n; i++) {
                forecast.push(null);
            }
            forecast[n - 1] = actual[n - 1]; // Connect actual and forecast lines

            // Project future N months
            const nextMonths = [];
            let [y, m] = months[n - 1].split('-').map(Number);
            for (let i = 1; i <= N; i++) {
                m++;
                if (m > 12) {
                    m = 1;
                    y++;
                }
                const nextMonthStr = `${y}-${String(m).padStart(2, '0')}`;
                nextMonths.push(nextMonthStr);

                const predictedVal = Math.max(0, Math.round(slope * (n - 1 + i) + intercept));
                forecast.push(predictedVal);
            }

            return {
                months: [...months, ...nextMonths],
                actual: [...actual, ...Array(N).fill(null)],
                forecast: forecast,
                slope: slope
            };
        }

        // Chronological screening + DPAC events
        const records = <?= json_encode($historyRecords) ?>;
        // Targets with coordinates
        const targets = <?= json_encode($mapTargets) ?>;

        // Index records by CID for fast O(1) average lookup
        const recordsByCid = {};
        records.forEach(r => {
            if (!recordsByCid[r.cid]) {
                recordsByCid[r.cid] = [];
            }
            recordsByCid[r.cid].push(r);
        });

        // Resolve risk status at specific timestamp
        function getStatusAt(cid, endTimeStr) {
            const endTime = new Date(endTimeStr).getTime();
            const userRecs = recordsByCid[cid];
            if (!userRecs) return 'UNSCREENED';

            let latest = null;
            for (let i = 0; i < userRecs.length; i++) {
                let rTime = new Date(userRecs[i].created_at).getTime();
                if (rTime <= endTime) {
                    latest = userRecs[i];
                } else {
                    break;
                }
            }
            return latest ? latest.risk_level : 'UNSCREENED';
        }

        // Initialize Map
        const markers = {};
        const map = L.map('temporal-map').setView([<?= $mapCenterLat ?>, <?= $mapCenterLng ?>], <?= $mapInitialZoom ?>);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 20
        }).addTo(map);

        // Marker Colors
        const colors = {
            'HIGH': '#ef4444',
            'MODERATE': '#eab308',
            'NORMAL': '#10b981',
            'UNSCREENED': '#6b7280'
        };

        const labels = {
            'HIGH': 'เสี่ยงสูง 🔴',
            'MODERATE': 'เสี่ยงปานกลาง 🟡',
            'NORMAL': 'ปกติ / คุมได้ 🟢',
            'UNSCREENED': 'ยังไม่ได้ตรวจ ⚪'
        };

        // Create feature group to track bounds
        const markerGroup = L.featureGroup();

        // Create circles
        targets.forEach(t => {
            const marker = L.circleMarker([t.latitude, t.longitude], {
                radius: 6,
                fillColor: colors['UNSCREENED'],
                color: '#1e293b',
                weight: 1.5,
                fillOpacity: 0.85
            }).addTo(map);

            marker.bindPopup(`
                <div style="font-family: var(--font-sans); color: #1e293b; font-size: 13px;">
                    <strong style="color: #0ea5e9;">${t.first_name} ${t.last_name}</strong><br>
                    🏠 บ้านเลขที่: ${t.house_no} หมู่: ${t.moo}<br>
                    <span id="pop-status-${t.cid}">สถานะ: โหลดข้อมูล...</span>
                </div>
            `);

            markers[t.cid] = marker;

            // Check if coordinates fall within Tansum, Ubon boundaries to ignore incorrect marks
            const lat = parseFloat(t.latitude);
            const lng = parseFloat(t.longitude);
            if (lat >= 15.1 && lat <= 15.6 && lng >= 104.8 && lng <= 105.3) {
                markerGroup.addLayer(marker);
            }
        });

        // Fit map bounds automatically if there are valid markers
        if (markerGroup.getLayers().length > 0) {
            map.fitBounds(markerGroup.getBounds(), {
                padding: [30, 30]
            });
        }

        // Quarter playback
        let activeQuarterIndex = 0;
        let playInterval = null;

        const quarters = [{
                name: 'ไตรมาส 1 (ต.ค. - ธ.ค. 2568)',
                end: '2025-12-31T23:59:59'
            },
            {
                name: 'ไตรมาส 2 (ม.ค. - มี.ค. 2569)',
                end: '2026-03-31T23:59:59'
            },
            {
                name: 'ไตรมาส 3 (เม.ย. - มิ.ย. 2569)',
                end: '2026-06-30T23:59:59'
            },
            {
                name: 'ไตรมาส 4 (ก.ค. - ก.ย. 2569)',
                end: '2026-09-30T23:59:59'
            }
        ];

        function updateMapForQuarter(qIndex) {
            activeQuarterIndex = qIndex;
            const quarter = quarters[qIndex];

            document.querySelectorAll('.quarter-badge').forEach((badge, idx) => {
                if (idx === qIndex) {
                    badge.classList.add('active');
                } else {
                    badge.classList.remove('active');
                }
            });

            document.getElementById('active-quarter-title').innerText = quarter.name;

            let stats = {
                HIGH: 0,
                MODERATE: 0,
                NORMAL: 0,
                UNSCREENED: 0
            };

            targets.forEach(t => {
                const status = getStatusAt(t.cid, quarter.end);
                stats[status]++;

                const marker = markers[t.cid];
                if (marker) {
                    marker.setStyle({
                        fillColor: colors[status]
                    });
                    marker.getPopup().setContent(`
                        <div style="font-family: var(--font-sans); color: #1e293b; font-size: 13px;">
                            <strong style="color: #0ea5e9;">${t.first_name} ${t.last_name}</strong><br>
                            🏠 บ้านเลขที่: ${t.house_no} หมู่: ${t.moo}<br>
                            <span>สถานะ: ${labels[status]}</span>
                        </div>
                    `);
                }
            });

            document.getElementById('stat-unscreened').innerText = stats.UNSCREENED.toLocaleString() + ' ราย';
            document.getElementById('stat-normal').innerText = stats.NORMAL.toLocaleString() + ' ราย';
            document.getElementById('stat-moderate').innerText = stats.MODERATE.toLocaleString() + ' ราย';
            document.getElementById('stat-high').innerText = stats.HIGH.toLocaleString() + ' ราย';
        }

        function nextQuarter() {
            let nextIdx = (activeQuarterIndex + 1) % quarters.length;
            updateMapForQuarter(nextIdx);
        }

        function togglePlay() {
            const btn = document.getElementById('btn-play');
            if (playInterval) {
                clearInterval(playInterval);
                playInterval = null;
                btn.innerHTML = '▶️ เล่นภาพเคลื่อนไหว';
                btn.classList.remove('active-play');
            } else {
                playInterval = setInterval(nextQuarter, 1500);
                btn.innerHTML = '⏸️ หยุดชั่วคราว';
                btn.classList.add('active-play');
            }
        }

        // Load first quarter initially
        document.addEventListener("DOMContentLoaded", function() {
            updateMapForQuarter(0);
        });

        // ── Charts Render ──────────────────────────────────────────
        // Chart 1: Before vs After Averages
        var optionsOutcome = {
            series: [{
                name: 'ก่อนร่วมโครงการ (Round 1)',
                data: [<?= $avgSbpBefore ?>, <?= $avgDbpBefore ?>, <?= $avgFbsBefore ?>]
            }, {
                name: 'ประเมินรอบล่าสุด',
                data: [<?= $avgSbpAfter ?>, <?= $avgDbpAfter ?>, <?= $avgFbsAfter ?>]
            }],
            chart: {
                type: 'bar',
                height: 300,
                background: 'transparent',
                toolbar: {
                    show: false
                }
            },
            theme: {
                mode: localStorage.getItem('theme') || 'light'
            },
            colors: ['#3b82f6', '#10b981'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 4
                },
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: ['SYS BP (mmHg)', 'DIA BP (mmHg)', 'Sugar FBS (mg/dL)'],
                labels: {
                    style: {
                        colors: '#9ca3af'
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'ระดับผลตรวจสุขภาพ',
                    style: {
                        color: '#9ca3af'
                    }
                },
                labels: {
                    style: {
                        colors: '#9ca3af'
                    }
                }
            },
            fill: {
                opacity: 1
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val;
                    }
                }
            },
            legend: {
                labels: {
                    colors: '#9ca3af'
                }
            }
        };

        var chartOutcome = new ApexCharts(document.querySelector("#chart-outcome-comparison"), optionsOutcome);
        chartOutcome.render();

        // Chart 2: Risk Transitions
        var optionsTransition = {
            series: [{
                name: 'เสี่ยงสูง 🔴',
                data: [<?= $beforeHigh ?>, <?= $afterHigh ?>]
            }, {
                name: 'เสี่ยงปานกลาง 🟡',
                data: [<?= $beforeModerate ?>, <?= $afterModerate ?>]
            }, {
                name: 'ปกติ / คุมได้ 🟢',
                data: [<?= $beforeNormal ?>, <?= $afterNormal ?>]
            }],
            chart: {
                type: 'bar',
                height: 300,
                stacked: true,
                background: 'transparent',
                toolbar: {
                    show: false
                }
            },
            theme: {
                mode: localStorage.getItem('theme') || 'light'
            },
            colors: ['#ef4444', '#eab308', '#10b981'],
            plotOptions: {
                bar: {
                    borderRadius: 6
                }
            },
            xaxis: {
                categories: ['ก่อนเข้าร่วมโครงการ', 'ประเมินผลรอบล่าสุด'],
                labels: {
                    style: {
                        colors: '#9ca3af'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: '#9ca3af'
                    }
                }
            },
            fill: {
                opacity: 1
            },
            legend: {
                position: 'bottom',
                labels: {
                    colors: '#9ca3af'
                }
            }
        };

        var chartTransition = new ApexCharts(document.querySelector("#chart-risk-transition"), optionsTransition);
        chartTransition.render();

        // ------------------ FORECAST CHART ------------------
        const forecastData = forecastTrend(trendRawData, 3);
        const forecastMonthLabels = forecastData.months.map(m => {
            const parts = m.split('-');
            const thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            const mmIdx = parseInt(parts[1]) - 1;
            return (thaiMonths[mmIdx] || parts[1]) + ' ' + (parseInt(parts[0]) + 543 - 2500);
        });

        var optionsForecast = {
            series: [{
                    name: 'ข้อมูลจริง (Actual)',
                    data: forecastData.actual
                },
                {
                    name: 'คาดการณ์แนวโน้ม (Forecast)',
                    data: forecastData.forecast
                }
            ],
            chart: {
                height: 320,
                type: 'line',
                background: 'transparent',
                toolbar: {
                    show: false
                }
            },
            stroke: {
                width: [3, 3],
                curve: 'smooth',
                dashArray: [0, 5]
            },
            colors: ['#0ea5e9', '#ec4899'],
            xaxis: {
                categories: forecastMonthLabels,
                labels: {
                    style: {
                        colors: '#9ca3af'
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'จำนวนประชากรกลุ่มเสี่ยง (ราย)',
                    style: {
                        color: '#9ca3af'
                    }
                },
                labels: {
                    style: {
                        colors: '#9ca3af'
                    }
                }
            },
            legend: {
                labels: {
                    colors: '#9ca3af'
                }
            },
            tooltip: {
                theme: localStorage.getItem('theme') || 'light'
            }
        };

        var chartForecast = new ApexCharts(document.querySelector("#chart-forecast"), optionsForecast);
        chartForecast.render();

        // Update insight text dynamically based on the slope of the forecast
        const insightElement = document.getElementById('forecast-insight-text');
        if (insightElement) {
            const slope = forecastData.slope;
            if (slope > 0.5) {
                insightElement.textContent = `จำนวนผลคัดกรองที่อยู่ในกลุ่มเสี่ยงมีแนวโน้มเพิ่มขึ้นตามเส้นแนวโน้ม (${slope.toFixed(2)} รายต่อเดือน) ควรตรวจสอบร่วมกับจำนวนผู้ได้รับการคัดกรองในแต่ละเดือนก่อนนำไปวางแผนงาน`;
            } else if (slope < -0.5) {
                insightElement.textContent = `จำนวนผลคัดกรองที่อยู่ในกลุ่มเสี่ยงมีแนวโน้มลดลงตามเส้นแนวโน้ม (${slope.toFixed(2)} รายต่อเดือน) การลดลงนี้ยังไม่ยืนยันผลของมาตรการใดมาตรการหนึ่ง และควรตรวจสอบจำนวนผู้ได้รับการคัดกรองร่วมด้วย`;
            } else {
                insightElement.textContent = `จำนวนผลคัดกรองที่อยู่ในกลุ่มเสี่ยงมีแนวโน้มค่อนข้างคงที่ (${slope.toFixed(2)} รายต่อเดือน) ควรใช้ร่วมกับจำนวนผู้ได้รับการคัดกรองและความครบถ้วนของข้อมูลในแต่ละเดือน`;
            }
        }

        // ------------------ DEMOGRAPHIC PYRAMID CHART ------------------
        var optionsPyramid = {
            series: [{
                name: 'ชาย (คัดกรองแล้ว)',
                data: <?= json_encode(array_map(function($v) { return -$v; }, $maleScreened)) ?>
            }, {
                name: 'หญิง (คัดกรองแล้ว)',
                data: <?= json_encode($femaleScreened) ?>
            }],
            chart: {
                type: 'bar',
                height: 280,
                stacked: true,
                background: 'transparent',
                toolbar: { show: false }
            },
            colors: ['#0ea5e9', '#ec4899'],
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '60%',
                    borderRadius: 4
                }
            },
            dataLabels: { enabled: false },
            stroke: { width: 1, colors: ['transparent'] },
            grid: { xaxis: { lines: { show: false } } },
            yaxis: {
                categories: <?= json_encode($ageGroups) ?>,
                labels: { style: { colors: '#9ca3af' } }
            },
            xaxis: {
                labels: {
                    formatter: function (val) {
                        return Math.abs(val) + " ราย";
                    },
                    style: { colors: '#9ca3af' }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return Math.abs(val) + " ราย";
                    }
                }
            },
            legend: { labels: { colors: '#9ca3af' } }
        };
        var chartPyramid = new ApexCharts(document.querySelector("#chart-age-gender-pyramid"), optionsPyramid);
        chartPyramid.render();

        // ------------------ DPAC RETENTION FUNNEL CHART ------------------
        var optionsRetention = {
            series: [{
                name: 'จำนวนผู้เข้าติดตาม',
                data: [
                    <?= intval($retentionData['total_enrolled']) ?>,
                    <?= intval($retentionData['round1_count']) ?>,
                    <?= intval($retentionData['round2_count']) ?>,
                    <?= intval($retentionData['round3_count']) ?>,
                    <?= intval($retentionData['round4_count']) ?>
                ]
            }],
            chart: {
                type: 'bar',
                height: 240,
                background: 'transparent',
                toolbar: { show: false }
            },
            colors: ['#a78bfa'],
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    columnWidth: '45%',
                    distributed: true
                }
            },
            dataLabels: { enabled: true },
            xaxis: {
                categories: ['ลงทะเบียน', 'รอบ 1', 'รอบ 2', 'รอบ 3', 'รอบ 4'],
                labels: { style: { colors: '#9ca3af' } }
            },
            yaxis: { labels: { style: { colors: '#9ca3af' } } },
            legend: { show: false }
        };
        var chartRetention = new ApexCharts(document.querySelector("#chart-dpac-retention"), optionsRetention);
        chartRetention.render();

        // CSV R2R Export function
        function exportR2RCSV() {
            let csv = "\uFEFF"; // UTF-8 BOM
            csv += "CID,ชื่อ-นามสกุล,บ้านเลขที่,หมู่ที่,หน่วยบริการ,อายุ,คะแนนจัดลำดับติดตาม,ปัจจัยเสี่ยง\n";
            
            const risks = <?= json_encode($topConversionRisks) ?>;
            risks.forEach(r => {
                csv += `"${r.cid}","${r.name}","${r.house_no}","${r.moo}","${r.hc}","${r.age}","${r.score}","${r.factors}"\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", `R2R_Analytics_Export_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // R2R Satisfaction Table Copy Helper
        function copyR2RTable() {
            const table = document.getElementById('r2r-stat-table');
            if (!table) return;
            const rows = table.querySelectorAll('tr');
            let text = "ประเด็นการประเมินตามกรอบแนวคิด (TAM Framework)\tค่าเฉลี่ย (Mean - X̄)\tส่วนเบี่ยงเบนมาตรฐาน (S.D.)\tระดับความพึงพอใจ\n";

            rows.forEach((row, i) => {
                if (i === 0) return; // skip header
                const cols = row.querySelectorAll('td');
                if (cols.length === 4) {
                    const aspect = cols[0].innerText.replace(/[\r\n]/g, " ").replace(/\s+/g, " ").trim();
                    const mean = cols[1].innerText.trim();
                    const sd = cols[2].innerText.trim();
                    const level = cols[3].innerText.trim();
                    text += `${aspect}\t${mean}\t${sd}\t${level}\n`;
                }
            });

            navigator.clipboard.writeText(text).then(() => {
                alert('คัดลอกตารางข้อมูลวิจัย R2R ไปยังคลิปบอร์ดแล้ว! คุณสามารถกดวาง (Ctrl+V) ลงในเอกสาร Microsoft Word หรือ Excel ได้ทันที โดยข้อความจะถูกแปลงเป็นตารางที่จัดคอลัมน์ให้อย่างสมบูรณ์ครับ');
            }).catch(err => {
                alert('ไม่สามารถคัดลอกข้อมูลได้: ' + err);
            });
        }
    </script>
</body>

</html>
