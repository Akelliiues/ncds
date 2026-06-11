<?php
$ports = ['3333', '3306'];
$host = '127.0.0.1';
$db = 'tansum_ncd';
$user = 'tansum_ncd';
$pass = 'Prevention2026';

$connected = false;
foreach ($ports as $port) {
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        echo "Successfully connected on port $port!\n";
        $connected = true;
        
        // Let's run our query here!
        $stmt = $pdo->query("
            SELECT 
                need_screen_dm, 
                need_screen_ht, 
                health_status_origin,
                COUNT(*) as cnt
            FROM target_population
            GROUP BY need_screen_dm, need_screen_ht, health_status_origin
        ");
        $results = $stmt->fetchAll();
        echo "\n=== Target Population Summary ===\n";
        foreach ($results as $r) {
            printf("need_screen_dm: %d | need_screen_ht: %d | health_status_origin: %10s | count: %d\n", 
                $r['need_screen_dm'], 
                $r['need_screen_ht'], 
                $r['health_status_origin'] ?? 'NULL', 
                $r['cnt']
            );
        }
        
        break;
    } catch (Exception $e) {
        echo "Failed to connect on port $port: " . $e->getMessage() . "\n";
    }
}
