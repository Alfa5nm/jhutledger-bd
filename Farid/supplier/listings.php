<?php
require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('supplier');
$pdo = db();
$supplierId = (int) currentUser()['user_id'];
$edit = null;
if (isset($_GET['edit'])) {
    $statement = $pdo->prepare(
        "SELECT l.*, IF(bl.listing_id IS NULL, 'B2C', 'B2B') AS listing_type,
                bl.minimum_quantity, bl.bulk_unit_price, bc.bundle_size, bc.fixed_unit_price
         FROM listing AS l
         JOIN textile_batch AS b ON b.batch_id = l.batch_id
         LEFT JOIN b2b_listing AS bl ON bl.listing_id = l.listing_id
         LEFT JOIN b2c_listing AS bc ON bc.listing_id = l.listing_id
         WHERE l.listing_id = ? AND b.supplier_id = ?"
    );
    $statement->execute([(int) $_GET['edit'], $supplierId]);
    $edit = $statement->fetch() ?: null;
}
$statement = $pdo->prepare(
    "SELECT b.batch_id, b.material_type, b.color, b.available_quantity, b.unit_of_measure,
            COALESCE(SUM(CASE WHEN l.status = 'Active' THEN l.listed_quantity ELSE 0 END), 0)
                AS allocated_quantity
     FROM textile_batch AS b
     LEFT JOIN listing AS l ON l.batch_id = b.batch_id
     WHERE b.supplier_id = ? AND b.status = 'Active'
     GROUP BY b.batch_id, b.material_type, b.color, b.available_quantity, b.unit_of_measure
     ORDER BY b.entry_date DESC"
);
$statement->execute([$supplierId]);
$batches = $statement->fetchAll();
$batchFilter = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT) ?: null;
$channelFilter = in_array(($_GET['channel'] ?? ''), ['B2B', 'B2C'], true) ? $_GET['channel'] : '';
$listingSql = "SELECT l.*, b.material_type, b.composition, b.color, b.unit_of_measure,
                      IF(bl.listing_id IS NULL, 'B2C', 'B2B') AS listing_type,
                      bl.minimum_quantity, bl.bulk_unit_price, bc.bundle_size, bc.fixed_unit_price
               FROM listing AS l
               JOIN textile_batch AS b ON b.batch_id = l.batch_id
               LEFT JOIN b2b_listing AS bl ON bl.listing_id = l.listing_id
               LEFT JOIN b2c_listing AS bc ON bc.listing_id = l.listing_id
               WHERE b.supplier_id = ?";
$listingParams = [$supplierId];
if ($batchFilter) {
    $listingSql .= ' AND l.batch_id=?';
    $listingParams[] = $batchFilter;
}
if ($channelFilter === 'B2B') {
    $listingSql .= ' AND bl.listing_id IS NOT NULL';
} elseif ($channelFilter === 'B2C') {
    $listingSql .= ' AND bc.listing_id IS NOT NULL';
}
$listingSql .= ' ORDER BY l.created_at DESC';
$statement = $pdo->prepare($listingSql);
$statement->execute($listingParams);
$listings = $statement->fetchAll();
$form = $edit ?: [
    'listing_id' => '',
    'batch_id' => '',
    'listing_type' => 'B2B',
    'listed_quantity' => '',
    'status' => 'Active',
    'minimum_quantity' => '',
    'bulk_unit_price' => '',
    'bundle_size' => '',
    'fixed_unit_price' => '',
];
$submittedValues = $_SESSION['listing_values'] ?? null;
unset($_SESSION['listing_values']);

