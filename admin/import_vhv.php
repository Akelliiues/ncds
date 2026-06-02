<?php
// admin/import_vhv.php
require_once __DIR__ . '/../config/session.php';

// ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

require_once __DIR__ . '/../config/db.php';

$message = '';
$error = '';

// Helper to match column headers case-insensitively
function getColumnIndex($headers, $possibleNames) {
    foreach ($headers as $idx => $header) {
        $headerClean = strtolower(trim(preg_replace('/[\x{FEFF}\x{FFFE}]/u', '', $header)));
        foreach ($possibleNames as $name) {
            if ($headerClean === strtolower($name) || strpos($headerClean, strtolower($name)) !== false) {
                return $idx;
            }
        }
    }
    return -1;
}

// Tambon Mapping for Tal Sum District
$tambonMap = [
    'ตาลสุม' => '341801',
    'สำโรง' => '341802',
    'จิกเทิง' => '341803',
    'หนองกุง' => '341804',
    'นาคาย' => '341805',
    'คำหว้า' => '341806',
    'ต.ตาลสุม' => '341801',
    'ต.สำโรง' => '341802',
    'ต.จิกเทิง' => '341803',
    'ต.หนองกุง' => '341804',
    'ต.นาคาย' => '341805',
    'ต.คำหว้า' => '341806'
];

$step = 1;
$detectedAmphoes = [];
$csvHeaders = [];
$tempFilePath = '';
$fileHash = '';

