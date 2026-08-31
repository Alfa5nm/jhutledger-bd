<?php

declare(strict_types=1);

function authenticateAccount(PDO $pdo, string $email, string $password): array
{
    $sql = 'SELECT user_id, name, email, password_hash, user_status
            FROM users
            WHERE email = ?
            LIMIT 1';

    $statement = $pdo->prepare($sql);
    $statement->execute([strtolower($email)]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        throw new RuntimeException('Invalid email or password.');
    }
    if ($user['user_status'] !== 'Active') {
        throw new RuntimeException('This account is not active. Contact the administrator.');
    }

    return $user;
}

function registerAccount(PDO $pdo, array $values, string $password): int
{
    return transactional($pdo, function () use ($pdo, $values, $password): int {
        $sql = "INSERT INTO users
                    (name, email, phone, password_hash, street, city, district, postal_code, user_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')";

        $statement = $pdo->prepare($sql);
        $statement->execute([
            $values['name'],
            strtolower($values['email']),
            $values['phone'],
            password_hash($password, PASSWORD_DEFAULT),
            $values['street'],
            $values['city'],
            $values['district'],
            $values['postal_code'],
        ]);
        $userId = (int) $pdo->lastInsertId();

        $subtypeTable = match ($values['role']) {
            'supplier' => 'supplier',
            'b2b' => 'b2b_buyer',
            'b2c' => 'b2c_buyer',
            default => throw new RuntimeException('Select a valid account role.'),
        };
        $sql = "INSERT INTO {$subtypeTable} (user_id) VALUES (?)";
        $statement = $pdo->prepare($sql);
        $statement->execute([$userId]);

        return $userId;
    });
}

function updateAccountProfile(PDO $pdo, int $userId, array $values): void
{
    $sql = 'UPDATE users
            SET name = ?, phone = ?, street = ?, city = ?, district = ?, postal_code = ?
            WHERE user_id = ?';

    $statement = $pdo->prepare($sql);
    $statement->execute([
        $values['name'],
        $values['phone'],
        $values['street'],
        $values['city'],
        $values['district'],
        $values['postal_code'],
        $userId,
    ]);
}

function updateAccountStatus(PDO $pdo, int $userId, string $status): void
{
    if (!in_array($status, ['Active', 'Inactive'], true)) {
        throw new RuntimeException('Invalid user status request.');
    }

    $sql = 'UPDATE users SET user_status = ? WHERE user_id = ?';
    $statement = $pdo->prepare($sql);
    $statement->execute([$status, $userId]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('The user status did not change.');
    }
}

function createBuyerQuotation(
    PDO $pdo,
    int $buyerId,
    int $listingId,
    float $quantity,
    float $price,
    string $expiryDate,
): int {
    return transactional($pdo, function () use ($pdo, $buyerId, $listingId, $quantity, $price, $expiryDate): int {
        $listing = lockListing($pdo, $listingId, 'B2B');
        $outsideRange = $quantity < (float) $listing['minimum_quantity']
            || $quantity > reservableQuantity($listing);
        if ($listing['listing_status'] !== 'Active' || $listing['batch_status'] !== 'Active' || $outsideRange) {
            throw new RuntimeException('Requested quantity is outside the available wholesale range.');
        }

        $sql = "SELECT COUNT(*)
                FROM quotation
                WHERE buyer_id = ? AND listing_id = ? AND status IN ('Pending', 'Countered')";
        $statement = $pdo->prepare($sql);
        $statement->execute([$buyerId, $listingId]);
        if ($statement->fetchColumn()) {
            throw new RuntimeException('You already have an open quotation for this listing.');
        }

        $sql = 'INSERT INTO quotation
                    (buyer_id, listing_id, requested_quantity, proposed_price, expiry_date)
                VALUES (?, ?, ?, ?, ?)';
        $statement = $pdo->prepare($sql);
        $statement->execute([$buyerId, $listingId, $quantity, $price, $expiryDate]);

        return (int) $pdo->lastInsertId();
    });
}

function cancelBuyerQuotation(PDO $pdo, int $quotationId, int $buyerId): void
{
    $sql = "UPDATE quotation
            SET status = 'Cancelled'
            WHERE quotation_id = ? AND buyer_id = ? AND status IN ('Pending', 'Countered')";
    $statement = $pdo->prepare($sql);
    $statement->execute([$quotationId, $buyerId]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Quotation cannot be cancelled.');
    }
}

function counterSupplierQuotation(PDO $pdo, int $quotationId, int $supplierId, float $price): void
{
    $sql = "UPDATE quotation AS q
            JOIN listing AS l ON l.listing_id = q.listing_id
            JOIN textile_batch AS b ON b.batch_id = l.batch_id
            SET q.counter_price = ?, q.status = 'Countered'
            WHERE q.quotation_id = ? AND b.supplier_id = ? AND q.status = 'Pending'";
    $statement = $pdo->prepare($sql);
    $statement->execute([$price, $quotationId, $supplierId]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Quotation cannot be countered.');
    }
}

function rejectSupplierQuotation(PDO $pdo, int $quotationId, int $supplierId): void
{
    $sql = "UPDATE quotation AS q
            JOIN listing AS l ON l.listing_id = q.listing_id
            JOIN textile_batch AS b ON b.batch_id = l.batch_id
            SET q.status = 'Rejected'
            WHERE q.quotation_id = ? AND b.supplier_id = ? AND q.status IN ('Pending', 'Countered')";
    $statement = $pdo->prepare($sql);
    $statement->execute([$quotationId, $supplierId]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Quotation cannot be rejected.');
    }
}

function archiveSupplierBatch(PDO $pdo, int $supplierId, int $batchId): void
{
    transactional($pdo, function () use ($pdo, $supplierId, $batchId): void {
        $sql = 'SELECT batch_id
                FROM textile_batch
                WHERE batch_id = ? AND supplier_id = ?
                FOR UPDATE';
        $statement = $pdo->prepare($sql);
        $statement->execute([$batchId, $supplierId]);
        if (!$statement->fetchColumn()) {
            throw new RuntimeException('Batch not found.');
        }

        $sql = "UPDATE textile_batch SET status = 'Inactive' WHERE batch_id = ?";
        $statement = $pdo->prepare($sql);
        $statement->execute([$batchId]);

        $sql = "UPDATE listing SET status = 'Inactive' WHERE batch_id = ? AND status = 'Active'";
        $statement = $pdo->prepare($sql);
        $statement->execute([$batchId]);
    });
}

function saveSupplierBatch(PDO $pdo, int $supplierId, ?int $batchId, array $values): int
{
    return transactional($pdo, function () use ($pdo, $supplierId, $batchId, $values): int {
        if ($batchId !== null) {
            $sql = 'SELECT *
                    FROM textile_batch
                    WHERE batch_id = ? AND supplier_id = ?
                    FOR UPDATE';
            $statement = $pdo->prepare($sql);
            $statement->execute([$batchId, $supplierId]);
            $oldBatch = $statement->fetch();
            if (!$oldBatch) {
                throw new RuntimeException('Batch not found.');
            }

            $usedQuantity = (float) $oldBatch['total_quantity'] - (float) $oldBatch['available_quantity'];
            $newTotal = (float) $values['total_quantity'];
            if ($newTotal < $usedQuantity) {
                throw new RuntimeException("Total quantity cannot be below {$usedQuantity}; that amount is already reserved.");
            }
            $newAvailable = $newTotal - $usedQuantity;

            $sql = "SELECT COALESCE(SUM(listed_quantity), 0)
                    FROM listing
                    WHERE batch_id = ? AND status = 'Active'";
            $statement = $pdo->prepare($sql);
            $statement->execute([$batchId]);
            $allocatedQuantity = (float) $statement->fetchColumn();
            if ($newAvailable < $allocatedQuantity) {
                throw new RuntimeException(
                    "Available quantity cannot be below {$allocatedQuantity}; that amount is allocated to active listings.",
                );
            }

            $sql = 'UPDATE textile_batch
                    SET material_type = ?, composition = ?, color = ?, gsm = ?, `condition` = ?,
                        total_quantity = ?, available_quantity = ?, average_cost = ?, storage_location = ?,
                        entry_date = ?, unit_of_measure = ?, status = ?
                    WHERE batch_id = ?';
            $statement = $pdo->prepare($sql);
            $statement->execute([
                $values['material_type'], $values['composition'], $values['color'], $values['gsm'],
                $values['condition'], $newTotal, $newAvailable, $values['average_cost'],
                $values['storage_location'], $values['entry_date'], $values['unit_of_measure'],
                $values['status'], $batchId,
            ]);

            $quantityChange = $newTotal - (float) $oldBatch['total_quantity'];
            if (abs($quantityChange) > 0.0001) {
                $transactionType = $quantityChange > 0 ? 'ADJUSTMENT_IN' : 'ADJUSTMENT_OUT';
                $sql = 'INSERT INTO stock_transaction (batch_id, quantity, transaction_type, remarks)
                        VALUES (?, ?, ?, ?)';
                $statement = $pdo->prepare($sql);
                $statement->execute([
                    $batchId,
                    abs($quantityChange),
                    $transactionType,
                    'Batch total adjusted by supplier',
                ]);
            }

            if ($values['status'] === 'Inactive') {
                $sql = "UPDATE listing SET status = 'Inactive' WHERE batch_id = ? AND status = 'Active'";
                $statement = $pdo->prepare($sql);
                $statement->execute([$batchId]);
            }

            return $batchId;
        }

        $sql = 'INSERT INTO textile_batch
                    (supplier_id, material_type, composition, color, gsm, `condition`, total_quantity,
                     available_quantity, average_cost, storage_location, entry_date, unit_of_measure, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $statement = $pdo->prepare($sql);
        $statement->execute([
            $supplierId, $values['material_type'], $values['composition'], $values['color'], $values['gsm'],
            $values['condition'], $values['total_quantity'], $values['total_quantity'], $values['average_cost'],
            $values['storage_location'], $values['entry_date'], $values['unit_of_measure'], $values['status'],
        ]);
        $newBatchId = (int) $pdo->lastInsertId();

        $sql = "INSERT INTO stock_transaction (batch_id, quantity, transaction_type, remarks)
                VALUES (?, ?, 'STOCK_ADDED', 'Opening quantity')";
        $statement = $pdo->prepare($sql);
        $statement->execute([$newBatchId, $values['total_quantity']]);

        return $newBatchId;
    });
}

function archiveSupplierListing(PDO $pdo, int $supplierId, int $listingId): void
{
    $sql = "UPDATE listing AS l
            JOIN textile_batch AS b ON b.batch_id = l.batch_id
            SET l.status = 'Inactive'
            WHERE l.listing_id = ? AND b.supplier_id = ?";
    $statement = $pdo->prepare($sql);
    $statement->execute([$listingId, $supplierId]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Listing not found.');
    }
}

function saveSupplierListing(PDO $pdo, int $supplierId, ?int $listingId, array $values): int
{
    return transactional($pdo, function () use ($pdo, $supplierId, $listingId, $values): int {
        $sql = 'SELECT *
                FROM textile_batch
                WHERE batch_id = ? AND supplier_id = ?
                FOR UPDATE';
        $statement = $pdo->prepare($sql);
        $statement->execute([$values['batch_id'], $supplierId]);
        $batch = $statement->fetch();
        if (!$batch) {
            throw new RuntimeException('Batch not found.');
        }

        if ($listingId !== null) {
            $sql = "SELECT l.*, IF(bl.listing_id IS NULL, 'B2C', 'B2B') AS listing_type
                    FROM listing AS l
                    JOIN textile_batch AS b ON b.batch_id = l.batch_id
                    LEFT JOIN b2b_listing AS bl ON bl.listing_id = l.listing_id
                    WHERE l.listing_id = ? AND b.supplier_id = ?
                    FOR UPDATE";
            $statement = $pdo->prepare($sql);
            $statement->execute([$listingId, $supplierId]);
            $oldListing = $statement->fetch();
            if (!$oldListing) {
                throw new RuntimeException('Listing not found.');
            }
            if ((int) $oldListing['batch_id'] !== $values['batch_id']
                || $oldListing['listing_type'] !== $values['listing_type']) {
                throw new RuntimeException('A listing cannot change its batch or sales channel after creation.');
            }
        }

        $sql = "SELECT COALESCE(SUM(listed_quantity), 0)
                FROM listing
                WHERE batch_id = ? AND status = 'Active' AND listing_id <> ?";
        $statement = $pdo->prepare($sql);
        $statement->execute([$values['batch_id'], $listingId ?? 0]);
        $otherAllocations = (float) $statement->fetchColumn();
        $remainingQuantity = max(0, (float) $batch['available_quantity'] - $otherAllocations);
        if ($values['status'] === 'Active' && $values['listed_quantity'] > $remainingQuantity + 0.0001) {
            $available = number_format($remainingQuantity, 2) . ' ' . $batch['unit_of_measure'];
            throw new RuntimeException("Only {$available} remains available for allocation in this batch.");
        }

        if ($listingId !== null) {
            $sql = 'UPDATE listing SET listed_quantity = ?, status = ? WHERE listing_id = ?';
            $statement = $pdo->prepare($sql);
            $statement->execute([$values['listed_quantity'], $values['status'], $listingId]);

            if ($values['listing_type'] === 'B2B') {
                $sql = 'UPDATE b2b_listing
                        SET minimum_quantity = ?, bulk_unit_price = ?
                        WHERE listing_id = ?';
                $statement = $pdo->prepare($sql);
                $statement->execute([$values['minimum_quantity'], $values['bulk_unit_price'], $listingId]);
            } else {
                $sql = 'UPDATE b2c_listing
                        SET bundle_size = ?, fixed_unit_price = ?
                        WHERE listing_id = ?';
                $statement = $pdo->prepare($sql);
                $statement->execute([$values['bundle_size'], $values['fixed_unit_price'], $listingId]);
            }

            return $listingId;
        }

        $sql = 'INSERT INTO listing (batch_id, listed_quantity, status) VALUES (?, ?, ?)';
        $statement = $pdo->prepare($sql);
        $statement->execute([$values['batch_id'], $values['listed_quantity'], $values['status']]);
        $newListingId = (int) $pdo->lastInsertId();

        if ($values['listing_type'] === 'B2B') {
            $sql = 'INSERT INTO b2b_listing (listing_id, minimum_quantity, bulk_unit_price)
                    VALUES (?, ?, ?)';
            $statement = $pdo->prepare($sql);
            $statement->execute([$newListingId, $values['minimum_quantity'], $values['bulk_unit_price']]);
        } else {
            $sql = 'INSERT INTO b2c_listing (listing_id, bundle_size, fixed_unit_price)
                    VALUES (?, ?, ?)';
            $statement = $pdo->prepare($sql);
            $statement->execute([$newListingId, $values['bundle_size'], $values['fixed_unit_price']]);
        }

        return $newListingId;
    });
}
