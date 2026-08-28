<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('supplier');
$pdo = db();
$supplierId = (int) currentUser()['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = input('action');
    $batchId = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT) ?: null;

    if ($action === 'archive' && $batchId) {
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT batch_id FROM textile_batch WHERE batch_id = ? AND supplier_id = ? FOR UPDATE');
            $statement->execute([$batchId, $supplierId]);
            if (!$statement->fetchColumn()) {
                throw new RuntimeException('Batch not found.');
            }
            $pdo->prepare("UPDATE textile_batch SET status = 'Inactive' WHERE batch_id = ?")->execute([$batchId]);
            $pdo->prepare("UPDATE listing SET status = 'Inactive' WHERE batch_id = ? AND status = 'Active'")->execute([$batchId]);
            $pdo->commit();
            setFlash('success', 'Batch and its active listings were archived.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('danger', $exception->getMessage());
        }
        redirect('supplier/batches.php');
    }

    $values = [
        'material_type' => input('material_type'), 'composition' => input('composition'),
        'color' => input('color'), 'gsm' => input('gsm'), 'condition' => input('condition'),
        'total_quantity' => input('total_quantity'), 'average_cost' => input('average_cost'),
        'storage_location' => input('storage_location'), 'entry_date' => input('entry_date'),
        'unit_of_measure' => input('unit_of_measure'), 'status' => input('status'),
    ];
    foreach (['material_type', 'composition', 'color', 'storage_location', 'unit_of_measure'] as $field) {
        if ($values[$field] === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    if (!is_numeric($values['gsm']) || (float) $values['gsm'] <= 0) {
        $errors[] = 'GSM must be greater than zero.';
    }
    if (!is_numeric($values['total_quantity']) || (float) $values['total_quantity'] <= 0) {
        $errors[] = 'Total quantity must be greater than zero.';
    }
    if (!is_numeric($values['average_cost']) || (float) $values['average_cost'] < 0) {
        $errors[] = 'Average cost cannot be negative.';
    }
    if (!in_array($values['condition'], ['New', 'Surplus', 'Dead Stock', 'Recycled'], true)) {
        $errors[] = 'Select a valid condition.';
    }
    if (!in_array($values['status'], ['Active', 'Inactive'], true)) {
        $errors[] = 'Select a valid status.';
    }
    if (!validDate($values['entry_date'])) {
        $errors[] = 'Enter a valid entry date.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if ($batchId) {
                $statement = $pdo->prepare('SELECT * FROM textile_batch WHERE batch_id = ? AND supplier_id = ? FOR UPDATE');
                $statement->execute([$batchId, $supplierId]);
                $old = $statement->fetch();
                if (!$old) {
                    throw new RuntimeException('Batch not found.');
                }
                $used = (float) $old['total_quantity'] - (float) $old['available_quantity'];
                $newTotal = (float) $values['total_quantity'];
                if ($newTotal < $used) {
                    throw new RuntimeException("Total quantity cannot be below {$used}; that amount is already reserved.");
                }
                $newAvailable = $newTotal - $used;
                $allocatedStatement = $pdo->prepare("SELECT COALESCE(SUM(listed_quantity),0) FROM listing WHERE batch_id=? AND status='Active'");
                $allocatedStatement->execute([$batchId]);
                $allocated = (float) $allocatedStatement->fetchColumn();
                if ($newAvailable < $allocated) {
                    throw new RuntimeException("Available quantity cannot be below {$allocated}; that amount is allocated to active listings.");
                }
                $delta = $newTotal - (float) $old['total_quantity'];
                $pdo->prepare(
                    'UPDATE textile_batch SET material_type=?, composition=?, color=?, gsm=?, `condition`=?, total_quantity=?,
                     available_quantity=?, average_cost=?, storage_location=?, entry_date=?, unit_of_measure=?, status=?
                     WHERE batch_id=?'
                )->execute([
                    $values['material_type'], $values['composition'], $values['color'], $values['gsm'], $values['condition'],
                    $newTotal, $newAvailable, $values['average_cost'], $values['storage_location'], $values['entry_date'],
                    $values['unit_of_measure'], $values['status'], $batchId,
                ]);
                if (abs($delta) > 0.0001) {
                    $type = $delta > 0 ? 'ADJUSTMENT_IN' : 'ADJUSTMENT_OUT';
                    $pdo->prepare('INSERT INTO stock_transaction (batch_id, quantity, transaction_type, remarks) VALUES (?, ?, ?, ?)')
                        ->execute([$batchId, abs($delta), $type, 'Batch total adjusted by supplier']);
                }
                if ($values['status'] === 'Inactive') {
                    $pdo->prepare("UPDATE listing SET status='Inactive' WHERE batch_id=? AND status='Active'")->execute([$batchId]);
                }
                setFlash('success', 'Batch updated successfully.');
            } else {
                $pdo->prepare(
                    'INSERT INTO textile_batch (supplier_id,material_type,composition,color,gsm,`condition`,total_quantity,
                     available_quantity,average_cost,storage_location,entry_date,unit_of_measure,status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $supplierId, $values['material_type'], $values['composition'], $values['color'], $values['gsm'],
                    $values['condition'], $values['total_quantity'], $values['total_quantity'], $values['average_cost'],
                    $values['storage_location'], $values['entry_date'], $values['unit_of_measure'], $values['status'],
                ]);
                $batchId = (int) $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO stock_transaction (batch_id, quantity, transaction_type, remarks) VALUES (?, ?, 'STOCK_ADDED', 'Opening quantity')")
                    ->execute([$batchId, $values['total_quantity']]);
                setFlash('success', 'Textile batch created successfully.');
            }
            $pdo->commit();
            redirect('supplier/batches.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
        }
    }
}

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
$pageTitle = 'Textile batches';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Supplier inventory</div>
            <h1>Textile batches</h1>
            <p>Record stock at its source and keep available quantities traceable.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?=e(url('supplier/listings.php'))?>">Manage listings</a>
    </div>
    <?php if ($errors):?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error):?>
            <li><?=e($error)?></li>
            <?php endforeach;?>
        </ul>
    </div>
    <?php endif;?>
    <section class="panel">
        <h2 class="h4"><?=$edit ? 'Edit batch #' . e($edit['batch_id']) : 'Add a textile batch'?></h2>
        <form method="post">
            <?=csrfField()?>
            <input type="hidden" name="action" value="save" />
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
                    <a class="btn btn-outline-secondary" href="<?=e(url('supplier/batches.php'))?>">Cancel</a>
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
                                <form method="post" onsubmit="return confirm('Archive this batch and its listings?');">
                                    <?=csrfField()?>
                                    <input type="hidden" name="action" value="archive" />
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
<?php require __DIR__ . '/../includes/footer.php'; ?>
