<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('admin');
$pdo=db();
$queries=['Total users'=>'SELECT COUNT(*) FROM users','Active users'=>'SELECT COUNT(*) FROM users WHERE user_status=\'Active\'','Textile batches'=>'SELECT COUNT(*) FROM textile_batch','Orders'=>'SELECT COUNT(*) FROM orders'];
$stats=[];foreach($queries as $label=>$sql){$stats[$label]=$pdo->query($sql)->fetchColumn();}
$pageTitle='Admin dashboard';require __DIR__ . '/../includes/header.php';
?>
<main class="container"><div class="page-head"><div><div class="eyebrow">Viva control centre</div><h1>Administrator dashboard</h1><p>Live counts prove that PHP is querying jhutledger_db.</p></div><span class="badge-soft">Admin overlay · <?=e(formatRole(currentUser()['base_role']))?></span></div><div class="stats-grid"><?php foreach($stats as $label=>$value):?><div class="stat-card"><span><?=e($label)?></span><strong><?=e($value)?></strong></div><?php endforeach;?></div><div class="row g-3 mt-2"><div class="col-md-6"><section class="panel h-100 mt-0"><h2 class="h4">User management</h2><p>Search accounts and demonstrate controlled status updates.</p><a class="btn btn-primary" href="<?=e(url('admin/users.php'))?>">Manage users</a></section></div><div class="col-md-6"><section class="panel h-100 mt-0"><h2 class="h4">Database proof</h2><p>Inspect connectivity, server version, tables, and subtype counts.</p><a class="btn btn-outline-primary" href="<?=e(url('admin/database-status.php'))?>">Open DB status</a></section></div></div></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>

