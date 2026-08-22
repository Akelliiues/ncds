<?php
// config/demo_database.php
// ระบบฐานข้อมูลจำลองสมบูรณ์แบบ 100% สำหรับโหมด Demo Sandbox (100% Mockup DB Isolation)

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
        $tables = [
            'target_population' => 'demo_mock_target_population',
            'task_assignments'  => 'demo_mock_task_assignments',
            'screening_results' => 'demo_mock_screening_results',
            'dpac_enrollments'  => 'demo_mock_dpac_enrollments',
            'dpac_followups'    => 'demo_mock_dpac_followups',
            'vhv_users'         => 'demo_mock_vhv_users'
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
        // 1. สร้างตาราง demo_mock_target_population
        $pdo->exec("CREATE TABLE IF NOT EXISTS `demo_mock_target_population` LIKE `target_population`;");
        
        // 2. สร้างตาราง demo_mock_task_assignments
        $pdo->exec("CREATE TABLE IF NOT EXISTS `demo_mock_task_assignments` LIKE `task_assignments`;");

        // 3. สร้างตาราง demo_mock_screening_results
        $pdo->exec("CREATE TABLE IF NOT EXISTS `demo_mock_screening_results` LIKE `screening_results`;");

        // 4. สร้างตาราง demo_mock_dpac_enrollments
        $pdo->exec("CREATE TABLE IF NOT EXISTS `demo_mock_dpac_enrollments` LIKE `dpac_enrollments`;");

        // 5. สร้างตาราง demo_mock_dpac_followups
        $pdo->exec("CREATE TABLE IF NOT EXISTS `demo_mock_dpac_followups` LIKE `dpac_followups`;");

        // 6. สร้างตาราง demo_mock_vhv_users
        $pdo->exec("CREATE TABLE IF NOT EXISTS `demo_mock_vhv_users` LIKE `vhv_users`;");

        // ตรวจสอบว่ามีข้อมูลในตารางจำลองแล้วหรือยัง หากยังไม่มีให้ลงข้อมูลสมมติ 100%
        $count = $pdo->query("SELECT COUNT(*) FROM `demo_mock_target_population`")->fetchColumn();
        if ($count == 0) {
            // Seed Mock Targets (CID สมมติ 99999..., ชื่อสมมติ ปลอดภัย 100%)
            $mockTargets = [
                ['9999900000001', '1001', '12/1', '1', 'สมชาย', 'ใจดี (จำลอง)', '1968-05-15', '1', '341001', '99999', 1, 1, 'BOTH', 15.4321, 104.9812],
                ['9999900000002', '1002', '45/2', '1', 'สมศรี', 'สุขสรรค์ (จำลอง)', '1955-09-20', '2', '341001', '99999', 1, 0, 'DM_ONLY', 15.4310, 104.9825],
                ['9999900000003', '1003', '88', '2', 'บุญมี', 'มีโชค (จำลอง)', '1962-11-04', '1', '341001', '99999', 1, 1, 'BOTH', 15.4335, 104.9840],
                ['9999900000004', '1004', '101', '2', 'ทองสุข', 'สดใส (จำลอง)', '1975-01-30', '2', '341001', '99999', 0, 1, 'HT_ONLY', 15.4340, 104.9850],
                ['9999900000005', '1005', '15/3', '3', 'วิชัย', 'มั่นคง (จำลอง)', '1958-08-12', '1', '341001', '99999', 1, 1, 'BOTH', 15.4350, 104.9860],
                ['9999900000006', '1006', '77/1', '3', 'วิภาดา', 'รุ่งเรือง (จำลอง)', '1980-03-25', '2', '341001', '99999', 1, 0, 'DM_ONLY', 15.4360, 104.9870],
                ['9999900000007', '1007', '99/4', '4', 'อนันต์', 'เจริญสุข (จำลอง)', '1965-07-18', '1', '341001', '99999', 0, 1, 'HT_ONLY', 15.4370, 104.9880],
                ['9999900000008', '1008', '23/2', '5', 'อุบล', 'มีสุข (จำลอง)', '1972-12-01', '2', '341001', '99999', 1, 1, 'BOTH', 15.4380, 104.9890]
            ];

            $stmtT = $pdo->prepare("INSERT INTO `demo_mock_target_population` 
                (cid, hid, house_no, moo, first_name, last_name, birth, sex, sub_district_code, hoscode, need_screen_dm, need_screen_ht, health_status_origin, latitude, longitude) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($mockTargets as $t) {
                $stmtT->execute($t);
            }

            // Seed Mock Task Assignments
            $mockAssignments = [
                ['9999900000001', 'DEMO_1001', 2026, 'pending', 1, 1],
                ['9999900000002', 'DEMO_1001', 2026, 'pending', 1, 1],
                ['9999900000003', 'DEMO_1002', 2026, 'pending', 1, 1],
                ['9999900000004', 'DEMO_1002', 2026, 'completed', 1, 1],
                ['9999900000005', 'DEMO_1003', 2026, 'completed', 1, 1]
            ];

            $stmtA = $pdo->prepare("INSERT INTO `demo_mock_task_assignments` 
                (target_cid, vhv_id, budget_year, assignment_status, round_number, is_sandbox) 
                VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($mockAssignments as $a) {
                $stmtA->execute($a);
            }

            // Seed Mock VHVs
            $mockVhvs = [
                ['DEMO_1001', 'อสม. สมชาย ใจดี (จำลอง)', '1', '34100101', '99999', 1],
                ['DEMO_1002', 'อสม. สายสมร มีสุข (จำลอง)', '2', '34100102', '99999', 1],
                ['DEMO_1003', 'อสม. บุญทัน เจริญดี (จำลอง)', '3', '34100103', '99999', 1]
            ];

            $stmtV = $pdo->prepare("INSERT INTO `demo_mock_vhv_users` 
                (vhv_id, vhv_name, vhv_moo, vhid_code, hoscode, approved) 
                VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($mockVhvs as $v) {
                $stmtV->execute($v);
            }
        }
    } catch (\Throwable $e) {
        // Failover gracefully
    }
}
