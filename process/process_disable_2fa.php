<?php
/**
 * process_disable_2fa.php — Process Disable 2FA
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Verifies the member's current password before disabling 2FA.
 */

session_start();
require_once "helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . Routes::PROFILE);
    exit();
}

if (!isset($_SESSION["member_id"])) {
    header("Location: " . Routes::LOGIN);
    exit();
}

validateCsrf(Routes::PROFILE);

$member_id = $_SESSION["member_id"];
$current_pwd = $_POST["current_pwd"] ?? "";

if (empty($current_pwd)) {
    setFlash('error', 'Password is required to disable 2FA.');
    header("Location: " . Routes::PROFILE);
    exit();
}

require_once "db.php";

// Verify current password
$stmt = $pdo->prepare("SELECT password_hash FROM members WHERE member_id = :id");
$stmt->execute([':id' => $member_id]);
$row = $stmt->fetch();

if (!$row || !password_verify($current_pwd, $row["password_hash"])) {
    setFlash('error', 'Incorrect password. 2FA was not disabled.');
    header("Location: " . Routes::PROFILE);
    exit();
}

// Disable 2FA
$update = $pdo->prepare(
    "UPDATE members SET totp_secret = NULL, totp_enabled = 0 WHERE member_id = :id"
);
$update->execute([':id' => $member_id]);

setFlash('success', 'Two-factor authentication has been disabled.');
header("Location: " . Routes::PROFILE);
exit();
