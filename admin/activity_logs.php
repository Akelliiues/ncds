<?php
// admin/activity_logs.php
// User Activity & Dashboard Audit Log Viewer (Strictly excludes main Super Admin)

require_once __DIR__ . '/../config/session.php';

// สิทธิ์เข้าถึง: เฉพาะผู้ดูแลระบบหลัก (Super Admin สสอ. ตาลสุม) เท่านั้น
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !empty($_SESSION['admin_hoscode'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/activity_logger.php';

ensureActivityLogTable($pdo);

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$is_super_admin = (!isset($admin_hoscode) || empty($admin_hoscode));
$admin_title = function_exists('get_admin_title') ? get_admin_title() : 'ผู้ดูแลระบบ';
$hc_names = function_exists('get_health_units') ? get_health_units() : [];

// Handle Log Cleanup Action (Super Admin Only)
$action_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $is_super_admin) {
    $cleanAction = $_POST['action'];
    if ($cleanAction === 'clear_old') {
        $days = max(7, intval($_POST['days'] ?? 30));
        $stmtDel = $pdo->prepare("DELETE FROM user_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmtDel->execute([$days]);
        $delCount = $stmtDel->rowCount();
        $action_msg = "ล้างประวัติกิจกรรมที่เก่ากว่า {$days} วัน เรียบร้อยแล้ว (ลบ {$delCount} รายการ)";
    } elseif ($cleanAction === 'clear_all') {
        $pdo->exec("DELETE FROM user_activity_logs WHERE 1=1");
        $action_msg = "ล้างประวัติกิจกรรมทั้งหมดเรียบร้อยแล้ว";
    }
}

// Filters
$search      = trim($_GET['search'] ?? '');
$filter_hsc  = trim($_GET['hoscode'] ?? '');
$filter_cat  = trim($_GET['category'] ?? '');
$filter_user = trim($_GET['user_type'] ?? '');
$filter_date = trim($_GET['date_range'] ?? 'today');
$page        = max(1, intval($_GET['page'] ?? 1));
$limit       = 40;
$offset      = ($page - 1) * $limit;

$where = ['1=1'];
$params = [];

// Area admin can only see their own hoscode
if ($admin_hoscode) {
    $where[] = "hoscode = ?";
    $params[] = $admin_hoscode;
} elseif ($filter_hsc !== '') {
    $where[] = "hoscode = ?";
    $params[] = $filter_hsc;
}

if ($filter_cat !== '') {
    $where[] = "action_category = ?";
    $params[] = $filter_cat;
}

if ($filter_user !== '') {
    $where[] = "user_type = ?";
    $params[] = $filter_user;
}

if ($filter_date === 'today') {
    $where[] = "DATE(created_at) = CURDATE()";
} elseif ($filter_date === '7days') {
    $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter_date === '30days') {
    $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

if ($search !== '') {
    $where[] = "(username LIKE ? OR user_fullname LIKE ? OR action_title LIKE ? OR ip_address LIKE ? OR action_detail LIKE ?)";
    $sVal = "%{$search}%";
    $params[] = $sVal;
    $params[] = $sVal;
    $params[] = $sVal;
    $params[] = $sVal;
    $params[] = $sVal;
}

$whereSql = implode(' AND ', $where);

// Total count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_activity_logs WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit) ?: 1;

// Fetch logs
$querySql = "
    SELECT * FROM user_activity_logs 
    WHERE {$whereSql} 
    ORDER BY created_at DESC, log_id DESC 
    LIMIT {$limit} OFFSET {$offset}
";
$dataStmt = $pdo->prepare($querySql);
$dataStmt->execute($params);
$logs = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate KPI statistics
$kpiWhere = $admin_hoscode ? "WHERE hoscode = '{$admin_hoscode}'" : "";
$kpiTodayWhere = $admin_hoscode ? "WHERE hoscode = '{$admin_hoscode}' AND DATE(created_at) = CURDATE()" : "WHERE DATE(created_at) = CURDATE()";

$todayTotal = 0;
$todayActiveUsers = 0;
$topAction = 'ยังไม่มีข้อมูล';
$topHosname = 'ยังไม่มีข้อมูล';

try {
    $todayTotal = (int)$pdo->query("SELECT COUNT(*) FROM user_activity_logs {$kpiTodayWhere}")->fetchColumn();
    $todayActiveUsers = (int)$pdo->query("SELECT COUNT(DISTINCT username) FROM user_activity_logs {$kpiTodayWhere}")->fetchColumn();
    
    $topActRow = $pdo->query("SELECT action_title, COUNT(*) as c FROM user_activity_logs {$kpiWhere} GROUP BY action_title ORDER BY c DESC LIMIT 1")->fetch();
    if ($topActRow) $topAction = $topActRow['action_title'];

    $topHosRow = $pdo->query("SELECT hosname, COUNT(*) as c FROM user_activity_logs WHERE hosname IS NOT NULL GROUP BY hosname ORDER BY c DESC LIMIT 1")->fetch();
    if ($topHosRow) $topHosname = $topHosRow['hosname'];
} catch (\Throwable $e) {}

$categoryBadges = [
    'AUTH'        => ['label' => 'เข้าสู่ระบบ / บัญชี', 'color' => '#3B82F6', 'bg' => 'rgba(59, 130, 246, 0.12)', 'icon' => '🔑'],
    'SCREENING'   => ['label' => 'คัดกรอง อสม.', 'color' => '#10B981', 'bg' => 'rgba(16, 185, 129, 0.12)', 'icon' => '📱'],
    'ASSIGNMENT'  => ['label' => 'จ่ายงานคัดกรอง', 'color' => '#8B5CF6', 'bg' => 'rgba(139, 92, 246, 0.12)', 'icon' => '📋'],
    'DPAC'        => ['label' => 'คลินิก DPAC', 'color' => '#EC4899', 'bg' => 'rgba(236, 72, 153, 0.12)', 'icon' => '🩺'],
    'IMPORT_SYNC' => ['label' => 'ซิงค์ / นำเข้าข้อมูล', 'color' => '#06B6D4', 'bg' => 'rgba(6, 182, 212, 0.12)', 'icon' => '📥'],
    'BROADCAST'   => ['label' => 'ประกาศข่าวสาร', 'color' => '#F59E0B', 'bg' => 'rgba(245, 158, 11, 0.12)', 'icon' => '📢'],
    'SETTINGS'    => ['label' => 'การตั้งค่าระบบ', 'color' => '#64748B', 'bg' => 'rgba(100, 116, 139, 0.12)', 'icon' => '⚙️'],
    'REPORTS'     => ['label' => 'รายงานและสถิติ', 'color' => '#6366F1', 'bg' => 'rgba(99, 102, 241, 0.12)', 'icon' => '📊']
];
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
    <title>บันทึกประวัติการใช้งาน (Activity Audit Log) - NCDs Portal อำเภอ<?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-bar {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 16px;
            box-shadow: var(--neumorph-flat);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            border: 1px solid var(--border-color);
        }
        .filter-select, .filter-input {
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 600;
            outline: none;
            transition: all 0.2s;
        }
        .filter-select:focus, .filter-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 2px rgba(13, 44, 84, 0.1);
        }
        .log-table-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        .log-table th {
            background: var(--bg-darker);
            color: var(--text-secondary);
            font-weight: 800;
            text-align: left;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }
        .log-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: top;
        }
        .log-table tr:hover td {
            background: rgba(13, 44, 84, 0.02);
        }
        .user-type-badge {
            font-size: 11px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 8px;
            display: inline-block;
        }
        .badge-staff { background: rgba(59, 130, 246, 0.15); color: #2563EB; }
        .badge-vhv { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .badge-exec { background: rgba(245, 158, 11, 0.15); color: #D97706; }
    </style>
</head>
<body class="admin-body">
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <div style="max-width: 1280px; margin: 40px auto; padding: 0 20px;">
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
            <div>
                <h2 style="color: var(--color-accent); margin: 0; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px;">
                    📜 บันทึกประวัติการใช้งานและกิจกรรม (Activity Audit Log)
                </h2>
                <p style="color: var(--text-secondary); margin: 6px 0 0 0; font-size: 14px;">
                    ตรวจสอบความเคลื่อนไหวและการปฏิบัติงานของเจ้าหน้าที่ รพ.สต. และ อสม. ทั่วอำเภอ 
                </p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" onclick="window.location.reload()" class="btn-dash-action" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--color-primary); padding: 8px 16px; border-radius: 12px; font-size: 13.5px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: var(--neumorph-flat);">
                    🔄 รีเฟรช
                </button>
                <?php if ($is_super_admin): ?>
                    <button type="button" onclick="openCleanupModal()" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #EF4444; padding: 8px 16px; border-radius: 12px; font-size: 13.5px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        🗑️ ล้าง Log เก่า
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($action_msg)): ?>
            <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; padding: 14px 18px; border-radius: 14px; font-weight: 700; margin-bottom: 20px; font-size: 14px;">
                ✅ <?= htmlspecialchars($action_msg) ?>
            </div>
        <?php endif; ?>

        <!-- KPI Statistics -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 24px;">
            <div class="card-dark" style="padding: 18px; background: var(--bg-card); border-radius: 18px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 14px;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(59, 130, 246, 0.15); color: #3B82F6; display: flex; align-items: center; justify-content: center; font-size: 24px;">📊</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">กิจกรรมวันนี้</div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--text-primary);"><?= number_format($todayTotal) ?> รายการ</div>
                </div>
            </div>
            <div class="card-dark" style="padding: 18px; background: var(--bg-card); border-radius: 18px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 14px;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(16, 185, 129, 0.15); color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 24px;">👥</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">ผู้ใช้งาน Active วันนี้</div>
                    <div style="font-size: 22px; font-weight: 800; color: #10B981;"><?= number_format($todayActiveUsers) ?> คน</div>
                </div>
            </div>
            <div class="card-dark" style="padding: 18px; background: var(--bg-card); border-radius: 18px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 14px;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(245, 158, 11, 0.15); color: #F59E0B; display: flex; align-items: center; justify-content: center; font-size: 24px;">🏆</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">กิจกรรมยอดนิยม</div>
                    <div style="font-size: 15px; font-weight: 800; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;" title="<?= htmlspecialchars($topAction) ?>"><?= htmlspecialchars($topAction) ?></div>
                </div>
            </div>
            <div class="card-dark" style="padding: 18px; background: var(--bg-card); border-radius: 18px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 14px;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(139, 92, 246, 0.15); color: #8B5CF6; display: flex; align-items: center; justify-content: center; font-size: 24px;">🏥</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">รพ.สต. ที่มีการใช้งานสูงสุด</div>
                    <div style="font-size: 15px; font-weight: 800; color: #8B5CF6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;" title="<?= htmlspecialchars($topHosname) ?>"><?= htmlspecialchars($topHosname) ?></div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" class="filter-bar">
            <input type="text" name="search" class="filter-input" placeholder="🔍 ค้นหาชื่อ, กิจกรรม, IP..." value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">
            
            <?php if ($is_super_admin): ?>
                <select name="hoscode" class="filter-select">
                    <option value="">🏥 ทุก รพ.สต.</option>
                    <?php foreach ($hc_names as $code => $name): ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= ($filter_hsc === (string)$code) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <select name="category" class="filter-select">
                <option value="">📂 ทุกหมวดหมู่</option>
                <?php foreach ($categoryBadges as $catKey => $catInfo): ?>
                    <option value="<?= $catKey ?>" <?= ($filter_cat === $catKey) ? 'selected' : '' ?>>
                        <?= $catInfo['icon'] ?> <?= $catInfo['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="user_type" class="filter-select">
                <option value="">👤 ทุกประเภทผู้ใช้</option>
                <option value="staff" <?= ($filter_user === 'staff') ? 'selected' : '' ?>>เจ้าหน้าที่ รพ.สต.</option>
                <option value="vhv" <?= ($filter_user === 'vhv') ? 'selected' : '' ?>>อสม.</option>
                <option value="executive" <?= ($filter_user === 'executive') ? 'selected' : '' ?>>ผู้เข้าชม/ผู้บริหาร</option>
            </select>

            <select name="date_range" class="filter-select">
                <option value="today" <?= ($filter_date === 'today') ? 'selected' : '' ?>>📅 วันนี้</option>
                <option value="7days" <?= ($filter_date === '7days') ? 'selected' : '' ?>>7 วันล่าสุด</option>
                <option value="30days" <?= ($filter_date === '30days') ? 'selected' : '' ?>>30 วันล่าสุด</option>
                <option value="all" <?= ($filter_date === 'all') ? 'selected' : '' ?>>ทั้งหมด</option>
            </select>

            <button type="submit" class="btn-giant btn-giant-primary" style="margin: 0; height: 38px; padding: 0 18px; font-size: 13.5px; border-radius: 12px;">
                กรองข้อมูล
            </button>
            <?php if (!empty($search) || !empty($filter_hsc) || !empty($filter_cat) || !empty($filter_user) || $filter_date !== 'today'): ?>
                <a href="activity_logs.php" style="color: var(--color-red); font-size: 13px; font-weight: 700; text-decoration: none; padding: 0 6px;">✕ ล้างตัวกรอง</a>
            <?php endif; ?>
        </form>

        <!-- Log Records Table -->
        <div class="log-table-container">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width: 160px;">🕒 วันที่ / เวลา</th>
                        <th style="width: 170px;">📂 หมวดหมู่</th>
                        <th>📝 กิจกรรมที่ดำเนินการ</th>
                        <th style="width: 220px;">👤 ผู้ดำเนินการ</th>
                        <th style="width: 150px;">🌐 ข้อมูลเครื่อง/IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 60px 16px; color: var(--text-muted);">
                                <div style="font-size: 40px; margin-bottom: 12px;">📭</div>
                                <div style="font-size: 16px; font-weight: 700;">ไม่พบบันทึกกิจกรรมตามเงื่อนไขที่เลือก</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $row): ?>
                            <?php
                                $catInfo = $categoryBadges[$row['action_category']] ?? ['label' => $row['action_category'], 'color' => '#64748B', 'bg' => 'rgba(100,116,139,0.12)', 'icon' => '📌'];
                                $userTypeClass = 'badge-staff';
                                $userTypeLabel = 'จนท. รพ.สต.';
                                if ($row['user_type'] === 'vhv') {
                                    $userTypeClass = 'badge-vhv';
                                    $userTypeLabel = 'อสม.';
                                } elseif ($row['user_type'] === 'executive') {
                                    $userTypeClass = 'badge-exec';
                                    $userTypeLabel = 'ผู้เข้าชม';
                                }
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 800; font-size: 13px; color: var(--text-primary);">
                                        <?= htmlspecialchars(date('d/m/Y', strtotime($row['created_at']))) ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-secondary); font-family: monospace;">
                                        <?= htmlspecialchars(date('H:i:s น.', strtotime($row['created_at']))) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 11.5px; font-weight: 800; padding: 4px 10px; border-radius: 10px; background: <?= $catInfo['bg'] ?>; color: <?= $catInfo['color'] ?>; display: inline-flex; align-items: center; gap: 4px;">
                                        <span><?= $catInfo['icon'] ?></span>
                                        <span><?= $catInfo['label'] ?></span>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 800; font-size: 14px; color: var(--text-primary); margin-bottom: 2px;">
                                        <?= htmlspecialchars($row['action_title']) ?>
                                    </div>
                                    <?php if (!empty($row['action_detail'])): ?>
                                        <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.4; background: var(--bg-main); padding: 4px 8px; border-radius: 8px; margin-top: 4px; border: 1px solid var(--border-color); display: inline-block; max-width: 500px; word-break: break-word;">
                                            <?= htmlspecialchars($row['action_detail']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                        <span class="user-type-badge <?= $userTypeClass ?>"><?= $userTypeLabel ?></span>
                                        <span style="font-weight: 800; font-size: 13px; color: var(--text-primary);">
                                            <?= htmlspecialchars($row['user_fullname'] ?: $row['username']) ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 11.5px; color: var(--text-muted);">
                                        <?= htmlspecialchars($row['hosname'] ?: ($row['hoscode'] ? 'รพ.สต. ' . $row['hoscode'] : '-')) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 12px; font-weight: 700; color: var(--text-secondary); font-family: monospace;">
                                        <?= htmlspecialchars($row['ip_address'] ?: '127.0.0.1') ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;" title="<?= htmlspecialchars($row['user_agent']) ?>">
                                        <?= htmlspecialchars($row['user_agent'] ?: '-') ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 10px;">
                <div style="font-size: 13px; color: var(--text-secondary); font-weight: 600;">
                    แสดงหน้า <?= $page ?> จาก <?= $totalPages ?> (ทั้งหมด <?= number_format($totalRecords) ?> รายการ)
                </div>
                <div style="display: flex; gap: 6px;">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn-dash-action" style="padding: 6px 14px; font-size: 13px; text-decoration: none; border-radius: 10px; background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border-color);">« ก่อนหน้า</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn-dash-action" style="padding: 6px 14px; font-size: 13px; text-decoration: none; border-radius: 10px; background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border-color);">ถัดไป »</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Modal: Cleanup Logs (Super Admin Only) -->
    <?php if ($is_super_admin): ?>
        <div id="cleanupModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
            <div class="card-dark" style="background: var(--bg-card); border-radius: 20px; padding: 24px; max-width: 440px; width: 100%; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color);">
                <h3 style="margin-top: 0; color: var(--color-red); font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    🗑️ ล้างประวัติการใช้งาน (Maintenance)
                </h3>
                <p style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.5; margin-bottom: 20px;">
                    เลือกช่วงเวลาเพื่อลบ Log ประวัติการใช้งานที่เก่าแล้ว เพื่อประหยัดพื้นที่จัดเก็บข้อมูลของระบบ
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="clear_old">
                    <div style="margin-bottom: 16px;">
                        <label style="font-size: 13px; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 6px;">เลือกระยะเวลาที่ต้องการลบ:</label>
                        <select name="days" class="filter-select" style="width: 100%;">
                            <option value="30">ลบ Log ที่เก่ากว่า 30 วัน</option>
                            <option value="60">ลบ Log ที่เก่ากว่า 60 วัน</option>
                            <option value="90">ลบ Log ที่เก่ากว่า 90 วัน</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" onclick="closeCleanupModal()" class="btn-giant btn-giant-secondary" style="margin: 0; height: 40px; padding: 0 16px; font-size: 13.5px; border-radius: 10px;">
                            ยกเลิก
                        </button>
                        <button type="submit" class="btn-giant btn-giant-danger" style="margin: 0; height: 40px; padding: 0 16px; font-size: 13.5px; border-radius: 10px; background: var(--color-red); color: white;">
                            ยืนยันการลบ
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openCleanupModal() {
                document.getElementById('cleanupModal').style.display = 'flex';
            }
            function closeCleanupModal() {
                document.getElementById('cleanupModal').style.display = 'none';
            }
        </script>
    <?php endif; ?>

</body>
</html>
