<?php
/**
 * booking_emails.php — Booking Email Notifications
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Builds and sends styled HTML booking notification emails
 * via the existing sendEmail() helper. A failed email will
 * never prevent the booking operation from completing.
 */

require_once __DIR__ . '/send_email.php';

/**
 * Send a booking confirmation email after a new booking is created.
 *
 * @param PDO $pdo        Database connection
 * @param int $booking_id The newly created booking ID
 * @return bool True if email sent, false otherwise
 */
function sendBookingConfirmation(PDO $pdo, int $booking_id): bool
{
    $data = fetchBookingDetails($pdo, $booking_id);
    if (!$data) return false;

    $subject = "Booking Confirmed — #$booking_id | The Rolling Dice";

    $body = buildEmailLayout(
        'Booking Confirmed!',
        "
        <p>Hi <strong>" . htmlspecialchars($data['fname']) . "</strong>,</p>
        <p>Your booking has been confirmed. Here are your details:</p>
        " . buildBookingTable($data) . "
        <p>We look forward to seeing you!</p>
        "
    );

    return sendEmail($data['email'], $data['fname'] . ' ' . $data['lname'], $subject, $body);
}

/**
 * Send a booking update email when the booking status changes.
 *
 * @param PDO $pdo        Database connection
 * @param int $booking_id The updated booking ID
 * @return bool True if email sent, false otherwise
 */
function sendBookingUpdate(PDO $pdo, int $booking_id): bool
{
    $data = fetchBookingDetails($pdo, $booking_id);
    if (!$data) return false;

    $status = htmlspecialchars($data['status']);
    $subject = "Booking $status — #$booking_id | The Rolling Dice";

    $body = buildEmailLayout(
        "Booking $status",
        "
        <p>Hi <strong>" . htmlspecialchars($data['fname']) . "</strong>,</p>
        <p>Your booking <strong>#$booking_id</strong> has been updated to <strong>$status</strong>.</p>
        " . buildBookingTable($data) . "
        <p>If you have any questions, feel free to contact us.</p>
        "
    );

    return sendEmail($data['email'], $data['fname'] . ' ' . $data['lname'], $subject, $body);
}

/**
 * Send a booking cancellation email.
 *
 * @param PDO $pdo        Database connection
 * @param int $booking_id The cancelled booking ID
 * @return bool True if email sent, false otherwise
 */
function sendBookingCancellation(PDO $pdo, int $booking_id): bool
{
    $data = fetchBookingDetails($pdo, $booking_id);
    if (!$data) return false;

    $subject = "Booking Cancelled — #$booking_id | The Rolling Dice";

    $body = buildEmailLayout(
        'Booking Cancelled',
        "
        <p>Hi <strong>" . htmlspecialchars($data['fname']) . "</strong>,</p>
        <p>Your booking <strong>#$booking_id</strong> has been cancelled.</p>
        " . buildBookingTable($data) . "
        <p>If this was a mistake, you can make a new reservation on our website.</p>
        "
    );

    return sendEmail($data['email'], $data['fname'] . ' ' . $data['lname'], $subject, $body);
}

// ---------------------------------------------------------------------------
// Internal helpers (not called outside this file)
// ---------------------------------------------------------------------------

/**
 * Fetch booking + member + game details for email content.
 *
 * @param PDO $pdo        Database connection
 * @param int $booking_id Booking to look up
 * @return array|false Associative row or false if not found
 */
function fetchBookingDetails(PDO $pdo, int $booking_id): array|false
{
    $stmt = $pdo->prepare(
        "SELECT b.*, m.fname, m.lname, m.email, g.title AS game_title
         FROM bookings b
         JOIN members m ON m.member_id = b.member_id
         LEFT JOIN games g ON g.game_id = b.game_id
         WHERE b.booking_id = :id"
    );
    $stmt->execute([':id' => $booking_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Build the booking details HTML table used across all email types.
 *
 * @param array $data Booking row from fetchBookingDetails()
 * @return string HTML table markup
 */
function buildBookingTable(array $data): string
{
    $date  = date('d M Y', strtotime($data['booking_date']));
    $game  = $data['game_title'] ? htmlspecialchars($data['game_title']) : 'None selected';

    return "
    <table width='100%' cellpadding='8' cellspacing='0'
           style='border:1px solid #E6DDD5; border-radius:8px; border-collapse:separate; margin:16px 0;'>
        <tr style='background-color:#FFF8F0;'>
            <td style='font-weight:bold; color:#3C2415; width:40%;'>Booking ID</td>
            <td style='color:#3E2C23;'>#" . (int)$data['booking_id'] . "</td>
        </tr>
        <tr>
            <td style='font-weight:bold; color:#3C2415;'>Date</td>
            <td style='color:#3E2C23;'>$date</td>
        </tr>
        <tr style='background-color:#FFF8F0;'>
            <td style='font-weight:bold; color:#3C2415;'>Time Slot</td>
            <td style='color:#3E2C23;'>" . htmlspecialchars($data['time_slot']) . "</td>
        </tr>
        <tr>
            <td style='font-weight:bold; color:#3C2415;'>Party Size</td>
            <td style='color:#3E2C23;'>" . (int)$data['party_size'] . "</td>
        </tr>
        <tr style='background-color:#FFF8F0;'>
            <td style='font-weight:bold; color:#3C2415;'>Game</td>
            <td style='color:#3E2C23;'>$game</td>
        </tr>
        <tr>
            <td style='font-weight:bold; color:#3C2415;'>Rental Hours</td>
            <td style='color:#3E2C23;'>" . (int)$data['rental_hours'] . " hr(s)</td>
        </tr>
        <tr style='background-color:#FFF8F0;'>
            <td style='font-weight:bold; color:#3C2415;'>Status</td>
            <td style='color:#3E2C23; font-weight:bold;'>" . htmlspecialchars($data['status']) . "</td>
        </tr>
    </table>";
}

/**
 * Wrap email content in the branded HTML layout.
 *
 * @param string $heading  Email heading text
 * @param string $content  Inner HTML content
 * @return string Complete HTML email body
 */
function buildEmailLayout(string $heading, string $content): string
{
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head><meta charset='UTF-8'></head>
    <body style='margin:0; padding:0; background-color:#FFF8F0; font-family:Nunito,Segoe UI,sans-serif; color:#3E2C23;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background-color:#FFF8F0;'>
            <tr><td align='center' style='padding:24px 16px;'>
                <table width='600' cellpadding='0' cellspacing='0' style='max-width:600px; width:100%;'>
                    <!-- Header -->
                    <tr>
                        <td style='background-color:#3C2415; padding:24px; text-align:center; border-radius:12px 12px 0 0;'>
                            <h1 style='margin:0; color:#E8A849; font-family:Georgia,serif; font-size:24px;'>The Rolling Dice</h1>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style='background-color:#FFFDF9; padding:32px 24px; border-left:1px solid #E6DDD5; border-right:1px solid #E6DDD5;'>
                            <h2 style='margin:0 0 16px; color:#3C2415; font-family:Georgia,serif; font-size:20px; border-bottom:2px solid #996633; padding-bottom:8px;'>$heading</h2>
                            $content
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style='background-color:#3C2415; padding:16px 24px; text-align:center; border-radius:0 0 12px 12px;'>
                            <p style='margin:0; color:#FFF8F0; font-size:13px; opacity:0.85;'>
                                The Rolling Dice Pte. Ltd. &bull; 123 Dice Lane, #01-01, Singapore 123456
                            </p>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>";
}
