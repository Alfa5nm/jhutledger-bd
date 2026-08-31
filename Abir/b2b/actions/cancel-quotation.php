<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('b2b');
verifyCsrf();

// Request flow: cancel form -> cancelBuyerQuotation() -> quotation UPDATE -> quotations redirect.
$quotationId = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);
try {
    if (!$quotationId) {
        throw new RuntimeException('Select a valid quotation.');
    }
    cancelBuyerQuotation(db(), $quotationId, (int) currentUser()['user_id']);
    setFlash('success', 'Quotation cancelled.');
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Abir/b2b/quotations.php');
