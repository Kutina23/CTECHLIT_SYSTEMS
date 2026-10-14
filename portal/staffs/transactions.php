<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Staff Transactions
 * Staff member's transaction history page
 */

require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();
$transactions = portalStaffTransactions();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Transactions — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
  <!-- Main Content -->
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <!-- Header -->
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>

    <!-- Dashboard Content -->
    <div class="portal-content">
      <!-- Transaction Stats -->
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-wallet"></i>
            </div>
          </div>
          <div class="kpi-value">GHS <?php echo number_format(array_sum(array_map(fn($row) => (float) ($row['total_amount'] ?? 0), $transactions)), 2); ?></div>
          <div class="kpi-label">Total Earnings</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-check-circle"></i>
            </div>
          </div>
          <div class="kpi-value">GHS <?php echo number_format(array_sum(array_map(fn($row) => (float) (($row['status'] ?? '') === 'completed' ? ($row['total_amount'] ?? 0) : 0), $transactions)), 2); ?></div>
          <div class="kpi-label">Paid Out</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-clock"></i>
            </div>
          </div>
          <div class="kpi-value">GHS <?php echo number_format(array_sum(array_map(fn($row) => (float) (($row['status'] ?? '') === 'pending' ? ($row['total_amount'] ?? 0) : 0), $transactions)), 2); ?></div>
          <div class="kpi-label">Pending</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-receipt"></i>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format(count($transactions)); ?></div>
          <div class="kpi-label">Transactions</div>
        </div>
      </div>

      <!-- Transactions Table -->
      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">Transaction History</div>
            <div class="card-subtitle">Your recent transactions</div>
          </div>
          <div style="display: flex; gap: 8px;">
            <select class="header-filter" style="padding: 6px 12px; font-size: 13px;">
              <option>All Types</option>
              <option>Commission</option>
              <option>Bonus</option>
              <option>Deduction</option>
            </select>
            <input type="date" class="header-filter" style="padding: 6px 12px; font-size: 13px;">
          </div>
        </div>

        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Transaction ID</th>
                <th>Type</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($transactions as $transaction): ?>
              <tr>
                <td style="font-weight: 600; color: var(--ink); font-size: 14px;"><?php echo sanitize($transaction['transaction_id'] ? '#TXN-' . $transaction['transaction_id'] : 'N/A'); ?></td>
                <td><span class="badge <?php echo ($transaction['status'] ?? '') === 'pending' ? 'badge-warning' : 'badge-success'; ?>"><?php echo sanitize(ucfirst($transaction['type_name'] ?? 'Transaction')); ?></span></td>
                <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize($transaction['customer_name'] ?? 'Customer'); ?></td>
                <td style="font-weight: 600; color: var(--green);"><?php echo (float) ($transaction['total_amount'] ?? 0) >= 0 ? '+' : '-'; ?>GHS <?php echo number_format(abs((float) ($transaction['total_amount'] ?? 0)), 2); ?></td>
                <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize(date('Y-m-d', strtotime($transaction['created_at']))); ?></td>
                <td><span class="badge <?php echo ($transaction['status'] ?? '') === 'pending' ? 'badge-warning' : 'badge-success'; ?>"><?php echo sanitize(ucfirst($transaction['status'] ?? 'completed')); ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Floating Action Button -->
<button class="fab" aria-label="Export transactions">
  <i class="fas fa-download"></i>
</button>

<script>
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
</script>
</body>
</html>