// Default mappings
$col_prefix = -1;
$col_cid = -1;
$col_fname = -1;
$col_lname = -1;
$col_phone = -1;
$col_moo = -1;
$col_tambon = -1;
$col_amphoe = -1;
$col_hoscode = -1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'upload';

    // Step 1 -> 2: Handle File Upload & Read Headers
    if ($action === 'upload' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        
        // Check extension
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext !== 'csv' && $ext !== 'txt') {
            $error = "กรุณาอัปโหลดไฟล์นามสกุล .csv เท่านั้น";
        } else {
            // Move to temp file for Step 2
            $fileHash = md5_file($tmpName) . '_' . time() . '.csv';
            $tempFilePath = sys_get_temp_dir() . '/' . $fileHash;
            
            if (move_uploaded_file($tmpName, $tempFilePath)) {
                // Convert file encoding to UTF-8 if it's not
                $content = file_get_contents($tempFilePath);
                if (!mb_check_encoding($content, 'UTF-8')) {
                    $content = iconv('TIS-620', 'UTF-8//IGNORE', $content);
                    file_put_contents($tempFilePath, $content);
                }

                if (($handle = fopen($tempFilePath, "r")) !== FALSE) {
                    // Read first row as header
                    $csvHeaders = fgetcsv($handle, 4096, ",");
                    if ($csvHeaders) {
                        // Auto-detect columns for defaults
                        $col_prefix = getColumnIndex($csvHeaders, ['คำนำหน้า', 'คำนำหน้าชื่อ', 'prefix', 'title', 'name_title']);
                        $col_cid = getColumnIndex($csvHeaders, ['เลขบัตร', 'บัตรประชาชน', 'cid', 'id_card', 'เลขประจำตัว']);
                        $col_fname = getColumnIndex($csvHeaders, ['ชื่อ', 'first name', 'fname']);
                        $col_lname = getColumnIndex($csvHeaders, ['สกุล', 'นามสกุล', 'last name', 'lname']);
                        $col_phone = getColumnIndex($csvHeaders, ['เบอร์โทร', 'โทรศัพท์', 'phone', 'tel']);
                        $col_moo = getColumnIndex($csvHeaders, ['หมู่ที่', 'หมู่', 'moo']);
                        $col_tambon = getColumnIndex($csvHeaders, ['ตำบล', 'tambon', 'subdistrict']);
                        $col_amphoe = getColumnIndex($csvHeaders, ['อำเภอ', 'amphoe', 'district']);
                        $col_hoscode = getColumnIndex($csvHeaders, ['รหัสสถานบริการ', 'หน่วยบริการ', 'hcode', 'hoscode']);

                        $step = 2; // Move to mapping step
                    } else {
                        $error = "ไม่สามารถอ่าน Header ของไฟล์ CSV ได้";
                        unlink($tempFilePath);
                    }
                    fclose($handle);
                }
            } else {
                $error = "ไม่สามารถบันทึกไฟล์ชั่วคราวได้";
            }
        }
    }
    
    // Step 2 -> 3: Handle Column Mapping
    elseif ($action === 'map_columns') {
        $fileHash = $_POST['file_hash'] ?? '';
        $tempFilePath = sys_get_temp_dir() . '/' . $fileHash;
        
        $col_prefix = (int)($_POST['col_prefix'] ?? -1);
        $col_cid = (int)($_POST['col_cid'] ?? -1);
        $col_fname = (int)($_POST['col_fname'] ?? -1);
        $col_lname = (int)($_POST['col_lname'] ?? -1);
        $col_phone = (int)($_POST['col_phone'] ?? -1);
        $col_moo = (int)($_POST['col_moo'] ?? -1);
        $col_tambon = (int)($_POST['col_tambon'] ?? -1);
        $col_amphoe = (int)($_POST['col_amphoe'] ?? -1);
        $col_hoscode = (int)($_POST['col_hoscode'] ?? -1);

        if (!file_exists($tempFilePath)) {
            $error = "ไม่พบไฟล์ CSV (อาจหมดอายุ) กรุณาอัปโหลดใหม่";
            $step = 1;
        } elseif ($col_cid == -1 || $col_fname == -1 || $col_amphoe == -1 || $col_moo == -1 || $col_tambon == -1) {
            $error = "กรุณาระบุคอลัมน์บังคับให้ครบถ้วน (เลขบัตรปชช., ชื่อ, หมู่ที่, ตำบล, อำเภอ)";
            $step = 2;
            // re-read headers for form
            if (($handle = fopen($tempFilePath, "r")) !== FALSE) {
                $csvHeaders = fgetcsv($handle, 4096, ",");
                fclose($handle);
            }
        } else {
            // Scan file for unique Amphoes using the mapped column and count total rows
            if (($handle = fopen($tempFilePath, "r")) !== FALSE) {
                $csvHeaders = fgetcsv($handle, 4096, ","); // skip header
                $totalRows = 0;
                while (($data = fgetcsv($handle, 4096, ",")) !== FALSE) {
                    $totalRows++;
                    if (isset($data[$col_amphoe])) {
                        $amp = trim($data[$col_amphoe]);
                        if (!empty($amp)) {
                            $detectedAmphoes[$amp] = true;
                        }
                    }
                }
                fclose($handle);
                $detectedAmphoes = array_keys($detectedAmphoes);
                sort($detectedAmphoes);
                $step = 3; // Move to confirmation step
            }
        }
    }

    // Handle AJAX Chunked Import
    elseif ($action === 'import_ajax_chunk') {
        header('Content-Type: application/json');
        
        $fileHash = $_POST['file_hash'] ?? '';
        $tempFilePath = sys_get_temp_dir() . '/' . $fileHash;
        $targetAmphoe = $_POST['target_amphoe'] ?? '';
        
        $col_prefix = (int)($_POST['col_prefix'] ?? -1);
        $col_cid = (int)($_POST['col_cid'] ?? -1);
        $col_fname = (int)($_POST['col_fname'] ?? -1);
        $col_lname = (int)($_POST['col_lname'] ?? -1);
        $col_phone = (int)($_POST['col_phone'] ?? -1);
        $col_moo = (int)($_POST['col_moo'] ?? -1);
        $col_tambon = (int)($_POST['col_tambon'] ?? -1);
        $col_amphoe = (int)($_POST['col_amphoe'] ?? -1);
        $col_hoscode = (int)($_POST['col_hoscode'] ?? -1);

        $chunkOffset = (int)($_POST['chunk_offset'] ?? 0);
        $chunkSize = (int)($_POST['chunk_size'] ?? 100);

        if (!file_exists($tempFilePath)) {
            echo json_encode(['error' => 'ไม่พบไฟล์ CSV (อาจหมดอายุ) กรุณาอัปโหลดใหม่']);
            exit;
        }

        if (($handle = fopen($tempFilePath, "r")) !== FALSE) {
            // Skip header
            fgetcsv($handle, 4096, ",");
            
            // Skip rows up to chunkOffset
            for ($i = 0; $i < $chunkOffset; $i++) {
                if (fgetcsv($handle, 4096, ",") === FALSE) {
                    break; // Reached EOF early
                }
            }

            $importedCount = 0;
            $processedCount = 0;
            $skippedCount = 0;
            $eof = false;

            $pdo->beginTransaction();
            try {
                $insertStmt = $pdo->prepare("
                    INSERT INTO vhv_users (vhv_id, vhv_name, vhv_moo, vhid_code, hoscode, password_hash, is_leader, approved)
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0)
                    ON DUPLICATE KEY UPDATE
                    vhv_name = VALUES(vhv_name), vhv_moo = VALUES(vhv_moo), vhid_code = VALUES(vhid_code), hoscode = VALUES(hoscode)
                ");

                while ($processedCount < $chunkSize) {
                    $data = fgetcsv($handle, 4096, ",");
                    if ($data === FALSE) {
                        $eof = true;
                        break;
                    }
                    $processedCount++;

                    // Check Amphoe
                    if (isset($data[$col_amphoe]) && trim($data[$col_amphoe]) !== $targetAmphoe) {
                        $skippedCount++;
                        continue;
                    }

                    $cid = isset($data[$col_cid]) ? trim($data[$col_cid]) : '';
                    $prefix = ($col_prefix !== -1 && isset($data[$col_prefix])) ? trim($data[$col_prefix]) : '';
                    $fname = isset($data[$col_fname]) ? trim($data[$col_fname]) : '';
                    $lname = ($col_lname !== -1 && isset($data[$col_lname])) ? trim($data[$col_lname]) : '';
                    $phone = ($col_phone !== -1 && isset($data[$col_phone])) ? trim($data[$col_phone]) : '';
                    $mooStr = isset($data[$col_moo]) ? trim($data[$col_moo]) : '';
                    $tambonStr = isset($data[$col_tambon]) ? trim($data[$col_tambon]) : '';
                    $hoscode = ($col_hoscode !== -1 && isset($data[$col_hoscode])) ? trim($data[$col_hoscode]) : '';

                    if (empty($cid) || empty($fname)) {
                        $skippedCount++;
                        continue;
                    }

                    $phone_clean = ($col_phone !== -1 && !empty($phone)) ? preg_replace('/[^0-9]/', '', $phone) : '';
                    $vhv_id = !empty($phone_clean) ? $phone_clean : preg_replace('/[^0-9]/', '', $cid);
                    if (empty($vhv_id)) {
                        $skippedCount++;
                        continue;
                    }

                    $vhv_name = trim(($prefix ? $prefix . ' ' : '') . $fname . ' ' . $lname);
                    $moo = 0;
                    if (preg_match('/[0-9]+/', $mooStr, $matches)) {
                        $moo = (int)$matches[0];
                    }

                    $tambonClean = preg_replace('/^ต\./', '', $tambonStr);
                    $tambonClean = trim($tambonClean);
                    $tambonCode = $tambonMap[$tambonClean] ?? '341801'; 
                    $vhidCode = $tambonCode . sprintf("%02d", $moo);
                    $passwordHash = password_hash('123456', PASSWORD_DEFAULT);

                    $insertStmt->execute([
                        $vhv_id,
                        $vhv_name,
                        $moo,
                        $vhidCode,
                        $hoscode,
                        $passwordHash
                    ]);
                    $importedCount++;
                }

                $pdo->commit();
                
                if ($eof) {
                    @unlink($tempFilePath); // Cleanup
                }

                echo json_encode([
                    'success' => true,
                    'imported' => $importedCount,
                    'skipped' => $skippedCount,
                    'processed' => $processedCount,
                    'eof' => $eof
                ]);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
            fclose($handle);
        } else {
            echo json_encode(['error' => 'ไม่สามารถเปิดไฟล์ชั่วคราวได้']);
            exit;
        }
    }

    // Step 3 -> 4: Handle Import Confirmation (Fallback, although we'll use AJAX)
    elseif ($action === 'import_confirm') {
        $fileHash = $_POST['file_hash'] ?? '';
        $tempFilePath = sys_get_temp_dir() . '/' . $fileHash;
        $targetAmphoe = $_POST['target_amphoe'] ?? '';
        $passwordMode = $_POST['password_mode'] ?? 'phone'; 
        $fixedPassword = $_POST['fixed_password'] ?? '123456';
        
        $col_prefix = (int)($_POST['col_prefix'] ?? -1);
        $col_cid = (int)($_POST['col_cid'] ?? -1);
        $col_fname = (int)($_POST['col_fname'] ?? -1);
        $col_lname = (int)($_POST['col_lname'] ?? -1);
        $col_phone = (int)($_POST['col_phone'] ?? -1);
        $col_moo = (int)($_POST['col_moo'] ?? -1);
        $col_tambon = (int)($_POST['col_tambon'] ?? -1);
        $col_amphoe = (int)($_POST['col_amphoe'] ?? -1);
        $col_hoscode = (int)($_POST['col_hoscode'] ?? -1);
        
        if (!file_exists($tempFilePath)) {
            $error = "ไม่พบไฟล์ CSV (อาจหมดอายุ) กรุณาอัปโหลดใหม่";
            $step = 1;
        } elseif (empty($targetAmphoe)) {
            $error = "กรุณาเลือกอำเภอที่ต้องการนำเข้า";
            $step = 3;
            // re-read Amphoes to show form again
            if (($handle = fopen($tempFilePath, "r")) !== FALSE) {
                fgetcsv($handle, 4096, ",");
                while (($data = fgetcsv($handle, 4096, ",")) !== FALSE) {
                    if (isset($data[$col_amphoe])) {
                        $amp = trim($data[$col_amphoe]);
                        if (!empty($amp)) $detectedAmphoes[$amp] = true;
                    }
                }
                fclose($handle);
                $detectedAmphoes = array_keys($detectedAmphoes);
                sort($detectedAmphoes);
            }
        } else {
            // Perform Import
            if (($handle = fopen($tempFilePath, "r")) !== FALSE) {
                fgetcsv($handle, 4096, ","); // skip header
                
                $pdo->beginTransaction();
                $importedCount = 0;
                
                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO vhv_users (vhv_id, vhv_name, vhv_moo, vhid_code, hoscode, password_hash, is_leader, approved)
                        VALUES (?, ?, ?, ?, ?, ?, 0, 0)
                        ON DUPLICATE KEY UPDATE
                        vhv_name = VALUES(vhv_name), vhv_moo = VALUES(vhv_moo), vhid_code = VALUES(vhid_code), hoscode = VALUES(hoscode)
                    ");
                    
                    while (($data = fgetcsv($handle, 4096, ",")) !== FALSE) {
                        // Check Amphoe
                        if (isset($data[$col_amphoe]) && trim($data[$col_amphoe]) !== $targetAmphoe) {
                            continue;
                        }
                        
                        $cid = isset($data[$col_cid]) ? trim($data[$col_cid]) : '';
                        $prefix = ($col_prefix !== -1 && isset($data[$col_prefix])) ? trim($data[$col_prefix]) : '';
                        $fname = isset($data[$col_fname]) ? trim($data[$col_fname]) : '';
                        $lname = ($col_lname !== -1 && isset($data[$col_lname])) ? trim($data[$col_lname]) : '';
                        $phone = ($col_phone !== -1 && isset($data[$col_phone])) ? trim($data[$col_phone]) : '';
                        $mooStr = isset($data[$col_moo]) ? trim($data[$col_moo]) : '';
                        $tambonStr = isset($data[$col_tambon]) ? trim($data[$col_tambon]) : '';
                        $hoscode = ($col_hoscode !== -1 && isset($data[$col_hoscode])) ? trim($data[$col_hoscode]) : '';
                        
                        if (empty($cid) || empty($fname)) {
                            continue;
                        }
                        
                        // Parse vhv_id: use phone (digits only, strip dashes) if available and not skipped, else fallback to CID
                        $phone_clean = preg_replace('/[^0-9]/', '', $phone); // strip dashes and spaces
                        $vhv_id = (!empty($phone_clean) && $col_phone !== -1) ? $phone_clean : preg_replace('/[^0-9]/', '', $cid);
                        if (empty($vhv_id)) continue;
                        
                        // Build full name: prefix + fname + lname
                        $vhv_name = trim(($prefix ? $prefix . ' ' : '') . $fname . ' ' . $lname);
                        
                        // Parse Moo
                        $moo = 0;
                        if (preg_match('/[0-9]+/', $mooStr, $matches)) {
                            $moo = (int)$matches[0];
                        }
                        
                        // Determine vhid_code
                        $tambonClean = preg_replace('/^ต\./', '', $tambonStr);
                        $tambonClean = trim($tambonClean);
                        $tambonCode = $tambonMap[$tambonClean] ?? '341801'; 
                        $vhid_code = $tambonCode . sprintf("%02d", $moo);
                        
                        // Determine Password
                        $plainPassword = $fixedPassword;
                        if ($passwordMode === 'phone' && !empty($phone_clean) && $col_phone !== -1) {
                            $plainPassword = $phone_clean; // already digits only
                        } elseif ($passwordMode === 'cid' && strlen($cid) >= 4) {
                            $plainPassword = substr(preg_replace('/[^0-9]/', '', $cid), -4);
                        }
                        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
                        
                        if (empty($hoscode) && $admin_hoscode !== null) {
                            $hoscode = $admin_hoscode;
                        } elseif (empty($hoscode)) {
                            $hoscode = '10957';
                        }
                        
                        $insertStmt->execute([
                            $vhv_id,
                            $vhv_name,
                            $moo,
                            $vhid_code,
                            $hoscode,
                            $passwordHash
                        ]);
                        
                        $importedCount++;
                    }
                    
                    $pdo->commit();
                    $message = "นำเข้าข้อมูล อสม. สำเร็จจำนวน $importedCount ราย (สถานะ: รอการอนุมัติ — อสม. ต้องลงทะเบียนแล้ว ระบบจะอนุมัติให้อัตโนมัติถ้าข้อมูลตรงกัน)";
                    $step = 1;
                    
                    @unlink($tempFilePath);
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
                    $step = 1;
                }
                fclose($handle);
            } else {
                $error = "ไม่สามารถเปิดไฟล์ชั่วคราวได้";
                $step = 1;
            }
        }
    } // <-- Added missing closing brace
    // Handle Delete All Pending VHVs
    elseif ($action === 'delete_all_pending') {
        try {
            // Only delete VHVs that have no related data (safe delete: no task_assignments)
            $del = $pdo->prepare("
                DELETE v FROM vhv_users v
                LEFT JOIN task_assignments ta ON v.vhv_id = ta.vhv_id
                WHERE v.approved = 0 AND ta.assignment_id IS NULL
            ");
            $del->execute();
            $deleted = $del->rowCount();
            $message = "ลบข้อมูล อสม. ที่ยังไม่ได้รับการอนุมัติ สำเร็จจำนวน $deleted ราย (สามารถนำเข้าข้อมูลใหม่ได้เลยครับ)";
        } catch (Exception $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นำเข้าข้อมูล อสม. (ThaiPHC) - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">
    <?php include 'navbar.php'; ?>
    <div class="admin-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h2 style="color: var(--color-accent); margin: 0; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 12px;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                นำเข้า อสม. จากระบบ ThaiPHC
            </h2>
            <a href="vhv_approval.php" class="btn-giant btn-giant-secondary" style="margin: 0; display: inline-flex; width: auto; padding: 10px 20px;">
                กลับไปหน้าจัดการผู้ใช้
            </a>
        </div>

        <?php if ($message): ?>
            <div style="background-color: rgba(16, 185, 129, 0.1); color: var(--color-green); padding: 16px; border-radius: var(--border-radius); border-left: 4px solid var(--color-green); margin-bottom: 24px; font-weight: bold;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background-color: rgba(239, 68, 68, 0.1); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); border-left: 4px solid var(--color-red); margin-bottom: 24px; font-weight: bold;">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <!-- Step 1: Upload -->
        <div class="card-dark" style="margin-bottom: 30px;">
            <h3 style="color: var(--color-accent); margin-top: 0; margin-bottom: 20px; font-size: 18px;">
                ขั้นตอนที่ 1: อัปโหลดไฟล์ CSV (ThaiPHC)
            </h3>
            
            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="action" value="upload">
                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label">เลือกไฟล์รายชื่อ (CSV Format)</label>
                    <div style="border: 2px dashed rgba(13, 44, 84, 0.2); border-radius: var(--border-radius); padding: 40px 20px; text-align: center; background: rgba(13, 44, 84, 0.02);">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-secondary); margin-bottom: 12px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                        <p style="color: var(--text-secondary); margin: 0 0 16px 0;">ลากไฟล์ลงที่นี่ หรือคลิกเพื่อเลือกไฟล์ (รองรับเฉพาะนามสกุล .csv)</p>
                        <input type="file" name="csv_file" accept=".csv,.txt" required style="display: block; margin: 0 auto; color: var(--text-primary);">
                    </div>
                </div>
                
                <button type="submit" class="btn-giant btn-giant-primary" style="margin: 0; width: 100%;">ดำเนินการต่อ (จับคู่คอลัมน์)</button>
            </form>
        </div>

        <!-- Danger Zone: Delete all pending -->
        <?php
        $pendingCount = 0;
        try {
            $cntStmt = $pdo->query("SELECT COUNT(*) FROM vhv_users WHERE approved = 0");
            $pendingCount = (int)$cntStmt->fetchColumn();
        } catch (Exception $e) {}
        ?>
        <?php if ($pendingCount > 0): ?>
        <div style="border: 2px solid rgba(239, 68, 68, 0.4); border-radius: var(--border-radius); padding: 20px; background: rgba(239, 68, 68, 0.04); margin-bottom: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div>
                    <p style="color: var(--color-red); font-weight: bold; margin: 0 0 4px;">🗑️ ล้างข้อมูล อสม. ที่รอการอนุมัติ (<?= $pendingCount ?> ราย)</p>
                    <p style="color: var(--text-muted); font-size: 13px; margin: 0;">ลบเฉพาะรายชื่อที่ยังไม่ได้รับการอนุมัติและไม่มีประวัติงานในระบบ เพื่อให้นำเข้าข้อมูลใหม่ได้</p>
                </div>
                <form method="POST" onsubmit="return confirm('ยืนยันการลบข้อมูล อสม. ที่รอการอนุมัติทั้งหมด <?= $pendingCount ?> ราย? ข้อมูลที่มีงานมอบหมายแล้วจะไม่ถูกลบ')">
                    <input type="hidden" name="action" value="delete_all_pending">
                    <button type="submit" style="background: var(--color-red); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 14px; white-space: nowrap;">
                        ลบทั้งหมดและนำเข้าใหม่
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($step === 2): ?>
        <!-- Step 2: Column Mapping -->
        <div class="card-dark" style="margin-bottom: 30px;">
            <h3 style="color: var(--color-accent); margin-top: 0; margin-bottom: 20px; font-size: 18px;">
                ขั้นตอนที่ 2: จับคู่คอลัมน์ข้อมูล (Column Mapping)
            </h3>
            
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="map_columns">
                <input type="hidden" name="file_hash" value="<?= htmlspecialchars($fileHash) ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <?php
                    $fields = [
                        ['name' => 'col_prefix', 'label' => 'คำนำหน้าชื่อ', 'default' => $col_prefix, 'required' => false, 'note' => 'เช่น นาย, นาง, น.ส., อื่นๆ (ถ้ามีจะถูกเติมไว้หน้าชื่อ)'],
                        ['name' => 'col_cid', 'label' => 'เลขบัตรประชาชน (บังคับ)', 'default' => $col_cid, 'required' => true, 'note' => ''],
                        ['name' => 'col_fname', 'label' => 'ชื่อ (บังคับ)', 'default' => $col_fname, 'required' => true, 'note' => ''],
                        ['name' => 'col_lname', 'label' => 'นามสกุล', 'default' => $col_lname, 'required' => false, 'note' => ''],
                        ['name' => 'col_phone', 'label' => 'เบอร์โทรศัพท์', 'default' => $col_phone, 'required' => false, 'note' => 'เลือก "-- ไม่นำเข้า --" เพื่อใช้เลขบัตรปชช. เป็น ID แทน'],
                        ['name' => 'col_moo', 'label' => 'หมู่ที่ (บังคับ)', 'default' => $col_moo, 'required' => true, 'note' => ''],
                        ['name' => 'col_tambon', 'label' => 'ตำบล (บังคับ)', 'default' => $col_tambon, 'required' => true, 'note' => ''],
                        ['name' => 'col_amphoe', 'label' => 'อำเภอ (บังคับ)', 'default' => $col_amphoe, 'required' => true, 'note' => ''],
                        ['name' => 'col_hoscode', 'label' => 'รหัสหน่วยบริการ/รพ.สต.', 'default' => $col_hoscode, 'required' => false, 'note' => ''],
                    ];

                    foreach ($fields as $f): ?>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label class="form-label"><?= $f['label'] ?></label>
                        <select name="<?= $f['name'] ?>" class="form-input-text" <?= $f['required'] ? 'required' : '' ?> style="box-shadow: var(--neumorph-inset);">
                            <option value="-1">-- ไม่นำเข้า / ไม่มีคอลัมน์นี้ --</option>
                            <?php foreach ($csvHeaders as $idx => $header): ?>
                                <option value="<?= $idx ?>" <?= ($idx === $f['default']) ? 'selected' : '' ?>><?= htmlspecialchars($header) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($f['note'])): ?>
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;"><?= $f['note'] ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="display: flex; gap: 16px; margin-top: 32px;">
                    <a href="import_vhv.php" class="btn-giant btn-giant-secondary" style="margin: 0; flex: 1;">ยกเลิกอัปโหลดใหม่</a>
                    <button type="submit" class="btn-giant btn-giant-primary" style="margin: 0; flex: 2;">
                        ดำเนินการต่อ (เลือกอำเภอ)
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($step === 3): ?>
        <!-- Step 3: Confirm & Amphoe Selection -->
        <div class="card-dark" style="margin-bottom: 30px;">
            <h3 style="color: var(--color-accent); margin-top: 0; margin-bottom: 20px; font-size: 18px;">
                ขั้นตอนที่ 3: ยืนยันและเลือกเงื่อนไขการนำเข้า
            </h3>
            
            <form method="POST" class="admin-form" id="importForm">
                <input type="hidden" name="action" value="import_ajax_chunk">
                <input type="hidden" name="file_hash" value="<?= htmlspecialchars($fileHash) ?>">
                <!-- Preserve mapping -->
                <input type="hidden" name="total_rows" id="total_rows" value="<?= isset($totalRows) ? $totalRows : 1000 ?>">
                <input type="hidden" name="col_prefix" value="<?= $col_prefix ?>">
                <input type="hidden" name="col_cid" value="<?= $col_cid ?>">
                <input type="hidden" name="col_fname" value="<?= $col_fname ?>">
                <input type="hidden" name="col_lname" value="<?= $col_lname ?>">
                <input type="hidden" name="col_phone" value="<?= $col_phone ?>">
                <input type="hidden" name="col_moo" value="<?= $col_moo ?>">
                <input type="hidden" name="col_tambon" value="<?= $col_tambon ?>">
                <input type="hidden" name="col_amphoe" value="<?= $col_amphoe ?>">
                <input type="hidden" name="col_hoscode" value="<?= $col_hoscode ?>">
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label">เลือกอำเภอเป้าหมาย (พบ <?= count($detectedAmphoes) ?> อำเภอในไฟล์)</label>
                    <select name="target_amphoe" class="form-input-text" required style="box-shadow: var(--neumorph-inset);">
                        <option value="">-- กรุณาเลือกอำเภอ --</option>
                        <?php foreach ($detectedAmphoes as $amp): ?>
                            <option value="<?= htmlspecialchars($amp) ?>" <?= (strpos($amp, 'ตาลสุม') !== false) ? 'selected' : '' ?>><?= htmlspecialchars($amp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label">ตั้งค่ารหัสผ่านเริ่มต้นสำหรับ อสม. (Default Password)</label>
                    <div style="display: flex; flex-direction: column; gap: 12px; padding: 16px; background: rgba(13, 44, 84, 0.02); border-radius: 12px; border: 1px solid rgba(13, 44, 84, 0.1);">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="password_mode" value="phone" checked>
                            <span style="color: var(--text-primary);">ใช้ <strong>เบอร์โทรศัพท์</strong> เป็นรหัสผ่าน (ถ้าไม่มีเบอร์ จะใช้ 123456)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="password_mode" value="cid">
                            <span style="color: var(--text-primary);">ใช้ <strong>เลขบัตรประชาชน 4 ตัวท้าย</strong> เป็นรหัสผ่าน</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="password_mode" value="fixed" onchange="document.getElementById('fixed_pw_input').style.display = this.checked ? 'block' : 'none'">
                            <span style="color: var(--text-primary);">กำหนดรหัสผ่านเดียวกันทั้งหมด</span>
                        </label>
                        <input type="text" name="fixed_password" id="fixed_pw_input" value="123456" class="form-input-text" style="display: none; box-shadow: var(--neumorph-inset); margin-left: 28px; width: calc(100% - 28px);" placeholder="ระบุรหัสผ่าน เช่น 123456">
                    </div>
                </div>

                <div style="display: flex; gap: 16px; margin-top: 32px;">
                    <a href="import_vhv.php" class="btn-giant btn-giant-secondary" style="margin: 0; flex: 1;">เริ่มต้นใหม่ทั้งหมด</a>
                    <button type="submit" class="btn-giant btn-giant-success" style="margin: 0; flex: 2; background: linear-gradient(135deg, var(--color-green), #059669); color: white;">
                        <span style="font-size: 20px;">🚀</span> ยืนยันการนำเข้าข้อมูล
                    </button>
                </div>
            </form>
        </div>

        <!-- Import Progress Modal -->
        <div id="importModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
            <div style="background: var(--bg-main); border-radius: 16px; padding: 32px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); text-align: center;">
                <div id="modalLoadingState">
                    <div style="font-size: 48px; margin-bottom: 16px;" class="spin">🔄</div>
                    <h3 style="color: var(--text-primary); margin: 0 0 16px 0; font-size: 24px;">กำลังนำเข้าข้อมูล...</h3>
                    
                    <div style="background: rgba(13, 44, 84, 0.1); border-radius: 12px; height: 24px; width: 100%; overflow: hidden; margin-bottom: 16px; position: relative;">
                        <div id="progressBar" style="background: linear-gradient(90deg, var(--color-accent), #3b82f6); width: 0%; height: 100%; transition: width 0.3s ease; border-radius: 12px;"></div>
                        <span id="progressText" style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); color: white; font-weight: bold; font-size: 12px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">0%</span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; font-size: 14px; color: var(--text-muted);">
                        <span id="importStats">กำลังประมวลผล: 0 / 0</span>
                        <span id="skipStats" style="color: var(--color-red);">ข้าม: 0</span>
                    </div>
                </div>

                <div id="modalSuccessState" style="display: none;">
                    <div style="font-size: 64px; margin-bottom: 16px;">✅</div>
                    <h3 style="color: var(--color-green); margin: 0 0 16px 0; font-size: 28px;">นำเข้าข้อมูลสำเร็จ!</h3>
                    <p style="color: var(--text-primary); font-size: 16px; margin-bottom: 8px;">นำเข้าทั้งหมด: <strong id="finalImported" style="color: var(--color-accent); font-size: 20px;">0</strong> ราย</p>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 24px;">ข้ามข้อมูลที่ไม่ตรงเงื่อนไข: <span id="finalSkipped">0</span> ราย</p>
                    <button onclick="window.location.href='vhv_approval.php'" class="btn-giant btn-giant-primary" style="width: 100%; margin: 0;">ไปที่หน้าจัดการผู้ใช้</button>
                </div>

                <div id="modalErrorState" style="display: none;">
                    <div style="font-size: 64px; margin-bottom: 16px;">❌</div>
                    <h3 style="color: var(--color-red); margin: 0 0 16px 0; font-size: 28px;">เกิดข้อผิดพลาด</h3>
                    <p id="errorText" style="color: var(--text-primary); margin-bottom: 24px;"></p>
                    <button onclick="window.location.href='import_vhv.php'" class="btn-giant btn-giant-secondary" style="width: 100%; margin: 0;">ลองใหม่อีกครั้ง</button>
                </div>
            </div>
        </div>

        <style>
            .spin {
                animation: spin 2s linear infinite;
                display: inline-block;
            }
            @keyframes spin { 100% { transform: rotate(360deg); } }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('importForm');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const modal = document.getElementById('importModal');
                    const progressBar = document.getElementById('progressBar');
                    const progressText = document.getElementById('progressText');
                    const importStats = document.getElementById('importStats');
                    const skipStats = document.getElementById('skipStats');
                    
                    const loadingState = document.getElementById('modalLoadingState');
                    const successState = document.getElementById('modalSuccessState');
                    const errorState = document.getElementById('modalErrorState');
                    
                    const formData = new FormData(form);
                    const totalRows = parseInt(document.getElementById('total_rows').value) || 1000; // rough estimate if unknown
                    const chunkSize = 200; // Process 200 rows at a time
                    
                    let currentOffset = 0;
                    let totalImported = 0;
                    let totalSkipped = 0;
                    let isDone = false;

                    modal.style.display = 'flex';
                    
                    while (!isDone) {
                        formData.set('chunk_offset', currentOffset);
                        formData.set('chunk_size', chunkSize);
                        
                        try {
                            const response = await fetch('import_vhv.php', {
                                method: 'POST',
                                body: formData
                            });
                            
                            const result = await response.json();
                            
                            if (result.error) {
                                loadingState.style.display = 'none';
                                errorState.style.display = 'block';
                                document.getElementById('errorText').textContent = result.error;
                                break;
                            }
                            
                            totalImported += result.imported;
                            totalSkipped += result.skipped;
                            currentOffset += result.processed;
                            
                            // Update UI
                            let percent = Math.min(100, Math.round((currentOffset / totalRows) * 100));
                            if (result.eof) percent = 100;
                            
                            progressBar.style.width = percent + '%';
                            progressText.textContent = percent + '%';
                            importStats.textContent = `กำลังประมวลผล: ${currentOffset} / ${totalRows}`;
                            skipStats.textContent = `ข้าม: ${totalSkipped}`;
                            
                            if (result.eof) {
                                isDone = true;
                                setTimeout(() => {
                                    loadingState.style.display = 'none';
                                    successState.style.display = 'block';
                                    document.getElementById('finalImported').textContent = totalImported;
                                    document.getElementById('finalSkipped').textContent = totalSkipped;
                                }, 500); // slight delay for smooth 100% animation
                            }
                            
                        } catch (err) {
                            console.error(err);
                            loadingState.style.display = 'none';
                            errorState.style.display = 'block';
                            document.getElementById('errorText').textContent = "เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์";
                            break;
                        }
                    }
                });
            }
        });
        </script>

        <?php endif; ?>
    </div>
</body>
</html>
