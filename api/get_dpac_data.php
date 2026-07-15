<?php
// api/get_dpac_data.php
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode([]);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$type = $_GET['type'] ?? '';
$moo = $_GET['moo'] ?? '';
$vhid = $_GET['vhid'] ?? '';
$hoscode = $_GET['hoscode'] ?? '';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
// Force restriction if non-super-admin
if ($admin_hoscode !== null) {
    $hoscode = $admin_hoscode;
}

try {
    if ($type === 'targets') {
        // Fetch active enrolled DPAC participants in the village
        $query = "
            SELECT e.enrollment_id, e.risk_type, e.enrolled_at, e.assigned_vhv_id, 
                   p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.sub_district_code,
                   v.vhv_name as assigned_vhv,
                   (SELECT COUNT(*) FROM dpac_followups f WHERE f.enrollment_id = e.enrollment_id) as total_rounds,
                   (SELECT COUNT(*) FROM dpac_followups f WHERE f.enrollment_id = e.enrollment_id AND f.status = 'pending') as pending_rounds
            FROM dpac_enrollments e
            JOIN target_population p ON e.cid = p.cid
            LEFT JOIN vhv_users v ON e.assigned_vhv_id = v.vhv_id
            WHERE e.budget_year = 2026 AND e.status = 'active'
              AND (p.vhid_code = ? OR (CAST(p.moo AS UNSIGNED) = CAST(? AS UNSIGNED) AND p.hoscode = ?))
        ";
        
        $params = [$vhid, $moo, $hoscode];
        
        if ($hoscode) {
            $hoscodes = get_query_hoscodes($hoscode);
            $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
            $query .= " AND p.hoscode IN ($inPlaceholders)";
            $params = array_merge($params, $hoscodes);
        }
        
        // Remove simulated/sandbox test populations if not in sandbox mode
        if (!isSandboxMode($hoscode)) {
            $query .= " AND p.cid NOT IN ('1234567890111', '1234567890112', '1234567890113', '1234567890114')";
        }
        
        $query .= " ORDER BY LENGTH(p.house_no), p.house_no";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        
    } elseif ($type === 'vhvs') {
        // Fetch VHVs in the village, count workloads
        $query = "
            SELECT v.vhv_id, v.vhv_name, 
                   (
                       SELECT COUNT(*) 
                       FROM dpac_followups f
                       WHERE f.vhv_id = v.vhv_id AND f.status = 'pending'
                   ) as pending_dpac_count,
                   (
                       SELECT COUNT(*) 
                       FROM task_assignments a 
                       JOIN target_population p ON a.target_cid = p.cid
                       WHERE a.vhv_id = v.vhv_id 
                         AND a.budget_year = 2026 
                         AND a.assignment_status = 'pending'
                         AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
                   ) as pending_screen_count
            FROM vhv_users v
            WHERE v.vhid_code = ? AND v.approved = 1
        ";
        
        $params = [$vhid];
        
        if ($hoscode) {
            $hoscodes = get_query_hoscodes($hoscode);
            $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
            $query .= " AND v.hoscode IN ($inPlaceholders)";
            $params = array_merge($params, $hoscodes);
        }
        
        // Remove simulated/sandbox test VHVs if not in sandbox mode
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
