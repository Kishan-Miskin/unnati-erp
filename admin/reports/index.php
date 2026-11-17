<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

// Reports: Sales, Profit, Stock, Employee, Customer
$report = sanitize($_GET['report'] ?? 'sales_summary');
$from   = sanitize($_GET['from'] ?? date('Y-m-01'));
$to     = sanitize($_GET['to'] ?? date('Y-m-d'));
$empId  = (int)($_GET['employee'] ?? 0);
$catId  = (int)($_GET['category'] ?? 0);
$groupBy = sanitize($_GET['group'] ?? 'day');

$pageTitle = 'Reports & Analytics'; $activePage = 'reports';
$employees  = db()->fetchAll("SELECT id, full_name FROM users ORDER BY full_name");
$categories = db()->fetchAll("SELECT id, name FROM categories WHERE parent_id IS NULL AND status='active' ORDER BY name");

// ── Report Data ───────────────────────────────────────────
$data = []; $summary = [];

if ($report === 'sales_summary') {
    $fmt = $groupBy === 'month' ? '%Y-%m' : '%Y-%m-%d';
    $label = $groupBy === 'month' ? '%b %Y' : '%d %b %Y';
    $where = ['s.status=\'active\'', 's.sale_date>=?', 's.sale_date<=?']; $params = [$from, $to];
    if ($empId) { $where[] = 's.created_by=?'; $params[] = $empId; }
    $w = implode(' AND ', $where);
    $data = db()->fetchAll(
        "SELECT DATE_FORMAT(s.sale_date,'$label') AS label, COUNT(s.id) AS bills, SUM(s.grand_total) AS revenue,
                SUM(s.discount_amount) AS total_discount, SUM(s.amount_received) AS collected,
                SUM(si.line_total) AS revenue2, SUM(si.qty) AS units_sold,
                SUM(si.purchase_cost_snapshot * si.qty) AS cogs
         FROM sales s JOIN sale_items si ON s.id=si.sale_id
         WHERE $w
         GROUP BY DATE_FORMAT(s.sale_date,'$fmt'), DATE_FORMAT(s.sale_date,'$label')
         ORDER BY MIN(s.sale_date)",
        $params
    );
    $summary = db()->fetchOne(
        "SELECT COUNT(s.id) AS bills, COALESCE(SUM(s.grand_total),0) AS total_revenue,
                COALESCE(SUM(s.discount_amount),0) AS total_discount,
                COALESCE(SUM(si.qty),0) AS total_qty,
                COALESCE(SUM(si.purchase_cost_snapshot*si.qty),0) AS total_cogs
         FROM sales s JOIN sale_items si ON s.id=si.sale_id WHERE $w",
        $params
    );

} elseif ($report === 'product_performance') {
    $where = ['s.status=\'active\'', 's.sale_date>=?', 's.sale_date<=?']; $params = [$from,$to];
    if ($catId) { $where[] = 'p.category_id=?'; $params[] = $catId; }
    $w = implode(' AND ', $where);
    $data = db()->fetchAll(
        "SELECT si.sku_snapshot AS sku, si.product_name_snapshot AS name, si.size_snapshot AS size,
                SUM(si.qty) AS qty_sold, SUM(si.line_total) AS revenue,
                SUM(si.purchase_cost_snapshot*si.qty) AS cogs,
                SUM(si.line_total)-SUM(si.purchase_cost_snapshot*si.qty) AS profit
         FROM sale_items si JOIN sales s ON si.sale_id=s.id
         JOIN product_variants pv ON si.variant_id=pv.id
         JOIN products p ON pv.product_id=p.id
         WHERE $w GROUP BY si.variant_id ORDER BY qty_sold DESC LIMIT 50",
        $params
    );

} elseif ($report === 'employee_performance') {
    $where = ['s.status=\'active\'', 's.sale_date>=?', 's.sale_date<=?']; $params = [$from,$to];
    $w = implode(' AND ', $where);
    $data = db()->fetchAll(
        "SELECT u.full_name AS employee, COUNT(s.id) AS bills, SUM(s.grand_total) AS revenue,
                SUM(s.discount_amount) AS discount, AVG(s.grand_total) AS avg_bill
         FROM sales s JOIN users u ON s.created_by=u.id
         WHERE $w GROUP BY u.id ORDER BY revenue DESC",
        $params
    );

} elseif ($report === 'customer_ledger') {
    $data = db()->fetchAll(
        "SELECT c.name, c.mobile, c.customer_type,
                COUNT(s.id) AS total_bills, COALESCE(SUM(s.grand_total),0) AS total_spent,
                COALESCE(SUM(s.balance_due),0) AS total_due
         FROM customers c LEFT JOIN sales s ON s.customer_name=c.name AND s.status='active'
         WHERE c.status='active' GROUP BY c.id ORDER BY total_spent DESC"
    );

} elseif ($report === 'stock_valuation') {
    $data = db()->fetchAll(
        "SELECT pv.sku, p.name AS product_name, pv.size, c.name AS category, pv.current_stock,
                pv.purchase_price_with_gst AS cost, pv.selling_price,
                pv.purchase_price_with_gst*pv.current_stock AS stock_cost_value,
                pv.selling_price*pv.current_stock AS stock_sell_value
         FROM product_variants pv JOIN products p ON pv.product_id=p.id
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE pv.status='active' AND p.status='active'
         ORDER BY stock_cost_value DESC"
    );
    $summary = db()->fetchOne(
        "SELECT COALESCE(SUM(pv.purchase_price_with_gst*pv.current_stock),0) AS total_cost,
                COALESCE(SUM(pv.selling_price*pv.current_stock),0) AS total_sell,
                COALESCE(SUM(pv.current_stock),0) AS total_units
         FROM product_variants pv WHERE pv.status='active'"
    );
}

