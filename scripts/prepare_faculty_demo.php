<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';

$pdo = db();

function demoUserId(PDO $pdo, string $email): int
{
    $statement = $pdo->prepare("SELECT user_id FROM users WHERE email=? AND user_status='Active'");
    $statement->execute([$email]);
    $id = (int) $statement->fetchColumn();
    if ($id <= 0) {
        throw new RuntimeException("Active demo account {$email} was not found.");
    }
    return $id;
}

function confirmedDemoOrder(PDO $pdo, int $buyerId, int $supplierId, string $paymentMode): ?int
{
    $paymentClause = $paymentMode === 'pending'
        ? "p.payment_status='Pending'"
        : 'p.payment_id IS NULL';
    $statement = $pdo->prepare(
        "SELECT o.order_id
         FROM orders o
         JOIN order_item oi ON oi.order_id=o.order_id AND oi.line_no=1
         JOIN listing l ON l.listing_id=oi.listing_id
         JOIN textile_batch b ON b.batch_id=l.batch_id
         LEFT JOIN payment p ON p.order_id=o.order_id
         WHERE o.buyer_id=? AND b.supplier_id=? AND o.order_type='B2B'
           AND o.order_status='Confirmed' AND {$paymentClause}
         ORDER BY o.order_id DESC LIMIT 1"
    );
    $statement->execute([$buyerId, $supplierId]);
    $id = $statement->fetchColumn();
    return $id === false ? null : (int) $id;
}

function createQuotationBackedDemoOrder(PDO $pdo, int $buyerId, int $listingId, float $quantity): int
{
    $statement = $pdo->prepare(
        "SELECT bl.bulk_unit_price
         FROM b2b_listing bl JOIN listing l ON l.listing_id=bl.listing_id
         WHERE bl.listing_id=? AND l.status='Active' FOR UPDATE"
    );
    $statement->execute([$listingId]);
    $price = (float) $statement->fetchColumn();
    if ($price <= 0) {
        throw new RuntimeException('The selected B2B listing is no longer available.');
    }
    $proposed = round($price * 0.90, 2);
    $counter = round($price * 0.96, 2);
    $pdo->prepare(
        "INSERT INTO quotation
         (buyer_id,listing_id,requested_quantity,proposed_price,counter_price,status,expiry_date)
         VALUES (?,?,?,?,?,'Countered',DATE_ADD(CURDATE(),INTERVAL 7 DAY))"
    )->execute([$buyerId, $listingId, $quantity, $proposed, $counter]);
    return acceptQuotation($pdo, (int) $pdo->lastInsertId(), 'b2b', $buyerId);
}

try {
    $supplierId = demoUserId($pdo, 'supplier@jhutledger.local');
    $buyerId = demoUserId($pdo, 'b2b@jhutledger.local');
    $paymentOrderId = confirmedDemoOrder($pdo, $buyerId, $supplierId, 'pending');
    $cancellationOrderId = confirmedDemoOrder($pdo, $buyerId, $supplierId, 'none');

    $pdo->beginTransaction();
    $listing = null;
    if (!$paymentOrderId || !$cancellationOrderId) {
        $statement = $pdo->prepare(
            "SELECT l.listing_id,l.listed_quantity,bl.minimum_quantity,b.material_type,b.unit_of_measure
             FROM listing l
             JOIN b2b_listing bl ON bl.listing_id=l.listing_id
             JOIN textile_batch b ON b.batch_id=l.batch_id
             WHERE b.supplier_id=? AND l.status='Active' AND b.status='Active'
               AND l.listed_quantity >= bl.minimum_quantity * 3
               AND b.available_quantity >= bl.minimum_quantity * 3
             ORDER BY bl.bulk_unit_price ASC,l.listing_id DESC LIMIT 1 FOR UPDATE"
        );
        $statement->execute([$supplierId]);
        $listing = $statement->fetch();
        if (!$listing) {
            throw new RuntimeException('No active B2B listing has enough stock for two demo orders plus a live quotation.');
        }
    }

    if (!$paymentOrderId) {
        $paymentOrderId = createQuotationBackedDemoOrder(
            $pdo,
            $buyerId,
            (int) $listing['listing_id'],
            (float) $listing['minimum_quantity']
        );
        submitPayment($pdo, $buyerId, $paymentOrderId, 'Mobile Banking');
    }
    if (!$cancellationOrderId) {
        $cancellationOrderId = createQuotationBackedDemoOrder(
            $pdo,
            $buyerId,
            (int) $listing['listing_id'],
            (float) $listing['minimum_quantity']
        );
    }
    $pdo->commit();

    $liveListing = $pdo->prepare(
        "SELECT l.listing_id,l.listed_quantity,b.material_type,b.unit_of_measure
         FROM listing l JOIN b2b_listing bl ON bl.listing_id=l.listing_id
         JOIN textile_batch b ON b.batch_id=l.batch_id
         JOIN order_item oi ON oi.listing_id=l.listing_id AND oi.line_no=1
         WHERE b.supplier_id=? AND oi.order_id=? AND l.status='Active'
         LIMIT 1"
    );
    $liveListing->execute([$supplierId, $paymentOrderId]);
    $live = $liveListing->fetch();

    echo "FACULTY DEMO READY\n";
    echo "Completion/payment order: #{$paymentOrderId} (Confirmed, payment Pending)\n";
    echo "Cancellation order: #{$cancellationOrderId} (Confirmed, no payment)\n";
    if ($live) {
        echo "Live B2B listing: #{$live['listing_id']} — {$live['material_type']} — {$live['listed_quantity']} {$live['unit_of_measure']}\n";
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "DEMO PREPARATION FAILED: {$exception->getMessage()}\n");
    exit(1);
}
