<?php
require_once "auth_check.php";
require_once __DIR__ . "/../process/db.php";

$total_members = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$total_reviews = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$total_games = $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
$total_menu = $pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="../">
    <title>Admin Panel - The Rolling Dice</title>
    <?php include_once __DIR__ . "/../inc/head.inc.php"; ?>
</head>
<body>
    <?php include_once __DIR__ . "/../inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <h1>Admin Panel</h1>
        <p class="text-muted mb-4">Manage games, menu, bookings, orders, and members.</p>

        <?php echo displayFlash(); ?>

        <!-- Stats -->
        <div class="row g-4 mb-5">
            <div class="col-sm-6 col-lg-4">
                <a href="admin/members.php" class="text-decoration-none">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">people</span>
                        <h3><?php echo (int)$total_members; ?></h3>
                        <p class="text-muted mb-0">Members</p>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-4">
                <a href="admin/games.php" class="text-decoration-none">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">extension</span>
                        <h3><?php echo (int)$total_games; ?></h3>
                        <p class="text-muted mb-0">Games</p>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-4">
                <a href="admin/menu.php" class="text-decoration-none">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">restaurant</span>
                        <h3><?php echo (int)$total_menu; ?></h3>
                        <p class="text-muted mb-0">Menu Items</p>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-4">
                <a href="admin/bookings.php" class="text-decoration-none">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">event</span>
                        <h3><?php echo (int)$total_bookings; ?></h3>
                        <p class="text-muted mb-0">Bookings</p>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-4">
                <a href="admin/orders.php" class="text-decoration-none">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">receipt_long</span>
                        <h3><?php echo (int)$total_orders; ?></h3>
                        <p class="text-muted mb-0">Orders</p>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="dashboard-card">
                    <span class="material-icons" aria-hidden="true">rate_review</span>
                    <h3><?php echo (int)$total_reviews; ?></h3>
                    <p class="text-muted mb-0">Reviews</p>
                </div>
            </div>
        </div>

    </main>

    <?php include_once __DIR__ . "/../inc/footer.inc.php"; ?>
</body>
</html>
