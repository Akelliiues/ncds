<?php
// api/get_assignment_data.php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/demo_data.php';

if (DemoDataProvider::isDemoMode()) {
    $type = $_GET['type'] ?? '';
    $moo = intval($_GET['moo'] ?? 0);
    
    if ($type === 'vhvs') {
        $allVhvs = DemoDataProvider::getMockVhvs();
        $filteredVhvs = [];
        foreach ($allVhvs as $v) {
            if ($moo === 0 || intval($v['moo']) === $moo) {
                $filteredVhvs[] = [
                    'vhv_id' => $v['vhv_id'],
                    'vhv_name' => $v['vhv_name'],
                    'village_task_count' => $v['assigned_count'],
                    'total_task_count' => $v['assigned_count'] - $v['completed_count']
                ];
            }
        }
        echo json_encode($filteredVhvs, JSON_UNESCAPED_UNICODE);
        exit();
    } else {
        $allTargets = DemoDataProvider::getMockTargets();
        $filtered = [];
        foreach ($allTargets as $t) {
            if ($moo === 0 || intval($t['moo']) === $moo) {
                $cid = $t['cid'];
                $assignedVhv = $_SESSION['demo_assignments'][$cid] ?? ($t['assigned_vhv'] ?? '-');
                $filtered[] = [
                    'cid' => $t['cid'],
                    'first_name' => $t['first_name'],
                    'last_name' => $t['last_name'],
                    'house_no' => $t['house_no'],
                    'birth' => $t['birth'],
                    'age' => $t['age'],
                    'assigned_vhv' => $assignedVhv,
                    'assignment_status' => $t['assignment_status'],
                    'round_number' => $t['round_number'],
                    'max_completed_round' => ($t['round_number'] >= 2 ? 1 : 0),
                    'health_status_origin' => $t['health_status_origin'],
                    'need_screen_dm' => $t['need_screen_dm'],
                    'need_screen_ht' => $t['need_screen_ht']
                ];
            }
        }
        echo json_encode($filtered, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized', 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$type = $_GET['type'] ?? '';
$moo = $_GET['moo'] ?? '';
$vhid = $_GET['vhid'] ?? '';
$group = $_GET['group'] ?? 'main';

if (empty($type) || empty($vhid)) {
    echo json_encode([]);
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$hoscode = $admin_hoscode ? $admin_hoscode : ($_GET['hoscode'] ?? null);
$selectedBudgetYear = isset($_GET['budget_year']) && is_numeric($_GET['budget_year']) ? (int)$_GET['budget_year'] : (isset($_SESSION['active_budget_year']) ? (int)$_SESSION['active_budget_year'] : (function_exists('get_current_budget_year') ? get_current_budget_year() : 2026));

try {
    if ($type === 'targets') {
        $isSandboxVal = isSandboxMode($hoscode) ? 1 : 0;
        $query = "
            SELECT p.cid, p.first_name, p.last_name, p.house_no, p.birth, 
                   TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) AS age,
                   v.vhv_name as assigned_vhv, a.assignment_status, a.round_number, a.assignment_id,
                   GREATEST(
                       IFNULL((SELECT MAX(CASE WHEN ta.round_number IS NULL OR ta.round_number = 0 THEN 1 ELSE ta.round_number END) FROM task_assignments ta WHERE ta.target_cid = p.cid AND ta.assignment_status = 'completed' AND ta.budget_year = {$selectedBudgetYear} AND ta.is_sandbox = ?), 0),
                       IFNULL((SELECT MAX(CASE WHEN sr.round_number IS NULL OR sr.round_number = 0 THEN 1 ELSE sr.round_number END) FROM screening_results sr WHERE (sr.target_cid = p.cid OR sr.assignment_id IN (SELECT ta_sub.assignment_id FROM task_assignments ta_sub WHERE ta_sub.target_cid = p.cid)) AND sr.is_sandbox = ?), 0)
                   ) as max_completed_round,
                   COALESCE(
                       (
                           SELECT v2.vhv_name 
                           FROM task_assignments ta_prev 
                           LEFT JOIN vhv_users v2 ON ta_prev.vhv_id = v2.vhv_id 
                           WHERE ta_prev.target_cid = p.cid AND ta_prev.assignment_status = 'completed' AND ta_prev.budget_year = {$selectedBudgetYear} AND ta_prev.is_sandbox = ?
                           ORDER BY ta_prev.round_number DESC, ta_prev.assignment_id DESC LIMIT 1
                       ),
                       (
                           SELECT v3.vhv_name
                           FROM screening_results sr_prev
                           JOIN task_assignments ta3 ON sr_prev.assignment_id = ta3.assignment_id
                           JOIN vhv_users v3 ON ta3.vhv_id = v3.vhv_id
                           WHERE sr_prev.target_cid = p.cid AND sr_prev.is_sandbox = ?
                           ORDER BY sr_prev.round_number DESC, sr_prev.screening_id DESC LIMIT 1
                       )
                   ) as prev_vhv_name,
                   p.health_status_origin, p.need_screen_dm, p.need_screen_ht
            FROM target_population p
            LEFT JOIN task_assignments a ON a.assignment_id = (
                SELECT assignment_id FROM task_assignments ta 
                WHERE ta.target_cid = p.cid AND ta.budget_year = {$selectedBudgetYear} AND ta.assignment_status = 'pending' AND ta.is_sandbox = ?
                ORDER BY ta.round_number DESC, ta.assignment_id DESC LIMIT 1
            )
            LEFT JOIN vhv_users v ON a.vhv_id = v.vhv_id
            WHERE (p.vhid_code = ? OR (CAST(p.moo AS UNSIGNED) = CAST(? AS UNSIGNED) AND p.hoscode = ?))
        ";
        
        // Filter by target group
        if ($group === 'suspect') {
            // Suspect group requires age 35+ and not already an active target
            $query .= " AND p.need_screen_dm = 0 AND p.need_screen_ht = 0 AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35";
        } elseif ($group === 'under_35_risk') {
            // Specific under-35 risk group (must have risk flag or manual flag)
            $query .= " AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) < 35 AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1 OR COALESCE(p.is_manual, 0) = 1)";
        } else {
            // Active target group (Main Baseline Targets: age 35+ and needs screening)
            $query .= " AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1) AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35";
        }

        
        // กรองข้อมูลประชากรจำลองทดสอบออกในโหมดจริง
        if (!isSandboxMode($hoscode)) {
            $query .= " AND p.cid NOT IN ('1234567890111', '1234567890112', '1234567890113', '1234567890114')";
        }
        
        $target_hoscode = $admin_hoscode ? $admin_hoscode : ($_GET['hoscode'] ?? null);
        $hoscodeParam = $target_hoscode ?: '';
        $params = [$isSandboxVal, $isSandboxVal, $isSandboxVal, $isSandboxVal, $isSandboxVal, $vhid, $moo, $hoscodeParam];
        if ($target_hoscode) {
            $hoscodes = get_query_hoscodes($target_hoscode);
            $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
            $query .= " AND p.hoscode IN ($inPlaceholders)";
            $params = array_merge($params, $hoscodes);
        }
        $query .= " ORDER BY LENGTH(p.house_no), p.house_no";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($type === 'vhvs') {
        $isSandboxVal = isSandboxMode($hoscode) ? 1 : 0;
        $legacyVhid = str_replace('3420', '3418', $vhid);
        $target_hoscode = $admin_hoscode ? $admin_hoscode : ($_GET['hoscode'] ?? null);

        $query = "
            SELECT v.vhv_id, v.vhv_name, v.vhv_moo, v.vhid_code, v.hoscode,
                   (
                       (
                           SELECT COUNT(*) 
                           FROM task_assignments a 
                           JOIN target_population p ON a.target_cid = p.cid
                           WHERE a.vhv_id = v.vhv_id 
                             AND a.budget_year = {$selectedBudgetYear} 
                             AND a.assignment_status = 'pending'
                             AND a.is_sandbox = :is_sandbox1
                             AND (
                                 ((p.need_screen_dm = 1 OR p.need_screen_ht = 1) AND (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35 OR COALESCE(p.is_manual, 0) = 1))
                                 OR p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
                                 OR COALESCE(p.is_manual, 0) = 1
                             )
                             AND (
                                 v.vhv_moo IS NULL OR v.vhv_moo = '' OR p.moo IS NULL OR p.moo = ''
                                 OR CAST(p.moo AS UNSIGNED) = CAST(v.vhv_moo AS UNSIGNED)
                                 OR p.vhid_code = v.vhid_code
                             )
                       ) + (
                           SELECT COUNT(*) 
                           FROM dpac_followups f
                           JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
                           JOIN target_population p ON e.cid = p.cid
                           WHERE f.vhv_id = v.vhv_id
                             AND f.status = 'pending'
                             AND f.is_sandbox = :is_sandbox2
                       )
                   ) as total_task_count,
                   (
                       (
                           SELECT COUNT(*) 
                           FROM task_assignments a 
                           JOIN target_population p ON a.target_cid = p.cid
                           LEFT JOIN screening_results sr ON a.assignment_id = sr.assignment_id
                           WHERE a.vhv_id = v.vhv_id 
                             AND a.budget_year = {$selectedBudgetYear} 
                             AND a.is_sandbox = :is_sandbox3
                             AND (
                                 ((p.need_screen_dm = 1 OR p.need_screen_ht = 1) AND (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35 OR COALESCE(p.is_manual, 0) = 1))
                                 OR p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
                                 OR COALESCE(p.is_manual, 0) = 1
                                 OR sr.screening_id IS NOT NULL
                             )
                             AND (
                                 sr.screening_id IS NOT NULL
                                 OR v.vhv_moo IS NULL OR v.vhv_moo = '' OR p.moo IS NULL OR p.moo = ''
                                 OR CAST(p.moo AS UNSIGNED) = CAST(v.vhv_moo AS UNSIGNED)
                                 OR p.vhid_code = v.vhid_code
                             )
                       ) + (
                           SELECT COUNT(*) 
                           FROM dpac_followups f
                           JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
                           JOIN target_population p ON e.cid = p.cid
                           WHERE f.vhv_id = v.vhv_id
                             AND f.is_sandbox = :is_sandbox4
                       )
                   ) as overall_total_count,
                   (
                       (
                           SELECT COUNT(*) 
                           FROM task_assignments a 
                           JOIN target_population p ON a.target_cid = p.cid
                           WHERE a.vhv_id = v.vhv_id 
                             AND a.budget_year = {$selectedBudgetYear} 
                             AND (p.vhid_code = :vhid1 OR (CAST(p.moo AS UNSIGNED) = CAST(:moo1 AS UNSIGNED) AND p.hoscode = v.hoscode))
                             AND a.is_sandbox = :is_sandbox5
                             AND (
                                 ((p.need_screen_dm = 1 OR p.need_screen_ht = 1) AND (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35 OR COALESCE(p.is_manual, 0) = 1))
                                 OR p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
                                 OR COALESCE(p.is_manual, 0) = 1
                             )
                       ) + (
                           SELECT COUNT(*) 
                           FROM dpac_followups f
                           JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
                           JOIN target_population p ON e.cid = p.cid
                           WHERE f.vhv_id = v.vhv_id
                             AND (p.vhid_code = :vhid2 OR (CAST(p.moo AS UNSIGNED) = CAST(:moo2 AS UNSIGNED) AND p.hoscode = v.hoscode))
                             AND f.is_sandbox = :is_sandbox6
                       )
                   ) as village_task_count
              FROM vhv_users v
              WHERE (
                  v.vhid_code = :vhid3 
                  OR v.vhid_code = :vhid_legacy
                  OR (CAST(v.vhv_moo AS UNSIGNED) = CAST(:moo_match AS UNSIGNED) AND (:match_hoscode = '' OR v.hoscode = :match_hoscode2))
                  OR v.vhid_code LIKE :vhid_like
              )
              AND (v.approved = 1 OR v.approved IS NULL)
        ";
        
        $params = [
            'vhid1'         => $vhid,
            'moo1'          => $moo,
            'vhid2'         => $vhid,
            'moo2'          => $moo,
            'vhid3'         => $vhid,
            'vhid_legacy'   => $legacyVhid,
            'moo_match'     => $moo,
            'match_hoscode' => $target_hoscode ?: '',
            'match_hoscode2'=> $target_hoscode ?: '',
            'vhid_like'     => "%" . str_pad($moo, 2, '0', STR_PAD_LEFT),
            'is_sandbox1'   => $isSandboxVal,
            'is_sandbox2'   => $isSandboxVal,
            'is_sandbox3'   => $isSandboxVal,
            'is_sandbox4'   => $isSandboxVal,
            'is_sandbox5'   => $isSandboxVal,
            'is_sandbox6'   => $isSandboxVal
        ];

        if ($target_hoscode) {
            $hoscodes = get_query_hoscodes($target_hoscode);
            $inKeys = [];
            foreach ($hoscodes as $i => $code) {
                $key = "hoscode_" . $i;
                $inKeys[] = ":" . $key;
                $params[$key] = $code;
            }
            $inPlaceholders = implode(',', $inKeys);
            $query .= " AND v.hoscode IN ($inPlaceholders)";
        }
        
        // กรองข้อมูล อสม. จำลองทดสอบออกในโหมดจริง
        if (!isSandboxMode($hoscode)) {
            $query .= " AND v.vhv_id NOT IN ('1001', '1002', '1003')";
        }
        
        $query .= " ORDER BY v.vhv_name";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode([]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
