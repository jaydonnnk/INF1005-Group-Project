<?php
// Process Login Form
// Validates credentials using password_verify() against the stored bcrypt hash.
// On success, creates a session. On failure, redirects back with an error message.

session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit();
}

$email = $pwd = "";
$errorMsg = "";

// Validate email
if (empty($_POST["email"])) {
    $errorMsg = "Email is required.";
} else {
    $email = trim($_POST["email"]);
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
}

// Validate password
if (empty($_POST["pwd"])) {
    $errorMsg = "Password is required.";
} else {
    $pwd = $_POST["pwd"]; // Do NOT sanitize passwords
}

// If basic validation passed, check against database
if (empty($errorMsg)) {
    try {
        require_once "db.php";

        $stmt = $pdo->prepare("SELECT member_id, fname, lname, email, password_hash FROM members WHERE email = :email");
        $stmt->execute([":email" => $email]);
        $member = $stmt->fetch();

        if ($member && password_verify($pwd, $member["password_hash"])) {
            // Login successful — create session
            $_SESSION["member_id"]   = $member["member_id"];
            $_SESSION["member_name"] = trim($member["fname"] . " " . $member["lname"]);
            $_SESSION["member_email"]= $member["email"];

            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            header("Location: ../dashboard.php");
            exit();
        } else {
            $errorMsg = "Invalid email or password.";
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $errorMsg = "A system error occurred. Please try again later.";
    }
}

// Redirect back to login with error
header("Location: ../login.php?error=" . urlencode($errorMsg));
exit();
