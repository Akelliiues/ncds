<?php
// logout.php
// หากอยู่ในโหมดจำลองมุมมอง อสม. ให้สลับกลับสู่ระบบแอดมินแทนการออกจากระบบ
if (!empty($_SESSION['is_admin_impersonating']) || !empty($_SESSION['impersonator_admin'])) {
    header("Location: admin/exit_impersonation.php");
    exit();
}

// ล้างค่าตัวแปร Session ทั้งหมด
$_SESSION = array();

// ทำลายคุกกี้ Session ของเบราว์เซอร์
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย Session
session_destroy();

// ส่งผู้ใช้กลับไปยังหน้าแรก (Login Portal)
header("Location: index.php");
exit();
?>
