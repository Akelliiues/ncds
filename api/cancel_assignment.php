<?php
// api/cancel_assignment.php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['cid'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

$cid = trim($data['cid']);
$currentYear = 2026;
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

try {
    // ดึง assignment ที่จะยกเลิก
    $stmt = $pdo->prepare("
        SELECT ta.assignment_id, ta.vhv_id, ta.assignment_status, tp.hoscode
        FROM task_assignments ta
        JOIN target_population tp ON ta.target_cid = tp.cid
        WHERE ta.target_cid = ? AND ta.budget_year = ?
        LIMIT 1
    ");
    $stmt->execute([$cid, $currentYear]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลการมอบหมายงาน']);
        exit();
    }

    // ตรวจสิทธิ์ hoscode สำหรับ admin ที่ล็อค hoscode
    if ($admin_hoscode && $assignment['hoscode'] !== $admin_hoscode) {
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ยกเลิกการมอบหมายงานนอกเขตบริการของคุณ']);
        exit();
    }

    // ป้องกันยกเลิกงานที่คัดกรองหรือข้ามเคสแล้ว
    if (in_array($assignment['assignment_status'], ['completed', 'skipped'])) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถยกเลิกงานที่ดำเนินการเสร็จสิ้นแล้วได้']);
        exit();
    }

    $pdo->beginTransaction();

    // บันทึก log ก่อนลบ
    $logStmt = $pdo->prepare("
        INSERT INTO assignment_history_log (assignment_id, action, note)
        VALUES (?, 'CANCEL', ?)
    ");
    $logStmt->execute([
        $assignment['assignment_id'],
        "ยกเลิกการมอบหมายงานโดยผู้ดูแลระบบ (CID: $cid)"
    ]);

    // ลบ assignment(s) ทั้งหมดของ CID นี้สำหรับปีงบประมาณนี้ (เผื่อกรณีมีข้อมูลซ้ำซ้อน)
    $delStmt = $pdo->prepare("DELETE FROM task_assignments WHERE target_cid = ? AND budget_year = ?");
    $delStmt->execute([$cid, $currentYear]);

    // ลบคะแนนสะสมที่เกี่ยวข้อง (ถ้ามี)
    $delRewards = $pdo->prepare("DELETE FROM vhv_rewards WHERE assignment_id = ?");
    $delRewards->execute([$assignment['assignment_id']]);

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'ยกเลิกการมอบหมายงานเรียบร้อยแล้ว']);
} catch (\Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
