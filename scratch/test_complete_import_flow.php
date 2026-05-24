<?php
// scratch/test_complete_import_flow.php
// Testing the full JHCIS PERSON import + HDC masked HT exchange import + ETL matching & risk mapping logic.

require_once __DIR__ . '/../config/db.php';

echo "=== Test Scenario: Full Import and ETL Consolidation ===\n";

// 1. Clean up old test data
$pdo->query("DELETE FROM target_population WHERE pid = 888 AND hoscode = '03752'");
$pdo->query("DELETE FROM staging_hdc_ht WHERE pid = 888 AND hoscode = '03752'");
echo "Cleaned up old test data.\n";

// 2. Mock JHCIS PERSON CSV file (with real CID and name)
$personCsv = "hcode,pid,cid,first_name,last_name,sex,birth,hid,house_no,vhid_code,typearea\n";
$personCsv .= "03752,888,1111111111188,สมชาย,ทดสอบระบบ,1,1980-05-15,888,15 ม.1,34180201,1\n";

// We will write this mock CSV to a temp file
$tempPersonFile = __DIR__ . '/temp_person_test.csv';
file_put_contents($tempPersonFile, $personCsv);
echo "Created mock PERSON CSV file.\n";

// Parse mock PERSON file using our logic
$handle = fopen($tempPersonFile, 'r');
$headers = fgetcsv($handle, 1000, ',');

$expectedPersonCols = [
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
];

function getColIdx($headers, $possibleNames) {
    foreach ($headers as $idx => $header) {
        $headerClean = strtolower(trim($header));
        foreach ($possibleNames as $name) {
            if ($headerClean === strtolower($name)) return $idx;
        }
    }
    return -1;
}

$mapping = [];
foreach ($expectedPersonCols as $colKey => $names) {
    $mapping[$colKey] = getColIdx($headers, $names);
}

