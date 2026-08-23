<?php
// vhv/rewards.php - หน้าร้านค้าแลกของรางวัล อสม. (VHV Reward & Point Redemption Store)
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/demo_banner.php';

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

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
        // Immediately apply theme before rendering
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NCDs Portal">
    <meta name="application-name" content="NCDs Portal">
    <meta name="theme-color" content="#0d2c54">
    <title>ศูนย์แลกของรางวัล อสม. - NCDs <?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="apple-touch-icon" href="../assets/icon.png">
    <link rel="manifest" href="manifest.json">
    <style>
        /* Category Filter Chips */
        .category-scroll {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 16px;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        .category-scroll::-webkit-scrollbar { display: none; }

        .category-chip {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 7px 14px;
            border-radius: 50px;
            font-size: 12.5px;
            font-weight: 800;
            white-space: nowrap;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .category-chip.active {
            background: var(--color-primary);
            color: #ffffff;
            border-color: var(--color-primary);
        }

        /* Rewards List Layout */
        .rewards-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 24px;
        }

        .reward-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 16px;
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .reward-card:active {
            transform: scale(0.99);
        }

        .reward-top-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 8px;
        }

        .reward-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: var(--bg-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            box-shadow: var(--neumorph-inset);
        }

        .reward-points-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(245, 158, 11, 0.12);
            color: #D97706;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
        }

        /* Progress Bar */
        .progress-box {
            margin: 8px 0 12px 0;
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
            padding: 11px;
            border-radius: 12px;
            font-size: 13.5px;
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
            transform: translateY(-1px);
        }

        .btn-redeem-locked {
            background: var(--bg-main);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            cursor: not-allowed;
        }

        /* Modal */
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
            border-radius: 22px;
            padding: 22px 20px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            text-align: center;
        }
    </style>
