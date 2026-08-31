<?php
require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('b2b');
$pdo = db();
$buyerId = (int) currentUser()['user_id'];
$statement = $pdo->prepare(
    'SELECT q.*, b.material_type, b.color, b.unit_of_measure,
            u.name AS supplier_name, o.order_id
     FROM quotation AS q
     JOIN listing AS l ON l.listing_id = q.listing_id
     JOIN textile_batch AS b ON b.batch_id = l.batch_id
     JOIN users AS u ON u.user_id = b.supplier_id
     LEFT JOIN orders AS o ON o.quotation_id = q.quotation_id
     WHERE q.buyer_id = ?
     ORDER BY q.quotation_id DESC'
);
$statement->execute([$buyerId]);
$quotations = $statement->fetchAll();
$pageTitle = 'My quotations';
require __DIR__ . '/../../Mixed/includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Wholesale negotiation</div>
            <h1>My quotations</h1>
            <p>Track offers, supplier counter-offers, and converted orders.</p>
        </div>
        <a class="btn btn-primary" href="<?=e(url('Abir/marketplace.php'))?>">Browse marketplace</a>
    </div>
    <section class="panel">
        <div class="table-wrap">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Quote</th>
                        <th>Material</th>
                        <th>Quantity</th>
                        <th>Your offer</th>
                        <th>Counter/final</th>
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
                        <td>
                            <?=e($quotation['material_type'])?>
                            <br />
                            <small class="muted"> <?=e($quotation['supplier_name'])?> </small>
                        </td>
                        <td><?=e($quotation['requested_quantity'])?> <?=e($quotation['unit_of_measure'])?></td>
                        <td><?=e(money($quotation['proposed_price']))?></td>
                        <td><?=e(money($quotation['final_price'] ?? $quotation['counter_price'] ?? 0))?></td>
                        <td>
                            <span class="<?=e(statusClass($quotation['status']))?>"> <?=e($quotation['status'])?> </span>
                        </td>
                        <td>
                            <?php if ($quotation['status'] === 'Countered'):?>
                            <form method="post" action="<?= e(url('Abir/b2b/actions/accept-quotation.php')) ?>" class="action-row">
                                <?=csrfField()?>
                                <input type="hidden" name="quotation_id" value="<?=e($quotation['quotation_id'])?>" />
                                <button class="btn btn-sm btn-primary">Accept</button>
                                <button class="btn btn-sm btn-outline-danger" formaction="<?= e(url('Abir/b2b/actions/cancel-quotation.php')) ?>">
                                    Cancel
                                </button>
                            </form>
                            <?php elseif ($quotation['status'] === 'Pending'):?>
                            <form method="post" action="<?= e(url('Abir/b2b/actions/cancel-quotation.php')) ?>">
                                <?=csrfField()?>
                                <input type="hidden" name="quotation_id" value="<?=e($quotation['quotation_id'])?>" />
                                <button class="btn btn-sm btn-outline-danger">
                                    Cancel
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
                        <td colspan="7" class="text-center muted py-4">No quotations yet.</td>
                    </tr>
                    <?php endif;?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../../Mixed/includes/footer.php';
?>
