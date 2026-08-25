<?php
// vhv/leaderboard.php - กระดานคะแนนเกียรติยศ & ศูนย์แลกของรางวัล อสม.
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/demo_banner.php';

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/icons.php';

$currentVhvId = $_SESSION['vhv_id'];
$vhvName = $_SESSION['vhv_name'] ?? 'อสม.';
$isSandboxVal = function_exists('isSandboxMode') && isSandboxMode() ? 1 : 0;
$currentBudgetYear = function_exists('get_current_budget_year') ? get_current_budget_year() : 2026;

// Positive title mapping for the top 50 ranks (Unique top 5, tiered classes for 6-50)
function getPositiveTitle($rank)
{
    if ($rank <= 0 || $rank > 50)
        return '';

    // Top 5 are unique supreme titles
    if ($rank === 1)
        return '👑 สุดยอดขุนพลสาธารณสุข' . DISTRICT_NAME;
    if ($rank === 2)
        return '⭐ ยอดอัศวินสุขภาพชุมชน';
    if ($rank === 3)
        return '🏆 ดาวรุ่งแห่งความห่วงใย';
    if ($rank === 4)
        return '🥇 ผู้พิทักษ์หัวใจไร้โรค';
    if ($rank === 5)
        return '🌟 ขวัญใจสุขภาพดีถ้วนหน้า';

    // Base titles for group tiers (ranks 6-50 in groups of 5)
    $baseTitles = [
        1 => '💎 ยอดนักปราบเบาหวานและความดัน',
        2 => '🌿 ผู้ปกป้องสุขภาวะ' . DISTRICT_NAME,
        3 => '🎖️ เสาหลักสุขภาพดีชุมชน',
        4 => '🏅 ผู้หว่านเมล็ดพันธุ์สุขภาพ',
        5 => '📜 พลังขับเคลื่อนตำบลสุขภาพดี',
        6 => '🌟 ผู้จุดประกายรักตนเอง',
        7 => '🏷️ ทูตสุขภาพสร้างพลังบวก',
        8 => '🛡️ ปราชญ์สุขภาพคู่บ้านคู่เมือง',
        9 => '✨ แสงสว่างนำทางชีวิตชีวา'
    ];

    // Thai traditional civil service / military tiers
    $suffixes = [
        0 => 'ชั้นเอก',
        1 => 'ชั้นโท',
        2 => 'ชั้นตรี',
        3 => 'ชั้นจัตวา',
        4 => 'ชั้นเบญจ'
    ];

    $groupIndex = floor(($rank - 6) / 5) + 1;
    $suffixIndex = ($rank - 6) % 5;

    if (isset($baseTitles[$groupIndex]) && isset($suffixes[$suffixIndex])) {
        return $baseTitles[$groupIndex] . ' ' . $suffixes[$suffixIndex];
    }

    return '';
}

// Visual identity for each rank title
function getRankTitleTheme($rank)
{
    if ($rank === 1) return 'champion';
    if ($rank === 2) return 'knight';
    if ($rank === 3) return 'rising-star';
    if ($rank === 4) return 'heart-guard';
    if ($rank === 5) return 'sunshine';

    $themes = ['ncd-guardian', 'health-shield', 'community-pillar', 'seedling', 'teamwork', 'spark', 'clover', 'wisdom', 'sunrise'];
    $index = floor(($rank - 6) / 5);
    return $themes[$index] ?? 'sunrise';
}

function renderRankTitleHeader($rank, $compact = false)
{
    $title = getPositiveTitle($rank);
    if (!$title) return '';

    $sizeClass = $compact ? ' rank-title-header--compact' : '';
    return '<div class="rank-title-header rank-title-header--' . getRankTitleTheme($rank) . $sizeClass . '" aria-label="ฉายา ' . htmlspecialchars($title) . '">
        <img class="rank-title-header__icon" src="rank_icon.php?rank=' . (int)$rank . '" alt="" aria-hidden="true">
        <span class="rank-title-header__title">' . htmlspecialchars($title) . '</span>
    </div>';
}

// 👑 PRESTIGE LEADERBOARD RANK EMBLEM CONFIG (TOP 1 to 50+)
function getRankEmblemConfig($rank)
{
    $rank = (int)$rank;
    if ($rank === 1) {
        return [
            'icon' => 'rank-crown',
            'discClass' => 'disc-gold-radiant',
            'badgeTitle' => '👑 แชมเปี้ยนสูงสุด',
            'tierName' => 'Grand Champion'
        ];
    } elseif ($rank === 2) {
        return [
            'icon' => 'rank-star-trophy',
            'discClass' => 'disc-ruby-gold',
            'badgeTitle' => '⭐ รองแชมป์อันดับ 1',
            'tierName' => 'First Runner-Up'
        ];
    } elseif ($rank === 3) {
        return [
            'icon' => 'rank-cup-trophy',
            'discClass' => 'disc-sapphire-gold',
            'badgeTitle' => '🏆 รองแชมป์อันดับ 2',
            'tierName' => 'Second Runner-Up'
        ];
    } elseif ($rank >= 4 && $rank <= 5) {
        return [
            'icon' => 'rank-rosette-medal',
            'discClass' => 'disc-emerald-gold',
            'badgeTitle' => '🥇 สุดยอด 5 อันดับแรก',
            'tierName' => 'Top 5 Grand Masters'
        ];
    } elseif ($rank >= 6 && $rank <= 10) {
        return [
            'icon' => 'rank-diamond',
            'discClass' => 'disc-diamond',
            'badgeTitle' => '💎 ลีกยอดฝีมือ Top 10',
            'tierName' => 'Diamond League'
        ];
    } elseif ($rank >= 11 && $rank <= 15) {
        return [
            'icon' => 'rank-laurel',
            'discClass' => 'disc-navy-gold',
            'badgeTitle' => '🌿 ช่อลอเรลเกียรติยศ',
            'tierName' => 'Laurel League'
        ];
    } elseif ($rank >= 16 && $rank <= 20) {
        return [
            'icon' => 'rank-honor-cross',
            'discClass' => 'disc-teal',
            'badgeTitle' => '🎖️ กางเขนเกียรติคุณ',
            'tierName' => 'Honor Cross'
        ];
    } elseif ($rank >= 21 && $rank <= 25) {
        return [
            'icon' => 'rank-neck-medal',
            'discClass' => 'disc-amber',
            'badgeTitle' => '🏅 เหรียญทองเกียรติยศ',
            'tierName' => 'Olympic Ribbon'
        ];
    } elseif ($rank >= 26 && $rank <= 30) {
        return [
            'icon' => 'rank-certificate',
            'discClass' => 'disc-purple-gold',
            'badgeTitle' => '📜 ม้วนเกียรติบัตรเชิดชู',
            'tierName' => 'Honor Scroll'
        ];
    } elseif ($rank >= 31 && $rank <= 35) {
        return [
            'icon' => 'rank-star-letter',
            'discClass' => 'disc-golden',
            'badgeTitle' => '🌟 ดวงดาวสร้างพลังบวก',
            'tierName' => 'Star Letter'
        ];
    } elseif ($rank >= 36 && $rank <= 40) {
        return [
            'icon' => 'rank-merit-cert',
            'discClass' => 'disc-cyan',
            'badgeTitle' => '🏷️ ประกาศนียบัตรยอดเยี่ยม',
            'tierName' => 'Merit Diploma'
        ];
    } elseif ($rank >= 41 && $rank <= 45) {
        return [
            'icon' => 'rank-shield-gold',
            'discClass' => 'disc-shield',
            'badgeTitle' => '🛡️ โล่ทองพิทักษ์สุขภาวะ',
            'tierName' => 'Health Shield'
        ];
    } elseif ($rank >= 46 && $rank <= 50) {
        return [
            'icon' => 'rank-star-coin',
            'discClass' => 'disc-silver',
            'badgeTitle' => '✨ เหรียญดวงดาวเกียรติยศ',
            'tierName' => 'Star Coin'
        ];
    } else {
        return [
            'icon' => 'rank-pin',
            'discClass' => 'disc-soft',
            'badgeTitle' => '🎗️ อสม. ผู้ร่วมขับเคลื่อน',
            'tierName' => 'Active VHV'
        ];
    }
}

