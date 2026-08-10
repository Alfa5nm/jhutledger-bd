<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? appConfig('name');
$flashes = getFlashes();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(appConfig('name')) ?></title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="topbar">
    <div class="container nav-inner">
        <a class="brand" href="<?= e(url()) ?>"><span>JL</span> JhutLedger BD</a>
        <div class="nav-links">
            <?php if (isLoggedIn()): ?>
                <a href="<?= e(url(dashboardPath())) ?>">Dashboard</a>
                <a href="<?= e(url('profile.php')) ?>">Profile</a>
                <?php if (currentUser()['role'] === 'admin'): ?>
                    <a href="<?= e(url('admin/users.php')) ?>">Users</a>
                    <a href="<?= e(url('admin/database-status.php')) ?>">DB Status</a>
                <?php endif; ?>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="inline-form">
                    <?= csrfField() ?>
                    <button type="submit" class="link-button">Logout</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('login.php')) ?>">Login</a>
                <a class="nav-cta" href="<?= e(url('register.php')) ?>">Create account</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<?php if ($flashes): ?>
    <div class="container flash-stack">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

