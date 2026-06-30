<?php
// vhv/diagnose_league.php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: text/html; charset=utf-8');
echo "<h1>🔍 Diagnostic Report for Hospital League</h1>";

echo "<h3>1. ตารางหน่วยบริการ (health_units)</h3>";
try {
    $count = $pdo->query("SELECT COUNT(*) FROM health_units")->fetchColumn();
    echo "จำนวนแถวทั้งหมด: <b>" . $count . "</b> แห่ง<br>";
    $units = $pdo->query("SELECT * FROM health_units")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($units, true) . "</pre>";
} catch (Exception $e) {
    echo "<span style='color:red;'>ข้อผิดพลาดใน health_units: " . $e->getMessage() . "</span><br>";
}

echo "<h3>2. ตารางกลุ่มเป้าหมาย (target_population)</h3>";
try {
    $count = $pdo->query("SELECT COUNT(*) FROM target_population")->fetchColumn();
    echo "จำนวนเป้าหมายทั้งหมดในระบบ: <b>" . $count . "</b> ราย<br>";
    
    $group = $pdo->query("SELECT hoscode, COUNT(*) as count FROM target_population GROUP BY hoscode")->fetchAll(PDO::FETCH_ASSOC);
    echo "การกระจายตัวของเป้าหมายแยกตาม รพ.สต.:";
    echo "<pre>" . print_r($group, true) . "</pre>";
} catch (Exception $e) {
    echo "<span style='color:red;'>ข้อผิดพลาดใน target_population: " . $e->getMessage() . "</span><br>";
}

echo "<h3>3. ทดสอบรันคิวรีลีก รพ.สต. (League Query Test)</h3>";
try {
    $hosQuery = "
        SELECT 
            u.hoscode,
            COUNT(DISTINCT p.cid) as total_targets,
            COUNT(DISTINCT CASE WHEN a.assignment_status = 'completed' THEN p.cid END) as completed_targets
        FROM health_units u
        LEFT JOIN target_population p ON u.hoscode = p.hoscode
        LEFT JOIN task_assignments a ON p.cid = a.target_cid AND a.budget_year = 2026
        GROUP BY u.hoscode
        HAVING COUNT(DISTINCT p.cid) > 0
        ORDER BY (COUNT(DISTINCT CASE WHEN a.assignment_status = 'completed' THEN p.cid END) / COUNT(DISTINCT p.cid)) DESC, u.hoscode ASC
    ";
    $hospitalStats = $pdo->query($hosQuery)->fetchAll(PDO::FETCH_ASSOC);
    echo "ผลลัพธ์ของคิวรี (Query Results):";
    echo "<pre>" . print_r($hospitalStats, true) . "</pre>";
} catch (Exception $e) {
    echo "<span style='color:red;'>คิวรีเกิดข้อผิดพลาด: " . $e->getMessage() . "</span><br>";
}
?>
