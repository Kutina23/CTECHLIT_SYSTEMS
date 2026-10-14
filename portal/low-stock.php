<?php
/**
 * CTECHLIT & SYSTEMS — Low Stock Alerts
 */

require_once __DIR__ . '/../includes/auth.php';
requirePermission('inventory.view');

$canAdjustStock = hasPermission('inventory.adjust');
$lowStockProducts = Database::fetchAll(
    "SELECT product_id, name, category, stock_quantity, min_stock_level
     FROM products
     WHERE is_active = 1 AND stock_quantity <= min_stock_level
     ORDER BY stock_quantity ASC, name ASC"
);
$outOfStockCount = count(array_filter(
    $lowStockProducts,
    static fn(array $product): bool => (int) $product['stock_quantity'] <= 0
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Low Stock Alerts — CTECHLIT & SYSTEMS</title>
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
      <div class="kpi-grid" style="margin-bottom:24px;">
        <div class="kpi-card" style="--kpi-color:var(--amber); --kpi-bg:rgba(245,158,11,.1);">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div></div>
          <div class="kpi-value"><?php echo count($lowStockProducts); ?></div>
          <div class="kpi-label">Products to Restock</div>
        </div>
        <div class="kpi-card" style="--kpi-color:var(--red); --kpi-bg:rgba(239,68,68,.1);">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-box-open"></i></div></div>
          <div class="kpi-value"><?php echo $outOfStockCount; ?></div>
          <div class="kpi-label">Out of Stock</div>
        </div>
      </div>

      <div class="card glass-card">
        <div class="card-header">
          <div>
            <div class="card-title">Low Stock Alerts</div>
            <div class="card-subtitle">Active products at or below their reorder level</div>
          </div>
        </div>
        <div class="table-container" style="border:none; background:transparent;">
          <table class="data-table">
            <thead><tr><th>Product</th><th>Category</th><th>In Stock</th><th>Reorder Level</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($lowStockProducts as $product): ?>
                <?php $outOfStock = (int) $product['stock_quantity'] <= 0; ?>
                <tr>
                  <td><?php echo htmlspecialchars($product['name']); ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($product['category'])); ?></td>
                  <td style="font-weight:600; color:<?php echo $outOfStock ? 'var(--red)' : 'var(--amber)'; ?>;"><?php echo number_format((int) $product['stock_quantity']); ?></td>
                  <td><?php echo number_format((int) $product['min_stock_level']); ?></td>
                  <td><span class="status-badge <?php echo $outOfStock ? 'danger' : 'warning'; ?>"><span class="status-dot"></span><?php echo $outOfStock ? 'Out of Stock' : 'Low Stock'; ?></span></td>
                  <td>
                    <?php if ($canAdjustStock): ?>
                      <a class="btn btn-secondary" href="stock-adjustments.php?product_id=<?php echo (int) $product['product_id']; ?>">Adjust Stock</a>
                    <?php else: ?>
                      <span style="color:var(--ink-secondary);">No adjustment access</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($lowStockProducts)): ?>
                <tr><td colspan="6" style="text-align:center; padding:24px; color:var(--ink-secondary);">All active products are above their reorder levels.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
