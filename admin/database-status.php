<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('admin');
$expected = ['users', 'supplier', 'b2b_buyer', 'b2c_buyer', 'textile_batch', 'listing', 'b2b_listing', 'b2c_listing', 'quotation', 'orders', 'order_item', 'payment', 'stock_transaction'];
$healthy = false;
$details = [];
$recent = [];
$present = [];
try {
    $pdo = db();
    $healthy = true;
    $details['Database'] = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $details['Server'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    $details['Driver'] = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $statement = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME');
    $statement->execute([$details['Database']]);
    $present = $statement->fetchAll(PDO::FETCH_COLUMN);
    $details['Expected tables'] = count($expected);
    $details['Present tables'] = count(array_intersect($expected, $present));
    $details['Users'] = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $details['Suppliers'] = $pdo->query('SELECT COUNT(*) FROM supplier')->fetchColumn();
    $details['B2B buyers'] = $pdo->query('SELECT COUNT(*) FROM b2b_buyer')->fetchColumn();
    $details['B2C buyers'] = $pdo->query('SELECT COUNT(*) FROM b2c_buyer')->fetchColumn();
    $recent = $pdo->query('SELECT user_id,name,email,user_status,created_at FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll();
} catch (Throwable $exception) {
    $details['Safe error'] = 'The application could not connect. Credentials are intentionally hidden.';
}
$missing = array_values(array_diff($expected, $present));
$pageTitle = 'Database status';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">System status</div>
            <h1>Database status</h1>
            <p>Database server and account information.</p>
        </div>
        <span class="<?=$healthy && !$missing ? 'badge-success' : 'badge-danger'?>"> <?=$healthy && !$missing ? 'Healthy' : 'Needs attention'?> </span>
    </div>
    <section class="panel mt-0 <?=$healthy ? 'db-ok' : 'db-down'?>">
        <h2 class="h4">Database connection: <?=$healthy ? 'Connected' : 'Unavailable'?></h2>
        <div class="row g-3 mt-1">
            <?php foreach ($details as $label => $value):?>
            <div class="col-md-4">
                <div class="stat-card">
                    <span> <?=e($label)?> </span>
                    <strong class="fs-5"> <?=e($value)?> </strong>
                </div>
            </div>
            <?php endforeach;?>
        </div>
        <?php if ($missing):?>
        <div class="alert alert-danger mt-3 mb-0">Missing tables: <?=e(implode(', ', $missing))?></div>
        <?php endif;?>
    </section>
    <?php if ($healthy):?>
    <section class="panel">
        <h2 class="h4">Recent users</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $user):?>
                    <tr>
                        <td>#<?=e($user['user_id'])?></td>
                        <td><?=e($user['name'])?></td>
                        <td><?=e($user['email'])?></td>
                        <td>
                            <span class="<?=e(statusClass($user['user_status']))?>"> <?=e($user['user_status'])?> </span>
                        </td>
                        <td><?=e($user['created_at'])?></td>
                    </tr>
                    <?php endforeach;?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif;?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
