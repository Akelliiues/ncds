<?php
require __DIR__ . '/../config/db.php';
try {
    $cols = $pdo->query("DESCRIBE vhv_users")->fetchAll(PDO::FETCH_COLUMN);
    echo "vhv_users columns:\n";
    echo implode(", ", $cols) . "\n\n";
    
    $cols2 = $pdo->query("DESCRIBE target_population")->fetchAll(PDO::FETCH_COLUMN);
    echo "target_population columns:\n";
    echo implode(", ", $cols2) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
