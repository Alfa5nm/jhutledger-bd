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
<main class="container"><div class="page-head"><div><div class="eyebrow">Retail buyer workspace</div><h1>Welcome, <?=e(currentUser()['name'])?></h1><p>Your B2C specialization was resolved from the database.</p></div><span class="badge-soft">B2C Buyer</span></div><div class="stats-grid"><?php foreach($stats as $label=>$value):?><div class="stat-card"><span><?=e($label)?></span><strong><?=e($value)?></strong></div><?php endforeach;?></div><section class="panel"><h2 class="h4">Next phase</h2><p class="mb-0">Direct ordering will use the same textile batch inventory shared with B2B listings.</p></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>

