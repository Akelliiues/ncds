<?php
// api/emergency_alert.php - Realtime Critical Emergency Dispatcher & Referral Sync
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/station_token_auth.php';

$action = $_REQUEST['action'] ?? '';
$stationToken = authenticate_station_token($pdo);
$isAdminSession = !empty($_SESSION['admin_logged_in']);

function requireStationAccess($stationToken, $isAdminSession, $permission, $allowWithHoscode = false) {
    if (!$isAdminSession && !$stationToken) {
        if ($allowWithHoscode && !empty($_REQUEST['hoscode'])) {
            return; // Allow public/station polling by valid hoscode
        }
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุ Station Access Token หรือเข้าสู่ระบบ'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if ($stationToken && !station_token_can($stationToken, $permission)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Token ไม่มีสิทธิ์ดำเนินการนี้'], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

function requireAlertHoscode(PDO $pdo, $stationToken, $alertId) {
    if (!$stationToken || $stationToken['hoscode'] === 'ALL') return;
    $stmt = $pdo->prepare('SELECT hoscode FROM critical_alerts WHERE alert_id = ? LIMIT 1');
    $stmt->execute([$alertId]);
    $hoscode = (string)$stmt->fetchColumn();
    if ($hoscode === '' || !station_token_allows_hoscode($stationToken, $hoscode)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Token ไม่ได้รับอนุญาตให้เข้าถึงเคสนี้'], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// -------------------------------------------------------------
// 1. STREAM ALERTS (Server-Sent Events: SSE)
// -------------------------------------------------------------
if ($action === 'stream_alerts') {
    // Disable output buffering
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', 1);
    }
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Nginx compatibility

    $hoscode = trim($_GET['hoscode'] ?? '');

    $maxRuns = 25; // approx 50s per SSE connection
    $run = 0;

    while ($run < $maxRuns) {
        if (connection_aborted()) {
            break;
        }

        try {
            // Find active pending alerts
            $sql = "SELECT * FROM critical_alerts WHERE alert_status IN ('pending', 'acknowledged') ";
            $params = [];

            if (!empty($hoscode) && $hoscode !== 'ALL' && $hoscode !== 'GLOBAL') {
                $sql .= " AND (hoscode = ? OR hoscode = 'ALL' OR hoscode = '00325' OR hoscode = '99999') ";
                $params[] = $hoscode;
            }

            $sql .= " ORDER BY alert_id DESC LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $activeAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $pendingCount = count(array_filter($activeAlerts, fn($a) => $a['alert_status'] === 'pending'));

            // Send SSE payload
            $payload = json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'count' => count($activeAlerts),
                'pending_count' => $pendingCount,
                'alerts' => $activeAlerts
            ], JSON_UNESCAPED_UNICODE);

            echo "event: emergency_update\n";
            echo "data: {$payload}\n\n";
            flush();
        } catch (\Throwable $e) {
            echo "event: error\n";
            echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
            flush();
        }

        sleep(2);
        $run++;
    }
    exit();
}

// For all other JSON actions
header('Content-Type: application/json; charset=utf-8');

