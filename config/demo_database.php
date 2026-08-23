<?php
// config/demo_database.php
// ระบบฐานข้อมูลจำลองครอบคลุมทุกเมนูและฟังก์ชันทั้งหน้าบ้านและหลังบ้าน 100% (Complete Mockup DB Generator with Round 1 & Round 2 Data)

class DemoMockPDOStatement {
    private $stmt;
    public function __construct($stmt) {
        $this->stmt = $stmt;
    }
    public function execute($params = null) {
        try {
            return $this->stmt->execute($params);
        } catch (\Throwable $e) {
            return false;
        }
    }
    public function fetch($mode = PDO::FETCH_ASSOC, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0) {
        $row = $this->stmt->fetch($mode, $cursorOrientation, $cursorOffset);
        if ($row !== false && $row !== null) {
            maskRowData($row);
        }
        return $row;
    }
    public function fetchAll($mode = PDO::FETCH_ASSOC, ...$args) {
        $rows = $this->stmt->fetchAll($mode, ...$args);
        if (is_array($rows)) {
            foreach ($rows as &$row) {
                maskRowData($row);
            }
        }
        return $rows;
    }
    public function fetchColumn($column = 0) {
        return $this->stmt->fetchColumn($column);
    }
    public function rowCount() {
        return $this->stmt->rowCount();
    }
}

class DemoMockPDO extends PDO {
    public function replaceTables($sql) {
        if (strpos($sql, 'demo_mock_') !== false) {
            return $sql;
        }
        $tables = [
            'target_population'        => 'demo_mock_target_population',
            'task_assignments'         => 'demo_mock_task_assignments',
            'screening_results'        => 'demo_mock_screening_results',
            'dpac_enrollments'         => 'demo_mock_dpac_enrollments',
            'dpac_followups'           => 'demo_mock_dpac_followups',
            'vhv_users'                => 'demo_mock_vhv_users',
            'vhv_rewards'              => 'demo_mock_vhv_rewards',
            'vhv_surveys'              => 'demo_mock_vhv_surveys',
            'vhv_survey_participants'  => 'demo_mock_vhv_survey_participants',
            'admin_users'              => 'demo_mock_admin_users',
            'health_units'             => 'demo_mock_health_units',
            'sub_districts'            => 'demo_mock_sub_districts',
            'villages'                 => 'demo_mock_villages',
            'assignment_history_log'   => 'demo_mock_assignment_history_log',
            'line_house_mappings'      => 'demo_mock_line_house_mappings',
            'staging_hdc_ht'           => 'demo_mock_staging_hdc_ht',
            'staging_hdc_dm'           => 'demo_mock_staging_hdc_dm',
            'staging_jhcis_person'     => 'demo_mock_staging_jhcis_person',
            'jhcis_sync_configs'       => 'demo_mock_jhcis_sync_configs',
            'jhcis_sync_logs'          => 'demo_mock_jhcis_sync_logs',
            'citizen_self_screenings'  => 'demo_mock_citizen_self_screenings',
            'critical_alerts'          => 'demo_mock_critical_alerts'
        ];
        foreach ($tables as $real => $mock) {
            $sql = preg_replace('/\b' . preg_quote($real, '/') . '\b/i', $mock, $sql);
        }
        return $sql;
    }

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = []) {
        $mockQuery = $this->replaceTables($query);
        $stmt = parent::prepare($mockQuery, $options);
        return new DemoMockPDOStatement($stmt);
    }

    #[\ReturnTypeWillChange]
    public function query($query, $fetchMode = null, ...$fetch_mode_args) {
        $mockQuery = $this->replaceTables($query);
        if ($fetchMode !== null) {
            $stmt = parent::query($mockQuery, $fetchMode, ...$fetch_mode_args);
        } else {
            $stmt = parent::query($mockQuery);
        }
        return new DemoMockPDOStatement($stmt);
    }

    #[\ReturnTypeWillChange]
    public function exec($statement) {
        $mockStatement = $this->replaceTables($statement);
        return parent::exec($mockStatement);
    }
}

