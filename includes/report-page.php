<?php

declare(strict_types=1);

if (!isset($reportTitle, $reportEyebrow, $reportAudience)
    || !array_key_exists('supplierId', get_defined_vars())) {
    throw new LogicException('Report page configuration is missing.');
}

$pdo = db();
$filters = reportFilters($_GET);
$report = salesReport($pdo, $supplierId, $filters);

if (($_GET['export'] ?? '') === 'csv') {
    $filename = 'jhutledger-' . $reportAudience . '-sales-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Order ID', 'Date', 'Type', 'Buyer', 'Material', 'Quantity', 'Unit price', 'Gross revenue', 'Gross profit', 'Returned', 'Net revenue', 'Net profit', 'Payment']);
    foreach ($report['orders'] as $row) {
        $safeBuyer = preg_match('/^[=+\-@]/', $row['buyer_name']) ? "'" . $row['buyer_name'] : $row['buyer_name'];
        $safeMaterial = preg_match('/^[=+\-@]/', $row['material_type']) ? "'" . $row['material_type'] : $row['material_type'];
        fputcsv($output, [
            $row['order_id'], $row['order_date'], $row['order_type'], $safeBuyer, $safeMaterial,
            $row['quantity'], $row['selling_price'], $row['revenue'], $row['gross_profit'], $row['has_return'] ? 'Yes' : 'No', $row['net_revenue'], $row['net_profit'], $row['payment_status'],
        ]);
    }
    fclose($output);
    exit;
}

$exportQuery = http_build_query(array_filter($filters) + ['export' => 'csv']);
$pageTitle = $reportTitle;
require __DIR__ . '/header.php';
?>
<main class="container">
    <div class="page-head">
        <div><div class="eyebrow"><?= e($reportEyebrow) ?></div><h1><?= e($reportTitle) ?></h1><p>Completed-order revenue and profit calculated from historical order-item snapshots.</p></div>
        <a class="btn btn-outline-primary" href="?<?= e($exportQuery) ?>">Download CSV</a>
    </div>

    <section class="filter-bar">
        <form method="get" class="filter-grid report-filters">
            <div><label class="form-label" for="date_from">From</label><input class="form-control" type="date" id="date_from" name="date_from" value="<?= e($filters['date_from']) ?>"></div>
            <div><label class="form-label" for="date_to">To</label><input class="form-control" type="date" id="date_to" name="date_to" value="<?= e($filters['date_to']) ?>"></div>
            <div><label class="form-label" for="order_type">Order type</label><select class="form-select" id="order_type" name="order_type"><option value="">B2B and B2C</option><option value="B2B" <?= $filters['order_type'] === 'B2B' ? 'selected' : '' ?>>B2B</option><option value="B2C" <?= $filters['order_type'] === 'B2C' ? 'selected' : '' ?>>B2C</option></select></div>
            <div class="filter-action"><button class="btn btn-primary">Apply filters</button><a href="?">Clear</a></div>
        </form>
    </section>

    <div class="stats-grid">
        <div class="stat-card"><span>Completed orders</span><strong><?= e($report['summary']['order_count']) ?></strong></div>
        <div class="stat-card"><span>Quantity sold</span><strong><?= e(number_format((float) $report['summary']['quantity_sold'], 2)) ?></strong></div>
        <div class="stat-card"><span>Revenue</span><strong><?= e(money($report['summary']['revenue'])) ?></strong></div>
        <div class="stat-card"><span>Gross profit</span><strong><?= e(money($report['summary']['gross_profit'])) ?></strong></div>
        <div class="stat-card"><span>Returned revenue</span><strong><?= e(money($report['summary']['returned_revenue'])) ?></strong></div>
        <div class="stat-card"><span>Net revenue</span><strong><?= e(money($report['summary']['net_revenue'])) ?></strong></div>
        <div class="stat-card"><span>Net profit</span><strong><?= e(money($report['summary']['net_profit'])) ?></strong></div>
    </div>

    <section class="panel">
        <div class="section-heading"><div><h2 class="h4">Payment summary</h2><p class="muted mb-0">Payment states attached to the completed orders in this report.</p></div></div>
        <div class="summary-strip">
            <?php foreach ($report['payments'] as $payment): ?><div><span class="<?= e(statusClass($payment['payment_status'])) ?>"><?= e($payment['payment_status']) ?></span><strong><?= e($payment['order_count']) ?> order<?= (int) $payment['order_count'] === 1 ? '' : 's' ?></strong></div><?php endforeach; ?>
            <?php if (!$report['payments']): ?><p class="muted mb-0">No completed orders matched the filters.</p><?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <h2 class="h4">Material performance</h2>
        <div class="table-wrap"><table class="table align-middle"><thead><tr><th>Material</th><th>Orders</th><th>Quantity</th><th>Revenue</th><th>Gross profit</th></tr></thead><tbody>
        <?php foreach ($report['materials'] as $row): ?><tr><td><strong><?= e($row['material_type']) ?></strong></td><td><?= e($row['order_count']) ?></td><td><?= e(number_format((float) $row['quantity_sold'], 2)) ?></td><td><?= e(money($row['revenue'])) ?></td><td><?= e(money($row['gross_profit'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$report['materials']): ?><tr><td colspan="5" class="text-center muted py-4">No completed sales matched the filters.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

    <section class="panel">
        <h2 class="h4">Completed order detail</h2>
        <div class="table-wrap"><table class="table align-middle"><thead><tr><th>Order</th><th>Buyer</th><th>Material</th><th>Quantity</th><th>Revenue</th><th>Profit</th><th>Payment</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($report['orders'] as $row): ?><tr><td><a href="<?=e(url('order.php?id='.$row['order_id']))?>">#<?= e($row['order_id']) ?></a><br><small class="muted"><?= e($row['order_type']) ?> · <?= e(date('d M Y', strtotime($row['order_date']))) ?></small></td><td><?= e($row['buyer_name']) ?></td><td><?= e($row['material_type']) ?></td><td><?= e($row['quantity']) ?></td><td><?= e(money($row['revenue'])) ?></td><td><?= e(money($row['gross_profit'])) ?></td><td><span class="<?= e(statusClass($row['payment_status'])) ?>"><?= e($row['payment_status']) ?></span></td><td><a href="<?=e(url('invoice.php?id='.$row['order_id']))?>">Print invoice</a></td></tr><?php endforeach; ?>
        <?php if (!$report['orders']): ?><tr><td colspan="7" class="text-center muted py-4">No completed orders matched the filters.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</main>
<?php require __DIR__ . '/footer.php'; ?>
