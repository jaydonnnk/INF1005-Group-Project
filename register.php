<?php
/**
 * register.php — Member Registration Form
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Register - The Rolling Dice</title>
    <?php include_once "inc/head.inc.php"; ?>
</head>

<body>
    <?php include_once "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <h1>Member Registration</h1>
                <p>
                    Already a member?
                    <a href="login.php">Sign in here</a>.
                </p>

                <form action="process/process_register.php" method="post" class="needs-validation" novalidate
                    aria-label="Member registration form">

                    <?php echo csrfField(); ?>

                    <p class="text-muted small"><span class="text-danger">*</span> indicates a required field.</p>

                    <!-- First Name (optional) -->
                    <div class="mb-3">
                        <label for="fname" class="form-label">First Name:</label>
                        <input type="text" id="fname" name="fname" class="form-control" maxlength="45"
                            placeholder="Enter first name (optional)" aria-describedby="fnameHelp">
                        <div id="fnameHelp" class="form-text">Optional — some people have only one name.</div>
                    </div>

                    <!-- Last Name (required) -->
                    <div class="mb-3">
                        <label for="lname" class="form-label">Last Name: <span class="text-danger">*</span></label>
                        <input type="text" id="lname" name="lname" class="form-control" maxlength="45"
                            placeholder="Enter last name" required>
                        <div class="invalid-feedback">Please enter your last name.</div>
                    </div>

                    <!-- Email (required) -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email: <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" maxlength="100"
                            placeholder="Enter email address" required>
                        <div class="form-text">Use Gmail, Yahoo, Outlook, or your school email.</div>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>

                    <!-- Phone (optional) -->
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone:</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <select id="country_code" name="country_code" class="form-select" aria-label="Country code">
                                    <option value="+65" selected>+65 (SG)</option>
                                    <option value="+60">+60 (MY)</option>
                                    <option value="+62">+62 (ID)</option>
                                    <option value="+63">+63 (PH)</option>
                                    <option value="+66">+66 (TH)</option>
                                    <option value="+91">+91 (IN)</option>
                                    <option value="+44">+44 (UK)</option>
                                    <option value="+1">+1 (US/CA)</option>
                                    <option value="+61">+61 (AU)</option>
                                    <option value="+81">+81 (JP)</option>
                                    <option value="+82">+82 (KR)</option>
                                    <option value="+86">+86 (CN)</option>
                                </select>
                            </div>
                            <div class="col-8">
                                <input type="text" id="phone_number" name="phone_number" class="form-control"
                                    inputmode="numeric" pattern="[0-9]+" maxlength="15"
                                    placeholder="e.g. 91234567">
                                <div class="invalid-feedback" id="phoneInvalidFeedback">Invalid phone number.</div>
                            </div>
                        </div>
                        <div class="form-text" id="phoneFeedback"></div>
                    </div>

                    <!-- Password (required) -->
                    <div class="mb-3">
                        <label for="pwd" class="form-label">Password: <span class="text-danger">*</span></label>
                        <input type="password" id="pwd" name="pwd" class="form-control" minlength="12"
                            pattern="(?=.*[A-Z])(?=.*[!@#$%^&amp;*()_+\-=\[\]{};':&quot;\\|,.&lt;&gt;\/?]).{12,}"
                            title="At least 12 characters, 1 uppercase letter, and 1 special character"
                            placeholder="Minimum 12 characters" required aria-describedby="pwdHelp">
                        <div id="pwdHelp" class="form-text">Must be at least 12 characters, with at least 1 uppercase letter and 1 special character</div>
                        <div class="invalid-feedback">Password must meet the requirements above.</div>
                    </div>

                    <!-- Confirm Password (required) -->
                    <div class="mb-3">
                        <label for="pwd_confirm" class="form-label">Confirm Password: <span
                                class="text-danger">*</span></label>
                        <input type="password" id="pwd_confirm" name="pwd_confirm" class="form-control" minlength="12"
                            placeholder="Re-enter your password" required>
                        <div class="invalid-feedback">Passwords do not match.</div>
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

    <?php include_once "inc/footer.inc.php"; ?>

    <script>
    (() => {
        const code = document.getElementById('country_code');
        const phone = document.getElementById('phone_number');
        const feedback = document.getElementById('phoneFeedback');
        if (!code || !phone) return;

        function validatePhone() {
            const cc = code.value;
            const num = phone.value.trim();
            if (!num) { feedback.textContent = ''; phone.classList.remove('is-invalid', 'is-valid'); return; }
            if (!/^\d+$/.test(num)) { show('Only digits allowed.', false); return; }
            let valid = false, msg = '';
            if (cc === '+65') {
                valid = num.length === 8 && /^[689]/.test(num);
                msg = valid ? 'Valid Singapore number.' : 'SG numbers: 8 digits starting with 6, 8, or 9.';
            } else if (cc === '+60') {
                valid = num.length >= 9 && num.length <= 10;
                msg = valid ? 'Valid Malaysia number.' : 'MY numbers: 9-10 digits.';
            } else if (cc === '+1') {
                valid = num.length === 10;
                msg = valid ? 'Valid US/Canada number.' : 'US/CA numbers: exactly 10 digits.';
            } else {
                valid = num.length >= 7 && num.length <= 15;
                msg = valid ? 'Valid number.' : 'Enter 7-15 digits.';
            }
            show(msg, valid);
        }

        function show(msg, valid) {
            feedback.textContent = msg;
            feedback.style.color = valid ? 'var(--color-sage)' : 'var(--color-berry)';
            phone.classList.toggle('is-valid', valid);
            phone.classList.toggle('is-invalid', !valid);
        }

        code.addEventListener('change', validatePhone);
        phone.addEventListener('input', validatePhone);
    })();
    </script>
</body>

</html>
