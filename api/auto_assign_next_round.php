<?php
// api/auto_assign_next_round.php - ระบบตรวจสอบและมอบหมายงานคัดกรองรอบถัดไปอัตโนมัติ (Smart Next-Round Auto Assignment)
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/demo_data.php';

if (DemoDataProvider::isDemoMode()) {
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    if ($action === 'check_status') {
        echo json_encode([
            'status' => 'ready',
            'can_assign' => true,
            'current_round' => 1,
            'target_round' => 2,
            'total_targets' => 25,
            'prev_round_completed' => 25,
            'prev_round_pct' => 100.0,
            'already_assigned_count' => 5,
            'eligible_count' => 20,
            'vhv_breakdown' => [
                ['vhv_id' => 'VHV001', 'vhv_name' => 'นางสมศรี ใจดี (จำลอง)', 'count' => 10],
                ['vhv_id' => 'VHV002', 'vhv_name' => 'นายสมศักดิ์ รักถิ่น (จำลอง)', 'count' => 10]
            ],
            'unassigned_vhv_count' => 0,
            'message' => 'รอบที่ 1 ดำเนินการครบ 100% แล้ว พร้อมมอบหมายรอบที่ 2 จำนวน 20 ราย'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } elseif ($action === 'execute') {
        echo json_encode([
            'status' => 'success',
            'assigned_count' => 20,
            'target_round' => 2,
            'message' => 'จำลองการมอบหมายรอบที่ 2 อัตโนมัติสำเร็จ 20 ราย'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$selectedBudgetYear = isset($_SESSION['active_budget_year']) ? (int)$_SESSION['active_budget_year'] : (function_exists('get_current_budget_year') ? get_current_budget_year() : 2026);

// Support both GET (check_status) and POST JSON (execute/check_status)
$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?: [];

$action = $_GET['action'] ?? ($postData['action'] ?? 'check_status');
$tambon = $_GET['tambon'] ?? ($postData['tambon'] ?? '');
$moo = $_GET['moo'] ?? ($postData['moo'] ?? '');
$hoscode = $admin_hoscode ?: ($_GET['hoscode'] ?? ($postData['hoscode'] ?? ''));
$group = $_GET['group'] ?? ($postData['group'] ?? 'main');
if (isset($_GET['budget_year']) && is_numeric($_GET['budget_year'])) {
    $selectedBudgetYear = (int)$_GET['budget_year'];
} elseif (isset($postData['budget_year']) && is_numeric($postData['budget_year'])) {
    $selectedBudgetYear = (int)$postData['budget_year'];
}

if (!$tambon || $moo === '') {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุตำบลและหมู่บ้าน']);
    exit();
}

$vhidCode = $tambon . str_pad($moo, 2, '0', STR_PAD_LEFT);
$isSandboxVal = isSandboxMode($hoscode) ? 1 : 0;

try {
    // 1. Fetch all targets in this village for the given group
    $targetQuery = "
        SELECT p.cid, p.first_name, p.last_name, p.house_no, p.hoscode, p.vhid_code, p.moo,
               TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) AS age
        FROM target_population p
        WHERE (p.vhid_code = ? OR (CAST(p.moo AS UNSIGNED) = CAST(? AS UNSIGNED) AND p.hoscode = ?))
    ";

    if ($group === 'suspect') {
        $targetQuery .= " AND p.need_screen_dm = 0 AND p.need_screen_ht = 0 AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35";
    } elseif ($group === 'under_35_risk') {
        $targetQuery .= " AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) < 35 AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)";
    } else {
        $targetQuery .= " AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)";
    }

    if (!$isSandboxVal) {
        $targetQuery .= " AND p.cid NOT IN ('1234567890111', '1234567890112', '1234567890113', '1234567890114')";
    }

    if ($hoscode) {
        $hoscodes = get_query_hoscodes($hoscode);
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $targetQuery .= " AND p.hoscode IN ($inPlaceholders)";
        $params = array_merge([$vhidCode, $moo, $hoscode], $hoscodes);
    } else {
        $params = [$vhidCode, $moo, $hoscode ?: ''];
    }

    $tStmt = $pdo->prepare($targetQuery);
    $tStmt->execute($params);
    $allTargets = $tStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalTargets = count($allTargets);

    if ($totalTargets === 0) {
        echo json_encode([
            'status' => 'empty',
            'can_assign' => false,
            'total_targets' => 0,
            'message' => 'ไม่พบประชากรเป้าหมายในหมู่บ้านที่เลือก'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $cids = array_column($allTargets, 'cid');
    $cidPlaceholders = implode(',', array_fill(0, count($cids), '?'));

    // 2. Fetch all historical task assignments & screening results for these CIDs in this budget year
    $taskSql = "
        SELECT ta.assignment_id, ta.target_cid, ta.vhv_id, ta.round_number, ta.assignment_status,
               v.vhv_name
        FROM task_assignments ta
        LEFT JOIN vhv_users v ON ta.vhv_id = v.vhv_id
        WHERE ta.target_cid IN ($cidPlaceholders)
          AND ta.budget_year = ?
          AND ta.is_sandbox = ?
        ORDER BY ta.round_number ASC, ta.assignment_id ASC
    ";
    $taskParams = array_merge($cids, [$selectedBudgetYear, $isSandboxVal]);
    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute($taskParams);
    $allTasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group tasks by CID
    $tasksByCid = [];
    foreach ($allTasks as $row) {
        $cid = $row['target_cid'];
        $tasksByCid[$cid][] = $row;
    }

    // 3. Evaluate Round Progression (Round 1, Round 2, Round 3...)
    // Determine status of Round 1
    $round1CompletedCount = 0;
    $round1PendingCount = 0;
    $round1UnassignedCount = 0;

    foreach ($cids as $cid) {
        $userTasks = $tasksByCid[$cid] ?? [];
        $r1Task = null;
        foreach ($userTasks as $t) {
            $rn = (int)($t['round_number'] ?: 1);
            if ($rn === 1) {
                $r1Task = $t;
                break;
            }
        }

        if (!$r1Task) {
            $round1UnassignedCount++;
        } elseif ($r1Task['assignment_status'] === 'completed' || $r1Task['assignment_status'] === 'skipped') {
            $round1CompletedCount++;
        } else {
            $round1PendingCount++;
        }
    }

    $round1Pct = $totalTargets > 0 ? round(($round1CompletedCount / $totalTargets) * 100, 1) : 0;

    // CASE 1: Round 1 is NOT 100% completed
    if ($round1CompletedCount < $totalTargets) {
        $remaining = $totalTargets - $round1CompletedCount;
        echo json_encode([
            'status' => 'locked',
            'can_assign' => false,
            'current_round' => 1,
            'target_round' => 2,
            'total_targets' => $totalTargets,
            'prev_round_completed' => $round1CompletedCount,
            'prev_round_pct' => $round1Pct,
            'round1_pending' => $round1PendingCount,
            'round1_unassigned' => $round1UnassignedCount,
            'remaining_count' => $remaining,
            'message' => "รอบที่ 1 คัดกรองแล้ว {$round1CompletedCount}/{$totalTargets} ราย ({$round1Pct}%) ยังเหลืออีก {$remaining} รายที่ยังไม่เสร็จสิ้น ต้องคัดกรองรอบที่ 1 ให้ครบ 100% ก่อนจึงจะเปิดรอบถัดไปได้"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // CASE 2: Round 1 IS 100% completed!
    // Check Round 2 status and higher rounds
    $checkRound = 2;
    $foundReadyRound = null;
    $eligibleTargets = [];
    $alreadyAssignedInTargetRound = 0;

    while ($checkRound <= 10) {
        $prevRound = $checkRound - 1;
        
        // Count how many completed prevRound
        $prevCompletedCount = 0;
        foreach ($cids as $cid) {
            $userTasks = $tasksByCid[$cid] ?? [];
            foreach ($userTasks as $t) {
                $rn = (int)($t['round_number'] ?: 1);
                if ($rn === $prevRound && ($t['assignment_status'] === 'completed' || $t['assignment_status'] === 'skipped')) {
                    $prevCompletedCount++;
                    break;
                }
            }
        }

        // If prevRound was not 100% completed, we cannot assign checkRound
        if ($prevCompletedCount < $totalTargets) {
            $pct = round(($prevCompletedCount / $totalTargets) * 100, 1);
            $remaining = $totalTargets - $prevCompletedCount;
            echo json_encode([
                'status' => 'locked',
                'can_assign' => false,
                'current_round' => $prevRound,
                'target_round' => $checkRound,
                'total_targets' => $totalTargets,
                'prev_round_completed' => $prevCompletedCount,
                'prev_round_pct' => $pct,
                'remaining_count' => $remaining,
                'message' => "รอบที่ {$prevRound} คัดกรองแล้ว {$prevCompletedCount}/{$totalTargets} ราย ({$pct}%) ต้องคัดกรองให้ครบ 100% ก่อนจึงจะเปิดรอบที่ {$checkRound} ได้"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Prev round is 100% completed. Now check candidates for checkRound
        $roundEligible = [];
        $roundAlreadyAssigned = 0;

        foreach ($allTargets as $tObj) {
            $cid = $tObj['cid'];
            $userTasks = $tasksByCid[$cid] ?? [];
            
            // Check if this CID already has an assignment in checkRound
            $hasCheckRound = false;
            $prevVhvId = null;
            $prevVhvName = null;

            foreach ($userTasks as $t) {
                $rn = (int)($t['round_number'] ?: 1);
                if ($rn === $checkRound) {
                    $hasCheckRound = true;
                }
                // Track last responsible VHV from earlier rounds
                if ($rn < $checkRound && !empty($t['vhv_id'])) {
                    $prevVhvId = $t['vhv_id'];
                    $prevVhvName = $t['vhv_name'];
                }
            }

            if ($hasCheckRound) {
                $roundAlreadyAssigned++;
            } else {
                $roundEligible[] = [
                    'cid' => $cid,
                    'name' => $tObj['first_name'] . ' ' . $tObj['last_name'],
                    'house_no' => $tObj['house_no'],
                    'prev_vhv_id' => $prevVhvId,
                    'prev_vhv_name' => $prevVhvName ?: 'ยังไม่ระบุ อสม.'
                ];
            }
        }

        if (count($roundEligible) > 0) {
            $foundReadyRound = $checkRound;
            $eligibleTargets = $roundEligible;
            $alreadyAssignedInTargetRound = $roundAlreadyAssigned;
            break;
        }

        // If no one is eligible for checkRound, check if checkRound is also 100% completed to move to checkRound+1
        $checkRoundCompleted = 0;
        foreach ($cids as $cid) {
            $userTasks = $tasksByCid[$cid] ?? [];
            foreach ($userTasks as $t) {
                $rn = (int)($t['round_number'] ?: 1);
                if ($rn === $checkRound && ($t['assignment_status'] === 'completed' || $t['assignment_status'] === 'skipped')) {
                    $checkRoundCompleted++;
                    break;
                }
            }
        }

        if ($checkRoundCompleted < $totalTargets) {
            // checkRound is assigned to everyone, but still in progress (<100% completed)
            $pct = round(($checkRoundCompleted / $totalTargets) * 100, 1);
            echo json_encode([
                'status' => 'in_progress',
                'can_assign' => false,
                'current_round' => $checkRound,
                'target_round' => $checkRound + 1,
                'total_targets' => $totalTargets,
                'round_completed' => $checkRoundCompleted,
                'round_pct' => $pct,
                'already_assigned_count' => $roundAlreadyAssigned,
                'message' => "มอบหมายงานรอบที่ {$checkRound} ครบทุกคนแล้ว (ขณะนี้คัดกรองแล้ว {$checkRoundCompleted}/{$totalTargets} ราย - {$pct}%)"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $checkRound++;
    }

    if (!$foundReadyRound) {
        echo json_encode([
            'status' => 'completed_all',
            'can_assign' => false,
            'total_targets' => $totalTargets,
            'message' => "ประชากรเป้าหมายในหมู่บ้านนี้ได้รับการคัดกรองครบถ้วนสมบูรณ์แล้วทุกรอบ"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Group eligible targets by previous VHV
    $vhvBreakdownMap = [];
    $unassignedVhvTargets = [];

    foreach ($eligibleTargets as $t) {
        $vid = $t['prev_vhv_id'];
        if ($vid) {
            if (!isset($vhvBreakdownMap[$vid])) {
                $vhvBreakdownMap[$vid] = [
                    'vhv_id' => $vid,
                    'vhv_name' => $t['prev_vhv_name'],
                    'count' => 0,
                    'targets' => []
                ];
            }
            $vhvBreakdownMap[$vid]['count']++;
            $vhvBreakdownMap[$vid]['targets'][] = $t;
        } else {
            $unassignedVhvTargets[] = $t;
        }
    }

    $vhvBreakdownList = array_values($vhvBreakdownMap);

    // IF ACTION IS JUST CHECK_STATUS -> Return Status Data
    if ($action === 'check_status') {
        echo json_encode([
            'status' => 'ready',
            'can_assign' => true,
            'current_round' => $foundReadyRound - 1,
            'target_round' => $foundReadyRound,
            'total_targets' => $totalTargets,
            'prev_round_completed' => $totalTargets,
            'prev_round_pct' => 100.0,
            'already_assigned_count' => $alreadyAssignedInTargetRound,
            'eligible_count' => count($eligibleTargets),
            'vhv_breakdown' => $vhvBreakdownList,
            'unassigned_vhv_count' => count($unassignedVhvTargets),
            'unassigned_vhv_targets' => $unassignedVhvTargets,
            'message' => "รอบที่ " . ($foundReadyRound - 1) . " ดำเนินการครบ 100% แล้ว พร้อมมอบหมายรอบที่ {$foundReadyRound} จำนวน " . count($eligibleTargets) . " ราย ให้ อสม. คนเดิม"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // IF ACTION IS EXECUTE -> Perform Database Insert Transaction
    if ($action === 'execute') {
        $defaultVhvId = $postData['default_vhv_id'] ?? null;
        
        // Fetch list of active VHVs in this village as fallback if needed
        $villageVhvsStmt = $pdo->prepare("
            SELECT vhv_id, vhv_name 
            FROM vhv_users 
            WHERE (vhid_code = ? OR (CAST(vhv_moo AS UNSIGNED) = CAST(? AS UNSIGNED) AND (:target_hcode = '' OR hoscode = :target_hcode2)))
              AND (approved = 1 OR approved IS NULL)
            ORDER BY vhv_name ASC
        ");
        $villageVhvsStmt->execute([$vhidCode, (int)$moo, $hoscode ?: '', $hoscode ?: '']);
        $villageVhvs = $villageVhvsStmt->fetchAll(PDO::FETCH_ASSOC);
        $fallbackVhvId = $defaultVhvId ?: (!empty($villageVhvs[0]['vhv_id']) ? $villageVhvs[0]['vhv_id'] : null);

        $pdo->beginTransaction();

        $insertCount = 0;
        $insertStmt = $pdo->prepare("
            INSERT INTO task_assignments (target_cid, vhv_id, budget_year, round_number, assignment_status, is_sandbox)
            VALUES (?, ?, ?, ?, 'pending', ?)
        ");

        $logStmt = $pdo->prepare("
            INSERT INTO assignment_history_log (assignment_id, action, note)
            VALUES (?, 'AUTO_ASSIGN', ?)
        ");

        foreach ($eligibleTargets as $t) {
            $cid = $t['cid'];
            $targetVhvId = $t['prev_vhv_id'] ?: $fallbackVhvId;

            if (!$targetVhvId) {
                throw new \Exception("ไม่สามารถระบุ อสม. ผู้รับผิดชอบสำหรับ {$t['name']} (CID: $cid) ได้ กรุณาระบุ อสม. ประจำหมู่บ้าน");
            }

            $insertStmt->execute([$cid, $targetVhvId, $selectedBudgetYear, $foundReadyRound, $isSandboxVal]);
            $newAssignmentId = $pdo->lastInsertId();

            $note = "มอบหมายรอบที่ {$foundReadyRound} อัตโนมัติ (อสม. เดิม: $targetVhvId) โดยผู้ดูแลระบบ";
            $logStmt->execute([$newAssignmentId, $note]);
            $insertCount++;
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'assigned_count' => $insertCount,
            'target_round' => $foundReadyRound,
            'message' => "มอบหมายงานคัดกรองติดตามรอบที่ {$foundReadyRound} อัตโนมัติสำเร็จเรียบร้อยแล้ว จำนวน {$insertCount} ราย!"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}
