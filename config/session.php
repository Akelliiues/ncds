<?php
// config/session.php - Global Persistent Session Configuration

if (session_status() === PHP_SESSION_NONE) {
    // 1 year cookie lifetime (31,536,000 seconds)
    $lifetime = 31536000;

    // Define custom session save path under scratch/sessions to prevent cleanup by OS or other apps
    $session_dir = dirname(__DIR__) . '/scratch/sessions';
    if (!is_dir($session_dir)) {
        mkdir($session_dir, 0777, true);
    }
    session_save_path($session_dir);

    // Determine secure flag based on HTTPS status
    $secure = false;
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)) {
        $secure = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $secure = true;
    }

    // Configure session cookie parameters
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Set PHP ini settings for session lifetimes
    ini_set('session.cookie_lifetime', $lifetime);
    ini_set('session.gc_maxlifetime', $lifetime);

    // Start session
    session_start();
}
