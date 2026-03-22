<?php
/**
 * helpers.php — Shared Helper Functions
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Centralised utility functions used across process scripts and page templates.
 */
require_once __DIR__ . '/process_routes.php';

/**
 * Sanitize user input by trimming whitespace.
 * HTML encoding is done at OUTPUT time (in views), NOT at storage time.
 *
 * @param string $data Raw user input
 * @return string Trimmed input
 */
function sanitizeInput(string $data): string
{
    return trim($data);
}

/**
 * Generate or retrieve the CSRF token for the current session.
 *
 * @return string 64-character hex CSRF token
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field for use inside forms.
 *
 * @return string HTML hidden input element containing the CSRF token
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Validate the CSRF token submitted via POST.
 * Redirects with a flash error if the token is missing or invalid.
 *
 * @param string $redirect_url URL to redirect to on failure
 * @return void
 */
function validateCsrf(string $redirect_url = '../index.php'): void
{
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
    ) {
        setFlash('error', 'Invalid or expired form submission. Please try again.');
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Store a flash message in the session.
 * @param string $type  'success' or 'error'
 * @param string $message  The message text (plain text, will be escaped on display)
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Display and clear the flash message (if any).
 * Returns the HTML string for the alert.
 *
 * @return string HTML alert div, or empty string if no flash message
 */
function displayFlash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $alert_class = ($flash['type'] === 'error') ? 'alert-danger' : 'alert-success';
    $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');

    return '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">'
        . $message
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '</div>';
}
