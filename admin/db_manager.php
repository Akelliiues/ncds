<?php
// admin/db_manager.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_username = $_SESSION['admin_username'] ?? '';

// Only allow super admin (admin_hoscode is null and username is not adminsso)
if ($admin_hoscode !== null || $admin_username === 'adminsso') {
    die("<div style='padding: 20px; color: red; text-align: center;'><h2>เข้าถึงถูกปฏิเสธ (Access Denied)</h2><p>ฟังก์ชันนี้สงวนไว้สำหรับสิทธิ์การดูแลระดับอำเภอ (Super Admin) เท่านั้น</p><a href='index.php'>กลับหน้าหลัก</a></div>");
}

require_once __DIR__ . '/../config/db.php';

// Fetch stats for all Hoscodes
$statsQuery = "
    SELECT 
        p.hoscode, 
        COUNT(DISTINCT p.cid) as total_targets,
        COUNT(DISTINCT a.assignment_id) as total_assignments,
        COUNT(DISTINCT s.screening_id) as total_screenings
    FROM target_population p
    LEFT JOIN task_assignments a ON p.cid = a.target_cid
    LEFT JOIN screening_results s ON a.assignment_id = s.assignment_id
    GROUP BY p.hoscode
    ORDER BY p.hoscode
";
$stats = $pdo->query($statsQuery)->fetchAll();

// Map Hoscode to Name
$hcNames = [
    '10957' => 'โรงพยาบาลตาลสุม',
    '03751' => 'รพ.สต.ดอนพันชาด',
    '03752' => 'รพ.สต.บ้านสำโรง',
    '03753' => 'รพ.สต.บ้านจิกเทิง',
    '03754' => 'รพ.สต.บ้านหนองกุงใหญ่',
    '03755' => 'รพ.สต.นาคาย',
    '03756' => 'รพ.สต.คำหนามแท่ง',
    '03757' => 'รพ.สต.คำหว้า'
];

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการฐานข้อมูล (DB Manager) - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .db-card {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--neumorph-flat);
            margin-bottom: 20px;
        }
        .db-table {
            width: 100%;
            border-collapse: collapse;
            color: var(--text-primary);
        }
        .db-table th, .db-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .db-table th {
            color: var(--text-secondary);
            font-weight: 800;
        }
        .btn-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--color-red);
            border: 1px solid var(--color-red);
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
        }
        .btn-danger:hover {
            background-color: var(--color-red);
            color: white;
        }
        .btn-manage {
            background-color: rgba(13, 44, 84, 0.1);
            color: var(--color-primary);
            border: 1px solid var(--color-primary);
            padding: 7px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .btn-manage:hover {
            background-color: var(--color-primary);
            color: white;
        }
        .btn-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--color-red);
            border: 1px solid var(--color-red);
            padding: 7px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
    </style>
</head>
<body class="admin-dashboard">
    <div class="admin-wrapper" style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 style="color: var(--color-accent); margin: 0; font-size: 28px;">⚙️ จัดการฐานข้อมูลระบบ (DB Manager)</h1>
                <p style="color: var(--text-secondary); margin: 5px 0 0 0;">เฉพาะสิทธิ์ Super Admin (สสอ.ตาลสุม)</p>
            </div>
            <a href="index.php" class="btn-giant btn-giant-secondary" style="margin: 0; padding: 10px 20px; font-size: 14px;">← กลับหน้าแดชบอร์ด</a>
        </div>

        <div class="db-card">
            <h3 style="color: var(--color-red); margin-top: 0;">⚠️ ข้อควรระวังในการเคลียร์ข้อมูล</h3>
            <p style="color: var(--text-secondary); line-height: 1.6; font-size: 14px;">
                การกดปุ่มเคลียร์ข้อมูล จะทำการลบข้อมูล <strong>กลุ่มเป้าหมาย (target_population), การมอบหมายงาน (task_assignments) และผลการคัดกรอง (screening_results)</strong> ของ รพ.สต. ที่เลือก <strong><u>อย่างถาวร</u></strong><br>
                ระบบจะลบข้อมูลที่เกี่ยวโยงกันทั้งหมดโดยอัตโนมัติ กรุณาตรวจสอบให้แน่ใจก่อนดำเนินการ เหมาะสำหรับกรณีที่ต้องการล้างข้อมูลเพื่ออัปโหลดไฟล์ Excel ใหม่
            </p>
        </div>

        <div class="db-card">
            <table class="db-table">
                <thead>
                    <tr>
                        <th>รหัส รพ.สต.</th>
                        <th>ชื่อหน่วยบริการ</th>
                        <th>จำนวนประชากร (เป้าหมาย)</th>
                        <th>ข้อมูลคัดกรองที่มี</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['hoscode']) ?></td>
                            <td><strong style="color: var(--color-primary);"><?= $hcNames[$row['hoscode']] ?? 'ไม่ระบุ' ?></strong></td>
                            <td><?= number_format($row['total_targets']) ?> ราย</td>
                            <td><?= number_format($row['total_screenings']) ?> รายการ</td>
                            <td>
                                <?php if ($row['total_targets'] > 0): ?>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <a href="db_records.php?hoscode=<?= urlencode($row['hoscode']) ?>" class="btn-manage">
                                            ⚙️ จัดการรายบุคคล
                                        </a>
                                        <button class="btn-danger" onclick="clearData('<?= htmlspecialchars($row['hoscode']) ?>', '<?= $hcNames[$row['hoscode']] ?? $row['hoscode'] ?>')">
                                            🗑️ ล้างข้อมูล รพ.สต.
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 13px;">ว่างเปล่า</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function clearData(hoscode, name) {
            if (confirm(`⚠️ ยืนยันการล้างข้อมูลของ [${name}]?\n\nข้อมูลเป้าหมายและการคัดกรองทั้งหมดของพื้นที่นี้จะถูกลบอย่างถาวรและไม่สามารถกู้คืนได้!`)) {
                
                // Double confirmation for safety
                let check = prompt(`พิมพ์รหัส รพ.สต. "${hoscode}" เพื่อยืนยันการลบข้อมูล:`);
                if (check !== hoscode) {
                    alert("รหัสไม่ตรงกัน ยกเลิกการลบข้อมูล");
                    return;
                }

                fetch('../api/admin_db.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'clear_hoscode',
                        'hoscode': hoscode
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(`ล้างข้อมูลสำเร็จแล้ว\nจำนวนประชากรที่ถูกลบ: ${data.deleted_count} ราย`);
                        window.location.reload();
                    } else {
                        alert("เกิดข้อผิดพลาด: " + data.message);
                    }
                })
                .catch(err => {
                    alert("เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย");
                });
            }
        }
    </script>
</body>
</html>
