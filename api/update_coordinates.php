<?php
// api/update_coordinates.php
// API endpoint to update house coordinates (latitude/longitude) for target_population
session_start();
header('Content-Type: application/json; charset=utf-8');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง กรุณาเข้าสู่ระบบใหม่']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

$cid = $input['cid'] ?? null;
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;

// Validate required fields
if (!$cid || $latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'กรุณาระบุ cid, latitude และ longitude']);
    exit();
}

// Validate coordinate ranges (Thailand rough bounds)
$latitude = floatval($latitude);
$longitude = floatval($longitude);

if ($latitude < 5.0 || $latitude > 21.0 || $longitude < 97.0 || $longitude > 106.0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'พิกัดไม่อยู่ในขอบเขตประเทศไทย']);
    exit();
}

try {
    // Check if target exists
    $checkStmt = $pdo->prepare("SELECT cid, first_name, last_name, house_no, moo FROM target_population WHERE cid = ?");
    $checkStmt->execute([$cid]);
    $target = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'ไม่พบรายการเป้าหมาย CID: ' . $cid]);
        exit();
    }
    
    // Optionally check admin_hoscode permission
    $admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
    if ($admin_hoscode) {
        $hoscodes = [$admin_hoscode];
        if ($admin_hoscode === '10957') {
            $hoscodes[] = '10688';
        }
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $permStmt = $pdo->prepare("SELECT cid FROM target_population WHERE cid = ? AND hoscode IN ($inPlaceholders)");
        $permStmt->execute(array_merge([$cid], $hoscodes));
        if (!$permStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์แก้ไขพิกัดเป้าหมายนอกเขตรับผิดชอบ']);
            exit();
        }
    }
    
    // Update coordinates
    $updateStmt = $pdo->prepare("UPDATE target_population SET latitude = ?, longitude = ?, updated_at = NOW() WHERE cid = ?");
    $updateStmt->execute([$latitude, $longitude, $cid]);
    
    echo json_encode([
        'success' => true,
        'message' => 'อัปเดตพิกัดสำเร็จ สำหรับ ' . $target['first_name'] . ' ' . $target['last_name'] . ' บ้านเลขที่ ' . $target['house_no'] . ' หมู่ ' . $target['moo'],
        'data' => [
            'cid' => $cid,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $target['first_name'] . ' ' . $target['last_name']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
