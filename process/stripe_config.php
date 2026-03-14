<?php
/**
 * Stripe Configuration
 * The Rolling Dice - Board Game Café
 *
 * Loads API keys from .env file — never hardcode secrets in source code.
 */

require_once __DIR__ . '/env_loader.php';
loadEnv(__DIR__ . '/../.env');

require_once __DIR__ . '/../vendor/autoload.php';

// Stripe API keys from environment
define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY'));
define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY'));

// Website base URL (no trailing slash)
define('SITE_BASE_URL', getenv('SITE_BASE_URL'));

// Set the Stripe API key
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
