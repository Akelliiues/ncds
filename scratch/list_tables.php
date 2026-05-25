<?php
require_once __DIR__ . '/../config/db.php';
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "=== TABLES ===\n";
    foreach ($tables as $t) {
        echo "- $t\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
