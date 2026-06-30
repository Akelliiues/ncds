<?php
require_once __DIR__ . '/../config/db.php';

echo "=== Target Population Summary ===\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM target_population");
echo "Total target_population: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM target_population WHERE cid LIKE '%*%'");
echo "Masked CIDs in target_population: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM target_population WHERE cid NOT LIKE '%*%' AND cid NOT LIKE '0%'");
echo "Real CIDs in target_population: " . $stmt->fetchColumn() . "\n";

echo "\n=== Staging HDC DM Summary ===\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM staging_hdc_dm");
echo "Total staging_hdc_dm: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM staging_hdc_dm WHERE cid LIKE '%*%'");
echo "Masked CIDs in staging_hdc_dm: " . $stmt->fetchColumn() . "\n";

echo "\n=== Staging HDC HT Summary ===\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM staging_hdc_ht");
echo "Total staging_hdc_ht: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM staging_hdc_ht WHERE cid LIKE '%*%'");
echo "Masked CIDs in staging_hdc_ht: " . $stmt->fetchColumn() . "\n";

echo "\n=== Left Join test ===\n";
// Let's test the join used in target_manager.php:
$sql = "
    SELECT 
        t.cid as t_cid,
        t.pid as t_pid,
        t.hoscode as t_hoscode,
        t.first_name as t_first_name,
        t.last_name as t_last_name,
        tp_real.cid as real_cid,
        tp_real.first_name as real_fname,
        tp_real.last_name as real_lname
    FROM target_population t
    LEFT JOIN target_population tp_real ON (
        tp_real.hoscode = t.hoscode
        AND tp_real.pid = t.pid
        AND tp_real.cid NOT LIKE '0%'
        AND tp_real.cid NOT LIKE '%*%'
        AND tp_real.first_name NOT IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown','')
    )
    WHERE t.cid LIKE '%*%' AND tp_real.cid IS NOT NULL
    LIMIT 10
";
$stmt = $pdo->query($sql);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n=== Masked examples NOT mapped ===\n";
$sql = "
    SELECT 
        t.cid as t_cid,
        t.pid as t_pid,
        t.hoscode as t_hoscode,
        t.first_name as t_first_name,
        t.last_name as t_last_name
    FROM target_population t
    LEFT JOIN target_population tp_real ON (
        tp_real.hoscode = t.hoscode
        AND tp_real.pid = t.pid
        AND tp_real.cid NOT LIKE '0%'
        AND tp_real.cid NOT LIKE '%*%'
        AND tp_real.first_name NOT IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown','')
    )
    WHERE t.cid LIKE '%*%' AND tp_real.cid IS NULL
    LIMIT 10
";
$stmt = $pdo->query($sql);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
