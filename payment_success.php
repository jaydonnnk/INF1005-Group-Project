<?php
session_start();
if (!isset($_SESSION["member_id"])) {
    header("Location: login.php");
    exit();
}
$member_id = $_SESSION["member_id"];

require_once "process/stripe_config.php";
require_once "process/db.php";

$session_id = $_GET["session_id"] ?? "";
$error = "";
$success_type = "";
$booking_summary = null;
$order_summary = null;

if (empty($session_id)) {
    $error = "Missing payment session.";
} else {
    try {
        // Idempotency check: prevent duplicate processing on page refresh
        $dup_stmt = $pdo->prepare("SELECT payment_id FROM payments WHERE stripe_session_id = :sid LIMIT 1");
        $dup_stmt->execute([':sid' => $session_id]);
        if ($dup_stmt->fetch()) {
            $error = "This payment has already been processed. Check your bookings or orders.";
        } else {
            // Retrieve the Stripe session to verify payment
            $session = \Stripe\Checkout\Session::retrieve($session_id);

            if ($session->payment_status !== 'paid') {
                $error = "Payment was not completed.";
            } else {
                $checkout_type = $session->metadata->checkout_type ?? "";
                $amount_paid = $session->amount_total / 100; // Convert from cents

                if ($checkout_type === 'booking' && isset($_SESSION['pending_booking'])) {
                    // ---- BOOKING: Save to database (wrapped in transaction) ----
                    $b = $_SESSION['pending_booking'];

                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        "INSERT INTO bookings (member_id, booking_date, time_slot, party_size, game_id, rental_hours, notes)
                        VALUES (:mid, :date, :slot, :size, :gid, :hours, :notes)"
                    );
                    $stmt->execute([
                        ':mid' => $member_id,
                        ':date' => $b['booking_date'],
                        ':slot' => $b['time_slot'],
                        ':size' => $b['party_size'],
                        ':gid' => $b['game_id'],
                        ':hours' => $b['rental_hours'],
                        ':notes' => $b['notes'],
                    ]);
                    $booking_id = $pdo->lastInsertId();

                    // Insert payment record
                    $pay_stmt = $pdo->prepare(
                        "INSERT INTO payments (member_id, stripe_session_id, amount, currency, payment_type, reference_id, status)
                        VALUES (:mid, :sid, :amt, 'sgd', 'booking', :ref, 'completed')"
                    );
                    $pay_stmt->execute([
                        ':mid' => $member_id,
                        ':sid' => $session_id,
                        ':amt' => $amount_paid,
                        ':ref' => $booking_id,
                    ]);

                    $pdo->commit();

                    // Fetch game title for summary
                    $game_title = "None selected";
                    if ($b['game_id']) {
                        $g_stmt = $pdo->prepare("SELECT title FROM games WHERE game_id = :gid");
                        $g_stmt->execute([':gid' => $b['game_id']]);
                        $g = $g_stmt->fetch();
                        if ($g) $game_title = $g['title'];
                    }

                    $booking_summary = [
                        'booking_id' => $booking_id,
                        'date' => date('d M Y', strtotime($b['booking_date'])),
                        'time_slot' => $b['time_slot'],
                        'party_size' => $b['party_size'],
                        'game' => $game_title,
                        'rental_hours' => $b['rental_hours'],
                        'amount' => $amount_paid,
                    ];

                    unset($_SESSION['pending_booking']);
                    $success_type = 'booking';

                } elseif ($checkout_type === 'order' && isset($_SESSION['pending_order_id'])) {
                    // ---- ORDER: Update status (wrapped in transaction) ----
                    $order_id = $_SESSION['pending_order_id'];

                    // Verify this order belongs to the member
                    $o_stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :oid AND member_id = :mid");
                    $o_stmt->execute([':oid' => $order_id, ':mid' => $member_id]);
                    $order = $o_stmt->fetch();

                    if ($order) {
                        $pdo->beginTransaction();

                        // Update order status to Preparing
                        $upd = $pdo->prepare("UPDATE orders SET status = 'Preparing' WHERE order_id = :oid");
                        $upd->execute([':oid' => $order_id]);

                        // Insert payment record
                        $pay_stmt = $pdo->prepare(
                            "INSERT INTO payments (member_id, stripe_session_id, amount, currency, payment_type, reference_id, status)
                            VALUES (:mid, :sid, :amt, 'sgd', 'order', :ref, 'completed')"
                        );
                        $pay_stmt->execute([
                            ':mid' => $member_id,
                            ':sid' => $session_id,
                            ':amt' => $amount_paid,
                            ':ref' => $order_id,
                        ]);

                        $pdo->commit();

                        $order_summary = [
                            'order_id' => $order_id,
                            'amount' => $amount_paid,
                        ];

                        $success_type = 'order';
                    } else {
                        $error = "Order not found.";
                    }

                    // Only clear session after successful processing or order-not-found
                    unset($_SESSION['pending_order_id']);

                } else {
                    $error = "Session data expired. Your payment was processed — please check your bookings or orders.";
                }
            }
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Stripe verification error: " . $e->getMessage());
        $error = "Could not verify payment. Please contact us if you were charged.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Payment processing error: " . $e->getMessage());
        $error = "An error occurred while processing your payment. Please contact us.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Payment <?php echo $error ? 'Error' : 'Success'; ?> - The Rolling Dice</title>
    <?php include_once "inc/head.inc.php"; ?>
</head>
<body>
    <?php include_once "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <?php if ($error): ?>
                    <div class="text-center">
                        <span class="material-icons text-danger" style="font-size:4rem;" aria-hidden="true">error</span>
                        <h1 class="mt-3">Payment Issue</h1>
                        <p class="text-muted"><?php echo htmlspecialchars($error); ?></p>
                        <a href="bookings.php" class="btn btn-primary me-2">My Bookings</a>
                        <a href="orders.php" class="btn btn-outline-primary">My Orders</a>
                    </div>

                <?php elseif ($success_type === 'booking' && $booking_summary): ?>
                    <div class="text-center mb-4">
                        <span class="material-icons text-success" style="font-size:4rem;" aria-hidden="true">check_circle</span>
                        <h1 class="mt-3">Booking Confirmed!</h1>
                        <p class="text-muted">Your payment of <strong>$<?php echo number_format($booking_summary['amount'], 2); ?> SGD</strong> was successful.</p>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Booking Summary</h2>
                            <table class="table table-sm mb-0">
                                <tr><th scope="row">Booking ID</th><td>#<?php echo $booking_summary['booking_id']; ?></td></tr>
                                <tr><th scope="row">Date</th><td><?php echo htmlspecialchars($booking_summary['date']); ?></td></tr>
                                <tr><th scope="row">Time Slot</th><td><?php echo htmlspecialchars($booking_summary['time_slot']); ?></td></tr>
                                <tr><th scope="row">Party Size</th><td><?php echo $booking_summary['party_size']; ?></td></tr>
                                <tr><th scope="row">Game</th><td><?php echo htmlspecialchars($booking_summary['game']); ?></td></tr>
                                <tr><th scope="row">Rental Hours</th><td><?php echo $booking_summary['rental_hours']; ?> hr(s)</td></tr>
                                <tr><th scope="row">Total Paid</th><td class="fw-bold text-caramel">$<?php echo number_format($booking_summary['amount'], 2); ?></td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="receipt.php?type=booking&booking_id=<?php echo $booking_summary['booking_id']; ?>" class="btn btn-outline-primary me-2">
                            <span class="material-icons align-middle me-1" aria-hidden="true">receipt</span>View Receipt
                        </a>
                        <a href="bookings.php" class="btn btn-primary">My Bookings</a>
                    </div>

                <?php elseif ($success_type === 'order' && $order_summary): ?>
                    <div class="text-center mb-4">
                        <span class="material-icons text-success" style="font-size:4rem;" aria-hidden="true">check_circle</span>
                        <h1 class="mt-3">Order Placed!</h1>
                        <p class="text-muted">Your payment of <strong>$<?php echo number_format($order_summary['amount'], 2); ?> SGD</strong> was successful. We're preparing your order now.</p>
                    </div>

                    <div class="text-center mt-4">
                        <a href="receipt.php?type=order&order_id=<?php echo $order_summary['order_id']; ?>" class="btn btn-outline-primary me-2">
                            <span class="material-icons align-middle me-1" aria-hidden="true">receipt</span>View Receipt
                        </a>
                        <a href="orders.php" class="btn btn-primary">My Orders</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <?php include_once "inc/footer.inc.php"; ?>
</body>
</html>