if (is_array($submittedValues)) {
    $form = array_merge($form, $submittedValues);
}
if (!$edit && ($_GET['prefill'] ?? '') === '1') {
    $prefillBatch = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
    $prefillType = in_array(($_GET['listing_type'] ?? ''), ['B2B', 'B2C'], true) ? $_GET['listing_type'] : '';
    $owned = false;
    foreach ($batches as $candidate) {
        if ((int) $candidate['batch_id'] === $prefillBatch) {
            $owned = true;
            break;
        }
    }
    if ($owned && $prefillType) {
        $form['batch_id'] = $prefillBatch;
        $form['listing_type'] = $prefillType;
        $form['listed_quantity'] = max(0, (float) ($_GET['listed_quantity'] ?? 0));
        if ($prefillType === 'B2B') {
            $form['minimum_quantity'] = max(0, (float) ($_GET['minimum_quantity'] ?? 0));
            $form['bulk_unit_price'] = max(0, (float) ($_GET['bulk_unit_price'] ?? 0));
        } else {
            $form['bundle_size'] = max(0, (float) ($_GET['bundle_size'] ?? 0));
            $form['fixed_unit_price'] = max(0, (float) ($_GET['fixed_unit_price'] ?? 0));
        }
        setFlash('info', 'Pricing suggestion loaded. Review it before publishing; no listing has been created yet.');
    }
}
$pageTitle = 'Marketplace listings';
require __DIR__ . '/../../Mixed/includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Sales channels</div>
            <h1>Marketplace listings</h1>
            <p>Allocate batch stock to wholesale or retail buyers.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?=e(url('Farid/supplier/batches.php'))?>">Manage batches</a>
    </div>
    <section class="panel">
        <div class="section-heading">
            <div>
                <div class="eyebrow"><?=$edit ? 'Existing channel' : 'New allocation'?></div>
                <h2 class="h4 mb-0"><?=$edit ? 'Edit listing #' . e($edit['listing_id']) : 'Create a listing'?></h2>
            </div>
            <?php if ($edit):?>
            <span class="channel-badge channel-<?=e(strtolower($form['listing_type']))?>"> <?=e($form['listing_type'] === 'B2B' ? 'B2B Wholesale' : 'B2C Retail')?> </span>
            <?php endif;?>
        </div>
        <form method="post" action="<?= e(url('Farid/supplier/actions/save-listing.php')) ?>" data-listing-form data-edit-current="<?=e($edit && $form['status'] === 'Active' ? $form['listed_quantity'] : 0)?>">
            <?=csrfField()?>
            <input type="hidden" name="listing_id" value="<?=e($form['listing_id'])?>" />
            <div class="form-grid">
                <div>
                    <label class="form-label" for="batch_id">Source batch</label>
                    <select class="form-select" id="batch_id" name="batch_id" required <?=$edit ? 'disabled' : ''?>>
                        <option value="">Choose batch</option>
                        <?php foreach ($batches as $batch):?>
                        <option
                            value="<?=e($batch['batch_id'])?>"
                            data-material="<?=e($batch['material_type'] . ' · ' . $batch['color'])?>"
                            data-available="<?=e($batch['available_quantity'])?>"
                            data-allocated="<?=e($batch['allocated_quantity'])?>"
                            data-unit="<?=e($batch['unit_of_measure'])?>"
                            <?=(int) $form['batch_id'] === (int) $batch['batch_id'] ? 'selected' : ''?>
                        >
                            Batch #<?=e($batch['batch_id'])?> · <?=e($batch['material_type'])?> · <?=e($batch['available_quantity'])?> <?=e($batch['unit_of_measure'])?> available
                        </option>
                        <?php endforeach;?>
                    </select>
                    <?php if ($edit):?>
                    <input type="hidden" name="batch_id" value="<?=e($form['batch_id'])?>" />
                    <?php endif;?>
                </div>
                <div>
                    <label class="form-label" for="listing_type">Sales channel</label>
                    <select class="form-select" id="listing_type" name="listing_type" <?=$edit ? 'disabled' : ''?>>
                        <option value="B2B" <?=$form['listing_type'] === 'B2B' ? 'selected' : ''?>>B2B Wholesale</option>
                        <option value="B2C" <?=$form['listing_type'] === 'B2C' ? 'selected' : ''?>>B2C Retail</option>
                    </select>
                    <?php if ($edit):?>
                    <input type="hidden" name="listing_type" value="<?=e($form['listing_type'])?>" />
                    <small class="form-note d-block"
                        >The source batch and sales channel are permanent. Archive and recreate the listing to change
                        either.</small
                    >
                    <?php endif;?>
                </div>
                <div class="full batch-allocation" data-batch-summary hidden aria-live="polite">
                    <strong data-batch-summary-title> </strong>
                    <span data-batch-summary-copy> </span>
                </div>
                <div>
                    <label class="form-label" for="listed_quantity">Quantity allocated to this listing</label>
                    <div class="input-unit">
                        <input
                            class="form-control"
                            id="listed_quantity"
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="listed_quantity"
                            required
                            value="<?=e($form['listed_quantity'])?>"
                        />
                        <span data-batch-unit>units</span>
                    </div>
                </div>
                <div>
                    <label class="form-label" for="listing_status">Status</label>
                    <select class="form-select" id="listing_status" name="status">
                        <option <?=$form['status'] === 'Active' ? 'selected' : ''?>>Active</option>
                        <option <?=$form['status'] === 'Inactive' ? 'selected' : ''?>>Inactive</option>
                    </select>
                </div>
                <div class="channel-fields full" data-channel-panel="B2B" <?=$form['listing_type'] === 'B2B' ? '' : 'hidden'?>>
                    <div class="channel-heading">
                        <div>
                            <span class="channel-badge channel-b2b">B2B Wholesale</span>
                            <h3 class="h5">Wholesale terms</h3>
                        </div>
                        <p>For businesses purchasing larger quantities through quotation.</p>
                    </div>
                    <div class="form-grid">
                        <div>
                            <label class="form-label" for="minimum_quantity">Minimum order quantity</label>
                            <div class="input-unit">
                                <input
                                    class="form-control"
                                    id="minimum_quantity"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    name="minimum_quantity"
                                    value="<?=e($form['minimum_quantity'])?>"
                                    <?=$form['listing_type'] === 'B2B' ? 'required' : 'disabled'?>
                                />
                                <span data-batch-unit>units</span>
                            </div>
                        </div>
                        <div>
                            <label class="form-label" for="bulk_unit_price">Wholesale price per unit (BDT)</label>
                            <input
                                class="form-control"
                                id="bulk_unit_price"
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="bulk_unit_price"
                                value="<?=e($form['bulk_unit_price'])?>"
                                <?=$form['listing_type'] === 'B2B' ? 'required' : 'disabled'?>
                            />
                        </div>
                    </div>
                </div>
                <div class="channel-fields full" data-channel-panel="B2C" <?=$form['listing_type'] === 'B2C' ? '' : 'hidden'?>>
                    <div class="channel-heading">
                        <div>
                            <span class="channel-badge channel-b2c">B2C Retail</span>
                            <h3 class="h5">Retail terms</h3>
                        </div>
                        <p>For individuals purchasing fixed-size bundles at a listed price.</p>
                    </div>
                    <div class="form-grid">
                        <div>
                            <label class="form-label" for="bundle_size">Quantity per bundle</label>
                            <div class="input-unit">
                                <input
                                    class="form-control"
                                    id="bundle_size"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    name="bundle_size"
                                    value="<?=e($form['bundle_size'])?>"
                                    <?=$form['listing_type'] === 'B2C' ? 'required' : 'disabled'?>
                                />
                                <span data-batch-unit>units</span>
                            </div>
                        </div>
                        <div>
                            <label class="form-label" for="fixed_unit_price">Retail price per unit (BDT)</label>
                            <input
                                class="form-control"
                                id="fixed_unit_price"
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="fixed_unit_price"
                                value="<?=e($form['fixed_unit_price'])?>"
                                <?=$form['listing_type'] === 'B2C' ? 'required' : 'disabled'?>
                            />
                        </div>
                    </div>
                </div>
                <div class="full d-flex gap-2">
                    <button class="btn btn-primary" type="submit" data-listing-submit><?=$edit ? 'Save ' . e($form['listing_type']) . ' listing' : 'Publish B2B listing'?></button>
                    <?php if ($edit):?>
                    <a class="btn btn-outline-secondary" href="<?=e(url('Farid/supplier/listings.php'))?>">Cancel</a>
                    <?php endif;?>
                </div>
            </div>
        </form>
    </section>
    <aside class="channel-notice">
        <strong>One batch, separate sales channels</strong>
        <span
            >A batch may be divided into separate wholesale and retail listings. Each listing ID belongs to only one
            buyer channel and has its own allocated quantity.</span
        >
    </aside>
    <section class="panel">
        <div class="section-heading">
            <div>
                <div class="eyebrow">Channel allocations</div>
                <h2 class="h4 mb-0">Your listings</h2>
            </div>
        </div>
        <form method="get" class="filter-grid compact-listing-filters">
            <div>
                <label class="form-label" for="filter_batch">Source batch</label>
                <select class="form-select" id="filter_batch" name="batch_id">
                    <option value="">All batches</option>
                    <?php foreach ($batches as $batch):?>
                    <option value="<?=e($batch['batch_id'])?>" <?=$batchFilter === (int) $batch['batch_id'] ? 'selected' : ''?>>Batch #<?=e($batch['batch_id'])?> · <?=e($batch['material_type'])?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div>
                <label class="form-label" for="filter_channel">Sales channel</label>
                <select class="form-select" id="filter_channel" name="channel">
                    <option value="">All channels</option>
                    <option value="B2B" <?=$channelFilter === 'B2B' ? 'selected' : ''?>>B2B Wholesale</option>
                    <option value="B2C" <?=$channelFilter === 'B2C' ? 'selected' : ''?>>B2C Retail</option>
                </select>
            </div>
            <div class="filter-action">
                <button class="btn btn-primary">Apply filters</button>
                <a href="<?=e(url('Farid/supplier/listings.php'))?>">Clear</a>
            </div>
        </form>
        <div class="table-wrap">
            <table class="table align-middle responsive-table">
                <thead>
                    <tr>
                        <th>Listing / Batch</th>
                        <th>Material</th>
                        <th>Channel</th>
                        <th>Allocation</th>
                        <th>Channel terms</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listings as $listing):$listingImage = textileImage($listing['material_type'], $listing['composition']);?>
                    <tr>
                        <td data-label="Listing / Batch">
                            <strong>Listing #<?=e($listing['listing_id'])?> </strong>
                            <br />
                            <small class="muted">Batch #<?=e($listing['batch_id'])?> </small>
                        </td>
                        <td data-label="Material">
                            <div class="material-cell">
                                <img
                                    class="textile-thumb"
                                    src="<?=e($listingImage['src'])?>"
                                    alt="<?=e($listingImage['alt'])?>"
                                    width="120"
                                    height="80"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <span>
                                    <strong> <?=e($listing['material_type'])?> </strong>
                                    <small class="muted"> <?=e($listing['color'])?> </small>
                                    <small class="representative-label">Representative image</small>
                                </span>
                            </div>
                        </td>
                        <td data-label="Channel">
                            <span class="channel-badge channel-<?=e(strtolower($listing['listing_type']))?>"> <?=e($listing['listing_type'] === 'B2B' ? 'B2B Wholesale' : 'B2C Retail')?> </span>
                        </td>
                        <td data-label="Allocation"><?=e($listing['listed_quantity'])?> <?=e($listing['unit_of_measure'])?></td>
                        <td data-label="Channel terms"><?=$listing['listing_type'] === 'B2B' ? 'Minimum ' . e($listing['minimum_quantity']) . ' ' . e($listing['unit_of_measure']) . '<br>
