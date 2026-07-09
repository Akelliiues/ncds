<?php
// admin/reports.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

$hc_names = get_health_units();

$admin_title = get_admin_title();

$tambons = [];
try {
    $stmt = $pdo->query("SELECT sub_district_code, CONCAT('ตำบล', sub_district_name) FROM sub_districts ORDER BY sub_district_code ASC");
    $tambons = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (\Exception $e) {
    $tambons = [
        '341801' => 'ตำบลตาลสุม',
        '341802' => 'ตำบลสำโรง',
        '341803' => 'ตำบลจิกเทิง',
        '341804' => 'ตำบลหนองกุง',
        '341805' => 'ตำบลนาคาย',
        '341806' => 'ตำบลคำหว้า'
    ];
}

// ดึงข้อมูลความสัมพันธ์หมู่บ้านและ รพ.สต. เพื่อใช้ในการกรองข้อมูลให้ตรงกับที่ตั้งค่าในระบบ
$relations = [];
try {
    $stmtV = $pdo->query("SELECT vhid_code, sub_district_code, moo, village_name, hoscode FROM villages ORDER BY hoscode ASC, moo ASC");
    $allVillages = $stmtV->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allVillages as $v) {
        $hc = $v['hoscode'];
        if (empty($hc)) continue;
        if (!isset($relations[$hc])) {
            $relations[$hc] = [
                'tambon' => $v['sub_district_code'],
                'villages' => []
            ];
        }
        $relations[$hc]['villages'][] = [
            'moo' => intval($v['moo']),
            'name' => $v['village_name']
        ];
    }
} catch (\Exception $e) {
    // ปล่อยว่างไว้
}

// Parameters from request
$filter_hoscode = $_GET['hoscode'] ?? '';
$filter_tambon = $_GET['tambon'] ?? '';
$filter_moo = $_GET['moo'] ?? '';
$filter_risk = $_GET['risk'] ?? '';
$filter_disease = $_GET['disease'] ?? '';
$filter_source = $_GET['source'] ?? 'screened'; // 'screened', 'baseline', 'unscreened', 'vhv_list', 'summary_stats', 'summary_hoscode'
$filter_gender = $_GET['gender'] ?? '';
$filter_age = $_GET['age'] ?? '';

// Force sub-admin to see only their hoscode
if ($admin_hoscode !== null) {
    $filter_hoscode = $admin_hoscode;
}

function appendDemographicFilters(&$sql, $alias = 'p') {
    global $filter_gender, $filter_age;
    if ($filter_gender === '1' || $filter_gender === '2') {
        $sql .= " AND $alias.sex = " . intval($filter_gender);
    }
    if ($filter_age === '35-59') {
        $sql .= " AND (TIMESTAMPDIFF(YEAR, $alias.birth, CURDATE()) BETWEEN 35 AND 59)";
    } elseif ($filter_age === '60+') {
        $sql .= " AND (TIMESTAMPDIFF(YEAR, $alias.birth, CURDATE()) >= 60)";
    }
}

// Build SQL Query
$whereClauses = [];
$params = [];

