<?php
/**
 * process_forgot_password.php — Process Forgot Password
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Generates a password reset token and sends the reset email.
 * Uses a generic success message to avoid revealing whether the email exists.
 */

session_start();
require_once "helpers.php";
require_once "env_loader.php";
loadEnv(__DIR__ . '/../.env');
require_once "send_email.php";

define('FORGOT_PAGE', '../forgot_password.php');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . Routes::FORGOT_PW);
    exit();
}

validateCsrf(Routes::FORGOT_PW);

$email = trim($_POST["email"] ?? "");

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setFlash('error', 'Please enter a valid email address.');
    header("Location: " . Routes::FORGOT_PW);
    exit();
}

try {
    require_once "db.php";

    // Look up verified member
    $stmt = $pdo->prepare("SELECT member_id, fname, lname FROM members WHERE email = :email AND email_verified = 1");
    $stmt->execute([':email' => $email]);
    $member = $stmt->fetch();

    if ($member) {
        // Generate new token
        $token = bin2hex(random_bytes(32));

        // Delete any existing reset entry for this email
        $delete = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
        $delete->execute([':email' => $email]);

        // Insert new reset record (1-hour expiry)
        $insert = $pdo->prepare(
            "INSERT INTO password_resets (email, token, expires_at)
            VALUES (:email, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
        );
        $insert->execute([':email' => $email, ':token' => $token]);

        // Send reset email
        $base_url = getenv('SITE_BASE_URL');
        $reset_link = $base_url . '/reset_password.php?token=' . $token;
        $name = trim($member['fname'] . ' ' . $member['lname']);

        $htmlBody = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">'
            . '<h2 style="color: #5C3D2E;">Reset Your Password</h2>'
            . '<p>Hi ' . htmlspecialchars($name) . ',</p>'
            . '<p>Click the button below to reset your password. This link expires in 1 hour.</p>'
            . '<p><a href="' . htmlspecialchars($reset_link) . '" style="display: inline-block; background-color: #5C3D2E; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 8px;">Reset Password</a></p>'
            . '<p style="color: #999; font-size: 0.85rem;">If you did not request a password reset, you can ignore this email.</p>'
            . '</div>';

        sendEmail($email, $name, 'Reset Your Rolling Dice Password', $htmlBody);
    }

    // Always show generic message (don't reveal if email exists)
    setFlash('success', 'If an account exists with that email, a password reset link has been sent. Please check your inbox.');
} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    setFlash('success', 'If an account exists with that email, a password reset link has been sent. Please check your inbox.');
}

header("Location: " . Routes::FORGOT_PW);
exit();
