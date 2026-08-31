<?php

require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('b2c');

$pdo = db();
$buyerId = (int) currentUser()['user_id'];
$orderType = 'B2C';
$buyerRole = 'b2c';
require __DIR__ . '/../../Mixed/includes/buyer-orders-page.php';
