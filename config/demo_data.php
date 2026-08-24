<?php
// config/demo_data.php - Provider ข้อมูลจำลองสมจริง 100% สำหรับโหมด Demo Sandbox (ปลอดข้อมูลจริง 100%)

class DemoDataProvider {

    public static function isDemoMode() {
        return isset($_SESSION['is_demo_mode']) && $_SESSION['is_demo_mode'] === true;
    }

    public static function getDemoRole() {
        return $_SESSION['demo_role'] ?? 'vhv';
    }

    // 1. รายชื่อประชากรจำลอง 10 คน (CID สมมติ ปลอดภัยจากข้อมูลจริง 100% ครอบคลุมหมู่ 1 ถึง 5)
    public static function getMockTargets() {
        return [
            // --- หมู่ 1: ได้รับมอบหมาย (กดคัดกรองได้จากหน้าหลัก อสม. หรือสแกน QR ก็ผ่าน) ---
            [
                'cid' => '9999900000001',
                'first_name' => 'สมชาย',
                'last_name' => 'ใจดี (จำลอง)',
                'sex' => '1',
                'birth' => '1968-05-15',
                'age' => 58,
                'house_no' => '12/1',
                'moo' => '1',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'BOTH',
                'assignment_status' => 'pending',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. สมชาย ใจดี (จำลอง)',
                'last_sbp' => 136,
                'last_dbp' => 86,
                'last_dtx' => 116,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo1_assigned',
                'health_case' => 'risk' // กลุ่มเสี่ยง (Pre-HT/DM)
            ],
            [
                'cid' => '9999900000002',
                'first_name' => 'สมศรี',
                'last_name' => 'สุขสรรค์ (จำลอง)',
                'sex' => '2',
                'birth' => '1955-09-20',
                'age' => 71,
                'house_no' => '45/2',
                'moo' => '1',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'BOTH',
                'assignment_status' => 'pending',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. สมชาย ใจดี (จำลอง)',
                'last_sbp' => 158,
                'last_dbp' => 96,
                'last_dtx' => 175,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo1_assigned',
                'health_case' => 'abnormal_high_risk' // กลุ่มผิดปกติ/สงสัยป่วยสูง
            ],

            // --- หมู่ 2: Bypass สแกน QR Code หมู่ 2 ได้ทั้งหมด และเข้าคัดกรองได้ปกติ ---
            [
                'cid' => '9999900000003',
                'first_name' => 'บุญมี',
                'last_name' => 'มีโชค (จำลอง)',
                'sex' => '1',
                'birth' => '1962-11-04',
                'age' => 64,
                'house_no' => '88',
                'moo' => '2',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'BOTH',
                'assignment_status' => 'pending',
                'round_number' => 2,
                'assigned_vhv' => 'อสม. สายสมร มีสุข (จำลอง)',
                'last_sbp' => 162,
                'last_dbp' => 98,
                'last_dtx' => 195,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo2_bypass',
                'health_case' => 'round2_comparison' // เคสรอบที่ 2 สำหรับเปรียบเทียบผลกับรอบ 1
            ],
            [
                'cid' => '9999900000004',
                'first_name' => 'ทองสุข',
                'last_name' => 'สดใส (จำลอง)',
                'sex' => '2',
                'birth' => '1975-01-30',
                'age' => 51,
                'house_no' => '101',
                'moo' => '2',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 0,
                'need_screen_ht' => 1,
                'health_status_origin' => 'HT_ONLY',
                'assignment_status' => 'pending',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. สายสมร มีสุข (จำลอง)',
                'last_sbp' => 118,
                'last_dbp' => 76,
                'last_dtx' => 95,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo2_bypass',
                'health_case' => 'normal' // เคสสุขภาพปกติ
            ],

            // --- หมู่ 3: ล็อกการคัดกรองเพราะ "ยังไม่ได้รับมอบหมายงาน" ---
            [
                'cid' => '9999900000005',
                'first_name' => 'วิชัย',
                'last_name' => 'มั่นคง (จำลอง)',
                'sex' => '1',
                'birth' => '1958-08-12',
                'age' => 68,
                'house_no' => '15/3',
                'moo' => '3',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'BOTH',
                'assignment_status' => 'unassigned',
                'round_number' => 1,
                'assigned_vhv' => '-',
                'last_sbp' => 162,
                'last_dbp' => 98,
                'last_dtx' => 210,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo3_unassigned'
            ],
            [
                'cid' => '9999900000006',
                'first_name' => 'อำนวย',
                'last_name' => 'รวยรื่น (จำลอง)',
                'sex' => '2',
                'birth' => '1965-03-25',
                'age' => 61,
                'house_no' => '22',
                'moo' => '3',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 0,
                'health_status_origin' => 'DM_ONLY',
                'assignment_status' => 'unassigned',
                'round_number' => 1,
                'assigned_vhv' => '-',
                'last_sbp' => 128,
                'last_dbp' => 82,
                'last_dtx' => 135,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo3_unassigned'
            ],

            // --- หมู่ 4: ล็อกการคัดกรองเพราะ "ยังไม่ได้รับมอบหมายงาน" ---
            [
                'hid' => '1007',
                'cid' => '9999900001007',
                'first_name' => 'อนันต์',
                'last_name' => 'เจริญสุข (จำลอง)',
                'sex' => '1',
                'birth' => '1970-10-18',
                'age' => 56,
                'house_no' => '99/4',
                'moo' => '4',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'BOTH',
                'assignment_status' => 'unassigned',
                'round_number' => 1,
                'assigned_vhv' => '-',
                'last_sbp' => 142,
                'last_dbp' => 88,
                'last_dtx' => 150,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo4_unassigned',
                'health_case' => 'unassigned_lock'
            ],

            // --- หมู่ 5: ล็อกการคัดกรองเพราะ "สแกนข้ามเขต" ---
            [
                'hid' => '1008',
                'cid' => '9999900001008',
                'first_name' => 'อุบล',
                'last_name' => 'มีสุข (จำลอง)',
                'sex' => '2',
                'birth' => '1952-12-05',
                'age' => 74,
                'house_no' => '23/2',
                'moo' => '5',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 0,
                'need_screen_ht' => 1,
                'health_status_origin' => 'HT_ONLY',
                'assignment_status' => 'out_of_territory',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. วนิดา (เขต ม.5)',
                'last_sbp' => 155,
                'last_dbp' => 95,
                'last_dtx' => 110,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo5_outofarea',
                'health_case' => 'outofarea_lock'
            ],

            // --- หมู่ 5: ล็อกการคัดกรองเพราะ "สแกนข้ามเขต" ---
            [
                'cid' => '9999900000009',
                'first_name' => 'ประเสริฐ',
                'last_name' => 'เลิศล้ำ (จำลอง)',
                'sex' => '1',
                'birth' => '1960-07-14',
                'age' => 66,
                'house_no' => '9/1',
                'moo' => '5',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'BOTH',
                'assignment_status' => 'out_of_territory',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. วนิดา (เขต ม.5)',
                'last_sbp' => 136,
                'last_dbp' => 84,
                'last_dtx' => 124,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo5_outofarea'
            ],
            [
                'cid' => '9999900000010',
                'first_name' => 'พวงเพ็ญ',
                'last_name' => 'เจริญผล (จำลอง)',
                'sex' => '2',
                'birth' => '1967-02-28',
                'age' => 59,
                'house_no' => '33',
                'moo' => '5',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 0,
                'health_status_origin' => 'DM_ONLY',
                'assignment_status' => 'out_of_territory',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. วนิดา (เขต ม.5)',
                'last_sbp' => 122,
                'last_dbp' => 80,
                'last_dtx' => 178,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo5_outofarea'
            ],
            // --- รายการเพิ่มเติมสำหรับจำลองการจัดการกลุ่มเป้าหมาย (หมู่ 1 - 5) ---
            [
                'cid' => '9999900000011',
                'first_name' => 'สำราญ',
                'last_name' => 'จิตผ่อง (จำลอง)',
                'sex' => '1',
                'birth' => '1955-06-18',
                'age' => 71,
                'house_no' => '12/1',
                'moo' => '1',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'BOTH',
                'assignment_status' => 'pending',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. สมชาย ใจดี (จำลอง)',
                'last_sbp' => 148,
                'last_dbp' => 92,
                'last_dtx' => 165,
                'last_dtx_type' => 'fpg'
            ],
            [
                'cid' => '9999900000012',
                'first_name' => 'ประนอม',
                'last_name' => 'ศรีสวัสดิ์ (จำลอง)',
                'sex' => '2',
                'birth' => '1972-09-09',
                'age' => 54,
                'house_no' => '54',
                'moo' => '1',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 0,
                'need_screen_ht' => 1,
                'health_status_origin' => 'HT_ONLY',
                'assignment_status' => 'pending',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. สมชาย ใจดี (จำลอง)',
                'last_sbp' => 134,
                'last_dbp' => 86,
                'last_dtx' => 102,
                'last_dtx_type' => 'fpg'
            ],
            [
                'cid' => '9999900000013',
                'first_name' => 'ชูเกียรติ',
                'last_name' => 'มั่นคง (จำลอง)',
                'sex' => '1',
                'birth' => '1963-04-14',
                'age' => 63,
                'house_no' => '72/2',
                'moo' => '2',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 0,
                'health_status_origin' => 'DM_ONLY',
                'assignment_status' => 'pending',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. สายสมร มีสุข (จำลอง)',
                'last_sbp' => 125,
                'last_dbp' => 78,
                'last_dtx' => 188,
                'last_dtx_type' => 'fpg'
            ],
            [
                'cid' => '9999900000014',
                'first_name' => 'จันทร์แรม',
                'last_name' => 'แก้วมณี (จำลอง)',
                'sex' => '2',
                'birth' => '1980-11-22',
                'age' => 46,
                'house_no' => '19',
                'moo' => '2',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'HIGH_RISK',
                'assignment_status' => 'pending',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. สายสมร มีสุข (จำลอง)',
                'last_sbp' => 152,
                'last_dbp' => 94,
                'last_dtx' => 160,
                'last_dtx_type' => 'fpg'
            ],
            [
                'cid' => '9999900000015',
                'first_name' => 'ประสิทธิ์',
                'last_name' => 'วารินทร์ (จำลอง)',
                'sex' => '1',
                'birth' => '1959-12-01',
                'age' => 67,
                'house_no' => '65',
                'moo' => '3',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 0,
                'need_screen_ht' => 1,
                'health_status_origin' => 'HT_ONLY',
                'assignment_status' => 'unassigned',
                'round_number' => 1,
                'assigned_vhv' => '-',
                'last_sbp' => 146,
                'last_dbp' => 90,
                'last_dtx' => 108,
                'last_dtx_type' => 'fpg'
            ],
            [
                'cid' => '9999900000016',
                'first_name' => 'บัวลอย',
                'last_name' => 'สุขสมบูรณ์ (จำลอง)',
                'sex' => '2',
                'birth' => '1968-08-30',
                'age' => 58,
                'house_no' => '84/1',
                'moo' => '3',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'BOTH',
                'assignment_status' => 'unassigned',
                'round_number' => 1,
                'assigned_vhv' => '-',
                'last_sbp' => 160,
                'last_dbp' => 98,
                'last_dtx' => 205,
                'last_dtx_type' => 'fpg'
            ],
            [
                'cid' => '9999900000017',
                'first_name' => 'ไพฑูรย์',
                'last_name' => 'ศรีสุมิตร (จำลอง)',
                'sex' => '1',
                'birth' => '1974-03-12',
                'age' => 52,
                'house_no' => '42',
                'moo' => '4',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 0,
                'health_status_origin' => 'DM_ONLY',
                'assignment_status' => 'unassigned',
                'round_number' => 1,
                'assigned_vhv' => '-',
                'last_sbp' => 120,
                'last_dbp' => 78,
                'last_dtx' => 155,
                'last_dtx_type' => 'fpg'
            ],
            [
                'cid' => '9999900000018',
                'first_name' => 'ดวงใจ',
                'last_name' => 'สายธาร (จำลอง)',
                'sex' => '2',
                'birth' => '1961-05-19',
                'age' => 65,
                'house_no' => '108',
                'moo' => '4',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'BOTH',
                'assignment_status' => 'unassigned',
                'round_number' => 1,
                'assigned_vhv' => '-',
                'last_sbp' => 168,
                'last_dbp' => 102,
                'last_dtx' => 220,
                'last_dtx_type' => 'fpg'
            ],
            [
                'cid' => '9999900000019',
                'first_name' => 'สง่า',
                'last_name' => 'วงศ์เจริญ (จำลอง)',
                'sex' => '1',
                'birth' => '1957-10-05',
                'age' => 69,
                'house_no' => '77',
                'moo' => '5',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 0,
                'need_screen_ht' => 1,
                'health_status_origin' => 'HT_ONLY',
                'assignment_status' => 'out_of_territory',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. วนิดา (เขต ม.5)',
                'last_sbp' => 140,
                'last_dbp' => 88,
                'last_dtx' => 115,
                'last_dtx_type' => 'fpg'
            ],
            [
                'cid' => '9999900000020',
                'first_name' => 'ปราณี',
                'last_name' => 'ทองหล่อ (จำลอง)',
                'sex' => '2',
                'birth' => '1978-01-15',
                'age' => 48,
                'house_no' => '15/1',
                'moo' => '5',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'HIGH_RISK',
                'assignment_status' => 'out_of_territory',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. วนิดา (เขต ม.5)',
                'last_sbp' => 150,
                'last_dbp' => 92,
                'last_dtx' => 170,
                'last_dtx_type' => 'fpg'
            ]
        ];
    }

