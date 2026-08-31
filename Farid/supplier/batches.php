<?php
require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('supplier');
$pdo = db();
$supplierId = (int) currentUser()['user_id'];
$edit = null;
if (isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM textile_batch WHERE batch_id = ? AND supplier_id = ?');
    $statement->execute([(int) $_GET['edit'], $supplierId]);
    $edit = $statement->fetch() ?: null;
}
$statement = $pdo->prepare(
    'SELECT b.*, COUNT(l.listing_id) AS listing_count
     FROM textile_batch b LEFT JOIN listing l ON l.batch_id=b.batch_id
     WHERE b.supplier_id=? GROUP BY b.batch_id ORDER BY b.entry_date DESC, b.batch_id DESC'
);
$statement->execute([$supplierId]);
$batches = $statement->fetchAll();
$form = $edit ?: [
    'batch_id' => '',
    'material_type' => '',
    'composition' => '',
    'color' => '',
    'gsm' => '',
    'condition' => 'Surplus',
    'total_quantity' => '',
    'average_cost' => '',
    'storage_location' => '',
    'entry_date' => date('Y-m-d'),
    'unit_of_measure' => 'kg',
    'status' => 'Active',
];
$submittedValues = $_SESSION['batch_values'] ?? null;
unset($_SESSION['batch_values']);

if (is_array($submittedValues)) {
    $form = array_merge($form, $submittedValues);
}
$pageTitle = 'Textile batches';
require __DIR__ . '/../../Mixed/includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Supplier inventory</div>
            <h1>Textile batches</h1>
            <p>Record stock at its source and keep available quantities traceable.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?=e(url('Farid/supplier/listings.php'))?>">Manage listings</a>
    </div>
    <section class="panel">
        <h2 class="h4"><?=$edit ? 'Edit batch #' . e($edit['batch_id']) : 'Add a textile batch'?></h2>
        <form method="post" action="<?= e(url('Farid/supplier/actions/save-batch.php')) ?>">
            <?=csrfField()?>
            <input type="hidden" name="batch_id" value="<?=e($form['batch_id'])?>" />
            <div class="form-grid">
                <div>
                    <label class="form-label">Material type</label>
                    <input class="form-control" name="material_type" required value="<?=e($form['material_type'])?>" />
                </div>
                <div>
                    <label class="form-label">Composition</label>
                    <input class="form-control" name="composition" required value="<?=e($form['composition'])?>" />
                </div>
                <div>
                    <label class="form-label">Color</label>
                    <input class="form-control" name="color" required value="<?=e($form['color'])?>" />
                </div>
                <div>
                    <label class="form-label">GSM</label>
                    <input
                        class="form-control"
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="gsm"
                        required
                        value="<?=e($form['gsm'])?>"
                    />
                </div>
                <div>
                    <label class="form-label">Condition</label>
                    <select class="form-select" name="condition">
                        <?php foreach (['New', 'Surplus', 'Dead Stock', 'Recycled'] as $option):?>
                        <option <?=$form['condition'] === $option ? 'selected' : ''?>><?=e($option)?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Total quantity</label>
                    <input
                        class="form-control"
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="total_quantity"
                        required
                        value="<?=e($form['total_quantity'])?>"
                    />
                </div>
                <div>
                    <label class="form-label">Average cost per unit (BDT)</label>
                    <input
                        class="form-control"
                        type="number"
                        step="0.01"
                        min="0"
                        name="average_cost"
                        required
                        value="<?=e($form['average_cost'])?>"
                    />
                </div>
                <div>
                    <label class="form-label">Unit</label>
                    <input class="form-control" name="unit_of_measure" required value="<?=e($form['unit_of_measure'])?>" />
                </div>
                <div>
                    <label class="form-label">Storage location</label>
                    <input class="form-control" name="storage_location" required value="<?=e($form['storage_location'])?>" />
                </div>
                <div>
                    <label class="form-label">Entry date</label>
                    <input class="form-control" type="date" name="entry_date" required value="<?=e($form['entry_date'])?>" />
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option <?=$form['status'] === 'Active' ? 'selected' : ''?>>Active</option>
                        <option <?=$form['status'] === 'Inactive' ? 'selected' : ''?>>Inactive</option>
                    </select>
                </div>
                <div class="full d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><?=$edit ? 'Save batch' : 'Add batch'?></button>
                    <?php if ($edit):?>
                    <a class="btn btn-outline-secondary" href="<?=e(url('Farid/supplier/batches.php'))?>">Cancel</a>
                    <?php endif;?>
                </div>
            </div>
        </form>
    </section>
    <section class="panel">
        <h2 class="h4">Your inventory</h2>
        <div class="table-wrap">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Material</th>
                        <th>Available</th>
                        <th>Cost</th>
                        <th>Listings</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $batch):$batchImage = textileImage($batch['material_type'], $batch['composition']);?>
                    <tr>
                        <td>#<?=e($batch['batch_id'])?></td>
                        <td>
                            <div class="material-cell">
                                <img
                                    class="textile-thumb"
                                    src="<?=e($batchImage['src'])?>"
                                    alt="<?=e($batchImage['alt'])?>"
                                    width="120"
                                    height="80"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <span>
                                    <strong> <?=e($batch['material_type'])?> </strong>
                                    <small class="muted"> <?=e($batch['composition'])?> · <?=e($batch['color'])?> </small>
                                    <small class="representative-label">Representative image</small>
                                </span>
                            </div>
                        </td>
                        <td><?=e($batch['available_quantity'])?> / <?=e($batch['total_quantity'])?> <?=e($batch['unit_of_measure'])?></td>
                        <td><?=e(money($batch['average_cost']))?></td>
                        <td><?=e($batch['listing_count'])?></td>
                        <td>
                            <span class="<?=e(statusClass($batch['status']))?>"> <?=e($batch['status'])?> </span>
                        </td>
                        <td>
                            <div class="action-row">
                                <a class="btn btn-sm btn-outline-primary" href="?edit=<?=e($batch['batch_id'])?>">Edit</a>
                                <?php if ($batch['status'] === 'Active'):?>
                                <form method="post" action="<?= e(url('Farid/supplier/actions/archive-batch.php')) ?>" onsubmit="return confirm('Archive this batch and its listings?');">
                                    <?=csrfField()?>
                                    <input type="hidden" name="batch_id" value="<?=e($batch['batch_id'])?>" />
                                    <button class="btn btn-sm btn-outline-danger">Archive</button>
                                </form>
                                <?php endif;?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach;?>
<?php if (!$batches):?>
                    <tr>
                        <td colspan="7" class="text-center muted py-4">No batches yet. Add the first one above.</td>
                    </tr>
                    <?php endif;?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../../Mixed/includes/footer.php'; ?>
