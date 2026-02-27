<?php
// Start session if not already started (needed for login state)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION["member_id"]);
?>

<nav class="navbar navbar-expand-md sticky-top" data-bs-theme="dark" aria-label="Main navigation">
    <div class="container">
        <!-- Brand / Logo -->
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <span class="material-icons me-2" aria-hidden="true">casino</span>
            <span class="brand-text">The Rolling Dice</span>
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Collapsible Menu -->
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-md-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <span class="material-icons align-middle me-1" aria-hidden="true">home</span>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="games.php">
                        <span class="material-icons align-middle me-1" aria-hidden="true">extension</span>Games
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="menu.php">
                        <span class="material-icons align-middle me-1" aria-hidden="true">restaurant</span>Menu
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php">
                        <span class="material-icons align-middle me-1" aria-hidden="true">info</span>About
                    </a>
                </li>
            </ul>

            <!-- Right-side Auth Links -->
            <ul class="navbar-nav ms-auto mb-2 mb-md-0">
                <?php if ($is_logged_in): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <span class="material-icons align-middle me-1" aria-hidden="true">dashboard</span>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="process/process_logout.php">
                            <span class="material-icons align-middle me-1" aria-hidden="true">logout</span>Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <span class="material-icons align-middle me-1" aria-hidden="true">login</span>Sign In
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <span class="material-icons align-middle me-1" aria-hidden="true">person_add</span>Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
