<?php
/**
 * Process Booking CRUD Operations
 * The Rolling Dice - Board Game Cafe
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

validate_csrf('../bookings.php');

$member_id = $_SESSION["member_id"];
require_once "db.php";

$action = $_POST["action"] ?? "";

switch ($action) {

    // ---- CREATE ----
    case "create":
        $errors = validateBookingInput();
        if (!empty($errors)) {
            set_flash('error', implode(" ", $errors));
            header("Location: ../bookings.php?action=new");
            exit();
        }

        $stmt = $pdo->prepare(
            "INSERT INTO bookings (member_id, booking_date, time_slot, party_size, game_id, notes)
             VALUES (:mid, :date, :slot, :size, :gid, :notes)"
        );
        $stmt->execute([
            ':mid'   => $member_id,
            ':date'  => $_POST["booking_date"],
            ':slot'  => sanitize_input($_POST["time_slot"]),
            ':size'  => (int) $_POST["party_size"],
            ':gid'   => !empty($_POST["game_id"]) ? (int) $_POST["game_id"] : null,
            ':notes' => sanitize_input($_POST["notes"] ?? ""),
        ]);

        set_flash('success', 'Booking confirmed!');
        header("Location: ../bookings.php");
        exit();

    // ---- UPDATE ----
    case "update":
        $booking_id = (int) ($_POST["booking_id"] ?? 0);
        $errors = validateBookingInput();
        if (!empty($errors)) {
            set_flash('error', implode(" ", $errors));
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
            ':date'  => $_POST["booking_date"],
            ':slot'  => sanitize_input($_POST["time_slot"]),
            ':size'  => (int) $_POST["party_size"],
            ':gid'   => !empty($_POST["game_id"]) ? (int) $_POST["game_id"] : null,
            ':notes' => sanitize_input($_POST["notes"] ?? ""),
            ':bid'   => $booking_id,
            ':mid'   => $member_id,
        ]);

        set_flash('success', 'Booking updated.');
        header("Location: ../bookings.php");
        exit();

    // ---- DELETE (Cancel) ----
    case "delete":
        $booking_id = (int) ($_POST["booking_id"] ?? 0);
        $stmt = $pdo->prepare(
            "UPDATE bookings SET status = 'Cancelled'
             WHERE booking_id = :bid AND member_id = :mid"
        );
        $stmt->execute([':bid' => $booking_id, ':mid' => $member_id]);

        set_flash('success', 'Booking cancelled.');
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
    if (empty($_POST["booking_date"])) $errors[] = "Date is required.";
    if (empty($_POST["time_slot"]))    $errors[] = "Time slot is required.";
    if (empty($_POST["party_size"]) || (int)$_POST["party_size"] < 1 || (int)$_POST["party_size"] > 12) {
        $errors[] = "Party size must be between 1 and 12.";
    }
    return $errors;
}