    // 2. งาน อสม. จำลอง (เฉพาะหมู่ 1 ที่ได้รับมอบหมายในหน้าหลัก)
    public static function getDemoVhvTasks() {
        $targets = self::getMockTargets();
        return [
            'pending' => [
                array_merge($targets[0], ['assignment_id' => 'DEMO_ASSIGN_1']),
                array_merge($targets[1], ['assignment_id' => 'DEMO_ASSIGN_2'])
            ],
            'dpac' => [
                array_merge($targets[2], ['assignment_id' => 'DEMO_ASSIGN_3', 'dpac_id' => 'DEMO_DPAC_1', 'round_no' => 2])
            ],
            'completed' => [
                array_merge($targets[3], ['assignment_id' => 'DEMO_ASSIGN_4', 'assignment_status' => 'completed'])
            ],
            'skipped' => []
        ];
    }

    // 3. ข้อมูลสถิติเชิงบริหารจำลอง 100% (สำหรับหน้า Admin / เจ้าหน้าที่)
    public static function getMockExecutiveMetrics() {
        return [
            'total_targets' => 250,
            'screened' => 185,
            'screened_percent' => 74.0,
            'normal' => 110,
            'risk' => 45,
            'high_risk' => 22,
            'critical' => 8,
            'village_stats' => [
                ['moo' => '1', 'village_name' => 'หมู่ 1 บ้านตาลสุม (จำลอง)', 'total' => 50, 'screened' => 42, 'percent' => 84.0],
                ['moo' => '2', 'village_name' => 'หมู่ 2 บ้านดอนใหญ่ (จำลอง)', 'total' => 60, 'screened' => 45, 'percent' => 75.0],
                ['moo' => '3', 'village_name' => 'หมู่ 3 บ้านโคกสว่าง (จำลอง)', 'total' => 45, 'screened' => 32, 'percent' => 71.1],
                ['moo' => '4', 'village_name' => 'หมู่ 4 บ้านนาเจริญ (จำลอง)', 'total' => 55, 'screened' => 38, 'percent' => 69.1],
                ['moo' => '5', 'village_name' => 'หมู่ 5 บ้านโนนงาม (จำลอง)', 'total' => 40, 'screened' => 28, 'percent' => 70.0]
            ],
            'recent_screenings' => [
                [
                    'screening_id' => 'DEMO_SCR_101',
                    'cid' => '9999900000005',
                    'first_name' => 'วิชัย',
                    'last_name' => 'มั่นคง (จำลอง)',
                    'house_no' => '15/3',
                    'moo' => '3',
                    'sbp' => 162,
                    'dbp' => 98,
                    'dtx' => 210,
                    'bmi' => 26.4,
                    'risk_level' => 'high_risk',
                    'risk_title' => '🟠 กลุ่มเสี่ยงสูง',
                    'vhv_name' => 'อสม. บุญทัน เจริญดี (จำลอง)',
                    'screened_at' => date('d/m/Y H:i', strtotime('-1 hours'))
                ],
                [
                    'screening_id' => 'DEMO_SCR_102',
                    'cid' => '9999900000004',
                    'first_name' => 'ทองสุข',
                    'last_name' => 'สดใส (จำลอง)',
                    'house_no' => '101',
                    'moo' => '2',
                    'sbp' => 118,
                    'dbp' => 76,
                    'dtx' => 95,
                    'bmi' => 21.2,
                    'risk_level' => 'normal',
                    'risk_title' => '🟢 ปกติ',
                    'vhv_name' => 'อสม. สายสมร มีสุข (จำลอง)',
                    'screened_at' => date('d/m/Y H:i', strtotime('-3 hours'))
                ]
            ],
            'dpac_followups' => [
                [
                    'followup_id' => 'DEMO_DPAC_101',
                    'cid' => '9999900000003',
                    'first_name' => 'บุญมี',
                    'last_name' => 'มีโชค (จำลอง)',
                    'house_no' => '88',
                    'moo' => '2',
                    'round_number' => 2,
                    'sbp' => 138,
                    'dbp' => 86,
                    'dtx' => 145,
                    'status' => 'pending',
                    'vhv_name' => 'อสม. สายสมร มีสุข (จำลอง)'
                ]
            ]
        ];
    }

