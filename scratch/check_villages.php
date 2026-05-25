<?php
require_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("SELECT DISTINCT sub_district_code, moo, hoscode FROM target_population LIMIT 30");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "=== TARGET POPULATION VILLAGES ===\n";
foreach ($rows as $row) {
    echo "Hoscode: {$row['hoscode']} | Sub-district Code: '{$row['sub_district_code']}' (len: " . strlen($row['sub_district_code'] ?? '') . ") | Moo: '{$row['moo']}'\n";
}
