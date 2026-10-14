<?php
/**
 * CTECHLIT & SYSTEMS — Refund History
 */

require_once __DIR__ . '/../includes/auth.php';
requirePermission('transactions.refund');

$refundsEnabled = refundsTableExists();
$pageSize = 25;
$totalRefunds = $refundsEnabled
    ? (int) Database::fetchColumn("SELECT COUNT(*) FROM transaction_refunds")
    : 0;
$totalPages = max(1, (int) ceil($totalRefunds / $pageSize));
$page = min($totalPages, max(1, (int) ($_GET['page'] ?? 1)));
$offset = ($page - 1) * $pageSize;
$refunds = $refundsEnabled
    ? Database::fetchAll(
        "SELECT r.refund_id, r.transaction_id, r.amount, r.reason, r.notes, r.created_at,
                t.receipt_number, u.full_name AS customer_name, s.full_name AS processed_by_name
         FROM transaction_refunds r
         JOIN transactions t ON t.transaction_id = r.transaction_id
         LEFT JOIN users u ON u.user_id = t.user_id
         LEFT JOIN staff s ON s.staff_id = r.processed_by
         ORDER BY r.created_at DESC, r.refund_id DESC
         LIMIT {$pageSize} OFFSET {$offset}"
    )
    : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Refunds — CTECHLIT & SYSTEMS</title>
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
      <?php if (!$refundsEnabled): ?>
        <div class="card animate-fade-in" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); color: var(--amber); margin-bottom: 20px; padding: 14px 20px; border-radius: var(--radius-lg); display: flex; align-items: center; gap: 10px;">
          <i class="fas fa-database"></i>
          <span>Refunds are disabled until the refunds tables are installed — run <code>database/migrations/004_transaction_refunds.sql</code>.</span>
        </div>
      <?php endif; ?>

      <div class="card glass-card animate-fade-in">
        <div class="card-header">
          <div>
            <div class="card-title">Refund History</div>
            <div class="card-subtitle">Money returned to customers</div>
          </div>
          <a class="btn btn-primary" href="transactions.php">Issue a Refund</a>
        </div>
        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table">
            <thead>
              <tr><th>Receipt</th><th>Customer</th><th>Reason</th><th>Amount</th><th>Processed By</th><th>Date</th></tr>
            </thead>
            <tbody>
              <?php foreach ($refunds as $refund): ?>
                <tr>
                  <td style="font-weight: 600; color: var(--violet);"><?php echo sanitize($refund['receipt_number'] ?? ('#TXN-' . $refund['transaction_id'])); ?></td>
                  <td><?php echo sanitize($refund['customer_name'] ?? 'Walk-in Customer'); ?></td>
                  <td><span class="status-badge info"><span class="status-dot"></span><?php echo sanitize(ucfirst(str_replace('_', ' ', $refund['reason']))); ?></span></td>
                  <td style="font-weight: 600; color: var(--red);">−GHS <?php echo number_format((float) $refund['amount'], 2); ?></td>
                  <td><?php echo sanitize($refund['processed_by_name'] ?? 'System'); ?></td>
                  <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize(date('M j, Y H:i', strtotime($refund['created_at']))); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($refunds)): ?>
                <tr><td colspan="6" style="text-align: center; padding: 24px; color: var(--ink-secondary);">No refunds processed yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-top:16px;">
          <span style="font-size:13px; color:var(--ink-secondary);">
            <?php if ($totalRefunds > 0): ?>
              Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $pageSize, $totalRefunds); ?> of <?php echo $totalRefunds; ?> refunds
            <?php else: ?>
              No refunds
            <?php endif; ?>
            (Page <?php echo $page; ?> of <?php echo $totalPages; ?>)
          </span>
          <nav aria-label="Refund pagination" style="display:flex; gap:8px;">
            <?php if ($page > 1): ?>
              <a class="btn btn-secondary" href="?page=<?php echo $page - 1; ?>" aria-label="Previous refunds page">Previous</a>
            <?php else: ?>
              <span class="btn btn-secondary" aria-disabled="true" style="opacity:.5; pointer-events:none;">Previous</span>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
              <a class="btn btn-secondary" href="?page=<?php echo $page + 1; ?>" aria-label="Next refunds page">Next</a>
            <?php else: ?>
              <span class="btn btn-secondary" aria-disabled="true" style="opacity:.5; pointer-events:none;">Next</span>
            <?php endif; ?>
          </nav>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
