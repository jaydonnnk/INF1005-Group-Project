<?php
/**
 * reviews.php — Game Reviews Page
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

// Determine mode
$show_form = isset($_GET['action']) && in_array($_GET['action'], ['new', 'edit']);
$edit_id = isset($_GET['review_id']) ? (int) $_GET['review_id'] : 0;
$preselect_game = isset($_GET['game_id']) ? (int) $_GET['game_id'] : 0;
$review_data = null;

// game_id with no action = show all reviews for that game (read-only)
$show_game_reviews = ($preselect_game > 0 && !isset($_GET['action']));

if ($show_form && $_GET['action'] === 'edit' && $edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE review_id = :rid AND member_id = :mid");
    $stmt->execute([':rid' => $edit_id, ':mid' => $member_id]);
    $review_data = $stmt->fetch();
    if (!$review_data) {
        setFlash('error', 'Review not found.');
        header("Location: reviews.php");
        exit();
    }
    $preselect_game = $review_data['game_id'];
}

$all_games = $pdo->query("SELECT game_id, title FROM games ORDER BY title ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>My Reviews - The Rolling Dice</title>
    <?php include_once "inc/head.inc.php"; ?>
</head>

<body>
    <?php include_once "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo $show_game_reviews ? htmlspecialchars($pdo->query("SELECT title FROM games WHERE game_id = $preselect_game")->fetchColumn()) . ' Reviews' : 'My Reviews'; ?></h1>
            <?php if (!$show_form && !$show_game_reviews): ?>
                <a href="reviews.php?action=new" class="btn btn-primary">
                    <span class="material-icons align-middle me-1" aria-hidden="true">add</span>Write a Review
                </a>
            <?php elseif ($show_game_reviews): ?>
                <a href="reviews.php" class="btn btn-outline-primary">
                    <span class="material-icons align-middle me-1" aria-hidden="true">arrow_back</span>My Reviews
                </a>
            <?php endif; ?>
        </div>

        <?php echo displayFlash(); ?>

        <?php if ($show_game_reviews): ?>
            <!-- ALL REVIEWS FOR THIS GAME -->
            <?php
            $stmt = $pdo->prepare(
                "SELECT r.*, m.fname, m.lname
                FROM reviews r
                JOIN members m ON r.member_id = m.member_id
                WHERE r.game_id = :gid
                ORDER BY r.created_at DESC"
            );
            $stmt->execute([':gid' => $preselect_game]);
            $game_reviews = $stmt->fetchAll();
            ?>
            <?php if (count($game_reviews) === 0): ?>
                <p class="text-muted">No reviews yet for this game.</p>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($game_reviews as $r): ?>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <p class="text-muted small mb-2">by <?php echo htmlspecialchars(trim($r['fname'] . ' ' . $r['lname'])); ?></p>
                                    <div class="star-rating mb-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="material-icons <?php echo $i <= $r['rating'] ? '' : 'empty'; ?>" aria-hidden="true">star</span>
                                        <?php endfor; ?>
                                        <span class="visually-hidden"><?php echo $r['rating']; ?> out of 5 stars</span>
                                    </div>
                                    <?php if (!empty($r['comment'])): ?>
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($r['comment'])); ?></p>
                                    <?php endif; ?>
                                    <p class="text-muted small"><?php echo date('d M Y', strtotime($r['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($show_form): ?>
            <!-- CREATE / EDIT REVIEW FORM -->
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <h2><?php echo $review_data ? 'Edit Review' : 'Write a Review'; ?></h2>

                    <form action="process/process_review.php" method="post" class="needs-validation" novalidate
                        aria-label="Review form">

                        <?php echo csrfField(); ?>

                        <p class="text-muted small"><span class="text-danger">*</span> indicates a required field.</p>
                        <input type="hidden" name="action" value="<?php echo $review_data ? 'update' : 'create'; ?>">
                        <?php if ($review_data): ?>
                            <input type="hidden" name="review_id" value="<?php echo $review_data['review_id']; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="game_id" class="form-label">Game: <span class="text-danger">*</span></label>
                            <select id="game_id" name="game_id" class="form-select" required>
                                <option value="">Select a game</option>
                                <?php foreach ($all_games as $g): ?>
                                    <option value="<?php echo $g['game_id']; ?>" <?php echo ($preselect_game == $g['game_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a game.</div>
                        </div>

                        <div class="mb-3">
                            <label for="rating" class="form-label">Rating: <span class="text-danger">*</span></label>
                            <select id="rating" name="rating" class="form-select" required>
                                <option value="">Select rating</option>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($review_data && $review_data['rating'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo str_repeat('★', $i) . str_repeat('☆', 5 - $i) . " ($i/5)"; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <div class="invalid-feedback">Please select a rating.</div>
                        </div>

                        <div class="mb-3">
                            <label for="comment" class="form-label">Your Review:</label>
                            <textarea id="comment" name="comment" class="form-control" rows="4" maxlength="1000"
                                placeholder="What did you think of this game?"><?php echo $review_data ? htmlspecialchars($review_data['comment']) : ''; ?></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-icons align-middle me-1" aria-hidden="true">save</span>
                                <?php echo $review_data ? 'Update Review' : 'Submit Review'; ?>
                            </button>
                            <a href="games.php" class="btn btn-outline-primary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- REVIEW LIST -->
            <?php
            $stmt = $pdo->prepare(
                "SELECT r.*, g.title AS game_title
                FROM reviews r
                JOIN games g ON r.game_id = g.game_id
                WHERE r.member_id = :mid
                ORDER BY r.created_at DESC"
            );
            $stmt->execute([':mid' => $member_id]);
            $reviews = $stmt->fetchAll();
            ?>

            <?php if (count($reviews) === 0): ?>
                <p class="text-muted">You haven't written any reviews yet. <a href="games.php">Browse games</a> and share your
                    thoughts!</p>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($reviews as $r): ?>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h3 class="card-title"><?php echo htmlspecialchars($r['game_title']); ?></h3>
                                        <div class="star-rating mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="material-icons <?php echo $i <= $r['rating'] ? '' : 'empty'; ?>"
                                                    aria-hidden="true">star</span>
                                            <?php endfor; ?>
                                            <span class="visually-hidden"><?php echo $r['rating']; ?> out of 5 stars</span>
                                        </div>
                                        <?php if (!empty($r['comment'])): ?>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($r['comment'])); ?></p>
                                        <?php endif; ?>
                                        <p class="text-muted small"><?php echo date('d M Y', strtotime($r['created_at'])); ?></p>
                                </div>
                                <div class="card-footer bg-transparent d-flex gap-2">
                                    <a href="reviews.php?action=edit&review_id=<?php echo $r['review_id']; ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <span class="material-icons" style="font-size:1rem;" aria-hidden="true">edit</span> Edit
                                    </a>
                                    <form method="post" action="process/process_review.php" class="d-inline"
                                        onsubmit="return confirm('Delete this review?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="review_id" value="<?php echo $r['review_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <span class="material-icons" style="font-size:1rem;" aria-hidden="true">delete</span>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </main>

    <?php include_once "inc/footer.inc.php"; ?>
</body>

</html>
