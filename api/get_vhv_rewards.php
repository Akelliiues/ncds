<?php
// api/get_vhv_rewards.php
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$vhv_id = $_GET['vhv_id'] ?? '';

if (empty($vhv_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing vhv_id parameter.']);
    exit();
}

try {
    // Check if the VHV exists
    $vhvStmt = $pdo->prepare("SELECT vhv_name, vhv_moo, hoscode FROM vhv_users WHERE vhv_id = ?");
    $vhvStmt->execute([$vhv_id]);
    $vhvInfo = $vhvStmt->fetch();

    if (!$vhvInfo) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'VHV not found.']);
        exit();
    }

    // Dynamic hospital name mapping
    $hospitals = [
        '10957' => 'โรงพยาบาลตาลสุม',
        '03751' => 'รพ.สต. ดอนพันชาด',
        '03752' => 'รพ.สต. สำโรง',
        '03753' => 'รพ.สต. จิกเทิง',
        '03754' => 'รพ.สต. หนองกุง',
        '03755' => 'รพ.สต. นาคาย',
        '03756' => 'รพ.สต. บ้านคำหนามแท่ง',
        '03757' => 'รพ.สต. คำหว้า'
    ];
    $vhvInfo['hospital_name'] = $hospitals[$vhvInfo['hoscode']] ?? 'ไม่ระบุสังกัด';

    // Query rewards list (requiring active task assignments/followups to filter out cancelled/deleted ones)
    $stmt = $pdo->prepare("
        SELECT 
            r.reward_id,
            r.points_earned,
            r.created_at,
            t.first_name,
            t.last_name,
            t.cid,
            'screening' as activity_type
        FROM vhv_rewards r
        JOIN task_assignments a ON r.assignment_id = a.assignment_id
        LEFT JOIN screening_results s ON r.screening_id = s.screening_id
        LEFT JOIN target_population t ON a.target_cid = t.cid
        WHERE r.vhv_id = ? AND r.approval_status IN ('approved', 'waiting')
          AND r.followup_id IS NULL

        UNION ALL

        SELECT 
            r.reward_id,
            r.points_earned,
            r.created_at,
            t.first_name,
            t.last_name,
            t.cid,
            'dpac' as activity_type
        FROM vhv_rewards r
        JOIN dpac_followups f ON r.followup_id = f.followup_id
        LEFT JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        LEFT JOIN target_population t ON e.cid = t.cid
        WHERE r.vhv_id = ? AND r.approval_status IN ('approved', 'waiting')
          AND r.followup_id IS NOT NULL

        ORDER BY created_at DESC
    ");

    $stmt->execute([$vhv_id, $vhv_id]);
    $rewards = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'vhv' => $vhvInfo,
        'rewards' => $rewards
    ], JSON_UNESCAPED_UNICODE);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
