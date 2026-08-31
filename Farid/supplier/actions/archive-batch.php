<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('supplier');
verifyCsrf();

// Request flow: archive form -> archiveSupplierBatch() -> batch/listing UPDATE -> batches redirect.
$batchId = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
try {
    if (!$batchId) {
        throw new RuntimeException('Select a valid batch.');
    }
    archiveSupplierBatch(db(), (int) currentUser()['user_id'], $batchId);
    setFlash('success', 'Batch and its active listings were archived.');
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Farid/supplier/batches.php');
