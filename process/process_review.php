<?php
/**
 * Process Review CRUD Operations
 *
 */

session_start();
require_once "helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . Routes::REVIEWS);
    exit();
}

if (!isset($_SESSION["member_id"])) {
    header("Location: " . Routes::LOGIN);
    exit();
}

validateCsrf(Routes::REVIEWS);

$member_id = $_SESSION["member_id"];
require_once "db.php";

$action = $_POST["action"] ?? "";

switch ($action) {

    // ---- CREATE ----
    case "create":
        $game_id = (int) ($_POST["game_id"] ?? 0);
        $rating = (int) ($_POST["rating"] ?? 0);
        $comment = sanitizeInput($_POST["comment"] ?? "");

        if ($game_id <= 0 || $rating < 1 || $rating > 5) {
            setFlash('error', 'Please select a game and rating.');
            header("Location: " . Routes::REVIEWS . "?action=new");
            exit();
        }

        // Check for duplicate review
        $check = $pdo->prepare("SELECT review_id FROM reviews WHERE member_id = :mid AND game_id = :gid");
        $check->execute([':mid' => $member_id, ':gid' => $game_id]);
        if ($check->rowCount() > 0) {
            setFlash('error', "You've already reviewed this game. Edit your existing review instead.");
            header("Location: " . Routes::REVIEWS);
            exit();
        }

        $stmt = $pdo->prepare(
            "INSERT INTO reviews (member_id, game_id, rating, comment)
            VALUES (:mid, :gid, :rating, :comment)"
        );
        $stmt->execute([
            ':mid' => $member_id,
            ':gid' => $game_id,
            ':rating' => $rating,
            ':comment' => $comment,
        ]);

        setFlash('success', 'Review submitted!');
        header("Location: " . Routes::REVIEWS);
        exit();

    // ---- UPDATE ----
    case "update":
        $review_id = (int) ($_POST["review_id"] ?? 0);
        $game_id = (int) ($_POST["game_id"] ?? 0);
        $rating = (int) ($_POST["rating"] ?? 0);
        $comment = sanitizeInput($_POST["comment"] ?? "");

        if ($game_id <= 0 || $rating < 1 || $rating > 5) {
            setFlash('error', 'Invalid input.');
            header("Location: " . Routes::REVIEWS . "?action=edit&review_id=$review_id");
            exit();
        }

        $stmt = $pdo->prepare(
            "UPDATE reviews SET game_id = :gid, rating = :rating, comment = :comment
            WHERE review_id = :rid AND member_id = :mid"
        );
        $stmt->execute([
            ':gid' => $game_id,
            ':rating' => $rating,
            ':comment' => $comment,
            ':rid' => $review_id,
            ':mid' => $member_id,
        ]);

        setFlash('success', 'Review updated.');
        header("Location: " . Routes::REVIEWS);
        exit();

    // ---- DELETE ----
    case "delete":
        $review_id = (int) ($_POST["review_id"] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE review_id = :rid AND member_id = :mid");
        $stmt->execute([':rid' => $review_id, ':mid' => $member_id]);

        setFlash('success', 'Review deleted.');
        header("Location: " . Routes::REVIEWS);
        exit();

    default:
        header("Location: " . Routes::REVIEWS);
        exit();
}
