<?php
// vhv/dpac_form.php
session_start();

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$fid = $_GET['fid'] ?? '';

if (empty($fid)) {
    header("Location: index.php");
    exit();
}

$vhvId = $_SESSION['vhv_id'];

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
    <style>
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

        .btn-advice-chip {
            background-color: var(--bg-card);
            color: var(--text-primary);
            border: none;
            padding: 12px 16px;
            border-radius: var(--border-radius);
            text-align: left;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            transition: all var(--transition-speed);
            box-shadow: var(--neumorph-flat);
            margin-bottom: 8px;
        }

        .btn-advice-chip.selected {
            background-color: var(--bg-darker) !important;
            color: var(--color-green) !important;
            box-shadow: var(--neumorph-inset) !important;
        }
    </style>
</head>

<body class="vhv-accessibility">
    <div class="mobile-wrapper">
        <div class="header-nav">
            <button class="back-btn" onclick="window.location.href='index.php'">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15 19l-7-7 7-7"></path>
                </svg>
                กลับ
            </button>
            <h2 style="font-size: 18px;">ติดตามกลุ่มเสี่ยง DPAC</h2>
        </div>

        <div style="padding: 20px;">
            <div class="form-section" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white;">
                <h3 style="margin-top: 0; color: #38bdf8;">รอบติดตามที่ <?= $task['round_number'] ?></h3>
                <p style="margin: 5px 0;"><strong>ชื่อ-สกุล:</strong>
                    <?= htmlspecialchars($task['first_name'] . ' ' . $task['last_name']) ?></p>
                <p style="margin: 5px 0;"><strong>ที่อยู่:</strong> บ้านเลขที่
                    <?= htmlspecialchars($task['house_no']) ?> หมู่ <?= htmlspecialchars($task['moo']) ?>
                </p>
                <p style="margin: 5px 0; color: #fbbf24; font-weight: bold;">⚠️ กลุ่มเสี่ยง: <?= $task['risk_type'] ?>
                </p>
            </div>

            <?php if (isset($error)): ?>
                <div style="color: red; margin-bottom: 15px; font-weight: bold; text-align: center;"><?= $error ?></div>
            <?php endif; ?>

            <form method="post" id="dpacForm">
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
                        <label class="form-label">รอบเอว (ซม.)</label>
                        <input type="number" step="0.1" name="waist" id="waist" class="form-input-text" required>
                    </div>
                </div>

                <!-- ข้อมูลเฉพาะโรค -->
                <div class="form-section">
                    <h3 style="color: var(--color-accent); margin-top: 0;">2. ตรวจวัดค่าความเสี่ยง</h3>

                    <?php if ($isDM): ?>
                        <div style="margin-top: 15px;">
                            <label class="form-label">ระดับน้ำตาลในเลือด (FBS) (mg/dL)</label>
                            <input type="number" name="fbs" id="fbs" class="form-input-text" oninput="calculateRisk()"
                                required placeholder="ตัวอย่าง: 110">
                        </div>
                    <?php endif; ?>

                    <?php if ($isHT): ?>
                        <div class="row-grid" style="margin-top: 15px;">
                            <div>
                                <label class="form-label">ความดันตัวบน (SYS)</label>
                                <input type="number" name="bp_sys" id="bp_sys" class="form-input-text"
                                    oninput="calculateRisk()" required placeholder="ตัวอย่าง: 130">
                            </div>
                            <div>
                                <label class="form-label">ความดันตัวล่าง (DIA)</label>
                                <input type="number" name="bp_dia" id="bp_dia" class="form-input-text"
                                    oninput="calculateRisk()" required placeholder="ตัวอย่าง: 85">
                            </div>
                        </div>
                    <?php endif; ?>

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

                    <div class="advice-box">
                        <h4>💡 แนวทางการให้คำแนะนำ (อสม. อ่านให้ฟัง)</h4>
                        <ul>
                            <?php if ($isDM): ?>
                                <li>ลดการทานของหวาน น้ำอัดลม ชาไข่มุก</li>
                                <li>เน้นทานผักใบเขียว หลีกเลี่ยงผลไม้รสหวานจัด (เช่น ทุเรียน มะม่วงสุก)</li>
                                <li>ออกกำลังกายสม่ำเสมอ อย่างน้อย 30 นาที/วัน</li>
                            <?php endif; ?>
                            <?php if ($isHT): ?>
                                <li>ลดอาหารเค็ม จัด รสจัด งดการเติมน้ำปลา/ซอสเพิ่ม</li>
                                <li>หลีกเลี่ยงอาหารแปรรูป (ไส้กรอก แหนม บะหมี่กึ่งสำเร็จรูป)</li>
                                <li>งดสูบบุหรี่และงดเครื่องดื่มแอลกอฮอล์</li>
                                <li>ผ่อนคลายความเครียด พักผ่อนให้เพียงพอ</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <label class="form-label">สรุปคำแนะนำ</label>
                    <textarea name="advice_given" id="advice_given" class="form-input-text"
                        style="height: 80px; resize: none; background-color: var(--bg-darker); border: 2px solid var(--border-color); color: var(--text-primary); cursor: default;"
                        readonly required placeholder="กรุณาคลิกเลือกคำแนะนำด้านล่าง..."></textarea>

                    <div
                        style="display: flex; flex-direction: column; gap: 8px; margin-top: 10px; margin-bottom: 15px;">
                        <?php if ($isDM): ?>
                            <button type="button" class="btn-advice-chip"
                                onclick="toggleAdviceChip('ลดของหวาน/เครื่องดื่มน้ำอัดลม/ชาไข่มุก', this)">
                                <span class="chip-status">⚪</span> ลดของหวาน/เครื่องดื่มน้ำอัดลม/ชาไข่มุก
                            </button>
                            <button type="button" class="btn-advice-chip"
                                onclick="toggleAdviceChip('เลี่ยงผลไม้หวานจัด เช่น ทุเรียน/มะม่วงสุก', this)">
                                <span class="chip-status">⚪</span> เลี่ยงผลไม้หวานจัด เช่น ทุเรียน/มะม่วงสุก
                            </button>
                        <?php endif; ?>
                        <?php if ($isHT): ?>
                            <button type="button" class="btn-advice-chip"
                                onclick="toggleAdviceChip('เลี่ยงเค็ม/ปลาร้า/บะหมี่กึ่งสำเร็จรูป/ลดการเติมน้ำปลา', this)">
                                <span class="chip-status">⚪</span> เลี่ยงเค็ม/ปลาร้า/บะหมี่กึ่งสำเร็จรูป/ลดการเติมน้ำปลา
                            </button>
                            <button type="button" class="btn-advice-chip"
                                onclick="toggleAdviceChip('ผ่อนคลายความเครียด/พักผ่อนให้เพียงพอ', this)">
                                <span class="chip-status">⚪</span> ผ่อนคลายความเครียด/พักผ่อนให้เพียงพอ
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn-advice-chip"
                            onclick="toggleAdviceChip('ออกกำลังกายอย่างน้อย 30 นาที/วัน', this)">
                            <span class="chip-status">⚪</span> ออกกำลังกายอย่างน้อย 30 นาที/วัน
                        </button>
                        <button type="button" class="btn-advice-chip"
                            onclick="toggleAdviceChip('งดสูบบุหรี่และงดเครื่องดื่มแอลกอฮอล์', this)">
                            <span class="chip-status">⚪</span> งดสูบบุหรี่และงดเครื่องดื่มแอลกอฮอล์
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-giant btn-giant-primary" style="margin-bottom: 40px;">บันทึกผลการติดตาม
                    DPAC</button>
            </form>
        </div>
    </div>

    <script>
        const isDM = <?= $isDM ? 'true' : 'false' ?>;
        const isHT = <?= $isHT ? 'true' : 'false' ?>;

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

        document.getElementById('dpacForm').onsubmit = function (e) {
            const risk = document.getElementById('health_risk_level').value;
            if (!risk) {
                e.preventDefault();
                alert('กรุณากรอกข้อมูลเพื่อประเมินความเสี่ยงให้ครบถ้วน');
            }
        };

        function toggleAdviceChip(text, btn) {
            btn.classList.toggle('selected');
            const indicator = btn.querySelector('.chip-status');
            if (btn.classList.contains('selected')) {
                indicator.textContent = '✅';
            } else {
                indicator.textContent = '⚪';
            }

            const selected = [];
            document.querySelectorAll('.btn-advice-chip.selected').forEach(chip => {
                const chipText = chip.textContent.replace('✅', '').replace('⚪', '').trim();
                selected.push(chipText);
            });

            document.getElementById('advice_given').value = selected.join(', ');
        }
    </script>
</body>

</html>