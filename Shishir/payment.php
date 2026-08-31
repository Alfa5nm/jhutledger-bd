<?php
require __DIR__ . '/../Mixed/includes/bootstrap.php';
requireRole(['b2b', 'b2c']);

$pdo = db();
$buyerId = (int) currentUser()['user_id'];
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$orderId) {
    http_response_code(404);
    exit('Order not found.');
}

$statement = $pdo->prepare(
    'SELECT o.*, oi.quantity, b.material_type, b.unit_of_measure, supplier.name AS supplier_name,
            p.payment_method, p.payment_status
     FROM orders o
     JOIN order_item oi ON oi.order_id = o.order_id AND oi.line_no = 1
     JOIN listing l ON l.listing_id = oi.listing_id
     JOIN textile_batch b ON b.batch_id = l.batch_id
     JOIN users supplier ON supplier.user_id = b.supplier_id
     LEFT JOIN payment p ON p.order_id = o.order_id
     WHERE o.order_id = ? AND o.buyer_id = ?'
);
$statement->execute([$orderId, $buyerId]);
$order = $statement->fetch();
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$canSubmit = in_array($order['order_status'], ['Confirmed', 'Processing'], true)
    && (!$order['payment_status'] || $order['payment_status'] === 'Failed');
$pageTitle = 'Order payment';
require __DIR__ . '/../Mixed/includes/header.php';
?>
<main class="container narrow">
    <div class="page-head">
        <div>
            <div class="eyebrow">Simulated payment</div>
            <h1>Order #<?= e($orderId) ?></h1>
            <p>Submit a payment method for administrator verification.</p>
        </div>
    </div>
    <section class="panel mt-0">
        <dl class="detail-grid">
            <div>
                <dt>Material</dt>
                <dd><?= e($order['material_type']) ?></dd>
            </div>
            <div>
                <dt>Supplier</dt>
                <dd><?= e($order['supplier_name']) ?></dd>
            </div>
            <div>
                <dt>Quantity</dt>
                <dd><?= e($order['quantity']) ?> <?= e($order['unit_of_measure']) ?></dd>
            </div>
            <div>
                <dt>Amount</dt>
                <dd><?= e(money($order['total_amount'])) ?></dd>
            </div>
            <div>
                <dt>Order status</dt>
                <dd>
                    <span class="<?= e(statusClass($order['order_status'])) ?>"> <?= e($order['order_status']) ?> </span>
                </dd>
            </div>
            <div>
                <dt>Payment status</dt>
                <dd>
                    <span class="<?= e(statusClass($order['payment_status'] ?? 'Not submitted')) ?>"> <?= e($order['payment_status'] ?? 'Not submitted') ?> </span>
                </dd>
            </div>
        </dl>
        <?php if ($canSubmit): ?>
        <form method="post" action="<?= e(url('Shishir/actions/submit-payment.php')) ?>" class="mt-4">
            <?= csrfField() ?>
            <input type="hidden" name="order_id" value="<?= e($orderId) ?>" />
            <label class="form-label" for="payment_method">Payment method</label>
            <select class="form-select" id="payment_method" name="payment_method" required>
                <option value="">Choose a method</option>
                <?php foreach (['Cash', 'Bank Transfer', 'Mobile Banking', 'Card'] as $method): ?>
                <option value="<?= e($method) ?>" <?= $order['payment_method'] === $method ? 'selected' : '' ?>><?= e($method) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="form-note">
                This faculty demonstration records a simulated payment; no real money is transferred.
            </p>
            <button class="btn btn-primary w-100" type="submit"><?= $order['payment_status'] === 'Failed' ? 'Resubmit payment' : 'Submit payment' ?></button>
        </form>
        <?php else: ?>
        <div class="alert alert-info mt-4 mb-0">This order does not currently accept another payment submission.</div>
        <?php endif; ?>
        <div class="action-row mt-3">
            <a class="btn btn-outline-primary" href="<?=e(url('Mixed/order.php?id=' . $orderId))?>">View order details</a>
            <a class="btn btn-outline-primary" href="<?=e(url('Mixed/invoice.php?id=' . $orderId))?>">Print invoice</a>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../Mixed/includes/footer.php'; ?>
