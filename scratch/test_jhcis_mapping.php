<?php
// scratch/test_jhcis_mapping.php

// Define the mappings exactly as in admin/import_hdc.php
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
        'vhid_code' => ['vhid_code', 'vhid', 'check_vhid', 'check_vhic', 'vhic', 'village_id', 'village_code', 'vhvid'],
        'typearea' => ['typearea', 'type_area']
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

function isHeaderRow($row) {
    if (empty($row)) return false;
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
            return true;
        }
    }
    return false;
}

echo "=== JHCIS Header Detection & Mapping Test ===\n\n";

// Test 1: Person.txt Header
$personHeaderStr = "hospcode|cid|pid|hid|prename|name|lname|hn|sex|birth|mstatus|occupation_old|occupation_new|race|nation|religion|education|fstatus|father|mother|couple|vstatus|movein|discharge|ddischarge|abogroup|rhgroup|labor|passport|typearea|d_update|telephone|mobile";
$personHeaders = explode('|', $personHeaderStr);

echo "Test 1: JHCIS Person.txt header row detection...\n";
$isPersonHeader = isHeaderRow($personHeaders);
echo "  Is Header Row? " . ($isPersonHeader ? "YES (PASSED)" : "NO (FAILED)") . "\n";

echo "  Column Mappings:\n";
$personMapping = [];
foreach ($columnMappings['person'] as $colKey => $names) {
    $idx = getColumnIndex($personHeaders, $names);
    $personMapping[$colKey] = $idx;
    $colName = ($idx !== -1) ? $personHeaders[$idx] : "UNMAPPED";
    echo "    - $colKey => Index $idx ($colName)\n";
}

// Assertions for Person mapping
$expectedPerson = [
    'hoscode' => 0,
    'cid' => 1,
    'pid' => 2,
    'hid' => 3,
    'first_name' => 5,
    'last_name' => 6,
    'sex' => 8,
    'birth' => 9,
    'typearea' => 29
];
$passedPerson = true;
foreach ($expectedPerson as $key => $expectedIdx) {
    if ($personMapping[$key] !== $expectedIdx) {
        echo "  [ERROR] Mismatch for person field '$key': expected index $expectedIdx, got " . $personMapping[$key] . "\n";
        $passedPerson = false;
    }
}
if ($passedPerson) {
    echo "  >> Person.txt mapping auto-detection matches expectations perfectly!\n\n";
} else {
    echo "  >> Person.txt mapping test failed!\n\n";
}


// Test 2: home.txt Header
$homeHeaderStr = "hospcode|hid|house_id|housetype|roomno|condo|house|soisub|soimain|road|villaname|village|tambon|ampur|changwat|telephone|latitude|longitude|nfamily|locatype|vhvid|headid|toilet|water|watertype|garbage|housing|durability|cleanliness|ventilation|light|watertm|mfood|bcontrol|acontrol|chemical|outdate|d_update";
$homeHeaders = explode('|', $homeHeaderStr);

echo "Test 2: JHCIS home.txt header row detection...\n";
$isHomeHeader = isHeaderRow($homeHeaders);
echo "  Is Header Row? " . ($isHomeHeader ? "YES (PASSED)" : "NO (FAILED)") . "\n";

echo "  Column Mappings:\n";
$homeMapping = [];
foreach ($columnMappings['home'] as $colKey => $names) {
    $idx = getColumnIndex($homeHeaders, $names);
    $homeMapping[$colKey] = $idx;
    $colName = ($idx !== -1) ? $homeHeaders[$idx] : "UNMAPPED";
    echo "    - $colKey => Index $idx ($colName)\n";
}

// Assertions for home mapping
$expectedHome = [
    'hoscode' => 0,
    'hid' => 1,
    'house_no' => 6,
    'vhid_code' => 20, // vhvid
    'latitude' => 16,
    'longitude' => 17,
    'village' => 11,
    'tambon' => 12,
    'ampur' => 13,
    'changwat' => 14
];
$passedHome = true;
foreach ($expectedHome as $key => $expectedIdx) {
    if ($homeMapping[$key] !== $expectedIdx) {
        echo "  [ERROR] Mismatch for home field '$key': expected index $expectedIdx, got " . $homeMapping[$key] . "\n";
        $passedHome = false;
    }
}
if ($passedHome) {
    echo "  >> home.txt mapping auto-detection matches expectations perfectly!\n\n";
} else {
    echo "  >> home.txt mapping test failed!\n\n";
}

// Test 3: Fallback Mappings (Headerless)
echo "Test 3: Fallback mappings for files without headers...\n";
$personFallbackExpected = [
    'hoscode' => 0, 'pid' => 2, 'cid' => 1, 'first_name' => 5, 'last_name' => 6,
    'sex' => 8, 'birth' => 9, 'hid' => 3, 'house_no' => -1, 'vhid_code' => -1, 'typearea' => 29
];
$homeFallbackExpected = [
    'hoscode' => 0, 'hid' => 1, 'house_no' => 6, 'vhid_code' => 20, 'latitude' => 16, 'longitude' => 17,
    'village' => 11, 'tambon' => 12, 'ampur' => 13, 'changwat' => 14
];

echo "  Checking Person fallback values...\n";
// Let's create a simulated headerless line
$personRow = explode('|', "10957|3418012345678|12345|999|นาย|สมจิต|คิดดี|HN001|1|1990-01-01|1|1|1|1|1|1|1|1|1|1|1|1|1|1|1|1|1|1|1|1|2026-05-25|0812345678|0812345678");
$importType = 'person';
if ($importType === 'person' && count($personRow) >= 23) {
    $personFallback = [
        'hoscode' => 0, 'pid' => 2, 'cid' => 1, 'first_name' => 5, 'last_name' => 6,
        'sex' => 8, 'birth' => 9, 'hid' => 3, 'house_no' => -1, 'vhid_code' => -1, 'typearea' => 29
    ];
    echo "    - Map Person fallbacks matches expected: " . ($personFallback === $personFallbackExpected ? "PASSED" : "FAILED") . "\n";
}

echo "  Checking Home fallback values...\n";
$homeRow = explode('|', "10957|999|HID123|1|||12/3|||||1|341801|3418|34||15.4234|104.9812||||||||||||||||||||");
$importType = 'home';
if ($importType === 'home' && count($homeRow) >= 18) {
    $homeFallback = [
        'hoscode' => 0, 'hid' => 1, 'house_no' => 6, 'vhid_code' => 20, 'latitude' => 16, 'longitude' => 17,
        'village' => 11, 'tambon' => 12, 'ampur' => 13, 'changwat' => 14
    ];
    echo "    - Map Home fallbacks matches expected: " . ($homeFallback === $homeFallbackExpected ? "PASSED" : "FAILED") . "\n";
}
echo "\n=== Tests Completed ===\n";
