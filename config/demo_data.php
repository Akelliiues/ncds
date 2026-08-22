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

            // --- หมู่ 4: ล็อกการคัดกรองเพราะ "สแกนข้ามเขต" ---
            [
                'cid' => '9999900000007',
                'first_name' => 'มานพ',
                'last_name' => 'สมบูรณ์ (จำลอง)',
                'sex' => '1',
                'birth' => '1970-10-18',
                'age' => 56,
                'house_no' => '54',
                'moo' => '4',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 1,
                'need_screen_ht' => 1,
                'health_status_origin' => 'BOTH',
                'assignment_status' => 'out_of_territory',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. ชาญชัย (เขต ม.4)',
                'last_sbp' => 142,
                'last_dbp' => 88,
                'last_dtx' => 150,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo4_outofarea'
            ],
            [
                'cid' => '9999900000008',
                'first_name' => 'สายใจ',
                'last_name' => 'ยิ่งยง (จำลอง)',
                'sex' => '2',
                'birth' => '1952-12-05',
                'age' => 74,
                'house_no' => '76/1',
                'moo' => '4',
                'tambon_name' => 'ตาลสุม (จำลอง)',
                'need_screen_dm' => 0,
                'need_screen_ht' => 1,
                'health_status_origin' => 'HT_ONLY',
                'assignment_status' => 'out_of_territory',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. ชาญชัย (เขต ม.4)',
                'last_sbp' => 155,
                'last_dbp' => 95,
                'last_dtx' => 110,
                'last_dtx_type' => 'fpg',
                'demo_category' => 'moo4_outofarea'
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

        // Action / Advice (Concise & Bold)
        $adviceList = [];
        if ($sbp >= 130 || $dbp >= 85) {
            $adviceList[] = [
                'icon' => '🧂',
                'title' => 'ลดเค็ม เลี่ยงปลาร้า/แจ่วบอง',
                'desc' => 'งดซดน้ำแกง เลี่ยงของเค็มจัด ช่วยลดความดันโลหิต'
            ];
        }
        if ($dtx >= 100 || $bmi >= 23) {
            $adviceList[] = [
                'icon' => '🍬',
                'title' => 'ลดหวาน งดน้ำอัดลม/ชาหวาน',
                'desc' => 'ลดแป้งและของหวาน ช่วยคุมระดับน้ำตาล'
            ];
        }
        if ($bmi >= 23 || $riskLevel === 'risk') {
            $adviceList[] = [
                'icon' => '🚶‍♂️',
                'title' => 'ขยับกาย เดินวันละ 30 นาที',
                'desc' => 'เดินสะสมก้าวต่อเนื่อง ช่วยเผาผลาญไขมันและคุมน้ำหนัก'
            ];
        }
        if ($riskLevel === 'high_risk' || $riskLevel === 'critical') {
            $adviceList[] = [
                'icon' => '🩺',
                'title' => 'ส่งต่อพบแพทย์ รพ.สต.',
                'desc' => 'นัดติดตามตรวจยืนยันสภาวะโรคเพื่อรับการรักษาที่เหมาะสม'
            ];
        }
        if (empty($adviceList)) {
            $adviceList[] = [
                'icon' => '🌟',
                'title' => 'รักษาวินัย 3อ. 2ส. ยอดเยี่ยม',
                'desc' => 'ปฏิบัติตัวดีเยี่ยม รักษาสุขภาพแข็งแรงต่อเนื่อง'
            ];
        }

        // Reward points (2x for Round 2)
        $rewardPoints = ($roundNumber >= 2) ? 2 : 1;

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
}
