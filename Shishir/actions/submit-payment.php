<?php

require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requirePost();
requireRole(['b2b', 'b2c']);
verifyCsrf();

// Request flow: payment form -> submitPayment() -> payment INSERT/UPDATE -> buyer orders redirect.
$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$role = currentUser()['base_role'];
try {
    if (!$orderId) {
        throw new RuntimeException('Select a valid order.');
    }
    submitPayment(db(), (int) currentUser()['user_id'], $orderId, input('payment_method'));
    setFlash('success', 'Payment submitted for administrator verification.');
    redirect('Abir/' . $role . '/orders.php');
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
    redirect('Shishir/payment.php?order_id=' . (int) $orderId);
}
