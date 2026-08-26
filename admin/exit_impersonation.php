<?php
// admin/exit_impersonation.php - สิ้นสุดโหมดจำลองมุมมอง อสม. และกลับสู่ระบบเจ้าหน้าที่หลังบ้าน
require_once __DIR__ . '/../config/session.php';

if (!empty($_SESSION['impersonator_admin'])) {
    $admin = $_SESSION['impersonator_admin'];
    
    // คืนค่า Session ของผู้ดูแลระบบ/เจ้าหน้าที่
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $admin['admin_username'] ?? 'admin';
    $_SESSION['admin_hoscode'] = $admin['admin_hoscode'] ?? null;
    $_SESSION['admin_role'] = $admin['admin_role'] ?? 'admin';
    
    if (!empty($admin['is_executive'])) {
        $_SESSION['is_executive'] = true;
    }
    if (!empty($admin['is_visitor'])) {
        $_SESSION['is_visitor'] = true;
    }
    
    $returnUrl = $admin['return_url'] ?? 'index.php';
    
    // ล้างค่า Session ของ อสม. และแฟล็กจำลอง
    unset($_SESSION['vhv_id']);
    unset($_SESSION['vhv_name']);
    unset($_SESSION['vhv_moo']);
    unset($_SESSION['vhid_code']);
    unset($_SESSION['hoscode']);
    unset($_SESSION['is_leader']);
    unset($_SESSION['is_hl_coach']);
    unset($_SESSION['is_admin_impersonating']);
    unset($_SESSION['impersonator_admin']);
    
    header("Location: " . $returnUrl);
    exit();
} else {
    // หากไม่ได้อยู่ในโหมดจำลอง ให้ส่งกลับหน้าแรกของระบบแอดมิน
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        header("Location: index.php");
    } else {
        header("Location: ../index.php");
    }
    exit();
}