if ($filter_source === 'all') {
    // Query all targets (both screened and unscreened)
    $sql = "
        SELECT p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.sub_district_code, COALESCE(v.hoscode, p.hoscode) as hoscode,
               s.sys_bp1, s.dia_bp1, s.dtx_value, s.bmi, s.cv_risk_score, s.created_at,
               p.health_status_origin as risk, p.need_screen_dm, p.need_screen_ht,
               CASE WHEN s.created_at IS NOT NULL THEN 'screened' ELSE 'unscreened' END as screen_status
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.assignment_status = 'completed'
        LEFT JOIN screening_results s ON a.assignment_id = s.assignment_id
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ";

    if ($filter_hoscode) {
        $hoscodes = get_query_hoscodes($filter_hoscode);
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $sql .= " AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholders)";
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
            $sql .= " AND (
                (s.created_at IS NOT NULL AND (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126))
                OR (s.created_at IS NULL AND p.health_status_origin = 'BOTH')
            )";
        } elseif ($filter_risk === 'risk') {
            $sql .= " AND (
                (s.created_at IS NOT NULL AND (
                    ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125))
                    AND NOT (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126)
                ))
                OR (s.created_at IS NULL AND p.health_status_origin IN ('DM_ONLY', 'HT_ONLY'))
            )";
        } elseif ($filter_risk === 'all_risk') {
            $sql .= " AND (
                (s.created_at IS NOT NULL AND (
                    (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126)
                    OR
                    ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125))
                ))
                OR (s.created_at IS NULL AND p.health_status_origin IN ('BOTH', 'DM_ONLY', 'HT_ONLY'))
            )";
        } elseif ($filter_risk === 'normal') {
            $sql .= " AND (
                (s.created_at IS NOT NULL AND (s.sys_bp1 < 120 AND s.dia_bp1 < 80 AND (s.dtx_value < 100 OR s.dtx_value IS NULL) AND (s.cv_risk_score < 10 OR s.cv_risk_score IS NULL)))
                OR (s.created_at IS NULL AND (p.health_status_origin = 'NORMAL' OR p.health_status_origin IS NULL OR p.health_status_origin = ''))
            )";
        }
    }

    appendDemographicFilters($sql, 'p');
    $sql .= " ORDER BY p.moo, LENGTH(p.house_no), p.house_no";

} elseif ($filter_source === 'screened') {
    // Query VHV screened results
    $sql = "
        SELECT p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.sub_district_code, COALESCE(v.hoscode, p.hoscode) as hoscode,
               s.sys_bp1, s.dia_bp1, s.dtx_value, s.bmi, s.cv_risk_score, s.created_at
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        JOIN target_population p ON a.target_cid = p.cid
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ";

    if ($filter_hoscode) {
        $hoscodes = get_query_hoscodes($filter_hoscode);
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $sql .= " AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholders)";
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
        } elseif ($filter_risk === 'all_risk') {
            $sql .= " AND (
                (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126)
                OR
                ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125))
            )";
        } elseif ($filter_risk === 'normal') {
            $sql .= " AND (s.sys_bp1 < 120 AND s.dia_bp1 < 80 AND (s.dtx_value < 100 OR s.dtx_value IS NULL) AND (s.cv_risk_score < 10 OR s.cv_risk_score IS NULL))";
        }
    }

    appendDemographicFilters($sql, 'p');
    $sql .= " ORDER BY p.moo, LENGTH(p.house_no), p.house_no, s.created_at DESC";

} elseif ($filter_source === 'unscreened') {
    // Query targets that have no completed screenings
    $sql = "
        SELECT p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.sub_district_code, COALESCE(v.hoscode, p.hoscode) as hoscode,
               p.health_status_origin as risk, p.need_screen_dm, p.need_screen_ht
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.assignment_status = 'completed'
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        WHERE a.assignment_id IS NULL AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ";

    if ($filter_hoscode) {
        $hoscodes = get_query_hoscodes($filter_hoscode);
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $sql .= " AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholders)";
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
            $sql .= " AND p.health_status_origin = 'BOTH'";
        } elseif ($filter_risk === 'risk') {
            $sql .= " AND p.health_status_origin IN ('DM_ONLY', 'HT_ONLY')";
        } elseif ($filter_risk === 'all_risk') {
            $sql .= " AND p.health_status_origin IN ('BOTH', 'DM_ONLY', 'HT_ONLY')";
        } elseif ($filter_risk === 'normal') {
            $sql .= " AND (p.health_status_origin = 'NORMAL' OR p.health_status_origin IS NULL OR p.health_status_origin = '')";
        }
    }

    appendDemographicFilters($sql, 'p');
    $sql .= " ORDER BY p.moo, LENGTH(p.house_no), p.house_no";

} elseif ($filter_source === 'baseline') {
    // Query HDC Baseline Targets
    $sql = "
        SELECT p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.sub_district_code, COALESCE(v.hoscode, p.hoscode) as hoscode,
               p.health_status_origin as risk, p.need_screen_dm, p.need_screen_ht
        FROM target_population p
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ";

    if ($filter_hoscode) {
        $hoscodes = get_query_hoscodes($filter_hoscode);
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $sql .= " AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholders)";
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
            $sql .= " AND p.health_status_origin = 'BOTH'";
        } elseif ($filter_risk === 'risk') {
            $sql .= " AND p.health_status_origin IN ('DM_ONLY', 'HT_ONLY')";
        } elseif ($filter_risk === 'all_risk') {
            $sql .= " AND p.health_status_origin IN ('BOTH', 'DM_ONLY', 'HT_ONLY')";
        } elseif ($filter_risk === 'normal') {
            $sql .= " AND (p.health_status_origin = 'NORMAL' OR p.health_status_origin IS NULL OR p.health_status_origin = '')";
        }
    }

    appendDemographicFilters($sql, 'p');
    $sql .= " ORDER BY p.moo, LENGTH(p.house_no), p.house_no";

} elseif ($filter_source === 'vhv_list') {
    $sql = "
        SELECT v.vhv_name, v.hoscode, v.vhv_moo, v.approved, v.vhid_code,
               (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.vhv_id = v.vhv_id AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)) as assigned_targets,
               (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.vhv_id = v.vhv_id AND a.assignment_status = 'completed' AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)) as completed_screenings,
               (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.vhv_id = v.vhv_id AND a.assignment_status = 'pending' AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)) as pending_screenings,
               (SELECT COUNT(*) FROM task_assignments a JOIN target_population p ON a.target_cid = p.cid WHERE a.vhv_id = v.vhv_id AND a.assignment_status = 'skipped' AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)) as skipped_screenings
        FROM vhv_users v
        WHERE 1=1
    ";

    if ($filter_hoscode) {
        $hoscodes = get_query_hoscodes($filter_hoscode);
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
        SELECT MAX(p.sub_district_code) as sub_district_code, p.moo, COALESCE(v.hoscode, p.hoscode) as hoscode,
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
         LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
         WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ";

    if ($filter_hoscode) {
        $hoscodes = get_query_hoscodes($filter_hoscode);
    } else {
        $hoscodes = get_query_hoscodes();
    }
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
    $sql .= " AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholders)";
    $params = array_merge($params, $hoscodes);

    if ($filter_tambon) {
        $sql .= " AND p.sub_district_code = ?";
        $params[] = $filter_tambon;
    }

    if ($filter_moo) {
        $sql .= " AND p.moo = ?";
        $params[] = $filter_moo;
    }

    appendDemographicFilters($sql, 'p');
    $sql .= " GROUP BY COALESCE(v.hoscode, p.hoscode), p.moo";
    $sql .= " ORDER BY COALESCE(v.hoscode, p.hoscode), p.moo";

} elseif ($filter_source === 'summary_hoscode') {
    // Query Hoscode-level Target and Screening Summary
    $sql = "
        SELECT COALESCE(v.hoscode, p.hoscode) as hoscode,
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
        LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
        WHERE (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
    ";

    if ($filter_hoscode) {
        $hoscodes = get_query_hoscodes($filter_hoscode);
    } else {
        $hoscodes = get_query_hoscodes();
    }
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
    $sql .= " AND COALESCE(v.hoscode, p.hoscode) IN ($inPlaceholders)";
    $params = array_merge($params, $hoscodes);

    appendDemographicFilters($sql, 'p');
    $sql .= " GROUP BY COALESCE(v.hoscode, p.hoscode)";
    $sql .= " ORDER BY COALESCE(v.hoscode, p.hoscode)";
}

$reportData = [];
$totalRecords = 0;
$totalPages = 0;
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

try {
    if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Count total records
        $countSql = "SELECT COUNT(*) FROM ($sql) as sub";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);

        // Paginate query
        $sqlPaginated = $sql . " LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sqlPaginated);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
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
    if ($filter_source === 'all') {
        fputcsv($output, ['ลำดับ', 'เลขบัตรประชาชน', 'ชื่อ-นามสกุล', 'บ้านเลขที่', 'หมู่', 'บ้าน', 'ตำบล', 'รพ.สต.', 'สถานะ', 'ค่าความดันโลหิต', 'ค่าน้ำตาล (DTX)', 'ดัชนีมวลกาย (BMI)', 'ความเสี่ยง (CV Risk)', 'วันที่คัดกรองล่าสุด']);
        foreach ($reportData as $row) {
            $logical_tambon_code = $hoscode_villages[$row['hoscode']]['tambon'] ?? $row['sub_district_code'];
            $tambonName = str_replace('ตำบล', '', $tambons[$logical_tambon_code] ?? $logical_tambon_code);
            $hosName = $hc_names[$row['hoscode']] ?? $row['hoscode'];
            $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($logical_tambon_code, $row['moo']);
            $statusStr = ($row['screen_status'] === 'screened') ? 'คัดกรองแล้ว' : 'ยังไม่คัดกรอง';

            fputcsv($output, [
                $no++,
                "'" . $row['cid'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['house_no'],
                $row['moo'],
                $village_only,
                $tambonName,
                $hosName,
                $statusStr,
                ($row['screen_status'] === 'screened') ? $row['sys_bp1'] . '/' . $row['dia_bp1'] : '-',
                ($row['screen_status'] === 'screened' && $row['dtx_value']) ? $row['dtx_value'] : '-',
                ($row['screen_status'] === 'screened' && $row['bmi']) ? $row['bmi'] : '-',
                ($row['screen_status'] === 'screened' && $row['cv_risk_score'] !== null) ? $row['cv_risk_score'] . '%' : '-',
                ($row['screen_status'] === 'screened' && $row['created_at']) ? $row['created_at'] : '-'
            ]);
        }
    } elseif ($filter_source === 'screened') {
        fputcsv($output, ['ลำดับ', 'เลขบัตรประชาชน', 'ชื่อ-นามสกุล', 'บ้านเลขที่', 'หมู่', 'บ้าน', 'ตำบล', 'รพ.สต.', 'ค่าความดันโลหิต', 'ค่าน้ำตาล (DTX)', 'ดัชนีมวลกาย (BMI)', 'ความเสี่ยง (CV Risk)', 'วันที่คัดกรองล่าสุด']);
        foreach ($reportData as $row) {
            $logical_tambon_code = $hoscode_villages[$row['hoscode']]['tambon'] ?? $row['sub_district_code'];
            $tambonName = str_replace('ตำบล', '', $tambons[$logical_tambon_code] ?? $logical_tambon_code);
            $hosName = $hc_names[$row['hoscode']] ?? $row['hoscode'];
            $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($logical_tambon_code, $row['moo']);

            fputcsv($output, [
                $no++,
                "'" . $row['cid'], // Force string for Excel
                $row['first_name'] . ' ' . $row['last_name'],
                $row['house_no'],
                $row['moo'],
                $village_only,
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
        fputcsv($output, ['ลำดับ', 'เลขบัตรประชาชน', 'ชื่อ-นามสกุล', 'บ้านเลขที่', 'หมู่', 'บ้าน', 'ตำบล', 'รพ.สต.', 'สถานะความเสี่ยงตั้งต้น', 'คัดกรองเบาหวาน', 'คัดกรองความดัน']);
        foreach ($reportData as $row) {
            $logical_tambon_code = $hoscode_villages[$row['hoscode']]['tambon'] ?? $row['sub_district_code'];
            $tambonName = str_replace('ตำบล', '', $tambons[$logical_tambon_code] ?? $logical_tambon_code);
            $hosName = $hc_names[$row['hoscode']] ?? $row['hoscode'];
            $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($logical_tambon_code, $row['moo']);

            fputcsv($output, [
                $no++,
                "'" . $row['cid'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['house_no'],
                $row['moo'],
                $village_only,
                $tambonName,
                $hosName,
                $row['risk'] ?: 'ปกติ',
                $row['need_screen_dm'] ? 'ต้องการ' : 'ไม่ต้อง',
                $row['need_screen_ht'] ? 'ต้องการ' : 'ไม่ต้อง'
            ]);
        }
    } elseif ($filter_source === 'unscreened') {
        fputcsv($output, ['ลำดับ', 'เลขบัตรประชาชน', 'ชื่อ-นามสกุล', 'บ้านเลขที่', 'หมู่', 'บ้าน', 'ตำบล', 'รพ.สต.', 'สถานะความเสี่ยงตั้งต้น', 'คัดกรองเบาหวาน', 'คัดกรองความดัน']);
        foreach ($reportData as $row) {
            $logical_tambon_code = $hoscode_villages[$row['hoscode']]['tambon'] ?? $row['sub_district_code'];
            $tambonName = str_replace('ตำบล', '', $tambons[$logical_tambon_code] ?? $logical_tambon_code);
            $hosName = $hc_names[$row['hoscode']] ?? $row['hoscode'];
            $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($logical_tambon_code, $row['moo']);

            fputcsv($output, [
                $no++,
                "'" . $row['cid'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['house_no'],
                $row['moo'],
                $village_only,
                $tambonName,
                $hosName,
                $row['risk'] ?: 'ปกติ',
                $row['need_screen_dm'] ? 'ต้องการ' : 'ไม่ต้อง',
                $row['need_screen_ht'] ? 'ต้องการ' : 'ไม่ต้อง'
            ]);
        }
    } elseif ($filter_source === 'vhv_list') {
        fputcsv($output, ['ลำดับ', 'ชื่อ-นามสกุล อสม.', 'สังกัด รพ.สต.', 'หมู่บ้านรับผิดชอบ', 'จำนวนเป้าหมายที่มอบหมาย', 'คัดกรองสำเร็จ', 'ค้างดำเนินการ', 'ข้ามชั่วคราว', 'ร้อยละความสำเร็จ', 'สถานะการอนุมัติ']);
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
                $row['pending_screenings'],
                $row['skipped_screenings'],
                $rate . '%',
                $status
            ]);
        }
    } elseif ($filter_source === 'summary_stats') {
        fputcsv($output, ['ลำดับ', 'รพ.สต.', 'หมู่', 'บ้าน', 'ตำบล', 'เป้าหมายทั้งหมด', 'เป้าหมาย DM', 'เป้าหมาย HT', 'คัดกรองเสร็จสิ้น', 'ร้อยละความครอบคลุม', 'กลุ่มปกติ', 'กลุ่มเสี่ยง', 'กลุ่มเสี่ยงสูง']);
        foreach ($reportData as $row) {
            $logical_tambon_code = $hoscode_villages[$row['hoscode']]['tambon'] ?? $row['sub_district_code'];
            $tambonName = str_replace('ตำบล', '', $tambons[$logical_tambon_code] ?? $logical_tambon_code);
            $hosName = $hc_names[$row['hoscode']] ?? $row['hoscode'];
            $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($logical_tambon_code, $row['moo']);

            $rate = $row['total_targets'] > 0 ? round(($row['completed_screenings'] / $row['total_targets']) * 100, 1) : 0;

            fputcsv($output, [
                $no++,
                $hosName,
                $row['moo'],
                $village_only,
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
    } elseif ($filter_source === 'summary_hoscode') {
        fputcsv($output, ['ลำดับ', 'รพ.สต.', 'เป้าหมายทั้งหมด', 'เป้าหมาย DM', 'เป้าหมาย HT', 'คัดกรองเสร็จสิ้น', 'ร้อยละความครอบคลุม', 'กลุ่มปกติ', 'กลุ่มเสี่ยง', 'กลุ่มเสี่ยงสูง']);
        foreach ($reportData as $row) {
            $hosName = $hc_names[$row['hoscode']] ?? $row['hoscode'];
            $rate = $row['total_targets'] > 0 ? round(($row['completed_screenings'] / $row['total_targets']) * 100, 1) : 0;

            fputcsv($output, [
                $no++,
                $hosName,
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
    exit;
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
    <link
        href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800&display=swap"
        rel="stylesheet">
    <style>
        .form-grid-row-1 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-grid-row-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

            .admin-navbar,
            .card-dark:first-of-type,
            .actions-bar,
            .no-print,
            h2.no-print,
            p.no-print {
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
                border-radius: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
                background: transparent !important;
            }

            table.admin-table {
                box-shadow: none !important;
                border: 1px solid #000000 !important;
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 10px;
                border-radius: 0 !important;
            }

            table.admin-table th {
                background: #f2f2f2 !important;
                color: black !important;
                border: 1px solid #000000 !important;
                font-weight: bold !important;
                padding: 8px 4px !important;
                font-size: 13px !important;
                text-align: center !important;
                border-radius: 0 !important;
            }

            table.admin-table td {
                color: black !important;
                border: 1px solid #000000 !important;
                border-radius: 0 !important;
                white-space: normal !important;
                /* Allow wrapping on print if narrow */
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
    <?php include 'navbar.php'; ?>

    <!-- Print Header Only shown on printed pages -->
    <div class="print-header">
        <h2 style="font-family: 'Sarabun', 'TH Sarabun New', 'TH Sarabun PSK', sans-serif;">
            <?php
            if ($filter_source === 'all') {
                echo 'รายงานรายชื่อกลุ่มเป้าหมายคัดกรองทั้งหมด (แยกตามสถานะการคัดกรอง)';
            } elseif ($filter_source === 'vhv_list') {
                echo 'รายงานทำเนียบ อสม. และสถิติผลงานการคัดกรองโรคไม่ติดต่อเรื้อรัง (NCDs)';
            } elseif ($filter_source === 'summary_stats') {
                echo 'รายงานสรุปสถิติจำนวนเป้าหมายและผลคัดกรอง NCDs รายหมู่บ้าน';
            } elseif ($filter_source === 'summary_hoscode') {
                echo 'รายงานสรุปสถิติภาพรวมระดับ รพ.สต.';
            } elseif ($filter_source === 'unscreened') {
                echo 'รายชื่อผู้ที่ยังไม่ได้รับการคัดกรอง (Pending)';
            } else {
                echo 'รายงานรายชื่อกลุ่มเป้าหมายคัดกรองโรคไม่ติดต่อเรื้อรัง (NCDs)';
            }
            ?>
        </h2>
        <p style="font-family: 'Sarabun', 'TH Sarabun New', 'TH Sarabun PSK', sans-serif; font-size: 16px;">อำเภอตาลสุม
            จังหวัดอุบลราชธานี</p>
        <p
            style="font-family: 'Sarabun', 'TH Sarabun New', 'TH Sarabun PSK', sans-serif; font-size: 13px; margin: 8px 0 0 0; font-weight: normal; color: #333;">
            <strong>เงื่อนไขรายงาน:</strong> แหล่งข้อมูล =
            <?= $filter_source == 'all' ? 'รายชื่อทั้งหมด' : ($filter_source == 'screened' ? 'ผลการคัดกรองล่าสุด' : ($filter_source == 'baseline' ? 'เป้าหมายตั้งต้น' : ($filter_source == 'unscreened' ? 'ผู้ตกหล่น' : ($filter_source == 'summary_hoscode' ? 'สรุปภาพรวม รพ.สต.' : ($filter_source == 'vhv_list' ? 'ทำเนียบ อสม.' : 'สรุปเชิงสถิติรายหมู่บ้าน'))))) ?>
            |
            หน่วยบริการ = <?= $filter_hoscode ? ($hc_names[$filter_hoscode] ?? $filter_hoscode) : 'ทุกแห่ง' ?> |
            หมู่ = <?= $filter_moo ? 'หมู่ที่ ' . $filter_moo : 'ทุกหมู่' ?> |
            ระดับความเสี่ยง =
            <?= $filter_risk == 'high' ? 'กลุ่มเสี่ยงสูง' : ($filter_risk == 'risk' ? 'กลุ่มเสี่ยง' : ($filter_risk == 'all_risk' ? 'กลุ่มเสี่ยงทั้งหมด' : ($filter_risk == 'normal' ? 'กลุ่มปกติ' : 'ทั้งหมด'))) ?>
            |
            ประเภทโรค =
            <?= $filter_disease == 'DM' ? 'เบาหวาน (DM)' : ($filter_disease == 'HT' ? 'ความดันโลหิต (HT)' : 'ทั้งหมด') ?>
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
            <h3
                style="color: var(--color-accent); margin-top: 0; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px;">
                🔍 เงื่อนไขรายงาน
            </h3>

            <form method="GET" action="reports.php">
                <!-- Row 1: Report Type & Disease Type -->
                <div class="form-grid-row-1">
                    <!-- Source selection -->
                    <div class="form-group">
                        <label>ประเภทรายงาน</label>
                        <select name="source" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $filter_source === 'all' ? 'selected' : '' ?>>รายชื่อทั้งหมด
                                (ทั้งที่คัดกรองแล้วและยังไม่คัดกรอง)</option>
                            <option value="screened" <?= $filter_source === 'screened' ? 'selected' : '' ?>>รายชื่อรายบุคคล
                                (แยกตามผลการคัดกรองล่าสุด)</option>
                            <option value="baseline" <?= $filter_source === 'baseline' ? 'selected' : '' ?>>รายชื่อรายบุคคล
                                (แยกตามเป้าหมายคัดกรองตั้งต้น HDC)</option>
                            <option value="unscreened" <?= $filter_source === 'unscreened' ? 'selected' : '' ?>>รายชื่อผู้ที่ยังไม่ได้รับการคัดกรอง
                                (Pending/Unscreened)</option>
                            <option value="vhv_list" <?= $filter_source === 'vhv_list' ? 'selected' : '' ?>>ทำเนียบ อสม.
                                และสถิติผลงาน (VHV Directory & Performance)</option>
                            <option value="summary_stats" <?= $filter_source === 'summary_stats' ? 'selected' : '' ?>>
                                รายงานสรุปสถิติจำนวนเป้าหมายและผลคัดกรองรายหมู่บ้าน</option>
                            <option value="summary_hoscode" <?= $filter_source === 'summary_hoscode' ? 'selected' : '' ?>>
                                รายงานสรุปสถิติภาพรวมระดับ รพ.สต.</option>
                        </select>
                    </div>

                    <!-- Disease selection -->
                    <div class="form-group">
                        <label>ประเภทโรค</label>
                        <select name="disease" class="form-select">
                            <option value="">-- ทั้งหมด (DM & HT) --</option>
                            <option value="DM" <?= $filter_disease == 'DM' ? 'selected' : '' ?>>เบาหวาน (DM)</option>
                            <option value="HT" <?= $filter_disease == 'HT' ? 'selected' : '' ?>>ความดันโลหิต (HT)</option>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Hospital, Village, Risk, Gender, Age -->
                <div class="form-grid-row-2">
                    <!-- Hospital Area selection -->
                    <div class="form-group">
                        <label>หน่วยบริการ / รพ.สต.</label>
                        <?php if ($admin_hoscode !== null): ?>
                            <input type="text" class="form-select"
                                value="<?= htmlspecialchars($hc_names[$admin_hoscode]) ?>" readonly
                                style="background-color: rgba(0, 0, 0, 0.1); cursor: not-allowed; font-weight: normal; color: var(--text-muted);">
                            <input type="hidden" name="hoscode" id="hoscode"
                                value="<?= htmlspecialchars($admin_hoscode) ?>">
                        <?php else: ?>
                            <select name="hoscode" id="hoscode" class="form-select" onchange="onHoscodeChange()">
                                <option value="">-- ทุกแห่ง (ทั้งหมด) --</option>
                                <?php foreach ($hc_names as $code => $name): ?>
                                    <option value="<?= $code ?>" <?= ($filter_hoscode == $code) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- Village selection -->
                    <div class="form-group">
                        <label>หมู่ที่</label>
                        <select name="moo" id="moo" class="form-select">
                            <option value="">-- ทุกหมู่บ้าน --</option>
                            <?php
                            $current_hos = $admin_hoscode ?: $filter_hoscode;
                            if (!empty($current_hos) && isset($relations[$current_hos])) {
                                foreach ($relations[$current_hos]['villages'] as $vill) {
                                    $selected = ($filter_moo == $vill['moo']) ? 'selected' : '';
                                    echo '<option value="' . $vill['moo'] . '" ' . $selected . '>หมู่ที่ ' . $vill['moo'] . ' ' . htmlspecialchars($vill['name']) . '</option>';
                                }
                            } else {
                                for ($i = 1; $i <= 15; $i++) {
                                    $selected = ($filter_moo == $i) ? 'selected' : '';
                                    echo '<option value="' . $i . '" ' . $selected . '>หมู่ที่ ' . $i . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Risk selection -->
                    <div class="form-group">
                        <label>ระดับความเสี่ยง</label>
                        <select name="risk" class="form-select">
                            <option value="">-- ทั้งหมด (ทุกกลุ่ม) --</option>
                            <option value="all_risk" <?= $filter_risk == 'all_risk' ? 'selected' : '' ?>>🟠 กลุ่มเสี่ยงทั้งหมด (All Risk)</option>
                            <option value="high" <?= $filter_risk == 'high' ? 'selected' : '' ?>>🔴 กลุ่มเสี่ยงสูง (High Risk)</option>
                            <option value="risk" <?= $filter_risk == 'risk' ? 'selected' : '' ?>>🟡 กลุ่มเสี่ยง (Moderate Risk)</option>
                            <option value="normal" <?= $filter_risk == 'normal' ? 'selected' : '' ?>>🟢 กลุ่มปกติ (Normal)</option>
                        </select>
                    </div>

                    <!-- Gender selection -->
                    <div class="form-group">
                        <label>เพศ</label>
                        <select name="gender" class="form-select">
                            <option value="">-- ทุกเพศ --</option>
                            <option value="1" <?= $filter_gender === '1' ? 'selected' : '' ?>>ชาย (Male)</option>
                            <option value="2" <?= $filter_gender === '2' ? 'selected' : '' ?>>หญิง (Female)</option>
                        </select>
                    </div>

                    <!-- Age selection -->
                    <div class="form-group">
                        <label>ช่วงอายุ</label>
                        <select name="age" class="form-select">
                            <option value="">-- ทุกช่วงอายุ --</option>
                            <option value="35-59" <?= $filter_age === '35-59' ? 'selected' : '' ?>>35-59 ปี (วัยทำงาน)</option>
                            <option value="60+" <?= $filter_age === '60+' ? 'selected' : '' ?>>60 ปีขึ้นไป (ผู้สูงอายุ)</option>
                        </select>
                    </div>
                </div>

                <!-- Action buttons container -->
                <div style="display: flex; justify-content: flex-end; gap: 15px; flex-wrap: wrap; margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                    <!-- Action buttons -->
                    <div class="actions-bar"
                        style="margin-top: 0; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                        <button type="submit" class="btn-primary"
                            style="padding: 10px 24px; border-radius: 20px; border: none; background: var(--color-accent); color: white; cursor: pointer; font-weight: bold; box-shadow: var(--neumorph-flat); height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                            🔍 ค้นหาข้อมูล
                        </button>
                        <button type="button" onclick="window.print()" class="btn-primary"
                            style="padding: 10px 24px; border-radius: 20px; border: none; background: var(--color-green); color: white; cursor: pointer; font-weight: bold; box-shadow: var(--neumorph-flat); height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                            🖨️ พิมพ์รายงาน (Print)
                        </button>
                        <a href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_csv'])) ?>"
                            class="btn-primary"
                            style="padding: 0 24px; border-radius: 20px; border: none; background: #0284c7; color: white; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; box-shadow: var(--neumorph-flat); height: 42px; box-sizing: border-box;">
                            📥 ส่งออกเป็น CSV (Excel)
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Card -->
        <div class="card-dark">
            <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px;"
                class="no-print">
                ตัวอย่างข้อมูลรายงาน (พบทั้งหมด <?= number_format($totalRecords) ?>
                รายการ<?= $totalPages > 1 ? " | หน้าที่ $page/$totalPages" : "" ?>)
            </h3>

            <?php if (isset($queryError)): ?>
                <div
                    style="background-color: rgba(239, 68, 68, 0.15); border: 1px solid var(--color-red); color: var(--color-red); padding: 15px; border-radius: 8px;">
                    ดึงข้อมูลล้มเหลว: <?= htmlspecialchars($queryError) ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <?php if ($filter_source === 'all'): ?>
                                <tr>
                                    <th style="width: 50px;">ลำดับ</th>
                                    <th>เลขบัตรประชาชน</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>บ้านเลขที่</th>
                                    <th>หมู่</th>
                                    <th>บ้าน</th>
                                    <th>ตำบล</th>
                                    <th>รพ.สต.</th>
                                    <th>สถานะ</th>
                                    <th>ความดันโลหิต</th>
                                    <th>ค่าน้ำตาล (DTX)</th>
                                    <th>ดัชนีมวลกาย (BMI)</th>
                                    <th>ความเสี่ยง (CV Risk)</th>
                                    <th>วันที่ตรวจคัดกรอง</th>
                                </tr>
                            <?php elseif ($filter_source === 'screened'): ?>
                                <tr>
                                    <th style="width: 50px;">ลำดับ</th>
                                    <th>เลขบัตรประชาชน</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>บ้านเลขที่</th>
                                    <th>หมู่</th>
                                    <th>บ้าน</th>
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
                                    <th>หมู่</th>
                                    <th>บ้าน</th>
                                    <th>ตำบล</th>
                                    <th>รพ.สต.</th>
                                    <th>ความเสี่ยงตั้งต้น HDC</th>
                                    <th>ตรวจ DM</th>
                                    <th>ตรวจ HT</th>
                                </tr>
                            <?php elseif ($filter_source === 'unscreened'): ?>
                                <tr>
                                    <th style="width: 50px;">ลำดับ</th>
                                    <th>เลขบัตรประชาชน</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>บ้านเลขที่</th>
                                    <th>หมู่</th>
                                    <th>บ้าน</th>
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
                                    <th>สำเร็จ (ราย)</th>
                                    <th>ค้าง (ราย)</th>
                                    <th>ข้าม (ราย)</th>
                                    <th>ร้อยละความสำเร็จ</th>
                                    <th>สถานะสิทธิ์</th>
                                </tr>
                            <?php elseif ($filter_source === 'summary_stats'): ?>
                                <tr>
                                    <th style="width: 50px;">ลำดับ</th>
                                    <th>หน่วยบริการ / รพ.สต.</th>
                                    <th>หมู่</th>
                                    <th>บ้าน</th>
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
                            <?php elseif ($filter_source === 'summary_hoscode'): ?>
                                <tr>
                                    <th style="width: 50px;">ลำดับ</th>
                                    <th>หน่วยบริการ / รพ.สต.</th>
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
                                    $colspan = 13;
                                    if ($filter_source === 'all')
                                        $colspan = 14;
                                    elseif ($filter_source === 'baseline' || $filter_source === 'unscreened')
                                        $colspan = 11;
                                    elseif ($filter_source === 'vhv_list')
                                        $colspan = 10;
                                    elseif ($filter_source === 'summary_stats')
                                        $colspan = 13;
                                    elseif ($filter_source === 'summary_hoscode')
                                        $colspan = 10;
                                    ?>
                                    <td colspan="<?= $colspan ?>"
                                        style="text-align: center; color: var(--text-secondary); padding: 24px;">
                                        ไม่พบรายการที่ตรงกับเงื่อนไขตัวกรอง</td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $no = 1;
                                foreach ($reportData as $row):
                                    ?>
                                    <?php if ($filter_source === 'all'): ?>
                                        <?php
                                        $logical_tambon_code = $hoscode_villages[$row['hoscode']]['tambon'] ?? $row['sub_district_code'];
                                        $tambonName = str_replace('ตำบล', '', $tambons[$logical_tambon_code] ?? $logical_tambon_code);
                                        $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($logical_tambon_code, $row['moo']);
                                        ?>
                                        <tr>
                                            <td style="text-align: center;"><?= $no++ ?></td>
                                            <td><?= htmlspecialchars((strpos($row['cid'], '*') === false) ? $row['cid'] : (substr($row['cid'], 0, 4) . '-XXXXX-' . substr($row['cid'], -3))) ?>
                                            </td>
                                            <td><strong
                                                    style="color: var(--text-primary);"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($row['house_no']) ?></td>
                                            <td style="text-align: center;"><?= htmlspecialchars($row['moo']) ?></td>
                                            <td><?= htmlspecialchars($village_only) ?></td>
                                            <td><?= htmlspecialchars($tambonName) ?></td>
                                            <td><span
                                                    style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($hc_names[$row['hoscode']] ?? $row['hoscode']) ?></span>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($row['screen_status'] === 'screened'): ?>
                                                    <span style="color: var(--color-green); font-weight: bold;">🟢 คัดกรองแล้ว</span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">⚪ ยังไม่คัดกรอง</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['screen_status'] === 'screened'): ?>
                                                    <?php if ($row['sys_bp1'] >= 140 || $row['dia_bp1'] >= 90): ?>
                                                        <span
                                                            style="color: var(--color-red); font-weight: bold;"><?= $row['sys_bp1'] ?>/<?= $row['dia_bp1'] ?></span>
                                                    <?php else: ?>
                                                        <?= $row['sys_bp1'] ?>/<?= $row['dia_bp1'] ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['screen_status'] === 'screened'): ?>
                                                    <?php if ($row['dtx_value'] >= 126): ?>
                                                        <span
                                                            style="color: var(--color-red); font-weight: bold;"><?= $row['dtx_value'] ?></span>
                                                    <?php else: ?>
                                                        <?= $row['dtx_value'] ?: '-' ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars(($row['screen_status'] === 'screened' && $row['bmi']) ? $row['bmi'] : '-') ?></td>
                                            <td>
                                                <?php if ($row['screen_status'] === 'screened'): ?>
                                                    <?php if ($row['cv_risk_score'] >= 10): ?>
                                                        <span
                                                            style="background-color: rgba(239, 68, 68, 0.15); color: var(--color-red); padding: 4px 8px; border-radius: 4px; font-weight: bold;"><?= $row['cv_risk_score'] ?>%</span>
                                                    <?php else: ?>
                                                        <?= $row['cv_risk_score'] !== null ? $row['cv_risk_score'] . '%' : '-' ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 11px; color: var(--text-muted);">
                                                <?= htmlspecialchars(($row['screen_status'] === 'screened' && $row['created_at']) ? $row['created_at'] : '-') ?></td>
                                        </tr>
                                    <?php elseif ($filter_source === 'screened'): ?>
                                        <?php
                                        $logical_tambon_code = $hoscode_villages[$row['hoscode']]['tambon'] ?? $row['sub_district_code'];
                                        $tambonName = str_replace('ตำบล', '', $tambons[$logical_tambon_code] ?? $logical_tambon_code);
                                        $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($logical_tambon_code, $row['moo']);
                                        ?>
                                        <tr>
                                            <td style="text-align: center;"><?= $no++ ?></td>
                                            <td><?= htmlspecialchars((strpos($row['cid'], '*') === false) ? $row['cid'] : (substr($row['cid'], 0, 4) . '-XXXXX-' . substr($row['cid'], -3))) ?>
                                            </td>
                                            <td><strong
                                                    style="color: var(--text-primary);"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($row['house_no']) ?></td>
                                            <td style="text-align: center;"><?= htmlspecialchars($row['moo']) ?></td>
                                            <td><?= htmlspecialchars($village_only) ?></td>
                                            <td><?= htmlspecialchars(str_replace('ตำบล', '', $tambons[$row['sub_district_code']] ?? $row['sub_district_code'])) ?>
                                            </td>
                                            <td><span
                                                    style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($hc_names[$row['hoscode']] ?? $row['hoscode']) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($row['sys_bp1'] >= 140 || $row['dia_bp1'] >= 90): ?>
                                                    <span
                                                        style="color: var(--color-red); font-weight: bold;"><?= $row['sys_bp1'] ?>/<?= $row['dia_bp1'] ?></span>
                                                <?php else: ?>
                                                    <?= $row['sys_bp1'] ?>/<?= $row['dia_bp1'] ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['dtx_value'] >= 126): ?>
                                                    <span
                                                        style="color: var(--color-red); font-weight: bold;"><?= $row['dtx_value'] ?></span>
                                                <?php else: ?>
                                                    <?= $row['dtx_value'] ?: '-' ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['bmi'] ?: '-') ?></td>
                                            <td>
                                                <?php if ($row['cv_risk_score'] >= 10): ?>
                                                    <span
                                                        style="background-color: rgba(239, 68, 68, 0.15); color: var(--color-red); padding: 4px 8px; border-radius: 4px; font-weight: bold;"><?= $row['cv_risk_score'] ?>%</span>
                                                <?php else: ?>
                                                    <?= $row['cv_risk_score'] !== null ? $row['cv_risk_score'] . '%' : '-' ?>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 11px; color: var(--text-muted);">
                                                <?= htmlspecialchars($row['created_at']) ?></td>
                                        </tr>
                                    <?php elseif ($filter_source === 'baseline'): ?>
                                        <?php
                                        $logical_tambon_code = $hoscode_villages[$row['hoscode']]['tambon'] ?? $row['sub_district_code'];
                                        $tambonName = str_replace('ตำบล', '', $tambons[$logical_tambon_code] ?? $logical_tambon_code);
                                        $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($logical_tambon_code, $row['moo']);
                                        ?>
                                        <tr>
                                            <td style="text-align: center;"><?= $no++ ?></td>
                                            <td><?= htmlspecialchars((strpos($row['cid'], '*') === false) ? $row['cid'] : (substr($row['cid'], 0, 4) . '-XXXXX-' . substr($row['cid'], -3))) ?>
                                            </td>
                                            <td><strong
                                                    style="color: var(--text-primary);"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($row['house_no']) ?></td>
                                            <td style="text-align: center;"><?= htmlspecialchars($row['moo']) ?></td>
                                            <td><?= htmlspecialchars($village_only) ?></td>
                                            <td><?= htmlspecialchars($tambonName) ?>
                                            </td>
                                            <td><span
                                                    style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($hc_names[$row['hoscode']] ?? $row['hoscode']) ?></span>
                                            </td>
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
                                    <?php elseif ($filter_source === 'unscreened'): ?>
                                        <?php
                                        $logical_tambon_code = $hoscode_villages[$row['hoscode']]['tambon'] ?? $row['sub_district_code'];
                                        $tambonName = str_replace('ตำบล', '', $tambons[$logical_tambon_code] ?? $logical_tambon_code);
                                        $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($logical_tambon_code, $row['moo']);
                                        ?>
                                        <tr>
                                            <td style="text-align: center;"><?= $no++ ?></td>
                                            <td><?= htmlspecialchars((strpos($row['cid'], '*') === false) ? $row['cid'] : (substr($row['cid'], 0, 4) . '-XXXXX-' . substr($row['cid'], -3))) ?>
                                            </td>
                                            <td><strong
                                                    style="color: var(--text-primary);"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($row['house_no']) ?></td>
                                            <td style="text-align: center;"><?= htmlspecialchars($row['moo']) ?></td>
                                            <td><?= htmlspecialchars($village_only) ?></td>
                                            <td><?= htmlspecialchars($tambonName) ?>
                                            </td>
                                            <td><span
                                                    style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($hc_names[$row['hoscode']] ?? $row['hoscode']) ?></span>
                                            </td>
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
                                            <td><strong
                                                    style="color: var(--text-primary);"><?= htmlspecialchars($row['vhv_name']) ?></strong>
                                            </td>
                                            <td><span
                                                    style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($hc_names[$row['hoscode']] ?? $row['hoscode']) ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($village_full) ?></td>
                                            <td style="text-align: center;"><?= number_format($row['assigned_targets']) ?></td>
                                            <td style="text-align: center; color: var(--color-green); font-weight: bold;">
                                                <?= number_format($row['completed_screenings']) ?></td>
                                            <td style="text-align: center; color: var(--color-primary); font-weight: bold;">
                                                <?= number_format($row['pending_screenings']) ?></td>
                                            <td style="text-align: center; color: var(--color-yellow); font-weight: bold;">
                                                <?= number_format($row['skipped_screenings']) ?></td>
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
                                        $logical_tambon_code = $hoscode_villages[$row['hoscode']]['tambon'] ?? $row['sub_district_code'];
                                        $tambonName = str_replace('ตำบล', '', $tambons[$logical_tambon_code] ?? $logical_tambon_code);
                                        $village_only = $hoscode_villages[$row['hoscode']]['villages'][intval($row['moo'])] ?? get_village_only_name($logical_tambon_code, $row['moo']);
                                        $rate = $row['total_targets'] > 0 ? round(($row['completed_screenings'] / $row['total_targets']) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td style="text-align: center;"><?= $no++ ?></td>
                                            <td><span
                                                    style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($hc_names[$row['hoscode']] ?? $row['hoscode']) ?></span>
                                            </td>
                                            <td style="text-align: center;"><?= htmlspecialchars($row['moo']) ?></td>
                                            <td><strong
                                                    style="color: var(--text-primary);"><?= htmlspecialchars($village_only) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($tambonName) ?>
                                            </td>
                                            <td style="text-align: center; font-weight: bold;">
                                                <?= number_format($row['total_targets']) ?></td>
                                            <td style="text-align: center;"><?= number_format($row['targets_dm']) ?></td>
                                            <td style="text-align: center;"><?= number_format($row['targets_ht']) ?></td>
                                            <td style="text-align: center; color: var(--color-green); font-weight: bold;">
                                                <?= number_format($row['completed_screenings']) ?></td>
                                            <td style="text-align: center; font-weight: bold; color: var(--color-primary);">
                                                <?= $rate ?>%</td>
                                            <td style="text-align: center; color: var(--color-green); font-weight: bold;">
                                                <?= number_format($row['normal_risk_count']) ?></td>
                                            <td style="text-align: center; color: var(--color-yellow); font-weight: bold;">
                                                <?= number_format($row['moderate_risk_count']) ?></td>
                                            <td style="text-align: center; color: var(--color-red); font-weight: bold;">
                                                <?= number_format($row['high_risk_count']) ?></td>
                                        </tr>
                                    <?php elseif ($filter_source === 'summary_hoscode'): ?>
                                        <?php
                                        $rate = $row['total_targets'] > 0 ? round(($row['completed_screenings'] / $row['total_targets']) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td style="text-align: center;"><?= $no++ ?></td>
                                            <td><span
                                                    style="font-size: 13px; color: var(--text-primary); font-weight: bold;"><?= htmlspecialchars($hc_names[$row['hoscode']] ?? $row['hoscode']) ?></span>
                                            </td>
                                            <td style="text-align: center; font-weight: bold;">
                                                <?= number_format($row['total_targets']) ?></td>
                                            <td style="text-align: center;"><?= number_format($row['targets_dm']) ?></td>
                                            <td style="text-align: center;"><?= number_format($row['targets_ht']) ?></td>
                                            <td style="text-align: center; color: var(--color-green); font-weight: bold;">
                                                <?= number_format($row['completed_screenings']) ?></td>
                                            <td style="text-align: center; font-weight: bold; color: var(--color-primary);">
                                                <?= $rate ?>%</td>
                                            <td style="text-align: center; color: var(--color-green); font-weight: bold;">
                                                <?= number_format($row['normal_risk_count']) ?></td>
                                            <td style="text-align: center; color: var(--color-yellow); font-weight: bold;">
                                                <?= number_format($row['moderate_risk_count']) ?></td>
                                            <td style="text-align: center; color: var(--color-red); font-weight: bold;">
                                                <?= number_format($row['high_risk_count']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination (Hidden on Print) -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination no-print" style="margin-top: 25px; margin-bottom: 10px;">
                        <?php
                        $startPage = max(1, $page - 3);
                        $endPage = min($totalPages, $page + 3);

                        $queryParams = $_GET;

                        if ($startPage > 1) {
                            $queryParams['page'] = 1;
                            echo '<a href="?' . http_build_query($queryParams) . '" class="page-link">1</a>';
                            if ($startPage > 2)
                                echo '<span style="padding: 6px; color: var(--text-secondary);">...</span>';
                        }

                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $active = ($i == $page) ? 'active' : '';
                            $queryParams['page'] = $i;
                            echo '<a href="?' . http_build_query($queryParams) . '" class="page-link ' . $active . '">' . $i . '</a>';
                        }

                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1)
                                echo '<span style="padding: 6px; color: var(--text-secondary);">...</span>';
                            $queryParams['page'] = $totalPages;
                            echo '<a href="?' . http_build_query($queryParams) . '" class="page-link">' . $totalPages . '</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const relations = <?= json_encode($relations, JSON_UNESCAPED_UNICODE) ?>;

        function onHoscodeChange() {
            const hSelect = document.getElementById('hoscode');
            const mSelect = document.getElementById('moo');
            if (!mSelect) return;

            const hCode = hSelect ? hSelect.value : "";
            const savedMoo = mSelect.value;

            if (hCode && relations[hCode]) {
                // Populate villages specifically for this hoscode
                populateMooSelect(relations[hCode].villages, savedMoo);
            } else {
                // Generic 1-15
                populateMooSelect([], savedMoo, true);
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
            const mSelect = document.getElementById('moo');

            const initialHoscode = hSelect ? hSelect.value : "";
            const initialMoo = "<?= $filter_moo ?>";

            if (initialHoscode) {
                onHoscodeChange();
                if (mSelect) mSelect.value = initialMoo;
            } else {
                populateMooSelect([], initialMoo, true);
            }
        });
    </script>
</body>

</html>