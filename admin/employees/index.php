<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

$page = max(1,(int)($_GET['page']??1));
$search = sanitize($_GET['search'] ?? '');
$where = ['u.role=\'employee\'']; $params=[];
if ($search) { $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.mobile LIKE ?)'; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }
$w = implode(' AND ', $where);

$total = db()->fetchOne("SELECT COUNT(*) AS c FROM users u WHERE $w", $params)['c'];
$pagination = paginate($total, $page);
$employees = db()->fetchAll(
    "SELECT u.*,
            COUNT(s.id) AS total_bills,
            COALESCE(SUM(s.grand_total),0) AS total_sales
     FROM users u LEFT JOIN sales s ON s.created_by=u.id AND s.status='active'
     WHERE $w GROUP BY u.id ORDER BY u.full_name
     LIMIT ".DEFAULT_PAGE_SIZE." OFFSET {$pagination['offset']}",
    $params
);

$pageTitle='Employees'; $activePage='employees';
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="page-header">
  <div class="page-header-left"><h1 class="page-title"><i class="fas fa-user-tie text-primary" style="margin-right:10px"></i>Employees</h1><p class="page-subtitle"><?= $total ?> employee(s)</p></div>
  <div class="page-header-right"><a href="<?= APP_URL ?>/admin/employees/add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Employee</a></div>
</div>
<div class="card">
  <form method="GET" class="table-filters">
    <input type="text" name="search" class="form-control" placeholder="Name, username, mobile..." value="<?= e($search) ?>">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
    <a href="<?= APP_URL ?>/admin/employees/index.php" class="btn btn-light btn-sm"><i class="fas fa-times"></i></a>
  </form>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Name</th><th>Username</th><th>Mobile</th><th class="text-center">Can Enter Stock</th><th class="text-center">Can Discount</th><th class="text-right">Max Discount%</th><th class="text-center">Bills</th><th class="text-right">Total Sales</th><th class="text-center">Status</th><th class="text-right no-print">Actions</th></tr></thead>
      <tbody>
        <?php if (empty($employees)): ?>
        <tr><td colspan="10"><div class="empty-state"><i class="fas fa-user-tie"></i><h4>No employees yet</h4><a href="<?= APP_URL ?>/admin/employees/add.php" class="btn btn-primary mt-3">Add Employee</a></div></td></tr>
        <?php else: foreach ($employees as $emp): ?>
        <tr>
          <td>
            <div class="d-flex align-center gap-2">
              <div class="user-avatar-sm" style="width:34px;height:34px;font-size:0.85rem"><?= strtoupper(substr($emp['full_name'],0,1)) ?></div>
              <div>
                <div style="font-weight:600"><?= e($emp['full_name']) ?></div>
                <?php if ($emp['email']): ?><div style="font-size:0.73rem;color:#64748b"><?= e($emp['email']) ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td><code><?= e($emp['username']) ?></code></td>
          <td><?= e($emp['mobile']??'—') ?></td>
          <td class="text-center"><?= $emp['can_enter_stock'] ? '<span class="badge badge-success"><i class="fas fa-check"></i></span>' : '<span class="badge badge-secondary"><i class="fas fa-times"></i></span>' ?></td>
          <td class="text-center"><?= $emp['can_apply_discount'] ? '<span class="badge badge-success"><i class="fas fa-check"></i></span>' : '<span class="badge badge-secondary"><i class="fas fa-times"></i></span>' ?></td>
          <td class="text-right"><?= $emp['can_apply_discount'] ? $emp['max_discount_pct'].'%' : '—' ?></td>
          <td class="text-center"><?= $emp['total_bills'] ?></td>
          <td class="text-right"><?= formatCurrency($emp['total_sales']) ?></td>
          <td class="text-center"><span class="badge badge-<?= $emp['status']==='active'?'success':'danger' ?>"><?= ucfirst($emp['status']) ?></span></td>
          <td class="text-right no-print">
            <div class="table-actions">
              <a href="<?= APP_URL ?>/admin/employees/view.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-light"><i class="fas fa-eye"></i></a>
              <a href="<?= APP_URL ?>/admin/employees/edit.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pagination['total_pages']>1): ?>
  <div class="card-footer"><?php echo renderPagination($pagination, APP_URL.'/admin/employees/index.php?search='.urlencode($search)); ?></div>
  <?php endif; ?>
</div>
<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
