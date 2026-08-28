<?php

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}
verifyCsrf();
logoutUser();
session_start();
setFlash('success', 'You have been logged out safely.');
redirect('login.php');
