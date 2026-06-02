<?php
// config/db.php

// ==========================================
// Visitor Mode: Data Masking & Security Interceptor
// ==========================================

if (!function_exists('maskRowData')) {
    function maskRowData(&$row)
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['is_visitor']) && $_SESSION['is_visitor'] === true) {
            if ($row === null) return;
            
            $nameKeys = ['first_name', 'last_name', 'vhv_name', 'admin_name'];
            $cidKeys = ['cid', 'target_cid', 'vhv_id'];
            $telKeys = ['tel', 'telephone', 'phone_number'];
            
            if (is_array($row)) {
                foreach ($row as $key => $val) {
                    if ($val === null || $val === '') continue;
                    
                    if (in_array($key, $nameKeys)) {
                        $valStr = trim((string)$val);
                        $len = mb_strlen($valStr);
                        if ($len <= 2) {
                            $row[$key] = mb_substr($valStr, 0, 1) . '*';
                        } else {
                            $row[$key] = mb_substr($valStr, 0, 2) . str_repeat('*', min(4, $len - 2));
                        }
                    } elseif (in_array($key, $cidKeys)) {
                        $valStr = trim((string)$val);
                        if (strlen($valStr) === 13) {
                            $row[$key] = substr($valStr, 0, 3) . '-XXXX-XXXX-' . substr($valStr, -2);
                        } else {
                            $row[$key] = substr($valStr, 0, min(3, strlen($valStr))) . str_repeat('X', min(5, max(0, strlen($valStr) - 5))) . substr($valStr, -2);
                        }
                    } elseif (in_array($key, $telKeys)) {
                        $valStr = trim((string)$val);
                        if (strlen($valStr) >= 9) {
                            $row[$key] = substr($valStr, 0, 3) . '-XXX-' . substr($valStr, -3);
                        } else {
                            $row[$key] = substr($valStr, 0, 3) . 'XXX';
                        }
                    }
                }
            } elseif (is_object($row)) {
                foreach ($nameKeys as $key) {
                    if (isset($row->$key) && $row->$key !== null && $row->$key !== '') {
                        $valStr = trim((string)$row->$key);
                        $len = mb_strlen($valStr);
                        if ($len <= 2) {
                            $row->$key = mb_substr($valStr, 0, 1) . '*';
                        } else {
                            $row->$key = mb_substr($valStr, 0, 2) . str_repeat('*', min(4, $len - 2));
                        }
                    }
                }
                foreach ($cidKeys as $key) {
                    if (isset($row->$key) && $row->$key !== null && $row->$key !== '') {
                        $valStr = trim((string)$row->$key);
                        if (strlen($valStr) === 13) {
                            $row->$key = substr($valStr, 0, 3) . '-XXXX-XXXX-' . substr($valStr, -2);
                        } else {
                            $row->$key = substr($valStr, 0, min(3, strlen($valStr))) . str_repeat('X', min(5, max(0, strlen($valStr) - 5))) . substr($valStr, -2);
                        }
                    }
                }
                foreach ($telKeys as $key) {
                    if (isset($row->$key) && $row->$key !== null && $row->$key !== '') {
                        $valStr = trim((string)$row->$key);
                        if (strlen($valStr) >= 9) {
                            $row->$key = substr($valStr, 0, 3) . '-XXX-' . substr($valStr, -3);
                        } else {
                            $row->$key = substr($valStr, 0, 3) . 'XXX';
                        }
                    }
                }
            }
        }
    }
}

if (!class_exists('VisitorMaskPDOStatement')) {
    class VisitorMaskPDOStatement extends PDOStatement
    {
        protected $pdo;
        protected function __construct($pdo)
        {
            $this->pdo = $pdo;
        }

        public function fetch($mode = null, $cursorOrientation = null, $cursorOffset = null)
        {
            if ($mode === null) {
                $row = parent::fetch();
            } else {
                $row = parent::fetch($mode, $cursorOrientation, $cursorOffset);
            }
            if ($row !== false && $row !== null) {
                maskRowData($row);
            }
            return $row;
        }

        public function fetchAll($mode = null, ...$args)
        {
            if ($mode === null) {
                $rows = parent::fetchAll();
            } else {
                $rows = parent::fetchAll($mode, ...$args);
            }
            if (is_array($rows)) {
                foreach ($rows as &$row) {
                    maskRowData($row);
                }
            }
            return $rows;
        }
    }
}

