<?php
// scratch/simulate_post.php
define('MOCK_UPLOAD', true);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start mock session
require_once __DIR__ . '/../config/session.php';
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_hoscode'] = null; // simulate main admin

// Create a dummy CSV file
$csvContent = "hcode,hosname,pid,cid,name,lname,sex,birth,risk,result\n";
$csvContent .= "10957,Tal Sum Hospital,111,1234567890123,John,Doe,1,1990-01-01,1,Normal\n";

$dummyFile = __DIR__ . '/dummy_upload.csv';
file_put_contents($dummyFile, $csvContent);

// Setup $_POST and $_FILES to mock the upload
$_POST['action_upload'] = '1';
$_POST['import_type'] = 'dm';
$_POST['hoscode'] = '10957';

$_FILES['csv_file'] = [
    'name' => 'DMexchange.csv',
    'type' => 'text/csv',
    'tmp_name' => $dummyFile,
    'error' => 0,
    'size' => filesize($dummyFile)
];

// Override is_uploaded_file for the simulation
// Since is_uploaded_file() only returns true for files uploaded via HTTP POST,
// we must mock/replace it. But wait, in PHP we can't easily override native functions
// without namespaces or extensions.
// Let's modify the check in import_hdc.php temporarily if needed, or see what fails.
echo "Simulating upload of dummy file: $dummyFile\n";

try {
    require_once __DIR__ . '/../admin/import_hdc.php';
    echo "\nSimulation finished. Message: '$message', Error: '$error'\n";
} catch (\Exception $e) {
    echo "Caught Exception: " . $e->getMessage() . "\n";
}
