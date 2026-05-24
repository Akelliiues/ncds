<?php
// api/save_manual_target.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['cid'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
if ($admin_hoscode) {
    $allowed_hoscodes = [$admin_hoscode];
    if (!in_array($data['hoscode'], $allowed_hoscodes)) {
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์บันทึกข้อมูลในหน่วยบริการนี้']);
        exit();
    }
}

try {
    // Check if CID already exists
    $checkStmt = $pdo->prepare("SELECT cid, hoscode FROM target_population WHERE cid = ?");
    $checkStmt->execute([$data['cid']]);
    $exists = $checkStmt->fetch();

    if ($exists) {
        // If sub-admin, verify the existing record belongs to their hoscode
        if ($admin_hoscode && !in_array($exists['hoscode'], $allowed_hoscodes)) {
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์แก้ไขข้อมูลประชากรของหน่วยบริการอื่น']);
            exit();
        }
        // Update existing record
        $updateStmt = $pdo->prepare("
            UPDATE target_population 
            SET first_name=?, last_name=?, sex=?, birth=?, house_no=?, moo=?, 
                sub_district_code=?, vhid_code=?, hoscode=?, need_screen_dm=?, need_screen_ht=?, updated_at=CURRENT_TIMESTAMP
            WHERE cid=?
        ");
        $updateStmt->execute([
            $data['first_name'], $data['last_name'], $data['sex'], $data['birth'], 
            $data['house_no'], $data['moo'], $data['sub_district_code'], $data['vhid_code'], 
            $data['hoscode'], $data['need_screen_dm'], $data['need_screen_ht'], $data['cid']
        ]);
    } else {
        // Insert new record
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
    }
    
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
