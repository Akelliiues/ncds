<?php
// Scratch script to test api/update_coordinates.php
// Since session is required, we can mock session or call functions directly if we include it, or we can use curl by temporarily bypassing session or mocking it in the test script.
// To test it realistically, let's create a mockup or mock session variables before requiring the file.

require_once __DIR__ . '/../config/session.php';
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_hoscode'] = '10688'; // mock sub-admin

// We will capture output using ob_start
$_SERVER['REQUEST_METHOD'] = 'POST';

// Mock php://input contents by overriding it or simulating the variables.
// In PHP, we can't easily override php://input directly, but we can mock the values or wrap the execution.
// Let's create a temporary test target with CID '9999999999999' to test the SQL queries.
require_once __DIR__ . '/../config/db.php';

// Clean up old test records if any
$pdo->query("DELETE FROM target_population WHERE cid = '9999999999999'");

// Insert test record
$pdo->prepare("
    INSERT INTO target_population (cid, first_name, last_name, house_no, moo, sub_district_code, hoscode, need_screen_dm, need_screen_ht)
    VALUES ('9999999999999', 'ทดสอบ', 'ระบบพิกัด', '123', '2', '341801', '10688', 1, 1)
")->execute();

// We will now test the controller logic directly by simulating the inputs.
// Since we can't mock php://input easily without wrapping, let's simulate the controller logic itself in this test script.
$cid = '9999999999999';
$latitude = 15.4350;
$longitude = 104.9950;

echo "=== Test 1: Check validation & update ===\n";

try {
    // 1. Check if target exists
    $checkStmt = $pdo->prepare("SELECT cid, first_name, last_name, house_no, moo FROM target_population WHERE cid = ?");
    $checkStmt->execute([$cid]);
    $target = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        throw new Exception("Target not found!");
    }
    echo "Target found: " . $target['first_name'] . "\n";
    
    // 2. Validate bounds
    if ($latitude < 5.0 || $latitude > 21.0 || $longitude < 97.0 || $longitude > 106.0) {
        throw new Exception("Out of bounds!");
    }
    echo "Bounds check passed.\n";
    
    // 3. Check admin hoscode perm
    $admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
    if ($admin_hoscode) {
        $hoscodes = [$admin_hoscode];
        if ($admin_hoscode === '10957') {
            $hoscodes[] = '10688';
        }
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        $permStmt = $pdo->prepare("SELECT cid FROM target_population WHERE cid = ? AND hoscode IN ($inPlaceholders)");
        $permStmt->execute(array_merge([$cid], $hoscodes));
        if (!$permStmt->fetch()) {
            throw new Exception("No permission!");
        }
    }
    echo "Permission check passed.\n";
    
    // 4. Update
    $updateStmt = $pdo->prepare("UPDATE target_population SET latitude = ?, longitude = ?, updated_at = NOW() WHERE cid = ?");
    $updateStmt->execute([$latitude, $longitude, $cid]);
    echo "Update executed successfully.\n";
    
    // Verify
    $verifyStmt = $pdo->prepare("SELECT latitude, longitude FROM target_population WHERE cid = ?");
    $verifyStmt->execute([$cid]);
    $updated = $verifyStmt->fetch();
    echo "Updated Coordinates: Lat=" . $updated['latitude'] . ", Lng=" . $updated['longitude'] . "\n";
    if (abs(floatval($updated['latitude']) - $latitude) < 0.0001 && abs(floatval($updated['longitude']) - $longitude) < 0.0001) {
        echo "TEST SUCCESSFUL!\n";
    } else {
        echo "TEST FAILED: coordinates do not match!\n";
    }
} catch (Exception $e) {
    echo "TEST ERROR: " . $e->getMessage() . "\n";
}

// Clean up
$pdo->query("DELETE FROM target_population WHERE cid = '9999999999999'");
echo "Cleaned up test record.\n";
