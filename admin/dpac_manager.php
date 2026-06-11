<?php
// admin/dpac_manager.php
require_once __DIR__ . '/../config/session.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$message = '';
$budgetYear = 2026;

// Hospital list
$hc_names = get_health_units();


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
$filter_hoscode = $_GET['hoscode'] ?? ($admin_hoscode ?: '');
$filter_vhid = $_GET['vhid'] ?? '';

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

if ($filter_hoscode) {
    $enrolled_query .= " AND p.hoscode = ?";
    $params[] = $filter_hoscode;
}

if ($filter_vhid) {
    $tambon = substr($filter_vhid, 0, 6);
    $moo = intval(substr($filter_vhid, 6, 2));
    $enrolled_query .= " AND p.sub_district_code = ? AND p.moo = ?";
    $params[] = $tambon;
    $params[] = $moo;
}

$enrolled_query .= " ORDER BY p.moo, p.house_no";

$stmt = $pdo->prepare($enrolled_query);
$stmt->execute($params);
$enrollments = $stmt->fetchAll();

// Fetch VHVs for assignment dropdown
$vhv_query = "SELECT vhv_id, vhv_name, vhid_code FROM vhv_users";
$vhv_params = [];

if ($filter_hoscode) {
    $vhv_query .= " WHERE approved = 1 AND hoscode = ?";
    $vhv_params[] = $filter_hoscode;
} else {
    $vhv_query .= " WHERE approved = 1";
}

if ($filter_vhid) {
    $vhv_query .= " AND vhid_code = ?";
    $vhv_params[] = $filter_vhid;
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
            1 => 'บ้านสำโรงใหญ่', 2 => 'บ้านสำโรงกลาง', 3 => 'บ้านนาโพธิ์', 4 => 'บ้านสำโรงใต้', 5 => 'บ้านนาแพง',
            6 => 'บ้านหนองโน', 7 => 'บ้านหนองสะเดา', 8 => 'บ้านทุ่งเจริญ'
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

// Generate village options
$village_options = [];
if (!empty($filter_hoscode)) {
    $h = $filter_hoscode;
    if (isset($hoscode_villages[$h])) {
        $tcode = $hoscode_villages[$h]['tambon'];
        foreach ($hoscode_villages[$h]['villages'] as $moo => $name) {
            $vcode = $tcode . sprintf('%02d', $moo);
            $village_options[$vcode] = "หมู่ {$moo} {$name}";
        }
    }
} else {
    foreach ($hoscode_villages as $h => $info) {
        $tcode = $info['tambon'];
        foreach ($info['villages'] as $moo => $name) {
            $vcode = $tcode . sprintf('%02d', $moo);
            $village_options[$vcode] = "หมู่ {$moo} {$name} (" . ($hc_names[$h] ?? $h) . ")";
        }
    }
    ksort($village_options);
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
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="margin-bottom: 4px;">ผู้เข้าร่วมโครงการปรับเปลี่ยนพฤติกรรม (DPAC)</h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 15px;">มอบหมายงานให้ อสม. ติดตามผลกลุ่มเสี่ยง</p>

        <?php if ($message): ?>
            <div style="background-color: rgba(16, 185, 129, 0.15); border: 1px solid var(--color-green); color: var(--color-green); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="card-dark" style="margin-bottom: 25px;">
            <form method="GET" action="dpac_manager.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                
                <div style="flex: 1; min-width: 200px;">
                    <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">หน่วยบริการ / รพ.สต.</label>
                    <?php if ($admin_hoscode !== null): ?>
                        <input type="text" class="form-select" value="<?= htmlspecialchars($hc_names[$admin_hoscode] ?? '') ?>" readonly style="background-color: rgba(0, 0, 0, 0.1); cursor: not-allowed; color: var(--text-muted);">
                        <input type="hidden" name="hoscode" value="<?= htmlspecialchars($admin_hoscode) ?>">
                    <?php else: ?>
                        <select name="hoscode" class="form-select" onchange="this.form.submit()">
                            <option value="">-- ทุกแห่ง (ทั้งหมด) --</option>
                            <?php foreach ($hc_names ?? [] as $code => $name): ?>
                                <option value="<?= $code ?>" <?= ($filter_hoscode == $code) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div style="flex: 1; min-width: 200px;">
                    <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">หมู่บ้าน</label>
                    <select name="vhid" class="form-select" onchange="this.form.submit()">
                        <option value="">-- ทุกหมู่บ้าน --</option>
                        <?php foreach ($village_options as $val => $lbl): ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= $filter_vhid == $val ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn-primary" style="height: 42px; padding: 0 20px; border-radius: var(--border-radius); font-weight: bold; cursor: pointer; border: none; background: var(--color-accent); color: white;">
                        ค้นหา
                    </button>
                    <a href="dpac_manager.php" class="btn-primary" style="height: 42px; padding: 0 15px; border-radius: var(--border-radius); font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; background: rgba(13, 44, 84, 0.1); color: var(--text-primary); border: 1px solid var(--border-color); box-sizing: border-box; margin-left: 5px;">
                        ล้างค่า
                    </a>
                </div>
            </form>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="assign_dpac">
            
            <div class="card-dark" style="margin-bottom: 30px; border: 2px dashed var(--color-green); background-color: rgba(16, 185, 129, 0.02);">
                <h3 style="color: var(--color-green); margin-bottom: 15px;">มอบหมายงานติดตามรอบใหม่</h3>
                <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                    
                    <div style="flex: 1; min-width: 250px;">
                        <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">เลือก อสม. ที่รับผิดชอบ:</label>
                        <select name="vhv_id" id="vhv_select" class="form-select" required>
                            <option value="">-- เลือก อสม. --</option>
                            <?php foreach ($vhvList as $v): ?>
                                <?php
                                $vhid_prefix = !empty($v['vhid_code']) && strlen($v['vhid_code']) >= 8 ? substr($v['vhid_code'], 0, 8) : '';
                                ?>
                                <option value="<?= htmlspecialchars($v['vhv_id']) ?>" data-vhid-prefix="<?= htmlspecialchars($vhid_prefix) ?>">
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