    // 4. การประมวลผลการคัดกรองในโหมด Demo
    public static function processDemoScreening($postData) {
        $sbp1 = intval($postData['sys_bp1'] ?? 0);
        $dbp1 = intval($postData['dia_bp1'] ?? 0);
        $sbp2 = intval($postData['sys_bp2'] ?? 0);
        $dbp2 = intval($postData['dia_bp2'] ?? 0);
        $dtx  = intval($postData['dtx_value'] ?? 0);
        $dtxType = $postData['dtx_type'] ?? 'fpg';
        $weight = floatval($postData['weight'] ?? 0);
        $height = floatval($postData['height'] ?? 0);
        $waist  = floatval($postData['waist'] ?? 0);
        $roundNumber = intval($postData['round_number'] ?? 1);

        $sbp = max($sbp1, $sbp2);
        $dbp = max($dbp1, $dbp2);
        if ($sbp == 0) $sbp = 120;
        if ($dbp == 0) $dbp = 80;

        $bmi = ($weight > 0 && $height > 0) ? round($weight / pow($height / 100, 2), 2) : 0;

        $isCriticalBp = ($sbp >= 180 || $dbp >= 110);
        $isCriticalDtx = ($dtx >= 300);
        $isHighBp = ($sbp >= 140 || $dbp >= 90);
        $isRiskBp = ($sbp >= 120 || $dbp >= 80);
        $isHighDtx = ($dtxType === 'fpg' ? $dtx >= 126 : $dtx >= 200);
        $isRiskDtx = ($dtxType === 'fpg' ? ($dtx >= 100 && $dtx < 126) : ($dtx >= 140 && $dtx < 200));

        $riskLevel = 'green';
        $riskColor = '#10B981';
        $riskTitle = '🟢 สุขภาพปกติ (เกณฑ์ดีเยี่ยม)';
        $statusDesc = 'ค่าความดันโลหิตและระดับน้ำตาลอยู่ในเกณฑ์มาตรฐาน สุขภาพแข็งแรงดี';

        if ($isCriticalBp || $isCriticalDtx) {
            $riskLevel = 'red';
            $riskColor = '#DC2626';
            $riskTitle = '🔴 ระดับวิกฤต (สูงรุนแรง - ส่งต่อด่วน)';
            $statusDesc = 'พบค่าสัญญาณชีพสูงวิกฤต เสี่ยงต่อภาวะแทรกซ้อนรุนแรง ต้องส่งต่อ รพ.สต. หรือแพทย์ด่วน!';
        } elseif ($isHighBp || $isHighDtx) {
            $riskLevel = 'orange';
            $riskColor = '#EA580C';
            $riskTitle = '🟠 กลุ่มเสี่ยงสูง (สงสัยป่วย - ควรพบแพทย์)';
            $statusDesc = 'ความดันหรือน้ำตาลสูงเกินเกณฑ์ แนะนำตรวจยืนยันสภาวะโรคที่ รพ.สต.';
        } elseif ($isRiskBp || $isRiskDtx || $bmi >= 23) {
            $riskLevel = 'yellow';
            $riskColor = '#F59E0B';
            $riskTitle = '🟡 กลุ่มเสี่ยง (เริ่มสูง - ปรับ 3อ. 2ส.)';
            $statusDesc = 'เริ่มมีความเสี่ยง ควรปรับอาหาร ลดเค็ม ลดหวาน และออกกำลังกาย';
        }

        // Comparison with previous baseline/round
        $lastSbp = intval($postData['last_sbp'] ?? 135);
        $lastDbp = intval($postData['last_dbp'] ?? 85);
        $lastDtx = intval($postData['last_dtx'] ?? 118);
        $hasHistory = ($lastSbp > 0 || $lastDtx > 0 || $roundNumber >= 2);

        $trendStatus = 'stable';
        $trendTitle = '⚖️ สุขภาพทรงตัว';
        $trendColor = '#38BDF8';
        $trendDetails = [];

        if ($hasHistory) {
            $improvedPoints = 0;
            $worsenedPoints = 0;

            if ($lastSbp > 0) {
                if ($sbp < $lastSbp - 3) {
                    $improvedPoints++;
                    $diff = $lastSbp - $sbp;
                    $trendDetails[] = "ความดันตัวบนลดลง $diff mmHg (เดิม $lastSbp → ใหม่ $sbp)";
                } elseif ($sbp > $lastSbp + 5) {
                    $worsenedPoints++;
                    $diff = $sbp - $lastSbp;
                    $trendDetails[] = "ความดันตัวบนเพิ่มขึ้น $diff mmHg (เดิม $lastSbp → ใหม่ $sbp)";
                } else {
                    $trendDetails[] = "ความดันใกล้เคียงเดิม ($sbp mmHg)";
                }
            }

            if ($lastDtx > 0 && $dtx > 0) {
                if ($dtx < $lastDtx - 5) {
                    $improvedPoints++;
                    $diff = $lastDtx - $dtx;
                    $trendDetails[] = "น้ำตาลในเลือดลดลง $diff mg/dL (เดิม $lastDtx → ใหม่ $dtx)";
                } elseif ($dtx > $lastDtx + 10) {
                    $worsenedPoints++;
                    $diff = $dtx - $lastDtx;
                    $trendDetails[] = "น้ำตาลในเลือดเพิ่มขึ้น $diff mg/dL (เดิม $lastDtx → ใหม่ $dtx)";
                } else {
                    $trendDetails[] = "ระดับน้ำตาลใกล้เคียงเดิม ($dtx mg/dL)";
                }
            }

            if ($improvedPoints > $worsenedPoints) {
                $trendStatus = 'improved';
                $trendTitle = '📈 สุขภาพดีขึ้นกว่ารอบก่อน';
                $trendColor = '#10B981';
            } elseif ($worsenedPoints > $improvedPoints) {
                $trendStatus = 'worsened';
                $trendTitle = '⚠️ เฝ้าระวัง (ค่าตรวจสูงขึ้น)';
                $trendColor = '#F59E0B';
            } else {
                $trendStatus = 'stable';
                $trendTitle = '⚖️ สุขภาพทรงตัวจากรอบก่อน';
                $trendColor = '#38BDF8';
            }
        } else {
            $trendStatus = 'first_round';
            $trendTitle = '✨ คัดกรองรอบที่ 1 (จุดเซฟเริ่มต้น)';
            $trendColor = '#6366F1';
            $trendDetails[] = 'บันทึกเป็นฐานข้อมูลประเมินสุขภาพประจำปีเรียบร้อย';
        }

        // Action / Advice (Concise & Bold with 3D Clay images)
        $adviceList = [];
        if ($sbp >= 130 || $dbp >= 85) {
            $adviceList[] = [
                'icon' => '🧂',
                'img' => '../assets/img/advice/reduce_salt.jpg',
                'title' => 'ลดเค็ม เลี่ยงปลาร้า/แจ่วบอง',
                'desc' => 'งดซดน้ำแกง เลี่ยงของเค็มจัด ช่วยลดความดันโลหิต'
            ];
        }
        if ($dtx >= 100 || $bmi >= 23) {
            $adviceList[] = [
                'icon' => '🍬',
                'img' => '../assets/img/clay/sweet.jpg',
                'title' => 'ลดหวาน งดน้ำอัดลม/ชาหวาน',
                'desc' => 'ลดแป้งและของหวาน ช่วยคุมระดับน้ำตาล'
            ];
        }
        if ($bmi >= 23 || $riskLevel === 'risk') {
            $adviceList[] = [
                'icon' => '🚶‍♂️',
                'img' => '../assets/img/clay/exercise.jpg',
                'title' => 'ขยับกาย เดินวันละ 30 นาที',
                'desc' => 'เดินสะสมก้าวต่อเนื่อง ช่วยเผาผลาญไขมันและคุมน้ำหนัก'
            ];
        }
        if ($riskLevel === 'high_risk' || $riskLevel === 'critical') {
            $adviceList[] = [
                'icon' => '🩺',
                'img' => '../assets/img/advice/meet_doctor.jpg',
                'title' => 'ส่งต่อพบแพทย์ รพ.สต.',
                'desc' => 'นัดติดตามตรวจยืนยันสภาวะโรคเพื่อรับการรักษาที่เหมาะสม'
            ];
        }
        if (empty($adviceList)) {
            $adviceList[] = [
                'icon' => '🌟',
                'img' => '../assets/img/clay/shield.jpg',
                'title' => 'รักษาวินัย 3อ. 2ส. ยอดเยี่ยม',
                'desc' => 'ปฏิบัติตัวดีเยี่ยม รักษาสุขภาพแข็งแรงต่อเนื่อง'
            ];
        }

        // Reward points (2x for Round 2)
        $rewardPoints = ($roundNumber >= 2) ? 2 : 1;

        if (!isset($_SESSION['demo_screenings'])) $_SESSION['demo_screenings'] = [];
        $cid = $postData['cid'] ?? 'unknown';
        $_SESSION['demo_screenings'][$cid] = [
            'assignment_id' => $postData['assignment_id'] ?? null,
            'cid' => $cid,
            'sys_bp1' => $sbp1,
            'dia_bp1' => $dbp1,
            'dtx_value' => $dtx,
            'weight' => $weight,
            'height' => $height,
            'waist' => $waist,
            'sleep_quality' => $postData['sleep_quality'] ?? 'good',
            'care_level' => $postData['care_level'] ?? 'good',
            'next_visit_date' => $postData['next_visit_date'] ?? null,
            'guidance_summary' => $postData['guidance_summary'] ?? '',
            'health_progress' => $postData['health_progress'] ?? 'baseline',
            'saved_at' => date('Y-m-d H:i:s')
        ];

        return [
            'status' => 'success',
            'message' => 'บันทึกข้อมูลคัดกรองโหมดทดลองเรียบร้อยแล้ว (ข้อมูลจำลอง 100%)',
            'reward_points' => $rewardPoints,
            'is_demo' => true,
            'summary_metadata' => [
                'resident_name' => $postData['_residentName'] ?? 'สมชาย ใจดี (จำลอง)',
                'round_number' => $roundNumber,
                'sbp' => $sbp,
                'dbp' => $dbp,
                'dtx' => $dtx,
                'dtx_type' => $dtxType,
                'bmi' => $bmi,
                'waist' => $waist,
                'risk_level' => $riskLevel,
                'risk_color' => $riskColor,
                'risk_title' => $riskTitle,
                'status_desc' => $statusDesc,
                'has_history' => $hasHistory,
                'trend_status' => $trendStatus,
                'trend_title' => $trendTitle,
                'trend_color' => $trendColor,
                'trend_details' => $trendDetails,
                'advice_list' => $adviceList,
                'reward_points' => $rewardPoints,
                'next_appointment' => date('d/m/Y', strtotime('+3 months'))
            ]
        ];
    }

