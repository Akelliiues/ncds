<?php
// admin/rewards_management.php - จัดการระบบของรางวัล & แคตตาล็อก (Reward & Redemption Suite)
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_title = function_exists('get_admin_title') ? get_admin_title() : 'ผู้ดูแลระบบ';
$is_super_admin = !empty($_SESSION['is_super_admin']);

$systemEnabled = (int)get_system_setting('reward_system_enabled', 0);

// Fetch Dynamic Categories
$categories = [];
$categoryList = [];
try {
    $stmtCats = $pdo->query("
        SELECT c.*, COUNT(i.item_id) AS item_count
        FROM `reward_categories` c
        LEFT JOIN `reward_items` i ON c.category_code = i.category
        GROUP BY c.category_code
        ORDER BY c.sort_order ASC, c.category_name ASC
    ");
    $categoryList = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categoryList as $c) {
        $categories[$c['category_code']] = $c['category_name'];
    }
} catch (\Exception $e) {
    $categories = [
        'equipment' => 'อุปกรณ์ลงพื้นที่',
        'souvenir' => 'ของที่ระลึก อสม.',
        'medical' => 'เครื่องมือแพทย์',
        'honorary' => 'เชิดชูเกียรติ'
    ];
}

// Fetch items
$items = [];
try {
    $items = $pdo->query("SELECT * FROM `reward_items` ORDER BY `sort_order` ASC, `points_required` ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $items = [];
}

// Fetch stats
$totalItems = count($items);
$activeItems = 0;
foreach ($items as $it) {
    if ($it['is_active']) $activeItems++;
}

$pendingCount = 0;
$fulfilledCount = 0;
try {
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM `reward_redemptions` WHERE `status` = 'pending'")->fetchColumn();
    $fulfilledCount = (int)$pdo->query("SELECT COUNT(*) FROM `reward_redemptions` WHERE `status` = 'fulfilled'")->fetchColumn();
} catch (\Exception $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการระบบของรางวัล อสม. - NCDs Portal อำเภอ<?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .reward-container {
            max-width: 1240px;
            margin: 30px auto 80px auto;
            padding: 0 20px;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 18px 20px;
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        /* 3D Neumorphic Pill Toggle Switch */
        .neu-switch-outer {
            background: #EBECF0;
            padding: 8px 10px;
            border-radius: 60px;
            box-shadow: 6px 6px 14px rgba(166, 171, 189, 0.5), -6px -6px 14px rgba(255, 255, 255, 0.8);
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.7);
        }

        [data-theme="dark"] .neu-switch-outer {
            background: #1E293B;
            box-shadow: 6px 6px 14px rgba(0, 0, 0, 0.5), -6px -6px 14px rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .neu-switch-outer:hover {
            transform: scale(1.03);
        }

        .neu-switch-track {
            position: relative;
            width: 230px;
            height: 58px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            padding: 5px;
            box-sizing: border-box;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: inset 4px 4px 8px rgba(0, 0, 0, 0.25), inset -3px -3px 6px rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .neu-switch-track.active {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            justify-content: flex-end;
        }

        .neu-switch-track.closed {
            background: linear-gradient(135deg, #475569 0%, #1E293B 100%);
            justify-content: flex-start;
        }

        .neu-switch-label {
            font-size: 14.5px;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .neu-switch-track.active .neu-switch-label {
            padding-right: 18px;
        }

        .neu-switch-track.closed .neu-switch-label {
            padding-left: 18px;
        }

        .neu-switch-thumb {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #FFFFFF;
            position: absolute;
            top: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 3px 3px 8px rgba(0, 0, 0, 0.35), -2px -2px 6px rgba(255, 255, 255, 0.7);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 3;
        }

        .neu-switch-track.active .neu-switch-thumb {
            left: 5px;
            background: #FFFFFF;
        }

        .neu-switch-track.closed .neu-switch-thumb {
            left: calc(100% - 53px);
            background: #E2E8F0;
        }

        .neu-switch-icon {
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.4s ease;
        }

        .neu-switch-outer:hover .neu-switch-icon {
            transform: scale(1.15) rotate(10deg);
        }

        /* Tabs */
        .reward-tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 20px;
            overflow-x: auto;
        }

        .tab-button {
            background: none;
            border: none;
            padding: 12px 18px;
            font-size: 14.5px;
            font-weight: 800;
            color: var(--text-secondary);
            cursor: pointer;
            position: relative;
            white-space: nowrap;
            transition: color 0.2s;
        }

        .tab-button.active {
            color: var(--color-primary);
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--color-primary);
            border-radius: 3px 3px 0 0;
        }

        /* Catalog Grid */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .item-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 16px;
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: transform 0.2s;
        }

        .item-card:hover {
            transform: translateY(-2px);
        }

        .item-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .item-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: var(--bg-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: var(--neumorph-inset);
            flex-shrink: 0;
            overflow: hidden;
        }

        .item-icon-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-badge-points {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(245, 158, 11, 0.12);
            color: #D97706;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
        }

        .item-badge-stock {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
        }

        .queue-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .queue-table th {
            padding: 12px 14px;
            background: var(--bg-main);
            color: var(--text-secondary);
            font-weight: 800;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .queue-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .preset-btn {
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .preset-btn:hover {
            background: var(--color-primary);
            color: white;
        }

        /* Modal */
        .reward-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 44, 84, 0.6);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .reward-modal-content {
            background: var(--bg-card);
            border-radius: 24px;
            padding: 24px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            max-height: 90vh;
            overflow-y: auto;
        }

        .form-row {
            margin-bottom: 14px;
        }

        .form-row label {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            box-sizing: border-box;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 14px;
            color: var(--text-primary);
            outline: none;
            box-shadow: var(--neumorph-inset);
        }

        .form-input:focus {
            border-color: var(--color-primary);
        }
    </style>
</head>
<body class="admin-body">
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <div class="reward-container">
        <!-- Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
            <div>
                <h2 style="color: var(--color-accent); margin: 0; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px;">
                    🎁 จัดการระบบของรางวัล & แคตตาล็อก อสม.
                </h2>
                <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 14px;">
                    ควบคุมสถานะเปิด-ปิดระบบแลกของรางวัล จัดการหมวดหมู่ แนบรูปภาพจริง และบันทึกการส่งมอบ
                </p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" onclick="openCategoryModal()" class="btn-dash-action" style="background: var(--bg-card); color: var(--color-primary); border: 1.5px solid var(--border-color); padding: 10px 16px; border-radius: 14px; font-size: 13.5px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: var(--neumorph-flat);">
                    🗂️ จัดการหมวดหมู่
                </button>
                <button type="button" onclick="openItemModal()" class="btn-dash-action" style="background: var(--color-primary); color: #ffffff; padding: 10px 18px; border-radius: 14px; font-size: 13.5px; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: var(--neumorph-flat);">
                    ➕ เพิ่มของรางวัลใหม่
                </button>
            </div>
        </div>

        <!-- 1. 3D Neumorphic Feature Toggle Hero Card -->
        <div style="background: var(--bg-card); border-radius: 24px; padding: 22px 26px; box-shadow: var(--neumorph-flat); border: 1.5px solid var(--border-color); margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div style="max-width: 600px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <span style="font-size: 24px;"><?= $systemEnabled ? '🎁' : '🔒' ?></span>
                    <strong style="font-size: 17px; color: var(--text-primary);">
                        สถานะระบบแลกของรางวัล: <span id="systemStatusText" style="color: <?= $systemEnabled ? '#10B981' : '#64748B' ?>;"><?= $systemEnabled ? 'เปิดให้แลกของรางวัล (Active)' : 'ปิดระบบ (โหมดพรีวิว / Preview)' ?></span>
                    </strong>
                </div>
                <p style="margin: 0; font-size: 13.5px; color: var(--text-secondary); line-height: 1.5;">
                    <?= $systemEnabled 
                        ? '🟢 <strong>โหมดเปิด:</strong> อสม. เห็นเกณฑ์คะแนนและสามารถกดแลกของรางวัลในแอปได้ตามปกติ' 
                        : '🔒 <strong>โหมดปิด (Preview):</strong> อสม. เห็นเฉพาะรายการของรางวัลที่มีให้แลก โดยยังไม่แสดงเกณฑ์คะแนนและยังกดแลกไม่ได้' ?>
                </p>
            </div>
            <div>
                <!-- 3D Neumorphic Pill Toggle Button -->
                <div class="neu-switch-outer" id="neuSwitchOuter" onclick="toggleSystem(<?= $systemEnabled ? 0 : 1 ?>)" title="คลิกเพื่อสลับสถานะเปิด/ปิดระบบแลกของรางวัล">
                    <div class="neu-switch-track <?= $systemEnabled ? 'active' : 'closed' ?>" id="neuSwitchTrack">
                        <?php if ($systemEnabled): ?>
                            <div class="neu-switch-thumb" id="neuSwitchThumb">
                                <span class="neu-switch-icon">🎁</span>
                            </div>
                            <span class="neu-switch-label" id="neuSwitchLabel">เปิดให้แลก</span>
                        <?php else: ?>
                            <span class="neu-switch-label" id="neuSwitchLabel">ปิดระบบ</span>
                            <div class="neu-switch-thumb" id="neuSwitchThumb">
                                <span class="neu-switch-icon">🔒</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Statistics Grid -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3B82F6;">🎁</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">ของรางวัลในระบบ</div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--text-primary);"><?= number_format($totalItems) ?> รายการ</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">✅</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">เปิดใช้งานอยู่</div>
                    <div style="font-size: 22px; font-weight: 800; color: #10B981;"><?= number_format($activeItems) ?> รายการ</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">⏳</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">คำขอรอส่งมอบ</div>
                    <div style="font-size: 22px; font-weight: 800; color: #F59E0B;"><?= number_format($pendingCount) ?> รายการ</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(139, 92, 246, 0.15); color: #8B5CF6;">🎉</div>
                <div>
                    <div style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">ส่งมอบรางวัลแล้ว</div>
                    <div style="font-size: 22px; font-weight: 800; color: #8B5CF6;"><?= number_format($fulfilledCount) ?> ครั้ง</div>
                </div>
            </div>
        </div>

        <!-- 3. Navigation Tabs -->
        <div class="reward-tabs">
            <button type="button" class="tab-button active" onclick="switchRewardTab('catalog')" id="tab-btn-catalog">
                📦 แคตตาล็อกของรางวัล (<?= count($items) ?>)
            </button>
            <button type="button" class="tab-button" onclick="switchRewardTab('categories')" id="tab-btn-categories">
                🗂️ จัดการหมวดหมู่ (<?= count($categoryList) ?>)
            </button>
            <button type="button" class="tab-button" onclick="switchRewardTab('queue')" id="tab-btn-queue">
                📋 คำขอแลกรางวัล & ส่งมอบ (<span id="queueCountBadge"><?= $pendingCount ?></span>)
            </button>
        </div>

        <!-- Tab 1: Catalog Items Grid -->
        <div id="tab-content-catalog">
            <div class="items-grid">
                <?php foreach ($items as $item): ?>
                    <div class="item-card" id="item-card-<?= $item['item_id'] ?>" style="<?= !$item['is_active'] ? 'opacity: 0.6;' : '' ?>">
                        <div>
                            <div class="item-card-header">
                                <div class="item-icon-box">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="../<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                                    <?php else: ?>
                                        <?= htmlspecialchars($item['icon_emoji']) ?>
                                    <?php endif; ?>
                                </div>
                                <div style="flex-grow: 1; min-width: 0;">
                                    <div style="font-size: 15px; font-weight: 800; color: var(--text-primary); margin-bottom: 2px;">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted); font-weight: 600;">
                                        หมวด: <?= htmlspecialchars($categories[$item['category']] ?? $item['category']) ?>
                                    </div>
                                </div>
                            </div>
                            <p style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.4; margin: 0 0 12px 0;">
                                <?= htmlspecialchars($item['description'] ?: 'ไม่มีคำอธิบายเพิ่มเติม') ?>
                            </p>
                        </div>

                        <div>
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; border-top: 1px dashed var(--border-color); padding-top: 10px;">
                                <div class="item-badge-points">
                                    ⭐ <strong><?= number_format($item['points_required']) ?></strong> แต้ม
                                </div>
                                <div class="item-badge-stock">
                                    📦 <?= $item['stock_quantity'] == -1 ? 'ไม่จำกัดสต็อก' : 'คงเหลือ ' . number_format($item['stock_quantity']) . ' ชิ้น' ?>
                                    • แลกแล้ว <?= number_format($item['redeemed_count']) ?>
                                </div>
                            </div>

                            <div style="display: flex; gap: 8px;">
                                <button type="button" onclick='openEditModal(<?= json_encode($item, JSON_UNESCAPED_UNICODE) ?>)' style="flex: 1; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); padding: 8px; border-radius: 10px; font-size: 12.5px; font-weight: 700; cursor: pointer;">
                                    ✏️ แก้ไข
                                </button>
                                <button type="button" onclick="deleteItem(<?= $item['item_id'] ?>, '<?= addslashes($item['title']) ?>')" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #EF4444; padding: 8px 12px; border-radius: 10px; font-size: 12.5px; font-weight: 700; cursor: pointer;">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tab 2: Category Management -->
        <div id="tab-content-categories" style="display: none;">
            <div style="background: var(--bg-card); border-radius: 20px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); overflow: hidden; padding: 14px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
                    <strong style="font-size: 15px; color: var(--text-primary);">รายการหมวดหมู่ของรางวัล</strong>
                    <button type="button" onclick="openCategoryModal()" class="preset-btn" style="background: var(--color-primary); color: white; border: none; padding: 8px 14px;">
                        ➕ เพิ่มหมวดหมู่ใหม่
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="queue-table">
                        <thead>
                            <tr>
                                <th>ไอคอน</th>
                                <th>ชื่อหมวดหมู่</th>
                                <th>รหัสหมวดหมู่ (Code)</th>
                                <th>จำนวนของรางวัล</th>
                                <th>ลำดับ</th>
                                <th>สถานะ</th>
                                <th>การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categoryList as $cat): ?>
                                <tr>
                                    <td style="font-size: 22px;"><?= htmlspecialchars($cat['icon_emoji']) ?></td>
                                    <td><strong><?= htmlspecialchars($cat['category_name']) ?></strong></td>
                                    <td><code style="background: var(--bg-main); padding: 3px 6px; border-radius: 6px;"><?= htmlspecialchars($cat['category_code']) ?></code></td>
                                    <td><?= number_format($cat['item_count']) ?> รายการ</td>
                                    <td><?= (int)$cat['sort_order'] ?></td>
                                    <td>
                                        <?= $cat['is_active'] ? '<span style="color: #10B981; font-weight: 800;">✅ เปิดใช้งาน</span>' : '<span style="color: #64748B;">🔒 ปิด</span>' ?>
                                    </td>
                                    <td>
                                        <button type="button" onclick='openEditCategoryModal(<?= json_encode($cat, JSON_UNESCAPED_UNICODE) ?>)' class="preset-btn">✏️ แก้ไข</button>
                                        <button type="button" onclick="deleteCategory('<?= $cat['category_code'] ?>', '<?= addslashes($cat['category_name']) ?>', <?= (int)$cat['item_count'] ?>)" class="preset-btn" style="color: #EF4444;">🗑️</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 3: Redemptions Queue -->
        <div id="tab-content-queue" style="display: none;">
            <div style="background: var(--bg-card); border-radius: 20px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); overflow: hidden; padding: 10px;">
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="preset-btn" onclick="fetchRedemptions('all')">ทั้งหมด</button>
                        <button type="button" class="preset-btn" onclick="fetchRedemptions('pending')">⏳ รอดำเนินการ</button>
                        <button type="button" class="preset-btn" onclick="fetchRedemptions('fulfilled')">✅ มอบของแล้ว</button>
                    </div>
                    <div>
                        <button type="button" onclick="fetchRedemptions()" style="background: none; border: none; color: var(--color-primary); font-size: 13px; font-weight: 700; cursor: pointer;">
                            🔄 รีเฟรชข้อมูล
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="queue-table">
                        <thead>
                            <tr>
                                <th>รหัสคำขอ</th>
                                <th>วันที่ขอแลก</th>
                                <th>อสม. ผู้ขอแลก</th>
                                <th>ของรางวัล</th>
                                <th>แต้มที่ใช้</th>
                                <th>สถานะ</th>
                                <th>การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="redemptionsTableBody">
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    กำลังโหลดข้อมูล... ⌛
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Add / Edit Item (With Real Image Upload) -->
    <div id="itemModal" class="reward-modal" onclick="closeItemModal(event)">
        <div class="reward-modal-content" onclick="event.stopPropagation()">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h3 id="modalTitle" style="margin: 0; color: var(--color-accent); font-size: 18px; font-weight: 800;">
                    ➕ เพิ่มของรางวัลใหม่
                </h3>
                <button type="button" onclick="closeItemModal()" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form id="itemForm" onsubmit="handleSaveItem(event)" enctype="multipart/form-data">
                <input type="hidden" name="item_id" id="form_item_id" value="0">
                <input type="hidden" name="image_url" id="form_image_url" value="">

                <div class="form-row">
                    <label>ชื่อของรางวัล <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="title" id="form_title" class="form-input" required placeholder="เช่น ร่มพับกันแดด อสม.ตาลสุม">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-row">
                        <label>คะแนนที่ใช้แลก (แต้ม) <span style="color: #EF4444;">*</span></label>
                        <input type="number" name="points_required" id="form_points" class="form-input" min="1" required value="15">
                    </div>
                    <div class="form-row">
                        <label>ไอคอน Emoji</label>
                        <input type="text" name="icon_emoji" id="form_emoji" class="form-input" value="🎁" maxlength="10">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-row">
                        <label>หมวดหมู่</label>
                        <select name="category" id="form_category" class="form-input">
                            <?php foreach ($categories as $catCode => $catName): ?>
                                <option value="<?= htmlspecialchars($catCode) ?>"><?= htmlspecialchars($catName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>จำนวนสต็อก (-1 = ไม่จำกัด)</label>
                        <input type="number" name="stock_quantity" id="form_stock" class="form-input" value="-1">
                    </div>
                </div>

                <!-- Real Image Upload / Attachment -->
                <div class="form-row">
                    <label>📷 แนบภาพประกอบของรางวัล (จริง)</label>
                    <input type="file" name="image_file" id="form_image_file" accept="image/*" class="form-input" onchange="previewSelectedImage(this)">
                    <div id="image_preview_container" style="display: none; margin-top: 10px; text-align: center;">
                        <img id="image_preview_box" src="" alt="Preview" style="max-height: 120px; border-radius: 12px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color);">
                    </div>
                </div>

                <div class="form-row">
                    <label>รายละเอียดของรางวัล</label>
                    <textarea name="description" id="form_description" rows="2" class="form-input" placeholder="คำอธิบายสั้นๆ ของสิ่งของ..."></textarea>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                    <input type="checkbox" name="is_active" id="form_active" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
                    <label for="form_active" style="margin: 0; font-size: 13.5px; font-weight: 700; cursor: pointer;">เปิดให้แสดงในแคตตาล็อก</label>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" id="btnSaveItem" class="btn-giant btn-giant-primary" style="margin: 0; padding: 12px; font-size: 14px; border-radius: 12px; flex: 1;">
                        💾 บันทึกข้อมูล
                    </button>
                    <button type="button" onclick="closeItemModal()" class="btn-dash-action" style="padding: 12px 18px; border-radius: 12px;">
                        ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add / Edit Category -->
    <div id="categoryModal" class="reward-modal" onclick="closeCategoryModal(event)">
        <div class="reward-modal-content" onclick="event.stopPropagation()">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h3 id="catModalTitle" style="margin: 0; color: var(--color-accent); font-size: 18px; font-weight: 800;">
                    ➕ จัดการหมวดหมู่ของรางวัล
                </h3>
                <button type="button" onclick="closeCategoryModal()" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form id="categoryForm" onsubmit="handleSaveCategory(event)">
                <input type="hidden" name="is_edit" id="form_cat_is_edit" value="0">

                <div class="form-row">
                    <label>รหัสหมวดหมู่ (Code ภาษาอังกฤษ) <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="category_code" id="form_cat_code" class="form-input" required placeholder="เช่น uniform, tech, wellness">
                </div>

                <div class="form-row">
                    <label>ชื่อหมวดหมู่ (ภาษาไทย) <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="category_name" id="form_cat_name" class="form-input" required placeholder="เช่น เสื้อและเครื่องแต่งกาย">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-row">
                        <label>ไอคอน Emoji</label>
                        <input type="text" name="icon_emoji" id="form_cat_emoji" class="form-input" value="📦" maxlength="10">
                    </div>
                    <div class="form-row">
                        <label>ลำดับการแสดง</label>
                        <input type="number" name="sort_order" id="form_cat_sort" class="form-input" value="0">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 14px;">
                    <button type="submit" id="btnSaveCat" class="btn-giant btn-giant-primary" style="margin: 0; padding: 12px; font-size: 14px; border-radius: 12px; flex: 1;">
                        💾 บันทึกหมวดหมู่
                    </button>
                    <button type="button" onclick="closeCategoryModal()" class="btn-dash-action" style="padding: 12px 18px; border-radius: 12px;">
                        ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchRewardTab(tabName) {
            document.getElementById('tab-content-catalog').style.display = tabName === 'catalog' ? 'block' : 'none';
            document.getElementById('tab-content-categories').style.display = tabName === 'categories' ? 'block' : 'none';
            document.getElementById('tab-content-queue').style.display = tabName === 'queue' ? 'block' : 'none';
            
            document.getElementById('tab-btn-catalog').classList.toggle('active', tabName === 'catalog');
            document.getElementById('tab-btn-categories').classList.toggle('active', tabName === 'categories');
            document.getElementById('tab-btn-queue').classList.toggle('active', tabName === 'queue');

            if (tabName === 'queue') {
                fetchRedemptions();
            }
        }

        function toggleSystem(newVal) {
            const outer = document.getElementById('neuSwitchOuter');
            const track = document.getElementById('neuSwitchTrack');
            
            if (outer) outer.style.pointerEvents = 'none';
            if (track) track.style.opacity = '0.6';

            fetch('../api/rewards.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'admin_toggle_system', enabled: newVal })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (data.message || 'ไม่สามารถเปลี่ยนสถานะได้'));
                    if (outer) outer.style.pointerEvents = 'auto';
                    if (track) track.style.opacity = '1';
                }
            })
            .catch(err => {
                alert('เชื่อมต่อล้มเหลว: ' + err);
                if (outer) outer.style.pointerEvents = 'auto';
                if (track) track.style.opacity = '1';
            });
        }

        function previewSelectedImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('image_preview_box').src = e.target.result;
                    document.getElementById('image_preview_container').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function openItemModal() {
            document.getElementById('modalTitle').innerText = '➕ เพิ่มของรางวัลใหม่';
            document.getElementById('form_item_id').value = 0;
            document.getElementById('form_title').value = '';
            document.getElementById('form_description').value = '';
            document.getElementById('form_points').value = 15;
            document.getElementById('form_emoji').value = '🎁';
            document.getElementById('form_image_url').value = '';
            document.getElementById('form_image_file').value = '';
            document.getElementById('image_preview_container').style.display = 'none';
            document.getElementById('form_stock').value = -1;
            document.getElementById('form_active').checked = true;
            document.getElementById('itemModal').style.display = 'flex';
        }

        function openEditModal(item) {
            document.getElementById('modalTitle').innerText = '✏️ แก้ไขของรางวัล';
            document.getElementById('form_item_id').value = item.item_id;
            document.getElementById('form_title').value = item.title;
            document.getElementById('form_description').value = item.description || '';
            document.getElementById('form_points').value = item.points_required;
            document.getElementById('form_emoji').value = item.icon_emoji || '🎁';
            document.getElementById('form_category').value = item.category || 'equipment';
            document.getElementById('form_stock').value = item.stock_quantity;
            document.getElementById('form_image_url').value = item.image_url || '';
            document.getElementById('form_image_file').value = '';
            
            if (item.image_url) {
                document.getElementById('image_preview_box').src = '../' + item.image_url;
                document.getElementById('image_preview_container').style.display = 'block';
            } else {
                document.getElementById('image_preview_container').style.display = 'none';
            }

            document.getElementById('form_active').checked = item.is_active == 1;
            document.getElementById('itemModal').style.display = 'flex';
        }

        function closeItemModal() {
            document.getElementById('itemModal').style.display = 'none';
        }

        function handleSaveItem(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveItem');
            btn.disabled = true;
            btn.innerText = 'กำลังบันทึก... ⌛';

            const form = document.getElementById('itemForm');
            const formData = new FormData(form);
            formData.append('action', 'admin_save_item');
            if (!document.getElementById('form_active').checked) {
                formData.set('is_active', '0');
            }

            fetch('../api/rewards.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                    btn.disabled = false;
                    btn.innerText = '💾 บันทึกข้อมูล';
                }
            })
            .catch(err => {
                alert('เชื่อมต่อล้มเหลว: ' + err);
                btn.disabled = false;
                btn.innerText = '💾 บันทึกข้อมูล';
            });
        }

        function deleteItem(itemId, title) {
            if (!confirm(`คุณต้องการลบของรางวัล "${title}" ใช่หรือไม่?`)) return;

            fetch('../api/rewards.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'admin_delete_item', item_id: itemId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    const card = document.getElementById('item-card-' + itemId);
                    if (card) card.remove();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(err => alert('เชื่อมต่อล้มเหลว: ' + err));
        }

        function openCategoryModal() {
            document.getElementById('catModalTitle').innerText = '➕ เพิ่มหมวดหมู่ใหม่';
            document.getElementById('form_cat_is_edit').value = '0';
            document.getElementById('form_cat_code').value = '';
            document.getElementById('form_cat_code').readOnly = false;
            document.getElementById('form_cat_name').value = '';
            document.getElementById('form_cat_emoji').value = '📦';
            document.getElementById('form_cat_sort').value = '0';
            document.getElementById('categoryModal').style.display = 'flex';
        }

        function openEditCategoryModal(cat) {
            document.getElementById('catModalTitle').innerText = '✏️ แก้ไขหมวดหมู่';
            document.getElementById('form_cat_is_edit').value = '1';
            document.getElementById('form_cat_code').value = cat.category_code;
            document.getElementById('form_cat_code').readOnly = true;
            document.getElementById('form_cat_name').value = cat.category_name;
            document.getElementById('form_cat_emoji').value = cat.icon_emoji || '📦';
            document.getElementById('form_cat_sort').value = cat.sort_order || '0';
            document.getElementById('categoryModal').style.display = 'flex';
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }

        function handleSaveCategory(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveCat');
            btn.disabled = true;
            btn.innerText = 'กำลังบันทึก... ⌛';

            const form = document.getElementById('categoryForm');
            const formData = new FormData(form);
            formData.append('action', 'admin_save_category');

            fetch('../api/rewards.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                    btn.disabled = false;
                    btn.innerText = '💾 บันทึกหมวดหมู่';
                }
            })
            .catch(err => {
                alert('เชื่อมต่อล้มเหลว: ' + err);
                btn.disabled = false;
                btn.innerText = '💾 บันทึกหมวดหมู่';
            });
        }

        function deleteCategory(code, name, itemCount) {
            if (itemCount > 0) {
                alert(`ไม่สามารถลบหมวดหมู่ "${name}" ได้ เนื่องจากมีของรางวัลอยู่ในหมวดนี้ ${itemCount} รายการ`);
                return;
            }
            if (!confirm(`คุณต้องการลบหมวดหมู่ "${name}" ใช่หรือไม่?`)) return;

            fetch('../api/rewards.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'admin_delete_category', category_code: code })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(err => alert('เชื่อมต่อล้มเหลว: ' + err));
        }

        function fetchRedemptions(status = 'all') {
            const tbody = document.getElementById('redemptionsTableBody');
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">กำลังโหลดข้อมูล... ⌛</td></tr>';

            fetch('../api/rewards.php?action=admin_get_redemptions&status=' + status)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderRedemptionsTable(data.redemptions || []);
                    } else {
                        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #EF4444; padding: 20px;">${data.message}</td></tr>`;
                    }
                })
                .catch(err => {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #EF4444; padding: 20px;">เชื่อมต่อล้มเหลว: ${err}</td></tr>`;
                });
        }

        function renderRedemptionsTable(list) {
            const tbody = document.getElementById('redemptionsTableBody');
            if (list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">📭 ยังไม่มีรายการคำขอแลกของรางวัล</td></tr>';
                return;
            }

            let html = '';
            list.forEach(r => {
                let badge = '<span style="background: rgba(245, 158, 11, 0.15); color: #D97706; padding: 3px 8px; border-radius: 8px; font-weight: 800; font-size: 11px;">⏳ รอดำเนินการ</span>';
                if (r.status === 'fulfilled') {
                    badge = '<span style="background: rgba(16, 185, 129, 0.15); color: #059669; padding: 3px 8px; border-radius: 8px; font-weight: 800; font-size: 11px;">✅ มอบของแล้ว</span>';
                } else if (r.status === 'cancelled') {
                    badge = '<span style="background: rgba(239, 68, 68, 0.15); color: #DC2626; padding: 3px 8px; border-radius: 8px; font-weight: 800; font-size: 11px;">❌ ยกเลิก</span>';
                }

                let actions = '';
                if (r.status === 'pending') {
                    actions = `
                        <button type="button" onclick="fulfillRedemption(${r.redemption_id}, 'fulfilled')" style="background: #10B981; color: white; border: none; padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer;">
                            ✅ มอบของ
                        </button>
                        <button type="button" onclick="fulfillRedemption(${r.redemption_id}, 'cancelled')" style="background: none; border: 1px solid #EF4444; color: #EF4444; padding: 3px 8px; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; margin-left: 4px;">
                            ยกเลิก
                        </button>
                    `;
                } else {
                    actions = `<span style="color: var(--text-muted); font-size: 11px;">${r.fulfilled_by || '-'}</span>`;
                }

                html += `
                    <tr>
                        <td><strong style="font-family: monospace; color: var(--color-primary); font-size: 14px;">${r.redemption_code}</strong></td>
                        <td style="font-size: 12px; color: var(--text-secondary); white-space: nowrap;">${r.created_at ? r.created_at.substring(0, 16) : '-'}</td>
                        <td>
                            <strong>${r.vhv_name || 'อสม.'}</strong>
                            <div style="font-size: 11.5px; color: var(--text-muted);">หมู่ ${r.vhv_moo || '-'} • โทร: ${r.vhv_phone || '-'}</div>
                        </td>
                        <td>
                            <span>${r.icon_emoji || '🎁'} ${r.item_title}</span>
                        </td>
                        <td><strong style="color: #059669;">${r.points_spent} แต้ม</strong></td>
                        <td>${badge}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function fulfillRedemption(redemptionId, status) {
            const promptMsg = status === 'fulfilled' ? 'ยืนยันการส่งมอบของรางวัลให้กับ อสม. ใช่หรือไม่?' : 'ยืนยันการยกเลิกคำขอนี้ใช่หรือไม่? (แต้มจะถูกคืนให้ อสม.)';
            if (!confirm(promptMsg)) return;

            fetch('../api/rewards.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'admin_fulfill_redemption',
                    redemption_id: redemptionId,
                    status: status
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    fetchRedemptions();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(err => alert('เชื่อมต่อล้มเหลว: ' + err));
        }
    </script>
</body>
</html>
