<?php

require __DIR__ . '/../includes/bootstrap.php';
requirePost();
requireLogin();
verifyCsrf();

// Request flow: profile form -> updateAccountProfile() -> users UPDATE -> profile redirect.
$values = [
    'name' => input('name'),
    'phone' => input('phone'),
    'street' => input('street'),
    'city' => input('city'),
    'district' => input('district'),
    'postal_code' => input('postal_code'),
];
$errors = [];
if (mb_strlen($values['name']) < 2 || !preg_match('/^[0-9+ -]{7,30}$/', $values['phone'])) {
    $errors[] = 'Enter a valid name and phone number.';
}
foreach (['street', 'city', 'district', 'postal_code'] as $field) {
    if ($values[$field] === '') {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
    }
}
if ($errors) {
    $_SESSION['profile_values'] = $values;
    foreach ($errors as $error) {
        setFlash('danger', $error);
    }
    redirect('Mixed/profile.php');
}

try {
    $pdo = db();
    updateAccountProfile($pdo, (int) currentUser()['user_id'], $values);
    refreshSessionUser($pdo);
    setFlash('success', 'Profile updated successfully.');
} catch (Throwable) {
    $_SESSION['profile_values'] = $values;
    setFlash('danger', 'The profile could not be updated. Please try again.');
}

redirect('Mixed/profile.php');
