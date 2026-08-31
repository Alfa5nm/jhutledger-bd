<?php

require __DIR__ . '/../includes/bootstrap.php';
requirePost();
verifyCsrf();

// Request flow: login form -> authenticateAccount() -> users SELECT -> dashboard redirect.
$email = strtolower(input('email'));
$password = (string) ($_POST['password'] ?? '');

try {
    $user = authenticateAccount(db(), $email, $password);
    $baseRole = getUserRole(db(), (int) $user['user_id']);
    loginUser($user, $baseRole);
    setFlash('success', 'Welcome back, ' . $user['name'] . '.');
    redirect(dashboardPath());
} catch (RuntimeException $exception) {
    $_SESSION['login_email'] = $email;
    setFlash('danger', $exception->getMessage());
    redirect('Mixed/login.php');
} catch (Throwable) {
    $_SESSION['login_email'] = $email;
    setFlash('danger', 'Database connection unavailable or account integrity check failed.');
    redirect('Mixed/login.php');
}
