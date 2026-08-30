<?php
declare(strict_types=1);
if (!isset($sustainabilityTitle, $sustainabilityAudience) || !array_key_exists('supplierId', get_defined_vars())) {
    throw new LogicException('Sustainability page configuration is missing.');
}
$filters = sustainabilityFilters($_GET);
$report = sustainabilityReport(db(), $supplierId, $filters);
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="jhutledger-' . $sustainabilityAudience . '-recirculation-' . date('Ymd-His') . '.csv"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Unit', 'Recirculated quantity', 'Returned quantity', 'Net retained quantity', 'Recovered material value (BDT)', 'Retention %', 'Orders']);
    foreach ($report['units'] as $row) {
        fputcsv($output, [$row['unit_of_measure'], $row['recirculated_quantity'], $row['returned_quantity'], $row['net_quantity'], $row['recovered_value'], $row['utilization_percentage'], $row['order_count']]);
    }
    fputcsv($output, []);
    fputcsv($output, ['Material', 'Unit', 'Recirculated', 'Returned', 'Net retained', 'Recovered value']);
    foreach ($report['materials'] as $row) {
        $label = preg_match('/^[=+\-@]/', $row['label']) ? "'" . $row['label'] : $row['label'];
        fputcsv($output, [$label, $row['unit_of_measure'], $row['recirculated_quantity'], $row['returned_quantity'], $row['net_quantity'], $row['recovered_value']]);
    }
    fclose($output);
    exit;
}
$exportQuery = http_build_query(array_filter($filters) + ['export' => 'csv']);
$pageTitle = $sustainabilityTitle;
require __DIR__ . '/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Textile recovery</div>
            <h1><?= e($sustainabilityTitle) ?></h1>
            <p>
                Measured textile recirculation from completed sales and full returns. No unsupported carbon estimates
                are used.
            </p>
        </div>
        <a class="btn btn-outline-primary" href="?<?= e($exportQuery) ?>">Download visible data as CSV</a>
    </div>
    <section class="filter-bar">
        <form method="get" class="filter-grid">
            <div>
                <label class="form-label">From</label>
                <input class="form-control" type="date" name="date_from" value="<?= e($filters['date_from']) ?>" />
            </div>
            <div>
                <label class="form-label">To</label>
                <input class="form-control" type="date" name="date_to" value="<?= e($filters['date_to']) ?>" />
            </div>
            <div>
                <label class="form-label">Channel</label>
                <select class="form-select" name="channel">
                    <option value="">Both channels</option>
                    <option <?= $filters['channel'] === 'B2B' ? 'selected' : '' ?>>B2B</option>
                    <option <?= $filters['channel'] === 'B2C' ? 'selected' : '' ?>>B2C</option>
                </select>
            </div>
            <div>
                <label class="form-label">Condition</label>
                <select class="form-select" name="condition">
                    <option value="">All conditions</option>
                    <?php foreach (['New', 'Surplus', 'Dead Stock', 'Recycled'] as $value): ?>
                    <option <?= $filters['condition'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Material</label>
                <input class="form-control" name="material" value="<?= e($filters['material']) ?>" placeholder="e.g. Denim" />
            </div>
            <div>
                <label class="form-label">Unit</label>
                <select class="form-select" name="unit">
                    <option value="">All, kept separate</option>
                    <?php foreach (['kg', 'metre', 'piece'] as $value): ?>
                    <option <?= $filters['unit'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-action">
                <button class="btn btn-primary">Apply filters</button>
                <a href="?">Clear</a>
            </div>
        </form>
    </section>
    <section class="panel">
        <h2 class="h4">Recirculation by unit</h2>
        <p class="muted">
            Kilograms, metres, and pieces are intentionally never combined into one total. Retention is net quantity ÷
            recirculated quantity.
        </p>
        <div class="table-wrap">
            <table class="table responsive-table">
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Recirculated</th>
                        <th>Returned</th>
                        <th>Net retained</th>
                        <th>Recovered value</th>
                        <th>Retention</th>
                        <th>Orders</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['units'] as $row): ?>
                    <tr>
                        <td data-label="Unit">
                            <strong> <?= e($row['unit_of_measure']) ?> </strong>
                        </td>
                        <td data-label="Recirculated"><?= e(number_format((float) $row['recirculated_quantity'], 2)) ?></td>
                        <td data-label="Returned"><?= e(number_format((float) $row['returned_quantity'], 2)) ?></td>
                        <td data-label="Net retained"><?= e(number_format((float) $row['net_quantity'], 2)) ?></td>
                        <td data-label="Recovered value"><?= e(money($row['recovered_value'])) ?></td>
                        <td data-label="Retention"><?= e($row['utilization_percentage']) ?>%</td>
                        <td data-label="Orders"><?= e($row['order_count']) ?></td>
                    </tr>
                    <?php endforeach; ?>
<?php if (!$report['units']): ?>
                    <tr>
                        <td colspan="7" class="text-center muted py-4">No completed sales match these filters.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php foreach (['conditions' => 'Condition performance', 'materials' => 'Material recovery', 'channels' => 'B2B / B2C contribution'] as $key => $heading): ?>
    <section class="panel">
        <h2 class="h4"><?= e($heading) ?></h2>
        <div class="table-wrap">
            <table class="table responsive-table">
                <thead>
                    <tr>
                        <th><?= $key === 'channels' ? 'Channel' : ($key === 'conditions' ? 'Condition' : 'Material') ?></th>
                        <th>Unit</th>
                        <th>Recirculated</th>
                        <th>Returned</th>
                        <th>Net retained</th>
                        <th>Recovered value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report[$key] as $row): ?>
                    <tr>
                        <td data-label="Group">
                            <strong> <?= e($row['label']) ?> </strong>
                        </td>
                        <td data-label="Unit"><?= e($row['unit_of_measure']) ?></td>
                        <td data-label="Recirculated"><?= e(number_format((float) $row['recirculated_quantity'], 2)) ?></td>
                        <td data-label="Returned"><?= e(number_format((float) $row['returned_quantity'], 2)) ?></td>
                        <td data-label="Net retained"><?= e(number_format((float) $row['net_quantity'], 2)) ?></td>
                        <td data-label="Recovered value"><?= e(money($row['recovered_value'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
<?php if (!$report[$key]): ?>
                    <tr>
                        <td colspan="6" class="text-center muted py-4">No matching data.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endforeach; ?>
</main>
<?php require __DIR__ . '/footer.php'; ?>
