<?php
// Database Connection Script (PDO)
// Uses PDO with prepared statements to prevent SQL injection.
// Include this file in any PHP script that needs database access.

// Database configuration
$db_host = "localhost";
$db_name = "rolling_dice_db";
$db_user = "root";         // Change for production
$db_pass = "";             // Change for production
$db_charset = "utf8mb4";

// DSN (Data Source Name)
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

// PDO options for security and error handling
$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Return associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                    // Use real prepared statements
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $pdo_options);
} catch (PDOException $e) {
    // In production, log the error instead of displaying it
    error_log("Database connection failed: " . $e->getMessage());
    die("Sorry, we're experiencing technical difficulties. Please try again later.");
}
