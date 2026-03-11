<!DOCTYPE html>
<html lang="en">

<head>
    <title>Menu - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>

<body>
    <?php include "inc/nav.inc.php"; ?>

    <main id="main-content">

        <header class="hero-section">
            <div class="container">
                <h1>Food &amp; Drinks</h1>
                <p>Comfort food, craft drinks, and sweet treats to fuel your game night.</p>
            </div>
        </header>

        <div class="container section-padding">

            <?php echo displayFlash(); ?>

            <?php
            try {
                require_once "process/db.php";

                $categories = ['Food', 'Drinks', 'Desserts'];
                $icons = ['Food' => 'lunch_dining', 'Drinks' => 'local_cafe', 'Desserts' => 'cake'];

                foreach ($categories as $cat) {
                    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE category = :cat AND available = 1 ORDER BY name ASC");
                    $stmt->execute([':cat' => $cat]);
                    $items = $stmt->fetchAll();

                    if (count($items) === 0)
                        continue;
                    ?>

                    <section class="mb-5">
                        <h2>
                            <span class="material-icons align-middle text-caramel me-2"
                                aria-hidden="true"><?php echo $icons[$cat]; ?></span>
                            <?php echo htmlspecialchars($cat); ?>
                        </h2>
                        <div class="row g-4">
                            <?php foreach ($items as $item): ?>
                                <div class="col-sm-6 col-lg-4">
                                    <div class="card h-100">
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="card-img-top"
                                            alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <div class="card-body d-flex flex-column">
                                            <h3 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                                                <p class="card-text"><?php echo htmlspecialchars($item['description']); ?></p>
                                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                                    <span
                                                        class="fs-5 fw-bold text-caramel">$<?php echo number_format($item['price'], 2); ?></span>
                                                    <?php if ($is_logged_in): ?>
                                                        <form method="post" action="process/process_order.php" class="d-inline">
                                                            <?php echo csrfField(); ?>
                                                            <input type="hidden" name="action" value="add_item">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                            <input type="hidden" name="from_page" value="menu">
                                                            <button type="submit" class="btn btn-primary btn-sm">
                                                                <span class="material-icons align-middle" style="font-size:1rem;"
                                                                    aria-hidden="true">add_shopping_cart</span>
                                                                Add to Order
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <?php
                }
            } catch (Exception $e) {
                echo '<p class="text-muted text-center">Menu is currently unavailable. Please check back soon!</p>';
            }
            ?>

            <?php if (!$is_logged_in): ?>
                <div class="text-center mt-3">
                    <p class="text-muted">
                        <a href="login.php">Sign in</a> to place orders directly from the menu.
                    </p>
                </div>
            <?php endif; ?>

        </div>

    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>

</html>