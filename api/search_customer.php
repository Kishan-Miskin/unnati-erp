<?php
/**
 * API: Search Customer for autocomplete (billing page)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$q = sanitize($_GET['q'] ?? '');
if (strlen($q) < 1) jsonSuccess([], 'No query');

$term    = "%$q%";
$results = db()->fetchAll(
    "SELECT id, name, mobile, email, address, customer_type, gstin, notes, outstanding_balance
     FROM customers
     WHERE status = 'active' AND (name LIKE ? OR mobile LIKE ? OR email LIKE ?)
     ORDER BY name ASC LIMIT 15",
    [$term, $term, $term]
);

jsonSuccess($results, count($results) . ' customers found');
