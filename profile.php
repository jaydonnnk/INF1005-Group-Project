<?php
/**
 * Member Profile Page
 * The Rolling Dice - Board Game Café
 *
 * Allows logged-in members to update their name, email, phone,
 * and change their password (with current password verification).
 */

session_start();
if (!isset($_SESSION["member_id"])) {
    header("Location: login.php");
    exit();
}

$member_id = $_SESSION["member_id"];
require_once "process/db.php";

// Fetch current member data
$stmt = $pdo->prepare(
    "SELECT fname, lname, email, phone, totp_enabled FROM members WHERE member_id = :id"
);
$stmt->execute([':id' => $member_id]);
$member = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>My Profile - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>

<body>
    <?php include "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>My Profile</h1>
                <p class="text-muted mb-0">Update your personal details or change your password.</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <span class="material-icons align-middle me-1" aria-hidden="true">dashboard</span>Dashboard
            </a>
        </div>

        <?php echo displayFlash(); ?>

        <div class="row g-4">

            <!-- ── Personal Details ── -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3">
                            <span class="material-icons align-middle text-caramel me-2"
                                aria-hidden="true">person</span>Personal Details
                        </h2>

                        <form action="process/process_profile.php" method="post"
                            class="needs-validation" novalidate
                            aria-label="Update personal details form">

                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="update_profile">

                            <p class="text-muted small">
                                <span class="text-danger">*</span> indicates a required field.
                            </p>

                            <!-- First Name -->
                            <div class="mb-3">
                                <label for="fname" class="form-label">First Name:</label>
                                <input type="text" id="fname" name="fname" class="form-control"
                                    maxlength="45"
                                    value="<?php echo htmlspecialchars($member['fname'] ?? ''); ?>"
                                    placeholder="First name (optional)">
                            </div>

                            <!-- Last Name -->
                            <div class="mb-3">
                                <label for="lname" class="form-label">
                                    Last Name: <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="lname" name="lname" class="form-control"
                                    maxlength="45"
                                    value="<?php echo htmlspecialchars($member['lname']); ?>"
                                    placeholder="Last name" required>
                                <div class="invalid-feedback">Last name is required.</div>
                            </div>

                            <!-- Email -->
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    Email: <span class="text-danger">*</span>
                                </label>
                                <input type="email" id="email" name="email" class="form-control"
                                    maxlength="100"
                                    value="<?php echo htmlspecialchars($member['email']); ?>"
                                    placeholder="Email address" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>

                            <!-- Phone -->
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone:</label>
                                <input type="tel" id="phone" name="phone" class="form-control"
                                    maxlength="20"
                                    value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>"
                                    placeholder="e.g. +65 9123 4567">
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <span class="material-icons align-middle me-1"
                                    aria-hidden="true">save</span>Save Changes
                            </button>

                        </form>
                    </div>
                </div>
            </div>

            <!-- ── Change Password ── -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3">
                            <span class="material-icons align-middle text-caramel me-2"
                                aria-hidden="true">lock</span>Change Password
                        </h2>

                        <form action="process/process_profile.php" method="post"
                            class="needs-validation" novalidate
                            aria-label="Change password form">

                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="change_password">

                            <p class="text-muted small">
                                <span class="text-danger">*</span> indicates a required field.
                            </p>

                            <!-- Current Password -->
                            <div class="mb-3">
                                <label for="current_pwd" class="form-label">
                                    Current Password: <span class="text-danger">*</span>
                                </label>
                                <input type="password" id="current_pwd" name="current_pwd"
                                    class="form-control"
                                    placeholder="Enter current password" required>
                                <div class="invalid-feedback">Please enter your current password.</div>
                            </div>

                            <!-- New Password -->
                            <div class="mb-3">
                                <label for="new_pwd" class="form-label">
                                    New Password: <span class="text-danger">*</span>
                                </label>
                                <input type="password" id="new_pwd" name="new_pwd"
                                    class="form-control" minlength="8"
                                    placeholder="Minimum 8 characters" required>
                                <div class="form-text">Must be at least 8 characters.</div>
                                <div class="invalid-feedback">Password must be at least 8 characters.</div>
                            </div>

                            <!-- Confirm New Password -->
                            <div class="mb-3">
                                <label for="pwd_confirm" class="form-label">
                                    Confirm New Password: <span class="text-danger">*</span>
                                </label>
                                <input type="password" id="pwd_confirm" name="pwd_confirm"
                                    class="form-control" minlength="8"
                                    placeholder="Re-enter new password" required>
                                <div class="invalid-feedback">Passwords do not match.</div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <span class="material-icons align-middle me-1"
                                    aria-hidden="true">lock_reset</span>Update Password
                            </button>

                        </form>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Two-Factor Authentication ── -->
        <div class="row g-4 mt-1">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5 mb-3">
                            <span class="material-icons align-middle text-caramel me-2"
                                aria-hidden="true">security</span>Two-Factor Authentication
                        </h2>

                        <?php if (empty($member['totp_enabled'])): ?>
                            <p class="mb-2">
                                <span class="badge bg-secondary">Disabled</span>
                            </p>
                            <p>Add an extra layer of security to your account. Use an authenticator app like Google Authenticator or Authy.</p>
                            <a href="setup_2fa.php" class="btn btn-primary">
                                <span class="material-icons align-middle me-1" aria-hidden="true">lock</span>Enable 2FA
                            </a>
                        <?php else: ?>
                            <p class="mb-2">
                                <span class="badge bg-success">Enabled</span>
                            </p>
                            <p>Two-factor authentication is active on your account.</p>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#disable2faModal">
                                <span class="material-icons align-middle me-1" aria-hidden="true">lock_open</span>Disable 2FA
                            </button>

                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($member['totp_enabled'])): ?>
        <!-- Disable 2FA Confirmation Modal -->
        <div class="modal fade" id="disable2faModal" tabindex="-1" aria-labelledby="disable2faModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="process/process_disable_2fa.php" method="post">
                        <?php echo csrfField(); ?>
                        <div class="modal-header">
                            <h5 class="modal-title" id="disable2faModalLabel">Disable Two-Factor Authentication</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to disable two-factor authentication? This will make your account less secure.</p>
                            <div class="mb-3">
                                <label for="disable_pwd" class="form-label">Enter your current password to confirm:</label>
                                <input type="password" id="disable_pwd" name="current_pwd" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Disable 2FA</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>

</html>
