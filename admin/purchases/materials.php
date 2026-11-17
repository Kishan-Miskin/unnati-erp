<?php
/**
 * Admin: Raw Material Stock & Inventory (Supplier Side)
 * Manages raw fabrics (by meter), rolls, trims, uniform materials and mill supplies
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

// ── Filters ───────────────────────────────────────────────
$search  = sanitize($_GET['search'] ?? '');
$catId   = (int)($_GET['category'] ?? 0);
$filter  = sanitize($_GET['filter'] ?? 'all'); // all, low_stock, out_of_stock
$page    = max(1, (int)($_GET['page'] ?? 1));

$where = ["pv.status = 'active'", "p.status = 'active'", "p.item_type = 'material'"]; 
$params = [];

if ($search) { 
    $where[] = '(pv.sku LIKE ? OR p.name LIKE ? OR p.brand LIKE ?)'; 
    $s = "%$search%"; 
    $params = array_merge($params, [$s, $s, $s]); 
}
if ($catId)  { 
    $where[] = 'p.category_id = ?'; 
    $params[] = $catId; 
}
if ($filter === 'low_stock')  { 
    $where[] = 'pv.current_stock > 0 AND pv.current_stock <= pv.min_stock_alert'; 
}
if ($filter === 'out_of_stock') { 
    $where[] = 'pv.current_stock = 0'; 
}
$w = implode(' AND ', $where);

$total = db()->fetchOne("SELECT COUNT(*) AS c FROM product_variants pv JOIN products p ON pv.product_id=p.id WHERE $w", $params)['c'];
$pagination = paginate($total, $page);

$materials = db()->fetchAll(
    "SELECT pv.id, pv.sku, pv.size, pv.current_stock, pv.min_stock_alert,
            pv.purchase_price_with_gst, pv.selling_price, pv.purchase_price, pv.purchase_gst_pct,
            p.id AS product_id, p.name AS product_name, p.brand AS material_type,
            COALESCE(p.garment_category, 'General') AS garment_category,
            c.name AS category_name,
            COALESCE((SELECT SUM(pi.qty) FROM purchase_items pi JOIN purchases pr ON pi.purchase_id=pr.id WHERE pi.variant_id=pv.id),0) AS total_inwarded,
            COALESCE((SELECT SUM(si.qty) FROM sale_items si JOIN sales s ON si.sale_id=s.id WHERE si.variant_id=pv.id AND s.status != 'cancelled'),0) AS total_consumed
     FROM product_variants pv
     JOIN products p ON pv.product_id=p.id
     LEFT JOIN categories c ON p.category_id=c.id
     WHERE $w 
     ORDER BY FIELD(COALESCE(p.garment_category,'General'), 'Pant / Trouser', 'Shirt', 'General'), pv.current_stock ASC, p.name ASC",
    $params
);

// Group materials by broader garment category (Pant / Trouser, Shirt, etc.)
$groupedCategories = [];
foreach ($materials as $m) {
    $grp = trim($m['garment_category'] ?: 'General');
    if ($grp === '' || $grp === 'General') $grp = 'General Fabrics & Trims';

    if (!isset($groupedCategories[$grp])) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $grp));
        $icon = 'fas fa-layer-group';
        $iconBg = '#eff6ff';
        $iconColor = '#2563eb';
        $desc = 'General raw materials & mill supplies';

        if (stripos($grp, 'Pant') !== false) {
            $icon = 'fas fa-scroll';
            $iconBg = '#eff6ff';
            $iconColor = '#1e40af';
            $desc = '5 Components: Fabric Roll, Zippers, Waist Hooks, Pocketing Aster, Buttons';
        } elseif (stripos($grp, 'Shirt') !== false) {
            $icon = 'fas fa-tshirt';
            $iconBg = '#f0fdf4';
            $iconColor = '#059669';
            $desc = '4 Components: Fabric Cloth, Collar Fusing, Cuff Canvas, Buttons';
        }

        $groupedCategories[$grp] = [
            'name'             => $grp,
            'slug'             => $slug,
            'icon'             => $icon,
            'icon_bg'          => $iconBg,
            'icon_color'       => $iconColor,
            'description'      => $desc,
            'items'            => [],
            'total_sku'        => 0,
            'total_stock'      => 0,
            'total_inwarded'   => 0,
            'total_consumed'   => 0,
            'total_valuation'  => 0,
            'low_stock_count'  => 0,
            'out_stock_count'  => 0,
        ];
    }

    $groupedCategories[$grp]['items'][] = $m;
    $groupedCategories[$grp]['total_sku']++;
    $groupedCategories[$grp]['total_stock']     += (int)$m['current_stock'];
    $groupedCategories[$grp]['total_inwarded']  += (int)$m['total_inwarded'];
    $groupedCategories[$grp]['total_consumed']  += (int)$m['total_consumed'];
    $groupedCategories[$grp]['total_valuation'] += ((int)$m['current_stock'] * (float)$m['purchase_price_with_gst']);
    if ($m['current_stock'] == 0) {
        $groupedCategories[$grp]['out_stock_count']++;
    } elseif ($m['current_stock'] <= $m['min_stock_alert']) {
        $groupedCategories[$grp]['low_stock_count']++;
    }
}

$categories   = db()->fetchAll("SELECT id, name FROM categories WHERE parent_id IS NULL AND status='active' ORDER BY name");
$lowCount     = db()->fetchOne("SELECT COUNT(*) AS c FROM product_variants pv JOIN products p ON pv.product_id=p.id WHERE pv.status='active' AND p.status='active' AND p.item_type='material' AND pv.current_stock > 0 AND pv.current_stock <= pv.min_stock_alert")['c'];
$outCount     = db()->fetchOne("SELECT COUNT(*) AS c FROM product_variants pv JOIN products p ON pv.product_id=p.id WHERE pv.status='active' AND p.status='active' AND p.item_type='material' AND pv.current_stock=0")['c'];
$totalValue   = db()->fetchOne("SELECT COALESCE(SUM(pv.purchase_price_with_gst * pv.current_stock),0) AS v FROM product_variants pv JOIN products p ON pv.product_id=p.id WHERE pv.status='active' AND p.status='active' AND p.item_type='material'")['v'];
$totalMeters  = db()->fetchOne("SELECT COALESCE(SUM(pv.current_stock),0) AS v FROM product_variants pv JOIN products p ON pv.product_id=p.id WHERE pv.status='active' AND p.status='active' AND p.item_type='material'")['v'];

$pageTitle  = 'Material Stock & Inventory';
$activePage = 'purchases';
$breadcrumb = [['label' => 'Supplier'], ['label' => 'Material Stock & Inventory']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<script>window.APP_URL='<?= APP_URL ?>';</script>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title"><i class="fas fa-scroll text-primary" style="margin-right:10px"></i>Material Stock &amp; Inventory</h1>
    <p class="page-subtitle">Raw fabrics (meters), rolls, trims &amp; uniform materials supplied by mills</p>
  </div>
  <div class="page-header-right">
    <a href="<?= APP_URL ?>/admin/purchases/material_movements.php" class="btn btn-light"><i class="fas fa-exchange-alt"></i> Material Movements</a>
    <a href="<?= APP_URL ?>/admin/suppliers/index.php" class="btn btn-light"><i class="fas fa-truck"></i> Suppliers</a>
    <a href="<?= APP_URL ?>/admin/purchases/index.php" class="btn btn-light"><i class="fas fa-file-invoice"></i> Supplier Bills</a>
    <a href="<?= APP_URL ?>/admin/purchases/add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Inward Stock</a>
  </div>
</div>

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <div class="stat-card stat-card-primary">
    <div class="stat-icon"><i class="fas fa-boxes"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($total) ?></div>
      <div class="stat-label">Material SKUs</div>
    </div>
  </div>
  <div class="stat-card stat-card-info">
    <div class="stat-icon"><i class="fas fa-ruler-combined"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($totalMeters) ?></div>
      <div class="stat-label">Total Units / Meters</div>
    </div>
  </div>
  <div class="stat-card stat-card-warning">
    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= $lowCount ?></div>
      <div class="stat-label">Low Stock Materials</div>
    </div>
  </div>
  <div class="stat-card stat-card-success">
    <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
    <div class="stat-body">
      <div class="stat-value"><?= formatCurrency($totalValue) ?></div>
      <div class="stat-label">Material Valuation</div>
    </div>
  </div>
</div>

<!-- Tab Pills -->
<div class="tab-wrapper" style="margin-bottom:16px">
  <div class="tab-nav">
    <a href="?filter=all<?= $search ? '&search='.urlencode($search) : '' ?><?= $catId ? '&category='.$catId : '' ?>"
       class="tab-btn <?= $filter==='all' ? 'active' : '' ?>">All Materials</a>
    <a href="?filter=low_stock<?= $search ? '&search='.urlencode($search) : '' ?><?= $catId ? '&category='.$catId : '' ?>"
       class="tab-btn <?= $filter==='low_stock' ? 'active' : '' ?>">
       <i class="fas fa-exclamation-triangle text-warning"></i> Low Stock (<?= $lowCount ?>)
    </a>
    <a href="?filter=out_of_stock<?= $search ? '&search='.urlencode($search) : '' ?><?= $catId ? '&category='.$catId : '' ?>"
       class="tab-btn <?= $filter==='out_of_stock' ? 'active' : '' ?>">
       <i class="fas fa-times-circle text-danger"></i> Out of Stock (<?= $outCount ?>)
    </a>
  </div>
</div>

<div class="card">
  <!-- Filters -->
  <form method="GET" class="table-filters">
    <input type="hidden" name="filter" value="<?= e($filter) ?>">
    <input type="text" name="search" class="form-control" placeholder="Search material name, fabric type, SKU..." value="<?= e($search) ?>" style="min-width:240px">
    <select name="category" class="form-select">
      <option value="">All Categories</option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?= $cat['id'] ?>" <?= $catId==$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
    <a href="<?= APP_URL ?>/admin/purchases/materials.php" class="btn btn-light btn-sm"><i class="fas fa-times"></i> Clear</a>
    <div style="margin-left:auto">
      <button type="button" class="btn btn-light btn-sm" onclick="exportTableToCSV('materialsTable','material_stock')">
        <i class="fas fa-download"></i> Export CSV
      </button>
    </div>
  </form>

  <!-- View Mode Switcher -->
  <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-bottom:1px solid #e2e8f0;background:#fafbfc;flex-wrap:wrap;gap:10px">
    <div class="d-flex align-center gap-2">
      <button type="button" id="btnViewCategories" class="btn btn-sm btn-primary" onclick="setMaterialView('categories')">
        <i class="fas fa-layer-group"></i> Grouped by Garment Category (<?= count($groupedCategories) ?>)
      </button>
      <button type="button" id="btnViewAll" class="btn btn-sm btn-light" onclick="setMaterialView('all')">
        <i class="fas fa-list"></i> All Individual Materials (<?= count($materials) ?>)
      </button>
    </div>
    <div style="font-size:0.8rem;color:#64748b">
      <i class="fas fa-info-circle"></i> Click any category row to open its components &amp; live stock modal
    </div>
  </div>

  <!-- 1. GROUPED CATEGORY VIEW (DEFAULT) -->
  <div id="viewCategoriesContainer">
    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th style="min-width:240px">Garment Category &amp; Kit</th>
            <th style="min-width:280px">Components Included</th>
            <th class="text-center" style="width:150px">Available Stock</th>
            <th class="text-center" style="width:120px">Sold &amp; Consumed</th>
            <th class="text-center" style="width:120px">Total Inwarded</th>
            <th class="text-right" style="width:130px">Category Valuation</th>
            <th class="text-center" style="width:180px">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($groupedCategories)): ?>
          <tr>
            <td colspan="7" class="text-center text-muted" style="padding:40px">
              <i class="fas fa-scroll" style="font-size:2.5rem;opacity:0.2;display:block;margin-bottom:10px"></i>
              No material categories found matching filters.
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($groupedCategories as $grp): 
            $hasOut = $grp['out_stock_count'] > 0;
            $hasLow = $grp['low_stock_count'] > 0;
            $allOut = $grp['out_stock_count'] == $grp['total_sku'];
          ?>
          <tr style="cursor:pointer" onclick="openCategoryModal('<?= $grp['slug'] ?>')">
            <td>
              <div style="display:flex;align-items:center;gap:12px">
                <div style="width:44px;height:44px;border-radius:10px;background:<?= $grp['icon_bg'] ?>;color:<?= $grp['icon_color'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">
                  <i class="<?= $grp['icon'] ?>"></i>
                </div>
                <div>
                  <strong style="font-size:0.98rem;color:#0f172a;display:block"><?= e($grp['name']) ?> Materials</strong>
                  <span style="font-size:0.75rem;color:#64748b"><?= e($grp['description']) ?></span>
                </div>
              </div>
            </td>
            <td>
              <div style="display:flex;flex-wrap:wrap;gap:4px">
                <?php foreach ($grp['items'] as $it): ?>
                  <span class="badge badge-light" style="font-size:0.74rem;border:1px solid #cbd5e1;background:#f8fafc;padding:3px 8px">
                    <?= e($it['product_name']) ?>
                  </span>
                <?php endforeach; ?>
              </div>
              <div style="font-size:0.72rem;color:#7c3aed;margin-top:4px">
                <i class="fas fa-layer-group"></i> <?= $grp['total_sku'] ?> component(s) in this kit
              </div>
            </td>
            <td class="text-center" onclick="event.stopPropagation()">
              <?php if ($allOut): ?>
                <span class="badge badge-danger" style="font-weight:700;background:#fef2f2;color:#dc2626;border:1px solid #fecaca">[ 0 available ] &bull; Out</span>
              <?php elseif ($hasLow): ?>
                <span class="badge badge-warning" style="font-weight:700;background:#fffbeb;color:#b45309;border:1px solid #fde68a">[ <?= number_format($grp['total_stock']) ?> units ] &bull; Low</span>
              <?php else: ?>
                <span class="badge badge-success" style="font-weight:700;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0">[ <?= number_format($grp['total_stock']) ?> units available ]</span>
              <?php endif; ?>
            </td>
            <td class="text-center" onclick="event.stopPropagation()">
              <span style="font-weight:600;color:#64748b"><?= number_format($grp['total_consumed']) ?> Units</span>
            </td>
            <td class="text-center" onclick="event.stopPropagation()">
              <span style="font-weight:600;color:#2563eb"><?= number_format($grp['total_inwarded']) ?> Units</span>
            </td>
            <td class="text-right" style="font-weight:700" onclick="event.stopPropagation()">
              <?= formatCurrency($grp['total_valuation']) ?>
            </td>
            <td class="text-center" onclick="event.stopPropagation()">
              <div class="d-flex gap-1 justify-center">
                <button type="button" class="btn btn-sm btn-primary" onclick="openCategoryModal('<?= $grp['slug'] ?>')" style="font-weight:600;font-size:0.8rem">
                  <i class="fas fa-boxes"></i> View Materials
                </button>
                <a href="<?= APP_URL ?>/admin/purchases/add.php" class="btn btn-sm btn-outline-success" title="Inward stock for this category" style="font-weight:600;font-size:0.8rem">
                  <i class="fas fa-plus"></i> Inward
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 2. DETAILED ALL INDIVIDUAL MATERIALS VIEW (CAN BE TOGGLED) -->
  <div id="viewAllContainer" style="display:none">
    <div class="table-wrapper">
      <table class="data-table" id="materialsTable">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Material Name &amp; Spec</th>
            <th>Category</th>
            <th>Unit</th>
            <th class="text-center">Available Stock</th>
            <th class="text-center">Sold &amp; Consumed</th>
            <th class="text-center">Total Inwarded</th>
            <th class="text-right">Cost (incl GST)</th>
            <th class="text-right">Stock Valuation</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($materials)): ?>
          <tr>
            <td colspan="10" class="text-center text-muted" style="padding:40px">
              <i class="fas fa-scroll" style="font-size:2.5rem;opacity:0.2;display:block;margin-bottom:10px"></i>
              No materials found. Inward stock from suppliers using the button above.
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($materials as $m):
            $val = $m['current_stock'] * $m['purchase_price_with_gst'];
            $isLow = $m['current_stock'] > 0 && $m['current_stock'] <= $m['min_stock_alert'];
            $isOut = $m['current_stock'] == 0;
            $unitBadge = $m['size'] ?: 'Meter';
          ?>
          <tr>
            <td><span class="sku-badge"><?= e($m['sku']) ?></span></td>
            <td>
              <strong><?= e($m['product_name']) ?></strong>
              <?php if ($m['material_type']): ?>
              <div style="font-size:0.75rem;color:#7c3aed"><i class="fas fa-tag"></i> <?= e($m['material_type']) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-secondary"><?= e($m['category_name'] ?: 'Fabrics') ?></span></td>
            <td><span class="badge badge-info"><?= e($unitBadge) ?></span></td>
            <td class="text-center">
              <?php if ($isOut): ?>
                <span class="badge badge-danger" style="font-weight:700;background:#fef2f2;color:#dc2626;border:1px solid #fecaca">[ 0 available ]</span>
              <?php elseif ($isLow): ?>
                <span class="badge badge-warning" style="font-weight:700;background:#fffbeb;color:#b45309;border:1px solid #fde68a">[ <?= $m['current_stock'] ?> available ] &bull; Low</span>
              <?php else: ?>
                <span class="badge badge-success" style="font-weight:700;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0">[ <?= $m['current_stock'] ?> available ]</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <span style="font-weight:600;color:#64748b"><?= number_format($m['total_consumed']) ?> <?= e($unitBadge) ?></span>
            </td>
            <td class="text-center">
              <span style="font-weight:600;color:#2563eb"><?= number_format($m['total_inwarded']) ?> <?= e($unitBadge) ?></span>
            </td>
            <td class="text-right"><?= formatCurrency($m['purchase_price_with_gst']) ?> <small class="text-muted">(<?= $m['purchase_gst_pct'] ?>%)</small></td>
            <td class="text-right" style="font-weight:700"><?= formatCurrency($val) ?></td>
            <td class="text-center">
              <div class="table-actions">
                <a href="<?= APP_URL ?>/admin/purchases/add.php?sku=<?= urlencode($m['sku']) ?>" class="btn-icon btn-icon-primary" title="Inward More Stock">
                  <i class="fas fa-plus"></i>
                </a>
                <a href="<?= APP_URL ?>/admin/purchases/material_movements.php?variant_id=<?= $m['id'] ?>" class="btn-icon btn-icon-light" title="Material Movement Ledger">
                  <i class="fas fa-history"></i>
                </a>
                <a href="<?= APP_URL ?>/admin/inventory/adjustments.php" class="btn-icon btn-icon-warning" title="Adjust Stock">
                  <i class="fas fa-sliders-h"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pagination['pages'] > 1): ?>
    <div class="card-footer d-flex justify-between align-center">
      <span class="text-muted text-sm">Showing <?= count($materials) ?> of <?= number_format($total) ?> material(s)</span>
      <div class="pagination">
        <?php for ($p=1; $p<=$pagination['pages']; $p++): ?>
        <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&category=<?= $catId ?>&filter=<?= e($filter) ?>"
           class="page-link <?= $p==$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ===================================================== -->
<!-- MODALS FOR EACH GARMENT CATEGORY (PANT, SHIRT, ETC.)  -->
<!-- ===================================================== -->
<?php foreach ($groupedCategories as $grp): ?>
<div class="modal-overlay" id="categoryModal-<?= $grp['slug'] ?>">
  <div class="modal" style="max-width:960px">
    <div class="modal-header">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:36px;height:36px;border-radius:8px;background:<?= $grp['icon_bg'] ?>;color:<?= $grp['icon_color'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.1rem">
          <i class="<?= $grp['icon'] ?>"></i>
        </div>
        <div>
          <span class="modal-title" style="font-size:1.15rem"><?= e($grp['name']) ?> &bull; Materials &amp; Live Stock</span>
          <div style="font-size:0.75rem;color:#64748b"><?= e($grp['description']) ?></div>
        </div>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('categoryModal-<?= $grp['slug'] ?>')">&times;</button>
    </div>

    <div class="modal-body" style="padding:16px 20px">
      <!-- Summary Bar inside Modal -->
      <div style="display:flex;justify-content:space-between;align-items:center;background:#f8fafc;padding:12px 18px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div style="display:flex;align-items:center;gap:8px">
          <span class="badge badge-primary" style="font-size:0.78rem"><?= $grp['total_sku'] ?> Raw Materials</span>
          <span style="font-size:0.82rem;color:#475569">Components configured for this category</span>
        </div>
        <div class="d-flex gap-3 text-sm" style="flex-wrap:wrap">
          <span>Available Stock: <strong style="color:<?= $grp['total_stock'] > 0 ? '#059669' : '#dc2626' ?>"><?= number_format($grp['total_stock']) ?> Units</strong></span>
          <span>Total Inwarded: <strong class="text-primary"><?= number_format($grp['total_inwarded']) ?></strong></span>
          <span>Consumed: <strong class="text-muted"><?= number_format($grp['total_consumed']) ?></strong></span>
          <span>Valuation: <strong class="text-success"><?= formatCurrency($grp['total_valuation']) ?></strong></span>
        </div>
      </div>

      <!-- Materials Table inside Modal -->
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th style="min-width:140px">SKU</th>
              <th style="min-width:200px">Material Name &amp; Spec</th>
              <th style="width:90px">Unit</th>
              <th class="text-center" style="width:130px">Available Stock</th>
              <th class="text-center" style="width:110px">Sold &amp; Consumed</th>
              <th class="text-center" style="width:110px">Total Inwarded</th>
              <th class="text-right" style="width:120px">Cost (incl GST)</th>
              <th class="text-right" style="width:110px">Valuation</th>
              <th class="text-center" style="width:150px">Actions Side to It</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($grp['items'] as $it): 
              $itemVal = $it['current_stock'] * $it['purchase_price_with_gst'];
              $isLow = $it['current_stock'] > 0 && $it['current_stock'] <= $it['min_stock_alert'];
              $isOut = $it['current_stock'] == 0;
              $unitLabel = $it['size'] ?: 'Meter';
            ?>
            <tr>
              <td><span class="sku-badge" style="font-size:0.72rem"><?= e($it['sku']) ?></span></td>
              <td>
                <strong style="color:#0f172a;font-size:0.92rem"><?= e($it['product_name']) ?></strong>
                <?php if ($it['material_type']): ?>
                  <div style="font-size:0.72rem;color:#7c3aed;margin-top:2px"><i class="fas fa-tag"></i> <?= e($it['material_type']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-info" style="font-size:0.72rem"><?= e($unitLabel) ?></span></td>
              <td class="text-center">
                <?php if ($isOut): ?>
                  <span class="badge badge-danger" style="font-weight:700;background:#fef2f2;color:#dc2626;border:1px solid #fecaca">[ 0 available ]</span>
                <?php elseif ($isLow): ?>
                  <span class="badge badge-warning" style="font-weight:700;background:#fffbeb;color:#b45309;border:1px solid #fde68a">[ <?= $it['current_stock'] ?> available ] &bull; Low</span>
                <?php else: ?>
                  <span class="badge badge-success" style="font-weight:700;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0">[ <?= $it['current_stock'] ?> available ]</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <span style="font-weight:600;color:#64748b"><?= number_format($it['total_consumed']) ?> <?= e($unitLabel) ?></span>
              </td>
              <td class="text-center">
                <span style="font-weight:600;color:#2563eb"><?= number_format($it['total_inwarded']) ?> <?= e($unitLabel) ?></span>
              </td>
              <td class="text-right"><?= formatCurrency($it['purchase_price_with_gst']) ?></td>
              <td class="text-right font-weight-bold"><?= formatCurrency($itemVal) ?></td>
              <td class="text-center">
                <div class="d-flex gap-1 justify-center">
                  <a href="<?= APP_URL ?>/admin/purchases/add.php?sku=<?= urlencode($it['sku']) ?>" class="btn btn-xs btn-primary" title="Inward Stock for this material" style="font-weight:600;padding:3px 8px">
                    <i class="fas fa-plus"></i> Inward
                  </a>
                  <a href="<?= APP_URL ?>/admin/purchases/material_movements.php?variant_id=<?= $it['id'] ?>" class="btn btn-xs btn-light" title="Stock Movement Ledger" style="padding:3px 7px">
                    <i class="fas fa-history"></i>
                  </a>
                  <a href="<?= APP_URL ?>/admin/inventory/adjustments.php" class="btn btn-xs btn-light" title="Adjust Stock" style="padding:3px 7px">
                    <i class="fas fa-sliders-h"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal-footer" style="display:flex;justify-content:space-between;align-items:center;background:#f8fafc">
      <div style="font-size:0.8rem;color:#64748b">
        <i class="fas fa-info-circle"></i> Use the actions side to each component to inward stock or view ledger.
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-light" onclick="closeModal('categoryModal-<?= $grp['slug'] ?>')">Close</button>
        <?php if (stripos($grp['name'], 'Pant') !== false): ?>
          <a href="<?= APP_URL ?>/admin/purchases/add.php?open_kit=pant" class="btn btn-success" style="font-weight:600">
            <i class="fas fa-truck-loading"></i> Inward Complete Pant Kit in Stock Entry &rarr;
          </a>
        <?php elseif (stripos($grp['name'], 'Shirt') !== false): ?>
          <a href="<?= APP_URL ?>/admin/purchases/add.php?open_kit=shirt" class="btn btn-success" style="font-weight:600">
            <i class="fas fa-truck-loading"></i> Inward Complete Shirt Kit in Stock Entry &rarr;
          </a>
        <?php else: ?>
          <a href="<?= APP_URL ?>/admin/purchases/add.php" class="btn btn-success" style="font-weight:600">
            <i class="fas fa-truck-loading"></i> Inward Raw Materials in Stock Entry &rarr;
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
function openCategoryModal(slug) {
  openModal('categoryModal-' + slug);
}

function setMaterialView(mode) {
  const catView = document.getElementById('viewCategoriesContainer');
  const allView = document.getElementById('viewAllContainer');
  const btnCat  = document.getElementById('btnViewCategories');
  const btnAll  = document.getElementById('btnViewAll');

  if (mode === 'all') {
    if (catView) catView.style.display = 'none';
    if (allView) allView.style.display = 'block';
    if (btnCat) { btnCat.classList.remove('btn-primary'); btnCat.classList.add('btn-light'); }
    if (btnAll) { btnAll.classList.remove('btn-light'); btnAll.classList.add('btn-primary'); }
  } else {
    if (catView) catView.style.display = 'block';
    if (allView) allView.style.display = 'none';
    if (btnCat) { btnCat.classList.remove('btn-light'); btnCat.classList.add('btn-primary'); }
    if (btnAll) { btnAll.classList.remove('btn-primary'); btnAll.classList.add('btn-light'); }
  }
}
</script>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>