    // 5. Mockup Data กระดานคะแนนผู้นำ อสม. ครบทั้ง 50 อันดับ
    public static function getDemoLeaderboard() {
        $names = [
            ['สมรวย ช่วยชาติ', 'บ้านนาแก', 1, 1, 188.00, 150.00, 38.00, 48, 48],
            ['วรรณา สดใส', 'บ้านคำข่า', 2, 1, 176.00, 142.00, 34.00, 45, 45],
            ['ประเสริฐ ยิ่งยง', 'บ้านตาลสุม', 1, 0, 165.00, 135.00, 30.00, 44, 44],
            ['บุญเรือน สว่างจิต', 'บ้านนามน', 4, 1, 154.00, 126.00, 28.00, 42, 41],
            ['สายใจ รักชุมชน', 'บ้านดอนตะลี', 2, 0, 145.00, 120.00, 25.00, 40, 39],
            ['พรทิพย์ สุขเกษม', 'บ้านปากกุดหวาย', 3, 1, 138.00, 114.00, 24.00, 38, 38],
            ['วิชัย มุ่งมั่น', 'บ้านหนองเป็ด', 5, 0, 130.00, 108.00, 22.00, 36, 35],
            ['ใจดี มีสุข (โหมดจำลอง)', 'บ้านตาลสุม', 1, 0, 124.00, 104.00, 20.00, 35, 31],
            ['จำรัส ส่องแสง', 'บ้านม่วงโคน', 1, 1, 118.00, 100.00, 18.00, 34, 32],
            ['สุพรรณี มีลาภ', 'บ้านสำโรง', 4, 0, 112.00, 96.00, 16.00, 32, 30],
            ['ทองม้วน ชวนชื่น', 'บ้านห้วยดู่', 2, 0, 108.00, 92.00, 16.00, 30, 29],
            ['มาลี ดอกไม้หอม', 'บ้านนาแก', 3, 1, 104.00, 90.00, 14.00, 30, 28],
            ['สมศักดิ์ พิทักษ์ไทย', 'บ้านคำข่า', 1, 0, 100.00, 86.00, 14.00, 28, 27],
            ['จันทร์เพ็ญ เด่นดวง', 'บ้านตาลสุม', 3, 0, 96.00, 84.00, 12.00, 28, 26],
            ['ไพโรจน์ โชติช่วง', 'บ้านดอนตะลี', 5, 1, 92.00, 80.00, 12.00, 26, 25],
            ['รัตนาภรณ์ งามยิ่ง', 'บ้านนามน', 1, 0, 88.00, 78.00, 10.00, 26, 24],
            ['อุดม สมบูรณ์', 'บ้านปากกุดหวาย', 6, 0, 85.00, 75.00, 10.00, 25, 23],
            ['ชลธิชา ธาราทอง', 'บ้านหนองเป็ด', 2, 1, 82.00, 72.00, 10.00, 24, 22],
            ['ประนอม ถนอมจิต', 'บ้านม่วงโคน', 3, 0, 79.00, 70.00, 9.00, 24, 22],
            ['กมลวรรณ ขวัญยืน', 'บ้านสำโรง', 7, 0, 76.00, 68.00, 8.00, 23, 21],
            ['ชัยวัฒน์ เจริญสุข', 'บ้านห้วยดู่', 6, 1, 74.00, 66.00, 8.00, 22, 20],
            ['ดวงใจ บริสุทธิ์', 'บ้านนาแก', 2, 0, 71.00, 64.00, 7.00, 22, 20],
            ['นิตยา เกสรมาลี', 'บ้านคำข่า', 4, 0, 68.00, 62.00, 6.00, 21, 19],
            ['ประจักษ์ แจ่มแจ้ง', 'บ้านตาลสุม', 2, 1, 66.00, 60.00, 6.00, 20, 18],
            ['วิภาดา ฟ้าใส', 'บ้านดอนตะลี', 2, 0, 64.00, 58.00, 6.00, 20, 18],
            ['ศิริพร พรประเสริฐ', 'บ้านนามน', 4, 0, 62.00, 56.00, 6.00, 19, 17],
            ['อนันต์ มั่นคง', 'บ้านปากกุดหวาย', 3, 0, 60.00, 55.00, 5.00, 19, 17],
            ['ยุพา สงบจิต', 'บ้านหนองเป็ด', 5, 1, 58.00, 53.00, 5.00, 18, 16],
            ['รุ่งโรจน์ เรืองรอง', 'บ้านม่วงโคน', 1, 0, 56.00, 51.00, 5.00, 18, 16],
            ['ลัดดา วงศ์วาน', 'บ้านสำโรง', 4, 0, 54.00, 50.00, 4.00, 17, 15],
            ['เสาวนีย์ ศรีสุข', 'บ้านห้วยดู่', 2, 0, 52.00, 48.00, 4.00, 17, 15],
            ['อำนาจ อาจหาญ', 'บ้านนาแก', 1, 1, 50.00, 46.00, 4.00, 16, 14],
            ['กรรณิการ์ การกุศล', 'บ้านคำข่า', 2, 0, 48.00, 45.00, 3.00, 16, 14],
            ['ขวัญจิต มิตรแท้', 'บ้านตาลสุม', 3, 0, 46.00, 43.00, 3.00, 15, 13],
            ['จิราพร พรหมมาศ', 'บ้านดอนตะลี', 5, 0, 45.00, 42.00, 3.00, 15, 13],
            ['ชูชาติ ชนะศึก', 'บ้านนามน', 1, 1, 44.00, 41.00, 3.00, 14, 12],
            ['ฐิติมา ทรงคุณ', 'บ้านปากกุดหวาย', 6, 0, 43.00, 40.00, 3.00, 14, 12],
            ['ณรงค์ ฤทธิ์เดช', 'บ้านหนองเป็ด', 2, 0, 42.00, 39.00, 3.00, 14, 12],
            ['ดวงเดือน เด่นหล้า', 'บ้านม่วงโคน', 3, 0, 41.00, 38.00, 3.00, 13, 11],
            ['ทวีชัย ไตรภพ', 'บ้านสำโรง', 7, 1, 40.00, 37.00, 3.00, 13, 11],
            ['ธนภัทร พัฒนชัย', 'บ้านห้วยดู่', 6, 0, 39.00, 36.00, 3.00, 13, 11],
            ['นภาพร มณีวรรณ', 'บ้านนาแก', 3, 0, 38.00, 35.00, 3.00, 12, 10],
            ['บัญชา สั่งสอน', 'บ้านคำข่า', 1, 0, 37.00, 34.00, 3.00, 12, 10],
            ['ปิยะดา ธาราน้ำ', 'บ้านตาลสุม', 1, 1, 36.00, 33.00, 3.00, 12, 10],
            ['ผกามาศ ชาญณรงค์', 'บ้านดอนตะลี', 2, 0, 35.00, 32.00, 3.00, 11, 9],
            ['พงษ์ศักดิ์ ภักดี', 'บ้านนามน', 4, 0, 34.00, 31.00, 3.00, 11, 9],
            ['ภัทรวดี ศรีวิชัย', 'บ้านปากกุดหวาย', 3, 0, 33.00, 30.00, 3.00, 11, 9],
            ['มนัสวี วงศ์ดี', 'บ้านหนองเป็ด', 5, 0, 32.00, 29.00, 3.00, 10, 8],
            ['ยุวดี มณีรัตน์', 'บ้านม่วงโคน', 1, 1, 31.00, 28.00, 3.00, 10, 8],
            ['รัตนา พูนทรัพย์', 'บ้านสำโรง', 4, 0, 30.00, 28.00, 2.00, 10, 8]
        ];

        $list = [];
        foreach ($names as $idx => $n) {
            $vhvId = ($idx === 7) ? 'DEMO_1001' : ('DEMO_' . (1001 + $idx));
            $list[] = [
                'vhv_id' => $vhvId,
                'vhv_name' => 'อสม. ' . $n[0],
                'village_name' => $n[1],
                'vhv_moo' => $n[2],
                'vhid_code' => '3410010' . $n[2],
                'hoscode' => '99999',
                'is_hl_coach' => $n[3],
                'total_points' => $n[4],
                'screening_points' => $n[5],
                'dpac_points' => $n[6],
                'total_assigned' => $n[7],
                'completed' => $n[8],
                'waiting_rewards' => 0,
                'approved' => 1
            ];
        }
        return $list;
    }

