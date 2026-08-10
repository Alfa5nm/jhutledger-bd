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
<main class="container"><div class="page-head"><div><div class="eyebrow">Wholesale buyer workspace</div><h1>Welcome, <?= e(currentUser()['name']) ?></h1><p>Track wholesale listings, quotations, and orders.</p></div><span class="badge-soft">B2B Buyer</span></div><div class="stats-grid"><?php foreach($stats as $label=>$value):?><div class="stat-card"><span><?=e($label)?></span><strong><?=e($value)?></strong></div><?php endforeach;?></div><section class="panel"><h2 class="h4">Wholesale sourcing</h2><p>Find an available lot, propose a unit price, and follow the supplier response.</p><div class="action-row"><a class="btn btn-primary" href="<?=e(url('marketplace.php'))?>">Browse marketplace</a><a class="btn btn-outline-primary" href="<?=e(url('b2b/quotations.php'))?>">My quotations</a></div></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
