<?php

declare(strict_types=1);

function transactional(PDO $pdo, callable $callback): mixed
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $result = $callback();
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function lockListing(PDO $pdo, int $listingId, string $type): array
{
    $subtype = $type === 'B2B' ? 'b2b_listing' : 'b2c_listing';
    $sql = "SELECT l.listing_id, l.batch_id, l.listed_quantity, l.status AS listing_status,
                   b.supplier_id, b.available_quantity, b.average_cost, b.status AS batch_status,
                   x.*
            FROM listing l
            JOIN textile_batch b ON b.batch_id = l.batch_id
            JOIN {$subtype} x ON x.listing_id = l.listing_id
            WHERE l.listing_id = ? FOR UPDATE";
    $statement = $pdo->prepare($sql);
    $statement->execute([$listingId]);
    $listing = $statement->fetch();
    if (!$listing) {
        throw new RuntimeException('The selected listing does not exist.');
    }
    return $listing;
}

function reservableQuantity(array $listing): float
{
    return max(0, min((float) $listing['listed_quantity'], (float) $listing['available_quantity']));
}

function createReservedOrder(
    PDO $pdo,
    int $buyerId,
    int $listingId,
    float $quantity,
    float $unitPrice,
    string $orderType,
    ?int $quotationId = null
): int {
    $listing = lockListing($pdo, $listingId, $orderType);
    if ($listing['listing_status'] !== 'Active' || $listing['batch_status'] !== 'Active') {
        throw new RuntimeException('This listing is no longer available.');
    }
    if ($quantity <= 0 || $quantity > reservableQuantity($listing)) {
        throw new RuntimeException('The requested quantity is no longer available.');
    }

    $total = round($quantity * $unitPrice, 2);
    $cost = (float) $listing['average_cost'];
    $grossProfit = round(($unitPrice - $cost) * $quantity, 2);

    $statement = $pdo->prepare(
        'INSERT INTO orders (buyer_id, quotation_id, order_type, order_status, total_amount)
         VALUES (?, ?, ?, \'Confirmed\', ?)'
    );
    $statement->execute([$buyerId, $quotationId, $orderType, $total]);
    $orderId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO order_item (order_id, line_no, listing_id, quantity, selling_price, average_cost_snapshot, gross_profit)
         VALUES (?, 1, ?, ?, ?, ?, ?)'
    )->execute([$orderId, $listingId, $quantity, $unitPrice, $cost, $grossProfit]);

    $pdo->prepare(
        "UPDATE listing
         SET status = IF(listed_quantity - ? <= 0, 'Sold Out', status),
             listed_quantity = listed_quantity - ?
         WHERE listing_id = ?"
    )->execute([$quantity, $quantity, $listingId]);
    $pdo->prepare('UPDATE textile_batch SET available_quantity = available_quantity - ? WHERE batch_id = ?')
        ->execute([$quantity, $listing['batch_id']]);
    $pdo->prepare(
        "INSERT INTO stock_transaction (batch_id, order_id, quantity, transaction_type, remarks)
         VALUES (?, ?, ?, 'RESERVED', ?)"
    )->execute([
        $listing['batch_id'], $orderId, $quantity,
        "Reserved for {$orderType} order #{$orderId}",
    ]);

    return $orderId;
}

function placeB2cOrder(PDO $pdo, int $buyerId, int $listingId, float $quantity): int
{
    return transactional($pdo, function () use ($pdo, $buyerId, $listingId, $quantity): int {
        $listing = lockListing($pdo, $listingId, 'B2C');
        $bundle = (float) $listing['bundle_size'];
        if ($bundle <= 0 || $quantity < $bundle || abs(fmod($quantity, $bundle)) > 0.0001) {
            throw new RuntimeException("Quantity must be purchased in bundles of {$bundle}.");
        }
        $orderId = createReservedOrder(
            $pdo, $buyerId, $listingId, $quantity, (float) $listing['fixed_unit_price'], 'B2C'
        );
        return $orderId;
    });
}

