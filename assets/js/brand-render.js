/**
 * BrandRender — turns a Brand Studio design config into SVG pages.
 *
 *   BrandRender.pages(type, config, ctx) → [{svg, w, h, label, overflow?}]
 *   ctx = {kit, business, logo: dataURL|null}
 *
 * Text is wrapped and auto-fitted using canvas measurements with the same
 * system font stack the SVG uses, so previews and exports match.
 */
(function () {
    'use strict';

    const FONT = "'Segoe UI', 'Helvetica Neue', Arial, sans-serif";
    const measureCtx = document.createElement('canvas').getContext('2d');

    // ---------- Formats, layouts, themes ----------
    const FORMATS = {
        a4: {label: 'A4 flyer (print)', w: 1240, h: 1754, mm: [210, 297]},
        square: {label: 'Square post (Instagram, Facebook)', w: 1080, h: 1080},
        story: {label: 'Story / WhatsApp status', w: 1080, h: 1920},
        landscape: {label: 'Landscape 16:9 (X, YouTube, screens)', w: 1600, h: 900},
        link: {label: 'Facebook / LinkedIn link post', w: 1200, h: 630},
        banner: {label: 'X / Twitter header', w: 1500, h: 500}
    };
    const FLYER_LAYOUTS = {bold: 'Bold', split: 'Split', minimal: 'Minimal', offer: 'Offer badge'};
    const SLIDE_LAYOUTS = {title: 'Title', bullets: 'Bullet points', stat: 'Big number', image: 'Photo & text', closing: 'Closing'};
    const LETTER_LAYOUTS = {classic: 'Classic', band: 'Side band', modern: 'Modern header'};
    const CARD_LAYOUTS = {classic: 'Classic', bold: 'Bold'};

    const THEMES = {
        brand: {label: 'Brand kit'},
        midnight: {label: 'Midnight', primary: '#6366F1', secondary: '#22D3EE', dark: '#0B1120'},
        emerald: {label: 'Emerald', primary: '#10B981', secondary: '#F59E0B', dark: '#052E2B'},
        sunset: {label: 'Sunset', primary: '#F97316', secondary: '#E11D48', dark: '#3B0A0A'},
        royal: {label: 'Royal', primary: '#8B5CF6', secondary: '#EC4899', dark: '#1E1B4B'},
        mono: {label: 'Mono', primary: '#111827', secondary: '#6B7280', dark: '#030712'}
    };

    function theme(id, kit) {
        const base = id && id !== 'brand' && THEMES[id] ? THEMES[id] : {primary: kit.primary, secondary: kit.secondary, dark: kit.dark};
        return {
            primary: base.primary, secondary: base.secondary, dark: base.dark,
            light: '#F8FAFC', white: '#FFFFFF', ink: '#0F172A', muted: '#64748B',
            onPrimary: contrast(base.primary)
        };
    }

    // ---------- Helpers ----------
    function esc(value) {
        return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function hexRgb(hex) {
        const h = String(hex || '#000000').replace('#', '');
        return [parseInt(h.slice(0, 2), 16) || 0, parseInt(h.slice(2, 4), 16) || 0, parseInt(h.slice(4, 6), 16) || 0];
    }
    function contrast(hex) {
        const [r, g, b] = hexRgb(hex).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
        return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.45 ? '#0F172A' : '#FFFFFF';
    }
    function initials(name) {
        return String(name || 'C').split(/[\s&]+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
    }
    let uid = 0;
    const nextId = (p) => p + (++uid);

    function measure(text, size, weight) {
        measureCtx.font = (weight || 400) + ' ' + size + 'px ' + FONT;
        return measureCtx.measureText(text).width;
    }

    /** Wrap text (respecting newlines) to lines no wider than maxW. */
    function wrap(text, size, weight, maxW, maxLines) {
        const lines = [];
        String(text || '').split(/\n/).forEach((paragraph, index, all) => {
            const words = paragraph.split(/\s+/).filter(Boolean);
            if (!words.length) { if (index < all.length - 1) lines.push(''); return; }
            let line = '';
            words.forEach((word) => {
                const attempt = line ? line + ' ' + word : word;
                if (measure(attempt, size, weight) <= maxW || !line) {
                    line = attempt;
                    // Break single words longer than the line.
                    while (measure(line, size, weight) > maxW && line.length > 1) {
                        let cut = line.length - 1;
                        while (cut > 1 && measure(line.slice(0, cut), size, weight) > maxW) cut--;
                        lines.push(line.slice(0, cut));
                        line = line.slice(cut);
                    }
                } else {
                    lines.push(line);
                    line = word;
                }
            });
            if (line) lines.push(line);
        });
        if (maxLines && lines.length > maxLines) {
            const kept = lines.slice(0, maxLines);
            let last = kept[maxLines - 1];
            while (last && measure(last + '…', size, weight) > maxW) last = last.slice(0, -1);
            kept[maxLines - 1] = last.replace(/\s+$/, '') + '…';
            kept.truncated = true;
            return kept;
        }
        return lines;
    }

    /** Largest font size (between max and min) at which text fits in maxLines. */
    function fit(text, maxW, maxLines, maxSize, minSize, weight) {
        for (let size = maxSize; size >= minSize; size -= 2) {
            const lines = wrap(text, size, weight, maxW);
            if (lines.length <= maxLines) return {size, lines};
        }
        return {size: minSize, lines: wrap(text, minSize, weight, maxW, maxLines)};
    }

    function textEl(lines, x, y, size, opts) {
        opts = opts || {};
        if (!lines.length) return '';
        const lh = size * (opts.lineHeight || 1.22);
        const anchor = opts.anchor || 'start';
        return '<text x="' + x + '" y="' + (y + size * 0.92) + '" font-family="' + esc(FONT) + '" font-size="' + size + '" font-weight="' + (opts.weight || 400)
            + '" fill="' + (opts.color || '#0F172A') + '" text-anchor="' + anchor + '"' + (opts.opacity ? ' fill-opacity="' + opts.opacity + '"' : '')
            + (opts.spacing ? ' letter-spacing="' + opts.spacing + '"' : '') + '>'
            + lines.map((line, i) => '<tspan x="' + x + '" dy="' + (i === 0 ? 0 : lh) + '">' + esc(line) + '</tspan>').join('') + '</text>';
    }
    const blockHeight = (lines, size, lh) => lines.length ? size * (lh || 1.22) * (lines.length - 1) + size * 1.12 : 0;

    /**
     * Vertical stack of blocks laid out from y within width w.
     * Block kinds: text {text,size,weight,color,maxLines,fit:[max,min],gap}, pill {text,size,bg,color}, button {text,size,bg,color}, rule {color,w,h}, gap {h}.
     */
    function stack(blocks, x, y, w, align) {
        const anchorX = align === 'middle' ? x + w / 2 : x;
        let cursor = y;
        let svg = '';
        blocks.forEach((b) => {
            if (!b || (b.text !== undefined && String(b.text).trim() === '')) return;
            if (b.kind === 'gap') { cursor += b.h; return; }
            if (b.kind === 'rule') {
                const rx = align === 'middle' ? x + (w - b.w) / 2 : x;
                svg += '<rect x="' + rx + '" y="' + cursor + '" width="' + b.w + '" height="' + b.h + '" rx="' + b.h / 2 + '" fill="' + b.color + '"/>';
                cursor += b.h + (b.after || 0);
                return;
            }
            if (b.kind === 'pill' || b.kind === 'button') {
                const size = b.size;
                const label = b.kind === 'pill' ? String(b.text).toUpperCase() : String(b.text);
                const weight = 700;
                const textW = Math.min(measure(label, size, weight), w - size * 2);
                const padX = b.kind === 'pill' ? size * 0.9 : size * 1.3;
                const h = b.kind === 'pill' ? size * 2.1 : size * 2.7;
                const bw = textW + padX * 2;
                const bx = align === 'middle' ? x + (w - bw) / 2 : x;
                svg += '<rect x="' + bx + '" y="' + cursor + '" width="' + bw + '" height="' + h + '" rx="' + h / 2 + '" fill="' + b.bg + '"' + (b.bgOpacity ? ' fill-opacity="' + b.bgOpacity + '"' : '') + '/>';
                svg += textEl([wrap(label, size, weight, w - padX * 2, 1)[0] || ''], bx + bw / 2, cursor + (h - size * 1.12) / 2, size, {weight, color: b.color, anchor: 'middle', spacing: b.kind === 'pill' ? size * 0.08 : 0});
                cursor += h + (b.after || 0);
                return;
            }
            const weight = b.weight || 400;
            let size = b.size;
            let lines;
            if (b.fit) {
                const fitted = fit(b.text, w, b.maxLines || 3, b.fit[0], b.fit[1], weight);
                size = fitted.size;
                lines = fitted.lines;
            } else {
                lines = wrap(b.text, size, weight, w, b.maxLines);
            }
            svg += textEl(lines, anchorX, cursor, size, {weight, color: b.color, anchor: align === 'middle' ? 'middle' : 'start', lineHeight: b.lineHeight, opacity: b.opacity, spacing: b.spacing});
            cursor += blockHeight(lines, size, b.lineHeight) + (b.after || 0);
        });
        return {svg, height: cursor - y};
    }

    /** Logo image (or monogram), optionally on a white plate for dark backgrounds. */
    function logo(ctx, t, x, y, h, opts) {
        opts = opts || {};
        const plate = opts.plate;
        let svg = '';
        const w = opts.w || h * 2.6;
        if (ctx.logo) {
            if (plate) svg += '<rect x="' + (x - h * 0.16) + '" y="' + (y - h * 0.16) + '" width="' + (w + h * 0.32) + '" height="' + (h * 1.32) + '" rx="' + h * 0.25 + '" fill="#FFFFFF"/>';
            const aspect = opts.align === 'middle' ? 'xMidYMid' : opts.align === 'end' ? 'xMaxYMid' : 'xMinYMid';
            svg += '<image href="' + ctx.logo + '" x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" preserveAspectRatio="' + aspect + ' meet"/>';
            return svg;
        }
        const r = h / 2;
        const cx = opts.align === 'middle' ? x + w / 2 : opts.align === 'end' ? x + w - r : x + r;
        svg += '<circle cx="' + cx + '" cy="' + (y + r) + '" r="' + r + '" fill="' + (opts.monoBg || t.primary) + '"/>';
        svg += textEl([initials(ctx.business.name)], cx, y + r - h * 0.2, h * 0.38, {weight: 800, color: opts.monoInk || t.onPrimary, anchor: 'middle'});
        return svg;
    }

    function photo(dataUrl, x, y, w, h, opts) {
        if (!dataUrl) return '';
        opts = opts || {};
        const id = nextId('clip');
        return '<clipPath id="' + id + '"><rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" rx="' + (opts.r || 0) + '"/></clipPath>'
            + '<image href="' + dataUrl + '" x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" preserveAspectRatio="xMidYMid slice" clip-path="url(#' + id + ')"' + (opts.opacity ? ' opacity="' + opts.opacity + '"' : '') + '/>';
    }

    function gradient(id, from, to, angle) {
        const vertical = angle === 'v';
        return '<linearGradient id="' + id + '" x1="0" y1="0" x2="' + (vertical ? 0 : 1) + '" y2="1"><stop offset="0" stop-color="' + from + '"/><stop offset="1" stop-color="' + to + '"/></linearGradient>';
    }

    function wrapSvg(w, h, body, defs) {
        return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 ' + w + ' ' + h + '" width="' + w + '" height="' + h + '">'
            + '<defs>' + (defs || '') + '</defs>' + body + '</svg>';
    }

    // ---------- Flyers & social ----------
    function renderFlyer(cfg, ctx) {
        const f = cfg.fields || {};
        const fmt = FORMATS[cfg.format] || FORMATS.square;
        const W = fmt.w, H = fmt.h;
        const t = theme(cfg.theme, ctx.kit);
        const s = Math.min(W, H) / 1080;
        const pad = Math.round(Math.min(W, H) * 0.075);
        const wide = W / H > 1.6;
        const g = nextId('g');
        let defs = gradient(g, t.primary, t.dark);
        let body = '';
        const contact = f.contact || [ctx.business.phone, ctx.kit.website].filter(Boolean).join('  ·  ');
        const plate = cfg.logoPlate !== false;

        const contentBlocks = (dark, size) => [
            {kind: 'pill', text: f.eyebrow, size: 22 * size, bg: dark ? '#FFFFFF' : t.primary, bgOpacity: dark ? 0.16 : 0.12, color: dark ? '#FFFFFF' : t.primary, after: 26 * size},
            {text: f.headline, fit: [Math.round(wide ? 96 * size : 112 * size), Math.round(44 * size)], maxLines: wide ? 2 : 4, weight: 800, color: dark ? '#FFFFFF' : t.ink, lineHeight: 1.06, after: 22 * size},
            {text: f.subheadline, size: Math.round(36 * size), weight: 600, color: dark ? '#FFFFFF' : t.primary, maxLines: 2, opacity: dark ? 0.92 : 0, after: 20 * size},
            {text: wide ? '' : f.body, size: Math.round(27 * size), color: dark ? '#E2E8F0' : t.muted, maxLines: 5, lineHeight: 1.45, after: 34 * size},
            {kind: 'button', text: f.cta, size: Math.round(26 * size), bg: dark ? '#FFFFFF' : t.primary, color: dark ? t.dark : t.onPrimary}
        ];

        // Shrink the content until it fits the space available (never overflow the footer).
        const fitContent = (dark, base, x, w, avail, align, filter) => {
            let k = base;
            let result = null;
            for (let i = 0; i < 12; i++) {
                let blocks = contentBlocks(dark, k);
                if (filter) blocks = blocks.map(filter);
                result = stack(blocks, x, 0, w, align);
                if (result.height <= avail) break;
                k *= 0.92;
            }
            return result;
        };

        if (cfg.layout === 'split') {
            const landscape = W > H * 1.15;
            const pw = landscape ? Math.round(W * 0.44) : W;
            const ph = landscape ? H : Math.round(H * 0.42);
            body += '<rect width="' + W + '" height="' + H + '" fill="' + t.light + '"/>';
            body += '<rect width="' + pw + '" height="' + ph + '" fill="url(#' + g + ')"/>';
            body += f.photo ? photo(f.photo, 0, 0, pw, ph) : '<circle cx="' + pw * 0.85 + '" cy="' + ph * 0.15 + '" r="' + ph * 0.45 + '" fill="#FFFFFF" fill-opacity="0.06"/>';
            if (!f.photo) body += logo(ctx, t, pw / 2 - 150 * s, ph / 2 - 60 * s, 120 * s, {plate, w: 300 * s, align: 'middle', monoBg: '#FFFFFF', monoInk: t.primary});
            else body += logo(ctx, t, pad, pad * 0.8, 70 * s, {plate: true});
            const cx = landscape ? pw + pad : pad;
            const cy = landscape ? pad : ph + pad * 0.8;
            const cw = landscape ? W - pw - pad * 2 : W - pad * 2;
            const ch = (landscape ? H : H - ph) - pad * 1.8 - 60 * s;
            const content = fitContent(false, s * (landscape ? 0.85 : 1), cx, cw, ch);
            body += '<g transform="translate(0 ' + (cy + Math.max(0, (ch - content.height) / 2)) + ')">' + content.svg + '</g>';
            body += textEl([contact], landscape ? pw + pad : pad, H - pad * 0.75 - 24 * s, Math.round(24 * s), {weight: 600, color: t.muted});
        } else if (cfg.layout === 'minimal') {
            body += '<rect width="' + W + '" height="' + H + '" fill="#FFFFFF"/>';
            body += '<rect width="' + Math.round(18 * s) + '" height="' + H + '" fill="' + t.primary + '"/>';
            body += '<rect x="' + (W - 260 * s) + '" y="' + (H - 260 * s) + '" width="' + 520 * s + '" height="' + 520 * s + '" rx="' + 260 * s + '" fill="' + t.secondary + '" fill-opacity="0.12"/>';
            body += logo(ctx, t, pad + 10 * s, pad * 0.8, 64 * s, {});
            body += textEl([ctx.business.name], pad + 10 * s + (ctx.logo ? 190 * s : 80 * s), pad * 0.8 + 18 * s, Math.round(24 * s), {weight: 700, color: t.ink});
            const top = pad * 0.8 + 120 * s;
            const free = H - top - pad * 1.6 - 40 * s;
            const content = fitContent(false, s, pad + 10 * s, W - pad * 2 - 10 * s, free);
            body += '<g transform="translate(0 ' + (top + Math.max(0, (free - content.height) / 2)) + ')">' + content.svg + '</g>';
            body += '<rect x="' + (pad + 10 * s) + '" y="' + (H - pad - 50 * s) + '" width="' + 60 * s + '" height="' + 6 * s + '" rx="' + 3 * s + '" fill="' + t.primary + '"/>';
            body += textEl([contact], pad + 10 * s, H - pad - 32 * s, Math.round(24 * s), {weight: 600, color: t.muted});
        } else if (cfg.layout === 'offer') {
            body += '<rect width="' + W + '" height="' + H + '" fill="' + t.dark + '"/>';
            body += '<circle cx="' + W + '" cy="0" r="' + Math.max(W, H) * 0.55 + '" fill="' + t.primary + '" fill-opacity="0.18"/>';
            if (f.photo) body += photo(f.photo, 0, 0, W, H, {opacity: 0.22});
            const portrait = H >= W * 0.95;
            const r = Math.round((portrait ? 170 : 150) * s * (wide ? 0.8 : 1));
            const bx = portrait ? W / 2 : W - pad - r;
            const by = portrait ? pad + 110 * s + r : H / 2;
            const bg = nextId('b');
            defs += gradient(bg, t.primary, t.secondary);
            body += '<circle cx="' + bx + '" cy="' + by + '" r="' + (r + 16 * s) + '" fill="none" stroke="' + t.secondary + '" stroke-width="' + 4 * s + '" stroke-dasharray="' + 10 * s + ' ' + 10 * s + '"/>';
            body += '<circle cx="' + bx + '" cy="' + by + '" r="' + r + '" fill="url(#' + bg + ')"/>';
            const offer = fit(f.offer || 'SALE', r * 1.5, 2, Math.round(r * 0.55), Math.round(r * 0.2), 900);
            body += textEl(offer.lines, bx, by - blockHeight(offer.lines, offer.size, 1.0) / 2, offer.size, {weight: 900, color: contrast(t.primary), anchor: 'middle', lineHeight: 1.0});
            body += logo(ctx, t, pad, pad * 0.8, 64 * s, {plate});
            const cx = pad;
            const cw = portrait ? W - pad * 2 : W - pad * 3 - r * 2;
            const top = portrait ? by + r + 60 * s : pad + 120 * s;
            const free = H - top - pad * 1.5 - 30 * s;
            const content = fitContent(true, s * (portrait ? 0.9 : 0.85), cx, cw, free, portrait ? 'middle' : 'start', (b) => (b && b.kind === 'pill' ? null : b));
            body += '<g transform="translate(0 ' + (top + Math.max(0, (free - content.height) / 2)) + ')">' + content.svg + '</g>';
            body += textEl([contact], portrait ? W / 2 : pad, H - pad * 0.7 - 24 * s, Math.round(24 * s), {weight: 600, color: '#CBD5E1', anchor: portrait ? 'middle' : 'start'});
        } else {
            // bold
            body += '<rect width="' + W + '" height="' + H + '" fill="url(#' + g + ')"/>';
            if (f.photo) {
                const ov = nextId('o');
                defs += '<linearGradient id="' + ov + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + t.dark + '" stop-opacity="0.35"/><stop offset="1" stop-color="' + t.dark + '" stop-opacity="0.92"/></linearGradient>';
                body += photo(f.photo, 0, 0, W, H) + '<rect width="' + W + '" height="' + H + '" fill="url(#' + ov + ')"/>';
            } else {
                body += '<circle cx="' + W * 0.92 + '" cy="' + H * 0.08 + '" r="' + Math.max(W, H) * 0.32 + '" fill="' + t.secondary + '" fill-opacity="0.22"/>';
                body += '<circle cx="' + W * 0.05 + '" cy="' + H * 0.95 + '" r="' + Math.max(W, H) * 0.22 + '" fill="#FFFFFF" fill-opacity="0.06"/>';
            }
            body += logo(ctx, t, pad, pad * 0.8, 64 * s, {plate});
            body += textEl([ctx.business.name], pad + (ctx.logo ? 200 * s : 84 * s), pad * 0.8 + 18 * s, Math.round(24 * s), {weight: 700, color: '#FFFFFF'});
            const top = pad * 0.8 + 120 * s;
            const free = H - top - pad * 1.5 - 40 * s;
            const content = fitContent(true, s * (wide ? 0.8 : 1), pad, W - pad * 2, free);
            body += '<g transform="translate(0 ' + (top + Math.max(0, (free - content.height) / 2)) + ')">' + content.svg + '</g>';
            body += '<rect x="0" y="' + (H - pad * 1.1) + '" width="' + W + '" height="' + pad * 1.1 + '" fill="#000000" fill-opacity="0.22"/>';
            body += textEl([contact], pad, H - pad * 0.55 - 14 * s, Math.round(24 * s), {weight: 600, color: '#FFFFFF'});
        }
        return {svg: wrapSvg(W, H, body, defs), w: W, h: H, label: fmt.label};
    }

    // ---------- Presentation ----------
    function renderSlide(slide, deck, ctx, index, total) {
        const W = 1920, H = 1080, pad = 110;
        const t = theme(deck.theme, ctx.kit);
        const plate = deck.logoPlate !== false;
        const g = nextId('g');
        let defs = gradient(g, t.primary, t.dark);
        let body = '';
        const footerText = deck.footer || ctx.business.name;
        const chrome = (dark) => {
            const c = dark ? '#E2E8F0' : t.muted;
            return '<rect x="' + pad + '" y="' + (H - 78) + '" width="' + (W - pad * 2) + '" height="2" fill="' + c + '" fill-opacity="0.25"/>'
                + textEl([footerText], pad, H - 62, 24, {weight: 600, color: c})
                + textEl([(index + 1) + ' / ' + total], W - pad, H - 62, 24, {weight: 600, color: c, anchor: 'end'});
        };
        const bullets = String(slide.bullets || '').split('\n').map((b) => b.trim()).filter(Boolean).slice(0, 6);
        const layout = slide.layout || 'bullets';

        if (layout === 'title' || layout === 'closing') {
            body += '<rect width="' + W + '" height="' + H + '" fill="url(#' + g + ')"/>';
            if (slide.photo) {
                const ov = nextId('o');
                defs += '<linearGradient id="' + ov + '" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="' + t.dark + '" stop-opacity="0.95"/><stop offset="1" stop-color="' + t.dark + '" stop-opacity="0.45"/></linearGradient>';
                body += photo(slide.photo, 0, 0, W, H) + '<rect width="' + W + '" height="' + H + '" fill="url(#' + ov + ')"/>';
            } else {
                body += '<circle cx="' + W * 0.86 + '" cy="' + H * 0.2 + '" r="420" fill="' + t.secondary + '" fill-opacity="0.2"/>';
                body += '<circle cx="' + W * 0.95 + '" cy="' + H * 0.95 + '" r="260" fill="#FFFFFF" fill-opacity="0.06"/>';
            }
            body += logo(ctx, t, pad, 90, 90, {plate});
            const center = layout === 'closing';
            const blocks = [
                {kind: 'rule', w: 120, h: 10, color: t.secondary, after: 40},
                {text: slide.title, fit: [120, 60], maxLines: 3, weight: 800, color: '#FFFFFF', lineHeight: 1.05, after: 30},
                {text: slide.subtitle, size: 40, weight: 500, color: '#E2E8F0', maxLines: 2, after: 40},
                {text: center ? (slide.body || [ctx.business.phone, ctx.business.email, ctx.kit.website].filter(Boolean).join('   ·   ')) : slide.body, size: 30, color: '#CBD5E1', maxLines: 3, lineHeight: 1.4}
            ];
            const content = stack(blocks, pad, 0, center ? W - pad * 2 : W * 0.62, center ? 'middle' : 'start');
            body += '<g transform="translate(0 ' + Math.max(260, (H - content.height) / 2 + 40) + ')">' + content.svg + '</g>';
        } else if (layout === 'stat') {
            body += '<rect width="' + W + '" height="' + H + '" fill="#FFFFFF"/>';
            body += '<rect width="' + W * 0.42 + '" height="' + H + '" fill="url(#' + g + ')"/>';
            const stat = fit(slide.stat || '100%', W * 0.42 - pad * 2, 1, 230, 80, 900);
            body += textEl(stat.lines, W * 0.21, H / 2 - stat.size * 0.75, stat.size, {weight: 900, color: '#FFFFFF', anchor: 'middle'});
            body += stack([{text: slide.statLabel, size: 36, weight: 600, color: '#E2E8F0', maxLines: 2}], W * 0.03, H / 2 + stat.size * 0.45, W * 0.36, 'middle').svg;
            const content = stack([
                {text: slide.title, fit: [72, 44], maxLines: 3, weight: 800, color: t.ink, lineHeight: 1.1, after: 26},
                {kind: 'rule', w: 90, h: 8, color: t.primary, after: 30},
                {text: slide.body || bullets.join('\n'), size: 32, color: t.muted, maxLines: 9, lineHeight: 1.5}
            ], W * 0.42 + pad * 0.8, 0, W * 0.58 - pad * 1.6);
            body += '<g transform="translate(0 ' + Math.max(120, (H - 90 - content.height) / 2) + ')">' + content.svg + '</g>';
            body += logo(ctx, t, W - pad - 180, 60, 60, {w: 180, align: 'end'});
            body += chrome(false).replace(/x="110"/, 'x="' + (W * 0.42 + pad * 0.8) + '"');
        } else if (layout === 'image') {
            body += '<rect width="' + W + '" height="' + H + '" fill="#FFFFFF"/>';
            body += slide.photo ? photo(slide.photo, W * 0.52, 70, W * 0.48 - 70, H - 140, {r: 28}) : '<rect x="' + W * 0.52 + '" y="70" width="' + (W * 0.48 - 70) + '" height="' + (H - 140) + '" rx="28" fill="url(#' + g + ')"/>'
                + textEl(['Add a photo'], W * 0.76 - 35, H / 2 - 20, 34, {weight: 600, color: '#FFFFFF', anchor: 'middle', opacity: 0.8});
            body += logo(ctx, t, pad, 70, 60, {});
            const content = stack([
                {text: slide.title, fit: [80, 46], maxLines: 3, weight: 800, color: t.ink, lineHeight: 1.08, after: 24},
                {kind: 'rule', w: 90, h: 8, color: t.primary, after: 30},
                {text: slide.body || bullets.join('\n'), size: 32, color: t.muted, maxLines: 10, lineHeight: 1.5}
            ], pad, 0, W * 0.52 - pad * 1.6);
            body += '<g transform="translate(0 ' + Math.max(180, (H - content.height) / 2) + ')">' + content.svg + '</g>';
            body += textEl([(index + 1) + ' / ' + total], pad, H - 62, 24, {weight: 600, color: t.muted});
        } else {
            body += '<rect width="' + W + '" height="' + H + '" fill="#FFFFFF"/>';
            body += '<rect width="16" height="' + H + '" fill="' + t.primary + '"/>';
            body += '<circle cx="' + W + '" cy="0" r="300" fill="' + t.secondary + '" fill-opacity="0.1"/>';
            body += logo(ctx, t, W - pad - 180, 70, 60, {w: 180, align: 'end'});
            const head = stack([
                {text: slide.title, fit: [76, 46], maxLines: 2, weight: 800, color: t.ink, lineHeight: 1.08, after: 14},
                {text: slide.subtitle, size: 34, weight: 500, color: t.primary, maxLines: 1}
            ], pad, 110, W - pad * 2 - 220);
            body += head.svg;
            let y = 110 + head.height + 70;
            const rowGap = Math.min(150, (H - 120 - y) / Math.max(1, bullets.length));
            bullets.forEach((text, i) => {
                const lines = wrap(text, 38, 500, W - pad * 2 - 110, 2);
                body += '<circle cx="' + (pad + 30) + '" cy="' + (y + 28) + '" r="30" fill="' + t.primary + '" fill-opacity="0.12"/>';
                body += textEl([String(i + 1)], pad + 30, y + 13, 30, {weight: 800, color: t.primary, anchor: 'middle'});
                body += textEl(lines, pad + 90, y + 6, 38, {weight: 500, color: t.ink, lineHeight: 1.25});
                y += Math.max(rowGap, blockHeight(lines, 38, 1.25) + 40);
            });
            body += chrome(false);
        }
        return {svg: wrapSvg(W, H, body, defs), w: W, h: H, label: 'Slide ' + (index + 1)};
    }

    // ---------- Letterhead ----------
    function renderLetter(cfg, ctx) {
        const f = cfg.fields || {};
        const W = 1240, H = 1754, pad = 110;
        const t = theme(cfg.theme, ctx.kit);
        const layout = cfg.layout || 'classic';
        const b = ctx.business;
        const g = nextId('g');
        let defs = gradient(g, t.primary, t.dark, 'v');
        let body = '<rect width="' + W + '" height="' + H + '" fill="#FFFFFF"/>';
        const contactLines = [b.address, [b.phone, b.email].filter(Boolean).join('  ·  '), ctx.kit.website].filter(Boolean);
        let left = pad, right = W - pad, top;

        if (layout === 'band') {
            body += '<rect width="300" height="' + H + '" fill="url(#' + g + ')"/>';
            body += logo(ctx, t, 50, 90, 80, {plate: cfg.logoPlate !== false, w: 200, align: 'middle', monoBg: '#FFFFFF', monoInk: t.primary});
            body += stack([
                {text: b.name, size: 26, weight: 800, color: '#FFFFFF', maxLines: 3, after: 10},
                {text: b.tagline, size: 18, color: '#E2E8F0', maxLines: 3, after: 50},
                {text: contactLines.join('\n'), size: 17, color: '#E2E8F0', lineHeight: 1.6, maxLines: 8}
            ], 50, 230, 200).svg;
            left = 380;
            top = 120;
        } else if (layout === 'modern') {
            body += '<rect width="' + W + '" height="230" fill="url(#' + g + ')"/>';
            body += '<rect y="230" width="' + W + '" height="10" fill="' + t.secondary + '"/>';
            body += logo(ctx, t, pad, 70, 90, {plate: cfg.logoPlate !== false});
            body += textEl([b.name], right, 82, 38, {weight: 800, color: '#FFFFFF', anchor: 'end'});
            body += textEl(wrap(b.tagline, 20, 400, 500, 2), right, 136, 20, {color: '#E2E8F0', anchor: 'end'});
            top = 320;
        } else {
            body += logo(ctx, t, pad, 90, 90, {});
            body += textEl([b.name], right, 96, 36, {weight: 800, color: t.ink, anchor: 'end'});
            body += textEl(wrap(b.tagline, 20, 400, 520, 2), right, 146, 20, {color: t.primary, anchor: 'end'});
            body += '<rect x="' + pad + '" y="226" width="' + (W - pad * 2) + '" height="4" fill="' + t.primary + '"/>';
            body += '<rect x="' + pad + '" y="234" width="' + (W - pad * 2) * 0.3 + '" height="4" fill="' + t.secondary + '"/>';
            top = 310;
        }

        const width = right - left;
        const footerY = H - 120;
        let y = top;
        const add = (text, size, weight, color, after, lh) => {
            if (!String(text || '').trim()) return;
            const lines = wrap(text, size, weight, width);
            body += textEl(lines, left, y, size, {weight, color, lineHeight: lh || 1.5});
            y += blockHeight(lines, size, lh || 1.5) + after;
        };
        add(f.date, 22, 400, t.muted, 40);
        add(f.recipient, 22, 400, t.ink, 40);
        add(f.subject ? 'RE: ' + f.subject : '', 24, 700, t.ink, 36);
        const bodyStart = y;
        const bodyLines = wrap(f.body, 22, 400, width);
        const maxBodyLines = Math.floor((footerY - 260 - bodyStart) / (22 * 1.6)) + 1;
        const overflow = bodyLines.length > maxBodyLines;
        const shown = overflow ? bodyLines.slice(0, maxBodyLines) : bodyLines;
        body += textEl(shown, left, y, 22, {color: '#1E293B', lineHeight: 1.6});
        y += blockHeight(shown, 22, 1.6) + 60;
        if (!overflow) {
            add(f.closing || 'Yours faithfully,', 22, 400, t.ink, 70);
            add(f.signName, 24, 700, t.ink, 6, 1.3);
            add(f.signTitle, 20, 400, t.muted, 0, 1.3);
        }

        if (layout !== 'band') {
            body += '<rect x="' + pad + '" y="' + footerY + '" width="' + (W - pad * 2) + '" height="2" fill="' + t.primary + '" fill-opacity="0.35"/>';
            body += textEl([contactLines.join('   ·   ')], W / 2, footerY + 26, 18, {color: t.muted, anchor: 'middle'});
        }
        return {svg: wrapSvg(W, H, body, defs), w: W, h: H, label: 'A4 letter', overflow};
    }

    // ---------- Business card ----------
    function renderCard(cfg, ctx, side) {
        const f = cfg.fields || {};
        const W = 1050, H = 600, pad = 70;
        const t = theme(cfg.theme, ctx.kit);
        const g = nextId('g');
        let defs = gradient(g, t.primary, t.dark);
        let body = '';
        const b = ctx.business;
        if (side === 'front') {
            body += '<rect width="' + W + '" height="' + H + '" fill="url(#' + g + ')"/>';
            body += '<circle cx="' + W + '" cy="' + H + '" r="330" fill="' + t.secondary + '" fill-opacity="0.22"/>';
            body += logo(ctx, t, W / 2 - 170, H / 2 - 120, 130, {plate: cfg.logoPlate !== false, w: 340, align: 'middle', monoBg: '#FFFFFF', monoInk: t.primary});
            body += textEl([b.name], W / 2, H / 2 + 50, 40, {weight: 800, color: '#FFFFFF', anchor: 'middle'});
            body += textEl(wrap(b.tagline, 22, 400, W - pad * 2, 1), W / 2, H / 2 + 110, 22, {color: '#E2E8F0', anchor: 'middle'});
        } else {
            const bold = cfg.layout === 'bold';
            body += '<rect width="' + W + '" height="' + H + '" fill="' + (bold ? t.dark : '#FFFFFF') + '"/>';
            body += '<rect width="' + W + '" height="14" fill="' + t.primary + '"/><rect y="14" width="' + W * 0.35 + '" height="8" fill="' + t.secondary + '"/>';
            const ink = bold ? '#FFFFFF' : t.ink;
            const muted = bold ? '#CBD5E1' : t.muted;
            body += stack([
                {text: f.personName, fit: [52, 34], maxLines: 1, weight: 800, color: ink, after: 8},
                {text: f.personTitle, size: 26, weight: 600, color: bold ? t.secondary : t.primary, maxLines: 1}
            ], pad, 80, W - pad * 2 - 220).svg;
            const rows = [['☎', f.phone || b.phone], ['✉', f.email || b.email], ['⌂', f.address || b.address], ['◎', f.website || ctx.kit.website]].filter((r) => r[1]);
            rows.slice(0, 4).forEach((row, i) => {
                const y = 290 + i * 62;
                body += '<circle cx="' + (pad + 22) + '" cy="' + (y + 15) + '" r="22" fill="' + t.primary + '" fill-opacity="' + (bold ? 0.35 : 0.12) + '"/>';
                body += textEl([row[0]], pad + 22, y + 2, 22, {color: bold ? '#FFFFFF' : t.primary, anchor: 'middle', weight: 700});
                body += textEl(wrap(row[1], 24, 500, W - pad * 2 - 80, 1), pad + 62, y + 2, 24, {weight: 500, color: muted});
            });
            body += logo(ctx, t, W - pad - 170, 80, 80, {w: 170, align: 'end', plate: bold && cfg.logoPlate !== false});
        }
        return {svg: wrapSvg(W, H, body, defs), w: W, h: H, label: side === 'front' ? 'Front' : 'Back'};
    }

    function pages(type, cfg, ctx) {
        if (type === 'powerpoint') {
            const slides = cfg.slides && cfg.slides.length ? cfg.slides : [{layout: 'title', title: 'Untitled'}];
            return slides.map((slide, i) => renderSlide(slide, cfg, ctx, i, slides.length));
        }
        if (type === 'letterhead') return [renderLetter(cfg, ctx)];
        if (type === 'business_card') return [renderCard(cfg, ctx, 'front'), renderCard(cfg, ctx, 'back')];
        return [renderFlyer(cfg, ctx)];
    }

    window.BrandRender = {pages, theme, contrast, FORMATS, FLYER_LAYOUTS, SLIDE_LAYOUTS, LETTER_LAYOUTS, CARD_LAYOUTS, THEMES, FONT, wrap};
})();
