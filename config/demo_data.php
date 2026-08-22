<?php
// config/demo_data.php - Provider ข้อมูลจำลองสมจริงสำหรับโหมด Demo Sandbox

class DemoDataProvider {

    public static function isDemoMode() {
        return isset($_SESSION['is_demo_mode']) && $_SESSION['is_demo_mode'] === true;
    }

    public static function getDemoRole() {
        return $_SESSION['demo_role'] ?? 'vhv';
    }

    public static function getDemoVhvTasks() {
        return [
            'pending' => [
                [
                    'assignment_id' => 'DEMO_ASSIGN_1',
                    'first_name' => 'สมชาย',
                    'last_name' => 'ใจดี',
                    'sex' => '1',
                    'birthdate' => '1968-05-15',
                    'age' => 58,
                    'house_no' => '12/1',
                    'moo' => '1',
                    'tambon_name' => 'ตาลสุม',
                    'sub_district_code' => '341001',
                    'need_screen_dm' => 1,
                    'need_screen_ht' => 1,
                    'origin' => 'BOTH',
                    'latitude' => 15.4321,
                    'longitude' => 104.9812,
                    'last_sbp' => 135,
                    'last_dbp' => 85,
                    'last_dtx' => 118,
                    'last_dtx_type' => 'fpg'
                ],
                [
                    'assignment_id' => 'DEMO_ASSIGN_2',
                    'first_name' => 'สมศรี',
                    'last_name' => 'สุขสรรค์',
                    'sex' => '2',
                    'birthdate' => '1955-09-20',
                    'age' => 71,
                    'house_no' => '45/2',
                    'moo' => '1',
                    'tambon_name' => 'ตาลสุม',
                    'sub_district_code' => '341001',
                    'need_screen_dm' => 1,
                    'need_screen_ht' => 0,
                    'origin' => 'DM_ONLY',
                    'latitude' => 15.4310,
                    'longitude' => 104.9825,
                    'last_sbp' => 120,
                    'last_dbp' => 78,
                    'last_dtx' => 142,
                    'last_dtx_type' => 'fpg'
                ]
            ],
            'dpac' => [
                [
                    'assignment_id' => 'DEMO_ASSIGN_3',
                    'dpac_id' => 'DEMO_DPAC_1',
                    'first_name' => 'บุญมี',
                    'last_name' => 'มีโชค',
                    'sex' => '1',
                    'birthdate' => '1962-11-04',
                    'age' => 64,
                    'house_no' => '88',
                    'moo' => '1',
                    'tambon_name' => 'ตาลสุม',
                    'sub_district_code' => '341001',
                    'round_no' => 2,
                    'latitude' => 15.4335,
                    'longitude' => 104.9840,
                    'last_sbp' => 148,
                    'last_dbp' => 92,
                    'last_dtx' => 165,
                    'last_dtx_type' => 'fpg'
                ]
            ],
            'completed' => [
                [
                    'assignment_id' => 'DEMO_ASSIGN_4',
                    'first_name' => 'ทองสุข',
                    'last_name' => 'สดใส',
                    'sex' => '2',
                    'birthdate' => '1975-01-30',
                    'age' => 51,
                    'house_no' => '101',
                    'moo' => '1',
                    'tambon_name' => 'ตาลสุม',
                    'sub_district_code' => '341001',
                    'need_screen_dm' => 1,
                    'need_screen_ht' => 1,
                    'origin' => 'BOTH',
                    'latitude' => 15.4340,
                    'longitude' => 104.9850,
                    'last_sbp' => 118,
                    'last_dbp' => 76,
                    'last_dtx' => 95,
                    'last_dtx_type' => 'fpg'
                ]
            ],
            'skipped' => []
        ];
    }

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

        $sbp = max($sbp1, $sbp2);
        $dbp = max($dbp1, $dbp2);
        if ($sbp == 0) $sbp = 120;
        if ($dbp == 0) $dbp = 80;

        $bmi = ($weight > 0 && $height > 0) ? round($weight / pow($height / 100, 2), 2) : 0;

