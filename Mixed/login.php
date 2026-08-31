<?php
require __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(dashboardPath());
}

$email = (string) ($_SESSION['login_email'] ?? '');
unset($_SESSION['login_email']);

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<main class="container narrow auth-shell">
    <div class="panel">
        <div class="eyebrow">Account access</div>
        <h1 class="h2 mt-2">Welcome back</h1>
        <p class="muted">Sign in to continue to your dashboard.</p>
        <form method="post" action="<?= e(url('Mixed/actions/login.php')) ?>">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input
                    class="form-control"
                    type="email"
                    id="email"
                    name="email"
                    required
                    autocomplete="email"
                    value="<?= e($email) ?>"
                />
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input
                    class="form-control"
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                />
            </div>
            <button class="btn btn-primary w-100" type="submit">Log in</button>
        </form>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
