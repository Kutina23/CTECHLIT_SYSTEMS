<?php
/**
 * CTECHLIT & SYSTEMS — Transactions
 * Financial transaction overview with refund processing
 */

require_once __DIR__ . '/../includes/auth.php';
requirePermission('transactions.view');

$canRefund = hasPermission('transactions.refund');
$transactions = portalTransactions();
$totals = portalRefundTotals();
$refundsEnabled = refundsTableExists();

/**
 * Human label for a transaction status.
 */
function transactionStatusLabel(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}

/**
 * Status badge class for a transaction status.
 */
function transactionStatusClass(string $status): string
{
    switch ($status) {
        case 'completed':
            return 'success';
        case 'pending':
            return 'warning';
        case 'refunded':
        case 'cancelled':
            return 'error';
        case 'partially_refunded':
            return 'warning';
        default:
            return 'info';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Transactions — CTECHLIT & SYSTEMS</title>
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

      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-money-bill-wave"></i></div></div>
          <div class="kpi-value">GHS <?php echo number_format($totals['net_revenue'], 2); ?></div>
          <div class="kpi-label">Net Revenue</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-receipt"></i></div></div>
          <div class="kpi-value">GHS <?php echo number_format($totals['gross_revenue'], 2); ?></div>
          <div class="kpi-label">Gross Revenue</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-clock"></i></div></div>
          <div class="kpi-value">GHS <?php echo number_format($totals['pending_amount'], 2); ?></div>
          <div class="kpi-label">Pending</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--red); --kpi-bg: rgba(239, 68, 68, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-undo"></i></div></div>
          <div class="kpi-value">GHS <?php echo number_format($totals['refunded_total'], 2); ?></div>
          <div class="kpi-label">Refunds (<?php echo (int) $totals['refund_count']; ?>)</div>
        </div>
      </div>

      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">Recent Transactions</div>
            <div class="card-subtitle">Latest payments and settlement entries</div>
          </div>
          <div style="display: flex; gap: 10px; align-items: center;">
            <input type="search" id="transactionFilter" class="form-input" placeholder="Filter by receipt, customer or status" style="width: 260px; padding: 9px 14px;">
            <button class="btn btn-primary" style="padding: 10px 16px; font-size: 13px;">
              <i class="fas fa-download"></i> Export
            </button>
          </div>
        </div>

        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table" id="transactionsTable">
            <thead>
              <tr>
                <th>Txn ID</th>
                <th>Customer</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Refunded</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($transactions as $transaction): ?>
              <?php
                  $status = (string) ($transaction['status'] ?? 'pending');
                  $refunded = (float) ($transaction['refunded_amount'] ?? 0);
                  $refundable = (float) ($transaction['refundable_amount'] ?? 0);
                  $canRefundRow = $refundsEnabled
                      && $canRefund
                      && in_array($status, ['completed', 'partially_refunded'], true)
                      && $refundable > 0;

                  $rowJson = json_encode([
                      'transaction_id' => (int) $transaction['transaction_id'],
                      'receipt_number' => $transaction['receipt_number'] ?? ('TXN-' . $transaction['transaction_id']),
                      'customer_name' => $transaction['customer_name'] ?? 'Walk-in Customer',
                      'total_amount' => number_format((float) $transaction['total_amount'], 2, '.', ''),
                      'refunded_amount' => number_format($refunded, 2, '.', ''),
                      'refundable_amount' => number_format($refundable, 2, '.', ''),
                      'status' => $status,
                      'created_at' => $transaction['created_at'] ?? null,
                      'can_refund' => $canRefundRow,
                  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
              ?>
              <tr data-id="<?php echo (int) $transaction['transaction_id']; ?>" data-transaction='<?php echo htmlspecialchars($rowJson, ENT_QUOTES, 'UTF-8'); ?>'>
                <td style="font-weight: 600; color: var(--violet);">
                  <?php echo sanitize($transaction['receipt_number'] ?? ('#TXN-' . $transaction['transaction_id'])); ?>
                </td>
                <td><?php echo sanitize($transaction['customer_name'] ?? 'Walk-in Customer'); ?></td>
                <td><?php echo sanitize($transaction['type_name'] ?? 'Transaction'); ?></td>
                <td style="font-weight: 600; color: var(--ink);">GHS <?php echo number_format((float) $transaction['total_amount'], 2); ?></td>
                <td style="font-weight: 600; color: <?php echo $refunded > 0 ? 'var(--red)' : 'var(--ink-secondary)'; ?>;">
                  <?php echo $refunded > 0 ? '−GHS ' . number_format($refunded, 2) : '—'; ?>
                </td>
                <td>
                  <span class="status-badge <?php echo transactionStatusClass($status); ?>">
                    <span class="status-dot"></span><?php echo sanitize(transactionStatusLabel($status)); ?>
                  </span>
                </td>
                <td>
                  <div class="action-buttons" style="display: flex; gap: 6px;">
                    <button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button>
                    <?php if ($canRefundRow): ?>
                      <button class="header-btn" title="Refund" data-action="refund" style="color: var(--amber);"><i class="fas fa-undo"></i></button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($transactions)): ?>
              <tr>
                <td colspan="7" style="text-align: center; padding: 24px; color: var(--ink-secondary);">No transactions found</td>
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
  const REFUNDS_ENABLED = <?php echo $refundsEnabled ? 'true' : 'false'; ?>;
  const CURRENCY = '<?php echo sanitize(siteSetting('currency_symbol', 'GHS')); ?>';

  window.TRANSACTION_VIEW_FIELDS = [
    {name: 'receipt_number', label: 'Receipt Number', type: 'text'},
    {name: 'customer_name', label: 'Customer', type: 'text'},
    {name: 'total_amount', label: 'Total Amount', type: 'text'},
    {name: 'refunded_amount', label: 'Refunded Amount', type: 'text'},
    {name: 'refundable_amount', label: 'Refundable Balance', type: 'text'},
    {name: 'status', label: 'Status', type: 'text'},
    {name: 'created_at', label: 'Recorded On', type: 'text'}
  ];

  document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('transactionsTable');
    const filter = document.getElementById('transactionFilter');

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
      const transaction = parseRowData(row);
      if (!transaction) return;

      if (button.dataset.action === 'view') {
        showTransactionDetails(transaction);
      } else if (button.dataset.action === 'refund') {
        openRefundModal(transaction);
      }
    });
  });

  function parseRowData(row) {
    try {
      return JSON.parse(row.dataset.transaction);
    } catch (error) {
      console.error('Unable to read transaction data', error);
      return null;
    }
  }

  function money(value) {
    const amount = Number(value || 0);
    return CURRENCY + ' ' + amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }

  function titleCase(value) {
    if (!value) return '';
    return String(value).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }

  function escapeHtml(value) {
    const element = document.createElement('span');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
  }

  async function showTransactionDetails(transaction) {
    const overlay = document.getElementById('viewModalOverlay');
    const body = document.getElementById('viewModalBody');
    const modalTitle = document.getElementById('viewModalTitle');

    let items = [];
    let refunds = [];
    let fullTransaction = {...transaction};

    try {
      const response = await fetch(`api/orders.php?transaction_id=${encodeURIComponent(transaction.transaction_id)}`, {
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const result = await response.json();
      if (!response.ok || !result?.success || !result.data || typeof result.data !== 'object') {
        throw new Error(result?.message || 'The transaction details could not be loaded.');
      }
      fullTransaction = {...transaction, ...result.data};
      items = Array.isArray(result.data.items) ? result.data.items : [];
      refunds = Array.isArray(result.data.refunds) ? result.data.refunds : [];
    } catch (error) {
      console.error('Unable to load transaction detail', error);
      ModalController.showNotification(`Showing available list data only. ${error.message}`, 'warning');
    }

    const itemRows = items.length
      ? items.map((item) => `
          <tr>
            <td>${escapeHtml(item.service_name || item.description || 'Service')}</td>
            <td>${escapeHtml(Number(item.quantity || 0))}</td>
            <td>${money(item.unit_price)}</td>
            <td>${money(item.total_price)}</td>
          </tr>`).join('')
      : '<tr><td colspan="4" style="text-align:center; color: var(--ink-secondary);">No line items on this transaction</td></tr>';

    const refundRows = refunds.length
      ? refunds.map((refund) => `
          <tr>
            <td>${escapeHtml(titleCase(refund.reason))}</td>
            <td style="color: var(--red); font-weight: 600;">−${money(refund.amount)}</td>
            <td>${escapeHtml(refund.notes || '—')}</td>
            <td>${escapeHtml(refund.created_at || '—')}</td>
          </tr>`).join('')
      : '<tr><td colspan="4" style="text-align:center; color: var(--ink-secondary);">No refunds on this transaction</td></tr>';

    modalTitle.textContent = `Transaction ${fullTransaction.receipt_number || fullTransaction.transaction_id}`;
    body.innerHTML = `
      <div style="display: grid; gap: 16px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
          ${infoTile('Customer', fullTransaction.customer_name || 'Walk-in Customer')}
          ${infoTile('Total', money(fullTransaction.total_amount))}
          ${infoTile('Refunded', money(fullTransaction.refunded_amount))}
          ${infoTile('Refundable', money(fullTransaction.refundable_amount))}
        </div>

        <div>
          <div class="form-label" style="margin-bottom: 8px;">Line items</div>
          <table class="data-table" style="width: 100%;">
            <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
            <tbody>${itemRows}</tbody>
          </table>
        </div>

        <div>
          <div class="form-label" style="margin-bottom: 8px;">Refund history</div>
          <table class="data-table" style="width: 100%;">
            <thead><tr><th>Reason</th><th>Amount</th><th>Notes</th><th>Date</th></tr></thead>
            <tbody>${refundRows}</tbody>
          </table>
        </div>
        <section class="record-detail-section">
          <h4>Complete transaction details</h4>
          ${ModalController.renderDetails('transaction', Object.fromEntries(
            Object.entries({...fullTransaction, items, refunds})
              .filter(([key]) => key !== 'items' && key !== 'refunds')
          ))}
        </section>
      </div>
    `;
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

  function openRefundModal(transaction) {
    const refundable = Number(transaction.refundable_amount || 0);
    if (refundable <= 0) {
      ModalController.showNotification('This transaction has nothing left to refund', 'warning');
      return;
    }

    const overlay = document.getElementById('viewModalOverlay');
    const body = document.getElementById('viewModalBody');
    const modalTitle = document.getElementById('viewModalTitle');

    modalTitle.textContent = `Refund ${transaction.receipt_number}`;
    body.innerHTML = `
      <form id="refundForm" style="display: grid; gap: 16px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
          ${infoTile('Customer', transaction.customer_name)}
          ${infoTile('Refundable', money(refundable))}
        </div>

        <div>
          <label class="form-label" for="refund_amount">Refund amount</label>
          <input id="refund_amount" name="refund_amount" type="number" step="0.01" min="0.01"
                 max="${refundable.toFixed(2)}"
                 value="${refundable.toFixed(2)}" required>
          <div class="form-hint">Maximum refundable ${escapeHtml(money(refundable))}</div>
        </div>

        <div>
          <label class="form-label" for="refund_reason">Reason</label>
          <select id="refund_reason" name="reason" required>
            <option value="customer_request">Customer request</option>
            <option value="service_issue">Service issue</option>
            <option value="billing_error">Billing error</option>
            <option value="duplicate_charge">Duplicate charge</option>
            <option value="manager_override">Manager override</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div>
          <label class="form-label" for="refund_notes">Notes</label>
          <textarea id="refund_notes" name="notes" rows="3" placeholder="Why is this refund being issued?"></textarea>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
          <button type="button" class="btn btn-secondary" data-action="close-refund">Cancel</button>
          <button type="submit" class="btn btn-primary" style="background: var(--amber); border-color: var(--amber); color: #1B1233;">
            <i class="fas fa-undo"></i> Issue refund
          </button>
        </div>
      </form>
    `;
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';

    body.querySelector('[data-action="close-refund"]').addEventListener('click', closeModal);
    document.getElementById('refundForm').addEventListener('submit', async (event) => {
      event.preventDefault();

      const submitButton = event.target.querySelector('button[type="submit"]');
      submitButton.disabled = true;

      const formData = new FormData(event.target);
      const amount = Number(formData.get('refund_amount'));

      if (!amount || amount <= 0 || amount > refundable) {
        ModalController.showNotification(`Enter an amount between 0.01 and ${money(refundable)}`, 'error');
        submitButton.disabled = false;
        return;
      }

      try {
        const response = await fetch('api/orders.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
          body: JSON.stringify({
            action: 'refund',
            transaction_id: transaction.transaction_id,
            refund_amount: amount,
            reason: formData.get('reason'),
            notes: formData.get('notes')
          })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Refund failed');

        closeModal();
        ModalController.showNotification(result.message || 'Refund processed', 'success');
        setTimeout(() => window.location.reload(), 700);
      } catch (error) {
        ModalController.showNotification(error.message || 'Refund failed', 'error');
        submitButton.disabled = false;
      }
    });
  }

  function closeModal() {
    const overlay = document.getElementById('viewModalOverlay');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
</script>
</body>
</html>
