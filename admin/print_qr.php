<?php
// admin/print_qr.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

$hc_names = get_health_units();

$admin_title = get_admin_title();

$filter_hoscode = $_GET['hoscode'] ?? ($admin_hoscode ?: '');
$filter_moo = $_GET['moo'] ?? '';
$filter_target = $_GET['target'] ?? '';

// Build Query
$sql = "
    SELECT 
        cid,
        hid, 
        house_no, 
        moo, 
        first_name,
        last_name
    FROM target_population 
    WHERE (need_screen_dm = 1 OR need_screen_ht = 1)
";
$params = [];

if ($filter_hoscode) {
    $hoscodes = get_query_hoscodes($filter_hoscode);
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
    $sql .= " AND hoscode IN ($inPlaceholders)";
    $params = array_merge($params, $hoscodes);
}
if ($filter_moo) {
    $sql .= " AND moo = ?";
    $params[] = $filter_moo;
}
if ($filter_target === 'dm') {
    $sql .= " AND need_screen_dm = 1 AND need_screen_ht = 0";
} elseif ($filter_target === 'ht') {
    $sql .= " AND need_screen_dm = 0 AND need_screen_ht = 1";
} elseif ($filter_target === 'both') {
    $sql .= " AND need_screen_dm = 1 AND need_screen_ht = 1";
}

$sql .= " ORDER BY CAST(moo AS UNSIGNED) ASC, CAST(house_no AS UNSIGNED) ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$people = $stmt->fetchAll(PDO::FETCH_ASSOC);

