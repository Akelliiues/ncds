<?php
// scratch/run_migration_direct.php

$db = 'tansum_ncd';
$user = 'tansum_ncd';
$pass = 'Prevention2026';
$charset = 'utf8mb4';

$ports = ['3333', '3306', ''];
$connected = false;
$pdo = null;

foreach ($ports as $port) {
    try {
        if (!empty($port)) {
            $dsn = "mysql:host=127.0.0.1;port=$port;dbname=$db;charset=$charset";
        } else {
            $dsn = "mysql:host=localhost;dbname=$db;charset=$charset";
        }
        
        echo "Trying connection to $dsn ... ";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        echo "SUCCESS!\n";
        $connected = true;
        break;
    } catch (PDOException $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}

if (!$connected) {
    die("Error: Could not connect to database on any port.\n");
}

try {
    // Create table system_settings
    $sql = "CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        description VARCHAR(255) NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "[OK] Table 'system_settings' checked/created.\n";

    // Insert default sandbox_mode value
    $insertSql = "INSERT INTO system_settings (setting_key, setting_value, description)
        VALUES ('sandbox_mode', '1', 'โหมดทดสอบจำลองระบบ (0 = ปิด/ใช้งานจริง, 1 = เปิด/จำลอง)')
        ON DUPLICATE KEY UPDATE description = 'โหมดทดสอบจำลองระบบ (0 = ปิด/ใช้งานจริง, 1 = เปิด/จำลอง)';";
        
    $pdo->exec($insertSql);
    echo "[OK] Default 'sandbox_mode' value inserted/updated.\n";
    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
