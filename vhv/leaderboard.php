<?php
// vhv/leaderboard.php
session_start();

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$currentVhvId = $_SESSION['vhv_id'];
$vhvName = $_SESSION['vhv_name'];

// Positive title mapping for the top 50 ranks
function getPositiveTitle($rank) {
    if ($rank <= 0 || $rank > 50) return '';
    if ($rank === 1) return '🏆 สุดยอดขุนพลสาธารณสุขตาลสุม';
    if ($rank === 2) return '🥈 ยอดอัศวินสุขภาพชุมชน';
    if ($rank === 3) return '🥉 ดาวรุ่งแห่งความห่วงใย';
    if ($rank === 4) return '✨ ผู้พิทักษ์หัวใจไร้โรค';
    if ($rank === 5) return '🌟 ขวัญใจสุขภาพดีถ้วนหน้า';
    if ($rank >= 6 && $rank <= 10) return '💪 ยอดนักปราบเบาหวานและความดัน';
    if ($rank >= 11 && $rank <= 15) return '🛡️ ผู้ปกป้องสุขภาวะตาลสุม';
    if ($rank >= 16 && $rank <= 20) return '❤️ เสาหลักสุขภาพดีชุมชน';
    if ($rank >= 21 && $rank <= 25) return '🌱 ผู้หว่านเมล็ดพันธุ์สุขภาพ';
    if ($rank >= 26 && $rank <= 30) return '🤝 พลังขับเคลื่อนตำบลสุขภาพดี';
    if ($rank >= 31 && $rank <= 35) return '🎉 ผู้จุดประกายรักตนเอง';
    if ($rank >= 36 && $rank <= 40) return '🍀 ทูตสุขภาพสร้างพลังบวก';
    if ($rank >= 41 && $rank <= 45) return '💡 ปราชญ์สุขภาพคู่บ้านคู่เมือง';
    if ($rank >= 46 && $rank <= 50) return '☀️ แสนสว่างนำทางชีวิตชีวา';
    return '';
}