    // 6. รายชื่อ อสม. จำลองทั้งหมด 5 หมู่บ้าน สำหรับงานบริหาร (Admin/Staff)
    public static function getMockVhvs() {
        $baseVhvs = [
            [
                'vhv_id' => 'DEMO_1001',
                'username' => 'vhv_moo1_1',
                'vhv_name' => 'อสม. สมชาย ใจดี (จำลอง)',
                'moo' => 1,
                'village_name' => 'บ้านตาลสุม (จำลอง)',
                'vhid_code' => '34100101',
                'hoscode' => '99999',
                'phone' => '081-234-5671',
                'is_leader' => 1,
                'is_hl_coach' => 1,
                'status' => 'approved',
                'assigned_count' => 50,
                'completed_count' => 42,
                'dpac_count' => 12
            ],
            [
                'vhv_id' => 'DEMO_1002',
                'username' => 'vhv_moo2_1',
                'vhv_name' => 'อสม. สายสมร มีสุข (จำลอง)',
                'moo' => 2,
                'village_name' => 'บ้านดอนใหญ่ (จำลอง)',
                'vhid_code' => '34100102',
                'hoscode' => '99999',
                'phone' => '082-345-6782',
                'is_leader' => 0,
                'is_hl_coach' => 1,
                'status' => 'approved',
                'assigned_count' => 60,
                'completed_count' => 45,
                'dpac_count' => 15
            ],
            [
                'vhv_id' => 'DEMO_1003',
                'username' => 'vhv_moo3_1',
                'vhv_name' => 'อสม. บุญทัน เจริญดี (จำลอง)',
                'moo' => 3,
                'village_name' => 'บ้านโคกสว่าง (จำลอง)',
                'vhid_code' => '34100103',
                'hoscode' => '99999',
                'phone' => '083-456-7893',
                'is_leader' => 1,
                'is_hl_coach' => 0,
                'status' => 'approved',
                'assigned_count' => 45,
                'completed_count' => 32,
                'dpac_count' => 8
            ],
            [
                'vhv_id' => 'DEMO_1004',
                'username' => 'vhv_moo4_1',
                'vhv_name' => 'อสม. ชาญชัย มั่นคง (จำลอง)',
                'moo' => 4,
                'village_name' => 'บ้านนาเจริญ (จำลอง)',
                'vhid_code' => '34100104',
                'hoscode' => '99999',
                'phone' => '084-567-8904',
                'is_leader' => 0,
                'is_hl_coach' => 0,
                'status' => 'approved',
                'assigned_count' => 55,
                'completed_count' => 38,
                'dpac_count' => 10
            ],
            [
                'vhv_id' => 'DEMO_1005',
                'username' => 'vhv_moo5_1',
                'vhv_name' => 'อสม. วนิดา สดใส (จำลอง)',
                'moo' => 5,
                'village_name' => 'บ้านโนนงาม (จำลอง)',
                'vhid_code' => '34100105',
                'hoscode' => '99999',
                'phone' => '085-678-9015',
                'is_leader' => 0,
                'is_hl_coach' => 1,
                'status' => 'approved',
                'assigned_count' => 40,
                'completed_count' => 28,
                'dpac_count' => 7
            ],
            [
                'vhv_id' => 'DEMO_1006',
                'username' => 'vhv_new_1',
                'vhv_name' => 'อสม. สมใจ รักเรียน (จำลอง)',
                'moo' => 1,
                'village_name' => 'บ้านตาลสุม (จำลอง)',
                'vhid_code' => '34100101',
                'hoscode' => '99999',
                'phone' => '086-789-0126',
                'is_leader' => 0,
                'is_hl_coach' => 0,
                'status' => 'pending',
                'assigned_count' => 0,
                'completed_count' => 0,
                'dpac_count' => 0
            ],
            [
                'vhv_id' => 'DEMO_1007',
                'username' => 'vhv_new_2',
                'vhv_name' => 'อสม. ประนอม ศรีสวัสดิ์ (จำลอง)',
                'moo' => 3,
                'village_name' => 'บ้านโคกสว่าง (จำลอง)',
                'vhid_code' => '34100103',
                'hoscode' => '99999',
                'phone' => '087-890-1237',
                'is_leader' => 0,
                'is_hl_coach' => 0,
                'status' => 'pending',
                'assigned_count' => 0,
                'completed_count' => 0,
                'dpac_count' => 0
            ]
        ];

        // Apply session mutations
        foreach ($baseVhvs as &$v) {
            $vid = $v['vhv_id'];
            if (isset($_SESSION['demo_vhvs_hl_coach'][$vid])) {
                $v['is_hl_coach'] = $_SESSION['demo_vhvs_hl_coach'][$vid] ? 1 : 0;
            }
            if (isset($_SESSION['demo_vhvs_leader'][$vid])) {
                $v['is_leader'] = $_SESSION['demo_vhvs_leader'][$vid] ? 1 : 0;
            }
            if (isset($_SESSION['demo_vhvs_status'][$vid])) {
                $v['status'] = $_SESSION['demo_vhvs_status'][$vid];
            }
        }
        unset($v);

        return $baseVhvs;
    }

