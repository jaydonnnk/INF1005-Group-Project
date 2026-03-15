<?php
require_once "auth_check.php";
require_once __DIR__ . "/../process/db.php";

$stmt = $pdo->query(
    "SELECT o.*, m.fname, m.lname, m.email
    FROM orders o
    JOIN members m ON o.member_id = m.member_id
    ORDER BY o.order_date DESC"
);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="../">
    <title>Manage Orders - Admin - The Rolling Dice</title>
    <?php include __DIR__ . "/../inc/head.inc.php"; ?>
</head>
<body>
    <?php include __DIR__ . "/../inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>All Orders</h1>
            <a href="admin/index.php" class="btn btn-outline-primary">
                <span class="material-icons align-middle me-1" aria-hidden="true">arrow_back</span>Admin Home
            </a>
        </div>

        <?php echo displayFlash(); ?>

        <?php if (count($orders) === 0): ?>
            <p class="text-muted">No orders found.</p>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Order #<?php echo (int)$order['order_id']; ?></strong>
                            <span class="text-muted ms-2"><?php echo date('d M Y, g:i A', strtotime($order['order_date'])); ?></span>
                            <br><small class="text-muted">
                                <?php echo htmlspecialchars(trim($order['fname'] . ' ' . $order['lname'])); ?>
                                (<?php echo htmlspecialchars($order['email']); ?>)
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?php
                            $badge = match($order['status']) {
                                'Pending' => 'bg-warning text-dark',
                                'Preparing' => 'bg-info',
                                'Completed' => 'bg-success',
                                'Cancelled' => 'bg-secondary',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($order['status']); ?></span>

                            <form method="post" action="admin/process/process_admin.php" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                                <select name="status" class="form-select form-select-sm d-inline-block" style="width:auto;"
                                        onchange="this.form.submit();" aria-label="Update status">
                                    <?php foreach (['Pending', 'Preparing', 'Completed', 'Cancelled'] as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo ($order['status'] === $s) ? 'selected' : ''; ?>>
                                            <?php echo $s; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
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
                                        <td class="text-center"><?php echo (int)$item['quantity']; ?></td>
                                        <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="text-end fw-bold">Total:</td>
                                    <td class="text-end fw-bold text-caramel">$<?php echo number_format($order['total_amount'], 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>

    <?php include __DIR__ . "/../inc/footer.inc.php"; ?>
</body>
</html>
