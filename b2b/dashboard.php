<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('b2b');
$pdo = db();
$userId = currentUser()['user_id'];
$queries = [
    'Active B2B listings' => ['SELECT COUNT(*) FROM b2b_listing bl JOIN listing l ON l.listing_id = bl.listing_id WHERE l.status = \'Active\'', []],
    'My quotations' => ['SELECT COUNT(*) FROM quotation WHERE buyer_id = ?', [$userId]],
    'Accepted quotations' => ['SELECT COUNT(*) FROM quotation WHERE buyer_id = ? AND status = \'Accepted\'', [$userId]],
    'My orders' => ['SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND order_type = \'B2B\'', [$userId]],
];
$stats = [];
foreach ($queries as $label => [$sql,$params]) {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $stats[$label] = $statement->fetchColumn();
}
$statement = $pdo->prepare("SELECT COUNT(*) FROM quotation WHERE buyer_id=? AND status='Countered'");
$statement->execute([$userId]);
$counterOffers = (int)$statement->fetchColumn();
$statement = $pdo->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN payment p ON p.order_id=o.order_id WHERE o.buyer_id=? AND o.order_status IN ('Confirmed','Processing') AND (p.payment_id IS NULL OR p.payment_status IN ('Failed','Pending'))");
$statement->execute([$userId]);
$paymentAttention = (int)$statement->fetchColumn();
$pageTitle = 'B2B buyer dashboard';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
<div class="page-head">
<div>
<div class="eyebrow">Wholesale buyer workspace</div>
<h1>Welcome, <?= e(currentUser()['name']) ?>
</h1>
<p>Track wholesale listings, quotations, orders, and payment status.</p>
</div>
<span class="badge-soft">B2B Buyer</span>
</div>
<div class="stats-grid">
<?php foreach ($stats as $label => $value):?>
<div class="stat-card">
<span>
<?=e($label)?>
</span>
<strong data-count>
<?=e($value)?>
</strong>
</div>
<?php endforeach;?>
</div>
<section class="panel attention-panel">
<div>
<div class="eyebrow">Needs attention</div>
<h2 class="h4">Continue your purchases</h2>
</div>
<div class="attention-actions">
<a href="<?=e(url('b2b/quotations.php'))?>">
<strong>
<?=e($counterOffers)?>
</strong>
<span>counter-offers</span>
</a>
<a href="<?=e(url('b2b/orders.php'))?>">
<strong>
<?=e($paymentAttention)?>
</strong>
<span>payments to review</span>
</a>
</div>
</section>
<section class="panel">
<h2 class="h4">Wholesale sourcing</h2>
<p>Find an available lot, propose a unit price, and follow the order through fulfilment.</p>
<div class="role-tool-grid">
<a href="<?=e(url('marketplace.php'))?>">Marketplace <span>Find wholesale lots</span>
</a>
<a href="<?=e(url('b2b/quotations.php'))?>">Quotations <span>Negotiate unit price</span>
</a>
<a href="<?=e(url('b2b/orders.php'))?>">Orders <span>Track and pay</span>
</a>
</div>
</section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
