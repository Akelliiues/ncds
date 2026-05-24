<?php
require __DIR__ . '/../config/db.php';

try {
    echo "=== staging_hdc_dm SAMPLE ===\n";
    $q1 = $pdo->query("SELECT cid, hoscode, check_vhid, addr, risk FROM staging_hdc_dm LIMIT 15");
    $r1 = $q1->fetchAll(PDO::FETCH_ASSOC);
    foreach ($r1 as $row) {
        echo "CID: {$row['cid']} | HOSCODE: {$row['hoscode']} | CHECK_VHID: {$row['check_vhid']} | ADDR: {$row['addr']} | RISK: {$row['risk']}\n";
    }

    echo "\n=== staging_hdc_ht SAMPLE ===\n";
    $q2 = $pdo->query("SELECT cid, hoscode, check_vhid, addr, risk FROM staging_hdc_ht LIMIT 15");
    $r2 = $q2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($r2 as $row) {
        echo "CID: {$row['cid']} | HOSCODE: {$row['hoscode']} | CHECK_VHID: {$row['check_vhid']} | ADDR: {$row['addr']} | RISK: {$row['risk']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
