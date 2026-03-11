<?php
/**
 * Process Wishlist CRUD Operations
 * The Rolling Dice - Board Game Cafe
 */

session_start();
require_once "helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../wishlist.php");
    exit();
}

if (!isset($_SESSION["member_id"])) {
    header("Location: ../login.php");
    exit();
}

validateCsrf('../games.php');

$member_id = $_SESSION["member_id"];
require_once "db.php";

$action = $_POST["action"] ?? "";

switch ($action) {

    // ---- ADD TO WISHLIST ----
    case "add":
        $game_id = (int) ($_POST["game_id"] ?? 0);
        if ($game_id <= 0) {
            setFlash('error', 'Invalid game.');
            header("Location: ../games.php");
            exit();
        }

        // Check if already in wishlist
        $check = $pdo->prepare("SELECT wishlist_id FROM wishlists WHERE member_id = :mid AND game_id = :gid");
        $check->execute([':mid' => $member_id, ':gid' => $game_id]);

        if ($check->rowCount() > 0) {
            setFlash('success', 'This game is already in your wishlist!');
            header("Location: ../games.php");
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO wishlists (member_id, game_id) VALUES (:mid, :gid)");
        $stmt->execute([':mid' => $member_id, ':gid' => $game_id]);

        setFlash('success', 'Added to your wishlist!');
        header("Location: ../games.php");
        exit();

    // ---- REMOVE FROM WISHLIST ----
    case "remove":
        $wishlist_id = (int) ($_POST["wishlist_id"] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM wishlists WHERE wishlist_id = :wid AND member_id = :mid");
        $stmt->execute([':wid' => $wishlist_id, ':mid' => $member_id]);

        setFlash('success', 'Removed from wishlist.');
        header("Location: ../wishlist.php");
        exit();

    default:
        header("Location: ../wishlist.php");
        exit();
}
