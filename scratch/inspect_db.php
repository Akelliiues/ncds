<?php
require_once __DIR__ . '/../config/db.php';

function printTableDesc($pdo, $tbl) {
    echo "=== DESCRIBE `$tbl` ===\n";
    try {
        $stmt = $pdo->query("DESCRIBE `$tbl`");
        while ($row = $stmt->fetch()) {
            printf("  %-20s | %-15s | %-5s | %-5s | %-10s\n", 
                $row['Field'], $row['Type'], $row['Null'], $row['Key'], $row['Default'] ?? 'NULL'
            );
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== INDEXES FOR `$tbl` ===\n";
    try {
        $stmt = $pdo->query("SHOW INDEX FROM `$tbl`");
        while ($row = $stmt->fetch()) {
            printf("  Key: %-15s | Column: %-15s | Seq: %d | Non_Unique: %d\n",
                $row['Key_name'], $row['Column_name'], $row['Seq_in_index'], $row['Non_unique']
            );
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n" . str_repeat("-", 60) . "\n\n";
}

printTableDesc($pdo, 'target_population');
printTableDesc($pdo, 'jhcis_homes');

