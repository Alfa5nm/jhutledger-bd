<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('supplier');
verifyCsrf();

// Request flow: listing form -> saveSupplierListing() -> listing/subtype INSERT or UPDATE -> listings redirect.
$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT) ?: null;
$type = input('listing_type');
$values = [
    'batch_id' => (int) input('batch_id'),
    'listing_type' => $type,
    'listed_quantity' => (float) input('listed_quantity'),
    'status' => input('status'),
    'minimum_quantity' => $type === 'B2B' ? (float) input('minimum_quantity') : 0.0,
    'bulk_unit_price' => $type === 'B2B' ? (float) input('bulk_unit_price') : 0.0,
    'bundle_size' => $type === 'B2C' ? (float) input('bundle_size') : 0.0,
    'fixed_unit_price' => $type === 'B2C' ? (float) input('fixed_unit_price') : 0.0,
];
$errors = [];
if ($values['batch_id'] <= 0 || $values['listed_quantity'] <= 0) {
    $errors[] = 'Select a batch and enter a positive listed quantity.';
}
if (!in_array($type, ['B2B', 'B2C'], true)) {
    $errors[] = 'Select a valid listing type.';
}
if (!in_array($values['status'], ['Active', 'Inactive'], true)) {
    $errors[] = 'Select a valid status.';
}
if ($type === 'B2B' && ($values['minimum_quantity'] <= 0 || $values['minimum_quantity'] > $values['listed_quantity'])) {
    $errors[] = 'Minimum order quantity must be positive and cannot exceed the listed quantity.';
}
if ($type === 'B2B' && $values['bulk_unit_price'] <= 0) {
    $errors[] = 'Enter a positive wholesale unit price.';
}
if ($type === 'B2C' && ($values['bundle_size'] <= 0 || $values['bundle_size'] > $values['listed_quantity'])) {
    $errors[] = 'Bundle quantity must be positive and cannot exceed the listed quantity.';
}
if ($type === 'B2C' && $values['fixed_unit_price'] <= 0) {
    $errors[] = 'Enter a positive retail unit price.';
}
if ($errors) {
    $_SESSION['listing_values'] = array_merge($values, ['listing_id' => $listingId]);

    foreach ($errors as $error) {
        setFlash('danger', $error);
    }
    redirect('Farid/supplier/listings.php' . ($listingId ? '?edit=' . $listingId : ''));
}
try {
    saveSupplierListing(db(), (int) currentUser()['user_id'], $listingId, $values);
    setFlash('success', $listingId ? 'Listing updated successfully.' : 'Marketplace listing created.');
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Farid/supplier/listings.php' . ($listingId ? '?edit=' . $listingId : ''));
