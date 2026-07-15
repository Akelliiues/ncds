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
            NULL AS round_number,
            NULL AS risk_type,
            p.cid, p.first_name, p.last_name, p.house_no, p.moo,
            TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) AS age
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.vhv_id = ? AND a.budget_year = 2026 AND a.is_sandbox = ?
          AND (
              (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
              OR 
              (p.need_screen_dm = 0 AND p.need_screen_ht = 0 AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
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
            TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) AS age
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        JOIN target_population p ON e.cid = p.cid
        WHERE f.vhv_id = ? AND f.is_sandbox = ?
        
        ORDER BY CAST(house_no AS UNSIGNED) ASC, house_no ASC
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
