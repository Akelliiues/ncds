<?php
require_once __DIR__ . '/config/db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dpac_enrollments (
            enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
            cid VARCHAR(13) NOT NULL,
            budget_year INT NOT NULL,
            risk_type ENUM('DM', 'HT', 'BOTH') NOT NULL,
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active', 'completed', 'dropped') DEFAULT 'active'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dpac_followups (
            followup_id INT AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT NOT NULL,
            vhv_id VARCHAR(20) NOT NULL,
            round_number INT NOT NULL DEFAULT 1,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending', 'completed') DEFAULT 'pending',
            completed_at TIMESTAMP NULL,
            weight DECIMAL(5,2),
            height DECIMAL(5,2),
            waist DECIMAL(5,2),
            bp_sys INT,
            bp_dia INT,
            fbs INT,
            health_risk_level VARCHAR(20),
            advice_given TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo 'Tables created successfully';
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
