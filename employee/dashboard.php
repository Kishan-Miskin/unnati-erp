<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();

// Employee dashboard — redirect admins to admin dashboard
if (isAdmin()) { header('Location: ' . APP_URL . '/admin/dashboard.php'); exit; }

$userId = currentUserId();
$user   = currentUser();

$todayBills = db()->fetchOne("SELECT COUNT(*) AS c, COALESCE(SUM(grand_total),0) AS t FROM sales WHERE created_by=? AND DATE(sale_date)=CURDATE() AND status='active'", [$userId]);
$monthBills = db()->fetchOne("SELECT COUNT(*) AS c, COALESCE(SUM(grand_total),0) AS t FROM sales WHERE created_by=? AND MONTH(sale_date)=MONTH(NOW()) AND YEAR(sale_date)=YEAR(NOW()) AND status='active'", [$userId]);
$allBills   = db()->fetchOne("SELECT COUNT(*) AS c, COALESCE(SUM(grand_total),0) AS t FROM sales WHERE created_by=? AND status='active'", [$userId]);
$lowStock   = db()->fetchOne("SELECT COUNT(*) AS c FROM product_variants WHERE current_stock <= min_stock_alert AND current_stock > 0 AND status='active'")['c'];
$outStock   = db()->fetchOne("SELECT COUNT(*) AS c FROM product_variants WHERE current_stock=0 AND status='active'")['c'];

$recentBills = db()->fetchAll(
    "SELECT invoice_no, customer_name, grand_total, payment_status, sale_date FROM sales WHERE created_by=? ORDER BY id DESC LIMIT 8",
    [$userId]
);

$pageTitle  = 'My Dashboard';
$activePage = 'dashboard';
include dirname(__DIR__) . '/includes/header.php';
?>
<script>window.APP_URL='<?= APP_URL ?>';</script>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">My Dashboard</h1>
    <p class="page-subtitle">Welcome, <strong><?= e($user['name']) ?></strong> — <?= date('l, d F Y') ?></p>
  </div>
  <div class="page-header-right">
    <a href="<?= APP_URL ?>/employee/create_bill.php" class="btn btn-primary btn-lg">
      <i class="fas fa-plus"></i> New Bill
    </a>
    <?php if ($user['can_enter_stock']): ?>
    <a href="<?= APP_URL ?>/employee/stock_entry.php" class="btn btn-outline-primary">
      <i class="fas fa-boxes"></i> Enter Stock
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- KPI CARDS -->
<div class="stats-grid" style="grid-template-columns:repeat(<?= $user['can_enter_stock'] ? '5' : '4' ?>,1fr)">
  <div class="stat-card stat-card-primary">
    <div class="stat-icon"><i class="fas fa-receipt"></i></div>
    <div class="stat-body"><div class="stat-value"><?= $todayBills['c'] ?></div><div class="stat-label">Today's Bills</div><div class="stat-trend"><?= formatCurrency($todayBills['t']) ?></div></div>
  </div>
  <div class="stat-card stat-card-success">
    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
    <div class="stat-body"><div class="stat-value" style="font-size:1.1rem"><?= formatCurrency($monthBills['t']) ?></div><div class="stat-label">This Month</div><div class="stat-trend"><?= $monthBills['c'] ?> bill(s)</div></div>
  </div>
  <div class="stat-card stat-card-secondary">
    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
    <div class="stat-body"><div class="stat-value" style="font-size:1.1rem"><?= formatCurrency($allBills['t']) ?></div><div class="stat-label">All-time Sales</div><div class="stat-trend"><?= $allBills['c'] ?> bill(s)</div></div>
  </div>
  <?php if ($lowStock > 0 || $outStock > 0): ?>
  <div class="stat-card stat-card-warning">
    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= $lowStock ?></div>
      <div class="stat-label">Low Stock Items</div>
      <?php if ($outStock > 0): ?><div class="stat-trend down"><i class="fas fa-times-circle"></i> <?= $outStock ?> out of stock</div><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($user['can_apply_discount']): ?>
  <div class="stat-card stat-card-secondary">
    <div class="stat-icon"><i class="fas fa-percent"></i></div>
    <div class="stat-body"><div class="stat-value"><?= $user['max_discount_pct'] ?>%</div><div class="stat-label">Max Discount</div></div>
  </div>
  <?php endif; ?>
</div>

<!-- QUICK ACTIONS -->
<div class="grid-2">
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-bolt"></i> Quick Actions</span></div>
    <div class="card-body">
      <div class="d-flex" style="flex-wrap:wrap;gap:12px">
        <a href="<?= APP_URL ?>/employee/create_bill.php" class="btn btn-primary" style="flex:1;min-width:140px;padding:14px;flex-direction:column;gap:6px;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-file-invoice" style="font-size:1.4rem"></i><span>Create Bill</span>
        </a>
        <a href="<?= APP_URL ?>/employee/my_bills.php" class="btn btn-light" style="flex:1;min-width:140px;padding:14px;flex-direction:column;gap:6px;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-list" style="font-size:1.4rem"></i><span>My Bills</span>
        </a>
        <?php if ($user['can_enter_stock']): ?>
        <a href="<?= APP_URL ?>/employee/stock_entry.php" class="btn btn-light" style="flex:1;min-width:140px;padding:14px;flex-direction:column;gap:6px;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-boxes" style="font-size:1.4rem"></i><span>Enter Stock</span>
        </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/change_password.php" class="btn btn-light" style="flex:1;min-width:140px;padding:14px;flex-direction:column;gap:6px;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-key" style="font-size:1.4rem"></i><span>Change Password</span>
        </a>
      </div>
    </div>
  </div>

  <!-- Recent Bills -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-receipt"></i> My Recent Bills</span>
      <a href="<?= APP_URL ?>/employee/my_bills.php" class="btn btn-sm btn-light">View All</a>
    </div>
    <div class="card-body p-0">
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Invoice</th><th>Customer</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($recentBills)): ?>
            <tr><td colspan="4" class="text-center text-muted" style="padding:30px"><i class="fas fa-receipt" style="font-size:2rem;opacity:0.2;display:block;margin-bottom:10px"></i>No bills yet. <a href="<?= APP_URL ?>/employee/create_bill.php">Create your first bill</a></td></tr>
            <?php else: foreach ($recentBills as $b): ?>
            <tr>
              <td>
                <a href="<?= APP_URL ?>/admin/billing/invoice.php?id=<?= urlencode($b['invoice_no']) ?>" style="font-weight:600;font-size:0.83rem"><?= e($b['invoice_no']) ?></a>
                <div style="font-size:0.72rem;color:#94a3b8"><?= formatDate($b['sale_date']) ?></div>
              </td>
              <td style="font-size:0.85rem"><?= e($b['customer_name']) ?></td>
              <td class="text-right text-bold"><?= formatCurrency($b['grand_total']) ?></td>
              <td><?= paymentBadge($b['payment_status']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
