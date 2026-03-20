<?php
/**
 * Admin CRUD Actions
 *
 *
 * Handles all admin POST actions: games, menu items, bookings, orders, members.
 */

session_start();
require_once __DIR__ . "/../../process/helpers.php";
require_once __DIR__ . "/../../process/db.php";

define('ADMIN_HOME', '../index.php');
define('ADMIN_GAMES', '../games.php');
define('ADMIN_MENU', '../menu.php');

// Must be admin
if (!isset($_SESSION['member_id']) || empty($_SESSION['is_admin'])) {
    header("Location: ../../dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . ADMIN_HOME);
    exit();
}

validateCsrf(ADMIN_HOME);

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        // Games
        case 'add_game':
            $title = sanitizeInput($_POST['title'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $min_players = (int)($_POST['min_players'] ?? 1);
            $max_players = (int)($_POST['max_players'] ?? 4);
            $genre = sanitizeInput($_POST['genre'] ?? '');
            $difficulty = $_POST['difficulty'] ?? 'Medium';
            $image_url = sanitizeInput($_POST['image_url'] ?? '');
            $price_per_hour = number_format((float)($_POST['price_per_hour'] ?? 5.00), 2, '.', '');
            $quantity = (int)($_POST['quantity'] ?? 3);
            $stripe_price_id = sanitizeInput($_POST['stripe_price_id'] ?? '');

            if (empty($title)) {
                setFlash('error', 'Game title is required.');
                header("Location: " . ADMIN_GAMES);
                exit();
            }

            $stmt = $pdo->prepare(
                "INSERT INTO games (title, description, min_players, max_players, genre, difficulty, image_url, price_per_hour, quantity, stripe_price_id)
                VALUES (:title, :desc, :minp, :maxp, :genre, :diff, :img, :price, :qty, :stripe)"
            );
            $stmt->execute([
                ':title' => $title, ':desc' => $description,
                ':minp' => $min_players, ':maxp' => $max_players,
                ':genre' => $genre, ':diff' => $difficulty,
                ':img' => $image_url, ':price' => $price_per_hour,
                ':qty' => $quantity, ':stripe' => $stripe_price_id
            ]);
            setFlash('success', 'Game added successfully.');
            header("Location: " . ADMIN_GAMES);
            exit();

        case 'edit_game':
            $game_id = (int)($_POST['game_id'] ?? 0);
            $title = sanitizeInput($_POST['title'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $min_players = (int)($_POST['min_players'] ?? 1);
            $max_players = (int)($_POST['max_players'] ?? 4);
            $genre = sanitizeInput($_POST['genre'] ?? '');
            $difficulty = $_POST['difficulty'] ?? 'Medium';
            $image_url = sanitizeInput($_POST['image_url'] ?? '');
            $price_per_hour = number_format((float)($_POST['price_per_hour'] ?? 5.00), 2, '.', '');
            $quantity = (int)($_POST['quantity'] ?? 3);
            $stripe_price_id = sanitizeInput($_POST['stripe_price_id'] ?? '');

            if ($game_id <= 0 || empty($title)) {
                setFlash('error', 'Invalid game data.');
                header("Location: " . ADMIN_GAMES);
                exit();
            }

            $stmt = $pdo->prepare(
                "UPDATE games SET title = :title, description = :desc, min_players = :minp, max_players = :maxp,
                genre = :genre, difficulty = :diff, image_url = :img, price_per_hour = :price,
                quantity = :qty, stripe_price_id = :stripe
                WHERE game_id = :id"
            );
            $stmt->execute([
                ':title' => $title, ':desc' => $description,
                ':minp' => $min_players, ':maxp' => $max_players,
                ':genre' => $genre, ':diff' => $difficulty,
                ':img' => $image_url, ':price' => $price_per_hour,
                ':qty' => $quantity, ':stripe' => $stripe_price_id,
                ':id' => $game_id
            ]);
            setFlash('success', 'Game updated successfully.');
            header("Location: " . ADMIN_GAMES);
            exit();

        case 'delete_game':
            $game_id = (int)($_POST['game_id'] ?? 0);
            if ($game_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM games WHERE game_id = :id");
                $stmt->execute([':id' => $game_id]);
                setFlash('success', 'Game deleted.');
            }
            header("Location: " . ADMIN_GAMES);
            exit();

        // Menu Items
        case 'add_menu_item':
            $name = sanitizeInput($_POST['name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $price = number_format((float)($_POST['price'] ?? 0), 2, '.', '');
            $category = $_POST['category'] ?? 'Food';
            $image_url = sanitizeInput($_POST['image_url'] ?? '');
            $stripe_price_id = sanitizeInput($_POST['stripe_price_id'] ?? '');

            if (empty($name) || $price <= 0) {
                setFlash('error', 'Name and valid price are required.');
                header("Location: " . ADMIN_MENU);
                exit();
            }

            $stmt = $pdo->prepare(
                "INSERT INTO menu_items (name, description, price, category, image_url, stripe_price_id)
                VALUES (:name, :desc, :price, :cat, :img, :stripe)"
            );
            $stmt->execute([
                ':name' => $name, ':desc' => $description, ':price' => $price,
                ':cat' => $category, ':img' => $image_url, ':stripe' => $stripe_price_id
            ]);
            setFlash('success', 'Menu item added.');
            header("Location: " . ADMIN_MENU);
            exit();

        case 'edit_menu_item':
            $item_id = (int)($_POST['item_id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $price = number_format((float)($_POST['price'] ?? 0), 2, '.', '');
            $category = $_POST['category'] ?? 'Food';
            $image_url = sanitizeInput($_POST['image_url'] ?? '');
            $available = isset($_POST['available']) ? 1 : 0;
            $stripe_price_id = sanitizeInput($_POST['stripe_price_id'] ?? '');

            if ($item_id <= 0 || empty($name)) {
                setFlash('error', 'Invalid menu item data.');
                header("Location: " . ADMIN_MENU);
                exit();
            }

            $stmt = $pdo->prepare(
                "UPDATE menu_items SET name = :name, description = :desc, price = :price,
                category = :cat, image_url = :img, available = :avail, stripe_price_id = :stripe
                WHERE item_id = :id"
            );
            $stmt->execute([
                ':name' => $name, ':desc' => $description, ':price' => $price,
                ':cat' => $category, ':img' => $image_url, ':avail' => $available,
                ':stripe' => $stripe_price_id, ':id' => $item_id
            ]);
            setFlash('success', 'Menu item updated.');
            header("Location: " . ADMIN_MENU);
            exit();

        case 'delete_menu_item':
            $item_id = (int)($_POST['item_id'] ?? 0);
            if ($item_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM menu_items WHERE item_id = :id");
                $stmt->execute([':id' => $item_id]);
                setFlash('success', 'Menu item deleted.');
            }
            header("Location: " . ADMIN_MENU);
            exit();

        // Bookings
        case 'update_booking_status':
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $valid_statuses = ['Confirmed', 'Completed', 'Cancelled'];

            if ($booking_id > 0 && in_array($status, $valid_statuses, true)) {
                $stmt = $pdo->prepare("UPDATE bookings SET status = :status WHERE booking_id = :id");
                $stmt->execute([':status' => $status, ':id' => $booking_id]);
                setFlash('success', 'Booking status updated.');
            }
            header("Location: ../bookings.php");
            exit();

        // Orders
        case 'update_order_status':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $valid_statuses = ['Pending', 'Preparing', 'Completed', 'Cancelled'];

            if ($order_id > 0 && in_array($status, $valid_statuses, true)) {
                $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE order_id = :id");
                $stmt->execute([':status' => $status, ':id' => $order_id]);
                setFlash('success', 'Order status updated.');
            }
            header("Location: ../orders.php");
            exit();

        // Members
        case 'toggle_admin':
            $target_id = (int)($_POST['member_id'] ?? 0);
            $new_val = (int)($_POST['is_admin'] ?? 0);

            // Prevent removing own admin
            if ($target_id === (int)$_SESSION['member_id']) {
                setFlash('error', 'You cannot change your own admin status.');
                header("Location: ../members.php");
                exit();
            }

            if ($target_id > 0) {
                $stmt = $pdo->prepare("UPDATE members SET is_admin = :val WHERE member_id = :id");
                $stmt->execute([':val' => $new_val ? 1 : 0, ':id' => $target_id]);
                setFlash('success', 'Member admin status updated.');
            }
            header("Location: ../members.php");
            exit();

        default:
            setFlash('error', 'Unknown action.');
            header("Location: " . ADMIN_HOME);
            exit();
    }
} catch (PDOException $e) {
    error_log("Admin error: " . $e->getMessage());
    setFlash('error', 'A database error occurred.');
    header("Location: " . ADMIN_HOME);
    exit();
}
