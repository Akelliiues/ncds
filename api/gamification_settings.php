<?php
// api/gamification_settings.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/gamification_config.php';

// Authentication & Admin authorization
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง (สำหรับผู้ดูแลระบบเท่านั้น)'], JSON_UNESCAPED_UNICODE);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

try {
    if ($action === 'get') {
        $activeConfig = get_gamification_config();
        $defaultConfig = get_default_gamification_config();
        $healthUnits = get_health_units();

        echo json_encode([
            'status' => 'success',
            'active_config' => $activeConfig,
            'default_config' => $defaultConfig,
            'health_units' => $healthUnits
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'save') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['config'])) {
            throw new Exception('ข้อมูลการตั้งค่าไม่ถูกต้อง');
        }

        $configToSave = $input['config'];
        $syncPoints = !empty($input['sync_points']);

        $success = save_gamification_config($configToSave);
        if (!$success) {
            throw new Exception('ไม่สามารถบันทึกการตั้งค่าลงฐานข้อมูลได้');
        }

        // If requested, sync past points to match the updated scoring rules
        $updatedRows = 0;
        if ($syncPoints && !empty($configToSave['scoring_rules'])) {
            $mode = $configToSave['scoring_rules']['mode'] ?? 'progressive';
            if ($mode === 'progressive') {
                $stmt = $pdo->exec("
                    UPDATE vhv_rewards r
                    JOIN screening_results s ON r.screening_id = s.screening_id
                    SET r.points_earned = GREATEST(1.00, CAST(IFNULL(s.round_number, 1) AS DECIMAL(4,2)))
                ");
                $updatedRows = $stmt;
            } elseif ($mode === 'custom' && !empty($configToSave['scoring_rules']['round_points'])) {
                foreach ($configToSave['scoring_rules']['round_points'] as $r => $pts) {
                    $rNum = (int)$r;
                    $ptsVal = max(0.25, (float)$pts);
                    $stmt = $pdo->prepare("
                        UPDATE vhv_rewards r
                        JOIN screening_results s ON r.screening_id = s.screening_id
                        SET r.points_earned = ?
                        WHERE IFNULL(s.round_number, 1) = ?
                    ");
                    $stmt->execute([$ptsVal, $rNum]);
                    $updatedRows += $stmt->rowCount();
                }
            }

            // Sync DPAC points
            if (isset($configToSave['scoring_rules']['dpac_points'])) {
                $dpacPts = max(0.25, (float)$configToSave['scoring_rules']['dpac_points']);
                $stmtD = $pdo->prepare("
                    UPDATE vhv_rewards r
                    JOIN dpac_followups f ON r.followup_id = f.followup_id
                    SET r.points_earned = ?
                ");
                $stmtD->execute([$dpacPts]);
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'บันทึกการตั้งค่าเรียบร้อยแล้ว' . ($syncPoints ? " (ปรับปรุงคะแนนย้อนหลังแล้ว {$updatedRows} รายการ)" : ''),
            'active_config' => get_gamification_config()
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'reset') {
        $input = json_decode(file_get_contents('php://input'), true);
        $section = $input['section'] ?? 'all';

        $success = reset_gamification_config($section);
        if (!$success) {
            throw new Exception('ไม่สามารถคืนค่าเริ่มต้นได้');
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'คืนค่าเริ่มต้นของระบบเรียบร้อยแล้ว',
            'active_config' => get_gamification_config(),
            'default_config' => get_default_gamification_config()
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } else {
        throw new Exception('ไม่พบคำสั่งที่ต้องการ');
    }

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
