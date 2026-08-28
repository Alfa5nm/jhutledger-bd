<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('supplier');
$pdo = db();
$userId = currentUser()['user_id'];

$queries = [
    'Batches' => ['SELECT COUNT(*) FROM textile_batch WHERE supplier_id = ?', [$userId]],
    'Available quantity' => [
        "SELECT COALESCE(SUM(available_quantity), 0)
         FROM textile_batch
         WHERE supplier_id = ? AND status = 'Active'",
        [$userId],
    ],
    'Active listings' => [
        "SELECT COUNT(*)
         FROM listing AS l
         JOIN textile_batch AS b ON b.batch_id = l.batch_id
         WHERE b.supplier_id = ? AND l.status = 'Active'",
        [$userId],
    ],
    'Open orders' => [
        "SELECT COUNT(DISTINCT o.order_id)
         FROM orders AS o
         JOIN order_item AS oi ON oi.order_id = o.order_id
         JOIN listing AS l ON l.listing_id = oi.listing_id
         JOIN textile_batch AS b ON b.batch_id = l.batch_id
         WHERE b.supplier_id = ?
           AND o.order_status IN ('Pending', 'Confirmed', 'Processing')",
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
     FROM quotation AS q
     JOIN listing AS l ON l.listing_id = q.listing_id
     JOIN textile_batch AS b ON b.batch_id = l.batch_id
     WHERE b.supplier_id = ? AND q.status IN ('Pending', 'Countered')"
);
$statement->execute([$userId]);
$pendingQuotations = (int) $statement->fetchColumn();
$statement = $pdo->prepare(
    "SELECT COUNT(*)
     FROM textile_batch
     WHERE supplier_id = ?
       AND status = 'Active'
       AND total_quantity > 0
       AND available_quantity <= total_quantity * 0.20"
);
$statement->execute([$userId]);
$lowStock = (int) $statement->fetchColumn();
$pageTitle = 'Supplier dashboard';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Supplier workspace</div>
            <h1>Assalamu alaikum, <?= e(currentUser()['name']) ?></h1>
            <p>Monitor your textile inventory and marketplace activity.</p>
        </div>
        <span class="badge-soft">Supplier</span>
    </div>
    <div class="stats-grid">
        <?php foreach ($stats as $label => $value): ?>
        <div class="stat-card">
            <span> <?= e($label) ?> </span>
            <strong data-count> <?= e($value) ?> </strong>
        </div>
        <?php endforeach; ?>
    </div>
    <section class="panel attention-panel">
        <div>
            <div class="eyebrow">Needs attention</div>
            <h2 class="h4">Your next tasks</h2>
        </div>
        <div class="attention-actions">
            <a href="<?=e(url('supplier/orders.php'))?>">
                <strong> <?=e($stats['Open orders'])?> </strong>
                <span>open orders</span>
            </a>
            <a href="<?=e(url('supplier/quotations.php'))?>">
                <strong> <?=e($pendingQuotations)?> </strong>
                <span>quotation replies</span>
            </a>
            <a href="<?=e(url('supplier/stock-ledger.php'))?>">
                <strong> <?=e($lowStock)?> </strong>
                <span>low-stock batches</span>
            </a>
        </div>
    </section>
    <section class="panel">
        <h2 class="h4">Inventory and fulfilment</h2>
        <p>Record stock, publish listings, negotiate wholesale offers, and complete customer orders.</p>
        <div class="role-tool-grid">
            <a href="<?=e(url('supplier/batches.php'))?>">Batches <span>Record and adjust stock</span> </a>
            <a href="<?=e(url('supplier/listings.php'))?>">Listings <span>Publish available material</span> </a>
            <a href="<?=e(url('supplier/stock-ledger.php'))?>">Stock ledger <span>Trace every movement</span> </a>
            <a href="<?=e(url('supplier/reports.php'))?>">Reports <span>Review sales and profit</span> </a>
            <a href="<?=e(url('supplier/pricing-assistant.php'))?>">Pricing assistant <span>Simulate price and margin</span> </a>
            <a href="<?=e(url('supplier/sustainability.php'))?>">Recirculation <span>Measure textile recovery</span> </a>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
