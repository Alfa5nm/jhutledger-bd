<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/marketplace.php';

$expected = ['users','supplier','b2b_buyer','b2c_buyer','textile_batch','listing','b2b_listing','b2c_listing','quotation','orders','order_item','payment','stock_transaction'];
$pdo = db();
$database = $pdo->query('SELECT DATABASE()')->fetchColumn();
$statement = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
$statement->execute([$database]);
$tables = $statement->fetchAll(PDO::FETCH_COLUMN);
$missing = array_diff($expected, $tables);
if ($missing) {
    throw new RuntimeException('Missing tables: ' . implode(', ', $missing));
}

$users = $pdo->query("SELECT u.user_id, u.email, u.password_hash,
    (EXISTS(SELECT 1 FROM supplier s WHERE s.user_id=u.user_id) +
     EXISTS(SELECT 1 FROM b2b_buyer b WHERE b.user_id=u.user_id) +
     EXISTS(SELECT 1 FROM b2c_buyer c WHERE c.user_id=u.user_id)) AS subtype_count
    FROM users u")->fetchAll();
foreach ($users as $user) {
    if ((int) $user['subtype_count'] !== 1) {
        throw new RuntimeException("Subtype integrity failed for {$user['email']}");
    }
}

$demo = $pdo->query("SELECT password_hash FROM users WHERE email='supplier@jhutledger.local'")->fetchColumn();
if (!$demo || !password_verify('Demo@123', $demo)) {
    throw new RuntimeException('Demo password verification failed.');
}

$imageCases = [
    ['DENIM Fabric', '98% Cotton', 'Denim', 'denim.webp'],
    ['Cotton Knit', '100% Cotton', 'Cotton and knit', 'cotton-knit.webp'],
    ['Jute cloth', 'Natural fibre', 'Jute', 'jute.webp'],
    ['Nylon Fabric', 'Polyester blend', 'Synthetic textile', 'nylon-synthetic.webp'],
    ['Recycled Yarn', 'Reclaimed cotton', 'Recycled textile', 'recycled.webp'],
    ['Assorted cloth', 'Mixed blend', 'Mixed fabric', 'mixed-fabric.webp'],
    ['Uncatalogued material', 'Unknown', 'Textile stock', 'textile-default.webp'],
];
foreach ($imageCases as [$material, $composition, $category, $filename]) {
    $image = textileImage($material, $composition);
    if ($image['category'] !== $category || basename($image['src']) !== $filename || $image['alt'] === '') {
        throw new RuntimeException("Textile image mapping failed for {$material}.");
    }
    $publicPath = parse_url($image['src'], PHP_URL_PATH);
    $relativePath = substr($publicPath, strlen((string) appConfig('base_url')));
    if (!is_file(dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $relativePath))) {
        throw new RuntimeException("Textile image asset is missing: {$filename}.");
    }
}

$pdo->beginTransaction();
$email = 'rollback-test-' . bin2hex(random_bytes(4)) . '@example.test';
$insert = $pdo->prepare("INSERT INTO users (name,email,phone,password_hash,street,city,district,postal_code,user_status) VALUES (?,?,?,?,?,?,?,?, 'Active')");
$insert->execute(['Rollback Test', $email, '01700000000', password_hash('TestPass123', PASSWORD_DEFAULT), 'Test Street', 'Dhaka', 'Dhaka', '1200']);
$id = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO supplier (user_id) VALUES (?)')->execute([$id]);
$pdo->rollBack();

$reservation = stockMovement('RESERVED', 5);
$release = stockMovement('RESERVATION_RELEASED', 5);
$sold = stockMovement('SOLD', 5);
if ($reservation['class'] !== 'movement-out' || $release['class'] !== 'movement-in' || $sold['class'] !== 'movement-neutral') {
    throw new RuntimeException('Stock ledger movement semantics are incorrect.');
}
$knownOrderId = (int) $pdo->query('SELECT order_id FROM orders ORDER BY order_id LIMIT 1')->fetchColumn();
$knownOrder = findOrderDetails($pdo, $knownOrderId);
if (!$knownOrder || (int) $knownOrder['order_id'] !== $knownOrderId || (float) $knownOrder['total_amount'] <= 0) {
    throw new RuntimeException('Shared order details did not resolve immutable order data.');
}
$exceptionCounts = adminExceptionCounts($pdo);
$directPending = (int) $pdo->query("SELECT COUNT(*) FROM payment WHERE payment_status='Pending'")->fetchColumn();
if (($exceptionCounts['Pending payments'] ?? -1) !== $directPending || count($exceptionCounts) !== 6) {
    throw new RuntimeException('Admin exception counts do not match direct database queries.');
}
$check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');
$check->execute([$email]);
if ((int) $check->fetchColumn() !== 0) {
    throw new RuntimeException('Transaction rollback test failed.');
}

