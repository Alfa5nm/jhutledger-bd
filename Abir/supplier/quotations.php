<?php
require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('supplier');
$pdo = db();
$supplierId = (int) currentUser()['user_id'];
$statement = $pdo->prepare(
    'SELECT q.*, b.material_type, b.color, b.unit_of_measure,
            u.name AS buyer_name, o.order_id
     FROM quotation AS q
     JOIN listing AS l ON l.listing_id = q.listing_id
     JOIN textile_batch AS b ON b.batch_id = l.batch_id
     JOIN users AS u ON u.user_id = q.buyer_id
     LEFT JOIN orders AS o ON o.quotation_id = q.quotation_id
     WHERE b.supplier_id = ?
     ORDER BY q.quotation_id DESC'
);
$statement->execute([$supplierId]);
$quotations = $statement->fetchAll();
$pageTitle = 'Buyer quotations';
require __DIR__ . '/../../Mixed/includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Wholesale offers</div>
            <h1>Buyer quotations</h1>
            <p>Accept, counter, or reject offers on your B2B listings.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?=e(url('Farid/supplier/listings.php'))?>">View listings</a>
    </div>
    <section class="panel">
        <div class="table-wrap">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Quote</th>
                        <th>Buyer</th>
                        <th>Material</th>
                        <th>Quantity</th>
                        <th>Offer</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotations as $quotation):?>
                    <tr>
                        <td>
                            #<?=e($quotation['quotation_id'])?>
<?php if ($quotation['order_id']):?>
                            <br />
                            <small>Order #<?=e($quotation['order_id'])?> </small>
                            <?php endif;?>
                        </td>
                        <td><?=e($quotation['buyer_name'])?></td>
                        <td>
                            <?=e($quotation['material_type'])?>
                            <br />
                            <small class="muted"> <?=e($quotation['color'])?> </small>
                        </td>
                        <td><?=e($quotation['requested_quantity'])?> <?=e($quotation['unit_of_measure'])?></td>
                        <td>
                            <?=e(money($quotation['proposed_price']))?>
<?php if ($quotation['counter_price'] !== null):?>
                            <br />
                            <small>Counter: <?=e(money($quotation['counter_price']))?> </small>
                            <?php endif;?>
                        </td>
                        <td>
                            <span class="<?=e(statusClass($quotation['status']))?>"> <?=e($quotation['status'])?> </span>
                        </td>
                        <td>
                            <?php if ($quotation['status'] === 'Pending'):?>
                            <form method="post" action="<?= e(url('Abir/supplier/actions/accept-quotation.php')) ?>" class="quote-actions">
                                <?=csrfField()?>
                                <input type="hidden" name="quotation_id" value="<?=e($quotation['quotation_id'])?>" />
                                <button class="btn btn-sm btn-primary">Accept</button>
                                <div class="counter-line">
                                    <input
                                        class="form-control form-control-sm"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="counter_price"
                                        value="<?=e($quotation['proposed_price'])?>"
                                    />
                                    <button class="btn btn-sm btn-outline-primary" formaction="<?= e(url('Abir/supplier/actions/counter-quotation.php')) ?>">
                                        Counter
                                    </button>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" formaction="<?= e(url('Abir/supplier/actions/reject-quotation.php')) ?>">
                                    Reject
                                </button>
                            </form>
                            <?php elseif ($quotation['status'] === 'Countered'):?>
                            <form method="post" action="<?= e(url('Abir/supplier/actions/reject-quotation.php')) ?>">
                                <?=csrfField()?>
                                <input type="hidden" name="quotation_id" value="<?=e($quotation['quotation_id'])?>" />
                                <button class="btn btn-sm btn-outline-danger">
                                    Withdraw
                                </button>
                            </form>
                            <?php else:?>
                            <span class="muted">—</span>
                            <?php endif;?>
                        </td>
                    </tr>
                    <?php endforeach;?>
<?php if (!$quotations):?>
                    <tr>
                        <td colspan="7" class="text-center muted py-4">No buyer quotations yet.</td>
                    </tr>
                    <?php endif;?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../../Mixed/includes/footer.php';
?>
