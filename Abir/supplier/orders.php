<?php
require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('supplier');

$pdo = db();
$supplierId = (int) currentUser()['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $action = input('action');
    try {
        if (!$orderId) {
            throw new RuntimeException('Select a valid order.');
        }
        if ($action === 'processing') {
            advanceOrderStatus($pdo, $orderId, $supplierId, 'Processing');
            setFlash('success', "Order #{$orderId} is now processing.");
        } elseif ($action === 'complete') {
            advanceOrderStatus($pdo, $orderId, $supplierId, 'Completed');
            setFlash('success', "Order #{$orderId} completed and the sale was added to the stock ledger.");
        } elseif ($action === 'cancel') {
            cancelOrder($pdo, $orderId, 'supplier', $supplierId);
            setFlash('success', "Order #{$orderId} cancelled and stock restored.");
        } else {
            throw new RuntimeException('Invalid order action.');
        }
    } catch (Throwable $exception) {
        setFlash('danger', $exception->getMessage());
    }
    redirect('Abir/supplier/orders.php');
}

$statement = $pdo->prepare(
    "SELECT o.*, oi.quantity, b.material_type, b.color, b.unit_of_measure,
            buyer.name AS buyer_name, buyer.email AS buyer_email,
            p.payment_method, p.payment_status,
            EXISTS(SELECT 1 FROM stock_transaction returned WHERE returned.order_id=o.order_id AND returned.transaction_type='RETURNED') has_return
     FROM orders o
     JOIN order_item oi ON oi.order_id = o.order_id AND oi.line_no = 1
     JOIN listing l ON l.listing_id = oi.listing_id
     JOIN textile_batch b ON b.batch_id = l.batch_id
     JOIN users buyer ON buyer.user_id = o.buyer_id
     LEFT JOIN payment p ON p.order_id = o.order_id
     WHERE b.supplier_id = ?
     ORDER BY o.order_date DESC, o.order_id DESC"
);
$statement->execute([$supplierId]);
$orders = $statement->fetchAll();
$pageTitle = 'Supplier orders';
require __DIR__ . '/../../Mixed/includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Order fulfilment</div>
            <h1>Customer orders</h1>
            <p>Process confirmed orders, complete sales, or release stock before processing.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= e(url('Shishir/supplier/reports.php')) ?>">Sales reports</a>
    </div>
    <section class="panel mt-0">
        <div class="table-wrap">
            <table class="table align-middle responsive-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Buyer</th>
                        <th>Material</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td data-label="Order">
                            <strong>#<?= e($order['order_id']) ?> </strong>
                            <br />
                            <small class="muted"> <?= e($order['order_type']) ?> · <?= e(date('d M Y', strtotime($order['order_date']))) ?> </small>
                        </td>
                        <td data-label="Buyer">
                            <?= e($order['buyer_name']) ?>
                            <br />
                            <small class="muted"> <?= e($order['buyer_email']) ?> </small>
                        </td>
                        <td data-label="Material">
                            <?= e($order['material_type']) ?>
                            <br />
                            <small class="muted"> <?= e($order['color']) ?> </small>
                        </td>
                        <td data-label="Quantity"><?= e($order['quantity']) ?> <?= e($order['unit_of_measure']) ?></td>
                        <td data-label="Total"><?= e(money($order['total_amount'])) ?></td>
                        <td data-label="Payment">
                            <span class="<?= e(statusClass($order['payment_status'] ?? 'Not submitted')) ?>"> <?= e($order['payment_status'] ?? 'Not submitted') ?> </span>
                            <?php if ($order['payment_method']): ?>
                            <br />
                            <small class="muted"> <?= e($order['payment_method']) ?> </small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <span class="<?= e(statusClass(displayOrderStatus($order))) ?>"> <?= e(displayOrderStatus($order)) ?> </span>
                        </td>
                        <td data-label="Actions">
                            <div class="action-row">
                                <a class="btn btn-sm btn-outline-primary" href="<?=e(url('Mixed/order.php?id=' . $order['order_id']))?>">Details</a>
                                <a class="btn btn-sm btn-outline-primary" href="<?=e(url('Mixed/invoice.php?id=' . $order['order_id']))?>">Invoice</a>
                                <?php if ($order['order_status'] === 'Confirmed'): ?>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="order_id" value="<?= e($order['order_id']) ?>" />
                                    <button class="btn btn-sm btn-primary" name="action" value="processing">
                                        Start processing
                                    </button>
                                </form>
                                <?php elseif ($order['order_status'] === 'Processing'): ?>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="order_id" value="<?= e($order['order_id']) ?>" />
                                    <button class="btn btn-sm btn-primary" name="action" value="complete">
                                        Complete
                                    </button>
                                </form>
                                <?php endif; ?>
<?php if (in_array($order['order_status'], ['Pending', 'Confirmed'], true)): ?>
                                <form
                                    method="post"
                                    onsubmit="return confirm('Cancel this order and restore the reserved stock?');"
                                >
                                    <?= csrfField() ?>
                                    <input type="hidden" name="order_id" value="<?= e($order['order_id']) ?>" />
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="cancel">
                                        Cancel
                                    </button>
                                </form>
                                <?php endif; ?>
<?php if ($order['order_status'] === 'Completed' && !$order['has_return']): ?>
                                <a class="btn btn-sm btn-outline-danger" href="<?= e(url('Mixed/return.php?order_id=' . $order['order_id'])) ?>">Process return</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
<?php if (!$orders): ?>
                    <tr>
                        <td colspan="8" class="text-center muted py-4">
                            No orders have been placed against your listings.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../../Mixed/includes/footer.php'; ?>
