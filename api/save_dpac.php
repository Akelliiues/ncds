<?php
// api/save_dpac.php
require_once __DIR__ . '/../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['vhv_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'เข้าสู่ระบบหมดอายุ กรุณาเข้าสู่ระบบใหม่'
    ]);
    exit();
}

$vhvId = $_SESSION['vhv_id'];
$fid = (int)($_POST['followup_id'] ?? 0);

if ($fid <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ข้อมูลรหัสติดตามไม่ถูกต้อง'
    ]);
    exit();
}

$weight = $_POST['weight'] !== '' ? (float)$_POST['weight'] : null;
$height = $_POST['height'] !== '' ? (float)$_POST['height'] : null;
$waist = $_POST['waist'] !== '' ? (float)$_POST['waist'] : null;
$fbs = ($_POST['fbs'] !== null && $_POST['fbs'] !== '') ? (int)$_POST['fbs'] : null;
$sbp = ($_POST['bp_sys'] !== null && $_POST['bp_sys'] !== '') ? (int)$_POST['bp_sys'] : null;
$dbp = ($_POST['bp_dia'] !== null && $_POST['bp_dia'] !== '') ? (int)$_POST['bp_dia'] : null;
$healthRisk = $_POST['health_risk_level'] ?? '';
$advice = $_POST['advice_given'] ?? '';

$pdo->beginTransaction();
try {
    $updateStmt = $pdo->prepare("
        UPDATE dpac_followups 
        SET status = 'completed', completed_at = CURRENT_TIMESTAMP,
            weight = ?, height = ?, waist = ?,
            fbs = ?, bp_sys = ?, bp_dia = ?,
            health_risk_level = ?, advice_given = ?
        WHERE followup_id = ? AND vhv_id = ?
    ");
    $updateStmt->execute([$weight, $height, $waist, $fbs, $sbp, $dbp, $healthRisk, $advice, $fid, $vhvId]);

    // Insert reward point (+1 point) for DPAC followup completion
    $checkReward = $pdo->prepare("SELECT COUNT(*) FROM vhv_rewards WHERE vhv_id = ? AND followup_id = ?");
    $checkReward->execute([$vhvId, $fid]);
    if ($checkReward->fetchColumn() == 0) {
        $rewardStmt = $pdo->prepare("
            INSERT INTO vhv_rewards (vhv_id, followup_id, points_earned, approval_status, approved_at)
            VALUES (?, ?, 1, 'approved', CURRENT_TIMESTAMP)
        ");
        $rewardStmt->execute([$vhvId, $fid]);
    }

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'message' => 'บันทึกผลการติดตาม DPAC สำเร็จ! อสม. ได้รับ +1 คะแนนสะสม'
    ]);
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()
    ]);
}
exit();
