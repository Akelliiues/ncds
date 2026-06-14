<?php
// admin/db_records.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

if ($_SESSION['admin_hoscode'] !== null) {
    die("<div style='padding: 20px; color: red; text-align: center;'><h2>เข้าถึงถูกปฏิเสธ (Access Denied)</h2><p>ฟังก์ชันนี้สงวนไว้สำหรับสิทธิ์การดูแลระดับอำเภอ (Super Admin) เท่านั้น</p><a href='index.php'>กลับหน้าหลัก</a></div>");
}

require_once __DIR__ . '/../config/db.php';

$hoscode = $_GET['hoscode'] ?? '';
if (!$hoscode) {
    header("Location: db_manager.php");
    exit();
}

$hcNames = get_health_units();
$hoscodeName = $hcNames[$hoscode] ?? $hoscode;

$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Build query
$where = "hoscode = ?";
$params = [$hoscode];

if ($search !== '') {
    $where .= " AND (cid LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(cid) FROM target_population WHERE $where");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch data
$sql = "SELECT cid, first_name, last_name, house_no, moo, birth, sex FROM target_population WHERE $where ORDER BY CAST(moo AS UNSIGNED) ASC, CAST(house_no AS UNSIGNED) ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรายบุคคล - Admin</title>
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
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        .btn-danger:hover {
            background-color: var(--color-red);
            color: white;
        }
        .pagination {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .page-link {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 4px;
        }
        .page-link.active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }
    </style>
</head>
<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="color: var(--color-accent); margin: 0;">จัดการเรคคอร์ดรายบุคคล</h2>
                <p style="color: var(--text-secondary); margin: 5px 0 0 0;">หน่วยบริการ: <strong><?= htmlspecialchars($hoscodeName) ?></strong></p>
            </div>
            <a href="db_manager.php" class="btn-giant btn-giant-secondary" style="margin: 0; padding: 10px 20px; font-size: 14px;">← กลับไปหน้า DB Manager</a>
        </div>

        <div class="db-card">
            <form method="GET" action="db_records.php" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                <input type="hidden" name="hoscode" value="<?= htmlspecialchars($hoscode) ?>">
                <input type="text" name="search" class="form-select" placeholder="ค้นหาด้วย เลขบัตร ปชช. หรือ ชื่อ-นามสกุล..." value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 250px;">
                <button type="submit" class="btn-giant" style="margin: 0; padding: 10px 20px; height: auto;">ค้นหา</button>
                <?php if ($search): ?>
                    <a href="db_records.php?hoscode=<?= urlencode($hoscode) ?>" class="btn-giant btn-giant-secondary" style="margin: 0; padding: 10px 20px; height: auto; text-decoration: none;">ล้างการค้นหา</a>
                <?php endif; ?>
            </form>
            
            <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 15px;">พบข้อมูลทั้งหมด <?= number_format($totalRecords) ?> รายการ (หน้าที่ <?= $page ?>/<?= max(1, $totalPages) ?>)</p>

            <div style="overflow-x: auto;">
                <table class="db-table">
                    <thead>
                        <tr>
                            <th>เลขบัตร ปชช. (CID)</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th>บ้านเลขที่</th>
                            <th>หมู่</th>
                            <th>วันเกิด</th>
                            <th>เพศ</th>
                            <th style="text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($records) > 0): ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td style="font-family: monospace; font-size: 14px;"><?= htmlspecialchars($r['cid']) ?></td>
                                    <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                    <td><?= htmlspecialchars($r['house_no']) ?></td>
                                    <td><?= htmlspecialchars($r['moo']) ?></td>
                                    <td><?= htmlspecialchars($r['birth']) ?></td>
                                    <td><?= $r['sex'] == '1' ? 'ชาย' : 'หญิง' ?></td>
                                    <td style="text-align: center;">
                                        <button class="btn-danger" onclick="deleteRecord('<?= htmlspecialchars($r['cid']) ?>', '<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>')">
                                            ลบข้อมูล
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">ไม่พบข้อมูล</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $startPage = max(1, $page - 3);
                    $endPage = min($totalPages, $page + 3);
                    
                    if ($startPage > 1) {
                        echo '<a href="?hoscode='.urlencode($hoscode).'&search='.urlencode($search).'&page=1" class="page-link">1</a>';
                        if ($startPage > 2) echo '<span style="padding: 6px;">...</span>';
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $active = ($i == $page) ? 'active' : '';
                        echo '<a href="?hoscode='.urlencode($hoscode).'&search='.urlencode($search).'&page='.$i.'" class="page-link '.$active.'">'.$i.'</a>';
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) echo '<span style="padding: 6px;">...</span>';
                        echo '<a href="?hoscode='.urlencode($hoscode).'&search='.urlencode($search).'&page='.$totalPages.'" class="page-link">'.$totalPages.'</a>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function deleteRecord(cid, name) {
            if (confirm(`⚠️ ยืนยันการลบข้อมูลของ:\n\nชื่อ: ${name}\nเลขบัตร: ${cid}\n\nคำเตือน: ข้อมูลการคัดกรองและประวัติทั้งหมดที่เกี่ยวข้องจะถูกลบอย่างถาวร!`)) {
                
                fetch('../api/admin_db.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'delete_individual_record',
                        'cid': cid
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(`ลบข้อมูล ${name} สำเร็จแล้ว`);
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
