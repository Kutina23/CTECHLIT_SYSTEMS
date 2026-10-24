/**
 * Print Jobs page: live per-printer queues, new-job form with price preview and payment,
 * job actions (printed, failed, resend, retry, move, priority, collect, cancel/refund, reprint) and history.
 */
(function () {
    'use strict';

    const CFG = window.PJ_CONFIG || {};
    const API = 'api/print-jobs.php';
    const CURRENCY = window.CTL_CURRENCY || 'GHS';
    const METHODS = [
        ['collect', 'fa-hand-holding-dollar', 'On collection'],
        ['cash', 'fa-money-bill-wave', 'Cash'],
        ['mobile_money', 'fa-mobile-screen', 'MoMo'],
        ['card', 'fa-credit-card', 'Card'],
        ['bank_transfer', 'fa-building-columns', 'Bank'],
        ['wallet', 'fa-wallet', 'Wallet'],
        ['account', 'fa-file-invoice', 'On account']
    ];
    const STATUS = {
        queued: ['Queued', 'queued'], printing: ['Printing', 'printing'], completed: ['Printed', 'ok'],
        failed: ['Failed', 'bad'], cancelled: ['Cancelled', 'muted']
    };
    const PAYMENT = {
        paid: ['Paid', 'ok'], unpaid: ['To collect', 'warn'], on_account: ['On account', 'info'],
        refunded: ['Refunded', 'muted'], waived: ['Waived', 'muted']
    };

    const $ = (id) => document.getElementById(id);
    const esc = (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; };
    const money = (v) => CURRENCY + ' ' + Number(v || 0).toLocaleString('en-GH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const size = (bytes) => { bytes = Number(bytes || 0); return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB'; };
    const notify = (message, type) => window.ModalController ? ModalController.showNotification(message, type || 'success') : CtlDialog.alert({message});
    const parseDate = (value) => value ? new Date(String(value).replace(' ', 'T')) : null;
    const when = (value) => {
        const d = parseDate(value);
        if (!d || isNaN(d)) return '—';
        const today = new Date();
        const time = d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        return d.toDateString() === today.toDateString() ? 'Today ' + time : d.toLocaleDateString([], {day: 'numeric', month: 'short'}) + ' ' + time;
    };
    const wait = (minutes) => { minutes = Number(minutes || 0); return minutes < 1 ? 'just now' : minutes < 60 ? minutes + ' min' : Math.floor(minutes / 60) + ' h ' + (minutes % 60) + ' min'; };
    const fileIcon = (job) => {
        const name = String(job.file_name || '').toLowerCase();
        if (/\.pdf$/.test(name)) return 'fa-file-pdf';
        if (/\.(jpe?g|png)$/.test(name)) return 'fa-file-image';
        if (/\.docx?$|\.rtf$/.test(name)) return 'fa-file-word';
        if (/\.xlsx?$/.test(name)) return 'fa-file-excel';
        if (/\.pptx?$/.test(name)) return 'fa-file-powerpoint';
        return 'fa-file-lines';
    };
    const pill = (map, key) => { const [label, tone] = map[key] || [key, 'muted']; return '<span class="pj-pill ' + tone + '">' + esc(label) + '</span>'; };

    async function request(url, options) {
        const init = Object.assign({credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}}, options || {});
        if (init.json) {
            init.method = init.method || 'POST';
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(init.json);
            delete init.json;
        }
        const response = await fetch(url, init);
        let result = {};
        try { result = await response.json(); } catch (e) { /* not JSON */ }
        if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Request failed (HTTP ' + response.status + ')');
        return result;
    }
    const act = (action, jobId, extra) => request(API, {json: Object.assign({action, print_job_id: jobId}, extra || {})});

    // ---------- Modal (shares the Sessions dialog styles) ----------
    const modal = () => $('sesModal');
    function openModal(title, body, footer) {
        $('sesModalTitle').textContent = title;
        $('sesModalBody').innerHTML = body;
        $('sesModalFooter').innerHTML = footer;
        modal().classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() { modal().classList.remove('active'); document.body.style.overflow = ''; }
    const modalOpen = () => modal().classList.contains('active') || Boolean(document.querySelector('.ctl-dlg-backdrop'));
    document.addEventListener('click', (event) => {
        if (event.target === modal() || (event.target.closest('[data-close]') && modal().contains(event.target))) closeModal();
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal().classList.contains('active')) closeModal(); });

    function methodPicker(name, options) {
        options = options || {};
        const list = METHODS.filter(([value]) => !(options.exclude || []).includes(value));
        return '<div><span class="form-label">' + (options.label || 'Payment') + '</span><div class="ses-methods pj-methods" role="radiogroup">'
            + list.map(([value, icon, label], i) => '<label><input type="radio" name="' + name + '" value="' + value + '"' + (i === 0 ? ' checked' : '') + '><i class="fas ' + icon + '"></i>' + label + '</label>').join('')
            + '</div><div class="ses-note" id="' + name + 'Note"></div></div>'
            + '<div id="' + name + 'RefWrap" hidden><label class="form-label" for="' + name + 'Ref">Reference (MoMo/bank ID, optional)</label><input class="form-input" id="' + name + 'Ref" maxlength="100"></div>';
    }
    const picked = (name) => { const node = document.querySelector('input[name="' + name + '"]:checked'); return node ? node.value : ''; };
    function wireMethodPicker(name, walletBalance) {
        const update = () => {
            const method = picked(name);
            const refWrap = $(name + 'RefWrap');
            if (refWrap) refWrap.hidden = !['mobile_money', 'card', 'bank_transfer'].includes(method);
            const note = $(name + 'Note');
            if (!note) return;
            const balance = typeof walletBalance === 'function' ? walletBalance() : walletBalance;
            note.className = 'ses-note';
            note.textContent = method === 'collect' ? 'The job prints now; take payment when the customer collects it.'
                : method === 'wallet' ? (balance == null ? 'Choose a customer to use their wallet.' : 'Wallet balance: ' + money(balance))
                : method === 'account' ? 'Added to the customer’s account and billed on their next invoice.' : '';
        };
        document.querySelectorAll('input[name="' + name + '"]').forEach((input) => input.addEventListener('change', update));
        update();
        return update;
    }
    async function busy(button, work) {
        button.disabled = true;
        const label = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Working…';
        try { await work(); } catch (error) { notify(error.message, 'error'); } finally { button.disabled = false; button.innerHTML = label; }
    }

    // ---------- Shared data ----------
    let options = null;
    async function loadOptions(force) {
        if (!options || force) options = (await request(API + '?options=1')).data;
        return options;
    }

    // ---------- New job ----------
    async function countPdfPages(file) {
        if (!/pdf$/i.test(file.type) && !/\.pdf$/i.test(file.name)) return /^image\//.test(file.type) ? 1 : null;
        const text = await file.text();
        const pages = (text.match(/\/Type\s*\/Page(?![a-zA-Z])/g) || []).length;
        return pages || null;
    }

    async function openNewJob() {
        try { await loadOptions(true); } catch (error) { notify(error.message, 'error'); return; }
        const printers = options.printers.filter((p) => p.status !== 'maintenance');
        if (!printers.length) {
            CtlDialog.alert({title: 'No printers available', message: 'Add a printer on the Printers page, or take one out of maintenance.', tone: 'warning', icon: 'fa-print'});
            return;
        }
        const prices = options.prices;
        openModal('New print job',
            '<form class="ses-form" id="pjForm" novalidate>'
            + '<label class="pj-drop" id="pjDrop"><input type="file" id="pjFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.jpg,.jpeg,.png">'
            + '<i class="fas fa-cloud-arrow-up" aria-hidden="true"></i><strong id="pjDropTitle">Drop the document here or browse</strong>'
            + '<small id="pjDropHint">PDF, Word, Excel, PowerPoint, text or image · up to 25 MB</small></label>'
            + '<div class="pj-grid2">'
            + '<div><label class="form-label" for="pjCustomerFilter">Customer</label>'
            + '<input class="form-input" id="pjCustomerFilter" type="search" placeholder="Name or phone — blank for walk-in" autocomplete="off">'
            + '<select class="form-input" id="pjCustomer" size="4"><option value="" selected>Walk-in customer</option>'
            + options.customers.map((c) => '<option value="' + c.user_id + '" data-wallet="' + c.wallet_balance + '">' + esc(c.full_name + (c.phone ? ' · ' + c.phone : '')) + '</option>').join('') + '</select></div>'
            + '<div class="ses-form"><div><label class="form-label" for="pjPrinter">Printer</label><select class="form-input" id="pjPrinter">'
            + printers.map((p) => '<option value="' + p.peripheral_id + '">' + esc(p.name + (p.status === 'offline' ? ' (offline — print manually)' : '')) + '</option>').join('') + '</select></div>'
            + '<div><label class="form-label" for="pjStation">From station (optional)</label><select class="form-input" id="pjStation"><option value="">Counter</option>'
            + options.stations.map((s) => '<option value="' + s.station_id + '">' + esc(s.station_code + ' — ' + s.name) + '</option>').join('') + '</select></div></div>'
            + '</div>'
            + '<div class="ses-segment" role="radiogroup" aria-label="Colour">'
            + '<label><input type="radio" name="pjColor" value="bw" checked> <strong>Black & white</strong><small>' + money(prices.bw) + ' per side</small></label>'
            + '<label><input type="radio" name="pjColor" value="color"> <strong>Colour</strong><small>' + money(prices.color) + ' per side</small></label></div>'
            + '<div class="pj-grid3">'
            + '<div><label class="form-label" for="pjPages">Pages</label><input class="form-input" id="pjPages" type="number" min="1" max="5000" inputmode="numeric" placeholder="Auto"></div>'
            + '<div><label class="form-label" for="pjCopies">Copies</label><input class="form-input" id="pjCopies" type="number" min="1" max="500" value="1" inputmode="numeric"></div>'
            + '<div class="pj-checks"><label><input type="checkbox" id="pjDuplex"> Double-sided</label><label><input type="checkbox" id="pjUrgent"> Urgent (front of queue)</label></div>'
            + '</div>'
            + methodPicker('pjPay')
            + '<div><label class="form-label" for="pjNotes">Notes for the operator (optional)</label><input class="form-input" id="pjNotes" maxlength="255" placeholder="e.g. staple, pages 2–5 only"></div>'
            + '<div class="pj-total" id="pjTotal"><span>Total</span><strong>—</strong></div>'
            + '</form>',
            '<button class="btn btn-secondary" type="button" data-close>Cancel</button>'
            + '<button class="btn btn-primary" type="button" id="pjSubmit"><i class="fas fa-print"></i> Queue job</button>');

        const fileInput = $('pjFile');
        const drop = $('pjDrop');
        const customer = $('pjCustomer');
        let detected = null;
        const wallet = () => { const o = customer.selectedOptions[0]; return o && o.value ? Number(o.dataset.wallet) : null; };
        const updateMethodNote = wireMethodPicker('pjPay', wallet);

        const recalc = () => {
            const pages = Number($('pjPages').value) || detected;
            const copies = Math.max(1, Number($('pjCopies').value) || 1);
            const unit = Number(prices[picked('pjColor')] || 0);
            const total = $('pjTotal');
            total.innerHTML = pages
                ? '<span>' + pages + ' page' + (pages === 1 ? '' : 's') + ' × ' + copies + ' cop' + (copies === 1 ? 'y' : 'ies') + ' × ' + money(unit) + '</span><strong>' + money(pages * copies * unit) + '</strong>'
                : '<span>Pages will be counted when the file is uploaded' + (fileInput.files[0] ? ' — enter them for Word/Excel files without page info' : '') + '</span><strong>—</strong>';
        };
        const setFile = async (file) => {
            if (!file) return;
            if (file.size > 25 * 1024 * 1024) { notify('Choose a file up to 25 MB.', 'error'); fileInput.value = ''; return; }
            $('pjDropTitle').textContent = file.name;
            $('pjDropHint').textContent = size(file.size) + ' · click to change';
            drop.classList.add('has-file');
            detected = null;
            try { detected = await countPdfPages(file); } catch (e) { detected = null; }
            $('pjPages').placeholder = detected ? detected + ' (detected)' : 'Required';
            recalc();
        };
        fileInput.addEventListener('change', () => setFile(fileInput.files[0]));
        ['dragenter', 'dragover'].forEach((type) => drop.addEventListener(type, (e) => { e.preventDefault(); drop.classList.add('drag'); }));
        ['dragleave', 'drop'].forEach((type) => drop.addEventListener(type, () => drop.classList.remove('drag')));
        drop.addEventListener('drop', (e) => {
            e.preventDefault();
            if (!e.dataTransfer.files.length) return;
            const dt = new DataTransfer();
            dt.items.add(e.dataTransfer.files[0]);
            fileInput.files = dt.files;
            setFile(fileInput.files[0]);
        });
        $('pjCustomerFilter').addEventListener('input', (e) => {
            const term = e.target.value.trim().toLowerCase();
            let first = null;
            Array.from(customer.options).forEach((o) => {
                const match = !o.value || o.text.toLowerCase().includes(term);
                o.hidden = !match;
                if (match && o.value && !first) first = o;
            });
            if (term && first) customer.value = first.value;
            updateMethodNote();
        });
        customer.addEventListener('change', updateMethodNote);
        ['pjPages', 'pjCopies'].forEach((id) => $(id).addEventListener('input', recalc));
        document.querySelectorAll('input[name="pjColor"]').forEach((input) => input.addEventListener('change', recalc));
        recalc();

        $('pjSubmit').addEventListener('click', (event) => busy(event.currentTarget, async () => {
            const file = fileInput.files[0];
            if (!file) throw new Error('Choose the document to print.');
            const pages = Number($('pjPages').value) || detected;
            if (!pages && !/\.(docx|pptx)$/i.test(file.name)) throw new Error('Enter the number of pages.');
            const method = picked('pjPay');
            if (!customer.value && ['wallet', 'account'].includes(method)) throw new Error('Choose a customer to charge their wallet or account.');
            const walkIn = $('pjCustomerFilter').value.trim();
            const data = new FormData();
            data.append('file', file);
            data.append('peripheral_id', $('pjPrinter').value);
            if (customer.value) data.append('user_id', customer.value);
            else if (walkIn) data.append('customer_name', walkIn);
            if ($('pjStation').value) data.append('station_id', $('pjStation').value);
            if ($('pjPages').value) data.append('pages', $('pjPages').value);
            data.append('copies', $('pjCopies').value || '1');
            data.append('color_mode', picked('pjColor'));
            data.append('duplex', $('pjDuplex').checked ? '1' : '0');
            data.append('priority', $('pjUrgent').checked ? '5' : '0');
            data.append('payment_method', method);
            if ($('pjPayRef').value.trim()) data.append('reference', $('pjPayRef').value.trim());
            if ($('pjNotes').value.trim()) data.append('notes', $('pjNotes').value.trim());
            const result = await request(API, {method: 'POST', body: data});
            closeModal();
            notify(result.message);
            if (result.data && result.data.error_message) notify(result.data.error_message, 'warning');
            refresh();
        }));
    }

    // ---------- Job actions ----------
    async function collect(job) {
        openModal('Collect payment — PR-' + job.print_job_id,
            '<div class="ses-form"><div class="ses-summary"><strong>' + esc(job.file_name) + '</strong><br>' + esc(job.customer_name || 'Walk-in') + ' · ' + money(job.amount) + '</div>'
            + methodPicker('pjCollect', {exclude: ['collect'], label: 'How is the customer paying?'}) + '</div>',
            '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="pjCollectGo"><i class="fas fa-check"></i> Record ' + money(job.amount) + '</button>');
        wireMethodPicker('pjCollect', null);
        $('pjCollectGo').addEventListener('click', (event) => busy(event.currentTarget, async () => {
            const result = await act('collect', job.print_job_id, {payment_method: picked('pjCollect'), reference: $('pjCollectRef').value.trim()});
            closeModal();
            notify(result.message);
            refresh();
        }));
    }

    async function move(job) {
        await loadOptions();
        const others = options.printers.filter((p) => Number(p.peripheral_id) !== Number(job.peripheral_id) && p.status !== 'maintenance');
        if (!others.length) { CtlDialog.alert({title: 'No other printer', message: 'There is no other printer available to move this job to.', tone: 'warning', icon: 'fa-print'}); return; }
        openModal('Move PR-' + job.print_job_id,
            '<div class="ses-form"><div><label class="form-label" for="pjMoveTo">Send to printer</label><select class="form-input" id="pjMoveTo">'
            + others.map((p) => '<option value="' + p.peripheral_id + '">' + esc(p.name + ' (' + p.status + ')') + '</option>').join('') + '</select></div></div>',
            '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="pjMoveGo"><i class="fas fa-right-left"></i> Move job</button>');
        $('pjMoveGo').addEventListener('click', (event) => busy(event.currentTarget, async () => {
            const result = await act('move', job.print_job_id, {peripheral_id: $('pjMoveTo').value});
            closeModal();
            notify(result.message);
            refresh();
        }));
    }

    async function reprint(job) {
        await loadOptions();
        openModal('Reprint PR-' + job.print_job_id,
            '<div class="ses-form"><div class="ses-summary"><strong>' + esc(job.file_name) + '</strong><br>' + job.pages + ' page(s) · ' + (job.color_mode === 'color' ? 'Colour' : 'B&W') + ' · ' + esc(job.customer_name || 'Walk-in') + '</div>'
            + '<div class="pj-grid2"><div><label class="form-label" for="pjRpPrinter">Printer</label><select class="form-input" id="pjRpPrinter">'
            + options.printers.filter((p) => p.status !== 'maintenance').map((p) => '<option value="' + p.peripheral_id + '"' + (Number(p.peripheral_id) === Number(job.peripheral_id) ? ' selected' : '') + '>' + esc(p.name) + '</option>').join('')
            + '</select></div><div><label class="form-label" for="pjRpCopies">Copies</label><input class="form-input" id="pjRpCopies" type="number" min="1" max="500" value="' + job.copies + '"></div></div>'
            + methodPicker('pjRp') + '<div class="pj-total" id="pjRpTotal"></div></div>',
            '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="pjRpGo"><i class="fas fa-copy"></i> Queue reprint</button>');
        wireMethodPicker('pjRp', null);
        const recalc = () => { $('pjRpTotal').innerHTML = '<span>' + job.pages + ' × ' + (Number($('pjRpCopies').value) || 1) + ' × ' + money(job.unit_price) + '</span><strong>' + money(job.pages * (Number($('pjRpCopies').value) || 1) * job.unit_price) + '</strong>'; };
        $('pjRpCopies').addEventListener('input', recalc);
        recalc();
        $('pjRpGo').addEventListener('click', (event) => busy(event.currentTarget, async () => {
            const result = await act('reprint', job.print_job_id, {peripheral_id: $('pjRpPrinter').value, copies: $('pjRpCopies').value, payment_method: picked('pjRp'), reference: $('pjRpRef').value.trim()});
            closeModal();
            notify(result.message);
            refresh();
        }));
    }

    async function cancel(job) {
        const refundNote = job.payment_status === 'paid' ? ' The ' + money(job.amount) + ' payment will be refunded.'
            : job.payment_status === 'on_account' ? ' The ' + money(job.amount) + ' account charge will be removed.' : '';
        const reason = await CtlDialog.prompt({
            title: 'Cancel PR-' + job.print_job_id + '?',
            message: (job.status === 'printing' ? 'The printer will be told to stop.' : 'The job will leave the queue.') + refundNote,
            label: 'Reason', presets: ['Customer changed their mind', 'Wrong file', 'Printer problem', 'Duplicate job'],
            value: '', maxLength: 200, tone: 'danger', icon: 'fa-ban', confirmText: 'Cancel job',
            validate: (value) => value.trim() ? '' : 'Give a reason.'
        });
        if (reason == null) return;
        try { notify((await act('cancel', job.print_job_id, {reason})).message); refresh(); } catch (error) { notify(error.message, 'error'); }
    }

    async function simple(action, job, confirmOptions, extra) {
        if (confirmOptions && !(await CtlDialog.confirm(confirmOptions))) return;
        try {
            const result = await act(action, job.print_job_id, extra);
            notify(result.message, result.data && result.data.error_message && action === 'resend' ? 'warning' : 'success');
            refresh();
        } catch (error) { notify(error.message, 'error'); }
    }

    async function failJob(job) {
        const reason = await CtlDialog.prompt({
            title: 'Mark PR-' + job.print_job_id + ' as failed?', message: 'The printer moves on to the next job. You can retry or cancel (refund) this one afterwards.',
            label: 'What went wrong?', presets: ['Paper jam', 'Out of toner/ink', 'Out of paper', 'Bad print quality'], value: 'Paper jam', maxLength: 200, tone: 'warning', icon: 'fa-triangle-exclamation', confirmText: 'Mark failed'
        });
        if (reason == null) return;
        simple('fail', job, null, {reason});
    }

    async function deleteJob(job) {
        if (!(await CtlDialog.confirm({title: 'Delete PR-' + job.print_job_id + '?', message: 'The job record and its uploaded file are removed permanently. Payments already recorded are not changed.', tone: 'danger', icon: 'fa-trash', confirmText: 'Delete'}))) return;
        try { notify((await request(API, {method: 'DELETE', json: {print_job_id: job.print_job_id}})).message); refresh(); } catch (error) { notify(error.message, 'error'); }
    }

    function details(job) {
        const row = (label, value) => value == null || value === '' ? '' : '<div><dt>' + label + '</dt><dd>' + value + '</dd></div>';
        openModal('PR-' + job.print_job_id + ' · ' + job.file_name,
            '<dl class="pj-details">'
            + row('Status', pill(STATUS, job.status) + ' ' + pill(PAYMENT, job.payment_status))
            + row('Customer', esc(job.customer_name || 'Walk-in') + (job.customer_phone ? ' · ' + esc(job.customer_phone) : ''))
            + row('Printer', esc(job.printer_name) + (job.dispatch === 'ipp' ? ' · sent automatically' + (job.printer_job_id ? ' (printer job #' + job.printer_job_id + ')' : '') : ' · printed by staff'))
            + row('Station', esc(job.station_code || 'Counter'))
            + row('Document', esc(job.file_name) + (job.file_size ? ' · ' + size(job.file_size) : ''))
            + row('Settings', job.pages + ' page(s) × ' + job.copies + ' · ' + (job.color_mode === 'color' ? 'Colour' : 'B&W') + (Number(job.duplex) ? ' · double-sided' : '') + (Number(job.priority) > 0 ? ' · urgent' : ''))
            + row('Amount', money(job.amount) + ' (' + money(job.unit_price) + ' per side)' + (job.receipt_number ? ' · receipt ' + esc(job.receipt_number) : ''))
            + row('Submitted', when(job.created_at) + (job.staff_name ? ' by ' + esc(job.staff_name) : ''))
            + row('Started', job.started_at ? when(job.started_at) + ' (waited ' + wait(job.wait_minutes) + ')' : '')
            + row('Finished', job.completed_at ? when(job.completed_at) : '')
            + row('Notes', esc(job.notes))
            + row('Message', job.error_message ? '<span class="pj-error-text">' + esc(job.error_message) + '</span>' : '')
            + '</dl>',
            '<a class="btn btn-secondary" href="' + API + '?download=' + job.print_job_id + '" target="_blank" rel="noopener"><i class="fas fa-file-arrow-down"></i> Open file</a>'
            + '<button class="btn btn-primary" type="button" data-close>Close</button>');
    }

    /** Buttons for a job, depending on its state and the user's permissions. */
    function actionsFor(job, compact) {
        const b = (action, icon, label, cls) => '<button type="button" class="' + (cls || '') + '" data-act="' + action + '" data-id="' + job.print_job_id + '" title="' + label + '"><i class="fas ' + icon + '"></i>' + (compact ? '<span class="sr-only">' + label + '</span>' : ' ' + label) + '</button>';
        const out = [b('open', 'fa-file-arrow-down', 'File')];
        if (CFG.canManage) {
            if (job.status === 'printing') {
                out.push(b('complete', 'fa-check', 'Printed', 'go'));
                if (job.dispatch === 'ipp' || job.error_message) out.push(b('resend', 'fa-paper-plane', 'Resend'));
                out.push(b('fail', 'fa-triangle-exclamation', 'Failed'));
            }
            if (job.status === 'queued') {
                out.push(b('complete', 'fa-check', 'Printed', 'go'));
                out.push(b('bump', Number(job.priority) > 0 ? 'fa-arrow-down' : 'fa-bolt', Number(job.priority) > 0 ? 'Normal priority' : 'Urgent'));
                out.push(b('move', 'fa-right-left', 'Move'));
            }
            if (job.status === 'failed') { out.push(b('retry', 'fa-rotate-right', 'Retry', 'go')); out.push(b('move', 'fa-right-left', 'Move')); }
            if (job.payment_status === 'unpaid' && job.status !== 'cancelled') out.push(b('collect', 'fa-hand-holding-dollar', 'Collect ' + money(job.amount), 'pay'));
            if (['completed', 'cancelled', 'failed'].includes(job.status)) out.push(b('reprint', 'fa-copy', 'Reprint'));
        }
        if (CFG.canCancel) {
            if (['queued', 'printing', 'failed'].includes(job.status)) out.push(b('cancel', 'fa-ban', 'Cancel', 'danger'));
            if (['completed', 'cancelled'].includes(job.status)) out.push(b('delete', 'fa-trash', 'Delete', 'danger'));
        }
        return out.join('');
    }

    const jobsById = new Map();
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-act]');
        if (button) {
            const job = jobsById.get(Number(button.dataset.id));
            if (!job) return;
            switch (button.dataset.act) {
                case 'open': window.open(API + '?download=' + job.print_job_id, '_blank', 'noopener'); break;
                case 'details': details(job); break;
                case 'complete': simple('complete', job); break;
                case 'resend': simple('resend', job); break;
                case 'fail': failJob(job); break;
                case 'retry': simple('retry', job); break;
                case 'bump': simple('priority', job, null, {priority: Number(job.priority) > 0 ? 0 : 5}); break;
                case 'move': move(job); break;
                case 'collect': collect(job); break;
                case 'reprint': reprint(job); break;
                case 'cancel': cancel(job); break;
                case 'delete': deleteJob(job); break;
            }
            return;
        }
        const info = event.target.closest('[data-printer-info]');
        if (info) printerInfo(info.dataset.printerInfo, info.dataset.name);
    });

    async function printerInfo(id, name) {
        openModal(name, '<div class="ses-empty"><i class="fas fa-spinner fa-spin"></i><p>Asking the printer…</p></div>', '<button class="btn btn-primary" type="button" data-close>Close</button>');
        try {
            const result = await request(API + '?printer_info=' + id);
            const info = result.data;
            if (!info.available) {
                $('sesModalBody').innerHTML = '<div class="ses-empty"><i class="fas fa-plug-circle-xmark"></i><p>' + esc(result.message || 'Live status unavailable.') + '</p></div>';
                return;
            }
            $('sesModalBody').innerHTML = '<dl class="pj-details">'
                + '<div><dt>State</dt><dd>' + esc(info.state) + (info.reasons.length ? ' · ' + esc(info.reasons.join(', ')) : '') + '</dd></div>'
                + '<div><dt>Model</dt><dd>' + esc(info.model || '—') + '</dd></div>'
                + '<div><dt>Jobs on printer</dt><dd>' + esc(info.queued == null ? '—' : info.queued) + '</dd></div>'
                + '<div><dt>Direct formats</dt><dd>' + esc(info.formats.filter((f) => /pdf|jpeg|png|urf|pwg/.test(f)).join(', ') || '—') + '</dd></div>'
                + '</dl>'
                + (info.supplies.length ? '<h4 class="pj-sub">Supplies</h4>' + info.supplies.map((s) => '<div class="pj-supply"><span>' + esc(s.name) + '</span><div class="ses-bar"><i style="width:' + s.level + '%"></i></div><b>' + s.level + '%</b></div>').join('') : '');
            refresh();
        } catch (error) {
            $('sesModalBody').innerHTML = '<div class="ses-empty"><i class="fas fa-circle-exclamation"></i><p>' + esc(error.message) + '</p></div>';
        }
    }

    // ---------- Board ----------
    function jobLine(job) {
        return '<li class="pj-qjob' + (Number(job.priority) > 0 ? ' urgent' : '') + '">'
            + '<span class="pj-pos">' + job.queue_position + '</span>'
            + '<button type="button" class="pj-qmain" data-act="details" data-id="' + job.print_job_id + '"><strong><i class="fas ' + fileIcon(job) + '"></i> ' + esc(job.file_name) + '</strong>'
            + '<small>' + esc(job.customer_name || 'Walk-in') + ' · ' + job.pages + 'p × ' + job.copies + ' · ' + (job.color_mode === 'color' ? 'Colour' : 'B&W') + ' · waiting ' + wait(job.wait_minutes) + '</small></button>'
            + '<div class="pj-qfoot"><span class="pj-qside">' + (Number(job.priority) > 0 ? '<span class="pj-pill bad">Urgent</span>' : '') + pill(PAYMENT, job.payment_status) + ' <b>' + money(job.amount) + '</b></span>'
            + '<div class="ses-actions pj-mini">' + actionsFor(job, true) + '</div></div></li>';
    }

    function printerCard(printer) {
        const current = printer.jobs.find((j) => j.status === 'printing');
        const queued = printer.jobs.filter((j) => j.status === 'queued');
        const statusTone = printer.status === 'online' ? 'ok' : printer.status === 'maintenance' ? 'warn' : 'muted';
        return '<article class="pj-printer ' + (current ? 'busy' : '') + '">'
            + '<header><div><h3><i class="fas fa-print"></i> ' + esc(printer.name) + '</h3>'
            + '<small>' + esc([printer.model, printer.ip, printer.station_code].filter(Boolean).join(' · ') || 'No details') + '</small></div>'
            + '<div class="pj-badges"><span class="pj-pill ' + statusTone + '">' + esc(printer.status) + '</span>'
            + '<span class="pj-pill ' + (printer.auto_print ? 'info' : 'muted') + '" title="' + (printer.auto_print ? 'PDF and images are sent to this printer automatically' : 'Staff print jobs from the file and mark them done') + '">' + (printer.auto_print ? 'Auto-print' : 'Manual') + '</span>'
            + (printer.auto_print ? '<button type="button" class="pj-icon" data-printer-info="' + printer.peripheral_id + '" data-name="' + esc(printer.name) + '" title="Live printer status" aria-label="Live status of ' + esc(printer.name) + '"><i class="fas fa-gauge"></i></button>' : '')
            + '</div></header>'
            + (current
                ? '<div class="pj-now"><div class="pj-now-label"><span class="pj-dot"></span> Printing now' + (current.dispatch === 'ipp' && !current.error_message ? ' · sent to printer' : ' · print manually') + '</div>'
                    + '<button type="button" class="pj-now-file" data-act="details" data-id="' + current.print_job_id + '"><i class="fas ' + fileIcon(current) + '"></i><span><strong>' + esc(current.file_name) + '</strong>'
                    + '<small>PR-' + current.print_job_id + ' · ' + esc(current.customer_name || 'Walk-in') + ' · ' + current.pages + 'p × ' + current.copies + ' · ' + (current.color_mode === 'color' ? 'Colour' : 'B&W') + (Number(current.duplex) ? ' · 2-sided' : '') + '</small></span></button>'
                    + '<div class="pj-now-meta"><strong>' + money(current.amount) + '</strong>' + pill(PAYMENT, current.payment_status) + (current.notes ? '<span class="pj-note"><i class="fas fa-note-sticky"></i> ' + esc(current.notes) + '</span>' : '') + '</div>'
                    + (current.error_message ? '<div class="pj-warn"><i class="fas fa-circle-info"></i> ' + esc(current.error_message) + '</div>' : '')
                    + '<div class="ses-actions">' + actionsFor(current, false) + '</div></div>'
                : '<div class="pj-idle"><i class="fas fa-mug-hot"></i> Idle — ready for the next job</div>')
            + '<div class="pj-qhead"><span>Up next</span><span>' + queued.length + ' waiting</span></div>'
            + (queued.length ? '<ol class="pj-queue">' + queued.map(jobLine).join('') + '</ol>' : '<p class="pj-muted pj-qempty">Nothing waiting.</p>')
            + '</article>';
    }

    async function loadBoard() {
        const {data} = await request(API + '?board=1');
        data.printers.forEach((p) => p.jobs.forEach((j) => jobsById.set(Number(j.print_job_id), j)));
        data.attention.forEach((j) => jobsById.set(Number(j.print_job_id), j));
        const t = data.today;
        const queued = data.printers.reduce((n, p) => n + p.jobs.filter((j) => j.status === 'queued').length, 0);
        const printing = data.printers.reduce((n, p) => n + p.jobs.filter((j) => j.status === 'printing').length, 0);
        $('pjStats').innerHTML = [
            ['', 'In the queue', queued + (printing ? ' <small>+' + printing + ' printing</small>' : '')],
            ['ok', 'Printed today', Number(t.sides).toLocaleString() + ' <small>sides · ' + Number(t.color_sides).toLocaleString() + ' colour</small>'],
            ['ok', 'Print revenue today', money(t.revenue)],
            [Number(t.uncollected) > 0 ? 'warn' : '', 'To collect', money(t.uncollected)],
            [Number(t.failed) > 0 ? 'bad' : '', 'Failed today', Number(t.failed || 0)],
            ['', 'Avg. wait today', t.avg_wait == null ? '—' : t.avg_wait + ' min']
        ].map(([tone, label, value]) => '<div class="ses-stat ' + tone + '"><span>' + label + '</span><strong>' + value + '</strong></div>').join('');

        const attention = $('pjAttention');
        attention.hidden = !data.attention.length;
        attention.innerHTML = data.attention.length
            ? '<h2><i class="fas fa-bell"></i> Needs attention <span>' + data.attention.length + '</span></h2><ul>'
                + data.attention.map((j) => '<li><button type="button" class="pj-qmain" data-act="details" data-id="' + j.print_job_id + '"><strong>PR-' + j.print_job_id + ' · ' + esc(j.file_name) + '</strong><small>'
                    + (j.status === 'failed' ? '<span class="pj-error-text">' + esc(j.error_message || 'Failed') + '</span>' : 'Printed for ' + esc(j.customer_name || 'walk-in') + ' — ' + money(j.amount) + ' not yet paid')
                    + ' · ' + esc(j.printer_name) + '</small></button><div class="ses-actions pj-mini">' + actionsFor(j, true) + '</div></li>').join('') + '</ul>'
            : '';

        $('pjBoard').innerHTML = data.printers.length
            ? data.printers.map(printerCard).join('')
            : '<div class="ses-empty"><i class="fas fa-print"></i><p>No printers yet. Add one on the <a href="printers.php">Printers</a> page.</p></div>';
        const mode = data.mode === 'lan' ? 'PDF & images go to network printers automatically' : 'Cloud mode: staff print from the file and mark jobs done';
        $('pjSubtitle').innerHTML = 'B&W ' + money(data.prices.bw) + ' · Colour ' + money(data.prices.color) + ' per side · ' + mode + ' · updated <span id="pjUpdated">' + new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'}) + '</span>';
    }

    // ---------- History ----------
    let page = 1;
    let printersLoaded = false;
    async function loadHistory() {
        if (!printersLoaded) {
            try {
                await loadOptions();
                const select = document.querySelector('#pjFilters [name=printer]');
                select.insertAdjacentHTML('beforeend', options.printers.map((p) => '<option value="' + p.peripheral_id + '">' + esc(p.name) + '</option>').join(''));
                printersLoaded = true;
            } catch (e) { /* filters still work without the printer list */ }
        }
        const params = new URLSearchParams(new FormData($('pjFilters')));
        params.set('jobs', '1');
        params.set('page', String(page));
        const {data} = await request(API + '?' + params.toString());
        data.jobs.forEach((j) => jobsById.set(Number(j.print_job_id), j));
        $('pjSubtitle').textContent = 'Every print job, newest first · click a job number for full details';
        $('pjTotals').innerHTML = '<span><b>' + data.total.toLocaleString() + '</b> job' + (data.total === 1 ? '' : 's') + '</span><span><b>' + Number(data.totals.sides).toLocaleString() + '</b> sides printed</span><span><b>' + money(data.totals.billed) + '</b> billed</span>';
        $('pjRows').innerHTML = data.jobs.length ? data.jobs.map((j) =>
            '<tr><td><button type="button" class="pj-link" data-act="details" data-id="' + j.print_job_id + '">PR-' + j.print_job_id + '</button></td>'
            + '<td class="pj-doc"><i class="fas ' + fileIcon(j) + '"></i> <span title="' + esc(j.file_name) + '">' + esc(j.file_name) + '</span></td>'
            + '<td>' + esc(j.customer_name || 'Walk-in') + '</td><td>' + esc(j.printer_name || '—') + '</td>'
            + '<td>' + j.pages + ' × ' + j.copies + (j.color_mode === 'color' ? ' <span class="pj-pill info">Colour</span>' : '') + '</td>'
            + '<td><strong>' + money(j.amount) + '</strong><br>' + pill(PAYMENT, j.payment_status) + '</td>'
            + '<td>' + pill(STATUS, j.status) + '</td><td class="pj-when">' + when(j.created_at) + (j.staff_name ? '<br><small>' + esc(j.staff_name) + '</small>' : '') + '</td>'
            + '<td><div class="ses-actions pj-mini">' + actionsFor(j, true) + '</div></td></tr>').join('')
            : '<tr><td colspan="9"><div class="ses-empty"><i class="fas fa-inbox"></i><p>No print jobs match these filters.</p></div></td></tr>';
        $('pjPager').innerHTML = data.pages > 1
            ? '<button type="button" class="btn btn-secondary btn-sm" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '><i class="fas fa-chevron-left"></i> Previous</button><span>Page ' + page + ' of ' + data.pages + '</span><button type="button" class="btn btn-secondary btn-sm" data-page="' + (page + 1) + '"' + (page >= data.pages ? ' disabled' : '') + '>Next <i class="fas fa-chevron-right"></i></button>'
            : '';
    }
    $('pjPager').addEventListener('click', (event) => { const b = event.target.closest('[data-page]'); if (b) { page = Number(b.dataset.page); refresh(); } });
    let filterTimer = null;
    $('pjFilters').addEventListener('input', () => { clearTimeout(filterTimer); filterTimer = setTimeout(() => { page = 1; refresh(); }, 300); });
    $('pjFilters').addEventListener('submit', (event) => event.preventDefault());

    // ---------- View switching & refresh ----------
    let view = CFG.view || 'board';
    document.querySelectorAll('.ses-tabs a[data-view]').forEach((link) => link.addEventListener('click', (event) => {
        event.preventDefault();
        view = link.dataset.view;
        document.querySelectorAll('.ses-tabs a[data-view]').forEach((a) => a.removeAttribute('aria-current'));
        link.setAttribute('aria-current', 'page');
        $('pjBoardView').hidden = view !== 'board';
        $('pjHistoryView').hidden = view !== 'history';
        history.replaceState(null, '', view === 'history' ? 'print-jobs.php?view=history' : location.pathname);
        refresh();
    }));

    let loading = false;
    async function refresh() {
        if (loading) return;
        loading = true;
        try { await (view === 'history' ? loadHistory() : loadBoard()); }
        catch (error) { notify(error.message, 'error'); }
        finally { loading = false; }
    }

    const newButton = $('pjNew');
    if (newButton) newButton.addEventListener('click', openNewJob);
    refresh();
    setInterval(() => { if (!document.hidden && !modalOpen() && view === 'board') refresh(); }, 8000);
})();
