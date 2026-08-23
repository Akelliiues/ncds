<?php
// api/rewards.php - ระบบจัดการและแลกของรางวัล อสม. (Feature-Flagged Reward & Redemption Suite)
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/demo_data.php';

// Demo Mode Handler
if (DemoDataProvider::isDemoMode()) {
    if (!isset($_SESSION['demo_reward_enabled'])) {
        $_SESSION['demo_reward_enabled'] = 0; // Default closed/preview mode
    }
    if (!isset($_SESSION['demo_reward_redemptions'])) {
        $_SESSION['demo_reward_redemptions'] = [];
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? 'get_catalog');

    if ($action === 'get_catalog') {
        $sampleItems = [
            [
                'item_id' => 1,
                'title' => 'สเปรย์แอลกอฮอล์พกพา + ยาดมสมุนไพร',
                'description' => 'ชุดเซ็ตพกพาสำหรับ อสม. ลงพื้นที่ พร้อมซองใส่และสายคล้องคอ',
                'points_required' => 15,
                'category' => 'equipment',
                'icon_emoji' => '🧴',
                'stock_quantity' => 100,
                'redeemed_count' => 5,
                'is_active' => 1
            ],
            [
                'item_id' => 2,
                'title' => 'ร่มพับกันแดด/กันฝน สกรีน อสม.ตาลสุม',
                'description' => 'ร่มพับยูวี 3 ตอน แข็งแรง ทนทาน สกรีนตราสัญลักษณ์อำเภอตาลสุม',
                'points_required' => 30,
                'category' => 'souvenir',
                'icon_emoji' => '☂️',
                'stock_quantity' => 50,
                'redeemed_count' => 12,
                'is_active' => 1
            ],
            [
                'item_id' => 3,
                'title' => 'หมวกแก๊ป อสม. จิตอาสา นวัตกรรมสุขภาพ',
                'description' => 'หมวกแก๊ปผ้าคอนตอนเนื้อดี ระบายอากาศ ปักตราสัญลักษณ์ อสม.',
                'points_required' => 30,
                'category' => 'equipment',
                'icon_emoji' => '🧢',
                'stock_quantity' => 50,
                'redeemed_count' => 8,
                'is_active' => 1
            ],
            [
                'item_id' => 4,
                'title' => 'กระบอกน้ำสแตนเลสเก็บความเย็น 500ml',
                'description' => 'กระบอกน้ำเก็บอุณหภูมิร้อน-เย็น พกพาสะดวกสำหรับลงพื้นที่',
                'points_required' => 35,
                'category' => 'souvenir',
                'icon_emoji' => '🥤',
                'stock_quantity' => 30,
                'redeemed_count' => 4,
                'is_active' => 1
            ],
            [
                'item_id' => 5,
                'title' => 'เครื่องวัดความดันโลหิตดิจิทัลพกพา (ข้อมือ/ต้นแขน)',
                'description' => 'เครื่องวัดความดันระบบอัตโนมัติ แม่นยำ อ่านค่าง่าย พกพาสะดวก',
                'points_required' => 50,
                'category' => 'medical',
                'icon_emoji' => '🩺',
                'stock_quantity' => 20,
                'redeemed_count' => 2,
                'is_active' => 1
            ],
            [
                'item_id' => 6,
                'title' => 'ชุดแถบตรวจน้ำตาลในเลือด (Strip Test 25 ชิ้น)',
                'description' => 'ชุดแถบตรวจน้ำตาลปลายนิ้ว พร้อมเข็มเจาะ สำหรับติดตามกลุ่มเสี่ยง',
                'points_required' => 60,
                'category' => 'medical',
                'icon_emoji' => '🩸',
                'stock_quantity' => 20,
                'redeemed_count' => 1,
                'is_active' => 1
            ],
            [
                'item_id' => 7,
                'title' => 'โล่เกียรติยศ อสม. ดีเด่นระดับอำเภอตาลสุม',
                'description' => 'โล่เกียรติยศคริสตัล พร้อมสลักชื่อเชิดชูเกียรติ มอบในงานประชุมประจำปี',
                'points_required' => 100,
                'category' => 'honorary',
                'icon_emoji' => '🏆',
                'stock_quantity' => 10,
                'redeemed_count' => 0,
                'is_active' => 1
            ]
        ];

        echo json_encode([
            'status' => 'success',
            'system_enabled' => (int)$_SESSION['demo_reward_enabled'],
            'user_points' => [
                'total_earned' => 45.0,
                'points_spent' => 0.0,
                'available_points' => 45.0
            ],
            'categories' => [
                'all' => 'ทั้งหมด',
                'equipment' => 'อุปกรณ์ลงพื้นที่',
                'souvenir' => 'ของที่ระลึก อสม.',
                'medical' => 'เครื่องมือแพทย์',
                'honorary' => 'เชิดชูเกียรติ'
            ],
            'items' => $sampleItems,
            'my_redemptions' => $_SESSION['demo_reward_redemptions']
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'admin_toggle_system') {
        $val = isset($_POST['enabled']) ? (int)$_POST['enabled'] : (($_SESSION['demo_reward_enabled'] == 1) ? 0 : 1);
        $_SESSION['demo_reward_enabled'] = $val;
        echo json_encode([
            'status' => 'success',
            'system_enabled' => $val,
            'message' => $val ? 'เปิดระบบแลกของรางวัลเรียบร้อยแล้ว' : 'ปิดระบบแลกของรางวัลเรียบร้อยแล้ว (โหมดพรีวิว)'
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'redeem_item') {
        if (empty($_SESSION['demo_reward_enabled'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ระบบแลกของรางวัลยังไม่เปิดให้บริการในขณะนี้ กำลังอยู่ระหว่างเตรียมรายการของรางวัล'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $itemId = intval($_POST['item_id'] ?? 0);
        $code = 'RWD-DEMO' . rand(100, 999);
        $_SESSION['demo_reward_redemptions'][] = [
            'redemption_id' => rand(1000, 9999),
            'redemption_code' => $code,
            'item_id' => $itemId,
            'title' => 'ร่มพับกันแดด/กันฝน สกรีน อสม.ตาลสุม',
            'points_spent' => 30,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        echo json_encode([
            'status' => 'success',
            'redemption_code' => $code,
            'message' => 'ขอแลกของรางวัลสำเร็จ (โหมดจำลอง) นำรหัสไปแสดงที่ รพ.สต. เพื่อรับของรางวัล'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get_catalog');

// Dynamic categories from database
$categories = ['all' => 'ทั้งหมด'];
$categoryList = [];
try {
    $stmtCats = $pdo->query("SELECT * FROM `reward_categories` WHERE `is_active` = 1 ORDER BY `sort_order` ASC, `category_name` ASC");
    $categoryList = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categoryList as $c) {
        $categories[$c['category_code']] = $c['category_name'];
    }
} catch (\Exception $e) {
    $categories = [
        'all' => 'ทั้งหมด',
        'equipment' => 'อุปกรณ์ลงพื้นที่',
        'souvenir' => 'ของที่ระลึก อสม.',
        'medical' => 'เครื่องมือแพทย์',
        'honorary' => 'เชิดชูเกียรติ'
    ];
}

try {
    // 1. Get Catalog & User Points
    if ($action === 'get_catalog') {
        $systemEnabled = (int)get_system_setting('reward_system_enabled', 0);

        // Fetch active items
        $stmtItems = $pdo->query("
            SELECT * FROM `reward_items` 
            WHERE `is_active` = 1 
            ORDER BY `sort_order` ASC, `points_required` ASC
        ");
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // User points calculation
        $userPoints = [
            'total_earned' => 0.0,
            'points_spent' => 0.0,
            'available_points' => 0.0
        ];
        $myRedemptions = [];

        if (!empty($_SESSION['vhv_id'])) {
            $vhvId = (int)$_SESSION['vhv_id'];
            $isSandbox = function_exists('isSandboxMode') && isSandboxMode() ? 1 : 0;

            // Total Earned Points
            $stmtPts = $pdo->prepare("
                SELECT COALESCE(SUM(points_earned), 0) 
                FROM `vhv_rewards` 
                WHERE `vhv_id` = ? AND `approval_status` IN ('approved', 'waiting') AND `is_sandbox` = ?
            ");
            $stmtPts->execute([$vhvId, $isSandbox]);
            $totalEarned = (float)$stmtPts->fetchColumn();

            // Total Points Spent in Active/Pending Redemptions
            $stmtSpent = $pdo->prepare("
                SELECT COALESCE(SUM(points_spent), 0) 
                FROM `reward_redemptions` 
                WHERE `vhv_id` = ? AND `status` IN ('pending', 'fulfilled')
            ");
            $stmtSpent->execute([$vhvId]);
            $spent = (float)$stmtSpent->fetchColumn();

            $userPoints = [
                'total_earned' => $totalEarned,
                'points_spent' => $spent,
                'available_points' => max(0.0, $totalEarned - $spent)
            ];

            // Fetch My Redemptions History
            $stmtMy = $pdo->prepare("
                SELECT r.*, i.title, i.category, i.icon_emoji, i.image_url 
                FROM `reward_redemptions` r
                JOIN `reward_items` i ON r.item_id = i.item_id
                WHERE r.vhv_id = ?
                ORDER BY r.created_at DESC
                LIMIT 30
            ");
            $stmtMy->execute([$vhvId]);
            $myRedemptions = $stmtMy->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'status' => 'success',
            'system_enabled' => $systemEnabled,
            'user_points' => $userPoints,
            'categories' => $categories,
            'items' => $items,
            'my_redemptions' => $myRedemptions
        ], JSON_UNESCAPED_UNICODE);
        exit();

    // 2. VHV Redeem an Item
    } elseif ($action === 'redeem_item') {
        if (empty($_SESSION['vhv_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ อสม. ก่อนทำรายการ'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $systemEnabled = (int)get_system_setting('reward_system_enabled', 0);
        if (!$systemEnabled) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ระบบแลกของรางวัลยังไม่เปิดให้บริการในขณะนี้ กำลังอยู่ระหว่างเตรียมรายการของรางวัล ท่านสามารถสะสมคะแนนรอได้ค่ะ'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $vhvId = (int)$_SESSION['vhv_id'];
        $itemId = intval($_POST['item_id'] ?? 0);
        $isSandbox = function_exists('isSandboxMode') && isSandboxMode() ? 1 : 0;

        if ($itemId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'รหัสของรางวัลไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Check Item Details
        $stmtItem = $pdo->prepare("SELECT * FROM `reward_items` WHERE `item_id` = ? AND `is_active` = 1");
        $stmtItem->execute([$itemId]);
        $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลของรางวัลที่เลือก หรือของรางวัลนี้ถูกปิดใช้งานแล้ว'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if ($item['stock_quantity'] != -1 && $item['stock_quantity'] <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ขออภัย ของรางวัลนี้หมดชั่วคราว'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Check User Points
        $stmtPts = $pdo->prepare("
            SELECT COALESCE(SUM(points_earned), 0) 
            FROM `vhv_rewards` 
            WHERE `vhv_id` = ? AND `approval_status` IN ('approved', 'waiting') AND `is_sandbox` = ?
        ");
        $stmtPts->execute([$vhvId, $isSandbox]);
        $totalEarned = (float)$stmtPts->fetchColumn();

        $stmtSpent = $pdo->prepare("
            SELECT COALESCE(SUM(points_spent), 0) 
            FROM `reward_redemptions` 
            WHERE `vhv_id` = ? AND `status` IN ('pending', 'fulfilled')
        ");
        $stmtSpent->execute([$vhvId]);
        $spent = (float)$stmtSpent->fetchColumn();

        $available = max(0.0, $totalEarned - $spent);
        $required = (float)$item['points_required'];

        if ($available < $required) {
            echo json_encode([
                'status' => 'error',
                'message' => "คะแนนสะสมคงเหลือไม่เพียงพอ (ต้องการ {$required} แต้ม แต่มีคงเหลือ " . number_format($available, 1) . " แต้ม)"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Generate Unique Redemption Code
        $code = 'RWD-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $pdo->beginTransaction();

        // Insert Redemption Record
        $stmtInsert = $pdo->prepare("
            INSERT INTO `reward_redemptions` (`redemption_code`, `vhv_id`, `item_id`, `points_spent`, `status`, `created_at`)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmtInsert->execute([$code, $vhvId, $itemId, $required]);
        $redemptionId = $pdo->lastInsertId();

        // Update Stock & Redeemed Count
        if ($item['stock_quantity'] > 0) {
            $pdo->prepare("UPDATE `reward_items` SET `stock_quantity` = `stock_quantity` - 1, `redeemed_count` = `redeemed_count` + 1 WHERE `item_id` = ?")->execute([$itemId]);
        } else {
            $pdo->prepare("UPDATE `reward_items` SET `redeemed_count` = `redeemed_count` + 1 WHERE `item_id` = ?")->execute([$itemId]);
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'redemption_id' => $redemptionId,
            'redemption_code' => $code,
            'item_title' => $item['title'],
            'points_spent' => $required,
            'remaining_points' => max(0.0, $available - $required),
            'message' => "ขอแลก {$item['title']} สำเร็จ! กรุณานำรหัส {$code} ไปแสดงต่อเจ้าหน้าที่ รพ.สต. เพื่อรับของรางวัล"
        ], JSON_UNESCAPED_UNICODE);
        exit();

    // 3. Admin Toggle Feature Flag
    } elseif ($action === 'admin_toggle_system') {
        if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_username'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $enabled = isset($_POST['enabled']) ? intval($_POST['enabled']) : 0;
        $stmt = $pdo->prepare("
            INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) 
            VALUES ('reward_system_enabled', ?, 'สถานะเปิด/ปิดระบบแลกของรางวัล อสม.')
            ON DUPLICATE KEY UPDATE `setting_value` = ?
        ");
        $stmt->execute([$enabled, $enabled]);

        echo json_encode([
            'status' => 'success',
            'system_enabled' => $enabled,
            'message' => $enabled ? 'เปิดใช้งานระบบแลกของรางวัลเรียบร้อยแล้ว' : 'ปิดระบบแลกของรางวัลเรียบร้อยแล้ว (เปลี่ยนเป็นโหมดพรีวิวสะสมแต้ม)'
        ], JSON_UNESCAPED_UNICODE);
        exit();

    // 4. Admin Save Item (Create / Edit + File Upload)
    } elseif ($action === 'admin_save_item') {
        if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_username'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $itemId = intval($_POST['item_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $points = max(1, intval($_POST['points_required'] ?? 10));
        $category = trim($_POST['category'] ?? 'equipment') ?: 'equipment';
        $emoji = trim($_POST['icon_emoji'] ?? '🎁') ?: '🎁';
        $stock = intval($_POST['stock_quantity'] ?? -1);
        $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $imageUrl = trim($_POST['image_url'] ?? '');

        if (empty($title)) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกชื่อของรางวัล'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Handle File Upload if provided
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowedTypes[$mimeType])) {
                echo json_encode(['status' => 'error', 'message' => 'รูปแบบไฟล์รูปภาพไม่ถูกต้อง (รองรับ JPG, PNG, WEBP)'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['status' => 'error', 'message' => 'ขนาดไฟล์รูปภาพเกินกำหนด (ไม่เกิน 5 MB)'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            $uploadDir = __DIR__ . '/../uploads/rewards/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = $allowedTypes[$mimeType];
            $newFileName = 'rwd_' . time() . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 8) . '.' . $ext;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $imageUrl = 'uploads/rewards/' . $newFileName;
            }
        }

        if ($itemId > 0) {
            // If editing and no new image provided, keep existing unless explicitly overwritten
            if (empty($imageUrl) && !isset($_POST['image_url'])) {
                $stmt = $pdo->prepare("
                    UPDATE `reward_items`
                    SET `title` = ?, `description` = ?, `points_required` = ?, `category` = ?, `icon_emoji` = ?, `stock_quantity` = ?, `is_active` = ?, `sort_order` = ?
                    WHERE `item_id` = ?
                ");
                $stmt->execute([$title, $desc, $points, $category, $emoji, $stock, $isActive, $sortOrder, $itemId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE `reward_items`
                    SET `title` = ?, `description` = ?, `points_required` = ?, `category` = ?, `icon_emoji` = ?, `image_url` = ?, `stock_quantity` = ?, `is_active` = ?, `sort_order` = ?
                    WHERE `item_id` = ?
                ");
                $stmt->execute([$title, $desc, $points, $category, $emoji, $imageUrl, $stock, $isActive, $sortOrder, $itemId]);
            }
            $msg = 'แก้ไขข้อมูลของรางวัลเรียบร้อยแล้ว';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO `reward_items` (`title`, `description`, `points_required`, `category`, `icon_emoji`, `image_url`, `stock_quantity`, `is_active`, `sort_order`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $desc, $points, $category, $emoji, $imageUrl, $stock, $isActive, $sortOrder]);
            $itemId = $pdo->lastInsertId();
            $msg = 'เพิ่มของรางวัลใหม่ในแคตตาล็อกเรียบร้อยแล้ว';
        }

        echo json_encode(['status' => 'success', 'item_id' => $itemId, 'image_url' => $imageUrl, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit();

    // 5. Admin Delete Item
    } elseif ($action === 'admin_delete_item') {
        if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_username'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $itemId = intval($_POST['item_id'] ?? 0);

        // Check if there are redemptions
        $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM `reward_redemptions` WHERE `item_id` = ?");
        $stmtChk->execute([$itemId]);
        $hasRedemptions = $stmtChk->fetchColumn() > 0;

        if ($hasRedemptions) {
            // Soft delete
            $pdo->prepare("UPDATE `reward_items` SET `is_active` = 0 WHERE `item_id` = ?")->execute([$itemId]);
            echo json_encode(['status' => 'success', 'message' => 'ปิดการใช้งานของรางวัลนี้แล้ว (มีประวัติการแลกแล้ว)'], JSON_UNESCAPED_UNICODE);
        } else {
            // Hard delete
            $pdo->prepare("DELETE FROM `reward_items` WHERE `item_id` = ?")->execute([$itemId]);
            echo json_encode(['status' => 'success', 'message' => 'ลบของรางวัลออกจากแคตตาล็อกแล้ว'], JSON_UNESCAPED_UNICODE);
        }
        exit();

    // 6. Admin Get Redemptions Queue
    } elseif ($action === 'admin_get_redemptions') {
        if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_username'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $statusFilter = $_GET['status'] ?? 'all';
        $where = [];
        $params = [];

        if (in_array($statusFilter, ['pending', 'fulfilled', 'cancelled'])) {
            $where[] = "r.status = ?";
            $params[] = $statusFilter;
        }

        if (!empty($_SESSION['admin_hoscode'])) {
            $where[] = "v.hoscode = ?";
            $params[] = $_SESSION['admin_hoscode'];
        }

        $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $pdo->prepare("
            SELECT r.*, i.title as item_title, i.category, i.icon_emoji,
                   v.vhv_name, v.vhv_phone, v.vhv_moo, v.hoscode
            FROM `reward_redemptions` r
            JOIN `reward_items` i ON r.item_id = i.item_id
            JOIN `vhv_users` v ON r.vhv_id = v.vhv_id
            $whereSql
            ORDER BY r.status = 'pending' DESC, r.created_at DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        $redemptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'redemptions' => $redemptions], JSON_UNESCAPED_UNICODE);
        exit();

    // 7. Admin Fulfill or Cancel Redemption
    } elseif ($action === 'admin_fulfill_redemption') {
        if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_username'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $redemptionId = intval($_POST['redemption_id'] ?? 0);
        $newStatus = in_array($_POST['status'] ?? '', ['fulfilled', 'cancelled', 'pending']) ? $_POST['status'] : 'fulfilled';
        $note = trim($_POST['note'] ?? '');
        $adminTitle = function_exists('get_admin_title') ? get_admin_title() : $_SESSION['admin_username'];

        $stmt = $pdo->prepare("
            UPDATE `reward_redemptions`
            SET `status` = ?, `fulfilled_by` = ?, `fulfilled_at` = IF(? = 'fulfilled', NOW(), NULL), `note` = ?
            WHERE `redemption_id` = ?
        ");
        $stmt->execute([$newStatus, $adminTitle, $newStatus, $note, $redemptionId]);

        // If cancelled, restore stock
        if ($newStatus === 'cancelled') {
            $stmtItem = $pdo->prepare("SELECT item_id FROM `reward_redemptions` WHERE `redemption_id` = ?");
            $stmtItem->execute([$redemptionId]);
            $itemId = $stmtItem->fetchColumn();
            if ($itemId) {
                $pdo->prepare("UPDATE `reward_items` SET `stock_quantity` = IF(`stock_quantity` >= 0, `stock_quantity` + 1, `stock_quantity`), `redeemed_count` = GREATEST(0, `redeemed_count` - 1) WHERE `item_id` = ?")->execute([$itemId]);
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => $newStatus === 'fulfilled' ? 'บันทึกการส่งมอบของรางวัลเรียบร้อยแล้ว' : 'ปรับสถานะคำขอเรียบร้อยแล้ว'
        ], JSON_UNESCAPED_UNICODE);
        exit();

    // 8. Admin Get Categories (with Item Counts)
    } elseif ($action === 'admin_get_categories') {
        if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_username'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $stmt = $pdo->query("
            SELECT c.*, COUNT(i.item_id) AS item_count
            FROM `reward_categories` c
            LEFT JOIN `reward_items` i ON c.category_code = i.category AND i.is_active = 1
            GROUP BY c.category_code
            ORDER BY c.sort_order ASC, c.category_name ASC
        ");
        $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'categories' => $cats], JSON_UNESCAPED_UNICODE);
        exit();

    // 9. Admin Save Category (Create / Edit)
    } elseif ($action === 'admin_save_category') {
        if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_username'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $code = trim($_POST['category_code'] ?? '');
        $name = trim($_POST['category_name'] ?? '');
        $emoji = trim($_POST['icon_emoji'] ?? '📦') ?: '📦';
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        $isEdit = !empty($_POST['is_edit']);

        if (empty($code) || empty($name)) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัสหมวดหมู่และชื่อหมวดหมู่'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Sanitize code (a-z, 0-9, _)
        $code = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($code));

        if ($isEdit) {
            $stmt = $pdo->prepare("
                UPDATE `reward_categories`
                SET `category_name` = ?, `icon_emoji` = ?, `sort_order` = ?, `is_active` = ?
                WHERE `category_code` = ?
            ");
            $stmt->execute([$name, $emoji, $sortOrder, $isActive, $code]);
            $msg = 'แก้ไขหมวดหมู่เรียบร้อยแล้ว';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO `reward_categories` (`category_code`, `category_name`, `icon_emoji`, `sort_order`, `is_active`)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`), `icon_emoji` = VALUES(`icon_emoji`), `sort_order` = VALUES(`sort_order`), `is_active` = VALUES(`is_active`)
            ");
            $stmt->execute([$code, $name, $emoji, $sortOrder, $isActive]);
            $msg = 'เพิ่มหมวดหมู่ของรางวัลใหม่เรียบร้อยแล้ว';
        }

        echo json_encode(['status' => 'success', 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit();

    // 10. Admin Delete Category
    } elseif ($action === 'admin_delete_category') {
        if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_username'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $code = trim($_POST['category_code'] ?? '');

        // Check if items use this category
        $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM `reward_items` WHERE `category` = ?");
        $stmtChk->execute([$code]);
        $count = $stmtChk->fetchColumn();

        if ($count > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => "ไม่สามารถลบหมวดหมู่นี้ได้เนื่องจากมีของรางวัลในหมวดนี้อยู่ {$count} รายการ กรุณาย้ายหรือลบของรางวัลก่อน"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $pdo->prepare("DELETE FROM `reward_categories` WHERE `category_code` = ?")->execute([$code]);
        echo json_encode(['status' => 'success', 'message' => 'ลบหมวดหมู่เรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);
        exit();
    }

} catch (\Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
}
