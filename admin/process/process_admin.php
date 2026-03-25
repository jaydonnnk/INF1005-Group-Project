<?php
/**
 * process_admin.php — Admin CRUD Actions
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Handles all admin POST actions: games, menu items, bookings, orders, members.
 */

session_start();
require_once __DIR__ . "/../../process/helpers.php";
require_once __DIR__ . "/../../process/db.php";
require_once __DIR__ . "/../../process/booking_emails.php";

// Must be admin
if (!isset($_SESSION['member_id']) || empty($_SESSION['is_admin'])) {
    header("Location: " . Routes::ADMIN_DASH);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . Routes::ADMIN_HOME);
    exit();
}

validateCsrf(Routes::ADMIN_HOME);

$action = $_POST['action'] ?? '';

// ---------------------------------------------------------------------------
// Helper: handle an uploaded image file.
//
// $field        — the $_FILES key (e.g. 'image_file')
// $subfolder    — destination subfolder inside assets/images/ (e.g. 'games')
// $current_url  — existing image_url from DB; returned unchanged if no new
//                 file is uploaded (allows edit without replacing the image)
//
// Returns the relative URL to store in the DB (e.g. "assets/images/games/abc.jpg")
// or throws a RuntimeException with a user-friendly message on failure.
// ---------------------------------------------------------------------------
function handleImageUpload(string $field, string $subfolder, string $current_url = ''): string
{
    // No file chosen — keep the existing URL (edit flow)
    if (empty($_FILES[$field]['name'])) {
        return $current_url;
    }

    $file  = $_FILES[$field];
    $error = $file['error'];

    if ($error !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];
        throw new RuntimeException($msgs[$error] ?? 'Unknown upload error.');
    }

    // 2 MB hard cap
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image must be 2 MB or smaller.');
    }

    // Validate MIME via file content, not the browser-supplied type
    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed_mime, true)) {
        throw new RuntimeException('Only JPG, PNG, WEBP, and GIF images are allowed.');
    }

    $ext_map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $ext = $ext_map[$mime];

    // Build destination path relative to the project root.
    // __FILE__ is root-folder/admin/process/process_admin.php
    // so two levels up lands at root-folder/.
    $root      = dirname(__DIR__, 2);                   // root-folder/
    $dest_dir  = $root . '/assets/images/' . $subfolder;

    if (!is_dir($dest_dir) && !mkdir($dest_dir, 0755, true)) {
        throw new RuntimeException('Could not create image upload directory.');
    }

    // Use a random hex name to avoid collisions and prevent guessable paths
    $filename  = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest_path = $dest_dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        throw new RuntimeException('Failed to save the uploaded image.');
    }

    // Return a root-relative URL for use in <img src="...">
    return 'assets/images/' . $subfolder . '/' . $filename;
}

// ---------------------------------------------------------------------------

