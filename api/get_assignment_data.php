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
        ";
        
        // Filter by target group
        if ($group === 'suspect') {
            // Suspect group requires age 35+ and not already an active target
            $query .= " AND p.need_screen_dm = 0 AND p.need_screen_ht = 0 AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35";
        } else {
            // Active target group allows any age
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
        $group = $_GET['group'] ?? '';
        $query = "
            SELECT v.vhv_id, v.vhv_name, 
                   (
                       (
                           SELECT COUNT(*) 
                           FROM task_assignments a 
                           JOIN target_population p ON a.target_cid = p.cid
                           WHERE a.vhv_id = v.vhv_id 
                             AND a.budget_year = 2026 
                             AND a.assignment_status = 'pending'
                             AND (
                                 (:group1 = 'suspect' AND p.need_screen_dm = 0 AND p.need_screen_ht = 0 AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
                                 OR (:group2 != 'suspect' AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1))
                             )
                       ) + (
                           SELECT COUNT(*) 
                           FROM dpac_followups f
                           WHERE f.vhv_id = v.vhv_id
                             AND f.status = 'pending'
                       )
                   ) as total_task_count,
                   (
                       (
                           SELECT COUNT(*) 
                           FROM task_assignments a 
                           JOIN target_population p ON a.target_cid = p.cid
                           WHERE a.vhv_id = v.vhv_id 
                             AND a.budget_year = 2026 
                             AND p.vhid_code = :vhid1
                             AND (
                                 (:group3 = 'suspect' AND p.need_screen_dm = 0 AND p.need_screen_ht = 0 AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
                                 OR (:group4 != 'suspect' AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1))
                             )
                       ) + (
                           SELECT COUNT(*) 
                           FROM dpac_followups f
                           JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
                           JOIN target_population p ON e.cid = p.cid
                           WHERE f.vhv_id = v.vhv_id
                             AND p.vhid_code = :vhid2
                       )
                   ) as village_task_count
            FROM vhv_users v
            WHERE v.vhid_code = :vhid3 AND v.approved = 1
        ";
        
        $params = [
            'group1' => $group,
            'group2' => $group,
            'group3' => $group,
            'group4' => $group,
            'vhid1'  => $vhid,
            'vhid2'  => $vhid,
            'vhid3'  => $vhid
        ];
        $target_hoscode = $admin_hoscode ? $admin_hoscode : ($_GET['hoscode'] ?? null);
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
