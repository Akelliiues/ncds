<?php
// scratch/get_test_data.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "Testing scan_security_log table creation...\n";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scan_security_log (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            logged_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            vhv_id       VARCHAR(20)  NOT NULL,
            vhv_name     VARCHAR(120) DEFAULT NULL,
            hoscode      VARCHAR(10)  DEFAULT NULL,
            scanned_code VARCHAR(30)  NOT NULL,
            scan_lat     DECIMAL(10,7) DEFAULT NULL,
            scan_lng     DECIMAL(10,7) DEFAULT NULL,
            ip_address   VARCHAR(45)  DEFAULT NULL,
            user_agent   TEXT         DEFAULT NULL,
            incident_type VARCHAR(60) NOT NULL DEFAULT 'UNAUTHORIZED_SCAN',
            INDEX idx_logged_at (logged_at),
            INDEX idx_vhv_id    (vhv_id),
            INDEX idx_hoscode   (hoscode)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table created successfully!\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
