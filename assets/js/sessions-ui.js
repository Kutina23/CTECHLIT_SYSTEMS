/**
 * Shared Sessions UI: formatting helpers and the start / extend / move / end / collect dialogs.
 * Used by portal/sessions.php and portal/session-history.php.
 */
(function () {
    'use strict';

    const CURRENCY = window.CTL_CURRENCY || 'GHS';
    const METHODS = [
        ['cash', 'fa-money-bill-wave', 'Cash'],
        ['mobile_money', 'fa-mobile-screen', 'MoMo'],
        ['card', 'fa-credit-card', 'Card'],
        ['bank_transfer', 'fa-building-columns', 'Bank'],
        ['wallet', 'fa-wallet', 'Wallet'],
        ['account', 'fa-file-invoice', 'On account']
    ];

    const esc = (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; };
    const money = (v) => CURRENCY + ' ' + Number(v || 0).toLocaleString('en-GH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const pad = (n) => String(n).padStart(2, '0');
    const clock = (seconds) => {
        seconds = Math.max(0, Math.floor(seconds));
        const h = Math.floor(seconds / 3600);
        return (h ? h + ':' : '') + pad(Math.floor(seconds % 3600 / 60)) + ':' + pad(seconds % 60);
    };
    const duration = (minutes) => {
        minutes = Math.max(0, Math.round(Number(minutes || 0)));
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return h ? h + ' h' + (m ? ' ' + m + ' min' : '') : m + ' min';
    };
    const notify = (message, type) => window.ModalController ? ModalController.showNotification(message, type || 'success') : CtlDialog.alert({message});

    async function request(url, options) {
        const response = await fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        }, options || {}));
        let result = {};
        try { result = await response.json(); } catch (e) { /* non-JSON */ }
        if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Request failed (HTTP ' + response.status + ')');
        return result;
    }

    // ---------- Modal ----------
    const modal = () => document.getElementById('sesModal');
    function openModal(title, body, footer) {
        const m = modal();
        document.getElementById('sesModalTitle').textContent = title;
        document.getElementById('sesModalBody').innerHTML = body;
        document.getElementById('sesModalFooter').innerHTML = footer;
        m.classList.add('active');
        document.body.style.overflow = 'hidden';
        const first = m.querySelector('select, input:not([type=radio]):not([type=checkbox])');
        if (first) first.focus();
    }
    function closeModal() {
        const m = modal();
        if (!m) return;
        m.classList.remove('active');
        document.body.style.overflow = '';
    }
    function modalOpen() {
        const m = modal();
        return Boolean(m && m.classList.contains('active')) || Boolean(document.querySelector('.ctl-dlg-backdrop'));
    }
    document.addEventListener('click', (event) => {
        const m = modal();
        if (m && (event.target === m || (event.target.closest('[data-close]') && m.contains(event.target)))) closeModal();
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal() && modal().classList.contains('active')) closeModal(); });

    function paymentPicker(name, options) {
        options = options || {};
        const exclude = options.exclude || [];
        return '<div><span class="form-label">' + (options.label || 'Payment') + '</span><div class="ses-methods" role="radiogroup">'
            + METHODS.filter(([value]) => !exclude.includes(value)).map(([value, icon, label], i) =>
                '<label><input type="radio" name="' + name + '" value="' + value + '"' + (i === 0 ? ' checked' : '') + '><i class="fas ' + icon + '"></i>' + label + '</label>').join('')
            + '</div>' + (options.note ? '<div class="ses-note">' + options.note + '</div>' : '') + '</div>'
            + '<div data-ref><label class="form-label" for="sesRef">Reference (MoMo/bank ID, optional)</label><input class="form-input" id="sesRef" maxlength="100"></div>';
    }
    const picked = (name) => { const node = document.querySelector('input[name="' + name + '"]:checked'); return node ? node.value : ''; };

    async function submitWith(button, work) {
        button.disabled = true;
        try {
            await work();
        } catch (error) {
            notify(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    }

    // ---------- Start ----------
    let optionsCache = null;
    async function openStart(onDone, preset) {
        let options;
        try {
            options = (await request('api/sessions.php?options=1')).data;
            optionsCache = options;
        } catch (error) {
            notify(error.message, 'error');
            return;
        }
        const free = options.stations.filter((s) => !Number(s.in_use) && s.status !== 'maintenance');
        if (!free.length) {
            CtlDialog.alert({title: 'No free stations', message: 'Every station is in use or under maintenance. End a session first.', tone: 'warning', icon: 'fa-desktop'});
            return;
        }
        if (!options.customers.length) {
            CtlDialog.alert({title: 'No customers yet', message: 'Add the customer on the Customers page (or sell time at the POS, which creates them automatically).', tone: 'warning'});
            return;
        }
        const rate = Number(options.hourly_rate);
        openModal('Start a session',
            '<div class="ses-form">'
            + '<div><label class="form-label" for="sesCustomerFilter">Customer</label>'
            + '<input class="form-input" id="sesCustomerFilter" type="search" placeholder="Type to filter by name or phone" autocomplete="off" style="margin-bottom:6px">'
            + '<select class="form-input" id="sesCustomer" size="5">' + options.customers.map((c) => '<option value="' + c.user_id + '" data-wallet="' + c.wallet_balance + '">' + esc(c.full_name + (c.phone ? ' · ' + c.phone : '')) + '</option>').join('') + '</select>'
            + '<div class="ses-note" id="sesWallet"></div></div>'
            + '<div><label class="form-label" for="sesStation">Station</label><select class="form-input" id="sesStation">'
            + free.map((s) => '<option value="' + s.station_id + '"' + (preset && Number(preset.station_id) === Number(s.station_id) ? ' selected' : '') + '>' + esc(s.station_code + ' — ' + s.name + (Number(s.agent_online) ? '' : ' (agent offline)')) + '</option>').join('') + '</select></div>'
            + '<div><span class="form-label">Billing</span><div class="ses-segment" role="radiogroup">'
            + '<label><input type="radio" name="sesMode" value="fixed" checked> <strong>Fixed time</strong><small>Customer pays now</small></label>'
            + '<label><input type="radio" name="sesMode" value="open"> <strong>Open</strong><small>Pay when it ends · ' + money(rate) + '/hr</small></label></div></div>'
            + '<div data-fixed><span class="form-label">Duration</span><div class="ses-chips" role="group">'
            + [30, 60, 120, 180, 300].map((m, i) => '<button type="button" data-min="' + m + '"' + (i === 1 ? ' aria-pressed="true"' : ' aria-pressed="false"') + '>' + duration(m) + '</button>').join('')
            + '<input class="form-input" id="sesMinutes" type="number" min="5" max="1440" step="5" value="60" aria-label="Minutes" style="width:96px"></div>'
            + '<div class="ses-total" id="sesTotal"></div></div>'
            + '<div data-fixed>' + paymentPicker('sesPay', {note: '“On account” needs a credit limit on the customer (Customer Billing).'}) + '</div>'
            + '<div><label class="form-label" for="sesNotes">Notes (optional)</label><input class="form-input" id="sesNotes" maxlength="200"></div>'
            + '</div>',
            '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="sesSubmit"><i class="fas fa-play"></i> Start</button>');

        const customer = document.getElementById('sesCustomer');
        const minutes = document.getElementById('sesMinutes');
        customer.selectedIndex = 0;
        const updateWallet = () => {
            const option = customer.selectedOptions[0];
            document.getElementById('sesWallet').textContent = option ? 'Wallet balance: ' + money(option.dataset.wallet) : '';
        };
        const updateTotal = () => {
            const m = Math.max(0, Number(minutes.value) || 0);
            document.getElementById('sesTotal').innerHTML = duration(m) + ' · <strong>' + money(m / 60 * rate) + '</strong>';
            document.querySelectorAll('[data-min]').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.min) === m)));
        };
        const updateMode = () => {
            const fixed = picked('sesMode') === 'fixed';
            document.querySelectorAll('[data-fixed]').forEach((node) => { node.hidden = !fixed; });
        };
        document.getElementById('sesCustomerFilter').addEventListener('input', (event) => {
            const query = event.target.value.trim().toLowerCase();
            let firstVisible = null;
            Array.from(customer.options).forEach((option) => {
                option.hidden = query !== '' && !option.textContent.toLowerCase().includes(query);
                if (!option.hidden && !firstVisible) firstVisible = option;
            });
            if (firstVisible && customer.selectedOptions[0] && customer.selectedOptions[0].hidden) { firstVisible.selected = true; updateWallet(); }
        });
        customer.addEventListener('change', updateWallet);
        minutes.addEventListener('input', updateTotal);
        document.querySelectorAll('[data-min]').forEach((b) => b.addEventListener('click', () => { minutes.value = b.dataset.min; updateTotal(); }));
        document.querySelectorAll('input[name=sesMode]').forEach((r) => r.addEventListener('change', updateMode));
        updateWallet(); updateTotal(); updateMode();

        document.getElementById('sesSubmit').addEventListener('click', (event) => submitWith(event.target, async () => {
            if (!customer.value) throw new Error('Choose a customer.');
            const fixed = picked('sesMode') === 'fixed';
            const payload = {
                action: 'start', user_id: Number(customer.value), station_id: Number(document.getElementById('sesStation').value),
                duration_minutes: fixed ? Number(minutes.value) : 0, payment_method: fixed ? picked('sesPay') : '',
                reference: document.getElementById('sesRef').value, notes: document.getElementById('sesNotes').value
            };
            if (fixed && !(payload.duration_minutes >= 5)) throw new Error('Enter at least 5 minutes.');
            const result = await request('api/sessions.php', {method: 'POST', body: JSON.stringify(payload)});
            closeModal();
            notify(result.message);
            if (onDone) onDone();
        }));
    }

    // ---------- Extend ----------
    function openExtend(session, rate, onDone) {
        const prepaid = session.prepaid;
        openModal('Add time — ' + session.station_code,
            '<div class="ses-form"><div class="ses-summary">' + esc(session.customer_name) + ' · ' + duration(Math.max(0, session.minutes_left)) + ' left</div>'
            + '<div><span class="form-label">Extra time</span><div class="ses-chips">'
            + [15, 30, 60, 120].map((m, i) => '<button type="button" data-min="' + m + '" aria-pressed="' + (i === 2) + '">+' + duration(m) + '</button>').join('')
            + '<input class="form-input" id="sesMinutes" type="number" min="5" max="720" step="5" value="60" aria-label="Minutes" style="width:96px"></div>'
            + '<div class="ses-total" id="sesTotal"></div></div>'
            + (prepaid ? paymentPicker('sesPay', {label: 'Payment for the extra time'}) : '<div class="ses-note">This session isn’t prepaid — the extra time is charged when it ends.</div>')
            + '</div>',
            '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="sesSubmit"><i class="fas fa-plus"></i> Add time</button>');
        const minutes = document.getElementById('sesMinutes');
        const update = () => {
            const m = Math.max(0, Number(minutes.value) || 0);
            document.getElementById('sesTotal').innerHTML = '+' + duration(m) + (prepaid ? ' · <strong>' + money(m / 60 * rate) + '</strong>' : '');
            document.querySelectorAll('[data-min]').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.min) === m)));
        };
        minutes.addEventListener('input', update);
        document.querySelectorAll('[data-min]').forEach((b) => b.addEventListener('click', () => { minutes.value = b.dataset.min; update(); }));
        update();
        document.getElementById('sesSubmit').addEventListener('click', (event) => submitWith(event.target, async () => {
            const result = await request('api/sessions.php', {method: 'POST', body: JSON.stringify({
                action: 'extend', session_id: session.session_id, minutes: Number(minutes.value),
                payment_method: prepaid ? picked('sesPay') : '', reference: prepaid ? document.getElementById('sesRef').value : ''
            })});
            closeModal();
            notify(result.message);
            if (onDone) onDone();
        }));
    }

    // ---------- Move ----------
    async function openMove(session, onDone) {
        let options;
        try { options = (await request('api/sessions.php?options=1')).data; } catch (error) { notify(error.message, 'error'); return; }
        const free = options.stations.filter((s) => !Number(s.in_use) && s.status !== 'maintenance');
        if (!free.length) {
            CtlDialog.alert({title: 'No free stations', message: 'There is no free station to move this session to.', tone: 'warning', icon: 'fa-desktop'});
            return;
        }
        openModal('Move session — ' + session.customer_name,
            '<div class="ses-form"><div class="ses-summary">Currently on <strong>' + esc(session.station_code) + '</strong>. Time and charges carry over.</div>'
            + '<div><label class="form-label" for="sesStation">Move to</label><select class="form-input" id="sesStation">'
            + free.map((s) => '<option value="' + s.station_id + '">' + esc(s.station_code + ' — ' + s.name) + '</option>').join('') + '</select></div></div>',
            '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="sesSubmit"><i class="fas fa-right-left"></i> Move</button>');
        document.getElementById('sesSubmit').addEventListener('click', (event) => submitWith(event.target, async () => {
            const result = await request('api/sessions.php', {method: 'POST', body: JSON.stringify({action: 'move', session_id: session.session_id, station_id: Number(document.getElementById('sesStation').value)})});
            closeModal();
            notify(result.message);
            if (onDone) onDone();
        }));
    }

    // ---------- End ----------
    function openEnd(session, rate, elapsedSeconds, onDone) {
        const minutes = Math.floor(elapsedSeconds / 60);
        const charge = Math.round(minutes / 60 * rate * 100) / 100;
        const payNow = !session.prepaid && charge > 0;
        const wallet = Number(session.wallet_balance || 0);
        openModal('End session — ' + session.station_code,
            '<div class="ses-form">'
            + '<div class="ses-receipt"><div><span>Customer</span><strong>' + esc(session.customer_name) + '</strong></div>'
            + '<div><span>Time used</span><strong>' + duration(minutes) + '</strong></div>'
            + (session.prepaid
                ? '<div><span>Paid in advance</span><strong>' + money(Number(session.paid_amount) + Number(session.owed_amount)) + '</strong></div>'
                : '<div class="big"><span>Amount due</span><strong>' + money(charge) + '</strong></div>') + '</div>'
            + (payNow
                ? (wallet >= charge
                    ? '<div class="ses-note ok"><i class="fas fa-wallet"></i> Wallet has ' + money(wallet) + ' — it will be paid from the wallet automatically.</div>'
                    : paymentPicker('sesPay', {label: 'How is the customer paying?', exclude: ['wallet'], note: 'Choose “On account” to add it to their unpaid charges.'}))
                : '')
            + '<label class="ses-check"><input type="checkbox" id="sesLock" checked> Lock the PC when the session ends</label>'
            + '</div>',
            '<button class="btn btn-secondary" type="button" data-close>Keep running</button><button class="btn btn-danger" type="button" id="sesSubmit"><i class="fas fa-stop"></i> End session</button>');
        document.getElementById('sesSubmit').addEventListener('click', (event) => submitWith(event.target, async () => {
            const method = payNow && wallet < charge ? picked('sesPay') : '';
            const result = await request('api/sessions.php', {method: 'POST', body: JSON.stringify({
                action: 'end', session_id: session.session_id, payment_method: method,
                reference: document.getElementById('sesRef') ? document.getElementById('sesRef').value : '',
                lock_station: document.getElementById('sesLock').checked
            })});
            closeModal();
            notify(result.message);
            if (onDone) onDone(result);
        }));
    }

    // ---------- Collect an unpaid session charge ----------
    function openSettle(session, onDone) {
        openModal('Collect payment — session #' + session.session_id,
            '<div class="ses-form"><div class="ses-receipt"><div><span>Customer</span><strong>' + esc(session.customer_name) + '</strong></div>'
            + '<div><span>Station</span><strong>' + esc(session.station_code) + '</strong></div>'
            + '<div class="big"><span>Unpaid</span><strong>' + money(session.owed_amount) + '</strong></div></div>'
            + paymentPicker('sesPay', {exclude: ['account'], note: 'Wallet balance: ' + money(session.wallet_balance)}) + '</div>',
            '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="sesSubmit"><i class="fas fa-check"></i> Record payment</button>');
        document.getElementById('sesSubmit').addEventListener('click', (event) => submitWith(event.target, async () => {
            const result = await request('api/sessions.php', {method: 'POST', body: JSON.stringify({action: 'settle', session_id: session.session_id, payment_method: picked('sesPay'), reference: document.getElementById('sesRef').value})});
            closeModal();
            notify(result.message);
            if (onDone) onDone();
        }));
    }

    window.SessionsUI = {esc, money, clock, duration, notify, request, openStart, openExtend, openMove, openEnd, openSettle, modalOpen, closeModal};
})();
