<?php
// admin/vhv_approval.php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$message = '';
$error = '';

$hc_names = [
    '10957' => 'โรงพยาบาลตาลสุม',
    '10688' => 'โรงพยาบาลตาลสุม',
    '03751' => 'รพ.สต.ดอนพันชาด',
    '03752' => 'รพ.สต.สำโรง',
    '03753' => 'รพ.สต.บ้านจิกเทิง',
    '03754' => 'รพ.สต.หนองกุง',
    '03755' => 'รพ.สต.นาคาย',
    '03756' => 'รพ.สต.บ้านคำหนามแท่ง',
    '03757' => 'รพ.สต.คำหว้า'
];

$admin_title = $admin_hoscode ? ($hc_names[$admin_hoscode] ?? 'รพ.สต.') : 'แอดมินหลัก (ทุก รพ.สต.)';

function get_village_full_name($vhid_code, $moo) {
    $tambon = substr($vhid_code, 0, 6);
    $moo = intval($moo);
    
    $villages = [
        '341801' => [
            1 => 'บ้านม่วงโคน',
            2 => 'บ้านดอนรังกา',
            3 => 'บ้านนาห้วยแคน',
            4 => 'บ้านดอนพันชาด',
            5 => 'บ้านนามน',
            6 => 'บ้านดอนตะลี',
            7 => 'บ้านปากห้วย',
            8 => 'บ้านโนนค้อ',
            9 => 'บ้านแก่งกบ',
            10 => 'บ้านนามน',
            11 => 'บ้านตาลสุม',
            12 => 'บ้านคำไม้ตาย',
            13 => 'บ้านปากเซ',
            14 => 'บ้านโนนสวรรค์',
            15 => 'บ้านทุ่งเจริญ'
        ],
        '341802' => [
            1 => 'บ้านสำโรงใหญ่',
            2 => 'บ้านสำโรงกลาง',
            3 => 'บ้านนาโพธิ์',
            4 => 'บ้านสำโรงใต้',
            5 => 'บ้านทรายมูลเหนือ',
            6 => 'บ้านทรายมูลใต้',
            7 => 'บ้านหนองบัว',
            8 => 'บ้านทุ่งเจริญ'
        ],
        '341803' => [
            1 => 'บ้านจิกเทิง',
            2 => 'บ้านจิกลุ่ม',
            3 => 'บ้านเชียงแก้ว',
            4 => 'บ้านเชียงแก้ว',
            5 => 'บ้านดอนโด่',
            6 => 'บ้านดอนยูง',
            7 => 'บ้านค้อ',
            8 => 'บ้านดอนแป้นลม',
            9 => 'บ้านสร้างคำ'
        ],
        '341804' => [
            1 => 'บ้านหนองกุงใหญ่',
            2 => 'บ้านหนองกุงน้อย',
            3 => 'บ้านคำแคน',
            4 => 'บ้านสร้างแสง',
            5 => 'บ้านคำเตยใต้',
            6 => 'บ้านสร้างหว้า',
            7 => 'บ้านคำเตยเหนือ',
            8 => 'บ้านสร้างหว้าพัฒนา'
        ],
        '341805' => [
            1 => 'บ้านนาคาย',
            2 => 'บ้านโนนจิก',
            3 => 'บ้านหนองเป็ด',
            4 => 'บ้านโนนยาง',
            5 => 'บ้านดอนขวาง',
            6 => 'บ้านดอนหวาย',
            7 => 'บ้านโคกคล้าย',
            8 => 'บ้านคำหนามแท่ง',
            9 => 'บ้านคำผักหนอก',
            10 => 'บ้านคำฮี',
            11 => 'บ้านห่องแดง',
            12 => 'บ้านโนนสำราญ',
            13 => 'บ้านโนนเจริญ'
        ],
        '341806' => [
            1 => 'บ้านคำหว้า',
            2 => 'บ้านคำหว้า',
            3 => 'บ้านห้วยดู่',
            4 => 'บ้านนาทมเหนือ',
            5 => 'บ้านไฮหย่อง',
            6 => 'บ้านนาทมใต้'
        ]
    ];

    $name = $villages[$tambon][$moo] ?? '';
    return $name ? "หมู่ที่ {$moo} {$name}" : "หมู่ที่ {$moo}";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_vhv_id = $_POST['target_vhv_id'] ?? '';
    $action = $_POST['action'];

    if (!empty($target_vhv_id) || $action === 'edit') {
        try {
            // Build authorization check
            $authCheck = true;
            $allowed_hoscodes = [];
            if ($admin_hoscode) {
                $allowed_hoscodes = [$admin_hoscode];
                if ($admin_hoscode === '10957') {
                    $allowed_hoscodes[] = '10688';
                }
            }

            // Determine which VHV ID to check authorization for
            $vhv_id_to_check = ($action === 'edit') ? ($_POST['old_vhv_id'] ?? '') : $target_vhv_id;

            if ($admin_hoscode && !empty($vhv_id_to_check)) {
                // Check if target vhv belongs to sub-admin's hoscode
                $stmt = $pdo->prepare("SELECT hoscode FROM vhv_users WHERE vhv_id = ?");
                $stmt->execute([$vhv_id_to_check]);
                $row = $stmt->fetch();
                if (!$row || !in_array($row['hoscode'], $allowed_hoscodes)) {
                    $authCheck = false;
                }
            }

            // Check if user is editing hoscode to an unauthorized one
            if ($action === 'edit' && $admin_hoscode) {
                $new_hoscode = $_POST['hoscode'] ?? '';
                if (!in_array($new_hoscode, $allowed_hoscodes)) {
                    $authCheck = false;
                }
            }

            if ($authCheck) {
                if ($action === 'approve') {
                    $stmt = $pdo->prepare("UPDATE vhv_users SET approved = 1 WHERE vhv_id = ?");
                    $stmt->execute([$target_vhv_id]);
                    $message = "อนุมัติสิทธิ์การใช้งาน อสม. เรียบร้อยแล้ว";
                } elseif ($action === 'suspend') {
                    $stmt = $pdo->prepare("UPDATE vhv_users SET approved = 0 WHERE vhv_id = ?");
                    $stmt->execute([$target_vhv_id]);
                    $message = "ระงับสิทธิ์การใช้งาน อสม. เรียบร้อยแล้ว (ย้ายไปยังแท็บรอการอนุมัติ)";
                } elseif ($action === 'delete') {
                    // Start transaction to clean dependent records first
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("DELETE FROM task_assignments WHERE vhv_id = ?");
                    $stmt->execute([$target_vhv_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM dpac_followups WHERE vhv_id = ?");
                    $stmt->execute([$target_vhv_id]);

                    $stmt = $pdo->prepare("DELETE FROM vhv_rewards WHERE vhv_id = ?");
                    $stmt->execute([$target_vhv_id]);

                    $stmt = $pdo->prepare("DELETE FROM vhv_users WHERE vhv_id = ?");
                    $stmt->execute([$target_vhv_id]);

                    $pdo->commit();
                    $message = "ลบข้อมูลการลงทะเบียน อสม. เรียบร้อยแล้ว";
                } elseif ($action === 'edit') {
                    $old_vhv_id = $_POST['old_vhv_id'] ?? '';
                    $new_vhv_id = trim($_POST['vhv_id'] ?? '');
                    $vhv_name = trim($_POST['vhv_name'] ?? '');
                    $vhv_moo = intval($_POST['vhv_moo'] ?? 0);
                    $hoscode = trim($_POST['hoscode'] ?? '');
                    $is_leader = intval($_POST['is_leader'] ?? 0);

                    if (empty($new_vhv_id) || empty($vhv_name) || empty($hoscode)) {
                        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
                    } else {
                        // Check duplicate if vhv_id changed
                        if ($new_vhv_id !== $old_vhv_id) {
                            $check = $pdo->prepare("SELECT vhv_id FROM vhv_users WHERE vhv_id = ?");
                            $check->execute([$new_vhv_id]);
                            if ($check->fetch()) {
                                throw new \Exception("เบอร์โทรศัพท์ (ID เข้าใช้งาน) นี้ซ้ำกับระบบงานอื่น");
                            }
                        }

                        $pdo->beginTransaction();

                        // 1. Update task_assignments
                        $stmt = $pdo->prepare("UPDATE task_assignments SET vhv_id = ? WHERE vhv_id = ?");
                        $stmt->execute([$new_vhv_id, $old_vhv_id]);
                        
                        // 2. Update dpac_followups
                        $stmt = $pdo->prepare("UPDATE dpac_followups SET vhv_id = ? WHERE vhv_id = ?");
                        $stmt->execute([$new_vhv_id, $old_vhv_id]);

                        // 3. Update vhv_rewards
                        $stmt = $pdo->prepare("UPDATE vhv_rewards SET vhv_id = ? WHERE vhv_id = ?");
                        $stmt->execute([$new_vhv_id, $old_vhv_id]);

                        // Determine new vhid_code (8 digits) based on hoscode / tambon mapping
                        $getVhid = $pdo->prepare("SELECT vhid_code FROM vhv_users WHERE vhv_id = ?");
                        $getVhid->execute([$old_vhv_id]);
                        $old_vhid = $getVhid->fetchColumn() ?: '';
                        $tambonPrefix = substr($old_vhid, 0, 6);
                        if (empty($tambonPrefix)) {
                            if ($hoscode === '03752') $tambonPrefix = '341802';
                            elseif ($hoscode === '03753') $tambonPrefix = '341803';
                            elseif ($hoscode === '03754') $tambonPrefix = '341804';
                            elseif ($hoscode === '03755' || $hoscode === '03756') $tambonPrefix = '341805';
                            elseif ($hoscode === '03757') $tambonPrefix = '341806';
                            else $tambonPrefix = '341801';
                        }
                        $new_vhid = $tambonPrefix . sprintf("%02d", $vhv_moo);

                        // 4. Update VHV info
                        $updateVhv = $pdo->prepare("
                            UPDATE vhv_users 
                            SET vhv_id = ?, vhv_name = ?, vhv_moo = ?, vhid_code = ?, hoscode = ?, is_leader = ?
                            WHERE vhv_id = ?
                        ");
                        $updateVhv->execute([$new_vhv_id, $vhv_name, $vhv_moo, $new_vhid, $hoscode, $is_leader, $old_vhv_id]);

                        $pdo->commit();
                        $message = "แก้ไขข้อมูลผู้ใช้งาน อสม. เรียบร้อยแล้ว";
                    }
                }
            } else {
                $error = "คุณไม่มีสิทธิ์ในการจัดการผู้ใช้รายนี้";
            }
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Fetch Pending and Approved Lists
try {
    $pending_query = "SELECT * FROM vhv_users WHERE approved = 0";
    $approved_query = "SELECT * FROM vhv_users WHERE approved = 1";
    $params = [];

    if ($admin_hoscode) {
        $hoscodes = [$admin_hoscode];
        if ($admin_hoscode === '10957') {
            $hoscodes[] = '10688';
        }
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        
        $pending_query .= " AND hoscode IN ($inPlaceholders)";
        $approved_query .= " AND hoscode IN ($inPlaceholders)";
        $params = $hoscodes;
    }

    $pending_query .= " ORDER BY created_at DESC";
    $approved_query .= " ORDER BY vhv_name ASC";

    $stmt = $pdo->prepare($pending_query);
    $stmt->execute($params);
    $pending_list = $stmt->fetchAll();

    $stmt = $pdo->prepare($approved_query);
    $stmt->execute($params);
    $approved_list = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error = "เกิดข้อผิดพลาดในการโหลดข้อมูล: " . $e->getMessage();
    $pending_list = [];
    $approved_list = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้ อสม. - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background-color: var(--bg-main); }
        .tab-menu {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
        }
        .tab-link {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all var(--transition-speed);
        }
        .tab-link.active {
            background-color: var(--bg-darker);
            color: var(--color-accent);
            box-shadow: var(--neumorph-inset);
        }
        .action-form {
            display: inline-block;
            margin: 0;
        }
        
        /* Premium Action Icon Buttons */
        .action-btn-container {
            display: inline-flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
        }
        .action-btn {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-speed);
            box-shadow: var(--neumorph-flat);
            color: white;
            padding: 0;
            position: relative;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 4px 4px 8px #cbd5e1;
        }
        .action-btn.approve {
            background-color: var(--color-green);
        }
        .action-btn.suspend {
            background-color: var(--color-yellow);
        }
        .action-btn.edit {
            background-color: #3b82f6;
        }
        .action-btn.delete {
            background-color: var(--color-red);
        }
        .action-btn svg {
            width: 18px;
            height: 18px;
            stroke-width: 2.5;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* Modal styling */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 44, 84, 0.4);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--neumorph-flat);
            width: 100%;
            max-width: 500px;
            margin: 20px;
            padding: 32px;
            animation: modalSlideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalSlideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-form-group {
            margin-bottom: 18px;
        }
        .modal-form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--text-primary);
            font-size: 15px;
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

    <div style="max-width: 1100px; margin: 40px auto; padding: 0 20px;">
        <h2 style="color: var(--color-accent); margin-bottom: 8px;">จัดการผู้ใช้งาน อสม.</h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px;">
            หน่วยบริการผู้รับผิดชอบ: <strong><?= htmlspecialchars($admin_title) ?></strong>
        </p>

        <?php if (!empty($message)): ?>
            <div style="background-color: rgba(16, 185, 129, 0.15); border: 2px solid var(--color-green); color: var(--color-green); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px; font-weight: bold;">
                🎉 <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px; font-weight: bold;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Sub Tabs -->
        <div class="tab-menu">
            <a href="#pending" class="tab-link active" onclick="switchTab('pending', this)">
                รอการอนุมัติ (<?= count($pending_list) ?>)
            </a>
            <a href="#approved" class="tab-link" onclick="switchTab('approved', this)">
                อนุมัติแล้ว (<?= count($approved_list) ?>)
            </a>
        </div>

        <!-- Pending Approvals Section -->
        <div id="pending-section" class="card-dark">
            <h3 style="color: var(--color-accent); margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <span>⏳</span> รายการสิทธิ์ อสม. รอการอนุมัติ
            </h3>
            
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ชื่อ - นามสกุล</th>
                            <th>เบอร์โทรศัพท์ (ID เข้าใช้งาน)</th>
                            <th>หมู่บ้าน</th>
                            <th>รพ.สต. ที่สังกัด</th>
                            <th>วันที่สมัคร</th>
                            <th style="width: 180px; text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_list)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">ไม่มีผู้ใช้อยู่ระหว่างรออนุมัติ</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_list as $user): ?>
                                <tr>
                                    <!-- High contrast readable vhv_name -->
                                    <td style="font-weight: bold; color: var(--text-primary);"><?= htmlspecialchars($user['vhv_name']) ?></td>
                                    <td><?= htmlspecialchars($user['vhv_id']) ?></td>
                                    <td><?= htmlspecialchars(get_village_full_name($user['vhid_code'], $user['vhv_moo'])) ?></td>
                                    <td><?= htmlspecialchars($hc_names[$user['hoscode']] ?? $user['hoscode']) ?></td>
                                    <td style="font-size: 13px; color: var(--text-muted);"><?= $user['created_at'] ?></td>
                                    <td style="text-align: center;">
                                        <div class="action-btn-container">
                                            <!-- Approve Button -->
                                            <form method="POST" class="action-form" onsubmit="return confirm('ต้องการอนุมัติสิทธิ์ให้ อสม. ท่านนี้ใช่หรือไม่?')">
                                                <input type="hidden" name="target_vhv_id" value="<?= $user['vhv_id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="action-btn approve" title="อนุมัติสิทธิ์">
                                                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                </button>
                                            </form>
                                            
                                            <!-- Edit Button -->
                                            <?php 
                                                $userJson = htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <button type="button" class="action-btn edit" title="แก้ไขข้อมูล" onclick="openEditModal('<?= $userJson ?>')">
                                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </button>

                                            <!-- Delete Button -->
                                            <form method="POST" class="action-form" onsubmit="return confirm('ยืนยันที่จะปฏิเสธและลบข้อมูลการลงทะเบียนนี้?')">
                                                <input type="hidden" name="target_vhv_id" value="<?= $user['vhv_id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="action-btn delete" title="ลบออก">
                                                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Approved Users Section -->
        <div id="approved-section" class="card-dark" style="display: none;">
            <h3 style="color: var(--color-accent); margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <span>✅</span> รายการ อสม. ที่อนุมัติสิทธิ์เรียบร้อยแล้ว
            </h3>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ชื่อ - นามสกุล</th>
                            <th>เบอร์โทรศัพท์ (ID เข้าใช้งาน)</th>
                            <th>หมู่บ้าน</th>
                            <th>รพ.สต. ที่สังกัด</th>
                            <th>สถานะประธาน</th>
                            <th style="width: 180px; text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($approved_list)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">ไม่พบ อสม. ที่อนุมัติแล้ว</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($approved_list as $user): ?>
                                <tr>
                                    <!-- High contrast readable vhv_name -->
                                    <td style="font-weight: bold; color: var(--text-primary);"><?= htmlspecialchars($user['vhv_name']) ?></td>
                                    <td><?= htmlspecialchars($user['vhv_id']) ?></td>
                                    <td><?= htmlspecialchars(get_village_full_name($user['vhid_code'], $user['vhv_moo'])) ?></td>
                                    <td><?= htmlspecialchars($hc_names[$user['hoscode']] ?? $user['hoscode']) ?></td>
                                    <td>
                                        <?php if ($user['is_leader']): ?>
                                            <span style="color: var(--color-accent); font-weight: bold;">ประธาน อสม.</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">อสม. สมาชิก</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="action-btn-container">
                                            <!-- Suspend Button -->
                                            <form method="POST" class="action-form" onsubmit="return confirm('ยืนยันที่จะระงับสิทธิ์การใช้งานของ อสม. ท่านนี้? บัญชีจะถูกดึงกลับไปที่ส่วนรออนุมัติ')">
                                                <input type="hidden" name="target_vhv_id" value="<?= $user['vhv_id'] ?>">
                                                <input type="hidden" name="action" value="suspend">
                                                <button type="submit" class="action-btn suspend" title="ระงับสิทธิ์">
                                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                                </button>
                                            </form>

                                            <!-- Edit Button -->
                                            <?php 
                                                $userJson = htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <button type="button" class="action-btn edit" title="แก้ไขข้อมูล" onclick="openEditModal('<?= $userJson ?>')">
                                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </button>

                                            <!-- Delete Button -->
                                            <form method="POST" class="action-form" onsubmit="return confirm('ยืนยันลบสิทธิ์การเข้าใช้งานของ อสม. รายนี้? (ผู้ใช้และข้อมูลประวัติติดตามที่เกี่ยวข้องจะถูกลบออกจากระบบ)')">
                                                <input type="hidden" name="target_vhv_id" value="<?= $user['vhv_id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="action-btn delete" title="ลบผู้ใช้งาน">
                                                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <h3 style="color: var(--color-accent); margin-top: 0; margin-bottom: 24px; text-align: center; font-size: 20px; font-weight: 800;">
                📝 แก้ไขข้อมูล อสม.
            </h3>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="old_vhv_id" id="modal_old_vhv_id">

                <div class="modal-form-group">
                    <label for="modal_vhv_name" class="modal-form-label">ชื่อ - นามสกุล</label>
                    <input type="text" name="vhv_name" id="modal_vhv_name" class="form-input-text" style="box-shadow: var(--neumorph-inset); text-align: left;" required>
                </div>

                <div class="modal-form-group">
                    <label for="modal_vhv_id" class="modal-form-label">เบอร์โทรศัพท์ (ID เข้าใช้งาน)</label>
                    <input type="text" name="vhv_id" id="modal_vhv_id" class="form-input-text" style="box-shadow: var(--neumorph-inset); text-align: left;" required maxlength="10">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
                    <div>
                        <label for="modal_vhv_moo" class="modal-form-label">หมู่ที่</label>
                        <input type="number" name="vhv_moo" id="modal_vhv_moo" class="form-input-text" style="box-shadow: var(--neumorph-inset); text-align: center;" required min="1" max="50">
                    </div>
                    <div>
                        <label for="modal_is_leader" class="modal-form-label">สถานะในระบบ</label>
                        <select name="is_leader" id="modal_is_leader" class="form-select" style="box-shadow: var(--neumorph-inset);">
                            <option value="0">อสม. สมาชิก</option>
                            <option value="1">ประธาน อสม.</option>
                        </select>
                    </div>
                </div>

                <div class="modal-form-group" style="margin-bottom: 28px;">
                    <label for="modal_hoscode" class="modal-form-label">รพ.สต. ที่สังกัด</label>
                    <select name="hoscode" id="modal_hoscode" class="form-select" style="box-shadow: var(--neumorph-inset);">
                        <?php foreach ($hc_names as $code => $name): ?>
                            <?php if ($code == 10688) continue; ?>
                            <option value="<?= $code ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeEditModal()" class="btn-giant btn-giant-secondary" style="height: 44px; line-height: 44px; padding: 0 24px; font-size: 15px; margin: 0; width: auto; background-color: var(--text-muted); color: white;">
                        ยกเลิก
                    </button>
                    <button type="submit" class="btn-giant btn-giant-primary" style="height: 44px; line-height: 44px; padding: 0 24px; font-size: 15px; margin: 0; width: auto; background-color: var(--color-green);">
                        บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab, element) {
            document.querySelectorAll('.tab-link').forEach(link => link.classList.remove('active'));
            element.classList.add('active');

            if (tab === 'pending') {
                document.getElementById('pending-section').style.display = 'block';
                document.getElementById('approved-section').style.display = 'none';
            } else {
                document.getElementById('pending-section').style.display = 'none';
                document.getElementById('approved-section').style.display = 'block';
            }
        }

        function openEditModal(userJson) {
            try {
                const user = JSON.parse(userJson);
                document.getElementById('modal_old_vhv_id').value = user.vhv_id;
                document.getElementById('modal_vhv_id').value = user.vhv_id;
                document.getElementById('modal_vhv_name').value = user.vhv_name;
                document.getElementById('modal_vhv_moo').value = user.vhv_moo;
                document.getElementById('modal_is_leader').value = user.is_leader;
                document.getElementById('modal_hoscode').value = user.hoscode;
                
                // Show modal overlay
                const modal = document.getElementById('editModal');
                modal.style.display = 'flex';
            } catch (e) {
                console.error("Error opening edit modal:", e);
                alert("ไม่สามารถโหลดข้อมูลผู้ใช้เพื่อแก้ไขได้");
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside content
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>