    // 7. รายชื่อ อสม. ที่รอการอนุมัติ (Pending Approval)
    public static function getMockPendingVhvs() {
        $all = self::getMockVhvs();
        return array_values(array_filter($all, function($v) {
            return ($v['status'] ?? '') === 'pending';
        }));
    }

    // 8. การจัดการประชากรเป้าหมายจำลองพร้อมสถานะ เปิด/ปิด (Target Population with Active Toggle)
    public static function getMockTargetPopulation() {
        $targets = self::getMockTargets();
        foreach ($targets as &$t) {
            $cid = $t['cid'];
            // Session toggle for active/inactive target
            $t['is_active'] = $_SESSION['demo_target_status'][$cid] ?? 1;
            $t['hoscode'] = '99999';
            $t['vhid_code'] = '3410010' . $t['moo'];
            $t['sub_district_code'] = '341001';
        }
        unset($t);
        return $targets;
    }

    // 9. ข้อมูลสำหรับการวิเคราะห์และชาร์ตทั้งหมด (Complete Analytics & Multi-Round Comparisons)
    public static function getMockAnalyticsData() {
        return [
            'beforeAfterData' => [
                [
                    'enrollment_id' => 'DEMO_ENROLL_1',
                    'cid' => '9999900000003',
                    'first_name' => 'บุญมี',
                    'last_name' => 'มีโชค (จำลอง)',
                    'house_no' => '88',
                    'moo' => '2',
                    'hoscode' => '99999',
                    'risk_type' => 'BOTH',
                    'sbp_before' => 162,
                    'dbp_before' => 98,
                    'fbs_before' => 195,
                    'risk_before' => 'high_risk',
                    'sbp_after' => 138,
                    'dbp_after' => 86,
                    'fbs_after' => 145,
                    'risk_after' => 'risk',
                    'latest_round' => 2
                ],
                [
                    'enrollment_id' => 'DEMO_ENROLL_2',
                    'cid' => '9999900000002',
                    'first_name' => 'สมศรี',
                    'last_name' => 'สุขสรรค์ (จำลอง)',
                    'house_no' => '45/2',
                    'moo' => '1',
                    'hoscode' => '99999',
                    'risk_type' => 'BOTH',
                    'sbp_before' => 158,
                    'dbp_before' => 96,
                    'fbs_before' => 175,
                    'risk_before' => 'high_risk',
                    'sbp_after' => 132,
                    'dbp_after' => 84,
                    'fbs_after' => 128,
                    'risk_after' => 'risk',
                    'latest_round' => 2
                ],
                [
                    'enrollment_id' => 'DEMO_ENROLL_3',
                    'cid' => '9999900000005',
                    'first_name' => 'วิชัย',
                    'last_name' => 'มั่นคง (จำลอง)',
                    'house_no' => '15/3',
                    'moo' => '3',
                    'hoscode' => '99999',
                    'risk_type' => 'BOTH',
                    'sbp_before' => 162,
                    'dbp_before' => 98,
                    'fbs_before' => 210,
                    'risk_before' => 'high_risk',
                    'sbp_after' => 140,
                    'dbp_after' => 88,
                    'fbs_after' => 155,
                    'risk_after' => 'risk',
                    'latest_round' => 2
                ]
            ],
            'ncdMultiRoundData' => [
                [
                    'cid' => '9999900000003',
                    'first_name' => 'บุญมี',
                    'last_name' => 'มีโชค (จำลอง)',
                    'house_no' => '88',
                    'moo' => '2',
                    'hoscode' => '99999',
                    'sbp_r1' => 162,
                    'dbp_r1' => 98,
                    'dtx_r1' => 195,
                    'bmi_r1' => 27.5,
                    'date_r1' => '2025-11-10',
                    'sbp_r2' => 138,
                    'dbp_r2' => 86,
                    'dtx_r2' => 145,
                    'bmi_r2' => 25.8,
                    'r2_date' => '2026-02-15',
                    'sbp_latest' => 138,
                    'dbp_latest' => 86,
                    'dtx_latest' => 145,
                    'bmi_latest' => 25.8,
                    'date_latest' => '2026-02-15',
                    'latest_round' => 2,
                    'trend_label' => 'improved'
                ],
                [
                    'cid' => '9999900000004',
                    'first_name' => 'ทองสุข',
                    'last_name' => 'สดใส (จำลอง)',
                    'house_no' => '101',
                    'moo' => '2',
                    'hoscode' => '99999',
                    'sbp_r1' => 118,
                    'dbp_r1' => 76,
                    'dtx_r1' => 95,
                    'bmi_r1' => 21.2,
                    'date_r1' => '2025-11-12',
                    'sbp_r2' => 116,
                    'dbp_r2' => 74,
                    'dtx_r2' => 92,
                    'bmi_r2' => 21.0,
                    'r2_date' => '2026-02-18',
                    'sbp_latest' => 116,
                    'dbp_latest' => 74,
                    'dtx_latest' => 92,
                    'bmi_latest' => 21.0,
                    'date_latest' => '2026-02-18',
                    'latest_round' => 2,
                    'trend_label' => 'stable'
                ],
                [
                    'cid' => '9999900000001',
                    'first_name' => 'สมชาย',
                    'last_name' => 'ใจดี (จำลอง)',
                    'house_no' => '12/1',
                    'moo' => '1',
                    'hoscode' => '99999',
                    'sbp_r1' => 136,
                    'dbp_r1' => 86,
                    'dtx_r1' => 116,
                    'bmi_r1' => 24.2,
                    'date_r1' => '2025-11-05',
                    'sbp_r2' => 126,
                    'dbp_r2' => 80,
                    'dtx_r2' => 104,
                    'bmi_r2' => 23.5,
                    'r2_date' => '2026-02-10',
                    'sbp_latest' => 126,
                    'dbp_latest' => 80,
                    'dtx_latest' => 104,
                    'bmi_latest' => 23.5,
                    'date_latest' => '2026-02-10',
                    'latest_round' => 2,
                    'trend_label' => 'improved'
                ]
            ],
            'roundComparisonSummary' => [
                'total_re_screened' => 64,
                'improved' => 38,
                'improved_percent' => 59.4,
                'stable' => 18,
                'stable_percent' => 28.1,
                'worsened' => 8,
                'worsened_percent' => 12.5
            ],
            'riskStratification' => [
                'low' => ['label' => '< 10% (ความเสี่ยงต่ำ)', 'count' => 120, 'percent' => 64.9, 'color' => '#10B981'],
                'moderate' => ['label' => '10 - 20% (ความเสี่ยงปานกลาง)', 'count' => 35, 'percent' => 18.9, 'color' => '#F59E0B'],
                'high' => ['label' => '20 - 30% (ความเสี่ยงสูง)', 'count' => 18, 'percent' => 9.7, 'color' => '#EA580C'],
                'very_high' => ['label' => '30 - 40% (ความเสี่ยงสูงมาก)', 'count' => 8, 'percent' => 4.3, 'color' => '#DC2626'],
                'critical' => ['label' => '> 40% (อันตรายสูงรุนแรง)', 'count' => 4, 'percent' => 2.2, 'color' => '#7F1D1D']
            ],
            'bmiDistribution' => [
                'underweight' => ['label' => 'ผอม (< 18.5)', 'count' => 12, 'percent' => 6.5],
                'normal'      => ['label' => 'ปกติ (18.5 - 22.9)', 'count' => 88, 'percent' => 47.6],
                'overweight'  => ['label' => 'ท้วม (23.0 - 24.9)', 'count' => 46, 'percent' => 24.9],
                'obese_1'     => ['label' => 'อ้วนระดับ 1 (25.0 - 29.9)', 'count' => 28, 'percent' => 15.1],
                'obese_2'     => ['label' => 'อ้วนอันตราย (>= 30.0)', 'count' => 11, 'percent' => 5.9]
            ],
            'lifestyleStats' => [
                'diet_sweet' => 48,
                'diet_salty' => 62,
                'diet_fatty' => 38,
                'exercise_regular' => 74,
                'exercise_none' => 111,
                'stress_high' => 26,
                'smoking_active' => 32,
                'alcohol_regular' => 28
            ]
        ];
    }

