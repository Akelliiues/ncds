<?php
// scratch/run_test_log.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

// 1. ดึงข้อมูล อสม. จริง 1 คนจาก vhv_users
$vhv = null;
try {
    $vhv = $pdo->query("
        SELECT vhv_id, vhv_name, vhid_code, hoscode 
        FROM vhv_users 
        WHERE approved = 1 AND vhid_code IS NOT NULL AND vhid_code <> '' 
        LIMIT 1
    ")->fetch();
} catch (Exception $e) {
    // Ignore
}

if (!$vhv) {
    $vhv = [
        'vhv_id' => '0986624652',
        'vhv_name' => 'นายทดสอบ ระบบงาน',
        'vhid_code' => '34180401',
        'hoscode' => '03754'
    ];
}

// 2. ดึงข้อมูลเป้าหมายจริง 1 คนในระบบเพื่อเป็นแกนในการจำลองสแกนสำเร็จ
$assigned_target = null;
try {
    $assigned_target = $pdo->query("
        SELECT cid, hid, vhid_code, hoscode 
        FROM target_population 
        WHERE cid IS NOT NULL AND cid <> '' AND hid IS NOT NULL AND hid <> '' AND vhid_code IS NOT NULL AND vhid_code <> '' 
        LIMIT 1
    ")->fetch();
} catch (Exception $e) {
    // Ignore
}

// 3. กำหนดค่า Session และตัวแปรจำลองตามเป้าหมายจริงที่พบ
if ($assigned_target) {
    $assigned_house_hid = $assigned_target['hid'];
    $target_cid = $assigned_target['cid'];
    
    // ล็อกอิน อสม. โดยอิงเขตที่ตรงกับเป้าหมายนี้เพื่อให้สแกนสำเร็จ (AUTHORIZED_SCAN)
    $_SESSION['vhv_id'] = $vhv['vhv_id'];
    $_SESSION['vhv_name'] = $vhv['vhv_name'];
    $_SESSION['vhid_code'] = $assigned_target['vhid_code'];
    $_SESSION['hoscode'] = $assigned_target['hoscode'];
    $_SESSION['is_leader'] = 0;
    $_SESSION['is_hl_coach'] = false;
    
    // สร้างการมอบหมายงานทดลองใน DB
    try {
        $check_assign = $pdo->prepare("
            SELECT COUNT(*) FROM task_assignments 
            WHERE target_cid = ? AND vhv_id = ? AND budget_year = 2026
        ");
        $check_assign->execute([$target_cid, $vhv['vhv_id']]);
        
        if ($check_assign->fetchColumn() == 0) {
            $insert_assign = $pdo->prepare("
                INSERT INTO task_assignments (target_cid, vhv_id, budget_year)
                VALUES (?, ?, 2026)
            ");
            $insert_assign->execute([$target_cid, $vhv['vhv_id']]);
        }
    } catch (Exception $e) {
        // Ignore
    }
    
    // กรณี NO_ASSIGNMENT: หาบ้านหลังอื่นในหมู่บ้านเดียวกัน แต่ไม่มีการมอบหมายงานให้ อสม. คนนี้
    $no_assignment_hid = '999901';
    try {
        $no_assign_house = $pdo->prepare("
            SELECT p.hid FROM target_population p
            LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.vhv_id = ? AND a.budget_year = 2026
            WHERE p.vhid_code = ? AND p.hid IS NOT NULL AND p.hid <> ? AND a.assignment_id IS NULL
            LIMIT 1
        ");
        $no_assign_house->execute([$vhv['vhv_id'], $assigned_target['vhid_code'], $assigned_target['hid']]);
        $no_assign_res = $no_assign_house->fetch();
        if ($no_assign_res) {
            $no_assignment_hid = $no_assign_res['hid'];
        }
    } catch (Exception $e) {
        // Ignore
    }
    
    // กรณี CROSS_DISTRICT: หาบ้านที่อยู่คนละหมู่บ้านกัน
    $cross_district_hid = '999903';
    try {
        $cross_house = $pdo->prepare("
            SELECT hid FROM target_population 
            WHERE vhid_code <> ? AND vhid_code IS NOT NULL AND vhid_code <> '' AND hid IS NOT NULL AND hid <> '' 
            LIMIT 1
        ");
        $cross_house->execute([$assigned_target['vhid_code']]);
        $cross_res = $cross_house->fetch();
        if ($cross_res) {
            $cross_district_hid = $cross_res['hid'];
        }
    } catch (Exception $e) {
        // Ignore
    }

} else {
    // Fallback logic if DB target_population is empty (mocking)
    $assigned_house_hid = '999902';
    $no_assignment_hid = '999901';
    $cross_district_hid = '999903';
    
    $_SESSION['vhv_id'] = $vhv['vhv_id'];
    $_SESSION['vhv_name'] = $vhv['vhv_name'];
    $_SESSION['vhid_code'] = $vhv['vhid_code'];
    $_SESSION['hoscode'] = $vhv['hoscode'];
    $_SESSION['is_leader'] = 0;
    $_SESSION['is_hl_coach'] = false;
}

$invalid_hid = '999999999'; // UNAUTHORIZED_SCAN

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจำลองการทดสอบ Security Log | NCD ตาลสุม</title>
    <style>
        body { font-family: sans-serif; background: #0f172a; color: #f8fafc; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        h2 { color: #f59e0b; margin-top: 0; }
        .btn { display: block; width: 100%; padding: 12px; margin: 15px 0; border: none; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; color: white; transition: background 0.2s; }
        .btn-unauth { background: #6b7280; }
        .btn-unauth:hover { background: #4b5563; }
        .btn-cross { background: #ef4444; }
        .btn-cross:hover { background: #dc2626; }
        .btn-no-assign { background: #8b5cf6; }
        .btn-no-assign:hover { background: #7c3aed; }
        .btn-success-scan { background: #10b981; }
        .btn-success-scan:hover { background: #059669; }
        .result-box { margin-top: 20px; padding: 15px; border-radius: 8px; background: #0f172a; font-family: monospace; min-height: 50px; white-space: pre-wrap; word-break: break-all; }
        .info { font-size: 13px; color: #94a3b8; line-height: 1.5; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h2>🧪 จำลองการทดสอบ Security Log</h2>
    <div class="info">
        ล็อกอินเป็น อสม. แล้ว: <strong><?= htmlspecialchars($_SESSION['vhv_name']) ?></strong> (ID: <?= $_SESSION['vhv_id'] ?>)<br>
        สังกัด รพ.สต.: <strong><?= $_SESSION['hoscode'] ?></strong>, หมู่บ้านที่ดูแล: <strong><?= $_SESSION['vhid_code'] ?></strong>
    </div>

    <button class="btn btn-unauth" onclick="runTest('<?= $invalid_hid ?>')">
        1. ทดสอบสแกนรหัสไม่มีในระบบ (UNAUTHORIZED_SCAN)
    </button>
    
    <button class="btn btn-cross" onclick="runTest('<?= $cross_district_hid ?>')">
        2. ทดสอบสแกนข้ามเขต (CROSS_DISTRICT_UNAUTHORIZED_SCAN_BLOCKED)
    </button>

    <button class="btn btn-no-assign" onclick="runTest('<?= $no_assignment_hid ?>')">
        3. ทดสอบสแกนไม่มีงานมอบหมายในเขต (NO_ASSIGNMENT)
    </button>

    <button class="btn btn-success-scan" onclick="runTest('<?= $assigned_house_hid ?>')">
        4. ทดสอบสแกนสำเร็จ ได้รับมอบหมายงาน (AUTHORIZED_SCAN / SUCCESS)
    </button>

    <div class="result-box" id="result">
        กดปุ่มด้านบนเพื่อจำลองการส่งข้อมูลสแกน QR Code...
    </div>
    
    <div style="margin-top: 25px; text-align: center;">
        <a href="../admin/security_log.php" style="color: #3b82f6; text-decoration: none; font-weight: bold;">
            ➡️ ไปยังหน้าตรวจสอบ Security Log ของแอดมิน
        </a>
    </div>
</div>

<script>
function runTest(hid) {
    const resultDiv = document.getElementById('result');
    resultDiv.innerHTML = "กำลังส่ง Request...";
    resultDiv.style.color = '#94a3b8';

    const formData = new FormData();
    formData.append('hid', hid);
    formData.append('lat', '15.4294');
    formData.append('lng', '104.9922');

    fetch('../api/check_qrcode.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        resultDiv.innerHTML = JSON.stringify(data, null, 2);
        if (data.status === 'locked') {
            resultDiv.style.color = '#f87171'; // สีแดง
        } else {
            resultDiv.style.color = '#4ade80'; // สีเขียว
        }
    })
    .catch(error => {
        resultDiv.innerHTML = "เกิดข้อผิดพลาด: " + error;
        resultDiv.style.color = '#ef4444';
    });
}
</script>
</body>
</html>