// Visitor Security Interceptor: Block DB Modification Requests (POST or GET destructive parameters)
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['is_visitor']) && $_SESSION['is_visitor'] === true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['reset']) || (isset($_GET['action']) && in_array($_GET['action'], ['delete', 'reset', 'clear', 'remove', 'seed', 'approve', 'reject', 'disapprove']))) {
        // Check if AJAX request (JSON format expected)
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
                  (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => 'ผู้มาเยือน (Visitor) ไม่สามารถเพิ่ม แก้ไข หรือลบข้อมูลได้'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        } else {
            echo "<script>
                alert('ผู้มาเยือน (Visitor) ไม่สามารถเพิ่ม แก้ไข หรือลบข้อมูลได้');
                window.history.back();
            </script>";
            exit();
        }
    }
}

// Fallbacks and safe multibyte functions for environments with restricted encoding support
if (!function_exists('safe_is_utf8')) {
    function safe_is_utf8($str)
    {
        if ($str === null)
            return true;
        return preg_match('//u', (string) $str) === 1;
    }
}

if (!function_exists('safe_tis620_to_utf8')) {
    function safe_tis620_to_utf8($val)
    {
        if ($val === null)
            return '';
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
    function mb_check_encoding($var = null, $encoding = null)
    {
        if ($var === null)
            return true;
        return preg_match('//u', $var) === 1;
    }
}

if (!function_exists('mb_convert_encoding')) {
    function mb_convert_encoding($val, $to_encoding, $from_encoding = null)
    {
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
    function mb_strpos($haystack, $needle, $offset = 0, $encoding = null)
    {
        return strpos($haystack, $needle, $offset);
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null)
    {
        return strlen($string);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null)
    {
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

$db = 'tansum_ncd';
$user = 'tansum_ncd';
$pass = 'Prevention2026';
$charset = 'utf8mb4';

if (!empty($port)) {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
} else {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['VisitorMaskPDOStatement', [$pdo]]);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int) $e->getCode());
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


// Auto-migration: Add advice_given column to screening_results if it doesn't exist
try {
    $check = $pdo->query("SHOW COLUMNS FROM `screening_results` LIKE 'advice_given'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `screening_results` ADD COLUMN `advice_given` TEXT DEFAULT NULL");
    }
} catch (\PDOException $e) {
    // Fail silently
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

// Auto-migration: Add is_hl_coach column to vhv_users if it doesn't exist
try {
    $check = $pdo->query("SHOW COLUMNS FROM `vhv_users` LIKE 'is_hl_coach'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `vhv_users` ADD COLUMN `is_hl_coach` TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (\PDOException $e) {
    // Fail silently
}

// Auto-migration: Update vhv_rewards table columns for DPAC followup rewards
try {
    // Make screening_id nullable in vhv_rewards
    $pdo->exec("ALTER TABLE `vhv_rewards` MODIFY COLUMN `screening_id` INT NULL");

    // Add followup_id column to vhv_rewards if not exists
    $check = $pdo->query("SHOW COLUMNS FROM `vhv_rewards` LIKE 'followup_id'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `vhv_rewards` ADD COLUMN `followup_id` INT NULL AFTER `screening_id`");
    }

    // Retroactively backfill missing rewards for completed screenings
    $pdo->exec("
        INSERT INTO vhv_rewards (vhv_id, screening_id, points_earned, approval_status, approved_at, created_at)
        SELECT a.vhv_id, s.screening_id, 1, 'approved', s.created_at, s.created_at
        FROM screening_results s
        JOIN task_assignments a ON s.assignment_id = a.assignment_id
        LEFT JOIN vhv_rewards r ON s.screening_id = r.screening_id
        WHERE r.reward_id IS NULL
    ");

    // Retroactively backfill missing rewards for completed DPAC followups
    $pdo->exec("
        INSERT INTO vhv_rewards (vhv_id, followup_id, points_earned, approval_status, approved_at, created_at)
        SELECT f.vhv_id, f.followup_id, 1, 'approved', f.completed_at, f.completed_at
        FROM dpac_followups f
        LEFT JOIN vhv_rewards r ON f.followup_id = r.followup_id
        WHERE f.status = 'completed' AND r.reward_id IS NULL
    ");
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

    // Seed adminsso if not exists
    $checkSso = $pdo->prepare("SELECT COUNT(*) FROM `admin_users` WHERE username = ?");
    $checkSso->execute(['adminsso']);
    if ($checkSso->fetchColumn() == 0) {
        $ssoPasswordHash = password_hash('123456', PASSWORD_DEFAULT);
        $insertSso = $pdo->prepare("INSERT INTO `admin_users` (username, password_hash, hoscode, admin_name) VALUES (?, ?, ?, ?)");
        $insertSso->execute(['adminsso', $ssoPasswordHash, null, 'ผู้รับผิดชอบงานระดับอำเภอ']);
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

// Auto-migration: Add database performance indexes
try {
    // staging_hdc_dm indexes
    $idxCheck = $pdo->query("SHOW INDEX FROM `staging_hdc_dm` WHERE Key_name = 'idx_staging_dm_cid'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `staging_hdc_dm` ADD INDEX `idx_staging_dm_cid` (`cid`)");
    }
} catch (\PDOException $e) {
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM `staging_hdc_dm` WHERE Key_name = 'idx_staging_dm_hos_pid'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `staging_hdc_dm` ADD INDEX `idx_staging_dm_hos_pid` (`hoscode`, `pid`)");
    }
} catch (\PDOException $e) {
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM `staging_hdc_dm` WHERE Key_name = 'idx_staging_dm_check_vhid'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `staging_hdc_dm` ADD INDEX `idx_staging_dm_check_vhid` (`check_vhid`)");
    }
} catch (\PDOException $e) {
}

try {
    // staging_hdc_ht indexes
    $idxCheck = $pdo->query("SHOW INDEX FROM `staging_hdc_ht` WHERE Key_name = 'idx_staging_ht_cid'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `staging_hdc_ht` ADD INDEX `idx_staging_ht_cid` (`cid`)");
    }
} catch (\PDOException $e) {
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM `staging_hdc_ht` WHERE Key_name = 'idx_staging_ht_hos_pid'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `staging_hdc_ht` ADD INDEX `idx_staging_ht_hos_pid` (`hoscode`, `pid`)");
    }
} catch (\PDOException $e) {
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM `staging_hdc_ht` WHERE Key_name = 'idx_staging_ht_check_vhid'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `staging_hdc_ht` ADD INDEX `idx_staging_ht_check_vhid` (`check_vhid`)");
    }
} catch (\PDOException $e) {
}

