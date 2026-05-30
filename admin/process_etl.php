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
    $id = preg_replace('/[^0-9*]/', '', $id);
    if (strlen($id) !== 13) {
        return false;
    }
    return true;
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
            $hoscode = trim((string)$hoscode);
            if (is_numeric($hoscode) && strlen($hoscode) < 5) {
                $hoscode = str_pad($hoscode, 5, '0', STR_PAD_LEFT);
            }

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

            // Map baseline health_status_origin (0 = Normal, 1 = Risk, 2 = High Risk, 3 = Suspect, 5 = Diagnosed/Exclude)
            if ($dmRisk === '2' || $htRisk === '2') {
                $healthStatusOrigin = 'HIGH_RISK';
            } elseif ($dmRisk === '1' && $htRisk === '1') {
                $healthStatusOrigin = 'BOTH';
            } elseif ($dmRisk === '1') {
                $healthStatusOrigin = 'DM_ONLY';
            } elseif ($htRisk === '1') {
                $healthStatusOrigin = 'HT_ONLY';
            } elseif ($dmRisk === '3' || $htRisk === '3') {
                $healthStatusOrigin = 'SUSPECT';
            } else {
                $healthStatusOrigin = 'NORMAL';
            }

            // For SUSPECT (Risk 3), set screening flags to false initially (we manage them separately)
            if ($healthStatusOrigin === 'SUSPECT') {
                $needScreenDm = false;
                $needScreenHt = false;
            }

            // Check if patient already exists in target_population by hoscode and pid (stable unique keys)
            $exists = false;
            if ($hoscode && $pid) {
                $checkStmt = $pdo->prepare("SELECT cid, first_name, last_name, house_no, moo, sub_district_code, vhid_code, latitude, longitude, need_screen_dm, need_screen_ht FROM target_population WHERE LPAD(hoscode, 5, '0') = LPAD(?, 5, '0') AND TRIM(LEADING '0' FROM pid) = TRIM(LEADING '0' FROM ?)");
                $checkStmt->execute([$hoscode, $pid]);
                $exists = $checkStmt->fetch();
            }

            if ($exists) {
                // Patient exists (matched by hoscode and pid, preserve real names/CID)
                $realCid = $exists['cid'];
                $lat = $exists['latitude'] ?: ($source['latitude'] ?? null) ?: ($latMin + mt_rand() / mt_getrandmax() * ($latMax - $latMin));
                $lng = $exists['longitude'] ?: ($source['longitude'] ?? null) ?: ($lngMin + mt_rand() / mt_getrandmax() * ($lngMax - $lngMin));

                // If suspect was already activated as target by admin, preserve that status
                if ($exists['need_screen_dm'] == 1) {
                    $needScreenDm = true;
                }
                if ($exists['need_screen_ht'] == 1) {
                    $needScreenHt = true;
                }

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
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
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

            <form action="" method="POST" id="etl-form" style="margin-top: 30px;">
                <button type="submit" class="btn-giant btn-giant-primary" style="border-radius: var(--border-radius);">
                    เริ่มประมวลผลข้อมูลและกรองรายชื่อคัดกรอง (Run ETL Engine)
                </button>
            </form>
        </div>
    </div>

    <!-- Progress Overlay Modal -->
    <div id="progress-overlay" class="modal-overlay" style="display: none; flex-direction: column; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(13, 44, 84, 0.4); backdrop-filter: blur(4px); z-index: 9999; color: white;">
        <div class="modal-content" style="background: rgba(13, 44, 84, 0.9); backdrop-filter: blur(10px); color: white; padding: 40px; border-radius: 24px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); width: 90%; max-width: 450px; text-align: center;">
            <div class="spinner" style="margin: 0 auto 24px auto; width: 50px; height: 50px; border: 4px solid rgba(255,255,255,0.1); border-top-color: var(--color-accent); border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <h3 id="progress-status" style="margin: 0 0 10px 0; font-size: 18px; font-weight: bold; color: white;">กำลังประมวลผลระบบ ETL...</h3>
            <p id="progress-substatus" style="margin: 0 0 24px 0; font-size: 13px; color: rgba(255,255,255,0.6);">ระบบกำลังรวบรวมและวิเคราะห์ข้อมูลคัดแยกกลุ่มเป้าหมาย</p>
            <div style="background: rgba(255,255,255,0.1); border-radius: 10px; height: 10px; overflow: hidden; position: relative;">
                <div id="progress-bar" style="background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-accent) 100%); width: 0%; height: 100%; transition: width 0.2s ease-out; border-radius: 10px;"></div>
            </div>
            <div id="progress-percent" style="margin-top: 10px; font-size: 14px; font-weight: bold; color: var(--color-accent);">0%</div>
        </div>
    </div>

    <style>
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

    <script>
        function runProgressAnimation(duration, statusMessages) {
            const overlay = document.getElementById('progress-overlay');
            const progressBar = document.getElementById('progress-bar');
            const progressPercent = document.getElementById('progress-percent');
            const progressStatus = document.getElementById('progress-status');
            
            overlay.style.display = 'flex';
            
            let start = null;
            function step(timestamp) {
                if (!start) start = timestamp;
                const progress = Math.min((timestamp - start) / duration, 1);
                
                // Slow down progress as it approaches 95%
                const displayProgress = Math.floor(progress * 94); 
                
                progressBar.style.width = displayProgress + '%';
                progressPercent.textContent = displayProgress + '%';
                
                // Update messages based on progress
                if (statusMessages && statusMessages.length > 0) {
                    const msgIndex = Math.min(Math.floor(progress * statusMessages.length), statusMessages.length - 1);
                    progressStatus.textContent = statusMessages[msgIndex];
                }
                
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            }
            window.requestAnimationFrame(step);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const etlForm = document.getElementById('etl-form');
            if (etlForm) {
                etlForm.addEventListener('submit', () => {
                    runProgressAnimation(6000, [
                        "กำลังเริ่มต้นดึงข้อมูลจากตารางนำเข้าชั่วคราว...",
                        "กำลังประมวลผลหาค่าบัตรประชาชนแบบไม่ซ้ำ (Unique CID)...",
                        "กำลังเปรียบเทียบข้อมูลกลุ่มเสี่ยง DM และ HT...",
                        "กำลังคัดแยกผู้ป่วยที่ได้รับการวินิจฉัยแล้ว (Exclusion Rules)...",
                        "กำลังจำลองพิกัดบ้านสำหรับแสดงผลแผนที่คลัสเตอร์...",
                        "กำลังบันทึกรายชื่อใหม่และปรับปรุงกลุ่มเป้าหมายเดิม...",
                        "เสร็จสิ้นการประมวลผล กำลังเปิดเผยตารางสถิติสรุปผล..."
                    ]);
                });
            }
        });
    </script>
</body>
</html>