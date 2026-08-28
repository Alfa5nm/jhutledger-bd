<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('admin');
$pdo = db();
$queries = [
    'Total users' => 'SELECT COUNT(*) FROM users',
    'Pending payments' => "SELECT COUNT(*) FROM payment WHERE payment_status = 'Pending'",
    'Completed orders' => "SELECT COUNT(*) FROM orders WHERE order_status = 'Completed'",
    'Revenue' => "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status = 'Completed'",
];
$stats = [];
foreach ($queries as $label => $sql) {
    $stats[$label] = $pdo->query($sql)->fetchColumn();
}
$exceptions = adminExceptionCounts($pdo);
$exceptionTotal = array_sum($exceptions);
$pageTitle = 'Admin dashboard';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Administration</div>
            <h1>Administrator dashboard</h1>
            <p>Overview of users, payments, completed sales, and database health.</p>
        </div>
        <span class="badge-soft">Administrator</span>
    </div>
    <div class="stats-grid">
        <?php foreach ($stats as $label => $value):?>
        <div class="stat-card">
            <span> <?=e($label)?> </span>
            <strong data-count> <?=e($label === 'Revenue' ? money($value) : $value)?> </strong>
        </div>
        <?php endforeach;?>
    </div>
    <section class="panel attention-panel">
        <div>
            <div class="eyebrow">Operations monitor</div>
            <h2 class="h4"><?=e($exceptionTotal)?> items need review</h2>
        </div>
        <div class="attention-actions">
            <?php foreach (array_slice($exceptions, 0, 4, true) as $label => $count):?>
            <a href="<?=e(url('admin/exceptions.php'))?>">
                <strong> <?=e($count)?> </strong>
                <span> <?=e($label)?> </span>
            </a>
            <?php endforeach;?>
        </div>
        <a class="btn btn-primary" href="<?=e(url('admin/exceptions.php'))?>">Open exception monitor</a>
    </section>
    <section class="panel">
        <h2 class="h4">Administration tools</h2>
        <div class="role-tool-grid">
            <a href="<?=e(url('admin/users.php'))?>">Users <span>Search and manage access</span> </a>
            <a href="<?=e(url('admin/payments.php'))?>">Payments <span>Verify manual payments</span> </a>
            <a href="<?=e(url('admin/reports.php'))?>">Reports <span>Review platform performance</span> </a>
            <a href="<?=e(url('admin/database-status.php'))?>">Database <span>Check connection health</span> </a>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
