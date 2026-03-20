<?php
/**
 * Claim Waitlist Spot
 *
 *
 * Members land here from the email link. If the token is valid and
 * within the 1-hour window, they are redirected to the booking form
 * with date and slot pre-filled. If expired or invalid, a clear
 * message is shown.
 */

session_start();
require_once "process/db.php";
require_once "process/helpers.php";
require_once "process/waitlist_notifier.php";

define('HOME_PAGE', 'index.php');

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    setFlash('error', 'Invalid claim link.');
    header("Location: " . HOME_PAGE);
    exit();
}

// Expire any stale notifications before checking this token
expireWaitlistNotifications($pdo);

// Look up the token
$stmt = $pdo->prepare(
    "SELECT w.*, m.fname, m.email
    FROM waitlist w
    JOIN members m ON w.member_id = m.member_id
    WHERE w.claim_token = :token"
);
$stmt->execute([':token' => $token]);
$entry = $stmt->fetch();

// Token not found
if (!$entry) {
    setFlash('error', 'This claim link is invalid or has already been used.');
    header("Location: " . HOME_PAGE);
    exit();
}

// Token expired
if ($entry['status'] === 'Expired' || strtotime($entry['claim_expires_at']) < time()) {
    // Mark as expired if not already
    if ($entry['status'] !== 'Expired') {
        $pdo->prepare(
            "UPDATE waitlist SET status = 'Expired', claim_token = NULL WHERE waitlist_id = :id"
        )->execute([':id' => $entry['waitlist_id']]);
        notifyWaitlist($pdo, $entry['booking_date'], $entry['time_slot']);
    }
    setFlash('error', 'Sorry — your 1-hour claim window has expired. The spot has been offered to the next person on the waitlist.');
    header("Location: " . HOME_PAGE);
    exit();
}

// Already claimed
if ($entry['status'] === 'Claimed') {
    setFlash('error', 'This spot has already been claimed.');
    header("Location: " . HOME_PAGE);
    exit();
}

// Valid — mark as Claimed and redirect to booking form with pre-filled fields
$pdo->prepare(
    "UPDATE waitlist SET status = 'Claimed', claim_token = NULL WHERE waitlist_id = :id"
)->execute([':id' => $entry['waitlist_id']]);

// Auto-login if the member is not already logged in
if (!isset($_SESSION['member_id'])) {
    $_SESSION['member_id']    = $entry['member_id'];
    $_SESSION['member_name']  = $entry['fname'];
    $_SESSION['member_email'] = $entry['email'];
    $_SESSION['is_admin']     = 0;
    session_regenerate_id(true);
}

setFlash('success', 'Your spot is reserved for 1 hour — please complete your booking below.');
header("Location: bookings.php?action=new"
    . "&date="     . urlencode($entry['booking_date'])
    . "&time_slot=" . urlencode($entry['time_slot'])
    . "&game_id="   . urlencode($entry['game_id'] ?? '')
);
exit();
