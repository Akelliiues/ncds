<?php
// vhv/register.php
session_start();
require_once __DIR__ . '/../config/db.php';

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $tambonCode = trim($_POST['tambon'] ?? '');
    $moo = intval($_POST['moo'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');

    if (empty($title) || empty($firstName) || empty($lastName) || empty($tambonCode) || empty($moo) || empty($phone)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง';
    } elseif (!preg_match('/^[0-9]{9,10}$/', $phone)) {
        $error = 'กรุณากรอกเบอร์โทรศัพท์เป็นตัวเลข 9-10 หลัก';
    } else {
        try {
            // 1. ตรวจสอบว่าเบอร์โทรศัพท์ (vhv_id) นี้ลงทะเบียนไปหรือยัง
            $checkStmt = $pdo->prepare("SELECT vhv_id, vhv_name, hoscode, approved FROM vhv_users WHERE vhv_id = ?");
            $checkStmt->execute([$phone]);
            $existingRow = $checkStmt->fetch();

            if ($existingRow && $existingRow['approved'] == 1) {
                $error = 'เบอร์โทรศัพท์นี้ถูกใช้ลงทะเบียนไปแล้วในระบบ';
            } elseif ($existingRow && $existingRow['approved'] == 0) {
                // Found a pre-imported pending record — try to auto-activate if name + hoscode match
                $vhvNameFull = $title . $firstName . ' ' . $lastName;
                $vhidCode = $tambonCode . sprintf("%02d", $moo);

                // Resolve hoscode for the tambon+moo the user submitted
                $hoscode = '';
                $hosStmt = $pdo->prepare("SELECT hoscode FROM target_population WHERE vhid_code = ? LIMIT 1");
                $hosStmt->execute([$vhidCode]);
                $hosRow = $hosStmt->fetch();
                if ($hosRow && !empty($hosRow['hoscode'])) {
                    $hoscode = $hosRow['hoscode'];
                } else {
                    if ($tambonCode === '341801') {
                        $donPhanchadMoos = [4, 6, 7, 8, 9, 13, 14, 15];
                        $hoscode = in_array($moo, $donPhanchadMoos) ? '03751' : '10957';
                    } elseif ($tambonCode === '341802') { $hoscode = '03752';
                    } elseif ($tambonCode === '341803') { $hoscode = '03753';
                    } elseif ($tambonCode === '341804') { $hoscode = '03754';
                    } elseif ($tambonCode === '341805') { $hoscode = ($moo >= 1 && $moo <= 6) ? '03755' : '03756';
                    } elseif ($tambonCode === '341806') { $hoscode = '03757';
                    } else { $hoscode = '10957'; }
                }

                // Compare: name similarity (strip prefix variations) + hoscode
                $importedName = trim($existingRow['vhv_name']);
                $submittedName = trim($vhvNameFull);
                $nameMatch = (mb_strpos($importedName, $firstName) !== false && mb_strpos($importedName, $lastName) !== false);
                $hoscodeMatch = ($existingRow['hoscode'] === $hoscode);

                if ($nameMatch && $hoscodeMatch) {
                    // Auto-activate: update password, mark approved = 1, update name/moo/vhid
                    $passwordHash = password_hash('1234', PASSWORD_DEFAULT);
                    $actStmt = $pdo->prepare("
                        UPDATE vhv_users
                        SET approved = 1, vhv_name = ?, vhv_moo = ?, vhid_code = ?, password_hash = ?
                        WHERE vhv_id = ?
                    ");
                    $actStmt->execute([$submittedName, $moo, $vhidCode, $passwordHash, $phone]);
                    $message = 'ยืนยันตัวตนสำเร็จ! ระบบตรวจพบข้อมูลของคุณในฐานข้อมูล อนุมัติสิทธิ์ให้คุณโดยอัตโนมัติแล้ว! รหัสผ่านเริ่มต้นคือ "1234" (กรุณาเปลี่ยนรหัสผ่านหลังเข้าสู่ระบบ)';
                    $success = true;
                } else {
                    $error = 'พบรายชื่อเบอร์โทรนี้ในระบบ แต่ชื่อ-นามสกุลหรือเขตรับผิดชอบไม่ตรงกัน กรุณาติดต่อเจ้าหน้าที่เพื่อตรวจสอบสิทธิ์';
                }
            } else {
                // No existing record — insert new pending
                // 2. คำนวณหา vhid_code
                $vhidCode = $tambonCode . sprintf("%02d", $moo);
                
                // 3. ค้นหา hoscode
                $hoscode = '';
                $hosStmt = $pdo->prepare("SELECT hoscode FROM target_population WHERE vhid_code = ? LIMIT 1");
                $hosStmt->execute([$vhidCode]);
                $hosRow = $hosStmt->fetch();
                if ($hosRow && !empty($hosRow['hoscode'])) {
                    $hoscode = $hosRow['hoscode'];
                } else {
                    if ($tambonCode === '341801') {
                        $donPhanchadMoos = [4, 6, 7, 8, 9, 13, 14, 15];
                        $hoscode = in_array($moo, $donPhanchadMoos) ? '03751' : '10957';
                    } elseif ($tambonCode === '341802') { $hoscode = '03752';
                    } elseif ($tambonCode === '341803') { $hoscode = '03753';
                    } elseif ($tambonCode === '341804') { $hoscode = '03754';
                    } elseif ($tambonCode === '341805') { $hoscode = ($moo >= 1 && $moo <= 6) ? '03755' : '03756';
                    } elseif ($tambonCode === '341806') { $hoscode = '03757';
                    } else { $hoscode = '10957'; }
                }

                // 4. บันทึกข้อมูล
                $vhvName = $title . $firstName . ' ' . $lastName;
                $passwordHash = password_hash('1234', PASSWORD_DEFAULT);

                $insertStmt = $pdo->prepare("
                    INSERT INTO vhv_users (vhv_id, vhv_name, vhv_moo, vhid_code, hoscode, password_hash, is_leader, approved)
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0)
                ");
                $insertStmt->execute([$phone, $vhvName, $moo, $vhidCode, $hoscode, $passwordHash]);

                $message = 'ลงทะเบียน อสม. สำเร็จ! รหัสผ่านเริ่มต้นของคุณคือ "1234" (อยู่ระหว่างรอผู้ดูแลระบบตรวจสอบและอนุมัติการใช้งาน)';
                $success = true;
            }
        } catch (\PDOException $e) {
            $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียน อสม. ใหม่ - NCDs ตาลสุม</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-base);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .register-container {
            width: 100%;
            max-width: 500px;
        }
        .register-brand {
            text-align: center;
            margin-bottom: 20px;
        }
        .register-brand h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 6px 0;
        }
        .register-brand span {
            color: var(--color-accent);
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 15px;
            text-align: left;
        }
        .row-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        .form-group {
            margin-bottom: 16px;
        }
    </style>
</head>
<body class="vhv-accessibility">
    <div class="register-container">
        <div class="register-brand">
            <span>อำเภอตาลสุม จังหวัดอุบลราชธานี</span>
            <h1>ลงทะเบียน อสม. ใหม่</h1>
        </div>

        <div class="card-dark" style="margin-bottom: 0;">
            <?php if (!empty($error)): ?>
                <div style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 12px; border-radius: var(--border-radius); margin-bottom: 20px; font-size: 15px; text-align: center; font-weight: bold;">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="background-color: rgba(16, 185, 129, 0.15); border: 2px solid var(--color-green); color: var(--color-green); padding: 20px; border-radius: var(--border-radius); margin-bottom: 20px; font-size: 16px; text-align: center; font-weight: bold; line-height: 1.6;">
                    🎉 <?= htmlspecialchars($message) ?><br>
                    <span style="font-size: 13px; color: var(--text-secondary); font-weight: normal;">เมื่อแอดมินอนุมัติบัญชีเรียบร้อยแล้ว คุณจึงจะสามารถใช้ "เบอร์โทรศัพท์" และรหัสผ่านเพื่อเข้าใช้งานระบบได้</span>
                </div>
                <a href="../index.php" class="btn-giant btn-giant-primary" style="margin-bottom: 0; text-decoration: none; display: flex; align-items: center; justify-content: center;">
                    ไปหน้าเข้าสู่ระบบ
                </a>
            <?php else: ?>
                <form action="" method="POST">
                    
                    <!-- คำนำหน้าชื่อ และชื่อ -->
                    <div class="row-grid">
                        <div>
                            <label for="title" class="form-label">คำนำหน้า</label>
                            <select name="title" id="title" class="form-select" required>
                                <option value="นาง">นาง</option>
                                <option value="นางสาว">นางสาว</option>
                                <option value="นาย">นาย</option>
                            </select>
                        </div>
                        <div>
                            <label for="first_name" class="form-label">ชื่อจริง (ภาษาไทย)</label>
                            <input type="text" name="first_name" id="first_name" class="form-input-text" placeholder="เช่น ใจดี" required autocomplete="off">
                        </div>
                    </div>

                    <!-- นามสกุล -->
                    <div class="form-group">
                        <label for="last_name" class="form-label">นามสกุล (ภาษาไทย)</label>
                        <input type="text" name="last_name" id="last_name" class="form-input-text" placeholder="เช่น รักษ์สุขภาพ" required autocomplete="off">
                    </div>

                    <!-- เบอร์โทรศัพท์ (ที่จะใช้เป็น ID ล็อกอิน) -->
                    <div class="form-group">
                        <label for="phone" class="form-label">เบอร์โทรศัพท์ (ใช้เป็นชื่อผู้ใช้ล็อกอิน)</label>
                        <input type="tel" name="phone" id="phone" class="form-input-text" placeholder="เช่น 0991234567" maxlength="10" required autocomplete="off">
                    </div>

                    <!-- ตำบล และ หมู่บ้าน (แถวแรกเริ่มต้น) -->
                    <div class="row-grid" id="tambon_row" style="margin-bottom: 0;">
                        <div>
                            <label for="tambon" class="form-label">ตำบล</label>
                            <select name="tambon" id="tambon" class="form-select" onchange="onTambonChange()" required>
                                <option value="">-- เลือกตำบล --</option>
                                <option value="341801">ตาลสุม</option>
                                <option value="341802">สำโรง</option>
                                <option value="341803">จิกเทิง</option>
                                <option value="341804">หนองกุง</option>
                                <option value="341805">นาคาย</option>
                                <option value="341806">คำหว้า</option>
                            </select>
                        </div>
                        <div id="single_moo_container">
                            <div id="moo_field_wrapper">
                                <label for="moo" class="form-label">หมู่บ้านรับผิดชอบ</label>
                                <select name="moo" id="moo" class="form-select" required>
                                    <option value="">-- เลือกตำบลก่อน --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- หน่วยบริการ (รพ.สต.) และ หมู่บ้าน (แถวสอง - แสดงเฉพาะ ตาลสุม/นาคาย) -->
                    <div class="row-grid" id="sub_service_row" style="display: none; grid-template-columns: 1.2fr 1fr; margin-top: 0; margin-bottom: 0;">
                        <div>
                            <label for="hoscode_select" class="form-label">หน่วยบริการ (รพ.สต.)</label>
                            <select id="hoscode_select" class="form-select" onchange="onHoscodeChange()">
                                <option value="">-- เลือกหน่วยบริการ --</option>
                            </select>
                        </div>
                        <div id="split_moo_container">
                            <!-- moo_field_wrapper จะถูกย้ายมาที่นี่ผ่าน JS เพื่อให้อยู่แถวเดียวกันกับ รพ.สต. -->
                        </div>
                    </div>

                    <button type="submit" class="btn-giant btn-giant-accent" style="margin-top: 10px; margin-bottom: 12px; font-size: 18px;">
                        📝 ลงทะเบียน อสม.
                    </button>
                    
                    <a href="../index.php" class="btn-giant btn-giant-secondary" style="margin-bottom: 0; text-decoration: none; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                        ย้อนกลับ
                    </a>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ข้อมูลโครงสร้างหน่วยบริการ (รพ.สต.) และหมู่บ้านของอำเภอตาลสุม
        const tambonData = {
            "341801": { // ตาลสุม
                name: "ตาลสุม",
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
                            { moo: 12, name: "บ้านคำไม้ตาย" }
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
                            { moo: 13, name: "บ้านปากเซ" },
                            { moo: 14, name: "บ้านโนนสวรรค์" },
                            { moo: 15, name: "บ้านทุ่งเจริญ" }
                        ]
                    }
                }
            },
            "341802": { // สำโรง
                name: "สำโรง",
                hasSubUnits: false,
                hoscode: "03752",
                villages: [
                    { moo: 1, name: "บ้านสำโรงใหญ่" },
                    { moo: 2, name: "บ้านสำโรงกลาง" },
                    { moo: 3, name: "บ้านนาโพธิ์" },
                    { moo: 4, name: "บ้านสำโรงใต้" },
                    { moo: 5, name: "บ้านนาแพง" },
                    { moo: 6, name: "บ้านหนองโน" },
                    { moo: 7, name: "บ้านหนองสะเดา" },
                    { moo: 8, name: "บ้านทุ่งเจริญ" }
                ]
            },
            "341803": { // จิกเทิง
                name: "จิกเทิง",
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
            "341804": { // หนองกุง
                name: "หนองกุง",
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
            "341805": { // นาคาย
                name: "นาคาย",
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
            "341806": { // คำหว้า
                name: "คำหว้า",
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
            const tambonSelect = document.getElementById('tambon');
            const tambonRow = document.getElementById('tambon_row');
            const subServiceRow = document.getElementById('sub_service_row');
            const hoscodeSelect = document.getElementById('hoscode_select');
            const mooSelect = document.getElementById('moo');
            
            const singleMooContainer = document.getElementById('single_moo_container');
            const splitMooContainer = document.getElementById('split_moo_container');
            const mooFieldWrapper = document.getElementById('moo_field_wrapper');
            
            const selectedTambon = tambonSelect.value;
            
            // รีเซ็ตค่าอื่นๆ
            hoscodeSelect.innerHTML = '<option value="">-- เลือกหน่วยบริการ --</option>';
            mooSelect.innerHTML = '<option value="">-- เลือกตำบลก่อน --</option>';
            subServiceRow.style.display = 'none';
            hoscodeSelect.removeAttribute('required');
            
            // คืนค่าเลย์เอาต์แถวแรกเริ่มต้น และย้ายกล่องหมู่บ้านกลับมาที่เดิม
            tambonRow.style.display = 'grid';
            tambonRow.style.gridTemplateColumns = '1fr 2fr';
            singleMooContainer.style.display = 'block';
            singleMooContainer.appendChild(mooFieldWrapper);
            
            if (selectedTambon && tambonData[selectedTambon]) {
                const data = tambonData[selectedTambon];
                if (data.hasSubUnits) {
                    // ปรับเลย์เอาต์แถวแรกให้ช่องตำบลกว้างเต็มแถว
                    tambonRow.style.gridTemplateColumns = '1fr';
                    singleMooContainer.style.display = 'none';
                    
                    // ย้ายช่องหมู่บ้านไปประกบคู่ รพ.สต. ในแถวที่สอง
                    splitMooContainer.appendChild(mooFieldWrapper);
                    
                    // แสดงแถวที่สอง
                    subServiceRow.style.display = 'grid';
                    hoscodeSelect.setAttribute('required', 'required');
                    
                    for (const code in data.subUnits) {
                        const option = document.createElement('option');
                        option.value = code;
                        option.textContent = data.subUnits[code].name;
                        hoscodeSelect.appendChild(option);
                    }
                    mooSelect.innerHTML = '<option value="">-- เลือกหน่วยบริการก่อน --</option>';
                } else {
                    // ไม่มีหน่วยบริการย่อย ให้แสดงหมู่บ้านทั้งหมดเลย
                    mooSelect.innerHTML = '<option value="">-- เลือกหมู่บ้าน --</option>';
                    data.villages.forEach(v => {
                        const option = document.createElement('option');
                        option.value = v.moo;
                        option.textContent = `หมู่ ${v.moo} ${v.name}`;
                        mooSelect.appendChild(option);
                    });
                }
            }
        }

        function onHoscodeChange() {
            const tambonSelect = document.getElementById('tambon');
            const hoscodeSelect = document.getElementById('hoscode_select');
            const mooSelect = document.getElementById('moo');
            
            const selectedTambon = tambonSelect.value;
            const selectedHoscode = hoscodeSelect.value;
            
            mooSelect.innerHTML = '<option value="">-- เลือกหมู่บ้าน --</option>';
            
            if (selectedTambon && selectedHoscode && tambonData[selectedTambon]) {
                const data = tambonData[selectedTambon];
                if (data.hasSubUnits && data.subUnits[selectedHoscode]) {
                    const subUnit = data.subUnits[selectedHoscode];
                    subUnit.villages.forEach(v => {
                        const option = document.createElement('option');
                        option.value = v.moo;
                        option.textContent = `หมู่ ${v.moo} ${v.name}`;
                        mooSelect.appendChild(option);
                    });
                }
            } else {
                if (selectedTambon && tambonData[selectedTambon] && tambonData[selectedTambon].hasSubUnits) {
                    mooSelect.innerHTML = '<option value="">-- เลือกหน่วยบริการก่อน --</option>';
                }
            }
        }
    </script>
</body>
</html>
