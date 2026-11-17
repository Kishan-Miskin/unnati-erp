<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

$id   = (int)($_GET['id'] ?? 0);
$sale = db()->fetchOne("SELECT * FROM sales WHERE id=? AND status='active'", [$id]);
if (!$sale) { $_SESSION['flash_error']='Bill not found.'; header('Location:'.APP_URL.'/admin/billing/index.php'); exit; }

$saleItems = db()->fetchAll("SELECT * FROM sale_items WHERE sale_id=?", [$id]);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $reason      = sanitize($_POST['reason'] ?? '');
    $returnItems = $_POST['return_qty'] ?? [];
    if (empty($reason)) { $errors[] = 'Return reason required.'; }

    $validReturns = [];
    foreach ($returnItems as $itemId => $retQty) {
        $retQty = (int)$retQty;
        if ($retQty <= 0) continue;
        $item = null;
        foreach ($saleItems as $si) { if ($si['id'] == $itemId) { $item = $si; break; } }
        if (!$item) continue;
        if ($retQty > $item['qty']) { $errors[] = "Return qty for {$item['product_name_snapshot']} cannot exceed {$item['qty']}."; continue; }
        $validReturns[] = array_merge($item, ['return_qty' => $retQty]);
    }

    if (empty($validReturns) && empty($errors)) { $errors[] = 'Select at least one item to return.'; }

    if (empty($errors)) {
        try {
            db()->beginTransaction();
            $returnNo    = generateReturnNumber();
            $returnTotal = 0;

            foreach ($validReturns as $rv) {
                $lineRefund = round($rv['line_total'] / $rv['qty'] * $rv['return_qty'], 2);
                $returnTotal += $lineRefund;

                // Restore stock
                recordStockMovement($rv['variant_id'], 'return', $rv['return_qty'], $id, 'sale', 'Return: '.$returnNo, currentUserId());
            }

            // Create return record
            $returnId = db()->insert(
                "INSERT INTO sales_returns (return_no, sale_id, return_reason, return_amount, status, created_by)
                 VALUES (?,?,?,?,?,?)",
                [$returnNo, $id, $reason, $returnTotal, 'approved', currentUserId()]
            );

            foreach ($validReturns as $rv) {
                $lineRefund = round($rv['line_total'] / $rv['qty'] * $rv['return_qty'], 2);
                db()->insert(
                    "INSERT INTO sales_return_items (return_id, sale_item_id, variant_id, qty_returned, refund_amount)
                     VALUES (?,?,?,?,?)",
                    [$returnId, $rv['id'], $rv['variant_id'], $rv['return_qty'], $lineRefund]
                );
            }

            // Update sale status
            db()->execute("UPDATE sales SET status='returned', updated_at=NOW() WHERE id=?", [$id]);

            db()->commit();
            logActivity("Return processed: $returnNo", 'billing', $id, 'sale');
            $_SESSION['flash_success'] = "Return $returnNo processed. Refund: " . formatCurrency($returnTotal);
            header('Location: '.APP_URL.'/admin/billing/invoice.php?id='.$id); exit;
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Error: '.$e->getMessage();
        }
    }
}

$pageTitle='Process Return'; $activePage='billing';
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="page-header">
  <div class="page-header-left"><h1 class="page-title"><i class="fas fa-undo text-warning" style="margin-right:10px"></i>Process Return: <?= e($sale['invoice_no']) ?></h1></div>
  <div class="page-header-right"><a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $id ?>" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a></div>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<form method="POST">
  <?= csrfField() ?>
  <div class="card">
    <div class="card-header"><span class="card-title">Select Items to Return</span></div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Product</th><th>SKU</th><th class="text-center">Sold Qty</th><th class="text-center">Return Qty</th><th class="text-right">Unit Price</th></tr></thead>
        <tbody>
          <?php foreach ($saleItems as $item): ?>
          <tr>
            <td><?= e($item['product_name_snapshot']) ?> <small class="text-muted"><?= e($item['size_snapshot']) ?></small></td>
            <td><span class="sku-badge"><?= e($item['sku_snapshot']) ?></span></td>
            <td class="text-center"><?= $item['qty'] ?></td>
            <td class="text-center" style="width:120px"><input type="number" name="return_qty[<?= $item['id'] ?>]" class="form-control qty-input" min="0" max="<?= $item['qty'] ?>" value="0"></td>
            <td class="text-right"><?= formatCurrency($item['line_total'] / $item['qty']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-body">
      <div class="form-group mb-0">
        <label class="form-label">Return Reason <span class="required">*</span></label>
        <textarea name="reason" class="form-control" rows="2" required placeholder="Reason for return/exchange..."></textarea>
      </div>
    </div>
    <div class="card-footer d-flex gap-3">
      <a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $id ?>" class="btn btn-light">Cancel</a>
      <button type="submit" class="btn btn-warning"><i class="fas fa-undo"></i> Process Return</button>
    </div>
  </div>
</form>
<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
