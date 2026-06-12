<?php
// scratch/get_test_data.php
if (function_exists('opcache_reset')) {
    opcache_reset();
}

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // หา VHV คนหนึ่ง
    $vhv = $pdo->query("SELECT vhv_id, vhv_name, vhid_code, hoscode FROM vhv_users WHERE approved = 1 LIMIT 1")->fetch();
    
    // หาบ้านในเขตของ VHV คนนี้ที่มีงานมอบหมาย
    $assigned_house = null;
    if ($vhv) {
        $assigned_house = $pdo->prepare("
            SELECT p.hid, p.cid, p.vhid_code, p.hoscode, p.first_name
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            WHERE a.vhv_id = ? AND a.budget_year = 2026
            LIMIT 1
        ");
        $assigned_house->execute([$vhv['vhv_id']]);
        $assigned_house = $assigned_house->fetch();
    }
    
    // หาบ้านในเขตของ VHV คนนี้ แต่ไม่มีงานมอบหมายให้ VHV คนนี้ (NO_ASSIGNMENT)
    $no_assign_house = null;
    if ($vhv) {
        $no_assign_house = $pdo->prepare("
            SELECT p.hid, p.cid, p.vhid_code, p.hoscode, p.first_name
            FROM target_population p
            LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.vhv_id = ? AND a.budget_year = 2026
            WHERE p.vhid_code = ? AND a.assignment_id IS NULL
            LIMIT 1
        ");
        $no_assign_house->execute([$vhv['vhv_id'], $vhv['vhid_code']]);
        $no_assign_house = $no_assign_house->fetch();
    }
    
    // หาบ้านนอกเขตของ VHV คนนี้ (CROSS_DISTRICT_UNAUTHORIZED_SCAN_BLOCKED)
    $cross_house = null;
    if ($vhv) {
        $cross_house = $pdo->prepare("
            SELECT p.hid, p.cid, p.vhid_code, p.hoscode, p.first_name
            FROM target_population p
            WHERE p.vhid_code <> ? AND p.vhid_code IS NOT NULL AND p.vhid_code <> ''
            LIMIT 1
        ");
        $cross_house->execute([$vhv['vhid_code']]);
        $cross_house = $cross_house->fetch();
    }

    echo json_encode([
        'status' => 'success',
        'vhv' => $vhv,
        'assigned_house' => $assigned_house,
        'no_assign_house' => $no_assign_house,
        'cross_house' => $cross_house
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
