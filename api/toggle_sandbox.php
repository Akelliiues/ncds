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
        
        // If toggling OFF sandbox mode (mode = 0), perform database restore point cleanup
        if ($mode === '0') {
            if ($target_hoscode !== '') {
                // 1. Delete sandboxed records (is_sandbox = 1) for this hoscode
                $stmtDelScreen = $pdo->prepare("
                    DELETE FROM screening_results 
                    WHERE is_sandbox = 1 
                      AND assignment_id IN (
                          SELECT a.assignment_id 
                          FROM task_assignments a 
                          JOIN target_population p ON a.target_cid = p.cid 
                          WHERE p.hoscode = ?
                      )
                ");
                $stmtDelScreen->execute([$target_hoscode]);

                $stmtDelTasks = $pdo->prepare("
                    DELETE FROM task_assignments 
                    WHERE is_sandbox = 1 
                      AND target_cid IN (
                          SELECT cid FROM target_population WHERE hoscode = ?
                      )
                ");
                $stmtDelTasks->execute([$target_hoscode]);

                $stmtDelRewards = $pdo->prepare("
                    DELETE FROM vhv_rewards 
                    WHERE is_sandbox = 1 
                      AND vhv_id IN (
                          SELECT vhv_id 
                          FROM vhv_users 
                          WHERE hoscode = ?
                      )
                ");
                $stmtDelRewards->execute([$target_hoscode]);

                $stmtDelDpac = $pdo->prepare("
                    DELETE FROM dpac_followups 
                    WHERE is_sandbox = 1 
                      AND enrollment_id IN (
                          SELECT e.enrollment_id 
                          FROM dpac_enrollments e 
                          JOIN target_population p ON e.cid = p.cid 
                          WHERE p.hoscode = ?
                      )
                ");
                $stmtDelDpac->execute([$target_hoscode]);

                // 2. Restore production task assignments touched in sandbox for this hoscode
                $stmtUpdTasks = $pdo->prepare("
                    UPDATE task_assignments 
                    SET assignment_status = 'pending', 
                        is_sandbox_completed = 0 
                    WHERE is_sandbox_completed = 1 
                      AND target_cid IN (
                          SELECT cid FROM target_population WHERE hoscode = ?
                      )
                ");
                $stmtUpdTasks->execute([$target_hoscode]);

                // 3. Restore production DPAC followups touched in sandbox for this hoscode
                $stmtUpdDpac = $pdo->prepare("
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
                      AND enrollment_id IN (
                          SELECT e.enrollment_id 
                          FROM dpac_enrollments e 
                          JOIN target_population p ON e.cid = p.cid 
                          WHERE p.hoscode = ?
                      )
                ");
                $stmtUpdDpac->execute([$target_hoscode]);
            } else {
                // Global Switch Off -> cleanup all sandbox records
                $pdo->exec("DELETE FROM vhv_rewards WHERE is_sandbox = 1");
                $pdo->exec("DELETE FROM screening_results WHERE is_sandbox = 1");
                $pdo->exec("DELETE FROM task_assignments WHERE is_sandbox = 1");
                $pdo->exec("DELETE FROM dpac_followups WHERE is_sandbox = 1");

                $pdo->exec("
                    UPDATE task_assignments 
                    SET assignment_status = 'pending', 
                        is_sandbox_completed = 0 
                    WHERE is_sandbox_completed = 1
                ");

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
        }

        $pdo->commit();
        
        $modeText = ($mode === '1') ? 'เปิดโหมดทดสอบ (Sandbox Mode)' : 'ปิดโหมดทดสอบ (Production Mode) และรีเซ็ตข้อมูลจำลองการทดสอบเรียบร้อยแล้ว';
        
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