$fkBlocked = false;
try {
    $pdo->prepare('INSERT INTO supplier (user_id) VALUES (?)')->execute([999999999]);
} catch (PDOException $exception) {
    $fkBlocked = $exception->getCode() === '23000';
}
if (!$fkBlocked) {
    throw new RuntimeException('Foreign key rejection test failed.');
}

$overAllocated = $pdo->query(
    "SELECT b.batch_id FROM textile_batch b
     JOIN listing l ON l.batch_id=b.batch_id AND l.status='Active'
     GROUP BY b.batch_id,b.available_quantity
     HAVING SUM(l.listed_quantity)>b.available_quantity"
)->fetchColumn();
if ($overAllocated) {
    throw new RuntimeException("Active listings over-allocate batch #{$overAllocated}.");
}

$beforeStock = (float) $pdo->query('SELECT available_quantity FROM textile_batch WHERE batch_id=2')->fetchColumn();
$beforeListingQuantity = (float) $pdo->query('SELECT listed_quantity FROM listing WHERE listing_id=3')->fetchColumn();
$pdo->beginTransaction();
$testOrderId = createReservedOrder($pdo, 4, 3, 5.00, 260.00, 'B2C');
$duringStock = (float) $pdo->query('SELECT available_quantity FROM textile_batch WHERE batch_id=2')->fetchColumn();
if (abs($duringStock - ($beforeStock - 5.00)) > 0.0001) {
    throw new RuntimeException('Order did not reserve batch stock.');
}
$listingAfterReservation = $pdo->query('SELECT listed_quantity,status FROM listing WHERE listing_id=3')->fetch();
if (abs((float) $listingAfterReservation['listed_quantity'] - ($beforeListingQuantity - 5.00)) > 0.0001
    || $listingAfterReservation['status'] !== 'Active') {
    throw new RuntimeException('Listing quantity/status update failed.');
}
$pdo->rollBack();
$afterStock = (float) $pdo->query('SELECT available_quantity FROM textile_batch WHERE batch_id=2')->fetchColumn();
$statement = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_id=?');
$statement->execute([$testOrderId]);
if (abs($afterStock - $beforeStock) > 0.0001 || (int)$statement->fetchColumn() !== 0) {
    throw new RuntimeException('Marketplace transaction rollback failed.');
}
$pdo->beginTransaction();
$sellableQuantity = (float) $pdo->query(
    'SELECT LEAST(l.listed_quantity,b.available_quantity) FROM listing l
     JOIN textile_batch b ON b.batch_id=l.batch_id WHERE l.listing_id=3'
)->fetchColumn();
if ($sellableQuantity <= 0) {
    throw new RuntimeException('Sold-out test listing has no available quantity.');
}
createReservedOrder($pdo, 4, 3, $sellableQuantity, 260.00, 'B2C');
$soldOutStatus = $pdo->query('SELECT status FROM listing WHERE listing_id=3')->fetchColumn();
if ($soldOutStatus !== 'Sold Out') {
    throw new RuntimeException('Sold-out listing status update failed.');
}
$pdo->rollBack();

