<?php
// api/emergency_alert.php - Realtime Critical Emergency Dispatcher & Referral Sync
require_once __DIR__ . '/../config/db.php';

$action = $_REQUEST['action'] ?? '';

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
                $sql .= " AND (hoscode = ? OR hoscode = 'ALL' OR hoscode = '99999') ";
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

    if (empty($patientName)) {
        $patientName = 'ผู้ป่วยฉุกเฉิน (ไม่ระบุนาม)';
    }
    if (empty($hoscode)) {
        // Fallback hoscode if missing
        $hoscode = '07758';
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO critical_alerts (
                screening_id, citizen_screening_id, hoscode, target_cid,
                patient_name, age, house_no, moo, sub_district_code,
                latitude, longitude, crisis_type, sbp, dbp, dtx,
                red_flags, vhv_name, vhv_phone, alert_status, created_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, 'pending', NOW()
            )
        ");

        $stmt->execute([
            $screeningId, $citizenScreeningId, $hoscode, $targetCid,
            $patientName, $age, $houseNo, $moo, $subDistrictCode,
            $latitude, $longitude, $crisisType, $sbp, $dbp, $dtx,
            $redFlags, $vhvName, $vhvPhone
        ]);

        $alertId = $pdo->lastInsertId();

        echo json_encode([
            'status' => 'success',
            'message' => 'ส่งสัญญาณแจ้งเหตุฉุกเฉินไปยัง รพ.สต. สำเร็จแล้ว',
            'alert_id' => $alertId,
            'hoscode' => $hoscode,
            'patient_name' => $patientName,
            'crisis_type' => $crisisType,
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
    $hoscode = trim($_GET['hoscode'] ?? '');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

    try {
        $sql = "SELECT * FROM critical_alerts WHERE alert_status IN ('pending', 'acknowledged', 'dispatched') ";
        $params = [];

        if (!empty($hoscode) && $hoscode !== 'ALL' && $hoscode !== 'GLOBAL') {
            $sql .= " AND (hoscode = ? OR hoscode = 'ALL' OR hoscode = '99999') ";
            $params[] = $hoscode;
        }

        $sql .= " ORDER BY alert_id DESC LIMIT " . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pendingCount = count(array_filter($alerts, fn($a) => $a['alert_status'] === 'pending'));

        echo json_encode([
            'status' => 'success',
            'count' => count($alerts),
            'pending_count' => $pendingCount,
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
    $alertId = (int)($_POST['alert_id'] ?? 0);
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
        $mockVisitNo = 'REF-6901-' . str_pad($alertId, 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("
            UPDATE critical_alerts 
            SET alert_status = 'referred_hospital',
                referral_destination = ?,
                is_jhcis_synced = 1,
                jhcis_visitno = ?
            WHERE alert_id = ?
        ");
        $stmt->execute([$dest, $mockVisitNo, $alertId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'จำลองการสั่งส่งต่อไปยังโรงพยาบาลสำเร็จ',
            'alert_id' => $alertId,
            'alert_status' => 'referred_hospital',
            'referral_destination' => $dest,
            'is_jhcis_synced' => 1,
            'jhcis_visitno' => $mockVisitNo
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
    $alertId = (int)($_POST['alert_id'] ?? 0);
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

        // Push to JHCIS if requested
        if ($syncToJHCIS && $newStatus === 'referred_hospital') {
            try {
                $targetHoscode = $alert['hoscode'] ?: '07758';
                $configStmt = $pdo->prepare("SELECT * FROM jhcis_sync_configs WHERE hoscode = ? LIMIT 1");
                $configStmt->execute([$targetHoscode]);
                $jhcisConfig = $configStmt->fetch(PDO::FETCH_ASSOC);

                // Extract 5-digit destination hospital code (e.g. 10957 from "โรงพยาบาลตาลสุม (10957)")
                $destHospCode = '10957';
                if (!empty($_POST['referral_hospcode'])) {
                    $destHospCode = trim($_POST['referral_hospcode']);
                } elseif (preg_match('/\b(\d{5})\b/', $destination, $m)) {
                    $destHospCode = $m[1];
                }

                if ($jhcisConfig && function_exists('getJHCISConnection')) {
                    $jhcisPdo = getJHCISConnection($jhcisConfig);

                    // Find Person in JHCIS
                    $findPid = $jhcisPdo->prepare("SELECT pid, pcucode FROM person WHERE idcard = ? OR cid = ? LIMIT 1");
                    $findPid->execute([$alert['target_cid'], $alert['target_cid']]);
                    $pRow = $findPid->fetch(PDO::FETCH_ASSOC);

                    if ($pRow) {
                        $pcuCode = $pRow['pcucode'];
                        $pid = $pRow['pid'];
                        $visitDate = date('Y-m-d');
                        $visitTime = date('H:i:s');
                        $cause = "ความดัน/น้ำตาลวิกฤต: SBP {$alert['sbp']}/{$alert['dbp']} DTX {$alert['dtx']} ({$alert['crisis_type']})";

                        // Create visit if needed
                        $visitNoStmt = $jhcisPdo->prepare("SELECT MAX(visitno) as max_v FROM visit WHERE pcucode = ?");
                        $visitNoStmt->execute([$pcuCode]);
                        $maxV = $visitNoStmt->fetch(PDO::FETCH_ASSOC);
                        $newVisitNo = ((int)($maxV['max_v'] ?? 0)) + 1;

                        $insVisit = $jhcisPdo->prepare("
                            INSERT INTO visit (pcucode, visitno, visitdate, pid, symptoms, sbp, dbp, money1, userupdate, dateupdate)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'NCD_PORTAL', NOW())
                        ");
                        $insVisit->execute([$pcuCode, $newVisitNo, $visitDate, $pid, $cause, $alert['sbp'], $alert['dbp']]);

                        // Insert into visitrefer
                        $insRefer = $jhcisPdo->prepare("
                            INSERT INTO visitrefer (pcucode, visitno, pid, referdate, referhosp, refertype, cause, officer, dateupdate)
                            VALUES (?, ?, ?, ?, ?, '1', ?, 'NCD_PORTAL', NOW())
                        ");
                        $insRefer->execute([$pcuCode, $newVisitNo, $pid, $visitDate, $destHospCode, $cause]);

                        $jhcisVisitNo = (string)$newVisitNo;
                        $isJhcisSynced = 1;
                        $jhcisSyncMessage = " (ซิงค์เข้า JHCIS visitno: {$newVisitNo} ปลายทาง รพ. {$destHospCode} เรียบร้อย)";
                    }
                }
            } catch (\Throwable $je) {
                $jhcisSyncMessage = " (หมายเหตุ JHCIS: " . $je->getMessage() . ")";
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

echo json_encode(['status' => 'error', 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
