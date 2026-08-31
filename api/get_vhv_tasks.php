<?php
// api/get_vhv_tasks.php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/demo_data.php';

if (DemoDataProvider::isDemoMode()) {
    $allTargets = DemoDataProvider::getMockTargets();
    $mockTasks = [];
    foreach ($allTargets as $idx => $t) {
        $isCompleted = ($idx >= 3);
        $mockTasks[] = [
            'task_type' => ($idx === 2 ? 'dpac' : 'screen'),
            'task_id' => 'DEMO_TASK_' . ($idx + 1),
            'assignment_status' => ($isCompleted ? 'completed' : 'pending'),
            'is_sandbox' => 1,
            'assigned_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'round_number' => $t['round_number'] ?? 1,
            'risk_type' => ($idx === 2 ? 'BOTH' : null),
            'cid' => $t['cid'],
            'first_name' => $t['first_name'],
            'last_name' => $t['last_name'],
            'house_no' => $t['house_no'],
            'moo' => $t['moo'],
            'age' => $t['age'],
            'screening_id' => ($isCompleted ? 'DEMO_SCR_' . ($idx + 1) : null),
            'sys_bp1' => $t['last_sbp'] ?? 120,
            'dia_bp1' => $t['last_dbp'] ?? 80,
            'sys_bp2' => null,
            'dia_bp2' => null,
            'dtx_value' => $t['last_dtx'] ?? 100,
            'dtx_type' => $t['last_dtx_type'] ?? 'fpg',
            'weight' => 62.5,
            'height' => 165,
            'waist' => 78,
            'bmi' => 22.95,
            'cv_risk_score' => ($idx % 3 == 0 ? 12.5 : 4.2),
            'diet_risk' => 1,
            'exercise_risk' => 0,
            'stress_risk' => 0,
            'smoking_risk' => 0,
            'alcohol_risk' => 0,
            'skipped_reason' => null,
            'advice_given' => 'แนะนำปรับพฤติกรรม 3อ. 2ส. และลดอาหารเค็ม',
            'screened_at' => ($isCompleted ? date('Y-m-d H:i:s', strtotime('-1 days')) : null),
            'screening_lat' => 15.4325,
            'screening_lng' => 104.9815,
            'prev_sys_bp1' => 135,
            'prev_dia_bp1' => 85,
            'prev_dtx_value' => 110,
            'prev_round_number' => 1
        ];
    }
    echo json_encode([
        'status' => 'success',
        'vhv_name' => 'อสม. จำลอง (โหมดทดสอบ)',
        'is_sandbox' => 1,
        'tasks' => $mockTasks
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$vhvId = $_GET['vhv_id'] ?? '';
if (empty($vhvId)) {
    echo json_encode([]);
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

try {
    // 1. Fetch VHV info to verify hoscode authority and get active sandbox mode
    $vStmt = $pdo->prepare("SELECT vhv_name, hoscode FROM vhv_users WHERE vhv_id = ?");
    $vStmt->execute([$vhvId]);
    $vhv = $vStmt->fetch(PDO::FETCH_ASSOC);

    if (!$vhv) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล อสม.']);
        exit();
    }

    if ($admin_hoscode && $vhv['hoscode'] !== $admin_hoscode) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึงข้อมูล อสม. นอกสังกัด']);
        exit();
    }

    $isSandboxVal = isSandboxMode($vhv['hoscode']) ? 1 : 0;
    $currentBudgetYear = isset($_GET['budget_year']) && is_numeric($_GET['budget_year']) 
        ? (int)$_GET['budget_year'] 
        : (function_exists('get_current_budget_year') ? get_current_budget_year() : 2026);

    // 2. Fetch assigned tasks (UNION NCD screenings and DPAC followups)
    $tStmt = $pdo->prepare("
        SELECT 
            'screen' AS task_type,
            a.assignment_id AS task_id, 
            a.assignment_status, 
            a.is_sandbox, 
            a.assigned_at,
            a.round_number,
            NULL AS risk_type,
            p.cid, p.first_name, p.last_name, p.house_no, p.moo,
            TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) AS age,
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35 AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1) THEN 1
                WHEN TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) < 35 AND (COALESCE(p.is_manual, 0) = 1 OR p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')) THEN 1
                WHEN sr.screening_id IS NOT NULL THEN 1
                ELSE 0
            END AS is_valid_target,
            sr.screening_id,
            sr.sys_bp1, sr.dia_bp1, sr.sys_bp2, sr.dia_bp2,
            sr.dtx_value, sr.dtx_type,
            sr.weight, sr.height, sr.waist, sr.bmi,
            sr.cv_risk_score,
            sr.diet_risk, sr.exercise_risk, sr.stress_risk, sr.smoking_risk, sr.alcohol_risk,
            sr.skipped_reason,
            sr.advice_given,
            sr.created_at AS screened_at,
            sr.screening_lat, sr.screening_lng,
            (SELECT sr_prev.sys_bp1 FROM screening_results sr_prev LEFT JOIN task_assignments ta_prev ON sr_prev.assignment_id = ta_prev.assignment_id WHERE (sr_prev.target_cid = p.cid OR ta_prev.target_cid = p.cid) ORDER BY sr_prev.created_at DESC, sr_prev.screening_id DESC LIMIT 1) AS prev_sys_bp1,
            (SELECT sr_prev.dia_bp1 FROM screening_results sr_prev LEFT JOIN task_assignments ta_prev ON sr_prev.assignment_id = ta_prev.assignment_id WHERE (sr_prev.target_cid = p.cid OR ta_prev.target_cid = p.cid) ORDER BY sr_prev.created_at DESC, sr_prev.screening_id DESC LIMIT 1) AS prev_dia_bp1,
            (SELECT sr_prev.dtx_value FROM screening_results sr_prev LEFT JOIN task_assignments ta_prev ON sr_prev.assignment_id = ta_prev.assignment_id WHERE (sr_prev.target_cid = p.cid OR ta_prev.target_cid = p.cid) ORDER BY sr_prev.created_at DESC, sr_prev.screening_id DESC LIMIT 1) AS prev_dtx_value,
            (SELECT IFNULL(sr_prev.round_number, ta_prev.round_number) FROM screening_results sr_prev LEFT JOIN task_assignments ta_prev ON sr_prev.assignment_id = ta_prev.assignment_id WHERE (sr_prev.target_cid = p.cid OR ta_prev.target_cid = p.cid) ORDER BY sr_prev.created_at DESC, sr_prev.screening_id DESC LIMIT 1) AS prev_round_number
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        LEFT JOIN screening_results sr ON a.assignment_id = sr.assignment_id
        LEFT JOIN vhv_users v ON a.vhv_id = v.vhv_id
        WHERE a.vhv_id = ? AND a.budget_year = ?
          AND (
              ((p.need_screen_dm = 1 OR p.need_screen_ht = 1) AND (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35 OR COALESCE(p.is_manual, 0) = 1))
              OR p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
              OR COALESCE(p.is_manual, 0) = 1
              OR sr.screening_id IS NOT NULL
          )
          AND (
              sr.screening_id IS NOT NULL
              OR v.vhv_moo IS NULL OR v.vhv_moo = '' OR p.moo IS NULL OR p.moo = ''
              OR CAST(p.moo AS UNSIGNED) = CAST(v.vhv_moo AS UNSIGNED)
              OR p.vhid_code = v.vhid_code
          )
        
        UNION ALL
        
        SELECT 
            'dpac' AS task_type,
            f.followup_id AS task_id, 
            f.status AS assignment_status, 
            f.is_sandbox, 
            f.assigned_at,
            f.round_number,
            e.risk_type,
            p.cid, p.first_name, p.last_name, p.house_no, p.moo,
            TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) AS age,
            1 AS is_valid_target,
            NULL AS screening_id,
            f.bp_sys AS sys_bp1, f.bp_dia AS dia_bp1, NULL AS sys_bp2, NULL AS dia_bp2,
            f.fbs AS dtx_value, 'fpg' AS dtx_type,
            f.weight, f.height, f.waist, NULL AS bmi,
            NULL AS cv_risk_score,
            NULL AS diet_risk, NULL AS exercise_risk, NULL AS stress_risk, NULL AS smoking_risk, NULL AS alcohol_risk,
            f.skipped_reason,
            f.advice_given,
            f.completed_at AS screened_at,
            NULL AS screening_lat, NULL AS screening_lng,
            NULL AS prev_sys_bp1, NULL AS prev_dia_bp1, NULL AS prev_dtx_value, NULL AS prev_round_number
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        JOIN target_population p ON e.cid = p.cid
        WHERE f.vhv_id = ?
        
        ORDER BY CAST(house_no AS UNSIGNED) ASC, house_no ASC, cid ASC, round_number ASC
    ");
    $tStmt->execute([$vhvId, $currentBudgetYear, $vhvId]);
    $tasks = $tStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'vhv_name' => $vhv['vhv_name'],
        'is_sandbox' => $isSandboxVal,
        'tasks' => $tasks
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