function acceptQuotation(PDO $pdo, int $quotationId, string $actorRole, int $actorId): int
{
    return transactional($pdo, function () use ($pdo, $quotationId, $actorRole, $actorId): int {
        $statement = $pdo->prepare(
            'SELECT q.*, b.supplier_id
             FROM quotation q
             JOIN listing l ON l.listing_id = q.listing_id
             JOIN textile_batch b ON b.batch_id = l.batch_id
             WHERE q.quotation_id = ? FOR UPDATE'
        );
        $statement->execute([$quotationId]);
        $quotation = $statement->fetch();
        if (!$quotation || $quotation['expiry_date'] < date('Y-m-d')) {
            throw new RuntimeException('This quotation is missing or has expired.');
        }

        if ($actorRole === 'supplier') {
            if ((int) $quotation['supplier_id'] !== $actorId || $quotation['status'] !== 'Pending') {
                throw new RuntimeException('This quotation cannot be accepted by your account.');
            }
            $finalPrice = (float) $quotation['proposed_price'];
        } elseif ($actorRole === 'b2b') {
            if ((int) $quotation['buyer_id'] !== $actorId || $quotation['status'] !== 'Countered') {
                throw new RuntimeException('This counter-offer cannot be accepted by your account.');
            }
            $finalPrice = (float) $quotation['counter_price'];
        } else {
            throw new RuntimeException('Invalid quotation action.');
        }

        $orderId = createReservedOrder(
            $pdo,
            (int) $quotation['buyer_id'],
            (int) $quotation['listing_id'],
            (float) $quotation['requested_quantity'],
            $finalPrice,
            'B2B',
            $quotationId
        );
        $pdo->prepare("UPDATE quotation SET status = 'Accepted', final_price = ? WHERE quotation_id = ?")
            ->execute([$finalPrice, $quotationId]);
        return $orderId;
    });
}

function lockOrder(PDO $pdo, int $orderId): array
{
    $statement = $pdo->prepare(
        'SELECT o.*, oi.listing_id, oi.quantity, oi.selling_price, oi.gross_profit,
                l.batch_id, l.status AS listing_status, b.supplier_id, b.status AS batch_status
         FROM orders o
         JOIN order_item oi ON oi.order_id = o.order_id AND oi.line_no = 1
         JOIN listing l ON l.listing_id = oi.listing_id
         JOIN textile_batch b ON b.batch_id = l.batch_id
         WHERE o.order_id = ? FOR UPDATE'
    );
    $statement->execute([$orderId]);
    $order = $statement->fetch();
    if (!$order) {
        throw new RuntimeException('The selected order does not exist.');
    }
    return $order;
}

function actorCanManageOrder(array $order, string $actorRole, int $actorId): bool
{
    if (in_array($actorRole, ['b2b', 'b2c'], true)) {
        return (int) $order['buyer_id'] === $actorId
            && strtolower((string) $order['order_type']) === $actorRole;
    }
    return $actorRole === 'supplier' && (int) $order['supplier_id'] === $actorId;
}

