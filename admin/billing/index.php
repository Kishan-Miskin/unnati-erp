<?php
/**
 * Admin: Bill List
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

$search  = sanitize($_GET['search'] ?? '');
$status  = sanitize($_GET['status'] ?? '');
$payMode = sanitize($_GET['payment'] ?? '');
$empId   = (int)($_GET['employee'] ?? 0);
$from    = sanitize($_GET['from'] ?? '');
$to      = sanitize($_GET['to'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($search) { $where[] = '(s.invoice_no LIKE ? OR s.customer_name LIKE ? OR s.customer_mobile LIKE ?)'; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }
if ($status) { $where[] = 's.status=?'; $params[]=$status; }
if ($payMode) { $where[] = 's.payment_method=?'; $params[]=$payMode; }
if ($empId) { $where[] = 's.created_by=?'; $params[]=$empId; }
if ($from) { $where[] = 's.sale_date>=?'; $params[]=$from; }
if ($to) { $where[] = 's.sale_date<=?'; $params[]=$to; }
$whereStr = implode(' AND ', $where);

$total = db()->fetchOne("SELECT COUNT(*) AS c FROM sales s WHERE $whereStr", $params)['c'];
$pagination = paginate($total, $page);

$bills = db()->fetchAll(
    "SELECT s.id, s.invoice_no, s.sale_date, s.customer_name, s.grand_total, s.amount_received,
            s.balance_due, s.payment_status, s.payment_method, s.status,
            u.full_name AS employee,
            (SELECT COUNT(*) FROM sale_items WHERE sale_id=s.id) AS item_count
     FROM sales s JOIN users u ON s.created_by=u.id
     WHERE $whereStr ORDER BY s.id DESC LIMIT " . DEFAULT_PAGE_SIZE . " OFFSET {$pagination['offset']}",
    $params
);

$employees = db()->fetchAll("SELECT id, full_name FROM users ORDER BY full_name");
$pageTitle = 'All Bills'; $activePage = 'billing';
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<script>window.APP_URL='<?= APP_URL ?>';</script>
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title"><i class="fas fa-receipt text-primary" style="margin-right:10px"></i>All Bills</h1>
    <p class="page-subtitle"><?= number_format($total) ?> bill(s)</p>
  </div>
  <div class="page-header-right">
    <a href="<?= APP_URL ?>/admin/billing/create.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Bill</a>
  </div>
</div>
<div class="card">
  <form method="GET" class="table-filters">
    <input type="text" name="search" class="form-control" placeholder="Invoice no, customer..." value="<?= e($search) ?>">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <select name="status" class="form-select">
      <option value="">All Status</option>
      <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
      <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
      <option value="returned" <?= $status==='returned'?'selected':'' ?>>Returned</option>
    </select>
    <select name="payment" class="form-select">
      <option value="">All Payment</option>
      <option value="cash">Cash</option>
      <option value="upi">UPI</option>
      <option value="card">Card</option>
      <option value="credit">Credit</option>
    </select>
    <select name="employee" class="form-select">
      <option value="">All Employees</option>
      <?php foreach ($employees as $e): ?>
      <option value="<?= $e['id'] ?>" <?= $empId==$e['id']?'selected':'' ?>><?= e($e['full_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
    <a href="<?= APP_URL ?>/admin/billing/index.php" class="btn btn-light btn-sm"><i class="fas fa-times"></i></a>
    <button type="button" class="btn btn-light btn-sm" onclick="exportTableToCSV('billsTable','bills')" style="margin-left:auto"><i class="fas fa-file-csv"></i> CSV</button>
  </form>
  <div class="table-wrapper">
    <table id="billsTable">
      <thead><tr><th>Invoice No</th><th>Date</th><th>Customer</th><th>Employee</th><th>Items</th><th class="text-right">Amount</th><th>Payment</th><th>Status</th><th class="text-right no-print">Actions</th></tr></thead>
      <tbody>
        <?php if (empty($bills)): ?>
        <tr><td colspan="9"><div class="empty-state"><i class="fas fa-receipt"></i><h4>No bills found</h4></div></td></tr>
        <?php else: foreach ($bills as $b): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $b['id'] ?>" style="font-weight:700;font-size:0.85rem"><?= e($b['invoice_no']) ?></a></td>
          <td><?= formatDate($b['sale_date']) ?></td>
          <td><?= e($b['customer_name']) ?></td>
          <td style="font-size:0.82rem"><?= e($b['employee']) ?></td>
          <td class="text-center"><?= $b['item_count'] ?></td>
          <td class="text-right">
            <strong><?= formatCurrency($b['grand_total']) ?></strong>
            <?php if ($b['balance_due'] > 0): ?>
            <div style="font-size:0.72rem;color:#dc2626">Due: <?= formatCurrency($b['balance_due']) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-secondary" style="font-size:0.7rem"><?= ucfirst(str_replace('_',' ',$b['payment_method'])) ?></span></td>
          <td><?= billStatusBadge($b['status']) ?> <?= paymentBadge($b['payment_status']) ?></td>
          <td class="text-right no-print">
            <div class="table-actions">
              <a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-light" title="View/Print"><i class="fas fa-eye"></i></a>
              <?php if ($b['status'] === 'active'): ?>
              <a href="<?= APP_URL ?>/admin/billing/return.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary" title="Return"><i class="fas fa-undo"></i></a>
              <a href="<?= APP_URL ?>/admin/billing/cancel.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger" title="Cancel"><i class="fas fa-ban"></i></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pagination['total_pages'] > 1): ?>
  <div class="card-footer">
    <?php $baseUrl = APP_URL.'/admin/billing/index.php?search='.urlencode($search).'&status='.$status.'&payment='.$payMode.'&employee='.$empId.'&from='.$from.'&to='.$to; echo renderPagination($pagination, $baseUrl); ?>
  </div>
  <?php endif; ?>
</div>
<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
