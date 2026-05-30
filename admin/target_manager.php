<?php
// admin/target_manager.php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

// Handle API requests
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'get_targets') {
        header('Content-Type: application/json');
        $hoscode = $_GET['hoscode'] ?? '';
        $moo = $_GET['moo'] ?? '';
        $status = $_GET['status'] ?? 'all';

        if (!$hoscode || !$moo) {
            echo json_encode([]);
            exit;
        }

        try {
            $pdo->exec("ALTER TABLE target_population ADD COLUMN prefix VARCHAR(50) NULL AFTER pid");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE target_population ADD COLUMN is_manual TINYINT(1) DEFAULT 0 AFTER need_screen_ht");
        } catch (Exception $e) {
        }

        $sql = "SELECT cid, prefix, first_name, last_name, birth, house_no, TIMESTAMPDIFF(YEAR, birth, CURDATE()) as age, need_screen_dm, need_screen_ht, health_status_origin, is_manual 
                FROM target_population 
                WHERE LPAD(hoscode, 5, '0') = LPAD(?, 5, '0') AND moo = ?";

        $params = [$hoscode, $moo];

        if ($status === 'target') {
            $sql .= " AND (need_screen_dm = 1 OR need_screen_ht = 1)";
        } elseif ($status === 'non_target') {
            $sql .= " AND (need_screen_dm = 0 AND need_screen_ht = 0)";
        }

        $sql .= " ORDER BY CAST(house_no AS UNSIGNED) ASC, house_no ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'add_manual') {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        $cid = $data['cid'] ?? '';
        $prefix = $data['prefix'] ?? '';
        $fname = $data['fname'] ?? '';
        $lname = $data['lname'] ?? '';
        $birth_formatted = $data['birth_formatted'] ?? date('Y-m-d');
        $house_no = $data['house_no'] ?? '';
        $dm = $data['need_dm'] ? 1 : 0;
        $ht = $data['need_ht'] ? 1 : 0;
        $tambon = $data['tambon'] ?? '';
        $moo = $data['moo'] ?? '';
        $hoscode = $data['hoscode'] ?? '';

        $birth = $birth_formatted;
        $hid = '';
        $pid = '';
        $sex = '1';

        $moo_str = str_pad($moo, 2, '0', STR_PAD_LEFT);
        $vhid_code = $tambon . $moo_str;

        try {
            try {
                $pdo->exec("ALTER TABLE target_population ADD COLUMN prefix VARCHAR(50) NULL AFTER pid");
            } catch (Exception $e) {
            }
            try {
                $pdo->exec("ALTER TABLE target_population ADD COLUMN is_manual TINYINT(1) DEFAULT 0 AFTER need_screen_ht");
            } catch (Exception $e) {
            }

            if ($dm == 1 && $ht == 1) {
                $origin = 'BOTH';
            } elseif ($dm == 1) {
                $origin = 'DM_ONLY';
            } elseif ($ht == 1) {
                $origin = 'HT_ONLY';
            } else {
                $origin = 'NORMAL';
            }

            $check = $pdo->prepare("SELECT cid FROM target_population WHERE cid = ?");
            $check->execute([$cid]);
            if ($check->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE target_population SET prefix = ?, first_name = ?, last_name = ?, birth = ?, house_no = ?, need_screen_dm = ?, need_screen_ht = ?, health_status_origin = ?, is_manual = 1, updated_at = NOW() WHERE cid = ?");
                $stmt->execute([$prefix, $fname, $lname, $birth, $house_no, $dm, $ht, $origin, $cid]);
            } else {
                $sql = "INSERT INTO target_population 
                        (cid, hid, pid, prefix, first_name, last_name, sex, birth, house_no, moo, sub_district_code, vhid_code, hoscode, health_status_origin, need_screen_dm, need_screen_ht, is_manual) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $cid,
                    $hid,
                    $pid,
                    $prefix,
                    $fname,
                    $lname,
                    $sex,
                    $birth,
                    $house_no,
                    $moo,
                    $tambon,
                    $vhid_code,
                    $hoscode,
                    $origin,
                    $dm,
                    $ht
                ]);
            }
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'update_targets') {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $cids = $data['cids'] ?? [];
        $dm = $data['need_dm'] ? 1 : 0;
        $ht = $data['need_ht'] ? 1 : 0;

        if (empty($cids)) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีรายชื่อที่เลือก']);
            exit;
        }

        $inQuery = implode(',', array_fill(0, count($cids), '?'));
        $sql = "UPDATE target_population SET need_screen_dm = ?, need_screen_ht = ?, updated_at = NOW() WHERE cid IN ($inQuery)";
        $stmt = $pdo->prepare($sql);

        $params = array_merge([$dm, $ht], $cids);
        try {
            $stmt->execute($params);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการกลุ่มเป้าหมาย (Target Manager) - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-main);
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

        .target-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--color-accent);
        }

        .badge {
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-dm {
            background: rgba(244, 63, 94, 0.2);
            color: #f43f5e;
            border: 1px solid #f43f5e;
        }

        .badge-ht {
            background: rgba(56, 189, 248, 0.2);
            color: #38bdf8;
            border: 1px solid #38bdf8;
        }

        .badge-none {
            background: rgba(156, 163, 175, 0.2);
            color: #9ca3af;
            border: 1px solid #9ca3af;
        }
    </style>
</head>

<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2
            style="color: var(--color-accent); margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
            ระบบจัดการกลุ่มเป้าหมายคัดกรอง (Target Manager)
        </h2>

        <!-- Step 1: Filters -->
        <div class="filter-card">
            <h4 style="margin-top: 0; margin-bottom: 16px; color: var(--text-primary);">ตัวกรองพื้นที่และกลุ่มประชากร
            </h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px;">
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
                <div>
                    <label class="form-label">สถานะกลุ่มเป้าหมาย</label>
                    <select id="status_filter" class="form-select" onchange="fetchData()">
                        <option value="all">ทั้งหมด</option>
                        <option value="target">เป็นกลุ่มเป้าหมายแล้ว</option>
                        <option value="non_target">ยังไม่ถูกตั้งเป็นเป้าหมาย</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Action Panel -->
        <div class="filter-card" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <label
                    style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); font-weight: bold;">
                    <input type="checkbox" id="select-all" class="target-checkbox" onchange="toggleSelectAll()">
                    เลือกทั้งหมด
                </label>
                <span id="selected-count" style="color: var(--color-accent); font-weight: bold;">เลือก 0 คน</span>
            </div>
            <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <label
                    style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; color: var(--text-primary); cursor: pointer; font-size: 14px;">
                    <input type="checkbox" id="set_dm" checked
                        style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--color-accent);">
                    เป้าหมายเบาหวาน (DM)
                </label>
                <label
                    style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; color: var(--text-primary); cursor: pointer; font-size: 14px;">
                    <input type="checkbox" id="set_ht" checked
                        style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--color-accent);">
                    เป้าหมายความดัน (HT)
                </label>
                <button onclick="updateTargetStatus()" class="btn-giant btn-giant-primary" title="บันทึกการตั้งค่า"
                    style="margin: 0; padding: 0; display: inline-flex; align-items: center; justify-content: center; width: 44px !important; height: 44px !important; border-radius: 50% !important; min-width: 44px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Target List -->
        <div class="list-card" style="height: auto; min-height: 500px;">
            <div
                style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: var(--text-primary);">รายชื่อประชากร <span id="target-count"
                        style="font-size: 14px; color: var(--text-muted); font-weight: normal;">(พบ 0 ราย)</span></h3>
                <button onclick="showManualAddModal()" class="btn-giant btn-giant-primary" title="เพิ่มประชากรใหม่"
                    style="margin: 0; padding: 0; display: inline-flex; align-items: center; justify-content: center; width: 44px !important; height: 44px !important; border-radius: 50% !important; min-width: 44px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                </button>
            </div>

            <div class="list-body" id="target-list" style="margin-top: 20px;">
                <div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกพื้นที่</div>
            </div>
        </div>
    </div>

    <!-- Script Definitions -->
    <script>
        // Data logic from register.php (Copied from assignment.php)
        const tambonData = {
            "341801": { hasSubUnits: true, subUnits: { "10957": { name: "โรงพยาบาลตาลสุม", villages: [{ moo: 1, name: "บ้านม่วงโคน" }, { moo: 2, name: "บ้านดอนรังกา" }, { moo: 3, name: "บ้านนาห้วยแคน" }, { moo: 5, name: "บ้านนามน" }, { moo: 10, name: "บ้านนามน" }, { moo: 11, name: "บ้านตาลสุม" }, { moo: 12, name: "บ้านคำไม้ตาย" }, { moo: 13, name: "บ้านปากเซ" }] }, "03751": { name: "รพ.สต. ดอนพันชาด", villages: [{ moo: 4, name: "บ้านดอนพันชาด" }, { moo: 6, name: "บ้านดอนตะลี" }, { moo: 7, name: "บ้านปากห้วย" }, { moo: 8, name: "บ้านโนนค้อ" }, { moo: 9, name: "บ้านแก่งกบ" }, { moo: 14, name: "บ้านโนนสวรรค์" }, { moo: 15, name: "บ้านทุ่งเจริญ" }] } } },
            "341802": { hasSubUnits: false, hoscode: "03752", villages: [{ moo: 1, name: "บ้านสำโรงใหญ่" }, { moo: 2, name: "บ้านสำโรงกลาง" }, { moo: 3, name: "บ้านนาโพธิ์" }, { moo: 4, name: "บ้านสำโรงใต้" }, { moo: 5, name: "บ้านนาแพง" }, { moo: 6, name: "บ้านหนองโน" }, { moo: 7, name: "บ้านหนองสะเดา" }, { moo: 8, name: "บ้านทุ่งเจริญ" }] },
            "341803": { hasSubUnits: false, hoscode: "03753", villages: [{ moo: 1, name: "บ้านจิกเทิง" }, { moo: 2, name: "บ้านจิกลุ่ม" }, { moo: 3, name: "บ้านเชียงแก้ว" }, { moo: 4, name: "บ้านเชียงแก้ว" }, { moo: 5, name: "บ้านดอนโด่" }, { moo: 6, name: "บ้านดอนยูง" }, { moo: 7, name: "บ้านค้อ" }, { moo: 8, name: "บ้านดอนแป้นลม" }, { moo: 9, name: "บ้านสร้างคำ" }] },
            "341804": { hasSubUnits: false, hoscode: "03754", villages: [{ moo: 1, name: "บ้านหนองกุงใหญ่" }, { moo: 2, name: "บ้านหนองกุงน้อย" }, { moo: 3, name: "บ้านคำแคน" }, { moo: 4, name: "บ้านสร้างแสง" }, { moo: 5, name: "บ้านคำเตยใต้" }, { moo: 6, name: "บ้านสร้างหว้า" }, { moo: 7, name: "บ้านคำเตยเหนือ" }, { moo: 8, name: "บ้านสร้างหว้าพัฒนา" }] },
            "341805": { hasSubUnits: true, subUnits: { "03755": { name: "รพ.สต. นาคาย", villages: [{ moo: 1, name: "บ้านนาคาย" }, { moo: 2, name: "บ้านโนนจิก" }, { moo: 3, name: "บ้านหนองเป็ด" }, { moo: 4, name: "บ้านโนนยาง" }, { moo: 5, name: "บ้านดอนขวาง" }, { moo: 6, name: "บ้านดอนหวาย" }] }, "03756": { name: "รพ.สต. บ้านคำหนามแท่ง", villages: [{ moo: 7, name: "บ้านโคกคล้าย" }, { moo: 8, name: "บ้านคำหนามแท่ง" }, { moo: 9, name: "บ้านคำผักหนอก" }, { moo: 10, name: "บ้านคำฮี" }, { moo: 11, name: "บ้านห่องแดง" }, { moo: 12, name: "บ้านโนนสำราญ" }, { moo: 13, name: "บ้านโนนเจริญ" }] } } },
            "341806": { hasSubUnits: false, hoscode: "03757", villages: [{ moo: 1, name: "บ้านคำหว้า" }, { moo: 2, name: "บ้านคำหว้า" }, { moo: 3, name: "บ้านห้วยดู่" }, { moo: 4, name: "บ้านนาทมเหนือ" }, { moo: 5, name: "บ้านไฮหย่อง" }, { moo: 6, name: "บ้านนาทมใต้" }] }
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

        function fetchData() {
            const tambon = document.getElementById('tambon').value;
            const moo = document.getElementById('moo').value;
            const status = document.getElementById('status_filter').value;
            let hoscode = '';

            if (!tambon || !moo) {
                document.getElementById('target-list').innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>';
                document.getElementById('target-count').innerText = '(พบ 0 ราย)';
                return;
            }

            if (tambonData[tambon].hasSubUnits) {
                hoscode = document.getElementById('hoscode').value;
            } else {
                hoscode = tambonData[tambon].hoscode;
            }

            document.getElementById('target-list').innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">กำลังโหลด...</div>';

            fetch(`target_manager.php?action=get_targets&hoscode=${hoscode}&moo=${moo}&status=${status}`)
                .then(r => r.json())
                .then(data => {
                    currentTargets = data;
                    renderTargets();
                });
        }

        function renderTargets() {
            const list = document.getElementById('target-list');
            document.getElementById('target-count').innerText = `(พบ ${currentTargets.length} ราย)`;
            updateSelectedCount();

            if (currentTargets.length === 0) {
                list.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">ไม่พบประชากรเป้าหมายในพื้นที่นี้</div>';
                return;
            }

            let html = '';
            currentTargets.forEach(t => {
                let badgeDM = t.need_screen_dm == 1 ? '<span class="badge badge-dm">ตรวจเบาหวาน</span> ' : '';
                let badgeHT = t.need_screen_ht == 1 ? '<span class="badge badge-ht">ตรวจความดัน</span> ' : '';
                let noBadge = (t.need_screen_dm == 0 && t.need_screen_ht == 0) ? '<span class="badge badge-none">ไม่คัดกรอง</span>' : '';

                let originText = t.health_status_origin;
                if (originText === 'HT_ONLY') originText = 'เฉพาะความดัน';
                else if (originText === 'DM_ONLY') originText = 'เฉพาะเบาหวาน';
                else if (originText === 'BOTH') originText = 'ทั้งเบาหวานและความดัน';
                else if (originText === 'HIGH_RISK') originText = 'กลุ่มเสี่ยงสูง';
                else if (originText === 'NORMAL') originText = 'ปกติ';
                else if (originText === 'MANUAL') originText = 'แมนนวล (ข้อมูลเก่า)';

                if (t.is_manual == 1) originText += ' (แมนนวล)';

                html += `
                    <div class="item-row">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <input type="checkbox" class="target-checkbox item-cb" value="${t.cid}" onchange="updateSelectedCount()">
                            <div class="item-info">
                                <h4>${t.first_name} ${t.last_name}</h4>
                                <p>บ้านเลขที่: ${t.house_no} | อายุ: ${t.age || '-'} ปี | ข้อมูลตั้งต้น: ${originText}</p>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            ${badgeDM} ${badgeHT} ${noBadge}
                            <button onclick="editTarget('${t.cid}')" title="แก้ไขข้อมูล" style="background: none; border: none; color: var(--color-accent); cursor: pointer; padding: 4px; margin-left: 12px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
            document.getElementById('select-all').checked = false;
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

        function updateTargetStatus() {
            const cids = Array.from(document.querySelectorAll('.item-cb:checked')).map(cb => cb.value);
            if (cids.length === 0) {
                alert("กรุณาเลือกประชากรก่อนครับ");
                return;
            }

            const need_dm = document.getElementById('set_dm').checked;
            const need_ht = document.getElementById('set_ht').checked;

            if (confirm(`ยืนยันการเปลี่ยนสถานะเป้าหมายสำหรับ ${cids.length} รายที่เลือก?`)) {
                fetch('target_manager.php?action=update_targets', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ cids: cids, need_dm: need_dm, need_ht: need_ht })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert("อัปเดตข้อมูลสำเร็จ!");
                            fetchData();
                        } else {
                            alert("เกิดข้อผิดพลาด: " + data.message);
                        }
                    })
                    .catch(err => alert("เกิดข้อผิดพลาดในการเชื่อมต่อ"));
            }
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
        let modalMode = 'add';
        let editCid = '';

        function showManualAddModal() {
            const tambon = document.getElementById('tambon').value;
            const moo = document.getElementById('moo').value;
            if (!tambon || !moo) {
                alert('กรุณาเลือกพื้นที่ (ตำบลและหมู่บ้าน) ก่อนเพิ่มข้อมูลแมนนวล');
                return;
            }

            modalMode = 'add';
            document.getElementById('modal-title').innerText = '+ เพิ่มประชากรเป้าหมายแบบแมนนวล';
            document.getElementById('manual_cid').disabled = false;

            document.getElementById('manual_cid').value = '';
            document.getElementById('manual_prefix').value = '';
            document.getElementById('manual_fname').value = '';
            document.getElementById('manual_lname').value = '';
            document.getElementById('manual_birth_date').value = '';
            document.getElementById('manual_house_no').value = '';
            document.getElementById('manual_dm').checked = true;
            document.getElementById('manual_ht').checked = true;
            document.getElementById('cid-error').style.display = 'none';
            document.getElementById('manual_cid').style.borderColor = 'var(--border-color)';

            document.getElementById('manual-add-modal').style.display = 'flex';
        }

        function editTarget(cid) {
            const t = currentTargets.find(x => x.cid === cid);
            if (!t) return;

            modalMode = 'edit';
            editCid = cid;
            document.getElementById('modal-title').innerText = 'แก้ไขข้อมูลประชากร';
            document.getElementById('manual_cid').value = t.cid;
            document.getElementById('cid-error').style.display = 'none';
            document.getElementById('manual_cid').style.borderColor = 'var(--border-color)';
            formatThaiID(document.getElementById('manual_cid'));
            document.getElementById('manual_cid').disabled = true; // Cannot edit CID

            document.getElementById('manual_prefix').value = t.prefix || '';
            document.getElementById('manual_fname').value = t.first_name || '';
            document.getElementById('manual_lname').value = t.last_name || '';

            if (t.birth) {
                const parts = t.birth.split('-');
                if (parts.length === 3) {
                    const yearBE = parseInt(parts[0]) + 543;
                    document.getElementById('manual_birth_date').value = `${parts[2]}/${parts[1]}/${yearBE}`;
                }
            } else {
                document.getElementById('manual_birth_date').value = '';
            }

            document.getElementById('manual_house_no').value = t.house_no || '';
            document.getElementById('manual_dm').checked = t.need_screen_dm == 1;
            document.getElementById('manual_ht').checked = t.need_screen_ht == 1;

            document.getElementById('manual-add-modal').style.display = 'flex';
        }

        function closeManualAddModal() {
            document.getElementById('manual-add-modal').style.display = 'none';
        }

        function submitManualAdd() {
            const tambon = document.getElementById('tambon').value;
            const moo = document.getElementById('moo').value;
            let hoscode = '';

            if (tambonData[tambon].hasSubUnits) {
                hoscode = document.getElementById('hoscode').value;
                if (!hoscode) { alert('กรุณาเลือกหน่วยบริการ'); return; }
            } else {
                hoscode = tambonData[tambon].hoscode;
            }

            const rawBirthDate = document.getElementById('manual_birth_date').value.trim();
            if (!rawBirthDate) { alert('กรุณาระบุ วัน/เดือน/ปีเกิด (พ.ศ.)'); return; }

            const parts = rawBirthDate.split('/');
            if (parts.length !== 3) {
                alert('กรุณาระบุรูปแบบ วัน/เดือน/ปีเกิด ให้ถูกต้อง เช่น 25/10/2530');
                return;
            }
            const day = parts[0].padStart(2, '0');
            const month = parts[1].padStart(2, '0');
            const yearBE = parseInt(parts[2]);
            if (isNaN(yearBE) || yearBE < 2400 || yearBE > 2600) {
                alert('กรุณาระบุปี พ.ศ. ให้ถูกต้อง');
                return;
            }
            const yearCE = yearBE - 543;

            const rawCid = document.getElementById('manual_cid').value.replace(/\D/g, '');
            if (modalMode === 'add') {
                if (!isValidThaiID(rawCid)) {
                    alert('เลขบัตรประชาชนไม่ถูกต้องตามหลักเกณฑ์ กรุณาตรวจสอบ');
                    return;
                }
            }

            const prefix = document.getElementById('manual_prefix').value;
            const data = {
                cid: modalMode === 'edit' ? editCid : rawCid,
                prefix: prefix,
                fname: document.getElementById('manual_fname').value.trim(),
                lname: document.getElementById('manual_lname').value.trim(),
                birth_formatted: `${yearCE}-${month}-${day}`,
                house_no: document.getElementById('manual_house_no').value.trim(),
                need_dm: document.getElementById('manual_dm').checked ? 1 : 0,
                need_ht: document.getElementById('manual_ht').checked ? 1 : 0,
                tambon: tambon,
                moo: moo,
                hoscode: hoscode
            };

            if (!data.cid || data.cid.length !== 13) { alert('กรุณาระบุเลขบัตรประชาชน 13 หลัก'); return; }
            if (!data.fname || !data.lname) { alert('กรุณาระบุชื่อและนามสกุล'); return; }

            fetch('target_manager.php?action=add_manual', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        if (modalMode === 'edit') {
                            alert('การแก้ไขสำเร็จ');
                        } else {
                            alert('เพิ่มประชากรกลุ่มเป้าหมายสำเร็จ!');
                        }
                        closeManualAddModal();
                        document.getElementById('manual_cid').value = '';
                        document.getElementById('manual_fname').value = '';
                        document.getElementById('manual_lname').value = '';
                        document.getElementById('manual_birth_date').value = '';
                        document.getElementById('manual_house_no').value = '';
                        fetchData();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + (res.message || 'ไม่สามารถบันทึกได้'));
                    }
                })
                .catch(err => alert('เกิดข้อผิดพลาดในการเชื่อมต่อ'));
        }

        function formatThaiID(input) {
            let val = input.value.replace(/\D/g, '');
            if (val.length > 13) val = val.substring(0, 13);

            let formatted = '';
            if (val.length > 0) formatted += val.substring(0, 1);
            if (val.length > 1) formatted += '-' + val.substring(1, 5);
            if (val.length > 5) formatted += '-' + val.substring(5, 10);
            if (val.length > 10) formatted += '-' + val.substring(10, 12);
            if (val.length > 12) formatted += '-' + val.substring(12, 13);

            input.value = formatted;

            const errDiv = document.getElementById('cid-error');
            if (errDiv) {
                if (val.length === 13) {
                    if (!isValidThaiID(val)) {
                        errDiv.style.display = 'block';
                        input.style.borderColor = '#ff4d4f';
                    } else {
                        errDiv.style.display = 'none';
                        input.style.borderColor = 'var(--border-color)';
                    }
                } else {
                    errDiv.style.display = 'none';
                    input.style.borderColor = 'var(--border-color)';
                }
            }
        }

        function isValidThaiID(id) {
            if (id.length !== 13) return false;
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                sum += parseInt(id.charAt(i)) * (13 - i);
            }
            const mod = sum % 11;
            const checkDigit = (11 - mod) % 10;
            return checkDigit === parseInt(id.charAt(12));
        }
    </script>

    <!-- Manual Add Modal -->
    <div id="manual-add-modal" class="modal-overlay"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
        <div class="modal-content card-dark" style="max-width: 500px; width: 100%; padding: 24px; position: relative;">
            <h3 id="modal-title"
                style="margin-top: 0; color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                + เพิ่มประชากรเป้าหมายแบบแมนนวล</h3>
            <div style="margin-top: 16px;">
                <label class="form-label"
                    style="color: var(--text-secondary); display: block; margin-bottom: 4px;">เลขบัตรประชาชน (13
                    หลัก)</label>
                <input type="text" id="manual_cid" class="form-control" maxlength="17" placeholder="X-XXXX-XXXXX-XX-X"
                    style="width: 100%; box-sizing: border-box;" oninput="formatThaiID(this)">
                <div id="cid-error" style="color: #ff4d4f; font-size: 12px; margin-top: 4px; display: none;">
                    เลขบัตรประชาชนไม่ถูกต้องตามหลักเกณฑ์ Mod 11</div>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 12px;">
                <div style="width: 120px; flex-shrink: 0;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">คำนำหน้า</label>
                    <select id="manual_prefix" class="form-control" style="width: 100%; box-sizing: border-box;">
                        <option value="นาย">นาย</option>
                        <option value="นาง">นาง</option>
                        <option value="นางสาว">นางสาว</option>
                        <option value="">(ไม่ระบุ)</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">ชื่อ</label>
                    <input type="text" id="manual_fname" class="form-control" placeholder="ชื่อจริง"
                        style="width: 100%; box-sizing: border-box;">
                </div>
                <div style="flex: 1;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">นามสกุล</label>
                    <input type="text" id="manual_lname" class="form-control" placeholder="นามสกุล"
                        style="width: 100%; box-sizing: border-box;">
                </div>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 12px;">
                <div style="flex: 1;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">วัน/เดือน/ปีเกิด
                        (พ.ศ.)</label>
                    <input type="text" id="manual_birth_date" class="form-control" placeholder="เช่น 25/10/2530"
                        style="width: 100%; box-sizing: border-box;"
                        oninput="this.value = this.value.replace(/\D/g, '').substring(0,8).replace(/^(\d{2})(\d{1,2})?(\d{1,4})?$/, function(_, d, m, y) { return d + (m ? '/' + m : '') + (y ? '/' + y : ''); })">
                </div>
                <div style="flex: 1;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">บ้านเลขที่</label>
                    <input type="text" id="manual_house_no" class="form-control" placeholder="บ้านเลขที่"
                        style="width: 100%; box-sizing: border-box;">
                </div>
            </div>
            <div style="margin-top: 16px;">
                <label class="form-label"
                    style="color: var(--text-secondary); display: block; margin-bottom: 4px;">ต้องการตรวจ</label>
                <div style="display: flex; gap: 16px; margin-top: 8px;">
                    <label style="color: var(--text-primary); cursor: pointer;"><input type="checkbox" id="manual_dm"
                            checked style="accent-color: var(--color-accent);"> เบาหวาน (DM)</label>
                    <label style="color: var(--text-primary); cursor: pointer;"><input type="checkbox" id="manual_ht"
                            checked style="accent-color: var(--color-accent);"> ความดัน (HT)</label>
                </div>
            </div>
            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <button onclick="closeManualAddModal()" class="btn-giant"
                    style="background: var(--bg-main); color: var(--text-secondary); box-shadow: var(--neumorph-flat);">ยกเลิก</button>
                <button onclick="submitManualAdd()" class="btn-giant btn-giant-primary">บันทึกข้อมูล</button>
            </div>
        </div>
    </div>
</body>

</html>