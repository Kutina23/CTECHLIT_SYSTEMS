<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Orders
 * View and manage orders
 */

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();
$orderStats = portalSummaryStats();
$orders = portalOrders();
$cancelledOrders = (int) (Database::fetchColumn("SELECT COUNT(*) FROM transactions WHERE status IN ('cancelled', 'failed', 'refunded')") ?? 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders — CTECHLIT & SYSTEMS</title>
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
      <!-- Order Stats -->
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-shopping-cart"></i>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format((int) ($orderStats['total_orders'] ?? 0)); ?></div>
          <div class="kpi-label">Total Orders</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-check-circle"></i>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format((int) ($orderStats['completed_orders'] ?? 0)); ?></div>
          <div class="kpi-label">Completed</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-clock"></i>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format((int) ($orderStats['pending_orders'] ?? 0)); ?></div>
          <div class="kpi-label">Pending</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--red); --kpi-bg: rgba(239, 68, 68, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-times-circle"></i>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($cancelledOrders); ?></div>
          <div class="kpi-label">Cancelled</div>
        </div>
      </div>

      <!-- Orders Table -->
      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">Recent Orders</div>
            <div class="card-subtitle">Latest 10 orders</div>
          </div>
          <button class="btn btn-primary" id="addOrderBtn" style="padding: 10px 16px; font-size: 13px;">
            <i class="fas fa-plus"></i> New Order
          </button>
        </div>

        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table" id="ordersTable">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($orders)): ?>
                <tr>
                  <td colspan="7" style="text-align:center; color: var(--ink-secondary);">No orders found in the database.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($orders as $order): ?>
                  <?php
                    $orderId = $order['transaction_id'] ?? 0;
                    $status = strtolower((string) ($order['status'] ?? 'pending'));
                    $statusLabel = ucfirst(str_replace('_', ' ', $status));
                    $badgeClass = 'warning';
                    if ($status === 'completed') {
                        $badgeClass = 'success';
                    } elseif (in_array($status, ['cancelled', 'failed', 'refunded'], true)) {
                        $badgeClass = 'error';
                    }
                    $orderJson = json_encode([
                        'transaction_id' => $orderId,
                        'receipt_number' => $order['order_number'] ?? 'TXN-' . $orderId,
                        'customer_name' => $order['customer_name'] ?? 'Walk-in Customer',
                        'items_count' => (int) ($order['item_count'] ?? 0),
                        'total_amount' => number_format((float) ($order['total_amount'] ?? 0), 2, '.', ''),
                        'created_at' => $order['created_at'] ?? null,
                        'status' => $status,
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                  ?>
                  <tr data-id="<?php echo (int) $orderId; ?>" data-order='<?php echo htmlspecialchars($orderJson, ENT_QUOTES, 'UTF-8'); ?>'>
                    <td style="font-weight: 600; color: var(--violet);">#<?php echo htmlspecialchars($order['order_number'] ?? 'TXN-' . $orderId); ?></td>
                    <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer'); ?></td>
                    <td><?php echo (int) ($order['item_count'] ?? 0); ?></td>
                    <td style="font-weight: 600; color: var(--green);">GHS <?php echo number_format((float) ($order['total_amount'] ?? 0), 2); ?></td>
                    <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($order['created_at'] ?? 'now'))); ?></td>
                    <td><span class="status-badge <?php echo $badgeClass; ?>"><span class="status-dot"></span><?php echo htmlspecialchars($statusLabel); ?></span></td>
                    <td>
                      <div class="action-buttons" style="display: flex; gap: 6px;">
                        <button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button>
                        <button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Floating Action Button -->
<button class="fab" id="fabAddOrder" aria-label="New order">
  <i class="fas fa-plus"></i>
</button>

<!-- Include Modals -->
<?php include_once __DIR__ . '/../includes/modals.php'; ?>

<script src="../assets/js/modal-controller.js"></script>
<script>
  // Initialize Modal Configs
  window.MODAL_CONFIGS = {
    order: {
      title: 'Order',
      fields: [
        {name: 'user_id', label: 'Customer', type: 'select', required: true, options: [], ajax: 'api/users.php?list=1'},
        {name: 'items', label: 'Items (JSON)', type: 'textarea', required: true, placeholder: '[{"service_id":1,"quantity":2,"unit_price":2.00}]'},
        {name: 'status', label: 'Status', type: 'select', required: true, options: [
          {value: 'pending', label: 'Pending'},
          {value: 'completed', label: 'Completed'},
          {value: 'failed', label: 'Failed'},
          {value: 'refunded', label: 'Refunded'},
          {value: 'partially_refunded', label: 'Partially Refunded'},
        ]},
        {name: 'payment_method', label: 'Payment Method', type: 'select', required: true, options: [
          {value: 'cash', label: 'Cash'},
          {value: 'card', label: 'Card'},
          {value: 'mobile_money', label: 'Mobile Money'},
          {value: 'wallet', label: 'Wallet'},
          {value: 'voucher', label: 'Voucher'},
        ]},
        {name: 'notes', label: 'Notes', type: 'textarea', required: false, placeholder: 'Additional notes'},
      ],
      api: 'api/orders.php',
      table: 'transactions',
      idField: 'transaction_id',
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
    const table = document.getElementById('ordersTable');
    const addBtn = document.getElementById('addOrderBtn');
    const fabBtn = document.getElementById('fabAddOrder');

    function openAddModal() {
      ModalController.open('order', 'create');
    }

    addBtn.addEventListener('click', openAddModal);
    fabBtn.addEventListener('click', openAddModal);

    table.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;

      const row = btn.closest('tr');
      const id = row.dataset.id;
      const orderData = JSON.parse(row.dataset.order);
      const action = btn.dataset.action;

      switch (action) {
        case 'view':
          ModalController.openView('order', orderData);
          break;
        case 'edit':
          ModalController.open('order', 'edit', orderData);
          break;
      }
    });

    window.refreshTable = function() {
      location.reload();
    };

    ModalController.setCallback('onSave', (data, mode) => {
      console.log('Order saved:', data, mode);
    });
  });
</script>
</body>
</html>