$pdo->beginTransaction();
$workflowOrderId = createReservedOrder($pdo, 4, 3, 5.00, 260.00, 'B2C');
submitPayment($pdo, 4, $workflowOrderId, 'Mobile Banking');
$payment = $pdo->query("SELECT * FROM payment WHERE order_id={$workflowOrderId}")->fetch();
if (!$payment || $payment['payment_status'] !== 'Pending' || abs((float) $payment['amount'] - 1300.00) > 0.0001) {
    throw new RuntimeException('Server-calculated payment submission failed.');
}
$duplicateBlocked = false;
try {
    submitPayment($pdo, 4, $workflowOrderId, 'Cash');
} catch (RuntimeException) {
    $duplicateBlocked = true;
}
if (!$duplicateBlocked) {
    throw new RuntimeException('Duplicate active payment was not blocked.');
}
$pdo->prepare('UPDATE payment SET amount = amount + 1 WHERE payment_id = ?')->execute([$payment['payment_id']]);
$mismatchedPaymentBlocked = false;
try {
    reviewPayment($pdo, (int) $payment['payment_id'], 'Paid');
} catch (RuntimeException) {
    $mismatchedPaymentBlocked = true;
}
if (!$mismatchedPaymentBlocked) {
    throw new RuntimeException('Mismatched payment amount was not blocked.');
}
$pdo->prepare('UPDATE payment SET amount = ? WHERE payment_id = ?')->execute([1300.00, $payment['payment_id']]);
reviewPayment($pdo, (int) $payment['payment_id'], 'Paid');
advanceOrderStatus($pdo, $workflowOrderId, 2, 'Processing');
advanceOrderStatus($pdo, $workflowOrderId, 2, 'Completed');
$completed = $pdo->query("SELECT order_status FROM orders WHERE order_id={$workflowOrderId}")->fetchColumn();
$soldCount = $pdo->query("SELECT COUNT(*) FROM stock_transaction WHERE order_id={$workflowOrderId} AND transaction_type='SOLD'")->fetchColumn();
if ($completed !== 'Completed' || (int) $soldCount !== 1) {
    throw new RuntimeException('Order completion or SOLD ledger entry failed.');
}
$repeatCompletionBlocked = false;
try {
    advanceOrderStatus($pdo, $workflowOrderId, 2, 'Completed');
} catch (RuntimeException) {
    $repeatCompletionBlocked = true;
}
if (!$repeatCompletionBlocked) {
    throw new RuntimeException('Repeated order completion was not blocked.');
}
$lateCancellationBlocked = false;
try {
    cancelOrder($pdo, $workflowOrderId, 'b2c', 4);
} catch (RuntimeException) {
    $lateCancellationBlocked = true;
}
if (!$lateCancellationBlocked) {
    throw new RuntimeException('Completed order cancellation was not blocked.');
}
$returnBatchBefore = (float) $pdo->query('SELECT available_quantity FROM textile_batch WHERE batch_id=2')->fetchColumn();
$returnListingBefore = (float) $pdo->query('SELECT listed_quantity FROM listing WHERE listing_id=3')->fetchColumn();
returnOrder($pdo, $workflowOrderId, 'b2c', 4);
$returnedCount = (int) $pdo->query("SELECT COUNT(*) FROM stock_transaction WHERE order_id={$workflowOrderId} AND transaction_type='RETURNED'")->fetchColumn();
$returnedPayment = $pdo->query("SELECT payment_status FROM payment WHERE order_id={$workflowOrderId}")->fetchColumn();
$returnBatchAfter = (float) $pdo->query('SELECT available_quantity FROM textile_batch WHERE batch_id=2')->fetchColumn();
$returnListingAfter = (float) $pdo->query('SELECT listed_quantity FROM listing WHERE listing_id=3')->fetchColumn();
if ($returnedCount !== 1 || $returnedPayment !== 'Refunded'
    || abs($returnBatchAfter - ($returnBatchBefore + 5)) > 0.0001
    || abs($returnListingAfter - ($returnListingBefore + 5)) > 0.0001) {
    throw new RuntimeException('Return did not restore stock and refund the paid payment.');
}
$duplicateReturnBlocked = false;
try {
    returnOrder($pdo, $workflowOrderId, 'b2c', 4);
} catch (RuntimeException) {
    $duplicateReturnBlocked = true;
}
if (!$duplicateReturnBlocked) {
    throw new RuntimeException('Duplicate full-order return was not blocked.');
}
$reordered = repeatPurchase($pdo, $workflowOrderId, 4, 'b2c');
if ($reordered['type'] !== 'order' || $reordered['id'] <= 0) {
    throw new RuntimeException('B2C repeat purchase failed.');
}
$pdo->rollBack();

$batchBeforeCancel = (float) $pdo->query('SELECT available_quantity FROM textile_batch WHERE batch_id=2')->fetchColumn();
$listingBeforeCancel = (float) $pdo->query('SELECT listed_quantity FROM listing WHERE listing_id=3')->fetchColumn();
$pdo->beginTransaction();
$cancelOrderId = createReservedOrder($pdo, 4, 3, 5.00, 260.00, 'B2C');
submitPayment($pdo, 4, $cancelOrderId, 'Card');
$cancelPaymentId = (int) $pdo->query("SELECT payment_id FROM payment WHERE order_id={$cancelOrderId}")->fetchColumn();
reviewPayment($pdo, $cancelPaymentId, 'Paid');
cancelOrder($pdo, $cancelOrderId, 'b2c', 4);
$cancelled = $pdo->query("SELECT order_status FROM orders WHERE order_id={$cancelOrderId}")->fetchColumn();
$paymentStatus = $pdo->query("SELECT payment_status FROM payment WHERE order_id={$cancelOrderId}")->fetchColumn();
$batchAfterCancel = (float) $pdo->query('SELECT available_quantity FROM textile_batch WHERE batch_id=2')->fetchColumn();
$listingAfterCancel = (float) $pdo->query('SELECT listed_quantity FROM listing WHERE listing_id=3')->fetchColumn();
$released = $pdo->query("SELECT COUNT(*) FROM stock_transaction WHERE order_id={$cancelOrderId} AND transaction_type='RESERVATION_RELEASED'")->fetchColumn();
if ($cancelled !== 'Cancelled' || $paymentStatus !== 'Refunded' || (int) $released !== 1
    || abs($batchAfterCancel - $batchBeforeCancel) > 0.0001
    || abs($listingAfterCancel - $listingBeforeCancel) > 0.0001) {
    throw new RuntimeException('Cancellation did not restore stock, payment, and ledger state.');
}
$pdo->rollBack();

