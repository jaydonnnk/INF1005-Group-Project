<?php
/**
 * Process Matchmaking Post & Interest Operations
 * The Rolling Dice - Board Game Café
 *
 * Actions:
 *   create   – Insert a new matchmaking post (logged-in only)
 *   interest – Toggle interest on a post (logged-in only)
 */

session_start();
require_once "helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . Routes::MATCHMAKING);
    exit();
}

if (!isset($_SESSION["member_id"])) {
    setFlash('error', 'You must be signed in to do that.');
    header("Location: " . Routes::LOGIN);
    exit();
}

validateCsrf(Routes::MATCHMAKING);

$member_id = $_SESSION["member_id"];
require_once "db.php";

$action = $_POST["action"] ?? "";

switch ($action) {

    // ── CREATE POST ──────────────────────────────────────────────────────────
    case "create":

        // --- Validate required fields ---
        $title = sanitizeInput($_POST["title"] ?? "");
        $game_name = sanitizeInput($_POST["game_name"] ?? "");
        $game_type = sanitizeInput($_POST["game_type"] ?? "");
        $skill_level = sanitizeInput($_POST["skill_level"] ?? "");
        $session_date = sanitizeInput($_POST["session_date"] ?? "");
        $session_time = sanitizeInput($_POST["session_time"] ?? "");
        $spots_total = (int) ($_POST["spots_total"] ?? 0);

        $errors = [];

        if (empty($title) || strlen($title) > 80) {
            $errors[] = "Title is required and must be 80 characters or fewer.";
        }
        if (empty($game_name)) {
            $errors[] = "Game name is required.";
        }

        $valid_game_types = ['Strategy', 'Party', 'Cooperative', 'Deck-Building', 'Role-Playing', 'Trivia', 'Word'];
        if (!in_array($game_type, $valid_game_types, true)) {
            $errors[] = "Please select a valid game type.";
        }

        $valid_skills = ['Beginner', 'Intermediate', 'Advanced'];
        if (!in_array($skill_level, $valid_skills, true)) {
            $errors[] = "Please select a valid skill level.";
        }

        // Validate date: must be a real date and not in the past
        $parsed_date = date_create_from_format('Y-m-d', $session_date);
        if (!$parsed_date || $parsed_date->format('Y-m-d') !== $session_date) {
            $errors[] = "Invalid session date.";
        } elseif ($session_date < date('Y-m-d')) {
            $errors[] = "Session date cannot be in the past.";
        }

        // Validate time: must be one of the 6 allowed 2-hour session slots
        $valid_slots = ['11:00', '13:00', '15:00', '17:00', '19:00', '21:00'];
        if (!in_array($session_time, $valid_slots, true)) {
            $errors[] = "Please select a valid time slot.";
        } elseif (strtotime("{$session_date} {$session_time}:00") <= time()) {
            $errors[] = "Session date and time cannot be in the past.";
        }

        if ($spots_total < 1 || $spots_total > 10) {
            $errors[] = "Spots needed must be between 1 and 10.";
        }

        if (!empty($errors)) {
            setFlash('error', implode(" ", $errors));
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        // --- Optional fields ---
        $play_style_raw = sanitizeInput($_POST["play_style"] ?? "");
        $valid_styles = ['Casual', 'Competitive', 'Story-driven'];
        $play_style = in_array($play_style_raw, $valid_styles, true) ? $play_style_raw : null;

        $pref_gender_raw = sanitizeInput($_POST["pref_gender"] ?? "Any");
        $valid_genders = ['Any', 'Male', 'Female', 'Non-binary'];
        $pref_gender = in_array($pref_gender_raw, $valid_genders, true) ? $pref_gender_raw : 'Any';

        $pref_skill_raw = sanitizeInput($_POST["pref_skill"] ?? "Any");
        $valid_pref_skills = ['Any', 'Beginner', 'Intermediate', 'Advanced', 'Intermediate+'];
        $pref_skill = in_array($pref_skill_raw, $valid_pref_skills, true) ? $pref_skill_raw : 'Any';

        $pref_age = sanitizeInput($_POST["pref_age"] ?? "");
        $pref_age = strlen($pref_age) <= 20 ? $pref_age : null;

        $body = sanitizeInput($_POST["body"] ?? "");
        $body = strlen($body) <= 400 ? $body : substr($body, 0, 400);

        $is_urgent = isset($_POST["is_urgent"]) ? 1 : 0;

        // --- Insert ---
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO matchmaking_posts
                    (member_id, title, body, game_name, game_type, skill_level,
                    play_style, spots_total, session_date, session_time,
                    pref_gender, pref_skill, pref_age, is_urgent)
                VALUES
                    (:mid, :title, :body, :game_name, :game_type, :skill_level,
                    :play_style, :spots_total, :session_date, :session_time,
                    :pref_gender, :pref_skill, :pref_age, :is_urgent)"
            );
            $stmt->execute([
                ':mid' => $member_id,
                ':title' => $title,
                ':body' => $body,
                ':game_name' => $game_name,
                ':game_type' => $game_type,
                ':skill_level' => $skill_level,
                ':play_style' => $play_style,
                ':spots_total' => $spots_total,
                ':session_date' => $session_date,
                ':session_time' => $session_time,
                ':pref_gender' => $pref_gender,
                ':pref_skill' => $pref_skill,
                ':pref_age' => $pref_age ?: null,
                ':is_urgent' => $is_urgent,
            ]);

            // Update last-active timestamp for "online now" indicator
            $pdo->prepare(
                "INSERT INTO matchmaking_sessions (member_id, last_active)
                VALUES (:mid, NOW())
                ON DUPLICATE KEY UPDATE last_active = NOW()"
            )->execute([':mid' => $member_id]);

            setFlash('success', 'Your session has been posted! Good luck finding your players.');
        } catch (Exception $e) {
            error_log("Matchmaking create error: " . $e->getMessage());
            setFlash('error', 'Failed to post your session. Please try again.');
        }

        header("Location: " . Routes::MATCHMAKING);
        exit();


    // ── UPDATE POST ──────────────────────────────────────────────────────────
    case "update":

        $post_id = (int) ($_POST["post_id"] ?? 0);
        if ($post_id <= 0) {
            setFlash('error', 'Invalid session.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        // Verify the post belongs to this member
        $check = $pdo->prepare("SELECT member_id FROM matchmaking_posts WHERE post_id=:pid");
        $check->execute([':pid' => $post_id]);
        $row = $check->fetch();
        if (!$row || (int) $row['member_id'] !== $member_id) {
            setFlash('error', 'You can only edit your own posts.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        // Reuse the same validation as create
        $title = sanitizeInput($_POST["title"] ?? "");
        $game_name = sanitizeInput($_POST["game_name"] ?? "");
        $game_type = sanitizeInput($_POST["game_type"] ?? "");
        $skill_level = sanitizeInput($_POST["skill_level"] ?? "");
        $session_date = sanitizeInput($_POST["session_date"] ?? "");
        $session_time = sanitizeInput($_POST["session_time"] ?? "");
        $spots_total = (int) ($_POST["spots_total"] ?? 0);

        $errors = [];
        if (empty($title) || strlen($title) > 80) {
            $errors[] = "Title is required (max 80 chars).";
        }
        if (empty($game_name)) {
            $errors[] = "Game name is required.";
        }
        if (!in_array($game_type, ['Strategy', 'Party', 'Cooperative', 'Deck-Building', 'Role-Playing', 'Trivia', 'Word'], true)) {
            $errors[] = "Invalid game type.";
        }
        if (!in_array($skill_level, ['Beginner', 'Intermediate', 'Advanced'], true)) {
            $errors[] = "Invalid skill level.";
        }
        $pd = date_create_from_format('Y-m-d', $session_date);
        if (!$pd || $pd->format('Y-m-d') !== $session_date) {
            $errors[] = "Invalid date.";
        }
        $valid_slots = ['11:00', '13:00', '15:00', '17:00', '19:00', '21:00'];
        if (!in_array($session_time, $valid_slots, true)) {
            $errors[] = "Please select a valid time slot.";
        } elseif (strtotime("{$session_date} {$session_time}:00") <= time()) {
            $errors[] = "Session date and time cannot be in the past.";
        }
        if ($spots_total < 1 || $spots_total > 10) {
            $errors[] = "Spots must be 1–10.";
        }

        if (!empty($errors)) {
            setFlash('error', implode(" ", $errors));
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        $play_style_raw = sanitizeInput($_POST["play_style"] ?? "");
        $play_style = in_array($play_style_raw, ['Casual', 'Competitive', 'Story-driven'], true) ? $play_style_raw : null;
        $pref_gender_raw = sanitizeInput($_POST["pref_gender"] ?? "Any");
        $pref_gender = in_array($pref_gender_raw, ['Any', 'Male', 'Female', 'Non-binary'], true) ? $pref_gender_raw : 'Any';
        $pref_skill_raw = sanitizeInput($_POST["pref_skill"] ?? "Any");
        $pref_skill = in_array($pref_skill_raw, ['Any', 'Beginner', 'Intermediate', 'Advanced', 'Intermediate+'], true) ? $pref_skill_raw : 'Any';
        $pref_age = sanitizeInput($_POST["pref_age"] ?? "");
        $pref_age = strlen($pref_age) <= 20 ? $pref_age : null;
        $body = sanitizeInput($_POST["body"] ?? "");
        $body = strlen($body) <= 400 ? $body : substr($body, 0, 400);
        $is_urgent = isset($_POST["is_urgent"]) ? 1 : 0;

        try {
            $pdo->prepare(
                "UPDATE matchmaking_posts SET
                    title=:title, body=:body, game_name=:game_name, game_type=:game_type,
                    skill_level=:skill_level, play_style=:play_style, spots_total=:spots_total,
                    session_date=:session_date, session_time=:session_time,
                    pref_gender=:pref_gender, pref_skill=:pref_skill, pref_age=:pref_age,
                    is_urgent=:is_urgent
                WHERE post_id=:pid AND member_id=:mid"
            )->execute([
                        ':title' => $title,
                        ':body' => $body,
                        ':game_name' => $game_name,
                        ':game_type' => $game_type,
                        ':skill_level' => $skill_level,
                        ':play_style' => $play_style,
                        ':spots_total' => $spots_total,
                        ':session_date' => $session_date,
                        ':session_time' => $session_time,
                        ':pref_gender' => $pref_gender,
                        ':pref_skill' => $pref_skill,
                        ':pref_age' => $pref_age ?: null,
                        ':is_urgent' => $is_urgent,
                        ':pid' => $post_id,
                        ':mid' => $member_id,
                    ]);
            setFlash('success', 'Session updated successfully.');
        } catch (Exception $e) {
            error_log("Matchmaking update error: " . $e->getMessage());
            setFlash('error', 'Failed to update your session. Please try again.');
        }

        header("Location: " . Routes::MATCHMAKING);
        exit();


    // ── DELETE POST ──────────────────────────────────────────────────────────
    case "delete":

        $post_id = (int) ($_POST["post_id"] ?? 0);
        if ($post_id <= 0) {
            setFlash('error', 'Invalid session.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        // Verify the post belongs to this member before deleting
        $check = $pdo->prepare("SELECT member_id FROM matchmaking_posts WHERE post_id=:pid");
        $check->execute([':pid' => $post_id]);
        $row = $check->fetch();
        if (!$row || (int) $row['member_id'] !== $member_id) {
            setFlash('error', 'You can only delete your own posts.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        try {
            // Setting status to Cancelled keeps historical data intact
            $pdo->prepare(
                "UPDATE matchmaking_posts SET status='Cancelled' WHERE post_id=:pid AND member_id=:mid"
            )->execute([':pid' => $post_id, ':mid' => $member_id]);
            setFlash('success', 'Session deleted.');
        } catch (Exception $e) {
            error_log("Matchmaking delete error: " . $e->getMessage());
            setFlash('error', 'Failed to delete your session. Please try again.');
        }

        header("Location: " . Routes::MATCHMAKING);
        exit();


    // ── JOIN SESSION ─────────────────────────────────────────────────────────
    case "join":

        $post_id = (int) ($_POST["post_id"] ?? 0);
        if ($post_id <= 0) {
            setFlash('error', 'Invalid session.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        $post = $pdo->prepare("SELECT member_id, spots_total, spots_filled, status FROM matchmaking_posts WHERE post_id=:pid");
        $post->execute([':pid' => $post_id]);
        $row = $post->fetch();

        if (!$row || $row['status'] !== 'Open') {
            setFlash('error', 'Session not found or closed.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }
        if ((int) $row['member_id'] === $member_id) {
            setFlash('error', 'You cannot join your own session.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }
        if ((int) $row['spots_filled'] >= (int) $row['spots_total']) {
            setFlash('error', 'This session is already full.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        try {
            $pdo->prepare("INSERT IGNORE INTO matchmaking_joins (post_id, member_id) VALUES (:pid,:mid)")->execute([':pid' => $post_id, ':mid' => $member_id]);
            $pdo->prepare("UPDATE matchmaking_posts SET spots_filled = (SELECT COUNT(*) FROM matchmaking_joins WHERE post_id=:pid) WHERE post_id=:pid2")->execute([':pid' => $post_id, ':pid2' => $post_id]);
            setFlash('success', 'You have joined the session!');
        } catch (Exception $e) {
            error_log("Join error: " . $e->getMessage());
            setFlash('error', 'Could not join. Please try again.');
        }
        header("Location: " . Routes::MATCHMAKING);
        exit();


    // ── UNJOIN SESSION ───────────────────────────────────────────────────────
    case "unjoin":

        $post_id = (int) ($_POST["post_id"] ?? 0);
        if ($post_id <= 0) {
            setFlash('error', 'Invalid session.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        try {
            $pdo->prepare("DELETE FROM matchmaking_joins WHERE post_id=:pid AND member_id=:mid")->execute([':pid' => $post_id, ':mid' => $member_id]);
            $pdo->prepare("UPDATE matchmaking_posts SET spots_filled = (SELECT COUNT(*) FROM matchmaking_joins WHERE post_id=:pid) WHERE post_id=:pid2")->execute([':pid' => $post_id, ':pid2' => $post_id]);
            setFlash('success', 'You have left the session.');
        } catch (Exception $e) {
            error_log("Unjoin error: " . $e->getMessage());
            setFlash('error', 'Could not leave. Please try again.');
        }
        header("Location: " . Routes::MATCHMAKING);
        exit();


    // ── UNINTEREST ───────────────────────────────────────────────────────────
    case "uninterest":

        $post_id = (int) ($_POST["post_id"] ?? 0);
        if ($post_id <= 0) {
            setFlash('error', 'Invalid session.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        try {
            $pdo->prepare("DELETE FROM matchmaking_interests WHERE post_id=:pid AND member_id=:mid")->execute([':pid' => $post_id, ':mid' => $member_id]);
            setFlash('success', 'Interest removed.');
        } catch (Exception $e) {
            error_log("Uninterest error: " . $e->getMessage());
            setFlash('error', 'Could not remove interest. Please try again.');
        }
        header("Location: " . Routes::MATCHMAKING);
        exit();


    // ── TOGGLE INTEREST ──────────────────────────────────────────────────────
    case "interest":

        $post_id = (int) ($_POST["post_id"] ?? 0);

        if ($post_id <= 0) {
            setFlash('error', 'Invalid session.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        // Prevent the post owner from showing interest in their own post
        $owner_check = $pdo->prepare(
            "SELECT member_id FROM matchmaking_posts WHERE post_id = :pid AND status = 'Open'"
        );
        $owner_check->execute([':pid' => $post_id]);
        $post = $owner_check->fetch();

        if (!$post) {
            setFlash('error', 'Session not found or no longer open.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        if ((int) $post['member_id'] === $member_id) {
            setFlash('error', 'You cannot mark interest in your own post.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        // Check if already interested
        $exists = $pdo->prepare(
            "SELECT interest_id FROM matchmaking_interests
            WHERE post_id = :pid AND member_id = :mid"
        );
        $exists->execute([':pid' => $post_id, ':mid' => $member_id]);

        if ($exists->rowCount() > 0) {
            setFlash('error', 'You have already marked interest in this session.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        try {
            $pdo->prepare(
                "INSERT INTO matchmaking_interests (post_id, member_id) VALUES (:pid, :mid)"
            )->execute([':pid' => $post_id, ':mid' => $member_id]);

            setFlash('success', 'Marked as interested! The host can now see your interest.');
        } catch (Exception $e) {
            error_log("Matchmaking interest error: " . $e->getMessage());
            setFlash('error', 'Could not register your interest. Please try again.');
        }

        header("Location: " . Routes::MATCHMAKING);
        exit();


    // ── ADMIN DELETE (any post) ──────────────────────────────────────────────
    case "admin_delete":

        // Must be admin
        if (empty($_SESSION['is_admin'])) {
            setFlash('error', 'Admin access required.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        $post_id = (int) ($_POST["post_id"] ?? 0);
        if ($post_id <= 0) {
            setFlash('error', 'Invalid session.');
            header("Location: " . Routes::MATCHMAKING);
            exit();
        }

        try {
            $pdo->prepare(
                "UPDATE matchmaking_posts SET status='Cancelled' WHERE post_id=:pid"
            )->execute([':pid' => $post_id]);
            setFlash('success', 'Session removed by admin.');
        } catch (Exception $e) {
            error_log("Admin delete error: " . $e->getMessage());
            setFlash('error', 'Failed to remove the session. Please try again.');
        }

        header("Location: " . Routes::MATCHMAKING);
        exit();


    default:
        header("Location: " . Routes::MATCHMAKING);
        exit();
}