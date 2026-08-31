<?php

require __DIR__ . '/../includes/bootstrap.php';
requirePost();
verifyCsrf();

// Request flow: registration form -> registerAccount() -> users/subtype INSERT -> login redirect.
$values = [
    'name' => input('name'),
    'email' => input('email'),
    'phone' => input('phone'),
    'street' => input('street'),
    'city' => input('city'),
    'district' => input('district'),
    'postal_code' => input('postal_code'),
    'role' => input('role'),
];
$password = (string) ($_POST['password'] ?? '');
$passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
$errors = [];

if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 120) {
    $errors[] = 'Name must be between 2 and 120 characters.';
}
if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address.';
}
if (!preg_match('/^[0-9+ -]{7,30}$/', $values['phone'])) {
    $errors[] = 'Enter a valid phone number.';
}
foreach (['street', 'city', 'district', 'postal_code'] as $field) {
    if ($values[$field] === '') {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
    }
}
if (!in_array($values['role'], ['supplier', 'b2b', 'b2c'], true)) {
    $errors[] = 'Select a valid account role.';
}
if (strlen($password) < 8) {
    $errors[] = 'Password must contain at least 8 characters.';
}
if ($password !== $passwordConfirmation) {
    $errors[] = 'Password confirmation does not match.';
}

if ($errors) {
    $_SESSION['register_values'] = $values;
    $_SESSION['register_errors'] = $errors;
    redirect('Mixed/register.php');
}

try {
    registerAccount(db(), $values, $password);
    setFlash('success', 'Your account has been created. You can now log in.');
    redirect('Mixed/login.php');
} catch (PDOException $exception) {
    $_SESSION['register_values'] = $values;
    $_SESSION['register_errors'] = [
        $exception->getCode() === '23000'
            ? 'That email address is already registered.'
            : 'Registration could not reach the database. Please try again.',
    ];
    redirect('Mixed/register.php');
}
