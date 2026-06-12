<?php
// scratch/test_security_log_cli.php
$_SERVER['HTTP_HOST'] = 'ncd.ssotansum.com'; // switch to production DB mode
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_hoscode'] = null; // simulate main admin

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Starting security_log.php CLI dry run...\n";
try {
    include __DIR__ . '/../admin/security_log.php';
    echo "\nDry run completed without script crashes.\n";
} catch (Exception $e) {
    echo "Caught Exception: " . $e->getMessage() . "\n";
} catch (Throwable $t) {
    echo "Caught Fatal Error: " . $t->getMessage() . " in " . $t->getFile() . " on line " . $t->getLine() . "\n";
}
