<?php
session_start();
if (!isset($_SESSION["member_id"])) {
    header("Location: login.php");
    exit();
}
$member_id = $_SESSION["member_id"];
require_once "process/db.php";

$type = $_GET["type"] ?? "";
$error = "";
$receipt = null;

if ($type === 'order') {
    $order_id = (int) ($_GET["order_id"] ?? 0);

    // Fetch order belonging to this member
    $stmt = $pdo->prepare(
        "SELECT o.*, m.fname, m.lname, m.email
         FROM orders o
         JOIN members m ON o.member_id = m.member_id
         WHERE o.order_id = :oid AND o.member_id = :mid"
    );
    $stmt->execute([':oid' => $order_id, ':mid' => $member_id]);
    $order = $stmt->fetch();

    if (!$order) {
        $error = "Order not found.";
    } else {
        // Fetch order items
        $items_stmt = $pdo->prepare(
            "SELECT oi.quantity, oi.subtotal, mi.name, mi.price
             FROM order_items oi
             JOIN menu_items mi ON oi.item_id = mi.item_id
             WHERE oi.order_id = :oid"
        );
        $items_stmt->execute([':oid' => $order_id]);
        $items = $items_stmt->fetchAll();

        // Fetch payment record
        $pay_stmt = $pdo->prepare(
            "SELECT stripe_session_id, created_at FROM payments
             WHERE payment_type = 'order' AND reference_id = :ref AND member_id = :mid
             ORDER BY created_at DESC LIMIT 1"
        );
        $pay_stmt->execute([':ref' => $order_id, ':mid' => $member_id]);
        $payment = $pay_stmt->fetch();

        $receipt = [
            'type' => 'order',
            'order' => $order,
            'items' => $items,
            'payment' => $payment,
        ];
    }

} elseif ($type === 'booking') {
    $booking_id = (int) ($_GET["booking_id"] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT b.*, g.title AS game_title, g.price_per_hour, m.fname, m.lname, m.email
         FROM bookings b
         LEFT JOIN games g ON b.game_id = g.game_id
         JOIN members m ON b.member_id = m.member_id
         WHERE b.booking_id = :bid AND b.member_id = :mid"
    );
    $stmt->execute([':bid' => $booking_id, ':mid' => $member_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $error = "Booking not found.";
    } else {
        // Fetch payment record
        $pay_stmt = $pdo->prepare(
            "SELECT stripe_session_id, amount, created_at FROM payments
             WHERE payment_type = 'booking' AND reference_id = :ref AND member_id = :mid
             ORDER BY created_at DESC LIMIT 1"
        );
        $pay_stmt->execute([':ref' => $booking_id, ':mid' => $member_id]);
        $payment = $pay_stmt->fetch();

        $receipt = [
            'type' => 'booking',
            'booking' => $booking,
            'payment' => $payment,
        ];
    }
} else {
    $error = "Invalid receipt type.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Receipt - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>
<body>
    <?php include "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <?php if ($error): ?>
                    <div class="text-center">
                        <span class="material-icons text-danger" style="font-size:3rem;" aria-hidden="true">error</span>
                        <h1 class="mt-3">Receipt Not Found</h1>
                        <p class="text-muted"><?php echo htmlspecialchars($error); ?></p>
                        <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
                    </div>

                <?php elseif ($receipt['type'] === 'order'): ?>
                    <?php $o = $receipt['order']; $items = $receipt['items']; $pay = $receipt['payment']; ?>

                    <div class="card" id="receipt-card">
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <h1 class="h4">The Rolling Dice</h1>
                                <p class="text-muted small mb-0">123 Dice Lane, #01-01, Singapore 123456</p>
                                <p class="text-muted small">hello@therollingdice.sg</p>
                            </div>

                            <hr>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <strong>Order #<?php echo $o['order_id']; ?></strong><br>
                                    <span class="text-muted small"><?php echo date('d M Y, g:i A', strtotime($o['order_date'])); ?></span>
                                </div>
                                <div class="col-6 text-end">
                                    <strong><?php echo htmlspecialchars($o['fname'] . ' ' . $o['lname']); ?></strong><br>
                                    <span class="text-muted small"><?php echo htmlspecialchars($o['email']); ?></span>
                                </div>
                            </div>

                            <table class="table table-sm" aria-label="Order items receipt">
                                <thead>
                                    <tr>
                                        <th scope="col">Item</th>
                                        <th scope="col" class="text-center">Qty</th>
                                        <th scope="col" class="text-end">Unit Price</th>
                                        <th scope="col" class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end">$<?php echo number_format($item['price'], 2); ?></td>
                                            <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Total:</td>
                                        <td class="text-end fw-bold text-caramel">$<?php echo number_format($o['total_amount'], 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>

                            <hr>

                            <?php if ($pay): ?>
                                <p class="small text-muted mb-1">
                                    <strong>Payment Reference:</strong> <?php echo htmlspecialchars($pay['stripe_session_id']); ?>
                                </p>
                                <p class="small text-muted mb-0">
                                    <strong>Paid on:</strong> <?php echo date('d M Y, g:i A', strtotime($pay['created_at'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-center mt-4 no-print">
                        <button onclick="window.print()" class="btn btn-outline-primary me-2">
                            <span class="material-icons align-middle me-1" aria-hidden="true">print</span>Print Receipt
                        </button>
                        <a href="orders.php" class="btn btn-primary">Back to Orders</a>
                    </div>

                <?php elseif ($receipt['type'] === 'booking'): ?>
                    <?php $b = $receipt['booking']; $pay = $receipt['payment']; ?>

                    <div class="card" id="receipt-card">
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <h1 class="h4">The Rolling Dice</h1>
                                <p class="text-muted small mb-0">123 Dice Lane, #01-01, Singapore 123456</p>
                                <p class="text-muted small">hello@therollingdice.sg</p>
                            </div>

                            <hr>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <strong>Booking #<?php echo $b['booking_id']; ?></strong><br>
                                    <span class="text-muted small"><?php echo date('d M Y', strtotime($b['booking_date'])); ?></span>
                                </div>
                                <div class="col-6 text-end">
                                    <strong><?php echo htmlspecialchars($b['fname'] . ' ' . $b['lname']); ?></strong><br>
                                    <span class="text-muted small"><?php echo htmlspecialchars($b['email']); ?></span>
                                </div>
                            </div>

                            <table class="table table-sm" aria-label="Booking receipt details">
                                <tbody>
                                    <tr><th scope="row">Date</th><td><?php echo date('d M Y', strtotime($b['booking_date'])); ?></td></tr>
                                    <tr><th scope="row">Time Slot</th><td><?php echo htmlspecialchars($b['time_slot']); ?></td></tr>
                                    <tr><th scope="row">Party Size</th><td><?php echo $b['party_size']; ?> pax</td></tr>
                                    <tr><th scope="row">Game</th><td><?php echo $b['game_title'] ? htmlspecialchars($b['game_title']) : 'None selected'; ?></td></tr>
                                    <tr><th scope="row">Rental Hours</th><td><?php echo $b['rental_hours']; ?> hr(s)</td></tr>
                                </tbody>
                            </table>

                            <table class="table table-sm" aria-label="Booking cost breakdown">
                                <tbody>
                                    <?php if ($b['game_title']): ?>
                                    <tr>
                                        <td>Board Game Rental (<?php echo $b['rental_hours']; ?> hr x $<?php echo number_format($b['price_per_hour'], 2); ?>)</td>
                                        <td class="text-end">$<?php echo number_format($b['rental_hours'] * $b['price_per_hour'], 2); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="fw-bold">Total Paid</td>
                                        <td class="text-end fw-bold text-caramel">
                                            $<?php echo $pay ? number_format($pay['amount'], 2) : '—'; ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>

                            <hr>

                            <?php if ($pay): ?>
                                <p class="small text-muted mb-1">
                                    <strong>Payment Reference:</strong> <?php echo htmlspecialchars($pay['stripe_session_id']); ?>
                                </p>
                                <p class="small text-muted mb-0">
                                    <strong>Paid on:</strong> <?php echo date('d M Y, g:i A', strtotime($pay['created_at'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-center mt-4 no-print">
                        <button onclick="window.print()" class="btn btn-outline-primary me-2">
                            <span class="material-icons align-middle me-1" aria-hidden="true">print</span>Print Receipt
                        </button>
                        <a href="bookings.php" class="btn btn-primary">Back to Bookings</a>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>
</html>
