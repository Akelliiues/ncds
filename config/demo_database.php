<?php
// config/demo_database.php
// ระบบฐานข้อมูลจำลองครอบคลุมทุกเมนูและฟังก์ชันทั้งหน้าบ้านและหลังบ้าน 100% (Complete Mockup DB Generator)

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
            'staging_jhcis_person'     => 'demo_mock_staging_jhcis_person'
        ];
        foreach ($tables as $real => $mock) {
            $sql = preg_replace('/\b' . preg_quote($real, '/') . '\b/i', $mock, $sql);
        }
        return $sql;
    }

    public function prepare($query, $options = []) {
        $mockQuery = $this->replaceTables($query);
        $stmt = parent::prepare($mockQuery, $options);
        return new DemoMockPDOStatement($stmt);
    }

    public function query($query, $fetchMode = null, ...$fetch_mode_args) {
        $mockQuery = $this->replaceTables($query);
        return parent::query($mockQuery, $fetchMode, ...$fetch_mode_args);
    }

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
            'staging_jhcis_person'
        ];

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

            // Seed Mock Task Assignments
            $mockAssignments = [
                ['9999900000001', 'DEMO_1001', 2026, 'pending', 1, 1],
                ['9999900000002', 'DEMO_1001', 2026, 'pending', 1, 1],
                ['9999900000003', 'DEMO_1002', 2026, 'pending', 1, 1],
                ['9999900000004', 'DEMO_1002', 2026, 'completed', 1, 1],
                ['9999900000005', 'DEMO_1003', 2026, 'completed', 1, 1],
                ['9999900000009', 'DEMO_1001', 2026, 'pending', 1, 1],
                ['9999900000010', 'DEMO_1002', 2026, 'completed', 1, 1]
            ];
            $stmtA = $pdo->prepare("INSERT INTO `demo_mock_task_assignments` (target_cid, vhv_id, budget_year, assignment_status, round_number, is_sandbox) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($mockAssignments as $a) { $stmtA->execute($a); }

            // Seed Mock Screening Results
            $stmtS = $pdo->prepare("INSERT INTO `demo_mock_screening_results` (assignment_id, target_cid, vhv_id, sys_bp1, dia_bp1, dtx_value, dtx_type, weight, height, waist, bmi, cv_risk_score, is_sandbox) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtS->execute([4, '9999900000004', 'DEMO_1002', 118, 76, 95, 'fpg', 58.5, 160.0, 74.0, 22.8, 1, 1]);
            $stmtS->execute([5, '9999900000005', 'DEMO_1003', 162, 98, 210, 'fpg', 72.0, 165.0, 88.0, 26.4, 3, 1]);

            // Seed Mock DPAC Enrollments & Followups
            $stmtE = $pdo->prepare("INSERT INTO `demo_mock_dpac_enrollments` (enrollment_id, cid, budget_year, risk_type, assigned_vhv_id, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtE->execute([1, '9999900000003', 2026, 'BOTH', 'DEMO_1002', 'active']);

            $stmtF = $pdo->prepare("INSERT INTO `demo_mock_dpac_followups` (followup_id, enrollment_id, vhv_id, round_number, status, weight, height, waist, bp_sys, bp_dia, fbs, health_risk_level, advice_given) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtF->execute([1, 1, 'DEMO_1002', 1, 'pending', 65.0, 165.0, 82.0, 138, 86, 145, 'yellow', 'ลดเค็ม ลดหวาน ออกกำลังกาย']);

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
        }
    } catch (\Throwable $e) {
        // Failover gracefully
    }
}
