<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Clear all existing coordinates
    $pdo->exec("UPDATE target_population SET latitude = NULL, longitude = NULL");
    
    // 2. Update with real coordinates from jhcis_homes where available
    $updated = $pdo->exec("
        UPDATE target_population t
        JOIN jhcis_homes h ON LPAD(t.hoscode, 5, '0') = LPAD(h.hoscode, 5, '0') AND t.hid = h.hid
        SET t.latitude = h.latitude, t.longitude = h.longitude
        WHERE h.latitude IS NOT NULL AND h.longitude IS NOT NULL
    ");
    
    // Also try matching just by house_no and vhid_code if hid is empty or doesn't match
    $updated_fallback = $pdo->exec("
        UPDATE target_population t
        JOIN jhcis_homes h ON LPAD(t.hoscode, 5, '0') = LPAD(h.hoscode, 5, '0') 
                           AND t.vhid_code = h.vhid_code 
                           AND t.house_no = h.house_no
        SET t.latitude = h.latitude, t.longitude = h.longitude
        WHERE t.latitude IS NULL 
          AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL
    ");

    $pdo->commit();
    echo "<h1>ดำเนินการลบพิกัดจำลองสำเร็จ</h1>";
    echo "<p>ล้างพิกัดจำลองของเป้าหมายทั้งหมดแล้ว</p>";
    echo "<p>ดึงพิกัดจริงจากข้อมูลแฟ้ม HOME (อ้างอิงตามเลขที่บ้าน) กลับคืนมาได้: " . ($updated + $updated_fallback) . " รายการ</p>";
    echo "<a href='index.php'>กลับไปยังหน้าแดชบอร์ด</a>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
