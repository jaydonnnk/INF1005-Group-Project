<?php
/**
 * Booking Waitlist Page
 * The Rolling Dice - Board Game Café
 *
 * Members can join the waitlist for a fully-booked time slot
 * and view or cancel their pending waitlist entries.
 */

session_start();
if (!isset($_SESSION["member_id"])) {
    header("Location: login.php");
    exit();
}

$member_id = $_SESSION["member_id"];
require_once "process/db.php";

$show_form = isset($_GET['action']) && $_GET['action'] === 'new';

// Time slots (same list used across the project)
$slots = [
    '11:00 AM - 1:00 PM',
    '1:00 PM - 3:00 PM',
    '3:00 PM - 5:00 PM',
    '5:00 PM - 7:00 PM',
    '7:00 PM - 9:00 PM',
    '9:00 PM - 11:00 PM'
];

// Fetch all games for the optional game preference dropdown
$games_stmt = $pdo->query("SELECT game_id, title FROM games WHERE quantity > 0 ORDER BY title ASC");
$all_games = $games_stmt->fetchAll();

// Fetch this member's pending waitlist entries with queue position
$entries_stmt = $pdo->prepare(
    "SELECT
        w.*,
        g.title AS game_title,
        (
            SELECT COUNT(*)
            FROM waitlist w2
            WHERE w2.booking_date = w.booking_date
            AND w2.time_slot    = w.time_slot
            AND w2.status       = 'Pending'
            AND w2.created_at  <= w.created_at
        ) AS queue_position
    FROM waitlist w
    LEFT JOIN games g ON w.game_id = g.game_id
    WHERE w.member_id = :mid
    ORDER BY w.booking_date ASC, w.time_slot ASC, w.created_at ASC"
);
$entries_stmt->execute([':mid' => $member_id]);
$entries = $entries_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Waitlist - The Rolling Dice</title>
    <?php include "inc/head.inc.php"; ?>
</head>

<body>
    <?php include "inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Booking Waitlist</h1>
            <?php if (!$show_form): ?>
                <a href="waitlist.php?action=new" class="btn btn-primary">
                    <span class="material-icons align-middle me-1" aria-hidden="true">queue</span>Join Waitlist
                </a>
            <?php endif; ?>
        </div>

        <?php echo displayFlash(); ?>

        <?php if ($show_form): ?>
            <!-- ── JOIN WAITLIST FORM ── -->
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <h2>Join the Waitlist</h2>
                    <p class="text-muted">
                        Can't find an available slot? Add yourself to the waitlist and we'll
                        contact you when a spot opens up.
                    </p>

                    <form action="process/process_waitlist.php" method="post" class="needs-validation" novalidate
                        aria-label="Join waitlist form">

                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="join">

                        <p class="text-muted small">
                            <span class="text-danger">*</span> indicates a required field.
                        </p>

                        <!-- Date -->
                        <div class="mb-3">
                            <label for="booking_date" class="form-label">
                                Preferred Date: <span class="text-danger">*</span>
                            </label>
                            <input type="date" id="booking_date" name="booking_date" class="form-control" required
                                min="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback">Please select a date.</div>
                        </div>

                        <!-- Time Slot -->
                        <div class="mb-3">
                            <label for="time_slot" class="form-label">
                                Preferred Time Slot: <span class="text-danger">*</span>
                            </label>
                            <select id="time_slot" name="time_slot" class="form-select" required>
                                <option value="">Select a time slot</option>
                                <?php foreach ($slots as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s); ?>">
                                        <?php echo htmlspecialchars($s); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a time slot.</div>
                        </div>

                        <!-- Party Size -->
                        <div class="mb-3">
                            <label for="party_size" class="form-label">
                                Party Size: <span class="text-danger">*</span>
                            </label>
                            <input type="number" id="party_size" name="party_size" class="form-control" min="1" max="12"
                                required value="2">
                            <div class="invalid-feedback">Enter a party size between 1 and 12.</div>
                        </div>

                        <!-- Game Preference (optional) -->
                        <div class="mb-3">
                            <label for="game_id" class="form-label">
                                Game Preference (optional):
                            </label>
                            <select id="game_id" name="game_id" class="form-select">
                                <option value="">No preference / decide when we arrive</option>
                                <?php foreach ($all_games as $g): ?>
                                    <option value="<?php echo $g['game_id']; ?>">
                                        <?php echo htmlspecialchars($g['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Notes -->
                        <div class="mb-3">
                            <label for="notes" class="form-label">Special Requests:</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3" maxlength="500"
                                placeholder="Birthday decorations, highchair needed, etc."></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-icons align-middle me-1" aria-hidden="true">queue</span>
                                Join Waitlist
                            </button>
                            <a href="waitlist.php" class="btn btn-outline-primary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- ── WAITLIST ENTRIES LIST ── -->

            <!-- Info banner explaining how the waitlist works -->
            <div class="alert alert-info d-flex align-items-start gap-2" role="note">
                <span class="material-icons mt-1" aria-hidden="true">info</span>
                <div>
                    <strong>How it works:</strong> When a slot opens up, our staff will contact you
                    at the email address on your account. Payment is only required once your spot
                    is confirmed — joining the waitlist is free.
                </div>
            </div>

            <?php
            $pending = array_filter($entries, fn($e) => $e['status'] === 'Pending');
            $cancelled = array_filter($entries, fn($e) => $e['status'] === 'Cancelled');
            ?>

            <?php if (count($pending) === 0 && count($cancelled) === 0): ?>
                <p class="text-muted">
                    You're not on any waitlists yet.
                    <a href="waitlist.php?action=new">Join a waitlist</a> or
                    <a href="bookings.php?action=new">make a regular booking</a>.
                </p>

            <?php else: ?>

                <?php if (count($pending) > 0): ?>
                    <h2 class="mb-3">Active Waitlist Entries</h2>
                    <div class="table-responsive mb-5">
                        <table class="table table-hover align-middle" aria-label="Your active waitlist entries">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Time Slot</th>
                                    <th scope="col">Party</th>
                                    <th scope="col">Game Preference</th>
                                    <th scope="col">Queue Position</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending as $e): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars(date('d M Y', strtotime($e['booking_date']))); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($e['time_slot']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($e['party_size']); ?>
                                        </td>
                                        <td>
                                            <?php echo $e['game_title']
                                                ? htmlspecialchars($e['game_title'])
                                                : '<span class="text-muted">No preference</span>'; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                #
                                                <?php echo (int) $e['queue_position']; ?> in queue
                                            </span>
                                        </td>
                                        <td>
                                            <form method="post" action="process/process_waitlist.php" class="d-inline"
                                                onsubmit="return confirm('Remove yourself from this waitlist?');">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="waitlist_id" value="<?php echo $e['waitlist_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Leave waitlist"
                                                    aria-label="Leave waitlist for <?php echo htmlspecialchars($e['time_slot']); ?> on <?php echo htmlspecialchars($e['booking_date']); ?>">
                                                    <span class="material-icons" style="font-size:1rem;"
                                                        aria-hidden="true">cancel</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (count($cancelled) > 0): ?>
                    <h2 class="mb-3">Past Waitlist Entries</h2>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" aria-label="Your past waitlist entries">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Time Slot</th>
                                    <th scope="col">Party</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cancelled as $e): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars(date('d M Y', strtotime($e['booking_date']))); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($e['time_slot']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($e['party_size']); ?>
                                        </td>
                                        <td><span class="badge bg-secondary">Cancelled</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>

    </main>

    <?php include "inc/footer.inc.php"; ?>
</body>

</html>