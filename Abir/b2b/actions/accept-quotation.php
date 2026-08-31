<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('b2b');
verifyCsrf();

// Request flow: accept form -> acceptQuotation() -> order/ledger INSERT -> quotations redirect.
$quotationId = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);
try {
    if (!$quotationId) {
        throw new RuntimeException('Select a valid quotation.');
    }
    $orderId = acceptQuotation(db(), $quotationId, 'b2b', (int) currentUser()['user_id']);
    setFlash('success', "Counter-offer accepted. Order #{$orderId} was confirmed and stock reserved.");
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Abir/b2b/quotations.php');
