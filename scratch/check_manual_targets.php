<?php
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->query("SELECT cid, first_name, last_name, birth, TIMESTAMPDIFF(YEAR, birth, CURDATE()) as age, house_no, moo, sub_district_code, hoscode, need_screen_dm, need_screen_ht, is_manual FROM target_population WHERE is_manual = 1 ORDER BY updated_at DESC LIMIT 20");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== MANUAL TARGETS IN DATABASE (LATEST 20) ===\n";
    if (empty($rows)) {
        echo "No manually added targets found in the database.\n";
    } else {
        foreach ($rows as $row) {
            echo sprintf(
                "CID: %s | Name: %s %s | Birth: %s (Age: %d) | Moo: %s | Tambon: %s | Hoscode: %s | DM: %d | HT: %d\n",
                $row['cid'],
                $row['first_name'],
                $row['last_name'],
                $row['birth'],
                $row['age'],
                $row['moo'],
                $row['sub_district_code'],
                $row['hoscode'],
                $row['need_screen_dm'],
                $row['need_screen_ht']
            );
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