    // 10. Helper Functions สำหรับการสลับสถานะจำลอง (Simulation Mutators - Session Only)
    public static function toggleTargetStatus($cid) {
        if (!isset($_SESSION['demo_target_status'])) $_SESSION['demo_target_status'] = [];
        $current = $_SESSION['demo_target_status'][$cid] ?? 1;
        $_SESSION['demo_target_status'][$cid] = ($current == 1) ? 0 : 1;
        return $_SESSION['demo_target_status'][$cid];
    }

    public static function toggleHlCoach($vhvId) {
        if (!isset($_SESSION['demo_vhvs_hl_coach'])) $_SESSION['demo_vhvs_hl_coach'] = [];
        $current = $_SESSION['demo_vhvs_hl_coach'][$vhvId] ?? 0;
        $_SESSION['demo_vhvs_hl_coach'][$vhvId] = ($current == 1) ? 0 : 1;
        return $_SESSION['demo_vhvs_hl_coach'][$vhvId];
    }

    public static function toggleVhvLeader($vhvId) {
        if (!isset($_SESSION['demo_vhvs_leader'])) $_SESSION['demo_vhvs_leader'] = [];
        $current = $_SESSION['demo_vhvs_leader'][$vhvId] ?? 0;
        $_SESSION['demo_vhvs_leader'][$vhvId] = ($current == 1) ? 0 : 1;
        return $_SESSION['demo_vhvs_leader'][$vhvId];
    }

