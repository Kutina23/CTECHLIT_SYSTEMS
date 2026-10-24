/**
 * Settings page: section navigation, search, unsaved-change tracking with a save bar,
 * character counters, opening-hours preview and the directions-link test button.
 */
(function () {
    'use strict';

    const $ = (id) => document.getElementById(id);
    const form = $('settingsForm');
    const fields = Array.from(document.querySelectorAll('[data-key]'));
    const valueOf = (el) => (el.type === 'checkbox' ? (el.checked ? 'true' : 'false') : el.value);
    const initial = new Map(fields.map((el) => [el, valueOf(el)]));

    // ---------- Unsaved changes ----------
    function refreshDirty() {
        let count = 0;
        const dirtySections = new Set();
        fields.forEach((el) => {
            const changed = valueOf(el) !== initial.get(el);
            el.classList.toggle('changed', changed);
            if (changed) {
                count++;
                dirtySections.add(el.closest('[data-section]').dataset.section);
            }
        });
        document.querySelectorAll('[data-nav]').forEach((a) => a.classList.toggle('dirty', dirtySections.has(a.dataset.nav)));
        const bar = $('saveBar');
        if (bar) {
            bar.hidden = count === 0;
            $('dirtyCount').textContent = count;
            $('dirtyPlural').textContent = count === 1 ? '' : 's';
        }
        return count;
    }
    fields.forEach((el) => el.addEventListener(el.type === 'checkbox' || el.tagName === 'SELECT' ? 'change' : 'input', refreshDirty));

    if ($('discardBtn')) {
        $('discardBtn').addEventListener('click', () => {
            fields.forEach((el) => {
                if (el.type === 'checkbox') el.checked = initial.get(el) === 'true'; else el.value = initial.get(el);
                el.dispatchEvent(new Event('input'));
            });
            refreshDirty();
            hoursPreview();
        });
    }

    let submitting = false;
    if (form) {
        form.addEventListener('submit', () => {
            submitting = true;
            const firstDirty = fields.find((el) => valueOf(el) !== initial.get(el));
            $('stSection').value = firstDirty ? firstDirty.closest('[data-section]').dataset.section : '';
            const save = $('saveBtn');
            if (save) { save.disabled = true; save.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…'; }
        });
    }
    window.addEventListener('beforeunload', (event) => {
        if (!submitting && refreshDirty() > 0) { event.preventDefault(); event.returnValue = ''; }
    });
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's' && form && $('saveBar') && !$('saveBar').hidden) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    // ---------- Character counters ----------
    document.querySelectorAll('[data-count-for]').forEach((counter) => {
        const input = $(counter.dataset.countFor);
        const max = Number(input.getAttribute('maxlength')) || 0;
        const update = () => {
            counter.textContent = input.value.length + ' / ' + max;
            counter.classList.toggle('near', max && input.value.length > max * 0.9);
        };
        input.addEventListener('input', update);
        update();
    });

    // ---------- Currency label follows the "currency label on screen" field ----------
    const symbolInput = $('f_currency_symbol');
    if (symbolInput) symbolInput.addEventListener('input', () => {
        document.querySelectorAll('[data-currency]').forEach((el) => { el.textContent = symbolInput.value.trim() || '—'; });
    });

    // ---------- Directions link test button ----------
    document.querySelectorAll('[data-test-for]').forEach((link) => {
        const input = $(link.dataset.testFor);
        const update = () => { link.href = /^https?:\/\//i.test(input.value.trim()) ? input.value.trim() : '#'; };
        input.addEventListener('input', update);
        link.addEventListener('click', (e) => { if (link.getAttribute('href') === '#') e.preventDefault(); });
        update();
    });

    // ---------- Opening hours: live open/closed preview ----------
    const hoursInput = $('f_opening_hours');
    let hoursTimer = null;
    async function hoursPreview() {
        const box = $('hoursPreview');
        if (!hoursInput || !box) return;
        const text = hoursInput.value.trim();
        if (!text) { box.textContent = ''; box.className = 'st-preview'; return; }
        try {
            const response = await fetch('settings.php?hours_preview=' + encodeURIComponent(text), {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            const result = await response.json();
            const status = result.data;
            if (!status) {
                box.className = 'st-preview bad';
                box.innerHTML = '<i class="fas fa-triangle-exclamation"></i> Can’t work out the hours from this text';
            } else {
                box.className = 'st-preview ' + (status.open ? 'open' : 'closed');
                box.innerHTML = '<span class="dot"></span> Website will show: ' + (status.open ? 'Open now' : 'Closed now') + ' · ' + status.detail.replace(/[<>&]/g, '');
            }
        } catch (e) { box.textContent = ''; }
    }
    if (hoursInput) {
        hoursInput.addEventListener('input', () => { clearTimeout(hoursTimer); hoursTimer = setTimeout(hoursPreview, 350); });
        hoursPreview();
    }

    // ---------- Section navigation (scroll-spy) ----------
    const navLinks = Array.from(document.querySelectorAll('[data-nav]'));
    const cards = Array.from(document.querySelectorAll('.st-card[data-section]'));
    if ('IntersectionObserver' in window) {
        const spy = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                navLinks.forEach((a) => a.classList.toggle('active', a.dataset.nav === entry.target.dataset.section));
            });
        }, {rootMargin: '-30% 0px -60% 0px'});
        cards.forEach((card) => spy.observe(card));
    }

    // ---------- Search ----------
    const search = $('stSearch');
    if (search) search.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        let any = false;
        cards.forEach((card) => {
            const sectionFields = Array.from(card.querySelectorAll('.st-field'));
            if (!sectionFields.length) { card.hidden = Boolean(term) && !card.textContent.toLowerCase().includes(term); any = any || !card.hidden; return; }
            let visible = 0;
            sectionFields.forEach((f) => {
                const match = !term || f.dataset.search.includes(term);
                f.hidden = !match;
                if (match) visible++;
            });
            card.querySelectorAll('.st-group').forEach((g) => { g.hidden = Boolean(term); });
            card.hidden = visible === 0;
            any = any || visible > 0;
        });
        $('noResults').hidden = any;
    });

    // Jump to the first field with an error.
    const firstError = document.querySelector('.st-field.has-error .form-input, .st-field.has-error input');
    if (firstError) { firstError.scrollIntoView({block: 'center'}); firstError.focus({preventScroll: true}); }
    refreshDirty();
})();
