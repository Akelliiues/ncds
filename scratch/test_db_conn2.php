<?php
$passwords = ['', 'root', 'Prevention2026'];
foreach ($passwords as $pw) {
    try {
        $pdo = new PDO("mysql:host=127.0.0.1;port=3333;dbname=tansum_ncd", "root", $pw);
        echo "SUCCESS WITH ROOT AND PASSWORD '$pw'!\n";
        
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables in tansum_ncd:\n";
        foreach ($tables as $t) {
            echo "  - $t\n";
        }
        exit(0);
    } catch (Exception $e) {
        echo "FAIL WITH ROOT AND PASSWORD '$pw': " . $e->getMessage() . "\n";
    }
}
