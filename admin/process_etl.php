<?php
// admin/process_etl.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_username = $_SESSION['admin_username'] ?? '';
if ($admin_hoscode !== null || $admin_username === 'adminsso') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────
function validateThaiCitizenID($id) {
    $id = preg_replace('/[^0-9*]/', '', $id);
    return strlen($id) === 13;
}

function isValidThaiCitizenIDMOD11($cid) {
    $cid = preg_replace('/[^0-9]/', '', $cid);
    if (strlen($cid) !== 13) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$cid[$i] * (13 - $i);
    }
    $checkDigit = (11 - ($sum % 11)) % 10;
    return $checkDigit === (int)$cid[12];
}

function isMockHospitalCID($cid, $hoscode = null) {
    $cid = trim((string)$cid);
    if (strlen($cid) !== 13) return false;
    
    if ($hoscode !== null) {
        $paddedHos = str_pad(trim($hoscode), 5, '0', STR_PAD_LEFT);
        if (strpos($cid, $paddedHos) === 0) {
            return true;
        }
    }
    
    global $hc_names;
    if (empty($hc_names)) {
        $hc_names = get_health_units();
    }
    $prefix = substr($cid, 0, 5);
    if (isset($hc_names[$prefix])) {
        return true;
    }
    
    return false;
}

/**
 * คำนวณ confidence score ระหว่าง 2 records
 * คืนค่า 0-100 และ array of matching factors
 */
function calcConfidence($a, $b) {
    $score   = 0;
    $factors = [];

    // 1. pid + hoscode ตรงกัน (strongest signal)
    if (!empty($a['pid']) && !empty($b['pid']) &&
        ltrim($a['pid'],'0') === ltrim($b['pid'],'0') &&
        str_pad($a['hoscode'],5,'0',STR_PAD_LEFT) === str_pad($b['hoscode'],5,'0',STR_PAD_LEFT)) {
        $score += 50;
        $factors[] = ['label' => 'รหัสบุคคล (pid) ตรงกัน', 'icon' => '🔑', 'weight' => 'high'];
    }

    // 2. หมู่บ้าน (moo) + ตำบล (sub_district_code) ตรงกัน
    if (!empty($a['moo']) && $a['moo'] == $b['moo'] &&
        !empty($a['sub_district_code']) && $a['sub_district_code'] === $b['sub_district_code']) {
        $score += 15;
        $factors[] = ['label' => 'หมู่/ตำบลเดียวกัน (หมู่ ' . intval($a['moo']) . ')', 'icon' => '📍', 'weight' => 'medium'];
    }

    // 3. บ้านเลขที่ตรงกัน
    $ha = preg_replace('/\s+/', '', $a['house_no'] ?? '');
    $hb = preg_replace('/\s+/', '', $b['house_no'] ?? '');
    if (!empty($ha) && !empty($hb) && $ha === $hb) {
        $score += 10;
        $factors[] = ['label' => 'บ้านเลขที่ตรงกัน (' . htmlspecialchars($ha) . ')', 'icon' => '🏠', 'weight' => 'medium'];
    }

    // 4. ชื่อต้น (first name prefix ≥ 2 chars)
    $fa = mb_substr(trim($a['first_name'] ?? ''), 0, 3);
    $fb = mb_substr(trim($b['first_name'] ?? ''), 0, 3);
    if (!empty($fa) && !empty($fb) && $fa === $fb &&
        !in_array($fa, ['ไม่ท','ไม่ร','Unk'])) {
        $score += 10;
        $factors[] = ['label' => 'ชื่อขึ้นต้นเหมือนกัน ("' . htmlspecialchars($fa) . '...")', 'icon' => '👤', 'weight' => 'medium'];
    }

    // 5. ปีเกิดตรงกัน
    $ya = substr($a['birth'] ?? '', 0, 4);
    $yb = substr($b['birth'] ?? '', 0, 4);
    if (!empty($ya) && !empty($yb) && $ya === $yb && $ya !== '1970') {
        $score += 10;
        $factors[] = ['label' => 'ปีเกิดตรงกัน (' . $ya . ')', 'icon' => '🎂', 'weight' => 'low'];
    }

    // 6. เพศตรงกัน
    if (!empty($a['sex']) && !empty($b['sex']) && $a['sex'] === $b['sex']) {
        $score += 5;
        $factors[] = ['label' => 'เพศตรงกัน', 'icon' => '⚧', 'weight' => 'low'];
    }

    // 7. CID ปกปิด (มี *)
    $aHasStar = strpos($a['cid'] ?? '', '*') !== false;
    $bHasStar = strpos($b['cid'] ?? '', '*') !== false;
    if ($aHasStar || $bHasStar) {
        $score += 0; // ไม่ได้ add แต่ mark ไว้
        $factors[] = ['label' => 'มีข้อมูลรหัสบัตรถูกปกปิด (*)', 'icon' => '🔒', 'weight' => 'info'];
    }

    return ['score' => min(100, $score), 'factors' => $factors];
}

/**
 * ค้นหา duplicate pairs ทั้งหมดพร้อม confidence score
 */
