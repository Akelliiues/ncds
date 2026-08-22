<?php
// qr.php - Dual-Role Adaptive QR Router & Family Health Portal (PDPA Safe)
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/demo_banner.php';
require_once __DIR__ . '/config/demo_data.php';

$code = $_GET['code'] ?? $_GET['hid'] ?? $_GET['cid'] ?? '';
$isVhvLoggedIn = isset($_SESSION['vhv_id']);
$isDemo = DemoDataProvider::isDemoMode();

// -------------------------------------------------------------
// 1. DUAL-ROLE ROUTING: If logged in as VHV -> Redirect to screening form
// -------------------------------------------------------------
if ($isVhvLoggedIn && !empty($code)) {
    header("Location: vhv/screening_form.php?code=" . urlencode($code));
    exit();
}

// -------------------------------------------------------------
// 2. PUBLIC MODE: Look up House Residents & Positive Health Cards
// -------------------------------------------------------------
$residents = [];
$houseDisplay = 'บ้านเลขที่ประจำครอบครัว';
$villageDisplay = 'อำเภอ' . (defined('DISTRICT_NAME') ? DISTRICT_NAME : 'ตาลสุม');

if ($isDemo) {
    $allTargets = DemoDataProvider::getMockTargets();
    if (!empty($code)) {
        $cleanHid = trim(preg_replace('/^(บ้านเลขที่|บ้าน|ม\.)\s*/u', '', $code));
        if ($code === 'DEMO_HOUSE_12_1' || $code === 'DEMO_HID_1') $cleanHid = '12/1';
        elseif ($code === 'DEMO_HOUSE_88_2') $cleanHid = '88';
        elseif ($code === 'DEMO_HOUSE_101_2') $cleanHid = '101';
        elseif ($code === 'DEMO_HOUSE_15_3') $cleanHid = '15/3';
        elseif ($code === 'DEMO_HOUSE_54_4') $cleanHid = '54';
        elseif ($code === 'DEMO_HOUSE_9_5') $cleanHid = '9/1';

        $residents = array_values(array_filter($allTargets, function($r) use ($code, $cleanHid) {
            return ($r['cid'] === $code || $r['house_no'] === $code || $r['house_no'] === $cleanHid || (isset($r['hid']) && $r['hid'] === $code));
        }));
    }
    if (empty($residents)) {
        $residents = array_slice($allTargets, 0, 2);
    }
    $houseDisplay = 'บ้านเลขที่ ' . ($residents[0]['house_no'] ?? '12/1') . ' หมู่ ' . ($residents[0]['moo'] ?? '3');
    $villageDisplay = 'บ้านสำโรง ตำบลสำโรง';
} else {
    try {
        if (!empty($code)) {
            // Find by CID, HID, or House No
            $stmt = $pdo->prepare("
                SELECT p.*, v.village_name, v.sub_district_name
                FROM target_population p
                LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
                WHERE p.cid = ? OR p.hid = ? OR p.house_no = ?
                ORDER BY p.age DESC
                LIMIT 5
            ");
            $stmt->execute([$code, $code, $code]);
            $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($residents)) {
                $first = $residents[0];
                $houseDisplay = 'บ้านเลขที่ ' . htmlspecialchars($first['house_no']) . ' หมู่ ' . htmlspecialchars($first['moo']);
                $villageDisplay = (!empty($first['village_name']) ? 'บ้าน' . htmlspecialchars($first['village_name']) : '') . 
                                 (!empty($first['sub_district_name']) ? ' ต.' . htmlspecialchars($first['sub_district_name']) : '');
            }
        }
    } catch (\Throwable $e) {}
}

/**
 * Mask person name for PDPA Privacy (e.g. สมศรี ใจดี -> สม*** ใ***)
 */
function maskNamePDPA($firstName, $lastName, $gender, $age) {
    $title = ($gender == 1 || $gender === 'ชาย') ? 'คุณตา' : 'คุณยาย';
    if ($age < 60) {
        $title = ($gender == 1 || $gender === 'ชาย') ? 'คุณลุง' : 'คุณป้า';
    }
    if ($age < 45) {
        $title = 'คุณ';
    }

    $fLen = mb_strlen($firstName, 'UTF-8');
    $maskedF = ($fLen > 2) ? mb_substr($firstName, 0, 2, 'UTF-8') . str_repeat('*', min($fLen - 2, 4)) : $firstName;

    $lLen = mb_strlen($lastName, 'UTF-8');
    $maskedL = ($lLen > 1) ? mb_substr($lastName, 0, 1, 'UTF-8') . str_repeat('*', min($lLen - 1, 4)) : $lastName;

    return "{$title}{$firstName} ({$maskedF} {$maskedL})";
}

