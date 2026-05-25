<?php
// api/save_screening.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/line_config.php';

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['vhv_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'เข้าสู่ระบบหมดอายุ กรุณาเข้าสู่ระบบใหม่'
    ]);
    exit();
}

$vhvId = $_SESSION['vhv_id'];
$action = $_POST['action'] ?? '';
$assignmentId = (int)($_POST['assignment_id'] ?? 0);

if ($assignmentId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ข้อมูลใบงานมอบหมายงานไม่ถูกต้อง'
    ]);
    exit();
}

// Haversine formula to calculate distance in meters between two coordinates
function getDistanceMeters($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371000; // meters

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) * sin($dLng / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadius * $c;

    return $distance; // in meters
}

// Function to send Line Flex Message via curl
function sendLineFlexMessage($lineUserId, $flexData) {
    if (LINE_CHANNEL_ACCESS_TOKEN === 'YOUR_CHANNEL_ACCESS_TOKEN') {
        return false; // Skip if token is placeholder
    }

    $url = 'https://api.line.me/v2/bot/message/push';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
    ];

    $body = [
        'to' => $lineUserId,
        'messages' => [
            [
                'type' => 'flex',
                'altText' => 'การ์ดความห่วงใยผลการตรวจคัดกรองสุขภาพ',
                'contents' => $flexData
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

try {
    // Fetch target population & home coordinates
    $assignStmt = $pdo->prepare("
        SELECT a.*, p.cid, p.hid, p.first_name, p.last_name, p.latitude as home_lat, p.longitude as home_lng
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.assignment_id = ?
    ");
    $assignStmt->execute([$assignmentId]);
    $assignment = $assignStmt->fetch();

    if (!$assignment) {
        throw new Exception("ไม่พบข้อมูลใบงานมอบหมายที่ระบุ");
    }

    $targetCid = $assignment['target_cid'];
    $hid = $assignment['hid'];
    $residentName = $assignment['first_name'] . ' ' . $assignment['last_name'];

    if ($action === 'save_screening') {
        $pdo->beginTransaction();

        $weight = (float)($_POST['weight'] ?? 0);
        $height = (float)($_POST['height'] ?? 0);
        $waist = (float)($_POST['waist'] ?? 0);
        $sys1 = (int)($_POST['sys_bp1'] ?? 0);
        $dia1 = (int)($_POST['dia_bp1'] ?? 0);
        $sys2 = ($_POST['sys_bp2'] !== '') ? (int)$_POST['sys_bp2'] : null;
        $dia2 = ($_POST['dia_bp2'] !== '') ? (int)$_POST['dia_bp2'] : null;
        $dtx = ($_POST['dtx_value'] !== '') ? (int)$_POST['dtx_value'] : null;
        $dtxType = $_POST['dtx_type'] ?? 'fpg';
        $lat = (float)($_POST['screening_lat'] ?? 0);
        $lng = (float)($_POST['screening_lng'] ?? 0);

        // Calculate BMI = Weight / (Height/100)^2
        $bmi = 0;
        if ($height > 0) {
            $bmi = $weight / (($height / 100) * ($height / 100));
        }

        // Retrieve risk factors from POST
        $diet = $_POST['diet_risk'] ?? 'green';
        $exercise = $_POST['exercise_risk'] ?? 'green';
        $stress = $_POST['stress_risk'] ?? 'green';
        $smoking = $_POST['smoking_risk'] ?? 'green';
        $alcohol = $_POST['alcohol_risk'] ?? 'green';
        $cvRiskScore = (float)($_POST['cv_risk_score'] ?? 0);

        // 0. Delete any previous skipped entry to prevent duplicate rows for this assignment
        $delStmt = $pdo->prepare("DELETE FROM screening_results WHERE assignment_id = ?");
        $delStmt->execute([$assignmentId]);

        // 1. Insert into screening_results
        $screenStmt = $pdo->prepare("
            INSERT INTO screening_results 
            (assignment_id, sys_bp1, dia_bp1, sys_bp2, dia_bp2, dtx_value, dtx_type, weight, height, waist, bmi, diet_risk, exercise_risk, stress_risk, smoking_risk, alcohol_risk, cv_risk_score, screening_lat, screening_lng)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $screenStmt->execute([
            $assignmentId, $sys1, $dia1, $sys2, $dia2, $dtx, $dtxType,
            $weight, $height, $waist, round($bmi, 2),
            $diet, $exercise, $stress, $smoking, $alcohol, $cvRiskScore,
            $lat, $lng
        ]);
        $screeningId = $pdo->lastInsertId();

        // 2. Update task assignment status to 'completed'
        $updateAssign = $pdo->prepare("UPDATE task_assignments SET assignment_status = 'completed' WHERE assignment_id = ?");
        $updateAssign->execute([$assignmentId]);

        // 3. Dynamic GPS Buffer check for reward points:
        // Calculate distance from home location coordinates
        $approvalStatus = 'approved';
        $reasonLog = 'พิกัดถูกต้องภายใต้รัศมี 100 เมตร';
        
        if ($assignment['home_lat'] && $assignment['home_lng'] && $lat && $lng) {
            $distance = getDistanceMeters($assignment['home_lat'], $assignment['home_lng'], $lat, $lng);
            if ($distance > 100) {
                // If coordinates discrepancy exceeds 100 meters, set status to 'waiting' for supervisor review
                $approvalStatus = 'waiting';
                $reasonLog = 'พิกัดสแกนจริงห่างจากบ้านจดทะเบียน ' . round($distance, 1) . ' เมตร (เกินระยะ 100 เมตร)';
            }
        } else {
            // Missing reference coords, default to approved but log warning
            $approvalStatus = 'approved';
        }

        // Insert VHV reward points
        $rewardStmt = $pdo->prepare("
            INSERT INTO vhv_rewards (vhv_id, screening_id, points_earned, approval_status, approved_at)
            VALUES (?, ?, 1, ?, ?)
        ");
        $rewardStmt->execute([
            $vhvId,
            $screeningId,
            $approvalStatus,
            $approvalStatus === 'approved' ? date('Y-m-d H:i:s') : null
        ]);

        $pdo->commit();

        // 4. LINE flex notifications check
        $lineStmt = $pdo->prepare("SELECT line_user_id FROM line_house_mappings WHERE hid = ?");
        $lineStmt->execute([$hid]);
        $lineUsers = $lineStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($lineUsers)) {
            // Construct Flex Message body tailored to screening results
            $bpColor = ($sys1 >= 140 || $dia1 >= 90) ? '#EF4444' : '#10B981';
            $bpText = "$sys1/$dia1 mmHg (" . (($sys1 >= 140 || $dia1 >= 90) ? 'เสี่ยงความดันโลหิตสูง' : 'ระดับความดันปกติ') . ")";
            
            $sugarColor = '#10B981';
            $sugarText = 'ระดับปกติ';
            if ($dtx) {
                $sugarColor = ($dtx >= 126) ? '#EF4444' : '#10B981';
                $sugarText = "$dtx mg/dL (" . (($dtx >= 126) ? 'ค่าน้ำตาลสูงเกณฑ์เบาหวาน' : 'ระดับปกติ') . ")";
            }

            // High Sodium advice specific to Tal Sum local delicacies
            $sodiumAdvice = 'ควรดูแลรับประทานอาหารปรุงสุกสะอาด';
            if ($diet === 'red') {
                $sodiumAdvice = '⚠️ ระวังโซเดียมแฝงใน: ' . SODIUM_WARNING_FOODS['ปลาร้า'] . ' และ ' . SODIUM_WARNING_FOODS['แจ่ว'] . ' ควรเลี่ยงส้มตำปลาร้าและอาหารรสจัดแจ่วบอง';
            }

            foreach ($lineUsers as $user) {
                // Construct Flex card JSON structure
                $flexCard = [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            ['type' => 'text', 'text' => 'การ์ดความห่วงใย สสอ.ตาลสุม', 'weight' => 'bold', 'color' => '#ffffff', 'size' => 'lg'],
                            ['type' => 'text', 'text' => 'รายงานผลการตรวจสุขภาพประจำบ้าน', 'color' => '#a5b4fc', 'size' => 'xs', 'margin' => 'sm']
                        ],
                        'backgroundColor' => '#1e3a8a'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            ['type' => 'text', 'text' => 'ผู้รับการตรวจ: ' . $residentName, 'weight' => 'bold', 'size' => 'md'],
                            ['type' => 'separator', 'margin' => 'md'],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'margin' => 'lg',
                                'spacing' => 'sm',
                                'contents' => [
                                    [
                                        'type' => 'box',
                                        'layout' => 'horizontal',
                                        'contents' => [
                                            ['type' => 'text', 'text' => 'ความดัน:', 'color' => '#4b5563', 'size' => 'sm'],
                                            ['type' => 'text', 'text' => $bpText, 'weight' => 'bold', 'size' => 'sm', 'color' => $bpColor, 'align' => 'right']
                                        ]
                                    ],
                                    [
                                        'type' => 'box',
                                        'layout' => 'horizontal',
                                        'contents' => [
                                            ['type' => 'text', 'text' => 'น้ำตาลสะสม (DTX):', 'color' => '#4b5563', 'size' => 'sm'],
                                            ['type' => 'text', 'text' => $sugarText, 'weight' => 'bold', 'size' => 'sm', 'color' => $sugarColor, 'align' => 'right']
                                        ]
                                    ],
                                    [
                                        'type' => 'box',
                                        'layout' => 'horizontal',
                                        'contents' => [
                                            ['type' => 'text', 'text' => 'ดัชนีมวลกาย BMI:', 'color' => '#4b5563', 'size' => 'sm'],
                                            ['type' => 'text', 'text' => round($bmi, 1) . ' (' . ($bmi >= 23 ? 'เริ่มท้วม/อ้วน' : 'ปกติ') . ')', 'weight' => 'bold', 'size' => 'sm', 'align' => 'right']
                                        ]
                                    ]
                                ]
                            ],
                            ['type' => 'separator', 'margin' => 'lg'],
                            [
                                'type' => 'box',
                                'layout' => 'vertical',
                                'margin' => 'lg',
                                'contents' => [
                                    ['type' => 'text', 'text' => '❤️ คำแนะนำเพื่อสุขภาพ:', 'weight' => 'bold', 'size' => 'sm', 'color' => '#f59e0b', 'margin' => 'xs'],
                                    ['type' => 'text', 'text' => $sodiumAdvice, 'size' => 'sm', 'color' => '#374151', 'wrap' => true, 'margin' => 'xs']
                                ]
                            ]
                        ]
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            ['type' => 'text', 'text' => 'อสม. ' . $_SESSION['vhv_name'] . ' บันทึกข้อมูลคัดกรอง', 'align' => 'center', 'size' => 'xs', 'color' => '#9ca3af']
                        ]
                    ]
                ];
                sendLineFlexMessage($user, $flexCard);
            }
        }

        // Calculate overall risk for HL-Coach
        $hl_risk_level = 'green';
        if ($sys1 >= 160 || $dia1 >= 100 || ($dtxType === 'fpg' && $dtx >= 126) || ($dtxType === 'random' && $dtx >= 200) || $cvRiskScore >= 30) {
            $hl_risk_level = 'red';
        } elseif ($sys1 >= 140 || $dia1 >= 90 || ($dtxType === 'fpg' && $dtx >= 100) || ($dtxType === 'random' && $dtx >= 140) || $cvRiskScore >= 20) {
            $hl_risk_level = 'yellow';
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึกข้อมูลเรียบร้อย',
            'reward_status' => $approvalStatus,
            'log' => $reasonLog,
            'hl_risk_level' => $hl_risk_level,
            'is_hl_coach' => $_SESSION['is_hl_coach'] ?? false
        ]);
        exit();

    } elseif ($action === 'skip_case') {
        $pdo->beginTransaction();

        $skippedReason = $_POST['skipped_reason'] ?? 'ไม่อยู่บ้าน/ทำนา';
        $lat = (float)($_POST['lat'] ?? 0);
        $lng = (float)($_POST['lng'] ?? 0);

        // 1. Set task assignment status to 'skipped'
        $updateAssign = $pdo->prepare("UPDATE task_assignments SET assignment_status = 'skipped' WHERE assignment_id = ?");
        $updateAssign->execute([$assignmentId]);

        // 2. Insert record in screening_results with skipped reason
        $screenStmt = $pdo->prepare("
            INSERT INTO screening_results (assignment_id, skipped_reason, screening_lat, screening_lng)
            VALUES (?, ?, ?, ?)
        ");
        $screenStmt->execute([$assignmentId, $skippedReason, $lat, $lng]);
        $screeningId = $pdo->lastInsertId();

        // 3. Award VHV +1 reward point immediately (approval_status = 'approved') to motivate them
        $rewardStmt = $pdo->prepare("
            INSERT INTO vhv_rewards (vhv_id, screening_id, points_earned, approval_status, approved_at)
            VALUES (?, ?, 1, 'approved', CURRENT_TIMESTAMP)
        ");
        $rewardStmt->execute([$vhvId, $screeningId]);

        $pdo->commit();

        // 4. Send Skip Notification to Line relatives
        $lineStmt = $pdo->prepare("SELECT line_user_id FROM line_house_mappings WHERE hid = ?");
        $lineStmt->execute([$hid]);
        $lineUsers = $lineStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($lineUsers)) {
            foreach ($lineUsers as $user) {
                $flexCard = [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            ['type' => 'text', 'text' => 'อสม. แวะเยี่ยมบ้านการรักษา', 'weight' => 'bold', 'color' => '#ffffff', 'size' => 'md'],
                        ],
                        'backgroundColor' => '#f59e0b'
                    ],
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            ['type' => 'text', 'text' => 'ผู้รับการคัดกรอง: ' . $residentName, 'weight' => 'bold', 'size' => 'sm'],
                            ['type' => 'text', 'text' => 'สถานะ: ข้ามเคสการคัดกรองชั่วคราวเนื่องจาก: ' . $skippedReason, 'margin' => 'md', 'size' => 'sm', 'color' => '#ef4444', 'wrap' => true],
                            ['type' => 'text', 'text' => 'อสม. จะแวะมาทำการตรวจคัดกรองใหม่อีกครั้งภายหลัง กรุณาแจ้งสมาชิกในบ้านจัดแจงคิวเพื่อตรวจวัดในครั้งถัดไป', 'margin' => 'sm', 'size' => 'xs', 'color' => '#4b5563', 'wrap' => true]
                        ]
                    ]
                ];
                sendLineFlexMessage($user, $flexCard);
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึกข้ามใบงานสำเร็จ อสม. ได้รับ +1 แต้มสะสม'
        ]);
        exit();

    } else {
        throw new Exception("ไม่ระบุ Action การดำเนินการ");
    }

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
    exit();
}
