<?php
require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('supplier');

$pdo = db();
$supplierId = (int) currentUser()['user_id'];
$statement = $pdo->prepare(
    "SELECT b.batch_id,b.material_type,b.color,b.average_cost,b.available_quantity,b.unit_of_measure,
            GROUP_CONCAT(DISTINCT bl.bulk_unit_price ORDER BY bl.bulk_unit_price SEPARATOR ', ') b2b_prices,
            GROUP_CONCAT(DISTINCT bc.fixed_unit_price ORDER BY bc.fixed_unit_price SEPARATOR ', ') b2c_prices
     FROM textile_batch b
     LEFT JOIN listing l ON l.batch_id=b.batch_id AND l.status='Active'
     LEFT JOIN b2b_listing bl ON bl.listing_id=l.listing_id
     LEFT JOIN b2c_listing bc ON bc.listing_id=l.listing_id
     WHERE b.supplier_id=? AND b.status='Active'
     GROUP BY b.batch_id,b.material_type,b.color,b.average_cost,b.available_quantity,b.unit_of_measure
     ORDER BY b.entry_date DESC"
);
$statement->execute([$supplierId]);
$batches = $statement->fetchAll();
$projection = null;
$error = '';
$form = [
    'batch_id' => (string) ($_POST['batch_id'] ?? ''), 'channel' => (string) ($_POST['channel'] ?? 'B2B'),
    'quantity' => (string) ($_POST['quantity'] ?? ''), 'target_margin' => (string) ($_POST['target_margin'] ?? '20'),
    'channel_quantity' => (string) ($_POST['channel_quantity'] ?? ''),
];
$batch = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $batchId = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
        $channel = input('channel');
        $quantity = (float) input('quantity');
        $margin = (float) input('target_margin');
        $channelQuantity = (float) input('channel_quantity');
        foreach ($batches as $candidate) {
            if ((int) $candidate['batch_id'] === $batchId) {
                $batch = $candidate;
                break;
            }
        }
        if (!$batch) {
            throw new RuntimeException('Select one of your active batches.');
        }
        if (!in_array($channel, ['B2B', 'B2C'], true)) {
            throw new RuntimeException('Select a valid sales channel.');
        }
        if ($quantity <= 0 || $quantity > (float) $batch['available_quantity']) {
            throw new RuntimeException('Listing quantity must be positive and within available stock.');
        }
        if ($channelQuantity <= 0 || $channelQuantity > $quantity) {
            throw new RuntimeException('Minimum or bundle quantity must fit within the listing quantity.');
        }
        $projection = pricingProjection((float) $batch['average_cost'], $quantity, $margin);
        $query = [
            'prefill' => '1', 'batch_id' => $batchId, 'listing_type' => $channel,
            'listed_quantity' => $quantity,
            $channel === 'B2B' ? 'minimum_quantity' : 'bundle_size' => $channelQuantity,
            $channel === 'B2B' ? 'bulk_unit_price' : 'fixed_unit_price' => $projection['suggested_price'],
        ];
        $projection['listing_url'] = url('Farid/supplier/listings.php?' . http_build_query($query));
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$pageTitle = 'Pricing and margin assistant';
require __DIR__ . '/../../Mixed/includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Supplier planning</div>
            <h1>Pricing &amp; margin assistant</h1>
            <p>Simulate a listing price from your real batch cost. Nothing is saved until you publish a listing.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= e(url('Farid/supplier/listings.php')) ?>">Marketplace listings</a>
    </div>
    <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <div class="detail-layout">
        <section class="panel mt-0">
            <h2 class="h4">Price simulation</h2>
            <form method="post" class="form-grid">
                <?= csrfField() ?>
                <div class="full">
                    <label class="form-label" for="batch_id">Your active batch</label>
                    <select class="form-select" name="batch_id" id="batch_id" required>
                        <option value="">Choose batch</option>
                        <?php foreach ($batches as $row): ?>
                        <option value="<?= e($row['batch_id']) ?>" <?= (int) $form['batch_id'] === (int) $row['batch_id'] ? 'selected' : '' ?>>
                            Batch #<?= e($row['batch_id']) ?> · <?= e($row['material_type']) ?> · cost <?= e(money($row['average_cost'])) ?> · <?= e($row['available_quantity']) ?> <?= e($row['unit_of_measure']) ?>
                            available
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="channel">Sales channel</label>
                    <select class="form-select" name="channel" id="channel">
                        <option value="B2B" <?= $form['channel'] === 'B2B' ? 'selected' : '' ?>>B2B Wholesale</option>
                        <option value="B2C" <?= $form['channel'] === 'B2C' ? 'selected' : '' ?>>B2C Retail</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="quantity">Quantity to list</label>
                    <input
                        class="form-control"
                        type="number"
                        min="0.01"
                        step="0.01"
                        name="quantity"
                        id="quantity"
                        value="<?= e($form['quantity']) ?>"
                        required
                    />
                </div>
                <div>
                    <label class="form-label" for="target_margin">Target gross margin (%)</label>
                    <input
                        class="form-control"
                        type="number"
                        min="0"
                        max="99.99"
                        step="0.01"
                        name="target_margin"
                        id="target_margin"
                        value="<?= e($form['target_margin']) ?>"
                        required
                    />
                </div>
                <div>
                    <label class="form-label" for="channel_quantity">Minimum order / bundle quantity</label>
                    <input
                        class="form-control"
                        type="number"
                        min="0.01"
                        step="0.01"
                        name="channel_quantity"
                        id="channel_quantity"
                        value="<?= e($form['channel_quantity']) ?>"
                        required
                    />
                </div>
                <div class="full">
                    <button class="btn btn-primary">Calculate suggestion</button>
                </div>
            </form>
        </section>
        <aside class="panel mt-0">
            <h2 class="h4">How it works</h2>
            <p>Suggested price = cost ÷ (1 − target margin). A 20% margin on a ৳100 cost gives ৳125, not ৳120.</p>
            <?php if ($batch): ?>
            <p>
                <strong>Current B2B prices:</strong>
                <?= e($batch['b2b_prices'] ?: 'None') ?>
                <br />
                <strong>Current B2C prices:</strong>
                <?= e($batch['b2c_prices'] ?: 'None') ?>
            </p>
            <?php endif; ?>
        </aside>
    </div>
    <?php if ($projection): ?>
    <section class="panel">
        <h2 class="h4">Suggested result</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <span>Break-even unit price</span>
                <strong> <?= e(money($projection['break_even_price'])) ?> </strong>
            </div>
            <div class="stat-card">
                <span>Suggested unit price</span>
                <strong> <?= e(money($projection['suggested_price'])) ?> </strong>
            </div>
            <div class="stat-card">
                <span>Projected revenue</span>
                <strong> <?= e(money($projection['projected_revenue'])) ?> </strong>
            </div>
            <div class="stat-card">
                <span>Projected cost</span>
                <strong> <?= e(money($projection['projected_cost'])) ?> </strong>
            </div>
            <div class="stat-card">
                <span>Projected gross profit</span>
                <strong> <?= e(money($projection['projected_profit'])) ?> </strong>
            </div>
            <div class="stat-card">
                <span>Actual margin</span>
                <strong> <?= e($projection['actual_margin']) ?>%</strong>
            </div>
        </div>
        <a class="btn btn-primary" href="<?= e($projection['listing_url']) ?>">Use this suggestion</a>
        <p class="form-note mt-2">This only prefills the normal listing form. Review and publish it there.</p>
    </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../../Mixed/includes/footer.php'; ?>