$filters = ['date_from' => '', 'date_to' => '', 'order_type' => ''];
$adminReport = salesReport($pdo, null, $filters);
$supplierReport = salesReport($pdo, 2, $filters);
$directRevenue = (float) $pdo->query(
    "SELECT COALESCE(SUM(oi.quantity * oi.selling_price),0) FROM orders o
     JOIN order_item oi ON oi.order_id=o.order_id WHERE o.order_status='Completed'"
)->fetchColumn();
if (abs((float) $adminReport['summary']['revenue'] - $directRevenue) > 0.0001
    || (float) $supplierReport['summary']['revenue'] > (float) $adminReport['summary']['revenue']) {
    throw new RuntimeException('Sales report totals or supplier scoping failed.');
}

$projection = pricingProjection(100, 10, 20);
if (abs($projection['suggested_price'] - 125) > 0.0001 || abs($projection['projected_profit'] - 250) > 0.0001) {
    throw new RuntimeException('Margin-on-selling-price calculation failed.');
}
$invalidMarginBlocked = false;
try {
    pricingProjection(100, 10, 100);
} catch (RuntimeException) {
    $invalidMarginBlocked = true;
}
if (!$invalidMarginBlocked) {
    throw new RuntimeException('Invalid pricing margin was not blocked.');
}
$sustainabilityFilters = ['date_from' => '','date_to' => '','channel' => '','condition' => '','material' => '','unit' => ''];
$adminSustainability = sustainabilityReport($pdo, null, $sustainabilityFilters);
$supplierSustainability = sustainabilityReport($pdo, 2, $sustainabilityFilters);
$adminRecovered = array_sum(array_map(fn ($row) => (float)$row['recovered_value'], $adminSustainability['units']));
$supplierRecovered = array_sum(array_map(fn ($row) => (float)$row['recovered_value'], $supplierSustainability['units']));
if ($supplierRecovered > $adminRecovered + 0.0001) {
    throw new RuntimeException('Sustainability supplier scoping failed.');
}

$pdo->beginTransaction();
$scopeEmail = 'report-scope-' . bin2hex(random_bytes(4)) . '@example.test';
$insert->execute(['Report Scope Supplier', $scopeEmail, '01700000001', password_hash('TestPass123', PASSWORD_DEFAULT), 'Scope Street', 'Dhaka', 'Dhaka', '1200']);
$scopeSupplierId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO supplier (user_id) VALUES (?)')->execute([$scopeSupplierId]);
$pdo->prepare(
    "INSERT INTO textile_batch
     (supplier_id,material_type,composition,color,gsm,`condition`,total_quantity,available_quantity,average_cost,storage_location,entry_date,unit_of_measure,status)
     VALUES (?, 'Scope Material', 'Test', 'Natural', 100, 'Surplus', 10, 10, 20, 'Scope Store', CURDATE(), 'kg', 'Active')"
)->execute([$scopeSupplierId]);
$scopeBatchId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO listing(batch_id,listed_quantity,status) VALUES (?,10,'Active')")->execute([$scopeBatchId]);
$scopeListingId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO b2c_listing(listing_id,bundle_size,fixed_unit_price) VALUES (?,5,100)')->execute([$scopeListingId]);
$scopeOrderId = createReservedOrder($pdo, 4, $scopeListingId, 5, 100, 'B2C');
advanceOrderStatus($pdo, $scopeOrderId, $scopeSupplierId, 'Processing');
advanceOrderStatus($pdo, $scopeOrderId, $scopeSupplierId, 'Completed');
$scopedAdminReport = salesReport($pdo, null, $filters);
$originalSupplierReport = salesReport($pdo, 2, $filters);
if (abs((float) $scopedAdminReport['summary']['revenue'] - ((float) $adminReport['summary']['revenue'] + 500.00)) > 0.0001
    || abs((float) $originalSupplierReport['summary']['revenue'] - (float) $supplierReport['summary']['revenue']) > 0.0001) {
    throw new RuntimeException('Supplier report exposed another supplier’s completed sale.');
}
$pdo->rollBack();

echo "PASS: {$database} has 13 expected tables.\n";
echo 'PASS: ' . count($users) . " database users each have exactly one subtype.\n";
echo "PASS: password_verify, transaction rollback, and foreign key rejection work.\n";
echo "PASS: listing allocation and transactional stock reservation work.\n";
echo "PASS: payment review, order transitions, cancellation restoration, and reports work.\n";
echo "PASS: order detail, stock movement, and admin exception derivation work.\n";
echo "PASS: returns, repeat purchase, pricing projections, and sustainability scoping work.\n";
echo "PASS: context-aware textile image mappings and local assets work.\n";
