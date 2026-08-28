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
    $subtype = $type === "B2B" ? "b2b_listing" : "b2c_listing";
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
        throw new RuntimeException("The selected listing does not exist.");
    }
    return $listing;
}

function reservableQuantity(array $listing): float
{
    return max(0, min((float) $listing["listed_quantity"], (float) $listing["available_quantity"]));
}

function createReservedOrder(
    PDO $pdo,
    int $buyerId,
    int $listingId,
    float $quantity,
    float $unitPrice,
    string $orderType,
    ?int $quotationId = null,
): int {
    $listing = lockListing($pdo, $listingId, $orderType);
    if ($listing["listing_status"] !== "Active" || $listing["batch_status"] !== "Active") {
        throw new RuntimeException("This listing is no longer available.");
    }
    if ($quantity <= 0 || $quantity > reservableQuantity($listing)) {
        throw new RuntimeException("The requested quantity is no longer available.");
    }

    $total = round($quantity * $unitPrice, 2);
    $cost = (float) $listing["average_cost"];
    $grossProfit = round(($unitPrice - $cost) * $quantity, 2);

    $statement = $pdo->prepare(
        'INSERT INTO orders (buyer_id, quotation_id, order_type, order_status, total_amount)
         VALUES (?, ?, ?, \'Confirmed\', ?)',
    );
    $statement->execute([$buyerId, $quotationId, $orderType, $total]);
    $orderId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO order_item (order_id, line_no, listing_id, quantity, selling_price, average_cost_snapshot, gross_profit)
         VALUES (?, 1, ?, ?, ?, ?, ?)',
    )->execute([$orderId, $listingId, $quantity, $unitPrice, $cost, $grossProfit]);

    $pdo->prepare(
        "UPDATE listing
         SET status = IF(listed_quantity - ? <= 0, 'Sold Out', status),
             listed_quantity = listed_quantity - ?
         WHERE listing_id = ?",
    )->execute([$quantity, $quantity, $listingId]);
    $pdo->prepare("UPDATE textile_batch SET available_quantity = available_quantity - ? WHERE batch_id = ?")->execute([
        $quantity,
        $listing["batch_id"],
    ]);
    $pdo->prepare(
        "INSERT INTO stock_transaction (batch_id, order_id, quantity, transaction_type, remarks)
         VALUES (?, ?, ?, 'RESERVED', ?)",
    )->execute([$listing["batch_id"], $orderId, $quantity, "Reserved for {$orderType} order #{$orderId}"]);

    return $orderId;
}

function placeB2cOrder(PDO $pdo, int $buyerId, int $listingId, float $quantity): int
{
    return transactional($pdo, function () use ($pdo, $buyerId, $listingId, $quantity): int {
        $listing = lockListing($pdo, $listingId, "B2C");
        $bundle = (float) $listing["bundle_size"];
        if ($bundle <= 0 || $quantity < $bundle || abs(fmod($quantity, $bundle)) > 0.0001) {
            throw new RuntimeException("Quantity must be purchased in bundles of {$bundle}.");
        }
        $orderId = createReservedOrder(
            $pdo,
            $buyerId,
            $listingId,
            $quantity,
            (float) $listing["fixed_unit_price"],
            "B2C",
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
             WHERE q.quotation_id = ? FOR UPDATE',
        );
        $statement->execute([$quotationId]);
        $quotation = $statement->fetch();
        if (!$quotation || $quotation["expiry_date"] < date("Y-m-d")) {
            throw new RuntimeException("This quotation is missing or has expired.");
        }

        if ($actorRole === "supplier") {
            if ((int) $quotation["supplier_id"] !== $actorId || $quotation["status"] !== "Pending") {
                throw new RuntimeException("This quotation cannot be accepted by your account.");
            }
            $finalPrice = (float) $quotation["proposed_price"];
        } elseif ($actorRole === "b2b") {
            if ((int) $quotation["buyer_id"] !== $actorId || $quotation["status"] !== "Countered") {
                throw new RuntimeException("This counter-offer cannot be accepted by your account.");
            }
            $finalPrice = (float) $quotation["counter_price"];
        } else {
            throw new RuntimeException("Invalid quotation action.");
        }

        $orderId = createReservedOrder(
            $pdo,
            (int) $quotation["buyer_id"],
            (int) $quotation["listing_id"],
            (float) $quotation["requested_quantity"],
            $finalPrice,
            "B2B",
            $quotationId,
        );
        $pdo->prepare("UPDATE quotation SET status = 'Accepted', final_price = ? WHERE quotation_id = ?")->execute([
            $finalPrice,
            $quotationId,
        ]);
        return $orderId;
    });
}
