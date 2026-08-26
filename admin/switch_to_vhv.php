<?php
// admin/switch_to_vhv.php - สลับบทบาทเข้าสู่มุมมอง อสม. ในพื้นที่สำหรับเจ้าหน้าที่/แอดมิน
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

// ตรวจสอบสิทธิ์ว่าต้องเป็นผู้ดูแลระบบ/เจ้าหน้าที่
$isAdminLoggedIn = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) 
                || !empty($_SESSION['impersonator_admin']['admin_logged_in']);

if (!$isAdminLoggedIn) {
    header("Location: ../index.php");
    exit();
}

$vhvId = trim($_GET['vhv_id'] ?? $_POST['vhv_id'] ?? '');
if (empty($vhvId)) {
    header("Location: index.php?error=no_vhv_selected");
    exit();
}

// ตรวจสอบข้อมูล อสม. ในฐานข้อมูล
try {
    $adminHoscode = $_SESSION['admin_hoscode'] ?? $_SESSION['impersonator_admin']['admin_hoscode'] ?? null;
    
    $sql = "SELECT * FROM vhv_users WHERE vhv_id = ?";
    $params = [$vhvId];
    
    // ถ้าไม่ใช่ Super Admin ให้จำกัดการสลับดูเฉพาะ อสม. ในสังกัด รพ.สต. ตนเอง
    if (!empty($adminHoscode)) {
        $sql .= " AND (hoscode = ? OR CAST(hoscode AS UNSIGNED) = CAST(? AS UNSIGNED))";
        $params[] = $adminHoscode;
        $params[] = $adminHoscode;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vhv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vhv) {
        // กรณีไม่พบ ให้ลองหาโดยไม่จำกัด hoscode หากเป็นระดับอำเภอ
        $stmt2 = $pdo->prepare("SELECT * FROM vhv_users WHERE vhv_id = ?");
        $stmt2->execute([$vhvId]);
        $vhv = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if (!$vhv) {
            header("Location: index.php?error=vhv_not_found");
            exit();
        }
    }

    // บันทึกสถานะ Session ของแอดมินตัวจริงเก็บไว้ (เก็บเฉพาะครั้งแรกที่สลับ)
    if (empty($_SESSION['impersonator_admin'])) {
        $returnUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        if (strpos($returnUrl, 'switch_to_vhv.php') !== false) {
            $returnUrl = 'index.php';
        }
        
        $_SESSION['impersonator_admin'] = [
            'admin_logged_in' => true,
            'admin_username' => $_SESSION['admin_username'] ?? 'admin',
            'admin_hoscode' => $_SESSION['admin_hoscode'] ?? null,
            'admin_role' => $_SESSION['admin_role'] ?? 'admin',
            'is_executive' => $_SESSION['is_executive'] ?? false,
            'is_visitor' => $_SESSION['is_visitor'] ?? false,
            'return_url' => $returnUrl
        ];
    }

    // ตั้งค่า Session เพื่อจำลองบทบาท อสม.
    $_SESSION['vhv_id'] = $vhv['vhv_id'];
    $_SESSION['vhv_name'] = $vhv['vhv_name'];
    $_SESSION['vhv_moo'] = $vhv['vhv_moo'];
    $_SESSION['vhid_code'] = $vhv['vhid_code'];
    $_SESSION['hoscode'] = $vhv['hoscode'];
    $_SESSION['is_leader'] = intval($vhv['is_leader']);
    $_SESSION['is_hl_coach'] = (bool)($vhv['is_hl_coach'] ?? false);
    $_SESSION['is_admin_impersonating'] = true;

    // เคลียร์โหมด Demo หากมี
    unset($_SESSION['is_demo_mode']);
    unset($_SESSION['demo_role']);

    // นำทางไปยังหน้าหลักของ อสม.
    header("Location: ../vhv/index.php");
    exit();

} catch (\Throwable $e) {
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit();
}
