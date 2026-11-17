<?php
/**
 * API: Get single product variant by ID
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) jsonError('No ID provided');

$v = db()->fetchOne(
    "SELECT pv.*, p.name AS product_name, p.brand
     FROM product_variants pv JOIN products p ON pv.product_id = p.id
     WHERE pv.id = ? AND pv.status = 'active' AND p.status = 'active'",
    [$id]
);

if (!$v) jsonError('Product not found', 404);

jsonSuccess([
    'id'                       => (int)$v['id'],
    'sku'                      => $v['sku'],
    'name'                     => $v['product_name'],
    'brand'                    => $v['brand'] ?? '',
    'size'                     => $v['size'],
    'current_stock'            => (int)$v['current_stock'],
    'purchase_price'           => (float)$v['purchase_price'],
    'purchase_gst_pct'         => (float)$v['purchase_gst_pct'],
    'purchase_gst_amount'      => (float)$v['purchase_gst_amount'],
    'purchase_price_with_gst'  => (float)$v['purchase_price_with_gst'],
    'profit_markup_pct'        => (float)$v['profit_markup_pct'],
    'profit_amount'            => (float)$v['profit_amount'],
    'selling_price_before_gst' => (float)$v['selling_price_before_gst'],
    'sales_gst_pct'            => (float)$v['sales_gst_pct'],
    'sales_gst_amount'         => (float)$v['sales_gst_amount'],
    'selling_price'            => (float)$v['selling_price'],
    'tag_price'                => (float)$v['tag_price'],
]);
