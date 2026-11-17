<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$sale = db()->fetchOne("SELECT * FROM sales WHERE id=? AND status='active'", [$id]);
if (!$sale) { $_SESSION['flash_error']='Bill not found or already cancelled.'; header('Location:'.APP_URL.'/admin/billing/index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $reason = sanitize($_POST['reason'] ?? '');
    if (empty($reason)) { $_SESSION['flash_error']='Cancellation reason required.'; header('Location:'.APP_URL.'/admin/billing/cancel.php?id='.$id); exit; }

    try {
        db()->beginTransaction();

        // Get sale items and restore stock
        $items = db()->fetchAll("SELECT * FROM sale_items WHERE sale_id=?", [$id]);
        foreach ($items as $item) {
            recordStockMovement($item['variant_id'], 'return', $item['qty'], $id, 'sale', 'Bill cancelled: '.$sale['invoice_no'], currentUserId());
        }

        // Mark bill cancelled
        db()->execute("UPDATE sales SET status='cancelled', updated_at=NOW() WHERE id=?", [$id]);

        // Restore customer outstanding
        if ($sale['customer_id']) {
            db()->execute("UPDATE customers SET outstanding_balance = outstanding_balance - ? WHERE id=?", [$sale['balance_due'], $sale['customer_id']]);
        }

        // Record cancellation
        db()->insert("INSERT INTO bill_cancellations (sale_id, cancellation_reason, cancelled_by, cancelled_at) VALUES (?,?,?,NOW())", [$id, $reason, currentUserId()]);

        db()->commit();
        logActivity("Cancelled bill: {$sale['invoice_no']}", 'billing', $id, 'sale');
        $_SESSION['flash_success'] = "Bill {$sale['invoice_no']} cancelled. Stock restored.";
        header('Location: '.APP_URL.'/admin/billing/invoice.php?id='.$id); exit;
    } catch (Exception $e) {
        db()->rollback();
        $_SESSION['flash_error'] = 'Cancellation failed: '.$e->getMessage();
        header('Location:'.APP_URL.'/admin/billing/cancel.php?id='.$id); exit;
    }
}

$pageTitle='Cancel Bill'; $activePage='billing';
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="page-header">
  <div class="page-header-left"><h1 class="page-title"><i class="fas fa-ban text-danger" style="margin-right:10px"></i>Cancel Bill: <?= e($sale['invoice_no']) ?></h1></div>
  <div class="page-header-right"><a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $id ?>" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a></div>
</div>
<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> Cancelling a bill will restore all product stock quantities. This action cannot be undone.</div>
<div style="max-width:520px">
  <div class="card">
    <div class="card-header"><span class="card-title">Bill Summary</span></div>
    <div class="card-body">
      <table style="width:100%">
        <tr><td class="text-muted">Invoice No</td><td class="text-bold"><?= e($sale['invoice_no']) ?></td></tr>
        <tr><td class="text-muted">Customer</td><td><?= e($sale['customer_name']) ?></td></tr>
        <tr><td class="text-muted">Amount</td><td class="text-bold"><?= formatCurrency($sale['grand_total']) ?></td></tr>
        <tr><td class="text-muted">Date</td><td><?= formatDate($sale['sale_date']) ?></td></tr>
      </table>
    </div>
  </div>
  <div class="card" style="margin-top:16px">
    <form method="POST">
      <?= csrfField() ?>
      <div class="card-body">
        <div class="form-group mb-0">
          <label class="form-label">Reason for Cancellation <span class="required">*</span></label>
          <textarea name="reason" class="form-control" rows="3" required placeholder="Provide a clear reason for cancellation..."></textarea>
        </div>
      </div>
      <div class="card-footer d-flex gap-3">
        <a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $id ?>" class="btn btn-light">Go Back</a>
        <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Confirm Cancellation</button>
      </div>
    </form>
  </div>
</div>
<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
