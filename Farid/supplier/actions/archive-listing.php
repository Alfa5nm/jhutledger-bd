<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('supplier');
verifyCsrf();

// Request flow: archive form -> archiveSupplierListing() -> listing UPDATE -> listings redirect.
$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
try {
    if (!$listingId) {
        throw new RuntimeException('Select a valid listing.');
    }
    archiveSupplierListing(db(), (int) currentUser()['user_id'], $listingId);
    setFlash('success', 'Listing archived.');
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Farid/supplier/listings.php');
