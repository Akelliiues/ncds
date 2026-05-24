<?php
// admin/print_qr.php
session_start();

// ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$selectedMoo = $_GET['moo'] ?? '';

// Fetch all distinct Moos for selection
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
if ($admin_hoscode) {
    $hoscodes = [$admin_hoscode];
    if ($admin_hoscode === '10957') {
        $hoscodes[] = '10688';
    }
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
    $moosStmt = $pdo->prepare("SELECT DISTINCT moo FROM target_population WHERE hoscode IN ($inPlaceholders) ORDER BY moo");
    $moosStmt->execute($hoscodes);
    $moos = $moosStmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $moosQuery = $pdo->query("SELECT DISTINCT moo FROM target_population ORDER BY moo");
    $moos = $moosQuery->fetchAll(PDO::FETCH_COLUMN);
}

$houses = [];
if ($selectedMoo !== '') {
    // Fetch unique houses in this Moo
    // We group by hid and select the oldest person or first name as the house head representation
    $houseQuery = "
        SELECT hid, house_no, moo, sub_district_code,
               MIN(CONCAT(first_name, ' ', last_name)) as representative_name,
               COUNT(*) as member_count
        FROM target_population
        WHERE moo = ?
    ";
    $params = [$selectedMoo];
    if ($admin_hoscode) {
        $hoscodes = [$admin_hoscode];
        if ($admin_hoscode === '10957') {
            $hoscodes[] = '10688';
        }
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $houseQuery .= " AND hoscode IN ($inPlaceholders)";
        $params = array_merge($params, $hoscodes);
    }
    $houseQuery .= "
        GROUP BY hid, house_no, moo, sub_district_code
        ORDER BY LENGTH(house_no), house_no
    ";
    $houseStmt = $pdo->prepare($houseQuery);
    $houseStmt->execute($params);
    $houses = $houseStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dual-Identity QR Code Generator - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Printable grid layouts */
        .print-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 24px;
        }

        .qr-card {
            background-color: white;
            color: black;
            border: 2px solid #000;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: none;
            page-break-inside: avoid;
            font-family: 'Sarabun', sans-serif;
        }

        .qr-details {
            flex-grow: 1;
            padding-right: 16px;
        }

        .qr-details h2 {
            margin: 0 0 8px 0;
            font-size: 24px;
            color: #1e3a8a;
            font-weight: 800;
        }

        .qr-details p {
            margin: 4px 0;
            font-size: 15px;
            color: #4b5563;
        }

        .qr-image-container {
            width: 140px;
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb;
            padding: 5px;
            background-color: white;
        }

        .qr-image-container img {
            width: 130px;
            height: 130px;
        }

        .no-print-area {
            max-width: 900px;
            margin: 40px auto 0 auto;
            padding: 0 20px;
        }

        @media print {
            body {
                background-color: white !important;
                color: black !important;
            }
            .admin-navbar, .no-print-area {
                display: none !important;
            }
            .print-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px !important;
                margin-top: 0 !important;
            }
            .qr-card {
                border: 2px solid #000 !important;
                box-shadow: none !important;
                background-color: white !important;
            }
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-navbar">
        <a href="index.php" class="admin-logo">NCDs Prevention Portal - Tansum</a>
        <div class="admin-nav-links">
            <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" data-tooltip="แดชบอร์ด">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            </a>
            <?php if (!$admin_hoscode): ?>
                <a href="import_hdc.php" class="<?= basename($_SERVER['PHP_SELF']) == 'import_hdc.php' ? 'active' : '' ?>" data-tooltip="นำเข้าข้อมูล HDC">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                </a>
                <a href="process_etl.php" class="<?= basename($_SERVER['PHP_SELF']) == 'process_etl.php' ? 'active' : '' ?>" data-tooltip="ประมวลผล ETL">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                </a>
            <?php endif; ?>
            <a href="hdc_list.php" class="<?= basename($_SERVER['PHP_SELF']) == 'hdc_list.php' ? 'active' : '' ?>" data-tooltip="คัดกรองความเสี่ยง HDC">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </a>
            <a href="dpac_manager.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dpac_manager.php' ? 'active' : '' ?>" data-tooltip="จัดการโครงการ DPAC">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </a>
            <a href="assignment.php" class="<?= basename($_SERVER['PHP_SELF']) == 'assignment.php' ? 'active' : '' ?>" data-tooltip="มอบหมายงาน อสม.">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
            </a>
            <a href="print_qr.php" class="<?= basename($_SERVER['PHP_SELF']) == 'print_qr.php' ? 'active' : '' ?>" data-tooltip="พิมพ์ QR Code บ้าน">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
            </a>
            <a href="vhv_approval.php" class="<?= basename($_SERVER['PHP_SELF']) == 'vhv_approval.php' ? 'active' : '' ?>" data-tooltip="จัดการผู้ใช้ อสม.">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </a>
            <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" data-tooltip="ข้อมูลส่วนตัว / เปลี่ยนรหัสผ่าน">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </a>
            <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" data-tooltip="รายงานและการพิมพ์">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </a>
            <a href="../logout.php" data-tooltip="ออกจากระบบ" style="color: var(--color-red) !important;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </a>
        </div>
    </div>

    <div class="no-print-area">
        <div class="card-dark">
            <h2 style="color: var(--color-accent); border-bottom: 2px solid var(--border-color); padding-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                สร้างรหัส QR Code ประจำบ้าน (Dual-Identity QR Code)
            </h2>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">
                เลือกหมู่บ้านเพื่อแสดงการ์ด QR Code ประจำบ้าน โดยการ์ดแต่ละใบประกอบด้วยรหัสบ้าน (HID) เลขที่บ้าน และข้อมูลตัวตนสำหรับการสแกนของ อสม. เพื่อเปิดหน้าประวัติและบันทึกคัดกรองอย่างรวดเร็ว
            </p>

            <form action="" method="GET" style="display: flex; gap: 16px; align-items: center; margin-bottom: 20px;">
                <div style="flex-grow: 1;">
                    <select name="moo" class="input-large" style="height: 50px; font-size: 16px; text-align: left;" required>
                        <option value="">-- เลือกหมู่บ้าน --</option>
                        <?php foreach ($moos as $moo): ?>
                            <option value="<?= $moo ?>" <?= (string)$selectedMoo === (string)$moo ? 'selected' : '' ?>>หมู่ที่ <?= $moo ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-giant btn-giant-primary" style="height: 50px; font-size: 16px; width: auto; padding: 0 24px; margin-bottom: 0;">
                    ดึงข้อมูลบ้าน
                </button>
                <?php if (!empty($houses)): ?>
                    <button type="button" onclick="window.print()" class="btn-giant btn-giant-accent" style="height: 50px; font-size: 16px; width: auto; padding: 0 24px; margin-bottom: 0;">
                        🖨️ สั่งพิมพ์การ์ด QR
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Print Layout Area -->
    <?php if (!empty($houses)): ?>
        <div style="max-width: 1000px; margin: 0 auto; padding: 0 20px 40px 20px;">
            <div class="print-grid">
                <?php foreach ($houses as $h): 
                    // Generate URL mapping for scanner
                    $scanUrl = "https://ncd.ssotansum.com/vhv/scan.php?hid=" . urlencode($h['hid']);
                    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($scanUrl);
                ?>
                    <div class="qr-card">
                        <div class="qr-details">
                            <h2>บ้านเลขที่ <?= htmlspecialchars($h['house_no']) ?></h2>
                            <p><strong>หมู่ที่:</strong> <?= htmlspecialchars($h['moo']) ?></p>
                            <p><strong>รหัสบ้าน (HID):</strong> <?= htmlspecialchars($h['hid']) ?></p>
                            <p><strong>ผู้แทนบ้าน:</strong> <?= htmlspecialchars($h['representative_name']) ?> (<?= $h['member_count'] ?> คน)</p>
                            <p style="font-size: 12px; margin-top: 10px; color: #9ca3af; font-family: monospace;">ncd.ssotansum.com</p>
                        </div>
                        <div class="qr-image-container">
                            <img src="<?= $qrCodeUrl ?>" alt="QR Code House <?= htmlspecialchars($h['house_no']) ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($selectedMoo !== ''): ?>
        <div class="no-print-area" style="margin-top: 20px;">
            <div style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); text-align: center;">
                ไม่พบข้อมูลบ้านที่บันทึกอยู่ในหมู่บ้านที่เลือก กรุณาประมวลผลข้อมูล ETL ก่อน
            </div>
        </div>
    <?php endif; ?>
</body>
</html>