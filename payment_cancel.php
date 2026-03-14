<?php
session_start();
$type = $_GET["type"] ?? "booking";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Payment Cancelled - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>
<body>
    <?php include "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 text-center">
                <span class="material-icons text-warning" style="font-size:4rem;" aria-hidden="true">cancel</span>
                <h1 class="mt-3">Payment Cancelled</h1>
                <p class="text-muted">Your payment was cancelled. No charges were made.</p>

                <div class="mt-4">
                    <?php if ($type === 'order'): ?>
                        <a href="orders.php" class="btn btn-primary">
                            <span class="material-icons align-middle me-1" aria-hidden="true">shopping_bag</span>Back to Orders
                        </a>
                    <?php else: ?>
                        <a href="bookings.php?action=new" class="btn btn-primary">
                            <span class="material-icons align-middle me-1" aria-hidden="true">event</span>Try Again
                        </a>
                        <a href="bookings.php" class="btn btn-outline-primary ms-2">My Bookings</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>
</html>
