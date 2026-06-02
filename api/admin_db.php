<?php
// api/admin_db.php
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

// Super Admin check: 'admin_logged_in' is true and 'admin_hoscode' is null
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['admin_hoscode'] !== null) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ในการดำเนินการนี้']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'clear_hoscode') {
    $hoscode = $_POST['hoscode'] ?? '';
    if (empty($hoscode)) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่ได้ระบุ รพ.สต.']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Target population table uses 'cid' as primary key.
        // Child tables: task_assignments (target_cid), screening_results (assignment_id), vhv_rewards (screening_id)
        
        // 1. Delete vhv_rewards where screening_id belongs to assignments of this hoscode
        $pdo->prepare("
            DELETE FROM vhv_rewards WHERE screening_id IN (
                SELECT s.screening_id 
                FROM screening_results s
                JOIN task_assignments a ON s.assignment_id = a.assignment_id
                JOIN target_population p ON a.target_cid = p.cid
                WHERE p.hoscode = ?
            )
        ")->execute([$hoscode]);

        // 2. Delete screening_results
        $pdo->prepare("
            DELETE FROM screening_results WHERE assignment_id IN (
                SELECT a.assignment_id
                FROM task_assignments a
                JOIN target_population p ON a.target_cid = p.cid
                WHERE p.hoscode = ?
            )
        ")->execute([$hoscode]);

        // 3. Delete task_assignments
        $pdo->prepare("
            DELETE FROM task_assignments WHERE target_cid IN (
                SELECT cid FROM target_population WHERE hoscode = ?
            )
        ")->execute([$hoscode]);

        // 4. Delete dpac_followups
        $pdo->prepare("
            DELETE FROM dpac_followups WHERE enrollment_id IN (
                SELECT e.enrollment_id 
                FROM dpac_enrollments e
                JOIN target_population p ON e.cid = p.cid
                WHERE p.hoscode = ?
            )
        ")->execute([$hoscode]);

        // 5. Delete dpac_enrollments
        $pdo->prepare("
            DELETE FROM dpac_enrollments WHERE cid IN (
                SELECT cid FROM target_population WHERE hoscode = ?
            )
        ")->execute([$hoscode]);

        // 6. Finally, delete target_population
        $stmt = $pdo->prepare("DELETE FROM target_population WHERE hoscode = ?");
        $stmt->execute([$hoscode]);
        $deletedRows = $stmt->rowCount();

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'ล้างข้อมูลสำเร็จแล้ว',
            'deleted_count' => $deletedRows
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
    }
    exit();
} elseif ($action === 'delete_individual_record') {
    $cid = $_POST['cid'] ?? '';
    if (empty($cid)) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่ได้ระบุ CID']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Delete vhv_rewards
        $pdo->prepare("
            DELETE FROM vhv_rewards WHERE screening_id IN (
                SELECT s.screening_id 
                FROM screening_results s
                JOIN task_assignments a ON s.assignment_id = a.assignment_id
                WHERE a.target_cid = ?
            )
        ")->execute([$cid]);

        // 2. Delete screening_results
        $pdo->prepare("
            DELETE FROM screening_results WHERE assignment_id IN (
                SELECT assignment_id FROM task_assignments WHERE target_cid = ?
            )
        ")->execute([$cid]);

        // 3. Delete task_assignments
        $pdo->prepare("DELETE FROM task_assignments WHERE target_cid = ?")->execute([$cid]);

        // 4. Delete dpac_followups
        $pdo->prepare("
            DELETE FROM dpac_followups WHERE enrollment_id IN (
                SELECT enrollment_id FROM dpac_enrollments WHERE cid = ?
            )
        ")->execute([$cid]);

        // 5. Delete dpac_enrollments
        $pdo->prepare("DELETE FROM dpac_enrollments WHERE cid = ?")->execute([$cid]);

        // 6. Delete target_population
        $stmt = $pdo->prepare("DELETE FROM target_population WHERE cid = ?");
        $stmt->execute([$cid]);
        $deletedRows = $stmt->rowCount();

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'ลบข้อมูลรายบุคคลสำเร็จ',
            'deleted_count' => $deletedRows
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
    }
    exit();
} else {
    echo json_encode(['status' => 'error', 'message' => 'คำสั่งไม่ถูกต้อง']);
    exit();
}
