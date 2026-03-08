<?php
// Load DB early for genre query and game listing
try {
    require_once "process/db.php";
    $genre_stmt = $pdo->query("SELECT DISTINCT genre FROM games WHERE genre IS NOT NULL ORDER BY genre");
    $genres = $genre_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $genres = [];
    $pdo = null;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Game Library - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>

<body>
    <?php include "inc/nav.inc.php"; ?>

    <header class="hero-section">
        <div class="container">
            <h1>Game Library</h1>
            <p>Over 200 titles to explore. Filter by genre or difficulty to find your next favourite.</p>
        </div>
    </header>

    <main class="container section-padding">

        <?php echo display_flash(); ?>

        <!-- Search & Filter Bar -->
        <form method="get" action="games.php" class="row g-3 mb-4" aria-label="Filter games">
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" id="search" name="search" class="form-control" placeholder="Search by title..."
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="genre" class="form-label">Genre</label>
                <select id="genre" name="genre" class="form-select">
                    <option value="">All Genres</option>
                    <?php foreach ($genres as $g): ?>
                        <option value="<?php echo htmlspecialchars($g); ?>" <?php echo (isset($_GET['genre']) && $_GET['genre'] === $g) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="difficulty" class="form-label">Difficulty</label>
                <select id="difficulty" name="difficulty" class="form-select">
                    <option value="">All Levels</option>
                    <?php
                    $levels = ['Easy', 'Medium', 'Hard'];
                    foreach ($levels as $l) {
                        $selected = (isset($_GET['difficulty']) && $_GET['difficulty'] === $l) ? 'selected' : '';
                        echo "<option value=\"$l\" $selected>$l</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <span class="material-icons align-middle me-1" aria-hidden="true">search</span>Filter
                </button>
            </div>
        </form>

        <!-- Game Cards -->
        <div class="row g-4">
            <?php
            if ($pdo) {
                try {
                    // Build dynamic query with filters
                    $sql = "SELECT * FROM games WHERE 1=1";
                    $params = [];

                    if (!empty($_GET['search'])) {
                        $sql .= " AND title LIKE :search";
                        $params[':search'] = "%" . $_GET['search'] . "%";
                    }
                    if (!empty($_GET['genre'])) {
                        $sql .= " AND genre = :genre";
                        $params[':genre'] = $_GET['genre'];
                    }
                    if (!empty($_GET['difficulty'])) {
                        $sql .= " AND difficulty = :difficulty";
                        $params[':difficulty'] = $_GET['difficulty'];
                    }

                    $sql .= " ORDER BY title ASC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $games = $stmt->fetchAll();

                    if (count($games) === 0) {
                        echo '<p class="text-muted text-center">No games found matching your filters.</p>';
                    }

                    foreach ($games as $game) {
                        $diff_class = 'badge-difficulty-' . strtolower($game['difficulty']);
                        ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="card h-100">
                                <img src="<?php echo htmlspecialchars($game['image_url']); ?>" class="card-img-top"
                                    alt="<?php echo htmlspecialchars($game['title']); ?>">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo htmlspecialchars($game['title']); ?></h5>
                                    <div class="mb-2">
                                        <span class="badge badge-genre"><?php echo htmlspecialchars($game['genre']); ?></span>
                                        <span
                                            class="badge <?php echo $diff_class; ?>"><?php echo htmlspecialchars($game['difficulty']); ?></span>
                                    </div>
                                    <p class="card-text"><?php echo htmlspecialchars($game['description']); ?></p>
                                    <div class="mt-auto">
                                        <p class="text-caramel fw-bold mb-2">
                                            <?php echo htmlspecialchars($game['min_players']); ?>&ndash;<?php echo htmlspecialchars($game['max_players']); ?>
                                            players &bull;
                                            $<?php echo htmlspecialchars($game['price_per_hour']); ?>/hr
                                        </p>
                                        <?php if ($is_logged_in): ?>
                                            <div class="d-flex gap-2">
                                                <a href="reviews.php?game_id=<?php echo $game['game_id']; ?>"
                                                    class="btn btn-outline-primary btn-sm">
                                                    <span class="material-icons align-middle" style="font-size:1rem;"
                                                        aria-hidden="true">rate_review</span> Review
                                                </a>
                                                <form method="post" action="process/process_wishlist.php" class="d-inline">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="add">
                                                    <input type="hidden" name="game_id" value="<?php echo $game['game_id']; ?>">
                                                    <button type="submit" class="btn btn-outline-primary btn-sm">
                                                        <span class="material-icons align-middle" style="font-size:1rem;"
                                                            aria-hidden="true">favorite_border</span> Wishlist
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                } catch (Exception $e) {
                    echo '<p class="text-muted text-center">Game library is currently unavailable.</p>';
                }
            } else {
                echo '<p class="text-muted text-center">Game library is currently unavailable.</p>';
            }
            ?>
        </div>

    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>

</html>