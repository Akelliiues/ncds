<?php
// api/save_citizen_screening.php - บันทึกผลการประเมินสุขภาพตนเองของประชาชน (Citizen Self-Screening)
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

// Accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    // Fallback to $_POST if not JSON
    $input = $_POST;
}

if (empty($input)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีข้อมูลสำหรับบันทึก']);
    exit();
}

// Extract & Validate Anonymous Health Indicators
$allowed_gender = ['male', 'female'];
$allowed_age = ['young', 'middle', 'senior'];
$allowed_shape = ['thin', 'slim', 'chubby', 'obese'];
$allowed_habit3 = ['low', 'med', 'high'];
$allowed_veggie = ['good', 'poor'];
$allowed_exercise = ['regular', 'some', 'sedentary'];
$allowed_sleep = ['good', 'poor'];
$allowed_substance = ['none', 'some', 'regular'];
$allowed_family = ['no', 'yes'];
$allowed_risk = ['green', 'yellow', 'red'];

$gender = in_array($input['gender'] ?? '', $allowed_gender) ? $input['gender'] : 'female';
$age_group = in_array($input['age_group'] ?? '', $allowed_age) ? $input['age_group'] : 'young';
$body_shape = in_array($input['body_shape'] ?? '', $allowed_shape) ? $input['body_shape'] : 'slim';
$sweet_habit = in_array($input['sweet_habit'] ?? '', $allowed_habit3) ? $input['sweet_habit'] : 'low';
$salt_habit = in_array($input['salt_habit'] ?? '', $allowed_habit3) ? $input['salt_habit'] : 'low';
$veggie_habit = in_array($input['veggie_habit'] ?? '', $allowed_veggie) ? $input['veggie_habit'] : 'good';
$exercise_habit = in_array($input['exercise_habit'] ?? '', $allowed_exercise) ? $input['exercise_habit'] : 'regular';
$sleep_habit = in_array($input['sleep_habit'] ?? '', $allowed_sleep) ? $input['sleep_habit'] : 'good';
$substance_habit = in_array($input['substance_habit'] ?? '', $allowed_substance) ? $input['substance_habit'] : 'none';
$family_history = in_array($input['family_history'] ?? '', $allowed_family) ? $input['family_history'] : 'no';
$risk_points = max(0, min(30, intval($input['risk_points'] ?? 0)));
$risk_level = in_array($input['risk_level'] ?? '', $allowed_risk) ? $input['risk_level'] : 'green';

// Optional subdistrict code (if provided, otherwise null)
$sub_district_code = !empty($input['sub_district_code']) ? substr(preg_replace('/[^0-9]/', '', $input['sub_district_code']), 0, 10) : null;

// Session Hash: A completely anonymous, non-reversible client token to avoid double submission
$session_hash = !empty($input['session_hash']) ? substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $input['session_hash']), 0, 64) : null;

$budget_year = function_exists('get_current_budget_year') ? get_current_budget_year() : (int)date('Y');
if ($budget_year < 2500 && $budget_year > 2000) {
    // Current year is in CE e.g. 2026
} elseif ($budget_year >= 2500) {
    $budget_year = $budget_year - 543;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO `citizen_self_screenings` (
            `session_hash`,
            `budget_year`,
            `gender`,
            `age_group`,
            `body_shape`,
            `sweet_habit`,
            `salt_habit`,
            `veggie_habit`,
            `exercise_habit`,
            `sleep_habit`,
            `substance_habit`,
            `family_history`,
            `risk_points`,
            `risk_level`,
            `sub_district_code`,
            `created_at`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $success = $stmt->execute([
        $session_hash,
        $budget_year,
        $gender,
        $age_group,
        $body_shape,
        $sweet_habit,
        $salt_habit,
        $veggie_habit,
        $exercise_habit,
        $sleep_habit,
        $substance_habit,
        $family_history,
        $risk_points,
        $risk_level,
        $sub_district_code
    ]);

    if ($success) {
        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึกข้อมูลสถิติการประเมินสุขภาพตนเองสำเร็จ (Citizen Self-Screening Logged)',
            'risk_level' => $risk_level,
            'risk_points' => $risk_points
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถบันทึกข้อมูลได้'], JSON_UNESCAPED_UNICODE);
    }
} catch (\Throwable $e) {
    // Fail gracefully
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
