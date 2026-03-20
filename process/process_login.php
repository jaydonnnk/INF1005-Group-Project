<?php
/**
 * Process Login Form
 *
 *
 * Validates credentials using password_verify() against the stored bcrypt hash.
 * On success, creates a session. On failure, redirects back with a flash error.
 */

session_start();
require_once "helpers.php";

define('LOGIN_PAGE', '../login.php');
define('DASHBOARD_PAGE', '../dashboard.php');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . LOGIN_PAGE);
    exit();
}

validateCsrf(redirect_url: '../login.php');

// Validate email
if (empty($_POST["email"])) {
    setFlash('error', 'Email is required.');
    header("Location: " . LOGIN_PAGE);
    exit();
}

$email = trim($_POST["email"]);
$email = filter_var($email, FILTER_SANITIZE_EMAIL);

// Validate password
if (empty($_POST["pwd"])) {
    setFlash('error', 'Password is required.');
    header("Location: " . LOGIN_PAGE);
    exit();
}

$pwd = $_POST["pwd"]; // Do NOT sanitize passwords

// Check against database
try {
    require_once "db.php";

    $stmt = $pdo->prepare("SELECT member_id, fname, lname, email, password_hash, is_admin, email_verified, totp_secret, totp_enabled FROM members WHERE email = :email");
    $stmt->execute([":email" => $email]);
    $member = $stmt->fetch();

    if ($member && password_verify($pwd, $member["password_hash"])) {
        // Check email verification
        if (empty($member['email_verified'])) {
            setFlash('error', 'Please verify your email address first. Check your inbox for the verification link.');
            header("Location: " . LOGIN_PAGE);
            exit();
        }

        // Check if 2FA is enabled — redirect to TOTP verification
        if (!empty($member['totp_enabled'])) {
            $_SESSION['2fa_pending'] = true;
            $_SESSION['2fa_member_data'] = $member;
            header("Location: ../verify_2fa.php");
            exit();
        }

        // Login successful — create session
        $_SESSION["member_id"]   = $member["member_id"];
        $_SESSION["member_name"] = trim($member["fname"] . " " . $member["lname"]);
        $_SESSION["member_email"]= $member["email"];
        $_SESSION["is_admin"]    = $member["is_admin"];

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        header("Location: " . DASHBOARD_PAGE);
        exit();
    } else {
        setFlash('error', 'Invalid email or password.');
    }
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    setFlash('error', 'A system error occurred. Please try again later.');
}

header("Location: " . LOGIN_PAGE);
exit();
