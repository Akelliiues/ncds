<?php
// config/demo_data.php - Provider ข้อมูลจำลองสมจริง 100% สำหรับโหมด Demo Sandbox (ปลอดข้อมูลจริง 100%)

class DemoDataProvider {

    public static function isDemoMode() {
        return isset($_SESSION['is_demo_mode']) && $_SESSION['is_demo_mode'] === true;
    }

    public static function getDemoRole() {
        return $_SESSION['demo_role'] ?? 'vhv';
    }

    // 1. รายชื่อประชากรจำลอง 100% (CID สมมติ 99999..., ชื่อสมมติ ปลอดภัยจากข้อมูลจริง 100%)
    public static function getMockTargets() {
        return [
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
                'last_sbp' => 135,
                'last_dbp' => 85,
                'last_dtx' => 118,
                'last_dtx_type' => 'fpg'
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
                'need_screen_ht' => 0,
                'health_status_origin' => 'DM_ONLY',
                'assignment_status' => 'pending',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. สมชาย ใจดี (จำลอง)',
                'last_sbp' => 120,
                'last_dbp' => 78,
                'last_dtx' => 142,
                'last_dtx_type' => 'fpg'
            ],
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
                'last_sbp' => 148,
                'last_dbp' => 92,
                'last_dtx' => 165,
                'last_dtx_type' => 'fpg'
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
                'assignment_status' => 'completed',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. สายสมร มีสุข (จำลอง)',
                'last_sbp' => 118,
                'last_dbp' => 76,
                'last_dtx' => 95,
                'last_dtx_type' => 'fpg'
            ],
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
                'assignment_status' => 'completed',
                'round_number' => 1,
                'assigned_vhv' => 'อสม. บุญทัน เจริญดี (จำลอง)',
                'last_sbp' => 162,
                'last_dbp' => 98,
                'last_dtx' => 210,
                'last_dtx_type' => 'fpg'
            ]
        ];
    }

    // 2. งาน อสม. จำลอง
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
                array_merge($targets[3], ['assignment_id' => 'DEMO_ASSIGN_4']),
                array_merge($targets[4], ['assignment_id' => 'DEMO_ASSIGN_5'])
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

        $riskLevel = 'normal';
        $riskColor = '#10B981';
        $riskTitle = '🟢 สุขภาพปกติ';
        $statusDesc = 'ค่าความดันและน้ำตาลอยู่ในเกณฑ์มาตรฐาน สุขภาพดีเยี่ยม';

        if ($isCriticalBp || $isCriticalDtx) {
            $riskLevel = 'critical';
            $riskColor = '#EF4444';
            $riskTitle = '🔴 วิกฤต (พบแพทย์ด่วน)';
            $statusDesc = 'พบค่าสัญญาณชีพสูงวิกฤต ต้องนำส่ง รพ.สต./โรงพยาบาล ทันที';
        } elseif ($isHighBp || $isHighDtx) {
            $riskLevel = 'high_risk';
            $riskColor = '#EF4444';
            $riskTitle = '🔴 กลุ่มเสี่ยงสูง (สงสัยป่วย)';
            $statusDesc = 'ความดันหรือน้ำตาลสูงเกินเกณฑ์ แนะนำตรวจยืนยันที่ รพ.สต.';
        } elseif ($isRiskBp || $isRiskDtx || $bmi >= 23) {
            $riskLevel = 'risk';
            $riskColor = '#F59E0B';
            $riskTitle = '🟡 กลุ่มเสี่ยง (ต้องปรับ 3อ. 2ส.)';
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
}
