<?php
// admin/migrate_settings.php
require_once __DIR__ . '/../config/db.php';

echo "<h1>Starting System Settings Migration</h1>";

try {
    // 1. Create table system_settings
    $sql = "CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        description VARCHAR(255) NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "<p style='color: green;'>[OK] Table 'system_settings' checked/created.</p>";

    // 2. Insert default sandbox_mode value
    $insertSql = "INSERT INTO system_settings (setting_key, setting_value, description)
        VALUES ('sandbox_mode', '1', 'โหมดทดสอบจำลองระบบ (0 = ปิด/ใช้งานจริง, 1 = เปิด/จำลอง)')
        ON DUPLICATE KEY UPDATE description = 'โหมดทดสอบจำลองระบบ (0 = ปิด/ใช้งานจริง, 1 = เปิด/จำลอง)';";
        
    $pdo->exec($insertSql);
    echo "<p style='color: green;'>[OK] Default 'sandbox_mode' value inserted/updated.</p>";

    echo "<h3>Migration completed successfully!</h3>";

} catch (Exception $e) {
    echo "<p style='color: red;'>[ERROR] Migration failed: " . $e->getMessage() . "</p>";
}