include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<script>window.APP_URL='<?= APP_URL ?>';</script>
<div class="page-header">
  <div class="page-header-left"><h1 class="page-title"><i class="fas fa-chart-bar text-primary" style="margin-right:10px"></i>Reports & Analytics</h1></div>
  <div class="page-header-right">
    <button onclick="exportTableToCSV('reportTable','report_<?= $report ?>_<?= $from ?>_<?= $to ?>')" class="btn btn-light"><i class="fas fa-file-csv"></i> Export CSV</button>
    <button onclick="printInvoice()" class="btn btn-light"><i class="fas fa-print"></i> Print</button>
  </div>
</div>

<!-- Report Tabs -->
<div class="card" style="margin-bottom:12px">
  <div style="padding:12px 16px;display:flex;gap:6px;flex-wrap:wrap">
    <?php
    $tabs = [
      'sales_summary'       => ['icon'=>'fa-chart-line',    'label'=>'Sales Summary'],
      'product_performance' => ['icon'=>'fa-trophy',         'label'=>'Product Performance'],
      'employee_performance'=> ['icon'=>'fa-user-tie',       'label'=>'Employee Performance'],
      'customer_ledger'     => ['icon'=>'fa-users',          'label'=>'Customer Ledger'],
      'stock_valuation'     => ['icon'=>'fa-warehouse',      'label'=>'Stock Valuation'],
    ];
    foreach ($tabs as $key => $tab):
    ?>
    <a href="?report=<?= $key ?>&from=<?= $from ?>&to=<?= $to ?>" class="btn btn-sm <?= $report===$key?'btn-primary':'btn-light' ?>">
      <i class="fas <?= $tab['icon'] ?>"></i> <?= $tab['label'] ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Filters -->
<form method="GET" class="card" style="margin-bottom:16px">
  <div class="table-filters">
    <input type="hidden" name="report" value="<?= e($report) ?>">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <?php if (in_array($report,['sales_summary','employee_performance'])): ?>
    <select name="employee" class="form-select">
      <option value="">All Employees</option>
      <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $empId==$e['id']?'selected':'' ?>><?= e($e['full_name']) ?></option><?php endforeach; ?>
    </select>
    <?php endif; ?>
    <?php if ($report === 'sales_summary'): ?>
    <select name="group" class="form-select">
      <option value="day" <?= $groupBy==='day'?'selected':'' ?>>By Day</option>
      <option value="month" <?= $groupBy==='month'?'selected':'' ?>>By Month</option>
    </select>
    <?php endif; ?>
    <?php if ($report === 'product_performance'): ?>
    <select name="category" class="form-select">
      <option value="">All Categories</option>
      <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= $catId==$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option><?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
    <div style="margin-left:auto">
      <small class="text-muted">
        <?= date('d M Y', strtotime($from)) ?> — <?= date('d M Y', strtotime($to)) ?>
      </small>
    </div>
  </div>
