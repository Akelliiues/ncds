<?php
// admin/vhv_tasks.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

$jsData = [];
$subsList = [];
try {
    $subsList = $pdo->query("SELECT * FROM sub_districts ORDER BY sub_district_code ASC")->fetchAll();
    foreach ($subsList as $sub) {
        $subCode = $sub['sub_district_code'];
        $subName = $sub['sub_district_name'];

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT hoscode) FROM villages WHERE sub_district_code = ? AND hoscode IS NOT NULL AND hoscode != ''");
        $stmt->execute([$subCode]);
        $distinctHoscodes = $stmt->fetchColumn();

        $hasSubUnits = ($distinctHoscodes > 1);

        if ($hasSubUnits) {
            $jsData[$subCode] = [
                'name' => $subName,
                'hasSubUnits' => true,
                'subUnits' => []
            ];

            $stmt = $pdo->prepare("SELECT DISTINCT v.hoscode, h.hosname FROM villages v JOIN health_units h ON v.hoscode = h.hoscode WHERE v.sub_district_code = ?");
            $stmt->execute([$subCode]);
            $subUnits = $stmt->fetchAll();

            foreach ($subUnits as $su) {
                $hc = $su['hoscode'];
                $hcName = $su['hosname'];

                $vStmt = $pdo->prepare("SELECT moo, village_name FROM villages WHERE sub_district_code = ? AND hoscode = ? ORDER BY moo ASC");
                $vStmt->execute([$subCode, $hc]);
                $vills = $vStmt->fetchAll();

                $villList = [];
                foreach ($vills as $v) {
                    $villList[] = [
                        'moo' => intval($v['moo']),
                        'name' => $v['village_name']
                    ];
                }

                $jsData[$subCode]['subUnits'][$hc] = [
                    'name' => $hcName,
                    'villages' => $villList
                ];
            }
        } else {
            $stmt = $pdo->prepare("SELECT DISTINCT hoscode FROM villages WHERE sub_district_code = ? LIMIT 1");
            $stmt->execute([$subCode]);
            $hc = $stmt->fetchColumn();

            $vStmt = $pdo->prepare("SELECT moo, village_name FROM villages WHERE sub_district_code = ? ORDER BY moo ASC");
            $vStmt->execute([$subCode]);
            $vills = $vStmt->fetchAll();

            $villList = [];
            foreach ($vills as $v) {
                $villList[] = [
                    'moo' => intval($v['moo']),
                    'name' => $v['village_name']
                ];
            }

            $jsData[$subCode] = [
                'name' => $subName,
                'hasSubUnits' => false,
                'hoscode' => $hc ?: '',
                'villages' => $villList
            ];
        }
    }
} catch (\Exception $e) {
    // Fail silently
}

