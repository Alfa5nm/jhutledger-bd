<?php

require __DIR__ . '/../includes/bootstrap.php';
requirePost();
requireRole(['b2b', 'b2c']);
verifyCsrf();

// Request flow: Buy again form -> repeatPurchase() -> order/quotation INSERT -> destination redirect.
$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$role = currentUser()['base_role'];
try {
    if (!$orderId) {
        throw new RuntimeException('Select a valid order.');
    }
    $result = repeatPurchase(db(), $orderId, (int) currentUser()['user_id'], $role);
    if ($result['type'] === 'order') {
        setFlash('success', "Repeat order #{$result['id']} was confirmed using the current retail price.");
        redirect('Mixed/order.php?id=' . $result['id']);
    }
    setFlash('success', "Repeat quotation #{$result['id']} was created using the current wholesale terms.");
    redirect('Abir/b2b/quotations.php');
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
    redirect('Abir/' . $role . '/orders.php');
}
