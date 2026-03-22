<?php
/**
 * wishlist.php — Game Wishlist Page
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 */

session_start();
require_once "process/helpers.php";

if (!isset($_SESSION["member_id"])) {
    header("Location: " . Routes::ROOT_LOGIN);
    exit();
}
$member_id = $_SESSION["member_id"];
require_once "process/db.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>My Wishlist - The Rolling Dice</title>
    <?php include_once "inc/head.inc.php"; ?>
</head>

<body>
    <?php include_once "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>My Wishlist</h1>
            <a href="games.php" class="btn btn-outline-primary">
                <span class="material-icons align-middle me-1" aria-hidden="true">extension</span>Browse Games
            </a>
        </div>

        <?php echo displayFlash(); ?>

        <?php
        // Fetch wishlist with game details
        $stmt = $pdo->prepare(
            "SELECT w.wishlist_id, w.added_at, g.*
            FROM wishlists w
            JOIN games g ON w.game_id = g.game_id
            WHERE w.member_id = :mid
            ORDER BY w.added_at DESC"
        );
        $stmt->execute([':mid' => $member_id]);
        $wishlist = $stmt->fetchAll();
        ?>

        <?php if (count($wishlist) === 0): ?>
            <p class="text-muted">
                Your wishlist is empty. <a href="games.php">Browse the game library</a> and tap
                the heart icon to save games you'd like to play!
            </p>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($wishlist as $item): ?>
                    <div class="col-sm-6 col-lg-4">
                        <div class="card h-100">
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="card-img-top"
                                alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <div class="card-body d-flex flex-column">
                                <h2 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h2>
                                    <div class="mb-2">
                                        <span class="badge badge-genre"><?php echo htmlspecialchars($item['genre']); ?></span>
                                        <span class="badge badge-difficulty-<?php echo strtolower($item['difficulty']); ?>">
                                            <?php echo htmlspecialchars($item['difficulty']); ?>
                                        </span>
                                    </div>
                                    <p class="card-text"><?php echo htmlspecialchars($item['description']); ?></p>
                                    <p class="text-muted small mt-auto">
                                        Added <?php echo date('d M Y', strtotime($item['added_at'])); ?>
                                    </p>
                            </div>
                            <div class="card-footer bg-transparent d-flex gap-2">
                                <a href="bookings.php?action=new" class="btn btn-sm btn-primary">
                                    <span class="material-icons" style="font-size:1rem;" aria-hidden="true">event</span> Book
                                    &amp; Play
                                </a>
                                <form method="post" action="process/process_wishlist.php" class="d-inline"
                                    onsubmit="return confirm('Remove from wishlist?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="wishlist_id" value="<?php echo $item['wishlist_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <span class="material-icons" style="font-size:1rem;"
                                            aria-hidden="true">heart_broken</span> Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <?php include_once "inc/footer.inc.php"; ?>
</body>

</html>