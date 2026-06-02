<?php
// admin/analytics.php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;

$hc_names = [
    '10957' => 'โรงพยาบาลตาลสุม',
    '03751' => 'รพ.สต.ดอนพันชาด',
    '03752' => 'รพ.สต.บ้านสำโรง',
    '03753' => 'รพ.สต.บ้านจิกเทิง',
    '03754' => 'รพ.สต.บ้านหนองกุงใหญ่',
    '03755' => 'รพ.สต.นาคาย',
    '03756' => 'รพ.สต.คำหนามแท่ง',
    '03757' => 'รพ.สต.คำหว้า'
];

$admin_title = $admin_hoscode ? ($hc_names[$admin_hoscode] ?? 'รพ.สต.') : (($_SESSION['admin_username'] ?? '') === 'adminsso' ? 'ผู้รับผิดชอบระดับอำเภอ' : 'แอดมินหลัก (ทุก รพ.สต.)');

if ($admin_hoscode) {
    $hoscodes = [$admin_hoscode];
    $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
} else {
    $valid_hoscodes = array_keys($hc_names);
    $inPlaceholders = implode(',', array_fill(0, count($valid_hoscodes), '?'));
    $hoscodes = $valid_hoscodes;
}

// 1. Before-After Query for DPAC progress tracking
$beforeAfterStmt = $pdo->prepare("
    SELECT 
        e.enrollment_id,
        e.risk_type,
        p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.hoscode,
        f1.bp_sys AS sbp_before, f1.bp_dia AS dbp_before, f1.fbs AS fbs_before, f1.health_risk_level AS risk_before,
        fl.bp_sys AS sbp_after, fl.bp_dia AS dbp_after, fl.fbs AS fbs_after, fl.health_risk_level AS risk_after,
        fl.round_number AS latest_round
    FROM dpac_enrollments e
    JOIN target_population p ON e.cid = p.cid
    JOIN dpac_followups f1 ON e.enrollment_id = f1.enrollment_id AND f1.round_number = 1 AND f1.status = 'completed'
    JOIN dpac_followups fl ON e.enrollment_id = fl.enrollment_id AND fl.status = 'completed'
    JOIN (
        SELECT enrollment_id, MAX(round_number) as max_round
        FROM dpac_followups
        WHERE status = 'completed'
        GROUP BY enrollment_id
    ) max_f ON fl.enrollment_id = max_f.enrollment_id AND fl.round_number = max_f.max_round
    WHERE max_f.max_round > 1 AND p.hoscode IN ($inPlaceholders)
    ORDER BY fl.completed_at DESC
");
$beforeAfterStmt->execute($hoscodes);
$beforeAfterData = $beforeAfterStmt->fetchAll(PDO::FETCH_ASSOC);

// Summarize stats
$totalAnalyzed = count($beforeAfterData);
$improvedBpCount = 0;
$improvedFbsCount = 0;
$totalHtCases = 0;
$totalDmCases = 0;

$sbpBeforeSum = 0;
$sbpAfterSum = 0;
$dbpBeforeSum = 0;
$dbpAfterSum = 0;
$fbsBeforeSum = 0;
$fbsAfterSum = 0;

$highToHigh = 0;
$highToModerate = 0;
$highToNormal = 0;
$moderateToHigh = 0;
$moderateToModerate = 0;
$moderateToNormal = 0;
$normalToHigh = 0;
$normalToModerate = 0;
$normalToNormal = 0;

foreach ($beforeAfterData as $row) {
    $hasHt = in_array($row['risk_type'], ['HT', 'BOTH']);
    $hasDm = in_array($row['risk_type'], ['DM', 'BOTH']);

    if ($hasHt && !empty($row['sbp_before']) && !empty($row['sbp_after'])) {
        $totalHtCases++;
        $sbpBeforeSum += $row['sbp_before'];
        $sbpAfterSum += $row['sbp_after'];
        $dbpBeforeSum += $row['dbp_before'];
        $dbpAfterSum += $row['dbp_after'];
        
        if ($row['sbp_after'] < $row['sbp_before'] || ($row['sbp_after'] < 140 && $row['dbp_after'] < 90)) {
            $improvedBpCount++;
        }
    }

    if ($hasDm && !empty($row['fbs_before']) && !empty($row['fbs_after'])) {
        $totalDmCases++;
        $fbsBeforeSum += $row['fbs_before'];
        $fbsAfterSum += $row['fbs_after'];

        if ($row['fbs_after'] < $row['fbs_before'] || $row['fbs_after'] < 126) {
            $improvedFbsCount++;
        }
    }

    $before = $row['risk_before'];
    $after = $row['risk_after'];
    
    if ($before === 'เสี่ยงสูง') {
        if ($after === 'เสี่ยงสูง') $highToHigh++;
        elseif ($after === 'เสี่ยง') $highToModerate++;
        else $highToNormal++;
    } elseif ($before === 'เสี่ยง') {
        if ($after === 'เสี่ยงสูง') $moderateToHigh++;
        elseif ($after === 'เสี่ยง') $moderateToModerate++;
        else $moderateToNormal++;
    } else {
        if ($after === 'เสี่ยงสูง') $normalToHigh++;
        elseif ($after === 'เสี่ยง') $normalToModerate++;
        else $normalToNormal++;
    }
}

$avgSbpBefore = $totalHtCases > 0 ? round($sbpBeforeSum / $totalHtCases, 1) : 0;
$avgSbpAfter = $totalHtCases > 0 ? round($sbpAfterSum / $totalHtCases, 1) : 0;
$avgDbpBefore = $totalHtCases > 0 ? round($dbpBeforeSum / $totalHtCases, 1) : 0;
$avgDbpAfter = $totalHtCases > 0 ? round($dbpAfterSum / $totalHtCases, 1) : 0;

$avgFbsBefore = $totalDmCases > 0 ? round($fbsBeforeSum / $totalDmCases, 1) : 0;
$avgFbsAfter = $totalDmCases > 0 ? round($fbsAfterSum / $totalDmCases, 1) : 0;

$pctBpImprovement = $totalHtCases > 0 ? round(($improvedBpCount / $totalHtCases) * 100, 1) : 0;
$pctFbsImprovement = $totalDmCases > 0 ? round(($improvedFbsCount / $totalDmCases) * 100, 1) : 0;

// Risk evaluation matrix counts
$beforeHigh = $highToHigh + $highToModerate + $highToNormal;
$beforeModerate = $moderateToHigh + $moderateToModerate + $moderateToNormal;
$beforeNormal = $normalToHigh + $normalToModerate + $normalToNormal;

$afterHigh = $highToHigh + $moderateToHigh + $normalToHigh;
$afterModerate = $highToModerate + $moderateToModerate + $normalToModerate;
$afterNormal = $highToNormal + $moderateToNormal + $normalToNormal;

// 2. Risk Reduction Rate by Village (moo)
$villageImprovementStmt = $pdo->prepare("
    SELECT 
        p.hoscode, p.moo,
        COUNT(DISTINCT e.enrollment_id) as total_enrolled,
        SUM(CASE WHEN max_f.max_round > 1 THEN 1 ELSE 0 END) as completed_followups,
        SUM(CASE 
            WHEN max_f.max_round > 1 AND (
                (f1.health_risk_level = 'เสี่ยงสูง' AND fl.health_risk_level IN ('เสี่ยง', 'ปกติ')) OR
                (f1.health_risk_level = 'เสี่ยง' AND fl.health_risk_level = 'ปกติ') OR
                (f1.bp_sys > fl.bp_sys AND f1.bp_sys >= 140) OR
                (f1.fbs > fl.fbs AND f1.fbs >= 126)
            ) THEN 1 ELSE 0 
        END) as improved_count
    FROM dpac_enrollments e
    JOIN target_population p ON e.cid = p.cid
    LEFT JOIN dpac_followups f1 ON e.enrollment_id = f1.enrollment_id AND f1.round_number = 1 AND f1.status = 'completed'
    LEFT JOIN dpac_followups fl ON e.enrollment_id = fl.enrollment_id AND fl.status = 'completed'
    LEFT JOIN (
        SELECT enrollment_id, MAX(round_number) as max_round
        FROM dpac_followups
        WHERE status = 'completed'
        GROUP BY enrollment_id
    ) max_f ON fl.enrollment_id = max_f.enrollment_id AND fl.round_number = max_f.max_round
    WHERE p.hoscode IN ($inPlaceholders)
    GROUP BY p.hoscode, p.moo
    ORDER BY p.hoscode, p.moo
");
$villageImprovementStmt->execute($hoscodes);
$villageImprovementData = $villageImprovementStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Temporal Heatmap Data (Screens & DPAC followups)
$historyStmt = $pdo->prepare("
    SELECT 
        p.cid, p.latitude, p.longitude,
        combined.risk_level, combined.created_at
    FROM (
        SELECT a.target_cid,
               CASE 
                   WHEN (s.cv_risk_score >= 10 OR s.sys_bp1 >= 140 OR s.dia_bp1 >= 90 OR s.dtx_value >= 126) THEN 'HIGH'
                   WHEN ((s.sys_bp1 BETWEEN 120 AND 139) OR (s.dia_bp1 BETWEEN 80 AND 89) OR (s.dtx_value BETWEEN 100 AND 125)) THEN 'MODERATE'
                   ELSE 'NORMAL'
               END AS risk_level,
               s.created_at
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        WHERE a.assignment_status = 'completed'

        UNION ALL

        SELECT e.cid AS target_cid,
               CASE 
                   WHEN f.health_risk_level = 'เสี่ยงสูง' THEN 'HIGH'
                   WHEN f.health_risk_level = 'เสี่ยง' THEN 'MODERATE'
                   ELSE 'NORMAL'
               END AS risk_level,
               f.completed_at AS created_at
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        WHERE f.status = 'completed'
    ) AS combined
    JOIN target_population p ON combined.target_cid = p.cid
    WHERE p.latitude IS NOT NULL AND p.longitude IS NOT NULL AND p.hoscode IN ($inPlaceholders)
    ORDER BY combined.created_at ASC
");
$historyStmt->execute($hoscodes);
$historyRecords = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Unique targets with coordinates for the map
$mapTargetsStmt = $pdo->prepare("
    SELECT cid, first_name, last_name, house_no, moo, hoscode, latitude, longitude
    FROM target_population
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND hoscode IN ($inPlaceholders)
");
$mapTargetsStmt->execute($hoscodes);
$mapTargets = $mapTargetsStmt->fetchAll(PDO::FETCH_ASSOC);

// Map centroid calculation
$latSum = 0;
$lngSum = 0;
$coordCount = 0;
foreach ($mapTargets as $t) {
    if ($t['latitude'] && $t['longitude']) {
        $latSum += $t['latitude'];
        $lngSum += $t['longitude'];
        $coordCount++;
    }
}
$mapCenterLat = $coordCount > 0 ? $latSum / $coordCount : 15.4294;
$mapCenterLng = $coordCount > 0 ? $lngSum / $coordCount : 104.9922;
$mapInitialZoom = $coordCount > 0 ? 13 : 12;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบวิเคราะห์ข้อมูลเชิงลึก (Advanced Analytics) - NCDs Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Leaflet Map CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        #temporal-map {
            height: 450px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            background: var(--bg-darker);
            margin-bottom: 16px;
            z-index: 1;
        }
        .play-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-darker);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 12px 24px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .btn-control {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-control:hover {
            border-color: var(--color-accent);
            color: var(--color-accent);
            background: rgba(14, 165, 233, 0.05);
        }
        .btn-control.active-play {
            background: var(--color-accent);
            color: white;
            border-color: var(--color-accent);
            box-shadow: 0 0 10px rgba(14, 165, 233, 0.4);
        }
        .quarter-indicator {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .quarter-badge {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }
        .quarter-badge.active {
            background: rgba(34, 197, 94, 0.15);
            color: var(--color-green);
            border-color: var(--color-green);
        }
        .stats-badge-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stats-badge-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .stats-badge-card .num {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .stats-badge-card .label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: bold;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 30px;
        }
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .stats-badge-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body class="admin-body dashboard-page">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: rgba(14, 165, 233, 0.15); color: #0ea5e9;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12z" />
                </svg>
            </span>
            ระบบวิเคราะห์ข้อมูลเชิงลึก (Advanced Analytics)
        </h2>
        <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 15px;">
            หน่วยบริการผู้รับผิดชอบ: <strong style="color: var(--color-accent);"><?= htmlspecialchars($admin_title) ?></strong>
        </p>

        <!-- DPAC Intervention Outcome Summary Cards -->
        <h3 style="color: var(--color-accent); margin-bottom: 16px; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <span>🔄 ประสิทธิผลการปรับเปลี่ยนพฤติกรรมกลุ่มเสี่ยง (DPAC Outcomes)</span>
            <span style="font-size: 13px; font-weight: normal; color: var(--text-secondary);">(ประเมินเปรียบเทียบ Round 1 vs ล่าสุด ของผู้ติดตาม 2 รอบขึ้นไป รวม <?= number_format($totalAnalyzed) ?> ราย)</span>
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card-dark" style="border-left: 4px solid var(--color-accent);">
                <div style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-bottom: 8px;">ค่าความดันตัวบนเฉลี่ย (Systolic BP)</div>
                <div style="display: flex; align-items: baseline; gap: 8px;">
                    <span style="font-size: 26px; font-weight: 800; color: var(--text-primary);"><?= $avgSbpBefore ?></span>
                    <span style="color: var(--text-muted); font-size: 13px;">→</span>
                    <span style="font-size: 26px; font-weight: 800; color: var(--color-green);"><?= $avgSbpAfter ?></span>
                    <span style="font-size: 13px; color: var(--text-muted); margin-left: 4px;">mmHg</span>
                </div>
                <div style="font-size: 12px; margin-top: 8px; color: var(--color-green); font-weight: bold;">
                    📉 ลดลงเฉลี่ย <?= $avgSbpBefore > $avgSbpAfter ? round($avgSbpBefore - $avgSbpAfter, 1) : 0 ?> mmHg
                </div>
            </div>

            <div class="card-dark" style="border-left: 4px solid #a78bfa;">
                <div style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-bottom: 8px;">ค่าน้ำตาลในเลือดเฉลี่ย (FBS)</div>
                <div style="display: flex; align-items: baseline; gap: 8px;">
                    <span style="font-size: 26px; font-weight: 800; color: var(--text-primary);"><?= $avgFbsBefore ?></span>
                    <span style="color: var(--text-muted); font-size: 13px;">→</span>
                    <span style="font-size: 26px; font-weight: 800; color: var(--color-green);"><?= $avgFbsAfter ?></span>
                    <span style="font-size: 13px; color: var(--text-muted); margin-left: 4px;">mg/dL</span>
                </div>
                <div style="font-size: 12px; margin-top: 8px; color: var(--color-green); font-weight: bold;">
                    📉 ลดลงเฉลี่ย <?= $avgFbsBefore > $avgFbsAfter ? round($avgFbsBefore - $avgFbsAfter, 1) : 0 ?> mg/dL
                </div>
            </div>

            <div class="card-dark" style="border-left: 4px solid var(--color-green);">
                <div style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-bottom: 8px;">ควบคุมความดันสำเร็จ / ดีขึ้น</div>
                <div class="stat-val" style="color: var(--color-green);">
                    <?= $pctBpImprovement ?>% 
                    <span style="font-size: 14px; color: var(--text-secondary); font-weight: normal;">(<?= $improvedBpCount ?>/<?= $totalHtCases ?> ราย)</span>
                </div>
                <div style="font-size: 12px; margin-top: 8px; color: var(--text-muted);">
                    มีระดับความดันลดลงจากเดิมหรือกลับสู่สภาวะปกติ
                </div>
            </div>

            <div class="card-dark" style="border-left: 4px solid var(--color-green);">
                <div style="color: var(--text-secondary); font-size: 13px; font-weight: bold; margin-bottom: 8px;">ควบคุมค่าน้ำตาลสำเร็จ / ดีขึ้น</div>
                <div class="stat-val" style="color: var(--color-green);">
                    <?= $pctFbsImprovement ?>%
                    <span style="font-size: 14px; color: var(--text-secondary); font-weight: normal;">(<?= $improvedFbsCount ?>/<?= $totalDmCases ?> ราย)</span>
                </div>
                <div style="font-size: 12px; margin-top: 8px; color: var(--text-muted);">
                    ระดับน้ำตาลในเลือดลดลงจากเดิมหรือควบคุมได้ดี
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card-dark">
                <h4 style="color: var(--color-accent); margin-bottom: 16px; font-size: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">📊 เปรียบเทียบผลตรวจเฉลี่ย ก่อน-หลัง ร่วมโครงการ</h4>
                <div id="chart-outcome-comparison"></div>
            </div>
            <div class="card-dark">
                <h4 style="color: var(--color-accent); margin-bottom: 16px; font-size: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">📈 อัตราการเปลี่ยนแปลงความรุนแรงของกลุ่มเสี่ยง (Risk Transition)</h4>
                <div id="chart-risk-transition"></div>
            </div>
        </div>

        <!-- Geographic Temporal Map Playback Section -->
        <h3 style="color: var(--color-accent); margin-bottom: 16px; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <span>⏱️ แผนที่ระบุระดับพิกัดความเสี่ยงรายไตรมาส (Temporal Geographic Risk Heatmap)</span>
        </h3>
        
        <div class="play-controls">
            <button type="button" id="btn-play" onclick="togglePlay()" class="btn-control">
                ▶️ เล่นภาพเคลื่อนไหว
            </button>
            <div style="font-size: 15px; font-weight: bold; color: var(--color-accent);" id="active-quarter-title">
                ไตรมาส 1 (ต.ค. - ธ.ค. 2568)
            </div>
            <div class="quarter-indicator">
                <?php foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $idx => $qName): ?>
                    <span class="quarter-badge <?= $idx === 0 ? 'active' : '' ?>" onclick="updateMapForQuarter(<?= $idx ?>)">
                        <?= $qName ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stats-badge-container">
            <div class="stats-badge-card" style="border-top: 4px solid #6b7280;">
                <div class="num" id="stat-unscreened" style="color: #9ca3af;">0 ราย</div>
                <div class="label">⚪ ยังไม่ตรวจ</div>
            </div>
            <div class="stats-badge-card" style="border-top: 4px solid var(--color-green);">
                <div class="num" id="stat-normal" style="color: var(--color-green);">0 ราย</div>
                <div class="label">🟢 กลุ่มปกติ / คุมได้</div>
            </div>
            <div class="stats-badge-card" style="border-top: 4px solid var(--color-yellow);">
                <div class="num" id="stat-moderate" style="color: var(--color-yellow);">0 ราย</div>
                <div class="label">🟡 เสี่ยงปานกลาง</div>
            </div>
            <div class="stats-badge-card" style="border-top: 4px solid var(--color-red);">
                <div class="num" id="stat-high" style="color: var(--color-red);">0 ราย</div>
                <div class="label">🔴 กลุ่มเสี่ยงสูง / ป่วย</div>
            </div>
        </div>

        <div id="temporal-map"></div>

        <!-- Village scorecard table -->
        <div class="card-dark" style="margin-top: 30px;">
            <h3 style="color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: rgba(34, 197, 94, 0.15); color: #22c55e;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </span>
                <span>อัตราการลดความเสี่ยงรายพื้นที่หมู่บ้าน (Risk Reduction Rate by Village)</span>
            </h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>โรงพยาบาลส่งเสริมสุขภาพตำบล</th>
                            <th>หมู่บ้าน</th>
                            <th>หมู่</th>
                            <th style="text-align: right;">จำนวนในโครงการ (ราย)</th>
                            <th style="text-align: right;">ติดตามประเมิน 2 รอบขึ้นไป</th>
                            <th style="text-align: right;">จำนวนที่ค่าสุขภาพดีขึ้น (ราย)</th>
                            <th style="text-align: right;">อัตราสำเร็จ (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($villageImprovementData)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 24px;">ยังไม่มีข้อมูลผลลัพธ์การเข้าร่วมโครงการ</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($villageImprovementData as $vi): ?>
                                <?php
                                $village_only = $hoscode_villages[$vi['hoscode']]['villages'][intval($vi['moo'])] ?? get_village_only_name($hoscode_villages[$vi['hoscode']]['tambon'], $vi['moo']);
                                $hcName = $hc_names[$vi['hoscode']] ?? $vi['hoscode'];
                                $successRate = $vi['completed_followups'] > 0 ? round(($vi['improved_count'] / $vi['completed_followups']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($hcName) ?></td>
                                    <td><?= htmlspecialchars($village_only ?: 'หมู่ที่ ' . $vi['moo']) ?></td>
                                    <td><?= htmlspecialchars($vi['moo']) ?></td>
                                    <td style="text-align: right; font-weight: bold;"><?= number_format($vi['total_enrolled']) ?></td>
                                    <td style="text-align: right;"><?= number_format($vi['completed_followups']) ?></td>
                                    <td style="text-align: right; color: var(--color-green); font-weight: bold;"><?= number_format($vi['improved_count']) ?></td>
                                    <td style="text-align: right;">
                                        <span style="background-color: <?= $successRate >= 50 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(245, 158, 11, 0.15)' ?>; color: <?= $successRate >= 50 ? 'var(--color-green)' : 'var(--color-yellow)' ?>; padding: 4px 10px; border-radius: 12px; font-weight: bold;">
                                            <?= $successRate ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Chronological screening + DPAC events
        const records = <?= json_encode($historyRecords) ?>;
        // Targets with coordinates
        const targets = <?= json_encode($mapTargets) ?>;
        
        // Index records by CID for fast O(1) average lookup
        const recordsByCid = {};
        records.forEach(r => {
            if (!recordsByCid[r.cid]) {
                recordsByCid[r.cid] = [];
            }
            recordsByCid[r.cid].push(r);
        });

        // Resolve risk status at specific timestamp
        function getStatusAt(cid, endTimeStr) {
            const endTime = new Date(endTimeStr).getTime();
            const userRecs = recordsByCid[cid];
            if (!userRecs) return 'UNSCREENED';
            
            let latest = null;
            for (let i = 0; i < userRecs.length; i++) {
                let rTime = new Date(userRecs[i].created_at).getTime();
                if (rTime <= endTime) {
                    latest = userRecs[i];
                } else {
                    break;
                }
            }
            return latest ? latest.risk_level : 'UNSCREENED';
        }

        // Initialize Map
        const markers = {};
        const map = L.map('temporal-map').setView([<?= $mapCenterLat ?>, <?= $mapCenterLng ?>], <?= $mapInitialZoom ?>);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 20
        }).addTo(map);

        // Marker Colors
        const colors = {
            'HIGH': '#ef4444',
            'MODERATE': '#eab308',
            'NORMAL': '#10b981',
            'UNSCREENED': '#6b7280'
        };

        const labels = {
            'HIGH': 'เสี่ยงสูง 🔴',
            'MODERATE': 'เสี่ยงปานกลาง 🟡',
            'NORMAL': 'ปกติ / คุมได้ 🟢',
            'UNSCREENED': 'ยังไม่ได้ตรวจ ⚪'
        };

        // Create circles
        targets.forEach(t => {
            const marker = L.circleMarker([t.latitude, t.longitude], {
                radius: 6,
                fillColor: colors['UNSCREENED'],
                color: '#1e293b',
                weight: 1.5,
                fillOpacity: 0.85
            }).addTo(map);
            
            marker.bindPopup(`
                <div style="font-family: var(--font-sans); color: #1e293b; font-size: 13px;">
                    <strong style="color: #0ea5e9;">${t.first_name} ${t.last_name}</strong><br>
                    🏠 บ้านเลขที่: ${t.house_no} หมู่: ${t.moo}<br>
                    <span id="pop-status-${t.cid}">สถานะ: โหลดข้อมูล...</span>
                </div>
            `);
            
            markers[t.cid] = marker;
        });

        // Quarter playback
        let activeQuarterIndex = 0;
        let playInterval = null;

        const quarters = [
            { name: 'ไตรมาส 1 (ต.ค. - ธ.ค. 2568)', end: '2025-12-31T23:59:59' },
            { name: 'ไตรมาส 2 (ม.ค. - มี.ค. 2569)', end: '2026-03-31T23:59:59' },
            { name: 'ไตรมาส 3 (เม.ย. - มิ.ย. 2569)', end: '2026-06-30T23:59:59' },
            { name: 'ไตรมาส 4 (ก.ค. - ก.ย. 2569)', end: '2026-09-30T23:59:59' }
        ];

        function updateMapForQuarter(qIndex) {
            activeQuarterIndex = qIndex;
            const quarter = quarters[qIndex];
            
            document.querySelectorAll('.quarter-badge').forEach((badge, idx) => {
                if (idx === qIndex) {
                    badge.classList.add('active');
                } else {
                    badge.classList.remove('active');
                }
            });
            
            document.getElementById('active-quarter-title').innerText = quarter.name;
            
            let stats = { HIGH: 0, MODERATE: 0, NORMAL: 0, UNSCREENED: 0 };
            
            targets.forEach(t => {
                const status = getStatusAt(t.cid, quarter.end);
                stats[status]++;
                
                const marker = markers[t.cid];
                if (marker) {
                    marker.setStyle({ fillColor: colors[status] });
                    marker.getPopup().setContent(`
                        <div style="font-family: var(--font-sans); color: #1e293b; font-size: 13px;">
                            <strong style="color: #0ea5e9;">${t.first_name} ${t.last_name}</strong><br>
                            🏠 บ้านเลขที่: ${t.house_no} หมู่: ${t.moo}<br>
                            <span>สถานะ: ${labels[status]}</span>
                        </div>
                    `);
                }
            });
            
            document.getElementById('stat-unscreened').innerText = stats.UNSCREENED.toLocaleString() + ' ราย';
            document.getElementById('stat-normal').innerText = stats.NORMAL.toLocaleString() + ' ราย';
            document.getElementById('stat-moderate').innerText = stats.MODERATE.toLocaleString() + ' ราย';
            document.getElementById('stat-high').innerText = stats.HIGH.toLocaleString() + ' ราย';
        }

        function nextQuarter() {
            let nextIdx = (activeQuarterIndex + 1) % quarters.length;
            updateMapForQuarter(nextIdx);
        }

        function togglePlay() {
            const btn = document.getElementById('btn-play');
            if (playInterval) {
                clearInterval(playInterval);
                playInterval = null;
                btn.innerHTML = '▶️ เล่นภาพเคลื่อนไหว';
                btn.classList.remove('active-play');
            } else {
                playInterval = setInterval(nextQuarter, 1500);
                btn.innerHTML = '⏸️ หยุดชั่วคราว';
                btn.classList.add('active-play');
            }
        }

        // Load first quarter initially
        document.addEventListener("DOMContentLoaded", function() {
            updateMapForQuarter(0);
        });

        // ── Charts Render ──────────────────────────────────────────
        // Chart 1: Before vs After Averages
        var optionsOutcome = {
            series: [{
                name: 'ก่อนร่วมโครงการ (Round 1)',
                data: [<?= $avgSbpBefore ?>, <?= $avgDbpBefore ?>, <?= $avgFbsBefore ?>]
            }, {
                name: 'ประเมินรอบล่าสุด',
                data: [<?= $avgSbpAfter ?>, <?= $avgDbpAfter ?>, <?= $avgFbsAfter ?>]
            }],
            chart: {
                type: 'bar',
                height: 300,
                background: 'transparent',
                toolbar: { show: false }
            },
            theme: { mode: 'dark' },
            colors: ['#3b82f6', '#10b981'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 4
                },
            },
            dataLabels: { enabled: false },
            stroke: { show: true, width: 2, colors: ['transparent'] },
            xaxis: {
                categories: ['SYS BP (mmHg)', 'DIA BP (mmHg)', 'Sugar FBS (mg/dL)'],
                labels: { style: { colors: '#9ca3af' } }
            },
            yaxis: {
                title: { text: 'ระดับผลตรวจสุขภาพ', style: { color: '#9ca3af' } },
                labels: { style: { colors: '#9ca3af' } }
            },
            fill: { opacity: 1 },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val;
                    }
                }
            },
            legend: { labels: { colors: '#9ca3af' } }
        };

        var chartOutcome = new ApexCharts(document.querySelector("#chart-outcome-comparison"), optionsOutcome);
        chartOutcome.render();

        // Chart 2: Risk Transitions
        var optionsTransition = {
            series: [{
                name: 'เสี่ยงสูง 🔴',
                data: [<?= $beforeHigh ?>, <?= $afterHigh ?>]
            }, {
                name: 'เสี่ยงปานกลาง 🟡',
                data: [<?= $beforeModerate ?>, <?= $afterModerate ?>]
            }, {
                name: 'ปกติ / คุมได้ 🟢',
                data: [<?= $beforeNormal ?>, <?= $afterNormal ?>]
            }],
            chart: {
                type: 'bar',
                height: 300,
                stacked: true,
                background: 'transparent',
                toolbar: { show: false }
            },
            theme: { mode: 'dark' },
            colors: ['#ef4444', '#eab308', '#10b981'],
            plotOptions: {
                bar: {
                    borderRadius: 6
                }
            },
            xaxis: {
                categories: ['ก่อนเข้าร่วมโครงการ', 'ประเมินผลรอบล่าสุด'],
                labels: { style: { colors: '#9ca3af' } }
            },
            yaxis: {
                labels: { style: { colors: '#9ca3af' } }
            },
            fill: { opacity: 1 },
            legend: {
                position: 'bottom',
                labels: { colors: '#9ca3af' }
            }
        };

        var chartTransition = new ApexCharts(document.querySelector("#chart-risk-transition"), optionsTransition);
        chartTransition.render();
    </script>
</body>
</html>
