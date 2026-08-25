<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('admin');
$supplierId = null;
$sustainabilityTitle = 'Platform textile recirculation';
$sustainabilityAudience = 'admin';
require __DIR__ . '/../includes/sustainability-page.php';
