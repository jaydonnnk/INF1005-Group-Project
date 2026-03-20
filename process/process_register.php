<?php
/**
 * Process Registration Form
 *
 * Validates and sanitizes all input, hashes the password,
 * and inserts the new member into the database using PDO prepared statements.
 */

session_start();
require_once "helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . Routes::REGISTER);
    exit();
}

validateCsrf(Routes::REGISTER);

// ============================================
// 1. VALIDATE & SANITIZE EACH FIELD
// ============================================

$fname = $lname = $email = $phone = "";
$errors = [];

// --- First Name (optional) ---
if (!empty($_POST["fname"])) {
    $fname = sanitizeInput($_POST["fname"]);
    if (strlen($fname) > 45) {
        $errors[] = "First name must be 45 characters or fewer.";
    }
}

// --- Last Name (required) ---
if (empty($_POST["lname"])) {
    $errors[] = "Last name is required.";
} else {
    $lname = sanitizeInput($_POST["lname"]);
    if (strlen($lname) > 45) {
        $errors[] = "Last name must be 45 characters or fewer.";
    }
}

// --- Email (required + format check) ---
if (empty($_POST["email"])) {
    $errors[] = "Email is required.";
} else {
    $email = sanitizeInput($_POST["email"]);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
}

// --- Phone (optional) ---
if (!empty($_POST["phone"])) {
    $phone = sanitizeInput($_POST["phone"]);
    // Allow digits, spaces, +, -, and parentheses
    if (!preg_match('/^[\d\s\+\-\(\)]+$/', $phone)) {
        $errors[] = "Invalid phone number format.";
    }
}

// --- Password (required, min 8 chars) ---
// Note: We do NOT sanitize the password as it may strip intentional special characters.
if (empty($_POST["pwd"])) {
    $errors[] = "Password is required.";
} elseif (strlen($_POST["pwd"]) < 8) {
    $errors[] = "Password must be at least 8 characters.";
}

// --- Confirm Password ---
if (empty($_POST["pwd_confirm"])) {
    $errors[] = "Please confirm your password.";
} elseif ($_POST["pwd"] !== $_POST["pwd_confirm"]) {
    $errors[] = "Passwords do not match.";
}

// --- Terms & Conditions Checkbox ---
if (!isset($_POST["agree"])) {
    $errors[] = "You must agree to the terms and conditions.";
}

// ============================================
// 2. PROCESS REGISTRATION IF VALID
// ============================================

$success = empty($errors);
$email_exists = false;

if ($success) {
    // Hash the password using bcrypt (NEVER store plaintext)
    $password_hash = password_hash($_POST["pwd"], PASSWORD_DEFAULT);

    try {
        require_once "db.php";

        // Check if email already exists
        $check_stmt = $pdo->prepare("SELECT member_id FROM members WHERE email = :email");
        $check_stmt->execute([":email" => $email]);

        if ($check_stmt->rowCount() > 0) {
            $errors[] = "An account with this email already exists.";
            $email_exists = true;
            $success = false;
        } else {
            // Insert new member using prepared statement (prevents SQL injection)
            $insert_stmt = $pdo->prepare(
                "INSERT INTO members (fname, lname, email, phone, password_hash)
                VALUES (:fname, :lname, :email, :phone, :password_hash)"
            );
            $insert_stmt->execute([
                ":fname" => $fname,
                ":lname" => $lname,
                ":email" => $email,
                ":phone" => $phone,
                ":password_hash" => $password_hash,
            ]);

            // Send verification email
            $new_member_id = $pdo->lastInsertId();
            $token = bin2hex(random_bytes(32));

            $token_stmt = $pdo->prepare(
                "UPDATE members
                SET verification_token = :token,
                verification_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR)
                WHERE member_id = :id"
            );
            $token_stmt->execute([':token' => $token, ':id' => $new_member_id]);

            // Load env and send email
            require_once "env_loader.php";
            loadEnv(__DIR__ . '/../.env');
            require_once "send_email.php";

            $base_url = getenv('SITE_BASE_URL');
            $verify_link = $base_url . '/verify_email.php?token=' . $token;
            $member_name = trim($fname . ' ' . $lname);

            $htmlBody = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">'
                . '<h2 style="color: #5C3D2E;">Welcome to The Rolling Dice!</h2>'
                . '<p>Hi ' . htmlspecialchars($member_name) . ',</p>'
                . '<p>Thank you for registering. Click the link below to verify your email address. This link expires in 24 hours.</p>'
                . '<p><a href="' . htmlspecialchars($verify_link) . '" style="display: inline-block; background-color: #5C3D2E; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 8px;">Verify Email</a></p>'
                . '<p style="color: #999; font-size: 0.85rem;">If you did not create this account, you can ignore this email.</p>'
                . '</div>';

            sendEmail($email, $member_name, 'Verify Your Rolling Dice Account', $htmlBody);
        }
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        $errors[] = "A system error occurred. Please try again later.";
        $success = false;
    }
}

// ============================================
// 3. DISPLAY RESULT PAGE
// ============================================
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Registration Result - The Rolling Dice</title>
    <base href="../">
    <?php include_once "../inc/head.inc.php"; ?>
</head>

<body>
    <?php include_once "../inc/nav.inc.php"; ?>

    <main class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <?php if ($success): ?>
                    <div class="text-center">
                        <span class="material-icons text-caramel" style="font-size: 4rem;"
                            aria-hidden="true">check_circle</span>
                        <h1 class="mt-3">Welcome Aboard!</h1>
                        <p>
                            A verification email has been sent to your email address.
                            Please check your inbox and click the link to activate your account.
                        </p>
                        <a href="../login.php" class="btn btn-primary">
                            <span class="material-icons align-middle me-1" aria-hidden="true">login</span>
                            Sign In
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <span class="material-icons text-danger" style="font-size: 4rem;" aria-hidden="true">error</span>
                        <h1 class="mt-3">Registration Failed</h1>
                        <div class="alert alert-danger mt-3 text-start" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                    <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php if ($email_exists): ?>
                            <a href="../login.php" class="btn btn-primary me-2">
                                <span class="material-icons align-middle me-1" aria-hidden="true">login</span>
                                Sign In Instead
                            </a>
                        <?php endif; ?>
                        <a href="../register.php" class="btn btn-outline-primary">
                            <span class="material-icons align-middle me-1" aria-hidden="true">arrow_back</span>
                            Go Back and Try Again
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <?php include_once "../inc/footer.inc.php"; ?>
</body>

</html>
