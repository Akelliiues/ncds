<?php
// config/db.php
require_once __DIR__ . '/line_config.php';
require_once __DIR__ . '/icons.php';

// ==========================================
// Executive / Visitor Mode: Enhanced PDPA Masking & Security Interceptor
// ==========================================

if (!function_exists('isExecutiveMode')) {
    function isExecutiveMode()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return false;
        return (!empty($_SESSION['is_executive']) || !empty($_SESSION['is_visitor']) || (isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['executive', 'viewer', 'auditor'])));
    }
}

if (!function_exists('maskRowData')) {
    function maskRowData(&$row)
    {
        if (isExecutiveMode()) {
            if ($row === null) return;
            
            $nameKeys = [
                'first_name', 'last_name', 'vhv_name', 'admin_name', 'patient_name',
                'fullname', 'head_name', 'name', 'fname', 'lname', 'reporter_name',
                'assessor_name', 'user_fullname', 'creator_name', 'updater_name', 'user_name'
            ];
            $cidKeys = [
                'cid', 'target_cid', 'vhv_id', 'pid', 'person_id', 'idcard', 'id_card',
                'patient_cid', 'citizen_id', 'id_card_no', 'leader_cid'
            ];
            $telKeys = [
                'tel', 'telephone', 'phone_number', 'phone', 'mobile', 'contact_tel',
                'contact_phone', 'tel_no', 'vhv_phone', 'patient_tel'
            ];
            $houseKeys = [
                'house_no', 'hno', 'house_number', 'address', 'addr', 'home_no', 'house', 'residence_no'
            ];
            $geoKeys = [
                'latitude', 'longitude', 'lat', 'lng', 'screening_lat', 'screening_lng', 'gps_lat', 'gps_lng', 'home_lat', 'home_lng'
            ];
            
            $prefixes = ['นาย', 'นาง', 'น.ส.', 'นางสาว', 'ด.ช.', 'ด.ญ.', 'ดร.', 'นพ.', 'พญ.', 'ทพ.', 'ภก.', 'อสม.'];

            $maskThaiWord = function ($w) use ($prefixes) {
                $w = trim($w);
                if ($w === '') return '';
                if (in_array($w, $prefixes)) return $w;
                $len = mb_strlen($w, 'UTF-8');
                if ($len <= 2) {
                    return mb_substr($w, 0, 1, 'UTF-8') . '*';
                } elseif ($len <= 4) {
                    return mb_substr($w, 0, 2, 'UTF-8') . '**';
                } else {
                    return mb_substr($w, 0, 2, 'UTF-8') . '***';
                }
            };

            $maskNameVal = function ($valStr) use ($maskThaiWord) {
                $words = preg_split('/\s+/', trim((string)$valStr));
                if (empty($words)) return $valStr;
                $maskedWords = array_map($maskThaiWord, $words);
                return implode(' ', $maskedWords);
            };

            $maskCidVal = function ($valStr) {
                $digits = preg_replace('/\D/', '', (string)$valStr);
                if (strlen($digits) === 13) {
                    return substr($digits, 0, 1) . '-' . substr($digits, 1, 2) . 'XX-XXXXX-XX-' . substr($digits, -1);
                } elseif (strlen($valStr) > 4) {
                    return substr($valStr, 0, min(3, strlen($valStr))) . '******' . substr($valStr, -2);
                }
                return $valStr;
            };

            $maskTelVal = function ($valStr) {
                $digits = preg_replace('/\D/', '', (string)$valStr);
                if (strlen($digits) === 10) {
                    return substr($digits, 0, 3) . '-XXX-' . substr($digits, -3);
                } elseif (strlen($digits) === 9) {
                    return substr($digits, 0, 2) . '-XXX-' . substr($digits, -3);
                } elseif (strlen($valStr) >= 4) {
                    return substr($valStr, 0, 3) . '***' . substr($valStr, -2);
                }
                return $valStr;
            };

            $maskHouseVal = function ($valStr) {
                if ($valStr === null || trim((string)$valStr) === '') return $valStr;
                return '***';
            };

            $maskGeoVal = function ($valStr) {
                if ($valStr === null || trim((string)$valStr) === '' || (float)$valStr == 0) return $valStr;
                // Truncate to 2 decimal places (~1.1 km grid resolution) to obscure individual house position
                return round(floatval($valStr), 2);
            };

            if (is_array($row)) {
                foreach ($row as $key => $val) {
                    if ($val === null || $val === '') continue;
                    $keyLower = strtolower($key);
                    if (in_array($keyLower, $nameKeys)) {
                        $row[$key] = $maskNameVal((string)$val);
                    } elseif (in_array($keyLower, $cidKeys)) {
                        $row[$key] = $maskCidVal((string)$val);
                    } elseif (in_array($keyLower, $telKeys)) {
                        $row[$key] = $maskTelVal((string)$val);
                    } elseif (in_array($keyLower, $houseKeys)) {
                        $row[$key] = $maskHouseVal((string)$val);
                    } elseif (in_array($keyLower, $geoKeys)) {
                        $row[$key] = $maskGeoVal((string)$val);
                    }
                }
            } elseif (is_object($row)) {
                foreach ($nameKeys as $key) {
                    if (isset($row->$key) && $row->$key !== null && $row->$key !== '') {
                        $row->$key = $maskNameVal((string)$row->$key);
                    }
                }
                foreach ($cidKeys as $key) {
                    if (isset($row->$key) && $row->$key !== null && $row->$key !== '') {
                        $row->$key = $maskCidVal((string)$row->$key);
                    }
                }
                foreach ($telKeys as $key) {
                    if (isset($row->$key) && $row->$key !== null && $row->$key !== '') {
                        $row->$key = $maskTelVal((string)$row->$key);
                    }
                }
                foreach ($houseKeys as $key) {
                    if (isset($row->$key) && $row->$key !== null && $row->$key !== '') {
                        $row->$key = $maskHouseVal((string)$row->$key);
                    }
                }
                foreach ($geoKeys as $key) {
                    if (isset($row->$key) && $row->$key !== null && $row->$key !== '') {
                        $row->$key = $maskGeoVal((string)$row->$key);
                    }
                }
            }
        }
    }
}

if (!class_exists('VisitorMaskPDOStatement')) {
    if (PHP_VERSION_ID >= 80000) {
        eval('
            class VisitorMaskPDOStatement extends PDOStatement
            {
                protected $pdo;
                protected function __construct($pdo)
                {
                    $this->pdo = $pdo;
                }

                #[\ReturnTypeWillChange]
                public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
                {
                    $args = func_get_args();
                    $row = parent::fetch(...$args);
                    if ($row !== false && $row !== null) {
                        maskRowData($row);
                    }
                    return $row;
                }

                #[\ReturnTypeWillChange]
                public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
                {
                    $args = func_get_args();
                    $rows = parent::fetchAll(...$args);
                    if (is_array($rows)) {
                        foreach ($rows as &$row) {
                            maskRowData($row);
                        }
                    }
                    return $rows;
                }
            }
        ');
    } else {
        eval('
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

                public function fetchAll($mode = null, $className = null, $ctorArgs = null)
                {
                    if ($mode === null) {
                        $rows = parent::fetchAll();
                    } elseif ($ctorArgs !== null) {
                        $rows = parent::fetchAll($mode, $className, $ctorArgs);
                    } elseif ($className !== null) {
                        $rows = parent::fetchAll($mode, $className);
                    } else {
                        $rows = parent::fetchAll($mode);
                    }
                    if (is_array($rows)) {
                        foreach ($rows as &$row) {
                            maskRowData($row);
                        }
                    }
                    return $rows;
                }
            }
        ');
    }
}

// Executive / Visitor Security Interceptor: Block DB Modification Requests (POST or GET destructive parameters)
if (function_exists('isExecutiveMode') && isExecutiveMode()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['reset']) || (isset($_GET['action']) && in_array($_GET['action'], ['delete', 'reset', 'clear', 'remove', 'seed', 'approve', 'reject', 'disapprove', 'sync', 'etl', 'save', 'update']))) {
        // Check if AJAX request (JSON format expected)
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
                  (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => '🔒 โหมดผู้บริหาร (Executive Read-Only): สำหรับการเข้าชมและตรวจสอบระบบ ข้อมูลส่วนบุคคลจะถูกปกปิดตามหลัก PDPA และไม่สามารถประมวลผลหรือแก้ไขข้อมูลได้'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        } else {
            echo "<script>
                alert('🔒 โหมดผู้บริหาร (Executive Read-Only):\\nสำหรับการเข้าชมและตรวจสอบระบบ ข้อมูลส่วนบุคคลจะถูกปกปิดตามหลัก PDPA และไม่สามารถประมวลผลหรือแก้ไขข้อมูลได้');
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

// Default database configurations (fallback settings)
$db_config = [
    'local' => [
        'host' => '127.0.0.1',
        'port' => '3333',
        'db' => 'tansum_ncd',
        'user' => 'tansum_ncd',
        'pass' => 'Prevention2026',
    ],
    'production' => [
        'host' => 'localhost',
        'port' => '',
        'db' => 'tansum_ncd',
        'user' => 'tansum_ncd',
        'pass' => 'Prevention2026',
    ]
];

// Load external config if available
$config_file = __DIR__ . '/db_config.php';
if (file_exists($config_file)) {
    $loaded_config = require $config_file;
    if (is_array($loaded_config)) {
        $db_config = array_replace_recursive($db_config, $loaded_config);
    }
}

$env = $is_local ? 'local' : 'production';
$host = $db_config[$env]['host'] ?? 'localhost';
$port = $db_config[$env]['port'] ?? '';
$db = $db_config[$env]['db'] ?? 'tansum_ncd';
$user = $db_config[$env]['user'] ?? 'tansum_ncd';
$pass = $db_config[$env]['pass'] ?? 'Prevention2026';
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

require_once __DIR__ . '/demo_database.php';

$pdo = null;
$connectError = null;
$portsToTry = !empty($port) ? [$port] : [''];
if ($is_local) {
    foreach (['3307', '3306', '3333'] as $fallbackPort) {
        if (!in_array($fallbackPort, $portsToTry)) {
            $portsToTry[] = $fallbackPort;
        }
    }
}

$usersToTry = [[$user, $pass]];
if ($is_local && ($user !== 'root' || $pass !== '')) {
    $usersToTry[] = ['root', ''];
}

foreach ($portsToTry as $pTry) {
    foreach ($usersToTry as $uCred) {
        $uName = $uCred[0];
        $uPass = $uCred[1];
        try {
            if (!empty($pTry)) {
                $dsnTry = "mysql:host=$host;port=$pTry;dbname=$db;charset=$charset";
            } else {
                $dsnTry = "mysql:host=$host;dbname=$db;charset=$charset";
            }

            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['is_demo_mode']) && $_SESSION['is_demo_mode'] === true) {
                $pdo = new DemoMockPDO($dsnTry, $uName, $uPass, $options);
                initDemoMockupDatabase($pdo);
            } else {
                $pdo = new PDO($dsnTry, $uName, $uPass, $options);
                $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['VisitorMaskPDOStatement', [$pdo]]);
            }
            $connectError = null;
            break 2; // Connected successfully
        } catch (\PDOException $e) {
            $connectError = $e;
        }
    }
}

