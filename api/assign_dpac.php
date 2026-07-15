<?php
// api/assign_dpac.php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['vhv_id']) || empty($data['enrollment_ids']) || !is_array($data['enrollment_ids'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

$vhvId = $data['vhv_id'];
$enrollmentIds = $data['enrollment_ids'];

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
if ($admin_hoscode) {
    // Check VHV authority
    $vhvCheckStmt = $pdo->prepare("SELECT hoscode FROM vhv_users WHERE vhv_id = ?");
    $vhvCheckStmt->execute([$vhvId]);
    $vhvRow = $vhvCheckStmt->fetch();
    if (!$vhvRow || $vhvRow['hoscode'] !== $admin_hoscode) {
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์มอบหมายงานให้กับ อสม. นอกสังกัด']);
        exit();
    }
}

try {
    $pdo->beginTransaction();
    $isSandboxVal = isSandboxMode($admin_hoscode) ? 1 : 0;
    
    $roundStmt = $pdo->prepare("SELECT IFNULL(MAX(round_number), 0) + 1 FROM dpac_followups WHERE enrollment_id = ?");
    $insertStmt = $pdo->prepare("INSERT INTO dpac_followups (enrollment_id, vhv_id, round_number, is_sandbox) VALUES (?, ?, ?, ?)");
    $updateEnrollStmt = $pdo->prepare("UPDATE dpac_enrollments SET assigned_vhv_id = ? WHERE enrollment_id = ?");

    $success = 0;
    foreach ($enrollmentIds as $eid) {
        // If non-super admin, verify enrollment is in their hoscode
        if ($admin_hoscode) {
            $eCheck = $pdo->prepare("
                SELECT p.hoscode 
                FROM dpac_enrollments e 
                JOIN target_population p ON e.cid = p.cid 
                WHERE e.enrollment_id = ?
            ");
            $eCheck->execute([$eid]);
            $eHos = $eCheck->fetchColumn();
            if ($eHos !== $admin_hoscode) {
                throw new \Exception("ไม่พบสิทธิ์เข้าถึงข้อมูลผู้เข้าร่วมโครงการ ID: $eid");
            }
        }

        // Get next round number
        $roundStmt->execute([$eid]);
        $nextRound = $roundStmt->fetchColumn();
        
        $insertStmt->execute([$eid, $vhvId, $nextRound, $isSandboxVal]);
        $updateEnrollStmt->execute([$vhvId, $eid]);
        $success++;
    }
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => "มอบหมายงานติดตาม DPAC สำเร็จ $success รายการ"]);
} catch (\Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
