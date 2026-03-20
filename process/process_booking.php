<?php
/**
 * Process Booking Update & Cancel Operations
 * 
 *
 * Note: Booking creation now goes through Stripe Checkout
 * (create_checkout.php → payment_success.php).
 */

session_start();
require_once "helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../bookings.php");
    exit();
}

if (!isset($_SESSION["member_id"])) {
    header("Location: ../login.php");
    exit();
}

validateCsrf('../bookings.php');

$member_id = $_SESSION["member_id"];
require_once "db.php";

$action = $_POST["action"] ?? "";

switch ($action) {

    // ---- UPDATE ----
    case "update":
        $booking_id = (int) ($_POST["booking_id"] ?? 0);
        $errors = validateBookingInput();
        if (!empty($errors)) {
            setFlash('error', implode(" ", $errors));
            header("Location: ../bookings.php?action=edit&id=$booking_id");
            exit();
        }

        $stmt = $pdo->prepare(
            "UPDATE bookings
            SET booking_date = :date, time_slot = :slot, party_size = :size,
                game_id = :gid, notes = :notes
            WHERE booking_id = :bid AND member_id = :mid"
        );
        $stmt->execute([
            ':date' => $_POST["booking_date"],
            ':slot' => sanitizeInput($_POST["time_slot"]),
            ':size' => (int) $_POST["party_size"],
            ':gid' => !empty($_POST["game_id"]) ? (int) $_POST["game_id"] : null,
            ':notes' => sanitizeInput($_POST["notes"] ?? ""),
            ':bid' => $booking_id,
            ':mid' => $member_id,
        ]);

        setFlash('success', 'Booking updated.');
        header("Location: ../bookings.php");
        exit();

    // ---- DELETE (Cancel) ----
    case "delete":
        $booking_id = (int) ($_POST["booking_id"] ?? 0);

        // Fetch date and slot before cancelling (needed for waitlist notification)
        $slot_stmt = $pdo->prepare(
            "SELECT booking_date, time_slot FROM bookings
         WHERE booking_id = :bid AND member_id = :mid"
        );
        $slot_stmt->execute([':bid' => $booking_id, ':mid' => $member_id]);
        $cancelled_booking = $slot_stmt->fetch();

        $stmt = $pdo->prepare(
            "UPDATE bookings SET status = 'Cancelled'
        WHERE booking_id = :bid AND member_id = :mid"
        );
        $stmt->execute([':bid' => $booking_id, ':mid' => $member_id]);

        // Notify the first person on the waitlist for this slot
        if ($cancelled_booking) {
            require_once "waitlist_notifier.php";
            notifyWaitlist($pdo, $cancelled_booking['booking_date'], $cancelled_booking['time_slot']);
        }

        setFlash('success', 'Booking cancelled.');
        header("Location: ../bookings.php");
        exit();

    default:
        header("Location: ../bookings.php");
        exit();
}

// ---- Helper Functions ----

function validateBookingInput(): array
{
    $errors = [];
    if (empty($_POST["booking_date"])) {
        $errors[] = "Date is required.";
    }
    if (empty($_POST["time_slot"])) {
        $errors[] = "Time slot is required.";
    }
    if (empty($_POST["party_size"]) || (int) $_POST["party_size"] < 1 || (int) $_POST["party_size"] > 12) {
        $errors[] = "Party size must be between 1 and 12.";
    }
    return $errors;
}
