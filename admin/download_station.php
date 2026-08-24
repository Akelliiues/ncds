<?php
// admin/download_station.php - Safe Downloader for NCDs Red Alert Desktop Station
require_once __DIR__ . '/../config/session.php';

$format = $_GET['format'] ?? 'zip';
$baseDir = realpath(__DIR__ . '/../tools/red_alert_station');

if ($format === 'exe') {
    $filePath = $baseDir . DIRECTORY_SEPARATOR . 'NCDs_RedAlert_Station.exe';
    $fileName = 'NCDs_RedAlert_Station.exe';
    $contentType = 'application/octet-stream';
} else {
    // Default to safe .zip package
    $filePath = $baseDir . DIRECTORY_SEPARATOR . 'NCDs_RedAlert_Station.zip';
    $fileName = 'NCDs_RedAlert_Station.zip';
    $contentType = 'application/zip';
    
    // Auto re-pack if zip doesn't exist
    if (!file_exists($filePath) && file_exists($baseDir . DIRECTORY_SEPARATOR . 'NCDs_RedAlert_Station.exe')) {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $zip->addFile($baseDir . DIRECTORY_SEPARATOR . 'NCDs_RedAlert_Station.exe', 'NCDs_RedAlert_Station.exe');
                if (file_exists($baseDir . DIRECTORY_SEPARATOR . 'START_RED_ALERT_STATION.bat')) {
                    $zip->addFile($baseDir . DIRECTORY_SEPARATOR . 'START_RED_ALERT_STATION.bat', 'START_RED_ALERT_STATION.bat');
                }
                if (file_exists($baseDir . DIRECTORY_SEPARATOR . 'HOW_TO_RUN.txt')) {
                    $zip->addFile($baseDir . DIRECTORY_SEPARATOR . 'HOW_TO_RUN.txt', 'HOW_TO_RUN.txt');
                }
                $zip->close();
            }
        }
    }
}

if (!file_exists($filePath)) {
    http_response_code(404);
    echo "<!DOCTYPE html><html lang='th'><head><meta charset='UTF-8'><title>ไม่พบไฟล์</title></head><body style='font-family:sans-serif; text-align:center; padding:50px;'><h2>⚠️ ไม่พบไฟล์โปรแกรมที่ต้องการดาวน์โหลด</h2><p>กรุณาติดต่อผู้ดูแลระบบ</p><a href='emergency_receiver.php'>กลับหน้าหลัก</a></body></html>";
    exit;
}

// Clean output buffer to avoid corrupted binary downloads
if (ob_get_level()) {
    ob_end_clean();
}

// Send standard safe download headers
header('Content-Description: File Transfer');
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
