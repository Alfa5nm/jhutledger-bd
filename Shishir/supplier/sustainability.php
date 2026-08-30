<?php

require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('supplier');
$supplierId = (int) currentUser()['user_id'];
$sustainabilityTitle = 'My textile recirculation';
$sustainabilityAudience = 'supplier';
require __DIR__ . '/../../Mixed/includes/sustainability-page.php';
