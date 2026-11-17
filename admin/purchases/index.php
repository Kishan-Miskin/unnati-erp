<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

$search = sanitize($_GET['search'] ?? '');
$from   = sanitize($_GET['from'] ?? '');
$to     = sanitize($_GET['to'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($search) { $where[] = '(p.purchase_no LIKE ? OR p.supplier_name LIKE ? OR p.supplier_invoice_no LIKE ?)'; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }
if ($from) { $where[] = 'p.purchase_date>=?'; $params[]=$from; }
if ($to)   { $where[] = 'p.purchase_date<=?'; $params[]=$to; }
$w = implode(' AND ', $where);

$total = db()->fetchOne("SELECT COUNT(*) AS c FROM purchases p WHERE $w", $params)['c'];
$pagination = paginate($total, $page);

$purchases = db()->fetchAll(
    "SELECT p.*, u.full_name AS created_by_name
     FROM purchases p JOIN users u ON p.created_by=u.id
     WHERE $w ORDER BY p.id DESC LIMIT ".DEFAULT_PAGE_SIZE." OFFSET {$pagination['offset']}",
    $params
);
$pageTitle='Purchase History'; $activePage='purchases';
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="page-header">
  <div class="page-header-left"><h1 class="page-title"><i class="fas fa-shopping-cart text-primary" style="margin-right:10px"></i>Purchase History</h1><p class="page-subtitle"><?= number_format($total) ?> purchase(s)</p></div>
  <div class="page-header-right"><a href="<?= APP_URL ?>/admin/purchases/add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Purchase</a></div>
</div>
<div class="card">
  <form method="GET" class="table-filters">
    <input type="text" name="search" class="form-control" placeholder="Purchase no, supplier..." value="<?= e($search) ?>">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
    <a href="<?= APP_URL ?>/admin/purchases/index.php" class="btn btn-light btn-sm"><i class="fas fa-times"></i></a>
    <button type="button" class="btn btn-light btn-sm" onclick="exportTableToCSV('purTable','purchases')" style="margin-left:auto"><i class="fas fa-file-csv"></i> CSV</button>
  </form>
  <div class="table-wrapper">
    <table id="purTable">
      <thead><tr><th>Purchase No</th><th>Date</th><th>Supplier</th><th>Supplier Inv</th><th class="text-center">Items</th><th class="text-center">Qty</th><th class="text-right">Total Amount</th><th>Added By</th><th class="text-right no-print">Actions</th></tr></thead>
      <tbody>
        <?php if (empty($purchases)): ?>
        <tr><td colspan="9"><div class="empty-state"><i class="fas fa-shopping-cart"></i><h4>No purchases yet</h4></div></td></tr>
        <?php else: foreach ($purchases as $p): ?>
        <tr>
          <td>
            <strong><?= e($p['purchase_no']) ?></strong>
            <?php if (!empty($p['notes'])): ?>
            <div style="font-size:0.75rem;color:#64748b;margin-top:2px"><i class="fas fa-sticky-note text-warning"></i> <?= e($p['notes']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= formatDate($p['purchase_date']) ?></td>
          <td><?= e($p['supplier_name'] ?? '—') ?></td>
          <td><span class="badge badge-secondary"><?= e($p['supplier_invoice_no'] ?? '—') ?></span></td>
          <td class="text-center"><?= $p['total_items'] ?></td>
          <td class="text-center"><strong><?= number_format($p['total_qty']) ?></strong></td>
          <td class="text-right"><strong><?= formatCurrency($p['total_amount']) ?></strong></td>
          <td style="font-size:0.82rem"><?= e($p['created_by_name']) ?></td>
          <td class="text-right no-print">
            <a href="<?= APP_URL ?>/admin/purchases/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-light" title="View Inward Bill"><i class="fas fa-eye"></i> View</a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pagination['total_pages'] > 1): ?>
  <div class="card-footer"><?php $baseUrl = APP_URL.'/admin/purchases/index.php?search='.urlencode($search).'&from='.$from.'&to='.$to; echo renderPagination($pagination, $baseUrl); ?></div>
  <?php endif; ?>
</div>
<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
