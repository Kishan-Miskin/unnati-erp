<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

$search = sanitize($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = '(s.name LIKE ? OR s.contact_person LIKE ? OR s.mobile LIKE ? OR s.gstin LIKE ?)';
    $str = "%$search%";
    $params = array_merge($params, [$str, $str, $str, $str]);
}

$w = implode(' AND ', $where);

$total = db()->fetchOne("SELECT COUNT(*) AS c FROM suppliers s WHERE $w", $params)['c'];
$pagination = paginate($total, $page);

$suppliers = db()->fetchAll(
    "SELECT s.*, 
            COUNT(p.id) AS total_orders,
            COALESCE(SUM(p.total_amount), 0) AS total_purchased
     FROM suppliers s
     LEFT JOIN purchases p ON s.id = p.supplier_id
     WHERE $w
     GROUP BY s.id
     ORDER BY total_purchased DESC, s.name ASC
     LIMIT " . DEFAULT_PAGE_SIZE . " OFFSET {$pagination['offset']}",
    $params
);

$pageTitle = 'Suppliers'; $activePage = 'suppliers';
$breadcrumb = [['label' => 'Suppliers']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title"><i class="fas fa-truck text-primary" style="margin-right:10px"></i>Suppliers &amp; Mills Directory</h1>
    <p class="page-subtitle"><?= number_format($total) ?> supplier(s) on record</p>
  </div>
  <div class="page-header-right">
    <a href="<?= APP_URL ?>/admin/purchases/add.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Stock Inward Entry</a>
    <a href="<?= APP_URL ?>/admin/purchases/index.php" class="btn btn-light"><i class="fas fa-file-invoice"></i> Inward Bills</a>
  </div>
</div>

<div class="card">
  <!-- Filters -->
  <form method="GET" class="table-filters">
    <input type="text" name="search" class="form-control" placeholder="Search supplier name, contact, mobile, GSTIN..." value="<?= e($search) ?>" style="max-width:380px">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
    <a href="<?= APP_URL ?>/admin/suppliers/index.php" class="btn btn-light btn-sm"><i class="fas fa-times"></i> Clear</a>
  </form>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Supplier / Mill Name</th>
          <th>Contact Person</th>
          <th>Phone / Mobile</th>
          <th>GSTIN</th>
          <th class="text-center">Total Inward Orders</th>
          <th class="text-right">Total Purchased Value</th>
          <th class="text-right no-print">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($suppliers)): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="fas fa-truck"></i><h4>No suppliers found</h4></div></td></tr>
        <?php else: foreach ($suppliers as $s): ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:0.92rem"><?= e($s['name']) ?></div>
            <?php if (!empty($s['address'])): ?>
              <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?= e($s['address']) ?></small>
            <?php endif; ?>
          </td>
          <td><?= e($s['contact_person'] ?: '—') ?></td>
          <td><?= e($s['mobile'] ?: '—') ?></td>
          <td><span class="sku-badge"><?= e($s['gstin'] ?: '—') ?></span></td>
          <td class="text-center">
            <span class="badge badge-secondary"><?= $s['total_orders'] ?></span>
          </td>
          <td class="text-right">
            <strong><?= formatCurrency($s['total_purchased']) ?></strong>
          </td>
          <td class="text-right no-print">
            <a href="<?= APP_URL ?>/admin/purchases/index.php?search=<?= urlencode($s['name']) ?>" class="btn btn-sm btn-light" title="View Inward Invoices">
              <i class="fas fa-file-invoice"></i> Invoices
            </a>
            <a href="<?= APP_URL ?>/admin/purchases/add.php" class="btn btn-sm btn-outline-primary" title="Inward Stock from this Supplier">
              <i class="fas fa-plus"></i> Inward
            </a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pagination['total_pages'] > 1): ?>
  <div class="card-footer">
    <?php 
    $baseUrl = APP_URL . '/admin/suppliers/index.php?search=' . urlencode($search);
    echo renderPagination($pagination, $baseUrl); 
    ?>
  </div>
  <?php endif; ?>
</div>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>