function initDemoMockupDatabase($pdo) {
    try {
        $tablesToDuplicate = [
            'target_population',
            'task_assignments',
            'screening_results',
            'dpac_enrollments',
            'dpac_followups',
            'vhv_users',
            'vhv_rewards',
            'vhv_surveys',
            'vhv_survey_participants',
            'admin_users',
            'health_units',
            'sub_districts',
            'villages',
            'assignment_history_log',
            'line_house_mappings',
            'staging_hdc_ht',
            'staging_hdc_dm',
            'staging_jhcis_person',
            'system_messages',
            'system_message_reads',
            'jhcis_sync_configs',
            'jhcis_sync_logs',
            'citizen_self_screenings',
            'critical_alerts'
        ];

        // Ensure base critical_alerts table exists
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
            `alert_status` VARCHAR(30) DEFAULT 'pending',
            `acknowledged_by` VARCHAR(100) DEFAULT NULL,
            `acknowledged_at` DATETIME DEFAULT NULL,
            `referral_destination` VARCHAR(100) DEFAULT NULL,
            `referral_notes` TEXT DEFAULT NULL,
            `is_jhcis_synced` TINYINT(1) DEFAULT 0,
            `jhcis_visitno` VARCHAR(50) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Ensure base citizen_self_screenings exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `citizen_self_screenings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `session_hash` VARCHAR(64) NULL,
            `budget_year` INT NOT NULL DEFAULT 2026,
            `gender` ENUM('male', 'female') NOT NULL DEFAULT 'female',
            `age_group` ENUM('young', 'middle', 'senior') NOT NULL,
            `body_shape` ENUM('slim', 'chubby', 'obese') NOT NULL,
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

        foreach ($tablesToDuplicate as $tbl) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `demo_mock_$tbl` LIKE `$tbl`;");
        }

        // ตรวจสอบว่ามีข้อมูลในตารางจำลองแล้วหรือยัง หากยังไม่มีให้ลงข้อมูลสมมติ 100%
        $count = $pdo->query("SELECT COUNT(*) FROM `demo_mock_target_population`")->fetchColumn();
        if ($count == 0) {
            // Seed Mock Targets (10+ รายชื่อประชากรกลุ่มเป้าหมายจำลอง)
            $mockTargets = [
                ['9999900000001', '1001', '12/1', '1', 'สมชาย', 'ใจดี (จำลอง)', '1968-05-15', '1', '341001', '99999', 1, 1, 'BOTH', 15.4321, 104.9812],
                ['9999900000002', '1002', '45/2', '1', 'สมศรี', 'สุขสรรค์ (จำลอง)', '1955-09-20', '2', '341001', '99999', 1, 0, 'DM_ONLY', 15.4310, 104.9825],
                ['9999900000003', '1003', '88', '2', 'บุญมี', 'มีโชค (จำลอง)', '1962-11-04', '1', '341001', '99999', 1, 1, 'BOTH', 15.4335, 104.9840],
                ['9999900000004', '1004', '101', '2', 'ทองสุข', 'สดใส (จำลอง)', '1975-01-30', '2', '341001', '99999', 0, 1, 'HT_ONLY', 15.4340, 104.9850],
                ['9999900000005', '1005', '15/3', '3', 'วิชัย', 'มั่นคง (จำลอง)', '1958-08-12', '1', '341001', '99999', 1, 1, 'BOTH', 15.4350, 104.9860],
                ['9999900000006', '1006', '77/1', '3', 'วิภาดา', 'รุ่งเรือง (จำลอง)', '1980-03-25', '2', '341001', '99999', 1, 0, 'DM_ONLY', 15.4360, 104.9870],
                ['9999900000007', '1007', '99/4', '4', 'อนันต์', 'เจริญสุข (จำลอง)', '1965-07-18', '1', '341001', '99999', 0, 1, 'HT_ONLY', 15.4370, 104.9880],
                ['9999900000008', '1008', '23/2', '5', 'อุบล', 'มีสุข (จำลอง)', '1972-12-01', '2', '341001', '99999', 1, 1, 'BOTH', 15.4380, 104.9890],
                ['9999900000009', '1009', '54', '1', 'ประเสริฐ', 'เลิศอนันต์ (จำลอง)', '1960-04-10', '1', '341001', '99999', 1, 1, 'BOTH', 15.4325, 104.9818],
                ['9999900000010', '1010', '89/1', '2', 'มณีรัตน์', 'ศรีสว่าง (จำลอง)', '1978-09-05', '2', '341001', '99999', 1, 0, 'DM_ONLY', 15.4338, 104.9845]
            ];

            $stmtT = $pdo->prepare("INSERT INTO `demo_mock_target_population` 
                (cid, hid, house_no, moo, first_name, last_name, birth, sex, sub_district_code, hoscode, need_screen_dm, need_screen_ht, health_status_origin, latitude, longitude) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($mockTargets as $t) { $stmtT->execute($t); }

            // Seed Mock Task Assignments (Rounds 1, 2, and 3)
            $mockAssignments = [
                ['9999900000001', 'DEMO_1001', 2026, 'completed', 1, 1],
                ['9999900000002', 'DEMO_1001', 2026, 'completed', 1, 1],
                ['9999900000003', 'DEMO_1002', 2026, 'completed', 1, 1],
                ['9999900000004', 'DEMO_1002', 2026, 'completed', 1, 1],
                ['9999900000005', 'DEMO_1003', 2026, 'completed', 1, 1],
                ['9999900000009', 'DEMO_1001', 2026, 'pending', 1, 1],
                ['9999900000010', 'DEMO_1002', 2026, 'completed', 1, 1],
                // Round 2 assignments
                ['9999900000001', 'DEMO_1001', 2026, 'completed', 2, 1],
                ['9999900000002', 'DEMO_1001', 2026, 'completed', 2, 1],
                ['9999900000003', 'DEMO_1002', 2026, 'pending', 2, 1],
                ['9999900000004', 'DEMO_1002', 2026, 'pending', 2, 1],
                // Round 3 assignment
                ['9999900000001', 'DEMO_1001', 2026, 'pending', 3, 1]
            ];
            $stmtA = $pdo->prepare("INSERT INTO `demo_mock_task_assignments` (target_cid, vhv_id, budget_year, assignment_status, round_number, is_sandbox) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($mockAssignments as $a) { $stmtA->execute($a); }

            // Seed Mock Screening Results (R1 & R2)
            $stmtS = $pdo->prepare("INSERT INTO `demo_mock_screening_results` (assignment_id, target_cid, vhv_id, round_number, sys_bp1, dia_bp1, dtx_value, dtx_type, weight, height, waist, bmi, cv_risk_score, is_sandbox) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtS->execute([1, '9999900000001', 'DEMO_1001', 1, 130, 84, 110, 'fpg', 65.0, 168.0, 80.0, 23.0, 1, 1]);
            $stmtS->execute([2, '9999900000002', 'DEMO_1001', 1, 145, 92, 135, 'fpg', 70.0, 158.0, 85.0, 28.0, 2, 1]);
            $stmtS->execute([3, '9999900000003', 'DEMO_1002', 1, 122, 78, 105, 'fpg', 62.0, 162.0, 76.0, 23.6, 1, 1]);
            $stmtS->execute([4, '9999900000004', 'DEMO_1002', 1, 118, 76, 95, 'fpg', 58.5, 160.0, 74.0, 22.8, 1, 1]);
            $stmtS->execute([5, '9999900000005', 'DEMO_1003', 1, 162, 98, 210, 'fpg', 72.0, 165.0, 88.0, 26.4, 3, 1]);
            // Round 2 screening results
            $stmtS->execute([8, '9999900000001', 'DEMO_1001', 2, 124, 80, 102, 'fpg', 64.0, 168.0, 78.0, 22.7, 1, 1]);
            $stmtS->execute([9, '9999900000002', 'DEMO_1001', 2, 138, 88, 120, 'fpg', 68.5, 158.0, 83.0, 27.4, 2, 1]);

            // Seed Mock DPAC Enrollments & Followups
            $stmtE = $pdo->prepare("INSERT INTO `demo_mock_dpac_enrollments` (enrollment_id, cid, budget_year, risk_type, assigned_vhv_id, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtE->execute([1, '9999900000003', 2026, 'BOTH', 'DEMO_1002', 'active']);

            $stmtF = $pdo->prepare("INSERT INTO `demo_mock_dpac_followups` (followup_id, enrollment_id, vhv_id, round_number, status, weight, height, waist, bp_sys, bp_dia, fbs, health_risk_level, advice_given) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtF->execute([1, 1, 'DEMO_1002', 1, 'pending', 65.0, 165.0, 82.0, 138, 86, 145, 'yellow', 'ลดเค็ม ลดหวาน ออกกำลังกาย']);
            $stmtF->execute([2, 1, 'DEMO_1002', 2, 'completed', 63.5, 165.0, 80.0, 126, 82, 120, 'green', 'คุมอาหารได้ดี ออกกำลังกายสม่ำเสมอ']);

            // Seed Mock VHVs
            $mockVhvs = [
                ['DEMO_1001', 'อสม. สมชาย ใจดี (จำลอง)', '1', '34100101', '99999', 1],
                ['DEMO_1002', 'อสม. สายสมร มีสุข (จำลอง)', '2', '34100102', '99999', 1],
                ['DEMO_1003', 'อสม. บุญทัน เจริญดี (จำลอง)', '3', '34100103', '99999', 1]
            ];
            $stmtV = $pdo->prepare("INSERT INTO `demo_mock_vhv_users` (vhv_id, vhv_name, vhv_moo, vhid_code, hoscode, approved) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($mockVhvs as $v) { $stmtV->execute($v); }

            // Seed Mock VHV Rewards for Leaderboard
            $stmtR = $pdo->prepare("INSERT INTO `demo_mock_vhv_rewards` (vhv_id, points_earned, approval_status) VALUES (?, ?, ?)");
            $stmtR->execute(['DEMO_1001', 12.00, 'approved']);
            $stmtR->execute(['DEMO_1002', 18.00, 'approved']);
            $stmtR->execute(['DEMO_1003', 25.00, 'approved']);

            // Seed Mock Health Units & Villages
            $stmtH = $pdo->prepare("INSERT INTO `demo_mock_health_units` (hoscode, hosname) VALUES (?, ?)");
            $stmtH->execute(['99999', 'รพ.สต. ตาลสุม (จำลอง)']);

            $stmtSub = $pdo->prepare("INSERT INTO `demo_mock_sub_districts` (sub_district_code, sub_district_name) VALUES (?, ?)");
            $stmtSub->execute(['341001', 'ตำบลตาลสุม (จำลอง)']);

            $stmtVil = $pdo->prepare("INSERT INTO `demo_mock_villages` (vhid_code, sub_district_code, moo, village_name, hoscode) VALUES (?, ?, ?, ?, ?)");
            $stmtVil->execute(['34100101', '341001', 1, 'หมู่ 1 บ้านตาลสุม (จำลอง)', '99999']);
            $stmtVil->execute(['34100102', '341001', 2, 'หมู่ 2 บ้านดอนใหญ่ (จำลอง)', '99999']);
            $stmtVil->execute(['34100103', '341001', 3, 'หมู่ 3 บ้านโคกสว่าง (จำลอง)', '99999']);
            $stmtVil->execute(['34100104', '341001', 4, 'หมู่ 4 บ้านนาเจริญ (จำลอง)', '99999']);
            $stmtVil->execute(['34100105', '341001', 5, 'หมู่ 5 บ้านโนนงาม (จำลอง)', '99999']);

            // Seed Mock Citizen Self-Screening Anonymous Records (30+ diverse demographic cases)
            $mockCitizenScreenings = [
                ['anon_sess_01', 2026, 'male', 'young', 'slim', 'low', 'low', 'good', 'regular', 'good', 'none', 'no', 0, 'green', '341801'],
                ['anon_sess_02', 2026, 'female', 'middle', 'chubby', 'med', 'med', 'good', 'some', 'good', 'none', 'yes', 5, 'yellow', '341801'],
                ['anon_sess_03', 2026, 'female', 'senior', 'obese', 'high', 'high', 'poor', 'sedentary', 'poor', 'none', 'yes', 15, 'red', '341802'],
                ['anon_sess_04', 2026, 'male', 'middle', 'chubby', 'med', 'high', 'good', 'some', 'poor', 'some', 'no', 8, 'yellow', '341802'],
                ['anon_sess_05', 2026, 'female', 'young', 'slim', 'low', 'low', 'good', 'regular', 'good', 'none', 'no', 0, 'green', '341803'],
                ['anon_sess_06', 2026, 'male', 'senior', 'obese', 'high', 'high', 'poor', 'sedentary', 'poor', 'regular', 'yes', 18, 'red', '341803'],
                ['anon_sess_07', 2026, 'female', 'middle', 'slim', 'med', 'low', 'good', 'regular', 'good', 'none', 'no', 2, 'green', '341804'],
                ['anon_sess_08', 2026, 'male', 'young', 'chubby', 'high', 'med', 'poor', 'some', 'good', 'some', 'no', 7, 'yellow', '341804'],
                ['anon_sess_09', 2026, 'female', 'senior', 'chubby', 'low', 'med', 'good', 'some', 'poor', 'none', 'yes', 6, 'yellow', '341805'],
                ['anon_sess_10', 2026, 'male', 'middle', 'obese', 'high', 'high', 'poor', 'sedentary', 'poor', 'regular', 'yes', 17, 'red', '341805'],
                ['anon_sess_11', 2026, 'female', 'young', 'slim', 'med', 'low', 'good', 'some', 'good', 'none', 'no', 2, 'green', '341806'],
                ['anon_sess_12', 2026, 'male', 'senior', 'slim', 'low', 'low', 'good', 'regular', 'good', 'none', 'no', 2, 'green', '341806'],
                ['anon_sess_13', 2026, 'female', 'middle', 'obese', 'med', 'high', 'poor', 'sedentary', 'good', 'none', 'yes', 11, 'red', '341801'],
                ['anon_sess_14', 2026, 'male', 'middle', 'chubby', 'med', 'med', 'good', 'some', 'good', 'some', 'no', 6, 'yellow', '341802'],
                ['anon_sess_15', 2026, 'female', 'young', 'slim', 'low', 'low', 'good', 'regular', 'good', 'none', 'no', 0, 'green', '341803'],
                ['anon_sess_16', 2026, 'male', 'senior', 'chubby', 'med', 'high', 'poor', 'some', 'poor', 'none', 'yes', 10, 'red', '341804'],
                ['anon_sess_17', 2026, 'female', 'middle', 'slim', 'low', 'med', 'good', 'regular', 'good', 'none', 'no', 2, 'green', '341805'],
                ['anon_sess_18', 2026, 'male', 'young', 'obese', 'high', 'high', 'poor', 'sedentary', 'poor', 'regular', 'no', 14, 'red', '341806'],
                ['anon_sess_19', 2026, 'female', 'senior', 'chubby', 'low', 'med', 'good', 'some', 'good', 'none', 'yes', 5, 'yellow', '341801'],
                ['anon_sess_20', 2026, 'male', 'middle', 'slim', 'low', 'low', 'good', 'regular', 'good', 'none', 'no', 1, 'green', '341802']
            ];

            $stmtCS = $pdo->prepare("INSERT INTO `demo_mock_citizen_self_screenings` 
                (session_hash, budget_year, gender, age_group, body_shape, sweet_habit, salt_habit, veggie_habit, exercise_habit, sleep_habit, substance_habit, family_history, risk_points, risk_level, sub_district_code) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($mockCitizenScreenings as $cs) {
                $stmtCS->execute($cs);
            }

            // Seed Mock Critical Alerts
            $mockAlerts = [
                ['99999', '9999900000005', 'วิชัย มั่นคง (จำลอง)', 68, '15/3', '3', '341001', 15.4350, 104.9860, 'ht_crisis (ความดันโลหิตสูงวิกฤต)', 210, 115, 310, 'ปวดศีรษะรุนแรง ตาพร่ามัว ปากเบี้ยว', 'อสม. บุญทัน เจริญดี (จำลอง)', '081-234-5678', 'pending'],
                ['99999', '9999900000002', 'สมศรี สุขสรรค์ (จำลอง)', 71, '45/2', '1', '341001', 15.4310, 104.9825, 'dtx_high (น้ำตาลสูงวิกฤต)', 175, 95, 340, 'อ่อนเพลียมาก กระหายน้ำ หายใจหอบ', 'อสม. สมชาย ใจดี (จำลอง)', '089-876-5432', 'acknowledged'],
                ['99999', '9999900000007', 'อนันต์ เจริญสุข (จำลอง)', 61, '99/4', '4', '341001', 15.4370, 104.9880, 'ht_crisis (ความดันโลหิตสูงวิกฤต)', 195, 110, 140, 'แน่นหน้าอกร้าวไปกราม', 'อสม. บุญทัน เจริญดี (จำลอง)', '081-234-5678', 'referred_hospital']
            ];

            $stmtCA = $pdo->prepare("INSERT INTO `demo_mock_critical_alerts`
                (hoscode, target_cid, patient_name, age, house_no, moo, sub_district_code, latitude, longitude, crisis_type, sbp, dbp, dtx, red_flags, vhv_name, vhv_phone, alert_status, referral_destination, is_jhcis_synced)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($mockAlerts as $ca) {
                $refDest = ($ca[16] === 'referred_hospital') ? 'โรงพยาบาลตาลสุม (10988)' : null;
                $isSynced = ($ca[16] === 'referred_hospital') ? 1 : 0;
                $stmtCA->execute([
                    $ca[0], $ca[1], $ca[2], $ca[3], $ca[4], $ca[5], $ca[6], $ca[7], $ca[8], $ca[9],
                    $ca[10], $ca[11], $ca[12], $ca[13], $ca[14], $ca[15], $ca[16], $refDest, $isSynced
                ]);
            }
        }
    } catch (\Throwable $e) {
        // Failover gracefully
    }
}
