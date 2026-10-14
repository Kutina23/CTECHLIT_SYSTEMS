/**
 * InvoiceDoc — renders a billing invoice (from api/billing.php?invoice_id=N) as printable HTML.
 *
 *   InvoiceDoc.render(invoice, business, template)  → HTML string   (template: classic | modern | minimal)
 *   InvoiceDoc.print(invoice, business, template)   → opens the browser print dialog (Save as PDF works too)
 */
(function () {
    'use strict';

    const CURRENCY = () => window.CTL_CURRENCY || 'GHS';
    const esc = (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; };
    const money = (v) => CURRENCY() + ' ' + Number(v || 0).toLocaleString('en-GH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const date = (v) => v ? new Date(/^\d{4}-\d{2}-\d{2}$/.test(String(v)) ? v + 'T00:00:00' : String(v).replace(' ', 'T')).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'}) : '—';
    const label = (s) => String(s || '').replace('_', ' ').replace(/^./, (c) => c.toUpperCase());

    const THEMES = {
        classic: {accent: '#7C3AED', head: '#ffffff', headInk: '#0f172a', band: '#F5F3FF'},
        modern: {accent: '#06B6D4', head: '#0f172a', headInk: '#ffffff', band: '#ECFEFF'},
        minimal: {accent: '#0f172a', head: '#ffffff', headInk: '#0f172a', band: '#F8FAFC'}
    };
    const STAMP = {paid: '#059669', overdue: '#DC2626', void: '#64748B', issued: '#D97706', partially_paid: '#D97706'};

    function css(t) {
        return '.invdoc{font:13px/1.5 Inter,system-ui,sans-serif;color:#0f172a;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0}'
            + '.invdoc-head{display:flex;justify-content:space-between;gap:20px;padding:26px 28px;background:' + t.head + ';color:' + t.headInk + ';border-bottom:3px solid ' + t.accent + '}'
            + '.invdoc-head h3{margin:0;font-size:20px}.invdoc-head .muted{opacity:.75;font-size:12px}'
            + '.invdoc-title{text-align:right}.invdoc-title .word{font-size:26px;font-weight:800;letter-spacing:.08em;color:' + (t.head === '#ffffff' ? t.accent : t.headInk) + '}'
            + '.invdoc-body{padding:24px 28px}'
            + '.invdoc-meta{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px}'
            + '.invdoc-meta .box{padding:12px 14px;border-radius:10px;background:' + t.band + '}'
            + '.invdoc-meta .k{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:700}'
            + '.invdoc table{width:100%;border-collapse:collapse;margin:8px 0 14px}'
            + '.invdoc th{padding:9px 8px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:2px solid ' + t.accent + '}'
            + '.invdoc td{padding:9px 8px;border-bottom:1px solid #eef2f7}.invdoc .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}'
            + '.invdoc-totals{margin-left:auto;width:280px}.invdoc-totals div{display:flex;justify-content:space-between;padding:4px 0}'
            + '.invdoc-totals .grand{margin-top:6px;padding:10px 12px;border-radius:10px;background:' + t.accent + ';color:#fff;font-weight:800;font-size:15px}'
            + '.invdoc-stamp{display:inline-block;margin-top:8px;padding:3px 10px;border:2px solid currentColor;border-radius:6px;font-weight:800;font-size:11px;letter-spacing:.12em;text-transform:uppercase}'
            + '.invdoc-foot{padding:14px 28px;background:' + t.band + ';font-size:12px;color:#475569}'
            + '@media print{.invdoc{border:0;border-radius:0}}';
    }

    function render(inv, business, template) {
        const name = THEMES[template] ? template : 'classic';
        const t = THEMES[name];
        const status = inv.display_status || inv.status;
        const due = status === 'void' ? 0 : Number(inv.balance_due ?? (inv.total_amount - inv.amount_paid));
        business = business || {};
        // Scope every rule to this template so several invoices can share a page.
        const scoped = css(t).split('.invdoc').join('.tpl-' + name + ' .invdoc');
        return '<div class="tpl-' + name + '"><style>' + scoped + '</style><div class="invdoc">'
            + '<div class="invdoc-head"><div><h3>' + esc(business.name || '') + '</h3>'
            + '<div class="muted">' + esc(business.address || '') + '</div>'
            + '<div class="muted">' + esc([business.phone, business.email].filter(Boolean).join(' · ')) + '</div></div>'
            + '<div class="invdoc-title"><div class="word">INVOICE</div><div><strong>' + esc(inv.invoice_number) + '</strong></div>'
            + '<span class="invdoc-stamp" style="color:' + (STAMP[status] || '#475569') + (t.head !== '#ffffff' ? ';background:#fff' : '') + '">' + esc(label(status)) + '</span></div></div>'
            + '<div class="invdoc-body"><div class="invdoc-meta">'
            + '<div class="box"><div class="k">Bill to</div><strong>' + esc(inv.customer_name) + '</strong><br>' + esc(inv.account_number || '')
            + '<br>' + esc([inv.customer_phone, inv.customer_email].filter(Boolean).join(' · ')) + '</div>'
            + '<div class="box"><div class="k">Details</div>Issued: <strong>' + date(inv.issue_date) + '</strong><br>Due: <strong>' + date(inv.due_date) + '</strong>'
            + (inv.created_by_name ? '<br>Prepared by: ' + esc(inv.created_by_name) : '') + '</div></div>'
            + '<table><thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead><tbody>'
            + (inv.items || []).map((i) => '<tr><td>' + esc(i.description) + '</td><td class="num">' + Number(i.quantity) + '</td><td class="num">' + money(i.unit_price) + '</td><td class="num">' + money(i.total_price) + '</td></tr>').join('')
            + '</tbody></table><div class="invdoc-totals">'
            + '<div><span>Subtotal</span><span class="num">' + money(inv.subtotal) + '</span></div>'
            + (Number(inv.discount_amount) > 0 ? '<div><span>Discount</span><span class="num">−' + money(inv.discount_amount) + '</span></div>' : '')
            + '<div><span>Total</span><strong class="num">' + money(inv.total_amount) + '</strong></div>'
            + '<div><span>Paid</span><span class="num">' + money(inv.amount_paid) + '</span></div>'
            + '<div class="grand"><span>Balance due</span><span class="num">' + money(due) + '</span></div></div>'
            + ((inv.payments || []).length ? '<p style="margin-top:16px"><strong>Payments received</strong><br>' + inv.payments.map((p) => date(p.created_at) + ' — ' + money(p.amount) + ' by ' + esc(label(p.payment_method)) + (p.reference ? ' (ref ' + esc(p.reference) + ')' : '')).join('<br>') + '</p>' : '')
            + (inv.notes ? '<p style="margin-top:12px;white-space:pre-wrap"><strong>Notes</strong><br>' + esc(inv.notes) + '</p>' : '')
            + '</div><div class="invdoc-foot">Thank you for your business. Please quote <strong>' + esc(inv.invoice_number) + '</strong> with your payment.</div></div></div>';
    }

    function print(inv, business, template) {
        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
        document.body.appendChild(frame);
        const doc = frame.contentDocument;
        doc.open();
        doc.write('<!doctype html><html><head><meta charset="utf-8"><title>' + esc(inv.invoice_number) + '</title>'
            + '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">'
            + '<style>@page{margin:14mm}body{margin:0}</style></head><body>' + render(inv, business, template) + '</body></html>');
        doc.close();
        setTimeout(() => {
            frame.contentWindow.focus();
            frame.contentWindow.print();
            setTimeout(() => frame.remove(), 1500);
        }, 500);
    }

    window.InvoiceDoc = {render, print, templates: Object.keys(THEMES)};
})();
