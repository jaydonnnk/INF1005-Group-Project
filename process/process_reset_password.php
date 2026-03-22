<?php
/**
 * process_reset_password.php — Process Reset Password
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Validates the reset token and updates the member's password.
 */

session_start();
require_once "helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . Routes::LOGIN);
    exit();
}

validateCsrf(Routes::LOGIN);

$token = trim($_POST['token'] ?? '');
$new_pwd = $_POST['new_pwd'] ?? '';
$pwd_confirm = $_POST['pwd_confirm'] ?? '';

// Validate token format
if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    setFlash('error', 'Invalid reset token.');
    header("Location: " . Routes::FORGOT_PW);
    exit();
}

// Validate passwords
if (empty($new_pwd) || empty($pwd_confirm)) {
    setFlash('error', 'All fields are required.');
    header("Location: " . Routes::RESET_PW . "?token=" . urlencode($token));
    exit();
}

if (strlen($new_pwd) < 8) {
    setFlash('error', 'Password must be at least 8 characters.');
    header("Location: " . Routes::RESET_PW . "?token=" . urlencode($token));
    exit();
}

if ($new_pwd !== $pwd_confirm) {
    setFlash('error', 'Passwords do not match.');
    header("Location: " . Routes::RESET_PW . "?token=" . urlencode($token));
    exit();
}

try {
    require_once "db.php";

    // Look up valid token
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW()");
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();

    if (!$row) {
        setFlash('error', 'This reset link is invalid or has expired.');
        header("Location: " . Routes::FORGOT_PW);
        exit();
    }

    // Look up verified member by email
    $stmt = $pdo->prepare("SELECT member_id FROM members WHERE email = :email AND email_verified = 1");
    $stmt->execute([':email' => $row['email']]);
    $member = $stmt->fetch();

    if (!$member) {
        setFlash('error', 'Unable to reset password. Please try again.');
        header("Location: " . Routes::FORGOT_PW);
        exit();
    }

    // Update password
    $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE members SET password_hash = :hash WHERE member_id = :id");
    $update->execute([':hash' => $new_hash, ':id' => $member['member_id']]);

    // Delete the used token
    $delete = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
    $delete->execute([':token' => $token]);

    setFlash('success', 'Your password has been reset. You can now sign in.');
    header("Location: " . Routes::LOGIN);
    exit();
} catch (PDOException $e) {
    error_log("Reset password error: " . $e->getMessage());
    setFlash('error', 'A system error occurred. Please try again.');
    header("Location: " . Routes::FORGOT_PW);
    exit();
}
