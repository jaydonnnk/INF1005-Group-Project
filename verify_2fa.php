<?php
/**
 * Two-Factor Authentication Verification (Login)
 * The Rolling Dice - Board Game Café
 *
 * Shown during login when a member has 2FA enabled.
 * The member enters their 6-digit TOTP code to complete sign-in.
 */

session_start();
require_once "process/helpers.php";

// Must have a pending 2FA session
if (empty($_SESSION['2fa_pending']) || empty($_SESSION['2fa_member_data'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Two-Factor Verification - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>

<body>
    <?php include "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">

                <h1>Two-Factor Verification</h1>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <p>Open your authenticator app and enter the 6-digit code shown for <strong>The Rolling Dice</strong>.</p>

                        <form action="process/process_verify_2fa.php" method="post"
                              class="needs-validation" novalidate
                              aria-label="Two-factor verification form">

                            <?php echo csrfField(); ?>

                            <div class="mb-3">
                                <label for="totp_code" class="form-label">
                                    Authentication Code: <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="totp_code" name="totp_code"
                                    class="form-control text-center fs-4"
                                    maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                                    placeholder="000000" autocomplete="one-time-code" required autofocus>
                                <div class="invalid-feedback">Please enter the 6-digit code.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <span class="material-icons align-middle me-1" aria-hidden="true">login</span>
                                Verify &amp; Sign In
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="login.php" class="text-muted small">Back to Sign In</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>

</html>
