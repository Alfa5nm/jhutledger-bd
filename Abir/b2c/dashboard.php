<?php
require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('b2c');
$pdo = db();
$userId = currentUser()['user_id'];
$queries = [
    'Active B2C listings' => [
        "SELECT COUNT(*)
         FROM b2c_listing AS bl
         JOIN listing AS l ON l.listing_id = bl.listing_id
         WHERE l.status = 'Active'",
        [],
    ],
    'My orders' => ['SELECT COUNT(*) FROM orders WHERE buyer_id=? AND order_type=\'B2C\'', [$userId]],
    'Pending orders' => [
        "SELECT COUNT(*)
         FROM orders
         WHERE buyer_id = ? AND order_status IN ('Pending', 'Confirmed', 'Processing')",
        [$userId],
    ],
    'Paid orders' => [
        "SELECT COUNT(*)
         FROM payment AS p
         JOIN orders AS o ON o.order_id = p.order_id
         WHERE o.buyer_id = ? AND p.payment_status = 'Paid'",
        [$userId],
    ],
];
$stats = [];
foreach ($queries as $label => [$sql, $params]) {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $stats[$label] = $statement->fetchColumn();
}
$statement = $pdo->prepare(
    "SELECT COUNT(*)
     FROM orders AS o
     LEFT JOIN payment AS p ON p.order_id = o.order_id
     WHERE o.buyer_id = ?
       AND o.order_status IN ('Confirmed', 'Processing')
       AND (p.payment_id IS NULL OR p.payment_status IN ('Failed', 'Pending'))"
);
$statement->execute([$userId]);
$paymentAttention = (int) $statement->fetchColumn();
$pageTitle = 'B2C buyer dashboard';
require __DIR__ . '/../../Mixed/includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Retail buyer workspace</div>
            <h1>Welcome, <?=e(currentUser()['name'])?></h1>
            <p>Track retail listings, purchases, and payment activity.</p>
        </div>
        <span class="badge-soft">B2C Buyer</span>
    </div>
    <div class="stats-grid">
        <?php foreach ($stats as $label => $value):?>
        <div class="stat-card">
            <span> <?=e($label)?> </span>
            <strong data-count> <?=e($value)?> </strong>
        </div>
        <?php endforeach;?>
    </div>
    <section class="panel attention-panel">
        <div>
            <div class="eyebrow">Needs attention</div>
            <h2 class="h4">Keep orders moving</h2>
        </div>
        <div class="attention-actions">
            <a href="<?=e(url('Abir/b2c/orders.php'))?>">
                <strong> <?=e($stats['Pending orders'])?> </strong>
                <span>active orders</span>
            </a>
            <a href="<?=e(url('Abir/b2c/orders.php'))?>">
                <strong> <?=e($paymentAttention)?> </strong>
                <span>payments to review</span>
            </a>
        </div>
    </section>
    <section class="panel">
        <h2 class="h4">Retail sourcing</h2>
        <p>Browse bundle-sized listings and place an order against live stock.</p>
        <div class="role-tool-grid">
            <a href="<?=e(url('Abir/marketplace.php'))?>">Marketplace <span>Browse retail bundles</span> </a>
            <a href="<?=e(url('Abir/b2c/orders.php'))?>">Orders <span>Track, pay, and print</span> </a>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../../Mixed/includes/footer.php'; ?>
