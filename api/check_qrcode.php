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
        // Prioritize the logged-in VHV's village (vhid_code) and hospital (hoscode) 
        // to handle non-globally-unique HIDs correctly.
        $houseStmt = $pdo->prepare("
            SELECT vhid_code, hoscode 
            FROM target_population 
            WHERE hid = ? 
            ORDER BY 
                CASE WHEN vhid_code = ? THEN 0 ELSE 1 END,
                CASE WHEN hoscode = ? THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $houseStmt->execute([$hid, $vhidCode, $hoscode]);
        $houseInfo = $houseStmt->fetch();
    }

    $isAuthorized = true;
    $incidentType = 'CROSS_DISTRICT_UNAUTHORIZED_SCAN_BLOCKED';
    
    if (!$houseInfo) {
        // House not found in staging database, lock it
        $isAuthorized = false;
        $incidentType = 'UNAUTHORIZED_SCAN';
    } else {
        // If the house village (vhid_code) or hospital (hoscode) doesn't match the VHV's village/hospital
        // OR no assignment exists for this VHV for this house
        if ($houseInfo['vhid_code'] !== $vhidCode) {
            $isAuthorized = false;
            $incidentType = 'CROSS_DISTRICT_UNAUTHORIZED_SCAN_BLOCKED';
        } elseif (empty($assignments)) {
            $isAuthorized = false;
            $incidentType = 'NO_ASSIGNMENT';
        }
    }

    if (!$isAuthorized) {
        // 1. JSON Log writing (as a backup)
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
            'incident_type' => $incidentType
        ];

        file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 2. Database Log writing
        $vhvName = $_SESSION['vhv_name'] ?? null;
        $vhvHoscode = $_SESSION['hoscode'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            // Ensure table exists (in case admin hasn't opened security_log.php yet)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS scan_security_log (
                    id           INT AUTO_INCREMENT PRIMARY KEY,
                    logged_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    vhv_id       VARCHAR(20)  NOT NULL,
                    vhv_name     VARCHAR(120) DEFAULT NULL,
                    hoscode      VARCHAR(10)  DEFAULT NULL,
                    scanned_code VARCHAR(30)  NOT NULL,
                    scan_lat     DECIMAL(10,7) DEFAULT NULL,
                    scan_lng     DECIMAL(10,7) DEFAULT NULL,
                    ip_address   VARCHAR(45)  DEFAULT NULL,
                    user_agent   TEXT         DEFAULT NULL,
                    incident_type VARCHAR(60) NOT NULL DEFAULT 'UNAUTHORIZED_SCAN',
                    INDEX idx_logged_at (logged_at),
                    INDEX idx_vhv_id    (vhv_id),
                    INDEX idx_hoscode   (hoscode)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $logStmt = $pdo->prepare("
                INSERT INTO scan_security_log (vhv_id, vhv_name, hoscode, scanned_code, scan_lat, scan_lng, ip_address, user_agent, incident_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $logStmt->execute([
                $vhvId,
                $vhvName,
                $vhvHoscode,
                $hid,
                $lat > 0 ? $lat : null,
                $lng > 0 ? $lng : null,
                $ipAddress,
                $userAgent,
                $incidentType
            ]);
        } catch (\PDOException $dbEx) {
            // Ignore DB log write error to prevent app crash
        }

        // Return error message to VHV app
        $msgText = 'ความปลอดภัย: บล็อกการแสดงข้อมูลเนื่องจากสแกนบ้านนอกเขตรับผิดชอบของท่าน';
        if ($incidentType === 'NO_ASSIGNMENT') {
            $msgText = 'สิทธิ์การเข้าถึง: ท่านไม่ได้รับมอบหมายงานคัดกรองบุคคล/บ้านหลังนี้ในปีงบประมาณปัจจุบัน';
        } elseif ($incidentType === 'UNAUTHORIZED_SCAN') {
            $msgText = 'สิทธิ์การเข้าถึง: ไม่พบรหัสบ้านหรือเลขบัตรประชาชนนี้ในฐานข้อมูลระบบ';
        }

        echo json_encode([
            'status' => 'locked',
            'message' => $msgText
        ]);
        exit();
    }

    // Within area and assigned, log success scan and return list of assignments
    $vhvName = $_SESSION['vhv_name'] ?? null;
    $vhvHoscode = $_SESSION['hoscode'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    try {
        // Ensure table exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS scan_security_log (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                logged_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                vhv_id       VARCHAR(20)  NOT NULL,
                vhv_name     VARCHAR(120) DEFAULT NULL,
                hoscode      VARCHAR(10)  DEFAULT NULL,
                scanned_code VARCHAR(30)  NOT NULL,
                scan_lat     DECIMAL(10,7) DEFAULT NULL,
                scan_lng     DECIMAL(10,7) DEFAULT NULL,
                ip_address   VARCHAR(45)  DEFAULT NULL,
                user_agent   TEXT         DEFAULT NULL,
                incident_type VARCHAR(60) NOT NULL DEFAULT 'UNAUTHORIZED_SCAN',
                INDEX idx_logged_at (logged_at),
                INDEX idx_vhv_id    (vhv_id),
                INDEX idx_hoscode   (hoscode)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $logStmt = $pdo->prepare("
            INSERT INTO scan_security_log (vhv_id, vhv_name, hoscode, scanned_code, scan_lat, scan_lng, ip_address, user_agent, incident_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'AUTHORIZED_SCAN')
        ");
        $logStmt->execute([
            $vhvId,
            $vhvName,
            $vhvHoscode,
            $hid,
            $lat > 0 ? $lat : null,
            $lng > 0 ? $lng : null,
            $ipAddress,
            $userAgent
        ]);
    } catch (\PDOException $dbEx) {
        // Ignore DB log write error
    }

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
