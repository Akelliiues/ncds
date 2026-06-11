<?php
// scratch/test_admin_queries.php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = 'admin';

// Test case 1: Super Admin (no hoscode)
$_SESSION['admin_hoscode'] = null; 

echo "=== Testing Database Connection ===\n";
require_once __DIR__ . '/../config/db.php';
echo "Connected successfully!\n\n";

echo "=== Testing Super Admin Queries ===\n";
try {
    $valid_hoscodes = get_query_hoscodes();
    $inPlaceholdersSa = implode(',', array_fill(0, count($valid_hoscodes), '?'));

    echo "1. Metrics query... ";
    $metricsStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM target_population WHERE hoscode IN ($inPlaceholdersSa) AND (need_screen_dm = 1 OR need_screen_ht = 1)) as total_targets,
            (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'completed' AND p.hoscode IN ($inPlaceholdersSa)) as screened_count,
            (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'pending' AND p.hoscode IN ($inPlaceholdersSa)) as pending_count,
            (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.assignment_status = 'skipped' AND p.hoscode IN ($inPlaceholdersSa)) as skipped_count,
            (SELECT SUM(points_earned) FROM vhv_rewards r JOIN vhv_users v ON r.vhv_id = v.vhv_id WHERE v.hoscode IN ($inPlaceholdersSa)) as total_points,
            (SELECT COUNT(*) FROM vhv_users WHERE hoscode IN ($inPlaceholdersSa)) as total_vhvs
    ");
    $metricsParams = array_merge($valid_hoscodes, $valid_hoscodes, $valid_hoscodes, $valid_hoscodes, $valid_hoscodes, $valid_hoscodes);
    $metricsStmt->execute($metricsParams);
    $metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC);
    echo "OK (total_targets: " . $metrics['total_targets'] . ")\n";

    echo "2. Group breakdown query... ";
    $groupStmtSa = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN need_screen_dm = 1 AND need_screen_ht = 0 THEN 1 ELSE 0 END) as group_dm,
            SUM(CASE WHEN need_screen_dm = 0 AND need_screen_ht = 1 THEN 1 ELSE 0 END) as group_ht,
            SUM(CASE WHEN need_screen_dm = 1 AND need_screen_ht = 1 THEN 1 ELSE 0 END) as group_both,
            SUM(CASE WHEN need_screen_dm = 1 OR need_screen_ht = 1 THEN 1 ELSE 0 END) as group_risk,
            SUM(CASE WHEN health_status_origin = 'NORMAL' AND (need_screen_dm = 0 AND need_screen_ht = 0) THEN 1 ELSE 0 END) as group_normal,
            SUM(CASE WHEN need_screen_dm = 0 AND need_screen_ht = 0 THEN 1 ELSE 0 END) as group_suspected
        FROM target_population WHERE hoscode IN ($inPlaceholdersSa)
    ");
    $groupStmtSa->execute($valid_hoscodes);
    $groupCounts = $groupStmtSa->fetch(PDO::FETCH_ASSOC);
    echo "OK (group_risk: " . $groupCounts['group_risk'] . ", group_dm: " . $groupCounts['group_dm'] . ", group_ht: " . $groupCounts['group_ht'] . ", group_both: " . $groupCounts['group_both'] . ")\n";

    echo "3. Group detail breakdown... ";
    $groupDetailStmtSa = $pdo->prepare("
        SELECT 
            CASE 
                WHEN need_screen_dm = 1 AND need_screen_ht = 1 THEN 'BOTH'
                WHEN need_screen_dm = 1 AND need_screen_ht = 0 THEN 'DM_ONLY'
                WHEN need_screen_dm = 0 AND need_screen_ht = 1 THEN 'HT_ONLY'
                ELSE 'NORMAL'
            END as health_status_origin,
            COUNT(*) as count 
        FROM target_population WHERE hoscode IN ($inPlaceholdersSa)
        GROUP BY 
            CASE 
                WHEN need_screen_dm = 1 AND need_screen_ht = 1 THEN 'BOTH'
                WHEN need_screen_dm = 1 AND need_screen_ht = 0 THEN 'DM_ONLY'
                WHEN need_screen_dm = 0 AND need_screen_ht = 1 THEN 'HT_ONLY'
                ELSE 'NORMAL'
            END
        ORDER BY FIELD(health_status_origin, 'BOTH','DM_ONLY','HT_ONLY','NORMAL')
    ");
    $groupDetailStmtSa->execute($valid_hoscodes);
    $groupDetail = $groupDetailStmtSa->fetchAll(PDO::FETCH_ASSOC);
    echo "OK (rows: " . count($groupDetail) . ")\n";

    echo "4. Targets detail... ";
    $targetsDetailStmt = $pdo->prepare("SELECT hoscode, COUNT(*) as count FROM target_population WHERE hoscode IN ($inPlaceholdersSa) AND (need_screen_dm = 1 OR need_screen_ht = 1) GROUP BY hoscode ORDER BY hoscode");
    $targetsDetailStmt->execute($valid_hoscodes);
    $targetsDetail = $targetsDetailStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "OK (rows: " . count($targetsDetail) . ")\n";

    echo "5. Screened detail... ";
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
    echo "OK (high_risk: " . ($screenedDetail['high_risk'] ?? 0) . ")\n";

    echo "6. Skipped detail... ";
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
    echo "OK (rows: " . count($skippedDetail) . ")\n";

    echo "7. Rewards detail... ";
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
    echo "OK (rows: " . count($rewardsDetail) . ")\n";

    echo "8. Heatmap... ";
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
    echo "OK (rows: " . count($allMapTargets) . ")\n";

    echo "9. Chart coverage... ";
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
    echo "OK (rows: " . count($chartCoverageData) . ")\n";

    echo "10. Chart trend... ";
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
    echo "OK (rows: " . count($chartTrendData) . ")\n";

} catch (Exception $e) {
    echo "\n[ERROR] Super Admin Queries failed: " . $e->getMessage() . "\n";
}

