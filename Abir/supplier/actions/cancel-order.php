<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('supplier');
verifyCsrf();

// Request flow: supplier cancel form -> cancelOrder() -> order/stock/payment UPDATE -> orders redirect.
$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
try {
    if (!$orderId) {
        throw new RuntimeException('Select a valid order.');
    }
    cancelOrder(db(), $orderId, 'supplier', (int) currentUser()['user_id']);
    setFlash('success', "Order #{$orderId} cancelled and stock restored.");
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Abir/supplier/orders.php');
