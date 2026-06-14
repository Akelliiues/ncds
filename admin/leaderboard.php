<?php
// admin/leaderboard.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_username = $_SESSION['admin_username'] ?? '';

$hc_names = get_health_units();

$tambon_names = [];
try {
    $stmt = $pdo->query("SELECT sub_district_code, CONCAT('ตำบล', sub_district_name) FROM sub_districts ORDER BY sub_district_code ASC");
    $tambon_names = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (\Exception $e) {
    // Fallback
    $tambon_names = [
        '341801' => 'ตำบลตาลสุม',
        '341802' => 'ตำบลสำโรง',
        '341803' => 'ตำบลจิกเทิง',
        '341804' => 'ตำบลหนองกุง',
        '341805' => 'ตำบลนาคาย',
        '341806' => 'ตำบลคำหว้า'
    ];
}

$hospitalTambons = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT hoscode, sub_district_code FROM villages WHERE hoscode IS NOT NULL AND hoscode != ''");
    $hospitalTambons = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (\Exception $e) {
    // Fallback
}

// Fetch all VHVs with their points breakdown
$sql = "
    SELECT 
        u.vhv_id, 
        u.vhv_name, 
        u.vhv_moo, 
        u.vhid_code,
        u.hoscode, 
        u.is_hl_coach,
        u.approved,
        COALESCE(SUM(CASE WHEN r.screening_id IS NOT NULL THEN r.points_earned ELSE 0 END), 0) as screening_points,
        COALESCE(SUM(CASE WHEN r.followup_id IS NOT NULL THEN r.points_earned ELSE 0 END), 0) as dpac_points,
        COALESCE(SUM(r.points_earned), 0) as total_points
    FROM vhv_users u
    LEFT JOIN vhv_rewards r ON u.vhv_id = r.vhv_id AND r.approval_status = 'approved'
    GROUP BY u.vhv_id, u.vhv_name, u.vhv_moo, u.vhid_code, u.hoscode, u.is_hl_coach, u.approved
    ORDER BY total_points DESC, u.vhv_name ASC
";

$error = '';
try {
    $stmt = $pdo->query($sql);
    $vhv_list = $stmt->fetchAll();
} catch (\PDOException $e) {
    $vhv_list = [];
    $error = "เกิดข้อผิดพลาดในการโหลดข้อมูล: " . $e->getMessage();
}

// Calculate summary stats
$total_vhvs = count($vhv_list);
$active_vhvs = 0;
$total_points = 0;
$top_points = 0;
$top_vhv_name = '-';

foreach ($vhv_list as $vhv) {
    if ($vhv['approved']) {
        $active_vhvs++;
    }
    $points = (float) $vhv['total_points'];
    $total_points += $points;
    if ($points > $top_points) {
        $top_points = $points;
        $top_vhv_name = $vhv['vhv_name'];
    }
}
$avg_points = $total_vhvs > 0 ? round($total_points / $total_vhvs, 1) : 0;
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กระดานคะแนน อสม. ระดับอำเภอ - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-main);
        }

        /* Stats grid spacing */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card-premium {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--neumorph-flat);
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            overflow: hidden;
            transition: all var(--transition-speed);
        }

        .stat-card-premium:hover {
            transform: translateY(-2px);
        }

        .stat-card-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--color-primary);
        }

        .stat-card-premium.accent::before {
            background: var(--color-accent);
        }

        .stat-card-premium.success::before {
            background: var(--color-green);
        }

        .stat-card-premium.warning::before {
            background: var(--color-yellow);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background-color: var(--bg-darker);
            box-shadow: var(--neumorph-inset);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 13.5px;
            color: var(--text-secondary);
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
        }

        /* Filter Panel */
        .filter-panel {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--neumorph-flat);
            padding: 24px;
            margin-bottom: 24px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: flex-end;
        }

        /* Modal specific styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 44, 84, 0.45);
            backdrop-filter: blur(5px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--neumorph-flat);
            width: 100%;
            max-width: 750px;
            max-height: 85vh;
            overflow-y: auto;
            animation: modalSlideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }

        .modal-header-premium {
            padding: 24px 30px;
            border-bottom: 2px solid var(--bg-darker);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-main);
            border-radius: 20px 20px 0 0;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-close-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: color var(--transition-speed);
        }

        .modal-close-btn:hover {
            color: var(--color-red);
        }

        /* Rank badges */
        .rank-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14.5px;
            box-shadow: var(--neumorph-inset);
        }

        .rank-gold {
            background-color: #fef3c7;
            color: #d97706;
            border: 2px solid #fbbf24;
            box-shadow: 0 4px 8px rgba(251, 191, 36, 0.25);
        }

        .rank-silver {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 2px solid #9ca3af;
            box-shadow: 0 4px 8px rgba(156, 163, 175, 0.2);
        }

        .rank-bronze {
            background-color: #ffedd5;
            color: #c2410c;
            border: 2px solid #f97316;
            box-shadow: 0 4px 8px rgba(249, 115, 22, 0.2);
        }

        .rank-normal {
            background-color: var(--bg-darker);
            color: var(--text-secondary);
        }

        /* Hoverable row action */
        .btn-view-logs {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--color-primary);
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 800;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all var(--transition-speed);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view-logs:hover {
            border-color: var(--color-primary);
            transform: translateY(-1px);
            color: var(--color-accent);
        }

        .btn-view-logs:active {
            box-shadow: var(--neumorph-inset);
            transform: scale(0.97);
        }

        /* Allow word wrapping in table headers, but prevent wrapping in table body cells for clean Excel-like records */
        table.admin-table th {
            white-space: normal !important;
        }

        table.admin-table td {
            white-space: nowrap !important;
        }

        .btn-view-logs-icon {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--color-primary);
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all var(--transition-speed);
            font-size: 15px;
        }

        .btn-view-logs-icon:hover {
            border-color: var(--color-primary);
            color: var(--color-accent);
            transform: translateY(-1px);
        }

        .btn-view-logs-icon:active {
            box-shadow: var(--neumorph-inset);
            transform: scale(0.95);
        }

        /* Print formatting */
        @media print {
            .no-print,
            .admin-navbar,
            .stats-grid,
            .filter-panel,
            .btn-view-logs-icon {
                display: none !important;
            }

            body, .admin-body {
                background: white !important;
                color: black !important;
                font-family: 'Sarabun', 'Prompt', sans-serif !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .print-only-header {
                display: block !important;
            }

            .card-dark {
                box-shadow: none !important;
                border: none !important;
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Excel-like grid table style */
            .admin-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 10px !important;
                border: 1px solid #000000 !important;
            }

            .admin-table th,
            .admin-table td {
                border: 1px solid #000000 !important;
                padding: 6px 8px !important;
                font-size: 11px !important;
                color: black !important;
                background: transparent !important;
                text-shadow: none !important;
                box-shadow: none !important;
            }

            .admin-table th {
                background-color: #f2f2f2 !important;
                font-weight: bold !important;
                text-align: center !important;
                white-space: normal !important;
            }

            .admin-table td {
                white-space: nowrap !important;
            }

            .rank-badge {
                border: none !important;
                background: transparent !important;
                box-shadow: none !important;
                width: auto !important;
                height: auto !important;
                display: inline !important;
                font-weight: bold !important;
                color: black !important;
            }

            .admin-table td span {
                background: transparent !important;
                border: none !important;
                color: black !important;
                padding: 0 !important;
                font-size: 10px !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">

        <!-- Header for Screen Mode (hidden in print) -->
        <div class="no-print"
            style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
            <div>
                <h2 style="color: var(--color-accent); margin-top: 0; margin-bottom: 8px;">🏆 กระดานคะแนน อสม. ทั้งอำเภอ
                </h2>
                <p style="color: var(--text-secondary); margin: 0;">
                    ติดตาม จัดลำดับ และวิเคราะห์ผลการสะสมแต้มของ อสม. ทุกตำบลในอำเภอตาลสุม
                </p>
            </div>
            <div>
                <button onclick="window.print()" class="btn-giant btn-giant-secondary"
                    style="margin: 0; padding: 10px 20px; font-size: 14.5px; display: inline-flex; align-items: center; gap: 8px;">
                    🖨️ พิมพ์รายงานกระดาน
                </button>
            </div>
        </div>

        <!-- Header for Print Mode (hidden in screen) -->
        <div class="print-only-header" style="display: none;">
            <h2 style="text-align: center; margin: 0 0 6px 0; font-size: 20px; color: black; font-weight: bold;">รายงานทำเนียบผลงานและแต้มสะสม อสม. อำเภอตาลสุม</h2>
            <p style="text-align: center; margin: 0 0 24px 0; font-size: 12px; color: #444;">ข้อมูล ณ วันที่ <?= date('d/m/Y H:i') ?> น. • เรียงลำดับจากแต้มรวมสูงสุด</p>
        </div>

        <?php if (!empty($error)): ?>
            <div
                style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px; font-weight: bold;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Overview Widgets -->
        <div class="stats-grid">
            <div class="stat-card-premium">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <span class="stat-label">อสม. ลงทะเบียนทั้งหมด</span>
                    <span class="stat-value"><?= number_format($total_vhvs) ?> คน</span>
                </div>
            </div>
            <div class="stat-card-premium success">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <span class="stat-label">อสม. อนุมัติสิทธิ์แล้ว</span>
                    <span class="stat-value"><?= number_format($active_vhvs) ?> คน</span>
                </div>
            </div>
            <div class="stat-card-premium warning">
                <div class="stat-icon">🪙</div>
                <div class="stat-info">
                    <span class="stat-label">แต้มสะสมรวมทั้งอำเภอ</span>
                    <span class="stat-value"><?= number_format($total_points) ?> แต้ม</span>
                </div>
            </div>
            <div class="stat-card-premium accent">
                <div class="stat-icon">🌟</div>
                <div class="stat-info">
                    <span class="stat-label">ค่าเฉลี่ยแต้มต่อ อสม.</span>
                    <span class="stat-value"><?= $avg_points ?> แต้ม</span>
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel no-print">
            <h3
                style="color: var(--color-primary); margin-top: 0; margin-bottom: 18px; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                🔍 ตัวกรองและการค้นหาข้อมูลแบบละเอียด
            </h3>
            <div class="filter-grid">
                <!-- Search Input -->
                <div style="flex-grow: 1;">
                    <label class="modal-form-label" style="font-size: 13.5px;" for="search-input">ค้นหา อสม. (ชื่อ-สกุล
                        หรือ รหัส)</label>
                    <input type="text" id="search-input" class="form-input-text" placeholder="ระบุคำค้นหา..."
                        style="box-shadow: var(--neumorph-inset); text-align: left; height: 40px; margin-bottom: 0;">
                </div>

                <!-- Filter Tambon -->
                <div>
                    <label class="modal-form-label" style="font-size: 13.5px;" for="filter-tambon">ตำบล</label>
                    <select id="filter-tambon" class="form-select"
                        style="box-shadow: var(--neumorph-inset); height: 40px;"
                        onchange="updateHospitalFilterOptions()">
                        <option value="">-- ทุกตำบล --</option>
                        <?php foreach ($tambon_names as $code => $name): ?>
                            <option value="<?= $code ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Hoscode -->
                <div>
                    <label class="modal-form-label" style="font-size: 13.5px;"
                        for="filter-hoscode">หน่วยบริการที่สังกัด</label>
                    <select id="filter-hoscode" class="form-select"
                        style="box-shadow: var(--neumorph-inset); height: 40px;">
                        <option value="">-- ทุกหน่วยบริการ --</option>
                        <?php foreach ($hc_names as $code => $name): ?>
                            <option value="<?= $code ?>" <?= ($admin_hoscode === $code) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Coach Status -->
                <div>
                    <label class="modal-form-label" style="font-size: 13.5px;" for="filter-coach">สถานะ HL-Coach</label>
                    <select id="filter-coach" class="form-select"
                        style="box-shadow: var(--neumorph-inset); height: 40px;">
                        <option value="">-- ทั้งหมด --</option>
                        <option value="coach">เฉพาะ HL-Coach</option>
                        <option value="member">อสม. สมาชิกทั่วไป</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <label class="modal-form-label" style="font-size: 13.5px;" for="sort-by">เรียงลำดับตาม</label>
                    <select id="sort-by" class="form-select" style="box-shadow: var(--neumorph-inset); height: 40px;">
                        <option value="total_points">คะแนนรวมสูงสุด</option>
                        <option value="screening_points">คะแนนคัดกรองสูงสุด</option>
                        <option value="dpac_points">คะแนนติดตาม DPAC สูงสุด</option>
                        <option value="vhv_name">เรียงตามชื่อ ก-ฮ</option>
                    </select>
                </div>

                <!-- Reset Button -->
                <div>
                    <button type="button" onclick="resetFilters()" class="btn-giant btn-giant-secondary"
                        style="height: 40px; line-height: 40px; margin: 0; padding: 0 16px; font-size: 13.5px; width: 100%;">
                        รีเซ็ตตัวกรอง
                    </button>
                </div>
            </div>
        </div>

        <!-- Leaderboard Table Container -->
        <div class="card-dark" style="padding: 24px;">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 8px;">
                <h3 style="color: var(--color-accent); margin: 0; font-size: 18px; font-weight: 800;">
                    📊 ตารางวิเคราะห์แต้มสะสม อสม. (<span id="results-count"><?= $total_vhvs ?></span> รายการ)
                </h3>
                <span id="filtered-label"
                    style="font-size: 13px; color: var(--text-secondary); font-weight: bold; background: rgba(13, 44, 84, 0.05); padding: 4px 10px; border-radius: 8px; box-shadow: var(--neumorph-inset); display: none;">
                    กรองข้อมูลอยู่
                </span>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 70px; text-align: center;">อันดับ</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th style="text-align: center;">หมู่</th>
                            <th>ตำบล</th>
                            <th>หน่วยบริการสังกัด</th>
                            <th style="text-align: right; width: 120px;">แต้มคัดกรอง DM/HT</th>
                            <th style="text-align: right; width: 120px;">แต้มติดตาม DPAC</th>
                            <th style="text-align: right; width: 110px; font-weight: 800; color: var(--color-accent);">
                                แต้มสะสมรวม</th>
                            <th style="text-align: center; width: 120px;">สถานะพิเศษ</th>
                            <th style="width: 80px; text-align: center;" class="no-print">ประวัติแต้ม</th>
                        </tr>
                    </thead>
                    <tbody id="leaderboard-tbody">
                        <!-- Dynamic Row Injection -->
                    </tbody>
                </table>
            </div>

            <div id="no-data-msg"
                style="display: none; text-align: center; color: var(--text-secondary); padding: 40px; font-weight: bold;">
                ❌ ไม่พบข้อมูล อสม. ตามเงื่อนไขการกรองข้างต้น
            </div>
        </div>

    </div>

    <!-- VHV Details Drilldown Modal -->
    <div id="logs-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header-premium">
                <div>
                    <h3 id="modal-vhv-name"
                        style="margin: 0; color: var(--color-primary); font-size: 19px; font-weight: 800;">
                        ประวัติสะสมแต้ม</h3>
                    <p id="modal-vhv-info"
                        style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-secondary); font-weight: bold;">
                    </p>
                </div>
                <button onclick="closeLogsModal()" class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="stats-grid" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div
                        style="background-color: var(--bg-darker); padding: 16px; border-radius: 12px; text-align: center; box-shadow: var(--neumorph-inset);">
                        <span
                            style="font-size: 12.5px; color: var(--text-secondary); font-weight: bold; display: block; margin-bottom: 4px;">แต้มรวมงานคัดกรอง</span>
                        <div id="modal-stat-screening"
                            style="font-size: 24px; font-weight: 800; color: var(--color-primary);">0.00</div>
                    </div>
                    <div
                        style="background-color: var(--bg-darker); padding: 16px; border-radius: 12px; text-align: center; box-shadow: var(--neumorph-inset);">
                        <span
                            style="font-size: 12.5px; color: var(--text-secondary); font-weight: bold; display: block; margin-bottom: 4px;">แต้มรวมงานติดตาม
                            DPAC</span>
                        <div id="modal-stat-dpac"
                            style="font-size: 24px; font-weight: 800; color: var(--color-primary);">0.00</div>
                    </div>
                </div>

                <h4
                    style="margin-top: 0; margin-bottom: 12px; color: var(--text-primary); font-size: 15px; font-weight: 800;">
                    รายการการบันทึกงานที่ได้รับการอนุมัติ (Audit Log)</h4>

                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="admin-table" style="font-size: 13.5px;">
                        <thead>
                            <tr>
                                <th>วัน-เวลาบันทึก</th>
                                <th>กิจกรรมสาธารณสุข</th>
                                <th>ผู้รับบริการ</th>
                                <th>เลขบัตรประชาชน (CID)</th>
                                <th style="text-align: right; width: 90px;">แต้มได้รับ</th>
                            </tr>
                        </thead>
                        <tbody id="modal-logs-tbody">
                            <!-- Dynamic logs injection -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                style="padding: 20px 30px; background-color: var(--bg-main); border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; border-radius: 0 0 20px 20px;">
                <button onclick="closeLogsModal()" class="btn-giant btn-giant-secondary"
                    style="margin: 0; padding: 8px 20px; font-size: 14px; width: auto; background-color: var(--text-muted); color: white;">
                    ปิดหน้าต่าง
                </button>
            </div>
        </div>
    </div>

    <script>
        // Load data from PHP
        const allVhvs = <?= json_encode($vhv_list, JSON_UNESCAPED_UNICODE) ?>;
        const hcNames = <?= json_encode($hc_names, JSON_UNESCAPED_UNICODE) ?>;
        const tambonNames = <?= json_encode($tambon_names, JSON_UNESCAPED_UNICODE) ?>;
        const loggedInHoscode = <?= json_encode($admin_hoscode, JSON_UNESCAPED_UNICODE) ?>;

        // Positive titles mapped to rankings (Unique top 5, tiered classes for 6-50)
        function getRankTitle(rank) {
            if (rank <= 0 || rank > 50) return '';
            
            // Top 5 are unique supreme titles
            if (rank === 1) return '🏆 สุดยอดขุนพลสาธารณสุขตาลสุม';
            if (rank === 2) return '🏆 ยอดอัศวินสุขภาพชุมชน';
            if (rank === 3) return '🏆 ดาวรุ่งแห่งความห่วงใย';
            if (rank === 4) return '✨ ผู้พิทักษ์หัวใจไร้โรค';
            if (rank === 5) return '🌟 ขวัญใจสุขภาพดีถ้วนหน้า';

            // Base titles for group tiers (ranks 6-50 in groups of 5)
            const baseTitles = {
                1: '💪 ยอดนักปราบเบาหวานและความดัน',
                2: '🛡️ ผู้ปกป้องสุขภาวะตาลสุม',
                3: '❤️ เสาหลักสุขภาพดีชุมชน',
                4: '🌱 ผู้หว่านเมล็ดพันธุ์สุขภาพ',
                5: '🤝 พลังขับเคลื่อนตำบลสุขภาพดี',
                6: '🎉 ผู้จุดประกายรักตนเอง',
                7: '🍀 ทูตสุขภาพสร้างพลังบวก',
                8: '💡 ปราชญ์สุขภาพคู่บ้านคู่เมือง',
                9: '☀️ แสนสว่างนำทางชีวิตชีวา'
            };

            // Thai traditional civil service / military tiers
            const suffixes = {
                0: 'ชั้นเอก',
                1: 'ชั้นโท',
                2: 'ชั้นตรี',
                3: 'ชั้นจัตวา',
                4: 'ชั้นเบญจ'
            };

            const groupIndex = Math.floor((rank - 6) / 5) + 1;
            const suffixIndex = (rank - 6) % 5;

            if (baseTitles[groupIndex] && suffixes[suffixIndex]) {
                return baseTitles[groupIndex] + ' ' + suffixes[suffixIndex];
            }

            return '';
        }

        // Set up event listeners for filters
        document.getElementById('search-input').addEventListener('input', renderLeaderboard);
        document.getElementById('filter-tambon').addEventListener('change', renderLeaderboard);
        document.getElementById('filter-hoscode').addEventListener('change', renderLeaderboard);
        document.getElementById('filter-coach').addEventListener('change', renderLeaderboard);
        document.getElementById('sort-by').addEventListener('change', renderLeaderboard);

        // Map hospital to tambon prefix
        const hospitalTambons = <?= json_encode($hospitalTambons) ?>;

        // When Tambon changes, filter hospital options
        function updateHospitalFilterOptions() {
            const selectedTambon = document.getElementById('filter-tambon').value;
            const hoscodeSelect = document.getElementById('filter-hoscode');
            const currentSelected = hoscodeSelect.value;

            // Clear current options except "All"
            hoscodeSelect.innerHTML = '<option value="">-- ทุกหน่วยบริการ --</option>';

            for (const [code, name] of Object.entries(hcNames)) {
                const tambonOfHospital = hospitalTambons[code];
                if (!selectedTambon || tambonOfHospital === selectedTambon) {
                    const opt = document.createElement('option');
                    opt.value = code;
                    opt.textContent = name;
                    if (code === currentSelected) {
                        opt.selected = true;
                    }
                    hoscodeSelect.appendChild(opt);
                }
            }
        }

        function resetFilters() {
            document.getElementById('search-input').value = '';
            document.getElementById('filter-tambon').value = '';
            document.getElementById('filter-coach').value = '';
            document.getElementById('sort-by').value = 'total_points';

            // Restore original hospital options based on logged-in constraints
            updateHospitalFilterOptions();
            document.getElementById('filter-hoscode').value = loggedInHoscode || '';

            renderLeaderboard();
        }

        // Main client-side sorting and filtering engine
        function renderLeaderboard() {
            const query = document.getElementById('search-input').value.trim().toLowerCase();
            const selectedTambon = document.getElementById('filter-tambon').value;
            const selectedHoscode = document.getElementById('filter-hoscode').value;
            const selectedCoach = document.getElementById('filter-coach').value;
            const sortBy = document.getElementById('sort-by').value;

            // 1. Filter
            let filtered = allVhvs.filter(vhv => {
                // Search term
                if (query) {
                    const nameMatch = vhv.vhv_name.toLowerCase().includes(query);
                    const idMatch = vhv.vhv_id.toLowerCase().includes(query);
                    if (!nameMatch && !idMatch) return false;
                }

                // Tambon
                if (selectedTambon) {
                    const vhvTambon = vhv.vhid_code ? vhv.vhid_code.substring(0, 6) : '';
                    if (vhvTambon !== selectedTambon) return false;
                }

                // Hoscode
                if (selectedHoscode) {
                    if (vhv.hoscode !== selectedHoscode) return false;
                }

                // Coach Status
                if (selectedCoach === 'coach' && !vhv.is_hl_coach) return false;
                if (selectedCoach === 'member' && vhv.is_hl_coach) return false;

                return true;
            });

            // Show active filters badge
            const hasFilters = query || selectedTambon || selectedHoscode || selectedCoach;
            document.getElementById('filtered-label').style.display = hasFilters ? 'inline-block' : 'none';

            // 2. Sort
            filtered.sort((a, b) => {
                if (sortBy === 'vhv_name') {
                    return a.vhv_name.localeCompare(b.vhv_name, 'th');
                }

                let valA = parseFloat(a[sortBy]) || 0;
                let valB = parseFloat(b[sortBy]) || 0;

                if (valA !== valB) {
                    return valB - valA; // Descending points
                }
                return a.vhv_name.localeCompare(b.vhv_name, 'th'); // Alphabetical tie-breaker
            });

            // 3. Render HTML
            const tbody = document.getElementById('leaderboard-tbody');
            tbody.innerHTML = '';

            document.getElementById('results-count').textContent = filtered.length;

            if (filtered.length === 0) {
                document.getElementById('no-data-msg').style.display = 'block';
                return;
            }
            document.getElementById('no-data-msg').style.display = 'none';

            filtered.forEach((vhv, index) => {
                const rankNum = index + 1;
                let rankHtml = '';

                if (rankNum === 1) {
                    rankHtml = `<span class="rank-badge rank-gold" title="อันดับ 1">🥇</span>`;
                } else if (rankNum === 2) {
                    rankHtml = `<span class="rank-badge rank-silver" title="อันดับ 2">🥈</span>`;
                } else if (rankNum === 3) {
                    rankHtml = `<span class="rank-badge rank-bronze" title="อันดับ 3">🥉</span>`;
                } else {
                    rankHtml = `<span class="rank-badge rank-normal">${rankNum}</span>`;
                }

                const hosName = hcNames[vhv.hoscode] || vhv.hoscode || '-';
                const vhvTambonCode = vhv.vhid_code ? vhv.vhid_code.substring(0, 6) : '';
                const tambonName = (tambonNames[vhvTambonCode] || 'ไม่ระบุ').replace(/^ตำบล/, '');

                let badges = '';
                if (vhv.is_hl_coach) {
                    badges += `<span style="color: #fbbf24; font-weight: bold; background: rgba(251,191,36,0.1); padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-right: 4px; border: 1px solid rgba(251,191,36,0.2);">✨ HL-Coach</span>`;
                }

                const rankTitle = getRankTitle(rankNum);
                if (rankTitle) {
                    badges += `<span style="color: var(--color-accent); font-weight: bold; background: rgba(13, 44, 84, 0.05); padding: 2px 6px; border-radius: 4px; font-size: 11px; box-shadow: var(--neumorph-inset);">${rankTitle}</span>`;
                }

                const totalPtsFormatted = parseFloat(vhv.total_points).toFixed(2).replace(/\.00$/, '');
                const screeningPtsFormatted = parseFloat(vhv.screening_points).toFixed(2).replace(/\.00$/, '');
                const dpacPtsFormatted = parseFloat(vhv.dpac_points).toFixed(2).replace(/\.00$/, '');

                const row = document.createElement('tr');
                if (loggedInHoscode && vhv.hoscode === loggedInHoscode) {
                    row.style.backgroundColor = 'rgba(13, 44, 84, 0.02)'; // Highlight local hospital VHVs slightly
                }

                row.innerHTML = `
                    <td style="text-align: center;">${rankHtml}</td>
                    <td style="font-weight: 800; color: var(--text-primary);">${escapeHtml(vhv.vhv_name)}</td>
                    <td style="text-align: center; font-weight: bold;">${parseInt(vhv.vhv_moo)}</td>
                    <td style="white-space: nowrap;">${escapeHtml(tambonName)}</td>
                    <td style="font-size: 13.5px; color: var(--text-secondary); white-space: nowrap;">${escapeHtml(hosName)}</td>
                    <td style="text-align: right; font-weight: 600;">${screeningPtsFormatted}</td>
                    <td style="text-align: right; font-weight: 600;">${dpacPtsFormatted}</td>
                    <td style="text-align: right; font-weight: 800; color: var(--color-accent); font-size: 15px;">${totalPtsFormatted}</td>
                    <td style="text-align: center;">${badges || '-'}</td>
                    <td style="text-align: center;" class="no-print">
                        <button onclick="openLogsModal('${vhv.vhv_id}')" class="btn-view-logs-icon" title="ดูประวัติแต้ม">
                            🔍
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // HTML escaping helper
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Modal Operations
        function openLogsModal(vhvId) {
            const modal = document.getElementById('logs-modal');
            const tbody = document.getElementById('modal-logs-tbody');

            // Clean modal state
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">⌛ กำลังโหลดประวัติ...</td></tr>';
            document.getElementById('modal-vhv-name').textContent = 'กำลังดึงข้อมูล...';
            document.getElementById('modal-vhv-info').textContent = '';
            document.getElementById('modal-stat-screening').textContent = '0.00';
            document.getElementById('modal-stat-dpac').textContent = '0.00';

            modal.style.display = 'flex';

            // Fetch AJAX data
            fetch(`../api/get_vhv_rewards.php?vhv_id=${encodeURIComponent(vhvId)}`)
                .then(response => {
                    return response.text().then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (!response.ok) {
                                throw new Error(data.message || `เซิร์ฟเวอร์ส่งคืนรหัส ${response.status}`);
                            }
                            return data;
                        } catch (e) {
                            if (!response.ok) {
                                try {
                                    const data = JSON.parse(text.substring(text.indexOf('{')));
                                    throw new Error(data.message || `เซิร์ฟเวอร์ขัดข้อง (HTTP ${response.status})`);
                                } catch (inner) {
                                    throw new Error(`เซิร์ฟเวอร์ขัดข้อง (HTTP ${response.status})`);
                                }
                            }
                            const jsonStart = text.indexOf('{');
                            if (jsonStart !== -1) {
                                try {
                                    return JSON.parse(text.substring(jsonStart));
                                } catch (innerE) { }
                            }
                            throw new Error('รูปแบบข้อมูลจากเซิร์ฟเวอร์ไม่ถูกต้อง');
                        }
                    });
                })
                .then(data => {
                    if (data.status === 'success') {
                        // Set headers
                        document.getElementById('modal-vhv-name').textContent = `🏆 ประวัติคะแนน: ${data.vhv.vhv_name}`;
                        document.getElementById('modal-vhv-info').textContent = `หมู่ที่ ${data.vhv.vhv_moo} | ${data.vhv.hospital_name} | รหัส อสม. ${vhvId}`;

                        let screeningTotal = 0;
                        let dpacTotal = 0;

                        tbody.innerHTML = '';
                        if (data.rewards.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-secondary);">ไม่พบประวัติคะแนนที่ได้รับอนุมัติ</td></tr>';
                        } else {
                            data.rewards.forEach(log => {
                                const pts = parseFloat(log.points_earned);
                                if (log.activity_type === 'screening') {
                                    screeningTotal += pts;
                                } else {
                                    dpacTotal += pts;
                                }

                                const dateStr = new Date(log.created_at).toLocaleDateString('th-TH', {
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });

                                const activityLabel = log.activity_type === 'screening'
                                    ? '<span style="color: var(--color-green); font-weight: bold;">🏥 คัดกรอง DM/HT</span>'
                                    : '<span style="color: var(--color-accent); font-weight: bold;">❤️ ติดตาม DPAC</span>';

                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>${dateStr}</td>
                                    <td>${activityLabel}</td>
                                    <td style="font-weight: bold; color: var(--text-primary);">${escapeHtml(log.first_name)} ${escapeHtml(log.last_name)}</td>
                                    <td style="font-family: monospace;">${escapeHtml(log.cid)}</td>
                                    <td style="text-align: right; font-weight: bold; color: var(--color-accent);">${pts.toFixed(2).replace(/\.00$/, '')}</td>
                                `;
                                tbody.appendChild(row);
                            });
                        }

                        document.getElementById('modal-stat-screening').textContent = screeningTotal.toFixed(2).replace(/\.00$/, '');
                        document.getElementById('modal-stat-dpac').textContent = dpacTotal.toFixed(2).replace(/\.00$/, '');
                    } else {
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--color-red);">⚠️ ข้อผิดพลาด: ${escapeHtml(data.message)}</td></tr>`;
                    }
                })
                .catch(err => {
                    tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--color-red);">⚠️ ${escapeHtml(err.message)}</td></tr>`;
                });
        }

        function closeLogsModal() {
            document.getElementById('logs-modal').style.display = 'none';
        }

        // Close modal when clicking outside contents
        window.onclick = function (event) {
            const modal = document.getElementById('logs-modal');
            if (event.target === modal) {
                closeLogsModal();
            }
        };

        // Initialize display
        updateHospitalFilterOptions();
        renderLeaderboard();
    </script>
</body>

</html>