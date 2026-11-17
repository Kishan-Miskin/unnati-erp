<?php
/**
 * API: Calculate pricing preview
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$purchasePrice    = (float)($_POST['purchase_price']    ?? $_GET['purchase_price']    ?? 0);
$purchaseGstPct   = (float)($_POST['purchase_gst_pct']  ?? $_GET['purchase_gst_pct']  ?? getSetting('purchase_gst_pct','5'));
$profitMarkupPct  = (float)($_POST['profit_markup_pct'] ?? $_GET['profit_markup_pct'] ?? getSetting('default_profit_markup_pct','65'));
$salesGstPct      = (float)($_POST['sales_gst_pct']     ?? $_GET['sales_gst_pct']     ?? getSetting('sales_gst_pct','5'));
$qty              = (int)($_POST['qty']                  ?? $_GET['qty']               ?? 1);

if ($purchasePrice <= 0) jsonError('Purchase price must be positive');

$pricing = calculatePricing($purchasePrice, $purchaseGstPct, $profitMarkupPct, $salesGstPct, $qty);
jsonSuccess($pricing, 'Pricing calculated');
