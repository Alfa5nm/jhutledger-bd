<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('b2b');
$pdo = db();
$buyerId = (int) currentUser()['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = input('action');
    $quotationId = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);
    try {
        if ($action === 'create') {
            $listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
            $quantity = (float) input('requested_quantity');
            $price = (float) input('proposed_price');
            $expiry = input('expiry_date');
            if (!$listingId || $quantity <= 0 || $price < 0 || !validDate($expiry) || $expiry < date('Y-m-d')) {
                throw new RuntimeException('Enter valid quotation terms.');
            }
            $pdo->beginTransaction();
            $listing = lockListing($pdo, $listingId, 'B2B');
            if ($listing['listing_status'] !== 'Active' || $listing['batch_status'] !== 'Active' || $quantity < (float) $listing['minimum_quantity'] || $quantity > reservableQuantity($listing)) {
                throw new RuntimeException('Requested quantity is outside the available wholesale range.');
            }
            $statement = $pdo->prepare("SELECT COUNT(*) FROM quotation WHERE buyer_id=? AND listing_id=? AND status IN('Pending','Countered')");
            $statement->execute([$buyerId, $listingId]);
            if ($statement->fetchColumn()) {
                throw new RuntimeException('You already have an open quotation for this listing.');
            }
            $pdo->prepare('INSERT INTO quotation(buyer_id,listing_id,requested_quantity,proposed_price,expiry_date) VALUES(?,?,?,?,?)')->execute([$buyerId, $listingId, $quantity, $price, $expiry]);
            $pdo->commit();
            setFlash('success', 'Quotation request sent to the supplier.');
        } elseif ($action === 'accept' && $quotationId) {
            $orderId = acceptQuotation($pdo, $quotationId, 'b2b', $buyerId);
            setFlash('success', "Counter-offer accepted. Order #{$orderId} was confirmed and stock reserved.");
        } elseif ($action === 'cancel' && $quotationId) {
            $statement = $pdo->prepare("UPDATE quotation SET status='Cancelled' WHERE quotation_id=? AND buyer_id=? AND status IN('Pending','Countered')");
            $statement->execute([$quotationId, $buyerId]);
            if (!$statement->rowCount()) {
                throw new RuntimeException('Quotation cannot be cancelled.');
            }setFlash('success', 'Quotation cancelled.');
        } else {
            throw new RuntimeException('Invalid quotation action.');
        }
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }setFlash('danger', $exception->getMessage());
    }
    redirect('b2b/quotations.php');
}
$statement = $pdo->prepare("SELECT q.*,b.material_type,b.color,b.unit_of_measure,u.name supplier_name,o.order_id FROM quotation q JOIN listing l ON l.listing_id=q.listing_id JOIN textile_batch b ON b.batch_id=l.batch_id JOIN users u ON u.user_id=b.supplier_id LEFT JOIN orders o ON o.quotation_id=q.quotation_id WHERE q.buyer_id=? ORDER BY q.quotation_id DESC");
$statement->execute([$buyerId]);
$quotations = $statement->fetchAll();
$pageTitle = 'My quotations';
require __DIR__ . '/../includes/header.php';?>
<main class="container">
<div class="page-head">
<div>
<div class="eyebrow">Wholesale negotiation</div>
<h1>My quotations</h1>
<p>Track offers, supplier counter-offers, and converted orders.</p>
</div>
<a class="btn btn-primary" href="<?=e(url('marketplace.php'))?>">Browse marketplace</a>
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
<td>#<?=e($quotation['quotation_id'])?>
<?php if ($quotation['order_id']):?>
<br>
<small>Order #<?=e($quotation['order_id'])?>
</small>
<?php endif;?>
</td>
<td>
<?=e($quotation['material_type'])?>
<br>
<small class="muted">
<?=e($quotation['supplier_name'])?>
</small>
</td>
<td>
<?=e($quotation['requested_quantity'])?>
<?=e($quotation['unit_of_measure'])?>
</td>
<td>
<?=e(money($quotation['proposed_price']))?>
</td>
<td>
<?=e(money($quotation['final_price'] ?? $quotation['counter_price'] ?? 0))?>
</td>
<td>
<span class="<?=e(statusClass($quotation['status']))?>">
<?=e($quotation['status'])?>
</span>
</td>
<td>
<?php if ($quotation['status'] === 'Countered'):?>
<form method="post" class="action-row">
<?=csrfField()?>
<input type="hidden" name="quotation_id" value="<?=e($quotation['quotation_id'])?>">
<button class="btn btn-sm btn-primary" name="action" value="accept">Accept</button>
<button class="btn btn-sm btn-outline-danger" name="action" value="cancel">Cancel</button>
</form>
<?php elseif ($quotation['status'] === 'Pending'):?>
<form method="post">
<?=csrfField()?>
<input type="hidden" name="quotation_id" value="<?=e($quotation['quotation_id'])?>">
<button class="btn btn-sm btn-outline-danger" name="action" value="cancel">Cancel</button>
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
<?php require __DIR__ . '/../includes/footer.php';?>
