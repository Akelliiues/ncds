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
// Default: show only กลุ่มเสี่ยง (1) and เสี่ยงสูง (2) — exclude ปกติ(0) and ป่วย/สงสัย(3)
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



function get_risk_label($risk) {
    $risk = trim((string)$risk);
    if ($risk === '0') {
        return 'ปกติ';
    } elseif ($risk === '1') {
        return 'กลุ่มเสี่ยง';
    } elseif ($risk === '2') {
        return 'กลุ่มเสี่ยงสูง';
    } elseif ($risk === '3') {
        return 'ป่วย/สงสัยป่วย';
    }
    return $risk;
}

function calculate_age($birth_date) {
    if (empty($birth_date)) {
        return '-';
    }
    try {
        $birthDate = new DateTime($birth_date);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        return $age . ' ปี';
    } catch (\Exception $e) {
        return '-';
    }
}



// Fetch Data
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$whereClauses = [];
$params = [];

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

// Pagination Variables
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$totalRecords = 0;
$totalPages = 0;

if ($diseaseType === 'BOTH') {
    if ($riskFilter !== 'all') {
        $whereClauses[] = "(dm.risk = ? OR ht.risk = ?)";
        $params[] = $riskFilter;
        $params[] = $riskFilter;
    } else {
        // Default: exclude ปกติ (0) and ป่วย/สงสัยป่วย (3)
        $whereClauses[] = "(dm.risk IN ('1','2') OR ht.risk IN ('1','2'))";
    }
    $whereClauses[] = "dm.hoscode IN ($inPlaceholders)";
    $params = array_merge($params, $hoscodes);

    if ($filter_vhid) {
        $selected_moo = intval(substr($filter_vhid, 6, 2));
        $moo_str = sprintf('%02d', $selected_moo);
        $whereClauses[] = "RIGHT(dm.check_vhid, 2) = ?";
        $params[] = $moo_str;

        // If hoscode is not selected, restrict check_vhid by the tambon prefix of the selected village
        if (empty($filter_hoscode)) {
            $village_tambon = substr($filter_vhid, 0, 6);
            $whereClauses[] = "dm.check_vhid LIKE ?";
            $params[] = $village_tambon . '%';
        }
    } elseif ($filter_tambon) {
        $whereClauses[] = "dm.check_vhid LIKE ?";
        $params[] = $filter_tambon . '%';
    }

    $whereClause = "";
    if (!empty($whereClauses)) {
        $whereClause = "WHERE " . implode(" AND ", $whereClauses);
    }

    // Count total records
    $countSql = "
        SELECT COUNT(dm.cid)
        FROM staging_hdc_dm dm
        INNER JOIN staging_hdc_ht ht ON LPAD(dm.hoscode, 5, '0') = LPAD(ht.hoscode, 5, '0') AND dm.pid = ht.pid
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    $sql = "
        SELECT 
            dm.cid, dm.name, dm.lname, dm.sex, dm.birth, dm.addr, dm.check_vhid, dm.hoscode,
            dm.bstest, dm.bslevel, dm.risk as dm_risk,
            ht.sbp, ht.dbp, ht.risk as ht_risk,
            t.cid AS real_cid, t.first_name AS real_first_name, t.last_name AS real_last_name, t.birth AS real_birth
        FROM staging_hdc_dm dm
        INNER JOIN staging_hdc_ht ht ON LPAD(dm.hoscode, 5, '0') = LPAD(ht.hoscode, 5, '0') AND dm.pid = ht.pid
        LEFT JOIN target_population t ON LPAD(dm.hoscode, 5, '0') = LPAD(t.hoscode, 5, '0') AND dm.pid = t.pid
        $whereClause
        ORDER BY COALESCE(t.first_name, dm.name) ASC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
} else {
    $table = $diseaseType === 'DM' ? 'staging_hdc_dm' : 'staging_hdc_ht';
    if ($riskFilter !== 'all') {
        $whereClauses[] = "s.risk = ?";
        $params[] = $riskFilter;
    } else {
        // Default: exclude ปกติ (0) and ป่วย/สงสัยป่วย (3)
        $whereClauses[] = "s.risk IN ('1','2')";
    }
    $whereClauses[] = "s.hoscode IN ($inPlaceholders)";
    $params = array_merge($params, $hoscodes);

    if ($filter_vhid) {
        $selected_moo = intval(substr($filter_vhid, 6, 2));
        $moo_str = sprintf('%02d', $selected_moo);
        $whereClauses[] = "RIGHT(s.check_vhid, 2) = ?";
        $params[] = $moo_str;

        // If hoscode is not selected, restrict check_vhid by the tambon prefix of the selected village
        if (empty($filter_hoscode)) {
            $village_tambon = substr($filter_vhid, 0, 6);
            $whereClauses[] = "s.check_vhid LIKE ?";
            $params[] = $village_tambon . '%';
        }
    } elseif ($filter_tambon) {
        $whereClauses[] = "s.check_vhid LIKE ?";
        $params[] = $filter_tambon . '%';
    }

    $whereClause = "";
    if (!empty($whereClauses)) {
        $whereClause = "WHERE " . implode(" AND ", $whereClauses);
    }

    // Count total records
    $countSql = "
        SELECT COUNT(s.cid)
        FROM $table s
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    $sql = "
        SELECT 
            s.*,
            t.cid AS real_cid, t.first_name AS real_first_name, t.last_name AS real_last_name, t.birth AS real_birth
        FROM $table s
        LEFT JOIN target_population t ON LPAD(s.hoscode, 5, '0') = LPAD(t.hoscode, 5, '0') AND s.pid = t.pid
        $whereClause
        ORDER BY COALESCE(t.first_name, s.name) ASC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
}

