/**
 * Analytics page: loads api/analytics.php for the chosen period and renders KPIs with
 * period-over-period change, trend, categories, heatmap, payment methods, stations,
 * customers, printing, best sellers and receivables.
 */
(function () {
    'use strict';

    const API = 'api/analytics.php';
    const $ = (id) => document.getElementById(id);
    const esc = (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; };
    const PALETTE = ['#7C3AED', '#06B6D4', '#10B981', '#F59E0B', '#EC4899', '#3B82F6', '#EF4444', '#64748B', '#8B5CF6', '#14B8A6'];
    const METHOD_LABELS = {cash: 'Cash', mobile_money: 'Mobile money', card: 'Card', bank_transfer: 'Bank transfer', wallet: 'Wallet', account: 'On account', unrecorded: 'Not recorded', other: 'Other'};
    const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    let currency = window.CTL_CURRENCY || 'GHS';
    let report = null;
    let measure = 'revenue';
    let heatMeasure = 'transactions';
    const charts = {};

    const money = (v, digits) => currency + ' ' + Number(v || 0).toLocaleString('en-GH', {minimumFractionDigits: digits == null ? 2 : digits, maximumFractionDigits: digits == null ? 2 : digits});
    const compact = (v) => Math.abs(v) >= 10000 ? currency + ' ' + (v / 1000).toLocaleString('en-GH', {maximumFractionDigits: 1}) + 'k' : money(v);
    const num = (v) => Number(v || 0).toLocaleString('en-GH');
    const fmtDate = (s, withYear) => new Date(s + 'T00:00:00').toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: withYear ? 'numeric' : undefined});
    const fmtDateTime = (s) => { const d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? '—' : d.toLocaleDateString('en-GB', {day: 'numeric', month: 'short'}) + ', ' + d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}); };

    // ---------- Period state (kept in the URL so reports can be bookmarked/shared) ----------
    const params = new URLSearchParams(location.search);
    let state = params.get('from') && params.get('to') ? {from: params.get('from'), to: params.get('to')} : {range: params.get('range') || '30d'};
    const query = () => state.range ? 'range=' + encodeURIComponent(state.range) : 'from=' + state.from + '&to=' + state.to;

    function syncControls() {
        document.querySelectorAll('#anPresets [data-range]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.range === state.range)));
        $('anExport').href = API + '?' + query() + '&export=csv';
        try { history.replaceState(null, '', location.pathname + '?' + query()); } catch (e) { /* URL sync is optional */ }
    }

    // ---------- KPIs ----------
    function delta(change, inverse) {
        if (change === null || change === undefined) return '<span class="an-delta new">New</span>';
        if (change === 0) return '<span class="an-delta flat">No change</span>';
        const up = change > 0;
        const good = inverse ? !up : up;
        return '<span class="an-delta ' + (good ? 'up' : 'down') + '"><i class="fas fa-arrow-' + (up ? 'up' : 'down') + '"></i> ' + Math.abs(change).toLocaleString('en-GH') + '%</span>';
    }

    function renderKpis() {
        const c = report.current, p = report.previous, ch = report.changes;
        const cards = [
            ['fa-coins', 'violet', 'Net revenue', money(c.revenue), ch.revenue, 'vs ' + money(p.revenue) + (c.refunds ? ' · ' + money(c.refunds) + ' refunded' : '')],
            ['fa-receipt', 'cyan', 'Sales', num(c.transactions), ch.transactions, 'vs ' + num(p.transactions) + (c.on_account ? ' · ' + money(c.on_account) + ' on account' : '')],
            ['fa-scale-balanced', 'green', 'Average sale', money(c.avg_ticket), ch.avg_ticket, 'vs ' + money(p.avg_ticket)],
            ['fa-users', 'amber', 'Customers served', num(c.customers), ch.customers, num(c.new_customers) + ' new sign-up' + (c.new_customers === 1 ? '' : 's')],
            ['fa-desktop', 'pink', 'Station hours', num(c.station_hours) + ' h', ch.station_hours, c.utilization + '% utilised · ' + num(c.sessions) + ' session' + (c.sessions === 1 ? '' : 's')],
            ['fa-stopwatch', 'blue', 'Average session', c.avg_session_minutes >= 60 ? Math.floor(c.avg_session_minutes / 60) + ' h ' + (c.avg_session_minutes % 60) + ' m' : c.avg_session_minutes + ' min', ch.avg_session_minutes, 'vs ' + p.avg_session_minutes + ' min'],
            ['fa-print', 'teal', 'Printed sides', num(c.print_sides), ch.print_sides, 'vs ' + num(p.print_sides)],
            ['fa-wallet', 'slate', 'Wallet top-ups', money(c.topups), ch.topups, 'Prepaid cash in — not counted as revenue']
        ];
        $('anKpis').innerHTML = cards.map(([icon, tone, label, value, change, note]) =>
            '<article class="an-kpi tone-' + tone + '"><div class="an-kpi-top"><span>' + label + '</span><i class="fas ' + icon + '" aria-hidden="true"></i></div>'
            + '<div class="an-kpi-value">' + value + '</div><div class="an-kpi-foot">' + delta(change) + '<small>' + esc(note) + '</small></div></article>').join('');
    }

    // ---------- Insights ----------
    function renderInsights() {
        const notes = [];
        const c = report.current, ch = report.changes;
        if (c.transactions === 0) {
            notes.push(['fa-circle-info', 'No sales were recorded in this period.']);
        } else {
            if (ch.revenue !== null && ch.revenue !== 0) notes.push([ch.revenue > 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down', 'Net revenue is ' + (ch.revenue > 0 ? 'up ' : 'down ') + Math.abs(ch.revenue) + '% on the previous period.']);
            const best = report.series.reduce((a, b) => (b.revenue > a.revenue ? b : a), report.series[0]);
            if (best && best.revenue > 0 && report.series.length > 1) notes.push(['fa-trophy', 'Best ' + (report.range.bucket === 'month' ? 'month' : 'day') + ': ' + best.label + ' with ' + money(best.revenue) + '.']);
            if (report.categories.length) {
                const total = report.categories.reduce((s, x) => s + Math.max(0, x.revenue), 0) || 1;
                const top = report.categories[0];
                notes.push(['fa-chart-pie', top.label + ' brings in ' + Math.round(Math.max(0, top.revenue) / total * 100) + '% of revenue.']);
            }
            if (report.heatmap.length) {
                const peak = report.heatmap.reduce((a, b) => (Number(b.transactions) > Number(a.transactions) ? b : a));
                notes.push(['fa-clock', 'Busiest time: ' + ['Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays', 'Sundays'][peak.dow] + ' around ' + String(peak.hour).padStart(2, '0') + ':00.']);
            }
        }
        if (report.receivables.invoices_overdue > 0) notes.push(['fa-triangle-exclamation', money(report.receivables.invoices_overdue) + ' in invoices is overdue.', 'warn']);
        if (Number(report.printing.uncollected) > 0) notes.push(['fa-hand-holding-dollar', money(report.printing.uncollected) + ' of printing is still to be collected.', 'warn']);
        $('anInsights').hidden = !notes.length;
        $('anInsights').innerHTML = notes.map(([icon, text, tone]) => '<div class="an-insight ' + (tone || '') + '"><i class="fas ' + icon + '" aria-hidden="true"></i><span>' + esc(text) + '</span></div>').join('');
    }

    // ---------- Charts ----------
    function chartReady() { return typeof window.Chart !== 'undefined'; }

    function renderTrend() {
        if (!chartReady()) return;
        const series = report.series;
        const prev = report.previous_series;
        const isMoney = measure === 'revenue';
        const ctx = $('anTrend').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(124,58,237,0.28)');
        gradient.addColorStop(1, 'rgba(124,58,237,0)');
        const data = {
            labels: series.map((r) => r.label),
            datasets: [
                {label: 'This period', data: series.map((r) => r[measure]), borderColor: '#7C3AED', backgroundColor: gradient, fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: series.length > 45 ? 0 : 3, pointHoverRadius: 5, pointBackgroundColor: '#7C3AED'},
                {label: 'Previous period', data: series.map((r, i) => (prev[i] ? prev[i][measure] : null)), borderColor: '#94A3B8', borderDash: [6, 5], borderWidth: 1.5, fill: false, tension: 0.35, pointRadius: 0, pointHoverRadius: 4,
                    prevLabels: series.map((r, i) => (prev[i] ? prev[i].label : ''))}
            ]
        };
        const options = {
            responsive: true, maintainAspectRatio: false, interaction: {mode: 'index', intersect: false},
            plugins: {
                legend: {position: 'bottom', labels: {usePointStyle: true, boxWidth: 8, font: {family: 'Inter', size: 12}}},
                tooltip: {callbacks: {
                    title: (items) => items.map((i) => (i.datasetIndex === 1 ? i.dataset.prevLabels[i.dataIndex] : i.label)).filter(Boolean).join(' vs '),
                    label: (item) => ' ' + item.dataset.label + ': ' + (isMoney ? money(item.raw) : num(item.raw))
                }}
            },
            scales: {
                x: {grid: {display: false}, ticks: {maxRotation: 0, autoSkip: true, maxTicksLimit: 10, font: {family: 'Inter', size: 11}, color: '#64748B'}},
                y: {beginAtZero: true, grid: {color: 'rgba(148,163,184,0.18)'}, border: {display: false}, ticks: {font: {family: 'Inter', size: 11}, color: '#64748B', precision: 0, callback: (v) => (isMoney ? compact(v) : num(v))}}
            }
        };
        if (charts.trend) charts.trend.destroy();
        charts.trend = new Chart(ctx, {type: 'line', data, options});
    }

    function renderCategories() {
        const cats = report.categories.filter((c) => c.revenue > 0);
        const total = cats.reduce((s, c) => s + c.revenue, 0);
        $('anDonutCenter').innerHTML = '<strong>' + compact(total) + '</strong><span>net revenue</span>';
        $('anCategoryList').innerHTML = cats.length
            ? cats.map((c, i) => '<li><i style="background:' + PALETTE[i % PALETTE.length] + '"></i><span>' + esc(c.label) + '</span><b>' + money(c.revenue) + '</b><small>' + Math.round(c.revenue / total * 100) + '%</small></li>').join('')
            : '<li class="an-empty">No revenue in this period.</li>';
        if (!chartReady()) return;
        if (charts.categories) charts.categories.destroy();
        charts.categories = new Chart($('anCategories'), {
            type: 'doughnut',
            data: {labels: cats.map((c) => c.label), datasets: [{data: cats.length ? cats.map((c) => c.revenue) : [1], backgroundColor: cats.length ? cats.map((c, i) => PALETTE[i % PALETTE.length]) : ['#E2E8F0'], borderWidth: 2, borderColor: '#fff', hoverOffset: 6}]},
            options: {responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: {legend: {display: false}, tooltip: {enabled: cats.length > 0, callbacks: {label: (i) => ' ' + i.label + ': ' + money(i.raw)}}}}
        });
    }

    // ---------- Heatmap ----------
    function renderHeat() {
        const grid = Array.from({length: 7}, () => Array(24).fill(0));
        report.heatmap.forEach((cell) => { grid[cell.dow][cell.hour] = Number(cell[heatMeasure]); });
        const max = Math.max(1, ...grid.flat());
        // Show the opening window only (hours with any activity, padded), at least 08:00–21:00.
        let first = 8, last = 21;
        report.heatmap.forEach((c) => { first = Math.min(first, Number(c.hour)); last = Math.max(last, Number(c.hour)); });
        const hours = [];
        for (let h = first; h <= last; h++) hours.push(h);
        let html = '<div class="an-heat-row an-heat-hours" style="--cols:' + hours.length + '"><span></span>' + hours.map((h) => '<span>' + (h % 3 === 0 ? String(h).padStart(2, '0') : '') + '</span>').join('') + '</div>';
        grid.forEach((row, d) => {
            html += '<div class="an-heat-row" style="--cols:' + hours.length + '"><span class="an-heat-day">' + DAYS[d] + '</span>'
                + hours.map((h) => {
                    const v = row[h];
                    const level = v ? 0.12 + 0.88 * (v / max) : 0;
                    const label = DAYS[d] + ' ' + String(h).padStart(2, '0') + ':00 — ' + (heatMeasure === 'revenue' ? money(v) : v + ' sale' + (v === 1 ? '' : 's'));
                    return '<span class="an-cell" style="--a:' + level.toFixed(2) + '" title="' + label + '" aria-label="' + label + '"></span>';
                }).join('') + '</div>';
        });
        html += '<div class="an-heat-scale"><span>Less</span><i style="--a:0.12"></i><i style="--a:0.4"></i><i style="--a:0.7"></i><i style="--a:1"></i><span>More</span></div>';
        $('anHeat').innerHTML = html;
    }

    // ---------- Lists ----------
    function bars(rows, opts) {
        const max = opts.max || Math.max(1, ...rows.map((r) => Math.abs(opts.value(r))));
        return rows.map((r, i) => '<li><div class="an-bar-label"><span>' + opts.label(r) + '</span><b>' + opts.display(r) + '</b></div>'
            + '<div class="an-bar"><i style="width:' + Math.max(2, Math.abs(opts.value(r)) / max * 100).toFixed(1) + '%;background:' + (opts.color ? opts.color(r, i) : PALETTE[i % PALETTE.length]) + '"></i></div>'
            + (opts.sub ? '<small>' + opts.sub(r) + '</small>' : '') + '</li>').join('');
    }

    function renderLists() {
        const r = report;
        const methodTotal = r.methods.reduce((s, m) => s + Number(m.revenue), 0) || 1;
        $('anMethods').innerHTML = r.methods.length ? bars(r.methods, {
            label: (m) => esc(METHOD_LABELS[m.method] || m.method), value: (m) => Number(m.revenue),
            display: (m) => money(m.revenue), sub: (m) => num(m.transactions) + ' sale' + (Number(m.transactions) === 1 ? '' : 's') + ' · ' + Math.round(Number(m.revenue) / methodTotal * 100) + '%'
        }) : '<li class="an-empty">No sales in this period.</li>';

        $('anCapacity').textContent = 'Hours used against ' + r.capacity.hours_per_day + ' opening hours a day × ' + r.range.days + ' day' + (r.range.days === 1 ? '' : 's');
        $('anStations').innerHTML = r.stations.length ? bars(r.stations, {
            label: (s) => '<strong>' + esc(s.station_code) + '</strong> ' + esc(s.name) + (s.status === 'maintenance' ? ' <em>maintenance</em>' : ''),
            max: 100, value: (s) => Number(s.utilization) || 0, display: (s) => s.utilization + '%',
            color: (s) => (s.utilization >= 70 ? '#10B981' : s.utilization >= 30 ? '#7C3AED' : '#94A3B8'),
            sub: (s) => num(s.hours) + ' h · ' + num(s.sessions) + ' session' + (Number(s.sessions) === 1 ? '' : 's') + ' · ' + money(s.revenue)
        }) : '<li class="an-empty">No stations set up.</li>';

        $('anCustomerMix').textContent = r.customer_mix.returning + ' returning · ' + r.customer_mix.new + ' new customer' + (r.customer_mix.new === 1 ? '' : 's') + ' this period';
        $('anCustomers').innerHTML = r.customers.length
            ? r.customers.map((c, i) => '<tr><td class="an-rank">' + (i + 1) + '</td><td><a href="customers.php?search=' + encodeURIComponent(c.full_name) + '">' + esc(c.full_name) + '</a>' + (c.phone ? '<small>' + esc(c.phone) + '</small>' : '') + '</td><td>' + num(c.transactions) + '</td><td>' + fmtDateTime(c.last_visit) + '</td><td class="num"><b>' + money(c.revenue) + '</b></td></tr>').join('')
            : '<tr><td colspan="5" class="an-empty">No named customers in this period.</td></tr>';

        $('anItems').innerHTML = r.items.length ? bars(r.items, {
            label: (it) => esc(it.description), value: (it) => Number(it.revenue), display: (it) => money(it.revenue),
            sub: (it) => num(Number(it.quantity)) + ' sold · ' + num(it.transactions) + ' sale' + (Number(it.transactions) === 1 ? '' : 's')
        }) : '<li class="an-empty">No itemised sales in this period.</li>';

        const pr = r.printing;
        const colorShare = Number(pr.sides) ? Math.round(Number(pr.color_sides) / Number(pr.sides) * 100) : 0;
        $('anPrinting').innerHTML =
            '<div><dt>Jobs</dt><dd>' + num(pr.jobs) + '</dd></div>'
            + '<div><dt>Sides printed</dt><dd>' + num(pr.sides) + ' <small>' + colorShare + '% colour</small></dd></div>'
            + '<div><dt>Printing revenue</dt><dd>' + money(pr.revenue) + '</dd></div>'
            + '<div><dt>To collect</dt><dd class="' + (Number(pr.uncollected) > 0 ? 'warn' : '') + '">' + money(pr.uncollected) + '</dd></div>'
            + '<div><dt>Failed / cancelled</dt><dd>' + num(pr.failed || 0) + ' / ' + num(pr.cancelled || 0) + '</dd></div>';

        const rc = r.receivables;
        $('anReceivables').innerHTML =
            '<div><dt>Sold on account <small>unbilled + billed, unpaid</small></dt><dd>' + money(rc.on_account) + '</dd></div>'
            + '<div><dt>Invoices due</dt><dd>' + money(rc.invoices_due) + '</dd></div>'
            + '<div><dt>Overdue invoices</dt><dd class="' + (rc.invoices_overdue > 0 ? 'bad' : '') + '">' + money(rc.invoices_overdue) + '</dd></div>'
            + '<div><dt>Customer wallets <small>prepaid, owed as service</small></dt><dd>' + money(rc.wallet_balances) + '</dd></div>'
            + '<div class="an-figures-link"><a href="billing.php">Customer billing <i class="fas fa-arrow-right"></i></a></div>';
    }

    // ---------- Load ----------
    let requestId = 0;
    async function load() {
        syncControls();
        const id = ++requestId;
        document.body.classList.add('an-loading');
        try {
            const response = await fetch(API + '?' + query(), {credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.success) throw new Error(result.message || 'Analytics could not be loaded.');
            if (id !== requestId) return;
            report = result.data;
            currency = report.currency || currency;
            $('anError').hidden = true;
            const rg = report.range;
            const same = rg.from === rg.to;
            $('anRangeText').innerHTML = '<strong>' + (same ? fmtDate(rg.from, true) : fmtDate(rg.from, rg.from.slice(0, 4) !== rg.to.slice(0, 4)) + ' – ' + fmtDate(rg.to, true)) + '</strong>'
                + ' · compared with ' + (rg.prev_from === rg.prev_to ? fmtDate(rg.prev_from, true) : fmtDate(rg.prev_from) + ' – ' + fmtDate(rg.prev_to, true));
            $('anFrom').value = rg.from;
            $('anTo').value = rg.to;
            $('anTrendNote').textContent = (rg.bucket === 'month' ? 'Monthly' : 'Daily') + ', net of refunds · dashed line is the previous period';
            renderKpis();
            renderInsights();
            renderCategories();
            renderTrend();
            renderHeat();
            renderLists();
        } catch (error) {
            $('anError').hidden = false;
            $('anError').innerHTML = '<i class="fas fa-circle-exclamation"></i> ' + esc(error.message);
        } finally {
            if (id === requestId) document.body.classList.remove('an-loading');
        }
    }

    $('anPresets').addEventListener('click', (event) => {
        const button = event.target.closest('[data-range]');
        if (!button) return;
        state = {range: button.dataset.range};
        load();
    });
    $('anCustom').addEventListener('submit', (event) => {
        event.preventDefault();
        if (!$('anFrom').value || !$('anTo').value) return;
        state = {from: $('anFrom').value, to: $('anTo').value};
        load();
    });
    document.querySelectorAll('[data-measure]').forEach((b) => b.addEventListener('click', () => {
        measure = b.dataset.measure;
        document.querySelectorAll('[data-measure]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
        if (report) renderTrend();
    }));
    document.querySelectorAll('[data-heat]').forEach((b) => b.addEventListener('click', () => {
        heatMeasure = b.dataset.heat;
        document.querySelectorAll('[data-heat]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
        if (report) renderHeat();
    }));
    $('anPrint').addEventListener('click', () => window.print());
    $('anTo').max = new Date().toISOString().slice(0, 10);

    // Chart.js loads deferred; draw charts once it is there.
    window.addEventListener('load', () => { if (report) { renderCategories(); renderTrend(); } });
    window.addEventListener('beforeprint', () => Object.values(charts).forEach((c) => c.resize()));
    load();
})();
