<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('supplier');
verifyCsrf();

// Request flow: reject form -> rejectSupplierQuotation() -> quotation UPDATE -> quotations redirect.
$quotationId = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);
try {
    if (!$quotationId) {
        throw new RuntimeException('Select a valid quotation.');
    }
    rejectSupplierQuotation(db(), $quotationId, (int) currentUser()['user_id']);
    setFlash('success', 'Quotation rejected.');
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Abir/supplier/quotations.php');
