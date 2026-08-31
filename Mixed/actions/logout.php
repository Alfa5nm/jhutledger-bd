<?php

require __DIR__ . '/../includes/bootstrap.php';
requirePost();
verifyCsrf();

// Request flow: logout form -> destroy session -> landing-page redirect.
logoutUser();
session_start();
setFlash('success', 'You have been logged out safely.');
redirect('Mixed/login.php');
