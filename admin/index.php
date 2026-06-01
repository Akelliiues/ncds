<?php
// admin/index.php
session_start();

// ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';


// Fetch summary metrics
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

$hc_names = [
    '10957' => 'โรงพยาบาลตาลสุม',
    '03751' => 'รพ.สต.ดอนพันชาด',
    '03752' => 'รพ.สต.บ้านสำโรง',
    '03753' => 'รพ.สต.บ้านจิกเทิง',
    '03754' => 'รพ.สต.บ้านหนองกุงใหญ่',
    '03755' => 'รพ.สต.นาคาย',
    '03756' => 'รพ.สต.คำหนามแท่ง',
    '03757' => 'รพ.สต.คำหว้า'
];

$admin_title = $admin_hoscode ? ($hc_names[$admin_hoscode] ?? 'รพ.สต.') : 'แอดมินหลัก (ทุก รพ.สต.)';

if ($admin_hoscode) {
    $hoscodes = [$admin_hoscode];
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));

    $total_targets = $pdo->prepare("SELECT COUNT(*) FROM target_population WHERE hoscode IN ($inPlaceholders) AND (need_screen_dm = 1 OR need_screen_ht = 1)");
    $total_targets->execute($hoscodes);
    $total_targets_val = $total_targets->fetchColumn();

    // Query target groups by health_status_origin
    $groupStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN health_status_origin = 'DM_ONLY' AND (need_screen_dm = 1 OR need_screen_ht = 1) THEN 1 ELSE 0 END) as group_dm,
            SUM(CASE WHEN health_status_origin = 'HT_ONLY' AND (need_screen_dm = 1 OR need_screen_ht = 1) THEN 1 ELSE 0 END) as group_ht,
            SUM(CASE WHEN health_status_origin IN ('BOTH','HIGH_RISK') AND (need_screen_dm = 1 OR need_screen_ht = 1) THEN 1 ELSE 0 END) as group_both,
            SUM(CASE WHEN health_status_origin IN ('HIGH_RISK','DM_ONLY','HT_ONLY','BOTH') AND (need_screen_dm = 1 OR need_screen_ht = 1) THEN 1 ELSE 0 END) as group_risk,
            SUM(CASE WHEN health_status_origin = 'NORMAL' THEN 1 ELSE 0 END) as group_normal,
            SUM(CASE WHEN need_screen_dm = 0 AND need_screen_ht = 0 THEN 1 ELSE 0 END) as group_suspected
        FROM target_population WHERE hoscode IN ($inPlaceholders)
    ");
    $groupStmt->execute($hoscodes);
    $groupCounts = $groupStmt->fetch(PDO::FETCH_ASSOC);

    // Detail breakdown per group for modal
    $groupDetailStmt = $pdo->prepare("
        SELECT health_status_origin, COUNT(*) as count 
        FROM target_population WHERE hoscode IN ($inPlaceholders)
        GROUP BY health_status_origin ORDER BY FIELD(health_status_origin, 'HIGH_RISK','BOTH','DM_ONLY','HT_ONLY','NORMAL')
    ");
    $groupDetailStmt->execute($hoscodes);
    $groupDetail = $groupDetailStmt->fetchAll(PDO::FETCH_ASSOC);

    $screened = $pdo->prepare("SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'completed' AND p.hoscode IN ($inPlaceholders)");
    $screened->execute($hoscodes);
    $screened_val = $screened->fetchColumn();

    $pending = $pdo->prepare("SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'pending' AND p.hoscode IN ($inPlaceholders)");
    $pending->execute($hoscodes);
    $pending_val = $pending->fetchColumn();

    $skipped = $pdo->prepare("SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholders)");
    $skipped->execute($hoscodes);
    $skipped_val = $skipped->fetchColumn();

    $rewards = $pdo->prepare("SELECT SUM(points_earned) FROM vhv_rewards r JOIN vhv_users v ON r.vhv_id = v.vhv_id WHERE v.hoscode IN ($inPlaceholders)");
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

    // Card 1 Detail: Targets per village (moo)
    $mooQuery = $pdo->prepare("SELECT hoscode, moo, COUNT(*) as count FROM target_population WHERE hoscode IN ($inPlaceholders) AND (need_screen_dm = 1 OR need_screen_ht = 1) GROUP BY hoscode, moo ORDER BY moo");
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
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        WHERE p.hoscode IN ($inPlaceholders) AND a.assignment_status = 'completed'
    ");
    $screenedDetailQuery->execute($hoscodes);
    $screenedDetail = $screenedDetailQuery->fetch(PDO::FETCH_ASSOC);

    // Card 3 Detail: Skipped reasons
    $skippedDetailQuery = $pdo->prepare("
        SELECT s.skipped_reason, COUNT(*) as count 
        FROM screening_results s 
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholders)
        GROUP BY s.skipped_reason
    ");
    $skippedDetailQuery->execute($hoscodes);
    $skippedDetail = $skippedDetailQuery->fetchAll(PDO::FETCH_ASSOC);

    // Card 4 Detail: Top VHVs by rewards
    $rewardsDetailQuery = $pdo->prepare("
        SELECT v.vhv_name, SUM(r.points_earned) as total_points
        FROM vhv_rewards r
        JOIN vhv_users v ON r.vhv_id = v.vhv_id
        WHERE v.hoscode IN ($inPlaceholders)
        GROUP BY v.vhv_id
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
        WHERE hoscode IN ($inPlaceholders)
        ORDER BY moo, house_no
    ");
    $editTargetsStmt->execute($hoscodes);
    $editableTargets = $editTargetsStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- NEW CHARTS DATA (ADMIN) ---
    $chartCoverageStmt = $pdo->prepare("
        SELECT p.hoscode, p.moo,
               COUNT(*) as total_targets,
               SUM(CASE WHEN a.assignment_status = 'completed' THEN 1 ELSE 0 END) as screened
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid
        WHERE p.hoscode IN ($inPlaceholders) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY p.hoscode, p.moo
        ORDER BY p.hoscode, p.moo
    ");
    $chartCoverageStmt->execute($hoscodes);
    $chartCoverageData = $chartCoverageStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($chartCoverageData as &$row) {
        $row['village_name'] = get_village_display_name_by_hoscode($row['hoscode'], $row['moo']);
    }
    unset($row);

    $chartRiskStmt = $pdo->prepare("
        SELECT p.hoscode, MAX(p.sub_district_code) as sub_district_code, p.moo,
               SUM(CASE WHEN a.assignment_status = 'completed' AND (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) THEN 1 ELSE 0 END) as high_risk,
               SUM(CASE WHEN a.assignment_status = 'completed' AND NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 1 ELSE 0 END) as moderate_risk,
               SUM(CASE WHEN a.assignment_status = 'completed' AND NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND NOT ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 1 ELSE 0 END) as normal,
               SUM(CASE WHEN a.assignment_status IS NULL OR a.assignment_status != 'completed' THEN 1 ELSE 0 END) as unscreened
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid
        LEFT JOIN screening_results s ON a.assignment_id = s.assignment_id
        WHERE p.hoscode IN ($inPlaceholders) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY p.hoscode, p.moo
        ORDER BY p.hoscode, p.moo
    ");
    $chartRiskStmt->execute($hoscodes);
    $chartRiskData = $chartRiskStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($chartRiskData as &$row) {
        $row['village_name'] = get_village_display_name_by_hoscode($row['hoscode'], $row['moo']);
    }
    unset($row);

    $chartDiseaseStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as ht_dm,
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND (s.dtx_value < 126 OR s.dtx_value IS NULL) THEN 1 ELSE 0 END) as ht_only,
            SUM(CASE WHEN (s.sys_bp1 < 140 AND s.dia_bp1 < 90) AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as dm_only,
            SUM(CASE WHEN (s.sys_bp1 < 140 AND s.dia_bp1 < 90) AND (s.dtx_value < 126 OR s.dtx_value IS NULL) 
                      AND ((s.sys_bp1 >= 120) OR (s.dia_bp1 >= 80) OR (s.dtx_value >= 100) OR (s.cv_risk_score >= 10)) THEN 1 ELSE 0 END) as risk_group,
            SUM(CASE WHEN (s.sys_bp1 < 120 AND s.dia_bp1 < 80) AND (s.dtx_value < 100 OR s.dtx_value IS NULL) AND (s.cv_risk_score < 10 OR s.cv_risk_score IS NULL) THEN 1 ELSE 0 END) as normal_group
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id AND a.assignment_status = 'completed'
        JOIN target_population p ON a.target_cid = p.cid
        WHERE p.hoscode IN ($inPlaceholders)
    ");
    $chartDiseaseStmt->execute($hoscodes);
    $chartDiseaseData = $chartDiseaseStmt->fetch(PDO::FETCH_ASSOC);

    $chartTrendStmt = $pdo->prepare("
        SELECT DATE(created_at) as screen_date, COUNT(*) as daily_count
        FROM (
            SELECT s.created_at
            FROM screening_results s
            JOIN task_assignments a ON s.assignment_id = a.assignment_id AND a.assignment_status = 'completed'
            JOIN target_population p ON a.target_cid = p.cid
            WHERE p.hoscode IN ($inPlaceholders)
              AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            UNION ALL
            SELECT f.completed_at as created_at
            FROM dpac_followups f
            JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
            JOIN target_population p ON e.cid = p.cid
            WHERE f.status = 'completed'
              AND p.hoscode IN ($inPlaceholders)
              AND f.completed_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
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
        WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholders)
        GROUP BY s.skipped_reason
    ");
    $chartSkippedStmt->execute($hoscodes);
    $chartSkippedData = $chartSkippedStmt->fetchAll(PDO::FETCH_ASSOC);

    // DPAC Enrollments Data
    $chartDpacStmt = $pdo->prepare("
        SELECT e.risk_type, COUNT(*) as count 
        FROM dpac_enrollments e
        JOIN target_population p ON e.cid = p.cid
        WHERE p.hoscode IN ($inPlaceholders)
        GROUP BY e.risk_type
    ");
    $chartDpacStmt->execute($hoscodes);
    $chartDpacData = $chartDpacStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $valid_hoscodes = ['10957', '03751', '03752', '03753', '03754', '03755', '03756', '03757'];
    $inPlaceholdersSa = implode(',', array_fill(0, count($valid_hoscodes), '?'));

    $metricsStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM target_population WHERE hoscode IN ($inPlaceholdersSa) AND (need_screen_dm = 1 OR need_screen_ht = 1)) as total_targets,
            (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'completed' AND p.hoscode IN ($inPlaceholdersSa)) as screened_count,
            (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'pending' AND p.hoscode IN ($inPlaceholdersSa)) as pending_count,
            (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholdersSa)) as skipped_count,
            (SELECT SUM(points_earned) FROM vhv_rewards r JOIN vhv_users v ON r.vhv_id = v.vhv_id WHERE v.hoscode IN ($inPlaceholdersSa)) as total_points,
            (SELECT COUNT(*) FROM vhv_users WHERE hoscode IN ($inPlaceholdersSa)) as total_vhvs
    ");
    // Duplicate array parameters for the 6 subqueries
    $metricsParams = array_merge($valid_hoscodes, $valid_hoscodes, $valid_hoscodes, $valid_hoscodes, $valid_hoscodes, $valid_hoscodes);
    $metricsStmt->execute($metricsParams);
    $metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC);

    // Query target groups by health_status_origin
    $groupStmtSa = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN health_status_origin = 'DM_ONLY' AND (need_screen_dm = 1 OR need_screen_ht = 1) THEN 1 ELSE 0 END) as group_dm,
            SUM(CASE WHEN health_status_origin = 'HT_ONLY' AND (need_screen_dm = 1 OR need_screen_ht = 1) THEN 1 ELSE 0 END) as group_ht,
            SUM(CASE WHEN health_status_origin IN ('BOTH','HIGH_RISK') AND (need_screen_dm = 1 OR need_screen_ht = 1) THEN 1 ELSE 0 END) as group_both,
            SUM(CASE WHEN health_status_origin IN ('HIGH_RISK','DM_ONLY','HT_ONLY','BOTH') AND (need_screen_dm = 1 OR need_screen_ht = 1) THEN 1 ELSE 0 END) as group_risk,
            SUM(CASE WHEN health_status_origin = 'NORMAL' THEN 1 ELSE 0 END) as group_normal,
            SUM(CASE WHEN need_screen_dm = 0 AND need_screen_ht = 0 THEN 1 ELSE 0 END) as group_suspected
        FROM target_population WHERE hoscode IN ($inPlaceholdersSa)
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
        SELECT health_status_origin, COUNT(*) as count 
        FROM target_population WHERE hoscode IN ($inPlaceholdersSa)
        GROUP BY health_status_origin ORDER BY FIELD(health_status_origin, 'HIGH_RISK','BOTH','DM_ONLY','HT_ONLY','NORMAL')
    ");
    $groupDetailStmtSa->execute($valid_hoscodes);
    $groupDetail = $groupDetailStmtSa->fetchAll(PDO::FETCH_ASSOC);

    // Card 1 Detail: Targets per hoscode for super admin
    $targetsDetailStmt = $pdo->prepare("SELECT hoscode, COUNT(*) as count FROM target_population WHERE hoscode IN ($inPlaceholdersSa) AND (need_screen_dm = 1 OR need_screen_ht = 1) GROUP BY hoscode ORDER BY hoscode");
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
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.assignment_status = 'completed' AND p.hoscode IN ($inPlaceholdersSa)
    ");
    $screenedDetailStmt->execute($valid_hoscodes);
    $screenedDetail = $screenedDetailStmt->fetch(PDO::FETCH_ASSOC);

    // Card 3 Detail: Skipped reasons
    $skippedDetailStmt = $pdo->prepare("
        SELECT s.skipped_reason, COUNT(*) as count 
        FROM screening_results s 
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholdersSa)
        GROUP BY s.skipped_reason
    ");
    $skippedDetailStmt->execute($valid_hoscodes);
    $skippedDetail = $skippedDetailStmt->fetchAll(PDO::FETCH_ASSOC);

    // Card 4 Detail: Top VHVs by rewards
    $rewardsDetailStmt = $pdo->prepare("
        SELECT v.vhv_name, SUM(r.points_earned) as total_points
        FROM vhv_rewards r
        JOIN vhv_users v ON r.vhv_id = v.vhv_id
        WHERE v.hoscode IN ($inPlaceholdersSa)
        GROUP BY v.vhv_id
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
        WHERE hoscode IN ($inPlaceholdersSa)
        ORDER BY moo, house_no
    ");
    $editableTargetsStmt->execute($valid_hoscodes);
    $editableTargets = $editableTargetsStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- NEW CHARTS DATA (SUPER ADMIN) ---
    $chartCoverageStmt = $pdo->prepare("
        SELECT p.hoscode, 
               COUNT(*) as total_targets,
               SUM(CASE WHEN a.assignment_status = 'completed' THEN 1 ELSE 0 END) as screened
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid
        WHERE p.hoscode IN ($inPlaceholdersSa) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY p.hoscode
    ");
    $chartCoverageStmt->execute($valid_hoscodes);
    $chartCoverageData = $chartCoverageStmt->fetchAll(PDO::FETCH_ASSOC);

    $chartRiskStmt = $pdo->prepare("
        SELECT p.hoscode,
               SUM(CASE WHEN a.assignment_status = 'completed' AND (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) THEN 1 ELSE 0 END) as high_risk,
               SUM(CASE WHEN a.assignment_status = 'completed' AND NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 1 ELSE 0 END) as moderate_risk,
               SUM(CASE WHEN a.assignment_status = 'completed' AND NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) AND NOT ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 1 ELSE 0 END) as normal,
               SUM(CASE WHEN a.assignment_status IS NULL OR a.assignment_status != 'completed' THEN 1 ELSE 0 END) as unscreened
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid
        LEFT JOIN screening_results s ON a.assignment_id = s.assignment_id
        WHERE p.hoscode IN ($inPlaceholdersSa) AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY p.hoscode
    ");
    $chartRiskStmt->execute($valid_hoscodes);
    $chartRiskData = $chartRiskStmt->fetchAll(PDO::FETCH_ASSOC);

    $chartDiseaseStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as ht_dm,
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND (s.dtx_value < 126 OR s.dtx_value IS NULL) THEN 1 ELSE 0 END) as ht_only,
            SUM(CASE WHEN (s.sys_bp1 < 140 AND s.dia_bp1 < 90) AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as dm_only,
            SUM(CASE WHEN (s.sys_bp1 < 140 AND s.dia_bp1 < 90) AND (s.dtx_value < 126 OR s.dtx_value IS NULL) 
                      AND ((s.sys_bp1 >= 120) OR (s.dia_bp1 >= 80) OR (s.dtx_value >= 100) OR (s.cv_risk_score >= 10)) THEN 1 ELSE 0 END) as risk_group,
            SUM(CASE WHEN (s.sys_bp1 < 120 AND s.dia_bp1 < 80) AND (s.dtx_value < 100 OR s.dtx_value IS NULL) AND (s.cv_risk_score < 10 OR s.cv_risk_score IS NULL) THEN 1 ELSE 0 END) as normal_group
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id AND a.assignment_status = 'completed'
        JOIN target_population p ON a.target_cid = p.cid
        WHERE p.hoscode IN ($inPlaceholdersSa)
    ");
    $chartDiseaseStmt->execute($valid_hoscodes);
    $chartDiseaseData = $chartDiseaseStmt->fetch(PDO::FETCH_ASSOC);

    $chartTrendStmt = $pdo->prepare("
        SELECT DATE(created_at) as screen_date, COUNT(*) as daily_count
        FROM (
            SELECT s.created_at
            FROM screening_results s
            JOIN task_assignments a ON s.assignment_id = a.assignment_id AND a.assignment_status = 'completed'
            JOIN target_population p ON a.target_cid = p.cid
            WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
              AND p.hoscode IN ($inPlaceholdersSa)
            UNION ALL
            SELECT f.completed_at as created_at
            FROM dpac_followups f
            JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
            JOIN target_population p ON e.cid = p.cid
            WHERE f.status = 'completed'
              AND f.completed_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
              AND p.hoscode IN ($inPlaceholdersSa)
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
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholdersSa)
        GROUP BY s.skipped_reason
    ");
    $chartSkippedStmt->execute($valid_hoscodes);
    $chartSkippedData = $chartSkippedStmt->fetchAll(PDO::FETCH_ASSOC);

    // DPAC Enrollments Data
    $chartDpacStmt = $pdo->prepare("
        SELECT e.risk_type, COUNT(*) as count 
        FROM dpac_enrollments e
        JOIN target_population p ON e.cid = p.cid
        WHERE p.hoscode IN ($inPlaceholdersSa)
        GROUP BY e.risk_type
    ");
    $chartDpacStmt->execute($valid_hoscodes);
    $chartDpacData = $chartDpacStmt->fetchAll(PDO::FETCH_ASSOC);
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
</head>

<body class="admin-body dashboard-page">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="margin-bottom: 4px;">ภาพรวมความคุ้มครองและพิกัดกลุ่มเสี่ยง (Dashboard)</h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 15px;">
            หน่วยบริการผู้รับผิดชอบ: <strong
                style="color: var(--color-accent);"><?= htmlspecialchars($admin_title) ?></strong>
        </p>

        <!-- Target Group Summary -->
        <div style="margin-bottom: 12px;">
            <h3
                style="color: var(--color-accent); margin-bottom: 8px; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z">
                    </path>
                </svg>
                กลุ่มเป้าหมายการคัดกรอง
                <span style="font-size: 13px; font-weight: normal; color: var(--text-secondary);">(รวมทั้งหมด
                    <?= number_format($metrics['total_targets']) ?> ราย)</span>
            </h3>
        </div>
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 16px; margin-bottom: 30px;">
            <!-- กลุ่มเสี่ยง DM -->
            <div class="card-dark"
                style="cursor: pointer; border-left: 4px solid #f97316; position: relative; overflow: hidden;"
                onclick="showCardModal('targets')">
                <div
                    style="position: absolute; top: -15px; right: -15px; width: 80px; height: 80px; border-radius: 50%; background: rgba(249, 115, 22, 0.08);">
                </div>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <span style="font-size: 22px;">🟠</span>
                    <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">เสี่ยง
                        (เบาหวาน)</span>
                </div>
                <div class="stat-val" style="color: #f97316;"><?= number_format($metrics['group_dm']) ?> <span
                        style="font-size: 16px; color: var(--text-secondary);">ราย</span></div>
                <div style="margin-top: 8px; font-size: 12px; color: var(--text-muted);">
                    เฉพาะเบาหวาน — คัดกรองซ้ำ
                </div>
                <div style="margin-top: 6px; font-size: 12px; color: #f97316; font-weight: bold;">
                    <?= $metrics['total_targets'] > 0 ? round(($metrics['group_dm'] / $metrics['total_targets']) * 100, 1) : 0 ?>%
                    ของเป้าหมาย
                </div>
            </div>

            <!-- กลุ่มเสี่ยง HT -->
            <div class="card-dark"
                style="cursor: pointer; border-left: 4px solid #06b6d4; position: relative; overflow: hidden;"
                onclick="showCardModal('targets')">
                <div
                    style="position: absolute; top: -15px; right: -15px; width: 80px; height: 80px; border-radius: 50%; background: rgba(6, 182, 212, 0.08);">
                </div>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <span style="font-size: 22px;">🔵</span>
                    <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">เสี่ยง
                        (ความดัน)</span>
                </div>
                <div class="stat-val" style="color: #06b6d4;"><?= number_format($metrics['group_ht']) ?> <span
                        style="font-size: 16px; color: var(--text-secondary);">ราย</span></div>
                <div style="margin-top: 8px; font-size: 12px; color: var(--text-muted);">
                    เฉพาะความดัน — คัดกรองซ้ำ
                </div>
                <div style="margin-top: 6px; font-size: 12px; color: #06b6d4; font-weight: bold;">
                    <?= $metrics['total_targets'] > 0 ? round(($metrics['group_ht'] / $metrics['total_targets']) * 100, 1) : 0 ?>%
                    ของเป้าหมาย
                </div>
            </div>

            <!-- กลุ่มเสี่ยง Both/High Risk -->
            <div class="card-dark"
                style="cursor: pointer; border-left: 4px solid var(--color-red); position: relative; overflow: hidden;"
                onclick="showCardModal('targets')">
                <div
                    style="position: absolute; top: -15px; right: -15px; width: 80px; height: 80px; border-radius: 50%; background: rgba(239, 68, 68, 0.08);">
                </div>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <span style="font-size: 22px;">🔴</span>
                    <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">เสี่ยง
                        (เบาหวาน+ความดัน)</span>
                </div>
                <div class="stat-val" style="color: var(--color-red);"><?= number_format($metrics['group_both']) ?>
                    <span style="font-size: 16px; color: var(--text-secondary);">ราย</span></div>
                <div style="margin-top: 8px; font-size: 12px; color: var(--text-muted);">
                    เสี่ยงทั้งคู่/สูง — คัดกรองซ้ำ
                </div>
                <div style="margin-top: 6px; font-size: 12px; color: var(--color-red); font-weight: bold;">
                    <?= $metrics['total_targets'] > 0 ? round(($metrics['group_both'] / $metrics['total_targets']) * 100, 1) : 0 ?>%
                    ของเป้าหมาย
                </div>
            </div>


        </div>

        <!-- Metrics Grid -->
        <div class="grid-cols-4" style="margin-bottom: 30px;">
            <div class="card-dark" style="cursor: pointer;" onclick="showCardModal('screened')">
                <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">คัดกรองเสร็จสิ้น</span>
                <div class="stat-val" style="color: var(--color-green);">
                    <?= number_format($metrics['screened_count']) ?> <span
                        style="font-size: 16px; color: var(--text-secondary);">ราย</span>
                </div>
                <div style="margin-top: 10px; font-size: 13px; color: var(--text-muted);">
                    คิดเป็น
                    <?= $metrics['total_targets'] > 0 ? round(($metrics['screened_count'] / $metrics['total_targets']) * 100, 1) : 0 ?>%
                    ของเป้าหมาย (คลิกดูรายละเอียด)
                </div>
            </div>

            <div class="card-dark" style="cursor: pointer;" onclick="showCardModal('pending')">
                <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">รอดำเนินการ
                    (Pending)</span>
                <div class="stat-val" style="color: var(--color-primary);">
                    <?= number_format($metrics['pending_count']) ?> <span
                        style="font-size: 16px; color: var(--text-secondary);">ราย</span>
                </div>
                <div style="margin-top: 10px; font-size: 13px; color: var(--text-muted);">
                    มอบหมายแล้ว รอ อสม. ดำเนินการ
                </div>
            </div>

            <div class="card-dark" style="cursor: pointer;" onclick="showCardModal('skipped')">
                <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">เลื่อน/ข้ามสะสม
                    (Skipped)</span>
                <div class="stat-val" style="color: var(--color-yellow);">
                    <?= number_format($metrics['skipped_count']) ?> <span
                        style="font-size: 16px; color: var(--text-secondary);">ราย</span>
                </div>
                <div style="margin-top: 10px; font-size: 13px; color: var(--text-muted);">
                    พักไว้เพื่อสแกนตรวจสอบซ้ำภายหลัง (คลิกดูรายละเอียด)
                </div>
            </div>

            <div class="card-dark" style="cursor: pointer;" onclick="showCardModal('rewards')">
                <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">แต้มรางวัลสะสม
                    อสม.</span>
                <div class="stat-val" style="color: var(--color-primary);">
                    <?= number_format($metrics['total_points'] ?? 0) ?> <span
                        style="font-size: 16px; color: var(--text-secondary);">แต้ม</span>
                </div>
                <div style="margin-top: 10px; font-size: 13px; color: var(--text-muted);">
                    จาก อสม. ผู้ปฏิบัติงานทั้งหมด <?= $metrics['total_vhvs'] ?> คน (คลิกดูบอร์ดคะแนน)
                </div>
            </div>
        </div>

        <!-- Analytics Dashboard Section -->

        <!-- Top Analytics Row -->
        <div class="grid-cols-4"
            style="margin-bottom: 30px; gap: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr));">
            <!-- Overall Progress (Radial) -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(14, 165, 233, 0.15); color: #0ea5e9;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </span>
                    <span>Overall Progress</span>
                </h3>
                <div id="chart-overall-progress" style="display: flex; justify-content: center;"></div>
            </div>

            <!-- Total vs Screened Pie Chart (NEW) -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(34, 197, 94, 0.15); color: #22c55e;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                        </svg>
                    </span>
                    <span>สัดส่วนคัดกรอง / เป้าหมาย</span>
                </h3>
                <div id="chart-total-pie"></div>
            </div>

            <!-- Cockpit Radar Chart (NEW) -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                    <span>มิติกลุ่มเป้าหมาย (Cockpit)</span>
                </h3>
                <div id="chart-cockpit-radar"></div>
            </div>

            <!-- Screened Risk Pie Chart (NEW) -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                    <span>สัดส่วนผลการคัดกรองแยกตามระดับความเสี่ยง</span>
                </h3>
                <div id="chart-screened-risk-pie"></div>
            </div>

            <!-- Skipped Reasons (Donut) -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(100, 116, 139, 0.15); color: #64748b;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                    </span>
                    <span>สาเหตุการข้ามเคส (เคสไม่สมบูรณ์)</span>
                </h3>
                <div id="chart-skipped"></div>
            </div>

            <!-- DPAC Enrollments (Donut) -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(6, 182, 212, 0.15); color: #06b6d4;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </span>
                    <span>กลุ่มเสี่ยงเข้าร่วมโครงการปรับเปลี่ยนพฤติกรรม</span>
                </h3>
                <div id="chart-dpac"></div>
            </div>
        </div>

        <div class="grid-cols-2"
            style="margin-bottom: 30px; gap: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 400px), 1fr));">
            <!-- Chart 1: Coverage -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                    </span>
                    <span>ความครอบคลุมการคัดกรอง แยกตาม รพ.สต.</span>
                </h3>
                <div id="chart-coverage"></div>
            </div>

            <!-- Chart 2: Risk Distribution -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(244, 63, 94, 0.15); color: #f43f5e;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </span>
                    <span>ระดับความเสี่ยงประชากร แยกตาม รพ.สต.</span>
                </h3>
                <div id="chart-risk"></div>
            </div>

            <!-- Chart 3: Disease Breakdown -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(236, 72, 153, 0.15); color: #ec4899;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <span>ผลการคัดกรองแยกตามระดับความเสี่ยงและกลุ่มโรค</span>
                </h3>
                <div id="chart-disease"></div>
            </div>

            <!-- Chart 4: Screening Trend -->
            <div class="card-dark">
                <h3
                    style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(14, 165, 233, 0.15); color: #0ea5e9;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </span>
                    <span>แนวโน้มการคัดกรองรายวัน (14 วันล่าสุด)</span>
                </h3>
                <div id="chart-trend"></div>
            </div>
        </div>

        <!-- Recent Screenings Table -->
        <div class="card-dark" style="margin-top: 30px;">
            <h3
                style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(34, 197, 94, 0.15); color: #22c55e;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </span>
                <span>ผลการคัดกรองล่าสุดในพื้นที่</span>
            </h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ประเภทกิจกรรม</th>
                            <th>บ้านเลขที่</th>
                            <th>หมู่บ้าน</th>
                            <th>หมู่</th>
                            <th>ความดันโลหิต</th>
                            <th>ค่าน้ำตาล (DTX)</th>
                            <th>ดัชนีมวลกาย (BMI)</th>
                            <th>ความเสี่ยง (CV Risk)</th>
                            <th>อสม. ผู้บันทึก</th>
                            <th>พิกัดบันทึก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($admin_hoscode) {
                            $hoscodes = [$admin_hoscode];
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
                                ORDER BY combined.created_at DESC LIMIT 10
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
                                ORDER BY combined.created_at DESC LIMIT 10
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
                                            <span style="display: inline-block; background-color: rgba(6, 182, 212, 0.15); color: #06b6d4; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; border: 1px solid rgba(6, 182, 212, 0.3); white-space: nowrap;">
                                                🔄 <?= htmlspecialchars($rs['activity_type']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-block; background-color: rgba(34, 197, 94, 0.15); color: #22c55e; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; border: 1px solid rgba(34, 197, 94, 0.3); white-space: nowrap;">
                                                📋 <?= htmlspecialchars($rs['activity_type']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($rs['house_no']) ?></td>
                                    <td><?= htmlspecialchars(get_village_display_name_by_hoscode($rs['hoscode'], $rs['moo'])) ?>
                                    </td>
                                    <td>หมู่ที่ <?= $rs['moo'] ?></td>
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
                                    <td><?= htmlspecialchars($rs['vhv_name']) ?></td>
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

        <!-- Heatmap Section -->
        <div class="card-dark" style="margin-top: 30px;">
            <h2
                style="color: var(--color-accent); border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </span>
                <span>Geographic NCDs Hotspot Heatmap (แผนที่กลุ่มเสี่ยงสูง อำเภอตาลสุม)</span>
            </h2>
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
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
                                        <?= $hasCoord ?> หมู่ <?= $et['moo'] ?>     <?= htmlspecialchars($village_only) ?> -
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
            // Data from PHP
            const hcNamesChart = <?= json_encode($hc_names) ?>;

            const isRegularAdmin = <?= json_encode($admin_hoscode !== null) ?>;

            // Coverage Data
            const coverageRaw = <?= json_encode($chartCoverageData) ?>;
            const covCategories = coverageRaw.map(d => isRegularAdmin ? (d.village_name || "หมู่ " + d.moo) : (hcNamesChart[d.hoscode] || d.hoscode));
            const covTotal = coverageRaw.map(d => parseInt(d.total_targets));
            const covScreened = coverageRaw.map(d => parseInt(d.screened));

            // Coverage Chart
            const hasCoverageData = covTotal.reduce((a, b) => a + b, 0);
            if (hasCoverageData > 0) {
                var optionsCoverage = {
                    series: [{
                        name: 'เป้าหมายทั้งหมด',
                        data: covTotal
                    }, {
                        name: 'คัดกรองแล้ว',
                        data: covScreened
                    }],
                    chart: {
                        type: 'bar',
                        height: 350,
                        background: 'transparent',
                        toolbar: { show: false }
                    },
                    theme: { mode: 'dark' },
                    colors: ['#4b5563', '#22c55e'],
                    legend: { position: 'bottom', labels: { colors: '#9ca3af' } },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '55%',
                            borderRadius: 4
                        },
                    },
                    dataLabels: { enabled: false },
                    xaxis: {
                        categories: covCategories,
                        labels: { style: { colors: '#9ca3af' } }
                    },
                    yaxis: {
                        labels: { style: { colors: '#9ca3af' } }
                    },
                    tooltip: { theme: 'dark' }
                };
                new ApexCharts(document.querySelector("#chart-coverage"), optionsCoverage).render();
            } else {
                document.querySelector("#chart-coverage").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 100px; font-size: 14px;">ยังไม่มีข้อมูลความครอบคลุมการคัดกรอง</div>';
            }

            // Risk Data
            const riskRaw = <?= json_encode($chartRiskData) ?>;

            const riskCategories = isRegularAdmin
                ? coverageRaw.map(d => d.village_name || "หมู่ " + d.moo)
                : [...new Set(coverageRaw.map(d => d.hoscode))].map(hc => hcNamesChart[hc] || hc);

            const riskNormal = [];
            const riskModerate = [];
            const riskHigh = [];
            const riskUnscreened = [];

            if (isRegularAdmin) {
                coverageRaw.forEach(covRow => {
                    const match = riskRaw.find(d => d.moo === covRow.moo && d.sub_district_code === covRow.sub_district_code) || { normal: 0, moderate_risk: 0, high_risk: 0, unscreened: 0 };
                    riskNormal.push(parseInt(match.normal) || 0);
                    riskModerate.push(parseInt(match.moderate_risk) || 0);
                    riskHigh.push(parseInt(match.high_risk) || 0);
                    riskUnscreened.push(parseInt(match.unscreened) || 0);
                });
            } else {
                const allHoscodesRaw = [...new Set(coverageRaw.map(d => d.hoscode))];
                allHoscodesRaw.forEach(hc => {
                    const match = riskRaw.find(d => d.hoscode === hc) || { normal: 0, moderate_risk: 0, high_risk: 0, unscreened: 0 };
                    riskNormal.push(parseInt(match.normal) || 0);
                    riskModerate.push(parseInt(match.moderate_risk) || 0);
                    riskHigh.push(parseInt(match.high_risk) || 0);
                    riskUnscreened.push(parseInt(match.unscreened) || 0);
                });
            }

            // Risk Chart (100% Stacked)
            const hasRiskData = riskNormal.reduce((a, b) => a + b, 0) + riskModerate.reduce((a, b) => a + b, 0) + riskHigh.reduce((a, b) => a + b, 0) + riskUnscreened.reduce((a, b) => a + b, 0);
            if (hasRiskData > 0) {
                var optionsRisk = {
                    series: [{
                        name: 'เสี่ยงปานกลาง',
                        data: riskModerate
                    }, {
                        name: 'เสี่ยงสูง',
                        data: riskHigh
                    }, {
                        name: 'ยังไม่คัดกรอง',
                        data: riskUnscreened
                    }],
                    chart: {
                        type: 'bar',
                        height: 350,
                        stacked: true,
                        stackType: '100%',
                        background: 'transparent',
                        toolbar: { show: false }
                    },
                    theme: { mode: 'dark' },
                    colors: ['#f59e0b', '#ef4444', '#4b5563'],
                    legend: { position: 'bottom', labels: { colors: '#9ca3af' } },
                    plotOptions: { bar: { borderRadius: 2 } },
                    xaxis: {
                        categories: riskCategories,
                        labels: { style: { colors: '#9ca3af' } }
                    },
                    yaxis: {
                        labels: { style: { colors: '#9ca3af' } }
                    },
                    tooltip: { theme: 'dark' },
                    fill: { opacity: 1 }
                };
                new ApexCharts(document.querySelector("#chart-risk"), optionsRisk).render();
            } else {
                document.querySelector("#chart-risk").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 100px; font-size: 14px;">ยังไม่มีข้อมูลระดับความเสี่ยงประชากร</div>';
            }

            // Disease Data
            const diseaseRaw = <?= json_encode($chartDiseaseData) ?>;
            const diseaseSeries = [
                parseInt(diseaseRaw?.risk_group || 0),
                parseInt(diseaseRaw?.dm_only || 0),
                parseInt(diseaseRaw?.ht_only || 0),
                parseInt(diseaseRaw?.ht_dm || 0)
            ];

            // Disease Chart (Donut)
            const totalDiseaseCount = diseaseSeries.reduce((a, b) => a + b, 0);
            if (totalDiseaseCount > 0) {
                var optionsDisease = {
                    series: diseaseSeries,
                    chart: {
                        type: 'donut',
                        height: 350,
                        background: 'transparent'
                    },
                    theme: { mode: 'dark' },
                    labels: ['กลุ่มเสี่ยง', 'ป่วย/สงสัยเบาหวาน (DM)', 'ป่วย/สงสัยความดัน (HT)', 'ป่วย/สงสัยทั้ง HT และ DM'],
                    colors: ['#f59e0b', '#8b5cf6', '#3b82f6', '#ec4899'],
                    stroke: { show: false },
                    legend: {
                        position: 'bottom',
                        labels: { colors: '#9ca3af' }
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function (val) {
                            return Math.round(val) + "%"
                        }
                    },
                    tooltip: { theme: 'dark' }
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
                        height: 350,
                        background: 'transparent',
                        toolbar: { show: false }
                    },
                    theme: { mode: 'dark' },
                    colors: ['#0ea5e9'],
                    legend: { position: 'bottom', labels: { colors: '#9ca3af' } },
                    dataLabels: { enabled: true },
                    stroke: { curve: 'smooth', width: 2 },
                    xaxis: {
                        categories: trendCategories,
                        labels: {
                            style: { colors: '#9ca3af' },
                            formatter: function (val) {
                                if (!val) return '';
                                const parts = val.split('-');
                                if (parts.length < 3) return val;
                                return parts[2] + '/' + parts[1]; // DD/MM format
                            }
                        }
                    },
                    yaxis: {
                        labels: { style: { colors: '#9ca3af' } }
                    },
                    tooltip: { theme: 'dark' },
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

            // Overall Progress Chart (Horizontal Bar Leaderboard)
            const totalTargetsCount = <?= intval($metrics['total_targets']) ?>;
            const totalScreenedCount = <?= intval($metrics['screened_count']) ?>;
            const progressPercent = totalTargetsCount > 0 ? Math.round((totalScreenedCount / totalTargetsCount) * 100) : 0;

            const progressData = [];

            // Calculate per village or per hoscode
            coverageRaw.forEach(d => {
                const targets = parseInt(d.total_targets);
                const screened = parseInt(d.screened);
                const pct = targets > 0 ? Math.round((screened / targets) * 100) : 0;
                progressData.push({
                    x: isRegularAdmin ? (d.village_name || "หมู่ " + d.moo) : (hcNamesChart[d.hoscode] || d.hoscode),
                    y: pct,
                    fillColor: '#22c55e'
                });
            });

            // Sort by percentage descending
            progressData.sort((a, b) => b.y - a.y);

            // Push overall first (always at the top)
            progressData.unshift({
                x: isRegularAdmin ? 'ภาพรวมหน่วยบริการ' : 'ภาพรวมทั้งอำเภอ',
                y: progressPercent,
                fillColor: '#0ea5e9' // Distinct color for overall
            });

            if (coverageRaw && coverageRaw.length > 0) {
                var optionsProgress = {
                    series: [{
                        name: 'ความคืบหน้า (%)',
                        data: progressData
                    }],
                    chart: {
                        height: 350,
                        type: 'bar',
                        background: 'transparent',
                        toolbar: { show: false }
                    },
                    theme: { mode: 'dark' },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 4,
                            dataLabels: { position: 'top' }
                        }
                    },
                    dataLabels: {
                        enabled: true,
                        offsetX: 20,
                        style: { colors: ['#9ca3af'] },
                        formatter: function (val) { return val + "%" }
                    },
                    xaxis: {
                        max: 100,
                        labels: { style: { colors: '#9ca3af' }, formatter: function (val) { return val + "%" } }
                    },
                    yaxis: {
                        labels: { style: { colors: '#9ca3af', fontSize: '12px', fontWeight: 'bold' } }
                    },
                    tooltip: { theme: 'dark' }
                };
                new ApexCharts(document.querySelector("#chart-overall-progress"), optionsProgress).render();
            } else {
                document.querySelector("#chart-overall-progress").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 100px; font-size: 14px;">ยังไม่มีข้อมูลความคืบหน้า</div>';
            }

            // Screened Risk Distribution Data (Pie Chart)
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
                    labels: ['ปกติ (เสี่ยงต่ำ)', 'เสี่ยงปานกลาง', 'เสี่ยงสูง (สงสัยป่วย)'],
                    chart: { type: 'pie', height: 280, background: 'transparent' },
                    theme: { mode: 'dark' },
                    colors: ['#22c55e', '#f59e0b', '#ef4444'],
                    stroke: { show: false },
                    legend: { position: 'bottom', labels: { colors: '#9ca3af' } },
                    dataLabels: { enabled: true, formatter: function (val) { return Math.round(val) + "%" } }
                };
                new ApexCharts(document.querySelector("#chart-screened-risk-pie"), optionsScreenedRisk).render();
            } else {
                document.querySelector("#chart-screened-risk-pie").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 100px; font-size: 14px;">ยังไม่มีข้อมูลผลการคัดกรองแยกตามระดับความเสี่ยง</div>';
            }

            // Skipped Reasons Chart
            const skippedRaw = <?= json_encode($chartSkippedData) ?>;
            if (skippedRaw && skippedRaw.length > 0) {
                var optionsSkipped = {
                    series: skippedRaw.map(d => parseInt(d.count)),
                    labels: skippedRaw.map(d => d.skipped_reason || 'ไม่ระบุ'),
                    chart: { type: 'donut', height: 280, background: 'transparent' },
                    theme: { mode: 'dark' },
                    colors: ['#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9', '#64748b'],
                    stroke: { show: false },
                    legend: { position: 'bottom', labels: { colors: '#9ca3af' } },
                    dataLabels: { enabled: true, formatter: function (val) { return Math.round(val) + "%" } }
                };
                new ApexCharts(document.querySelector("#chart-skipped"), optionsSkipped).render();
            } else {
                document.querySelector("#chart-skipped").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 50px;">ไม่มีข้อมูลการข้ามเคส</div>';
            }

            // DPAC Enrollments Chart
            const dpacRaw = <?= json_encode($chartDpacData) ?>;
            if (dpacRaw && dpacRaw.length > 0) {
                var optionsDpac = {
                    series: dpacRaw.map(d => parseInt(d.count)),
                    labels: dpacRaw.map(d => d.risk_type == '1' ? 'กลุ่มเสี่ยงเบาหวาน' : (d.risk_type == '2' ? 'กลุ่มเสี่ยงความดันฯ' : (d.risk_type == '3' ? 'กลุ่มป่วย/อื่นๆ' : 'ไม่ระบุ'))),
                    chart: { type: 'pie', height: 280, background: 'transparent' },
                    theme: { mode: 'dark' },
                    colors: ['#22d3ee', '#c084fc', '#f43f5e', '#a8a29e'],
                    stroke: { show: false },
                    legend: { position: 'bottom', labels: { colors: '#9ca3af' } },
                    dataLabels: { enabled: true, formatter: function (val) { return Math.round(val) + "%" } }
                };
                new ApexCharts(document.querySelector("#chart-dpac"), optionsDpac).render();
            } else {
                document.querySelector("#chart-dpac").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 50px;">ไม่มีข้อมูลผู้เข้าร่วมโครงการ</div>';
            }

            // Total vs Screened Pie Chart
            var optionsTotalPie = {
                series: [
                    <?= intval($metrics['screened_count']) ?>,
                    Math.max(0, <?= intval($metrics['total_targets']) ?> - <?= intval($metrics['screened_count']) ?>)
                ],
                labels: ['คัดกรองแล้ว', 'ยังไม่คัดกรอง'],
                chart: { type: 'pie', height: 280, background: 'transparent' },
                theme: { mode: 'dark' },
                colors: ['#22c55e', '#4b5563'],
                stroke: { show: false },
                legend: { position: 'bottom', labels: { colors: '#9ca3af' } },
                dataLabels: { enabled: true, formatter: function (val) { return Math.round(val) + "%" } }
            };
            if (<?= intval($metrics['total_targets']) ?> > 0) {
                new ApexCharts(document.querySelector("#chart-total-pie"), optionsTotalPie).render();
            } else {
                document.querySelector("#chart-total-pie").innerHTML = '<div style="text-align: center; color: #6b7280; margin-top: 50px;">ไม่มีข้อมูล</div>';
            }

            // Cockpit Radar Chart
            var optionsRadar = {
                series: [{
                    name: 'จำนวนประชากร',
                    data: [
                        <?= intval($metrics['group_dm'] ?? 0) ?>,
                        <?= intval($metrics['group_ht'] ?? 0) ?>,
                        <?= intval($metrics['group_both'] ?? 0) ?>,
                        <?= intval($metrics['group_suspected'] ?? 0) ?>,
                        <?= intval($metrics['group_risk'] ?? 0) ?>,
                        <?= intval($metrics['group_normal'] ?? 0) ?>
                    ]
                }],
                chart: {
                    height: 280,
                    type: 'radar',
                    background: 'transparent',
                    toolbar: { show: false }
                },
                theme: { mode: 'dark' },
                labels: ['เสี่ยงเบาหวาน', 'เสี่ยงความดัน', 'เสี่ยงคู่', 'สงสัยป่วยใหม่', 'กลุ่มเสี่ยงรวม', 'กลุ่มปกติ'],
                stroke: { width: 2, colors: ['#0ea5e9'] },
                fill: { opacity: 0.2, colors: ['#0ea5e9'] },
                markers: { size: 4, colors: ['#fff'], strokeColors: '#0ea5e9', strokeWidth: 2 },
                yaxis: { show: false },
                legend: { show: false }
            };
            new ApexCharts(document.querySelector("#chart-cockpit-radar"), optionsRadar).render();

        </script>

        <!-- Map Script Initialization -->
        <script>
            // ============== MAP DATA ==============
            var allMapData = <?= json_encode($allMapTargets) ?>;
            var hcNames = <?= json_encode($hc_names) ?>;

            // Classify risk for each target
            allMapData.forEach(function (t) {
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

            var riskColors = { high: '#ef4444', moderate: '#f59e0b', normal: '#22c55e' };
            var riskLabels = { high: '🔴 เสี่ยงสูง', moderate: '🟡 เสี่ยงปานกลาง', normal: '🟢 ปกติ' };

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
                var hasHigh = groupData.some(function(t) { return t.risk === 'high'; });
                var hasMod = groupData.some(function(t) { return t.risk === 'moderate'; });
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

            function buildMarkers(adjustView) {
                // Clear existing
                markers.forEach(function (m) { map.removeLayer(m.marker); });
                markers = [];
                if (heatLayer) { map.removeLayer(heatLayer); heatLayer = null; }

                var heatPoints = [];
                var visibleCount = 0;
                
                // Group data by coordinates
                var groupedData = {};
                var bounds = [];

                allMapData.forEach(function (t) {
                    if (!t.latitude || !t.longitude) return;

                    // Apply filters
                    var passRisk = (currentRiskFilter === 'all' || t.risk === currentRiskFilter);
                    var passHos = (currentHosFilter === 'all' || t.hoscode === currentHosFilter);

                    if (!passRisk || !passHos) return;

                    visibleCount++;
                    var lat = parseFloat(t.latitude).toFixed(6);
                    var lng = parseFloat(t.longitude).toFixed(6);
                    var key = lat + ',' + lng;
                    
                    if (!groupedData[key]) {
                        groupedData[key] = [];
                    }
                    groupedData[key].push(t);
                    
                    // Heatmap intensity for this individual
                    var intensity = t.risk === 'high' ? 1.0 : (t.risk === 'moderate' ? 0.6 : 0.3);
                    heatPoints.push([parseFloat(t.latitude), parseFloat(t.longitude), intensity]);
                    bounds.push([parseFloat(t.latitude), parseFloat(t.longitude)]);
                });

                // Create markers for each group
                Object.keys(groupedData).forEach(function(key) {
                    var group = groupedData[key];
                    var parts = key.split(',');
                    var lat = parseFloat(parts[0]);
                    var lng = parseFloat(parts[1]);
                    
                    // Determine highest risk in group for marker color
                    var hasHigh = group.some(function(t) { return t.risk === 'high'; });
                    var hasMod = group.some(function(t) { return t.risk === 'moderate'; });
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

                    markers.push({ marker: marker, data: group });
                });

                // Update counter
                document.getElementById('visible-count').textContent = visibleCount;

                // Add heatmap layer
                if (heatPoints.length > 0) {
                    heatLayer = L.heatLayer(heatPoints, {
                        radius: 25,
                        blur: 15,
                        maxZoom: 15,
                        gradient: { 0.2: '#22c55e', 0.4: '#a3e635', 0.6: '#f59e0b', 0.8: '#f97316', 1.0: '#ef4444' }
                    }).addTo(map);
                }

                // Adjust map view to fit all filtered points
                if (adjustView) {
                    if (bounds.length > 0) {
                        // Calculate centroid (average coordinates) of all visible points
                        var latSum = 0;
                        var lngSum = 0;
                        bounds.forEach(function (c) {
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
                document.querySelectorAll('[id^="btn-risk-"]').forEach(function (btn) {
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
                document.querySelectorAll('[id^="btn-hos-"]').forEach(function (btn) {
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
                    // Jump to existing location
                    map.setView([lat, lng], 16);
                    document.getElementById('edit-status').innerHTML = '📌 พิกัดปัจจุบัน: ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '<br>💡 คลิกบนแผนที่เพื่อเปลี่ยนตำแหน่งใหม่';
                } else {
                    document.getElementById('edit-status').innerHTML = '❌ ยังไม่มีพิกัด - คลิกบนแผนที่เพื่อกำหนดตำแหน่ง';
                }
            }

            map.on('click', function (e) {
                if (!editMode) return;

                var cid = document.getElementById('edit-target-select').value;
                if (!cid) {
                    document.getElementById('edit-status').innerHTML = '<span style="color: var(--color-red);">⚠️ กรุณาเลือกรายชื่อเป้าหมายก่อน</span>';
                    return;
                }

                var lat = e.latlng.lat;
                var lng = e.latlng.lng;
                pendingCoord = { cid: cid, latitude: lat, longitude: lng };

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

                editMarker = L.marker([lat, lng], { icon: pulseIcon, draggable: true }).addTo(map);
                editMarker.bindPopup('<div style="color: black; font-weight: bold;">📍 ตำแหน่งใหม่<br>' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</div>').openPopup();

                editMarker.on('dragend', function (e) {
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
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(pendingCoord)
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.success) {
                            document.getElementById('edit-status').innerHTML = '<span style="color: var(--color-green); font-weight: bold;">✅ ' + data.message + '</span>';

                            // Update local data
                            var found = allMapData.find(function (t) { return t.cid === pendingCoord.cid; });
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

                            if (editMarker) { map.removeLayer(editMarker); editMarker = null; }
                            pendingCoord = null;
                            btn.style.display = 'none';
                        } else {
                            document.getElementById('edit-status').innerHTML = '<span style="color: var(--color-red);">❌ ' + data.message + '</span>';
                        }

                        btn.textContent = '💾 บันทึกพิกัด';
                        btn.disabled = false;
                    })
                    .catch(function (err) {
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
                style="width: 90%; max-width: 500px; padding: 24px; max-height: 80vh; overflow-y: auto;">
                <h3 id="modal-title"
                    style="color: var(--color-accent); border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px;">
                    รายละเอียด</h3>
                <div id="modal-body-content" style="margin-bottom: 24px;"></div>
                <button onclick="closeDetailsModal()" class="btn-primary"
                    style="width: 100%; height: 50px; border-radius: 25px; border: none; background: var(--color-primary); color: white; font-weight: bold; cursor: pointer;">ปิดหน้าต่าง</button>
            </div>
        </div>

        <!-- Metric Card Modal JS -->
        <script>
            var targetsDetail = <?= json_encode($targetsDetail) ?>;
            var groupDetail = <?= json_encode($groupDetail) ?>;
            var screenedDetail = <?= json_encode($screenedDetail) ?>;
            var skippedDetail = <?= json_encode($skippedDetail) ?>;
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

                if (type === 'targets') {
                    title = '📊 กลุ่มเป้าหมายการคัดกรอง แยกตามสถานะ HDC';
                    html = '<table class="admin-table"><thead><tr><th>กลุ่มเป้าหมาย</th><th style="text-align: right;">จำนวน (ราย)</th></tr></thead><tbody>';
                    if (groupDetail.length === 0) {
                        html += '<tr><td colspan="2" style="text-align: center;">ไม่มีข้อมูล</td></tr>';
                    } else {
                        groupDetail.forEach(function (row) {
                            var label = groupLabels[row.health_status_origin] || row.health_status_origin;
                            var color = groupColors[row.health_status_origin] || 'var(--text-primary)';
                            html += '<tr><td style="color: ' + color + '; font-weight: bold;">' + label + '</td><td style="text-align: right; font-weight: bold; color: ' + color + ';">' + Number(row.count).toLocaleString() + ' ราย</td></tr>';
                        });
                    }
                    html += '<tr style="background-color: var(--bg-darker); font-weight: bold;"><td>รวมทั้งหมด</td><td style="text-align: right;">' + Number(<?= $metrics['total_targets'] ?>).toLocaleString() + ' ราย</td></tr>';
                    html += '</tbody></table>';

                    // Also show per-village/hoscode breakdown
                    html += '<h4 style="margin-top: 20px; color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">แยกตามพื้นที่</h4>';
                    html += '<table class="admin-table"><thead><tr><th>พื้นที่</th><th style="text-align: right;">จำนวน (ราย)</th></tr></thead><tbody>';
                    <?php if (!$admin_hoscode): ?>
                        if (targetsDetail.length === 0) {
                            html += '<tr><td colspan="2" style="text-align: center;">ไม่มีข้อมูล</td></tr>';
                        } else {
                            targetsDetail.forEach(function (row) {
                                html += '<tr><td>' + (hcNamesChart[row.hoscode] || row.hoscode) + '</td><td style="text-align: right; font-weight: bold;">' + Number(row.count).toLocaleString() + ' ราย</td></tr>';
                            });
                        }
                    <?php else: ?>
                        if (targetsDetail.length === 0) {
                            html += '<tr><td colspan="2" style="text-align: center;">ไม่มีข้อมูล</td></tr>';
                        } else {
                            targetsDetail.forEach(function (row) {
                                html += '<tr><td>' + (row.village_name || ('หมู่ที่ ' + row.moo)) + '</td><td style="text-align: right; font-weight: bold;">' + Number(row.count).toLocaleString() + ' ราย</td></tr>';
                            });
                        }
                    <?php endif; ?>
                    html += '</tbody></table>';
                } else if (type === 'screened') {
                    title = '🟢 ผลการคัดกรองเสร็จสิ้นแยกกลุ่มเสี่ยง';
                    var high = Number(screenedDetail.high_risk || 0);
                    var risk = Number(screenedDetail.risk || 0);
                    var normal = Number(screenedDetail.normal || 0);
                    var total = high + risk + normal;

                    html = '<table class="admin-table"><tbody>' +
                        '<tr><td>🔴 กลุ่มเสี่ยงสูง (High Risk)</td><td style="text-align: right; font-weight: bold; color: var(--color-red);">' + high.toLocaleString() + ' ราย (' + (total > 0 ? Math.round(high / total * 100) : 0) + '%)</td></tr>' +
                        '<tr><td>🟡 กลุ่มเสี่ยง (Moderate Risk)</td><td style="text-align: right; font-weight: bold; color: var(--color-yellow);">' + risk.toLocaleString() + ' ราย (' + (total > 0 ? Math.round(risk / total * 100) : 0) + '%)</td></tr>' +
                        '<tr><td>🟢 กลุ่มปกติ (Normal)</td><td style="text-align: right; font-weight: bold; color: var(--color-green);">' + normal.toLocaleString() + ' ราย (' + (total > 0 ? Math.round(normal / total * 100) : 0) + '%)</td></tr>' +
                        '<tr style="font-weight: bold; background-color: var(--bg-darker);"><td>รวมคัดกรองเสร็จสิ้น</td><td style="text-align: right;">' + total.toLocaleString() + ' ราย</td></tr>' +
                        '</tbody></table>';
                } else if (type === 'skipped') {
                    title = '⚠️ สาเหตุที่กดข้าม / เลื่อนตรวจสะสม';
                    html = '<table class="admin-table"><thead><tr><th>เหตุผล</th><th style="text-align: right;">จำนวนเคส (ราย)</th></tr></thead><tbody>';
                    if (skippedDetail.length === 0) {
                        html += '<tr><td colspan="2" style="text-align: center;">ไม่มีเคสถูกข้าม</td></tr>';
                    } else {
                        skippedDetail.forEach(function (row) {
                            html += '<tr><td>' + (row.skipped_reason || 'ไม่อยู่บ้าน/ไม่มีผู้ให้ประวัติ') + '</td><td style="text-align: right; font-weight: bold;">' + Number(row.count).toLocaleString() + ' เคส</td></tr>';
                        });
                    }
                    html += '</tbody></table>';
                } else if (type === 'rewards') {
                    title = '🏆 กระดานคะแนน อสม. ยอดเยี่ยม (Top 10)';
                    html = '<table class="admin-table"><thead><tr><th>อสม. ผู้ปฏิบัติงาน</th><th style="text-align: right;">คะแนนสะสม (แต้ม)</th></tr></thead><tbody>';
                    if (rewardsDetail.length === 0) {
                        html += '<tr><td colspan="2" style="text-align: center;">ยังไม่มีการบันทึกผลงานสะสม</td></tr>';
                    } else {
                        rewardsDetail.forEach(function (row) {
                            html += '<tr><td style="font-weight: bold; color: var(--text-primary);">' + row.vhv_name + '</td><td style="text-align: right; font-weight: bold; color: var(--color-green);">' + Number(row.total_points).toLocaleString() + ' แต้ม</td></tr>';
                        });
                    }
                    html += '</tbody></table>';
                }

                document.getElementById('modal-title').textContent = title;
                document.getElementById('modal-body-content').innerHTML = html;
                document.getElementById('details-modal').style.display = 'flex';
            }

            function closeDetailsModal() {
                document.getElementById('details-modal').style.display = 'none';
            }
        </script>
</body>

</html>