<?php
// vhv/leaderboard.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$currentVhvId = $_SESSION['vhv_id'];
$vhvName = $_SESSION['vhv_name'];

// Positive title mapping for the top 50 ranks (Unique top 5, tiered classes for 6-50)
function getPositiveTitle($rank)
{
    if ($rank <= 0 || $rank > 50)
        return '';

    // Top 5 are unique supreme titles
    if ($rank === 1)
        return '🏆 สุดยอดขุนพลสาธารณสุข' . DISTRICT_NAME;
    if ($rank === 2)
        return '🏆 ยอดอัศวินสุขภาพชุมชน';
    if ($rank === 3)
        return '🏆 ดาวรุ่งแห่งความห่วงใย';
    if ($rank === 4)
        return '✨ ผู้พิทักษ์หัวใจไร้โรค';
    if ($rank === 5)
        return '🌟 ขวัญใจสุขภาพดีถ้วนหน้า';

    // Base titles for group tiers (ranks 6-50 in groups of 5)
    $baseTitles = [
        1 => '💪 ยอดนักปราบเบาหวานและความดัน',
        2 => '🛡️ ผู้ปกป้องสุขภาวะ' . DISTRICT_NAME,
        3 => '❤️ เสาหลักสุขภาพดีชุมชน',
        4 => '🌱 ผู้หว่านเมล็ดพันธุ์สุขภาพ',
        5 => '🤝 พลังขับเคลื่อนตำบลสุขภาพดี',
        6 => '🎉 ผู้จุดประกายรักตนเอง',
        7 => '🍀 ทูตสุขภาพสร้างพลังบวก',
        8 => '💡 ปราชญ์สุขภาพคู่บ้านคู่เมือง',
        9 => '☀️ แสนสว่างนำทางชีวิตชีวา'
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

// Query Top 50 VHVs with points breakdown and subqueries for badges calculation
$leaderboardStmt = $pdo->query("
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
            WHERE r.vhv_id = u.vhv_id AND r.approval_status IN ('approved', 'waiting') AND r.is_sandbox = 0
        ) as total_points,
        (SELECT COUNT(*) FROM task_assignments WHERE vhv_id = u.vhv_id AND budget_year = 2026) as total_assigned,
        (SELECT COUNT(*) FROM task_assignments WHERE vhv_id = u.vhv_id AND budget_year = 2026 AND assignment_status = 'completed') as completed,
        (SELECT COUNT(*) FROM vhv_rewards WHERE vhv_id = u.vhv_id AND approval_status = 'waiting' AND is_sandbox = 0) as waiting_rewards
    FROM vhv_users u
    LEFT JOIN villages v ON u.vhid_code = v.vhid_code
    WHERE u.approved = 1
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
        LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.budget_year = 2026
        WHERE p.hoscode = ? 
          AND p.moo > 0 
          AND p.moo IS NOT NULL 
          AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        GROUP BY p.moo
        HAVING total_targets > 0
        ORDER BY p.moo ASC
    ";
    $villStmt = $pdo->prepare($villQuery);
    $villStmt->execute([$hoscode]);
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
        LEFT JOIN target_population p ON u.hoscode = p.hoscode AND (p.need_screen_dm = 1 OR p.need_screen_ht = 1)
        LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.budget_year = 2026
        GROUP BY u.hoscode
        HAVING COUNT(DISTINCT p.cid) > 0
        ORDER BY (COUNT(DISTINCT CASE WHEN a.assignment_status = 'completed' THEN p.cid END) / COUNT(DISTINCT p.cid)) DESC, u.hoscode ASC
    ";
    $hospitalStats = $pdo->query($hosQuery)->fetchAll();
} catch (\Exception $e) {
    // Fail silently
}
$hcNames = get_health_units();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กระดานคะแนน อสม. - NCDs <?= DISTRICT_NAME ?></title>
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
        .tab-btn.active {
            background: var(--bg-card) !important;
            color: var(--color-accent) !important;
            box-shadow: var(--neumorph-flat) !important;
        }

        .tab-content {
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
    </style>
</head>

<body class="vhv-accessibility">
    <div class="mobile-wrapper" style="padding-bottom: 90px;">
        <!-- Header -->
        <div class="vhv-header">
            <h3 style="color: var(--color-accent); margin: 0; font-size: 16px; font-weight: 800;">🏆 กระดานเกียรติยศ
                อสม.</h3>
            <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 14px;">50 อันดับ อสม.
                ผลงานคัดกรองสูงสุดในพื้นที่<?= DISTRICT_NAME ?></p>
        </div>

        <!-- Current VHV Score Widget -->
        <div class="card-dark" style="padding: 20px; box-shadow: var(--neumorph-flat);">
            <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: center; text-align: center;">
                <div style="border-right: 1px solid rgba(13, 44, 84, 0.1); padding-right: 8px;">
                    <span
                        style="color: var(--text-secondary); font-size: 13px; font-weight: bold; display: block; margin-bottom: 4px;">อันดับของคุณ</span>
                    <div style="font-size: 32px; font-weight: 800; color: var(--color-accent);">
                        #<?= $currentVhvRank ?: 'N/A' ?>
                    </div>
                </div>
                <div>
                    <span
                        style="color: var(--text-secondary); font-size: 13px; font-weight: bold; display: block; margin-bottom: 4px;">คะแนนผลงานสะสม</span>
                    <div style="font-size: 32px; font-weight: 800; color: var(--text-primary);">
                        <?= (float)$currentVhvPoints ?> <span
                            style="font-size: 16px; color: var(--text-secondary); font-weight: normal;">แต้ม</span>
                    </div>
                </div>
            </div>
            <div
                style="margin-top: 16px; font-size: 14px; text-align: center; color: var(--text-primary); border-top: 1px solid rgba(13, 44, 84, 0.1); padding-top: 12px; font-weight: bold; line-height: 1.5;">
                📊 คุณอยู่อันดับที่ <?= $currentVhvRank ?: 'N/A' ?> จาก อสม. ทั้งหมด <?= $totalVhvs ?> คน ของอำเภอ<?= DISTRICT_NAME ?>
            </div>
            <?php
            $myTitle = getPositiveTitle($currentVhvRank);
            if ($myTitle):
            ?>
                <div
                    style="margin-top: 10px; font-size: 14px; text-align: center; color: var(--color-accent); background: rgba(13, 44, 84, 0.05); padding: 8px; border-radius: 12px; font-weight: bold; box-shadow: var(--neumorph-inset);">
                    🎯 ฉายาของคุณ: <?= $myTitle ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab Bar for Mobile Responsiveness (Icon-only to prevent horizontal scrolling) -->
        <div class="tab-container" style="display: flex; gap: 8px; margin-top: 20px; margin-bottom: 20px; background: rgba(13,44,84,0.05); padding: 6px; border-radius: 14px; box-shadow: var(--neumorph-inset);">
            <button onclick="switchTab('leaderboard')" id="btn-leaderboard" class="tab-btn active" style="flex: 1; padding: 12px; border: none; border-radius: 10px; background: transparent; font-size: 20px; cursor: pointer; transition: all 0.3s ease;" title="อันดับ อสม.">🏆</button>
            <button onclick="switchTab('villages')" id="btn-villages" class="tab-btn" style="flex: 1; padding: 12px; border: none; border-radius: 10px; background: transparent; font-size: 20px; cursor: pointer; transition: all 0.3s ease;" title="ผลงานรายหมู่บ้าน">🏘️</button>
            <button onclick="switchTab('hospitals')" id="btn-hospitals" class="tab-btn" style="flex: 1; padding: 12px; border: none; border-radius: 10px; background: transparent; font-size: 20px; cursor: pointer; transition: all 0.3s ease;" title="ลีก รพ.สต.">🏥</button>
            <button onclick="switchTab('badges')" id="btn-badges" class="tab-btn" style="flex: 1; padding: 12px; border: none; border-radius: 10px; background: transparent; font-size: 20px; cursor: pointer; transition: all 0.3s ease;" title="เกณฑ์ตราเกียรติยศ">🛡️</button>
        </div>

        <!-- Tab 2: Village Progress Board -->
        <div id="content-villages" class="tab-content" style="display: none;">
            <?php if (!empty($villageStats)): ?>
                <div class="card-dark" style="padding: 20px; box-shadow: var(--neumorph-flat); margin-bottom: 20px;">
                    <h4 style="color: var(--color-accent); font-size: 16px; margin: 0 0 12px 0; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        🏘️ สมรภูมิคัดกรองรายหมู่บ้าน
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

        <!-- Tab 3: Hospital / Zone League Standings -->
        <div id="content-hospitals" class="tab-content" style="display: none;">
            <?php if (!empty($hospitalStats)): ?>
                <div class="card-dark" style="padding: 20px; box-shadow: var(--neumorph-flat); margin-bottom: 20px;">
                    <h4 style="color: var(--color-accent); font-size: 16px; margin: 0 0 12px 0; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        🏥 ลีกหน่วยบริการ รพ.สต. (ทั้งอำเภอ<?= DISTRICT_NAME ?>)
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

        <!-- Tab 4: VHV Badges Explanations Card -->
        <div id="content-badges" class="tab-content" style="display: none;">
            <div class="card-dark" style="padding: 20px; box-shadow: var(--neumorph-flat); margin-bottom: 20px;">
                <h4 style="color: var(--color-accent); font-size: 16px; margin: 0 0 12px 0; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    🛡️ ตำนานตราเกียรติยศ (อสม. คัดกรองดีเด่น)
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

        <!-- Tab 1: Leaderboard List -->
        <div id="content-leaderboard" class="tab-content">
            <!-- Leaderboard List -->
            <div style="margin-top: 20px;">
                <h4 style="color: var(--text-primary); font-size: 18px; margin-bottom: 12px; font-weight: 800;">50
                    อันดับสูงสุด</h4>

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
                    // Display trophy or medal or badge in rank area
                    $trophyHtml = '';
                    if ($rankNum === 1) {
                        $trophyHtml = '<span class="trophy-icon" title="อันดับ 1" style="font-size: 32px; filter: drop-shadow(0 4px 8px rgba(251, 191, 36, 0.55));">🏆</span>';
                    } elseif ($rankNum === 2) {
                        $trophyHtml = '<span class="trophy-icon silver" title="อันดับ 2" style="font-size: 30px; filter: drop-shadow(0 4px 8px rgba(156, 163, 175, 0.55)) sepia(0.3) hue-rotate(180deg) saturate(0.3) brightness(1.5);">🏆</span>'; // Silver Trophy cup
                    } elseif ($rankNum === 3) {
                        $trophyHtml = '<span class="trophy-icon bronze" title="อันดับ 3" style="font-size: 30px; filter: drop-shadow(0 4px 8px rgba(180, 100, 30, 0.55)) sepia(1) saturate(2) hue-rotate(5deg) brightness(0.85);">🏆</span>'; // Bronze Trophy cup
                    } elseif ($rankNum >= 4 && $rankNum <= 10) {
                        $trophyHtml = '<span class="trophy-icon medal" title="อันดับ ' . $rankNum . '" style="font-size: 26px; filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.15));">🏅</span>'; // Medal
                    } else {
                        $trophyHtml = '<span style="font-size: 14px; font-weight: 800; color: var(--text-secondary); background: var(--bg-main); width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; box-shadow: var(--neumorph-inset);">#' . $rankNum . '</span>';
                    }
                    ?>
                    <div class="leaderboard-row"
                        style="<?= $leader['vhv_id'] === $currentVhvId ? 'box-shadow: var(--neumorph-inset); background-color: var(--bg-darker);' : '' ?> display: flex; align-items: center; padding: 18px 16px; border-radius: var(--border-radius); background: var(--bg-card); box-shadow: var(--neumorph-flat); margin-bottom: 16px; position: relative; overflow: hidden;">

                        <!-- Faded background watermark rank number -->
                        <div style="position: absolute; right: 80px; bottom: -20px; font-size: 80px; font-weight: 900; color: rgba(13, 44, 84, 0.04); pointer-events: none; user-select: none; font-family: 'Outfit', sans-serif;">
                            <?= $rankNum ?>
                        </div>

                        <div style="width: 55px; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0; position: relative; z-index: 2;">
                            <?= $trophyHtml ?>
                        </div>

                        <?php
                        $badges = getBadgesList($leader['total_assigned'], $leader['completed'], $leader['waiting_rewards']);
                        ?>
                        <div class="leader-info" style="position: relative; z-index: 2;">
                            <strong
                                style="color: var(--text-primary); font-size: 16px;">
                                <?= htmlspecialchars($leader['vhv_name']) ?>
                                <?php foreach ($badges as $badge): ?>
                                    <span class="badge-icon" style="background: rgba(13,44,84,0.05); font-size: 14px;" title="<?= htmlspecialchars($badge['title']) ?>: <?= htmlspecialchars($badge['desc']) ?>">
                                        <?= $badge['icon'] ?>
                                    </span>
                                <?php endforeach; ?>
                            </strong>
                            <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-secondary);">
                                หมู่ที่ <?= $leader['vhv_moo'] ?><?= !empty($leader['village_name']) ? ' ' . htmlspecialchars($leader['village_name']) : '' ?>
                            </p>
                            <?php if (!empty($leader['is_hl_coach'])): ?>
                                <div
                                    style="margin-top: 6px; font-size: 12px; color: #fbbf24; font-weight: bold; display: inline-block; background-color: rgba(251, 191, 36, 0.1); padding: 4px 8px; border-radius: 8px; border: 1px solid rgba(251,191,36,0.3);">
                                    ✨ HL-Coach
                                </div>
                            <?php endif; ?>
                            <?php
                            $rowTitle = getPositiveTitle($rankNum);
                            if ($rowTitle):
                            ?>
                                <div
                                    style="margin-top: 6px; font-size: 12px; color: var(--color-accent); font-weight: bold; display: inline-block; background-color: rgba(13, 44, 84, 0.05); padding: 4px 8px; border-radius: 8px; box-shadow: var(--neumorph-inset);">
                                    <?= $rowTitle ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="leader-score" style="flex-shrink: 0; position: relative; z-index: 2;">
                            <div style="font-size: 20px; color: var(--color-accent);"><?= (float)$points ?></div>
                            <span style="font-size: 12px; color: var(--text-muted);">แต้ม</span>
                            <?= $shinyBadge ?>
                        </div>
                    </div>
                <?php
                    $rankNum++;
                endforeach;
                ?>
            </div>
        </div>

        <!-- Bottom Navigation Bar -->
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
                        d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z">
                    </path>
                </svg>
                กระดานคะแนน
            </a>
            <a href="profile.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                ข้อมูลส่วนตัว
            </a>
        </div>
    </div>
    <script>
        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

            // Show selected tab content and activate button
            document.getElementById('content-' + tabId).style.display = 'block';
            document.getElementById('btn-' + tabId).classList.add('active');
        }
    </script>
</body>

</html>