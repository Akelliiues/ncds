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
$hoscode = !empty($_GET['hoscode']) ? $_GET['hoscode'] : ($admin_hoscode ?: ($postData['hoscode'] ?? ''));
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
$legacyVhid = str_replace('3420', '3418', $vhidCode);
$isSandboxVal = isSandboxMode($hoscode) ? 1 : 0;

try {
    // 1. Fetch all targets in this village for the given group
    $targetQuery = "
        SELECT p.cid, p.first_name, p.last_name, p.house_no, p.hoscode, p.vhid_code, p.moo,
               TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) AS age
        FROM target_population p
        WHERE (p.vhid_code = :vhid OR p.vhid_code = :legacy_vhid OR (CAST(p.moo AS UNSIGNED) = CAST(:moo AS UNSIGNED) AND (:target_hoscode = '' OR p.hoscode = :target_hoscode2)))
    ";

    if ($group === 'suspect') {
        $targetQuery .= " AND p.need_screen_dm = 0 AND p.need_screen_ht = 0 AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35";
    } elseif ($group === 'under_35_risk') {
        $targetQuery .= " AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) < 35 AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1 OR COALESCE(p.is_manual, 0) = 1)";
    } else {
        $targetQuery .= " AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1) AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35";
    }

    if (!$isSandboxVal) {
        $targetQuery .= " AND p.cid NOT IN ('1234567890111', '1234567890112', '1234567890113', '1234567890114')";
    }

    $params = [
        'vhid' => $vhidCode,
        'legacy_vhid' => $legacyVhid,
        'moo' => $moo,
        'target_hoscode' => $hoscode ?: '',
        'target_hoscode2' => $hoscode ?: ''
    ];

    if ($hoscode) {
        $hoscodes = get_query_hoscodes($hoscode);
        $inKeys = [];
        foreach ($hoscodes as $i => $code) {
            $key = "hoscode_" . $i;
            $inKeys[] = ":" . $key;
            $params[$key] = $code;
        }
        $inPlaceholders = implode(',', $inKeys);
        $targetQuery .= " AND p.hoscode IN ($inPlaceholders)";
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
          AND (ta.budget_year = ? OR ta.budget_year IS NULL)
          AND ta.is_sandbox = ?
        ORDER BY ta.round_number ASC, ta.assignment_id ASC
    ";
    $taskParams = array_merge($cids, [$selectedBudgetYear, $isSandboxVal]);
    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute($taskParams);
    $allTasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch screening_results for these CIDs
    $scrSql = "
        SELECT sr.screening_id, sr.assignment_id, COALESCE(sr.target_cid, ta.target_cid) as target_cid,
               sr.round_number, sr.created_at,
               ta.vhv_id, v.vhv_name
        FROM screening_results sr
        LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
        LEFT JOIN vhv_users v ON ta.vhv_id = v.vhv_id
        WHERE (sr.target_cid IN ($cidPlaceholders) OR ta.target_cid IN ($cidPlaceholders))
          AND ta.budget_year = ?
          AND COALESCE(sr.is_sandbox, 0) = ?
        ORDER BY sr.round_number ASC, sr.created_at ASC
    ";
    $scrParams = array_merge($cids, $cids, [$selectedBudgetYear, $isSandboxVal]);
    $scrStmt = $pdo->prepare($scrSql);
    $scrStmt->execute($scrParams);
    $allScreenings = $scrStmt->fetchAll(PDO::FETCH_ASSOC);

    // Compile comprehensive historical state for each CID
    $cidState = [];
    foreach ($cids as $cid) {
        $cidState[$cid] = [
            'max_completed_round' => 0,
            'pending_rounds' => [],
            'all_rounds' => [],
            'last_vhv_id' => null,
            'last_vhv_name' => null
        ];
    }

    foreach ($allTasks as $row) {
        $cid = $row['target_cid'];
        if (!isset($cidState[$cid])) continue;
        $rn = (int)($row['round_number'] ?: 1);
        $cidState[$cid]['all_rounds'][$rn] = true;
        if ($row['assignment_status'] === 'completed' || $row['assignment_status'] === 'skipped') {
            if ($rn > $cidState[$cid]['max_completed_round']) {
                $cidState[$cid]['max_completed_round'] = $rn;
            }
        } elseif ($row['assignment_status'] === 'pending') {
            $cidState[$cid]['pending_rounds'][$rn] = true;
        }
        if (!empty($row['vhv_id'])) {
            $cidState[$cid]['last_vhv_id'] = $row['vhv_id'];
            $cidState[$cid]['last_vhv_name'] = $row['vhv_name'];
        }
    }

    foreach ($allScreenings as $row) {
        $cid = $row['target_cid'];
        if (!$cid || !isset($cidState[$cid])) continue;
        $rn = (int)($row['round_number'] ?: 1);
        $cidState[$cid]['all_rounds'][$rn] = true;
        if ($rn > $cidState[$cid]['max_completed_round']) {
            $cidState[$cid]['max_completed_round'] = $rn;
        }
        if (!empty($row['vhv_id'])) {
            $cidState[$cid]['last_vhv_id'] = $row['vhv_id'];
            $cidState[$cid]['last_vhv_name'] = $row['vhv_name'];
        }
    }

    // 3. Evaluate Round Progression (Round 1, Round 2, Round 3...)
    $foundReadyRound = null;
    $eligibleTargets = [];
    $alreadyAssignedInTargetRound = 0;

    for ($r = 1; $r <= 10; $r++) {
        // Count how many have completed round $r (or higher)
        $completedRoundR = 0;
        foreach ($cids as $cid) {
            if ($cidState[$cid]['max_completed_round'] >= $r) {
                $completedRoundR++;
            }
        }

        // If Round 1 is not 100% completed
        if ($r === 1 && $completedRoundR < $totalTargets) {
            $pct = $totalTargets > 0 ? round(($completedRoundR / $totalTargets) * 100, 1) : 0;
            $remaining = $totalTargets - $completedRoundR;
            echo json_encode([
                'status' => 'locked',
                'can_assign' => false,
                'current_round' => 1,
                'target_round' => 2,
                'total_targets' => $totalTargets,
                'prev_round_completed' => $completedRoundR,
                'prev_round_pct' => $pct,
                'remaining_count' => $remaining,
                'message' => "รอบที่ 1 คัดกรองแล้ว {$completedRoundR}/{$totalTargets} ราย ({$pct}%) ยังเหลืออีก {$remaining} รายที่ยังไม่เสร็จสิ้น ต้องคัดกรองรอบที่ 1 ให้ครบ 100% ก่อนจึงจะเปิดรอบถัดไปได้"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // If previous round ($r - 1) was 100% completed, now evaluate Round $r for assignment:
        if ($r > 1) {
            $prevRound = $r - 1;
            // Check candidates for Round $r
            $roundEligible = [];
            $roundAlreadyAssigned = 0;

            foreach ($allTargets as $tObj) {
                $cid = $tObj['cid'];
                $hasRoundR = ($cidState[$cid]['max_completed_round'] >= $r) || isset($cidState[$cid]['pending_rounds'][$r]);

                if ($hasRoundR) {
                    $roundAlreadyAssigned++;
                } else {
                    $roundEligible[] = [
                        'cid' => $cid,
                        'name' => $tObj['first_name'] . ' ' . $tObj['last_name'],
                        'house_no' => $tObj['house_no'],
                        'prev_vhv_id' => $cidState[$cid]['last_vhv_id'],
                        'prev_vhv_name' => $cidState[$cid]['last_vhv_name'] ?: 'ยังไม่ระบุ อสม.'
                    ];
                }
            }

            if (count($roundEligible) > 0) {
                $foundReadyRound = $r;
                $eligibleTargets = $roundEligible;
                $alreadyAssignedInTargetRound = $roundAlreadyAssigned;
                break;
            }

            // If no one is eligible for Round $r (everyone is assigned or completed):
            // Check if Round $r is also completed by everyone
            if ($completedRoundR < $totalTargets) {
                $pct = round(($completedRoundR / $totalTargets) * 100, 1);
                echo json_encode([
                    'status' => 'in_progress',
                    'can_assign' => false,
                    'current_round' => $r,
                    'target_round' => $r + 1,
                    'total_targets' => $totalTargets,
                    'round_completed' => $completedRoundR,
                    'round_pct' => $pct,
                    'already_assigned_count' => $roundAlreadyAssigned,
                    'message' => "มอบหมายงานรอบที่ {$r} ครบทุกคนแล้ว (ขณะนี้คัดกรองแล้ว {$completedRoundR}/{$totalTargets} ราย - {$pct}%)"
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }
        }
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
            WHERE (vhid_code = ? OR (CAST(vhv_moo AS UNSIGNED) = CAST(? AS UNSIGNED) AND (? = '' OR hoscode = ?)))
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

        if (function_exists('logUserActivity')) {
            logUserActivity('ASSIGNMENT', "มอบหมายงานรอบที่ {$foundReadyRound} อัตโนมัติ", [
                'tambon' => $tambon,
                'moo' => $moo,
                'assigned_count' => $insertCount,
                'target_round' => $foundReadyRound,
                'budget_year' => $selectedBudgetYear
            ]);
        }

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
