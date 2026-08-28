<?php
require __DIR__ . '/includes/bootstrap.php';
requireLogin();
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId) {
    http_response_code(404);
    exit('Order not found.');
}
$order = accessibleOrder(db(), $orderId);
$documentLabel = $order['payment_status'] === 'Paid' ? 'PAID RECEIPT' : ($order['payment_status'] === 'Refunded' ? 'REFUNDED RECEIPT' : 'INVOICE');
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title><?= e($documentLabel) ?> #<?= e($orderId) ?> | JhutLedger BD</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet" />
    </head>
    <body class="invoice-page">
        <main class="invoice-sheet">
            <div class="invoice-actions no-print">
                <a class="btn btn-outline-primary" href="<?= e(url('order.php?id=' . $orderId)) ?>">Back to order</a>
                <button class="btn btn-primary" type="button" onclick="window.print()">Print / Save PDF</button>
            </div>
            <header class="invoice-head">
                <div>
                    <div class="invoice-brand">JhutLedger <small>Bangladesh</small></div>
                    <p>Surplus textile, put back to work</p>
                </div>
                <div class="invoice-number">
                    <span> <?= e($documentLabel) ?> </span>
                    <strong>#<?= e($orderId) ?> </strong>
                    <small> <?= e(date('d M Y', strtotime($order['order_date']))) ?> </small>
                </div>
            </header>
            <?php if ($order['order_status'] === 'Cancelled'): ?>
            <div class="invoice-watermark">CANCELLED</div>
            <?php elseif ($order['has_return']): ?>
            <div class="invoice-watermark">RETURNED</div>
            <?php endif; ?>
            <section class="invoice-parties">
                <div>
                    <span>Issued to</span>
                    <strong> <?= e($order['buyer_name']) ?> </strong>
                    <p>
                        <?= e($order['buyer_email']) ?>
                        <br />
                        <?= e($order['buyer_phone']) ?>
                        <br />
                        <?= e($order['buyer_street'] . ', ' . $order['buyer_city'] . ', ' . $order['buyer_district']) ?>
                    </p>
                </div>
                <div>
                    <span>Supplied by</span>
                    <strong> <?= e($order['supplier_name']) ?> </strong>
                    <p>
                        <?= e($order['supplier_email']) ?>
                        <br />
                        <?= e($order['supplier_phone']) ?>
                        <br />
                        <?= e($order['storage_location']) ?>
                    </p>
                </div>
            </section>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit price</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong> <?= e($order['material_type']) ?> </strong>
                            <small> <?= e($order['composition']) ?> · <?= e($order['color']) ?> · <?= e($order['gsm']) ?> GSM · <?= e($order['condition']) ?> </small>
                        </td>
                        <td><?= e(number_format((float) $order['quantity'], 2)) ?> <?= e($order['unit_of_measure']) ?></td>
                        <td><?= e(money($order['selling_price'])) ?></td>
                        <td><?= e(money($order['total_amount'])) ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td><?= e(money($order['total_amount'])) ?></td>
                    </tr>
                </tfoot>
            </table>
            <section class="invoice-status">
                <div>
                    <span>Order status</span>
                    <strong> <?= e(displayOrderStatus($order)) ?> </strong>
                </div>
                <div>
                    <span>Payment status</span>
                    <strong> <?= e($order['payment_status'] ?? 'Not submitted') ?> </strong>
                </div>
                <div>
                    <span>Payment method</span>
                    <strong> <?= e($order['payment_method'] ?? '—') ?> </strong>
                </div>
                <div>
                    <span>Payment date</span>
                    <strong> <?= $order['payment_date'] ? e(date('d M Y, H:i', strtotime($order['payment_date']))) : '—' ?> </strong>
                </div>
            </section>
            <footer class="invoice-foot">
                <p>
                    This document was generated from immutable order-item price and cost snapshots. It is a simulated
                    academic marketplace document and does not represent a real financial transaction.
                </p>
            </footer>
        </main>
    </body>
</html>
