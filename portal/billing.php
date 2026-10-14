<?php
/**
 * CTECHLIT & SYSTEMS — Customer Billing
 * Wallets, unbilled charges, invoices, payments and statements.
 */

require_once __DIR__ . '/../includes/auth.php';
requireStaffAuth();
requirePermission('billing.view');

$canManageBilling = hasPermission('billing.manage');
$currency = siteSetting('currency_symbol', 'GHS');
$businessName = siteSetting('business_name', 'CTECHLIT & SYSTEMS');
$businessAddress = siteSetting('contact_address', '');
$businessPhone = siteSetting('contact_phone', '');
$businessEmail = siteSetting('contact_email', '');
$focusUserId = (int) ($_GET['user_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Billing — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <style>
    .billing-layout { display: grid; grid-template-columns: minmax(300px, 0.9fr) minmax(0, 1.6fr); gap: 24px; align-items: start; }
    .billing-search { position: relative; margin-bottom: 12px; }
    .billing-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); }
    .billing-search input { padding-left: 36px; }
    .account-list { display: flex; flex-direction: column; gap: 6px; max-height: 640px; overflow-y: auto; padding-right: 4px; }
    .account-item { display: grid; grid-template-columns: 38px 1fr auto; gap: 10px; align-items: center; width: 100%; padding: 10px 12px; border: 1px solid transparent; border-radius: 12px; background: rgba(255,255,255,0.5); text-align: left; cursor: pointer; transition: var(--transition); font: inherit; color: var(--ink); }
    .account-item:hover { border-color: rgba(124,58,237,0.25); }
    .account-item.active { border-color: var(--violet); background: rgba(124,58,237,0.08); }
    .account-avatar { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, var(--violet), var(--cyan)); color: #fff; font-weight: 700; }
    .account-name { font-weight: 600; font-size: 14px; }
    .account-meta { font-size: 12px; color: var(--muted); }
    .account-owed { text-align: right; font-size: 13px; font-weight: 700; }
    .account-owed small { display: block; font-weight: 500; color: var(--muted); font-size: 11px; }
    .owed-positive { color: var(--red, #EF4444); }
    .owed-clear { color: var(--green, #10B981); }
    .detail-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 18px; }
    .detail-head h2 { margin: 0; font-size: 22px; color: var(--ink); }
    .detail-head p { margin: 4px 0 0; color: var(--ink-secondary); font-size: 13px; }
    .detail-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .balance-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
    .balance-tile { padding: 14px; border-radius: 14px; background: rgba(255,255,255,0.6); border: 1px solid rgba(148,163,184,0.2); }
    .balance-tile span { display: block; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
    .balance-tile strong { font-size: 20px; color: var(--ink); font-variant-numeric: tabular-nums; }
    .billing-tabs { display: flex; gap: 4px; padding: 4px; margin-bottom: 14px; border-radius: 12px; background: rgba(148,163,184,0.12); width: fit-content; }
    .billing-tabs button { padding: 8px 14px; border: 0; border-radius: 9px; background: transparent; color: var(--ink-secondary); font: 600 13px Inter, sans-serif; cursor: pointer; }
    .billing-tabs button[aria-selected="true"] { background: #fff; color: var(--violet); box-shadow: 0 2px 8px rgba(15,23,42,0.08); }
    .amount { font-variant-numeric: tabular-nums; font-weight: 600; white-space: nowrap; }
    .amount.credit { color: var(--green, #10B981); }
    .amount.debit { color: var(--red, #EF4444); }
    .empty-note { padding: 28px 12px; text-align: center; color: var(--muted); font-size: 13px; }
    .placeholder-panel { display: grid; place-items: center; min-height: 420px; text-align: center; color: var(--muted); }
    .placeholder-panel i { font-size: 40px; margin-bottom: 12px; color: var(--violet); opacity: 0.6; }
    .invoice-filter { display: flex; gap: 8px; flex-wrap: wrap; }
    .bill-form { display: grid; gap: 14px; }
    .bill-form .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .bill-form label { display: block; font-size: 13px; color: var(--ink-secondary); margin-bottom: 6px; }
    .bill-hint { font-size: 12px; color: var(--muted); margin-top: 4px; }
    .invoice-doc { background: #fff; color: #0f172a; padding: 28px; border-radius: 12px; font-size: 13px; }
    .invoice-doc header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #7C3AED; padding-bottom: 14px; margin-bottom: 18px; }
    .invoice-doc h3 { margin: 0; font-size: 20px; }
    .invoice-doc table { width: 100%; border-collapse: collapse; margin: 14px 0; }
    .invoice-doc th, .invoice-doc td { padding: 8px 6px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    .invoice-doc td.num, .invoice-doc th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .invoice-doc .totals { margin-left: auto; width: 260px; }
    .invoice-doc .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
    .invoice-doc .totals .grand { font-weight: 700; font-size: 15px; border-top: 2px solid #0f172a; margin-top: 4px; padding-top: 8px; }
    .stamp { display: inline-block; padding: 4px 10px; border: 2px solid currentColor; border-radius: 6px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; font-size: 12px; }
    .stamp.paid { color: #059669; } .stamp.overdue { color: #DC2626; } .stamp.void { color: #64748B; } .stamp.issued, .stamp.partially_paid { color: #D97706; }
    @media (max-width: 1100px) { .billing-layout { grid-template-columns: 1fr; } .balance-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media print {
      body * { visibility: hidden !important; }
      #printArea, #printArea * { visibility: visible !important; }
      #printArea { position: absolute; inset: 0; padding: 0; }
    }
  </style>
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content">
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-wallet"></i></div></div>
          <div class="kpi-value" id="kpiWallet">—</div>
          <div class="kpi-label">Customer Wallet Balances</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-receipt"></i></div></div>
          <div class="kpi-value" id="kpiUnbilled">—</div>
          <div class="kpi-label">Unbilled Charges</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-file-invoice-dollar"></i></div></div>
          <div class="kpi-value" id="kpiReceivable">—</div>
          <div class="kpi-label" id="kpiReceivableLabel">Open Invoices</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--red); --kpi-bg: rgba(239, 68, 68, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-triangle-exclamation"></i></div></div>
          <div class="kpi-value" id="kpiOverdue">—</div>
          <div class="kpi-label" id="kpiOverdueLabel">Overdue</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.2s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-hand-holding-dollar"></i></div></div>
          <div class="kpi-value" id="kpiCollected">—</div>
          <div class="kpi-label">Collected This Month</div>
        </div>
      </div>

      <div class="billing-layout">
        <section class="card glass-card animate-fade-in" aria-labelledby="accountsTitle">
          <div class="card-header">
            <div>
              <div class="card-title" id="accountsTitle">Customer Accounts</div>
              <div class="card-subtitle">Sorted by amount owed</div>
            </div>
          </div>
          <div class="billing-search">
            <i class="fas fa-search" aria-hidden="true"></i>
            <label class="sr-only" for="accountSearch">Search customers</label>
            <input id="accountSearch" class="form-input" type="search" placeholder="Search name, phone or account no.">
          </div>
          <div class="account-list" id="accountList" role="listbox" aria-label="Customer accounts">
            <div class="empty-note">Loading accounts…</div>
          </div>
        </section>

        <section class="card glass-card animate-fade-in" id="accountPanel" aria-live="polite" style="animation-delay: 0.05s;">
          <div class="placeholder-panel">
            <div>
              <i class="fas fa-user-tag" aria-hidden="true"></i>
              <p>Select a customer to see their wallet, charges, invoices and statement.</p>
            </div>
          </div>
        </section>
      </div>

      <section class="card glass-card animate-fade-in" style="margin-top: 24px; animation-delay: 0.1s;">
        <div class="card-header">
          <div>
            <div class="card-title">Invoices</div>
            <div class="card-subtitle">All customer invoices</div>
          </div>
          <div class="invoice-filter" role="group" aria-label="Filter invoices">
            <button class="btn btn-secondary small" data-invoice-filter="open" aria-pressed="true">Open</button>
            <button class="btn btn-secondary small" data-invoice-filter="overdue" aria-pressed="false">Overdue</button>
            <button class="btn btn-secondary small" data-invoice-filter="paid" aria-pressed="false">Paid</button>
            <button class="btn btn-secondary small" data-invoice-filter="void" aria-pressed="false">Void</button>
            <button class="btn btn-secondary small" data-invoice-filter="" aria-pressed="false">All</button>
          </div>
        </div>
        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table">
            <thead>
              <tr><th>Invoice</th><th>Customer</th><th>Issued</th><th>Due</th><th>Total</th><th>Balance</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody id="invoiceTable"><tr><td colspan="8" class="empty-note">Loading invoices…</td></tr></tbody>
          </table>
        </div>
      </section>
    </div>
  </main>

  <div class="modal-overlay" id="billModal" role="dialog" aria-modal="true" aria-labelledby="billModalTitle">
    <div class="modal" id="billModalBox">
      <div class="modal-header">
        <h3 class="modal-title" id="billModalTitle">Billing</h3>
        <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body" id="billModalBody"></div>
      <div class="modal-footer" id="billModalFooter"></div>
    </div>
  </div>
  <div id="printArea" hidden></div>

  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script>
  (function () {
    const CURRENCY = <?php echo json_encode($currency); ?>;
    const CAN_MANAGE = <?php echo $canManageBilling ? 'true' : 'false'; ?>;
    const BUSINESS = <?php echo json_encode(['name' => $businessName, 'address' => $businessAddress, 'phone' => $businessPhone, 'email' => $businessEmail]); ?>;
    const API = 'api/billing.php';
    let selectedUserId = <?php echo $focusUserId ?: 'null'; ?>;
    let currentOverview = null;
    let invoiceFilter = 'open';
    let activeTab = 'unbilled';

    const money = (value) => CURRENCY + ' ' + Number(value || 0).toLocaleString('en-GH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const esc = (value) => { const div = document.createElement('div'); div.textContent = value == null ? '' : String(value); return div.innerHTML; };
    const fmtDate = (value) => value ? new Date(/^\d{4}-\d{2}-\d{2}$/.test(String(value)) ? value + 'T00:00:00' : String(value).replace(' ', 'T')).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'}) : '—';
    const fmtDateTime = (value) => value ? new Date(String(value).replace(' ', 'T')).toLocaleString('en-GB', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'}) : '—';
    const statusClass = (status) => ({paid: 'success', overdue: 'error', void: 'info', partially_paid: 'warning', issued: 'warning'})[status] || 'info';
    const statusLabel = (status) => String(status || '').replace('_', ' ').replace(/^./, (c) => c.toUpperCase());

    function notify(message, type) {
      if (window.ModalController && ModalController.showNotification) ModalController.showNotification(message, type || 'success');
      else window.alert(message);
    }

    async function request(url, options) {
      const response = await fetch(url, Object.assign({credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json'}}, options || {}));
      let result = {};
      try { result = await response.json(); } catch (error) { /* non-JSON */ }
      if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Request failed (HTTP ' + response.status + ')');
      return result;
    }

    const post = (payload) => request(API, {method: 'POST', body: JSON.stringify(payload)});

    // ---------- Summary ----------
    async function loadSummary() {
      try {
        const {data} = await request(API + '?summary=1');
        document.getElementById('kpiWallet').textContent = money(data.wallet_total);
        document.getElementById('kpiUnbilled').textContent = money(data.unbilled_total);
        document.getElementById('kpiReceivable').textContent = money(data.receivable_total);
        document.getElementById('kpiReceivableLabel').textContent = data.open_invoices + ' Open Invoice' + (data.open_invoices === 1 ? '' : 's');
        document.getElementById('kpiOverdue').textContent = money(data.overdue_total);
        document.getElementById('kpiOverdueLabel').textContent = data.overdue_count + ' Overdue';
        document.getElementById('kpiCollected').textContent = money(data.collected_this_month);
      } catch (error) {
        notify(error.message, 'error');
      }
    }

    // ---------- Accounts list ----------
    let searchTimer = null;
    async function loadAccounts() {
      const list = document.getElementById('accountList');
      const search = document.getElementById('accountSearch').value.trim();
      try {
        const {data} = await request(API + '?accounts=1' + (search ? '&search=' + encodeURIComponent(search) : ''));
        if (!data.length) {
          list.innerHTML = '<div class="empty-note">No customers found.</div>';
          return;
        }
        list.innerHTML = data.map((account) => {
          const owed = Number(account.owed);
          const initials = String(account.full_name || '?').split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase();
          return '<button type="button" role="option" class="account-item' + (Number(account.user_id) === selectedUserId ? ' active' : '') + '" data-user-id="' + account.user_id + '" aria-selected="' + (Number(account.user_id) === selectedUserId) + '">'
            + '<span class="account-avatar" aria-hidden="true">' + esc(initials) + '</span>'
            + '<span><span class="account-name">' + esc(account.full_name) + '</span><span class="account-meta" style="display:block">' + esc(account.account_number || 'No account yet') + ' · ' + esc(account.phone || account.email || 'Walk-in') + '</span></span>'
            + '<span class="account-owed ' + (owed > 0 ? 'owed-positive' : 'owed-clear') + '">' + (owed > 0 ? money(owed) : 'Clear') + '<small>Wallet ' + money(account.balance) + '</small></span>'
            + '</button>';
        }).join('');
      } catch (error) {
        list.innerHTML = '<div class="empty-note">' + esc(error.message) + '</div>';
      }
    }

    document.getElementById('accountSearch').addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(loadAccounts, 250);
    });
    document.getElementById('accountList').addEventListener('click', (event) => {
      const item = event.target.closest('[data-user-id]');
      if (!item) return;
      selectedUserId = Number(item.dataset.userId);
      document.querySelectorAll('.account-item').forEach((node) => {
        node.classList.toggle('active', node === item);
        node.setAttribute('aria-selected', String(node === item));
      });
      loadAccount();
    });

    // ---------- Account panel ----------
    async function loadAccount() {
      if (!selectedUserId) return;
      const panel = document.getElementById('accountPanel');
      try {
        const {data} = await request(API + '?user_id=' + selectedUserId);
        currentOverview = data;
        renderAccount();
      } catch (error) {
        panel.innerHTML = '<div class="empty-note">' + esc(error.message) + '</div>';
      }
    }

    function renderAccount() {
      const {customer, account, outstanding, unbilled, invoices, ledger} = currentOverview;
      const panel = document.getElementById('accountPanel');
      const actions = CAN_MANAGE ? '<div class="detail-actions">'
        + '<button class="btn btn-primary small" data-act="topup"><i class="fas fa-plus"></i> Top up</button>'
        + '<button class="btn btn-secondary small" data-act="invoice"' + (unbilled.length ? '' : ' disabled') + '><i class="fas fa-file-invoice"></i> Create invoice</button>'
        + '<button class="btn btn-secondary small" data-act="settle"' + (Number(account.balance) > 0 && outstanding.total > 0 ? '' : ' disabled') + '><i class="fas fa-wallet"></i> Pay from wallet</button>'
        + '<button class="btn btn-secondary small" data-act="adjust"><i class="fas fa-scale-balanced"></i> Adjust</button>'
        + '<button class="btn btn-secondary small" data-act="settings" aria-label="Account settings"><i class="fas fa-gear"></i></button>'
        + '</div>' : '';

      panel.innerHTML = '<div class="detail-head"><div><h2>' + esc(customer.full_name) + '</h2>'
        + '<p>' + esc(account.account_number) + ' · ' + esc(customer.phone || customer.email || 'No contact') + ' · '
        + '<span class="status-badge ' + (account.status === 'active' ? 'success' : 'error') + '"><span class="status-dot"></span>' + esc(statusLabel(account.status)) + '</span></p></div>' + actions + '</div>'
        + '<div class="balance-grid">'
        + '<div class="balance-tile"><span>Wallet balance</span><strong class="amount credit">' + money(account.balance) + '</strong></div>'
        + '<div class="balance-tile"><span>Amount owed</span><strong class="amount ' + (outstanding.total > 0 ? 'debit' : '') + '">' + money(outstanding.total) + '</strong></div>'
        + '<div class="balance-tile"><span>Unbilled</span><strong>' + money(outstanding.unbilled) + '</strong></div>'
        + '<div class="balance-tile"><span>Credit limit</span><strong>' + (Number(account.credit_limit) > 0 ? money(account.credit_limit) : 'None') + '</strong></div>'
        + '</div>'
        + '<div class="billing-tabs" role="tablist">'
        + ['unbilled', 'invoices', 'statement'].map((tab) => '<button role="tab" data-tab="' + tab + '" aria-selected="' + (activeTab === tab) + '">' + {unbilled: 'Unbilled (' + unbilled.length + ')', invoices: 'Invoices (' + invoices.length + ')', statement: 'Statement'}[tab] + '</button>').join('')
        + '</div><div id="tabContent"></div>';
      renderTab();
    }

    function renderTab() {
      const {unbilled, invoices, ledger} = currentOverview;
      const target = document.getElementById('tabContent');
      if (activeTab === 'unbilled') {
        target.innerHTML = unbilled.length
          ? '<div class="table-container" style="border:none;background:transparent"><table class="data-table"><thead><tr><th>Date</th><th>Description</th><th>Reference</th><th style="text-align:right">Amount</th></tr></thead><tbody>'
            + unbilled.map((charge) => '<tr><td>' + fmtDateTime(charge.created_at) + '</td><td>' + esc(charge.description) + '</td><td>' + esc(charge.payment_reference || '—') + '</td><td class="amount" style="text-align:right">' + money(charge.total_amount) + '</td></tr>').join('')
            + '</tbody></table></div>'
          : '<div class="empty-note">No unbilled charges. Pay-later POS sales and unpaid station sessions appear here.</div>';
      } else if (activeTab === 'invoices') {
        target.innerHTML = invoices.length
          ? '<div class="table-container" style="border:none;background:transparent"><table class="data-table"><thead><tr><th>Invoice</th><th>Due</th><th>Total</th><th>Balance</th><th>Status</th><th></th></tr></thead><tbody>'
            + invoices.map((invoice) => invoiceRow(invoice, false)).join('') + '</tbody></table></div>'
          : '<div class="empty-note">No invoices yet.</div>';
      } else {
        target.innerHTML = ledger.length
          ? '<div class="table-container" style="border:none;background:transparent"><table class="data-table"><thead><tr><th>Date</th><th>Entry</th><th>By</th><th style="text-align:right">Amount</th><th style="text-align:right">Balance</th></tr></thead><tbody>'
            + ledger.map((entry) => '<tr><td>' + fmtDateTime(entry.created_at) + '</td><td>' + esc(entry.description) + (entry.reference ? '<div class="account-meta">Ref ' + esc(entry.reference) + '</div>' : '') + '</td><td>' + esc(entry.staff_name || 'System') + '</td>'
              + '<td class="amount ' + (Number(entry.amount) >= 0 ? 'credit' : 'debit') + '" style="text-align:right">' + (Number(entry.amount) >= 0 ? '+' : '−') + money(Math.abs(entry.amount)) + '</td><td class="amount" style="text-align:right">' + money(entry.balance_after) + '</td></tr>').join('')
            + '</tbody></table></div>'
          : '<div class="empty-note">No wallet activity yet.</div>';
      }
    }

    function invoiceRow(invoice, withCustomer) {
      const status = invoice.display_status;
      const canPay = CAN_MANAGE && (status === 'issued' || status === 'partially_paid' || status === 'overdue');
      return '<tr>'
        + '<td><strong style="color:var(--violet)">' + esc(invoice.invoice_number) + '</strong></td>'
        + (withCustomer ? '<td><a href="#" data-open-account="' + invoice.user_id + '">' + esc(invoice.customer_name) + '</a></td><td>' + fmtDate(invoice.issue_date) + '</td>' : '')
        + '<td>' + fmtDate(invoice.due_date) + '</td>'
        + '<td class="amount">' + money(invoice.total_amount) + '</td>'
        + '<td class="amount ' + (Number(invoice.balance_due) > 0 && status !== 'void' ? 'debit' : '') + '">' + money(status === 'void' ? 0 : invoice.balance_due) + '</td>'
        + '<td><span class="status-badge ' + statusClass(status) + '"><span class="status-dot"></span>' + esc(statusLabel(status)) + '</span></td>'
        + '<td style="white-space:nowrap"><button class="header-btn" title="View / print" aria-label="View invoice ' + esc(invoice.invoice_number) + '" data-view-invoice="' + invoice.invoice_id + '" style="width:32px;height:32px"><i class="fas fa-eye"></i></button>'
        + (canPay ? ' <button class="header-btn" title="Record payment" aria-label="Record payment" data-pay-invoice="' + invoice.invoice_id + '" style="width:32px;height:32px;color:var(--green)"><i class="fas fa-money-bill-wave"></i></button>' : '')
        + '</td></tr>';
    }

    document.getElementById('accountPanel').addEventListener('click', (event) => {
      const tab = event.target.closest('[data-tab]');
      if (tab) {
        activeTab = tab.dataset.tab;
        document.querySelectorAll('[data-tab]').forEach((node) => node.setAttribute('aria-selected', String(node === tab)));
        renderTab();
        return;
      }
      const act = event.target.closest('[data-act]');
      if (act) openAction(act.dataset.act);
    });

    // ---------- Invoices table ----------
    async function loadInvoices() {
      const tbody = document.getElementById('invoiceTable');
      try {
        const {data} = await request(API + '?invoices=1' + (invoiceFilter ? '&status=' + invoiceFilter : ''));
        tbody.innerHTML = data.length ? data.map((invoice) => invoiceRow(invoice, true)).join('') : '<tr><td colspan="8" class="empty-note">No invoices in this view.</td></tr>';
      } catch (error) {
        tbody.innerHTML = '<tr><td colspan="8" class="empty-note">' + esc(error.message) + '</td></tr>';
      }
    }

    document.querySelectorAll('[data-invoice-filter]').forEach((button) => button.addEventListener('click', () => {
      invoiceFilter = button.dataset.invoiceFilter;
      document.querySelectorAll('[data-invoice-filter]').forEach((node) => node.setAttribute('aria-pressed', String(node === button)));
      loadInvoices();
    }));

    document.addEventListener('click', (event) => {
      const view = event.target.closest('[data-view-invoice]');
      if (view) { viewInvoice(Number(view.dataset.viewInvoice)); return; }
      const pay = event.target.closest('[data-pay-invoice]');
      if (pay) { openPayment(Number(pay.dataset.payInvoice)); return; }
      const open = event.target.closest('[data-open-account]');
      if (open) {
        event.preventDefault();
        selectedUserId = Number(open.dataset.openAccount);
        loadAccounts();
        loadAccount();
        document.getElementById('accountPanel').scrollIntoView({behavior: 'smooth', block: 'start'});
      }
    });

    // ---------- Modal ----------
    const modal = document.getElementById('billModal');
    let modalSubmit = null;
    function openModal(title, bodyHtml, submitLabel, onSubmit, wide) {
      document.getElementById('billModalTitle').textContent = title;
      document.getElementById('billModalBody').innerHTML = bodyHtml;
      document.getElementById('billModalBox').classList.toggle('modal-lg', Boolean(wide));
      const footer = document.getElementById('billModalFooter');
      footer.innerHTML = '<button type="button" class="btn btn-secondary" data-close-modal>' + (onSubmit ? 'Cancel' : 'Close') + '</button>'
        + (onSubmit ? '<button type="button" class="btn btn-primary" id="billModalSubmit">' + esc(submitLabel) + '</button>' : '');
      modalSubmit = onSubmit;
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
      const first = modal.querySelector('input, select, textarea');
      if (first) first.focus();
    }
    function closeModal() {
      modal.classList.remove('active');
      document.body.style.overflow = '';
      modalSubmit = null;
    }
    modal.addEventListener('click', async (event) => {
      if (event.target === modal || event.target.closest('[data-close-modal]')) { closeModal(); return; }
      const submit = event.target.closest('#billModalSubmit');
      if (submit && modalSubmit) {
        submit.disabled = true;
        try {
          await modalSubmit();
        } catch (error) {
          notify(error.message, 'error');
        } finally {
          submit.disabled = false;
        }
      }
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('active')) closeModal(); });
    const field = (id) => document.getElementById(id);

    function refreshAll() {
      return Promise.all([loadSummary(), loadAccounts(), loadInvoices(), selectedUserId ? loadAccount() : null]);
    }

    async function afterChange(message) {
      closeModal();
      notify(message);
      await refreshAll();
    }

    const methodOptions = (includeWallet) => '<option value="cash">Cash</option><option value="mobile_money">Mobile Money</option><option value="card">Card</option><option value="bank_transfer">Bank Transfer</option>' + (includeWallet ? '<option value="wallet">Customer wallet</option>' : '');

    function openAction(action) {
      if (!currentOverview) return;
      const {customer, account, outstanding, unbilled} = currentOverview;
      if (action === 'topup') {
        CtlPayments.openTopUp({userId: customer.user_id, name: customer.full_name, owed: outstanding.total, onDone: refreshAll});
      } else if (action === 'invoice') {
        const total = unbilled.reduce((sum, charge) => sum + Number(charge.total_amount), 0);
        openModal('Create invoice — ' + customer.full_name,
          '<div class="bill-form"><p style="margin:0">Bills <strong>' + unbilled.length + '</strong> unbilled charge' + (unbilled.length === 1 ? '' : 's') + ' totalling <strong>' + money(total) + '</strong>.</p>'
          + '<div class="row2"><div><label for="fDue">Due in (days)</label><input class="form-input" id="fDue" type="number" min="0" max="365" value="' + Number(account.payment_terms_days || 14) + '"></div>'
          + '<div><label for="fDiscount">Discount (' + CURRENCY + ')</label><input class="form-input" id="fDiscount" type="number" min="0" step="0.01" value="0"></div></div>'
          + '<div><label for="fNotes">Notes (printed on invoice)</label><textarea class="form-input" id="fNotes" rows="2" maxlength="500"></textarea></div></div>',
          'Create invoice', async () => {
            const result = await post({action: 'create_invoice', user_id: customer.user_id, due_days: Number(field('fDue').value), discount_amount: Number(field('fDiscount').value), notes: field('fNotes').value});
            await afterChange(result.message);
            viewInvoice(result.data.invoice_id);
          });
      } else if (action === 'settle') {
        openModal('Pay from wallet — ' + customer.full_name,
          '<p>Use the wallet balance of <strong>' + money(account.balance) + '</strong> to pay open charges and invoices (oldest first). Owed: <strong>' + money(outstanding.total) + '</strong>.</p>',
          'Pay now', async () => {
            const result = await post({action: 'settle_wallet', user_id: customer.user_id});
            await afterChange(result.message);
          });
      } else if (action === 'adjust') {
        openModal('Adjust balance — ' + customer.full_name,
          '<div class="bill-form"><div><label for="fAmount">Amount (' + CURRENCY + ')</label><input class="form-input" id="fAmount" type="number" step="0.01" required><div class="bill-hint">Positive adds credit, negative removes it.</div></div>'
          + '<div><label for="fReason">Reason</label><input class="form-input" id="fReason" maxlength="200" required></div></div>',
          'Apply adjustment', async () => {
            const result = await post({action: 'adjust', user_id: customer.user_id, amount: Number(field('fAmount').value), reason: field('fReason').value});
            await afterChange(result.message);
          });
      } else if (action === 'settings') {
        openModal('Account settings — ' + customer.full_name,
          '<div class="bill-form"><div class="row2"><div><label for="fLimit">Credit limit (' + CURRENCY + ')</label><input class="form-input" id="fLimit" type="number" min="0" step="0.01" value="' + Number(account.credit_limit) + '"><div class="bill-hint">Allows pay-later sales up to this amount. 0 disables pay-later.</div></div>'
          + '<div><label for="fTerms">Payment terms (days)</label><input class="form-input" id="fTerms" type="number" min="0" max="365" value="' + Number(account.payment_terms_days) + '"></div></div>'
          + '<div class="row2"><div><label for="fStatus">Account status</label><select class="form-input" id="fStatus"><option value="active">Active</option><option value="suspended">Suspended</option><option value="closed">Closed</option></select></div>'
          + '<div><label style="margin-top:28px;display:flex;gap:8px;align-items:center"><input type="checkbox" id="fAuto"' + (Number(account.auto_pay_sessions) ? ' checked' : '') + '> Auto-pay station sessions from wallet</label></div></div>'
          + '<div><label for="fNotes">Internal notes</label><textarea class="form-input" id="fNotes" rows="2" maxlength="1000">' + esc(account.notes || '') + '</textarea></div></div>',
          'Save settings', async () => {
            const result = await post({action: 'update_account', user_id: customer.user_id, credit_limit: Number(field('fLimit').value), payment_terms_days: Number(field('fTerms').value), status: field('fStatus').value, auto_pay_sessions: field('fAuto').checked, notes: field('fNotes').value});
            await afterChange(result.message);
          });
        field('fStatus').value = account.status;
      }
    }

    async function openPayment(invoiceId) {
      try {
        const {data: invoice} = await request(API + '?invoice_id=' + invoiceId);
        openModal('Record payment — ' + invoice.invoice_number,
          '<div class="bill-form"><p style="margin:0">' + esc(invoice.customer_name) + ' · Balance due <strong>' + money(invoice.balance_due) + '</strong></p>'
          + '<div class="row2"><div><label for="fAmount">Amount (' + CURRENCY + ')</label><input class="form-input" id="fAmount" type="number" min="0.01" step="0.01" value="' + Number(invoice.balance_due).toFixed(2) + '"></div>'
          + '<div><label for="fMethod">Method</label><select class="form-input" id="fMethod">' + methodOptions(true) + '</select></div></div>'
          + '<div><label for="fRef">Reference (optional)</label><input class="form-input" id="fRef" maxlength="100"></div></div>',
          'Record payment', async () => {
            const result = await post({action: 'record_payment', invoice_id: invoiceId, amount: Number(field('fAmount').value), payment_method: field('fMethod').value, reference: field('fRef').value});
            await afterChange(result.message);
          });
      } catch (error) {
        notify(error.message, 'error');
      }
    }

    function invoiceDocument(invoice) {
      const status = invoice.display_status;
      return '<div class="invoice-doc"><header><div><h3>' + esc(BUSINESS.name) + '</h3><div>' + esc(BUSINESS.address) + '</div><div>' + esc([BUSINESS.phone, BUSINESS.email].filter(Boolean).join(' · ')) + '</div></div>'
        + '<div style="text-align:right"><h3>INVOICE</h3><div><strong>' + esc(invoice.invoice_number) + '</strong></div><div>Issued ' + fmtDate(invoice.issue_date) + '</div><div>Due ' + fmtDate(invoice.due_date) + '</div><div style="margin-top:6px"><span class="stamp ' + esc(status) + '">' + esc(statusLabel(status)) + '</span></div></div></header>'
        + '<div><strong>Bill to</strong><br>' + esc(invoice.customer_name) + '<br>' + esc(invoice.account_number || '') + '<br>' + esc([invoice.customer_phone, invoice.customer_email].filter(Boolean).join(' · ')) + '</div>'
        + '<table><thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">Amount</th></tr></thead><tbody>'
        + invoice.items.map((item) => '<tr><td>' + esc(item.description) + '</td><td class="num">' + Number(item.quantity) + '</td><td class="num">' + money(item.unit_price) + '</td><td class="num">' + money(item.total_price) + '</td></tr>').join('')
        + '</tbody></table><div class="totals"><div><span>Subtotal</span><span>' + money(invoice.subtotal) + '</span></div>'
        + (Number(invoice.discount_amount) > 0 ? '<div><span>Discount</span><span>−' + money(invoice.discount_amount) + '</span></div>' : '')
        + '<div class="grand"><span>Total</span><span>' + money(invoice.total_amount) + '</span></div><div><span>Paid</span><span>' + money(invoice.amount_paid) + '</span></div>'
        + '<div class="grand"><span>Balance due</span><span>' + money(status === 'void' ? 0 : invoice.balance_due) + '</span></div></div>'
        + (invoice.payments.length ? '<p style="margin-top:16px"><strong>Payments</strong><br>' + invoice.payments.map((p) => fmtDateTime(p.created_at) + ' — ' + money(p.amount) + ' via ' + esc(String(p.payment_method).replace('_', ' ')) + (p.reference ? ' (' + esc(p.reference) + ')' : '')).join('<br>') + '</p>' : '')
        + (invoice.notes ? '<p style="margin-top:12px;white-space:pre-wrap"><strong>Notes</strong><br>' + esc(invoice.notes) + '</p>' : '')
        + '<p style="margin-top:18px;color:#64748b;font-size:12px">Thank you for your business.</p></div>';
    }

    async function viewInvoice(invoiceId) {
      try {
        const {data: invoice} = await request(API + '?invoice_id=' + invoiceId);
        const status = invoice.display_status;
        openModal('Invoice ' + invoice.invoice_number, invoiceDocument(invoice), null, null, true);
        const footer = document.getElementById('billModalFooter');
        if (CAN_MANAGE && ['issued', 'partially_paid', 'overdue'].includes(status)) {
          if (Number(invoice.amount_paid) === 0) footer.insertAdjacentHTML('afterbegin', '<button type="button" class="btn btn-danger" id="voidInvoiceBtn">Void</button>');
          footer.insertAdjacentHTML('beforeend', '<button type="button" class="btn btn-secondary" id="payInvoiceBtn">Record payment</button>');
          const payConfig = await CtlPayments.config('api/');
          if (payConfig.online_enabled) {
            footer.insertAdjacentHTML('beforeend', '<button type="button" class="btn btn-secondary" id="payOnlineBtn"><i class="fas fa-globe"></i> Pay online</button>');
            field('payOnlineBtn').addEventListener('click', () => {
              closeModal();
              CtlPayments.openOnlinePayment({userId: invoice.user_id, name: invoice.customer_name, amount: Number(invoice.balance_due), purpose: 'invoice', invoiceId: invoice.invoice_id, onDone: refreshAll});
            });
          }
        }
        footer.insertAdjacentHTML('beforeend', '<button type="button" class="btn btn-primary" id="printInvoiceBtn"><i class="fas fa-print"></i> Print</button>');
        field('printInvoiceBtn').addEventListener('click', () => {
          const area = field('printArea');
          area.innerHTML = invoiceDocument(invoice);
          area.hidden = false;
          window.print();
          area.hidden = true;
        });
        if (field('payInvoiceBtn')) field('payInvoiceBtn').addEventListener('click', () => openPayment(invoiceId));
        if (field('voidInvoiceBtn')) field('voidInvoiceBtn').addEventListener('click', () => {
          openModal('Void ' + invoice.invoice_number,
            '<div class="bill-form"><p style="margin:0">The charges return to the customer\'s unbilled list.</p><div><label for="fReason">Reason</label><input class="form-input" id="fReason" maxlength="200"></div></div>',
            'Void invoice', async () => {
              const result = await post({action: 'void_invoice', invoice_id: invoiceId, reason: field('fReason').value});
              await afterChange(result.message);
            });
        });
      } catch (error) {
        notify(error.message, 'error');
      }
    }

    const returned = new URLSearchParams(location.search).get('payment');
    if (returned) {
      const messages = {succeeded: ['Online payment confirmed and credited.', 'success'], pending: ['The online payment is still pending.', 'warning'], failed: ['The online payment failed.', 'error'], abandoned: ['The online payment was abandoned.', 'warning'], error: ['Could not confirm the online payment. Use “Check payment” to retry.', 'error']};
      const [text, type] = messages[returned] || messages.pending;
      setTimeout(() => notify(text, type), 400);
    }

    loadSummary();
    loadAccounts();
    loadInvoices();
    if (selectedUserId) loadAccount();
  })();
  </script>
</body>
</html>
