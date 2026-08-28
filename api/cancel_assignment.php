<?php
// api/cancel_assignment.php
require_once __DIR__ . '/../config/demo_data.php';

if (DemoDataProvider::isDemoMode()) {
    echo json_encode(['status' => 'success', 'message' => 'จำลองการยกเลิกมอบหมายงานสำเร็จ (โหมดจำลอง)'], JSON_UNESCAPED_UNICODE);
    exit();
}

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
$cid = isset($data['cid']) ? trim($data['cid']) : '';
$assignmentId = isset($data['task_id']) ? intval($data['task_id']) : (isset($data['assignment_id']) ? intval($data['assignment_id']) : 0);

try {
    $isSandboxVal = isSandboxMode($admin_hoscode) ? 1 : 0;

    if ($assignmentId > 0) {
        $stmt = $pdo->prepare("
            SELECT ta.assignment_id, ta.vhv_id, ta.assignment_status, ta.target_cid, tp.hoscode
            FROM task_assignments ta
            JOIN target_population tp ON ta.target_cid = tp.cid
            WHERE ta.assignment_id = ?
              AND ta.assignment_status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$assignmentId]);
    } else {
        // Fetch ONLY latest PENDING assignment for this CID
        $stmt = $pdo->prepare("
            SELECT ta.assignment_id, ta.vhv_id, ta.assignment_status, ta.target_cid, tp.hoscode
            FROM task_assignments ta
            JOIN target_population tp ON ta.target_cid = tp.cid
            WHERE ta.target_cid = ? AND ta.budget_year = ? AND ta.assignment_status = 'pending'
            ORDER BY ta.round_number DESC LIMIT 1
        ");
        $stmt->execute([$cid, $currentYear]);
    }
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลการมอบหมายงานรอดำเนินการที่สามารถยกเลิกได้ (ไม่สามารถยกเลิกงานที่คัดกรองเสร็จแล้ว)']);
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

    // 1. บันทึก log ก่อนการยกเลิก
    $logStmt = $pdo->prepare("
        INSERT INTO assignment_history_log (assignment_id, action, note)
        VALUES (?, 'CANCEL', ?)
    ");
    $logStmt->execute([
        $assignment['assignment_id'],
        "ยกเลิกการมอบหมายงานโดยผู้ดูแลระบบ (CID: {$assignment['target_cid']})"
    ]);

    // 2. ป้องกันการลบข้อมูลแบบ CASCADE โดยการรับประกัน target_cid และปลดล็อก assignment_id บน screening_results
    $ensureCidStmt = $pdo->prepare("
        UPDATE screening_results 
        SET target_cid = ? 
        WHERE (target_cid IS NULL OR target_cid = '') AND assignment_id = ?
    ");
    $ensureCidStmt->execute([$assignment['target_cid'], $assignment['assignment_id']]);

    $unlinkSrStmt = $pdo->prepare("
        UPDATE screening_results 
        SET assignment_id = NULL 
        WHERE assignment_id = ?
    ");
    $unlinkSrStmt->execute([$assignment['assignment_id']]);

    // 3. ปลดล็อกตารางคะแนนสะสมก่อนลบใบงาน
    $unlinkRwStmt = $pdo->prepare("
        UPDATE vhv_rewards 
        SET assignment_id = NULL 
        WHERE assignment_id = ?
    ");
    $unlinkRwStmt->execute([$assignment['assignment_id']]);

    // 4. ลบเฉพาะใบงานรอดำเนินการ (pending) ของ assignment_id นี้เท่านั้น
    $delStmt = $pdo->prepare("DELETE FROM task_assignments WHERE assignment_id = ? AND assignment_status = 'pending'");
    $delStmt->execute([$assignment['assignment_id']]);

    if ($delStmt->rowCount() !== 1) {
        throw new \RuntimeException('ไม่สามารถยกเลิกใบงานที่ระบุได้ เนื่องจากสถานะใบงานมีการเปลี่ยนแปลง');
    }

    if (function_exists('logUserActivity')) {
        logUserActivity('ASSIGNMENT', 'ยกเลิกการมอบหมายงาน', [
            'assignment_id' => $assignment['assignment_id'] ?? null,
            'target_name' => ($target['first_name'] ?? '') . ' ' . ($target['last_name'] ?? ''),
            'cid' => $targetCid
        ]);
    }

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'ยกเลิกการมอบหมายงานเรียบร้อยแล้ว']);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