// Read-only station directory. Source is the same health_units table used by
// admin/unit_house_manager.php?tab=units.
if ($action === 'get_health_units') {
    try {
        $stmt = $pdo->query("SELECT hoscode, hosname FROM health_units WHERE hoscode <> '' AND hosname <> '' ORDER BY hoscode ASC");
        echo json_encode(['status' => 'success', 'source' => 'health_units', 'units' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถอ่านรายชื่อหน่วยบริการได้'], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 2. TRIGGER ALERT (From VHV screening form or Citizen Self-Screening)
// -------------------------------------------------------------
if ($action === 'trigger_alert') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $screeningId = !empty($_POST['screening_id']) ? (int)$_POST['screening_id'] : null;
    $citizenScreeningId = !empty($_POST['citizen_screening_id']) ? (int)$_POST['citizen_screening_id'] : null;
    $hoscode = trim($_POST['hoscode'] ?? '');
    $targetCid = trim($_POST['target_cid'] ?? '');
    $patientName = trim($_POST['patient_name'] ?? '');
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    $houseNo = trim($_POST['house_no'] ?? '');
    $moo = trim($_POST['moo'] ?? '');
    $subDistrictCode = trim($_POST['sub_district_code'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $crisisType = trim($_POST['crisis_type'] ?? 'general_critical');
    $sbp = !empty($_POST['sbp']) ? (int)$_POST['sbp'] : null;
    $dbp = !empty($_POST['dbp']) ? (int)$_POST['dbp'] : null;
    $dtx = !empty($_POST['dtx']) ? (int)$_POST['dtx'] : null;
    $redFlags = is_array($_POST['red_flags'] ?? null) ? implode(', ', $_POST['red_flags']) : trim($_POST['red_flags'] ?? '');
    $vhvName = trim($_POST['vhv_name'] ?? '');
    $vhvPhone = trim($_POST['vhv_phone'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');
    $contactType = trim($_POST['contact_type'] ?? 'vhv');

    if (empty($contactPhone)) {
        $contactPhone = !empty($vhvPhone) ? $vhvPhone : null;
    }

    if (empty($patientName)) {
        $patientName = 'ผู้ป่วยฉุกเฉิน (ไม่ระบุนาม)';
    }
    if (!preg_match('/^\d{5}$/', $hoscode)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุรหัสสถานบริการ 5 หลักจากข้อมูลหน่วยบริการ'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO critical_alerts (
                screening_id, citizen_screening_id, hoscode, target_cid,
                patient_name, age, house_no, moo, sub_district_code,
                latitude, longitude, crisis_type, sbp, dbp, dtx,
                red_flags, vhv_name, vhv_phone, contact_phone, contact_type, alert_status, created_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, 'pending', NOW()
            )
        ");

        $stmt->execute([
            $screeningId, $citizenScreeningId, $hoscode, $targetCid,
            $patientName, $age, $houseNo, $moo, $subDistrictCode,
            $latitude, $longitude, $crisisType, $sbp, $dbp, $dtx,
            $redFlags, $vhvName, $vhvPhone, $contactPhone, $contactType
        ]);

        $alertId = $pdo->lastInsertId();

        echo json_encode([
            'status' => 'success',
            'message' => 'ส่งสัญญาณแจ้งเหตุฉุกเฉินไปยัง รพ.สต. สำเร็จแล้ว',
            'alert_id' => $alertId,
            'hoscode' => $hoscode,
            'patient_name' => $patientName,
            'crisis_type' => $crisisType,
            'vhv_name' => $vhvName,
            'vhv_phone' => $vhvPhone,
            'contact_phone' => $contactPhone,
            'contact_type' => $contactType,
            'created_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการส่งสัญญาณ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 3. GET ACTIVE ALERTS (Polling fallback / Dashboard fetch)
// -------------------------------------------------------------
if ($action === 'get_active_alerts') {
    requireStationAccess($stationToken, $isAdminSession, 'alerts:read', true);
    $hoscode = trim($_GET['hoscode'] ?? '');
    if ($stationToken && $stationToken['hoscode'] !== 'ALL') $hoscode = $stationToken['hoscode'];
    $statusFilter = trim($_GET['status'] ?? 'all');
    $limit = isset($_GET['limit']) ? min(500, max(1, (int)$_GET['limit'])) : 150;

    try {
        $sql = "SELECT * FROM critical_alerts WHERE 1=1 ";
        $params = [];

        if (!empty($hoscode) && $hoscode !== 'ALL' && $hoscode !== 'GLOBAL') {
            $sql .= " AND (hoscode = ? OR hoscode = 'ALL' OR hoscode = '00325' OR hoscode = '99999') ";
            $params[] = $hoscode;
        }

        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $sql .= " AND alert_status = ? ";
            $params[] = $statusFilter;
        }

        $sql .= " ORDER BY alert_id DESC LIMIT " . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pendingCount = count(array_filter($alerts, fn($a) => $a['alert_status'] === 'pending'));
        $ackCount = count(array_filter($alerts, fn($a) => $a['alert_status'] === 'acknowledged' || $a['alert_status'] === 'dispatched'));
        $referredCount = count(array_filter($alerts, fn($a) => $a['alert_status'] === 'referred_hospital' || !empty($a['is_jhcis_synced'])));

        echo json_encode([
            'status' => 'success',
            'count' => count($alerts),
            'pending_count' => $pendingCount,
            'ack_count' => $ackCount,
            'referred_count' => $referredCount,
            'alerts' => $alerts
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 4. ACKNOWLEDGE ALERT (Mute alarm & mark as acknowledged)
// -------------------------------------------------------------
if ($action === 'acknowledge_alert') {
    requireStationAccess($stationToken, $isAdminSession, 'alerts:update', true);
    $alertId = (int)($_POST['alert_id'] ?? 0);
    requireAlertHoscode($pdo, $stationToken, $alertId);
    $staffName = trim($_POST['staff_name'] ?? 'เจ้าหน้าที่ รพ.สต.');

    if ($alertId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสเคสฉุกเฉินไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE critical_alerts 
            SET alert_status = 'acknowledged',
                acknowledged_by = ?,
                acknowledged_at = NOW()
            WHERE alert_id = ?
        ");
        $stmt->execute([$staffName, $alertId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'รับทราบเคสเรียบร้อยแล้ว (ปิดเสียงเตือน)',
            'alert_id' => $alertId,
            'acknowledged_by' => $staffName,
            'acknowledged_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 4.1 CHECK ALERT STATUS (For VHV Real-time Live Progress Bar)
// -------------------------------------------------------------
if ($action === 'check_alert_status') {
    $alertId = (int)($_REQUEST['alert_id'] ?? 0);

    if ($alertId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสเคสไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM critical_alerts WHERE alert_id = ? LIMIT 1");
        $stmt->execute([$alertId]);
        $alert = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$alert) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบเคส'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        echo json_encode([
            'status' => 'success',
            'alert_id' => (int)$alert['alert_id'],
            'alert_status' => $alert['alert_status'],
            'acknowledged_by' => $alert['acknowledged_by'],
            'acknowledged_at' => $alert['acknowledged_at'],
            'referral_destination' => $alert['referral_destination'],
            'referral_notes' => $alert['referral_notes'],
            'is_jhcis_synced' => (int)$alert['is_jhcis_synced'],
            'jhcis_visitno' => $alert['jhcis_visitno'],
            'updated_at' => $alert['updated_at']
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 4.2 SIMULATE DEMO ACKNOWLEDGMENT (For Sandbox / Demo Mode)
// -------------------------------------------------------------
if ($action === 'simulate_demo_ack') {
    $alertId = (int)($_REQUEST['alert_id'] ?? 0);
    $staffName = trim($_REQUEST['staff_name'] ?? 'พยาบาลสมคิด สุขเกษม (รพ.สต.ดอนมดแดง - จำลอง)');

    if ($alertId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสเคสไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE critical_alerts 
            SET alert_status = 'acknowledged',
                acknowledged_by = ?,
                acknowledged_at = NOW()
            WHERE alert_id = ?
        ");
        $stmt->execute([$staffName, $alertId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'จำลองการรับเรื่องจากเจ้าหน้าที่ รพ.สต. สำเร็จ',
            'alert_id' => $alertId,
            'alert_status' => 'acknowledged',
            'acknowledged_by' => $staffName,
            'acknowledged_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 4.3 SIMULATE DEMO REFERRAL (For Sandbox / Demo Mode)
// -------------------------------------------------------------
if ($action === 'simulate_demo_refer') {
    $alertId = (int)($_REQUEST['alert_id'] ?? 0);
    $dest = trim($_REQUEST['referral_destination'] ?? 'โรงพยาบาลตาลสุม (10957)');

    if ($alertId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสเคสไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE critical_alerts 
            SET alert_status = 'referred_hospital',
                referral_destination = ?,
                is_jhcis_synced = 0,
                jhcis_visitno = NULL
            WHERE alert_id = ?
        ");
        $stmt->execute([$dest, $alertId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'จำลองการสั่งส่งต่อไปยังโรงพยาบาลสำเร็จ',
            'alert_id' => $alertId,
            'alert_status' => 'referred_hospital',
            'referral_destination' => $dest,
            'is_jhcis_synced' => 0,
            'jhcis_visitno' => null
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 5. UPDATE REFERRAL STATUS & JHCIS SYNC
// -------------------------------------------------------------
if ($action === 'update_referral_status') {
    requireStationAccess($stationToken, $isAdminSession, 'alerts:update');
    $alertId = (int)($_POST['alert_id'] ?? 0);
    requireAlertHoscode($pdo, $stationToken, $alertId);
    $newStatus = trim($_POST['status'] ?? 'referred_hospital'); // acknowledged, dispatched, referred_hospital, resolved, cancelled
    $destination = trim($_POST['referral_destination'] ?? 'โรงพยาบาลตาลสุม (10988)');
    $notes = trim($_POST['referral_notes'] ?? '');
    $staffName = trim($_POST['staff_name'] ?? 'เจ้าหน้าที่ รพ.สต.');
    $syncToJHCIS = !empty($_POST['sync_jhcis']);

    if ($alertId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสเคสฉุกเฉินไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        // Fetch current alert
        $stmt = $pdo->prepare("SELECT * FROM critical_alerts WHERE alert_id = ?");
        $stmt->execute([$alertId]);
        $alert = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$alert) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลเคสฉุกเฉิน'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $jhcisVisitNo = $alert['jhcis_visitno'];
        $isJhcisSynced = (int)$alert['is_jhcis_synced'];
        $jhcisSyncMessage = '';

        // JHCIS writes are performed locally by the Station only after preview,
        // explicit confirmation, transaction and post-write verification.
        // The web server must never open or mutate a facility's JHCIS database.
        if ($syncToJHCIS) {
            $localSynced = ($_POST['jhcis_local_committed'] ?? '') === '1';
            $localVisitNo = trim($_POST['jhcis_visitno'] ?? '');
            if ($localSynced && $stationToken && station_token_can($stationToken, 'jhcis:sync') && ctype_digit($localVisitNo)) {
                $jhcisVisitNo = $localVisitNo;
                $isJhcisSynced = 1;
                $jhcisSyncMessage = " (Station ยืนยันการบันทึก JHCIS visitno: {$localVisitNo})";
            } else {
                $jhcisSyncMessage = ' (ยังไม่ได้บันทึก JHCIS จาก Station)';
            }
        }

        $upd = $pdo->prepare("
            UPDATE critical_alerts 
            SET alert_status = ?,
                referral_destination = ?,
                referral_notes = ?,
                acknowledged_by = COALESCE(acknowledged_by, ?),
                acknowledged_at = COALESCE(acknowledged_at, NOW()),
                is_jhcis_synced = ?,
                jhcis_visitno = ?
            WHERE alert_id = ?
        ");
        $upd->execute([$newStatus, $destination, $notes, $staffName, $isJhcisSynced, $jhcisVisitNo, $alertId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'อัปเดตสถานะการส่งต่อเรียบร้อยแล้ว' . $jhcisSyncMessage,
            'alert_id' => $alertId,
            'status_updated' => $newStatus,
            'is_jhcis_synced' => $isJhcisSynced
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 6. GET SINGLE ALERT DETAILS (For Modal & e-Referral Slip)
// -------------------------------------------------------------
if ($action === 'get_alert_details') {
    $alertId = (int)($_GET['alert_id'] ?? 0);

    try {
        $stmt = $pdo->prepare("SELECT * FROM critical_alerts WHERE alert_id = ? LIMIT 1");
        $stmt->execute([$alertId]);
        $alert = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$alert) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลเคส'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        echo json_encode(['status' => 'success', 'alert' => $alert], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 7. CLEAR ALL TEST ALERTS (Purge Mock / Test Alerts)
// -------------------------------------------------------------
if ($action === 'clear_test_alerts') {
    requireStationAccess($stationToken, $isAdminSession, 'alerts:write', true);
    
    try {
        $sql = "
            DELETE FROM critical_alerts 
            WHERE patient_name LIKE '%ทดสอบ%' 
               OR patient_name LIKE '%จำลอง%' 
               OR vhv_name LIKE '%ทดสอบ%' 
               OR vhv_name LIKE '%จำลอง%' 
               OR crisis_type LIKE '%ทดสอบ%' 
               OR crisis_type LIKE '%จำลอง%' 
               OR target_cid LIKE '003250000%' 
               OR house_no = 'TEST-1' 
               OR red_flags LIKE '%จำลอง%' 
               OR red_flags LIKE '%ทดสอบ%'
               OR screening_id = 999999
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $deletedCount = $stmt->rowCount();

        echo json_encode([
            'status' => 'success',
            'message' => "ลบเคสทดสอบและข้อมูลจำลองเรียบร้อยแล้ว ({$deletedCount} รายการ)",
            'deleted_count' => $deletedCount
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบเคสทดสอบ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 8. DELETE SINGLE ALERT (Restricted strictly to Main District Admin only)
// -------------------------------------------------------------
if ($action === 'delete_alert') {
    $adminHoscode = $_SESSION['admin_hoscode'] ?? null;
    $isVisitor = !empty($_SESSION['is_visitor']) || !empty($_SESSION['is_executive']);
    $username = strtolower($_SESSION['admin_username'] ?? '');
    $isMainAdmin = !empty($_SESSION['admin_logged_in']) && !$isVisitor && ($username === 'admin' || empty($adminHoscode) || $adminHoscode === '00325');

    if (!$isMainAdmin) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'ไม่อนุญาต: สิทธิ์การลบเคสสงวนไว้สำหรับแอดมินหลัก (สสอ.ตาลสุม) เท่านั้น ผู้รับผิดชอบ รพ. หรือระดับ รพ.สต. ไม่มีสิทธิ์ลบ'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $alertId = (int)($_POST['alert_id'] ?? $_GET['alert_id'] ?? 0);
    if ($alertId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่ระบุรหัสเคสที่ต้องการลบ'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM critical_alerts WHERE alert_id = ?");
        $stmt->execute([$alertId]);
        echo json_encode(['status' => 'success', 'message' => "ลบเคส #{$alertId} ออกจากระบบเรียบร้อยแล้ว", 'alert_id' => $alertId], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบเคส: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
