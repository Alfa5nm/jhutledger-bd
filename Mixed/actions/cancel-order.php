<?php

require __DIR__ . '/../includes/bootstrap.php';
requirePost();
requireRole(['b2b', 'b2c']);
verifyCsrf();

// Request flow: buyer cancel form -> cancelOrder() -> order/stock/payment UPDATE -> history redirect.
$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$role = currentUser()['base_role'];
try {
    if (!$orderId) {
        throw new RuntimeException('Select a valid order.');
    }
    cancelOrder(db(), $orderId, $role, (int) currentUser()['user_id']);
    setFlash('success', "Order #{$orderId} cancelled and its reserved stock was restored.");
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Abir/' . $role . '/orders.php');
