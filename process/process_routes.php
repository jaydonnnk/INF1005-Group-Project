<?php
/**
 * process_routes.php — Centralised Route Constants
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Paths are relative to the file that includes this.
 * - Routes::*        → for files in process/         (one level below root, prefix ../)
 * - Routes::ROOT_*   → for files at root level        (no prefix)
 * - Routes::ADMIN_*  → for files in admin/process/    (two levels below root, prefix ../../ or ../)
 */
class Routes {

    // ── process/ files → root (prefix: ../) ──────────────────────
    const LOGIN         = '../login.php';
    const DASHBOARD     = '../dashboard.php';
    const INDEX         = '../index.php';
    const BOOKINGS      = '../bookings.php';
    const ORDERS        = '../orders.php';
    const MENU          = '../menu.php';
    const GAMES         = '../games.php';
    const REVIEWS       = '../reviews.php';
    const WISHLIST      = '../wishlist.php';
    const WAITLIST      = '../waitlist.php';
    const FORGOT_PW     = '../forgot_password.php';
    const RESET_PW      = '../reset_password.php';
    const RESEND_VERIFY = '../resend_verification.php';
    const SETUP_2FA     = '../setup_2fa.php';
    const VERIFY_2FA    = '../verify_2fa.php';
    const PROFILE       = '../profile.php';
    const REGISTER      = '../register.php';
    const MATCHMAKING   = '../matchmaking.php';

    // Root-level files (no prefix) 
    const ROOT_LOGIN        = 'login.php';
    const ROOT_BOOKINGS     = 'bookings.php';
    const ROOT_BOOKINGS_NEW = 'bookings.php?action=new';
    const ROOT_REVIEWS      = 'reviews.php';
    const ROOT_INDEX        = 'index.php';

    // admin/process/ files (prefix: ../ = admin/, ../../ = root)
    const ADMIN_DASH     = '../../dashboard.php';
    const ADMIN_HOME     = '../index.php';
    const ADMIN_GAMES    = '../games.php';
    const ADMIN_MENU     = '../menu.php';
    const ADMIN_BOOKINGS = '../bookings.php';
    const ADMIN_ORDERS   = '../orders.php';
    const ADMIN_MEMBERS  = '../members.php';
}