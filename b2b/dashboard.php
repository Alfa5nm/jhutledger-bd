<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('b2b');
$pdo = db(); $userId = currentUser()['user_id'];
$queries = [
    'Active B2B listings' => ['SELECT COUNT(*) FROM b2b_listing bl JOIN listing l ON l.listing_id = bl.listing_id WHERE l.status = \'Active\'', []],
    'My quotations' => ['SELECT COUNT(*) FROM quotation WHERE buyer_id = ?', [$userId]],
    'Accepted quotations' => ['SELECT COUNT(*) FROM quotation WHERE buyer_id = ? AND status = \'Accepted\'', [$userId]],
    'My orders' => ['SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND order_type = \'B2B\'', [$userId]],
];
$stats=[]; foreach($queries as $label=>[$sql,$params]){$s=$pdo->prepare($sql);$s->execute($params);$stats[$label]=$s->fetchColumn();}
$pageTitle='B2B buyer dashboard'; require __DIR__ . '/../includes/header.php';
?>
<main class="container"><div class="page-head"><div><div class="eyebrow">Wholesale buyer workspace</div><h1>Welcome, <?= e(currentUser()['name']) ?></h1><p>Your B2B specialization was resolved from the database.</p></div><span class="badge-soft">B2B Buyer</span></div><div class="stats-grid"><?php foreach($stats as $label=>$value):?><div class="stat-card"><span><?=e($label)?></span><strong><?=e($value)?></strong></div><?php endforeach;?></div><section class="panel"><h2 class="h4">Next phase</h2><p class="mb-0">Quotation creation and conversion to orders will build on the seeded workflow already represented in the schema.</p></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>

