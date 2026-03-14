<?php
session_start();
if (!isset($_SESSION["member_id"])) {
    header("Location: login.php");
    exit();
}
$member_id = $_SESSION["member_id"];
require_once "process/db.php";

// Determine if we're showing the new/edit form or the list
$show_form = isset($_GET['action']) && in_array($_GET['action'], ['new', 'edit']);
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$booking_data = null;

// Time slots used by both new and edit forms
$slots = ['11:00 AM - 1:00 PM', '1:00 PM - 3:00 PM', '3:00 PM - 5:00 PM',
          '5:00 PM - 7:00 PM', '7:00 PM - 9:00 PM', '9:00 PM - 11:00 PM'];

// Fetch booking data for editing
if ($show_form && $_GET['action'] === 'edit' && $edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = :id AND member_id = :mid");
    $stmt->execute([':id' => $edit_id, ':mid' => $member_id]);
    $booking_data = $stmt->fetch();
    if (!$booking_data) {
        setFlash('error', 'Booking not found.');
        header("Location: bookings.php");
        exit();
    }
}

// For edit form: fetch all games (not filtered by availability)
if ($show_form && $booking_data) {
    $games_stmt = $pdo->query("SELECT game_id, title FROM games WHERE quantity > 0 ORDER BY title ASC");
    $all_games = $games_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Bookings - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>
<body>
    <?php include "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>My Bookings</h1>
            <?php if (!$show_form): ?>
                <a href="bookings.php?action=new" class="btn btn-primary">
                    <span class="material-icons align-middle me-1" aria-hidden="true">add</span>New Booking
                </a>
            <?php endif; ?>
        </div>

        <?php echo displayFlash(); ?>

        <?php if ($show_form && !$booking_data): ?>
        <!-- NEW BOOKING FORM (posts to Stripe checkout) -->
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <h2>New Booking</h2>

                <form action="process/create_checkout.php" method="post"
                    class="needs-validation" novalidate
                    aria-label="Booking form"
                    data-preselect-game="<?php echo isset($_GET['game_id']) ? (int)$_GET['game_id'] : ''; ?>">

                    <?php echo csrfField(); ?>
                    <input type="hidden" name="checkout_type" value="booking">

                    <p class="text-muted small"><span class="text-danger">*</span> indicates a required field.</p>

                    <div class="mb-3">
                        <label for="booking_date" class="form-label">Date: <span class="text-danger">*</span></label>
                        <input type="date" id="booking_date" name="booking_date" class="form-control" required
                            min="<?php echo date('Y-m-d'); ?>">
                        <div class="invalid-feedback">Please select a date.</div>
                    </div>

                    <div class="mb-3">
                        <label for="time_slot" class="form-label">Time Slot: <span class="text-danger">*</span></label>
                        <select id="time_slot" name="time_slot" class="form-select" required>
                            <option value="">Select a time slot</option>
                            <?php
                            foreach ($slots as $s) {
                                echo '<option value="' . htmlspecialchars($s) . '">' . htmlspecialchars($s) . '</option>';
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Please select a time slot.</div>
                    </div>

                    <div class="mb-3">
                        <label for="party_size" class="form-label">Party Size: <span class="text-danger">*</span></label>
                        <input type="number" id="party_size" name="party_size" class="form-control"
                            min="1" max="12" required value="2">
                        <div class="invalid-feedback">Enter a party size between 1 and 12.</div>
                    </div>

                    <div class="mb-3">
                        <label for="game_id" class="form-label">Pre-select a Game (optional):</label>
                        <select id="game_id" name="game_id" class="form-select" disabled>
                            <option value="">Select date and time first</option>
                        </select>
                        <div class="form-text" id="game_help">Available games will load after you select a date and time slot.</div>
                    </div>

                    <div class="mb-3">
                        <label for="rental_hours" class="form-label">Rental Hours: <span class="text-danger">*</span></label>
                        <input type="number" id="rental_hours" name="rental_hours" class="form-control"
                            min="1" max="6" required value="2">
                        <div class="form-text">$5.00/hr per game rental.</div>
                        <div class="invalid-feedback">Enter rental hours between 1 and 6.</div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Special Requests:</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"
                                maxlength="500" placeholder="Birthday decorations, highchair needed, etc."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons align-middle me-1" aria-hidden="true">payment</span>
                            Pay &amp; Confirm Booking
                        </button>
                        <a href="bookings.php" class="btn btn-outline-primary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($show_form && $booking_data): ?>
        <!-- EDIT BOOKING FORM (stays with process_booking.php) -->
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <h2>Edit Booking</h2>

                <form action="process/process_booking.php" method="post"
                    class="needs-validation" novalidate
                    aria-label="Edit booking form">

                    <?php echo csrfField(); ?>

                    <p class="text-muted small"><span class="text-danger">*</span> indicates a required field.</p>

                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_data['booking_id']; ?>">

                    <div class="mb-3">
                        <label for="booking_date" class="form-label">Date: <span class="text-danger">*</span></label>
                        <input type="date" id="booking_date" name="booking_date" class="form-control" required
                            min="<?php echo date('Y-m-d'); ?>"
                            value="<?php echo htmlspecialchars($booking_data['booking_date']); ?>">
                        <div class="invalid-feedback">Please select a date.</div>
                    </div>

                    <div class="mb-3">
                        <label for="time_slot" class="form-label">Time Slot: <span class="text-danger">*</span></label>
                        <select id="time_slot" name="time_slot" class="form-select" required>
                            <option value="">Select a time slot</option>
                            <?php
                            foreach ($slots as $s) {
                                $selected = ($booking_data['time_slot'] === $s) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($s) . '" ' . $selected . '>'. htmlspecialchars($s) . '</option>';
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Please select a time slot.</div>
                    </div>

                    <div class="mb-3">
                        <label for="party_size" class="form-label">Party Size: <span class="text-danger">*</span></label>
                        <input type="number" id="party_size" name="party_size" class="form-control"
                            min="1" max="12" required
                            value="<?php echo htmlspecialchars($booking_data['party_size']); ?>">
                        <div class="invalid-feedback">Enter a party size between 1 and 12.</div>
                    </div>

                    <div class="mb-3">
                        <label for="game_id" class="form-label">Pre-select a Game (optional):</label>
                        <select id="game_id" name="game_id" class="form-select">
                            <option value="">We'll pick when we arrive</option>
                            <?php foreach ($all_games as $g): ?>
                                <option value="<?php echo $g['game_id']; ?>"
                                    <?php echo ($booking_data['game_id'] == $g['game_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Special Requests:</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"
                                maxlength="500" placeholder="Birthday decorations, highchair needed, etc."><?php echo htmlspecialchars($booking_data['notes']); ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons align-middle me-1" aria-hidden="true">save</span>
                            Update Booking
                        </button>
                        <a href="bookings.php" class="btn btn-outline-primary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- BOOKING LIST (READ) -->
        <?php
        $stmt = $pdo->prepare(
            "SELECT b.*, g.title AS game_title
            FROM bookings b
            LEFT JOIN games g ON b.game_id = g.game_id
            WHERE b.member_id = :mid
            ORDER BY b.booking_date DESC, b.time_slot ASC"
        );
        $stmt->execute([':mid' => $member_id]);
        $bookings = $stmt->fetchAll();
        ?>

        <?php if (count($bookings) === 0): ?>
            <p class="text-muted">You have no bookings yet. <a href="bookings.php?action=new">Make your first reservation!</a></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" aria-label="Your bookings">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Time</th>
                            <th scope="col">Party</th>
                            <th scope="col">Game</th>
                            <th scope="col">Hours</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('d M Y', strtotime($b['booking_date']))); ?></td>
                                <td><?php echo htmlspecialchars($b['time_slot']); ?></td>
                                <td><?php echo htmlspecialchars($b['party_size']); ?></td>
                                <td><?php echo $b['game_title'] ? htmlspecialchars($b['game_title']) : '<span class="text-muted">TBD</span>'; ?></td>
                                <td><?php echo htmlspecialchars($b['rental_hours']); ?> hr</td>
                                <td>
                                    <?php
                                    $badge = match($b['status']) {
                                        'Confirmed' => 'bg-success',
                                        'Cancelled' => 'bg-secondary',
                                        'Completed' => 'bg-info',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($b['status']); ?></span>
                                </td>
                                <td>
                                    <?php if ($b['status'] === 'Confirmed'): ?>
                                        <a href="bookings.php?action=edit&id=<?php echo $b['booking_id']; ?>"
                                        class="btn btn-sm btn-outline-primary me-1" title="Edit booking" aria-label="Edit booking">
                                            <span class="material-icons" style="font-size:1rem;" aria-hidden="true">edit</span>
                                        </a>
                                        <form method="post" action="process/process_booking.php" class="d-inline"
                                            onsubmit="return confirm('Cancel this booking?');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel booking" aria-label="Cancel booking">
                                                <span class="material-icons" style="font-size:1rem;" aria-hidden="true">cancel</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($b['status'] === 'Confirmed' || $b['status'] === 'Completed'): ?>
                                        <a href="receipt.php?type=booking&booking_id=<?php echo $b['booking_id']; ?>"
                                           class="btn btn-sm btn-outline-primary ms-1" title="View receipt" aria-label="View receipt">
                                            <span class="material-icons" style="font-size:1rem;" aria-hidden="true">receipt</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php endif; ?>

    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>
</html>
