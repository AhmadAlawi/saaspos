/**
 * The web Label Designer — free x/y drag + width/height resize over a FIXED
 * set of 5 elements (name, sku, price, barcode, image), one row per
 * `config('labels.layouts')` key. Adapted from
 * resources/js/admin/receipt-canvas-editor.js: same interact.js drag +
 * commit-on-end shape, NOT an Alpine component for the same reason (fights
 * a 60fps drag loop) — but working in PERCENT space (the canvas surface's
 * own pixel size defines 0-100%) instead of mm, since positions here are
 * percent-of-label, not millimeters. Nothing is added or removed here —
 * every layout always has exactly these 5 rows (seeded by
 * LabelLayout::firstOrCreateForKey()), only their fields change.
 *
 * The canvas surface is server-rendered — it's the SAME `_cell-canvas`
 * partial the true-size preview and the print sheet use (real fonts, real
 * barcode SVG, real logo image), not a hand-rolled placeholder. After every
 * commit (drag end, resize end, a property-panel field, a dimension
 * change) the surface is re-fetched from the server rather than
 * hand-mirroring the Blade sizing rules here — one place decides how an
 * element looks, this file just drives position/size and property edits.
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

    let canvasW = parseFloat(root.dataset.canvasW);
    let canvasH = parseFloat(root.dataset.canvasH);
    let pxPerMm = parseFloat(root.dataset.pxPerMm);
    let labelWMm = parseFloat(root.dataset.labelWMm);
    let labelHMm = parseFloat(root.dataset.labelHMm);
    const updateUrlTemplate = root.dataset.updateUrlTemplate;
    const dimensionsUrl = root.dataset.dimensionsUrl;
    const canvasUrl = root.dataset.canvasUrl;
    const previewUrl = root.dataset.previewUrl;
    const labels = JSON.parse(root.dataset.labels);
    let elements = JSON.parse(root.dataset.elements);

    const surface = document.getElementById('lbl-canvas-surface');
    const panel = document.getElementById('lbl-property-panel');
    const statusEl = document.getElementById('lbl-save-status');
    const previewFrame = document.getElementById('lbl-preview-frame');
    const dimWInput = document.getElementById('lbl-dim-w');
    const dimHInput = document.getElementById('lbl-dim-h');
    const dimSaveBtn = document.getElementById('lbl-dim-save');

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

    /** Re-fetch the real rendered cell (server is the only source of truth
     *  for how an element looks) and re-wire drag/resize on the fresh nodes. */
    async function refreshCanvas() {
        try {
            const res = await fetch(canvasUrl, { credentials: 'same-origin' });
            surface.innerHTML = await res.text();
        } catch (e) {
            // The last-known-good DOM stays on screen — a failed refetch
            // isn't worth surfacing as an error, the save itself already
            // reports success/failure.
        }
        wireInteractions();
        if (selectedType) highlightSelection(selectedType);
    }

    function highlightSelection(type) {
        surface.querySelectorAll('.label-el').forEach((n) => {
            n.style.outline = n.dataset.type === type ? '2px solid #2563eb' : '1px dashed #93c5fd';
            n.style.outlineOffset = '1px';
        });
    }

    function selectElement(type) {
        selectedType = type;
        highlightSelection(type);
        renderPanel(findEl(type));
    }

    async function saveField(type, field, value) {
        setStatus(labels.saving);
        try {
            await posPatch(updateUrl(type), { [field]: value });
            const el = findEl(type);
            if (el) el[field] = value;
            setStatus(labels.saved);
            await refreshCanvas();
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
        rows.push(sizeRow(el));

        if (FONT_TYPES.includes(el.type)) {
            rows.push(fieldRow('font_size', 'number', el.font_size, { min: 5, max: 48 }));
            rows.push(fontFamilyRow(el.font_family));
        }
        rows.push(alignRow(el.align));

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

    /**
     * Width/height — every element type gets these now (drag a corner on
     * the canvas itself for the same effect; these inputs are for typing
     * an exact percent). Blank means "auto" — the element's old implicit
     * sizing (font/line-height for text, `scale` for barcode/image).
     */
    function sizeRow(el) {
        return `<div style="display:flex; gap:8px; margin-bottom:8px;">
            <label class="field" style="flex:1;">
                <span class="field-label">Width %</span>
                <input type="number" class="pos-input" data-field="width_pct" value="${el.width_pct ?? ''}" min="1" max="100" step="1" placeholder="auto">
            </label>
            <label class="field" style="flex:1;">
                <span class="field-label">Height %</span>
                <input type="number" class="pos-input" data-field="height_pct" value="${el.height_pct ?? ''}" min="1" max="100" step="1" placeholder="auto">
            </label>
        </div>`;
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
                if (input.type === 'number') {
                    // Width/height are nullable ("auto") — an emptied field
                    // clears back to the old implicit sizing instead of
                    // coercing to 0/NaN.
                    value = value === '' ? null : parseFloat(value);
                } else if (value === '') {
                    value = null;
                }
                saveField(el.type, field, value);
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
                    await refreshCanvas();
                    renderPanel(el);
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
                    await refreshCanvas();
                    renderPanel(el);
                    refreshPreview();
                } catch (e) {
                    setStatus(labels.saveFailed);
                }
            });
        }
    }

    function wireInteractions() {
        interact('#lbl-canvas-surface .label-el').unset();
        interact('#lbl-canvas-surface .label-el')
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
                        target.style.left = el.x_pct + '%';
                        target.style.top = el.y_pct + '%';
                        posPatch(updateUrl(type), { x_pct: el.x_pct, y_pct: el.y_pct }).then(() => {
                            setStatus(labels.saved);
                            refreshCanvas();
                            refreshPreview();
                        }).catch(() => setStatus(labels.saveFailed));
                    },
                },
            })
            .resizable({
                // Bottom/right only — resizing from the top/left edge would
                // also shift x/y, which would need a second coordinate
                // update on every resize. Dragging the element afterwards
                // covers repositioning just as well.
                edges: { left: false, right: true, bottom: true, top: false },
                listeners: {
                    move(event) {
                        const target = event.target;
                        target.style.width = event.rect.width + 'px';
                        target.style.height = event.rect.height + 'px';
                    },
                    end(event) {
                        const target = event.target;
                        const type = target.dataset.type;
                        const el = findEl(type);
                        if (! el) return;
                        el.width_pct = Math.max(1, Math.min(100, (event.rect.width / canvasW) * 100));
                        el.height_pct = Math.max(1, Math.min(100, (event.rect.height / canvasH) * 100));
                        posPatch(updateUrl(type), { width_pct: el.width_pct, height_pct: el.height_pct }).then(() => {
                            setStatus(labels.saved);
                            refreshCanvas();
                            refreshPreview();
                            if (selectedType === type) renderPanel(el);
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

    if (dimSaveBtn) {
        dimSaveBtn.addEventListener('click', async () => {
            const w = parseFloat(dimWInput.value);
            const h = parseFloat(dimHInput.value);
            if (! w || ! h) return;
            setStatus(labels.saving);
            try {
                await posPatch(dimensionsUrl, { label_w_mm: w, label_h_mm: h });
                labelWMm = w;
                labelHMm = h;
                // Same clamp formula as designer.blade.php's initial render.
                pxPerMm = Math.max(4, Math.min(10, 260 / Math.max(w, h)));
                canvasW = Math.round(w * pxPerMm);
                canvasH = Math.round(h * pxPerMm);
                surface.style.width = canvasW + 'px';
                surface.style.height = canvasH + 'px';
                setStatus(labels.saved);
                await refreshCanvas();
                refreshPreview();
            } catch (e) {
                setStatus(labels.saveFailed);
            }
        });
    }

    wireInteractions();
    renderPanel(null);
}

document.addEventListener('DOMContentLoaded', init);
