<?php
// admin/reports.php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

// Hospital list
$hc_names = [
    '10957' => 'โรงพยาบาลตาลสุม',
    '10688' => 'โรงพยาบาลตาลสุม',
    '03751' => 'รพ.สต.ดอนพันชาด',
    '03752' => 'รพ.สต.สำโรง',
    '03753' => 'รพ.สต.บ้านจิกเทิง',
    '03754' => 'รพ.สต.หนองกุง',
    '03755' => 'รพ.สต.นาคาย',
    '03756' => 'รพ.สต.บ้านคำหนามแท่ง',
    '03757' => 'รพ.สต.คำหว้า'
];

$admin_title = $admin_hoscode ? ($hc_names[$admin_hoscode] ?? 'รพ.สต.') : 'แอดมินหลัก (ทุก รพ.สต.)';

// Get villages helper mapping
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

// Tambon lists
$tambons = [
    '341801' => 'ตำบลตาลสุม',
    '341802' => 'ตำบลสำโรง',
    '341803' => 'ตำบลจิกเทิง',
    '341804' => 'ตำบลหนองกุง',
    '341805' => 'ตำบลนาคาย',
    '341806' => 'ตำบลคำหว้า'
];

// Parameters from request
$filter_hoscode = $_GET['hoscode'] ?? '';
$filter_tambon = $_GET['tambon'] ?? '';
$filter_moo = $_GET['moo'] ?? '';
$filter_risk = $_GET['risk'] ?? '';
$filter_disease = $_GET['disease'] ?? '';
$filter_source = $_GET['source'] ?? 'screened'; // 'screened' (VHV Result) or 'baseline' (HDC Target)

// Force sub-admin to see only their hoscode
if ($admin_hoscode !== null) {
    $filter_hoscode = $admin_hoscode;
}

// Build SQL Query
$whereClauses = [];
$params = [];

if ($filter_source === 'screened') {
    // Query VHV screened results
    $sql = "
        SELECT p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.sub_district_code, p.hoscode,
               s.sys_bp1, s.dia_bp1, s.dtx_value, s.bmi, s.cv_risk_score, s.created_at
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        WHERE 1=1
    ";
    
    if ($filter_hoscode) {
        $hoscodes = [$filter_hoscode];
        if ($filter_hoscode === '10957' || $filter_hoscode === '10688') {
            $hoscodes = ['10957', '10688'];
        }
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $sql .= " AND p.hoscode IN ($inPlaceholders)";
        $params = array_merge($params, $hoscodes);
    }
    
    if ($filter_tambon) {
        $sql .= " AND p.sub_district_code = ?";
        $params[] = $filter_tambon;
    }
    
    if ($filter_moo) {
        $sql .= " AND p.moo = ?";
        $params[] = $filter_moo;
    }
    
    if ($filter_disease === 'DM') {
        $sql .= " AND (a.target_cid IN (SELECT cid FROM target_population WHERE need_screen_dm = 1))";
    } elseif ($filter_disease === 'HT') {
        $sql .= " AND (a.target_cid IN (SELECT cid FROM target_population WHERE need_screen_ht = 1))";
    }
    
    if ($filter_risk) {
        if ($filter_risk === 'high') {
            $sql .= " AND (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126)";
        } elseif ($filter_risk === 'risk') {
            $sql .= " AND (
                ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125))
                AND NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126)
            )";
        } elseif ($filter_risk === 'normal') {
            $sql .= " AND (s.sys_bp1 < 120 AND s.dia_bp1 < 80 AND (s.dtx_value < 100 OR s.dtx_value IS NULL) AND (s.cv_risk_score < 10 OR s.cv_risk_score IS NULL))";
        }
    }
    
    $sql .= " ORDER BY p.moo, LENGTH(p.house_no), p.house_no, s.created_at DESC";
    
} elseif ($filter_source === 'baseline') {
    // Query HDC Baseline Targets
    $sql = "
        SELECT p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.sub_district_code, p.hoscode,
               p.health_status_origin as risk, p.need_screen_dm, p.need_screen_ht
        FROM target_population p
        WHERE 1=1
    ";
    
    if ($filter_hoscode) {
        $hoscodes = [$filter_hoscode];
        if ($filter_hoscode === '10957' || $filter_hoscode === '10688') {
            $hoscodes = ['10957', '10688'];
        }
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $sql .= " AND p.hoscode IN ($inPlaceholders)";
        $params = array_merge($params, $hoscodes);
    }
    
    if ($filter_tambon) {
        $sql .= " AND p.sub_district_code = ?";
        $params[] = $filter_tambon;
    }
    
    if ($filter_moo) {
        $sql .= " AND p.moo = ?";
        $params[] = $filter_moo;
    }
    
    if ($filter_disease === 'DM') {
        $sql .= " AND p.need_screen_dm = 1";
    } elseif ($filter_disease === 'HT') {
        $sql .= " AND p.need_screen_ht = 1";
    }
    
    if ($filter_risk) {
        if ($filter_risk === 'high') {
            $sql .= " AND p.health_status_origin IN ('HIGH_RISK', 'BOTH', 'BOTH_HIGH')";
        } elseif ($filter_risk === 'risk') {
            $sql .= " AND p.health_status_origin IN ('DM_ONLY', 'HT_ONLY', 'BOTH')";
        } elseif ($filter_risk === 'normal') {
            $sql .= " AND p.health_status_origin = 'NORMAL'";
        }
    }
    
    $sql .= " ORDER BY p.moo, LENGTH(p.house_no), p.house_no";
    
} elseif ($filter_source === 'vhv_list') {
    // Query VHV Users and their stats
    $sql = "
        SELECT v.vhv_name, v.hoscode, v.vhv_moo, v.approved, v.vhid_code,
               (SELECT COUNT(*) FROM task_assignments a WHERE a.vhv_id = v.vhv_id) as assigned_targets,
               (SELECT COUNT(*) FROM task_assignments a WHERE a.vhv_id = v.vhv_id AND a.assignment_status = 'completed') as completed_screenings
        FROM vhv_users v
        WHERE 1=1
    ";
    
    if ($filter_hoscode) {
        $hoscodes = [$filter_hoscode];
        if ($filter_hoscode === '10957' || $filter_hoscode === '10688') {
            $hoscodes = ['10957', '10688'];
        }
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $sql .= " AND v.hoscode IN ($inPlaceholders)";
        $params = array_merge($params, $hoscodes);
    }
    
    if ($filter_tambon) {
        $sql .= " AND v.vhid_code LIKE ?";
        $params[] = $filter_tambon . '%';
    }
    
    if ($filter_moo) {
        $sql .= " AND v.vhv_moo = ?";
        $params[] = $filter_moo;
    }
    
    $sql .= " ORDER BY v.hoscode, v.vhv_moo, v.vhv_name";
    
} elseif ($filter_source === 'summary_stats') {
    // Query Village-level Target and Screening Summary
    $sql = "
        SELECT p.sub_district_code, p.moo, p.hoscode,
               COUNT(p.cid) as total_targets,
               SUM(CASE WHEN p.need_screen_dm = 1 THEN 1 ELSE 0 END) as targets_dm,
               SUM(CASE WHEN p.need_screen_ht = 1 THEN 1 ELSE 0 END) as targets_ht,
               SUM(CASE WHEN EXISTS (
                   SELECT 1 FROM task_assignments a 
                   WHERE a.target_cid = p.cid AND a.assignment_status = 'completed'
               ) THEN 1 ELSE 0 END) as completed_screenings,
               SUM(CASE WHEN EXISTS (
                   SELECT 1 FROM task_assignments a 
                   JOIN screening_results s ON s.assignment_id = a.assignment_id
                   WHERE a.target_cid = p.cid AND a.assignment_status = 'completed'
                     AND (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126)
               ) THEN 1 ELSE 0 END) as high_risk_count,
               SUM(CASE WHEN EXISTS (
                   SELECT 1 FROM task_assignments a 
                   JOIN screening_results s ON s.assignment_id = a.assignment_id
                   WHERE a.target_cid = p.cid AND a.assignment_status = 'completed'
                     AND NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126)
                     AND ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125))
               ) THEN 1 ELSE 0 END) as moderate_risk_count,
               SUM(CASE WHEN EXISTS (
                   SELECT 1 FROM task_assignments a 
                   JOIN screening_results s ON s.assignment_id = a.assignment_id
                   WHERE a.target_cid = p.cid AND a.assignment_status = 'completed'
                     AND s.sys_bp1 < 120 AND s.dia_bp1 < 80 AND (s.dtx_value < 100 OR s.dtx_value IS NULL) AND (s.cv_risk_score < 10 OR s.cv_risk_score IS NULL)
               ) THEN 1 ELSE 0 END) as normal_risk_count
        FROM target_population p
        WHERE 1=1
    ";
    
    if ($filter_hoscode) {
        $hoscodes = [$filter_hoscode];
        if ($filter_hoscode === '10957' || $filter_hoscode === '10688') {
            $hoscodes = ['10957', '10688'];
        }
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $sql .= " AND p.hoscode IN ($inPlaceholders)";
        $params = array_merge($params, $hoscodes);
    }
    
    if ($filter_tambon) {
        $sql .= " AND p.sub_district_code = ?";
        $params[] = $filter_tambon;
    }
    
    if ($filter_moo) {
        $sql .= " AND p.moo = ?";
        $params[] = $filter_moo;
    }
    
    $sql .= " GROUP BY p.hoscode, p.sub_district_code, p.moo";
    $sql .= " ORDER BY p.hoscode, p.sub_district_code, p.moo";
}

