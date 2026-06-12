<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $hid = '1';
    echo "Querying target_population for hid = '1':\n";
    $stmt = $pdo->prepare("SELECT cid, hid, vhid_code, hoscode, first_name FROM target_population WHERE hid = ?");
    $stmt->execute([$hid]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $index => $row) {
        echo "Row $index: cid={$row['cid']}, hid={$row['hid']}, vhid_code={$row['vhid_code']}, hoscode={$row['hoscode']}, name={$row['first_name']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
