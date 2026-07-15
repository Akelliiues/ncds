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

if (!$data || (empty($data['cid']) && empty($data['followup_id']))) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$currentYear = 2026;

// CASE 1: DPAC Followup Cancellation
if (isset($data['followup_id'])) {
    try {
        $isSandboxVal = isSandboxMode($admin_hoscode) ? 1 : 0;
        $followupId = intval($data['followup_id']);

        // ดึง dpac_followups เพื่อเช็คสิทธิ์และสถานะ
        $stmt = $pdo->prepare("
            SELECT f.followup_id, f.enrollment_id, f.status, p.hoscode
            FROM dpac_followups f
            JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
            JOIN target_population p ON e.cid = p.cid
            WHERE f.followup_id = ? AND f.is_sandbox = ?
            LIMIT 1
        ");
        $stmt->execute([$followupId, $isSandboxVal]);
        $followup = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$followup) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลการติดตาม DPAC ในโหมดการทำงานปัจจุบัน']);
            exit();
        }

        // ตรวจสิทธิ์ hoscode
        if ($admin_hoscode && $followup['hoscode'] !== $admin_hoscode) {
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ยกเลิกงานนอกเขตบริการของคุณ']);
            exit();
        }

        if ($followup['status'] === 'completed') {
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถยกเลิกงานที่ติดตามเสร็จสิ้นแล้วได้']);
            exit();
        }

        $pdo->beginTransaction();

        // ลบ dpac_followup
        $delStmt = $pdo->prepare("DELETE FROM dpac_followups WHERE followup_id = ?");
        $delStmt->execute([$followupId]);

        // อัปเดต enrollment ตั้งค่า assigned_vhv_id เป็น NULL
        $upStmt = $pdo->prepare("UPDATE dpac_enrollments SET assigned_vhv_id = NULL WHERE enrollment_id = ?");
        $upStmt->execute([$followup['enrollment_id']]);

        // ลบคะแนนที่เกี่ยวข้อง (ถ้ามี)
        $delRewards = $pdo->prepare("DELETE FROM vhv_rewards WHERE followup_id = ?");
        $delRewards->execute([$followupId]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'ยกเลิกการมอบหมายงานติดตาม DPAC เรียบร้อยแล้ว']);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
    exit();
}

// CASE 2: NCD Screening Cancellation
$cid = trim($data['cid']);

try {
    $isSandboxVal = isSandboxMode($admin_hoscode) ? 1 : 0;
    // ดึง assignment ที่จะยกเลิก
    $stmt = $pdo->prepare("
        SELECT ta.assignment_id, ta.vhv_id, ta.assignment_status, tp.hoscode
        FROM task_assignments ta
        JOIN target_population tp ON ta.target_cid = tp.cid
        WHERE ta.target_cid = ? AND ta.budget_year = ? AND ta.is_sandbox = ?
        LIMIT 1
    ");
    $stmt->execute([$cid, $currentYear, $isSandboxVal]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลการมอบหมายงานในโหมดการทำงานปัจจุบัน']);
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

    // ลบ assignment(s) ทั้งหมดของ CID นี้สำหรับปีงบประมาณนี้และในโหมดการทำงานปัจจุบัน
    $delStmt = $pdo->prepare("DELETE FROM task_assignments WHERE target_cid = ? AND budget_year = ? AND is_sandbox = ?");
    $delStmt->execute([$cid, $currentYear, $isSandboxVal]);

    // ลบคะแนนสะสมที่เกี่ยวข้อง (ถ้ามี)
    $delRewards = $pdo->prepare("DELETE FROM vhv_rewards WHERE assignment_id = ?");
    $delRewards->execute([$assignment['assignment_id']]);

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'ยกเลิกการมอบหมายงานเรียบร้อยแล้ว']);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
