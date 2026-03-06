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

// Fetch booking data for editing
if ($show_form && $_GET['action'] === 'edit' && $edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = :id AND member_id = :mid");
    $stmt->execute([':id' => $edit_id, ':mid' => $member_id]);
    $booking_data = $stmt->fetch();
    if (!$booking_data) {
        set_flash('error', 'Booking not found.');
        header("Location: bookings.php");
        exit();
    }
}

// Fetch all games for the dropdown
$games_stmt = $pdo->query("SELECT game_id, title FROM games ORDER BY title ASC");
$all_games = $games_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Bookings - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>
<body>
    <?php include "inc/nav.inc.php"; ?>

    <main class="container section-padding">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>My Bookings</h1>
            <?php if (!$show_form): ?>
                <a href="bookings.php?action=new" class="btn btn-primary">
                    <span class="material-icons align-middle me-1" aria-hidden="true">add</span>New Booking
                </a>
            <?php endif; ?>
        </div>

        <?php echo display_flash(); ?>

        <?php if ($show_form): ?>
        <!-- CREATE / EDIT FORM -->
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <h2><?php echo $booking_data ? 'Edit Booking' : 'New Booking'; ?></h2>

                <form action="process/process_booking.php" method="post"
                      class="needs-validation" novalidate
                      aria-label="Booking form">

                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="<?php echo $booking_data ? 'update' : 'create'; ?>">
                    <?php if ($booking_data): ?>
                        <input type="hidden" name="booking_id" value="<?php echo $booking_data['booking_id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="booking_date" class="form-label">Date: <span class="text-danger">*</span></label>
                        <input type="date" id="booking_date" name="booking_date" class="form-control" required
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo $booking_data ? htmlspecialchars($booking_data['booking_date']) : ''; ?>">
                        <div class="invalid-feedback">Please select a date.</div>
                    </div>

                    <div class="mb-3">
                        <label for="time_slot" class="form-label">Time Slot: <span class="text-danger">*</span></label>
                        <select id="time_slot" name="time_slot" class="form-select" required>
                            <option value="">Select a time slot</option>
                            <?php
                            $slots = ['11:00 AM - 1:00 PM', '1:00 PM - 3:00 PM', '3:00 PM - 5:00 PM',
                                      '5:00 PM - 7:00 PM', '7:00 PM - 9:00 PM', '9:00 PM - 11:00 PM'];
                            foreach ($slots as $s) {
                                $selected = ($booking_data && $booking_data['time_slot'] === $s) ? 'selected' : '';
                                echo "<option value=\"$s\" $selected>$s</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Please select a time slot.</div>
                    </div>

                    <div class="mb-3">
                        <label for="party_size" class="form-label">Party Size: <span class="text-danger">*</span></label>
                        <input type="number" id="party_size" name="party_size" class="form-control"
                               min="1" max="12" required
                               value="<?php echo $booking_data ? htmlspecialchars($booking_data['party_size']) : '2'; ?>">
                        <div class="invalid-feedback">Enter a party size between 1 and 12.</div>
                    </div>

                    <div class="mb-3">
                        <label for="game_id" class="form-label">Pre-select a Game (optional):</label>
                        <select id="game_id" name="game_id" class="form-select">
                            <option value="">We'll pick when we arrive</option>
                            <?php foreach ($all_games as $g): ?>
                                <option value="<?php echo $g['game_id']; ?>"
                                    <?php echo ($booking_data && $booking_data['game_id'] == $g['game_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Special Requests:</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"
                                  maxlength="500" placeholder="Birthday decorations, highchair needed, etc."><?php echo $booking_data ? htmlspecialchars($booking_data['notes']) : ''; ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons align-middle me-1" aria-hidden="true">save</span>
                            <?php echo $booking_data ? 'Update Booking' : 'Confirm Booking'; ?>
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
                                           class="btn btn-sm btn-outline-primary me-1" title="Edit booking">
                                            <span class="material-icons" style="font-size:1rem;" aria-hidden="true">edit</span>
                                        </a>
                                        <form method="post" action="process/process_booking.php" class="d-inline"
                                              onsubmit="return confirm('Cancel this booking?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel booking">
                                                <span class="material-icons" style="font-size:1rem;" aria-hidden="true">cancel</span>
                                            </button>
                                        </form>
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
