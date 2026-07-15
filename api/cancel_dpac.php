<?php
// api/cancel_dpac.php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['enrollment_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

$enrollmentId = $data['enrollment_id'];
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

try {
    // If non-super admin, verify enrollment is in their hoscode
    if ($admin_hoscode) {
        $eCheck = $pdo->prepare("
            SELECT p.hoscode 
            FROM dpac_enrollments e 
            JOIN target_population p ON e.cid = p.cid 
            WHERE e.enrollment_id = ?
        ");
        $eCheck->execute([$enrollmentId]);
        $eHos = $eCheck->fetchColumn();
        if ($eHos !== $admin_hoscode) {
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ลบข้อมูลผู้เข้าร่วมโครงการนอกเขตสังกัด']);
            exit();
        }
    }

    $pdo->beginTransaction();

    // Delete followups first
    $deleteFollowups = $pdo->prepare("DELETE FROM dpac_followups WHERE enrollment_id = ?");
    $deleteFollowups->execute([$enrollmentId]);
    
    // Delete enrollment
    $deleteEnrollment = $pdo->prepare("DELETE FROM dpac_enrollments WHERE enrollment_id = ?");
    $deleteEnrollment->execute([$enrollmentId]);
    
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => "ยกเลิกการเข้าร่วมโครงการ DPAC สำเร็จ"]);
} catch (\Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการยกเลิก: ' . $e->getMessage()]);
}
