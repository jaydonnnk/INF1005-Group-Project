<?php
/**
 * Resend Verification Email Page
 * The Rolling Dice - Board Game Café
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Resend Verification - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>
<body>
    <?php include "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">

                <h1>Resend Verification Email</h1>
                <p>Enter the email address you registered with and we'll send you a new verification link.</p>

                <?php echo displayFlash(); ?>

                <form action="process/process_resend_verification.php" method="post"
                    class="needs-validation" novalidate
                    aria-label="Resend verification email form">

                    <?php echo csrfField(); ?>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email: <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" maxlength="100"
                            placeholder="Enter your email" required>
                        <div class="invalid-feedback">Please enter your email address.</div>
                    </div>

                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <span class="material-icons align-middle me-1" aria-hidden="true">email</span>
                            Resend Verification Email
                        </button>
                    </div>

                </form>

                <p class="text-center">
                    <a href="login.php">Back to Sign In</a>
                </p>
            </div>
        </div>
    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>
</html>
