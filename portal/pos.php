<?php
/**
 * POS Register — modern cart-based counter for services, time packages and products.
 */

require_once __DIR__ . '/../includes/auth.php';
requireStaffAuth();
requirePermission('pos.view');

$currentStaff = currentStaff();
$canSell = hasPermission('pos.manage');
$canBilling = hasPermission('billing.view');
$currency = siteSetting('currency_symbol', 'GHS');
$business = [
    'name' => siteSetting('business_name', 'CTECHLIT & SYSTEMS'),
    'address' => siteSetting('contact_address', ''),
    'phone' => siteSetting('contact_phone', ''),
];
$posTerminal = Database::fetchOne('SELECT * FROM pos_terminals WHERE staff_id = ? AND is_active = 1 LIMIT 1', [$currentStaff['staff_id']]);
$printers = Database::fetchAll(
    "SELECT p.peripheral_id, p.name, p.status, p.config, s.station_code
     FROM peripherals p LEFT JOIN stations s ON s.station_id = p.station_id
     WHERE p.peripheral_type = 'printer' ORDER BY p.name"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS Register — CTECHLIT</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <style>
    .reg-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
    .reg-top h1 { margin: 0; font-size: 26px; color: var(--ink); letter-spacing: -0.02em; }
    .reg-top p { margin: 4px 0 0; color: var(--ink-secondary); font-size: 13px; }
    .reg-stats { display: flex; flex-wrap: wrap; gap: 10px; }
    .reg-stat { min-width: 120px; padding: 10px 14px; border-radius: 14px; background: rgba(255,255,255,0.7); border: 1px solid rgba(148,163,184,0.2); }
    .reg-stat span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); }
    .reg-stat strong { font-size: 18px; color: var(--ink); font-variant-numeric: tabular-nums; }
    .reg-tabs { display: flex; gap: 4px; padding: 4px; margin-bottom: 16px; border-radius: 12px; background: rgba(148,163,184,0.14); width: fit-content; }
    .reg-tabs button { padding: 9px 16px; border: 0; border-radius: 9px; background: transparent; font: 600 13px Inter, sans-serif; color: var(--ink-secondary); cursor: pointer; }
    .reg-tabs button[aria-selected="true"] { background: #fff; color: var(--violet); box-shadow: 0 2px 8px rgba(15,23,42,0.08); }
    .register { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(340px, 1fr); gap: 20px; align-items: start; }
    .catalog-bar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
    .catalog-search { position: relative; flex: 1 1 240px; }
    .catalog-search i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); }
    .catalog-search input { padding-left: 38px; }
    .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
    .chip { padding: 7px 13px; border: 1px solid rgba(148,163,184,0.3); border-radius: 999px; background: rgba(255,255,255,0.7); font: 600 12.5px Inter, sans-serif; color: var(--ink-secondary); cursor: pointer; }
    .chip[aria-pressed="true"] { background: var(--violet); border-color: var(--violet); color: #fff; }
    .tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
    .tile { position: relative; display: flex; flex-direction: column; gap: 8px; min-height: 118px; padding: 14px; border: 1px solid rgba(148,163,184,0.22); border-radius: 16px; background: rgba(255,255,255,0.78); text-align: left; cursor: pointer; font: inherit; color: var(--ink); transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease; }
    .tile:hover:not(:disabled) { transform: translateY(-2px); border-color: rgba(124,58,237,0.45); box-shadow: 0 10px 24px rgba(124,58,237,0.12); }
    .tile:active:not(:disabled) { transform: scale(0.98); }
    .tile:disabled { opacity: 0.45; cursor: not-allowed; }
    .tile-icon { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 11px; color: #fff; font-size: 15px; }
    .tile-name { font-weight: 600; font-size: 13.5px; line-height: 1.3; }
    .tile-price { margin-top: auto; font-weight: 800; font-size: 15px; font-variant-numeric: tabular-nums; }
    .tile-price small { font-weight: 500; color: var(--muted); font-size: 11.5px; }
    .tile-badge { position: absolute; top: 10px; right: 10px; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700; background: rgba(16,185,129,0.12); color: #059669; }
    .tile-badge.low { background: rgba(245,158,11,0.15); color: #B45309; }
    .tile-badge.out { background: rgba(239,68,68,0.12); color: #DC2626; }
    .stations-board { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
    .station-chip { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; padding: 11px 12px; border-radius: 13px; border: 1px solid rgba(148,163,184,0.25); background: rgba(255,255,255,0.75); text-align: left; font: inherit; color: var(--ink); cursor: pointer; }
    .station-chip strong { display: block; font-size: 13px; line-height: 1.3; }
    .station-chip span { display: block; font-size: 11.5px; color: var(--muted); }
    .station-chip.free { border-left: 4px solid #10B981; }
    .station-chip.busy { border-left: 4px solid #F59E0B; }
    .station-chip.down { border-left: 4px solid #94A3B8; opacity: 0.7; }
    .station-chip.selected { outline: 2px solid var(--violet); outline-offset: 1px; }
    .ticket { position: sticky; top: calc(var(--header-height) + 16px); display: flex; flex-direction: column; max-height: calc(100vh - var(--header-height) - 32px); padding: 0; overflow: hidden; }
    .ticket-section { padding: 16px 18px; border-bottom: 1px solid rgba(148,163,184,0.16); }
    .ticket-label { display: block; margin-bottom: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); }
    .cust-box { position: relative; }
    .cust-results { position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; right: 0; display: none; max-height: 260px; overflow-y: auto; padding: 6px; border-radius: 12px; background: #fff; box-shadow: 0 16px 40px rgba(15,23,42,0.16); }
    .cust-results.open { display: block; }
    .cust-results button { display: block; width: 100%; padding: 9px 10px; border: 0; border-radius: 8px; background: transparent; text-align: left; font: inherit; cursor: pointer; }
    .cust-results button:hover, .cust-results button:focus { background: rgba(124,58,237,0.08); }
    .cust-results small { display: block; color: var(--muted); font-size: 11.5px; }
    .cust-card { display: flex; align-items: center; gap: 10px; margin-top: 10px; padding: 10px 12px; border-radius: 12px; background: rgba(124,58,237,0.07); }
    .cust-card .who { flex: 1; min-width: 0; }
    .cust-card .who strong { display: block; font-size: 14px; }
    .cust-card .who span { font-size: 12px; color: var(--ink-secondary); }
    .cust-card button { border: 0; background: transparent; color: var(--muted); cursor: pointer; }
    .lines { flex: 1 1 auto; min-height: 120px; overflow-y: auto; padding: 8px 18px; }
    .line { display: grid; grid-template-columns: 1fr auto; gap: 6px 10px; padding: 11px 0; border-bottom: 1px dashed rgba(148,163,184,0.3); }
    .line-name { font-weight: 600; font-size: 13.5px; }
    .line-sub { font-size: 12px; color: var(--muted); }
    .line-total { font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; }
    .qty { display: inline-flex; align-items: center; gap: 4px; }
    .qty button { width: 26px; height: 26px; border: 1px solid rgba(148,163,184,0.35); border-radius: 8px; background: #fff; cursor: pointer; color: var(--ink); }
    .qty input { width: 52px; padding: 3px 4px; border: 1px solid rgba(148,163,184,0.35); border-radius: 7px; text-align: center; font: 600 13px Inter, sans-serif; }
    .line-remove { border: 0; background: transparent; color: #EF4444; cursor: pointer; justify-self: end; }
    .ticket-empty { padding: 30px 10px; text-align: center; color: var(--muted); font-size: 13px; }
    .totals-row { display: flex; justify-content: space-between; align-items: center; padding: 3px 0; font-size: 13.5px; color: var(--ink-secondary); font-variant-numeric: tabular-nums; }
    .totals-row.grand { padding-top: 8px; font-size: 22px; font-weight: 800; color: var(--ink); }
    .discount-input { width: 110px; padding: 5px 8px; border: 1px solid rgba(148,163,184,0.35); border-radius: 8px; text-align: right; font: inherit; }
    .pay-methods { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
    .pay-method { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 9px 4px; border: 1px solid rgba(148,163,184,0.3); border-radius: 11px; background: #fff; font: 600 12px Inter, sans-serif; color: var(--ink-secondary); cursor: pointer; }
    .pay-method i { font-size: 15px; }
    .pay-method[aria-pressed="true"] { border-color: var(--violet); background: rgba(124,58,237,0.08); color: var(--violet); }
    .pay-method:disabled { opacity: 0.4; cursor: not-allowed; }
    .cash-quick { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .cash-quick button { padding: 6px 10px; border: 1px solid rgba(148,163,184,0.35); border-radius: 8px; background: #fff; font: 600 12px Inter, sans-serif; cursor: pointer; }
    .change-line { margin-top: 8px; font-size: 14px; font-weight: 700; color: #059669; }
    .charge-btn { width: 100%; padding: 15px; border: 0; border-radius: 14px; background: linear-gradient(135deg, #7C3AED, #2563EB); color: #fff; font: 800 16px Inter, sans-serif; letter-spacing: 0.01em; cursor: pointer; box-shadow: 0 12px 26px rgba(76,64,220,0.35); }
    .charge-btn:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }
    .reg-msg { min-height: 18px; margin-top: 8px; font-size: 12.5px; text-align: center; }
    .reg-msg.error { color: #DC2626; }
    .receipt { width: 300px; margin: 0 auto; padding: 18px; background: #fff; color: #111; font: 12.5px/1.45 'Courier New', monospace; border-radius: 8px; }
    .receipt h4 { margin: 0; text-align: center; font-size: 15px; }
    .receipt .c { text-align: center; }
    .receipt hr { border: 0; border-top: 1px dashed #333; margin: 8px 0; }
    .receipt .r { display: flex; justify-content: space-between; gap: 8px; }
    .receipt .big { font-weight: 700; font-size: 14px; }
    .print-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .kbd { padding: 1px 6px; border: 1px solid rgba(148,163,184,0.5); border-radius: 5px; font-size: 11px; }
    @media (max-width: 1100px) { .register { grid-template-columns: 1fr; } .ticket { position: static; max-height: none; } .print-grid { grid-template-columns: 1fr; } }
    @media print {
      body * { visibility: hidden !important; }
      #receiptPrint, #receiptPrint * { visibility: visible !important; }
      #receiptPrint { position: absolute; left: 0; top: 0; }
    }
  </style>
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content">
      <div class="reg-top">
        <div>
          <h1>Register</h1>
          <p><?php echo sanitize($posTerminal['terminal_name'] ?? 'POS-01'); ?> · <?php echo sanitize($currentStaff['full_name']); ?> · <span class="kbd">/</span> search · <span class="kbd">Ctrl</span>+<span class="kbd">Enter</span> charge</p>
        </div>
        <div class="reg-stats">
          <div class="reg-stat"><span>Today's sales</span><strong id="statToday">—</strong></div>
          <div class="reg-stat"><span>Transactions</span><strong id="statCount">—</strong></div>
          <div class="reg-stat"><span>On account</span><strong id="statAccount">—</strong></div>
          <div class="reg-stat"><span>Active sessions</span><strong id="statSessions">—</strong></div>
        </div>
      </div>

      <div class="reg-tabs" role="tablist">
        <button role="tab" aria-selected="true" data-view="register"><i class="fas fa-cash-register"></i> Register</button>
        <button role="tab" aria-selected="false" data-view="sales"><i class="fas fa-clock-rotate-left"></i> Recent sales</button>
        <button role="tab" aria-selected="false" data-view="print"><i class="fas fa-print"></i> Print desk</button>
      </div>

      <!-- REGISTER -->
      <section data-panel="register">
        <div class="register">
          <div>
            <div class="card glass-card" style="padding: 18px;">
              <div class="catalog-bar">
                <div class="catalog-search">
                  <i class="fas fa-search" aria-hidden="true"></i>
                  <label class="sr-only" for="catalogSearch">Search services and products</label>
                  <input id="catalogSearch" class="form-input" type="search" placeholder="Search or scan barcode…" autocomplete="off">
                </div>
                <?php if ($canSell): ?>
                <button class="btn btn-secondary" type="button" id="customItemBtn"><i class="fas fa-pen-to-square"></i> Custom item</button>
                <?php endif; ?>
              </div>
              <div class="chips" role="group" aria-label="Category">
                <button class="chip" data-cat="all" aria-pressed="true">All</button>
                <button class="chip" data-cat="time" aria-pressed="false">Internet &amp; Gaming</button>
                <button class="chip" data-cat="docs" aria-pressed="false">Printing &amp; Docs</button>
                <button class="chip" data-cat="plans" aria-pressed="false">Packages</button>
                <button class="chip" data-cat="products" aria-pressed="false">Products</button>
                <button class="chip" data-cat="other" aria-pressed="false">Other services</button>
              </div>
              <div class="tiles" id="tiles"><div class="ticket-empty">Loading catalog…</div></div>
            </div>

            <div class="card glass-card" style="padding: 18px; margin-top: 20px;">
              <div class="card-header" style="padding:0 0 12px;">
                <div>
                  <div class="card-title">Stations</div>
                  <div class="card-subtitle">Tap a free station to assign timed sales to it, or an occupied one to extend that customer</div>
                </div>
                <button class="btn btn-secondary small" type="button" id="refreshStations"><i class="fas fa-rotate"></i></button>
              </div>
              <div class="stations-board" id="stationsBoard"></div>
            </div>
          </div>

          <aside class="card glass-card ticket" aria-label="Current sale">
            <div class="ticket-section">
              <span class="ticket-label">Customer</span>
              <div class="cust-box">
                <label class="sr-only" for="custSearch">Find customer</label>
                <input id="custSearch" class="form-input" type="search" placeholder="Walk-in — or search name / phone" autocomplete="off">
                <div class="cust-results" id="custResults" role="listbox"></div>
              </div>
              <div id="custCard"></div>
            </div>

            <div class="ticket-section" id="stationSection" hidden>
              <label class="ticket-label" for="stationSelect">Station for timed use</label>
              <select class="form-input" id="stationSelect"></select>
            </div>

            <div class="lines" id="lines"><div class="ticket-empty"><i class="fas fa-basket-shopping" style="font-size:26px;opacity:.4;display:block;margin-bottom:8px"></i>Tap a service or product to start a sale.</div></div>

            <div class="ticket-section">
              <div class="totals-row"><span>Subtotal</span><span id="tSubtotal">—</span></div>
              <div class="totals-row"><label for="discountInput">Discount</label><input id="discountInput" class="discount-input" type="number" min="0" step="0.01" value="0"></div>
              <div class="totals-row grand"><span>Total</span><span id="tTotal">—</span></div>
            </div>

            <div class="ticket-section">
              <span class="ticket-label">Payment</span>
              <div class="pay-methods" role="group" aria-label="Payment method">
                <button class="pay-method" data-method="cash" aria-pressed="true"><i class="fas fa-money-bill-wave"></i>Cash</button>
                <button class="pay-method" data-method="mobile_money" aria-pressed="false"><i class="fas fa-mobile-screen"></i>MoMo</button>
                <button class="pay-method" data-method="card" aria-pressed="false"><i class="fas fa-credit-card"></i>Card</button>
                <button class="pay-method" data-method="bank_transfer" aria-pressed="false"><i class="fas fa-building-columns"></i>Bank</button>
                <button class="pay-method" data-method="wallet" aria-pressed="false" disabled><i class="fas fa-wallet"></i>Wallet</button>
                <button class="pay-method" data-method="account" aria-pressed="false" disabled><i class="fas fa-file-invoice"></i>On account</button>
              </div>
              <div id="payDetail" style="margin-top:10px"></div>
            </div>

            <div class="ticket-section" style="border-bottom:0">
              <button class="charge-btn" id="chargeBtn" type="button" disabled <?php echo $canSell ? '' : 'title="You need the Manage POS Sales permission"'; ?>>Charge</button>
              <div class="reg-msg" id="regMsg" aria-live="polite"></div>
            </div>
          </aside>
        </div>
      </section>

      <!-- RECENT SALES -->
      <section data-panel="sales" hidden>
        <div class="card glass-card">
          <div class="card-header">
            <div><div class="card-title">Recent sales</div><div class="card-subtitle">Latest 15 sales across all terminals</div></div>
          </div>
          <div class="table-container" style="border:none;background:transparent">
            <table class="data-table">
              <thead><tr><th>Receipt</th><th>Customer</th><th>Type</th><th>Station</th><th>Payment</th><th>Amount</th><th>Status</th><th>Time</th><th></th></tr></thead>
              <tbody id="salesTable"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- PRINT DESK -->
      <section data-panel="print" hidden>
        <div class="card glass-card">
          <div class="card-header">
            <div><div class="card-title">Print desk</div><div class="card-subtitle">Check network printers and queue print jobs in order</div></div>
          </div>
          <div class="print-grid">
            <div class="network-devices">
              <?php if (!$printers): ?><div class="ticket-empty">No printers registered. Add one under Print Jobs → Add Printer.</div><?php endif; ?>
              <?php foreach ($printers as $printer):
                $printerConfig = json_decode((string) ($printer['config'] ?? ''), true) ?: []; ?>
                <div class="network-device-card">
                  <div class="network-device-info">
                    <div class="network-device-name"><?php echo sanitize(($printer['station_code'] ?? 'Printer') . ' - ' . $printer['name']); ?></div>
                    <div class="network-device-meta" data-printer-status>Printer status: <?php echo $printer['status'] === 'online' ? 'Online' : 'Offline'; ?></div>
                    <div style="display:flex; gap:6px; margin-top:8px; flex-wrap:wrap;">
                      <label class="sr-only" for="printer-ip-<?php echo (int) $printer['peripheral_id']; ?>">Printer IP address</label>
                      <input id="printer-ip-<?php echo (int) $printer['peripheral_id']; ?>" type="text" inputmode="decimal" placeholder="Printer IP address" value="<?php echo htmlspecialchars((string) ($printerConfig['ip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-printer-ip class="form-input" style="max-width:160px; padding:6px 8px; font-size:12px;">
                      <label class="sr-only" for="printer-protocol-<?php echo (int) $printer['peripheral_id']; ?>">Printer protocol</label>
                      <select id="printer-protocol-<?php echo (int) $printer['peripheral_id']; ?>" data-printer-protocol class="form-input" style="max-width:90px; padding:6px 8px; font-size:12px;">
                        <option value="ipp" <?php echo ($printerConfig['protocol'] ?? 'ipp') === 'ipp' ? 'selected' : ''; ?>>IPP</option>
                        <option value="lpd" <?php echo ($printerConfig['protocol'] ?? '') === 'lpd' ? 'selected' : ''; ?>>LPD</option>
                      </select>
                    </div>
                    <div data-printer-message aria-live="polite" style="font-size:11px; margin-top:5px;"></div>
                  </div>
                  <button type="button" class="btn btn-secondary small" data-printer-check="<?php echo (int) $printer['peripheral_id']; ?>">Check</button>
                </div>
              <?php endforeach; ?>
            </div>
            <form id="printCommandForm" class="print-command-grid">
              <div>
                <label for="print_printer_id" class="form-label">Printer</label>
                <select id="print_printer_id" name="peripheral_id" class="form-input" required>
                  <option value="">Select printer</option>
                  <?php foreach ($printers as $printer): ?>
                    <option value="<?php echo (int) $printer['peripheral_id']; ?>"><?php echo sanitize(($printer['station_code'] ?? 'Printer') . ' - ' . $printer['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="print-row">
                <div><label for="print_file" class="form-label">File</label><input id="print_file" name="file" type="file" class="form-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.jpg,.jpeg,.png" required></div>
                <div><label for="print_priority" class="form-label">Priority</label><input id="print_priority" name="priority" type="number" min="0" max="10" class="form-input" value="1"></div>
              </div>
              <div class="print-row">
                <div><label for="print_pages" class="form-label">Pages</label><input id="print_pages" name="pages" type="number" min="1" class="form-input" value="1" required></div>
                <div><label for="print_copies" class="form-label">Copies</label><input id="print_copies" name="copies" type="number" min="1" class="form-input" value="1" required></div>
              </div>
              <div class="print-row">
                <div><label for="print_color_mode" class="form-label">Color</label><select id="print_color_mode" name="color_mode" class="form-input"><option value="bw">Black &amp; White</option><option value="color">Color</option></select></div>
                <div><label class="form-checkbox" style="margin-top: 28px; display: inline-flex; gap: 8px;"><input id="print_duplex" name="duplex" type="checkbox"> Double-sided</label></div>
              </div>
              <div class="print-actions">
                <div id="printCommandMessage" style="font-size: 13px; min-height: 20px;"></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-print"></i> Queue print job</button>
              </div>
            </form>
          </div>
          <div class="table-container" style="border:none;background:transparent;margin-top:16px">
            <table class="data-table">
              <thead><tr><th>Pos</th><th>Job</th><th>Printer</th><th>Pages</th><th>Status</th></tr></thead>
              <tbody id="printQueue"><tr><td colspan="5" class="ticket-empty">Loading queue…</td></tr></tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </main>

  <div class="modal-overlay" id="posModal" role="dialog" aria-modal="true" aria-labelledby="posModalTitle">
    <div class="modal">
      <div class="modal-header">
        <h3 class="modal-title" id="posModalTitle">Sale</h3>
        <button class="modal-close" type="button" data-close aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body" id="posModalBody"></div>
      <div class="modal-footer" id="posModalFooter"></div>
    </div>
  </div>
  <div id="receiptPrint" hidden></div>

  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script>
  (function () {
    const CURRENCY = <?php echo json_encode($currency); ?>;
    const CAN_SELL = <?php echo $canSell ? 'true' : 'false'; ?>;
    const CAN_BILLING = <?php echo $canBilling ? 'true' : 'false'; ?>;
    const BUSINESS = <?php echo json_encode($business); ?>;
    const STAFF = <?php echo json_encode(['id' => (int) $currentStaff['staff_id'], 'name' => $currentStaff['full_name'], 'station_id' => (int) ($currentStaff['station_id'] ?? 0)]); ?>;
    const UNIT = {per_hour: '/hr', per_minute: '/min', per_page: '/page', per_job: '/job', fixed: ''};
    const ICON = {internet: ['fa-globe', '#2563EB'], gaming: ['fa-gamepad', '#7C3AED'], printing: ['fa-print', '#0891B2'], scanning: ['fa-file-image', '#0E7490'], typing: ['fa-keyboard', '#4F46E5'], mobile_money: ['fa-mobile-screen', '#D97706'], consultation: ['fa-user-tie', '#475569'], other: ['fa-star', '#64748B']};
    const PRODUCT_ICON = {beverage: 'fa-mug-hot', snack: 'fa-cookie', merchandise: 'fa-shirt', accessory: 'fa-headphones', other: 'fa-box'};

    const state = {catalog: {services: [], plans: [], products: [], stations: []}, cart: [], category: 'all', customer: null, method: 'cash', stationId: STAFF.station_id || null};

    const money = (v) => CURRENCY + ' ' + Number(v || 0).toLocaleString('en-GH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const esc = (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; };
    const $ = (id) => document.getElementById(id);

    async function request(url, options) {
      const response = await fetch(url, Object.assign({credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json'}}, options || {}));
      let result = {};
      try { result = await response.json(); } catch (e) { /* ignore */ }
      if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Request failed (HTTP ' + response.status + ')');
      return result;
    }

    // ---------- Tabs ----------
    document.querySelectorAll('[data-view]').forEach((tab) => tab.addEventListener('click', () => {
      document.querySelectorAll('[data-view]').forEach((t) => t.setAttribute('aria-selected', String(t === tab)));
      document.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== tab.dataset.view; });
      if (tab.dataset.view === 'sales') loadSales();
      if (tab.dataset.view === 'print') loadPrintQueue();
    }));

    // ---------- Catalog ----------
    async function loadCatalog() {
      try {
        const {data} = await request('api/pos.php?catalog=1');
        state.catalog = data;
        renderTiles();
        renderStations();
      } catch (error) {
        $('tiles').innerHTML = '<div class="ticket-empty">' + esc(error.message) + '</div>';
      }
    }

    function catalogItems() {
      const items = [];
      state.catalog.services.forEach((s) => {
        const group = ['internet', 'gaming', 'typing', 'consultation'].includes(s.category) && s.price_unit === 'per_hour' ? 'time' : (['printing', 'scanning'].includes(s.category) ? 'docs' : 'other');
        const [icon, color] = ICON[s.category] || ICON.other;
        items.push({key: 's' + s.service_id, group, name: s.name, price: Number(s.base_price), unit: UNIT[s.price_unit] || '', icon, color, add: {type: 'service', service_id: s.service_id, name: s.name, unit_price: Number(s.base_price), price_unit: s.price_unit, timed: ['internet', 'gaming'].includes(s.category) && ['per_hour', 'per_minute'].includes(s.price_unit)}});
      });
      state.catalog.plans.forEach((p) => {
        const minutes = Number(p.duration_minutes);
        items.push({key: 'p' + p.plan_id, group: 'plans', name: p.name, price: Number(p.price), unit: minutes ? ' · ' + (minutes >= 60 ? (minutes / 60) + ' h' : minutes + ' min') : '', icon: 'fa-ticket', color: '#DB2777', add: {type: 'plan', plan_id: p.plan_id, name: p.name, unit_price: Number(p.price), timed: minutes > 0, minutes}});
      });
      state.catalog.products.forEach((p) => {
        const stock = Number(p.stock_quantity);
        items.push({key: 'r' + p.product_id, group: 'products', name: p.name, price: Number(p.selling_price), unit: '', icon: PRODUCT_ICON[p.category] || 'fa-box', color: '#059669', stock, barcode: p.barcode, sku: p.sku, add: {type: 'product', product_id: p.product_id, name: p.name, unit_price: Number(p.selling_price), stock}});
      });
      return items;
    }

    function renderTiles() {
      const query = $('catalogSearch').value.trim().toLowerCase();
      const items = catalogItems().filter((item) => (state.category === 'all' || item.group === state.category) && (!query || item.name.toLowerCase().includes(query) || (item.barcode || '').toLowerCase() === query || (item.sku || '').toLowerCase() === query));
      if (!items.length) {
        $('tiles').innerHTML = '<div class="ticket-empty">' + (state.catalog.services.length + state.catalog.products.length ? 'Nothing matches.' : 'No services or products yet. Add them under Services and Products.') + '</div>';
        return;
      }
      $('tiles').innerHTML = items.map((item) => {
        let badge = '';
        if (item.stock !== undefined) badge = '<span class="tile-badge ' + (item.stock <= 0 ? 'out' : item.stock <= 5 ? 'low' : '') + '">' + (item.stock <= 0 ? 'Out' : item.stock + ' left') + '</span>';
        return '<button type="button" class="tile" data-key="' + item.key + '"' + (item.stock !== undefined && item.stock <= 0 ? ' disabled' : '') + '>'
          + badge + '<span class="tile-icon" style="background:' + item.color + '"><i class="fas ' + item.icon + '"></i></span>'
          + '<span class="tile-name">' + esc(item.name) + '</span>'
          + '<span class="tile-price">' + money(item.price) + '<small>' + esc(item.unit) + '</small></span></button>';
      }).join('');
    }

    $('tiles').addEventListener('click', (event) => {
      const tile = event.target.closest('.tile');
      if (!tile) return;
      const item = catalogItems().find((i) => i.key === tile.dataset.key);
      if (item) addToCart(item.key, item.add);
    });
    document.querySelectorAll('.chip').forEach((chip) => chip.addEventListener('click', () => {
      state.category = chip.dataset.cat;
      document.querySelectorAll('.chip').forEach((c) => c.setAttribute('aria-pressed', String(c === chip)));
      renderTiles();
    }));
    $('catalogSearch').addEventListener('input', renderTiles);
    $('catalogSearch').addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      const query = event.target.value.trim().toLowerCase();
      const items = catalogItems();
      const exact = items.find((i) => (i.barcode || '').toLowerCase() === query || (i.sku || '').toLowerCase() === query)
        || items.filter((i) => i.name.toLowerCase().includes(query))[0];
      if (exact && !(exact.stock !== undefined && exact.stock <= 0)) {
        addToCart(exact.key, exact.add);
        event.target.value = '';
        renderTiles();
      }
    });

    // ---------- Cart ----------
    function qtyStep(line) { return line.price_unit === 'per_hour' ? 0.5 : line.price_unit === 'per_minute' ? 15 : 1; }
    function qtyLabel(line) {
      if (line.price_unit === 'per_hour') return line.quantity + ' h';
      if (line.price_unit === 'per_minute') return line.quantity + ' min';
      if (line.price_unit === 'per_page') return line.quantity + ' page' + (line.quantity === 1 ? '' : 's');
      return '× ' + line.quantity;
    }

    function addToCart(key, add) {
      const existing = state.cart.find((l) => l.key === key);
      if (existing) {
        changeQty(existing, qtyStep(existing));
      } else {
        const line = Object.assign({key, quantity: add.price_unit === 'per_minute' ? 60 : 1}, add);
        state.cart.push(line);
      }
      renderCart();
    }

    function changeQty(line, delta) {
      let next = Math.round((Number(line.quantity) + delta) * 100) / 100;
      if (line.type === 'product' && next > line.stock) { setMsg('Only ' + line.stock + ' in stock.', true); next = line.stock; }
      if (next <= 0) state.cart = state.cart.filter((l) => l !== line); else line.quantity = next;
    }

    function totals() {
      const subtotal = state.cart.reduce((sum, l) => sum + Math.round(l.unit_price * l.quantity * 100) / 100, 0);
      const discount = Math.min(Math.max(0, Number($('discountInput').value) || 0), subtotal);
      return {subtotal, discount, total: Math.round((subtotal - discount) * 100) / 100};
    }

    function cartNeedsStation() {
      return state.cart.some((l) => l.timed);
    }

    function cartMinutes() {
      return state.cart.reduce((sum, l) => sum + (l.type === 'plan' ? (l.minutes || 0) * l.quantity : l.price_unit === 'per_hour' && l.timed ? l.quantity * 60 : l.price_unit === 'per_minute' && l.timed ? l.quantity : 0), 0);
    }

    function renderCart() {
      const lines = $('lines');
      if (!state.cart.length) {
        lines.innerHTML = '<div class="ticket-empty"><i class="fas fa-basket-shopping" style="font-size:26px;opacity:.4;display:block;margin-bottom:8px"></i>Tap a service or product to start a sale.</div>';
      } else {
        lines.innerHTML = state.cart.map((line, index) => '<div class="line" data-index="' + index + '">'
          + '<div><div class="line-name">' + esc(line.name) + '</div><div class="line-sub">' + money(line.unit_price) + ' ' + esc(UNIT[line.price_unit] || 'each') + ' · ' + esc(qtyLabel(line)) + '</div></div>'
          + '<div class="line-total">' + money(line.unit_price * line.quantity) + '</div>'
          + '<div class="qty"><button type="button" data-qty="-1" aria-label="Decrease">−</button><input type="number" min="0" step="' + qtyStep(line) + '" value="' + line.quantity + '" aria-label="Quantity for ' + esc(line.name) + '"><button type="button" data-qty="1" aria-label="Increase">+</button></div>'
          + '<button type="button" class="line-remove" aria-label="Remove ' + esc(line.name) + '"><i class="fas fa-trash-can"></i></button></div>').join('');
      }
      const needsStation = cartNeedsStation();
      $('stationSection').hidden = !needsStation;
      if (needsStation) renderStationSelect();
      const t = totals();
      $('tSubtotal').textContent = money(t.subtotal);
      $('tTotal').textContent = money(t.total);
      renderPayDetail();
      updateChargeButton();
    }

    $('lines').addEventListener('click', (event) => {
      const row = event.target.closest('.line');
      if (!row) return;
      const line = state.cart[Number(row.dataset.index)];
      const qtyButton = event.target.closest('[data-qty]');
      if (qtyButton) { changeQty(line, Number(qtyButton.dataset.qty) * qtyStep(line)); renderCart(); }
      if (event.target.closest('.line-remove')) { state.cart.splice(Number(row.dataset.index), 1); renderCart(); }
    });
    $('lines').addEventListener('change', (event) => {
      const row = event.target.closest('.line');
      if (!row || event.target.tagName !== 'INPUT') return;
      const line = state.cart[Number(row.dataset.index)];
      changeQty(line, (Number(event.target.value) || 0) - line.quantity);
      renderCart();
    });
    $('discountInput').addEventListener('input', () => { const t = totals(); $('tTotal').textContent = money(t.total); renderPayDetail(); updateChargeButton(); });

    // ---------- Stations ----------
    function stationState(s) {
      if (s.status === 'maintenance' || s.status === 'offline') return 'down';
      return s.active_customer ? 'busy' : 'free';
    }
    function minutesLeft(s) {
      if (s.minutes_left === null || s.minutes_left === undefined) return 'open session';
      const mins = Number(s.minutes_left);
      return mins > 0 ? mins + ' min left' : 'ending';
    }
    function renderStations() {
      $('stationsBoard').innerHTML = state.catalog.stations.map((s) => {
        const st = stationState(s);
        const label = st === 'free' ? 'Free' : st === 'busy' ? esc(s.active_customer) + ' · ' + minutesLeft(s) : (s.status === 'maintenance' ? 'Maintenance' : 'Offline');
        return '<button type="button" class="station-chip ' + st + (Number(s.station_id) === Number(state.stationId) ? ' selected' : '') + '" data-station="' + s.station_id + '"' + (s.status === 'maintenance' ? ' disabled' : '') + '><strong>' + esc(s.station_code) + ' · ' + esc(s.name) + '</strong><span>' + label + '</span></button>';
      }).join('') || '<div class="ticket-empty">No stations registered.</div>';
    }
    function renderStationSelect() {
      const select = $('stationSelect');
      const options = ['<option value="">Choose a station…</option>'];
      state.catalog.stations.filter((s) => s.status !== 'maintenance').forEach((s) => {
        const busy = Boolean(s.active_customer);
        const ownSession = busy && state.customer && Number(s.active_user_id) === Number(state.customer.user_id);
        if (busy && !ownSession) return;
        options.push('<option value="' + s.station_id + '">' + esc(s.station_code + ' — ' + s.name) + (ownSession ? ' (extend current session)' : s.status === 'offline' ? ' (agent offline)' : '') + '</option>');
      });
      select.innerHTML = options.join('');
      select.value = state.stationId && select.querySelector('option[value="' + state.stationId + '"]') ? String(state.stationId) : '';
      state.stationId = select.value ? Number(select.value) : null;
    }
    $('stationSelect').addEventListener('change', (e) => { state.stationId = e.target.value ? Number(e.target.value) : null; renderStations(); updateChargeButton(); });
    $('stationsBoard').addEventListener('click', (event) => {
      const chip = event.target.closest('[data-station]');
      if (!chip) return;
      const station = state.catalog.stations.find((s) => Number(s.station_id) === Number(chip.dataset.station));
      if (station.active_customer && (!state.customer || Number(station.active_user_id) !== Number(state.customer.user_id))) {
        selectCustomer({user_id: station.active_user_id, full_name: station.active_customer});
      }
      state.stationId = Number(station.station_id);
      renderStations();
      renderCart();
      setMsg(station.active_customer ? 'Add time to extend ' + station.active_customer + '\'s session on ' + station.station_code + '.' : station.station_code + ' selected for timed sales.');
    });
    $('refreshStations').addEventListener('click', loadCatalog);

    // ---------- Customer ----------
    let custTimer = null;
    $('custSearch').addEventListener('input', () => {
      clearTimeout(custTimer);
      custTimer = setTimeout(searchCustomers, 220);
    });
    async function searchCustomers() {
      const query = $('custSearch').value.trim();
      const box = $('custResults');
      if (query.length < 2) { box.classList.remove('open'); return; }
      try {
        const {data} = await request('api/customers.php?search=' + encodeURIComponent(query));
        box.innerHTML = data.map((c) => '<button type="button" role="option" data-id="' + c.id + '" data-name="' + esc(c.name) + '">' + esc(c.name) + '<small>' + esc(c.phone || c.email || 'No contact') + '</small></button>').join('')
          + (CAN_SELL ? '<button type="button" data-new="1"><i class="fas fa-user-plus"></i> Add “' + esc(query) + '” as new customer</button>' : '');
        box.classList.add('open');
      } catch (error) {
        box.innerHTML = '<button type="button" disabled>' + esc(error.message) + '</button>';
        box.classList.add('open');
      }
    }
    $('custResults').addEventListener('click', async (event) => {
      const option = event.target.closest('button');
      if (!option) return;
      $('custResults').classList.remove('open');
      if (option.dataset.new) {
        try {
          const {data} = await request('api/customers.php', {method: 'POST', body: JSON.stringify({full_name: $('custSearch').value.trim(), membership_tier: 'standard', is_active: 1})});
          selectCustomer({user_id: data.user_id, full_name: data.full_name});
        } catch (error) { setMsg(error.message, true); }
        return;
      }
      selectCustomer({user_id: Number(option.dataset.id), full_name: option.dataset.name});
    });
    document.addEventListener('click', (event) => { if (!event.target.closest('.cust-box')) $('custResults').classList.remove('open'); });

    async function selectCustomer(customer) {
      state.customer = Object.assign({wallet: 0, owed: 0, credit_limit: 0}, customer);
      $('custSearch').value = '';
      renderCustomer();
      try {
        const {data} = await request('api/pos.php?customer_id=' + customer.user_id);
        Object.assign(state.customer, {wallet: data.wallet_balance, owed: data.owed, credit_limit: data.credit_limit, phone: data.customer.phone, account_status: data.account_status});
        renderCustomer();
      } catch (error) { /* balance unavailable */ }
    }
    function renderCustomer() {
      const c = state.customer;
      if (!c) { $('custCard').innerHTML = ''; } else {
        $('custCard').innerHTML = '<div class="cust-card"><span class="account-avatar" style="display:grid;place-items:center;width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#7C3AED,#06B6D4);color:#fff;font-weight:700">' + esc(String(c.full_name || '?')[0].toUpperCase()) + '</span>'
          + '<div class="who"><strong>' + esc(c.full_name) + '</strong><span>Wallet ' + money(c.wallet) + (c.owed > 0 ? ' · <span style="color:#DC2626">Owes ' + money(c.owed) + '</span>' : '') + '</span>'
          + (CAN_BILLING ? ' <a href="billing.php?user_id=' + c.user_id + '" style="font-size:12px">Billing</a>' : '') + '</div>'
          + (CAN_SELL ? '<button type="button" id="topupCustomer" class="btn btn-secondary small" style="color:var(--violet)" title="Top up wallet"><i class="fas fa-wallet"></i> Top up</button>' : '')
          + '<button type="button" id="clearCustomer" aria-label="Remove customer"><i class="fas fa-xmark"></i></button></div>';
        $('clearCustomer').addEventListener('click', () => { state.customer = null; if (['wallet', 'account'].includes(state.method)) setMethod('cash'); renderCustomer(); });
        if ($('topupCustomer')) $('topupCustomer').addEventListener('click', () => CtlPayments.openTopUp({
          userId: c.user_id, name: c.full_name, owed: c.owed,
          onDone: () => { selectCustomer({user_id: c.user_id, full_name: c.full_name}); loadStats(); }
        }));
      }
      document.querySelector('[data-method="wallet"]').disabled = !c;
      document.querySelector('[data-method="account"]').disabled = !c;
      if (cartNeedsStation()) renderStationSelect();
      renderPayDetail();
      updateChargeButton();
    }

    // ---------- Payment ----------
    function setMethod(method) {
      state.method = method;
      document.querySelectorAll('.pay-method').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.method === method)));
      renderPayDetail();
      updateChargeButton();
    }
    document.querySelectorAll('.pay-method').forEach((b) => b.addEventListener('click', () => setMethod(b.dataset.method)));

    function renderPayDetail() {
      const detail = $('payDetail');
      const total = totals().total;
      const keepTendered = $('tendered') ? $('tendered').value : '';
      const keepRef = $('payRef') ? $('payRef').value : '';
      if (state.method === 'cash') {
        const quick = [total, 5, 10, 20, 50, 100].filter((v, i, arr) => v > 0 && (i === 0 || v >= total) && arr.indexOf(v) === i);
        detail.innerHTML = '<label class="sr-only" for="tendered">Cash received</label><input class="form-input" id="tendered" type="number" min="0" step="0.01" placeholder="Cash received (optional)" value="' + esc(keepTendered) + '">'
          + '<div class="cash-quick">' + quick.map((v, i) => '<button type="button" data-cash="' + v + '">' + (i === 0 ? 'Exact' : money(v)) + '</button>').join('') + '</div><div class="change-line" id="changeLine"></div>';
        $('tendered').addEventListener('input', updateChange);
        detail.querySelectorAll('[data-cash]').forEach((b) => b.addEventListener('click', () => { $('tendered').value = Number(b.dataset.cash).toFixed(2); updateChange(); }));
        updateChange();
      } else if (state.method === 'wallet') {
        const c = state.customer;
        const short = c && c.wallet < total;
        detail.innerHTML = '<div style="font-size:13px">Wallet balance: <strong>' + money(c ? c.wallet : 0) + '</strong>' + (short ? '<div style="color:#DC2626;margin-top:4px">Not enough balance — top up in Customer Billing or choose another method.</div>' : '<div style="color:var(--muted);margin-top:4px">After sale: ' + money((c ? c.wallet : 0) - total) + '</div>') + '</div>';
      } else if (state.method === 'account') {
        const c = state.customer;
        detail.innerHTML = '<div style="font-size:13px">Adds ' + money(total) + ' to ' + esc(c ? c.full_name : 'the customer') + '\'s unbilled charges.' + (c && c.credit_limit <= 0 ? '<div style="color:#DC2626;margin-top:4px">No credit limit set for this customer — set one in Customer Billing first.</div>' : c ? '<div style="color:var(--muted);margin-top:4px">Credit limit ' + money(c.credit_limit) + ' · currently owes ' + money(c.owed) + '</div>' : '') + '</div>';
      } else {
        detail.innerHTML = '<label class="sr-only" for="payRef">Payment reference</label><input class="form-input" id="payRef" maxlength="100" placeholder="' + (state.method === 'mobile_money' ? 'MoMo transaction ID' : 'Reference') + ' (optional)" value="' + esc(keepRef) + '">';
      }
    }
    function updateChange() {
      const tendered = Number($('tendered') && $('tendered').value);
      const total = totals().total;
      const line = $('changeLine');
      if (!line) return;
      if (!tendered) { line.textContent = ''; return; }
      line.style.color = tendered + 0.001 >= total ? '#059669' : '#DC2626';
      line.textContent = tendered + 0.001 >= total ? 'Change: ' + money(tendered - total) : 'Short by ' + money(total - tendered);
    }

    function updateChargeButton() {
      const t = totals();
      const button = $('chargeBtn');
      let blocker = '';
      if (!CAN_SELL) blocker = 'No permission to sell';
      else if (!state.cart.length) blocker = 'Cart is empty';
      else if (cartNeedsStation() && !state.stationId) blocker = 'Choose a station';
      else if (state.method === 'wallet' && (!state.customer || state.customer.wallet + 0.001 < t.total)) blocker = 'Insufficient wallet';
      else if (state.method === 'account' && (!state.customer || state.customer.credit_limit <= 0)) blocker = 'No credit limit';
      button.disabled = Boolean(blocker);
      button.textContent = blocker && state.cart.length ? blocker : 'Charge ' + money(t.total);
    }

    function setMsg(text, error) {
      const msg = $('regMsg');
      msg.textContent = text || '';
      msg.className = 'reg-msg' + (error ? ' error' : '');
    }

    async function charge() {
      if ($('chargeBtn').disabled) return;
      const t = totals();
      const payload = {
        customer_id: state.customer ? state.customer.user_id : null,
        customer_name: state.customer ? state.customer.full_name : 'Walk-in Customer',
        station_id: cartNeedsStation() ? state.stationId : null,
        usage_minutes: cartMinutes(),
        items: state.cart.map((l) => ({type: l.type, service_id: l.service_id, plan_id: l.plan_id, product_id: l.product_id, name: l.name, quantity: l.quantity, unit_price: l.unit_price, service_type: l.service_type, minutes: l.minutes})),
        discount_amount: t.discount,
        payment_method: state.method,
        amount_tendered: state.method === 'cash' && $('tendered') && $('tendered').value ? Number($('tendered').value) : null,
        reference: $('payRef') ? $('payRef').value : null
      };
      const button = $('chargeBtn');
      button.disabled = true;
      button.textContent = 'Processing…';
      setMsg('');
      try {
        const {data, message} = await request('api/pos.php', {method: 'POST', body: JSON.stringify(payload)});
        showReceipt(data, message);
        state.cart = [];
        $('discountInput').value = '0';
        if ($('tendered')) $('tendered').value = '';
        if (state.customer) selectCustomer({user_id: state.customer.user_id, full_name: state.customer.full_name});
        renderCart();
        loadCatalog();
        loadStats();
      } catch (error) {
        setMsg(error.message, true);
        updateChargeButton();
      }
    }
    $('chargeBtn').addEventListener('click', charge);

    // ---------- Custom item ----------
    const customBtn = $('customItemBtn');
    if (customBtn) customBtn.addEventListener('click', () => {
      openModal('Custom item', '<div style="display:grid;gap:12px"><div><label class="form-label" for="ciName">Description</label><input class="form-input" id="ciName" maxlength="120"></div>'
        + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div><label class="form-label" for="ciPrice">Price (' + CURRENCY + ')</label><input class="form-input" id="ciPrice" type="number" min="0" step="0.01"></div>'
        + '<div><label class="form-label" for="ciType">Category</label><select class="form-input" id="ciType"><option value="other">Other</option><option value="printing">Printing</option><option value="internet">Internet</option><option value="gaming">Gaming</option><option value="retail">Retail</option></select></div></div></div>',
        '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="ciAdd">Add to sale</button>');
      $('ciAdd').addEventListener('click', () => {
        const name = $('ciName').value.trim();
        const price = Number($('ciPrice').value);
        if (!name || !(price >= 0)) return;
        state.cart.push({key: 'c' + Date.now(), type: 'custom', name, unit_price: price, quantity: 1, service_type: $('ciType').value});
        closeModal();
        renderCart();
      });
    });

    // ---------- Modal & receipt ----------
    const modal = $('posModal');
    function openModal(title, body, footer) {
      $('posModalTitle').textContent = title;
      $('posModalBody').innerHTML = body;
      $('posModalFooter').innerHTML = footer;
      modal.classList.add('active');
      const first = modal.querySelector('input, button.btn-primary');
      if (first) first.focus();
    }
    function closeModal() { modal.classList.remove('active'); }
    modal.addEventListener('click', (e) => { if (e.target === modal || e.target.closest('[data-close]')) closeModal(); });

    const METHOD_LABEL = {cash: 'Cash', mobile_money: 'Mobile Money', card: 'Card', bank_transfer: 'Bank transfer', wallet: 'Wallet', account: 'On account'};
    function receiptHtml(sale) {
      const items = sale.items || [];
      return '<div class="receipt"><h4>' + esc(BUSINESS.name) + '</h4><div class="c">' + esc(BUSINESS.address) + '</div><div class="c">' + esc(BUSINESS.phone) + '</div><hr>'
        + '<div class="r"><span>' + esc(sale.receipt_number || '') + '</span><span>' + esc(new Date(String(sale.created_at).replace(' ', 'T')).toLocaleString('en-GB')) + '</span></div>'
        + '<div>Customer: ' + esc(sale.customer_name) + '</div>' + (sale.station_code ? '<div>Station: ' + esc(sale.station_code) + '</div>' : '') + '<div>Served by: ' + esc(sale.staff_name || STAFF.name) + '</div><hr>'
        + items.map((i) => '<div>' + esc(i.name) + '</div><div class="r"><span>&nbsp;&nbsp;' + Number(i.quantity) + ' × ' + money(i.unit_price) + '</span><span>' + money(i.total) + '</span></div>').join('')
        + '<hr>' + (Number(sale.discount) > 0 ? '<div class="r"><span>Discount</span><span>−' + money(sale.discount) + '</span></div>' : '')
        + '<div class="r big"><span>TOTAL</span><span>' + money(sale.total) + '</span></div>'
        + '<div class="r"><span>' + esc(METHOD_LABEL[sale.payment_method] || sale.payment_method) + '</span><span>' + (sale.amount_tendered ? money(sale.amount_tendered) : '') + '</span></div>'
        + (sale.change ? '<div class="r"><span>Change</span><span>' + money(sale.change) + '</span></div>' : '')
        + (sale.wallet_balance != null ? '<div class="r"><span>Wallet balance</span><span>' + money(sale.wallet_balance) + '</span></div>' : '')
        + (sale.status === 'pending' ? '<div class="c" style="margin-top:6px">** CHARGED TO ACCOUNT — UNPAID **</div>' : '')
        + (sale.usage_minutes ? '<div class="c" style="margin-top:6px">Time: ' + sale.usage_minutes + ' minutes</div>' : '')
        + '<hr><div class="c">Thank you! Come again.</div></div>';
    }
    function showReceipt(sale, message) {
      const station = state.catalog.stations.find((s) => Number(s.station_id) === Number(sale.station_id));
      if (station) sale.station_code = station.station_code;
      openModal(message || 'Sale complete', receiptHtml(sale), '<button class="btn btn-secondary" type="button" id="printReceipt"><i class="fas fa-print"></i> Print</button><button class="btn btn-primary" type="button" data-close>New sale</button>');
      $('printReceipt').addEventListener('click', () => {
        const area = $('receiptPrint');
        area.innerHTML = receiptHtml(sale);
        area.hidden = false;
        window.print();
        area.hidden = true;
      });
    }

    // ---------- Stats & sales ----------
    async function loadStats() {
      try {
        const {data} = await request('api/pos.php?dashboard=1');
        $('statToday').textContent = money(data.stats.today_revenue);
        $('statCount').textContent = data.stats.today_sales;
        $('statAccount').textContent = money(data.stats.today_on_account);
        $('statSessions').textContent = data.stats.active_sessions;
        state.recent = data.transactions;
      } catch (error) { /* stats are optional */ }
    }
    async function loadSales() {
      await loadStats();
      const rows = state.recent || [];
      $('salesTable').innerHTML = rows.length ? rows.map((r) => '<tr><td><strong>' + esc(r.receipt_number || ('#' + r.pos_transaction_id)) + '</strong></td><td>' + esc(r.customer_name) + '</td><td style="text-transform:capitalize">' + esc(r.service_type) + '</td><td>' + esc(r.station_code || '—') + '</td><td>' + esc(METHOD_LABEL[r.payment_method] || r.payment_method) + '</td>'
        + '<td class="amount" style="font-weight:700">' + money(r.amount) + '</td><td><span class="status-badge ' + (r.status === 'paid' ? 'success' : r.status === 'pending' ? 'warning' : 'error') + '"><span class="status-dot"></span>' + esc(r.status === 'pending' ? 'On account' : r.status) + '</span></td>'
        + '<td>' + esc(new Date(r.created_at.replace(' ', 'T')).toLocaleString('en-GB', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'})) + '</td>'
        + '<td><button class="header-btn" style="width:32px;height:32px" data-reprint="' + r.pos_transaction_id + '" aria-label="Reprint receipt"><i class="fas fa-receipt"></i></button></td></tr>').join('')
        : '<tr><td colspan="9" class="ticket-empty">No sales yet.</td></tr>';
    }
    $('salesTable').addEventListener('click', async (event) => {
      const button = event.target.closest('[data-reprint]');
      if (!button) return;
      try {
        const {data} = await request('api/pos.php?receipt=' + button.dataset.reprint);
        showReceipt({receipt_number: data.receipt_number, created_at: data.created_at, customer_name: data.customer_name, station_code: data.station_code, staff_name: data.staff_name, items: data.items,
          discount: data.discount_amount, total: data.amount, payment_method: data.payment_method, amount_tendered: data.amount_tendered, change: data.amount_tendered ? Math.max(0, data.amount_tendered - data.amount) : null,
          status: data.status, usage_minutes: Number(data.usage_minutes)}, 'Receipt ' + (data.receipt_number || ''));
      } catch (error) { setMsg(error.message, true); }
    });

    // ---------- Print desk ----------
    document.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-printer-check]');
      if (!button) return;
      const card = button.closest('.network-device-card');
      const status = card.querySelector('[data-printer-status]');
      const message = card.querySelector('[data-printer-message]');
      const ip = card.querySelector('[data-printer-ip]').value.trim();
      const protocol = card.querySelector('[data-printer-protocol]').value;
      button.disabled = true;
      button.textContent = 'Checking…';
      try {
        if (ip) await request('api/printers.php?action=configure', {method: 'POST', body: JSON.stringify({peripheral_id: Number(button.dataset.printerCheck), ip_address: ip, protocol})});
        const response = await fetch('api/printers.php?action=test', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: JSON.stringify({peripheral_id: Number(button.dataset.printerCheck)})});
        const result = await response.json();
        const ok = response.ok && result.success !== false;
        status.textContent = 'Printer status: ' + (ok ? 'Online' : 'Offline');
        message.textContent = result.message || '';
        message.style.color = ok ? 'var(--green)' : 'var(--red)';
      } catch (error) {
        message.textContent = error.message;
        message.style.color = 'var(--red)';
      } finally {
        button.disabled = false;
        button.textContent = 'Check';
      }
    });

    $('printCommandForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.target;
      const data = new FormData(form);
      const message = $('printCommandMessage');
      const file = data.get('file');
      if (!(file instanceof File) || !file.size || !Number(data.get('peripheral_id'))) {
        message.textContent = 'Choose a printer and a file.';
        message.style.color = 'var(--red)';
        return;
      }
      data.set('user_id', String(state.customer ? state.customer.user_id : STAFF.id));
      data.set('station_id', String(STAFF.station_id || 0));
      data.set('staff_id', String(STAFF.id));
      data.set('duplex', data.get('duplex') ? '1' : '0');
      try {
        const response = await fetch('api/print-jobs.php', {method: 'POST', credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: data});
        const result = await response.json();
        if (!response.ok || result.success === false) throw new Error(result.message || 'Print job could not be queued');
        message.textContent = 'Print job queued.';
        message.style.color = 'var(--green)';
        form.reset();
        loadPrintQueue();
      } catch (error) {
        message.textContent = error.message;
        message.style.color = 'var(--red)';
      }
    });

    async function loadPrintQueue() {
      try {
        const response = await fetch('api/print-jobs.php?queue=1', {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const result = await response.json();
        const jobs = Array.isArray(result.data) ? result.data : [];
        $('printQueue').innerHTML = jobs.length ? jobs.map((job) => '<tr><td>' + (job.status === 'printing' ? '<span class="queue-now-badge">Now</span>' : job.queue_position ? '#' + job.queue_position : '—') + '</td><td>' + esc(job.file_name || 'Untitled') + '</td><td>' + esc(job.printer_name || '—') + '</td><td>' + (Number(job.pages) || 0) + '</td><td><span class="status-badge ' + (job.status === 'printing' ? 'info' : job.status === 'queued' ? 'warning' : 'success') + '"><span class="status-dot"></span>' + esc(job.status) + '</span></td></tr>').join('')
          : '<tr><td colspan="5" class="ticket-empty">No queued print jobs.</td></tr>';
      } catch (error) { /* keep previous */ }
    }
    setInterval(() => { if (!document.hidden && !document.querySelector('[data-panel="print"]').hidden) loadPrintQueue(); }, 4000);
    setInterval(() => { if (!document.hidden) { loadCatalog(); loadStats(); } }, 30000);

    // ---------- Keyboard ----------
    document.addEventListener('keydown', (event) => {
      if (event.key === '/' && !event.target.closest('input, textarea, select')) { event.preventDefault(); $('catalogSearch').focus(); }
      if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) { event.preventDefault(); charge(); }
      if (event.key === 'Escape' && modal.classList.contains('active')) closeModal();
    });

    loadCatalog();
    loadStats();
    renderCart();
    renderCustomer();
  })();
  </script>
</body>
</html>
