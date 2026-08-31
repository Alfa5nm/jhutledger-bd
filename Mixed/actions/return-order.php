<?php

require __DIR__ . '/../includes/bootstrap.php';
requirePost();
requireLogin();
verifyCsrf();

// Request flow: return confirmation -> returnOrder() -> stock/payment UPDATE -> order redirect.
$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
if (!$orderId) {
    http_response_code(404);
    exit('Order not found.');
}
$user = currentUser();
try {
    returnOrder(db(), $orderId, $user['role'], (int) $user['user_id']);
    setFlash('success', "Order #{$orderId} was returned. Stock was restored and any paid payment was refunded.");
    redirect('Mixed/order.php?id=' . $orderId);
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
    redirect('Mixed/return.php?order_id=' . $orderId);
}
