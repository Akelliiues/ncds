<?php
// admin/analytics.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

$hc_names = get_health_units();


$admin_title = get_admin_title();

if ($admin_hoscode) {
    $hoscodes = get_query_hoscodes($admin_hoscode);
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
} else {
    $valid_hoscodes = get_query_hoscodes();
    $inPlaceholders = implode(',', array_fill(0, count($valid_hoscodes), '?'));
    $hoscodes = $valid_hoscodes;
}

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
    JOIN dpac_followups f1 ON e.enrollment_id = f1.enrollment_id AND f1.round_number = 1 AND f1.status = 'completed'
    JOIN dpac_followups fl ON e.enrollment_id = fl.enrollment_id AND fl.status = 'completed'
    JOIN (
        SELECT enrollment_id, MAX(round_number) as max_round
        FROM dpac_followups
        WHERE status = 'completed'
        GROUP BY enrollment_id
    ) max_f ON fl.enrollment_id = max_f.enrollment_id AND fl.round_number = max_f.max_round
    WHERE max_f.max_round > 1 AND p.hoscode IN ($inPlaceholders)
    ORDER BY fl.completed_at DESC
");
$beforeAfterStmt->execute($hoscodes);
$beforeAfterData = $beforeAfterStmt->fetchAll(PDO::FETCH_ASSOC);

