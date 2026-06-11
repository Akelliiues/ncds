<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Loading config/db.php...\n";
require_once __DIR__ . '/../config/db.php';
echo "Loaded config/db.php successfully!\n\n";

echo "Testing get_health_units()...\n";
$units = get_health_units();
echo "Found " . count($units) . " health units.\n\n";

echo "Testing get_query_hoscodes() without param...\n";
$hoscodesAll = get_query_hoscodes();
echo "All hoscodes: " . implode(', ', $hoscodesAll) . "\n\n";

echo "Testing get_query_hoscodes('10957')...\n";
$hoscodes10957 = get_query_hoscodes('10957');
echo "Hoscodes for 10957: " . implode(', ', $hoscodes10957) . "\n\n";

echo "Testing get_query_hoscodes('03751')...\n";
$hoscodes03751 = get_query_hoscodes('03751');
echo "Hoscodes for 03751: " . implode(', ', $hoscodes03751) . "\n\n";

echo "Diagnosis complete!\n";
