<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$errors = [];

$actionFiles = [
    'Mixed/actions/login.php',
    'Mixed/actions/register.php',
    'Mixed/actions/update-profile.php',
    'Mixed/actions/logout.php',
    'Mixed/actions/cancel-order.php',
    'Mixed/actions/repeat-order.php',
    'Mixed/actions/return-order.php',
    'Farid/supplier/actions/save-batch.php',
    'Farid/supplier/actions/archive-batch.php',
    'Farid/supplier/actions/save-listing.php',
    'Farid/supplier/actions/archive-listing.php',
    'Abir/b2b/actions/create-quotation.php',
    'Abir/b2b/actions/accept-quotation.php',
    'Abir/b2b/actions/cancel-quotation.php',
    'Abir/b2c/actions/place-order.php',
    'Abir/supplier/actions/accept-quotation.php',
    'Abir/supplier/actions/counter-quotation.php',
    'Abir/supplier/actions/reject-quotation.php',
    'Abir/supplier/actions/process-order.php',
    'Abir/supplier/actions/complete-order.php',
    'Abir/supplier/actions/cancel-order.php',
    'Shishir/actions/submit-payment.php',
    'Shishir/admin/actions/update-payment.php',
    'Shishir/admin/actions/update-user-status.php',
];

foreach ($actionFiles as $relativePath) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($path)) {
        $errors[] = "Missing action: {$relativePath}";
        continue;
    }

    $contents = (string) file_get_contents($path);
    foreach (['requirePost();', 'verifyCsrf();', 'Request flow:'] as $requiredText) {
        if (!str_contains($contents, $requiredText)) {
            $errors[] = "{$relativePath} is missing {$requiredText}";
        }
    }
}

$displayFiles = [
    'Mixed/login.php',
    'Mixed/register.php',
    'Mixed/profile.php',
    'Mixed/return.php',
    'Farid/supplier/batches.php',
    'Farid/supplier/listings.php',
    'Abir/b2b/quotations.php',
    'Abir/b2c/orders.php',
    'Abir/supplier/orders.php',
    'Abir/supplier/quotations.php',
    'Shishir/payment.php',
    'Shishir/admin/payments.php',
    'Shishir/admin/users.php',
];

foreach ($displayFiles as $relativePath) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $contents = (string) file_get_contents($path);

    if (preg_match('/REQUEST_METHOD[^\n]+POST/', $contents) === 1) {
        $errors[] = "Display page still handles POST: {$relativePath}";
    }
}

if ($errors) {
    fwrite(STDERR, "Request-flow check failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo 'Request-flow check passed for ' . count($actionFiles) . " POST endpoints.\n";
