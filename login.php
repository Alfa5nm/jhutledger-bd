<?php
require __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(dashboardPath());
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = strtolower(input('email'));
    $password = (string) ($_POST['password'] ?? '');

    try {
        $statement = db()->prepare('SELECT user_id, name, email, password_hash, user_status FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['user_status'] !== 'Active') {
            $error = 'This account is not active. Contact the administrator.';
        } else {
            $baseRole = getUserRole(db(), (int) $user['user_id']);
            loginUser($user, $baseRole);
            setFlash('success', 'Welcome back, ' . $user['name'] . '.');
            redirect(dashboardPath());
        }
    } catch (Throwable) {
        $error = 'Database connection unavailable or account integrity check failed.';
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<main class="container narrow auth-shell">
<div class="panel">
<div class="eyebrow">Account access</div>
<h1 class="h2 mt-2">Welcome back</h1>
<p class="muted">Sign in to continue to your dashboard.</p>
<?php if ($error): ?>
<div class="alert alert-danger" role="alert">
<?= e($error) ?>
</div>
<?php endif; ?>
<form method="post">
<?= csrfField() ?>
<div class="mb-3">
<label class="form-label" for="email">Email</label>
<input class="form-control" type="email" id="email" name="email" required autocomplete="email" value="<?= e($email) ?>">
</div>
<div class="mb-3">
<label class="form-label" for="password">Password</label>
<input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
</div>
<button class="btn btn-primary w-100" type="submit">Log in</button>
</form>
</div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
