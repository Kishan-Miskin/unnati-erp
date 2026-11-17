<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$product = db()->fetchOne(
    "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=?",
    [$id]
);
if (!$product) { $_SESSION['flash_error']='Product not found.'; header('Location:'.APP_URL.'/admin/products/index.php'); exit; }

$variants = db()->fetchAll(
    "SELECT * FROM product_variants WHERE product_id=? ORDER BY id",
    [$id]
);
$totalStock = array_sum(array_column($variants, 'current_stock'));
$movements = db()->fetchAll(
    "SELECT sm.*, pv.sku, u.full_name AS employee, sm.created_at
     FROM stock_movements sm JOIN product_variants pv ON sm.variant_id=pv.id LEFT JOIN users u ON sm.created_by=u.id
     WHERE pv.product_id=? ORDER BY sm.id DESC LIMIT 10",
    [$id]
);

$pageTitle = e($product['name']); $activePage='products';
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title"><i class="fas fa-box text-primary" style="margin-right:10px"></i><?= e($product['name']) ?></h1>
    <p class="page-subtitle"><?= e($product['category_name'] ?? 'Uncategorized') ?> &bull; <span class="badge badge-<?= $product['status']==='active'?'success':'danger' ?>"><?= ucfirst($product['status']) ?></span></p>
  </div>
  <div class="page-header-right">
    <a href="<?= APP_URL ?>/admin/products/edit.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
    <a href="<?= APP_URL ?>/admin/purchases/add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Stock</a>
    <a href="<?= APP_URL ?>/admin/products/index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<div class="grid-2">
  <div>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-info-circle"></i> Product Details</span></div>
      <div class="card-body">
        <?php if ($product['image'] && file_exists(UPLOAD_PATH.'products/'.$product['image'])): ?>
        <div style="margin-bottom:16px"><img src="<?= APP_URL ?>/uploads/products/<?= e($product['image']) ?>" style="max-height:160px;border-radius:10px;border:1px solid #e2e8f0"></div>
        <?php endif; ?>
        <table style="width:100%;border-collapse:collapse">
          <?php
          $fields = [
            'Material Type / Brand' => $product['brand'] ?? '—',
            'Category'    => $product['category_name'] ?? '—',
            'Description' => $product['description'] ?? '—',
            'Total Variants / Units' => count($variants),
            'Total Stock' => number_format($totalStock).' ('.($variants[0]['size'] ?? 'units').')',
            'Added'       => formatDate($product['created_at']),
          ];
          foreach ($fields as $label => $val):
          ?>
          <tr style="border-bottom:1px solid #f1f5f9"><td style="padding:9px 0;color:#64748b;font-size:0.82rem;font-weight:600;width:130px"><?= $label ?></td><td style="padding:9px 0;font-size:0.85rem"><?= e($val) ?></td></tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-layer-group"></i> Variants & Pricing</span></div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>SKU</th><th>Unit / Size</th><th class="text-right">Cost+GST</th><th class="text-right">Sell Price</th><th class="text-right">Margin%</th><th class="text-center">Stock</th></tr></thead>
          <tbody>
            <?php foreach ($variants as $v):
              $margin = $v['purchase_price_with_gst'] > 0 ? round(($v['selling_price']-$v['purchase_price_with_gst'])/$v['selling_price']*100,1) : 0;
            ?>
            <tr>
              <td><span class="sku-badge"><?= e($v['sku']) ?></span></td>
              <td><?= e($v['size']) ?></td>
              <td class="text-right"><?= formatCurrency($v['purchase_price_with_gst']) ?></td>
              <td class="text-right text-bold"><?= formatCurrency($v['selling_price']) ?></td>
              <td class="text-right"><span class="badge badge-<?= $margin>=20?'success':($margin>=10?'warning':'danger') ?>"><?= $margin ?>%</span></td>
              <td class="text-center">
                <?php if ($v['current_stock']==0): ?><span class="badge badge-danger">0</span>
                <?php elseif ($v['current_stock']<=$v['min_stock_alert']): ?><span class="badge badge-warning"><?= $v['current_stock'] ?></span>
                <?php else: ?><span class="badge badge-success"><?= $v['current_stock'] ?></span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Stock Movements -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-exchange-alt"></i> Recent Stock Movements</span>
    <a href="<?= APP_URL ?>/admin/stock/movements.php?variant_id=<?= $variants[0]['id']??0 ?>" class="btn btn-sm btn-light">View All</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Date/Time</th><th>SKU</th><th class="text-center">Type</th><th class="text-center">Change</th><th class="text-center">After</th><th>Notes</th><th>By</th></tr></thead>
      <tbody>
        <?php foreach ($movements as $m): ?>
        <tr>
          <td style="font-size:0.8rem"><?= formatDateTime($m['created_at']) ?></td>
          <td><span class="sku-badge"><?= e($m['sku']) ?></span></td>
          <td class="text-center"><span class="badge badge-<?= $m['movement_type']==='purchase'?'success':($m['movement_type']==='sale'?'primary':'warning') ?>"><?= ucfirst($m['movement_type']) ?></span></td>
          <td class="text-center <?= $m['qty_change']>0?'text-success':'text-danger' ?>" style="font-weight:700"><?= $m['qty_change']>0?'+':''?><?= $m['qty_change'] ?></td>
          <td class="text-center text-bold"><?= $m['qty_after'] ?></td>
          <td style="font-size:0.8rem"><?= e($m['notes']??'') ?></td>
          <td style="font-size:0.8rem"><?= e($m['employee']??'System') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($movements)): ?><tr><td colspan="7" class="text-center text-muted" style="padding:20px">No movements yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
