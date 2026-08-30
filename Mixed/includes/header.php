<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? appConfig('name');
$flashes = getFlashes();
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?= e($pageTitle) ?> | <?= e(appConfig('name')) ?></title>
        <link rel="preconnect" href="https://cdn.jsdelivr.net" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="<?= e(url('Mixed/assets/css/app.css?v=' . filemtime(__DIR__ . '/../assets/css/app.css'))) ?>" rel="stylesheet" />
    </head>
    <body>
        <nav class="topbar">
            <div class="container nav-inner">
                <a class="brand" href="<?= e(url()) ?>">
                    <span class="brand-mark">JL</span>
                    <span class="brand-name">JhutLedger<small>Bangladesh</small> </span>
                </a>
                <button
                    class="nav-toggle"
                    type="button"
                    aria-expanded="false"
                    aria-controls="primary-navigation"
                    aria-label="Open navigation"
                >
                    <span> </span>
                    <span> </span>
                    <span> </span>
                </button>
                <div class="nav-links" id="primary-navigation">
                    <?php if (isLoggedIn()): ?>
                    <div class="nav-group">
                        <span class="nav-group-label"> <?= e(formatRole(currentUser()['role'])) ?> </span>
                        <a href="<?= e(url(dashboardPath())) ?>">Dashboard</a>
                    </div>
                    <?php if (currentUser()['role'] === 'supplier'): ?>
                    <div class="nav-group">
                        <span class="nav-group-label">Inventory</span>
                        <a href="<?= e(url('Farid/supplier/batches.php')) ?>">Batches</a>
                        <a href="<?= e(url('Farid/supplier/listings.php')) ?>">Listings</a>
                        <a href="<?= e(url('Farid/supplier/stock-ledger.php')) ?>">Stock ledger</a>
                        <a href="<?= e(url('Farid/supplier/pricing-assistant.php')) ?>">Pricing assistant</a>
                    </div>
                    <div class="nav-group">
                        <span class="nav-group-label">Sales</span>
                        <a href="<?= e(url('Abir/supplier/quotations.php')) ?>">Quotations</a>
                        <a href="<?= e(url('Abir/supplier/orders.php')) ?>">Orders</a>
                        <a href="<?= e(url('Shishir/supplier/reports.php')) ?>">Reports</a>
                        <a href="<?= e(url('Shishir/supplier/sustainability.php')) ?>">Recirculation</a>
                    </div>
                    <?php elseif (currentUser()['role'] === 'b2b'): ?>
                    <div class="nav-group">
                        <span class="nav-group-label">Sourcing</span>
                        <a href="<?= e(url('Abir/marketplace.php')) ?>">Marketplace</a>
                        <a href="<?= e(url('Abir/b2b/quotations.php')) ?>">Quotations</a>
                        <a href="<?= e(url('Abir/b2b/orders.php')) ?>">Orders</a>
                    </div>
                    <?php elseif (currentUser()['role'] === 'b2c'): ?>
                    <div class="nav-group">
                        <span class="nav-group-label">Shopping</span>
                        <a href="<?= e(url('Abir/marketplace.php')) ?>">Marketplace</a>
                        <a href="<?= e(url('Abir/b2c/orders.php')) ?>">Orders</a>
                    </div>
                    <?php endif; ?>
<?php if (currentUser()['role'] === 'admin'): ?>
                    <div class="nav-group">
                        <span class="nav-group-label">Operations</span>
                        <a href="<?= e(url('Shishir/admin/exceptions.php')) ?>">Exceptions</a>
                        <a href="<?= e(url('Shishir/admin/users.php')) ?>">Users</a>
                        <a href="<?= e(url('Shishir/admin/payments.php')) ?>">Payments</a>
                    </div>
                    <div class="nav-group">
                        <span class="nav-group-label">Insight</span>
                        <a href="<?= e(url('Shishir/admin/reports.php')) ?>">Reports</a>
                        <a href="<?= e(url('Shishir/admin/sustainability.php')) ?>">Recirculation</a>
                        <a href="<?= e(url('Shishir/admin/database-status.php')) ?>">DB status</a>
                    </div>
                    <?php endif; ?>
                    <div class="nav-group nav-account">
                        <span class="nav-group-label">Account</span>
                        <a href="<?= e(url('Mixed/profile.php')) ?>">Profile</a>
                        <form method="post" action="<?= e(url('Mixed/logout.php')) ?>" class="inline-form">
                            <?= csrfField() ?>
                            <button type="submit" class="link-button">Logout</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <a href="<?= e(url('Mixed/login.php')) ?>">Login</a>
                    <a class="nav-cta" href="<?= e(url('Mixed/register.php')) ?>">Create account</a>
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
    </body>
</html>
