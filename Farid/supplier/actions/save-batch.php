<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('supplier');
verifyCsrf();

// Request flow: batch form -> saveSupplierBatch() -> batch/ledger INSERT or UPDATE -> batches redirect.
$supplierId = (int) currentUser()['user_id'];
$batchId = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT) ?: null;
$values = [
    'material_type' => input('material_type'),
    'composition' => input('composition'),
    'color' => input('color'),
    'gsm' => input('gsm'),
    'condition' => input('condition'),
    'total_quantity' => input('total_quantity'),
    'average_cost' => input('average_cost'),
    'storage_location' => input('storage_location'),
    'entry_date' => input('entry_date'),
    'unit_of_measure' => input('unit_of_measure'),
    'status' => input('status'),
];
$errors = [];
foreach (['material_type', 'composition', 'color', 'storage_location', 'unit_of_measure'] as $field) {
    if ($values[$field] === '') {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
    }
}
if (!is_numeric($values['gsm']) || (float) $values['gsm'] <= 0) {
    $errors[] = 'GSM must be greater than zero.';
}
if (!is_numeric($values['total_quantity']) || (float) $values['total_quantity'] <= 0) {
    $errors[] = 'Total quantity must be greater than zero.';
}
if (!is_numeric($values['average_cost']) || (float) $values['average_cost'] < 0) {
    $errors[] = 'Average cost cannot be negative.';
}
if (!in_array($values['condition'], ['New', 'Surplus', 'Dead Stock', 'Recycled'], true)) {
    $errors[] = 'Select a valid condition.';
}
if (!in_array($values['status'], ['Active', 'Inactive'], true)) {
    $errors[] = 'Select a valid status.';
}
if (!validDate($values['entry_date'])) {
    $errors[] = 'Enter a valid entry date.';
}
if ($errors) {
    $_SESSION['batch_values'] = array_merge($values, ['batch_id' => $batchId]);
    foreach ($errors as $error) {
        setFlash('danger', $error);
    }
    redirect('Farid/supplier/batches.php' . ($batchId ? '?edit=' . $batchId : ''));
}
try {
    saveSupplierBatch(db(), $supplierId, $batchId, $values);
    setFlash('success', $batchId ? 'Batch updated successfully.' : 'Textile batch created successfully.');
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Farid/supplier/batches.php' . ($batchId ? '?edit=' . $batchId : ''));
