<?php
/**
 * stripe_config.php — Stripe Configuration
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Loads API keys from .env file — never hardcode secrets in source code.
 */

require_once __DIR__ . '/env_loader.php';
loadEnv(__DIR__ . '/../.env');

require_once __DIR__ . '/../vendor/autoload.php';

// Stripe API keys from environment
$stripe_sk = getenv('STRIPE_SECRET_KEY');
$stripe_pk = getenv('STRIPE_PUBLISHABLE_KEY');
$site_url  = getenv('SITE_BASE_URL');

if (!$stripe_sk || !$stripe_pk || !$site_url) {
    error_log('Missing required environment variables in .env (STRIPE_SECRET_KEY, STRIPE_PUBLISHABLE_KEY, or SITE_BASE_URL)');
    die('Payment configuration error. Please contact the site administrator.');
}

define('STRIPE_SECRET_KEY', $stripe_sk);
define('STRIPE_PUBLISHABLE_KEY', $stripe_pk);

// Website base URL (no trailing slash)
define('SITE_BASE_URL', rtrim($site_url, '/'));

// Set the Stripe API key
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
