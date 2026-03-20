<?php
/**
 * Process Resend Verification Email
 * 
 *
 * Generates a new verification token and sends the email.
 * Uses a generic success message to avoid revealing whether the email exists.
 */

session_start();
require_once "helpers.php";
require_once "env_loader.php";
loadEnv(__DIR__ . '/../.env');
require_once "send_email.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../resend_verification.php");
    exit();
}

validateCsrf('../resend_verification.php');

$email = trim($_POST["email"] ?? "");

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setFlash('error', 'Please enter a valid email address.');
    header("Location: ../resend_verification.php");
    exit();
}

try {
    require_once "db.php";

    // Look up unverified member
    $stmt = $pdo->prepare("SELECT member_id, fname, lname FROM members WHERE email = :email AND email_verified = 0");
    $stmt->execute([':email' => $email]);
    $member = $stmt->fetch();

    if ($member) {
        // Generate new token
        $token = bin2hex(random_bytes(32));

        $update = $pdo->prepare(
            "UPDATE members
             SET verification_token = :token,
                 verification_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR)
             WHERE member_id = :id"
        );
        $update->execute([':token' => $token, ':id' => $member['member_id']]);

        // Send verification email
        $base_url = getenv('SITE_BASE_URL');
        $verify_link = $base_url . '/verify_email.php?token=' . $token;
        $name = trim($member['fname'] . ' ' . $member['lname']);

        $htmlBody = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">'
            . '<h2 style="color: #5C3D2E;">Verify Your Email</h2>'
            . '<p>Hi ' . htmlspecialchars($name) . ',</p>'
            . '<p>Click the link below to verify your email address. This link expires in 24 hours.</p>'
            . '<p><a href="' . htmlspecialchars($verify_link) . '" style="display: inline-block; background-color: #5C3D2E; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 8px;">Verify Email</a></p>'
            . '<p style="color: #999; font-size: 0.85rem;">If you did not create an account, you can ignore this email.</p>'
            . '</div>';

        sendEmail($email, $name, 'Verify Your Rolling Dice Account', $htmlBody);
    }

    // Always show generic message (don't reveal if email exists)
    setFlash('success', 'If an account exists with that email, a verification link has been sent. Please check your inbox.');
} catch (Exception $e) {
    error_log("Resend verification error: " . $e->getMessage());
    setFlash('success', 'If an account exists with that email, a verification link has been sent. Please check your inbox.');
}

header("Location: ../resend_verification.php");
exit();
