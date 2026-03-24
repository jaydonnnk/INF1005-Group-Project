<?php
/**
 * admin/games.php — Admin Game Management Page
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 */

require_once "auth_check.php";
require_once __DIR__ . "/../process/db.php";

$games = $pdo->query("SELECT * FROM games ORDER BY title ASC")->fetchAll();

// If editing, fetch that game
$edit_game = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM games WHERE game_id = :id");
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $edit_game = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="../">
    <title>Manage Games - Admin - The Rolling Dice</title>
    <?php include_once __DIR__ . "/../inc/head.inc.php"; ?>
</head>
<body>
    <?php include_once __DIR__ . "/../inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Games</h1>
            <a href="admin/index.php" class="btn btn-outline-primary">
                <span class="material-icons align-middle me-1" aria-hidden="true">arrow_back</span>Admin Home
            </a>
        </div>

        <?php echo displayFlash(); ?>

        <!-- Add / Edit Game Form -->
        <div class="card mb-4">
            <div class="card-header">
                <strong><?php echo $edit_game ? 'Edit Game' : 'Add New Game'; ?></strong>
            </div>
            <div class="card-body">
                <form method="post" action="admin/process/process_admin.php" enctype="multipart/form-data" class="row g-3">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="<?php echo $edit_game ? 'edit_game' : 'add_game'; ?>">
                    <?php if ($edit_game): ?>
                        <input type="hidden" name="game_id" value="<?php echo (int)$edit_game['game_id']; ?>">
                    <?php endif; ?>

                    <div class="col-md-6">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" id="title" name="title" class="form-control" required
                            value="<?php echo $edit_game ? htmlspecialchars($edit_game['title']) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="genre" class="form-label">Genre</label>
                        <input type="text" id="genre" name="genre" class="form-control"
                            value="<?php echo $edit_game ? htmlspecialchars($edit_game['genre']) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="difficulty" class="form-label">Difficulty</label>
                        <select id="difficulty" name="difficulty" class="form-select">
                            <?php foreach (['Easy', 'Medium', 'Hard'] as $d): ?>
                                <option value="<?php echo $d; ?>" <?php echo ($edit_game && $edit_game['difficulty'] === $d) ? 'selected' : ''; ?>>
                                    <?php echo $d; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="2"><?php echo $edit_game ? htmlspecialchars($edit_game['description']) : ''; ?></textarea>
                    </div>
                    <div class="col-md-2">
                        <label for="min_players" class="form-label">Min Players</label>
                        <input type="number" id="min_players" name="min_players" class="form-control" min="1"
                            value="<?php echo $edit_game ? (int)$edit_game['min_players'] : 1; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="max_players" class="form-label">Max Players</label>
                        <input type="number" id="max_players" name="max_players" class="form-control" min="1"
                            value="<?php echo $edit_game ? (int)$edit_game['max_players'] : 4; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="price_per_hour" class="form-label">$/hr</label>
                        <input type="number" id="price_per_hour" name="price_per_hour" class="form-control" step="0.50" min="0"
                            value="<?php echo $edit_game ? htmlspecialchars($edit_game['price_per_hour']) : '5.00'; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="quantity" class="form-label">Copies</label>
                        <input type="number" id="quantity" name="quantity" class="form-control" min="0"
                            value="<?php echo $edit_game ? (int)$edit_game['quantity'] : 3; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="image_file" class="form-label">Game Image</label>
                        <?php if ($edit_game && !empty($edit_game['image_url'])): ?>
                            <small class="d-block text-muted mb-1">Current: <?php echo htmlspecialchars($edit_game['image_url']); ?></small>
                        <?php endif; ?>
                        <input type="file" id="image_file" name="image_file" class="form-control"
                            accept="image/jpeg,image/png,image/webp,image/gif">
                        <div class="form-text">Accepted: JPG, PNG, WEBP, GIF · Max 2 MB</div>
                    </div>
                    <div class="col-md-6">
                        <label for="stripe_price_id" class="form-label">Stripe Price ID</label>
                        <input type="text" id="stripe_price_id" name="stripe_price_id" class="form-control"
                            value="<?php echo $edit_game ? htmlspecialchars($edit_game['stripe_price_id']) : ''; ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_game ? 'Update Game' : 'Add Game'; ?>
                        </button>
                        <?php if ($edit_game): ?>
                            <a href="admin/games.php" class="btn btn-outline-primary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Games Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle" aria-label="All games">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Genre</th>
                        <th>Difficulty</th>
                        <th>Players</th>
                        <th>$/hr</th>
                        <th>Copies</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games as $g): ?>
                        <tr>
                            <td><?php echo (int)$g['game_id']; ?></td>
                            <td><?php echo htmlspecialchars($g['title']); ?></td>
                            <td><?php echo htmlspecialchars($g['genre']); ?></td>
                            <td><?php echo htmlspecialchars($g['difficulty']); ?></td>
                            <td><?php echo (int)$g['min_players']; ?>-<?php echo (int)$g['max_players']; ?></td>
                            <td>$<?php echo number_format($g['price_per_hour'], 2); ?></td>
                            <td><?php echo (int)$g['quantity']; ?></td>
                            <td>
                                <a href="admin/games.php?edit=<?php echo (int)$g['game_id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                    <span class="material-icons" style="font-size:1rem;" aria-hidden="true">edit</span>
                                </a>
                                <form method="post" action="admin/process/process_admin.php" class="d-inline"
                                    onsubmit="return confirm('Delete this game?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete_game">
                                    <input type="hidden" name="game_id" value="<?php echo (int)$g['game_id']; ?>">
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