</form>

<!-- Summary KPIs -->
<?php if (!empty($summary) && $report === 'sales_summary'): ?>
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:16px">
  <div class="stat-card stat-card-primary"><div class="stat-icon"><i class="fas fa-receipt"></i></div><div class="stat-body"><div class="stat-value"><?= $summary['bills'] ?></div><div class="stat-label">Total Bills</div></div></div>
  <div class="stat-card stat-card-success"><div class="stat-icon"><i class="fas fa-rupee-sign"></i></div><div class="stat-body"><div class="stat-value" style="font-size:1rem"><?= formatCurrency($summary['total_revenue']) ?></div><div class="stat-label">Revenue</div></div></div>
  <div class="stat-card stat-card-warning"><div class="stat-icon"><i class="fas fa-tag"></i></div><div class="stat-body"><div class="stat-value" style="font-size:1rem"><?= formatCurrency($summary['total_discount']) ?></div><div class="stat-label">Discounts</div></div></div>
  <div class="stat-card stat-card-secondary"><div class="stat-icon"><i class="fas fa-boxes"></i></div><div class="stat-body"><div class="stat-value"><?= number_format($summary['total_qty']) ?></div><div class="stat-label">Units Sold</div></div></div>
  <div class="stat-card stat-card-success"><div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div><div class="stat-body"><div class="stat-value" style="font-size:1rem"><?= formatCurrency($summary['total_revenue'] - $summary['total_cogs']) ?></div><div class="stat-label">Gross Profit</div></div></div>
</div>
<?php elseif (!empty($summary) && $report === 'stock_valuation'): ?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
  <div class="stat-card stat-card-primary"><div class="stat-icon"><i class="fas fa-boxes"></i></div><div class="stat-body"><div class="stat-value"><?= number_format($summary['total_units']) ?></div><div class="stat-label">Total Units in Stock</div></div></div>
  <div class="stat-card stat-card-secondary"><div class="stat-icon"><i class="fas fa-rupee-sign"></i></div><div class="stat-body"><div class="stat-value" style="font-size:1rem"><?= formatCurrency($summary['total_cost']) ?></div><div class="stat-label">Cost Value</div></div></div>
  <div class="stat-card stat-card-success"><div class="stat-icon"><i class="fas fa-tag"></i></div><div class="stat-body"><div class="stat-value" style="font-size:1rem"><?= formatCurrency($summary['total_sell']) ?></div><div class="stat-label">Sell Value</div></div></div>
</div>
<?php endif; ?>

