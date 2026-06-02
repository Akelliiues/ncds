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
$action = $_POST['action'] ?? '';

if ($fid <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ข้อมูลรหัสติดตามไม่ถูกต้อง'
    ]);
    exit();
}

// Check followup status and skip_count
$checkFollowup = $pdo->prepare("SELECT status, skip_count FROM dpac_followups WHERE followup_id = ? AND vhv_id = ?");
$checkFollowup->execute([$fid, $vhvId]);
$followup = $checkFollowup->fetch();

if (!$followup) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบข้อมูลการติดตามในระบบ'
    ]);
    exit();
}

if ($followup['status'] === 'completed') {
    echo json_encode([
        'status' => 'error',
        'message' => 'การติดตามรอบนี้ได้ดำเนินการเสร็จสิ้นไปแล้ว'
    ]);
    exit();
}

$pdo->beginTransaction();
try {
    if ($action === 'skip_case') {
        $skipCount = (int)$followup['skip_count'];
        if ($skipCount >= 3) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่สามารถข้ามเคสนี้ได้อีก เนื่องจากข้ามครบกำหนด 3 ครั้งแล้ว'
            ]);
            exit();
        }

        $skippedReason = $_POST['skipped_reason'] ?? 'ไม่ระบุ';

        // Update skip count and reason
        $updateStmt = $pdo->prepare("
            UPDATE dpac_followups 
            SET skip_count = skip_count + 1,
                skipped_reason = ?
            WHERE followup_id = ? AND vhv_id = ?
        ");
        $updateStmt->execute([$skippedReason, $fid, $vhvId]);

        // Award +0.25 points for effort
        $rewardStmt = $pdo->prepare("
            INSERT INTO vhv_rewards (vhv_id, followup_id, points_earned, approval_status, approved_at)
            VALUES (?, ?, 0.25, 'approved', CURRENT_TIMESTAMP)
        ");
        $rewardStmt->execute([$vhvId, $fid]);

        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'ข้ามเคสติดตามชั่วคราวเรียบร้อย! อสม. ได้รับ +0.25 คะแนนสะสม'
        ]);
        exit();

    } else {
        // Normal completion
        $weight = $_POST['weight'] !== '' ? (float)$_POST['weight'] : null;
        $height = $_POST['height'] !== '' ? (float)$_POST['height'] : null;
        $waist = $_POST['waist'] !== '' ? (float)$_POST['waist'] : null;
        $fbs = ($_POST['fbs'] !== null && $_POST['fbs'] !== '') ? (int)$_POST['fbs'] : null;
        $sbp = ($_POST['bp_sys'] !== null && $_POST['bp_sys'] !== '') ? (int)$_POST['bp_sys'] : null;
        $dbp = ($_POST['bp_dia'] !== null && $_POST['bp_dia'] !== '') ? (int)$_POST['bp_dia'] : null;
        $healthRisk = $_POST['health_risk_level'] ?? '';
        $advice = $_POST['advice_given'] ?? '';

        $updateStmt = $pdo->prepare("
            UPDATE dpac_followups 
            SET status = 'completed', completed_at = CURRENT_TIMESTAMP,
                weight = ?, height = ?, waist = ?,
                fbs = ?, bp_sys = ?, bp_dia = ?,
                health_risk_level = ?, advice_given = ?
            WHERE followup_id = ? AND vhv_id = ?
        ");
        $updateStmt->execute([$weight, $height, $waist, $fbs, $sbp, $dbp, $healthRisk, $advice, $fid, $vhvId]);

        // Calculate points to earn: 1.00 - (skip_count * 0.25)
        $skipCount = (int)$followup['skip_count'];
        $pointsEarned = max(0.00, 1.00 - ($skipCount * 0.25));

        // Insert remaining points
        $rewardStmt = $pdo->prepare("
            INSERT INTO vhv_rewards (vhv_id, followup_id, points_earned, approval_status, approved_at)
            VALUES (?, ?, ?, 'approved', CURRENT_TIMESTAMP)
        ");
        $rewardStmt->execute([$vhvId, $fid, $pointsEarned]);

        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึกผลการติดตาม DPAC สำเร็จ! อสม. ได้รับ +' . $pointsEarned . ' คะแนนสะสม'
        ]);
        exit();
    }
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()
    ]);
    exit();
}
