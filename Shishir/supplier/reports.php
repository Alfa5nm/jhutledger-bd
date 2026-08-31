<?php

require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('supplier');
$reportTitle = 'Sales and profit reports';
$reportEyebrow = 'Supplier performance';
$reportAudience = 'supplier';
$supplierId = (int) currentUser()['user_id'];
require __DIR__ . '/../../Mixed/includes/report-page.php';