function maskName($firstName, $lastName)
{
    if (strpos($firstName, '*') === false && strpos($lastName, '*') === false) {
        return $firstName . ' ' . $lastName;
    }
    $maskedFirst = mb_substr($firstName, 0, 3) . '***';
    $maskedLast = mb_substr($lastName, 0, 1) . '***';
    return $maskedFirst . ' ' . $maskedLast;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พิมพ์ QR Code ประจำตัวกลุ่มเป้าหมาย - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- QRCode.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .qr-card {
            background: white;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            color: black;
            page-break-inside: avoid;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .qr-card-header {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 6px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }

        .qr-code-img {
            margin: 10px auto;
            width: 120px;
            height: 120px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-code-img img {
            width: 100%;
            height: 100%;
        }

        .house-details {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .house-members {
            font-size: 13.5px;
            color: #334155;
            font-weight: 500;
        }

        .qr-card-id {
            font-size: 11px;
            margin-top: 4px;
            color: #64748b;
        }

        .qr-card-id-manual {
            font-size: 10px;
            margin-top: 4px;
            color: #d97706;
            font-weight: bold;
        }

        .qr-card-footer {
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
            margin-top: 10px;
            font-size: 11px;
            color: #0284c7;
            font-weight: bold;
            line-height: 1.4;
        }

        .qr-card-footer-sub {
            font-size: 10px;
            font-weight: normal;
            color: #475569;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
                color: black;
            }

            .print-container {
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }

            .no-print {
                display: none !important;
            }

            .qr-grid {
                display: grid !important;
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 4mm !important;
                margin-top: 0 !important;
            }

            .qr-card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
                padding: 8px 6px !important;
                border-radius: 6px !important;
                margin-bottom: 0 !important;
                height: 58mm !important;
                box-sizing: border-box !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            /* Enforce page break exactly after every 12th card (3x4 grid) */
            .qr-card:nth-child(12n) {
                page-break-after: always !important;
                break-after: page !important;
            }

            /* Prevent trailing blank page when the card count is a multiple of 12 */
            .qr-card:last-child {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }

            /* Explicitly hide any floating back-to-top buttons on print */
            #backToTopBtn, .back-to-top {
                display: none !important;
            }

            .qr-card-header {
                font-size: 10px !important;
                margin-bottom: 4px !important;
                padding-bottom: 2px !important;
            }

            .house-details {
                font-size: 12px !important;
                margin-bottom: 1px !important;
            }

            .house-members {
                font-size: 11px !important;
            }

            .qr-code-img {
                margin: 2px auto !important;
                width: 90px !important;
                height: 90px !important;
            }

            .qr-code-img img {
                width: 90px !important;
                height: 90px !important;
            }

            .qr-card-id, .qr-card-id-manual {
                font-size: 9px !important;
                margin-top: 1px !important;
            }

            .qr-card-footer {
                margin-top: 4px !important;
                padding-top: 4px !important;
                font-size: 9px !important;
            }

            .qr-card-footer-sub {
                font-size: 8px !important;
            }

            @page {
                margin: 0.8cm;
                size: A4;
            }
        }
    </style>
</head>

<body class="admin-body">

    <?php include 'navbar.php'; ?>

    <div class="print-container" style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="margin-bottom: 4px;" class="no-print">พิมพ์ QR Code รายบุคคล (Individual QR)</h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 15px;" class="no-print">
            ผู้รับผิดชอบ: <strong style="color: var(--color-accent);"><?= htmlspecialchars($admin_title) ?></strong>
        </p>

        <!-- Filters (Hidden on Print) -->
        <div class="card-dark no-print" style="margin-bottom: 30px;">
            <h3
                style="color: var(--color-accent); margin-top: 0; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px;">
                🔍 ค้นหาบ้านเพื่อพิมพ์ QR Code
            </h3>

            <form method="GET" action="print_qr.php">
                <div class="form-grid">
                    <!-- Hospital Area selection -->
                    <div class="form-group">
                        <label>หน่วยบริการ / รพ.สต.</label>
                        <?php if ($admin_hoscode !== null): ?>
                            <input type="text" class="form-select"
                                value="<?= htmlspecialchars($hc_names[$admin_hoscode]) ?>" readonly
                                style="background-color: rgba(0, 0, 0, 0.1); cursor: not-allowed; font-weight: normal; color: var(--text-muted);">
                            <input type="hidden" name="hoscode" id="hoscode"
                                value="<?= htmlspecialchars($admin_hoscode) ?>">
                        <?php else: ?>
                            <select name="hoscode" id="hoscode" class="form-select" onchange="onHoscodeChange()" required>
                                <option value="">-- เลือก รพ.สต. --</option>
                                <?php foreach ($hc_names as $code => $name): ?>
                                    <option value="<?= $code ?>" <?= ($filter_hoscode == $code) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- Village selection -->
                    <div class="form-group">
                        <label>หมู่ที่</label>
                        <select name="moo" id="moo" class="form-select">
                            <option value="">-- ทุกหมู่บ้าน --</option>
                            <?php for ($i = 1; $i <= 15; $i++): ?>
                                <option value="<?= $i ?>" <?= $filter_moo == $i ? 'selected' : '' ?>>หมู่ที่ <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Target Group selection -->
                    <div class="form-group">
                        <label>กลุ่มเป้าหมาย</label>
                        <select name="target" id="target" class="form-select">
                            <option value="">-- ทั้งหมด --</option>
                            <option value="dm" <?= $filter_target === 'dm' ? 'selected' : '' ?>>เสี่ยงเบาหวาน (DM)</option>
                            <option value="ht" <?= $filter_target === 'ht' ? 'selected' : '' ?>>เสี่ยงความดัน (HT)</option>
                            <option value="both" <?= $filter_target === 'both' ? 'selected' : '' ?>>เสี่ยงเบาหวานและความดัน
                                (DM & HT)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn-giant"
                            style="width: 100%; height: 42px; margin: 0; font-size: 15px;">ค้นหา</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($filter_hoscode): ?>
            <!-- Print Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;" class="no-print">ผลการค้นหา: พบ <?= count($people) ?> รายชื่อเป้าหมาย</h3>
                <button onclick="window.print()" class="btn-giant btn-giant-secondary no-print"
                    style="margin: 0; padding: 10px 20px;">🖨️ พิมพ์ QR Code</button>
            </div>

            <!-- Print Grid -->
            <div class="qr-grid">
                <?php if (count($people) > 0): ?>
                    <?php foreach ($people as $person): ?>
                        <div class="qr-card">
                            <!-- System Name & District Header -->
                            <div class="qr-card-header">
                                <span>🌞 NCDs Prevention</span>
                                <span style="color: #475569;">อำเภอตาลสุม</span>
                            </div>

                            <div class="house-details">บ้านเลขที่ <?= htmlspecialchars($person['house_no']) ?> หมู่ <?= htmlspecialchars($person['moo']) ?></div>
                            <div class="house-members">
                                <?= htmlspecialchars(maskName($person['first_name'], $person['last_name'])) ?>
                            </div>

                            <div class="qr-code-img" id="qr-<?= htmlspecialchars($person['cid']) ?>"></div>

                            <?php if (!empty($person['hid']) && $person['hid'] !== '000000000000000'): ?>
                                <div class="qr-card-id">HID: <?= htmlspecialchars($person['hid']) ?></div>
                            <?php else: ?>
                                <div class="qr-card-id-manual">CID: <?= htmlspecialchars(substr($person['cid'], 0, 4) . '***' . substr($person['cid'], -4)) ?> (Manual Import)</div>
                            <?php endif; ?>

                            <!-- Message of Care -->
                            <div class="qr-card-footer">
                                "ด้วยความห่วงใยในสุขภาพของท่าน"<br>
                                <span class="qr-card-footer-sub">หลีกเลี่ยง หวาน มัน เค็ม และตรวจเช็คสุขภาพสม่ำเสมอ</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-muted); grid-column: 1 / -1; text-align: center; padding: 50px 0;">
                        ไม่พบรายชื่อประชากรในเงื่อนไขที่เลือก</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card-dark no-print" style="text-align: center; padding: 50px 0; color: var(--text-muted);">
                กรุณาเลือกหน่วยบริการเพื่อแสดง QR Code
            </div>
        <?php endif; ?>
    </div>

    <!-- Script to Handle Relations and QR Generation -->
    <script>
        const relations = <?php
        $relations = [];
        try {
            $stmt = $pdo->query("SELECT vhid_code, sub_district_code, moo, village_name, hoscode FROM villages ORDER BY hoscode, moo");
            $dbVillages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($dbVillages as $v) {
                $hc = $v['hoscode'];
                if (!$hc) continue;
                if (!isset($relations[$hc])) {
                    $relations[$hc] = [
                        'tambon' => $v['sub_district_code'],
                        'villages' => []
                    ];
                }
                $relations[$hc]['villages'][] = [
                    'moo' => (int)$v['moo'],
                    'name' => $v['village_name']
                ];
            }
        } catch (\Exception $e) {
            foreach ($hoscode_villages as $hc => $info) {
                $vList = [];
                foreach ($info['villages'] as $moo => $name) {
                    $vList[] = ['moo' => (int) $moo, 'name' => $name];
                }
                $relations[$hc] = [
                    'tambon' => $info['tambon'],
                    'villages' => $vList
                ];
            }
        }
        echo json_encode($relations, JSON_UNESCAPED_UNICODE);
        ?>;

        function onHoscodeChange() {
            const hSelect = document.getElementById('hoscode');
            const mSelect = document.getElementById('moo');
            if (!mSelect) return;

            const hCode = hSelect ? hSelect.value : "<?= $admin_hoscode ?: '' ?>";
            const savedMoo = mSelect.value;

            if (hCode && relations[hCode]) {
                populateMooSelect(relations[hCode].villages, savedMoo);
            } else {
                populateMooSelect([], savedMoo, true);
            }
        }

        function populateMooSelect(villages, selectedMoo, isGeneric = false) {
            const mSelect = document.getElementById('moo');
            if (!mSelect) return;
            mSelect.innerHTML = '<option value="">-- ทุกหมู่บ้าน --</option>';

            if (isGeneric || villages.length === 0) {
                for (let i = 1; i <= 15; i++) {
                    mSelect.innerHTML += `<option value="${i}" ${selectedMoo == i ? 'selected' : ''}>หมู่ที่ ${i}</option>`;
                }
            } else {
                villages.forEach(v => {
                    mSelect.innerHTML += `<option value="${v.moo}" ${selectedMoo == v.moo ? 'selected' : ''}>หมู่ที่ ${v.moo} ${v.name}</option>`;
                });
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            // Setup Village filtering
            const hSelect = document.getElementById('hoscode');
            const mSelect = document.getElementById('moo');

            const initialHoscode = hSelect ? hSelect.value : "<?= $admin_hoscode ?: '' ?>";
            const initialMoo = "<?= $filter_moo ?>";

            if (initialHoscode) {
                onHoscodeChange();
                if (mSelect) mSelect.value = initialMoo;
            } else {
                populateMooSelect([], initialMoo, true);
            }

            // Generate QR Codes
            <?php if ($filter_hoscode && count($people) > 0): ?>
                <?php foreach ($people as $person): ?>
                    <?php
                    // Fallback to CID if JHCIS HID is empty or placeholder (000000000000000)
                    $qrText = (!empty($person['hid']) && $person['hid'] !== '000000000000000') ? $person['hid'] : $person['cid'];
                    ?>
                    new QRCode(document.getElementById("qr-<?= $person['cid'] ?>"), {
                        text: "<?= $qrText ?>",
                        width: 120,
                        height: 120,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.M
                    });
                <?php endforeach; ?>
            <?php endif; ?>
        });
    </script>
</body>

</html>