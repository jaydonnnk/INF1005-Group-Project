<?php
/**
 * Email Helper
 * The Rolling Dice - Board Game Café
 *
 * Sends emails via Gmail SMTP using PHPMailer.
 * Assumes env is already loaded by the calling script.
 * Assumes vendor/autoload.php is available.
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Send an email via Gmail SMTP.
 *
 * @param string $toEmail  Recipient email address
 * @param string $toName   Recipient display name
 * @param string $subject  Email subject line
 * @param string $htmlBody Email body (HTML)
 * @return bool True on success, false on failure
 */
function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $gmailAddress  = getenv('GMAIL_ADDRESS');
    $gmailPassword = getenv('GMAIL_APP_PASSWORD');

    if (!$gmailAddress || !$gmailPassword) {
        error_log('Missing GMAIL_ADDRESS or GMAIL_APP_PASSWORD in .env');
        return false;
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmailAddress;
        $mail->Password   = $gmailPassword;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender and recipient
        $mail->setFrom($gmailAddress, 'The Rolling Dice');
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('PHPMailer error: ' . $mail->ErrorInfo);
        return false;
    }
}
