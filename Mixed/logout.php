<?php

require __DIR__ . '/includes/bootstrap.php';

// Logout mutations now use Mixed/actions/logout.php.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(405);
    header('Allow: GET');
    exit('Use the logout form from the application navigation.');
}

redirect(isLoggedIn() ? dashboardPath() : 'Mixed/login.php');
