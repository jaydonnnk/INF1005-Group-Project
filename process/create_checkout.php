<?php
/**
 * Create Stripe Checkout Session
 * The Rolling Dice - Board Game Café
 *
 * Handles both booking and order checkout flows.
 */

session_start();
require_once "helpers.php";
require_once "stripe_config.php";
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit();
}

if (!isset($_SESSION["member_id"])) {
    header("Location: ../login.php");
    exit();
}

validateCsrf('../index.php');

$member_id = $_SESSION["member_id"];
$checkout_type = $_POST["checkout_type"] ?? "";

try {
    switch ($checkout_type) {

        // ============================================
        // BOOKING CHECKOUT
        // ============================================
        case "booking":
            // Validate required fields
            $booking_date = $_POST["booking_date"] ?? "";
            $time_slot = sanitizeInput($_POST["time_slot"] ?? "");
            $party_size = (int) ($_POST["party_size"] ?? 2);
            $game_id = !empty($_POST["game_id"]) ? (int) $_POST["game_id"] : null;
            $rental_hours = (int) ($_POST["rental_hours"] ?? 2);
            $notes = sanitizeInput($_POST["notes"] ?? "");

            if (empty($booking_date) || empty($time_slot)) {
                setFlash('error', 'Date and time slot are required.');
                header("Location: ../bookings.php?action=new");
                exit();
            }
            if ($party_size < 1 || $party_size > 12) {
                setFlash('error', 'Party size must be between 1 and 12.');
                header("Location: ../bookings.php?action=new");
                exit();
            }
            if ($rental_hours < 1 || $rental_hours > 6) {
                setFlash('error', 'Rental hours must be between 1 and 6.');
                header("Location: ../bookings.php?action=new");
                exit();
            }

            // A game must be selected to proceed with payment
            if (!$game_id) {
                setFlash('error', 'Please select a game to book.');
                header("Location: ../bookings.php?action=new");
                exit();
            }

            // Check availability
            $avail_stmt = $pdo->prepare(
                "SELECT g.quantity - COALESCE(booked.cnt, 0) AS available_copies, g.stripe_price_id
                FROM games g
                LEFT JOIN (
                    SELECT game_id, COUNT(*) AS cnt
                    FROM bookings
                    WHERE booking_date = :date AND time_slot = :slot AND status = 'Confirmed'
                    GROUP BY game_id
                ) booked ON booked.game_id = g.game_id
                WHERE g.game_id = :gid"
            );
            $avail_stmt->execute([':date' => $booking_date, ':slot' => $time_slot, ':gid' => $game_id]);
            $game_avail = $avail_stmt->fetch();

            if (!$game_avail || $game_avail['available_copies'] <= 0) {
                setFlash('error', 'Sorry, that game is no longer available for the selected date and time.');
                header("Location: ../bookings.php?action=new");
                exit();
            }

            // Build Stripe line items — Board Game Rental x rental_hours
            $line_items = [];
            $line_items[] = [
                'price' => $game_avail['stripe_price_id'],
                'quantity' => $rental_hours,
            ];

            // Store booking details in session for payment_success.php
            $_SESSION['pending_booking'] = [
                'booking_date' => $booking_date,
                'time_slot' => $time_slot,
                'party_size' => $party_size,
                'game_id' => $game_id,
                'rental_hours' => $rental_hours,
                'notes' => $notes,
            ];

            // Create Stripe Checkout Session
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $line_items,
                'mode' => 'payment',
                'success_url' => SITE_BASE_URL . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => SITE_BASE_URL . '/payment_cancel.php?type=booking',
                'metadata' => [
                    'member_id' => $member_id,
                    'checkout_type' => 'booking',
                ],
            ]);

            header("Location: " . $session->url);
            exit();

        // ============================================
        // ORDER CHECKOUT
        // ============================================
        case "order":
            $order_id = (int) ($_POST["order_id"] ?? 0);

            // Fetch the pending order belonging to this member
            $order_stmt = $pdo->prepare(
                "SELECT * FROM orders WHERE order_id = :oid AND member_id = :mid AND status = 'Pending'"
            );
            $order_stmt->execute([':oid' => $order_id, ':mid' => $member_id]);
            $order = $order_stmt->fetch();

            if (!$order) {
                setFlash('error', 'No pending order found.');
                header("Location: ../orders.php");
                exit();
            }

            // Fetch order items with their stripe price IDs
            $items_stmt = $pdo->prepare(
                "SELECT oi.quantity, mi.stripe_price_id, mi.name
                FROM order_items oi
                JOIN menu_items mi ON oi.item_id = mi.item_id
                WHERE oi.order_id = :oid"
            );
            $items_stmt->execute([':oid' => $order_id]);
            $items = $items_stmt->fetchAll();

            if (empty($items)) {
                setFlash('error', 'Your order is empty.');
                header("Location: ../orders.php");
                exit();
            }

            // Build Stripe line items
            $line_items = [];
            foreach ($items as $item) {
                $line_items[] = [
                    'price' => $item['stripe_price_id'],
                    'quantity' => $item['quantity'],
                ];
            }

            // Store order ID in session
            $_SESSION['pending_order_id'] = $order_id;

            // Create Stripe Checkout Session
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $line_items,
                'mode' => 'payment',
                'success_url' => SITE_BASE_URL . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => SITE_BASE_URL . '/payment_cancel.php?type=order',
                'metadata' => [
                    'member_id' => $member_id,
                    'checkout_type' => 'order',
                    'order_id' => $order_id,
                ],
            ]);

            header("Location: " . $session->url);
            exit();

        default:
            setFlash('error', 'Invalid checkout type.');
            header("Location: ../index.php");
            exit();
    }
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("Stripe error: " . $e->getMessage());
    setFlash('error', 'Payment service error. Please try again.');
    $redirect = ($checkout_type === 'booking') ? '../bookings.php?action=new' : '../orders.php';
    header("Location: $redirect");
    exit();
}
