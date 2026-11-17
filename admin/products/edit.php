<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$variantId = (int)($_GET['variant_id'] ?? 0);

if (!$id && $variantId) {
    $vRow = db()->fetchOne("SELECT product_id FROM product_variants WHERE id = ?", [$variantId]);
    if ($vRow) $id = (int)$vRow['product_id'];
}

$product = db()->fetchOne(
    "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=?",
    [$id]
);
if (!$product) { $_SESSION['flash_error']='Product not found.'; header('Location:'.APP_URL.'/admin/products/index.php'); exit; }

$variants = db()->fetchAll("SELECT * FROM product_variants WHERE product_id=? ORDER BY id", [$id]);
$categories = db()->fetchAll("SELECT id, name FROM categories WHERE parent_id IS NULL AND status='active' ORDER BY name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $name        = sanitize($_POST['name'] ?? '');
    $categoryId  = (int)($_POST['category_id'] ?? 0);
    $brand       = sanitize($_POST['brand'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $status      = in_array($_POST['status']??'', ['active','inactive']) ? $_POST['status'] : 'active';

    if (empty($name)) $errors[] = 'Product name required.';

    if (empty($errors)) {
        // Handle image
        $imageName = $product['image'];
        if (!empty($_FILES['image']['name'])) {
            $uploaded = uploadImage($_FILES['image'], UPLOAD_PATH.'products/', 'prod_');
            if ($uploaded) {
                if ($imageName && file_exists(UPLOAD_PATH.'products/'.$imageName)) unlink(UPLOAD_PATH.'products/'.$imageName);
                $imageName = $uploaded;
            }
        }

        db()->execute(
            "UPDATE products SET name=?,category_id=?,brand=?,description=?,image=?,status=?,updated_at=NOW() WHERE id=?",
            [$name, $categoryId?:null, $brand?:null, $description?:null, $imageName, $status, $id]
        );

        // Update each variant pricing if purchase_price changed
        $updatedVariants = $_POST['variants'] ?? [];
        foreach ($updatedVariants as $vid => $v) {
            $pp   = (float)($v['purchase_price'] ?? 0);
            $pgst = (float)($v['purchase_gst_pct'] ?? 5);
            $prof = (float)($v['profit_markup_pct'] ?? 65);
            $sgst = (float)($v['sales_gst_pct'] ?? 5);
            $size = sanitize($v['size'] ?? 'Standard');
            $tag  = (float)($v['tag_price'] ?? 0);
            $min  = (int)($v['min_stock_alert'] ?? 5);
            $vstatus = sanitize($v['status'] ?? 'active');

            if ($pp > 0) {
                $pricing = calculatePricing($pp, $pgst, $prof, $sgst);
                db()->execute(
                    "UPDATE product_variants SET size=?,purchase_price=?,purchase_gst_pct=?,purchase_gst_amount=?,
                     purchase_price_with_gst=?,profit_markup_pct=?,profit_amount=?,selling_price_before_gst=?,
                     sales_gst_pct=?,sales_gst_amount=?,selling_price=?,tag_price=?,min_stock_alert=?,status=?,updated_at=NOW()
                     WHERE id=? AND product_id=?",
                    [$size, $pricing['purchase_price'], $pricing['purchase_gst_pct'], $pricing['purchase_gst_amount'],
                     $pricing['purchase_price_with_gst'], $pricing['profit_markup_pct'], $pricing['profit_amount'],
                     $pricing['selling_price_before_gst'], $pricing['sales_gst_pct'], $pricing['sales_gst_amount'],
                     $pricing['selling_price'], $tag?:$pricing['selling_price'], $min, $vstatus, $vid, $id]
                );
            }
        }

        logActivity("Updated product: $name", 'products', $id, 'product');
        $_SESSION['flash_success'] = "Product '$name' updated.";
        header('Location: '.APP_URL.'/admin/products/view.php?id='.$id); exit;
    }
}

$pageTitle='Edit Product'; $activePage='products';
$breadcrumb = [['label'=>'Products','url'=>APP_URL.'/admin/products/index.php'],['label'=>e($product['name']),'url'=>APP_URL.'/admin/products/view.php?id='.$id],['label'=>'Edit']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<script>window.APP_URL='<?= APP_URL ?>';</script>

<!-- Stock Adjustment Modal -->
<div class="modal-overlay" id="stockAdjustModal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-boxes text-primary"></i> Adjust Stock</span>
      <button class="modal-close" onclick="closeModal('stockAdjustModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div style="background:#f1f5f9;padding:12px;border-radius:6px;margin-bottom:16px">
        <div style="font-size:0.85rem;color:#64748b">SKU</div>
        <div style="font-weight:600" id="adjustSku"></div>
      </div>
      
      <div class="form-group">
        <label class="form-label">Current Stock</label>
        <input type="number" id="currentStockDisplay" class="form-control" readonly disabled style="background:#f8fafc">
      </div>

      <div class="form-group">
        <label class="form-label">Adjustment Type <span class="required">*</span></label>
        <select id="adjustType" class="form-select" onchange="updateAdjustPreview()">
          <option value="add">Add Stock (+)</option>
          <option value="subtract">Remove Stock (-)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Quantity <span class="required">*</span></label>
        <input type="number" id="adjustQty" class="form-control" min="1" value="1" oninput="updateAdjustPreview()" required>
      </div>

      <div style="background:#eff6ff;padding:12px;border-radius:6px;border-left:3px solid #2563eb;margin-bottom:16px">
        <div style="font-size:0.85rem;color:#1e40af;font-weight:500">New Stock: <span id="newStockPreview" style="font-size:1.1rem">0</span></div>
      </div>

      <div class="form-group">
        <label class="form-label">Reason <span class="required">*</span></label>
        <textarea id="adjustReason" class="form-control" rows="3" placeholder="Enter reason for adjustment..." required></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-light" onclick="closeModal('stockAdjustModal')">Cancel</button>
      <button class="btn btn-primary" id="confirmAdjustBtn" onclick="confirmStockAdjust()">
        <i class="fas fa-check"></i> Adjust Stock
      </button>
    </div>
  </div>
</div>

<div class="page-header">
  <div class="page-header-left"><h1 class="page-title"><i class="fas fa-edit text-primary" style="margin-right:10px"></i>Edit: <?= e($product['name']) ?></h1></div>
  <div class="page-header-right"><a href="<?= APP_URL ?>/admin/products/view.php?id=<?= $id ?>" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a></div>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<form method="POST" enctype="multipart/form-data">
  <?= csrfField() ?>
  <div class="grid-2">
    <div>
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-info-circle"></i> Product Info</span></div>
        <div class="card-body">
          <div class="form-group"><label class="form-label">Name <span class="required">*</span></label><input type="text" name="name" class="form-control" required value="<?= e($product['name']) ?>"></div>
          <div class="form-row cols-2">
            <div class="form-group"><label class="form-label">Category</label>
              <select name="category_id" class="form-select">
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= $product['category_id']==$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="active" <?= $product['status']==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $product['status']==='inactive'?'selected':'' ?>>Inactive</option>
              </select>
            </div>
          </div>
          <div class="form-group"><label class="form-label">Brand</label><input type="text" name="brand" class="form-control" value="<?= e($product['brand']??'') ?>"></div>
          <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= e($product['description']??'') ?></textarea></div>
          <div class="form-group mb-0">
            <label class="form-label">Image</label>
            <?php if ($product['image'] && file_exists(UPLOAD_PATH.'products/'.$product['image'])): ?>
            <div style="margin-bottom:8px"><img src="<?= APP_URL ?>/uploads/products/<?= e($product['image']) ?>" style="height:80px;border-radius:8px;border:1px solid #e2e8f0"></div>
            <?php endif; ?>
            <input type="file" name="image" class="form-control" accept="image/*">
          </div>
        </div>
      </div>
    </div>
    <div>
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-layer-group"></i> Variants</span></div>
        <?php foreach ($variants as $v):
          $margin = $v['purchase_price_with_gst'] > 0 ? round(($v['selling_price']-$v['purchase_price_with_gst'])/$v['selling_price']*100,1) : 0;
        ?>
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0">
          <div style="display:flex;justify-content:space-between;margin-bottom:12px">
            <strong style="font-size:0.88rem"><?= e($v['sku']) ?></strong>
            <span class="badge badge-<?= $margin>=20?'success':($margin>=10?'warning':'danger') ?>"><?= $margin ?>% margin</span>
          </div>
          <div class="form-row cols-2" style="margin-bottom:10px">
            <div class="form-group mb-0"><label class="form-label text-xs">Unit / Measurement</label><input type="text" name="variants[<?= $v['id'] ?>][size]" class="form-control" value="<?= e($v['size']) ?>" placeholder="e.g. Meter, Piece, Roll"></div>
            <div class="form-group mb-0"><label class="form-label text-xs">Status</label>
              <select name="variants[<?= $v['id'] ?>][status]" class="form-select">
                <option value="active" <?= $v['status']==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $v['status']==='inactive'?'selected':'' ?>>Inactive</option>
              </select>
            </div>
          </div>
          <div class="form-row cols-3">
            <div class="form-group mb-0"><label class="form-label text-xs">Purchase Price</label><input type="number" name="variants[<?= $v['id'] ?>][purchase_price]" class="form-control" step="0.01" value="<?= $v['purchase_price'] ?>" onchange="calcEdit(<?= $v['id'] ?>,this)"></div>
            <div class="form-group mb-0"><label class="form-label text-xs">Purchase GST%</label><input type="number" name="variants[<?= $v['id'] ?>][purchase_gst_pct]" class="form-control" step="0.01" value="<?= $v['purchase_gst_pct'] ?>"></div>
            <div class="form-group mb-0"><label class="form-label text-xs">Profit Markup%</label><input type="number" name="variants[<?= $v['id'] ?>][profit_markup_pct]" class="form-control" step="0.01" value="<?= $v['profit_markup_pct'] ?>"></div>
          </div>
          <div class="form-row cols-3 mt-2">
            <div class="form-group mb-0"><label class="form-label text-xs">Sales GST%</label><input type="number" name="variants[<?= $v['id'] ?>][sales_gst_pct]" class="form-control" step="0.01" value="<?= $v['sales_gst_pct'] ?>"></div>
            <div class="form-group mb-0"><label class="form-label text-xs">Tag/MRP</label><input type="number" name="variants[<?= $v['id'] ?>][tag_price]" class="form-control" step="0.01" value="<?= $v['tag_price'] ?>"></div>
            <div class="form-group mb-0"><label class="form-label text-xs">Min Alert</label><input type="number" name="variants[<?= $v['id'] ?>][min_stock_alert]" class="form-control" value="<?= $v['min_stock_alert'] ?>"></div>
          </div>
          <div style="background:#f8fafc;border-radius:6px;padding:8px 10px;margin-top:10px;font-size:0.78rem">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
              <div style="color:#64748b">
                Current: Sell Price = <strong class="text-primary">₹<?= $v['selling_price'] ?></strong> &bull; 
                Stock = <strong class="<?= $v['current_stock'] <= $v['min_stock_alert'] ? 'text-danger' : 'text-success' ?>"><?= $v['current_stock'] ?></strong>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="showStockAdjustModal(<?= $v['id'] ?>, '<?= addslashes(e($v['sku'])) ?>', <?= $v['current_stock'] ?>)" title="Adjust Stock">
                <i class="fas fa-boxes"></i> Adjust
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button></div>
      </div>
    </div>
  </div>
</form>
<script>
let adjustVariantId = null;
let adjustCurrentStock = 0;

function showStockAdjustModal(variantId, sku, currentStock) {
  adjustVariantId = variantId;
  adjustCurrentStock = currentStock;
  
  document.getElementById('adjustSku').textContent = sku;
  document.getElementById('currentStockDisplay').value = currentStock;
  document.getElementById('adjustQty').value = 1;
  document.getElementById('adjustReason').value = '';
  document.getElementById('adjustType').value = 'add';
  
  updateAdjustPreview();
  openModal('stockAdjustModal');
}

function updateAdjustPreview() {
  const type = document.getElementById('adjustType').value;
  const qty = parseInt(document.getElementById('adjustQty').value) || 0;
  const newStock = type === 'add' ? adjustCurrentStock + qty : adjustCurrentStock - qty;
  
  document.getElementById('newStockPreview').textContent = Math.max(0, newStock);
  document.getElementById('newStockPreview').style.color = newStock < 0 ? '#dc2626' : '#059669';
}

function confirmStockAdjust() {
  const type = document.getElementById('adjustType').value;
  const qty = parseInt(document.getElementById('adjustQty').value);
  const reason = document.getElementById('adjustReason').value.trim();
  
  if (!qty || qty < 1) {
    showToast('Please enter a valid quantity', 'error');
    return;
  }
  
  if (!reason) {
    showToast('Please enter a reason for adjustment', 'error');
    document.getElementById('adjustReason').focus();
    return;
  }
  
  const btn = document.getElementById('confirmAdjustBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner spinner-sm"></span> Adjusting...';
  
  const formData = new FormData();
  formData.append('variant_id', adjustVariantId);
  formData.append('adjustment_type', type);
  formData.append('qty_change', qty);
  formData.append('reason', reason);
  formData.append('csrf_token', '<?= csrfToken() ?>');
  
  fetch(APP_URL + '/api/adjust_stock.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(data.message, 'success');
      closeModal('stockAdjustModal');
      setTimeout(() => window.location.reload(), 1000);
    } else {
      showToast(data.message, 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check"></i> Adjust Stock';
    }
  })
  .catch(err => {
    showToast('Failed to adjust stock. Please try again.', 'error');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Adjust Stock';
  });
}

function calcEdit(id, input) {
  const parent = input.closest('div[style*="border-bottom"]');
  const pp   = parseFloat(parent.querySelector('[name*="[purchase_price]"]').value)||0;
  const pgst = parseFloat(parent.querySelector('[name*="[purchase_gst_pct]"]').value)||5;
  const prof = parseFloat(parent.querySelector('[name*="[profit_markup_pct]"]').value)||65;
  const sgst = parseFloat(parent.querySelector('[name*="[sales_gst_pct]"]').value)||5;
  if (pp>0) {
    const result = calculatePricing(pp,pgst,prof,sgst);
    const info = parent.querySelector('[style*="background:#f8fafc"]');
    if (info) {
      const stockDiv = info.querySelector('div');
      const previewText = 'Preview: Sell Price = <strong class="text-primary">₹'+result.sellingPrice.toFixed(2)+'</strong> &bull; Cost+GST = ₹'+result.purchasePriceWithGst.toFixed(2)+' &bull; Margin = ₹'+result.profitAmount.toFixed(2);
      if (stockDiv) stockDiv.innerHTML = previewText; else info.innerHTML = previewText;
    }
  }
}
</script>
<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
