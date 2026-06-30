<?php
require_once 'config/db.php';
try {
    echo "=== target_population health_status_origin count ===\n";
    $stmt = $pdo->query("SELECT health_status_origin, COUNT(*) as cnt FROM target_population GROUP BY health_status_origin");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['health_status_origin']}: {$row['cnt']}\n";
    }

    echo "\n=== staging_hdc_dm risk count ===\n";
    $stmt = $pdo->query("SELECT risk, COUNT(*) as cnt FROM staging_hdc_dm GROUP BY risk");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Risk {$row['risk']}: {$row['cnt']}\n";
    }

    echo "\n=== staging_hdc_ht risk count ===\n";
    $stmt = $pdo->query("SELECT risk, COUNT(*) as cnt FROM staging_hdc_ht GROUP BY risk");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Risk {$row['risk']}: {$row['cnt']}\n";
    }

    echo "\n=== Patients with BOTH DM (risk=5) and HT (risk=5) in staging ===\n";
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT dm.cid) 
        FROM staging_hdc_dm dm 
        JOIN staging_hdc_ht ht ON dm.cid = ht.cid 
        WHERE dm.risk = '5' AND ht.risk = '5'
    ");
    $cnt = $stmt->fetchColumn();
    echo "Comorbid Patients Count: {$cnt}\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>