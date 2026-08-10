<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/marketplace.php';

$expected = ['users','supplier','b2b_buyer','b2c_buyer','textile_batch','listing','b2b_listing','b2c_listing','quotation','orders','order_item','payment','stock_transaction'];
$pdo = db();
$database = $pdo->query('SELECT DATABASE()')->fetchColumn();
$statement = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
$statement->execute([$database]);
$tables = $statement->fetchAll(PDO::FETCH_COLUMN);
$missing = array_diff($expected, $tables);
if ($missing) throw new RuntimeException('Missing tables: ' . implode(', ', $missing));

$users = $pdo->query("SELECT u.user_id, u.email, u.password_hash,
    (EXISTS(SELECT 1 FROM supplier s WHERE s.user_id=u.user_id) +
     EXISTS(SELECT 1 FROM b2b_buyer b WHERE b.user_id=u.user_id) +
     EXISTS(SELECT 1 FROM b2c_buyer c WHERE c.user_id=u.user_id)) AS subtype_count
    FROM users u")->fetchAll();
foreach ($users as $user) {
    if ((int) $user['subtype_count'] !== 1) throw new RuntimeException("Subtype integrity failed for {$user['email']}");
}

$demo = $pdo->query("SELECT password_hash FROM users WHERE email='supplier@jhutledger.local'")->fetchColumn();
if (!$demo || !password_verify('Demo@123', $demo)) throw new RuntimeException('Demo password verification failed.');

$pdo->beginTransaction();
$email = 'rollback-test-' . bin2hex(random_bytes(4)) . '@example.test';
$insert = $pdo->prepare("INSERT INTO users (name,email,phone,password_hash,street,city,district,postal_code,user_status) VALUES (?,?,?,?,?,?,?,?, 'Active')");
$insert->execute(['Rollback Test', $email, '01700000000', password_hash('TestPass123', PASSWORD_DEFAULT), 'Test Street', 'Dhaka', 'Dhaka', '1200']);
$id = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO supplier (user_id) VALUES (?)')->execute([$id]);
$pdo->rollBack();
$check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?'); $check->execute([$email]);
if ((int) $check->fetchColumn() !== 0) throw new RuntimeException('Transaction rollback test failed.');

$fkBlocked = false;
try {
    $pdo->prepare('INSERT INTO supplier (user_id) VALUES (?)')->execute([999999999]);
} catch (PDOException $exception) {
    $fkBlocked = $exception->getCode() === '23000';
}
if (!$fkBlocked) throw new RuntimeException('Foreign key rejection test failed.');

$overAllocated = $pdo->query(
    "SELECT b.batch_id FROM textile_batch b
     JOIN listing l ON l.batch_id=b.batch_id AND l.status='Active'
     GROUP BY b.batch_id,b.available_quantity
     HAVING SUM(l.listed_quantity)>b.available_quantity"
)->fetchColumn();
if ($overAllocated) throw new RuntimeException("Active listings over-allocate batch #{$overAllocated}.");

$beforeStock = (float) $pdo->query('SELECT available_quantity FROM textile_batch WHERE batch_id=2')->fetchColumn();
$pdo->beginTransaction();
$testOrderId = createReservedOrder($pdo, 4, 3, 5.00, 260.00, 'B2C');
$duringStock = (float) $pdo->query('SELECT available_quantity FROM textile_batch WHERE batch_id=2')->fetchColumn();
if (abs($duringStock - ($beforeStock - 5.00)) > 0.0001) throw new RuntimeException('Order did not reserve batch stock.');
$listingAfterReservation = $pdo->query('SELECT listed_quantity,status FROM listing WHERE listing_id=3')->fetch();
if ((float)$listingAfterReservation['listed_quantity'] !== 295.0 || $listingAfterReservation['status'] !== 'Active') {
    throw new RuntimeException('Listing quantity/status update failed.');
}
$pdo->rollBack();
$afterStock = (float) $pdo->query('SELECT available_quantity FROM textile_batch WHERE batch_id=2')->fetchColumn();
$statement = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_id=?');$statement->execute([$testOrderId]);
if (abs($afterStock - $beforeStock) > 0.0001 || (int)$statement->fetchColumn() !== 0) {
    throw new RuntimeException('Marketplace transaction rollback failed.');
}
$pdo->beginTransaction();
createReservedOrder($pdo, 4, 3, 300.00, 260.00, 'B2C');
$soldOutStatus = $pdo->query('SELECT status FROM listing WHERE listing_id=3')->fetchColumn();
if ($soldOutStatus !== 'Sold Out') throw new RuntimeException('Sold-out listing status update failed.');
$pdo->rollBack();

echo "PASS: {$database} has 13 expected tables.\n";
echo 'PASS: ' . count($users) . " database users each have exactly one subtype.\n";
echo "PASS: password_verify, transaction rollback, and foreign key rejection work.\n";
echo "PASS: listing allocation and transactional stock reservation work.\n";
