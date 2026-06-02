<?php
// api/check_qrcode.php
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['vhv_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'เข้าสู่ระบบหมดอายุ กรุณาเข้าสู่ระบบใหม่'
    ]);
    exit();
}

$vhvId = $_SESSION['vhv_id'];
$vhidCode = $_SESSION['vhid_code'];
$hoscode = $_SESSION['hoscode'];

$hid = $_POST['hid'] ?? '';
$lat = (float)($_POST['lat'] ?? 0);
$lng = (float)($_POST['lng'] ?? 0);

if (empty($hid)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบข้อมูลรหัสบ้าน (HID) หรือรหัสบุคคล'
    ]);
    exit();
}

try {
    // Check if input is a 13-digit CID or raw HID
    $isCid = preg_match('/^\d{13}$/', $hid);

    if ($isCid) {
        // 1. Check assignments mapping to this specific CID
        $stmt = $pdo->prepare("
            SELECT a.assignment_id, p.vhid_code, p.hoscode, p.first_name, p.last_name
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            WHERE p.cid = ? AND a.vhv_id = ? AND a.budget_year = 2026
        ");
        $stmt->execute([$hid, $vhvId]);
        $assignments = $stmt->fetchAll();

        // 2. PDPA Cross-District Lock:
        $houseStmt = $pdo->prepare("SELECT vhid_code, hoscode FROM target_population WHERE cid = ? LIMIT 1");
        $houseStmt->execute([$hid]);
        $houseInfo = $houseStmt->fetch();
    } else {
        // 1. Check assignments mapping to targets in JHCIS house
        $stmt = $pdo->prepare("
            SELECT a.assignment_id, p.vhid_code, p.hoscode, p.first_name, p.last_name
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            WHERE p.hid = ? AND a.vhv_id = ? AND a.budget_year = 2026
        ");
        $stmt->execute([$hid, $vhvId]);
        $assignments = $stmt->fetchAll();

        // 2. PDPA Cross-District Lock:
        $houseStmt = $pdo->prepare("SELECT vhid_code, hoscode FROM target_population WHERE hid = ? LIMIT 1");
        $houseStmt->execute([$hid]);
        $houseInfo = $houseStmt->fetch();
    }

    $isAuthorized = true;
    
    if (!$houseInfo) {
        // House not found in staging database, lock it
        $isAuthorized = false;
    } else {
        // If the house village (vhid_code) or hospital (hoscode) doesn't match the VHV's village/hospital
        // OR no assignment exists for this VHV for this house
        if ($houseInfo['vhid_code'] !== $vhidCode || empty($assignments)) {
            $isAuthorized = false;
        }
    }

    if (!$isAuthorized) {
        // Security Log writing
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/security_log.json';
        $logData = [];
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $logData = json_decode($logContent, true) ?: [];
        }

        // Add new log entry
        $logData[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'vhv_id' => $vhvId,
            'scanned_hid' => $hid,
            'vhv_latitude' => $lat,
            'vhv_longitude' => $lng,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'incident_type' => 'CROSS_DISTRICT_UNAUTHORIZED_SCAN_BLOCKED'
        ];

        file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode([
            'status' => 'locked',
            'message' => 'ความปลอดภัย: บล็อกการแสดงข้อมูลเนื่องจากสแกนบ้านนอกเขตรับผิดชอบของท่าน'
        ]);
        exit();
    }

    // Within area and assigned, return list of assignments
    echo json_encode([
        'status' => 'success',
        'data' => $assignments
    ]);
    exit();

} catch (\PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการตรวจสอบฐานข้อมูล: ' . $e->getMessage()
    ]);
    exit();
}
