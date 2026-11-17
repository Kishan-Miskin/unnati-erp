<?php
/**
 * Redirect Finished Goods Stock to unified Shop Products
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . APP_URL . '/admin/products/index.php' . $queryString, true, 302);
exit;