if ($pdo === null && $connectError !== null) {
    global $allow_db_failure;
    if (php_sapi_name() === 'cli' || (isset($allow_db_failure) && $allow_db_failure === true)) {
        throw new \PDOException($connectError->getMessage(), (int) $connectError->getCode());
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        $is_sub_dir = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/vhv/') !== false);
        $css_path = $is_sub_dir ? '../assets/css/style.css' : 'assets/css/style.css';
        ?>
        <!DOCTYPE html>
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>เชื่อมต่อฐานข้อมูลไม่สำเร็จ - NCD Portal</title>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css_path); ?>">
            <style>
                body {
                    background-color: #0b0f19;
                    color: #f3f4f6;
                    font-family: 'Inter', 'Prompt', sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    padding: 20px;
                }
                .error-card {
                    background: rgba(17, 24, 39, 0.7);
                    border: 1px solid rgba(239, 68, 68, 0.3);
                    border-radius: 16px;
                    padding: 30px;
                    max-width: 520px;
                    width: 100%;
                    text-align: center;
                    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
                    backdrop-filter: blur(8px);
                }
                .error-icon {
                    font-size: 60px;
                    margin-bottom: 20px;
                }
                h2 {
                    color: #ef4444;
                    margin-top: 0;
                    font-weight: 700;
                    font-size: 22px;
                }
                p {
                    color: #9ca3af;
                    line-height: 1.6;
                    font-size: 15px;
                }
                .steps {
                    text-align: left;
                    background: rgba(0, 0, 0, 0.2);
                    padding: 15px 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                    border-left: 4px solid #ef4444;
                }
                .steps ul {
                    margin: 0;
                    padding-left: 20px;
                    color: #d1d5db;
                }
                .steps li {
                    margin-bottom: 8px;
                    font-size: 13.5px;
                }
                .btn-retry {
                    background: linear-gradient(135deg, #ef4444, #b91c1c);
                    color: white;
                    border: none;
                    padding: 10px 24px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: bold;
                    text-decoration: none;
                    display: inline-block;
                    transition: transform 0.2s;
                }
                .btn-retry:hover {
                    transform: scale(1.02);
                }
                .error-code {
                    font-size: 11px;
                    color: #6b7280;
                    margin-top: 20px;
                    word-break: break-all;
                }
            </style>
        </head>
        <body>
            <div class="error-card">
                <div class="error-icon">⚠️</div>
                <h2>เชื่อมต่อฐานข้อมูลล้มเหลว</h2>
                <p>ระบบ NCD Portal ไม่สามารถเชื่อมต่อกับฐานข้อมูล MySQL ของระบบ JHCIS (พอร์ต 3333) ได้ในขณะนี้</p>
                
                <div class="steps">
                    <strong>วิธีแก้ไขเบื้องต้น:</strong>
                    <ul>
                        <li>ตรวจสอบว่าได้เปิดบริการ MySQL ของระบบ JHCIS หรือ AppServ บนพอร์ต 3333 แล้ว</li>
                        <li>หากรันบนเครื่อง Localhost กรุณาตรวจสอบสถานะของ JHCIS Database Server (พอร์ต 3333)</li>
                        <li>หากเพิ่งเริ่มระบบคอมพิวเตอร์ กรุณารอประมาณ 1-2 นาทีเพื่อให้ฐานข้อมูลเริ่มทำงานเสร็จสมบูรณ์</li>
                    </ul>
                </div>
                
                <a href="" class="btn-retry">🔄 ลองใหม่อีกครั้ง</a>
                
                <div class="error-code">
                    รายละเอียดข้อผิดพลาด: <?php echo htmlspecialchars($e->getMessage()); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
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

// Auto-create and auto-correct assignment_history_log table if it doesn't exist or is missing fields
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `assignment_history_log` (
        `log_id` INT AUTO_INCREMENT PRIMARY KEY,
        `assignment_id` INT NOT NULL,
        `action` VARCHAR(50) NOT NULL,
        `note` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Check and add missing columns dynamically
    $colsToCheck = [
        'assignment_id' => 'INT NOT NULL AFTER `log_id`',
        'action' => 'VARCHAR(50) NOT NULL AFTER `assignment_id`',
        'note' => 'TEXT NULL AFTER `action`',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `note`'
    ];

    foreach ($colsToCheck as $colName => $definition) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM `assignment_history_log` LIKE '$colName'");
        if ($checkCol->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `assignment_history_log` ADD COLUMN `$colName` $definition");
        }
    }
} catch (\PDOException $e) {
    // Fail silently
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

    $checkDpacSandbox = $pdo->query("SHOW COLUMNS FROM `dpac_enrollments` LIKE 'is_sandbox'");
    if ($checkDpacSandbox->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_enrollments` ADD COLUMN `is_sandbox` TINYINT(1) NOT NULL DEFAULT 0");
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
        `advice_given` TEXT,
        `is_sandbox` TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $checkFollowupSandbox = $pdo->query("SHOW COLUMNS FROM `dpac_followups` LIKE 'is_sandbox'");
    if ($checkFollowupSandbox->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_followups` ADD COLUMN `is_sandbox` TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (\PDOException $e) {
    // Fail silently or handle
}

// Auto-create vhv_surveys and vhv_survey_participants tables for R2R Survey
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `vhv_surveys` (
        `survey_id` INT AUTO_INCREMENT PRIMARY KEY,
        `hoscode` VARCHAR(9) NOT NULL,
        `sub_district_code` VARCHAR(6) NOT NULL,
        `score_peou` TINYINT NOT NULL,
        `score_sq` TINYINT NOT NULL,
        `score_iq` TINYINT NOT NULL,
        `score_pu` TINYINT NOT NULL,
        `score_bi` TINYINT NOT NULL,
        `selected_tags` TEXT,
        `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `budget_year` INT NOT NULL DEFAULT 2026,
        `is_sandbox` TINYINT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `vhv_survey_participants` (
        `vhv_id` VARCHAR(50) NOT NULL,
        `budget_year` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`vhv_id`, `budget_year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (\PDOException $e) {
    // Fail silently
}

// Auto-migration: Add created_at column to vhv_survey_participants if it doesn't exist
try {
    $check = $pdo->query("SHOW COLUMNS FROM `vhv_survey_participants` LIKE 'created_at'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `vhv_survey_participants` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }
} catch (\PDOException $e) {
    // Fail silently
}



// Auto-create JHCIS sync tables and columns
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `jhcis_sync_configs` (
        `config_id` INT AUTO_INCREMENT PRIMARY KEY,
        `hoscode` VARCHAR(10) NOT NULL UNIQUE,
        `jhcis_host` VARCHAR(100) DEFAULT 'localhost',
        `jhcis_port` INT DEFAULT 3333,
        `jhcis_dbname` VARCHAR(50) DEFAULT 'jhcisdb',
        `jhcis_user` VARCHAR(50) DEFAULT 'root',
        `jhcis_pass` VARCHAR(100) DEFAULT '',
        `date_mode` ENUM('screening_date', 'sync_date') DEFAULT 'screening_date',
        `overwrite_mode` ENUM('skip_existing', 'update_newer') DEFAULT 'skip_existing',
        `auto_sync_approved` TINYINT(1) DEFAULT 0,
        `last_connected_at` DATETIME NULL,
        `last_synced_at` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `jhcis_sync_logs` (
        `log_id` INT AUTO_INCREMENT PRIMARY KEY,
        `hoscode` VARCHAR(10) NOT NULL,
        `sync_type` VARCHAR(50) DEFAULT 'manual',
        `date_range` VARCHAR(100) NULL,
        `total_records` INT DEFAULT 0,
        `success_records` INT DEFAULT 0,
        `skipped_records` INT DEFAULT 0,
        `failed_records` INT DEFAULT 0,
        `duration_seconds` DECIMAL(6,2) DEFAULT 0.00,
        `error_message` TEXT NULL,
        `synced_by` VARCHAR(100) DEFAULT 'Admin',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $checkSyncCol = $pdo->query("SHOW COLUMNS FROM `screening_results` LIKE 'is_synced_jhcis'");
    if ($checkSyncCol->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `screening_results` ADD COLUMN `is_synced_jhcis` TINYINT(1) NOT NULL DEFAULT 0");
    }

    $checkSyncDateCol = $pdo->query("SHOW COLUMNS FROM `screening_results` LIKE 'jhcis_synced_at'");
    if ($checkSyncDateCol->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `screening_results` ADD COLUMN `jhcis_synced_at` DATETIME NULL");
    }
} catch (\PDOException $e) {
    // Fail silently
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

// Auto-migration: Add vhv_phone column to vhv_users if it doesn't exist
try {
    $checkPhone = $pdo->query("SHOW COLUMNS FROM `vhv_users` LIKE 'vhv_phone'");
    if ($checkPhone->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `vhv_users` ADD COLUMN `vhv_phone` VARCHAR(30) DEFAULT NULL AFTER `vhv_name`");
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

    // Add assignment_id column to vhv_rewards if not exists
    $checkAssignId = $pdo->query("SHOW COLUMNS FROM `vhv_rewards` LIKE 'assignment_id'");
    if ($checkAssignId->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `vhv_rewards` ADD COLUMN `assignment_id` INT NULL AFTER `vhv_id`");
        $pdo->exec("ALTER TABLE `vhv_rewards` ADD INDEX `idx_rewards_assign_id` (`assignment_id`)");
    }

    // Auto-reconciliation: Sync task_assignments status to 'completed' when screening exists for that target_cid and round_number
    try {
        $pdo->exec("
            UPDATE task_assignments a
            JOIN screening_results s ON (a.assignment_id = s.assignment_id OR (a.target_cid = s.target_cid AND (a.round_number = s.round_number OR (a.round_number IS NULL AND s.round_number IS NULL))))
            SET a.assignment_status = 'completed'
            WHERE a.assignment_status != 'completed'
        ");
    } catch (\PDOException $e) {}

    // Auto-reconciliation: Revert task_assignments with status 'completed' to 'pending' if no screening_results exist for that round and CID/assignment_id
    try {
        $pdo->exec("
            UPDATE task_assignments a
            LEFT JOIN screening_results s ON (a.assignment_id = s.assignment_id OR (a.target_cid = s.target_cid AND (a.round_number = s.round_number OR (a.round_number IS NULL AND s.round_number IS NULL))))
            SET a.assignment_status = 'pending'
            WHERE a.assignment_status = 'completed'
              AND s.screening_id IS NULL
        ");
    } catch (\PDOException $e) {}

    // Auto-reconciliation: Link s.assignment_id to active task_assignments.assignment_id if missing/unlinked matching CID and round
    try {
        $pdo->exec("
            UPDATE screening_results s
            JOIN task_assignments a ON s.target_cid = a.target_cid AND (s.round_number = a.round_number OR (s.round_number IS NULL AND a.round_number IS NULL))
            SET s.assignment_id = a.assignment_id
            WHERE s.assignment_id IS NULL OR s.assignment_id NOT IN (SELECT assignment_id FROM task_assignments)
        ");
    } catch (\PDOException $e) {}

    // Backfill assignment_id in vhv_rewards from screening_results or task_assignments
    try {
        $pdo->exec("
            UPDATE vhv_rewards r
            JOIN screening_results s ON r.screening_id = s.screening_id
            SET r.assignment_id = s.assignment_id
            WHERE r.assignment_id IS NULL AND s.assignment_id IS NOT NULL
        ");
    } catch (\PDOException $e) {}

    // Modify points_earned in vhv_rewards to DECIMAL(4,2) to support decimal points like 0.25, 0.75
    $pdo->exec("ALTER TABLE `vhv_rewards` MODIFY COLUMN `points_earned` DECIMAL(4,2) DEFAULT 1.00");

    // Add skip_count and skipped_reason columns to dpac_followups if not exists
    $checkSkipCount = $pdo->query("SHOW COLUMNS FROM `dpac_followups` LIKE 'skip_count'");
    if ($checkSkipCount->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_followups` ADD COLUMN `skip_count` INT NOT NULL DEFAULT 0 AFTER `status`");
    }

    $checkSkippedReason = $pdo->query("SHOW COLUMNS FROM `dpac_followups` LIKE 'skipped_reason'");
    if ($checkSkippedReason->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_followups` ADD COLUMN `skipped_reason` VARCHAR(255) DEFAULT NULL AFTER `skip_count`");
    }

    // Retroactively backfill missing rewards for completed screenings (Round 1 = 1 pt, Round 2+ = 2 pts) matching by assignment_id OR target_cid
    try {
        $pdo->exec("
            INSERT INTO vhv_rewards (vhv_id, screening_id, assignment_id, points_earned, approval_status, approved_at, created_at)
            SELECT a.vhv_id, s.screening_id, a.assignment_id, 
                   CASE WHEN a.round_number >= 2 OR s.round_number >= 2 THEN 2.00 ELSE 1.00 END, 
                   'approved', s.created_at, s.created_at
            FROM screening_results s
            JOIN task_assignments a ON (s.assignment_id = a.assignment_id OR s.target_cid = a.target_cid)
            LEFT JOIN vhv_rewards r ON s.screening_id = r.screening_id
            WHERE r.reward_id IS NULL AND a.vhv_id IS NOT NULL
        ");
    } catch (\PDOException $e) {}

    // Retroactively update 2x points (2.00) for all past Round 2+ screenings
    $pdo->exec("
        UPDATE vhv_rewards r
        JOIN task_assignments a ON r.assignment_id = a.assignment_id
        SET r.points_earned = 2.00
        WHERE a.round_number >= 2 AND r.points_earned < 2.00
    ");

    $pdo->exec("
        UPDATE vhv_rewards r
        JOIN screening_results s ON r.screening_id = s.screening_id
        SET r.points_earned = 2.00
        WHERE s.round_number >= 2 AND r.points_earned < 2.00
    ");

    // Retroactively backfill missing rewards for completed DPAC followups
    $pdo->exec("
        INSERT INTO vhv_rewards (vhv_id, followup_id, points_earned, approval_status, approved_at, created_at)
        SELECT f.vhv_id, f.followup_id, 1, 'approved', f.completed_at, f.completed_at
        FROM dpac_followups f
        LEFT JOIN vhv_rewards r ON f.followup_id = r.followup_id
        WHERE f.status = 'completed' AND r.reward_id IS NULL
    ");
    // Do not auto-delete assignments here. A target can legitimately have more than
    // one assignment in the same budget year when multi-round screening is enabled.
    // The old cleanup query compared only CID and budget year, so creating round 2
    // deleted the round 1 assignment and could cascade-delete its screening result.
    // Duplicate prevention is handled by the per-round unique index
    // (target_cid, budget_year, round_number, is_sandbox).

    // Auto-cleanup orphaned task assignments (where target_cid is not in target_population)
    $pdo->exec("
        DELETE a FROM task_assignments a
        LEFT JOIN target_population p ON a.target_cid = p.cid
        WHERE p.cid IS NULL
    ");

    // Auto-cleanup orphaned rewards (where assignment_id is not null but does not exist in task_assignments)
    $pdo->exec("
        DELETE r FROM vhv_rewards r
        LEFT JOIN task_assignments a ON r.assignment_id = a.assignment_id
        WHERE r.assignment_id IS NOT NULL AND a.assignment_id IS NULL
    ");

    // Auto-cleanup orphaned rewards (where screening_id is not null but does not exist in screening_results)
    $pdo->exec("
        DELETE r FROM vhv_rewards r
        LEFT JOIN screening_results s ON r.screening_id = s.screening_id
        WHERE r.screening_id IS NOT NULL AND s.screening_id IS NULL
    ");

    // Auto-cleanup orphaned rewards (where followup_id is not null but does not exist in dpac_followups)
    $pdo->exec("
        DELETE r FROM vhv_rewards r
        LEFT JOIN dpac_followups f ON r.followup_id = f.followup_id
        WHERE r.followup_id IS NOT NULL AND f.followup_id IS NULL
    ");

    // Retroactively mark existing manual targets (where pid is null or empty) as is_manual = 1
    $pdo->exec("
        UPDATE target_population 
        SET is_manual = 1 
        WHERE (pid IS NULL OR pid = '') AND (is_manual IS NULL OR is_manual = 0)
    ");

} catch (\PDOException $e) {
    // Fail silently
}

// ==========================================
// Enterprise Scalability & Performance Engine (Multi-Million Record Ready)
// ==========================================
try {
    // 1. Create KPI Summary Cache Table (Pre-aggregated materialization)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `kpi_summary_cache` (
        `cache_id` INT AUTO_INCREMENT PRIMARY KEY,
        `cache_key` VARCHAR(100) NOT NULL UNIQUE,
        `budget_year` INT NOT NULL,
        `hoscode` VARCHAR(10) DEFAULT NULL,
        `sub_district_code` VARCHAR(6) DEFAULT NULL,
        `is_sandbox` TINYINT NOT NULL DEFAULT 0,
        `total_target` INT NOT NULL DEFAULT 0,
        `r1_done` INT NOT NULL DEFAULT 0,
        `r2_done` INT NOT NULL DEFAULT 0,
        `r3_done` INT NOT NULL DEFAULT 0,
        `dpac_total` INT NOT NULL DEFAULT 0,
        `dpac_done` INT NOT NULL DEFAULT 0,
        `payload_json` MEDIUMTEXT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_kpi_lookup` (`budget_year`, `hoscode`, `is_sandbox`),
        INDEX `idx_kpi_tambon` (`budget_year`, `sub_district_code`, `is_sandbox`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. High-Throughput Composite Indexing Suite
    $indexesToAdd = [
        ['target_population', 'idx_perf_pop_geo', '(`sub_district_code`, `moo`, `hoscode`, `need_screen_dm`, `need_screen_ht`)'],
        ['screening_results', 'idx_perf_screen_composite', '(`target_cid`, `round_number`, `is_sandbox`, `created_at`)'],
        ['screening_results', 'idx_perf_screen_assign', '(`assignment_id`, `round_number`, `is_sandbox`)'],
        ['task_assignments', 'idx_perf_task_lookup', '(`target_cid`, `vhv_id`, `round_number`, `budget_year`, `status`, `is_sandbox`)'],
        ['dpac_followups', 'idx_perf_dpac_lookup', '(`enrollment_id`, `status`, `is_sandbox`, `completed_at`)'],
        ['dpac_enrollments', 'idx_perf_dpac_cid', '(`cid`, `budget_year`, `is_sandbox`, `status`)'],
        ['emergency_beacons', 'idx_perf_beacon_status', '(`status`, `created_at`, `hoscode`)']
    ];

    foreach ($indexesToAdd as $idxInfo) {
        list($tbl, $idxName, $cols) = $idxInfo;
        try {
            $checkIdx = $pdo->query("SHOW INDEX FROM `$tbl` WHERE Key_name = '$idxName'");
            if ($checkIdx && $checkIdx->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `$tbl` ADD INDEX `$idxName` $cols");
            }
        } catch (\Throwable $e) {}
    }
} catch (\Throwable $e) {
    // Fail gracefully without interrupting live traffic
}

// Auto-create admin_users table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_users` (
        `username` VARCHAR(50) PRIMARY KEY,
        `password_hash` VARCHAR(255) NOT NULL,
        `hoscode` VARCHAR(10) DEFAULT NULL,
        `admin_name` VARCHAR(100) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Auto-migration: Add status and role columns to admin_users if they don't exist
    try {
        $checkStatus = $pdo->query("SHOW COLUMNS FROM `admin_users` LIKE 'status'");
        if ($checkStatus->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `admin_users` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active'");
        }
        $checkRole = $pdo->query("SHOW COLUMNS FROM `admin_users` LIKE 'role'");
        if ($checkRole->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `admin_users` ADD COLUMN `role` VARCHAR(30) NOT NULL DEFAULT 'admin'");
        }
    } catch (\PDOException $e) {
        // Fail silently
    }

    // Seed default admin accounts if empty
    $count = $pdo->query("SELECT COUNT(*) FROM `admin_users`")->fetchColumn();
    if ($count == 0) {
        $defaultPasswordHash = password_hash('Prevention2026', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `admin_users` (username, password_hash, hoscode, admin_name, role) VALUES (?, ?, ?, ?, 'admin')");

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
        $insertSso = $pdo->prepare("INSERT INTO `admin_users` (username, password_hash, hoscode, admin_name, role) VALUES (?, ?, ?, ?, 'admin')");
        $insertSso->execute(['adminsso', $ssoPasswordHash, null, 'ผู้รับผิดชอบงานระดับอำเภอ']);
    }

    // Seed executive if not exists
    $checkExec = $pdo->prepare("SELECT COUNT(*) FROM `admin_users` WHERE username = ?");
    $checkExec->execute(['executive']);
    if ($checkExec->fetchColumn() == 0) {
        $execPasswordHash = password_hash('123456', PASSWORD_DEFAULT);
        $insertExec = $pdo->prepare("INSERT INTO `admin_users` (username, password_hash, hoscode, admin_name, status, role) VALUES (?, ?, ?, ?, 'active', 'executive')");
        $insertExec->execute(['executive', $execPasswordHash, null, 'ผู้บริหาร / ผู้ตรวจประเมิน']);
    }

    // Auto-create health_units table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `health_units` (
        `hoscode` VARCHAR(10) PRIMARY KEY,
        `hosname` VARCHAR(255) NOT NULL,
        `sub_district_code` VARCHAR(10) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Ensure sub_district_code column exists in existing health_units table
    try {
        $pdo->exec("ALTER TABLE `health_units` ADD COLUMN `sub_district_code` VARCHAR(10) DEFAULT NULL AFTER `hosname`");
    } catch (\Throwable $e) {}

    // Auto-populate default sub_district_code for health units if empty & migrate legacy codes
    try {
        $defaultUnitTambon = [
            '10957' => '342001', // โรงพยาบาลตาลสุม -> ตำบลตาลสุม
            '03751' => '342001', // รพ.สต.ดอนพันชาด -> ตำบลตาลสุม
            '03752' => '342002', // รพ.สต.บ้านสำโรง -> ตำบลสำโรง
            '03753' => '342003', // รพ.สต.บ้านจิกเทิง -> ตำบลจิกเทิง
            '03754' => '342004', // รพ.สต.บ้านหนองกุงใหญ่ -> ตำบลหนองกุง
            '03755' => '342005', // รพ.สต.นาคาย -> ตำบลนาคาย
            '03756' => '342005', // รพ.สต.คำหนามแท่ง -> ตำบลนาคาย
            '03757' => '342006', // รพ.สต.คำหว้า -> ตำบลคำหว้า
        ];

        // Safe Migration: Update any legacy 3418xx codes to official 3420xx codes
        $subFixes = [
            '341801' => '342001',
            '341802' => '342002',
            '341803' => '342003',
            '341804' => '342004',
            '341805' => '342005',
            '341806' => '342006'
        ];
        foreach ($subFixes as $oldCode => $newCode) {
            $pdo->exec("UPDATE `sub_districts` SET `sub_district_code` = '{$newCode}' WHERE `sub_district_code` = '{$oldCode}'");
            $pdo->exec("UPDATE `health_units` SET `sub_district_code` = '{$newCode}' WHERE `sub_district_code` = '{$oldCode}'");
            $pdo->exec("UPDATE `villages` SET `sub_district_code` = '{$newCode}', `vhid_code` = REPLACE(`vhid_code`, '{$oldCode}', '{$newCode}') WHERE `sub_district_code` = '{$oldCode}'");
            $pdo->exec("UPDATE `target_population` SET `sub_district_code` = '{$newCode}', `vhid_code` = REPLACE(`vhid_code`, '{$oldCode}', '{$newCode}') WHERE `sub_district_code` = '{$oldCode}'");
            $pdo->exec("UPDATE `vhv_users` SET `vhid_code` = REPLACE(`vhid_code`, '{$oldCode}', '{$newCode}') WHERE `vhid_code` LIKE '{$oldCode}%'");
        }

        // Backfill vhid_code in vhv_users if missing based on hoscode and vhv_moo
        try {
            $pdo->exec("
                UPDATE vhv_users v
                JOIN health_units h ON v.hoscode = h.hoscode
                SET v.vhid_code = CONCAT(h.sub_district_code, LPAD(CAST(v.vhv_moo AS UNSIGNED), 2, '0'))
                WHERE (v.vhid_code IS NULL OR v.vhid_code = '' OR v.vhid_code NOT LIKE '3420%')
                  AND h.sub_district_code IS NOT NULL AND v.vhv_moo IS NOT NULL AND CAST(v.vhv_moo AS UNSIGNED) > 0
            ");
        } catch (\Throwable $e) {}

        $checkNull = $pdo->query("SELECT COUNT(*) FROM `health_units` WHERE sub_district_code IS NULL OR sub_district_code = ''")->fetchColumn();
        if ($checkNull > 0) {
            $upStmt = $pdo->prepare("UPDATE `health_units` SET `sub_district_code` = ? WHERE `hoscode` = ? AND (`sub_district_code` IS NULL OR `sub_district_code` = '')");
            foreach ($defaultUnitTambon as $hCode => $sdCode) {
                $upStmt->execute([$sdCode, $hCode]);
            }
        }
    } catch (\Throwable $e) {}

    // Auto-create sub_districts table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sub_districts` (
        `sub_district_code` VARCHAR(10) PRIMARY KEY,
        `sub_district_name` VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Auto-create villages table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `villages` (
        `vhid_code` VARCHAR(20) PRIMARY KEY,
        `sub_district_code` VARCHAR(10) NOT NULL,
        `moo` INT NOT NULL,
        `village_name` VARCHAR(100) NOT NULL,
        `hoscode` VARCHAR(10) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Auto-create staging_jhcis_person table if it doesn't exist
    $pdo->exec("DROP TABLE IF EXISTS `staging_jhcis_person`;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `staging_jhcis_person` (
        `staging_id` INT AUTO_INCREMENT PRIMARY KEY,
        `hoscode` VARCHAR(5) NOT NULL,
        `pid` VARCHAR(15) NOT NULL,
        `cid` VARCHAR(13) NOT NULL,
        `first_name` VARCHAR(100) DEFAULT NULL,
        `last_name` VARCHAR(100) DEFAULT NULL,
        `sex` VARCHAR(1) DEFAULT NULL,
        `birth` DATE DEFAULT NULL,
        `hid` VARCHAR(15) DEFAULT NULL,
        `house_no` VARCHAR(50) DEFAULT NULL,
        `vhid_code` VARCHAR(8) DEFAULT NULL,
        `typearea` VARCHAR(2) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_hos_pid` (`hoscode`, `pid`),
        UNIQUE KEY `uq_cid` (`cid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Auto-create system_settings table early if it doesn't exist
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            description VARCHAR(255) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (\Exception $e) {}

    // Auto-seed default data ONLY if not already initialized
    $systemInitialized = false;
    try {
        $checkInit = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'system_initialized'");
        if ($checkInit) {
            $systemInitialized = ($checkInit->fetchColumn() === '1');
        }
    } catch (\Exception $e) {}

    if (!$systemInitialized) {
        $unitCount = $pdo->query("SELECT COUNT(*) FROM `health_units`")->fetchColumn();
        if ($unitCount == 0) {
            $defaultUnits = [
                '10957' => 'โรงพยาบาลตาลสุม',
                '03751' => 'รพ.สต.ดอนพันชาด',
                '03752' => 'รพ.สต.บ้านสำโรง',
                '03753' => 'รพ.สต.บ้านจิกเทิง',
                '03754' => 'รพ.สต.บ้านหนองกุงใหญ่',
                '03755' => 'รพ.สต.นาคาย',
                '03756' => 'รพ.สต.คำหนามแท่ง',
                '03757' => 'รพ.สต.คำหว้า'
            ];
            $stmt = $pdo->prepare("INSERT INTO `health_units` (hoscode, hosname) VALUES (?, ?)");
            foreach ($defaultUnits as $code => $name) {
                $stmt->execute([$code, $name]);
            }
        }

        $subDistrictCount = $pdo->query("SELECT COUNT(*) FROM `sub_districts`")->fetchColumn();
        if ($subDistrictCount == 0) {
            $defaultSubs = [
                '342001' => 'ตาลสุม',
                '342002' => 'สำโรง',
                '342003' => 'จิกเทิง',
                '342004' => 'หนองกุง',
                '342005' => 'นาคาย',
                '342006' => 'คำหว้า'
            ];
            $stmt = $pdo->prepare("INSERT INTO `sub_districts` (sub_district_code, sub_district_name) VALUES (?, ?)");
            foreach ($defaultSubs as $code => $name) {
                $stmt->execute([$code, $name]);
            }
        }

        $villageCount = $pdo->query("SELECT COUNT(*) FROM `villages`")->fetchColumn();
        if ($villageCount == 0) {
            $seed_hoscode_villages = [
                '10957' => [
                    'tambon' => '342001',
                    'villages' => [
                        1 => 'บ้านม่วงโคน', 2 => 'บ้านดอนรังกา', 3 => 'บ้านนาห้วยแคน (เขตเทศบาล)',
                        5 => 'บ้านนามน (เขตเทศบาล)', 10 => 'บ้านนามน (เขตเทศบาล)', 11 => 'บ้านตาลสุม (เขตเทศบาล)',
                        12 => 'บ้านคำไม้ตาย', 13 => 'บ้านปากเซ'
                    ]
                ],
                '03751' => [
                    'tambon' => '342001',
                    'villages' => [
                        4 => 'บ้านดอนพันชาด', 6 => 'บ้านดอนตะลี', 7 => 'บ้านปากห้วย',
                        8 => 'บ้านโนนค้อ', 9 => 'บ้านแก่งกบ', 14 => 'บ้านโนนสวรรค์', 15 => 'บ้านทุ่งเจริญ'
                    ]
                ],
                '03752' => [
                    'tambon' => '342002',
                    'villages' => [
                        1 => 'บ้านสำโรงใหญ่', 2 => 'บ้านสำโรงกลาง', 3 => 'บ้านนาโพธิ์',
                        4 => 'บ้านสำโรงใต้', 5 => 'บ้านนาแพง', 6 => 'บ้านหนองโน',
                        7 => 'บ้านหนองสะเดา', 8 => 'บ้านทุ่งเจริญ'
                    ]
                ],
                '03753' => [
                    'tambon' => '342003',
                    'villages' => [
                        1 => 'บ้านจิกเทิง', 2 => 'บ้านจิกลุ่ม', 3 => 'บ้านเชียงแก้ว',
                        4 => 'บ้านเชียงแก้ว', 5 => 'บ้านดอนโด่ (บ้านดอนโต)', 6 => 'บ้านดอนยูง',
                        7 => 'บ้านค้อ', 8 => 'บ้านดอนแป้นลม', 9 => 'บ้านสร้างคำ'
                    ]
                ],
                '03754' => [
                    'tambon' => '342004',
                    'villages' => [
                        1 => 'บ้านหนองกุงใหญ่', 2 => 'บ้านหนองกุงน้อย', 3 => 'บ้านคำแคน',
                        4 => 'บ้านสร้างแสง', 5 => 'บ้านคำเตยใต้', 6 => 'บ้านสร้างหว้า',
                        7 => 'บ้านคำเตยเหนือ', 8 => 'บ้านสร้างหว้าพัฒนา'
                    ]
                ],
                '03755' => [
                    'tambon' => '342005',
                    'villages' => [
                        1 => 'บ้านนาคาย', 2 => 'บ้านโนนจิก', 3 => 'บ้านหนองเป็ด',
                        4 => 'บ้านโนนยาง', 5 => 'บ้านดอนขวาง', 6 => 'บ้านดอนหวาย'
                    ]
                ],
                '03756' => [
                    'tambon' => '342005',
                    'villages' => [
                        7 => 'บ้านโคกคล้าย', 8 => 'บ้านคำหนามแท่ง', 9 => 'บ้านคำผักหนอก',
                        10 => 'บ้านคำฮี', 11 => 'บ้านห่องแดง', 12 => 'บ้านโนนสำราญ', 13 => 'บ้านโนนเจริญ'
                    ]
                ],
                '03757' => [
                    'tambon' => '342006',
                    'villages' => [
                        1 => 'บ้านคำหว้า', 2 => 'บ้านคำหว้า', 3 => 'บ้านห้วยดู่',
                        4 => 'บ้านนาทมเหนือ', 5 => 'บ้านไฮหย่อง', 6 => 'บ้านนาทมใต้'
                    ]
                ]
            ];

            $stmt = $pdo->prepare("INSERT INTO `villages` (vhid_code, sub_district_code, moo, village_name, hoscode) VALUES (?, ?, ?, ?, ?)");
            foreach ($seed_hoscode_villages as $hcode => $data) {
                $tambon = $data['tambon'];
                foreach ($data['villages'] as $moo => $vname) {
                    $vhid = $tambon . sprintf("%02d", $moo);
                    $stmt->execute([$vhid, $tambon, $moo, $vname, $hcode]);
                }
            }
        }

        // Insert system_initialized flag
        try {
            $pdo->exec("INSERT INTO system_settings (setting_key, setting_value, description) 
                VALUES ('system_initialized', '1', 'ระบบทำงานแล้ว ป้องกันการ Re-seed ข้อมูลตัวอย่าง') 
                ON DUPLICATE KEY UPDATE setting_value = '1'");
        } catch (\Exception $ex) {}
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
        'nation' => "VARCHAR(5) NULL",
        'result' => "VARCHAR(255) NULL"
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
    $idxCheck = $pdo->query("SHOW INDEX FROM `target_population` WHERE Key_name = 'idx_target_hos_hid'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `target_population` ADD INDEX `idx_target_hos_hid` (`hoscode`, `hid`)");
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

try {
    // vhv_users hoscode index
    $idxCheck = $pdo->query("SHOW INDEX FROM `vhv_users` WHERE Key_name = 'idx_vhv_hoscode'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `vhv_users` ADD INDEX `idx_vhv_hoscode` (`hoscode`)");
    }
} catch (\PDOException $e) {
}

try {
    // target_population covering index for screening targets
    $idxCheck = $pdo->query("SHOW INDEX FROM `target_population` WHERE Key_name = 'idx_target_hos_screen'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `target_population` ADD INDEX `idx_target_hos_screen` (`hoscode`, `need_screen_dm`, `need_screen_ht`)");
    }
} catch (\PDOException $e) {
}

try {
    // dpac_enrollments indexes
    $idxCheck = $pdo->query("SHOW INDEX FROM `dpac_enrollments` WHERE Key_name = 'idx_dpac_enroll_cid'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_enrollments` ADD INDEX `idx_dpac_enroll_cid` (`cid`)");
    }
} catch (\PDOException $e) {
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM `dpac_enrollments` WHERE Key_name = 'idx_dpac_enroll_status'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_enrollments` ADD INDEX `idx_dpac_enroll_status` (`status`)");
    }
} catch (\PDOException $e) {
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM `dpac_enrollments` WHERE Key_name = 'idx_dpac_enroll_vhv'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_enrollments` ADD INDEX `idx_dpac_enroll_vhv` (`assigned_vhv_id`)");
    }
} catch (\PDOException $e) {
}

try {
    // dpac_followups indexes
    $idxCheck = $pdo->query("SHOW INDEX FROM `dpac_followups` WHERE Key_name = 'idx_dpac_follow_enroll'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_followups` ADD INDEX `idx_dpac_follow_enroll` (`enrollment_id`)");
    }
} catch (\PDOException $e) {
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM `dpac_followups` WHERE Key_name = 'idx_dpac_follow_vhv'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_followups` ADD INDEX `idx_dpac_follow_vhv` (`vhv_id`)");
    }
} catch (\PDOException $e) {
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM `dpac_followups` WHERE Key_name = 'idx_dpac_follow_status'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_followups` ADD INDEX `idx_dpac_follow_status` (`status`)");
    }
} catch (\PDOException $e) {
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM `dpac_followups` WHERE Key_name = 'idx_dpac_follow_completed'");
    if ($idxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `dpac_followups` ADD INDEX `idx_dpac_follow_completed` (`completed_at`)");
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
        $moo_val = intval($moo);
        
        global $pdo;
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT village_name FROM villages WHERE (sub_district_code = ? AND moo = ?) OR vhid_code = ?");
                $stmt->execute([$tambon, $moo_val, $vhid_code]);
                $vname = $stmt->fetchColumn();
                if ($vname) {
                    return $vname;
                }
            } catch (\Exception $e) {
                // Fallback
            }
        }

        if (empty($tambon) || strlen($tambon) < 6) {
            $admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
            $hoscode_tambons = [
                '10957' => '342001',
                '03751' => '342001',
                '03752' => '342002',
                '03753' => '342003',
                '03754' => '342004',
                '03755' => '342005',
                '03756' => '342005',
                '03757' => '342006'
            ];
            if ($admin_hoscode && isset($hoscode_tambons[$admin_hoscode])) {
                $tambon = $hoscode_tambons[$admin_hoscode];
            }
        }
        $moo = intval($moo);

        $villages = [
            '342001' => [
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
            '342002' => [
                1 => 'บ้านสำโรงใหญ่',
                2 => 'บ้านสำโรงกลาง',
                3 => 'บ้านนาโพธิ์',
                4 => 'บ้านสำโรงใต้',
                5 => 'บ้านนาแพง',
                6 => 'บ้านหนองโน',
                7 => 'บ้านหนองสะเดา',
                8 => 'บ้านทุ่งเจริญ'
            ],
            '342003' => [
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
            '342004' => [
                1 => 'บ้านหนองกุงใหญ่',
                2 => 'บ้านหนองกุงน้อย',
                3 => 'บ้านคำแคน',
                4 => 'บ้านสร้างแสง',
                5 => 'บ้านคำเตยใต้',
                6 => 'บ้านสร้างหว้า',
                7 => 'บ้านคำเตยเหนือ',
                8 => 'บ้านสร้างหว้าพัฒนา'
            ],
            '342005' => [
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
            '342006' => [
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
        'tambon' => '342001',
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
        'tambon' => '342001',
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
        'tambon' => '342002',
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
        'tambon' => '342003',
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
        'tambon' => '342004',
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
        'tambon' => '342005',
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
        'tambon' => '342005',
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
        'tambon' => '342006',
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
            SELECT vhid_code, sub_district_code, moo, village_name, hoscode 
            FROM villages 
            WHERE hoscode IS NOT NULL AND hoscode != ''
            ORDER BY hoscode ASC, moo ASC
        ");
        $db_hoscode_villages = [];
        while ($row = $stmt_map->fetch(PDO::FETCH_ASSOC)) {
            $hc = trim($row['hoscode']);
            $sub = trim($row['sub_district_code']);
            $m = intval($row['moo']);
            $vname = trim($row['village_name']);

            if (!isset($db_hoscode_villages[$hc])) {
                $db_hoscode_villages[$hc] = [
                    'tambon' => $sub,
                    'villages' => []
                ];
            }
            $db_hoscode_villages[$hc]['villages'][$m] = $vname;
        }
        if (!empty($db_hoscode_villages)) {
            $hoscode_villages = $db_hoscode_villages;
        }
    } catch (\Exception $e) {
        // Fail silently
    }
}

if (!function_exists('get_village_display_name_by_hoscode')) {
    function get_village_display_name_by_hoscode($hoscode, $moo)
    {
        global $pdo;
        $moo_val = intval($moo);
        if (isset($pdo) && !empty($hoscode)) {
            try {
                $stmt = $pdo->prepare("SELECT village_name FROM villages WHERE hoscode = ? AND moo = ?");
                $stmt->execute([$hoscode, $moo_val]);
                $vname = $stmt->fetchColumn();
                if ($vname) {
                    return "หมู่ " . $moo_val . " " . $vname;
                }
            } catch (\Exception $e) {
                // Fallback
            }
        }

        global $hoscode_villages;

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

if (!function_exists('get_health_units')) {
    function get_health_units() {
        global $pdo;
        $fallback = [
            '10957' => 'โรงพยาบาลตาลสุม',
            '03751' => 'รพ.สต.ดอนพันชาด',
            '03752' => 'รพ.สต.บ้านสำโรง',
            '03753' => 'รพ.สต.บ้านจิกเทิง',
            '03754' => 'รพ.สต.บ้านหนองกุงใหญ่',
            '03755' => 'รพ.สต.นาคาย',
            '03756' => 'รพ.สต.คำหนามแท่ง',
            '03757' => 'รพ.สต.คำหว้า'
        ];
        if (isset($pdo)) {
            try {
                $stmt = $pdo->query("SELECT hoscode, hosname FROM health_units ORDER BY hoscode ASC");
                $units = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                if (!empty($units)) {
                    return $units;
                }
            } catch (\Exception $e) {
                // Fallback
            }
        }
        return $fallback;
    }
}

if (!function_exists('get_query_hoscodes')) {
    function get_query_hoscodes($hoscode = null) {
        $hc_names = get_health_units();
        if (!empty($hoscode)) {
            $hoscode = trim((string)$hoscode);
            if ($hoscode === '10957' || $hoscode === '10688') {
                return ['10957', '10688'];
            }
            return [$hoscode];
        } else {
            $hocs = array_keys($hc_names);
            if (!in_array('10688', $hocs)) {
                $hocs[] = '10688';
            }
            return $hocs;
        }
    }
}

if (!function_exists('get_current_budget_year')) {
    function get_current_budget_year(): int {
        // Thailand Fiscal Year: Starts Oct 1 to Sep 30 (Oct-Dec is next calendar year's budget year)
        $m = (int)date('n');
        $y = (int)date('Y');
        return ($m >= 10) ? ($y + 1) : $y;
    }
}

if (!function_exists('get_budget_year_thai')) {
    function get_budget_year_thai($year = null): int {
        $y = $year ? (int)$year : get_current_budget_year();
        return $y + 543;
    }
}

if (!function_exists('get_available_budget_years')) {
    function get_available_budget_years(): array {
        global $pdo;
        $currentYear = get_current_budget_year();
        $years = [$currentYear];
        
        if (isset($pdo)) {
            try {
                $stmt = $pdo->query("
                    SELECT DISTINCT budget_year FROM task_assignments WHERE budget_year IS NOT NULL
                    UNION SELECT DISTINCT budget_year FROM dpac_enrollments WHERE budget_year IS NOT NULL
                    UNION SELECT DISTINCT budget_year FROM vhv_surveys WHERE budget_year IS NOT NULL
                    ORDER BY budget_year DESC
                ");
                $dbYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($dbYears)) {
                    $years = array_unique(array_merge($dbYears, $years));
                }
                
                // Also check custom registered years in system_settings
                if (function_exists('get_system_setting')) {
                    $customYearsJson = get_system_setting('custom_budget_years', '[]');
                    $customYears = json_decode($customYearsJson, true);
                    if (is_array($customYears) && !empty($customYears)) {
                        $years = array_unique(array_merge($customYears, $years));
                    }
                }
                
                rsort($years, SORT_NUMERIC);
            } catch (\Throwable $e) {
                // Fallback
            }
        }
        return array_map('intval', array_values($years));
    }
}

if (!function_exists('set_system_setting')) {
    function set_system_setting($key, $value): bool {
        global $pdo;
        if (!isset($pdo)) return false;
        try {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            return $stmt->execute([$key, (string)$value]);
        } catch (\Exception $e) {
            return false;
        }
    }
}

// Auto-migration: Fix target_population screening defaults and incorrect values
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `target_population` LIKE 'need_screen_dm'");
    $col = $stmt->fetch();
    if ($col && $col['Default'] === '1') {
        // Change defaults in target_population
        $pdo->exec("ALTER TABLE `target_population` MODIFY `need_screen_dm` TINYINT(1) DEFAULT 0");
        $pdo->exec("ALTER TABLE `target_population` MODIFY `need_screen_ht` TINYINT(1) DEFAULT 0");
        $pdo->exec("ALTER TABLE `target_population` MODIFY `health_status_origin` VARCHAR(20) DEFAULT 'NORMAL'");

        // Fix existing records in target_population
        // Set all to 0 / NORMAL first
        $pdo->exec("UPDATE `target_population` SET `need_screen_dm` = 0, `need_screen_ht` = 0, `health_status_origin` = 'NORMAL'");

        // Restore correct DM target status based on staging table
        $pdo->exec("
            UPDATE `target_population` t
            JOIN `staging_hdc_dm` dm ON t.cid = dm.cid
            SET t.need_screen_dm = CASE 
                WHEN dm.risk = '5' OR dm.result LIKE '%ผู้ป่วย%' THEN 0 
                ELSE 1 
            END
        ");

        // Restore correct HT target status based on staging table
        $pdo->exec("
            UPDATE `target_population` t
            JOIN `staging_hdc_ht` ht ON t.cid = ht.cid
            SET t.need_screen_ht = CASE 
                WHEN ht.risk = '5' THEN 0 
                ELSE 1 
            END
        ");

        // Recalculate health_status_origin based on staging tables risk levels
        $pdo->exec("
            UPDATE `target_population` t
            LEFT JOIN `staging_hdc_dm` dm ON t.cid = dm.cid
            LEFT JOIN `staging_hdc_ht` ht ON t.cid = ht.cid
            SET t.health_status_origin = CASE 
                WHEN (dm.risk = '2' OR ht.risk = '2') THEN 'HIGH_RISK'
                WHEN (dm.risk = '1' AND ht.risk = '1') THEN 'BOTH'
                WHEN (dm.risk = '1') THEN 'DM_ONLY'
                WHEN (ht.risk = '1') THEN 'HT_ONLY'
                WHEN (dm.risk = '3' OR ht.risk = '3') THEN 'SUSPECT'
                ELSE 'NORMAL'
            END
            WHERE dm.cid IS NOT NULL OR ht.cid IS NOT NULL
        ");
    }
} catch (\PDOException $e) {
    // Fail silently
}

// Auto-migration: Merge masked/dummy duplicate records with unmasked JHCIS records
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sys_migrations (migration_name VARCHAR(255) PRIMARY KEY, run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migration: Strip leading zeros from pid in target_population, staging_hdc_dm, staging_hdc_ht to improve performance of equality joins
    try {
        $stmtMigrationCheck2 = $pdo->prepare("SELECT 1 FROM sys_migrations WHERE migration_name = ?");
        $stmtMigrationCheck2->execute(['strip_pid_leading_zeros_20260612']);
        if (!$stmtMigrationCheck2->fetch()) {
            $pdo->exec("UPDATE target_population SET pid = TRIM(LEADING '0' FROM pid) WHERE pid LIKE '0%'");
            $pdo->exec("UPDATE staging_hdc_dm SET pid = TRIM(LEADING '0' FROM pid) WHERE pid LIKE '0%'");
            $pdo->exec("UPDATE staging_hdc_ht SET pid = TRIM(LEADING '0' FROM pid) WHERE pid LIKE '0%'");
            
            $stmtInsert2 = $pdo->prepare("INSERT INTO sys_migrations (migration_name) VALUES (?)");
            $stmtInsert2->execute(['strip_pid_leading_zeros_20260612']);
        }
    } catch (\Exception $e) {
        // Fail silently
    }
    
    // Migration: Normalize hoscodes and PIDs and populate missing PIDs from CID
    try {
        $stmtMigrationCheck3 = $pdo->prepare("SELECT 1 FROM sys_migrations WHERE migration_name = ?");
        $stmtMigrationCheck3->execute(['normalize_hoscode_pid_20260701']);
        if (!$stmtMigrationCheck3->fetch()) {
            $pdo->exec("UPDATE target_population SET hoscode = LPAD(hoscode, 5, '0') WHERE LENGTH(hoscode) < 5");
            $pdo->exec("UPDATE staging_hdc_dm SET hoscode = LPAD(hoscode, 5, '0') WHERE LENGTH(hoscode) < 5");
            $pdo->exec("UPDATE staging_hdc_ht SET hoscode = LPAD(hoscode, 5, '0') WHERE LENGTH(hoscode) < 5");
            
            $pdo->exec("
                UPDATE target_population 
                SET pid = TRIM(LEADING '0' FROM SUBSTRING(cid, 6)) 
                WHERE cid LIKE '0%' 
                  AND (pid IS NULL OR pid = '' OR pid = '0')
                  AND LENGTH(cid) >= 10
            ");
            
            $pdo->exec("UPDATE target_population SET pid = TRIM(LEADING '0' FROM pid) WHERE pid LIKE '0%'");
            $pdo->exec("UPDATE staging_hdc_dm SET pid = TRIM(LEADING '0' FROM pid) WHERE pid LIKE '0%'");
            $pdo->exec("UPDATE staging_hdc_ht SET pid = TRIM(LEADING '0' FROM pid) WHERE pid LIKE '0%'");
            
            $stmtInsert3 = $pdo->prepare("INSERT INTO sys_migrations (migration_name) VALUES (?)");
            $stmtInsert3->execute(['normalize_hoscode_pid_20260701']);
        }
    } catch (\Exception $e) {
        // Fail silently
    }
    
    // Migration: Add index on birth to speed up fuzzy matching
    try {
        $stmtMigrationCheck4 = $pdo->prepare("SELECT 1 FROM sys_migrations WHERE migration_name = ?");
        $stmtMigrationCheck4->execute(['add_idx_target_birth_20260701_v2']);
        if (!$stmtMigrationCheck4->fetch()) {
            try {
                $pdo->exec("ALTER TABLE target_population ADD INDEX idx_target_birth (birth)");
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
            $stmtInsert4 = $pdo->prepare("INSERT INTO sys_migrations (migration_name) VALUES (?)");
            $stmtInsert4->execute(['add_idx_target_birth_20260701_v2']);
        }
    } catch (\Exception $e) {
        // Fail silently
    }

    // Migration: Auto approve all waiting rewards
    try {
        $stmtMigrationCheck5 = $pdo->prepare("SELECT 1 FROM sys_migrations WHERE migration_name = ?");
        $stmtMigrationCheck5->execute(['auto_approve_waiting_rewards_20260702']);
        if (!$stmtMigrationCheck5->fetch()) {
            $pdo->exec("UPDATE vhv_rewards SET approval_status = 'approved', approved_at = NOW() WHERE approval_status = 'waiting'");
            $stmtInsert5 = $pdo->prepare("INSERT INTO sys_migrations (migration_name) VALUES (?)");
            $stmtInsert5->execute(['auto_approve_waiting_rewards_20260702']);
        }
    } catch (\Exception $e) {
        // Fail silently
    }
    
    // Check if run
    $stmtMigrationCheck = $pdo->prepare("SELECT 1 FROM sys_migrations WHERE migration_name = ?");
    $stmtMigrationCheck->execute(['merge_masked_duplicates_20260611_v2']);
    if (!$stmtMigrationCheck->fetch()) {
        // Find duplicate pairs where t1 is masked/dummy and t2 is unmasked JHCIS record
        // t1 is duplicate if CID or name has '*' OR CID starts with '0' (dummy CID) OR CID matches the dummy pattern of hoscode+pid
        // t2 is real if CID/name has no '*' AND CID does not start with '0' (Thai citizen ID starts with 1-8, never 0)
        $dupesQuery = $pdo->query("
            SELECT 
                t1.cid AS masked_cid, t1.need_screen_dm AS masked_dm, t1.need_screen_ht AS masked_ht, t1.health_status_origin AS masked_status,
                t2.cid AS real_cid
            FROM target_population t1
            JOIN target_population t2 
              ON LPAD(t1.hoscode, 5, '0') = LPAD(t2.hoscode, 5, '0') 
             AND TRIM(LEADING '0' FROM t1.pid) = TRIM(LEADING '0' FROM t2.pid)
            WHERE (
                t1.cid LIKE '%*%' 
                OR t1.first_name LIKE '%*%' 
                OR t1.cid LIKE '0%' 
                OR t1.cid = CONCAT(LPAD(t1.hoscode, 5, '0'), LPAD(t1.pid, 8, '0'))
                OR t1.cid = CONCAT(LPAD(t1.hoscode, 5, '0'), t1.pid)
              )
              AND (
                t2.cid NOT LIKE '%*%' 
                AND t2.first_name NOT LIKE '%*%' 
                AND t2.cid NOT LIKE '0%' 
                AND t2.cid <> CONCAT(LPAD(t2.hoscode, 5, '0'), LPAD(t2.pid, 8, '0'))
                AND t2.cid <> CONCAT(LPAD(t2.hoscode, 5, '0'), t2.pid)
              )
              AND t1.cid <> t2.cid
              AND t1.pid IS NOT NULL AND t1.pid != ''
        ");
        $dupes = $dupesQuery->fetchAll();
        
        if (!empty($dupes)) {
            $pdo->beginTransaction();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            
            $stmtUpdateReal = $pdo->prepare("
                UPDATE target_population 
                SET 
                    need_screen_dm = CASE WHEN ? = 1 THEN 1 ELSE need_screen_dm END,
                    need_screen_ht = CASE WHEN ? = 1 THEN 1 ELSE need_screen_ht END,
                    health_status_origin = CASE WHEN health_status_origin = 'NORMAL' OR health_status_origin = '' OR health_status_origin IS NULL THEN ? ELSE health_status_origin END,
                    updated_at = NOW()
                WHERE cid = ?
            ");
            
            $stmtGetAssign = $pdo->prepare("SELECT * FROM task_assignments WHERE target_cid = ?");
            $stmtDeleteAssign = $pdo->prepare("DELETE FROM task_assignments WHERE assignment_id = ?");
            $stmtUpdateAssignCid = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE assignment_id = ?");
            
            $stmtGetDpac = $pdo->prepare("SELECT * FROM dpac_enrollments WHERE cid = ?");
            $stmtDeleteDpac = $pdo->prepare("DELETE FROM dpac_enrollments WHERE enrollment_id = ?");
            $stmtUpdateDpacCid = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE enrollment_id = ?");
            
            $stmtDeleteTarget = $pdo->prepare("DELETE FROM target_population WHERE cid = ?");
            
            foreach ($dupes as $dup) {
                $mCid = $dup['masked_cid'];
                $rCid = $dup['real_cid'];
                
                // 1. Copy screening flags to real record
                $stmtUpdateReal->execute([$dup['masked_dm'], $dup['masked_ht'], $dup['masked_status'], $rCid]);
                
                // 2. Merge task assignments
                $stmtGetAssign->execute([$mCid]);
                $mAssigns = $stmtGetAssign->fetchAll();
                
                $stmtGetAssign->execute([$rCid]);
                $rAssigns = $stmtGetAssign->fetchAll();
                
                $rByYear = [];
                foreach ($rAssigns as $ra) {
                    $rByYear[$ra['budget_year']] = $ra;
                }
                
                foreach ($mAssigns as $ma) {
                    $year = $ma['budget_year'];
                    if (isset($rByYear[$year])) {
                        $ra = $rByYear[$year];
                        $checkScreen = $pdo->prepare("SELECT COUNT(*) FROM screening_results WHERE assignment_id = ?");
                        $checkScreen->execute([$ma['assignment_id']]);
                        $hasScreening = $checkScreen->fetchColumn() > 0;
                        
                        if ($hasScreening) {
                            $moveScreen = $pdo->prepare("UPDATE screening_results SET assignment_id = ? WHERE assignment_id = ?");
                            $moveScreen->execute([$ra['assignment_id'], $ma['assignment_id']]);
                        }
                        $stmtDeleteAssign->execute([$ma['assignment_id']]);
                    } else {
                        $stmtUpdateAssignCid->execute([$rCid, $ma['assignment_id']]);
                    }
                }
                
                // 3. Merge DPAC enrollments
                $stmtGetDpac->execute([$mCid]);
                $mDpac = $stmtGetDpac->fetchAll();
                
                $stmtGetDpac->execute([$rCid]);
                $rDpac = $stmtGetDpac->fetchAll();
                
                $rDpacByYear = [];
                foreach ($rDpac as $rd) {
                    $rDpacByYear[$rd['budget_year']] = $rd;
                }
                
                foreach ($mDpac as $md) {
                    $year = $md['budget_year'];
                    if (isset($rDpacByYear[$year])) {
                        $moveFollowups = $pdo->prepare("UPDATE dpac_followups SET enrollment_id = ? WHERE enrollment_id = ?");
                        $moveFollowups->execute([$rDpacByYear[$year]['enrollment_id'], $md['enrollment_id']]);
                        $stmtDeleteDpac->execute([$md['enrollment_id']]);
                    } else {
                        $stmtUpdateDpacCid->execute([$rCid, $md['enrollment_id']]);
                    }
                }
                
                // 4. Delete masked target
                $stmtDeleteTarget->execute([$mCid]);
            }
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $pdo->commit();
        }
        
        // Log migration run
        $stmtInsert = $pdo->prepare("INSERT INTO sys_migrations (migration_name) VALUES (?)");
        $stmtInsert->execute(['merge_masked_duplicates_20260611_v2']);
    }
} catch (\Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Fail silently
}

// ─────────────────────────────────────────────────────────────────────────────
// System Settings Sandbox/Production Config Auto-migration & Helper Functions
// ─────────────────────────────────────────────────────────────────────────────
try {
    if (isset($pdo)) {
        // 1. Auto Create table system_settings
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            description VARCHAR(255) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 2. Insert default sandbox_mode value
        $pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
            VALUES ('sandbox_mode', '1', 'โหมดทดสอบจำลองระบบ (0 = ปิด/ใช้งานจริง, 1 = เปิด/จำลอง)');");

        // 3. Auto-migration: Add sandbox-related columns to support Restore Point mechanism
        try {
            $checkSr = $pdo->query("SHOW COLUMNS FROM `screening_results` LIKE 'is_sandbox'");
            if ($checkSr->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `screening_results` ADD COLUMN `is_sandbox` TINYINT(1) DEFAULT 0");
            }
        } catch (\PDOException $e) {}

        try {
            $checkTa1 = $pdo->query("SHOW COLUMNS FROM `task_assignments` LIKE 'is_sandbox'");
            if ($checkTa1->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `task_assignments` ADD COLUMN `is_sandbox` TINYINT(1) DEFAULT 0");
            }
            $checkTa2 = $pdo->query("SHOW COLUMNS FROM `task_assignments` LIKE 'is_sandbox_completed'");
            if ($checkTa2->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `task_assignments` ADD COLUMN `is_sandbox_completed` TINYINT(1) DEFAULT 0");
            }
        } catch (\PDOException $e) {}

        try {
            $checkWr = $pdo->query("SHOW COLUMNS FROM `vhv_rewards` LIKE 'is_sandbox'");
            if ($checkWr->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `vhv_rewards` ADD COLUMN `is_sandbox` TINYINT(1) DEFAULT 0");
            }
        } catch (\PDOException $e) {}

        try {
            $checkDf1 = $pdo->query("SHOW COLUMNS FROM `dpac_followups` LIKE 'is_sandbox'");
            if ($checkDf1->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `dpac_followups` ADD COLUMN `is_sandbox` TINYINT(1) DEFAULT 0");
            }
            $checkDf2 = $pdo->query("SHOW COLUMNS FROM `dpac_followups` LIKE 'is_sandbox_completed'");
            if ($checkDf2->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `dpac_followups` ADD COLUMN `is_sandbox_completed` TINYINT(1) DEFAULT 0");
            }
        } catch (\PDOException $e) {}

        // 4. Auto-migration for NCD Multi-Round Re-screening
        try {
            $checkTaRound = $pdo->query("SHOW COLUMNS FROM `task_assignments` LIKE 'round_number'")->fetchAll();
            if (empty($checkTaRound)) {
                $pdo->exec("ALTER TABLE `task_assignments` ADD COLUMN `round_number` INT NOT NULL DEFAULT 1");
            }
        } catch (\PDOException $e) {}

        try {
            $checkSrRound = $pdo->query("SHOW COLUMNS FROM `screening_results` LIKE 'round_number'")->fetchAll();
            if (empty($checkSrRound)) {
                $pdo->exec("ALTER TABLE `screening_results` ADD COLUMN `round_number` INT NOT NULL DEFAULT 1");
            }
        } catch (\PDOException $e) {}

        try {
            $indexStmt2 = $pdo->query("SHOW INDEX FROM `task_assignments` WHERE Key_name = 'udx_cid_year_round_sb'")->fetchAll();
            if (empty($indexStmt2)) {
                $pdo->exec("ALTER TABLE `task_assignments` ADD UNIQUE KEY `udx_cid_year_round_sb` (`target_cid`, `budget_year`, `round_number`, `is_sandbox`)");
            }
        } catch (\PDOException $e) {}

        try {
            $indexStmt = $pdo->query("SHOW INDEX FROM `task_assignments` WHERE Key_name = 'udx_cid_year'")->fetchAll();
            if (!empty($indexStmt)) {
                $pdo->exec("ALTER TABLE `task_assignments` DROP INDEX `udx_cid_year`");
            }
        } catch (\PDOException $e) {}

        // 5. Data Durability Migration: Add target_cid directly to screening_results and drop CASCADE constraint
        try {
            $checkSrCid = $pdo->query("SHOW COLUMNS FROM `screening_results` LIKE 'target_cid'")->fetchAll();
            if (empty($checkSrCid)) {
                $pdo->exec("ALTER TABLE `screening_results` ADD COLUMN `target_cid` VARCHAR(13) NULL AFTER `assignment_id`");
            }
        } catch (\PDOException $e) {}

        // 6. DMYST-inspired clinical and sleep migrations
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `system_messages` (
                `message_id` INT AUTO_INCREMENT PRIMARY KEY,
                `sender_username` VARCHAR(50) NOT NULL,
                `sender_name` VARCHAR(100) NOT NULL,
                `sender_role` VARCHAR(20) NOT NULL DEFAULT 'admin',
                `target_type` VARCHAR(20) NOT NULL DEFAULT 'all',
                `target_hcode` VARCHAR(10) NULL,
                `target_sub_district` VARCHAR(6) NULL,
                `title` VARCHAR(255) NOT NULL,
                `message_body` TEXT NOT NULL,
                `priority` ENUM('normal', 'urgent', 'emergency') DEFAULT 'normal',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `system_message_reads` (
                `read_id` INT AUTO_INCREMENT PRIMARY KEY,
                `message_id` INT NOT NULL,
                `reader_id` VARCHAR(50) NOT NULL,
                `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_msg_reader` (`message_id`, `reader_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // screening_results columns
            $srCols = [
                'sleep_quality' => "VARCHAR(20) NOT NULL DEFAULT 'good'",
                'care_level' => "VARCHAR(20) NOT NULL DEFAULT 'good'",
                'next_visit_date' => "DATE NULL",
                'guidance_summary' => "TEXT NULL",
                'health_progress' => "VARCHAR(20) NULL"
            ];
            foreach ($srCols as $col => $type) {
                $chk = $pdo->query("SHOW COLUMNS FROM `screening_results` LIKE '$col'")->fetchAll();
                if (empty($chk)) {
                    $pdo->exec("ALTER TABLE `screening_results` ADD COLUMN `$col` $type");
                }
            }

            // dpac_followups columns
            $dpCols = [
                'sleep_quality' => "VARCHAR(20) NOT NULL DEFAULT 'good'",
                'care_level' => "VARCHAR(20) NOT NULL DEFAULT 'good'",
                'next_visit_date' => "DATE NULL",
                'guidance_summary' => "TEXT NULL",
                'health_progress' => "VARCHAR(20) NULL"
            ];
            foreach ($dpCols as $col => $type) {
                $chk = $pdo->query("SHOW COLUMNS FROM `dpac_followups` LIKE '$col'")->fetchAll();
                if (empty($chk)) {
                    $pdo->exec("ALTER TABLE `dpac_followups` ADD COLUMN `$col` $type");
                }
            }

            // Seed a welcome broadcast message if system_messages is empty
            $msgCount = $pdo->query("SELECT COUNT(*) FROM `system_messages`")->fetchColumn();
            if ($msgCount == 0) {
                $stmtMsg = $pdo->prepare("INSERT INTO `system_messages` (sender_username, sender_name, sender_role, target_type, title, message_body, priority) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtMsg->execute([
                    'admin',
                    'ผู้ดูแลระบบหลัก สสอ.ตาลสุม',
                    'super_admin',
                    'all',
                    'ยินดีต้อนรับสู่ NCD Portal ตาลสุม 2026 (ระบบคัดกรองและดูแลสุขภาพชุมชนเชิงรุก)',
                    'ขอขอบคุณ อสม. และเจ้าหน้าที่ทุกท่านที่ร่วมขับเคลื่อนการคัดกรองและปรับเปลี่ยนพฤติกรรมสุขภาพ (3อ. 2ส. 1น.) เพื่อสุขภาพที่ดีของพี่น้องชาวตาลสุมทุกคน',
                    'normal'
                ]);
            }

            // 7. Citizen Self-Screening Anonymous Logs Table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `citizen_self_screenings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `session_hash` VARCHAR(64) NULL,
                `budget_year` INT NOT NULL DEFAULT 2026,
                `gender` ENUM('male', 'female') NOT NULL DEFAULT 'female',
                `age_group` ENUM('young', 'middle', 'senior') NOT NULL,
                `body_shape` ENUM('thin', 'slim', 'chubby', 'obese') NOT NULL DEFAULT 'slim',
                `sweet_habit` ENUM('low', 'med', 'high') NOT NULL,
                `salt_habit` ENUM('low', 'med', 'high') NOT NULL,
                `veggie_habit` ENUM('good', 'poor') NOT NULL,
                `exercise_habit` ENUM('regular', 'some', 'sedentary') NOT NULL,
                `sleep_habit` ENUM('good', 'poor') NOT NULL,
                `substance_habit` ENUM('none', 'some', 'regular') NOT NULL,
                `family_history` ENUM('no', 'yes') NOT NULL,
                `risk_points` INT NOT NULL DEFAULT 0,
                `risk_level` ENUM('green', 'yellow', 'red') NOT NULL,
                `sub_district_code` VARCHAR(10) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_budget_year` (`budget_year`),
                INDEX `idx_created_at` (`created_at`),
                INDEX `idx_risk_level` (`risk_level`),
                INDEX `idx_gender` (`gender`),
                INDEX `idx_age_group` (`age_group`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            try {
                // Ensure gender column exists if table was previously created
                $pdo->exec("ALTER TABLE `citizen_self_screenings` ADD COLUMN `gender` ENUM('male', 'female') NOT NULL DEFAULT 'female' AFTER `budget_year`;");
            } catch (\PDOException $e) {}

            try {
                $pdo->exec("ALTER TABLE `citizen_self_screenings` MODIFY COLUMN `body_shape` ENUM('thin', 'slim', 'chubby', 'obese') NOT NULL DEFAULT 'slim';");
            } catch (\PDOException $e) {}

            try {
                $pdo->exec("ALTER TABLE `citizen_self_screenings` ADD COLUMN `sub_district_code` VARCHAR(10) NULL AFTER `risk_level`;");
            } catch (\PDOException $e) {}

            // 8. Reward & Redemption System Tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
                `setting_key` VARCHAR(100) PRIMARY KEY,
                `setting_value` TEXT NOT NULL,
                `description` VARCHAR(255) NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // Seed default reward_system_enabled = '0' (disabled by default)
            $pdo->exec("INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `description`) 
                        VALUES ('reward_system_enabled', '0', 'สถานะเปิด/ปิดระบบแลกของรางวัล อสม.');");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `reward_categories` (
                `category_code` VARCHAR(50) PRIMARY KEY,
                `category_name` VARCHAR(100) NOT NULL,
                `icon_emoji` VARCHAR(20) NOT NULL DEFAULT '📦',
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // Seed default categories if empty
            $catCount = $pdo->query("SELECT COUNT(*) FROM `reward_categories`")->fetchColumn();
            if ($catCount == 0) {
                $seedCats = [
                    ['equipment', 'อุปกรณ์ลงพื้นที่', '🧴', 1],
                    ['souvenir', 'ของที่ระลึก อสม.', '☂️', 2],
                    ['medical', 'เครื่องมือแพทย์', '🩺', 3],
                    ['honorary', 'เชิดชูเกียรติ', '🏆', 4]
                ];
                $stmtCatSeed = $pdo->prepare("INSERT IGNORE INTO `reward_categories` (`category_code`, `category_name`, `icon_emoji`, `sort_order`) VALUES (?, ?, ?, ?)");
                foreach ($seedCats as $sc) {
                    $stmtCatSeed->execute($sc);
                }
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS `reward_items` (
                `item_id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(200) NOT NULL,
                `description` TEXT NULL,
                `points_required` INT NOT NULL DEFAULT 10,
                `category` VARCHAR(50) NOT NULL DEFAULT 'equipment',
                `icon_emoji` VARCHAR(20) NOT NULL DEFAULT '🎁',
                `image_url` VARCHAR(255) NULL,
                `stock_quantity` INT NOT NULL DEFAULT -1,
                `redeemed_count` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_category` (`category`),
                INDEX `idx_active` (`is_active`),
                INDEX `idx_points` (`points_required`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `reward_redemptions` (
                `redemption_id` INT AUTO_INCREMENT PRIMARY KEY,
                `redemption_code` VARCHAR(20) NOT NULL,
                `vhv_id` INT NOT NULL,
                `item_id` INT NOT NULL,
                `points_spent` INT NOT NULL,
                `status` ENUM('pending', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending',
                `fulfilled_by` VARCHAR(100) NULL,
                `fulfilled_at` DATETIME NULL,
                `note` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_vhv_id` (`vhv_id`),
                INDEX `idx_item_id` (`item_id`),
                INDEX `idx_status` (`status`),
                INDEX `idx_code` (`redemption_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // Seed default catalog items if empty
            $itemCount = $pdo->query("SELECT COUNT(*) FROM `reward_items`")->fetchColumn();
            if ($itemCount == 0) {
                $seedItems = [
                    [
                        'title' => 'สเปรย์แอลกอฮอล์พกพา + ยาดมสมุนไพร',
                        'description' => 'ชุดเซ็ตพกพาสำหรับ อสม. ลงพื้นที่ พร้อมซองใส่และสายคล้องคอ',
                        'points_required' => 15,
                        'category' => 'equipment',
                        'icon_emoji' => '🧴',
                        'stock_quantity' => 100,
                        'sort_order' => 1
                    ],
                    [
                        'title' => 'ร่มพับกันแดด/กันฝน สกรีน อสม.ตาลสุม',
                        'description' => 'ร่มพับยูวี 3 ตอน แข็งแรง ทนทาน สกรีนตราสัญลักษณ์อำเภอตาลสุม',
                        'points_required' => 30,
                        'category' => 'souvenir',
                        'icon_emoji' => '☂️',
                        'stock_quantity' => 50,
                        'sort_order' => 2
                    ],
                    [
                        'title' => 'หมวกแก๊ป อสม. จิตอาสา นวัตกรรมสุขภาพ',
                        'description' => 'หมวกแก๊ปผ้าคอนตอนเนื้อดี ระบายอากาศ ปักตราสัญลักษณ์ อสม.',
                        'points_required' => 30,
                        'category' => 'equipment',
                        'icon_emoji' => '🧢',
                        'stock_quantity' => 50,
                        'sort_order' => 3
                    ],
                    [
                        'title' => 'กระบอกน้ำสแตนเลสเก็บความเย็น 500ml',
                        'description' => 'กระบอกน้ำเก็บอุณหภูมิร้อน-เย็น พกพาสะดวกสำหรับลงพื้นที่',
                        'points_required' => 35,
                        'category' => 'souvenir',
                        'icon_emoji' => '🥤',
                        'stock_quantity' => 30,
                        'sort_order' => 4
                    ],
                    [
                        'title' => 'เครื่องวัดความดันโลหิตดิจิทัลพกพา (ข้อมือ/ต้นแขน)',
                        'description' => 'เครื่องวัดความดันระบบอัตโนมัติ แม่นยำ อ่านค่าง่าย พกพาสะดวก',
                        'points_required' => 50,
                        'category' => 'medical',
                        'icon_emoji' => '🩺',
                        'stock_quantity' => 20,
                        'sort_order' => 5
                    ],
                    [
                        'title' => 'ชุดแถบตรวจน้ำตาลในเลือด (Strip Test 25 ชิ้น)',
                        'description' => 'ชุดแถบตรวจน้ำตาลปลายนิ้ว พร้อมเข็มเจาะ สำหรับติดตามกลุ่มเสี่ยง',
                        'points_required' => 60,
                        'category' => 'medical',
                        'icon_emoji' => '🩸',
                        'stock_quantity' => 20,
                        'sort_order' => 6
                    ],
                    [
                        'title' => 'โล่เกียรติยศ อสม. ดีเด่นระดับอำเภอตาลสุม',
                        'description' => 'โล่เกียรติยศคริสตัล พร้อมสลักชื่อเชิดชูเกียรติ มอบในงานประชุมประจำปี',
                        'points_required' => 100,
                        'category' => 'honorary',
                        'icon_emoji' => '🏆',
                        'stock_quantity' => 10,
                        'sort_order' => 7
                    ]
                ];

                $stmtSeed = $pdo->prepare("
                    INSERT INTO `reward_items` (`title`, `description`, `points_required`, `category`, `icon_emoji`, `stock_quantity`, `sort_order`)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($seedItems as $si) {
                    $stmtSeed->execute([
                        $si['title'],
                        $si['description'],
                        $si['points_required'],
                        $si['category'],
                        $si['icon_emoji'],
                        $si['stock_quantity'],
                        $si['sort_order']
                    ]);
                }
            }
        } catch (\PDOException $e) {}
    }
} catch (\Exception $e) {
    // Fail silently
}

if (!function_exists('get_system_setting')) {
    function get_system_setting($key, $default = null) {
        global $pdo;
        if (!isset($pdo)) return $default;
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return $val !== false ? $val : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('isSandboxMode')) {
    function isSandboxMode($hoscode = null) {
        if ($hoscode === null && session_status() === PHP_SESSION_ACTIVE) {
            if (isset($_SESSION['hoscode'])) {
                $hoscode = $_SESSION['hoscode'];
            } elseif (isset($_SESSION['admin_hoscode'])) {
                $hoscode = $_SESSION['admin_hoscode'];
            }
        }
        if ($hoscode !== null && $hoscode !== '') {
            $globalVal = get_system_setting('sandbox_mode', '1');
            return get_system_setting('sandbox_mode_' . $hoscode, $globalVal) === '1';
        }
        return get_system_setting('sandbox_mode', '1') === '1';
    }
}

if (!function_exists('get_admin_title')) {
    function get_admin_title() {
        global $pdo;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
        $username = $_SESSION['admin_username'] ?? null;
        
        if ($admin_hoscode) {
            $hc_names = get_health_units();
            return $hc_names[$admin_hoscode] ?? 'รพ.สต.';
        }
        
        if ($username === 'adminsso') {
            return 'ผู้รับผิดชอบระดับอำเภอ';
        }
        
        if ($username) {
            try {
                $stmt = $pdo->prepare("SELECT admin_name FROM admin_users WHERE username = ?");
                $stmt->execute([$username]);
                $name = $stmt->fetchColumn();
                if ($name !== false && $name !== null && trim($name) !== '') {
                    return $name;
                }
            } catch (\Exception $e) {}
        }
        
        return '☠️ ข้าคือชะตาที่มิอาจเลี่ยง!!';
    }
}

// Auto-create critical_alerts table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `critical_alerts` (
        `alert_id` INT AUTO_INCREMENT PRIMARY KEY,
        `screening_id` INT NULL,
        `citizen_screening_id` INT NULL,
        `hoscode` VARCHAR(10) NOT NULL,
        `target_cid` VARCHAR(20) NOT NULL,
        `patient_name` VARCHAR(150) NOT NULL,
        `age` INT DEFAULT NULL,
        `house_no` VARCHAR(50) DEFAULT NULL,
        `moo` VARCHAR(10) DEFAULT NULL,
        `sub_district_code` VARCHAR(10) DEFAULT NULL,
        `latitude` DECIMAL(10,8) DEFAULT NULL,
        `longitude` DECIMAL(11,8) DEFAULT NULL,
        `crisis_type` VARCHAR(50) NOT NULL,
        `sbp` INT DEFAULT NULL,
        `dbp` INT DEFAULT NULL,
        `dtx` INT DEFAULT NULL,
        `red_flags` TEXT DEFAULT NULL,
        `vhv_name` VARCHAR(150) DEFAULT NULL,
        `vhv_phone` VARCHAR(30) DEFAULT NULL,
        `contact_phone` VARCHAR(30) DEFAULT NULL,
        `contact_type` VARCHAR(50) DEFAULT 'vhv',
        `alert_status` VARCHAR(30) DEFAULT 'pending',
        `acknowledged_by` VARCHAR(100) DEFAULT NULL,
        `acknowledged_at` DATETIME DEFAULT NULL,
        `referral_destination` VARCHAR(100) DEFAULT NULL,
        `referral_notes` TEXT DEFAULT NULL,
        `is_jhcis_synced` TINYINT(1) DEFAULT 0,
        `jhcis_visitno` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_hoscode_status (`hoscode`, `alert_status`),
        INDEX idx_created_at (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Auto-migrate new columns if table existed previously
    $chkCol = $pdo->query("SHOW COLUMNS FROM `critical_alerts` LIKE 'contact_phone'")->fetchAll();
    if (empty($chkCol)) {
        $pdo->exec("ALTER TABLE `critical_alerts` ADD COLUMN `contact_phone` VARCHAR(30) DEFAULT NULL AFTER `vhv_phone`");
        $pdo->exec("ALTER TABLE `critical_alerts` ADD COLUMN `contact_type` VARCHAR(50) DEFAULT 'vhv' AFTER `contact_phone`");
    }
} catch (\PDOException $e) {
    // Fail silently
}

