<?php
// admin/index.php
require_once __DIR__ . '/../config/session.php';

// ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/demo_banner.php';

require_once __DIR__ . '/../config/db.php';


// Fetch summary metrics
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$selectedBudgetYear = isset($_SESSION['active_budget_year']) ? (int)$_SESSION['active_budget_year'] : (function_exists('get_current_budget_year') ? get_current_budget_year() : 2026);
if (isset($_GET['budget_year']) && ctype_digit((string)$_GET['budget_year'])) {
    $selectedBudgetYear = (int)$_GET['budget_year'];
    $_SESSION['active_budget_year'] = $selectedBudgetYear;
}

$hc_names = get_health_units();
$isSandboxVal = (function_exists('isSandboxMode') && isSandboxMode($admin_hoscode)) ? 1 : 0;
require_once __DIR__ . '/../config/demo_data.php';

if (DemoDataProvider::isDemoMode()) {
    $mockExec = DemoDataProvider::getMockExecutiveMetrics();
    $mockAnalytics = DemoDataProvider::getMockAnalyticsData();
    $mockTargets = DemoDataProvider::getMockTargets();

    $total_targets_val = $mockExec['total_targets'];
    $screened_val = $mockExec['screened'];
    $screened_percent = $mockExec['screened_percent'];
    $normal_val = $mockExec['normal'];
    $risk_val = $mockExec['risk'];
    $high_risk_val = $mockExec['high_risk'];
    $critical_val = $mockExec['critical'];
    $groupCounts = [
        'group_dm' => 45,
        'group_ht' => 80,
        'group_both' => 125,
        'group_risk' => 250,
        'group_normal' => 30,
        'group_suspected' => 15
    ];
    $village_stats = $mockExec['village_stats'];
    $recent_screenings = $mockExec['recent_screenings'];
    $dpac_followups = $mockExec['dpac_followups'];

    // Map targets
    $allMapTargets = [];
    $latBase = 15.4320;
    $lngBase = 104.9810;
    foreach ($mockTargets as $i => $t) {
        $allMapTargets[] = [
            'cid' => $t['cid'],
            'first_name' => $t['first_name'],
            'last_name' => $t['last_name'],
            'house_no' => $t['house_no'],
            'moo' => $t['moo'],
            'sub_district_code' => '341001',
            'hoscode' => '99999',
            'latitude' => $latBase + ($i * 0.0015),
            'longitude' => $lngBase + ($i * 0.0018),
            'health_status_origin' => $t['health_status_origin'] ?? 'BOTH',
            'sys_bp1' => $t['last_sbp'] ?? 120,
            'dia_bp1' => $t['last_dbp'] ?? 80,
            'dtx_value' => $t['last_dtx'] ?? 100,
            'cv_risk_score' => ($i % 3 == 0) ? 15.5 : (($i % 3 == 1) ? 8.2 : 3.5),
            'bmi' => 24.5
        ];
    }
    $mapData = $allMapTargets;
    $mapHoscodes = ['99999'];
    $editableTargets = $allMapTargets;

    // Charts Data
    $chartCoverageData = [];
    foreach ($village_stats as $vs) {
        $chartCoverageData[] = [
            'hoscode' => '99999',
            'moo' => $vs['moo'],
            'total_targets' => $vs['total'],
            'screened' => $vs['screened'],
            'village_name' => $vs['village_name']
        ];
    }

    $chartRiskData = [
        ['hoscode' => '99999', 'moo' => '1', 'village_name' => 'หมู่ 1 บ้านตาลสุม (จำลอง)', 'high_risk' => 8, 'moderate_risk' => 12, 'normal' => 22, 'unscreened' => 8],
        ['hoscode' => '99999', 'moo' => '2', 'village_name' => 'หมู่ 2 บ้านดอนใหญ่ (จำลอง)', 'high_risk' => 7, 'moderate_risk' => 10, 'normal' => 28, 'unscreened' => 15],
        ['hoscode' => '99999', 'moo' => '3', 'village_name' => 'หมู่ 3 บ้านโคกสว่าง (จำลอง)', 'high_risk' => 5, 'moderate_risk' => 9, 'normal' => 18, 'unscreened' => 13],
        ['hoscode' => '99999', 'moo' => '4', 'village_name' => 'หมู่ 4 บ้านนาเจริญ (จำลอง)', 'high_risk' => 6, 'moderate_risk' => 8, 'normal' => 24, 'unscreened' => 17],
        ['hoscode' => '99999', 'moo' => '5', 'village_name' => 'หมู่ 5 บ้านโนนงาม (จำลอง)', 'high_risk' => 4, 'moderate_risk' => 6, 'normal' => 18, 'unscreened' => 12]
    ];

    $chartDiseaseData = [
        'ht_dm' => 22,
        'ht_only' => 45,
        'dm_only' => 18,
        'risk_group' => 45,
        'normal_group' => 55
    ];

    $chartTrendData = [];
    for ($d = 13; $d >= 0; $d--) {
        $chartTrendData[] = [
            'screen_date' => date('Y-m-d', strtotime("-$d days")),
            'daily_count' => rand(8, 22)
        ];
    }

    $chartSkippedData = [
        ['skipped_reason' => 'ไม่อยู่บ้าน/ไปทำงานต่างจังหวัด', 'count' => 14],
        ['skipped_reason' => 'ปฏิเสธการตรวจ', 'count' => 5],
        ['skipped_reason' => 'ย้ายที่อยู่ชั่วคราว', 'count' => 8]
    ];

    $chartDpacData = [
        ['risk_type' => 'BOTH', 'count' => 24],
        ['risk_type' => 'HT_ONLY', 'count' => 18],
        ['risk_type' => 'DM_ONLY', 'count' => 12]
    ];

    $chartRescreenData = [
        ['hoscode' => '99999', 'moo' => '1', 'village_name' => 'หมู่ 1 บ้านตาลสุม (จำลอง)', 'total_targets' => 50, 'r1_completed' => 42, 'r2_completed' => 18, 'r3_completed' => 4],
        ['hoscode' => '99999', 'moo' => '2', 'village_name' => 'หมู่ 2 บ้านดอนใหญ่ (จำลอง)', 'total_targets' => 60, 'r1_completed' => 45, 'r2_completed' => 16, 'r3_completed' => 2],
        ['hoscode' => '99999', 'moo' => '3', 'village_name' => 'หมู่ 3 บ้านโคกสว่าง (จำลอง)', 'total_targets' => 45, 'r1_completed' => 32, 'r2_completed' => 12, 'r3_completed' => 0],
        ['hoscode' => '99999', 'moo' => '4', 'village_name' => 'หมู่ 4 บ้านนาเจริญ (จำลอง)', 'total_targets' => 55, 'r1_completed' => 38, 'r2_completed' => 10, 'r3_completed' => 1],
        ['hoscode' => '99999', 'moo' => '5', 'village_name' => 'หมู่ 5 บ้านโนนงาม (จำลอง)', 'total_targets' => 40, 'r1_completed' => 28, 'r2_completed' => 8, 'r3_completed' => 0]
    ];

    $chartRound1Count = 185;
    $chartRound2Count = 64;
    $chartRound3Count = 7;
} elseif ($admin_hoscode) {
    $hoscodes = get_query_hoscodes($admin_hoscode);
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));

    $total_targets = $pdo->prepare("SELECT COUNT(*) FROM target_population WHERE hoscode IN ($inPlaceholders) AND (need_screen_dm = 1 OR need_screen_ht = 1)");
    $total_targets->execute($hoscodes);
    $total_targets_val = $total_targets->fetchColumn();

    // Query target groups by health_status_origin
    $groupStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 0 THEN 1 ELSE 0 END) as group_dm,
            SUM(CASE WHEN p.need_screen_dm = 0 AND p.need_screen_ht = 1 THEN 1 ELSE 0 END) as group_ht,
            SUM(CASE WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 1 THEN 1 ELSE 0 END) as group_both,
            SUM(CASE WHEN p.need_screen_dm = 1 OR p.need_screen_ht = 1 THEN 1 ELSE 0 END) as group_risk,
            SUM(CASE WHEN p.health_status_origin = 'NORMAL' AND (p.need_screen_dm = 0 AND p.need_screen_ht = 0) THEN 1 ELSE 0 END) as group_normal,
            SUM(CASE WHEN p.health_status_origin = 'SUSPECT' THEN 1 ELSE 0 END) as group_suspected
        FROM target_population p
        WHERE p.hoscode IN ($inPlaceholders)
    ");
    $groupStmt->execute($hoscodes);
    $groupCounts = $groupStmt->fetch(PDO::FETCH_ASSOC);

    // Detail breakdown per group for modal
    $groupDetailStmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 1 THEN 'BOTH'
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 0 THEN 'DM_ONLY'
                WHEN p.need_screen_dm = 0 AND p.need_screen_ht = 1 THEN 'HT_ONLY'
                WHEN p.health_status_origin = 'SUSPECT' THEN 'SUSPECT'
                ELSE 'NORMAL'
            END as health_status_origin,
            COUNT(*) as count 
        FROM target_population p
        WHERE p.hoscode IN ($inPlaceholders) 
          AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1 OR p.health_status_origin = 'SUSPECT')
        GROUP BY 
            CASE 
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 1 THEN 'BOTH'
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 0 THEN 'DM_ONLY'
                WHEN p.need_screen_dm = 0 AND p.need_screen_ht = 1 THEN 'HT_ONLY'
                WHEN p.health_status_origin = 'SUSPECT' THEN 'SUSPECT'
                ELSE 'NORMAL'
            END
        ORDER BY FIELD(health_status_origin, 'BOTH','DM_ONLY','HT_ONLY','SUSPECT','NORMAL')
    ");
    $groupDetailStmt->execute($hoscodes);
    $groupDetail = $groupDetailStmt->fetchAll(PDO::FETCH_ASSOC);

    $screened = $pdo->prepare("
        SELECT COUNT(DISTINCT p.cid) 
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.assignment_status = 'completed' AND COALESCE(a.is_sandbox, 0) = ?
        LEFT JOIN screening_results s ON (p.cid = s.target_cid OR a.assignment_id = s.assignment_id) AND COALESCE(s.is_sandbox, 0) = ?
        WHERE p.hoscode IN ($inPlaceholders) 
          AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
          AND (a.assignment_id IS NOT NULL OR s.screening_id IS NOT NULL)
    ");
    $screened->execute(array_merge([$isSandboxVal, $isSandboxVal], $hoscodes));
    $screened_val = $screened->fetchColumn();

    $pending = $pdo->prepare("SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'pending' AND p.hoscode IN ($inPlaceholders) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)");
    $pending->execute($hoscodes);
    $pending_val = $pending->fetchColumn();

    $skipped = $pdo->prepare("SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholders) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)");
    $skipped->execute($hoscodes);
    $skipped_val = $skipped->fetchColumn();

    $rewards = $pdo->prepare("
        SELECT SUM(r.points_earned) 
        FROM vhv_rewards r 
        JOIN vhv_users v ON r.vhv_id = v.vhv_id 
        LEFT JOIN task_assignments ta ON r.assignment_id = ta.assignment_id
        LEFT JOIN dpac_followups f ON r.followup_id = f.followup_id
        WHERE v.hoscode IN ($inPlaceholders) 
          AND v.approved = 1
          AND r.approval_status IN ('approved', 'waiting')
          AND ((r.followup_id IS NULL AND r.assignment_id IS NULL) OR (r.followup_id IS NULL AND ta.assignment_id IS NOT NULL) OR (r.followup_id IS NOT NULL AND f.followup_id IS NOT NULL))
    ");
    $rewards->execute($hoscodes);
    $rewards_val = $rewards->fetchColumn() ?: 0;

    $total_vhvs = $pdo->prepare("SELECT COUNT(*) FROM vhv_users WHERE hoscode IN ($inPlaceholders)");
    $total_vhvs->execute($hoscodes);
    $total_vhvs_val = $total_vhvs->fetchColumn();

    $metrics = [
        'total_targets' => $total_targets_val,
        'group_risk' => $groupCounts['group_risk'] ?? 0,
        'group_dm' => $groupCounts['group_dm'] ?? 0,
        'group_ht' => $groupCounts['group_ht'] ?? 0,
        'group_both' => $groupCounts['group_both'] ?? 0,
        'group_normal' => $groupCounts['group_normal'] ?? 0,
        'group_suspected' => $groupCounts['group_suspected'] ?? 0,
        'screened_count' => $screened_val,
        'pending_count' => $pending_val,
        'skipped_count' => $skipped_val,
        'total_points' => $rewards_val,
        'total_vhvs' => $total_vhvs_val
    ];

    // Card 1 Detail: Targets per village (moo) and health status origin
    $mooQuery = $pdo->prepare("
        SELECT 
            p.hoscode, 
            p.moo, 
            CASE 
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 1 THEN 'BOTH'
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 0 THEN 'DM_ONLY'
                WHEN p.need_screen_dm = 0 AND p.need_screen_ht = 1 THEN 'HT_ONLY'
                WHEN p.health_status_origin = 'SUSPECT' THEN 'SUSPECT'
                ELSE 'NORMAL'
            END as health_status_origin,
            COUNT(*) as count 
        FROM target_population p
        WHERE p.hoscode IN ($inPlaceholders) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1 OR p.health_status_origin = 'SUSPECT') 
        GROUP BY 
            p.hoscode, 
            p.moo,
            CASE 
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 1 THEN 'BOTH'
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 0 THEN 'DM_ONLY'
                WHEN p.need_screen_dm = 0 AND p.need_screen_ht = 1 THEN 'HT_ONLY'
                WHEN p.health_status_origin = 'SUSPECT' THEN 'SUSPECT'
                ELSE 'NORMAL'
            END
        ORDER BY p.moo
    ");
    $mooQuery->execute($hoscodes);
    $targetsDetail = $mooQuery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($targetsDetail as &$row) {
        $row['village_name'] = get_village_display_name_by_hoscode($row['hoscode'], $row['moo']);
    }
    unset($row);

    // Card 2 Detail: Screened cases risk distribution
    $screenedDetailQuery = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126 THEN 1 ELSE 0 END) as high_risk,
            SUM(CASE WHEN NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) 
                      AND ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 1 ELSE 0 END) as risk,
            SUM(CASE WHEN NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) 
                      AND NOT ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 1 ELSE 0 END) as normal
        FROM target_population p
        JOIN screening_results s ON s.screening_id = (
            SELECT sr.screening_id FROM screening_results sr 
            LEFT JOIN task_assignments ta2 ON sr.assignment_id = ta2.assignment_id
            WHERE sr.target_cid = p.cid OR ta2.target_cid = p.cid
            ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1
        )
        WHERE p.hoscode IN ($inPlaceholders) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ");
    $screenedDetailQuery->execute($hoscodes);
    $screenedDetail = $screenedDetailQuery->fetch(PDO::FETCH_ASSOC);

    // Card 3 Detail: Skipped reasons
    $skippedDetailQuery = $pdo->prepare("
        SELECT s.skipped_reason, COUNT(*) as count 
        FROM screening_results s 
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholders) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY s.skipped_reason
    ");
    $skippedDetailQuery->execute($hoscodes);
    $skippedDetail = $skippedDetailQuery->fetchAll(PDO::FETCH_ASSOC);

    // Card pending Detail
    $pendingDetailQuery = $pdo->prepare("
        SELECT 
            p.hoscode, 
            p.moo, 
            COUNT(*) as count 
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        WHERE p.hoscode IN ($inPlaceholders) AND a.assignment_status = 'pending' AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY p.hoscode, p.moo
        ORDER BY p.moo
    ");
    $pendingDetailQuery->execute($hoscodes);
    $pendingDetail = $pendingDetailQuery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pendingDetail as &$row) {
        $row['village_name'] = get_village_display_name_by_hoscode($row['hoscode'], $row['moo']);
    }
    unset($row);

    // Card 4 Detail: Top VHVs by rewards
    $rewardsDetailQuery = $pdo->prepare("
        SELECT v.vhv_name, SUM(r.points_earned) as total_points
        FROM vhv_rewards r
        JOIN vhv_users v ON r.vhv_id = v.vhv_id
        LEFT JOIN task_assignments ta ON r.assignment_id = ta.assignment_id
        LEFT JOIN dpac_followups f ON r.followup_id = f.followup_id
        WHERE v.hoscode IN ($inPlaceholders) 
          AND v.approved = 1
          AND r.approval_status IN ('approved', 'waiting')
          AND ((r.followup_id IS NULL AND r.assignment_id IS NULL) OR (r.followup_id IS NULL AND ta.assignment_id IS NOT NULL) OR (r.followup_id IS NOT NULL AND f.followup_id IS NOT NULL))
        GROUP BY v.vhv_id, v.vhv_name
        ORDER BY total_points DESC
        LIMIT 10
    ");
    $rewardsDetailQuery->execute($hoscodes);
    $rewardsDetail = $rewardsDetailQuery->fetchAll(PDO::FETCH_ASSOC);

    // Heatmap - Get ALL targets with coordinates + screening results for risk classification
    $mapDataStmt = $pdo->prepare("
        SELECT p.cid, p.latitude, p.longitude, p.house_no, p.moo, p.sub_district_code, p.hoscode,
               p.first_name, p.last_name, p.health_status_origin,
               s.sys_bp1, s.dia_bp1, s.dtx_value, s.cv_risk_score, s.bmi
        FROM target_population p
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND a.assignment_status = 'completed'
        LEFT JOIN screening_results s ON s.assignment_id = a.assignment_id
        WHERE p.latitude IS NOT NULL 
          AND p.longitude IS NOT NULL
          AND p.hoscode IN ($inPlaceholders)
          AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ");
    $mapDataStmt->execute($hoscodes);
    $allMapTargets = $mapDataStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique hoscodes with data for filter buttons
    $hosFilterStmt = $pdo->prepare("
        SELECT DISTINCT p.hoscode 
        FROM target_population p 
        WHERE p.latitude IS NOT NULL AND p.longitude IS NOT NULL
          AND p.hoscode IN ($inPlaceholders)
        ORDER BY p.hoscode
    ");
    $hosFilterStmt->execute($hoscodes);
    $mapHoscodesRaw = $hosFilterStmt->fetchAll(PDO::FETCH_COLUMN);
    $mapHoscodes = $mapHoscodesRaw;

    // For coordinate editing: get all targets (including those without coords)
    $editTargetsStmt = $pdo->prepare("
        SELECT cid, first_name, last_name, house_no, moo, sub_district_code, hoscode, latitude, longitude
        FROM target_population 
        WHERE hoscode IN ($inPlaceholders) AND (need_screen_dm = 1 OR need_screen_ht = 1)
        ORDER BY moo, house_no
    ");
    $editTargetsStmt->execute($hoscodes);
    $editableTargets = $editTargetsStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- NEW CHARTS DATA (ADMIN) ---
    $chartCoverageStmt = $pdo->prepare("
        SELECT 
            COALESCE(v.hoscode, p.hoscode) as hoscode, 
            p.moo,
            COUNT(DISTINCT p.cid) as total_targets,
            COUNT(DISTINCT CASE WHEN s.screening_id IS NOT NULL OR a.assignment_status = 'completed' THEN p.cid END) as screened
        FROM target_population p
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND COALESCE(a.is_sandbox, 0) = ?
        LEFT JOIN screening_results s ON (s.target_cid = p.cid OR s.assignment_id = a.assignment_id) AND COALESCE(s.is_sandbox, 0) = ?
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
          AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholders)
        GROUP BY COALESCE(v.hoscode, p.hoscode), p.moo
        ORDER BY COALESCE(v.hoscode, p.hoscode), p.moo
    ");
    $chartCoverageStmt->execute(array_merge([$isSandboxVal, $isSandboxVal], $hoscodes));
    $chartCoverageData = $chartCoverageStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($chartCoverageData as &$row) {
        $row['village_name'] = get_village_display_name_by_hoscode($row['hoscode'], $row['moo']);
    }
    unset($row);

    $chartRiskStmt = $pdo->prepare("
        SELECT 
            COALESCE(v.hoscode, p.hoscode) as hoscode, 
            MAX(p.sub_district_code) as sub_district_code, 
            p.moo,
            COUNT(DISTINCT CASE WHEN s.screening_id IS NOT NULL AND (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) THEN p.cid END) as high_risk,
            COUNT(DISTINCT CASE WHEN s.screening_id IS NOT NULL AND NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN p.cid END) as moderate_risk,
            COUNT(DISTINCT CASE WHEN s.screening_id IS NOT NULL AND NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND NOT ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN p.cid END) as normal,
            COUNT(DISTINCT CASE WHEN s.screening_id IS NULL THEN p.cid END) as unscreened
        FROM target_population p
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND COALESCE(a.is_sandbox, 0) = ?
        LEFT JOIN screening_results s ON s.screening_id = (
            SELECT sr.screening_id FROM screening_results sr 
            LEFT JOIN task_assignments ta2 ON sr.assignment_id = ta2.assignment_id
            WHERE (sr.target_cid = p.cid OR ta2.target_cid = p.cid) AND COALESCE(sr.is_sandbox, 0) = ?
            ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1
        )
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1) 
          AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholders)
        GROUP BY COALESCE(v.hoscode, p.hoscode), p.moo
        ORDER BY COALESCE(v.hoscode, p.hoscode), p.moo
    ");
    $chartRiskStmt->execute(array_merge([$isSandboxVal, $isSandboxVal], $hoscodes));
    $chartRiskData = $chartRiskStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($chartRiskData as &$row) {
        $row['village_name'] = get_village_display_name_by_hoscode($row['hoscode'], $row['moo']);
    }
    unset($row);

    // Risk by round data (Admin)
    $chartRiskByRoundStmt = $pdo->prepare("
        SELECT 
            COALESCE(v.hoscode, p.hoscode) as hoscode, 
            p.moo,
            COALESCE(s.round_number, 1) as round_number,
            COUNT(DISTINCT CASE WHEN (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) THEN p.cid END) as high_risk,
            COUNT(DISTINCT CASE WHEN NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN p.cid END) as moderate_risk,
            COUNT(DISTINCT CASE WHEN NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND NOT ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN p.cid END) as normal
        FROM target_population p
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND COALESCE(a.is_sandbox, 0) = ?
        JOIN screening_results s ON (s.target_cid = p.cid OR s.assignment_id = a.assignment_id) AND COALESCE(s.is_sandbox, 0) = ?
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
          AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholders)
        GROUP BY COALESCE(v.hoscode, p.hoscode), p.moo, COALESCE(s.round_number, 1)
    ");
    $chartRiskByRoundStmt->execute(array_merge([$isSandboxVal, $isSandboxVal], $hoscodes));
    $chartRiskByRoundData = $chartRiskByRoundStmt->fetchAll(PDO::FETCH_ASSOC);

    $chartDiseaseStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as ht_dm,
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND (s.dtx_value < 126 OR s.dtx_value IS NULL) THEN 1 ELSE 0 END) as ht_only,
            SUM(CASE WHEN (s.sys_bp1 < 140 AND s.dia_bp1 < 90) AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as dm_only,
            SUM(CASE WHEN (s.sys_bp1 < 140 AND s.dia_bp1 < 90) AND (s.dtx_value < 126 OR s.dtx_value IS NULL) 
                      AND ((s.sys_bp1 >= 120) OR (s.dia_bp1 >= 80) OR (s.dtx_value >= 100) OR (s.cv_risk_score >= 10)) THEN 1 ELSE 0 END) as risk_group,
            SUM(CASE WHEN (s.sys_bp1 < 120 AND s.dia_bp1 < 80) AND (s.dtx_value < 100 OR s.dtx_value IS NULL) AND (s.cv_risk_score < 10 OR s.cv_risk_score IS NULL) THEN 1 ELSE 0 END) as normal_group
        FROM target_population p
        JOIN screening_results s ON s.screening_id = (
            SELECT sr.screening_id FROM screening_results sr 
            LEFT JOIN task_assignments ta2 ON sr.assignment_id = ta2.assignment_id
            WHERE sr.target_cid = p.cid OR ta2.target_cid = p.cid
            ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1
        )
        WHERE p.hoscode IN ($inPlaceholders) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ");
    $chartDiseaseStmt->execute($hoscodes);
    $chartDiseaseData = $chartDiseaseStmt->fetch(PDO::FETCH_ASSOC);

    $chartTrendStmt = $pdo->prepare("
        SELECT DATE(created_at) as screen_date, COUNT(*) as daily_count
        FROM (
            SELECT s.created_at
            FROM screening_results s
            LEFT JOIN task_assignments a ON s.assignment_id = a.assignment_id
            JOIN target_population p ON (s.target_cid = p.cid OR a.target_cid = p.cid)
            WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
              AND p.hoscode IN ($inPlaceholders)
              AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
            UNION ALL
            SELECT f.completed_at as created_at
            FROM dpac_followups f
            JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
            JOIN target_population p ON e.cid = p.cid
            WHERE f.status = 'completed'
              AND p.hoscode IN ($inPlaceholders)
              AND f.completed_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
              AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        ) as combined
        GROUP BY DATE(created_at)
        ORDER BY screen_date ASC
    ");
    $chartTrendStmt->execute(array_merge($hoscodes, $hoscodes));
    $chartTrendData = $chartTrendStmt->fetchAll(PDO::FETCH_ASSOC);

    // Skipped Reasons Data
    $chartSkippedStmt = $pdo->prepare("
        SELECT s.skipped_reason, COUNT(*) as count 
        FROM screening_results s 
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholders) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY s.skipped_reason
    ");
    $chartSkippedStmt->execute($hoscodes);
    $chartSkippedData = $chartSkippedStmt->fetchAll(PDO::FETCH_ASSOC);

    // DPAC Enrollments Data
    $chartDpacStmt = $pdo->prepare("
        SELECT e.risk_type, COUNT(*) as count 
        FROM dpac_enrollments e
        JOIN target_population p ON e.cid = p.cid
        WHERE p.hoscode IN ($inPlaceholders) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY e.risk_type
    ");
    $chartDpacStmt->execute($hoscodes);
    $chartDpacData = $chartDpacStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- RE-SCREENING MULTI-ROUND CHARTS DATA (ADMIN) ---
    $chartRescreenStmt = $pdo->prepare("
        SELECT 
            COALESCE(v.hoscode, p.hoscode) as hoscode, 
            p.moo,
            COUNT(DISTINCT p.cid) as total_targets,
            COUNT(DISTINCT CASE WHEN (s.screening_id IS NOT NULL AND (s.round_number = 1 OR s.round_number IS NULL)) OR (a.round_number = 1 AND a.assignment_status = 'completed') THEN p.cid END) as r1_completed,
            COUNT(DISTINCT CASE WHEN (s.screening_id IS NOT NULL AND s.round_number = 2) OR (a.round_number = 2 AND a.assignment_status = 'completed') THEN p.cid END) as r2_completed,
            COUNT(DISTINCT CASE WHEN (s.screening_id IS NOT NULL AND s.round_number >= 3) OR (a.round_number >= 3 AND a.assignment_status = 'completed') THEN p.cid END) as r3_completed,
            COUNT(DISTINCT CASE WHEN a.round_number = 2 AND a.assignment_status = 'pending' THEN p.cid END) as r2_assigned,
            COUNT(DISTINCT CASE WHEN a.round_number >= 3 AND a.assignment_status = 'pending' THEN p.cid END) as r3_assigned
        FROM target_population p
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND COALESCE(a.is_sandbox, 0) = ?
        LEFT JOIN screening_results s ON (s.target_cid = p.cid OR s.assignment_id = a.assignment_id) AND COALESCE(s.is_sandbox, 0) = ?
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
          AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholders)
        GROUP BY COALESCE(v.hoscode, p.hoscode), p.moo
        ORDER BY COALESCE(v.hoscode, p.hoscode), p.moo
    ");
    $chartRescreenStmt->execute(array_merge([$isSandboxVal, $isSandboxVal], $hoscodes));
    $chartRescreenData = $chartRescreenStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($chartRescreenData as &$row) {
        $row['village_name'] = get_village_display_name_by_hoscode($row['hoscode'], $row['moo']);
    }
    unset($row);
    $metrics['r1_completed'] = array_sum(array_column($chartRescreenData, 'r1_completed'));
    $metrics['r2_completed'] = array_sum(array_column($chartRescreenData, 'r2_completed'));
    $metrics['r3_completed'] = array_sum(array_column($chartRescreenData, 'r3_completed'));
} else {
    $valid_hoscodes = get_query_hoscodes();
    $inPlaceholdersSa = implode(',', array_fill(0, count($valid_hoscodes), '?'));

    $metricsStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM target_population WHERE hoscode IN ($inPlaceholdersSa) AND (need_screen_dm = 1 OR need_screen_ht = 1)) as total_targets,
            (SELECT COUNT(DISTINCT p.cid) FROM target_population p LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.assignment_status = 'completed' AND COALESCE(a.is_sandbox, 0) = ? LEFT JOIN screening_results s ON (p.cid = s.target_cid OR a.assignment_id = s.assignment_id) AND COALESCE(s.is_sandbox, 0) = ? WHERE p.hoscode IN ($inPlaceholdersSa) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1) AND (a.assignment_id IS NOT NULL OR s.screening_id IS NOT NULL)) as screened_count,
            (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'pending' AND p.hoscode IN ($inPlaceholdersSa) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)) as pending_count,
            (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholdersSa) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)) as skipped_count,
            (SELECT SUM(r.points_earned) FROM vhv_rewards r JOIN vhv_users v ON r.vhv_id = v.vhv_id LEFT JOIN task_assignments ta ON r.assignment_id = ta.assignment_id LEFT JOIN dpac_followups f ON r.followup_id = f.followup_id WHERE v.hoscode IN ($inPlaceholdersSa) AND v.approved = 1 AND r.approval_status IN ('approved', 'waiting') AND ((r.followup_id IS NULL AND r.assignment_id IS NULL) OR (r.followup_id IS NULL AND ta.assignment_id IS NOT NULL) OR (r.followup_id IS NOT NULL AND f.followup_id IS NOT NULL))) as total_points,
            (SELECT COUNT(*) FROM vhv_users WHERE hoscode IN ($inPlaceholdersSa)) as total_vhvs
    ");
    // Duplicate array parameters for the 6 subqueries
    $metricsParams = array_merge($valid_hoscodes, [$isSandboxVal, $isSandboxVal], $valid_hoscodes, $valid_hoscodes, $valid_hoscodes, $valid_hoscodes, $valid_hoscodes);
    $metricsStmt->execute($metricsParams);
    $metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC);

    // Query target groups by health_status_origin
    $groupStmtSa = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 0 THEN 1 ELSE 0 END) as group_dm,
            SUM(CASE WHEN p.need_screen_dm = 0 AND p.need_screen_ht = 1 THEN 1 ELSE 0 END) as group_ht,
            SUM(CASE WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 1 THEN 1 ELSE 0 END) as group_both,
            SUM(CASE WHEN p.need_screen_dm = 1 OR p.need_screen_ht = 1 THEN 1 ELSE 0 END) as group_risk,
            SUM(CASE WHEN p.health_status_origin = 'NORMAL' AND (p.need_screen_dm = 0 AND p.need_screen_ht = 0) THEN 1 ELSE 0 END) as group_normal,
            SUM(CASE WHEN p.health_status_origin = 'SUSPECT' THEN 1 ELSE 0 END) as group_suspected
        FROM target_population p
        WHERE p.hoscode IN ($inPlaceholdersSa)
    ");
    $groupStmtSa->execute($valid_hoscodes);
    $groupCounts = $groupStmtSa->fetch(PDO::FETCH_ASSOC);
    $metrics['group_risk'] = $groupCounts['group_risk'] ?? 0;
    $metrics['group_dm'] = $groupCounts['group_dm'] ?? 0;
    $metrics['group_ht'] = $groupCounts['group_ht'] ?? 0;
    $metrics['group_both'] = $groupCounts['group_both'] ?? 0;
    $metrics['group_normal'] = $groupCounts['group_normal'] ?? 0;
    $metrics['group_suspected'] = $groupCounts['group_suspected'] ?? 0;

    // Detail breakdown per group for modal
    $groupDetailStmtSa = $pdo->prepare("
        SELECT 
            CASE 
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 1 THEN 'BOTH'
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 0 THEN 'DM_ONLY'
                WHEN p.need_screen_dm = 0 AND p.need_screen_ht = 1 THEN 'HT_ONLY'
                WHEN p.health_status_origin = 'SUSPECT' THEN 'SUSPECT'
                ELSE 'NORMAL'
            END as health_status_origin,
            COUNT(*) as count 
        FROM target_population p
        WHERE p.hoscode IN ($inPlaceholdersSa) 
          AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1 OR p.health_status_origin = 'SUSPECT')
        GROUP BY 
            CASE 
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 1 THEN 'BOTH'
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 0 THEN 'DM_ONLY'
                WHEN p.need_screen_dm = 0 AND p.need_screen_ht = 1 THEN 'HT_ONLY'
                WHEN p.health_status_origin = 'SUSPECT' THEN 'SUSPECT'
                ELSE 'NORMAL'
            END
        ORDER BY FIELD(health_status_origin, 'BOTH','DM_ONLY','HT_ONLY','SUSPECT','NORMAL')
    ");
    $groupDetailStmtSa->execute($valid_hoscodes);
    $groupDetail = $groupDetailStmtSa->fetchAll(PDO::FETCH_ASSOC);

    // Card 1 Detail: Targets per hoscode and health status origin
    $targetsDetailStmt = $pdo->prepare("
        SELECT 
            p.hoscode, 
            CASE 
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 1 THEN 'BOTH'
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 0 THEN 'DM_ONLY'
                WHEN p.need_screen_dm = 0 AND p.need_screen_ht = 1 THEN 'HT_ONLY'
                WHEN p.health_status_origin = 'SUSPECT' THEN 'SUSPECT'
                ELSE 'NORMAL'
            END as health_status_origin,
            COUNT(*) as count 
        FROM target_population p
        WHERE p.hoscode IN ($inPlaceholdersSa) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1 OR p.health_status_origin = 'SUSPECT') 
        GROUP BY 
            p.hoscode,
            CASE 
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 1 THEN 'BOTH'
                WHEN p.need_screen_dm = 1 AND p.need_screen_ht = 0 THEN 'DM_ONLY'
                WHEN p.need_screen_dm = 0 AND p.need_screen_ht = 1 THEN 'HT_ONLY'
                WHEN p.health_status_origin = 'SUSPECT' THEN 'SUSPECT'
                ELSE 'NORMAL'
            END
        ORDER BY p.hoscode
    ");
    $targetsDetailStmt->execute($valid_hoscodes);
    $targetsDetail = $targetsDetailStmt->fetchAll(PDO::FETCH_ASSOC);

    // Card 2 Detail: Screened cases risk distribution
    $screenedDetailStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126 THEN 1 ELSE 0 END) as high_risk,
            SUM(CASE WHEN NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) 
                      AND ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 1 ELSE 0 END) as risk,
            SUM(CASE WHEN NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) 
                      AND NOT ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 1 ELSE 0 END) as normal
        FROM target_population p
        JOIN screening_results s ON s.screening_id = (
            SELECT sr.screening_id FROM screening_results sr 
            LEFT JOIN task_assignments ta2 ON sr.assignment_id = ta2.assignment_id
            WHERE sr.target_cid = p.cid OR ta2.target_cid = p.cid
            ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1
        )
        WHERE p.hoscode IN ($inPlaceholdersSa) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ");
    $screenedDetailStmt->execute($valid_hoscodes);
    $screenedDetail = $screenedDetailStmt->fetch(PDO::FETCH_ASSOC);

    // Card 3 Detail: Skipped reasons
    $skippedDetailStmt = $pdo->prepare("
        SELECT s.skipped_reason, COUNT(*) as count 
        FROM screening_results s 
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholdersSa) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY s.skipped_reason
    ");
    $skippedDetailStmt->execute($valid_hoscodes);
    $skippedDetail = $skippedDetailStmt->fetchAll(PDO::FETCH_ASSOC);

    // Card pending Detail (Super Admin)
    $pendingDetailStmt = $pdo->prepare("
        SELECT 
            p.hoscode, 
            COUNT(*) as count 
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        WHERE p.hoscode IN ($inPlaceholdersSa) AND a.assignment_status = 'pending' AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY p.hoscode
        ORDER BY hoscode
    ");
    $pendingDetailStmt->execute($valid_hoscodes);
    $pendingDetail = $pendingDetailStmt->fetchAll(PDO::FETCH_ASSOC);

    // Card 4 Detail: Top VHVs by rewards
    $rewardsDetailStmt = $pdo->prepare("
        SELECT v.vhv_name, SUM(r.points_earned) as total_points
        FROM vhv_rewards r
        JOIN vhv_users v ON r.vhv_id = v.vhv_id
        LEFT JOIN task_assignments ta ON r.assignment_id = ta.assignment_id
        LEFT JOIN dpac_followups f ON r.followup_id = f.followup_id
        WHERE v.hoscode IN ($inPlaceholdersSa) 
          AND v.approved = 1
          AND r.approval_status IN ('approved', 'waiting')
          AND ((r.followup_id IS NULL AND r.assignment_id IS NULL) OR (r.followup_id IS NULL AND ta.assignment_id IS NOT NULL) OR (r.followup_id IS NOT NULL AND f.followup_id IS NOT NULL))
        GROUP BY v.vhv_id, v.vhv_name
        ORDER BY total_points DESC
        LIMIT 10
    ");
    $rewardsDetailStmt->execute($valid_hoscodes);
    $rewardsDetail = $rewardsDetailStmt->fetchAll(PDO::FETCH_ASSOC);

    $heatmapStmt = $pdo->prepare("
        SELECT p.cid, p.latitude, p.longitude, p.house_no, p.moo, p.sub_district_code, p.hoscode,
               p.first_name, p.last_name, p.health_status_origin,
               s.sys_bp1, s.dia_bp1, s.dtx_value, s.cv_risk_score, s.bmi
        FROM target_population p
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND a.assignment_status = 'completed'
        LEFT JOIN screening_results s ON s.assignment_id = a.assignment_id
        WHERE p.latitude IS NOT NULL 
          AND p.longitude IS NOT NULL
          AND p.hoscode IN ($inPlaceholdersSa)
          AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ");
    $heatmapStmt->execute($valid_hoscodes);
    $allMapTargets = $heatmapStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique hoscodes with data for filter buttons
    $mapHoscodesStmt = $pdo->prepare("
        SELECT DISTINCT hoscode FROM target_population 
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL
          AND hoscode IN ($inPlaceholdersSa)
        ORDER BY hoscode
    ");
    $mapHoscodesStmt->execute($valid_hoscodes);
    $mapHoscodesRaw = $mapHoscodesStmt->fetchAll(PDO::FETCH_COLUMN);
    $mapHoscodes = $mapHoscodesRaw;

    // For coordinate editing: get all targets
    $editableTargetsStmt = $pdo->prepare("
        SELECT cid, first_name, last_name, house_no, moo, sub_district_code, hoscode, latitude, longitude
        FROM target_population 
        WHERE hoscode IN ($inPlaceholdersSa) AND (need_screen_dm = 1 OR need_screen_ht = 1)
        ORDER BY moo, house_no
    ");
    $editableTargetsStmt->execute($valid_hoscodes);
    $editableTargets = $editableTargetsStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- NEW CHARTS DATA (SUPER ADMIN) ---
    $chartCoverageStmt = $pdo->prepare("
        SELECT 
            COALESCE(v.hoscode, p.hoscode) as hoscode, 
            COUNT(DISTINCT p.cid) as total_targets,
            COUNT(DISTINCT CASE WHEN s.screening_id IS NOT NULL OR a.assignment_status = 'completed' THEN p.cid END) as screened
        FROM target_population p
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND COALESCE(a.is_sandbox, 0) = ?
        LEFT JOIN screening_results s ON (s.target_cid = p.cid OR s.assignment_id = a.assignment_id) AND COALESCE(s.is_sandbox, 0) = ?
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
          AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholdersSa)
        GROUP BY COALESCE(v.hoscode, p.hoscode)
        ORDER BY COALESCE(v.hoscode, p.hoscode)
    ");
    $chartCoverageStmt->execute(array_merge([$isSandboxVal, $isSandboxVal], $valid_hoscodes));
    $chartCoverageData = $chartCoverageStmt->fetchAll(PDO::FETCH_ASSOC);

    $chartRiskStmt = $pdo->prepare("
        SELECT 
            COALESCE(v.hoscode, p.hoscode) as hoscode,
            COUNT(DISTINCT CASE WHEN s.screening_id IS NOT NULL AND (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) THEN p.cid END) as high_risk,
            COUNT(DISTINCT CASE WHEN s.screening_id IS NOT NULL AND NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN p.cid END) as moderate_risk,
            COUNT(DISTINCT CASE WHEN s.screening_id IS NOT NULL AND NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND NOT ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN p.cid END) as normal,
            COUNT(DISTINCT CASE WHEN s.screening_id IS NULL THEN p.cid END) as unscreened
        FROM target_population p
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND COALESCE(a.is_sandbox, 0) = ?
        LEFT JOIN screening_results s ON s.screening_id = (
            SELECT sr.screening_id FROM screening_results sr 
            LEFT JOIN task_assignments ta2 ON sr.assignment_id = ta2.assignment_id
            WHERE (sr.target_cid = p.cid OR ta2.target_cid = p.cid) AND COALESCE(sr.is_sandbox, 0) = ?
            ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1
        )
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
          AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholdersSa)
        GROUP BY COALESCE(v.hoscode, p.hoscode)
        ORDER BY COALESCE(v.hoscode, p.hoscode)
    ");
    $chartRiskStmt->execute(array_merge([$isSandboxVal, $isSandboxVal], $valid_hoscodes));
    $chartRiskData = $chartRiskStmt->fetchAll(PDO::FETCH_ASSOC);

    // Risk by round data (Super Admin)
    $chartRiskByRoundStmt = $pdo->prepare("
        SELECT 
            COALESCE(v.hoscode, p.hoscode) as hoscode,
            COALESCE(s.round_number, 1) as round_number,
            COUNT(DISTINCT CASE WHEN (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) THEN p.cid END) as high_risk,
            COUNT(DISTINCT CASE WHEN NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN p.cid END) as moderate_risk,
            COUNT(DISTINCT CASE WHEN NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND NOT ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN p.cid END) as normal
        FROM target_population p
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND COALESCE(a.is_sandbox, 0) = ?
        JOIN screening_results s ON (s.target_cid = p.cid OR s.assignment_id = a.assignment_id) AND COALESCE(s.is_sandbox, 0) = ?
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
          AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholdersSa)
        GROUP BY COALESCE(v.hoscode, p.hoscode), COALESCE(s.round_number, 1)
    ");
    $chartRiskByRoundStmt->execute(array_merge([$isSandboxVal, $isSandboxVal], $valid_hoscodes));
    $chartRiskByRoundData = $chartRiskByRoundStmt->fetchAll(PDO::FETCH_ASSOC);

    $chartDiseaseStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as ht_dm,
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND (s.dtx_value < 126 OR s.dtx_value IS NULL) THEN 1 ELSE 0 END) as ht_only,
            SUM(CASE WHEN (s.sys_bp1 < 140 AND s.dia_bp1 < 90) AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as dm_only,
            SUM(CASE WHEN (s.sys_bp1 < 140 AND s.dia_bp1 < 90) AND (s.dtx_value < 126 OR s.dtx_value IS NULL) 
                      AND ((s.sys_bp1 >= 120) OR (s.dia_bp1 >= 80) OR (s.dtx_value >= 100) OR (s.cv_risk_score >= 10)) THEN 1 ELSE 0 END) as risk_group,
            SUM(CASE WHEN (s.sys_bp1 < 120 AND s.dia_bp1 < 80) AND (s.dtx_value < 100 OR s.dtx_value IS NULL) AND (s.cv_risk_score < 10 OR s.cv_risk_score IS NULL) THEN 1 ELSE 0 END) as normal_group
        FROM target_population p
        JOIN screening_results s ON s.screening_id = (
            SELECT sr.screening_id FROM screening_results sr 
            LEFT JOIN task_assignments ta2 ON sr.assignment_id = ta2.assignment_id
            WHERE sr.target_cid = p.cid OR ta2.target_cid = p.cid
            ORDER BY sr.created_at DESC, sr.screening_id DESC LIMIT 1
        )
        WHERE p.hoscode IN ($inPlaceholdersSa) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ");
    $chartDiseaseStmt->execute($valid_hoscodes);
    $chartDiseaseData = $chartDiseaseStmt->fetch(PDO::FETCH_ASSOC);

    $chartTrendStmt = $pdo->prepare("
        SELECT DATE(created_at) as screen_date, COUNT(*) as daily_count
        FROM (
            SELECT s.created_at
            FROM screening_results s
            LEFT JOIN task_assignments a ON s.assignment_id = a.assignment_id
            JOIN target_population p ON (s.target_cid = p.cid OR a.target_cid = p.cid)
            WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
              AND p.hoscode IN ($inPlaceholdersSa)
              AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
            UNION ALL
            SELECT f.completed_at as created_at
            FROM dpac_followups f
            JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
            JOIN target_population p ON e.cid = p.cid
            WHERE f.status = 'completed'
              AND f.completed_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
              AND p.hoscode IN ($inPlaceholdersSa)
              AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        ) as combined
        GROUP BY DATE(created_at)
        ORDER BY screen_date ASC
    ");
    $chartTrendStmt->execute(array_merge($valid_hoscodes, $valid_hoscodes));
    $chartTrendData = $chartTrendStmt->fetchAll(PDO::FETCH_ASSOC);

    // Skipped Reasons Data
    $chartSkippedStmt = $pdo->prepare("
        SELECT s.skipped_reason, COUNT(*) as count 
        FROM screening_results s 
        LEFT JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON (s.target_cid = p.cid OR a.target_cid = p.cid)
        WHERE (a.assignment_status = 'skipped' OR s.skipped_reason IS NOT NULL) AND p.hoscode IN ($inPlaceholdersSa) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY s.skipped_reason
    ");
    $chartSkippedStmt->execute($valid_hoscodes);
    $chartSkippedData = $chartSkippedStmt->fetchAll(PDO::FETCH_ASSOC);

    // DPAC Enrollments Data
    $chartDpacStmt = $pdo->prepare("
        SELECT e.risk_type, COUNT(*) as count 
        FROM dpac_enrollments e
        JOIN target_population p ON e.cid = p.cid
        WHERE p.hoscode IN ($inPlaceholdersSa) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY e.risk_type
    ");
    $chartDpacStmt->execute($valid_hoscodes);
    $chartDpacData = $chartDpacStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- RE-SCREENING MULTI-ROUND CHARTS DATA (SUPER ADMIN) ---
    $chartRescreenStmt = $pdo->prepare("
        SELECT 
            COALESCE(v.hoscode, p.hoscode) as hoscode,
            COUNT(DISTINCT p.cid) as total_targets,
            COUNT(DISTINCT CASE WHEN (s.screening_id IS NOT NULL AND (s.round_number = 1 OR s.round_number IS NULL)) OR (a.round_number = 1 AND a.assignment_status = 'completed') THEN p.cid END) as r1_completed,
            COUNT(DISTINCT CASE WHEN (s.screening_id IS NOT NULL AND s.round_number = 2) OR (a.round_number = 2 AND a.assignment_status = 'completed') THEN p.cid END) as r2_completed,
            COUNT(DISTINCT CASE WHEN (s.screening_id IS NOT NULL AND s.round_number >= 3) OR (a.round_number >= 3 AND a.assignment_status = 'completed') THEN p.cid END) as r3_completed,
            COUNT(DISTINCT CASE WHEN a.round_number = 2 AND a.assignment_status = 'pending' THEN p.cid END) as r2_assigned,
            COUNT(DISTINCT CASE WHEN a.round_number >= 3 AND a.assignment_status = 'pending' THEN p.cid END) as r3_assigned
        FROM target_population p
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND COALESCE(a.is_sandbox, 0) = ?
        LEFT JOIN screening_results s ON (s.target_cid = p.cid OR s.assignment_id = a.assignment_id) AND COALESCE(s.is_sandbox, 0) = ?
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
          AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholdersSa)
        GROUP BY COALESCE(v.hoscode, p.hoscode)
        ORDER BY COALESCE(v.hoscode, p.hoscode)
    ");
    $chartRescreenStmt->execute(array_merge([$isSandboxVal, $isSandboxVal], $valid_hoscodes));
    $chartRescreenData = $chartRescreenStmt->fetchAll(PDO::FETCH_ASSOC);
    $metrics['r1_completed'] = array_sum(array_column($chartRescreenData, 'r1_completed'));
    $metrics['r2_completed'] = array_sum(array_column($chartRescreenData, 'r2_completed'));
    $metrics['r3_completed'] = array_sum(array_column($chartRescreenData, 'r3_completed'));
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard & Hotspot Map - SSOTansum NCD</title>

    <!-- CSS Assets -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Leaflet Map CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet Heatmap Plugin -->
    <script src="https://leaflet.github.io/Leaflet.heat/dist/leaflet-heat.js"></script>

    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        .admin-bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 10px;
            margin-bottom: 10px;
        }
        .bento-card {
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 12px;
            padding: 12px 14px;
            box-shadow: 0 2px 10px -2px rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .bento-card:hover {
            border-color: rgba(99, 102, 241, 0.25);
            box-shadow: 0 6px 18px -4px rgba(0, 0, 0, 0.08);
        }
        .bento-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color, #e5e7eb);
            padding-bottom: 6px;
            margin-bottom: 8px;
            flex-wrap: nowrap;
            gap: 6px;
            min-height: 32px;
        }
        .bento-title {
            color: var(--color-accent, #1e293b);
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }
        .bento-icon-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 6px;
        }
        .bento-span-12 { grid-column: span 12; }
        .bento-span-8 { grid-column: span 8; }
        .bento-span-7 { grid-column: span 7; }
        .bento-span-6 { grid-column: span 6; }
        .bento-span-5 { grid-column: span 5; }
        .bento-span-4 { grid-column: span 4; }
        .bento-span-3 { grid-column: span 3; }

        @media (max-width: 1100px) {
            .bento-span-3, .bento-span-4, .bento-span-5 { grid-column: span 6; }
            .bento-span-7, .bento-span-8 { grid-column: span 12; }
        }
        @media (max-width: 768px) {
            .admin-bento-grid {
                grid-template-columns: 1fr;
                gap: 10px;
                margin-bottom: 10px;
            }
            .bento-span-12, .bento-span-8, .bento-span-7, .bento-span-6, .bento-span-5, .bento-span-4, .bento-span-3 {
                grid-column: span 1;
            }
        }

        /* Recent Screenings Custom Scroll Container */
        .recent-screenings-scroll {
            max-height: 410px;
            overflow-y: auto;
            overflow-x: auto;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        .recent-screenings-scroll::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        .recent-screenings-scroll table.admin-table thead th {
            position: sticky;
            top: 0;
            background-color: var(--bg-darker, #f1f5f9);
            color: var(--text-primary);
            z-index: 5;
            box-shadow: 0 1px 0 var(--border-color, #e5e7eb);
        }
    </style>
</head>

<body class="admin-body dashboard-page">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1240px; margin: 20px auto; padding: 0 16px;">
        <h2 style="margin-bottom: 4px;">ภาพรวมความคุ้มครองและพิกัดกลุ่มเสี่ยง (Dashboard)</h2>
        <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 14px;">
            หน่วยบริการผู้รับผิดชอบ: <strong
                style="color: var(--color-accent);"><?= htmlspecialchars($admin_title) ?></strong>
        </p>

        <!-- Target Group Summary -->
        <div style="margin-bottom: 8px;">
            <h3 style="color: var(--color-accent); margin-bottom: 6px; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                กลุ่มเป้าหมายคัดกรองแยกตามประเภทโรค
            </h3>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 10px; margin-bottom: 10px;">
            <!-- กลุ่มเสี่ยง Both -->
            <div class="card-dark" style="cursor: pointer; border-left: 4px solid var(--color-red); position: relative; overflow: hidden; padding: 14px 16px;" onclick="showCardModal('targets_both')">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                    <?= render_neu_icon('heart-pulse', 'sm', 'disc-red') ?>
                    <span style="color: var(--text-secondary); font-size: 13.5px; font-weight: bold;">เป้าหมายร่วม (DM+HT)</span>
                </div>
                <div class="stat-val" style="color: var(--color-red); font-size: 36px; font-weight: 900; line-height: 1; letter-spacing: -0.5px; margin: 4px 0; display: flex; justify-content: flex-end; align-items: baseline;">
                    <?= number_format($metrics['group_both']) ?> <span style="font-size: 13px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">  ราย</span>
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: var(--text-muted); line-height: 1.35;">
                    ประชากรทั่วไป 35 ปีขึ้นไป (ต้องตรวจ 2 โรค)
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: var(--color-red); font-weight: bold;">
                    <?= $metrics['total_targets'] > 0 ? round(($metrics['group_both'] / $metrics['total_targets']) * 100, 1) : 0 ?>%
                    ของเป้าหมายคัดกรอง
                </div>
            </div>

            <!-- กลุ่มเสี่ยง DM -->
            <div class="card-dark" style="cursor: pointer; border-left: 4px solid #f97316; position: relative; overflow: hidden; padding: 14px 16px;" onclick="showCardModal('targets_dm')">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                    <?= render_neu_icon('syringe', 'sm', 'disc-yellow') ?>
                    <span style="color: var(--text-secondary); font-size: 13.5px; font-weight: bold;">เป้าหมาย (เบาหวาน)</span>
                </div>
                <div class="stat-val" style="color: #f97316; font-size: 36px; font-weight: 900; line-height: 1; letter-spacing: -0.5px; margin: 4px 0; display: flex; justify-content: flex-end; align-items: baseline;">
                    <?= number_format($metrics['group_dm']) ?> <span style="font-size: 13px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">  ราย</span>
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: var(--text-muted); line-height: 1.35;">
                    เฉพาะเบาหวาน (เป็นผู้ป่วยความดันแล้ว)
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: #f97316; font-weight: bold;">
                    <?= $metrics['total_targets'] > 0 ? round(($metrics['group_dm'] / $metrics['total_targets']) * 100, 1) : 0 ?>%
                    ของเป้าหมายคัดกรอง
                </div>
            </div>

            <!-- กลุ่มเสี่ยง HT -->
            <div class="card-dark" style="cursor: pointer; border-left: 4px solid #06b6d4; position: relative; overflow: hidden; padding: 14px 16px;" onclick="showCardModal('targets_ht')">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                    <?= render_neu_icon('thermometer', 'sm', 'disc-blue') ?>
                    <span style="color: var(--text-secondary); font-size: 13.5px; font-weight: bold;">เป้าหมาย (ความดัน)</span>
                </div>
                <div class="stat-val" style="color: #06b6d4; font-size: 36px; font-weight: 900; line-height: 1; letter-spacing: -0.5px; margin: 4px 0; display: flex; justify-content: flex-end; align-items: baseline;">
                    <?= number_format($metrics['group_ht']) ?> <span style="font-size: 13px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">  ราย</span>
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: var(--text-muted); line-height: 1.35;">
                    เฉพาะความดัน (เป็นผู้ป่วยเบาหวานแล้ว)
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: #06b6d4; font-weight: bold;">
                    <?= $metrics['total_targets'] > 0 ? round(($metrics['group_ht'] / $metrics['total_targets']) * 100, 1) : 0 ?>%
                    ของเป้าหมายคัดกรอง
                </div>
            </div>

            <!-- กลุ่มสงสัยป่วยสะสม (Suspect) -->
            <div class="card-dark" style="cursor: pointer; border-left: 4px solid var(--color-yellow); position: relative; overflow: hidden; padding: 14px 16px;" onclick="showCardModal('targets_suspected')">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                    <?= render_neu_icon('warning-alert', 'sm', 'disc-yellow') ?>
                    <span style="color: var(--text-secondary); font-size: 13.5px; font-weight: bold;">กลุ่มสงสัยป่วย (Suspect)</span>
                </div>
                <div class="stat-val" style="color: var(--color-yellow); font-size: 36px; font-weight: 900; line-height: 1; letter-spacing: -0.5px; margin: 4px 0; display: flex; justify-content: flex-end; align-items: baseline;">
                    <?= number_format($metrics['group_suspected']) ?> <span style="font-size: 13px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">  ราย</span>
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: var(--text-muted); line-height: 1.35;">
                    ผลตรวจผิดปกติปีก่อน (รอแพทย์วินิจฉัย)
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: var(--color-yellow); font-weight: bold;">
                    จากฐานข้อมูลระบบ HDC
                </div>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid-cols-4" style="margin-bottom: 14px; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 10px;">
            <!-- Card 1: ผลงานคัดกรองรอบที่ 1 -->
            <div class="card-dark" style="cursor: pointer; border-left: 4px solid var(--color-green); position: relative; overflow: hidden; padding: 14px 16px;" onclick="showCardModal('screened')">
                <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 4px;">
                    <?= render_neu_icon('first-aid', 'md', 'disc-green') ?>
                    <div>
                        <div style="color: var(--text-secondary); font-size: 13.5px; font-weight: bold; line-height: 1.3;">คัดกรองรอบที่ 1</div>
                        <div style="font-size: 11.5px; color: var(--text-muted); line-height: 1.2;">รอบหลักประจำปี (Baseline)</div>
                    </div>
                </div>
                <div class="stat-val" style="color: var(--color-green); font-size: 36px; font-weight: 900; line-height: 1; letter-spacing: -0.5px; margin: 4px 0; display: flex; justify-content: flex-end; align-items: baseline;">
                    <?= number_format($metrics['r1_completed'] ?? $metrics['screened_count']) ?> <span
                        style="font-size: 13px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">  ราย</span>
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: var(--text-muted); line-height: 1.35;">
                    คิดเป็น <strong style="color: var(--color-green);"><?= $metrics['total_targets'] > 0 ? round((($metrics['r1_completed'] ?? $metrics['screened_count']) / $metrics['total_targets']) * 100, 1) : 0 ?>%</strong> ของเป้าหมาย <?= number_format($metrics['total_targets']) ?> ราย
                </div>
                <div style="margin-top: 2px; font-size: 11px; color: var(--text-muted);">
                    (คลิกดูสถิติแยกตามระดับความเสี่ยง)
                </div>
            </div>

            <!-- Card 2: ผลงานคัดกรองติดตามซ้ำรอบที่ 2 -->
            <div class="card-dark" style="cursor: pointer; border-left: 4px solid #3b82f6; position: relative; overflow: hidden; padding: 14px 16px;" onclick="showCardModal('rescreen_r2')">
                <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 4px;">
                    <?= render_neu_icon('refresh-repeat', 'md', 'disc-blue') ?>
                    <div>
                        <div style="color: var(--text-secondary); font-size: 13.5px; font-weight: bold; line-height: 1.3;">คัดกรองรอบที่ 2</div>
                        <div style="font-size: 11.5px; color: #3b82f6; line-height: 1.2;">ติดตามซ้ำกลุ่มเสี่ยง (Re-screening)</div>
                    </div>
                </div>
                <div class="stat-val" style="color: #3b82f6; font-size: 36px; font-weight: 900; line-height: 1; letter-spacing: -0.5px; margin: 4px 0; display: flex; justify-content: flex-end; align-items: baseline;">
                    <?= number_format($metrics['r2_completed'] ?? 0) ?> <span
                        style="font-size: 13px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">ราย</span>
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: var(--text-muted); line-height: 1.35;">
                    ติดตามซ้ำแล้ว <strong style="color: #3b82f6;"><?= ($metrics['r1_completed'] ?? 1) > 0 ? round((($metrics['r2_completed'] ?? 0) / max($metrics['r1_completed'] ?? 1, 1)) * 100, 1) : 0 ?>%</strong> จากรอบแรก
                </div>
                <div style="margin-top: 2px; font-size: 11px; color: var(--text-muted);">
                    (คลิกดูสถิติจำแนกรายพื้นที่)
                </div>
            </div>

            <!-- Card 3: รอดำเนินการ -->
            <div class="card-dark" style="cursor: pointer; border-left: 4px solid var(--color-primary); position: relative; overflow: hidden; padding: 14px 16px;" onclick="showCardModal('pending')">
                <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 4px;">
                    <?= render_neu_icon('clipboard-record', 'md', 'text-navy') ?>
                    <div>
                        <div style="color: var(--text-secondary); font-size: 13.5px; font-weight: bold; line-height: 1.3;">รอดำเนินการ</div>
                        <div style="font-size: 11.5px; color: var(--text-muted); line-height: 1.2;">งานมอบหมายค้างตรวจ (Pending)</div>
                    </div>
                </div>
                <div class="stat-val" style="color: var(--color-primary); font-size: 36px; font-weight: 900; line-height: 1; letter-spacing: -0.5px; margin: 4px 0; display: flex; justify-content: flex-end; align-items: baseline;">
                    <?= number_format($metrics['pending_count']) ?> <span
                        style="font-size: 13px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">ราย</span>
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: var(--text-muted); line-height: 1.35;">
                    มอบหมายแล้ว รอ อสม. ลงพื้นที่
                </div>
                <div style="margin-top: 2px; font-size: 11px; color: var(--text-muted);">
                    (คลิกดูรายละเอียดแยกรายพื้นที่)
                </div>
            </div>

            <!-- Card 4: แต้มรางวัลสะสม อสม. -->
            <div class="card-dark" style="cursor: pointer; border-left: 4px solid #eab308; position: relative; overflow: hidden; padding: 14px 16px;" onclick="showCardModal('rewards')">
                <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 4px;">
                    <?= render_neu_icon('doctor', 'md', 'disc-yellow') ?>
                    <div>
                        <div style="color: var(--text-secondary); font-size: 13.5px; font-weight: bold; line-height: 1.3;">แต้มรางวัลสะสม อสม.</div>
                        <div style="font-size: 11.5px; color: var(--text-muted); line-height: 1.2;">คะแนนปฏิบัติงานสะสม (Rewards)</div>
                    </div>
                </div>
                <div class="stat-val" style="color: #eab308; font-size: 36px; font-weight: 900; line-height: 1; letter-spacing: -0.5px; margin: 4px 0; display: flex; justify-content: flex-end; align-items: baseline;">
                    <?= ((float)($metrics['total_points'] ?? 0) == (int)($metrics['total_points'] ?? 0) ? number_format($metrics['total_points'] ?? 0) : number_format($metrics['total_points'] ?? 0, 2)) ?> <span
                        style="font-size: 13px; font-weight: 700; color: var(--text-secondary); margin-left: 4px;">  แต้ม</span>
                </div>
                <div style="margin-top: 3px; font-size: 11.5px; color: var(--text-muted); line-height: 1.35;">
                    จาก อสม. ปฏิบัติงานทั้งหมด <?= $metrics['total_vhvs'] ?> คน
                </div>
                <div style="margin-top: 2px; font-size: 11px; color: var(--text-muted);">
                    (คลิกดูกระดานคะแนน Top 10)
                </div>
            </div>
        </div>

        <!-- Multi-Round Re-screening Performance Chart (Standard Summary Card) -->
        <div class="card-dark" style="margin-bottom: 14px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <?= render_neu_icon('chart-line', 'sm', 'disc-blue') ?>
                    <h3 style="color: var(--color-accent); margin: 0; font-size: 15px; font-weight: 700;">ความก้าวหน้าและร้อยละผลงานการคัดกรองติดตามซ้ำรายรอบ (Multi-Round Re-screening)</h3>
                </div>
                <div style="font-size: 12px; font-weight: 600; color: var(--text-muted);">
                    <?= $admin_hoscode ? 'เปรียบเทียบรายหมู่บ้าน' : 'เปรียบเทียบรายหน่วยบริการ (รพ.สต.)' ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 14px;">
                <?php
                $totAll = array_sum(array_column($chartRescreenData, 'total_targets')) ?: 1;
                $r1All = array_sum(array_column($chartRescreenData, 'r1_completed'));
                $r2AssignedAll = array_sum(array_column($chartRescreenData, 'r2_assigned'));
                $r2CompAll = array_sum(array_column($chartRescreenData, 'r2_completed'));
                $r3AssignedAll = array_sum(array_column($chartRescreenData, 'r3_assigned'));
                $r3CompAll = array_sum(array_column($chartRescreenData, 'r3_completed'));

                $pctR1 = number_format(($r1All / $totAll) * 100, 1);
                $denomR2 = max($r1All, $r2CompAll + $r2AssignedAll, 1);
                $pctR2 = number_format(($r2CompAll / $denomR2) * 100, 1);
                $denomR3 = max($r2CompAll, $r3CompAll + $r3AssignedAll, 1);
                $pctR3 = number_format(($r3CompAll / $denomR3) * 100, 1);
                ?>
                <div style="background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.2); padding: 10px 14px; border-radius: 10px;">
                    <div style="font-size: 11.5px; color: var(--color-green); font-weight: 700;">✅ รอบที่ 1 (Baseline)</div>
                    <div style="font-size: 18px; font-weight: 800; color: var(--text-color); margin-top: 2px;"><?= number_format($r1All) ?> <span style="font-size: 12px; color: var(--color-green);">(<?= $pctR1 ?>%)</span></div>
                    <div style="font-size: 11px; color: var(--text-muted);">คัดกรองเสร็จจากเป้าหมาย <?= number_format($totAll) ?> ราย</div>
                </div>
                <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); padding: 10px 14px; border-radius: 10px;">
                    <div style="font-size: 11.5px; color: #3b82f6; font-weight: 700;">🔄 รอบที่ 2 (คัดกรองติดตามซ้ำ)</div>
                    <div style="font-size: 18px; font-weight: 800; color: var(--text-color); margin-top: 2px;"><?= number_format($r2CompAll) ?> <span style="font-size: 12px; color: #3b82f6;">(<?= $pctR2 ?>%)</span></div>
                    <div style="font-size: 11px; color: var(--text-muted);">คัดกรองเสร็จจากรอบแรก <?= number_format($r1All) ?> ราย</div>
                </div>
                <div style="background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.2); padding: 10px 14px; border-radius: 10px;">
                    <div style="font-size: 11.5px; color: #8b5cf6; font-weight: 700;">🔄 รอบที่ 3+ (ติดตามต่อเนื่อง)</div>
                    <div style="font-size: 18px; font-weight: 800; color: var(--text-color); margin-top: 2px;"><?= number_format($r3CompAll) ?> <span style="font-size: 12px; color: #8b5cf6;">(<?= $pctR3 ?>%)</span></div>
                    <div style="font-size: 11px; color: var(--text-muted);">คัดกรองสำเร็จจากงานมอบหมาย <?= number_format($r3CompAll + $r3AssignedAll) ?> ราย</div>
                </div>
            </div>

            <div id="chart-rescreen"></div>
        </div>

        <!-- ==================== BENTO GRID ANALYTICS (SCREENSHOT 1-4) ==================== -->

        <!-- Row 1: Overall Progress | Total vs Screened Pie | Cockpit Pipeline (Span 4 + 4 + 4) -->
        <div class="admin-bento-grid">
            <!-- 1. Overall Progress (Span 4) -->
            <div class="bento-card bento-span-4">
                <div class="bento-header">
                    <h3 class="bento-title" style="flex: 1; min-width: 0;">
                        <span class="bento-icon-badge" style="background: rgba(14, 165, 233, 0.15); color: #0ea5e9; flex-shrink: 0;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </span>
                        <span id="overall-progress-title" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">ความคืบหน้า (ทุกรอบ)</span>
                    </h3>
                    <div style="display: inline-flex; gap: 2px; background: var(--bg-darker); padding: 2px; border-radius: 6px; border: 1px solid var(--border-color); flex-shrink: 0;">
                        <button type="button" class="btn-progress-round active" onclick="switchOverallRound('all')" id="btn-ovr-all" style="padding: 2px 7px; font-size: 10.5px; font-weight: bold; border-radius: 4px; border: none; cursor: pointer; background: #0ea5e9; color: #ffffff; transition: all 0.2s;">
                            ทุกรอบ
                        </button>
                        <button type="button" class="btn-progress-round" onclick="switchOverallRound('r1')" id="btn-ovr-r1" style="padding: 2px 7px; font-size: 10.5px; font-weight: bold; border-radius: 4px; border: none; cursor: pointer; background: transparent; color: var(--text-secondary); transition: all 0.2s;">
                            รอบ 1
                        </button>
                        <button type="button" class="btn-progress-round" onclick="switchOverallRound('r2')" id="btn-ovr-r2" style="padding: 2px 7px; font-size: 10.5px; font-weight: bold; border-radius: 4px; border: none; cursor: pointer; background: transparent; color: var(--text-secondary); transition: all 0.2s;">
                            รอบ 2
                        </button>
                        <button type="button" class="btn-progress-round" onclick="switchOverallRound('r3')" id="btn-ovr-r3" style="padding: 2px 7px; font-size: 10.5px; font-weight: bold; border-radius: 4px; border: none; cursor: pointer; background: transparent; color: var(--text-secondary); transition: all 0.2s;">
                            รอบ 3+
                        </button>
                    </div>
                </div>
                <div id="chart-overall-progress" style="display: flex; justify-content: center;"></div>
            </div>

            <!-- 2. Total vs Screened Wave Area Chart (Span 4) -->
            <div class="bento-card bento-span-4">
                <div class="bento-header">
                    <h3 class="bento-title">
                        <span class="bento-icon-badge" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </span>
                        <span>สัดส่วนคัดกรอง / เป้าหมาย (แยกรอบ)</span>
                    </h3>
                </div>
                <div id="chart-total-pie"></div>
            </div>

            <!-- 3. Cockpit Multi-Round Pipeline (Span 4) -->
            <div class="bento-card bento-span-4">
                <div>
                    <div class="bento-header">
                        <h3 class="bento-title">
                            <span class="bento-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </span>
                            <span>Cockpit ประสิทธิภาพการคัดกรองรายรอบ</span>
                        </h3>
                        <span style="font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 9999px; background: rgba(34, 197, 94, 0.12); color: #10b981; border: 1px solid rgba(34, 197, 94, 0.25);">
                            4 มิติรอบ
                        </span>
                    </div>

                    <?php
                    $cR1Pct = $totAll > 0 ? round(($r1All / $totAll) * 100, 1) : 0;
                    $cR2AssignedTotal = $r2AssignedAll + $r2CompAll;
                    $cR2ReachPct = $r1All > 0 ? round(($cR2AssignedTotal / $r1All) * 100, 1) : 0;
                    $cR2CompPct = $cR2AssignedTotal > 0 ? round(($r2CompAll / $cR2AssignedTotal) * 100, 1) : 0;
                    $cR3AssignedTotal = $r3AssignedAll + $r3CompAll;
                    $cR3CompPct = $cR3AssignedTotal > 0 ? round(($r3CompAll / $cR3AssignedTotal) * 100, 1) : 0;
                    ?>

                    <div style="display: flex; flex-direction: column; gap: 5px;">
                        <!-- 1. Round 1 Coverage -->
                        <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 5px 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 16px; height: 16px; border-radius: 4px; background: #10b981; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 9.5px; font-weight: 900;">1</span>
                                    <span style="font-size: 11.5px; font-weight: 700; color: var(--text-primary);">ครอบคลุมรอบ 1</span>
                                </div>
                                <div style="display: flex; align-items: baseline; gap: 3px;">
                                    <span style="font-size: 12.5px; font-weight: 900; color: #10b981;"><?= $cR1Pct ?>%</span>
                                    <span style="font-size: 9.5px; color: var(--text-muted);">(<?= number_format($r1All) ?>/<?= number_format($totAll) ?>)</span>
                                </div>
                            </div>
                            <div style="height: 4px; background: var(--bg-darker); border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: <?= min(100, $cR1Pct) ?>%; background: linear-gradient(90deg, #10b981, #34d399); border-radius: 9999px;"></div>
                            </div>
                        </div>

                        <!-- 2. Round 2 Assigned -->
                        <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 5px 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 16px; height: 16px; border-radius: 4px; background: #0ea5e9; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 9.5px; font-weight: 900;">2</span>
                                    <span style="font-size: 11.5px; font-weight: 700; color: var(--text-primary);">มอบหมายติดตาม R2</span>
                                </div>
                                <div style="display: flex; align-items: baseline; gap: 3px;">
                                    <span style="font-size: 12.5px; font-weight: 900; color: #0ea5e9;"><?= $cR2ReachPct ?>%</span>
                                    <span style="font-size: 9.5px; color: var(--text-muted);">(<?= number_format($cR2AssignedTotal) ?>/<?= number_format($r1All) ?>)</span>
                                </div>
                            </div>
                            <div style="height: 4px; background: var(--bg-darker); border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: <?= min(100, $cR2ReachPct) ?>%; background: linear-gradient(90deg, #0ea5e9, #38bdf8); border-radius: 9999px;"></div>
                            </div>
                        </div>

                        <!-- 3. Round 2 Completed -->
                        <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 5px 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 16px; height: 16px; border-radius: 4px; background: #3b82f6; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 9.5px; font-weight: 900;">✓</span>
                                    <span style="font-size: 11.5px; font-weight: 700; color: var(--text-primary);">ติดตามสำเร็จรอบ 2</span>
                                </div>
                                <div style="display: flex; align-items: baseline; gap: 3px;">
                                    <span style="font-size: 12.5px; font-weight: 900; color: #3b82f6;"><?= $cR2CompPct ?>%</span>
                                    <span style="font-size: 9.5px; color: var(--text-muted);">(<?= number_format($r2CompAll) ?>/<?= number_format(max(1, $cR2AssignedTotal)) ?>)</span>
                                </div>
                            </div>
                            <div style="height: 4px; background: var(--bg-darker); border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: <?= min(100, $cR2CompPct) ?>%; background: linear-gradient(90deg, #3b82f6, #60a5fa); border-radius: 9999px;"></div>
                            </div>
                        </div>

                        <!-- 4. Round 3+ Completed -->
                        <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 5px 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <span style="width: 16px; height: 16px; border-radius: 4px; background: #8b5cf6; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 9.5px; font-weight: 900;">3+</span>
                                    <span style="font-size: 11.5px; font-weight: 700; color: var(--text-primary);">ติดตามสำเร็จรอบ 3+</span>
                                </div>
                                <div style="display: flex; align-items: baseline; gap: 3px;">
                                    <span style="font-size: 12.5px; font-weight: 900; color: #8b5cf6;"><?= $cR3CompPct ?>%</span>
                                    <span style="font-size: 9.5px; color: var(--text-muted);">(<?= number_format($r3CompAll) ?>/<?= number_format(max(1, $cR3AssignedTotal)) ?>)</span>
                                </div>
                            </div>
                            <div style="height: 4px; background: var(--bg-darker); border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: <?= min(100, $cR3CompPct) ?>%; background: linear-gradient(90deg, #8b5cf6, #a78bfa); border-radius: 9999px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 5px; padding-top: 4px; border-top: 1px dashed var(--border-color); display: flex; justify-content: space-between; align-items: center; font-size: 10.5px; color: var(--text-secondary);">
                    <span>⚡ อัตราส่งมอบงานติดตามต่อ:</span>
                    <strong style="color: #2563eb;"><?= $cR2ReachPct ?>%</strong>
                </div>
            </div>
        </div>

        <!-- Row 2: Screened Risk Distribution | Skipped Reasons | DPAC Enrollments (Span 4 + 4 + 4) -->
        <div class="admin-bento-grid">
            <!-- 4. Screened Risk Pie (Span 4) -->
            <div class="bento-card bento-span-4">
                <div class="bento-header">
                    <h3 class="bento-title">
                        <span class="bento-icon-badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </span>
                        <span>สัดส่วนผลการคัดกรองแยกตามระดับความเสี่ยง</span>
                    </h3>
                </div>
                <div id="chart-screened-risk-pie"></div>
            </div>

            <!-- 5. DPAC Enrollments (Span 4) -->
            <div class="bento-card bento-span-4">
                <div class="bento-header">
                    <h3 class="bento-title">
                        <span class="bento-icon-badge" style="background: rgba(6, 182, 212, 0.15); color: #06b6d4;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </span>
                        <span>กลุ่มเสี่ยงเข้าร่วมโครงการปรับเปลี่ยนพฤติกรรม</span>
                    </h3>
                </div>
                <div id="chart-dpac"></div>
            </div>

            <!-- 6. Skipped Reasons (Span 4) -->
            <div class="bento-card bento-span-4">
                <div class="bento-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="bento-title">
                        <span class="bento-icon-badge" style="background: rgba(100, 116, 139, 0.15); color: #64748b;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </span>
                        <span>สาเหตุการข้ามเคส (เคสไม่สมบูรณ์)</span>
                    </h3>
                    <?php if (empty($chartSkippedData)): ?>
                        <span style="background: rgba(16, 185, 129, 0.12); color: #10b981; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 9999px; border: 1px solid rgba(16, 185, 129, 0.25);">
                            ✓ 0 เคส
                        </span>
                    <?php endif; ?>
                </div>
                <div id="chart-skipped"></div>
            </div>


        </div>

        <!-- Row 3: Coverage by Area | Risk by Area (Span 6 + 6) -->
        <div class="admin-bento-grid">
            <!-- 7. Chart Coverage (Span 6) -->
            <div class="bento-card bento-span-6">
                <div class="bento-header">
                    <h3 class="bento-title">
                        <span class="bento-icon-badge" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                        </span>
                        <span id="coverage-chart-title">ความครอบคลุมการคัดกรอง แยกตาม <?= $admin_hoscode ? 'หมู่บ้าน' : 'รพ.สต.' ?></span>
                    </h3>
                    <div style="display: inline-flex; gap: 3px; background: var(--bg-darker); padding: 2px; border-radius: 7px; border: 1px solid var(--border-color);">
                        <button type="button" class="btn-cov-round" onclick="switchCoverageRound('all')" id="btn-cov-all" style="padding: 3px 8px; font-size: 11px; font-weight: bold; border-radius: 5px; border: none; cursor: pointer; background: #8b5cf6; color: #ffffff; transition: all 0.2s;">
                            ทุกรอบ
                        </button>
                        <button type="button" class="btn-cov-round" onclick="switchCoverageRound('r1')" id="btn-cov-r1" style="padding: 3px 8px; font-size: 11px; font-weight: bold; border-radius: 5px; border: none; cursor: pointer; background: transparent; color: var(--text-secondary); transition: all 0.2s;">
                            รอบ 1
                        </button>
                        <button type="button" class="btn-cov-round" onclick="switchCoverageRound('r2')" id="btn-cov-r2" style="padding: 3px 8px; font-size: 11px; font-weight: bold; border-radius: 5px; border: none; cursor: pointer; background: transparent; color: var(--text-secondary); transition: all 0.2s;">
                            รอบ 2
                        </button>
                        <button type="button" class="btn-cov-round" onclick="switchCoverageRound('r3')" id="btn-cov-r3" style="padding: 3px 8px; font-size: 11px; font-weight: bold; border-radius: 5px; border: none; cursor: pointer; background: transparent; color: var(--text-secondary); transition: all 0.2s;">
                            รอบ 3+
                        </button>
                    </div>
                </div>
                <div id="chart-coverage"></div>
            </div>

            <!-- 8. Chart Risk (Span 6) -->
            <div class="bento-card bento-span-6">
                <div class="bento-header">
                    <h3 class="bento-title">
                        <span class="bento-icon-badge" style="background: rgba(244, 63, 94, 0.15); color: #f43f5e;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <span id="risk-chart-title">ระดับความเสี่ยงประชากร แยกตาม <?= $admin_hoscode ? 'หมู่บ้าน' : 'รพ.สต.' ?></span>
                    </h3>
                    <div style="display: inline-flex; gap: 3px; background: var(--bg-darker); padding: 2px; border-radius: 7px; border: 1px solid var(--border-color);">
                        <button type="button" class="btn-risk-round" onclick="switchRiskRound('all')" id="btn-risk-all" style="padding: 3px 8px; font-size: 11px; font-weight: bold; border-radius: 5px; border: none; cursor: pointer; background: #f43f5e; color: #ffffff; transition: all 0.2s;">
                            ล่าสุด
                        </button>
                        <button type="button" class="btn-risk-round" onclick="switchRiskRound('r1')" id="btn-risk-r1" style="padding: 3px 8px; font-size: 11px; font-weight: bold; border-radius: 5px; border: none; cursor: pointer; background: transparent; color: var(--text-secondary); transition: all 0.2s;">
                            รอบ 1
                        </button>
                        <button type="button" class="btn-risk-round" onclick="switchRiskRound('r2')" id="btn-risk-r2" style="padding: 3px 8px; font-size: 11px; font-weight: bold; border-radius: 5px; border: none; cursor: pointer; background: transparent; color: var(--text-secondary); transition: all 0.2s;">
                            รอบ 2
                        </button>
                        <button type="button" class="btn-risk-round" onclick="switchRiskRound('r3')" id="btn-risk-r3" style="padding: 3px 8px; font-size: 11px; font-weight: bold; border-radius: 5px; border: none; cursor: pointer; background: transparent; color: var(--text-secondary); transition: all 0.2s;">
                            รอบ 3+
                        </button>
                    </div>
                </div>
                <div id="chart-risk"></div>
            </div>
        </div>

        <!-- Row 4: Disease Breakdown | Daily Trend (Span 6 + 6) -->
        <div class="admin-bento-grid">
            <!-- 9. Disease Breakdown (Span 6) -->
            <div class="bento-card bento-span-6">
                <div class="bento-header">
                    <h3 class="bento-title">
                        <span class="bento-icon-badge" style="background: rgba(236, 72, 153, 0.15); color: #ec4899;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <span>ผลการคัดกรองแยกตามระดับความเสี่ยงและกลุ่มโรค</span>
                    </h3>
                </div>
                <div id="chart-disease"></div>
            </div>

            <!-- 10. Screening Trend (Span 6) -->
            <div class="bento-card bento-span-6">
                <div class="bento-header">
                    <h3 class="bento-title">
                        <span class="bento-icon-badge" style="background: rgba(14, 165, 233, 0.15); color: #0ea5e9;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </span>
                        <span>แนวโน้มการคัดกรองรายวัน (14 วันล่าสุด)</span>
                    </h3>
                </div>
                <div id="chart-trend"></div>
            </div>
        </div>

        <!-- Recent Screenings Table -->
        <div class="card-dark" style="margin-top: 14px; margin-bottom: 14px;">
            <div class="bento-header">
                <h3 class="bento-title">
                    <span class="bento-icon-badge" style="background: rgba(34, 197, 94, 0.15); color: #22c55e;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </span>
                    <span>ผลการคัดกรองล่าสุดในพื้นที่</span>
                </h3>

            </div>
            <div class="table-responsive recent-screenings-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ประเภทกิจกรรม</th>
                            <th style="white-space: nowrap;">เลขที่</th>
                            <th>หมู่บ้าน</th>
                            <th>หมู่</th>
                            <th>ความดันโลหิต</th>
                            <th>ค่าน้ำตาล (DTX)</th>
                            <th>ดัชนีมวลกาย (BMI)</th>
                            <th>ความเสี่ยง<br>(CV Risk)</th>
                            <th>อสม. ผู้บันทึก</th>
                            <th>พิกัดบันทึก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($admin_hoscode) {
                            $hoscodes = get_query_hoscodes($admin_hoscode);
                            $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
                            $recentScreenQuery = $pdo->prepare("
                                SELECT p.house_no, p.moo, p.sub_district_code, p.hoscode,
                                       combined.sys_bp, combined.dia_bp, combined.dtx_value, 
                                       combined.bmi, combined.cv_risk_score, v.vhv_name, 
                                       combined.screening_lat, combined.screening_lng, combined.activity_type, combined.created_at
                                FROM (
                                    SELECT a.vhv_id, a.target_cid,
                                           s.sys_bp1 AS sys_bp, s.dia_bp1 AS dia_bp, s.dtx_value, s.bmi, s.cv_risk_score, 
                                           s.screening_lat, s.screening_lng, 'คัดกรองแรก' AS activity_type, s.created_at
                                    FROM screening_results s
                                    JOIN task_assignments a ON s.assignment_id = a.assignment_id
                                    
                                    UNION ALL
                                    
                                    SELECT f.vhv_id, e.cid AS target_cid,
                                           f.bp_sys AS sys_bp, f.bp_dia AS dia_bp, f.fbs AS dtx_value,
                                           CASE WHEN f.height > 0 THEN ROUND(f.weight / ((f.height/100) * (f.height/100)), 2) ELSE 0.00 END AS bmi,
                                           NULL AS cv_risk_score,
                                           NULL AS screening_lat, NULL AS screening_lng,
                                           CONCAT('ติดตาม DPAC รอบ ', f.round_number) AS activity_type, f.completed_at AS created_at
                                    FROM dpac_followups f
                                    JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
                                    WHERE f.status = 'completed'
                                ) AS combined
                                JOIN target_population p ON combined.target_cid = p.cid
                                JOIN vhv_users v ON combined.vhv_id = v.vhv_id
                                WHERE p.hoscode IN ($inPlaceholders)
                                ORDER BY combined.created_at DESC LIMIT 12
                            ");
                            $recentScreenQuery->execute($hoscodes);
                            $recentScreens = $recentScreenQuery->fetchAll();
                        } else {
                            $recentScreenQuery = $pdo->query("
                                SELECT p.house_no, p.moo, p.sub_district_code, p.hoscode,
                                       combined.sys_bp, combined.dia_bp, combined.dtx_value, 
                                       combined.bmi, combined.cv_risk_score, v.vhv_name, 
                                       combined.screening_lat, combined.screening_lng, combined.activity_type, combined.created_at
                                FROM (
                                    SELECT a.vhv_id, a.target_cid,
                                           s.sys_bp1 AS sys_bp, s.dia_bp1 AS dia_bp, s.dtx_value, s.bmi, s.cv_risk_score, 
                                           s.screening_lat, s.screening_lng, 'คัดกรองแรก' AS activity_type, s.created_at
                                    FROM screening_results s
                                    JOIN task_assignments a ON s.assignment_id = a.assignment_id
                                    
                                    UNION ALL
                                    
                                    SELECT f.vhv_id, e.cid AS target_cid,
                                           f.bp_sys AS sys_bp, f.bp_dia AS dia_bp, f.fbs AS dtx_value,
                                           CASE WHEN f.height > 0 THEN ROUND(f.weight / ((f.height/100) * (f.height/100)), 2) ELSE 0.00 END AS bmi,
                                           NULL AS cv_risk_score,
                                           NULL AS screening_lat, NULL AS screening_lng,
                                           CONCAT('ติดตาม DPAC รอบ ', f.round_number) AS activity_type, f.completed_at AS created_at
                                    FROM dpac_followups f
                                    JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
                                    WHERE f.status = 'completed'
                                ) AS combined
                                JOIN target_population p ON combined.target_cid = p.cid
                                JOIN vhv_users v ON combined.vhv_id = v.vhv_id
                                ORDER BY combined.created_at DESC LIMIT 12
                            ");
                            $recentScreens = $recentScreenQuery->fetchAll();
                        }
                        if (empty($recentScreens)):
                        ?>
                            <tr>
                                <td colspan="10" style="text-align: center; color: var(--text-secondary); padding: 24px;">
                                    ยังไม่มีข้อมูลผลการคัดกรองในระบบ</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentScreens as $rs): ?>
                                <tr>
                                    <td>
                                        <?php if (strpos($rs['activity_type'], 'ติดตาม DPAC') !== false): ?>
                                            <span style="display: inline-block; background-color: rgba(6, 182, 212, 0.15); color: #06b6d4; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; border: 1px solid rgba(6, 182, 212, 0.3); white-space: nowrap; margin-bottom: 4px;">
                                                🔄 <?= htmlspecialchars($rs['activity_type']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-block; background-color: rgba(34, 197, 94, 0.15); color: #22c55e; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; border: 1px solid rgba(34, 197, 94, 0.3); white-space: nowrap; margin-bottom: 4px;">
                                                📋 <?= htmlspecialchars($rs['activity_type']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php
                                        $timestamp = strtotime($rs['created_at']);
                                        $date_thai = date('d/m/', $timestamp) . (date('Y', $timestamp) + 543) . ' ' . date('H:i', $timestamp);
                                        ?>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px; white-space: nowrap;">
                                            📅 <?= $date_thai ?> น.
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($rs['house_no']) ?></td>
                                    <td style="white-space: nowrap;">
                                        <?php
                                        $logical_tambon = $hoscode_villages[$rs['hoscode']]['tambon'] ?? $rs['sub_district_code'] ?? null;
                                        $village_only = $hoscode_villages[$rs['hoscode']]['villages'][intval($rs['moo'])] ?? get_village_only_name($logical_tambon, $rs['moo']);
                                        echo htmlspecialchars($village_only);
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($rs['moo']) ?></td>
                                    <td>
                                        <?php if ($rs['sys_bp'] >= 140 || $rs['dia_bp'] >= 90): ?>
                                            <span
                                                style="color: var(--color-red); font-weight: bold;"><?= $rs['sys_bp'] ?>/<?= $rs['dia_bp'] ?></span>
                                        <?php else: ?>
                                            <?= $rs['sys_bp'] ?>/<?= $rs['dia_bp'] ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rs['dtx_value'] !== null): ?>
                                            <?php if ($rs['dtx_value'] >= 126): ?>
                                                <span style="color: var(--color-red); font-weight: bold;"><?= $rs['dtx_value'] ?></span>
                                            <?php else: ?>
                                                <?= $rs['dtx_value'] ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $rs['bmi'] ?: '-' ?></td>
                                    <td>
                                        <?php if ($rs['cv_risk_score'] !== null): ?>
                                            <?php if ($rs['cv_risk_score'] >= 10): ?>
                                                <span
                                                    style="background-color: rgba(239, 68, 68, 0.2); color: var(--color-red); padding: 4px 8px; border-radius: 4px; font-weight: bold;"><?= $rs['cv_risk_score'] ?>%</span>
                                            <?php else: ?>
                                                <?= $rs['cv_risk_score'] ?>%
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space: nowrap;"><?= htmlspecialchars($rs['vhv_name']) ?></td>
                                    <td style="font-size: 13px; color: var(--text-secondary);">
                                        <?php if ($rs['screening_lat'] && $rs['screening_lng']): ?>
                                            <?= round($rs['screening_lat'], 5) ?>, <?= round($rs['screening_lng'], 5) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Heatmap Section (Bento Full Span) -->
        <div class="bento-card" style="margin-top: 14px; margin-bottom: 24px;">
            <div class="bento-header" style="border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 14px;">
                <h2 class="bento-title" style="font-size: 16px;">
                    <span class="bento-icon-badge" style="width: 32px; height: 32px; border-radius: 8px; background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                    <span>Geographic NCDs Hotspot Heatmap (แผนที่กลุ่มเสี่ยงสูง อำเภอตาลสุม)</span>
                </h2>
            </div>
            <p style="color: var(--text-secondary); margin-bottom: 14px; font-size: 13.5px;">
                แผนที่แสดงการกระจุกตัวของประชากรกลุ่มเป้าหมาย แบ่งตามระดับความเสี่ยง สามารถกรองตามกลุ่มเสี่ยง
                และเขตรับผิดชอบ รพ.สต. ได้
            </p>

            <!-- Filter Buttons: Risk Groups -->
            <div style="margin-bottom: 12px;">
                <span
                    style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-right: 8px;">กรองตามกลุ่มเสี่ยง:</span>
                <div style="display: inline-flex; gap: 6px; flex-wrap: wrap;">
                    <button onclick="toggleRiskFilter('all')" id="btn-risk-all" class="map-filter-btn active"
                        style="background: var(--color-primary); color: white; border: 2px solid var(--color-primary); padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.2s;">
                        🌐 ทั้งหมด
                    </button>
                    <button onclick="toggleRiskFilter('high')" id="btn-risk-high" class="map-filter-btn"
                        style="background: transparent; color: var(--color-red); border: 2px solid var(--color-red); padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.2s;">
                        🔴 เสี่ยงสูง
                    </button>
                    <button onclick="toggleRiskFilter('moderate')" id="btn-risk-moderate" class="map-filter-btn"
                        style="background: transparent; color: var(--color-yellow); border: 2px solid var(--color-yellow); padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.2s;">
                        🟡 เสี่ยงปานกลาง
                    </button>
                    <button onclick="toggleRiskFilter('normal')" id="btn-risk-normal" class="map-filter-btn"
                        style="background: transparent; color: var(--color-green); border: 2px solid var(--color-green); padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.2s;">
                        🟢 ปกติ / ยังไม่คัดกรอง
                    </button>
                </div>
            </div>

            <!-- Filter Buttons: Service Area -->
            <div style="margin-bottom: 16px;">
                <span
                    style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-right: 8px;">กรองตามเขต
                    รพ.สต.:</span>
                <div style="display: inline-flex; gap: 6px; flex-wrap: wrap;">
                    <button onclick="toggleHosFilter('all')" id="btn-hos-all" class="map-filter-btn active"
                        style="background: var(--color-accent); color: var(--bg-card); border: 2px solid var(--color-accent); padding: 5px 12px; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: bold; transition: all 0.2s;">
                        ทุกเขต
                    </button>
                    <?php foreach ($mapHoscodes as $hc): ?>
                        <button onclick="toggleHosFilter('<?= $hc ?>')" id="btn-hos-<?= $hc ?>" class="map-filter-btn"
                            style="background: transparent; color: var(--color-accent); border: 2px solid var(--border-color); padding: 5px 12px; border-radius: 20px; cursor: pointer; font-size: 12px; transition: all 0.2s;">
                            <?= htmlspecialchars($hc_names[$hc] ?? $hc) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Map Counter -->
            <div id="map-counter" style="margin-bottom: 10px; font-size: 13px; color: var(--text-secondary);">
                📍 แสดง <strong id="visible-count">0</strong> จุด จากทั้งหมด
                <strong><?= count($allMapTargets) ?></strong> จุด
            </div>

            <div id="map"></div>

            <!-- Coordinate Editing Section -->
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <button onclick="toggleEditMode()" id="btn-edit-coords"
                        style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; border: none; padding: 8px 18px; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.3s; box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);">
                        📍 แก้ไขพิกัดบ้าน
                    </button>
                    <div id="edit-controls" style="display: none; flex: 1; min-width: 300px;">
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <select id="edit-target-select" onchange="onTargetSelected()"
                                style="flex: 1; min-width: 250px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-darker); color: var(--text-primary); font-size: 13px;">
                                <option value="">-- เลือกเป้าหมายที่ต้องการแก้ไขพิกัด --</option>
                                <?php foreach ($editableTargets as $et): ?>
                                    <?php
                                    $village_only = get_village_only_name($et['sub_district_code'], $et['moo']);
                                    $hasCoord = ($et['latitude'] && $et['longitude']) ? '✅' : '❌';
                                    ?>
                                    <option value="<?= htmlspecialchars($et['cid']) ?>" data-lat="<?= $et['latitude'] ?>"
                                        data-lng="<?= $et['longitude'] ?>">
                                        <?= $hasCoord ?> หมู่ <?= $et['moo'] ?> <?= htmlspecialchars($village_only) ?> -
                                        บ้านเลขที่ <?= htmlspecialchars($et['house_no']) ?> |
                                        <?= htmlspecialchars($et['first_name'] . ' ' . $et['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button onclick="saveNewCoordinate()" id="btn-save-coord"
                                style="display: none; background: var(--color-green); color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: bold; white-space: nowrap;">
                                💾 บันทึกพิกัด
                            </button>
                            <button onclick="cancelEditMode()"
                                style="background: transparent; color: var(--color-red); border: 1px solid var(--color-red); padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: bold; white-space: nowrap;">
                                ✕ ยกเลิก
                            </button>
                        </div>
                        <div id="edit-status" style="margin-top: 8px; font-size: 12px; color: var(--text-secondary);">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ApexCharts Initialization -->
        <script>
            // Theme detection for charts
            const isDark = (localStorage.getItem('theme') === 'dark' || document.documentElement.getAttribute('data-theme') === 'dark');

            // Data from PHP
            const hcNamesChart = <?= json_encode($hc_names) ?>;

            const isRegularAdmin = <?= json_encode($admin_hoscode !== null) ?>;

            // Custom pie/donut chart data label formatter to prevent deceptive 100% rounding when small values exist
            const pieLabelFormatter = function(val, opts) {
                const rawVal = opts.w.config.series[opts.seriesIndex];
                if (rawVal === 0) return '';
                if (val > 0 && val < 1) return val.toFixed(2) + "%";
                if (val > 99 && val < 100) return val.toFixed(2) + "%";
                return Math.round(val) + "%";
            };

            // Coverage Data
            const coverageRaw = <?= json_encode($chartCoverageData) ?>;
            const rescreenRaw = <?= json_encode($chartRescreenData ?? []) ?>;
            const covCategories = (coverageRaw && coverageRaw.length > 0) ? 
                coverageRaw.map(d => isRegularAdmin ? (d.village_name || "หมู่ " + d.moo) : (hcNamesChart[d.hoscode] || d.hoscode)) :
                rescreenRaw.map(d => isRegularAdmin ? (d.village_name || "หมู่ " + d.moo) : (hcNamesChart[d.hoscode] || d.hoscode));

            let chartCoverageInstance = null;

            function getCoverageDataset(roundKey) {
                const dataSource = (rescreenRaw && rescreenRaw.length > 0) ? rescreenRaw : coverageRaw;
                const totals = [];
                const screeneds = [];

                dataSource.forEach(d => {
                    const total = parseInt(d.total_targets) || 0;
                    let sc = 0;
                    if (roundKey === 'r1') {
                        sc = parseInt(d.r1_completed) || 0;
                    } else if (roundKey === 'r2') {
                        sc = parseInt(d.r2_completed) || 0;
                    } else if (roundKey === 'r3') {
                        sc = parseInt(d.r3_completed) || 0;
                    } else {
                        const covMatch = (coverageRaw || []).find(c => isRegularAdmin ? (c.moo === d.moo) : (c.hoscode === d.hoscode));
                        sc = covMatch ? (parseInt(covMatch.screened) || 0) : ((parseInt(d.r1_completed) || 0) + (parseInt(d.r2_completed) || 0) + (parseInt(d.r3_completed) || 0));
                    }
                    totals.push(total);
                    screeneds.push(sc);
                });

                const screenedName = (roundKey === 'r1') ? 'คัดกรองรอบ 1' : ((roundKey === 'r2') ? 'ติดตามสำเร็จรอบ 2' : ((roundKey === 'r3') ? 'ติดตามสำเร็จรอบ 3+' : 'คัดกรองแล้ว (สะสม)'));
                const screenedColor = (roundKey === 'r1') ? '#22c55e' : ((roundKey === 'r2') ? '#0ea5e9' : ((roundKey === 'r3') ? '#8b5cf6' : '#22c55e'));

                return {
                    series: [
                        { name: 'เป้าหมายทั้งหมด', data: totals },
                        { name: screenedName, data: screeneds }
                    ],
                    colors: ['#4b5563', screenedColor]
                };
            }

            window.switchCoverageRound = function(roundKey) {
                document.querySelectorAll('.btn-cov-round').forEach(b => {
                    b.style.background = 'transparent';
                    b.style.color = 'var(--text-secondary)';
                });
                const activeBtn = document.getElementById('btn-cov-' + roundKey);
                if (activeBtn) {
                    const activeColor = (roundKey === 'r1') ? '#22c55e' : ((roundKey === 'r2') ? '#0ea5e9' : ((roundKey === 'r3') ? '#8b5cf6' : '#8b5cf6'));
                    activeBtn.style.background = activeColor;
                    activeBtn.style.color = '#ffffff';
                }

                const roundTitles = {
                    'all': 'ความครอบคลุมการคัดกรอง (ทุกรอบ)',
                    'r1': 'ความครอบคลุมการคัดกรอง (รอบ 1)',
                    'r2': 'ความครอบคลุมการคัดกรอง (รอบ 2)',
                    'r3': 'ความครอบคลุมการคัดกรอง (รอบ 3+)'
                };
                const titleEl = document.getElementById('coverage-chart-title');
                if (titleEl && roundTitles[roundKey]) {
                    titleEl.innerText = roundTitles[roundKey];
                }

                const ds = getCoverageDataset(roundKey);
                if (chartCoverageInstance) {
                    chartCoverageInstance.updateOptions({
                        colors: ds.colors
                    });
                    chartCoverageInstance.updateSeries(ds.series);
                }
            };

            const initCovData = getCoverageDataset('all');
            const hasCoverageData = initCovData.series[0].data.reduce((a, b) => a + b, 0);

            if (hasCoverageData > 0) {
                var optionsCoverage = {
                    series: initCovData.series,
                    chart: {
                        type: 'bar',
                        height: 310,
                        background: 'transparent',
                        toolbar: {
                            show: false
                        }
                    },
                    theme: {
                        mode: localStorage.getItem('theme') || 'light'
                    },
                    colors: initCovData.colors,
                    legend: {
                        position: 'bottom',
                        labels: {
                            colors: '#9ca3af'
                        }
                    },
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
                    xaxis: {
                        categories: covCategories,
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
                    tooltip: {
                        theme: localStorage.getItem('theme') || 'light',
                        y: {
                            formatter: function(val, opts) {
                                const targetVal = opts.w.config.series[0].data[opts.dataPointIndex] || 1;
                                const pct = Math.round((val / targetVal) * 100);
                                return val.toLocaleString() + " ราย" + (opts.seriesIndex === 1 ? (" (" + pct + "%)") : "");
                            }
                        }
                    }
                };
                chartCoverageInstance = new ApexCharts(document.querySelector("#chart-coverage"), optionsCoverage);
                chartCoverageInstance.render();
            } else {
                document.querySelector("#chart-coverage").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 100px; font-size: 14px;">ยังไม่มีข้อมูลความครอบคลุมการคัดกรอง</div>';
            }

            // Risk Data
            const riskRaw = <?= json_encode($chartRiskData) ?>;
            const riskByRoundRaw = <?= json_encode($chartRiskByRoundData ?? []) ?>;

            const riskCategories = isRegularAdmin ?
                coverageRaw.map(d => d.village_name || "หมู่ " + d.moo) : [...new Set(coverageRaw.map(d => d.hoscode))].map(hc => hcNamesChart[hc] || hc);

            let chartRiskInstance = null;

            function getRiskDataset(roundKey) {
                const normal = [];
                const moderate = [];
                const high = [];
                const unscreened = [];

                const categoriesSource = (coverageRaw && coverageRaw.length > 0) ? coverageRaw : rescreenRaw;

                if (roundKey === 'all') {
                    if (isRegularAdmin) {
                        categoriesSource.forEach(covRow => {
                            const match = riskRaw.find(d => d.moo === covRow.moo && d.sub_district_code === covRow.sub_district_code) || {
                                normal: 0, moderate_risk: 0, high_risk: 0, unscreened: 0
                            };
                            normal.push(parseInt(match.normal) || 0);
                            moderate.push(parseInt(match.moderate_risk) || 0);
                            high.push(parseInt(match.high_risk) || 0);
                            unscreened.push(parseInt(match.unscreened) || 0);
                        });
                    } else {
                        const allHoscodesRaw = [...new Set(categoriesSource.map(d => d.hoscode))];
                        allHoscodesRaw.forEach(hc => {
                            const match = riskRaw.find(d => d.hoscode === hc) || {
                                normal: 0, moderate_risk: 0, high_risk: 0, unscreened: 0
                            };
                            normal.push(parseInt(match.normal) || 0);
                            moderate.push(parseInt(match.moderate_risk) || 0);
                            high.push(parseInt(match.high_risk) || 0);
                            unscreened.push(parseInt(match.unscreened) || 0);
                        });
                    }
                } else {
                    const targetRoundNum = (roundKey === 'r1') ? 1 : ((roundKey === 'r2') ? 2 : 3);
                    if (isRegularAdmin) {
                        categoriesSource.forEach(covRow => {
                            const totalTarget = parseInt(covRow.total_targets) || 0;
                            const match = riskByRoundRaw.find(d => (d.moo == covRow.moo) && (parseInt(d.round_number) === targetRoundNum || (targetRoundNum >= 3 && parseInt(d.round_number) >= 3))) || {
                                normal: 0, moderate_risk: 0, high_risk: 0
                            };
                            const normVal = parseInt(match.normal) || 0;
                            const modVal = parseInt(match.moderate_risk) || 0;
                            const highVal = parseInt(match.high_risk) || 0;
                            const screenedInRound = normVal + modVal + highVal;

                            const rescreenItem = (rescreenRaw || []).find(r => r.moo == covRow.moo);
                            let unscVal = 0;
                            if (roundKey === 'r1') {
                                const r1Comp = rescreenItem ? (parseInt(rescreenItem.r1_completed) || 0) : screenedInRound;
                                unscVal = Math.max(0, totalTarget - Math.max(screenedInRound, r1Comp));
                            } else if (roundKey === 'r2') {
                                unscVal = rescreenItem ? (parseInt(rescreenItem.r2_assigned) || 0) : 0;
                            } else {
                                unscVal = rescreenItem ? (parseInt(rescreenItem.r3_assigned) || 0) : 0;
                            }

                            normal.push(normVal);
                            moderate.push(modVal);
                            high.push(highVal);
                            unscreened.push(unscVal);
                        });
                    } else {
                        const allHoscodesRaw = [...new Set(categoriesSource.map(d => d.hoscode))];
                        allHoscodesRaw.forEach(hc => {
                            const covItem = categoriesSource.find(c => c.hoscode === hc);
                            const totalTarget = covItem ? (parseInt(covItem.total_targets) || 0) : 0;
                            const match = riskByRoundRaw.find(d => (d.hoscode === hc) && (parseInt(d.round_number) === targetRoundNum || (targetRoundNum >= 3 && parseInt(d.round_number) >= 3))) || {
                                normal: 0, moderate_risk: 0, high_risk: 0
                            };
                            const normVal = parseInt(match.normal) || 0;
                            const modVal = parseInt(match.moderate_risk) || 0;
                            const highVal = parseInt(match.high_risk) || 0;
                            const screenedInRound = normVal + modVal + highVal;

                            const rescreenItem = (rescreenRaw || []).find(r => r.hoscode === hc);
                            let unscVal = 0;
                            if (roundKey === 'r1') {
                                const r1Comp = rescreenItem ? (parseInt(rescreenItem.r1_completed) || 0) : screenedInRound;
                                unscVal = Math.max(0, totalTarget - Math.max(screenedInRound, r1Comp));
                            } else if (roundKey === 'r2') {
                                unscVal = rescreenItem ? (parseInt(rescreenItem.r2_assigned) || 0) : 0;
                            } else {
                                unscVal = rescreenItem ? (parseInt(rescreenItem.r3_assigned) || 0) : 0;
                            }

                            normal.push(normVal);
                            moderate.push(modVal);
                            high.push(highVal);
                            unscreened.push(unscVal);
                        });
                    }
                }

                return [
                    { name: 'ปกติ (เสี่ยงต่ำ)', data: normal },
                    { name: 'เสี่ยงปานกลาง', data: moderate },
                    { name: 'เสี่ยงสูง/สงสัยป่วย', data: high },
                    { name: 'ยังไม่คัดกรอง', data: unscreened }
                ];
            }

            window.switchRiskRound = function(roundKey) {
                document.querySelectorAll('.btn-risk-round').forEach(b => {
                    b.style.background = 'transparent';
                    b.style.color = 'var(--text-secondary)';
                });
                const activeBtn = document.getElementById('btn-risk-' + roundKey);
                if (activeBtn) {
                    activeBtn.style.background = '#f43f5e';
                    activeBtn.style.color = '#ffffff';
                }

                const roundTitles = {
                    'all': 'ระดับความเสี่ยงประชากร (ล่าสุด)',
                    'r1': 'ระดับความเสี่ยงประชากร (รอบ 1)',
                    'r2': 'ระดับความเสี่ยงประชากร (รอบ 2)',
                    'r3': 'ระดับความเสี่ยงประชากร (รอบ 3+)'
                };
                const titleEl = document.getElementById('risk-chart-title');
                if (titleEl && roundTitles[roundKey]) {
                    titleEl.innerText = roundTitles[roundKey];
                }

                const newSeries = getRiskDataset(roundKey);
                if (chartRiskInstance) {
                    chartRiskInstance.updateSeries(newSeries);
                }
            };

            const initRiskSeries = getRiskDataset('all');
            const hasRiskData = initRiskSeries.reduce((sum, s) => sum + s.data.reduce((a, b) => a + b, 0), 0);

            if (hasRiskData > 0) {
                var optionsRisk = {
                    series: initRiskSeries,
                    chart: {
                        type: 'bar',
                        height: 310,
                        stacked: true,
                        stackType: '100%',
                        background: 'transparent',
                        toolbar: {
                            show: false
                        }
                    },
                    theme: {
                        mode: localStorage.getItem('theme') || 'light'
                    },
                    colors: ['#22c55e', '#f59e0b', '#ef4444', '#4b5563'],
                    legend: {
                        position: 'bottom',
                        labels: {
                            colors: '#9ca3af'
                        }
                    },
                    plotOptions: {
                        bar: {
                            borderRadius: 2
                        }
                    },
                    xaxis: {
                        categories: riskCategories,
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
                    tooltip: {
                        theme: localStorage.getItem('theme') || 'light',
                        y: {
                            formatter: function(val, opts) {
                                return val.toLocaleString() + " ราย";
                            }
                        }
                    },
                    fill: {
                        opacity: 1
                    }
                };
                chartRiskInstance = new ApexCharts(document.querySelector("#chart-risk"), optionsRisk);
                chartRiskInstance.render();
            } else {
                document.querySelector("#chart-risk").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 100px; font-size: 14px;">ยังไม่มีข้อมูลระดับความเสี่ยงประชากร</div>';
            }

            // Disease Data
            const diseaseRaw = <?= json_encode($chartDiseaseData) ?>;
            const diseaseSeries = [
                parseInt(diseaseRaw?.normal_group || 0),
                parseInt(diseaseRaw?.risk_group || 0),
                parseInt(diseaseRaw?.dm_only || 0),
                parseInt(diseaseRaw?.ht_only || 0),
                parseInt(diseaseRaw?.ht_dm || 0)
            ];

            // Disease Chart (Modern Horizontal Rounded Capsule Bars)
            const totalDiseaseCount = diseaseSeries.reduce((a, b) => a + b, 0);
            if (totalDiseaseCount > 0) {
                const diseaseCats = ['ปกติ (เสี่ยงต่ำ)', 'กลุ่มเสี่ยง', 'ป่วย/สงสัย DM', 'ป่วย/สงสัย HT', 'ป่วยทั้ง HT+DM'];
                var optionsDisease = {
                    series: [{
                        name: 'จำนวนประชากร',
                        data: diseaseSeries
                    }],
                    chart: {
                        type: 'bar',
                        height: 215,
                        background: 'transparent',
                        toolbar: { show: false }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 6,
                            borderRadiusApplication: 'end',
                            barHeight: '62%',
                            distributed: true,
                            dataLabels: {
                                position: 'right'
                            }
                        }
                    },
                    colors: ['#10b981', '#f59e0b', '#8b5cf6', '#3b82f6', '#ec4899'],
                    dataLabels: {
                        enabled: true,
                        textAnchor: 'start',
                        offsetX: 6,
                        formatter: function(val) {
                            if (!val) return '0 คน';
                            var pct = ((val / totalDiseaseCount) * 100).toFixed(1);
                            return Number(val).toLocaleString() + ' คน (' + pct + '%)';
                        },
                        style: {
                            fontSize: '10.5px',
                            fontFamily: 'Prompt',
                            fontWeight: '800',
                            colors: [isDark ? '#cbd5e1' : '#334155']
                        }
                    },
                    xaxis: {
                        categories: diseaseCats,
                        labels: {
                            style: { colors: '#9ca3af', fontFamily: 'Prompt', fontSize: '10px' },
                            formatter: function(val) { return Number(val).toLocaleString(); }
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: '#9ca3af',
                                fontFamily: 'Prompt',
                                fontSize: '10.5px',
                                fontWeight: '700'
                            }
                        }
                    },
                    grid: {
                        borderColor: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
                        strokeDashArray: 4,
                        padding: { top: -10, bottom: -5 }
                    },
                    legend: { show: false },
                    tooltip: {
                        theme: localStorage.getItem('theme') || 'light',
                        y: {
                            formatter: function(val) {
                                return Number(val).toLocaleString() + " ราย (" + ((val / totalDiseaseCount) * 100).toFixed(1) + "%)";
                            }
                        }
                    }
                };
                new ApexCharts(document.querySelector("#chart-disease"), optionsDisease).render();
            } else {
                document.querySelector("#chart-disease").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 100px; font-size: 14px;">ยังไม่มีข้อมูลผลการคัดกรองแยกกลุ่มโรค</div>';
            }

            // Trend Data
            const trendRaw = <?= json_encode($chartTrendData) ?>;
            const trendCategories = trendRaw.map(d => d.screen_date);
            const trendCounts = trendRaw.map(d => parseInt(d.daily_count));

            // Trend Chart (Area)
            if (trendRaw && trendRaw.length > 0) {
                var optionsTrend = {
                    series: [{
                        name: 'จำนวนคัดกรอง',
                        data: trendCounts
                    }],
                    chart: {
                        type: 'area',
                        height: 215,
                        background: 'transparent',
                        toolbar: {
                            show: false
                        }
                    },
                    theme: {
                        mode: localStorage.getItem('theme') || 'light'
                    },
                    colors: ['#0ea5e9'],
                    legend: {
                        position: 'bottom',
                        offsetY: 0,
                        labels: {
                            colors: '#9ca3af'
                        }
                    },
                    dataLabels: {
                        enabled: true,
                        style: { fontSize: '10px' }
                    },
                    stroke: {
                        curve: 'smooth',
                        width: 2
                    },
                    xaxis: {
                        categories: trendCategories,
                        labels: {
                            style: {
                                colors: '#9ca3af',
                                fontSize: '10px'
                            },
                            formatter: function(val) {
                                if (!val) return '';
                                const parts = val.split('-');
                                if (parts.length < 3) return val;
                                return parts[2] + '/' + parts[1]; // DD/MM format
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: '#9ca3af',
                                fontSize: '10px'
                            }
                        }
                    },
                    tooltip: {
                        theme: localStorage.getItem('theme') || 'light'
                    },
                    grid: {
                        padding: { top: -10, bottom: -5 }
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.7,
                            opacityTo: 0.1,
                            stops: [0, 90, 100]
                        }
                    }
                };
                new ApexCharts(document.querySelector("#chart-trend"), optionsTrend).render();
            } else {
                document.querySelector("#chart-trend").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 100px; font-size: 14px;">ยังไม่มีข้อมูลแนวโน้มการคัดกรองรายวัน</div>';
            }

            // Re-screening Multi-Round Chart
            const rescreenCategories = rescreenRaw.map(d => isRegularAdmin ? (d.village_name || "หมู่ " + d.moo) : (hcNamesChart[d.hoscode] || d.hoscode));
            const r1Completed = rescreenRaw.map(d => parseInt(d.r1_completed) || 0);
            const r2Completed = rescreenRaw.map(d => parseInt(d.r2_completed) || 0);
            const r3Completed = rescreenRaw.map(d => parseInt(d.r3_completed) || 0);

            if (rescreenRaw && rescreenRaw.length > 0) {
                var optionsRescreen = {
                    series: [{
                        name: 'รอบที่ 1 (Baseline)',
                        data: r1Completed
                    }, {
                        name: 'ติดตามซ้ำ รอบที่ 2',
                        data: r2Completed
                    }, {
                        name: 'ติดตามซ้ำ รอบที่ 3+',
                        data: r3Completed
                    }],
                    chart: {
                        type: 'bar',
                        height: 240,
                        background: 'transparent',
                        toolbar: {
                            show: false
                        }
                    },
                    theme: {
                        mode: localStorage.getItem('theme') || 'light'
                    },
                    colors: ['#22c55e', '#3b82f6', '#8b5cf6'],
                    legend: {
                        position: 'bottom',
                        labels: {
                            colors: '#9ca3af'
                        }
                    },
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
                    xaxis: {
                        categories: rescreenCategories,
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
                    tooltip: {
                        theme: localStorage.getItem('theme') || 'light',
                        y: {
                            formatter: function(val, opts) {
                                const dataObj = rescreenRaw[opts.dataPointIndex];
                                const seriesIdx = opts.seriesIndex;
                                let denominator = parseInt(dataObj.total_targets) || 1;
                                if (seriesIdx === 1) {
                                    denominator = (parseInt(dataObj.r2_completed) || 0) + (parseInt(dataObj.r2_assigned) || 0);
                                    if (denominator === 0) denominator = parseInt(dataObj.r1_completed) || 1;
                                } else if (seriesIdx === 2) {
                                    denominator = (parseInt(dataObj.r3_completed) || 0) + (parseInt(dataObj.r3_assigned) || 0);
                                    if (denominator === 0) denominator = parseInt(dataObj.r2_completed) || 1;
                                }
                                const pct = ((val / denominator) * 100).toFixed(1);
                                return val + " ราย (" + pct + "% ของงานมอบหมายรอบนี้)";
                            }
                        }
                    }
                };
                new ApexCharts(document.querySelector("#chart-rescreen"), optionsRescreen).render();
            } else {
                document.querySelector("#chart-rescreen").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 100px; font-size: 14px;">ยังไม่มีข้อมูลการคัดกรองติดตามซ้ำ</div>';
            }

            // Overall Progress Chart (Multi-Round Support)
            let chartOverallProgressInstance = null;

            function getProgressDataset(roundKey) {
                const progressData = [];
                let totalTargetsSum = 0;
                let totalScreenedSum = 0;

                const dataSource = (rescreenRaw && rescreenRaw.length > 0) ? rescreenRaw : coverageRaw;

                dataSource.forEach(d => {
                    let targets = parseInt(d.total_targets) || 0;
                    let screened = 0;
                    let barColor = '#0ea5e9';

                    if (roundKey === 'r1') {
                        screened = parseInt(d.r1_completed) || 0;
                        targets = parseInt(d.total_targets) || 0;
                        barColor = '#22c55e';
                    } else if (roundKey === 'r2') {
                        screened = parseInt(d.r2_completed) || 0;
                        const r2Assigned = parseInt(d.r2_assigned) || 0;
                        const r1Completed = parseInt(d.r1_completed) || 0;
                        targets = (screened + r2Assigned > 0) ? (screened + r2Assigned) : (r1Completed > 0 ? r1Completed : (parseInt(d.total_targets) || 0));
                        barColor = '#3b82f6';
                    } else if (roundKey === 'r3') {
                        screened = parseInt(d.r3_completed) || 0;
                        const r3Assigned = parseInt(d.r3_assigned) || 0;
                        const r2Completed = parseInt(d.r2_completed) || 0;
                        targets = (screened + r3Assigned > 0) ? (screened + r3Assigned) : (r2Completed > 0 ? r2Completed : (parseInt(d.total_targets) || 0));
                        barColor = '#8b5cf6';
                    } else {
                        // All rounds combined (ทุกรอบ)
                        const covMatch = (coverageRaw || []).find(c => isRegularAdmin ? (c.moo === d.moo) : (c.hoscode === d.hoscode));
                        if (covMatch && covMatch.screened !== undefined) {
                            screened = parseInt(covMatch.screened) || 0;
                        } else {
                            screened = (parseInt(d.r1_completed) || 0) + (parseInt(d.r2_completed) || 0) + (parseInt(d.r3_completed) || 0);
                        }
                        targets = parseInt(d.total_targets) || 0;
                        barColor = '#0ea5e9';
                    }

                    totalTargetsSum += targets;
                    totalScreenedSum += screened;

                    const pct = targets > 0 ? Math.min(100, Math.round((screened / targets) * 100)) : 0;
                    const label = isRegularAdmin ? (d.village_name || "หมู่ " + d.moo) : (hcNamesChart[d.hoscode] || d.hoscode);

                    progressData.push({
                        x: label,
                        y: pct,
                        screened: screened,
                        targets: targets,
                        fillColor: barColor
                    });
                });

                // Sort by percentage descending, then by screened count descending
                progressData.sort((a, b) => (b.y - a.y) || (b.screened - a.screened));

                // Overall total percentage for this round
                const overallPct = totalTargetsSum > 0 ? Math.min(100, Math.round((totalScreenedSum / totalTargetsSum) * 100)) : 0;
                const overallColor = roundKey === 'r1' ? '#16a34a' : (roundKey === 'r2' ? '#2563eb' : (roundKey === 'r3' ? '#7c3aed' : '#0284c7'));

                // Push overall first (always at the top)
                progressData.unshift({
                    x: isRegularAdmin ? 'ภาพรวมหน่วยบริการ' : 'ภาพรวมทั้งอำเภอ',
                    y: overallPct,
                    screened: totalScreenedSum,
                    targets: totalTargetsSum,
                    fillColor: overallColor
                });

                return progressData;
            }

            window.switchOverallRound = function(roundKey) {
                document.querySelectorAll('.btn-progress-round').forEach(b => {
                    b.style.background = 'transparent';
                    b.style.color = 'var(--text-secondary)';
                });
                const activeBtn = document.getElementById('btn-ovr-' + roundKey);
                if (activeBtn) {
                    const activeBg = (roundKey === 'r1' ? '#22c55e' : (roundKey === 'r2' ? '#3b82f6' : (roundKey === 'r3' ? '#8b5cf6' : '#0ea5e9')));
                    activeBtn.style.background = activeBg;
                    activeBtn.style.color = '#ffffff';
                }

                const roundTitles = {
                    'all': 'ความคืบหน้า (ทุกรอบ)',
                    'r1': 'ความคืบหน้า (รอบ 1)',
                    'r2': 'ความคืบหน้า (รอบ 2)',
                    'r3': 'ความคืบหน้า (รอบ 3+)'
                };
                const titleEl = document.getElementById('overall-progress-title');
                if (titleEl && roundTitles[roundKey]) {
                    titleEl.innerText = roundTitles[roundKey];
                }

                const newData = getProgressDataset(roundKey);
                if (chartOverallProgressInstance) {
                    chartOverallProgressInstance.updateOptions({
                        xaxis: {
                            categories: newData.map(d => d.x)
                        },
                        series: [{
                            name: 'ความคืบหน้า (%)',
                            data: newData
                        }]
                    }, true, true);
                }
            };

            const initialProgressData = getProgressDataset('all');

            if ((coverageRaw && coverageRaw.length > 0) || (rescreenRaw && rescreenRaw.length > 0)) {
                var optionsProgress = {
                    series: [{
                        name: 'ความคืบหน้า (%)',
                        data: initialProgressData
                    }],
                    chart: {
                        height: 230,
                        type: 'bar',
                        background: 'transparent',
                        toolbar: {
                            show: false
                        }
                    },
                    theme: {
                        mode: localStorage.getItem('theme') || 'light'
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 3,
                            barHeight: '72%',
                            dataLabels: {
                                position: 'top'
                            }
                        }
                    },
                    dataLabels: {
                        enabled: true,
                        offsetX: 16,
                        style: {
                            colors: ['#9ca3af'],
                            fontSize: '10.5px'
                        },
                        formatter: function(val) {
                            return val + "%"
                        }
                    },
                    xaxis: {
                        categories: initialProgressData.map(d => d.x),
                        max: 100,
                        labels: {
                            style: {
                                colors: '#9ca3af',
                                fontSize: '10px'
                            },
                            formatter: function(val) {
                                return val + "%"
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: '#9ca3af',
                                fontSize: '11px',
                                fontWeight: 'bold'
                            }
                        }
                    },
                    grid: {
                        padding: { top: -10, bottom: -5 }
                    },
                    tooltip: {
                        theme: localStorage.getItem('theme') || 'light',
                        y: {
                            formatter: function(val, opts) {
                                const dp = opts.w.config.series[0].data[opts.dataPointIndex];
                                if (dp && dp.screened !== undefined && dp.targets !== undefined) {
                                    return val + "% (" + Number(dp.screened).toLocaleString() + " / " + Number(dp.targets).toLocaleString() + " ราย)";
                                }
                                return val + "%";
                            }
                        }
                    }
                };
                chartOverallProgressInstance = new ApexCharts(document.querySelector("#chart-overall-progress"), optionsProgress);
                chartOverallProgressInstance.render();
            } else {
                document.querySelector("#chart-overall-progress").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 60px; font-size: 13px;">ยังไม่มีข้อมูลความคืบหน้า</div>';
            }

            // Screened Risk Distribution Data (Semi-Circle Donut Health Gauge)
            const screenedDetailRaw = <?= json_encode($screenedDetail) ?>;
            const screenedRiskSeries = [
                parseInt(screenedDetailRaw?.normal || 0),
                parseInt(screenedDetailRaw?.risk || 0),
                parseInt(screenedDetailRaw?.high_risk || 0)
            ];
            const totalScreenedRisk = screenedRiskSeries.reduce((a, b) => a + b, 0);

            if (totalScreenedRisk > 0) {
                var optionsScreenedRisk = {
                    series: screenedRiskSeries,
                    labels: ['🟢 ปกติ (เสี่ยงต่ำ)', '🟡 เสี่ยงปานกลาง', '🔴 เสี่ยงสูง (สงสัยป่วย)'],
                    chart: {
                        type: 'donut',
                        height: 185,
                        background: 'transparent'
                    },
                    plotOptions: {
                        pie: {
                            startAngle: -90,
                            endAngle: 90,
                            offsetY: 0,
                            donut: {
                                size: '72%',
                                labels: {
                                    show: true,
                                    name: {
                                        show: true,
                                        fontSize: '11px',
                                        fontFamily: 'Prompt',
                                        color: '#9ca3af',
                                        offsetY: -18
                                    },
                                    value: {
                                        show: true,
                                        fontSize: '19px',
                                        fontFamily: 'Prompt',
                                        fontWeight: '900',
                                        color: isDark ? '#f8fafc' : '#0f172a',
                                        offsetY: -6,
                                        formatter: function(val) { return Number(val).toLocaleString() + ' คน'; }
                                    },
                                    total: {
                                        show: true,
                                        label: 'คัดกรองทั้งหมด',
                                        color: '#64748b',
                                        fontSize: '10.5px',
                                        fontFamily: 'Prompt',
                                        fontWeight: '700',
                                        formatter: function(w) {
                                             return w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString() + ' คน';
                                        }
                                    }
                                }
                            }
                        }
                    },
                    colors: ['#10b981', '#f59e0b', '#ef4444'],
                    stroke: {
                        width: 2.5,
                        colors: [isDark ? '#1e293b' : '#ffffff']
                    },
                    legend: {
                        position: 'bottom',
                        offsetY: -14,
                        fontSize: '10.5px',
                        fontFamily: 'Prompt',
                        labels: { colors: '#9ca3af' },
                        markers: { width: 8, height: 8, radius: 3 }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    tooltip: {
                        theme: localStorage.getItem('theme') || 'light',
                        y: {
                            formatter: function(val) {
                                return Number(val).toLocaleString() + " ราย (" + ((val / totalScreenedRisk) * 100).toFixed(1) + "%)";
                            }
                        }
                    },
                    grid: {
                        padding: { bottom: -60, top: -10 }
                    }
                };
                new ApexCharts(document.querySelector("#chart-screened-risk-pie"), optionsScreenedRisk).render();
            } else {
                document.querySelector("#chart-screened-risk-pie").innerHTML = `
                    <div style="height: 185px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 12px 14px; background: rgba(100, 116, 139, 0.04); border: 1.5px dashed rgba(100, 116, 139, 0.2); border-radius: 12px; box-sizing: border-box;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: rgba(100, 116, 139, 0.1); display: flex; align-items: center; justify-content: center; margin-bottom: 6px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                                <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                            </svg>
                        </div>
                        <div style="font-size: 13.5px; font-weight: 800; color: var(--text-primary); margin-bottom: 2px;">ยังไม่มีข้อมูลผลการคัดกรอง</div>
                        <div style="font-size: 11px; color: var(--text-secondary); max-width: 220px; line-height: 1.35;">ไม่พบข้อมูลระดับความเสี่ยงในเงื่อนไขและพื้นที่ที่เลือก</div>
                    </div>`;
            }

            // Skipped Reasons Chart
            const skippedRaw = <?= json_encode($chartSkippedData) ?>;
            if (skippedRaw && skippedRaw.length > 0) {
                var optionsSkipped = {
                    series: skippedRaw.map(d => parseInt(d.count)),
                    labels: skippedRaw.map(d => d.skipped_reason || 'ไม่ระบุ'),
                    chart: {
                        type: 'donut',
                        height: 185,
                        background: 'transparent'
                    },
                    theme: {
                        mode: localStorage.getItem('theme') || 'light'
                    },
                    colors: ['#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9', '#64748b'],
                    stroke: {
                        show: false
                    },
                    legend: {
                        position: 'bottom',
                        offsetY: -5,
                        fontSize: '10.5px',
                        labels: {
                            colors: '#9ca3af'
                        }
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: pieLabelFormatter
                    }
                };
                new ApexCharts(document.querySelector("#chart-skipped"), optionsSkipped).render();
            } else {
                document.querySelector("#chart-skipped").innerHTML = `
                    <div style="height: 185px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 10px 12px; background: rgba(16, 185, 129, 0.04); border: 1.5px dashed rgba(16, 185, 129, 0.25); border-radius: 12px; box-sizing: border-box;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: rgba(16, 185, 129, 0.12); display: flex; align-items: center; justify-content: center; margin-bottom: 6px; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.05);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <div style="font-size: 14px; font-weight: 800; color: #10b981; margin-bottom: 2px; letter-spacing: -0.2px;">
                            คัดกรองสมบูรณ์ 100%
                        </div>
                        <div style="font-size: 11px; color: var(--text-secondary); max-width: 230px; line-height: 1.35; margin-bottom: 6px;">
                            ไม่พบประวัติการข้ามเคส อสม. ติดตามตรวจคัดกรองกลุ่มเป้าหมายได้ครบถ้วนโดยไม่มีการตกหล่น
                        </div>
                        <span style="display: inline-flex; align-items: center; gap: 5px; background: rgba(16, 185, 129, 0.12); color: #059669; font-size: 10.5px; font-weight: 800; padding: 2px 10px; border-radius: 9999px; border: 1px solid rgba(16, 185, 129, 0.2);">
                            <span style="width: 5px; height: 5px; border-radius: 50%; background: #10b981;"></span>
                            เคสที่ถูกข้าม: 0 ราย
                        </span>
                    </div>`;
            }

            // DPAC Enrollments Chart (Modern Rounded Gradient Columns)
            const dpacRaw = <?= json_encode($chartDpacData) ?>;
            const mapDpacRiskType = function(rt) {
                if (!rt) return 'ไม่ระบุ';
                const s = String(rt).toUpperCase().trim();
                if (s === '1' || s === 'DM' || s === 'DIABETES') return 'เสี่ยงเบาหวาน';
                if (s === '2' || s === 'HT' || s === 'HYPERTENSION') return 'เสี่ยงความดัน';
                if (s === '3' || s === 'BOTH' || s === 'DM_HT' || s === 'HT_DM') return 'เสี่ยงทั้งคู่ (DM+HT)';
                return s;
            };

            if (dpacRaw && dpacRaw.length > 0) {
                const dpacCounts = dpacRaw.map(d => parseInt(d.count));
                const dpacCats = dpacRaw.map(d => mapDpacRiskType(d.risk_type));
                const dpacTotalSum = dpacCounts.reduce((a, b) => a + b, 0);

                var optionsDpac = {
                    series: [{
                        name: 'ผู้เข้าร่วม DPAC',
                        data: dpacCounts
                    }],
                    chart: {
                        type: 'bar',
                        height: 185,
                        background: 'transparent',
                        toolbar: { show: false }
                    },
                    plotOptions: {
                        bar: {
                            borderRadius: 6,
                            borderRadiusApplication: 'end',
                            columnWidth: '38%',
                            distributed: true,
                            dataLabels: {
                                position: 'top'
                            }
                        }
                    },
                    colors: ['#06b6d4', '#c084fc', '#f43f5e', '#94a3b8'],
                    dataLabels: {
                        enabled: true,
                        formatter: function(val) { return Number(val).toLocaleString() + " คน"; },
                        offsetY: -16,
                        style: {
                            fontSize: '10.5px',
                            fontFamily: 'Prompt',
                            fontWeight: '800',
                            colors: [isDark ? '#f8fafc' : '#0f172a']
                        }
                    },
                    xaxis: {
                        categories: dpacCats,
                        labels: {
                            style: {
                                colors: '#9ca3af',
                                fontFamily: 'Prompt',
                                fontSize: '10.5px',
                                fontWeight: '700'
                            }
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false }
                    },
                    yaxis: {
                        labels: {
                            style: { colors: '#9ca3af', fontFamily: 'Prompt', fontSize: '10px' },
                            formatter: function(val) { return Math.round(val); }
                        }
                    },
                    grid: {
                        borderColor: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
                        strokeDashArray: 4,
                        padding: { top: -10, bottom: -5 }
                    },
                    legend: { show: false },
                    tooltip: {
                        theme: localStorage.getItem('theme') || 'light',
                        y: {
                            formatter: function(val) {
                                var pct = dpacTotalSum > 0 ? ((val / dpacTotalSum) * 100).toFixed(1) : 0;
                                return Number(val).toLocaleString() + " ราย (" + pct + "%)";
                            }
                        }
                    }
                };
                new ApexCharts(document.querySelector("#chart-dpac"), optionsDpac).render();
            } else {
                document.querySelector("#chart-dpac").innerHTML = `
                    <div style="height: 185px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 12px 14px; background: rgba(6, 182, 212, 0.04); border: 1.5px dashed rgba(6, 182, 212, 0.25); border-radius: 12px; box-sizing: border-box;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: rgba(6, 182, 212, 0.12); display: flex; align-items: center; justify-content: center; margin-bottom: 6px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <div style="font-size: 13.5px; font-weight: 800; color: #0891b2; margin-bottom: 2px;">ยังไม่มีข้อมูลผู้เข้าร่วม DPAC</div>
                        <div style="font-size: 11px; color: var(--text-secondary); max-width: 220px; line-height: 1.35;">ยังไม่มีการส่งต่อกลุ่มเสี่ยงเข้าร่วมกิจกรรมปรับพฤติกรรมในรอบนี้</div>
                    </div>`;
            }

            // 1. Total Multi-Round Gradient Wave Area Chart (Smooth Purple/Blue Wave Peaks with Floating Milestone Numbers)
            const totTargetNum = <?= intval($metrics['total_targets']) ?>;
            const r1CompletedNum = <?= intval($r1All) ?>;
            const r2AssignedNum = <?= intval($cR2AssignedTotal) ?>;
            const r2CompletedNum = <?= intval($r2CompAll) ?>;
            const r3CompletedNum = <?= intval($r3CompAll) ?>;

            var optionsTotalPie = {
                series: [
                    {
                        name: 'งานที่มอบหมาย / เป้าหมาย',
                        data: [totTargetNum, r1CompletedNum, r2AssignedNum, r2AssignedNum, Math.max(r3CompletedNum, <?= intval($cR3AssignedTotal) ?>)]
                    },
                    {
                        name: 'ผลงานคัดกรองสำเร็จจริง',
                        data: [r1CompletedNum, r1CompletedNum, r2CompletedNum, r2CompletedNum, r3CompletedNum]
                    }
                ],
                chart: {
                    type: 'area',
                    height: 215,
                    background: 'transparent',
                    toolbar: { show: false },
                    sparkline: { enabled: false }
                },
                colors: ['#818cf8', '#8b5cf6'],
                stroke: {
                    curve: 'smooth',
                    width: [2, 2.5]
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.65,
                        opacityTo: 0.08,
                        stops: [0, 85, 100]
                    }
                },
                markers: {
                    size: [0, 3.5],
                    colors: ['#818cf8', '#8b5cf6'],
                    strokeColors: isDark ? '#1e293b' : '#ffffff',
                    strokeWidth: 2,
                    hover: { size: 5 }
                },
                annotations: {
                    points: [
                        {
                            x: 'คัดกรอง R1',
                            y: r1CompletedNum,
                            marker: { size: 4, fillColor: '#8b5cf6', strokeColor: '#ffffff', strokeWidth: 2 },
                            label: {
                                borderColor: 'rgba(139, 92, 246, 0.4)',
                                style: { color: '#ffffff', background: '#8b5cf6', fontSize: '10px', fontWeight: 800, fontFamily: 'Prompt' },
                                text: Number(r1CompletedNum).toLocaleString() + ' คน (' + '<?= $cR1Pct ?>%' + ')'
                            }
                        },
                        {
                            x: 'สำเร็จ R2',
                            y: r2CompletedNum,
                            marker: { size: 4, fillColor: '#6366f1', strokeColor: '#ffffff', strokeWidth: 2 },
                            label: {
                                borderColor: 'rgba(99, 102, 241, 0.4)',
                                style: { color: '#ffffff', background: '#6366f1', fontSize: '10px', fontWeight: 800, fontFamily: 'Prompt' },
                                text: Number(r2CompletedNum).toLocaleString() + ' คน'
                            }
                        }
                    ]
                },
                xaxis: {
                    categories: ['เป้าหมายรวม', 'คัดกรอง R1', 'มอบหมาย R2', 'สำเร็จ R2', 'สำเร็จ R3+'],
                    labels: {
                        style: {
                            colors: '#9ca3af',
                            fontSize: '10px',
                            fontFamily: 'Prompt',
                            fontWeight: '600'
                        }
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: {
                        style: { colors: '#9ca3af', fontFamily: 'Prompt', fontSize: '10px' },
                        formatter: function(val) { return Number(val).toLocaleString(); }
                    }
                },
                grid: {
                    borderColor: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
                    strokeDashArray: 4,
                    padding: { top: -10, bottom: -5 }
                },
                legend: {
                    show: true,
                    position: 'bottom',
                    offsetY: 0,
                    fontSize: '10.5px',
                    fontFamily: 'Prompt',
                    fontWeight: 600,
                    labels: { colors: '#9ca3af' },
                    markers: { width: 8, height: 8, radius: 3 }
                },
                tooltip: {
                    theme: localStorage.getItem('theme') || 'light',
                    y: {
                        formatter: function(val) {
                            return Number(val).toLocaleString() + " ราย";
                        }
                    }
                }
            };
            if (<?= intval($metrics['total_targets']) ?> > 0) {
                new ApexCharts(document.querySelector("#chart-total-pie"), optionsTotalPie).render();
            } else {
                document.querySelector("#chart-total-pie").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 50px;">ไม่มีข้อมูล</div>';
            }

            // Cockpit multi-round pipeline is rendered via native performance tracks widget.
        </script>

        <!-- Map Script Initialization -->
        <script>
            // ============== MAP DATA ==============
            var allMapData = <?= json_encode($allMapTargets) ?>;
            var hcNames = <?= json_encode($hc_names) ?>;

            // Classify risk for each target
            allMapData.forEach(function(t) {
                if (t.sys_bp1 !== null) {
                    // Has screening results
                    var sbp = parseInt(t.sys_bp1) || 0;
                    var dbp = parseInt(t.dia_bp1) || 0;
                    var dtx = parseFloat(t.dtx_value) || 0;
                    var cv = parseFloat(t.cv_risk_score) || 0;

                    if (sbp >= 140 || dbp >= 90 || dtx >= 126 || cv >= 10) {
                        t.risk = 'high';
                    } else if ((sbp >= 120 && sbp < 140) || (dbp >= 80 && dbp < 90) || (dtx >= 100 && dtx < 126)) {
                        t.risk = 'moderate';
                    } else {
                        t.risk = 'normal';
                    }
                } else {
                    t.risk = 'normal'; // No screening = unscreened, shown as normal/green
                }
            });

            var riskColors = {
                high: '#ef4444',
                moderate: '#f59e0b',
                normal: '#22c55e'
            };
            var riskLabels = {
                high: '🔴 เสี่ยงสูง',
                moderate: '🟡 เสี่ยงปานกลาง',
                normal: '🟢 ปกติ'
            };

            // ============== MAP INIT ==============
            var streetLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
                maxZoom: 20
            });
            var satelliteLayer = L.tileLayer('https://mt1.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
                attribution: '&copy; Google Maps',
                maxZoom: 20
            });
            var baseMaps = {
                "แผนที่ถนน (Street)": streetLayer,
                "แผนที่ดาวเทียม (Satellite)": satelliteLayer
            };

            var map = L.map('map', {
                center: [15.4294, 104.9922],
                zoom: 12,
                layers: [streetLayer]
            });
            L.control.layers(baseMaps).addTo(map);

            // ============== MARKERS & LAYERS ==============
            var markers = [];
            var heatLayer = null;
            var currentRiskFilter = 'all';
            var currentHosFilter = 'all';

            function classifyPopupGroupHTML(groupData) {
                // Determine group risk (highest risk in the house)
                var hasHigh = groupData.some(function(t) {
                    return t.risk === 'high';
                });
                var hasMod = groupData.some(function(t) {
                    return t.risk === 'moderate';
                });
                var groupRisk = hasHigh ? 'high' : (hasMod ? 'moderate' : 'normal');
                var groupColor = riskColors[groupRisk];

                var firstT = groupData[0];
                var villageName = firstT.house_no ? 'บ้านเลขที่ ' + firstT.house_no : '';

                var html = '<div style="color: black; font-size: 13px; min-width: 250px; max-height: 300px; overflow-y: auto;">';
                html += '<div style="position: sticky; top: 0; background: white; padding-bottom: 5px; border-bottom: 1px solid #ccc; margin-bottom: 8px;">';
                html += '<strong>' + villageName + ' หมู่ที่ ' + firstT.moo + '</strong><br>';
                html += '<span style="color: #888; font-size: 11px;">รพ.สต.: ' + (hcNames[firstT.hoscode] || firstT.hoscode) + '</span><br>';
                html += '<span>สมาชิกเป้าหมาย: <strong style="color: ' + groupColor + '">' + groupData.length + ' คน</strong></span>';
                html += '</div>';

                groupData.forEach(function(t, index) {
                    var riskLabel = riskLabels[t.risk];
                    html += '<div style="margin-bottom: 10px; padding: 5px; background: #f9f9f9; border-radius: 4px;">';
                    html += '<strong>' + (t.first_name || '') + ' ' + (t.last_name || '') + '</strong><br>';
                    html += '<span style="font-weight: bold; font-size: 12px;">สถานะ: ' + riskLabel + '</span><br>';

                    if (t.sys_bp1 !== null) {
                        var bpColor = (parseInt(t.sys_bp1) >= 140 || parseInt(t.dia_bp1) >= 90) ? 'red' : 'green';
                        html += 'ความดัน: <span style="color: ' + bpColor + '; font-weight: bold;">' + t.sys_bp1 + '/' + t.dia_bp1 + '</span> mmHg<br>';
                        var dtxColor = (parseFloat(t.dtx_value) >= 126) ? 'red' : 'green';
                        html += 'น้ำตาล: <span style="color: ' + dtxColor + '; font-weight: bold;">' + (t.dtx_value || 'N/A') + '</span> mg/dL<br>';
                        html += 'CV Risk: <span style="font-weight: bold;">' + (t.cv_risk_score || 0) + '%</span>';
                    } else {
                        html += '<span style="color: #888;">ยังไม่ได้รับการคัดกรอง</span><br>';
                        html += 'ประวัติ HDC: ' + (t.health_status_origin || '-');
                    }
                    html += '</div>';
                });

                html += '</div>';
                return html;
            }

            function getSafeLatLng(lat, lng) {
                var latVal = parseFloat(lat);
                var lngVal = parseFloat(lng);
                if (isNaN(latVal) || isNaN(lngVal)) return null;
                // If coordinates are swapped (Thailand latitude is ~15, longitude is ~104-105)
                if (latVal > 90 || (latVal > 80 && lngVal < 30)) {
                    return [lngVal, latVal];
                }
                return [latVal, lngVal];
            }

            function isMaskedDuplicate(masked, unmasked) {
                // Helper to check if name is masked (contains * or X)
                function isMasked(str) {
                    return str && (str.indexOf('*') !== -1 || str.indexOf('X') !== -1);
                }

                var maskedFirstIsMasked = isMasked(masked.first_name);
                var maskedLastIsMasked = isMasked(masked.last_name);

                var unmaskedFirstIsMasked = isMasked(unmasked.first_name);
                var unmaskedLastIsMasked = isMasked(unmasked.last_name);

                // If masked name is not masked, or unmasked name is actually masked, they are not a pair
                if (!(maskedFirstIsMasked || maskedLastIsMasked) || (unmaskedFirstIsMasked || unmaskedLastIsMasked)) {
                    return false;
                }

                // Helper to extract non-masked prefix
                function getPrefix(str) {
                    if (!str) return "";
                    var idxStar = str.indexOf('*');
                    var idxX = str.indexOf('X');
                    var idx = -1;
                    if (idxStar !== -1 && idxX !== -1) idx = Math.min(idxStar, idxX);
                    else if (idxStar !== -1) idx = idxStar;
                    else idx = idxX;
                    return idx > 0 ? str.substring(0, idx) : str.substring(0, 1);
                }

                var mFirstPrefix = getPrefix(masked.first_name);
                var mLastPrefix = getPrefix(masked.last_name);

                if (!mFirstPrefix || !mLastPrefix) return false;

                var uFirst = unmasked.first_name || "";
                var uLast = unmasked.last_name || "";

                // Both first name and last name must match prefix
                var firstMatch = uFirst.startsWith(mFirstPrefix);
                var lastMatch = uLast.startsWith(mLastPrefix);

                return firstMatch && lastMatch;
            }

            function buildMarkers(adjustView) {
                // Clear existing
                markers.forEach(function(m) {
                    map.removeLayer(m.marker);
                });
                markers = [];
                if (heatLayer) {
                    map.removeLayer(heatLayer);
                    heatLayer = null;
                }

                var heatPoints = [];
                var bounds = [];

                // Group data by coordinates
                var groupedData = {};

                allMapData.forEach(function(t) {
                    if (!t.latitude || !t.longitude) return;

                    // Apply filters
                    var passRisk = (currentRiskFilter === 'all' || t.risk === currentRiskFilter);
                    var passHos = (currentHosFilter === 'all' || t.hoscode === currentHosFilter);

                    if (!passRisk || !passHos) return;

                    var latLng = getSafeLatLng(t.latitude, t.longitude);
                    if (!latLng) return;
                    var latVal = latLng[0];
                    var lngVal = latLng[1];

                    var lat = latVal.toFixed(6);
                    var lng = lngVal.toFixed(6);
                    var key = lat + ',' + lng;

                    if (!groupedData[key]) {
                        groupedData[key] = [];
                    }
                    groupedData[key].push(t);
                });

                // Deduplicate masked duplicates within each coordinate group
                var visibleCount = 0;
                Object.keys(groupedData).forEach(function(key) {
                    var group = groupedData[key];
                    if (group.length > 1) {
                        var toRemove = [];
                        for (var i = 0; i < group.length; i++) {
                            for (var j = 0; j < group.length; j++) {
                                if (i === j) continue;
                                var t1 = group[i]; // potential masked duplicate
                                var t2 = group[j]; // potential unmasked real target

                                if (isMaskedDuplicate(t1, t2)) {
                                    // Merge screening results if t1 has results but t2 doesn't
                                    if (t1.sys_bp1 !== null && t2.sys_bp1 === null) {
                                        t2.sys_bp1 = t1.sys_bp1;
                                        t2.dia_bp1 = t1.dia_bp1;
                                        t2.dtx_value = t1.dtx_value;
                                        t2.cv_risk_score = t1.cv_risk_score;
                                        t2.bmi = t1.bmi;
                                        t2.risk = t1.risk;
                                    }
                                    toRemove.push(t1);
                                    break;
                                }
                            }
                        }
                        if (toRemove.length > 0) {
                            groupedData[key] = group.filter(function(t) {
                                return toRemove.indexOf(t) === -1;
                            });
                        }
                    }

                    // Update visibleCount
                    visibleCount += groupedData[key].length;
                });

                // Create markers and populate bounds/heatPoints for each group
                Object.keys(groupedData).forEach(function(key) {
                    var group = groupedData[key];
                    if (group.length === 0) return;

                    var parts = key.split(',');
                    var lat = parseFloat(parts[0]);
                    var lng = parseFloat(parts[1]);

                    // Determine highest risk in group for marker color
                    var hasHigh = group.some(function(t) {
                        return t.risk === 'high';
                    });
                    var hasMod = group.some(function(t) {
                        return t.risk === 'moderate';
                    });
                    var groupRisk = hasHigh ? 'high' : (hasMod ? 'moderate' : 'normal');

                    var color = riskColors[groupRisk];
                    var radius = groupRisk === 'high' ? 7 : (groupRisk === 'moderate' ? 5 : 4);
                    // Make it slightly larger if multiple people
                    if (group.length > 1) {
                        radius += 1.5;
                    }
                    var opacity = groupRisk === 'high' ? 0.9 : 0.7;

                    var marker = L.circleMarker([lat, lng], {
                        radius: radius,
                        fillColor: color,
                        color: group.length > 1 ? '#000' : '#fff', // Black border if multiple people
                        weight: group.length > 1 ? 2 : 1,
                        opacity: 1,
                        fillOpacity: opacity
                    }).addTo(map).bindPopup(classifyPopupGroupHTML(group));

                    markers.push({
                        marker: marker,
                        data: group
                    });

                    // Populating bounds and heatPoints only for coordinates within Tan Sum boundary
                    if (lat >= 15.20 && lat <= 15.60 && lng >= 104.70 && lng <= 105.40) {
                        var groupIntensity = groupRisk === 'high' ? 1.0 : (groupRisk === 'moderate' ? 0.6 : 0.3);
                        heatPoints.push([lat, lng, groupIntensity]);
                        bounds.push([lat, lng]);
                    }
                });

                // Update counter
                document.getElementById('visible-count').textContent = visibleCount;

                // Add heatmap layer
                if (heatPoints.length > 0) {
                    heatLayer = L.heatLayer(heatPoints, {
                        radius: 25,
                        blur: 15,
                        maxZoom: 15,
                        gradient: {
                            0.2: '#22c55e',
                            0.4: '#a3e635',
                            0.6: '#f59e0b',
                            0.8: '#f97316',
                            1.0: '#ef4444'
                        }
                    }).addTo(map);
                }

                // Adjust map view to fit all filtered points
                if (adjustView) {
                    if (bounds.length > 0) {
                        // Calculate centroid (average coordinates) of all visible points
                        var latSum = 0;
                        var lngSum = 0;
                        bounds.forEach(function(c) {
                            latSum += c[0];
                            lngSum += c[1];
                        });
                        var centerLat = latSum / bounds.length;
                        var centerLng = lngSum / bounds.length;

                        // Calculate optimal zoom level based on boundary box
                        var latLngBounds = L.latLngBounds(bounds);
                        var targetZoom = map.getBoundsZoom(latLngBounds);

                        // Cap the zoom levels to keep it looking professional
                        if (!isFinite(targetZoom) || targetZoom > 15) {
                            targetZoom = 15;
                        } else if (targetZoom < 11) {
                            targetZoom = 11;
                        }

                        // Smoothly fly to the centroid of all filtered markers
                        map.flyTo([centerLat, centerLng], targetZoom, {
                            animate: true,
                            duration: 1.5
                        });
                    } else {
                        // Default fallback to Tal Sum center
                        map.flyTo([15.4294, 104.9922], 12, {
                            animate: true,
                            duration: 1.5
                        });
                    }
                }
            }

            // ============== FILTER FUNCTIONS ==============
            function toggleRiskFilter(risk) {
                currentRiskFilter = risk;

                // Update button styles
                document.querySelectorAll('[id^="btn-risk-"]').forEach(function(btn) {
                    btn.classList.remove('active');
                    btn.style.background = 'transparent';
                    btn.style.color = btn.getAttribute('data-color') || 'var(--text-secondary)';
                });

                var activeBtn = document.getElementById('btn-risk-' + risk);
                activeBtn.classList.add('active');

                if (risk === 'all') {
                    activeBtn.style.background = 'var(--color-primary)';
                    activeBtn.style.color = 'white';
                } else if (risk === 'high') {
                    activeBtn.style.background = 'var(--color-red)';
                    activeBtn.style.color = 'white';
                } else if (risk === 'moderate') {
                    activeBtn.style.background = 'var(--color-yellow)';
                    activeBtn.style.color = '#000';
                } else if (risk === 'normal') {
                    activeBtn.style.background = 'var(--color-green)';
                    activeBtn.style.color = 'white';
                }

                buildMarkers(true);
            }

            function toggleHosFilter(hoscode) {
                currentHosFilter = hoscode;

                // Update button styles
                document.querySelectorAll('[id^="btn-hos-"]').forEach(function(btn) {
                    btn.classList.remove('active');
                    btn.style.background = 'transparent';
                    btn.style.color = 'var(--color-accent)';
                    btn.style.borderColor = 'var(--border-color)';
                });

                var activeBtn = document.getElementById('btn-hos-' + hoscode);
                activeBtn.classList.add('active');
                activeBtn.style.background = 'var(--color-accent)';
                activeBtn.style.color = 'var(--bg-card)';
                activeBtn.style.borderColor = 'var(--color-accent)';

                buildMarkers(true);
            }

            // ============== COORDINATE EDITING ==============
            var editMode = false;
            var editMarker = null;
            var pendingCoord = null;

            function toggleEditMode() {
                editMode = !editMode;
                var controls = document.getElementById('edit-controls');
                var btn = document.getElementById('btn-edit-coords');

                if (editMode) {
                    controls.style.display = 'block';
                    btn.style.background = 'linear-gradient(135deg, #dc2626, #b91c1c)';
                    btn.textContent = '🔒 ปิดโหมดแก้ไข';
                    document.getElementById('edit-status').innerHTML = '💡 เลือกรายชื่อเป้าหมาย จากนั้นคลิกบนแผนที่เพื่อปักพิกัดใหม่';
                    map.getContainer().style.cursor = 'crosshair';
                } else {
                    cancelEditMode();
                }
            }

            function cancelEditMode() {
                editMode = false;
                var controls = document.getElementById('edit-controls');
                var btn = document.getElementById('btn-edit-coords');

                controls.style.display = 'none';
                btn.style.background = 'linear-gradient(135deg, #8b5cf6, #6d28d9)';
                btn.textContent = '📍 แก้ไขพิกัดบ้าน';
                document.getElementById('edit-target-select').value = '';
                document.getElementById('btn-save-coord').style.display = 'none';
                document.getElementById('edit-status').innerHTML = '';
                map.getContainer().style.cursor = '';

                if (editMarker) {
                    map.removeLayer(editMarker);
                    editMarker = null;
                }
                pendingCoord = null;
            }

            function onTargetSelected() {
                var sel = document.getElementById('edit-target-select');
                var opt = sel.options[sel.selectedIndex];

                if (!sel.value) return;

                var lat = parseFloat(opt.getAttribute('data-lat'));
                var lng = parseFloat(opt.getAttribute('data-lng'));

                if (lat && lng) {
                    // Safety check: if coordinates are swapped
                    var latLng = getSafeLatLng(lat, lng);
                    if (latLng) {
                        lat = latLng[0];
                        lng = latLng[1];
                    }

                    // Jump to existing location smoothly
                    map.flyTo([lat, lng], 16, {
                        animate: true,
                        duration: 1.5
                    });
                    document.getElementById('edit-status').innerHTML = '📌 พิกัดปัจจุบัน: ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '<br>💡 คลิกบนแผนที่เพื่อเปลี่ยนตำแหน่งใหม่';
                } else {
                    document.getElementById('edit-status').innerHTML = '❌ ยังไม่มีพิกัด - คลิกบนแผนที่เพื่อกำหนดตำแหน่ง';
                }
            }

            map.on('click', function(e) {
                if (!editMode) return;

                var cid = document.getElementById('edit-target-select').value;
                if (!cid) {
                    document.getElementById('edit-status').innerHTML = '<span style="color: var(--color-red);">⚠️ กรุณาเลือกรายชื่อเป้าหมายก่อน</span>';
                    return;
                }

                var lat = e.latlng.lat;
                var lng = e.latlng.lng;
                pendingCoord = {
                    cid: cid,
                    latitude: lat,
                    longitude: lng
                };

                // Show/update preview marker
                if (editMarker) {
                    map.removeLayer(editMarker);
                }

                var pulseIcon = L.divIcon({
                    className: '',
                    html: '<div style="width: 20px; height: 20px; background: #8b5cf6; border: 3px solid white; border-radius: 50%; box-shadow: 0 0 10px rgba(139,92,246,0.5); animation: pulse 1s infinite;"></div>',
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });

                editMarker = L.marker([lat, lng], {
                    icon: pulseIcon,
                    draggable: true
                }).addTo(map);
                editMarker.bindPopup('<div style="color: black; font-weight: bold;">📍 ตำแหน่งใหม่<br>' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</div>').openPopup();

                editMarker.on('dragend', function(e) {
                    var pos = e.target.getLatLng();
                    pendingCoord.latitude = pos.lat;
                    pendingCoord.longitude = pos.lng;
                    document.getElementById('edit-status').innerHTML = '📌 พิกัดใหม่: <strong>' + pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6) + '</strong> (ลากปรับตำแหน่งได้)';
                    editMarker.setPopupContent('<div style="color: black; font-weight: bold;">📍 ตำแหน่งใหม่<br>' + pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6) + '</div>');
                });

                document.getElementById('btn-save-coord').style.display = 'inline-block';
                document.getElementById('edit-status').innerHTML = '📌 พิกัดใหม่: <strong>' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</strong> (ลากปรับตำแหน่งได้)';
            });

            function saveNewCoordinate() {
                if (!pendingCoord) return;

                var btn = document.getElementById('btn-save-coord');
                btn.textContent = '⏳ กำลังบันทึก...';
                btn.disabled = true;

                fetch('../api/update_coordinates.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(pendingCoord)
                    })
                    .then(function(res) {
                        return res.json();
                    })
                    .then(function(data) {
                        if (data.success) {
                            document.getElementById('edit-status').innerHTML = '<span style="color: var(--color-green); font-weight: bold;">✅ ' + data.message + '</span>';

                            // Update local data
                            var found = allMapData.find(function(t) {
                                return t.cid === pendingCoord.cid;
                            });
                            if (found) {
                                found.latitude = pendingCoord.latitude.toString();
                                found.longitude = pendingCoord.longitude.toString();
                            } else {
                                // Target was without coordinates before, add it
                                allMapData.push({
                                    cid: pendingCoord.cid,
                                    latitude: pendingCoord.latitude.toString(),
                                    longitude: pendingCoord.longitude.toString(),
                                    risk: 'normal',
                                    house_no: data.data ? data.data.name : '',
                                    moo: '',
                                    hoscode: ''
                                });
                            }

                            // Update the select option
                            var opt = document.querySelector('#edit-target-select option[value="' + pendingCoord.cid + '"]');
                            if (opt) {
                                opt.setAttribute('data-lat', pendingCoord.latitude);
                                opt.setAttribute('data-lng', pendingCoord.longitude);
                                opt.textContent = opt.textContent.replace('❌', '✅');
                            }

                            buildMarkers(false);

                            if (editMarker) {
                                map.removeLayer(editMarker);
                                editMarker = null;
                            }
                            pendingCoord = null;
                            btn.style.display = 'none';
                        } else {
                            document.getElementById('edit-status').innerHTML = '<span style="color: var(--color-red);">❌ ' + data.message + '</span>';
                        }

                        btn.textContent = '💾 บันทึกพิกัด';
                        btn.disabled = false;
                    })
                    .catch(function(err) {
                        document.getElementById('edit-status').innerHTML = '<span style="color: var(--color-red);">❌ เกิดข้อผิดพลาด: ' + err.message + '</span>';
                        btn.textContent = '💾 บันทึกพิกัด';
                        btn.disabled = false;
                    });
            }

            // ============== INIT ==============
            buildMarkers(true);
        </script>

        <style>
            @keyframes pulse {
                0% {
                    transform: scale(1);
                    opacity: 1;
                }

                50% {
                    transform: scale(1.3);
                    opacity: 0.7;
                }

                100% {
                    transform: scale(1);
                    opacity: 1;
                }
            }
        </style>

        <!-- Details Modal Placeholder -->
        <div id="details-modal" onclick="if(event.target === this) closeDetailsModal()"
            style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(13, 44, 84, 0.4); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center;">
            <div class="card-dark"
                style="position: relative; width: 90%; max-width: 500px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.1); border: 1px solid rgba(0, 0, 0, 0.08); border-radius: 24px; margin-bottom: 0;">
                <button onclick="closeDetailsModal()"
                    style="position: absolute; top: 24px; right: 24px; background: none; border: none; color: var(--text-muted); cursor: pointer; transition: color var(--transition-speed); padding: 4px; display: inline-flex; align-items: center; justify-content: center;"
                    onmouseover="this.style.color='var(--color-red)'"
                    onmouseout="this.style.color='var(--text-muted)'"
                    title="ปิด">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <h3 id="modal-title"
                    style="color: var(--color-accent); border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; padding-right: 30px;">
                    รายละเอียด</h3>
                <div id="modal-body-content" style="margin-bottom: 0;"></div>
            </div>
        </div>

        <!-- Metric Card Modal JS -->
        <script>
            var targetsDetail = <?= json_encode($targetsDetail) ?>;
            var groupDetail = <?= json_encode($groupDetail) ?>;
            var screenedDetail = <?= json_encode($screenedDetail) ?>;
            var skippedDetail = <?= json_encode($skippedDetail) ?>;
            var pendingDetail = <?= json_encode($pendingDetail) ?>;
            var rewardsDetail = <?= json_encode($rewardsDetail) ?>;

            var groupLabels = {
                'HIGH_RISK': '🔴 เสี่ยงสูง (High Risk)',
                'BOTH': '🟠 เสี่ยงทั้ง HT+DM',
                'DM_ONLY': '🟡 เสี่ยงเบาหวาน (DM)',
                'HT_ONLY': '🟡 เสี่ยงความดัน (HT)',
                'NORMAL': '🟢 กลุ่มปกติ (Normal)'
            };
            var groupColors = {
                'HIGH_RISK': 'var(--color-red)',
                'BOTH': '#f97316',
                'DM_ONLY': 'var(--color-yellow)',
                'HT_ONLY': 'var(--color-yellow)',
                'NORMAL': 'var(--color-green)'
            };

            function showCardModal(type) {
                var title = '';
                var html = '';

                var cardTargetFilters = {
                    'targets_dm': {
                        origin: 'DM_ONLY',
                        label: 'เฉพาะเสี่ยงเบาหวาน (DM)',
                        totalVal: <?= intval($metrics['group_dm']) ?>,
                        color: '#f97316'
                    },
                    'targets_ht': {
                        origin: 'HT_ONLY',
                        label: 'เฉพาะเสี่ยงความดัน (HT)',
                        totalVal: <?= intval($metrics['group_ht']) ?>,
                        color: '#06b6d4'
                    },
                    'targets_both': {
                        origin: 'BOTH',
                        label: 'เสี่ยงทั้งคู่ (DM+HT)',
                        totalVal: <?= intval($metrics['group_both']) ?>,
                        color: 'var(--color-red)'
                    },
                    'targets_suspected': {
                        origin: 'SUSPECT',
                        label: 'สงสัยป่วยสะสม (Suspect)',
                        totalVal: <?= intval($metrics['group_suspected']) ?>,
                        color: 'var(--color-yellow)'
                    }
                };

                if (type.indexOf('targets_') === 0) {
                    var config = cardTargetFilters[type];
                    var origin = config.origin;
                    title = '📊 กลุ่มเป้าหมาย ' + config.label;

                    html = '<div style="margin-bottom: 16px; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px; border-left: 4px solid ' + config.color + ';">';
                    html += '<span style="color: var(--text-secondary); font-size: 14px;">จำนวนเป้าหมายทั้งหมดในกลุ่มนี้</span>';
                    html += '<div style="font-size: 24px; font-weight: bold; color: ' + config.color + '; margin-top: 4px;">' + config.totalVal.toLocaleString() + ' ราย</div>';
                    html += '</div>';

                    // Also show per-village/hoscode breakdown specifically for this group
                    html += '<h4 style="margin-top: 20px; color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">แยกตามพื้นที่</h4>';
                    html += '<div style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05);">';
                    html += '<table class="admin-table"><thead><tr><th>พื้นที่</th><th style="text-align: right;">จำนวน (ราย)</th></tr></thead><tbody>';

                    var filteredDetails = targetsDetail.filter(function(row) {
                        return row.health_status_origin === origin;
                    });

                    var totalCount = 0;
                    <?php if (!$admin_hoscode): ?>
                        if (filteredDetails.length === 0) {
                            html += '<tr><td colspan="2" style="text-align: center;">ไม่มีข้อมูล</td></tr>';
                        } else {
                            filteredDetails.forEach(function(row) {
                                totalCount += Number(row.count);
                                html += '<tr><td>' + (hcNamesChart[row.hoscode] || row.hoscode) + '</td><td style="text-align: right; font-weight: bold;">' + Number(row.count).toLocaleString() + ' ราย</td></tr>';
                            });
                        }
                    <?php else: ?>
                        if (filteredDetails.length === 0) {
                            html += '<tr><td colspan="2" style="text-align: center;">ไม่มีข้อมูล</td></tr>';
                        } else {
                            filteredDetails.forEach(function(row) {
                                totalCount += Number(row.count);
                                html += '<tr><td>' + (row.village_name || ('หมู่ที่ ' + row.moo)) + '</td><td style="text-align: right; font-weight: bold;">' + Number(row.count).toLocaleString() + ' ราย</td></tr>';
                            });
                        }
                    <?php endif; ?>
                    html += '<tr style="background-color: var(--bg-darker); font-weight: bold;"><td>รวมทั้งหมด</td><td style="text-align: right;">' + totalCount.toLocaleString() + ' ราย</td></tr>';
                    html += '</tbody></table></div>';
                } else if (type === 'targets') {
                    title = '📊 กลุ่มเป้าหมายการคัดกรอง แยกตามสถานะ HDC';
                    html = '<div style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05); margin-bottom: 20px;">';
                    html += '<table class="admin-table"><thead><tr><th>กลุ่มเป้าหมาย</th><th style="text-align: right;">จำนวน (ราย)</th></tr></thead><tbody>';
                    if (groupDetail.length === 0) {
                        html += '<tr><td colspan="2" style="text-align: center;">ไม่มีข้อมูล</td></tr>';
                    } else {
                        groupDetail.forEach(function(row) {
                            var label = groupLabels[row.health_status_origin] || row.health_status_origin;
                            var color = groupColors[row.health_status_origin] || 'var(--text-primary)';
                            html += '<tr><td style="color: ' + color + '; font-weight: bold;">' + label + '</td><td style="text-align: right; font-weight: bold; color: ' + color + ';">' + Number(row.count).toLocaleString() + ' ราย</td></tr>';
                        });
                    }
                    html += '<tr style="background-color: var(--bg-darker); font-weight: bold;"><td>รวมทั้งหมด</td><td style="text-align: right;">' + Number(<?= $metrics['total_targets'] ?>).toLocaleString() + ' ราย</td></tr>';
                    html += '</tbody></table></div>';

                    // Also show per-village/hoscode breakdown
                    html += '<h4 style="margin-top: 20px; color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">แยกตามพื้นที่</h4>';
                    html += '<div style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05);">';
                    html += '<table class="admin-table"><thead><tr><th>พื้นที่</th><th style="text-align: right;">จำนวน (ราย)</th></tr></thead><tbody>';

                    var areaCounts = {};
                    targetsDetail.forEach(function(row) {
                        var areaKey = <?php echo !$admin_hoscode ? 'row.hoscode' : "row.hoscode + '_' + row.moo"; ?>;
                        if (!areaCounts[areaKey]) {
                            areaCounts[areaKey] = {
                                hoscode: row.hoscode,
                                moo: row.moo || '',
                                village_name: row.village_name || '',
                                count: 0
                            };
                        }
                        areaCounts[areaKey].count += Number(row.count);
                    });

                    var areaList = Object.values(areaCounts);
                    <?php if ($admin_hoscode): ?>
                        areaList.sort(function(a, b) {
                            return Number(a.moo) - Number(b.moo);
                        });
                    <?php endif; ?>

                    if (areaList.length === 0) {
                        html += '<tr><td colspan="2" style="text-align: center;">ไม่มีข้อมูล</td></tr>';
                    } else {
                        areaList.forEach(function(row) {
                            <?php if (!$admin_hoscode): ?>
                                html += '<tr><td>' + (hcNamesChart[row.hoscode] || row.hoscode) + '</td><td style="text-align: right; font-weight: bold;">' + Number(row.count).toLocaleString() + ' ราย</td></tr>';
                            <?php else: ?>
                                html += '<tr><td>' + (row.village_name || ('หมู่ที่ ' + row.moo)) + '</td><td style="text-align: right; font-weight: bold;">' + Number(row.count).toLocaleString() + ' ราย</td></tr>';
                            <?php endif; ?>
                        });
                    }
                    html += '</tbody></table></div>';
                } else if (type === 'screened') {
                    title = '🟢 ผลการคัดกรองเสร็จสิ้นแยกกลุ่มเสี่ยง';
                    var high = Number(screenedDetail.high_risk || 0);
                    var risk = Number(screenedDetail.risk || 0);
                    var normal = Number(screenedDetail.normal || 0);
                    var total = high + risk + normal;

                    html = '<div style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05);">';
                    html += '<table class="admin-table"><tbody>' +
                        '<tr><td>🔴 กลุ่มเสี่ยงสูง (High Risk)</td><td style="text-align: right; font-weight: bold; color: var(--color-red);">' + high.toLocaleString() + ' ราย (' + (total > 0 ? Math.round(high / total * 100) : 0) + '%)</td></tr>' +
                        '<tr><td>🟡 กลุ่มเสี่ยง (Moderate Risk)</td><td style="text-align: right; font-weight: bold; color: var(--color-yellow);">' + risk.toLocaleString() + ' ราย (' + (total > 0 ? Math.round(risk / total * 100) : 0) + '%)</td></tr>' +
                        '<tr><td>🟢 กลุ่มปกติ (Normal)</td><td style="text-align: right; font-weight: bold; color: var(--color-green);">' + normal.toLocaleString() + ' ราย (' + (total > 0 ? Math.round(normal / total * 100) : 0) + '%)</td></tr>' +
                        '<tr style="font-weight: bold; background-color: var(--bg-darker);"><td>รวมคัดกรองเสร็จสิ้น</td><td style="text-align: right;">' + total.toLocaleString() + ' ราย</td></tr>' +
                        '</tbody></table></div>';
                } else if (type === 'skipped') {
                    title = '⚠️ สาเหตุที่กดข้าม / เลื่อนตรวจสะสม';
                    html = '<div style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05);">';
                    html += '<table class="admin-table"><thead><tr><th>เหตุผล</th><th style="text-align: right;">จำนวนเคส (ราย)</th></tr></thead><tbody>';
                    if (skippedDetail.length === 0) {
                        html += '<tr><td colspan="2" style="text-align: center;">ไม่มีเคสถูกข้าม</td></tr>';
                    } else {
                        skippedDetail.forEach(function(row) {
                            html += '<tr><td>' + (row.skipped_reason || 'ไม่อยู่บ้าน/ไม่มีผู้ให้ประวัติ') + '</td><td style="text-align: right; font-weight: bold;">' + Number(row.count).toLocaleString() + ' เคส</td></tr>';
                        });
                    }
                    html += '</tbody></table></div>';
                } else if (type === 'pending') {
                    title = '⏳ รายละเอียดงานรอดำเนินการ แยกตามพื้นที่';
                    html = '<div style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05);">';
                    html += '<table class="admin-table"><thead><tr><th>พื้นที่</th><th style="text-align: right;">รอดำเนินการ (ราย)</th></tr></thead><tbody>';
                    var totalPending = 0;
                    if (pendingDetail.length === 0) {
                        html += '<tr><td colspan="2" style="text-align: center;">ไม่มีงานรอดำเนินการค้างในระบบ</td></tr>';
                    } else {
                        pendingDetail.forEach(function(row) {
                            totalPending += Number(row.count);
                            <?php if (!$admin_hoscode): ?>
                                html += '<tr><td>' + (hcNamesChart[row.hoscode] || row.hoscode) + '</td><td style="text-align: right; font-weight: bold; color: var(--color-primary);">' + Number(row.count).toLocaleString() + ' ราย</td></tr>';
                            <?php else: ?>
                                html += '<tr><td>' + (row.village_name || ('หมู่ที่ ' + row.moo)) + '</td><td style="text-align: right; font-weight: bold; color: var(--color-primary);">' + Number(row.count).toLocaleString() + ' ราย</td></tr>';
                            <?php endif; ?>
                        });
                    }
                    html += '<tr style="background-color: var(--bg-darker); font-weight: bold;"><td>รวมทั้งหมด</td><td style="text-align: right; color: var(--color-primary);">' + totalPending.toLocaleString() + ' ราย</td></tr>';
                    html += '</tbody></table></div>';
                } else if (type === 'rewards') {
                    title = '🏆 กระดานคะแนน อสม. ยอดเยี่ยม (Top 10)';
                    html = '<div style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05);">';
                    html += '<table class="admin-table"><thead><tr><th>อสม. ผู้ปฏิบัติงาน</th><th style="text-align: right;">คะแนนสะสม (แต้ม)</th></tr></thead><tbody>';
                    if (rewardsDetail.length === 0) {
                        html += '<tr><td colspan="2" style="text-align: center;">ยังไม่มีการบันทึกผลงานสะสม</td></tr>';
                    } else {
                        rewardsDetail.forEach(function(row) {
                            html += '<tr><td style="font-weight: bold; color: var(--text-primary);">' + row.vhv_name + '</td><td style="text-align: right; font-weight: bold; color: var(--color-green);">' + Number(row.total_points).toLocaleString() + ' แต้ม</td></tr>';
                        });
                    }
                    html += '</tbody></table></div>';
                } else if (type === 'rescreen_r2') {
                    title = '🔄 สถิติผลงานการคัดกรองติดตามซ้ำ (รอบที่ 2)';
                    var totalR2 = <?= intval($metrics['r2_completed'] ?? 0) ?>;
                    var totalR1 = <?= intval($metrics['r1_completed'] ?? 1) ?>;
                    var pctR2 = totalR1 > 0 ? ((totalR2 / totalR1) * 100).toFixed(1) : 0;

                    html = '<div style="margin-bottom: 16px; padding: 14px; background: rgba(59, 130, 246, 0.08); border-radius: 8px; border-left: 4px solid #3b82f6;">';
                    html += '<div style="display: flex; justify-content: space-between; align-items: center;">';
                    html += '<div><span style="color: var(--text-secondary); font-size: 13px;">คัดกรองติดตามซ้ำรอบ 2 ทั้งหมด</span><div style="font-size: 22px; font-weight: bold; color: #3b82f6; margin-top: 4px;">' + totalR2.toLocaleString() + ' ราย</div></div>';
                    html += '<div style="text-align: right;"><span style="color: var(--text-secondary); font-size: 13px;">สัดส่วนเทียบรอบแรก</span><div style="font-size: 22px; font-weight: bold; color: #3b82f6; margin-top: 4px;">' + pctR2 + '%</div></div>';
                    html += '</div></div>';

                    html += '<h4 style="margin-top: 20px; color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">สถิติจำแนกรายพื้นที่</h4>';
                    html += '<div style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05);">';
                    html += '<table class="admin-table"><thead><tr><th>พื้นที่</th><th style="text-align: right;">รอบ 1</th><th style="text-align: right;">รอบ 2 (ติดตามซ้ำ)</th><th style="text-align: right;">ร้อยละ</th></tr></thead><tbody>';

                    if (!rescreenRaw || rescreenRaw.length === 0) {
                        html += '<tr><td colspan="4" style="text-align: center;">ไม่มีข้อมูล</td></tr>';
                    } else {
                        var sumR1 = 0;
                        var sumR2 = 0;
                        rescreenRaw.forEach(function(row) {
                            var r1 = Number(row.r1_completed) || 0;
                            var r2 = Number(row.r2_completed) || 0;
                            sumR1 += r1;
                            sumR2 += r2;
                            var areaName = isRegularAdmin ? (row.village_name || ('หมู่ที่ ' + row.moo)) : (hcNamesChart[row.hoscode] || row.hoscode);
                            var pct = r1 > 0 ? ((r2 / r1) * 100).toFixed(1) : '0.0';
                            html += '<tr><td>' + areaName + '</td><td style="text-align: right;">' + r1.toLocaleString() + '</td><td style="text-align: right; font-weight: bold; color: #3b82f6;">' + r2.toLocaleString() + ' ราย</td><td style="text-align: right; font-weight: bold; color: #3b82f6;">' + pct + '%</td></tr>';
                        });
                        var totalPct = sumR1 > 0 ? ((sumR2 / sumR1) * 100).toFixed(1) : '0.0';
                        html += '<tr style="background-color: var(--bg-darker); font-weight: bold;"><td>รวมทั้งหมด</td><td style="text-align: right;">' + sumR1.toLocaleString() + '</td><td style="text-align: right; color: #3b82f6;">' + sumR2.toLocaleString() + ' ราย</td><td style="text-align: right; color: #3b82f6;">' + totalPct + '%</td></tr>';
                    }
                    html += '</tbody></table></div>';
                }

                document.getElementById('modal-title').textContent = title;
                document.getElementById('modal-body-content').innerHTML = html;
                document.getElementById('details-modal').style.display = 'flex';
            }

            function closeDetailsModal() {
                document.getElementById('details-modal').style.display = 'none';
            }
        </script>
        <script src="../assets/js/proxy-manager.js"></script>
        <?php include_once __DIR__ . '/../config/dev_modal.php'; ?>
</body>

</html>
