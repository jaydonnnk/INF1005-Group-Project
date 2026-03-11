<?php
session_start();
if (!isset($_SESSION["member_id"])) {
    header("Location: login.php");
    exit();
}
$member_id = $_SESSION["member_id"];
require_once "process/db.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>My Orders - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>

<body>
    <?php include "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>My Orders</h1>
            <a href="menu.php" class="btn btn-primary">
                <span class="material-icons align-middle me-1" aria-hidden="true">restaurant</span>Order More
            </a>
        </div>

        <?php echo displayFlash(); ?>

        <?php
        // Fetch all orders with their items
        $stmt = $pdo->prepare(
            "SELECT * FROM orders WHERE member_id = :mid ORDER BY order_date DESC"
        );
        $stmt->execute([':mid' => $member_id]);
        $orders = $stmt->fetchAll();
        ?>

        <?php if (count($orders) === 0): ?>
            <p class="text-muted">You have no orders yet. Head to the <a href="menu.php">menu</a> to start ordering!</p>
        <?php else: ?>

            <?php foreach ($orders as $order): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Order #<?php echo $order['order_id']; ?></strong>
                            <span
                                class="text-muted ms-2"><?php echo date('d M Y, g:i A', strtotime($order['order_date'])); ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?php
                            $badge = match ($order['status']) {
                                'Pending' => 'bg-warning text-dark',
                                'Preparing' => 'bg-info',
                                'Completed' => 'bg-success',
                                'Cancelled' => 'bg-secondary',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($order['status']); ?></span>

                            <?php if ($order['status'] === 'Pending'): ?>
                                <form method="post" action="process/process_order.php" class="d-inline"
                                    onsubmit="return confirm('Cancel this order?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel order"
                                        aria-label="Cancel order">
                                        <span class="material-icons" style="font-size:1rem;" aria-hidden="true">cancel</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        $items_stmt = $pdo->prepare(
                            "SELECT oi.*, mi.name AS item_name
                             FROM order_items oi
                             JOIN menu_items mi ON oi.item_id = mi.item_id
                             WHERE oi.order_id = :oid"
                        );
                        $items_stmt->execute([':oid' => $order['order_id']]);
                        $items = $items_stmt->fetchAll();
                        ?>
                        <table class="table table-sm mb-0" aria-label="Order items">
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col" class="text-center">Qty</th>
                                    <th scope="col" class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="text-end fw-bold">Total:</td>
                                    <td class="text-end fw-bold text-caramel">
                                        $<?php echo number_format($order['total_amount'], 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>

</html>