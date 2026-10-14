<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Products
 * View and manage products
 */

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Products — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <!-- Main Content -->
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <!-- Header -->
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <!-- Dashboard Content -->
    <div class="portal-content">
      <!-- Product Stats -->
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-box"></i>
            </div>
          </div>
          <div class="kpi-value">48</div>
          <div class="kpi-label">Total Products</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-check"></i>
            </div>
          </div>
          <div class="kpi-value">42</div>
          <div class="kpi-label">In Stock</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
          </div>
          <div class="kpi-value">4</div>
          <div class="kpi-label">Low Stock</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--red); --kpi-bg: rgba(239, 68, 68, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-times"></i>
            </div>
          </div>
          <div class="kpi-value">2</div>
          <div class="kpi-label">Out of Stock</div>
        </div>
      </div>

      <!-- Products Grid -->
      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">All Products</div>
            <div class="card-subtitle">48 total products</div>
          </div>
          <button class="btn btn-primary" id="addProductBtn" style="padding: 10px 16px; font-size: 13px;">
            <i class="fas fa-plus"></i> Add Product
          </button>
        </div>

        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table" id="productsTable">
            <thead>
              <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr data-id="1" data-product='{"product_id":1,"name":"Gaming PC","category":"Electronics","selling_price":"200.00","stock_quantity":12,"is_active":1}'>
                <td>
                  <div class="table-product">
                    <div class="table-product-img" style="background: rgba(124, 58, 237, 0.1); color: var(--violet);">
                      <i class="fas fa-laptop"></i>
                    </div>
                    <div class="table-product-info">
                      <span class="table-product-name">Gaming PC</span>
                      <span class="table-product-category">Electronics</span>
                    </div>
                  </div>
                </td>
                <td>Electronics</td>
                <td style="font-weight: 600; color: var(--ink);">GHS 200.00</td>
                <td>12</td>
                <td><span class="status-badge success"><span class="status-dot"></span>In Stock</span></td>
                <td>
                  <div class="action-buttons" style="display: flex; gap: 6px;">
                    <button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button>
                    <button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button>
                    <button class="header-btn delete-btn" title="Delete" data-action="delete" style="color: var(--red);"><i class="fas fa-trash"></i></button>
                  </div>
                </td>
              </tr>
              <tr data-id="2" data-product='{"product_id":2,"name":"Print Service","category":"Services","selling_price":"0.50","stock_quantity":0,"is_active":1}'>
                <td>
                  <div class="table-product">
                    <div class="table-product-img" style="background: rgba(6, 182, 212, 0.1); color: var(--cyan);">
                      <i class="fas fa-print"></i>
                    </div>
                    <div class="table-product-info">
                      <span class="table-product-name">Print Service</span>
                      <span class="table-product-category">Services</span>
                    </div>
                  </div>
                </td>
                <td>Services</td>
                <td style="font-weight: 600; color: var(--ink);">GHS 0.50/page</td>
                <td>—</td>
                <td><span class="status-badge success"><span class="status-dot"></span>Available</span></td>
                <td>
                  <div class="action-buttons" style="display: flex; gap: 6px;">
                    <button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button>
                    <button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button>
                    <button class="header-btn delete-btn" title="Delete" data-action="delete" style="color: var(--red);"><i class="fas fa-trash"></i></button>
                  </div>
                </td>
              </tr>
              <tr data-id="3" data-product='{"product_id":3,"name":"Internet Hour","category":"Access","selling_price":"15.00","stock_quantity":0,"is_active":1}'>
                <td>
                  <div class="table-product">
                    <div class="table-product-img" style="background: rgba(16, 185, 129, 0.1); color: var(--green);">
                      <i class="fas fa-wifi"></i>
                    </div>
                    <div class="table-product-info">
                      <span class="table-product-name">Internet Hour</span>
                      <span class="table-product-category">Access</span>
                    </div>
                  </div>
                </td>
                <td>Access</td>
                <td style="font-weight: 600; color: var(--ink);">GHS 15.00</td>
                <td>—</td>
                <td><span class="status-badge success"><span class="status-dot"></span>Available</span></td>
                <td>
                  <div class="action-buttons" style="display: flex; gap: 6px;">
                    <button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button>
                    <button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button>
                    <button class="header-btn delete-btn" title="Delete" data-action="delete" style="color: var(--red);"><i class="fas fa-trash"></i></button>
                  </div>
                </td>
              </tr>
              <tr data-id="4" data-product='{"product_id":4,"name":"Coffee","category":"F&B","selling_price":"8.00","stock_quantity":3,"is_active":1}'>
                <td>
                  <div class="table-product">
                    <div class="table-product-img" style="background: rgba(245, 158, 11, 0.1); color: var(--amber);">
                      <i class="fas fa-coffee"></i>
                    </div>
                    <div class="table-product-info">
                      <span class="table-product-name">Coffee</span>
                      <span class="table-product-category">F&B</span>
                    </div>
                  </div>
                </td>
                <td>F&B</td>
                <td style="font-weight: 600; color: var(--ink);">GHS 8.00</td>
                <td>3</td>
                <td><span class="status-badge warning"><span class="status-dot"></span>Low Stock</span></td>
                <td>
                  <div class="action-buttons" style="display: flex; gap: 6px;">
                    <button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button>
                    <button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button>
                    <button class="header-btn delete-btn" title="Delete" data-action="delete" style="color: var(--red);"><i class="fas fa-trash"></i></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Floating Action Button -->
<button class="fab" id="fabAddProduct" aria-label="Add product">
  <i class="fas fa-plus"></i>
</button>

<!-- Include Modals -->
<?php include_once __DIR__ . '/../includes/modals.php'; ?>

<script src="../assets/js/modal-controller.js"></script>
<script>
  // Initialize Modal Configs
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

  // Sidebar Toggle
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar.classList.add('open');
    sidebarOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      closeSidebar();
    }
  });

  // Table action handlers
  document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('productsTable');
    const addBtn = document.getElementById('addProductBtn');
    const fabBtn = document.getElementById('fabAddProduct');

    function openAddModal() {
      ModalController.open('product', 'create');
    }

    addBtn.addEventListener('click', openAddModal);
    fabBtn.addEventListener('click', openAddModal);

    table.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;

      const row = btn.closest('tr');
      const id = row.dataset.id;
      const productData = JSON.parse(row.dataset.product);
      const action = btn.dataset.action;

      switch (action) {
        case 'view':
          ModalController.openView('product', productData);
          break;
        case 'edit':
          ModalController.open('product', 'edit', productData);
          break;
        case 'delete':
          ModalController.openConfirm({
            title: 'Delete Product',
            message: `Are you sure you want to delete "${productData.name}"?`,
            detail: 'This action cannot be undone.',
            icon: 'danger',
            confirmText: 'Delete',
            confirmClass: 'btn-danger',
            onConfirm: () => deleteProduct(id),
          });
          break;
      }
    });

    async function deleteProduct(id) {
      try {
        const response = await fetch('api/products.php', {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({product_id: id}),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Failed to delete');
        ModalController.showNotification('Product deleted successfully', 'success');
        refreshTable();
      } catch (error) {
        ModalController.showNotification(error.message, 'error');
      }
    }

    window.refreshTable = function() {
      location.reload();
    };

    ModalController.setCallback('onSave', (data, mode) => {
      console.log('Product saved:', data, mode);
    });
  });
</script>
</body>
</html>