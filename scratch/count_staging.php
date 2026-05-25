<?php
require __DIR__ . '/../config/db.php';

try {
    echo "=== staging_hdc_dm counts by hoscode & risk ===\n";
    $q1 = $pdo->query("SELECT hoscode, risk, COUNT(*) as qty FROM staging_hdc_dm GROUP BY hoscode, risk ORDER BY hoscode, risk");
    while ($row = $q1->fetch(PDO::FETCH_ASSOC)) {
        echo "HOSCODE: {$row['hoscode']} | RISK: {$row['risk']} | COUNT: {$row['qty']}\n";
    }
    $totalDm = $pdo->query("SELECT COUNT(*) FROM staging_hdc_dm")->fetchColumn();
    echo "Total DM: $totalDm\n\n";

    echo "=== staging_hdc_ht counts by hoscode & risk ===\n";
    $q2 = $pdo->query("SELECT hoscode, risk, COUNT(*) as qty FROM staging_hdc_ht GROUP BY hoscode, risk ORDER BY hoscode, risk");
    while ($row = $q2->fetch(PDO::FETCH_ASSOC)) {
        echo "HOSCODE: {$row['hoscode']} | RISK: {$row['risk']} | COUNT: {$row['qty']}\n";
    }
    $totalHt = $pdo->query("SELECT COUNT(*) FROM staging_hdc_ht")->fetchColumn();
    echo "Total HT: $totalHt\n\n";
    
    echo "=== target_population counts by hoscode & health_status_origin ===\n";
    $q3 = $pdo->query("SELECT hoscode, health_status_origin, COUNT(*) as qty FROM target_population GROUP BY hoscode, health_status_origin ORDER BY hoscode, health_status_origin");
    while ($row = $q3->fetch(PDO::FETCH_ASSOC)) {
        echo "HOSCODE: {$row['hoscode']} | STATUS: {$row['health_status_origin']} | COUNT: {$row['qty']}\n";
    }
    $totalTp = $pdo->query("SELECT COUNT(*) FROM target_population")->fetchColumn();
    echo "Total Target Population: $totalTp\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
