<?php
/**
 * LiteDBAdmin Configuration File
 * 
 * This file contains all database connection settings and application configurations.
 */

// Database connection configuration
$host = "localhost";      // Database host
$username = "root";       // Database username
$password = "";           // Database password
$database = "mysql";      // Default database

// Security settings
$dev_password = "@ayushx309@";  // Developer access password
$max_login_attempts = 5;        // Maximum login attempts before lockout
$lockout_time = 30 * 60;        // Lockout duration in seconds (30 minutes)

// Establish database connection with error handling
try {
    $conn = mysqli_connect($host, $username, $password, $database);
    
    // Check connection
    if (!$conn) {
        throw new Exception(mysqli_connect_error());
    }
    
    // Set character set for proper UTF-8 support
    mysqli_set_charset($conn, "utf8mb4");
    
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Session configuration
$session_timeout = 3600;         // Session timeout (1 hour)
ini_set('session.gc_maxlifetime', $session_timeout);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS'])); // Secure cookies when using HTTPS

// Performance settings
$max_execution_time = 30;        // Maximum query execution time (in seconds)
ini_set('max_execution_time', $max_execution_time);

// Error reporting settings - disable in production
if ($_SERVER['SERVER_NAME'] == 'localhost' || strpos($_SERVER['SERVER_NAME'], 'dev.') === 0) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
?>
