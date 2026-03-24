<?php
/**
 * matchmaking.php — Player Matchmaking Page
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$is_logged_in = isset($_SESSION["member_id"]);
$is_admin     = !empty($_SESSION["is_admin"]);

$posts = []; $total_posts = $online_count = $today_count = $game_types_count = 0;
$my_interests = []; $pdo_error = false;

try {
    require_once "process/db.php";
    $posts = $pdo->query(
        "SELECT mp.post_id, mp.member_id,
            CONCAT(COALESCE(m.fname,''), IF(m.fname IS NOT NULL AND m.fname != '',' ',''), LEFT(m.lname,1),'.') AS author,
            UPPER(CONCAT(LEFT(COALESCE(m.fname,m.lname),1), IF(m.fname IS NOT NULL AND m.fname != '',LEFT(m.lname,1),''))) AS initials,
            mp.title, mp.body, mp.game_name, mp.game_type,
            mp.skill_level AS skill, mp.play_style,
            mp.spots_total AS spots, mp.spots_filled AS filled,
            mp.session_date, mp.session_time,
            mp.pref_age, mp.pref_gender, mp.pref_skill,
            mp.is_urgent AS urgent, mp.created_at,
            COALESCE(mi.interest_count,0) AS interest_count,
            EXISTS(SELECT 1 FROM matchmaking_sessions ms
            WHERE ms.member_id=mp.member_id
            AND ms.last_active >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS is_online
            FROM matchmaking_posts mp
            JOIN members m ON mp.member_id=m.member_id
            LEFT JOIN (SELECT post_id, COUNT(*) AS interest_count FROM matchmaking_interests GROUP BY post_id) mi
            ON mi.post_id=mp.post_id
            WHERE mp.status='Open' AND mp.session_date >= CURDATE()
            ORDER BY mp.created_at DESC"
    )->fetchAll();

    $total_posts      = count($posts);
    $online_count     = count(array_filter($posts, fn($p) => $p['is_online']));
    $today_count      = count(array_filter($posts, fn($p) => $p['session_date'] === date('Y-m-d')));
    $game_types_count = count(array_unique(array_column($posts, 'game_type')));

    if ($is_logged_in) {
        $s = $pdo->prepare("SELECT post_id FROM matchmaking_interests WHERE member_id=:mid");
        $s->execute([':mid' => $_SESSION['member_id']]);
        $my_interests = array_column($s->fetchAll(), 'post_id');

        $s2 = $pdo->prepare("SELECT post_id FROM matchmaking_joins WHERE member_id=:mid");
        $s2->execute([':mid' => $_SESSION['member_id']]);
        $my_joins = array_column($s2->fetchAll(), 'post_id');

        // Fetch user's confirmed bookings for the "link to booking" dropdown
        $s3 = $pdo->prepare(
            "SELECT b.booking_id, b.booking_date, b.time_slot
             FROM bookings b
             WHERE b.member_id = :mid AND b.status = 'Confirmed'
               AND CONCAT(b.booking_date, ' ', CASE b.time_slot
                   WHEN '11:00 AM - 1:00 PM' THEN '11:00:00'
                   WHEN '1:00 PM - 3:00 PM'  THEN '13:00:00'
                   WHEN '3:00 PM - 5:00 PM'  THEN '15:00:00'
                   WHEN '5:00 PM - 7:00 PM'  THEN '17:00:00'
                   WHEN '7:00 PM - 9:00 PM'  THEN '19:00:00'
                   WHEN '9:00 PM - 11:00 PM' THEN '21:00:00'
                   ELSE '00:00:00' END) >= NOW()
             ORDER BY b.booking_date ASC, b.time_slot ASC"
        );
        $s3->execute([':mid' => $_SESSION['member_id']]);
        $my_bookings = $s3->fetchAll();
    }
} catch (Exception $e) {
    error_log("Matchmaking load error: " . $e->getMessage());
    $pdo_error = true;
}
$my_joins    = $my_joins    ?? [];
$my_bookings = $my_bookings ?? [];

/**
 * Convert a datetime string into a human-readable relative time label.
 *
 * @param string $dt Datetime string (e.g. '2026-03-23 14:30:00')
 * @return string Relative time (e.g. 'Just now', '5 min ago', '1 day ago')
 */
function timeAgo(string $dt): string {
    $d = time() - strtotime($dt);
    $result = floor($d/86400)." days ago";
    if ($d < 60) { $result = "Just now"; }
    elseif ($d < 3600) { $result = floor($d/60)." min ago"; }
    elseif ($d < 86400) { $result = floor($d/3600)." hr ago"; }
    elseif ($d < 172800) { $result = "1 day ago"; }
    return $result;
}

/**
 * Format a session date and time into a user-friendly label.
 *
 * @param string $date Session date (YYYY-MM-DD)
 * @param string $time Session start time (HH:MM)
 * @return string Formatted label (e.g. 'Today, 11:00 AM – 1:00 PM')
 */
function fmtDate(string $date, string $time): string {
    $diff = strtotime($date) - strtotime(date('Y-m-d'));
    if ($diff === 0) {
        $lbl = "Today";
    } elseif ($diff === 86400) {
        $lbl = "Tomorrow";
    } else {
        $lbl = date('D, d M', strtotime($date));
    }
    $slots = [
        '11:00' => '11:00 AM – 1:00 PM',
        '13:00' => '1:00 PM – 3:00 PM',
        '15:00' => '3:00 PM – 5:00 PM',
        '17:00' => '5:00 PM – 7:00 PM',
        '19:00' => '7:00 PM – 9:00 PM',
        '21:00' => '9:00 PM – 11:00 PM',
    ];
    $key   = substr($time, 0, 5);
    $slot  = $slots[$key] ?? date('g:i A', strtotime($time));
    return "$lbl, $slot";
}

$skill_badge = ["Beginner"=>"badge-difficulty-easy","Intermediate"=>"badge-difficulty-medium","Advanced"=>"badge-difficulty-hard"];

