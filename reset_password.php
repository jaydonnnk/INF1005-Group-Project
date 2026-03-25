<?php
/**
 * reset_password.php — Reset Password Page
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Validates the token from the reset link and shows the password reset form.
 */
session_start();
require_once "process/db.php";
require_once "process/helpers.php";

$token = $_GET['token'] ?? '';
$valid = false;
$reset_email = '';

if (!empty($token) && strlen($token) === 64 && ctype_xdigit($token)) {
    $stmt = $pdo->prepare(
        "SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW()"
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
    if ($row) {
        $valid = true;
        $reset_email = $row['email'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password - The Rolling Dice</title>
    <?php include_once "inc/head.inc.php"; ?>
</head>
<body>
    <?php include_once "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 text-center">
                <?php if ($valid): ?>
                    <span class="material-icons text-caramel" style="font-size: 4rem;" aria-hidden="true">lock_reset</span>
                    <h1 class="mt-3">Set a New Password</h1>
                    <p>Enter and confirm your new password below.</p>

                    <?php echo displayFlash(); ?>

                    <form action="process/process_reset_password.php" method="post"
                        class="needs-validation text-start" novalidate
                        aria-label="Reset password form">

                        <?php echo csrfField(); ?>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                        <div class="mb-3">
                            <label for="new_pwd" class="form-label">New Password: <span class="text-danger">*</span></label>
                            <input type="password" id="new_pwd" name="new_pwd" class="form-control"
                                minlength="12"
                                pattern="(?=.*[A-Z])(?=.*[!@#$%^&amp;*()_+\-=\[\]{};':&quot;\\|,.&lt;&gt;\/?]).{12,}"
                                title="At least 12 characters, 1 uppercase letter, and 1 special character"
                                placeholder="Minimum 12 characters" required>
                            <div class="form-text">Must be at least 12 characters, with at least 1 uppercase letter and 1 special character</div>
                            <div class="invalid-feedback">Password must meet the requirements above.</div>
                        </div>

                        <div class="mb-3">
                            <label for="pwd_confirm" class="form-label">Confirm New Password: <span class="text-danger">*</span></label>
                            <input type="password" id="pwd_confirm" name="pwd_confirm" class="form-control"
                                minlength="12" placeholder="Re-enter new password" required>
                            <div class="invalid-feedback">Please confirm your new password.</div>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <span class="material-icons align-middle me-1" aria-hidden="true">lock_reset</span>
                                Reset Password
                            </button>
                        </div>

                    </form>
                <?php else: ?>
                    <span class="material-icons text-danger" style="font-size: 4rem;" aria-hidden="true">error</span>
                    <h1 class="mt-3">Invalid or Expired Link</h1>
                    <p>This password reset link is invalid or has expired.</p>
                    <a href="forgot_password.php" class="btn btn-outline-primary">
                        <span class="material-icons align-middle me-1" aria-hidden="true">lock_reset</span>
                        Request a New Link
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include_once "inc/footer.inc.php"; ?>
</body>
</html>
