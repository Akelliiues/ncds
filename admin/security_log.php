<?php
// admin/security_log.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

// -----------------------------------------------------------------------
// Create table if not exists
// -----------------------------------------------------------------------
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scan_security_log (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            logged_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            vhv_id       VARCHAR(20)  NOT NULL,
            vhv_name     VARCHAR(120) DEFAULT NULL,
            hoscode      VARCHAR(10)  DEFAULT NULL,
            scanned_code VARCHAR(30)  NOT NULL,
            scan_lat     DECIMAL(10,7) DEFAULT NULL,
            scan_lng     DECIMAL(10,7) DEFAULT NULL,
            ip_address   VARCHAR(45)  DEFAULT NULL,
            user_agent   TEXT         DEFAULT NULL,
            incident_type VARCHAR(60) NOT NULL DEFAULT 'UNAUTHORIZED_SCAN',
            INDEX idx_logged_at (logged_at),
            INDEX idx_vhv_id    (vhv_id),
            INDEX idx_hoscode   (hoscode)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    // Table may already exist; ignore
}

// -----------------------------------------------------------------------
// Sync survey participants to scan_security_log if not already present
// -----------------------------------------------------------------------
try {
    $pdo->exec("
        INSERT INTO scan_security_log (logged_at, vhv_id, vhv_name, hoscode, scanned_code, incident_type)
        SELECT p.created_at, p.vhv_id, u.vhv_name, u.hoscode, 'SURVEY_2026', 'SATISFACTION_SURVEY'
        FROM vhv_survey_participants p
        JOIN vhv_users u ON p.vhv_id = u.vhv_id
        LEFT JOIN scan_security_log sl ON sl.vhv_id = p.vhv_id AND sl.incident_type = 'SATISFACTION_SURVEY'
        WHERE sl.id IS NULL
    ");
} catch (PDOException $e) {
    // Ignore any sync issue
}

// -----------------------------------------------------------------------
// Action: clear logs (super-admin only)
// -----------------------------------------------------------------------
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($admin_hoscode === null && $_POST['action'] === 'clear_all') {
        $days = intval($_POST['days'] ?? 30);
        $pdo->prepare("DELETE FROM scan_security_log WHERE logged_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$days]);
        $pdo->prepare("DELETE FROM scan_security_log WHERE 1=1")->execute(); // clear all
        $message = "ลบบันทึก Security Log ทั้งหมดเรียบร้อยแล้ว";
    } elseif ($admin_hoscode === null && $_POST['action'] === 'clear_old') {
        $days = intval($_POST['days'] ?? 90);
        $pdo->prepare("DELETE FROM scan_security_log WHERE logged_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$days]);
        $message = "ลบ Log ที่เก่ากว่า {$days} วันแล้ว";
    }
}

// -----------------------------------------------------------------------
// Filters
// -----------------------------------------------------------------------
$search       = trim($_GET['search']   ?? '');
$filter_hsc   = trim($_GET['hoscode']  ?? '');
$filter_date  = trim($_GET['date']     ?? '');
$filter_type  = trim($_GET['type']     ?? '');
$page         = max(1, intval($_GET['page'] ?? 1));
$limit        = 50;
$offset       = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];

// Scope: sub-admin sees only their hoscode; main admin sees all
if ($admin_hoscode) {
    $where[]  = 'sl.hoscode = ?';
    $params[] = $admin_hoscode;
} elseif ($filter_hsc !== '') {
    $where[]  = 'sl.hoscode = ?';
    $params[] = $filter_hsc;
}

if ($search !== '') {
    $where[]  = '(sl.vhv_id LIKE ? OR sl.vhv_name LIKE ? OR sl.scanned_code LIKE ? OR sl.ip_address LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_date !== '') {
    $where[]  = 'DATE(sl.logged_at) = ?';
    $params[] = $filter_date;
}

if ($filter_type !== '') {
    $where[]  = 'sl.incident_type = ?';
    $params[] = $filter_type;
}

$whereSQL = implode(' AND ', $where);

// -----------------------------------------------------------------------
// Counts
// -----------------------------------------------------------------------
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM scan_security_log sl
    WHERE $whereSQL
");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages   = max(1, ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// -----------------------------------------------------------------------
// Summary stats (today, this week)
// -----------------------------------------------------------------------
$stats = [];
try {
    $statWhere  = $admin_hoscode ? 'WHERE hoscode = ?' : 'WHERE 1=1';
    $statParams = $admin_hoscode ? [$admin_hoscode] : [];

    $s = $pdo->prepare("SELECT
        COUNT(*) AS total_all,
        SUM(DATE(logged_at) = CURDATE()) AS today,
        SUM(logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS this_week,
        COUNT(DISTINCT vhv_id) AS unique_vhvs
        FROM scan_security_log $statWhere");
    $s->execute($statParams);
    $stats = $s->fetch();
} catch (PDOException $e) {
}

// -----------------------------------------------------------------------
// Fetch records
// -----------------------------------------------------------------------
$logs = [];
try {
    $dataStmt = $pdo->prepare("
        SELECT sl.*, v.village_name, u.vhv_moo
        FROM scan_security_log sl
        LEFT JOIN vhv_users u ON sl.vhv_id = u.vhv_id COLLATE utf8mb4_unicode_ci
        LEFT JOIN villages v ON u.vhid_code = v.vhid_code COLLATE utf8mb4_unicode_ci
        WHERE $whereSQL
        ORDER BY sl.logged_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $dataStmt->execute($params);
    $logs = $dataStmt->fetchAll();
} catch (PDOException $e) {
    $logs = [];
}

// -----------------------------------------------------------------------
// hoscode name map (Query from health_units database table)
// -----------------------------------------------------------------------
$hc_names = [];
try {
    $unitStmt = $pdo->query("SELECT hoscode, hosname FROM health_units ORDER BY hoscode ASC");
    while ($row = $unitStmt->fetch()) {
        $hc_names[$row['hoscode']] = $row['hosname'];
    }
} catch (PDOException $e) {
    // Fallback in case of database issue
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
}

$incident_labels = [
    'CROSS_DISTRICT_UNAUTHORIZED_SCAN_BLOCKED' => ['label' => 'สแกนข้ามเขต', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.12)'],
    'UNAUTHORIZED_SCAN'                         => ['label' => 'ไม่มีสิทธิ์สแกน', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.12)'],
    'NO_ASSIGNMENT'                             => ['label' => 'ไม่มีงานมอบหมาย', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.12)'],
    'AUTHORIZED_SCAN'                           => ['label' => 'เข้าสู่ฟอร์มคัดกรอง', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.12)'],
    'SCREENING_COMPLETE'                         => ['label' => 'คัดกรองสำเร็จ', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.12)'],
    'SCREENING_SKIPPED'                          => ['label' => 'ข้ามการคัดกรอง', 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,0.12)'],
    'SATISFACTION_SURVEY'                       => ['label' => 'แบบประเมินความพึงพอใจ', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.12)'],
];
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Log - การสแกนที่ผิดปกติ | NCD ตาลสุม</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-main);
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 20px 18px;
            box-shadow: var(--neumorph-flat);
            text-align: center;
        }

        .stat-num {
            font-size: 36px;
            font-weight: 800;
            line-height: 1.1;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 4px;
            font-weight: 600;
        }

        .badge-incident {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .log-table th,
        .log-table td {
            padding: 10px 12px;
            text-align: left;
            font-size: 13.5px;
            vertical-align: middle;
        }

        .log-table th {
            background: var(--bg-darker);
            color: var(--text-secondary);
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .log-table tr:hover td {
            background: rgba(59, 130, 246, 0.04);
        }

        .map-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: var(--color-primary);
            font-size: 12px;
            text-decoration: none;
            font-weight: 600;
        }

        .map-link:hover {
            text-decoration: underline;
        }

        .filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 20px;
        }

        .filter-bar label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .filter-bar input,
        .filter-bar select {
            height: 40px;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            padding: 0 12px;
        }

        .filter-bar .f-search {
            min-width: 200px;
            flex: 2;
        }

        .filter-bar .f-sm {
            min-width: 130px;
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
        }

        .page-link.active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state .icon {
            font-size: 56px;
            display: block;
            margin-bottom: 16px;
        }

        /* Danger zone box */
        .danger-zone {
            background: rgba(239, 68, 68, 0.06);
            border: 1.5px solid rgba(239, 68, 68, 0.3);
            border-radius: var(--border-radius);
            padding: 20px 24px;
            margin-top: 24px;
        }

        .danger-zone h4 {
            color: var(--color-red);
            margin: 0 0 12px;
            font-size: 15px;
        }
    </style>
</head>

<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width:1300px;margin:40px auto;padding:0 20px;">

        <!-- Page header -->
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:28px;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 style="color:var(--color-accent);margin:0 0 6px;">🔐 Security & Activity Log — การสแกนและบันทึกกิจกรรม</h2>
                <p style="color:var(--text-secondary);margin:0;font-size:14px;">
                    รายการบันทึกเหตุการณ์ความปลอดภัย การสแกน QR Code ที่ผิดปกติ หรือประวัติการตอบแบบสอบถาม
                    <?php if ($admin_hoscode): ?>
                        • แสดงเฉพาะ <strong><?= htmlspecialchars($hc_names[$admin_hoscode] ?? $admin_hoscode) ?></strong>
                    <?php endif; ?>
                </p>
            </div>
            <a href="javascript:window.print()" class="btn-giant btn-giant-secondary"
                style="margin:0;padding:10px 18px;font-size:14px;display:inline-flex;align-items:center;gap:6px;">
                🖨️ พิมพ์รายงาน
            </a>
        </div>

        <!-- Success message -->
        <?php if ($message): ?>
            <div style="background:rgba(16,185,129,0.12);border:2px solid var(--color-green);color:var(--color-green);
                padding:14px 18px;border-radius:var(--border-radius);margin-bottom:20px;font-weight:700;">
                ✅ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Summary Stats -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-num" style="color:var(--color-red);"><?= number_format((float)($stats['total_all'] ?? 0)) ?></div>
                <div class="stat-label">ทั้งหมด (ทุกช่วง)</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" style="color:var(--color-yellow);"><?= number_format((float)($stats['today'] ?? 0)) ?></div>
                <div class="stat-label">วันนี้</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" style="color:var(--color-primary);"><?= number_format((float)($stats['this_week'] ?? 0)) ?></div>
                <div class="stat-label">7 วันล่าสุด</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" style="color:var(--color-accent);"><?= number_format((float)($stats['unique_vhvs'] ?? 0)) ?></div>
                <div class="stat-label">อสม. ที่เกิดเหตุ (ไม่ซ้ำ)</div>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="card-dark" style="padding:18px 20px;margin-bottom:20px;">
            <form method="GET" class="filter-bar">
                <div style="flex:2;min-width:200px;">
                    <label>ค้นหา (ID อสม. / ชื่อ / รหัสสแกน / IP)</label>
                    <input type="text" name="search" class="f-search" style="width:100%;"
                        value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหา...">
                </div>
                <?php if (!$admin_hoscode): ?>
                    <div class="f-sm">
                        <label>สังกัด รพ.สต.</label>
                        <select name="hoscode">
                            <option value="">-- ทั้งหมด --</option>
                            <?php foreach ($hc_names as $code => $name): ?>
                                <option value="<?= $code ?>" <?= $filter_hsc === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="f-sm">
                    <label>วันที่</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
                </div>
                <div class="f-sm">
                    <label>ประเภทเหตุการณ์</label>
                    <select name="type">
                        <option value="">-- ทั้งหมด --</option>
                        <?php foreach ($incident_labels as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $filter_type === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end;">
                    <button type="submit"
                        style="height:40px;padding:0 18px;border-radius:8px;border:none;
                               background:var(--color-primary);color:white;font-weight:700;cursor:pointer;">
                        🔍 กรอง
                    </button>
                    <?php if ($search || $filter_hsc || $filter_date || $filter_type): ?>
                        <a href="security_log.php"
                            style="height:40px;padding:0 14px;border-radius:8px;border:1px solid var(--border-color);
                          background:var(--bg-card);color:var(--text-secondary);font-weight:700;
                          display:inline-flex;align-items:center;text-decoration:none;">
                            ✕ ล้าง
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Log Table -->
        <div class="card-dark" style="padding:0;overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border-color);
                    display:flex;justify-content:space-between;align-items:center;">
                <span style="font-weight:800;color:var(--color-accent);font-size:15px;">
                    📋 รายการ Log (<?= number_format($total_records) ?> รายการ)
                </span>
                <span style="font-size:13px;color:var(--text-muted);">หน้า <?= $page ?> / <?= $total_pages ?></span>
            </div>

            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <span class="icon">✅</span>
                    <h3 style="color:var(--color-green);margin-bottom:8px;">ไม่พบรายการสแกนที่ผิดปกติ</h3>
                    <p style="font-size:14px;">ยังไม่มีการบันทึกเหตุการณ์ด้านความปลอดภัยตามเงื่อนไขที่เลือก</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="log-table" style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="padding-left:20px;">#</th>
                                <th>วันที่ / เวลา</th>
                                <th>อสม. (ID)</th>
                                <th>ชื่อ</th>
                                <th>หมู่บ้านที่สังกัด</th>
                                <th>สังกัด</th>
                                <th>HID/CID</th>
                                <th>ประเภทเหตุการณ์</th>
                                <th>พิกัด GPS</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $i => $log):
                                $inc = $incident_labels[$log['incident_type']] ?? ['label' => $log['incident_type'], 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,0.1)'];
                                $rowNum = $offset + $i + 1;
                            ?>
                                <tr style="border-bottom:1px solid var(--border-color);">
                                    <td style="padding-left:20px;color:var(--text-muted);font-size:12px;"><?= $rowNum ?></td>
                                    <td style="white-space:nowrap;">
                                        <strong style="font-size:13px;"><?= date('d/m/Y', strtotime($log['logged_at'])) ?></strong><br>
                                        <span style="color:var(--text-muted);font-size:12px;"><?= date('H:i:s', strtotime($log['logged_at'])) ?></span>
                                    </td>
                                    <td>
                                        <code style="background:var(--bg-darker);padding:2px 8px;border-radius:6px;font-size:13px;">
                                            <?= htmlspecialchars($log['vhv_id']) ?>
                                        </code>
                                    </td>
                                    <td style="font-weight:600;color:var(--text-primary);white-space:nowrap;">
                                        <?= htmlspecialchars($log['vhv_name'] ?: '—') ?>
                                    </td>
                                    <td style="font-size:13.5px;color:var(--text-primary);white-space:nowrap;">
                                        <?php if (!empty($log['village_name'])): ?>
                                            <?= 'ม.' . intval($log['vhv_moo']) . ' ' . htmlspecialchars($log['village_name']) ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:13px;color:var(--text-secondary);white-space:nowrap;">
                                        <?= htmlspecialchars($hc_names[$log['hoscode']] ?? ($log['hoscode'] ?: '—')) ?>
                                    </td>
                                    <td>
                                        <code style="background:rgba(239,68,68,0.08);color:var(--color-red);
                                         padding:2px 8px;border-radius:6px;font-size:13px;">
                                            <?= htmlspecialchars($log['scanned_code']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="badge-incident"
                                            style="background:<?= $inc['bg'] ?>;color:<?= $inc['color'] ?>;">
                                            <?= $inc['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($log['scan_lat'] && $log['scan_lng']): ?>
                                            <a href="https://maps.google.com/?q=<?= $log['scan_lat'] ?>,<?= $log['scan_lng'] ?>"
                                                target="_blank" class="map-link">
                                                📍 ดูแผนที่
                                            </a>
                                            <br>
                                            <span style="font-size:11px;color:var(--text-muted);">
                                                <?= number_format((float)$log['scan_lat'], 5) ?>, <?= number_format((float)$log['scan_lng'], 5) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);font-size:12px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12px;color:var(--text-muted);">
                                        <?= htmlspecialchars($log['ip_address'] ?: '—') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="padding:16px 20px;">
                        <?php
                        $qp = $_GET;
                        $start = max(1, $page - 3);
                        $end   = min($total_pages, $page + 3);
                        if ($start > 1) {
                            $qp['page'] = 1;
                            echo '<a href="?' . http_build_query($qp) . '" class="page-link">1</a>';
                            if ($start > 2) echo '<span style="padding:6px 4px;color:var(--text-muted);">…</span>';
                        }
                        for ($pi = $start; $pi <= $end; $pi++) {
                            $qp['page'] = $pi;
                            $active = $pi == $page ? 'active' : '';
                            echo '<a href="?' . http_build_query($qp) . '" class="page-link ' . $active . '">' . $pi . '</a>';
                        }
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) echo '<span style="padding:6px 4px;color:var(--text-muted);">…</span>';
                            $qp['page'] = $total_pages;
                            echo '<a href="?' . http_build_query($qp) . '" class="page-link">' . $total_pages . '</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Danger zone: clear log (super admin only) -->
        <?php if (!$admin_hoscode): ?>
            <div class="danger-zone no-print">
                <h4>⚠️ จัดการ Log (ผู้ดูแลระบบหลักเท่านั้น)</h4>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <form method="POST" onsubmit="return confirm('ยืนยัน: ลบ Log ที่เก่ากว่า 90 วัน?')">
                        <input type="hidden" name="action" value="clear_old">
                        <input type="hidden" name="days" value="90">
                        <button type="submit"
                            style="height:38px;padding:0 18px;border-radius:8px;border:1px solid rgba(239,68,68,0.4);
                               background:rgba(239,68,68,0.08);color:var(--color-red);font-weight:700;cursor:pointer;">
                            🗑️ ลบ Log เก่ากว่า 90 วัน
                        </button>
                    </form>
                    <form method="POST" onsubmit="return confirm('ยืนยัน: ลบ Log ทั้งหมด? ไม่สามารถย้อนกลับได้!')">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit"
                            style="height:38px;padding:0 18px;border-radius:8px;border:1px solid var(--color-red);
                               background:var(--color-red);color:white;font-weight:700;cursor:pointer;">
                            🗑️ ลบ Log ทั้งหมด
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <style>
        @media print {

            .admin-navbar,
            .filter-bar,
            .danger-zone,
            .pagination,
            .no-print {
                display: none !important;
            }

            .log-table th,
            .log-table td {
                font-size: 11px !important;
                padding: 6px 8px !important;
            }

            body {
                background: white !important;
            }

            .card-dark {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }
        }
    </style>
</body>

</html>