/**
 * Modal Controller for CRUD Operations
 * Handles opening, closing, form submission, and API interactions
 */

window.ModalController = (function() {
    'use strict';

    // State
    let currentEntity = null;
    let currentEntityId = null;
    let currentMode = 'create'; // 'create' | 'edit' | 'view'
    let callbacks = {};
    let viewRequestId = 0;
    let currentInvoiceTemplate = 'classic';

    // DOM Elements
    const overlays = {
        main: document.getElementById('modalOverlay'),
        confirm: document.getElementById('confirmModalOverlay'),
        view: document.getElementById('viewModalOverlay'),
    };

    const modals = {
        main: document.getElementById('modal'),
        confirm: document.getElementById('confirmModal'),
        view: document.getElementById('viewModal'),
    };

    const elements = {
        title: document.getElementById('modalTitle'),
        body: document.getElementById('modalBody'),
        footer: document.getElementById('modalFooter'),
        cancel: document.getElementById('modalCancel'),
        confirm: document.getElementById('modalConfirm'),
        close: document.getElementById('modalClose'),
        
        confirmTitle: document.getElementById('confirmModalTitle'),
        confirmIcon: document.getElementById('confirmModalIcon'),
        confirmMessage: document.getElementById('confirmModalMessage'),
        confirmDetail: document.getElementById('confirmModalDetail'),
        confirmCancel: document.getElementById('confirmModalCancel'),
        confirmConfirm: document.getElementById('confirmModalConfirm'),
        confirmClose: document.getElementById('confirmModalClose'),
        
        viewTitle: document.getElementById('viewModalTitle'),
        viewBody: document.getElementById('viewModalBody'),
        viewClose: document.getElementById('viewModalClose'),
        viewCloseBtn: document.getElementById('viewModalCloseBtn'),
    };

    const focusBeforeOverlay = new WeakMap();
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function getActiveOverlay() {
        const activeOverlays = document.querySelectorAll('.modal-overlay.active');
        return activeOverlays[activeOverlays.length - 1] || null;
    }

    function getFocusableElements(overlay) {
        return Array.from(overlay.querySelectorAll(focusableSelector))
            .filter(element => element.getClientRects().length > 0);
    }

    function watchOverlayState() {
        Object.values(overlays).forEach(overlay => {
            if (!overlay) return;
            overlay.setAttribute('aria-hidden', 'true');
            overlay.inert = true;
        });

        const observer = new MutationObserver(records => {
            records.forEach(record => {
                const overlay = record.target;
                const isOpen = overlay.classList.contains('active');
                const wasOpen = overlay.getAttribute('aria-hidden') === 'false';
                if (isOpen === wasOpen) return;

                overlay.setAttribute('aria-hidden', String(!isOpen));
                overlay.inert = !isOpen;

                if (isOpen) {
                    focusBeforeOverlay.set(overlay, document.activeElement);
                    requestAnimationFrame(() => {
                        if (!overlay.classList.contains('active')) return;
                        const target = getFocusableElements(overlay)[0] || overlay.querySelector('.modal');
                        if (target) target.focus();
                    });
                } else {
                    const previousFocus = focusBeforeOverlay.get(overlay);
                    if (previousFocus && previousFocus.isConnected) previousFocus.focus();
                }
            });
        });

        Object.values(overlays).forEach(overlay => {
            if (overlay) observer.observe(overlay, { attributes: true, attributeFilter: ['class'] });
        });
    }

    // Utility: Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeAttribute(text) {
        return escapeHtml(text).replace(/'/g, '&#39;');
    }

    function getDetailLabel(key, config) {
        const field = config?.fields?.find(item => item.name === key);
        if (field) return field.label;
        return String(key)
            .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
            .replace(/[_-]+/g, ' ')
            .replace(/\b\w/g, character => character.toUpperCase());
    }

    function formatDetailValue(value, field, key) {
        if (value === null || value === undefined || value === '') return '<span class="detail-empty">—</span>';
        if (
            field?.type === 'checkbox' ||
            typeof value === 'boolean' ||
            (/^(is_|has_|requires_)/i.test(key) && (value === 0 || value === 1 || value === '0' || value === '1'))
        ) {
            return value === true || value === 1 || value === '1' ? 'Yes' : 'No';
        }
        if (field?.type === 'select') {
            const option = field.options?.find(item => String(item.value) === String(value));
            if (option) return escapeHtml(option.label);
        }
        if (typeof value === 'object') {
            return `<pre class="detail-json">${escapeHtml(JSON.stringify(value, null, 2))}</pre>`;
        }
        return escapeHtml(value);
    }

    function renderDetailView(entity, data) {
        const config = window.MODAL_CONFIGS?.[entity];
        const fieldMap = new Map((config?.fields || []).map(field => [field.name, field]));
        const hiddenField = /password|secret|token|credential|auth.?key/i;
        const entries = Object.entries(data || {}).filter(([key]) => !hiddenField.test(key));

        if (!entries.length) {
            return '<div class="detail-empty-state">No record details are available.</div>';
        }

        return `<dl class="detail-view">${entries.map(([key, value]) => {
            const field = fieldMap.get(key);
            return `<div class="detail-row">
                <dt class="detail-label">${escapeHtml(getDetailLabel(key, config))}</dt>
                <dd class="detail-value">${formatDetailValue(value, field, key)}</dd>
            </div>`;
        }).join('')}</dl>`;
    }

    async function fetchFullRecord(entity, data) {
        const config = window.MODAL_CONFIGS?.[entity];
        if (!config?.api || !config?.idField) return data;

        const recordId = data?.[config.idField] ?? data?.id;
        if (recordId === null || recordId === undefined || recordId === '') return data;

        const url = new URL(config.api, window.location.href);
        url.searchParams.set(config.detailParam || config.idField, String(recordId));
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
        });
        const result = await response.json();
        if (!response.ok || result?.success === false) {
            throw new Error(result?.message || `Unable to load ${config.title.toLowerCase()} details.`);
        }
        if (!result?.data || typeof result.data !== 'object' || Array.isArray(result.data)) {
            throw new Error(`The ${config.title.toLowerCase()} details response was invalid.`);
        }
        return {...data, ...result.data};
    }

    // Utility: Show notification
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 14px 20px;
            border-radius: var(--radius);
            background: var(--panel);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            z-index: 2000;
            animation: slideIn 0.3s ease-out;
            max-width: 350px;
        `;
        
        const colors = {
            success: 'var(--green)',
            error: 'var(--red)',
            warning: 'var(--amber)',
            info: 'var(--cyan)'
        };
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas ${icons[type]}" style="color: ${colors[type]}; font-size: 18px;"></i>
                <span style="color: var(--ink); font-size: 13px; font-weight: 500;">${escapeHtml(message)}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideIn 0.3s ease-out reverse';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    // Generate form HTML from config
    function generateFormHtml(config, data = {}) {
        let html = '<form class="modal-form" id="modalForm" novalidate>';
        
        config.fields.forEach(field => {
            const value = data[field.name] ?? '';
            const isRequired = field.required || (field.requiredOnCreate && currentMode !== 'edit');
            const required = isRequired ? 'required' : '';
            const placeholder = field.placeholder ? `placeholder="${escapeHtml(field.placeholder)}"` : '';
            
            html += `<div class="form-group${field.fullWidth ? ' full-width' : ''}">`;
            html += `<label class="form-label" for="${field.name}">${escapeHtml(field.label)}${isRequired ? ' <span style="color: var(--red);">*</span>' : ''}</label>`;
            
            switch (field.type) {
                case 'text':
                case 'email':
                case 'tel':
                case 'password':
                case 'number':
                case 'date':
                    const step = field.step ? `step="${field.step}"` : '';
                    const min = field.min !== undefined ? `min="${field.min}"` : '';
                    html += `<input type="${field.type}" class="form-input" id="${field.name}" name="${field.name}" value="${escapeHtml(value)}" ${required} ${placeholder} ${step} ${min}>`;
                    break;
                    
                case 'textarea':
                    const textareaValue = typeof value === 'object' && value !== null
                        ? JSON.stringify(value, null, 2)
                        : value;
                    html += `<textarea class="form-textarea form-input" id="${field.name}" name="${field.name}" ${required} ${placeholder}>${escapeHtml(textareaValue)}</textarea>`;
                    break;

                case 'file':
                    html += `<input type="file" class="form-input" id="${field.name}" name="${field.name}" ${required} ${field.accept ? `accept="${escapeHtml(field.accept)}"` : ''}>`;
                    if (data.file_name) {
                        html += `<p class="form-help">Current file: ${escapeHtml(data.file_name)}. Select a new file only if you want to replace it.</p>`;
                    }
                    break;
                    
                case 'select':
                    html += `<select class="form-select form-input" id="${field.name}" name="${field.name}" ${required}>`;
                    if (!field.required) {
                        html += '<option value="">Select...</option>';
                    }
                    if (field.options && field.options.length) {
                        field.options.forEach(opt => {
                            const selected = value == opt.value ? 'selected' : '';
                            const extraAttrs = Object.entries(opt)
                                .filter(([key]) => key.startsWith('data_'))
                                .map(([key, val]) => `data-${key.substring(5)}="${escapeHtml(String(val))}"`)
                                .join(' ');
                            html += `<option value="${escapeHtml(opt.value)}" ${selected}${extraAttrs ? ' ' + extraAttrs : ''}>${escapeHtml(opt.label)}</option>`;
                        });
                    } else if (field.ajax) {
                        html += '<option value="" disabled>Loading...</option>';
                    }
                    html += '</select>';
                    break;
                    
                case 'checkbox':
                    const checked = value ? 'checked' : '';
                    html += `<label class="form-checkbox">
                        <input type="checkbox" name="${field.name}" value="1" ${checked}>
                        <span>${escapeHtml(field.label)}</span>
                    </label>`;
                    html = html.replace(`<label class="form-label" for="${field.name}">${escapeHtml(field.label)}${field.required ? ' <span style="color: var(--red);">*</span>' : ''}</label>`, '');
                    break;
            }
            
            if (field.help) {
                html += `<p class="form-help">${escapeHtml(field.help)}</p>`;
            }
            
            html += `<span class="form-error" id="${field.name}Error"></span>`;
            html += '</div>';
        });
        
        html += '</form>';
        return html;
    }

    // Load select options via AJAX
    async function loadSelectOptions(selectElement, url) {
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const data = await response.json();
            
            const currentValue = selectElement.value;
            const firstOption = selectElement.querySelector('option');
            selectElement.innerHTML = '';
            
            if (firstOption && !firstOption.value) {
                selectElement.appendChild(firstOption);
            }
            
            if (data.data && Array.isArray(data.data)) {
                data.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id || item.value;
                    option.textContent = item.name || item.label || item.station_code || item.email || item.full_name;
                    if (option.value == currentValue) option.selected = true;
                    selectElement.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load select options:', error);
            selectElement.innerHTML = '<option value="" disabled>Failed to load options</option>';
        }
    }

    // Validate form
    function validateForm(form) {
        let isValid = true;
        const formData = new FormData(form);
        
        form.querySelectorAll('.form-input, .form-select, .form-textarea').forEach(input => {
            const errorEl = document.getElementById(`${input.id}Error`);
            if (errorEl) errorEl.textContent = '';
            input.classList.remove('error');
        });
        
        form.querySelectorAll('[required]').forEach(input => {
            const value = formData.get(input.name);
            if (!value || (input.type === 'checkbox' && !input.checked) || (input.type === 'file' && !input.files.length)) {
                input.classList.add('error');
                const errorEl = document.getElementById(`${input.id}Error`);
                if (errorEl) errorEl.textContent = 'This field is required';
                isValid = false;
            }
        });
        
        return isValid;
    }

    // Collect form data
    function collectFormData(form) {
        const formData = new FormData(form);
        const data = {};
        
        formData.forEach((value, key) => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input && input.type === 'checkbox') {
                data[key] = input.checked ? 1 : 0;
            } else if (input && input.type === 'file') {
                if (input.files.length) data[key] = input.files[0];
            } else {
                data[key] = value;
            }
        });

        form.querySelectorAll('input[type="checkbox"][name]').forEach(input => {
            if (!input.checked) data[input.name] = 0;
        });
        
        return data;
    }

    // Submit form via AJAX
    async function submitForm(config, data, isEdit) {
        const url = config.api;
        const method = isEdit ? 'PUT' : 'POST';
        
        if (isEdit && currentEntityId) {
            data[config.idField] = currentEntityId;
        }
        
        const headers = {'X-Requested-With': 'XMLHttpRequest'};
        let body;
        let requestMethod = method;
        if (data.file instanceof File) {
            body = new FormData();
            Object.entries(data).forEach(([key, value]) => {
                if (value instanceof File) {
                    body.append(key, value);
                } else {
                    body.append(key, String(value));
                }
            });
            if (isEdit) {
                body.append('_method', 'PUT');
                requestMethod = 'POST';
            }
        } else {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(data);
        }
        const response = await fetch(url, {method: requestMethod, headers, body, credentials: 'same-origin'});
        
        const result = await response.json();
        
        if (!response.ok || result?.success === false) {
            throw new Error(result.message || 'Request failed');
        }
        
        return result;
    }

    function formatInvoiceCurrency(value) {
        const normalized = typeof value === 'string'
            ? value.replace(/[^0-9.-]/g, '')
            : value;
        const amount = Number(normalized || 0);
        return `GHS ${amount.toLocaleString('en-GH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;
    }

    function formatInvoiceDate(value) {
        if (!value) return '—';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    }

    function getLineItemRows(items) {
        const lineItems = Array.isArray(items) ? items : [];
        if (!lineItems.length) {
            return `<div style="display: grid; grid-template-columns: 1.5fr .5fr 1fr; gap: 12px; padding: 18px; font-size: 14px; color: #374151;">
                <div>Transaction total</div>
                <div style="text-align: right;">—</div>
                <div style="text-align: right; font-weight: 700;">—</div>
            </div>`;
        }
        return lineItems.map(item => `
            <div style="display: grid; grid-template-columns: 1.5fr .5fr 1fr; gap: 12px; padding: 14px 18px; font-size: 14px; color: #374151; border-bottom: 1px solid #f1f5f9;">
                <div>${escapeHtml(item.service_name || item.description || 'Service')}</div>
                <div style="text-align: right;">${escapeHtml(item.quantity ?? 0)}</div>
                <div style="text-align: right; font-weight: 700;">${escapeHtml(formatInvoiceCurrency(item.total_price ?? item.unit_price ?? 0))}</div>
            </div>`).join('');
    }

    function getEmptyLineItemRows() {
        return Array.from({length: 5}, () => `
            <div style="display: grid; grid-template-columns: 1.5fr .5fr 1fr; gap: 12px; padding: 14px 18px; font-size: 14px; color: #94a3b8; border-bottom: 1px dashed #e2e8f0;">
                <div>&nbsp;</div>
                <div style="text-align: right;">&nbsp;</div>
                <div style="text-align: right;">&nbsp;</div>
            </div>`).join('');
    }

    // Template renderers
    function renderClassicTemplate(data) {
        const invoiceNumber = data.invoice_number || 'INV-0000';
        const customerName = data.customer_name || 'Walk-in Customer';
        const amount = Number(data.total_amount || 0);
        const status = String(data.status || 'pending');
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        const paidLabel = status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? 'Paid' : statusLabel;
        const reference = data.transaction_id ? `#${data.transaction_id}` : '—';
        const invoiceData = escapeAttribute(JSON.stringify(data));
        const invoiceRows = getLineItemRows(data.items);

        return `
            <div class="invoicePrintContainer" data-invoice='${invoiceData}' data-template="classic" style="display: grid; gap: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 1px solid #e6eaf2; padding-bottom: 18px;">
                    <div>
                        <div style="font-size: 28px; font-weight: 800; letter-spacing: 0.04em; color: #1f2937;">CTECHLIT & SYSTEMS</div>
                        <div style="font-size: 12px; color: #5f6c85; margin-top: 4px;">Digital Services & Internet Café</div>
                        <div style="font-size: 12px; color: #5f6c85; margin-top: 4px;">support@ctechlit.com • +233 20 000 0000</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="display: inline-flex; align-items: center; justify-content: center; min-width: 110px; padding: 8px 12px; border-radius: 999px; background: rgba(124,58,237,0.12); color: #4f46e5; font-weight: 700; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">
                            Invoice
                        </div>
                        <div style="font-size: 12px; color: #5f6c85; margin-top: 10px;">${escapeHtml(invoiceNumber)}</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(2, minmax(180px, 1fr)); gap: 18px;">
                    <div style="background: #f8fafc; border: 1px solid #e6eaf2; border-radius: 14px; padding: 16px;">
                        <div style="font-size: 11px; color: #6b7280; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 700; margin-bottom: 8px;">Billed To</div>
                        <div style="font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 6px;">${escapeHtml(customerName)}</div>
                        <div style="font-size: 13px; color: #4b5563;">Customer record</div>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e6eaf2; border-radius: 14px; padding: 16px;">
                        <div style="font-size: 11px; color: #6b7280; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 700; margin-bottom: 8px;">Invoice Details</div>
                        <div style="display: grid; gap: 8px; font-size: 13px; color: #374151;">
                            <div style="display: flex; justify-content: space-between; gap: 12px;"><span>Issue Date</span><strong>${escapeHtml(formatInvoiceDate(data.created_at))}</strong></div>
                            <div style="display: flex; justify-content: space-between; gap: 12px;"><span>Due Date</span><strong>${escapeHtml(formatInvoiceDate(data.due_date))}</strong></div>
                            <div style="display: flex; justify-content: space-between; gap: 12px;"><span>Reference</span><strong>${escapeHtml(reference)}</strong></div>
                            <div style="display: flex; justify-content: space-between; gap: 12px;"><span>Status</span><strong>${escapeHtml(paidLabel)}</strong></div>
                        </div>
                    </div>
                </div>

                <div style="border: 1px solid #e6eaf2; border-radius: 14px; overflow: hidden; background: #fff;">
                    <div style="display: grid; grid-template-columns: 1.5fr 1fr 1fr; background: linear-gradient(135deg, #f5f3ff, #eef2ff); font-weight: 700; color: #374151; padding: 14px 18px; border-bottom: 1px solid #e6eaf2; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em;">
                        <div>Service</div>
                        <div style="text-align: right;">Qty</div>
                        <div style="text-align: right;">Amount</div>
                    </div>
                    ${invoiceRows}
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <div style="width: min(340px, 100%); background: linear-gradient(135deg, rgba(124,58,237,0.18), rgba(6,182,212,0.12), rgba(236,72,153,0.10)); border-radius: 14px; color: var(--ink, #1a1a2e); padding: 18px 20px; box-shadow: 0 20px 40px rgba(15,23,42,0.08); border: 1px solid rgba(255,255,255,0.7);">
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 13px; color: var(--ink-secondary, #4a4a5a); padding-bottom: 10px; border-bottom: 1px solid var(--line, rgba(255,255,255,0.65)); margin-bottom: 12px;">
                            <span>Subtotal</span>
                            <strong>${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 13px; color: var(--ink-secondary, #4a4a5a); padding-bottom: 10px; border-bottom: 1px solid var(--line, rgba(255,255,255,0.65)); margin-bottom: 12px;">
                            <span>VAT</span>
                            <strong>${escapeHtml(formatInvoiceCurrency(0))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 18px; font-weight: 800;">
                            <span>Total</span>
                            <strong>${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-top: 8px;">
                    <button type="button" class="btn btn-secondary" data-invoice-action="print" style="padding: 10px 18px; border-radius: 10px;">Print</button>
                    <button type="button" class="btn btn-primary" data-invoice-action="download" style="padding: 10px 18px; border-radius: 10px;">Download</button>
                </div>
            </div>
        `;
    }

    function renderMinimalTemplate(data) {
        const invoiceNumber = data.invoice_number || 'INV-0000';
        const customerName = data.customer_name || 'Walk-in Customer';
        const amount = Number(data.total_amount || 0);
        const status = String(data.status || 'pending');
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        const paidLabel = status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? 'Paid' : statusLabel;
        const reference = data.transaction_id ? `#${data.transaction_id}` : '—';
        const invoiceData = escapeAttribute(JSON.stringify(data));
        const invoiceRows = getLineItemRows(data.items);

        return `
            <div class="invoicePrintContainer" data-invoice='${invoiceData}' data-template="minimal" style="display: grid; gap: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: baseline; padding-bottom: 16px; border-bottom: 2px solid #0f172a;">
                    <div>
                        <div style="font-size: 22px; font-weight: 700; color: #0f172a; letter-spacing: -0.02em;">CTECHLIT & SYSTEMS</div>
                        <div style="font-size: 11px; color: #64748b; margin-top: 4px; letter-spacing: 0.06em; text-transform: uppercase;">Digital Services & Internet Café</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 11px; color: #64748b; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Invoice</div>
                        <div style="font-size: 20px; font-weight: 700; color: #0f172a;">${escapeHtml(invoiceNumber)}</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px;">
                    <div>
                        <div style="font-size: 10px; color: #94a3b8; letter-spacing: 0.1em; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Bill To</div>
                        <div style="font-size: 16px; font-weight: 600; color: #1e293b;">${escapeHtml(customerName)}</div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 2px;">Customer</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 10px; color: #94a3b8; letter-spacing: 0.1em; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Details</div>
                        <div style="display: grid; gap: 6px; font-size: 13px; color: #334155;">
                            <div><span style="color: #94a3b8;">Issued</span> <strong>${escapeHtml(formatInvoiceDate(data.created_at))}</strong></div>
                            <div><span style="color: #94a3b8;">Due</span> <strong>${escapeHtml(formatInvoiceDate(data.due_date))}</strong></div>
                            <div><span style="color: #94a3b8;">Ref</span> <strong>${escapeHtml(reference)}</strong></div>
                            <div><span style="color: #94a3b8;">Status</span> <strong style="color: ${status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? '#059669' : '#d97706'}">${escapeHtml(paidLabel)}</strong></div>
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px solid #e2e8f0; padding-top: 16px;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; font-size: 10px; color: #94a3b8; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                        <div>Description</div>
                        <div style="text-align: right;">Qty</div>
                        <div style="text-align: right;">Amount</div>
                    </div>
                    ${invoiceRows}
                </div>

                <div style="display: flex; justify-content: flex-end; padding-top: 8px;">
                    <div style="width: min(280px, 100%); text-align: right;">
                        <div style="display: flex; justify-content: space-between; gap: 24px; font-size: 13px; color: #64748b; padding-bottom: 8px;">
                            <span>Subtotal</span>
                            <strong style="color: #1e293b;">${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 24px; font-size: 13px; color: #64748b; padding-bottom: 8px;">
                            <span>VAT</span>
                            <strong style="color: #1e293b;">${escapeHtml(formatInvoiceCurrency(0))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 24px; font-size: 16px; font-weight: 700; color: #0f172a; padding-top: 8px; border-top: 2px solid #0f172a;">
                            <span>Total</span>
                            <strong>${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-top: 8px;">
                    <button type="button" class="btn btn-secondary" data-invoice-action="print" style="padding: 10px 18px; border-radius: 10px;">Print</button>
                    <button type="button" class="btn btn-primary" data-invoice-action="download" style="padding: 10px 18px; border-radius: 10px;">Download</button>
                </div>
            </div>
        `;
    }

    function renderModernTemplate(data) {
        const invoiceNumber = data.invoice_number || 'INV-0000';
        const customerName = data.customer_name || 'Walk-in Customer';
        const amount = Number(data.total_amount || 0);
        const status = String(data.status || 'pending');
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        const paidLabel = status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? 'Paid' : statusLabel;
        const reference = data.transaction_id ? `#${data.transaction_id}` : '—';
        const invoiceData = escapeAttribute(JSON.stringify(data));
        const invoiceRows = getLineItemRows(data.items);

        return `
            <div class="invoicePrintContainer" data-invoice='${invoiceData}' data-template="modern" style="display: grid; gap: 0;">
                <div style="background: #0f172a; color: #f8fafc; padding: 24px 28px; border-radius: 16px 16px 0 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 20px; font-weight: 700; letter-spacing: 0.02em;">CTECHLIT & SYSTEMS</div>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Digital Services & Internet Café</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 10px; color: #94a3b8; letter-spacing: 0.1em; text-transform: uppercase; font-weight: 600;">Invoice</div>
                            <div style="font-size: 18px; font-weight: 700; margin-top: 2px;">${escapeHtml(invoiceNumber)}</div>
                        </div>
                    </div>
                </div>

                <div style="background: #fff; padding: 24px 28px; border: 1px solid #e2e8f0; border-top: none; display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div style="background: #f8fafc; border-radius: 12px; padding: 16px; border: 1px solid #f1f5f9;">
                        <div style="font-size: 10px; color: #94a3b8; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600; margin-bottom: 10px;">Billed To</div>
                        <div style="font-size: 16px; font-weight: 700; color: #1e293b;">${escapeHtml(customerName)}</div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Customer record</div>
                    </div>
                    <div style="background: #f8fafc; border-radius: 12px; padding: 16px; border: 1px solid #f1f5f9;">
                        <div style="font-size: 10px; color: #94a3b8; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600; margin-bottom: 10px;">Invoice Details</div>
                        <div style="display: grid; gap: 8px; font-size: 13px; color: #334155;">
                            <div style="display: flex; justify-content: space-between;"><span style="color: #94a3b8;">Issue Date</span><strong>${escapeHtml(formatInvoiceDate(data.created_at))}</strong></div>
                            <div style="display: flex; justify-content: space-between;"><span style="color: #94a3b8;">Due Date</span><strong>${escapeHtml(formatInvoiceDate(data.due_date))}</strong></div>
                            <div style="display: flex; justify-content: space-between;"><span style="color: #94a3b8;">Reference</span><strong>${escapeHtml(reference)}</strong></div>
                            <div style="display: flex; justify-content: space-between;"><span style="color: #94a3b8;">Status</span><strong style="color: ${status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? '#059669' : '#d97706'}">${escapeHtml(paidLabel)}</strong></div>
                        </div>
                    </div>
                </div>

                <div style="background: #fff; padding: 0 28px; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; font-size: 10px; color: #94a3b8; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600; padding: 16px 0; border-bottom: 1px solid #e2e8f0;">
                        <div>Service</div>
                        <div style="text-align: right;">Qty</div>
                        <div style="text-align: right;">Amount</div>
                    </div>
                    ${invoiceRows}
                </div>

                <div style="background: #fff; padding: 20px 28px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 16px 16px; display: flex; justify-content: flex-end;">
                    <div style="width: min(300px, 100%); background: #f8fafc; border-radius: 12px; padding: 18px; border: 1px solid #f1f5f9;">
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 13px; color: #64748b; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; margin-bottom: 10px;">
                            <span>Subtotal</span>
                            <strong style="color: #1e293b;">${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 13px; color: #64748b; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; margin-bottom: 10px;">
                            <span>VAT</span>
                            <strong style="color: #1e293b;">${escapeHtml(formatInvoiceCurrency(0))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 18px; font-weight: 700; color: #0f172a;">
                            <span>Total</span>
                            <strong>${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-top: 8px;">
                    <button type="button" class="btn btn-secondary" data-invoice-action="print" style="padding: 10px 18px; border-radius: 10px;">Print</button>
                    <button type="button" class="btn btn-primary" data-invoice-action="download" style="padding: 10px 18px; border-radius: 10px;">Download</button>
                </div>
            </div>
        `;
    }

    function renderFormalTemplate(data) {
        const invoiceNumber = data.invoice_number || 'INV-0000';
        const customerName = data.customer_name || 'Walk-in Customer';
        const amount = Number(data.total_amount || 0);
        const status = String(data.status || 'pending');
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        const paidLabel = status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? 'Paid' : statusLabel;
        const reference = data.transaction_id ? `#${data.transaction_id}` : '—';
        const invoiceData = escapeAttribute(JSON.stringify(data));
        const invoiceRows = getLineItemRows(data.items);
        const lineItems = Array.isArray(data.items) ? data.items : [];

        return `
            <div class="invoicePrintContainer" data-invoice='${invoiceData}' data-template="formal" style="display: grid; gap: 0; border: 1px solid #cbd5e1;">
                <div style="background: #1e3a5f; color: #f8fafc; padding: 28px 32px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <div style="font-size: 24px; font-weight: 700; letter-spacing: 0.03em;">CTECHLIT & SYSTEMS</div>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 6px; letter-spacing: 0.04em;">Digital Services & Internet Café</div>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">support@ctechlit.com • +233 20 000 0000</div>
                        </div>
                        <div style="text-align: right; background: rgba(255,255,255,0.1); padding: 12px 16px; border-radius: 8px;">
                            <div style="font-size: 10px; color: #94a3b8; letter-spacing: 0.1em; text-transform: uppercase; font-weight: 600;">Invoice</div>
                            <div style="font-size: 16px; font-weight: 700; margin-top: 4px;">${escapeHtml(invoiceNumber)}</div>
                        </div>
                    </div>
                </div>

                <div style="background: #fff; padding: 28px 32px; display: grid; grid-template-columns: 1fr 1fr; gap: 32px; border-bottom: 1px solid #e2e8f0;">
                    <div>
                        <div style="font-size: 10px; color: #64748b; letter-spacing: 0.1em; text-transform: uppercase; font-weight: 600; margin-bottom: 10px;">Bill To</div>
                        <div style="font-size: 16px; font-weight: 700; color: #1e293b;">${escapeHtml(customerName)}</div>
                        <div style="font-size: 12px; color: #64748b8; margin-top: 4px;">Customer record</div>
                    </div>
                    <div>
                        <div style="font-size: 10px; color: #64748b; letter-spacing: 0.1em; text-transform: uppercase; font-weight: 600; margin-bottom: 10px;">Invoice Details</div>
                        <table style="width: 100%; font-size: 13px; color: #334155; border-collapse: collapse;">
                            <tr><td style="padding: 4px 0; color: #64748b; width: 40%;">Issue Date</td><td style="padding: 4px 0; font-weight: 600; text-align: right;">${escapeHtml(formatInvoiceDate(data.created_at))}</td></tr>
                            <tr><td style="padding: 4px 0; color: #64748b;">Due Date</td><td style="padding: 4px 0; font-weight: 600; text-align: right;">${escapeHtml(formatInvoiceDate(data.due_date))}</td></tr>
                            <tr><td style="padding: 4px 0; color: #64748b;">Reference</td><td style="padding: 4px 0; font-weight: 600; text-align: right;">${escapeHtml(reference)}</td></tr>
                            <tr><td style="padding: 4px 0; color: #64748b;">Status</td><td style="padding: 4px 0; font-weight: 600; text-align: right; color: ${status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? '#059669' : '#d97706'}">${escapeHtml(paidLabel)}</td></tr>
                        </table>
                    </div>
                </div>

                <div style="background: #fff; padding: 0 32px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background: #f1f5f9;">
                                <th style="text-align: left; padding: 12px 16px; font-size: 10px; color: #64748b; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600;">Service</th>
                                <th style="text-align: right; padding: 12px 16px; font-size: 10px; color: #64748b; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600;">Qty</th>
                                <th style="text-align: right; padding: 12px 16px; font-size: 10px; color: #64748b; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${lineItems.length ? lineItems.map((item, i) => `
                                <tr style="border-bottom: 1px solid #f1f5f9; ${i % 2 === 0 ? 'background: #fafbfc;' : ''}">
                                    <td style="padding: 12px 16px; color: #1e293b;">${escapeHtml(item.service_name || item.description || 'Service')}</td>
                                    <td style="padding: 12px 16px; text-align: right; color: #64748b;">${escapeHtml(item.quantity ?? 0)}</td>
                                    <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: #0f172a;">${escapeHtml(formatInvoiceCurrency(item.total_price ?? item.unit_price ?? 0))}</td>
                                </tr>`).join('') : `
                                <tr><td style="padding: 12px 16px; color: #94a3b8;">Transaction total</td><td style="padding: 12px 16px; text-align: right; color: #94a3b8;">—</td><td style="padding: 12px 16px; text-align: right; font-weight: 600; color: #94a3b8;">—</td></tr>
                            `}
                        </tbody>
                    </table>
                </div>

                <div style="background: #fff; padding: 24px 32px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;">
                    <div style="width: min(300px, 100%);">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <tr><td style="padding: 8px 0; color: #64748b;">Subtotal</td><td style="padding: 8px 0; text-align: right; font-weight: 600; color: #1e293b;">${escapeHtml(formatInvoiceCurrency(amount))}</td></tr>
                            <tr><td style="padding: 8px 0; color: #64748b;">VAT</td><td style="padding: 8px 0; text-align: right; font-weight: 600; color: #1e293b;">${escapeHtml(formatInvoiceCurrency(0))}</td></tr>
                            <tr style="border-top: 2px solid #1e3a5f;"><td style="padding: 10px 0; font-size: 15px; font-weight: 700; color: #0f172a;">Total</td><td style="padding: 10px 0; text-align: right; font-size: 15px; font-weight: 700; color: #1e3a5f;">${escapeHtml(formatInvoiceCurrency(amount))}</td></tr>
                        </table>
                    </div>
                </div>

                <div style="background: #fff; padding: 16px 32px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #94a3b8;">
                    <span>support@ctechlit.com • +233 20 000 0000</span>
                    <span>Thank you for your business</span>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-top: 8px;">
                    <button type="button" class="btn btn-secondary" data-invoice-action="print" style="padding: 10px 18px; border-radius: 10px;">Print</button>
                    <button type="button" class="btn btn-primary" data-invoice-action="download" style="padding: 10px 18px; border-radius: 10px;">Download</button>
                </div>
            </div>
        `;
    }

    function renderBoldTemplate(data) {
        const invoiceNumber = data.invoice_number || 'INV-0000';
        const customerName = data.customer_name || 'Walk-in Customer';
        const amount = Number(data.total_amount || 0);
        const status = String(data.status || 'pending');
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        const paidLabel = status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? 'Paid' : statusLabel;
        const reference = data.transaction_id ? `#${data.transaction_id}` : '—';
        const invoiceData = escapeAttribute(JSON.stringify(data));
        const invoiceRows = getLineItemRows(data.items);
        const lineItems = Array.isArray(data.items) ? data.items : [];

        return `
            <div class="invoicePrintContainer" data-invoice='${invoiceData}' data-template="bold" style="display: grid; gap: 0; border-radius: 12px; overflow: hidden; border: 1px solid #0f172a;">
                <div style="background: #0f172a; color: #f8fafc; padding: 28px 32px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 22px; font-weight: 800; letter-spacing: 0.02em;">CTECHLIT & SYSTEMS</div>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 4px; letter-spacing: 0.04em;">Digital Services & Internet Café</div>
                        </div>
                        <div style="background: #f8fafc; color: #0f172a; padding: 8px 14px; border-radius: 6px; text-align: center;">
                            <div style="font-size: 9px; color: #64748b; letter-spacing: 0.1em; text-transform: uppercase; font-weight: 600;">Invoice</div>
                            <div style="font-size: 15px; font-weight: 800; margin-top: 2px;">${escapeHtml(invoiceNumber)}</div>
                        </div>
                    </div>
                </div>

                <div style="background: #fff; padding: 28px 32px; display: grid; grid-template-columns: 1fr 1fr; gap: 32px; border-bottom: 1px solid #e2e8f0;">
                    <div>
                        <div style="font-size: 10px; color: #64748b; letter-spacing: 0.1em; text-transform: uppercase; font-weight: 700; margin-bottom: 10px;">BILL TO</div>
                        <div style="font-size: 16px; font-weight: 700; color: #0f172a;">${escapeHtml(customerName)}</div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Customer record</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 10px; color: #64748b; letter-spacing: 0.1em; text-transform: uppercase; font-weight: 700; margin-bottom: 10px;">DETAILS</div>
                        <div style="display: grid; gap: 6px; font-size: 13px; color: #334155;">
                            <div><span style="color: #94a3b8;">Issued</span> <strong>${escapeHtml(formatInvoiceDate(data.created_at))}</strong></div>
                            <div><span style="color: #94a3b8;">Due</span> <strong>${escapeHtml(formatInvoiceDate(data.due_date))}</strong></div>
                            <div><span style="color: #94a3b8;">Ref</span> <strong>${escapeHtml(reference)}</strong></div>
                            <div><span style="color: #94a3b8;">Status</span> <strong style="color: ${status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? '#059669' : '#d97706'}">${escapeHtml(paidLabel)}</strong></div>
                        </div>
                    </div>
                </div>

                <div style="background: #fff; padding: 0 32px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background: #0f172a; color: #f8fafc;">
                                <th style="text-align: left; padding: 14px 16px; font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600;">Service</th>
                                <th style="text-align: right; padding: 14px 16px; font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600;">Qty</th>
                                <th style="text-align: right; padding: 14px 16px; font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${lineItems.length ? lineItems.map((item, i) => `
                                <tr style="border-bottom: 1px solid #e2e8f0; ${i % 2 === 0 ? 'background: #fafbfc;' : ''}">
                                    <td style="padding: 14px 16px; color: #1e293b; font-weight: 500;">${escapeHtml(item.service_name || item.description || 'Service')}</td>
                                    <td style="padding: 14px 16px; text-align: right; color: #64748b;">${escapeHtml(item.quantity ?? 0)}</td>
                                    <td style="padding: 14px 16px; text-align: right; font-weight: 700; color: #0f172a;">${escapeHtml(formatInvoiceCurrency(item.total_price ?? item.unit_price ?? 0))}</td>
                                </tr>`).join('') : `
                                <tr><td style="padding: 14px 16px; color: #94a3b8;">Transaction total</td><td style="padding: 14px 16px; text-align: right; color: #94a3b8;">—</td><td style="padding: 14px 16px; text-align: right; font-weight: 600; color: #94a3b8;">—</td></tr>
                            `}
                        </tbody>
                    </table>
                </div>

                <div style="background: #fff; padding: 24px 32px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;">
                    <div style="width: min(300px, 100%); background: #0f172a; color: #f8fafc; border-radius: 10px; padding: 18px 20px;">
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 13px; color: #94a3b8; padding-bottom: 10px; border-bottom: 1px solid #334155; margin-bottom: 10px;">
                            <span>Subtotal</span>
                            <strong style="color: #f8fafc;">${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 13px; color: #94a3b8; padding-bottom: 10px; border-bottom: 1px solid #334155; margin-bottom: 10px;">
                            <span>VAT</span>
                            <strong style="color: #f8fafc;">${escapeHtml(formatInvoiceCurrency(0))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 18px; font-weight: 800; color: #f8fafc;">
                            <span>Total</span>
                            <strong>${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                    </div>
                </div>

                <div style="background: #fff; padding: 16px 32px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #94a3b8;">
                    <span>support@ctechlit.com • +233 20 000 0000</span>
                    <span>Thank you for your business</span>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-top: 8px;">
                    <button type="button" class="btn btn-secondary" data-invoice-action="print" style="padding: 10px 18px; border-radius: 10px;">Print</button>
                    <button type="button" class="btn btn-primary" data-invoice-action="download" style="padding: 10px 18px; border-radius: 10px;">Download</button>
                </div>
            </div>
        `;
    }

    function renderCompactTemplate(data) {
        const invoiceNumber = data.invoice_number || 'INV-0000';
        const customerName = data.customer_name || 'Walk-in Customer';
        const amount = Number(data.total_amount || 0);
        const status = String(data.status || 'pending');
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        const paidLabel = status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? 'Paid' : statusLabel;
        const reference = data.transaction_id ? `#${data.transaction_id}` : '—';
        const invoiceData = escapeAttribute(JSON.stringify(data));
        const lineItems = Array.isArray(data.items) ? data.items : [];

        return `
            <div class="invoicePrintContainer" data-invoice='${invoiceData}' data-template="compact" style="display: grid; gap: 12px; font-size: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                    <div>
                        <div style="font-size: 18px; font-weight: 700; color: #0f172a;">CTECHLIT & SYSTEMS</div>
                        <div style="font-size: 10px; color: #64748b; margin-top: 2px;">support@ctechlit.com • +233 20 000 0000</div>
                    </div>
                    <div style="text-align: right; background: #f1f5f9; padding: 6px 12px; border-radius: 6px;">
                        <div style="font-size: 9px; color: #64748b; letter-spacing: 0.08em; text-transform: uppercase;">Invoice</div>
                        <div style="font-size: 14px; font-weight: 700; color: #0f172a;">${escapeHtml(invoiceNumber)}</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <div style="font-size: 9px; color: #94a3b8; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">BILL TO</div>
                        <div style="font-size: 13px; font-weight: 600; color: #1e293b;">${escapeHtml(customerName)}</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="display: grid; gap: 2px; font-size: 11px; color: #475569;">
                            <div><span style="color: #94a3b8;">Issued</span> <strong>${escapeHtml(formatInvoiceDate(data.created_at))}</strong></div>
                            <div><span style="color: #94a3b8;">Due</span> <strong>${escapeHtml(formatInvoiceDate(data.due_date))}</strong></div>
                            <div><span style="color: #94a3b8;">Ref</span> <strong>${escapeHtml(reference)}</strong></div>
                            <div><span style="color: #94a3b8;">Status</span> <strong style="color: ${status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? '#059669' : '#d97706'}">${escapeHtml(paidLabel)}</strong></div>
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px solid #e2e8f0; padding-top: 8px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="text-align: left; padding: 8px 10px; font-size: 9px; color: #64748b; letter-spacing: 0.06em; text-transform: uppercase; font-weight: 600;">Service</th>
                                <th style="text-align: right; padding: 8px 10px; font-size: 9px; color: #64748b; letter-spacing: 0.06em; text-transform: uppercase; font-weight: 600;">Qty</th>
                                <th style="text-align: right; padding: 8px 10px; font-size: 9px; color: #64748b; letter-spacing: 0.06em; text-transform: uppercase; font-weight: 600;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${lineItems.length ? lineItems.map((item, i) => `
                                <tr style="border-bottom: 1px solid #f1f5f9; ${i % 2 === 0 ? 'background: #fafbfc;' : ''}">
                                    <td style="padding: 8px 10px; color: #1e293b;">${escapeHtml(item.service_name || item.description || 'Service')}</td>
                                    <td style="padding: 8px 10px; text-align: right; color: #64748b;">${escapeHtml(item.quantity ?? 0)}</td>
                                    <td style="padding: 8px 10px; text-align: right; font-weight: 600; color: #0f172a;">${escapeHtml(formatInvoiceCurrency(item.total_price ?? item.unit_price ?? 0))}</td>
                                </tr>`).join('') : `
                                <tr><td style="padding: 8px 10px; color: #94a3b8;">Transaction total</td><td style="padding: 8px 10px; text-align: right; color: #94a3b8;">—</td><td style="padding: 8px 10px; text-align: right; font-weight: 600; color: #94a3b8;">—</td></tr>
                            `}
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; justify-content: flex-end; padding-top: 4px;">
                    <div style="width: min(240px, 100%); text-align: right;">
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 11px; color: #64748b; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; margin-bottom: 6px;">
                            <span>Subtotal</span><strong style="color: #1e293b;">${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 11px; color: #64748b; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; margin-bottom: 6px;">
                            <span>VAT</span><strong style="color: #1e293b;">${escapeHtml(formatInvoiceCurrency(0))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 14px; font-weight: 700; color: #0f172a; padding-top: 4px;">
                            <span>Total</span><strong>${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-top: 8px;">
                    <button type="button" class="btn btn-secondary" data-invoice-action="print" style="padding: 10px 18px; border-radius: 10px;">Print</button>
                    <button type="button" class="btn btn-primary" data-invoice-action="download" style="padding: 10px 18px; border-radius: 10px;">Download</button>
                </div>
            </div>
        `;
    }

    function renderEmptyTemplate(data) {
        const invoiceNumber = data.invoice_number || 'INV-0000';
        const customerName = data.customer_name || '';
        const amount = Number(data.total_amount || 0);
        const status = String(data.status || 'pending');
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        const paidLabel = status.toLowerCase() === 'completed' || status.toLowerCase() === 'paid' ? 'Paid' : statusLabel;
        const reference = data.transaction_id ? `#${data.transaction_id}` : '';
        const invoiceData = escapeAttribute(JSON.stringify(data));
        const emptyRows = getEmptyLineItemRows();

        return `
            <div class="invoicePrintContainer" data-invoice='${invoiceData}' data-template="empty" style="display: grid; gap: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding-bottom: 16px; border-bottom: 1px dashed #cbd5e1;">
                    <div>
                        <div style="font-size: 24px; font-weight: 700; color: #1e293b;">CTECHLIT & SYSTEMS</div>
                        <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Digital Services & Internet Café</div>
                        <div style="font-size: 11px; color: #64748b; margin-top: 4px;">support@ctechlit.com • +233 20 000 0000</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 10px; color: #64748b; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Invoice</div>
                        <div style="font-size: 18px; font-weight: 700; color: #0f172a;">${escapeHtml(invoiceNumber)}</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div>
                        <div style="font-size: 10px; color: #64748b; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">BILL TO</div>
                        <div style="font-size: 15px; font-weight: 600; color: #1e293b; min-height: 20px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 2px;">${escapeHtml(customerName) || '&nbsp;'}</div>
                    </div>
                    <div>
                        <div style="font-size: 10px; color: #64748b; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">DETAILS</div>
                        <div style="display: grid; gap: 6px; font-size: 12px; color: #475569;">
                            <div><span style="color: #94a3b8; width: 70px; display: inline-block;">Issued</span> <strong>${escapeHtml(formatInvoiceDate(data.created_at))}</strong></div>
                            <div><span style="color: #94a3b8; width: 70px; display: inline-block;">Due</span> <strong>${escapeHtml(formatInvoiceDate(data.due_date))}</strong></div>
                            <div><span style="color: #94a3b8; width: 70px; display: inline-block;">Ref</span> <strong>${escapeHtml(reference) || '—'}</strong></div>
                            <div><span style="color: #94a3b8; width: 70px; display: inline-block;">Status</span> <strong>${escapeHtml(paidLabel) || '—'}</strong></div>
                        </div>
                    </div>
                </div>

                <div style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #fff;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; background: #f8fafc; font-weight: 600; color: #64748b; padding: 10px 16px; border-bottom: 1px solid #e2e8f0; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em;">
                        <div>Service / Description</div>
                        <div style="text-align: right;">Qty</div>
                        <div style="text-align: right;">Amount</div>
                    </div>
                    ${emptyRows}
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <div style="width: min(260px, 100%); text-align: right;">
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 12px; color: #64748b; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0; margin-bottom: 8px;">
                            <span>Subtotal</span><strong style="color: #1e293b;">${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 12px; color: #64748b; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0; margin-bottom: 8px;">
                            <span>VAT</span><strong style="color: #1e293b;">${escapeHtml(formatInvoiceCurrency(0))}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 16px; font-weight: 700; color: #0f172a; padding-top: 6px; border-top: 2px solid #0f172a;">
                            <span>Total</span><strong>${escapeHtml(formatInvoiceCurrency(amount))}</strong>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-top: 8px;">
                    <button type="button" class="btn btn-secondary" data-invoice-action="print" style="padding: 10px 18px; border-radius: 10px;">Print</button>
                    <button type="button" class="btn btn-primary" data-invoice-action="download" style="padding: 10px 18px; border-radius: 10px;">Download</button>
                </div>
            </div>
        `;
    }

    function buildInvoiceMarkup(data, templateId) {
        const template = templateId || currentInvoiceTemplate || 'classic';
        const templates = {
            classic: renderClassicTemplate,
            minimal: renderMinimalTemplate,
            modern: renderModernTemplate,
            formal: renderFormalTemplate,
            bold: renderBoldTemplate,
            compact: renderCompactTemplate,
            empty: renderEmptyTemplate,
        };
        const renderer = templates[template] || renderClassicTemplate;
        return renderer(data);
    }

    function printInvoice(data, templateId) {
        const template = templateId || currentInvoiceTemplate || 'classic';
        const html = buildInvoiceMarkup(data, template);
        const printWindow = window.open('', '_blank', 'width=1200,height=900');
        if (!printWindow) {
            showNotification('Pop-up blocked. Please allow pop-ups to print the invoice.', 'warning');
            return;
        }

        const printHtml = `
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Invoice ${escapeHtml(data.invoice_number || 'Preview')}</title>
                <style>
                    body { font-family: Inter, Arial, sans-serif; background: #fff; color: #111827; margin: 0; padding: 32px; }
                    * { box-sizing: border-box; }
                    .btn { display: none !important; }
                </style>
            </head>
            <body>${html}</body>
            </html>
        `;

        printWindow.document.write(printHtml);
        printWindow.document.close();
        setTimeout(() => {
            printWindow.focus();
            printWindow.print();
        }, 500);
    }

    function downloadInvoice(data, templateId) {
        const template = templateId || currentInvoiceTemplate || 'classic';
        const filename = `${(data.invoice_number || 'invoice').replace(/\s+/g, '-')}.html`;
        const raw = `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>${escapeHtml(data.invoice_number || 'Invoice')}</title><style>body{font-family:Inter,Arial,sans-serif;background:#f4f6fb;color:#111827;margin:0;padding:32px}*{box-sizing:border-box} .btn{display:none!important}</style></head><body>${buildInvoiceMarkup(data, template)}</body></html>`;
        const blob = new Blob([raw], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        showNotification('Invoice downloaded successfully.', 'success');
    }

    let editableInvoiceData = {};

    function openEditableInvoiceForm(data = {}) {
        editableInvoiceData = {
            invoice_number: data.invoice_number || '',
            customer_name: data.customer_name || '',
            total_amount: Number(data.total_amount || 0),
            created_at: data.created_at || '',
            due_date: data.due_date || '',
            status: data.status || 'pending',
            transaction_id: data.transaction_id || '',
            items: Array.isArray(data.items) ? data.items : [],
        };

        elements.viewTitle.textContent = 'Edit Invoice';
        elements.viewBody.innerHTML = getEditableInvoiceFormHtml();
        overlays.view.classList.add('active');
        document.body.style.overflow = 'hidden';
        bindEditableInvoiceEvents();
    }

    function getEditableInvoiceFormHtml() {
        const d = editableInvoiceData;
        const rows = d.items.map((item, index) => `
            <div class="invoice-line-row" data-index="${index}" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: end; margin-bottom: 10px;">
                <div>
                    <label style="font-size: 11px; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px;">Service / Description</label>
                    <input type="text" class="form-input line-service" value="${escapeHtml(item.service_name || item.description || '')}" placeholder="e.g. Website Design" style="width: 100%;">
                </div>
                <div>
                    <label style="font-size: 11px; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px;">Qty</label>
                    <input type="number" class="form-input line-qty" value="${escapeHtml(String(item.quantity ?? 1))}" placeholder="1" min="1" step="1" style="width: 100%;">
                </div>
                <div>
                    <label style="font-size: 11px; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px;">Amount (GHS)</label>
                    <input type="number" class="form-input line-amount" value="${escapeHtml(String(item.total_price ?? item.unit_price ?? 0))}" placeholder="0.00" min="0" step="0.01" style="width: 100%;">
                </div>
                <button type="button" class="btn btn-secondary remove-line-btn" style="padding: 8px 12px; border-radius: 8px; margin-bottom: 0; height: 38px;" title="Remove line">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `).join('');

        return `
            <form id="editableInvoiceForm" style="display: grid; gap: 16px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 6px;">Invoice Number</label>
                        <input type="text" id="editInvoiceNumber" class="form-input" value="${escapeHtml(d.invoice_number)}" placeholder="INV-0000" style="width: 100%;">
                    </div>
                    <div>
                        <label style="font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 6px;">Customer Name</label>
                        <input type="text" id="editCustomerName" class="form-input" value="${escapeHtml(d.customer_name)}" placeholder="Walk-in Customer" style="width: 100%;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 6px;">Issue Date</label>
                        <input type="date" id="editCreatedAt" class="form-input" value="${escapeHtml(d.created_at)}" style="width: 100%;">
                    </div>
                    <div>
                        <label style="font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 6px;">Due Date</label>
                        <input type="date" id="editDueDate" class="form-input" value="${escapeHtml(d.due_date)}" style="width: 100%;">
                    </div>
                    <div>
                        <label style="font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 6px;">Status</label>
                        <select id="editStatus" class="form-select form-input" style="width: 100%;">
                            <option value="pending" ${d.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="completed" ${d.status === 'completed' ? 'selected' : ''}>Paid</option>
                            <option value="failed" ${d.status === 'failed' ? 'selected' : ''}>Failed</option>
                            <option value="refunded" ${d.status === 'refunded' ? 'selected' : ''}>Refunded</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label style="font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 6px;">Reference / Transaction ID</label>
                    <input type="text" id="editTransactionId" class="form-input" value="${escapeHtml(String(d.transaction_id))}" placeholder="#1001" style="width: 100%;">
                </div>

                <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; background: #fff;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <div style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">Line Items</div>
                        <button type="button" id="addLineBtn" class="btn btn-secondary" style="padding: 6px 12px; border-radius: 8px; font-size: 12px;">
                            <i class="fas fa-plus" style="margin-right: 4px;"></i> Add Line
                        </button>
                    </div>
                    <div id="lineItemsContainer">
                        ${rows || '<div style="font-size: 12px; color: #94a3b8; padding: 10px 0;">No line items. Click Add Line to begin.</div>'}
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" id="editPrintBtn" style="padding: 10px 18px; border-radius: 10px;">Print</button>
                    <button type="button" class="btn btn-secondary" id="editDownloadBtn" style="padding: 10px 18px; border-radius: 10px;">Download</button>
                    <button type="button" class="btn btn-primary" id="editSaveBtn" style="padding: 10px 18px; border-radius: 10px;">Save</button>
                </div>
            </form>
        `;
    }

    function bindEditableInvoiceEvents() {
        const container = document.getElementById('lineItemsContainer');
        const addBtn = document.getElementById('addLineBtn');
        const saveBtn = document.getElementById('editSaveBtn');
        const printBtn = document.getElementById('editPrintBtn');
        const downloadBtn = document.getElementById('editDownloadBtn');

        if (addBtn) {
            addBtn.addEventListener('click', () => {
                const emptyState = container.querySelector('div[style]');
                if (emptyState && emptyState.textContent.includes('No line items')) {
                    container.innerHTML = '';
                }
                const index = container.querySelectorAll('.invoice-line-row').length;
                const row = document.createElement('div');
                row.className = 'invoice-line-row';
                row.dataset.index = index;
                row.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: end; margin-bottom: 10px;';
                row.innerHTML = `
                    <div>
                        <label style="font-size: 11px; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px;">Service / Description</label>
                        <input type="text" class="form-input line-service" value="" placeholder="e.g. Website Design" style="width: 100%;">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px;">Qty</label>
                        <input type="number" class="form-input line-qty" value="1" placeholder="1" min="1" step="1" style="width: 100%;">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px;">Amount (GHS)</label>
                        <input type="number" class="form-input line-amount" value="0.00" placeholder="0.00" min="0" step="0.01" style="width: 100%;">
                    </div>
                    <button type="button" class="btn btn-secondary remove-line-btn" style="padding: 8px 12px; border-radius: 8px; margin-bottom: 0; height: 38px;" title="Remove line">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                `;
                container.appendChild(row);
                bindLineRowEvents(row);
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                const invoiceNumber = document.getElementById('editInvoiceNumber').value.trim();
                const customerName = document.getElementById('editCustomerName').value.trim();
                const created_at = document.getElementById('editCreatedAt').value;
                const due_date = document.getElementById('editDueDate').value;
                const status = document.getElementById('editStatus').value;
                const transaction_id = document.getElementById('editTransactionId').value.trim();

                const lineRows = container.querySelectorAll('.invoice-line-row');
                const items = [];
                let totalAmount = 0;
                lineRows.forEach(row => {
                    const service = row.querySelector('.line-service').value.trim();
                    const quantity = Number(row.querySelector('.line-qty').value) || 0;
                    const amount = Number(row.querySelector('.line-amount').value) || 0;
                    if (service || quantity || amount) {
                        items.push({ service_name: service, description: service, quantity, unit_price: amount, total_price: amount });
                        totalAmount += amount * quantity;
                    }
                });

                editableInvoiceData = {
                    invoice_number: invoiceNumber || 'INV-0000',
                    customer_name: customerName || 'Walk-in Customer',
                    total_amount: totalAmount,
                    created_at,
                    due_date,
                    status,
                    transaction_id: transaction_id || '',
                    items,
                };

                currentInvoiceTemplate = 'empty';
                const label = document.getElementById('currentTemplateLabel');
                if (label) label.textContent = 'Empty';

                showNotification('Custom invoice saved.', 'success');
                closeView();
            });
        }

        if (printBtn) {
            printBtn.addEventListener('click', () => {
                collectEditableInvoiceData();
                printInvoice(editableInvoiceData, 'empty');
            });
        }

        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => {
                collectEditableInvoiceData();
                downloadInvoice(editableInvoiceData, 'empty');
            });
        }

        container.querySelectorAll('.invoice-line-row').forEach(bindLineRowEvents);
    }

    function bindLineRowEvents(row) {
        const removeBtn = row.querySelector('.remove-line-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                row.remove();
                const container = document.getElementById('lineItemsContainer');
                if (container && !container.querySelectorAll('.invoice-line-row').length) {
                    container.innerHTML = '<div style="font-size: 12px; color: #94a3b8; padding: 10px 0;">No line items. Click Add Line to begin.</div>';
                }
            });
        }
    }

    function collectEditableInvoiceData() {
        const container = document.getElementById('lineItemsContainer');
        const invoiceNumber = document.getElementById('editInvoiceNumber').value.trim();
        const customerName = document.getElementById('editCustomerName').value.trim();
        const created_at = document.getElementById('editCreatedAt').value;
        const due_date = document.getElementById('editDueDate').value;
        const status = document.getElementById('editStatus').value;
        const transaction_id = document.getElementById('editTransactionId').value.trim();

        const lineRows = container.querySelectorAll('.invoice-line-row');
        const items = [];
        let totalAmount = 0;
        lineRows.forEach(row => {
            const service = row.querySelector('.line-service').value.trim();
            const quantity = Number(row.querySelector('.line-qty').value) || 0;
            const amount = Number(row.querySelector('.line-amount').value) || 0;
            if (service || quantity || amount) {
                items.push({ service_name: service, description: service, quantity, unit_price: amount, total_price: amount });
                totalAmount += amount * quantity;
            }
        });

        editableInvoiceData = {
            invoice_number: invoiceNumber || 'INV-0000',
            customer_name: customerName || 'Walk-in Customer',
            total_amount: totalAmount,
            created_at,
            due_date,
            status,
            transaction_id: transaction_id || '',
            items,
        };
    }

    // Open view modal
    async function openView(entity, data) {
        const config = window.MODAL_CONFIGS?.[entity];
        if (!config) return;

        const requestId = ++viewRequestId;
        elements.viewTitle.textContent = config.title;
        const template = currentInvoiceTemplate || 'classic';
        const renderContent = record => entity === 'invoice'
            ? `${buildInvoiceMarkup(record, template)}<section class="record-detail-section">
                <h4>Complete record details</h4>
                ${renderDetailView(entity, record)}
            </section>`
            : renderDetailView(entity, record);
        elements.viewBody.innerHTML = renderContent(data);
        overlays.view.classList.add('active');
        document.body.style.overflow = 'hidden';

        try {
            const fullRecord = await fetchFullRecord(entity, data);
            if (requestId === viewRequestId && overlays.view.classList.contains('active')) {
                elements.viewBody.innerHTML = renderContent(fullRecord);
            }
        } catch (error) {
            if (requestId === viewRequestId && overlays.view.classList.contains('active')) {
                showNotification(`Showing available list data only. ${error.message}`, 'warning');
            }
        }
    }

    // Close view modal
    function closeView() {
        viewRequestId++;
        overlays.view.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Open main modal
    function openModal(entity, mode = 'create', data = {}) {
        const config = window.MODAL_CONFIGS?.[entity];
        if (!config) {
            console.error(`No config found for entity: ${entity}`);
            return;
        }
        if (mode === 'view') {
            openView(entity, data);
            return;
        }
        
        currentEntity = entity;
        currentMode = mode;
        currentEntityId = data[config.idField] || null;
        
        const title = mode === 'edit' ? `Edit ${config.title}` : mode === 'view' ? `View ${config.title}` : `Add ${config.title}`;
        elements.title.textContent = title;
        
        let html = '';
        if (mode === 'view') {
            // View mode - show read-only details
            html = renderDetailView(entity, data);
            
            // Hide footer for view mode
            elements.footer.style.display = 'none';
        } else {
            // Create/Edit mode - show form
            html = generateFormHtml(config, data);
            elements.footer.style.display = 'flex';
            elements.confirm.textContent = mode === 'edit' ? 'Update' : 'Create';
        }
        
        elements.body.innerHTML = html;
        
        // Load AJAX selects if any
        if (mode !== 'view') {
            const form = document.getElementById('modalForm');
            if (form) {
                form.querySelectorAll('select[ajax-url]').forEach(select => {
                    loadSelectOptions(select, select.getAttribute('ajax-url'));
                });
                
                config.fields.forEach(field => {
                    if (field.ajax) {
                        const select = form.querySelector(`[name="${field.name}"]`);
                        if (select) {
                            select.setAttribute('ajax-url', field.ajax);
                            loadSelectOptions(select, field.ajax);
                        }
                    }
                });
            }
        }
        
        // Show modal
        overlays.main.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Focus first input
        setTimeout(() => {
            const firstInput = elements.body.querySelector('.form-input, .form-select, .form-textarea');
            if (firstInput) firstInput.focus();
        }, 100);
    }

    // Close main modal
    function closeModal() {
        overlays.main.classList.add('closing');
        setTimeout(() => {
            overlays.main.classList.remove('active', 'closing');
            document.body.style.overflow = '';
            elements.body.innerHTML = '';
            currentEntity = null;
            currentEntityId = null;
            currentMode = 'create';
        }, 250);
    }

    // Open confirmation modal
    function openConfirm(options) {
        const {
            title = 'Confirm Action',
            message = 'Are you sure you want to proceed?',
            detail = '',
            icon = 'warning', // 'warning' | 'danger' | 'info'
            confirmText = 'Confirm',
            confirmClass = 'btn-danger',
            onConfirm,
            onCancel,
        } = options;
        
        elements.confirmTitle.textContent = title;
        elements.confirmMessage.textContent = message;
        elements.confirmDetail.textContent = detail;
        elements.confirmConfirm.textContent = confirmText;
        elements.confirmConfirm.className = `btn ${confirmClass}`;
        elements.confirmIcon.className = `modal-icon ${icon}`;
        
        const icons = {
            warning: 'fa-exclamation-triangle',
            danger: 'fa-trash',
            info: 'fa-info-circle'
        };
        elements.confirmIcon.innerHTML = `<i class="fas ${icons[icon]}"></i>`;
        
        callbacks.confirm = onConfirm;
        callbacks.confirmCancel = onCancel;
        
        overlays.confirm.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Close confirmation modal
    function closeConfirm() {
        overlays.confirm.classList.remove('active');
        document.body.style.overflow = '';
        callbacks.confirm = null;
        callbacks.confirmCancel = null;
    }

    // Event Listeners
    function bindEvents() {
        // Main modal
        elements.close.addEventListener('click', closeModal);
        elements.cancel.addEventListener('click', closeModal);
        overlays.main.addEventListener('click', (e) => {
            if (e.target === overlays.main) closeModal();
        });
        
        elements.confirm.addEventListener('click', async () => {
            const form = document.getElementById('modalForm');
            if (!form) return;
            
            if (!validateForm(form)) return;
            
            const config = window.MODAL_CONFIGS[currentEntity];
            let data = collectFormData(form);
            
            if (callbacks.beforeSubmit) {
                const modified = await callbacks.beforeSubmit(data, currentMode);
                if (modified === null) return;
                if (modified !== undefined) data = modified;
            }
            
            elements.confirm.disabled = true;
            elements.confirm.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            try {
                await submitForm(config, data, currentMode === 'edit');
                showNotification(`${config.title} ${currentMode === 'edit' ? 'updated' : 'created'} successfully`, 'success');
                closeModal();
                
                // Trigger callback if exists
                if (callbacks.onSave) {
                    callbacks.onSave(data, currentMode);
                }
                
                // Refresh page or table
                if (typeof window.refreshTable === 'function') {
                    window.refreshTable();
                } else {
                    location.reload();
                }
            } catch (error) {
                showNotification(error.message, 'error');
            } finally {
                elements.confirm.disabled = false;
                elements.confirm.textContent = currentMode === 'edit' ? 'Update' : 'Create';
            }
        });
        
        // Confirm modal
        elements.confirmClose.addEventListener('click', closeConfirm);
        elements.confirmCancel.addEventListener('click', () => {
            if (callbacks.confirmCancel) callbacks.confirmCancel();
            closeConfirm();
        });
        overlays.confirm.addEventListener('click', (e) => {
            if (e.target === overlays.confirm) closeConfirm();
        });
        
        elements.confirmConfirm.addEventListener('click', () => {
            if (callbacks.confirm) callbacks.confirm();
            closeConfirm();
        });
        
        // View modal
        elements.viewClose.addEventListener('click', closeView);
        elements.viewCloseBtn.addEventListener('click', closeView);
        overlays.view.addEventListener('click', (e) => {
            if (e.target === overlays.view) closeView();
        });

        document.addEventListener('click', (event) => {
            const actionButton = event.target.closest('[data-invoice-action]');
            if (!actionButton) return;

            const container = actionButton.closest('.invoicePrintContainer');
            const invoiceData = container ? JSON.parse(container.dataset.invoice || '{}') : {};
            const template = container ? (container.dataset.template || currentInvoiceTemplate || 'classic') : (currentInvoiceTemplate || 'classic');
            const action = actionButton.dataset.invoiceAction;

            if (action === 'print') {
                printInvoice(invoiceData, template);
            }

            if (action === 'download') {
                downloadInvoice(invoiceData, template);
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            const activeOverlay = getActiveOverlay();
            if (e.key === 'Tab' && activeOverlay) {
                const focusableElements = getFocusableElements(activeOverlay);
                if (!focusableElements.length) {
                    e.preventDefault();
                    activeOverlay.querySelector('.modal')?.focus();
                    return;
                }

                const first = focusableElements[0];
                const last = focusableElements[focusableElements.length - 1];
                if (e.shiftKey && (document.activeElement === first || !activeOverlay.contains(document.activeElement))) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && (document.activeElement === last || !activeOverlay.contains(document.activeElement))) {
                    e.preventDefault();
                    first.focus();
                }
                return;
            }

            if (e.key === 'Escape') {
                if (overlays.main.classList.contains('active')) closeModal();
                else if (overlays.confirm.classList.contains('active')) closeConfirm();
                else if (overlays.view.classList.contains('active')) closeView();
            }
        });
    }

    // Initialize
    function init() {
        watchOverlayState();

        // Wait for DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bindEvents);
        } else {
            bindEvents();
        }
        
        // Make configs globally available
        window.MODAL_CONFIGS = window.MODAL_CONFIGS || {};
    }

    // Public API
    return {
        init,
        open: openModal,
        close: closeModal,
        openConfirm,
        closeConfirm,
        openView,
        closeView,
        openEditableInvoiceForm,
        renderDetails: renderDetailView,
        setCallback: (name, fn) => { callbacks[name] = fn; },
        showNotification,
        downloadInvoice,
        printInvoice,
        getCurrentEntity: () => currentEntity,
        setInvoiceTemplate: (template) => { currentInvoiceTemplate = template; },
        getInvoiceTemplate: () => currentInvoiceTemplate,
        buildInvoiceMarkup,
    };
})();

window.downloadInvoice = window.ModalController.downloadInvoice;
window.printInvoice = window.ModalController.printInvoice;

// Initialize on load
window.ModalController.init();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = window.ModalController;
}
