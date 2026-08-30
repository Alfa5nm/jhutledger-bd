<?php
require __DIR__ . '/includes/bootstrap.php';
requireLogin();

$pdo = db();
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$orderId) {
    http_response_code(404);
    exit('Order not found.');
}
$order = accessibleOrder($pdo, $orderId);
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        returnOrder($pdo, $orderId, $user['role'], (int) $user['user_id']);
        setFlash('success', "Order #{$orderId} was returned. Stock was restored and any paid payment was refunded.");
        redirect('Mixed/order.php?id=' . $orderId);
    } catch (Throwable $exception) {
        setFlash('danger', $exception->getMessage());
        redirect('Mixed/return.php?order_id=' . $orderId);
    }
}

$pageTitle = "Return order #{$orderId}";
require __DIR__ . '/includes/header.php';
?>
<main class="container narrow-page">
    <div class="page-head">
        <div>
            <div class="eyebrow">Full-order return</div>
            <h1>Return order #<?= e($orderId) ?></h1>
            <p>Confirm receipt of the complete order quantity. Partial returns are not supported.</p>
        </div>
    </div>
    <section class="panel mt-0">
        <?php if (!empty($order['has_return'])): ?>
        <div class="attention-banner">
            <strong>Already returned</strong>
            <span>This order already has a RETURNED stock-ledger entry.</span>
        </div>
        <?php elseif ($order['order_status'] !== 'Completed'): ?>
        <div class="attention-banner danger">
            <strong>Return unavailable</strong>
            <span>Only completed orders can be returned.</span>
        </div>
        <?php else: ?>
        <h2 class="h4">Return summary</h2>
        <dl class="detail-grid">
            <div>
                <dt>Material</dt>
                <dd><?= e($order['material_type']) ?></dd>
            </div>
            <div>
                <dt>Quantity restored</dt>
                <dd><?= e($order['quantity']) ?> <?= e($order['unit_of_measure']) ?></dd>
            </div>
            <div>
                <dt>Listing</dt>
                <dd>#<?= e($order['listing_id']) ?></dd>
            </div>
            <div>
                <dt>Payment result</dt>
                <dd><?= $order['payment_status'] === 'Paid' ? 'Paid → Refunded' : e($order['payment_status'] ?? 'No payment change') ?></dd>
            </div>
        </dl>
        <p class="muted">
            This transaction restores the listing and batch quantities, records one RETURNED ledger movement, and
            prevents a duplicate return.
        </p>
        <form
            method="post"
            class="action-row"
            onsubmit="return confirm('Return the complete order and restore all stock?');"
        >
            <?= csrfField() ?>
            <button class="btn btn-danger" type="submit">Confirm full return</button>
            <a class="btn btn-outline-primary" href="<?= e(url('Mixed/order.php?id=' . $orderId)) ?>">Keep order</a>
        </form>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
