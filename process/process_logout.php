<?php
/**
 * process_logout.php — Process Logout
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Destroys the current session and redirects to the home page.
 */
require_once __DIR__ . '/process_routes.php';

session_start();
$_SESSION = [];
session_destroy();
header("Location: " . Routes::INDEX);
exit();
