<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('b2b');
verifyCsrf();

// Request flow: marketplace form -> createBuyerQuotation() -> quotation INSERT -> quotations redirect.
$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$quantity = (float) input('requested_quantity');
$price = (float) input('proposed_price');
$expiryDate = input('expiry_date');
try {
    if (!$listingId || $quantity <= 0 || $price < 0 || !validDate($expiryDate) || $expiryDate < date('Y-m-d')) {
        throw new RuntimeException('Enter valid quotation terms.');
    }
    createBuyerQuotation(db(), (int) currentUser()['user_id'], $listingId, $quantity, $price, $expiryDate);
    setFlash('success', 'Quotation request sent to the supplier.');
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
redirect('Abir/b2b/quotations.php');
