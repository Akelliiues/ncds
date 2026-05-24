<?php
// admin/hdc_list.php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$message = '';
$diseaseType = $_GET['type'] ?? 'DM';
$riskFilter = $_GET['risk'] ?? 'all';

// Process Enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll_dpac') {
    $cids = $_POST['cids'] ?? [];
    if (!empty($cids)) {
        $budgetYear = 2026;
        $success = 0;
        $pdo->beginTransaction();
        try {
            $checkStmt = $pdo->prepare("SELECT enrollment_id FROM dpac_enrollments WHERE cid = ? AND budget_year = ? AND status = 'active'");
            $insertStmt = $pdo->prepare("INSERT INTO dpac_enrollments (cid, budget_year, risk_type) VALUES (?, ?, ?)");
            
            // We also need to ensure they exist in target_population. We can pull from staging.
            $stagingStmt = $diseaseType === 'DM' 
                ? $pdo->prepare("SELECT name, lname, sex, birth FROM staging_hdc_dm WHERE cid = ? LIMIT 1")
                : $pdo->prepare("SELECT name, lname, sex, birth FROM staging_hdc_ht WHERE cid = ? LIMIT 1");
                
            $targetCheckStmt = $pdo->prepare("SELECT cid FROM target_population WHERE cid = ?");
            $targetInsertStmt = $pdo->prepare("INSERT INTO target_population (cid, first_name, last_name, sex, birth) VALUES (?, ?, ?, ?, ?)");

            foreach ($cids as $cid) {
                // Check if already enrolled
                $checkStmt->execute([$cid, $budgetYear]);
                if ($checkStmt->rowCount() > 0) continue;
                
                // Ensure in target_population
                $targetCheckStmt->execute([$cid]);
                if ($targetCheckStmt->rowCount() == 0) {
                    $stagingStmt->execute([$cid]);
                    $stg = $stagingStmt->fetch();
                    if ($stg) {
                        $targetInsertStmt->execute([$cid, $stg['name'], $stg['lname'], $stg['sex'], $stg['birth']]);
                    }
                }

                $insertStmt->execute([$cid, $budgetYear, $diseaseType]);
                $success++;
            }
            $pdo->commit();
            $message = "นำเข้าโครงการ DPAC สำเร็จ $success รายการ";
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Hospital list
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

$tambons = [
    '341801' => 'ตำบลตาลสุม',
    '341802' => 'ตำบลสำโรง',
    '341803' => 'ตำบลจิกเทิง',
    '341804' => 'ตำบลหนองกุง',
    '341805' => 'ตำบลนาคาย',
    '341806' => 'ตำบลคำหว้า'
];

function get_village_only_name($vhid_code, $moo) {
    $tambon = substr($vhid_code, 0, 6);
    $moo = intval($moo);
    
    $villages = [
        '341801' => [
            1 => 'บ้านม่วงโคน', 2 => 'บ้านดอนรังกา', 3 => 'บ้านนาห้วยแคน', 4 => 'บ้านดอนพันชาด', 5 => 'บ้านนามน',
            6 => 'บ้านดอนตะลี', 7 => 'บ้านปากห้วย', 8 => 'บ้านโนนค้อ', 9 => 'บ้านแก่งกบ', 10 => 'บ้านนามน',
            11 => 'บ้านตาลสุม', 12 => 'บ้านคำไม้ตาย', 13 => 'บ้านปากเซ', 14 => 'บ้านโนนสวรรค์', 15 => 'บ้านทุ่งเจริญ'
        ],
        '341802' => [
            1 => 'บ้านสำโรงใหญ่', 2 => 'บ้านสำโรงกลาง', 3 => 'บ้านนาโพธิ์', 4 => 'บ้านสำโรงใต้', 5 => 'บ้านทรายมูลเหนือ',
            6 => 'บ้านทรายมูลใต้', 7 => 'บ้านหนองบัว', 8 => 'บ้านทุ่งเจริญ'
        ],
        '341803' => [
            1 => 'บ้านจิกเทิง', 2 => 'บ้านจิกลุ่ม', 3 => 'บ้านเชียงแก้ว', 4 => 'บ้านเชียงแก้ว', 5 => 'บ้านดอนโด่',
            6 => 'บ้านดอนยูง', 7 => 'บ้านค้อ', 8 => 'บ้านดอนแป้นลม', 9 => 'บ้านสร้างคำ'
        ],
        '341804' => [
            1 => 'บ้านหนองกุงใหญ่', 2 => 'บ้านหนองกุงน้อย', 3 => 'บ้านคำแคน', 4 => 'บ้านสร้างแสง', 5 => 'บ้านคำเตยใต้',
            6 => 'บ้านสร้างหว้า', 7 => 'บ้านคำเตยเหนือ', 8 => 'บ้านสร้างหว้าพัฒนา'
        ],
        '341805' => [
            1 => 'บ้านนาคาย', 2 => 'บ้านโนนจิก', 3 => 'บ้านหนองเป็ด', 4 => 'บ้านโนนยาง', 5 => 'บ้านดอนขวาง',
            6 => 'บ้านดอนหวาย', 7 => 'บ้านโคกคล้าย', 8 => 'บ้านคำหนามแท่ง', 9 => 'บ้านคำผักหนอก', 10 => 'บ้านคำฮี',
            11 => 'บ้านห่องแดง', 12 => 'บ้านโนนสำราญ', 13 => 'บ้านโนนเจริญ'
        ],
        '341806' => [
            1 => 'บ้านคำหว้า', 2 => 'บ้านคำหว้า', 3 => 'บ้านห้วยดู่', 4 => 'บ้านนาทมเหนือ', 5 => 'บ้านไฮหย่อง',
            6 => 'บ้านนาทมใต้'
        ]
    ];

    return $villages[$tambon][$moo] ?? "หมู่ที่ {$moo}";
}

// Mapping of hospital codes (hoscode) to tambon and villages (moo & name)
$hoscode_villages = [
    '10957' => [
        'tambon' => '341801',
        'villages' => [
            1 => 'บ้านม่วงโคน', 2 => 'บ้านดอนรังกา', 3 => 'บ้านนาห้วยแคน (เขตเทศบาล)',
            5 => 'บ้านนามน (เขตเทศบาล)', 10 => 'บ้านนามน (เขตเทศบาล)',
            11 => 'บ้านตาลสุม (เขตเทศบาล)', 12 => 'บ้านคำไม้ตาย'
        ]
    ],

    '03751' => [
        'tambon' => '341801',
        'villages' => [
            4 => 'บ้านดอนพันชาด', 6 => 'บ้านดอนตะลี', 7 => 'บ้านปากห้วย', 8 => 'บ้านโนนค้อ',
            9 => 'บ้านแก่งกบ', 13 => 'บ้านปากเซ', 14 => 'บ้านโนนสวรรค์', 15 => 'บ้านทุ่งเจริญ'
        ]
    ],
    '03752' => [
        'tambon' => '341802',
        'villages' => [
            1 => 'บ้านสำโรงใหญ่', 2 => 'บ้านสำโรงกลาง', 3 => 'บ้านนาโพธิ์', 4 => 'บ้านสำโรงใต้',
            5 => 'บ้านทรายมูลเหนือ', 6 => 'บ้านทรายมูลใต้', 7 => 'บ้านหนองบัว', 8 => 'บ้านทุ่งเจริญ'
        ]
    ],
    '03753' => [
        'tambon' => '341803',
        'villages' => [
            1 => 'บ้านจิกเทิง', 2 => 'บ้านจิกลุ่ม', 3 => 'บ้านเชียงแก้ว', 4 => 'บ้านเชียงแก้ว',
            5 => 'บ้านดอนโด่ (บ้านดอนโต)', 6 => 'บ้านดอนยูง', 7 => 'บ้านค้อ', 8 => 'บ้านดอนแป้นลม', 9 => 'บ้านสร้างคำ'
        ]
    ],
    '03754' => [
        'tambon' => '341804',
        'villages' => [
            1 => 'บ้านหนองกุงใหญ่', 2 => 'บ้านหนองกุงน้อย', 3 => 'บ้านคำแคน', 4 => 'บ้านสร้างแสง',
            5 => 'บ้านคำเตยใต้', 6 => 'บ้านสร้างหว้า', 7 => 'บ้านคำเตยเหนือ', 8 => 'บ้านสร้างหว้าพัฒนา'
        ]
    ],
    '03755' => [
        'tambon' => '341805',
        'villages' => [
            1 => 'บ้านนาคาย', 2 => 'บ้านโนนจิก', 3 => 'บ้านหนองเป็ด', 4 => 'บ้านโนนยาง', 5 => 'บ้านดอนขวาง',
            6 => 'บ้านดอนหวาย'
        ]
    ],
    '03756' => [
        'tambon' => '341805',
        'villages' => [
            7 => 'บ้านโคกคล้าย', 8 => 'บ้านคำหนามแท่ง', 9 => 'บ้านคำผักหนอก', 10 => 'บ้านคำฮี',
            11 => 'บ้านห่องแดง', 12 => 'บ้านโนนสำราญ', 13 => 'บ้านโนนเจริญ'
        ]
    ],
    '03757' => [
        'tambon' => '341806',
        'villages' => [
            1 => 'บ้านคำหว้า', 2 => 'บ้านคำหว้า', 3 => 'บ้านห้วยดู่', 4 => 'บ้านนาทมเหนือ',
            5 => 'บ้านไฮหย่อง', 6 => 'บ้านนาทมใต้'
        ]
    ]
];

// Fetch Data
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$table = $diseaseType === 'DM' ? 'staging_hdc_dm' : 'staging_hdc_ht';
$whereClauses = [];
$params = [];

if ($riskFilter !== 'all') {
    $whereClauses[] = "risk = ?";
    $params[] = $riskFilter;
}

$filter_hoscode = $_GET['hoscode'] ?? '';
$filter_tambon = $_GET['tambon'] ?? '';
$filter_vhid = $_GET['vhid'] ?? ''; // Selected village ID (check_vhid)

if ($admin_hoscode !== null) {
    $filter_hoscode = $admin_hoscode;
}

// If hoscode is selected, clear tambon to enforce "no tambon menu" query conditions
if (!empty($filter_hoscode)) {
    $filter_tambon = '';
}

if ($filter_hoscode) {
    $hoscodes = [$filter_hoscode];
} else {
    $hoscodes = ['10957', '03751', '03752', '03753', '03754', '03755', '03756', '03757'];
}
$inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
$whereClauses[] = "hoscode IN ($inPlaceholders)";
$params = array_merge($params, $hoscodes);

if ($filter_vhid) {
    $selected_moo = intval(substr($filter_vhid, 6, 2));
    $moo_str = sprintf('%02d', $selected_moo);
    $whereClauses[] = "RIGHT(check_vhid, 2) = ?";
    $params[] = $moo_str;
    
    // If hoscode is not selected, restrict check_vhid by the tambon prefix of the selected village
    if (empty($filter_hoscode)) {
        $village_tambon = substr($filter_vhid, 0, 6);
        $whereClauses[] = "check_vhid LIKE ?";
        $params[] = $village_tambon . '%';
    }
} elseif ($filter_tambon) {
    $whereClauses[] = "check_vhid LIKE ?";
    $params[] = $filter_tambon . '%';
}

$whereClause = "";
if (!empty($whereClauses)) {
    $whereClause = "WHERE " . implode(" AND ", $whereClauses);
}

$stmt = $pdo->prepare("SELECT * FROM $table $whereClause ORDER BY name ASC LIMIT 1000");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Get unique risks for tabs
$riskWhere = "WHERE risk IS NOT NULL AND risk != ''";
$riskParams = [];
if ($admin_hoscode) {
    $hoscodes = [$admin_hoscode];
} else {
    $hoscodes = ['10957', '03751', '03752', '03753', '03754', '03755', '03756', '03757'];
}
$inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
$riskWhere .= " AND hoscode IN ($inPlaceholders)";
$riskParams = $hoscodes;
$riskStmt = $pdo->prepare("SELECT DISTINCT risk FROM $table $riskWhere");
$riskStmt->execute($riskParams);
$availableRisks = $riskStmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการข้อมูลจาก HDC - NCDs Prevention Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .tabs {
            display: flex;
            background-color: var(--bg-card);
            border-radius: 16px;
            padding: 6px;
            margin-bottom: 20px;
            box-shadow: var(--neumorph-inset);
            gap: 8px;
            width: fit-content;
            flex-wrap: wrap;
        }
        .tab {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 15px;
            font-weight: 800;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 12px;
            text-decoration: none;
            transition: all var(--transition-speed);
        }
        .tab:hover {
            color: var(--text-primary);
        }
        .tab.active {
            background-color: var(--color-primary);
            color: white !important;
            box-shadow: var(--neumorph-flat);
        }
        .risk-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .risk-normal { background: rgba(16, 185, 129, 0.15); color: var(--color-green); }
        .risk-risk { background: rgba(245, 158, 11, 0.15); color: var(--color-yellow); }
        .risk-high { background: rgba(239, 68, 68, 0.15); color: var(--color-red); }
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

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="margin-bottom: 4px;">รายการผลตรวจจาก HDC</h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 15px;">จำแนกกลุ่มเป้าหมายตามระดับความเสี่ยง และนำเข้าโครงการปรับเปลี่ยนพฤติกรรม</p>

        <?php if ($message): ?>
            <div style="background-color: rgba(16, 185, 129, 0.15); border: 1px solid var(--color-green); color: var(--color-green); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php
        $filterParams = [];
        if ($filter_hoscode) $filterParams['hoscode'] = $filter_hoscode;
        if ($filter_tambon) $filterParams['tambon'] = $filter_tambon;
        if ($filter_vhid) $filterParams['vhid'] = $filter_vhid;

        // Populate village options based on selected area
        $village_options = [];
        if (!empty($filter_hoscode)) {
            $h = $filter_hoscode;
            if (isset($hoscode_villages[$h])) {
                $tcode = $hoscode_villages[$h]['tambon'];
                foreach ($hoscode_villages[$h]['villages'] as $moo => $name) {
                    $vcode = $tcode . sprintf('%02d', $moo);
                    $village_options[$vcode] = "หมู่ {$moo} {$name}";
                }
            }
        } elseif (!empty($filter_tambon)) {
            foreach ($hoscode_villages as $h => $info) {
                if ($info['tambon'] === $filter_tambon) {
                    foreach ($info['villages'] as $moo => $name) {
                        $vcode = $filter_tambon . sprintf('%02d', $moo);
                        $village_options[$vcode] = "หมู่ {$moo} {$name}";
                    }
                }
            }
            ksort($village_options);
        }
        ?>

        <!-- Filters Section -->
        <div class="card-dark" style="margin-bottom: 25px;">
            <form method="GET" action="hdc_list.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="type" value="<?= htmlspecialchars($diseaseType) ?>">
                <input type="hidden" name="risk" value="<?= htmlspecialchars($riskFilter) ?>">
                
                <div style="flex: 1; min-width: 200px;">
                    <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">หน่วยบริการ / รพ.สต.</label>
                    <?php if ($admin_hoscode !== null): ?>
                        <input type="text" class="form-select" value="<?= htmlspecialchars($hc_names[$admin_hoscode]) ?>" readonly style="background-color: rgba(0, 0, 0, 0.1); cursor: not-allowed; color: var(--text-muted);">
                        <input type="hidden" name="hoscode" value="<?= htmlspecialchars($admin_hoscode) ?>">
                    <?php else: ?>
                        <select name="hoscode" class="form-select" onchange="this.form.submit()">
                            <option value="">-- ทุกแห่ง (ทั้งหมด) --</option>
                            <?php foreach ($hc_names as $code => $name): ?>
                                <option value="<?= $code ?>" <?= ($filter_hoscode == $code) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <?php if (empty($filter_hoscode)): ?>
                <div style="width: 180px;">
                    <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">ตำบล</label>
                    <select name="tambon" class="form-select" onchange="this.form.submit()">
                        <option value="">-- ทุกตำบล --</option>
                        <?php foreach ($tambons as $code => $name): ?>
                            <option value="<?= $code ?>" <?= $filter_tambon == $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div style="flex: 1; min-width: 200px;">
                    <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">หมู่บ้าน</label>
                    <select name="vhid" class="form-select" onchange="this.form.submit()">
                        <option value="">-- ทุกหมู่บ้าน --</option>
                        <?php foreach ($village_options as $val => $lbl): ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= $filter_vhid == $val ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn-primary" style="height: 42px; padding: 0 20px; border-radius: var(--border-radius); font-weight: bold; cursor: pointer; border: none; background: var(--color-accent); color: white;">
                        ค้นหา
                    </button>
                    <a href="hdc_list.php?type=<?= htmlspecialchars($diseaseType) ?>&risk=<?= htmlspecialchars($riskFilter) ?>" class="btn-primary" style="height: 42px; padding: 0 15px; border-radius: var(--border-radius); font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; background: rgba(13, 44, 84, 0.1); color: var(--text-primary); border: 1px solid var(--border-color); box-sizing: border-box; margin-left: 5px;">
                        ล้างค่า
                    </a>
                </div>
            </form>
        </div>

        <div class="tabs">
            <a href="?<?= http_build_query(array_merge($filterParams, ['type' => 'DM', 'risk' => $riskFilter])) ?>" class="tab <?= $diseaseType === 'DM' ? 'active' : '' ?>">เบาหวาน (DM)</a>
            <a href="?<?= http_build_query(array_merge($filterParams, ['type' => 'HT', 'risk' => $riskFilter])) ?>" class="tab <?= $diseaseType === 'HT' ? 'active' : '' ?>">ความดัน (HT)</a>
        </div>

        <div class="tabs">
            <a href="?<?= http_build_query(array_merge($filterParams, ['type' => $diseaseType, 'risk' => 'all'])) ?>" class="tab <?= $riskFilter === 'all' ? 'active' : '' ?>">ทั้งหมด</a>
            <?php foreach ($availableRisks as $r): ?>
                <a href="?<?= http_build_query(array_merge($filterParams, ['type' => $diseaseType, 'risk' => $r])) ?>" class="tab <?= $riskFilter === $r ? 'active' : '' ?>"><?= htmlspecialchars($r) ?></a>
            <?php endforeach; ?>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="enroll_dpac">
            <div class="card-dark">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                    <h3 style="margin: 0; color: var(--color-accent);">รายชื่อเป้าหมาย (<?= count($records) ?> รายการ)</h3>
                    <button type="submit" class="btn-primary" style="padding: 10px 20px; border-radius: 20px; border: none; background: var(--color-green); color: white; cursor: pointer; font-weight: bold; box-shadow: var(--neumorph-flat);">
                        + นำเข้าโครงการปรับเปลี่ยนพฤติกรรม (DPAC)
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll"></th>
                                <th>เลขบัตรประชาชน</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ที่อยู่ / หมู่บ้าน</th>
                                <?php if ($diseaseType === 'DM'): ?>
                                    <th>ระดับน้ำตาล (FBS)</th>
                                <?php else: ?>
                                    <th>ความดัน (SBP/DBP)</th>
                                <?php endif; ?>
                                <th>ระดับความเสี่ยง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px; color: var(--text-secondary);">ไม่มีข้อมูล</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $r): ?>
                                    <?php 
                                        $riskClass = 'risk-normal';
                                        if (strpos($r['risk'], 'สูง') !== false) $riskClass = 'risk-high';
                                        else if (strpos($r['risk'], 'เสี่ยง') !== false) $riskClass = 'risk-risk';

                                        $vhid = $r['check_vhid'] ?? '';
                                        $moo = strlen($vhid) === 8 ? intval(substr($vhid, 6, 2)) : 0;
                                        $tambon = strlen($vhid) === 8 ? substr($vhid, 0, 6) : '';
                                        $village_only = get_village_only_name($tambon, $moo);
                                        $village_full = $moo > 0 ? "หมู่ {$moo} {$village_only}" : '';
                                        $address_full = trim(($r['addr'] ?? '') . ' ' . $village_full);
                                    ?>
                                    <tr>
                                        <td style="text-align: center;"><input type="checkbox" name="cids[]" value="<?= htmlspecialchars($r['cid']) ?>"></td>
                                        <td><?= htmlspecialchars($r['cid']) ?></td>
                                        <td style="font-weight: bold; color: var(--text-primary);"><?= htmlspecialchars($r['name'] . ' ' . $r['lname']) ?></td>
                                        <td><?= htmlspecialchars($address_full ?: '-') ?></td>
                                        <?php if ($diseaseType === 'DM'): ?>
                                            <td><?= htmlspecialchars($r['result'] ?? '-') ?> mg/dL</td>
                                        <?php else: ?>
                                            <td><?= htmlspecialchars($r['sbp'] ?? '-') ?> / <?= htmlspecialchars($r['dbp'] ?? '-') ?> mmHg</td>
                                        <?php endif; ?>
                                        <td><span class="risk-badge <?= $riskClass ?>"><?= htmlspecialchars($r['risk']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('selectAll').addEventListener('change', function(e) {
            document.querySelectorAll('input[name="cids[]"]').forEach(cb => cb.checked = e.target.checked);
        });
    </script>
</body>
</html>