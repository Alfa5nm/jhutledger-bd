<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('supplier');

$pdo = db();
$supplierId = (int) currentUser()['user_id'];
$batchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT) ?: 0;
$type = trim((string) ($_GET['type'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$types = ['STOCK_ADDED', 'RESERVED', 'RESERVATION_RELEASED', 'SOLD', 'RETURNED', 'ADJUSTMENT_IN', 'ADJUSTMENT_OUT'];
if (!in_array($type, $types, true)) $type = '';
if (!validDate($dateFrom)) $dateFrom = '';
if (!validDate($dateTo)) $dateTo = '';

$statement = $pdo->prepare('SELECT batch_id,material_type,color,available_quantity,total_quantity,unit_of_measure,status FROM textile_batch WHERE supplier_id=? ORDER BY entry_date DESC,batch_id DESC');
$statement->execute([$supplierId]);
$batches = $statement->fetchAll();

$where = ['b.supplier_id=?'];
$params = [$supplierId];
if ($batchId) { $where[] = 'st.batch_id=?'; $params[] = $batchId; }
if ($type !== '') { $where[] = 'st.transaction_type=?'; $params[] = $type; }
if ($dateFrom !== '') { $where[] = 'DATE(st.transaction_date)>=?'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where[] = 'DATE(st.transaction_date)<=?'; $params[] = $dateTo; }
$sql = "SELECT st.*,b.material_type,b.color,b.available_quantity,b.total_quantity,b.unit_of_measure,o.order_type,o.order_status
        FROM stock_transaction st JOIN textile_batch b ON b.batch_id=st.batch_id
        LEFT JOIN orders o ON o.order_id=st.order_id WHERE " . implode(' AND ', $where) . '
        ORDER BY st.transaction_date DESC,st.transaction_id DESC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$transactions = $statement->fetchAll();

$pageTitle = 'Stock ledger';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="page-head"><div><div class="eyebrow">Supplier / Inventory</div><h1>Stock ledger</h1><p>Trace every addition, reservation, release, sale, return, and adjustment.</p></div><a class="btn btn-outline-primary" href="<?= e(url('supplier/batches.php')) ?>">Manage batches</a></div>
    <section class="filter-bar"><form method="get" class="filter-grid ledger-filters">
        <div><label class="form-label" for="batch_id">Batch</label><select class="form-select" id="batch_id" name="batch_id"><option value="">All batches</option><?php foreach ($batches as $batch): ?><option value="<?= e($batch['batch_id']) ?>" <?= $batchId === (int) $batch['batch_id'] ? 'selected' : '' ?>>#<?= e($batch['batch_id']) ?> · <?= e($batch['material_type']) ?> · <?= e($batch['color']) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label" for="type">Movement</label><select class="form-select" id="type" name="type"><option value="">All movements</option><?php foreach ($types as $item): ?><option <?= $type === $item ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $item)) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label" for="date_from">From</label><input class="form-control" type="date" id="date_from" name="date_from" value="<?= e($dateFrom) ?>"></div>
        <div><label class="form-label" for="date_to">To</label><input class="form-control" type="date" id="date_to" name="date_to" value="<?= e($dateTo) ?>"></div>
        <div class="filter-action"><button class="btn btn-primary">Apply</button><a href="<?= e(url('supplier/stock-ledger.php')) ?>">Clear</a></div>
    </form></section>

    <section class="panel mt-0"><div class="section-heading"><div><h2 class="h4">Movement history</h2><p class="muted mb-0"><?= e(count($transactions)) ?> matching ledger records</p></div></div>
        <div class="table-wrap"><table class="table align-middle responsive-table"><thead><tr><th>Date</th><th>Batch</th><th>Movement</th><th>Quantity</th><th>Order</th><th>Remarks</th></tr></thead><tbody>
        <?php foreach ($transactions as $row): $movement = stockMovement($row['transaction_type'], (float) $row['quantity']); ?>
            <tr><td data-label="Date"><?= e(date('d M Y, H:i', strtotime($row['transaction_date']))) ?></td><td data-label="Batch"><strong>#<?= e($row['batch_id']) ?></strong><br><small class="muted"><?= e($row['material_type']) ?> · <?= e($row['color']) ?></small></td><td data-label="Movement"><span class="movement <?= e($movement['class']) ?>"><?= e(str_replace('_', ' ', $row['transaction_type'])) ?></span><br><small class="muted"><?= e($movement['effect']) ?></small></td><td data-label="Quantity"><strong class="<?= e($movement['class']) ?>"><?= e($movement['symbol']) ?><?= e(number_format($movement['quantity'], 2)) ?></strong> <?= e($row['unit_of_measure']) ?><br><small class="muted"><?= e(number_format((float) $row['available_quantity'], 2)) ?> currently available</small></td><td data-label="Order"><?php if ($row['order_id']): ?><a href="<?= e(url('order.php?id=' . $row['order_id'])) ?>">#<?= e($row['order_id']) ?></a><br><small class="muted"><?= e($row['order_type']) ?> · <?= e($row['order_status']) ?></small><?php else: ?><span class="muted">Inventory</span><?php endif; ?></td><td data-label="Remarks"><?= e($row['remarks'] ?: '—') ?></td></tr>
        <?php endforeach; ?><?php if (!$transactions): ?><tr><td colspan="6" class="text-center muted py-4">No stock movements matched these filters.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
