<?php
/**
 * Process Profile Updates
 * 
 *
 * Handles two actions:
 *   update_profile  — update name, email, phone
 *   change_password — verify current password, hash and store new one
 */

session_start();
require_once "helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../profile.php");
    exit();
}

if (!isset($_SESSION["member_id"])) {
    header("Location: ../login.php");
    exit();
}

validateCsrf('../profile.php');

$member_id = $_SESSION["member_id"];
require_once "db.php";

$action = $_POST["action"] ?? "";

switch ($action) {

    // ============================================
    // UPDATE PROFILE INFO
    // ============================================
    case "update_profile":
        $errors = [];

        $fname = sanitizeInput($_POST["fname"] ?? "");
        $lname = sanitizeInput($_POST["lname"] ?? "");
        $email = sanitizeInput($_POST["email"] ?? "");
        $phone = sanitizeInput($_POST["phone"] ?? "");

        if (strlen($fname) > 45) {
            $errors[] = "First name must be 45 characters or fewer.";
        }

        if (empty($lname)) {
            $errors[] = "Last name is required.";
        } elseif (strlen($lname) > 45) {
            $errors[] = "Last name must be 45 characters or fewer.";
        }

        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }

        if (!empty($phone) && !preg_match('/^[\d\s\+\-\(\)]+$/', $phone)) {
            $errors[] = "Invalid phone number format.";
        }

        if (!empty($errors)) {
            setFlash('error', implode(" ", $errors));
            header("Location: ../profile.php");
            exit();
        }

        // Check the new email isn't already used by a different account
        $email_check = $pdo->prepare(
            "SELECT member_id FROM members WHERE email = :email AND member_id != :id"
        );
        $email_check->execute([':email' => $email, ':id' => $member_id]);
        if ($email_check->fetch()) {
            setFlash('error', 'That email address is already in use by another account.');
            header("Location: ../profile.php");
            exit();
        }

        $stmt = $pdo->prepare(
            "UPDATE members
             SET fname = :fname, lname = :lname, email = :email, phone = :phone
             WHERE member_id = :id"
        );
        $stmt->execute([
            ':fname' => $fname,
            ':lname' => $lname,
            ':email' => $email,
            ':phone' => $phone,
            ':id'    => $member_id,
        ]);

        // Keep session in sync with the updated name and email
        $_SESSION["member_name"]  = trim($fname . " " . $lname);
        $_SESSION["member_email"] = $email;

        setFlash('success', 'Profile updated successfully.');
        header("Location: ../profile.php");
        exit();

    // ============================================
    // CHANGE PASSWORD
    // ============================================
    case "change_password":
        $current_pwd = $_POST["current_pwd"] ?? "";  // Do NOT sanitize passwords
        $new_pwd     = $_POST["new_pwd"]     ?? "";
        $pwd_confirm = $_POST["pwd_confirm"] ?? "";

        if (empty($current_pwd) || empty($new_pwd) || empty($pwd_confirm)) {
            setFlash('error', 'All password fields are required.');
            header("Location: ../profile.php");
            exit();
        }

        if (strlen($new_pwd) < 8) {
            setFlash('error', 'New password must be at least 8 characters.');
            header("Location: ../profile.php");
            exit();
        }

        if ($new_pwd !== $pwd_confirm) {
            setFlash('error', 'New passwords do not match.');
            header("Location: ../profile.php");
            exit();
        }

        // Fetch stored hash and verify current password
        $stmt = $pdo->prepare(
            "SELECT password_hash FROM members WHERE member_id = :id"
        );
        $stmt->execute([':id' => $member_id]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current_pwd, $row["password_hash"])) {
            setFlash('error', 'Current password is incorrect.');
            header("Location: ../profile.php");
            exit();
        }

        // Hash the new password and store it
        $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);

        $update = $pdo->prepare(
            "UPDATE members SET password_hash = :hash WHERE member_id = :id"
        );
        $update->execute([':hash' => $new_hash, ':id' => $member_id]);

        setFlash('success', 'Password changed successfully.');
        header("Location: ../profile.php");
        exit();

    default:
        header("Location: ../profile.php");
        exit();
}
