<?php
// admin/seed_db.php
session_start();

// ตรวจสอบสิทธิ์แอดมิน
if (php_sapi_name() !== 'cli') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }
}

require_once __DIR__ . '/../config/db.php';

$message = '';
$error = '';

if (isset($_POST['seed']) || (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'seed')) {
    try {
        $pdo->beginTransaction();

        // 1. Seed VHV users
        // Clear existing VHV users first
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("TRUNCATE TABLE vhv_users;");
        $pdo->exec("TRUNCATE TABLE task_assignments;");
        $pdo->exec("TRUNCATE TABLE target_population;");
        $pdo->exec("TRUNCATE TABLE screening_results;");
        $pdo->exec("TRUNCATE TABLE vhv_rewards;");
        $pdo->exec("TRUNCATE TABLE assignment_history_log;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        $passHash = password_hash('1234', PASSWORD_DEFAULT);

        // Leader (is_leader = 1)
        $insertVhv = $pdo->prepare("
            INSERT INTO vhv_users (vhv_id, vhv_name, vhv_moo, vhid_code, hoscode, password_hash, is_leader)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $insertVhv->execute(['1001', 'นางใจดี รักสงบ (ประธาน)', 1, '34180101', '10957', $passHash, 1]);
        $insertVhv->execute(['1002', 'นางสมศรี มีสุข (อสม.)', 1, '34180101', '10957', $passHash, 0]);
        $insertVhv->execute(['1003', 'นายสมชาย แข็งแรง (อสม.)', 2, '34180102', '10957', $passHash, 0]);

        // 2. Seed Staging HDC DM records
        $pdo->exec("TRUNCATE TABLE staging_hdc_dm;");
        $insertStgDm = $pdo->prepare("
            INSERT INTO staging_hdc_dm (hoscode, hosname, pid, cid, name, lname, sex, birth, hid, addr, check_vhid, typearea, risk, result)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Diagnosed Patient (Exclusion engine target: need_screen_dm should become false)
        $insertStgDm->execute(['10957', 'รพ.สต.ตาลสุม', '11', '1234567890111', 'นายดำ', 'เก่งจริง', '1', '1965-04-12', '111', '12 ม.1', '34180101', '1', '5', 'ผู้ป่วยเบาหวานเดิม']);
        // DM screening risk group
        $insertStgDm->execute(['10957', 'รพ.สต.ตาลสุม', '12', '1234567890112', 'นางแดง', 'งามยิ่ง', '2', '1972-08-23', '112', '45 ม.1', '34180101', '1', '2', 'กลุ่มเสี่ยงเบาหวาน']);
        // Normal DM target
        $insertStgDm->execute(['10957', 'รพ.สต.ตาลสุม', '13', '1234567890113', 'น.ส.เขียว', 'สดใส', '2', '1985-11-05', '113', '78/1 ม.1', '34180101', '1', '1', 'ปกติ']);

        // 3. Seed Staging HDC HT records
        $pdo->exec("TRUNCATE TABLE staging_hdc_ht;");
        $insertStgHt = $pdo->prepare("
            INSERT INTO staging_hdc_ht (hoscode, hosname, pid, cid, name, lname, sex, birth, hid, addr, check_vhid, typearea, sbp, dbp, risk)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // HT high risk target
        $insertStgHt->execute(['10957', 'รพ.สต.ตาลสุม', '12', '1234567890112', 'นางแดง', 'งามยิ่ง', '2', '1972-08-23', '112', '45 ม.1', '34180101', '1', 145, 95, '2']);
        $insertStgHt->execute(['10957', 'รพ.สต.ตาลสุม', '13', '1234567890113', 'น.ส.เขียว', 'สดใส', '2', '1985-11-05', '113', '78/1 ม.1', '34180101', '1', 120, 80, '1']);
        // Target in Moo 2
        $insertStgHt->execute(['10957', 'รพ.สต.ตาลสุม', '14', '1234567890114', 'นายขาว', 'บริสุทธิ์', '1', '1958-02-17', '201', '3 ม.2', '34180102', '1', 135, 85, '2']);

        // 4. Seed Target Population and Task Assignments directly for immediate testing
        $insertTarget = $pdo->prepare("
            INSERT INTO target_population 
            (cid, hid, pid, first_name, last_name, sex, birth, house_no, moo, sub_district_code, vhid_code, hoscode, latitude, longitude, health_status_origin, need_screen_dm, need_screen_ht)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // นางแดง งามยิ่ง (Moo 1) - home location: 15.4300, 104.9800
        $insertTarget->execute([
            '1234567890112',
            '112',
            '12',
            'นางแดง',
            'งามยิ่ง',
            '2',
            '1972-08-23',
            '45',
            1,
            '341801',
            '34180101',
            '10957',
            15.4300,
            104.9800,
            'BOTH',
            1,
            1
        ]);

        // น.ส.เขียว สดใส (Moo 1) - home location: 15.4320, 104.9820
        $insertTarget->execute([
            '1234567890113',
            '113',
            '13',
            'น.ส.เขียว',
            'สดใส',
            '2',
            '1985-11-05',
            '78/1',
            1,
            '341801',
            '34180101',
            '10957',
            15.4320,
            104.9820,
            'BOTH',
            1,
            1
        ]);

        // นายขาว บริสุทธิ์ (Moo 2) - home location: 15.4400, 104.9900
        $insertTarget->execute([
            '1234567890114',
            '201',
            '14',
            'นายขาว',
            'บริสุทธิ์',
            '1',
            '1958-02-17',
            '3',
            2,
            '341801',
            '34180102',
            '10957',
            15.4400,
            104.9900,
            'HT_ONLY',
            1,
            1
        ]);

        // Seed Task Assignments directly
        $insertAssign = $pdo->prepare("
            INSERT INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status)
            VALUES (?, ?, 2026, 'pending')
        ");

        // Assign Moo 1 targets to vhv นางสมศรี มีสุข (1002)
        $insertAssign->execute(['1234567890112', '1002']);
        $insertAssign->execute(['1234567890113', '1002']);

        // Assign Moo 2 target to vhv นายสมชาย แข็งแรง (1003)
        $insertAssign->execute(['1234567890114', '1003']);

        $pdo->commit();
        $message = "นำเข้าข้อมูล อสม. ตัวอย่าง, ข้อมูล Staging และข้อมูลประชากรเป้าหมายพร้อมใบงานเสร็จสมบูรณ์! สามารถล็อกอินและเริ่มทดสอบคัดกรองได้ทันที";
    } catch (\Exception $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาดในการใส่ข้อมูลตัวอย่าง: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed Database - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <div class="card-dark" style="text-align: center;">
            <h2 style="color: var(--color-accent); margin-bottom: 20px;">📂 ใส่ข้อมูลจำลองสำหรับผู้ทดสอบระบบ (Seed DB)
            </h2>
            <p style="color: var(--text-secondary); text-align: left; line-height: 1.8; margin-bottom: 30px;">
                ระบบจะสร้างบัญชีผู้ใช้ อสม. ตัวอย่างให้ทันที เพื่อนำไปกรอกทดลองในโทรศัพท์มือถือ/ระบบ PWA ได้แก่:
            </p>
            <ul
                style="color: var(--text-primary); text-align: left; line-height: 2; margin-bottom: 30px; box-shadow: var(--neumorph-inset); background-color: var(--bg-card); padding: 20px 40px; border-radius: var(--border-radius); border: none; list-style-type: square;">
                <li><strong>ประธาน อสม. (Moo 1):</strong> รหัส อสม.: <code
                        style="color: var(--color-accent);">1001</code> รหัสผ่าน: <code
                        style="color: var(--color-accent);">1234</code></li>
                <li><strong>อสม. ทั่วไป (Moo 1):</strong> รหัส อสม.: <code
                        style="color: var(--color-accent);">1002</code> รหัสผ่าน: <code
                        style="color: var(--color-accent);">1234</code></li>
                <li><strong>อสม. ทั่วไป (Moo 2):</strong> รหัส อสม.: <code
                        style="color: var(--color-accent);">1003</code> รหัสผ่าน: <code
                        style="color: var(--color-accent);">1234</code></li>
            </ul>

            <?php if (!empty($message)): ?>
                <div
                    style="background-color: rgba(16, 185, 129, 0.15); border: 1px solid var(--color-green); color: var(--color-green); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px; text-align: left;">
                    <strong>สำเร็จ!</strong> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div
                    style="background-color: rgba(239, 68, 68, 0.15); border: 1px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px; text-align: left;">
                    <strong>ล้มเหลว!</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <button type="submit" name="seed" class="btn-giant btn-giant-primary">
                    เริ่มใส่ข้อมูลจำลองสู่ฐานข้อมูลจริง (Seed Data)
                </button>
            </form>
        </div>
    </div>
</body>

</html>