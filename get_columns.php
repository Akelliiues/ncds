<?php
// get_columns.php
require_once __DIR__ . '/config/db.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM assignment_history_log");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "=== COLUMNS IN assignment_history_log ===\n";
    print_r($columns);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
