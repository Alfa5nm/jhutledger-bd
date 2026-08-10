<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('b2c');
$pdo=db();$userId=currentUser()['user_id'];
$queries=[
    'Active B2C listings'=>['SELECT COUNT(*) FROM b2c_listing bl JOIN listing l ON l.listing_id=bl.listing_id WHERE l.status=\'Active\'',[]],
    'My orders'=>['SELECT COUNT(*) FROM orders WHERE buyer_id=? AND order_type=\'B2C\'',[$userId]],
    'Pending orders'=>['SELECT COUNT(*) FROM orders WHERE buyer_id=? AND order_status IN (\'Pending\',\'Confirmed\',\'Processing\')',[$userId]],
    'Paid orders'=>['SELECT COUNT(*) FROM payment p JOIN orders o ON o.order_id=p.order_id WHERE o.buyer_id=? AND p.payment_status=\'Paid\'',[$userId]],
];
$stats=[];foreach($queries as $label=>[$sql,$params]){$s=$pdo->prepare($sql);$s->execute($params);$stats[$label]=$s->fetchColumn();}
$pageTitle='B2C buyer dashboard';require __DIR__ . '/../includes/header.php';
?>
<main class="container"><div class="page-head"><div><div class="eyebrow">Retail buyer workspace</div><h1>Welcome, <?=e(currentUser()['name'])?></h1><p>Track retail listings, purchases, and payment activity.</p></div><span class="badge-soft">B2C Buyer</span></div><div class="stats-grid"><?php foreach($stats as $label=>$value):?><div class="stat-card"><span><?=e($label)?></span><strong><?=e($value)?></strong></div><?php endforeach;?></div><section class="panel"><h2 class="h4">Retail sourcing</h2><p>Browse bundle-sized listings and place an order against live stock.</p><div class="action-row"><a class="btn btn-primary" href="<?=e(url('marketplace.php'))?>">Browse marketplace</a><a class="btn btn-outline-primary" href="<?=e(url('b2c/orders.php'))?>">My orders</a></div></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
