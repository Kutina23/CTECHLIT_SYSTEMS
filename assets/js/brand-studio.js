/**
 * Brand Studio — library, editor and brand kit for flyers & social posts,
 * presentations, letterheads and business cards.
 */
(function () {
    'use strict';

    const CONFIG = window.BRAND_STUDIO || {};
    const API = CONFIG.api;
    const root = document.getElementById('brandStudio');
    if (!root || !API) return;

    const TYPES = {
        flyer: {label: 'Flyer & social post', short: 'Flyer', icon: 'fa-image', color: '#7C3AED', blurb: 'Posters, Instagram, WhatsApp status, Facebook'},
        powerpoint: {label: 'Presentation', short: 'Slides', icon: 'fa-person-chalkboard', color: '#F97316', blurb: 'Editable PowerPoint deck + PDF'},
        letterhead: {label: 'Letterhead', short: 'Letter', icon: 'fa-envelope-open-text', color: '#0891B2', blurb: 'Branded letter as Word or PDF'},
        business_card: {label: 'Business card', short: 'Card', icon: 'fa-id-card', color: '#059669', blurb: 'Front & back, print-ready PDF'}
    };

    const state = {kit: {}, business: {}, can: {}, me: 0, logo: null, lib: [], filter: '', search: '', mine: false, doc: null, page: 0, tab: 'content', dirty: false, readOnly: false};

    // ---------- Helpers ----------
    const esc = (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; };
    const notify = (m, type) => (window.ModalController ? ModalController.showNotification(m, type || 'success') : CtlDialog.alert({message: m}));
    const clone = (o) => JSON.parse(JSON.stringify(o));
    const ago = (v) => {
        const d = new Date(String(v).replace(' ', 'T'));
        const s = Math.round((Date.now() - d) / 1000);
        if (s < 3600) return Math.max(1, Math.round(s / 60)) + ' min ago';
        if (s < 86400) return Math.round(s / 3600) + ' h ago';
        return d.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
    };

    async function request(url, options) {
        const response = await fetch(url, Object.assign({credentials: 'same-origin', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}}, options || {}));
        let result = {};
        try { result = await response.json(); } catch (e) { /* non-JSON */ }
        if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Request failed (HTTP ' + response.status + ')');
        return result;
    }
    const post = (payload) => request(API, {method: 'POST', body: JSON.stringify(payload)});

    function blobToDataUrl(blob) {
        return new Promise((resolve, reject) => { const r = new FileReader(); r.onload = () => resolve(r.result); r.onerror = reject; r.readAsDataURL(blob); });
    }

    /** Downscale a photo so saved designs stay small. */
    function readPhoto(file, max) {
        return new Promise((resolve, reject) => {
            if (!/^image\/(png|jpe?g|webp|gif)$/i.test(file.type)) { reject(new Error('Choose a PNG, JPG, WebP or GIF image.')); return; }
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                const scale = Math.min(1, (max || 1600) / Math.max(img.naturalWidth, img.naturalHeight));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.naturalWidth * scale);
                canvas.height = Math.round(img.naturalHeight * scale);
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                resolve(canvas.toDataURL('image/jpeg', 0.86));
            };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('That image could not be read.')); };
            img.src = url;
        });
    }

    function ctx() {
        return {kit: state.kit, business: state.business, logo: state.logo};
    }

    // ---------- Defaults ----------
    function defaults(type) {
        const b = state.business;
        if (type === 'powerpoint') {
            return {theme: 'brand', footer: b.name, logoPlate: true, slides: [
                {layout: 'title', title: b.name + ' — Company Overview', subtitle: b.tagline || 'Digital services you can count on', body: ''},
                {layout: 'bullets', title: 'What we offer', subtitle: 'Everything in one place', bullets: 'High-speed internet & gaming lounge\nPrinting, scanning & typing\nMobile money & digital services\nICT training and technical support'},
                {layout: 'stat', title: 'Trusted by our community', stat: '500+', statLabel: 'customers served every month', body: 'Fast machines, a comfortable space and staff who actually help.'},
                {layout: 'closing', title: 'Thank you', subtitle: 'Let’s work together', body: ''}
            ]};
        }
        if (type === 'letterhead') {
            return {theme: 'brand', layout: 'classic', logoPlate: true, fields: {
                date: new Date().toLocaleDateString('en-GB', {day: 'numeric', month: 'long', year: 'numeric'}),
                recipient: 'The Manager\nOrganisation name\nAddress',
                subject: 'Partnership Proposal',
                body: 'Dear Sir/Madam,\n\nWe are pleased to present this proposal on behalf of ' + b.name + '. We look forward to the opportunity to work with you and provide reliable digital services to your organisation.\n\nPlease feel free to contact us at any time to discuss the details.',
                closing: 'Yours faithfully,', signName: CONFIG.staffName || '', signTitle: 'Manager'
            }};
        }
        if (type === 'business_card') {
            return {theme: 'brand', layout: 'classic', logoPlate: true, fields: {personName: CONFIG.staffName || 'Your Name', personTitle: 'Manager', phone: b.phone, email: b.email, address: b.address, website: state.kit.website}};
        }
        return {theme: 'brand', format: 'square', layout: 'bold', logoPlate: true, fields: {
            eyebrow: 'New this week', headline: 'Fast internet, printing & more', subheadline: 'Everything digital, under one roof',
            body: 'Browse, print, scan and get things done with friendly help. Open Monday – Saturday.', offer: '20% OFF',
            cta: 'Visit us today', contact: [b.phone, state.kit.website].filter(Boolean).join('  ·  '), photo: null
        }};
    }

    // ---------- Field schemas ----------
    const F = (key, label, type, extra) => Object.assign({key, label, type: type || 'text'}, extra || {});
    const SCHEMA = {
        flyer: [F('eyebrow', 'Small label above the headline'), F('headline', 'Headline', 'text', {hint: 'Short and punchy — it auto-sizes to fit.'}), F('subheadline', 'Sub-headline'),
            F('body', 'Details', 'textarea', {rows: 3, hint: 'Hidden on wide banner formats.'}), F('offer', 'Offer badge text', 'text', {hint: 'Used by the “Offer badge” layout, e.g. 50% OFF.'}),
            F('cta', 'Button text'), F('contact', 'Contact line'), F('photo', 'Photo (optional)', 'image')],
        letterhead: [F('date', 'Date'), F('recipient', 'Recipient', 'textarea', {rows: 3}), F('subject', 'Subject'), F('body', 'Letter', 'textarea', {rows: 10}),
            F('closing', 'Closing'), F('signName', 'Signed by'), F('signTitle', 'Position')],
        business_card: [F('personName', 'Name'), F('personTitle', 'Job title'), F('phone', 'Phone'), F('email', 'Email'), F('address', 'Address'), F('website', 'Website')]
    };
    const SLIDE_FIELDS = {
        title: ['title', 'subtitle', 'body', 'photo'], closing: ['title', 'subtitle', 'body', 'photo'],
        bullets: ['title', 'subtitle', 'bullets'], stat: ['title', 'stat', 'statLabel', 'body'], image: ['title', 'body', 'photo']
    };
    const SLIDE_LABELS = {title: F('title', 'Title'), subtitle: F('subtitle', 'Subtitle'), body: F('body', 'Text', 'textarea', {rows: 4}),
        bullets: F('bullets', 'Bullet points', 'textarea', {rows: 6, hint: 'One point per line (up to 6).'}), stat: F('stat', 'Big number', 'text', {hint: 'e.g. 500+, 98%, GHS 2'}),
        statLabel: F('statLabel', 'Number caption'), photo: F('photo', 'Photo', 'image')};

    // ---------- Routing ----------
    function route() {
        const [view, arg] = location.hash.replace(/^#/, '').split('/');
        if (view === 'new' && TYPES[arg]) newDoc(arg);
        else if (view === 'edit' && Number(arg)) openDoc(Number(arg));
        else showLibrary();
    }
    async function leaveEditor() {
        if (!state.doc || !state.dirty) return true;
        return CtlDialog.confirm({title: 'Discard unsaved changes?', message: 'Your latest edits to “' + state.doc.name + '” have not been saved.', tone: 'warning', confirmText: 'Discard', cancelText: 'Keep editing', icon: 'fa-floppy-disk'});
    }
    window.addEventListener('hashchange', route);
    window.addEventListener('beforeunload', (e) => { if (state.doc && state.dirty) { e.preventDefault(); e.returnValue = ''; } });

    // ---------- Header (kit chip) ----------
    function kitChip() {
        const k = state.kit;
        return '<button type="button" class="bs-kit-chip" data-kit>' + (state.logo ? '<img src="' + state.logo + '" alt="">' : '')
            + '<span class="dots"><i style="background:' + esc(k.primary) + '"></i><i style="background:' + esc(k.secondary) + '"></i><i style="background:' + esc(k.dark) + '"></i></span>Brand kit</button>';
    }

    // ---------- Library ----------
    async function showLibrary() {
        state.doc = null;
        state.dirty = false;
        root.innerHTML = '<div class="bs-top"><div><h1>Brand Studio</h1><p>On-brand flyers, social posts, presentations, letterheads and business cards</p></div><span class="bs-spacer"></span>' + kitChip() + '</div>'
            + (state.can.create ? '<div class="bs-create">' + Object.entries(TYPES).map(([key, t]) => '<button type="button" data-new="' + key + '"><span class="ic" style="background:' + t.color + '"><i class="fas ' + t.icon + '"></i></span><span><strong>New ' + esc(t.label.toLowerCase()) + '</strong><small>' + esc(t.blurb) + '</small></span></button>').join('') + '</div>' : '')
            + '<div class="card glass-card"><div class="bs-lib-bar"><div class="bs-chips" role="group" aria-label="Filter by type">'
            + [['', 'All']].concat(Object.entries(TYPES).map(([k, t]) => [k, t.short])).map(([k, l]) => '<button type="button" data-filter="' + k + '" aria-pressed="' + (state.filter === k) + '">' + l + '</button>').join('')
            + '</div><input class="form-input" id="bsSearch" type="search" placeholder="Search designs" value="' + esc(state.search) + '" style="max-width:260px;padding:8px 12px">'
            + '<label class="bs-toggle"><input type="checkbox" id="bsMine"' + (state.mine ? ' checked' : '') + '> Only mine</label></div>'
            + '<div class="bs-lib" id="bsLib"><div class="bs-empty">Loading designs…</div></div></div>';
        await loadLibrary();
    }

    async function loadLibrary() {
        const params = new URLSearchParams({list: '1'});
        if (state.filter) params.set('type', state.filter);
        if (state.search) params.set('search', state.search);
        if (state.mine) params.set('mine', '1');
        const grid = document.getElementById('bsLib');
        try {
            state.lib = (await request(API + '?' + params.toString())).data;
            if (!grid) return;
            grid.innerHTML = state.lib.length ? state.lib.map((a) => {
                const t = TYPES[a.asset_type] || TYPES.flyer;
                const own = Number(a.created_by) === Number(state.me) || state.can.admin;
                return '<article class="bs-item" data-id="' + a.asset_id + '">'
                    + '<button type="button" class="bs-thumb" data-open aria-label="Open ' + esc(a.name) + '">' + (a.thumbnail ? '<img src="' + a.thumbnail + '" alt="">' : '<i class="fas ' + t.icon + '"></i>') + '</button>'
                    + '<div class="bs-item-body"><div><div class="bs-item-name" title="' + esc(a.name) + '">' + esc(a.name) + '</div>'
                    + '<div class="bs-item-meta"><span class="bs-type">' + t.short + '</span> ' + esc(a.created_by_name || '') + ' · ' + ago(a.updated_at) + '</div></div>'
                    + '<div class="bs-menu"><button type="button" data-menu aria-label="More actions" aria-haspopup="true"><i class="fas fa-ellipsis-vertical"></i></button><ul hidden>'
                    + '<li><button type="button" data-open><i class="fas fa-pen"></i> Open</button></li>'
                    + (state.can.create ? '<li><button type="button" data-dup><i class="fas fa-copy"></i> Duplicate</button></li>' : '')
                    + (own && state.can.edit ? '<li><button type="button" data-rename><i class="fas fa-i-cursor"></i> Rename</button></li>' : '')
                    + (own && state.can.delete ? '<li><button type="button" class="danger" data-del><i class="fas fa-trash"></i> Delete</button></li>' : '')
                    + '</ul></div></div></article>';
            }).join('') : '<div class="bs-empty"><i class="fas fa-swatchbook" style="font-size:30px;color:var(--violet);opacity:.6;display:block;margin-bottom:8px"></i>'
                + (state.search || state.filter || state.mine ? 'No designs match.' : 'No designs yet. Start with one of the templates above.') + '</div>';
        } catch (error) {
            if (grid) grid.innerHTML = '<div class="bs-empty">' + esc(error.message) + '</div>';
        }
    }

    root.addEventListener('click', async (event) => {
        const target = event.target;
        if (target.closest('[data-kit]')) { event.preventDefault(); openKit(); return; }
        const newBtn = target.closest('[data-new]');
        if (newBtn) { location.hash = 'new/' + newBtn.dataset.new; return; }
        const filter = target.closest('[data-filter]');
        if (filter) { state.filter = filter.dataset.filter; root.querySelectorAll('[data-filter]').forEach((b) => b.setAttribute('aria-pressed', String(b === filter))); loadLibrary(); return; }
        const item = target.closest('.bs-item');
        if (target.closest('[data-menu]')) {
            const list = target.closest('.bs-menu').querySelector('ul');
            const open = list.hidden;
            root.querySelectorAll('.bs-menu ul').forEach((u) => { u.hidden = true; });
            list.hidden = !open;
            return;
        }
        if (!item) { root.querySelectorAll('.bs-menu ul').forEach((u) => { u.hidden = true; }); }
        if (item) {
            const id = Number(item.dataset.id);
            const asset = state.lib.find((a) => Number(a.asset_id) === id);
            if (target.closest('[data-open]')) { location.hash = 'edit/' + id; return; }
            if (target.closest('[data-dup]')) {
                try { const r = await post({action: 'duplicate', asset_id: id}); notify(r.message); location.hash = 'edit/' + r.data.asset_id; } catch (e) { notify(e.message, 'error'); }
                return;
            }
            if (target.closest('[data-rename]')) {
                const name = await CtlDialog.prompt({title: 'Rename design', label: 'Name', value: asset.name, maxLength: 150, icon: 'fa-i-cursor', confirmText: 'Rename'});
                if (name === null) return;
                try { await post({action: 'rename', asset_id: id, name}); loadLibrary(); } catch (e) { notify(e.message, 'error'); }
                return;
            }
            if (target.closest('[data-del]')) {
                const ok = await CtlDialog.confirm({title: 'Delete “' + asset.name + '”?', message: 'This design is removed for everyone. Files you already downloaded are not affected.', tone: 'danger', icon: 'fa-trash', confirmText: 'Delete'});
                if (!ok) return;
                try { await request(API, {method: 'DELETE', body: JSON.stringify({asset_id: id})}); notify('Design deleted'); loadLibrary(); } catch (e) { notify(e.message, 'error'); }
            }
        }
    });
    let searchTimer = null;
    root.addEventListener('input', (event) => {
        if (event.target.id === 'bsSearch') { clearTimeout(searchTimer); searchTimer = setTimeout(() => { state.search = event.target.value.trim(); loadLibrary(); }, 250); }
    });
    root.addEventListener('change', (event) => {
        if (event.target.id === 'bsMine') { state.mine = event.target.checked; loadLibrary(); }
    });

    // ---------- Editor ----------
    function newDoc(type) {
        if (!state.can.create) { notify('You do not have permission to create designs.', 'error'); location.hash = ''; return; }
        state.doc = {id: null, type, name: 'Untitled ' + TYPES[type].label.toLowerCase(), config: defaults(type), owner: state.me};
        state.page = 0;
        state.tab = 'content';
        state.readOnly = false;
        state.dirty = true;
        renderEditor();
    }

    async function openDoc(id) {
        try {
            const {data} = await request(API + '?asset_id=' + id);
            const base = defaults(data.asset_type);
            const config = Object.assign(base, data.config || {});
            if (base.fields) config.fields = Object.assign({}, base.fields, (data.config || {}).fields || {});
            state.doc = {id: data.asset_id, type: data.asset_type, name: data.name, config, owner: Number(data.created_by), ownerName: data.created_by_name};
            state.page = 0;
            state.tab = 'content';
            state.readOnly = !state.can.edit || (Number(data.created_by) !== Number(state.me) && !state.can.admin);
            state.dirty = false;
            renderEditor();
        } catch (error) {
            notify(error.message, 'error');
            location.hash = '';
        }
    }

    function exportItems(type) {
        const items = [];
        if (type === 'powerpoint') {
            items.push(['pptx', 'fa-file-powerpoint', 'PowerPoint (.pptx)', 'Editable slides with real text']);
            items.push(['pdf', 'fa-file-pdf', 'PDF', 'All slides, one per page']);
            items.push(['png', 'fa-image', 'PNG — this slide', 'Full HD 1920×1080']);
            items.push(['png-all', 'fa-images', 'PNG — all slides', 'One image per slide']);
        } else if (type === 'letterhead') {
            items.push(['docx', 'fa-file-word', 'Word (.docx)', 'Editable letter with branded header & footer']);
            items.push(['pdf', 'fa-file-pdf', 'PDF (A4)', 'Ready to print or email']);
            items.push(['png', 'fa-image', 'PNG', 'A4 image']);
        } else if (type === 'business_card') {
            items.push(['pdf', 'fa-file-pdf', 'Print PDF', 'Front & back, 3.5 × 2 in']);
            items.push(['png-all', 'fa-images', 'PNG — front & back', 'High resolution']);
        } else {
            items.push(['png', 'fa-image', 'PNG', 'Best for social media']);
            items.push(['jpg', 'fa-file-image', 'JPG', 'Smaller file for WhatsApp']);
            items.push(['pdf', 'fa-file-pdf', 'PDF', state.doc.config.format === 'a4' ? 'A4, print-ready' : 'Same size as the design']);
        }
        return items;
    }

    function renderEditor() {
        const doc = state.doc;
        const t = TYPES[doc.type];
        root.innerHTML = '<div class="bs-editbar">'
            + '<button class="btn btn-secondary small" type="button" data-back><i class="fas fa-arrow-left"></i> Library</button>'
            + '<span class="bs-type" style="background:' + t.color + '1a;color:' + t.color + '">' + esc(t.label) + '</span>'
            + '<label class="sr-only" for="bsName">Design name</label><input class="bs-name" id="bsName" value="' + esc(doc.name) + '" maxlength="150"' + (state.readOnly ? ' readonly' : '') + '>'
            + '<span class="bs-status" id="bsStatus"></span>'
            + kitChip()
            + (state.readOnly ? (state.can.create ? '<button class="btn btn-secondary" type="button" data-dupdoc><i class="fas fa-copy"></i> Duplicate to edit</button>' : '')
                : '<button class="btn btn-secondary" type="button" data-save><i class="fas fa-floppy-disk"></i> Save</button>')
            + '<div class="bs-export"><button class="btn btn-primary" type="button" data-export-toggle aria-haspopup="true" aria-expanded="false"><i class="fas fa-download"></i> Download</button><ul hidden>'
            + exportItems(doc.type).map(([k, i, l, d]) => '<li><button type="button" data-export="' + k + '"><i class="fas ' + i + '"></i><span>' + esc(l) + '</span><small>' + esc(d) + '</small></button></li>').join('')
            + '</ul></div></div>'
            + (state.readOnly ? '<div class="bs-readonly" style="margin-bottom:14px"><i class="fas fa-lock"></i> This design belongs to ' + esc(doc.ownerName || 'another staff member') + '. You can download it, or duplicate it to make changes.</div>' : '')
            + '<div class="bs-editor"><aside class="card glass-card bs-panel" aria-label="Design settings"><div class="bs-tabs" role="tablist">'
            + '<button type="button" role="tab" data-tab="content" aria-selected="' + (state.tab === 'content') + '">Content</button>'
            + '<button type="button" role="tab" data-tab="design" aria-selected="' + (state.tab === 'design') + '">Design</button></div>'
            + '<div class="bs-panel-body" id="bsPanel"></div></aside>'
            + '<section class="card glass-card bs-stage" aria-label="Preview"><div class="bs-canvas" id="bsCanvas"></div>'
            + '<div class="bs-stage-meta"><span id="bsMeta"></span><span id="bsWarn" class="bs-warn"></span></div>'
            + '<div id="bsPagesWrap"></div></section></div>';
        renderPanel();
        refresh(false);
    }

    function setDirty(dirty) {
        state.dirty = dirty;
        const status = document.getElementById('bsStatus');
        if (status) {
            status.textContent = state.readOnly ? 'Read only' : dirty ? (state.doc.id ? 'Unsaved changes' : 'Not saved yet') : 'All changes saved';
            status.classList.toggle('dirty', dirty && !state.readOnly);
        }
    }

    let renderQueued = false;
    function refresh(markDirty) {
        if (markDirty && !state.readOnly) setDirty(true);
        else setDirty(state.dirty);
        if (renderQueued) return;
        renderQueued = true;
        requestAnimationFrame(() => {
            renderQueued = false;
            const doc = state.doc;
            if (!doc) return;
            const pages = BrandRender.pages(doc.type, doc.config, ctx());
            state.page = Math.min(state.page, pages.length - 1);
            const page = pages[state.page];
            document.getElementById('bsCanvas').innerHTML = page.svg;
            document.getElementById('bsMeta').textContent = page.label + ' · ' + page.w + ' × ' + page.h + ' px';
            document.getElementById('bsWarn').textContent = page.overflow ? 'The letter is longer than one page — PDF/PNG are cut off; the Word download keeps everything.' : '';
            renderPages(pages);
        });
    }

    function renderPages(pages) {
        const wrap = document.getElementById('bsPagesWrap');
        const doc = state.doc;
        if (doc.type !== 'powerpoint' && doc.type !== 'business_card') { wrap.innerHTML = ''; return; }
        const deck = doc.type === 'powerpoint';
        wrap.innerHTML = '<div class="bs-pages" role="list">' + pages.map((p, i) => '<button type="button" class="bs-page" role="listitem" data-page="' + i + '" aria-current="' + (i === state.page) + '" aria-label="' + esc(p.label) + '">' + p.svg + '<span>' + (deck ? i + 1 : esc(p.label)) + '</span></button>').join('')
            + (deck && !state.readOnly ? '<button type="button" class="bs-page-add" data-slide="add" aria-label="Add slide" title="Add slide"><i class="fas fa-plus"></i></button>' : '') + '</div>'
            + (deck && !state.readOnly ? '<div class="bs-slide-tools" style="margin-top:8px">'
                + '<button class="btn btn-secondary small" type="button" data-slide="left" title="Move left"><i class="fas fa-arrow-left"></i></button>'
                + '<button class="btn btn-secondary small" type="button" data-slide="right" title="Move right"><i class="fas fa-arrow-right"></i></button>'
                + '<button class="btn btn-secondary small" type="button" data-slide="dup"><i class="fas fa-copy"></i> Duplicate slide</button>'
                + '<button class="btn btn-secondary small" type="button" data-slide="del" style="color:#DC2626"><i class="fas fa-trash"></i> Delete slide</button></div>' : '');
    }

    function fieldHtml(field, value) {
        const id = 'bsf-' + field.key;
        const disabled = state.readOnly ? ' disabled' : '';
        let control;
        if (field.type === 'textarea') control = '<textarea id="' + id + '" data-field="' + field.key + '" rows="' + (field.rows || 3) + '"' + disabled + '>' + esc(value || '') + '</textarea>';
        else if (field.type === 'image') {
            control = '<div class="bs-img-field">' + (value ? '<img src="' + value + '" alt="">' : '')
                + (state.readOnly ? '' : '<label class="btn btn-secondary small" style="margin:0"><i class="fas fa-upload"></i> ' + (value ? 'Replace' : 'Upload') + '<input type="file" accept="image/*" data-image="' + field.key + '" hidden></label>'
                + (value ? ' <button type="button" class="btn btn-secondary small" data-clear-image="' + field.key + '">Remove</button>' : '')) + '</div>';
            return '<div class="bs-field"><span class="bs-label">' + esc(field.label) + '</span>' + control + (field.hint ? '<div class="bs-hint">' + esc(field.hint) + '</div>' : '') + '</div>';
        } else control = '<input type="text" id="' + id + '" data-field="' + field.key + '" value="' + esc(value || '') + '"' + disabled + '>';
        return '<div class="bs-field"><label for="' + id + '">' + esc(field.label) + '</label>' + control + (field.hint ? '<div class="bs-hint">' + esc(field.hint) + '</div>' : '') + '</div>';
    }

    function currentFields() {
        const doc = state.doc;
        if (doc.type === 'powerpoint') return doc.config.slides[state.page];
        return doc.config.fields;
    }

    function layoutButtons(map, current, attr) {
        return '<div class="bs-layouts">' + Object.entries(map).map(([key, label]) => {
            let preview = '';
            if (state.doc.type !== 'powerpoint') {
                const cfg = clone(state.doc.config);
                cfg.layout = key;
                preview = BrandRender.pages(state.doc.type, cfg, ctx())[state.doc.type === 'business_card' ? 1 : 0].svg;
            }
            return '<button type="button" ' + attr + '="' + key + '" aria-pressed="' + (key === current) + '"' + (state.readOnly ? ' disabled' : '') + '>' + preview + esc(label) + '</button>';
        }).join('') + '</div>';
    }

    function renderPanel() {
        const doc = state.doc;
        const panel = document.getElementById('bsPanel');
        const c = doc.config;
        let html = '';
        if (state.tab === 'content') {
            if (doc.type === 'powerpoint') {
                const slide = c.slides[state.page];
                html += '<div class="bs-field"><label for="bsSlideLayout">Slide ' + (state.page + 1) + ' of ' + c.slides.length + ' — layout</label><select id="bsSlideLayout"' + (state.readOnly ? ' disabled' : '') + '>'
                    + Object.entries(BrandRender.SLIDE_LAYOUTS).map(([k, l]) => '<option value="' + k + '"' + (slide.layout === k ? ' selected' : '') + '>' + esc(l) + '</option>').join('') + '</select></div>';
                (SLIDE_FIELDS[slide.layout] || SLIDE_FIELDS.bullets).forEach((key) => { html += fieldHtml(SLIDE_LABELS[key], slide[key]); });
                html += fieldHtml(F('notes', 'Speaker notes (PowerPoint only)', 'textarea', {rows: 2}), slide.notes);
            } else {
                if (doc.type === 'business_card') html += '<div class="bs-hint">Front shows your logo and business name from the brand kit; the back uses these details.</div>';
                SCHEMA[doc.type].forEach((field) => { html += fieldHtml(field, c.fields[field.key]); });
            }
        } else {
            if (doc.type === 'flyer') {
                html += '<div class="bs-field"><label for="bsFormat">Size</label><select id="bsFormat"' + (state.readOnly ? ' disabled' : '') + '>'
                    + Object.entries(BrandRender.FORMATS).map(([k, f]) => '<option value="' + k + '"' + (c.format === k ? ' selected' : '') + '>' + esc(f.label) + ' · ' + f.w + '×' + f.h + '</option>').join('') + '</select></div>';
                html += '<div><span class="bs-label">Layout</span>' + layoutButtons(BrandRender.FLYER_LAYOUTS, c.layout, 'data-layout') + '</div>';
            } else if (doc.type === 'letterhead') {
                html += '<div><span class="bs-label">Layout</span>' + layoutButtons(BrandRender.LETTER_LAYOUTS, c.layout, 'data-layout') + '<div class="bs-hint">The Word download always uses a header-and-footer letterhead so it stays editable.</div></div>';
            } else if (doc.type === 'business_card') {
                html += '<div><span class="bs-label">Back style</span>' + layoutButtons(BrandRender.CARD_LAYOUTS, c.layout, 'data-layout') + '</div>';
            } else {
                html += fieldHtml(F('footer', 'Footer text on every slide'), c.footer);
            }
            html += '<div><span class="bs-label">Colours</span><div class="bs-themes">' + Object.entries(BrandRender.THEMES).map(([k, th]) => {
                const p = BrandRender.theme(k, state.kit);
                return '<button type="button" data-theme="' + k + '" aria-pressed="' + ((c.theme || 'brand') === k) + '"' + (state.readOnly ? ' disabled' : '') + '><span class="sw"><i style="background:' + p.primary + '"></i><i style="background:' + p.secondary + '"></i><i style="background:' + p.dark + '"></i></span>' + esc(th.label) + '</button>';
            }).join('') + '</div></div>';
            html += '<label class="bs-toggle"><input type="checkbox" id="bsPlate"' + (c.logoPlate !== false ? ' checked' : '') + (state.readOnly ? ' disabled' : '') + '> White backing behind the logo on dark colours</label>';
            if (!state.logo) html += '<div class="bs-hint"><i class="fas fa-circle-info"></i> No logo yet — your initials are used. <a href="#" data-kit>Upload a logo in the brand kit</a>.</div>';
        }
        panel.innerHTML = html;
    }

    // Editor events
    root.addEventListener('input', (event) => {
        if (!state.doc || state.readOnly) return;
        const target = event.target;
        if (target.id === 'bsName') { state.doc.name = target.value; setDirty(true); return; }
        if (target.dataset.field) {
            if (state.doc.type === 'powerpoint' && target.dataset.field === 'footer') state.doc.config.footer = target.value;
            else currentFields()[target.dataset.field] = target.value;
            refresh(true);
        }
    });
    root.addEventListener('change', async (event) => {
        if (!state.doc || state.readOnly) return;
        const target = event.target;
        if (target.id === 'bsFormat') { state.doc.config.format = target.value; renderPanel(); refresh(true); }
        if (target.id === 'bsSlideLayout') { state.doc.config.slides[state.page].layout = target.value; renderPanel(); refresh(true); }
        if (target.id === 'bsPlate') { state.doc.config.logoPlate = target.checked; refresh(true); }
        if (target.dataset.image) {
            const file = target.files[0];
            if (!file) return;
            try {
                currentFields()[target.dataset.image] = await readPhoto(file, 1600);
                renderPanel();
                refresh(true);
            } catch (error) { notify(error.message, 'error'); }
        }
    });
    root.addEventListener('click', async (event) => {
        if (!state.doc) return;
        const target = event.target;
        if (target.closest('[data-back]')) { if (await leaveEditor()) { state.dirty = false; location.hash = ''; } return; }
        const tab = target.closest('[data-tab]');
        if (tab) { state.tab = tab.dataset.tab; root.querySelectorAll('[data-tab]').forEach((b) => b.setAttribute('aria-selected', String(b === tab))); renderPanel(); return; }
        if (target.closest('[data-save]')) { saveDoc(); return; }
        if (target.closest('[data-dupdoc]')) {
            try { const r = await post({action: 'duplicate', asset_id: state.doc.id}); notify('Copy created — you can edit it now.'); location.hash = 'edit/' + r.data.asset_id; } catch (e) { notify(e.message, 'error'); }
            return;
        }
        const exportToggle = target.closest('[data-export-toggle]');
        const menu = root.querySelector('.bs-export ul');
        if (exportToggle) { menu.hidden = !menu.hidden; exportToggle.setAttribute('aria-expanded', String(!menu.hidden)); return; }
        if (menu && !target.closest('.bs-export')) menu.hidden = true;
        const exp = target.closest('[data-export]');
        if (exp) { menu.hidden = true; runExport(exp.dataset.export); return; }
        const layout = target.closest('[data-layout]');
        if (layout && !state.readOnly) { state.doc.config.layout = layout.dataset.layout; root.querySelectorAll('[data-layout]').forEach((b) => b.setAttribute('aria-pressed', String(b === layout))); refresh(true); return; }
        const themeBtn = target.closest('[data-theme]');
        if (themeBtn && !state.readOnly) { state.doc.config.theme = themeBtn.dataset.theme; renderPanel(); refresh(true); return; }
        const clear = target.closest('[data-clear-image]');
        if (clear && !state.readOnly) { currentFields()[clear.dataset.clearImage] = null; renderPanel(); refresh(true); return; }
        const pageBtn = target.closest('[data-page]');
        if (pageBtn) { state.page = Number(pageBtn.dataset.page); renderPanel(); refresh(false); return; }
        const slideAct = target.closest('[data-slide]');
        if (slideAct && !state.readOnly) {
            const slides = state.doc.config.slides;
            const act = slideAct.dataset.slide;
            if (act === 'add') { slides.splice(state.page + 1, 0, {layout: 'bullets', title: 'New slide', subtitle: '', bullets: 'First point\nSecond point\nThird point'}); state.page++; }
            if (act === 'dup') { slides.splice(state.page + 1, 0, clone(slides[state.page])); state.page++; }
            if (act === 'left' && state.page > 0) { [slides[state.page - 1], slides[state.page]] = [slides[state.page], slides[state.page - 1]]; state.page--; }
            if (act === 'right' && state.page < slides.length - 1) { [slides[state.page + 1], slides[state.page]] = [slides[state.page], slides[state.page + 1]]; state.page++; }
            if (act === 'del') {
                if (slides.length === 1) { notify('A presentation needs at least one slide.', 'warning'); return; }
                if (!(await CtlDialog.confirm({title: 'Delete slide ' + (state.page + 1) + '?', tone: 'danger', icon: 'fa-trash', confirmText: 'Delete slide'}))) return;
                slides.splice(state.page, 1);
                state.page = Math.max(0, state.page - 1);
            }
            renderPanel();
            refresh(true);
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's' && state.doc && !state.readOnly) {
            event.preventDefault();
            saveDoc();
        }
    });

    async function saveDoc() {
        const doc = state.doc;
        if (!doc.name.trim()) { notify('Give the design a name first.', 'error'); document.getElementById('bsName').focus(); return; }
        const button = root.querySelector('[data-save]');
        if (button) button.disabled = true;
        try {
            const pages = BrandRender.pages(doc.type, doc.config, ctx());
            const thumbnail = await BrandExport.thumbnail(pages[0]).catch(() => null);
            const result = await post({action: 'save', asset_id: doc.id, asset_type: doc.type, name: doc.name.trim(), config: doc.config, thumbnail});
            const wasNew = !doc.id;
            doc.id = result.data.asset_id;
            setDirty(false);
            notify('Saved “' + doc.name + '”');
            if (wasNew) history.replaceState(null, '', '#edit/' + doc.id);
        } catch (error) {
            notify(error.message, 'error');
        } finally {
            if (button) button.disabled = false;
        }
    }

    async function runExport(kind) {
        const doc = state.doc;
        const pages = BrandRender.pages(doc.type, doc.config, ctx());
        const status = document.getElementById('bsStatus');
        const previous = status.textContent;
        status.textContent = 'Preparing download…';
        try {
            if (kind === 'png' || kind === 'jpg') await BrandExport.image(pages, doc.type === 'powerpoint' ? state.page : 0, kind, doc.name);
            else if (kind === 'png-all') await BrandExport.image(pages, 'all', 'png', doc.name);
            else if (kind === 'pdf') await BrandExport.pdf(doc.type, doc.config, pages, doc.name);
            else if (kind === 'pptx') await BrandExport.pptx(doc.config, ctx(), doc.name);
            else if (kind === 'docx') await BrandExport.docx(doc.config, ctx(), doc.name);
            notify('Download ready');
        } catch (error) {
            notify(error.message || 'The download failed.', 'error');
        } finally {
            status.textContent = previous;
        }
    }

    // ---------- Brand kit ----------
    function openKit() {
        const k = state.kit;
        const canEdit = state.can.edit;
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'bsKitTitle');
        overlay.innerHTML = '<div class="modal"><div class="modal-header"><h3 class="modal-title" id="bsKitTitle">Brand kit</h3><button class="modal-close" type="button" data-close aria-label="Close"><i class="fas fa-times"></i></button></div>'
            + '<div class="modal-body"><div class="bs-kit">'
            + '<div><span class="bs-label">Logo</span><div class="bs-kit-logo"><div class="box">' + (state.logo ? '<img src="' + state.logo + '" alt="Current logo">' : '<span style="font-size:12px;color:var(--muted)">No logo</span>') + '</div>'
            + (canEdit ? '<div style="display:grid;gap:6px"><label class="btn btn-secondary small" style="margin:0"><i class="fas fa-upload"></i> Upload logo<input type="file" accept="image/png,image/jpeg,image/webp" id="bsLogoFile" hidden></label>'
                + (state.logo ? '<button type="button" class="btn btn-secondary small" id="bsLogoRemove">Remove</button>' : '') + '<span class="bs-hint">PNG with a transparent background works best (max 2 MB).</span></div>' : '') + '</div></div>'
            + '<div><span class="bs-label">Colours</span><div class="bs-kit-colors">'
            + [['primary', 'Primary'], ['secondary', 'Accent'], ['dark', 'Dark']].map(([key, label]) => '<label>' + label + '<input type="color" data-color="' + key + '" value="' + esc(k[key]) + '"' + (canEdit ? '' : ' disabled') + '></label>').join('') + '</div></div>'
            + '<div class="bs-field"><label for="bsKitWebsite">Website</label><input type="text" id="bsKitWebsite" value="' + esc(k.website || '') + '" placeholder="www.example.com"' + (canEdit ? '' : ' disabled') + '></div>'
            + '<div class="bs-hint">Business name, tagline, phone, email and address come from <a href="settings.php">Settings</a>: ' + esc([state.business.name, state.business.phone, state.business.email].filter(Boolean).join(' · ')) + '</div>'
            + '</div></div><div class="modal-footer"><button class="btn btn-secondary" type="button" data-close>' + (canEdit ? 'Cancel' : 'Close') + '</button>'
            + (canEdit ? '<button class="btn btn-primary" type="button" id="bsKitSave">Save brand kit</button>' : '') + '</div></div>';
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        const close = () => { overlay.remove(); document.body.style.overflow = ''; document.removeEventListener('keydown', onKey); };
        const onKey = (e) => { if (e.key === 'Escape' && !document.querySelector('.ctl-dlg-backdrop')) close(); };
        document.addEventListener('keydown', onKey);
        overlay.addEventListener('click', (e) => { if (e.target === overlay || e.target.closest('[data-close]')) close(); });
        const after = async (kit, message) => { await applyKit(kit); notify(message); close(); if (state.doc) { renderPanel(); refresh(false); } else showLibrary(); };

        const file = overlay.querySelector('#bsLogoFile');
        if (file) file.addEventListener('change', async () => {
            if (!file.files[0]) return;
            const form = new FormData();
            form.append('action', 'logo');
            form.append('logo', file.files[0]);
            try {
                const response = await fetch(API, {method: 'POST', body: form, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Upload failed');
                await after(result.data, 'Logo uploaded');
            } catch (error) { notify(error.message, 'error'); }
        });
        const remove = overlay.querySelector('#bsLogoRemove');
        if (remove) remove.addEventListener('click', async () => {
            try { const r = await post({action: 'remove_logo'}); await after(r.data, 'Logo removed'); } catch (e) { notify(e.message, 'error'); }
        });
        const saveBtn = overlay.querySelector('#bsKitSave');
        if (saveBtn) saveBtn.addEventListener('click', async () => {
            const payload = {action: 'kit', website: overlay.querySelector('#bsKitWebsite').value};
            overlay.querySelectorAll('[data-color]').forEach((input) => { payload[input.dataset.color] = input.value; });
            saveBtn.disabled = true;
            try { const r = await post(payload); await after(r.data, 'Brand kit saved — every design now uses it.'); } catch (e) { notify(e.message, 'error'); saveBtn.disabled = false; }
        });
    }

    async function applyKit(kit) {
        state.kit = kit;
        state.logo = null;
        if (kit.logo_url) {
            try {
                const response = await fetch(kit.logo_url, {credentials: 'same-origin'});
                if (response.ok) state.logo = await blobToDataUrl(await response.blob());
            } catch (e) { state.logo = null; }
        }
    }

    // ---------- Start ----------
    (async function init() {
        try {
            const {data} = await request(API + '?kit=1');
            state.business = data.business;
            state.can = data.can;
            state.me = data.me;
            await applyKit(data.kit);
            route();
        } catch (error) {
            root.innerHTML = '<div class="bs-empty">' + esc(error.message) + '</div>';
        }
    })();
})();
