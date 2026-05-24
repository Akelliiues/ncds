<?php
// admin/import_hdc.php
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

$message = '';
$error = '';
$linesImported = 0;
$importType = 'dm'; // default
$selectedHoscode = '10957'; // default Tal Sum Hospital

$hc_names = [
    '10957' => 'โรงพยาบาลตาลสุม',
    '03751' => 'รพ.สต.ดอนพันชาด',
    '03752' => 'รพ.สต.บ้านสำโรง',
    '03753' => 'รพ.สต.บ้านจิกเทิง',
    '03754' => 'รพ.สต.บ้านหนองกุงใหญ่',
    '03755' => 'รพ.สต.นาคาย',
    '03756' => 'รพ.สต.คำหนามแท่ง',
    '03757' => 'รพ.สต.คำหว้า'
];

// Helper to match column headers case-insensitively
function getColumnIndex($headers, $possibleNames) {
    foreach ($headers as $idx => $header) {
        $headerClean = strtolower(trim(preg_replace('/[\x{FEFF}\x{FFFE}]/u', '', $header)));
        foreach ($possibleNames as $name) {
            if ($headerClean === strtolower($name)) {
                return $idx;
            }
        }
    }
    return -1;
}

// Helper to check if a row is a header row
function isHeaderRow($row) {
    if (empty($row)) return false;
    
    // Check if the 3rd element (index 2) is a valid CID (13-digit number)
    if (isset($row[2]) && preg_match('/^[0-9]{13}$/', $row[2])) {
        return false; // It's a data row
    }
    
    $commonHeaders = ['cid', 'pid', 'hn', 'hcode', 'hoscode', 'name', 'fname', 'discharge', 'typearea', 'sbp', 'dbp', 'risk'];
    foreach ($row as $val) {
        $valClean = strtolower(trim(preg_replace('/[\x{FEFF}\x{FFFE}]/u', '', $val)));
        if (in_array($valClean, $commonHeaders)) {
            return true; // It's a header row
        }
    }
    
    return false; // Default: assume it has no headers
}


// Columns definition for each import type
$columnMappings = [
    'person' => [
        'hoscode' => ['hoscode', 'hcode', 'pcucode', 'hos_code', 'h_code'],
        'pid' => ['pid', 'hn', 'p_id', 'person_id', 'hn_no'],
        'cid' => ['cid', 'card_id', 'citizen_id', 'personal_id'],
        'first_name' => ['first_name', 'firstname', 'name', 'fname'],
        'last_name' => ['last_name', 'lastname', 'lname'],
        'sex' => ['sex', 'gender'],
        'birth' => ['birth', 'birthdate', 'dob', 'dateofbirth'],
        'hid' => ['hid', 'h_id', 'home_id'],
        'house_no' => ['house_no', 'house_num', 'no', 'addr', 'address'],
        'vhid_code' => ['vhid_code', 'vhid', 'check_vhid', 'check_vhic', 'vhic', 'village_id', 'village_code'],
        'typearea' => ['typearea', 'type_area']
    ],
    'dm' => [
        'hoscode' => ['hoscode', 'hcode', 'pcucode', 'hos_code', 'h_code'],
        'hosname' => ['hosname', 'hos_name'],
        'pid' => ['pid', 'hn', 'p_id', 'person_id', 'hn_no'],
        'cid' => ['cid', 'card_id', 'citizen_id', 'personal_id'],
        'name' => ['name', 'fname', 'first_name', 'firstname'],
        'lname' => ['lname', 'last_name', 'lastname'],
        'sex' => ['sex', 'gender'],
        'birth' => ['birth', 'birthdate', 'dob', 'dateofbirth'],
        'hid' => ['hid', 'h_id', 'home_id'],
        'addr' => ['addr', 'address', 'house_no', 'house_num', 'no'],
        'check_vhid' => ['check_vhid', 'check_vhic', 'vhid', 'vhic', 'village_id', 'village_code'],
        'nation' => ['nation', 'nationality'],
        'discharge' => ['discharge', 'discharge_type'],
        'typearea' => ['typearea', 'type_area'],
        'date_screen' => ['date_screen', 'date_scree', 'screendate', 'screen_date', 'date_scre'],
        'bstest' => ['bstest', 'bs_test'],
        'bslevel' => ['bslevel', 'bs_level'],
        'hosp_screen' => ['hosp_screen', 'hosp_scree', 'hosp_scre'],
        'hosp_input' => ['hosp_input', 'hosp_inpu'],
        'providername' => ['providername', 'providerna', 'provider_name'],
        'risk' => ['risk', 'risk_group', 'risk_level'],
        'result' => ['result', 'screening_result']
    ],
    'ht' => [
        'hoscode' => ['hoscode', 'hcode', 'pcucode', 'hos_code', 'h_code'],
        'hosname' => ['hosname', 'hos_name'],
        'pid' => ['pid', 'hn', 'p_id', 'person_id', 'hn_no'],
        'cid' => ['cid', 'card_id', 'citizen_id', 'personal_id'],
        'name' => ['name', 'fname', 'first_name', 'firstname'],
        'lname' => ['lname', 'last_name', 'lastname'],
        'sex' => ['sex', 'gender'],
        'birth' => ['birth', 'birthdate', 'dob', 'dateofbirth'],
        'hid' => ['hid', 'h_id', 'home_id'],
        'addr' => ['addr', 'address', 'house_no', 'house_num', 'no'],
        'check_vhid' => ['check_vhid', 'check_vhic', 'vhid', 'vhic', 'village_id', 'village_code'],
        'nation' => ['nation', 'nationality'],
        'typearea' => ['typearea', 'type_area'],
        'sbp' => ['sbp', 'bp_sys', 'sys'],
        'dbp' => ['dbp', 'bp_dia', 'dia'],
        'risk' => ['risk', 'risk_group', 'risk_level']
    ],
    'home' => [
        'hoscode' => ['hoscode', 'hcode', 'pcucode', 'hos_code', 'h_code', 'hospcode'],
        'hid' => ['hid', 'h_id', 'home_id'],
        'house_no' => ['house_no', 'house_num', 'no', 'addr', 'address'],
        'vhid_code' => ['vhid_code', 'vhid', 'check_vhid', 'check_vhic', 'vhic', 'village_id', 'village_code'],
        'latitude' => ['latitude', 'lat', 'house_lat'],
        'longitude' => ['longitude', 'lng', 'lon', 'house_lng']
    ]
];

