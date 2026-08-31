<?php
require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('admin');
$pdo = db();
$counts = adminExceptionCounts($pdo);
$pending = $pdo->query(
    "SELECT p.payment_id, p.order_id, p.amount, p.payment_method,
            u.name AS buyer_name, o.order_date
     FROM payment AS p
     JOIN orders AS o ON o.order_id = p.order_id
     JOIN users AS u ON u.user_id = o.buyer_id
     WHERE p.payment_status = 'Pending'
     ORDER BY o.order_date"
)->fetchAll();
$overdue = $pdo->query(
    "SELECT o.order_id, o.order_type, o.order_date, u.name AS buyer_name
     FROM orders AS o
     JOIN users AS u ON u.user_id = o.buyer_id
     WHERE o.order_status = 'Confirmed' AND o.order_date < NOW() - INTERVAL 2 DAY
     ORDER BY o.order_date"
)->fetchAll();
$expired = $pdo->query(
    "SELECT q.quotation_id, q.expiry_date, q.status,
            u.name AS buyer_name, b.material_type
     FROM quotation AS q
     JOIN users AS u ON u.user_id = q.buyer_id
     JOIN listing AS l ON l.listing_id = q.listing_id
     JOIN textile_batch AS b ON b.batch_id = l.batch_id
     WHERE q.status IN ('Pending', 'Countered') AND q.expiry_date < CURDATE()
     ORDER BY q.expiry_date"
)->fetchAll();
$lowStock = $pdo->query(
    "SELECT b.batch_id, b.material_type, b.available_quantity,
            b.total_quantity, b.unit_of_measure, u.name AS supplier_name
     FROM textile_batch AS b
     JOIN users AS u ON u.user_id = b.supplier_id
     WHERE b.status = 'Active'
       AND b.total_quantity > 0
       AND b.available_quantity / b.total_quantity <= 0.20
     ORDER BY b.available_quantity / b.total_quantity"
)->fetchAll();
$zeroListings = $pdo->query(
    "SELECT l.listing_id, b.material_type, l.listed_quantity,
            b.available_quantity, b.unit_of_measure, u.name AS supplier_name
     FROM listing AS l
     JOIN textile_batch AS b ON b.batch_id = l.batch_id
     JOIN users AS u ON u.user_id = b.supplier_id
     WHERE l.status = 'Active' AND (l.listed_quantity <= 0 OR b.available_quantity <= 0)
     ORDER BY l.listing_id"
)->fetchAll();
$inactiveOpen = $pdo->query(
    "SELECT DISTINCT u.user_id, u.name, u.email, o.order_id, o.order_status
     FROM users AS u
     JOIN orders AS o ON o.buyer_id = u.user_id
     WHERE u.user_status = 'Inactive'
       AND o.order_status IN ('Pending', 'Confirmed', 'Processing')
     ORDER BY u.name"
)->fetchAll();
$pageTitle = 'Operational exceptions';
require __DIR__ . '/../../Mixed/includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Admin / Operations</div>
            <h1>Exception monitor</h1>
            <p>Live queues that need attention, derived from the existing academic schema.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= e(url('Shishir/admin/dashboard.php')) ?>">Admin dashboard</a>
    </div>
    <div class="exception-grid">
        <?php foreach ($counts as $label => $value): ?>
        <a class="exception-card <?= $value ? 'has-items' : '' ?>" href="#<?= e(strtolower(str_replace(' ', '-', $label))) ?>">
            <span> <?= e($label) ?> </span>
            <strong> <?= e($value) ?> </strong>
            <small> <?= $value ? 'Needs review' : 'No issues' ?> </small>
        </a>
        <?php endforeach; ?>
    </div>
    <section class="panel" id="pending-payments">
        <h2 class="h4">Pending payments</h2>
        <div class="queue-list">
            <?php foreach ($pending as $row):?>
            <article>
                <div>
                    <span class="badge-warning">Pending</span>
                    <strong>Payment #<?=e($row['payment_id'])?> · <?=e(money($row['amount']))?> </strong>
                    <small> <?=e($row['buyer_name'])?> · <?=e($row['payment_method'])?> </small>
                </div>
                <div class="action-row">
                    <a href="<?=e(url('Mixed/order.php?id=' . $row['order_id']))?>">Order #<?=e($row['order_id'])?> </a>
                    <a class="btn btn-sm btn-primary" href="<?=e(url('Shishir/admin/payments.php?q=' . $row['order_id']))?>">Review</a>
                </div>
            </article>
            <?php endforeach;?>
