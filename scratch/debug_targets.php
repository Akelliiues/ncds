<?php
// debug_targets.php - ตรวจสอบข้อมูลใน target_population
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/html; charset=utf-8');

// 1. ตรวจสอบ schema ของตาราง target_population
$cols = $pdo->query("DESCRIBE target_population")->fetchAll(PDO::FETCH_ASSOC);
echo "<h2>Table Structure: target_population</h2><pre>";
foreach ($cols as $c) {
    echo $c['Field'] . " | " . $c['Type'] . " | Null=" . $c['Null'] . " | Key=" . $c['Key'] . " | Default=" . $c['Default'] . "\n";
}
echo "</pre>";

// 2. ดู sample data 10 รายการล่าสุด
$rows = $pdo->query("SELECT cid, pid, hoscode, moo, house_no, first_name, last_name, need_screen_dm, need_screen_ht FROM target_population ORDER BY updated_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "<h2>Sample Data (10 รายการล่าสุด)</h2><table border='1' cellpadding='4'><tr>";
if (!empty($rows)) {
    echo "<tr><th>cid</th><th>pid</th><th>hoscode</th><th>moo (type)</th><th>house_no</th><th>first_name</th><th>last_name</th><th>dm</th><th>ht</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($r['cid']) . "</td>";
        echo "<td>" . htmlspecialchars($r['pid']) . "</td>";
        echo "<td>" . htmlspecialchars($r['hoscode']) . "</td>";
        echo "<td>" . htmlspecialchars($r['moo']) . " (" . gettype($r['moo']) . ")</td>";
        echo "<td>" . htmlspecialchars($r['house_no']) . "</td>";
        echo "<td>" . htmlspecialchars($r['first_name']) . "</td>";
        echo "<td>" . htmlspecialchars($r['last_name']) . "</td>";
        echo "<td>" . $r['need_screen_dm'] . "</td>";
        echo "<td>" . $r['need_screen_ht'] . "</td>";
        echo "</tr>";
    }
}
echo "</table>";

// 3. นับรายการทั้งหมด
$total = $pdo->query("SELECT COUNT(*) FROM target_population")->fetchColumn();
$active = $pdo->query("SELECT COUNT(*) FROM target_population WHERE need_screen_dm=1 OR need_screen_ht=1")->fetchColumn();
echo "<h2>Count</h2><p>Total: $total | Active targets: $active</p>";

// 4. ดู distinct hoscode+moo combinations
echo "<h2>Distinct hoscode + moo combinations</h2>";
$combos = $pdo->query("SELECT hoscode, moo, COUNT(*) as cnt FROM target_population GROUP BY hoscode, moo ORDER BY hoscode, moo")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='4'><tr><th>hoscode</th><th>moo</th><th>count</th></tr>";
foreach ($combos as $c) {
    echo "<tr><td>" . htmlspecialchars($c['hoscode']) . "</td><td>" . htmlspecialchars($c['moo']) . "</td><td>" . $c['cnt'] . "</td></tr>";
}
echo "</table>";

// 5. ทดสอบ get_targets query โดยตรงจาก URL parameter ถ้ามี
if (isset($_GET['hoscode']) && isset($_GET['moo'])) {
    $hs = $_GET['hoscode'];
    $m = $_GET['moo'];
    echo "<h2>Test Query: hoscode=$hs, moo=$m</h2>";
    
    $stmt = $pdo->prepare("SELECT cid, pid, hoscode, moo, first_name, last_name FROM target_population WHERE LPAD(hoscode, 5, '0') = LPAD(?, 5, '0') AND moo = ?");
    $stmt->execute([$hs, $m]);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>พบ " . count($res) . " รายการ</p>";
    if (!empty($res)) {
        echo "<table border='1' cellpadding='4'><tr><th>cid</th><th>hoscode</th><th>moo</th><th>first_name</th></tr>";
        foreach ($res as $r) {
            echo "<tr><td>" . htmlspecialchars($r['cid']) . "</td><td>" . htmlspecialchars($r['hoscode']) . "</td><td>" . htmlspecialchars($r['moo']) . "</td><td>" . htmlspecialchars($r['first_name']) . "</td></tr>";
        }
        echo "</table>";
    }
}

// 6. ตรวจสอบว่า villages table มี hoscode และ moo ที่ตรงกันไหม
echo "<h2>Villages Table (sample)</h2>";
$vills = $pdo->query("SELECT hoscode, moo, village_name FROM villages ORDER BY hoscode, moo LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='4'><tr><th>hoscode</th><th>moo</th><th>name</th></tr>";
foreach ($vills as $v) {
    echo "<tr><td>" . htmlspecialchars($v['hoscode']) . "</td><td>" . htmlspecialchars($v['moo']) . "</td><td>" . htmlspecialchars($v['village_name']) . "</td></tr>";
}
echo "</table>";

// 7. ตรวจสอบข้อมูล staging_hdc_dm
echo "<h2>staging_hdc_dm Columns & Risk Distribution</h2>";
try {
    $dm_cols = $pdo->query("DESCRIBE staging_hdc_dm")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>Columns:\n";
    foreach ($dm_cols as $c) {
        echo $c['Field'] . " (" . $c['Type'] . ")\n";
    }
    echo "</pre>";

    $dm_risks = $pdo->query("SELECT risk, COUNT(*) as cnt FROM staging_hdc_dm GROUP BY risk")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='4'><tr><th>risk</th><th>count</th></tr>";
    foreach ($dm_risks as $dr) {
        echo "<tr><td>" . htmlspecialchars($dr['risk']) . "</td><td>" . $dr['cnt'] . "</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error staging_hdc_dm: " . $e->getMessage();
}

// 8. ตรวจสอบข้อมูล staging_hdc_ht
echo "<h2>staging_hdc_ht Columns & Risk Distribution</h2>";
try {
    $ht_cols = $pdo->query("DESCRIBE staging_hdc_ht")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>Columns:\n";
    foreach ($ht_cols as $c) {
        echo $c['Field'] . " (" . $c['Type'] . ")\n";
    }
    echo "</pre>";

    $ht_risks = $pdo->query("SELECT risk, COUNT(*) as cnt FROM staging_hdc_ht GROUP BY risk")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='4'><tr><th>risk</th><th>count</th></tr>";
    foreach ($ht_risks as $hr) {
        echo "<tr><td>" . htmlspecialchars($hr['risk']) . "</td><td>" . $hr['cnt'] . "</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error staging_hdc_ht: " . $e->getMessage();
}

