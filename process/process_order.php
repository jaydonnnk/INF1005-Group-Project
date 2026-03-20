<?php
/**
 * Process Order CRUD Operations
 *
 *
 * add_item: Adds an item to the member's current pending order (creates one if none exists).
 * cancel:   Cancels a pending order.
 */

session_start();
require_once "helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . Routes::ORDERS);
    exit();
}

if (!isset($_SESSION["member_id"])) {
    header("Location: " . Routes::LOGIN);
    exit();
}

validateCsrf(Routes::MENU);

$member_id = $_SESSION["member_id"];
require_once "db.php";

$action = $_POST["action"] ?? "";

switch ($action) {

    // ---- ADD ITEM TO ORDER ----
    case "add_item":
        $item_id = (int) ($_POST["item_id"] ?? 0);
        if ($item_id <= 0) {
            setFlash('error', 'Invalid item.');
            header("Location: " . Routes::MENU);
            exit();
        }

        // Get item price
        $item_stmt = $pdo->prepare("SELECT price FROM menu_items WHERE item_id = :id AND available = 1");
        $item_stmt->execute([':id' => $item_id]);
        $menu_item = $item_stmt->fetch();

        if (!$menu_item) {
            setFlash('error', 'Item not available.');
            header("Location: " . Routes::MENU);
            exit();
        }

        $price = (float) $menu_item['price'];

        // Use a transaction to keep order data consistent
        $pdo->beginTransaction();
        try {
            // Find or create a pending order
            $order_stmt = $pdo->prepare("SELECT order_id, total_amount FROM orders WHERE member_id = :mid AND status = 'Pending' LIMIT 1");
            $order_stmt->execute([':mid' => $member_id]);
            $order = $order_stmt->fetch();

            if ($order) {
                $order_id = $order['order_id'];
            } else {
                // Create new pending order
                $create_stmt = $pdo->prepare("INSERT INTO orders (member_id, total_amount) VALUES (:mid, 0.00)");
                $create_stmt->execute([':mid' => $member_id]);
                $order_id = $pdo->lastInsertId();
            }

            // Check if item already in order (increment quantity)
            $existing = $pdo->prepare("SELECT order_item_id, quantity FROM order_items WHERE order_id = :oid AND item_id = :iid");
            $existing->execute([':oid' => $order_id, ':iid' => $item_id]);
            $existing_item = $existing->fetch();

            if ($existing_item) {
                $new_qty = $existing_item['quantity'] + 1;
                $new_sub = $price * $new_qty;
                $update_item = $pdo->prepare("UPDATE order_items SET quantity = :qty, subtotal = :sub WHERE order_item_id = :oiid");
                $update_item->execute([':qty' => $new_qty, ':sub' => $new_sub, ':oiid' => $existing_item['order_item_id']]);
            } else {
                $insert_item = $pdo->prepare("INSERT INTO order_items (order_id, item_id, quantity, subtotal) VALUES (:oid, :iid, 1, :sub)");
                $insert_item->execute([':oid' => $order_id, ':iid' => $item_id, ':sub' => $price]);
            }

            // Update order total
            $total_stmt = $pdo->prepare("SELECT SUM(subtotal) AS total FROM order_items WHERE order_id = :oid");
            $total_stmt->execute([':oid' => $order_id]);
            $new_total = $total_stmt->fetch()['total'];

            $update_order = $pdo->prepare("UPDATE orders SET total_amount = :total WHERE order_id = :oid");
            $update_order->execute([':total' => $new_total, ':oid' => $order_id]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Order error: " . $e->getMessage());
            setFlash('error', 'Failed to add item. Please try again.');
            header("Location: " . Routes::MENU);
            exit();
        }

        // Whitelist allowed redirect targets to prevent open redirect
        $allowed_pages = ['menu', 'orders'];
        $from = in_array($_POST["from_page"] ?? "", $allowed_pages) ? $_POST["from_page"] : "menu";

        setFlash('success', 'Item added to your order!');
        header("Location: ../$from.php");
        exit();

    // ---- CANCEL ORDER ----
    case "cancel":
        $order_id = (int) ($_POST["order_id"] ?? 0);
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE order_id = :oid AND member_id = :mid AND status = 'Pending'");
        $stmt->execute([':oid' => $order_id, ':mid' => $member_id]);

        setFlash('success', 'Order cancelled.');
        header("Location: " . Routes::ORDERS);
        exit();

    default:
        header("Location: " . Routes::ORDERS);
        exit();
}
