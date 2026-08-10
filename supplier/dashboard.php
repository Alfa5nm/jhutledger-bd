<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('supplier');
$pdo = db();
$userId = currentUser()['user_id'];

$queries = [
    'Batches' => ['SELECT COUNT(*) FROM textile_batch WHERE supplier_id = ?', [$userId]],
    'Available quantity' => ['SELECT COALESCE(SUM(available_quantity), 0) FROM textile_batch WHERE supplier_id = ? AND status = \'Active\'', [$userId]],
    'Active listings' => ['SELECT COUNT(*) FROM listing l JOIN textile_batch b ON b.batch_id = l.batch_id WHERE b.supplier_id = ? AND l.status = \'Active\'', [$userId]],
    'Pending quotations' => ['SELECT COUNT(*) FROM quotation q JOIN listing l ON l.listing_id = q.listing_id JOIN textile_batch b ON b.batch_id = l.batch_id WHERE b.supplier_id = ? AND q.status IN (\'Pending\', \'Countered\')', [$userId]],
];
$stats = [];
foreach ($queries as $label => [$sql, $params]) { $s = $pdo->prepare($sql); $s->execute($params); $stats[$label] = $s->fetchColumn(); }
$pageTitle = 'Supplier dashboard';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="page-head"><div><div class="eyebrow">Supplier workspace</div><h1>Assalamu alaikum, <?= e(currentUser()['name']) ?></h1><p>Your identity and counts below are loaded from the database.</p></div><span class="badge-soft">Supplier</span></div>
    <div class="stats-grid"><?php foreach ($stats as $label => $value): ?><div class="stat-card"><span><?= e($label) ?></span><strong><?= e($value) ?></strong></div><?php endforeach; ?></div>
    <section class="panel"><h2 class="h4">Milestone status</h2><p class="mb-0">Authentication and schema are active. Batch/listing workflow screens belong to the next project phase.</p></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>

