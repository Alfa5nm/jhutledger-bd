<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('supplier');
verifyCsrf();

// Request flow: complete form -> advanceOrderStatus() -> order/ledger UPDATE -> orders redirect.
$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
try {
    if (!$orderId) {
        throw new RuntimeException('Select a valid order.');
    }
    advanceOrderStatus(db(), $orderId, (int) currentUser()['user_id'], 'Completed');
    setFlash('success', "Order #{$orderId} completed and the sale was added to the stock ledger.");
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Abir/supplier/orders.php');
