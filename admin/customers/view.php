<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$customer = db()->fetchOne("SELECT * FROM customers WHERE id=?", [$id]);
if (!$customer) { $_SESSION['flash_error']='Customer not found.'; header('Location:'.APP_URL.'/admin/customers/index.php'); exit; }

// Fetch invoices
$bills = db()->fetchAll(
    "SELECT s.id, s.invoice_no, s.sale_date, s.grand_total, s.amount_received, s.balance_due, s.payment_status, s.status, u.full_name AS employee
     FROM sales s JOIN users u ON s.created_by=u.id
     WHERE s.customer_id=? OR s.customer_name=? ORDER BY s.id DESC LIMIT 50",
    [$id, $customer['name']]
);

// Fetch itemized purchases
$purchasedItems = db()->fetchAll(
    "SELECT si.*, s.id AS sale_id, s.invoice_no, s.sale_date, pv.id AS variant_id, pv.product_id
     FROM sale_items si
     JOIN sales s ON si.sale_id = s.id
     LEFT JOIN product_variants pv ON si.variant_id = pv.id
     WHERE (s.customer_id = ? OR s.customer_name = ?) AND s.status != 'cancelled'
     ORDER BY s.sale_date DESC, si.id DESC LIMIT 100",
    [$id, $customer['name']]
);

$stats = db()->fetchOne(
    "SELECT COUNT(*) AS bills, COALESCE(SUM(grand_total),0) AS spent, COALESCE(SUM(balance_due),0) AS due
     FROM sales WHERE (customer_id=? OR customer_name=?) AND status='active'",
    [$id, $customer['name']]
);

$pageTitle = e($customer['name']); $activePage='customers';
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title"><i class="fas fa-user text-primary" style="margin-right:10px"></i><?= e($customer['name']) ?></h1>
    <p class="page-subtitle"><?= ucfirst($customer['customer_type']) ?> Customer &bull; <?= $customer['status'] === 'active' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>' ?></p>
  </div>
  <div class="page-header-right">
    <a href="<?= APP_URL ?>/admin/billing/create.php?customer_id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Bill / Sale</a>
    <a href="<?= APP_URL ?>/admin/customers/edit.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
    <a href="<?= APP_URL ?>/admin/customers/index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> All Customers</a>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
  <div class="stat-card stat-card-primary"><div class="stat-icon"><i class="fas fa-receipt"></i></div><div class="stat-body"><div class="stat-value"><?= $stats['bills'] ?></div><div class="stat-label">Total Invoices</div></div></div>
  <div class="stat-card stat-card-success"><div class="stat-icon"><i class="fas fa-rupee-sign"></i></div><div class="stat-body"><div class="stat-value" style="font-size:1.1rem"><?= formatCurrency($stats['spent']) ?></div><div class="stat-label">Total Purchased</div></div></div>
  <div class="stat-card <?= $stats['due'] > 0 ? 'stat-card-danger' : 'stat-card-secondary' ?>"><div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div><div class="stat-body"><div class="stat-value" style="font-size:1.1rem"><?= formatCurrency($stats['due']) ?></div><div class="stat-label">Outstanding Due</div></div></div>
</div>

<div class="grid-2">
  <!-- Customer Profile Card -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-address-card"></i> Customer Information</span></div>
    <div class="card-body">
      <table style="width:100%;border-collapse:collapse">
        <?php
        $fields = [
          'Mobile'   => $customer['mobile'] ?? '—',
          'Email'    => $customer['email'] ?? '—',
          'Address'  => $customer['address'] ?? '—',
          'GSTIN'    => $customer['gstin'] ?? '—',
          'Type'     => ucfirst($customer['customer_type'] ?? 'retail'),
          'Notes'    => $customer['notes'] ?? '—',
          'Customer Since' => formatDate($customer['created_at'])
        ];
        foreach ($fields as $label => $val):
        ?>
        <tr style="border-bottom:1px solid #f1f5f9">
          <td style="padding:10px 0;font-size:0.82rem;color:#64748b;width:130px;font-weight:600"><?= $label ?></td>
          <td style="padding:10px 0;font-size:0.85rem"><?= e($val) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- Invoices History Card -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-receipt"></i> Billing / Invoices History</span></div>
    <div class="card-body p-0">
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Invoice No</th><th>Date</th><th class="text-right">Amount</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php if (empty($bills)): ?>
            <tr><td colspan="5" class="text-center text-muted" style="padding:25px">No invoices generated yet</td></tr>
            <?php else: foreach ($bills as $b): ?>
            <tr>
              <td>
                <a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $b['id'] ?>" style="font-weight:600;font-size:0.85rem">
                  <?= e($b['invoice_no']) ?>
                </a>
              </td>
              <td style="font-size:0.82rem"><?= formatDate($b['sale_date']) ?></td>
              <td class="text-right">
                <strong><?= formatCurrency($b['grand_total']) ?></strong>
                <?php if ($b['balance_due'] > 0): ?>
                  <div style="font-size:0.72rem;color:#dc2626">Due: <?= formatCurrency($b['balance_due']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= paymentBadge($b['payment_status']) ?></td>
              <td>
                <a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-light" title="View Invoice">
                  <i class="fas fa-eye"></i> View
                </a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- DETAILED PURCHASED ITEMS / MATERIALS TABLE -->
<div class="card" style="margin-top:20px">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
    <span class="card-title"><i class="fas fa-boxes"></i> What this Customer has Purchased (Materials, Outfits &amp; Products)</span>
    <span class="badge badge-primary"><?= count($purchasedItems) ?> item(s)</span>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Invoice</th>
          <th>Product ID / SKU</th>
          <th>Material / Product Name</th>
          <th class="text-center">Unit / Size</th>
          <th class="text-center">Qty</th>
          <th class="text-right">Unit Price</th>
          <th class="text-right">GST (₹)</th>
          <th class="text-right">Total (₹)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($purchasedItems)): ?>
        <tr><td colspan="9" class="text-center text-muted" style="padding:30px">No purchased items recorded yet</td></tr>
        <?php else: foreach ($purchasedItems as $item): ?>
        <tr>
          <td style="font-size:0.8rem"><?= formatDate($item['sale_date']) ?></td>
          <td>
            <a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $item['sale_id'] ?>" style="font-weight:600;font-size:0.83rem">
              <?= e($item['invoice_no']) ?>
            </a>
          </td>
          <td>
            <span class="sku-badge"><?= e($item['sku_snapshot']) ?></span>
            <?php if (!empty($item['product_id'])): ?>
              <small class="text-muted" style="display:block">ID #<?= $item['product_id'] ?></small>
            <?php endif; ?>
          </td>
          <td>
            <div style="font-weight:600"><?= e($item['product_name_snapshot']) ?></div>
          </td>
          <td class="text-center">
            <span class="badge badge-info"><?= e($item['size_snapshot'] ?: 'Standard') ?></span>
          </td>
          <td class="text-center">
            <strong><?= number_format($item['qty']) ?></strong>
          </td>
          <td class="text-right"><?= formatCurrency($item['selling_price_snapshot']) ?></td>
          <td class="text-right text-muted"><?= formatCurrency($item['gst_amount']) ?></td>
          <td class="text-right" style="font-weight:700"><?= formatCurrency($item['line_total']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
