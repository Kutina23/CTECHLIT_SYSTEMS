/**
 * CtlDialog — styled replacements for window.confirm / prompt / alert.
 *
 *   await CtlDialog.confirm({title, message, detail, confirmText, tone: 'danger'|'warning'|'primary', icon})  → true | false
 *   await CtlDialog.prompt({title, message, label, value, placeholder, multiline, maxLength, type, presets, confirmText, icon, validate}) → string | null
 *   await CtlDialog.alert({title, message, tone, icon})
 *
 * Accessible: focus moves into the dialog and is trapped there, Esc cancels,
 * Enter confirms (Ctrl+Enter in multi-line fields), and focus returns to the opener.
 */
(function () {
    'use strict';

    const STYLE_ID = 'ctl-dialog-styles';
    const TONES = {
        primary: {color: '#7C3AED', soft: 'rgba(124, 58, 237, 0.12)', icon: 'fa-circle-question'},
        warning: {color: '#D97706', soft: 'rgba(245, 158, 11, 0.14)', icon: 'fa-triangle-exclamation'},
        danger: {color: '#DC2626', soft: 'rgba(239, 68, 68, 0.12)', icon: 'fa-power-off'},
        success: {color: '#059669', soft: 'rgba(16, 185, 129, 0.12)', icon: 'fa-circle-check'},
        info: {color: '#0891B2', soft: 'rgba(6, 182, 212, 0.12)', icon: 'fa-circle-info'}
    };

    function injectStyles() {
        if (document.getElementById(STYLE_ID)) return;
        const style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = `
.ctl-dlg-backdrop { position: fixed; inset: 0; z-index: 3000; display: grid; place-items: center; padding: 16px;
  background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
  animation: ctl-dlg-fade 0.18s ease both; }
.ctl-dlg-backdrop.closing { animation: ctl-dlg-fade 0.16s ease reverse both; }
.ctl-dlg { position: relative; width: min(440px, 100%); max-height: calc(100vh - 32px); overflow: auto; padding: 26px 24px 20px;
  border-radius: 20px; background: rgba(255, 255, 255, 0.97); border: 1px solid rgba(255, 255, 255, 0.8);
  box-shadow: 0 30px 70px rgba(15, 23, 42, 0.28), 0 0 0 1px rgba(148, 163, 184, 0.15);
  font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #1a1a2e;
  animation: ctl-dlg-pop 0.24s cubic-bezier(0.22, 1, 0.36, 1) both; }
.ctl-dlg-backdrop.closing .ctl-dlg { animation: ctl-dlg-pop 0.16s ease reverse both; }
.ctl-dlg-close { position: absolute; top: 14px; right: 14px; display: grid; place-items: center; width: 32px; height: 32px;
  border: 0; border-radius: 9px; background: transparent; color: #64748B; cursor: pointer; font-size: 15px; }
.ctl-dlg-close:hover { background: rgba(148, 163, 184, 0.15); color: #1a1a2e; }
.ctl-dlg-icon { display: grid; place-items: center; width: 52px; height: 52px; margin-bottom: 14px; border-radius: 16px;
  background: var(--ctl-soft); color: var(--ctl-tone); font-size: 22px; }
.ctl-dlg h2 { margin: 0 32px 6px 0; font-size: 18px; font-weight: 700; line-height: 1.3; }
.ctl-dlg p { margin: 0; font-size: 14px; line-height: 1.55; color: #4a4a5a; }
.ctl-dlg .ctl-dlg-detail { margin-top: 10px; padding: 10px 12px; border-radius: 10px; background: var(--ctl-soft); color: #334155; font-size: 13px; }
.ctl-dlg label { display: block; margin: 16px 0 6px; font-size: 13px; font-weight: 600; color: #334155; }
.ctl-dlg input, .ctl-dlg textarea { width: 100%; box-sizing: border-box; padding: 11px 13px; border: 1px solid rgba(148, 163, 184, 0.5);
  border-radius: 12px; background: #fff; color: #1a1a2e; font: 14px/1.45 'Inter', system-ui, sans-serif; resize: vertical; }
.ctl-dlg input:focus, .ctl-dlg textarea:focus { outline: none; border-color: var(--ctl-tone); box-shadow: 0 0 0 3px var(--ctl-soft); }
.ctl-dlg-count { margin-top: 4px; text-align: right; font-size: 11.5px; color: #94A3B8; }
.ctl-dlg-error { min-height: 16px; margin-top: 6px; font-size: 12.5px; color: #DC2626; }
.ctl-dlg-presets { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.ctl-dlg-presets button { padding: 6px 10px; border: 1px solid rgba(148, 163, 184, 0.4); border-radius: 999px; background: #fff;
  color: #475569; font: 600 12px 'Inter', system-ui, sans-serif; cursor: pointer; }
.ctl-dlg-presets button:hover { border-color: var(--ctl-tone); color: var(--ctl-tone); }
.ctl-dlg-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
.ctl-dlg-actions button { padding: 10px 18px; border-radius: 12px; font: 600 14px 'Inter', system-ui, sans-serif; cursor: pointer; }
.ctl-dlg-cancel { border: 1px solid rgba(148, 163, 184, 0.45); background: #fff; color: #334155; }
.ctl-dlg-cancel:hover { background: #F1F5F9; }
.ctl-dlg-ok { border: 0; background: var(--ctl-tone); color: #fff; box-shadow: 0 8px 18px var(--ctl-soft); }
.ctl-dlg-ok:hover { filter: brightness(1.07); }
.ctl-dlg button:focus-visible, .ctl-dlg-close:focus-visible { outline: 2px solid var(--ctl-tone); outline-offset: 2px; }
@keyframes ctl-dlg-fade { from { opacity: 0; } to { opacity: 1; } }
@keyframes ctl-dlg-pop { from { opacity: 0; transform: translateY(10px) scale(0.97); } to { opacity: 1; transform: none; } }
@media (prefers-reduced-motion: reduce) { .ctl-dlg-backdrop, .ctl-dlg { animation: none !important; } }
@media (max-width: 480px) { .ctl-dlg-actions { flex-direction: column-reverse; } .ctl-dlg-actions button { width: 100%; } }`;
        document.head.appendChild(style);
    }

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text != null) node.textContent = text;
        return node;
    }

    let counter = 0;

    function open(kind, options) {
        injectStyles();
        options = options || {};
        const tone = TONES[options.tone] || TONES.primary;
        const opener = document.activeElement;
        const id = 'ctl-dlg-' + (++counter);

        return new Promise(function (resolve) {
            const backdrop = el('div', 'ctl-dlg-backdrop');
            const dialog = el('div', 'ctl-dlg');
            dialog.setAttribute('role', kind === 'alert' ? 'alertdialog' : 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-labelledby', id + '-title');
            dialog.style.setProperty('--ctl-tone', tone.color);
            dialog.style.setProperty('--ctl-soft', tone.soft);

            const close = el('button', 'ctl-dlg-close');
            close.type = 'button';
            close.setAttribute('aria-label', 'Close');
            close.innerHTML = '<i class="fas fa-xmark" aria-hidden="true"></i>';

            const icon = el('div', 'ctl-dlg-icon');
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = '<i class="fas ' + (options.icon || tone.icon) + '"></i>';

            const title = el('h2', null, options.title || (kind === 'prompt' ? 'Enter a value' : 'Are you sure?'));
            title.id = id + '-title';
            dialog.append(close, icon, title);

            if (options.message) {
                const message = el('p', null, options.message);
                message.id = id + '-desc';
                dialog.setAttribute('aria-describedby', message.id);
                dialog.appendChild(message);
            }
            if (options.detail) dialog.appendChild(el('div', 'ctl-dlg-detail', options.detail));

            let field = null;
            let errorLine = null;
            if (kind === 'prompt') {
                const label = el('label', null, options.label || 'Value');
                label.htmlFor = id + '-field';
                field = document.createElement(options.multiline ? 'textarea' : 'input');
                field.id = id + '-field';
                if (options.multiline) field.rows = options.rows || 3;
                else field.type = options.type || 'text';
                if (options.placeholder) field.placeholder = options.placeholder;
                if (options.maxLength) field.maxLength = options.maxLength;
                if (options.min != null) field.min = options.min;
                if (options.inputMode) field.inputMode = options.inputMode;
                field.value = options.value != null ? String(options.value) : '';
                field.autocomplete = 'off';
                dialog.append(label, field);

                if (options.maxLength) {
                    const count = el('div', 'ctl-dlg-count');
                    const update = function () { count.textContent = field.value.length + ' / ' + options.maxLength; };
                    field.addEventListener('input', update);
                    update();
                    dialog.appendChild(count);
                }
                if (Array.isArray(options.presets) && options.presets.length) {
                    const presets = el('div', 'ctl-dlg-presets');
                    presets.setAttribute('role', 'group');
                    presets.setAttribute('aria-label', 'Quick choices');
                    options.presets.forEach(function (preset) {
                        const chip = el('button', null, typeof preset === 'string' ? preset : preset.label);
                        chip.type = 'button';
                        chip.addEventListener('click', function () {
                            field.value = typeof preset === 'string' ? preset : preset.value;
                            field.dispatchEvent(new Event('input'));
                            field.focus();
                        });
                        presets.appendChild(chip);
                    });
                    dialog.appendChild(presets);
                }
                errorLine = el('div', 'ctl-dlg-error');
                errorLine.setAttribute('aria-live', 'polite');
                dialog.appendChild(errorLine);
            }

            const actions = el('div', 'ctl-dlg-actions');
            let cancel = null;
            if (kind !== 'alert') {
                cancel = el('button', 'ctl-dlg-cancel', options.cancelText || 'Cancel');
                cancel.type = 'button';
                actions.appendChild(cancel);
            }
            const ok = el('button', 'ctl-dlg-ok', options.confirmText || (kind === 'alert' ? 'OK' : kind === 'prompt' ? 'Send' : 'Confirm'));
            ok.type = 'button';
            actions.appendChild(ok);
            dialog.appendChild(actions);
            backdrop.appendChild(dialog);
            document.body.appendChild(backdrop);
            const previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';

            let done = false;
            function finish(value) {
                if (done) return;
                done = true;
                document.removeEventListener('keydown', onKey, true);
                backdrop.classList.add('closing');
                window.setTimeout(function () {
                    backdrop.remove();
                    document.body.style.overflow = previousOverflow;
                    if (opener && typeof opener.focus === 'function') opener.focus();
                }, 150);
                resolve(value);
            }

            function submit() {
                if (kind === 'confirm') return finish(true);
                if (kind === 'alert') return finish(undefined);
                const value = field.value.trim();
                const problem = options.required !== false && value === '' ? 'Please enter a value.'
                    : (typeof options.validate === 'function' ? options.validate(value) : '');
                if (problem) {
                    errorLine.textContent = problem;
                    field.setAttribute('aria-invalid', 'true');
                    field.focus();
                    return;
                }
                finish(value);
            }

            function cancelled() { finish(kind === 'confirm' ? false : kind === 'prompt' ? null : undefined); }

            function onKey(event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    cancelled();
                } else if (event.key === 'Enter' && event.target !== cancel && event.target !== close
                    && !(event.target.tagName === 'BUTTON' && event.target.closest('.ctl-dlg-presets'))) {
                    if (event.target.tagName === 'TEXTAREA' && !(event.ctrlKey || event.metaKey)) return;
                    event.preventDefault();
                    submit();
                } else if (event.key === 'Tab') {
                    const focusable = Array.from(dialog.querySelectorAll('button, input, textarea')).filter(function (n) { return !n.disabled; });
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                }
            }

            document.addEventListener('keydown', onKey, true);
            ok.addEventListener('click', submit);
            close.addEventListener('click', cancelled);
            if (cancel) cancel.addEventListener('click', cancelled);
            backdrop.addEventListener('mousedown', function (event) { if (event.target === backdrop) cancelled(); });

            window.requestAnimationFrame(function () {
                if (field) { field.focus(); field.select(); }
                else (options.tone === 'danger' && cancel ? cancel : ok).focus();
            });
        });
    }

    const MESSAGE_PRESETS = [
        'We close in 10 minutes. Please save your work.',
        'Your session ends in 5 minutes.',
        'Your print job is ready at the front desk.',
        'Please keep the noise down. Thank you!'
    ];

    /**
     * Ask before sending a command to every station. Resolves {ok, text}.
     * stationCount: how many stations will be affected (optional, shown to the user).
     */
    async function bulkStations(command, stationCount) {
        const affected = typeof stationCount === 'number'
            ? stationCount + ' station' + (stationCount === 1 ? '' : 's') + ' will be affected (stations in maintenance are skipped).'
            : 'Stations in maintenance are skipped.';
        if (command === 'message') {
            const text = await open('prompt', {
                title: 'Message every station',
                message: 'Shown as a pop-up on each station’s screen.',
                detail: affected,
                label: 'Message',
                value: MESSAGE_PRESETS[0],
                multiline: true,
                maxLength: 250,
                presets: MESSAGE_PRESETS,
                icon: 'fa-message',
                tone: 'primary',
                confirmText: 'Send to all'
            });
            return {ok: text !== null, text: text || ''};
        }
        if (command === 'lock') {
            const ok = await open('confirm', {
                title: 'Lock all stations?',
                message: 'Every station screen will lock. Customers sign back in to continue — their sessions and time are not affected.',
                detail: affected,
                icon: 'fa-lock',
                tone: 'warning',
                confirmText: 'Lock all'
            });
            return {ok, text: ''};
        }
        if (command === 'shutdown') {
            const ok = await open('confirm', {
                title: 'Shut down all stations?',
                message: 'Every station will shut down after a 5-second warning. Any unsaved work on them will be lost.',
                detail: affected,
                icon: 'fa-plug-circle-xmark',
                tone: 'danger',
                confirmText: 'Shut down all'
            });
            return {ok, text: ''};
        }
        return {ok: true, text: ''};
    }

    /**
     * Ask before a single-device command. Resolves {ok, text}.
     */
    async function deviceCommand(command, deviceName) {
        const name = deviceName || 'this device';
        const presets = {
            logoff: ['Sign the user out of ' + name + '?', 'The current user is signed out and any unsaved work is lost.', 'fa-right-from-bracket', 'warning', 'Sign out'],
            sleep: ['Put ' + name + ' to sleep?', 'The station sleeps after a short delay. Use Power on to wake it.', 'fa-moon', 'warning', 'Sleep'],
            restart: ['Restart ' + name + '?', 'It restarts after a 5-second warning. Unsaved work will be lost.', 'fa-rotate-right', 'danger', 'Restart'],
            shutdown: ['Shut down ' + name + '?', 'It shuts down after a 5-second warning. Unsaved work will be lost.', 'fa-plug-circle-xmark', 'danger', 'Shut down'],
            block: ['Block ' + name + '?', 'It is disconnected and cannot get online until unblocked. Used time is charged first.', 'fa-ban', 'danger', 'Block'],
            disconnect: ['Disconnect ' + name + '?', 'Remaining paid time is kept for next time.', 'fa-power-off', 'warning', 'Disconnect']
        };
        if (command === 'message') {
            const text = await open('prompt', {
                title: 'Message ' + name, message: 'Shown as a pop-up on the station’s screen.', label: 'Message',
                value: MESSAGE_PRESETS[1], multiline: true, maxLength: 250, presets: MESSAGE_PRESETS,
                icon: 'fa-message', confirmText: 'Send message'
            });
            return {ok: text !== null, text: text || ''};
        }
        const preset = presets[command];
        if (!preset) return {ok: true, text: ''};
        const ok = await open('confirm', {title: preset[0], message: preset[1], icon: preset[2], tone: preset[3], confirmText: preset[4]});
        return {ok, text: ''};
    }

    window.CtlDialog = {
        confirm: function (options) { return open('confirm', options); },
        prompt: function (options) { return open('prompt', options); },
        alert: function (options) { return open('alert', typeof options === 'string' ? {message: options} : options); },
        bulkStations: bulkStations,
        deviceCommand: deviceCommand
    };
})();
