<?php
// vhv/rewards.php - หน้าร้านค้าแลกของรางวัล อสม. (VHV Reward & Point Redemption Store)
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/demo_banner.php';

$vhvId = (int)$_SESSION['vhv_id'];
$vhvName = $_SESSION['vhv_name'] ?? 'อสม.';
$isSandbox = function_exists('isSandboxMode') && isSandboxMode() ? 1 : 0;

$systemEnabled = (int)get_system_setting('reward_system_enabled', 0);

// Calculate user's points
$totalEarned = 0.0;
$pointsSpent = 0.0;
try {
    $stmtPts = $pdo->prepare("
        SELECT COALESCE(SUM(points_earned), 0) 
        FROM `vhv_rewards` 
        WHERE `vhv_id` = ? AND `approval_status` IN ('approved', 'waiting') AND `is_sandbox` = ?
    ");
    $stmtPts->execute([$vhvId, $isSandbox]);
    $totalEarned = (float)$stmtPts->fetchColumn();

    $stmtSpent = $pdo->prepare("
        SELECT COALESCE(SUM(points_spent), 0) 
        FROM `reward_redemptions` 
        WHERE `vhv_id` = ? AND `status` IN ('pending', 'fulfilled')
    ");
    $stmtSpent->execute([$vhvId]);
    $pointsSpent = (float)$stmtSpent->fetchColumn();
} catch (\Exception $e) {}

$availablePoints = max(0.0, $totalEarned - $pointsSpent);

// Fetch active items
$items = [];
try {
    $items = $pdo->query("SELECT * FROM `reward_items` WHERE `is_active` = 1 ORDER BY `sort_order` ASC, `points_required` ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $items = [];
}

// Fetch my redemptions
$myRedemptions = [];
try {
    $stmtMy = $pdo->prepare("
        SELECT r.*, i.title as item_title, i.category, i.icon_emoji 
        FROM `reward_redemptions` r
        JOIN `reward_items` i ON r.item_id = i.item_id
        WHERE r.vhv_id = ?
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $stmtMy->execute([$vhvId]);
    $myRedemptions = $stmtMy->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $myRedemptions = [];
}
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>ศูนย์แลกของรางวัล อสม. - NCDs Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .store-container {
            max-width: 680px;
            margin: 0 auto;
            padding: 16px 16px 100px 16px;
        }

        /* Top Points Hero Card */
        .points-hero-card {
            background: linear-gradient(135deg, #0D2C54 0%, #1A3E6D 100%);
            color: #ffffff;
            border-radius: 24px;
            padding: 22px 20px;
            box-shadow: 0 10px 25px rgba(13, 44, 84, 0.25);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .points-hero-card::after {
            content: "🎁";
            position: absolute;
            right: -10px;
            bottom: -15px;
            font-size: 85px;
            opacity: 0.15;
            pointer-events: none;
        }

        .points-value-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin: 8px 0 14px 0;
        }

        .points-big-num {
            font-size: 42px;
            font-weight: 900;
            line-height: 1;
            color: #FCD34D;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .points-breakdown-bar {
            display: flex;
            gap: 12px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            padding: 10px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .points-sub-item {
            flex: 1;
        }

        .points-sub-label {
            font-size: 11px;
            opacity: 0.8;
            font-weight: 600;
        }

        .points-sub-val {
            font-size: 14px;
            font-weight: 800;
        }

        /* Coming Soon / Preview Banner */
        .banner-coming-soon {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
            border: 1.5px solid #FCD34D;
            border-radius: 20px;
            padding: 16px 18px;
            margin-bottom: 20px;
            box-shadow: var(--neumorph-flat);
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        [data-theme="dark"] .banner-coming-soon {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.2));
            border-color: rgba(245, 158, 11, 0.4);
        }

        .banner-active {
            background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
            border: 1.5px solid #6EE7B7;
            border-radius: 20px;
            padding: 14px 18px;
            margin-bottom: 20px;
            box-shadow: var(--neumorph-flat);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Category Filter Chips */
        .category-scroll {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 12px;
            margin-bottom: 16px;
            scrollbar-width: none;
        }
        .category-scroll::-webkit-scrollbar { display: none; }

        .category-chip {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s;
        }

        .category-chip.active {
            background: var(--color-primary);
            color: #ffffff;
            border-color: var(--color-primary);
        }

        /* Reward Cards Grid */
        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .reward-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 18px;
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .reward-card:hover {
            transform: translateY(-2px);
        }

        .reward-top-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
        }

        .reward-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            background: var(--bg-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
            box-shadow: var(--neumorph-inset);
        }

        .reward-points-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(245, 158, 11, 0.12);
            color: #D97706;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 800;
        }

        /* Progress Mini Bar */
        .progress-box {
            margin: 10px 0 14px 0;
        }

        .progress-label-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-secondary);
        }

        .progress-track {
            height: 6px;
            background: var(--bg-main);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--neumorph-inset);
        }

        .progress-fill {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, #10B981, #059669);
            transition: width 0.4s ease;
        }

        .btn-redeem {
            width: 100%;
            padding: 12px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-redeem-active {
            background: linear-gradient(135deg, #00A878, #059669);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 168, 120, 0.3);
        }
        .btn-redeem-active:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 168, 120, 0.4);
        }

        .btn-redeem-locked {
            background: var(--bg-main);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            cursor: not-allowed;
        }

        /* Modal Confirmation */
        .confirm-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 44, 84, 0.65);
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .confirm-modal-box {
            background: var(--bg-card);
            border-radius: 24px;
            padding: 26px 22px;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            text-align: center;
        }
    </style>
