<?php

declare(strict_types=1);

if (!isset($orderType, $buyerRole) || !in_array($orderType, ['B2B', 'B2C'], true)) {
    throw new LogicException('Buyer order page configuration is missing.');
}

$pdo = db();
$buyerId = (int) currentUser()['user_id'];

$statement = $pdo->prepare(
    "SELECT o.*, oi.quantity, oi.selling_price, l.listing_id,
            b.material_type, b.color, b.unit_of_measure, supplier.name AS supplier_name,
            p.payment_id, p.payment_method, p.payment_status,
            EXISTS(SELECT 1 FROM stock_transaction returned WHERE returned.order_id=o.order_id AND returned.transaction_type='RETURNED') has_return
     FROM orders o
     JOIN order_item oi ON oi.order_id = o.order_id AND oi.line_no = 1
     JOIN listing l ON l.listing_id = oi.listing_id
     JOIN textile_batch b ON b.batch_id = l.batch_id
     JOIN users supplier ON supplier.user_id = b.supplier_id
     LEFT JOIN payment p ON p.order_id = o.order_id
     WHERE o.buyer_id = ? AND o.order_type = ?
     ORDER BY o.order_date DESC, o.order_id DESC"
);
$statement->execute([$buyerId, $orderType]);
$orders = $statement->fetchAll();

$pageTitle = "{$orderType} orders";
require __DIR__ . '/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow"><?= e($orderType) ?> purchases</div>
            <h1>My orders</h1>
            <p>Track fulfilment, submit payment, or cancel before processing begins.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('Abir/marketplace.php')) ?>">Browse marketplace</a>
    </div>
    <section class="panel mt-0">
        <div class="table-wrap">
            <table class="table align-middle responsive-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Material</th>
                        <th>Supplier</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Order</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td data-label="Order">
                            <strong>#<?= e($order['order_id']) ?> </strong>
                            <br />
                            <small class="muted"> <?= e(date('d M Y', strtotime($order['order_date']))) ?> </small>
                        </td>
                        <td data-label="Material">
                            <?= e($order['material_type']) ?>
                            <br />
                            <small class="muted"> <?= e($order['color']) ?> · Listing #<?= e($order['listing_id']) ?> </small>
                        </td>
                        <td data-label="Supplier"><?= e($order['supplier_name']) ?></td>
                        <td data-label="Quantity"><?= e($order['quantity']) ?> <?= e($order['unit_of_measure']) ?></td>
                        <td data-label="Total"><?= e(money($order['total_amount'])) ?></td>
                        <td data-label="Order status">
                            <span class="<?= e(statusClass(displayOrderStatus($order))) ?>"> <?= e(displayOrderStatus($order)) ?> </span>
                        </td>
                        <td data-label="Payment">
                            <?php if ($order['payment_status']): ?>
                            <span class="<?= e(statusClass($order['payment_status'])) ?>"> <?= e($order['payment_status']) ?> </span>
                            <br />
                            <small class="muted"> <?= e($order['payment_method']) ?> </small>
                            <?php else: ?>
                            <span class="badge-soft">Not submitted</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <div class="action-row">
                                <a class="btn btn-sm btn-outline-primary" href="<?=e(url('Mixed/order.php?id=' . $order['order_id']))?>">View details</a>
                                <a class="btn btn-sm btn-outline-primary" href="<?=e(url('Mixed/invoice.php?id=' . $order['order_id']))?>">Print invoice</a>
                                <?php if (in_array($order['order_status'], ['Confirmed', 'Processing'], true) && (!$order['payment_status'] || $order['payment_status'] === 'Failed')): ?>
                                <a class="btn btn-sm btn-primary" href="<?= e(url('Shishir/payment.php?order_id=' . $order['order_id'])) ?>"> <?= $order['payment_status'] === 'Failed' ? 'Retry payment' : 'Pay' ?> </a>
                                <?php endif; ?>
<?php if (in_array($order['order_status'], ['Pending', 'Confirmed'], true)): ?>
                                <form
                                    method="post"
                                    action="<?= e(url('Mixed/actions/cancel-order.php')) ?>"
                                    onsubmit="return confirm('Cancel this order and restore its reserved stock?');"
                                >
                                    <?= csrfField() ?>
                                    <input type="hidden" name="order_id" value="<?= e($order['order_id']) ?>" />
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                </form>
                                <?php endif; ?>
<?php if (in_array($order['order_status'], ['Completed', 'Cancelled'], true)): ?>
                                <form method="post" action="<?= e(url('Mixed/actions/repeat-order.php')) ?>">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="order_id" value="<?= e($order['order_id']) ?>" />
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Buy again</button>
                                </form>
                                <?php endif; ?>
<?php if ($order['order_status'] === 'Completed' && !$order['has_return']): ?>
                                <a class="btn btn-sm btn-outline-danger" href="<?= e(url('Mixed/return.php?order_id=' . $order['order_id'])) ?>">Return</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
<?php if (!$orders): ?>
                    <tr>
                        <td colspan="8" class="text-center muted py-4">You have not placed an order yet.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__ . '/footer.php'; ?>
