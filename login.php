<!DOCTYPE html>
<html lang="en">

<head>
    <title>Sign In - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>

<body>
    <?php include "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">

                <h1>Sign In</h1>
                <p>
                    Not a member yet?
                    <a href="register.php">Register here</a>.
                </p>

                <?php echo displayFlash(); ?>

                <form action="process/process_login.php" method="post" class="needs-validation" novalidate
                    aria-label="Member sign-in form">

                    <?php echo csrfField(); ?>

                    <p class="text-muted small"><span class="text-danger">*</span> indicates a required field.</p>

                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email: <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" maxlength="100"
                            placeholder="Enter your email" required>
                        <div class="invalid-feedback">Please enter your email address.</div>
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label for="pwd" class="form-label">Password: <span class="text-danger">*</span></label>
                        <input type="password" id="pwd" name="pwd" class="form-control"
                            placeholder="Enter your password" required>
                        <div class="invalid-feedback">Please enter your password.</div>
                    </div>

                    <!-- Submit -->
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <span class="material-icons align-middle me-1" aria-hidden="true">login</span>
                            Sign In
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>

</html>