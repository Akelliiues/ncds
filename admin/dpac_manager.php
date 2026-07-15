<?php
// admin/dpac_manager.php
require_once __DIR__ . '/../config/session.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

// Fetch dynamic sub-districts and units (same as assignment.php)
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
    <title>Smart DPAC Manager - NCDs Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-main);
        }

        .grid-container {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
            margin-top: 20px;
        }

        @media (max-width: 992px) {
            .grid-container {
                grid-template-columns: 1fr;
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
            height: 600px;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
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

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Custom Checkbox */
        .target-checkbox {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: var(--color-accent);
        }

        .vhv-row.selected {
            background-color: var(--bg-darker) !important;
            border-left: 4px solid var(--color-green) !important;
            box-shadow: var(--neumorph-inset) !important;
        }

        .assign-btn {
            background: var(--color-green);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 24px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .assign-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            background: #059669;
        }

        .assign-btn:disabled {
            background: #cbd5e1;
            color: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-cancel {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .btn-cancel:hover {
            background-color: #ef4444;
            color: white;
        }
    </style>
</head>
<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="color: var(--color-accent); margin-bottom: 5px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
            ระบบมอบหมายงานติดตามโครงการปรับเปลี่ยนพฤติกรรม (Smart DPAC Manager)
        </h2>
        <p style="color: var(--text-secondary); margin-bottom: 25px; font-size: 15px;">บริหารจัดการ อสม. ผู้ติดตามผลลัพธ์พฤติกรรมกลุ่มเสี่ยงเบาหวาน/ความดัน (DPAC)</p>

        <!-- Step 1: Select Responsibility -->
        <div class="filter-card">
            <h4 style="margin-top: 0; margin-bottom: 16px; color: var(--text-primary);">1. เลือกเขตรับผิดชอบ</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; min-width: 0;">
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

        <!-- Step 2: Lists Grid -->
        <div class="grid-container">
            <!-- Left: Enrolled Targets -->
            <div class="list-card">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                    <div>
                        <h3 style="margin: 0; color: var(--text-primary);">ผู้เข้าร่วมโครงการปรับพฤติกรรม (DPAC)</h3>
                        <span style="font-size: 12.5px; color: var(--text-muted);" id="target-count">พบ 0 ราย</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="select-all" class="target-checkbox" onchange="toggleSelectAll(this)">
                        <label for="select-all" style="font-size: 13.5px; font-weight: bold; cursor: pointer; color: var(--text-primary);">เลือกทั้งหมด</label>
                    </div>
                </div>

                <!-- Live Search Bar -->
                <div style="margin-top: 12px;">
                    <input type="text" id="search-target" placeholder="🔍 พิมพ์ชื่อ-นามสกุล หรือบ้านเลขที่เพื่อค้นหา..." oninput="onSearchInput()"
                        style="width: 100%; padding: 10px 14px; border-radius: 12px; border: 1px solid var(--border-color); background-color: var(--bg-main); color: var(--text-primary); font-size: 14px; box-sizing: border-box; box-shadow: var(--neumorph-inset); outline: none;">
                </div>

                <div class="list-body" id="target-list">
                    <div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>
                </div>
            </div>

            <!-- Right: VHVs list with workloads -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="list-card" style="height: 480px;">
                    <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                        <h3 style="margin: 0; color: var(--text-primary);">รายชื่อ อสม. ในพื้นที่</h3>
                        <span style="font-size: 12.5px; color: var(--text-muted);" id="vhv-count">พบ 0 ราย</span>
                    </div>

                    <div class="list-body" id="vhv-list">
                        <div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>
                    </div>
                </div>

                <!-- Assignment Card -->
                <div class="filter-card" style="margin-bottom: 0;">
                    <h4 style="margin-top: 0; margin-bottom: 12px; color: var(--text-primary);">จัดการมอบหมายงาน</h4>
                    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
                        เลือกรายชื่อผู้รับบริการจากคอลัมน์ด้านซ้าย และคลิกเลือก อสม. จากด้านบนเพื่อมอบหมายงานติดตามผลพฤติกรรมรอบถัดไป
                    </p>
                    <div style="margin-bottom: 14px; font-size: 14px; font-weight: bold; color: var(--color-accent);" id="selected-summary">
                        เลือกแล้ว 0 ราย
                    </div>
                    <button class="assign-btn" id="assign-btn" disabled onclick="assignDpacTasks()">
                        🏃 มอบหมายงานติดตาม (Follow-up)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const tambonData = <?= json_encode($jsData, JSON_UNESCAPED_UNICODE) ?>;
        let currentTargets = [];
        let selectedEnrollmentIds = new Set();
        let selectedVhvId = null;

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

        function fetchData() {
            selectedEnrollmentIds.clear();
            selectedVhvId = null;
            document.getElementById('select-all').checked = false;
            updateSelectedCount();

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
            fetch(`../api/get_dpac_data.php?type=targets&moo=${moo}&vhid=${vhidCode}&hoscode=${hoscode}`)
                .then(r => r.json())
                .then(data => {
                    currentTargets = data;
                    renderTargets();
                })
                .catch(() => {
                    document.getElementById('target-list').innerHTML = '<div style="text-align: center; color: var(--color-red); padding: 40px;">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
                });

            // Fetch VHVs
            fetch(`../api/get_dpac_data.php?type=vhvs&moo=${moo}&vhid=${vhidCode}&hoscode=${hoscode}`)
                .then(r => r.json())
                .then(data => {
                    renderVhvs(data);
                })
                .catch(() => {
                    document.getElementById('vhv-list').innerHTML = '<div style="text-align: center; color: var(--color-red); padding: 40px;">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
                });
        }

        function onSearchInput() {
            renderTargets();
        }

        function renderTargets() {
            const list = document.getElementById('target-list');
            const searchVal = (document.getElementById('search-target')?.value || '').trim().toLowerCase();

            const filteredTargets = currentTargets.filter(t => {
                if (!searchVal) return true;
                const fullName = `${t.first_name} ${t.last_name}`.toLowerCase();
                const houseNo = (t.house_no || '').toString().toLowerCase();
                const cid = (t.cid || '').toString().toLowerCase();
                return fullName.includes(searchVal) || houseNo.includes(searchVal) || cid.includes(searchVal);
            });

            if (filteredTargets.length === 0) {
                list.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">ไม่พบผู้เข้าร่วมโครงการในพื้นที่</div>';
                document.getElementById('target-count').innerText = 'พบ 0 ราย';
                return;
            }

            document.getElementById('target-count').innerText = `พบ ${filteredTargets.length} ราย`;

            list.innerHTML = filteredTargets.map(t => {
                const isChecked = selectedEnrollmentIds.has(t.enrollment_id.toString()) ? 'checked' : '';
                const vhvLabel = t.assigned_vhv 
                    ? `<span class="badge" style="background-color: rgba(34, 197, 94, 0.15); color: #22c55e;">อสม. ${t.assigned_vhv} (รอบติดตาม: ${t.total_rounds})</span>` 
                    : '<span class="badge" style="background-color: rgba(239, 68, 68, 0.15); color: #ef4444;">ยังไม่มี อสม. รับผิดชอบ</span>';

                return `
                    <div class="item-row">
                        <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                            <input type="checkbox" class="target-checkbox" value="${t.enrollment_id}" ${isChecked} onchange="toggleSelectTarget(this, '${t.enrollment_id}')">
                            <div class="item-info">
                                <h4>${t.first_name} ${t.last_name} <span style="font-size:12px; color: var(--text-muted); font-weight:normal;">(${t.cid})</span></h4>
                                <p>บ้านเลขที่: ${t.house_no} | หมู่ที่: ${t.moo} | กลุ่มพฤติกรรม: <strong style="color:var(--color-red);">${t.risk_type}</strong></p>
                                <div style="margin-top: 6px;">${vhvLabel}</div>
                            </div>
                        </div>
                        <button onclick="cancelDpacEnrollment('${t.enrollment_id}', '${t.first_name} ${t.last_name}')" class="btn-cancel" style="margin: 0; padding: 6px 12px; border-radius: 12px; font-size: 12px; font-weight:bold;">
                            ยกเลิกเข้าร่วม
                        </button>
                    </div>
                `;
            }).join('');
        }

        function renderVhvs(data) {
            const list = document.getElementById('vhv-list');
            if (!data || data.length === 0) {
                list.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">ไม่พบ อสม. ในเขตรับผิดชอบ</div>';
                document.getElementById('vhv-count').innerText = 'พบ 0 ราย';
                return;
            }

            document.getElementById('vhv-count').innerText = `พบ ${data.length} ราย`;

            list.innerHTML = data.map(v => {
                const isSelected = selectedVhvId === v.vhv_id ? 'selected' : '';
                return `
                    <div class="item-row vhv-row ${isSelected}" onclick="selectVhv('${v.vhv_id}')" style="cursor: pointer; border-left: 4px solid transparent; transition: all 0.2s;">
                        <div class="item-info">
                            <h4>อสม. ${v.vhv_name}</h4>
                            <p style="font-size:12.5px; color:var(--text-secondary); margin-top:2px;">
                                📋 งานคัดกรอง: <strong style="color:var(--color-accent);">${v.pending_screen_count}</strong> งาน | 
                                🏃 ติดตาม DPAC: <strong style="color:var(--color-green);">${v.pending_dpac_count}</strong> งาน
                            </p>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function toggleSelectTarget(checkbox, id) {
            if (checkbox.checked) {
                selectedEnrollmentIds.add(id.toString());
            } else {
                selectedEnrollmentIds.delete(id.toString());
                document.getElementById('select-all').checked = false;
            }
            updateSelectedCount();
        }

        function toggleSelectAll(master) {
            const checkboxes = document.querySelectorAll('#target-list .target-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = master.checked;
                if (master.checked) {
                    selectedEnrollmentIds.add(cb.value.toString());
                } else {
                    selectedEnrollmentIds.delete(cb.value.toString());
                }
            });
            updateSelectedCount();
        }

        function selectVhv(vhvId) {
            selectedVhvId = vhvId;
            // Rerender VHV list using current DOM elements to maintain scroll
            const rows = document.querySelectorAll('#vhv-list .vhv-row');
            const dataVhvIds = [];
            
            // Re-fetch existing data state from DOM elements
            rows.forEach((row, idx) => {
                row.classList.remove('selected');
                const onclickStr = row.getAttribute('onclick');
                const match = onclickStr.match(/'([^']+)'/);
                if (match && match[1] === vhvId) {
                    row.classList.add('selected');
                }
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const count = selectedEnrollmentIds.size;
            document.getElementById('selected-summary').innerText = `เลือกแล้ว ${count} ราย`;
            
            const assignBtn = document.getElementById('assign-btn');
            if (count > 0 && selectedVhvId !== null) {
                assignBtn.disabled = false;
            } else {
                assignBtn.disabled = true;
            }
        }

        function assignDpacTasks() {
            const enrollIds = Array.from(selectedEnrollmentIds);
            if (enrollIds.length === 0) {
                alert("กรุณาเลือกผู้เข้าร่วมโครงการที่ต้องการมอบหมายงานก่อนครับ");
                return;
            }
            if (!selectedVhvId) {
                alert("กรุณาเลือก อสม. เพื่อมอบหมายงานก่อนครับ");
                return;
            }

            if (confirm(`ยืนยันมอบหมายงานติดตามโครงการ DPAC จำนวน ${enrollIds.length} ราย ให้กับ อสม. ที่ระบุ?`)) {
                const btn = document.getElementById('assign-btn');
                btn.disabled = true;
                btn.innerText = '⏳ กำลังมอบหมายงาน...';

                fetch('../api/assign_dpac.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        vhv_id: selectedVhvId,
                        enrollment_ids: enrollIds
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert("มอบหมายงานเรียบร้อยแล้ว!");
                        selectedEnrollmentIds.clear();
                        fetchData(); // Reload lists
                    } else {
                        alert("เกิดข้อผิดพลาด: " + data.message);
                        btn.disabled = false;
                        btn.innerText = '🏃 มอบหมายงานติดตาม (Follow-up)';
                    }
                })
                .catch(() => {
                    alert("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
                    btn.disabled = false;
                    btn.innerText = '🏃 มอบหมายงานติดตาม (Follow-up)';
                });
            }
        }

        function cancelDpacEnrollment(enrollmentId, name) {
            if (confirm(`⚠️ ยืนยันยกเลิกการเข้าร่วมโครงการ DPAC ของ "${name}" ใช่หรือไม่?\n\nการดำเนินการนี้จะลบข้อมูลประวัติการติดตามผลทั้งหมดที่เกี่ยวข้องอย่างถาวร!`)) {
                fetch('../api/cancel_dpac.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ enrollment_id: enrollmentId })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert("ยกเลิกการเข้าร่วมโครงการเรียบร้อยแล้ว");
                        selectedEnrollmentIds.delete(enrollmentId.toString());
                        fetchData(); // Reload lists
                    } else {
                        alert("เกิดข้อผิดพลาด: " + data.message);
                    }
                })
                .catch(() => {
                    alert("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
                });
            }
        }
    </script>
</body>
</html>