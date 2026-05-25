<?php
$db   = 'tansum_ncd';
$user = 'tansum_ncd';
$pass = 'Prevention2026';
$charset = 'utf8mb4';

$ports = ['3306', '3333', ''];
$hosts = ['127.0.0.1', 'localhost'];

foreach ($hosts as $host) {
    foreach ($ports as $port) {
        $dsn = empty($port) ? "mysql:host=$host;dbname=$db;charset=$charset" : "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
        try {
            echo "Trying $dsn... ";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2
            ]);
            echo "SUCCESS!\n";
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "Tables:\n";
            foreach ($tables as $t) {
                echo "  - $t\n";
            }
            exit(0);
        } catch (\Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
        }
    }
}
echo "All connections failed.\n";
