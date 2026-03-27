<?php
/**
 * admin/reviews.php — Admin Review Management Page
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 */

require_once "auth_check.php";
require_once __DIR__ . "/../process/db.php";

// Fetch all reviews with game title and member details
$stmt = $pdo->query(
    "SELECT r.*, g.title AS game_title, m.fname, m.lname, m.email
    FROM reviews r
    JOIN games g ON r.game_id = g.game_id
    JOIN members m ON r.member_id = m.member_id
    ORDER BY r.created_at DESC"
);

$reviews = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="../">
    <title>Manage Reviews - Admin - The Rolling Dice</title>
    <?php include_once __DIR__ . "/../inc/head.inc.php"; ?>
</head>
<body>
    <?php include_once __DIR__ . "/../inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>All Reviews</h1>
            <a href="admin/index.php" class="btn btn-outline-primary">
                <span class="material-icons align-middle me-1" aria-hidden="true">arrow_back</span>Admin Home
            </a>
        </div>

        <?php echo displayFlash(); ?>

        <?php if (count($reviews) === 0): ?>
            <p class="text-muted">No reviews found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" aria-label="All reviews">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Game</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $r): ?>
                            <tr>
                                <td><?php echo (int)$r['review_id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars(trim($r['fname'] . ' ' . $r['lname'])); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($r['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($r['game_title']); ?></td>
                                <td><?php echo (int)$r['rating']; ?>/5</td>
                                <td><?php echo $r['comment'] ? htmlspecialchars(mb_strimwidth($r['comment'], 0, 60, '…')) : '<span class="text-muted">—</span>'; ?></td>
                                <td><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
                                <td>
                                    <form method="post" action="admin/process/process_admin.php" class="d-inline"
                                        onsubmit="return confirm('Delete this review?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete_review">
                                        <input type="hidden" name="review_id" value="<?php echo (int)$r['review_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </main>

    <?php include_once __DIR__ . "/../inc/footer.inc.php"; ?>
</body>
</html>
