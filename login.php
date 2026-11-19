<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if ($auth->user() !== null) {
    redirect('index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
        } elseif ($auth->login($username, $password)) {
            redirect('index.php');
        } else {
            $error = 'Invalid username or password, or account is inactive.';
        }
    }
}

$csrf = e($auth->csrfToken());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to GSSF Family Match Tracker to record event times and compare progress.">
    <title>Sign In — GSSF Family Tracker</title>
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
<body class="auth-body">
<main class="auth-wrapper">
    <div class="auth-theme-bar">
        <button type="button" id="btn-theme-toggle" class="btn btn-ghost btn-sm" aria-label="Toggle light or dark mode">Dark Mode</button>
    </div>
    <section class="auth-card" aria-labelledby="login-heading">
        <div class="auth-brand">
            <span class="brand-mark" aria-hidden="true">G</span>
            <div>
                <h1 id="login-heading">GSSF Family Tracker</h1>
                <p class="auth-subtitle">Sign in to log match times and compare progress</p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="field">
                <label for="login-username">Username</label>
                <input type="text" id="login-username" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username" placeholder="e.g. admin">
            </div>
            <div class="field">
                <label for="login-password">Password</label>
                <input type="password" id="login-password" name="password" required autocomplete="current-password" placeholder="Enter your password">
            </div>
            <button type="submit" id="btn-login-submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

        <div class="auth-divider"><span>or</span></div>

        <div id="passkey-status" style="display:none;" role="alert"></div>
        <button type="button" id="btn-passkey-login" class="btn btn-secondary btn-block">Sign in with Passkey</button>
    </section>
</main>
<script>
    (function(){
        var btn = document.getElementById('btn-theme-toggle');
        if (!btn) return;
        function sync() {
            var cur = document.documentElement.getAttribute('data-theme') || 'light';
            btn.textContent = cur === 'dark' ? 'Light Mode' : 'Dark Mode';
        }
        sync();
        btn.addEventListener('click', function() {
            var cur = document.documentElement.getAttribute('data-theme') || 'light';
            var next = cur === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('gssf_theme', next);
            sync();
        });
    })();
</script>
<script src="assets/js/passkeys.js"></script>
</body>
</html>
