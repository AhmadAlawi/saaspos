/**
 * The web Label Designer — free x/y drag over a FIXED set of 5 elements
 * (name, sku, price, barcode, image), one row per `config('labels.layouts')`
 * key. Adapted from resources/js/admin/receipt-canvas-editor.js: same
 * interact.js drag + commit-on-end shape, NOT an Alpine component for the
 * same reason (fights a 60fps drag loop) — but working in PERCENT space
 * (the canvas surface's own pixel size defines 0-100%) instead of mm,
 * since positions here are percent-of-label, not millimeters. Nothing is
 * added or removed here — every layout always has exactly these 5 rows
 * (seeded by LabelLayout::firstOrCreateForKey()), only their fields change.
 */
import interact from 'interactjs';
import { posPost, posPatch } from '../lib/http.js';

// A real HTTP PATCH with a multipart body never reaches PHP's $_FILES —
// method-spoof it as a POST, same trick receipt-canvas-editor.js uses.
function postAsPatch(url, formData) {
    formData.append('_method', 'PATCH');
    return posPost(url, formData);
}

function init() {
    const root = document.getElementById('lbl-canvas-app');
    if (! root) return;

    const canvasW = parseFloat(root.dataset.canvasW);
    const canvasH = parseFloat(root.dataset.canvasH);
    const updateUrlTemplate = root.dataset.updateUrlTemplate;
    const previewUrl = root.dataset.previewUrl;
    const labels = JSON.parse(root.dataset.labels);
    let elements = JSON.parse(root.dataset.elements);

    const surface = document.getElementById('lbl-canvas-surface');
    const panel = document.getElementById('lbl-property-panel');
    const statusEl = document.getElementById('lbl-save-status');
    const previewFrame = document.getElementById('lbl-preview-frame');

    let selectedType = null;

    function updateUrl(type) { return updateUrlTemplate.replace('__TYPE__', type); }

    function setStatus(text) {
        statusEl.textContent = text;
        if (text === labels.saved) {
            setTimeout(() => { if (statusEl.textContent === labels.saved) statusEl.textContent = ''; }, 1500);
        }
    }

    function refreshPreview() {
        previewFrame.src = previewUrl + '?t=' + Date.now();
    }

    function findEl(type) {
        return elements.find((e) => e.type === type);
    }

    function renderElementBox(el) {
        const box = document.createElement('div');
        box.className = 'lbl-el';
        box.dataset.type = el.type;
        box.style.position = 'absolute';
        box.style.left = (el.x_pct / 100 * canvasW) + 'px';
        box.style.top = (el.y_pct / 100 * canvasH) + 'px';
        box.style.minWidth = '20px';
        box.style.minHeight = '14px';
        box.style.padding = '2px 4px';
        box.style.fontSize = Math.max(8, el.font_size || 10) + 'px';
        box.style.textAlign = el.align;
        box.style.border = '1px dashed #93c5fd';
        box.style.background = 'rgba(239,246,255,.85)';
        box.style.cursor = 'move';
        box.style.opacity = el.is_visible ? '1' : '0.35';
        box.style.userSelect = 'none';
        box.style.whiteSpace = 'nowrap';
        box.style.overflow = 'hidden';
        if (el.width_pct) box.style.width = (el.width_pct / 100 * canvasW) + 'px';
        box.textContent = labels.types[el.type] || el.type;
        box.dataset.x = 0;
        box.dataset.y = 0;
        return box;
    }

    function renderAll() {
        surface.querySelectorAll('.lbl-el').forEach((n) => n.remove());
        elements.forEach((el) => surface.appendChild(renderElementBox(el)));
        wireInteractions();
        if (selectedType) selectElement(selectedType); else renderPanel(null);
    }

    function selectElement(type) {
        selectedType = type;
        surface.querySelectorAll('.lbl-el').forEach((n) => {
            n.style.borderColor = n.dataset.type === type ? '#2563eb' : '#93c5fd';
            n.style.borderWidth = n.dataset.type === type ? '2px' : '1px';
        });
        renderPanel(findEl(type));
    }

    async function saveField(type, field, value) {
        setStatus(labels.saving);
        try {
            await posPatch(updateUrl(type), { [field]: value });
            const el = findEl(type);
            if (el) el[field] = value;
            setStatus(labels.saved);
            refreshPreview();
        } catch (e) {
            setStatus(labels.saveFailed);
        }
    }

    const FONT_LABELS = { sans: 'Sans', serif: 'Serif', mono: 'Monospace' };
    const FONT_TYPES = ['name', 'sku', 'price'];

    function renderPanel(el) {
        if (! el) {
            panel.innerHTML = `<p class="text-muted text-xs">${labels.noSelection}</p>`;
            return;
        }

        const rows = [];
        rows.push(`<div style="font-weight:600; margin-bottom:8px;">${(labels.types[el.type] || el.type).replace(/</g, '&lt;')}</div>`);
        rows.push(checkboxRow('is_visible', el.is_visible, 'visible'));

        if (FONT_TYPES.includes(el.type)) {
            rows.push(fieldRow('font_size', 'number', el.font_size, { min: 5, max: 48 }));
            rows.push(fontFamilyRow(el.font_family));
            rows.push(alignRow(el.align));
        } else {
            rows.push(scaleRow(el.scale));
        }

        if (el.type === 'image') {
            rows.push(imageRow(el));
        }

        panel.innerHTML = rows.join('');
        wirePanelInputs(el);
    }

    function fieldRow(name, type, value, attrs = {}) {
        const attrStr = Object.entries(attrs).map(([k, v]) => `${k}="${v}"`).join(' ');
        const label = name === 'font_size' ? 'Font size' : name;
        return `<label class="field" style="margin-bottom:8px;">
            <span class="field-label">${label}</span>
            <input type="${type}" class="pos-input" data-field="${name}" value="${value ?? ''}" ${attrStr}>
        </label>`;
    }

    function scaleRow(current) {
        return `<label class="field" style="margin-bottom:8px;">
            <span class="field-label">Size</span>
            <input type="number" class="pos-input" data-field="scale" value="${current}" min="0.1" max="3" step="0.1">
        </label>`;
    }

    function fontFamilyRow(current) {
        const opts = Object.entries(FONT_LABELS).map(([v, label]) =>
            `<option value="${v}" ${v === current ? 'selected' : ''}>${label}</option>`).join('');
        return `<label class="field" style="margin-bottom:8px;">
            <span class="field-label">Font</span>
            <select class="pos-input" data-field="font_family">${opts}</select>
        </label>`;
    }

    function alignRow(current) {
        const opts = ['left', 'center', 'right'].map((v) =>
            `<option value="${v}" ${v === current ? 'selected' : ''}>${v}</option>`).join('');
        return `<label class="field" style="margin-bottom:8px;">
            <span class="field-label">Align</span>
            <select class="pos-input" data-field="align">${opts}</select>
        </label>`;
    }

    function checkboxRow(name, value, text) {
        return `<label class="field-toggle" style="margin-bottom:8px;">
            <input type="checkbox" data-field="${name}" ${value ? 'checked' : ''}>
            <span>${text}</span>
        </label>`;
    }

    function imageRow(el) {
        const current = el.config?.image_path
            ? `<div class="mb-2"><img src="/storage/${el.config.image_path}" style="max-height:50px;"></div>`
            : '';
        const removeBtn = el.config?.image_path
            ? `<button type="button" class="pos-btn pos-btn-sm pos-btn-ghost mt-1" data-action="remove-image">${'Remove logo'}</button>`
            : '';
        return `<div class="field" style="margin-bottom:8px;">
            ${current}
            <span class="field-label">Image</span>
            <input type="file" class="pos-input" data-field="image" accept="image/png,image/jpeg,image/webp">
            <button type="button" class="pos-btn pos-btn-sm pos-btn-primary mt-1" data-action="save-image">Upload</button>
            ${removeBtn}
        </div>`;
    }

    function wirePanelInputs(el) {
        panel.querySelectorAll('[data-field]').forEach((input) => {
            if (input.dataset.field === 'image') return; // saved via explicit button
            const evt = (input.type === 'checkbox' || input.tagName === 'SELECT') ? 'change' : 'blur';
            input.addEventListener(evt, () => {
                const field = input.dataset.field;
                let value = input.type === 'checkbox' ? input.checked : input.value;
                if (input.type === 'number') value = parseFloat(value);
                saveField(el.type, field, value).then(() => renderAll());
            });
        });

        const saveImageBtn = panel.querySelector('[data-action="save-image"]');
        if (saveImageBtn) {
            saveImageBtn.addEventListener('click', async () => {
                const fileInput = panel.querySelector('[data-field="image"]');
                if (! fileInput.files[0]) return;
                const form = new FormData();
                form.append('image', fileInput.files[0]);
                setStatus(labels.saving);
                try {
                    const { data } = await postAsPatch(updateUrl(el.type), form);
                    if (data?.config !== undefined) el.config = data.config;
                    setStatus(labels.saved);
                    renderAll();
                    refreshPreview();
                } catch (e) {
                    setStatus(labels.saveFailed);
                }
            });
        }

        const removeImageBtn = panel.querySelector('[data-action="remove-image"]');
        if (removeImageBtn) {
            removeImageBtn.addEventListener('click', async () => {
                setStatus(labels.saving);
                try {
                    const { data } = await posPatch(updateUrl(el.type), { image_remove: true });
                    if (data?.config !== undefined) el.config = data.config;
                    setStatus(labels.saved);
                    renderAll();
                    refreshPreview();
                } catch (e) {
                    setStatus(labels.saveFailed);
                }
            });
        }
    }

    function wireInteractions() {
        interact('.lbl-el').unset();
        interact('.lbl-el')
            .draggable({
                listeners: {
                    move(event) {
                        const target = event.target;
                        const x = (parseFloat(target.dataset.x) || 0) + event.dx;
                        const y = (parseFloat(target.dataset.y) || 0) + event.dy;
                        target.style.transform = `translate(${x}px, ${y}px)`;
                        target.dataset.x = x;
                        target.dataset.y = y;
                    },
                    end(event) {
                        const target = event.target;
                        const type = target.dataset.type;
                        const el = findEl(type);
                        if (! el) return;
                        const dxPct = ((parseFloat(target.dataset.x) || 0) / canvasW) * 100;
                        const dyPct = ((parseFloat(target.dataset.y) || 0) / canvasH) * 100;
                        el.x_pct = Math.max(0, Math.min(100, el.x_pct + dxPct));
                        el.y_pct = Math.max(0, Math.min(100, el.y_pct + dyPct));
                        target.dataset.x = 0;
                        target.dataset.y = 0;
                        target.style.transform = '';
                        target.style.left = (el.x_pct / 100 * canvasW) + 'px';
                        target.style.top = (el.y_pct / 100 * canvasH) + 'px';
                        posPatch(updateUrl(type), { x_pct: el.x_pct, y_pct: el.y_pct }).then(() => {
                            setStatus(labels.saved);
                            refreshPreview();
                        }).catch(() => setStatus(labels.saveFailed));
                    },
                },
            })
            .on('tap', (event) => {
                selectElement(event.currentTarget.dataset.type);
            });
    }

    document.querySelectorAll('[data-select-type]').forEach((btn) => {
        btn.addEventListener('click', () => selectElement(btn.dataset.selectType));
    });

    renderAll();
}

document.addEventListener('DOMContentLoaded', init);
