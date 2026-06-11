<?php
// api/auth.php
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'reset_password') {
    // 1. Check if the logged-in user is a VHV Leader
    if (!isset($_SESSION['vhv_id']) || !$_SESSION['is_leader']) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่มีสิทธิ์ในการเข้าถึงการจัดการนี้ (สิทธิ์ประธาน อสม. เท่านั้น)'
        ]);
        exit();
    }

    $leaderVhvId = $_SESSION['vhv_id'];
    $targetVhvId = $_POST['target_vhv_id'] ?? '';

    if (empty($targetVhvId)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่ระบุเป้าหมาย อสม. ที่ต้องการรีเซ็ตรหัสผ่าน'
        ]);
        exit();
    }

    try {
        // 2. Fetch Leader's Village Code and Rank
        $leaderStmt = $pdo->prepare("SELECT vhid_code, hoscode, is_leader FROM vhv_users WHERE vhv_id = ?");
        $leaderStmt->execute([$leaderVhvId]);
        $leader = $leaderStmt->fetch();

        // 3. Fetch Target's Village Code
        $targetStmt = $pdo->prepare("SELECT vhid_code, hoscode, vhv_name FROM vhv_users WHERE vhv_id = ?");
        $targetStmt->execute([$targetVhvId]);
        $target = $targetStmt->fetch();

        if (!$target) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่พบข้อมูล อสม. ที่ต้องการรีเซ็ต'
            ]);
            exit();
        }

        // 4. Cross-District Guard: Verify scope of permission based on rank
        $leaderRank = intval($leader['is_leader'] ?? 1);
        $allowed = false;
        $errorMsg = '';
        
        if ($leaderRank >= 3) {
            // District President can reset anyone in the district
            $allowed = true;
        } elseif ($leaderRank == 2) {
            // Sub-district President can reset anyone in the same sub-district (tambon = first 6 chars of vhid_code)
            $leaderTambon = substr($leader['vhid_code'], 0, 6);
            $targetTambon = substr($target['vhid_code'], 0, 6);
            if ($leaderTambon === $targetTambon) {
                $allowed = true;
            } else {
                $errorMsg = 'ข้อจำกัดความปลอดภัย: ในฐานะประธาน อสม. ระดับตำบล คุณสามารถรีเซ็ตรหัสผ่านให้กับ อสม. ในตำบลเดียวกันเท่านั้น';
            }
        } else {
            // Village President can only reset anyone in the same village and hoscode
            if ($leader['vhid_code'] === $target['vhid_code'] && $leader['hoscode'] === $target['hoscode']) {
                $allowed = true;
            } else {
                $errorMsg = 'ข้อจำกัดความปลอดภัย: คุณสามารถรีเซ็ตรหัสผ่านให้กับ อสม. ในเขตความรับผิดชอบหมู่บ้านเดียวกันเท่านั้น';
            }
        }

        if (!$allowed) {
            echo json_encode([
                'status' => 'error',
                'message' => $errorMsg ?: 'ไม่มีสิทธิ์เข้าถึงข้อมูล อสม. รายนี้'
            ]);
            exit();
        }

        // 5. Perform Reset to default password "1234"
        $newHash = password_hash('1234', PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE vhv_users SET password_hash = ? WHERE vhv_id = ?");
        $updateStmt->execute([$newHash, $targetVhvId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'รีเซ็ตรหัสผ่าน อสม. ' . htmlspecialchars($target['vhv_name']) . ' เรียบร้อยแล้ว รหัสเริ่มต้นคือ 1234'
        ]);
        exit();

    } catch (\PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดฐานข้อมูล: ' . $e->getMessage()
        ]);
        exit();
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid action'
    ]);
}
