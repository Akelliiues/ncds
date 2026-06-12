<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Clear in jhcis_homes for hoscode 10957
    $stmt1 = $pdo->prepare("
        UPDATE jhcis_homes 
        SET latitude = NULL, longitude = NULL 
        WHERE CAST(hoscode AS UNSIGNED) = 10957
    ");
    $stmt1->execute();
    $homes_updated = $stmt1->rowCount();

    // 2. Clear in target_population for hoscode 10957
    $stmt2 = $pdo->prepare("
        UPDATE target_population 
        SET latitude = NULL, longitude = NULL 
        WHERE CAST(hoscode AS UNSIGNED) = 10957
    ");
    $stmt2->execute();
    $targets_updated = $stmt2->rowCount();

    $pdo->commit();
    echo "SUCCESS: Cleared coordinate values (set to NULL) for $homes_updated houses and $targets_updated target individuals under hoscode 10957.";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage();
}
?>