$criticalColumns = [
    'person' => ['pid', 'cid', 'first_name', 'last_name'],
    'dm' => ['pid', 'risk'],
    'ht' => ['pid', 'risk'],
    'home' => ['hid', 'vhid_code']
];

$thaiColNames = [
    'hoscode' => 'รหัสหน่วยบริการ (hoscode)',
    'hosname' => 'ชื่อหน่วยบริการ (hosname)',
    'pid' => 'เลขบุคคลประจำหน่วย (pid)',
    'cid' => 'เลขบัตรประชาชน (cid)',
    'first_name' => 'ชื่อจริง (first_name)',
    'last_name' => 'นามสกุล (last_name)',
    'name' => 'ชื่อจริง (name)',
    'lname' => 'นามสกุล (lname)',
    'sex' => 'เพศ (sex)',
    'birth' => 'วันเกิด (birth)',
    'hid' => 'รหัสบ้าน (hid)',
    'house_no' => 'บ้านเลขที่ (house_no)',
    'addr' => 'บ้านเลขที่/ที่อยู่ (addr)',
    'vhid_code' => 'รหัสหมู่บ้าน 8 หลัก (vhid_code)',
    'check_vhid' => 'รหัสหมู่บ้าน (check_vhid)',
    'nation' => 'สัญชาติ (nation)',
    'typearea' => 'สถานะอยู่อาศัย (typearea)',
    'discharge' => 'สถานะการจำหน่าย (discharge)',
    'date_screen' => 'วันที่ตรวจ (date_screen)',
    'bstest' => 'วิธีการตรวจน้ำตาล (bstest)',
    'bslevel' => 'ระดับน้ำตาล FBS (bslevel)',
    'hosp_screen' => 'หน่วยงานที่ตรวจ (hosp_screen)',
    'hosp_input' => 'หน่วยงานที่บันทึก (hosp_input)',
    'providername' => 'ชื่อผู้ตรวจ (providername)',
    'sbp' => 'ค่าความดันซิสโตลิก SBP (sbp)',
    'dbp' => 'ค่าความดันไดแอสโตลิก DBP (dbp)',
    'risk' => 'ระดับความเสี่ยง 0,1,2 (risk)',
    'result' => 'สรุปผลตรวจ (result)',
    'latitude' => 'ละติจูด (latitude)',
    'longitude' => 'ลองจิจูด (longitude)'
];

$previewData = null;
$step = 1; // 1 = Form, 2 = Preview

// Ensure scratch directory exists
$tempDir = __DIR__ . '/../scratch/temp_uploads';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// Action: Cancel and delete temp file
if (isset($_POST['action_cancel'])) {
    $tempFile = $_POST['temp_file'] ?? '';
    if ($tempFile && file_exists($tempDir . '/' . basename($tempFile))) {
        unlink($tempDir . '/' . basename($tempFile));
    }
    $step = 1;
}

// Action: Handle File Upload & Generate Preview
if (isset($_POST['action_upload']) && isset($_FILES['csv_file'])) {
    $importType = $_POST['import_type'] ?? 'dm';
    $selectedHoscode = $_POST['hoscode'] ?? '10957';
    $file = $_FILES['csv_file']['tmp_name'];
    $originalName = $_FILES['csv_file']['name'];
    
    $isMock = defined('MOCK_UPLOAD') && MOCK_UPLOAD;
    if ($isMock || is_uploaded_file($file)) {
        $tempFileName = 'temp_' . session_id() . '_' . time() . '_' . $importType . '.csv';
        $tempPath = $tempDir . '/' . $tempFileName;
        
        if ($isMock ? copy($file, $tempPath) : move_uploaded_file($file, $tempPath)) {
            $handle = fopen($tempPath, 'r');
            if ($handle !== false) {
                // Detect delimiter
                $firstLine = fgets($handle);
                $delimiter = ',';
                if (strpos($firstLine, '|') !== false) {
                    $delimiter = '|';
                }
                rewind($handle); // Rewind to the beginning so fgetcsv reads the first row
                
                // Read header row
                $firstRow = fgetcsv($handle, 1000, $delimiter);
                if ($firstRow !== false) {
                    foreach ($firstRow as $k => $v) {
                        if (!safe_is_utf8($v)) {
                            $firstRow[$k] = safe_tis620_to_utf8($v);
                        }
                    }
                    $hasHeaders = isHeaderRow($firstRow);
                
                $mapping = [];
                $expectedCols = $columnMappings[$importType];
                $criticalCols = $criticalColumns[$importType];
                $missingCritical = [];
                $headers = [];
                
                if ($hasHeaders) {
                    $headers = $firstRow;
                    foreach ($expectedCols as $colKey => $names) {
                        $idx = getColumnIndex($headers, $names);
                        $mapping[$colKey] = $idx;
                        if ($idx === -1 && in_array($colKey, $criticalCols)) {
                            $missingCritical[] = $colKey;
                        }
                    }
                } else {
                    // No headers: check if JHCIS PERSON or HOME file structure
                    if ($importType === 'person' && count($firstRow) >= 23) {
                        $mapping = [
                            'hoscode' => 0,
                            'pid' => 1,
                            'cid' => 2,
                            'first_name' => 4,
                            'last_name' => 5,
                            'sex' => 6,
                            'birth' => 7,
                            'hid' => -1,
                            'house_no' => -1,
                            'vhid_code' => -1,
                            'typearea' => 22
                        ];
                    } elseif ($importType === 'home' && count($firstRow) >= 18) {
                        $mapping = [
                            'hoscode' => 0,
                            'hid' => 1,
                            'house_no' => 4,
                            'vhid_code' => 9,
                            'latitude' => 16,
                            'longitude' => 17
                        ];
                    } else {
                        // Assume first row is header but missing critical cols
                        $headers = $firstRow;
                        foreach ($expectedCols as $colKey => $names) {
                            $idx = getColumnIndex($headers, $names);
                            $mapping[$colKey] = $idx;
                            if ($idx === -1 && in_array($colKey, $criticalCols)) {
                                $missingCritical[] = $colKey;
                            }
                        }
                    }
                    // Rewind since first row is data
                    rewind($handle);
                }
                
                // Read first 5 data rows for preview table
                $sampleRows = [];
                $count = 0;
                while (($row = fgetcsv($handle, 1000, $delimiter)) !== false && $count < 5) {
                        if (empty(array_filter($row))) continue;
                        
                        foreach ($row as $k => $v) {
                            if (!safe_is_utf8($v)) {
                                $row[$k] = safe_tis620_to_utf8($v);
                            }
                            $row[$k] = trim((string)$row[$k]);
                        }
                        
                        $mappedRow = [];
                        foreach ($mapping as $colKey => $idx) {
                            $mappedRow[$colKey] = ($idx !== -1 && isset($row[$idx])) ? $row[$idx] : null;
                        }
                        $sampleRows[] = $mappedRow;
                        $count++;
                    }
                    fclose($handle);
                    
                    $previewData = [
                        'import_type' => $importType,
                        'hoscode' => $selectedHoscode,
                        'temp_file' => $tempFileName,
                        'original_name' => $originalName,
                        'delimiter' => $delimiter,
                        'mapping' => $mapping,
                        'headers_count' => count($headers),
                        'missing_critical' => $missingCritical,
                        'sample_rows' => $sampleRows,
                        'expected_cols' => $expectedCols,
                        'raw_headers' => $headers
                    ];
                    $step = 2;
                } else {
                    $error = "ไม่สามารถอ่านหัวคอลัมน์ของไฟล์ได้";
                    unlink($tempPath);
                }
            } else {
                $error = "ไม่สามารถเปิดไฟล์ได้";
                unlink($tempPath);
            }
        } else {
            $error = "ไม่สามารถบันทึกไฟล์ชั่วคราวได้";
        }
    } else {
        $error = "กรุณาอัปโหลดไฟล์ที่ถูกต้อง";
    }
}