/**
 * Generate Positive Health Framing (ไร้ตัวเลขดิบที่ชวนกังวล, มีแต่พลังบวกและคำแนะนำนุ่มนวล)
 */
function evaluatePositiveHealthCard($screen, $prevScreen = null) {
    $sys = !empty($screen['sys_bp1']) ? (int)$screen['sys_bp1'] : 120;
    $dia = !empty($screen['dia_bp1']) ? (int)$screen['dia_bp1'] : 80;
    $dtx = !empty($screen['dtx_value']) ? (float)$screen['dtx_value'] : 100;
    $sleep = (int)($screen['sleep_quality'] ?? 1);

    // 1. Overall Vibe & Color Theme
    $statusTheme = 'green'; // green, yellow, orange
    $statusHeadline = 'ร่างกายแข็งแรง สดชื่นแจ่มใส!';
    $statusSubtext = 'ผลการดูแลสุขภาพอยู่ในเกณฑ์ที่ดีมาก ชวนรักษาความสดชื่นนี้ไว้อย่างต่อเนื่องครับ';
    $statusEmoji = '💚';

    if ($sys >= 160 || $dia >= 100 || $dtx >= 180) {
        $statusTheme = 'orange';
        $statusHeadline = 'ชวนมาร่วมฟื้นฟูเติมพลังกาย';
        $statusSubtext = 'ช่วงนี้ร่างกายอยากให้เอาใจใส่เป็นพิเศษ ชวนไปพบคุณหมอที่ รพ.สต. เพื่อตรวจเช็คให้สบายใจนะครับ';
        $statusEmoji = '💖';
    } elseif ($sys >= 140 || $dia >= 90 || $dtx >= 126) {
        $statusTheme = 'yellow';
        $statusHeadline = 'ชวนเพิ่มความสดชื่นและใส่ใจสุขภาพ';
        $statusSubtext = 'ร่างกายยังคงแข็งแรงดี ชวนลดหวาน มัน เค็มลงอีกนิด ดื่มน้ำเปล่าบ่อยๆ จะรู้สึกเบาสบายยิ่งขึ้นครับ';
        $statusEmoji = '💛';
    }

    // 2. Blood Circulation (BP)
    $bpEvaluation = 'ระบบไหลเวียนโลหิตทำงานได้ดีมาก สดชื่นกระปรี้กระเปร่า 🟢';
    if ($sys >= 160 || $dia >= 100) {
        $bpEvaluation = 'ชวนไปตรวจเช็คความดันเพิ่มเติมกับคุณหมอที่ รพ.สต. เพื่อความอุ่นใจ 💖';
    } elseif ($sys >= 140 || $dia >= 90) {
        $bpEvaluation = 'ชวนดื่มน้ำเปล่าบ่อยๆ ผ่อนคลายกล้ามเนื้อ ลดเค็มลงนิดหน่อย ร่างกายจะสบายขึ้น 🟡';
    }

    // 3. Energy & Sugar Balance (DTX)
    $dtxEvaluation = 'ระดับพลังงานสมดุลดี ร่างกายเบาสบาย 🟢';
    if ($dtx >= 180) {
        $dtxEvaluation = 'ชวนหลีกเลี่ยงของหวานจัด และนัดพบคุณหมอเพื่อตรวจติดตามสุขภาพ 💖';
    } elseif ($dtx >= 126) {
        $dtxEvaluation = 'ชวนลดเครื่องดื่มรสหวาน ขนมหวานลงอีกนิด เพื่อสุขภาพที่ดียิ่งขึ้น 🟡';
    }

    // 4. Sleep Quality (1น.)
    $sleepEvaluation = 'หลับสนิทดี ตื่นมาสดใสมีพลัง 🌙';
    if ($sleep === 2) {
        $sleepEvaluation = 'มีตื่นกลางดึกบ้าง ชวนจิบน้ำอุ่นและผ่อนคลายจิตใจก่อนนอน 🍵';
    } elseif ($sleep === 3) {
        $sleepEvaluation = 'นอนไม่ค่อยหลับ ชวนปรับห้องนอนให้เงียบสงบ ไม่เล่นมือถือก่อนนอน 🛏️';
    }

    // 5. Progress Indicator
    $progressText = '🌿 สุขภาพคงที่ แข็งแรงสม่ำเสมอ';
    $progressBadge = 'stable';
    if (!empty($prevScreen)) {
        $prevSys = (int)($prevScreen['sys_bp1'] ?? 120);
        $prevDtx = (float)($prevScreen['dtx_value'] ?? 100);
        if ($sys < $prevSys || $dtx < $prevDtx) {
            $progressText = '📈 สุขภาพและผลตรวจดีขึ้นกว่ารอบก่อนหน้า!';
            $progressBadge = 'better';
        } elseif ($sys > $prevSys + 15 || $dtx > $prevDtx + 25) {
            $progressText = '💖 ชวนลูกหลานช่วยดูแลเอาใจใส่เป็นพิเศษในรอบนี้';
            $progressBadge = 'care';
        }
    }

    // 6. Friendly tips for Family & Children
    $familyTips = [
        'ชวนคุณพ่อ/คุณแม่ดื่มน้ำเปล่าบ่อยๆ ระหว่างวัน',
        'ชวนเดินเล่นรับลมและแสงแดดยามเช้า 15-20 นาที',
        'เตือนท่านรับประทานยาและอาหารให้ตรงเวลา',
        'โทรศัพท์พูดคุยส่งกำลังใจให้ท่านเป็นประจำ'
    ];

    return [
        'theme' => $statusTheme,
        'headline' => $statusHeadline,
        'subtext' => $statusSubtext,
        'emoji' => $statusEmoji,
        'bp' => $bpEvaluation,
        'dtx' => $dtxEvaluation,
        'sleep' => $sleepEvaluation,
        'progress_text' => $progressText,
        'progress_badge' => $progressBadge,
        'family_tip' => $familyTips[array_rand($familyTips)],
        'date' => !empty($screen['screening_date']) ? $screen['screening_date'] : date('d/m/Y'),
        'vhv_name' => !empty($screen['vhv_name']) ? $screen['vhv_name'] : 'อสม. ประจำคุ้ม'
    ];
}

