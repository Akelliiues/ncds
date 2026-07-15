<?php
// api/submit_survey.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['vhv_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึงข้อมูล']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$vhvId = $_SESSION['vhv_id'];

// Retrieve and decode input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit();
}

$score_peou = intval($input['peou'] ?? 0);
$score_sq = intval($input['sq'] ?? 0);
$score_iq = intval($input['iq'] ?? 0);
$score_pu = intval($input['pu'] ?? 0);
$score_bi = intval($input['bi'] ?? 0);
$tags = $input['tags'] ?? [];

// Validate ratings
if ($score_peou < 1 || $score_peou > 5 ||
    $score_sq < 1 || $score_sq > 5 ||
    $score_iq < 1 || $score_iq > 5 ||
    $score_pu < 1 || $score_pu > 5 ||
    $score_bi < 1 || $score_bi > 5) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกคะแนนประเมินให้ครบทุกช่อง (1-5 คะแนน)']);
    exit();
}

try {
    // Query current VHV info (vhv_name, hoscode and vhid_code)
    $vhvQuery = $pdo->prepare("SELECT vhv_name, hoscode, vhid_code FROM vhv_users WHERE vhv_id = ?");
    $vhvQuery->execute([$vhvId]);
    $vhvInfo = $vhvQuery->fetch(PDO::FETCH_ASSOC);
    
    if (!$vhvInfo) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล อสม. ในระบบ']);
        exit();
    }
    
    $hoscode = $vhvInfo['hoscode'];
    $sub_district_code = substr($vhvInfo['vhid_code'], 0, 6);
    
    // Check if sandbox mode
    $isSandboxVal = 0;
    if (function_exists('isSandboxMode') && isSandboxMode($hoscode)) {
        $isSandboxVal = 1;
    }
    
    $pdo->beginTransaction();
    
    // 1. Check duplicate participation
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM vhv_survey_participants WHERE vhv_id = ? AND budget_year = 2026");
    $checkStmt->execute([$vhvId]);
    if ($checkStmt->fetchColumn() > 0) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'คุณได้ทำการประเมินความพึงพอใจประจำปีงบประมาณนี้เรียบร้อยแล้ว']);
        exit();
    }
    
    // 2. Register participant
    $participantStmt = $pdo->prepare("INSERT INTO vhv_survey_participants (vhv_id, budget_year) VALUES (?, 2026)");
    $participantStmt->execute([$vhvId]);
    
    // 3. Insert anonymous response
    $selected_tags = json_encode($tags, JSON_UNESCAPED_UNICODE);
    $surveyStmt = $pdo->prepare("
        INSERT INTO vhv_surveys (hoscode, sub_district_code, score_peou, score_sq, score_iq, score_pu, score_bi, selected_tags, budget_year, is_sandbox)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 2026, ?)
    ");
    $surveyStmt->execute([
        $hoscode,
        $sub_district_code,
        $score_peou,
        $score_sq,
        $score_iq,
        $score_pu,
        $score_bi,
        $selected_tags,
        $isSandboxVal
    ]);
    
    // 4. Award 5.00 points to VHV
    $rewardStmt = $pdo->prepare("
        INSERT INTO vhv_rewards (vhv_id, points_earned, approval_status, approved_at, is_sandbox)
        VALUES (?, 5.00, 'approved', NOW(), ?)
    ");
    $rewardStmt->execute([$vhvId, $isSandboxVal]);
    
    
    // 5. Log activity to security log
    $logStmt = $pdo->prepare("
        INSERT INTO scan_security_log (logged_at, vhv_id, vhv_name, hoscode, scanned_code, incident_type, ip_address, user_agent)
        VALUES (NOW(), ?, ?, ?, 'SURVEY_2026', 'SATISFACTION_SURVEY', ?, ?)
    ");
    $logStmt->execute([
        $vhvId,
        $vhvInfo['vhv_name'],
        $hoscode,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'ขอบคุณสำหรับข้อเสนอแนะ! ระบบได้เพิ่มแต้มสะสมพิเศษให้คุณเรียบร้อย 🏆'
    ]);
    
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()]);
}