// Perform INSERT ON DUPLICATE KEY UPDATE in target_population
$stmt = $pdo->prepare("
    INSERT INTO target_population 
    (cid, hid, pid, first_name, last_name, sex, birth, house_no, moo, sub_district_code, vhid_code, hoscode)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
      hid = VALUES(hid),
      pid = VALUES(pid),
      first_name = VALUES(first_name),
      last_name = VALUES(last_name),
      sex = VALUES(sex),
      birth = VALUES(birth),
      house_no = VALUES(house_no),
      moo = VALUES(moo),
      sub_district_code = VALUES(sub_district_code),
      vhid_code = VALUES(vhid_code),
      hoscode = VALUES(hoscode),
      updated_at = NOW()
");

while (($row = fgetcsv($handle, 1000, ',')) !== false) {
    if (empty(array_filter($row))) continue;
    $rowVals = [];
    foreach ($expectedPersonCols as $colKey => $names) {
        $idx = $mapping[$colKey];
        $rowVals[$colKey] = ($idx !== -1 && isset($row[$idx])) ? trim($row[$idx]) : null;
    }
    
    $checkVhid = $rowVals['vhid_code'];
    $moo = (int)substr($checkVhid, 6, 2);
    $subDistrictCode = substr($checkVhid, 0, 6);
    
    $stmt->execute([
        $rowVals['cid'],
        $rowVals['hid'],
        $rowVals['pid'],
        $rowVals['first_name'],
        $rowVals['last_name'],
        $rowVals['sex'],
        $rowVals['birth'],
        $rowVals['house_no'],
        $moo,
        $subDistrictCode,
        $checkVhid,
        $rowVals['hoscode']
    ]);
}
fclose($handle);
unlink($tempPersonFile);
echo "Imported PERSON to target_population.\n";

// Verify JHCIS PERSON record
$verifyPerson = $pdo->query("SELECT * FROM target_population WHERE pid = 888 AND hoscode = '03752'")->fetch();
echo "Verified target_population:\n";
echo "  CID: " . $verifyPerson['cid'] . " | Name: " . $verifyPerson['first_name'] . " " . $verifyPerson['last_name'] . "\n";


// 3. Mock HDC HT CSV file (with masked CID and masked names, SBP=135, DBP=85, risk=1)
$htCsv = "hoscode,hosname,pid,cid,name,lname,sex,birth,hid,addr,check_vhic,nation,typearea,sbp,dbp,risk\n";
$htCsv .= "03752,รพ.สต.สำโรง,888,11111111*****,สม***,ทด***,1,1980-05-15,888,15 ม.1,34180201,099,1,135,85,1\n";

$tempHtFile = __DIR__ . '/temp_ht_test.csv';
file_put_contents($tempHtFile, $htCsv);
echo "Created mock HDC HT CSV file.\n";

// Parse mock HT file and insert into staging_hdc_ht
$handle = fopen($tempHtFile, 'r');
$headers = fgetcsv($handle, 1000, ',');

$expectedHtCols = [
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
];

$mappingHt = [];
foreach ($expectedHtCols as $colKey => $names) {
    $mappingHt[$colKey] = getColIdx($headers, $names);
}

$insertStgHt = $pdo->prepare("
    INSERT INTO staging_hdc_ht 
    (hoscode, hosname, pid, cid, name, lname, sex, birth, hid, addr, check_vhid, nation, typearea, sbp, dbp, risk) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

while (($row = fgetcsv($handle, 1000, ',')) !== false) {
    if (empty(array_filter($row))) continue;
    $rowVals = [];
    foreach ($expectedHtCols as $colKey => $names) {
        $idx = $mappingHt[$colKey];
        $rowVals[$colKey] = ($idx !== -1 && isset($row[$idx])) ? trim($row[$idx]) : null;
    }
    
    $insertStgHt->execute([
        $rowVals['hoscode'],
        $rowVals['hosname'],
        $rowVals['pid'],
        $rowVals['cid'],
        $rowVals['name'],
        $rowVals['lname'],
        $rowVals['sex'],
        $rowVals['birth'],
        $rowVals['hid'],
        $rowVals['addr'],
        $rowVals['check_vhid'],
        $rowVals['nation'],
        $rowVals['typearea'],
        $rowVals['sbp'],
        $rowVals['dbp'],
        $rowVals['risk']
    ]);
}
fclose($handle);
unlink($tempHtFile);
echo "Imported HT exchange records to staging_hdc_ht.\n";


// 4. Run process_etl.php logic for this mock patient
// We will simulate process_etl.php execution for the masked CID: '11111111*****'
$testCid = '11111111*****';

$latMin = 15.3800; $latMax = 15.4800; $lngMin = 104.9200; $lngMax = 105.0800;

// Read HT staging record
$stmtHt = $pdo->prepare("SELECT * FROM staging_hdc_ht WHERE cid = ? ORDER BY staging_id DESC LIMIT 1");
$stmtHt->execute([$testCid]);
$htData = $stmtHt->fetch();

$dmData = false; // no DM record

$source = $htData;
$firstName = $source['name'];
$lastName = $source['lname'];
$sex = $source['sex'];
$birth = $source['birth'];
$hid = $source['hid'];
$addr = $source['addr'];
$checkVhid = $source['check_vhid'];
$pid = $source['pid'];
$hoscode = $source['hoscode'];

$houseNo = $addr;
$moo = 1;
$subDistrictCode = '341802';

$dmRisk = null;
$htRisk = trim($htData['risk']);

$needScreenDm = true;
$needScreenHt = true;
if ($htRisk === '5') {
    $needScreenHt = false;
}

// Map baseline health_status_origin
if ($htRisk === '2') {
    $healthStatusOrigin = 'HIGH_RISK';
} elseif ($htRisk === '1') {
    $healthStatusOrigin = 'HT_ONLY'; // only HT risk is 1, so HT_ONLY
} else {
    $healthStatusOrigin = 'NORMAL';
}

echo "Running Matching Logic...\n";
$exists = false;
if ($hoscode && $pid) {
    $checkStmt = $pdo->prepare("SELECT cid, first_name, last_name, house_no, moo, sub_district_code, vhid_code, latitude, longitude FROM target_population WHERE hoscode = ? AND pid = ?");
    $checkStmt->execute([$hoscode, $pid]);
    $exists = $checkStmt->fetch();
}

if ($exists) {
    echo "  MATCH FOUND! Matched to real CID: " . $exists['cid'] . " (" . $exists['first_name'] . " " . $exists['last_name'] . ")\n";
    
    $realCid = $exists['cid'];
    $lat = $exists['latitude'] ?: ($latMin + mt_rand() / mt_getrandmax() * ($latMax - $latMin));
    $lng = $exists['longitude'] ?: ($lngMin + mt_rand() / mt_getrandmax() * ($lngMax - $lngMin));

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
    echo "  Updated target_population record.\n";
} else {
    echo "  NO MATCH FOUND. Inserting new record.\n";
}

// 5. Verify final status of target_population
$finalPerson = $pdo->query("SELECT * FROM target_population WHERE pid = 888 AND hoscode = '03752'")->fetch();
echo "=== Final target_population details ===\n";
echo "  CID: " . $finalPerson['cid'] . " (Should be real: 1111111111188)\n";
echo "  Name: " . $finalPerson['first_name'] . " " . $finalPerson['last_name'] . " (Should be real: สมชาย ทดสอบระบบ)\n";
echo "  Health Status Origin: " . $finalPerson['health_status_origin'] . " (Should be HT_ONLY)\n";
echo "  Need DM Screen: " . $finalPerson['need_screen_dm'] . " (Should be 1)\n";
echo "  Need HT Screen: " . $finalPerson['need_screen_ht'] . " (Should be 1)\n";

if ($finalPerson['cid'] === '1111111111188' && $finalPerson['first_name'] === 'สมชาย' && $finalPerson['health_status_origin'] === 'HT_ONLY') {
    echo "\nTEST PASSED SUCCESSFULLY! Real demographics were perfectly preserved, and risk levels were mapped correctly!\n";
} else {
    echo "\nTEST FAILED! Check logic!\n";
}

// Clean up
$pdo->query("DELETE FROM target_population WHERE pid = 888 AND hoscode = '03752'");
$pdo->query("DELETE FROM staging_hdc_ht WHERE pid = 888 AND hoscode = '03752'");
echo "Cleaned up database.\n";
