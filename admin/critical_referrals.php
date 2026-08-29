<?php
// admin/critical_referrals.php - ศูนย์จัดการเคสวิกฤตและการส่งต่อ (Critical Referrals & JHCIS Integration)
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

// Auto-ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `critical_alerts` (
        `alert_id` INT AUTO_INCREMENT PRIMARY KEY,
        `screening_id` INT NULL,
        `citizen_screening_id` INT NULL,
        `hoscode` VARCHAR(10) NOT NULL,
        `target_cid` VARCHAR(20) NOT NULL,
        `patient_name` VARCHAR(150) NOT NULL,
        `age` INT DEFAULT NULL,
        `house_no` VARCHAR(50) DEFAULT NULL,
        `moo` VARCHAR(10) DEFAULT NULL,
        `sub_district_code` VARCHAR(10) DEFAULT NULL,
        `latitude` DECIMAL(10,8) DEFAULT NULL,
        `longitude` DECIMAL(11,8) DEFAULT NULL,
        `crisis_type` VARCHAR(50) NOT NULL,
        `sbp` INT DEFAULT NULL,
        `dbp` INT DEFAULT NULL,
        `dtx` INT DEFAULT NULL,
        `red_flags` TEXT DEFAULT NULL,
        `vhv_name` VARCHAR(150) DEFAULT NULL,
        `vhv_phone` VARCHAR(30) DEFAULT NULL,
        `alert_status` VARCHAR(30) DEFAULT 'pending',
        `acknowledged_by` VARCHAR(100) DEFAULT NULL,
        `acknowledged_at` DATETIME DEFAULT NULL,
        `referral_destination` VARCHAR(100) DEFAULT NULL,
        `referral_notes` TEXT DEFAULT NULL,
        `is_jhcis_synced` TINYINT(1) DEFAULT 0,
        `jhcis_visitno` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (\Throwable $e) {}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_title = function_exists('get_admin_title') ? get_admin_title() : 'ผู้ดูแลระบบ';
$is_super_admin = !empty($_SESSION['is_super_admin']);
$hc_names = function_exists('get_health_units') ? get_health_units() : [];

$selected_hoscode = $_GET['hoscode'] ?? $admin_hoscode ?? '';
$status_filter = $_GET['status'] ?? 'all';

// Fetch Alerts
$where = ["1=1"];
$params = [];

if (!empty($selected_hoscode) && $selected_hoscode !== 'ALL') {
    $where[] = "(hoscode = ? OR hoscode = 'ALL' OR hoscode = '99999')";
    $params[] = $selected_hoscode;
}

if ($status_filter !== 'all') {
    $where[] = "alert_status = ?";
    $params[] = $status_filter;
}

$whereSql = implode(' AND ', $where);

// Summary Stats
$stats = [
    'total_alerts' => 0, 'pending_count' => 0, 'ack_count' => 0, 'referred_count' => 0, 'jhcis_synced_count' => 0
];

try {
    $statsSql = "
        SELECT 
            COUNT(*) as total_alerts,
            SUM(CASE WHEN alert_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN alert_status = 'acknowledged' THEN 1 ELSE 0 END) as ack_count,
            SUM(CASE WHEN alert_status = 'referred_hospital' THEN 1 ELSE 0 END) as referred_count,
            SUM(CASE WHEN is_jhcis_synced = 1 THEN 1 ELSE 0 END) as jhcis_synced_count
        FROM critical_alerts
        WHERE " . (!empty($selected_hoscode) && $selected_hoscode !== 'ALL' ? "hoscode = ?" : "1=1");
    
    $statsStmt = $pdo->prepare($statsSql);
    $statsStmt->execute(!empty($selected_hoscode) && $selected_hoscode !== 'ALL' ? [$selected_hoscode] : []);
    $resStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    if ($resStats) {
        $stats = $resStats;
    }
} catch (\Throwable $e) {}