// Summarize stats
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
            COALESCE(FLOOR(DATEDIFF(CURRENT_DATE, p.dob)/365.25), 48) AS age,
            s.sys_bp1, s.dia_bp1, s.dtx_value, s.bmi, s.family_history, s.created_at AS screen_date
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        JOIN screening_results s ON a.assignment_id = s.assignment_id
        JOIN (
            SELECT target_cid, MAX(assignment_id) as max_aid
            FROM task_assignments
            WHERE assignment_status = 'completed'
            GROUP BY target_cid
        ) latest_a ON a.assignment_id = latest_a.max_aid
        WHERE a.assignment_status = 'completed' 
          AND p.hoscode IN ($inPlaceholders)
          AND (
              (s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)
          )
    ");
    $predictiveStmt->execute($hoscodes);
    $predictiveRaw = $predictiveStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $ex) {
    try {
        $predictiveStmt = $pdo->prepare("
            SELECT 
                p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode, 48 AS age,
                s.sys_bp1, s.dia_bp1, s.dtx_value, s.bmi, s.family_history, s.created_at AS screen_date
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            JOIN screening_results s ON a.assignment_id = s.assignment_id
            JOIN (
                SELECT target_cid, MAX(assignment_id) as max_aid
                FROM task_assignments
                WHERE assignment_status = 'completed'
                GROUP BY target_cid
            ) latest_a ON a.assignment_id = latest_a.max_aid
            WHERE a.assignment_status = 'completed' 
              AND p.hoscode IN ($inPlaceholders)
              AND (
                  (s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)
              )
        ");
        $predictiveStmt->execute($hoscodes);
        $predictiveRaw = $predictiveStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e2) {}
}

$conversionRiskList = [];
$highConversionCount = 0;

foreach ($predictiveRaw as $row) {
    $score = 0;
    $factors = [];
    
    if (($row['sys_bp1'] ?? 0) >= 130 || ($row['dia_bp1'] ?? 0) >= 85) {
        $score += 30;
        $factors[] = "BP โซนสูง (" . $row['sys_bp1'] . "/" . $row['dia_bp1'] . ")";
    }
    if (($row['dtx_value'] ?? 0) >= 110) {
        $score += 30;
        $factors[] = "DTX โซนสูง (" . $row['dtx_value'] . " mg/dL)";
    }
    if (($row['bmi'] ?? 0) >= 25) {
        $score += 20;
        $factors[] = "BMI เกิน (" . number_format($row['bmi'], 1) . ")";
    }
    if (!empty($row['family_history']) && !in_array($row['family_history'], ['NONE', 'ไม่มี', '0'])) {
        $score += 10;
        $factors[] = "มีประวัติครอบครัว";
    }
    if (($row['age'] ?? 0) >= 45) {
        $score += 10;
        $factors[] = "อายุ 45+";
    }
    
    if ($score >= 50) {
        $highConversionCount++;
    }
    
    $v_name = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($hoscode_villages[$row['hoscode']]['tambon'] ?? '', $row['moo']);
    
    $conversionRiskList[] = [
        'cid' => $row['cid'],
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'house_no' => $row['house_no'],
        'moo' => $row['moo'],
        'village' => $v_name ?: 'หมู่ที่ ' . $row['moo'],
        'hc' => $hc_names[$row['hoscode']] ?? $row['hoscode'],
        'age' => $row['age'],
        'score' => $score,
        'factors' => implode(', ', $factors)
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
            u.user_id, u.full_name AS vhv_name, u.hoscode, u.village,
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
        FROM users u
        JOIN task_assignments a ON (u.full_name = a.assigned_vhv OR CAST(u.user_id AS CHAR) = a.assigned_vhv)
        JOIN screening_results s ON a.assignment_id = s.assignment_id
        LEFT JOIN dpac_enrollments e ON a.target_cid = e.cid
        LEFT JOIN dpac_followups f1 ON e.enrollment_id = f1.enrollment_id AND f1.round_number = 1 AND f1.status = 'completed'
        LEFT JOIN dpac_followups fl ON e.enrollment_id = fl.enrollment_id AND fl.status = 'completed'
        LEFT JOIN (
            SELECT enrollment_id, MAX(round_number) as max_round
            FROM dpac_followups
            WHERE status = 'completed'
            GROUP BY enrollment_id
        ) max_f ON fl.enrollment_id = max_f.enrollment_id AND fl.round_number = max_f.max_round
        WHERE a.assignment_status = 'completed' AND u.hoscode IN ($inPlaceholders)
        GROUP BY u.user_id, u.full_name, u.hoscode, u.village
        HAVING total_screened >= 2
        ORDER BY dpac_improved_count DESC, risk_found DESC
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
$ageGroups = ['35-44 ปี', '45-54 ปี', '55-64 ปี', '65 ปีขึ้นไป'];
$maleTotal = [0, 0, 0, 0];
$maleScreened = [0, 0, 0, 0];
$femaleTotal = [0, 0, 0, 0];
$femaleScreened = [0, 0, 0, 0];

try {
    $pyramidStmt = $pdo->prepare("
        SELECT 
            COALESCE(p.sex, '1') as sex,
            CASE 
                WHEN COALESCE(FLOOR(DATEDIFF(CURRENT_DATE, p.dob)/365.25), 45) BETWEEN 35 AND 44 THEN '35-44 ปี'
                WHEN COALESCE(FLOOR(DATEDIFF(CURRENT_DATE, p.dob)/365.25), 45) BETWEEN 45 AND 54 THEN '45-54 ปี'
                WHEN COALESCE(FLOOR(DATEDIFF(CURRENT_DATE, p.dob)/365.25), 45) BETWEEN 55 AND 64 THEN '55-64 ปี'
                ELSE '65 ปีขึ้นไป'
            END AS age_group,
            COUNT(*) as total_target,
            SUM(CASE WHEN a.assignment_status = 'completed' THEN 1 ELSE 0 END) as screened_count
        FROM target_population p
        LEFT JOIN (
            SELECT DISTINCT target_cid, 'completed' as assignment_status 
            FROM task_assignments 
            WHERE assignment_status = 'completed'
        ) a ON p.cid = a.target_cid
        WHERE p.hoscode IN ($inPlaceholders)
        GROUP BY sex, age_group
        ORDER BY age_group ASC
    ");
    $pyramidStmt->execute($hoscodes);
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

        <!-- Module 1: Predictive Disease Conversion Risk -->
        <div class="card-dark" style="margin-bottom: 30px; padding: 24px; border-top: 4px solid #f59e0b;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <h3 style="color: #f59e0b; margin: 0; font-size: 17px; display: flex; align-items: center; gap: 8px;">
                    <span>🔮 การทำนายความเสี่ยงการเกิดโรครายใหม่ (Predictive Conversion Risk Model)</span>
                </h3>
                <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: bold;">
                    เสี่ยงสูงต่อการเกิดโรครายใหม่: <?= number_format($highConversionCount) ?> ราย
                </span>
            </div>
            <p style="color: var(--text-secondary); font-size: 13.5px; margin-bottom: 16px; line-height: 1.6;">
                วิเคราะห์จากกลุ่มเสี่ยงปานกลาง (Pre-hypertension / Pre-diabetes) โดยคำนวณปัจจัยเสี่ยงผสม (ค่า BP/DTX โซนบน, ดัชนีมวลกาย BMI, ประวัติครอบครัว และอายุ) เพื่อให้ รพ.สต. และ อสม. เร่งลงพื้นที่ป้องกันล่วงหน้า 6-12 เดือน
            </p>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="text-align: center; width: 60px;">ลำดับ</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>หน่วยบริการ / หมู่บ้าน</th>
                            <th style="text-align: center;">อายุ</th>
                            <th style="text-align: center;">โอกาสป่วย (%)</th>
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
                                    <td style="text-align: center;"><?= $r['age'] ?> ปี</td>
                                    <td style="text-align: center;">
                                        <span style="background: <?= $r['score'] >= 60 ? 'rgba(239, 68, 68, 0.15)' : 'rgba(245, 158, 11, 0.15)' ?>; color: <?= $r['score'] >= 60 ? '#ef4444' : '#f59e0b' ?>; padding: 4px 10px; border-radius: 12px; font-weight: bold;">
                                            <?= $r['score'] ?>%
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
                <span>แผนภูมิพยากรณ์และวิเคราะห์คาดการณ์แนวโน้มกลุ่มเสี่ยงสะสมล่วงหน้า (Predictive Risk Trend Forecasting)</span>
            </h3>
            <div id="chart-forecast" style="margin-bottom: 20px;"></div>

            <div style="background: rgba(14, 165, 233, 0.05); padding: 16px; border-radius: 12px; border: 1px solid rgba(14, 165, 233, 0.15); font-size: 13.5px; color: var(--text-secondary); line-height: 1.6;" id="forecast-insights">
                🔍 <strong>บทวิเคราะห์แนวโน้มการคาดการณ์เชิงสถิติ:</strong>
                <span id="forecast-insight-text">กำลังประมวลผลข้อมูลและคาดการณ์จากประวัติ...</span>
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
                                $village_only = $hoscode_villages[$vi['hoscode']]['villages'][intval($vi['moo'])] ?? get_village_only_name($hoscode_villages[$vi['hoscode']]['tambon'], $vi['moo']);
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
                        $vname = $hoscode_villages[$vi['hoscode']]['villages'][intval($vi['moo'])] ?? get_village_only_name($hoscode_villages[$vi['hoscode']]['tambon'], $vi['moo']);
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
                insightElement.innerHTML = `แนวโน้มอัตราการเกิดกลุ่มเสี่ยงและผู้ป่วยรายใหม่มีทิศทาง **เพิ่มขึ้น** (ความชัน: +${slope.toFixed(2)} รายต่อเดือน) แนะนำให้ รพ.สต. และ อสม. จัดกิจกรรมกระตุ้นพฤติกรรมสุขภาพ หรือเพิ่มความเข้มข้นในการคัดกรองและการดำเนินกิจกรรมในคลินิก DPAC เป็นพิเศษเพื่อชะลอการเกิดของกลุ่มผู้ป่วยรายใหม่`;
            } else if (slope < -0.5) {
                insightElement.innerHTML = `แนวโน้มอัตราการเกิดกลุ่มเสี่ยงรายใหม่มีทิศทาง **ลดลง** อย่างต่อเนื่อง (ความชัน: ${slope.toFixed(2)} รายต่อเดือน) แสดงถึงผลลัพธ์ที่ดีเยี่ยมจากการจัดกิจกรรมควบคุมโรคและการร่วมมือดูแลสุขภาพในพื้นที่ แนะนำให้คงมาตรการเฝ้าระวังเชิงรุกและการติดตามพฤติกรรมนี้ไว้เพื่อความยั่งยืน`;
            } else {
                insightElement.innerHTML = `แนวโน้มอัตราการเกิดกลุ่มเสี่ยงรายใหม่ค่อนข้าง **คงที่และทรงตัว** (ความชัน: ${slope.toFixed(2)} รายต่อเดือน) สถานการณ์ภาพรวมอยู่ในระดับคงตัวและสามารถควบคุมได้ตามมาตรฐาน แนะนำให้รักษารอบการเยี่ยมบ้านและการติดตามผลตามตารางปกติ`;
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
            csv += "CID,ชื่อ-นามสกุล,บ้านเลขที่,หมู่ที่,หน่วยบริการ,อายุ,คะแนนความเสี่ยงทำนาย(%),ปัจจัยเสี่ยง\n";
            
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