</head>
<body class="vhv-body">

    <div class="store-container">
        
        <!-- Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="index.php" style="display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 12px; background: var(--bg-card); color: var(--text-primary); text-decoration: none; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color);">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <div>
                    <h2 style="margin: 0; font-size: 19px; font-weight: 800; color: var(--text-primary);">
                        🎁 ศูนย์แลกของรางวัล อสม.
                    </h2>
                    <span style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($vhvName) ?></span>
                </div>
            </div>
            <div>
                <button type="button" onclick="openMyRedemptionsModal()" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--color-primary); padding: 8px 14px; border-radius: 12px; font-size: 12.5px; font-weight: 800; cursor: pointer; box-shadow: var(--neumorph-flat);">
                    📜 ประวัติของฉัน (<?= count($myRedemptions) ?>)
                </button>
            </div>
        </div>

        <!-- 1. Points Hero Card -->
        <div class="points-hero-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 13px; font-weight: 700; opacity: 0.9;">
                    <?= $systemEnabled ? '⭐ คะแนนพร้อมแลกรางวัล' : '⭐ คะแนนผลงานสะสมของคุณ' ?>
                </span>
                <span style="background: rgba(255, 255, 255, 0.2); padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 800;">ปีงบประมาณ <?= function_exists('get_current_budget_year') ? get_current_budget_year() + 543 : '2569' ?></span>
            </div>

            <div class="points-value-row">
                <span class="points-big-num"><?= number_format($availablePoints, 1) ?></span>
                <span style="font-size: 16px; font-weight: 800; opacity: 0.9;">แต้ม</span>
            </div>

            <div class="points-breakdown-bar">
                <div class="points-sub-item">
                    <div class="points-sub-label">คะแนนสะสมทั้งหมด</div>
                    <div class="points-sub-val"><?= number_format($totalEarned, 1) ?> แต้ม</div>
                </div>
                <div class="points-sub-item" style="border-left: 1px solid rgba(255,255,255,0.2); padding-left: 12px;">
                    <?php if ($systemEnabled): ?>
                        <div class="points-sub-label">แลกไปแล้ว</div>
                        <div class="points-sub-val"><?= number_format($pointsSpent, 1) ?> แต้ม</div>
                    <?php else: ?>
                        <div class="points-sub-label">สถานะระบบ</div>
                        <div class="points-sub-val" style="color: #FCD34D;">รอเปิดให้แลกรางวัล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 2. System Status Banner -->
        <?php if (!$systemEnabled): ?>
            <div class="banner-coming-soon">
                <span style="font-size: 26px;">🎁</span>
                <div>
                    <strong style="font-size: 14.5px; color: #92400E; display: block; margin-bottom: 2px;">
                        ของรางวัลเตรียมเปิดให้แลกเร็วๆ นี้ (Preview Mode)
                    </strong>
                    <p style="margin: 0; font-size: 12.5px; color: #B45309; line-height: 1.45;">
                        เจ้าหน้าที่ รพ.สต. กำลังจัดเตรียมรายการของรางวัลและกำหนดเกณฑ์คะแนน ท่านสามารถสะสมแต้มจากการลงพื้นที่คัดกรองรอไว้ได้เลยค่ะ!
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="banner-active">
                <span style="font-size: 22px;">🎉</span>
                <div style="font-size: 13px; color: #065F46; font-weight: 700;">
                    เปิดให้แลกของรางวัลแล้ว! เลือกของรางวัลที่ถูกใจและกดแลกรับสิทธิ์ได้ทันที
                </div>
            </div>
        <?php endif; ?>

        <!-- 3. Category Filter Chips -->
        <div class="category-scroll">
            <button type="button" class="category-chip active" onclick="filterCategory('all', this)">ทั้งหมด</button>
            <button type="button" class="category-chip" onclick="filterCategory('equipment', this)">🧴 อุปกรณ์ลงพื้นที่</button>
            <button type="button" class="category-chip" onclick="filterCategory('souvenir', this)">☂️ ของที่ระลึก</button>
            <button type="button" class="category-chip" onclick="filterCategory('medical', this)">🩺 เครื่องมือแพทย์</button>
            <button type="button" class="category-chip" onclick="filterCategory('honorary', this)">🏆 เชิดชูเกียรติ</button>
        </div>

        <!-- 4. Rewards Grid -->
        <div class="rewards-grid">
            <?php foreach ($items as $it): ?>
                <?php
                    $reqPts = (float)$it['points_required'];
                    $pct = min(100, round(($availablePoints / $reqPts) * 100));
                    $canAfford = $availablePoints >= $reqPts;
                    $isOutOfStock = ($it['stock_quantity'] != -1 && $it['stock_quantity'] <= 0);
                ?>
                <div class="reward-card item-card-box" data-category="<?= htmlspecialchars($it['category']) ?>">
                    <div>
                        <div class="reward-top-row">
                            <div class="reward-icon-box"><?= htmlspecialchars($it['icon_emoji']) ?></div>
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="font-size: 15px; font-weight: 800; color: var(--text-primary); margin-bottom: 2px;">
                                    <?= htmlspecialchars($it['title']) ?>
                                </div>
                                <?php if ($systemEnabled): ?>
                                    <div class="reward-points-badge">
                                        ⭐ <strong><?= number_format($reqPts) ?></strong> แต้ม
                                    </div>
                                <?php else: ?>
                                    <div class="reward-points-badge" style="background: rgba(100, 116, 139, 0.12); color: #64748B;">
                                        ✨ รายการของรางวัล
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.4; margin: 0 0 8px 0;">
                            <?= htmlspecialchars($it['description'] ?: 'ไม่มีคำอธิบายเพิ่มเติม') ?>
                        </p>

                        <?php if ($systemEnabled): ?>
                            <!-- Progress Bar (Active Mode) -->
                            <div class="progress-box">
                                <div class="progress-label-row">
                                    <span>ความคืบหน้า</span>
                                    <span><?= number_format($availablePoints, 1) ?> / <?= number_format($reqPts) ?> แต้ม</span>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill" style="width: <?= $pct ?>%; <?= $canAfford ? 'background: #10B981;' : 'background: #F59E0B;' ?>"></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Coming Soon Note (Preview Mode - Hide Points) -->
                            <div style="font-size: 11.5px; color: var(--text-muted); margin: 6px 0 12px 0; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                                <span>⏳</span> <span>รอประกาศเกณฑ์คะแนนเร็วๆ นี้</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <?php if (!$systemEnabled): ?>
                            <button type="button" class="btn-redeem btn-redeem-locked" disabled>
                                🔒 เร็วๆ นี้ (เปิดให้แลกเร็วๆ นี้)
                            </button>
                        <?php elseif ($isOutOfStock): ?>
                            <button type="button" class="btn-redeem btn-redeem-locked" disabled>
                                ❌ ของรางวัลหมดชั่วคราว
                            </button>
                        <?php elseif (!$canAfford): ?>
                            <?php $diff = $reqPts - $availablePoints; ?>
                            <button type="button" class="btn-redeem btn-redeem-locked" disabled>
                                🔒 ขาดอีก <?= number_format($diff, 1) ?> แต้ม
                            </button>
                        <?php else: ?>
                            <button type="button" onclick='confirmRedeem(<?= json_encode($it, JSON_UNESCAPED_UNICODE) ?>)' class="btn-redeem btn-redeem-active">
                                🎁 แลกของรางวัลนี้
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- Modal: Confirm Redeem -->
    <div id="confirmModal" class="confirm-modal" onclick="closeConfirmModal(event)">
        <div class="confirm-modal-box" onclick="event.stopPropagation()">
            <div id="modalIcon" style="font-size: 54px; margin-bottom: 10px;">🎁</div>
            <h3 id="modalTitle" style="margin: 0 0 6px 0; font-size: 18px; font-weight: 800; color: var(--text-primary);">
                ยืนยันการแลกของรางวัล
            </h3>
            <p id="modalDesc" style="font-size: 13px; color: var(--text-secondary); margin: 0 0 16px 0;"></p>

            <div style="background: var(--bg-main); padding: 14px; border-radius: 16px; margin-bottom: 20px; text-align: left; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="color: var(--text-secondary);">แต้มที่ต้องใช้:</span>
                    <strong id="modalCost" style="color: #D97706;">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">แต้มคงเหลือหลังแลก:</span>
                    <strong id="modalRemaining" style="color: #059669;">-</strong>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="button" id="btnSubmitRedeem" onclick="submitRedeem()" class="btn-redeem btn-redeem-active" style="flex: 1;">
                    ยืนยันการแลก ✨
                </button>
                <button type="button" onclick="closeConfirmModal()" class="btn-redeem btn-redeem-locked" style="width: auto; padding: 12px 18px;">
                    ยกเลิก
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: My Redemptions History -->
    <div id="myRedemptionsModal" class="confirm-modal" onclick="closeMyRedemptionsModal(event)">
        <div class="confirm-modal-box" onclick="event.stopPropagation()" style="max-width: 520px; max-height: 80vh; overflow-y: auto; text-align: left;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--color-accent);">
                    📜 ประวัติการแลกของรางวัลของฉัน
                </h3>
                <button type="button" onclick="closeMyRedemptionsModal()" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php if (empty($myRedemptions)): ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 30px 10px;">
                        <div style="font-size: 36px; margin-bottom: 8px;">📭</div>
                        <div style="font-weight: 700;">ยังไม่มีประวัติการแลกของรางวัล</div>
                        <div style="font-size: 12px; margin-top: 2px;">เมื่อกดแลกของรางวัล รายการจะแสดงที่นี่</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($myRedemptions as $mr): ?>
                        <?php
                            $statusBadge = '<span style="background:rgba(245,158,11,0.15); color:#D97706; padding:2px 8px; border-radius:8px; font-size:11px; font-weight:800;">⏳ รอรับของที่ รพ.สต.</span>';
                            if ($mr['status'] === 'fulfilled') {
                                $statusBadge = '<span style="background:rgba(16,185,129,0.15); color:#059669; padding:2px 8px; border-radius:8px; font-size:11px; font-weight:800;">✅ รับของแล้ว</span>';
                            } elseif ($mr['status'] === 'cancelled') {
                                $statusBadge = '<span style="background:rgba(239,68,68,0.15); color:#DC2626; padding:2px 8px; border-radius:8px; font-size:11px; font-weight:800;">❌ ยกเลิก</span>';
                            }
                        ?>
                        <div style="background: var(--bg-main); border-radius: 14px; padding: 14px; box-shadow: var(--neumorph-inset); border: 1px solid var(--border-color);">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                <strong style="font-size: 14.5px; color: var(--text-primary);">
                                    <?= htmlspecialchars($mr['icon_emoji']) ?> <?= htmlspecialchars($mr['item_title']) ?>
                                </strong>
                                <?= $statusBadge ?>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: var(--text-secondary);">
                                <span>รหัสรับของ: <strong style="font-family: monospace; color: var(--color-primary);"><?= htmlspecialchars($mr['redemption_code']) ?></strong></span>
                                <span>ใช้ <?= number_format($mr['points_spent']) ?> แต้ม</span>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                🕒 วันที่ขอ: <?= htmlspecialchars(substr($mr['created_at'], 0, 16)) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation Bar -->
    <div class="bottom-nav-container no-print">
        <div class="bottom-nav">
            <a href="index.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                หน้าแรก
            </a>
            <a href="scan.php" class="nav-link nav-scan-fab fab-scan-pulse">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                </svg>
                <span>สแกนบ้าน</span>
            </a>
            <a href="rewards.php" class="nav-link active">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V6a2 2 0 10-2 2h2zm0 13a7 7 0 100-14 7 7 0 000 14z"></path>
                </svg>
                แลกรางวัล
            </a>
            <a href="leaderboard.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                </svg>
                คะแนน
            </a>
            <a href="profile.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                ข้อมูลฉัน
            </a>
        </div>
    </div>

    <script>
        let selectedItem = null;
        const currentPoints = <?= (float)$availablePoints ?>;

        function filterCategory(cat, btn) {
            document.querySelectorAll('.category-chip').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const cards = document.querySelectorAll('.item-card-box');
            cards.forEach(card => {
                if (cat === 'all' || card.getAttribute('data-category') === cat) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function confirmRedeem(item) {
            selectedItem = item;
            document.getElementById('modalIcon').innerText = item.icon_emoji || '🎁';
            document.getElementById('modalTitle').innerText = item.title;
            document.getElementById('modalDesc').innerText = item.description || 'กรุณายืนยันการขอแลกของรางวัลนี้';
            document.getElementById('modalCost').innerText = item.points_required + ' แต้ม';
            document.getElementById('modalRemaining').innerText = (currentPoints - parseFloat(item.points_required)).toFixed(1) + ' แต้ม';
            document.getElementById('confirmModal').style.display = 'flex';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
            selectedItem = null;
        }

        function submitRedeem() {
            if (!selectedItem) return;

            const btn = document.getElementById('btnSubmitRedeem');
            btn.disabled = true;
            btn.innerText = 'กำลังทำรายการ... ⌛';

            fetch('../api/rewards.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'redeem_item', item_id: selectedItem.item_id })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                    btn.disabled = false;
                    btn.innerText = 'ยืนยันการแลก ✨';
                }
            })
            .catch(err => {
                alert('เชื่อมต่อล้มเหลว: ' + err);
                btn.disabled = false;
                btn.innerText = 'ยืนยันการแลก ✨';
            });
        }

        function openMyRedemptionsModal() {
            document.getElementById('myRedemptionsModal').style.display = 'flex';
        }

        function closeMyRedemptionsModal() {
            document.getElementById('myRedemptionsModal').style.display = 'none';
        }
    </script>
</body>
</html>
