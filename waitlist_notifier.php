<?php
/**
 * waitlist_notifier.php — Waitlist Notification Helper
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Call notifyWaitlist($pdo, $booking_date, $time_slot) after any booking
 * cancellation. Finds the first Pending entry for that slot, generates a
 * 1-hour claim token, and emails the member.
 *
 * Also call expireWaitlistNotifications($pdo) to cascade expired
 * notifications to the next person in queue.
 */

/**
 * Expire any Notified entries whose 1-hour window has passed,
 * then notify the next Pending member in each affected slot.
 *
 * @param PDO $pdo Database connection
 * @return void
 */
function expireWaitlistNotifications(PDO $pdo): void
{
    // Find all Notified entries that have expired
    $expired = $pdo->query(
        "SELECT waitlist_id, booking_date, time_slot, member_id
        FROM waitlist
        WHERE status = 'Notified'
        AND claim_expires_at < NOW()"
    )->fetchAll();

    foreach ($expired as $entry) {
        // Mark as Expired
        $pdo->prepare(
            "UPDATE waitlist SET status = 'Expired', claim_token = NULL
            WHERE waitlist_id = :id"
        )->execute([':id' => $entry['waitlist_id']]);

        // Send "sorry, slot gone" email to the expired member
        $member = $pdo->prepare(
            "SELECT fname, email FROM members WHERE member_id = :id"
        );
        $member->execute([':id' => $entry['member_id']]);
        $m = $member->fetch();
        if ($m) {
            sendExpiredEmail($m['email'], $m['fname'], $entry['booking_date'], $entry['time_slot']);
        }

        // Notify next Pending member in the same slot
        notifyWaitlist($pdo, $entry['booking_date'], $entry['time_slot']);
    }
}

/**
 * Find the first Pending waitlist entry for a slot and send a claim email.
 *
 * @param PDO $pdo Database connection
 * @param string $booking_date Date of the booking slot (YYYY-MM-DD)
 * @param string $time_slot Time slot label (e.g. '11:00 AM - 1:00 PM')
 * @return void
 */
function notifyWaitlist(PDO $pdo, string $booking_date, string $time_slot): void
{
    // First expire any stale notifications for this slot
    $stale = $pdo->prepare(
        "SELECT waitlist_id, member_id FROM waitlist
        WHERE status = 'Notified'
        AND booking_date = :date AND time_slot = :slot
        AND claim_expires_at < NOW()"
    );
    $stale->execute([':date' => $booking_date, ':slot' => $time_slot]);
    foreach ($stale->fetchAll() as $s) {
        $pdo->prepare(
            "UPDATE waitlist SET status = 'Expired', claim_token = NULL
            WHERE waitlist_id = :id"
        )->execute([':id' => $s['waitlist_id']]);

        $m = $pdo->prepare("SELECT fname, email FROM members WHERE member_id = :id");
        $m->execute([':id' => $s['member_id']]);
        $expired_member = $m->fetch();
        if ($expired_member) {
            sendExpiredEmail(
                $expired_member['email'],
                $expired_member['fname'],
                $booking_date,
                $time_slot
            );
        }
    }

    // If someone is already Notified (within their 1hr window), do nothing
    $active = $pdo->prepare(
        "SELECT waitlist_id FROM waitlist
        WHERE status = 'Notified'
        AND booking_date = :date AND time_slot = :slot
        AND claim_expires_at > NOW()"
    );
    $active->execute([':date' => $booking_date, ':slot' => $time_slot]);
    if ($active->fetch()) {
        return; // Someone already has the active claim window
    }

    // Find the next Pending member (oldest first = first in queue)
    $next = $pdo->prepare(
        "SELECT w.waitlist_id, w.member_id, m.fname, m.email
        FROM waitlist w
        JOIN members m ON w.member_id = m.member_id
        WHERE w.status = 'Pending'
        AND w.booking_date = :date
        AND w.time_slot    = :slot
        ORDER BY w.created_at ASC
        LIMIT 1"
    );
    $next->execute([':date' => $booking_date, ':slot' => $time_slot]);
    $member = $next->fetch();

    if (!$member) {
        return; // Nobody waiting
    }

    // Generate a secure claim token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $pdo->prepare(
        "UPDATE waitlist
        SET status = 'Notified', claim_token = :token,
        notified_at = NOW(), claim_expires_at = :expires
        WHERE waitlist_id = :id"
    )->execute([
                ':token' => $token,
                ':expires' => $expires,
                ':id' => $member['waitlist_id'],
            ]);

    sendClaimEmail(
        $member['email'],
        $member['fname'],
        $booking_date,
        $time_slot,
        $token
    );
}

/**
 * Send the "a spot opened up — claim it within 1 hour" email.
 *
 * @param string $to Recipient email address
 * @param string $fname Recipient first name
 * @param string $booking_date Date of the available slot
 * @param string $time_slot Time slot label
 * @param string $token Secure claim token for the URL
 * @return void
 */
function sendClaimEmail(
    string $to,
    string $fname,
    string $booking_date,
    string $time_slot,
    string $token
): void {
    $base_url = getBaseUrl();
    $claim_url = $base_url . '/claim_waitlist.php?token=' . urlencode($token);
    $date_label = date('l, d F Y', strtotime($booking_date));
    $subject = 'A spot just opened up at The Rolling Dice!';

    $body = "Hi {$fname},\n\n"
        . "Great news! A spot has opened up for your waitlisted time slot:\n\n"
        . "  Date:  {$date_label}\n"
        . "  Time:  {$time_slot}\n\n"
        . "You have 1 hour to claim this spot before it is offered to the next person.\n\n"
        . "Click the link below to claim your booking:\n"
        . "{$claim_url}\n\n"
        . "If you no longer need this spot, simply ignore this email.\n\n"
        . "See you at The Rolling Dice!\n"
        . "The Rolling Dice Team";

    $headers = "From: noreply@rollingdice.com\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    mail($to, $subject, $body, $headers);
}

/**
 * Send the "sorry, your 1-hour window has expired" email.
 *
 * @param string $to Recipient email address
 * @param string $fname Recipient first name
 * @param string $booking_date Date of the expired slot
 * @param string $time_slot Time slot label
 * @return void
 */
function sendExpiredEmail(
    string $to,
    string $fname,
    string $booking_date,
    string $time_slot
): void {
    $date_label = date('l, d F Y', strtotime($booking_date));
    $subject = 'Your waitlist claim window has expired — The Rolling Dice';

    $body = "Hi {$fname},\n\n"
        . "Unfortunately your 1-hour window to claim the following spot has expired:\n\n"
        . "  Date:  {$date_label}\n"
        . "  Time:  {$time_slot}\n\n"
        . "The spot has been offered to the next person on the waitlist.\n"
        . "You remain on the waitlist and will be contacted again if another spot opens up.\n\n"
        . "The Rolling Dice Team";

    $headers = "From: noreply@rollingdice.com\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    mail($to, $subject, $body, $headers);
}

/**
 * Determine the base URL dynamically from the current request.
 *
 * @return string Protocol and host (e.g. 'https://example.com')
 */
function getBaseUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}
