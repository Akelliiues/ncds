<?php
// scratch/find_auth_gaps.php
// Script to scan the codebase for potential security gaps, missing auth checks, and code quality issues.

function scanDirectory($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

$baseDir = dirname(__DIR__);
$adminFiles = scanDirectory($baseDir . '/admin');
$vhvFiles = scanDirectory($baseDir . '/vhv');
$apiFiles = scanDirectory($baseDir . '/api');
$rootFiles = glob($baseDir . '/*.php');

echo "=== SCANNING ADMIN FILES ===\n";
foreach ($adminFiles as $file) {
    $content = file_get_contents($file);
    $relative = str_replace($baseDir . '/', '', $file);
    
    // Ignore backup or utility files
    if (strpos($relative, '.bak') !== false || strpos($relative, 'seed_db.php') !== false || strpos($relative, 'clear_coords') !== false || strpos($relative, 'swap_coords') !== false) {
        continue;
    }
    
    // Check if session.php is included
    $hasSession = (strpos($content, 'session.php') !== false);
    // Check if admin log in check exists
    $hasAdminCheck = (strpos($content, 'admin_logged_in') !== false);
    
    if (!$hasSession || !$hasAdminCheck) {
        echo "[WARNING] Admin File: $relative\n";
        echo "  - Has Session Config: " . ($hasSession ? 'Yes' : 'NO') . "\n";
        echo "  - Has Admin Login Check: " . ($hasAdminCheck ? 'Yes' : 'NO') . "\n";
    }
}

echo "\n=== SCANNING VHV FILES ===\n";
foreach ($vhvFiles as $file) {
    $content = file_get_contents($file);
    $relative = str_replace($baseDir . '/', '', $file);
    
    if (in_array(basename($file), ['login.php', 'register.php'])) {
        continue;
    }
    
    $hasSession = (strpos($content, 'session.php') !== false);
    $hasVhvCheck = (strpos($content, 'vhv_id') !== false);
    
    if (!$hasSession || !$hasVhvCheck) {
        echo "[WARNING] VHV File: $relative\n";
        echo "  - Has Session Config: " . ($hasSession ? 'Yes' : 'NO') . "\n";
        echo "  - Has VHV Login Check: " . ($hasVhvCheck ? 'Yes' : 'NO') . "\n";
    }
}

echo "\n=== SCANNING API FILES (AUTH CHECK) ===\n";
foreach ($apiFiles as $file) {
    $content = file_get_contents($file);
    $relative = str_replace($baseDir . '/', '', $file);
    
    if (in_array(basename($file), ['auth.php', 'line_webhook.php', 'toggle_sandbox.php'])) {
        continue;
    }
    
    $hasSession = (strpos($content, 'session.php') !== false);
    $hasVhvCheck = (strpos($content, 'vhv_id') !== false || strpos($content, 'admin_logged_in') !== false || strpos($content, 'is_visitor') !== false);
    
    if (!$hasSession && !$hasVhvCheck) {
        echo "[INFO] API File without session/auth check: $relative\n";
    }
}

echo "\n=== SCANNING ALL PHP FILES FOR SQL INJECTION PATTERNS ===\n";
$allFiles = array_merge($adminFiles, $vhvFiles, $apiFiles, $rootFiles);
foreach ($allFiles as $file) {
    $content = file_get_contents($file);
    $relative = str_replace($baseDir . '/', '', $file);
    
    if (strpos($relative, 'scratch/') !== false || strpos($relative, '.bak') !== false) {
        continue;
    }
    
    // Look for variables inside prepare/query/exec calls
    // Pattern: ->query("... $var ...") or ->prepare("... $var ...") or ->exec("... $var ...")
    // Let's matching something like: ->(query|prepare|exec)\(\s*["'][^"']*\$[a-zA-Z_0-9]+[^"']*["']\s*\)
    if (preg_match_all('/->(query|prepare|exec)\(\s*["\']([^"\']*)\$[a-zA-Z_0-9]+([^"\']*)["\']\s*\)/', $content, $matches)) {
        echo "[POTENTIAL SQLI] $relative:\n";
        foreach ($matches[0] as $match) {
            echo "  - Match: " . trim($match) . "\n";
        }
    }
}

echo "\n=== SCANNING FOR TRANSACTION / EARLY EXIT ANOMALIES ===\n";
foreach ($allFiles as $file) {
    $content = file_get_contents($file);
    $relative = str_replace($baseDir . '/', '', $file);
    if (strpos($relative, 'scratch/') !== false || strpos($relative, '.bak') !== false) {
        continue;
    }
    
    // Check if beginTransaction is called
    if (strpos($content, 'beginTransaction') !== false) {
        // If there's an "exit(" or "die(" or "return;" or "header(" that redirects and exits, let's see if rollback is missing before it.
        // We can do a simple check: does it have exit/die without rollBack?
        // This is a naive regex but it helps inspect.
        echo "[TRANSACTION USER] $relative (contains beginTransaction)\n";
    }
}

echo "\n=== FINISHED ===\n";
