<?php
/**
 * Admin: Add Product
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
requireAdmin();

$errors  = [];
$success = '';

// Default pricing values from settings
$defPurchaseGst = getSetting('purchase_gst_pct', '5');
$defSalesGst    = getSetting('sales_gst_pct', '5');
$defProfit      = getSetting('default_profit_markup_pct', '65');
$defMinStock    = getSetting('default_min_stock_alert', '5');

// Convert labels into short, safe SKU segments.
function skuSegment($value, $fallback = 'GEN') {
    $value = strtoupper(trim((string)$value));
    $value = preg_replace('/[^A-Z0-9]+/', '', $value);
    return $value === '' ? $fallback : substr($value, 0, 8);
}

function sizeSkuSegment($size) {
    $map = [
        'Meter' => 'MTR', 'Per Meter' => 'MTR', 'Piece' => 'PCS', 'Roll' => 'ROLL',
        'Yard' => 'YD', 'Pair' => 'PR', 'Packet' => 'PKT',
        'Standard' => 'STD', 'X-Small' => 'XS', 'Small' => 'S',
        'Medium' => 'M', 'Large' => 'L', 'X-Large' => 'XL',
        'XX-Large' => 'XXL', 'XXX-Large' => 'XXXL',
        '4X-Large' => '4XL', '5X-Large' => '5XL',
        'Free Size' => 'FREE', 'One Size' => 'ONE'
    ];
    return isset($map[$size]) ? $map[$size] : skuSegment($size, 'CUSTOM');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();

    // Product fields
    $name        = sanitize($_POST['name'] ?? '');
    $categoryId  = (int)($_POST['category_id'] ?? 0);
    $subCatId    = (int)($_POST['subcategory_id'] ?? 0);
    $brand       = sanitize($_POST['brand'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $status      = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    // Variants (at least 1 required)
    $variants = $_POST['variants'] ?? [];

    if (empty($name)) $errors[] = 'Product name is required.';
    if (empty($variants)) $errors[] = 'At least one variant (size/unit) is required.';

    // Validate variants. SKU is generated automatically by the server.
    $allowedSizes = [
        'Meter', 'Piece', 'Roll', 'Yard', 'Pair', 'Packet',
        'Standard', 'X-Small', 'Small', 'Medium', 'Large', 'X-Large',
        'XX-Large', 'XXX-Large', '4X-Large', '5X-Large', 'Free Size', 'One Size', 'Custom'
    ];
    foreach ($variants as $i => $v) {
        if (!isset($v['purchase_price']) || !is_numeric($v['purchase_price']) || (float)$v['purchase_price'] < 0) {
            $errors[] = "Variant " . ($i+1) . ": Invalid purchase price.";
        }
        $postedSize = $v['size'] ?? 'Standard';
        if (!in_array($postedSize, $allowedSizes, true)) {
            $errors[] = "Variant " . ($i+1) . ": Please select a valid size.";
        }
        if ($postedSize === 'Custom' && trim($v['custom_size'] ?? '') === '') {
            $errors[] = "Variant " . ($i+1) . ": Enter the custom size.";
        }
    }

    // Validate selected category and ensure the subcategory belongs to it.
    if ($categoryId > 0) {
        $categoryCheck = db()->fetchOne("SELECT id FROM categories WHERE id = ? AND parent_id IS NULL AND status='active'", [$categoryId]);
        if (!$categoryCheck) $errors[] = 'Please select a valid category.';
    }
    if ($subCatId > 0) {
        $subCategoryCheck = db()->fetchOne("SELECT id FROM categories WHERE id = ? AND parent_id = ? AND status='active'", [$subCatId, $categoryId]);
        if (!$categoryId || !$subCategoryCheck) $errors[] = 'Please select a valid subcategory for the chosen category.';
    }

    if (empty($errors)) {
        try {
            db()->beginTransaction();

            // Handle image upload
            $imageName = null;
            if (!empty($_FILES['image']['name'])) {
                $uploaded = uploadImage($_FILES['image'], UPLOAD_PATH . 'products/', 'prod_');
                if ($uploaded) $imageName = $uploaded;
                else $errors[] = 'Image upload failed. Check file type and size.';
            }

            if (empty($errors)) {
                // Insert product (Finished shop garment)
                $productId = db()->insert(
                    "INSERT INTO products (category_id, subcategory_id, item_type, name, brand, description, image, status, created_by)
                     VALUES (?,?,'product',?,?,?,?,?,?)",
                    [$categoryId ?: null, $subCatId ?: null, $name, $brand ?: null, $description ?: null, $imageName, $status, currentUserId()]
                );

                // Generate SKU prefix from the selected category and subcategory.
                $categoryRow = $categoryId ? db()->fetchOne("SELECT name FROM categories WHERE id = ?", [$categoryId]) : null;
                $subCategoryRow = $subCatId ? db()->fetchOne("SELECT name FROM categories WHERE id = ?", [$subCatId]) : null;
                $categoryCode = skuSegment($categoryRow['name'] ?? '', 'GEN');
                $subCategoryCode = skuSegment($subCategoryRow['name'] ?? '', 'GEN');

                // Product ID guarantees uniqueness between products; row suffix distinguishes variant rows.
                $variantNumber = 0;
                foreach ($variants as $v) {
                    $variantNumber++;
                    $selectedSize = $v['size'] ?? 'Standard';
                    $size = ($selectedSize === 'Custom')
                        ? sanitize($v['custom_size'] ?? '')
                        : sanitize($selectedSize);
                    if ($size === '') $size = 'Standard';

                    $sku = $categoryCode . '-' . $subCategoryCode . '-P'
                        . str_pad((string)$productId, 6, '0', STR_PAD_LEFT)
                        . '-' . sizeSkuSegment($size)
                        . '-' . str_pad((string)$variantNumber, 2, '0', STR_PAD_LEFT);

                    $purPrice      = (float)$v['purchase_price'];
                    $purGstPct     = (float)($v['purchase_gst_pct'] ?? $defPurchaseGst);
                    $profitPct     = (float)($v['profit_markup_pct'] ?? $defProfit);
                    $salesGstPct   = (float)($v['sales_gst_pct'] ?? $defSalesGst);
                    $tagPrice      = (float)($v['tag_price'] ?? 0);
                    $minAlert      = (int)($v['min_stock_alert'] ?? $defMinStock);
                    $initStock     = (int)($v['initial_stock'] ?? 0);

                    // Server-side pricing calculation
                    $pricing = calculatePricing($purPrice, $purGstPct, $profitPct, $salesGstPct);

                    $variantId = db()->insert(
                        "INSERT INTO product_variants
                         (product_id, sku, size, purchase_price, purchase_gst_pct, purchase_gst_amount,
                          purchase_price_with_gst, profit_markup_pct, profit_amount, selling_price_before_gst,
                          sales_gst_pct, sales_gst_amount, selling_price, tag_price, current_stock, min_stock_alert)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                        [
                            $productId, $sku, $size, $pricing['purchase_price'], $pricing['purchase_gst_pct'],
                            $pricing['purchase_gst_amount'], $pricing['purchase_price_with_gst'],
                            $pricing['profit_markup_pct'], $pricing['profit_amount'],
                            $pricing['selling_price_before_gst'], $pricing['sales_gst_pct'],
                            $pricing['sales_gst_amount'], $pricing['selling_price'],
                            $tagPrice ?: $pricing['selling_price'],
                            $initStock, $minAlert
                        ]
                    );

                    // Record initial stock if provided
                    if ($initStock > 0) {
                        db()->insert(
                            "INSERT INTO stock_movements (variant_id, movement_type, qty_change, qty_before, qty_after, reference_id, reference_type, notes, created_by)
                             VALUES (?,?,?,?,?,?,?,?,?)",
                            [$variantId, 'purchase', $initStock, 0, $initStock, $productId, 'product', 'Initial stock on product creation', currentUserId()]
                        );
                    }
                }

                db()->commit();
                logActivity("Added product: $name (ID: $productId)", 'products', $productId, 'product');
                $_SESSION['flash_success'] = "Product '$name' added successfully.";
                header('Location: ' . APP_URL . '/admin/products/view.php?id=' . $productId);
                exit;
            }
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$categories = db()->fetchAll("SELECT id, name FROM categories WHERE parent_id IS NULL AND status='active' ORDER BY name");
$pageTitle   = 'Add Product';
$activePage  = 'products';
$breadcrumb  = [['label' => 'Products', 'url' => APP_URL . '/admin/products/index.php'], ['label' => 'Add Product']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<script>window.APP_URL='<?= APP_URL ?>';</script>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title"><i class="fas fa-plus-circle text-primary" style="margin-right:10px"></i>Add New Product</h1>
  </div>
  <div class="page-header-right">
    <a href="<?= APP_URL ?>/admin/products/index.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<form method="POST" enctype="multipart/form-data" id="productForm">
  <?= csrfField() ?>

  <div class="grid-2">
    <!-- Left column: Product Info -->
    <div>
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-info-circle"></i> Product Information</span></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Product Name <span class="required">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Cotton T-Shirt" value="<?= e($_POST['name'] ?? '') ?>">
          </div>
          <div class="form-row cols-2">
            <div class="form-group">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-select" id="catSelect" onchange="loadSubcats(this.value)">
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Subcategory</label>
              <select name="subcategory_id" class="form-select" id="subcatSelect">
                <option value="">Select Subcategory</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Brand</label>
            <input type="text" name="brand" class="form-control" placeholder="Optional" value="<?= e($_POST['brand'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Optional product description"><?= e($_POST['description'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Image Upload -->
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-image"></i> Product Image</span></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Upload Image (JPG, PNG, WebP — max 5MB)</label>
            <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
          </div>
          <div id="imagePreview" style="display:none;margin-top:12px">
            <img id="previewImg" style="max-height:200px;border-radius:10px;border:1px solid #e2e8f0" alt="Preview">
          </div>
        </div>
      </div>
    </div>

    <!-- Right column: Variants -->
    <div>
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-layer-group"></i> Product Variants (Sizes)</span>
          <button type="button" class="btn btn-sm btn-primary" onclick="addVariantRow()">
            <i class="fas fa-plus"></i> Add Size
          </button>
        </div>
        <div class="card-body p-0">
          <div id="variantsContainer">
            <!-- Variant rows inserted here -->
          </div>
        </div>
        <div class="card-footer">
          <small class="text-muted"><i class="fas fa-info-circle"></i> Each size is a separate variant with its own SKU and stock quantity.</small>
        </div>
      </div>

      <!-- Pricing Defaults Info -->
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-calculator"></i> Default Pricing Rates</span></div>
        <div class="card-body">
          <div class="form-row cols-3">
            <div class="form-group mb-0">
              <label class="form-label text-xs text-muted">Purchase GST %</label>
              <input type="number" id="defPurchaseGst" value="<?= $defPurchaseGst ?>" class="form-control" step="0.01">
            </div>
            <div class="form-group mb-0">
              <label class="form-label text-xs text-muted">Profit Markup %</label>
              <input type="number" id="defProfit" value="<?= $defProfit ?>" class="form-control" step="0.01">
            </div>
            <div class="form-group mb-0">
              <label class="form-label text-xs text-muted">Sales GST %</label>
              <input type="number" id="defSalesGst" value="<?= $defSalesGst ?>" class="form-control" step="0.01">
            </div>
          </div>
          <div class="mt-3">
            <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="applyDefaultRates()">
              <i class="fas fa-sync"></i> Apply These Rates to All Variants
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-3 justify-end mt-4">
    <a href="<?= APP_URL ?>/admin/products/index.php" class="btn btn-light">Cancel</a>
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="fas fa-save"></i> Save Product
    </button>
  </div>
</form>

<script>
let variantCount = 0;

function addVariantRow(data) {
  variantCount++;
  const idx = variantCount;
  const defPGst = document.getElementById('defPurchaseGst').value || '<?= $defPurchaseGst ?>';
  const defProfit = document.getElementById('defProfit').value || '<?= $defProfit ?>';
  const defSGst = document.getElementById('defSalesGst').value || '<?= $defSalesGst ?>';

  const div = document.createElement('div');
  div.id = 'variant-' + idx;
  div.style.cssText = 'border-bottom:1px solid #e2e8f0;padding:18px 20px;';

  div.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <strong style="font-size:0.88rem;color:#1e293b">Variant #${idx}</strong>
      ${idx > 1 ? '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVariant('+idx+')"><i class="fas fa-trash"></i></button>' : ''}
    </div>
    <div class="form-row cols-2" style="margin-bottom:14px">
      <div class="form-group mb-0">
        <label class="form-label text-xs">SKU / Product Code</label>
        <input type="text" id="sku-preview-${idx}" class="form-control" readonly
               value="Generated automatically" style="background:#f1f5f9;color:#475569;font-weight:600">
        <small class="text-muted">Generated automatically from category, subcategory and size.</small>
      </div>
      <div class="form-group mb-0">
        <label class="form-label text-xs">Size <span class="required">*</span></label>
        <select name="variants[${idx}][size]" id="variant-size-${idx}" class="form-select"
                onchange="toggleCustomSize(${idx}); updateSkuPreviews()" required>
          <option value="Standard" selected>Standard</option>
          <option value="Small">Small (S)</option>
          <option value="Medium">Medium (M)</option>
          <option value="Large">Large (L)</option>
          <option value="X-Large">X-Large (XL)</option>
          <option value="XX-Large">XX-Large (XXL)</option>
          <option value="Free Size">Free Size</option>
          <option value="Custom">Other / Custom size...</option>
        </select>
        <input type="text" name="variants[${idx}][custom_size]" id="custom-size-${idx}"
               class="form-control mt-2" placeholder="Enter custom size, e.g. 38, 40, 42, XS, Plus Size"
               style="display:none" maxlength="40">
      </div>
    </div>
    <div class="form-row cols-2" style="margin-bottom:14px">
      <div class="form-group mb-0">
        <label class="form-label text-xs">Purchase Price (₹) <span class="required">*</span></label>
        <input type="number" name="variants[${idx}][purchase_price]" class="form-control" step="0.01" min="0" required
               onchange="calcVariant(${idx})" placeholder="0.00" value="${data?.purchase_price||''}">
      </div>
      <div class="form-group mb-0">
        <label class="form-label text-xs">Initial Stock Qty</label>
        <input type="number" name="variants[${idx}][initial_stock]" class="form-control" min="0" value="${data?.initial_stock||0}">
      </div>
    </div>
    <div class="form-row cols-3" style="margin-bottom:14px">
      <div class="form-group mb-0">
        <label class="form-label text-xs">Purchase GST %</label>
        <input type="number" name="variants[${idx}][purchase_gst_pct]" class="form-control" step="0.01" onchange="calcVariant(${idx})" value="${defPGst}">
      </div>
      <div class="form-group mb-0">
        <label class="form-label text-xs">Profit Markup %</label>
        <input type="number" name="variants[${idx}][profit_markup_pct]" class="form-control" step="0.01" onchange="calcVariant(${idx})" value="${defProfit}">
      </div>
      <div class="form-group mb-0">
        <label class="form-label text-xs">Sales GST %</label>
        <input type="number" name="variants[${idx}][sales_gst_pct]" class="form-control" step="0.01" onchange="calcVariant(${idx})" value="${defSGst}">
      </div>
    </div>
    <div id="pricing-preview-${idx}" style="background:#f8fafc;border-radius:8px;padding:12px;border:1px solid #e2e8f0;font-size:0.8rem;display:none">
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
        <div><span style="color:#64748b">Purchase+GST:</span><br><strong id="pp-with-gst-${idx}">₹0.00</strong></div>
        <div><span style="color:#64748b">Selling Price:</span><br><strong id="selling-price-${idx}" style="color:#2563eb">₹0.00</strong></div>
        <div><span style="color:#64748b">Gross Profit/unit:</span><br><strong id="gross-profit-${idx}" style="color:#059669">₹0.00</strong></div>
      </div>
    </div>
    <div class="form-row cols-2 mt-3">
      <div class="form-group mb-0">
        <label class="form-label text-xs">Tag/MRP (₹)</label>
        <input type="number" name="variants[${idx}][tag_price]" class="form-control" step="0.01" min="0" placeholder="Auto-filled from selling price" value="${data?.tag_price||''}">
      </div>
      <div class="form-group mb-0">
        <label class="form-label text-xs">Min Stock Alert</label>
        <input type="number" name="variants[${idx}][min_stock_alert]" class="form-control" min="0" value="<?= $defMinStock ?>">
      </div>
    </div>
  `;

  document.getElementById('variantsContainer').appendChild(div);
  updateSkuPreviews();
}

function removeVariant(idx) {
  const el = document.getElementById('variant-' + idx);
  if (el) el.remove();
}

function calcVariant(idx) {
  const pp   = parseFloat(document.querySelector(`[name="variants[${idx}][purchase_price]"]`)?.value) || 0;
  const pgst = parseFloat(document.querySelector(`[name="variants[${idx}][purchase_gst_pct]"]`)?.value) || 5;
  const prof = parseFloat(document.querySelector(`[name="variants[${idx}][profit_markup_pct]"]`)?.value) || 65;
  const sgst = parseFloat(document.querySelector(`[name="variants[${idx}][sales_gst_pct]"]`)?.value) || 5;

  if (pp <= 0) return;
  const result = calculatePricing(pp, pgst, prof, sgst);
  const preview = document.getElementById('pricing-preview-' + idx);
  if (preview) {
    preview.style.display = 'block';
    document.getElementById('pp-with-gst-'+idx).textContent = '₹' + result.purchasePriceWithGst.toFixed(2);
    document.getElementById('selling-price-'+idx).textContent = '₹' + result.sellingPrice.toFixed(2);
    document.getElementById('gross-profit-'+idx).textContent = '₹' + result.profitAmount.toFixed(2);
    // Auto-fill tag price if empty
    const tagInput = document.querySelector(`[name="variants[${idx}][tag_price]"]`);
    if (tagInput && !tagInput.value) tagInput.value = result.sellingPrice.toFixed(2);
  }
}

function applyDefaultRates() {
  const pGst = document.getElementById('defPurchaseGst').value;
  const prof = document.getElementById('defProfit').value;
  const sGst = document.getElementById('defSalesGst').value;
  document.querySelectorAll('[name*="[purchase_gst_pct]"]').forEach(function(el) { el.value = pGst; });
  document.querySelectorAll('[name*="[profit_markup_pct]"]').forEach(function(el) { el.value = prof; });
  document.querySelectorAll('[name*="[sales_gst_pct]"]').forEach(function(el) { el.value = sGst; });
  showToast('Default rates applied to all variants', 'success');
}

function previewImage(input) {
  const preview = document.getElementById('imagePreview');
  const img = document.getElementById('previewImg');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) { img.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}

function loadSubcats(catId) {
  const select = document.getElementById('subcatSelect');
  select.innerHTML = '<option value="">Loading...</option>';
  if (!catId) { select.innerHTML = '<option value="">Select Subcategory</option>'; return; }
  apiCall(window.APP_URL + '/api/get_subcategories.php', 'GET', { parent_id: catId }).then(function(res) {
    select.innerHTML = '<option value="">Select Subcategory</option>';
    if (res.success && res.data) {
      res.data.forEach(function(sub) {
        const opt = document.createElement('option');
        opt.value = sub.id; opt.textContent = sub.name;
        select.appendChild(opt);
      });
    }
    updateSkuPreviews();
  }).catch(function() { select.innerHTML = '<option value="">Select Subcategory</option>'; updateSkuPreviews(); });
}

function toggleCustomSize(idx) {
  const select = document.getElementById('variant-size-' + idx);
  const input = document.getElementById('custom-size-' + idx);
  if (!select || !input) return;
  const custom = select.value === 'Custom';
  input.style.display = custom ? 'block' : 'none';
  input.required = custom;
}

function skuPreviewToken(value, fallback) {
  const token = String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 8);
  return token || fallback;
}

function updateSkuPreviews() {
  const cat = document.getElementById('catSelect');
  const sub = document.getElementById('subcatSelect');
  const catName = cat && cat.selectedIndex > 0 ? cat.options[cat.selectedIndex].text : 'GEN';
  const subName = sub && sub.selectedIndex > 0 ? sub.options[sub.selectedIndex].text : 'GEN';
  const catCode = skuPreviewToken(catName, 'GEN');
  const subCode = skuPreviewToken(subName, 'GEN');

  document.querySelectorAll('[id^="variant-size-"]').forEach(function(select) {
    const idx = select.id.replace('variant-size-', '');
    const customInput = document.getElementById('custom-size-' + idx);
    const sizeValue = select.value === 'Custom' ? ((customInput && customInput.value) || 'CUSTOM') : select.value;
    const map = {
      'Meter':'MTR','Roll':'ROLL','Piece':'PCS','Yard':'YD','Pair':'PR','Packet':'PKT',
      'Standard':'STD','X-Small':'XS','Small':'S','Medium':'M','Large':'L',
      'X-Large':'XL','XX-Large':'XXL','XXX-Large':'XXXL','4X-Large':'4XL',
      '5X-Large':'5XL','Free Size':'FREE','One Size':'ONE'
    };
    const sizeCode = map[sizeValue] || skuPreviewToken(sizeValue, 'CUSTOM');
    const preview = document.getElementById('sku-preview-' + idx);
    if (preview) preview.value = catCode + '-' + subCode + '-P[ID]-' + sizeCode;
  });
}

// Add first variant by default
addVariantRow();
document.getElementById('catSelect').addEventListener('change', updateSkuPreviews);
document.getElementById('subcatSelect').addEventListener('change', updateSkuPreviews);
document.getElementById('variantsContainer').addEventListener('input', function(event) {
  if (event.target && event.target.id.indexOf('custom-size-') === 0) updateSkuPreviews();
});
</script>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
