<?php
require __DIR__ . '/includes/bootstrap.php';
requireLogin();

$pdo = db();
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId) {
    http_response_code(404);
    exit('Order not found.');
}
$order = accessibleOrder($pdo, $orderId);
$statement = $pdo->prepare('SELECT * FROM stock_transaction WHERE order_id=? ORDER BY transaction_date,transaction_id');
$statement->execute([$orderId]);
$ledger = $statement->fetchAll();
$stages = ['Confirmed', 'Processing', 'Completed'];
$stageIndex = match ($order['order_status']) {
    'Processing' => 1, 'Completed' => 2, default => 0
};
$displayStatus = displayOrderStatus($order);
$orderImage = textileImage($order['material_type'], $order['composition']);
$pageTitle = "Order #{$orderId}";
require __DIR__ . '/includes/header.php';
?>
<main class="container">
    <div class="page-head"><div><div class="eyebrow"><?= e(formatRole(currentUser()['role'])) ?> / Order details</div><h1>Order #<?= e($orderId) ?></h1><p><?= e($order['order_type']) ?> order placed <?= e(date('d M Y, H:i', strtotime($order['order_date']))) ?></p></div><div class="action-row"><a class="btn btn-outline-primary" href="<?= e(url('invoice.php?id=' . $orderId)) ?>">Print invoice</a><?php if (currentUser()['role'] === 'supplier'): ?><a class="btn btn-primary" href="<?= e(url('supplier/orders.php')) ?>">Back to orders</a><?php elseif (currentUser()['role'] === 'admin'): ?><a class="btn btn-primary" href="<?= e(url('admin/exceptions.php')) ?>">Operations</a><?php else: ?><a class="btn btn-primary" href="<?= e(url(strtolower($order['order_type']) . '/orders.php')) ?>">Back to orders</a><?php endif; ?></div></div>

    <?php if ($order['order_status'] === 'Cancelled'): ?><div class="attention-banner danger"><strong>Order cancelled</strong><span>The stock reservation was released. Payment state: <?= e($order['payment_status'] ?? 'Not submitted') ?>.</span></div><?php endif; ?>
    <?php if ($order['has_return']): ?><div class="attention-banner"><strong>Order returned</strong><span>The complete quantity was restored to stock<?= $order['payment_status'] === 'Refunded' ? ' and the paid payment was refunded' : '' ?>.</span></div><?php endif; ?>
    <?php if (in_array(currentUser()['role'], ['b2b','b2c'], true) && in_array($order['order_status'], ['Confirmed','Processing'], true) && (!$order['payment_status'] || $order['payment_status'] === 'Failed')): ?><div class="attention-banner"><strong>Payment needs attention</strong><span>Submit the simulated payment so the administrator can verify it.</span><a class="btn btn-sm btn-primary" href="<?= e(url('payment.php?order_id=' . $orderId)) ?>">Open payment</a></div><?php endif; ?>

    <?php if ($order['order_status'] === 'Completed' && !$order['has_return']): ?><div class="action-row mb-3"><a class="btn btn-outline-danger" href="<?= e(url('return.php?order_id=' . $orderId)) ?>">Return full order</a></div><?php endif; ?>

    <ol class="order-timeline" aria-label="Order progress">
        <?php foreach ($stages as $index => $stage): $done = $order['order_status'] !== 'Cancelled' && $stageIndex >= $index; ?><li class="<?= $done ? 'is-complete' : '' ?> <?= $order['order_status'] === $stage ? 'is-current' : '' ?>" <?= $order['order_status'] === $stage ? 'aria-current="step"' : '' ?>><span><?= e($index + 1) ?></span><div><strong><?= e($stage) ?></strong><small><?= e(match ($stage) {
            'Confirmed' => 'Stock reserved and order accepted', 'Processing' => 'Supplier is preparing the order', default => 'Sale completed and recorded'
        }) ?></small></div></li><?php endforeach; ?>
    </ol>

    <figure class="order-material-image"><img src="<?= e($orderImage['src']) ?>" alt="<?= e($orderImage['alt']) ?>" width="1200" height="800" loading="lazy" decoding="async"><figcaption>Representative image · <?= e($orderImage['category']) ?>. The supplier's exact stock may vary.</figcaption></figure>

    <div class="detail-layout">
        <section class="panel mt-0"><h2 class="h4">Order summary</h2><dl class="detail-grid"><div><dt>Material</dt><dd><?= e($order['material_type']) ?></dd></div><div><dt>Specification</dt><dd><?= e($order['composition']) ?> · <?= e($order['color']) ?> · <?= e($order['gsm']) ?> GSM</dd></div><div><dt>Quantity</dt><dd><?= e($order['quantity']) ?> <?= e($order['unit_of_measure']) ?></dd></div><div><dt>Unit price</dt><dd><?= e(money($order['selling_price'])) ?></dd></div><div><dt>Total</dt><dd><?= e(money($order['total_amount'])) ?></dd></div><div><dt>Gross profit snapshot</dt><dd><?= currentUser()['role'] === 'supplier' || currentUser()['role'] === 'admin' ? e(money($order['gross_profit'])) : 'Restricted' ?></dd></div><div><dt>Order status</dt><dd><span class="<?= e(statusClass($displayStatus)) ?>"><?= e($displayStatus) ?></span></dd></div><div><dt>Payment</dt><dd><span class="<?= e(statusClass($order['payment_status'] ?? 'Not submitted')) ?>"><?= e($order['payment_status'] ?? 'Not submitted') ?></span><?php if ($order['payment_method']): ?> · <?= e($order['payment_method']) ?><?php endif; ?></dd></div></dl></section>
        <aside class="panel mt-0"><h2 class="h4">Participants</h2><div class="party-card"><span>Buyer</span><strong><?= e($order['buyer_name']) ?></strong><small><?= e($order['buyer_email']) ?> · <?= e($order['buyer_phone']) ?></small><small><?= e($order['buyer_street'] . ', ' . $order['buyer_city'] . ', ' . $order['buyer_district']) ?></small></div><div class="party-card"><span>Supplier</span><strong><?= e($order['supplier_name']) ?></strong><small><?= e($order['supplier_email']) ?> · <?= e($order['supplier_phone']) ?></small><small><?= e($order['storage_location']) ?></small></div><?php if ($order['quotation_id']): ?><div class="party-card"><span>Quotation #<?= e($order['quotation_id']) ?></span><strong>Final <?= e(money($order['final_price'])) ?> per unit</strong><small>Original offer <?= e(money($order['proposed_price'])) ?><?php if ($order['counter_price'] !== null): ?> · Counter <?= e(money($order['counter_price'])) ?><?php endif; ?></small></div><?php endif; ?></aside>
    </div>

    <section class="panel"><h2 class="h4">Order ledger activity</h2><div class="ledger-strip"><?php foreach ($ledger as $row): $movement = stockMovement($row['transaction_type'], (float) $row['quantity']); ?><div><span class="movement <?= e($movement['class']) ?>"><?= e(str_replace('_', ' ', $row['transaction_type'])) ?></span><strong><?= e($movement['symbol']) ?><?= e(number_format($movement['quantity'], 2)) ?> <?= e($order['unit_of_measure']) ?></strong><small><?= e(date('d M Y, H:i', strtotime($row['transaction_date']))) ?> · <?= e($row['remarks'] ?: $movement['effect']) ?></small></div><?php endforeach; ?><?php if (!$ledger): ?><p class="muted mb-0">No order-linked ledger records are available.</p><?php endif; ?></div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
