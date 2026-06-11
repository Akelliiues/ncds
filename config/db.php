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
    global $allow_db_failure;
    if (php_sapi_name() === 'cli' || (isset($allow_db_failure) && $allow_db_failure === true)) {
        throw new \PDOException($e->getMessage(), (int) $e->getCode());
    } else {
        // Comment out HTTP 500 to prevent web server/proxy from overriding the custom error card with a generic "HTTP ERROR 500" page
        // header('HTTP/1.1 500 Internal Server Error');
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
if (!file_exists($flag_file)) {
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

if (!file_exists($flag_file)) {
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

    // Write flag file after all migrations run successfully
    @file_put_contents($flag_file, time());
}