function advanceOrderStatus(PDO $pdo, int $orderId, int $supplierId, string $targetStatus): void
{
    transactional($pdo, function () use ($pdo, $orderId, $supplierId, $targetStatus): void {
        $order = lockOrder($pdo, $orderId);
        if ((int) $order['supplier_id'] !== $supplierId) {
            throw new RuntimeException('This order does not belong to your inventory.');
        }

        $allowed = ['Confirmed' => 'Processing', 'Processing' => 'Completed'];
        if (($allowed[$order['order_status']] ?? null) !== $targetStatus) {
            throw new RuntimeException('That order status transition is not allowed.');
        }

        $statement = $pdo->prepare('UPDATE orders SET order_status = ? WHERE order_id = ? AND order_status = ?');
        $statement->execute([$targetStatus, $orderId, $order['order_status']]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The order changed while you were updating it.');
        }

        if ($targetStatus === 'Completed') {
            $statement = $pdo->prepare(
                "INSERT INTO stock_transaction (batch_id, order_id, quantity, transaction_type, remarks)
                 SELECT ?, ?, ?, 'SOLD', ?
                 WHERE NOT EXISTS (
                     SELECT 1 FROM stock_transaction WHERE order_id = ? AND transaction_type = 'SOLD'
                 )"
            );
            $statement->execute([
                $order['batch_id'], $orderId, $order['quantity'],
                "Completed sale for {$order['order_type']} order #{$orderId}", $orderId,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('A completed-sale transaction already exists for this order.');
            }
        }
    });
}

function cancelOrder(PDO $pdo, int $orderId, string $actorRole, int $actorId): void
{
    transactional($pdo, function () use ($pdo, $orderId, $actorRole, $actorId): void {
        $order = lockOrder($pdo, $orderId);
        if (!actorCanManageOrder($order, $actorRole, $actorId)) {
            throw new RuntimeException('You are not allowed to cancel this order.');
        }
        if (!in_array($order['order_status'], ['Pending', 'Confirmed'], true)) {
            throw new RuntimeException('Only pending or confirmed orders can be cancelled.');
        }

        $statement = $pdo->prepare(
            "UPDATE orders SET order_status = 'Cancelled'
             WHERE order_id = ? AND order_status IN ('Pending', 'Confirmed')"
        );
        $statement->execute([$orderId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The order changed while you were cancelling it.');
        }

        $pdo->prepare(
            "UPDATE listing
             SET listed_quantity = listed_quantity + ?,
                 status = IF(status = 'Sold Out' AND ? = 'Active', 'Active', status)
             WHERE listing_id = ?"
        )->execute([$order['quantity'], $order['batch_status'], $order['listing_id']]);
        $pdo->prepare('UPDATE textile_batch SET available_quantity = available_quantity + ? WHERE batch_id = ?')
            ->execute([$order['quantity'], $order['batch_id']]);
        $pdo->prepare(
            "INSERT INTO stock_transaction (batch_id, order_id, quantity, transaction_type, remarks)
             VALUES (?, ?, ?, 'RESERVATION_RELEASED', ?)"
        )->execute([
            $order['batch_id'], $orderId, $order['quantity'],
            "Released reservation for cancelled {$order['order_type']} order #{$orderId}",
        ]);
        $pdo->prepare(
            "UPDATE payment
             SET payment_status = CASE
                    WHEN payment_status = 'Paid' THEN 'Refunded'
                    WHEN payment_status = 'Pending' THEN 'Failed'
                    ELSE payment_status
                 END
             WHERE order_id = ?"
        )->execute([$orderId]);
    });
}

function submitPayment(PDO $pdo, int $buyerId, int $orderId, string $method): void
{
    $methods = ['Cash', 'Bank Transfer', 'Mobile Banking', 'Card'];
    if (!in_array($method, $methods, true)) {
        throw new RuntimeException('Select a valid payment method.');
    }

    transactional($pdo, function () use ($pdo, $buyerId, $orderId, $method): void {
        $order = lockOrder($pdo, $orderId);
        if ((int) $order['buyer_id'] !== $buyerId) {
            throw new RuntimeException('This order does not belong to your account.');
        }
        if (!in_array($order['order_status'], ['Confirmed', 'Processing'], true)) {
            throw new RuntimeException('Payment is only available for confirmed or processing orders.');
        }

        $statement = $pdo->prepare('SELECT * FROM payment WHERE order_id = ? FOR UPDATE');
        $statement->execute([$orderId]);
        $payment = $statement->fetch();
        if (!$payment) {
            $pdo->prepare(
                "INSERT INTO payment (order_id, amount, payment_method, payment_status)
                 VALUES (?, ?, ?, 'Pending')"
            )->execute([$orderId, $order['total_amount'], $method]);
            return;
        }
        if ($payment['payment_status'] !== 'Failed') {
            throw new RuntimeException('This order already has an active payment record.');
        }
        $pdo->prepare(
            "UPDATE payment
             SET amount = ?, payment_method = ?, payment_status = 'Pending', payment_date = NULL
             WHERE payment_id = ?"
        )->execute([$order['total_amount'], $method, $payment['payment_id']]);
    });
}

function reviewPayment(PDO $pdo, int $paymentId, string $status): void
{
    if (!in_array($status, ['Paid', 'Failed'], true)) {
        throw new RuntimeException('Select a valid payment decision.');
    }

    transactional($pdo, function () use ($pdo, $paymentId, $status): void {
        $statement = $pdo->prepare(
            'SELECT p.*, o.order_status, o.total_amount FROM payment p
             JOIN orders o ON o.order_id = p.order_id
             WHERE p.payment_id = ? FOR UPDATE'
        );
        $statement->execute([$paymentId]);
        $payment = $statement->fetch();
        if (!$payment || $payment['payment_status'] !== 'Pending') {
            throw new RuntimeException('Only pending payments can be reviewed.');
        }
        if ($payment['order_status'] === 'Cancelled') {
            throw new RuntimeException('A cancelled order cannot be marked as paid.');
        }
        if (abs((float) $payment['amount'] - (float) $payment['total_amount']) > 0.009) {
            throw new RuntimeException('The payment amount does not match the order total.');
        }

        $pdo->prepare(
            'UPDATE payment SET payment_status = ?, payment_date = ? WHERE payment_id = ?'
        )->execute([$status, $status === 'Paid' ? date('Y-m-d H:i:s') : null, $paymentId]);
    });
}

function reportFilters(array $input): array
{
    $from = trim((string) ($input['date_from'] ?? ''));
    $to = trim((string) ($input['date_to'] ?? ''));
    $type = strtoupper(trim((string) ($input['order_type'] ?? '')));
    return [
        'date_from' => validDate($from) ? $from : '',
        'date_to' => validDate($to) ? $to : '',
        'order_type' => in_array($type, ['B2B', 'B2C'], true) ? $type : '',
    ];
}

function salesReport(PDO $pdo, ?int $supplierId, array $filters): array
{
    $where = ["o.order_status = 'Completed'"];
    $params = [];
    if ($supplierId !== null) {
        $where[] = 'b.supplier_id = ?';
        $params[] = $supplierId;
    }
    if ($filters['date_from'] !== '') {
        $where[] = 'DATE(o.order_date) >= ?';
        $params[] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $where[] = 'DATE(o.order_date) <= ?';
        $params[] = $filters['date_to'];
    }
    if ($filters['order_type'] !== '') {
        $where[] = 'o.order_type = ?';
        $params[] = $filters['order_type'];
    }

    $from = ' FROM orders o
              JOIN order_item oi ON oi.order_id = o.order_id
              JOIN listing l ON l.listing_id = oi.listing_id
              JOIN textile_batch b ON b.batch_id = l.batch_id
              JOIN users buyer ON buyer.user_id = o.buyer_id
              LEFT JOIN payment p ON p.order_id = o.order_id
              WHERE ' . implode(' AND ', $where);

    $statement = $pdo->prepare(
        'SELECT COUNT(DISTINCT o.order_id) AS order_count,
                COALESCE(SUM(oi.quantity), 0) AS quantity_sold,
                COALESCE(SUM(oi.quantity * oi.selling_price), 0) AS revenue,
                COALESCE(SUM(oi.gross_profit), 0) AS gross_profit' . $from
    );
    $statement->execute($params);
    $summary = $statement->fetch();

    $statement = $pdo->prepare(
        'SELECT b.material_type, COUNT(DISTINCT o.order_id) AS order_count,
                SUM(oi.quantity) AS quantity_sold,
                SUM(oi.quantity * oi.selling_price) AS revenue,
                SUM(oi.gross_profit) AS gross_profit' . $from . '
         GROUP BY b.material_type ORDER BY revenue DESC, b.material_type'
    );
    $statement->execute($params);
    $materials = $statement->fetchAll();

    $statement = $pdo->prepare(
        "SELECT COALESCE(p.payment_status, 'Not submitted') AS payment_status,
                COUNT(DISTINCT o.order_id) AS order_count" . $from . '
         GROUP BY COALESCE(p.payment_status, \'Not submitted\') ORDER BY payment_status'
    );
    $statement->execute($params);
    $payments = $statement->fetchAll();

    $statement = $pdo->prepare(
        'SELECT o.order_id, o.order_date, o.order_type, buyer.name AS buyer_name,
                b.material_type, oi.quantity, oi.selling_price,
                oi.quantity * oi.selling_price AS revenue, oi.gross_profit,
                COALESCE(p.payment_status, \'Not submitted\') AS payment_status' . $from . '
         ORDER BY o.order_date DESC, o.order_id DESC'
    );
    $statement->execute($params);

    return [
        'summary' => $summary,
        'materials' => $materials,
        'payments' => $payments,
        'orders' => $statement->fetchAll(),
    ];
}

function findOrderDetails(PDO $pdo, int $orderId): ?array
{
    $statement = $pdo->prepare(
        'SELECT o.*, oi.line_no, oi.listing_id, oi.quantity, oi.selling_price,
                oi.average_cost_snapshot, oi.gross_profit,
                b.batch_id, b.material_type, b.composition, b.color, b.gsm,
                b.`condition`, b.unit_of_measure, b.storage_location, b.supplier_id,
                buyer.name AS buyer_name, buyer.email AS buyer_email, buyer.phone AS buyer_phone,
                buyer.street AS buyer_street, buyer.city AS buyer_city, buyer.district AS buyer_district,
                supplier.name AS supplier_name, supplier.email AS supplier_email, supplier.phone AS supplier_phone,
                q.proposed_price, q.counter_price, q.final_price, q.expiry_date,
                p.payment_id, p.amount AS payment_amount, p.payment_method, p.payment_status, p.payment_date
         FROM orders o
         JOIN order_item oi ON oi.order_id = o.order_id AND oi.line_no = 1
         JOIN listing l ON l.listing_id = oi.listing_id
         JOIN textile_batch b ON b.batch_id = l.batch_id
         JOIN users buyer ON buyer.user_id = o.buyer_id
         JOIN users supplier ON supplier.user_id = b.supplier_id
         LEFT JOIN quotation q ON q.quotation_id = o.quotation_id
         LEFT JOIN payment p ON p.order_id = o.order_id
         WHERE o.order_id = ?'
    );
    $statement->execute([$orderId]);
    return $statement->fetch() ?: null;
}

function canViewOrder(array $order, array $user): bool
{
    if ($user['role'] === 'admin') {
        return true;
    }
    if ($user['role'] === 'supplier') {
        return (int) $order['supplier_id'] === (int) $user['user_id'];
    }
    return in_array($user['role'], ['b2b', 'b2c'], true)
        && (int) $order['buyer_id'] === (int) $user['user_id']
        && strtolower((string) $order['order_type']) === $user['role'];
}

function accessibleOrder(PDO $pdo, int $orderId): array
{
    $order = findOrderDetails($pdo, $orderId);
    $user = currentUser();
    if (!$order || !$user || !canViewOrder($order, $user)) {
        http_response_code(404);
        exit('Order not found.');
    }
    return $order;
}

function stockMovement(string $type, float $quantity): array
{
    return match ($type) {
        'STOCK_ADDED', 'RESERVATION_RELEASED', 'RETURNED', 'ADJUSTMENT_IN'
            => ['class' => 'movement-in', 'symbol' => '+', 'quantity' => $quantity, 'effect' => 'Stock increased'],
        'RESERVED', 'ADJUSTMENT_OUT'
            => ['class' => 'movement-out', 'symbol' => '−', 'quantity' => $quantity, 'effect' => 'Available stock reduced'],
        'SOLD' => ['class' => 'movement-neutral', 'symbol' => '•', 'quantity' => $quantity, 'effect' => 'Reservation converted to sale'],
        default => ['class' => 'movement-neutral', 'symbol' => '•', 'quantity' => $quantity, 'effect' => 'Ledger record'],
    };
}

function adminExceptionCounts(PDO $pdo): array
{
    return [
        'Pending payments' => (int) $pdo->query("SELECT COUNT(*) FROM payment WHERE payment_status='Pending'")->fetchColumn(),
        'Overdue confirmed orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='Confirmed' AND order_date < NOW() - INTERVAL 2 DAY")->fetchColumn(),
        'Expired open quotations' => (int) $pdo->query("SELECT COUNT(*) FROM quotation WHERE status IN('Pending','Countered') AND expiry_date < CURDATE()")->fetchColumn(),
        'Low-stock batches' => (int) $pdo->query("SELECT COUNT(*) FROM textile_batch WHERE status='Active' AND total_quantity>0 AND available_quantity/total_quantity<=0.20")->fetchColumn(),
        'Zero-quantity active listings' => (int) $pdo->query("SELECT COUNT(*) FROM listing l JOIN textile_batch b ON b.batch_id=l.batch_id WHERE l.status='Active' AND (l.listed_quantity<=0 OR b.available_quantity<=0)")->fetchColumn(),
        'Inactive users with open orders' => (int) $pdo->query("SELECT COUNT(DISTINCT u.user_id) FROM users u JOIN orders o ON o.buyer_id=u.user_id WHERE u.user_status='Inactive' AND o.order_status IN('Pending','Confirmed','Processing')")->fetchColumn(),
    ];
}
