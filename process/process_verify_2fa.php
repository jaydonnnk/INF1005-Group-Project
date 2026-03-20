<?php
/**
 * Process 2FA Verification (Login)
 * 
 *
 * Validates the TOTP code during login and completes the sign-in.
 */

session_start();
require_once "helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit();
}

// Must have a pending 2FA session
if (empty($_SESSION['2fa_pending']) || empty($_SESSION['2fa_member_data'])) {
    header("Location: ../login.php");
    exit();
}

validateCsrf('../verify_2fa.php');

$code = trim($_POST['totp_code'] ?? '');
$member = $_SESSION['2fa_member_data'];

if (empty($code)) {
    header("Location: ../verify_2fa.php?error=" . urlencode("Please enter the 6-digit code."));
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

use RobThree\Auth\TwoFactorAuth;

$tfa = new TwoFactorAuth("The Rolling Dice");

if ($tfa->verifyCode($member['totp_secret'], $code)) {
    // Code valid — complete login
    unset($_SESSION['2fa_pending']);
    unset($_SESSION['2fa_member_data']);

    $_SESSION["member_id"]   = $member["member_id"];
    $_SESSION["member_name"] = trim($member["fname"] . " " . $member["lname"]);
    $_SESSION["member_email"]= $member["email"];
    $_SESSION["is_admin"]    = $member["is_admin"];

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    header("Location: ../dashboard.php");
    exit();
} else {
    header("Location: ../verify_2fa.php?error=" . urlencode("Invalid code. Please try again."));
    exit();
}