// Query Top 50 VHVs across the village based on approved reward points
$leaderboardStmt = $pdo->query("
    SELECT u.vhv_id, u.vhv_name, u.vhv_moo, u.is_hl_coach,
           SUM(r.points_earned) as total_points
     FROM vhv_users u
     LEFT JOIN vhv_rewards r ON u.vhv_id = r.vhv_id AND r.approval_status = 'approved'
     GROUP BY u.vhv_id, u.vhv_name, u.vhv_moo, u.is_hl_coach
     ORDER BY total_points DESC, u.vhv_name ASC
");
$allLeaders = $leaderboardStmt->fetchAll();
$totalVhvs = count($allLeaders);

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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กระดานคะแนน อสม. - NCD ตาลสุม</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <style>
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
    </style>
</head>
<body class="vhv-accessibility">
    <div class="mobile-wrapper" style="padding-bottom: 90px;">
        <!-- Header -->
        <div class="vhv-header">
            <h3 style="color: var(--color-accent); margin: 0; font-size: 16px; font-weight: 800;">🏆 กระดานเกียรติยศ อสม.</h3>
            <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 14px;">50 อันดับ อสม. ผลงานคัดกรองสูงสุดในพื้นที่ตาลสุม</p>
        </div>

        <!-- Current VHV Score Widget -->
        <div class="card-dark" style="padding: 20px; box-shadow: var(--neumorph-flat);">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: center; text-align: center;">
                <div style="border-right: 1px solid rgba(13, 44, 84, 0.1); padding-right: 8px;">
                    <span style="color: var(--text-secondary); font-size: 13px; font-weight: bold; display: block; margin-bottom: 4px;">อันดับของคุณ</span>
                    <div style="font-size: 32px; font-weight: 800; color: var(--color-accent);">
                        #<?= $currentVhvRank ?: 'N/A' ?>
                    </div>
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-size: 13px; font-weight: bold; display: block; margin-bottom: 4px;">คะแนนผลงานสะสม</span>
                    <div style="font-size: 32px; font-weight: 800; color: var(--text-primary);">
                        <?= $currentVhvPoints ?> <span style="font-size: 16px; color: var(--text-secondary); font-weight: normal;">แต้ม</span>
                    </div>
                </div>
            </div>
            <div style="margin-top: 16px; font-size: 14px; text-align: center; color: var(--text-primary); border-top: 1px solid rgba(13, 44, 84, 0.1); padding-top: 12px; font-weight: bold; line-height: 1.5;">
                📊 คุณอยู่อันดับที่ <?= $currentVhvRank ?: 'N/A' ?> จาก อสม. ทั้งหมด <?= $totalVhvs ?> คน ของทั้งอำเภอตาลสุม
            </div>
            <?php 
            $myTitle = getPositiveTitle($currentVhvRank);
            if ($myTitle): 
            ?>
            <div style="margin-top: 10px; font-size: 14px; text-align: center; color: var(--color-accent); background: rgba(13, 44, 84, 0.05); padding: 8px; border-radius: 12px; font-weight: bold; box-shadow: var(--neumorph-inset);">
                🎯 ฉายาของคุณ: <?= $myTitle ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Leaderboard List -->
        <div style="margin-top: 20px;">
            <h4 style="color: var(--text-primary); font-size: 18px; margin-bottom: 12px; font-weight: 800;">50 อันดับสูงสุด</h4>
            
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
                    $badgeText = '🥈';
                } elseif ($rankNum === 3) {
                    $rankClass = 'badge-bronze';
                    $badgeText = '🥉';
                } else {
                    $rankClass = 'badge-custom';
                    $badgeText = '🎖️';
                }

                // Add special shiny badging based on points milestones
                $shinyBadge = '';
                if ($points >= 50) {
                    $shinyBadge = '<span class="badge-icon badge-gold" title="ฮีโร่ตาลสุม">🔥</span>';
                } elseif ($points >= 20) {
                    $shinyBadge = '<span class="badge-icon badge-silver" title="ผู้พิทักษ์หัวใจ">💖</span>';
                }
            ?>
                <div class="leaderboard-row" style="<?= $leader['vhv_id'] === $currentVhvId ? 'box-shadow: var(--neumorph-inset); background-color: var(--bg-darker);' : '' ?>">
                    <div class="leader-rank <?= $rankClass ?>" style="border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                        <?= $badgeText ?: $rankNum ?>
                    </div>
                    
                    <div class="leader-avatar">
                        <?= mb_substr($leader['vhv_name'], 0, 1) ?>
                    </div>
                    
                    <div class="leader-info">
                        <strong style="color: var(--text-primary); font-size: 16px;"><?= htmlspecialchars($leader['vhv_name']) ?></strong>
                        <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-secondary);">
                            หมู่ที่ <?= $leader['vhv_moo'] ?>
                        </p>
                        <?php if (!empty($leader['is_hl_coach'])): ?>
                            <div style="margin-top: 6px; font-size: 12px; color: #fbbf24; font-weight: bold; display: inline-block; background-color: rgba(251, 191, 36, 0.1); padding: 4px 8px; border-radius: 8px; border: 1px solid rgba(251,191,36,0.3);">
                                ✨ HL-Coach
                            </div>
                        <?php endif; ?>
                        <?php 
                        $rowTitle = getPositiveTitle($rankNum);
                        if ($rowTitle): 
                        ?>
                        <div style="margin-top: 6px; font-size: 12px; color: var(--color-accent); font-weight: bold; display: inline-block; background-color: rgba(13, 44, 84, 0.05); padding: 4px 8px; border-radius: 8px; box-shadow: var(--neumorph-inset);">
                            <?= $rowTitle ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="leader-score">
                        <div style="font-size: 20px; color: var(--color-accent);"><?= $points ?></div>
                        <span style="font-size: 12px; color: var(--text-muted);">แต้ม</span>
                        <?= $shinyBadge ?>
                    </div>
                </div>
            <?php 
                $rankNum++;
            endforeach; 
            ?>
        </div>

        <!-- Bottom Navigation Bar -->
        <div class="bottom-nav">
            <a href="index.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                หน้าแรก
            </a>
            <a href="scan.php" class="nav-link nav-scan-fab fab-scan-pulse">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                <span>สแกนบ้าน</span>
            </a>
            <a href="leaderboard.php" class="nav-link active">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                กระดานคะแนน
            </a>
            <a href="profile.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                ข้อมูลส่วนตัว
            </a>
        </div>
    </div>
</body>
</html>
