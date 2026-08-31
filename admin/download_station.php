<?php
require_once __DIR__ . '/../config/session.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit;
}

// This URL is now a download endpoint only. The confirmation UI is displayed
// as a modal in critical_referrals.php and emergency_receiver.php.
if (($_GET['download'] ?? '') !== '1') {
    header('Location: critical_referrals.php');
    exit;
}

$filePath = __DIR__ . DIRECTORY_SEPARATOR . 'NCDs_RedAlert_Station_Setup.exe';
if (!is_file($filePath)) {
    http_response_code(404);
    exit('ไม่พบไฟล์ติดตั้ง กรุณาติดต่อผู้ดูแลระบบ');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="NCDs_RedAlert_Station_Setup.exe"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-transform, must-revalidate');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
