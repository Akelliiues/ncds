<?php
// config/activity_logger.php
// Centralized User Activity & Dashboard Audit Log Engine

if (!function_exists('ensureActivityLogTable')) {
    function ensureActivityLogTable($pdo) {
        static $checked = false;
        if ($checked || !$pdo) return;
        if ($pdo->inTransaction()) {
            return;
        }
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_activity_logs (
                    log_id          BIGINT AUTO_INCREMENT PRIMARY KEY,
                    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    user_type       ENUM('staff', 'vhv', 'executive') NOT NULL,
                    username        VARCHAR(50) NOT NULL,
                    user_fullname   VARCHAR(120) DEFAULT NULL,
                    hoscode         VARCHAR(10) DEFAULT NULL,
                    hosname         VARCHAR(150) DEFAULT NULL,
                    action_category ENUM('AUTH', 'SCREENING', 'ASSIGNMENT', 'DPAC', 'IMPORT_SYNC', 'BROADCAST', 'SETTINGS', 'REPORTS') NOT NULL,
                    action_title    VARCHAR(150) NOT NULL,
                    action_detail   TEXT DEFAULT NULL,
                    ip_address      VARCHAR(45) DEFAULT NULL,
                    user_agent      VARCHAR(255) DEFAULT NULL,
                    status          ENUM('success', 'warning', 'failed') NOT NULL DEFAULT 'success',
                    INDEX idx_created_at (created_at),
                    INDEX idx_user_type (user_type),
                    INDEX idx_hoscode (hoscode),
                    INDEX idx_action_category (action_category)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $checked = true;
        } catch (\Throwable $e) {
            // Ignore if table already exists or permission issue
        }
    }
}

if (!function_exists('logUserActivity')) {
    /**
     * Record a user action into user_activity_logs.
     * STRICT RULE: Excludes primary Super Admin (สสอ. ตาลสุม) from being logged.
     *
     * @param string $category 'AUTH'|'SCREENING'|'ASSIGNMENT'|'DPAC'|'IMPORT_SYNC'|'BROADCAST'|'SETTINGS'|'REPORTS'
     * @param string $title Short human-readable title
     * @param mixed $detail Optional description string or array
     * @param string $status 'success'|'warning'|'failed'
     * @param array|null $customUser Optional custom user data override
     * @return bool True if logged, False if ignored/failed
     */
    function logUserActivity($category, $title, $detail = null, $status = 'success', $customUser = null) {
        // 1. STRICT EXCLUSION: Do not log primary Super Admin!
        if (session_status() === PHP_SESSION_ACTIVE) {
            $isSuperAdmin = !empty($_SESSION['is_super_admin']) || (!empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_hoscode']));
            if ($isSuperAdmin && empty($customUser)) {
                return false; // Skip main Super Admin completely
            }
        }

        global $pdo;
        if (!$pdo) {
            try {
                require_once __DIR__ . '/db.php';
            } catch (\Throwable $e) {
                return false;
            }
        }
        if (!$pdo) return false;

        ensureActivityLogTable($pdo);

        // 2. Resolve User Context
        $userType = 'staff';
        $username = 'unknown';
        $userFullname = 'ผู้ใช้งาน';
        $hoscode = null;
        $hosname = null;

        if (!empty($customUser) && is_array($customUser)) {
            $userType = $customUser['user_type'] ?? 'staff';
            $username = $customUser['username'] ?? 'unknown';
            $userFullname = $customUser['user_fullname'] ?? null;
            $hoscode = $customUser['hoscode'] ?? null;
            $hosname = $customUser['hosname'] ?? null;
        } elseif (session_status() === PHP_SESSION_ACTIVE) {
            if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_hoscode'])) {
                $userType = 'staff';
                $username = $_SESSION['admin_username'] ?? ('hos_' . $_SESSION['admin_hoscode']);
                $hoscode = $_SESSION['admin_hoscode'];
                $hcNames = function_exists('get_health_units') ? get_health_units() : [];
                $hosname = $hcNames[$hoscode] ?? ('รพ.สต. ' . $hoscode);
                $userFullname = function_exists('get_admin_title') ? get_admin_title() : $hosname;
            } elseif (!empty($_SESSION['vhv_id']) || !empty($_SESSION['vhv_cid'])) {
                $userType = 'vhv';
                $username = $_SESSION['vhv_cid'] ?? (string)$_SESSION['vhv_id'];
                $userFullname = $_SESSION['vhv_name'] ?? 'อสม.';
                $hoscode = $_SESSION['hoscode'] ?? null;
                $hcNames = function_exists('get_health_units') ? get_health_units() : [];
                $hosname = $hoscode ? ($hcNames[$hoscode] ?? ('รพ.สต. ' . $hoscode)) : null;
            } elseif (!empty($_SESSION['is_executive']) || !empty($_SESSION['is_visitor'])) {
                $userType = 'executive';
                $username = $_SESSION['executive_username'] ?? 'visitor';
                $userFullname = 'ผู้บริหาร / ผู้เข้าชม';
            } else {
                return false; // Anonymous unauthenticated action, skip
            }
        } else {
            return false;
        }

        // 3. Prepare IP & Client Info
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ip = substr($ip, 0, 45);

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ua = substr($ua, 0, 255);

        // 4. Format Detail
        $detailStr = null;
        if ($detail !== null) {
            if (is_array($detail) || is_object($detail)) {
                $detailStr = json_encode($detail, JSON_UNESCAPED_UNICODE);
            } else {
                $detailStr = (string)$detail;
            }
        }

        // 5. Insert Log
        try {
            $stmt = $pdo->prepare("
                INSERT INTO user_activity_logs 
                (created_at, user_type, username, user_fullname, hoscode, hosname, action_category, action_title, action_detail, ip_address, user_agent, status)
                VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userType,
                $username,
                $userFullname,
                $hoscode,
                $hosname,
                $category,
                $title,
                $detailStr,
                $ip,
                $ua,
                $status
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
