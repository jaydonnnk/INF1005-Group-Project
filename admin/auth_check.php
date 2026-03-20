<?php
/**
 * Admin Authentication Check
 *
 *
 * include_once this at the top of every admin page.
 * Redirects non-admin users to the member dashboard.
 */
require_once __DIR__ . '/../process/process_routes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['member_id']) || empty($_SESSION['is_admin'])) {
    header("Location: " . Routes::ADMIN_DASH);
    exit();
}
