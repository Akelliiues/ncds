<?php
// config/db.php

// Fallbacks and safe multibyte functions for environments with restricted encoding support
if (!function_exists('safe_is_utf8')) {
    function safe_is_utf8($str) {
        if ($str === null) return true;
        return preg_match('//u', (string)$str) === 1;
    }
}

if (!function_exists('safe_tis620_to_utf8')) {
    function safe_tis620_to_utf8($val) {
        if ($val === null) return '';
        $out = '';
        $len = strlen($val);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($val[$i]);
            if ($c >= 0x00 && $c <= 0x7F) {
                $out .= chr($c);
            } elseif ($c >= 0xA1 && $c <= 0xDF) {
                $out .= chr(0xE0) . chr(0xB8) . chr(0x80 + ($c - 0xA0));
            } elseif ($c >= 0xE0 && $c <= 0xFB) {
                $out .= chr(0xE0) . chr(0xB9) . chr(0x80 + ($c - 0xE0));
            } else {
                $out .= ' ';
            }
        }
        return $out;
    }
}

// Fallbacks for mbstring extension if not enabled in php.ini
if (!function_exists('mb_check_encoding')) {
    function mb_check_encoding($var = null, $encoding = null) {
        if ($var === null) return true;
        return preg_match('//u', $var) === 1;
    }
}

if (!function_exists('mb_convert_encoding')) {
    function mb_convert_encoding($val, $to_encoding, $from_encoding = null) {
        if (strtoupper($to_encoding) === 'UTF-8' && (strtoupper($from_encoding) === 'TIS-620' || strtoupper($from_encoding) === 'ISO-8859-11' || $from_encoding === null)) {
            $out = '';
            $len = strlen($val);
            for ($i = 0; $i < $len; $i++) {
                $c = ord($val[$i]);
                if ($c >= 0x00 && $c <= 0x7F) {
                    $out .= chr($c);
                } elseif ($c >= 0xA1 && $c <= 0xDF) {
                    $out .= chr(0xE0) . chr(0xB8) . chr(0x80 + ($c - 0xA0));
                } elseif ($c >= 0xE0 && $c <= 0xFB) {
                    $out .= chr(0xE0) . chr(0xB9) . chr(0x80 + ($c - 0xE0));
                } else {
                    $out .= ' ';
                }
            }
            return $out;
        }
        return $val;
    }
}

if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle, $offset = 0, $encoding = null) {
        return strpos($haystack, $needle, $offset);
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null) {
        return strlen($string);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null) {
        return substr($string, $start, $length);
    }
}

// Detect environment: use local settings if accessed via localhost/127.0.0.1 or if running locally on Windows
$is_local = false;
if (isset($_SERVER['HTTP_HOST'])) {
    $host_lower = strtolower($_SERVER['HTTP_HOST']);
    if (strpos($host_lower, 'localhost') !== false || strpos($host_lower, '127.0.0.1') !== false) {
        $is_local = true;
    }
} elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $is_local = true;
}

if ($is_local) {
    $host = '127.0.0.1';
    $port = '3333';
} else {
    $host = 'localhost';
    $port = '';
}

$db   = 'tansum_ncd';
$user = 'tansum_ncd';
$pass = 'Prevention2026';
$charset = 'utf8mb4';

if (!empty($port)) {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
} else {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Auto-create line_house_mappings table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `line_house_mappings` (
      `line_user_id` VARCHAR(100) NOT NULL,
      `hid` VARCHAR(15) NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`line_user_id`, `hid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (\PDOException $e) {
    // Fail silently or handle
}

// Auto-create dpac_enrollments table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `dpac_enrollments` (
        `enrollment_id` INT AUTO_INCREMENT PRIMARY KEY,
        `cid` VARCHAR(13) NOT NULL,
        `budget_year` INT NOT NULL,
        `risk_type` ENUM('DM', 'HT', 'BOTH') NOT NULL,
        `assigned_vhv_id` VARCHAR(20) DEFAULT NULL,
        `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `status` ENUM('active', 'completed', 'dropped') DEFAULT 'active'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $check = $pdo->query("SHOW COLUMNS FROM `dpac_enrollments` LIKE 'assigned_vhv_id'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_enrollments` ADD COLUMN `assigned_vhv_id` VARCHAR(20) DEFAULT NULL AFTER `risk_type`");
    }
} catch (\PDOException $e) {
    // Fail silently or handle
}

