<?php
// admin/surveillance_reports.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/demo_data.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_title = function_exists('get_admin_title') ? get_admin_title() : 'ผู้ดูแลระบบ';
$is_super_admin = !empty($_SESSION['is_super_admin']);
$hc_names = function_exists('get_health_units') ? get_health_units() : [];

$selected_hoscode = $_GET['hoscode'] ?? ($admin_hoscode ?? '');
$selected_tab = $_GET['tab'] ?? 'tab1';

// Pagination parameters
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? min(200, max(10, (int)$_GET['limit'])) : 25;
$offset = ($page - 1) * $limit;

$activeBudgetYear = isset($_GET['budget_year']) && is_numeric($_GET['budget_year'])
    ? (int)$_GET['budget_year']
    : (isset($_SESSION['active_budget_year']) ? (int)$_SESSION['active_budget_year'] : (function_exists('get_current_budget_year') ? get_current_budget_year() : 2026));

$tambons = [];
try {
    $stmt = $pdo->query("SELECT sub_district_code, CONCAT('ตำบล', sub_district_name) FROM sub_districts ORDER BY sub_district_code ASC");
    $tambons = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (\Exception $e) {
    $tambons = ['341801' => 'ตำบลตาลสุม', '341802' => 'ตำบลสำโรง', '341803' => 'ตำบลจิกเทิง', '341804' => 'ตำบลหนองกุง', '341805' => 'ตำบลนาคาย', '341806' => 'ตำบลคำหว้า'];
}

// -------------------------------------------------------------
// DIMENSION 1: ทะเบียนติดตามกลุ่มเสี่ยง / เยี่ยมบ้าน (Risk Registry)
// -------------------------------------------------------------
$dim1_total = 0;
$dim1_data = [];
try {
    $countSql1 = "
        SELECT COUNT(*)
        FROM screening_results sr
        JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
        JOIN target_population p ON ta.target_cid = p.cid
        WHERE (sr.care_level IN ('fair', 'poor', 'critical') OR sr.sys_bp1 >= 130 OR sr.dtx_value >= 100)
    ";
    $paramsCount1 = [];
    if (!empty($selected_hoscode)) {
        $countSql1 .= " AND p.hoscode = ?";
        $paramsCount1[] = $selected_hoscode;
    }
    $stmtCount1 = $pdo->prepare($countSql1);
    $stmtCount1->execute($paramsCount1);
    $dim1_total = (int)$stmtCount1->fetchColumn();

    if ($selected_tab === 'tab1' || empty($selected_tab)) {
        $sql1 = "
            SELECT p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
                   sr.sys_bp1, sr.dia_bp1, sr.dtx_value, sr.bmi,
                   COALESCE(sr.care_level, 'good') AS care_level,
                   sr.next_visit_date,
                   COALESCE(sr.health_progress, 'baseline') AS health_progress,
                   COALESCE(sr.sleep_quality, 'good') AS sleep_quality,
                   sr.created_at AS last_screen_date,
                   v.vhv_name
            FROM screening_results sr
            JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
            JOIN target_population p ON ta.target_cid = p.cid
            LEFT JOIN vhv_users v ON ta.vhv_id = v.vhv_id
            WHERE (sr.care_level IN ('fair', 'poor', 'critical') OR sr.sys_bp1 >= 130 OR sr.dtx_value >= 100)
        ";
        $params1 = [];
        if (!empty($selected_hoscode)) {
            $sql1 .= " AND p.hoscode = ?";
            $params1[] = $selected_hoscode;
        }
        $sql1 .= " ORDER BY sr.care_level = 'critical' DESC, sr.care_level = 'poor' DESC, sr.next_visit_date ASC LIMIT $limit OFFSET $offset";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->execute($params1);
        $dim1_data = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Exception $e) { $dim1_data = []; }

// -------------------------------------------------------------
// DIMENSION 2: กลุ่มที่ควรตรวจซ้ำ (Retest Due)
// -------------------------------------------------------------
$dim2_total = 0;
$dim2_data = [];
try {
    $countSql2 = "
        SELECT COUNT(*)
        FROM screening_results sr
        JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
        JOIN target_population p ON ta.target_cid = p.cid
        WHERE sr.round_number = 1 
          AND ( (sr.sys_bp1 BETWEEN 130 AND 159 OR sr.dia_bp1 BETWEEN 85 AND 99) OR (sr.dtx_value BETWEEN 100 AND 125) )
          AND NOT EXISTS (
              SELECT 1 FROM screening_results sr2 
              JOIN task_assignments ta2 ON sr2.assignment_id = ta2.assignment_id 
              WHERE ta2.target_cid = p.cid AND sr2.round_number >= 2
          )
    ";
    $paramsCount2 = [];
    if (!empty($selected_hoscode)) {
        $countSql2 .= " AND p.hoscode = ?";
        $paramsCount2[] = $selected_hoscode;
    }
    $stmtCount2 = $pdo->prepare($countSql2);
    $stmtCount2->execute($paramsCount2);
    $dim2_total = (int)$stmtCount2->fetchColumn();

    if ($selected_tab === 'tab2') {
        $sql2 = "
            SELECT p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
                   sr.sys_bp1, sr.dia_bp1, sr.dtx_value, sr.round_number, sr.created_at AS round1_date,
                   DATEDIFF(CURDATE(), sr.created_at) AS days_since_r1,
                   v.vhv_name
            FROM screening_results sr
            JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
            JOIN target_population p ON ta.target_cid = p.cid
            LEFT JOIN vhv_users v ON ta.vhv_id = v.vhv_id
            WHERE sr.round_number = 1 
              AND ( (sr.sys_bp1 BETWEEN 130 AND 159 OR sr.dia_bp1 BETWEEN 85 AND 99) OR (sr.dtx_value BETWEEN 100 AND 125) )
              AND NOT EXISTS (
                  SELECT 1 FROM screening_results sr2 
                  JOIN task_assignments ta2 ON sr2.assignment_id = ta2.assignment_id 
                  WHERE ta2.target_cid = p.cid AND sr2.round_number >= 2
              )
        ";
        $params2 = [];
        if (!empty($selected_hoscode)) {
            $sql2 .= " AND p.hoscode = ?";
            $params2[] = $selected_hoscode;
        }
        $sql2 .= " ORDER BY days_since_r1 DESC LIMIT $limit OFFSET $offset";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute($params2);
        $dim2_data = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Exception $e) { $dim2_data = []; }

// -------------------------------------------------------------
// DIMENSION 3: กลุ่มที่ขาดการติดตามในรอบเดือน (Overdue Followup)
// -------------------------------------------------------------
$dim3_total = 0;
$dim3_data = [];
try {
    $countSql3 = "
        SELECT COUNT(*)
        FROM screening_results sr
        JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
        JOIN target_population p ON ta.target_cid = p.cid
        WHERE sr.next_visit_date IS NOT NULL AND sr.next_visit_date < CURDATE()
    ";
    $paramsCount3 = [];
    if (!empty($selected_hoscode)) {
        $countSql3 .= " AND p.hoscode = ?";
        $paramsCount3[] = $selected_hoscode;
    }
    $stmtCount3 = $pdo->prepare($countSql3);
    $stmtCount3->execute($paramsCount3);
    $dim3_total = (int)$stmtCount3->fetchColumn();

    if ($selected_tab === 'tab3') {
        $sql3 = "
            SELECT p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
                   sr.care_level, sr.next_visit_date,
                   DATEDIFF(CURDATE(), sr.next_visit_date) AS overdue_days,
                   sr.sys_bp1, sr.dia_bp1, sr.dtx_value,
                   v.vhv_name
            FROM screening_results sr
            JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
            JOIN target_population p ON ta.target_cid = p.cid
            LEFT JOIN vhv_users v ON ta.vhv_id = v.vhv_id
            WHERE sr.next_visit_date IS NOT NULL AND sr.next_visit_date < CURDATE()
        ";
        $params3 = [];
        if (!empty($selected_hoscode)) {
            $sql3 .= " AND p.hoscode = ?";
            $params3[] = $selected_hoscode;
        }
        $sql3 .= " ORDER BY overdue_days DESC LIMIT $limit OFFSET $offset";
        $stmt3 = $pdo->prepare($sql3);
        $stmt3->execute($params3);
        $dim3_data = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Exception $e) { $dim3_data = []; }

// -------------------------------------------------------------
// DIMENSION 4: กลุ่มที่ไม่เคยได้รับการคัดกรอง (Unscreened Population)
// -------------------------------------------------------------
$dim4_total = 0;
$dim4_data = [];
try {
    $countSql4 = "
        SELECT COUNT(*)
        FROM target_population p
        LEFT JOIN task_assignments ta ON p.cid = ta.target_cid AND ta.budget_year = ?
        WHERE (ta.assignment_status IS NULL OR ta.assignment_status = 'pending')
          AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35
    ";
    $paramsCount4 = [$activeBudgetYear];
    if (!empty($selected_hoscode)) {
        $countSql4 .= " AND p.hoscode = ?";
        $paramsCount4[] = $selected_hoscode;
    }
    $stmtCount4 = $pdo->prepare($countSql4);
    $stmtCount4->execute($paramsCount4);
    $dim4_total = (int)$stmtCount4->fetchColumn();

    if ($selected_tab === 'tab4') {
        $sql4 = "
            SELECT p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
                   TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) AS age,
                   p.sex, p.health_status_origin,
                   v.vhv_name
            FROM target_population p
            LEFT JOIN task_assignments ta ON p.cid = ta.target_cid AND ta.budget_year = ?
            LEFT JOIN vhv_users v ON ta.vhv_id = v.vhv_id
            WHERE (ta.assignment_status IS NULL OR ta.assignment_status = 'pending')
              AND TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35
        ";
        $params4 = [$activeBudgetYear];
        if (!empty($selected_hoscode)) {
            $sql4 .= " AND p.hoscode = ?";
            $params4[] = $selected_hoscode;
        }
        $sql4 .= " ORDER BY LENGTH(p.house_no), p.house_no LIMIT $limit OFFSET $offset";
        $stmt4 = $pdo->prepare($sql4);
        $stmt4->execute($params4);
        $dim4_data = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Exception $e) { $dim4_data = []; }

// -------------------------------------------------------------
// DIMENSION 5: ผลสัมฤทธิ์โครงการ & คุณภาพการนอนหลับ (Outcome & Sleep)
// -------------------------------------------------------------
$dim5_stats = [
    'improved' => 0, 'stable' => 0, 'worsened' => 0, 'baseline' => 0,
    'sleep_good' => 0, 'sleep_restless' => 0, 'sleep_poor' => 0,
    'total_screened' => 0
];
try {
    $sql5 = "
        SELECT health_progress, sleep_quality, COUNT(*) as cnt
        FROM screening_results sr
        JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
        JOIN target_population p ON ta.target_cid = p.cid
        WHERE 1=1
    ";
    $params5 = [];
    if (!empty($selected_hoscode)) {
        $sql5 .= " AND p.hoscode = ?";
        $params5[] = $selected_hoscode;
    }
    $sql5 .= " GROUP BY health_progress, sleep_quality";
    $stmt5 = $pdo->prepare($sql5);
    $stmt5->execute($params5);
    $rows5 = $stmt5->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows5 as $r) {
        $cnt = (int)$r['cnt'];
        $dim5_stats['total_screened'] += $cnt;
        if ($r['health_progress'] === 'improved') $dim5_stats['improved'] += $cnt;
        elseif ($r['health_progress'] === 'worsened') $dim5_stats['worsened'] += $cnt;
        elseif ($r['health_progress'] === 'stable') $dim5_stats['stable'] += $cnt;
        else $dim5_stats['baseline'] += $cnt;

        if ($r['sleep_quality'] === 'good') $dim5_stats['sleep_good'] += $cnt;
        elseif ($r['sleep_quality'] === 'restless') $dim5_stats['sleep_restless'] += $cnt;
        elseif ($r['sleep_quality'] === 'poor') $dim5_stats['sleep_poor'] += $cnt;
    }
} catch (\Exception $e) {}

// -------------------------------------------------------------
// DIMENSION 6: ตรวจสอบคุณภาพข้อมูล (Data Quality Audit)
// -------------------------------------------------------------
$dim6_total = 0;
$dim6_anomalies = [];
try {
    $countSql6 = "
        SELECT COUNT(*)
        FROM screening_results sr
        JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
        JOIN target_population p ON ta.target_cid = p.cid
        WHERE (sr.sys_bp1 > 260 OR sr.sys_bp1 < 50)
           OR (sr.dia_bp1 > 160 OR sr.dia_bp1 < 30)
           OR (sr.sys_bp1 <= sr.dia_bp1 AND sr.sys_bp1 > 0)
           OR (sr.dtx_value > 600 OR (sr.dtx_value < 30 AND sr.dtx_value > 0))
           OR (sr.weight > 250 OR (sr.weight < 25 AND sr.weight > 0))
           OR (sr.height > 230 OR (sr.height < 100 AND sr.height > 0))
    ";
    $paramsCount6 = [];
    if (!empty($selected_hoscode)) {
        $countSql6 .= " AND p.hoscode = ?";
        $paramsCount6[] = $selected_hoscode;
    }
    $stmtCount6 = $pdo->prepare($countSql6);
    $stmtCount6->execute($paramsCount6);
    $dim6_total = (int)$stmtCount6->fetchColumn();

    if ($selected_tab === 'tab6') {
        $sql6 = "
            SELECT sr.screening_id, p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
                   sr.sys_bp1, sr.dia_bp1, sr.dtx_value, sr.weight, sr.height, sr.bmi,
                   v.vhv_name,
                   CASE
                       WHEN sr.sys_bp1 > 260 OR sr.sys_bp1 < 50 THEN 'ค่าความดันตัวบนผิดปกติ (Out of Range)'
                       WHEN sr.dia_bp1 > 160 OR sr.dia_bp1 < 30 THEN 'ค่าความดันตัวล่างผิดปกติ (Out of Range)'
                       WHEN sr.sys_bp1 <= sr.dia_bp1 THEN 'ความดันตัวบนน้อยกว่าหรือเท่ากับตัวล่าง (SYS <= DIA)'
                       WHEN sr.dtx_value > 600 OR sr.dtx_value < 20 THEN 'ค่าน้ำตาลผิดปกติ (Out of Range)'
                       WHEN sr.weight > 250 OR sr.weight < 25 THEN 'ค่าน้ำหนักตัวผิดปกติ'
                       WHEN sr.height > 230 OR sr.height < 100 THEN 'ค่าส่วนสูงผิดปกติ'
                       ELSE 'ตรวจพบค่าผิดปกติ'
                   END AS anomaly_reason
            FROM screening_results sr
            JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
            JOIN target_population p ON ta.target_cid = p.cid
            LEFT JOIN vhv_users v ON ta.vhv_id = v.vhv_id
            WHERE (sr.sys_bp1 > 260 OR sr.sys_bp1 < 50)
               OR (sr.dia_bp1 > 160 OR sr.dia_bp1 < 30)
               OR (sr.sys_bp1 <= sr.dia_bp1 AND sr.sys_bp1 > 0)
               OR (sr.dtx_value > 600 OR (sr.dtx_value < 30 AND sr.dtx_value > 0))
               OR (sr.weight > 250 OR (sr.weight < 25 AND sr.weight > 0))
               OR (sr.height > 230 OR (sr.height < 100 AND sr.height > 0))
        ";
        $params6 = [];
        if (!empty($selected_hoscode)) {
            $sql6 .= " AND p.hoscode = ?";
            $params6[] = $selected_hoscode;
        }
        $sql6 .= " LIMIT $limit OFFSET $offset";
        $stmt6 = $pdo->prepare($sql6);
        $stmt6->execute($params6);
        $dim6_anomalies = $stmt6->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Exception $e) { $dim6_anomalies = []; }

// Helper Functions for Pagination and Section Headers
function render_surveillance_table_header($title, $iconName, $iconClass, $totalRecords, $page, $limit, $tab, $tableId, $exportFilename) {
    global $selected_hoscode;
    $startItem = $totalRecords > 0 ? ($page - 1) * $limit + 1 : 0;
    $endItem = min($totalRecords, $page * $limit);
    ?>
    <div class="pagination-header-info">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <h4 style="margin: 0; color: var(--color-accent); font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                <?= render_neu_icon($iconName, 'sm', $iconClass) ?>
                <span><?= htmlspecialchars($title) ?></span>
            </h4>
            <span class="pagination-records-info">
                (แสดง <?= number_format($startItem) ?> - <?= number_format($endItem) ?> จากทั้งหมด <?= number_format($totalRecords) ?> รายการ)
            </span>
        </div>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <label for="limit-select-<?= $tab ?>" style="font-size: 12px; font-weight: 700; color: var(--text-secondary);">แสดง:</label>
                <select id="limit-select-<?= $tab ?>" class="per-page-select" onchange="changeLimit(this.value, '<?= $tab ?>')">
                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25 แถว/หน้า</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 แถว/หน้า</option>
                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 แถว/หน้า</option>
                    <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200 แถว/หน้า</option>
                </select>
            </div>
            <button type="button" class="btn-export-excel" onclick="exportTableToExcel('<?= $tableId ?>', '<?= htmlspecialchars($exportFilename, ENT_QUOTES) ?>')">
                📥 ส่งออก Excel
            </button>
        </div>
    </div>
    <?php
}

function render_surveillance_pagination($totalRecords, $page, $limit, $tab, $selected_hoscode) {
    $totalPages = max(1, (int)ceil($totalRecords / $limit));
    if ($totalPages <= 1 && $totalRecords <= $limit) {
        return;
    }
    
    $buildUrl = function($p) use ($tab, $selected_hoscode, $limit) {
        $params = ['tab' => $tab, 'page' => $p, 'limit' => $limit];
        if (!empty($selected_hoscode)) $params['hoscode'] = $selected_hoscode;
        return '?' . http_build_query($params);
    };

    echo '<div class="pagination no-print">';
    
    // First & Previous Page
    if ($page > 1) {
        echo '<a href="' . $buildUrl(1) . '" class="page-link" title="หน้าแรก">«</a>';
        echo '<a href="' . $buildUrl($page - 1) . '" class="page-link" title="ก่อนหน้า">‹</a>';
    } else {
        echo '<span class="page-link disabled">«</span>';
        echo '<span class="page-link disabled">‹</span>';
    }
    
    // Page Numbers with Window
    $startPage = max(1, $page - 2);
    $endPage = min($totalPages, $page + 2);
    
    if ($startPage > 1) {
        echo '<a href="' . $buildUrl(1) . '" class="page-link ' . ($page == 1 ? 'active' : '') . '">1</a>';
        if ($startPage > 2) {
            echo '<span style="padding: 0 4px; color: var(--text-secondary); line-height: 38px;">...</span>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        $active = ($i == $page) ? 'active' : '';
        echo '<a href="' . $buildUrl($i) . '" class="page-link ' . $active . '">' . $i . '</a>';
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            echo '<span style="padding: 0 4px; color: var(--text-secondary); line-height: 38px;">...</span>';
        }
        echo '<a href="' . $buildUrl($totalPages) . '" class="page-link ' . ($page == $totalPages ? 'active' : '') . '">' . $totalPages . '</a>';
    }
    
    // Next & Last Page
    if ($page < $totalPages) {
        echo '<a href="' . $buildUrl($page + 1) . '" class="page-link" title="ถัดไป">›</a>';
        echo '<a href="' . $buildUrl($totalPages) . '" class="page-link" title="หน้าสุดท้าย">»</a>';
    } else {
        echo '<span class="page-link disabled">›</span>';
        echo '<span class="page-link disabled">»</span>';
    }
    
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานเฝ้าระวัง 6 มิติ - NCDs Portal อำเภอ<?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .surv-tabs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        @media (max-width: 900px) {
            .surv-tabs {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 520px) {
            .surv-tabs {
                grid-template-columns: 1fr;
            }
        }
        .surv-tab-btn {
            background: var(--bg-card);
            border: 1.5px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 16px;
            padding: 14px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s ease;
            text-align: left;
            box-sizing: border-box;
            width: 100%;
        }
        .surv-tab-btn .tab-icon {
            font-size: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: var(--bg-darker);
            flex-shrink: 0;
            transition: all 0.2s;
        }
        .surv-tab-btn .tab-label {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
            min-width: 0;
        }
        .surv-tab-btn .tab-title {
            font-size: 14px;
            font-weight: 800;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .surv-tab-btn .tab-count {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
        }
        .surv-tab-btn.active {
            background: var(--color-primary);
            color: #ffffff !important;
            border-color: var(--color-primary);
            box-shadow: 0 6px 18px rgba(59, 130, 246, 0.35);
        }
        .surv-tab-btn.active .tab-icon {
            background: rgba(255, 255, 255, 0.2);
        }
        .surv-tab-btn.active .tab-count {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        .surv-tab-btn:hover:not(.active) {
            border-color: var(--color-primary);
            color: var(--color-primary);
            transform: translateY(-2px);
        }
        .table-responsive {
            overflow-x: auto;
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: var(--neumorph-flat);
        }
        .surv-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }
        .surv-table th {
            background: var(--bg-darker);
            color: var(--color-accent);
            padding: 12px 14px;
            font-weight: 800;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }
        .surv-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .surv-table tr:hover td {
            background: rgba(59, 130, 246, 0.04);
        }
        .btn-export-excel {
            background: #10B981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            box-shadow: var(--neumorph-flat);
        }
        .btn-export-excel:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        /* Pagination & Layout Controls */
        .pagination-header-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 14px;
        }
        .pagination-records-info {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-secondary);
        }
        .per-page-select {
            padding: 7px 12px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            font-weight: 700;
            font-size: 12.5px;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            outline: none;
        }
        .per-page-select:focus {
            border-color: var(--color-primary);
        }
        .pagination {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
            margin-top: 24px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .page-link {
            padding: 8px 14px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            box-sizing: border-box;
        }
        .page-link:hover:not(.disabled):not(.active) {
            border-color: var(--color-primary);
            color: var(--color-primary);
            transform: translateY(-1px);
        }
        .page-link.active {
            background: var(--color-primary);
            color: #ffffff !important;
            border-color: var(--color-primary);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
        }
        .page-link.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
            box-shadow: none;
        }
    </style>
</head>
<body class="admin-body">
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <div style="max-width: 1280px; margin: 40px auto; padding: 0 20px;">
        <!-- Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
            <div>
                <h2 style="color: var(--color-accent); margin: 0; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 12px;">
                    <?= render_neu_icon('xray', 'lg', 'text-navy') ?>
                    <span>รายงานและระบบเฝ้าระวังสุขภาพ 6 มิติ</span>
                </h2>
                <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 14px;">
                    เครื่องมือติดตามเฝ้าระวังโรคไม่ติดต่อเรื้อรังเชิงรุก อำเภอ<?= DISTRICT_NAME ?>
                </p>
            </div>
            <!-- Hospital Filter -->
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <form method="GET" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($selected_tab) ?>">
                    <input type="hidden" name="limit" value="<?= (int)$limit ?>">
                    <select name="hoscode" onchange="this.form.submit()" style="padding: 8px 12px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-weight: 700; font-size: 13px; box-shadow: var(--neumorph-flat);">
                        <option value="">-- แสดงทุก รพ.สต. ในอำเภอ --</option>
                        <?php foreach ($hc_names as $code => $name): ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $selected_hoscode == $code ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($code) ?>] <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- 6 Tabs Navigation (2 Rows Grid Layout) -->
        <div class="surv-tabs no-print">
            <button class="surv-tab-btn <?= $selected_tab === 'tab1' ? 'active' : '' ?>" onclick="switchSurvTab('tab1')">
                <?= render_neu_icon('clipboard-record', 'md', 'text-blue') ?>
                <div class="tab-label">
                    <span class="tab-title">1. ทะเบียนติดตามกลุ่มเสี่ยง</span>
                    <span class="tab-count"><?= number_format($dim1_total) ?> คน</span>
                </div>
            </button>
            <button class="surv-tab-btn <?= $selected_tab === 'tab2' ? 'active' : '' ?>" onclick="switchSurvTab('tab2')">
                <?= render_neu_icon('refresh-repeat', 'md', 'text-green') ?>
                <div class="tab-label">
                    <span class="tab-title">2. กลุ่มที่ควรตรวจซ้ำ</span>
                    <span class="tab-count"><?= number_format($dim2_total) ?> คน</span>
                </div>
            </button>
            <button class="surv-tab-btn <?= $selected_tab === 'tab3' ? 'active' : '' ?>" onclick="switchSurvTab('tab3')">
                <?= render_neu_icon('warning-alert', 'md', 'text-yellow') ?>
                <div class="tab-label">
                    <span class="tab-title">3. ขาดการติดตาม</span>
                    <span class="tab-count"><?= number_format($dim3_total) ?> คน</span>
                </div>
            </button>
            <button class="surv-tab-btn <?= $selected_tab === 'tab4' ? 'active' : '' ?>" onclick="switchSurvTab('tab4')">
                <?= render_neu_icon('doctor', 'md', 'text-purple') ?>
                <div class="tab-label">
                    <span class="tab-title">4. ยังไม่ได้รับการคัดกรอง</span>
                    <span class="tab-count"><?= number_format($dim4_total) ?> คน</span>
                </div>
            </button>
            <button class="surv-tab-btn <?= $selected_tab === 'tab5' ? 'active' : '' ?>" onclick="switchSurvTab('tab5')">
                <?= render_neu_icon('sleep', 'md', 'text-navy') ?>
                <div class="tab-label">
                    <span class="tab-title">5. ผลสัมฤทธิ์ & สุขภาพนอน 1น.</span>
                    <span class="tab-count">วิเคราะห์ภาพรวม</span>
                </div>
            </button>
            <button class="surv-tab-btn <?= $selected_tab === 'tab6' ? 'active' : '' ?>" onclick="switchSurvTab('tab6')">
                <?= render_neu_icon('search-inspect', 'md', 'text-red') ?>
                <div class="tab-label">
                    <span class="tab-title">6. ตรวจสอบคุณภาพข้อมูล</span>
                    <span class="tab-count"><?= number_format($dim6_total) ?> รายการ</span>
                </div>
            </button>
        </div>

        <!-- TAB 1: ทะเบียนติดตามกลุ่มเสี่ยง/เยี่ยมบ้าน -->
        <div id="tab1-content" class="surv-content" style="display: <?= $selected_tab === 'tab1' ? 'block' : 'none' ?>;">
            <?php render_surveillance_table_header(
                'ทะเบียนประชากรกลุ่มเสี่ยงสูงและภาวะวิกฤตที่ต้องได้รับการเยี่ยมบ้าน',
                'clipboard-record',
                'text-blue',
                $dim1_total,
                $page,
                $limit,
                'tab1',
                'table-tab1',
                'ทะเบียนติดตามกลุ่มเสี่ยง_สสอตาลสุม'
            ); ?>
            <div class="table-responsive">
                <table class="surv-table" id="table-tab1">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อ - สกุล</th>
                            <th>บ้านเลขที่/หมู่</th>
                            <th>รพ.สต.</th>
                            <th>ความดัน (SYS/DIA)</th>
                            <th>น้ำตาล (DTX)</th>
                            <th>ระดับการดูแล (Care Level)</th>
                            <th>พัฒนาการ</th>
                            <th>การนอนหลับ</th>
                            <th>วันนัดถัดไป</th>
                            <th>อสม. ผู้ดูแล</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dim1_data)): ?>
                            <tr><td colspan="11" style="text-align:center; color:var(--text-muted); padding:30px;">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td></tr>
                        <?php else: ?>
                            <?php foreach ($dim1_data as $idx => $r): ?>
                                <?php
                                    $clBadge = 'background:rgba(16,185,129,0.15); color:#10B981;';
                                    $clText = '🟢 ดูแลปกติ';
                                    if ($r['care_level'] === 'fair') { $clBadge = 'background:rgba(245,158,11,0.15); color:#D97706;'; $clText = '🟡 ดูแลพิเศษ'; }
                                    elseif ($r['care_level'] === 'poor') { $clBadge = 'background:rgba(249,115,22,0.15); color:#EA580C;'; $clText = '🟠 มากพิเศษ'; }
                                    elseif ($r['care_level'] === 'critical') { $clBadge = 'background:rgba(239,68,68,0.15); color:#DC2626;'; $clText = '🔴 วิกฤตด่วน'; }
                                ?>
                                <tr>
                                    <td><?= ($page - 1) * $limit + $idx + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($r['house_no']) ?> ม.<?= htmlspecialchars($r['moo']) ?></td>
                                    <td>[<?= htmlspecialchars($r['hoscode']) ?>]</td>
                                    <td style="color: <?= $r['sys_bp1'] >= 140 ? '#EF4444' : 'inherit' ?>; font-weight: bold;"><?= $r['sys_bp1'] ?>/<?= $r['dia_bp1'] ?></td>
                                    <td style="color: <?= $r['dtx_value'] >= 126 ? '#EF4444' : 'inherit' ?>; font-weight: bold;"><?= $r['dtx_value'] ?: '-' ?></td>
                                    <td><span style="padding:2px 8px; border-radius:8px; font-weight:800; font-size:11.5px; <?= $clBadge ?>"><?= $clText ?></span></td>
                                    <td>
                                        <?php if ($r['health_progress'] === 'improved'): ?>
                                            <span style="color:#10B981; font-weight:700;">🟢 ดีขึ้น</span>
                                        <?php elseif ($r['health_progress'] === 'worsened'): ?>
                                            <span style="color:#EF4444; font-weight:700;">🔴 ต้องระวัง</span>
                                        <?php else: ?>
                                            <span style="color:#F59E0B; font-weight:700;">🟡 ทรงตัว</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['sleep_quality'] === 'poor') echo '😫 หลับยาก'; elseif ($r['sleep_quality'] === 'restless') echo '🥱 ไม่สนิท'; else echo '😴 หลับสนิท'; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($r['next_visit_date'] ?: '-') ?></strong></td>
                                    <td><?= htmlspecialchars($r['vhv_name'] ?: 'ไม่ระบุ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_surveillance_pagination($dim1_total, $page, $limit, 'tab1', $selected_hoscode); ?>
        </div>

        <!-- TAB 2: กลุ่มที่ควรตรวจซ้ำ (Retest Due) -->
        <div id="tab2-content" class="surv-content" style="display: <?= $selected_tab === 'tab2' ? 'block' : 'none' ?>;">
            <?php render_surveillance_table_header(
                'กลุ่มสงสัยป่วย/ปริ่มเสี่ยง ที่ควรได้รับการตรวจซ้ำ (Retest Due)',
                'refresh-repeat',
                'text-green',
                $dim2_total,
                $page,
                $limit,
                'tab2',
                'table-tab2',
                'กลุ่มที่ควรตรวจซ้ำ_สสอตาลสุม'
            ); ?>
            <div class="table-responsive">
                <table class="surv-table" id="table-tab2">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อ - สกุล</th>
                            <th>บ้านเลขที่/หมู่</th>
                            <th>รพ.สต.</th>
                            <th>ผลตรวจรอบ 1 (BP/DTX)</th>
                            <th>วันที่ตรวจรอบ 1</th>
                            <th>ผ่านมาแล้ว (วัน)</th>
                            <th>สถานะการตรวจซ้ำ</th>
                            <th>อสม. ผู้รับผิดชอบ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dim2_data)): ?>
                            <tr><td colspan="9" style="text-align:center; color:var(--text-muted); padding:30px;">ไม่มีกลุ่มค้างตรวจซ้ำ</td></tr>
                        <?php else: ?>
                            <?php foreach ($dim2_data as $idx => $r): ?>
                                <tr>
                                    <td><?= ($page - 1) * $limit + $idx + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($r['house_no']) ?> ม.<?= htmlspecialchars($r['moo']) ?></td>
                                    <td>[<?= htmlspecialchars($r['hoscode']) ?>]</td>
                                    <td>BP: <strong><?= $r['sys_bp1'] ?>/<?= $r['dia_bp1'] ?></strong> | DTX: <strong><?= $r['dtx_value'] ?: '-' ?></strong></td>
                                    <td><?= substr($r['round1_date'], 0, 10) ?></td>
                                    <td><span style="font-weight:800; color:<?= $r['days_since_r1'] > 30 ? '#EF4444' : '#F59E0B' ?>;"><?= $r['days_since_r1'] ?> วัน</span></td>
                                    <td>
                                        <span style="background:rgba(239,68,68,0.1); color:#DC2626; padding:2px 8px; border-radius:8px; font-weight:700; font-size:11.5px;">
                                            ⏳ ยังไม่ตรวจรอบ 2
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($r['vhv_name'] ?: 'ไม่ระบุ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_surveillance_pagination($dim2_total, $page, $limit, 'tab2', $selected_hoscode); ?>
        </div>

        <!-- TAB 3: ขาดการติดตามในรอบเดือน (Overdue Followup) -->
        <div id="tab3-content" class="surv-content" style="display: <?= $selected_tab === 'tab3' ? 'block' : 'none' ?>;">
            <?php render_surveillance_table_header(
                'รายชื่อผู้ที่เกินกำหนดวันนัดติดตามเยี่ยมบ้าน (Overdue Followup)',
                'warning-alert',
                'text-yellow',
                $dim3_total,
                $page,
                $limit,
                'tab3',
                'table-tab3',
                'กลุ่มขาดการติดตาม_สสอตาลสุม'
            ); ?>
            <div class="table-responsive">
                <table class="surv-table" id="table-tab3">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อ - สกุล</th>
                            <th>บ้านเลขที่/หมู่</th>
                            <th>รพ.สต.</th>
                            <th>กำหนดนัดเดิม</th>
                            <th>เกินกำหนดแล้ว (วัน)</th>
                            <th>ค่าล่าสุด (BP/DTX)</th>
                            <th>ระดับการดูแล</th>
                            <th>อสม. ผู้รับผิดชอบ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dim3_data)): ?>
                            <tr><td colspan="9" style="text-align:center; color:var(--text-muted); padding:30px;">ไม่มีผู้ป่วยที่เกินกำหนดนัด</td></tr>
                        <?php else: ?>
                            <?php foreach ($dim3_data as $idx => $r): ?>
                                <tr>
                                    <td><?= ($page - 1) * $limit + $idx + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($r['house_no']) ?> ม.<?= htmlspecialchars($r['moo']) ?></td>
                                    <td>[<?= htmlspecialchars($r['hoscode']) ?>]</td>
                                    <td><strong style="color:#DC2626;"><?= htmlspecialchars($r['next_visit_date']) ?></strong></td>
                                    <td><span style="background:#FEE2E2; color:#DC2626; padding:2px 8px; border-radius:8px; font-weight:800;">+<?= $r['overdue_days'] ?> วัน</span></td>
                                    <td><?= $r['sys_bp1'] ?>/<?= $r['dia_bp1'] ?> | <?= $r['dtx_value'] ?: '-' ?></td>
                                    <td><?= htmlspecialchars($r['care_level']) ?></td>
                                    <td><?= htmlspecialchars($r['vhv_name'] ?: 'ไม่ระบุ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_surveillance_pagination($dim3_total, $page, $limit, 'tab3', $selected_hoscode); ?>
        </div>

        <!-- TAB 4: กลุ่มที่ยังไม่เคยได้รับการคัดกรอง -->
        <div id="tab4-content" class="surv-content" style="display: <?= $selected_tab === 'tab4' ? 'block' : 'none' ?>;">
            <?php render_surveillance_table_header(
                'ประชากรอายุ 35 ปีขึ้นไปที่ยังไม่ได้รับการคัดกรองในปีงบประมาณ ' . ($activeBudgetYear + 543),
                'doctor',
                'text-purple',
                $dim4_total,
                $page,
                $limit,
                'tab4',
                'table-tab4',
                'กลุ่มยังไม่ได้รับการคัดกรอง_สสอตาลสุม'
            ); ?>
            <div class="table-responsive">
                <table class="surv-table" id="table-tab4">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อ - สกุล</th>
                            <th>อายุ</th>
                            <th>เพศ</th>
                            <th>บ้านเลขที่/หมู่</th>
                            <th>รพ.สต.</th>
                            <th>สถานะกลุ่ม</th>
                            <th>อสม. ผู้รับผิดชอบ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dim4_data)): ?>
                            <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">ประชากรทุกคนได้รับการคัดกรองครบถ้วน 100%!</td></tr>
                        <?php else: ?>
                            <?php foreach ($dim4_data as $idx => $r): ?>
                                <tr>
                                    <td><?= ($page - 1) * $limit + $idx + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                                    <td><?= $r['age'] ?> ปี</td>
                                    <td><?= $r['sex'] == 1 ? 'ชาย' : 'หญิง' ?></td>
                                    <td><?= htmlspecialchars($r['house_no']) ?> ม.<?= htmlspecialchars($r['moo']) ?></td>
                                    <td>[<?= htmlspecialchars($r['hoscode']) ?>]</td>
                                    <td><span style="background:rgba(100,116,139,0.15); color:#64748B; padding:2px 8px; border-radius:8px; font-weight:700; font-size:11px;">รอคัดกรอง</span></td>
                                    <td><?= htmlspecialchars($r['vhv_name'] ?: 'ยังไม่มอบหมาย') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_surveillance_pagination($dim4_total, $page, $limit, 'tab4', $selected_hoscode); ?>
        </div>

        <!-- TAB 5: สรุปผลสัมฤทธิ์โครงการ & คุณภาพการนอนหลับ -->
        <div id="tab5-content" class="surv-content" style="display: <?= $selected_tab === 'tab5' ? 'block' : 'none' ?>;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <!-- Health Progress Outcome -->
                <div class="card-dark" style="padding: 20px; background: var(--bg-card); border-radius: 20px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color);">
                    <h4 style="margin: 0 0 16px 0; color: var(--color-accent); font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        <?= render_neu_icon('heart-pulse', 'sm', 'text-green') ?>
                        <span>สรุปผลสัมฤทธิ์พัฒนาการสุขภาพ (Health Progress)</span>
                    </h4>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: rgba(16,185,129,0.1); border-radius: 12px; border-left: 4px solid #10B981;">
                            <span style="font-weight: 700; color: #10B981;">🟢 สุขภาพดีขึ้น (Improved)</span>
                            <span style="font-size: 18px; font-weight: 800; color: #10B981;"><?= number_format($dim5_stats['improved']) ?> คน</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: rgba(245,158,11,0.1); border-radius: 12px; border-left: 4px solid #F59E0B;">
                            <span style="font-weight: 700; color: #D97706;">🟡 สุขภาพทรงตัว (Stable)</span>
                            <span style="font-size: 18px; font-weight: 800; color: #D97706;"><?= number_format($dim5_stats['stable']) ?> คน</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: rgba(239,68,68,0.1); border-radius: 12px; border-left: 4px solid #EF4444;">
                            <span style="font-weight: 700; color: #DC2626;">🔴 ต้องระวัง / แย่ลง (Worsened)</span>
                            <span style="font-size: 18px; font-weight: 800; color: #DC2626;"><?= number_format($dim5_stats['worsened']) ?> คน</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: rgba(100,116,139,0.1); border-radius: 12px; border-left: 4px solid #64748B;">
                            <span style="font-weight: 700; color: #64748B;">⚪ ค่าตั้งต้น (Baseline Checkpoint)</span>
                            <span style="font-size: 18px; font-weight: 800; color: #64748B;"><?= number_format($dim5_stats['baseline']) ?> คน</span>
                        </div>
                    </div>
                </div>

                <!-- Sleep Quality Analysis -->
                <div class="card-dark" style="padding: 20px; background: var(--bg-card); border-radius: 20px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color);">
                    <h4 style="margin: 0 0 16px 0; color: var(--color-accent); font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        <?= render_neu_icon('sleep', 'sm', 'text-purple') ?>
                        <span>คุณภาพการนอนหลับของกลุ่มเป้าหมาย (1น.)</span>
                    </h4>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: rgba(16,185,129,0.1); border-radius: 12px; border-left: 4px solid #10B981;">
                            <span style="font-weight: 700; color: #10B981;">😴 หลับสนิทดี (Good Sleep)</span>
                            <span style="font-size: 18px; font-weight: 800; color: #10B981;"><?= number_format($dim5_stats['sleep_good']) ?> คน</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: rgba(245,158,11,0.1); border-radius: 12px; border-left: 4px solid #F59E0B;">
                            <span style="font-weight: 700; color: #D97706;">🥱 หลับๆ ตื่นๆ (Restless Sleep)</span>
                            <span style="font-size: 18px; font-weight: 800; color: #D97706;"><?= number_format($dim5_stats['sleep_restless']) ?> คน</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: rgba(239,68,68,0.1); border-radius: 12px; border-left: 4px solid #EF4444;">
                            <span style="font-weight: 700; color: #DC2626;">😫 นอนไม่ค่อยหลับ / หลับยาก (Poor Sleep)</span>
                            <span style="font-size: 18px; font-weight: 800; color: #DC2626;"><?= number_format($dim5_stats['sleep_poor']) ?> คน</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 6: ตรวจสอบคุณภาพข้อมูล (Data Quality Audit) -->
        <div id="tab6-content" class="surv-content" style="display: <?= $selected_tab === 'tab6' ? 'block' : 'none' ?>;">
            <?php render_surveillance_table_header(
                'ตรวจจับค่าข้อมูลผิดปกติและค่าผิดวิสัย (Data Quality & Anomaly Audit)',
                'search-inspect',
                'text-red',
                $dim6_total,
                $page,
                $limit,
                'tab6',
                'table-tab6',
                'ตรวจสอบคุณภาพข้อมูล_สสอตาลสุม'
            ); ?>
            <div class="table-responsive">
                <table class="surv-table" id="table-tab6">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อ - สกุล</th>
                            <th>บ้านเลขที่/หมู่</th>
                            <th>รพ.สต.</th>
                            <th>ความดัน (SYS/DIA)</th>
                            <th>น้ำตาล (DTX)</th>
                            <th>น้ำหนัก/ส่วนสูง</th>
                            <th>สาเหตุที่ตรวจพบความผิดปกติ</th>
                            <th>อสม. ผู้บันทึก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dim6_anomalies)): ?>
                            <tr><td colspan="9" style="text-align:center; color:var(--color-green); padding:30px; font-weight:bold;">✅ ข้อมูลมีคุณภาพสมบูรณ์ 100% ไม่พบค่าผิดปกติ</td></tr>
                        <?php else: ?>
                            <?php foreach ($dim6_anomalies as $idx => $r): ?>
                                <tr>
                                    <td><?= ($page - 1) * $limit + $idx + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($r['house_no']) ?> ม.<?= htmlspecialchars($r['moo']) ?></td>
                                    <td>[<?= htmlspecialchars($r['hoscode']) ?>]</td>
                                    <td style="color:#DC2626; font-weight:bold;"><?= $r['sys_bp1'] ?>/<?= $r['dia_bp1'] ?></td>
                                    <td><?= $r['dtx_value'] ?: '-' ?></td>
                                    <td><?= $r['weight'] ?> kg / <?= $r['height'] ?> cm</td>
                                    <td><span style="background:#FEE2E2; color:#DC2626; padding:2px 8px; border-radius:8px; font-weight:700; font-size:11.5px;">⚠️ <?= htmlspecialchars($r['anomaly_reason']) ?></span></td>
                                    <td><?= htmlspecialchars($r['vhv_name'] ?: 'ไม่ระบุ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_surveillance_pagination($dim6_total, $page, $limit, 'tab6', $selected_hoscode); ?>
        </div>
    </div>

    <script>
        function switchSurvTab(tabId) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabId);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        function changeLimit(newLimit, tabId) {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', newLimit);
            url.searchParams.set('tab', tabId);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        function exportTableToExcel(tableId, filename = 'รายงานเฝ้าระวัง') {
            const table = document.getElementById(tableId);
            if (!table) return;

            let html = table.outerHTML;
            let blob = new Blob(['\ufeff' + html], {
                type: 'application/vnd.ms-excel;charset=utf-8'
            });

            let url = URL.createObjectURL(blob);
            let a = document.createElement('a');
            a.href = url;
            a.download = filename + '_' + new Date().toISOString().slice(0,10) + '.xls';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>