    public static function approveVhv($vhvId) {
        if (!isset($_SESSION['demo_vhvs_status'])) $_SESSION['demo_vhvs_status'] = [];
        $_SESSION['demo_vhvs_status'][$vhvId] = 'approved';
        return true;
    }

    public static function rejectVhv($vhvId) {
        if (!isset($_SESSION['demo_vhvs_status'])) $_SESSION['demo_vhvs_status'] = [];
        $_SESSION['demo_vhvs_status'][$vhvId] = 'rejected';
        return true;
    }

    public static function assignTarget($cid, $vhvId) {
        if (!isset($_SESSION['demo_assignments'])) $_SESSION['demo_assignments'] = [];
        $_SESSION['demo_assignments'][$cid] = $vhvId;
        return true;
    }

    public static function processDemoDpac($postData) {
        if (!isset($_SESSION['demo_dpacs'])) $_SESSION['demo_dpacs'] = [];
        $cid = $postData['cid'] ?? 'unknown';
        $_SESSION['demo_dpacs'][$cid] = [
            'assignment_id' => $postData['assignment_id'] ?? null,
            'cid' => $cid,
            'sleep_quality' => $postData['sleep_quality'] ?? 'good',
            'care_level' => $postData['care_level'] ?? 'good',
            'next_visit_date' => $postData['next_visit_date'] ?? null,
            'guidance_summary' => $postData['guidance_summary'] ?? '',
            'health_progress' => $postData['health_progress'] ?? 'baseline',
            'saved_at' => date('Y-m-d H:i:s')
        ];
        return [
            'status' => 'success',
            'message' => 'บันทึกข้อมูลติดตาม DPAC ในโหมดจำลอง (Demo Mode) สำเร็จ 100%',
            'is_demo' => true
        ];
    }
}

