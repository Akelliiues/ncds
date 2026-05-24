<?php
// Quick test of the SQL queries used in index.php
require __DIR__ . '/../config/db.php';

echo "=== Testing unified map data query ===\n";
try {
    $r = $pdo->query("
        SELECT p.cid, p.latitude, p.longitude, p.house_no, p.moo, p.sub_district_code, p.hoscode,
               p.first_name, p.last_name, p.health_status_origin,
               s.sys_bp1, s.dia_bp1, s.dtx_value, s.cv_risk_score, s.bmi
        FROM target_population p
        LEFT JOIN task_assignments a ON a.target_cid = p.cid AND a.assignment_status = 'completed'
        LEFT JOIN screening_results s ON s.assignment_id = a.assignment_id
        WHERE p.latitude IS NOT NULL 
          AND p.longitude IS NOT NULL
    ");
    $results = $r->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($results) . " records with coordinates\n";
    foreach ($results as $row) {
        $risk = 'normal';
        if ($row['sys_bp1'] !== null) {
            $sbp = intval($row['sys_bp1']);
            $dbp = intval($row['dia_bp1']);
            $dtx = floatval($row['dtx_value'] ?? 0);
            $cv = floatval($row['cv_risk_score'] ?? 0);
            if ($sbp >= 140 || $dbp >= 90 || $dtx >= 126 || $cv >= 10) $risk = 'high';
            elseif (($sbp >= 120 && $sbp < 140) || ($dbp >= 80 && $dbp < 90) || ($dtx >= 100 && $dtx < 126)) $risk = 'moderate';
        }
        echo "  " . $row['cid'] . " | " . $row['first_name'] . " " . $row['last_name'] . " | lat=" . $row['latitude'] . " | risk=" . $risk . " | hoscode=" . $row['hoscode'] . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Testing unique hoscodes query ===\n";
try {
    $r2 = $pdo->query("SELECT DISTINCT hoscode FROM target_population WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY hoscode");
    $hoscodes = $r2->fetchAll(PDO::FETCH_COLUMN);
    echo "Hoscodes with coords: " . implode(', ', $hoscodes) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Testing editable targets query ===\n";
try {
    $r3 = $pdo->query("SELECT COUNT(*) FROM target_population");
    echo "Total targets (for edit dropdown): " . $r3->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Testing VHV list query (fixed) ===\n";
try {
    $r4 = $pdo->query("
        SELECT v.vhv_name, v.hoscode, v.vhv_moo, v.approved, v.vhid_code,
               (SELECT COUNT(*) FROM task_assignments a WHERE a.vhv_id = v.vhv_id) as assigned_targets,
               (SELECT COUNT(*) FROM task_assignments a WHERE a.vhv_id = v.vhv_id AND a.assignment_status = 'completed') as completed_screenings
        FROM vhv_users v
        ORDER BY v.hoscode, v.vhv_moo, v.vhv_name
    ");
    $vhvs = $r4->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($vhvs) . " VHV users\n";
    foreach (array_slice($vhvs, 0, 5) as $v) {
        $vhid_sub = substr($v['vhid_code'] ?? '', 0, 6);
        echo "  " . $v['vhv_name'] . " | hoscode=" . $v['hoscode'] . " | moo=" . $v['vhv_moo'] . " | vhid_code=" . ($v['vhid_code'] ?? 'N/A') . " | sub=" . $vhid_sub . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\nAll queries passed!\n";