// Action: Confirm & Save Data to Database
if (isset($_POST['action_confirm'])) {
    $importType = $_POST['import_type'] ?? 'dm';
    $selectedHoscode = $_POST['hoscode'] ?? '10957';
    $tempFileName = $_POST['temp_file'] ?? '';
    $tempPath = $tempDir . '/' . basename($tempFileName);
    $delimiter = $_POST['delimiter'] ?? ',';
    
    if (file_exists($tempPath)) {
        try {
            // Re-read header mapping
            $handle = fopen($tempPath, 'r');
            $firstRow = fgetcsv($handle, 1000, $delimiter);
            if ($firstRow !== false) {
                foreach ($firstRow as $k => $v) {
                    if (!safe_is_utf8($v)) {
                        $firstRow[$k] = safe_tis620_to_utf8($v);
                    }
                }
            }
            $hasHeaders = isHeaderRow($firstRow);
            
            $mapping = [];
            $expectedCols = $columnMappings[$importType];
            
            if ($hasHeaders) {
                foreach ($expectedCols as $colKey => $names) {
                    $mapping[$colKey] = getColumnIndex($firstRow, $names);
                }
            } else {
                if ($importType === 'person' && count($firstRow) >= 23) {
                    $mapping = [
                        'hoscode' => 0,
                        'pid' => 1,
                        'cid' => 2,
                        'first_name' => 4,
                        'last_name' => 5,
                        'sex' => 6,
                        'birth' => 7,
                        'hid' => -1,
                        'house_no' => -1,
                        'vhid_code' => -1,
                        'typearea' => 22
                    ];
                } elseif ($importType === 'home' && count($firstRow) >= 18) {
                    $mapping = [
                        'hoscode' => 0,
                        'hid' => 1,
                        'house_no' => 4,
                        'vhid_code' => 9,
                        'latitude' => 16,
                        'longitude' => 17
                    ];
                } else {
                    foreach ($expectedCols as $colKey => $names) {
                        $mapping[$colKey] = getColumnIndex($firstRow, $names);
                    }
                }
                rewind($handle); // First row is data, rewind to start reading from line 1
            }
            
            // Prepared queries
            if ($importType === 'dm') {
                $pdo->exec("TRUNCATE TABLE staging_hdc_dm");
                $stmt = $pdo->prepare("
                    INSERT INTO staging_hdc_dm 
                    (hoscode, hosname, pid, cid, name, lname, sex, birth, hid, addr, check_vhid, nation, discharge, typearea, date_screen, bstest, bslevel, hosp_screen, hosp_input, providername, risk, result) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
            } elseif ($importType === 'ht') {
                $pdo->exec("TRUNCATE TABLE staging_hdc_ht");
                $stmt = $pdo->prepare("
                    INSERT INTO staging_hdc_ht 
                    (hoscode, hosname, pid, cid, name, lname, sex, birth, hid, addr, check_vhid, nation, typearea, sbp, dbp, risk) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
            } elseif ($importType === 'home') {
                $stmt = $pdo->prepare("
                    INSERT INTO jhcis_homes 
                    (hoscode, hid, house_no, vhid_code, latitude, longitude)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                      house_no = VALUES(house_no),
                      vhid_code = VALUES(vhid_code),
                      latitude = VALUES(latitude),
                      longitude = VALUES(longitude)
                ");
            } else {
                // Type: person
                $stmtCheckPerson = $pdo->prepare("SELECT * FROM target_population WHERE cid = ? OR (hoscode = ? AND pid = ?)");
                $stmtUpdatePersonCid = $pdo->prepare("UPDATE target_population SET cid = ?, hid = ?, pid = ?, first_name = ?, last_name = ?, sex = ?, birth = ?, house_no = ?, moo = ?, sub_district_code = ?, vhid_code = ?, hoscode = ?, updated_at = NOW() WHERE cid = ?");
                $stmtUpdatePersonSimple = $pdo->prepare("UPDATE target_population SET hid = ?, pid = ?, first_name = ?, last_name = ?, sex = ?, birth = ?, house_no = ?, moo = ?, sub_district_code = ?, vhid_code = ?, hoscode = ?, updated_at = NOW() WHERE cid = ?");
                $stmtInsertPerson = $pdo->prepare("INSERT INTO target_population (cid, hid, pid, first_name, last_name, sex, birth, house_no, moo, sub_district_code, vhid_code, hoscode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtUpdateAssignCid = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE target_cid = ?");
                $stmtUpdateDpacCid = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE cid = ?");
            }
            
            if ($selectedHoscode === 'ALL') {
                $allowedHoscodes = array_keys($hc_names);
            } else {
                $allowedHoscodes = [$selectedHoscode];
            }
            
            $pdo->beginTransaction();
            $linesImported = 0;
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                if (empty(array_filter($row))) continue;
                
                foreach ($row as $k => $v) {
                    if (!safe_is_utf8($v)) {
                        $row[$k] = safe_tis620_to_utf8($v);
                    }
                    $row[$k] = trim((string)$row[$k]);
                }
                
                $rowVals = [];
                foreach ($expectedCols as $colKey => $names) {
                    $idx = $mapping[$colKey];
                    $rowVals[$colKey] = ($idx !== -1 && isset($row[$idx])) ? $row[$idx] : null;
                }
                
                // Get hoscode
                $rowHoscode = $rowVals['hoscode'] ?: $selectedHoscode;
                if (!in_array($rowHoscode, $allowedHoscodes)) {
                    continue; // Skip mismatching records
                }
                
                // Clean dates
                $birthDate = $rowVals['birth'] ?? null;
                if ($birthDate) {
                    $birthDate = preg_replace('/[^0-9\-]/', '', $birthDate);
                    if (strlen($birthDate) === 8 && is_numeric($birthDate)) {
                        $birthDate = substr($birthDate, 0, 4) . '-' . substr($birthDate, 4, 2) . '-' . substr($birthDate, 6, 2);
                    }
                }
                
                if ($importType === 'dm') {
                    $dateScreen = $rowVals['date_screen'] ?? null;
                    if ($dateScreen) {
                        $dateScreen = preg_replace('/[^0-9\-]/', '', $dateScreen);
                        if (strlen($dateScreen) === 8 && is_numeric($dateScreen)) {
                            $dateScreen = substr($dateScreen, 0, 4) . '-' . substr($dateScreen, 4, 2) . '-' . substr($dateScreen, 6, 2);
                        }
                    }
                    
                    $stmt->execute([
                        $rowHoscode,
                        $rowVals['hosname'] ?: ($hc_names[$rowHoscode] ?? ''),
                        $rowVals['pid'],
                        $rowVals['cid'],
                        $rowVals['name'],
                        $rowVals['lname'],
                        $rowVals['sex'],
                        $birthDate ?: null,
                        $rowVals['hid'],
                        $rowVals['addr'],
                        $rowVals['check_vhid'],
                        $rowVals['nation'],
                        $rowVals['discharge'],
                        $rowVals['typearea'],
                        $dateScreen ?: null,
                        $rowVals['bstest'],
                        ($rowVals['bslevel'] !== '' && is_numeric($rowVals['bslevel'])) ? (int)$rowVals['bslevel'] : null,
                        $rowVals['hosp_screen'],
                        $rowVals['hosp_input'],
                        $rowVals['providername'],
                        $rowVals['risk'],
                        $rowVals['result']
                    ]);
                } elseif ($importType === 'ht') {
                    $stmt->execute([
                        $rowHoscode,
                        $rowVals['hosname'] ?: ($hc_names[$rowHoscode] ?? ''),
                        $rowVals['pid'],
                        $rowVals['cid'],
                        $rowVals['name'],
                        $rowVals['lname'],
                        $rowVals['sex'],
                        $birthDate ?: null,
                        $rowVals['hid'],
                        $rowVals['addr'],
                        $rowVals['check_vhid'],
                        $rowVals['nation'],
                        $rowVals['typearea'],
                        ($rowVals['sbp'] !== '' && is_numeric($rowVals['sbp'])) ? (int)$rowVals['sbp'] : null,
                        ($rowVals['dbp'] !== '' && is_numeric($rowVals['dbp'])) ? (int)$rowVals['dbp'] : null,
                        $rowVals['risk']
                    ]);
                } elseif ($importType === 'home') {
                    $lat = ($rowVals['latitude'] !== '' && is_numeric($rowVals['latitude'])) ? (float)$rowVals['latitude'] : null;
                    $lng = ($rowVals['longitude'] !== '' && is_numeric($rowVals['longitude'])) ? (float)$rowVals['longitude'] : null;
                    
                    if ($lat == 0 || $lng == 0) {
                        $lat = null;
                        $lng = null;
                    }
                    
                    $stmt->execute([
                        $rowHoscode,
                        $rowVals['hid'],
                        $rowVals['house_no'],
                        $rowVals['vhid_code'],
                        $lat,
                        $lng
                    ]);
                } else {
                    // Type: person
                    $newCid = $rowVals['cid'];
                    $pid = $rowVals['pid'];
                    $firstName = $rowVals['first_name'];
                    $lastName = $rowVals['last_name'];
                    $sex = $rowVals['sex'];
                    
                    // Determine address defaults
                    $checkVhid = $rowVals['vhid_code'] ?? '';
                    $houseNo = $rowVals['house_no'] ?? '';
                    if (strlen($checkVhid) === 8) {
                        $moo = (int)substr($checkVhid, 6, 2);
                        $subDistrictCode = substr($checkVhid, 0, 6);
                    } else {
                        $moo = 1;
                        $subDistrictCode = '341801';
                        $checkVhid = '34180101';
                    }

                    // Check if person exists by (hoscode and pid) OR (cid)
                    $stmtCheckPerson->execute([$newCid, $rowHoscode, $pid]);
                    $existing = $stmtCheckPerson->fetch();

                    if ($existing) {
                        $oldCid = $existing['cid'];
                        
                        // Preserve existing address and coordinates from HDC if they already exist
                        $finalHid = !empty($existing['hid']) ? $existing['hid'] : ($rowVals['hid'] ?: null);
                        $finalHouseNo = !empty($existing['house_no']) ? $existing['house_no'] : ($houseNo ?: null);
                        $finalMoo = !empty($existing['moo']) ? $existing['moo'] : $moo;
                        $finalSubDistrict = !empty($existing['sub_district_code']) ? $existing['sub_district_code'] : $subDistrictCode;
                        $finalVhid = !empty($existing['vhid_code']) && $existing['vhid_code'] !== '34180101' ? $existing['vhid_code'] : $checkVhid;

                        if ($oldCid !== $newCid) {
                            // Primary key change: disable foreign key checks temporarily
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                            
                            // Update task assignments target_cid
                            $stmtUpdateAssignCid->execute([$newCid, $oldCid]);
                            
                            // Update DPAC enrollments cid
                            $stmtUpdateDpacCid->execute([$newCid, $oldCid]);
                            
                            // Update person record using the old CID as unique key for update
                            $stmtUpdatePersonCid->execute([
                                $newCid,
                                $finalHid,
                                $pid,
                                $firstName,
                                $lastName,
                                $sex,
                                $birthDate ?: null,
                                $finalHouseNo,
                                $finalMoo,
                                $finalSubDistrict,
                                $finalVhid,
                                $rowHoscode,
                                $oldCid
                            ]);
                            
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                        } else {
                            // Same CID: update demographics and preserve address
                            $stmtUpdatePersonSimple->execute([
                                $finalHid,
                                $pid,
                                $firstName,
                                $lastName,
                                $sex,
                                $birthDate ?: null,
                                $finalHouseNo,
                                $finalMoo,
                                $finalSubDistrict,
                                $finalVhid,
                                $rowHoscode,
                                $newCid
                            ]);
                        }
                    } else {
                        // Insert new record
                        $stmtInsertPerson->execute([
                            $newCid,
                            $rowVals['hid'] ?: null,
                            $pid,
                            $firstName,
                            $lastName,
                            $sex,
                            $birthDate ?: null,
                            $houseNo ?: null,
                            $moo,
                            $subDistrictCode,
                            $checkVhid,
                            $rowHoscode
                        ]);
                    }
                }
                $linesImported++;
            }
            fclose($handle);
            
            // Auto-synchronize address and coordinates from jhcis_homes to target_population
            if ($importType === 'person' || $importType === 'home') {
                $pdo->exec("
                    UPDATE target_population t
                    JOIN jhcis_homes h ON t.hoscode = h.hoscode AND t.hid = h.hid
                    SET 
                      t.house_no = CASE WHEN h.house_no IS NOT NULL AND h.house_no != '' THEN h.house_no ELSE t.house_no END,
                      t.vhid_code = CASE WHEN h.vhid_code IS NOT NULL AND h.vhid_code != '' THEN h.vhid_code ELSE t.vhid_code END,
                      t.moo = CASE WHEN h.vhid_code IS NOT NULL AND LENGTH(h.vhid_code) = 8 THEN CAST(SUBSTRING(h.vhid_code, 7, 2) AS UNSIGNED) ELSE t.moo END,
                      t.sub_district_code = CASE WHEN h.vhid_code IS NOT NULL AND LENGTH(h.vhid_code) = 8 THEN SUBSTRING(h.vhid_code, 1, 6) ELSE t.sub_district_code END,
                      t.latitude = CASE WHEN h.latitude IS NOT NULL AND h.latitude != 0 THEN h.latitude ELSE t.latitude END,
                      t.longitude = CASE WHEN h.longitude IS NOT NULL AND h.longitude != 0 THEN h.longitude ELSE t.longitude END,
                      t.updated_at = NOW()
                ");
            }
            
            $pdo->commit();
            
            unlink($tempPath);
            $message = "นำเข้าข้อมูลสู่ระบบสำเร็จเรียบร้อย! จำนวนทั้งสิ้น $linesImported แถว";
            $step = 1;
        } catch (\Exception $e) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (\Exception $re) {
                // Ignore rollback errors to preserve original exception
            }
            $error = "เกิดข้อผิดพลาดในการนำเข้าข้อมูล: " . $e->getMessage();
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            $step = 1;
        }
    } else {
        $error = "ไม่พบไฟล์ชั่วคราวกรุณาเริ่มขั้นตอนใหม่อีกครั้ง";
        $step = 1;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นำเข้าข้อมูล HDC & 43 แฟ้ม - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .dropzone-container {
            border: 2.5px dashed rgba(13, 44, 84, 0.2);
            border-radius: var(--border-radius);
            padding: 50px 30px;
            text-align: center;
            background-color: var(--bg-darker);
            box-shadow: var(--neumorph-inset);
            cursor: pointer;
            transition: all var(--transition-speed) ease-in-out;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .dropzone-container:hover {
            border-color: var(--color-green);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(13, 44, 84, 0.08), var(--neumorph-inset);
        }
        .dropzone-container::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(13, 44, 84, 0.05) 100%);
            opacity: 0;
            transition: opacity var(--transition-speed);
            pointer-events: none;
        }
        .dropzone-container:hover::after {
            opacity: 1;
        }
        .file-input {
            display: none;
        }
        .radio-card {
            background-color: var(--bg-card);
            border: 2px solid transparent;
            border-radius: 18px;
            padding: 16px;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            position: relative;
        }
        .radio-card:hover {
            transform: translateY(-3px);
            box-shadow: 8px 8px 16px #d1d9e6, -8px -8px 16px #ffffff;
        }
        .radio-card input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--color-primary);
            cursor: pointer;
            margin-top: 3px;
        }
        .radio-card.selected {
            box-shadow: var(--neumorph-inset) !important;
            background-color: var(--bg-darker) !important;
            border-color: rgba(13, 44, 84, 0.15);
            transform: translateY(0);
        }
        #label-person.radio-card.selected, #label-home.radio-card.selected {
            border-color: rgba(16, 185, 129, 0.25);
        }
        .mapping-table th, .mapping-table td {
            font-size: 13px;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .badge-success { background-color: rgba(34, 197, 94, 0.15); color: var(--color-green); }
        .badge-warning { background-color: rgba(245, 158, 11, 0.15); color: var(--color-yellow); }
        .badge-danger { background-color: rgba(239, 68, 68, 0.15); color: var(--color-red); }
    </style>
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

    <div style="max-width: 1000px; margin: 40px auto; padding: 0 20px;">
        
        <?php if (!empty($message)): ?>
            <div style="background-color: rgba(16, 185, 129, 0.15); border: 2px solid var(--color-green); color: var(--color-green); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px;">
                <strong>สำเร็จ!</strong> <?= htmlspecialchars($message) ?>
                <?php if ($importType !== 'person'): ?>
                <div style="margin-top: 12px;">
                    <a href="process_etl.php" class="btn-primary" style="padding: 10px 20px; text-decoration: none; display: inline-block; border-radius: 8px;">
                        ไปหน้าประมวลผลข้อมูล (Run ETL Process) →
                    </a>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px;">
                <strong>ล้มเหลว!</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- STEP 1: Upload Form -->
            <div class="card-dark">
                <h2 style="border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                    นำเข้าข้อมูลคัดกรอง & ทะเบียนผู้ป่วย
                </h2>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">
                    อัปโหลดไฟล์นำเข้าข้อมูลเพื่อพักที่ตาราง staging หรือปรับปรุงฐานข้อมูลทะเบียนประชากร โดยเลือกประเภทไฟล์และรหัสหน่วยบริการที่อ้างอิง
                </p>

                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action_upload" value="1">
                    
                    <!-- Step 1: Select Hospital -->
                    <div style="background: var(--bg-card); border-radius: var(--border-radius); padding: 24px; box-shadow: var(--neumorph-flat); margin-bottom: 28px;">
                        <h3 style="color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 800;">
                            <span style="display: flex; align-items: center; justify-content: center; background: var(--color-primary); color: white; width: 28px; height: 28px; border-radius: 50%; font-size: 13px; font-weight: bold;">1</span>
                            เลือกหน่วยบริการ / รพ.สต. เพื่ออ้างอิงข้อมูล:
                        </h3>
                        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;">
                            กรุณาเลือกหน่วยบริการอ้างอิง หรือเลือก <strong style="color: var(--color-primary);">"ทุกหน่วยบริการ"</strong> เพื่อนำเข้าไฟล์ประมวลผลรวมที่มาจากระบบ HDC อำเภอ
                        </p>
                        <div style="position: relative; max-width: 550px;">
                            <select name="hoscode" required style="width: 100%; padding: 14px 18px; border-radius: 14px; border: 2px solid rgba(13, 44, 84, 0.1); background: var(--bg-darker); color: var(--text-primary); font-size: 14px; font-weight: 600; cursor: pointer; transition: all var(--transition-speed); outline: none; appearance: none;">
                                <option value="ALL" <?= $selectedHoscode === 'ALL' ? 'selected' : '' ?>>ทุกหน่วยบริการ (รวมข้อมูลทั้งหมด)</option>
                                <?php foreach ($hc_names as $code => $name): ?>
                                    <option value="<?= $code ?>" <?= $selectedHoscode === $code ? 'selected' : '' ?>><?= $code ?> - <?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div style="position: absolute; right: 18px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-secondary); display: flex; align-items: center;">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Select File Type (Grouped HDC vs JHCIS) -->
                    <div style="background: var(--bg-card); border-radius: var(--border-radius); padding: 24px; box-shadow: var(--neumorph-flat); margin-bottom: 28px;">
                        <h3 style="color: var(--text-primary); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 800;">
                            <span style="display: flex; align-items: center; justify-content: center; background: var(--color-primary); color: white; width: 28px; height: 28px; border-radius: 50%; font-size: 13px; font-weight: bold;">2</span>
                            เลือกประเภทไฟล์และแหล่งข้อมูลนำเข้า:
                        </h3>
                        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                            เลือกระบบที่เป็นแหล่งที่มาของไฟล์เพื่อดำเนินการตรวจสอบคอลัมน์การจับคู่ข้อมูล
                        </p>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                            
                            <!-- Group A: HDC Data -->
                            <div style="background: rgba(13, 44, 84, 0.03); padding: 20px; border-radius: 20px; border: 1px solid rgba(13, 44, 84, 0.05); display: flex; flex-direction: column; gap: 14px;">
                                <h4 style="color: var(--color-primary); font-size: 15px; margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px; font-weight: bold;">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    ไฟล์ส่งออกจากระบบ HDC (ข้อมูลคัดกรองมีสิทธิ์ปกปิด)
                                </h4>
                                
                                <label class="radio-card selected" id="label-dm" onclick="selectImport('dm')">
                                    <input type="radio" name="import_type" value="dm" checked style="accent-color: var(--color-primary);">
                                    <div>
                                        <strong style="display: block; font-size: 14px; color: var(--text-primary);">DMexchange.csv (เบาหวาน)</strong>
                                        <span style="display: block; font-size: 11px; color: var(--text-secondary); margin-top: 4px; line-height: 1.4;">ไฟล์คัดกรองเบาหวานจาก HDC เพื่อเก็บประวัติความเสี่ยงระดับ 1, 2, 5</span>
                                    </div>
                                </label>
                                
                                <label class="radio-card" id="label-ht" onclick="selectImport('ht')">
                                    <input type="radio" name="import_type" value="ht" style="accent-color: var(--color-primary);">
                                    <div>
                                        <strong style="display: block; font-size: 14px; color: var(--text-primary);">HTexchange.csv (ความดัน)</strong>
                                        <span style="display: block; font-size: 11px; color: var(--text-secondary); margin-top: 4px; line-height: 1.4;">ไฟล์คัดกรองความดันจาก HDC เพื่อเก็บค่า BP และประวัติกลุ่มเสี่ยง</span>
                                    </div>
                                </label>
                            </div>

                            <!-- Group B: JHCIS Data -->
                            <div style="background: rgba(16, 185, 129, 0.03); padding: 20px; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.05); display: flex; flex-direction: column; gap: 14px;">
                                <h4 style="color: var(--color-green); font-size: 15px; margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px; font-weight: bold;">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    ไฟล์ส่งออกจากระบบ JHCIS / 43 แฟ้ม (ข้อมูลจริงไม่ปกปิด)
                                </h4>
                                
                                <label class="radio-card" id="label-person" onclick="selectImport('person')">
                                    <input type="radio" name="import_type" value="person" style="accent-color: var(--color-primary);">
                                    <div>
                                        <strong style="display: block; font-size: 14px; color: var(--text-primary);">PERSON.csv (ทะเบียนประชากร)</strong>
                                        <span style="display: block; font-size: 11px; color: var(--text-secondary); margin-top: 4px; line-height: 1.4;">ข้อมูลบุคคลจริง (ชื่อ-สกุล และ CID เต็ม) เพื่อใช้แมปถอดรหัสข้อมูล HDC</span>
                                    </div>
                                </label>
                                
                                <label class="radio-card" id="label-home" onclick="selectImport('home')">
                                    <input type="radio" name="import_type" value="home" style="accent-color: var(--color-primary);">
                                    <div>
                                        <strong style="display: block; font-size: 14px; color: var(--text-primary);">HOME.csv (ทะเบียนบ้าน)</strong>
                                        <span style="display: block; font-size: 11px; color: var(--text-secondary); margin-top: 4px; line-height: 1.4;">ข้อมูลบ้าน เลขที่ และพิกัดตำแหน่งบ้านเพื่อบันทึกพิกัดภูมิศาสตร์ (GPS)</span>
                                    </div>
                                </label>
                            </div>
                            
                        </div>
                    </div>

                    <!-- Step 3: Drag and Drop Area -->
                    <div style="background: var(--bg-card); border-radius: var(--border-radius); padding: 24px; box-shadow: var(--neumorph-flat); margin-bottom: 28px;">
                        <h3 style="color: var(--text-primary); margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 800;">
                            <span style="display: flex; align-items: center; justify-content: center; background: var(--color-primary); color: white; width: 28px; height: 28px; border-radius: 50%; font-size: 13px; font-weight: bold;">3</span>
                            เลือกไฟล์นำเข้าข้อมูล (.csv หรือ .txt):
                        </h3>
                        
                        <div class="dropzone-container" onclick="document.getElementById('csv_file').click()">
                            <div style="display: inline-flex; align-items: center; justify-content: center; background: var(--bg-card); width: 68px; height: 68px; border-radius: 50%; box-shadow: var(--neumorph-flat); margin-bottom: 14px; color: var(--color-primary);">
                                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            </div>
                            <span style="color: var(--text-primary); font-size: 18px; font-weight: bold; display: block; margin-bottom: 6px;">คลิก หรือ ลากไฟล์ข้อมูลมาวางที่นี่</span>
                            <span style="color: var(--text-secondary); font-size: 13px;" id="file-name-display">รองรับไฟล์ CSV (.csv) หรือไฟล์คั่นด้วยเครื่องหมายไพป์ (.txt)</span>
                            <input type="file" name="csv_file" id="csv_file" class="file-input" accept=".csv,.txt" required onchange="handleFileSelect(this)">
                        </div>
                    </div>

                    <button type="submit" class="btn-giant btn-giant-primary" style="border-radius: var(--border-radius); width: 100%; border: none; outline: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 16px; font-weight: bold; height: 56px; box-shadow: var(--neumorph-flat);">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        อัปโหลดเพื่อแสดงตัวอย่างและตรวจสอบไฟล์ (Preview File)
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- STEP 2: Preview & Confirm -->
            <div class="card-dark">
                <h2 style="border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    ตรวจสอบความถูกต้องและจับคู่คอลัมน์ (Data Preview)
                </h2>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; font-size: 14px; color: var(--text-secondary);">
                    <div style="background: var(--bg-darker); padding: 12px 16px; border-radius: 8px;">
                        ชื่อไฟล์ดั้งเดิม: <strong style="color: var(--text-primary);"><?= htmlspecialchars($previewData['original_name']) ?></strong>
                    </div>
                    <div style="background: var(--bg-darker); padding: 12px 16px; border-radius: 8px;">
                        ประเภทข้อมูล: <strong style="color: var(--color-accent);"><?= strtoupper($previewData['import_type']) ?></strong>
                    </div>
                    <div style="background: var(--bg-darker); padding: 12px 16px; border-radius: 8px;">
                        รหัสหน่วยบริการอ้างอิง: <strong style="color: var(--text-primary);"><?= htmlspecialchars($previewData['hoscode']) ?> (<?= $previewData['hoscode'] === 'ALL' ? 'ทุกหน่วยบริการ (รวมข้อมูลทั้งหมด)' : htmlspecialchars($hc_names[$previewData['hoscode']] ?? '') ?>)</strong>
                    </div>
                </div>

                <!-- Missing Critical Columns warning -->
                <?php if (!empty($previewData['missing_critical'])): ?>
                    <div style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px;">
                        <strong>⚠️ ข้อควรระวัง:</strong> ไม่พบคอลัมน์สำคัญที่ระบบต้องการ ได้แก่: 
                        <strong><?= implode(', ', array_map(function($c) use ($thaiColNames) { return $thaiColNames[$c] ?? $c; }, $previewData['missing_critical'])) ?></strong> 
                        กรุณาตรวจสอบว่ามีคอลัมน์เหล่านี้หรือไม่หรือสะกดถูกต้องหรือไม่ก่อนกดนำเข้า
                    </div>
                <?php endif; ?>

                <h3 style="color: var(--color-accent); margin-bottom: 12px;">1. ผลวิเคราะห์ความตรงกันของหัวคอลัมน์ (Header Mapping Analysis)</h3>
                <div class="table-responsive" style="margin-bottom: 30px;">
                    <table class="admin-table mapping-table">
                        <thead>
                            <tr>
                                <th>ช่องข้อมูลที่ระบบต้องการ (Expected)</th>
                                <th>คอลัมน์ที่ตรวจพบในไฟล์ (Detected)</th>
                                <th style="text-align: center;">สถานะ (Status)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewData['expected_cols'] as $colKey => $possibleNames): ?>
                                <?php 
                                $idx = $previewData['mapping'][$colKey];
                                $foundName = ($idx !== -1 && isset($previewData['raw_headers'][$idx])) ? $previewData['raw_headers'][$idx] : null;
                                $isCrit = in_array($colKey, $criticalColumns[$previewData['import_type']]);
                                ?>
                                <tr>
                                    <td style="font-weight: bold;">
                                        <?= $thaiColNames[$colKey] ?? $colKey ?> 
                                        <?= $isCrit ? '<span style="color: var(--color-red);">*จำเป็น</span>' : '' ?>
                                    </td>
                                    <td>
                                        <?= $foundName ? '<span style="color: var(--text-primary); font-family: monospace;">' . htmlspecialchars($foundName) . '</span> (คอลัมน์ที่ ' . ($idx+1) . ')' : '<span style="color: var(--text-secondary); font-style: italic;">ไม่พบในไฟล์</span>' ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($idx !== -1): ?>
                                            <span class="badge badge-success">✅ ตรงกัน</span>
                                        <?php elseif ($isCrit): ?>
                                            <span class="badge badge-danger">❌ ไม่พบ (บังคับ)</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">⚠️ ไม่มี (เว้นว่างได้)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3 style="color: var(--color-accent); margin-bottom: 12px;">2. ตัวอย่างข้อมูลคัดกรอง 5 แถวแรก (First 5 Rows Sample Preview)</h3>
                <div class="table-responsive" style="margin-bottom: 30px;">
                    <table class="admin-table" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <?php foreach ($previewData['expected_cols'] as $colKey => $names): ?>
                                    <th><?= str_replace(' (', '<br>(', $thaiColNames[$colKey] ?? $colKey) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($previewData['sample_rows'])): ?>
                                <tr>
                                    <td colspan="<?= count($previewData['expected_cols']) ?>" style="text-align: center;">ไม่มีข้อมูลแสดงผลตัวอย่าง</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($previewData['sample_rows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($previewData['expected_cols'] as $colKey => $names): ?>
                                            <?php 
                                            $val = $row[$colKey];
                                            $isMaskedVal = ($colKey === 'cid' || $colKey === 'name' || $colKey === 'lname' || $colKey === 'first_name' || $colKey === 'last_name');
                                            ?>
                                            <td style="<?= $isMaskedVal && strpos($val, '*') !== false ? 'color: var(--color-yellow); font-family: monospace;' : '' ?>">
                                                <?= $val !== null ? htmlspecialchars($val) : '<span style="color: #666;">-</span>' ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Forms containing buttons -->
                <div style="display: flex; gap: 16px; margin-top: 30px;">
                    <form action="" method="POST" style="flex: 1;">
                        <input type="hidden" name="temp_file" value="<?= htmlspecialchars($previewData['temp_file']) ?>">
                        <button type="submit" name="action_cancel" class="btn-giant" style="background: transparent; border: 2px solid var(--color-red); color: var(--color-red); border-radius: var(--border-radius); width: 100%;">
                            ✕ ยกเลิกและอัปโหลดใหม่
                        </button>
                    </form>
                    
                    <form action="" method="POST" style="flex: 1;">
                        <input type="hidden" name="action_confirm" value="1">
                        <input type="hidden" name="import_type" value="<?= htmlspecialchars($previewData['import_type']) ?>">
                        <input type="hidden" name="hoscode" value="<?= htmlspecialchars($previewData['hoscode']) ?>">
                        <input type="hidden" name="temp_file" value="<?= htmlspecialchars($previewData['temp_file']) ?>">
                        <input type="hidden" name="delimiter" value="<?= htmlspecialchars($previewData['delimiter']) ?>">
                        <button type="submit" class="btn-giant btn-giant-primary" style="border-radius: var(--border-radius); width: 100%;">
                            💾 ยืนยันการนำเข้าข้อมูล (Confirm Import)
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function selectImport(type) {
            document.getElementById('label-dm').classList.remove('selected');
            document.getElementById('label-ht').classList.remove('selected');
            document.getElementById('label-person').classList.remove('selected');
            document.getElementById('label-home').classList.remove('selected');
            
            const card = document.getElementById('label-' + type);
            card.classList.add('selected');
            
            const radio = card.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
        }

        function handleFileSelect(input) {
            const display = document.getElementById('file-name-display');
            if (input.files && input.files.length > 0) {
                display.innerText = 'ไฟล์ที่เลือก: ' + input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(1) + ' KB)';
                display.style.color = 'var(--color-accent)';
            } else {
                display.innerText = 'รองรับไฟล์ CSV (.csv) หรือไฟล์คั่นด้วยเครื่องหมายไพป์ (.txt)';
                display.style.color = 'var(--text-secondary)';
            }
        }
    </script>
</body>
</html>