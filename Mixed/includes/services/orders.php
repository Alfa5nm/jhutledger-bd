<?php

declare(strict_types=1);

function lockOrder(PDO $pdo, int $orderId): array
{
    $statement = $pdo->prepare(
        'SELECT o.*, oi.listing_id, oi.quantity, oi.selling_price, oi.gross_profit,
                l.batch_id, l.status AS listing_status, b.supplier_id, b.status AS batch_status
         FROM orders o
         JOIN order_item oi ON oi.order_id = o.order_id AND oi.line_no = 1
         JOIN listing l ON l.listing_id = oi.listing_id
         JOIN textile_batch b ON b.batch_id = l.batch_id
         WHERE o.order_id = ? FOR UPDATE',
    );
    $statement->execute([$orderId]);
    $order = $statement->fetch();
    if (!$order) {
        throw new RuntimeException("The selected order does not exist.");
    }
    return $order;
}

function actorCanManageOrder(array $order, string $actorRole, int $actorId): bool
{
    if ($actorRole === "admin") {
        return true;
    }
    if (in_array($actorRole, ["b2b", "b2c"], true)) {
        return (int) $order["buyer_id"] === $actorId && strtolower((string) $order["order_type"]) === $actorRole;
    }
    return $actorRole === "supplier" && (int) $order["supplier_id"] === $actorId;
}

function orderHasReturn(PDO $pdo, int $orderId, bool $lock = false): bool
{
    $sql = "SELECT transaction_id FROM stock_transaction
            WHERE order_id = ? AND transaction_type = 'RETURNED' LIMIT 1";
    if ($lock) {
        $sql .= " FOR UPDATE";
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$orderId]);
    return (bool) $statement->fetchColumn();
}

function displayOrderStatus(array $order): string
{
    return !empty($order["has_return"]) ? "Returned" : (string) $order["order_status"];
}

