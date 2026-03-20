<?php
/**
 * Process Waitlist Actions
 *
 *
 * Handles: join waitlist, cancel waitlist entry.
 */

session_start();
require_once "helpers.php";

define('WAITLIST_PAGE', '../waitlist.php');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . WAITLIST_PAGE);
    exit();
}

if (!isset($_SESSION["member_id"])) {
    header("Location: ../login.php");
    exit();
}

validateCsrf('../waitlist.php');

$member_id = $_SESSION["member_id"];
require_once "db.php";

$action = $_POST["action"] ?? "";

$valid_slots = [
    '11:00 AM - 1:00 PM',
    '1:00 PM - 3:00 PM',
    '3:00 PM - 5:00 PM',
    '5:00 PM - 7:00 PM',
    '7:00 PM - 9:00 PM',
    '9:00 PM - 11:00 PM'
];

switch ($action) {

    // ---- JOIN WAITLIST ----
    case "join":
        $errors = [];

        $booking_date = sanitizeInput($_POST["booking_date"] ?? "");
        $time_slot = sanitizeInput($_POST["time_slot"] ?? "");
        $party_size = (int) ($_POST["party_size"] ?? 0);
        $game_id = !empty($_POST["game_id"]) ? (int) $_POST["game_id"] : null;
        $notes = sanitizeInput($_POST["notes"] ?? "");

        // Validate inputs
        if (empty($booking_date) || strtotime($booking_date) < strtotime(date('Y-m-d'))) {
            $errors[] = "Please select a valid future date.";
        }
        if (!in_array($time_slot, $valid_slots, true)) {
            $errors[] = "Please select a valid time slot.";
        }
        if ($party_size < 1 || $party_size > 12) {
            $errors[] = "Party size must be between 1 and 12.";
        }

        if (!empty($errors)) {
            setFlash('error', implode(" ", $errors));
            header("Location: ../waitlist.php?action=new");
            exit();
        }

        // Check member isn't already on the waitlist for this slot
        $check = $pdo->prepare(
            "SELECT waitlist_id FROM waitlist
             WHERE member_id = :mid AND booking_date = :date
               AND time_slot = :slot AND status = 'Pending'"
        );
        $check->execute([':mid' => $member_id, ':date' => $booking_date, ':slot' => $time_slot]);

        if ($check->fetch()) {
            setFlash('error', "You're already on the waitlist for that date and time slot.");
            header("Location: ../waitlist.php?action=new");
            exit();
        }

        // Insert waitlist entry
        $stmt = $pdo->prepare(
            "INSERT INTO waitlist (member_id, booking_date, time_slot, party_size, game_id, notes)
             VALUES (:mid, :date, :slot, :size, :gid, :notes)"
        );
        $stmt->execute([
            ':mid' => $member_id,
            ':date' => $booking_date,
            ':slot' => $time_slot,
            ':size' => $party_size,
            ':gid' => $game_id,
            ':notes' => $notes,
        ]);

        // Calculate queue position for the flash message
        $pos_stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM waitlist
             WHERE booking_date = :date AND time_slot = :slot AND status = 'Pending'"
        );
        $pos_stmt->execute([':date' => $booking_date, ':slot' => $time_slot]);
        $position = $pos_stmt->fetchColumn();

        setFlash('success', "You've been added to the waitlist! You are number {$position} in the queue.");
        header("Location: " . WAITLIST_PAGE);
        exit();

    // ---- CANCEL WAITLIST ENTRY ----
    case "cancel":
        $waitlist_id = (int) ($_POST["waitlist_id"] ?? 0);

        $stmt = $pdo->prepare(
            "UPDATE waitlist SET status = 'Cancelled'
             WHERE waitlist_id = :wid AND member_id = :mid"
        );
        $stmt->execute([':wid' => $waitlist_id, ':mid' => $member_id]);

        setFlash('success', "You've been removed from the waitlist.");
        header("Location: " . WAITLIST_PAGE);
        exit();

    default:
        header("Location: " . WAITLIST_PAGE);
        exit();
}
