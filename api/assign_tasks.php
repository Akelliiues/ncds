<?php
// api/assign_tasks.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['vhv_id']) || empty($data['target_cids']) || !is_array($data['target_cids'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

$vhvId = $data['vhv_id'];
$cids = $data['target_cids'];
$currentYear = 2026;
$staffName = "ผู้ดูแลระบบ (Smart Assignment)";
$reason = "แอดมินจัดสรรแบบระบุตัว";

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
if ($admin_hoscode) {
    $allowed_hoscodes = [$admin_hoscode];
    // Check VHV authority
    $vhvCheckStmt = $pdo->prepare("SELECT hoscode FROM vhv_users WHERE vhv_id = ?");
    $vhvCheckStmt->execute([$vhvId]);
    $vhvRow = $vhvCheckStmt->fetch();
    if (!$vhvRow || !in_array($vhvRow['hoscode'], $allowed_hoscodes)) {
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์มอบหมายงานให้กับ อสม. นอกสังกัด']);
        exit();
    }
}

try {
    $pdo->beginTransaction();

    foreach ($cids as $cid) {
        // If sub-admin, check target's hoscode
        if ($admin_hoscode) {
            $tStmt = $pdo->prepare("SELECT hoscode FROM target_population WHERE cid = ?");
            $tStmt->execute([$cid]);
            $tRow = $tStmt->fetch();
            if (!$tRow || !in_array($tRow['hoscode'], $allowed_hoscodes)) {
                throw new \Exception("มีกลุ่มเป้าหมายภายนอกเขตบริการปนอยู่ ไม่สามารถดำเนินการได้");
            }
        }

        // Check existing assignment
        $checkStmt = $pdo->prepare("SELECT * FROM task_assignments WHERE target_cid = ? AND budget_year = ?");
        $checkStmt->execute([$cid, $currentYear]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            if ($existing['vhv_id'] !== $vhvId) {
                $oldVhvId = $existing['vhv_id'];
                
                // Update assignment
                $updateStmt = $pdo->prepare("
                    UPDATE task_assignments 
                    SET vhv_id = ?, assignment_status = 'pending', assigned_at = CURRENT_TIMESTAMP 
                    WHERE assignment_id = ?
                ");
                $updateStmt->execute([$vhvId, $existing['assignment_id']]);

                // Log history
                $note = "เปลี่ยนจาก VHV: $oldVhvId เป็น $vhvId โดย $staffName ($reason)";
                $logStmt = $pdo->prepare("
                    INSERT INTO assignment_history_log (assignment_id, action, note)
                    VALUES (?, 'REASSIGN', ?)
                ");
                $logStmt->execute([$existing['assignment_id'], $note]);
            }
        } else {
            // New assignment
            $insertStmt = $pdo->prepare("
                INSERT INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status)
                VALUES (?, ?, ?, 'pending')
            ");
            $insertStmt->execute([$cid, $vhvId, $currentYear]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
