<!DOCTYPE html>
<html lang="en">
<head>
    <title>The Rolling Dice - Board Game Caf&eacute;</title>
    <?php include "inc/head.inc.php"; ?>
</head>
<body>
    <?php include "inc/nav.inc.php"; ?>

    <!-- Hero Section -->
    <header class="hero-section" role="banner">
        <div class="container">
            <h1 class="display-4">Welcome to The Rolling Dice</h1>
            <p>
                Singapore's cosiest board game caf&eacute;. Grab a seat, pick a game from
                our library of 200+ titles, and fuel up with craft coffee and comfort food.
            </p>
            <a href="bookings.php" class="btn btn-hero">
                <span class="material-icons align-middle me-1" aria-hidden="true">event</span>
                Reserve a Table
            </a>
        </div>
    </header>

    <main class="container">

        <!-- What We Offer Section -->
        <section class="section-padding" id="features">
            <h2 class="text-center mb-4">What We Offer</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">extension</span>
                        <h5>200+ Board Games</h5>
                        <p>From classic favourites to the latest releases — there's something for every player.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">restaurant</span>
                        <h5>Caf&eacute; &amp; Kitchen</h5>
                        <p>Craft coffee, comfort food, and sweet treats to keep the energy high.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card">
                        <span class="material-icons" aria-hidden="true">groups</span>
                        <h5>Private Events</h5>
                        <p>Host game nights, birthdays, and team-building events in our cosy space.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Games Section -->
        <section class="section-padding" id="featured-games">
            <h2 class="text-center mb-4">Featured Games</h2>
            <div class="row g-4">
                <?php
                // Connect to database and fetch featured games
                try {
                    require_once "process/db.php";
                    $stmt = $pdo->query("SELECT * FROM games ORDER BY RAND() LIMIT 3");
                    while ($game = $stmt->fetch()) {
                        echo '<div class="col-md-4">';
                        echo '  <div class="card h-100">';
                        echo '    <img src="' . htmlspecialchars($game["image_url"]) . '" ';
                        echo '         class="card-img-top" ';
                        echo '         alt="' . htmlspecialchars($game["title"]) . '">';
                        echo '    <div class="card-body">';
                        echo '      <h5 class="card-title">' . htmlspecialchars($game["title"]) . '</h5>';
                        echo '      <span class="badge badge-genre mb-2">' . htmlspecialchars($game["genre"]) . '</span> ';
                        echo '      <span class="badge badge-difficulty-' . strtolower($game["difficulty"]) . ' mb-2">' . htmlspecialchars($game["difficulty"]) . '</span>';
                        echo '      <p class="card-text">' . htmlspecialchars($game["description"]) . '</p>';
                        echo '      <p class="text-caramel fw-bold">' . htmlspecialchars($game["min_players"]) . '–' . htmlspecialchars($game["max_players"]) . ' players &bull; $' . htmlspecialchars($game["price_per_hour"]) . '/hr</p>';
                        echo '    </div>';
                        echo '  </div>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<p class="text-muted text-center">Game library is currently unavailable. Please check back soon!</p>';
                }
                ?>
            </div>
            <div class="text-center mt-4">
                <a href="games.php" class="btn btn-outline-primary">
                    <span class="material-icons align-middle me-1" aria-hidden="true">arrow_forward</span>
                    View Full Library
                </a>
            </div>
        </section>

        <!-- Call to Action -->
        <section class="section-padding text-center" id="cta">
            <h2>Ready to Roll?</h2>
            <p class="lead">Join our community of board game lovers. Members get priority reservations, loyalty points, and exclusive event invites.</p>
            <a href="register.php" class="btn btn-primary btn-lg">
                <span class="material-icons align-middle me-1" aria-hidden="true">person_add</span>
                Become a Member
            </a>
        </section>

    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>
</html>