<!-- Data Table -->
<div class="card">
  <div class="table-wrapper">
    <table id="reportTable">
      <?php if ($report === 'sales_summary'): ?>
      <thead><tr><th>Period</th><th class="text-center">Bills</th><th class="text-center">Units Sold</th><th class="text-right">Revenue</th><th class="text-right">Discount</th><th class="text-right">COGS</th><th class="text-right">Gross Profit</th></tr></thead>
      <tbody>
        <?php foreach ($data as $r): $profit = $r['revenue'] - $r['cogs']; ?>
        <tr>
          <td><?= e($r['label']) ?></td>
          <td class="text-center"><?= $r['bills'] ?></td>
          <td class="text-center"><?= number_format($r['units_sold']) ?></td>
          <td class="text-right"><?= formatCurrency($r['revenue']) ?></td>
          <td class="text-right text-danger"><?= formatCurrency($r['total_discount']) ?></td>
          <td class="text-right text-muted"><?= formatCurrency($r['cogs']) ?></td>
          <td class="text-right <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>"><strong><?= formatCurrency($profit) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>

      <?php elseif ($report === 'product_performance'): ?>
      <thead><tr><th>SKU</th><th>Product</th><th>Size</th><th class="text-center">Units Sold</th><th class="text-right">Revenue</th><th class="text-right">COGS</th><th class="text-right">Profit</th><th class="text-right">Margin %</th></tr></thead>
      <tbody>
        <?php foreach ($data as $r): $margin = $r['revenue'] ? round($r['profit']/$r['revenue']*100,1) : 0; ?>
        <tr>
          <td><span class="sku-badge"><?= e($r['sku']) ?></span></td>
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['size']) ?></td>
          <td class="text-center"><?= number_format($r['qty_sold']) ?></td>
          <td class="text-right"><?= formatCurrency($r['revenue']) ?></td>
          <td class="text-right text-muted"><?= formatCurrency($r['cogs']) ?></td>
          <td class="text-right <?= $r['profit'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($r['profit']) ?></td>
          <td class="text-right"><span class="badge badge-<?= $margin >= 20 ? 'success' : ($margin >= 10 ? 'warning' : 'danger') ?>"><?= $margin ?>%</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>

      <?php elseif ($report === 'employee_performance'): ?>
      <thead><tr><th>Employee</th><th class="text-center">Bills</th><th class="text-right">Revenue</th><th class="text-right">Discounts</th><th class="text-right">Avg. Bill</th></tr></thead>
      <tbody>
        <?php foreach ($data as $r): ?>
        <tr>
          <td><strong><?= e($r['employee']) ?></strong></td>
          <td class="text-center"><?= $r['bills'] ?></td>
          <td class="text-right text-bold"><?= formatCurrency($r['revenue']) ?></td>
          <td class="text-right text-danger"><?= formatCurrency($r['discount']) ?></td>
          <td class="text-right"><?= formatCurrency($r['avg_bill']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>

      <?php elseif ($report === 'customer_ledger'): ?>
      <thead><tr><th>Customer</th><th>Mobile</th><th>Type</th><th class="text-center">Bills</th><th class="text-right">Total Spent</th><th class="text-right">Outstanding Due</th></tr></thead>
      <tbody>
        <?php foreach ($data as $r): ?>
        <tr>
          <td><strong><?= e($r['name']) ?></strong></td>
          <td><?= e($r['mobile'] ?? '—') ?></td>
          <td><span class="badge badge-secondary"><?= ucfirst(e($r['customer_type'])) ?></span></td>
          <td class="text-center"><?= $r['total_bills'] ?></td>
          <td class="text-right"><?= formatCurrency($r['total_spent']) ?></td>
          <td class="text-right <?= $r['total_due'] > 0 ? 'text-danger text-bold' : '' ?>"><?= $r['total_due'] > 0 ? formatCurrency($r['total_due']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>

      <?php elseif ($report === 'stock_valuation'): ?>
      <thead><tr><th>SKU</th><th>Product</th><th>Size</th><th>Category</th><th class="text-center">Stock</th><th class="text-right">Cost/Unit</th><th class="text-right">Sell Price</th><th class="text-right">Stock Cost Value</th><th class="text-right">Stock Sell Value</th></tr></thead>
      <tbody>
        <?php foreach ($data as $r): ?>
        <tr>
          <td><span class="sku-badge"><?= e($r['sku']) ?></span></td>
          <td><?= e($r['product_name']) ?></td>
          <td><?= e($r['size']) ?></td>
          <td><?= e($r['category'] ?? '—') ?></td>
          <td class="text-center"><?= number_format($r['current_stock']) ?></td>
          <td class="text-right"><?= formatCurrency($r['cost']) ?></td>
          <td class="text-right"><?= formatCurrency($r['selling_price']) ?></td>
          <td class="text-right"><?= formatCurrency($r['stock_cost_value']) ?></td>
          <td class="text-right text-success"><?= formatCurrency($r['stock_sell_value']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php endif; ?>
    </table>
  </div>
  <?php if (empty($data)): ?>
  <div class="empty-state" style="padding:40px"><i class="fas fa-chart-bar"></i><h4>No data for this period</h4><p>Try changing the date range or filters.</p></div>
  <?php endif; ?>
</div>
<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
