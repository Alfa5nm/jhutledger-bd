<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

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

echo "PASS: {$database} has 13 expected tables.\n";
echo 'PASS: ' . count($users) . " seeded users each have exactly one subtype.\n";
echo "PASS: password_verify, transaction rollback, and foreign key rejection work.\n";

