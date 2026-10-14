<?php
/**
 * Invoices — create, track, print and collect customer invoices.
 */

require_once __DIR__ . '/../includes/auth.php';
requireStaffAuth();
requirePermission('billing.view');

$canManage = hasPermission('billing.manage');
$business = [
    'name' => siteSetting('business_name', 'CTECHLIT & SYSTEMS'),
    'address' => siteSetting('contact_address', ''),
    'phone' => siteSetting('contact_phone', ''),
    'email' => siteSetting('contact_email', ''),
];
$customers = Database::fetchAll('SELECT user_id, full_name, phone FROM users WHERE is_active = 1 ORDER BY full_name');
$asset = static fn(string $path): string => '../' . $path . '?v=' . filemtime(__DIR__ . '/../' . $path);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoices — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <link rel="stylesheet" href="<?php echo $asset('assets/css/sessions.css'); ?>">
  <style>
    .inv-filter { display: flex; gap: 4px; padding: 4px; border-radius: 12px; background: rgba(148,163,184,0.14); flex-wrap: wrap; }
    .inv-filter button { padding: 7px 12px; border: 0; border-radius: 9px; background: transparent; font: 600 12.5px Inter, sans-serif; color: var(--ink-secondary); cursor: pointer; }
    .inv-filter button[aria-pressed="true"] { background: #fff; color: var(--violet); box-shadow: 0 2px 6px rgba(15,23,42,0.08); }
    .inv-num { font-weight: 700; color: var(--violet); }
    .inv-due-late { font-size: 11.5px; color: #DC2626; font-weight: 600; }
    .inv-due-soon { font-size: 11.5px; color: #B45309; }
    .inv-actions { display: flex; gap: 6px; justify-content: flex-end; }
    .inv-actions .header-btn { width: 32px; height: 32px; font-size: 12px; }
    .inv-view-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
    .inv-view-tools .inv-filter { margin-right: auto; }
    .inv-lines { display: grid; gap: 8px; }
    .inv-line { display: grid; grid-template-columns: 1fr 80px 110px 32px; gap: 8px; align-items: center; }
    .inv-line .form-input { padding: 8px 10px; font-size: 13px; }
    .inv-line button { border: 0; background: transparent; color: #DC2626; cursor: pointer; }
    .inv-charges { max-height: 240px; overflow-y: auto; border: 1px solid rgba(148,163,184,0.3); border-radius: 12px; }
    .inv-charges label { display: grid; grid-template-columns: 22px 1fr auto; gap: 10px; align-items: center; padding: 9px 12px; border-bottom: 1px solid rgba(148,163,184,0.18); font-size: 13px; cursor: pointer; }
    .inv-charges label:last-child { border-bottom: 0; }
    .inv-charges small { display: block; color: var(--muted); font-size: 11.5px; }
    .modal.modal-xl { max-width: 900px; }
    @media (max-width: 640px) { .inv-line { grid-template-columns: 1fr 60px 90px 28px; } }
  </style>
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content">
      <div class="ses-top">
        <div>
          <h1>Invoices</h1>
          <p>Bill customers for unpaid charges or custom work, then track payment</p>
        </div>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" id="newInvoiceBtn" type="button"><i class="fas fa-plus"></i> New invoice</button>
        <?php endif; ?>
      </div>

      <div class="ses-stats">
        <div class="ses-stat"><span>Outstanding</span><strong id="kOpen">—</strong></div>
        <div class="ses-stat bad"><span id="kOverdueLabel">Overdue</span><strong id="kOverdue">—</strong></div>
        <div class="ses-stat warn"><span>Not yet invoiced</span><strong id="kUnbilled">—</strong></div>
        <div class="ses-stat ok"><span>Collected this month</span><strong id="kCollected">—</strong></div>
      </div>

      <div class="card glass-card">
        <form class="hist-filters" id="filters">
          <div><span class="form-label" style="font-size:12px;color:var(--muted);font-weight:600">Status</span>
            <div class="inv-filter" role="group" aria-label="Invoice status">
              <button type="button" data-status="open" aria-pressed="true">Open</button>
              <button type="button" data-status="overdue" aria-pressed="false">Overdue</button>
              <button type="button" data-status="paid" aria-pressed="false">Paid</button>
              <button type="button" data-status="void" aria-pressed="false">Void</button>
              <button type="button" data-status="" aria-pressed="false">All</button>
            </div>
          </div>
          <div style="flex:1 1 200px"><label for="fSearch">Search</label><input class="form-input" id="fSearch" type="search" placeholder="Invoice #, customer or phone"></div>
          <div><label for="fFrom">Issued from</label><input class="form-input" id="fFrom" type="date"></div>
          <div><label for="fTo">to</label><input class="form-input" id="fTo" type="date"></div>
        </form>
        <div class="table-container" style="border:none;background:transparent">
          <table class="data-table">
            <thead><tr><th>Invoice</th><th>Customer</th><th>Issued</th><th>Due</th><th style="text-align:right">Total</th><th style="text-align:right">Balance</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody id="invTable"><tr><td colspan="8" style="text-align:center;color:var(--muted);padding:28px">Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <div class="modal-overlay" id="invModal" role="dialog" aria-modal="true" aria-labelledby="invModalTitle">
    <div class="modal" id="invModalBox">
      <div class="modal-header">
        <h3 class="modal-title" id="invModalTitle">Invoice</h3>
        <button class="modal-close" type="button" data-close aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body" id="invModalBody"></div>
      <div class="modal-footer" id="invModalFooter"></div>
    </div>
  </div>

  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script src="<?php echo $asset('assets/js/invoice-doc.js'); ?>"></script>
  <script>
  (function () {
    const API = 'api/billing.php';
    const CAN_MANAGE = <?php echo $canManage ? 'true' : 'false'; ?>;
    const BUSINESS = <?php echo json_encode($business); ?>;
    const CUSTOMERS = <?php echo json_encode(array_map(fn($c) => ['id' => (int) $c['user_id'], 'name' => $c['full_name'], 'phone' => $c['phone']], $customers)); ?>;
    const CURRENCY = window.CTL_CURRENCY || 'GHS';
    const $ = (id) => document.getElementById(id);
    const esc = (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; };
    const money = (v) => CURRENCY + ' ' + Number(v || 0).toLocaleString('en-GH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const fmtDate = (v) => v ? new Date(/^\d{4}-\d{2}-\d{2}$/.test(String(v)) ? v + 'T00:00:00' : String(v).replace(' ', 'T')).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'}) : '—';
    const label = (s) => String(s || '').replace('_', ' ').replace(/^./, (c) => c.toUpperCase());
    const badge = {paid: 'success', overdue: 'error', void: 'info', partially_paid: 'warning', issued: 'warning'};
    const notify = (m, t) => ModalController.showNotification(m, t || 'success');
    let status = 'open';
    let rows = [];
    let template = 'classic';
    try { template = localStorage.getItem('invoiceTemplate') || 'classic'; } catch (e) { /* storage blocked */ }

    async function request(url, options) {
      const response = await fetch(url, Object.assign({credentials: 'same-origin', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}}, options || {}));
      let result = {};
      try { result = await response.json(); } catch (e) { /* ignore */ }
      if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Request failed (HTTP ' + response.status + ')');
      return result;
    }
    const post = (payload) => request(API, {method: 'POST', body: JSON.stringify(payload)});

    // ---------- Summary & list ----------
    async function loadSummary() {
      try {
        const {data} = await request(API + '?summary=1');
        $('kOpen').textContent = money(data.receivable_total);
        $('kOverdue').textContent = money(data.overdue_total);
        $('kOverdueLabel').textContent = 'Overdue · ' + data.overdue_count;
        $('kUnbilled').textContent = money(data.unbilled_total);
        $('kCollected').textContent = money(data.collected_this_month);
      } catch (error) { /* list shows the error */ }
    }

    function dueNote(inv) {
      if (!['issued', 'partially_paid', 'overdue'].includes(inv.display_status)) return '';
      const days = Math.round((new Date(inv.due_date + 'T00:00:00') - new Date(new Date().toDateString())) / 86400000);
      if (days < 0) return '<div class="inv-due-late">' + Math.abs(days) + ' day' + (days === -1 ? '' : 's') + ' overdue</div>';
      if (days <= 3) return '<div class="inv-due-soon">' + (days === 0 ? 'Due today' : 'Due in ' + days + ' day' + (days === 1 ? '' : 's')) + '</div>';
      return '';
    }

    async function load() {
      const params = new URLSearchParams({invoices: '1'});
      if (status) params.set('status', status);
      [['search', 'fSearch'], ['from', 'fFrom'], ['to', 'fTo']].forEach(([key, id]) => { if ($(id).value.trim()) params.set(key, $(id).value.trim()); });
      try {
        const {data} = await request(API + '?' + params.toString());
        rows = data;
        $('invTable').innerHTML = rows.length ? rows.map((inv) => {
          const open = ['issued', 'partially_paid', 'overdue'].includes(inv.display_status);
          return '<tr data-id="' + inv.invoice_id + '">'
            + '<td><button type="button" class="inv-num" data-act="view" style="border:0;background:none;padding:0;cursor:pointer;font:inherit;font-weight:700">' + esc(inv.invoice_number) + '</button></td>'
            + '<td><a href="billing.php?user_id=' + inv.user_id + '">' + esc(inv.customer_name) + '</a></td>'
            + '<td>' + fmtDate(inv.issue_date) + '</td>'
            + '<td>' + fmtDate(inv.due_date) + dueNote(inv) + '</td>'
            + '<td class="amount" style="text-align:right">' + money(inv.total_amount) + '</td>'
            + '<td class="amount' + (open ? ' owed' : '') + '" style="text-align:right">' + (inv.display_status === 'void' ? '—' : money(inv.balance_due)) + '</td>'
            + '<td><span class="status-badge ' + (badge[inv.display_status] || 'info') + '"><span class="status-dot"></span>' + esc(label(inv.display_status)) + '</span></td>'
            + '<td><div class="inv-actions">'
            + '<button class="header-btn" type="button" data-act="view" title="View & print" aria-label="View ' + esc(inv.invoice_number) + '"><i class="fas fa-eye"></i></button>'
            + '<button class="header-btn" type="button" data-act="print" title="Print / PDF" aria-label="Print ' + esc(inv.invoice_number) + '"><i class="fas fa-print"></i></button>'
            + (CAN_MANAGE && open ? '<button class="header-btn" type="button" data-act="pay" title="Record payment" aria-label="Record payment" style="color:var(--green)"><i class="fas fa-money-bill-wave"></i></button>' : '')
            + '</div></td></tr>';
        }).join('') : '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:32px">' + (status === 'open' ? 'No open invoices — everything is paid.' : 'No invoices match these filters.') + '</td></tr>';
      } catch (error) {
        $('invTable').innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--red);padding:28px">' + esc(error.message) + '</td></tr>';
      }
    }

    function refresh() { return Promise.all([loadSummary(), load()]); }

    document.querySelectorAll('[data-status]').forEach((button) => button.addEventListener('click', () => {
      status = button.dataset.status;
      document.querySelectorAll('[data-status]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
      load();
    }));
    let timer = null;
    $('filters').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 250); });
    $('filters').addEventListener('submit', (e) => e.preventDefault());

    // ---------- Modal ----------
    const modal = $('invModal');
    function openModal(title, body, footer, wide) {
      $('invModalTitle').textContent = title;
      $('invModalBody').innerHTML = body;
      $('invModalFooter').innerHTML = footer;
      $('invModalBox').classList.toggle('modal-xl', Boolean(wide));
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
    function closeModal() { modal.classList.remove('active'); document.body.style.overflow = ''; }
    modal.addEventListener('click', (e) => { if (e.target === modal || e.target.closest('[data-close]')) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('active') && !document.querySelector('.ctl-dlg-backdrop')) closeModal(); });

    async function fetchInvoice(id) { return (await request(API + '?invoice_id=' + id)).data; }

    // ---------- View ----------
    async function view(id) {
      let inv;
      try { inv = await fetchInvoice(id); } catch (error) { notify(error.message, 'error'); return; }
      const st = inv.display_status;
      const open = ['issued', 'partially_paid', 'overdue'].includes(st);
      const tools = '<div class="inv-view-tools"><div class="inv-filter" role="group" aria-label="Template">'
        + InvoiceDoc.templates.map((t) => '<button type="button" data-template="' + t + '" aria-pressed="' + (t === template) + '">' + label(t) + '</button>').join('') + '</div></div>';
      const footer = [];
      if (CAN_MANAGE && open && Number(inv.amount_paid) === 0) footer.push('<button class="btn btn-danger" type="button" data-do="void">Void</button>');
      if (CAN_MANAGE && open) {
        const pay = await CtlPayments.config('api/');
        if (pay.online_enabled) footer.push('<button class="btn btn-secondary" type="button" data-do="online"><i class="fas fa-globe"></i> Pay online</button>');
        footer.push('<button class="btn btn-secondary" type="button" data-do="pay"><i class="fas fa-money-bill-wave"></i> Record payment</button>');
      }
      footer.push('<button class="btn btn-primary" type="button" data-do="print"><i class="fas fa-print"></i> Print / PDF</button>');
      openModal(inv.invoice_number + ' · ' + inv.customer_name, tools + '<div id="invDoc">' + InvoiceDoc.render(inv, BUSINESS, template) + '</div>', footer.join(''), true);

      $('invModalBody').querySelectorAll('[data-template]').forEach((b) => b.addEventListener('click', () => {
        template = b.dataset.template;
        try { localStorage.setItem('invoiceTemplate', template); } catch (e) { /* ignore */ }
        $('invModalBody').querySelectorAll('[data-template]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
        $('invDoc').innerHTML = InvoiceDoc.render(inv, BUSINESS, template);
      }));
      $('invModalFooter').querySelectorAll('[data-do]').forEach((b) => b.addEventListener('click', () => {
        const action = b.dataset.do;
        if (action === 'print') InvoiceDoc.print(inv, BUSINESS, template);
        if (action === 'pay') payment(inv);
        if (action === 'online') { closeModal(); CtlPayments.openOnlinePayment({userId: inv.user_id, name: inv.customer_name, amount: Number(inv.balance_due), purpose: 'invoice', invoiceId: inv.invoice_id, onDone: refresh}); }
        if (action === 'void') voidInvoice(inv);
      }));
    }

    // ---------- Payment ----------
    async function payment(inv) {
      if (typeof inv === 'number') {
        try { inv = await fetchInvoice(inv); } catch (error) { notify(error.message, 'error'); return; }
      }
      const methods = [['cash', 'fa-money-bill-wave', 'Cash'], ['mobile_money', 'fa-mobile-screen', 'MoMo'], ['card', 'fa-credit-card', 'Card'], ['bank_transfer', 'fa-building-columns', 'Bank'], ['wallet', 'fa-wallet', 'Wallet']];
      openModal('Record payment — ' + inv.invoice_number,
        '<div class="ses-form"><div class="ses-receipt"><div><span>Customer</span><strong>' + esc(inv.customer_name) + '</strong></div>'
        + '<div><span>Invoice total</span><strong>' + money(inv.total_amount) + '</strong></div>'
        + '<div class="big"><span>Balance due</span><strong>' + money(inv.balance_due) + '</strong></div></div>'
        + '<div><label class="form-label" for="pAmount">Amount received</label><input class="form-input" id="pAmount" type="number" min="0.01" step="0.01" max="' + Number(inv.balance_due) + '" value="' + Number(inv.balance_due).toFixed(2) + '"></div>'
        + '<div><span class="form-label">Method</span><div class="ses-methods">' + methods.map(([v, i, l], n) => '<label><input type="radio" name="pMethod" value="' + v + '"' + (n === 0 ? ' checked' : '') + '><i class="fas ' + i + '"></i>' + l + '</label>').join('') + '</div></div>'
        + '<div><label class="form-label" for="pRef">Reference (optional)</label><input class="form-input" id="pRef" maxlength="100"></div></div>',
        '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="pSubmit"><i class="fas fa-check"></i> Record payment</button>');
      $('pAmount').focus();
      $('pSubmit').addEventListener('click', async (e) => {
        e.target.disabled = true;
        try {
          const result = await post({action: 'record_payment', invoice_id: inv.invoice_id, amount: Number($('pAmount').value), payment_method: modal.querySelector('input[name=pMethod]:checked').value, reference: $('pRef').value});
          closeModal();
          notify(result.message);
          refresh();
        } catch (error) { notify(error.message, 'error'); } finally { e.target.disabled = false; }
      });
    }

    async function voidInvoice(inv) {
      const reason = await CtlDialog.prompt({
        title: 'Void ' + inv.invoice_number + '?', message: 'Its charges go back to the customer’s unbilled list so they can be re-invoiced.',
        label: 'Reason', presets: ['Created by mistake', 'Wrong customer', 'Amount was wrong'], tone: 'danger', icon: 'fa-file-circle-xmark', confirmText: 'Void invoice'
      });
      if (reason === null) return;
      try {
        const result = await post({action: 'void_invoice', invoice_id: inv.invoice_id, reason});
        closeModal();
        notify(result.message);
        refresh();
      } catch (error) { notify(error.message, 'error'); }
    }

    $('invTable').addEventListener('click', async (event) => {
      const button = event.target.closest('[data-act]');
      if (!button) return;
      const id = Number(button.closest('tr').dataset.id);
      if (button.dataset.act === 'view') view(id);
      if (button.dataset.act === 'pay') payment(id);
      if (button.dataset.act === 'print') {
        try { InvoiceDoc.print(await fetchInvoice(id), BUSINESS, template); } catch (error) { notify(error.message, 'error'); }
      }
    });

    // ---------- New invoice ----------
    const newBtn = $('newInvoiceBtn');
    if (newBtn) newBtn.addEventListener('click', () => {
      if (!CUSTOMERS.length) { CtlDialog.alert({title: 'No customers yet', message: 'Add the customer on the Customers page first.', tone: 'warning'}); return; }
      openModal('New invoice',
        '<div class="ses-form">'
        + '<div><label class="form-label" for="nCustomer">Customer</label><select class="form-input" id="nCustomer"><option value="">Choose customer…</option>'
        + CUSTOMERS.map((c) => '<option value="' + c.id + '">' + esc(c.name + (c.phone ? ' · ' + c.phone : '')) + '</option>').join('') + '</select></div>'
        + '<div><span class="form-label">Invoice for</span><div class="ses-segment">'
        + '<label><input type="radio" name="nMode" value="charges" checked> <strong>Unpaid charges</strong><small>Sessions & pay-later sales</small></label>'
        + '<label><input type="radio" name="nMode" value="custom"> <strong>Custom items</strong><small>Typing, design, services…</small></label></div></div>'
        + '<div data-mode="charges"><div class="inv-charges" id="nCharges"><div style="padding:14px;color:var(--muted);font-size:13px">Choose a customer to see their unpaid charges.</div></div></div>'
        + '<div data-mode="custom" hidden><div class="inv-lines" id="nLines"></div><button type="button" class="btn btn-secondary small" id="nAddLine" style="margin-top:8px"><i class="fas fa-plus"></i> Add line</button></div>'
        + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div><label class="form-label" for="nDue">Due in (days)</label><input class="form-input" id="nDue" type="number" min="0" max="365" value="14"></div>'
        + '<div><label class="form-label" for="nDiscount">Discount (' + esc(CURRENCY) + ')</label><input class="form-input" id="nDiscount" type="number" min="0" step="0.01" value="0"></div></div>'
        + '<div><label class="form-label" for="nNotes">Notes on invoice (optional)</label><textarea class="form-input" id="nNotes" rows="2" maxlength="500"></textarea></div>'
        + '<div class="ses-total" id="nTotal"></div></div>',
        '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="nSubmit"><i class="fas fa-file-invoice"></i> Create invoice</button>');

      let charges = [];
      const mode = () => modal.querySelector('input[name=nMode]:checked').value;
      const addLine = (desc, qty, price) => {
        const row = document.createElement('div');
        row.className = 'inv-line';
        row.innerHTML = '<input class="form-input" placeholder="Description" maxlength="255" aria-label="Description" value="' + esc(desc || '') + '">'
          + '<input class="form-input" type="number" min="0.01" step="0.01" value="' + (qty || 1) + '" aria-label="Quantity">'
          + '<input class="form-input" type="number" min="0" step="0.01" placeholder="Price" value="' + (price != null ? price : '') + '" aria-label="Unit price">'
          + '<button type="button" aria-label="Remove line"><i class="fas fa-trash-can"></i></button>';
        row.querySelector('button').addEventListener('click', () => { row.remove(); total(); });
        row.addEventListener('input', total);
        $('nLines').appendChild(row);
      };
      const total = () => {
        let sum = 0;
        if (mode() === 'charges') {
          modal.querySelectorAll('#nCharges input:checked').forEach((box) => { sum += Number(box.dataset.amount); });
        } else {
          $('nLines').querySelectorAll('.inv-line').forEach((row) => {
            const [, q, p] = row.querySelectorAll('input');
            sum += (Number(q.value) || 0) * (Number(p.value) || 0);
          });
        }
        const discount = Math.min(sum, Math.max(0, Number($('nDiscount').value) || 0));
        $('nTotal').innerHTML = 'Invoice total: <strong>' + money(sum - discount) + '</strong>' + (discount ? ' (after ' + money(discount) + ' discount)' : '');
      };
      const loadCharges = async () => {
        const id = Number($('nCustomer').value);
        if (!id) return;
        $('nCharges').innerHTML = '<div style="padding:14px;color:var(--muted);font-size:13px">Loading…</div>';
        try {
          const {data} = await request(API + '?user_id=' + id);
          charges = data.unbilled;
          $('nDue').value = data.account.payment_terms_days || 14;
          $('nCharges').innerHTML = charges.length ? charges.map((c) => '<label><input type="checkbox" checked data-id="' + c.transaction_id + '" data-amount="' + c.total_amount + '">'
            + '<span>' + esc(c.description) + '<small>' + fmtDate(c.created_at) + (c.payment_reference ? ' · ' + esc(c.payment_reference) : '') + '</small></span><strong class="amount">' + money(c.total_amount) + '</strong></label>').join('')
            : '<div style="padding:14px;color:var(--muted);font-size:13px">This customer has no unpaid charges. Switch to <strong>Custom items</strong> to bill for something else.</div>';
        } catch (error) {
          $('nCharges').innerHTML = '<div style="padding:14px;color:var(--red);font-size:13px">' + esc(error.message) + '</div>';
        }
        total();
      };
      addLine();
      $('nCustomer').addEventListener('change', loadCharges);
      $('nCharges').addEventListener('change', total);
      $('nDiscount').addEventListener('input', total);
      $('nAddLine').addEventListener('click', () => { addLine(); total(); });
      modal.querySelectorAll('input[name=nMode]').forEach((r) => r.addEventListener('change', () => {
        modal.querySelectorAll('[data-mode]').forEach((node) => { node.hidden = node.dataset.mode !== mode(); });
        total();
      }));
      total();

      $('nSubmit').addEventListener('click', async (e) => {
        const userId = Number($('nCustomer').value);
        if (!userId) { notify('Choose a customer.', 'error'); return; }
        const common = {user_id: userId, due_days: Number($('nDue').value), discount_amount: Number($('nDiscount').value), notes: $('nNotes').value};
        let payload;
        if (mode() === 'charges') {
          const ids = [...modal.querySelectorAll('#nCharges input:checked')].map((b) => Number(b.dataset.id));
          if (!ids.length) { notify('Tick at least one charge, or switch to custom items.', 'error'); return; }
          payload = Object.assign({action: 'create_invoice', transaction_ids: ids}, common);
        } else {
          const items = [...$('nLines').querySelectorAll('.inv-line')].map((row) => {
            const [d, q, p] = row.querySelectorAll('input');
            return {description: d.value.trim(), quantity: Number(q.value), unit_price: Number(p.value)};
          }).filter((i) => i.description || i.unit_price);
          if (!items.length) { notify('Add at least one line item.', 'error'); return; }
          payload = Object.assign({action: 'create_manual_invoice', items}, common);
        }
        e.target.disabled = true;
        try {
          const result = await post(payload);
          closeModal();
          notify(result.message);
          await refresh();
          view(result.data.invoice_id);
        } catch (error) { notify(error.message, 'error'); } finally { e.target.disabled = false; }
      });
    });

    refresh();
    const focus = new URLSearchParams(location.search).get('invoice_id');
    if (focus) view(Number(focus));
  })();
  </script>
</body>
</html>
