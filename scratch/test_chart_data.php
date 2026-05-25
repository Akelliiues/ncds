<?php
// Try port 3306 then 3333
$ports = ['3306', '3333'];
$pdo = null;

foreach ($ports as $port) {
    try {
        $dsn = "mysql:host=127.0.0.1;port=$port;dbname=tansum_ncd;charset=utf8mb4";
        $pdo = new PDO($dsn, 'tansum_ncd', 'Prevention2026', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        echo "Successfully connected to MySQL on port $port\n";
        break;
    } catch (Exception $e) {
        echo "Failed to connect on port $port: " . $e->getMessage() . "\n";
    }
}

if (!$pdo) {
    die("Could not connect to MySQL database on any tested port.\n");
}

// Now let's test functions
require_once __DIR__ . '/../config/db.php'; // This might redefine $pdo or fail, so let's prevent db.php from throwing connection errors by temporarily mocking $is_local or just copying get_village_display_name_by_hoscode logic.

$hoscode = '03754'; // รพ.สต.บ้านหนองกุงใหญ่
echo "\n=== TESTING FOR HOSCODE $hoscode ===\n";

try {
    $stmt = $pdo->prepare("
        SELECT p.hoscode, p.sub_district_code, p.moo,
               COUNT(*) as total_targets,
               SUM(CASE WHEN a.assignment_status = 'completed' THEN 1 ELSE 0 END) as screened
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid
        WHERE p.hoscode = ?
        GROUP BY p.hoscode, p.sub_district_code, p.moo
        ORDER BY p.moo
    ");
    $stmt->execute([$hoscode]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Query returned " . count($data) . " rows.\n";
    foreach ($data as &$row) {
        $row['village_name'] = get_village_display_name_by_hoscode($row['hoscode'], $row['moo']);
        echo "moo: " . json_encode($row['moo']) . " | village_name: " . json_encode($row['village_name']) . " | targets: {$row['total_targets']} | screened: {$row['screened']}\n";
    }
    
    echo "\nJSON Output:\n";
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
