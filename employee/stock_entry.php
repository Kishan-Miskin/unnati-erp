<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();
if (!currentUser()['can_enter_stock']) {
    $_SESSION['flash_error'] = 'You do not have permission to enter stock.';
    header('Location: '.APP_URL.'/employee/dashboard.php'); exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $purchaseDate = sanitize($_POST['purchase_date'] ?? date('Y-m-d'));
    $supplierName = sanitize($_POST['supplier_name'] ?? '');
    $notes        = sanitize($_POST['notes'] ?? '');
    $items        = $_POST['items'] ?? [];

    if (empty($items)) $errors[] = 'Add at least one product.';

    if (empty($errors)) {
        try {
            db()->beginTransaction();
            $purchaseNo = generatePurchaseNumber();
            $totalAmt = 0; $totalQty = 0;
            $validItems = [];

            foreach ($items as $item) {
                $variantId = (int)($item['variant_id'] ?? 0);
                $qty       = (int)($item['qty'] ?? 0);
                if (!$variantId || $qty <= 0) continue;
                $variant = db()->fetchOne("SELECT id FROM product_variants WHERE id=?", [$variantId]);
                if (!$variant) continue;
                $totalQty += $qty;
                $validItems[] = ['variant_id'=>$variantId, 'qty'=>$qty, 'notes'=>$notes];
            }

            if (empty($validItems)) throw new Exception('No valid items.');

            $purchaseId = db()->insert(
                "INSERT INTO purchases (purchase_no, purchase_date, supplier_name, total_items, total_qty, total_amount, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$purchaseNo, $purchaseDate, $supplierName?:null, count($validItems), $totalQty, 0, $notes, currentUserId()]
            );

            foreach ($validItems as $vi) {
                db()->insert("INSERT INTO purchase_items (purchase_id, variant_id, qty) VALUES (?,?,?)", [$purchaseId, $vi['variant_id'], $vi['qty']]);
                recordStockMovement($vi['variant_id'], 'purchase', $vi['qty'], $purchaseId, 'purchase', "Stock entry: $purchaseNo by employee", currentUserId());
            }

            db()->commit();
            logActivity("Employee stock entry: $purchaseNo", 'stock', $purchaseId, 'purchase');
            $_SESSION['flash_success'] = "Stock entry $purchaseNo recorded successfully ($totalQty units added).";
            header('Location: '.APP_URL.'/employee/dashboard.php'); exit;
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Error: '.$e->getMessage();
        }
    }
}

$pageTitle  = 'Stock Entry'; $activePage = 'stock_entry';
$extraJs    = '<script src="'.APP_URL.'/assets/js/billing.js"></script>';
include dirname(__DIR__) . '/includes/header.php';
?>
<script>window.APP_URL='<?= APP_URL ?>';</script>
<div class="page-header">
  <div class="page-header-left"><h1 class="page-title"><i class="fas fa-boxes text-primary" style="margin-right:10px"></i>Stock Entry</h1></div>
  <div class="page-header-right"><a href="<?= APP_URL ?>/employee/dashboard.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Dashboard</a></div>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<form method="POST" id="stockForm">
  <?= csrfField() ?>
  <div class="grid-2">
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-info-circle"></i> Entry Details</span></div>
      <div class="card-body">
        <div class="form-row cols-2">
          <div class="form-group"><label class="form-label">Date</label><input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="form-group"><label class="form-label">Supplier Name</label><input type="text" name="supplier_name" class="form-control" placeholder="Optional"></div>
        </div>
        <div class="form-group mb-0"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Optional remarks"></textarea></div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-search"></i> Search Product</span></div>
      <div class="card-body">
        <div style="position:relative">
          <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none"></i>
          <input type="text" id="productSearchInput" class="form-control" placeholder="Search by SKU or product name..." style="padding-left:38px" autocomplete="off">
        </div>
        <div id="stockItems" style="margin-top:16px">
          <div class="text-center text-muted" style="padding:24px;border:2px dashed #e2e8f0;border-radius:8px">Search and add products above</div>
        </div>
      </div>
    </div>
  </div>
  <button type="submit" class="btn btn-primary btn-lg" style="margin-top:16px"><i class="fas fa-save"></i> Submit Stock Entry</button>
</form>

<script>
let stockRows = [];
let sc = 0;

document.addEventListener('DOMContentLoaded', function() {
  initProductSearch(document.getElementById('productSearchInput'), function(product) {
    const existing = stockRows.find(function(r) { return r.variantId === product.id; });
    if (existing) {
      const inp = document.getElementById('sqi-'+existing.idx);
      if (inp) { inp.value = parseInt(inp.value)+1; showToast('Qty updated for '+product.name,'info'); }
      return;
    }
    sc++;
    const idx = sc;
    stockRows.push({idx:idx, variantId:product.id});

    const container = document.getElementById('stockItems');
    // Clear placeholder
    const placeholder = container.querySelector('.text-center');
    if (placeholder) placeholder.remove();

    const row = document.createElement('div');
    row.id = 'sr-'+idx;
    row.style.cssText = 'display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9';
    row.innerHTML =
      '<input type="hidden" name="items['+idx+'][variant_id]" value="'+product.id+'">' +
      '<div style="flex:1"><div style="font-weight:600;font-size:0.88rem">'+escapeHtml(product.name)+'</div>' +
      '<div style="font-size:0.75rem;color:#64748b"><span class="sku-badge">'+escapeHtml(product.sku)+'</span> '+escapeHtml(product.size !== 'Standard' ? ' — '+product.size : '')+'</div>' +
      '<div style="font-size:0.73rem;color:#94a3b8">Current stock: '+product.current_stock+'</div></div>' +
      '<div><label style="font-size:0.75rem;color:#64748b;display:block;margin-bottom:2px">Add Qty</label>' +
      '<input type="number" id="sqi-'+idx+'" name="items['+idx+'][qty]" class="form-control qty-input" min="1" value="1" style="width:80px"></div>' +
      '<button type="button" class="remove-row-btn" onclick="removeStockRow('+idx+',this.parentElement)"><i class="fas fa-times"></i></button>';
    container.appendChild(row);
    document.getElementById('productSearchInput').value = '';
    showToast(product.name+' added','success');
  });
});

function removeStockRow(idx, el) {
  stockRows = stockRows.filter(function(r){return r.idx!==idx;});
  if (el) el.remove();
}
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
