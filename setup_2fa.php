<?php
/**
 * Two-Factor Authentication Setup
 * 
 *
 * Generates a TOTP secret, displays a QR code, and asks the user
 * to verify with a 6-digit code before enabling 2FA.
 */

session_start();
if (!isset($_SESSION["member_id"])) {
    header("Location: login.php");
    exit();
}

$member_id = $_SESSION["member_id"];
require_once "process/db.php";
require_once __DIR__ . '/vendor/autoload.php';

use RobThree\Auth\TwoFactorAuth;

// Check if 2FA is already enabled
$stmt = $pdo->prepare("SELECT email, totp_enabled FROM members WHERE member_id = :id");
$stmt->execute([':id' => $member_id]);
$member = $stmt->fetch();

if (!empty($member['totp_enabled'])) {
    setFlash('error', '2FA is already enabled on your account.');
    header("Location: profile.php");
    exit();
}

// Generate or reuse pending secret
$tfa = new TwoFactorAuth("The Rolling Dice");

if (empty($_SESSION['pending_totp_secret'])) {
    $_SESSION['pending_totp_secret'] = $tfa->createSecret();
}

$secret = $_SESSION['pending_totp_secret'];
$qrDataUri = $tfa->getQRCodeImageAsDataUri($member['email'], $secret);
$secretDisplay = chunk_split($secret, 4, ' ');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Set Up 2FA - The Rolling Dice</title>
    <?php include_once "inc/head.inc.php"; ?>
</head>

<body>
    <?php include_once "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <h1>Set Up Two-Factor Authentication</h1>

                <?php echo displayFlash(); ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Follow these steps:</h2>
                        <ol class="mb-4">
                            <li>Install <strong>Google Authenticator</strong> or <strong>Authy</strong> on your phone.</li>
                            <li>Scan the QR code below with the app.</li>
                            <li>Enter the 6-digit code shown in the app to verify.</li>
                        </ol>

                        <div class="text-center mb-4">
                            <img src="<?php echo $qrDataUri; ?>" alt="2FA QR Code" class="img-fluid" style="max-width: 200px;">
                        </div>

                        <div class="mb-4">
                            <p class="text-muted small mb-1">Can't scan the QR code? Enter this key manually:</p>
                            <code class="d-block text-center fs-5 p-2 bg-light rounded"><?php echo htmlspecialchars($secretDisplay); ?></code>
                        </div>

                        <form action="process/process_setup_2fa.php" method="post"
                            class="needs-validation" novalidate
                            aria-label="Verify 2FA code form">

                            <?php echo csrfField(); ?>

                            <div class="mb-3">
                                <label for="totp_code" class="form-label">
                                    Verification Code: <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="totp_code" name="totp_code"
                                class="form-control text-center fs-4"
                                maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                                placeholder="000000" autocomplete="one-time-code" required>
                                <div class="invalid-feedback">Please enter the 6-digit code from your authenticator app.</div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <span class="material-icons align-middle me-1" aria-hidden="true">verified</span>
                                    Verify &amp; Enable 2FA
                                </button>
                                <a href="profile.php" class="btn btn-outline-primary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include_once "inc/footer.inc.php"; ?>
</body>

</html>
