<?php
/**
 * Database Connection Script (PDO)
 * 
 *
 * Configured for LAMP stack on Google Cloud Compute Engine.
 * MySQL runs on the same VM as Apache/PHP, so we connect via localhost.
 * Uses PDO with prepared statements to prevent SQL injection.
 */

// Database configuration (LAMP — MySQL on same server)
$db_host = "localhost";
$db_name = "rolling_dice_db";
$db_user = "rolling_dice_user";     // Created during MySQL setup
$db_pass = "Student@s1t";    // Set during MySQL setup
$db_charset = "utf8mb4";

// DSN (Data Source Name)
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

// PDO options for security and error handling
$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,   // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Return associative arrays
    PDO::ATTR_EMULATE_PREPARES => false,                    // Use real prepared statements
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $pdo_options);
} catch (PDOException $e) {
    // In production, log the error instead of displaying it
    error_log("Database connection failed: " . $e->getMessage());
    die("Sorry, we're experiencing technical difficulties. Please try again later.");
}