function advanceOrderStatus(PDO $pdo, int $orderId, int $supplierId, string $targetStatus): void
{
    transactional($pdo, function () use ($pdo, $orderId, $supplierId, $targetStatus): void {
        $order = lockOrder($pdo, $orderId);
        if ((int) $order["supplier_id"] !== $supplierId) {
            throw new RuntimeException("This order does not belong to your inventory.");
        }

        $allowed = ["Confirmed" => "Processing", "Processing" => "Completed"];
        if (($allowed[$order["order_status"]] ?? null) !== $targetStatus) {
            throw new RuntimeException("That order status transition is not allowed.");
        }

        $statement = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ? AND order_status = ?");
        $statement->execute([$targetStatus, $orderId, $order["order_status"]]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException("The order changed while you were updating it.");
        }

        if ($targetStatus === "Completed") {
            // Stock was removed when the order was reserved. SOLD records completion without deducting it again.
            $statement = $pdo->prepare(
                "INSERT INTO stock_transaction (batch_id, order_id, quantity, transaction_type, remarks)
                 SELECT ?, ?, ?, 'SOLD', ?
                 WHERE NOT EXISTS (
                     SELECT 1 FROM stock_transaction WHERE order_id = ? AND transaction_type = 'SOLD'
                 )",
            );
            $statement->execute([
                $order["batch_id"],
                $orderId,
                $order["quantity"],
                "Completed sale for {$order["order_type"]} order #{$orderId}",
                $orderId,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException("A completed-sale transaction already exists for this order.");
            }
        }
    });
}

function cancelOrder(PDO $pdo, int $orderId, string $actorRole, int $actorId): void
{
    transactional($pdo, function () use ($pdo, $orderId, $actorRole, $actorId): void {
        $order = lockOrder($pdo, $orderId);
        if (!actorCanManageOrder($order, $actorRole, $actorId)) {
            throw new RuntimeException("You are not allowed to cancel this order.");
        }
        if (!in_array($order["order_status"], ["Pending", "Confirmed"], true)) {
            throw new RuntimeException("Only pending or confirmed orders can be cancelled.");
        }

        $statement = $pdo->prepare(
            "UPDATE orders SET order_status = 'Cancelled'
             WHERE order_id = ? AND order_status IN ('Pending', 'Confirmed')",
        );
        $statement->execute([$orderId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException("The order changed while you were cancelling it.");
        }

        $pdo->prepare(
            "UPDATE listing
             SET listed_quantity = listed_quantity + ?,
                 status = IF(status = 'Sold Out' AND ? = 'Active', 'Active', status)
             WHERE listing_id = ?",
        )->execute([$order["quantity"], $order["batch_status"], $order["listing_id"]]);
        $pdo->prepare(
            "UPDATE textile_batch SET available_quantity = available_quantity + ? WHERE batch_id = ?",
        )->execute([$order["quantity"], $order["batch_id"]]);
        $pdo->prepare(
            "INSERT INTO stock_transaction (batch_id, order_id, quantity, transaction_type, remarks)
             VALUES (?, ?, ?, 'RESERVATION_RELEASED', ?)",
        )->execute([
            $order["batch_id"],
            $orderId,
            $order["quantity"],
            "Released reservation for cancelled {$order["order_type"]} order #{$orderId}",
        ]);
        $pdo->prepare(
            "UPDATE payment
             SET payment_status = CASE
                    WHEN payment_status = 'Paid' THEN 'Refunded'
                    WHEN payment_status = 'Pending' THEN 'Failed'
                    ELSE payment_status
                 END
             WHERE order_id = ?",
        )->execute([$orderId]);
    });
}

function returnOrder(PDO $pdo, int $orderId, string $actorRole, int $actorId): void
{
    transactional($pdo, function () use ($pdo, $orderId, $actorRole, $actorId): void {
        $order = lockOrder($pdo, $orderId);
        if (!actorCanManageOrder($order, $actorRole, $actorId)) {
            throw new RuntimeException("You are not allowed to return this order.");
        }
        if ($order["order_status"] !== "Completed") {
            throw new RuntimeException("Only completed orders can be returned.");
        }
        if (orderHasReturn($pdo, $orderId, true)) {
            throw new RuntimeException("This order has already been returned.");
        }

        // The existing schema has no Returned order status, so the RETURNED ledger row is the durable return marker.
        $payment = $pdo->prepare("SELECT payment_id, payment_status FROM payment WHERE order_id = ? FOR UPDATE");
        $payment->execute([$orderId]);
        $paymentRow = $payment->fetch();

        $pdo->prepare(
            "UPDATE listing
             SET listed_quantity = listed_quantity + ?,
                 status = IF(status = 'Sold Out' AND ? = 'Active', 'Active', status)
             WHERE listing_id = ?",
        )->execute([$order["quantity"], $order["batch_status"], $order["listing_id"]]);
        $pdo->prepare(
            "UPDATE textile_batch SET available_quantity = available_quantity + ? WHERE batch_id = ?",
        )->execute([$order["quantity"], $order["batch_id"]]);
        $pdo->prepare(
            "INSERT INTO stock_transaction (batch_id, order_id, quantity, transaction_type, remarks)
             VALUES (?, ?, ?, 'RETURNED', ?)",
        )->execute([
            $order["batch_id"],
            $orderId,
            $order["quantity"],
            "Full return received for {$order["order_type"]} order #{$orderId}",
        ]);

        if ($paymentRow && $paymentRow["payment_status"] === "Paid") {
            $pdo->prepare("UPDATE payment SET payment_status = 'Refunded' WHERE payment_id = ?")->execute([
                $paymentRow["payment_id"],
            ]);
        }
    });
}

function repeatPurchase(PDO $pdo, int $sourceOrderId, int $buyerId, string $buyerRole): array
{
    if (!in_array($buyerRole, ["b2b", "b2c"], true)) {
        throw new RuntimeException("Only buyers can repeat a purchase.");
    }

    return transactional($pdo, function () use ($pdo, $sourceOrderId, $buyerId, $buyerRole): array {
        $order = lockOrder($pdo, $sourceOrderId);
        $orderType = strtoupper($buyerRole);
        if ((int) $order["buyer_id"] !== $buyerId || $order["order_type"] !== $orderType) {
            throw new RuntimeException("This order does not belong to your account.");
        }
        if (!in_array($order["order_status"], ["Completed", "Cancelled"], true)) {
            throw new RuntimeException("Buy again is available after an order is completed or cancelled.");
        }

        $listing = lockListing($pdo, (int) $order["listing_id"], $orderType);
        $quantity = (float) $order["quantity"];
        if ($listing["listing_status"] !== "Active" || $listing["batch_status"] !== "Active") {
            throw new RuntimeException("The original listing is no longer active.");
        }
        if ($quantity > reservableQuantity($listing)) {
            throw new RuntimeException("The original quantity is no longer available.");
        }

        if ($orderType === "B2C") {
            $bundle = (float) $listing["bundle_size"];
            if ($bundle <= 0 || $quantity < $bundle || abs(fmod($quantity, $bundle)) > 0.0001) {
                throw new RuntimeException("The current bundle size ({$bundle}) no longer fits the original quantity.");
            }
            $newOrderId = createReservedOrder(
                $pdo,
                $buyerId,
                (int) $order["listing_id"],
                $quantity,
                (float) $listing["fixed_unit_price"],
                "B2C",
            );
            return ["type" => "order", "id" => $newOrderId];
        }

        if ($quantity < (float) $listing["minimum_quantity"]) {
            throw new RuntimeException("The original quantity is below the current wholesale minimum.");
        }
        $open = $pdo->prepare(
            "SELECT quotation_id FROM quotation
             WHERE buyer_id = ? AND listing_id = ? AND status IN ('Pending', 'Countered') LIMIT 1 FOR UPDATE",
        );
        $open->execute([$buyerId, $order["listing_id"]]);
        if ($open->fetchColumn()) {
            throw new RuntimeException("You already have an open quotation for this listing.");
        }
        $pdo->prepare(
            'INSERT INTO quotation (buyer_id, listing_id, requested_quantity, proposed_price, expiry_date)
             VALUES (?, ?, ?, ?, ?)',
        )->execute([
            $buyerId,
            $order["listing_id"],
            $quantity,
            $listing["bulk_unit_price"],
            date("Y-m-d", strtotime("+7 days")),
        ]);
        return ["type" => "quotation", "id" => (int) $pdo->lastInsertId()];
    });
}

function submitPayment(PDO $pdo, int $buyerId, int $orderId, string $method): void
{
    $methods = ["Cash", "Bank Transfer", "Mobile Banking", "Card"];
    if (!in_array($method, $methods, true)) {
        throw new RuntimeException("Select a valid payment method.");
    }

    transactional($pdo, function () use ($pdo, $buyerId, $orderId, $method): void {
        $order = lockOrder($pdo, $orderId);
        if ((int) $order["buyer_id"] !== $buyerId) {
            throw new RuntimeException("This order does not belong to your account.");
        }
        if (!in_array($order["order_status"], ["Confirmed", "Processing"], true)) {
            throw new RuntimeException("Payment is only available for confirmed or processing orders.");
        }

        $statement = $pdo->prepare("SELECT * FROM payment WHERE order_id = ? FOR UPDATE");
        $statement->execute([$orderId]);
        $payment = $statement->fetch();
        if (!$payment) {
            $pdo->prepare(
                "INSERT INTO payment (order_id, amount, payment_method, payment_status)
                 VALUES (?, ?, ?, 'Pending')",
            )->execute([$orderId, $order["total_amount"], $method]);
            return;
        }
        if ($payment["payment_status"] !== "Failed") {
            throw new RuntimeException("This order already has an active payment record.");
        }
        $pdo->prepare(
            "UPDATE payment
             SET amount = ?, payment_method = ?, payment_status = 'Pending', payment_date = NULL
             WHERE payment_id = ?",
        )->execute([$order["total_amount"], $method, $payment["payment_id"]]);
    });
}

function reviewPayment(PDO $pdo, int $paymentId, string $status): void
{
    if (!in_array($status, ["Paid", "Failed"], true)) {
        throw new RuntimeException("Select a valid payment decision.");
    }

    transactional($pdo, function () use ($pdo, $paymentId, $status): void {
        $statement = $pdo->prepare(
            'SELECT p.*, o.order_status, o.total_amount FROM payment p
             JOIN orders o ON o.order_id = p.order_id
             WHERE p.payment_id = ? FOR UPDATE',
        );
        $statement->execute([$paymentId]);
        $payment = $statement->fetch();
        if (!$payment || $payment["payment_status"] !== "Pending") {
            throw new RuntimeException("Only pending payments can be reviewed.");
        }
        if ($payment["order_status"] === "Cancelled") {
            throw new RuntimeException("A cancelled order cannot be marked as paid.");
        }
        if (abs((float) $payment["amount"] - (float) $payment["total_amount"]) > 0.009) {
            throw new RuntimeException("The payment amount does not match the order total.");
        }

        $pdo->prepare("UPDATE payment SET payment_status = ?, payment_date = ? WHERE payment_id = ?")->execute([
            $status,
            $status === "Paid" ? date("Y-m-d H:i:s") : null,
            $paymentId,
        ]);
    });
}
