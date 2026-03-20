<?php
/**
 * Email Verification Page
 * 
 *
 * Validates the token from the verification link and activates the account.
 */
require_once "process/db.php";

$verified = false;
$token = $_GET['token'] ?? '';

if (!empty($token) && strlen($token) === 64 && ctype_xdigit($token)) {
    $stmt = $pdo->prepare(
        "SELECT member_id FROM members
        WHERE verification_token = :token
        AND verification_expires > NOW()
        AND email_verified = 0"
    );
    $stmt->execute([':token' => $token]);
    $member = $stmt->fetch();

    if ($member) {
        $update = $pdo->prepare(
            "UPDATE members
            SET email_verified = 1, verification_token = NULL, verification_expires = NULL
            WHERE member_id = :id"
        );
        $update->execute([':id' => $member['member_id']]);
        $verified = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Email Verification - The Rolling Dice</title>
    <?php include_once "inc/head.inc.php"; ?>
</head>
<body>
    <?php include_once "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 text-center">
                <?php if ($verified): ?>
                    <span class="material-icons text-caramel" style="font-size: 4rem;" aria-hidden="true">verified</span>
                    <h1 class="mt-3">Email Verified!</h1>
                    <p>Your email has been verified. You can now sign in.</p>
                    <a href="login.php" class="btn btn-primary">
                        <span class="material-icons align-middle me-1" aria-hidden="true">login</span>
                        Sign In
                    </a>
                <?php else: ?>
                    <span class="material-icons text-danger" style="font-size: 4rem;" aria-hidden="true">error</span>
                    <h1 class="mt-3">Verification Failed</h1>
                    <p>Invalid or expired verification link.</p>
                    <a href="resend_verification.php" class="btn btn-outline-primary">
                        <span class="material-icons align-middle me-1" aria-hidden="true">email</span>
                        Resend Verification Email
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include_once "inc/footer.inc.php"; ?>
</body>
</html>
