<?php
// admin/assignment.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

require_once __DIR__ . '/../config/demo_data.php';

$jsData = [];
$subsList = [];

if (DemoDataProvider::isDemoMode()) {
    $subsList = [
        ['sub_district_code' => '341801', 'sub_district_name' => 'ตาลสุม (จำลอง)']
    ];
    $jsData = [
        '341801' => [
            'name' => 'ตำบลตาลสุม (จำลอง)',
            'hasSubUnits' => false,
            'hoscode' => '99999',
            'villages' => [
                ['moo' => 1, 'name' => 'บ้านตาลสุม (จำลอง)'],
                ['moo' => 2, 'name' => 'บ้านดอนใหญ่ (จำลอง)'],
                ['moo' => 3, 'name' => 'บ้านโคกสว่าง (จำลอง)'],
                ['moo' => 4, 'name' => 'บ้านนาเจริญ (จำลอง)'],
                ['moo' => 5, 'name' => 'บ้านโนนงาม (จำลอง)']
            ]
        ]
    ];
} else {
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
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Assignment - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-main);
        }

        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
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
            height: 600px;
            display: flex;
            flex-direction: column;
        }

        .list-body {
            flex: 1;
            overflow-y: auto;
            margin-top: 10px;
            padding-right: 5px;
        }

        .item-row {
            background-color: var(--bg-main);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 10px;
            box-shadow: var(--neumorph-flat);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all var(--transition-speed);
        }

        .item-info h4 {
            margin: 0 0 4px 0;
            color: var(--text-primary);
            font-size: 16px;
        }

        .item-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(13, 44, 84, 0.4);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-main);
            border-radius: 20px;
            padding: 24px;
            width: 90%;
            max-width: 500px;
            box-shadow: var(--neumorph-flat);
        }

        .row-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Custom Checkbox */
        .target-checkbox {
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: var(--color-accent);
        }

        .assign-btn {
            background: var(--color-green);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
        }

        .assign-btn:hover {
            background: #059669;
        }
    </style>
</head>

