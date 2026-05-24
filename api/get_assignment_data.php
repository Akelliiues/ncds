<?php
// api/get_assignment_data.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$type = $_GET['type'] ?? '';
$moo = $_GET['moo'] ?? '';
$vhid = $_GET['vhid'] ?? '';

if (empty($type) || empty($vhid)) {
    echo json_encode([]);
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

try {
    if ($type === 'targets') {
        $query = "
            SELECT p.cid, p.first_name, p.last_name, p.house_no, p.birth, 
                   TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) AS age,
                   v.vhv_name as assigned_vhv
            FROM target_population p
            LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.budget_year = 2026
            LEFT JOIN vhv_users v ON a.vhv_id = v.vhv_id
            WHERE p.vhid_code = ?
        ";
        $params = [$vhid];
        if ($admin_hoscode) {
            $hoscodes = [$admin_hoscode];
            if ($admin_hoscode === '10957') {
                $hoscodes[] = '10688';
            }
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
                   (SELECT COUNT(*) FROM task_assignments a WHERE a.vhv_id = v.vhv_id AND a.budget_year = 2026) as task_count
            FROM vhv_users v
            WHERE v.vhid_code = ? AND v.approved = 1
        ";
        $params = [$vhid];
        if ($admin_hoscode) {
            $hoscodes = [$admin_hoscode];
            if ($admin_hoscode === '10957') {
                $hoscodes[] = '10688';
            }
            $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
            $query .= " AND v.hoscode IN ($inPlaceholders)";
            $params = array_merge($params, $hoscodes);
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
