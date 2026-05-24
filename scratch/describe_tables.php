<?php
require_once __DIR__ . '/../config/db.php';

function printTable($pdo, $table) {
    echo "=== Table: $table ===\n";
    try {
        $q = $pdo->query("DESCRIBE `$table`")->fetchAll();
        foreach ($q as $col) {
            echo "  {$col['Field']} | {$col['Type']} | {$col['Null']} | {$col['Key']} | {$col['Default']}\n";
        }
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

printTable($pdo, 'target_population');
printTable($pdo, 'staging_hdc_dm');
printTable($pdo, 'staging_hdc_ht');
