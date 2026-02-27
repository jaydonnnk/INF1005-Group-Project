<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>
<body>
    <?php include "inc/nav.inc.php"; ?>

    <main class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <h1>Member Registration</h1>
                <p>
                    Already a member?
                    <a href="login.php">Sign in here</a>.
                </p>

                <form action="process/process_register.php" method="post"
                      class="needs-validation" novalidate
                      aria-label="Member registration form">

                    <!-- First Name (optional) -->
                    <div class="mb-3">
                        <label for="fname" class="form-label">First Name:</label>
                        <input type="text" id="fname" name="fname"
                               class="form-control" maxlength="45"
                               placeholder="Enter first name (optional)"
                               aria-describedby="fnameHelp">
                        <div id="fnameHelp" class="form-text">Optional — some people have only one name.</div>
                    </div>

                    <!-- Last Name (required) -->
                    <div class="mb-3">
                        <label for="lname" class="form-label">Last Name: <span class="text-danger">*</span></label>
                        <input type="text" id="lname" name="lname"
                               class="form-control" maxlength="45"
                               placeholder="Enter last name" required>
                        <div class="invalid-feedback">Please enter your last name.</div>
                    </div>

                    <!-- Email (required) -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email: <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email"
                               class="form-control" maxlength="100"
                               placeholder="Enter email address" required>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>

                    <!-- Phone (optional) -->
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone:</label>
                        <input type="tel" id="phone" name="phone"
                               class="form-control" maxlength="20"
                               placeholder="e.g. +65 9123 4567">
                    </div>

                    <!-- Password (required) -->
                    <div class="mb-3">
                        <label for="pwd" class="form-label">Password: <span class="text-danger">*</span></label>
                        <input type="password" id="pwd" name="pwd"
                               class="form-control" minlength="8"
                               placeholder="Minimum 8 characters" required
                               aria-describedby="pwdHelp">
                        <div id="pwdHelp" class="form-text">Must be at least 8 characters long.</div>
                        <div class="invalid-feedback">Password must be at least 8 characters.</div>
                    </div>

                    <!-- Confirm Password (required) -->
                    <div class="mb-3">
                        <label for="pwd_confirm" class="form-label">Confirm Password: <span class="text-danger">*</span></label>
                        <input type="password" id="pwd_confirm" name="pwd_confirm"
                               class="form-control" minlength="8"
                               placeholder="Re-enter your password" required>
                        <div class="invalid-feedback">Passwords do not match.</div>
                    </div>

                    <!-- Terms & Conditions Checkbox (required) -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="agree" id="agree"
                               class="form-check-input" required>
                        <label class="form-check-label" for="agree">
                            I agree to the <a href="#" target="_blank">Terms and Conditions</a>.
                            <span class="text-danger">*</span>
                        </label>
                        <div class="invalid-feedback">You must agree before submitting.</div>
                    </div>

                    <!-- Submit Button -->
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <span class="material-icons align-middle me-1" aria-hidden="true">person_add</span>
                            Create Account
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>
</html>
