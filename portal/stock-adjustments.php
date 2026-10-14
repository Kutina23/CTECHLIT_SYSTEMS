<?php
/**
 * CTECHLIT & SYSTEMS — Stock Adjustments
 */

require_once __DIR__ . '/../includes/auth.php';
requirePermission('inventory.adjust');

$products = Database::fetchAll(
    "SELECT product_id, name, stock_quantity, min_stock_level
     FROM products
     WHERE is_active = 1
     ORDER BY name"
);
$selectedProductId = max(0, (int) ($_GET['product_id'] ?? 0));
$movements = Database::tableExists('inventory_movements')
    ? Database::fetchAll(
        "SELECT im.movement_id, im.movement_type, im.quantity, im.notes, im.created_at,
                p.name AS product_name, s.full_name AS created_by_name
         FROM inventory_movements im
         JOIN products p ON p.product_id = im.product_id
         LEFT JOIN staff s ON s.staff_id = im.created_by
         ORDER BY im.created_at DESC, im.movement_id DESC
         LIMIT 100"
    )
    : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stock Adjustments — CTECHLIT & SYSTEMS</title>
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
      <div class="card glass-card animate-fade-in" style="margin-bottom:24px;">
        <div class="card-header">
          <div>
            <div class="card-title">Adjust Stock</div>
            <div class="card-subtitle">Record deliveries, stock corrections, sales, and write-offs</div>
          </div>
          <button class="btn btn-primary" type="button" id="newAdjustmentButton"><i class="fas fa-plus"></i> New Adjustment</button>
        </div>
      </div>

      <div class="card glass-card animate-fade-in">
        <div class="card-header">
          <div>
            <div class="card-title">Stock Movement History</div>
            <div class="card-subtitle">Latest 100 restocks, corrections, sales, transfers, and write-offs</div>
          </div>
        </div>
        <div class="table-container" style="border:none; background:transparent;">
          <table class="data-table">
            <thead><tr><th>Item</th><th>Type</th><th>Change</th><th>Reason</th><th>By</th><th>When</th></tr></thead>
            <tbody>
              <?php foreach ($movements as $movement): ?>
                <tr>
                  <td><?php echo htmlspecialchars($movement['product_name']); ?></td>
                  <td><span class="status-badge info"><span class="status-dot"></span><?php echo htmlspecialchars(ucfirst($movement['movement_type'])); ?></span></td>
                  <td style="font-weight:600; color:<?php echo (int) $movement['quantity'] >= 0 ? 'var(--green)' : 'var(--red)'; ?>;">
                    <?php echo (int) $movement['quantity'] >= 0 ? '+' : ''; ?><?php echo (int) $movement['quantity']; ?>
                  </td>
                  <td style="font-size:13px; color:var(--ink-secondary);"><?php echo htmlspecialchars($movement['notes'] ?? '—'); ?></td>
                  <td><?php echo htmlspecialchars($movement['created_by_name'] ?? 'System'); ?></td>
                  <td style="font-size:13px; color:var(--ink-secondary);"><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($movement['created_at']))); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($movements)) : ?>
                <tr><td colspan="6" style="text-align:center; padding:24px; color:var(--ink-secondary);">No stock movements recorded yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script>
    window.MODAL_CONFIGS = {
      stock_adjustment: {
        title: 'Adjust Stock',
        fields: [
          {name: 'product_id', label: 'Product', type: 'select', required: true, options: <?php echo json_encode(array_map(function ($product) {
            return [
              'value' => (int) $product['product_id'],
              'label' => $product['name'] . ' (' . (int) $product['stock_quantity'] . ' in stock)',
              'data_stock' => (int) $product['stock_quantity'],
              'data_reorder' => (int) $product['min_stock_level'],
            ];
          }, $products)); ?>},
          {name: 'direction', label: 'Adjustment', type: 'select', required: true, options: [
            {value: 'in', label: 'Add stock'},
            {value: 'out', label: 'Remove stock'},
            {value: 'set', label: 'Set counted level'},
          ]},
          {name: 'movement_type', label: 'Movement type', type: 'select', required: true, options: [
            {value: 'purchase', label: 'Purchase / delivery received'},
            {value: 'transfer', label: 'Transfer'},
            {value: 'sale', label: 'Sale from counter'},
            {value: 'waste', label: 'Waste / expired'},
            {value: 'adjustment', label: 'Stock correction'},
          ]},
          {name: 'quantity', label: 'Quantity to add', type: 'number', required: true, min: '1', step: '1', value: '1'},
          {name: 'notes', label: 'Reason / reference', type: 'textarea', required: false, placeholder: 'Supplier, invoice, or reason for adjustment'},
        ],
        api: 'api/products.php',
        table: 'products',
        idField: 'product_id',
      }
    };

    window.refreshTable = () => location.reload();

    ModalController.setCallback('beforeSubmit', async (data, mode) => {
      const productSelect = document.getElementById('product_id');
      const directionSelect = document.getElementById('direction');
      const movementSelect = document.getElementById('movement_type');
      const quantityInput = document.getElementById('quantity');
      const notesInput = document.getElementById('notes');

      const selected = productSelect.selectedOptions[0];
      const current = Number(selected?.dataset?.stock || 0);
      const value = Number(quantityInput.value || 0);
      const direction = directionSelect.value;
      const quantity = direction === 'in' ? value : direction === 'out' ? -value : value - current;
      const notes = (notesInput?.value || '').trim();

      if (quantity === 0) {
        ModalController.showNotification('The new level is the same as the current level', 'warning');
        return null;
      }
      if (current + quantity < 0) {
        ModalController.showNotification(`Only ${current} units are in stock`, 'error');
        return null;
      }
      if (movementSelect.value === 'adjustment' && !notes) {
        ModalController.showNotification('A reason is required for stock corrections', 'error');
        return null;
      }

      return {...data, action: 'adjust_stock'};
    });

    document.getElementById('newAdjustmentButton').addEventListener('click', () => {
      ModalController.open('stock_adjustment', 'create');
    });

    const movementChoices = {
      in: [['purchase', 'Purchase / delivery received'], ['transfer', 'Transfer']],
      out: [['sale', 'Sale from counter'], ['waste', 'Waste / expired'], ['transfer', 'Transfer']],
      set: [['adjustment', 'Stock correction']],
    };

    function syncStockAdjustmentForm() {
      const productSelect = document.getElementById('product_id');
      const directionSelect = document.getElementById('direction');
      const movementSelect = document.getElementById('movement_type');
      const quantityInput = document.getElementById('quantity');
      const quantityLabel = document.querySelector('label[for="quantity"]');
      const notesInput = document.getElementById('notes');
      const preview = document.getElementById('stockPreview');

      if (!productSelect || !directionSelect || !movementSelect || !quantityInput) return;

      const choices = movementChoices[directionSelect.value] || [];
      movementSelect.replaceChildren(...choices.map(([value, label]) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        return option;
      }));

      if (directionSelect.value === 'set') {
        quantityInput.min = '0';
        quantityInput.value = '0';
        if (quantityLabel) quantityLabel.textContent = 'Counted stock level';
      } else {
        quantityInput.min = '1';
        quantityInput.value = '1';
        if (quantityLabel) quantityLabel.textContent = directionSelect.value === 'in' ? 'Quantity to add' : 'Quantity to remove';
      }

      if (notesInput) {
        notesInput.placeholder = directionSelect.value === 'in'
          ? 'Supplier name or invoice number'
          : directionSelect.value === 'set' ? 'Required: reason for the correction' : 'Reason for removing the stock';
      }

      updateStockPreview();
    }

    function updateStockPreview() {
      const productSelect = document.getElementById('product_id');
      const directionSelect = document.getElementById('direction');
      const quantityInput = document.getElementById('quantity');
      const preview = document.getElementById('stockPreview');

      if (!productSelect || !directionSelect || !quantityInput || !preview) return;

      const selected = productSelect.selectedOptions[0];
      if (!selected || !selected.value) {
        preview.textContent = 'Choose a product to preview the new stock level.';
        preview.style.color = '';
        quantityInput.removeAttribute('max');
        return;
      }

      const current = Number(selected.dataset.stock || 0);
      const quantity = Number(quantityInput.value || 0);
      const next = directionSelect.value === 'in'
        ? current + quantity
        : directionSelect.value === 'out' ? current - quantity : quantity;
      const reorder = Number(selected.dataset.reorder || 0);

      preview.textContent = `Current: ${current} · New level: ${next} · Reorder at: ${reorder}`;
      preview.style.color = next <= reorder ? 'var(--amber)' : 'var(--green)';

      if (directionSelect.value === 'out') {
        quantityInput.max = String(current);
      } else {
        quantityInput.removeAttribute('max');
      }
    }

    document.getElementById('modalBody').addEventListener('change', (event) => {
      if (event.target.id === 'direction' || event.target.id === 'product_id') {
        syncStockAdjustmentForm();
      }
      if (event.target.id === 'quantity') {
        updateStockPreview();
      }
    });

    document.getElementById('modalBody').addEventListener('input', (event) => {
      if (event.target.id === 'quantity') {
        updateStockPreview();
      }
    });

    document.getElementById('modalOverlay').addEventListener('transitionend', (event) => {
      if (event.target.id !== 'modalOverlay') return;
      if (!document.getElementById('modalOverlay').classList.contains('active')) return;
      if (ModalController.getCurrentEntity() !== 'stock_adjustment') return;
      syncStockAdjustmentForm();
    });

    document.getElementById('newAdjustmentButton').addEventListener('click', () => {
      ModalController.open('stock_adjustment', 'create');
    });

    <?php if ($selectedProductId): ?>
    window.addEventListener('DOMContentLoaded', () => {
      ModalController.open('stock_adjustment', 'create');
      setTimeout(() => {
        const productSelect = document.getElementById('product_id');
        if (productSelect) {
          productSelect.value = '<?php echo $selectedProductId; ?>';
          productSelect.dispatchEvent(new Event('change'));
        }
      }, 150);
    });
    <?php endif; ?>
  </script>
</body>
</html>