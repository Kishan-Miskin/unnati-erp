<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();
if (isAdmin()) { header('Location:'.APP_URL.'/admin/billing/index.php'); exit; }

$userId = currentUserId();
$search = sanitize($_GET['search'] ?? '');
$from   = sanitize($_GET['from'] ?? '');
$to     = sanitize($_GET['to'] ?? '');
$page   = max(1,(int)($_GET['page']??1));

$where  = ['s.created_by=?']; $params = [$userId];
if ($search) { $where[] = '(s.invoice_no LIKE ? OR s.customer_name LIKE ?)'; $s="%$search%"; $params=array_merge($params,[$s,$s]); }
if ($from)   { $where[] = 's.sale_date>=?'; $params[]=$from; }
if ($to)     { $where[] = 's.sale_date<=?'; $params[]=$to; }
$w = implode(' AND ', $where);

$total = db()->fetchOne("SELECT COUNT(*) AS c FROM sales s WHERE $w", $params)['c'];
$pagination = paginate($total, $page);

$bills = db()->fetchAll(
    "SELECT s.id, s.invoice_no, s.sale_date, s.customer_name, s.grand_total, s.payment_status, s.status,
            (SELECT COUNT(*) FROM sale_items WHERE sale_id=s.id) AS items
     FROM sales s WHERE $w ORDER BY s.id DESC LIMIT ".DEFAULT_PAGE_SIZE." OFFSET {$pagination['offset']}",
    $params
);

$monthStats = db()->fetchOne(
    "SELECT COUNT(*) AS c, COALESCE(SUM(grand_total),0) AS t FROM sales WHERE created_by=? AND MONTH(sale_date)=MONTH(NOW()) AND status='active'",
    [$userId]
);

$pageTitle='My Bills'; $activePage='my_bills';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title"><i class="fas fa-receipt text-primary" style="margin-right:10px"></i>My Bills</h1>
    <p class="page-subtitle">This month: <strong><?= $monthStats['c'] ?> bill(s)</strong> — <?= formatCurrency($monthStats['t']) ?></p>
  </div>
  <div class="page-header-right"><a href="<?= APP_URL ?>/admin/billing/create.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Bill</a></div>
</div>
<div class="card">
  <form method="GET" class="table-filters">
    <input type="text" name="search" class="form-control" placeholder="Invoice no, customer..." value="<?= e($search) ?>">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
    <a href="<?= APP_URL ?>/employee/my_bills.php" class="btn btn-light btn-sm"><i class="fas fa-times"></i></a>
  </form>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Invoice No</th><th>Date</th><th>Customer</th><th class="text-center">Items</th><th class="text-right">Amount</th><th>Status</th><th class="text-right no-print">Actions</th></tr></thead>
      <tbody>
        <?php if (empty($bills)): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="fas fa-receipt"></i><h4>No bills yet</h4><a href="<?= APP_URL ?>/admin/billing/create.php" class="btn btn-primary mt-3"><i class="fas fa-plus"></i> Create First Bill</a></div></td></tr>
        <?php else: foreach ($bills as $b): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $b['id'] ?>" style="font-weight:700;font-size:0.85rem"><?= e($b['invoice_no']) ?></a></td>
          <td><?= formatDate($b['sale_date']) ?></td>
          <td><?= e($b['customer_name']) ?></td>
          <td class="text-center"><?= $b['items'] ?></td>
          <td class="text-right text-bold"><?= formatCurrency($b['grand_total']) ?></td>
          <td><?= billStatusBadge($b['status']) ?> <?= paymentBadge($b['payment_status']) ?></td>
          <td class="text-right no-print">
            <a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-light" title="View"><i class="fas fa-eye"></i></a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pagination['total_pages']>1): ?>
  <div class="card-footer"><?php echo renderPagination($pagination, APP_URL.'/employee/my_bills.php?search='.urlencode($search).'&from='.$from.'&to='.$to); ?></div>
  <?php endif; ?>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
