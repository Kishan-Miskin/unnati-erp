<?php
/**
 * API: Export PDF Invoice
 * Uses pure PHP to generate a clean printable HTML invoice and captures it.
 * No external library needed — outputs HTML for browser print-to-PDF.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invalid invoice ID');

$sale = db()->fetchOne(
    "SELECT s.*, u.full_name AS billed_by FROM sales s JOIN users u ON s.created_by=u.id WHERE s.id=?",
    [$id]
);
if (!$sale) die('Invoice not found');

$items = db()->fetchAll(
    "SELECT * FROM sale_items WHERE sale_id=? ORDER BY id",
    [$id]
);

// Business settings
$bizName    = getSetting('business_name', 'Unnatti Enterprises');
$bizAddress = getSetting('business_address', '');
$bizPhone   = getSetting('business_phone', '');
$bizEmail   = getSetting('business_email', '');
$bizGstin   = getSetting('business_gstin', '');
$footer     = getSetting('invoice_footer', 'Thank you for your business!');
$terms      = getSetting('terms_conditions', 'Goods once sold will not be taken back.');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= e($sale['invoice_no']) ?></title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Segoe UI',Arial,sans-serif;font-size:11pt;color:#111;background:#fff}
  .page{max-width:800px;margin:20px auto;padding:30px;border:1px solid #ddd}
  .header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1e3a5f;padding-bottom:16px;margin-bottom:16px}
  .biz-name{font-size:20pt;font-weight:700;color:#1e3a5f}
  .biz-sub{font-size:9pt;color:#555;margin-top:4px}
  .inv-title{text-align:right}
  .inv-title h2{font-size:16pt;color:#1e3a5f;text-transform:uppercase}
  .inv-title p{font-size:10pt;color:#333;margin-top:3px}
  .meta{display:flex;justify-content:space-between;margin-bottom:16px;gap:16px}
  .meta-box{flex:1;background:#f7f9fc;border:1px solid #e0e6ef;border-radius:6px;padding:12px}
  .meta-box h4{font-size:8pt;text-transform:uppercase;letter-spacing:0.08em;color:#666;margin-bottom:6px}
  .meta-box p{font-size:10pt;line-height:1.6}
  table{width:100%;border-collapse:collapse;margin-bottom:14px}
  thead th{background:#1e3a5f;color:#fff;padding:8px 10px;font-size:9pt;text-align:left}
  thead th.r{text-align:right}
  tbody td{padding:7px 10px;font-size:10pt;border-bottom:1px solid #eee}
  tbody td.r{text-align:right}
  tbody tr:nth-child(even){background:#f9fbfd}
  .totals{margin-left:auto;width:280px;border-top:2px solid #1e3a5f;padding-top:8px}
  .totals-row{display:flex;justify-content:space-between;padding:4px 0;font-size:10pt}
  .totals-row.grand{font-size:13pt;font-weight:700;color:#1e3a5f;border-top:1px solid #ccc;padding-top:8px;margin-top:4px}
  .payment-info{background:#eaf4fb;border:1px solid #bee3f8;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:10pt}
  .payment-info.due{background:#fff0f0;border-color:#fca5a5}
  .footer-line{border-top:1px solid #ddd;margin-top:20px;padding-top:12px;text-align:center;font-size:9pt;color:#666}
  .terms{font-size:8.5pt;color:#888;margin-top:8px}
  .sig-box{display:flex;justify-content:space-between;margin-top:30px}
  .sig-line{border-top:1px solid #999;width:160px;text-align:center;padding-top:4px;font-size:9pt;color:#555}
  @media print{
    body{margin:0}
    .page{max-width:100%;margin:0;border:none;padding:16px}
    .no-print{display:none}
  }
</style>
</head>
<body>
<div class="no-print" style="text-align:center;padding:14px;background:#f1f5f9">
  <button onclick="window.print()" style="background:#1e3a5f;color:#fff;border:none;padding:10px 24px;border-radius:6px;cursor:pointer;font-size:14px;margin-right:10px">🖨 Print / Save PDF</button>
  <a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $id ?>" style="color:#2563eb">← Back to Invoice</a>
</div>
<div class="page">
  <!-- Header -->
  <div class="header">
    <div>
      <div class="biz-name"><?= e($bizName) ?></div>
      <div class="biz-sub">
        <?= e($bizAddress) ?><br>
        <?php if ($bizPhone): ?>📞 <?= e($bizPhone) ?><?php endif; ?>
        <?php if ($bizEmail): ?> &bull; ✉ <?= e($bizEmail) ?><?php endif; ?><br>
        <?php if ($bizGstin): ?>GSTIN: <strong><?= e($bizGstin) ?></strong><?php endif; ?>
      </div>
    </div>
    <div class="inv-title">
      <h2>Tax Invoice</h2>
      <p><strong><?= e($sale['invoice_no']) ?></strong></p>
      <p>Date: <?= formatDate($sale['sale_date']) ?></p>
      <?php if ($sale['status'] !== 'active'): ?>
      <p style="color:#dc2626;font-weight:700;margin-top:4px">[<?= strtoupper($sale['status']) ?>]</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bill To & Payment Info -->
  <div class="meta">
    <div class="meta-box">
      <h4>Bill To</h4>
      <p>
        <strong><?= e($sale['customer_name']) ?></strong><br>
        <?php if ($sale['customer_mobile']): ?>📞 <?= e($sale['customer_mobile']) ?><br><?php endif; ?>
        <?php if ($sale['customer_address']): ?><?= e($sale['customer_address']) ?><?php endif; ?>
      </p>
    </div>
    <div class="meta-box">
      <h4>Payment Details</h4>
      <p>
        Method: <strong><?= ucfirst(str_replace('_',' ', $sale['payment_method'])) ?></strong><br>
        Status: <strong><?= ucfirst($sale['payment_status']) ?></strong><br>
        Billed By: <?= e($sale['billed_by']) ?>
      </p>
    </div>
  </div>

  <!-- Items Table -->
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Product</th>
        <th>SKU</th>
        <th>Size</th>
        <th class="r">Qty</th>
        <th class="r">Rate (₹)</th>
        <th class="r">GST%</th>
        <th class="r">GST (₹)</th>
        <th class="r">Discount (₹)</th>
        <th class="r">Total (₹)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i => $item): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><strong><?= e($item['product_name_snapshot']) ?></strong></td>
        <td><?= e($item['sku_snapshot']) ?></td>
        <td><?= e($item['size_snapshot']) ?></td>
        <td class="r"><?= $item['qty'] ?></td>
        <td class="r"><?= number_format($item['selling_price_snapshot'], 2) ?></td>
        <td class="r"><?= $item['gst_pct_snapshot'] ?>%</td>
        <td class="r"><?= number_format($item['gst_amount'], 2) ?></td>
        <td class="r"><?= $item['discount_amount'] > 0 ? number_format($item['discount_amount'], 2) : '—' ?></td>
        <td class="r"><strong><?= number_format($item['line_total'], 2) ?></strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totals -->
  <div style="display:flex;justify-content:flex-end">
    <div class="totals">
      <div class="totals-row"><span>Subtotal</span><span>₹<?= number_format($sale['subtotal'], 2) ?></span></div>
      <?php if ($sale['tax_amount'] > 0): ?>
      <div class="totals-row"><span>GST Total</span><span>₹<?= number_format($sale['tax_amount'], 2) ?></span></div>
      <?php endif; ?>
      <?php if ($sale['discount_amount'] > 0): ?>
      <div class="totals-row" style="color:#dc2626"><span>Discount</span><span>− ₹<?= number_format($sale['discount_amount'], 2) ?></span></div>
      <?php endif; ?>
      <?php if ($sale['additional_charges'] > 0): ?>
      <div class="totals-row"><span>Additional Charges</span><span>₹<?= number_format($sale['additional_charges'], 2) ?></span></div>
      <?php endif; ?>
      <div class="totals-row grand"><span>Grand Total</span><span>₹<?= number_format($sale['grand_total'], 2) ?></span></div>
      <div class="totals-row" style="color:#059669"><span>Received</span><span>₹<?= number_format($sale['amount_received'], 2) ?></span></div>
      <?php if ($sale['balance_due'] > 0): ?>
      <div class="totals-row" style="color:#dc2626;font-weight:700"><span>Balance Due</span><span>₹<?= number_format($sale['balance_due'], 2) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Payment Status Banner -->
  <?php if ($sale['balance_due'] > 0): ?>
  <div class="payment-info due" style="margin-top:16px">
    ⚠ <strong>Balance Due: ₹<?= number_format($sale['balance_due'], 2) ?></strong> — Payment Pending
  </div>
  <?php else: ?>
  <div class="payment-info" style="margin-top:16px">
    ✅ <strong>Fully Paid</strong> — ₹<?= number_format($sale['amount_received'], 2) ?> received via <?= ucfirst(str_replace('_',' ',$sale['payment_method'])) ?>
  </div>
  <?php endif; ?>

  <?php if ($sale['notes']): ?>
  <div style="margin-top:12px;font-size:9.5pt;color:#555"><strong>Notes:</strong> <?= e($sale['notes']) ?></div>
  <?php endif; ?>

  <!-- Signature -->
  <div class="sig-box">
    <div class="sig-line">Customer Signature</div>
    <div class="sig-line">Authorised Signatory<br><small><?= e($bizName) ?></small></div>
  </div>

  <!-- Footer -->
  <div class="footer-line">
    <div><?= e($footer) ?></div>
    <div class="terms"><?= e($terms) ?></div>
  </div>
</div>
</body>
</html>
