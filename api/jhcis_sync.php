<?php
// api/jhcis_sync.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/station_token_auth.php';

header('Content-Type: application/json; charset=utf-8');

$stationToken = authenticate_station_token($pdo);
$isAdminSession = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!$isAdminSession && !$stationToken) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Access Token ไม่ถูกต้อง หมดอายุ หรือถูกเพิกถอน'], JSON_UNESCAPED_UNICODE);
    exit();
}

$admin_hoscode = $stationToken ? $stationToken['hoscode'] : ($_SESSION['admin_hoscode'] ?? null);
$hc_names = function_exists('get_health_units') ? get_health_units() : [];
$action = $_REQUEST['action'] ?? '';
if ($stationToken && $action !== 'test_connection') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Station Token ใช้ได้เฉพาะการตรวจสอบการเชื่อมต่อ JHCIS'], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Connect to external JHCIS MySQL Database with provided config or saved config
 */
function getJHCISConnection($config) {
    $host = trim($config['jhcis_host'] ?? 'localhost');
    if ($host === 'localhost') {
        $host = '127.0.0.1';
    }
    $port = !empty($config['jhcis_port']) ? (int)$config['jhcis_port'] : 3333;
    $dbname = trim($config['jhcis_dbname'] ?? 'jhcisdb');
    $user = trim($config['jhcis_user'] ?? 'root');
    $pass = $config['jhcis_pass'] ?? '';

    // Fast socket check with 2 seconds timeout to prevent Web Server / Nginx 504 Gateway Timeouts
    $socket = @fsockopen($host, $port, $errno, $errstr, 2);
    if (!$socket) {
        $msg = "ไม่สามารถเชื่อมต่อไปยังเครื่องแม่ข่าย {$host}:{$port} ได้";
        if (in_array($host, ['127.0.0.1', 'localhost'], true) || preg_match('/^(192\.168|10\.|172\.(1[6-9]|2[0-9]|3[01]))\./', $host)) {
            $msg .= " (เนื่องจากเว็บไซต์รันอยู่บนระบบคลาวด์ภายนอก จึงไม่สามารถต่อตรงเข้า IP ท้องถิ่นใน รพ.สต. ได้ แนะนำให้ใช้ปุ่ม 'ส่งออกไฟล์ SQL สำหรับนำเข้า JHCIS' หรือซิงค์ผ่านโปรแกรม RedAlert Station ประจำสถานี)";
        } else {
            $msg .= " ($errstr [$errno])";
        }
        throw new Exception($msg);
    }
    fclose($socket);

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=tis620";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3 // 3 seconds timeout
    ];

    try {
        $jhcisPdo = new PDO($dsn, $user, $pass, $options);
        try {
            $jhcisPdo->exec("SET NAMES tis620");
        } catch (\Throwable $e) {}
        return $jhcisPdo;
    } catch (\PDOException $e) {
        // Retry with utf8 charset in case JHCIS MySQL is converted to UTF8
        $dsnUtf8 = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8";
        try {
            $jhcisPdo = new PDO($dsnUtf8, $user, $pass, $options);
            return $jhcisPdo;
        } catch (\PDOException $e2) {
            throw new Exception("เชื่อมต่อฐานข้อมูล JHCIS ({$dbname}) ไม่สำเร็จ: " . $e->getMessage());
        }
    }
}

/**
 * Helper to auto-detect PCU code and Hospital Name from JHCIS Database
 */
function detectJHCISHospitalInfo($jhcisPdo) {
    $pcucode = '';
    $hosname = '';

    // 1. Try office table
    try {
        $officeColumns = $jhcisPdo->query("SHOW COLUMNS FROM office")->fetchAll(PDO::FETCH_COLUMN);
        $officeNameExpr = in_array('offname', $officeColumns, true) ? 'offname' : "'' AS offname";
        $stmt = $jhcisPdo->query("SELECT offid, {$officeNameExpr} FROM office WHERE offid <> '' AND offid <> '0000x' LIMIT 1");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res && !empty($res['offid'])) {
            $pcucode = trim((string)$res['offid']);
            $hosname = function_exists('safe_tis620_to_utf8') ? safe_tis620_to_utf8($res['offname'] ?? '') : (string)($res['offname'] ?? '');
        }
    } catch (\Throwable $e) {}

    // 2. Try hospital table
    if (empty($pcucode)) {
        try {
            $stmt = $jhcisPdo->query("SELECT hoscode, hosname FROM hospital LIMIT 1");
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res && !empty($res['hoscode'])) {
                $pcucode = trim((string)$res['hoscode']);
                $hosname = function_exists('safe_tis620_to_utf8') ? safe_tis620_to_utf8($res['hosname'] ?? '') : (string)($res['hosname'] ?? '');
            }
        } catch (\Throwable $e) {}
    }

    // 3. Try person table majority pcucode
    if (empty($pcucode)) {
        try {
            $personColumns = getJHCISTableColumns($jhcisPdo, 'person');
            $personPcuColumn = $personColumns['pcucode'] ?? $personColumns['pcucodeperson'] ?? null;
            if (!$personPcuColumn) {
                throw new Exception('No PCU column in person table');
            }
            $stmt = $jhcisPdo->query("SELECT `{$personPcuColumn}` AS pcucode, COUNT(*) as cnt FROM person WHERE `{$personPcuColumn}` IS NOT NULL AND `{$personPcuColumn}` != '' GROUP BY `{$personPcuColumn}` ORDER BY cnt DESC LIMIT 1");
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res && !empty($res['pcucode'])) {
                $pcucode = trim((string)$res['pcucode']);
            }
        } catch (\Throwable $e) {}
    }

    return [
        'pcucode' => $pcucode,
        'hosname' => $hosname
    ];
}

