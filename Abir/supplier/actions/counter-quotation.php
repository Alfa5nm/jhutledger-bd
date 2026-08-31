<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('supplier');
verifyCsrf();

// Request flow: counter form -> counterSupplierQuotation() -> quotation UPDATE -> quotations redirect.
$quotationId = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);
$price = (float) input('counter_price');
try {
    if (!$quotationId || $price < 0) {
        throw new RuntimeException('Enter a valid counter price.');
    }
    counterSupplierQuotation(db(), $quotationId, (int) currentUser()['user_id'], $price);
    setFlash('success', 'Counter-offer sent to the buyer.');
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Abir/supplier/quotations.php');
