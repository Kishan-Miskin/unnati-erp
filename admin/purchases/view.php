<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$purchase = db()->fetchOne("SELECT p.*, u.full_name AS created_by_name FROM purchases p JOIN users u ON p.created_by=u.id WHERE p.id=?", [$id]);
if (!$purchase) { $_SESSION['flash_error']='Purchase not found.'; header('Location:'.APP_URL.'/admin/purchases/index.php'); exit; }

$items = db()->fetchAll(
    "SELECT pi.*, pv.sku, pv.size, pr.name AS product_name, pr.brand AS material_type,
            c.name AS category_name, sc.name AS subcategory_name
     FROM purchase_items pi
     JOIN product_variants pv ON pi.variant_id=pv.id
     JOIN products pr ON pv.product_id=pr.id
     LEFT JOIN categories c ON pr.category_id=c.id
     LEFT JOIN categories sc ON pr.subcategory_id=sc.id
     WHERE pi.purchase_id=? ORDER BY pi.id",
    [$id]
);

$pageTitle = 'Purchase ' . $purchase['purchase_no']; $activePage = 'purchases';
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="page-header">
  <div class="page-header-left"><h1 class="page-title"><?= e($purchase['purchase_no']) ?></h1><p class="page-subtitle"><?= formatDate($purchase['purchase_date']) ?></p></div>
  <div class="page-header-right">
    <button onclick="printInvoice()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Print</button>
    <a href="<?= APP_URL ?>/admin/purchases/index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>
<div class="card">
  <div class="card-body">
    <div class="form-row cols-3" style="margin-bottom:20px">
      <div><label class="form-label text-muted">Purchase No</label><div class="text-bold"><?= e($purchase['purchase_no']) ?></div></div>
      <div><label class="form-label text-muted">Date</label><div><?= formatDate($purchase['purchase_date']) ?></div></div>
      <div><label class="form-label text-muted">Supplier</label><div><?= e($purchase['supplier_name'] ?? '—') ?></div></div>
      <div><label class="form-label text-muted">Supplier Invoice</label><div><?= e($purchase['supplier_invoice_no'] ?? '—') ?></div></div>
      <div><label class="form-label text-muted">Entered By</label><div><?= e($purchase['created_by_name']) ?></div></div>
      <div><label class="form-label text-muted">Total Amount</label><div class="text-bold text-primary" style="font-size:1.1rem"><?= formatCurrency($purchase['total_amount']) ?></div></div>
    </div>
    <?php if ($purchase['notes']): ?>
    <div class="alert alert-info"><i class="fas fa-info-circle"></i> <?= e($purchase['notes']) ?></div>
    <?php endif; ?>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>SKU</th>
            <th>Material / Product</th>
            <th>Category / Type</th>
            <th>Unit</th>
            <th class="text-center">Qty</th>
            <th class="text-right">Price (excl. GST)</th>
            <th class="text-right">GST %</th>
            <th class="text-right">GST Amount</th>
            <th class="text-right">Price+GST</th>
            <th class="text-right">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $subtotalNoGst = 0;
          $totalGst = 0;
          foreach ($items as $i => $item): 
            $lineNet = $item['purchase_price'] * $item['qty'];
            $lineGst = $item['purchase_gst_amount'] * $item['qty'];
            $subtotalNoGst += $lineNet;
            $totalGst += $lineGst;
          ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><span class="sku-badge"><?= e($item['sku']) ?></span></td>
            <td>
              <div style="font-weight:600"><?= e($item['product_name']) ?></div>
              <?php if (!empty($item['material_type'])): ?>
              <small class="text-muted"><i class="fas fa-tag"></i> <?= e($item['material_type']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($item['category_name'])): ?>
              <span class="badge badge-secondary"><?= e($item['category_name']) ?><?= !empty($item['subcategory_name']) ? ' › '.e($item['subcategory_name']) : '' ?></span>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-info"><?= e($item['size']) ?></span></td>
            <td class="text-center"><strong><?= number_format($item['qty']) ?></strong></td>
            <td class="text-right"><?= formatCurrency($item['purchase_price']) ?></td>
            <td class="text-right"><?= $item['purchase_gst_pct'] ?>%</td>
            <td class="text-right text-muted"><?= formatCurrency($lineGst) ?></td>
            <td class="text-right"><?= formatCurrency($item['purchase_price_with_gst']) ?></td>
            <td class="text-right"><strong><?= formatCurrency($item['total_amount']) ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f8fafc">
            <td colspan="10" class="text-right text-muted">Subtotal (excl. GST)</td>
            <td class="text-right font-weight-bold"><?= formatCurrency($subtotalNoGst) ?></td>
          </tr>
          <tr style="background:#f8fafc">
            <td colspan="10" class="text-right text-muted">Total GST</td>
            <td class="text-right text-muted"><?= formatCurrency($totalGst) ?></td>
          </tr>
          <tr style="background:#f1f5f9;border-top:2px solid #cbd5e1">
            <td colspan="10" class="text-right text-bold" style="font-size:1.05rem">Grand Total</td>
            <td class="text-right text-bold text-primary" style="font-size:1.15rem"><?= formatCurrency($purchase['total_amount']) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
