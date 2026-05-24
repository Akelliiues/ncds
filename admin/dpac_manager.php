<?php
// admin/dpac_manager.php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$message = '';
$budgetYear = 2026;

// Handle Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_dpac') {
    $enrollmentIds = $_POST['enrollments'] ?? [];
    $vhvId = $_POST['vhv_id'] ?? '';
    
    if (!empty($enrollmentIds) && !empty($vhvId)) {
        $pdo->beginTransaction();
        try {
            $roundStmt = $pdo->prepare("SELECT IFNULL(MAX(round_number), 0) + 1 FROM dpac_followups WHERE enrollment_id = ?");
            $insertStmt = $pdo->prepare("INSERT INTO dpac_followups (enrollment_id, vhv_id, round_number) VALUES (?, ?, ?)");
            $updateEnrollStmt = $pdo->prepare("UPDATE dpac_enrollments SET assigned_vhv_id = ? WHERE enrollment_id = ?");

            $success = 0;
            foreach ($enrollmentIds as $eid) {
                // Get next round number
                $roundStmt->execute([$eid]);
                $nextRound = $roundStmt->fetchColumn();
                
                $insertStmt->execute([$eid, $vhvId, $nextRound]);
                $updateEnrollStmt->execute([$vhvId, $eid]);
                $success++;
            }
            $pdo->commit();
            $message = "มอบหมายงานติดตาม DPAC สำเร็จ $success รายการ";
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Fetch Enrolled Participants
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

$enrolled_query = "
    SELECT e.enrollment_id, e.risk_type, e.enrolled_at, e.assigned_vhv_id, 
           p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.sub_district_code,
           v.vhv_name,
           (SELECT COUNT(*) FROM dpac_followups f WHERE f.enrollment_id = e.enrollment_id) as total_rounds,
           (SELECT COUNT(*) FROM dpac_followups f WHERE f.enrollment_id = e.enrollment_id AND f.status = 'pending') as pending_rounds
    FROM dpac_enrollments e
    JOIN target_population p ON e.cid = p.cid
    LEFT JOIN vhv_users v ON e.assigned_vhv_id = v.vhv_id
    WHERE e.budget_year = ? AND e.status = 'active'
";
$params = [$budgetYear];

if ($admin_hoscode) {
    $hoscodes = [$admin_hoscode];
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
    $enrolled_query .= " AND p.hoscode IN ($inPlaceholders)";
    $params = array_merge($params, $hoscodes);
}
$enrolled_query .= " ORDER BY p.moo, p.house_no";

$stmt = $pdo->prepare($enrolled_query);
$stmt->execute($params);
$enrollments = $stmt->fetchAll();

// Fetch VHVs for assignment dropdown
$vhv_query = "SELECT vhv_id, vhv_name, vhid_code FROM vhv_users";
$vhv_params = [];

if ($admin_hoscode) {
    $hoscodes = [$admin_hoscode];
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
    $vhv_query .= " WHERE approved = 1 AND hoscode IN ($inPlaceholders)";
    $vhv_params = $hoscodes;
} else {
    $vhv_query .= " WHERE approved = 1";
}
$vhv_query .= " ORDER BY vhv_name";

$vhvStmt = $pdo->prepare($vhv_query);
$vhvStmt->execute($vhv_params);
$vhvList = $vhvStmt->fetchAll();

// Get village name for display in Thai
function get_vhv_responsibility_desc($vhid_code) {
    if (empty($vhid_code) || strlen($vhid_code) < 8) {
        return "รหัสพื้นที่: " . ($vhid_code ?: 'ไม่ระบุ');
    }
    
    $tambon = substr($vhid_code, 0, 6);
    $moo = intval(substr($vhid_code, 6, 2));
    
    $villages = [
        '341801' => [
            1 => 'บ้านม่วงโคน', 2 => 'บ้านดอนรังกา', 3 => 'บ้านนาห้วยแคน', 4 => 'บ้านดอนพันชาด', 5 => 'บ้านนามน',
            6 => 'บ้านดอนตะลี', 7 => 'บ้านปากห้วย', 8 => 'บ้านโนนค้อ', 9 => 'บ้านแก่งกบ', 10 => 'บ้านนามน',
            11 => 'บ้านตาลสุม', 12 => 'บ้านคำไม้ตาย', 13 => 'บ้านปากเซ', 14 => 'บ้านโนนสวรรค์', 15 => 'บ้านทุ่งเจริญ'
        ],
        '341802' => [
            1 => 'บ้านสำโรงใหญ่', 2 => 'บ้านสำโรงกลาง', 3 => 'บ้านนาโพธิ์', 4 => 'บ้านสำโรงใต้', 5 => 'บ้านทรายมูลเหนือ',
            6 => 'บ้านทรายมูลใต้', 7 => 'บ้านหนองบัว', 8 => 'บ้านทุ่งเจริญ'
        ],
        '341803' => [
            1 => 'บ้านจิกเทิง', 2 => 'บ้านจิกลุ่ม', 3 => 'บ้านเชียงแก้ว', 4 => 'บ้านเชียงแก้ว', 5 => 'บ้านดอนโด่',
            6 => 'บ้านดอนยูง', 7 => 'บ้านค้อ', 8 => 'บ้านดอนแป้นลม', 9 => 'บ้านสร้างคำ'
        ],
        '341804' => [
            1 => 'บ้านหนองกุงใหญ่', 2 => 'บ้านหนองกุงน้อย', 3 => 'บ้านคำแคน', 4 => 'บ้านสร้างแสง', 5 => 'บ้านคำเตยใต้',
            6 => 'บ้านสร้างหว้า', 7 => 'บ้านคำเตยเหนือ', 8 => 'บ้านสร้างหว้าพัฒนา'
        ],
        '341805' => [
            1 => 'บ้านนาคาย', 2 => 'บ้านโนนจิก', 3 => 'บ้านหนองเป็ด', 4 => 'บ้านโนนยาง', 5 => 'บ้านดอนขวาง',
            6 => 'บ้านดอนหวาย', 7 => 'บ้านโคกคล้าย', 8 => 'บ้านคำหนามแท่ง', 9 => 'บ้านคำผักหนอก', 10 => 'บ้านคำฮี',
            11 => 'บ้านห่องแดง', 12 => 'บ้านโนนสำราญ', 13 => 'บ้านโนนเจริญ'
        ],
        '341806' => [
            1 => 'บ้านคำหว้า', 2 => 'บ้านคำหว้า', 3 => 'บ้านห้วยดู่', 4 => 'บ้านนาทมเหนือ', 5 => 'บ้านไฮหย่อง',
            6 => 'บ้านนาทมใต้'
        ]
    ];
    
    $village_name = $villages[$tambon][$moo] ?? '';
    if ($village_name) {
        return "รับผิดชอบ: หมู่ {$moo} {$village_name}";
    }
    
    return "รหัสพื้นที่: {$vhid_code}";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการโครงการ DPAC - NCDs Prevention Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="margin-bottom: 4px;">ผู้เข้าร่วมโครงการปรับเปลี่ยนพฤติกรรม (DPAC)</h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 15px;">มอบหมายงานให้ อสม. ติดตามผลกลุ่มเสี่ยง</p>

        <?php if ($message): ?>
            <div style="background-color: rgba(16, 185, 129, 0.15); border: 1px solid var(--color-green); color: var(--color-green); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="action" value="assign_dpac">
            
            <div class="card-dark" style="margin-bottom: 30px; border: 2px dashed var(--color-green); background-color: rgba(16, 185, 129, 0.02);">
                <h3 style="color: var(--color-green); margin-bottom: 15px;">มอบหมายงานติดตามรอบใหม่</h3>
                <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px;">
                        <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">เลือก อสม. ที่รับผิดชอบ:</label>
                        <select name="vhv_id" class="form-select" required>
                            <option value="">-- เลือก อสม. --</option>
                            <?php foreach ($vhvList as $v): ?>
                                <option value="<?= htmlspecialchars($v['vhv_id']) ?>">
                                    <?= htmlspecialchars($v['vhv_name']) ?> (<?= htmlspecialchars(get_vhv_responsibility_desc($v['vhid_code'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn-primary" style="height: 52px; padding: 0 30px; border-radius: var(--border-radius); font-weight: bold; cursor: pointer; border: none; background: var(--color-green); color: white; box-shadow: var(--neumorph-flat);">
                            สั่งงานติดตาม (Follow-up)
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-dark">
                <h3 style="color: var(--color-accent); margin-bottom: 8px;">รายชื่อผู้เข้าร่วมโครงการ (<?= count($enrollments) ?> รายการ)</h3>
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 20px;">* ทำเครื่องหมายถูกหน้าชื่อเพื่อมอบหมายงาน</p>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll"></th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ที่อยู่</th>
                                <th>โรคประจำตัว</th>
                                <th>จำนวนรอบติดตาม</th>
                                <th>สถานะ อสม. ปัจจุบัน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enrollments)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px; color: var(--text-secondary);">ไม่มีผู้เข้าร่วมโครงการ</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($enrollments as $r): ?>
                                    <tr>
                                        <td style="text-align: center;"><input type="checkbox" name="enrollments[]" value="<?= htmlspecialchars($r['enrollment_id']) ?>"></td>
                                        <td>
                                            <strong style="color: var(--text-primary);"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong><br>
                                            <span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($r['cid']) ?></span>
                                        </td>
                                        <td>บ้านเลขที่ <?= htmlspecialchars($r['house_no']) ?> หมู่ <?= htmlspecialchars($r['moo']) ?></td>
                                        <td><strong style="color: var(--color-red);"><?= htmlspecialchars($r['risk_type']) ?></strong></td>
                                        <td>
                                            ทั้งหมด <?= $r['total_rounds'] ?> รอบ
                                            <?php if ($r['pending_rounds'] > 0): ?>
                                                <br><span style="color: var(--color-yellow); font-size: 12px; font-weight: bold;">(มีงานค้าง <?= $r['pending_rounds'] ?> งาน)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $r['vhv_name'] ? '<strong style="color: var(--text-primary);">' . htmlspecialchars($r['vhv_name']) . '</strong>' : '<span style="color: var(--text-muted);">- ยังไม่มอบหมาย -</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('selectAll').addEventListener('change', function(e) {
            document.querySelectorAll('input[name="enrollments[]"]').forEach(cb => cb.checked = e.target.checked);
        });
    </script>
</body>
</html>