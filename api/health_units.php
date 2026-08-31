<?php
// Public read-only directory used by the desktop station.
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->query("SELECT hoscode, hosname FROM health_units WHERE hoscode <> '' AND hosname <> '' ORDER BY hoscode ASC");
    echo json_encode([
        'status' => 'success',
        'source' => 'health_units',
        'units' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถอ่านรายชื่อหน่วยบริการได้'], JSON_UNESCAPED_UNICODE);
}
