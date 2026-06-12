<?php
// admin/import_hdc.php
require_once __DIR__ . '/../config/session.php';

// ตรวจสอบสิทธิ์แอดมิน
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

$message = '';
$error = '';
$linesImported = 0;
$importType = 'dm'; // default
$selectedHoscode = '10957'; // default Tal Sum Hospital

$hc_names = get_health_units();

// Helper to translate risk/result text to numeric values for database staging
function translateRiskTextToNumber($text) {
    if ($text === null || $text === '') return '0';
    $text = trim((string)$text);
    
    if (strpos($text, 'เสี่ยงสูง') !== false) {
        return '2';
    }
    if (strpos($text, 'เสี่ยง') !== false) {
        return '1';
    }
    if (strpos($text, 'สงสัย') !== false) {
        return '3';
    }
    if (strpos($text, 'ป่วย') !== false || strpos($text, 'เบาหวานเดิม') !== false || strpos($text, 'ความดันเดิม') !== false) {
        return '5';
    }
    if (strpos($text, 'ปกติ') !== false) {
        return '0';
    }
    
    if (in_array($text, ['0', '1', '2', '3', '5'])) {
        return $text;
    }
    
    return '0';
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

// Helper to match column headers case-insensitively
function getColumnIndex($headers, $possibleNames) {
    foreach ($possibleNames as $name) {
        $nameClean = strtolower(trim($name));
        foreach ($headers as $idx => $header) {
            $headerClean = strtolower(trim(preg_replace('/[\x{FEFF}\x{FFFE}]/u', '', $header)));
            if ($headerClean === $nameClean) {
                return $idx;
            }
        }
    }
    return -1;
}

// Helper to check if a row is a header row
function isHeaderRow($row) {
    if (empty($row)) return false;
    
    // Check if any cell is a 13-digit CID (usually means data row, not header)
    foreach ($row as $val) {
        if (preg_match('/^[0-9]{13}$/', trim((string)$val))) {
            return false;
        }
    }
    
    $commonHeaders = [
        'cid', 'pid', 'hn', 'hcode', 'hoscode', 'hospcode', 
        'name', 'fname', 'discharge', 'typearea', 'sbp', 'dbp', 'risk',
        'hid', 'house', 'roomno', 'latitude', 'longitude', 'village', 'tambon', 'ampur', 'changwat'
    ];
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
        'hoscode' => ['hoscode', 'hcode', 'pcucode', 'hos_code', 'h_code', 'hospcode'],
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
        'hoscode' => ['hoscode', 'hcode', 'pcucode', 'hos_code', 'h_code', 'hospcode'],
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
        'hoscode' => ['hoscode', 'hcode', 'pcucode', 'hos_code', 'h_code', 'hospcode'],
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
        'risk' => ['risk', 'risk_group', 'risk_level'],
        'result' => ['result', 'screening_result']
    ],
    'home' => [
        'hoscode' => ['hoscode', 'hcode', 'pcucode', 'hos_code', 'h_code', 'hospcode'],
        'hid' => ['hid', 'h_id', 'home_id'],
        'house_no' => ['house_no', 'house_num', 'no', 'addr', 'address', 'house'],
        'vhid_code' => ['vhid_code', 'vhid', 'check_vhid', 'check_vhic', 'vhic', 'village_id', 'village_code', 'vhvid'],
        'latitude' => ['latitude', 'lat', 'house_lat'],
        'longitude' => ['longitude', 'lng', 'lon', 'house_lng'],
        'village' => ['village', 'moo'],
        'tambon' => ['tambon', 'sub_district', 'subdistrict'],
        'ampur' => ['ampur', 'district'],
        'changwat' => ['changwat', 'province']
    ]
];

