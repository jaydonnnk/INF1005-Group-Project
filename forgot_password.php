<?php
/**
 * forgot_password.php — Forgot Password Page
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Forgot Password - The Rolling Dice</title>
    <?php include_once "inc/head.inc.php"; ?>
</head>
<body>
    <?php include_once "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">

                <h1>Forgot Password</h1>
                <p>Enter the email address linked to your account and we'll send you a password reset link.</p>

                <?php echo displayFlash(); ?>

                <form action="process/process_forgot_password.php" method="post"
                    class="needs-validation" novalidate
                    aria-label="Forgot password form">

                    <?php echo csrfField(); ?>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email: <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" maxlength="100"
                            placeholder="Enter your email" required>
                        <div class="invalid-feedback">Please enter your email address.</div>
                    </div>

                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <span class="material-icons align-middle me-1" aria-hidden="true">lock_reset</span>
                            Send Reset Link
                        </button>
                    </div>

                </form>

                <p class="text-center">
                    Remembered your password? <a href="login.php">Sign In</a>
                </p>
            </div>
        </div>
    </main>

    <?php include_once "inc/footer.inc.php"; ?>
</body>
</html>
