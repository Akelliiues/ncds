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

$requestedRound = isset($data['round_number']) && is_numeric($data['round_number']) ? (int)$data['round_number'] : 0;

try {
    $idxNew = $pdo->query("SHOW INDEX FROM `task_assignments` WHERE Key_name = 'udx_cid_year_round_sb'")->fetchAll();
    if (empty($idxNew)) {
        $pdo->exec("ALTER TABLE `task_assignments` ADD UNIQUE KEY `udx_cid_year_round_sb` (`target_cid`, `budget_year`, `round_number`, `is_sandbox`)");
    }
    $idxOld = $pdo->query("SHOW INDEX FROM `task_assignments` WHERE Key_name = 'udx_cid_year'")->fetchAll();
    if (!empty($idxOld)) {
        $pdo->exec("ALTER TABLE `task_assignments` DROP INDEX `udx_cid_year`");
    }
} catch (\PDOException $e) {}

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

        // Check if there is an existing PENDING assignment for this target
        $pendingCheck = $pdo->prepare("
            SELECT * FROM task_assignments 
            WHERE target_cid = ? AND budget_year = ? AND assignment_status = 'pending' AND is_sandbox = ? 
            ORDER BY round_number DESC LIMIT 1
        ");
        $pendingCheck->execute([$cid, $currentYear, $isSandboxVal]);
        $existingPending = $pendingCheck->fetch();

        if ($existingPending) {
            // Update VHV for existing pending assignment
            if ($existingPending['vhv_id'] !== $vhvId) {
                $oldVhvId = $existingPending['vhv_id'];
                $targetRound = $existingPending['round_number'];

                $updateStmt = $pdo->prepare("
                    UPDATE task_assignments 
                    SET vhv_id = ?, assigned_at = CURRENT_TIMESTAMP 
                    WHERE assignment_id = ?
                ");
                $updateStmt->execute([$vhvId, $existingPending['assignment_id']]);

                // Log history
                $note = "เปลี่ยนจาก VHV: $oldVhvId เป็น $vhvId โดย $staffName ($reason) - รอบที่ $targetRound";
                $logStmt = $pdo->prepare("
                    INSERT INTO assignment_history_log (assignment_id, action, note)
                    VALUES (?, 'REASSIGN', ?)
                ");
                $logStmt->execute([$existingPending['assignment_id'], $note]);
            }
        } else {
            // No pending assignment exists (target is either new or has completed previous rounds)
            // Check max completed round to preserve Round 1 Baseline Checkpoint
            $maxRoundStmt = $pdo->prepare("
                SELECT IFNULL(MAX(round_number), 0) 
                FROM task_assignments 
                WHERE target_cid = ? AND budget_year = ? AND assignment_status = 'completed' AND is_sandbox = ?
            ");
            $maxRoundStmt->execute([$cid, $currentYear, $isSandboxVal]);
            $maxCompletedRound = (int)$maxRoundStmt->fetchColumn();

            if ($maxCompletedRound >= 1 && $requestedRound <= 1) {
                // Round 1 is locked as Checkpoint! Automatically promote new assignment to next round (Round 2+)
                $targetRound = $maxCompletedRound + 1;
            } else if ($requestedRound > 0) {
                $targetRound = $requestedRound;
            } else {
                $targetRound = $maxCompletedRound + 1;
            }

            // Insert new assignment for targetRound
            $insertStmt = $pdo->prepare("
                INSERT INTO task_assignments (target_cid, vhv_id, budget_year, round_number, assignment_status, is_sandbox)
                VALUES (?, ?, ?, ?, 'pending', ?)
            ");
            $insertStmt->execute([$cid, $vhvId, $currentYear, $targetRound, $isSandboxVal]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
