<?php
// admin/index.php
session_start();

// ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

function get_village_only_name($vhid_code, $moo) {
    $tambon = substr($vhid_code, 0, 6);
    $moo = intval($moo);
    
    $villages = [
        '341801' => [
            1 => 'บ้านม่วงโคน',
            2 => 'บ้านดอนรังกา',
            3 => 'บ้านนาห้วยแคน',
            4 => 'บ้านดอนพันชาด',
            5 => 'บ้านนามน',
            6 => 'บ้านดอนตะลี',
            7 => 'บ้านปากห้วย',
            8 => 'บ้านโนนค้อ',
            9 => 'บ้านแก่งกบ',
            10 => 'บ้านนามน',
            11 => 'บ้านตาลสุม',
            12 => 'บ้านคำไม้ตาย',
            13 => 'บ้านปากเซ',
            14 => 'บ้านโนนสวรรค์',
            15 => 'บ้านทุ่งเจริญ'
        ],
        '341802' => [
            1 => 'บ้านสำโรงใหญ่',
            2 => 'บ้านสำโรงกลาง',
            3 => 'บ้านนาโพธิ์',
            4 => 'บ้านสำโรงใต้',
            5 => 'บ้านทรายมูลเหนือ',
            6 => 'บ้านทรายมูลใต้',
            7 => 'บ้านหนองบัว',
            8 => 'บ้านทุ่งเจริญ'
        ],
        '341803' => [
            1 => 'บ้านจิกเทิง',
            2 => 'บ้านจิกลุ่ม',
            3 => 'บ้านเชียงแก้ว',
            4 => 'บ้านเชียงแก้ว',
            5 => 'บ้านดอนโด่',
            6 => 'บ้านดอนยูง',
            7 => 'บ้านค้อ',
            8 => 'บ้านดอนแป้นลม',
            9 => 'บ้านสร้างคำ'
        ],
        '341804' => [
            1 => 'บ้านหนองกุงใหญ่',
            2 => 'บ้านหนองกุงน้อย',
            3 => 'บ้านคำแคน',
            4 => 'บ้านสร้างแสง',
            5 => 'บ้านคำเตยใต้',
            6 => 'บ้านสร้างหว้า',
            7 => 'บ้านคำเตยเหนือ',
            8 => 'บ้านสร้างหว้าพัฒนา'
        ],
        '341805' => [
            1 => 'บ้านนาคาย',
            2 => 'บ้านโนนจิก',
            3 => 'บ้านหนองเป็ด',
            4 => 'บ้านโนนยาง',
            5 => 'บ้านดอนขวาง',
            6 => 'บ้านดอนหวาย',
            7 => 'บ้านโคกคล้าย',
            8 => 'บ้านคำหนามแท่ง',
            9 => 'บ้านคำผักหนอก',
            10 => 'บ้านคำฮี',
            11 => 'บ้านห่องแดง',
            12 => 'บ้านโนนสำราญ',
            13 => 'บ้านโนนเจริญ'
        ],
        '341806' => [
            1 => 'บ้านคำหว้า',
            2 => 'บ้านคำหว้า',
            3 => 'บ้านห้วยดู่',
            4 => 'บ้านนาทมเหนือ',
            5 => 'บ้านไฮหย่อง',
            6 => 'บ้านนาทมใต้'
        ]
    ];

    return $villages[$tambon][$moo] ?? "หมู่ที่ {$moo}";
}

// Fetch summary metrics
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

$hc_names = [
    '10957' => 'โรงพยาบาลตาลสุม',
    '10688' => 'โรงพยาบาลตาลสุม',
    '03751' => 'รพ.สต.ดอนพันชาด',
    '03752' => 'รพ.สต.สำโรง',
    '03753' => 'รพ.สต.บ้านจิกเทิง',
    '03754' => 'รพ.สต.หนองกุง',
    '03755' => 'รพ.สต.นาคาย',
    '03756' => 'รพ.สต.บ้านคำหนามแท่ง',
    '03757' => 'รพ.สต.คำหว้า'
];

$admin_title = $admin_hoscode ? ($hc_names[$admin_hoscode] ?? 'รพ.สต.') : 'แอดมินหลัก (ทุก รพ.สต.)';

