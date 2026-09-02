<?php
// api/check_qrcode.php
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../config/demo_data.php';

if (DemoDataProvider::isDemoMode()) {
    $hid = trim($_POST['hid'] ?? '');
    
    // 1. ถอดรหัสหากเป็น URL หรือมี Query String
    if (strpos($hid, '?') !== false || strpos($hid, 'http://') === 0 || strpos($hid, 'https://') === 0) {
        $parsed = parse_url($hid);
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $q);
            if (!empty($q['cid'])) $hid = $q['cid'];
            elseif (!empty($q['hid'])) $hid = $q['hid'];
            elseif (!empty($q['code'])) $hid = $q['code'];
        }
    }
    
    // 2. ถอดรหัสหากเป็น JSON string
    if (strpos($hid, '{') === 0) {
        $json = json_decode($hid, true);
        if (is_array($json)) {
            $hid = $json['cid'] ?? $json['hid'] ?? $hid;
        }
    }
    
    // 3. ตัดคำนำหน้าภาษาไทย e.g. "บ้าน 12/1", "บ้านเลขที่ 88"
    $vinylScenario = DemoDataProvider::resolveVinylQrCode($hid);
    if ($vinylScenario) {
        $hid = $vinylScenario['canonical_code'];
    }
    $cleanHid = trim(preg_replace('/^(บ้านเลขที่|บ้าน|ม\.)\s*/u', '', $hid));
    
    $targets = DemoDataProvider::getMockTargets();
    
    // Find matching target in 10 mock targets
    $matched = null;
    foreach ($targets as $t) {
        if ($t['cid'] === $hid || $t['cid'] === $cleanHid || 
            (isset($t['hid']) && ($t['hid'] === $hid || $t['hid'] === $cleanHid)) ||
            $t['house_no'] === $hid || $t['house_no'] === $cleanHid || 
            (isset($t['assignment_id']) && ($t['assignment_id'] === $hid || $t['assignment_id'] === $cleanHid))) {
            $matched = $t;
            break;
        }
    }
    
    // Special test code aliases
    if (!$matched) {
        if (strpos($hid, '1007') !== false || strpos($hid, '99/4') !== false || strpos($hid, 'อนันต์') !== false || $cleanHid === '99/4' || $hid === 'DEMO_HOUSE_99_4') {
            $matched = $targets[6]; // อนันต์ เจริญสุข (ม.4)
        } elseif (strpos($hid, '1008') !== false || strpos($hid, '23/2') !== false || strpos($hid, 'อุบล') !== false || $cleanHid === '23/2' || $hid === 'DEMO_HOUSE_23_2') {
            $matched = $targets[7]; // อุบล มีสุข (ม.5)
        } elseif (strpos($hid, 'DEMO_MOO1') !== false || $hid === 'DEMO_HOUSE_12_1' || $hid === 'DEMO_HID_1' || $cleanHid === '12/1' || $cleanHid === '45/2') {
            $matched = ($cleanHid === '45/2') ? $targets[1] : $targets[0];
        } elseif (strpos($hid, 'DEMO_MOO2') !== false || $hid === 'DEMO_HOUSE_88_2' || $hid === 'DEMO_HOUSE_101_2' || $cleanHid === '88' || $cleanHid === '101') {
            $matched = ($cleanHid === '101' || $hid === 'DEMO_HOUSE_101_2') ? $targets[3] : $targets[2];
        } elseif (strpos($hid, 'DEMO_MOO3') !== false || $hid === 'DEMO_HOUSE_15_3' || $hid === 'DEMO_HOUSE_22_3' || $cleanHid === '15/3' || $cleanHid === '22') {
            $matched = ($cleanHid === '22' || $hid === 'DEMO_HOUSE_22_3') ? $targets[5] : $targets[4];
        } elseif (strpos($hid, 'DEMO_MOO4') !== false || $hid === 'DEMO_HOUSE_54_4' || $hid === 'DEMO_HOUSE_76_4' || $cleanHid === '54' || $cleanHid === '76/1') {
            $matched = $targets[6];
        } elseif (strpos($hid, 'DEMO_MOO5') !== false || $hid === 'DEMO_HOUSE_9_5' || $hid === 'DEMO_HOUSE_33_5' || $cleanHid === '9/1' || $cleanHid === '33') {
            $matched = $targets[7];
        }
    }

    if (!$matched) {
        $matched = $targets[0];
    }

    // กรณีที่ 1: จำลองสถานการณ์ "ยังไม่ได้รับมอบหมายงาน" (อนันต์ เจริญสุข บ้าน 99/4 ม.4 HID 1007)
    if (($matched['assignment_status'] ?? '') === 'unassigned' || ($matched['health_case'] ?? '') === 'unassigned_lock' || ($matched['hid'] ?? '') === '1007' || ($matched['house_no'] ?? '') === '99/4') {
        echo json_encode([
            'status' => 'error',
            'error_code' => 'UNASSIGNED_TASK',
            'moo' => $matched['moo'] ?? '4',
            'lock_title' => 'สิทธิ์การเข้าถึง: ยังไม่ได้รับมอบหมายงาน (หมู่ ' . ($matched['moo'] ?? '4') . ')',
            'message' => 'รหัสบ้านเลขที่ ' . htmlspecialchars($matched['house_no'] ?? '99/4') . ' ม.' . ($matched['moo'] ?? '4') . ' (คุณ' . htmlspecialchars($matched['first_name'] . ' ' . $matched['last_name']) . ') ท่านไม่ได้รับมอบหมายงานคัดกรองบุคคล/บ้านหลังนี้ในรอบปัจจุบัน',
            'sub_message' => 'กรุณาประสานเจ้าหน้าที่ รพ.สต. เพื่อทำการมอบหมายงานก่อนเริ่มคัดกรอง',
            'is_demo' => true
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // กรณีที่ 2: จำลองสถานการณ์ "สแกนข้ามเขตรับผิดชอบ" (อุบล มีสุข บ้าน 23/2 ม.5 HID 1008)
    if (($matched['assignment_status'] ?? '') === 'out_of_territory' || ($matched['health_case'] ?? '') === 'outofarea_lock' || ($matched['hid'] ?? '') === '1008' || ($matched['house_no'] ?? '') === '23/2') {
        echo json_encode([
            'status' => 'error',
            'error_code' => 'OUT_OF_TERRITORY',
            'moo' => $matched['moo'] ?? '5',
            'lock_title' => 'ความปลอดภัย (PDPA): บล็อกการเข้าถึงข้ามเขต (หมู่ ' . ($matched['moo'] ?? '5') . ')',
            'message' => 'รหัสบ้านเลขที่ ' . htmlspecialchars($matched['house_no'] ?? '23/2') . ' ม.' . ($matched['moo'] ?? '5') . ' (คุณ' . htmlspecialchars($matched['first_name'] . ' ' . $matched['last_name']) . ') บล็อกการแสดงข้อมูลเนื่องจากสแกนบ้านนอกเขตรับผิดชอบของท่าน',
            'sub_message' => 'ระบบได้บันทึกการพยายามเข้าถึงข้ามเขต และปฏิบัติตามมาตรการคุ้มครองข้อมูลส่วนบุคคล (PDPA)',
            'is_demo' => true
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // กรณีเคสทั่วไป (หมู่ 1, หมู่ 2): สแกนผ่านและเข้าสู่แบบฟอร์มคัดกรองได้ปกติ
    echo json_encode([
        'status' => 'success',
        'hid' => $matched['cid'],
        'house_no' => $matched['house_no'],
        'moo' => $matched['moo'],
        'residents' => [$matched],
        'is_demo' => true
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

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

$hid = trim((string)($_POST['hid'] ?? ''));
$lat = (float)($_POST['lat'] ?? 0);
$lng = (float)($_POST['lng'] ?? 0);

// Printed house QR codes contain a URL such as qr.php?code=...
// Normalize all supported QR formats again on the server so authorization
// never depends on which scanner/client version sent the request.
if ($hid !== '' && (strpos($hid, '?') !== false || preg_match('#^https?://#i', $hid))) {
    $parsed = parse_url($hid);
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $queryParams);
        foreach (['cid', 'hid', 'code'] as $key) {
            if (isset($queryParams[$key]) && is_scalar($queryParams[$key]) && trim((string)$queryParams[$key]) !== '') {
                $hid = trim((string)$queryParams[$key]);
                break;
            }
        }
    }
}

if ($hid !== '' && strpos($hid, '{') === 0) {
    $decoded = json_decode($hid, true);
    if (is_array($decoded)) {
        foreach (['cid', 'hid', 'code'] as $key) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key]) && trim((string)$decoded[$key]) !== '') {
                $hid = trim((string)$decoded[$key]);
                break;
            }
        }
    }
}

if (empty($hid)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบข้อมูลรหัสบ้าน (HID) หรือรหัสบุคคล'
    ]);
    exit();
}