$criticalColumns = [
    'person' => ['pid', 'cid', 'first_name', 'last_name'],
    'dm' => ['pid'],
    'ht' => ['pid'],
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
    'longitude' => 'ลองจิจูด (longitude)',
    'village' => 'หมู่ที่ (village)',
    'tambon' => 'รหัสตำบล (tambon)',
    'ampur' => 'รหัสอำเภอ (ampur)',
    'changwat' => 'รหัสจังหวัด (changwat)'
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
    @set_time_limit(0);
    @ini_set('max_execution_time', 0);
    @ini_set('memory_limit', '512M');
    $importType = $_POST['import_type'] ?? 'dm';
    $selectedHoscode = 'ALL';
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
                            // Special case for home: if vhid_code is missing but village components are matched, it's not missing
                            if ($idx === -1 && in_array($colKey, $criticalCols)) {
                                if ($colKey === 'vhid_code' && $importType === 'home') {
                                    // Will check later if components are present
                                } else {
                                    $missingCritical[] = $colKey;
                                }
                            }
                        }
                        
                        // Re-validate vhid_code for home type area
                        if ($importType === 'home' && $mapping['vhid_code'] === -1) {
                            if ($mapping['village'] === -1 || $mapping['tambon'] === -1 || $mapping['ampur'] === -1 || $mapping['changwat'] === -1) {
                                $missingCritical[] = 'vhid_code';
                            }
                        }
                    } else {
                        // Generate mock headers
                        $headers = [];
                        for ($i = 0; $i < count($firstRow); $i++) {
                            $headers[] = "คอลัมน์ที่ " . ($i + 1) . " (ไม่มีชื่อหัว)";
                        }
                        
                        // Fallbacks for JHCIS
                        if ($importType === 'person' && count($firstRow) >= 23) {
                            $mapping = [
                                'hoscode' => 0, 'pid' => 2, 'cid' => 1, 'first_name' => 5, 'last_name' => 6,
                                'sex' => 8, 'birth' => 9, 'hid' => 3, 'house_no' => -1, 'vhid_code' => -1, 'typearea' => 29
                            ];
                        } elseif ($importType === 'home' && count($firstRow) >= 18) {
                            $mapping = [
                                'hoscode' => 0, 'hid' => 1, 'house_no' => 6, 'vhid_code' => 20, 'latitude' => 16, 'longitude' => 17,
                                'village' => 11, 'tambon' => 12, 'ampur' => 13, 'changwat' => 14
                            ];
                        } else {
                            foreach ($expectedCols as $colKey => $names) {
                                $mapping[$colKey] = -1;
                            }
                        }
                        
                        // Check criticals
                        foreach ($criticalCols as $colKey) {
                            if ((!isset($mapping[$colKey]) || $mapping[$colKey] === -1)) {
                                if ($colKey === 'vhid_code' && $importType === 'home' && isset($mapping['village']) && $mapping['village'] !== -1 && isset($mapping['tambon']) && $mapping['tambon'] !== -1 && isset($mapping['ampur']) && $mapping['ampur'] !== -1 && isset($mapping['changwat']) && $mapping['changwat'] !== -1) {
                                    continue;
                                }
                                $missingCritical[] = $colKey;
                            }
                        }
                        
                        // Rewind since first row is data
                        rewind($handle);
                    }
                    
                    // Read first 5 data rows for preview table
                    $sampleRows = [];
                    $rawSampleRows = [];
                    $count = 0;
                    while (($row = fgetcsv($handle, 1000, $delimiter)) !== false && $count < 5) {
                        if (empty(array_filter($row))) continue;
                        
                        foreach ($row as $k => $v) {
                            if (!safe_is_utf8($v)) {
                                $row[$k] = safe_tis620_to_utf8($v);
                            }
                            $row[$k] = trim((string)$row[$k]);
                        }
                        
                        $rawSampleRows[] = $row;
                        
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
                        'raw_sample_rows' => $rawSampleRows,
                        'expected_cols' => array_keys($expectedCols),
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
    @set_time_limit(0);
    @ini_set('max_execution_time', 0);
    @ini_set('memory_limit', '512M');
    $importType = $_POST['import_type'] ?? 'dm';
    $selectedHoscode = 'ALL';
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
            
            $hasPostMap = isset($_POST['mapping']) && is_array($_POST['mapping']);
            
            if ($hasPostMap) {
                foreach ($expectedCols as $colKey => $names) {
                    $mapping[$colKey] = isset($_POST['mapping'][$colKey]) && $_POST['mapping'][$colKey] !== '' ? (int)$_POST['mapping'][$colKey] : -1;
                }
            } else {
                if ($hasHeaders) {
                    foreach ($expectedCols as $colKey => $names) {
                        $mapping[$colKey] = getColumnIndex($firstRow, $names);
                    }
                } else {
                    if ($importType === 'person' && count($firstRow) >= 23) {
                        $mapping = [
                            'hoscode' => 0, 'pid' => 2, 'cid' => 1, 'first_name' => 5, 'last_name' => 6,
                            'sex' => 8, 'birth' => 9, 'hid' => 3, 'house_no' => -1, 'vhid_code' => -1, 'typearea' => 29
                        ];
                    } elseif ($importType === 'home' && count($firstRow) >= 18) {
                        $mapping = [
                            'hoscode' => 0, 'hid' => 1, 'house_no' => 6, 'vhid_code' => 20, 'latitude' => 16, 'longitude' => 17,
                            'village' => 11, 'tambon' => 12, 'ampur' => 13, 'changwat' => 14
                        ];
                    } else {
                        foreach ($expectedCols as $colKey => $names) {
                            $mapping[$colKey] = getColumnIndex($firstRow, $names);
                        }
                    }
                }
            }
            
            if ($selectedHoscode === 'ALL' && (!isset($mapping['hoscode']) || $mapping['hoscode'] === -1)) {
                throw new \Exception("หากเลือก 'ทุกหน่วยบริการ' จำเป็นต้องจับคู่คอลัมน์ รหัสหน่วยบริการ (hoscode)");
            }

            if (!$hasHeaders) {
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
                    (hoscode, hosname, pid, cid, name, lname, sex, birth, hid, addr, check_vhid, nation, typearea, sbp, dbp, risk, result) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
            } elseif ($importType === 'home') {
                $stmtCheckHome = $pdo->prepare("SELECT 1 FROM target_population WHERE hoscode = ? AND hid = ? LIMIT 1");
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
                $stmtInsertPerson = $pdo->prepare("INSERT INTO target_population (cid, hid, pid, first_name, last_name, sex, birth, house_no, moo, sub_district_code, vhid_code, hoscode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE hid=VALUES(hid), pid=VALUES(pid), first_name=VALUES(first_name), last_name=VALUES(last_name), sex=VALUES(sex), birth=VALUES(birth), house_no=VALUES(house_no), moo=VALUES(moo), sub_district_code=VALUES(sub_district_code), vhid_code=VALUES(vhid_code), hoscode=VALUES(hoscode), updated_at=NOW()");
                $stmtUpdateAssignCid = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE target_cid = ?");
                $stmtUpdateDpacCid = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE cid = ?");
            }
            
            if ($selectedHoscode === 'ALL') {
                $allowedHoscodes = array_map(function($code) {
                    return str_pad(trim($code), 5, '0', STR_PAD_LEFT);
                }, array_keys($hc_names));
            } else {
                $allowedHoscodes = [str_pad(trim($selectedHoscode), 5, '0', STR_PAD_LEFT)];
            }
            
            $pdo->beginTransaction();
            
            // ระบบ Memory Caching สำหรับประชากรเดิมใน target_population เพื่อเพิ่มความเร็วในการตรวจสอบข้อมูลซ้ำ
            $existingPersonsByCid = [];
            $existingPersonsByHosPid = [];
            if ($importType === 'person') {
                $allExisting = $pdo->query("SELECT cid, hoscode, pid, hid, house_no, moo, sub_district_code, vhid_code FROM target_population")->fetchAll();
                foreach ($allExisting as $p) {
                    $c = trim($p['cid']);
                    $h = str_pad(trim($p['hoscode']), 5, '0', STR_PAD_LEFT);
                    $pi = ltrim(trim($p['pid']), '0');
                    
                    $existingPersonsByCid[$c] = $p;
                    $existingPersonsByHosPid["{$h}|{$pi}"] = $p;
                }
            }

            $linesImported = 0;
            $insertedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $skippedDetails = [];

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
                
                if (isset($rowVals['pid']) && $rowVals['pid'] !== null) {
                    $rowVals['pid'] = ltrim(trim((string)$rowVals['pid']), '0');
                }
                
                // Get hoscode
                $rowHoscode = $rowVals['hoscode'] !== null ? trim((string)$rowVals['hoscode']) : '';
                if ($rowHoscode === '') {
                    $rowHoscode = $selectedHoscode;
                }
                if (is_numeric($rowHoscode) && strlen($rowHoscode) < 5) {
                    $rowHoscode = str_pad($rowHoscode, 5, '0', STR_PAD_LEFT);
                }
                
                if (!in_array($rowHoscode, $allowedHoscodes)) {
                    $skippedCount++;
                    $skippedDetails[] = "รหัสหน่วยบริการ ($rowHoscode) ไม่ตรงกับสิทธิ์ที่เลือกนำเข้า";
                    continue; // Skip mismatching records
                }
                
                // Import all records including risk = 0 (Normal group) and other risk levels as requested
                // (no longer discarding records based on risk value)
                
                // Clean dates
                $birthDate = $rowVals['birth'] ?? null;
                if ($birthDate) {
                    $birthDate = preg_replace('/[^0-9\-]/', '', $birthDate);
                    if (strlen($birthDate) === 8 && is_numeric($birthDate)) {
                        $birthDate = substr($birthDate, 0, 4) . '-' . substr($birthDate, 4, 2) . '-' . substr($birthDate, 6, 2);
                    }
                }
                
                try {
                    if ($importType === 'dm') {
                        $dateScreen = $rowVals['date_screen'] ?? null;
                        if ($dateScreen) {
                            $dateScreen = preg_replace('/[^0-9\-]/', '', $dateScreen);
                            if (strlen($dateScreen) === 8 && is_numeric($dateScreen)) {
                                $dateScreen = substr($dateScreen, 0, 4) . '-' . substr($dateScreen, 4, 2) . '-' . substr($dateScreen, 6, 2);
                            }
                        }
                        
                        $rawRisk = isset($rowVals['risk']) ? trim((string)$rowVals['risk']) : '';
                        $rawResult = isset($rowVals['result']) ? trim((string)$rowVals['result']) : '';
                        
                        $finalRisk = '0';
                        if ($rawResult !== '') {
                            $finalRisk = translateRiskTextToNumber($rawResult);
                        }
                        if ($finalRisk === '0' && $rawRisk !== '') {
                            $finalRisk = translateRiskTextToNumber($rawRisk);
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
                            $finalRisk,
                            $rowVals['result']
                        ]);
                        $insertedCount++;
                    } elseif ($importType === 'ht') {
                        $rawRisk = isset($rowVals['risk']) ? trim((string)$rowVals['risk']) : '';
                        $rawResult = isset($rowVals['result']) ? trim((string)$rowVals['result']) : '';
                        
                        $finalRisk = '0';
                        if ($rawResult !== '') {
                            $finalRisk = translateRiskTextToNumber($rawResult);
                        }
                        if ($finalRisk === '0' && $rawRisk !== '') {
                            $finalRisk = translateRiskTextToNumber($rawRisk);
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
                            $rowVals['typearea'],
                            ($rowVals['sbp'] !== '' && is_numeric($rowVals['sbp'])) ? (int)$rowVals['sbp'] : null,
                            ($rowVals['dbp'] !== '' && is_numeric($rowVals['dbp'])) ? (int)$rowVals['dbp'] : null,
                            $finalRisk,
                            $rowVals['result']
                        ]);
                        $insertedCount++;
                    } elseif ($importType === 'home') {
                        $lat = ($rowVals['latitude'] !== '' && is_numeric($rowVals['latitude'])) ? (float)$rowVals['latitude'] : null;
                        $lng = ($rowVals['longitude'] !== '' && is_numeric($rowVals['longitude'])) ? (float)$rowVals['longitude'] : null;
                        
                        // Clear lat and lng (set to null) ONLY for hospcode 10957 (โรงพยาบาลตาลสุม) due to corrupted coordinates in the source system
                        if (intval($rowHoscode) === 10957) {
                            $lat = null;
                            $lng = null;
                        }
                        
                        if ($lat == 0 || $lng == 0) {
                            $lat = null;
                            $lng = null;
                        }
                        
                        $vhidCode = $rowVals['vhid_code'];
                        if (empty($vhidCode) || strlen($vhidCode) !== 8) {
                            if (!empty($rowVals['changwat']) && !empty($rowVals['ampur']) && !empty($rowVals['tambon']) && !empty($rowVals['village'])) {
                                $vhidCode = trim($rowVals['changwat']) . trim($rowVals['ampur']) . trim($rowVals['tambon']) . str_pad(trim($rowVals['village']), 2, '0', STR_PAD_LEFT);
                            }
                        }
                        
                        // สำหรับไฟล์นำเข้า Home อนุญาตให้บันทึกลง jhcis_homes ได้เลยโดยตรงโดยไม่ข้าม เพื่อจัดเตรียมพิกัดไว้
                        $stmt->execute([
                            $rowHoscode,
                            $rowVals['hid'],
                            $rowVals['house_no'],
                            $vhidCode,
                            $lat,
                            $lng
                        ]);
                        $insertedCount++;
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

                        // Check if person exists by (hoscode and pid) OR (cid) using memory cache
                        $existing = null;
                        $cleanCid = trim($newCid);
                        $cleanHoscode = str_pad(trim($rowHoscode), 5, '0', STR_PAD_LEFT);
                        $cleanPid = ltrim(trim($pid), '0');

                        if (isset($existingPersonsByCid[$cleanCid])) {
                            $existing = $existingPersonsByCid[$cleanCid];
                        } elseif (isset($existingPersonsByHosPid["{$cleanHoscode}|{$cleanPid}"])) {
                            $existing = $existingPersonsByHosPid["{$cleanHoscode}|{$cleanPid}"];
                        }

                        if ($existing) {
                            $oldCid = $existing['cid'];
                            
                            // Preserve existing address and coordinates from HDC if they already exist
                            $finalHid = !empty($existing['hid']) ? $existing['hid'] : ($rowVals['hid'] ?: null);
                            $finalHouseNo = !empty($existing['house_no']) ? $existing['house_no'] : ($houseNo ?: null);
                            $finalMoo = !empty($existing['moo']) ? $existing['moo'] : $moo;
                            $finalSubDistrict = !empty($existing['sub_district_code']) ? $existing['sub_district_code'] : $subDistrictCode;
                            $finalVhid = !empty($existing['vhid_code']) && $existing['vhid_code'] !== '34180101' ? $existing['vhid_code'] : $checkVhid;

                            $useOldCid = false;
                            if (isValidThaiCitizenIDMOD11($oldCid) && isMockHospitalCID($newCid, $rowHoscode)) {
                                $useOldCid = true;
                            }

                            if ($oldCid !== $newCid && !$useOldCid) {
                                // Check if new CID already exists in database using cache to avoid PRIMARY KEY duplicate violation
                                if (isset($existingPersonsByCid[$cleanCid])) {
                                    $skippedCount++;
                                    $skippedDetails[] = "ข้ามรายชื่อ: CID $newCid ซ้ำซ้อนกับประชากรรายอื่นที่มีอยู่ในระบบแล้ว (เดิมมี CID $oldCid)";
                                    continue;
                                }

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
                                $updatedCount++;
                            } else {
                                // Same CID or we are forcing keeping the old CID: update demographics and preserve address
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
                                    $oldCid // Use old CID to perform update if we are keeping the old CID
                                ]);
                                $updatedCount++;
                            }
                        } else {
                            // นำเข้ารายชื่อ unmasked จาก JHCIS ใหม่ได้ทันที ไม่ว่าจะใช้ delimiter ใด
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
                            $insertedCount++;
                        }

                        // อัปเดตข้อมูลใน Cache Array เพื่อใช้ตรวจสอบแถวถัดๆ ไป
                        $cacheEntry = [
                            'cid' => $newCid,
                            'hoscode' => $rowHoscode,
                            'pid' => $pid,
                            'hid' => $existing ? $finalHid : ($rowVals['hid'] ?: null),
                            'house_no' => $existing ? $finalHouseNo : ($houseNo ?: null),
                            'moo' => $existing ? $finalMoo : $moo,
                            'sub_district_code' => $existing ? $finalSubDistrict : $subDistrictCode,
                            'vhid_code' => $existing ? $finalVhid : $checkVhid,
                        ];
                        $existingPersonsByCid[$cleanCid] = $cacheEntry;
                        $existingPersonsByHosPid["{$cleanHoscode}|{$cleanPid}"] = $cacheEntry;
                    }
                    $linesImported++;
                } catch (\PDOException $e) {
                    $skippedCount++;
                    $errCode = $e->getCode();
                    if ($errCode == 23000 || strpos($e->getMessage(), '1062') !== false) {
                        $skippedDetails[] = "แถวที่ " . ($linesImported + $skippedCount) . ": ข้อมูลเลขประจำตัวประชาชน หรือรหัสซ้ำซ้อนกับระบบ";
                    } else {
                        $skippedDetails[] = "แถวที่ " . ($linesImported + $skippedCount) . ": " . $e->getMessage();
                    }
                }
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
            
            // Generate detailed summary message
            $importSummary = [
                'inserted' => $insertedCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'skippedDetails' => array_values(array_unique($skippedDetails))
            ];
            $message = 'success';
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
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        
        <?php if (!empty($message) && $message === 'success' && isset($importSummary)): ?>
            <!-- Success Modal -->
            <div id="success-modal-overlay" class="modal-overlay" style="display: flex; flex-direction: column; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(13, 44, 84, 0.4); backdrop-filter: blur(4px); z-index: 9999; color: white;">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary); padding: 0; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); width: 90%; max-width: 500px; text-align: left; overflow: hidden; animation: modalPop 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
                    <div style="background: var(--color-green); padding: 30px 24px; text-align: center; color: white;">
                        <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
                            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <h2 style="margin: 0; font-size: 24px; font-weight: bold; color: white;">นำเข้าข้อมูลสำเร็จ!</h2>
                        <p style="margin: 8px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">ระบบได้บันทึกข้อมูลเข้าสู่ฐานข้อมูลเรียบร้อยแล้ว</p>
                    </div>
                    <div style="padding: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--border-color);">
                            <span style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
                                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--color-green);"></div>
                                นำเข้าใหม่สำเร็จ
                            </span>
                            <strong style="color: var(--color-green); font-size: 18px;"><?= number_format($importSummary['inserted']) ?> <span style="font-size: 13px; font-weight: normal; color: var(--text-secondary);">รายการ</span></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--border-color);">
                            <span style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
                                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--color-accent);"></div>
                                อัปเดตข้อมูลเดิม
                            </span>
                            <strong style="color: var(--color-accent); font-size: 18px;"><?= number_format($importSummary['updated']) ?> <span style="font-size: 13px; font-weight: normal; color: var(--text-secondary);">รายการ</span></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 0; <?= $importSummary['skipped'] > 0 ? 'border-bottom: 1px solid var(--border-color);' : '' ?>">
                            <span style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
                                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--color-red);"></div>
                                ข้ามข้อมูล (ไม่ผ่านเงื่อนไข)
                            </span>
                            <strong style="color: var(--color-red); font-size: 18px;"><?= number_format($importSummary['skipped']) ?> <span style="font-size: 13px; font-weight: normal; color: var(--text-secondary);">รายการ</span></strong>
                        </div>
                        
                        <?php if ($importSummary['skipped'] > 0): ?>
                        <div style="margin-top: 16px; background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.1); border-radius: 12px; padding: 16px; max-height: 160px; overflow-y: auto;">
                            <strong style="color: var(--color-red); font-size: 13px; display: block; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                เหตุผลการข้าม (แสดงสูงสุด 5 สาเหตุ):
                            </strong>
                            <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: var(--text-secondary); line-height: 1.5;">
                                <?php 
                                $uniqueReasons = array_slice($importSummary['skippedDetails'], 0, 5);
                                foreach ($uniqueReasons as $reason): ?>
                                    <li style="margin-bottom: 6px;"><?= htmlspecialchars($reason) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (count($importSummary['skippedDetails']) > 5): ?>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 10px; text-align: center; font-style: italic;">และอื่นๆ อีก <?= number_format(count($importSummary['skippedDetails']) - 5) ?> สาเหตุ...</div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div style="margin-top: 24px; display: flex; gap: 12px;">
                            <button type="button" onclick="document.getElementById('success-modal-overlay').style.display='none'" class="btn-giant" style="flex: 1; background: var(--bg-darker); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 12px; cursor: pointer; padding: 14px; font-weight: bold; font-size: 15px; transition: all 0.2s;">ปิดหน้าต่าง</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- STEP 1: Upload File Form -->
            <div class="card-dark">
                <h2 style="border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    นำเข้าข้อมูล HDC & 43 แฟ้มเข้าสู่ระบบ
                </h2>

                <?php if (!empty($error)): ?>
                    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--color-red); color: var(--color-red); padding: 14px 18px; border-radius: 16px; margin-bottom: 20px; font-weight: 700;">
                        ❌ <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data" id="upload-form">
                    <input type="hidden" name="action_upload" value="1">

                    <!-- Select File Type (Grouped HDC vs JHCIS) & Trigger Upload -->
                    <div style="background: var(--bg-card); border-radius: var(--border-radius); padding: 24px; box-shadow: var(--neumorph-flat); margin-bottom: 28px;">
                        <h3 style="color: var(--text-primary); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 800;">
                            เลือกประเภทไฟล์และแหล่งข้อมูลนำเข้าเพื่อเลือกไฟล์ทันที:
                        </h3>
                        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                            กดเลือกประเภทไฟล์ที่ต้องการนำเข้า ระบบจะเปิดหน้าต่างให้เลือกไฟล์ข้อมูล (.csv, .txt) และอัปโหลดตรวจสอบโดยอัตโนมัติ
                        </p>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                            
                            <!-- Group A: HDC Data -->
                            <div style="background: rgba(13, 44, 84, 0.03); padding: 20px; border-radius: 20px; border: 1px solid rgba(13, 44, 84, 0.05); display: flex; flex-direction: column; gap: 14px;">
                                <h4 style="color: var(--color-primary); font-size: 15px; margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px; font-weight: bold;">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    ไฟล์ส่งออกจากระบบ HDC (ข้อมูลคัดกรองมีสิทธิ์ปกปิด)
                                </h4>
                                
                                <div class="radio-card selected" id="label-dm" onclick="selectImport('dm', event)">
                                    <input type="radio" name="import_type" value="dm" checked style="accent-color: var(--color-primary);" onclick="event.stopPropagation()">
                                    <div>
                                        <strong style="display: block; font-size: 14px; color: var(--text-primary);">DMexchange.csv (เบาหวาน)</strong>
                                        <span style="display: block; font-size: 11px; color: var(--text-secondary); margin-top: 4px; line-height: 1.4;">ไฟล์คัดกรองเบาหวานจาก HDC เพื่อเก็บประวัติความเสี่ยงระดับ 1, 2, 5</span>
                                    </div>
                                </div>
                                
                                <div class="radio-card" id="label-ht" onclick="selectImport('ht', event)">
                                    <input type="radio" name="import_type" value="ht" style="accent-color: var(--color-primary);" onclick="event.stopPropagation()">
                                    <div>
                                        <strong style="display: block; font-size: 14px; color: var(--text-primary);">HTexchange.csv (ความดัน)</strong>
                                        <span style="display: block; font-size: 11px; color: var(--text-secondary); margin-top: 4px; line-height: 1.4;">ไฟล์คัดกรองความดันจาก HDC เพื่อเก็บค่า BP และประวัติกลุ่มเสี่ยง</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Group B: JHCIS Data -->
                            <div style="background: rgba(16, 185, 129, 0.03); padding: 20px; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.05); display: flex; flex-direction: column; gap: 14px;">
                                <h4 style="color: var(--color-green); font-size: 15px; margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px; font-weight: bold;">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    ไฟล์ส่งออกจากระบบ JHCIS / 43 แฟ้ม (ข้อมูลจริงไม่ปกปิด)
                                </h4>
                                
                                <div class="radio-card" id="label-person" onclick="selectImport('person', event)">
                                    <input type="radio" name="import_type" value="person" style="accent-color: var(--color-primary);" onclick="event.stopPropagation()">
                                    <div>
                                        <strong style="display: block; font-size: 14px; color: var(--text-primary);">PERSON.csv (ทะเบียนประชากร)</strong>
                                        <span style="display: block; font-size: 11px; color: var(--text-secondary); margin-top: 4px; line-height: 1.4;">ข้อมูลบุคคลจริง (ชื่อ-สกุล และ CID เต็ม) ระบบจะตรวจหาหน่วยบริการอัตโนมัติ</span>
                                    </div>
                                </div>
                                
                                <div class="radio-card" id="label-home" onclick="selectImport('home', event)">
                                    <input type="radio" name="import_type" value="home" style="accent-color: var(--color-primary);" onclick="event.stopPropagation()">
                                    <div>
                                        <strong style="display: block; font-size: 14px; color: var(--text-primary);">HOME.csv (ทะเบียนบ้าน)</strong>
                                        <span style="display: block; font-size: 11px; color: var(--text-secondary); margin-top: 4px; line-height: 1.4;">ข้อมูลบ้าน เลขที่ และพิกัดตำแหน่งบ้านเพื่อบันทึกพิกัดภูมิศาสตร์ (GPS)</span>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>

                    <!-- Hidden file input for auto trigger -->
                    <input type="file" name="csv_file" id="csv_file" style="display: none;" accept=".csv,.txt" required onchange="handleFileSelect(this)">
                </form>
            </div>
        <?php else: ?>
            <!-- STEP 2: Preview & Confirm with Mapping -->
            <?php 
            $detectedHoscode = null;
            $detectedHosname = null;
            if (($previewData['import_type'] === 'person' || $previewData['import_type'] === 'home') && !empty($previewData['sample_rows'])) {
                $firstRow = $previewData['sample_rows'][0];
                if (isset($firstRow['hoscode']) && !empty($firstRow['hoscode'])) {
                    $rawCode = trim($firstRow['hoscode']);
                    if (is_numeric($rawCode) && strlen($rawCode) < 5) {
                        $rawCode = str_pad($rawCode, 5, '0', STR_PAD_LEFT);
                    }
                    if (isset($hc_names[$rawCode])) {
                        $detectedHoscode = $rawCode;
                        $detectedHosname = $hc_names[$rawCode];
                    }
                }
            }
            ?>
            <div class="card-dark">
                <h2 style="border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    ตรวจสอบความถูกต้องและจับคู่คอลัมน์ (Data Preview & Mapping)
                </h2>
                <div style="display: grid; grid-template-columns: repeat(<?= $detectedHoscode ? '4' : '3' ?>, 1fr); gap: 16px; margin-bottom: 24px; font-size: 14px; color: var(--text-secondary);">
                    <div style="background: var(--bg-darker); padding: 12px 16px; border-radius: 8px;">
                        ชื่อไฟล์ดั้งเดิม: <strong style="color: var(--text-primary);"><?= htmlspecialchars($previewData['original_name']) ?></strong>
                    </div>
                    <div style="background: var(--bg-darker); padding: 12px 16px; border-radius: 8px;">
                        ประเภทข้อมูล: <strong style="color: var(--color-accent);"><?= strtoupper($previewData['import_type']) ?></strong>
                    </div>
                    <div style="background: var(--bg-darker); padding: 12px 16px; border-radius: 8px;">
                        รหัสหน่วยบริการอ้างอิง: <strong style="color: var(--text-primary);"><?= htmlspecialchars($previewData['hoscode']) ?> (<?= $previewData['hoscode'] === 'ALL' ? 'ทุกหน่วยบริการ (รวมข้อมูลทั้งหมด)' : htmlspecialchars($hc_names[$previewData['hoscode']] ?? '') ?>)</strong>
                    </div>
                    <?php if ($detectedHoscode): ?>
                    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); padding: 12px 16px; border-radius: 8px;">
                        หน่วยบริการที่ตรวจพบในไฟล์: <strong style="color: var(--color-green);"><?= htmlspecialchars($detectedHoscode) ?> - <?= htmlspecialchars($detectedHosname) ?></strong>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Dynamic Warning Div for Client-side Validation -->
                <div id="critical-warning" style="display: none; background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px;">
                    <strong>⚠️ ข้อควรระวัง:</strong> ยังจับคู่คอลัมน์สำคัญไม่ครบถ้วน ได้แก่: 
                    <strong id="missing-cols-list"></strong> 
                    กรุณาจับคู่คอลัมน์เหล่านั้นให้ถูกต้องก่อนกดยืนยันการนำเข้าข้อมูล
                </div>

                <!-- Main Confirm Form enclosing Mappings -->
                <form action="" method="POST" id="confirm-form">
                    <input type="hidden" name="action_confirm" value="1">
                    <input type="hidden" name="import_type" value="<?= htmlspecialchars($previewData['import_type']) ?>">
                    <input type="hidden" name="hoscode" value="<?= htmlspecialchars($previewData['hoscode']) ?>">
                    <input type="hidden" name="temp_file" value="<?= htmlspecialchars($previewData['temp_file']) ?>">
                    <input type="hidden" name="delimiter" value="<?= htmlspecialchars($previewData['delimiter']) ?>">

                    <h3 style="color: var(--color-accent); margin-bottom: 12px;">1. เลือกจับคู่หัวคอลัมน์จากไฟล์ (Map File Columns to Database)</h3>
                    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;">
                        ระบบระบุความสอดคล้องเบื้องต้นให้โดยอัตโนมัติแล้ว คุณสามารถปรับเปลี่ยนหรือจับคู่คอลัมน์ต่างๆ ให้ถูกต้องตรงตามความต้องการได้
                    </p>
                    <div class="table-responsive" style="margin-bottom: 30px;">
                        <table class="admin-table mapping-table">
                            <thead>
                                <tr>
                                    <th>ช่องข้อมูลที่ระบบต้องการ (Expected Column)</th>
                                    <th>เลือกคอลัมน์ในไฟล์ที่ตรงกัน (Select File Column)</th>
                                    <th style="text-align: center; width: 180px;">สถานะ (Status)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($columnMappings[$previewData['import_type']] as $colKey => $possibleNames): ?>
                                    <?php 
                                    $idx = $previewData['mapping'][$colKey];
                                    $isCrit = in_array($colKey, $criticalColumns[$previewData['import_type']]);
                                    ?>
                                    <tr>
                                        <td style="font-weight: bold; font-size: 13.5px;">
                                            <?= $thaiColNames[$colKey] ?? $colKey ?> 
                                            <?= $isCrit ? '<span style="color: var(--color-red); font-size: 12px; margin-left: 4px;">*จำเป็น</span>' : '' ?>
                                        </td>
                                        <td>
                                            <select id="select-map-<?= $colKey ?>" name="mapping[<?= $colKey ?>]" onchange="onMappingChanged('<?= $colKey ?>')" data-critical="<?= $isCrit ? '1' : '0' ?>" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-darker); color: var(--text-primary); font-size: 13px; font-weight: 500; cursor: pointer; outline: none;">
                                                <option value="-1">-- ไม่ระบุ / ไม่นำเข้า --</option>
                                                <?php foreach ($previewData['raw_headers'] as $hIdx => $hName): ?>
                                                    <option value="<?= $hIdx ?>" <?= $hIdx === $idx ? 'selected' : '' ?>>
                                                        คอลัมน์ที่ <?= $hIdx + 1 ?>: <?= htmlspecialchars($hName) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td style="text-align: center;">
                                            <span id="badge-<?= $colKey ?>" class="badge"></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <h3 style="color: var(--color-accent); margin-bottom: 12px;">2. ตัวอย่างข้อมูลคัดกรอง 5 แถวแรกตามการจับคู่ (First 5 Rows Preview)</h3>
                    <div class="table-responsive" style="margin-bottom: 30px;">
                        <table class="admin-table" style="font-size: 12px;">
                            <thead>
                                <tr>
                                    <?php foreach ($columnMappings[$previewData['import_type']] as $colKey => $names): ?>
                                        <th id="header-prev-<?= $colKey ?>"><?= str_replace(' (', '<br>(', $thaiColNames[$colKey] ?? $colKey) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody id="preview-table-body">
                                <!-- Populated dynamically by javascript -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Forms containing buttons -->
                    <div style="display: flex; gap: 16px; margin-top: 30px;">
                        <button type="button" onclick="cancelUpload()" class="btn-giant" style="flex: 1; background: transparent; border: 2px solid var(--color-red); color: var(--color-red); border-radius: var(--border-radius); cursor: pointer;">
                            ✕ ยกเลิกและอัปโหลดใหม่
                        </button>
                        
                        <button type="submit" id="confirm-import-btn" class="btn-giant btn-giant-primary" style="flex: 1; border-radius: var(--border-radius); cursor: pointer;">
                            💾 ยืนยันการนำเข้าข้อมูล (Confirm Import)
                        </button>
                    </div>
                </form>

                <!-- Hidden Cancel Form -->
                <form action="" method="POST" id="cancel-form" style="display: none;">
                    <input type="hidden" name="action_cancel" value="1">
                    <input type="hidden" name="temp_file" value="<?= htmlspecialchars($previewData['temp_file']) ?>">
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function selectImport(type, event) {
            if (event) event.stopPropagation();

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

            // Immediately trigger file input click
            document.getElementById('csv_file').click();
        }

        function handleFileSelect(input) {
            if (input.files && input.files.length > 0) {
                // Submit form immediately
                document.getElementById('upload-form').submit();
            }
        }

        function cancelUpload() {
            document.getElementById('cancel-form').submit();
        }

        <?php if ($step === 2): ?>
        // Client-side mapping preview logic
        const expectedCols = <?= json_encode(array_keys($columnMappings[$previewData['import_type']])) ?>;
        const rawSampleRows = <?= json_encode($previewData['raw_sample_rows'] ?? []) ?>;
        const thaiColNames = <?= json_encode($thaiColNames) ?>;

        function updateStatusBadge(colKey) {
            const select = document.getElementById('select-map-' + colKey);
            const badge = document.getElementById('badge-' + colKey);
            const isCrit = select.getAttribute('data-critical') === '1';
            const idx = parseInt(select.value);
            
            if (idx !== -1) {
                badge.className = 'badge badge-success';
                badge.textContent = '✅ จับคู่แล้ว';
            } else if (isCrit) {
                badge.className = 'badge badge-danger';
                badge.textContent = '❌ ไม่พบ (บังคับ)';
            } else {
                badge.className = 'badge badge-warning';
                badge.textContent = '⚠️ ไม่มี (เว้นว่าง)';
            }
        }

        function checkMissingCritical() {
            const importType = '<?= $previewData['import_type'] ?>';
            let missing = [];
            
            if (importType === 'person') {
                if (parseInt(document.getElementById('select-map-pid').value) === -1) missing.push('pid');
                if (parseInt(document.getElementById('select-map-cid').value) === -1) missing.push('cid');
                if (parseInt(document.getElementById('select-map-first_name').value) === -1) missing.push('first_name');
                if (parseInt(document.getElementById('select-map-last_name').value) === -1) missing.push('last_name');
            } else if (importType === 'home') {
                if (parseInt(document.getElementById('select-map-hid').value) === -1) missing.push('hid');
                
                const hasVhid = parseInt(document.getElementById('select-map-vhid_code').value) !== -1;
                const hasParts = parseInt(document.getElementById('select-map-village').value) !== -1 &&
                                 parseInt(document.getElementById('select-map-tambon').value) !== -1 &&
                                 parseInt(document.getElementById('select-map-ampur').value) !== -1 &&
                                 parseInt(document.getElementById('select-map-changwat').value) !== -1;
                if (!hasVhid && !hasParts) {
                    missing.push('vhid_code');
                }
            } else if (importType === 'dm') {
                if (parseInt(document.getElementById('select-map-pid').value) === -1) missing.push('pid');
            } else if (importType === 'ht') {
                if (parseInt(document.getElementById('select-map-pid').value) === -1) missing.push('pid');
            }
            
            // Check hoscode mapping if selected hospital is 'ALL'
            const selectedHoscodeVal = document.querySelector('input[name="hoscode"]') ? document.querySelector('input[name="hoscode"]').value : '';
            const selectHoscodeMap = document.getElementById('select-map-hoscode');
            if (selectedHoscodeVal === 'ALL' && selectHoscodeMap && parseInt(selectHoscodeMap.value) === -1) {
                missing.push('hoscode');
            }
            
            const warningDiv = document.getElementById('critical-warning');
            const confirmBtn = document.getElementById('confirm-import-btn');
            
            if (missing.length > 0) {
                warningDiv.style.display = 'block';
                confirmBtn.disabled = true;
                confirmBtn.style.opacity = '0.5';
                confirmBtn.style.cursor = 'not-allowed';
                
                const names = missing.map(c => thaiColNames[c] || c);
                document.getElementById('missing-cols-list').textContent = names.join(', ');
            } else {
                warningDiv.style.display = 'none';
                confirmBtn.disabled = false;
                confirmBtn.style.opacity = '1';
                confirmBtn.style.cursor = 'pointer';
            }
        }

        function renderPreviewTable() {
            const tbody = document.getElementById('preview-table-body');
            tbody.innerHTML = '';
            
            if (rawSampleRows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="' + expectedCols.length + '" style="text-align: center;">ไม่มีข้อมูลแสดงผลตัวอย่าง</td></tr>';
                return;
            }
            
            rawSampleRows.forEach(row => {
                const tr = document.createElement('tr');
                expectedCols.forEach(colKey => {
                    const select = document.getElementById('select-map-' + colKey);
                    const idx = select ? parseInt(select.value) : -1;
                    const val = (idx !== -1 && row[idx] !== undefined) ? row[idx] : null;
                    
                    const td = document.createElement('td');
                    if (val !== null) {
                        td.textContent = val;
                        const isMaskedVal = (colKey === 'cid' || colKey === 'name' || colKey === 'lname' || colKey === 'first_name' || colKey === 'last_name');
                        if (isMaskedVal && val.includes('*')) {
                            td.style.color = 'var(--color-yellow)';
                            td.style.fontFamily = 'monospace';
                        }
                    } else {
                        td.innerHTML = '<span style="color: #666;">-</span>';
                    }
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
        }

        function onMappingChanged(colKey) {
            updateStatusBadge(colKey);
            checkMissingCritical();
            
            // If vhid_code or parts changed for type home, update their statuses/warnings dynamically
            const importType = '<?= $previewData['import_type'] ?>';
            if (importType === 'home') {
                updateStatusBadge('vhid_code');
                updateStatusBadge('village');
                updateStatusBadge('tambon');
                updateStatusBadge('ampur');
                updateStatusBadge('changwat');
            }
            
            renderPreviewTable();
        }

        // Initialize badges, critical warning, and preview table
        expectedCols.forEach(updateStatusBadge);
        checkMissingCritical();
        renderPreviewTable();
        <?php endif; ?>
    </script>

    <!-- Progress Overlay Modal -->
    <div id="progress-overlay" class="modal-overlay" style="display: none; flex-direction: column; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(13, 44, 84, 0.4); backdrop-filter: blur(4px); z-index: 9999; color: white;">
        <div class="modal-content" style="background: rgba(13, 44, 84, 0.9); backdrop-filter: blur(10px); color: white; padding: 40px; border-radius: 24px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); width: 90%; max-width: 450px; text-align: center;">
            <div class="spinner" style="margin: 0 auto 24px auto; width: 50px; height: 50px; border: 4px solid rgba(255,255,255,0.1); border-top-color: var(--color-accent); border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <h3 id="progress-status" style="margin: 0 0 10px 0; font-size: 18px; font-weight: bold; color: white;">กำลังประมวลผล...</h3>
            <p id="progress-substatus" style="margin: 0 0 24px 0; font-size: 13px; color: rgba(255,255,255,0.6);">กรุณารอสักครู่ ห้ามปิดหรือรีเฟรชหน้าต่างนี้</p>
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
                const displayProgress = Math.floor(progress * 92); 
                
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

        // Bind progress overlay to forms
        document.addEventListener('DOMContentLoaded', () => {
            const uploadForm = document.getElementById('upload-form');
            if (uploadForm) {
                uploadForm.addEventListener('submit', () => {
                    runProgressAnimation(4000, [
                        "กำลังอัปโหลดไฟล์ขึ้นสู่เซิร์ฟเวอร์...",
                        "กำลังเชื่อมต่อและเปิดไฟล์...",
                        "กำลังตรวจสอบโครงสร้างคอลัมน์...",
                        "กำลังจัดเรียงข้อมูลพรีวิวสำหรับคุณ..."
                    ]);
                });
            }

            const confirmForm = document.getElementById('confirm-form');
            if (confirmForm) {
                confirmForm.addEventListener('submit', () => {
                    runProgressAnimation(6000, [
                        "กำลังดึงไฟล์ข้อมูลจากระบบสำรอง...",
                        "กำลังวิเคราะห์ข้อมูลรายแถว...",
                        "กำลังเริ่มเชื่อมต่อบันทึกข้อมูล...",
                        "กำลังล้างตารางพักเก่าลงฐานข้อมูลใหม่...",
                        "กำลังอัปเดตข้อมูลทะเบียนบ้านและบุคคล...",
                        "กำลังจัดเก็บประวัติข้อมูลลงฐานข้อมูล..."
                    ]);
                });
            }
        });
    </script>
</body>
</html>