// Build cards dataset for residents
$cardsData = [];
foreach ($residents as $res) {
    $cid = $res['cid'];
    $screenings = [];

    if ($isDemo) {
        // Mock history screenings for demo
        $screenings = [
            [
                'screening_id' => 102,
                'screening_date' => '18 ส.ค. 2569',
                'sys_bp1' => 124,
                'dia_bp1' => 78,
                'dtx_value' => 108,
                'sleep_quality' => 1,
                'vhv_name' => 'อสม.สมใจ พิทักษ์ไทย'
            ],
            [
                'screening_id' => 101,
                'screening_date' => '15 พ.ค. 2569',
                'sys_bp1' => 138,
                'dia_bp1' => 86,
                'dtx_value' => 122,
                'sleep_quality' => 2,
                'vhv_name' => 'อสม.สมใจ พิทักษ์ไทย'
            ]
        ];
    } else {
        try {
            $stmtS = $pdo->prepare("
                SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as vhv_name
                FROM screening_results s
                LEFT JOIN users u ON s.vhv_id = u.username OR s.vhv_id = u.id
                WHERE s.target_cid = ?
                ORDER BY s.screening_date DESC, s.screening_id DESC
                LIMIT 5
            ");
            $stmtS->execute([$cid]);
            $screenings = $stmtS->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}
    }

    $cardsData[] = [
        'cid' => $cid,
        'masked_name' => maskNamePDPA($res['first_name'] ?? 'คุณ', $res['last_name'] ?? 'รักสุข', $res['gender'] ?? 2, $res['age'] ?? 65),
        'age' => $res['age'] ?? 65,
        'gender' => $res['gender'] ?? 2,
        'screenings' => $screenings
    ];
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การ์ดสุขภาพครอบครัว - NCDs Portal อำเภอ<?= defined('DISTRICT_NAME') ? DISTRICT_NAME : 'ตาลสุม' ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .portal-wrapper {
            max-width: 520px;
            margin: 20px auto;
            padding: 0 16px;
            box-sizing: border-box;
        }

        .house-header-card {
            background: linear-gradient(135deg, #1E3A8A, #3B82F6);
            color: white;
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.4);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .house-header-card::after {
            content: "🏠";
            position: absolute;
            right: 15px;
            bottom: 5px;
            font-size: 70px;
            opacity: 0.15;
            pointer-events: none;
        }

        .health-card {
            background: var(--bg-card);
            border-radius: 24px;
            padding: 22px;
            box-shadow: var(--neumorph-flat);
            border: 1.5px solid var(--border-color);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .health-card.theme-green {
            border-top: 6px solid #10B981;
        }

        .health-card.theme-yellow {
            border-top: 6px solid #F59E0B;
        }

        .health-card.theme-orange {
            border-top: 6px solid #F97316;
        }

        .vibe-banner {
            border-radius: 18px;
            padding: 16px;
            margin-bottom: 18px;
            text-align: center;
        }

        .vibe-banner.green {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
        }

        .vibe-banner.yellow {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #D97706;
        }

        .vibe-banner.orange {
            background: rgba(249, 115, 22, 0.1);
            border: 1px solid rgba(249, 115, 22, 0.3);
            color: #EA580C;
        }

        .item-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px;
            background: var(--bg-darker);
            border-radius: 14px;
            margin-bottom: 10px;
            border: 1px solid var(--border-color);
            font-size: 13.5px;
            line-height: 1.45;
        }

        .round-slider-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 12.5px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .round-slider-btn.active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-line-share {
            background: #06C755;
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(6, 199, 85, 0.35);
            transition: all 0.2s;
            box-sizing: border-box;
            margin-bottom: 12px;
        }

        .btn-line-share:hover {
            filter: brightness(1.08);
            transform: translateY(-1px);
        }

        .btn-vhv-login {
            background: var(--bg-card);
            border: 1.5px dashed var(--color-primary);
            color: var(--color-primary);
            padding: 12px 18px;
            border-radius: 14px;
            font-size: 13.5px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            text-decoration: none;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .btn-vhv-login:hover {
            background: rgba(59, 130, 246, 0.08);
        }

        .person-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            border-radius: 14px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            font-size: 13px;
            font-weight: 800;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
        }

        .person-tab.active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="vhv-accessibility" style="min-height: 100vh; background: var(--bg-darker); padding: 10px 0;">

<div class="portal-wrapper">
    <!-- House Title Card -->
    <div class="house-header-card">
        <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9; margin-bottom: 4px;">
            🌟 บัตรพลังใจสุขภาพประจำบ้าน
        </div>
        <h2 style="margin: 0; font-size: 20px; font-weight: 900; line-height: 1.3;">
            <?= htmlspecialchars($houseDisplay) ?>
        </h2>
        <div style="font-size: 13px; opacity: 0.85; margin-top: 4px;">
            <?= htmlspecialchars($villageDisplay) ?>
        </div>
    </div>

    <?php if (empty($cardsData)): ?>
        <div class="health-card" style="text-align: center; padding: 40px 20px;">
            <div style="font-size: 50px; margin-bottom: 12px;">🏡</div>
            <h3 style="margin: 0 0 8px 0; color: var(--color-accent); font-size: 18px;">ไม่พบข้อมูลบ้านหลังนี้</h3>
            <p style="color: var(--text-secondary); font-size: 13px; margin: 0;">
                รหัส QR Code อาจไม่ถูกต้อง หรือยังไม่มีข้อมูลในระบบ
            </p>
        </div>
    <?php else: ?>

        <!-- Multiple Persons Tab (If > 1 resident) -->
        <?php if (count($cardsData) > 1): ?>
            <div style="display: flex; gap: 8px; overflow-x: auto; margin-bottom: 16px; padding-bottom: 4px;">
                <?php foreach ($cardsData as $idx => $pData): ?>
                    <button type="button" class="person-tab <?= $idx === 0 ? 'active' : '' ?>" onclick="switchPerson(<?= $idx ?>)">
                        <span><?= ($pData['gender'] == 1 || $pData['gender'] === 'ชาย') ? '👴' : '👵' ?></span>
                        <span><?= htmlspecialchars($pData['masked_name']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Person Health Card Containers -->
        <?php foreach ($cardsData as $pIdx => $pData): ?>
            <div id="person-card-<?= $pIdx ?>" class="person-container" style="display: <?= $pIdx === 0 ? 'block' : 'none' ?>;">
                
                <?php 
                $historyScreenings = $pData['screenings'];
                if (empty($historyScreenings)): 
                ?>
                    <!-- No Screening Yet Card -->
                    <div class="health-card" style="text-align: center; padding: 30px 20px;">
                        <div style="font-size: 45px; margin-bottom: 12px;">⏳</div>
                        <h3 style="margin: 0 0 6px 0; font-size: 17px; color: var(--color-accent);">
                            <?= htmlspecialchars($pData['masked_name']) ?>
                        </h3>
                        <p style="color: var(--text-secondary); font-size: 13.5px; margin: 0 0 16px 0;">
                            อยู่ระหว่างรอคุณหมอ อสม. ลงพื้นที่เยี่ยมเยียนและคัดกรองสุขภาพรอบปัจจุบัน
                        </p>
                        <div style="background: var(--bg-darker); border-radius: 14px; padding: 14px; font-size: 13px; color: var(--text-primary); text-align: left;">
                            💡 <strong>คำแนะนำเพื่อสุขภาพ:</strong> ดื่มน้ำเปล่าสะอาด รับประทานอาหารปรุงสุก และออกกำลังกายเบาๆ ทุกวันครับ
                        </div>
                    </div>
                <?php else: ?>

                    <!-- Round Navigation Slider / Buttons (if > 1 screening) -->
                    <?php if (count($historyScreenings) > 1): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                            <span style="font-size: 12px; font-weight: 800; color: var(--text-secondary);">
                                📅 รอบการตรวจคัดกรอง:
                            </span>
                            <div style="display: flex; gap: 6px;">
                                <?php foreach ($historyScreenings as $sIdx => $sRow): ?>
                                    <button type="button" class="round-slider-btn <?= $sIdx === 0 ? 'active' : '' ?>" onclick="switchRound(<?= $pIdx ?>, <?= $sIdx ?>)">
                                        <?= $sIdx === 0 ? '✨ ล่าสุด' : 'รอบที่ ' . (count($historyScreenings) - $sIdx) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Screening Round Slides -->
                    <?php 
                    foreach ($historyScreenings as $sIdx => $sRow): 
                        $prevRow = isset($historyScreenings[$sIdx + 1]) ? $historyScreenings[$sIdx + 1] : null;
                        $cardEval = evaluatePositiveHealthCard($sRow, $prevRow);
                    ?>
                        <div id="slide-<?= $pIdx ?>-<?= $sIdx ?>" class="round-slide-<?= $pIdx ?>" style="display: <?= $sIdx === 0 ? 'block' : 'none' ?>;">
                            <div class="health-card theme-<?= $cardEval['theme'] ?>">
                                
                                <!-- Card Header -->
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                                    <div>
                                        <h3 style="margin: 0; font-size: 18px; font-weight: 900; color: var(--color-accent);">
                                            <?= htmlspecialchars($pData['masked_name']) ?>
                                        </h3>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                                            📅 ตรวจเมื่อ: <?= htmlspecialchars($cardEval['date']) ?> • โดย: <?= htmlspecialchars($cardEval['vhv_name']) ?>
                                        </div>
                                    </div>
                                    <div style="font-size: 32px;">
                                        <?= $cardEval['emoji'] ?>
                                    </div>
                                </div>

                                <!-- Headline Vibe Banner -->
                                <div class="vibe-banner <?= $cardEval['theme'] ?>">
                                    <div style="font-size: 16px; font-weight: 900; margin-bottom: 4px;">
                                        <?= $cardEval['headline'] ?>
                                    </div>
                                    <div style="font-size: 12.5px; opacity: 0.9; line-height: 1.4;">
                                        <?= $cardEval['subtext'] ?>
                                    </div>
                                </div>

                                <!-- Progress Indicator -->
                                <div style="background: rgba(59, 130, 246, 0.08); border-radius: 12px; padding: 10px 14px; margin-bottom: 16px; font-size: 13px; font-weight: 800; color: var(--color-primary); display: flex; align-items: center; gap: 8px;">
                                    <span><?= $cardEval['progress_text'] ?></span>
                                </div>

                                <!-- 3 Dimension Positive Evaluation List -->
                                <div style="margin-bottom: 16px;">
                                    <div class="item-row">
                                        <span style="font-size: 20px;">❤️</span>
                                        <div>
                                            <strong>ระบบไหลเวียนโลหิต:</strong><br>
                                            <span style="color: var(--text-secondary);"><?= $cardEval['bp'] ?></span>
                                        </div>
                                    </div>
                                    <div class="item-row">
                                        <span style="font-size: 20px;">⚡</span>
                                        <div>
                                            <strong>สมดุลพลังงานร่างกาย:</strong><br>
                                            <span style="color: var(--text-secondary);"><?= $cardEval['dtx'] ?></span>
                                        </div>
                                    </div>
                                    <div class="item-row">
                                        <span style="font-size: 20px;">🌙</span>
                                        <div>
                                            <strong>คุณภาพการนอนหลับ (1น.):</strong><br>
                                            <span style="color: var(--text-secondary);"><?= $cardEval['sleep'] ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Friendly Family Tips -->
                                <div style="background: var(--bg-darker); border-left: 3.5px solid #10B981; border-radius: 0 14px 14px 0; padding: 12px 14px; margin-bottom: 18px; font-size: 13px;">
                                    <strong style="color: #10B981;">💡 ข้อความฝากถึงลูกหลาน:</strong><br>
                                    <span style="color: var(--text-primary); margin-top: 4px; display: inline-block;">
                                        "<?= $cardEval['family_tip'] ?>"
                                    </span>
                                </div>

                                <!-- Actions: Share to LINE -->
                                <?php
                                $shareText = "💌 บัตรพลังใจสุขภาพ: " . $pData['masked_name'] . "\n"
                                           . "🏡 " . $houseDisplay . "\n"
                                           . "✨ สถานะ: " . $cardEval['headline'] . "\n"
                                           . "💡 ฝากถึงลูกหลาน: " . $cardEval['family_tip'] . "\n"
                                           . "เปิดดูประวัติสุขภาพย้อนหลังได้ที่: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                                $lineShareUrl = "https://line.me/R/share?text=" . urlencode($shareText);
                                ?>
                                <a href="<?= $lineShareUrl ?>" target="_blank" class="btn-line-share">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 5.91 2 10.74c0 3.01 1.77 5.67 4.5 7.15-.2.74-.72 2.68-.82 3.1-.14.54.19.53.41.38.17-.11 2.37-1.63 3.32-2.29.84.14 1.7.22 2.59.22 5.52 0 10-3.91 10-8.74S17.52 2 12 2z"/>
                                    </svg>
                                    <span>ส่งการ์ดนี้เข้า LINE ครอบครัว</span>
                                </a>

                            </div>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <!-- VHV Login Switcher (For Health Volunteers) -->
    <div style="margin-top: 24px; text-align: center;">
        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">
            🔒 ข้อมูลนี้ได้รับการคุ้มครองตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล (PDPA)
        </div>
        <a href="index.php?target_hid=<?= urlencode($code) ?>" class="btn-vhv-login">
            <span>👩‍⚕️ คุณคือ อสม. ประจำบ้านหลังนี้ใช่หรือไม่? ➔ เข้าสู่ระบบคัดกรอง</span>
        </a>
    </div>
</div>

<script>
    function switchPerson(pIdx) {
        document.querySelectorAll('.person-tab').forEach((tab, i) => {
            tab.classList.toggle('active', i === pIdx);
        });
        document.querySelectorAll('.person-container').forEach((c, i) => {
            c.style.display = (i === pIdx) ? 'block' : 'none';
        });
    }

    function switchRound(pIdx, sIdx) {
        const slideButtons = event.currentTarget.parentElement.querySelectorAll('.round-slider-btn');
        slideButtons.forEach((btn, i) => {
            btn.classList.toggle('active', i === sIdx);
        });

        document.querySelectorAll('.round-slide-' + pIdx).forEach((slide, i) => {
            slide.style.display = (i === sIdx) ? 'block' : 'none';
        });
    }
</script>

</body>
</html>