$reportData = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $queryError = $e->getMessage();
}

// Handle Export CSV Action
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ncd_report_' . date('Ymd_His') . '.csv"');
    
    // Add UTF-8 BOM for Thai characters in Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    $no = 1;
    if ($filter_source === 'screened') {
        fputcsv($output, ['ลำดับ', 'เลขบัตรประชาชน', 'ชื่อ-นามสกุล', 'บ้านเลขที่', 'หมู่บ้าน', 'ตำบล', 'รพ.สต.', 'ค่าความดันโลหิต', 'ค่าน้ำตาล (DTX)', 'ดัชนีมวลกาย (BMI)', 'ความเสี่ยง (CV Risk)', 'วันที่คัดกรองล่าสุด']);
        foreach ($reportData as $row) {
            $tambonName = $tambons[$row['sub_district_code']] ?? $row['sub_district_code'];
            $hosName = $hc_names[$row['hoscode']] ?? $row['hoscode'];
            $village_only = get_village_only_name($row['sub_district_code'], $row['moo']);
            $village_full = (strpos($village_only, 'หมู่ที่') === 0) ? $village_only : "หมู่ที่ " . $row['moo'] . " " . $village_only;
            
            fputcsv($output, [
                $no++,
                "'" . $row['cid'], // Force string for Excel
                $row['first_name'] . ' ' . $row['last_name'],
                $row['house_no'],
                $village_full,
                $tambonName,
                $hosName,
                $row['sys_bp1'] . '/' . $row['dia_bp1'],
                $row['dtx_value'] ?: '-',
                $row['bmi'] ?: '-',
                ($row['cv_risk_score'] !== null ? $row['cv_risk_score'] . '%' : '-'),
                $row['created_at']
            ]);
        }
    } elseif ($filter_source === 'baseline') {
        fputcsv($output, ['ลำดับ', 'เลขบัตรประชาชน', 'ชื่อ-นามสกุล', 'บ้านเลขที่', 'หมู่บ้าน', 'ตำบล', 'รพ.สต.', 'สถานะความเสี่ยงตั้งต้น', 'คัดกรองเบาหวาน', 'คัดกรองความดัน']);
        foreach ($reportData as $row) {
            $tambonName = $tambons[$row['sub_district_code']] ?? $row['sub_district_code'];
            $hosName = $hc_names[$row['hoscode']] ?? $row['hoscode'];
            $village_only = get_village_only_name($row['sub_district_code'], $row['moo']);
            $village_full = (strpos($village_only, 'หมู่ที่') === 0) ? $village_only : "หมู่ที่ " . $row['moo'] . " " . $village_only;
            
            fputcsv($output, [
                $no++,
                "'" . $row['cid'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['house_no'],
                $village_full,
                $tambonName,
                $hosName,
                $row['risk'] ?: 'ปกติ',
                $row['need_screen_dm'] ? 'ต้องการ' : 'ไม่ต้อง',
                $row['need_screen_ht'] ? 'ต้องการ' : 'ไม่ต้อง'
            ]);
        }
    } elseif ($filter_source === 'vhv_list') {
        fputcsv($output, ['ลำดับ', 'ชื่อ-นามสกุล อสม.', 'สังกัด รพ.สต.', 'หมู่บ้านรับผิดชอบ', 'จำนวนเป้าหมายที่มอบหมาย', 'คัดกรองสำเร็จ', 'ร้อยละความสำเร็จ', 'สถานะการอนุมัติ']);
        foreach ($reportData as $row) {
            $hosName = $hc_names[$row['hoscode']] ?? $row['hoscode'];
            $vhid_sub = substr($row['vhid_code'] ?? '', 0, 6);
            $village_only = get_village_only_name($vhid_sub, $row['vhv_moo']);
            $village_full = (strpos($village_only, 'หมู่ที่') === 0) ? $village_only : "หมู่ที่ " . $row['vhv_moo'] . " " . $village_only;
            
            $rate = $row['assigned_targets'] > 0 ? round(($row['completed_screenings'] / $row['assigned_targets']) * 100, 1) : 0;
            $status = $row['approved'] ? 'อนุมัติแล้ว' : 'รอตรวจสอบ';
            
            fputcsv($output, [
                $no++,
                $row['vhv_name'],
                $hosName,
                $village_full,
                $row['assigned_targets'],
                $row['completed_screenings'],
                $rate . '%',
                $status
            ]);
        }
    } elseif ($filter_source === 'summary_stats') {
        fputcsv($output, ['ลำดับ', 'รพ.สต.', 'หมู่บ้าน', 'ตำบล', 'เป้าหมายทั้งหมด', 'เป้าหมาย DM', 'เป้าหมาย HT', 'คัดกรองเสร็จสิ้น', 'ร้อยละความครอบคลุม', 'กลุ่มปกติ', 'กลุ่มเสี่ยง', 'กลุ่มเสี่ยงสูง']);
        foreach ($reportData as $row) {
            $tambonName = $tambons[$row['sub_district_code']] ?? $row['sub_district_code'];
            $hosName = $hc_names[$row['hoscode']] ?? $row['hoscode'];
            $village_only = get_village_only_name($row['sub_district_code'], $row['moo']);
            $village_full = (strpos($village_only, 'หมู่ที่') === 0) ? $village_only : "หมู่ที่ " . $row['moo'] . " " . $village_only;
            
            $rate = $row['total_targets'] > 0 ? round(($row['completed_screenings'] / $row['total_targets']) * 100, 1) : 0;
            
            fputcsv($output, [
                $no++,
                $hosName,
                $village_full,
                $tambonName,
                $row['total_targets'],
                $row['targets_dm'],
                $row['targets_ht'],
                $row['completed_screenings'],
                $rate . '%',
                $row['normal_risk_count'],
                $row['moderate_risk_count'],
                $row['high_risk_count']
            ]);
        }
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานและพิมพ์ข้อมูล - NCDs Prevention Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: bold;
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }
        .actions-bar {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        /* Print Stylesheet */
        @media print {
            @page {
                size: landscape;
                margin: 1.2cm 1cm;
            }
            body {
                background: white !important;
                color: black !important;
                font-family: 'Sarabun', 'TH Sarabun New', 'TH Sarabun PSK', sans-serif !important;
                font-size: 14px;
                line-height: 1.4;
            }
            .admin-navbar, .card-dark:first-of-type, .actions-bar, .no-print, h2.no-print, p.no-print {
                display: none !important;
            }
            .card-dark {
                box-shadow: none !important;
                padding: 0 !important;
                background: transparent !important;
                border: none !important;
            }
            .table-responsive {
                overflow: visible !important;
            }
            table.admin-table {
                box-shadow: none !important;
                border: 1px solid #000000 !important;
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 10px;
            }
            table.admin-table th {
                background: #f2f2f2 !important;
                color: black !important;
                border: 1px solid #000000 !important;
                font-weight: bold !important;
                padding: 8px 4px !important;
                font-size: 13px !important;
                text-align: center !important;
            }
            table.admin-table td {
                color: black !important;
                border: 1px solid #000000 !important;
                white-space: normal !important; /* Allow wrapping on print if narrow */
                padding: 6px 4px !important;
                font-size: 12px !important;
            }
            table.admin-table td strong {
                color: black !important;
            }
            table.admin-table td span {
                color: black !important;
            }
            .print-header {
                display: block !important;
                margin-bottom: 25px;
                text-align: center;
                border-bottom: 2px solid #000000;
                padding-bottom: 15px;
            }
            .print-header h2 {
                margin: 0 0 8px 0;
                font-size: 22px;
                font-weight: bold;
            }
            .print-header p {
                margin: 4px 0;
                font-size: 15px;
            }
            /* Add page number footer for print */
            .print-footer {
                display: block !important;
                position: fixed;
                bottom: 0;
                width: 100%;
                text-align: right;
                font-size: 11px;
                color: #555;
                border-top: 1px solid #ccc;
                padding-top: 5px;
            }
        }
        .print-footer {
            display: none;
        }
        .print-header {
            display: none;
        }
    </style>
</head>
<body class="admin-body">
    <!-- Admin Top Navbar (Hidden on Print) -->
    <div class="admin-navbar no-print">
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

    <!-- Print Header Only shown on printed pages -->
    <div class="print-header">
        <h2 style="font-family: 'Sarabun', 'TH Sarabun New', 'TH Sarabun PSK', sans-serif;">
            <?php 
            if ($filter_source === 'vhv_list') {
                echo 'รายงานทำเนียบ อสม. และสถิติผลงานการคัดกรองโรคไม่ติดต่อเรื้อรัง (NCDs)';
            } elseif ($filter_source === 'summary_stats') {
                echo 'รายงานสรุปสถิติจำนวนเป้าหมายและผลคัดกรอง NCDs รายหมู่บ้าน';
            } else {
                echo 'รายงานรายชื่อกลุ่มเป้าหมายคัดกรองโรคไม่ติดต่อเรื้อรัง (NCDs)';
            }
            ?>
        </h2>
        <p style="font-family: 'Sarabun', 'TH Sarabun New', 'TH Sarabun PSK', sans-serif; font-size: 16px;">อำเภอตาลสุม จังหวัดอุบลราชธานี</p>
        <p style="font-family: 'Sarabun', 'TH Sarabun New', 'TH Sarabun PSK', sans-serif; font-size: 13px; margin: 8px 0 0 0; font-weight: normal; color: #333;">
            <strong>เงื่อนไขรายงาน:</strong> แหล่งข้อมูล = <?= $filter_source == 'screened' ? 'ผลการคัดกรองล่าสุด' : ($filter_source == 'baseline' ? 'เป้าหมายตั้งต้น' : ($filter_source == 'vhv_list' ? 'ทำเนียบ อสม.' : 'สรุปเชิงสถิติรายหมู่บ้าน')) ?> | 
            หน่วยบริการ = <?= $filter_hoscode ? ($hc_names[$filter_hoscode] ?? $filter_hoscode) : 'ทุกแห่ง' ?> | 
            ตำบล = <?= $filter_tambon ? ($tambons[$filter_tambon] ?? $filter_tambon) : 'ทุกตำบล' ?> | 
            หมู่ = <?= $filter_moo ? 'หมู่ที่ ' . $filter_moo : 'ทุกหมู่' ?> | 
            ระดับความเสี่ยง = <?= $filter_risk == 'high' ? 'กลุ่มเสี่ยงสูง' : ($filter_risk == 'risk' ? 'กลุ่มเสี่ยง' : ($filter_risk == 'normal' ? 'กลุ่มปกติ' : 'ทั้งหมด')) ?> | 
            ประเภทโรค = <?= $filter_disease == 'DM' ? 'เบาหวาน (DM)' : ($filter_disease == 'HT' ? 'ความดันโลหิต (HT)' : 'ทั้งหมด') ?>
        </p>
    </div>
    <div class="print-footer">
        <span>พิมพ์จากระบบ NCDs Prevention Portal - Tansum เมื่อวันที่ <?= date('d/m/Y H:i') ?> น.</span>
    </div>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="margin-bottom: 4px;" class="no-print">รายงานและการพิมพ์รายชื่อ (Flexible Reports Manager)</h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 15px;" class="no-print">
            ผู้รับผิดชอบ: <strong style="color: var(--color-accent);"><?= htmlspecialchars($admin_title) ?></strong>
        </p>

        <!-- Filters Section (Hidden on Print) -->
        <div class="card-dark no-print" style="margin-bottom: 30px;">
            <h3 style="color: var(--color-accent); margin-top: 0; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px;">
                🔍 เงื่อนไขรายงาน
            </h3>
            
            <form method="GET" action="reports.php">
                <div class="form-grid">
                    <!-- Source selection -->
                    <div class="form-group">
                        <label>ประเภทรายงาน</label>
                        <select name="source" class="form-select" onchange="this.form.submit()">
                            <option value="screened" <?= $filter_source === 'screened' ? 'selected' : '' ?>>รายชื่อรายบุคคล (แยกตามผลการคัดกรองล่าสุด)</option>
                            <option value="baseline" <?= $filter_source === 'baseline' ? 'selected' : '' ?>>รายชื่อรายบุคคล (แยกตามเป้าหมายคัดกรองตั้งต้น HDC)</option>
                            <option value="vhv_list" <?= $filter_source === 'vhv_list' ? 'selected' : '' ?>>ทำเนียบ อสม. และสถิติผลงาน (VHV Directory & Performance)</option>
                            <option value="summary_stats" <?= $filter_source === 'summary_stats' ? 'selected' : '' ?>>รายงานสรุปสถิติจำนวนเป้าหมายและผลคัดกรองรายหมู่บ้าน</option>
                        </select>
                    </div>

                    <!-- Hospital Area selection -->
                    <div class="form-group">
                        <label>หน่วยบริการ / รพ.สต.</label>
                        <?php if ($admin_hoscode !== null): ?>
                            <input type="text" class="form-select" value="<?= htmlspecialchars($hc_names[$admin_hoscode]) ?>" readonly style="background-color: rgba(0, 0, 0, 0.1); cursor: not-allowed; font-weight: normal; color: var(--text-muted);">
                            <input type="hidden" name="hoscode" id="hoscode" value="<?= htmlspecialchars($admin_hoscode) ?>">
                        <?php else: ?>
                            <select name="hoscode" id="hoscode" class="form-select" onchange="onHoscodeChange()">
                                <option value="">-- ทุกแห่ง (ทั้งหมด) --</option>
                                <?php foreach ($hc_names as $code => $name): ?>
                                    <?php if ($code == 10688) continue; ?>
                                    <option value="<?= $code ?>" <?= ($filter_hoscode == $code || ($code === '10957' && $filter_hoscode === '10688')) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- Tambon selection -->
                    <div class="form-group">
                        <label>ตำบล</label>
                        <select name="tambon" id="tambon" class="form-select" onchange="onTambonChange()">
                            <option value="">-- ทุกตำบล --</option>
                            <?php foreach ($tambons as $code => $name): ?>
                                <option value="<?= $code ?>" <?= $filter_tambon == $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Village selection -->
                    <div class="form-group">
                        <label>หมู่ที่</label>
                        <select name="moo" id="moo" class="form-select">
                            <option value="">-- ทุกหมู่บ้าน --</option>
                            <?php for ($i = 1; $i <= 15; $i++): ?>
                                <option value="<?= $i ?>" <?= $filter_moo == $i ? 'selected' : '' ?>>หมู่ที่ <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Risk selection -->
                    <div class="form-group">
                        <label>ระดับความเสี่ยง</label>
                        <select name="risk" class="form-select">
                            <option value="">-- ทั้งหมด (ทุกกลุ่ม) --</option>
                            <option value="high" <?= $filter_risk == 'high' ? 'selected' : '' ?>>🔴 กลุ่มเสี่ยงสูง (High Risk)</option>
                            <option value="risk" <?= $filter_risk == 'risk' ? 'selected' : '' ?>>🟡 กลุ่มเสี่ยง (Moderate Risk)</option>
                            <option value="normal" <?= $filter_risk == 'normal' ? 'selected' : '' ?>>🟢 กลุ่มปกติ (Normal)</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 15px; flex-wrap: wrap; margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                    <!-- Disease selection (aligned left) -->
                    <div class="form-group" style="flex: 1; min-width: 200px; max-width: 320px; margin-bottom: 0;">
                        <label>ประเภทโรค</label>
                        <select name="disease" class="form-select">
                            <option value="">-- ทั้งหมด (DM & HT) --</option>
                            <option value="DM" <?= $filter_disease == 'DM' ? 'selected' : '' ?>>เบาหวาน (DM)</option>
                            <option value="HT" <?= $filter_disease == 'HT' ? 'selected' : '' ?>>ความดันโลหิต (HT)</option>
                        </select>
                    </div>
                    
                    <!-- Action buttons (aligned right) -->
                    <div class="actions-bar" style="margin-top: 0; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                        <button type="submit" class="btn-primary" style="padding: 10px 24px; border-radius: 20px; border: none; background: var(--color-accent); color: white; cursor: pointer; font-weight: bold; box-shadow: var(--neumorph-flat); height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                            🔍 ค้นหาข้อมูล
                        </button>
                        <button type="button" onclick="window.print()" class="btn-primary" style="padding: 10px 24px; border-radius: 20px; border: none; background: var(--color-green); color: white; cursor: pointer; font-weight: bold; box-shadow: var(--neumorph-flat); height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                            🖨️ พิมพ์รายงาน (Print)
                        </button>
                        <a href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_csv'])) ?>" class="btn-primary" style="padding: 0 24px; border-radius: 20px; border: none; background: #0284c7; color: white; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; box-shadow: var(--neumorph-flat); height: 42px; box-sizing: border-box;">
                            📥 ส่งออกเป็น CSV (Excel)
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Card -->
        <div class="card-dark">
            <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px;" class="no-print">
                ตัวอย่างข้อมูลรายงาน (พบ <?= count($reportData) ?> รายการ)
            </h3>
            
            <?php if (isset($queryError)): ?>
                <div style="background-color: rgba(239, 68, 68, 0.15); border: 1px solid var(--color-red); color: var(--color-red); padding: 15px; border-radius: 8px;">
                    ดึงข้อมูลล้มเหลว: <?= htmlspecialchars($queryError) ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <?php if ($filter_source === 'screened'): ?>
                                <tr>
                                    <th style="width: 50px;">ลำดับ</th>
                                    <th>เลขบัตรประชาชน</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>บ้านเลขที่</th>
                                    <th>หมู่บ้าน</th>
                                    <th>ตำบล</th>
                                    <th>รพ.สต.</th>
                                    <th>ความดันโลหิต</th>
                                    <th>ค่าน้ำตาล (DTX)</th>
                                    <th>ดัชนีมวลกาย (BMI)</th>
                                    <th>ความเสี่ยง (CV Risk)</th>
                                    <th>วันที่ตรวจคัดกรอง</th>
                                </tr>
                            <?php elseif ($filter_source === 'baseline'): ?>
                                <tr>
                                    <th style="width: 50px;">ลำดับ</th>
                                    <th>เลขบัตรประชาชน</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>บ้านเลขที่</th>
                                    <th>หมู่บ้าน</th>
                                    <th>ตำบล</th>
                                    <th>รพ.สต.</th>
                                    <th>ความเสี่ยงตั้งต้น HDC</th>
                                    <th>ตรวจ DM</th>
                                    <th>ตรวจ HT</th>
                                </tr>
                            <?php elseif ($filter_source === 'vhv_list'): ?>
                                <tr>
                                    <th style="width: 50px;">ลำดับ</th>
                                    <th>ชื่อ-นามสกุล อสม.</th>
                                    <th>สังกัด รพ.สต.</th>
                                    <th>หมู่บ้านรับผิดชอบ</th>
                                    <th>จำนวนเป้าหมาย</th>
                                    <th>คัดกรองสำเร็จ</th>
                                    <th>ร้อยละความสำเร็จ</th>
                                    <th>สถานะสิทธิ์</th>
                                </tr>
                            <?php elseif ($filter_source === 'summary_stats'): ?>
                                <tr>
                                    <th style="width: 50px;">ลำดับ</th>
                                    <th>หน่วยบริการ / รพ.สต.</th>
                                    <th>หมู่บ้าน</th>
                                    <th>ตำบล</th>
                                    <th>เป้าหมายทั้งหมด</th>
                                    <th>เป้าหมาย DM</th>
                                    <th>เป้าหมาย HT</th>
                                    <th>คัดกรองเสร็จ</th>
                                    <th>ร้อยละความครอบคลุม</th>
                                    <th>กลุ่มปกติ (เขียว)</th>
                                    <th>กลุ่มเสี่ยง (เหลือง)</th>
                                    <th>กลุ่มเสี่ยงสูง (แดง)</th>
                                </tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                            <?php if (empty($reportData)): ?>
                                <tr>
                                    <?php 
                                    $colspan = 12;
                                    if ($filter_source === 'baseline') $colspan = 10;
                                    elseif ($filter_source === 'vhv_list') $colspan = 9;
                                    elseif ($filter_source === 'summary_stats') $colspan = 12;
                                    ?>
                                    <td colspan="<?= $colspan ?>" style="text-align: center; color: var(--text-secondary); padding: 24px;">ไม่พบรายการที่ตรงกับเงื่อนไขตัวกรอง</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $no = 1;
                                foreach ($reportData as $row): 
                                ?>
                                    <?php if ($filter_source === 'screened'): ?>
                                        <?php
                                        $village_only = get_village_only_name($row['sub_district_code'], $row['moo']);
                                        $village_full = (strpos($village_only, 'หมู่ที่') === 0) ? $village_only : "หมู่ที่ " . $row['moo'] . " " . $village_only;
                                        ?>
                                        <tr>
                                            <td style="text-align: center;"><?= $no++ ?></td>
                                            <td><?= htmlspecialchars(substr($row['cid'], 0, 4) . '-XXXXX-' . substr($row['cid'], -3)) ?></td>
                                            <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($row['house_no']) ?></td>
                                            <td><?= htmlspecialchars($village_full) ?></td>
                                            <td><?= htmlspecialchars($tambons[$row['sub_district_code']] ?? $row['sub_district_code']) ?></td>
                                            <td><span style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($hc_names[$row['hoscode']] ?? $row['hoscode']) ?></span></td>
                                            <td>
                                                <?php if ($row['sys_bp1'] >= 140 || $row['dia_bp1'] >= 90): ?>
                                                    <span style="color: var(--color-red); font-weight: bold;"><?= $row['sys_bp1'] ?>/<?= $row['dia_bp1'] ?></span>
                                                <?php else: ?>
                                                    <?= $row['sys_bp1'] ?>/<?= $row['dia_bp1'] ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['dtx_value'] >= 126): ?>
                                                    <span style="color: var(--color-red); font-weight: bold;"><?= $row['dtx_value'] ?></span>
                                                <?php else: ?>
                                                    <?= $row['dtx_value'] ?: '-' ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['bmi'] ?: '-') ?></td>
                                            <td>
                                                <?php if ($row['cv_risk_score'] >= 10): ?>
                                                    <span style="background-color: rgba(239, 68, 68, 0.15); color: var(--color-red); padding: 4px 8px; border-radius: 4px; font-weight: bold;"><?= $row['cv_risk_score'] ?>%</span>
                                                <?php else: ?>
                                                    <?= $row['cv_risk_score'] !== null ? $row['cv_risk_score'] . '%' : '-' ?>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($row['created_at']) ?></td>
                                        </tr>
                                    <?php elseif ($filter_source === 'baseline'): ?>
                                        <?php
                                        $village_only = get_village_only_name($row['sub_district_code'], $row['moo']);
                                        $village_full = (strpos($village_only, 'หมู่ที่') === 0) ? $village_only : "หมู่ที่ " . $row['moo'] . " " . $village_only;
                                        ?>
                                        <tr>
                                            <td style="text-align: center;"><?= $no++ ?></td>
                                            <td><?= htmlspecialchars(substr($row['cid'], 0, 4) . '-XXXXX-' . substr($row['cid'], -3)) ?></td>
                                            <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($row['house_no']) ?></td>
                                            <td><?= htmlspecialchars($village_full) ?></td>
                                            <td><?= htmlspecialchars($tambons[$row['sub_district_code']] ?? $row['sub_district_code']) ?></td>
                                            <td><span style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($hc_names[$row['hoscode']] ?? $row['hoscode']) ?></span></td>
                                            <td>
                                                <?php 
                                                $riskVal = $row['risk'];
                                                $class = 'color: var(--text-primary);';
                                                if (strpos($riskVal, 'HIGH') !== false) {
                                                    $class = 'color: var(--color-red); font-weight: bold;';
                                                } elseif ($riskVal !== 'NORMAL' && $riskVal !== '') {
                                                    $class = 'color: var(--color-yellow); font-weight: bold;';
                                                }
                                                ?>
                                                <span style="<?= $class ?>"><?= htmlspecialchars($riskVal ?: 'ปกติ') ?></span>
                                            </td>
                                            <td><?= $row['need_screen_dm'] ? '🟢 ตรวจ' : '⚪ -' ?></td>
                                            <td><?= $row['need_screen_ht'] ? '🟢 ตรวจ' : '⚪ -' ?></td>
                                        </tr>
                                    <?php elseif ($filter_source === 'vhv_list'): ?>
                                        <?php
                                        $vhid_sub = substr($row['vhid_code'] ?? '', 0, 6);
                                        $village_only = get_village_only_name($vhid_sub, $row['vhv_moo']);
                                        $village_full = (strpos($village_only, 'หมู่ที่') === 0) ? $village_only : "หมู่ที่ " . $row['vhv_moo'] . " " . $village_only;
                                        $rate = $row['assigned_targets'] > 0 ? round(($row['completed_screenings'] / $row['assigned_targets']) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td style="text-align: center;"><?= $no++ ?></td>
                                            <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($row['vhv_name']) ?></strong></td>
                                            <td><span style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($hc_names[$row['hoscode']] ?? $row['hoscode']) ?></span></td>
                                            <td><?= htmlspecialchars($village_full) ?></td>
                                            <td style="text-align: center;"><?= number_format($row['assigned_targets']) ?></td>
                                            <td style="text-align: center; color: var(--color-green); font-weight: bold;"><?= number_format($row['completed_screenings']) ?></td>
                                            <td style="text-align: center; font-weight: bold;"><?= $rate ?>%</td>
                                            <td style="text-align: center;">
                                                <?php if ($row['approved']): ?>
                                                    <span style="color: var(--color-green); font-weight: bold;">🟢 อนุมัติแล้ว</span>
                                                <?php else: ?>
                                                    <span style="color: var(--color-yellow);">🟡 รออนุมัติ</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php elseif ($filter_source === 'summary_stats'): ?>
                                        <?php
                                        $village_only = get_village_only_name($row['sub_district_code'], $row['moo']);
                                        $village_full = (strpos($village_only, 'หมู่ที่') === 0) ? $village_only : "หมู่ที่ " . $row['moo'] . " " . $village_only;
                                        $rate = $row['total_targets'] > 0 ? round(($row['completed_screenings'] / $row['total_targets']) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td style="text-align: center;"><?= $no++ ?></td>
                                            <td><span style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($hc_names[$row['hoscode']] ?? $row['hoscode']) ?></span></td>
                                            <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($village_full) ?></strong></td>
                                            <td><?= htmlspecialchars($tambons[$row['sub_district_code']] ?? $row['sub_district_code']) ?></td>
                                            <td style="text-align: center; font-weight: bold;"><?= number_format($row['total_targets']) ?></td>
                                            <td style="text-align: center;"><?= number_format($row['targets_dm']) ?></td>
                                            <td style="text-align: center;"><?= number_format($row['targets_ht']) ?></td>
                                            <td style="text-align: center; color: var(--color-green); font-weight: bold;"><?= number_format($row['completed_screenings']) ?></td>
                                            <td style="text-align: center; font-weight: bold; color: var(--color-primary);"><?= $rate ?>%</td>
                                            <td style="text-align: center; color: var(--color-green); font-weight: bold;"><?= number_format($row['normal_risk_count']) ?></td>
                                            <td style="text-align: center; color: var(--color-yellow); font-weight: bold;"><?= number_format($row['moderate_risk_count']) ?></td>
                                            <td style="text-align: center; color: var(--color-red); font-weight: bold;"><?= number_format($row['high_risk_count']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const relations = {
            "10957": {
                tambon: "341801",
                villages: [
                    { moo: 1, name: "บ้านม่วงโคน" },
                    { moo: 2, name: "บ้านดอนรังกา" },
                    { moo: 3, name: "บ้านนาห้วยแคน" },
                    { moo: 5, name: "บ้านนามน" },
                    { moo: 10, name: "บ้านนามน" },
                    { moo: 11, name: "บ้านตาลสุม" },
                    { moo: 12, name: "บ้านคำไม้ตาย" }
                ]
            },
            "03751": {
                tambon: "341801",
                villages: [
                    { moo: 4, name: "บ้านดอนพันชาด" },
                    { moo: 6, name: "บ้านดอนตะลี" },
                    { moo: 7, name: "บ้านปากห้วย" },
                    { moo: 8, name: "บ้านโนนค้อ" },
                    { moo: 9, name: "บ้านแก่งกบ" },
                    { moo: 13, name: "บ้านปากเซ" },
                    { moo: 14, name: "บ้านโนนสวรรค์" },
                    { moo: 15, name: "บ้านทุ่งเจริญ" }
                ]
            },
            "03752": {
                tambon: "341802",
                villages: [
                    { moo: 1, name: "บ้านสำโรงใหญ่" },
                    { moo: 2, name: "บ้านสำโรงกลาง" },
                    { moo: 3, name: "บ้านนาโพธิ์" },
                    { moo: 4, name: "บ้านสำโรงใต้" },
                    { moo: 5, name: "บ้านทรายมูลเหนือ" },
                    { moo: 6, name: "บ้านทรายมูลใต้" },
                    { moo: 7, name: "บ้านหนองบัว" },
                    { moo: 8, name: "บ้านทุ่งเจริญ" }
                ]
            },
            "03753": {
                tambon: "341803",
                villages: [
                    { moo: 1, name: "บ้านจิกเทิง" },
                    { moo: 2, name: "บ้านจิกลุ่ม" },
                    { moo: 3, name: "บ้านเชียงแก้ว" },
                    { moo: 4, name: "บ้านเชียงแก้ว" },
                    { moo: 5, name: "บ้านดอนโด่" },
                    { moo: 6, name: "บ้านดอนยูง" },
                    { moo: 7, name: "บ้านค้อ" },
                    { moo: 8, name: "บ้านดอนแป้นลม" },
                    { moo: 9, name: "บ้านสร้างคำ" }
                ]
            },
            "03754": {
                tambon: "341804",
                villages: [
                    { moo: 1, name: "บ้านหนองกุงใหญ่" },
                    { moo: 2, name: "บ้านหนองกุงน้อย" },
                    { moo: 3, name: "บ้านคำแคน" },
                    { moo: 4, name: "บ้านสร้างแสง" },
                    { moo: 5, name: "บ้านคำเตยใต้" },
                    { moo: 6, name: "บ้านสร้างหว้า" },
                    { moo: 7, name: "บ้านคำเตยเหนือ" },
                    { moo: 8, name: "บ้านสร้างหว้าพัฒนา" }
                ]
            },
            "03755": {
                tambon: "341805",
                villages: [
                    { moo: 1, name: "บ้านนาคาย" },
                    { moo: 2, name: "บ้านโนนจิก" },
                    { moo: 3, name: "บ้านหนองเป็ด" },
                    { moo: 4, name: "บ้านโนนยาง" },
                    { moo: 5, name: "บ้านดอนขวาง" },
                    { moo: 6, name: "บ้านดอนหวาย" }
                ]
            },
            "03756": {
                tambon: "341805",
                villages: [
                    { moo: 7, name: "บ้านโคกคล้าย" },
                    { moo: 8, name: "บ้านคำหนามแท่ง" },
                    { moo: 9, name: "บ้านคำผักหนอก" },
                    { moo: 10, name: "บ้านคำฮี" },
                    { moo: 11, name: "บ้านห่องแดง" },
                    { moo: 12, name: "บ้านโนนสำราญ" },
                    { moo: 13, name: "บ้านโนนเจริญ" }
                ]
            },
            "03757": {
                tambon: "341806",
                villages: [
                    { moo: 1, name: "บ้านคำหว้า" },
                    { moo: 2, name: "บ้านคำหว้า" },
                    { moo: 3, name: "บ้านห้วยดู่" },
                    { moo: 4, name: "บ้านนาทมเหนือ" },
                    { moo: 5, name: "บ้านไฮหย่อง" },
                    { moo: 6, name: "บ้านนาทมใต้" }
                ]
            }
        };

        function getVillagesByTambon(tambonCode) {
            let list = [];
            for (let h in relations) {
                if (relations[h].tambon === tambonCode) {
                    list = list.concat(relations[h].villages);
                }
            }
            list.sort((a, b) => a.moo - b.moo);
            return list;
        }

        function onHoscodeChange() {
            const hSelect = document.getElementById('hoscode');
            const tSelect = document.getElementById('tambon');
            const mSelect = document.getElementById('moo');
            if (!hSelect || !tSelect || !mSelect) return;
            
            const hCode = hSelect.value;
            const savedMoo = mSelect.value;
            
            if (hCode) {
                // Set tambon automatically
                const tambonVal = relations[hCode].tambon;
                tSelect.value = tambonVal;
                
                // Populate villages specifically for this hoscode
                populateMooSelect(relations[hCode].villages, savedMoo);
            } else {
                // If tambon is selected, show its villages. Else show generic 1-15
                const tCode = tSelect.value;
                if (tCode) {
                    populateMooSelect(getVillagesByTambon(tCode), savedMoo);
                } else {
                    populateMooSelect([], savedMoo, true);
                }
            }
        }

        function onTambonChange() {
            const hSelect = document.getElementById('hoscode');
            const tSelect = document.getElementById('tambon');
            const mSelect = document.getElementById('moo');
            if (!hSelect || !tSelect || !mSelect) return;
            
            const tCode = tSelect.value;
            const savedMoo = mSelect.value;
            
            if (tCode) {
                // Filter hoscode dropdown to show only units in this tambon
                filterHoscodeSelect(tCode);
                
                // Populate villages of this tambon
                populateMooSelect(getVillagesByTambon(tCode), savedMoo);
            } else {
                // Reset hoscode select options
                filterHoscodeSelect("");
                
                // If hoscode is selected, populate its villages. Else generic
                const hCode = hSelect.value;
                if (hCode) {
                    populateMooSelect(relations[hCode].villages, savedMoo);
                } else {
                    populateMooSelect([], savedMoo, true);
                }
            }
        }

        function filterHoscodeSelect(tambonCode) {
            const hSelect = document.getElementById('hoscode');
            if (!hSelect || hSelect.tagName !== 'SELECT') return;
            
            const currentVal = hSelect.value;
            hSelect.innerHTML = '<option value="">-- ทุกแห่ง (ทั้งหมด) --</option>';
            
            const hcNamesObj = {
                "10957": "โรงพยาบาลตาลสุม",
                "03751": "รพ.สต.ดอนพันชาด",
                "03752": "รพ.สต.สำโรง",
                "03753": "รพ.สต.บ้านจิกเทิง",
                "03754": "รพ.สต.หนองกุง",
                "03755": "รพ.สต.นาคาย",
                "03756": "รพ.สต.บ้านคำหนามแท่ง",
                "03757": "รพ.สต.คำหว้า"
            };
            
            for (let code in hcNamesObj) {
                if (!tambonCode || relations[code].tambon === tambonCode) {
                    hSelect.innerHTML += `<option value="${code}" ${currentVal === code ? 'selected' : ''}>${hcNamesObj[code]}</option>`;
                }
            }
        }

        function populateMooSelect(villages, selectedMoo, isGeneric = false) {
            const mSelect = document.getElementById('moo');
            if (!mSelect) return;
            mSelect.innerHTML = '<option value="">-- ทุกหมู่บ้าน --</option>';
            
            if (isGeneric || villages.length === 0) {
                for (let i = 1; i <= 15; i++) {
                    mSelect.innerHTML += `<option value="${i}" ${selectedMoo == i ? 'selected' : ''}>หมู่ที่ ${i}</option>`;
                }
            } else {
                villages.forEach(v => {
                    mSelect.innerHTML += `<option value="${v.moo}" ${selectedMoo == v.moo ? 'selected' : ''}>หมู่ที่ ${v.moo} ${v.name}</option>`;
                });
            }
        }

        // Initialize relations on DOM load
        const loggedAdminHoscode = "<?= $admin_hoscode ?: '' ?>";
        window.addEventListener('DOMContentLoaded', () => {
            const hSelect = document.getElementById('hoscode');
            const tSelect = document.getElementById('tambon');
            const mSelect = document.getElementById('moo');
            
            const initialHoscode = hSelect ? hSelect.value : "";
            const initialTambon = tSelect.value;
            const initialMoo = "<?= $filter_moo ?>";
            
            if (loggedAdminHoscode) {
                const targetTambon = relations[loggedAdminHoscode] ? relations[loggedAdminHoscode].tambon : "";
                if (targetTambon) {
                    tSelect.value = targetTambon;
                    tSelect.style.pointerEvents = 'none';
                    tSelect.style.backgroundColor = 'rgba(0,0,0,0.1)';
                    tSelect.style.cursor = 'not-allowed';
                }
                onHoscodeChange();
                mSelect.value = initialMoo;
            } else if (initialHoscode) {
                onHoscodeChange();
                mSelect.value = initialMoo;
            } else if (initialTambon) {
                onTambonChange();
                mSelect.value = initialMoo;
            } else {
                populateMooSelect([], initialMoo, true);
            }
        });
    </script>
</body>
</html>
