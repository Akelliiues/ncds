<?php
require_once __DIR__ . '/../config/session.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/etl_shadow.php';

$adminHoscode = ncdShadowHoscode($_SESSION['admin_hoscode'] ?? '');
$adminUsername = (string)($_SESSION['admin_username'] ?? 'ผู้ดูแลระบบ');
$isMainAdmin = $adminHoscode === '';
$healthUnits = get_health_units();
$message = '';
$error = '';

if (empty($_SESSION['etl_review_csrf'])) {
    $_SESSION['etl_review_csrf'] = bin2hex(random_bytes(24));
}

function etlReviewRequireCsrf(): void
{
    $provided = (string)($_POST['csrf_token'] ?? '');
    $expected = (string)($_SESSION['etl_review_csrf'] ?? '');
    if ($expected === '' || !hash_equals($expected, $provided)) {
        throw new RuntimeException('คำขอหมดอายุ กรุณาโหลดหน้าใหม่');
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        etlReviewRequireCsrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'create_review') {
            if (!$isMainAdmin) {
                throw new RuntimeException('เฉพาะผู้ดูแลระบบหลักเท่านั้นที่สร้างรอบตรวจสอบได้');
            }
            $runId = ncdShadowGenerateReview($pdo, $adminUsername);
            header('Location: etl_review.php?run_id=' . $runId . '&created=1');
            exit;
        }

        if ($action === 'review_item') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $decision = (string)($_POST['decision'] ?? '');
            if (!in_array($decision, ['approved', 'rejected'], true)) {
                throw new RuntimeException('สถานะการตรวจสอบไม่ถูกต้อง');
            }

            $stmt = $pdo->prepare("SELECT i.*, r.status AS run_status FROM etl_review_items i
                JOIN etl_review_runs r ON r.run_id=i.run_id WHERE i.item_id=?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item || $item['run_status'] !== 'reviewing') {
                throw new RuntimeException('ไม่พบรายการหรือรอบตรวจสอบสิ้นสุดแล้ว');
            }
            if (!in_array($item['item_type'], ['new_risk', 'support_update'], true)) {
                throw new RuntimeException('รายการนี้ใช้เพื่อเฝ้าดูและไม่สามารถนำเข้าได้');
            }
            if (!$isMainAdmin && ncdShadowHoscode($item['hoscode']) !== $adminHoscode) {
                throw new RuntimeException('ไม่มีสิทธิ์ตรวจสอบข้อมูลของหน่วยบริการอื่น');
            }

            $pdo->prepare("UPDATE etl_review_items SET review_status=?, reviewed_by=?, reviewed_at=NOW()
                WHERE item_id=? AND review_status IN ('pending','approved','rejected')")
                ->execute([$decision, $adminUsername, $itemId]);
            header('Location: etl_review.php?run_id=' . (int)$item['run_id'] . '&reviewed=1');
            exit;
        }

        if ($action === 'apply_run') {
            if (!$isMainAdmin) {
                throw new RuntimeException('เฉพาะผู้ดูแลระบบหลักเท่านั้นที่ยืนยันเข้าระบบจริงได้');
            }
            $runId = (int)($_POST['run_id'] ?? 0);
            $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM etl_review_items
                WHERE run_id=? AND item_type IN ('new_risk','support_update') AND review_status='pending'");
            $pendingStmt->execute([$runId]);
            if ((int)$pendingStmt->fetchColumn() > 0) {
                throw new RuntimeException('ยังมีรายการที่หน่วยบริการไม่ได้ตรวจสอบ จึงยังยืนยันเข้าระบบจริงไม่ได้');
            }
            $result = ncdShadowApplyRun($pdo, $runId, $adminUsername);
            header('Location: etl_review.php?run_id=' . $runId . '&applied=' . $result['applied'] . '&failed=' . $result['failed']);
            exit;
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$runId = (int)($_GET['run_id'] ?? 0);
if ($runId <= 0) {
    $runId = (int)$pdo->query("SELECT run_id FROM etl_review_runs ORDER BY run_id DESC LIMIT 1")->fetchColumn();
}

$run = null;
$items = [];
$statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'observed' => 0];
if ($runId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM etl_review_runs WHERE run_id=?");
    $stmt->execute([$runId]);
    $run = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($run) {
        $where = 'i.run_id=?';
        $params = [$runId];
        if (!$isMainAdmin) {
            $where .= ' AND i.hoscode=?';
            $params[] = $adminHoscode;
        }
        $stmt = $pdo->prepare("SELECT i.* FROM etl_review_items i WHERE $where
            ORDER BY FIELD(i.review_status,'pending','needs_review','observed','approved','rejected'),
                     FIELD(i.item_type,'new_risk','support_update','unit_transfer','conflict'), i.item_id LIMIT 500");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $pdo->prepare("SELECT review_status, COUNT(*) AS total FROM etl_review_items i
            WHERE $where GROUP BY review_status");
        $countStmt->execute($params);
        foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $statusCounts[$row['review_status']] = (int)$row['total'];
        }
    }
}

$recentRuns = $pdo->query("SELECT * FROM etl_review_runs ORDER BY run_id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

$fieldLabels = [
    'first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'hid' => 'รหัสบ้าน', 'pid' => 'รหัสบุคคล',
    'sex' => 'เพศ', 'birth' => 'วันเกิด', 'house_no' => 'บ้านเลขที่', 'moo' => 'หมู่',
    'sub_district_code' => 'รหัสตำบล', 'vhid_code' => 'รหัสหมู่บ้าน',
    'need_screen_dm' => 'เป้าคัดกรอง DM', 'need_screen_ht' => 'เป้าคัดกรอง HT',
    'health_status_origin' => 'ที่มาความเสี่ยง'
];
$reviewStatusLabels = [
    'pending' => 'รอตรวจสอบ',
    'approved' => 'อนุมัติแล้ว',
    'rejected' => 'ไม่รับรายการ',
    'observed' => 'เฝ้าดู',
    'needs_review' => 'ต้องตรวจสอบข้อมูล',
];

function etlReviewChangedFields(array $before, array $after, array $labels): array
{
    $result = [];
    foreach ($labels as $field => $label) {
        $old = $before[$field] ?? null;
        $new = $after[$field] ?? null;
        if ((string)$old !== (string)$new) {
            if (in_array($field, ['need_screen_dm', 'need_screen_ht'], true)) {
                $old = (int)$old === 1 ? 'เป็นเป้าหมาย' : 'ยังไม่เป็นเป้าหมาย';
                $new = (int)$new === 1 ? 'เป็นเป้าหมาย' : 'ยังไม่เป็นเป้าหมาย';
            }
            $result[] = ['label' => $label, 'old' => $old ?: 'ไม่มีข้อมูล', 'new' => $new ?: 'ไม่มีข้อมูล'];
        }
    }
    return $result;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบข้อมูลก่อนนำเข้า - NCDs Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .review-wrap{max-width:1320px;margin:0 auto;padding:24px}.review-head,.review-card,.empty-card{background:var(--bg-card);border-radius:24px;box-shadow:var(--neumorph-flat)}
        .review-head{padding:24px;margin-bottom:18px}.review-title{margin:0;color:var(--color-accent);font-size:28px;font-weight:900}.review-desc{margin:7px 0 0;color:var(--text-secondary);font-size:15px;line-height:1.55}
        .toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-top:18px}.btn{border:0;border-radius:14px;padding:12px 18px;font:800 14px var(--font-base);cursor:pointer}.btn-primary{background:#2563eb;color:#fff}.btn-success{background:#10b981;color:#fff}.btn-danger{background:#ef4444;color:#fff}.btn-muted{background:var(--bg-darker);color:var(--text-primary)}
        .notice{padding:13px 16px;border-radius:14px;margin-bottom:16px;font-weight:800}.notice-error{background:#fee2e2;color:#b91c1c}.notice-success{background:#d1fae5;color:#047857}
        .stats{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr));gap:10px;margin:16px 0}.stat{padding:15px;border-radius:18px;background:var(--bg-card);box-shadow:var(--neumorph-flat)}.stat span{display:block;color:var(--text-muted);font-size:12px;font-weight:800}.stat strong{display:block;margin-top:4px;font-size:24px;color:var(--text-primary)}
        .review-card{padding:18px;margin-bottom:13px}.review-card-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.review-card h3{margin:0;color:var(--text-primary);font-size:18px}.review-summary{margin:4px 0 0;color:var(--text-secondary);font-size:14px}.pill{display:inline-flex;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:900;white-space:nowrap}.pill-risk{background:#fef3c7;color:#b45309}.pill-update{background:#dbeafe;color:#1d4ed8}.pill-transfer{background:#ede9fe;color:#6d28d9}.pill-conflict{background:#fee2e2;color:#b91c1c}.pill-approved{background:#d1fae5;color:#047857}.pill-rejected,.pill-needs_review{background:#fee2e2;color:#b91c1c}.pill-pending{background:#f3f4f6;color:#4b5563}.pill-observed{background:#ede9fe;color:#6d28d9}
        .changes{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:8px;margin-top:14px}.change{background:var(--bg-darker);border-radius:14px;padding:10px 12px}.change b{display:block;color:var(--text-primary);font-size:13px;margin-bottom:5px}.before{color:#9ca3af;text-decoration:line-through;font-size:13px}.after{color:#047857;font-size:15px;font-weight:900;margin-top:2px}.actions{display:flex;gap:8px;margin-top:14px;justify-content:flex-end}.empty-card{padding:50px 20px;text-align:center;color:var(--text-secondary)}
        .safety{background:#ecfdf5;color:#065f46;border-radius:16px;padding:14px 16px;margin-top:14px;font-weight:700;line-height:1.5}.run-select{padding:10px 12px;border:0;border-radius:12px;background:var(--bg-darker);color:var(--text-primary);font-family:var(--font-base)}
        @media(max-width:760px){.review-wrap{padding:14px}.review-title{font-size:22px}.stats{grid-template-columns:repeat(2,1fr)}.review-card-head{display:block}.pill{margin-top:8px}.actions .btn{flex:1}.stat strong{font-size:20px}}
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<main class="review-wrap">
    <section class="review-head">
        <h1 class="review-title">ตรวจสอบข้อมูลก่อนนำเข้า</h1>
        <p class="review-desc">ข้อมูลในหน้านี้ยังไม่เข้าสู่ระบบงานจริง จะแสดงเฉพาะรายการที่เสนอให้เพิ่มหรือเปลี่ยน และรายการย้ายหน่วยบริการสำหรับเฝ้าดู</p>
        <div class="safety">ผลคัดกรอง ใบงาน คะแนน และประวัติเดิมจะไม่ถูกแก้ไขหรือลบจากกระบวนการนี้</div>
        <div class="toolbar">
            <form method="get">
                <select name="run_id" class="run-select" onchange="this.form.submit()">
                    <?php if (!$recentRuns): ?><option>ยังไม่มีรอบตรวจสอบ</option><?php endif; ?>
                    <?php foreach ($recentRuns as $recent): ?>
                        <option value="<?= (int)$recent['run_id'] ?>" <?= (int)$recent['run_id'] === $runId ? 'selected' : '' ?>>
                            รอบ #<?= (int)$recent['run_id'] ?> · <?= htmlspecialchars($recent['created_at']) ?> · <?= $recent['status'] === 'reviewing' ? 'กำลังตรวจสอบ' : 'ยืนยันแล้ว' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($isMainAdmin): ?>
                <form method="post" onsubmit="return confirm('สร้างผลตรวจสอบจากข้อมูลในพื้นที่พักตอนนี้ โดยยังไม่เปลี่ยนข้อมูลจริง ใช่หรือไม่?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['etl_review_csrf']) ?>">
                    <input type="hidden" name="action" value="create_review">
                    <button class="btn btn-primary" type="submit">สร้างรอบตรวจสอบใหม่</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($error): ?><div class="notice notice-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (isset($_GET['created'])): ?><div class="notice notice-success">สร้างรอบตรวจสอบแล้ว ข้อมูลจริงยังไม่เปลี่ยนแปลง</div><?php endif; ?>
    <?php if (isset($_GET['reviewed'])): ?><div class="notice notice-success">บันทึกผลการตรวจสอบแล้ว</div><?php endif; ?>
    <?php if (isset($_GET['applied'])): ?><div class="notice notice-success">ยืนยันเข้าระบบจริง <?= (int)$_GET['applied'] ?> รายการ<?= (int)($_GET['failed'] ?? 0) ? ' · ไม่สำเร็จ '.(int)$_GET['failed'].' รายการ' : '' ?></div><?php endif; ?>

    <?php if ($run): ?>
        <?php if ($isMainAdmin): ?>
            <div class="stats">
                <div class="stat"><span>ข้อมูลที่ตรวจทั้งหมด</span><strong><?= number_format($run['total_source']) ?></strong></div>
                <div class="stat"><span>ข้อเสนอให้ตรวจ</span><strong><?= number_format($run['proposed_count']) ?></strong></div>
                <div class="stat"><span>ไม่เปลี่ยนแปลง</span><strong><?= number_format($run['unchanged_count']) ?></strong></div>
                <div class="stat"><span>พบย้ายหน่วยบริการ</span><strong><?= number_format($run['transfer_count']) ?></strong></div>
                <div class="stat"><span>ข้อมูลขัดแย้ง</span><strong><?= number_format($run['conflict_count']) ?></strong></div>
            </div>
        <?php else: ?>
            <div class="stats" style="grid-template-columns:repeat(4,minmax(130px,1fr))">
                <div class="stat"><span>รายการของหน่วยบริการ</span><strong><?= number_format(array_sum($statusCounts)) ?></strong></div>
                <div class="stat"><span>รอตรวจสอบ</span><strong><?= number_format($statusCounts['pending'] ?? 0) ?></strong></div>
                <div class="stat"><span>อนุมัติแล้ว</span><strong><?= number_format($statusCounts['approved'] ?? 0) ?></strong></div>
                <div class="stat"><span>ไม่นำเข้า/เฝ้าดู</span><strong><?= number_format(($statusCounts['rejected'] ?? 0) + ($statusCounts['observed'] ?? 0) + ($statusCounts['needs_review'] ?? 0)) ?></strong></div>
            </div>
        <?php endif; ?>

        <?php if (!$items): ?>
            <div class="empty-card">ไม่มีรายการที่ต้องตรวจในขอบเขตของท่าน</div>
        <?php endif; ?>

        <?php foreach ($items as $item):
            $before = ncdShadowDecode($item['before_data']);
            $after = ncdShadowDecode($item['after_data']);
            $changes = etlReviewChangedFields($before, $after, $fieldLabels);
            $personName = trim(($after['first_name'] ?? '') . ' ' . ($after['last_name'] ?? ''));
            $typeMeta = [
                'new_risk' => ['เสนอเพิ่มกลุ่มเสี่ยง', 'pill-risk'],
                'support_update' => ['ปรับข้อมูลประกอบ', 'pill-update'],
                'unit_transfer' => ['เฝ้าดูการย้ายหน่วย', 'pill-transfer'],
                'conflict' => ['ข้อมูลขัดแย้ง', 'pill-conflict'],
            ][$item['item_type']] ?? [$item['item_type'], 'pill-pending'];
        ?>
            <article class="review-card">
                <div class="review-card-head">
                    <div>
                        <h3><?= htmlspecialchars($personName ?: 'ไม่พบชื่อที่ยืนยันได้') ?></h3>
                        <p class="review-summary"><?= htmlspecialchars($item['change_summary']) ?> · <?= htmlspecialchars($healthUnits[$item['hoscode']] ?? $item['hoscode']) ?></p>
                    </div>
                    <div>
                        <span class="pill <?= $typeMeta[1] ?>"><?= $typeMeta[0] ?></span>
                        <span class="pill pill-<?= htmlspecialchars($item['review_status']) ?>"><?= htmlspecialchars($reviewStatusLabels[$item['review_status']] ?? $item['review_status']) ?></span>
                    </div>
                </div>
                <?php if ($changes): ?><div class="changes">
                    <?php foreach ($changes as $change): ?><div class="change">
                        <b><?= htmlspecialchars($change['label']) ?></b>
                        <div class="before"><?= htmlspecialchars((string)$change['old']) ?></div>
                        <div class="after">→ <?= htmlspecialchars((string)$change['new']) ?></div>
                    </div><?php endforeach; ?>
                </div><?php endif; ?>

                <?php if (in_array($item['item_type'], ['new_risk','support_update'], true) && $run['status'] === 'reviewing'): ?>
                    <form method="post" class="actions">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['etl_review_csrf']) ?>">
                        <input type="hidden" name="action" value="review_item">
                        <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                        <button type="submit" name="decision" value="rejected" class="btn btn-muted">ไม่รับรายการนี้</button>
                        <button type="submit" name="decision" value="approved" class="btn btn-success">ยืนยันรายการนี้</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if ($isMainAdmin && $run['status'] === 'reviewing'): ?>
            <section class="review-head" style="margin-top:20px">
                <h2 style="margin:0;color:var(--text-primary)">ยืนยันเข้าสู่ระบบจริง</h2>
                <p class="review-desc">นำเข้าเฉพาะรายการที่อนุมัติแล้ว ไม่สร้างใบงาน ไม่ลบข้อมูล และไม่ดำเนินการกับรายการย้ายหน่วยบริการ</p>
                <?php if ($statusCounts['pending'] > 0): ?>
                    <div class="notice notice-error" style="margin-top:14px;margin-bottom:0">ยังเหลือ <?= number_format($statusCounts['pending']) ?> รายการที่ต้องตรวจสอบ</div>
                <?php elseif ($statusCounts['approved'] > 0): ?>
                    <form method="post" style="margin-top:14px" onsubmit="return confirm('ยืนยันนำเฉพาะรายการที่อนุมัติเข้าระบบจริงหรือไม่? ประวัติและใบงานเดิมจะไม่ถูกแก้ไข')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['etl_review_csrf']) ?>">
                        <input type="hidden" name="action" value="apply_run">
                        <input type="hidden" name="run_id" value="<?= (int)$runId ?>">
                        <button type="submit" class="btn btn-primary">ยืนยันรายการที่อนุมัติเข้าระบบจริง</button>
                    </form>
                <?php else: ?>
                    <div class="notice" style="margin-top:14px;margin-bottom:0;background:var(--bg-darker);color:var(--text-secondary)">ไม่มีรายการที่อนุมัติให้นำเข้า</div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-card">ยังไม่มีรอบตรวจสอบ ผู้ดูแลระบบหลักสามารถสร้างจากข้อมูลที่อยู่ในพื้นที่พักได้</div>
    <?php endif; ?>
</main>
</body>
</html>
