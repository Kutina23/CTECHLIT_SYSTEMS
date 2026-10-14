<?php
/**
 * CTECHLIT & SYSTEMS — Inventory
 * Inventory product catalog
 */

require_once __DIR__ . '/../includes/auth.php';
requirePermission('inventory.view');

$canManageProducts = hasPermission('inventory.manage');

$products = portalInventory();
$totalItems = (int) Database::fetchColumn("SELECT COALESCE(SUM(stock_quantity), 0) FROM products WHERE is_active = 1");
$lowStock = (int) Database::fetchColumn("SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_level AND stock_quantity > 0 AND is_active = 1");
$outOfStock = (int) Database::fetchColumn("SELECT COUNT(*) FROM products WHERE stock_quantity <= 0 AND is_active = 1");
$inventoryValue = (float) Database::fetchColumn("SELECT COALESCE(SUM(stock_quantity * cost_price), 0) FROM products WHERE is_active = 1");
$movementsToday = Database::tableExists('inventory_movements')
    ? (int) Database::fetchColumn("SELECT COUNT(*) FROM inventory_movements WHERE DATE(created_at) = CURDATE()")
    : 0;

/**
 * Stock badge class for a product row.
 */
function inventoryStockClass(string $status): string
{
    switch ($status) {
        case 'Out of Stock':
            return 'danger';
        case 'Low Stock':
            return 'warning';
        default:
            return 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>All Products — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content">
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-boxes-stacked"></i></div></div>
          <div class="kpi-value"><?php echo number_format($totalItems); ?></div>
          <div class="kpi-label">Items in Stock</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div></div>
          <div class="kpi-value"><?php echo number_format($lowStock + $outOfStock); ?></div>
          <div class="kpi-label">Need Restock (<?php echo (int) $outOfStock; ?> empty)</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-dollar-sign"></i></div></div>
          <div class="kpi-value">GHS <?php echo number_format($inventoryValue, 2); ?></div>
          <div class="kpi-label">Inventory Value</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-sliders"></i></div></div>
          <div class="kpi-value"><?php echo number_format($movementsToday); ?></div>
          <div class="kpi-label">Adjustments Today</div>
        </div>
      </div>

      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">All Products</div>
            <div class="card-subtitle">Product catalog and current stock levels</div>
          </div>
          <div style="display: flex; gap: 10px; align-items: center;">
            <input type="search" id="inventoryFilter" class="form-input" placeholder="Filter products" style="width: 220px; padding: 9px 14px;">
            <?php if ($canManageProducts): ?>
              <button class="btn btn-primary" id="addInventoryBtn" style="padding: 10px 16px; font-size: 13px;">
                <i class="fas fa-plus"></i> Add Item
              </button>
            <?php endif; ?>
          </div>
        </div>

        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table" id="inventoryTable">
            <thead>
              <tr>
                <th>Item</th>
                <th>Category</th>
                <th>Qty</th>
                <th>Reorder Level</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $product): ?>
              <?php
                  $productJson = json_encode($product + [
                      'unit_price' => number_format((float) ($product['selling_price'] ?? 0), 2, '.', ''),
                  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
              ?>
              <tr data-id="<?php echo (int) $product['product_id']; ?>" data-inventory='<?php echo htmlspecialchars($productJson, ENT_QUOTES, 'UTF-8'); ?>'>
                <td><?php echo sanitize($product['name']); ?></td>
                <td><?php echo sanitize(ucfirst($product['category'])); ?></td>
                <td style="font-weight: 600;"><?php echo number_format((int) $product['stock_quantity']); ?></td>
                <td><?php echo number_format((int) $product['min_stock_level']); ?></td>
                <td>
                  <span class="status-badge <?php echo inventoryStockClass((string) $product['stock_status']); ?>">
                    <span class="status-dot"></span><?php echo sanitize($product['stock_status']); ?>
                  </span>
                </td>
                <td>
                  <div class="action-buttons" style="display: flex; gap: 6px;">
                    <button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button>
                    <?php if ($canManageProducts): ?>
                      <button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button>
                      <button class="header-btn delete-btn" title="Delete" data-action="delete" style="color: var(--red);"><i class="fas fa-trash"></i></button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($products)): ?>
              <tr>
                <td colspan="6" style="text-align: center; padding: 24px; color: var(--ink-secondary);">No products found</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<?php include_once __DIR__ . '/../includes/modals.php'; ?>
<script src="../assets/js/modal-controller.js"></script>
<script>
  window.MODAL_CONFIGS = {
    product: {
      title: 'Product',
      fields: [
        {name: 'name', label: 'Product Name', type: 'text', required: true, placeholder: 'Enter product name'},
        {name: 'category', label: 'Category', type: 'select', required: true, options: [
          {value: 'beverage', label: 'Beverage'},
          {value: 'snack', label: 'Snack'},
          {value: 'merchandise', label: 'Merchandise'},
          {value: 'accessory', label: 'Accessory'},
          {value: 'other', label: 'Other'},
        ]},
        {name: 'sku', label: 'SKU', type: 'text', required: false, placeholder: 'Optional SKU'},
        {name: 'barcode', label: 'Barcode', type: 'text', required: false, placeholder: 'Optional barcode'},
        {name: 'description', label: 'Description', type: 'textarea', required: false, placeholder: 'Product description'},
        {name: 'cost_price', label: 'Cost Price', type: 'number', required: true, step: '0.01', min: '0', placeholder: '0.00'},
        {name: 'selling_price', label: 'Selling Price', type: 'number', required: true, step: '0.01', min: '0', placeholder: '0.00'},
        {name: 'stock_quantity', label: 'Stock Quantity', type: 'number', required: true, min: '0', placeholder: '0'},
        {name: 'min_stock_level', label: 'Min Stock Level', type: 'number', required: true, min: '0', placeholder: '5'},
        {name: 'is_active', label: 'Active', type: 'checkbox', required: false},
      ],
      api: 'api/products.php',
      table: 'products',
      idField: 'product_id',
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('inventoryTable');
    const addBtn = document.getElementById('addInventoryBtn');
    const filter = document.getElementById('inventoryFilter');

    if (addBtn) {
      addBtn.addEventListener('click', () => ModalController.open('product', 'create'));
    }

    filter.addEventListener('input', function () {
      const term = this.value.trim().toLowerCase();
      table.querySelectorAll('tbody tr[data-id]').forEach((row) => {
        row.style.display = !term || row.textContent.toLowerCase().includes(term) ? '' : 'none';
      });
    });

    table.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-action]');
      if (!button) return;

      const row = button.closest('tr');
      const id = row.dataset.id;
      const product = parseRowData(row);
      if (!product) return;

      switch (button.dataset.action) {
        case 'view':
          showProductDetails(id, product);
          break;
        case 'edit':
          ModalController.open('product', 'edit', product);
          break;
        case 'delete':
          ModalController.openConfirm({
            title: 'Delete Item',
            message: `Delete "${product.name}"?`,
            detail: 'This removes the product and its movement history. This action cannot be undone.',
            icon: 'danger',
            confirmText: 'Delete',
            confirmClass: 'btn-danger',
            onConfirm: () => deleteItem(id)
          });
          break;
      }
    });
  });

  function parseRowData(row) {
    try {
      return JSON.parse(row.dataset.inventory);
    } catch (error) {
      console.error('Unable to read product data', error);
      return null;
    }
  }

  async function showProductDetails(productId, product) {
    const overlay = document.getElementById('viewModalOverlay');
    const body = document.getElementById('viewModalBody');
    const modalTitle = document.getElementById('viewModalTitle');

    let fullProduct = {...product};
    try {
      const response = await fetch(`api/products.php?product_id=${encodeURIComponent(productId)}`, {
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const result = await response.json();
      if (!response.ok || !result?.success || !result.data || typeof result.data !== 'object') {
        throw new Error(result?.message || 'The complete product record could not be loaded.');
      }
      fullProduct = {...product, ...result.data};
    } catch (error) {
      console.error('Unable to load full product details', error);
      ModalController.showNotification(`Showing available list data only. ${error.message}`, 'warning');
    }

    let movements = [];
    let movementError = null;
    try {
      const response = await fetch(`api/products.php?movements=1&product_id=${encodeURIComponent(productId)}`, {
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const result = await response.json();
      if (!response.ok || !result?.success || !Array.isArray(result.data)) {
        throw new Error(result?.message || 'Stock movement history could not be loaded.');
      }
      movements = result.data;
    } catch (error) {
      console.error('Unable to load stock movements', error);
      movementError = error;
      ModalController.showNotification(`Stock movement history could not be loaded. ${error.message}`, 'warning');
    }

    const rows = movements.length
      ? movements.map((movement) => `
          <tr>
            <td>${escapeHtml(capitalise(movement.movement_type))}</td>
            <td style="font-weight:600; color: ${Number(movement.quantity) >= 0 ? 'var(--green)' : 'var(--red)'};">
              ${Number(movement.quantity) >= 0 ? '+' : ''}${Number(movement.quantity)}
            </td>
            <td>${escapeHtml(movement.notes || '—')}</td>
            <td>${escapeHtml(movement.created_by_name || 'System')}</td>
            <td>${escapeHtml(movement.created_at || '—')}</td>
          </tr>`).join('')
      : `<tr><td colspan="5" style="text-align:center; color: var(--ink-secondary);">${movementError ? 'Movement history unavailable' : 'No stock movements recorded yet'}</td></tr>`;

    modalTitle.textContent = fullProduct.name || 'Product details';
    body.innerHTML = `
      <div style="display: grid; gap: 16px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
          ${infoTile('In Stock', Number(fullProduct.stock_quantity || 0))}
          ${infoTile('Reorder Level', Number(fullProduct.min_stock_level || 0))}
          ${infoTile('Status', fullProduct.stock_status || '—')}
          ${infoTile('Category', capitalise(fullProduct.category || '—'))}
        </div>
        <section class="record-detail-section">
          <h4>Complete product details</h4>
          ${ModalController.renderDetails('product', fullProduct)}
        </section>
        <div>
          <div class="form-label" style="margin-bottom: 8px;">Movement history</div>
          <table class="data-table" style="width: 100%;">
            <thead><tr><th>Type</th><th>Change</th><th>Reason</th><th>By</th><th>When</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>`;
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function infoTile(label, value) {
    return `
      <div style="padding: 12px 14px; background: var(--bg-secondary); border-radius: var(--radius);">
        <div style="font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink-secondary);">${escapeHtml(label)}</div>
        <div style="font-size: 17px; font-weight: 700; color: var(--ink); margin-top: 4px;">${escapeHtml(value)}</div>
      </div>`;
  }

  function escapeHtml(value) {
    const element = document.createElement('span');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
  }

  function capitalise(value) {
    if (!value) return '';
    return String(value).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }

  async function deleteItem(id) {
    try {
      const res = await fetch('api/products.php', {
        method: 'DELETE',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({product_id: id})
      });
      const result = await res.json();
      if (!res.ok || !result.success) throw new Error(result.message || 'Failed to delete');
      ModalController.showNotification('Item deleted successfully', 'success');
      setTimeout(() => window.location.reload(), 700);
    } catch (error) {
      ModalController.showNotification(error.message, 'error');
    }
  }
</script>
</body>
</html>
