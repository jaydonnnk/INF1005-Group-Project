<?php
require_once "auth_check.php";
require_once __DIR__ . "/../process/db.php";

$members = $pdo->query(
    "SELECT member_id, fname, lname, email, phone, is_admin, created_at FROM members ORDER BY created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="../">
    <title>Manage Members - Admin - The Rolling Dice</title>
    <?php include_once __DIR__ . "/../inc/head.inc.php"; ?>
</head>
<body>
    <?php include_once __DIR__ . "/../inc/nav.inc.php"; ?>

    <main id="main-content" class="container section-padding">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>All Members</h1>
            <a href="admin/index.php" class="btn btn-outline-primary">
                <span class="material-icons align-middle me-1" aria-hidden="true">arrow_back</span>Admin Home
            </a>
        </div>

        <?php echo displayFlash(); ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle" aria-label="All members">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Joined</th>
                        <th>Admin</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td><?php echo (int)$m['member_id']; ?></td>
                            <td><?php echo htmlspecialchars(trim($m['fname'] . ' ' . $m['lname'])); ?></td>
                            <td><?php echo htmlspecialchars($m['email']); ?></td>
                            <td><?php echo $m['phone'] ? htmlspecialchars($m['phone']) : '<span class="text-muted">—</span>'; ?></td>
                            <td><?php echo date('d M Y', strtotime($m['created_at'])); ?></td>
                            <td>
                                <?php if ($m['is_admin']): ?>
                                    <span class="badge bg-warning text-dark">Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Member</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$m['member_id'] === (int)$_SESSION['member_id']): ?>
                                    <span class="text-muted small">You</span>
                                <?php else: ?>
                                    <form method="post" action="admin/process/process_admin.php" class="d-inline"
                                        onsubmit="return confirm('<?php echo $m['is_admin'] ? 'Remove admin access?' : 'Grant admin access?'; ?>');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle_admin">
                                        <input type="hidden" name="member_id" value="<?php echo (int)$m['member_id']; ?>">
                                        <input type="hidden" name="is_admin" value="<?php echo $m['is_admin'] ? 0 : 1; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $m['is_admin'] ? 'btn-outline-danger' : 'btn-outline-primary'; ?>"
                                                title="<?php echo $m['is_admin'] ? 'Remove admin' : 'Make admin'; ?>">
                                            <?php echo $m['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>

    <?php include_once __DIR__ . "/../inc/footer.inc.php"; ?>
</body>
</html>
