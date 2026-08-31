<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('b2c');
verifyCsrf();

// Request flow: marketplace form -> placeB2cOrder() -> order/stock INSERT and UPDATE -> orders redirect.
$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$quantity = (float) input('quantity');
try {
    if (!$listingId || $quantity <= 0) {
        throw new RuntimeException('Select a listing and enter a valid quantity.');
    }
    $orderId = placeB2cOrder(db(), (int) currentUser()['user_id'], $listingId, $quantity);
    setFlash('success', "Order #{$orderId} confirmed and stock reserved.");
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Abir/b2c/orders.php');
