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

// 2. ตรวจสอบสิทธิ์ระดับแอดมินสูงสุด (Super Admin)
if ($admin_hoscode !== null || $admin_username === 'adminsso') {
    echo json_encode([
        'status' => 'error',
        'message' => 'เข้าถึงถูกปฏิเสธ: ฟังก์ชันนี้สงวนไว้สำหรับสิทธิ์การดูแลระดับอำเภอ (Super Admin) เท่านั้น'
    ]);
    exit();
}

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
    
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description)
            VALUES ('sandbox_mode', ?, 'โหมดทดสอบจำลองระบบ (0 = ปิด/ใช้งานจริง, 1 = เปิด/จำลอง)')
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $stmt->execute([$mode, $mode]);
        
        // If toggling OFF sandbox mode (mode = 0), perform database restore point cleanup
        if ($mode === '0') {
            // 1. Delete sandboxed records (is_sandbox = 1)
            $pdo->exec("DELETE FROM vhv_rewards WHERE is_sandbox = 1");
            $pdo->exec("DELETE FROM screening_results WHERE is_sandbox = 1");
            $pdo->exec("DELETE FROM task_assignments WHERE is_sandbox = 1");
            $pdo->exec("DELETE FROM dpac_followups WHERE is_sandbox = 1");

            // 2. Restore production task assignments touched in sandbox
            $pdo->exec("
                UPDATE task_assignments 
                SET assignment_status = 'pending', 
                    is_sandbox_completed = 0 
                WHERE is_sandbox_completed = 1
            ");

            // 3. Restore production DPAC followups touched in sandbox
            $pdo->exec("
                UPDATE dpac_followups 
                SET status = 'pending', 
                    completed_at = NULL, 
                    weight = NULL, 
                    height = NULL, 
                    waist = NULL, 
                    fbs = NULL, 
                    bp_sys = NULL, 
                    bp_dia = NULL, 
                    health_risk_level = NULL, 
                    advice_given = NULL, 
                    skip_count = 0, 
                    skipped_reason = NULL, 
                    is_sandbox_completed = 0 
                WHERE is_sandbox_completed = 1
            ");
        }

        $pdo->commit();
        
        $modeText = ($mode === '1') ? 'เปิดโหมดทดสอบ (Sandbox Mode)' : 'ปิดโหมดทดสอบ (Production Mode) และรีเซ็ตข้อมูลจำลองการทดสอบเรียบร้อยแล้ว';
        
        echo json_encode([
            'status' => 'success',
            'sandbox_mode' => (int)$mode,
            'message' => 'ปรับปรุงโหมดระบบสำเร็จ: ' . $modeText
        ]);
        exit();
        
    } catch (Exception $e) {
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