if ($admin_hoscode) {
    $hoscodes = [$admin_hoscode];
    if ($admin_hoscode === '10957') {
        $hoscodes[] = '10688';
    }
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
    
    $total_targets = $pdo->prepare("SELECT COUNT(*) FROM target_population WHERE hoscode IN ($inPlaceholders)");
    $total_targets->execute($hoscodes);
    $total_targets_val = $total_targets->fetchColumn();
    
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
        'screened_count' => $screened_val,
        'pending_count' => $pending_val,
        'skipped_count' => $skipped_val,
        'total_points' => $rewards_val,
        'total_vhvs' => $total_vhvs_val
    ];

    // Card 1 Detail: Targets per village (moo)
    $mooQuery = $pdo->prepare("SELECT moo, COUNT(*) as count FROM target_population WHERE hoscode IN ($inPlaceholders) GROUP BY moo ORDER BY moo");
    $mooQuery->execute($hoscodes);
    $targetsDetail = $mooQuery->fetchAll(PDO::FETCH_ASSOC);

    // Card 2 Detail: Screened cases risk distribution
    $screenedDetailQuery = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126 THEN 1 ELSE 0 END) as high_risk,
            SUM(CASE WHEN (s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125) THEN 1 ELSE 0 END) as risk,
            SUM(CASE WHEN s.sys_bp1 < 120 AND s.dia_bp1 < 80 AND s.dtx_value < 100 THEN 1 ELSE 0 END) as normal
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
    $mapHoscodes = array_unique(array_map(function($hc) {
        return ($hc === '10688') ? '10957' : $hc;
    }, $mapHoscodesRaw));

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
        SELECT p.hoscode, 
               COUNT(*) as total_targets,
               SUM(CASE WHEN a.assignment_status = 'completed' THEN 1 ELSE 0 END) as screened
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid
        WHERE p.hoscode IN ($inPlaceholders)
        GROUP BY p.hoscode
    ");
    $chartCoverageStmt->execute($hoscodes);
    $chartCoverageData = $chartCoverageStmt->fetchAll(PDO::FETCH_ASSOC);

    $chartRiskStmt = $pdo->prepare("
        SELECT p.hoscode,
               SUM(CASE WHEN s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126 THEN 1 ELSE 0 END) as high_risk,
               SUM(CASE WHEN (s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125) THEN 1 ELSE 0 END) as moderate_risk,
               SUM(CASE WHEN s.sys_bp1 < 120 AND s.dia_bp1 < 80 AND s.dtx_value < 100 THEN 1 ELSE 0 END) as normal
        FROM target_population p
        JOIN task_assignments a ON p.cid = a.target_cid AND a.assignment_status = 'completed'
        JOIN screening_results s ON a.assignment_id = s.assignment_id
        WHERE p.hoscode IN ($inPlaceholders)
        GROUP BY p.hoscode
    ");
    $chartRiskStmt->execute($hoscodes);
    $chartRiskData = $chartRiskStmt->fetchAll(PDO::FETCH_ASSOC);

    $chartDiseaseStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND s.dtx_value < 126 THEN 1 ELSE 0 END) as ht_only,
            SUM(CASE WHEN s.sys_bp1 < 140 AND s.dia_bp1 < 90 AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as dm_only,
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as ht_dm,
            SUM(CASE WHEN s.sys_bp1 < 140 AND s.dia_bp1 < 90 AND s.dtx_value < 126 THEN 1 ELSE 0 END) as normal
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id AND a.assignment_status = 'completed'
        JOIN target_population p ON a.target_cid = p.cid
        WHERE p.hoscode IN ($inPlaceholders)
    ");
    $chartDiseaseStmt->execute($hoscodes);
    $chartDiseaseData = $chartDiseaseStmt->fetch(PDO::FETCH_ASSOC);

    $chartTrendStmt = $pdo->prepare("
        SELECT DATE(s.created_at) as screen_date, COUNT(*) as daily_count
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id AND a.assignment_status = 'completed'
        JOIN target_population p ON a.target_cid = p.cid
        WHERE p.hoscode IN ($inPlaceholders)
          AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        GROUP BY DATE(s.created_at)
        ORDER BY screen_date ASC
    ");
    $chartTrendStmt->execute($hoscodes);
    $chartTrendData = $chartTrendStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $metricsQuery = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM target_population) as total_targets,
            (SELECT COUNT(*) FROM task_assignments WHERE assignment_status = 'completed') as screened_count,
            (SELECT COUNT(*) FROM task_assignments WHERE assignment_status = 'pending') as pending_count,
            (SELECT COUNT(*) FROM task_assignments WHERE assignment_status = 'skipped') as skipped_count,
            (SELECT SUM(points_earned) FROM vhv_rewards) as total_points,
            (SELECT COUNT(*) FROM vhv_users) as total_vhvs
    ");
    $metrics = $metricsQuery->fetch();

    // Card 1 Detail: Targets per village (moo)
    $targetsDetail = $pdo->query("SELECT moo, COUNT(*) as count FROM target_population GROUP BY moo ORDER BY moo")->fetchAll(PDO::FETCH_ASSOC);

    // Card 2 Detail: Screened cases risk distribution
    $screenedDetail = $pdo->query("
        SELECT 
            SUM(CASE WHEN s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126 THEN 1 ELSE 0 END) as high_risk,
            SUM(CASE WHEN (s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125) THEN 1 ELSE 0 END) as risk,
            SUM(CASE WHEN s.sys_bp1 < 120 AND s.dia_bp1 < 80 AND s.dtx_value < 100 THEN 1 ELSE 0 END) as normal
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        WHERE a.assignment_status = 'completed'
    ")->fetch(PDO::FETCH_ASSOC);

    // Card 3 Detail: Skipped reasons
    $skippedDetail = $pdo->query("
        SELECT s.skipped_reason, COUNT(*) as count 
        FROM screening_results s 
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        WHERE a.assignment_status = 'skipped'
        GROUP BY s.skipped_reason
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Card 4 Detail: Top VHVs by rewards
    $rewardsDetail = $pdo->query("
        SELECT v.vhv_name, SUM(r.points_earned) as total_points
        FROM vhv_rewards r
        JOIN vhv_users v ON r.vhv_id = v.vhv_id
        GROUP BY v.vhv_id
        ORDER BY total_points DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    $heatmapQuery = $pdo->query("
        SELECT p.cid, p.latitude, p.longitude, p.house_no, p.moo, p.sub_district_code, p.hoscode,
               p.first_name, p.last_name, p.health_status_origin,
               s.sys_bp1, s.dia_bp1, s.dtx_value, s.cv_risk_score, s.bmi
        FROM target_population p
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND a.assignment_status = 'completed'
        LEFT JOIN screening_results s ON s.assignment_id = a.assignment_id
        WHERE p.latitude IS NOT NULL 
          AND p.longitude IS NOT NULL
    ");
    $allMapTargets = $heatmapQuery->fetchAll(PDO::FETCH_ASSOC);

    // Get unique hoscodes with data for filter buttons
    $mapHoscodesRaw = $pdo->query("
        SELECT DISTINCT hoscode FROM target_population 
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        ORDER BY hoscode
    ")->fetchAll(PDO::FETCH_COLUMN);
    $mapHoscodes = array_unique(array_map(function($hc) {
        return ($hc === '10688') ? '10957' : $hc;
    }, $mapHoscodesRaw));

    // For coordinate editing: get all targets
    $editableTargets = $pdo->query("
        SELECT cid, first_name, last_name, house_no, moo, sub_district_code, hoscode, latitude, longitude
        FROM target_population 
        ORDER BY moo, house_no
    ")->fetchAll(PDO::FETCH_ASSOC);

    // --- NEW CHARTS DATA (SUPER ADMIN) ---
    $chartCoverageData = $pdo->query("
        SELECT p.hoscode, 
               COUNT(*) as total_targets,
               SUM(CASE WHEN a.assignment_status = 'completed' THEN 1 ELSE 0 END) as screened
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid
        GROUP BY p.hoscode
    ")->fetchAll(PDO::FETCH_ASSOC);

    $chartRiskData = $pdo->query("
        SELECT p.hoscode,
               SUM(CASE WHEN s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126 THEN 1 ELSE 0 END) as high_risk,
               SUM(CASE WHEN (s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125) THEN 1 ELSE 0 END) as moderate_risk,
               SUM(CASE WHEN s.sys_bp1 < 120 AND s.dia_bp1 < 80 AND s.dtx_value < 100 THEN 1 ELSE 0 END) as normal
        FROM target_population p
        JOIN task_assignments a ON p.cid = a.target_cid AND a.assignment_status = 'completed'
        JOIN screening_results s ON a.assignment_id = s.assignment_id
        GROUP BY p.hoscode
    ")->fetchAll(PDO::FETCH_ASSOC);

    $chartDiseaseData = $pdo->query("
        SELECT 
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND s.dtx_value < 126 THEN 1 ELSE 0 END) as ht_only,
            SUM(CASE WHEN s.sys_bp1 < 140 AND s.dia_bp1 < 90 AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as dm_only,
            SUM(CASE WHEN (s.sys_bp1 >= 140 OR s.dia_bp1 >= 90) AND s.dtx_value >= 126 THEN 1 ELSE 0 END) as ht_dm,
            SUM(CASE WHEN s.sys_bp1 < 140 AND s.dia_bp1 < 90 AND s.dtx_value < 126 THEN 1 ELSE 0 END) as normal
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id AND a.assignment_status = 'completed'
    ")->fetch(PDO::FETCH_ASSOC);

    $chartTrendData = $pdo->query("
        SELECT DATE(s.created_at) as screen_date, COUNT(*) as daily_count
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id AND a.assignment_status = 'completed'
        WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        GROUP BY DATE(s.created_at)
        ORDER BY screen_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
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
    <div class="admin-navbar">
        <a href="index.php" class="admin-logo">NCDs Prevention Portal - Tansum</a>
        <div class="admin-nav-links">
            <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" data-tooltip="แดชบอร์ด">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            </a>
            <?php if (!$admin_hoscode): ?>
                <a href="import_hdc.php" class="<?= basename($_SERVER['PHP_SELF']) == 'import_hdc.php' ? 'active' : '' ?>" data-tooltip="นำเข้าข้อมูล HDC">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                </a>
                <a href="process_etl.php" class="<?= basename($_SERVER['PHP_SELF']) == 'process_etl.php' ? 'active' : '' ?>" data-tooltip="ประมวลผล ETL">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                </a>
            <?php endif; ?>
            <a href="hdc_list.php" class="<?= basename($_SERVER['PHP_SELF']) == 'hdc_list.php' ? 'active' : '' ?>" data-tooltip="คัดกรองความเสี่ยง HDC">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </a>
            <a href="dpac_manager.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dpac_manager.php' ? 'active' : '' ?>" data-tooltip="จัดการโครงการ DPAC">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </a>
            <a href="assignment.php" class="<?= basename($_SERVER['PHP_SELF']) == 'assignment.php' ? 'active' : '' ?>" data-tooltip="มอบหมายงาน อสม.">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
            </a>
            <a href="print_qr.php" class="<?= basename($_SERVER['PHP_SELF']) == 'print_qr.php' ? 'active' : '' ?>" data-tooltip="พิมพ์ QR Code บ้าน">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
            </a>
            <a href="vhv_approval.php" class="<?= basename($_SERVER['PHP_SELF']) == 'vhv_approval.php' ? 'active' : '' ?>" data-tooltip="จัดการผู้ใช้ อสม.">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </a>
            <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" data-tooltip="ข้อมูลส่วนตัว / เปลี่ยนรหัสผ่าน">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </a>
            <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" data-tooltip="รายงานและการพิมพ์">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </a>
            <a href="../logout.php" data-tooltip="ออกจากระบบ" style="color: var(--color-red) !important;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </a>
        </div>
    </div>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="margin-bottom: 4px;">ภาพรวมความคุ้มครองและพิกัดกลุ่มเสี่ยง (Dashboard)</h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 15px;">
            หน่วยบริการผู้รับผิดชอบ: <strong style="color: var(--color-accent);"><?= htmlspecialchars($admin_title) ?></strong>
        </p>

        <!-- Metrics Grid -->
        <div class="grid-cols-4" style="margin-bottom: 30px;">
            <div class="card-dark" style="cursor: pointer;" onclick="showCardModal('targets')">
                <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">ประชากรเป้าหมาย</span>
                <div class="stat-val"><?= number_format($metrics['total_targets']) ?> <span style="font-size: 16px; color: var(--text-secondary);">ราย</span></div>
                <div style="margin-top: 10px; font-size: 13px; color: var(--text-muted);">
                    นำเข้าข้อมูลคัดกรองเบื้องต้นจาก HDC (คลิกดูรายละเอียด)
                </div>
            </div>

            <div class="card-dark" style="cursor: pointer;" onclick="showCardModal('screened')">
                <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">คัดกรองเสร็จสิ้น</span>
                <div class="stat-val" style="color: var(--color-green);"><?= number_format($metrics['screened_count']) ?> <span style="font-size: 16px; color: var(--text-secondary);">ราย</span></div>
                <div style="margin-top: 10px; font-size: 13px; color: var(--text-muted);">
                    คิดเป็น <?= $metrics['total_targets'] > 0 ? round(($metrics['screened_count'] / $metrics['total_targets']) * 100, 1) : 0 ?>% ของเป้าหมาย (คลิกดูรายละเอียด)
                </div>
            </div>

            <div class="card-dark" style="cursor: pointer;" onclick="showCardModal('skipped')">
                <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">เลื่อน/ข้ามสะสม (Skipped)</span>
                <div class="stat-val" style="color: var(--color-yellow);"><?= number_format($metrics['skipped_count']) ?> <span style="font-size: 16px; color: var(--text-secondary);">ราย</span></div>
                <div style="margin-top: 10px; font-size: 13px; color: var(--text-muted);">
                    พักไว้เพื่อสแกนตรวจสอบซ้ำภายหลัง (คลิกดูรายละเอียด)
                </div>
            </div>

            <div class="card-dark" style="cursor: pointer;" onclick="showCardModal('rewards')">
                <span style="color: var(--text-secondary); font-size: 14px; font-weight: bold;">แต้มรางวัลสะสม อสม.</span>
                <div class="stat-val" style="color: var(--color-primary);"><?= number_format($metrics['total_points'] ?? 0) ?> <span style="font-size: 16px; color: var(--text-secondary);">แต้ม</span></div>
                <div style="margin-top: 10px; font-size: 13px; color: var(--text-muted);">
                    จาก อสม. ผู้ปฏิบัติงานทั้งหมด <?= $metrics['total_vhvs'] ?> คน (คลิกดูบอร์ดคะแนน)
                </div>
            </div>
        </div>

        <!-- Analytics Dashboard Section -->
        <div class="grid-cols-2" style="margin-bottom: 30px; gap: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));">
            <!-- Chart 1: Coverage -->
            <div class="card-dark">
                <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
                    ความครอบคลุมการคัดกรอง แยกตาม รพ.สต.
                </h3>
                <div id="chart-coverage"></div>
            </div>

            <!-- Chart 2: Risk Distribution -->
            <div class="card-dark">
                <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
                    ระดับความเสี่ยงประชากร แยกตาม รพ.สต.
                </h3>
                <div id="chart-risk"></div>
            </div>

            <!-- Chart 3: Disease Breakdown -->
            <div class="card-dark">
                <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
                    สัดส่วนกลุ่มโรคที่สงสัย (จากผลคัดกรอง)
                </h3>
                <div id="chart-disease"></div>
            </div>

            <!-- Chart 4: Screening Trend -->
            <div class="card-dark">
                <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
                    แนวโน้มการคัดกรองรายวัน (14 วันล่าสุด)
                </h3>
                <div id="chart-trend"></div>
            </div>
        </div>

        <!-- Recent Screenings Table -->
        <div class="card-dark" style="margin-top: 30px;">
            <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px;">
                ผลการคัดกรองล่าสุดในพื้นที่
            </h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>เลขที่</th>
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
                            if ($admin_hoscode === '10957') {
                                $hoscodes[] = '10688';
                            }
                            $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
                            $recentScreenQuery = $pdo->prepare("
                                SELECT p.house_no, p.moo, p.sub_district_code, s.sys_bp1, s.dia_bp1, s.dtx_value, s.bmi, s.cv_risk_score, v.vhv_name, s.screening_lat, s.screening_lng, r.approval_status
                                FROM screening_results s
                                JOIN task_assignments a ON s.assignment_id = a.assignment_id
                                JOIN target_population p ON a.target_cid = p.cid
                                JOIN vhv_users v ON a.vhv_id = v.vhv_id
                                LEFT JOIN vhv_rewards r ON s.screening_id = r.screening_id
                                WHERE p.hoscode IN ($inPlaceholders)
                                ORDER BY s.created_at DESC LIMIT 10
                            ");
                            $recentScreenQuery->execute($hoscodes);
                            $recentScreens = $recentScreenQuery->fetchAll();
                        } else {
                            $recentScreenQuery = $pdo->query("
                                SELECT p.house_no, p.moo, p.sub_district_code, s.sys_bp1, s.dia_bp1, s.dtx_value, s.bmi, s.cv_risk_score, v.vhv_name, s.screening_lat, s.screening_lng, r.approval_status
                                FROM screening_results s
                                JOIN task_assignments a ON s.assignment_id = a.assignment_id
                                JOIN target_population p ON a.target_cid = p.cid
                                JOIN vhv_users v ON a.vhv_id = v.vhv_id
                                LEFT JOIN vhv_rewards r ON s.screening_id = r.screening_id
                                ORDER BY s.created_at DESC LIMIT 10
                            ");
                            $recentScreens = $recentScreenQuery->fetchAll();
                        }
                        if (empty($recentScreens)):
                        ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--text-secondary); padding: 24px;">ยังไม่มีข้อมูลผลการคัดกรองในระบบ</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentScreens as $rs): ?>
                                <tr>
                                    <td><?= htmlspecialchars($rs['house_no']) ?></td>
                                    <td><?= htmlspecialchars(get_village_only_name($rs['sub_district_code'], $rs['moo'])) ?></td>
                                    <td>หมู่ที่ <?= $rs['moo'] ?></td>
                                    <td>
                                        <?php if ($rs['sys_bp1'] >= 140 || $rs['dia_bp1'] >= 90): ?>
                                            <span style="color: var(--color-red); font-weight: bold;"><?= $rs['sys_bp1'] ?>/<?= $rs['dia_bp1'] ?></span>
                                        <?php else: ?>
                                            <?= $rs['sys_bp1'] ?>/<?= $rs['dia_bp1'] ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rs['dtx_value'] >= 126): ?>
                                            <span style="color: var(--color-red); font-weight: bold;"><?= $rs['dtx_value'] ?></span>
                                        <?php else: ?>
                                            <?= $rs['dtx_value'] ?? '-' ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $rs['bmi'] ?></td>
                                    <td>
                                        <?php if ($rs['cv_risk_score'] >= 10): ?>
                                            <span style="background-color: rgba(239, 68, 68, 0.2); color: var(--color-red); padding: 4px 8px; border-radius: 4px; font-weight: bold;"><?= $rs['cv_risk_score'] ?>%</span>
                                        <?php else: ?>
                                            <?= $rs['cv_risk_score'] ?>%
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($rs['vhv_name']) ?></td>
                                    <td style="font-size: 13px; color: var(--text-secondary);">
                                        <?= round($rs['screening_lat'], 5) ?>, <?= round($rs['screening_lng'], 5) ?>
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
            <h2 style="color: var(--color-accent); border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px;">
                Geographic NCDs Hotspot Heatmap (แผนที่กลุ่มเสี่ยงสูง อำเภอตาลสุม)
            </h2>
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                แผนที่แสดงการกระจุกตัวของประชากรกลุ่มเป้าหมาย แบ่งตามระดับความเสี่ยง สามารถกรองตามกลุ่มเสี่ยง และเขตรับผิดชอบ รพ.สต. ได้
            </p>

            <!-- Filter Buttons: Risk Groups -->
            <div style="margin-bottom: 12px;">
                <span style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-right: 8px;">กรองตามกลุ่มเสี่ยง:</span>
                <div style="display: inline-flex; gap: 6px; flex-wrap: wrap;">
                    <button onclick="toggleRiskFilter('all')" id="btn-risk-all" class="map-filter-btn active" style="background: var(--color-primary); color: white; border: 2px solid var(--color-primary); padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.2s;">
                        🌐 ทั้งหมด
                    </button>
                    <button onclick="toggleRiskFilter('high')" id="btn-risk-high" class="map-filter-btn" style="background: transparent; color: var(--color-red); border: 2px solid var(--color-red); padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.2s;">
                        🔴 เสี่ยงสูง
                    </button>
                    <button onclick="toggleRiskFilter('moderate')" id="btn-risk-moderate" class="map-filter-btn" style="background: transparent; color: var(--color-yellow); border: 2px solid var(--color-yellow); padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.2s;">
                        🟡 เสี่ยงปานกลาง
                    </button>
                    <button onclick="toggleRiskFilter('normal')" id="btn-risk-normal" class="map-filter-btn" style="background: transparent; color: var(--color-green); border: 2px solid var(--color-green); padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.2s;">
                        🟢 ปกติ / ยังไม่คัดกรอง
                    </button>
                </div>
            </div>

            <!-- Filter Buttons: Service Area -->
            <div style="margin-bottom: 16px;">
                <span style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-right: 8px;">กรองตามเขต รพ.สต.:</span>
                <div style="display: inline-flex; gap: 6px; flex-wrap: wrap;">
                    <button onclick="toggleHosFilter('all')" id="btn-hos-all" class="map-filter-btn active" style="background: var(--color-accent); color: var(--bg-card); border: 2px solid var(--color-accent); padding: 5px 12px; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: bold; transition: all 0.2s;">
                        ทุกเขต
                    </button>
                    <?php foreach ($mapHoscodes as $hc): ?>
                        <button onclick="toggleHosFilter('<?= $hc ?>')" id="btn-hos-<?= $hc ?>" class="map-filter-btn" style="background: transparent; color: var(--color-accent); border: 2px solid var(--border-color); padding: 5px 12px; border-radius: 20px; cursor: pointer; font-size: 12px; transition: all 0.2s;">
                            <?= htmlspecialchars($hc_names[$hc] ?? $hc) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Map Counter -->
            <div id="map-counter" style="margin-bottom: 10px; font-size: 13px; color: var(--text-secondary);">
                📍 แสดง <strong id="visible-count">0</strong> จุด จากทั้งหมด <strong><?= count($allMapTargets) ?></strong> จุด
            </div>
            
            <div id="map"></div>

            <!-- Coordinate Editing Section -->
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <button onclick="toggleEditMode()" id="btn-edit-coords" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; border: none; padding: 8px 18px; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.3s; box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);">
                        📍 แก้ไขพิกัดบ้าน
                    </button>
                    <div id="edit-controls" style="display: none; flex: 1; min-width: 300px;">
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <select id="edit-target-select" onchange="onTargetSelected()" style="flex: 1; min-width: 250px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-darker); color: var(--text-primary); font-size: 13px;">
                                <option value="">-- เลือกเป้าหมายที่ต้องการแก้ไขพิกัด --</option>
                                <?php foreach ($editableTargets as $et): ?>
                                    <?php 
                                    $village_only = get_village_only_name($et['sub_district_code'], $et['moo']);
                                    $hasCoord = ($et['latitude'] && $et['longitude']) ? '✅' : '❌';
                                    ?>
                                    <option value="<?= htmlspecialchars($et['cid']) ?>" 
                                            data-lat="<?= $et['latitude'] ?>" 
                                            data-lng="<?= $et['longitude'] ?>">
                                        <?= $hasCoord ?> หมู่ <?= $et['moo'] ?> <?= htmlspecialchars($village_only) ?> - บ้านเลขที่ <?= htmlspecialchars($et['house_no']) ?> | <?= htmlspecialchars($et['first_name'] . ' ' . $et['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button onclick="saveNewCoordinate()" id="btn-save-coord" style="display: none; background: var(--color-green); color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: bold; white-space: nowrap;">
                                💾 บันทึกพิกัด
                            </button>
                            <button onclick="cancelEditMode()" style="background: transparent; color: var(--color-red); border: 1px solid var(--color-red); padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: bold; white-space: nowrap;">
                                ✕ ยกเลิก
                            </button>
                        </div>
                        <div id="edit-status" style="margin-top: 8px; font-size: 12px; color: var(--text-secondary);"></div>
                    </div>
                </div>
            </div>
        </div>

    <!-- ApexCharts Initialization -->
    <script>
        // Data from PHP
        const hcNamesChart = <?= json_encode($hc_names) ?>;
        
        // Coverage Data
        const coverageRaw = <?= json_encode($chartCoverageData) ?>;
        const covCategories = coverageRaw.map(d => hcNamesChart[d.hoscode] || d.hoscode);
        const covTotal = coverageRaw.map(d => parseInt(d.total_targets));
        const covScreened = coverageRaw.map(d => parseInt(d.screened));

        // Coverage Chart
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

        // Risk Data
        const riskRaw = <?= json_encode($chartRiskData) ?>;
        const riskCategories = riskRaw.map(d => hcNamesChart[d.hoscode] || d.hoscode);
        const riskNormal = riskRaw.map(d => parseInt(d.normal) || 0);
        const riskModerate = riskRaw.map(d => parseInt(d.moderate_risk) || 0);
        const riskHigh = riskRaw.map(d => parseInt(d.high_risk) || 0);

        // Risk Chart (100% Stacked)
        var optionsRisk = {
            series: [{
                name: 'ปกติ',
                data: riskNormal
            }, {
                name: 'เสี่ยงปานกลาง',
                data: riskModerate
            }, {
                name: 'เสี่ยงสูง',
                data: riskHigh
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
            colors: ['#22c55e', '#f59e0b', '#ef4444'],
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

        // Disease Data
        const diseaseRaw = <?= json_encode($chartDiseaseData) ?>;
        const diseaseSeries = [
            parseInt(diseaseRaw?.ht_only || 0), 
            parseInt(diseaseRaw?.dm_only || 0), 
            parseInt(diseaseRaw?.ht_dm || 0), 
            parseInt(diseaseRaw?.normal || 0)
        ];
        
        // Disease Chart (Donut)
        var optionsDisease = {
            series: diseaseSeries,
            chart: {
                type: 'donut',
                height: 350,
                background: 'transparent'
            },
            theme: { mode: 'dark' },
            labels: ['สงสัยความดัน (HT)', 'สงสัยเบาหวาน (DM)', 'สงสัยทั้ง HT และ DM', 'ปกติ'],
            colors: ['#3b82f6', '#8b5cf6', '#ec4899', '#22c55e'],
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

        // Trend Data
        const trendRaw = <?= json_encode($chartTrendData) ?>;
        const trendCategories = trendRaw.map(d => d.screen_date);
        const trendCounts = trendRaw.map(d => parseInt(d.daily_count));

        // Trend Chart (Area)
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
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: {
                categories: trendCategories,
                labels: {
                    style: { colors: '#9ca3af' },
                    formatter: function (val) {
                        if (!val) return '';
                        const parts = val.split('-');
                        if(parts.length < 3) return val;
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
        
        var riskColors = { high: '#ef4444', moderate: '#f59e0b', normal: '#22c55e' };
        var riskLabels = { high: '🔴 เสี่ยงสูง', moderate: '🟡 เสี่ยงปานกลาง', normal: '🟢 ปกติ' };
        
        // ============== MAP INIT ==============
        var map = L.map('map').setView([15.4294, 104.9922], 12);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
            maxZoom: 20
        }).addTo(map);
        
        // ============== MARKERS & LAYERS ==============
        var markers = [];
        var heatLayer = null;
        var currentRiskFilter = 'all';
        var currentHosFilter = 'all';
        
        function classifyPopupHTML(t) {
            var riskLabel = riskLabels[t.risk];
            var villageName = t.house_no ? 'บ้านเลขที่ ' + t.house_no : '';
            var html = '<div style="color: black; font-size: 13px; min-width: 200px;">';
            html += '<strong>' + villageName + ' หมู่ที่ ' + t.moo + '</strong><br>';
            html += '<span>' + (t.first_name || '') + ' ' + (t.last_name || '') + '</span><br>';
            html += '<span style="font-weight: bold;">' + riskLabel + '</span><br>';
            
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
            
            html += '<br><span style="color: #888; font-size: 11px;">รพ.สต.: ' + (hcNames[t.hoscode] || t.hoscode) + '</span>';
            html += '</div>';
            return html;
        }
        
        function buildMarkers() {
            // Clear existing
            markers.forEach(function(m) { map.removeLayer(m.marker); });
            markers = [];
            if (heatLayer) { map.removeLayer(heatLayer); heatLayer = null; }
            
            var heatPoints = [];
            var visibleCount = 0;
            
            allMapData.forEach(function(t) {
                if (!t.latitude || !t.longitude) return;
                
                // Apply filters
                var passRisk = (currentRiskFilter === 'all' || t.risk === currentRiskFilter);
                var passHos = (currentHosFilter === 'all' || t.hoscode === currentHosFilter || 
                               (currentHosFilter === '10957' && t.hoscode === '10688') || 
                               (currentHosFilter === '10688' && t.hoscode === '10957'));
                
                if (!passRisk || !passHos) return;
                
                visibleCount++;
                var lat = parseFloat(t.latitude);
                var lng = parseFloat(t.longitude);
                
                var color = riskColors[t.risk];
                var radius = t.risk === 'high' ? 7 : (t.risk === 'moderate' ? 5 : 4);
                var opacity = t.risk === 'high' ? 0.9 : 0.7;
                var intensity = t.risk === 'high' ? 1.0 : (t.risk === 'moderate' ? 0.6 : 0.3);
                
                heatPoints.push([lat, lng, intensity]);
                
                var marker = L.circleMarker([lat, lng], {
                    radius: radius,
                    fillColor: color,
                    color: '#fff',
                    weight: 1,
                    opacity: 1,
                    fillOpacity: opacity
                }).addTo(map).bindPopup(classifyPopupHTML(t));
                
                markers.push({ marker: marker, data: t });
            });
            
            // Update counter
            document.getElementById('visible-count').textContent = visibleCount;
            
            // Add heatmap layer
            if (heatPoints.length > 0) {
                heatLayer = L.heatLayer(heatPoints, {
                    radius: 25,
                    blur: 15,
                    maxZoom: 15,
                    gradient: {0.2: '#22c55e', 0.4: '#a3e635', 0.6: '#f59e0b', 0.8: '#f97316', 1.0: '#ef4444'}
                }).addTo(map);
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
            
            buildMarkers();
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
            
            buildMarkers();
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
        
        map.on('click', function(e) {
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
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(pendingCoord)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    document.getElementById('edit-status').innerHTML = '<span style="color: var(--color-green); font-weight: bold;">✅ ' + data.message + '</span>';
                    
                    // Update local data
                    var found = allMapData.find(function(t) { return t.cid === pendingCoord.cid; });
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
                    
                    buildMarkers();
                    
                    if (editMarker) { map.removeLayer(editMarker); editMarker = null; }
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
        buildMarkers();
    </script>
    
    <style>
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>

    <!-- Details Modal Placeholder -->
    <div id="details-modal" onclick="if(event.target === this) closeDetailsModal()" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(13, 44, 84, 0.4); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center;">
        <div class="card-dark" style="width: 90%; max-width: 500px; padding: 24px; max-height: 80vh; overflow-y: auto;">
            <h3 id="modal-title" style="color: var(--color-accent); border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px;">รายละเอียด</h3>
            <div id="modal-body-content" style="margin-bottom: 24px;"></div>
            <button onclick="closeDetailsModal()" class="btn-primary" style="width: 100%; height: 50px; border-radius: 25px; border: none; background: var(--color-primary); color: white; font-weight: bold; cursor: pointer;">ปิดหน้าต่าง</button>
        </div>
    </div>

    <!-- Metric Card Modal JS -->
    <script>
        var targetsDetail = <?= json_encode($targetsDetail) ?>;
        var screenedDetail = <?= json_encode($screenedDetail) ?>;
        var skippedDetail = <?= json_encode($skippedDetail) ?>;
        var rewardsDetail = <?= json_encode($rewardsDetail) ?>;

        function showCardModal(type) {
            var title = '';
            var html = '';

            if (type === 'targets') {
                title = '📊 จำนวนเป้าหมายแยกรายหมู่บ้าน';
                html = '<table class="admin-table"><thead><tr><th>หมู่ที่</th><th style="text-align: right;">จำนวนเป้าหมาย (ราย)</th></tr></thead><tbody>';
                if (targetsDetail.length === 0) {
                    html += '<tr><td colspan="2" style="text-align: center;">ไม่มีข้อมูล</td></tr>';
                } else {
                    targetsDetail.forEach(function(row) {
                        html += '<tr><td>หมู่ที่ ' + row.moo + '</td><td style="text-align: right; font-weight: bold;">' + Number(row.count).toLocaleString() + ' ราย</td></tr>';
                    });
                }
                html += '</tbody></table>';
            } else if (type === 'screened') {
                title = '🟢 ผลการคัดกรองเสร็จสิ้นแยกกลุ่มเสี่ยง';
                var high = Number(screenedDetail.high_risk || 0);
                var risk = Number(screenedDetail.risk || 0);
                var normal = Number(screenedDetail.normal || 0);
                var total = high + risk + normal;

                html = '<table class="admin-table"><tbody>' +
                       '<tr><td>🔴 กลุ่มเสี่ยงสูง (High Risk)</td><td style="text-align: right; font-weight: bold; color: var(--color-red);">' + high.toLocaleString() + ' ราย (' + (total > 0 ? Math.round(high/total*100) : 0) + '%)</td></tr>' +
                       '<tr><td>🟡 กลุ่มเสี่ยง (Moderate Risk)</td><td style="text-align: right; font-weight: bold; color: var(--color-yellow);">' + risk.toLocaleString() + ' ราย (' + (total > 0 ? Math.round(risk/total*100) : 0) + '%)</td></tr>' +
                       '<tr><td>🟢 กลุ่มปกติ (Normal)</td><td style="text-align: right; font-weight: bold; color: var(--color-green);">' + normal.toLocaleString() + ' ราย (' + (total > 0 ? Math.round(normal/total*100) : 0) + '%)</td></tr>' +
                       '<tr style="font-weight: bold; background-color: var(--bg-darker);"><td>รวมคัดกรองเสร็จสิ้น</td><td style="text-align: right;">' + total.toLocaleString() + ' ราย</td></tr>' +
                       '</tbody></table>';
            } else if (type === 'skipped') {
                title = '⚠️ สาเหตุที่กดข้าม / เลื่อนตรวจสะสม';
                html = '<table class="admin-table"><thead><tr><th>เหตุผล</th><th style="text-align: right;">จำนวนเคส (ราย)</th></tr></thead><tbody>';
                if (skippedDetail.length === 0) {
                    html += '<tr><td colspan="2" style="text-align: center;">ไม่มีเคสถูกข้าม</td></tr>';
                } else {
                    skippedDetail.forEach(function(row) {
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
                    rewardsDetail.forEach(function(row) {
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
