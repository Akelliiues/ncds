<?php
// scratch/run_test_log.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

// 1. ตั้งค่า Session ให้ล็อกอินเป็น อสม. ทดสอบ
$_SESSION['vhv_id'] = '0986624652';
$_SESSION['vhv_name'] = 'นายทดสอบ ระบบงาน';
$_SESSION['vhid_code'] = '34180401';
$_SESSION['hoscode'] = '03754';
$_SESSION['is_leader'] = 0;
$_SESSION['is_hl_coach'] = false;

// 2. ดึงข้อมูล HID ในหมู่บ้าน '34180401' เพื่อใช้สำหรับกรณี NO_ASSIGNMENT
$in_village_hid = '';
try {
    $house = $pdo->query("
        SELECT hid FROM target_population 
        WHERE vhid_code = '34180401' AND hid IS NOT NULL AND hid <> '' 
        LIMIT 1
    ")->fetch();
    if ($house) {
        $in_village_hid = $house['hid'];
    }
} catch (Exception $e) {
    // Ignore
}

// ถ้าไม่เจอบ้านในหมู่บ้านนี้ ให้ mock เป็นรหัสอื่นที่มีรูปแบบเดียวกัน
if (empty($in_village_hid)) {
    $in_village_hid = '999901'; 
}

// 3. กำหนดตัวแปรสำหรับกรณีต่างๆ
$invalid_hid = '999999999'; // UNAUTHORIZED_SCAN
$cross_district_hid = '1261'; // CROSS_DISTRICT_UNAUTHORIZED_SCAN_BLOCKED (มาจากข้อมูลหมู่ 34200511)
$no_assignment_hid = $in_village_hid; // NO_ASSIGNMENT (อยู่ในหมู่ 34180401 แต่ไม่มีงานมอบหมาย)

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