<small class="muted">Wholesale ' . e(money($listing['bulk_unit_price'])) . ' per unit</small>' : 'Bundle ' . e($listing['bundle_size']) . ' ' . e($listing['unit_of_measure']) . '<br>
<small class="muted">Retail ' . e(money($listing['fixed_unit_price'])) . ' per unit</small>'?></td>
                        <td data-label="Status">
                            <span class="<?=e(statusClass($listing['status']))?>"> <?=e($listing['status'])?> </span>
                        </td>
                        <td data-label="Actions">
                            <div class="action-row">
                                <a class="btn btn-sm btn-outline-primary" href="?edit=<?=e($listing['listing_id'])?>">Edit</a>
                                <?php if ($listing['status'] === 'Active'):?>
                                <form method="post" action="<?= e(url('Farid/supplier/actions/archive-listing.php')) ?>">
                                    <?=csrfField()?>
                                    <input type="hidden" name="listing_id" value="<?=e($listing['listing_id'])?>" />
                                    <button class="btn btn-sm btn-outline-danger">Archive</button>
                                </form>
                                <?php endif;?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach;?>
<?php if (!$listings):?>
                    <tr>
                        <td colspan="7" class="text-center muted py-4">No listings match these filters.</td>
                    </tr>
                    <?php endif;?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../../Mixed/includes/footer.php';
?>