function getJHCISTableColumns($jhcisPdo, $table) {
    $allowed = ['person', 'ncd_person_ncd_screen'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported JHCIS table');
    }
    $columns = [];
    foreach ($jhcisPdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[strtolower($column['Field'])] = $column['Field'];
    }
    return $columns;
}

// -------------------------------------------------------------
// 1. GET CONFIG
// -------------------------------------------------------------
if ($action === 'get_config') {
    $targetHoscode = $_GET['hoscode'] ?? $admin_hoscode;
    if (empty($targetHoscode)) {
        $targetHoscode = 'GLOBAL';
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM jhcis_sync_configs WHERE hoscode = ? LIMIT 1");
        $stmt->execute([$targetHoscode]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            $config = [
                'hoscode' => $targetHoscode,
                'jhcis_host' => 'localhost',
                'jhcis_port' => 3333,
                'jhcis_dbname' => 'jhcisdb',
                'jhcis_user' => 'root',
                'jhcis_pass' => '',
                'date_mode' => 'screening_date',
                'overwrite_mode' => 'skip_existing',
                'cross_hospital_mode' => 'strict',
                'auto_sync_approved' => 0,
                'last_connected_at' => null,
                'last_synced_at' => null
            ];
        }

        // Mask password for display
        $maskedConfig = $config;
        $maskedConfig['jhcis_pass_masked'] = !empty($config['jhcis_pass']) ? '••••••••' : '';

        echo json_encode([
            'status' => 'success',
            'config' => $maskedConfig
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 2. SAVE CONFIG
// -------------------------------------------------------------
if ($action === 'save_config') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $rawHoscode = trim($_POST['hoscode'] ?? '');
    $targetHoscode = !empty($rawHoscode) ? $rawHoscode : ($admin_hoscode ?: 'GLOBAL');
    $host = trim($_POST['jhcis_host'] ?? 'localhost');
    if ($host === '') $host = 'localhost';
    $port = (int)($_POST['jhcis_port'] ?? 3333);
    if ($port <= 0) $port = 3333;
    $dbname = trim($_POST['jhcis_dbname'] ?? 'jhcisdb');
    if ($dbname === '') $dbname = 'jhcisdb';
    $user = trim($_POST['jhcis_user'] ?? 'root');
    if ($user === '') $user = 'root';
    $pass = $_POST['jhcis_pass'] ?? '';
    $dateMode = in_array($_POST['date_mode'] ?? '', ['screening_date', 'sync_date']) ? $_POST['date_mode'] : 'screening_date';
    $overwriteMode = in_array($_POST['overwrite_mode'] ?? '', ['skip_existing', 'update_newer']) ? $_POST['overwrite_mode'] : 'skip_existing';
    $crossHospitalMode = in_array($_POST['cross_hospital_mode'] ?? '', ['strict', 'smart_lookup', 'force_current']) ? $_POST['cross_hospital_mode'] : 'strict';

    try {
        // Ensure table exists
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

        // Check if config exists
        $stmt = $pdo->prepare("SELECT jhcis_pass FROM jhcis_sync_configs WHERE hoscode = ?");
        $stmt->execute([$targetHoscode]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        // If password is blank or '••••••••', keep existing password
        if ($existing && ($pass === '' || $pass === '••••••••')) {
            $pass = $existing['jhcis_pass'];
        }

        $stmtSave = $pdo->prepare("
            INSERT INTO jhcis_sync_configs 
            (hoscode, jhcis_host, jhcis_port, jhcis_dbname, jhcis_user, jhcis_pass, date_mode, overwrite_mode, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                jhcis_host = VALUES(jhcis_host),
                jhcis_port = VALUES(jhcis_port),
                jhcis_dbname = VALUES(jhcis_dbname),
                jhcis_user = VALUES(jhcis_user),
                jhcis_pass = VALUES(jhcis_pass),
                date_mode = VALUES(date_mode),
                overwrite_mode = VALUES(overwrite_mode),
                updated_at = NOW()
        ");
        $stmtSave->execute([$targetHoscode, $host, $port, $dbname, $user, $pass, $dateMode, $overwriteMode]);

        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึกการตั้งค่าการเชื่อมต่อ JHCIS สำเร็จเรียบร้อย'
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'บันทึกไม่สำเร็จ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 3. TEST CONNECTION & DETECT JHCIS PCU CODE
// -------------------------------------------------------------
if ($action === 'test_connection') {
    if ($stationToken && !station_token_can($stationToken, 'jhcis:sync')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Token นี้ไม่มีสิทธิ์เชื่อมต่อ JHCIS'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    $host = trim($_POST['jhcis_host'] ?? 'localhost');
    $port = (int)($_POST['jhcis_port'] ?? 3333);
    $dbname = trim($_POST['jhcis_dbname'] ?? 'jhcisdb');
    $user = trim($_POST['jhcis_user'] ?? 'root');
    $pass = $_POST['jhcis_pass'] ?? '';
    $hoscode = trim($_POST['hoscode'] ?? $admin_hoscode ?? 'GLOBAL');
    if ($stationToken && $stationToken['hoscode'] !== 'ALL') {
        $hoscode = $stationToken['hoscode'];
    }

    // If password is blank or '••••••••', look up from saved config
    if ($pass === '' || $pass === '••••••••') {
        $stmt = $pdo->prepare("SELECT jhcis_pass FROM jhcis_sync_configs WHERE hoscode = ?");
        $stmt->execute([$hoscode]);
        $saved = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($saved) {
            $pass = $saved['jhcis_pass'];
        }
    }

    $isDemo = (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['is_demo_mode']) && $_SESSION['is_demo_mode'] === true);

    if ($isDemo) {
        // Simulated connection in Demo mode
        $detectedPcu = ($hoscode && $hoscode !== 'GLOBAL') ? $hoscode : '';
        $detectedName = $hc_names[$detectedPcu] ?? 'รพ.สต.บ้านสำโรง (จำลอง)';
        echo json_encode([
            'status' => 'success',
            'message' => "เชื่อมต่อฐานข้อมูล JHCIS [{$detectedPcu} {$detectedName}] สำเร็จ 100% (โหมดจำลอง)",
            'db_version' => 'MySQL 5.7.34-JHCIS (Simulated)',
            'tables_found' => ['ncd_person_ncd_screen', 'person', 'visit', 'village', 'office'],
            'detected_pcucode' => $detectedPcu,
            'detected_hosname' => $detectedName,
            'is_demo' => true
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $startTime = microtime(true);
        $jhcisPdo = getJHCISConnection([
            'jhcis_host' => $host,
            'jhcis_port' => $port,
            'jhcis_dbname' => $dbname,
            'jhcis_user' => $user,
            'jhcis_pass' => $pass
        ]);

        $pingTime = round((microtime(true) - $startTime) * 1000, 1);
        $version = $jhcisPdo->query("SELECT VERSION()")->fetchColumn();

        // Check essential tables
        $tablesToCheck = ['ncd_person_ncd_screen', 'person', 'visit', 'office'];
        $tablesFound = [];
        foreach ($tablesToCheck as $tbl) {
            try {
                $checkT = $jhcisPdo->query("SHOW TABLES LIKE '$tbl'")->rowCount();
                if ($checkT > 0) $tablesFound[] = $tbl;
            } catch (\Exception $e) {}
        }

        // Auto-detect PCU code from JHCIS
        $hospInfo = detectJHCISHospitalInfo($jhcisPdo);
        $detectedPcu = $hospInfo['pcucode'];
        $detectedName = $hospInfo['hosname'] ?: ($hc_names[$detectedPcu] ?? $detectedPcu);

        // Update last connected at
        try {
            $pdo->prepare("UPDATE jhcis_sync_configs SET last_connected_at = NOW() WHERE hoscode = ?")->execute([$hoscode]);
        } catch (\Exception $e) {}

        echo json_encode([
            'status' => 'success',
            'message' => "เชื่อมต่อฐานข้อมูล JHCIS ({$dbname}) สำเร็จเรียบร้อย!",
            'db_version' => $version,
            'ping_ms' => $pingTime,
            'tables_found' => $tablesFound,
            'detected_pcucode' => $detectedPcu,
            'detected_hosname' => $detectedName
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 4. GET SYNC PREVIEW & HOSPITAL BREAKDOWN
// -------------------------------------------------------------
if ($action === 'get_sync_preview') {
    $filterHoscode = $_GET['hoscode'] ?? $admin_hoscode;
    $filterMoo = $_GET['moo'] ?? '';
    $syncFilter = $_GET['sync_filter'] ?? 'unsynced'; // unsynced, all

    try {
        $where = ["(p.need_screen_dm = 1 OR p.need_screen_ht = 1)", "s.screening_id IS NOT NULL"];
        $params = [];

        if (!empty($filterHoscode)) {
            $where[] = "COALESCE(v.hoscode, p.hoscode) = ?";
            $params[] = $filterHoscode;
        }

        if (!empty($filterMoo)) {
            $where[] = "p.moo = ?";
            $params[] = $filterMoo;
        }

        if ($syncFilter === 'unsynced') {
            $where[] = "(s.is_synced_jhcis = 0 OR s.is_synced_jhcis IS NULL)";
        }

        $whereClause = implode(" AND ", $where);

        $sql = "
            SELECT 
                COUNT(*) as total_ready,
                SUM(CASE WHEN s.is_synced_jhcis = 1 THEN 1 ELSE 0 END) as already_synced,
                SUM(CASE WHEN s.is_synced_jhcis = 0 OR s.is_synced_jhcis IS NULL THEN 1 ELSE 0 END) as pending_sync,
                MIN(s.screening_date) as earliest_date,
                MAX(s.screening_date) as latest_date
            FROM screening_results s
            JOIN target_population p ON s.target_cid = p.cid
            LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
            WHERE {$whereClause}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch Health Center Breakdown (สถิติจำแนกตาม รพ.สต. ต้นสังกัด)
        $bdSql = "
            SELECT 
                COALESCE(v.hoscode, p.hoscode) as hoscode,
                COUNT(*) as total_count,
                SUM(CASE WHEN s.is_synced_jhcis = 1 THEN 1 ELSE 0 END) as synced_count,
                SUM(CASE WHEN s.is_synced_jhcis = 0 OR s.is_synced_jhcis IS NULL THEN 1 ELSE 0 END) as pending_count
            FROM screening_results s
            JOIN target_population p ON s.target_cid = p.cid
            LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
            WHERE {$whereClause}
            GROUP BY COALESCE(v.hoscode, p.hoscode)
            ORDER BY pending_count DESC, total_count DESC
        ";
        $bdStmt = $pdo->prepare($bdSql);
        $bdStmt->execute($params);
        $breakdownRaw = $bdStmt->fetchAll(PDO::FETCH_ASSOC);

        $breakdown = [];
        $matchedCount = 0;
        $crossHospitalCount = 0;

        foreach ($breakdownRaw as $bRow) {
            $hcCode = $bRow['hoscode'] ?: 'UNKNOWN';
            $hcName = $hc_names[$hcCode] ?? "รพ.สต. รหัส {$hcCode}";
            $isTarget = empty($filterHoscode) || ($hcCode === $filterHoscode);

            if ($isTarget) {
                $matchedCount += (int)$bRow['pending_count'];
            } else {
                $crossHospitalCount += (int)$bRow['pending_count'];
            }

            $breakdown[] = [
                'hoscode' => $hcCode,
                'hosname' => $hcName,
                'total_count' => (int)$bRow['total_count'],
                'synced_count' => (int)$bRow['synced_count'],
                'pending_count' => (int)$bRow['pending_count'],
                'is_target' => $isTarget
            ];
        }

        // Fetch top 15 sample records for preview table
        $sampleSql = "
            SELECT 
                s.screening_id,
                s.target_cid,
                p.first_name,
                p.last_name,
                p.house_no,
                p.moo,
                COALESCE(v.hoscode, p.hoscode) as hoscode,
                s.screening_date,
                s.sys_bp1,
                s.dia_bp1,
                s.dtx_value,
                s.weight,
                s.height,
                s.waist,
                s.is_synced_jhcis,
                s.jhcis_synced_at
            FROM screening_results s
            JOIN target_population p ON s.target_cid = p.cid
            LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
            WHERE {$whereClause}
            ORDER BY s.screening_date DESC
            LIMIT 15
        ";
        $stmtSample = $pdo->prepare($sampleSql);
        $stmtSample->execute($params);
        $samples = $stmtSample->fetchAll(PDO::FETCH_ASSOC);

        foreach ($samples as &$sample) {
            $sample['hosname'] = $hc_names[$sample['hoscode']] ?? $sample['hoscode'];
            $sample['is_matched'] = empty($filterHoscode) || ($sample['hoscode'] === $filterHoscode);
        }
        unset($sample);

        echo json_encode([
            'status' => 'success',
            'summary' => $summary,
            'breakdown' => $breakdown,
            'matched_count' => $matchedCount,
            'cross_hospital_count' => $crossHospitalCount,
            'samples' => $samples
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 5. EXECUTE SYNC BATCH WITH CROSS-HOSPITAL VALIDATION
// -------------------------------------------------------------
if ($action === 'execute_sync') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $filterHoscode = $_POST['hoscode'] ?? $admin_hoscode ?? '';
    $filterMoo = $_POST['moo'] ?? '';
    $dateMode = $_POST['date_mode'] ?? 'screening_date';
    $overwriteMode = $_POST['overwrite_mode'] ?? 'skip_existing';
    $crossHospitalMode = $_POST['cross_hospital_mode'] ?? 'strict'; // strict, smart_lookup, force_current
    $batchLimit = isset($_POST['limit']) ? (int)$_POST['limit'] : 500;

    $isDemo = (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['is_demo_mode']) && $_SESSION['is_demo_mode'] === true);

    $startTime = microtime(true);

    try {
        // Fetch items ready to sync
        $where = ["(p.need_screen_dm = 1 OR p.need_screen_ht = 1)", "s.screening_id IS NOT NULL"];
        $params = [];

        if (!empty($filterHoscode)) {
            $where[] = "COALESCE(v.hoscode, p.hoscode) = ?";
            $params[] = $filterHoscode;
        }
        if (!empty($filterMoo)) {
            $where[] = "p.moo = ?";
            $params[] = $filterMoo;
        }
        $where[] = "(s.is_synced_jhcis = 0 OR s.is_synced_jhcis IS NULL)";

        $whereClause = implode(" AND ", $where);

        $sql = "
            SELECT 
                s.screening_id,
                s.target_cid,
                p.first_name,
                p.last_name,
                p.house_no,
                p.moo,
                COALESCE(v.hoscode, p.hoscode) as hoscode,
                s.screening_date,
                s.sys_bp1,
                s.dia_bp1,
                s.sys_bp2,
                s.dia_bp2,
                s.dtx_value,
                s.dtx_type,
                s.weight,
                s.height,
                s.waist,
                s.smoke_status,
                s.alcohol_status,
                s.vhv_id,
                s.created_at
            FROM screening_results s
            JOIN target_population p ON s.target_cid = p.cid
            LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
            WHERE {$whereClause}
            ORDER BY s.screening_date ASC
            LIMIT {$batchLimit}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalRecords = count($records);
        if ($totalRecords === 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'ไม่มีรายการคัดกรองใหม่ที่รอการซิงค์',
                'total' => 0,
                'success' => 0,
                'skipped' => 0,
                'cross_hospital_skipped' => 0,
                'failed' => 0,
                'duration' => 0
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $successCount = 0;
        $skippedCount = 0;
        $crossHospitalSkipped = 0;
        $failedCount = 0;
        $syncedIds = [];

        if ($isDemo) {
            // Simulated JHCIS Sync in Demo Mode
            foreach ($records as $idx => $r) {
                // If strict mode and record belongs to another hospital, simulate skipping
                if ($crossHospitalMode === 'strict' && !empty($filterHoscode) && $r['hoscode'] !== $filterHoscode) {
                    $crossHospitalSkipped++;
                    continue;
                }
                $syncedIds[] = $r['screening_id'];
                $successCount++;
            }
        } else {
            // Live Real JHCIS MySQL Connection
            $configStmt = $pdo->prepare("SELECT * FROM jhcis_sync_configs WHERE hoscode = ? LIMIT 1");
            $configStmt->execute([$filterHoscode ?: 'GLOBAL']);
            $jhcisConfig = $configStmt->fetch(PDO::FETCH_ASSOC);

            if (!$jhcisConfig) {
                throw new Exception("กรุณาตั้งค่าการเชื่อมต่อฐานข้อมูล JHCIS ก่อนทำการซิงค์");
            }

            $jhcisPdo = getJHCISConnection($jhcisConfig);

            // Auto detect connected JHCIS Hospital PCU code
            $jhcisHosp = detectJHCISHospitalInfo($jhcisPdo);
            $connectedPcu = $jhcisHosp['pcucode'] ?: $filterHoscode;
            if (empty($connectedPcu)) {
                throw new Exception('ไม่พบรหัสสถานบริการจากฐาน JHCIS และไม่ได้ระบุ รพ.สต. สำหรับการซิงค์');
            }

            // JHCIS releases use two different column-name sets.
            $personColumns = getJHCISTableColumns($jhcisPdo, 'person');
            $screenColumns = getJHCISTableColumns($jhcisPdo, 'ncd_person_ncd_screen');
            $personPcuColumn = $personColumns['pcucode'] ?? $personColumns['pcucodeperson'] ?? null;
            $personCidColumn = $personColumns['idcard'] ?? $personColumns['cid'] ?? null;
            if (!$personPcuColumn || !$personCidColumn) {
                throw new Exception('โครงสร้างตาราง person ของ JHCIS ไม่มีคอลัมน์ที่รองรับ');
            }
            $birthExpr = isset($personColumns['birth']) ? 'birth' : 'NULL AS birth';
            $findPidStmt = $jhcisPdo->prepare("SELECT pid, `{$personPcuColumn}` AS pcucode, {$birthExpr} FROM person WHERE `{$personCidColumn}` = ? LIMIT 1");
            $usesModernScreenSchema = isset($screenColumns['screen_date']);

            if ($usesModernScreenSchema) {
                $checkScreenStmt = $jhcisPdo->prepare("SELECT screen_date FROM ncd_person_ncd_screen WHERE pcucode = ? AND pid = ? AND screen_date = ? LIMIT 1");
                $nextScreenNoStmt = $jhcisPdo->prepare("SELECT COALESCE(MAX(no), 0) + 1 FROM ncd_person_ncd_screen WHERE pcucode = ? AND pid = ?");
                $insertScreenStmt = $jhcisPdo->prepare("
                    INSERT INTO ncd_person_ncd_screen (
                        pcucode, pid, no, age_year, screen_date, height, weight, waist,
                        hbp_s1, hbp_d1, screen_q1, screen_q2, screen_q3, screen_q4, screen_q5, screen_q6,
                        do_measure, hbp_s2, hbp_d2, bsl, bmi, result_new_dm, result_new_hbp,
                        result_new_waist, result_new_obesity, d_update, user_update, smoke, alcohol,
                        dateupdate, servplace
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '0', '0', '0', '0', '0', '0',
                        '1', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'VHV', ?, ?, NOW(), '2'
                    )
                ");
            } else {
                $checkScreenStmt = $jhcisPdo->prepare("SELECT datescreen FROM ncd_person_ncd_screen WHERE pcucode = ? AND pid = ? AND datescreen = ? LIMIT 1");
                $insertScreenStmt = $jhcisPdo->prepare("
                    INSERT INTO ncd_person_ncd_screen (
                        pcucode, pid, datescreen, screenplace, weight, height, waist,
                        bps1, bpd1, bps2, bpd2, fbs, smoke, alcohol, officer, dateupdate
                    ) VALUES (?, ?, ?, 2, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'VHV', NOW())
                ");
            }

            foreach ($records as $r) {
                $cid = $r['target_cid'];
                $recordHoscode = $r['hoscode'];

                // 1. Cross-Hospital Policy Check (เช็คความตรงกันของรหัสสถานบริการ)
                if ($crossHospitalMode === 'strict') {
                    // Strict Mode: Check if record hoscode matches connected JHCIS PCU code
                    if (!empty($connectedPcu) && !empty($recordHoscode) && $recordHoscode !== $connectedPcu) {
                        // Skip cross-hospital record so it will be synced to its respective health center's JHCIS
                        $crossHospitalSkipped++;
                        continue;
                    }
                }

                // Determine date to use
                $screenDate = ($dateMode === 'screening_date' && !empty($r['screening_date'])) 
                              ? $r['screening_date'] 
                              : date('Y-m-d');

                try {
                    // Lookup PID & PCU Code in target JHCIS
                    $findPidStmt->execute([$cid]);
                    $personRow = $findPidStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$personRow || empty($personRow['pid'])) {
                        // Person not found in this JHCIS database, skip
                        $skippedCount++;
                        continue;
                    }

                    $pid = $personRow['pid'];
                    $targetPcu = !empty($personRow['pcucode']) ? $personRow['pcucode'] : $connectedPcu;

                    // Check existing screen
                    $checkScreenStmt->execute([$targetPcu, $pid, $screenDate]);
                    $existingDate = $checkScreenStmt->fetchColumn();

                    if ($existingDate) {
                        if ($overwriteMode === 'skip_existing') {
                            $skippedCount++;
                            $syncedIds[] = $r['screening_id']; // Mark as synced so it won't keep retrying
                            continue;
                        }
                    }

                    // Map values safely
                    $weight = !empty($r['weight']) ? (float)$r['weight'] : null;
                    $height = !empty($r['height']) ? (float)$r['height'] : null;
                    $waist = !empty($r['waist']) ? (float)$r['waist'] : null;
                    $bps1 = !empty($r['sys_bp1']) ? (int)$r['sys_bp1'] : null;
                    $bpd1 = !empty($r['dia_bp1']) ? (int)$r['dia_bp1'] : null;
                    $bps2 = !empty($r['sys_bp2']) ? (int)$r['sys_bp2'] : $bps1;
                    $bpd2 = !empty($r['dia_bp2']) ? (int)$r['dia_bp2'] : $bpd1;
                    $fbs = !empty($r['dtx_value']) ? (float)$r['dtx_value'] : null;

                    // Smoking code in JHCIS (1=ไม่สูบ, 2=สูบนานๆครั้ง, 3=สูบประจำ, 4=เคยสูบแต่เลิกแล้ว)
                    $smoke = ($r['smoke_status'] == 1 || $r['smoke_status'] === 'smoke') ? '3' : '1';
                    // Alcohol code in JHCIS (1=ไม่ดื่ม, 2=ดื่มนานๆครั้ง, 3=ดื่มประจำ, 4=เลิกแล้ว)
                    $alcohol = ($r['alcohol_status'] == 1 || $r['alcohol_status'] === 'drink') ? '3' : '1';

                    if ($usesModernScreenSchema) {
                        $nextScreenNoStmt->execute([$targetPcu, $pid]);
                        $screenNo = (int)$nextScreenNoStmt->fetchColumn();
                        $birthDate = !empty($personRow['birth']) ? new DateTime($personRow['birth']) : null;
                        $ageYear = $birthDate ? $birthDate->diff(new DateTime($screenDate))->y : 0;
                        $bmi = ($weight && $height) ? round($weight / (($height / 100) ** 2), 3) : 0;
                        $dmResult = ($fbs !== null && $fbs >= 126) ? '2' : '1';
                        $hbpResult = (($bps1 !== null && $bps1 >= 140) || ($bpd1 !== null && $bpd1 >= 90)) ? '2' : '1';
                        $waistResult = ($waist !== null && $waist >= 90) ? '2' : '1';
                        $obesityResult = ($bmi >= 25) ? '2' : '1';
                        $insertScreenStmt->execute([
                            $targetPcu, $pid, $screenNo, $ageYear, $screenDate,
                            $height ?: 0, $weight ?: 0, $waist ?: 0, $bps1 ?: 0, $bpd1 ?: 0,
                            $bps2, $bpd2, $fbs, $bmi,
                            $dmResult, $hbpResult, $waistResult, $obesityResult,
                            $screenDate, $smoke, $alcohol
                        ]);
                    } else {
                        $insertScreenStmt->execute([
                            $targetPcu, $pid, $screenDate,
                            $weight, $height, $waist,
                            $bps1, $bpd1, $bps2, $bpd2,
                            $fbs, $smoke, $alcohol
                        ]);
                    }

                    $successCount++;
                    $syncedIds[] = $r['screening_id'];
                } catch (\Exception $exItem) {
                    $failedCount++;
                }
            }
        }

        // Mark synced records in NCDs Portal
        if (!empty($syncedIds)) {
            $idPlaceholders = implode(',', array_fill(0, count($syncedIds), '?'));
            $pdo->prepare("UPDATE screening_results SET is_synced_jhcis = 1, jhcis_synced_at = NOW() WHERE screening_id IN ($idPlaceholders)")
                ->execute($syncedIds);
        }

        $duration = round(microtime(true) - $startTime, 2);

        // Record Sync Log
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO jhcis_sync_logs 
                (hoscode, sync_type, date_range, total_records, success_records, skipped_records, failed_records, duration_seconds, synced_by, created_at)
                VALUES (?, 'batch_direct', ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $dateRangeStr = ($records[0]['screening_date'] ?? '') . ' ถึง ' . ($records[count($records)-1]['screening_date'] ?? '');
            $adminUser = $_SESSION['admin_username'] ?? 'Admin';
            $logStmt->execute([
                $filterHoscode ?: 'ALL',
                $dateRangeStr,
                $totalRecords,
                $successCount,
                $skippedCount + $crossHospitalSkipped,
                $failedCount,
                $duration,
                $adminUser
            ]);

            // Update last_synced_at in config
            $pdo->prepare("UPDATE jhcis_sync_configs SET last_synced_at = NOW() WHERE hoscode = ?")
                ->execute([$filterHoscode ?: 'GLOBAL']);
        } catch (\Exception $e) {}

        $msg = "ซิงค์ข้อมูลเข้า JHCIS สำเร็จ {$successCount} รายการ";
        if ($crossHospitalSkipped > 0) {
            $msg .= " (ข้ามข้อมูลต่าง รพ.สต. {$crossHospitalSkipped} รายการ)";
        }
        $msg .= " (ใช้เวลา {$duration} วินาที)";

        echo json_encode([
            'status' => 'success',
            'message' => $msg,
            'total' => $totalRecords,
            'success' => $successCount,
            'skipped' => $skippedCount,
            'cross_hospital_skipped' => $crossHospitalSkipped,
            'failed' => $failedCount,
            'duration' => $duration
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการซิงค์ข้อมูล: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// -------------------------------------------------------------
// 6. GET SYNC LOGS
// -------------------------------------------------------------
if ($action === 'get_logs') {
    $filterHoscode = $_GET['hoscode'] ?? $admin_hoscode;
    try {
        $where = "1=1";
        $params = [];
        if (!empty($filterHoscode)) {
            $where .= " AND (hoscode = ? OR hoscode = 'ALL' OR hoscode = 'GLOBAL')";
            $params[] = $filterHoscode;
        }

        $stmt = $pdo->prepare("SELECT * FROM jhcis_sync_logs WHERE {$where} ORDER BY created_at DESC LIMIT 30");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'logs' => $logs
        ], JSON_UNESCAPED_UNICODE);
// -------------------------------------------------------------
// 7. EXPORT SQL SCRIPT (FOR JHCIS QUERY CENTER / HEIDISQL / NAVICAT)
// -------------------------------------------------------------
if ($action === 'export_sql') {
    $filterHoscode = $_GET['hoscode'] ?? $admin_hoscode ?? '';
    $filterMoo = $_GET['moo'] ?? '';
    $markSynced = !empty($_GET['mark_synced']);

    try {
        $where = ["(p.need_screen_dm = 1 OR p.need_screen_ht = 1)", "s.screening_id IS NOT NULL"];
        $params = [];

        if (!empty($filterHoscode)) {
            $where[] = "COALESCE(v.hoscode, p.hoscode) = ?";
            $params[] = $filterHoscode;
        }
        if (!empty($filterMoo)) {
            $where[] = "p.moo = ?";
            $params[] = $filterMoo;
        }
        $where[] = "(s.is_synced_jhcis = 0 OR s.is_synced_jhcis IS NULL)";

        $whereClause = implode(" AND ", $where);

        $sql = "
            SELECT 
                s.screening_id,
                s.target_cid,
                p.first_name,
                p.last_name,
                p.house_no,
                p.moo,
                COALESCE(v.hoscode, p.hoscode) as hoscode,
                s.screening_date,
                s.sys_bp1,
                s.dia_bp1,
                s.sys_bp2,
                s.dia_bp2,
                s.dtx_value,
                s.dtx_type,
                s.weight,
                s.height,
                s.waist,
                s.smoke_status,
                s.alcohol_status,
                s.vhv_id
            FROM screening_results s
            JOIN target_population p ON s.target_cid = p.cid
            LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
            WHERE {$whereClause}
            ORDER BY s.screening_date ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dateStr = date('Y-m-d_His');
        $filename = "jhcis_ncd_screen_sync_" . ($filterHoscode ?: 'all') . "_{$dateStr}.sql";

        header('Content-Type: text/plain; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        echo "-- =========================================================================\n";
        echo "-- NCDs Portal - JHCIS Screening Data Import Script\n";
        echo "-- รพ.สต. / หน่วยบริการ: " . ($filterHoscode ? "[{$filterHoscode}] " . ($hc_names[$filterHoscode] ?? '') : "ทุก รพ.สต.") . "\n";
        echo "-- วันที่สร้าง: " . date('d/m/Y H:i:s') . "\n";
        echo "-- จำนวนรายการ: " . count($records) . " รายการ\n";
        echo "-- NCDS-SCREENING-IDS: " . implode(',', array_column($records, 'screening_id')) . "\n";
        echo "-- วิธีใช้งาน:\n";
        echo "-- 1. เปิดโปรแกรมจัดการฐานข้อมูล เช่น HeidiSQL, Navicat หรือ JHCIS Query Tool\n";
        echo "-- 2. เชื่อมต่อฐานข้อมูล jhcisdb\n";
        echo "-- 3. Run คำสั่ง SQL ด้านล่างนี้ทั้งหมดเพื่อนำเข้าข้อมูลผลคัดกรอง NCD\n";
        echo "-- =========================================================================\n\n";

        echo "SET NAMES tis620;\n\n";

        $syncedIds = [];
        foreach ($records as $r) {
            $cid = addslashes($r['target_cid']);
            $hCode = addslashes($r['hoscode'] ?: $filterHoscode);
            $sDate = !empty($r['screening_date']) ? $r['screening_date'] : date('Y-m-d');
            $sBp1 = $r['sys_bp1'] !== null && $r['sys_bp1'] !== '' ? (int)$r['sys_bp1'] : 'NULL';
            $dBp1 = $r['dia_bp1'] !== null && $r['dia_bp1'] !== '' ? (int)$r['dia_bp1'] : 'NULL';
            $sBp2 = $r['sys_bp2'] !== null && $r['sys_bp2'] !== '' ? (int)$r['sys_bp2'] : 'NULL';
            $dBp2 = $r['dia_bp2'] !== null && $r['dia_bp2'] !== '' ? (int)$r['dia_bp2'] : 'NULL';
            $dtx = $r['dtx_value'] !== null && $r['dtx_value'] !== '' ? (float)$r['dtx_value'] : 'NULL';
            $wt = $r['weight'] !== null && $r['weight'] !== '' ? (float)$r['weight'] : 'NULL';
            $ht = $r['height'] !== null && $r['height'] !== '' ? (float)$r['height'] : 'NULL';
            $wst = $r['waist'] !== null && $r['waist'] !== '' ? (float)$r['waist'] : 'NULL';

            $smoke = ($r['smoke_status'] === 'smoke' || $r['smoke_status'] === 'sometimes') ? '2' : '1';
            $alcohol = ($r['alcohol_status'] === 'drink' || $r['alcohol_status'] === 'sometimes') ? '2' : '1';

            $nameComment = "-- [{$r['target_cid']}] {$r['first_name']} {$r['last_name']} (บ้าน {$r['house_no']} ม.{$r['moo']})";

            echo "{$nameComment}\n";
            echo "INSERT INTO ncd_person_ncd_screen (\n";
            echo "    pcucode, pid, no, age_year, screen_date, height, weight, waist,\n";
            echo "    hbp_s1, hbp_d1, screen_q1, screen_q2, screen_q3, screen_q4, screen_q5, screen_q6,\n";
            echo "    do_measure, hbp_s2, hbp_d2, bsl, bmi, result_new_dm, result_new_hbp,\n";
            echo "    result_new_waist, result_new_obesity, d_update, user_update, smoke, alcohol,\n";
            echo "    dateupdate, servplace\n";
            echo ")\n";
            echo "SELECT \n";
            echo "    p.pcucodeperson, p.pid,\n";
            echo "    (SELECT COALESCE(MAX(no), 0) + 1 FROM ncd_person_ncd_screen s WHERE s.pcucode = p.pcucodeperson AND s.pid = p.pid),\n";
            echo "    TIMESTAMPDIFF(YEAR, p.birth, '{$sDate}'),\n";
            echo "    '{$sDate}', {$ht}, {$wt}, {$wst},\n";
            echo "    {$sBp1}, {$dBp1}, '0', '0', '0', '0', '0', '0',\n";
            echo "    '1', {$sBp2}, {$dBp2}, {$dtx}, \n";
            echo "    CASE WHEN {$wt} IS NOT NULL AND {$ht} IS NOT NULL AND {$ht} > 0 THEN ROUND({$wt} / (({$ht}/100)*({$ht}/100)), 2) ELSE NULL END,\n";
            echo "    0, 0, 0, 0, NOW(), 'VHV', '{$smoke}', '{$alcohol}', NOW(), '2'\n";
            echo "FROM person p\n";
            echo "WHERE p.idcard = '{$cid}'\n";
            echo "  AND NOT EXISTS (SELECT 1 FROM ncd_person_ncd_screen ex WHERE ex.pcucode = p.pcucodeperson AND ex.pid = p.pid AND ex.screen_date = '{$sDate}')\n";
            echo "LIMIT 1;\n\n";

            $syncedIds[] = $r['screening_id'];
        }

        if ($markSynced && !empty($syncedIds)) {
            $idPlaceholders = implode(',', array_fill(0, count($syncedIds), '?'));
            $pdo->prepare("UPDATE screening_results SET is_synced_jhcis = 1, jhcis_synced_at = NOW() WHERE screening_id IN ($idPlaceholders)")
                ->execute($syncedIds);
        }
        exit();
    } catch (\Exception $e) {
        echo "-- ERROR: " . $e->getMessage();
        exit();
    }
}

// Confirm only records that the local Station has already committed to JHCIS.
if ($action === 'confirm_local_sync') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    $filterHoscode = trim($_POST['hoscode'] ?? $admin_hoscode ?? '');
    $rawIds = $_POST['screening_ids'] ?? '';
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawIds)), function ($id) { return $id > 0; })));
    if (!$filterHoscode || !$ids || count($ids) > 1000) {
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลยืนยันการซิงค์ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$filterHoscode]);
        $stmt = $pdo->prepare("UPDATE screening_results s
            JOIN target_population p ON s.target_cid = p.cid
            LEFT JOIN villages v ON p.sub_district_code = v.sub_district_code AND CAST(p.moo AS UNSIGNED) = v.moo
            SET s.is_synced_jhcis = 1, s.jhcis_synced_at = NOW()
            WHERE s.screening_id IN ({$placeholders}) AND COALESCE(v.hoscode, p.hoscode) = ?");
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'confirmed' => $stmt->rowCount()], JSON_UNESCAPED_UNICODE);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

// Invalid action
echo json_encode(['status' => 'error', 'message' => 'Invalid action specified'], JSON_UNESCAPED_UNICODE);
