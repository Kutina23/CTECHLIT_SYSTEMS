(() => {
    'use strict';

    const nativeFetch = window.fetch;
    const buttonStates = new WeakMap();
    let clickedButton = null;
    let clickedButtonTimer = 0;

    function restoreButton(button, state) {
        if (!button.isConnected || buttonStates.get(button) !== state) return;

        window.clearTimeout(state.releaseTimer);
        window.clearTimeout(state.safetyTimer);
        button.innerHTML = state.html;
        button.disabled = state.disabled;
        if (state.ariaBusy === null) {
            button.removeAttribute('aria-busy');
        } else {
            button.setAttribute('aria-busy', state.ariaBusy);
        }
        if (state.ariaLabel === null) {
            button.removeAttribute('aria-label');
        } else {
            button.setAttribute('aria-label', state.ariaLabel);
        }
        buttonStates.delete(button);
    }

    function showLoading(button) {
        if (!button || button.disabled || button.hasAttribute('data-no-loading')) return null;

        let state = buttonStates.get(button);
        if (state) {
            window.clearTimeout(state.releaseTimer);
            return state;
        }

        const label = button.dataset.loadingText || 'Loading';
        state = {
            html: button.innerHTML,
            disabled: button.disabled,
            ariaBusy: button.getAttribute('aria-busy'),
            ariaLabel: button.getAttribute('aria-label'),
            requestCount: 0,
            releaseTimer: 0,
            safetyTimer: 0,
        };
        buttonStates.set(button, state);
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.setAttribute('aria-label', `${button.getAttribute('aria-label') || button.textContent.trim() || 'Action'} in progress`);
        button.innerHTML = `<span class="button-loading-spinner" aria-hidden="true"></span><span class="button-loading-label">${label}</span>`;
        state.safetyTimer = window.setTimeout(() => restoreButton(button, state), 30000);
        return state;
    }

    function trackRequest(button, request) {
        const state = showLoading(button);
        if (!state) return request;

        state.requestCount += 1;
        request.then(
            () => finishRequest(button, state),
            () => finishRequest(button, state)
        );
        return request;
    }

    function finishRequest(button, state) {
        state.requestCount = Math.max(0, state.requestCount - 1);
        if (state.requestCount === 0) {
            state.releaseTimer = window.setTimeout(() => restoreButton(button, state), 160);
        }
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('button, input[type="submit"], input[type="button"]');
        clickedButton = button && !button.disabled && !button.hasAttribute('data-no-loading') ? button : null;
        window.clearTimeout(clickedButtonTimer);
        clickedButtonTimer = window.setTimeout(() => {
            clickedButton = null;
        }, 0);
    }, true);

    document.addEventListener('submit', event => {
        const form = event.target;
        const target = (form.getAttribute('target') || '').toLowerCase();
        if (target && target !== '_self') return;

        const activeElement = document.activeElement;
        const submitter = event.submitter || (
            activeElement && form.contains(activeElement) && activeElement.matches('button, input[type="submit"]')
                ? activeElement
                : form.querySelector('button:not([type]), button[type="submit"], input[type="submit"]')
        );
        if (submitter && submitter.matches('button, input[type="submit"]')) {
            showLoading(submitter);
        }
    }, true);

    if (typeof nativeFetch === 'function') {
        window.fetch = function(...args) {
            const button = clickedButton;
            clickedButton = null;

            let request;
            try {
                request = nativeFetch.apply(this, args);
            } catch (error) {
                if (button) {
                    const state = buttonStates.get(button);
                    if (state) restoreButton(button, state);
                }
                throw error;
            }

            return button ? trackRequest(button, request) : request;
        };
    }

    if (window.XMLHttpRequest) {
        const nativeSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function(...args) {
            const button = clickedButton;
            clickedButton = null;
            if (!button) return nativeSend.apply(this, args);

            const state = showLoading(button);
            if (!state) return nativeSend.apply(this, args);

            state.requestCount += 1;
            this.addEventListener('loadend', () => finishRequest(button, state), {once: true});
            try {
                return nativeSend.apply(this, args);
            } catch (error) {
                finishRequest(button, state);
                throw error;
            }
        };
    }
})();