// Filter for sub-district admins
if ($admin_hoscode !== null) {
    $filteredJsData = [];
    foreach ($jsData as $subCode => $subInfo) {
        if ($subInfo['hasSubUnits']) {
            if (isset($subInfo['subUnits'][$admin_hoscode])) {
                $filteredJsData[$subCode] = [
                    'name' => $subInfo['name'],
                    'hasSubUnits' => true,
                    'subUnits' => [
                        $admin_hoscode => $subInfo['subUnits'][$admin_hoscode]
                    ]
                ];
            }
        } else {
            if ($subInfo['hoscode'] === $admin_hoscode) {
                $filteredJsData[$subCode] = $subInfo;
            }
        }
    }
    $jsData = $filteredJsData;

    $filteredSubsList = [];
    foreach ($subsList as $sub) {
        if (isset($jsData[$sub['sub_district_code']])) {
            $filteredSubsList[] = $sub;
        }
    }
    $subsList = $filteredSubsList;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เช็คงาน อสม. - NCDs Prevention</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-main);
        }

        .grid-container {
            display: grid;
            grid-template-columns: 0.6fr 1.4fr;
            gap: 24px;
            margin-top: 20px;
        }

        @media (max-width: 992px) {
            .grid-container {
                grid-template-columns: 1fr;
            }

            #task-details-wrapper {
                height: auto !important;
            }
        }

        .filter-card {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--neumorph-flat);
            margin-bottom: 20px;
        }

        .list-card {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--neumorph-inset);
            height: 680px;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            min-width: 0;
            /* ป้องกันการดันขอบกล่องขยายเกินอัตราส่วน */
        }

        .list-body {
            flex: 1;
            overflow-y: auto;
            margin-top: 15px;
            padding-right: 5px;
        }

        .item-row {
            background-color: var(--bg-main);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 12px;
            box-shadow: var(--neumorph-flat);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all var(--transition-speed);
            border: 1px solid transparent;
            cursor: pointer;
        }

        .item-row:hover {
            transform: translateY(-1.5px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
            border-color: rgba(59, 130, 246, 0.2);
        }

        .item-row.active {
            background-color: #0d2c54 !important;
            color: white !important;
            box-shadow: inset 3px 3px 6px rgba(0, 0, 0, 0.4), inset -3px -3px 6px rgba(255, 255, 255, 0.1) !important;
        }

        .item-row.active h4 {
            color: white !important;
        }

        .item-row.active p,
        .item-row.active strong {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .item-info h4 {
            margin: 0 0 4px 0;
            color: var(--text-primary);
            font-size: 15.5px;
            font-weight: 700;
        }

        .item-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .task-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            color: var(--text-primary);
            table-layout: fixed;
            /* บังคับใช้ขนาดกว้างคงที่ตามกำหนด */
        }

        .task-table th,
        .task-table td {
            padding: 10px 12px;
            text-align: left;
            vertical-align: middle;
            font-size: 13px;
            white-space: nowrap;
            /* ป้องกันการตัดคำ */
            overflow: hidden;
            text-overflow: ellipsis;
            /* ตัดคำที่ล้นด้วย ... กรณีบีบแคบ */
        }

        .task-table thead th {
            background: linear-gradient(135deg, rgba(13, 44, 84, 0.06), rgba(59, 130, 246, 0.04));
            color: var(--text-secondary);
            font-weight: 850;
            font-size: 12.5px;
            border-bottom: 2px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .task-table tbody tr {
            transition: background 0.15s ease;
        }

        .task-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.04);
        }

        .task-table tbody td {
            border-bottom: 1px solid var(--border-color);
        }

        .task-table tbody tr:last-child td {
            border-bottom: none;
        }

        .btn-cancel-assign {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--color-yellow, #f59e0b);
            border: 1px solid rgba(245, 158, 11, 0.3);
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            font-weight: bold;
        }

        .btn-cancel-assign:hover {
            background-color: var(--color-yellow, #f59e0b);
            color: white;
            transform: translateY(-1px);
        }

        .btn-cancel-all {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--color-red, #ef4444);
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-cancel-all:hover {
            background-color: var(--color-red, #ef4444);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.15);
        }

        .btn-view-detail {
            background-color: rgba(59, 130, 246, 0.1);
            color: #2563eb;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 4px 9px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11.5px;
            font-weight: bold;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .btn-view-detail:hover {
            background-color: #2563eb;
            color: white;
            transform: translateY(-1px);
        }

        .badge-rescreen {
            background-color: rgba(99, 102, 241, 0.12);
            color: #6366f1;
            border: 1px solid rgba(99, 102, 241, 0.25);
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 4px;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(13, 44, 84, 0.45);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 24px;
            width: 90%;
            max-width: 580px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border-color);
        }

        .metric-card {
            background: var(--bg-main);
            border-radius: 12px;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            box-shadow: var(--neumorph-inset);
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: var(--text-secondary);
            font-size: 14px;
        }
    </style>
</head>

<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="color: var(--color-accent); margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
            🕵️ เช็คงาน อสม. รายบุคคล (VHV Workload Inspector)
        </h2>
        <p style="color: var(--text-secondary); font-size: 14px; margin-top: -10px; margin-bottom: 30px;">
            ตรวจสอบปริมาณงานที่ อสม. แต่ละคนได้รับมอบหมาย โดยแบ่งหมวดหมู่งานคัดกรอง NCD และติดตาม DPAC พร้อมระบบดึงงานคืนรายบุคคล/ทั้งหมด
        </p>

        <!-- Filters -->
        <div class="filter-card">
            <h4 style="margin-top: 0; margin-bottom: 16px; color: var(--text-primary);">เลือกเขตรับผิดชอบ</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <label class="form-label">ตำบล</label>
                    <select id="tambon" class="form-select" onchange="onTambonChange()">
                        <option value="">-- เลือกตำบล --</option>
                        <?php foreach ($subsList as $sub): ?>
                            <option value="<?= htmlspecialchars($sub['sub_district_code']) ?>"><?= htmlspecialchars($sub['sub_district_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="hoscode_container" style="display: none;">
                    <label class="form-label">หน่วยบริการ (รพ.สต.)</label>
                    <select id="hoscode" class="form-select" onchange="onHoscodeChange()">
                        <option value="">-- เลือกหน่วยบริการ --</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">หมู่บ้าน</label>
                    <select id="moo" class="form-select" onchange="onMooChange()">
                        <option value="">-- เลือกพื้นที่ก่อน --</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="grid-container">
            <!-- Left Panel: VHV List -->
            <div class="list-card">
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                    <h3 style="margin: 0; color: var(--text-primary); font-size: 16px;">รายชื่อ อสม. ในพื้นที่</h3>
                    <span style="font-size: 12px; color: var(--text-muted);" id="vhv-count">พบ 0 ราย</span>
                </div>
                <div class="list-body" id="vhv-list">
                    <div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>
                </div>
            </div>

            <!-- Right Panel: Vertical Stack of NCD and DPAC tables -->
            <div id="task-details-wrapper" style="display: flex; flex-direction: column; gap: 20px; height: 680px; min-width: 0;">

                <!-- Top Table Card: NCD Screenings -->
                <div class="list-card" id="ncd-card" style="height: 330px; padding: 18px; margin-bottom: 0; min-width: 0;">
                    <div class="task-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <div>
                                <h3 style="margin: 0; color: var(--color-primary); font-size: 15px;">📋 งานคัดกรอง NCD</h3>
                                <p style="color: var(--text-muted); margin: 3px 0 0 0; font-size: 12px;" id="ncd-summary-text">ค้าง 0 | ทั้งหมด 0 ใบ</p>
                            </div>
                            <button class="btn-cancel-all" id="btn-cancel-ncd" style="display: none;" onclick="cancelAllNcdTasks()">
                                ดึงคืนทั้งหมด
                            </button>
                        </div>
                    </div>
                    <div class="list-body" style="overflow-x: auto; margin-top: 10px;">
                        <table class="task-table">
                            <thead>
                                <tr>
                                    <th style="width: 105px;">เลขบัตร (CID)</th>
                                    <th style="width: 140px;">ชื่อ - นามสกุล</th>
                                    <th style="width: 45px; text-align: center;">อายุ</th>
                                    <th style="width: 75px;">ที่อยู่</th>
                                    <th style="width: 95px;">สถานะ</th>
                                    <th style="width: 110px;">วันที่มอบหมาย</th>
                                    <th style="width: 70px; text-align: center;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="ncd-list-body">
                                <tr><td colspan="7" style="text-align:center; padding: 40px; color:var(--text-muted);">กรุณาเลือก อสม. จากแถบทางซ้าย</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bottom Table Card: DPAC Followups -->
                <div class="list-card" id="dpac-card" style="height: 330px; padding: 18px; margin-bottom: 0; min-width: 0;">
                    <div class="task-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <div>
                                <h3 style="margin: 0; color: #6366f1; font-size: 15px;">🏃 งานติดตาม DPAC</h3>
                                <p style="color: var(--text-muted); margin: 3px 0 0 0; font-size: 12px;" id="dpac-summary-text">ค้าง 0 | ทั้งหมด 0 ใบ</p>
                            </div>
                            <button class="btn-cancel-all" id="btn-cancel-dpac" style="display: none;" onclick="cancelAllDpacTasks()">
                                ดึงคืนทั้งหมด
                            </button>
                        </div>
                    </div>
                    <div class="list-body" style="overflow-x: auto; margin-top: 10px;">
                        <table class="task-table">
                            <thead>
                                <tr>
                                    <th style="width: 105px;">เลขบัตร (CID)</th>
                                    <th style="width: 140px;">ชื่อ - นามสกุล</th>
                                    <th style="width: 45px; text-align: center;">อายุ</th>
                                    <th style="width: 100px;">รอบติดตาม</th>
                                    <th style="width: 95px;">สถานะ</th>
                                    <th style="width: 110px;">วันที่มอบหมาย</th>
                                    <th style="width: 70px; text-align: center;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="dpac-list-body">
                                <tr><td colspan="7" style="text-align:center; padding: 40px; color:var(--text-muted);">กรุณาเลือก อสม. จากแถบทางซ้าย</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        const tambonData = <?= json_encode($jsData, JSON_UNESCAPED_UNICODE) ?>;

        function formatThaiDate(dateStr) {
            if (!dateStr || dateStr === 'ไม่ระบุ') return 'ไม่ระบุ';
            const cleanDate = dateStr.split(' ')[0];
            const parts = cleanDate.split('-');
            if (parts.length !== 3) return dateStr;
            
            const year = parseInt(parts[0]);
            const month = parseInt(parts[1]);
            const day = parseInt(parts[2]);
            
            const thaiMonths = [
                'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
                'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
            ];
            
            const thaiYear = year + 543;
            const thaiMonth = thaiMonths[month - 1];
            
            return `${day} ${thaiMonth} ${thaiYear}`;
        }

        function resetTaskDetails() {
            document.getElementById('ncd-list-body').innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 40px; color:var(--text-muted);">กรุณาเลือก อสม. จากแถบทางซ้าย</td></tr>';
            document.getElementById('dpac-list-body').innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 40px; color:var(--text-muted);">กรุณาเลือก อสม. จากแถบทางซ้าย</td></tr>';
            document.getElementById('ncd-summary-text').innerText = 'ค้าง 0 | ทั้งหมด 0 ใบ';
            document.getElementById('dpac-summary-text').innerText = 'ค้าง 0 | ทั้งหมด 0 ใบ';
            document.getElementById('btn-cancel-ncd').style.display = 'none';
            document.getElementById('btn-cancel-dpac').style.display = 'none';
        }

        function onTambonChange() {
            const tCode = document.getElementById('tambon').value;
            const hContainer = document.getElementById('hoscode_container');
            const hSelect = document.getElementById('hoscode');
            const mSelect = document.getElementById('moo');

            hSelect.innerHTML = '<option value="">-- เลือกหน่วยบริการ --</option>';
            mSelect.innerHTML = '<option value="">-- เลือกพื้นที่ก่อน --</option>';
            hContainer.style.display = 'none';
            resetTaskDetails();
            document.getElementById('vhv-list').innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>';
            document.getElementById('vhv-count').innerText = 'พบ 0 ราย';

            if (!tCode) return;

            const tInfo = tambonData[tCode];
            if (tInfo.hasSubUnits) {
                hContainer.style.display = 'block';
                for (let hc in tInfo.subUnits) {
                    hSelect.innerHTML += `<option value="${hc}">${tInfo.subUnits[hc].name}</option>`;
                }
            } else {
                populateMoo(tInfo.villages);
            }
        }

        function onHoscodeChange() {
            const tCode = document.getElementById('tambon').value;
            const hCode = document.getElementById('hoscode').value;
            resetTaskDetails();
            document.getElementById('vhv-list').innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>';
            document.getElementById('vhv-count').innerText = 'พบ 0 ราย';

            if (tCode && hCode && tambonData[tCode].hasSubUnits) {
                populateMoo(tambonData[tCode].subUnits[hCode].villages);
            } else {
                document.getElementById('moo').innerHTML = '<option value="">-- เลือกหน่วยบริการก่อน --</option>';
            }
        }

        function populateMoo(villages) {
            const mSelect = document.getElementById('moo');
            mSelect.innerHTML = '<option value="">-- เลือกหมู่บ้าน --</option>';
            villages.forEach(v => {
                mSelect.innerHTML += `<option value="${v.moo}">หมู่ที่ ${v.moo} ${v.name}</option>`;
            });
        }

        function onMooChange() {
            const tambon = document.getElementById('tambon').value;
            const moo = document.getElementById('moo').value;
            const vList = document.getElementById('vhv-list');
            resetTaskDetails();

            if (!tambon || !moo) {
                vList.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>';
                document.getElementById('vhv-count').innerText = 'พบ 0 ราย';
                return;
            }

            let hoscode = '';
            if (tambonData[tambon].hasSubUnits) {
                hoscode = document.getElementById('hoscode').value;
            } else {
                hoscode = tambonData[tambon].hoscode;
            }

            const vhidCode = tambon + moo.padStart(2, '0');

            vList.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">กำลังโหลดรายชื่อ อสม....</div>';

            fetch(`../api/get_assignment_data.php?type=vhvs&moo=${moo}&vhid=${vhidCode}&hoscode=${hoscode}`)
                .then(r => r.json())
                .then(vhvs => {
                    document.getElementById('vhv-count').innerText = `พบ ${vhvs.length} ราย`;
                    if (vhvs.length === 0) {
                        vList.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">ไม่พบ อสม. ในพื้นที่นี้</div>';
                        return;
                    }

                    let html = '';
                    vhvs.forEach(v => {
                        html += `
                            <div class="item-row" id="vhv-row-${v.vhv_id}" onclick="selectVhv('${v.vhv_id}')">
                                <div class="item-info">
                                    <h4>${v.vhv_name}</h4>
                                    <p>⏳ งานค้าง: <strong>${v.total_task_count}</strong> ใบ | 📍 ทั้งหมด: <strong>${v.overall_total_count}</strong> ใบ</p>
                                </div>
                            </div>
                        `;
                    });
                    vList.innerHTML = html;
                })
                .catch(() => {
                    vList.innerHTML = '<div style="text-align: center; color: var(--color-red); padding: 40px;">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
                });
        }

        let selectedVhvId = '';
        let currentNcdTasks = [];
        let currentDpacTasks = [];
        let selectedVhvName = '';

        function selectVhv(vhvId) {
            selectedVhvId = vhvId;

            // Highlight VHV row
            document.querySelectorAll('#vhv-list .item-row').forEach(row => {
                row.classList.remove('active');
            });
            document.getElementById(`vhv-row-${vhvId}`)?.classList.add('active');

            fetchTasks(vhvId);
        }

        function fetchTasks(vhvId) {
            document.getElementById('ncd-list-body').innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">กำลังโหลดข้อมูลภารกิจ...</td></tr>';
            document.getElementById('dpac-list-body').innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">กำลังโหลดข้อมูลภารกิจ...</td></tr>';
            document.getElementById('task-details-wrapper').style.display = 'flex';

            fetch(`../api/get_vhv_tasks.php?vhv_id=${vhvId}`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        selectedVhvName = res.vhv_name;

                        // แยกงาน
                        currentNcdTasks = res.tasks.filter(t => t.task_type === 'screen');
                        currentDpacTasks = res.tasks.filter(t => t.task_type === 'dpac');

                        renderTasks();
                    } else {
                        alert("เกิดข้อผิดพลาด: " + res.message);
                    }
                })
                .catch(() => alert("เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย"));
        }

        function renderTasks() {
            // 1. Render NCD Screenings
            const ncdTbody = document.getElementById('ncd-list-body');
            const ncdText = document.getElementById('ncd-summary-text');
            const btnCancelNcd = document.getElementById('btn-cancel-ncd');

            ncdTbody.innerHTML = '';
            let ncdPending = 0;

            if (currentNcdTasks.length === 0) {
                ncdTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 30px; color:var(--text-muted);">ไม่มีงานคัดกรอง NCD</td></tr>';
                btnCancelNcd.style.display = 'none';
            } else {
                currentNcdTasks.forEach(t => {
                    let statusHtml = '';
                    let actionHtml = '';
                    const roundNum = parseInt(t.round_number) || 1;
                    const roundBadge = roundNum > 1 
                        ? `<span class="badge-rescreen">🔄 รอบที่ ${roundNum}</span>`
                        : `<span style="color:var(--text-muted); font-size:11px;"> (รอบ 1)</span>`;

                    if (t.assignment_status === 'completed') {
                        let summaryBadge = '';
                        if (t.sys_bp1 || t.dtx_value) {
                            const bpText = t.sys_bp1 ? `${t.sys_bp1}/${t.dia_bp1}` : '-';
                            const dtxText = t.dtx_value ? `${t.dtx_value}` : '-';
                            summaryBadge = `<div style="font-size:11px; color:var(--text-secondary); margin-top:2px;">BP: <strong>${bpText}</strong> | DTX: <strong>${dtxText}</strong></div>`;
                        }
                        statusHtml = `<span style="color:var(--color-green); font-weight:bold;">✅ คัดกรองแล้ว</span> ${roundBadge}${summaryBadge}`;
                        actionHtml = `<button class="btn-view-detail" onclick="openScreeningModal(${t.task_id})">🔍 ดูผลเดิม</button>`;
                    } else if (t.assignment_status === 'skipped') {
                        const reasonText = t.skipped_reason ? `<div style="font-size:11px; color:var(--text-muted); margin-top:2px;">เหตุ: ${t.skipped_reason}</div>` : '';
                        statusHtml = `<span style="color:var(--color-red); font-weight:bold;">❌ ข้ามเคสแล้ว</span> ${roundBadge}${reasonText}`;
                        actionHtml = `<button class="btn-view-detail" onclick="openScreeningModal(${t.task_id})">🔍 ดูเหตุผล</button>`;
                    } else {
                        let prevInfoBadge = '';
                        if (t.prev_sys_bp1 || t.prev_dtx_value) {
                            const pBp = t.prev_sys_bp1 ? `${t.prev_sys_bp1}/${t.prev_dia_bp1}` : '-';
                            const pDtx = t.prev_dtx_value ? `${t.prev_dtx_value}` : '-';
                            const pRound = t.prev_round_number ? `รอบที่ ${t.prev_round_number}` : 'เดิม';
                            prevInfoBadge = `<div style="font-size:11px; color:var(--text-secondary); margin-top:2px;">📜 ผลเดิม (${pRound}): BP <strong>${pBp}</strong> | DTX <strong>${pDtx}</strong></div>`;
                        }
                        statusHtml = `<span style="color:var(--color-yellow); font-weight:bold;">⏳ รอคัดกรอง</span> ${roundBadge}${prevInfoBadge}`;
                        actionHtml = `<button class="btn-cancel-assign" onclick="cancelAssignment('screen', '${t.cid}', ${t.task_id}, '${(t.first_name + ' ' + t.last_name).replace(/'/g, "\\'")}')">ดึงคืน</button>`;
                        ncdPending++;
                    }

                    const dateVal = (t.assignment_status === 'completed' || t.assignment_status === 'skipped') && t.screened_at 
                        ? t.screened_at 
                        : t.assigned_at;
                    const datePrefix = t.assignment_status === 'completed' ? 'คัดกรอง: ' : (t.assignment_status === 'skipped' ? 'บันทึก: ' : 'มอบหมาย: ');
                    let dateText = datePrefix + formatThaiDate(dateVal);

                    ncdTbody.innerHTML += `
                        <tr>
                            <td style="font-family:monospace; font-size:12.5px;">${t.cid}</td>
                            <td><strong>${t.first_name} ${t.last_name}</strong></td>
                            <td style="text-align:center; font-size:12.5px;">${t.age}</td>
                            <td style="font-size:12.5px;">${t.house_no} ม.${t.moo}</td>
                            <td style="font-size:12.5px;">${statusHtml}</td>
                            <td style="font-size:12px; color:var(--text-muted);">${dateText}</td>
                            <td style="text-align:center;">${actionHtml}</td>
                        </tr>
                    `;
                });
                btnCancelNcd.style.display = ncdPending > 0 ? 'inline-flex' : 'none';
            }
            const ncdCompleted = currentNcdTasks.filter(t => t.assignment_status === 'completed').length;
            const ncdSkipped = currentNcdTasks.filter(t => t.assignment_status === 'skipped').length;
            ncdText.innerHTML = `งานค้าง: <strong style="color:var(--color-yellow);">${ncdPending}</strong> | คัดกรองสำเร็จ: <strong style="color:var(--color-green);">${ncdCompleted}</strong> | ข้ามเคส: <strong style="color:var(--color-red);">${ncdSkipped}</strong> | รวมมอบหมายทั้งหมด: <strong>${currentNcdTasks.length}</strong> ใบ`;

            // 2. Render DPAC Followups
            const dpacTbody = document.getElementById('dpac-list-body');
            const dpacText = document.getElementById('dpac-summary-text');
            const btnCancelDpac = document.getElementById('btn-cancel-dpac');

            dpacTbody.innerHTML = '';
            let dpacPending = 0;

            if (currentDpacTasks.length === 0) {
                dpacTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 30px; color:var(--text-muted);">ไม่มีงานติดตาม DPAC</td></tr>';
                btnCancelDpac.style.display = 'none';
            } else {
                currentDpacTasks.forEach(t => {
                    let statusHtml = '';
                    let actionHtml = '';
                    let roundHtml = `<span style="background-color: rgba(99, 102, 241, 0.1); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.3); padding: 3px 6px; border-radius: 12px; font-size: 11px; font-weight: bold;">ครั้งที่ ${t.round_number} (${t.risk_type})</span>`;

                    if (t.assignment_status === 'completed') {
                        let summaryBadge = '';
                        if (t.sys_bp1 || t.dtx_value || t.weight) {
                            const bpText = t.sys_bp1 ? `${t.sys_bp1}/${t.dia_bp1}` : '-';
                            const dtxText = t.dtx_value ? `${t.dtx_value}` : '-';
                            summaryBadge = `<div style="font-size:11px; color:var(--text-secondary); margin-top:2px;">BP: <strong>${bpText}</strong> | FBS: <strong>${dtxText}</strong></div>`;
                        }
                        statusHtml = `<span style="color:var(--color-green); font-weight:bold;">✅ ติดตามแล้ว</span>${summaryBadge}`;
                        actionHtml = `<button class="btn-view-detail" onclick="openDpacModal(${t.task_id})">🔍 ดูผลเดิม</button>`;
                    } else if (t.assignment_status === 'skipped') {
                        const reasonText = t.skipped_reason ? `<div style="font-size:11px; color:var(--text-muted); margin-top:2px;">เหตุ: ${t.skipped_reason}</div>` : '';
                        statusHtml = `<span style="color:var(--color-red); font-weight:bold;">❌ ข้ามรอบแล้ว</span>${reasonText}`;
                        actionHtml = `<button class="btn-view-detail" onclick="openDpacModal(${t.task_id})">🔍 ดูเหตุผล</button>`;
                    } else {
                        statusHtml = '<span style="color:var(--color-yellow); font-weight:bold;">⏳ รอติดตาม</span>';
                        actionHtml = `<button class="btn-cancel-assign" onclick="cancelAssignment('dpac', '${t.cid}', ${t.task_id}, '${(t.first_name + ' ' + t.last_name).replace(/'/g, "\\'")}')">ดึงคืน</button>`;
                        dpacPending++;
                    }

                    const dateVal = (t.assignment_status === 'completed' || t.assignment_status === 'skipped') && t.screened_at 
                        ? t.screened_at 
                        : t.assigned_at;
                    const datePrefix = t.assignment_status === 'completed' ? 'ติดตาม: ' : 'มอบหมาย: ';
                    let dateText = datePrefix + formatThaiDate(dateVal);

                    dpacTbody.innerHTML += `
                        <tr>
                            <td style="font-family:monospace; font-size:12.5px;">${t.cid}</td>
                            <td><strong>${t.first_name} ${t.last_name}</strong></td>
                            <td style="text-align:center; font-size:12.5px;">${t.age}</td>
                            <td>${roundHtml}</td>
                            <td style="font-size:12.5px;">${statusHtml}</td>
                            <td style="font-size:12px; color:var(--text-muted);">${dateText}</td>
                            <td style="text-align:center;">${actionHtml}</td>
                        </tr>
                    `;
                });
                btnCancelDpac.style.display = dpacPending > 0 ? 'inline-flex' : 'none';
            }
            const dpacCompleted = currentDpacTasks.filter(t => t.assignment_status === 'completed').length;
            const dpacSkipped = currentDpacTasks.filter(t => t.assignment_status === 'skipped').length;
            dpacText.innerHTML = `งานค้าง: <strong style="color:var(--color-yellow);">${dpacPending}</strong> | ติดตามสำเร็จ: <strong style="color:var(--color-green);">${dpacCompleted}</strong> | รวมมอบหมายทั้งหมด: <strong>${currentDpacTasks.length}</strong> ใบ`;
        }

        function openScreeningModal(taskId) {
            const task = currentNcdTasks.find(t => t.task_id == taskId);
            if (!task) return;

            document.getElementById('modal-screen-title').innerHTML = `📋 รายละเอียดผลการคัดกรอง NCD (รอบที่ ${task.round_number || 1})`;
            document.getElementById('modal-screen-subtitle').innerHTML = `ผู้รับการคัดกรอง: <strong>${task.first_name} ${task.last_name}</strong> (CID: ${task.cid}) | อายุ ${task.age} ปี | บ้านเลขที่ ${task.house_no} ม.${task.moo}`;

            const modalBody = document.getElementById('modal-screen-body');

            if (task.assignment_status === 'skipped') {
                modalBody.innerHTML = `
                    <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 12px; padding: 16px; margin-top: 10px;">
                        <h4 style="margin: 0 0 8px 0; color: var(--color-red);">❌ สถานะ: ข้ามการคัดกรอง (รอบที่ ${task.round_number || 1})</h4>
                        <p style="margin: 0 0 6px 0; font-size: 14px;"><strong>เหตุผลที่ระบุ:</strong> ${task.skipped_reason || 'ไม่ระบุเหตุผล'}</p>
                        <p style="margin: 0; font-size: 13px; color: var(--text-muted);">วันที่บันทึก: ${formatThaiDate(task.screened_at || task.assigned_at)}</p>
                    </div>
                `;
            } else {
                // Formatting Blood Pressure
                let bp1 = (task.sys_bp1 && task.dia_bp1) ? `${task.sys_bp1}/${task.dia_bp1} mmHg` : 'ไม่ได้ระบุ';
                let bp2 = (task.sys_bp2 && task.dia_bp2) ? `${task.sys_bp2}/${task.dia_bp2} mmHg` : 'ไม่ได้วัดซ้ำ';
                let dtx = task.dtx_value ? `${task.dtx_value} mg/dL (${task.dtx_type === 'fpg' ? 'งดอาหาร 8 ชม.' : 'ไม่งดอาหาร/สุ่ม'})` : 'ไม่ได้ตรวจ';
                let bmiText = task.bmi ? `${task.bmi}` : '-';
                let weightText = task.weight ? `${task.weight} กก.` : '-';
                let heightText = task.height ? `${task.height} ซม.` : '-';
                let waistText = task.waist ? `${task.waist} ซม.` : '-';
                let cvRisk = task.cv_risk_score ? `${task.cv_risk_score}%` : 'ไม่มีข้อมูล';

                // Behavior assessment helper
                const formatRisk = (val) => {
                    if (val === 'risk' || val === 'yes' || val === '1' || val === 1) return '<span style="color:var(--color-red); font-weight:bold;">⚠️ เสี่ยง</span>';
                    if (val === 'normal' || val === 'no' || val === '0' || val === 0) return '<span style="color:var(--color-green); font-weight:bold;">ปกติ</span>';
                    return '<span style="color:var(--text-muted);">-</span>';
                };

                modalBody.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px;">
                        <div class="metric-card">
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">🩸 ความดันโลหิต (ครั้งที่ 1)</div>
                            <div style="font-size: 16px; font-weight: bold; color: var(--text-primary);">${bp1}</div>
                            ${task.sys_bp2 ? `<div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">ครั้งที่ 2: ${bp2}</div>` : ''}
                        </div>
                        <div class="metric-card">
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">🍬 น้ำตาลในเลือด (DTX)</div>
                            <div style="font-size: 16px; font-weight: bold; color: var(--text-primary);">${dtx}</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 12px;">
                        <div class="metric-card" style="text-align: center;">
                            <div style="font-size: 11.5px; color: var(--text-secondary);">น้ำหนัก / ส่วนสูง</div>
                            <div style="font-size: 14px; font-weight: bold; margin-top: 2px;">${weightText} / ${heightText}</div>
                        </div>
                        <div class="metric-card" style="text-align: center;">
                            <div style="font-size: 11.5px; color: var(--text-secondary);">รอบเอว</div>
                            <div style="font-size: 14px; font-weight: bold; margin-top: 2px;">${waistText}</div>
                        </div>
                        <div class="metric-card" style="text-align: center;">
                            <div style="font-size: 11.5px; color: var(--text-secondary);">ดัชนีมวลกาย (BMI)</div>
                            <div style="font-size: 14px; font-weight: bold; margin-top: 2px; color: var(--color-primary);">${bmiText}</div>
                        </div>
                    </div>

                    <div style="background: var(--bg-main); border-radius: 12px; padding: 12px; margin-top: 12px; border: 1px solid var(--border-color);">
                        <div style="font-size: 13px; font-weight: bold; color: var(--text-primary); margin-bottom: 8px;">🏃 พฤติกรรมสุขภาพ & ความเสี่ยง CVD</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; font-size: 12.5px;">
                            <div>การกินอาหาร: ${formatRisk(task.diet_risk)}</div>
                            <div>ออกกำลังกาย: ${formatRisk(task.exercise_risk)}</div>
                            <div>ความเครียด: ${formatRisk(task.stress_risk)}</div>
                            <div>การสูบบุหรี่: ${formatRisk(task.smoking_risk)}</div>
                            <div>ดื่มสุรา: ${formatRisk(task.alcohol_risk)}</div>
                            <div>โอกาสเกิด CVD: <strong>${cvRisk}</strong></div>
                        </div>
                    </div>

                    ${task.advice_given ? `
                        <div style="background: rgba(59, 130, 246, 0.06); border-radius: 12px; padding: 12px; margin-top: 12px; border: 1px solid rgba(59, 130, 246, 0.2);">
                            <div style="font-size: 12.5px; font-weight: bold; color: #2563eb; margin-bottom: 4px;">💬 คำแนะนำที่ให้:</div>
                            <div style="font-size: 13px; color: var(--text-primary);">${task.advice_given}</div>
                        </div>
                    ` : ''}

                    <div style="margin-top: 12px; font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between;">
                        <span>📅 วันที่คัดกรอง: <strong>${task.screened_at ? formatThaiDate(task.screened_at) + ' ' + (task.screened_at.split(' ')[1] || '') : 'ไม่ระบุ'}</strong></span>
                        <span>รอบงาน: <strong>รอบที่ ${task.round_number || 1}</strong></span>
                    </div>
                `;
            }

            document.getElementById('screening-detail-modal').style.display = 'flex';
        }

        function openDpacModal(taskId) {
            const task = currentDpacTasks.find(t => t.task_id == taskId);
            if (!task) return;

            document.getElementById('modal-screen-title').innerHTML = `🏃 รายละเอียดการติดตาม DPAC (ครั้งที่ ${task.round_number || 1})`;
            document.getElementById('modal-screen-subtitle').innerHTML = `ผู้รับการติดตาม: <strong>${task.first_name} ${task.last_name}</strong> (CID: ${task.cid}) | กลุ่มความเสี่ยง: ${task.risk_type || '-'}`;

            const modalBody = document.getElementById('modal-screen-body');

            if (task.assignment_status === 'skipped') {
                modalBody.innerHTML = `
                    <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 12px; padding: 16px; margin-top: 10px;">
                        <h4 style="margin: 0 0 8px 0; color: var(--color-red);">❌ สถานะ: ข้ามการติดตามรอบนี้</h4>
                        <p style="margin: 0 0 6px 0; font-size: 14px;"><strong>เหตุผลที่ระบุ:</strong> ${task.skipped_reason || 'ไม่ระบุเหตุผล'}</p>
                        <p style="margin: 0; font-size: 13px; color: var(--text-muted);">วันที่บันทึก: ${formatThaiDate(task.screened_at || task.assigned_at)}</p>
                    </div>
                `;
            } else {
                let bp = (task.sys_bp1 && task.dia_bp1) ? `${task.sys_bp1}/${task.dia_bp1} mmHg` : 'ไม่ได้ระบุ';
                let fbs = task.dtx_value ? `${task.dtx_value} mg/dL` : 'ไม่ได้ตรวจ';
                let weightText = task.weight ? `${task.weight} กก.` : '-';
                let waistText = task.waist ? `${task.waist} ซม.` : '-';

                modalBody.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px;">
                        <div class="metric-card">
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">🩸 ความดันโลหิต</div>
                            <div style="font-size: 16px; font-weight: bold; color: var(--text-primary);">${bp}</div>
                        </div>
                        <div class="metric-card">
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">🍬 ระดับน้ำตาล (FBS)</div>
                            <div style="font-size: 16px; font-weight: bold; color: var(--text-primary);">${fbs}</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px;">
                        <div class="metric-card" style="text-align: center;">
                            <div style="font-size: 11.5px; color: var(--text-secondary);">น้ำหนัก</div>
                            <div style="font-size: 14px; font-weight: bold; margin-top: 2px;">${weightText}</div>
                        </div>
                        <div class="metric-card" style="text-align: center;">
                            <div style="font-size: 11.5px; color: var(--text-secondary);">รอบเอว</div>
                            <div style="font-size: 14px; font-weight: bold; margin-top: 2px;">${waistText}</div>
                        </div>
                    </div>

                    ${task.advice_given ? `
                        <div style="background: rgba(99, 102, 241, 0.06); border-radius: 12px; padding: 12px; margin-top: 12px; border: 1px solid rgba(99, 102, 241, 0.2);">
                            <div style="font-size: 12.5px; font-weight: bold; color: #6366f1; margin-bottom: 4px;">💬 คำแนะนำในการปรับเปลี่ยนพฤติกรรม:</div>
                            <div style="font-size: 13px; color: var(--text-primary);">${task.advice_given}</div>
                        </div>
                    ` : ''}

                    <div style="margin-top: 12px; font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between;">
                        <span>📅 วันที่ติดตามผล: <strong>${task.screened_at ? formatThaiDate(task.screened_at) + ' ' + (task.screened_at.split(' ')[1] || '') : 'ไม่ระบุ'}</strong></span>
                        <span>รอบงาน: <strong>ครั้งที่ ${task.round_number || 1}</strong></span>
                    </div>
                `;
            }

            document.getElementById('screening-detail-modal').style.display = 'flex';
        }

        function closeScreeningModal() {
            document.getElementById('screening-detail-modal').style.display = 'none';
        }

        function cancelAssignment(taskType, cid, taskId, name) {
            const bodyData = taskType === 'dpac' ? {
                followup_id: taskId
            } : {
                cid: cid,
                task_id: taskId
            };
            const confirmMsg = taskType === 'dpac' ?
                `⚠️ ยืนยันยกเลิกใบงานติดตาม DPAC ของ [${name}]?\n\nงานติดตามรอบนี้จะถูกดึงคืน อสม. จะไม่เห็นงานนี้ในมือถือทันที` :
                `⚠️ ยืนยันยกเลิกใบงานคัดกรอง NCD ของ [${name}]?\n\nเป้าหมายจะกลับไปเป็นสถานะ "ยังไม่ได้มอบหมาย" ในระบบทันที`;

            if (confirm(confirmMsg)) {
                fetch('../api/cancel_assignment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(bodyData)
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert("ดึงงานคืนสำเร็จ!");
                            fetchTasks(selectedVhvId);
                            onMooChange();
                        } else {
                            alert("เกิดข้อผิดพลาด: " + data.message);
                        }
                    })
                    .catch(() => alert("เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย"));
            }
        }

        function cancelAllNcdTasks() {
            const pendingTasks = currentNcdTasks.filter(t => t.assignment_status === 'pending');
            if (pendingTasks.length === 0) return;

            if (confirm(`⚠️ ยืนยันดึงงานค้างคัดกรอง NCD คืนทั้งหมดจำนวน ${pendingTasks.length} รายการ จาก อสม. ${selectedVhvName}?`)) {
                executeBulkCancel(pendingTasks);
            }
        }

        function cancelAllDpacTasks() {
            const pendingTasks = currentDpacTasks.filter(t => t.assignment_status === 'pending');
            if (pendingTasks.length === 0) return;

            if (confirm(`⚠️ ยืนยันดึงงานค้างติดตาม DPAC คืนทั้งหมดจำนวน ${pendingTasks.length} รายการ จาก อสม. ${selectedVhvName}?`)) {
                executeBulkCancel(pendingTasks);
            }
        }

        function executeBulkCancel(tasks) {
            // Show loading indicators
            document.getElementById('ncd-list-body').innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:var(--color-primary); font-weight:bold;">⏳ กำลังทยอยดึงงานคืน...</td></tr>';
            document.getElementById('dpac-list-body').innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:var(--color-primary); font-weight:bold;">⏳ กำลังทยอยดึงงานคืน...</td></tr>';

            const promises = tasks.map(t => {
                const bodyData = t.task_type === 'dpac' ? {
                    followup_id: t.task_id
                } : {
                    cid: t.cid,
                    task_id: t.task_id
                };
                return fetch('../api/cancel_assignment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(bodyData)
                }).then(r => r.json());
            });

            Promise.all(promises)
                .then(results => {
                    const successCount = results.filter(r => r.status === 'success').length;
                    alert(`ดึงงานคืนสำเร็จทั้งหมด ${successCount} รายการ!`);
                    fetchTasks(selectedVhvId);
                    onMooChange();
                })
                .catch(() => {
                    alert("เกิดข้อผิดพลาดระหว่างดำเนินการบางรายการ");
                    fetchTasks(selectedVhvId);
                    onMooChange();
                });
        }
    </script>

    <!-- Screening Details Modal -->
    <div id="screening-detail-modal" class="modal-overlay" onclick="if(event.target === this) closeScreeningModal()">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
                <div>
                    <h3 style="margin: 0; color: var(--color-primary); font-size: 17px;" id="modal-screen-title">📋 ผลการคัดกรอง NCD</h3>
                    <p style="margin: 4px 0 0 0; color: var(--text-secondary); font-size: 13px;" id="modal-screen-subtitle">รายละเอียดการตรวจคัดกรองสุขภาพ</p>
                </div>
                <button type="button" onclick="closeScreeningModal()" style="background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer; line-height: 1; padding: 0 4px;">&times;</button>
            </div>
            
            <div id="modal-screen-body">
                <!-- Dynamic Content -->
            </div>

            <div style="text-align: right; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--border-color);">
                <button type="button" onclick="closeScreeningModal()" style="background: var(--bg-main); color: var(--text-primary); border: 1px solid var(--border-color); padding: 8px 18px; border-radius: 10px; font-weight: bold; cursor: pointer;">
                    ปิดหน้าต่าง
                </button>
            </div>
        </div>
    </div>
</body>

</html>