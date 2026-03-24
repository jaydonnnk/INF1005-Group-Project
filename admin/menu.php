<?php
/**
 * admin/menu.php — Admin Menu Item Management Page
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 */

require_once "auth_check.php";
require_once __DIR__ . "/../process/db.php";

$items = $pdo->query("SELECT * FROM menu_items ORDER BY category ASC, name ASC")->fetchAll();

$edit_item = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE item_id = :id");
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $edit_item = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="../">
    <title>Manage Menu - Admin - The Rolling Dice</title>
    <?php include_once __DIR__ . "/../inc/head.inc.php"; ?>
</head>
<body>
    <?php include_once __DIR__ . "/../inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Menu Items</h1>
            <a href="admin/index.php" class="btn btn-outline-primary">
                <span class="material-icons align-middle me-1" aria-hidden="true">arrow_back</span>Admin Home
            </a>
        </div>

        <?php echo displayFlash(); ?>

        <!-- Add / Edit Form -->
        <div class="card mb-4">
            <div class="card-header">
                <strong><?php echo $edit_item ? 'Edit Menu Item' : 'Add New Menu Item'; ?></strong>
            </div>
            <div class="card-body">
                <form method="post" action="admin/process/process_admin.php" enctype="multipart/form-data" class="row g-3">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit_menu_item' : 'add_menu_item'; ?>">
                    <?php if ($edit_item): ?>
                        <input type="hidden" name="item_id" value="<?php echo (int)$edit_item['item_id']; ?>">
                    <?php endif; ?>

                    <div class="col-md-5">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" id="name" name="name" class="form-control" required
                            value="<?php echo $edit_item ? htmlspecialchars($edit_item['name']) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="category" class="form-label">Category</label>
                        <select id="category" name="category" class="form-select">
                            <?php foreach (['Food', 'Drinks', 'Desserts'] as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($edit_item && $edit_item['category'] === $c) ? 'selected' : ''; ?>>
                                    <?php echo $c; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="price" class="form-label">Price ($) *</label>
                        <input type="number" id="price" name="price" class="form-control" step="0.10" min="0" required
                            value="<?php echo $edit_item ? htmlspecialchars($edit_item['price']) : ''; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <?php if ($edit_item): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="available" name="available"
                                    <?php echo $edit_item['available'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="available">Available</label>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="2"><?php echo $edit_item ? htmlspecialchars($edit_item['description']) : ''; ?></textarea>
                    </div>
                    <div class="col-md-5">
                        <label for="image_file" class="form-label">Item Image</label>
                        <?php if ($edit_item && !empty($edit_item['image_url'])): ?>
                            <small class="d-block text-muted mb-1">Current: <?php echo htmlspecialchars($edit_item['image_url']); ?></small>
                        <?php endif; ?>
                        <input type="file" id="image_file" name="image_file" class="form-control"
                            accept="image/jpeg,image/png,image/webp,image/gif">
                        <div class="form-text">Accepted: JPG, PNG, WEBP, GIF · Max 2 MB</div>
                    </div>
                    <div class="col-md-5">
                        <label for="stripe_price_id" class="form-label">Stripe Price ID</label>
                        <input type="text" id="stripe_price_id" name="stripe_price_id" class="form-control"
                            value="<?php echo $edit_item ? htmlspecialchars($edit_item['stripe_price_id']) : ''; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_item ? 'Update' : 'Add'; ?>
                        </button>
                        <?php if ($edit_item): ?>
                            <a href="admin/menu.php" class="btn btn-outline-primary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Menu Items Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle" aria-label="All menu items">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Available</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo (int)$item['item_id']; ?></td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <?php if ($item['available']): ?>
                                    <span class="badge bg-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="admin/menu.php?edit=<?php echo (int)$item['item_id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                    <span class="material-icons" style="font-size:1rem;" aria-hidden="true">edit</span>
                                </a>
                                <form method="post" action="admin/process/process_admin.php" class="d-inline"
                                    onsubmit="return confirm('Delete this menu item?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete_menu_item">
                                    <input type="hidden" name="item_id" value="<?php echo (int)$item['item_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <span class="material-icons" style="font-size:1rem;" aria-hidden="true">delete</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>

    <?php include_once __DIR__ . "/../inc/footer.inc.php"; ?>
</body>
</html>
