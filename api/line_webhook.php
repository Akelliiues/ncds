<?php
// api/line_webhook.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/line_config.php';

header('Content-Type: application/json');

// 1. Get raw request body and signature header
$rawBody = file_get_contents('php://input');
$headers = getallheaders();
$signature = $headers['X-Line-Signature'] ?? $headers['x-line-signature'] ?? '';

if (empty($rawBody)) {
    echo json_encode(['status' => 'error', 'message' => 'Empty request body']);
    exit();
}

// 2. Signature Validation (PDPA & LINE Security Guard)
if (LINE_CHANNEL_SECRET !== 'YOUR_CHANNEL_SECRET') {
    $hash = hash_hmac('sha256', $rawBody, LINE_CHANNEL_SECRET, true);
    $computedSignature = base64_encode($hash);
    if (!hash_equals($signature, $computedSignature)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
        exit();
    }
}

// Parse request events
$eventData = json_decode($rawBody, true);
$events = $eventData['events'] ?? [];

// Reply Message helper function
function replyTextMessage($replyToken, $text) {
    if (LINE_CHANNEL_ACCESS_TOKEN === 'YOUR_CHANNEL_ACCESS_TOKEN') {
        return false;
    }

    $url = 'https://api.line.me/v2/bot/message/reply';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
    ];

    $body = [
        'replyToken' => $replyToken,
        'messages' => [
            [
                'type' => 'text',
                'text' => $text
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = curl_exec($ch);
    curl_close($ch);

    return $res;
}

foreach ($events as $event) {
    $replyToken = $event['replyToken'] ?? '';
    $userId = $event['source']['userId'] ?? '';
    $type = $event['type'] ?? '';

    if ($type === 'message' && ($event['message']['type'] ?? '') === 'text') {
        $userText = trim($event['message']['text']);

        // Check for linking command pattern: "ผูกบ้าน [HID]" or "reg [HID]" or "link [HID]"
        if (preg_match('/^(ผูกบ้าน|reg|link)\s+(\d{5,15})$/i', $userText, $matches)) {
            $hid = $matches[2];

            try {
                // Verify if house HID exists in database
                $checkStmt = $pdo->prepare("SELECT house_no, moo, first_name, last_name FROM target_population WHERE hid = ? LIMIT 1");
                $checkStmt->execute([$hid]);
                $house = $checkStmt->fetch();

                if ($house) {
                    // Link line user id to house HID
                    $linkStmt = $pdo->prepare("
                        INSERT INTO line_house_mappings (line_user_id, hid)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP
                    ");
                    $linkStmt->execute([$userId, $hid]);

                    $confirmMsg = "✅ ผูกบัญชี LINE กับบ้านเลขที่ " . $house['house_no'] . " หมู่ที่ " . $house['moo'] . " สำเร็จแล้ว!\n"
                                . "ระบบจะแจ้งเตือนและส่งรายงานการ์ดความห่วงใยเมื่อ อสม. เข้าตรวจคัดกรองสมาชิกในบ้านของท่านอัตโนมัติ";
                    replyTextMessage($replyToken, $confirmMsg);
                } else {
                    replyTextMessage($replyToken, "❌ ไม่พบรหัสบ้าน (HID): $hid ในฐานข้อมูลระบบกรองประชากรเป้าหมายของอำเภอตาลสุม กรุณาตรวจสอบรหัสบ้านจากการ์ด QR Code หรือติดต่อเจ้าหน้าที่");
                }
            } catch (\Exception $e) {
                replyTextMessage($replyToken, "⚠️ เกิดข้อผิดพลาดในระบบฐานข้อมูลหลังบ้าน กรุณาลองใหม่อีกครั้งภายหลัง");
            }
        } else {
            // General query fallback response
            $helpMsg = "ยินดีต้อนรับสู่ระบบ NCDs อำเภอตาลสุม 🇹🇭\n\n"
                     . "หากต้องการรับผลคัดกรองและความห่วงใยของลูกหลาน/ครอบครัว กรุณาผูกบัญชีโดยส่งข้อความ:\n"
                     . "👉 'ผูกบ้าน [รหัสบ้าน HID 15 หลัก]'\n\n"
                     . "ตัวอย่างเช่น: ผูกบ้าน 341801010000001\n"
                     . "(สามารถดูรหัส HID ได้จากมุมซ้ายล่างของการ์ด QR Code หน้าบ้านของท่าน)";
            replyTextMessage($replyToken, $helpMsg);
        }
    }
}

echo json_encode(['status' => 'ok']);
exit();
