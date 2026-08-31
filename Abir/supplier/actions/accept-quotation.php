<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('supplier');
verifyCsrf();

// Request flow: supplier accept form -> acceptQuotation() -> order/ledger INSERT -> quotations redirect.
$quotationId = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);
try {
    if (!$quotationId) {
        throw new RuntimeException('Select a valid quotation.');
    }
    $orderId = acceptQuotation(db(), $quotationId, 'supplier', (int) currentUser()['user_id']);
    setFlash('success', "Quotation accepted. Order #{$orderId} confirmed and stock reserved.");
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Abir/supplier/quotations.php');
