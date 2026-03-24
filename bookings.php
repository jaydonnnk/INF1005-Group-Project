<?php
/**
 * bookings.php — Booking Management Page
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 */

session_start();
require_once "process/helpers.php";
require_once "process/db.php";

if (!isset($_SESSION["member_id"])) {
    header("Location: " . Routes::ROOT_LOGIN);
    exit();
}
$member_id = $_SESSION["member_id"];

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
        header("Location: " . Routes::ROOT_BOOKINGS);
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
    <?php include_once "inc/head.inc.php"; ?>
</head>
<body>
    <?php include_once "inc/nav.inc.php"; ?>

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
                        <label for="edit_booking_date" class="form-label">Date: <span class="text-danger">*</span></label>
                        <input type="date" id="edit_booking_date" name="booking_date" class="form-control" required
                            min="<?php echo date('Y-m-d'); ?>"
                            value="<?php echo htmlspecialchars($booking_data['booking_date']); ?>">
                        <div class="invalid-feedback">Please select a date.</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_time_slot" class="form-label">Time Slot: <span class="text-danger">*</span></label>
                        <select id="edit_time_slot" name="time_slot" class="form-select" required>
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
                        <label for="edit_party_size" class="form-label">Party Size: <span class="text-danger">*</span></label>
                        <input type="number" id="edit_party_size" name="party_size" class="form-control"
                            min="1" max="12" required
                            value="<?php echo htmlspecialchars($booking_data['party_size']); ?>">
                        <div class="invalid-feedback">Enter a party size between 1 and 12.</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_game_id" class="form-label">Pre-select a Game (optional):</label>
                        <select id="edit_game_id" name="game_id" class="form-select">
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
                        <label for="edit_notes" class="form-label">Special Requests:</label>
                        <textarea id="edit_notes" name="notes" class="form-control" rows="3"
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

        // For each booking, check if it has a linked matchmaking post with joins
        // Map booking_id => ['post' => [...], 'joiners' => [...], 'locked' => bool]
        $matchmaking_info = [];
        if (!empty($bookings)) {
            // Slot label → 24h hour offset for computing "2 hours before" cutoff
            $slot_to_hour = [
                '11:00 AM - 1:00 PM' => 11, '1:00 PM - 3:00 PM' => 13,
                '3:00 PM - 5:00 PM'  => 15, '5:00 PM - 7:00 PM' => 17,
                '7:00 PM - 9:00 PM'  => 19, '9:00 PM - 11:00 PM'=> 21,
            ];
            foreach ($bookings as $b) {
                // Find a linked matchmaking post for this booking
                $mp_stmt = $pdo->prepare(
                    "SELECT post_id, spots_total, spots_filled FROM matchmaking_posts
                     WHERE booking_id = :bid AND status != 'Cancelled' LIMIT 1"
                );
                $mp_stmt->execute([':bid' => $b['booking_id']]);
                $mp = $mp_stmt->fetch();
                if (!$mp) { continue; }

                // Is the session finalised? (within 2 hours of start time)
                $start_hour = $slot_to_hour[$b['time_slot']] ?? 0;
                $session_start = strtotime($b['booking_date'] . " {$start_hour}:00:00");
                $locked = (time() >= $session_start - 2 * 3600);

                // Fetch joiners' contact details
                $j_stmt = $pdo->prepare(
                    "SELECT m.fname, m.lname, m.email, m.phone
                     FROM matchmaking_joins mj
                     JOIN members m ON mj.member_id = m.member_id
                     WHERE mj.post_id = :pid"
                );
                $j_stmt->execute([':pid' => $mp['post_id']]);
                $joiners = $j_stmt->fetchAll();

                $matchmaking_info[$b['booking_id']] = [
                    'spots_total'  => (int)$mp['spots_total'],
                    'spots_filled' => (int)$mp['spots_filled'],
                    'locked'       => $locked,
                    'joiners'      => $joiners,
                ];
            }
        }
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
                            <th scope="col">Spots Filled</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b):
                            $mm = $matchmaking_info[$b['booking_id']] ?? null;
                        ?>
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
                                    <?php if ($mm): ?>
                                        <?php if ($mm['spots_filled'] > 0): ?>
                                            <?php $joiners_json = htmlspecialchars(json_encode($mm['joiners']), ENT_QUOTES, 'UTF-8'); ?>
                                            <button class="btn btn-sm btn-outline-primary btn-spots-filled"
                                                    type="button"
                                                    data-joiners="<?php echo $joiners_json; ?>"
                                                    data-spots-filled="<?php echo $mm['spots_filled']; ?>"
                                                    data-spots-total="<?php echo $mm['spots_total']; ?>">
                                                <span class="material-icons align-middle me-1" style="font-size:1rem;">people</span>
                                                <?php echo $mm['spots_filled']; ?>/<?php echo $mm['spots_total']; ?> Joined
                                            </button>
                                            <?php if ($mm['locked']): ?>
                                                <span class="badge bg-secondary ms-1 small">Finalised</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">
                                                <?php if ($mm['locked']): ?>
                                                    <span class="material-icons align-middle" style="font-size:1rem;">lock</span>
                                                <?php endif; ?>
                                                0/<?php echo $mm['spots_total']; ?> joined
                                            </span>
                                            <?php if ($mm['locked']): ?>
                                                <span class="badge bg-secondary ms-1 small">Finalised</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
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

    <?php include_once "inc/footer.inc.php"; ?>

    <!-- Joiners Modal — must be after footer so Bootstrap JS is already loaded -->
    <div class="modal fade" id="joinersModal" tabindex="-1" aria-labelledby="joinersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="joinersModalLabel">
                        <span class="material-icons align-middle me-2" style="font-size:1.2rem; vertical-align:middle;">people</span>
                        Players Who Joined Your Session
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody id="joinersTableBody"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-spots-filled');
        if (!btn) return;

        const joiners     = JSON.parse(btn.dataset.joiners || '[]');
        const spotsFilled = btn.dataset.spotsFilled;
        const spotsTotal  = btn.dataset.spotsTotal;

        // Update title
        document.getElementById('joinersModalLabel').innerHTML =
            '<span class="material-icons align-middle me-2" style="font-size:1.2rem;vertical-align:middle;">people</span>' +
            'Players Who Joined Your Session ' +
            '<span class="badge bg-secondary ms-1">' + spotsFilled + '/' + spotsTotal + '</span>';

        // Build table rows
        const tbody = document.getElementById('joinersTableBody');
        tbody.innerHTML = '';
        if (joiners.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No joiners yet.</td></tr>';
        } else {
            joiners.forEach(function (j, i) {
                const name  = [j.fname, j.lname].filter(Boolean).join(' ') || '—';
                const email = j.email  || '—';
                const phone = j.phone  || '—';
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td class="ps-3">' + (i + 1) + '</td>' +
                    '<td>' + esc(name)  + '</td>' +
                    '<td>' + esc(email) + '</td>' +
                    '<td>' + esc(phone) + '</td>';
                tbody.appendChild(tr);
            });
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('joinersModal')).show();
    });

    function esc(s) {
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    </script>
</body>
</html>
