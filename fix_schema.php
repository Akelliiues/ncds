<?php
require_once __DIR__ . '/config/db.php';
try {
    $pdo->exec("ALTER TABLE staging_hdc_dm MODIFY risk VARCHAR(50)");
    $pdo->exec("ALTER TABLE staging_hdc_ht MODIFY risk VARCHAR(50)");
    echo "Schema updated successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
