<?php
require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('admin');

$pdo = db();

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(buyer.name LIKE ? OR buyer.email LIKE ? OR CAST(o.order_id AS CHAR) = ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $search);
}
if (in_array($status, ['Pending', 'Paid', 'Failed', 'Refunded'], true)) {
    $where[] = 'p.payment_status = ?';
    $params[] = $status;
} else {
    $status = '';
}
$sql = 'SELECT p.*, o.order_type, o.order_status, buyer.name AS buyer_name, buyer.email AS buyer_email
        FROM payment p JOIN orders o ON o.order_id = p.order_id
        JOIN users buyer ON buyer.user_id = o.buyer_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.payment_id DESC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$payments = $statement->fetchAll();
$pageTitle = 'Payment administration';
require __DIR__ . '/../../Mixed/includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Manual verification</div>
            <h1>Payments</h1>
            <p>Review simulated buyer payment submissions without external credentials.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= e(url('Shishir/admin/reports.php')) ?>">Platform reports</a>
    </div>
    <section class="filter-bar">
        <form method="get" class="filter-grid compact-filters">
            <div>
                <label class="form-label" for="q">Buyer or order</label>
                <input
                    class="form-control"
                    id="q"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Name, email, or order ID"
                />
            </div>
            <div>
                <label class="form-label" for="status">Payment status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (['Pending', 'Paid', 'Failed', 'Refunded'] as $item): ?>
                    <option <?= $status === $item ? 'selected' : '' ?>><?= e($item) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-action">
                <button class="btn btn-primary">Apply filters</button>
                <a href="<?= e(url('Shishir/admin/payments.php')) ?>">Clear</a>
            </div>
        </form>
    </section>
    <section class="panel mt-0">
        <div class="table-wrap">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Payment</th>
                        <th>Order</th>
                        <th>Buyer</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td>
                            #<?= e($payment['payment_id']) ?>
<?php if ($payment['payment_date']): ?>
                            <br />
                            <small class="muted"> <?= e(date('d M Y, H:i', strtotime($payment['payment_date']))) ?> </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?=e(url('Mixed/order.php?id=' . $payment['order_id']))?>">#<?= e($payment['order_id']) ?> </a>
                            <br />
                            <small class="muted"> <?= e($payment['order_type']) ?> · <?= e($payment['order_status']) ?> </small>
                            <br />
                            <a href="<?=e(url('Mixed/invoice.php?id=' . $payment['order_id']))?>">Print invoice</a>
                        </td>
                        <td>
                            <?= e($payment['buyer_name']) ?>
                            <br />
                            <small class="muted"> <?= e($payment['buyer_email']) ?> </small>
                        </td>
                        <td><?= e($payment['payment_method']) ?></td>
                        <td><?= e(money($payment['amount'])) ?></td>
                        <td>
                            <span class="<?= e(statusClass($payment['payment_status'])) ?>"> <?= e($payment['payment_status']) ?> </span>
                        </td>
                        <td>
                            <?php if ($payment['payment_status'] === 'Pending'): ?>
                            <form method="post" action="<?= e(url('Shishir/admin/actions/update-payment.php')) ?>" class="action-row">
                                <?= csrfField() ?>
                                <input type="hidden" name="payment_id" value="<?= e($payment['payment_id']) ?>" />
                                <input type="hidden" name="return_q" value="<?= e($search) ?>" />
                                <input type="hidden" name="return_status" value="<?= e($status) ?>" />
                                <button class="btn btn-sm btn-primary" name="status" value="Paid">Mark paid</button>
                                <button class="btn btn-sm btn-outline-danger" name="status" value="Failed">
                                    Mark failed
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="muted">Reviewed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
<?php if (!$payments): ?>
                    <tr>
                        <td colspan="7" class="text-center muted py-4">No payments matched the filters.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../../Mixed/includes/footer.php'; ?>
