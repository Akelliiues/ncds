<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function screeningCorrectionResponse(string $status, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

$adminHoscode = $_SESSION['admin_hoscode'] ?? null;
$adminUsername = (string)($_SESSION['admin_username'] ?? '');
$adminRole = (string)($_SESSION['admin_role'] ?? 'admin');
$isMainAdmin = !empty($_SESSION['admin_logged_in'])
    && empty($adminHoscode)
    && $adminUsername !== ''
    && $adminUsername !== 'adminsso'
    && $adminRole === 'admin'
    && empty($_SESSION['is_visitor'])
    && empty($_SESSION['is_executive']);

if (!$isMainAdmin) {
    http_response_code(403);
    screeningCorrectionResponse('error', 'สิทธิ์แก้ไขผลคัดกรองสงวนไว้สำหรับผู้ดูแลระบบหลักเท่านั้น');
}

$screeningId = (int)($_REQUEST['screening_id'] ?? 0);
if ($screeningId <= 0) {
    screeningCorrectionResponse('error', 'ไม่พบรหัสผลการคัดกรอง');
}

$selectSql = "
    SELECT s.screening_id, COALESCE(s.target_cid, a.target_cid) AS target_cid, s.round_number,
           s.sys_bp1, s.dia_bp1, s.sys_bp2, s.dia_bp2,
           s.dtx_value, s.dtx_type, s.weight, s.height, s.waist,
           s.bmi, s.cv_risk_score, s.smoking_risk, s.created_at,
           COALESCE(s.is_synced_jhcis, 0) AS is_synced_jhcis,
           p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
           p.sex, p.birth, p.need_screen_dm, p.health_status_origin
    FROM screening_results s
    LEFT JOIN task_assignments a ON s.assignment_id = a.assignment_id
    LEFT JOIN target_population p ON p.cid = COALESCE(s.target_cid, a.target_cid)
    WHERE s.screening_id = ?
    LIMIT 1
";

function loadPreviousScreeningVitals(PDO $pdo, array $current): array
{
    if (empty($current['target_cid'])) return [];
    $stmt = $pdo->prepare("
        SELECT sr.sys_bp1, sr.dia_bp1, sr.dtx_value, sr.dtx_type
        FROM screening_results sr
        LEFT JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
        WHERE COALESCE(sr.target_cid, ta.target_cid) = ?
          AND sr.screening_id <> ?
          AND (sr.created_at < ? OR (sr.created_at = ? AND sr.screening_id < ?))
        ORDER BY sr.created_at DESC, sr.screening_id DESC
        LIMIT 1
    ");
    $stmt->execute([
        $current['target_cid'], $current['screening_id'],
        $current['created_at'], $current['created_at'], $current['screening_id']
    ]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function calculateScreeningCvRisk(array $current, array $previous, int $sys1, ?int $sys2, ?int $dtx, string $dtxType): float
{
    if (empty($current['birth']) || !in_array((string)$current['sex'], ['1', '2'], true)) {
        throw new InvalidArgumentException('ข้อมูลวันเกิดหรือเพศไม่ครบ จึงไม่สามารถคำนวณ CV Risk ได้');
    }

    $birthYear = (int)date('Y', strtotime((string)$current['birth']));
    $age = max(0, (int)date('Y') - $birthYear);

    if ($sys1 > 0 && $sys2 !== null && $sys2 > 0) {
        $sbp = ($sys1 + $sys2) / 2;
    } elseif ($sys1 > 0) {
        $sbp = $sys1;
    } elseif ($sys2 !== null && $sys2 > 0) {
        $sbp = $sys2;
    } elseif (!empty($previous['sys_bp1'])) {
        $sbp = (float)$previous['sys_bp1'];
    } else {
        $sbp = 120.0;
    }

    if ($dtx !== null && $dtx > 0) {
        $glucose = $dtx;
        $glucoseType = $dtxType;
    } elseif (!empty($previous['dtx_value'])) {
        $glucose = (float)$previous['dtx_value'];
        $glucoseType = (string)($previous['dtx_type'] ?: 'fpg');
    } else {
        $glucose = 90.0;
        $glucoseType = 'fpg';
    }

    $origin = (string)($current['health_status_origin'] ?? '');
    $hasDm = in_array($origin, ['DM_ONLY', 'BOTH'], true)
        || (!empty($current['need_screen_dm']) && ($glucoseType === 'fpg' ? $glucose >= 126 : $glucose >= 200));

    $risk = 1.2;
    if ($age >= 40 && $age < 50) $risk += 2.0;
    elseif ($age >= 50 && $age < 60) $risk += 5.5;
    elseif ($age >= 60) $risk += 12.0;

    $isSmoker = ($current['smoking_risk'] ?? 'green') === 'red';
    if ((string)$current['sex'] === '1') {
        $risk += 1.5;
        if ($isSmoker) $risk += 4.5;
    } elseif ($isSmoker) {
        $risk += 2.5;
    }

    if ($hasDm) $risk += 6.0;
    if ($sbp >= 140 && $sbp < 160) $risk += 2.5;
    elseif ($sbp >= 160) $risk += 7.0;

    return round(min(100, max(0.5, $risk)), 2);
}

$stmt = $pdo->prepare($selectSql);
$stmt->execute([$screeningId]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$current) {
    screeningCorrectionResponse('error', 'ไม่พบผลการคัดกรองรายการนี้');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $previous = loadPreviousScreeningVitals($pdo, $current);
    $current['previous_sys_bp1'] = $previous['sys_bp1'] ?? null;
    $current['previous_dtx_value'] = $previous['dtx_value'] ?? null;
    $current['previous_dtx_type'] = $previous['dtx_type'] ?? 'fpg';
    $current['age'] = !empty($current['birth']) ? max(0, (int)date('Y') - (int)date('Y', strtotime((string)$current['birth']))) : null;
    screeningCorrectionResponse('success', 'โหลดข้อมูลสำเร็จ', ['record' => $current]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    screeningCorrectionResponse('error', 'ไม่รองรับวิธีการส่งคำขอนี้');
}

$csrf = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['screening_correction_csrf']) || !hash_equals($_SESSION['screening_correction_csrf'], $csrf)) {
    http_response_code(419);
    screeningCorrectionResponse('error', 'คำขอหมดอายุ กรุณาเปิดหน้าใหม่แล้วลองอีกครั้ง');
}

function requiredNumber(string $key, float $min, float $max): float
{
    $raw = trim((string)($_POST[$key] ?? ''));
    if ($raw === '' || !is_numeric($raw)) {
        throw new InvalidArgumentException("กรุณากรอก {$key} ให้ถูกต้อง");
    }
    $value = (float)$raw;
    if ($value < $min || $value > $max) {
        throw new InvalidArgumentException("ค่า {$key} อยู่นอกช่วงที่ระบบรองรับ");
    }
    return $value;
}

function optionalNumber(string $key, float $min, float $max): ?float
{
    $raw = trim((string)($_POST[$key] ?? ''));
    if ($raw === '') return null;
    if (!is_numeric($raw)) throw new InvalidArgumentException("กรุณากรอก {$key} ให้ถูกต้อง");
    $value = (float)$raw;
    if ($value < $min || $value > $max) throw new InvalidArgumentException("ค่า {$key} อยู่นอกช่วงที่ระบบรองรับ");
    return $value;
}

try {
    $pdo->beginTransaction();
    $lockedStmt = $pdo->prepare($selectSql . " FOR UPDATE");
    $lockedStmt->execute([$screeningId]);
    $current = $lockedStmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        throw new InvalidArgumentException('ไม่พบผลการคัดกรองรายการนี้');
    }
    if ((int)$current['is_synced_jhcis'] === 1) {
        throw new InvalidArgumentException('รายการนี้ส่งเข้า JHCIS แล้ว จึงไม่อนุญาตให้แก้จากหน้าเว็บเพื่อป้องกันข้อมูลสองระบบไม่ตรงกัน');
    }

    $reason = trim((string)($_POST['correction_reason'] ?? ''));
    if (mb_strlen($reason, 'UTF-8') < 5) {
        throw new InvalidArgumentException('กรุณาระบุเหตุผลการแก้ไขอย่างน้อย 5 ตัวอักษร');
    }

    $sys1 = (int)requiredNumber('sys_bp1', 0, 300);
    $dia1 = (int)requiredNumber('dia_bp1', 0, 200);
    if (!(($sys1 === 0 && $dia1 === 0) || ($sys1 >= 60 && $dia1 >= 30))) {
        throw new InvalidArgumentException('ความดันครั้งที่ 1 ต้องกรอกทั้งตัวบนและตัวล่าง หรือใส่ 0 ทั้งสองช่องเมื่อไม่ได้ตรวจ');
    }
    $sys2Raw = optionalNumber('sys_bp2', 60, 300);
    $dia2Raw = optionalNumber('dia_bp2', 30, 200);
    if (($sys2Raw === null) !== ($dia2Raw === null)) {
        throw new InvalidArgumentException('ความดันครั้งที่ 2 ต้องกรอกทั้งตัวบนและตัวล่าง');
    }
    $sys2 = $sys2Raw !== null ? (int)$sys2Raw : null;
    $dia2 = $dia2Raw !== null ? (int)$dia2Raw : null;
    $dtxRaw = optionalNumber('dtx_value', 20, 700);
    $dtx = $dtxRaw !== null ? (int)$dtxRaw : null;
    $dtxType = in_array($_POST['dtx_type'] ?? '', ['fpg', 'rpg'], true) ? $_POST['dtx_type'] : 'fpg';
    $weight = optionalNumber('weight', 20, 300);
    $height = optionalNumber('height', 80, 250);
    $waist = optionalNumber('waist', 10, 100);
    $previous = loadPreviousScreeningVitals($pdo, $current);
    $cvRisk = calculateScreeningCvRisk($current, $previous, $sys1, $sys2, $dtx, $dtxType);
    $bmi = ($weight !== null && $height !== null && $height > 0)
        ? round($weight / pow($height / 100, 2), 2)
        : null;

    $newValues = [
        'sys_bp1' => $sys1, 'dia_bp1' => $dia1,
        'sys_bp2' => $sys2, 'dia_bp2' => $dia2,
        'dtx_value' => $dtx, 'dtx_type' => $dtxType,
        'weight' => $weight, 'height' => $height, 'waist' => $waist,
        'bmi' => $bmi, 'cv_risk_score' => $cvRisk
    ];

    $update = $pdo->prepare("
        UPDATE screening_results
        SET sys_bp1 = ?, dia_bp1 = ?, sys_bp2 = ?, dia_bp2 = ?,
            dtx_value = ?, dtx_type = ?, weight = ?, height = ?, waist = ?,
            bmi = ?, cv_risk_score = ?
        WHERE screening_id = ? AND COALESCE(is_synced_jhcis, 0) = 0
    ");
    $update->execute([
        $sys1, $dia1, $sys2, $dia2, $dtx, $dtxType,
        $weight, $height, $waist, $bmi, $cvRisk, $screeningId
    ]);
    if ($update->rowCount() < 1) {
        throw new RuntimeException('ไม่สามารถบันทึกได้ หรือข้อมูลไม่ได้เปลี่ยนแปลง');
    }
    $auditSaved = false;
    if (function_exists('logUserActivity')) {
        $oldValues = array_intersect_key($current, $newValues);
        $auditSaved = logUserActivity('SCREENING', 'แก้ไขผลคัดกรองโดยผู้ดูแลระบบหลัก', [
            'screening_id' => $screeningId,
            'target_cid' => $current['target_cid'],
            'round_number' => $current['round_number'],
            'reason' => $reason,
            'old_values' => $oldValues,
            'new_values' => $newValues
        ], 'warning', [
            'user_type' => 'staff',
            'username' => $adminUsername,
            'user_fullname' => 'ผู้ดูแลระบบหลัก',
            'hoscode' => null,
            'hosname' => 'ส่วนกลาง'
        ]);
    }
    if (!$auditSaved) {
        throw new RuntimeException('ไม่สามารถบันทึกประวัติการแก้ไขได้');
    }
    $pdo->commit();

    screeningCorrectionResponse('success', 'บันทึกค่าที่แก้ไขแล้ว และเก็บเหตุผลไว้ในประวัติการทำงานเรียบร้อย');
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    screeningCorrectionResponse('error', $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Screening correction failed: ' . $e->getMessage());
    screeningCorrectionResponse('error', 'บันทึกการแก้ไขไม่สำเร็จ กรุณาตรวจสอบข้อมูลแล้วลองอีกครั้ง');
}