// Get risk options for tabs (only กลุ่มเสี่ยง=1 and เสี่ยงสูง=2)
$availableRisks = ['1', '2'];

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
        .pagination {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .page-link {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s;
        }
        .page-link:hover {
            border-color: var(--color-primary);
            background: var(--bg-darker);
        }
        .page-link.active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }
    </style>
</head>
<body class="admin-body">
    <?php include 'navbar.php'; ?>

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
        } else {
            // Populate all villages from all hospitals
            foreach ($hoscode_villages as $h => $info) {
                $tcode = $info['tambon'];
                foreach ($info['villages'] as $moo => $name) {
                    $vcode = $tcode . sprintf('%02d', $moo);
                    $village_options[$vcode] = "หมู่ {$moo} {$name} (" . ($hc_names[$h] ?? $h) . ")";
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
            <a href="?<?= http_build_query(array_merge($filterParams, ['type' => 'BOTH', 'risk' => $riskFilter])) ?>" class="tab <?= $diseaseType === 'BOTH' ? 'active' : '' ?>">ทั้ง DM/HT</a>
        </div>

        <div class="tabs">
            <a href="?<?= http_build_query(array_merge($filterParams, ['type' => $diseaseType, 'risk' => 'all'])) ?>" class="tab <?= $riskFilter === 'all' ? 'active' : '' ?>">📋 กลุ่มเป้าหมายหลัก (Risk 1-2)</a>
            <a href="?<?= http_build_query(array_merge($filterParams, ['type' => $diseaseType, 'risk' => '1'])) ?>" class="tab <?= $riskFilter === '1' ? 'active' : '' ?>">🟡 กลุ่มเสี่ยง (Risk 1)</a>
            <a href="?<?= http_build_query(array_merge($filterParams, ['type' => $diseaseType, 'risk' => '2'])) ?>" class="tab <?= $riskFilter === '2' ? 'active' : '' ?>">🔴 กลุ่มเสี่ยงสูง (Risk 2)</a>
            <a href="?<?= http_build_query(array_merge($filterParams, ['type' => $diseaseType, 'risk' => '3'])) ?>" class="tab <?= $riskFilter === '3' ? 'active' : '' ?>">🔵 ป่วย/สงสัยป่วย (Risk 3)</a>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="enroll_dpac">
            <div class="card-dark">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                    <h3 style="margin: 0; color: var(--color-accent);">รายชื่อเป้าหมาย (พบทั้งหมด <?= number_format($totalRecords) ?> รายการ<?= $totalPages > 1 ? " | หน้าที่ $page/$totalPages" : "" ?>)</h3>
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
                                <?php elseif ($diseaseType === 'HT'): ?>
                                    <th>ความดัน (SBP/DBP)</th>
                                <?php else: ?>
                                    <th>ระดับน้ำตาล & ความดัน</th>
                                <?php endif; ?>
                                <th>ระดับความเสี่ยง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px; color: var(--text-secondary);">ไม่มีข้อมูล</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $r): ?>
                                    <?php 
                                        // Resolve real unmasked JHCIS details if matched
                                        $displayCid = (!empty($r['real_cid']) && strpos($r['real_cid'], '*') === false) ? $r['real_cid'] : $r['cid'];
                                        $displayName = (!empty($r['real_first_name']) && strpos($r['real_first_name'], '*') === false) ? $r['real_first_name'] : $r['name'];
                                        $displayLname = (!empty($r['real_last_name']) && strpos($r['real_last_name'], '*') === false) ? $r['real_last_name'] : $r['lname'];
                                        $displayBirth = (!empty($r['real_birth'])) ? $r['real_birth'] : $r['birth'];

                                        $age = calculate_age($displayBirth);
                                        $vhid = $r['check_vhid'] ?? '';
                                        $moo = strlen($vhid) === 8 ? intval(substr($vhid, 6, 2)) : 0;
                                        $village_full = get_village_display_name_by_hoscode($r['hoscode'], $moo);
                                        $address_full = trim(($r['addr'] ?? '') . ' ' . $village_full);
                                    ?>
                                    <tr>
                                        <td style="text-align: center;"><input type="checkbox" name="cids[]" value="<?= htmlspecialchars($displayCid) ?>"></td>
                                        <td><?= htmlspecialchars($displayCid) ?></td>
                                        <td style="font-weight: bold; color: var(--text-primary);">
                                            <?= htmlspecialchars($displayName . ' ' . $displayLname) ?><br>
                                            <span style="font-size: 12px; font-weight: normal; color: var(--text-muted);">อายุ: <?= htmlspecialchars($age) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($address_full ?: '-') ?></td>
                                        
                                        <?php if ($diseaseType === 'DM'): ?>
                                            <?php
                                                $risk_label = get_risk_label($r['risk']);
                                                $riskClass = 'risk-normal';
                                                if (strpos($risk_label, 'สูง') !== false || $r['risk'] === '3') $riskClass = 'risk-high';
                                                else if (strpos($risk_label, 'เสี่ยง') !== false || $r['risk'] === '1' || $r['risk'] === '2') $riskClass = 'risk-risk';
                                            ?>
                                            <td>
                                                <?= htmlspecialchars($r['bslevel'] ?? '-') ?> mg/dL
                                                <?php if (!empty($r['bstest'])): ?>
                                                    <br><span style="font-size: 11px; color: var(--text-secondary);">(<?= htmlspecialchars($r['bstest']) ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="risk-badge <?= $riskClass ?>"><?= htmlspecialchars($risk_label) ?></span></td>
                                            
                                        <?php elseif ($diseaseType === 'HT'): ?>
                                            <?php
                                                $risk_label = get_risk_label($r['risk']);
                                                $riskClass = 'risk-normal';
                                                if (strpos($risk_label, 'สูง') !== false || $r['risk'] === '3') $riskClass = 'risk-high';
                                                else if (strpos($risk_label, 'เสี่ยง') !== false || $r['risk'] === '1' || $r['risk'] === '2') $riskClass = 'risk-risk';
                                            ?>
                                            <td><?= htmlspecialchars($r['sbp'] ?? '-') ?> / <?= htmlspecialchars($r['dbp'] ?? '-') ?> mmHg</td>
                                            <td><span class="risk-badge <?= $riskClass ?>"><?= htmlspecialchars($risk_label) ?></span></td>
                                            
                                        <?php else: ?>
                                            <?php
                                                $dm_risk_label = get_risk_label($r['dm_risk']);
                                                $ht_risk_label = get_risk_label($r['ht_risk']);
                                                
                                                $dm_class = 'risk-normal';
                                                if (strpos($dm_risk_label, 'สูง') !== false || $r['dm_risk'] === '3') $dm_class = 'risk-high';
                                                else if (strpos($dm_risk_label, 'เสี่ยง') !== false || $r['dm_risk'] === '1' || $r['dm_risk'] === '2') $dm_class = 'risk-risk';

                                                $ht_class = 'risk-normal';
                                                if (strpos($ht_risk_label, 'สูง') !== false || $r['ht_risk'] === '3') $ht_class = 'risk-high';
                                                else if (strpos($ht_risk_label, 'เสี่ยง') !== false || $r['ht_risk'] === '1' || $r['ht_risk'] === '2') $ht_class = 'risk-risk';
                                            ?>
                                            <td>
                                                <div style="font-size: 13px; margin-bottom: 4px;">
                                                    🍭 <strong>FBS:</strong> <?= htmlspecialchars($r['bslevel'] ?? '-') ?> mg/dL 
                                                    <?php if (!empty($r['bstest'])): ?>
                                                        <span style="font-size: 11px; color: var(--text-secondary);">(<?= htmlspecialchars($r['bstest']) ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 13px;">
                                                    🫀 <strong>BP:</strong> <?= htmlspecialchars($r['sbp'] ?? '-') ?>/<?= htmlspecialchars($r['dbp'] ?? '-') ?> mmHg
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                                    <span>DM: <span class="risk-badge <?= $dm_class ?>"><?= htmlspecialchars($dm_risk_label) ?></span></span>
                                                    <span>HT: <span class="risk-badge <?= $ht_class ?>"><?= htmlspecialchars($ht_risk_label) ?></span></span>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination (Hidden on Print) -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination no-print" style="margin-bottom: 20px;">
                        <?php
                        $startPage = max(1, $page - 3);
                        $endPage = min($totalPages, $page + 3);
                        
                        $queryParams = $_GET;
                        
                        if ($startPage > 1) {
                            $queryParams['page'] = 1;
                            echo '<a href="?' . http_build_query($queryParams) . '" class="page-link">1</a>';
                            if ($startPage > 2) echo '<span style="padding: 6px; color: var(--text-secondary);">...</span>';
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $active = ($i == $page) ? 'active' : '';
                            $queryParams['page'] = $i;
                            echo '<a href="?' . http_build_query($queryParams) . '" class="page-link ' . $active . '">' . $i . '</a>';
                        }
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) echo '<span style="padding: 6px; color: var(--text-secondary);">...</span>';
                            $queryParams['page'] = $totalPages;
                            echo '<a href="?' . http_build_query($queryParams) . '" class="page-link">' . $totalPages . '</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>

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