<?php if (!$pending):?>
            <p class="muted mb-0">No pending payments.</p>
            <?php endif;?>
        </div>
    </section>
    <section class="panel" id="overdue-confirmed-orders">
        <h2 class="h4">Overdue confirmed orders</h2>
        <div class="queue-list">
            <?php foreach ($overdue as $row):?>
            <article>
                <div>
                    <span class="badge-warning">Confirmed</span>
                    <strong>Order #<?=e($row['order_id'])?> · <?=e($row['order_type'])?> </strong>
                    <small> <?=e($row['buyer_name'])?> · waiting since <?=e(date('d M Y', strtotime($row['order_date'])))?> </small>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="<?=e(url('Mixed/order.php?id=' . $row['order_id']))?>">View order</a>
            </article>
            <?php endforeach;?>
<?php if (!$overdue):?>
            <p class="muted mb-0">No overdue confirmed orders.</p>
            <?php endif;?>
        </div>
    </section>
    <section class="panel" id="expired-open-quotations">
        <h2 class="h4">Expired open quotations</h2>
        <div class="queue-list">
            <?php foreach ($expired as $row):?>
            <article>
                <div>
                    <span class="badge-danger">Expired</span>
                    <strong>Quotation #<?=e($row['quotation_id'])?> · <?=e($row['material_type'])?> </strong>
                    <small> <?=e($row['buyer_name'])?> · expired <?=e(date('d M Y', strtotime($row['expiry_date'])))?> </small>
                </div>
            </article>
            <?php endforeach;?>
<?php if (!$expired):?>
            <p class="muted mb-0">No expired open quotations.</p>
            <?php endif;?>
        </div>
    </section>
    <section class="panel" id="low-stock-batches">
        <h2 class="h4">Low-stock batches</h2>
        <div class="queue-list">
            <?php foreach ($lowStock as $row):$percent = (float) $row['total_quantity'] > 0 ? round((float) $row['available_quantity'] / (float) $row['total_quantity'] * 100) : 0;?>
            <article>
                <div>
                    <span class="badge-warning"> <?=e($percent)?>% left</span>
                    <strong>Batch #<?=e($row['batch_id'])?> · <?=e($row['material_type'])?> </strong>
                    <small> <?=e($row['supplier_name'])?> · <?=e($row['available_quantity'])?> / <?=e($row['total_quantity'])?> <?=e($row['unit_of_measure'])?> </small>
                </div>
            </article>
            <?php endforeach;?>
<?php if (!$lowStock):?>
            <p class="muted mb-0">No low-stock batches.</p>
            <?php endif;?>
        </div>
    </section>
    <section class="panel" id="zero-quantity-active-listings">
        <h2 class="h4">Zero-quantity active listings</h2>
        <div class="queue-list">
            <?php foreach ($zeroListings as $row):?>
            <article>
                <div>
                    <span class="badge-danger">Needs archive</span>
                    <strong>Listing #<?=e($row['listing_id'])?> · <?=e($row['material_type'])?> </strong>
                    <small> <?=e($row['supplier_name'])?> · listing <?=e($row['listed_quantity'])?> / batch <?=e($row['available_quantity'])?> <?=e($row['unit_of_measure'])?> </small>
                </div>
            </article>
            <?php endforeach;?>
<?php if (!$zeroListings):?>
            <p class="muted mb-0">No active zero-quantity listings.</p>
            <?php endif;?>
        </div>
    </section>
    <section class="panel" id="inactive-users-with-open-orders">
        <h2 class="h4">Inactive users with open orders</h2>
        <div class="queue-list">
            <?php foreach ($inactiveOpen as $row):?>
            <article>
                <div>
                    <span class="badge-danger">Inactive account</span>
                    <strong> <?=e($row['name'])?> · Order #<?=e($row['order_id'])?> </strong>
                    <small> <?=e($row['email'])?> · <?=e($row['order_status'])?> </small>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="<?=e(url('Mixed/order.php?id=' . $row['order_id']))?>">View order</a>
            </article>
            <?php endforeach;?>
<?php if (!$inactiveOpen):?>
            <p class="muted mb-0">No inactive users have open orders.</p>
            <?php endif;?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../../Mixed/includes/footer.php'; ?>