// Test case 2: Regular Admin (with hoscode 10957)
$_SESSION['admin_hoscode'] = '10957';
echo "\n=== Testing Regular Admin Queries (hoscode 10957) ===\n";
try {
    $hoscodes = get_query_hoscodes($_SESSION['admin_hoscode']);
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));

    echo "1. Metrics query... ";
    $total_targets = $pdo->prepare("SELECT COUNT(*) FROM target_population WHERE hoscode IN ($inPlaceholders) AND (need_screen_dm = 1 OR need_screen_ht = 1)");
    $total_targets->execute($hoscodes);
    $total_targets_val = $total_targets->fetchColumn();
    echo "OK (total_targets: " . $total_targets_val . ")\n";

    echo "2. Group breakdown... ";
    $groupStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN need_screen_dm = 1 AND need_screen_ht = 0 THEN 1 ELSE 0 END) as group_dm,
            SUM(CASE WHEN need_screen_dm = 0 AND need_screen_ht = 1 THEN 1 ELSE 0 END) as group_ht,
            SUM(CASE WHEN need_screen_dm = 1 AND need_screen_ht = 1 THEN 1 ELSE 0 END) as group_both,
            SUM(CASE WHEN need_screen_dm = 1 OR need_screen_ht = 1 THEN 1 ELSE 0 END) as group_risk,
            SUM(CASE WHEN health_status_origin = 'NORMAL' AND (need_screen_dm = 0 AND need_screen_ht = 0) THEN 1 ELSE 0 END) as group_normal,
            SUM(CASE WHEN need_screen_dm = 0 AND need_screen_ht = 0 THEN 1 ELSE 0 END) as group_suspected
        FROM target_population WHERE hoscode IN ($inPlaceholders)
    ");
    $groupStmt->execute($hoscodes);
    $groupCounts = $groupStmt->fetch(PDO::FETCH_ASSOC);
    echo "OK (group_risk: " . $groupCounts['group_risk'] . ")\n";

    echo "3. Recent screenings query... ";
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
    echo "OK (rows: " . count($recentScreens) . ")\n";

} catch (Exception $e) {
    echo "\n[ERROR] Regular Admin Queries failed: " . $e->getMessage() . "\n";
}
