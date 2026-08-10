<?php
// api/get_vhv_tasks.php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json; charset=utf-8');

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
        WHERE a.vhv_id = ? AND a.budget_year = 2026 AND a.is_sandbox = ?
        
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
        WHERE f.vhv_id = ? AND f.is_sandbox = ?
        
        ORDER BY CAST(house_no AS UNSIGNED) ASC, house_no ASC, cid ASC, round_number ASC
    ");
    $tStmt->execute([$vhvId, $isSandboxVal, $vhvId, $isSandboxVal]);
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
