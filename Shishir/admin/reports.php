<?php

require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('admin');
$reportTitle = 'Platform sales reports';
$reportEyebrow = 'Administration analytics';
$reportAudience = 'admin';
$supplierId = null;
require __DIR__ . '/../../Mixed/includes/report-page.php';
