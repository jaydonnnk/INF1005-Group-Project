<?php
/**
 * Process Wishlist CRUD Operations
 *
 */

session_start();
require_once "helpers.php";

define('WISHLIST_PAGE', '../wishlist.php');
define('GAMES_PAGE', '../games.php');
define('LOGIN_PAGE', '../login.php');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . WISHLIST_PAGE);
    exit();
}

if (!isset($_SESSION["member_id"])) {
    header("Location: " . LOGIN_PAGE);
    exit();
}

validateCsrf(GAMES_PAGE);

$member_id = $_SESSION["member_id"];
require_once "db.php";

$action = $_POST["action"] ?? "";

switch ($action) {

    // ---- ADD TO WISHLIST ----
    case "add":
        $game_id = (int) ($_POST["game_id"] ?? 0);
        if ($game_id <= 0) {
            setFlash('error', 'Invalid game.');
            header("Location: " . GAMES_PAGE);
            exit();
        }

        // Check if already in wishlist
        $check = $pdo->prepare("SELECT wishlist_id FROM wishlists WHERE member_id = :mid AND game_id = :gid");
        $check->execute([':mid' => $member_id, ':gid' => $game_id]);

        if ($check->rowCount() > 0) {
            setFlash('success', 'This game is already in your wishlist!');
            header("Location: " . GAMES_PAGE);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO wishlists (member_id, game_id) VALUES (:mid, :gid)");
        $stmt->execute([':mid' => $member_id, ':gid' => $game_id]);

        setFlash('success', 'Added to your wishlist!');
        header("Location: " . GAMES_PAGE);
        exit();

    // ---- REMOVE FROM WISHLIST ----
    case "remove":
        $wishlist_id = (int) ($_POST["wishlist_id"] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM wishlists WHERE wishlist_id = :wid AND member_id = :mid");
        $stmt->execute([':wid' => $wishlist_id, ':mid' => $member_id]);

        setFlash('success', 'Removed from wishlist.');
        header("Location: " . WISHLIST_PAGE);
        exit();

    default:
        header("Location: " . WISHLIST_PAGE);
        exit();
}
