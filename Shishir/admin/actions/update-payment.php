<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('admin');
verifyCsrf();

// Request flow: Admin payment form -> reviewPayment() -> payment UPDATE -> payment list redirect.
$paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
try {
    if (!$paymentId) {
        throw new RuntimeException('Select a valid payment.');
    }
    reviewPayment(db(), $paymentId, input('status'));
    setFlash('success', "Payment #{$paymentId} was reviewed.");
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
$query = http_build_query(array_filter([
    'q' => input('return_q'),
    'status' => input('return_status'),
]));
redirect('Shishir/admin/payments.php' . ($query ? '?' . $query : ''));