function renderVhvRankEmblem($rank, $size = 'md', $extraStyle = '')
{
    if (function_exists('render_50_rank_emblem')) {
        return render_50_rank_emblem($rank, $size, $extraStyle);
    }
    $cfg = getRankEmblemConfig($rank);
    return render_neu_icon($cfg['icon'], $size, $cfg['discClass'], $extraStyle);
}

require_once __DIR__ . '/../config/demo_data.php';

if (DemoDataProvider::isDemoMode()) {
    $allLeaders = DemoDataProvider::getDemoLeaderboard();
    $totalVhvs = count($allLeaders);
} else {
    // Query Top 50 VHVs with points breakdown and subqueries for badges calculation
    $leaderboardStmt = $pdo->prepare("
        SELECT 
            u.vhv_id, 
            u.vhv_name, 
            u.vhv_moo, 
            u.is_hl_coach,
            v.village_name,
            (
                SELECT COALESCE(SUM(CASE WHEN (r.followup_id IS NULL AND r.assignment_id IS NULL) OR (r.followup_id IS NULL AND ta.assignment_id IS NOT NULL) OR (r.followup_id IS NOT NULL AND f.followup_id IS NOT NULL) THEN r.points_earned ELSE 0 END), 0)
                FROM vhv_rewards r
                LEFT JOIN task_assignments ta ON r.assignment_id = ta.assignment_id
                LEFT JOIN dpac_followups f ON r.followup_id = f.followup_id
                WHERE r.vhv_id = u.vhv_id AND r.approval_status IN ('approved', 'waiting') AND r.is_sandbox = :is_sandbox1
            ) as total_points,
            (
                SELECT COUNT(*) 
                FROM task_assignments ta 
                JOIN target_population p ON ta.target_cid = p.cid 
                WHERE ta.vhv_id = u.vhv_id AND ta.budget_year = :budget_year1 AND ta.is_sandbox = :is_sandbox2
                  AND (
                      (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
                      OR 
                      (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
                      OR
                      p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
                      OR
                      COALESCE(p.is_manual, 0) = 1
                  )
            ) as total_assigned,
            (
                SELECT COUNT(*) 
                FROM task_assignments ta 
                JOIN target_population p ON ta.target_cid = p.cid 
                WHERE ta.vhv_id = u.vhv_id AND ta.budget_year = :budget_year2 AND ta.assignment_status = 'completed' AND ta.is_sandbox = :is_sandbox3
                  AND (
                      (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
                      OR 
                      (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
                      OR
                      p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
                      OR
                      COALESCE(p.is_manual, 0) = 1
                  )
            ) as completed,
            (SELECT COUNT(*) FROM vhv_rewards WHERE vhv_id = u.vhv_id AND approval_status = 'waiting' AND is_sandbox = :is_sandbox4) as waiting_rewards
        FROM vhv_users u
        LEFT JOIN villages v ON u.vhid_code = v.vhid_code
        WHERE u.approved = 1
        ORDER BY total_points DESC, u.vhv_name ASC
    ");
    $leaderboardStmt->execute([
        'is_sandbox1' => $isSandboxVal,
        'is_sandbox2' => $isSandboxVal,
        'is_sandbox3' => $isSandboxVal,
        'is_sandbox4' => $isSandboxVal,
        'budget_year1' => $currentBudgetYear,
        'budget_year2' => $currentBudgetYear
    ]);
    $allLeaders = $leaderboardStmt->fetchAll();
    $totalVhvs = count($allLeaders);
}

// Find current VHV rank and score
$currentVhvRank = 0;
$currentVhvPoints = 0;

foreach ($allLeaders as $index => $leader) {
    if ($leader['vhv_id'] === $currentVhvId) {
        $currentVhvRank = $index + 1;
        $currentVhvPoints = $leader['total_points'] ?? 0;
        break;
    }
}

// Slice Top 50
$topFifty = array_slice($allLeaders, 0, 50);

// VHV Badges helper
function getBadgesList($total_assigned, $completed, $waiting_rewards)
{
    $badges = [];
    $total_assigned = (int)$total_assigned;
    $completed = (int)$completed;
    $waiting_rewards = (int)$waiting_rewards;

    if ($completed > 0) {
        $badges[] = [
            'icon' => '🚀',
            'title' => 'ประเดิมผลงาน',
            'desc' => 'คัดกรองสำเร็จอย่างน้อย 1 รายการ'
        ];
    }

    if ($total_assigned > 0) {
        $rate = ($completed / $total_assigned) * 100;
        if ($rate >= 100) {
            $badges[] = [
                'icon' => '🥇',
                'title' => 'นักคัดกรองทองคำ',
                'desc' => 'คัดกรองสำเร็จครบ 100%'
            ];
        } elseif ($rate >= 75) {
            $badges[] = [
                'icon' => '🥈',
                'title' => 'นักคัดกรองเงิน',
                'desc' => 'คัดกรองสำเร็จ 75% ขึ้นไป'
            ];
        } elseif ($rate >= 50) {
            $badges[] = [
                'icon' => '🥉',
                'title' => 'นักคัดกรองทองแดง',
                'desc' => 'คัดกรองสำเร็จ 50% ขึ้นไป'
            ];
        }
    }

    if ($completed > 0 && $waiting_rewards === 0) {
        $badges[] = [
            'icon' => '📍',
            'title' => 'ผู้พิทักษ์พิกัดจริง',
            'desc' => 'คัดกรองพิกัดถูกต้องทุกเคส'
        ];
    }

    return $badges;
}

// 1. Query village (Moo) completion stats under current VHV's hospital (hoscode)
$hoscode = $_SESSION['hoscode'] ?? '';
$villageStats = [];
if (!empty($hoscode)) {
    $villQuery = "
        SELECT 
            p.moo,
            MAX(v.village_name) as village_name,
            COUNT(DISTINCT p.cid) as total_targets,
            COUNT(DISTINCT CASE WHEN a.assignment_status = 'completed' THEN p.cid END) as completed_targets
        FROM target_population p
        LEFT JOIN villages v ON p.moo = v.moo AND p.hoscode = v.hoscode
        LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.budget_year = ? AND a.is_sandbox = ?
        WHERE p.hoscode = ? 
          AND p.moo > 0 
          AND p.moo IS NOT NULL 
          AND (
              (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
              OR 
              (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
              OR
              p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
              OR
              COALESCE(p.is_manual, 0) = 1
          )
        GROUP BY p.moo
        HAVING total_targets > 0
        ORDER BY p.moo ASC
    ";
    $villStmt = $pdo->prepare($villQuery);
    $villStmt->execute([$currentBudgetYear, $isSandboxVal, $hoscode]);
    $villageStats = $villStmt->fetchAll();
}

// 2. Query hospital progress comparison (Tansum Health Center League)
$hospitalStats = [];
try {
    $hosQuery = "
        SELECT 
            u.hoscode,
            COUNT(DISTINCT p.cid) as total_targets,
            COUNT(DISTINCT CASE WHEN a.assignment_status = 'completed' THEN p.cid END) as completed_targets
        FROM health_units u
        LEFT JOIN target_population p ON u.hoscode = p.hoscode AND (
            (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
            OR 
            (TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) >= 35)
            OR
            p.health_status_origin IN ('RISK', 'HIGH_RISK', 'SUSPECT', 'HT', 'DM', 'BOTH')
            OR
            COALESCE(p.is_manual, 0) = 1
        )
        LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.budget_year = ? AND a.is_sandbox = ?
        GROUP BY u.hoscode
        HAVING COUNT(DISTINCT p.cid) > 0
        ORDER BY (COUNT(DISTINCT CASE WHEN a.assignment_status = 'completed' THEN p.cid END) / COUNT(DISTINCT p.cid)) DESC, u.hoscode ASC
    ";
    $hosStmt = $pdo->prepare($hosQuery);
    $hosStmt->execute([$currentBudgetYear, $isSandboxVal]);
    $hospitalStats = $hosStmt->fetchAll();
} catch (\Exception $e) {
    // Fail silently
}
$hcNames = get_health_units();

// ==========================================
// REWARD STORE DATA PREPARATION
// ==========================================
$systemEnabled = (int)get_system_setting('reward_system_enabled', 0);

// Calculate user's points for redemption
$totalEarned = 0.0;
$pointsSpent = 0.0;
try {
    $stmtPts = $pdo->prepare("
        SELECT COALESCE(SUM(points_earned), 0) 
        FROM `vhv_rewards` 
        WHERE `vhv_id` = ? AND `approval_status` IN ('approved', 'waiting') AND `is_sandbox` = ?
    ");
    $stmtPts->execute([$currentVhvId, $isSandboxVal]);
    $totalEarned = (float)$stmtPts->fetchColumn();

    $stmtSpent = $pdo->prepare("
        SELECT COALESCE(SUM(points_spent), 0) 
        FROM `reward_redemptions` 
        WHERE `vhv_id` = ? AND `status` IN ('pending', 'fulfilled')
    ");
    $stmtSpent->execute([$currentVhvId]);
    $pointsSpent = (float)$stmtSpent->fetchColumn();
} catch (\Exception $e) {}

$availablePoints = max(0.0, $totalEarned - $pointsSpent);

// Fetch categories from DB
$rewardCategories = [];
$categoryMap = [];
try {
    $catStmt = $pdo->query("SELECT * FROM `reward_categories` WHERE `is_active` = 1 ORDER BY `sort_order` ASC, `category_name` ASC");
    $rewardCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rewardCategories as $rc) {
        $categoryMap[$rc['category_code']] = $rc['category_name'];
    }
} catch (\Exception $e) {
    $rewardCategories = [
        ['category_code' => 'equipment', 'category_name' => 'อุปกรณ์ลงพื้นที่', 'icon_emoji' => '🧴'],
        ['category_code' => 'souvenir', 'category_name' => 'ของที่ระลึก', 'icon_emoji' => '☂️'],
        ['category_code' => 'medical', 'category_name' => 'เครื่องมือแพทย์', 'icon_emoji' => '🩺'],
        ['category_code' => 'honorary', 'category_name' => 'เชิดชูเกียรติ', 'icon_emoji' => '🏆']
    ];
    $categoryMap = [
        'equipment' => 'อุปกรณ์ลงพื้นที่',
        'souvenir' => 'ของที่ระลึก',
        'medical' => 'เครื่องมือแพทย์',
        'honorary' => 'เชิดชูเกียรติ'
    ];
}

// Fetch active items
$rewardItems = [];
try {
    $rewardItems = $pdo->query("SELECT * FROM `reward_items` WHERE `is_active` = 1 ORDER BY `sort_order` ASC, `points_required` ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $rewardItems = [];
}

// Fetch user redemptions
$myRedemptions = [];
try {
    $stmtMy = $pdo->prepare("
        SELECT r.*, i.title as item_title, i.category, i.icon_emoji, i.image_url 
        FROM `reward_redemptions` r
        JOIN `reward_items` i ON r.item_id = i.item_id
        WHERE r.vhv_id = ?
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $stmtMy->execute([$currentVhvId]);
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
    <title>กระดานคะแนน & แลกรางวัล - NCDs <?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="apple-touch-icon" href="../assets/icon.png">
    <link rel="manifest" href="manifest.json">
    <style>
        /* Rank-title headers: readable text plus a visual identity for every title family. */
        .rank-title-header {
            --rank-ink: #0d2c54;
            --rank-glow: rgba(13, 44, 84, 0.14);
            --rank-banner: linear-gradient(130deg, #f2f7fc 0%, #ffffff 55%, #eaf5f8 100%);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
            margin-top: 10px;
            padding: 8px 12px;
            min-height: 40px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 14px;
            color: var(--rank-ink);
            background: transparent;
            box-shadow: 0 4px 14px var(--rank-glow);
        }
        .rank-title-header::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 0;
            opacity: .75;
            background: var(--rank-banner);
        }
        .rank-title-header::after {
            content: '';
            position: absolute;
            right: -12px;
            top: -36px;
            width: 112px;
            height: 112px;
            border: 16px solid rgba(255, 255, 255, 0.55);
            border-radius: 50%;
        }
        .rank-title-header__icon {
            position: relative;
            z-index: 1;
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            filter: drop-shadow(0 3px 6px rgba(13, 44, 84, .16));
        }
        .rank-title-header__title {
            position: relative;
            z-index: 1;
            font-size: 13.5px;
            font-weight: 800;
            line-height: 1.25;
        }
        .rank-title-header--compact {
            margin-top: 5px;
            padding: 4px 8px;
            min-height: 26px;
            border-radius: 8px;
            gap: 6px;
            display: inline-flex;
            max-width: 100%;
        }
        .rank-title-header--compact .rank-title-header__icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        .rank-title-header--compact .rank-title-header__title {
            font-size: 11.5px;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .rank-title-header--compact::after {
            width: 50px;
            height: 50px;
            top: -20px;
            right: -10px;
            border-width: 7px;
        }

        .rank-title-header--champion { --rank-ink: #8a5700; --rank-glow: rgba(226, 165, 30, .25); --rank-banner: linear-gradient(130deg, #fff8d9, #fffdf4 55%, #ffe39b); }
        .rank-title-header--knight { --rank-ink: #50657d; --rank-glow: rgba(104, 126, 150, .22); --rank-banner: linear-gradient(130deg, #edf2f7, #ffffff 55%, #cfdbe6); }
        .rank-title-header--rising-star { --rank-ink: #a95827; --rank-glow: rgba(191, 103, 51, .22); --rank-banner: linear-gradient(130deg, #fff0df, #fffaf5 55%, #f2bb8d); }
        .rank-title-header--heart-guard { --rank-ink: #bc365a; --rank-glow: rgba(205, 66, 102, .20); --rank-banner: linear-gradient(130deg, #fff0f4, #fffafe 55%, #f5bdcd); }
        .rank-title-header--sunshine { --rank-ink: #b56c00; --rank-glow: rgba(240, 169, 0, .22); --rank-banner: linear-gradient(130deg, #fff9df, #fffffa 55%, #ffe680); }
        .rank-title-header--ncd-guardian { --rank-ink: #c1442f; --rank-glow: rgba(205, 80, 54, .20); --rank-banner: linear-gradient(130deg, #fff0eb, #fffafa 55%, #ffd0bf); }
        .rank-title-header--health-shield { --rank-ink: #136b7c; --rank-glow: rgba(27, 131, 148, .20); --rank-banner: linear-gradient(130deg, #e8fafb, #fbffff 55%, #bde9ed); }
        .rank-title-header--community-pillar { --rank-ink: #6b528f; --rank-glow: rgba(117, 86, 158, .20); --rank-banner: linear-gradient(130deg, #f4effd, #fffaff 55%, #d9caf0); }
        .rank-title-header--seedling { --rank-ink: #3d8550; --rank-glow: rgba(66, 140, 82, .20); --rank-banner: linear-gradient(130deg, #effaed, #fbfffa 55%, #ccebc7); }
        .rank-title-header--teamwork { --rank-ink: #1e7583; --rank-glow: rgba(35, 125, 140, .20); --rank-banner: linear-gradient(130deg, #e8f8fa, #fbffff 55%, #bde7eb); }
        .rank-title-header--spark { --rank-ink: #b64d6d; --rank-glow: rgba(194, 82, 114, .20); --rank-banner: linear-gradient(130deg, #fff0f5, #fffaff 55%, #f4c1d0); }
        .rank-title-header--clover { --rank-ink: #4d8a4c; --rank-glow: rgba(79, 142, 75, .20); --rank-banner: linear-gradient(130deg, #effbea, #fcfffa 55%, #cfebbc); }
        .rank-title-header--wisdom { --rank-ink: #6957a0; --rank-glow: rgba(106, 85, 161, .20); --rank-banner: linear-gradient(130deg, #f3efff, #fffaff 55%, #d9cef8); }
        .rank-title-header--sunrise { --rank-ink: #c06c27; --rank-glow: rgba(204, 113, 42, .20); --rank-banner: linear-gradient(130deg, #fff3df, #fffcf6 55%, #ffd49e); }

        .badge-icon {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
            border-radius: 50%;
            font-size: 14px;
            font-weight: bold;
            margin-left: 6px;
        }

        /* Trophy & Award Icon Hover Effect */
        .trophy-icon {
            display: inline-block;
            cursor: default;
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), filter 0.35s ease;
            transform-origin: center bottom;
        }

        .trophy-icon:hover {
            transform: scale(1.45) rotate(-12deg);
            filter: drop-shadow(0 6px 18px rgba(251, 191, 36, 0.7)) brightness(1.1);
        }

        /* Silver trophy hover */
        .trophy-icon.silver:hover {
            filter: drop-shadow(0 6px 18px rgba(156, 163, 175, 0.8)) brightness(1.12);
        }

        /* Bronze trophy hover */
        .trophy-icon.bronze:hover {
            filter: drop-shadow(0 6px 18px rgba(180, 100, 30, 0.75)) brightness(1.1);
        }

        /* Medal rank 4-10 hover */
        .trophy-icon.medal:hover {
            transform: scale(1.35) rotate(10deg);
            filter: drop-shadow(0 4px 12px rgba(99, 102, 241, 0.5)) brightness(1.08);
        }

        /* Tab Styles */
        .tab-btn {
            flex: 1;
            padding: 10px 4px;
            border: none;
            border-radius: 12px;
            background: transparent;
            cursor: pointer;
            transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tab-icon-img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.12));
            opacity: 0.72;
            transform: scale(0.92);
        }

        .tab-btn:hover .tab-icon-img {
            opacity: 0.95;
            transform: scale(1.05);
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.18));
        }

        .tab-btn.active {
            background: var(--bg-card) !important;
            box-shadow: var(--neumorph-flat) !important;
        }

        .tab-btn.active .tab-icon-img {
            opacity: 1;
            transform: scale(1.15);
            filter: drop-shadow(0 4px 10px rgba(13, 44, 84, 0.25));
        }

        .tab-content {
            animation: fadeIn 0.35s ease-in-out;
        }

        .main-tab-pane {
            animation: fadeIn 0.35s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Dynamic Category Filter Wrap Chips (No Overflow, Auto Wrap) */
        .category-filter-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 18px;
            align-items: center;
        }

        .category-chip {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 6px 13px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            line-height: 1.2;
            user-select: none;
        }

        .category-chip:active {
            transform: scale(0.96);
        }

        .category-chip.active {
            background: var(--color-primary);
            color: #ffffff;
            border-color: var(--color-primary);
            box-shadow: 0 4px 12px rgba(13, 44, 84, 0.25);
        }

        /* Rewards List & Card Redesign - High Impact Hero Visual & Clean Compact Layout */
        .rewards-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        @media (min-width: 640px) {
            .rewards-list {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
        }

        .reward-card {
            background: var(--bg-card);
            border-radius: 20px;
            box-shadow: var(--neumorph-flat);
            border: 1px solid var(--border-color);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
        }

        .reward-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(13, 44, 84, 0.12);
        }

        .reward-card:active {
            transform: scale(0.985);
        }

        /* Large Hero Visual Showcase */
        .reward-hero {
            position: relative;
            width: 100%;
            height: 140px;
            background: linear-gradient(145deg, rgba(13, 44, 84, 0.03) 0%, rgba(13, 44, 84, 0.08) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid rgba(13, 44, 84, 0.06);
            overflow: hidden;
        }

        [data-theme="dark"] .reward-hero {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.07) 100%);
            border-bottom-color: rgba(255, 255, 255, 0.06);
        }

        .reward-hero-img {
            max-height: 110px;
            max-width: 82%;
            object-fit: contain;
            filter: drop-shadow(0 6px 14px rgba(0, 0, 0, 0.14));
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .reward-card:hover .reward-hero-img {
            transform: scale(1.08);
        }

        .reward-hero-emoji {
            font-size: 58px;
            filter: drop-shadow(0 6px 14px rgba(0, 0, 0, 0.15));
            line-height: 1;
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .reward-card:hover .reward-hero-emoji {
            transform: scale(1.12);
        }

        /* Floating Overlays */
        .hero-badge-category {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: var(--text-primary);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 800;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            z-index: 2;
        }

        [data-theme="dark"] .hero-badge-category {
            background: rgba(30, 41, 59, 0.85);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .hero-badge-points {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: #ffffff;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 3px 10px rgba(217, 119, 6, 0.35);
            z-index: 2;
        }

        .hero-badge-preview {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: #ffffff;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            z-index: 2;
        }

        /* Card Content Body */
        .reward-body {
            padding: 14px 16px 16px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-grow: 1;
        }

        .reward-title {
            font-size: 15.5px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 4px 0;
            line-height: 1.35;
        }

        .reward-desc {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.45;
            margin: 0 0 12px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Progress Bar */
        .progress-box {
            margin: 0 0 12px 0;
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
            height: 5px;
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
            height: 42px;
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
            box-shadow: 0 6px 16px rgba(0, 168, 120, 0.4);
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
        
        <!-- Header -->
        <div class="vhv-header" style="display: flex; align-items: center; gap: 14px; padding: 12px 16px; margin-bottom: 16px; border-radius: var(--border-radius); background: var(--bg-card); box-shadow: var(--neumorph-flat); text-align: left;">
            <img src="../assets/icons/header_leaderboard_star.png" alt="คะแนน & รางวัล อสม." style="width: 56px; height: 56px; object-fit: contain; flex-shrink: 0; filter: drop-shadow(0 3px 8px rgba(226, 165, 30, 0.35));">
            <div style="flex: 1; min-width: 0;">
                <h3 style="color: var(--color-accent); margin: 0; font-size: 16.5px; font-weight: 800; line-height: 1.25;">
                    คะแนน & รางวัล อสม.
                </h3>
                <p style="color: var(--text-secondary); margin: 3px 0 0 0; font-size: 12.5px; line-height: 1.35;">
                    จัดอันดับผลงาน & ศูนย์แลกของรางวัลในพื้นที่<?= DISTRICT_NAME ?>
                </p>
            </div>
        </div>

        <!-- Main Mode Switcher: Leaderboard vs Rewards -->
        <div class="main-tab-switcher" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 18px; background: rgba(13,44,84,0.06); padding: 5px; border-radius: 16px; box-shadow: var(--neumorph-inset);">
            <button type="button" id="main-tab-btn-leaderboard" onclick="switchMainTab('leaderboard')" class="main-tab-pill active" style="display: flex; align-items: center; justify-content: center; gap: 6px; padding: 12px 10px; border: none; border-radius: 12px; font-size: 13.5px; font-weight: 800; cursor: pointer; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); background: var(--bg-card); color: var(--color-accent); box-shadow: var(--neumorph-flat);">
                <span style="font-size: 17px;">🏆</span> <span>อันดับผลงาน</span>
            </button>
            <button type="button" id="main-tab-btn-rewards" onclick="switchMainTab('rewards')" class="main-tab-pill" style="display: flex; align-items: center; justify-content: center; gap: 6px; padding: 12px 10px; border: none; border-radius: 12px; font-size: 13.5px; font-weight: 800; cursor: pointer; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); background: transparent; color: var(--text-secondary); box-shadow: none;">
                <span style="font-size: 17px;">🎁</span> <span>แลกของรางวัล</span>
                <?php if (count($rewardItems) > 0): ?>
                    <span style="background: var(--color-accent); color: #ffffff; font-size: 11px; padding: 1px 7px; border-radius: 50px; font-weight: 800;"><?= count($rewardItems) ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- ======================================================= -->
        <!-- TAB 1: LEADERBOARD PANE                                 -->
        <!-- ======================================================= -->
        <div id="main-pane-leaderboard" class="main-tab-pane">
            <!-- Current VHV Score Widget (Hero Split Prestige Emblem Card) -->
            <div class="card-dark" style="padding: 18px 20px; box-shadow: var(--neumorph-flat); border-radius: var(--border-radius); position: relative; overflow: hidden;">
                <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                    
                    <!-- Left: Full-Height Prestige Emblem Icon -->
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; min-width: 80px;">
                        <div style="position: relative; display: inline-flex;">
                            <?= renderVhvRankEmblem($currentVhvRank, 'xl', 'width: 72px; height: 72px;') ?>
                            <span style="position: absolute; bottom: -6px; left: 50%; transform: translateX(-50%); background: var(--bg-card); color: var(--color-accent); font-size: 11px; font-weight: 900; padding: 2px 8px; border-radius: 999px; box-shadow: var(--neumorph-flat); border: 1px solid var(--border-color); white-space: nowrap;">
                                #<?= $currentVhvRank ?: 'N/A' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Right: Performance Stats -->
                    <div style="flex: 1; min-width: 180px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; align-items: center; text-align: center; background: rgba(13, 44, 84, 0.04); padding: 10px 12px; border-radius: 14px; box-shadow: var(--neumorph-inset);">
                            <div style="border-right: 1px solid rgba(13, 44, 84, 0.1); padding-right: 6px;">
                                <span style="color: var(--text-secondary); font-size: 11.5px; font-weight: 700; display: block; margin-bottom: 2px;">อันดับของคุณ</span>
                                <div style="font-size: 26px; font-weight: 900; color: var(--color-accent); line-height: 1.1;">
                                    #<?= $currentVhvRank ?: 'N/A' ?>
                                </div>
                            </div>
                            <div>
                                <span style="color: var(--text-secondary); font-size: 11.5px; font-weight: 700; display: block; margin-bottom: 2px;">คะแนนผลงานสะสม</span>
                                <div style="font-size: 26px; font-weight: 900; color: var(--text-primary); line-height: 1.1;">
                                    <?= (float)$currentVhvPoints ?> <span style="font-size: 13px; color: var(--text-secondary); font-weight: normal;">แต้ม</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Footer Summary Text -->
                <div style="margin-top: 14px; font-size: 13px; text-align: center; color: var(--text-primary); border-top: 1px solid rgba(13, 44, 84, 0.08); padding-top: 10px; font-weight: 700; line-height: 1.4;">
                    📊 คุณอยู่อันดับที่ <?= $currentVhvRank ?: 'N/A' ?> จาก อสม. ทั้งหมด <?= $totalVhvs ?> คน ของอำเภอ<?= DISTRICT_NAME ?>
                </div>
                <?php
                $myTitle = getPositiveTitle($currentVhvRank);
                if ($myTitle):
                ?>
                    <div style="margin-top: 8px;">
                        <?= renderRankTitleHeader($currentVhvRank) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab Bar for Mobile Responsiveness (3D Icons) -->
            <div class="tab-container" style="display: flex; gap: 8px; margin-top: 20px; margin-bottom: 20px; background: rgba(13,44,84,0.05); padding: 6px; border-radius: 16px; box-shadow: var(--neumorph-inset);">
                <button onclick="switchTab('leaderboard')" id="btn-leaderboard" class="tab-btn active" title="อันดับ อสม.">
                    <img src="../assets/icons/tab_vhv_leaderboard.png" alt="อันดับ อสม." class="tab-icon-img">
                </button>
                <button onclick="switchTab('villages')" id="btn-villages" class="tab-btn" title="ผลงานรายหมู่บ้าน">
                    <img src="../assets/icons/tab_village_progress.png" alt="ผลงานรายหมู่บ้าน" class="tab-icon-img">
                </button>
                <button onclick="switchTab('hospitals')" id="btn-hospitals" class="tab-btn" title="ลีก รพ.สต.">
                    <img src="../assets/icons/tab_hospital_league.png" alt="ลีก รพ.สต." class="tab-icon-img">
                </button>
                <button onclick="switchTab('badges')" id="btn-badges" class="tab-btn" title="ตราเกียรติยศ">
                    <img src="../assets/icons/tab_badge_criteria.png" alt="ตราเกียรติยศ" class="tab-icon-img">
                </button>
            </div>

            <!-- Sub-Tab 2: Village Progress Board -->
            <div id="content-villages" class="tab-content" style="display: none;">
                <?php if (!empty($villageStats)): ?>
                    <div class="card-dark" style="padding: 20px; box-shadow: var(--neumorph-flat); margin-bottom: 20px;">
                        <h4 style="color: var(--color-accent); font-size: 16px; margin: 0 0 12px 0; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                            สมรภูมิคัดกรองรายหมู่บ้าน
                        </h4>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: -8px 0 16px 0;">เปรียบเทียบอัตราความสำเร็จในการคัดกรองเป้าหมายในตำบลของคุณ</p>
                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            <?php foreach ($villageStats as $vStat):
                                $total = (int)$vStat['total_targets'];
                                $done = (int)$vStat['completed_targets'];
                                $pct = $total > 0 ? round(($done / $total) * 100, 1) : 0;

                                // Select indicator color based on progress
                                $barColor = 'var(--color-yellow)';
                                if ($pct >= 100) $barColor = 'var(--color-green)';
                                elseif ($pct >= 50) $barColor = 'var(--color-accent)';
                                elseif ($pct < 20) $barColor = 'var(--color-red)';
                            ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: bold; margin-bottom: 6px; color: var(--text-primary);">
                                        <span>หมู่ที่ <?= htmlspecialchars($vStat['moo']) ?> <?= !empty($vStat['village_name']) ? htmlspecialchars($vStat['village_name']) : '' ?></span>
                                        <span style="color: <?= $barColor ?>;"><?= $done ?> / <?= $total ?> คน (<?= $pct ?>%)</span>
                                    </div>
                                    <div style="width: 100%; height: 12px; background: rgba(13, 44, 84, 0.08); border-radius: 6px; overflow: hidden; box-shadow: var(--neumorph-inset);">
                                        <div style="width: <?= $pct ?>%; height: 100%; background: <?= $barColor ?>; border-radius: 6px; transition: width 0.8s ease-in-out;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card-dark" style="padding: 30px; text-align: center; color: var(--text-muted); margin-bottom: 20px;">
                        ไม่พบข้อมูลประชากรเป้าหมายของ รพ.สต. คุณ
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sub-Tab 3: Hospital / Zone League Standings -->
            <div id="content-hospitals" class="tab-content" style="display: none;">
                <?php if (!empty($hospitalStats)): ?>
                    <div class="card-dark" style="padding: 20px; box-shadow: var(--neumorph-flat); margin-bottom: 20px;">
                        <h4 style="color: var(--color-accent); font-size: 16px; margin: 0 0 12px 0; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                            ลีกหน่วยบริการ รพ.สต. (ทั้งอำเภอ<?= DISTRICT_NAME ?>)
                        </h4>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: -8px 0 16px 0;">อันดับอัตราการคัดกรองสูงสุดแยกตามเขตรับผิดชอบของแต่ละ รพ.สต.</p>
                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            <?php
                            $hRank = 1;
                            foreach ($hospitalStats as $hStat):
                                $total = (int)$hStat['total_targets'];
                                $done = (int)$hStat['completed_targets'];
                                $pct = $total > 0 ? round(($done / $total) * 100, 1) : 0;
                                $hName = $hcNames[$hStat['hoscode']] ?? $hStat['hoscode'];

                                $isMyHos = ($hStat['hoscode'] === $hoscode);

                                $barColor = 'var(--color-accent)';
                                if ($pct >= 100) $barColor = 'var(--color-green)';

                                $rankIcon = '';
                                if ($hRank === 1) $rankIcon = '🥇';
                                elseif ($hRank === 2) $rankIcon = '🥈';
                                elseif ($hRank === 3) $rankIcon = '🥉';
                                else $rankIcon = '🏅';
                            ?>
                                <div style="<?= $isMyHos ? 'background: rgba(13, 44, 84, 0.04); border: 1px dashed var(--color-accent); padding: 8px; border-radius: 12px;' : '' ?>">
                                    <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: bold; margin-bottom: 6px; color: var(--text-primary);">
                                        <span><?= $rankIcon ?> #<?= $hRank ?> <?= htmlspecialchars($hName) ?> <?= $isMyHos ? '<span style="color:var(--color-accent);font-size:11px;">(รพ.สต. ของคุณ)</span>' : '' ?></span>
                                        <span><?= $pct ?>%</span>
                                    </div>
                                    <div style="width: 100%; height: 8px; background: rgba(13, 44, 84, 0.08); border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?= $pct ?>%; height: 100%; background: <?= $barColor ?>; border-radius: 4px; transition: width 0.8s ease-in-out;"></div>
                                    </div>
                                </div>
                            <?php
                                $hRank++;
                            endforeach;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sub-Tab 4: VHV Badges Explanations Card -->
            <div id="content-badges" class="tab-content" style="display: none;">
                <div class="card-dark" style="padding: 20px; box-shadow: var(--neumorph-flat); margin-bottom: 20px;">
                    <h4 style="color: var(--color-accent); font-size: 16px; margin: 0 0 12px 0; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        ตำนานตราเกียรติยศ (อสม. คัดกรองดีเด่น)
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 12px; font-size: 12px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 20px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: rgba(251, 191, 36, 0.1); border-radius: 50%;">🥇</span>
                            <div>
                                <strong>นักคัดกรองทองคำ:</strong>
                                <span style="color: var(--text-secondary);">คัดกรองเป้าหมายสำเร็จครบ 100%</span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 20px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: rgba(156, 163, 175, 0.1); border-radius: 50%;">🥈</span>
                            <div>
                                <strong>นักคัดกรองเงิน:</strong>
                                <span style="color: var(--text-secondary);">คัดกรองเป้าหมายสำเร็จ 75% ขึ้นไป</span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 20px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: rgba(180, 100, 30, 0.1); border-radius: 50%;">🥉</span>
                            <div>
                                <strong>นักคัดกรองทองแดง:</strong>
                                <span style="color: var(--text-secondary);">คัดกรองเป้าหมายสำเร็จ 50% ขึ้นไป</span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 20px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: rgba(59, 130, 246, 0.1); border-radius: 50%;">📍</span>
                            <div>
                                <strong>ผู้พิทักษ์พิกัดจริง:</strong>
                                <span style="color: var(--text-secondary);">บันทึกข้อมูลหน้าบ้านเป้าหมายในระยะ 100 เมตรสำเร็จครบทุกเคส</span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 20px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: rgba(16, 185, 129, 0.1); border-radius: 50%;">🚀</span>
                            <div>
                                <strong>ประเดิมผลงาน:</strong>
                                <span style="color: var(--text-secondary);">คัดกรองส่งงานเรียบร้อยแล้วอย่างน้อย 1 เคส</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sub-Tab 1: Leaderboard List -->
            <div id="content-leaderboard" class="tab-content">
                <div style="margin-top: 10px;">
                    <h4 style="color: var(--text-primary); font-size: 16px; margin-bottom: 12px; font-weight: 800;">50 อันดับสูงสุด</h4>

                    <?php
                    $rankNum = 1;
                    foreach ($topFifty as $index => $leader):
                        $points = $leader['total_points'] ?? 0;

                        // Assign CSS class based on rank
                        $rankClass = '';
                        $badgeText = '';
                        if ($rankNum === 1) {
                            $rankClass = 'badge-gold';
                            $badgeText = '🥇';
                        } elseif ($rankNum === 2) {
                            $rankClass = 'badge-silver';
                            $badgeText = '🏆';
                        } elseif ($rankNum === 3) {
                            $rankClass = 'badge-bronze';
                            $badgeText = '🏆';
                        } else {
                            $rankClass = 'badge-custom';
                            $badgeText = '🎖️';
                        }

                        // Add special shiny badging based on points milestones
                        $shinyBadge = '';
                        if ($points >= 50) {
                            $shinyBadge = '<span class="badge-icon badge-gold" title="ฮีโร่' . DISTRICT_NAME . '">🔥</span>';
                        } elseif ($points >= 20) {
                            $shinyBadge = '<span class="badge-icon badge-silver" title="ผู้พิทักษ์หัวใจ">💖</span>';
                        }
                    ?>
                        <?php
                        // Display prestige Neumorphic rank emblem based on tier
                        $emblemSize = ($rankNum <= 3) ? 'lg' : (($rankNum <= 10) ? 'md' : 'sm');
                        $trophyHtml = renderVhvRankEmblem($rankNum, $emblemSize);
                        ?>
                        <div class="leaderboard-row"
                            style="<?= $leader['vhv_id'] === $currentVhvId 
                                ? 'background: rgba(13, 110, 253, 0.09) !important; border: 2px solid var(--color-accent); box-shadow: var(--neumorph-inset) !important;' 
                                : 'background: var(--bg-card); box-shadow: var(--neumorph-flat);' ?> display: flex; align-items: center; padding: 16px 14px; border-radius: var(--border-radius); margin-bottom: 14px; position: relative; overflow: hidden;">

                            <?php if (!empty($leader['is_hl_coach'])): ?>
                                <!-- HL-Coach Badge in Top-Right Corner -->
                                <div style="position: absolute; top: 10px; right: 12px; font-size: 10.5px; font-weight: 800; color: #d97706; background: linear-gradient(135deg, rgba(251, 191, 36, 0.22), rgba(245, 158, 11, 0.12)); border: 1px solid rgba(245, 158, 11, 0.35); padding: 2px 8px; border-radius: 6px; z-index: 3; display: inline-flex; align-items: center; gap: 3px; box-shadow: 0 1px 4px rgba(245,158,11,0.12);" title="HL-Coach">
                                    <span>✨</span> <span>HL-Coach</span>
                                </div>
                            <?php endif; ?>

                            <!-- Faded background watermark rank number -->
                            <div style="position: absolute; right: 70px; bottom: -20px; font-size: 76px; font-weight: 900; color: rgba(13, 44, 84, 0.06); pointer-events: none; user-select: none; font-family: 'Outfit', sans-serif;">
                                <?= $rankNum ?>
                            </div>

                            <div style="width: 52px; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0; position: relative; z-index: 2;">
                                <?= $trophyHtml ?>
                            </div>

                            <div class="leader-info" style="position: relative; z-index: 2; flex: 1; min-width: 0;">
                                <strong style="color: var(--text-primary); font-size: 15.5px; font-weight: 800; display: block; line-height: 1.3;">
                                    <?= htmlspecialchars($leader['vhv_name']) ?>
                                </strong>
                                <p style="margin: 2px 0 0 0; font-size: 12.5px; color: var(--text-secondary);">
                                    หมู่ที่ <?= $leader['vhv_moo'] ?><?= !empty($leader['village_name']) ? ' ' . htmlspecialchars($leader['village_name']) : '' ?>
                                </p>
                                <?php
                                $rowTitle = getPositiveTitle($rankNum);
                                if ($rowTitle):
                                ?>
                                    <?= renderRankTitleHeader($rankNum, true) ?>
                                <?php endif; ?>
                            </div>

                            <div class="leader-score" style="flex-shrink: 0; position: relative; z-index: 2; text-align: right; margin-top: <?= !empty($leader['is_hl_coach']) ? '12px' : '0' ?>; margin-left: 8px;">
                                <div style="font-size: 22px; font-weight: 900; color: var(--color-accent); line-height: 1;"><?= (float)$points ?></div>
                                <span style="font-size: 11px; color: var(--text-muted); font-weight: 700;">แต้ม</span>
                                <?= $shinyBadge ?>
                            </div>
                        </div>
                    <?php
                        $rankNum++;
                    endforeach;
                    ?>
                </div>
            </div>
        </div>

        <!-- ======================================================= -->
        <!-- TAB 2: REWARDS STORE PANE                               -->
        <!-- ======================================================= -->
        <div id="main-pane-rewards" class="main-tab-pane" style="display: none;">
            
            <!-- Store Action Bar -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <div>
                    <h4 style="color: var(--color-accent); margin: 0; font-size: 15px; font-weight: 800;">🎁 ศูนย์ของรางวัล อสม.</h4>
                    <p style="color: var(--text-secondary); margin: 2px 0 0 0; font-size: 12px;">สะสมแต้มผลงานแลกรับสิทธิ์</p>
                </div>
                <button type="button" onclick="openMyRedemptionsModal()" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--color-primary); padding: 7px 12px; border-radius: 12px; font-size: 12px; font-weight: 800; cursor: pointer; box-shadow: var(--neumorph-flat); white-space: nowrap; flex-shrink: 0;">
                    📜 ประวัติ (<?= count($myRedemptions) ?>)
                </button>
            </div>

            <!-- Current Points Card -->
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
                        <div style="font-size: <?= $systemEnabled ? '30px' : '15px' ?>; font-weight: 800; color: <?= $systemEnabled ? 'var(--text-primary)' : '#D97706' ?>; line-height: 1.1; margin-top: <?= $systemEnabled ? '0' : '6px' ?>;">
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

            <!-- Dynamic Category Filter Wrap Chips (Auto Wrap & No Horizontal Overflow) -->
            <div class="category-filter-wrap">
                <button type="button" class="category-chip active" onclick="filterCategory('all', this)">
                    <span>✨</span> <span>ทั้งหมด</span>
                </button>
                <?php foreach ($rewardCategories as $rc): ?>
                    <button type="button" class="category-chip" onclick="filterCategory('<?= htmlspecialchars($rc['category_code']) ?>', this)">
                        <span><?= htmlspecialchars($rc['icon_emoji'] ?? '🏷️') ?></span> 
                        <span><?= htmlspecialchars($rc['category_name']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Rewards Cards List -->
            <div class="rewards-list">
                <?php if (empty($rewardItems)): ?>
                    <div class="card-dark" style="padding: 30px; text-align: center; color: var(--text-muted);">
                        <div style="font-size: 36px; margin-bottom: 8px;">🎁</div>
                        <div style="font-weight: 700; font-size: 14px;">ยังไม่มีรายการของรางวัล</div>
                        <div style="font-size: 12px; margin-top: 4px;">เจ้าหน้าที่กำลังจัดเตรียมรายการของรางวัลสำหรับ อสม.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($rewardItems as $it): ?>
                        <?php
                            $reqPts = (float)$it['points_required'];
                            $pct = ($reqPts > 0) ? min(100, round(($availablePoints / $reqPts) * 100)) : 100;
                            $canAfford = $availablePoints >= $reqPts;
                            $isOutOfStock = ($it['stock_quantity'] != -1 && $it['stock_quantity'] <= 0);
                        ?>
                        <div class="reward-card item-card-box" data-category="<?= htmlspecialchars($it['category']) ?>">
                            <!-- 1. Large Eye-Catching Hero Visual Showcase -->
                            <div class="reward-hero">
                                <!-- Floating Category Pill (Top-Left) -->
                                <div class="hero-badge-category">
                                    <?= htmlspecialchars($it['icon_emoji'] ?: '🏷️') ?> <?= htmlspecialchars($categoryMap[$it['category']] ?? $it['category']) ?>
                                </div>

                                <!-- Floating Points / Status Badge (Top-Right) -->
                                <?php if ($systemEnabled): ?>
                                    <div class="hero-badge-points">
                                        ⭐ <?= number_format($reqPts) ?> แต้ม
                                    </div>
                                <?php else: ?>
                                    <div class="hero-badge-preview">
                                        ✨ รอเปิดแลก
                                    </div>
                                <?php endif; ?>

                                <!-- Centered Large Image / 3D Emoji -->
                                <?php if (!empty($it['image_url'])): ?>
                                    <?php
                                        $imgSrc = (strpos($it['image_url'], 'http') === 0 || strpos($it['image_url'], '/') === 0) 
                                            ? $it['image_url'] 
                                            : '../' . $it['image_url'];
                                    ?>
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($it['title']) ?>" class="reward-hero-img">
                                <?php else: ?>
                                    <div class="reward-hero-emoji">
                                        <?= htmlspecialchars($it['icon_emoji'] ?: '🎁') ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- 2. Compact Crisp Content Body -->
                            <div class="reward-body">
                                <div>
                                    <h4 class="reward-title">
                                        <?= htmlspecialchars($it['title']) ?>
                                    </h4>
                                    <p class="reward-desc">
                                        <?= htmlspecialchars($it['description'] ?: 'ของรางวัลพิเศษสำหรับ อสม. คนเก่งประจำพื้นที่') ?>
                                    </p>
                                </div>

                                <div>
                                    <?php if ($systemEnabled): ?>
                                        <!-- Progress Bar (Active Mode) -->
                                        <div class="progress-box">
                                            <div class="progress-label-row">
                                                <span>คะแนนสะสม</span>
                                                <span><?= number_format($availablePoints, 1) ?> / <?= number_format($reqPts) ?> แต้ม</span>
                                            </div>
                                            <div class="progress-track">
                                                <div class="progress-fill" style="width: <?= $pct ?>%; <?= $canAfford ? 'background: #10B981;' : 'background: #F59E0B;' ?>"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Action CTA Button -->
                                    <?php if (!$systemEnabled): ?>
                                        <button type="button" class="btn-redeem btn-redeem-locked" disabled>
                                            🔒 เปิดให้แลกเร็วๆ นี้
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
                                            🎁 แลกของรางวัลนี้ ✨
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

        <!-- ======================================================= -->
        <!-- CLEAN 4-ITEM BOTTOM NAVIGATION                          -->
        <!-- ======================================================= -->
        <div class="bottom-nav">
            <a href="index.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                หน้าแรก
            </a>
            <a href="scan.php" class="nav-link nav-scan-fab fab-scan-pulse">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path
                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                    </path>
                </svg>
                <span>สแกนบ้าน</span>
            </a>
            <a href="leaderboard.php" class="nav-link active">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path
                        d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138z">
                    </path>
                </svg>
                คะแนน & รางวัล
            </a>
            <a href="profile.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
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
                                    <?= htmlspecialchars($mr['icon_emoji'] ?: '🎁') ?> <?= htmlspecialchars($mr['item_title']) ?>
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
        // Switch between Top Main Tabs: Leaderboard vs Rewards
        function switchMainTab(tabId, updateUrl = true) {
            if (updateUrl) {
                const newUrl = new URL(window.location);
                newUrl.searchParams.set('tab', tabId);
                window.history.replaceState({}, '', newUrl);
            }

            document.querySelectorAll('.main-tab-pane').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.main-tab-pill').forEach(btn => {
                btn.classList.remove('active');
                btn.style.background = 'transparent';
                btn.style.color = 'var(--text-secondary)';
                btn.style.boxShadow = 'none';
            });

            const targetPane = document.getElementById('main-pane-' + tabId);
            const targetBtn = document.getElementById('main-tab-btn-' + tabId);
            if (targetPane) targetPane.style.display = 'block';
            if (targetBtn) {
                targetBtn.classList.add('active');
                targetBtn.style.background = 'var(--bg-card)';
                targetBtn.style.color = 'var(--color-accent)';
                targetBtn.style.boxShadow = 'var(--neumorph-flat)';
            }
        }

        // Switch Sub-tabs inside Leaderboard
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

            const targetContent = document.getElementById('content-' + tabId);
            const targetBtn = document.getElementById('btn-' + tabId);
            if (targetContent) targetContent.style.display = 'block';
            if (targetBtn) targetBtn.classList.add('active');
        }

        // Auto-select main tab on load if specified in URL query
        (function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam === 'rewards') {
                switchMainTab('rewards', false);
            } else {
                switchMainTab('leaderboard', false);
            }
        })();

        // Category Filter for Rewards
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

        // Redemption Logic
        let selectedItem = null;
        const currentPoints = <?= (float)$availablePoints ?>;

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
