<?php

require __DIR__ . '/../includes/bootstrap.php';
requireRole('b2c');

$pdo = db();
$buyerId = (int) currentUser()['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'place') {
    verifyCsrf();
    $listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
    $quantity = (float) input('quantity');
    try {
        if (!$listingId) {
            throw new RuntimeException('Select a valid listing.');
        }
        $orderId = placeB2cOrder($pdo, $buyerId, $listingId, $quantity);
        setFlash('success', "Order #{$orderId} confirmed and stock reserved.");
        redirect('b2c/orders.php');
    } catch (Throwable $exception) {
        setFlash('danger', $exception->getMessage());
        redirect('marketplace.php');
    }
}

$orderType = 'B2C';
$buyerRole = 'b2c';
require __DIR__ . '/../includes/buyer-orders-page.php';