function findDuplicatesWithConfidence($pdo) {
    $dupes = [];
    $seen  = [];

    // Query A: CID มี *
    $stmtA = $pdo->query("
        SELECT
            t1.cid AS cid_a, t1.first_name AS fn_a, t1.last_name AS ln_a,
            t1.house_no AS hn_a, t1.moo AS moo_a, t1.sub_district_code AS sub_a,
            t1.birth AS birth_a, t1.sex AS sex_a, t1.pid AS pid_a, t1.hoscode AS hsc_a, t1.hid AS hid_a,
            t2.cid AS cid_b, t2.first_name AS fn_b, t2.last_name AS ln_b,
            t2.house_no AS hn_b, t2.moo AS moo_b, t2.sub_district_code AS sub_b,
            t2.birth AS birth_b, t2.sex AS sex_b, t2.pid AS pid_b, t2.hoscode AS hsc_b, t2.hid AS hid_b,
            'A' AS dup_type
        FROM target_population t1
        JOIN target_population t2
          ON t1.hoscode = t2.hoscode
         AND t1.pid = t2.pid
        WHERE t1.cid LIKE '%*%'
          AND t2.cid NOT LIKE '%*%'
          AND t1.cid <> t2.cid
          AND t1.pid IS NOT NULL AND t1.pid != ''
    ");
    foreach ($stmtA->fetchAll() as $r) {
        $key = $r['cid_a'] . '|' . $r['cid_b'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $seen[$r['cid_b'] . '|' . $r['cid_a']] = true;
            $conf = calcConfidence(
                ['cid'=>$r['cid_a'],'first_name'=>$r['fn_a'],'last_name'=>$r['ln_a'],'house_no'=>$r['hn_a'],'moo'=>$r['moo_a'],'sub_district_code'=>$r['sub_a'],'birth'=>$r['birth_a'],'sex'=>$r['sex_a'],'pid'=>$r['pid_a'],'hoscode'=>$r['hsc_a']],
                ['cid'=>$r['cid_b'],'first_name'=>$r['fn_b'],'last_name'=>$r['ln_b'],'house_no'=>$r['hn_b'],'moo'=>$r['moo_b'],'sub_district_code'=>$r['sub_b'],'birth'=>$r['birth_b'],'sex'=>$r['sex_b'],'pid'=>$r['pid_b'],'hoscode'=>$r['hsc_b']]
            );
            // masked = cid_a (มี *), real = cid_b
            $dupes[] = array_merge($r, ['confidence' => $conf['score'], 'factors' => $conf['factors'], 'masked_cid' => $r['cid_a'], 'real_cid' => $r['cid_b']]);
        }
    }

    // Query B: ทั้งคู่ไม่มี *, pid+hoscode ซ้ำ
    $stmtB = $pdo->query("
        SELECT
            t1.cid AS cid_a, t1.first_name AS fn_a, t1.last_name AS ln_a,
            t1.house_no AS hn_a, t1.moo AS moo_a, t1.sub_district_code AS sub_a,
            t1.birth AS birth_a, t1.sex AS sex_a, t1.pid AS pid_a, t1.hoscode AS hsc_a, t1.hid AS hid_a,
            t2.cid AS cid_b, t2.first_name AS fn_b, t2.last_name AS ln_b,
            t2.house_no AS hn_b, t2.moo AS moo_b, t2.sub_district_code AS sub_b,
            t2.birth AS birth_b, t2.sex AS sex_b, t2.pid AS pid_b, t2.hoscode AS hsc_b, t2.hid AS hid_b,
            'B' AS dup_type
        FROM target_population t1
        JOIN target_population t2
          ON t1.hoscode = t2.hoscode
         AND t1.pid = t2.pid
        WHERE t1.cid NOT LIKE '%*%'
          AND t2.cid NOT LIKE '%*%'
          AND t1.cid < t2.cid
          AND t1.pid IS NOT NULL AND t1.pid != ''
    ");
    foreach ($stmtB->fetchAll() as $r) {
        $key = $r['cid_a'] . '|' . $r['cid_b'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $conf = calcConfidence(
                ['cid'=>$r['cid_a'],'first_name'=>$r['fn_a'],'last_name'=>$r['ln_a'],'house_no'=>$r['hn_a'],'moo'=>$r['moo_a'],'sub_district_code'=>$r['sub_a'],'birth'=>$r['birth_a'],'sex'=>$r['sex_a'],'pid'=>$r['pid_a'],'hoscode'=>$r['hsc_a']],
                ['cid'=>$r['cid_b'],'first_name'=>$r['fn_b'],'last_name'=>$r['ln_b'],'house_no'=>$r['hn_b'],'moo'=>$r['moo_b'],'sub_district_code'=>$r['sub_b'],'birth'=>$r['birth_b'],'sex'=>$r['sex_b'],'pid'=>$r['pid_b'],'hoscode'=>$r['hsc_b']]
            );
            // ตรวจสอบความถูกต้องของ CID (MOD11) และตัวจำลองขึ้นต้นด้วยรหัสหน่วยบริการ (Mock Hospital CID)
            $aIsMock = isMockHospitalCID($r['cid_a'], $r['hsc_a']);
            $bIsMock = isMockHospitalCID($r['cid_b'], $r['hsc_b']);
            $aIsValidMOD11 = isValidThaiCitizenIDMOD11($r['cid_a']);
            $bIsValidMOD11 = isValidThaiCitizenIDMOD11($r['cid_b']);

            if ($aIsMock && !$bIsMock && $bIsValidMOD11) {
                $masked = $r['cid_a'];
                $real   = $r['cid_b'];
            } elseif ($bIsMock && !$aIsMock && $aIsValidMOD11) {
                $masked = $r['cid_b'];
                $real   = $r['cid_a'];
            } else {
                // เลือก record ที่ชื่อสมบูรณ์กว่าเป็น "real"
                $aIsDefault = in_array($r['fn_a'], ['ไม่ทราบชื่อ','ไม่ทราบ','Unknown','']);
                $masked = $aIsDefault ? $r['cid_a'] : $r['cid_b'];
                $real   = $aIsDefault ? $r['cid_b'] : $r['cid_a'];
            }
            $dupes[] = array_merge($r, ['confidence' => $conf['score'], 'factors' => $conf['factors'], 'masked_cid' => $masked, 'real_cid' => $real]);
        }
    }

    // เรียงตาม confidence สูง→ต่ำ
    usort($dupes, fn($a, $b) => $b['confidence'] - $a['confidence']);
    return $dupes;
}

// ─────────────────────────────────────────────────────────────────────────────
// Determine current step from session/POST
// ─────────────────────────────────────────────────────────────────────────────
$step    = $_SESSION['etl_step'] ?? 1;   // 1=ETL, 2=DupReview, 3=Done
$etlResults  = $_SESSION['etl_results'] ?? null;
$dupData     = null;
$mergeResults = null;
$message = '';
$error   = '';

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Run ETL
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_etl'])) {
    try {
        $pdo->beginTransaction();

        $cidsQuery = $pdo->query("SELECT DISTINCT cid FROM staging_hdc_dm UNION SELECT DISTINCT cid FROM staging_hdc_ht");
        $allCids = $cidsQuery->fetchAll(PDO::FETCH_COLUMN);

        $inserted = 0; $updated = 0; $excluded_dm = 0; $skipped_invalid = 0;

        // ดึงรายชื่อเป้าหมายที่ได้รับการคัดกรองหรือเลื่อนตรวจ (เสร็จสมบูรณ์/ข้ามสะสม) ไปแล้วในปีงบประมาณปัจจุบัน
        $screenedCids = $pdo->query("SELECT DISTINCT target_cid FROM task_assignments WHERE assignment_status IN ('completed', 'skipped') AND budget_year = 2026")->fetchAll(PDO::FETCH_COLUMN);
        $screenedCidsMap = array_flip($screenedCids);

        // ดึงข้อมูลประชากรทั้งหมดพร้อมข้อมูลพิกัดบ้านมารอใน PHP Memory cache เพื่อความเร็วและ unmasked data mapping
        $targetPopByCid = [];
        $targetPopByHosPid = [];
        
        $allTargets = $pdo->query("
            SELECT t.cid, t.hoscode, t.pid, t.hid, t.house_no, t.moo, t.sub_district_code, t.vhid_code,
                   COALESCE(t.latitude, h.latitude) as latitude,
                   COALESCE(t.longitude, h.longitude) as longitude,
                   t.need_screen_dm, t.need_screen_ht
            FROM target_population t
            LEFT JOIN jhcis_homes h ON t.hoscode = h.hoscode AND t.hid = h.hid
        ")->fetchAll();
        
        foreach ($allTargets as $tg) {
            $c = trim($tg['cid']);
            $h = str_pad(trim($tg['hoscode']), 5, '0', STR_PAD_LEFT);
            $p = ltrim(trim($tg['pid']), '0');
            
            $targetPopByCid[$c] = $tg;
            $targetPopByHosPid["{$h}|{$p}"] = $tg;
        }

        foreach ($allCids as $cid) {
            if (!validateThaiCitizenID($cid)) { $skipped_invalid++; continue; }

            $stmtDm = $pdo->prepare("SELECT * FROM staging_hdc_dm WHERE cid = ? ORDER BY staging_id DESC LIMIT 1");
            $stmtDm->execute([$cid]); $dmData = $stmtDm->fetch();

            $stmtHt = $pdo->prepare("SELECT * FROM staging_hdc_ht WHERE cid = ? ORDER BY staging_id DESC LIMIT 1");
            $stmtHt->execute([$cid]); $htData = $stmtHt->fetch();

            $source = $dmData ?: $htData;
            if (!$source) continue;

            $firstName  = $source['name']        ?? 'ไม่ทราบชื่อ';
            $lastName   = $source['lname']       ?? 'ไม่ทราบประวัติ';
            $sex        = $source['sex']         ?? '1';
            $birth      = $source['birth']       ?? '1970-01-01';
            $hid        = $source['hid']         ?? '000000000000000';
            $addr       = $source['addr']        ?? '';
            $checkVhid  = $source['check_vhid']  ?? '';
            $pid        = !empty($source['pid']) ? ltrim(trim((string)$source['pid']), '0') : null;
            $hoscode    = trim((string)($source['hoscode'] ?? '00000'));
            if (is_numeric($hoscode) && strlen($hoscode) < 5) $hoscode = str_pad($hoscode, 5, '0', STR_PAD_LEFT);

            $houseNo = '';
            $moo = 1;
            if (preg_match('/^(\d+[\/\d]*)/', $addr, $m)) { $houseNo = $m[1]; } else { $houseNo = $addr; }

            if (strlen($checkVhid) === 8) {
                $moo = (int)substr($checkVhid, 6, 2);
                $subDistrictCode = substr($checkVhid, 0, 6);
            } else {
                $moo = 1; $subDistrictCode = '341801'; $checkVhid = '34180101';
            }

            $dmRisk = $dmData ? trim($dmData['risk']) : null;
            $htRisk = $htData ? trim($htData['risk']) : null;
            // Only mark need_screen = true for the disease(s) that actually have staging data
            $needScreenDm = ($dmData !== false);
            $needScreenHt = ($htData !== false);

            // Exclude diagnosed patients (risk = 5 means already a patient, no need to screen)
            if ($dmRisk === '5' || ($dmData && (mb_strpos($dmData['result'] ?? '', 'ผู้ป่วย') !== false))) {
                $needScreenDm = false; $excluded_dm++;
            }
            if ($htRisk === '5') $needScreenHt = false;

            // Determine health_status_origin based on which staging data exists and their risk levels
            if ($dmRisk === '2' || $htRisk === '2') $healthStatusOrigin = 'HIGH_RISK';
            elseif ($dmRisk === '1' && $htRisk === '1') $healthStatusOrigin = 'BOTH';
            elseif ($dmRisk === '1') $healthStatusOrigin = 'DM_ONLY';
            elseif ($htRisk === '1') $healthStatusOrigin = 'HT_ONLY';
            elseif ($dmRisk === '3' || $htRisk === '3') { $healthStatusOrigin = 'SUSPECT'; $needScreenDm = false; $needScreenHt = false; }
            else $healthStatusOrigin = 'NORMAL';

            // ตรวจสอบข้อมูลซ้ำซ้อนหรือ unmasked record ที่มีใน Cache
            $exists = null;
            $cleanHoscode = str_pad(trim($hoscode), 5, '0', STR_PAD_LEFT);
            $cleanPid = ltrim(trim($pid), '0');
            $cleanCid = trim($cid);

            if (isset($targetPopByCid[$cleanCid])) {
                $exists = $targetPopByCid[$cleanCid];
            } elseif (isset($targetPopByHosPid["{$cleanHoscode}|{$cleanPid}"])) {
                $exists = $targetPopByHosPid["{$cleanHoscode}|{$cleanPid}"];
            }

            if ($exists) {
                $realCid = $exists['cid'];
                
                // หาก CID เดิมในระบบเป็นรหัสจำลอง แต่ใน staging ได้รับ CID จริง (ตามหลัก MOD11) ให้สลับมาใช้ CID จริงทันที
                if (isMockHospitalCID($realCid, $exists['hoscode']) && isValidThaiCitizenIDMOD11($cleanCid)) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                    $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE target_cid = ?")->execute([$cleanCid, $realCid]);
                    $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE cid = ?")->execute([$cleanCid, $realCid]);
                    $pdo->prepare("UPDATE target_population SET cid = ? WHERE cid = ?")->execute([$cleanCid, $realCid]);
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                    
                    $realCid = $cleanCid;
                }
                
                $lat = ($exists['latitude'] !== null && $exists['latitude'] != 0) ? $exists['latitude'] : null;
                $lng = ($exists['longitude'] !== null && $exists['longitude'] != 0) ? $exists['longitude'] : null;
                if ($exists['need_screen_dm'] == 1) $needScreenDm = true;
                if ($exists['need_screen_ht'] == 1) $needScreenHt = true;
                
                // รักษาค่า hid ดั้งเดิมของ JHCIS ที่นำเข้าไว้
                $finalHid = !empty($exists['hid']) && $exists['hid'] !== '000000000000000' ? $exists['hid'] : ($hid ?: null);
                
                // คัดกรองแล้ว: ล็อกระดับความเสี่ยงตั้งต้น (health_status_origin) ไม่ให้อัปเดตซ้ำเพื่อความสม่ำเสมอของผลงาน
                $isAlreadyScreened = isset($screenedCidsMap[$realCid]);
                $finalHealthStatusOrigin = $isAlreadyScreened ? $exists['health_status_origin'] : $healthStatusOrigin;

                $updateStmt = $pdo->prepare("UPDATE target_population SET hid=?, house_no=?, moo=?, sub_district_code=?, vhid_code=?, latitude=?, longitude=?, health_status_origin=?, need_screen_dm=?, need_screen_ht=?, updated_at=NOW() WHERE cid=?");
                $updateStmt->execute([
                    $finalHid, 
                    $exists['house_no'] ?: $houseNo, 
                    $exists['moo'] ?: $moo, 
                    $exists['sub_district_code'] ?: $subDistrictCode, 
                    $exists['vhid_code'] ?: $checkVhid, 
                    $lat, 
                    $lng, 
                    $finalHealthStatusOrigin, 
                    $needScreenDm?1:0, 
                    $needScreenHt?1:0, 
                    $realCid
                ]);
                $updated++;
            } else {
                $insertCid = $cid;
                if (strpos($insertCid, '*') !== false && !empty($hoscode) && !empty($pid)) {
                    $insertCid = str_pad($hoscode, 5, '0', STR_PAD_LEFT) . str_pad($pid, 8, '0', STR_PAD_LEFT);
                }
                $insertStmt = $pdo->prepare("INSERT INTO target_population (cid, hid, pid, first_name, last_name, sex, birth, house_no, moo, sub_district_code, vhid_code, hoscode, latitude, longitude, health_status_origin, need_screen_dm, need_screen_ht) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([$insertCid, $hid, $pid, $firstName, $lastName, $sex, $birth, $houseNo, $moo, $subDistrictCode, $checkVhid, $hoscode, null, null, $healthStatusOrigin, $needScreenDm?1:0, $needScreenHt?1:0]);
                $inserted++;
            }
        }

        // ทำความสะอาดฐานข้อมูล: ลบประชากรที่นำเข้ามาจาก JHCIS Person ในตอนแรก แต่ไม่มีชื่อ/สิทธิ์อยู่ใน HDC ปีนี้
        // และไม่มีประวัติผลคัดกรอง หรือการมอบหมายงาน อสม. ค้างอยู่ และไม่ได้เพิ่มด้วยระบบ Manual
        $pdo->exec("
            DELETE t FROM target_population t
            LEFT JOIN task_assignments ta ON t.cid = ta.target_cid
            WHERE t.need_screen_dm = 0 
              AND t.need_screen_ht = 0 
              AND (t.is_manual IS NULL OR t.is_manual = 0)
              AND ta.assignment_id IS NULL
        ");

        $pdo->commit();
        $_SESSION['etl_results'] = ['total' => count($allCids), 'inserted' => $inserted, 'updated' => $updated, 'excluded_dm' => $excluded_dm, 'skipped_invalid' => $skipped_invalid];
        $_SESSION['etl_step'] = 2;
        header("Location: process_etl.php");
        exit();

    } catch (\Exception $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาดในการประมวลผล: " . $e->getMessage();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Load duplicate review data
// ─────────────────────────────────────────────────────────────────────────────
if ($step === 2 || (isset($_POST['action_review']))) {
    $_SESSION['etl_step'] = 2;
    $step = 2;
    try {
        // Standardize hoscode first
        foreach (['target_population','staging_hdc_dm','staging_hdc_ht'] as $tbl) {
            try { $pdo->exec("UPDATE `$tbl` SET hoscode = LPAD(TRIM(hoscode),5,'0') WHERE hoscode IS NOT NULL AND LENGTH(TRIM(hoscode)) < 5"); } catch(\Exception $e) {}
        }
        $dupes = findDuplicatesWithConfidence($pdo);

        // Enrich with screening/DPAC counts
        $stmtSc = $pdo->prepare("SELECT COUNT(*) FROM screening_results sr JOIN task_assignments ta ON sr.assignment_id=ta.assignment_id WHERE ta.target_cid=?");
        $stmtDp = $pdo->prepare("SELECT COUNT(*) FROM dpac_enrollments WHERE cid=?");
        foreach ($dupes as &$d) {
            $stmtSc->execute([$d['masked_cid']]); $d['screen_count'] = (int)$stmtSc->fetchColumn();
            $stmtDp->execute([$d['masked_cid']]); $d['dpac_count']   = (int)$stmtDp->fetchColumn();
        }
        unset($d);

        // Count default-name records
        $defaultCount = (int)$pdo->query("SELECT COUNT(*) FROM target_population WHERE first_name IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown','') OR last_name IN ('ไม่ทราบประวัติ','ไม่ทราบ','Unknown','')")->fetchColumn();

        $dupData = ['pairs' => $dupes, 'default_count' => $defaultCount];
    } catch(\Exception $e) {
        $error = "ไม่สามารถโหลดข้อมูลซ้ำซ้อน: " . $e->getMessage();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: Merge selected pairs
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_merge'])) {
    $toMerge = $_POST['merge_pairs'] ?? []; // array of "masked_cid|real_cid"
    $merged = 0; $skipped = 0; $errors = [];

    if (!empty($toMerge)) {
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

            $stmtGetAssign    = $pdo->prepare("SELECT * FROM task_assignments WHERE target_cid = ?");
            $stmtGetAssign2   = $pdo->prepare("SELECT * FROM task_assignments WHERE target_cid = ?");
            $stmtDelAssign    = $pdo->prepare("DELETE FROM task_assignments WHERE assignment_id = ?");
            $stmtUpdAssign    = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE assignment_id = ?");
            $stmtMoveScreen   = $pdo->prepare("UPDATE screening_results SET assignment_id = ? WHERE assignment_id = ?");
            $stmtGetDpac      = $pdo->prepare("SELECT * FROM dpac_enrollments WHERE cid = ?");
            $stmtGetDpac2     = $pdo->prepare("SELECT * FROM dpac_enrollments WHERE cid = ?");
            $stmtDelDpac      = $pdo->prepare("DELETE FROM dpac_enrollments WHERE enrollment_id = ?");
            $stmtUpdDpac      = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE enrollment_id = ?");
            $stmtMoveFollowup = $pdo->prepare("UPDATE dpac_followups SET enrollment_id = ? WHERE enrollment_id = ?");
            $stmtDelTarget    = $pdo->prepare("DELETE FROM target_population WHERE cid = ?");

            foreach ($toMerge as $pair) {
                [$masked_cid, $real_cid] = explode('|', $pair);
                if (empty($masked_cid) || empty($real_cid) || $masked_cid === $real_cid) { $skipped++; continue; }

                try {
                    $pdo->beginTransaction();

                    // Merge task_assignments
                    $stmtGetAssign->execute([$masked_cid]); $maskedAssigns = $stmtGetAssign->fetchAll();
                    $stmtGetAssign2->execute([$real_cid]);  $realAssigns   = $stmtGetAssign2->fetchAll();
                    $realByYear = [];
                    foreach ($realAssigns as $ra) $realByYear[$ra['budget_year']] = $ra;

                    foreach ($maskedAssigns as $ma) {
                        $yr = $ma['budget_year'];
                        if (isset($realByYear[$yr])) {
                            $cntSc = $pdo->prepare("SELECT COUNT(*) FROM screening_results WHERE assignment_id=?");
                            $cntSc->execute([$ma['assignment_id']]);
                            if ($cntSc->fetchColumn() > 0) $stmtMoveScreen->execute([$realByYear[$yr]['assignment_id'], $ma['assignment_id']]);
                            $stmtDelAssign->execute([$ma['assignment_id']]);
                        } else {
                            $stmtUpdAssign->execute([$real_cid, $ma['assignment_id']]);
                        }
                    }

                    // Merge DPAC enrollments
                    $stmtGetDpac->execute([$masked_cid]);  $maskedDpac = $stmtGetDpac->fetchAll();
                    $stmtGetDpac2->execute([$real_cid]);   $realDpac   = $stmtGetDpac2->fetchAll();
                    $realDpacByYear = [];
                    foreach ($realDpac as $rd) $realDpacByYear[$rd['budget_year']] = $rd;

                    foreach ($maskedDpac as $md) {
                        $yr = $md['budget_year'];
                        if (isset($realDpacByYear[$yr])) {
                            $stmtMoveFollowup->execute([$realDpacByYear[$yr]['enrollment_id'], $md['enrollment_id']]);
                            $stmtDelDpac->execute([$md['enrollment_id']]);
                        } else {
                            $stmtUpdDpac->execute([$real_cid, $md['enrollment_id']]);
                        }
                    }

                    // คัดลอกข้อมูล Demographics ที่สมบูรณ์/ไม่ปกปิดจาก record ที่จะถูกลบไปยัง record ที่จะเก็บไว้
                    $stmtUpdateDemographics = $pdo->prepare("
                        UPDATE target_population t_keep
                        JOIN target_population t_del ON t_del.cid = ?
                        SET 
                            t_keep.first_name = CASE WHEN (t_keep.first_name LIKE '%*%' OR t_keep.first_name IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown','')) AND t_del.first_name NOT LIKE '%*%' AND t_del.first_name NOT IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown','') THEN t_del.first_name ELSE t_keep.first_name END,
                            t_keep.last_name = CASE WHEN (t_keep.last_name LIKE '%*%' OR t_keep.last_name IN ('ไม่ทราบประวัติ','ไม่ทราบ','Unknown','')) AND t_del.last_name NOT LIKE '%*%' AND t_del.last_name NOT IN ('ไม่ทราบประวัติ','ไม่ทราบ','Unknown','') THEN t_del.last_name ELSE t_keep.last_name END,
                            t_keep.sex = CASE WHEN (t_keep.sex IS NULL OR t_keep.sex = '1') AND t_del.sex != '1' THEN t_del.sex ELSE t_keep.sex END,
                            t_keep.birth = CASE WHEN (t_keep.birth IS NULL OR t_keep.birth = '1970-01-01') AND t_del.birth != '1970-01-01' THEN t_del.birth ELSE t_keep.birth END,
                            t_keep.pid = CASE WHEN (t_keep.pid IS NULL OR t_keep.pid = '') THEN t_del.pid ELSE t_keep.pid END,
                            t_keep.hid = CASE WHEN (t_keep.hid IS NULL OR t_keep.hid = '' OR t_keep.hid = '000000000000000') THEN t_del.hid ELSE t_keep.hid END,
                            t_keep.house_no = CASE WHEN (t_keep.house_no IS NULL OR t_keep.house_no = '') THEN t_del.house_no ELSE t_keep.house_no END,
                            t_keep.moo = CASE WHEN (t_keep.moo IS NULL OR t_keep.moo = 0 OR t_keep.moo = 1) AND t_del.moo > 1 THEN t_del.moo ELSE t_keep.moo END,
                            t_keep.sub_district_code = CASE WHEN (t_keep.sub_district_code IS NULL OR t_keep.sub_district_code = '' OR t_keep.sub_district_code = '341801') AND t_del.sub_district_code != '341801' THEN t_del.sub_district_code ELSE t_keep.sub_district_code END,
                            t_keep.vhid_code = CASE WHEN (t_keep.vhid_code IS NULL OR t_keep.vhid_code = '' OR t_keep.vhid_code = '34180101') AND t_del.vhid_code != '34180101' THEN t_del.vhid_code ELSE t_keep.vhid_code END,
                            t_keep.latitude = CASE WHEN (t_keep.latitude IS NULL OR t_keep.latitude = 0) AND t_del.latitude != 0 THEN t_del.latitude ELSE t_keep.latitude END,
                            t_keep.longitude = CASE WHEN (t_keep.longitude IS NULL OR t_keep.longitude = 0) AND t_del.longitude != 0 THEN t_del.longitude ELSE t_keep.longitude END,
                            t_keep.need_screen_dm = CASE WHEN t_del.need_screen_dm = 1 THEN 1 ELSE t_keep.need_screen_dm END,
                            t_keep.need_screen_ht = CASE WHEN t_del.need_screen_ht = 1 THEN 1 ELSE t_keep.need_screen_ht END,
                            t_keep.health_status_origin = CASE WHEN t_keep.health_status_origin = 'NORMAL' AND t_del.health_status_origin != 'NORMAL' THEN t_del.health_status_origin ELSE t_keep.health_status_origin END,
                            t_keep.updated_at = NOW()
                        WHERE t_keep.cid = ?
                    ");
                    $stmtUpdateDemographics->execute([$masked_cid, $real_cid]);

                    // Delete masked target record
                    $stmtDelTarget->execute([$masked_cid]);

                    $pdo->commit();
                    $merged++;
                } catch (\Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = "CID $masked_cid: " . $e->getMessage();
                    $skipped++;
                }
            }

            // Update default names from staging
            $updNames = $pdo->exec("
                UPDATE target_population t
                JOIN (
                    SELECT dm.pid, dm.hoscode,
                           COALESCE(NULLIF(dm.name,''), NULLIF(ht.hname,'')) AS fname,
                           COALESCE(NULLIF(dm.lname,''), NULLIF(ht.hlname,'')) AS lname
                    FROM (SELECT DISTINCT pid, hoscode, name, lname FROM staging_hdc_dm) dm
                    LEFT JOIN (SELECT DISTINCT pid, hoscode, name AS hname, lname AS hlname FROM staging_hdc_ht) ht
                      ON dm.pid = ht.pid AND dm.hoscode = ht.hoscode
                ) s ON t.hoscode = s.hoscode
                   AND t.pid = s.pid
                SET
                    t.first_name = CASE WHEN t.first_name IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown','') AND s.fname IS NOT NULL AND s.fname != '' THEN s.fname ELSE t.first_name END,
                    t.last_name  = CASE WHEN t.last_name IN ('ไม่ทราบประวัติ','ไม่ทราบ','Unknown','') AND s.lname IS NOT NULL AND s.lname != '' THEN s.lname ELSE t.last_name END,
                    t.updated_at = NOW()
                WHERE t.first_name IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown','')
                   OR t.last_name  IN ('ไม่ทราบประวัติ','ไม่ทราบ','Unknown','')
            ");

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $_SESSION['etl_step'] = 3;
            $_SESSION['merge_results'] = ['merged' => $merged, 'skipped' => $skipped, 'errors' => $errors, 'updated_names' => $updNames];
            header("Location: process_etl.php");
            exit();
        } catch (\Exception $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ACTION: Skip dup review, go to step 3
if (isset($_POST['action_skip_review'])) {
    $_SESSION['etl_step'] = 3;
    $_SESSION['merge_results'] = ['merged' => 0, 'skipped' => 0, 'errors' => [], 'updated_names' => 0];
    header("Location: process_etl.php");
    exit();
}

// ACTION: Reset / Start Over
if (isset($_POST['action_reset']) || isset($_GET['reset'])) {
    unset($_SESSION['etl_step'], $_SESSION['etl_results'], $_SESSION['merge_results']);
    header("Location: process_etl.php");
    exit();
}

// Load from session
if ($step === 3) {
    $mergeResults = $_SESSION['merge_results'] ?? null;
    $etlResults   = $_SESSION['etl_results']   ?? null;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ETL Engine & ตรวจสอบข้อมูลซ้ำ - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Wizard Steps ─────────────────────────────────── */
        .wizard-steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 32px;
            position: relative;
        }
        .wizard-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            position: relative;
            flex: 1;
        }
        .wizard-step-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 900;
            box-shadow: var(--neumorph-flat);
            background-color: var(--bg-card);
            color: var(--text-muted);
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }
        .wizard-step.active .wizard-step-circle {
            background: linear-gradient(135deg, #0d2c54, #1e40af);
            color: #fff;
            box-shadow: 0 4px 16px rgba(13,44,84,0.3);
        }
        .wizard-step.done .wizard-step-circle {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            box-shadow: 0 4px 12px rgba(16,185,129,0.3);
        }
        .wizard-step-label {
            font-size: 12px;
            font-weight: 800;
            color: var(--text-muted);
            text-align: center;
        }
        .wizard-step.active .wizard-step-label,
        .wizard-step.done .wizard-step-label { color: var(--text-primary); }
        .wizard-connector {
            flex: 1;
            height: 3px;
            background: var(--bg-darker);
            margin: 0 -2px;
            margin-bottom: 28px;
            position: relative;
            z-index: 1;
        }
        .wizard-connector.done { background: linear-gradient(90deg, #10b981, #059669); }

        /* ── Duplicate Pair Card ──────────────────────────── */
        .dup-pair-card {
            background: var(--bg-card);
            border-radius: 20px;
            box-shadow: var(--neumorph-flat);
            margin-bottom: 16px;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .dup-pair-card.selected {
            box-shadow: 0 0 0 2px #10b981, var(--neumorph-flat);
        }
        .dup-pair-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            background: var(--bg-darker);
            cursor: pointer;
            user-select: none;
        }
        .dup-pair-body {
            display: none;
            padding: 16px 20px;
        }
        .dup-pair-body.open { display: block; }
        .confidence-bar-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .confidence-bar {
            flex: 1;
            height: 8px;
            background: var(--bg-main);
            border-radius: 50px;
            overflow: hidden;
            box-shadow: var(--neumorph-inset);
        }
        .confidence-fill {
            height: 100%;
            border-radius: 50px;
            transition: width 0.6s cubic-bezier(0.34,1.56,0.64,1);
        }
        .confidence-fill.high   { background: linear-gradient(90deg, #10b981, #059669); }
        .confidence-fill.medium { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .confidence-fill.low    { background: linear-gradient(90deg, #6b7280, #9ca3af); }

        .compare-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }
        .compare-col {
            background: var(--bg-main);
            border-radius: 12px;
            padding: 12px 14px;
        }
        .compare-col-label {
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            padding: 3px 10px;
            border-radius: 50px;
            display: inline-block;
        }
        .label-remove { background: rgba(239,68,68,0.12); color: var(--color-red); }
        .label-keep   { background: rgba(16,185,129,0.12); color: var(--color-green); }
        .compare-field { font-size: 13px; margin-bottom: 4px; }
        .compare-field strong { color: var(--text-primary); }
        .compare-field span   { color: var(--text-secondary); }
        .highlight-diff { background: rgba(245,158,11,0.15); border-radius: 4px; padding: 0 4px; }

        .factor-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            margin: 2px 3px 2px 0;
        }
        .factor-high   { background: rgba(16,185,129,0.12); color: #059669; }
        .factor-medium { background: rgba(245,158,11,0.12); color: #d97706; }
        .factor-low    { background: rgba(107,114,128,0.1); color: #4b5563; }
        .factor-info   { background: rgba(239,68,68,0.1);  color: var(--color-red); }

        /* ── Action area ────────────────────────────────────── */
        .action-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-top: 12px;
            border-top: 1px solid rgba(0,0,0,0.05);
            margin-top: 12px;
        }
        .btn-merge {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 50px;
            background: var(--color-primary);
            color: #fff;
            font-weight: 800;
            font-size: 14px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(13,44,84,0.2);
            transition: all 0.2s;
            font-family: var(--font-base);
        }
        .btn-merge:active { transform: scale(0.97); }
        .btn-merge.selected-merge { background: var(--color-green); }
        .btn-skip-pair {
            padding: 8px 16px;
            border-radius: 50px;
            border: none;
            background: var(--bg-darker);
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--font-base);
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s;
        }
        .btn-skip-pair:hover { box-shadow: var(--neumorph-inset); }

        /* ── Stats bar ──────────────────────────────────────── */
        .stats-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-card);
            border-radius: 50px;
            padding: 8px 18px;
            box-shadow: var(--neumorph-flat);
            font-weight: 800;
            font-size: 14px;
        }
        .stat-chip-icon { font-size: 18px; }

        /* ── Sticky merge toolbar ─────────────────────────────── */
        #merge-toolbar {
            position: sticky;
            bottom: 16px;
            z-index: 100;
            display: none;
            justify-content: center;
        }
        .merge-toolbar-inner {
            background: rgba(13,44,84,0.92);
            backdrop-filter: blur(12px);
            color: #fff;
            border-radius: 50px;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(13,44,84,0.35);
        }
        .merge-toolbar-count { font-size: 15px; font-weight: 800; }
        .btn-toolbar-merge {
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 10px 24px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            font-family: var(--font-base);
            transition: all 0.2s;
        }
        .btn-toolbar-merge:hover { background: #059669; }
        .btn-toolbar-cancel {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--font-base);
            transition: all 0.2s;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner-sm {
            width: 18px; height: 18px;
            border: 3px solid rgba(255,255,255,0.2);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: inline-block;
        }
    </style>
</head>
<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1000px; margin: 32px auto; padding: 0 20px;">

        <!-- ── Wizard Steps ──────────────────────────────────────────────── -->
        <div class="wizard-steps">
            <div class="wizard-step <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">
                <div class="wizard-step-circle"><?= $step > 1 ? '✓' : '1' ?></div>
                <div class="wizard-step-label">ประมวลผล ETL</div>
            </div>
            <div class="wizard-connector <?= $step > 1 ? 'done' : '' ?>"></div>
            <div class="wizard-step <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">
                <div class="wizard-step-circle"><?= $step > 2 ? '✓' : '2' ?></div>
                <div class="wizard-step-label">ตรวจสอบข้อมูลซ้ำ</div>
            </div>
            <div class="wizard-connector <?= $step > 2 ? 'done' : '' ?>"></div>
            <div class="wizard-step <?= $step >= 3 ? 'active' : '' ?>">
                <div class="wizard-step-circle">3</div>
                <div class="wizard-step-label">สรุปผลดำเนินการ</div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
        <div style="background: rgba(239,68,68,0.1); border: 1px solid var(--color-red); color: var(--color-red); padding: 14px 18px; border-radius: 16px; margin-bottom: 20px; font-weight: 700;">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- STEP 1: ETL Engine                                            -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <?php if ($step === 1): ?>
        <div class="card-dark">
            <h2 style="color: var(--color-accent); margin-top:0; display:flex; align-items:center; gap:10px;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18"></path></svg>
                ขั้นตอนที่ 1 — ETL Consolidation Engine
            </h2>
            <p style="color: var(--text-secondary); line-height: 1.8; margin-bottom: 20px;">
                ระบบจะดึงข้อมูลจาก <code>staging_hdc_dm</code> และ <code>staging_hdc_ht</code> มารวมกัน วิเคราะห์ความเสี่ยง และนำเข้า <code>target_population</code> จากนั้นจะเข้าสู่ขั้นตอนตรวจสอบข้อมูลซ้ำซ้อนอัตโนมัติ
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 12px; margin-bottom: 28px;">
                <div style="background: var(--bg-darker); border-radius: 16px; padding: 14px 16px;">
                    <div style="font-size: 12px; color: var(--text-muted); font-weight: 700;">🎯 STEP 1</div>
                    <div style="font-weight: 800; margin-top: 4px;">ETL & นำเข้าข้อมูล</div>
                </div>
                <div style="background: var(--bg-darker); border-radius: 16px; padding: 14px 16px;">
                    <div style="font-size: 12px; color: var(--text-muted); font-weight: 700;">🔍 STEP 2</div>
                    <div style="font-weight: 800; margin-top: 4px;">ตรวจหา & จัดการข้อมูลซ้ำ</div>
                </div>
                <div style="background: var(--bg-darker); border-radius: 16px; padding: 14px 16px;">
                    <div style="font-size: 12px; color: var(--text-muted); font-weight: 700;">✅ STEP 3</div>
                    <div style="font-weight: 800; margin-top: 4px;">สรุปผลและสำเร็จ</div>
                </div>
            </div>
            <form method="POST" id="etl-form">
                <button type="submit" name="action_etl" class="btn-giant btn-giant-primary" style="border-radius: var(--border-radius);">
                    🚀 เริ่มประมวลผลข้อมูล (Run ETL Engine)
                </button>
            </form>
        </div>

        <!-- ── Loading overlay ── -->
        <div id="progress-overlay" style="display:none; flex-direction:column; align-items:center; justify-content:center; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(13,44,84,0.4); backdrop-filter:blur(4px); z-index:9999;">
            <div style="background:rgba(13,44,84,0.92); color:#fff; padding:40px; border-radius:24px; box-shadow:0 20px 50px rgba(0,0,0,0.3); width:90%; max-width:420px; text-align:center;">
                <div style="width:50px;height:50px;border:4px solid rgba(255,255,255,0.1);border-top-color:#38bdf8;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 24px;"></div>
                <h3 id="etl-status-msg" style="margin:0 0 10px;font-size:18px;">กำลังประมวลผลข้อมูล...</h3>
                <p style="margin:0;font-size:13px;opacity:0.6;">กรุณารอสักครู่ อย่าปิดหน้านี้</p>
                <div style="background:rgba(255,255,255,0.1);border-radius:10px;height:8px;overflow:hidden;margin-top:20px;">
                    <div id="etl-progress-bar" style="background:linear-gradient(90deg,#0d2c54,#38bdf8);width:0%;height:100%;border-radius:10px;transition:width 0.2s ease-out;"></div>
                </div>
            </div>
        </div>
        <script>
        const etlMsgs = [
            'กำลังดึง CID จาก staging tables...','กำลังวิเคราะห์ความเสี่ยง DM/HT...','กำลังตรวจสอบ Exclusion Rules...','กำลังบันทึกรายชื่อใหม่...','กำลังปรับปรุงข้อมูลเดิม...','เกือบเสร็จแล้ว...'
        ];
        document.getElementById('etl-form').addEventListener('submit', () => {
            const overlay = document.getElementById('progress-overlay');
            const bar = document.getElementById('etl-progress-bar');
            const msg = document.getElementById('etl-status-msg');
            overlay.style.display = 'flex';
            let pct = 0, mi = 0;
            const iv = setInterval(() => {
                pct = Math.min(pct + Math.random()*6, 92);
                bar.style.width = pct + '%';
                mi = Math.min(Math.floor(pct / 100 * etlMsgs.length), etlMsgs.length-1);
                msg.textContent = etlMsgs[mi];
            }, 350);
        });
        </script>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- STEP 2: Duplicate Review                                      -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <?php if ($step === 2 && $dupData !== null): ?>

        <!-- ETL Result Summary -->
        <?php if ($etlResults): ?>
        <div style="background: rgba(16,185,129,0.08); border: 1px solid var(--color-green); border-radius: 16px; padding: 14px 20px; margin-bottom: 20px; display:flex; flex-wrap:wrap; gap:20px; align-items:center;">
            <span style="color: var(--color-green); font-weight:800;">✅ ETL สำเร็จ!</span>
            <span style="color:var(--text-secondary); font-size:13px;">นำเข้าใหม่ <strong style="color:var(--text-primary)"><?= $etlResults['inserted'] ?></strong> ราย | อัปเดต <strong style="color:var(--text-primary)"><?= $etlResults['updated'] ?></strong> ราย | คัดออก <strong style="color:var(--text-primary)"><?= $etlResults['excluded_dm'] ?></strong> ราย</span>
        </div>
        <?php endif; ?>

        <div class="card-dark" style="margin-bottom: 20px;">
            <h2 style="color: var(--color-accent); margin-top:0; display:flex; align-items:center; gap:10px;">
                🔍 ขั้นตอนที่ 2 — ตรวจสอบและจัดการข้อมูลซ้ำซ้อน
            </h2>
            <p style="color: var(--text-secondary); margin-bottom: 16px; line-height: 1.8;">
                ระบบตรวจพบข้อมูลที่อาจเป็นบุคคลเดียวกัน โดยคำนวณ <strong>คะแนนความน่าจะใช่ (Confidence Score)</strong> จากหลายปัจจัย
                เลือกคู่ที่ต้องการ <strong>รวมข้อมูล</strong> แล้วกดยืนยัน — ประวัติการคัดกรองและ DPAC จะถูกย้ายไปยัง record จริงโดยอัตโนมัติ
            </p>

            <div class="stats-bar">
                <div class="stat-chip">
                    <span class="stat-chip-icon">⚠️</span>
                    <span>พบซ้ำซ้อน <strong><?= count($dupData['pairs']) ?></strong> คู่</span>
                </div>
                <?php
                $highConf = count(array_filter($dupData['pairs'], fn($d) => $d['confidence'] >= 70));
                $medConf  = count(array_filter($dupData['pairs'], fn($d) => $d['confidence'] >= 40 && $d['confidence'] < 70));
                ?>
                <?php if ($highConf > 0): ?>
                <div class="stat-chip" style="color: var(--color-green);">
                    <span class="stat-chip-icon">🟢</span>
                    <span>มั่นใจสูง <strong><?= $highConf ?></strong> คู่</span>
                </div>
                <?php endif; ?>
                <?php if ($medConf > 0): ?>
                <div class="stat-chip" style="color: var(--color-yellow);">
                    <span class="stat-chip-icon">🟡</span>
                    <span>ปานกลาง <strong><?= $medConf ?></strong> คู่</span>
                </div>
                <?php endif; ?>
                <?php if ($dupData['default_count'] > 0): ?>
                <div class="stat-chip" style="color: var(--color-yellow);">
                    <span class="stat-chip-icon">📝</span>
                    <span>ชื่อ default ที่จะอัปเดต <strong><?= $dupData['default_count'] ?></strong> ราย</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Bulk Select -->
            <?php if (count($dupData['pairs']) > 0): ?>
            <div style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
                <button type="button" onclick="selectAll()" class="btn-skip-pair" style="background: rgba(13,44,84,0.08); color: var(--color-primary);">
                    ☑️ เลือกทั้งหมด (คะแนนสูง)
                </button>
                <button type="button" onclick="selectNone()" class="btn-skip-pair">
                    ☐ ยกเลิกทั้งหมด
                </button>
            </div>
            <?php endif; ?>
        </div>

        <form method="POST" id="merge-form">
            <?php if (count($dupData['pairs']) === 0): ?>
            <div class="card-dark" style="text-align:center; padding: 40px;">
                <div style="font-size:48px; margin-bottom:12px;">🎉</div>
                <h3 style="color: var(--color-green); margin:0 0 8px;">ไม่พบข้อมูลซ้ำซ้อน!</h3>
                <p style="color: var(--text-secondary);">ข้อมูลในระบบสะอาดดีแล้ว พร้อมดำเนินการต่อ</p>
            </div>
            <?php else: ?>

            <?php foreach ($dupData['pairs'] as $idx => $dup):
                $conf     = $dup['confidence'];
                $confClass = $conf >= 70 ? 'high' : ($conf >= 40 ? 'medium' : 'low');
                $confLabel = $conf >= 70 ? 'มั่นใจสูง' : ($conf >= 40 ? 'ปานกลาง' : 'ต่ำ');
                $pairKey   = $dup['masked_cid'] . '|' . $dup['real_cid'];
                $isMasked  = strpos($dup['masked_cid'], '*') !== false;
            ?>
            <div class="dup-pair-card" id="card-<?= $idx ?>">
                <!-- Header (Clickable to expand) -->
                <div class="dup-pair-header" onclick="toggleCard(<?= $idx ?>)">
                    <!-- Checkbox -->
                    <label onclick="event.stopPropagation()" style="display:flex; align-items:center; cursor:pointer;">
                        <input type="checkbox" name="merge_pairs[]" value="<?= htmlspecialchars($pairKey) ?>"
                               id="chk-<?= $idx ?>" onchange="onCheckChange(<?= $idx ?>)"
                               style="width:18px; height:18px; cursor:pointer; accent-color: var(--color-green);"
                               <?= $conf >= 70 ? 'checked' : '' ?>>
                    </label>

                    <!-- Names -->
                    <div style="min-width:0; flex:1;">
                        <div style="font-weight:800; font-size:15px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars($dup['fn_b'] . ' ' . $dup['ln_b']) ?>
                            <?php if ($isMasked): ?>
                            <span style="background:rgba(239,68,68,0.1); color:var(--color-red); font-size:11px; padding:2px 8px; border-radius:50px; margin-left:6px; font-weight:700;">CID ปกปิด</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:12px; color: var(--text-muted);">
                            หมู่ <?= intval($dup['moo_b'] ?: $dup['moo_a']) ?> | pid: <?= htmlspecialchars(ltrim($dup['pid_b'] ?: $dup['pid_a'] ?? '', '0')) ?>
                        </div>
                    </div>

                    <!-- Confidence -->
                    <div style="width:160px; flex-shrink:0;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                            <span style="font-size:11px; font-weight:700; color:var(--text-muted);">Confidence</span>
                            <span style="font-size:13px; font-weight:900; color: <?= $conf >= 70 ? 'var(--color-green)' : ($conf >= 40 ? 'var(--color-yellow)' : 'var(--text-muted)') ?>;">
                                <?= $conf ?>% <span style="font-size:11px;"><?= $confLabel ?></span>
                            </span>
                        </div>
                        <div class="confidence-bar">
                            <div class="confidence-fill <?= $confClass ?>" style="width:<?= $conf ?>%"></div>
                        </div>
                    </div>

                    <!-- Badges -->
                    <div style="flex-shrink:0; display:flex; gap:4px; flex-wrap:wrap; justify-content:flex-end;">
                        <?php if ($dup['screen_count'] > 0): ?>
                        <span style="background:rgba(16,185,129,0.12); color:var(--color-green); padding:2px 10px; border-radius:50px; font-size:11px; font-weight:800;">📋 <?= $dup['screen_count'] ?> screens</span>
                        <?php endif; ?>
                        <?php if ($dup['dpac_count'] > 0): ?>
                        <span style="background:rgba(13,44,84,0.1); color:var(--color-primary); padding:2px 10px; border-radius:50px; font-size:11px; font-weight:800;">💊 <?= $dup['dpac_count'] ?> DPAC</span>
                        <?php endif; ?>
                        <span style="font-size:18px; color:var(--text-muted);" id="toggle-icon-<?= $idx ?>">▾</span>
                    </div>
                </div>

                <!-- Body (Expandable) -->
                <div class="dup-pair-body <?= $conf >= 70 ? 'open' : '' ?>" id="body-<?= $idx ?>">
                    <!-- Matching factors -->
                    <div style="margin-bottom: 12px;">
                        <span style="font-size:12px; font-weight:800; color:var(--text-muted); margin-right:6px;">เหตุผลที่ตรวจพบ:</span>
                        <?php foreach ($dup['factors'] as $f): ?>
                        <span class="factor-chip factor-<?= $f['weight'] ?>"><?= $f['icon'] ?> <?= htmlspecialchars($f['label']) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <!-- Side-by-side comparison -->
                    <div class="compare-grid">
                        <!-- Masked/Duplicate record (will be deleted) -->
                        <div class="compare-col">
                            <div class="compare-col-label label-remove">🗑️ Record ที่จะถูกลบ</div>
                            <div class="compare-field"><span>CID: </span><strong style="font-size:12px; font-family:monospace;"><?= htmlspecialchars($dup['masked_cid']) ?></strong></div>
                            <div class="compare-field"><span>ชื่อ: </span><strong><?= htmlspecialchars($dup['fn_a'] . ' ' . $dup['ln_a']) ?></strong></div>
                            <div class="compare-field"><span>บ้านเลขที่: </span><strong><?= htmlspecialchars($dup['hn_a'] ?: '-') ?></strong></div>
                            <div class="compare-field"><span>หมู่: </span><strong><?= intval($dup['moo_a']) ?></strong></div>
                            <div class="compare-field"><span>วันเกิด: </span><strong><?= htmlspecialchars($dup['birth_a'] ?: '-') ?></strong></div>
                            <div class="compare-field"><span>HID: </span><strong style="font-size:11px; font-family:monospace;"><?= htmlspecialchars($dup['hid_a'] ?: '-') ?></strong></div>
                            <?php if ($dup['screen_count'] > 0 || $dup['dpac_count'] > 0): ?>
                            <div style="margin-top:8px; padding:6px 10px; background:rgba(16,185,129,0.08); border-radius:8px; font-size:12px; color:var(--color-green);">
                                ⚠️ มีข้อมูล <?= $dup['screen_count'] > 0 ? "Screening {$dup['screen_count']} รายการ " : '' ?><?= $dup['dpac_count'] > 0 ? "DPAC {$dup['dpac_count']} รายการ" : '' ?> — จะถูกย้ายไปยัง record จริง
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Real record (will be kept) -->
                        <div class="compare-col">
                            <div class="compare-col-label label-keep">✅ Record จริง (จะถูกเก็บไว้)</div>
                            <div class="compare-field"><span>CID: </span><strong style="font-size:12px; font-family:monospace; color:var(--color-green);"><?= htmlspecialchars($dup['real_cid']) ?></strong></div>
                            <div class="compare-field"><span>ชื่อ: </span><strong><?= htmlspecialchars($dup['fn_b'] . ' ' . $dup['ln_b']) ?></strong></div>
                            <div class="compare-field"><span>บ้านเลขที่: </span><strong><?= htmlspecialchars($dup['hn_b'] ?: '-') ?></strong></div>
                            <div class="compare-field"><span>หมู่: </span><strong><?= intval($dup['moo_b']) ?></strong></div>
                            <div class="compare-field"><span>วันเกิด: </span><strong><?= htmlspecialchars($dup['birth_b'] ?: '-') ?></strong></div>
                            <div class="compare-field"><span>HID: </span><strong style="font-size:11px; font-family:monospace;"><?= htmlspecialchars($dup['hid_b'] ?: '-') ?></strong></div>
                        </div>
                    </div>

                    <!-- Action -->
                    <div class="action-row">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; flex:1;">
                            <input type="checkbox" name="merge_pairs[]" value="<?= htmlspecialchars($pairKey) ?>"
                                   id="chk2-<?= $idx ?>" onchange="syncCheckbox(<?= $idx ?>)"
                                   style="width:18px; height:18px; accent-color: var(--color-green);"
                                   <?= $conf >= 70 ? 'checked' : '' ?>>
                            <span style="font-weight:800; font-size:14px;">รวมข้อมูลทั้งสองเป็น record เดียว</span>
                        </label>
                        <span style="font-size:13px; color:var(--text-muted);">ยกเลิก checkbox เพื่อข้ามคู่นี้</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div style="display:flex; gap:12px; margin-top:24px; flex-wrap:wrap;">
                <?php if (count($dupData['pairs']) > 0): ?>
                <button type="submit" name="action_merge" class="btn-giant btn-giant-primary" style="border-radius: var(--border-radius); flex:1; min-width:200px;">
                    ✅ รวมข้อมูลที่เลือก และดำเนินการต่อ
                </button>
                <?php endif; ?>
                <button type="submit" name="action_skip_review" class="btn-giant btn-giant-secondary" style="border-radius: var(--border-radius); <?= count($dupData['pairs']) > 0 ? 'flex:0; min-width:160px;' : 'flex:1;' ?>">
                    <?= count($dupData['pairs']) === 0 ? '✅ ดำเนินการต่อ →' : '⏩ ข้ามขั้นตอนนี้' ?>
                </button>
            </div>
        </form>

        <!-- Sticky toolbar -->
        <div id="merge-toolbar" style="display:none; justify-content:center; position:sticky; bottom:16px; z-index:100;">
            <div class="merge-toolbar-inner">
                <span class="merge-toolbar-count">เลือก <span id="selected-count">0</span> คู่</span>
                <button type="button" class="btn-toolbar-merge" onclick="document.getElementById('merge-form').querySelector('[name=action_merge]').click()">
                    ✅ รวมข้อมูลที่เลือก
                </button>
                <button type="button" class="btn-toolbar-cancel" onclick="selectNone()">ยกเลิก</button>
            </div>
        </div>

        <script>
        function toggleCard(idx) {
            const body = document.getElementById('body-' + idx);
            const icon = document.getElementById('toggle-icon-' + idx);
            body.classList.toggle('open');
            icon.textContent = body.classList.contains('open') ? '▴' : '▾';
        }
        function syncCheckbox(idx) {
            const c1 = document.getElementById('chk-'  + idx);
            const c2 = document.getElementById('chk2-' + idx);
            if (c1 && c2) { c1.checked = c2.checked; }
            updateCard(idx);
            updateToolbar();
        }
        function onCheckChange(idx) {
            const c1 = document.getElementById('chk-'  + idx);
            const c2 = document.getElementById('chk2-' + idx);
            if (c1 && c2) c2.checked = c1.checked;
            updateCard(idx);
            updateToolbar();
        }
        function updateCard(idx) {
            const c = document.getElementById('chk-' + idx);
            const card = document.getElementById('card-' + idx);
            if (c && card) card.classList.toggle('selected', c.checked);
        }
        function updateToolbar() {
            const checked = document.querySelectorAll('input[name="merge_pairs[]"]:checked').length / 2; // ÷2 because 2 checkboxes per pair
            const toolbar = document.getElementById('merge-toolbar');
            document.getElementById('selected-count').textContent = Math.round(checked);
            toolbar.style.display = checked > 0 ? 'flex' : 'none';
        }
        function selectAll() {
            document.querySelectorAll('input[name="merge_pairs[]"]').forEach(cb => { cb.checked = true; });
            document.querySelectorAll('.dup-pair-card').forEach(c => c.classList.add('selected'));
            updateToolbar();
        }
        function selectNone() {
            document.querySelectorAll('input[name="merge_pairs[]"]').forEach(cb => { cb.checked = false; });
            document.querySelectorAll('.dup-pair-card').forEach(c => c.classList.remove('selected'));
            updateToolbar();
        }
        // Init state
        document.querySelectorAll('input[name="merge_pairs[]"]:checked').forEach(cb => {
            const idx = cb.id.replace('chk-','').replace('chk2-','');
            const card = document.getElementById('card-' + idx);
            if (card) card.classList.add('selected');
        });
        updateToolbar();
        </script>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- STEP 3: Summary                                               -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <?php if ($step === 3): ?>
        <div class="card-dark" style="text-align:center; padding: 40px 32px;">
            <div style="font-size:64px; margin-bottom:16px;">🎉</div>
            <h2 style="color: var(--color-green); margin:0 0 8px;">กระบวนการ ETL เสร็จสมบูรณ์!</h2>
            <p style="color: var(--text-secondary); margin:0 0 32px;">ข้อมูลถูกนำเข้า ตรวจสอบ และจัดการความซ้ำซ้อนเรียบร้อยแล้ว</p>

            <?php if ($etlResults || $mergeResults): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap: 14px; margin-bottom: 32px; text-align:left;">
                <?php if ($etlResults): ?>
                <div style="background: var(--bg-darker); border-radius: 16px; padding: 16px;">
                    <div style="font-size:12px; color: var(--text-muted); font-weight:700; margin-bottom:6px;">📥 ETL</div>
                    <div style="font-size:13px; line-height:2;">
                        <div>นำเข้าใหม่: <strong><?= $etlResults['inserted'] ?></strong></div>
                        <div>อัปเดต: <strong><?= $etlResults['updated'] ?></strong></div>
                        <div>คัดออก: <strong><?= $etlResults['excluded_dm'] ?></strong></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($mergeResults): ?>
                <div style="background: var(--bg-darker); border-radius: 16px; padding: 16px;">
                    <div style="font-size:12px; color: var(--text-muted); font-weight:700; margin-bottom:6px;">🔗 รวมข้อมูล</div>
                    <div style="font-size:13px; line-height:2;">
                        <div>รวมสำเร็จ: <strong style="color:var(--color-green);"><?= $mergeResults['merged'] ?> คู่</strong></div>
                        <div>ข้าม: <strong><?= $mergeResults['skipped'] ?></strong></div>
                        <div>อัปเดตชื่อ: <strong><?= $mergeResults['updated_names'] ?></strong></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($mergeResults['errors'])): ?>
            <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.3); border-radius:12px; padding:12px 16px; margin-bottom:20px; text-align:left;">
                <strong style="color:var(--color-red);">⚠️ ข้อผิดพลาดบางส่วน:</strong>
                <ul style="margin:8px 0 0; padding-left:20px; font-size:13px; color:var(--text-secondary);">
                    <?php foreach ($mergeResults['errors'] as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                <form method="POST" style="display:inline;">
                    <button type="submit" name="action_reset" class="btn-giant btn-giant-secondary" style="border-radius:var(--border-radius); min-width:200px;">
                        🔄 เริ่มกระบวนการใหม่อีกครั้ง
                    </button>
                </form>
                <a href="index.php" class="btn-giant btn-giant-primary" style="border-radius:var(--border-radius); min-width:200px; text-decoration:none;">
                    🏠 กลับหน้าหลัก Admin
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>