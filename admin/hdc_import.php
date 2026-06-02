<?php
// admin/hdc_import.php
require_once __DIR__ . '/../config/session.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_username = $_SESSION['admin_username'] ?? '';
if ($admin_hoscode !== null || $admin_username === 'adminsso') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['hdc_file'])) {
    $file = $_FILES['hdc_file'];
    $diseaseType = $_POST['disease_type'] ?? 'DM';
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $error = "กรุณาอัปโหลดไฟล์ .csv เท่านั้น";
        } else {
            $handle = fopen($file['tmp_name'], "r");
            if ($handle !== FALSE) {
                // Read header
                $header = fgetcsv($handle, 1000, ",");
                // Expected format:
                // DM: cid, name, lname, sex, birth, risk, result
                // HT: cid, name, lname, sex, birth, risk, sbp, dbp
                
                $successCount = 0;
                $pdo->beginTransaction();
                try {
                    if ($diseaseType === 'DM') {
                        $stmt = $pdo->prepare("INSERT INTO staging_hdc_dm (cid, name, lname, sex, birth, risk, result) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO staging_hdc_ht (cid, name, lname, sex, birth, risk, sbp, dbp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    }

                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        // Skip empty rows
                        if (empty(array_filter($data))) continue;
                        
                        // Basic parsing assuming column order
                        // 0:cid, 1:name, 2:lname, 3:sex(1/2), 4:birth(Y-m-d), 5:risk, 6:result/sbp, 7:dbp(if HT)
                        
                        $cid = $data[0] ?? '';
                        $name = $data[1] ?? '';
                        $lname = $data[2] ?? '';
                        $sex = $data[3] ?? '';
                        $birth = !empty($data[4]) ? $data[4] : null;
                        $risk = $data[5] ?? '';
                        
                        if (empty($cid)) continue;

                        if ($diseaseType === 'DM') {
                            $result = $data[6] ?? '';
                            $stmt->execute([$cid, $name, $lname, $sex, $birth, $risk, $result]);
                        } else {
                            $sbp = !empty($data[6]) ? (int)$data[6] : null;
                            $dbp = !empty($data[7]) ? (int)$data[7] : null;
                            $stmt->execute([$cid, $name, $lname, $sex, $birth, $risk, $sbp, $dbp]);
                        }
                        $successCount++;
                    }
                    $pdo->commit();
                    $message = "นำเข้าข้อมูล $diseaseType สำเร็จ $successCount รายการ";
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
                }
                fclose($handle);
            } else {
                $error = "ไม่สามารถเปิดไฟล์ได้";
            }
        }
    } else {
        $error = "ข้อผิดพลาดในการอัปโหลดไฟล์";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นำเข้าข้อมูล HDC - ระบบจัดการคัดกรอง</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2 style="font-size: 16px; font-weight: 800; line-height: 1.2;">NCDs Prevention Portal - Tansum</h2>
                <p>ศูนย์คัดกรอง รพ.สต.</p>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">ภาพรวม (Dashboard)</a>
                <a href="assignment.php" class="nav-item">จัดการเป้าหมาย/มอบหมายงาน</a>
                <?php if (!$admin_hoscode): ?>
                    <a href="import_hdc.php" class="nav-item active">นำเข้าข้อมูล HDC</a>
                <?php endif; ?>
                <a href="hdc_list.php" class="nav-item">คัดกรองความเสี่ยง HDC</a>
                <a href="dpac_manager.php" class="nav-item">จัดการโครงการ DPAC</a>
                <a href="vhv_approval.php" class="nav-item">จัดการผู้ใช้ อสม.</a>
                <a href="../logout.php" class="nav-item">ออกจากระบบ</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <div class="header-title">
                    <h1>นำเข้าข้อมูลจาก HDC</h1>
                    <p>อัปโหลดไฟล์ข้อมูลตรวจสุขภาพประจำปีจากระบบ HDC</p>
                </div>
            </header>

            <div class="content-wrapper">
                <?php if ($message): ?>
                    <div style="background-color: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <?= $message ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div style="background-color: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="card-white" style="max-width: 600px;">
                    <h3>อัปโหลดไฟล์ CSV</h3>
                    <p style="color: #64748b; margin-bottom: 20px; font-size: 14px;">
                        ไฟล์ควรบันทึกในรูปแบบ .csv (Comma Separated Values) แบบมีบรรทัดหัวคอลัมน์ (Header) อยู่บรรทัดแรก
                    </p>

                    <form method="post" enctype="multipart/form-data">
                        <div style="margin-bottom: 20px;">
                            <label class="form-label" style="display: block; margin-bottom: 8px;">ประเภทโรคของข้อมูล</label>
                            <select name="disease_type" class="form-select" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                <option value="DM">เบาหวาน (DM)</option>
                                <option value="HT">ความดันโลหิตสูง (HT)</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label class="form-label" style="display: block; margin-bottom: 8px;">เลือกไฟล์ CSV</label>
                            <input type="file" name="hdc_file" accept=".csv" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px dashed #cbd5e1; background-color: #f8fafc;">
                        </div>
                        <button type="submit" class="btn-primary" style="padding: 12px 24px; font-size: 16px; border: none; border-radius: 6px; background-color: #2563eb; color: white; cursor: pointer;">
                            อัปโหลดและนำเข้าข้อมูล
                        </button>
                    </form>
                    
                    <hr style="border:0; border-top:1px solid #e2e8f0; margin: 30px 0;">
                    
                    <h4>รูปแบบโครงสร้างไฟล์ที่รองรับ (ลำดับคอลัมน์)</h4>
                    <div style="background: #f1f5f9; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 13px; overflow-x: auto;">
                        <strong>ไฟล์สำหรับเบาหวาน (DM):</strong><br>
                        CID, ชื่อ, นามสกุล, เพศ(1=ชาย,2=หญิง), ว/ด/ปเกิด(YYYY-MM-DD), ระดับความเสี่ยง(ปกติ/เสี่ยง/เสี่ยงสูง), ค่าระดับน้ำตาล(FBS)<br><br>
                        <strong>ไฟล์สำหรับความดัน (HT):</strong><br>
                        CID, ชื่อ, นามสกุล, เพศ(1=ชาย,2=หญิง), ว/ด/ปเกิด(YYYY-MM-DD), ระดับความเสี่ยง(ปกติ/เสี่ยง/เสี่ยงสูง), ค่าความดันตัวบน(SBP), ค่าความดันตัวล่าง(DBP)
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>