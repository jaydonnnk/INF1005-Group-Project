<?php
/**
 * Process 2FA Setup — Verify Code & Enable
 * 
 *
 * Validates the TOTP code against the pending secret.
 * If valid, saves the secret to the database and enables 2FA.
 */

session_start();
require_once "helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . Routes::SETUP_2FA);
    exit();
}

if (!isset($_SESSION["member_id"])) {
    header("Location: " . Routes::LOGIN);
    exit();
}

validateCsrf(Routes::SETUP_2FA);

$member_id = $_SESSION["member_id"];
$secret = $_SESSION['pending_totp_secret'] ?? '';
$code = trim($_POST['totp_code'] ?? '');

if (empty($secret) || empty($code)) {
    header("Location: ../setup_2fa.php?error=" . urlencode("Please enter the 6-digit code."));
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

use RobThree\Auth\TwoFactorAuth;

$tfa = new TwoFactorAuth("The Rolling Dice");

if ($tfa->verifyCode($secret, $code)) {
    // Code is valid — save secret and enable 2FA
    require_once "db.php";

    $stmt = $pdo->prepare(
        "UPDATE members SET totp_secret = :secret, totp_enabled = 1 WHERE member_id = :id"
    );
    $stmt->execute([':secret' => $secret, ':id' => $member_id]);

    unset($_SESSION['pending_totp_secret']);

    setFlash('success', 'Two-factor authentication has been enabled successfully.');
    header("Location: " . Routes::PROFILE);
    exit();
} else {
    header("Location: " . Routes::SETUP_2FA . "?error=" . urlencode("Invalid code. Please try again."));
    exit();
}
