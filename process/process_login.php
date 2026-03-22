<?php
/**
 * process_login.php — Process Login Form
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Validates credentials using password_verify() against the stored bcrypt hash.
 * On success, creates a session. On failure, redirects back with a flash error.
 */

session_start();
require_once "helpers.php";


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . Routes::LOGIN);
    exit();
}

validateCsrf(redirect_url: Routes::LOGIN);

// Validate email
if (empty($_POST["email"])) {
    setFlash('error', 'Email is required.');
    header("Location: " . Routes::LOGIN);
    exit();
}

$email = trim($_POST["email"]);
$email = filter_var($email, FILTER_SANITIZE_EMAIL);

// Validate password
if (empty($_POST["pwd"])) {
    setFlash('error', 'Password is required.');
    header("Location: " . Routes::LOGIN);
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
            header("Location: " . Routes::LOGIN);
            exit();
        }

        // Check if 2FA is enabled — redirect to TOTP verification
        if (!empty($member['totp_enabled'])) {
            $_SESSION['2fa_pending'] = true;
            $_SESSION['2fa_member_data'] = $member;
            header("Location: " . Routes::VERIFY_2FA);
            exit();
        }

        // Login successful — create session
        $_SESSION["member_id"]   = $member["member_id"];
        $_SESSION["member_name"] = trim($member["fname"] . " " . $member["lname"]);
        $_SESSION["member_email"]= $member["email"];
        $_SESSION["is_admin"]    = $member["is_admin"];

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        header("Location: " . Routes::DASHBOARD);
        exit();
    } else {
        setFlash('error', 'Invalid email or password.');
    }
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    setFlash('error', 'A system error occurred. Please try again later.');
}

header("Location: " . Routes::LOGIN);
exit();
