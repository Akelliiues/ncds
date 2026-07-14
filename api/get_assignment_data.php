<?php
// api/get_assignment_data.php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['error' => 'Unauthorized']);
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

try {
    if ($type === 'targets') {
        $query = "
            SELECT p.cid, p.first_name, p.last_name, p.house_no, p.birth, 
                   TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) AS age,
                   v.vhv_name as assigned_vhv, a.assignment_status,
                   p.health_status_origin, p.need_screen_dm, p.need_screen_ht
            FROM target_population p
            LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.budget_year = 2026
            LEFT JOIN vhv_users v ON a.vhv_id = v.vhv_id
            WHERE (p.vhid_code = ? OR (CAST(p.moo AS UNSIGNED) = CAST(? AS UNSIGNED) AND p.hoscode = ?))
              AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35
        ";
        
        // Filter by target group
        if ($group === 'suspect') {
            $query .= " AND p.need_screen_dm = 0 AND p.need_screen_ht = 0";
        } else {
            $query .= " AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)";
        }
        
        // กรองข้อมูลประชากรจำลองทดสอบออกในโหมดจริง
        if (!isSandboxMode($hoscode)) {
            $query .= " AND p.cid NOT IN ('1234567890111', '1234567890112', '1234567890113', '1234567890114')";
        }
        
        $target_hoscode = $admin_hoscode ? $admin_hoscode : ($_GET['hoscode'] ?? null);
        $hoscodeParam = $target_hoscode ?: '';
        $params = [$vhid, $moo, $hoscodeParam];
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
        $query = "
            SELECT v.vhv_id, v.vhv_name, 
                   (
                       SELECT COUNT(*) 
                       FROM task_assignments a 
                       JOIN target_population p ON a.target_cid = p.cid
                       WHERE a.vhv_id = v.vhv_id 
                         AND a.budget_year = 2026 
                         AND a.assignment_status = 'pending'
                   ) as total_task_count,
                   (
                       SELECT COUNT(*) 
                       FROM task_assignments a 
                       JOIN target_population p ON a.target_cid = p.cid
                       WHERE a.vhv_id = v.vhv_id 
                         AND a.budget_year = 2026 
                         AND a.assignment_status = 'pending'
                         AND p.vhid_code = ?
                   ) as village_task_count
            FROM vhv_users v
            WHERE v.vhid_code = ? AND v.approved = 1
        ";
        $params = [$vhid, $vhid];
        $target_hoscode = $admin_hoscode ? $admin_hoscode : ($_GET['hoscode'] ?? null);
        if ($target_hoscode) {
            $hoscodes = get_query_hoscodes($target_hoscode);
            $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
            $query .= " AND v.hoscode IN ($inPlaceholders)";
            $params = array_merge($params, $hoscodes);
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
