<?php
/**
 * Admin: View Bill (non-printable detail view)
 * Redirects to invoice.php for now — invoice.php already serves both
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    $_SESSION['flash_error'] = 'No bill ID specified.';
    header('Location: ' . APP_URL . '/admin/billing/index.php');
    exit;
}
header('Location: ' . APP_URL . '/admin/billing/invoice.php?id=' . $id);
exit;
