<?php
/**
 * admin/bookings.php — Admin Booking Management Page
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 */

require_once "auth_check.php";
require_once __DIR__ . "/../process/db.php";

// Fetch all bookings with game title and member details
$stmt = $pdo->query(
    "SELECT b.*, g.title AS game_title, m.fname, m.lname, m.email
    FROM bookings b
    LEFT JOIN games g ON b.game_id = g.game_id
    JOIN members m ON b.member_id = m.member_id
    ORDER BY b.booking_date DESC, b.time_slot ASC"
);

$bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="../">
    <title>Manage Bookings - Admin - The Rolling Dice</title>
    <?php include_once __DIR__ . "/../inc/head.inc.php"; ?>
</head>
<body>
    <?php include_once __DIR__ . "/../inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>All Bookings</h1>
            <a href="admin/index.php" class="btn btn-outline-primary">
                <span class="material-icons align-middle me-1" aria-hidden="true">arrow_back</span>Admin Home
            </a>
        </div>

        <?php echo displayFlash(); ?>

        <?php if (count($bookings) === 0): ?>
            <p class="text-muted">No bookings found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" aria-label="All bookings">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Party</th>
                            <th>Game</th>
                            <th>Hours</th>
                            <th>Status</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td><?php echo (int)$b['booking_id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars(trim($b['fname'] . ' ' . $b['lname'])); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($b['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars(date('d M Y', strtotime($b['booking_date']))); ?></td>
                                <td><?php echo htmlspecialchars($b['time_slot']); ?></td>
                                <td><?php echo (int)$b['party_size']; ?></td>
                                <td><?php echo $b['game_title'] ? htmlspecialchars($b['game_title']) : '<span class="text-muted">TBD</span>'; ?></td>
                                <td><?php echo (int)$b['rental_hours']; ?> hr</td>
                                <td>
                                    <?php
                                    $badge = match($b['status']) {
                                        'Confirmed' => 'bg-success',
                                        'Completed' => 'bg-info',
                                        'Cancelled' => 'bg-secondary',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($b['status']); ?></span>
                                </td>
                                <td>
                                    <form method="post" action="admin/process/process_admin.php" class="d-inline">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="update_booking_status">
                                        <input type="hidden" name="booking_id" value="<?php echo (int)$b['booking_id']; ?>">
                                        <select name="status" class="form-select form-select-sm d-inline-block" style="width:auto;"
                                                onchange="this.form.submit();" aria-label="Update status">
                                            <?php foreach (['Confirmed', 'Completed', 'Cancelled'] as $s): ?>
                                                <option value="<?php echo $s; ?>" <?php echo ($b['status'] === $s) ? 'selected' : ''; ?>>
                                                    <?php echo $s; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
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
