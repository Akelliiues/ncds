<?php
require __DIR__ . '/../config/db.php';
try {
    $r = $pdo->query('DESCRIBE vhv_users');
    echo "=== vhv_users columns ===\n";
    while($row = $r->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    echo "\n=== Does sub_district_code exist? ===\n";
    try {
        $pdo->query('SELECT sub_district_code FROM vhv_users LIMIT 1');
        echo "YES\n";
    } catch (Exception $e) {
        echo "NO: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Checking target_population for hoscode-tambon mapping ===\n";
    $r2 = $pdo->query('SELECT DISTINCT hoscode, sub_district_code FROM target_population ORDER BY hoscode');
    while($row2 = $r2->fetch()) {
        echo $row2['hoscode'] . ' => ' . $row2['sub_district_code'] . "\n";
    }

    echo "\n=== Sample coordinates ===\n";
    $r3 = $pdo->query('SELECT latitude, longitude, hoscode, moo, sub_district_code FROM target_population WHERE latitude IS NOT NULL AND longitude IS NOT NULL LIMIT 5');
    while($row3 = $r3->fetch()) {
        echo $row3['latitude'] . ', ' . $row3['longitude'] . ' (hoscode=' . $row3['hoscode'] . ', moo=' . $row3['moo'] . ', sub=' . $row3['sub_district_code'] . ")\n";
    }
    
    echo "\n=== Count of targets with coordinates ===\n";
    $count = $pdo->query('SELECT COUNT(*) FROM target_population WHERE latitude IS NOT NULL AND longitude IS NOT NULL')->fetchColumn();
    echo "Total: $count\n";
    
    echo "\n=== Count of targets with coordinates, by hoscode ===\n";
    $r4 = $pdo->query('SELECT hoscode, COUNT(*) as cnt FROM target_population WHERE latitude IS NOT NULL AND longitude IS NOT NULL GROUP BY hoscode ORDER BY hoscode');
    while($row4 = $r4->fetch()) {
        echo $row4['hoscode'] . ': ' . $row4['cnt'] . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