</head>
<body class="vhv-accessibility">
    <div class="mobile-wrapper" style="padding-bottom: 90px;">
        
        <!-- Header (Unified with leaderboard and index) -->
        <div class="vhv-header" style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
            <div>
                <h3 style="color: var(--color-accent); margin: 0; font-size: 16px; font-weight: 800;">🎁 ศูนย์ของรางวัล อสม.</h3>
                <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 13px;">รายการของรางวัล & สะสมแต้มรับสิทธิ์</p>
            </div>
            <button type="button" onclick="openMyRedemptionsModal()" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--color-primary); padding: 7px 12px; border-radius: 12px; font-size: 12px; font-weight: 800; cursor: pointer; box-shadow: var(--neumorph-flat); white-space: nowrap; flex-shrink: 0;">
                📜 ประวัติ (<?= count($myRedemptions) ?>)
            </button>
        </div>

        <!-- 1. Current Points Card (Matching Card-Dark Pattern) -->
        <div class="card-dark" style="padding: 18px; box-shadow: var(--neumorph-flat); margin-bottom: 18px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: center; text-align: center;">
                <div style="border-right: 1px solid rgba(13, 44, 84, 0.1); padding-right: 8px;">
                    <span style="color: var(--text-secondary); font-size: 12px; font-weight: bold; display: block; margin-bottom: 2px;">
                        <?= $systemEnabled ? 'คะแนนพร้อมแลก' : 'คะแนนสะสมของคุณ' ?>
                    </span>
                    <div style="font-size: 30px; font-weight: 800; color: var(--color-accent); line-height: 1.1;">
                        <?= (float)$availablePoints ?> <span style="font-size: 15px; color: var(--text-secondary); font-weight: normal;">แต้ม</span>
                    </div>
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-size: 12px; font-weight: bold; display: block; margin-bottom: 2px;">
                        <?= $systemEnabled ? 'แลกไปแล้ว' : 'สถานะระบบ' ?>
                    </span>
                    <div style="font-size: <?= $systemEnabled ? '30px' : '16px' ?>; font-weight: 800; color: <?= $systemEnabled ? 'var(--text-primary)' : '#D97706' ?>; line-height: 1.1; margin-top: <?= $systemEnabled ? '0' : '6px' ?>;">
                        <?= $systemEnabled ? ((float)$pointsSpent . ' <span style="font-size: 15px; color: var(--text-secondary); font-weight: normal;">แต้ม</span>') : '⏳ เร็วๆ นี้' ?>
                    </div>
                </div>
            </div>
            <div style="margin-top: 14px; font-size: 12.5px; text-align: center; color: var(--text-primary); border-top: 1px solid rgba(13, 44, 84, 0.1); padding-top: 10px; font-weight: bold; line-height: 1.4;">
                <?= $systemEnabled 
                    ? '🎉 เปิดให้แลกของรางวัลแล้ว เลือกของรางวัลและกดแลกรับสิทธิ์ได้เลยค่ะ' 
                    : '🎁 กำลังจัดเตรียมรายการของรางวัล ท่านสามารถสะสมแต้มรอไว้ได้เลยค่ะ' ?>
            </div>
        </div>

        <!-- 2. Category Filter Chips -->
        <div class="category-scroll">
            <button type="button" class="category-chip active" onclick="filterCategory('all', this)">ทั้งหมด</button>
            <button type="button" class="category-chip" onclick="filterCategory('equipment', this)">🧴 อุปกรณ์ลงพื้นที่</button>
            <button type="button" class="category-chip" onclick="filterCategory('souvenir', this)">☂️ ของที่ระลึก</button>
            <button type="button" class="category-chip" onclick="filterCategory('medical', this)">🩺 เครื่องมือแพทย์</button>
            <button type="button" class="category-chip" onclick="filterCategory('honorary', this)">🏆 เชิดชูเกียรติ</button>
        </div>

        <!-- 3. Rewards List -->
        <div class="rewards-list">
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
                                <div style="font-size: 14.5px; font-weight: 800; color: var(--text-primary); margin-bottom: 2px;">
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

                        <p style="font-size: 12px; color: var(--text-secondary); line-height: 1.4; margin: 0 0 8px 0;">
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
                            <!-- Coming Soon Note (Preview Mode) -->
                            <div style="font-size: 11.5px; color: var(--text-muted); margin: 4px 0 10px 0; font-weight: 600; display: flex; align-items: center; gap: 4px;">
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

        <!-- Bottom Navigation Bar (Identical across all VHV pages) -->
        <div class="bottom-nav">
            <a href="index.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                หน้าแรก
            </a>
            <a href="scan.php" class="nav-link nav-scan-fab fab-scan-pulse">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                <span>สแกนบ้าน</span>
            </a>
            <a href="rewards.php" class="nav-link active">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V6a2 2 0 10-2 2h2zm0 13a7 7 0 100-14 7 7 0 000 14z"></path>
                </svg>
                แลกรางวัล
            </a>
            <a href="leaderboard.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                กระดานคะแนน
            </a>
            <a href="profile.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                ข้อมูลส่วนตัว
            </a>
        </div>

    </div>

    <!-- Modal: Confirm Redeem -->
    <div id="confirmModal" class="confirm-modal" onclick="closeConfirmModal(event)">
        <div class="confirm-modal-box" onclick="event.stopPropagation()">
            <div id="modalIcon" style="font-size: 48px; margin-bottom: 8px;">🎁</div>
            <h3 id="modalTitle" style="margin: 0 0 6px 0; font-size: 17px; font-weight: 800; color: var(--text-primary);">
                ยืนยันการแลกของรางวัล
            </h3>
            <p id="modalDesc" style="font-size: 12.5px; color: var(--text-secondary); margin: 0 0 14px 0;"></p>

            <div style="background: var(--bg-main); padding: 12px 14px; border-radius: 14px; margin-bottom: 18px; text-align: left; font-size: 13px;">
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
                <button type="button" onclick="closeConfirmModal()" class="btn-redeem btn-redeem-locked" style="width: auto; padding: 11px 16px;">
                    ยกเลิก
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: My Redemptions History -->
    <div id="myRedemptionsModal" class="confirm-modal" onclick="closeMyRedemptionsModal(event)">
        <div class="confirm-modal-box" onclick="event.stopPropagation()" style="max-width: 440px; max-height: 80vh; overflow-y: auto; text-align: left;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--color-accent);">
                    📜 ประวัติการแลกของรางวัล
                </h3>
                <button type="button" onclick="closeMyRedemptionsModal()" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php if (empty($myRedemptions)): ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 30px 10px;">
                        <div style="font-size: 32px; margin-bottom: 6px;">📭</div>
                        <div style="font-weight: 700; font-size: 13.5px;">ยังไม่มีประวัติการแลกของรางวัล</div>
                        <div style="font-size: 12px; margin-top: 2px;">เมื่อกดแลกของรางวัล รายการจะแสดงที่นี่</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($myRedemptions as $mr): ?>
                        <?php
                            $statusBadge = '<span style="background:rgba(245,158,11,0.15); color:#D97706; padding:2px 7px; border-radius:6px; font-size:10.5px; font-weight:800;">⏳ รอรับของที่ รพ.สต.</span>';
                            if ($mr['status'] === 'fulfilled') {
                                $statusBadge = '<span style="background:rgba(16,185,129,0.15); color:#059669; padding:2px 7px; border-radius:6px; font-size:10.5px; font-weight:800;">✅ รับของแล้ว</span>';
                            } elseif ($mr['status'] === 'cancelled') {
                                $statusBadge = '<span style="background:rgba(239,68,68,0.15); color:#DC2626; padding:2px 7px; border-radius:6px; font-size:10.5px; font-weight:800;">❌ ยกเลิก</span>';
                            }
                        ?>
                        <div style="background: var(--bg-main); border-radius: 12px; padding: 12px; box-shadow: var(--neumorph-inset); border: 1px solid var(--border-color);">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                                <strong style="font-size: 13.5px; color: var(--text-primary);">
                                    <?= htmlspecialchars($mr['icon_emoji']) ?> <?= htmlspecialchars($mr['item_title']) ?>
                                </strong>
                                <?= $statusBadge ?>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: var(--text-secondary);">
                                <span>รหัสรับของ: <strong style="font-family: monospace; color: var(--color-primary);"><?= htmlspecialchars($mr['redemption_code']) ?></strong></span>
                                <span>ใช้ <?= number_format($mr['points_spent']) ?> แต้ม</span>
                            </div>
                            <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 4px;">
                                🕒 <?= htmlspecialchars(substr($mr['created_at'], 0, 16)) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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
