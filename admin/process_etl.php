<?php
// admin/process_etl.php
session_start();

// ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
if ($admin_hoscode !== null) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

// Validate 13-digit Thai Citizen ID using mod 11 checksum
function validateThaiCitizenID($id) {
    if (!preg_match('/^[0-9]{13}$/', $id)) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$id[$i] * (13 - $i);
    }

    $checkDigit = (11 - ($sum % 11)) % 10;
    return $checkDigit === (int)$id[12];
}

$message = '';
$error = '';
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Fetch unique CIDs from staging tables
        $cidsQuery = $pdo->query("
            SELECT DISTINCT cid FROM staging_hdc_dm
            UNION
            SELECT DISTINCT cid FROM staging_hdc_ht
        ");
        $allCids = $cidsQuery->fetchAll(PDO::FETCH_COLUMN);

        $inserted = 0;
        $updated = 0;
        $excluded_dm = 0;
        $skipped_invalid = 0;

        // Bounding box of Tal Sum, Ubon Ratchathani for generating mock GPS coordinates
        // Latitude: 15.3800 to 15.4800, Longitude: 104.9200 to 105.0800
        $latMin = 15.3800;
        $latMax = 15.4800;
        $lngMin = 104.9200;
        $lngMax = 105.0800;

        foreach ($allCids as $cid) {
            // Skip mock/simulated citizen ID data
            if (!validateThaiCitizenID($cid)) {
                $skipped_invalid++;
                continue;
            }

            // Get DM staging data if exists
            $stmtDm = $pdo->prepare("SELECT * FROM staging_hdc_dm WHERE cid = ? ORDER BY staging_id DESC LIMIT 1");
            $stmtDm->execute([$cid]);
            $dmData = $stmtDm->fetch();

            // Get HT staging data if exists
            $stmtHt = $pdo->prepare("SELECT * FROM staging_hdc_ht WHERE cid = ? ORDER BY staging_id DESC LIMIT 1");
            $stmtHt->execute([$cid]);
            $htData = $stmtHt->fetch();

            // Extract demographic details (prefer HT or DM)
            $source = $dmData ? $dmData : $htData;
            if (!$source) continue;
            
            $firstName = $source['name'] ?? 'ไม่ทราบชื่อ';
            $lastName = $source['lname'] ?? 'ไม่ทราบประวัติ';
            $sex = $source['sex'] ?? '1';
            $birth = $source['birth'] ?? '1970-01-01';
            $hid = $source['hid'] ?? '000000000000000';
            $addr = $source['addr'] ?? '';
            $checkVhid = $source['check_vhid'] ?? ($dmData['check_vhid'] ?? $htData['check_vhid'] ?? '');
            $pid = $source['pid'] ?? null;
            $hoscode = $source['hoscode'] ?? ($dmData['hoscode'] ?? $htData['hoscode'] ?? '00000');

            // Parse house no and Moo from address or check_vhid
            $houseNo = '';
            $moo = 1;
            if (preg_match('/^(\d+[\/\d]*)/', $addr, $matches)) {
                $houseNo = $matches[1];
            } else {
                $houseNo = $addr;
            }

            // Parse Moo from check_vhid (last 2 digits usually, CCAATTMM)
            if (strlen($checkVhid) === 8) {
                $moo = (int)substr($checkVhid, 6, 2);
                $subDistrictCode = substr($checkVhid, 0, 6);
            } else {
                $moo = 1;
                $subDistrictCode = '341801'; // Default to Tal Sum subdistrict
                $checkVhid = '34180101';
            }

            // Translate risk values to baseline health_status_origin and screening requirements
            $dmRisk = $dmData ? trim($dmData['risk']) : null;
            $htRisk = $htData ? trim($htData['risk']) : null;

            // Determine if they are diagnosed patients to exclude
            $needScreenDm = true;
            $needScreenHt = true;

            if ($dmRisk === '5' || ($dmData && (mb_strpos($dmData['result'] ?? '', 'ผู้ป่วย') !== false || mb_strpos($dmData['result'] ?? '', 'DM') !== false))) {
                $needScreenDm = false;
                $excluded_dm++;
            }
            if ($htRisk === '5') {
                $needScreenHt = false;
            }

            // Map baseline health_status_origin (0 = Normal, 1 = Risk, 2 = High Risk, 5 = Diagnosed/Exclude)
            if ($dmRisk === '2' || $htRisk === '2') {
                $healthStatusOrigin = 'HIGH_RISK';
            } elseif ($dmRisk === '1' && $htRisk === '1') {
                $healthStatusOrigin = 'BOTH';
            } elseif ($dmRisk === '1') {
                $healthStatusOrigin = 'DM_ONLY';
            } elseif ($htRisk === '1') {
                $healthStatusOrigin = 'HT_ONLY';
            } else {
                $healthStatusOrigin = 'NORMAL';
            }

            // Check if patient already exists in target_population by hoscode and pid (stable unique keys)
            $exists = false;
            if ($hoscode && $pid) {
                $checkStmt = $pdo->prepare("SELECT cid, first_name, last_name, house_no, moo, sub_district_code, vhid_code, latitude, longitude FROM target_population WHERE hoscode = ? AND pid = ?");
                $checkStmt->execute([$hoscode, $pid]);
                $exists = $checkStmt->fetch();
            }

            if ($exists) {
                // Patient exists (matched by hoscode and pid, preserve real names/CID)
                $realCid = $exists['cid'];
                $lat = $exists['latitude'] ?: ($source['latitude'] ?? null) ?: ($latMin + mt_rand() / mt_getrandmax() * ($latMax - $latMin));
                $lng = $exists['longitude'] ?: ($source['longitude'] ?? null) ?: ($lngMin + mt_rand() / mt_getrandmax() * ($lngMax - $lngMin));

                // Update, preserving existing demographics (avoid overwriting real names/CID with masked values)
                $updateStmt = $pdo->prepare("
                    UPDATE target_population 
                    SET hid = ?, 
                        house_no = ?, moo = ?, sub_district_code = ?, vhid_code = ?,
                        latitude = ?, longitude = ?, health_status_origin = ?, 
                        need_screen_dm = ?, need_screen_ht = ?, updated_at = NOW()
                    WHERE cid = ?
                ");
                $updateStmt->execute([
                    $hid, 
                    $exists['house_no'] ?: $houseNo, 
                    $exists['moo'] ?: $moo, 
                    $exists['sub_district_code'] ?: $subDistrictCode, 
                    $exists['vhid_code'] ?: $checkVhid,
                    $lat, $lng, $healthStatusOrigin,
                    $needScreenDm ? 1 : 0, $needScreenHt ? 1 : 0,
                    $realCid
                ]);
                $updated++;
            } else {
                // Insert as new record using staging details (CID might be masked but serves as fallback)
                $lat = ($source['latitude'] ?? null) ?: ($latMin + mt_rand() / mt_getrandmax() * ($latMax - $latMin));
                $lng = ($source['longitude'] ?? null) ?: ($lngMin + mt_rand() / mt_getrandmax() * ($lngMax - $lngMin));

                $insertStmt = $pdo->prepare("
                    INSERT INTO target_population 
                    (cid, hid, pid, first_name, last_name, sex, birth, house_no, moo, 
                     sub_district_code, vhid_code, hoscode, latitude, longitude, 
                     health_status_origin, need_screen_dm, need_screen_ht)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([
                    $cid, $hid, $pid, $firstName, $lastName, $sex, $birth, $houseNo, $moo, 
                    $subDistrictCode, $checkVhid, $hoscode, $lat, $lng, 
                    $healthStatusOrigin, $needScreenDm ? 1 : 0, $needScreenHt ? 1 : 0
                ]);
                $inserted++;
            }
        }

        $pdo->commit();
        $message = "ประมวลผลข้อมูลสำเร็จ!";
        $results = [
            'total' => count($allCids),
            'inserted' => $inserted,
            'updated' => $updated,
            'excluded_dm' => $excluded_dm,
            'skipped_invalid' => $skipped_invalid
        ];
    } catch (\Exception $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาดในการประมวลผล: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ETL Engine & Exclusion Rules - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">
    <div class="admin-navbar">
        <a href="index.php" class="admin-logo">NCDs Prevention Portal - Tansum</a>
        <div class="admin-nav-links">
            <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" data-tooltip="แดชบอร์ด">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            </a>
            <?php if (!$admin_hoscode): ?>
                <a href="import_hdc.php" class="<?= basename($_SERVER['PHP_SELF']) == 'import_hdc.php' ? 'active' : '' ?>" data-tooltip="นำเข้าข้อมูล HDC">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                </a>
                <a href="process_etl.php" class="<?= basename($_SERVER['PHP_SELF']) == 'process_etl.php' ? 'active' : '' ?>" data-tooltip="ประมวลผล ETL">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                </a>
            <?php endif; ?>
            <a href="hdc_list.php" class="<?= basename($_SERVER['PHP_SELF']) == 'hdc_list.php' ? 'active' : '' ?>" data-tooltip="คัดกรองความเสี่ยง HDC">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </a>
            <a href="dpac_manager.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dpac_manager.php' ? 'active' : '' ?>" data-tooltip="จัดการโครงการ DPAC">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </a>
            <a href="assignment.php" class="<?= basename($_SERVER['PHP_SELF']) == 'assignment.php' ? 'active' : '' ?>" data-tooltip="มอบหมายงาน อสม.">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
            </a>
            <a href="print_qr.php" class="<?= basename($_SERVER['PHP_SELF']) == 'print_qr.php' ? 'active' : '' ?>" data-tooltip="พิมพ์ QR Code บ้าน">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
            </a>
            <a href="vhv_approval.php" class="<?= basename($_SERVER['PHP_SELF']) == 'vhv_approval.php' ? 'active' : '' ?>" data-tooltip="จัดการผู้ใช้ อสม.">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </a>
            <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" data-tooltip="ข้อมูลส่วนตัว / เปลี่ยนรหัสผ่าน">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </a>
            <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" data-tooltip="รายงานและการพิมพ์">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </a>
            <a href="../logout.php" data-tooltip="ออกจากระบบ" style="color: var(--color-red) !important;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </a>
        </div>
    </div>

    <div style="max-width: 900px; margin: 40px auto; padding: 0 20px;">
        <div class="card-dark">
            <h2 style="color: var(--color-accent); border-bottom: 2px solid var(--border-color); padding-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18"></path></svg>
                ETL Consolidation & Exclusion Engine
            </h2>
            <p style="color: var(--text-secondary);">
                ระบบจะดึงข้อมูลการคัดกรองจากตารางนำเข้าชั่วคราว (<code style="color: var(--color-primary);">staging_hdc_dm</code> และ <code style="color: var(--color-primary);">staging_hdc_ht</code>) มารวมกันแบบ Unique CID และทำการวิเคราะห์คัดแยกอัตโนมัติ (Exclusion Rule):
            </p>
            <ul style="color: var(--text-secondary); line-height: 1.8;">
                <li>ตรวจสอบประวัติป่วยโรคเรื้อรัง (โรคเบาหวาน DM/โรคความดันโลหิตสูง HT) จากข้อมูลนำเข้า</li>
                <li><strong>Exclusion Rule:</strong> หากพบผู้มีประวัติโรคเรื้อรังแล้ว ระบบจะกำหนดให้ไม่ต้องตรวจซ้ำซ้อน (<code style="color: var(--color-accent);">need_screen_dm = FALSE</code>) แต่จะยังเปิดให้ตรวจโรคอื่นที่ยังไม่เป็น (<code style="color: var(--color-green);">need_screen_ht = TRUE</code>)</li>
                <li>ตรวจสอบและแปลงข้อมูลบ้าน (HID) และรหัสหมู่บ้าน (Moo) จากรหัส 8 หลัก</li>
                <li>จำลองพิกัดแผนที่ (Latitude/Longitude) ในขอบเขตอำเภอตาลสุมสำหรับบ้านที่ยังไม่มีพิกัด เพื่อรองรับการแสดงผล Heatmap คลัสเตอร์กลุ่มเสี่ยง</li>
            </ul>

            <?php if (!empty($message)): ?>
                <div style="background-color: rgba(16, 185, 129, 0.15); border: 2px solid var(--color-green); color: var(--color-green); padding: 16px; border-radius: var(--border-radius); margin: 20px 0;">
                    <strong>สำเร็จ!</strong> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin: 20px 0;">
                    <strong>ข้อผิดพลาด!</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($results): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0;">
                    <div style="box-shadow: var(--neumorph-inset); background-color: var(--bg-card); padding: 16px; border-radius: var(--border-radius); text-align: center;">
                        <span style="color: var(--text-secondary); font-size: 14px;">จำนวนรายชื่อทั้งหมด</span>
                        <div class="stat-val"><?= $results['total'] ?> ราย</div>
                    </div>
                    <div style="box-shadow: var(--neumorph-inset); background-color: var(--bg-card); padding: 16px; border-radius: var(--border-radius); text-align: center;">
                        <span style="color: var(--text-secondary); font-size: 14px;">นำเข้าข้อมูลใหม่ / อัปเดต</span>
                        <div class="stat-val" style="color: var(--color-green);"><?= $results['inserted'] + $results['updated'] ?> ราย</div>
                    </div>
                    <div style="box-shadow: var(--neumorph-inset); background-color: var(--bg-card); padding: 16px; border-radius: var(--border-radius); text-align: center;">
                        <span style="color: var(--text-secondary); font-size: 14px;">คัดออกเนื่องจากเป็นผู้ป่วย</span>
                        <div class="stat-val" style="color: var(--color-primary);"><?= $results['excluded_dm'] ?> ราย</div>
                    </div>
                    <?php if (isset($results['skipped_invalid']) && $results['skipped_invalid'] > 0): ?>
                        <div style="box-shadow: var(--neumorph-inset); background-color: var(--bg-card); padding: 16px; border-radius: var(--border-radius); text-align: center;">
                            <span style="color: var(--text-secondary); font-size: 14px;">ข้ามข้อมูลจำลอง (เลขบัตรไม่ถูกต้อง)</span>
                            <div class="stat-val" style="color: var(--color-red);"><?= $results['skipped_invalid'] ?> ราย</div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" style="margin-top: 30px;">
                <button type="submit" class="btn-giant btn-giant-primary" style="border-radius: var(--border-radius);">
                    เริ่มประมวลผลข้อมูลและกรองรายชื่อคัดกรอง (Run ETL Engine)
                </button>
            </form>
        </div>
    </div>
</body>
</html>