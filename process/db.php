<?php
/**
 * Database Connection Script (PDO)
 * The Rolling Dice - Board Game Café
 *
 * Configured for Google Cloud SQL (MySQL).
 * Uses PDO with prepared statements to prevent SQL injection.
 *
 * SETUP: Replace the placeholder values below with your
 *        actual Google Cloud SQL credentials.
 */

// ============================================
// Google Cloud SQL Configuration
// ============================================
// Option 1: If connecting via Cloud SQL Public IP
//           Set $db_host to your instance's public IP address.
//
// Option 2: If connecting via Cloud SQL Auth Proxy (recommended)
//           Set $db_host to "127.0.0.1" (the proxy runs locally).
//
// Option 3: If connecting via Unix socket (App Engine / Cloud Run)
//           Uncomment the socket DSN below and comment out the TCP one.
// ============================================

$db_host = "127.0.0.1";                          // Cloud SQL Proxy default, or your instance Public IP
$db_port = "3306";                                // Default MySQL port
$db_name = "rolling_dice_db";                     // Database name (created in Cloud SQL)
$db_user = "rolling_dice_user";                   // Cloud SQL user (created in step 4)
$db_pass = "YOUR_PASSWORD_HERE";                  // Cloud SQL user password
$db_charset = "utf8mb4";

// For App Engine / Cloud Run using Unix socket, set this:
// $db_socket = "/cloudsql/YOUR_PROJECT_ID:YOUR_REGION:YOUR_INSTANCE_NAME";

// DSN (Data Source Name) — TCP connection
$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=$db_charset";

// DSN — Unix socket connection (uncomment if using App Engine / Cloud Run)
// $dsn = "mysql:unix_socket=$db_socket;dbname=$db_name;charset=$db_charset";

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
