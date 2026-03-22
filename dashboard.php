<?php
session_start();
require_once "process/helpers.php";

if (!isset($_SESSION["member_id"])) {
    header("Location: " . Routes::ROOT_LOGIN);
    exit();
}
$member_name = htmlspecialchars($_SESSION["member_name"]);
$member_id = $_SESSION["member_id"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Dashboard - The Rolling Dice</title>
    <?php include_once "inc/head.inc.php"; ?>
</head>

<body>
    <?php include_once "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">

        <h1>Welcome back, <?php echo $member_name; ?>!</h1>
        <p class="lead">Manage your bookings, orders, reviews, and wishlist from here.</p>

        <?php echo displayFlash(); ?>

        <!-- Dashboard Quick Stats -->
        <?php
        try {
            require_once "process/db.php";

            $booking_count = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE member_id = :id AND status = 'Confirmed'");
            $booking_count->execute([':id' => $member_id]);
            $bookings = $booking_count->fetchColumn();

            $order_count = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE member_id = :id AND status IN ('Pending','Preparing')");
            $order_count->execute([':id' => $member_id]);
            $orders = $order_count->fetchColumn();

            $review_count = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE member_id = :id");
            $review_count->execute([':id' => $member_id]);
            $reviews = $review_count->fetchColumn();

            $wishlist_count = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE member_id = :id");
            $wishlist_count->execute([':id' => $member_id]);
            $wishlists = $wishlist_count->fetchColumn();
        } catch (Exception $e) {
            $bookings = $orders = $reviews = $wishlists = 0;
        }
        ?>

        <div class="row g-4 mb-5">
            <div class="col-sm-6 col-lg-3">
                <a href="bookings.php" class="text-decoration-none"
                    aria-label="Bookings: <?php echo $bookings; ?> active">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">event</span>
                        <h2>Bookings</h2>
                        <p class="fs-3 fw-bold text-caramel mb-0"><?php echo $bookings; ?></p>
                        <p class="text-muted">active</p>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="orders.php" class="text-decoration-none"
                    aria-label="Orders: <?php echo $orders; ?> in progress">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">receipt_long</span>
                        <h2>Orders</h2>
                        <p class="fs-3 fw-bold text-caramel mb-0"><?php echo $orders; ?></p>
                        <p class="text-muted">in progress</p>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="reviews.php" class="text-decoration-none"
                    aria-label="Reviews: <?php echo $reviews; ?> written">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">rate_review</span>
                        <h2>Reviews</h2>
                        <p class="fs-3 fw-bold text-caramel mb-0"><?php echo $reviews; ?></p>
                        <p class="text-muted">written</p>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="wishlist.php" class="text-decoration-none"
                    aria-label="Wishlist: <?php echo $wishlists; ?> games saved">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">favorite</span>
                        <h2>Wishlist</h2>
                        <p class="fs-3 fw-bold text-caramel mb-0"><?php echo $wishlists; ?></p>
                        <p class="text-muted">games saved</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Quick Actions -->
        <section>
            <h2>Quick Actions</h2>
            <div class="d-flex flex-wrap gap-3">
                <a href="bookings.php?action=new" class="btn btn-primary">
                    <span class="material-icons align-middle me-1" aria-hidden="true">add</span>New Booking
                </a>
                <a href="menu.php" class="btn btn-outline-primary">
                    <span class="material-icons align-middle me-1" aria-hidden="true">restaurant</span>Order Food
                </a>
                <a href="games.php" class="btn btn-outline-primary">
                    <span class="material-icons align-middle me-1" aria-hidden="true">extension</span>Browse Games
                </a>
            </div>
        </section>

    </main>

    <?php include_once "inc/footer.inc.php"; ?>
</body>

</html>