// Fetch Alert List
$alerts = [];
try {
    $listStmt = $pdo->prepare("
        SELECT * FROM critical_alerts 
        WHERE {$whereSql} 
        ORDER BY alert_id DESC 
        LIMIT 100
    ");
    $listStmt->execute($params);
    $alerts = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {}

$districtName = defined('DISTRICT_NAME') ? DISTRICT_NAME : 'ตาลสุม';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <script>
        (function() {
            window.name = "ncd_critical_referrals_tab";
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ศูนย์จัดการเคสวิกฤตและการส่งต่อ - NCDs Portal อำเภอ<?= htmlspecialchars($districtName) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .kpi-grid-referral {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        @media (max-width: 900px) {
            .kpi-grid-referral {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 500px) {
            .kpi-grid-referral {
                grid-template-columns: 1fr;
            }
        }

        .referral-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--neumorph-flat);
        }

        .referral-header-action {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            text-decoration: none;
            padding: 9px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            box-shadow: var(--neumorph-flat);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .referral-header-action:hover {
            background: var(--bg-darker);
            transform: translateY(-2px);
        }

        .referral-header-action .action-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 30px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--neumorph-flat);
        }

        .referral-header-action.download .action-icon { color: #059669; }
        .referral-header-action.station .action-icon { color: #DC2626; }

        .referral-case-table th {
            font-weight: 700;
        }

        .referral-case-table {
            border-collapse: separate !important;
            border-spacing: 0 5px;
        }

        .referral-case-table td {
            font-weight: 400;
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.18s ease, border-color 0.18s ease;
        }

        .referral-case-table tbody tr td:first-child {
            border-left: 1px solid var(--border-color);
            border-radius: 12px 0 0 12px;
        }

        .referral-case-table tbody tr td:last-child {
            border-right: 1px solid var(--border-color);
            border-radius: 0 12px 12px 0;
        }

        .referral-case-table tbody tr.is-pending td {
            background: rgba(220, 38, 38, 0.045);
        }

        .referral-case-table tbody tr:hover td {
            background: rgba(37, 99, 235, 0.09);
            border-color: rgba(37, 99, 235, 0.28);
        }

        [data-theme="dark"] .referral-case-table tbody tr:hover td {
            background: rgba(56, 189, 248, 0.12);
            border-color: rgba(56, 189, 248, 0.3);
        }

        .referral-case-table tbody tr.case-row {
            cursor: pointer;
        }

        .referral-case-table tbody tr.case-row:focus-visible td {
            background: rgba(37, 99, 235, 0.09);
            border-color: #2563EB;
            outline: none;
        }

        .case-detail-modal-box {
            max-width: 760px;
            width: min(760px, calc(100vw - 40px));
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            padding: 22px 26px;
            box-sizing: border-box;
            border: none;
            border-radius: 26px;
            box-shadow: none;
            background: rgba(248, 250, 252, 0.94);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            text-align: center;
        }

        [data-theme="dark"] .case-detail-modal-box {
            background: rgba(15, 23, 42, 0.94);
        }

        .case-detail-heading {
            margin: 0 0 14px;
            font-size: clamp(23px, 3vw, 28px);
            line-height: 1.25;
            font-weight: 900;
            color: #DC2626;
        }

        .case-detail-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .case-detail-summary-item {
            padding: 10px 12px;
            border-radius: 14px;
            background: var(--bg-darker);
            box-shadow: var(--neumorph-inset);
            text-align: left;
        }

        .case-detail-panel {
            padding: 14px 16px;
            border-radius: 18px;
            background: var(--bg-darker);
            box-shadow: var(--neumorph-inset);
            text-align: left;
        }

        .case-detail-time-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 10px;
            margin-bottom: 12px;
            border-bottom: 1px dashed var(--border-color);
        }

        .case-detail-time-value {
            padding: 5px 12px;
            border-radius: 9px;
            color: #DC2626;
            background: rgba(220, 38, 38, 0.12);
            font-size: 14px;
            font-weight: 800;
            white-space: nowrap;
        }

        .case-detail-vitals {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        .case-detail-card {
            min-width: 0;
            padding: 10px 12px;
            border-radius: 14px;
            background: var(--bg-card);
            box-shadow: var(--neumorph-flat);
        }

        .case-detail-label {
            display: block;
            margin-bottom: 4px;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
        }

        .case-detail-value {
            color: var(--text-primary);
            font-size: 15px;
            font-weight: 700;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .case-detail-value.critical {
            color: #DC2626;
            font-size: clamp(22px, 3vw, 28px);
            font-weight: 900;
        }

        .case-detail-line {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-size: 15px;
            line-height: 1.5;
        }

        .case-detail-line-icon {
            width: 22px;
            flex: 0 0 22px;
            text-align: center;
        }

        .case-detail-referral-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }

        .case-detail-phone-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 10px;
            padding: 10px 14px;
            border: 1.5px solid #10B981;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.12);
        }

        .case-detail-actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }

        .case-detail-action {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 46px;
            padding: 0 18px;
            border: none;
            border-radius: 14px;
            color: #ffffff;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 800;
        }

        .case-detail-action.map { background: #2563EB; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .case-detail-action.call { background: #10B981; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }

        @media (max-width: 700px) {
            .case-detail-modal-box { padding: 18px; }
            .case-detail-summary-grid,
            .case-detail-referral-grid { grid-template-columns: 1fr; }
            .case-detail-time-row,
            .case-detail-phone-box { align-items: flex-start; flex-direction: column; }
        }

        @media (max-width: 480px) {
            .case-detail-vitals,
            .case-detail-actions { grid-template-columns: 1fr; flex-direction: column; }
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-pending { background: rgba(220, 38, 38, 0.15); color: #DC2626; border: 1px solid #DC2626; }
        .status-acknowledged { background: rgba(245, 158, 11, 0.15); color: #D97706; border: 1px solid #F59E0B; }
        .status-referred_hospital { background: rgba(59, 130, 246, 0.15); color: #2563EB; border: 1px solid #3B82F6; }
        .status-resolved { background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid #10B981; }

        /* Modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(8px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            max-width: 520px;
            width: 100%;
            padding: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.4);
            color: var(--text-primary);
        }

        /* Keep the case viewer identical in width to the Red Alert Station modal.
           This rule must remain after the shared .modal-box rule. */
        .modal-box.case-detail-modal-box {
            width: 100%;
            max-width: 760px;
            padding: 22px 26px;
            border: none;
            border-radius: 26px;
            box-shadow: none;
            background: rgba(248, 250, 252, 0.94);
        }

        [data-theme="dark"] .modal-box.case-detail-modal-box {
            background: rgba(15, 23, 42, 0.94);
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Top Navbar -->
        <?php require_once __DIR__ . '/navbar.php'; ?>

        <main class="main-content" style="padding: 20px 24px;">
            <!-- Header Section -->
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
                <div>
                    <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: var(--color-accent, #0d2c54); display: flex; align-items: center; gap: 10px;">
                        <span>🚨 ศูนย์จัดการเคสวิกฤต & ส่งต่อการรักษา (Emergency Triage)</span>
                    </h1>
                    <p style="margin: 4px 0 0 0; font-size: 13.5px; color: var(--text-secondary);">
                        ระบบรับเรื่องเหตุวิกฤตฉุกเฉินจากชุมชน • ประเมิน Triage • สั่งส่งต่อ รพ.ตาลสุม & ซิงค์เข้า JHCIS
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <a href="download_station.php?format=zip" class="referral-header-action download" title="ดาวน์โหลดตัวรับสัญญาณระบบ NCDs Red Alert Station">
                        <span class="action-icon">📥</span>
                        <span>ดาวน์โหลดตัวรับสัญญาณ</span>
                    </a>
                    <a href="emergency_receiver.php" onclick="openOrFocusTab('emergency_receiver.php', 'ncd_red_alert_station_tab'); return false;" class="referral-header-action station">
                        <span class="action-icon">🖥️</span>
                        <span>เปิดหน้าจอ Red Alert Station</span>
                    </a>
                </div>
            </div>

            <!-- KPI Metric Cards -->
            <div class="kpi-grid-referral">
                <div class="referral-card" style="border-left: 4px solid #DC2626;">
                    <div style="font-size: 12px; font-weight: 500; color: var(--text-secondary);">เคสรอรับเรื่องด่วน (Pending)</div>
                    <div style="font-size: 32px; font-weight: 900; color: #DC2626; margin-top: 4px;">
                        <?= number_format($stats['pending_count']) ?> <span style="font-size: 14px; font-weight: 500; color: var(--text-secondary);">เคส</span>
                    </div>
                </div>
                <div class="referral-card" style="border-left: 4px solid #F59E0B;">
                    <div style="font-size: 12px; font-weight: 500; color: var(--text-secondary);">รับทราบแล้ว/กำลังดูแล</div>
                    <div style="font-size: 32px; font-weight: 900; color: #F59E0B; margin-top: 4px;">
                        <?= number_format($stats['ack_count']) ?> <span style="font-size: 14px; font-weight: 500; color: var(--text-secondary);">เคส</span>
                    </div>
                </div>
                <div class="referral-card" style="border-left: 4px solid #3B82F6;">
                    <div style="font-size: 12px; font-weight: 500; color: var(--text-secondary);">ส่งต่อ รพ.ตาลสุม แล้ว</div>
                    <div style="font-size: 32px; font-weight: 900; color: #3B82F6; margin-top: 4px;">
                        <?= number_format($stats['referred_count']) ?> <span style="font-size: 14px; font-weight: 500; color: var(--text-secondary);">เคส</span>
                    </div>
                </div>
                <div class="referral-card" style="border-left: 4px solid #10B981;">
                    <div style="font-size: 12px; font-weight: 500; color: var(--text-secondary);">ซิงค์สร้างใน JHCIS แล้ว</div>
                    <div style="font-size: 32px; font-weight: 900; color: #10B981; margin-top: 4px;">
                        <?= number_format($stats['jhcis_synced_count']) ?> <span style="font-size: 14px; font-weight: 500; color: var(--text-secondary);">เคส</span>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="referral-card" style="margin-bottom: 20px; padding: 14px 20px;">
                <form method="GET" action="critical_referrals.php" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span style="font-size: 13px; font-weight: 600; color: var(--text-primary);">กรองข้อมูล:</span>
                        
                        <select name="hoscode" onchange="this.form.submit()" style="background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border-color); padding: 8px 12px; border-radius: 10px; font-size: 13px; font-weight: 500; outline: none;">
                            <option value="">ทุก รพ.สต. (ภาพรวมอำเภอ)</option>
                            <?php foreach ($hc_names as $code => $name): ?>
                                <option value="<?= $code ?>" <?= $selected_hoscode == $code ? 'selected' : '' ?>>
                                    [<?= $code ?>] <?= $name ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="status" onchange="this.form.submit()" style="background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border-color); padding: 8px 12px; border-radius: 10px; font-size: 13px; font-weight: 500; outline: none;">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>ทุกสถานะ</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>🚨 รอรับเรื่อง (Pending)</option>
                            <option value="acknowledged" <?= $status_filter === 'acknowledged' ? 'selected' : '' ?>>⏳ รับทราบแล้ว (Acknowledged)</option>
                            <option value="referred_hospital" <?= $status_filter === 'referred_hospital' ? 'selected' : '' ?>>🏥 ส่งต่อ รพ. (Referred)</option>
                            <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>✅ สิ้นสุดการดูแล (Resolved)</option>
                        </select>
                    </div>

                    <button type="button" onclick="location.reload()" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); padding: 8px 14px; border-radius: 10px; font-size: 12.5px; font-weight: 700; cursor: pointer;">
                        🔄 รีเฟรชรายการ
                    </button>
                </form>
            </div>

            <!-- Table of Emergency Cases -->
            <div class="referral-card">
                <div class="table-responsive">
                    <table class="custom-table referral-case-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 60px;">#</th>
                                <th>ผู้ป่วย / CID</th>
                                <th>ที่อยู่ / รพ.สต.</th>
                                <th>สัญญาณชีพวิกฤต</th>
                                <th>ภาวะฉุกเฉิน / Red Flags</th>
                                <th>อสม. ผู้แจ้ง</th>
                                <th style="text-align: center;">สถานะ</th>
                                <th style="text-align: center; width: 220px;">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alerts)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                                        <div style="font-size: 32px; margin-bottom: 8px;">🛡️</div>
                                        <div style="font-weight: 700;">ไม่พบเคสวิกฤตฉุกเฉินตามเงื่อนไขที่เลือก</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alerts as $row): ?>
                                    <?php
                                    $isPending = ($row['alert_status'] === 'pending');
                                    $isReferred = ($row['alert_status'] === 'referred_hospital');
                                    $mapUrl = ($row['latitude'] && $row['longitude'])
                                        ? "https://www.google.com/maps?q={$row['latitude']},{$row['longitude']}"
                                        : "https://www.google.com/maps/search/อำเภอตาลสุม";
                                    ?>
                                    <tr class="case-row<?= $isPending ? ' is-pending' : '' ?>"
                                        tabindex="0"
                                        role="button"
                                        aria-label="เปิดรายละเอียดเคส #<?= $row['alert_id'] ?>"
                                        onclick="openCaseDetail(event, <?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)"
                                        onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openCaseDetail(event, <?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>); }">
                                        <td style="text-align: center; font-weight: 700; color: var(--text-secondary);">
                                            #<?= $row['alert_id'] ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; font-size: 14.5px; color: var(--text-primary);">
                                                <?= htmlspecialchars($row['patient_name']) ?>
                                            </div>
                                            <div style="font-size: 11.5px; color: var(--text-secondary); font-family: monospace;">
                                                CID: <?= htmlspecialchars($row['target_cid']) ?> <?= $row['age'] ? "({$row['age']} ปี)" : '' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 12.5px; font-weight: 400;">
                                                บ้านเลขที่ <?= htmlspecialchars($row['house_no'] ?: '-') ?> ม.<?= htmlspecialchars($row['moo'] ?: '-') ?>
                                            </div>
                                            <div style="font-size: 11.5px; color: var(--text-secondary);">
                                                [<?= $row['hoscode'] ?>] <?= $hc_names[$row['hoscode']] ?? 'รพ.สต.' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 14px; font-weight: 800; color: <?= $row['sbp'] >= 180 ? '#DC2626' : '#10B981' ?>;">
                                                BP: <?= $row['sbp'] ? "{$row['sbp']}/{$row['dbp']}" : '-' ?> mmHg
                                            </div>
                                            <div style="font-size: 12px; font-weight: 700; color: <?= $row['dtx'] >= 300 ? '#DC2626' : '#F59E0B' ?>;">
                                                DTX: <?= $row['dtx'] ? "{$row['dtx']} mg%" : '-' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 12.5px; font-weight: 600; color: #DC2626;">
                                                <?= htmlspecialchars($row['crisis_type']) ?>
                                            </div>
                                            <?php if (!empty($row['red_flags'])): ?>
                                                <div style="font-size: 11px; color: var(--text-secondary); line-height: 1.3;">
                                                    ⚠️ <?= htmlspecialchars($row['red_flags']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 12.5px; font-weight: 500;">
                                                <?= htmlspecialchars($row['vhv_name'] ?: 'อสม. ในพื้นที่') ?>
                                            </div>
                                            <?php 
                                            $cbPhone = !empty($row['contact_phone']) ? $row['contact_phone'] : ($row['vhv_phone'] ?? '');
                                            $cbType = ($row['contact_type'] ?? 'vhv') === 'relative' ? 'ญาติ' : 'อสม.';
                                            if (!empty($cbPhone)): 
                                            ?>
                                                <a href="tel:<?= htmlspecialchars($cbPhone) ?>" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: #10B981; text-decoration: none; font-weight: 700; background: rgba(16,185,129,0.1); padding: 2px 6px; border-radius: 6px; margin-top: 3px;">
                                                    📞 <?= htmlspecialchars($cbPhone) ?> <span style="font-size: 10px; font-weight: normal; color: var(--text-muted);">(<?= $cbType ?>)</span>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($row['alert_status'] === 'pending'): ?>
                                                <span class="status-pill status-pending">🚨 รอรับเรื่อง</span>
                                            <?php elseif ($row['alert_status'] === 'acknowledged'): ?>
                                                <span class="status-pill status-acknowledged">⏳ รับทราบแล้ว</span>
                                            <?php elseif ($row['alert_status'] === 'referred_hospital'): ?>
                                                <span class="status-pill status-referred_hospital">🏥 ส่งต่อ รพ.</span>
                                                <?php if ($row['is_jhcis_synced']): ?>
                                                    <div style="font-size: 10.5px; color: #10B981; font-weight: 600; margin-top: 3px;">✓ JHCIS Synced</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="status-pill status-resolved">✅ ปิดเคสแล้ว</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                                                <?php if ($isPending): ?>
                                                    <button type="button" onclick="ackAlert(<?= $row['alert_id'] ?>)" class="btn-action" style="padding: 5px 10px; font-size: 11.5px; background: #DC2626; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                                        🔕 รับเรื่อง
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" onclick="openReferModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)" class="btn-action" style="padding: 5px 10px; font-size: 11.5px; background: #3B82F6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                                    🏥 สั่งส่งต่อ
                                                </button>

                                                <button type="button" onclick="openSlipModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)" class="btn-action" style="padding: 5px 10px; font-size: 11.5px; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; font-weight: 600; cursor: pointer;" title="ออกใบส่งต่ออิเล็กทรอนิกส์">
                                                    📋 e-Slip
                                                </button>

                                                <a href="<?= $mapUrl ?>" target="_blank" class="btn-action" style="padding: 5px 10px; font-size: 11.5px; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; text-decoration: none; font-weight: 600;" title="เปิดแผนที่นำทาง">
                                                    ⚓️
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Read-only case detail modal -->
    <div id="case-detail-modal" class="modal-backdrop" onclick="closeCaseDetailFromBackdrop(event)">
        <div class="modal-box case-detail-modal-box" role="dialog" aria-modal="true" aria-labelledby="case-detail-title" tabindex="-1">
            <h2 id="case-detail-title" class="case-detail-heading">-</h2>

            <div class="case-detail-summary-grid">
                <div class="case-detail-summary-item">
                    <span class="case-detail-label">รหัสเคส</span>
                    <div id="detail-case-id" class="case-detail-value">-</div>
                </div>
                <div class="case-detail-summary-item">
                    <span class="case-detail-label">เลขประจำตัวเป้าหมาย (CID)</span>
                    <div id="detail-cid" class="case-detail-value">-</div>
                </div>
                <div class="case-detail-summary-item">
                    <span class="case-detail-label">สถานะเคส</span>
                    <div id="detail-case-status" class="case-detail-value">-</div>
                </div>
            </div>

            <div class="case-detail-panel">
                <div class="case-detail-time-row">
                    <span class="case-detail-label" style="margin: 0; font-size: 13.5px;">วันและเวลาที่แจ้ง:</span>
                    <span id="detail-created-at" class="case-detail-time-value">-</span>
                </div>

                <div class="case-detail-vitals">
                    <div class="case-detail-card">
                        <span class="case-detail-label">🩺 ความดันโลหิต</span>
                        <div id="detail-bp" class="case-detail-value critical">-</div>
                    </div>
                    <div class="case-detail-card">
                        <span class="case-detail-label">🩸 น้ำตาล DTX</span>
                        <div id="detail-dtx" class="case-detail-value critical" style="color: #D97706;">-</div>
                    </div>
                </div>

                <div class="case-detail-line">
                    <span class="case-detail-line-icon">📍</span>
                    <div><strong>ที่อยู่:</strong> <span id="detail-address">-</span> <span id="detail-hoscode" style="color: var(--text-secondary);">-</span></div>
                </div>
                <div class="case-detail-line">
                    <span class="case-detail-line-icon">⚠️</span>
                    <div><strong>ภาวะวิกฤต:</strong> <span id="detail-crisis" style="color: #DC2626; font-weight: 700;">-</span></div>
                </div>
                <div class="case-detail-line">
                    <span class="case-detail-line-icon">👩‍⚕️</span>
                    <div><strong>อสม. ผู้แจ้ง:</strong> <span id="detail-contact">-</span></div>
                </div>

                <div id="detail-phone-box" class="case-detail-phone-box">
                    <div>
                        <span class="case-detail-label" style="color: #059669;">เบอร์โทรติดต่อกลับด่วน:</span>
                        <div id="detail-contact-phone" class="case-detail-value" style="font-size: 19px; font-weight: 900;">-</div>
                    </div>
                    <a id="detail-call-link-inline" class="case-detail-action call" href="#" style="flex: 0 0 auto; min-height: 40px;">📞 โทรทันที</a>
                </div>

                <div class="case-detail-referral-grid">
                    <div class="case-detail-card">
                        <span class="case-detail-label">ข้อมูลการส่งต่อ</span>
                        <div id="detail-referral" class="case-detail-value">-</div>
                    </div>
                    <div class="case-detail-card">
                        <span class="case-detail-label">สถานะ JHCIS</span>
                        <div id="detail-jhcis" class="case-detail-value">-</div>
                    </div>
                </div>
            </div>

            <div class="case-detail-actions">
                <a id="detail-map-link" class="case-detail-action map" href="#" target="_blank" rel="noopener">⚓️ เปิดแผนที่ GPS</a>
                <a id="detail-call-link" class="case-detail-action call" href="#">📞 โทรติดต่อ</a>
            </div>
        </div>
    </div>

    <!-- Refer & JHCIS Action Modal -->
    <div id="refer-modal" class="modal-backdrop">
        <div class="modal-box">
            <h3 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 900; color: #3B82F6; display: flex; align-items: center; gap: 8px;">
                <span>🏥</span> สั่งส่งต่อผู้ป่วย & บันทึกลง JHCIS
            </h3>
            
            <div id="modal-target-patient" style="background: var(--bg-darker); padding: 12px; border-radius: 12px; margin-bottom: 16px; font-size: 13px;">
                <!-- Target patient summary -->
            </div>

            <form id="form-refer-submit" onsubmit="submitReferral(event)">
                <input type="hidden" id="refer-alert-id" name="alert_id">

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 800; margin-bottom: 6px;">สถานพยาบาลปลายทางที่ส่งต่อ:</label>
                    <select id="refer-dest" name="referral_destination" class="form-select" style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-weight: 700;">
                        <option value="โรงพยาบาลตาลสุม (10957)" selected>🏥 โรงพยาบาลตาลสุม (แม่ข่ายหลัก - รหัส 10957)</option>
                        <option value="โรงพยาบาลสรรพสิทธิประสงค์ (10670)">🏥 โรงพยาบาลสรรพสิทธิประสงค์ (ศูนย์อุบลฯ - รหัส 10670)</option>
                        <option value="โรงพยาบาลวารินชำราบ (10738)">🏥 โรงพยาบาลวารินชำราบ (รหัส 10738)</option>
                        <option value="รพ.สต. ในเขตรับผิดชอบ">🏥 รพ.สต. ในเขตพื้นที่ (รับตัวเข้าสังเกตอาการ)</option>
                    </select>
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 800; margin-bottom: 6px;">การวินิจฉัยเบื้องต้น / บันทึกการส่งต่อ:</label>
                    <textarea id="refer-notes" name="referral_notes" rows="3" class="form-input" style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); box-sizing: border-box;" placeholder="ระบุอาการสำคัญ หรือข้อสั่งการของแพทย์/พยาบาล..."></textarea>
                </div>

                <div style="margin-bottom: 18px; background: rgba(16, 185, 129, 0.08); border: 1px solid #10B981; border-radius: 12px; padding: 12px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; color: #10B981; cursor: pointer;">
                        <input type="checkbox" id="refer-sync-jhcis" name="sync_jhcis" value="1" checked style="width: 18px; height: 18px;">
                        <span>⚡ ซิงค์สร้าง Record ส่งต่อไปยังตาราง visitrefer ใน JHCIS ทันที</span>
                    </label>
                    <div style="font-size: 11.5px; color: var(--text-secondary); margin-left: 26px; margin-top: 4px;">
                        ระบบจะสร้างรหัสรับบริการและบันทึกแฟ้ม REFER ในฐานข้อมูล JHCIS ของ รพ.สต. ให้อัตโนมัติ
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeReferModal()" style="padding: 10px 18px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-weight: 700; cursor: pointer;">
                        ยกเลิก
                    </button>
                    <button type="submit" id="btn-submit-refer" style="padding: 10px 24px; border-radius: 10px; border: none; background: #3B82F6; color: white; font-weight: 800; cursor: pointer; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35);">
                        🚀 ยืนยันการสั่งส่งต่อ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- e-Referral Slip Modal (Printable) -->
    <div id="slip-modal" class="modal-backdrop">
        <div class="modal-box" style="max-width: 480px; text-align: center;">
            <div style="border: 2px dashed #DC2626; border-radius: 18px; padding: 18px; background: rgba(220, 38, 38, 0.03); margin-bottom: 16px;">
                <div style="font-size: 11px; font-weight: 800; color: #DC2626; letter-spacing: 1px; text-transform: uppercase;">
                    EMERGENCY FAST-TRACK REFERRAL SLIP
                </div>
                <h2 style="margin: 4px 0 6px 0; font-size: 20px; font-weight: 900; color: var(--color-accent, #0d2c54);">
                    ใบส่งต่อกรณีวิกฤตฉุกเฉิน
                </h2>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    สาธารณสุขอำเภอ<?= htmlspecialchars($districtName) ?> • รพ.สต. ประจำตำบล
                </div>

                <div style="margin: 16px 0; padding: 12px; background: var(--bg-card); border-radius: 14px; text-align: left; font-size: 13px;">
                    <div style="font-size: 15px; font-weight: 900; color: var(--text-primary); margin-bottom: 6px;" id="slip-patient-name">-</div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;"><strong>CID:</strong> <span id="slip-cid">-</span></div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;"><strong>ที่อยู่:</strong> <span id="slip-address">-</span></div>
                    <div style="font-size: 14px; font-weight: 900; color: #DC2626; margin-top: 8px;" id="slip-vitals">-</div>
                    <div style="font-size: 12px; color: #DC2626; font-weight: 700; margin-top: 4px;" id="slip-crisis">-</div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 6px;"><strong>ปลายทาง:</strong> <span id="slip-dest" style="color: #3B82F6; font-weight: 800;">-</span></div>
                </div>

                <!-- QR Code representation -->
                <div style="background: white; padding: 10px; display: inline-block; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <img id="slip-qr-img" src="" alt="Referral QR" style="width: 140px; height: 140px; display: block;">
                </div>
                <div style="font-size: 11px; color: var(--text-secondary); margin-top: 6px;">
                    สแกนที่จุดคัดกรองห้องฉุกเฉิน (ER) เพื่อดึงข้อมูลประวัติทันที
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" onclick="window.print()" style="padding: 10px 18px; border-radius: 10px; border: none; background: #10B981; color: white; font-weight: 800; cursor: pointer;">
                    🖨️ พิมพ์ใบส่งต่อ
                </button>
                <button type="button" onclick="closeSlipModal()" style="padding: 10px 18px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-weight: 700; cursor: pointer;">
                    ปิดหน้าต่าง
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        let lastCaseDetailTrigger = null;

        function setCaseDetailText(id, value) {
            document.getElementById(id).textContent = value || '-';
        }

        function formatCaseDateTime(value) {
            if (!value) return '-';
            const date = new Date(String(value).replace(/-/g, '/'));
            if (Number.isNaN(date.getTime())) return value;

            const now = new Date();
            const diffSeconds = Math.max(0, Math.floor((now - date) / 1000));
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const buddhistYear = date.getFullYear() + 543;
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');

            const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
            const startOfCaseDay = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
            const dayDifference = Math.max(0, Math.floor((startOfToday - startOfCaseDay) / 86400000));

            let elapsedText;
            if (dayDifference === 0) {
                if (diffSeconds < 45) {
                    elapsedText = 'เมื่อสักครู่';
                } else if (diffSeconds < 3600) {
                    elapsedText = `${Math.floor(diffSeconds / 60)} นาทีที่แล้ว`;
                } else {
                    elapsedText = `${Math.floor(diffSeconds / 3600)} ชม. ที่แล้ว`;
                }
            } else {
                elapsedText = `${dayDifference} วันที่แล้ว`;
            }

            return `🕒 ${day}/${month}/${buddhistYear} • ${hours}:${minutes} น. (${elapsedText})`;
        }

        function getCaseStatusText(status) {
            const labels = {
                pending: '🚨 รอรับเรื่อง',
                acknowledged: '⏳ รับทราบแล้ว / กำลังดูแล',
                referred_hospital: '🏥 ส่งต่อโรงพยาบาลแล้ว',
                resolved: '✅ ปิดเคสแล้ว'
            };
            return labels[status] || status || '-';
        }

        function openCaseDetail(event, alert) {
            if (event.target.closest('a, button, input, select, textarea, label')) return;

            lastCaseDetailTrigger = event.currentTarget;
            const phone = alert.contact_phone || alert.vhv_phone || '';
            const redFlags = alert.red_flags ? ` • ${alert.red_flags}` : '';
            const referralNotes = alert.referral_notes ? ` • ${alert.referral_notes}` : '';

            setCaseDetailText('case-detail-title', `${alert.patient_name || 'ไม่ระบุชื่อ'}${alert.age ? ` (อายุ ${alert.age} ปี)` : ''}`);
            setCaseDetailText('detail-case-id', `#${alert.alert_id}`);
            setCaseDetailText('detail-case-status', getCaseStatusText(alert.alert_status));
            setCaseDetailText('detail-cid', alert.target_cid);
            setCaseDetailText('detail-created-at', formatCaseDateTime(alert.created_at));
            setCaseDetailText('detail-bp', `${alert.sbp || '-'}/${alert.dbp || '-'} mmHg`);
            setCaseDetailText('detail-dtx', `${alert.dtx || '-'} mg%`);
            setCaseDetailText('detail-hoscode', alert.hoscode ? `(รพ.สต. ${alert.hoscode})` : '');
            setCaseDetailText('detail-address', `บ้านเลขที่ ${alert.house_no || '-'} หมู่ ${alert.moo || '-'}`);
            setCaseDetailText('detail-contact', alert.vhv_name || 'อสม. ในพื้นที่');
            setCaseDetailText('detail-contact-phone', phone);
            setCaseDetailText('detail-crisis', `${alert.crisis_type || '-'}${redFlags}`);
            setCaseDetailText('detail-referral', `${alert.referral_destination || 'ยังไม่ระบุปลายทาง'}${referralNotes}`);
            setCaseDetailText('detail-jhcis', alert.is_jhcis_synced == 1
                ? `ซิงค์แล้ว${alert.jhcis_visitno ? ` • Visit No. ${alert.jhcis_visitno}` : ''}`
                : 'ยังไม่ซิงค์');

            const callLink = document.getElementById('detail-call-link');
            callLink.href = phone ? `tel:${phone}` : '#';
            callLink.style.display = phone ? 'inline-flex' : 'none';
            const inlineCallLink = document.getElementById('detail-call-link-inline');
            inlineCallLink.href = phone ? `tel:${phone}` : '#';
            document.getElementById('detail-phone-box').style.display = phone ? 'flex' : 'none';

            const mapLink = document.getElementById('detail-map-link');
            const hasCoordinates = alert.latitude && alert.longitude;
            mapLink.href = hasCoordinates
                ? `https://www.google.com/maps?q=${alert.latitude},${alert.longitude}`
                : 'https://www.google.com/maps/search/อำเภอตาลสุม';

            const modal = document.getElementById('case-detail-modal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            modal.querySelector('.case-detail-modal-box').focus?.();
        }

        function closeCaseDetail() {
            const modal = document.getElementById('case-detail-modal');
            if (modal.style.display !== 'flex') return;
            modal.style.display = 'none';
            document.body.style.overflow = '';
            lastCaseDetailTrigger?.focus();
        }

        function closeCaseDetailFromBackdrop(event) {
            if (event.target === event.currentTarget) closeCaseDetail();
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeCaseDetail();
        });

        function ackAlert(alertId) {
            const formData = new FormData();
            formData.append('action', 'acknowledge_alert');
            formData.append('alert_id', alertId);
            formData.append('staff_name', '<?= addslashes($_SESSION["admin_username"] ?? "เจ้าหน้าที่ รพ.สต.") ?>');

            fetch('../api/emergency_alert.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    location.reload();
                } else {
                    alert('ข้อผิดพลาด: ' + res.message);
                }
            });
        }

        function openReferModal(alert) {
            document.getElementById('refer-alert-id').value = alert.alert_id;
            document.getElementById('modal-target-patient').innerHTML = `
                <div style="font-weight: 800; font-size: 14.5px; color: var(--text-primary);">${alert.patient_name} (${alert.age ? alert.age + ' ปี' : ''})</div>
                <div style="color: #DC2626; font-weight: 800; margin-top: 2px;">ความดัน SBP: ${alert.sbp || '-'}/${alert.dbp || '-'} | DTX: ${alert.dtx || '-'} (${alert.crisis_type})</div>
                <div style="color: var(--text-secondary); font-size: 11.5px; margin-top: 2px;">บ้านเลขที่ ${alert.house_no || '-'} ม.${alert.moo || '-'} • อสม. ${alert.vhv_name || '-'}</div>
            `;
            document.getElementById('refer-notes').value = alert.referral_notes || `พบค่าสัญญาณชีพวิกฤต SBP ${alert.sbp}/${alert.dbp} DTX ${alert.dtx} ${alert.red_flags ? '(' + alert.red_flags + ')' : ''}`;
            document.getElementById('refer-modal').style.display = 'flex';
        }

        function closeReferModal() {
            document.getElementById('refer-modal').style.display = 'none';
        }

        function submitReferral(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit-refer');
            btn.disabled = true;
            btn.innerHTML = '⏳ กำลังบันทึกและส่งข้อมูลเข้า JHCIS...';

            const form = document.getElementById('form-refer-submit');
            const formData = new FormData(form);
            formData.append('action', 'update_referral_status');
            formData.append('status', 'referred_hospital');
            formData.append('staff_name', '<?= addslashes($_SESSION["admin_username"] ?? "เจ้าหน้าที่ รพ.สต.") ?>');

            fetch('../api/emergency_alert.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    alert('✅ ' + res.message);
                    location.reload();
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '🚀 ยืนยันการสั่งส่งต่อ';
                    alert('❌ ข้อผิดพลาด: ' + res.message);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '🚀 ยืนยันการสั่งส่งต่อ';
                alert('เชื่อมต่อล้มเหลว: ' + err);
            });
        }

        function openSlipModal(alert) {
            document.getElementById('slip-patient-name').innerText = `${alert.patient_name} (${alert.age ? alert.age + ' ปี' : ''})`;
            document.getElementById('slip-cid').innerText = alert.target_cid || '-';
            document.getElementById('slip-address').innerText = `บ้านเลขที่ ${alert.house_no || '-'} ม.${alert.moo || '-'} ต.ตาลสุม`;
            document.getElementById('slip-vitals').innerText = `🩺 BP: ${alert.sbp || '-'}/${alert.dbp || '-'} mmHg | 🩸 DTX: ${alert.dtx || '-'} mg%`;
            document.getElementById('slip-crisis').innerText = `⚠️ ${alert.crisis_type} ${alert.red_flags ? '(' + alert.red_flags + ')' : ''}`;
            document.getElementById('slip-dest').innerText = alert.referral_destination || 'โรงพยาบาลตาลสุม (10957)';

            const qrData = encodeURIComponent(`NCD_REFER:${alert.alert_id}|${alert.target_cid}|BP:${alert.sbp}/${alert.dbp}|DTX:${alert.dtx}`);
            document.getElementById('slip-qr-img').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${qrData}`;

            document.getElementById('slip-modal').style.display = 'flex';
        }

        function closeSlipModal() {
            document.getElementById('slip-modal').style.display = 'none';
        }

        // ----------------------------------------------------
        // Cross-Tab Navigator & Smart Focus Manager
        // ----------------------------------------------------
        const MY_TAB_NAME = "ncd_critical_referrals_tab";
        window.name = MY_TAB_NAME;

        function openOrFocusTab(url, targetTabName) {
            try {
                const bc = new BroadcastChannel('ncd_tab_channel');
                bc.postMessage({
                    action: 'focus_and_navigate',
                    target: targetTabName,
                    url: url,
                    timestamp: Date.now()
                });
            } catch(e) {}

            try {
                localStorage.setItem('ncd_focus_tab_signal', JSON.stringify({
                    target: targetTabName,
                    url: url,
                    timestamp: Date.now()
                }));
            } catch(e) {}

            const targetWin = window.open(url, targetTabName);
            if (targetWin) {
                try {
                    targetWin.focus();
                } catch(e) {}
            }
        }

        (function setupCrossTabFocusListener() {
            function handleTabFocus(data) {
                if (!data || data.target !== MY_TAB_NAME) return;
                try {
                    window.focus();
                } catch(e) {}

                if (data.url) {
                    const currentUrl = window.location.href;
                    const targetUrl = new URL(data.url, window.location.origin).href;
                    if (currentUrl !== targetUrl && data.url.indexOf('critical_referrals.php') !== -1) {
                        window.location.href = data.url;
                    }
                }

                const originalTitle = document.title.replace(/⚡ \[สลับมาแท็บนี้\] /g, '');
                document.title = "⚡ [สลับมาแท็บนี้] " + originalTitle;
                setTimeout(() => {
                    document.title = originalTitle;
                }, 2500);
            }

            try {
                const bc = new BroadcastChannel('ncd_tab_channel');
                bc.onmessage = (event) => {
                    if (event.data && event.data.action === 'focus_and_navigate') {
                        handleTabFocus(event.data);
                    }
                };
            } catch(e) {}

            window.addEventListener('storage', (e) => {
                if (e.key === 'ncd_focus_tab_signal' && e.newValue) {
                    try {
                        const payload = JSON.parse(e.newValue);
                        if (Date.now() - payload.timestamp < 3000) {
                            handleTabFocus(payload);
                        }
                    } catch(err) {}
                }
            });
        })();
    </script>
</body>
</html>
