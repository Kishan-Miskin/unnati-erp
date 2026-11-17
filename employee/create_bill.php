<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();
if (isAdmin()) { header('Location:'.APP_URL.'/admin/billing/create.php'); exit; }

// Employee create bill — same as admin create bill but using employee sidebar
$pageTitle  = 'Create Bill'; $activePage = 'create_bill';
$user       = currentUser();

// Load create bill but with employee context
// Simply redirect to the shared billing create (it auto-detects role via isAdmin())
header('Location: '.APP_URL.'/admin/billing/create.php');
exit;
