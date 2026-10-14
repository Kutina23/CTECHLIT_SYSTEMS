/**
 * Shared payment dialogs: wallet top-up at the counter or by online link (Paystack).
 *
 *   CtlPayments.openTopUp({userId, name, owed, apiBase, onDone})
 *   CtlPayments.openOnlinePayment({userId, name, amount, purpose: 'invoice', invoiceId, apiBase, onDone})
 *
 * apiBase defaults to "api/" (pages in /portal).
 */
(function () {
    'use strict';

    const CURRENCY = window.CTL_CURRENCY || 'GHS';
    let configPromise = null;

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function money(value) {
        return CURRENCY + ' ' + Number(value || 0).toLocaleString('en-GH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function notify(message, type) {
        if (window.ModalController && ModalController.showNotification) ModalController.showNotification(message, type || 'success');
        else window.alert(message);
    }

    async function request(url, options) {
        const response = await fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        }, options || {}));
        let result = {};
        try { result = await response.json(); } catch (error) { /* non-JSON */ }
        if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Request failed (HTTP ' + response.status + ')');
        return result;
    }

    function config(apiBase) {
        if (!configPromise) {
            configPromise = request(apiBase + 'payments.php?config=1').then((r) => r.data).catch(() => ({online_enabled: false, can_collect: false}));
        }
        return configPromise;
    }

    function dialog(title, bodyHtml) {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = '<div class="modal"><div class="modal-header"><h3 class="modal-title">' + esc(title) + '</h3>'
            + '<button class="modal-close" type="button" data-close aria-label="Close"><i class="fas fa-times"></i></button></div>'
            + '<div class="modal-body">' + bodyHtml + '</div><div class="modal-footer"></div></div>';
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        const close = () => { overlay.remove(); document.body.style.overflow = ''; document.removeEventListener('keydown', onKey); };
        const onKey = (event) => { if (event.key === 'Escape') close(); };
        document.addEventListener('keydown', onKey);
        overlay.addEventListener('click', (event) => { if (event.target === overlay || event.target.closest('[data-close]')) close(); });
        return {overlay, close, footer: overlay.querySelector('.modal-footer'), body: overlay.querySelector('.modal-body')};
    }

    function showLink(link, name, apiBase, onDone) {
        const view = dialog('Pay online — ' + name,
            '<p style="margin-top:0">Send this link to the customer, or open it on the counter device. They can pay ' + money(link.amount) + ' with Mobile Money or card.</p>'
            + '<div style="display:flex;gap:8px"><input class="form-input" readonly value="' + esc(link.authorization_url) + '" aria-label="Payment link" style="flex:1"><button class="btn btn-secondary" type="button" data-copy><i class="fas fa-copy"></i></button></div>'
            + '<p class="bill-hint" style="font-size:12px;color:var(--muted);margin-bottom:0">Reference ' + esc(link.reference) + '. The account is credited automatically when Paystack confirms the payment; use “Check payment” if it does not update.</p>');
        view.footer.innerHTML = '<a class="btn btn-secondary" target="_blank" rel="noopener" href="' + esc(link.authorization_url) + '"><i class="fas fa-up-right-from-square"></i> Open</a>'
            + '<button class="btn btn-primary" type="button" data-verify>Check payment</button>';
        view.body.querySelector('[data-copy]').addEventListener('click', async () => {
            try { await navigator.clipboard.writeText(link.authorization_url); notify('Payment link copied'); } catch (error) { view.body.querySelector('input').select(); }
        });
        view.footer.querySelector('[data-verify]').addEventListener('click', async (event) => {
            event.target.disabled = true;
            try {
                const result = await request(apiBase + 'payments.php', {method: 'POST', body: JSON.stringify({action: 'verify', reference: link.reference})});
                notify(result.message, result.data.status === 'succeeded' ? 'success' : 'warning');
                if (result.data.status === 'succeeded') { view.close(); if (onDone) onDone(result); }
            } catch (error) {
                notify(error.message, 'error');
            } finally {
                event.target.disabled = false;
            }
        });
    }

    async function openTopUp(options) {
        const apiBase = options.apiBase || 'api/';
        const settings = await config(apiBase);
        if (!settings.can_collect) {
            notify('You need the Manage Billing or Manage POS Sales permission to take payments.', 'error');
            return;
        }
        const owed = Number(options.owed || 0);
        const view = dialog('Top up wallet — ' + options.name,
            '<div style="display:grid;gap:14px">'
            + '<div role="group" aria-label="Payment channel" style="display:flex;gap:6px">'
            + '<button type="button" class="btn btn-primary small" data-channel="counter" aria-pressed="true"><i class="fas fa-cash-register"></i> At the counter</button>'
            + '<button type="button" class="btn btn-secondary small" data-channel="online" aria-pressed="false"' + (settings.online_enabled ? '' : ' disabled title="Add PAYSTACK_SECRET_KEY to config/.env to enable"') + '><i class="fas fa-globe"></i> Online (MoMo / card)</button></div>'
            + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div><label class="form-label" for="tuAmount">Amount (' + esc(CURRENCY) + ')</label><input class="form-input" id="tuAmount" type="number" min="0.01" step="0.01" value="' + (owed > 0 ? owed.toFixed(2) : '') + '"></div>'
            + '<div data-counter-only><label class="form-label" for="tuMethod">Method</label><select class="form-input" id="tuMethod"><option value="cash">Cash</option><option value="mobile_money">Mobile Money</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option></select></div>'
            + '<div data-online-only hidden><label class="form-label" for="tuEmail">Customer email (optional)</label><input class="form-input" id="tuEmail" type="email" maxlength="255"></div></div>'
            + '<div data-counter-only><label class="form-label" for="tuRef">Reference (MoMo/bank ID, optional)</label><input class="form-input" id="tuRef" maxlength="100"></div>'
            + '<div style="display:flex;flex-wrap:wrap;gap:6px">' + [5, 10, 20, 50, 100].map((v) => '<button type="button" class="btn btn-secondary small" data-quick="' + v + '">' + money(v) + '</button>').join('') + '</div>'
            + (owed > 0 ? '<label style="display:flex;gap:8px;align-items:center;font-size:13px"><input type="checkbox" id="tuSettle" checked> Use it to pay the ' + money(owed) + ' this customer owes</label>' : '')
            + '</div>');
        let channel = 'counter';
        const body = view.body;
        body.querySelectorAll('[data-channel]').forEach((button) => button.addEventListener('click', () => {
            channel = button.dataset.channel;
            body.querySelectorAll('[data-channel]').forEach((b) => {
                const active = b === button;
                b.setAttribute('aria-pressed', String(active));
                b.className = 'btn small ' + (active ? 'btn-primary' : 'btn-secondary');
            });
            body.querySelectorAll('[data-counter-only]').forEach((node) => { node.hidden = channel !== 'counter'; });
            body.querySelectorAll('[data-online-only]').forEach((node) => { node.hidden = channel !== 'online'; });
            submit.textContent = channel === 'online' ? 'Create payment link' : 'Top up';
        }));
        body.querySelectorAll('[data-quick]').forEach((button) => button.addEventListener('click', () => { body.querySelector('#tuAmount').value = Number(button.dataset.quick).toFixed(2); }));
        view.footer.innerHTML = '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" data-submit>Top up</button>';
        const submit = view.footer.querySelector('[data-submit]');
        body.querySelector('#tuAmount').focus();
        submit.addEventListener('click', async () => {
            const amount = Number(body.querySelector('#tuAmount').value);
            if (!(amount > 0)) { notify('Enter an amount', 'error'); return; }
            submit.disabled = true;
            try {
                if (channel === 'online') {
                    const result = await request(apiBase + 'payments.php', {method: 'POST', body: JSON.stringify({action: 'online_link', user_id: options.userId, amount, purpose: 'wallet_topup', email: body.querySelector('#tuEmail').value})});
                    view.close();
                    showLink(result.data, options.name, apiBase, options.onDone);
                    return;
                }
                const settle = body.querySelector('#tuSettle');
                const result = await request(apiBase + 'payments.php', {method: 'POST', body: JSON.stringify({
                    action: 'topup', user_id: options.userId, amount,
                    payment_method: body.querySelector('#tuMethod').value, reference: body.querySelector('#tuRef').value,
                    settle: settle ? settle.checked : false
                })});
                view.close();
                notify(result.message);
                if (options.onDone) options.onDone(result);
            } catch (error) {
                notify(error.message, 'error');
            } finally {
                submit.disabled = false;
            }
        });
    }

    async function openOnlinePayment(options) {
        const apiBase = options.apiBase || 'api/';
        try {
            const result = await request(apiBase + 'payments.php', {method: 'POST', body: JSON.stringify({
                action: 'online_link', user_id: options.userId, amount: options.amount, purpose: options.purpose || 'wallet_topup', invoice_id: options.invoiceId || null
            })});
            showLink(result.data, options.name, apiBase, options.onDone);
        } catch (error) {
            notify(error.message, 'error');
        }
    }

    window.CtlPayments = {openTopUp, openOnlinePayment, config};
})();