try {
    // target_population indexes
    $idxCheck = $pdo->query("SHOW INDEX FROM `target_population` WHERE Key_name = 'idx_target_hos_pid'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `target_population` ADD INDEX `idx_target_hos_pid` (`hoscode`, `pid`)");
    }
} catch (\PDOException $e) {
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM `target_population` WHERE Key_name = 'idx_target_vhid'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `target_population` ADD INDEX `idx_target_vhid` (`vhid_code`)");
    }
} catch (\PDOException $e) {
}

try {
    // vhv_users index
    $idxCheck = $pdo->query("SHOW INDEX FROM `vhv_users` WHERE Key_name = 'idx_vhv_approved'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `vhv_users` ADD INDEX `idx_vhv_approved` (`approved`)");
    }
} catch (\PDOException $e) {
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

if (!function_exists('get_village_only_name')) {
    function get_village_only_name($vhid_code, $moo)
    {
        $vhid_code = trim((string) $vhid_code);
        $tambon = substr($vhid_code, 0, 6);
        if (empty($tambon) || strlen($tambon) < 6) {
            $admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
            $hoscode_tambons = [
                '10957' => '341801',
                '03751' => '341801',
                '03752' => '341802',
                '03753' => '341803',
                '03754' => '341804',
                '03755' => '341805',
                '03756' => '341805',
                '03757' => '341806'
            ];
            if ($admin_hoscode && isset($hoscode_tambons[$admin_hoscode])) {
                $tambon = $hoscode_tambons[$admin_hoscode];
            }
        }
        $moo = intval($moo);

        $villages = [
            '341801' => [
                1 => 'บ้านม่วงโคน',
                2 => 'บ้านดอนรังกา',
                3 => 'บ้านนาห้วยแคน',
                4 => 'บ้านดอนพันชาด',
                5 => 'บ้านนามน',
                6 => 'บ้านดอนตะลี',
                7 => 'บ้านปากห้วย',
                8 => 'บ้านโนนค้อ',
                9 => 'บ้านแก่งกบ',
                10 => 'บ้านนามน',
                11 => 'บ้านตาลสุม',
                12 => 'บ้านคำไม้ตาย',
                13 => 'บ้านปากเซ',
                14 => 'บ้านโนนสวรรค์',
                15 => 'บ้านทุ่งเจริญ'
            ],
            '341802' => [
                1 => 'บ้านสำโรงใหญ่',
                2 => 'บ้านสำโรงกลาง',
                3 => 'บ้านนาโพธิ์',
                4 => 'บ้านสำโรงใต้',
                5 => 'บ้านนาแพง',
                6 => 'บ้านหนองโน',
                7 => 'บ้านหนองสะเดา',
                8 => 'บ้านทุ่งเจริญ'
            ],
            '341803' => [
                1 => 'บ้านจิกเทิง',
                2 => 'บ้านจิกลุ่ม',
                3 => 'บ้านเชียงแก้ว',
                4 => 'บ้านเชียงแก้ว',
                5 => 'บ้านดอนโด่',
                6 => 'บ้านดอนยูง',
                7 => 'บ้านค้อ',
                8 => 'บ้านดอนแป้นลม',
                9 => 'บ้านสร้างคำ'
            ],
            '341804' => [
                1 => 'บ้านหนองกุงใหญ่',
                2 => 'บ้านหนองกุงน้อย',
                3 => 'บ้านคำแคน',
                4 => 'บ้านสร้างแสง',
                5 => 'บ้านคำเตยใต้',
                6 => 'บ้านสร้างหว้า',
                7 => 'บ้านคำเตยเหนือ',
                8 => 'บ้านสร้างหว้าพัฒนา'
            ],
            '341805' => [
                1 => 'บ้านนาคาย',
                2 => 'บ้านโนนจิก',
                3 => 'บ้านหนองเป็ด',
                4 => 'บ้านโนนยาง',
                5 => 'บ้านดอนขวาง',
                6 => 'บ้านดอนหวาย',
                7 => 'บ้านโคกคล้าย',
                8 => 'บ้านคำหนามแท่ง',
                9 => 'บ้านคำผักหนอก',
                10 => 'บ้านคำฮี',
                11 => 'บ้านห่องแดง',
                12 => 'บ้านโนนสำราญ',
                13 => 'บ้านโนนเจริญ'
            ],
            '341806' => [
                1 => 'บ้านคำหว้า',
                2 => 'บ้านคำหว้า',
                3 => 'บ้านห้วยดู่',
                4 => 'บ้านนาทมเหนือ',
                5 => 'บ้านไฮหย่อง',
                6 => 'บ้านนาทมใต้'
            ]
        ];

        return $villages[$tambon][$moo] ?? "";
    }
}

if (!function_exists('get_village_display_name')) {
    function get_village_display_name($vhid_code, $moo)
    {
        $vname = get_village_only_name($vhid_code, $moo);
        $moo_val = intval($moo);
        if (empty($vname) || strpos($vname, 'หมู่ที่') === 0 || strpos($vname, 'หมู่ ') === 0) {
            return "หมู่ " . $moo_val;
        }
        return "หมู่ " . $moo_val . " " . $vname;
    }
}

// Dynamic village-hospital mapping loader
$hoscode_villages = [
    '10957' => [
        'tambon' => '341801',
        'villages' => [
            1 => 'บ้านม่วงโคน',
            2 => 'บ้านดอนรังกา',
            3 => 'บ้านนาห้วยแคน (เขตเทศบาล)',
            5 => 'บ้านนามน (เขตเทศบาล)',
            10 => 'บ้านนามน (เขตเทศบาล)',
            11 => 'บ้านตาลสุม (เขตเทศบาล)',
            12 => 'บ้านคำไม้ตาย',
            13 => 'บ้านปากเซ'
        ]
    ],
    '03751' => [
        'tambon' => '341801',
        'villages' => [
            4 => 'บ้านดอนพันชาด',
            6 => 'บ้านดอนตะลี',
            7 => 'บ้านปากห้วย',
            8 => 'บ้านโนนค้อ',
            9 => 'บ้านแก่งกบ',
            14 => 'บ้านโนนสวรรค์',
            15 => 'บ้านทุ่งเจริญ'
        ]
    ],
    '03752' => [
        'tambon' => '341802',
        'villages' => [
            1 => 'บ้านสำโรงใหญ่',
            2 => 'บ้านสำโรงกลาง',
            3 => 'บ้านนาโพธิ์',
            4 => 'บ้านสำโรงใต้',
            5 => 'บ้านนาแพง',
            6 => 'บ้านหนองโน',
            7 => 'บ้านหนองสะเดา',
            8 => 'บ้านทุ่งเจริญ'
        ]
    ],
    '03753' => [
        'tambon' => '341803',
        'villages' => [
            1 => 'บ้านจิกเทิง',
            2 => 'บ้านจิกลุ่ม',
            3 => 'บ้านเชียงแก้ว',
            4 => 'บ้านเชียงแก้ว',
            5 => 'บ้านดอนโด่ (บ้านดอนโต)',
            6 => 'บ้านดอนยูง',
            7 => 'บ้านค้อ',
            8 => 'บ้านดอนแป้นลม',
            9 => 'บ้านสร้างคำ'
        ]
    ],
    '03754' => [
        'tambon' => '341804',
        'villages' => [
            1 => 'บ้านหนองกุงใหญ่',
            2 => 'บ้านหนองกุงน้อย',
            3 => 'บ้านคำแคน',
            4 => 'บ้านสร้างแสง',
            5 => 'บ้านคำเตยใต้',
            6 => 'บ้านสร้างหว้า',
            7 => 'บ้านคำเตยเหนือ',
            8 => 'บ้านสร้างหว้าพัฒนา'
        ]
    ],
    '03755' => [
        'tambon' => '341805',
        'villages' => [
            1 => 'บ้านนาคาย',
            2 => 'บ้านโนนจิก',
            3 => 'บ้านหนองเป็ด',
            4 => 'บ้านโนนยาง',
            5 => 'บ้านดอนขวาง',
            6 => 'บ้านดอนหวาย'
        ]
    ],
    '03756' => [
        'tambon' => '341805',
        'villages' => [
            7 => 'บ้านโคกคล้าย',
            8 => 'บ้านคำหนามแท่ง',
            9 => 'บ้านคำผักหนอก',
            10 => 'บ้านคำฮี',
            11 => 'บ้านห่องแดง',
            12 => 'บ้านโนนสำราญ',
            13 => 'บ้านโนนเจริญ'
        ]
    ],
    '03757' => [
        'tambon' => '341806',
        'villages' => [
            1 => 'บ้านคำหว้า',
            2 => 'บ้านคำหว้า',
            3 => 'บ้านห้วยดู่',
            4 => 'บ้านนาทมเหนือ',
            5 => 'บ้านไฮหย่อง',
            6 => 'บ้านนาทมใต้'
        ]
    ]
];

// Complete/update mapping dynamically from database
if (isset($pdo)) {
    try {
        $stmt_map = $pdo->query("
            SELECT DISTINCT hoscode, sub_district_code, moo 
            FROM target_population 
            WHERE hoscode IS NOT NULL AND sub_district_code IS NOT NULL AND moo IS NOT NULL 
              AND hoscode != '' AND sub_district_code != '' AND moo != ''
        ");
        while ($row = $stmt_map->fetch(PDO::FETCH_ASSOC)) {
            $hc = trim($row['hoscode']);
            $sub = trim($row['sub_district_code']);
            $m = intval($row['moo']);

            if (!isset($hoscode_villages[$hc])) {
                $hoscode_villages[$hc] = [
                    'tambon' => $sub,
                    'villages' => []
                ];
            }
            if (!isset($hoscode_villages[$hc]['villages'][$m])) {
                $vname = get_village_only_name($sub, $m);
                if ($vname) {
                    $hoscode_villages[$hc]['villages'][$m] = $vname;
                }
            }
        }
    } catch (\Exception $e) {
        // Fail silently
    }
}

if (!function_exists('get_village_display_name_by_hoscode')) {
    function get_village_display_name_by_hoscode($hoscode, $moo)
    {
        global $hoscode_villages;
        $moo_val = intval($moo);

        if (!empty($hoscode) && isset($hoscode_villages[$hoscode]['villages'][$moo_val])) {
            return "หมู่ " . $moo_val . " " . $hoscode_villages[$hoscode]['villages'][$moo_val];
        }

        $tambon = isset($hoscode_villages[$hoscode]['tambon']) ? $hoscode_villages[$hoscode]['tambon'] : null;
        if ($tambon) {
            $vname = get_village_only_name($tambon, $moo_val);
            if ($vname) {
                return "หมู่ " . $moo_val . " " . $vname;
            }
        }

        return "หมู่ " . $moo_val;
    }
}