// Map booking time_slot labels to the 24h values used by the matchmaking time select
$slot_label_to_value = [
    '11:00 AM - 1:00 PM' => '11:00',
    '1:00 PM - 3:00 PM'  => '13:00',
    '3:00 PM - 5:00 PM'  => '15:00',
    '5:00 PM - 7:00 PM'  => '17:00',
    '7:00 PM - 9:00 PM'  => '19:00',
    '9:00 PM - 11:00 PM' => '21:00',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Find a Player – The Rolling Dice</title>
    <?php include_once "inc/head.inc.php"; ?>
    <style>
        .active-filters { display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; margin-bottom:1rem; min-height:2rem; }
        .filter-tag { background:rgba(198,139,89,.15); color:var(--color-walnut); border:1px solid rgba(198,139,89,.4); border-radius:20px; padding:.2rem .7rem; font-size:.78rem; font-weight:600; display:flex; align-items:center; gap:.3rem; }
        .filter-tag .remove-filter { cursor:pointer; color:var(--color-caramel); font-weight:700; background:none; border:none; padding:0; font-size:.9rem; }
        .post-card { border-left:4px solid transparent; transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease; }
        .post-card:hover { border-left-color:var(--color-caramel); transform:translateY(-3px); box-shadow:var(--shadow-medium); }
        .post-card.urgent-card { border-left-color:var(--color-berry); }
        .post-card.urgent-card:hover { border-left-color:var(--color-berry); }
        .player-avatar { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--color-caramel),var(--color-walnut)); display:flex; align-items:center; justify-content:center; color:#fff; font-family:var(--font-heading); font-weight:700; font-size:1rem; flex-shrink:0; position:relative; }
        .online-dot { position:absolute; bottom:1px; right:1px; width:10px; height:10px; border-radius:50%; border:2px solid var(--color-warm-white); }
        .online-dot.online { background-color:var(--color-sage); }
        .online-dot.offline { background-color:#ccc; }
        .spots-bar { height:6px; border-radius:3px; background-color:var(--color-border); overflow:hidden; margin-top:4px; }
        .spots-bar-fill { height:100%; border-radius:3px; background-color:var(--color-sage); transition:width .4s ease; }
        .spots-bar-fill.full { background-color:var(--color-berry); }
        .detail-pill { display:inline-flex; align-items:center; gap:.25rem; background:#f5ece3; border:1px solid var(--color-border); border-radius:20px; padding:.2rem .6rem; font-size:.75rem; color:var(--color-walnut); }
        .detail-pill .material-icons { font-size:.85rem; }
        .filter-sidebar { position:sticky; top:80px; }
        .modal-overlay { position:fixed; inset:0; background:rgba(60,36,21,.55); backdrop-filter:blur(4px); z-index:1055; display:none; align-items:center; justify-content:center; padding:1rem; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:var(--color-warm-white); border-radius:var(--radius-lg); width:100%; max-width:580px; max-height:90vh; overflow-y:auto; padding:2rem; position:relative; box-shadow:0 12px 48px rgba(60,36,21,.25); animation:modalPop .25s ease; }
        @keyframes modalPop { from{opacity:0;transform:scale(.95) translateY(16px)} to{opacity:1;transform:scale(1) translateY(0)} }
        .char-count { font-size:.72rem; color:var(--color-text-light); text-align:right; }
        .interest-count { font-size:.78rem; color:var(--color-text-light); }
        .interest-count .material-icons { font-size:.9rem; vertical-align:middle; }
        .sort-pill { border-radius:20px; font-size:.8rem; padding:.3rem .9rem; cursor:pointer; border:1px solid var(--color-border); background:#fff; color:var(--color-text-light); transition:all .15s; font-family:var(--font-body); font-weight:600; line-height:1.4; display:inline-block; }
        .sort-pill:hover { border-color:var(--color-walnut); color:var(--color-walnut); }
        .sort-pill.active { background:var(--color-walnut); border-color:var(--color-walnut); color:#fff; }
        .sort-pill.skill-beginner { border-color:var(--color-sage); color:#4a7a40; background:rgba(143,174,126,.12); }
        .sort-pill.skill-beginner:hover,.sort-pill.skill-beginner.active { background:var(--color-sage); border-color:var(--color-sage); color:#fff; }
        .sort-pill.skill-intermediate { border-color:var(--color-caramel); color:#8a5a20; background:rgba(198,139,89,.12); }
        .sort-pill.skill-intermediate:hover,.sort-pill.skill-intermediate.active { background:var(--color-caramel); border-color:var(--color-caramel); color:#fff; }
        .sort-pill.skill-advanced { border-color:var(--color-berry); color:#8a2a24; background:rgba(164,90,82,.12); }
        .sort-pill.skill-advanced:hover,.sort-pill.skill-advanced.active { background:var(--color-berry); border-color:var(--color-berry); color:#fff; }
        .online-strip { display:flex; flex-wrap:wrap; gap:.4rem; }
        .online-chip { display:inline-flex; align-items:center; gap:.35rem; background:#f5ece3; border:1px solid var(--color-border); border-radius:20px; padding:.25rem .65rem; font-size:.75rem; color:var(--color-walnut); font-weight:600; }
        .green-dot { width:7px; height:7px; border-radius:50%; background:var(--color-sage); animation:pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.35} }
        .empty-state { text-align:center; padding:3rem 1rem; color:var(--color-text-light); }
        .empty-state .material-icons { font-size:3.5rem; color:var(--color-border); }
        .toast-custom { position:fixed; bottom:1.5rem; right:1.5rem; background:var(--color-walnut); color:#fff; padding:.75rem 1.3rem; border-radius:var(--radius-md); font-weight:600; font-size:.88rem; z-index:9999; transform:translateY(80px); opacity:0; transition:all .3s ease; pointer-events:none; box-shadow:var(--shadow-medium); }
        .toast-custom.show { transform:translateY(0); opacity:1; }
        @media(max-width:767px){ .filter-sidebar{position:static;} }
    </style>
</head>
<body>
<?php include_once "inc/nav.inc.php"; ?>
<main id="main-content">

<header class="hero-section">
    <div class="container">
        <h1><span class="material-icons align-middle me-2" style="font-size:2.2rem;">group_add</span>Find Your Player</h1>
        <p>Browse open game sessions, post your own, and find the perfect match — by game, skill, or play style.</p>
        <?php if ($is_logged_in): ?>
            <button class="btn btn-hero mt-3" id="openPostModal"><span class="material-icons align-middle me-1">add_circle</span>Post a Session</button>
        <?php else: ?>
            <a href="login.php" class="btn btn-hero mt-3"><span class="material-icons align-middle me-1">login</span>Sign In to Post</a>
        <?php endif; ?>
    </div>
</header>

<div class="container section-padding">

<?php if (!$is_logged_in): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
    <span class="material-icons">lock</span>
    <div><strong>Sign in to participate.</strong> You can browse freely, but must <a href="login.php" class="alert-link">sign in</a> or <a href="register.php" class="alert-link">register</a> to post, join, or mark interest.</div>
</div>
<?php endif; ?>
<?php if ($pdo_error): ?>
<div class="alert alert-danger" role="alert"><span class="material-icons align-middle me-1">error</span>Could not load sessions. Please try again later.</div>
<?php endif; ?>

<?php echo displayFlash(); ?>


<div class="row g-4">

<!-- Sidebar -->
<div class="col-md-4 col-lg-3"><div class="filter-sidebar">

    <div class="card mb-3 p-3">
        <h2 class="h6 mb-3"><span class="material-icons align-middle me-1 text-caramel" style="font-size:1rem;">circle</span>Online Now</h2>
        <div class="online-strip">
            <?php $shown=0; foreach($posts as $p): if(!$p['is_online']) { continue; } $shown++; ?>
                <span class="online-chip"><span class="green-dot"></span><?php echo htmlspecialchars($p['author']); ?></span>
            <?php endforeach; if(!$shown): ?><span class="text-muted small">No one online right now.</span><?php endif; ?>
        </div>
    </div>

    <div class="card p-3">
        <h2 class="h6 mb-3"><span class="material-icons align-middle me-1 text-caramel" style="font-size:1rem;">tune</span>Filter Sessions</h2>

        <div class="mb-3">
            <label for="searchInput" class="form-label small fw-bold">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><span class="material-icons" style="font-size:1rem;">search</span></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Game, name, keyword…">
            </div>
        </div>

        <div class="mb-3">
            <label for="filterGameType" class="form-label small fw-bold">Game Type</label>
            <select id="filterGameType" class="form-select form-select-sm">
                <option value="">All Types</option>
                <?php foreach(['Strategy','Party','Cooperative','Deck-Building','Role-Playing','Trivia','Word'] as $gt): ?>
                <option><?php echo $gt; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <fieldset class="mb-3">
            <legend class="form-label small fw-bold d-block">Skill Level</legend>
            <div class="d-flex flex-wrap gap-1" id="filterSkill">
                <button class="sort-pill active" data-skill="">Any</button>
                <button class="sort-pill skill-beginner" data-skill="Beginner">Beginner</button>
                <button class="sort-pill skill-intermediate" data-skill="Intermediate">Intermediate</button>
                <button class="sort-pill skill-advanced" data-skill="Advanced">Advanced</button>
            </div>
        </fieldset>

        <div class="mb-3">
            <label for="filterStyle" class="form-label small fw-bold">Play Style</label>
            <select id="filterStyle" class="form-select form-select-sm">
                <option value="">Any Style</option>
                <option>Casual</option><option>Competitive</option><option>Story-driven</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="filterGender" class="form-label small fw-bold">Preferred Player Gender</label>
            <select id="filterGender" class="form-select form-select-sm">
                <option value="">Any</option>
                <option>Male</option><option>Female</option><option>Non-binary</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="filterAgeMin" class="form-label small fw-bold">Host Age Range: <span id="ageDisplay" class="text-caramel">All ages</span></label>
            <div class="row g-2 align-items-center">
                <div class="col"><input type="number" id="filterAgeMin" class="form-control form-control-sm" placeholder="Min" min="13" max="80"></div>
                <div class="col-auto text-muted">–</div>
                <div class="col"><input type="number" id="filterAgeMax" class="form-control form-control-sm" placeholder="Max" min="13" max="80"></div>
            </div>
        </div>

        <div class="mb-2 form-check">
            <input type="checkbox" class="form-check-input" id="filterSpots">
            <label class="form-check-label small" for="filterSpots">Spots still available</label>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="filterOnline">
            <label class="form-check-label small" for="filterOnline">Host online now</label>
        </div>

        <button id="btnResetFilters" class="btn btn-outline-primary btn-sm w-100">
            <span class="material-icons align-middle me-1" style="font-size:1rem;">refresh</span>Reset Filters
        </button>
    </div>

</div></div>

<!-- Feed -->
<div class="col-md-8 col-lg-9">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <span class="text-muted small">Showing <strong id="resultCount"><?php echo $total_posts; ?></strong> sessions</span>
        <div class="d-flex gap-1 align-items-center flex-wrap">
            <span class="small text-muted me-1">Sort:</span>
            <button class="sort-pill active" data-sort="newest">Newest</button>
            <button class="sort-pill" data-sort="urgent">Urgent</button>
            <button class="sort-pill" data-sort="spots">Most Spots</button>
            <button class="sort-pill" data-sort="online">Online First</button>
        </div>
    </div>

    <div class="active-filters" id="activeFilters" aria-live="polite"></div>
    <div id="postsContainer">

    <?php if (empty($posts) && !$pdo_error): ?>
        <div class="empty-state"><span class="material-icons">casino</span><h3 class="h5 mt-2">No open sessions yet</h3><p>Be the first to post a session!</p></div>
    <?php endif; ?>

    <?php foreach ($posts as $p):
        $filled=(int)$p['filled']; $total=(int)$p['spots']; $left=$total-$filled;
        $pct=$total>0?round($filled/$total*100):100; $full=$left<=0;
        $urgent=(bool)$p['urgent']; $online=(bool)$p['is_online'];
        $mine=$is_logged_in && (int)$p['member_id']===(int)$_SESSION['member_id'];
        $liked=in_array($p['post_id'], $my_interests);
        $joined=in_array($p['post_id'], $my_joins);
    ?>
    <article class="card post-card mb-3 <?php echo $urgent?'urgent-card':''; ?>"
            data-game-type="<?php echo htmlspecialchars($p['game_type']); ?>"
            data-skill="<?php echo htmlspecialchars($p['skill']); ?>"
            data-style="<?php echo htmlspecialchars($p['play_style']); ?>"
            data-gender="<?php echo htmlspecialchars($p['pref_gender']??''); ?>"
            data-age="0" data-online="<?php echo $online?'1':'0'; ?>"
            data-spots-left="<?php echo $left; ?>" data-urgent="<?php echo $urgent?'1':'0'; ?>"
            data-total="<?php echo $total; ?>"
            data-title="<?php echo htmlspecialchars(strtolower($p['title'].' '.$p['game_name'].' '.$p['author'])); ?>">
        <div class="card-body">

            <div class="d-flex align-items-start gap-3 mb-2">
                <div class="player-avatar">
                    <?php echo htmlspecialchars($p['initials']); ?>
                    <span class="online-dot <?php echo $online?'online':'offline'; ?>"></span>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <strong><?php echo htmlspecialchars($p['author']); ?></strong>
                        <span class="badge badge-genre"><?php echo htmlspecialchars($p['game_type']); ?></span>
                        <span class="badge <?php echo $skill_badge[$p['skill']]??'bg-secondary'; ?>"><?php echo htmlspecialchars($p['skill']); ?></span>
                        <?php if($urgent): ?><span class="badge" style="background:var(--color-berry);">🔥 Urgent</span><?php endif; ?>
                        <?php if($mine): ?><span class="badge bg-secondary">Your post</span><?php endif; ?>
                    </div>
                    <div class="text-muted small mt-1">
                        <?php echo timeAgo($p['created_at']); ?> &bull;
                        <span class="material-icons align-middle" style="font-size:.85rem;">schedule</span>
                        <?php echo htmlspecialchars(fmtDate($p['session_date'],$p['session_time'])); ?>
                    </div>
                </div>
            </div>

            <h2 class="h6 fw-bold mb-1" style="font-family:var(--font-body);"><?php echo htmlspecialchars($p['title']); ?></h2>
            <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($p['body']); ?></p>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="detail-pill"><span class="material-icons">sports_esports</span><?php echo htmlspecialchars($p['game_name']); ?></span>
                <span class="detail-pill"><span class="material-icons">emoji_events</span><?php echo htmlspecialchars($p['play_style']); ?></span>
                <?php if(!empty($p['pref_age'])): ?><span class="detail-pill"><span class="material-icons">people</span>Age: <?php echo htmlspecialchars($p['pref_age']); ?></span><?php endif; ?>
                <?php if(!empty($p['pref_gender'])&&$p['pref_gender']!=='Any'): ?><span class="detail-pill"><span class="material-icons">person</span><?php echo htmlspecialchars($p['pref_gender']); ?></span><?php endif; ?>
                <?php if(!empty($p['pref_skill'])&&$p['pref_skill']!=='Any'): ?><span class="detail-pill"><span class="material-icons">star</span><?php echo htmlspecialchars($p['pref_skill']); ?></span><?php endif; ?>
            </div>

            <div class="mb-3">
                <span class="small fw-bold spots-display" style="color:var(--color-walnut);">Spots: <?php echo $filled; ?>/<?php echo $total; ?></span>
                <?php if($full): ?> <span class="badge ms-1" style="background:var(--color-berry);">Full</span><?php endif; ?>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex gap-2 flex-wrap">
                    <?php if($is_logged_in && !$mine): ?>
                        <?php if(!$full): ?>
                            <button class="btn btn-sm btn-join <?php echo $joined?'btn-primary':'btn-outline-primary'; ?>"
                                    data-post-id="<?php echo $p['post_id']; ?>"
                                    data-game="<?php echo htmlspecialchars($p['game_name']); ?>"
                                    data-joined="<?php echo $joined?'1':'0'; ?>">
                                <span class="material-icons align-middle me-1" style="font-size:1rem;"><?php echo $joined?'check_circle':'how_to_reg'; ?></span><span class="btn-text"><?php echo $joined?'Joined ✓':'Join Session'; ?></span>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-outline-primary btn-sm" disabled><span class="material-icons align-middle me-1" style="font-size:1rem;">block</span>Session Full</button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-interest <?php echo $liked?'btn-primary':'btn-outline-primary'; ?>"
                                data-post-id="<?php echo $p['post_id']; ?>"
                                data-liked="<?php echo $liked?'1':'0'; ?>">
                            <span class="material-icons align-middle me-1" style="font-size:1rem;">thumb_up</span><span class="btn-text"><?php echo $liked?'Interested ✓':'Interested'; ?></span>
                        </button>
                    <?php elseif($mine): ?>
                        <button class="btn btn-outline-primary btn-sm btn-edit-post"
                                data-id="<?php echo $p['post_id']; ?>"
                                data-title="<?php echo htmlspecialchars($p['title'],ENT_QUOTES); ?>"
                                data-game="<?php echo htmlspecialchars($p['game_name'],ENT_QUOTES); ?>"
                                data-game-type="<?php echo htmlspecialchars($p['game_type'],ENT_QUOTES); ?>"
                                data-date="<?php echo htmlspecialchars($p['session_date'],ENT_QUOTES); ?>"
                                data-time="<?php echo htmlspecialchars(substr($p['session_time'],0,5),ENT_QUOTES); ?>"
                                data-spots="<?php echo $total; ?>"
                                data-skill="<?php echo htmlspecialchars($p['skill'],ENT_QUOTES); ?>"
                                data-style="<?php echo htmlspecialchars($p['play_style']??'',ENT_QUOTES); ?>"
                                data-pref-gender="<?php echo htmlspecialchars($p['pref_gender']??'Any',ENT_QUOTES); ?>"
                                data-pref-skill="<?php echo htmlspecialchars($p['pref_skill']??'Any',ENT_QUOTES); ?>"
                                data-pref-age="<?php echo htmlspecialchars($p['pref_age']??'',ENT_QUOTES); ?>"
                                data-body="<?php echo htmlspecialchars($p['body']??'',ENT_QUOTES); ?>"
                                data-urgent="<?php echo $urgent?'1':'0'; ?>">
                            <span class="material-icons align-middle me-1" style="font-size:1rem;">edit</span>Edit
                        </button>
                        <button class="btn btn-outline-danger btn-sm btn-delete-post"
                                data-id="<?php echo $p['post_id']; ?>"
                                data-title="<?php echo htmlspecialchars($p['title'],ENT_QUOTES); ?>">
                            <span class="material-icons align-middle me-1" style="font-size:1rem;">delete</span>Delete
                        </button>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary btn-sm"><span class="material-icons align-middle me-1" style="font-size:1rem;">login</span>Sign In to Join</a>
                    <?php endif; ?>
                    <?php if($is_admin && !$mine): ?>
                        <button class="btn btn-outline-danger btn-sm btn-admin-delete"
                                data-id="<?php echo $p['post_id']; ?>"
                                data-title="<?php echo htmlspecialchars($p['title'],ENT_QUOTES); ?>">
                            <span class="material-icons align-middle me-1" style="font-size:1rem;">admin_panel_settings</span>Remove
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-outline-primary btn-sm btn-share" data-title="<?php echo htmlspecialchars($p['title']); ?>">
                        <span class="material-icons align-middle" style="font-size:1rem;">share</span>
                    </button>
                </div>
                <span class="interest-count text-muted small">
                    <span class="material-icons">thumb_up</span>
                    <span class="interest-num" data-id="<?php echo $p['post_id']; ?>"><?php echo (int)$p['interest_count']; ?></span> interested
                </span>
            </div>

        </div>
    </article>
    <?php endforeach; ?>

    </div><!-- /postsContainer -->

    <output class="empty-state d-none" id="emptyState" aria-live="polite">
        <span class="material-icons">search_off</span>
        <h3 class="h5 mt-2">No sessions match your filters</h3>
        <p>Try adjusting your filters or <button class="btn btn-link p-0 align-baseline" id="emptyReset">reset them all</button>.</p>
    </output>

</div><!-- /feed col -->
</div><!-- /row -->
</div><!-- /container -->
</main>

<?php if ($is_logged_in): ?>

<!-- Create / Edit modal -->
<dialog class="modal-overlay" id="postModal" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-box">
        <button class="btn-close position-absolute top-0 end-0 m-3" id="closeModal" aria-label="Close"></button>
        <h2 id="modalTitle" class="mb-4" style="color:var(--color-espresso);">
            <span class="material-icons align-middle me-2 text-caramel" id="modalIcon">add_circle</span>
            <span id="modalTitleText">Post a Session</span>
        </h2>
        <form id="postForm" method="post" action="process/process_matchmaking.php" class="needs-validation" novalidate>
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="post_id" id="formPostId" value="">
            <p class="text-muted small mb-3"><span class="text-danger">*</span> Required fields</p>

            <?php if (!empty($my_bookings)): ?>
            <div class="mb-3">
                <label for="linkBooking" class="form-label fw-bold">Link to Your Booking <span class="text-muted small fw-normal">(optional)</span></label>
                <select id="linkBooking" name="booking_id" class="form-select">
                    <option value="">— No booking, enter date &amp; time manually —</option>
                    <?php foreach ($my_bookings as $bk):
                        $bk_date_fmt = date('d M Y', strtotime($bk['booking_date']));
                        $bk_val = $slot_label_to_value[$bk['time_slot']] ?? '';
                    ?>
                    <option value="<?php echo (int)$bk['booking_id']; ?>"
                            data-date="<?php echo htmlspecialchars($bk['booking_date']); ?>"
                            data-time="<?php echo htmlspecialchars($bk_val); ?>">
                        <?php echo htmlspecialchars("$bk_date_fmt — {$bk['time_slot']}"); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Selecting a booking will auto-fill the date and time slot below.</div>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="postTitle" class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                <input type="text" id="postTitle" name="title" class="form-control" maxlength="80" placeholder="e.g. Looking for Catan rivals this Saturday!" required>
                <div class="char-count"><span id="titleCount">0</span>/80</div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="postGame" class="form-label fw-bold">Game Name <span class="text-danger">*</span></label>
                    <input type="text" id="postGame" name="game_name" class="form-control" placeholder="e.g. Catan, Codenames…" required>
                </div>
                <div class="col-sm-6">
                    <label for="postGameType" class="form-label fw-bold">Game Type <span class="text-danger">*</span></label>
                    <select id="postGameType" name="game_type" class="form-select" required>
                        <option value="">Select type</option>
                        <?php foreach(['Strategy','Party','Cooperative','Deck-Building','Role-Playing','Trivia','Word'] as $gt): ?>
                        <option><?php echo $gt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="postDate" class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                    <input type="date" id="postDate" name="session_date" class="form-control" required>
                </div>
                <div class="col-sm-6">
                    <label for="postTime" class="form-label fw-bold">Time Slot <span class="text-danger">*</span></label>
                    <select id="postTime" name="session_time" class="form-select" required>
                        <option value="">Select time slot</option>
                        <option value="11:00">11:00 AM – 1:00 PM</option>
                        <option value="13:00">1:00 PM – 3:00 PM</option>
                        <option value="15:00">3:00 PM – 5:00 PM</option>
                        <option value="17:00">5:00 PM – 7:00 PM</option>
                        <option value="19:00">7:00 PM – 9:00 PM</option>
                        <option value="21:00">9:00 PM – 11:00 PM</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="postSpots" class="form-label fw-bold">Spots Needed <span class="text-danger">*</span></label>
                    <input type="number" id="postSpots" name="spots_total" class="form-control" min="1" max="10" placeholder="e.g. 3" required>
                </div>
                <div class="col-sm-6">
                    <label for="postSkill" class="form-label fw-bold">Your Skill Level <span class="text-danger">*</span></label>
                    <select id="postSkill" name="skill_level" class="form-select" required>
                        <option value="">Select level</option>
                        <option>Beginner</option><option>Intermediate</option><option>Advanced</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="postStyle" class="form-label fw-bold">Play Style</label>
                <select id="postStyle" name="play_style" class="form-select">
                    <option value="">No preference</option>
                    <option>Casual</option><option>Competitive</option><option>Story-driven</option>
                </select>
            </div>
            <p class="fw-bold mb-2" style="color:var(--color-espresso);">Who are you looking for?</p>
            <div class="row g-3 mb-3">
                <div class="col-sm-4">
                    <label for="prefGender" class="form-label small">Gender</label>
                    <select id="prefGender" name="pref_gender" class="form-select form-select-sm">
                        <option value="Any">Any</option>
                        <option>Male</option><option>Female</option><option>Non-binary</option>
                    </select>
                </div>
                <div class="col-sm-4">
                    <label for="prefSkill" class="form-label small">Skill Level</label>
                    <select id="prefSkill" name="pref_skill" class="form-select form-select-sm">
                        <option value="Any">Any</option>
                        <option>Beginner</option><option>Intermediate</option><option>Advanced</option><option>Intermediate+</option>
                    </select>
                </div>
                <div class="col-sm-4">
                    <label for="prefAge" class="form-label small">Age Range</label>
                    <input type="text" id="prefAge" name="pref_age" class="form-control form-control-sm" placeholder="e.g. 18–30 or Any">
                </div>
            </div>
            <div class="mb-3">
                <label for="postBody" class="form-label fw-bold">Description</label>
                <textarea id="postBody" name="body" class="form-control" rows="3" maxlength="400" placeholder="Tell potential players what to expect…"></textarea>
                <div class="char-count"><span id="bodyCount">0</span>/400</div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="postUrgent" name="is_urgent" value="1">
                <label class="form-check-label" for="postUrgent">🔥 Mark as urgent <span class="text-muted small">(session is very soon or last spot)</span></label>
            </div>
            <button type="submit" id="formSubmitBtn" class="btn btn-primary w-100">
                <span class="material-icons align-middle me-1">send</span><span id="formSubmitText">Post Session</span>
            </button>
        </form>
    </div>
</dialog>

<!-- Hidden delete form (submitted via JS) -->
<form id="deleteForm" method="post" action="process/process_matchmaking.php" style="display:none;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" id="deleteFormAction" value="delete">
    <input type="hidden" name="post_id" id="deletePostId" value="">
</form>

<?php endif; ?>

<output class="toast-custom" id="toast" aria-live="polite" aria-atomic="true"></output>

<?php include_once "inc/footer.inc.php"; ?>

<script>
(() => {
    "use strict";
    const $ = id => document.getElementById(id);
    const qsa = sel => [...document.querySelectorAll(sel)];
    const showToast = (msg, ms=3000) => { const t=$("toast"); t.textContent=msg; t.classList.add("show"); setTimeout(()=>t.classList.remove("show"),ms); };

    // ── Modal helpers ──────────────────────────────────────────────────
    const modal=$("postModal"), closeBtn=$("closeModal");
    const openModal = () => modal.classList.add("open");
    const closeModal = () => { modal.classList.remove("open"); resetModalToCreate(); };

    function setSelect(id, val) {
        const el=$(id); if(!el) return;
        for(let o of el.options) if(o.value===val||o.text===val){ el.value=o.value; return; }
        el.value="";
    }

    function resetModalToCreate() {
        $("formAction").value="create";
        $("formPostId").value="";
        $("modalIcon").textContent="add_circle";
        $("modalTitleText").textContent="Post a Session";
        $("formSubmitText").textContent="Post Session";
        $("postForm").reset();
        $("titleCount").textContent="0";
        $("bodyCount").textContent="0";
        const dateEl=$("postDate");
        if(dateEl) dateEl.readOnly=false;
        initDateConstraints();
    }

    // Set today as min date and filter past times if today is selected
    function initDateConstraints() {
        const dateEl=$("postDate"), timeEl=$("postTime");
        if(!dateEl||!timeEl) return;
        const today=new Date();
        const yyyy=today.getFullYear();
        const mm=String(today.getMonth()+1).padStart(2,'0');
        const dd=String(today.getDate()).padStart(2,'0');
        const todayStr=`${yyyy}-${mm}-${dd}`;
        dateEl.min=todayStr;
        filterTimeOptions();
        dateEl.addEventListener("change", filterTimeOptions);
    }

    function filterTimeOptions() {
        const dateEl=$("postDate"), timeEl=$("postTime");
        if(!dateEl||!timeEl) return;
        const today=new Date();
        const yyyy=today.getFullYear();
        const mm=String(today.getMonth()+1).padStart(2,'0');
        const dd=String(today.getDate()).padStart(2,'0');
        const todayStr=`${yyyy}-${mm}-${dd}`;
        const isToday=dateEl.value===todayStr;
        for(const opt of timeEl.options) {
            if(!opt.value){ opt.disabled=false; continue; }
            // opt.value is "HH:MM" — disable if today and slot start has passed
            const [h,m]=opt.value.split(':').map(Number);
            opt.disabled = isToday && (today.getHours()*60+today.getMinutes()) >= (h*60+m);
        }
        // Reset selection if the currently selected slot got disabled
        if(timeEl.selectedOptions[0]?.disabled) timeEl.value="";
    }

    // Open in CREATE mode
    const openBtn=$("openPostModal");
    if(openBtn) openBtn.addEventListener("click", ()=>{ resetModalToCreate(); openModal(); $("postTitle").focus(); });

    // Booking selector — auto-fills date & time slot when a booking is chosen
    const linkBooking=$("linkBooking");
    if(linkBooking) {
        linkBooking.addEventListener("change", () => {
            const opt = linkBooking.options[linkBooking.selectedIndex];
            const dateEl=$("postDate"), timeEl=$("postTime");
            if(opt.value && opt.dataset.date && opt.dataset.time) {
                dateEl.value = opt.dataset.date;
                // Re-run constraints so past slots get disabled first
                initDateConstraints();
                // Then set the value (only if option is still enabled)
                setSelect("postTime", opt.dataset.time);
                // Lock date/time to prevent manual override when booking is linked
                dateEl.readOnly = true;
                timeEl.disabled = false; // keep enabled so it submits
            } else {
                dateEl.readOnly = false;
                initDateConstraints();
            }
        });
    }

    // Open in EDIT mode
    document.addEventListener("click", e=>{
        const b=e.target.closest(".btn-edit-post"); if(!b) return;
        const d=b.dataset;
        $("formAction").value="update";
        $("formPostId").value=d.id;
        $("modalIcon").textContent="edit";
        $("modalTitleText").textContent="Edit Session";
        $("formSubmitText").textContent="Save Changes";
        $("postTitle").value=d.title;      $("titleCount").textContent=d.title.length;
        $("postGame").value=d.game;
        setSelect("postGameType", d.gameType);
        $("postDate").value=d.date;
        initDateConstraints();
        setSelect("postTime", d.time);
        $("postSpots").value=d.spots;
        setSelect("postSkill", d.skill);
        setSelect("postStyle", d.style);
        setSelect("prefGender", d.prefGender);
        setSelect("prefSkill",  d.prefSkill);
        $("prefAge").value=d.prefAge||"";
        $("postBody").value=d.body||"";    $("bodyCount").textContent=(d.body||"").length;
        $("postUrgent").checked=d.urgent==="1";
        openModal(); $("postTitle").focus();
    });

    // Delete with confirmation (post owner)
    document.addEventListener("click", e=>{
        const b=e.target.closest(".btn-delete-post"); if(!b) return;
        if(!confirm(`Delete "${b.dataset.title}"? This cannot be undone.`)) return;
        $("deletePostId").value=b.dataset.id;
        $("deleteFormAction").value="delete";
        $("deleteForm").submit();
    });

    // Admin remove (any post)
    document.addEventListener("click", e=>{
        const b=e.target.closest(".btn-admin-delete"); if(!b) return;
        if(!confirm(`Remove "${b.dataset.title}" as admin? This cannot be undone.`)) return;
        $("deletePostId").value=b.dataset.id;
        $("deleteFormAction").value="admin_delete";
        $("deleteForm").submit();
    });

    if(closeBtn) closeBtn.addEventListener("click", closeModal);
    if(modal) {
        modal.addEventListener("click", e=>{ if(e.target===modal) closeModal(); });
        document.addEventListener("keydown", e=>{ if(e.key==="Escape") closeModal(); });
        const cc=(inp,cnt)=>{ const i=$(inp),c=$(cnt); if(i&&c) i.addEventListener("input",()=>c.textContent=i.value.length); };
        cc("postTitle","titleCount"); cc("postBody","bodyCount");
    }

    // Post form — client-side validation before submit
    const postForm=$("postForm");
    if (postForm) postForm.addEventListener("submit", e=>{
        const req=["postTitle","postGame","postGameType","postDate","postTime","postSpots","postSkill"];
        let ok=true;
        req.forEach(id=>{ const el=$(id); el.classList.toggle("is-invalid",!el.value.trim()); if(!el.value.trim()) ok=false; });
        if (!ok) { e.preventDefault(); showToast("Please fill in all required fields."); return; }

        // Block past date+time combinations
        const dateVal=$("postDate").value, timeVal=$("postTime").value;
        if(dateVal && timeVal) {
            const chosen=new Date(`${dateVal}T${timeVal}:00`);
            if(chosen <= new Date()) {
                e.preventDefault();
                $("postTime").classList.add("is-invalid");
                showToast("Please choose a future date and time.");
                return;
            }
        }
    });

    // Filters
    let F = { search:"", gameType:"", skill:"", style:"", gender:"", ageMin:"", ageMax:"", spotsOnly:false, onlineOnly:false, sort:"newest" };

    qsa("#filterSkill .sort-pill").forEach(b=>b.addEventListener("click",()=>{
        qsa("#filterSkill .sort-pill").forEach(x=>x.classList.remove("active"));
        b.classList.add("active"); F.skill=b.dataset.skill; applyFilters();
    }));
    qsa("[data-sort]").forEach(b=>b.addEventListener("click",()=>{
        qsa("[data-sort]").forEach(x=>x.classList.remove("active"));
        b.classList.add("active"); F.sort=b.dataset.sort; applyFilters();
    }));
    $("searchInput").addEventListener("input",   e=>{ F.search=e.target.value.toLowerCase(); applyFilters(); });
    $("filterGameType").addEventListener("change",e=>{ F.gameType=e.target.value; applyFilters(); });
    $("filterStyle").addEventListener("change",  e=>{ F.style=e.target.value; applyFilters(); });
    $("filterGender").addEventListener("change", e=>{ F.gender=e.target.value; applyFilters(); });
    $("filterAgeMin").addEventListener("input",  e=>{ F.ageMin=e.target.value; updAge(); applyFilters(); });
    $("filterAgeMax").addEventListener("input",  e=>{ F.ageMax=e.target.value; updAge(); applyFilters(); });
    $("filterSpots").addEventListener("change",  e=>{ F.spotsOnly=e.target.checked; applyFilters(); });
    $("filterOnline").addEventListener("change", e=>{ F.onlineOnly=e.target.checked; applyFilters(); });

    function updAge() { $("ageDisplay").textContent=(F.ageMin||F.ageMax)?`${F.ageMin||"?"}–${F.ageMax||"?"}`:"All ages"; }

    function applyFilters() {
        const cards=qsa("#postsContainer article"); let vis=[];
        cards.forEach(c=>{
            const show=!(
                (F.search    &&!c.dataset.title.includes(F.search))||
                (F.gameType  &&c.dataset.gameType!==F.gameType)||
                (F.skill     &&c.dataset.skill!==F.skill)||
                (F.style     &&c.dataset.style!==F.style)||
                (F.gender    &&c.dataset.gender!==F.gender)||
                (F.ageMin    &&parseInt(c.dataset.age)<parseInt(F.ageMin))||
                (F.ageMax    &&parseInt(c.dataset.age)>parseInt(F.ageMax))||
                (F.spotsOnly &&parseInt(c.dataset.spotsLeft)<=0)||
                (F.onlineOnly&&c.dataset.online!=="1")
            );
            c.style.display=show?"":"none"; if(show) vis.push(c);
        });
        const cont=$("postsContainer");
        [...vis].sort((a,b)=>{
            if(F.sort==="urgent") return +b.dataset.urgent-+a.dataset.urgent;
            if(F.sort==="spots")  return +b.dataset.spotsLeft-+a.dataset.spotsLeft;
            if(F.sort==="online") return +b.dataset.online-+a.dataset.online;
            return 0;
        }).forEach(c=>cont.appendChild(c));
        $("resultCount").textContent=vis.length;
        $("emptyState").classList.toggle("d-none",vis.length>0);
        renderTags();
    }

    function renderTags() {
        const bar=$("activeFilters"); bar.innerHTML="";
        const add=(lbl,fn)=>{ const t=document.createElement("span"); t.className="filter-tag"; t.innerHTML=`${lbl} <button class="remove-filter">✕</button>`; t.querySelector("button").addEventListener("click",fn); bar.appendChild(t); };
        if(F.search)    add(`"${F.search}"`,  ()=>{ $("searchInput").value=""; F.search=""; applyFilters(); });
        if(F.gameType)  add(`${F.gameType}`,  ()=>{ $("filterGameType").value=""; F.gameType=""; applyFilters(); });
        if(F.skill)     add(`${F.skill}`,      ()=>{ qsa("#filterSkill .sort-pill").forEach(b=>b.classList.toggle("active",b.dataset.skill==="")); F.skill=""; applyFilters(); });
        if(F.style)     add(`${F.style}`,      ()=>{ $("filterStyle").value=""; F.style=""; applyFilters(); });
        if(F.gender)    add(`${F.gender}`,     ()=>{ $("filterGender").value=""; F.gender=""; applyFilters(); });
        if(F.ageMin||F.ageMax) add(`Age ${F.ageMin||"?"}–${F.ageMax||"?"}`,()=>{ $("filterAgeMin").value=$("filterAgeMax").value=""; F.ageMin=F.ageMax=""; updAge(); applyFilters(); });
        if(F.spotsOnly) add("Has spots", ()=>{ $("filterSpots").checked=false; F.spotsOnly=false; applyFilters(); });
        if(F.onlineOnly)add("Online now",()=>{ $("filterOnline").checked=false; F.onlineOnly=false; applyFilters(); });
    }

    function resetAll() {
        ["searchInput","filterGameType","filterStyle","filterGender"].forEach(id=>$(id).value="");
        ["filterAgeMin","filterAgeMax"].forEach(id=>$(id).value="");
        ["filterSpots","filterOnline"].forEach(id=>$(id).checked=false);
        qsa("#filterSkill .sort-pill").forEach(b=>b.classList.toggle("active",b.dataset.skill===""));
        qsa("[data-sort]").forEach(b=>b.classList.toggle("active",b.dataset.sort==="newest"));
        F={search:"",gameType:"",skill:"",style:"",gender:"",ageMin:"",ageMax:"",spotsOnly:false,onlineOnly:false,sort:"newest"};
        updAge(); applyFilters();
    }
    $("btnResetFilters").addEventListener("click",resetAll);
    $("emptyReset").addEventListener("click",resetAll);

    // Helper: post action to process_matchmaking.php via fetch
    function postAction(action, postId) {
        const fd = new FormData();
        fd.append("action", action);
        fd.append("post_id", postId);
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        if (csrfInput) fd.append("csrf_token", csrfInput.value);
        return fetch("process/process_matchmaking.php", {method:"POST", body:fd});
    }

    // Join — toggle on/off, update spots display
    document.addEventListener("click",e=>{
        const b=e.target.closest(".btn-join"); if(!b) return;
        const joined = b.dataset.joined==="1";
        const icon = b.querySelector(".material-icons");
        const txt  = b.querySelector(".btn-text");
        const card = b.closest("article");
        const spotsEl = card.querySelector(".spots-display");
        const total = parseInt(card.dataset.total);
        const curLeft = parseInt(card.dataset.spotsLeft);

        if (!joined) {
            postAction("join", b.dataset.postId);
            b.dataset.joined="1";
            b.classList.replace("btn-outline-primary","btn-primary");
            icon.textContent="check_circle";
            txt.textContent="Joined ✓";
            const newFilled = total - curLeft + 1;
            card.dataset.spotsLeft = curLeft - 1;
            spotsEl.textContent = `Spots: ${newFilled}/${total}`;
            showToast(`You've joined the ${b.dataset.game} session!`);
        } else {
            postAction("unjoin", b.dataset.postId);
            b.dataset.joined="0";
            b.classList.replace("btn-primary","btn-outline-primary");
            icon.textContent="how_to_reg";
            txt.textContent="Join Session";
            const newFilled = total - curLeft - 1;
            card.dataset.spotsLeft = curLeft + 1;
            spotsEl.textContent = `Spots: ${newFilled}/${total}`;
            showToast(`Left the ${b.dataset.game} session.`);
        }
    });

    // Interest — toggle on/off
    document.addEventListener("click",e=>{
        const b=e.target.closest(".btn-interest"); if(!b) return;
        const n   = b.closest("article").querySelector(".interest-num");
        const txt = b.querySelector(".btn-text");
        const liked = b.dataset.liked==="1";
        if (!liked) {
            postAction("interest", b.dataset.postId);
            b.dataset.liked="1";
            b.classList.replace("btn-outline-primary","btn-primary");
            txt.textContent="Interested ✓";
            n.textContent=+n.textContent+1;
            showToast("Marked as interested!");
        } else {
            postAction("uninterest", b.dataset.postId);
            b.dataset.liked="0";
            b.classList.replace("btn-primary","btn-outline-primary");
            txt.textContent="Interested";
            n.textContent=Math.max(0,+n.textContent-1);
            showToast("Removed interest.");
        }
    });
    // Share
    document.addEventListener("click",e=>{
        const b=e.target.closest(".btn-share"); if(!b) return;
        const url=location.href;
        if(navigator.share) {
            navigator.share({title:b.dataset.title, url});
        } else if(navigator.clipboard && location.protocol==="https:") {
            navigator.clipboard.writeText(url).then(()=>showToast("📋 Link copied!")).catch(()=>fallbackCopy(url));
        } else {
            fallbackCopy(url);
        }
    });

    function fallbackCopy(text) {
        const ta=document.createElement("textarea");
        ta.value=text;
        ta.style.cssText="position:fixed;top:0;left:0;opacity:0;pointer-events:none;";
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        try { document.execCommand("copy"); showToast("📋 Link copied!"); }
        catch { showToast("Could not copy — please copy the URL manually."); }
        document.body.removeChild(ta);
    }
})();
</script>
