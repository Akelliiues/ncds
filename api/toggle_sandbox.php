<?php
// api/toggle_sandbox.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// 1. ตรวจสอบล็อกอินแอดมิน
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode([
        'status' => 'error',
        'message' => 'เข้าถึงถูกปฏิเสธ: กรุณาเข้าสู่ระบบด้วยสิทธิ์ผู้ดูแลระบบ'
    ]);
    exit();
}

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_username = $_SESSION['admin_username'] ?? '';

// 3. รับค่าและบันทึก
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = isset($_POST['sandbox_mode']) ? trim($_POST['sandbox_mode']) : '';
    
    if ($mode !== '0' && $mode !== '1') {
        echo json_encode([
            'status' => 'error',
            'message' => 'ค่าตัวแปรไม่ถูกต้อง'
        ]);
        exit();
    }
    
    $target_hoscode = isset($_POST['target_hoscode']) ? trim($_POST['target_hoscode']) : '';
    if ($admin_hoscode !== null) {
        // Area Admin can only toggle their own hospital
        $target_hoscode = $admin_hoscode;
    }
    
    $setting_key = ($target_hoscode !== '') ? 'sandbox_mode_' . $target_hoscode : 'sandbox_mode';
    
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description)
            VALUES (?, ?, 'โหมดทดสอบจำลองระบบ')
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $stmt->execute([$setting_key, $mode, $mode]);
        
        // Switching modes changes only which dataset receives new work. Existing
        // assignments, screening results, DPAC records and rewards are historical
        // records and must never be deleted or reset by this setting.

        if (function_exists('logUserActivity')) {
            $modeTitle = ($mode === '1') ? 'เปิดโหมดทดสอบ (Sandbox)' : 'ปิดโหมดทดสอบ (Production Live)';
            logUserActivity('SETTINGS', $modeTitle, [
                'target_hoscode' => $target_hoscode ?: 'ALL',
                'sandbox_mode' => (int)$mode
            ]);
        }

        $pdo->commit();
        
        $modeText = ($mode === '1') ? 'เปิดโหมดทดสอบ (Sandbox Mode)' : 'ปิดโหมดทดสอบและกลับสู่โหมดใช้งานจริง โดยเก็บประวัติเดิมไว้';
        
        echo json_encode([
            'status' => 'success',
            'sandbox_mode' => (int)$mode,
            'target_hoscode' => $target_hoscode,
            'message' => 'ปรับปรุงโหมดระบบสำเร็จ: ' . $modeText
        ]);
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()
        ]);
        exit();
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid Request Method'
    ]);
    exit();
}
