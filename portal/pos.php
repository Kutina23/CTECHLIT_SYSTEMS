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
    /* ---------- Top bar ---------- */
    .pos-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; margin-bottom: 14px; }
    .reg-tabs { display: flex; gap: 4px; padding: 4px; border-radius: 12px; background: rgba(148,163,184,0.14); }
    .reg-tabs button { padding: 8px 14px; border: 0; border-radius: 9px; background: transparent; font: 600 13px Inter, sans-serif; color: var(--ink-secondary); cursor: pointer; }
    .reg-tabs button[aria-selected="true"] { background: #fff; color: var(--violet); box-shadow: 0 2px 8px rgba(15,23,42,0.08); }
    .pos-meta { font-size: 12.5px; color: var(--ink-secondary); }
    .pos-meta > i { color: var(--violet); }
    .pos-keys { margin-left: 8px; color: var(--muted); }
    @media (max-width: 1480px) { .pos-keys { display: none; } }
    @media (max-width: 1280px) { .pos-meta { display: none; } }
    .reg-stats { display: flex; gap: 6px; margin-left: auto; }
    .reg-stat { padding: 6px 12px; border-radius: 11px; background: rgba(255,255,255,0.7); border: 1px solid rgba(148,163,184,0.2); }
    .reg-stat span { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); }
    .reg-stat strong { font-size: 14px; color: var(--ink); font-variant-numeric: tabular-nums; }
    .kbd { padding: 1px 6px; border: 1px solid rgba(148,163,184,0.5); border-radius: 5px; font-size: 11px; background: rgba(255,255,255,0.7); }

    /* ---------- Register layout ---------- */
    .register { display: grid; grid-template-columns: minmax(0, 1fr) minmax(360px, 400px); gap: 16px; align-items: start; }
    .catalog { padding: 16px; }
    .catalog-bar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
    .catalog-search { position: relative; flex: 1 1 240px; }
    .catalog-search i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); }
    .catalog-search input { width: 100%; padding-left: 38px; }
    .station-strip { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; padding: 6px 6px 6px 12px; border-radius: 12px; background: rgba(148,163,184,0.1); }
    .strip-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); }
    .strip-scroll { display: flex; gap: 6px; flex: 1; overflow-x: auto; scrollbar-width: thin; padding: 2px; }
    .strip-refresh { flex: 0 0 auto; width: 30px; height: 30px; border: 0; border-radius: 8px; background: #fff; color: var(--muted); cursor: pointer; }
    .station-chip { flex: 0 0 auto; display: flex; align-items: center; gap: 7px; padding: 6px 10px; border: 1px solid rgba(148,163,184,0.25); border-radius: 999px; background: #fff; font: 600 12px Inter, sans-serif; color: var(--ink); cursor: pointer; white-space: nowrap; }
    .station-chip::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #10B981; }
    .station-chip.busy::before { background: #F59E0B; }
    .station-chip.down::before { background: #94A3B8; }
    .station-chip.down { color: var(--muted); }
    .station-chip small { font-weight: 500; color: var(--muted); }
    .station-chip.selected { border-color: var(--violet); background: rgba(124,58,237,0.08); color: var(--violet); }
    .station-chip:disabled { opacity: 0.5; cursor: not-allowed; }
    .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
    .chip { padding: 6px 12px; border: 1px solid rgba(148,163,184,0.3); border-radius: 999px; background: rgba(255,255,255,0.7); font: 600 12.5px Inter, sans-serif; color: var(--ink-secondary); cursor: pointer; }
    .chip[aria-pressed="true"] { background: var(--violet); border-color: var(--violet); color: #fff; }
    .tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
    .tile { position: relative; display: flex; flex-direction: column; gap: 6px; min-height: 104px; padding: 12px; border: 1px solid rgba(148,163,184,0.22); border-radius: 14px; background: rgba(255,255,255,0.82); text-align: left; cursor: pointer; font: inherit; color: var(--ink); transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease; }
    .tile:hover:not(:disabled) { transform: translateY(-2px); border-color: rgba(124,58,237,0.45); box-shadow: 0 10px 24px rgba(124,58,237,0.12); }
    .tile:active:not(:disabled) { transform: scale(0.97); }
    .tile:disabled { opacity: 0.45; cursor: not-allowed; }
    .tile.in-cart { border-color: var(--violet); box-shadow: inset 0 0 0 1px var(--violet); }
    .tile-qty { position: absolute; top: 8px; right: 8px; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; background: var(--violet); color: #fff; font-size: 11.5px; font-weight: 800; display: grid; place-items: center; }
    .tile-icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 10px; color: #fff; font-size: 14px; }
    .tile-name { font-weight: 600; font-size: 13px; line-height: 1.3; }
    .tile-price { margin-top: auto; font-weight: 800; font-size: 14.5px; font-variant-numeric: tabular-nums; }
    .tile-price small { font-weight: 500; color: var(--muted); font-size: 11px; }
    .tile-badge { position: absolute; top: 8px; right: 8px; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700; background: rgba(16,185,129,0.12); color: #059669; }
    .tile-badge.low { background: rgba(245,158,11,0.15); color: #B45309; }
    .tile-badge.out { background: rgba(239,68,68,0.12); color: #DC2626; }
    .tile.in-cart .tile-badge { display: none; }

    /* ---------- Cart ---------- */
    .cart { position: sticky; top: calc(var(--header-height) + 12px); display: flex; flex-direction: column; height: calc(100vh - var(--header-height) - 24px); padding: 0; overflow: hidden; }
    .cart-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px 8px; }
    .cart-head h2 { margin: 0; font-size: 16px; color: var(--ink); }
    .cart-count { margin-left: 4px; padding: 1px 8px; border-radius: 999px; background: rgba(124,58,237,0.12); color: var(--violet); font-size: 12px; }
    .cart-count:empty { display: none; }
    .cart-clear { border: 0; background: transparent; color: var(--muted); font: 600 12px Inter, sans-serif; cursor: pointer; }
    .cart-clear:hover { color: #DC2626; }
    .cart-customer { padding: 0 16px 10px; }
    /* glass-card gives every child the same layer; lift the customer block so its results overlay the station picker and items */
    .cart > .cart-customer { z-index: 40; }
    .cart > .cart-station { z-index: 3; }
    .cust-box { position: relative; display: flex; align-items: center; gap: 8px; padding: 0 12px; border: 1px solid rgba(148,163,184,0.35); border-radius: 12px; background: #fff; }
    .cust-box > i { color: var(--muted); font-size: 13px; }
    .cust-box input { flex: 1; min-width: 0; padding: 10px 0; border: 0; outline: none; background: transparent; font: 14px Inter, sans-serif; color: var(--ink); }
    .cust-box:focus-within { border-color: var(--violet); box-shadow: 0 0 0 3px rgba(124,58,237,0.12); }
    .cust-box[hidden] { display: none; }
    .cust-results { position: absolute; z-index: 30; top: calc(100% + 4px); left: 0; right: 0; display: none; max-height: 280px; overflow-y: auto; padding: 6px; border-radius: 12px; background: #fff; box-shadow: 0 16px 40px rgba(15,23,42,0.18); }
    .cust-results.open { display: block; }
    .cust-results button { display: block; width: 100%; padding: 9px 10px; border: 0; border-radius: 8px; background: transparent; text-align: left; font: inherit; cursor: pointer; }
    .cust-results button:hover, .cust-results button:focus, .cust-results button.active { background: rgba(124,58,237,0.08); outline: none; }
    .cust-results small { display: block; color: var(--muted); font-size: 11.5px; }
    .cust-card { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 12px; background: rgba(124,58,237,0.07); border: 1px solid rgba(124,58,237,0.18); }
    .cust-avatar { flex: 0 0 auto; display: grid; place-items: center; width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg,#7C3AED,#06B6D4); color: #fff; font-weight: 700; }
    .cust-card .who { flex: 1; min-width: 0; }
    .cust-card .who strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; }
    .cust-card .who span { font-size: 12px; color: var(--ink-secondary); }
    .cust-card .who .owes { color: #DC2626; font-weight: 600; }
    .cust-card .icon-btn { flex: 0 0 auto; width: 30px; height: 30px; border: 0; border-radius: 8px; background: #fff; color: var(--ink-secondary); cursor: pointer; }
    .cust-card .icon-btn:hover { color: var(--violet); }
    .cart-station { display: flex; align-items: center; gap: 10px; margin: 0 16px 10px; padding: 8px 10px; border-radius: 12px; background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); }
    .cart-station.ok { background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.3); }
    .st-label { flex: 0 0 auto; font-size: 12.5px; font-weight: 700; color: var(--ink-secondary); }
    .st-picker { position: relative; flex: 1; min-width: 0; }
    .st-toggle { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; padding: 8px 10px; border: 1px solid rgba(148,163,184,0.4); border-radius: 9px; background: #fff; font: 600 13px Inter, sans-serif; color: var(--ink); cursor: pointer; text-align: left; }
    .st-toggle span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .st-toggle i { color: var(--muted); font-size: 11px; transition: transform 0.15s; }
    .st-toggle[aria-expanded="true"] { border-color: var(--violet); box-shadow: 0 0 0 3px rgba(124,58,237,0.12); }
    .st-toggle[aria-expanded="true"] i { transform: rotate(180deg); }
    .st-list { position: absolute; z-index: 50; top: calc(100% + 6px); left: -86px; right: 0; max-height: min(320px, 55vh); overflow-y: auto; padding: 6px; border-radius: 14px; background: #fff; border: 1px solid rgba(148,163,184,0.25); box-shadow: 0 18px 44px rgba(15,23,42,0.2); }
    .st-list[hidden] { display: none; }
    .st-group { padding: 8px 8px 4px; font-size: 10.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); }
    .st-option { display: flex; align-items: center; justify-content: flex-start; gap: 10px; width: 100%; padding: 9px 10px; border: 0; border-radius: 10px; background: transparent; text-align: left; font: inherit; color: var(--ink); cursor: pointer; }
    .st-option:hover:not(:disabled), .st-option:focus-visible { background: rgba(124,58,237,0.08); outline: none; }
    .st-option[aria-selected="true"] { background: rgba(124,58,237,0.12); }
    .st-option:disabled { opacity: 0.45; cursor: not-allowed; }
    .st-option .dot { flex: 0 0 auto; width: 9px; height: 9px; border-radius: 50%; background: #10B981; }
    .st-option.busy .dot { background: #F59E0B; }
    .st-option.down .dot { background: #94A3B8; }
    .st-option > span:not(.dot) { flex: 1; min-width: 0; text-align: left; }
    .st-option strong { display: block; font-size: 13px; }
    .st-option small { display: block; font-size: 11.5px; color: var(--muted); }
    .st-option .check { margin-left: auto; color: var(--violet); }
    .cart > .cart-station.open { z-index: 45; }
    .cart-station[hidden] { display: none; }
    .lines { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0 16px; border-top: 1px solid rgba(148,163,184,0.16); }
    .line { display: grid; grid-template-columns: minmax(0, 1fr) auto auto 26px; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px dashed rgba(148,163,184,0.3); }
    .line-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; font-size: 13.5px; }
    .line-sub { font-size: 11.5px; color: var(--muted); }
    .line-total { min-width: 72px; font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; font-size: 13.5px; }
    .qty { display: inline-flex; align-items: center; border: 1px solid rgba(148,163,184,0.35); border-radius: 9px; background: #fff; overflow: hidden; }
    .qty button { width: 28px; height: 30px; border: 0; background: transparent; cursor: pointer; color: var(--ink); font-size: 15px; }
    .qty button:hover { background: rgba(124,58,237,0.08); color: var(--violet); }
    .qty input { width: 40px; padding: 0; border: 0; border-left: 1px solid rgba(148,163,184,0.25); border-right: 1px solid rgba(148,163,184,0.25); height: 30px; text-align: center; font: 700 13px Inter, sans-serif; -moz-appearance: textfield; }
    .qty input::-webkit-outer-spin-button, .qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .line-remove { width: 26px; height: 26px; border: 0; border-radius: 7px; background: transparent; color: var(--muted); cursor: pointer; }
    .line-remove:hover { background: #FEF2F2; color: #DC2626; }
    .line.flash { animation: line-flash 0.6s ease; }
    @keyframes line-flash { from { background: rgba(124,58,237,0.12); } to { background: transparent; } }
    .ticket-empty { padding: 30px 10px; text-align: center; color: var(--muted); font-size: 13px; }
    .cart-empty { display: grid; place-items: center; gap: 6px; height: 100%; min-height: 120px; color: var(--muted); font-size: 13px; text-align: center; }
    .cart-empty i { font-size: 28px; opacity: 0.35; }
    .cart-foot { flex-shrink: 0; padding: 10px 16px 14px; border-top: 1px solid rgba(148,163,184,0.22); background: rgba(255,255,255,0.6); }
    .totals-row { display: flex; justify-content: space-between; align-items: center; padding: 2px 0; font-size: 13.5px; color: var(--ink-secondary); font-variant-numeric: tabular-nums; }
    .totals-row[hidden] { display: none; }
    .totals-row.grand { padding: 4px 0 10px; font-size: 24px; font-weight: 800; color: var(--ink); }
    .disc-row { min-height: 30px; }
    .link-btn { padding: 0; border: 0; background: transparent; color: var(--violet); font: 600 12.5px Inter, sans-serif; cursor: pointer; }
    .discount-input { width: 110px; padding: 5px 8px; border: 1px solid rgba(148,163,184,0.35); border-radius: 8px; text-align: right; font: inherit; }
    .charge-btn { width: 100%; padding: 15px; border: 0; border-radius: 14px; background: linear-gradient(135deg, #7C3AED, #2563EB); color: #fff; font: 800 17px Inter, sans-serif; letter-spacing: 0.01em; cursor: pointer; box-shadow: 0 12px 26px rgba(76,64,220,0.35); }
    .charge-btn:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }
    .charge-btn.blocked { background: #FEF3C7; color: #92400E; opacity: 1; box-shadow: none; font-size: 14px; }
    .reg-msg { min-height: 0; margin-top: 6px; font-size: 12.5px; text-align: center; }
    .reg-msg:empty { margin: 0; }
    .reg-msg.error { color: #DC2626; }

    /* ---------- Checkout ---------- */
    .co-overlay { position: fixed; inset: 0; z-index: 1200; display: grid; place-items: center; padding: 20px; background: rgba(15,23,42,0.5); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
    .co-overlay[hidden] { display: none; }
    .co-panel { display: grid; grid-template-columns: 320px minmax(0, 1fr); width: min(980px, 100%); max-height: calc(100vh - 40px); overflow: hidden; border-radius: 22px; background: #fff; box-shadow: 0 30px 80px rgba(15,23,42,0.35); animation: co-in 0.18s ease-out; }
    @keyframes co-in { from { opacity: 0; transform: translateY(10px) scale(0.99); } to { opacity: 1; transform: none; } }
    .co-summary { display: flex; flex-direction: column; padding: 22px; overflow-y: auto; color: #fff; background: radial-gradient(ellipse at 0% 0%, rgba(6,182,212,0.3), transparent 55%), linear-gradient(160deg, #312E81, #6D28D9); }
    .co-back { align-self: flex-start; display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border: 1px solid rgba(255,255,255,0.3); border-radius: 999px; background: rgba(255,255,255,0.1); color: #fff; font: 600 12.5px Inter, sans-serif; cursor: pointer; }
    .co-back .kbd { background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.35); color: #fff; }
    .co-due-label { margin-top: 26px; font-size: 12px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.7); }
    .co-due { font-size: 40px; font-weight: 800; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
    .co-details { margin-top: 18px; display: grid; gap: 6px; font-size: 13px; color: rgba(255,255,255,0.85); }
    .co-details .row { display: flex; justify-content: space-between; gap: 10px; }
    .co-details .row span:last-child { font-variant-numeric: tabular-nums; }
    .co-details hr { width: 100%; border: 0; border-top: 1px solid rgba(255,255,255,0.2); margin: 6px 0; }
    .co-details .who { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; font-weight: 600; color: #fff; }
    .co-main { display: flex; flex-direction: column; gap: 14px; padding: 22px 24px; overflow-y: auto; }
    .co-main h2 { margin: 0; font-size: 18px; color: var(--ink); }
    .pay-methods { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .pay-method { position: relative; display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 14px 6px; border: 2px solid rgba(148,163,184,0.3); border-radius: 14px; background: #fff; font: 700 13.5px Inter, sans-serif; color: var(--ink-secondary); cursor: pointer; }
    .pay-method i { font-size: 20px; }
    .pay-method kbd { position: absolute; top: 6px; right: 8px; font: 600 10px Inter, sans-serif; color: var(--muted); }
    .pay-method:hover:not(:disabled) { border-color: rgba(124,58,237,0.5); }
    .pay-method[aria-checked="true"] { border-color: var(--violet); background: rgba(124,58,237,0.08); color: var(--violet); }
    .pay-method:disabled { opacity: 0.4; cursor: not-allowed; }
    .pay-detail { min-height: 120px; }
    .tender { display: grid; gap: 10px; }
    .tender label { font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); }
    .tender-input { width: 100%; padding: 12px 14px; border: 2px solid rgba(148,163,184,0.35); border-radius: 12px; font: 800 24px Inter, sans-serif; font-variant-numeric: tabular-nums; color: var(--ink); }
    .tender-input:focus { outline: none; border-color: var(--violet); box-shadow: 0 0 0 3px rgba(124,58,237,0.12); }
    .cash-quick { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .cash-quick button { padding: 11px 6px; border: 1px solid rgba(148,163,184,0.35); border-radius: 11px; background: #F8FAFC; font: 700 14px Inter, sans-serif; color: var(--ink); cursor: pointer; font-variant-numeric: tabular-nums; }
    .cash-quick button:hover { border-color: var(--violet); color: var(--violet); }
    .cash-quick button.exact { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.4); color: #047857; }
    .change-box { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border-radius: 12px; background: #F1F5F9; font-size: 15px; font-weight: 700; color: var(--ink-secondary); }
    .change-box strong { font-size: 24px; font-variant-numeric: tabular-nums; }
    .change-box.ok { background: rgba(16,185,129,0.1); color: #047857; }
    .change-box.short { background: #FEF2F2; color: #B91C1C; }
    .pay-note { padding: 12px 14px; border-radius: 12px; background: #F8FAFC; font-size: 14px; color: var(--ink-secondary); }
    .pay-note.warn { background: #FEF2F2; color: #B91C1C; }
    .co-complete { margin-top: auto; padding: 17px; font-size: 18px; }

    .receipt { width: 300px; margin: 0 auto; padding: 18px; background: #fff; color: #111; font: 12.5px/1.45 'Courier New', monospace; border-radius: 8px; }
    .receipt h4 { margin: 0; text-align: center; font-size: 15px; }
    .receipt .c { text-align: center; }
    .receipt hr { border: 0; border-top: 1px dashed #333; margin: 8px 0; }
    .receipt .r { display: flex; justify-content: space-between; gap: 8px; }
    .receipt .big { font-weight: 700; font-size: 14px; }
    .print-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .kbd { padding: 1px 6px; border: 1px solid rgba(148,163,184,0.5); border-radius: 5px; font-size: 11px; }
    @media (max-width: 1100px) {
      .register { grid-template-columns: 1fr; }
      .cart { position: static; height: auto; max-height: none; }
      .lines { max-height: 360px; }
      .print-grid { grid-template-columns: 1fr; }
      .reg-stats { margin-left: 0; }
      .pos-keys { display: none; }
    }
    @media (max-width: 760px) {
      .co-panel { grid-template-columns: 1fr; max-height: calc(100vh - 20px); overflow-y: auto; }
      .co-summary { padding: 16px; }
      .co-due { font-size: 32px; }
      .co-details { display: none; }
      .cash-quick { grid-template-columns: repeat(3, 1fr); }
    }
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
      <div class="pos-bar">
        <div class="reg-tabs" role="tablist" aria-label="Point of sale">
          <button role="tab" aria-selected="true" data-view="register"><i class="fas fa-cash-register"></i> Register</button>
          <button role="tab" aria-selected="false" data-view="sales"><i class="fas fa-clock-rotate-left"></i> Recent sales</button>
          <button role="tab" aria-selected="false" data-view="print"><i class="fas fa-print"></i> Print desk</button>
        </div>
        <div class="pos-meta"><i class="fas fa-desktop"></i> <?php echo sanitize($posTerminal['terminal_name'] ?? 'POS-01'); ?> · <?php echo sanitize($currentStaff['full_name']); ?>
          <span class="pos-keys"><span class="kbd">/</span> search · <span class="kbd">F2</span> customer · <span class="kbd">Ctrl</span>+<span class="kbd">Enter</span> charge</span></div>
        <div class="reg-stats">
          <div class="reg-stat"><span>Today</span><strong id="statToday">—</strong></div>
          <div class="reg-stat"><span>Sales</span><strong id="statCount">—</strong></div>
          <div class="reg-stat"><span>On account</span><strong id="statAccount">—</strong></div>
          <div class="reg-stat"><span>Sessions</span><strong id="statSessions">—</strong></div>
        </div>
      </div>

      <!-- REGISTER -->
      <section data-panel="register">
        <div class="register">
          <div class="card glass-card catalog">
            <div class="catalog-bar">
              <div class="catalog-search">
                <i class="fas fa-search" aria-hidden="true"></i>
                <label class="sr-only" for="catalogSearch">Search services and products</label>
                <input id="catalogSearch" class="form-input" type="search" placeholder="Search or scan barcode (Enter adds the first match)" autocomplete="off">
              </div>
              <?php if ($canSell): ?>
              <button class="btn btn-secondary" type="button" id="customItemBtn"><i class="fas fa-pen-to-square"></i> Custom item</button>
              <?php endif; ?>
            </div>
            <div class="station-strip">
              <span class="strip-label">Stations</span>
              <div class="strip-scroll" id="stationsBoard" aria-label="Stations: tap one to use it for timed sales"></div>
              <button class="strip-refresh" type="button" id="refreshStations" aria-label="Refresh stations"><i class="fas fa-rotate"></i></button>
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

          <aside class="card glass-card cart" aria-label="Current sale">
            <header class="cart-head">
              <h2>Current sale <span class="cart-count" id="cartCount"></span></h2>
              <button type="button" class="cart-clear" id="clearCart" hidden><i class="fas fa-rotate-left"></i> Clear</button>
            </header>
            <div class="cart-customer">
              <div class="cust-box" id="custBox">
                <i class="fas fa-user" aria-hidden="true"></i>
                <label class="sr-only" for="custSearch">Find customer</label>
                <input id="custSearch" type="search" placeholder="Walk-in customer — search name or phone" autocomplete="off">
                <div class="cust-results" id="custResults" role="listbox"></div>
              </div>
              <div id="custCard"></div>
            </div>
            <div class="cart-station" id="stationSection" hidden>
              <span class="st-label" id="stationLabel"><i class="fas fa-desktop"></i> Station</span>
              <div class="st-picker">
                <button type="button" class="st-toggle" id="stationToggle" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="stationLabel stationChosen">
                  <span id="stationChosen">Choose a station…</span><i class="fas fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="st-list" id="stationList" role="listbox" aria-labelledby="stationLabel" hidden></div>
              </div>
            </div>
            <div class="lines" id="lines"></div>
            <footer class="cart-foot">
              <div class="totals-row disc-row">
                <button type="button" class="link-btn" id="discountToggle"><i class="fas fa-tag"></i> Add discount</button>
                <span id="discWrap" hidden><label class="sr-only" for="discountInput">Discount</label><input id="discountInput" class="discount-input" type="number" min="0" step="0.01" value="0" placeholder="0.00"></span>
              </div>
              <div class="totals-row" id="subRow" hidden><span>Subtotal</span><span id="tSubtotal">—</span></div>
              <div class="totals-row grand"><span>Total</span><span id="tTotal">—</span></div>
              <button class="charge-btn" id="chargeBtn" type="button" disabled <?php echo $canSell ? '' : 'title="You need the Manage POS Sales permission"'; ?>>Charge</button>
              <div class="reg-msg" id="regMsg" aria-live="polite"></div>
            </footer>
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
                <div><label for="print_payment" class="form-label">Payment</label><select id="print_payment" name="payment_method" class="form-input"><option value="collect">On collection</option><option value="cash">Cash (paid now)</option><option value="mobile_money">MoMo (paid now)</option><option value="card">Card (paid now)</option><option value="wallet">Customer wallet</option><option value="account">Customer account</option></select></div>
              </div>
              <div class="print-row">
                <div><label for="print_pages" class="form-label">Pages</label><input id="print_pages" name="pages" type="number" min="1" class="form-input" placeholder="Auto for PDF/images"></div>
                <div><label for="print_copies" class="form-label">Copies</label><input id="print_copies" name="copies" type="number" min="1" class="form-input" value="1" required></div>
              </div>
              <div class="print-row">
                <div><label for="print_color_mode" class="form-label">Color</label><select id="print_color_mode" name="color_mode" class="form-input"><option value="bw">Black &amp; White</option><option value="color">Color</option></select></div>
                <div style="display:flex;flex-wrap:wrap;gap:4px 14px;margin-top:28px"><label class="form-checkbox" style="display:inline-flex;gap:8px"><input id="print_duplex" name="duplex" type="checkbox"> Double-sided</label><label class="form-checkbox" style="display:inline-flex;gap:8px"><input name="priority" type="checkbox" value="5"> Urgent</label></div>
              </div>
              <div class="print-actions">
                <div id="printCommandMessage" style="font-size: 13px; min-height: 20px;"></div>
                <a href="print-jobs.php" class="btn btn-secondary"><i class="fas fa-layer-group"></i> All queues</a><button type="submit" class="btn btn-primary"><i class="fas fa-print"></i> Queue print job</button>
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

  <div class="co-overlay" id="checkout" hidden role="dialog" aria-modal="true" aria-labelledby="coTitle">
    <div class="co-panel">
      <aside class="co-summary">
        <button type="button" class="co-back" id="coBack"><i class="fas fa-arrow-left"></i> Back to sale <span class="kbd">Esc</span></button>
        <div class="co-due-label">Amount due</div>
        <div class="co-due" id="coDue">—</div>
        <div class="co-details" id="coDetails"></div>
      </aside>
      <section class="co-main">
        <h2 id="coTitle">How is the customer paying?</h2>
        <div class="pay-methods" role="radiogroup" aria-label="Payment method">
          <button type="button" class="pay-method" role="radio" data-method="cash" aria-checked="true"><i class="fas fa-money-bill-wave"></i><span>Cash</span><kbd>1</kbd></button>
          <button type="button" class="pay-method" role="radio" data-method="mobile_money" aria-checked="false"><i class="fas fa-mobile-screen"></i><span>MoMo</span><kbd>2</kbd></button>
          <button type="button" class="pay-method" role="radio" data-method="card" aria-checked="false"><i class="fas fa-credit-card"></i><span>Card</span><kbd>3</kbd></button>
          <button type="button" class="pay-method" role="radio" data-method="bank_transfer" aria-checked="false"><i class="fas fa-building-columns"></i><span>Bank</span><kbd>4</kbd></button>
          <button type="button" class="pay-method" role="radio" data-method="wallet" aria-checked="false" disabled><i class="fas fa-wallet"></i><span>Wallet</span><kbd>5</kbd></button>
          <button type="button" class="pay-method" role="radio" data-method="account" aria-checked="false" disabled><i class="fas fa-file-invoice"></i><span>On account</span><kbd>6</kbd></button>
        </div>
        <div class="pay-detail" id="payDetail"></div>
        <div class="reg-msg" id="coMsg" aria-live="assertive"></div>
        <button class="charge-btn co-complete" id="completeBtn" type="button">Complete sale</button>
      </section>
    </div>
  </div>

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
    const WALKINS_ALLOWED = <?php echo walkInsAllowed() ? 'true' : 'false'; ?>;
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
        renderStationSelect();
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
        $('tiles').innerHTML = '<div class="ticket-empty">' + (state.catalog.services.length + state.catalog.products.length ? 'Nothing matches “' + esc(query) + '”.' : 'No services or products yet. Add them under Services and Products.') + '</div>';
        return;
      }
      $('tiles').innerHTML = items.map((item) => {
        const inCart = state.cart.find((l) => l.key === item.key);
        let badge = '';
        if (item.stock !== undefined) badge = '<span class="tile-badge ' + (item.stock <= 0 ? 'out' : item.stock <= 5 ? 'low' : '') + '">' + (item.stock <= 0 ? 'Out' : item.stock + ' left') + '</span>';
        return '<button type="button" class="tile' + (inCart ? ' in-cart' : '') + '" data-key="' + item.key + '"' + (item.stock !== undefined && item.stock <= 0 ? ' disabled' : '') + ' title="Add ' + esc(item.name) + '">'
          + badge + (inCart ? '<span class="tile-qty">' + esc(String(inCart.quantity)) + '</span>' : '')
          + '<span class="tile-icon" style="background:' + item.color + '"><i class="fas ' + item.icon + '"></i></span>'
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
      if (event.key === 'Escape') { event.target.value = ''; renderTiles(); return; }
      if (event.key !== 'Enter') return;
      event.preventDefault();
      const query = event.target.value.trim().toLowerCase();
      if (!query) { openCheckout(); return; }
      const items = catalogItems();
      const exact = items.find((i) => (i.barcode || '').toLowerCase() === query || (i.sku || '').toLowerCase() === query)
        || items.filter((i) => (state.category === 'all' || i.group === state.category) && i.name.toLowerCase().includes(query))[0];
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

    let flashKey = null;
    function addToCart(key, add) {
      const existing = state.cart.find((l) => l.key === key);
      if (existing) {
        changeQty(existing, qtyStep(existing));
      } else {
        state.cart.push(Object.assign({key, quantity: add.price_unit === 'per_minute' ? 60 : 1}, add));
      }
      flashKey = key;
      setMsg('');
      renderCart();
    }

    function changeQty(line, delta) {
      let next = Math.round((Number(line.quantity) + delta) * 100) / 100;
      if (line.type === 'product' && next > line.stock) { setMsg('Only ' + line.stock + ' of ' + line.name + ' in stock.', true); next = line.stock; }
      if (next <= 0) state.cart = state.cart.filter((l) => l !== line); else line.quantity = next;
    }

    function totals() {
      const subtotal = state.cart.reduce((sum, l) => sum + Math.round(l.unit_price * l.quantity * 100) / 100, 0);
      const discount = Math.min(Math.max(0, Number($('discountInput').value) || 0), subtotal);
      return {subtotal, discount, total: Math.round((subtotal - discount) * 100) / 100};
    }
    function cartNeedsStation() { return state.cart.some((l) => l.timed); }
    function cartMinutes() {
      return state.cart.reduce((sum, l) => sum + (l.type === 'plan' ? (l.minutes || 0) * l.quantity : l.price_unit === 'per_hour' && l.timed ? l.quantity * 60 : l.price_unit === 'per_minute' && l.timed ? l.quantity : 0), 0);
    }

    function renderCart() {
      const lines = $('lines');
      if (!state.cart.length) {
        lines.innerHTML = '<div class="cart-empty"><div><i class="fas fa-basket-shopping"></i><div>Tap a service or product to start a sale</div><div style="font-size:12px;margin-top:4px">or type its name and press Enter</div></div></div>';
      } else {
        lines.innerHTML = state.cart.map((line, index) => '<div class="line' + (line.key === flashKey ? ' flash' : '') + '" data-index="' + index + '">'
          + '<div style="min-width:0"><div class="line-name" title="' + esc(line.name) + '">' + esc(line.name) + '</div><div class="line-sub">' + money(line.unit_price) + ' ' + esc(UNIT[line.price_unit] || 'each') + (line.price_unit && line.price_unit !== 'fixed' ? ' · ' + esc(qtyLabel(line)) : '') + '</div></div>'
          + '<div class="qty"><button type="button" data-qty="-1" aria-label="Less ' + esc(line.name) + '">−</button><input type="number" min="0" step="' + qtyStep(line) + '" value="' + line.quantity + '" aria-label="Quantity of ' + esc(line.name) + '"><button type="button" data-qty="1" aria-label="More ' + esc(line.name) + '">+</button></div>'
          + '<div class="line-total">' + money(line.unit_price * line.quantity) + '</div>'
          + '<button type="button" class="line-remove" aria-label="Remove ' + esc(line.name) + '"><i class="fas fa-xmark"></i></button></div>').join('');
        const flashed = lines.querySelector('.line.flash');
        if (flashed) flashed.scrollIntoView({block: 'nearest'});
      }
      flashKey = null;
      const count = state.cart.length;
      $('cartCount').textContent = count ? count + (count === 1 ? ' item' : ' items') : '';
      $('clearCart').hidden = !count && !state.customer;
      const needsStation = cartNeedsStation();
      $('stationSection').hidden = !needsStation;
      if (needsStation) renderStationSelect();
      renderTotals();
      renderTiles();
    }

    function renderTotals() {
      const t = totals();
      $('subRow').hidden = !(t.discount > 0);
      $('tSubtotal').textContent = money(t.subtotal);
      $('tTotal').textContent = money(t.total);
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
    $('discountToggle').addEventListener('click', () => {
      $('discountToggle').hidden = true;
      $('discWrap').hidden = false;
      $('discountInput').value = '';
      $('discountInput').focus();
    });
    $('discountInput').addEventListener('input', renderTotals);
    function resetDiscount() {
      $('discountInput').value = '0';
      $('discWrap').hidden = true;
      $('discountToggle').hidden = false;
    }
    $('clearCart').addEventListener('click', () => {
      state.cart = [];
      state.customer = null;
      resetDiscount();
      setMethod('cash');
      renderCustomer();
      renderCart();
      $('catalogSearch').focus();
    });

    // ---------- Stations ----------
    function stationState(s) {
      if (s.status === 'maintenance' || s.status === 'offline') return 'down';
      return s.active_customer ? 'busy' : 'free';
    }
    function minutesLeft(s) {
      if (s.minutes_left === null || s.minutes_left === undefined) return 'open';
      const mins = Number(s.minutes_left);
      return mins > 0 ? mins + 'm left' : 'ending';
    }
    function renderStations() {
      $('stationsBoard').innerHTML = state.catalog.stations.map((s) => {
        const st = stationState(s);
        const label = st === 'free' ? 'free' : st === 'busy' ? esc(s.active_customer) + ' · ' + minutesLeft(s) : (s.status === 'maintenance' ? 'maintenance' : 'offline');
        return '<button type="button" class="station-chip ' + st + (Number(s.station_id) === Number(state.stationId) ? ' selected' : '') + '" data-station="' + s.station_id + '"' + (s.status === 'maintenance' ? ' disabled' : '') + ' title="' + esc(s.name) + '">' + esc(s.station_code) + ' <small>' + label + '</small></button>';
      }).join('') || '<span style="font-size:12px;color:var(--muted)">No stations registered.</span>';
    }
    function stationOptionInfo(s) {
      const busy = Boolean(s.active_customer);
      const ownSession = busy && state.customer && Number(s.active_user_id) === Number(state.customer.user_id);
      if (s.status === 'maintenance') return {kind: 'down', note: 'Under maintenance', disabled: true};
      if (ownSession) return {kind: 'busy', note: 'Extend this customer’s session · ' + minutesLeft(s)};
      if (busy) return {kind: 'busy', note: 'In use by ' + s.active_customer + ' · ' + minutesLeft(s) + ' · tap to extend'};
      return {kind: 'free', note: s.status === 'offline' ? 'Free · agent offline' : 'Free'};
    }

    function renderStationSelect() {
      const chosen = state.catalog.stations.find((s) => Number(s.station_id) === Number(state.stationId) && s.status !== 'maintenance');
      if (!chosen) state.stationId = null;
      $('stationChosen').textContent = chosen ? chosen.station_code + ' — ' + chosen.name : 'Choose a station…';
      $('stationSection').classList.toggle('ok', Boolean(state.stationId));
      const groups = {free: [], busy: [], down: []};
      state.catalog.stations.forEach((s) => {
        const info = stationOptionInfo(s);
        const selected = Number(s.station_id) === Number(state.stationId);
        groups[info.kind].push('<button type="button" role="option" class="st-option ' + info.kind + '" data-pick="' + s.station_id + '" aria-selected="' + selected + '"' + (info.disabled ? ' disabled' : '') + '>'
          + '<span class="dot"></span><span><strong>' + esc(s.station_code + ' — ' + s.name) + '</strong><small>' + esc(info.note) + '</small></span>'
          + (selected ? '<i class="fas fa-check check"></i>' : '') + '</button>');
      });
      $('stationList').innerHTML = (groups.free.length ? '<div class="st-group">Free</div>' + groups.free.join('') : '')
        + (groups.busy.length ? '<div class="st-group">In use — add time</div>' + groups.busy.join('') : '')
        + (groups.down.length ? '<div class="st-group">Unavailable</div>' + groups.down.join('') : '')
        || '<div class="st-group">No stations registered</div>';
    }

    function setStationList(open) {
      $('stationList').hidden = !open;
      $('stationToggle').setAttribute('aria-expanded', String(open));
      $('stationSection').classList.toggle('open', open);
      if (open) {
        const current = $('stationList').querySelector('[aria-selected="true"]') || $('stationList').querySelector('.st-option:not(:disabled)');
        if (current) current.focus();
      }
    }
    function pickStation(id) {
      const picked = state.catalog.stations.find((s) => Number(s.station_id) === Number(id));
      if (!picked || picked.status === 'maintenance') return;
      state.stationId = Number(picked.station_id);
      setStationList(false);
      // An occupied station means adding time to that customer's running session.
      if (picked.active_customer && (!state.customer || Number(picked.active_user_id) !== Number(state.customer.user_id))) {
        selectCustomer({user_id: picked.active_user_id, full_name: picked.active_customer});
        setMsg('Adding time to the session of ' + picked.active_customer + ' on ' + picked.station_code + '.');
      } else {
        setMsg('');
      }
      renderStationSelect();
      renderStations();
      updateChargeButton();
      $('stationToggle').focus();
    }
    $('stationToggle').addEventListener('click', () => {
      const opening = $('stationList').hidden;
      if (opening) renderStationSelect();
      setStationList(opening);
    });
    $('stationList').addEventListener('click', (event) => {
      const option = event.target.closest('[data-pick]');
      if (option && !option.disabled) pickStation(option.dataset.pick);
    });
    $('stationList').addEventListener('keydown', (event) => {
      const options = Array.from($('stationList').querySelectorAll('.st-option:not(:disabled)'));
      const index = options.indexOf(document.activeElement);
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const next = options[(index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length];
        if (next) next.focus();
      } else if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        setStationList(false);
        $('stationToggle').focus();
      }
    });
    document.addEventListener('click', (event) => {
      if (!$('stationList').hidden && !event.target.closest('.st-picker')) setStationList(false);
    });
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
      setMsg(station.active_customer ? 'Add time to extend ' + station.active_customer + ' on ' + station.station_code + '.' : station.station_code + ' will be used for timed items.');
    });
    $('refreshStations').addEventListener('click', loadCatalog);

    // ---------- Customer ----------
    let custTimer = null;
    let custActive = -1;
    $('custSearch').addEventListener('input', () => {
      clearTimeout(custTimer);
      custTimer = setTimeout(searchCustomers, 200);
    });
    $('custSearch').addEventListener('keydown', (event) => {
      const options = Array.from($('custResults').querySelectorAll('button:not([disabled])'));
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        if (!options.length) return;
        event.preventDefault();
        custActive = (custActive + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
        options.forEach((o, i) => o.classList.toggle('active', i === custActive));
      } else if (event.key === 'Enter') {
        event.preventDefault();
        const pick = options[custActive >= 0 ? custActive : 0];
        if (pick) pick.click();
      } else if (event.key === 'Escape') {
        $('custResults').classList.remove('open');
        event.target.value = '';
        $('catalogSearch').focus();
      }
    });
    async function searchCustomers() {
      const query = $('custSearch').value.trim();
      const box = $('custResults');
      custActive = -1;
      if (query.length < 2) { box.classList.remove('open'); return; }
      try {
        const {data} = await request('api/customers.php?search=' + encodeURIComponent(query));
        box.innerHTML = data.slice(0, 8).map((c) => '<button type="button" role="option" data-id="' + c.id + '" data-name="' + esc(c.name) + '">' + esc(c.name) + '<small>' + esc(c.phone || c.email || 'No contact') + '</small></button>').join('')
          + (CAN_SELL ? '<button type="button" data-new="1"><i class="fas fa-user-plus"></i> Add “' + esc(query) + '” as a new customer</button>' : '');
        box.classList.add('open');
      } catch (error) {
        box.innerHTML = '<button type="button" disabled>' + esc(error.message) + '</button>';
        box.classList.add('open');
      }
    }
    $('custResults').addEventListener('click', async (event) => {
      const option = event.target.closest('button');
      if (!option || option.disabled) return;
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
      $('catalogSearch').focus();
      try {
        const {data} = await request('api/pos.php?customer_id=' + customer.user_id);
        Object.assign(state.customer, {wallet: Number(data.wallet_balance), owed: Number(data.owed), credit_limit: Number(data.credit_limit), phone: data.customer.phone, account_status: data.account_status});
        renderCustomer();
      } catch (error) { /* balance unavailable */ }
    }
    function renderCustomer() {
      const c = state.customer;
      $('custBox').hidden = Boolean(c);
      if (!c) {
        $('custCard').innerHTML = '';
      } else {
        $('custCard').innerHTML = '<div class="cust-card"><span class="cust-avatar">' + esc(String(c.full_name || '?')[0].toUpperCase()) + '</span>'
          + '<div class="who"><strong>' + esc(c.full_name) + '</strong><span>Wallet ' + money(c.wallet) + (c.owed > 0 ? ' · <span class="owes">owes ' + money(c.owed) + '</span>' : '') + '</span></div>'
          + (CAN_SELL ? '<button type="button" class="icon-btn" id="topupCustomer" title="Top up wallet" aria-label="Top up wallet"><i class="fas fa-wallet"></i></button>' : '')
          + (CAN_BILLING ? '<a class="icon-btn" style="display:grid;place-items:center" href="billing.php?user_id=' + c.user_id + '" title="Customer billing" aria-label="Customer billing"><i class="fas fa-file-invoice-dollar"></i></a>' : '')
          + '<button type="button" class="icon-btn" id="clearCustomer" title="Change customer (F2)" aria-label="Remove customer"><i class="fas fa-xmark"></i></button></div>';
        $('clearCustomer').addEventListener('click', () => {
          state.customer = null;
          if (['wallet', 'account'].includes(state.method)) setMethod('cash');
          renderCustomer();
          $('custSearch').focus();
        });
        if ($('topupCustomer')) $('topupCustomer').addEventListener('click', () => CtlPayments.openTopUp({
          userId: c.user_id, name: c.full_name, owed: c.owed,
          onDone: () => { selectCustomer({user_id: c.user_id, full_name: c.full_name}); loadStats(); }
        }));
      }
      document.querySelector('[data-method="wallet"]').disabled = !c;
      document.querySelector('[data-method="account"]').disabled = !c;
      $('clearCart').hidden = !state.cart.length && !c;
      if (cartNeedsStation()) renderStationSelect();
      if (checkoutOpen()) renderCheckout();
      updateChargeButton();
    }

    // ---------- Charge button (cart) ----------
    function cartBlocker() {
      if (!CAN_SELL) return 'No permission to sell';
      if (!state.cart.length) return 'Add items to charge';
      if (!WALKINS_ALLOWED && !state.customer) return 'Select the customer first';
      if (cartNeedsStation() && !state.stationId) return 'Choose a station for timed items';
      return '';
    }
    function updateChargeButton() {
      const blocker = cartBlocker();
      const button = $('chargeBtn');
      button.disabled = Boolean(blocker);
      button.classList.toggle('blocked', Boolean(blocker) && state.cart.length > 0);
      button.innerHTML = blocker ? esc(blocker) : 'Charge ' + money(totals().total) + ' <i class="fas fa-arrow-right" style="margin-left:6px"></i>';
    }
    function setMsg(text, error) {
      const msg = $('regMsg');
      msg.textContent = text || '';
      msg.className = 'reg-msg' + (error ? ' error' : '');
    }
    $('chargeBtn').addEventListener('click', openCheckout);

    // ---------- Checkout step ----------
    const checkout = $('checkout');
    const METHOD_KEYS = ['cash', 'mobile_money', 'card', 'bank_transfer', 'wallet', 'account'];
    let lastFocus = null;
    function checkoutOpen() { return !checkout.hidden; }

    function openCheckout() {
      const blocker = cartBlocker();
      if (blocker) {
        setMsg(blocker, true);
        if (cartNeedsStation() && !state.stationId) setStationList(true);
        return;
      }
      lastFocus = document.activeElement;
      if (['wallet', 'account'].includes(state.method) && !state.customer) state.method = 'cash';
      $('coMsg').textContent = '';
      checkout.hidden = false;
      document.body.style.overflow = 'hidden';
      setMethod(state.method);
    }
    function closeCheckout() {
      checkout.hidden = true;
      document.body.style.overflow = '';
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    }
    $('coBack').addEventListener('click', closeCheckout);
    checkout.addEventListener('click', (event) => { if (event.target === checkout) closeCheckout(); });

    function setMethod(method) {
      state.method = method;
      document.querySelectorAll('.pay-method').forEach((b) => b.setAttribute('aria-checked', String(b.dataset.method === method)));
      if (checkoutOpen()) renderCheckout();
    }
    document.querySelectorAll('.pay-method').forEach((b) => b.addEventListener('click', () => { if (!b.disabled) setMethod(b.dataset.method); }));

    function renderCheckout() {
      const t = totals();
      $('coDue').textContent = money(t.total);
      const c = state.customer;
      const station = state.catalog.stations.find((s) => Number(s.station_id) === Number(state.stationId));
      $('coDetails').innerHTML = '<div class="who"><i class="fas fa-user"></i> ' + esc(c ? c.full_name : 'Walk-in customer') + '</div>'
        + (cartNeedsStation() && station ? '<div class="row"><span><i class="fas fa-desktop"></i> ' + esc(station.station_code) + '</span><span>' + cartMinutes() + ' min</span></div>' : '')
        + '<hr>' + state.cart.map((l) => '<div class="row"><span>' + esc(l.name) + ' <small style="opacity:.7">' + esc(qtyLabel(l)) + '</small></span><span>' + money(l.unit_price * l.quantity) + '</span></div>').join('')
        + (t.discount > 0 ? '<hr><div class="row"><span>Subtotal</span><span>' + money(t.subtotal) + '</span></div><div class="row"><span>Discount</span><span>−' + money(t.discount) + '</span></div>' : '');
      renderPayDetail();
    }

    function renderPayDetail() {
      const detail = $('payDetail');
      const total = totals().total;
      const c = state.customer;
      if (state.method === 'cash') {
        const notes = [5, 10, 20, 50, 100, 200].filter((v) => v > total);
        const roundUp = Math.ceil(total / 5) * 5;
        const quick = [total].concat(roundUp > total && !notes.includes(roundUp) ? [roundUp] : [], notes).slice(0, 8);
        detail.innerHTML = '<div class="tender"><label for="tendered">Cash received</label>'
          + '<input class="tender-input" id="tendered" type="number" min="0" step="0.01" inputmode="decimal" placeholder="' + Number(total).toFixed(2) + '" autocomplete="off">'
          + '<div class="cash-quick">' + quick.map((v, i) => '<button type="button" class="' + (i === 0 ? 'exact' : '') + '" data-cash="' + v + '">' + (i === 0 ? 'Exact' : money(v)) + '</button>').join('') + '</div>'
          + '<div class="change-box" id="changeBox"><span>Change</span><strong>' + money(0) + '</strong></div></div>';
        $('tendered').addEventListener('input', updateCompleteButton);
        $('tendered').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); completeSale(); } });
        detail.querySelectorAll('[data-cash]').forEach((b) => b.addEventListener('click', () => {
          $('tendered').value = Number(b.dataset.cash).toFixed(2);
          updateCompleteButton();
          $('completeBtn').focus();
        }));
        setTimeout(() => $('tendered') && $('tendered').focus(), 30);
      } else if (state.method === 'wallet') {
        const short = !c || c.wallet + 0.001 < total;
        detail.innerHTML = '<div class="pay-note' + (short ? ' warn' : '') + '">Wallet balance <strong>' + money(c ? c.wallet : 0) + '</strong>'
          + (short ? '<br>Not enough balance. Top up the wallet or choose another method.' : '<br>After this sale: <strong>' + money(c.wallet - total) + '</strong>') + '</div>';
      } else if (state.method === 'account') {
        const blocked = !c || c.credit_limit <= 0;
        detail.innerHTML = '<div class="pay-note' + (blocked ? ' warn' : '') + '">' + (blocked
          ? 'No credit limit is set for ' + esc(c ? c.full_name : 'this customer') + '. Set one in Customer Billing first.'
          : 'Adds <strong>' + money(total) + '</strong> to ' + esc(c.full_name) + '\'s account.<br>Credit limit ' + money(c.credit_limit) + ' · currently owes ' + money(c.owed)) + '</div>';
      } else {
        detail.innerHTML = '<div class="tender"><label for="payRef">' + (state.method === 'mobile_money' ? 'MoMo transaction ID' : 'Reference') + ' (optional)</label>'
          + '<input class="tender-input" style="font-size:17px;font-weight:600" id="payRef" maxlength="100" autocomplete="off" placeholder="e.g. 1234567890"></div>';
        $('payRef').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); completeSale(); } });
        setTimeout(() => $('payRef') && $('payRef').focus(), 30);
      }
      updateCompleteButton();
    }

    function checkoutBlocker() {
      const total = totals().total;
      const c = state.customer;
      if (state.method === 'wallet' && (!c || c.wallet + 0.001 < total)) return 'Not enough wallet balance';
      if (state.method === 'account' && (!c || c.credit_limit <= 0)) return 'No credit limit set';
      if (state.method === 'cash' && $('tendered') && $('tendered').value !== '' && Number($('tendered').value) + 0.001 < total) return 'Cash received is less than the total';
      return '';
    }
    function updateCompleteButton() {
      const total = totals().total;
      const box = $('changeBox');
      if (box && $('tendered')) {
        const raw = $('tendered').value;
        const tendered = raw === '' ? total : Number(raw);
        const diff = Math.round((tendered - total) * 100) / 100;
        box.className = 'change-box ' + (diff >= 0 ? (diff > 0 ? 'ok' : '') : 'short');
        box.innerHTML = diff >= 0 ? '<span>Change</span><strong>' + money(diff) + '</strong>' : '<span>Short by</span><strong>' + money(-diff) + '</strong>';
      }
      const blocker = checkoutBlocker();
      const button = $('completeBtn');
      button.disabled = Boolean(blocker);
      button.innerHTML = blocker ? esc(blocker) : '<i class="fas fa-check"></i> Complete sale · ' + money(total);
    }
    $('completeBtn').addEventListener('click', completeSale);

    let completing = false;
    async function completeSale() {
      if (completing || $('completeBtn').disabled || cartBlocker()) return;
      const t = totals();
      const tenderedRaw = state.method === 'cash' && $('tendered') ? $('tendered').value : '';
      const payload = {
        customer_id: state.customer ? state.customer.user_id : null,
        customer_name: state.customer ? state.customer.full_name : 'Walk-in Customer',
        station_id: cartNeedsStation() ? state.stationId : null,
        usage_minutes: cartMinutes(),
        items: state.cart.map((l) => ({type: l.type, service_id: l.service_id, plan_id: l.plan_id, product_id: l.product_id, name: l.name, quantity: l.quantity, unit_price: l.unit_price, service_type: l.service_type, minutes: l.minutes})),
        discount_amount: t.discount,
        payment_method: state.method,
        amount_tendered: state.method === 'cash' ? (tenderedRaw !== '' ? Number(tenderedRaw) : t.total) : null,
        reference: $('payRef') ? $('payRef').value.trim() || null : null
      };
      completing = true;
      const button = $('completeBtn');
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';
      $('coMsg').textContent = '';
      $('coMsg').className = 'reg-msg';
      try {
        const {data, message} = await request('api/pos.php', {method: 'POST', body: JSON.stringify(payload)});
        closeCheckout();
        showReceipt(data, message);
        state.cart = [];
        state.stationId = STAFF.station_id || null;
        resetDiscount();
        state.customer = null;
        state.method = 'cash';
        renderCustomer();
        renderCart();
        setMsg('');
        loadCatalog();
        loadStats();
      } catch (error) {
        $('coMsg').textContent = error.message;
        $('coMsg').className = 'reg-msg error';
        updateCompleteButton();
      } finally {
        completing = false;
      }
    }

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
      const payment = String(data.get('payment_method') || 'collect');
      if (!state.customer && ['wallet', 'account'].includes(payment)) {
        message.textContent = 'Select a customer on the Register to charge their wallet or account.';
        message.style.color = 'var(--red)';
        return;
      }
      if (state.customer) data.set('user_id', String(state.customer.user_id));
      if (Number(STAFF.station_id)) data.set('station_id', String(STAFF.station_id));
      data.set('duplex', data.get('duplex') ? '1' : '0');
      if (!data.get('pages')) data.delete('pages');
      try {
        const response = await fetch('api/print-jobs.php', {method: 'POST', credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: data});
        const result = await response.json();
        if (!response.ok || result.success === false) throw new Error(result.message || 'Print job could not be queued');
        message.textContent = result.message || 'Print job queued.';
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

    // ---------- Size the cart to the visible screen so Charge is always in view ----------
    (function fitCart() {
      const cart = document.querySelector('.cart');
      if (!cart) return;
      let frame = 0;
      const fit = () => {
        frame = 0;
        if (window.innerWidth <= 1100) { cart.style.height = ''; return; }
        const stickyTop = parseFloat(getComputedStyle(cart).top) || 80;
        const top = Math.max(cart.getBoundingClientRect().top, stickyTop);
        cart.style.height = Math.max(380, Math.floor(window.innerHeight - top - 12)) + 'px';
      };
      const schedule = () => { if (!frame) frame = requestAnimationFrame(fit); };
      window.addEventListener('scroll', schedule, {passive: true});
      window.addEventListener('resize', schedule);
      document.querySelectorAll('[data-view]').forEach((b) => b.addEventListener('click', () => setTimeout(fit, 0)));
      fit();
    })();

    // ---------- Keyboard ----------
    document.addEventListener('keydown', (event) => {
      const typing = event.target instanceof Element && event.target.closest('input, textarea, select');
      if (modal.classList.contains('active')) {
        if (event.key === 'Escape') closeModal();
        return;
      }
      if (checkoutOpen()) {
        if (event.key === 'Escape') { event.preventDefault(); closeCheckout(); return; }
        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) { event.preventDefault(); completeSale(); return; }
        if (!typing && /^[1-6]$/.test(event.key)) {
          const button = document.querySelector('.pay-method[data-method="' + METHOD_KEYS[Number(event.key) - 1] + '"]');
          if (button && !button.disabled) { event.preventDefault(); setMethod(button.dataset.method); }
        }
        if (event.key === 'Tab') {
          const focusable = Array.from(checkout.querySelectorAll('button:not([disabled]), input, a[href]')).filter((el) => el.offsetParent !== null);
          if (focusable.length) {
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
          }
        }
        return;
      }
      if (event.key === '/' && !typing) { event.preventDefault(); $('catalogSearch').focus(); }
      if (event.key === 'F2') {
        event.preventDefault();
        if (state.customer) { state.customer = null; renderCustomer(); }
        $('custSearch').focus();
      }
      if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) { event.preventDefault(); openCheckout(); }
    });

    loadCatalog();
    loadStats();
    renderCart();
    renderCustomer();
  })();
  </script>
</body>
</html>
