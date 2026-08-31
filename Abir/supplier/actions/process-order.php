<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('supplier');
verifyCsrf();

// Request flow: processing form -> advanceOrderStatus() -> orders UPDATE -> orders redirect.
$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
try {
    if (!$orderId) {
        throw new RuntimeException('Select a valid order.');
    }
    advanceOrderStatus(db(), $orderId, (int) currentUser()['user_id'], 'Processing');
    setFlash('success', "Order #{$orderId} is now processing.");
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Abir/supplier/orders.php');