// Auto-create dpac_followups table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `dpac_followups` (
        `followup_id` INT AUTO_INCREMENT PRIMARY KEY,
        `enrollment_id` INT NOT NULL,
        `vhv_id` VARCHAR(20) NOT NULL,
        `round_number` INT NOT NULL DEFAULT 1,
        `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `status` ENUM('pending', 'completed') DEFAULT 'pending',
        `completed_at` TIMESTAMP NULL,
        `weight` DECIMAL(5,2),
        `height` DECIMAL(5,2),
        `waist` DECIMAL(5,2),
        `bp_sys` INT,
        `bp_dia` INT,
        `fbs` INT,
        `health_risk_level` VARCHAR(20),
        `advice_given` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (\PDOException $e) {
    // Fail silently or handle
}

// Auto-migration: Add approved column to vhv_users if it doesn't exist
try {
    $check = $pdo->query("SHOW COLUMNS FROM `vhv_users` LIKE 'approved'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `vhv_users` ADD COLUMN `approved` TINYINT(1) NOT NULL DEFAULT 0");
        // Pre-approve existing seed/current users
        $pdo->exec("UPDATE `vhv_users` SET `approved` = 1");
    }
} catch (\PDOException $e) {
    // Fail silently
}

// Auto-create admin_users table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_users` (
        `username` VARCHAR(50) PRIMARY KEY,
        `password_hash` VARCHAR(255) NOT NULL,
        `hoscode` VARCHAR(10) DEFAULT NULL,
        `admin_name` VARCHAR(100) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Seed default admin accounts if empty
    $count = $pdo->query("SELECT COUNT(*) FROM `admin_users`")->fetchColumn();
    if ($count == 0) {
        $defaultPasswordHash = password_hash('Prevention2026', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `admin_users` (username, password_hash, hoscode, admin_name) VALUES (?, ?, ?, ?)");
        
        // Main Admin
        $stmt->execute(['admin', $defaultPasswordHash, null, 'ผู้ดูแลระบบหลัก']);
        
        // Sub-admins
        $subAdmins = [
            '10957' => 'โรงพยาบาลตาลสุม',
            '03751' => 'รพ.สต.ดอนพันชาด',
            '03752' => 'รพ.สต.บ้านสำโรง',
            '03753' => 'รพ.สต.บ้านจิกเทิง',
            '03754' => 'รพ.สต.บ้านหนองกุงใหญ่',
            '03755' => 'รพ.สต.นาคาย',
            '03756' => 'รพ.สต.คำหนามแท่ง',
            '03757' => 'รพ.สต.คำหว้า'
        ];
        
        foreach ($subAdmins as $hcode => $name) {
            $stmt->execute(['admin' . $hcode, $defaultPasswordHash, $hcode, 'แอดมิน ' . $name]);
        }
    }
} catch (\PDOException $e) {
    // Fail silently
}

// Auto-migration: Add missing columns to staging_hdc_dm and staging_hdc_ht
try {
    $dmCols = [
        'discharge' => "VARCHAR(5) NULL",
        'date_screen' => "DATE NULL",
        'bstest' => "VARCHAR(50) NULL",
        'bslevel' => "INT NULL",
        'hosp_screen' => "VARCHAR(10) NULL",
        'hosp_input' => "VARCHAR(10) NULL",
        'providername' => "VARCHAR(255) NULL",
        'nation' => "VARCHAR(5) NULL"
    ];
    foreach ($dmCols as $col => $def) {
        $check = $pdo->query("SHOW COLUMNS FROM `staging_hdc_dm` LIKE '$col'");
        if ($check->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `staging_hdc_dm` ADD COLUMN `$col` $def");
        }
    }
} catch (\PDOException $e) {
    // Fail silently
}

try {
    $htCols = [
        'nation' => "VARCHAR(5) NULL"
    ];
    foreach ($htCols as $col => $def) {
        $check = $pdo->query("SHOW COLUMNS FROM `staging_hdc_ht` LIKE '$col'");
        if ($check->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `staging_hdc_ht` ADD COLUMN `$col` $def");
        }
    }
} catch (\PDOException $e) {
    // Fail silently
}

// Auto-create jhcis_homes table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `jhcis_homes` (
        `hoscode` VARCHAR(10) NOT NULL,
        `hid` VARCHAR(15) NOT NULL,
        `house_no` VARCHAR(50) DEFAULT NULL,
        `vhid_code` VARCHAR(20) DEFAULT NULL,
        `latitude` DECIMAL(10,7) DEFAULT NULL,
        `longitude` DECIMAL(10,7) DEFAULT NULL,
        PRIMARY KEY (`hoscode`, `hid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (\PDOException $e) {
    // Fail silently
}
