/**
 * BrandExport — downloads for Brand Studio designs.
 *
 *   png / jpg  : any page, full resolution
 *   pdf        : every page (A4 for flyers & letters, 16:9 for decks, 3.5×2 in for cards)
 *   pptx       : editable PowerPoint (native text boxes and shapes via PptxGenJS)
 *   docx       : editable Word letterhead (via docx)
 *
 * Libraries are loaded from pinned CDN versions the first time they are needed.
 */
(function () {
    'use strict';

    const LIBS = {
        jspdf: ['https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', () => window.jspdf],
        pptx: ['https://cdn.jsdelivr.net/npm/pptxgenjs@3.12.0/dist/pptxgen.bundle.js', () => window.PptxGenJS],
        docx: ['https://cdn.jsdelivr.net/npm/docx@8.5.0/build/index.umd.js', () => window.docx]
    };
    const loading = {};

    function loadLib(name) {
        const [url, get] = LIBS[name];
        if (get()) return Promise.resolve(get());
        if (!loading[name]) {
            loading[name] = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = url;
                script.async = true;
                script.onload = () => (get() ? resolve(get()) : reject(new Error('The ' + name + ' library did not load.')));
                script.onerror = () => { delete loading[name]; reject(new Error('Could not download the ' + name + ' export library. Check the internet connection.')); };
                document.head.appendChild(script);
            });
        }
        return loading[name];
    }

    function slug(name) {
        return String(name || 'design').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60) || 'design';
    }

    function save(blob, fileName) {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 4000);
    }

    /** Draw an SVG page onto a canvas at the given scale. */
    function toCanvas(page, scale, background) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(new Blob([page.svg], {type: 'image/svg+xml;charset=utf-8'}));
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(page.w * scale);
                canvas.height = Math.round(page.h * scale);
                const ctx = canvas.getContext('2d');
                if (background) { ctx.fillStyle = background; ctx.fillRect(0, 0, canvas.width, canvas.height); }
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                resolve(canvas);
            };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('The design could not be rendered for export.')); };
            img.src = url;
        });
    }

    // Encode via toDataURL (works everywhere, including where toBlob is unavailable or never resolves).
    function canvasBlob(canvas, type, quality) {
        const dataUrl = canvas.toDataURL(type, quality);
        const binary = atob(dataUrl.split(',')[1]);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
        return Promise.resolve(new Blob([bytes], {type}));
    }

    async function image(pages, index, format, name) {
        const list = index === 'all' ? pages : [pages[index || 0]];
        for (let i = 0; i < list.length; i++) {
            const page = list[i];
            const scale = page.w < 1300 ? 2 : 1.5;
            const canvas = await toCanvas(page, scale, format === 'jpg' ? '#FFFFFF' : null);
            const blob = await canvasBlob(canvas, format === 'jpg' ? 'image/jpeg' : 'image/png', 0.93);
            const suffix = list.length > 1 || pages.length > 1 ? '-' + slug(page.label) : '';
            save(blob, slug(name) + suffix + '.' + format);
            if (list.length > 1) await new Promise((r) => setTimeout(r, 350));
        }
    }

    async function thumbnail(page) {
        const scale = Math.min(1, 480 / Math.max(page.w, page.h));
        const canvas = await toCanvas(page, scale, '#FFFFFF');
        return canvas.toDataURL('image/jpeg', 0.82);
    }

    async function pdf(type, cfg, pages, name) {
        const {jsPDF} = await loadLib('jspdf');
        let doc = null;
        for (const page of pages) {
            let size, orientation;
            if (type === 'letterhead' || (type === 'flyer' && cfg.format === 'a4')) { size = 'a4'; orientation = 'portrait'; }
            else if (type === 'business_card') { size = [252, 144]; orientation = 'landscape'; }
            else if (type === 'powerpoint') { size = [960, 540]; orientation = 'landscape'; }
            else { size = [page.w * 0.75, page.h * 0.75]; orientation = page.w >= page.h ? 'landscape' : 'portrait'; }
            if (!doc) doc = new jsPDF({unit: 'pt', format: size, orientation});
            else doc.addPage(size, orientation);
            const canvas = await toCanvas(page, type === 'business_card' ? 2 : 1.5, '#FFFFFF');
            const w = doc.internal.pageSize.getWidth();
            const h = doc.internal.pageSize.getHeight();
            doc.addImage(canvas.toDataURL('image/jpeg', 0.94), 'JPEG', 0, 0, w, h, undefined, 'FAST');
        }
        save(doc.output('blob'), slug(name) + '.pdf');
    }

    // ---------- PowerPoint (native, editable) ----------
    const hex = (c) => String(c || '#000000').replace('#', '').toUpperCase();

    async function pptx(cfg, ctx, name) {
        const PptxGenJS = await loadLib('pptx');
        const pres = new PptxGenJS();
        pres.layout = 'LAYOUT_WIDE'; // 13.33 × 7.5 in
        pres.author = ctx.business.name;
        pres.company = ctx.business.name;
        pres.title = name;
        const t = BrandRender.theme(cfg.theme, ctx.kit);
        const W = 13.333, H = 7.5, M = 0.75;
        const font = 'Segoe UI';
        const slides = cfg.slides || [];
        const logo = (slide, x, y, w, h, plate) => {
            if (ctx.logo) {
                if (plate) slide.addShape(pres.ShapeType.roundRect, {x: x - 0.08, y: y - 0.08, w: w + 0.16, h: h + 0.16, fill: {color: 'FFFFFF'}, line: {color: 'FFFFFF'}, rectRadius: 0.1});
                slide.addImage({data: ctx.logo, x, y, w, h, sizing: {type: 'contain', w, h}});
            } else {
                slide.addShape(pres.ShapeType.ellipse, {x, y, w: h, h, fill: {color: hex(t.primary)}, line: {color: hex(t.primary)}});
                slide.addText(ctx.business.name.split(/[\s&]+/).filter(Boolean).slice(0, 2).map((w2) => w2[0]).join(''), {x, y, w: h, h, align: 'center', valign: 'middle', fontFace: font, fontSize: 16, bold: true, color: hex(t.onPrimary)});
            }
        };
        const footer = (slide, i, dark) => {
            const color = dark ? 'E2E8F0' : hex(t.muted);
            slide.addShape(pres.ShapeType.line, {x: M, y: H - 0.55, w: W - M * 2, h: 0, line: {color, width: 0.75, transparency: 60}});
            slide.addText(cfg.footer || ctx.business.name, {x: M, y: H - 0.5, w: 8, h: 0.35, fontFace: font, fontSize: 11, color});
            slide.addText((i + 1) + ' / ' + slides.length, {x: W - M - 2, y: H - 0.5, w: 2, h: 0.35, fontFace: font, fontSize: 11, color, align: 'right'});
        };
        const bulletsOf = (s) => String(s.bullets || '').split('\n').map((b) => b.trim()).filter(Boolean).slice(0, 6);

        slides.forEach((s, i) => {
            const slide = pres.addSlide();
            const layout = s.layout || 'bullets';
            if (layout === 'title' || layout === 'closing') {
                slide.background = {color: hex(t.dark)};
                if (s.photo) {
                    slide.addImage({data: s.photo, x: 0, y: 0, w: W, h: H, sizing: {type: 'cover', w: W, h: H}});
                    slide.addShape(pres.ShapeType.rect, {x: 0, y: 0, w: W, h: H, fill: {color: hex(t.dark), transparency: 22}, line: {color: hex(t.dark), transparency: 100}});
                } else {
                    slide.addShape(pres.ShapeType.ellipse, {x: W - 4.6, y: -1.6, w: 5.8, h: 5.8, fill: {color: hex(t.primary), transparency: 35}, line: {color: hex(t.primary), transparency: 100}});
                    slide.addShape(pres.ShapeType.ellipse, {x: W - 2.2, y: H - 2.4, w: 3.6, h: 3.6, fill: {color: hex(t.secondary), transparency: 70}, line: {color: hex(t.secondary), transparency: 100}});
                }
                logo(slide, M, 0.6, 1.6, 0.62, cfg.logoPlate !== false);
                const center = layout === 'closing';
                slide.addShape(pres.ShapeType.rect, {x: center ? W / 2 - 0.45 : M, y: 2.35, w: 0.9, h: 0.08, fill: {color: hex(t.secondary)}, line: {color: hex(t.secondary)}});
                slide.addText(s.title || '', {x: M, y: 2.55, w: center ? W - M * 2 : 8.6, h: 1.8, fontFace: font, fontSize: 48, bold: true, color: 'FFFFFF', align: center ? 'center' : 'left', valign: 'top', fit: 'shrink'});
                if (s.subtitle) slide.addText(s.subtitle, {x: M, y: 4.4, w: center ? W - M * 2 : 8.6, h: 0.7, fontFace: font, fontSize: 22, color: 'E2E8F0', align: center ? 'center' : 'left'});
                const extra = center ? (s.body || [ctx.business.phone, ctx.business.email, ctx.kit.website].filter(Boolean).join('   ·   ')) : s.body;
                if (extra) slide.addText(extra, {x: M, y: 5.2, w: center ? W - M * 2 : 8.6, h: 1, fontFace: font, fontSize: 16, color: 'CBD5E1', align: center ? 'center' : 'left', valign: 'top'});
            } else if (layout === 'stat') {
                slide.background = {color: 'FFFFFF'};
                slide.addShape(pres.ShapeType.rect, {x: 0, y: 0, w: W * 0.42, h: H, fill: {color: hex(t.primary)}, line: {color: hex(t.primary)}});
                slide.addText(s.stat || '', {x: 0.3, y: 2.1, w: W * 0.42 - 0.6, h: 1.8, fontFace: font, fontSize: 96, bold: true, color: hex(t.onPrimary), align: 'center', fit: 'shrink'});
                if (s.statLabel) slide.addText(s.statLabel, {x: 0.3, y: 3.9, w: W * 0.42 - 0.6, h: 0.9, fontFace: font, fontSize: 20, color: hex(t.onPrimary), align: 'center'});
                slide.addText(s.title || '', {x: W * 0.42 + 0.6, y: 1.2, w: W * 0.58 - 1.2, h: 1.4, fontFace: font, fontSize: 34, bold: true, color: hex(t.ink), valign: 'top', fit: 'shrink'});
                slide.addShape(pres.ShapeType.rect, {x: W * 0.42 + 0.6, y: 2.7, w: 0.7, h: 0.07, fill: {color: hex(t.primary)}, line: {color: hex(t.primary)}});
                slide.addText(s.body || bulletsOf(s).join('\n'), {x: W * 0.42 + 0.6, y: 2.95, w: W * 0.58 - 1.2, h: 3.4, fontFace: font, fontSize: 18, color: hex(t.muted), valign: 'top', lineSpacingMultiple: 1.3});
                logo(slide, W - M - 1.3, 0.4, 1.3, 0.45, false);
                footer(slide, i, false);
            } else if (layout === 'image') {
                slide.background = {color: 'FFFFFF'};
                if (s.photo) slide.addImage({data: s.photo, x: W * 0.52, y: 0.5, w: W * 0.48 - 0.5, h: H - 1, sizing: {type: 'cover', w: W * 0.48 - 0.5, h: H - 1}, rounding: false});
                else slide.addShape(pres.ShapeType.roundRect, {x: W * 0.52, y: 0.5, w: W * 0.48 - 0.5, h: H - 1, fill: {color: hex(t.primary)}, line: {color: hex(t.primary)}, rectRadius: 0.2});
                logo(slide, M, 0.5, 1.3, 0.45, false);
                slide.addText(s.title || '', {x: M, y: 1.6, w: W * 0.52 - M - 0.5, h: 1.5, fontFace: font, fontSize: 36, bold: true, color: hex(t.ink), valign: 'top', fit: 'shrink'});
                slide.addShape(pres.ShapeType.rect, {x: M, y: 3.2, w: 0.7, h: 0.07, fill: {color: hex(t.primary)}, line: {color: hex(t.primary)}});
                slide.addText(s.body || bulletsOf(s).join('\n'), {x: M, y: 3.45, w: W * 0.52 - M - 0.5, h: 3.3, fontFace: font, fontSize: 18, color: hex(t.muted), valign: 'top', lineSpacingMultiple: 1.3});
            } else {
                slide.background = {color: 'FFFFFF'};
                slide.addShape(pres.ShapeType.rect, {x: 0, y: 0, w: 0.12, h: H, fill: {color: hex(t.primary)}, line: {color: hex(t.primary)}});
                logo(slide, W - M - 1.3, 0.45, 1.3, 0.45, false);
                slide.addText(s.title || '', {x: M, y: 0.6, w: W - M * 2 - 1.6, h: 1.0, fontFace: font, fontSize: 34, bold: true, color: hex(t.ink), fit: 'shrink'});
                if (s.subtitle) slide.addText(s.subtitle, {x: M, y: 1.55, w: W - M * 2, h: 0.5, fontFace: font, fontSize: 18, color: hex(t.primary)});
                const items = bulletsOf(s);
                if (items.length) {
                    slide.addText(items.map((text) => ({text, options: {bullet: {type: 'number'}, paraSpaceAfter: 10}})), {
                        x: M, y: 2.25, w: W - M * 2, h: H - 3.0, fontFace: font, fontSize: 22, color: hex(t.ink), valign: 'top'
                    });
                }
                footer(slide, i, false);
            }
            if (s.notes) slide.addNotes(s.notes);
        });
        const blob = await pres.write({outputType: 'blob'});
        save(blob, slug(name) + '.pptx');
    }

    // ---------- Word letterhead (editable) ----------
    function dataUrlBytes(dataUrl) {
        const base64 = String(dataUrl).split(',')[1] || '';
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
        return bytes;
    }
    const imageSize = (src) => new Promise((resolve) => { const img = new Image(); img.onload = () => resolve([img.naturalWidth, img.naturalHeight]); img.onerror = () => resolve([300, 100]); img.src = src; });

    async function docx(cfg, ctx, name) {
        const D = await loadLib('docx');
        const t = BrandRender.theme(cfg.theme, ctx.kit);
        const f = cfg.fields || {};
        const b = ctx.business;
        const color = (c) => hex(c);
        const font = 'Segoe UI';
        const headerChildren = [];
        const nameRuns = [new D.TextRun({text: b.name, bold: true, size: 36, color: color(t.ink), font})];
        if (ctx.logo) {
            // Word supports PNG/JPEG; re-encode other formats (e.g. WebP) as PNG.
            let logoData = ctx.logo;
            if (!/^data:image\/(png|jpeg)/.test(logoData)) {
                const page = {svg: '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="200"><image href="' + logoData + '" width="600" height="200" preserveAspectRatio="xMinYMid meet"/></svg>', w: 600, h: 200};
                logoData = (await toCanvas(page, 1, null)).toDataURL('image/png');
            }
            const [w, h] = await imageSize(logoData);
            const height = 56;
            headerChildren.push(new D.Paragraph({children: [new D.ImageRun({data: dataUrlBytes(logoData), transformation: {width: Math.round(height * (w / h)), height}})]}));
        }
        headerChildren.push(new D.Paragraph({children: nameRuns, spacing: {before: 80}}));
        headerChildren.push(new D.Paragraph({
            children: [new D.TextRun({text: b.tagline || '', size: 20, color: color(t.primary), font})],
            border: {bottom: {color: color(t.primary), space: 6, style: D.BorderStyle.SINGLE, size: 12}},
            spacing: {after: 120}
        }));
        const contact = [b.address, [b.phone, b.email].filter(Boolean).join('  ·  '), ctx.kit.website].filter(Boolean).join('   ·   ');
        const para = (text, opts) => new D.Paragraph(Object.assign({children: [new D.TextRun(Object.assign({text, font, size: 22}, (opts && opts.run) || {}))], spacing: {after: 120}}, opts && opts.para));
        const lines = (text) => String(text || '').split('\n');
        const children = [];
        if (f.date) children.push(para(f.date, {run: {color: color(t.muted)}, para: {spacing: {after: 240}}}));
        lines(f.recipient).forEach((line) => children.push(para(line, {para: {spacing: {after: 0}}})));
        if (f.recipient) children.push(new D.Paragraph({children: [], spacing: {after: 240}}));
        if (f.subject) children.push(para('RE: ' + f.subject, {run: {bold: true}, para: {spacing: {after: 240}}}));
        lines(f.body).forEach((line) => children.push(para(line, {para: {spacing: {after: line.trim() ? 160 : 80}, alignment: D.AlignmentType.JUSTIFIED}})));
        children.push(para(f.closing || 'Yours faithfully,', {para: {spacing: {before: 240, after: 600}}}));
        if (f.signName) children.push(para(f.signName, {run: {bold: true}, para: {spacing: {after: 0}}}));
        if (f.signTitle) children.push(para(f.signTitle, {run: {color: color(t.muted)}}));

        const doc = new D.Document({
            creator: b.name,
            title: name,
            styles: {default: {document: {run: {font, size: 22}}}},
            sections: [{
                properties: {page: {margin: {top: 1900, bottom: 1300, left: 1300, right: 1300, header: 500, footer: 500}}},
                headers: {default: new D.Header({children: headerChildren})},
                footers: {default: new D.Footer({children: [new D.Paragraph({
                    alignment: D.AlignmentType.CENTER,
                    border: {top: {color: color(t.primary), space: 6, style: D.BorderStyle.SINGLE, size: 6}},
                    children: [new D.TextRun({text: contact, size: 16, color: color(t.muted), font})]
                })]})},
                children
            }]
        });
        save(await D.Packer.toBlob(doc), slug(name) + '.docx');
    }

    window.BrandExport = {image, pdf, pptx, docx, thumbnail, toCanvas};
})();
