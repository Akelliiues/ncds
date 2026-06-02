<?php
// api/activate_suspect_targets.php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

// Parse JSON payload
$data = json_decode(file_get_contents('php://input'), true);
$cids = $data['cids'] ?? [];

if (empty($cids)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลบัตรประชาชนที่ต้องการเปิดสิทธิ์']);
    exit();
}

try {
    $pdo->beginTransaction();

    $dmStmt = $pdo->prepare("SELECT risk FROM staging_hdc_dm WHERE cid = ? ORDER BY staging_id DESC LIMIT 1");
    $htStmt = $pdo->prepare("SELECT risk FROM staging_hdc_ht WHERE cid = ? ORDER BY staging_id DESC LIMIT 1");
    $updateStmt = $pdo->prepare("UPDATE target_population SET need_screen_dm = ?, need_screen_ht = ?, updated_at = NOW() WHERE cid = ?");

    $successCount = 0;

    foreach ($cids as $cid) {
        $cid = trim((string)$cid);
        if (empty($cid)) continue;

        // Check if there is staging data indicating they have risk 3 for DM
        $dmStmt->execute([$cid]);
        $dmRisk = $dmStmt->fetchColumn();

        // Check if there is staging data indicating they have risk 3 for HT
        $htStmt->execute([$cid]);
        $htRisk = $htStmt->fetchColumn();

        $needDm = ($dmRisk === '3') ? 1 : 0;
        $needHt = ($htRisk === '3') ? 1 : 0;

        // Fallback: if we can't find specific staging records but they are in this list, activate both
        if ($needDm === 0 && $needHt === 0) {
            $needDm = 1;
            $needHt = 1;
        }

        $updateStmt->execute([$needDm, $needHt, $cid]);
        $successCount++;
    }

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'message' => "เปิดสิทธิ์คัดกรองกลุ่มป่วย/สงสัยป่วยสำเร็จ $successCount ราย"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage()
    ]);
}
