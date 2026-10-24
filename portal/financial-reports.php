<?php
/**
 * CTECHLIT & SYSTEMS — Financial Reports
 */

require_once __DIR__ . '/../includes/auth.php';
requirePermission('transactions.reports');

$totals = portalRefundTotals();
$refundsEnabled = refundsTableExists();
$refundJoin = $refundsEnabled
    ? "LEFT JOIN (
           SELECT transaction_id, SUM(amount) AS refunded
           FROM transaction_refunds
           GROUP BY transaction_id
       ) refunds ON refunds.transaction_id = t.transaction_id"
    : '';
$refundedSelect = $refundsEnabled ? 'COALESCE(refunds.refunded, 0)' : '0';
$monthlyRows = Database::fetchAll(
    "SELECT DATE_FORMAT(t.created_at, '%Y-%m') AS month_key,
            DATE_FORMAT(t.created_at, '%b %Y') AS month_label,
            COUNT(*) AS transaction_count,
            SUM(t.total_amount) AS gross_revenue,
            SUM({$refundedSelect}) AS refunded_amount,
            SUM(t.total_amount - {$refundedSelect}) AS net_revenue
     FROM transactions t
     {$refundJoin}
     WHERE t.status IN ('completed', 'partially_refunded', 'refunded')
       AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY YEAR(t.created_at), MONTH(t.created_at), DATE_FORMAT(t.created_at, '%Y-%m'), DATE_FORMAT(t.created_at, '%b %Y')
     ORDER BY month_key"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Financial Reports — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    <div class="portal-content">
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-money-bill-wave"></i></div></div>
          <div class="kpi-value">GHS <?php echo number_format($totals['net_revenue'], 2); ?></div>
          <div class="kpi-label">Net Revenue</div>
        </div>
        <div class="kpi-card" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1);">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-receipt"></i></div></div>
          <div class="kpi-value">GHS <?php echo number_format($totals['gross_revenue'], 2); ?></div>
          <div class="kpi-label">Gross Revenue</div>
        </div>
        <div class="kpi-card" style="--kpi-color: var(--red); --kpi-bg: rgba(239, 68, 68, 0.1);">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-undo"></i></div></div>
          <div class="kpi-value">GHS <?php echo number_format($totals['refunded_total'], 2); ?></div>
          <div class="kpi-label">Total Refunds (<?php echo (int) $totals['refund_count']; ?>)</div>
        </div>
        <div class="kpi-card" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1);">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-clock"></i></div></div>
          <div class="kpi-value">GHS <?php echo number_format($totals['pending_amount'], 2); ?></div>
          <div class="kpi-label">Pending</div>
        </div>
      </div>

      <div class="card glass-card" style="margin-bottom:24px;">
        <div class="card-header">
          <div>
            <div class="card-title">Monthly Revenue</div>
            <div class="card-subtitle">Gross revenue, refunds, and net revenue for the last six months</div>
          </div>
        </div>
        <div class="chart-container"><canvas id="financialRevenueChart"></canvas></div>
      </div>

      <div class="card glass-card">
        <div class="card-header">
          <div>
            <div class="card-title">Monthly Breakdown</div>
            <div class="card-subtitle">Completed, partially refunded, and refunded transactions</div>
          </div>
        </div>
        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table">
            <thead><tr><th>Month</th><th>Transactions</th><th>Gross Revenue</th><th>Refunds</th><th>Net Revenue</th></tr></thead>
            <tbody>
              <?php foreach ($monthlyRows as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['month_label']); ?></td>
                  <td><?php echo number_format((int) $row['transaction_count']); ?></td>
                  <td>GHS <?php echo number_format((float) $row['gross_revenue'], 2); ?></td>
                  <td style="color:var(--red);">−GHS <?php echo number_format((float) $row['refunded_amount'], 2); ?></td>
                  <td style="font-weight:600;">GHS <?php echo number_format((float) $row['net_revenue'], 2); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($monthlyRows)): ?>
                <tr><td colspan="5" style="text-align:center; padding:24px; color:var(--ink-secondary);">No completed transactions in this period</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
  <script>
    const monthlyReport = <?php echo json_encode([
        'labels' => array_column($monthlyRows, 'month_label'),
        'gross' => array_map('floatval', array_column($monthlyRows, 'gross_revenue')),
        'refunds' => array_map('floatval', array_column($monthlyRows, 'refunded_amount')),
        'net' => array_map('floatval', array_column($monthlyRows, 'net_revenue')),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    new Chart(document.getElementById('financialRevenueChart'), {
      type: 'bar',
      data: {
        labels: monthlyReport.labels,
        datasets: [
          {label: 'Gross Revenue', data: monthlyReport.gross, backgroundColor: 'rgba(16, 185, 129, 0.75)'},
          {label: 'Refunds', data: monthlyReport.refunds, backgroundColor: 'rgba(239, 68, 68, 0.75)'},
          {label: 'Net Revenue', data: monthlyReport.net, backgroundColor: 'rgba(124, 58, 237, 0.8)'}
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {y: {beginAtZero: true}}
      }
    });
  </script>
</body>
</html>
