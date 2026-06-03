<?php
// Set default timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

// Prevent direct access to config file
if (basename($_SERVER['SCRIPT_FILENAME']) === 'config.php') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

// Database configuration for XAMPP
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'car_parking_db');
define('DB_PORT', '3306');

try {
    // Create PDO connection
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Align MySQL session time zone with PHP timezone
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    // In a production environment, you should log this error and show a generic message.
    // For local XAMPP testing, showing the error details helps the user debug connections.
    die("Database connection failed: " . $e->getMessage());
}

// Secure session start helper
if (session_status() === PHP_SESSION_NONE) {
    // Secure cookie settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Enable secure cookies if using HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}