try {
    switch ($action) {

        // Games
        case 'add_game':
            $title          = sanitizeInput($_POST['title'] ?? '');
            $description    = sanitizeInput($_POST['description'] ?? '');
            $min_players    = (int)($_POST['min_players'] ?? 1);
            $max_players    = (int)($_POST['max_players'] ?? 4);
            $genre          = sanitizeInput($_POST['genre'] ?? '');
            $difficulty     = $_POST['difficulty'] ?? 'Medium';
            $price_per_hour = number_format((float)($_POST['price_per_hour'] ?? 5.00), 2, '.', '');
            $quantity       = (int)($_POST['quantity'] ?? 3);
            $stripe_price_id = sanitizeInput($_POST['stripe_price_id'] ?? '');

            if (empty($title)) {
                setFlash('error', 'Game title is required.');
                header("Location: " . Routes::ADMIN_GAMES);
                exit();
            }

            $image_url = handleImageUpload('image_file', 'games');

            $stmt = $pdo->prepare(
                "INSERT INTO games (title, description, min_players, max_players, genre, difficulty, image_url, price_per_hour, quantity, stripe_price_id)
                VALUES (:title, :desc, :minp, :maxp, :genre, :diff, :img, :price, :qty, :stripe)"
            );
            $stmt->execute([
                ':title' => $title, ':desc' => $description,
                ':minp'  => $min_players, ':maxp' => $max_players,
                ':genre' => $genre, ':diff' => $difficulty,
                ':img'   => $image_url, ':price' => $price_per_hour,
                ':qty'   => $quantity, ':stripe' => $stripe_price_id,
            ]);
            setFlash('success', 'Game added successfully.');
            header("Location: " . Routes::ADMIN_GAMES);
            exit();

        case 'edit_game':
            $game_id        = (int)($_POST['game_id'] ?? 0);
            $title          = sanitizeInput($_POST['title'] ?? '');
            $description    = sanitizeInput($_POST['description'] ?? '');
            $min_players    = (int)($_POST['min_players'] ?? 1);
            $max_players    = (int)($_POST['max_players'] ?? 4);
            $genre          = sanitizeInput($_POST['genre'] ?? '');
            $difficulty     = $_POST['difficulty'] ?? 'Medium';
            $price_per_hour = number_format((float)($_POST['price_per_hour'] ?? 5.00), 2, '.', '');
            $quantity       = (int)($_POST['quantity'] ?? 3);
            $stripe_price_id = sanitizeInput($_POST['stripe_price_id'] ?? '');

            if ($game_id <= 0 || empty($title)) {
                setFlash('error', 'Invalid game data.');
                header("Location: " . Routes::ADMIN_GAMES);
                exit();
            }

            // Fetch existing image URL so we can keep it if no new file is uploaded
            $cur = $pdo->prepare("SELECT image_url FROM games WHERE game_id = :id");
            $cur->execute([':id' => $game_id]);
            $current_image = $cur->fetchColumn() ?: '';

            $image_url = handleImageUpload('image_file', 'games', $current_image);

            $stmt = $pdo->prepare(
                "UPDATE games
                SET title = :title, description = :desc, min_players = :minp, max_players = :maxp,
                    genre = :genre, difficulty = :diff, image_url = :img, price_per_hour = :price,
                    quantity = :qty, stripe_price_id = :stripe
                WHERE game_id = :id"
            );
            $stmt->execute([
                ':title' => $title, ':desc' => $description,
                ':minp'  => $min_players, ':maxp' => $max_players,
                ':genre' => $genre, ':diff' => $difficulty,
                ':img'   => $image_url, ':price' => $price_per_hour,
                ':qty'   => $quantity, ':stripe' => $stripe_price_id,
                ':id'    => $game_id,
            ]);
            setFlash('success', 'Game updated successfully.');
            header("Location: " . Routes::ADMIN_GAMES);
            exit();

        case 'delete_game':
            $game_id = (int)($_POST['game_id'] ?? 0);
            if ($game_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM games WHERE game_id = :id");
                $stmt->execute([':id' => $game_id]);
                setFlash('success', 'Game deleted.');
            }
            header("Location: " . Routes::ADMIN_GAMES);
            exit();

        // Menu Items
        case 'add_menu_item':
            $name            = sanitizeInput($_POST['name'] ?? '');
            $description     = sanitizeInput($_POST['description'] ?? '');
            $price           = number_format((float)($_POST['price'] ?? 0), 2, '.', '');
            $category        = $_POST['category'] ?? 'Food';
            $stripe_price_id = sanitizeInput($_POST['stripe_price_id'] ?? '');

            if (empty($name) || $price <= 0) {
                setFlash('error', 'Name and valid price are required.');
                header("Location: " . Routes::ADMIN_MENU);
                exit();
            }

            $image_url = handleImageUpload('image_file', 'menu');

            $stmt = $pdo->prepare(
                "INSERT INTO menu_items (name, description, price, category, image_url, stripe_price_id)
                VALUES (:name, :desc, :price, :cat, :img, :stripe)"
            );
            $stmt->execute([
                ':name'   => $name, ':desc' => $description, ':price' => $price,
                ':cat'    => $category, ':img' => $image_url, ':stripe' => $stripe_price_id,
            ]);
            setFlash('success', 'Menu item added.');
            header("Location: " . Routes::ADMIN_MENU);
            exit();

        case 'edit_menu_item':
            $item_id         = (int)($_POST['item_id'] ?? 0);
            $name            = sanitizeInput($_POST['name'] ?? '');
            $description     = sanitizeInput($_POST['description'] ?? '');
            $price           = number_format((float)($_POST['price'] ?? 0), 2, '.', '');
            $category        = $_POST['category'] ?? 'Food';
            $available       = isset($_POST['available']) ? 1 : 0;
            $stripe_price_id = sanitizeInput($_POST['stripe_price_id'] ?? '');

            if ($item_id <= 0 || empty($name)) {
                setFlash('error', 'Invalid menu item data.');
                header("Location: " . Routes::ADMIN_MENU);
                exit();
            }

            // Fetch existing image URL so we can keep it if no new file is uploaded
            $cur = $pdo->prepare("SELECT image_url FROM menu_items WHERE item_id = :id");
            $cur->execute([':id' => $item_id]);
            $current_image = $cur->fetchColumn() ?: '';

            $image_url = handleImageUpload('image_file', 'menu', $current_image);

            $stmt = $pdo->prepare(
                "UPDATE menu_items
                SET name = :name, description = :desc, price = :price,
                    category = :cat, image_url = :img, available = :avail, stripe_price_id = :stripe
                WHERE item_id = :id"
            );
            $stmt->execute([
                ':name'   => $name, ':desc' => $description, ':price' => $price,
                ':cat'    => $category, ':img' => $image_url, ':avail' => $available,
                ':stripe' => $stripe_price_id, ':id' => $item_id,
            ]);
            setFlash('success', 'Menu item updated.');
            header("Location: " . Routes::ADMIN_MENU);
            exit();

        case 'delete_menu_item':
            $item_id = (int)($_POST['item_id'] ?? 0);
            if ($item_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM menu_items WHERE item_id = :id");
                $stmt->execute([':id' => $item_id]);
                setFlash('success', 'Menu item deleted.');
            }
            header("Location: " . Routes::ADMIN_MENU);
            exit();

        // Bookings
        case 'update_booking_status':
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $status     = $_POST['status'] ?? '';
            $valid_statuses = ['Confirmed', 'Completed', 'Cancelled'];

            if ($booking_id > 0 && in_array($status, $valid_statuses, true)) {
                $stmt = $pdo->prepare("UPDATE bookings SET status = :status WHERE booking_id = :id");
                $stmt->execute([':status' => $status, ':id' => $booking_id]);

                // Send appropriate booking email (failure is non-blocking)
                try {
                    if ($status === 'Cancelled') {
                        sendBookingCancellation($pdo, $booking_id);
                    } else {
                        sendBookingUpdate($pdo, $booking_id);
                    }
                } catch (Exception $e) {
                    error_log("Admin booking email failed: " . $e->getMessage());
                }

                setFlash('success', 'Booking status updated.');
            }
            header("Location: " . Routes::ADMIN_BOOKINGS);
            exit();

        // Orders
        case 'update_order_status':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $status   = $_POST['status'] ?? '';
            $valid_statuses = ['Pending', 'Preparing', 'Completed', 'Cancelled'];

            if ($order_id > 0 && in_array($status, $valid_statuses, true)) {
                $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE order_id = :id");
                $stmt->execute([':status' => $status, ':id' => $order_id]);
                setFlash('success', 'Order status updated.');
            }
            header("Location: " . Routes::ADMIN_ORDERS);
            exit();

        // Members
        case 'toggle_admin':
            $target_id = (int)($_POST['member_id'] ?? 0);
            $new_val   = (int)($_POST['is_admin'] ?? 0);

            // Prevent removing own admin
            if ($target_id === (int)$_SESSION['member_id']) {
                setFlash('error', 'You cannot change your own admin status.');
                header("Location: " . Routes::ADMIN_MEMBERS);
                exit();
            }

            if ($target_id > 0) {
                $stmt = $pdo->prepare("UPDATE members SET is_admin = :val WHERE member_id = :id");
                $stmt->execute([':val' => $new_val ? 1 : 0, ':id' => $target_id]);
                setFlash('success', 'Member admin status updated.');
            }
            header("Location: " . Routes::ADMIN_MEMBERS);
            exit();

        // Disable member account
        case 'disable_member':
            $target_id = (int)($_POST['member_id'] ?? 0);

            if ($target_id === (int)$_SESSION['member_id']) {
                setFlash('error', 'You cannot disable your own account.');
                header("Location: " . Routes::ADMIN_MEMBERS);
                exit();
            }

            if ($target_id > 0) {
                $stmt = $pdo->prepare("UPDATE members SET account_status = 'disabled' WHERE member_id = :id");
                $stmt->execute([':id' => $target_id]);
                setFlash('success', 'Member account has been disabled.');
            }
            header("Location: " . Routes::ADMIN_MEMBERS);
            exit();

        // Reactivate member account
        case 'reactivate_member':
            $target_id = (int)($_POST['member_id'] ?? 0);

            if ($target_id > 0) {
                $stmt = $pdo->prepare("UPDATE members SET account_status = 'active' WHERE member_id = :id");
                $stmt->execute([':id' => $target_id]);
                setFlash('success', 'Member account has been reactivated.');
            }
            header("Location: " . Routes::ADMIN_MEMBERS);
            exit();

        // Permanently delete member account
        case 'delete_member':
            $target_id = (int)($_POST['member_id'] ?? 0);

            if ($target_id === (int)$_SESSION['member_id']) {
                setFlash('error', 'You cannot delete your own account.');
                header("Location: " . Routes::ADMIN_MEMBERS);
                exit();
            }

            if ($target_id > 0) {
                // CASCADE on foreign keys will remove bookings, orders, reviews, etc.
                $stmt = $pdo->prepare("DELETE FROM members WHERE member_id = :id");
                $stmt->execute([':id' => $target_id]);
                setFlash('success', 'Member account and all associated data have been permanently deleted.');
            }
            header("Location: " . Routes::ADMIN_MEMBERS);
            exit();

        default:
            setFlash('error', 'Unknown action.');
            header("Location: " . Routes::ADMIN_HOME);
            exit();
    }
} catch (RuntimeException $e) {
    // User-facing upload/validation errors
    setFlash('error', $e->getMessage());
    // Redirect back to the appropriate admin page
    $back = match(true) {
        str_contains($action, 'game')      => Routes::ADMIN_GAMES,
        str_contains($action, 'menu_item') => Routes::ADMIN_MENU,
        default                            => Routes::ADMIN_HOME,
    };
    header("Location: " . $back);
    exit();
} catch (PDOException $e) {
    error_log("Admin error: " . $e->getMessage());
    setFlash('error', 'A database error occurred.');
    header("Location: " . Routes::ADMIN_HOME);
    exit();
}