        // คำนวณความเสี่ยง
        $isCriticalBp = ($sbp >= 180 || $dbp >= 110);
        $isCriticalDtx = ($dtx >= 300);
        $isHighBp = ($sbp >= 140 || $dbp >= 90);
        $isRiskBp = ($sbp >= 120 || $dbp >= 80);
        $isHighDtx = ($dtxType === 'fpg' ? $dtx >= 126 : $dtx >= 200);
        $isRiskDtx = ($dtxType === 'fpg' ? ($dtx >= 100 && $dtx < 126) : ($dtx >= 140 && $dtx < 200));

        $riskLevel = 'normal';
        $riskColor = '#10B981';
        $riskTitle = '🟢 ปกติ (สุขภาพดี)';
        $statusDesc = 'ค่าความดันโลหิตและระดับน้ำตาลในเลือดอยู่ในเกณฑ์ปกติ สุขภาพแข็งแรงดีเยี่ยม';

        if ($isCriticalBp || $isCriticalDtx) {
            $riskLevel = 'critical';
            $riskColor = '#EF4444';
            $riskTitle = '🔴 วิกฤต (ต้องพบแพทย์ทันที)';
            $statusDesc = 'พบค่าสัญญาณชีพสูงวิกฤต เสี่ยงต่อภาวะ Hypertensive Crisis หรือ Severe Hyperglycemia ต้องนำส่ง รพ.สต./โรงพยาบาล ด่วน!';
        } elseif ($isHighBp || $isHighDtx) {
            $riskLevel = 'high_risk';
            $riskColor = '#F97316';
            $riskTitle = '🟠 กลุ่มเสี่ยงสูง / สงสัยป่วย';
            $statusDesc = 'พบค่าความดันหรือระดับน้ำตาลสูงกว่าเกณฑ์ปกติ ควรได้รับการตรวจยืนยันโรคโดยแพทย์ที่ รพ.สต.';
        } elseif ($isRiskBp || $isRiskDtx || $bmi >= 23) {
            $riskLevel = 'risk';
            $riskColor = '#F59E0B';
            $riskTitle = '🟡 กลุ่มเสี่ยง (ต้องปรับพฤติกรรม)';
            $statusDesc = 'พบค่าความดัน/น้ำตาลเริ่มสูง หรือมีภาวะท้วม/อ้วน ควรปรับเปลี่ยนพฤติกรรม 3อ 2ส เพื่อป้องกันโรคเรื้อรัง';
        }

        // คำแนะนำเฉพาะบุคคล
        $adviceList = [];
        if ($sbp >= 130 || $dbp >= 85) {
            $adviceList[] = [
                'icon' => '🧂',
                'title' => 'ลดเค็ม โซเดียม',
                'desc' => 'หลีกเลี่ยงซีอิ๊ว น้ำปลา ผงชูรส และอาหารแปรรูป งดซดน้ำแกงจืด/น้ำก๋วยเตี๋ยว'
            ];
        }
        if ($dtx >= 100 || $bmi >= 23) {
            $adviceList[] = [
                'icon' => '🍬',
                'title' => 'ลดหวาน ขนม ของหวาน',
                'desc' => 'งดน้ำหวาน น้ำอัดลม ชาไข่มุก ลดปริมาณข้าวแป้งทานแต่พอดี เน้นผักใบเขียว'
            ];
        }
        if ($bmi >= 23) {
            $adviceList[] = [
                'icon' => '🚶‍♂️',
                'title' => 'เพิ่มการขยับกาย ออกกำลังกาย',
                'desc' => 'เดินสะสมก้าวอย่างน้อยวันละ 30 นาที 5 วันต่อสัปดาห์ เพื่อช่วยลดน้ำหนักและดัชนีมวลกาย'
            ];
        }
        $adviceList[] = [
            'icon' => '🍎',
            'title' => 'ยึดหลัก 3อ 2ส',
            'desc' => 'อาหารดี อารมณ์ดี ออกกำลังกายดี ไม่สูบบุหรี่ ไม่ดื่มสุรา'
        ];

        return [
            'status' => 'success',
            'message' => 'บันทึกข้อมูลคัดกรองโหมดทดลองเรียบร้อยแล้ว',
            'reward_points' => 1,
            'is_demo' => true,
            'summary_metadata' => [
                'resident_name' => $postData['_residentName'] ?? 'ผู้รับการคัดกรอง (โหมดทดลอง)',
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
                'advice_list' => $adviceList,
                'next_appointment' => date('d/m/Y', strtotime('+3 months'))
            ]
        ];
    }
}
