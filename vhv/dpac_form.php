<?php
// vhv/dpac_form.php
session_start();

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

$isShell = isset($_GET['shell']) && $_GET['shell'] === 'true';
$fid = $_GET['fid'] ?? '';

if (!$isShell && empty($fid)) {
    header("Location: index.php");
    exit();
}

$vhvId = $_SESSION['vhv_id'];
$task = null;
$riskType = '';
$isDM = false;
$isHT = false;

if (!$isShell) {
    require_once __DIR__ . '/../config/db.php';

    // Fetch Followup Data
    $stmt = $pdo->prepare("
        SELECT f.*, e.risk_type, p.cid, p.first_name, p.last_name, p.house_no, p.moo
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        JOIN target_population p ON e.cid = p.cid
        WHERE f.followup_id = ? AND f.vhv_id = ? AND f.status = 'pending'
    ");
    $stmt->execute([$fid, $vhvId]);
    $task = $stmt->fetch();

    if (!$task) {
        echo "<script>alert('ไม่พบงาน หรือถูกดำเนินการไปแล้ว'); window.location.href='index.php';</script>";
        exit();
    }

    $riskType = $task['risk_type'];
    $isDM = in_array($riskType, ['DM', 'BOTH']);
    $isHT = in_array($riskType, ['HT', 'BOTH']);
}

// Save Data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $weight = $_POST['weight'] ?? null;
    $height = $_POST['height'] ?? null;
    $waist = $_POST['waist'] ?? null;
    $fbs = $_POST['fbs'] ?? null;
    $sbp = $_POST['bp_sys'] ?? null;
    $dbp = $_POST['bp_dia'] ?? null;
    $healthRisk = $_POST['health_risk_level'] ?? '';
    $advice = $_POST['advice_given'] ?? '';

    $pdo->beginTransaction();
    try {
        $updateStmt = $pdo->prepare("
            UPDATE dpac_followups 
            SET status = 'completed', completed_at = CURRENT_TIMESTAMP,
                weight = ?, height = ?, waist = ?,
                fbs = ?, bp_sys = ?, bp_dia = ?,
                health_risk_level = ?, advice_given = ?
            WHERE followup_id = ? AND vhv_id = ?
        ");
        $updateStmt->execute([$weight, $height, $waist, $fbs, $sbp, $dbp, $healthRisk, $advice, $fid, $vhvId]);

        // Insert reward point (+1 point) for DPAC followup completion
        $checkReward = $pdo->prepare("SELECT COUNT(*) FROM vhv_rewards WHERE vhv_id = ? AND followup_id = ?");
        $checkReward->execute([$vhvId, $fid]);
        if ($checkReward->fetchColumn() == 0) {
            $rewardStmt = $pdo->prepare("
                INSERT INTO vhv_rewards (vhv_id, followup_id, points_earned, approval_status, approved_at)
                VALUES (?, ?, 1, 'approved', CURRENT_TIMESTAMP)
            ");
            $rewardStmt->execute([$vhvId, $fid]);
        }

        $pdo->commit();
        echo "<script>alert('บันทึกผลการติดตามสำเร็จ! อสม. ได้รับ +1 คะแนนสะสม'); window.location.href='index.php';</script>";
        exit();
    } catch (\Exception $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบฟอร์มติดตาม DPAC - อสม.</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/app.js"></script>
    <style>
        /* Row grid 2-column layout */
        .row-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-section {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--neumorph-flat);
        }

        .advice-box {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .advice-box h4 {
            color: #1e40af;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .advice-box ul {
            margin: 0;
            padding-left: 20px;
            color: #334155;
            font-size: 14px;
        }

        .advice-box li {
            margin-bottom: 5px;
        }

        .risk-eval {
            font-size: 18px;
            font-weight: 800;
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        .risk-eval.normal {
            background-color: #dcfce7;
            color: #166534;
        }

        .risk-eval.risk {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .risk-eval.high {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .risk-eval.default {
            background-color: var(--bg-main);
            color: var(--text-secondary);
        }

        /* ── Advice visual card grid ────────────────────────── */
        .advice-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }

        .advice-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            gap: 6px;
            background-color: var(--bg-card);
            border: 2px solid transparent;
            border-radius: 16px;
            padding: 12px 6px 10px;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-align: center;
            position: relative;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            min-height: 124px;
        }

        .advice-card:active {
            transform: scale(0.95);
        }

        .advice-card.selected {
            border-color: var(--color-green);
            background-color: rgba(16, 185, 129, 0.07);
            box-shadow: var(--neumorph-inset), 0 0 0 2px rgba(16, 185, 129, 0.25);
        }

        .advice-card.selected .advice-icon-wrap {
            background: rgba(16, 185, 129, 0.15);
        }

        .advice-card.selected .advice-card-label {
            color: var(--color-green);
        }

        .advice-icon-wrap {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: var(--bg-darker);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            flex-shrink: 0;
        }

        .advice-icon-wrap svg {
            width: 36px;
            height: 36px;
        }

        .advice-card-label {
            font-size: 12.5px;
            font-weight: 800;
            color: var(--text-secondary);
            line-height: 1.3;
            transition: color 0.2s;
        }

        .advice-card-check {
            position: absolute;
            top: 6px;
            right: 8px;
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .advice-card.selected .advice-card-check {
            opacity: 1;
        }

        /* ── Advice summary box ─────────────────────────────── */
        .advice-summary-box {
            background: var(--bg-darker);
            border-radius: 14px;
            box-shadow: var(--neumorph-inset);
            padding: 14px 16px;
            min-height: 70px;
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.7;
            margin-top: 12px;
            border-left: 4px solid var(--color-accent);
            transition: all 0.3s ease;
        }

        .advice-summary-placeholder {
            color: var(--text-muted);
            font-style: italic;
            font-size: 13px;
        }

        .advice-summary-count {
            font-size: 12px;
            font-weight: 800;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        /* Watermark round number */
        @keyframes floatNum {

            0%,
            100% {
                transform: translateY(0px) scale(1);
                opacity: 0.07;
            }

            50% {
                transform: translateY(-6px) scale(1.03);
                opacity: 0.11;
            }
        }

        @keyframes shimmerNum {
            0% {
                text-shadow: 0 0 40px rgba(56, 189, 248, 0);
            }

            50% {
                text-shadow: 0 0 80px rgba(56, 189, 248, 0.25), 0 0 120px rgba(56, 189, 248, 0.1);
            }

            100% {
                text-shadow: 0 0 40px rgba(56, 189, 248, 0);
            }
        }

        .round-watermark {
            position: absolute;
            right: -10px;
            bottom: -20px;
            font-size: 160px;
            font-weight: 900;
            line-height: 1;
            color: #fff;
            opacity: 0.07;
            pointer-events: none;
            user-select: none;
            letter-spacing: -8px;
            animation: floatNum 4s ease-in-out infinite, shimmerNum 4s ease-in-out infinite;
            font-family: 'Outfit', sans-serif;
        }

        .round-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(56, 189, 248, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 50px;
            padding: 4px 12px;
            font-size: 13px;
            font-weight: 700;
            color: #7dd3fc;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .round-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #38bdf8;
            box-shadow: 0 0 6px #38bdf8;
            display: inline-block;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                box-shadow: 0 0 6px #38bdf8;
            }

            50% {
                opacity: 0.5;
                box-shadow: 0 0 12px #38bdf8;
            }
        }

        /* Back button themed */
        .btn-back-themed {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 50px;
            background-color: var(--bg-card);
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 800;
            border: none;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all var(--transition-speed);
            text-decoration: none;
        }

        .btn-back-themed:active {
            box-shadow: var(--neumorph-inset);
            transform: scale(0.97);
        }

        .btn-back-themed svg {
            flex-shrink: 0;
        }

        /* Waist unit toggle */
        .unit-toggle-group {
            display: flex;
            gap: 0;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: var(--neumorph-inset);
            background-color: var(--bg-darker);
            width: fit-content;
            margin-bottom: 10px;
        }

        .unit-toggle-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            border-radius: 50px;
            transition: all 0.2s ease;
            font-family: var(--font-base);
        }

        .unit-toggle-btn.active {
            background-color: var(--color-primary);
            color: #fff;
            box-shadow: 2px 2px 6px rgba(13, 44, 84, 0.3);
        }

        .waist-convert-hint {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 6px;
            min-height: 18px;
            transition: all 0.2s;
        }
    </style>
</head>

<body class="vhv-accessibility">
    <div class="mobile-wrapper">
        <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 20px;">
            <button class="btn-back-themed" onclick="window.location.href='index.php'">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M15 19l-7-7 7-7"></path>
                </svg>
                กลับ
            </button>
            <h2 style="font-size: 18px; margin: 0; flex: 1;">ติดตามกลุ่มเสี่ยง DPAC</h2>
        </div>

        <div style="padding: 20px;">
            <div class="form-section"
                style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white; position: relative; overflow: hidden;">
                <!-- Watermark round number -->
                <span class="round-watermark" id="dpac-watermark"><?= $isShell ? '' : $task['round_number'] ?></span>

                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px;">
                    <div class="round-badge" id="dpac-round-badge" style="margin-bottom: 0;">รอบที่
                        <?= $isShell ? '' : $task['round_number'] ?>
                    </div>
                    <div class="round-badge" id="dpac-skip-badge"
                        style="display: <?= (!$isShell && isset($task['skip_count']) && $task['skip_count'] > 0) ? 'inline-flex' : 'none' ?>; background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.3); color: #facc15; margin-bottom: 0;">
                        ข้ามแล้ว <span
                            id="dpac-skip-count"><?= $isShell ? '0' : intval($task['skip_count'] ?? 0) ?></span>/3 ครั้ง
                    </div>
                </div>
                <h3 style="margin-top: 0; color: #38bdf8; font-size: 20px;" id="dpac-round-title">รอบติดตามที่
                    <?= $isShell ? '' : $task['round_number'] ?>
                </h3>
                <p style="margin: 5px 0;"><strong>ชื่อ-สกุล:</strong>
                    <span
                        id="dpac-name"><?= $isShell ? '' : htmlspecialchars($task['first_name'] . ' ' . $task['last_name']) ?></span>
                </p>
                <p style="margin: 5px 0;"><strong>ที่อยู่:</strong> บ้านเลขที่
                    <span
                        id="dpac-house-moo"><?= $isShell ? '' : htmlspecialchars($task['house_no']) . ' หมู่ ' . htmlspecialchars($task['moo']) ?></span>
                </p>
                <p style="margin: 5px 0; color: #fbbf24; font-weight: bold;">⚠️ กลุ่มเสี่ยง: <span
                        id="dpac-risk-type"><?= $isShell ? '' : $task['risk_type'] ?></span>
                </p>
            </div>

            <?php if (isset($error)): ?>
                <div style="color: red; margin-bottom: 15px; font-weight: bold; text-align: center;"><?= $error ?></div>
            <?php endif; ?>

            <form method="post" id="dpacForm">
                <input type="hidden" name="waist" id="waistCmHidden" value="">
                <!-- ข้อมูลพื้นฐาน -->
                <div class="form-section">
                    <h3 style="color: var(--color-accent); margin-top: 0;">1. ข้อมูลร่างกายพื้นฐาน</h3>
                    <div class="row-grid" style="margin-top: 15px;">
                        <div>
                            <label class="form-label">น้ำหนัก (กก.)</label>
                            <input type="number" step="0.1" name="weight" id="weight" class="form-input-text" required>
                        </div>
                        <div>
                            <label class="form-label">ส่วนสูง (ซม.)</label>
                            <input type="number" step="0.1" name="height" id="height" class="form-input-text" required>
                        </div>
                    </div>
                    <div style="margin-top: 15px;">
                        <label class="form-label">รอบเอว</label>
                        <div class="unit-toggle-group" id="waistUnitGroup">
                            <button type="button" class="unit-toggle-btn" id="btnCm"
                                onclick="switchWaistUnit('cm')">ซม.</button>
                            <button type="button" class="unit-toggle-btn active" id="btnInch"
                                onclick="switchWaistUnit('inch')">นิ้ว</button>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="number" step="0.1" id="waist" class="form-input-text" required
                                placeholder="นิ้ว">
                            <span id="waistUnit"
                                style="font-weight: 800; color: var(--text-secondary); white-space: nowrap; min-width: 32px;">นิ้ว</span>
                        </div>
                        <div class="waist-convert-hint" id="waistHint"></div>
                    </div>
                </div>

                <!-- ข้อมูลเฉพาะโรค -->
                <div class="form-section">
                    <h3 style="color: var(--color-accent); margin-top: 0;">2. ตรวจวัดค่าความเสี่ยง</h3>

                    <div style="margin-top: 15px; display: <?= $isDM ? 'block' : 'none' ?>;" id="section-dm-fields">
                        <label class="form-label">ระดับน้ำตาลในเลือด (FBS) (mg/dL)</label>
                        <input type="number" name="fbs" id="fbs" class="form-input-text" oninput="calculateRisk()"
                            <?= $isDM ? 'required' : '' ?> placeholder="ตัวอย่าง: 110">
                    </div>

                    <div class="row-grid" style="margin-top: 15px; display: <?= $isHT ? 'block' : 'none' ?>;"
                        id="section-ht-fields">
                        <div>
                            <label class="form-label">ความดันตัวบน (SYS)</label>
                            <input type="number" name="bp_sys" id="bp_sys" class="form-input-text"
                                oninput="calculateRisk()" <?= $isHT ? 'required' : '' ?> placeholder="ตัวอย่าง: 130">
                        </div>
                        <div>
                            <label class="form-label">ความดันตัวล่าง (DIA)</label>
                            <input type="number" name="bp_dia" id="bp_dia" class="form-input-text"
                                oninput="calculateRisk()" <?= $isHT ? 'required' : '' ?> placeholder="ตัวอย่าง: 85">
                        </div>
                    </div>

                    <div style="margin-top: 25px;">
                        <label class="form-label">ผลการประเมิน Health Risk ประจำรอบนี้</label>
                        <input type="hidden" name="health_risk_level" id="health_risk_level" value="">
                        <div id="risk_display" class="risk-eval default">
                            กรุณากรอกข้อมูลให้ครบเพื่อประเมินผล
                        </div>
                    </div>
                </div>

                <!-- การให้คำแนะนำ (อิงตามโรค) -->
                <div class="form-section">
                    <h3 style="color: var(--color-accent); margin-top: 0;">3. การให้คำแนะนำ</h3>

                    <!-- Icon Card Grid -->
                    <div class="advice-grid" id="adviceGrid">
                        <?php
                        // Disease-specific advice items
                        $dmItems = [
                            'sweet' => [
                                'text' => 'ลดของหวาน น้ำอัดลม ชาไข่มุก และเครื่องดื่มรสหวานทุกชนิด',
                                'label' => 'ลดของหวาน<br>น้ำอัดลม',
                                'icon' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="24" cy="28" r="13" fill="#fbbf24" opacity=".25"/>
                                    <path d="M18 20c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round"/>
                                    <rect x="17" y="20" width="14" height="16" rx="4" fill="#fbbf24"/>
                                    <path d="M20 28h8M20 32h5" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="30" y1="12" x2="36" y2="8" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                    <line x1="30" y1="12" x2="36" y2="16" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                    <line x1="30" y1="12" x2="24" y2="12" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                </svg>'
                            ],
                            'fruit' => [
                                'text' => 'หลีกเลี่ยงผลไม้รสหวานจัด เช่น ทุเรียน มะม่วงสุก เลือกทานผักใบเขียวและผลไม้ที่หวานน้อยแทน',
                                'label' => 'เลี่ยงผลไม้<br>หวานจัด',
                                'icon' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <ellipse cx="24" cy="32" rx="11" ry="9" fill="#fde68a"/>
                                    <path d="M24 23c0 0-4-8 4-13" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round"/>
                                    <ellipse cx="24" cy="32" rx="11" ry="9" fill="#fbbf24" opacity=".7"/>
                                    <path d="M16 30c2-3 5-4 8-3" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="33" y1="14" x2="39" y2="10" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                    <line x1="33" y1="14" x2="39" y2="18" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                    <line x1="33" y1="14" x2="27" y2="14" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                </svg>'
                            ]
                        ];

                        $htItems = [
                            'salt' => [
                                'text' => 'ลดอาหารเค็มจัด งดเติมน้ำปลา/ซอสเพิ่ม หลีกเลี่ยงปลาร้า บะหมี่กึ่งสำเร็จรูป และอาหารแปรรูป',
                                'label' => 'ลดเค็ม<br>งดซอส/ปลาร้า',
                                'icon' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="16" y="18" width="16" height="20" rx="4" fill="#93c5fd"/>
                                    <rect x="19" y="14" width="10" height="6" rx="2" fill="#60a5fa"/>
                                    <circle cx="24" cy="23" r="2" fill="#fff"/>
                                    <circle cx="24" cy="29" r="2" fill="#fff"/>
                                    <circle cx="24" cy="35" r="2" fill="#fff"/>
                                    <line x1="33" y1="12" x2="39" y2="8" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                    <line x1="33" y1="12" x2="39" y2="16" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                    <line x1="33" y1="12" x2="27" y2="12" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                </svg>'
                            ],
                            'relax' => [
                                'text' => 'ผ่อนคลายความเครียด พักผ่อนให้เพียงพออย่างน้อย 7-8 ชั่วโมง/คืน',
                                'label' => 'ผ่อนคลาย<br>พักผ่อนให้พอ',
                                'icon' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 30c0-7.732 5.373-14 12-14s12 6.268 12 14" stroke="#a78bfa" stroke-width="2.5" stroke-linecap="round"/>
                                    <rect x="10" y="30" width="28" height="5" rx="2.5" fill="#a78bfa" opacity=".4"/>
                                    <path d="M19 22c1-2 3-3 5-3" stroke="#7c3aed" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M36 10c-2 4-6 5-9 3 3 0 7-1 9-3z" fill="#fbbf24"/>
                                    <circle cx="40" cy="14" r="2" fill="#fbbf24" opacity=".7"/>
                                </svg>'
                            ]
                        ];

                        $generalItems = [
                            'exercise' => [
                                'text' => 'ออกกำลังกายสม่ำเสมออย่างน้อย 30 นาที/วัน เช่น เดินเร็ว ปั่นจักรยาน ว่ายน้ำ',
                                'label' => 'ออกกำลังกาย<br>30 นาที/วัน',
                                'icon' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="30" cy="11" r="4" fill="#34d399" opacity=".6"/>
                                    <path d="M28 16l-4 8 6 4-3 8" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M24 24l-5 2" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                                    <ellipse cx="20" cy="36" rx="8" ry="4" fill="#6ee7b7" opacity=".25"/>
                                    <path d="M10 38h28" stroke="#a7f3d0" stroke-width="2" stroke-linecap="round"/>
                                </svg>'
                            ],
                            'smoke' => [
                                'text' => 'งดสูบบุหรี่และงดดื่มเครื่องดื่มแอลกอฮอล์ทุกชนิด',
                                'label' => 'งดบุหรี่<br>& แอลกอฮอล์',
                                'icon' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="10" y="26" width="20" height="6" rx="3" fill="#fca5a5"/>
                                    <rect x="30" y="26" width="8" height="6" rx="2" fill="#ef4444" opacity=".6"/>
                                    <path d="M32 26c0-4 4-4 4-8" stroke="#9ca3af" stroke-width="2" stroke-linecap="round"/>
                                    <circle cx="24" cy="24" r="16" stroke="#ef4444" stroke-width="3"/>
                                    <line x1="13" y1="13" x2="35" y2="35" stroke="#ef4444" stroke-width="3" stroke-linecap="round"/>
                                </svg>'
                            ],
                            'water' => [
                                'text' => 'ดื่มน้ำเปล่าให้เพียงพออย่างน้อย 6-8 แก้ว/วัน และหลีกเลี่ยงเครื่องดื่มที่มีน้ำตาลสูง',
                                'label' => 'ดื่มน้ำเปล่า<br>6-8 แก้ว/วัน',
                                'icon' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M24 10 C18 18 14 22 14 28 a10 10 0 0 0 20 0 C34 22 30 18 24 10z" fill="#60a5fa" opacity=".5"/>
                                    <path d="M24 10 C18 18 14 22 14 28 a10 10 0 0 0 20 0 C34 22 30 18 24 10z" stroke="#3b82f6" stroke-width="2"/>
                                    <path d="M18 30c1 3 4 5 7 5" stroke="#bfdbfe" stroke-width="2" stroke-linecap="round"/>
                                </svg>'
                            ],
                            'veg' => [
                                'text' => 'เพิ่มการทานผักใบเขียวและธัญพืชไม่ขัดสีในทุกมื้ออาหาร',
                                'label' => 'เพิ่มผักใบเขียว<br>ธัญพืช',
                                'icon' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M24 36 C24 26 14 20 10 12 C16 14 22 20 24 26 C26 20 32 14 38 12 C34 20 24 26 24 36z" fill="#4ade80" opacity=".7"/>
                                    <path d="M24 36 C24 26 14 20 10 12 C16 14 22 20 24 26" stroke="#16a34a" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M24 36v2" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round"/>
                                    <ellipse cx="24" cy="40" rx="8" ry="3" fill="#bbf7d0" opacity=".5"/>
                                </svg>'
                            ],
                            'doctor' => [
                                'text' => 'ไปพบแพทย์/เจ้าหน้าที่สาธารณสุขตามนัดอย่างสม่ำเสมอ ไม่ขาดการนัด',
                                'label' => 'พบแพทย์<br>ตามนัดสม่ำเสมอ',
                                'icon' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="12" y="14" width="24" height="28" rx="5" fill="#e0f2fe" stroke="#38bdf8" stroke-width="2"/>
                                    <path d="M18 14v-4M30 14v-4" stroke="#0ea5e9" stroke-width="2.5" stroke-linecap="round"/>
                                    <rect x="16" y="20" width="16" height="2" rx="1" fill="#0ea5e9" opacity=".5"/>
                                    <path d="M22 30h4M24 28v4" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                    <rect x="16" y="26" width="16" height="12" rx="3" fill="#fff" opacity=".6"/>
                                </svg>'
                            ],
                            'oil' => [
                                'text' => 'หลีกเลี่ยงอาหารทอด อาหารมัน แกงกะทิ เพื่อควบคุมระดับไขมันในเลือดและน้ำหนักตัว',
                                'label' => 'เลี่ยงของมัน<br>ของทอด',
                                'icon' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="24" cy="24" r="16" stroke="#ef4444" stroke-width="3"/>
                                    <line x1="13" y1="13" x2="35" y2="35" stroke="#ef4444" stroke-width="3" stroke-linecap="round"/>
                                    <path d="M24 17c-2 2.5-3 4-3 6a3 3 0 0 0 6 0c0-2-1-3.5-3-6z" fill="#fbbf24" opacity=".8"/>
                                </svg>'
                            ],
                            'medicine' => [
                                'text' => 'ทานยาตามแพทย์สั่งอย่างสม่ำเสมอต่อเนื่อง ไม่หยุดยาหรือปรับขนาดยาเอง',
                                'label' => 'ทานยาต่อเนื่อง<br>ตามแพทย์สั่ง',
                                'icon' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="16" y="16" width="16" height="8" rx="4" fill="#38bdf8" transform="rotate(-30 24 20)" opacity=".8"/>
                                    <rect x="20" y="22" width="12" height="10" rx="3" fill="#fbbf24" transform="rotate(15 26 27)"/>
                                    <circle cx="16" cy="32" r="3.5" fill="#f472b6" opacity=".9"/>
                                    <circle cx="34" cy="18" r="3" fill="#a78bfa" opacity=".9"/>
                                </svg>'
                            ]
                        ];

                        // Build final visible list
                        $toRender = [];
                        if ($isShell) {
                            $toRender = array_merge($dmItems, $htItems, $generalItems);
                        } else {
                            if ($riskType === 'BOTH') {
                                // BOTH: 2 DM + 2 HT + 5 General = 9 items
                                $toRender = array_merge($dmItems, $htItems);
                                $toRender['exercise'] = $generalItems['exercise'];
                                $toRender['veg'] = $generalItems['veg'];
                                $toRender['doctor'] = $generalItems['doctor'];
                                $toRender['oil'] = $generalItems['oil'];
                                $toRender['medicine'] = $generalItems['medicine'];
                            } elseif ($riskType === 'DM') {
                                // DM: 2 DM + 7 General = 9 items
                                $toRender = $dmItems;
                                $toRender = array_merge($toRender, $generalItems);
                            } else {
                                // HT: 2 HT + 7 General = 9 items
                                $toRender = $htItems;
                                $toRender = array_merge($toRender, $generalItems);
                            }
                        }

                        // Loop through visible items
                        foreach ($toRender as $key => $item):
                            ?>
                            <button type="button" class="advice-card" data-key="<?= $key ?>"
                                data-text="<?= htmlspecialchars($item['text']) ?>" onclick="toggleCard(this)">
                                <span class="advice-card-check">✅</span>
                                <div class="advice-icon-wrap">
                                    <?= $item['icon'] ?>
                                </div>
                                <span class="advice-card-label"><?= $item['label'] ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <!-- /advice-grid -->

                    <!-- Auto-compiled summary -->
                    <label class="form-label" style="margin-top: 4px;">คำแนะนำที่ให้ในรอบนี้ <span id="adviceCountBadge"
                            style="display:none; background:var(--color-green); color:#fff; font-size:11px; padding:1px 8px; border-radius:50px; margin-left:6px;">0
                            รายการ</span></label>
                    <div class="advice-summary-box" id="adviceSummaryDisplay">
                        <span class="advice-summary-placeholder">ยังไม่ได้เลือกคำแนะนำ — แตะการ์ดด้านบนเพื่อเริ่ม</span>
                    </div>
                    <textarea name="advice_given" id="advice_given" style="display:none;" readonly required></textarea>

                </div>

                <?php
                $skipCount = $isShell ? 0 : intval($task['skip_count'] ?? 0);
                $disableSkip = $skipCount >= 3;
                ?>
                <div style="display: flex; gap: 14px; margin-bottom: 40px; flex-wrap: wrap;">
                    <button type="submit" class="btn-giant btn-giant-primary"
                        style="margin-bottom: 0; flex: 1; min-width: 140px;">บันทึกผล</button>
                    <button type="button" id="btn-skip-case" onclick="openSkipModal()" class="btn-giant"
                        style="margin-bottom: 0; flex: 1; min-width: 170px; background: var(--bg-card); color: #f59e0b; border: 2px solid #d97706; font-weight: bold; border-radius: 50px; <?= $disableSkip ? 'display: none;' : '' ?>">
                        ⚠️ ข้ามการคัดกรอง (ไม่พบตัว)
                    </button>
                </div>
                <div id="skip-disabled-msg"
                    style="display: <?= $disableSkip ? 'block' : 'none' ?>; margin-top: -30px; margin-bottom: 40px; text-align: center; color: #ef4444; font-size: 14px; font-weight: bold;">
                    * ข้ามเคสครบ 3 ครั้งแล้ว ต้องบันทึกผลติดตามจริงเท่านั้น
                </div>
            </form>

            <!-- Critical Value Alert Modal Overlay -->
            <div id="critical-alert-modal"
                style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 5000; align-items: center; justify-content: center; padding: 16px;">
                <div class="card-dark"
                    style="width: 90%; max-width: 480px; background: #0f172a; border: 2px solid var(--color-red); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); border-radius: 24px; padding: 24px; color: var(--text-primary); text-align: left; animation: fadeIn 0.3s ease;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div
                            style="display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: rgba(239, 68, 68, 0.2); color: var(--color-red); font-size: 24px; flex-shrink: 0;">
                            🚨
                        </div>
                        <div>
                            <h3 style="color: var(--color-red); margin: 0; font-size: 20px; font-weight: 800;">
                                ตรวจพบสัญญาณชีพสูงวิกฤต!</h3>
                            <p style="margin: 4px 0 0 0; color: var(--text-secondary); font-size: 13px;">(Critical Value
                                Alert)</p>
                        </div>
                    </div>

                    <div id="critical-alert-values"
                        style="background: rgba(239, 68, 68, 0.1); border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; border-left: 4px solid var(--color-red); font-size: 15px; font-weight: bold; color: white;">
                        <!-- Will be populated dynamically -->
                    </div>

                    <div style="margin-bottom: 24px;">
                        <h4
                            style="color: var(--text-primary); font-size: 15px; font-weight: bold; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                            📋 คำแนะนำการปฐมพยาบาลเบื้องต้น:
                        </h4>
                        <div id="critical-alert-advice"
                            style="font-size: 14px; line-height: 1.6; color: var(--text-secondary); display: flex; flex-direction: column; gap: 12px; max-height: 250px; overflow-y: auto; padding-right: 8px;">
                            <!-- Will be populated dynamically -->
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="button" onclick="closeCriticalModal()" class="btn-giant btn-giant-secondary"
                            style="flex: 1; height: 50px; font-size: 16px; margin-bottom: 0; border-radius: 12px; border: 1px solid var(--border-color); color: var(--text-primary); cursor: pointer; background: transparent;">
                            ✕ ปิดเพื่อแก้ไขค่า
                        </button>
                        <button type="button" id="btn-confirm-critical-save" class="btn-giant btn-giant-danger"
                            style="flex: 1; height: 50px; font-size: 16px; margin-bottom: 0; border-radius: 12px; background: var(--color-red); color: white; border: none; font-weight: bold; cursor: pointer;">
                            ✅ ยืนยันบันทึกข้อมูล
                        </button>
                    </div>
                </div>
            </div>

            <!-- Skip Case Modal Overlay -->
            <div id="skip-modal"
                style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 5000; align-items: center; justify-content: center; padding: 16px;">
                <div class="card-dark"
                    style="width: 90%; max-width: 440px; background: #0f172a; border: 2px solid #d97706; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); border-radius: 24px; padding: 24px; color: var(--text-primary); text-align: left; animation: fadeIn 0.3s ease;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div
                            style="display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: rgba(217, 119, 6, 0.2); color: #f59e0b; font-size: 24px; flex-shrink: 0;">
                            ⚠️
                        </div>
                        <div>
                            <h3 style="color: #f59e0b; margin: 0; font-size: 20px; font-weight: 800;">
                                ข้ามเคสติดตามชั่วคราว</h3>
                            <p style="margin: 4px 0 0 0; color: var(--text-secondary); font-size: 13px;">อสม. ได้รับ
                                +0.25 แต้มความพยายาม</p>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="form-label"
                            style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">ระบุสาเหตุที่ข้ามเคสนี้:</label>
                        <select id="skip_reason" class="form-input-text"
                            style="width: 100%; margin-bottom: 12px; background: var(--bg-darker); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; padding: 10px;"
                            onchange="toggleCustomReason(this.value)">
                            <option value="ไม่อยู่บ้าน">ไม่อยู่บ้าน / ติดธุระ</option>
                            <option value="ติดต่อไม่ได้">ไม่สามารถติดต่อได้</option>
                            <option value="ไม่สะดวกให้ข้อมูลในรอบนี้">ไม่สะดวกให้ข้อมูลในรอบนี้</option>
                            <option value="ย้ายที่อยู่ชั่วคราว">ย้ายที่อยู่ชั่วคราว</option>
                            <option value="other">ระบุสาเหตุอื่น ๆ...</option>
                        </select>
                        <input type="text" id="skip_reason_custom" class="form-input-text"
                            style="display: none; width: 100%; background: var(--bg-darker); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; padding: 10px;"
                            placeholder="ระบุเหตุผลอื่น ๆ">
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="button" onclick="closeSkipModal()" class="btn-giant btn-giant-secondary"
                            style="flex: 1; height: 50px; font-size: 16px; margin-bottom: 0; border-radius: 12px; border: 1px solid var(--border-color); color: var(--text-primary); cursor: pointer; background: transparent;">
                            ยกเลิก
                        </button>
                        <button type="button" onclick="submitSkipCase()" class="btn-giant"
                            style="flex: 1; height: 50px; font-size: 16px; margin-bottom: 0; border-radius: 12px; background: #d97706; color: white; border: none; font-weight: bold; cursor: pointer;">
                            ยืนยันข้ามเคส
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const isDM = <?= $isDM ? 'true' : 'false' ?>;
        const isHT = <?= $isHT ? 'true' : 'false' ?>;

        let isCriticalAcknowledged = false;

        document.addEventListener("DOMContentLoaded", function () {
            const fbsInput = document.getElementById('fbs');
            const sbpInput = document.getElementById('bp_sys');
            const dbpInput = document.getElementById('bp_dia');
            [fbsInput, sbpInput, dbpInput].forEach(el => {
                if (el) {
                    el.addEventListener('input', function () {
                        isCriticalAcknowledged = false;
                    });
                }
            });
        });

        // Waist unit toggle logic
        let waistUnit = 'inch';
        function switchWaistUnit(unit) {
            const input = document.getElementById('waist');
            const hint = document.getElementById('waistHint');
            const unitLabel = document.getElementById('waistUnit');
            const currentVal = parseFloat(input.value);

            if (unit === waistUnit) return;
            waistUnit = unit;

            document.getElementById('btnCm').classList.toggle('active', unit === 'cm');
            document.getElementById('btnInch').classList.toggle('active', unit === 'inch');

            if (unit === 'inch') {
                input.placeholder = 'นิ้ว';
                unitLabel.textContent = 'นิ้ว';
                if (!isNaN(currentVal) && currentVal > 0) {
                    const cmVal = currentVal; // current value is still cm before switch
                    const inchVal = (cmVal / 2.54).toFixed(1);
                    input.value = inchVal;
                    document.getElementById('waistCmHidden').value = cmVal.toFixed(1);
                    hint.textContent = '≈ ' + cmVal.toFixed(1) + ' ซม.';
                }
            } else {
                input.placeholder = 'ซม.';
                unitLabel.textContent = 'ซม.';
                if (!isNaN(currentVal) && currentVal > 0) {
                    const cmVal = (currentVal * 2.54).toFixed(1);
                    input.value = cmVal;
                    document.getElementById('waistCmHidden').value = cmVal;
                    hint.textContent = '≈ ' + currentVal.toFixed(1) + ' นิ้ว';
                }
            }
        }

        document.getElementById('waist').addEventListener('input', function () {
            const val = parseFloat(this.value);
            const hint = document.getElementById('waistHint');
            const hidden = document.getElementById('waistCmHidden');
            if (isNaN(val) || val <= 0) { hint.textContent = ''; return; }
            if (waistUnit === 'cm') {
                const inch = (val / 2.54).toFixed(1);
                hint.textContent = '≈ ' + inch + ' นิ้ว';
                hidden.value = val.toFixed(1);
            } else {
                const cm = (val * 2.54).toFixed(1);
                hint.textContent = '≈ ' + cm + ' ซม.';
                hidden.value = cm;
            }
        });

        function calculateRisk() {
            let riskLevel = 'ปกติ';
            let riskClass = 'normal';
            let msg = '🟢 ปกติ (ทำดีแล้ว รักษาระดับนี้ไว้)';

            let isHigh = false;
            let isRisk = false;

            if (isDM) {
                const fbs = parseInt(document.getElementById('fbs').value);
                if (fbs) {
                    if (fbs >= 126) isHigh = true;
                    else if (fbs >= 100) isRisk = true;
                }
            }

            if (isHT) {
                const sys = parseInt(document.getElementById('bp_sys').value);
                const dia = parseInt(document.getElementById('bp_dia').value);
                if (sys && dia) {
                    if (sys >= 140 || dia >= 90) isHigh = true;
                    else if (sys >= 120 || dia >= 80) isRisk = true;
                }
            }

            if (isHigh) {
                riskLevel = 'เสี่ยงสูง';
                riskClass = 'high';
                msg = '🔴 เสี่ยงสูง (อันตราย! ต้องปรับพฤติกรรมด่วน)';
            } else if (isRisk) {
                riskLevel = 'เสี่ยง';
                riskClass = 'risk';
                msg = '🟡 เสี่ยง (เฝ้าระวัง ปรับเปลี่ยนพฤติกรรม)';
            }

            const riskDisplay = document.getElementById('risk_display');
            document.getElementById('health_risk_level').value = riskLevel;

            // Check if all required fields are filled
            let allFilled = true;
            if (isDM && !document.getElementById('fbs').value) allFilled = false;
            if (isHT && (!document.getElementById('bp_sys').value || !document.getElementById('bp_dia').value)) allFilled = false;

            if (allFilled) {
                riskDisplay.className = 'risk-eval ' + riskClass;
                riskDisplay.textContent = msg;
            } else {
                riskDisplay.className = 'risk-eval default';
                riskDisplay.textContent = 'กรุณากรอกข้อมูลให้ครบเพื่อประเมินผล';
                document.getElementById('health_risk_level').value = '';
            }
        }

        function updateLocalDpacTask(followupId) {
            const pending = JSON.parse(localStorage.getItem('vhv_dpac_tasks') || '[]');
            const completed = JSON.parse(localStorage.getItem('vhv_completed_dpac_tasks') || '[]');

            const idx = pending.findIndex(t => String(t.followup_id) === String(followupId));
            if (idx !== -1) {
                const task = pending[idx];
                task.status = 'completed';
                task.completed_at = new Date().toISOString().replace('T', ' ').split('.')[0];

                // Remove from pending
                pending.splice(idx, 1);
                // Push to completed
                completed.push(task);

                localStorage.setItem('vhv_dpac_tasks', JSON.stringify(pending));
                localStorage.setItem('vhv_completed_dpac_tasks', JSON.stringify(completed));
            }
        }

        document.getElementById('dpacForm').onsubmit = function (e) {
            const risk = document.getElementById('health_risk_level').value;
            if (!risk) {
                e.preventDefault();
                alert('กรุณากรอกข้อมูลเพื่อประเมินความเสี่ยงให้ครบถ้วน');
                return;
            }

            if (!checkCriticalValues()) {
                e.preventDefault();
                return;
            }

            if (!navigator.onLine) {
                e.preventDefault();

                // Serialize form data for offline sync queue
                const weight = document.getElementById('weight').value;
                const height = document.getElementById('height').value;
                const waist = document.getElementById('waistCmHidden').value;
                const fbs = document.getElementById('fbs') ? document.getElementById('fbs').value : null;
                const bp_sys = document.getElementById('bp_sys') ? document.getElementById('bp_sys').value : null;
                const bp_dia = document.getElementById('bp_dia') ? document.getElementById('bp_dia').value : null;
                const healthRisk = document.getElementById('health_risk_level').value;
                const advice = document.getElementById('advice_given').value;

                // Get URL parameter for followup ID
                const urlParams = new URLSearchParams(window.location.search);
                const fidVal = urlParams.get('fid');

                // Get resident name from title or DOM
                const residentName = document.getElementById('dpac-name').innerText;

                const data = {
                    'action': 'save_dpac',
                    'followup_id': fidVal,
                    'weight': weight,
                    'height': height,
                    'waist': waist,
                    'fbs': fbs,
                    'bp_sys': bp_sys,
                    'bp_dia': bp_dia,
                    'health_risk_level': healthRisk,
                    'advice_given': advice,
                    '_timestamp': Date.now(),
                    '_type': 'dpac',
                    '_residentName': residentName
                };

                const queue = JSON.parse(localStorage.getItem('offline_submissions') || '[]');
                queue.push(data);
                localStorage.setItem('offline_submissions', JSON.stringify(queue));

                // Update local task cache
                updateLocalDpacTask(fidVal);

                showToast("บันทึกผลการติดตาม DPAC ในเครื่องเรียบร้อยแล้ว (โหมดออฟไลน์)", "warning");
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);
            }
        };

        // Shell mode and offline parameters check
        document.addEventListener('DOMContentLoaded', () => {
            const isShell = <?= $isShell ? 'true' : 'false' ?>;

            if (isShell || !navigator.onLine) {
                const urlParams = new URLSearchParams(window.location.search);
                const fidVal = urlParams.get('fid');

                if (!fidVal) {
                    alert("ไม่พบรหัสติดตาม");
                    window.location.href = 'index.php';
                    return;
                }

                const dpacTasks = JSON.parse(localStorage.getItem('vhv_dpac_tasks') || '[]');
                const task = dpacTasks.find(t => String(t.followup_id) === String(fidVal));

                if (!task) {
                    alert("ไม่พบงาน หรือถูกดำเนินการไปแล้ว");
                    window.location.href = 'index.php';
                    return;
                }

                // Update UI details
                document.getElementById('dpac-watermark').innerText = task.round_number;
                document.getElementById('dpac-round-badge').innerText = `รอบที่ ${task.round_number}`;
                document.getElementById('dpac-round-title').innerText = `รอบติดตามที่ ${task.round_number}`;
                document.getElementById('dpac-name').innerText = `${task.first_name} ${task.last_name}`;
                document.getElementById('dpac-house-moo').innerText = `${task.house_no} หมู่ ${task.moo}`;
                document.getElementById('dpac-risk-type').innerText = task.risk_type;

                // Handle skip count UI dynamically
                const skipCount = parseInt(task.skip_count || 0);
                const skipBadge = document.getElementById('dpac-skip-badge');
                if (skipCount > 0) {
                    document.getElementById('dpac-skip-count').innerText = skipCount;
                    skipBadge.style.display = 'inline-flex';
                } else {
                    skipBadge.style.display = 'none';
                }

                const btnSkip = document.getElementById('btn-skip-case');
                const skipMsg = document.getElementById('skip-disabled-msg');
                if (skipCount >= 3) {
                    if (btnSkip) btnSkip.style.display = 'none';
                    if (skipMsg) skipMsg.style.display = 'block';
                } else {
                    if (btnSkip) btnSkip.style.display = 'block';
                    if (skipMsg) skipMsg.style.display = 'none';
                }

                // Set risk type variables
                const riskType = task.risk_type;
                window.isDM = ['DM', 'BOTH'].includes(riskType);
                window.isHT = ['HT', 'BOTH'].includes(riskType);

                // Show/hide fields
                document.getElementById('section-dm-fields').style.display = window.isDM ? 'block' : 'none';
                if (window.isDM) document.getElementById('fbs').required = true;

                document.getElementById('section-ht-fields').style.display = window.isHT ? 'block' : 'none';
                if (window.isHT) {
                    document.getElementById('bp_sys').required = true;
                    document.getElementById('bp_dia').required = true;
                }

                // Filter advice cards in UI
                const bothKeys = ['sweet', 'fruit', 'salt', 'relax', 'exercise', 'veg', 'doctor', 'oil', 'medicine'];
                const dmSubsetKeys = ['sweet', 'fruit', 'exercise', 'smoke', 'water', 'veg', 'doctor', 'oil', 'medicine'];
                const htSubsetKeys = ['salt', 'relax', 'exercise', 'smoke', 'water', 'veg', 'doctor', 'oil', 'medicine'];

                document.querySelectorAll('.advice-card').forEach(card => {
                    const key = card.getAttribute('data-key');
                    let show = false;

                    if (riskType === 'BOTH') {
                        show = bothKeys.includes(key);
                    } else if (riskType === 'DM') {
                        show = dmSubsetKeys.includes(key);
                    } else if (riskType === 'HT') {
                        show = htSubsetKeys.includes(key);
                    }

                    card.style.display = show ? 'flex' : 'none';
                });
            }
        });

        // ── Advice card toggle ────────────────────────────────────
        function toggleCard(card) {
            card.classList.toggle('selected');
            // Animate
            card.style.transform = 'scale(0.93)';
            setTimeout(() => { card.style.transform = ''; }, 150);
            rebuildAdvice();
        }

        function rebuildAdvice() {
            const cards = document.querySelectorAll('.advice-card.selected');
            const hidden = document.getElementById('advice_given');
            const display = document.getElementById('adviceSummaryDisplay');
            const badge = document.getElementById('adviceCountBadge');

            if (cards.length === 0) {
                display.innerHTML = '<span class="advice-summary-placeholder">ยังไม่ได้เลือกคำแนะนำ — แตะการ์ดด้านบนเพื่อเริ่ม</span>';
                hidden.value = '';
                badge.style.display = 'none';
                return;
            }

            const sentences = [];
            cards.forEach(c => sentences.push(c.dataset.text));

            // Full sentence text
            const fullText = sentences.join(' | ');
            hidden.value = fullText;

            // Display as bullet list
            const items = sentences.map((s, i) => `<div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:${i < sentences.length - 1 ? '8' : '0'}px;"><span style="color:var(--color-green);font-size:14px;margin-top:1px;flex-shrink:0;">✅</span><span>${s}</span></div>`).join('');
            display.innerHTML = `<div class="advice-summary-count">${sentences.length} คำแนะนำที่เลือก:</div>` + items;

            badge.textContent = sentences.length + ' รายการ';
            badge.style.display = 'inline';
        }

        function showCriticalModal(sbp, dbp, dtx, hasCriticalBp, hasCriticalDtx) {
            const valuesDiv = document.getElementById('critical-alert-values');
            const adviceDiv = document.getElementById('critical-alert-advice');

            let valHtml = '';
            let adviceHtml = '';

            if (hasCriticalBp) {
                valHtml += `❤️ ความดันโลหิตสูงวิกฤต: ${sbp}/${dbp} mmHg<br>`;
                adviceHtml += `
                    <div>
                        <strong style="color: white; display: block; margin-bottom: 4px;">🩸 ภาวะความดันโลหิตสูงวิกฤต (Hypertensive Crisis):</strong>
                        1. <strong>จัดท่าทาง:</strong> ให้นั่งพักในท่าที่สบาย เอนหลังได้ ในที่สงบ อากาศถ่ายเทสะดวก พัก 15 นาที แล้วค่อยวัดซ้ำ<br>
                        2. <strong>ห้ามออกกำลังกาย:</strong> งดการทำกิจกรรมเคลื่อนไไหลร่างกายรุนแรง ห้ามดื่มน้ำเย็นจัด ชา กาแฟ หรือสูบบุหรี่<br>
                        3. <strong>สังเกตอาการอันตราย:</strong> หากมีอาการปวดศีรษะรุนแรง ตาพร่ามัว เจ็บแน่นหน้าอก หายใจหอบเหนื่อย ปากเบี้ยว หน้าเบี้ยว หรือแขนขาอ่อนแรง <strong>ให้รีบแจ้งเจ้าหน้าที่ รพ.สต. หรือโทรสายด่วน 1669 ส่งโรงพยาบาลทันที!</strong>
                    </div>
                `;
            }

            if (hasCriticalDtx) {
                valHtml += `🍭 ระดับน้ำตาลในเลือดสูงวิกฤต: ${dtx} mg/dL<br>`;
                adviceHtml += `
                    <div>
                        <strong style="color: white; display: block; margin-bottom: 4px;">🍬 ภาวะระดับน้ำตาลในเลือดสูงวิกฤต (Severe Hyperglycemia):</strong>
                        1. <strong>ดื่มน้ำสะอาด:</strong> ให้ดื่มน้ำเปล่าปริมาณมากๆ เพื่อช่วยขับน้ำตาลส่วนเกินออกจากร่างกายผ่านทางปัสสาวะ (หลีกเลี่ยงน้ำหวานหรือแอลกอฮอล์)<br>
                        2. <strong>สังเกตอาการขาดน้ำ/คีโตนคั่ง:</strong> เช่น กระหายน้ำรุนแรง ปัสสาวะบ่อย ซึมลง สับสน มึนงง อ่อนเพลียมาก คลื่นไส้ อาเจียน หายใจหอบลึก หรือลมหายใจมีกลิ่นคล้ายผลไม้<br>
                        3. <strong>ส่งแพทย์ด่วน:</strong> หากมีอาการซึมลง สับสน หรืออาเจียน <strong>ให้รีบนำส่งสถานพยาบาลหรือโทร 1669 ทันที!</strong>
                    </div>
                `;
            }

            valuesDiv.innerHTML = valHtml;
            adviceDiv.innerHTML = adviceHtml;

            document.getElementById('critical-alert-modal').style.display = 'flex';
        }

        function closeCriticalModal() {
            document.getElementById('critical-alert-modal').style.display = 'none';
        }

        function checkCriticalValues() {
            if (isCriticalAcknowledged) {
                return true;
            }

            const fbsInput = document.getElementById('fbs');
            const sbpInput = document.getElementById('bp_sys');
            const dbpInput = document.getElementById('bp_dia');

            let sbp = sbpInput ? parseInt(sbpInput.value) || 0 : 0;
            let dbp = dbpInput ? parseInt(dbpInput.value) || 0 : 0;
            let dtx = fbsInput ? parseInt(fbsInput.value) || 0 : 0;

            let hasCriticalBp = sbp >= 180 || dbp >= 110;
            let hasCriticalDtx = dtx >= 300;

            if (hasCriticalBp || hasCriticalDtx) {
                showCriticalModal(sbp, dbp, dtx, hasCriticalBp, hasCriticalDtx);
                return false;
            }

            return true;
        }

        document.getElementById('btn-confirm-critical-save').onclick = function () {
            isCriticalAcknowledged = true;
            closeCriticalModal();
            const btn = document.querySelector('#dpacForm button[type="submit"]') || document.querySelector('#dpacForm input[type="submit"]');
            if (btn) {
                btn.click();
            } else {
                document.getElementById('dpacForm').submit();
            }
        };

        // Skip Case Modal Functions
        function openSkipModal() {
            document.getElementById('skip-modal').style.display = 'flex';
        }

        function closeSkipModal() {
            document.getElementById('skip-modal').style.display = 'none';
        }

        function toggleCustomReason(val) {
            const customInput = document.getElementById('skip_reason_custom');
            if (val === 'other') {
                customInput.style.display = 'block';
                customInput.required = true;
                customInput.focus();
            } else {
                customInput.style.display = 'none';
                customInput.required = false;
            }
        }

        function submitSkipCase() {
            const selectVal = document.getElementById('skip_reason').value;
            let reason = selectVal;
            if (selectVal === 'other') {
                reason = document.getElementById('skip_reason_custom').value.trim();
                if (!reason) {
                    alert('กรุณาระบุเหตุผลในการข้ามเคส');
                    return;
                }
            }

            const urlParams = new URLSearchParams(window.location.search);
            const fidVal = urlParams.get('fid');

            if (!navigator.onLine) {
                const residentName = document.getElementById('dpac-name').innerText;
                const data = {
                    'action': 'skip_case',
                    'followup_id': fidVal,
                    'skipped_reason': reason,
                    '_timestamp': Date.now(),
                    '_type': 'skip_dpac_case',
                    '_residentName': residentName
                };

                const queue = JSON.parse(localStorage.getItem('offline_submissions') || '[]');
                queue.push(data);
                localStorage.setItem('offline_submissions', JSON.stringify(queue));

                // Update local task cache
                const pending = JSON.parse(localStorage.getItem('vhv_dpac_tasks') || '[]');
                const idx = pending.findIndex(t => String(t.followup_id) === String(fidVal));
                if (idx !== -1) {
                    pending[idx].skip_count = (parseInt(pending[idx].skip_count) || 0) + 1;
                    localStorage.setItem('vhv_dpac_tasks', JSON.stringify(pending));
                }

                alert("บันทึกการข้ามเคสชั่วคราวในเครื่องเรียบร้อยแล้ว (โหมดออฟไลน์) อสม. ได้รับ +0.25 แต้ม");
                window.location.href = 'index.php';
                return;
            }

            fetch('../api/save_dpac.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'skip_case',
                    'followup_id': fidVal,
                    'skipped_reason': reason
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert("ข้ามเคสชั่วคราวเรียบร้อย! อสม. ได้รับ +0.25 แต้มสะสม");
                        window.location.href = 'index.php';
                    } else {
                        alert("เกิดข้อผิดพลาด: " + data.message);
                    }
                })
                .catch(err => {
                    alert("เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย");
                });
        }
    </script>
</body>

</html>