<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px; flex-wrap: wrap; gap: 12px;">
            <h2 style="color: var(--color-accent); margin: 0;">
                ระบบมอบหมายงานคัดกรอง (Smart Assignment Manager)
            </h2>
            <a href="vhv_tasks.php" class="btn-primary" style="padding: 10px 20px; border-radius: 20px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; font-weight: bold; background: var(--color-primary); color: white; box-shadow: var(--neumorph-flat); transition: all 0.2s;">
                📋 เช็คงาน อสม. (VHV Tasks)
            </a>
        </div>

        <!-- Step 1: Filters -->
        <div class="filter-card">
            <h4 style="margin-top: 0; margin-bottom: 16px; color: var(--text-primary);">เลือกเขตรับผิดชอบ</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
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
                <div id="moo_container">
                    <label class="form-label">หมู่บ้าน</label>
                    <select id="moo" class="form-select" onchange="fetchData()">
                        <option value="">-- เลือกพื้นที่ก่อน --</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Target Group Tabs -->
        <div class="tabs"
            style="display: flex; gap: 8px; margin: 20px 0; background-color: var(--bg-card); padding: 6px; border-radius: 16px; box-shadow: var(--neumorph-inset); width: fit-content; flex-wrap: wrap;">
            <button onclick="switchTargetGroup('main')" id="tab-group-main" class="tab active"
                style="border: none; background: none; font-size: 15px; font-weight: 800; padding: 10px 20px; cursor: pointer; border-radius: 12px; transition: all var(--transition-speed); color: var(--text-secondary);">
                📋 กลุ่มเป้าหมายหลัก (Risk 1-2)
            </button>
            <button onclick="switchTargetGroup('suspect')" id="tab-group-suspect" class="tab"
                style="border: none; background: none; font-size: 15px; font-weight: 800; padding: 10px 20px; cursor: pointer; border-radius: 12px; transition: all var(--transition-speed); color: var(--text-secondary);">
                🔵 กลุ่มป่วย/สงสัยป่วย (Risk 3) [สำรอง]
            </button>
        </div>

        <style>
            .tabs .tab.active {
                background-color: #0d2c54 !important;
                /* Force Navy Blue */
                color: #ffffff !important;
                box-shadow: inset 3px 3px 6px rgba(0, 0, 0, 0.4), inset -3px -3px 6px rgba(255, 255, 255, 0.1) !important;
                font-weight: 800;
            }
        </style>

        <!-- Step 2: Lists (Targets vs VHVs) -->
        <div class="grid-container">
            <!-- Left: Targets -->
            <div class="list-card">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                    <div>
                        <h3 style="margin: 0; color: var(--text-primary);">รายชื่อประชากรเป้าหมาย</h3>
                        <span style="font-size: 12px; color: var(--text-muted);" id="target-count">พบ 0 ราย</span>
                    </div>
                    <button onclick="openManualModal()" class="numpad-btn btn-action"
                        style="margin: 0; padding: 8px 16px; border-radius: 20px; font-size: 14px; width: auto; height: auto;">
                        + เพิ่มแมนนวล
                    </button>
                </div>

                <!-- Search & Status Filter Row -->
                <div style="margin-top: 12px; display: flex; gap: 10px;">
                    <input type="text" id="search-target" placeholder="🔍 พิมพ์ชื่อ-นามสกุล หรือบ้านเลขที่เพื่อค้นหา..."
                        style="flex: 1; padding: 10px 14px; border-radius: 12px; border: 1px solid var(--border-color); background-color: var(--bg-main); color: var(--text-primary); font-size: 14px; box-sizing: border-box; box-shadow: var(--neumorph-inset); transition: all 0.3s;"
                        oninput="onSearchInput()">
                    <select id="filter-status" onchange="onStatusFilterChange()"
                        style="width: 140px; padding: 0 10px; border-radius: 12px; border: 1px solid var(--border-color); background-color: var(--bg-main); color: var(--text-primary); font-size: 14px; box-sizing: border-box; box-shadow: var(--neumorph-inset); cursor: pointer;">
                        <option value="all">ทั้งหมด</option>
                        <option value="assigned">มอบหมายแล้ว</option>
                        <option value="unassigned">ยังไม่มอบหมาย</option>
                    </select>
                </div>

                <div style="margin-top: 12px; display: flex; justify-content: space-between; align-items: center;">
                    <label
                        style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-secondary); font-size: 14px;">
                        <input type="checkbox" id="select-all" class="target-checkbox" onchange="toggleSelectAll()">
                        เลือกทั้งหมด
                    </label>
                    <span id="selected-count"
                        style="font-weight: bold; color: var(--color-accent); font-size: 14px;">เลือก 0 คน</span>
                </div>

                <div class="list-body" id="target-list">
                    <div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>
                </div>
            </div>

            <!-- Right: VHVs -->
            <div class="list-card" id="vhv-card">
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                    <h3 style="margin: 0; color: var(--text-primary);">รายชื่อ อสม. ในพื้นที่</h3>
                    <span style="font-size: 12px; color: var(--text-muted);" id="vhv-count">พบ 0 ราย</span>
                </div>

                <div style="margin-top: 12px; font-size: 14px; color: var(--text-secondary);">
                    <p style="margin-bottom: 8px;">👉 เลือกประชากรทางซ้ายมือ เลือกเปิดรอบคัดกรอง และกดปุ่ม <b>"มอบหมาย"</b> ที่ อสม. ด้านล่างนี้</p>
                    <div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; background: var(--bg-main); padding: 8px 14px; border-radius: 12px; border: 1px solid var(--border-color);">
                        <span style="font-size: 13px; font-weight: 700; color: var(--text-primary);">🎯 รอบการคัดกรอง:</span>
                        <select id="assign-round-select" style="padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--color-primary); font-size: 13px; font-weight: 800;">
                            <option value="auto" selected>✨ อัตโนมัติ (สร้างรอบถัดไปโดยเก็บประวัติเดิม)</option>
                            <?php for ($roundOption = 1; $roundOption <= 10; $roundOption++): ?>
                                <option value="<?= $roundOption ?>"><?= $roundOption === 1 ? 'รอบที่ 1 (ประจำปี / Baseline)' : "🔄 รอบที่ {$roundOption} (คัดกรองติดตามซ้ำ)" ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="list-body" id="vhv-list">
                    <div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>
                </div>
            </div>

            <!-- Right: Suspect Activation Panel -->
            <div class="list-card" id="suspect-activation-card" style="display: none;">
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                    <h3 style="margin: 0; color: var(--color-accent);">จัดการรายชื่อกลุ่มป่วย/สงสัยป่วย</h3>
                    <span
                        style="font-size: 12px; color: var(--text-muted);">ระบุผู้ใช้เป็นกลุ่มเป้าหมายคัดกรองหลัก</span>
                </div>

                <div
                    style="margin-top: 20px; font-size: 14px; color: var(--text-secondary); line-height: 1.6; flex: 1;">
                    <p style="margin-bottom: 12px;">👉 <b>ขั้นตอนดำเนินการ:</b></p>
                    <ol
                        style="padding-left: 20px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px;">
                        <li>เลือกประชากรป่วย/สงสัยป่วยฝั่งซ้ายมือ</li>
                        <li>กดปุ่ม <b>"ยืนยันระบุเป็นกลุ่มเป้าหมายหลัก"</b> ด้านล่างนี้</li>
                        <li>ระบบจะย้ายรายชื่อเข้าสู่กลุ่มคัดกรอง และ อสม. จะเห็นงานเพื่อไปดำเนินการได้ทันที</li>
                    </ol>
                </div>

                <div>
                    <button onclick="activateSuspects()" class="btn-primary"
                        style="width: 100%; height: 50px; font-size: 16px; font-weight: bold; border-radius: var(--border-radius); border: none; background: var(--color-green); color: white; cursor: pointer; box-shadow: var(--neumorph-flat);">
                        💾 ยืนยันระบุเป็นกลุ่มเป้าหมายหลัก
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Add Target Modal -->
    <div class="modal-overlay" id="manual-modal">
        <div class="modal-content">
            <h3
                style="color: var(--color-accent); margin-top: 0; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                เพิ่มประชากรเป้าหมาย (Manual)</h3>
            <form id="manual-form" onsubmit="saveManualTarget(event)">
                <div class="form-group">
                    <label class="form-label">เลขบัตรประชาชน (13 หลัก)</label>
                    <input type="text" id="m_cid" class="form-input-text" maxlength="13" required pattern="\d{13}">
                </div>
                <div class="row-grid">
                    <div>
                        <label class="form-label">ชื่อ</label>
                        <input type="text" id="m_fname" class="form-input-text" required>
                    </div>
                    <div>
                        <label class="form-label">นามสกุล</label>
                        <input type="text" id="m_lname" class="form-input-text" required>
                    </div>
                </div>
                <div class="row-grid">
                    <div>
                        <label class="form-label">เพศ</label>
                        <select id="m_sex" class="form-select" required>
                            <option value="1">ชาย</option>
                            <option value="2">หญิง</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">วันเกิด (ปี ค.ศ.)</label>
                        <input type="date" id="m_birth" class="form-input-text" required>
                    </div>
                </div>
                <div class="row-grid">
                    <div>
                        <label class="form-label">บ้านเลขที่</label>
                        <input type="text" id="m_house" class="form-input-text" required>
                    </div>
                    <div>
                        <label class="form-label">สิทธิ์คัดกรอง</label>
                        <div style="display: flex; gap: 10px; margin-top: 5px;">
                            <label><input type="checkbox" id="m_dm" checked> เบาหวาน</label>
                            <label><input type="checkbox" id="m_ht" checked> ความดัน</label>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="button" onclick="closeManualModal()" class="btn-giant btn-giant-secondary"
                        style="flex: 1; margin: 0;">ยกเลิก</button>
                    <button type="submit" class="btn-giant btn-giant-primary"
                        style="flex: 1; margin: 0;">บันทึกเพิ่มรายชื่อ</button>
                </div>
        </div>
    </div>

    <!-- VHV Tasks List Modal -->
    <div class="modal-overlay" id="vhv-tasks-modal" onclick="if(event.target === this) closeVhvTasksModal()">
        <div class="modal-content" style="max-width: 780px; width: 92%; max-height: 85vh; display: flex; flex-direction: column; padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
                <div>
                    <h3 style="margin: 0; color: var(--color-primary); font-size: 18px;" id="vhv-modal-title">📋 ภาระงาน อสม.</h3>
                    <p style="margin: 4px 0 0 0; color: var(--text-secondary); font-size: 13px;" id="vhv-modal-subtitle">รายการงานคัดกรอง NCD และงานติดตาม DPAC ทั้งหมด</p>
                </div>
                <button type="button" onclick="closeVhvTasksModal()" style="background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer; line-height: 1; padding: 0 4px;">&times;</button>
            </div>

            <!-- Tab buttons to filter status -->
            <div style="display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap;">
                <button type="button" onclick="switchVhvTaskFilter('all')" id="vhv-filter-all" class="tab active" style="border: none; padding: 6px 14px; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer;">
                    ทั้งหมด (<span id="vhv-count-all">0</span>)
                </button>
                <button type="button" onclick="switchVhvTaskFilter('pending')" id="vhv-filter-pending" class="tab" style="border: none; padding: 6px 14px; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer;">
                    ⏳ งานค้าง (<span id="vhv-count-pending">0</span>)
                </button>
                <button type="button" onclick="switchVhvTaskFilter('completed')" id="vhv-filter-completed" class="tab" style="border: none; padding: 6px 14px; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer;">
                    ✅ สำเร็จแล้ว (<span id="vhv-count-completed">0</span>)
                </button>
                <button type="button" onclick="switchVhvTaskFilter('skipped')" id="vhv-filter-skipped" class="tab" style="border: none; padding: 6px 14px; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer;">
                    ❌ ข้ามเคส (<span id="vhv-count-skipped">0</span>)
                </button>
            </div>

            <!-- Task Table Container -->
            <div style="flex: 1; overflow-y: auto; padding-right: 4px; min-height: 250px;" id="vhv-modal-task-body">
                <div style="text-align: center; color: var(--text-muted); padding: 40px;">กำลังโหลดรายการงาน...</div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--border-color);">
                <span id="vhv-modal-summary" style="font-size: 12.5px; color: var(--text-muted);"></span>
                <button type="button" onclick="closeVhvTasksModal()" class="btn-giant btn-giant-secondary" style="margin: 0; padding: 8px 20px; width: auto; height: auto; font-size: 14px;">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- Load Tambon Data & Scripts -->
    <script>
        // Data logic from register.php
        const tambonData = <?= json_encode($jsData, JSON_UNESCAPED_UNICODE) ?>;

        function onTambonChange() {
            const tCode = document.getElementById('tambon').value;
            const hContainer = document.getElementById('hoscode_container');
            const hSelect = document.getElementById('hoscode');
            const mSelect = document.getElementById('moo');

            hSelect.innerHTML = '<option value="">-- เลือกหน่วยบริการ --</option>';
            mSelect.innerHTML = '<option value="">-- เลือกพื้นที่ก่อน --</option>';
            hContainer.style.display = 'none';

            if (!tCode) {
                fetchData();
                return;
            }

            const tInfo = tambonData[tCode];
            if (tInfo.hasSubUnits) {
                hContainer.style.display = 'block';
                for (let hc in tInfo.subUnits) {
                    hSelect.innerHTML += `<option value="${hc}">${tInfo.subUnits[hc].name}</option>`;
                }
            } else {
                populateMoo(tInfo.villages);
                fetchData();
            }
        }

        function onHoscodeChange() {
            const tCode = document.getElementById('tambon').value;
            const hCode = document.getElementById('hoscode').value;
            if (tCode && hCode && tambonData[tCode].hasSubUnits) {
                populateMoo(tambonData[tCode].subUnits[hCode].villages);
                fetchData();
            } else {
                document.getElementById('moo').innerHTML = '<option value="">-- เลือกหน่วยบริการก่อน --</option>';
                fetchData();
            }
        }

        function populateMoo(villages) {
            const mSelect = document.getElementById('moo');
            mSelect.innerHTML = '<option value="">-- เลือกหมู่บ้าน --</option>';
            villages.forEach(v => {
                mSelect.innerHTML += `<option value="${v.moo}">หมู่ที่ ${v.moo} ${v.name}</option>`;
            });
        }

        let currentTargets = [];
        let currentTargetGroup = 'main';
        const selectedCids = new Set();

        function switchTargetGroup(group) {
            currentTargetGroup = group;
            selectedCids.clear();

            // Toggle active tab class
            document.getElementById('tab-group-main').classList.toggle('active', group === 'main');
            document.getElementById('tab-group-suspect').classList.toggle('active', group === 'suspect');

            // Toggle side cards
            document.getElementById('vhv-card').style.display = group === 'main' ? 'flex' : 'none';
            document.getElementById('suspect-activation-card').style.display = group === 'suspect' ? 'flex' : 'none';

            // Reset selections
            document.getElementById('select-all').checked = false;
            updateSelectedCount();

            fetchData();
        }

        function fetchData() {
            selectedCids.clear();
            const searchInput = document.getElementById('search-target');
            if (searchInput) searchInput.value = '';

            const statusFilter = document.getElementById('filter-status');
            if (statusFilter) statusFilter.value = 'all';

            const tambon = document.getElementById('tambon').value;
            const moo = document.getElementById('moo').value;
            let hoscode = '';

            if (!tambon || !moo) {
                document.getElementById('target-list').innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>';
                document.getElementById('vhv-list').innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>';
                document.getElementById('target-count').innerText = 'พบ 0 ราย';
                document.getElementById('vhv-count').innerText = 'พบ 0 ราย';
                return;
            }

            if (tambonData[tambon].hasSubUnits) {
                hoscode = document.getElementById('hoscode').value;
            } else {
                hoscode = tambonData[tambon].hoscode;
            }

            const vhidCode = tambon + moo.padStart(2, '0');

            // Fetch Targets
            fetch(`../api/get_assignment_data.php?type=targets&moo=${moo}&vhid=${vhidCode}&hoscode=${hoscode}&group=${currentTargetGroup}`)
                .then(r => r.json())
                .then(data => {
                    currentTargets = data;
                    renderTargets();
                });

            // Fetch VHVs
            fetch(`../api/get_assignment_data.php?type=vhvs&moo=${moo}&vhid=${vhidCode}&hoscode=${hoscode}&group=${currentTargetGroup}`)
                .then(r => r.json())
                .then(data => {
                    renderVhvs(data);
                });
        }

        function onSearchInput() {
            renderTargets();
        }

        function onStatusFilterChange() {
            renderTargets();
        }

        function renderTargets() {
            const list = document.getElementById('target-list');
            const searchVal = (document.getElementById('search-target')?.value || '').trim().toLowerCase();
            const filterStatus = (document.getElementById('filter-status')?.value || 'all');

            // Filter targets based on search query and assignment status
            const filteredTargets = currentTargets.filter(t => {
                // 1. Search filter
                if (searchVal) {
                    const fullName = `${t.first_name} ${t.last_name}`.toLowerCase();
                    const houseNo = (t.house_no || '').toString().toLowerCase();
                    if (!fullName.includes(searchVal) && !houseNo.includes(searchVal)) return false;
                }

                // 2. Status filter
                if (filterStatus === 'assigned') {
                    if (!t.assigned_vhv) return false;
                } else if (filterStatus === 'unassigned') {
                    if (t.assigned_vhv) return false;
                }

                return true;
            });

            if (searchVal || filterStatus !== 'all') {
                let filterLabel = '';
                if (filterStatus === 'assigned') filterLabel = 'มอบหมายแล้ว';
                if (filterStatus === 'unassigned') filterLabel = 'ยังไม่มอบหมาย';
                const searchLabel = searchVal ? `ค้นหา: "${searchVal}"` : '';
                const parts = [searchLabel, filterLabel].filter(Boolean);
                document.getElementById('target-count').innerText = `พบ ${filteredTargets.length} ราย (${parts.join(', ')})`;
            } else {
                document.getElementById('target-count').innerText = `พบ ${currentTargets.length} ราย`;
            }
            updateSelectedCount();

            if (filteredTargets.length === 0) {
                list.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">ไม่พบประชากรเป้าหมายตามที่ค้นหา</div>';
                return;
            }

            let html = '';
            filteredTargets.forEach(t => {
                let assignedText = '';
                let cancelBtn = '';
                const completedRound = parseInt(t.max_completed_round) || 0;
                const currentRound = parseInt(t.round_number) || 1;

                if (t.assigned_vhv) {
                    if (t.assignment_status === 'completed') {
                        assignedText = `<span style="color: var(--color-green); font-size: 12px; font-weight: bold;">✅ คัดกรองรอบที่ ${currentRound} แล้ว (อสม. ${t.assigned_vhv})</span>`;
                    } else if (t.assignment_status === 'skipped') {
                        assignedText = `<span style="color: var(--color-red); font-size: 12px; font-weight: bold;">❌ ข้ามเคสรอบที่ ${currentRound} (อสม. ${t.assigned_vhv})</span>`;
                    } else {
                        assignedText = `<span style="color: var(--color-yellow); font-size: 12px; font-weight: bold;">⏳ รอดำเนินการรอบที่ ${currentRound} (${t.assigned_vhv})</span>`;
                        cancelBtn = `<button onclick="cancelAssignment('${t.cid}', '${(t.first_name + ' ' + t.last_name).replace(/'/g, "\\'")}', ${t.assignment_id || 0})"
                            style="margin-left:8px; padding: 4px 10px; border-radius: 8px; border: 1px solid var(--color-red, #ef4444); background: transparent; color: var(--color-red, #ef4444); font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s;"
                            onmouseover="this.style.background='rgba(239,68,68,0.12)'"
                            onmouseout="this.style.background='transparent'"
                        >ยกเลิก</button>`;
                    }
                } else {
                    if (completedRound > 0) {
                        const vhvLabel = t.prev_vhv_name ? ` (อสม. ${t.prev_vhv_name})` : '';
                        assignedText = `<span style="color: var(--color-accent); font-size: 12px; font-weight: bold;">✅ คัดกรองรอบที่ ${completedRound} แล้ว${vhvLabel}</span>`;
                    } else {
                        assignedText = '<span style="color: var(--text-muted); font-size: 12px;">(ยังไม่มอบหมาย - รอบ 1)</span>';
                    }
                }

                html += `
                    <div class="item-row">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <input type="checkbox" class="target-checkbox item-cb" value="${t.cid}" ${selectedCids.has(t.cid) ? 'checked' : ''} onchange="onCheckboxChange('${t.cid}', this.checked)">
                            <div class="item-info">
                                <h4>${t.first_name} ${t.last_name}</h4>
                                <p>บ้านเลขที่: ${t.house_no} | อายุ: ${t.age} ปี</p>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:4px;">${assignedText}${cancelBtn}</div>
                    </div>
                `;
            });
            list.innerHTML = html;

            // Check if all filtered items are in selectedCids to toggle the header checkbox
            const allChecked = filteredTargets.every(t => selectedCids.has(t.cid));
            document.getElementById('select-all').checked = allChecked && filteredTargets.length > 0;
        }

        function renderVhvs(vhvs) {
            const list = document.getElementById('vhv-list');
            document.getElementById('vhv-count').innerText = `พบ ${vhvs.length} ราย`;

            if (vhvs.length === 0) {
                list.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">ไม่พบ อสม. ในพื้นที่นี้</div>';
                return;
            }

            let html = '';
            vhvs.forEach(v => {
                html += `
                    <div class="item-row">
                        <div class="item-info">
                            <h4 style="color: var(--color-accent);">${v.vhv_name}</h4>
                            <p style="margin: 2px 0 0 0; font-size: 13px;">
                                📍 ใบงานทั้งหมด: <strong style="color: var(--color-green, #10b981);">${v.village_task_count}</strong> งาน
                                <span style="color: var(--text-muted); margin: 0 4px;">|</span>
                                ⏳ งานค้างทั้งหมด: <strong style="color: var(--color-yellow, #f59e0b);">${v.total_task_count}</strong> งาน
                            </p>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="button" onclick="openVhvTasksModal('${v.vhv_id}', '${(v.vhv_name || '').replace(/'/g, "\\'")}')" class="btn-action" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: bold; text-decoration: none; background: var(--bg-card); color: var(--color-primary); border: 1px solid var(--border-color); display: inline-flex; align-items: center; gap: 4px; box-shadow: var(--neumorph-flat); cursor: pointer;">
                                🔍 ดูงาน
                            </button>
                            <button onclick="assignTasks('${v.vhv_id}')" class="assign-btn">
                                มอบหมาย
                            </button>
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        }

        function onCheckboxChange(cid, checked) {
            if (checked) {
                selectedCids.add(cid);
            } else {
                selectedCids.delete(cid);
            }
            updateSelectedCount();

            // Adjust the select-all header checkbox state
            const list = document.getElementById('target-list');
            const visibleCheckboxes = Array.from(list.querySelectorAll('.item-cb'));
            const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
            document.getElementById('select-all').checked = allVisibleChecked;
        }

        function toggleSelectAll() {
            const isChecked = document.getElementById('select-all').checked;
            const list = document.getElementById('target-list');
            list.querySelectorAll('.item-cb').forEach(cb => {
                cb.checked = isChecked;
                if (isChecked) {
                    selectedCids.add(cb.value);
                } else {
                    selectedCids.delete(cb.value);
                }
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const count = selectedCids.size;
            document.getElementById('selected-count').innerText = `เลือก ${count} คน`;
        }

        function assignTasks(vhvId) {
            const cids = Array.from(selectedCids);
            if (cids.length === 0) {
                alert("กรุณาเลือกประชากรเป้าหมายฝั่งซ้ายมือก่อนครับ");
                return;
            }

            const roundVal = document.getElementById('assign-round-select')?.value || 'auto';

            // Check targets that already completed previous round
            const completedTargets = currentTargets.filter(t =>
                cids.includes(t.cid) && t.assignment_status === 'completed'
            );

            // Only show warning if admin explicitly forces Round 1 overwrite
            if (roundVal === '1' && completedTargets.length > 0) {
                const names = completedTargets.map(t => `- ${t.first_name} ${t.last_name}`).join('\n');
                const confirmProceed = confirm(
                    `⚠️ แจ้งเตือนการเลือกบังคับคัดกรองรอบที่ 1:\n\n` +
                    `ตรวจพบกลุ่มเป้าหมายที่เคยคัดกรองสำเร็จแล้ว ดังนี้:\n${names}\n\n` +
                    `หากท่านต้องการให้เปิดงานคัดกรองซ้ำ (รอบที่ 2, 3...) โดยไม่เขียนทับข้อมูลเดิม แนะนำให้เลือก '✨ อัตโนมัติ (ต่อรอบ)' แทนครับ\n\n` +
                    `คุณแน่ใจหรือว่าต้องการบังคับระบุเป็นรอบที่ 1 หรือไม่?`
                );
                if (!confirmProceed) return;
            }

            let confirmMsg = '';
            if (roundVal === 'auto') {
                if (completedTargets.length > 0) {
                    confirmMsg = `ยืนยันมอบหมายงาน ${cids.length} ราย ให้ อสม. ท่านนี้?\n\n(ระบบจะเปิดงานคัดกรองซ้ำรอบถัดไป ให้อัตโนมัติ โดยเก็บผลการคัดกรองเดิมไว้ครบถ้วน)`;
                } else {
                    confirmMsg = `ยืนยันมอบหมายงาน ${cids.length} ราย ให้ อสม. ท่านนี้?`;
                }
            } else {
                confirmMsg = `ยืนยันมอบหมายงาน (รอบที่ ${roundVal}) จำนวน ${cids.length} ราย ให้ อสม. ท่านนี้?`;
            }

            if (confirm(confirmMsg)) {
                fetch('../api/assign_tasks.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            vhv_id: vhvId,
                            target_cids: cids,
                            round_number: roundVal
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert("มอบหมายงานสำเร็จ!");
                            selectedCids.clear(); // ล้างรายการที่เลือก
                            fetchData(); // Refresh lists
                        } else {
                            alert("เกิดข้อผิดพลาด: " + data.message);
                        }
                    })
                    .catch(err => alert("เกิดข้อผิดพลาดในการเชื่อมต่อ"));
            }
        }

        function activateSuspects() {
            const cids = Array.from(selectedCids);
            if (cids.length === 0) {
                alert("กรุณาเลือกประชากรป่วย/สงสัยป่วยที่ต้องการเปิดสิทธิ์ก่อนครับ");
                return;
            }

            if (confirm(`ยืนยันเปิดสิทธิ์คัดกรองประชากรที่เลือกจำนวน ${cids.length} ราย?`)) {
                fetch('../api/activate_suspect_targets.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            cids: cids
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert(data.message);
                            selectedCids.clear(); // ล้างรายการที่เลือก
                            fetchData(); // Refresh lists
                        } else {
                            alert("เกิดข้อผิดพลาด: " + data.message);
                        }
                    })
                    .catch(err => alert("เกิดข้อผิดพลาดในการเชื่อมต่อ"));
            }
        }

        function cancelAssignment(cid, name, assignmentId = 0) {
            if (!confirm(`ยืนยันยกเลิกการมอบหมายงานของ\n"${name}"\n\nรายชื่อนี้จะกลับสู่สถานะ "ยังไม่มอบหมาย" และ อสม. จะไม่เห็นงานนี้อีกต่อไป`)) {
                return;
            }

            fetch('../api/cancel_assignment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        cid: cid,
                        assignment_id: assignmentId
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        selectedCids.delete(cid); // ลบจากรายการที่เลือก (ถ้ามี)
                        fetchData(); // refresh รายการ
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + (data.message || 'ไม่ทราบสาเหตุ'));
                    }
                })
                .catch(() => alert('เกิดข้อผิดพลาดในการเชื่อมต่อ'));
        }

        // Modal Logic
        function openManualModal() {
            const moo = document.getElementById('moo').value;
            if (!moo) {
                alert("กรุณาเลือกตำบลและหมู่บ้านก่อนเพิ่มข้อมูลครับ");
                return;
            }
            document.getElementById('manual-modal').style.display = 'flex';
        }

        function closeManualModal() {
            document.getElementById('manual-modal').style.display = 'none';
            document.getElementById('manual-form').reset();
        }

        function saveManualTarget(e) {
            e.preventDefault();

            const tambon = document.getElementById('tambon').value;
            const moo = document.getElementById('moo').value;
            let hoscode = '';
            if (tambonData[tambon].hasSubUnits) {
                hoscode = document.getElementById('hoscode').value;
            } else {
                hoscode = tambonData[tambon].hoscode;
            }
            const vhidCode = tambon + moo.padStart(2, '0');

            const payload = {
                cid: document.getElementById('m_cid').value,
                first_name: document.getElementById('m_fname').value,
                last_name: document.getElementById('m_lname').value,
                sex: document.getElementById('m_sex').value,
                birth: document.getElementById('m_birth').value,
                house_no: document.getElementById('m_house').value,
                moo: moo,
                sub_district_code: tambon,
                vhid_code: vhidCode,
                hoscode: hoscode,
                need_screen_dm: document.getElementById('m_dm').checked ? 1 : 0,
                need_screen_ht: document.getElementById('m_ht').checked ? 1 : 0
            };

            fetch('../api/save_manual_target.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert("บันทึกข้อมูลสำเร็จ");
                        closeManualModal();
                        fetchData(); // Refresh target list
                    } else {
                        alert("ข้อผิดพลาด: " + data.message);
                    }
                })
                .catch(err => alert("เกิดข้อผิดพลาดในการเชื่อมต่อ"));
        }

        // Sub-admin automatic scoping
        const loggedAdminHoscode = "<?= $admin_hoscode ?: '' ?>";
        const isDemoMode = <?= DemoDataProvider::isDemoMode() ? 'true' : 'false' ?>;

        window.addEventListener('DOMContentLoaded', () => {
            if (isDemoMode) {
                const tSelect = document.getElementById('tambon');
                if (tSelect && tSelect.options.length > 1) {
                    tSelect.selectedIndex = 1;
                    onTambonChange();
                    const mSelect = document.getElementById('moo');
                    if (mSelect && mSelect.options.length > 1) {
                        mSelect.selectedIndex = 1;
                        fetchData();
                    }
                }
            } else if (loggedAdminHoscode) {
                let targetTambon = "";
                let targetSubUnit = "";

                // Find matching tambon for the logged in hoscode
                for (let t in tambonData) {
                    if (tambonData[t].hasSubUnits) {
                        if (tambonData[t].subUnits[loggedAdminHoscode]) {
                            targetTambon = t;
                            targetSubUnit = loggedAdminHoscode;
                            break;
                        }
                    } else {
                        if (tambonData[t].hoscode === loggedAdminHoscode) {
                            targetTambon = t;
                            break;
                        }
                    }
                }

                if (targetTambon) {
                    const tSelect = document.getElementById('tambon');
                    tSelect.value = targetTambon;
                    tSelect.style.pointerEvents = 'none';
                    tSelect.style.backgroundColor = 'var(--bg-darker)';
                    onTambonChange();

                    if (targetSubUnit) {
                        const hSelect = document.getElementById('hoscode');
                        hSelect.value = targetSubUnit;
                        hSelect.style.pointerEvents = 'none';
                        hSelect.style.backgroundColor = 'var(--bg-darker)';
                        onHoscodeChange();
                    }
                }
            }
        });

        // Modal VHV Tasks logic
        let modalVhvTasks = [];
        let currentVhvFilter = 'all';

        function openVhvTasksModal(vhvId, vhvName) {
            document.getElementById('vhv-modal-title').innerText = `📋 ภาระงาน อสม. ${vhvName}`;
            document.getElementById('vhv-modal-subtitle').innerText = `กำลังดึงรายการงานคัดกรอง NCD และงานติดตาม DPAC...`;
            document.getElementById('vhv-tasks-modal').style.display = 'flex';
            document.getElementById('vhv-modal-task-body').innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">⏳ กำลังโหลดรายการงาน...</div>';
            
            modalVhvTasks = [];
            currentVhvFilter = 'all';
            updateVhvFilterTabs();

            fetch(`../api/get_vhv_tasks.php?vhv_id=${vhvId}`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        modalVhvTasks = res.tasks || [];
                        document.getElementById('vhv-modal-subtitle').innerText = `อสม. ${res.vhv_name} | ใบงานทั้งหมด ${modalVhvTasks.length} รายการ`;
                        renderVhvModalTasks();
                    } else {
                        document.getElementById('vhv-modal-task-body').innerHTML = `<div style="text-align: center; color: var(--color-red); padding: 40px;">เกิดข้อผิดพลาด: ${res.message || 'ไม่สามารถโหลดข้อมูลได้'}</div>`;
                    }
                })
                .catch(() => {
                    document.getElementById('vhv-modal-task-body').innerHTML = '<div style="text-align: center; color: var(--color-red); padding: 40px;">เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย</div>';
                });
        }

        function closeVhvTasksModal() {
            document.getElementById('vhv-tasks-modal').style.display = 'none';
        }

        function switchVhvTaskFilter(filter) {
            currentVhvFilter = filter;
            updateVhvFilterTabs();
            renderVhvModalTasks();
        }

        function updateVhvFilterTabs() {
            ['all', 'pending', 'completed', 'skipped'].forEach(f => {
                const btn = document.getElementById(`vhv-filter-${f}`);
                if (btn) btn.classList.toggle('active', currentVhvFilter === f);
            });
        }

        function renderVhvModalTasks() {
            const container = document.getElementById('vhv-modal-task-body');
            
            const pendingCount = modalVhvTasks.filter(t => t.assignment_status === 'pending').length;
            const completedCount = modalVhvTasks.filter(t => t.assignment_status === 'completed').length;
            const skippedCount = modalVhvTasks.filter(t => t.assignment_status === 'skipped').length;

            document.getElementById('vhv-count-all').innerText = modalVhvTasks.length;
            document.getElementById('vhv-count-pending').innerText = pendingCount;
            document.getElementById('vhv-count-completed').innerText = completedCount;
            document.getElementById('vhv-count-skipped').innerText = skippedCount;
            document.getElementById('vhv-modal-summary').innerText = `ค้าง ${pendingCount} | สำเร็จ ${completedCount} | ข้าม ${skippedCount} | รวม ${modalVhvTasks.length} รายการ`;

            const filtered = modalVhvTasks.filter(t => {
                if (currentVhvFilter === 'pending') return t.assignment_status === 'pending';
                if (currentVhvFilter === 'completed') return t.assignment_status === 'completed';
                if (currentVhvFilter === 'skipped') return t.assignment_status === 'skipped';
                return true;
            });

            if (filtered.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">ไม่พบรายการงานตามเงื่อนไขที่เลือก</div>';
                return;
            }

            let html = `
                <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: var(--bg-card); text-align: left;">
                            <th style="padding: 8px 10px;">ประเภท</th>
                            <th style="padding: 8px 10px;">ชื่อ-นามสกุล</th>
                            <th style="padding: 8px 10px;">บ้าน/หมู่</th>
                            <th style="padding: 8px 10px; text-align: center;">รอบ</th>
                            <th style="padding: 8px 10px;">สถานะ</th>
                            <th style="padding: 8px 10px;">ผลการตรวจ / หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            filtered.forEach(t => {
                const typeLabel = t.task_type === 'dpac' 
                    ? '<span style="background: rgba(168,85,247,0.15); color: #9333ea; padding: 2px 8px; border-radius: 10px; font-weight: bold; font-size: 11px;">🏃 DPAC</span>'
                    : '<span style="background: rgba(59,130,246,0.15); color: #2563eb; padding: 2px 8px; border-radius: 10px; font-weight: bold; font-size: 11px;">📋 NCD</span>';

                let statusHtml = '';
                if (t.assignment_status === 'completed') {
                    statusHtml = '<span style="color: var(--color-green); font-weight: bold;">✅ คัดกรองแล้ว</span>';
                } else if (t.assignment_status === 'skipped') {
                    statusHtml = '<span style="color: var(--color-red); font-weight: bold;">❌ ข้ามเคส</span>';
                } else {
                    statusHtml = '<span style="color: var(--color-yellow, #f59e0b); font-weight: bold;">⏳ รอดำเนินการ</span>';
                }

                let detailHtml = '-';
                if (t.assignment_status === 'completed') {
                    const bpText = (t.sys_bp1 && t.dia_bp1) ? `BP: <strong>${t.sys_bp1}/${t.dia_bp1}</strong>` : '';
                    const dtxText = t.dtx_value ? `DTX: <strong>${t.dtx_value}</strong>` : '';
                    const parts = [bpText, dtxText].filter(Boolean);
                    detailHtml = parts.length > 0 ? parts.join(' | ') : 'บันทึกสำเร็จ';
                } else if (t.assignment_status === 'skipped') {
                    detailHtml = t.skipped_reason ? `<span style="color: var(--text-muted); font-size: 12px;">${t.skipped_reason}</span>` : 'ข้ามเคส';
                } else {
                    detailHtml = '<span style="color: var(--text-muted); font-size: 12px;">รอดำเนินการคัดกรอง</span>';
                }

                html += `
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 8px;">${typeLabel}</td>
                        <td style="padding: 10px 8px; font-weight: bold; color: var(--text-primary);">${t.first_name} ${t.last_name}</td>
                        <td style="padding: 10px 8px;">${t.house_no} ม.${t.moo}</td>
                        <td style="padding: 10px 8px; text-align: center; font-weight: bold;">${t.round_number || 1}</td>
                        <td style="padding: 10px 8px;">${statusHtml}</td>
                        <td style="padding: 10px 8px;">${detailHtml}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }
    </script>
</body>

</html>
