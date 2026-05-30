<?php
// admin/assignment.php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
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

        .item-row:hover {
            transform: translateY(-2px);
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
        <h2
            style="color: var(--color-accent); margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
            ระบบมอบหมายงานคัดกรอง (Smart Assignment Manager)
        </h2>

        <!-- Step 1: Filters -->
        <div class="filter-card">
            <h4 style="margin-top: 0; margin-bottom: 16px; color: var(--text-primary);">1. เลือกเขตรับผิดชอบ</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                <div>
                    <label class="form-label">ตำบล</label>
                    <select id="tambon" class="form-select" onchange="onTambonChange()">
                        <option value="">-- เลือกตำบล --</option>
                        <option value="341801">ตาลสุม</option>
                        <option value="341802">สำโรง</option>
                        <option value="341803">จิกเทิง</option>
                        <option value="341804">หนองกุง</option>
                        <option value="341805">นาคาย</option>
                        <option value="341806">คำหว้า</option>
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
                    <p>👉 เลือกประชากรทางซ้ายมือ และกดปุ่ม <b>"มอบหมาย"</b> ที่ อสม. ด้านล่างนี้</p>
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
                เพิ่มประชากรเป้าหมาย (แมนนวล)</h3>
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
            </form>
        </div>
    </div>

    <!-- Load Tambon Data & Scripts -->
    <script>
        // Data logic from register.php
        const tambonData = {
            "341801": {
                hasSubUnits: true,
                subUnits: {
                    "10957": {
                        name: "โรงพยาบาลตาลสุม (กลุ่มงานบริการด้านปฐมภูมิ)",
                        villages: [
                            { moo: 1, name: "บ้านม่วงโคน" },
                            { moo: 2, name: "บ้านดอนรังกา" },
                            { moo: 3, name: "บ้านนาห้วยแคน (เขตเทศบาล)" },
                            { moo: 5, name: "บ้านนามน (เขตเทศบาล)" },
                            { moo: 10, name: "บ้านนามน (เขตเทศบาล)" },
                            { moo: 11, name: "บ้านตาลสุม (เขตเทศบาล)" },
                            { moo: 12, name: "บ้านคำไม้ตาย" },
                            { moo: 13, name: "บ้านปากเซ" }
                        ]
                    },
                    "03751": {
                        name: "รพ.สต. ดอนพันชาด",
                        villages: [
                            { moo: 4, name: "บ้านดอนพันชาด" },
                            { moo: 6, name: "บ้านดอนตะลี" },
                            { moo: 7, name: "บ้านปากห้วย" },
                            { moo: 8, name: "บ้านโนนค้อ" },
                            { moo: 9, name: "บ้านแก่งกบ" },
                            { moo: 14, name: "บ้านโนนสวรรค์" },
                            { moo: 15, name: "บ้านทุ่งเจริญ" }
                        ]
                    }
                }
            },
            "341802": {
                hasSubUnits: false,
                hoscode: "03752",
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
            "341803": {
                hasSubUnits: false,
                hoscode: "03753",
                villages: [
                    { moo: 1, name: "บ้านจิกเทิง" },
                    { moo: 2, name: "บ้านจิกลุ่ม" },
                    { moo: 3, name: "บ้านเชียงแก้ว" },
                    { moo: 4, name: "บ้านเชียงแก้ว" },
                    { moo: 5, name: "บ้านดอนโด่ (บ้านดอนโต)" },
                    { moo: 6, name: "บ้านดอนยูง" },
                    { moo: 7, name: "บ้านค้อ" },
                    { moo: 8, name: "บ้านดอนแป้นลม" },
                    { moo: 9, name: "บ้านสร้างคำ" }
                ]
            },
            "341804": {
                hasSubUnits: false,
                hoscode: "03754",
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
            "341805": {
                hasSubUnits: true,
                subUnits: {
                    "03755": {
                        name: "รพ.สต. นาคาย",
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
                        name: "รพ.สต. บ้านคำหนามแท่ง",
                        villages: [
                            { moo: 7, name: "บ้านโคกคล้าย" },
                            { moo: 8, name: "บ้านคำหนามแท่ง" },
                            { moo: 9, name: "บ้านคำผักหนอก" },
                            { moo: 10, name: "บ้านคำฮี" },
                            { moo: 11, name: "บ้านห่องแดง" },
                            { moo: 12, name: "บ้านโนนสำราญ" },
                            { moo: 13, name: "บ้านโนนเจริญ" }
                        ]
                    }
                }
            },
            "341806": {
                hasSubUnits: false,
                hoscode: "03757",
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

        function onTambonChange() {
            const tCode = document.getElementById('tambon').value;
            const hContainer = document.getElementById('hoscode_container');
            const hSelect = document.getElementById('hoscode');
            const mSelect = document.getElementById('moo');

            hSelect.innerHTML = '<option value="">-- เลือกหน่วยบริการ --</option>';
            mSelect.innerHTML = '<option value="">-- เลือกพื้นที่ก่อน --</option>';
            hContainer.style.display = 'none';

            if (!tCode) { fetchData(); return; }

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

        function switchTargetGroup(group) {
            currentTargetGroup = group;

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
            fetch(`../api/get_assignment_data.php?type=vhvs&moo=${moo}&vhid=${vhidCode}&hoscode=${hoscode}`)
                .then(r => r.json())
                .then(data => {
                    renderVhvs(data);
                });
        }

        function renderTargets() {
            const list = document.getElementById('target-list');
            document.getElementById('target-count').innerText = `พบ ${currentTargets.length} ราย`;
            updateSelectedCount();

            if (currentTargets.length === 0) {
                list.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">ไม่พบประชากรเป้าหมายในพื้นที่นี้</div>';
                return;
            }

            let html = '';
            currentTargets.forEach(t => {
                const assignedText = t.assigned_vhv ? `<span style="color: var(--color-green); font-size: 12px; font-weight: bold;">(มอบหมายแล้ว: ${t.assigned_vhv})</span>` : '<span style="color: var(--color-yellow); font-size: 12px; font-weight: bold;">(ยังไม่มอบหมาย)</span>';

                html += `
                    <div class="item-row">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <input type="checkbox" class="target-checkbox item-cb" value="${t.cid}" onchange="updateSelectedCount()">
                            <div class="item-info">
                                <h4>${t.first_name} ${t.last_name}</h4>
                                <p>บ้านเลขที่: ${t.house_no} | อายุ: ${t.age} ปี</p>
                            </div>
                        </div>
                        <div>${assignedText}</div>
                    </div>
                `;
            });
            list.innerHTML = html;
            document.getElementById('select-all').checked = false;
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
                            <p>ใบงานปัจจุบัน: ${v.task_count} งาน</p>
                        </div>
                        <button onclick="assignTasks('${v.vhv_id}')" class="assign-btn">
                            มอบหมาย
                        </button>
                    </div>
                `;
            });
            list.innerHTML = html;
        }

        function toggleSelectAll() {
            const isChecked = document.getElementById('select-all').checked;
            document.querySelectorAll('.item-cb').forEach(cb => cb.checked = isChecked);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const count = document.querySelectorAll('.item-cb:checked').length;
            document.getElementById('selected-count').innerText = `เลือก ${count} คน`;
        }

        function assignTasks(vhvId) {
            const cids = Array.from(document.querySelectorAll('.item-cb:checked')).map(cb => cb.value);
            if (cids.length === 0) {
                alert("กรุณาเลือกประชากรเป้าหมายฝั่งซ้ายมือก่อนครับ");
                return;
            }

            if (confirm(`ยืนยันมอบหมายงาน ${cids.length} ราย ให้ อสม. ท่านนี้?`)) {
                fetch('../api/assign_tasks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ vhv_id: vhvId, target_cids: cids })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert("มอบหมายงานสำเร็จ!");
                            fetchData(); // Refresh lists
                        } else {
                            alert("เกิดข้อผิดพลาด: " + data.message);
                        }
                    })
                    .catch(err => alert("เกิดข้อผิดพลาดในการเชื่อมต่อ"));
            }
        }

        function activateSuspects() {
            const cids = Array.from(document.querySelectorAll('.item-cb:checked')).map(cb => cb.value);
            if (cids.length === 0) {
                alert("กรุณาเลือกประชากรป่วย/สงสัยป่วยที่ต้องการเปิดสิทธิ์ก่อนครับ");
                return;
            }

            if (confirm(`ยืนยันเปิดสิทธิ์คัดกรองประชากรที่เลือกจำนวน ${cids.length} ราย?`)) {
                fetch('../api/activate_suspect_targets.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ cids: cids })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert(data.message);
                            fetchData(); // Refresh lists
                        } else {
                            alert("เกิดข้อผิดพลาด: " + data.message);
                        }
                    })
                    .catch(err => alert("เกิดข้อผิดพลาดในการเชื่อมต่อ"));
            }
        }

        // Modal Logic
        function openManualModal() {
            const moo = document.getElementById('moo').value;
            if (!moo) { alert("กรุณาเลือกตำบลและหมู่บ้านก่อนเพิ่มข้อมูลครับ"); return; }
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
                headers: { 'Content-Type': 'application/json' },
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
        window.addEventListener('DOMContentLoaded', () => {
            if (loggedAdminHoscode) {
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
    </script>
</body>

</html>