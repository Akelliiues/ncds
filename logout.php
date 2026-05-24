<?php
// logout.php
session_start();

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
