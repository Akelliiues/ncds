<?php
// api/assign_tasks.php
require_once __DIR__ . '/../config/session.php';
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

// Fetch VHV details for verification
$vhvCheckStmt = $pdo->prepare("SELECT hoscode, vhid_code, vhv_moo FROM vhv_users WHERE vhv_id = ?");
$vhvCheckStmt->execute([$vhvId]);
$vhvRow = $vhvCheckStmt->fetch();
if (!$vhvRow) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล อสม.']);
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
if ($admin_hoscode) {
    $allowed_hoscodes = [$admin_hoscode];
    if (!in_array($vhvRow['hoscode'], $allowed_hoscodes)) {
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์มอบหมายงานให้กับ อสม. นอกสังกัด']);
        exit();
    }
}

try {
    $pdo->beginTransaction();

    foreach ($cids as $cid) {
        $tStmt = $pdo->prepare("SELECT first_name, last_name, hoscode, vhid_code, moo FROM target_population WHERE cid = ?");
        $tStmt->execute([$cid]);
        $tRow = $tStmt->fetch();
        if (!$tRow) {
            throw new \Exception("ไม่พบข้อมูลกลุ่มเป้าหมายรหัสบัตรประชาชน $cid");
        }
        $residentName = $tRow['first_name'] . ' ' . $tRow['last_name'];

        $targetMoo = intval($tRow['moo'] ?? 0);
        $vhvMoo = intval($vhvRow['vhv_moo'] ?? 0);
        $isOutsideArea = ($targetMoo === 0 || (isset($tRow['house_no']) && strpos($tRow['house_no'], 'นอกเขต') !== false));

        $vhidMatches = (!empty($tRow['vhid_code']) && !empty($vhvRow['vhid_code']) && $tRow['vhid_code'] === $vhvRow['vhid_code']);
        $mooMatches = ($targetMoo === $vhvMoo && $tRow['hoscode'] === $vhvRow['hoscode']);
        $outsideAreaAllowed = ($isOutsideArea && $tRow['hoscode'] === $vhvRow['hoscode']);

        if (!$vhidMatches && !$mooMatches && !$outsideAreaAllowed) {
            throw new \Exception("กลุ่มเป้าหมาย {$residentName} (หมู่ {$targetMoo}) อยู่คนละหมู่บ้านกับ อสม. (หมู่ {$vhvMoo}) ไม่สามารถดำเนินการได้");
        }

        if ($admin_hoscode) {
            if (!in_array($tRow['hoscode'], $allowed_hoscodes)) {
                throw new \Exception("กลุ่มเป้าหมาย {$residentName} อยู่นอกเขตบริการ ไม่สามารถดำเนินการได้");
            }
        }

        $isSandboxVal = isSandboxMode($vhvRow['hoscode']) ? 1 : 0;

        // Check existing assignment in the current mode
        $checkStmt = $pdo->prepare("SELECT * FROM task_assignments WHERE target_cid = ? AND budget_year = ? AND is_sandbox = ?");
        $checkStmt->execute([$cid, $currentYear, $isSandboxVal]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            if ($existing['vhv_id'] !== $vhvId) {
                // ปกป้องคะแนนของ อสม. คนเดิม: เซ็ตค่า screening_id ใน vhv_rewards ให้เป็น NULL ก่อนเปลี่ยนตัว
                // เพื่อไม่ให้โดนลบคะแนนตามกลไก Cascade ในภายหลัง
                if (in_array($existing['assignment_status'], ['completed', 'skipped'])) {
                    $nullifyStmt = $pdo->prepare("
                        UPDATE vhv_rewards 
                        SET screening_id = NULL 
                        WHERE screening_id IN (SELECT screening_id FROM screening_results WHERE assignment_id = ?)
                    ");
                    $nullifyStmt->execute([$existing['assignment_id']]);
                }

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
                INSERT INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status, is_sandbox)
                VALUES (?, ?, ?, 'pending', ?)
            ");
            $insertStmt->execute([$cid, $vhvId, $currentYear, $isSandboxVal]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
