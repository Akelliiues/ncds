<?php
['admin_logged_in'] = true;
require_once __DIR__ . '/config/db.php';
$payload = '{"cid":"1234567890123","first_name":"Test","last_name":"User","sex":"1","birth":"1990-01-01","house_no":"99","moo":"1","sub_district_code":"341804","vhid_code":"34180401","hoscode":"03754","need_screen_dm":1,"need_screen_ht":1}';
$data = json_decode($payload, true);
try {
    $insertStmt = $pdo->prepare("
        INSERT INTO target_population 
        (cid, first_name, last_name, sex, birth, house_no, moo, sub_district_code, vhid_code, hoscode, need_screen_dm, need_screen_ht) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([
        $data['cid'], $data['first_name'], $data['last_name'], $data['sex'], $data['birth'], 
        $data['house_no'], $data['moo'], $data['sub_district_code'], $data['vhid_code'], 
        $data['hoscode'], $data['need_screen_dm'], $data['need_screen_ht']
    ]);
    echo "SUCCESS";
} catch(PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