try {
    $isSandboxVal = isSandboxMode($hoscode) ? 1 : 0;
    $currentBudgetYear = function_exists('get_current_budget_year') ? get_current_budget_year() : 2026;
    // Check if input is a 13-digit CID or raw HID
    $isCid = preg_match('/^\d{13}$/', $hid);

    if ($isCid) {
        // 1. Check assignments mapping to this specific CID
        $stmt = $pdo->prepare("
            SELECT a.assignment_id, a.target_cid, a.assignment_status, a.round_number,
                   p.hid, p.vhid_code, p.hoscode, p.first_name, p.last_name
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            WHERE p.cid = ? AND a.vhv_id = ? AND a.budget_year = ? AND a.is_sandbox = ?
        ");
        $stmt->execute([$hid, $vhvId, $currentBudgetYear, $isSandboxVal]);
        $assignments = $stmt->fetchAll();

        // Auto-assign in Sandbox Mode if target exists but no assignment
        if (empty($assignments) && isSandboxMode($hoscode)) {
            $checkStmt = $pdo->prepare("SELECT cid FROM target_population WHERE cid = ? LIMIT 1");
            $checkStmt->execute([$hid]);
            $pop = $checkStmt->fetch();
            if ($pop) {
                $ins = $pdo->prepare("INSERT IGNORE INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status, is_sandbox) VALUES (?, ?, ?, 'pending', 1)");
                $ins->execute([$hid, $vhvId, $currentBudgetYear]);
                
                $stmt->execute([$hid, $vhvId, $currentBudgetYear, $isSandboxVal]);
                $assignments = $stmt->fetchAll();
            }
        }

        // 2. PDPA Cross-District Lock:
        $houseStmt = $pdo->prepare("SELECT vhid_code, hoscode FROM target_population WHERE cid = ? LIMIT 1");
        $houseStmt->execute([$hid]);
        $houseInfo = $houseStmt->fetch();
    } else {
        // 1. Check assignments mapping to targets in JHCIS house
        $stmt = $pdo->prepare("
            SELECT a.assignment_id, a.target_cid, a.assignment_status, a.round_number,
                   p.hid, p.vhid_code, p.hoscode, p.first_name, p.last_name
            FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            WHERE CAST(p.hid AS UNSIGNED) = CAST(? AS UNSIGNED) AND a.vhv_id = ? AND a.budget_year = ? AND a.is_sandbox = ?
        ");
        $stmt->execute([$hid, $vhvId, $currentBudgetYear, $isSandboxVal]);
        $assignments = $stmt->fetchAll();

        // Auto-assign in Sandbox Mode if targets exist in house but no assignments to this VHV
        if (empty($assignments) && isSandboxMode($hoscode)) {
            $checkStmt = $pdo->prepare("SELECT cid FROM target_population WHERE CAST(hid AS UNSIGNED) = CAST(? AS UNSIGNED)");
            $checkStmt->execute([$hid]);
            $targets = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($targets)) {
                $ins = $pdo->prepare("INSERT IGNORE INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status, is_sandbox) VALUES (?, ?, ?, 'pending', 1)");
                foreach ($targets as $tc) {
                    $ins->execute([$tc, $vhvId, $currentBudgetYear]);
                }
                
                $stmt->execute([$hid, $vhvId, $currentBudgetYear, $isSandboxVal]);
                $assignments = $stmt->fetchAll();
            }
        }

        // 2. PDPA Cross-District Lock:
        // Prioritize the logged-in VHV's village (vhid_code) and hospital (hoscode) 
        // to handle non-globally-unique HIDs correctly.
        $houseStmt = $pdo->prepare("
            SELECT vhid_code, hoscode 
            FROM target_population 
            WHERE CAST(hid AS UNSIGNED) = CAST(? AS UNSIGNED) 
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
        // ไม่พบรหัสบ้านหรือเลขบัตรนี้ในฐานข้อมูล
        $isAuthorized = false;
        $incidentType = 'UNAUTHORIZED_SCAN';
    } else {
        if (!empty($assignments)) {
            // หากแอดมินมอบหมายงานนี้ให้ อสม. คนนี้แล้ว ถือว่ามีสิทธิ์ทำงาน (Authorized)
            $isAuthorized = true;
        } else {
            // เปรียบเทียบรหัสหมู่บ้านโดยแปลงค่าคำนำหน้าเพื่อป้องกันความคลาดเคลื่อน (3420 -> 3418)
            $houseVhid = $houseInfo['vhid_code'] ?? '';
            $normalizedHouseVhid = (strpos($houseVhid, '3420') === 0) ? '3418' . substr($houseVhid, 4) : $houseVhid;
            $normalizedVhvVhid = (strpos($vhidCode, '3420') === 0) ? '3418' . substr($vhidCode, 4) : $vhidCode;
            
            $isHospitalVhv = (!empty($hoscode) && strpos($hoscode, '10') === 0);
            $isTargetInHospitalZone = (!empty($houseInfo['hoscode']) && strpos($houseInfo['hoscode'], '10') === 0);
            
            $isSameArea = ($normalizedHouseVhid === $normalizedVhvVhid) || ($isHospitalVhv && $isTargetInHospitalZone) || isSandboxMode($hoscode);

            if ($isSameArea) {
                // อสม. สแกนบ้านในเขตรับผิดชอบตนเอง -> เปิดใบงานให้ อสม. อัตโนมัติและอนุญาตเข้าทำงาน
                if ($isCid) {
                    $ins = $pdo->prepare("INSERT IGNORE INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status, is_sandbox) VALUES (?, ?, ?, 'pending', ?)");
                    $ins->execute([$hid, $vhvId, $currentBudgetYear, $isSandboxVal]);
                } else {
                    $checkStmt = $pdo->prepare("SELECT cid FROM target_population WHERE CAST(hid AS UNSIGNED) = CAST(? AS UNSIGNED)");
                    $checkStmt->execute([$hid]);
                    $targets = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($targets)) {
                        $ins = $pdo->prepare("INSERT IGNORE INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status, is_sandbox) VALUES (?, ?, ?, 'pending', ?)");
                        foreach ($targets as $tc) {
                            $ins->execute([$tc, $vhvId, $currentBudgetYear, $isSandboxVal]);
                        }
                    }
                }
                $isAuthorized = true;
            } else {
                $isAuthorized = false;
                $incidentType = 'CROSS_DISTRICT_UNAUTHORIZED_SCAN_BLOCKED';
            }
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
            $msgText = 'สิทธิ์การเข้าถึง: ท่านไม่ได้รับมอบหมายงานคัดกรองบุคคล/บ้านหลังนี้ในรอบปัจจุบัน';
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
        'hid' => $isCid ? null : $hid,
        'cid' => $isCid ? $hid : null,
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
