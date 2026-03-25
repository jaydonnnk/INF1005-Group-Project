<?php
/**
 * process_profile.php — Process Profile Updates
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Handles two actions:
 *   update_profile  — update name, email, phone
 *   change_password — verify current password, hash and store new one
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
require_once "db.php";

$action = $_POST["action"] ?? "";

switch ($action) {

    // UPDATE PROFILE INFO
    case "update_profile":
        $errors = [];

        $fname = sanitizeInput($_POST["fname"] ?? "");
        $lname = sanitizeInput($_POST["lname"] ?? "");
        $email = sanitizeInput($_POST["email"] ?? "");

        // Phone: combine country code + number
        $allowed_codes = ['+65', '+60', '+62', '+63', '+66', '+91', '+44', '+1', '+61', '+81', '+82', '+86'];
        $country_code = sanitizeInput($_POST["country_code"] ?? "+65");
        $phone_number = sanitizeInput($_POST["phone_number"] ?? "");
        $phone = "";

        if (!empty($phone_number)) {
            if (!in_array($country_code, $allowed_codes)) {
                $errors[] = "Invalid country code.";
            } elseif (!preg_match('/^\d+$/', $phone_number)) {
                $errors[] = "Phone number must contain only digits.";
            } else {
                $phone_len = strlen($phone_number);
                if ($country_code === '+65') {
                    if ($phone_len !== 8 || !preg_match('/^[689]/', $phone_number)) {
                        $errors[] = "Singapore numbers must be 8 digits starting with 6, 8, or 9.";
                    }
                } elseif ($country_code === '+60') {
                    if ($phone_len < 9 || $phone_len > 10) {
                        $errors[] = "Malaysia numbers must be 9-10 digits.";
                    }
                } elseif ($country_code === '+1') {
                    if ($phone_len !== 10) {
                        $errors[] = "US/Canada numbers must be exactly 10 digits.";
                    }
                } else {
                    if ($phone_len < 7 || $phone_len > 15) {
                        $errors[] = "Phone number must be 7-15 digits.";
                    }
                }
            }
            $phone = $country_code . " " . $phone_number;
        }

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
        } else {
            $allowed_domains = [
                'gmail.com', 'yahoo.com', 'yahoo.com.sg', 'hotmail.com', 'outlook.com',
                'live.com', 'icloud.com', 'me.com', 'mac.com', 'aol.com',
                'protonmail.com', 'proton.me', 'zoho.com',
                'sit.singaporetech.edu.sg', 'singaporetech.edu.sg',
                'u.nus.edu', 'ntu.edu.sg', 'smu.edu.sg', 'sutd.edu.sg',
                'mymail.sim.edu.sg', 'tp.edu.sg', 'np.edu.sg', 'sp.edu.sg',
                'rp.edu.sg', 'nyp.edu.sg'
            ];
            $email_domain = strtolower(substr(strrchr($email, '@'), 1));
            if (!in_array($email_domain, $allowed_domains)) {
                $errors[] = "Please use a valid email provider (Gmail, Yahoo, Outlook, etc.) or your school email.";
            }
        }

        if (!empty($errors)) {
            setFlash('error', implode(" ", $errors));
            header("Location: " . Routes::PROFILE);
            exit();
        }

        // Check the new email isn't already used by a different account
        $email_check = $pdo->prepare(
            "SELECT member_id FROM members WHERE email = :email AND member_id != :id"
        );
        $email_check->execute([':email' => $email, ':id' => $member_id]);
        if ($email_check->fetch()) {
            setFlash('error', 'That email address is already in use by another account.');
            header("Location: " . Routes::PROFILE);
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
        header("Location: " . Routes::PROFILE);
        exit();

    // CHANGE PASSWORD
    case "change_password":
        $current_pwd = $_POST["current_pwd"] ?? "";  // Do NOT sanitize passwords
        $new_pwd     = $_POST["new_pwd"]     ?? "";
        $pwd_confirm = $_POST["pwd_confirm"] ?? "";

        if (empty($current_pwd) || empty($new_pwd) || empty($pwd_confirm)) {
            setFlash('error', 'All password fields are required.');
            header("Location: " . Routes::PROFILE);
            exit();
        }

        if (strlen($new_pwd) < 12) {
            setFlash('error', 'New password must be at least 12 characters.');
            header("Location: " . Routes::PROFILE);
            exit();
        }

        if (!preg_match('/[A-Z]/', $new_pwd)) {
            setFlash('error', 'New password must contain at least one uppercase letter.');
            header("Location: " . Routes::PROFILE);
            exit();
        }

        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]/', $new_pwd)) {
            setFlash('error', 'New password must contain at least one special character.');
            header("Location: " . Routes::PROFILE);
            exit();
        }

        if ($new_pwd !== $pwd_confirm) {
            setFlash('error', 'New passwords do not match.');
            header("Location: " . Routes::PROFILE);
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
            header("Location: " . Routes::PROFILE);
            exit();
        }

        // Hash the new password and store it
        $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);

        $update = $pdo->prepare(
            "UPDATE members SET password_hash = :hash WHERE member_id = :id"
        );
        $update->execute([':hash' => $new_hash, ':id' => $member_id]);

        setFlash('success', 'Password changed successfully.');
        header("Location: " . Routes::PROFILE);
        exit();

    // DISABLE ACCOUNT
    case "disable_account":
        $confirm_pwd = $_POST["confirm_pwd"] ?? "";  // Do NOT sanitize passwords

        if (empty($confirm_pwd)) {
            setFlash('error', 'Please enter your password to confirm.');
            header("Location: " . Routes::PROFILE);
            exit();
        }

        // Fetch stored hash and verify password
        $stmt = $pdo->prepare(
            "SELECT password_hash FROM members WHERE member_id = :id"
        );
        $stmt->execute([':id' => $member_id]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($confirm_pwd, $row["password_hash"])) {
            setFlash('error', 'Incorrect password. Account was not disabled.');
            header("Location: " . Routes::PROFILE);
            exit();
        }

        // Mark account as disabled
        $update = $pdo->prepare(
            "UPDATE members SET account_status = 'disabled' WHERE member_id = :id"
        );
        $update->execute([':id' => $member_id]);

        // Destroy session immediately — user is now locked out
        $_SESSION = [];
        session_destroy();

        header("Location: " . Routes::LOGIN . "?disabled=1");
        exit();
        
    default:
        header("Location: " . Routes::PROFILE);
        exit();
}
