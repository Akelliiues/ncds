<?php
// api/messages.php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/demo_data.php';

// Demo Mode Handler
if (DemoDataProvider::isDemoMode()) {
    if (!isset($_SESSION['demo_read_message_ids']) || !is_array($_SESSION['demo_read_message_ids'])) {
        $_SESSION['demo_read_message_ids'] = [1]; // message 1 read by default, 2 is unread
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? 'get_messages');

    if ($action === 'get_messages') {
        $messages = [
            [
                'message_id' => 1,
                'title' => 'ยินดีต้อนรับสู่ NCD Portal ตาลสุม 2026',
                'message_body' => 'ขอขอบคุณ อสม. และเจ้าหน้าที่ทุกท่านที่ร่วมขับเคลื่อนการคัดกรองสุขภาพเชิงรุก (3อ. 2ส. 1น.) ในพื้นที่อำเภอตาลสุม',
                'sender_name' => 'ผู้ดูแลระบบ สสอ.ตาลสุม (จำลอง)',
                'sender_role' => 'super_admin',
                'priority' => 'normal',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'is_read' => in_array(1, $_SESSION['demo_read_message_ids']) ? 1 : 0
            ],
            [
                'message_id' => 2,
                'title' => 'แจ้งเตือนรณรงค์ติดตามกลุ่มเสี่ยง DPAC รอบ 2',
                'message_body' => 'ขอความร่วมมือ อสม. ทุกท่าน ติดตามเยี่ยมบ้านและบันทึกผลการนอนหลับ 1น. ร่วมกับการวัดความดันและน้ำตาลกลุ่มเสี่ยงในความดูแล',
                'sender_name' => 'แอดมิน รพ.สต.ดอนพันชาด (จำลอง)',
                'sender_role' => 'sub_admin',
                'priority' => 'urgent',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'is_read' => in_array(2, $_SESSION['demo_read_message_ids']) ? 1 : 0
            ]
        ];

        $unreadCount = 0;
        foreach ($messages as $m) {
            if (empty($m['is_read'])) {
                $unreadCount++;
            }
        }

        echo json_encode([
            'status' => 'success',
            'unread_count' => $unreadCount,
            'messages' => $messages
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'mark_read') {
        $messageId = intval($_POST['message_id'] ?? $_GET['message_id'] ?? 0);
        if ($messageId > 0 && !in_array($messageId, $_SESSION['demo_read_message_ids'])) {
            $_SESSION['demo_read_message_ids'][] = $messageId;
        }
        echo json_encode(['status' => 'success', 'message' => 'ทำเครื่องหมายอ่านแล้ว (โหมดจำลอง)'], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'mark_all_read') {
        $_SESSION['demo_read_message_ids'] = [1, 2];
        echo json_encode(['status' => 'success', 'message' => 'ทำเครื่องหมายอ่านทั้งหมดแล้ว (โหมดจำลอง)'], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'send_message') {
        echo json_encode(['status' => 'success', 'message' => 'ส่งข้อความสำเร็จ (โหมดจำลอง)', 'message_id' => 999], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get_messages');

// Determine current user ID & role
$currentUserId = null;
$currentUserRole = null;
$userHoscode = null;
$userSubDistrict = null;

if (!empty($_SESSION['admin_username'])) {
    $currentUserId = $_SESSION['admin_username'];
    $currentUserRole = !empty($_SESSION['is_super_admin']) ? 'super_admin' : 'staff';
    $userHoscode = $_SESSION['admin_hoscode'] ?? null;
} elseif (!empty($_SESSION['vhv_id']) || !empty($_SESSION['vhv_cid'])) {
    $currentUserId = $_SESSION['vhv_cid'] ?? $_SESSION['vhv_id'];
    $currentUserRole = 'vhv';
    $userHoscode = $_SESSION['hoscode'] ?? null;
    $userSubDistrict = $_SESSION['sub_district_code'] ?? null;
}

if (!$currentUserId) {
    // If not logged in, return zero messages gracefully
    echo json_encode(['status' => 'success', 'unread_count' => 0, 'messages' => []], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    if ($action === 'get_messages') {
        // Query applicable messages for this user
        // Note: If user is sender (sender_username = currentUserId), it is considered read automatically
        $sql = "
            SELECT m.*, 
                   IF(r.read_id IS NOT NULL OR m.sender_username = :sender_username, 1, 0) AS is_read,
                   r.read_at
            FROM system_messages m
            LEFT JOIN system_message_reads r ON m.message_id = r.message_id AND r.reader_id = :reader_id
            WHERE (
                m.target_type = 'all'
                OR (m.target_type = 'all_vhv' AND :role_vhv = 'vhv')
                OR (m.target_type = 'all_staff' AND :role_staff IN ('super_admin', 'staff'))
                OR (m.target_hcode IS NOT NULL AND m.target_hcode = :user_hcode)
                OR (m.target_sub_district IS NOT NULL AND m.target_sub_district = :user_sub_dist)
            )
            ORDER BY m.priority = 'emergency' DESC, m.priority = 'urgent' DESC, m.created_at DESC
            LIMIT 50
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sender_username' => $currentUserId,
            ':reader_id' => $currentUserId,
            ':role_vhv' => $currentUserRole,
            ':role_staff' => $currentUserRole,
            ':user_hcode' => $userHoscode,
            ':user_sub_dist' => $userSubDistrict
        ]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unreadCount = 0;
        foreach ($messages as $msg) {
            if (empty($msg['is_read'])) {
                $unreadCount++;
            }
        }

        echo json_encode([
            'status' => 'success',
            'unread_count' => $unreadCount,
            'messages' => $messages
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'mark_read') {
        $messageId = intval($_POST['message_id'] ?? $_GET['message_id'] ?? 0);
        if ($messageId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'รหัสข้อความไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO system_message_reads (message_id, reader_id, read_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE read_at = NOW()
        ");
        $stmt->execute([$messageId, $currentUserId]);

        echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'mark_all_read') {
        // Mark all visible messages for this user as read
        $stmtGet = $pdo->prepare("
            SELECT message_id FROM system_messages
            WHERE target_type = 'all' 
               OR (target_type = 'all_vhv' AND ? = 'vhv')
               OR (target_type = 'all_staff' AND ? IN ('super_admin', 'staff'))
               OR (target_hcode IS NOT NULL AND target_hcode = ?)
               OR (target_sub_district IS NOT NULL AND target_sub_district = ?)
        ");
        $stmtGet->execute([$currentUserRole, $currentUserRole, $userHoscode, $userSubDistrict]);
        $allIds = $stmtGet->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($allIds)) {
            $stmtInsert = $pdo->prepare("
                INSERT INTO system_message_reads (message_id, reader_id, read_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE read_at = NOW()
            ");
            foreach ($allIds as $mid) {
                $stmtInsert->execute([$mid, $currentUserId]);
            }
        }

        echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'send_message') {
        // Only Admin can send messages
        if (empty($_SESSION['admin_username'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ส่งข้อความประกาศ'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['message_body'] ?? '');
        $targetType = trim($_POST['target_type'] ?? 'all');
        $targetHcode = trim($_POST['target_hcode'] ?? '') ?: null;
        $targetSubDistrict = trim($_POST['target_sub_district'] ?? '') ?: null;
        $priority = in_array($_POST['priority'] ?? '', ['normal', 'urgent', 'emergency']) ? $_POST['priority'] : 'normal';

        if (empty($title) || empty($body)) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกหัวข้อและเนื้อหาข้อความ'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $senderName = function_exists('get_admin_title') ? get_admin_title() : 'ผู้ดูแลระบบ';

        $stmt = $pdo->prepare("
            INSERT INTO system_messages (sender_username, sender_name, sender_role, target_type, target_hcode, target_sub_district, title, message_body, priority, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['admin_username'],
            $senderName,
            $currentUserRole,
            $targetType,
            $targetHcode,
            $targetSubDistrict,
            $title,
            $body,
            $priority
        ]);

        $newMessageId = $pdo->lastInsertId();

        // Mark read for sender immediately
        if ($newMessageId) {
            $stmtRead = $pdo->prepare("INSERT INTO system_message_reads (message_id, reader_id, read_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE read_at = NOW()");
            $stmtRead->execute([$newMessageId, $currentUserId]);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'ส่งข้อความประกาศเรียบร้อยแล้ว',
            'message_id' => $newMessageId
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'delete_message') {
        if (empty($_SESSION['admin_username'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ลบข้อความ'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $messageId = intval($_POST['message_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM system_messages WHERE message_id = ?");
        $stmt->execute([$messageId]);

        $stmt2 = $pdo->prepare("DELETE FROM system_message_reads WHERE message_id = ?");
        $stmt2->execute([$messageId]);

        echo json_encode(['status' => 'success', 'message' => 'ลบข้อความประกาศแล้ว'], JSON_UNESCAPED_UNICODE);
        exit();
    }

} catch (\Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
}
