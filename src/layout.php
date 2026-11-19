<?php
declare(strict_types=1);

function render_header(string $title, string $activeNav, array $user, Auth $auth): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
    $csrf = e($auth->csrfToken());
    $displayName = e($user['display_name']);
    $roleLabel = ($user['role'] ?? 'member') === 'admin' ? 'Admin' : 'Shooter';
    $userColor = e($user['color'] ?? '#1d4ed8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage and compare GSSF family match results, stage times, and progress graphs.">
    <meta name="csrf-token" content="<?= $csrf ?>">
    <title><?= e($title) ?> — GSSF Family Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function(){
            var t = localStorage.getItem('gssf_theme');
            if (!t) { t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a href="index.php" class="brand" id="brand-home">
            <span class="brand-mark" aria-hidden="true">G</span>
            <span class="brand-text">
                <span class="brand-title">GSSF Family Tracker</span>
                <span class="brand-sub">Match Timers &amp; Progress</span>
            </span>
        </a>

        <nav class="main-nav" aria-label="Primary Navigation">
            <a href="index.php" id="nav-overview" class="nav-link <?= $activeNav === 'overview' ? 'is-active' : '' ?>">Overview</a>
            <a href="matches.php" id="nav-matches" class="nav-link <?= $activeNav === 'matches' ? 'is-active' : '' ?>">Matches</a>
            <a href="results.php" id="nav-results" class="nav-link <?= $activeNav === 'results' ? 'is-active' : '' ?>">Match Times</a>
            <a href="compare.php" id="nav-compare" class="nav-link <?= $activeNav === 'compare' ? 'is-active' : '' ?>">Progress Graphs</a>
            <a href="users.php" id="nav-users" class="nav-link <?= $activeNav === 'users' ? 'is-active' : '' ?>">Family Shooters</a>
        </nav>

        <div class="user-menu">
            <button type="button" id="btn-theme-toggle" class="btn btn-ghost btn-sm" aria-label="Toggle light or dark mode">Dark Mode</button>
            <span class="user-pill">
                <span class="color-dot" style="background-color: <?= $userColor ?>;"></span>
                <span class="user-name"><?= $displayName ?></span>
                <span class="user-role"><?= e($roleLabel) ?></span>
            </span>
            <a href="logout.php" id="btn-logout" class="btn btn-ghost btn-sm">Sign Out</a>
        </div>
    </div>
</header>
<main class="page-main">
    <div class="container">
    <?php
}

function render_footer(array $scripts = []): void
{
    ?>
    </div>
</main>
<footer class="site-footer">
    <div class="container footer-inner">
        <span>GSSF Family Match Tracker &middot; Lower time wins (Five to Glock + Glock the Plates + Glock 'M + Penalties)</span>
        <span>PHP &amp; Apache</span>
    </div>
</footer>
<script src="assets/js/app.js"></script>
<?php foreach ($scripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
    <?php
}
