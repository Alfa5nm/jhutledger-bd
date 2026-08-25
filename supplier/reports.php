<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('supplier');
$reportTitle = 'Sales and profit reports';
$reportEyebrow = 'Supplier performance';
$reportAudience = 'supplier';
$supplierId = (int) currentUser()['user_id'];
require __DIR__ . '/../includes/report-page.php';
