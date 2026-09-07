/**
 * The "Canvas" receipt designer — free x/y drag + resize over a list of
 * {@see \App\Models\ReceiptTemplateElement} rows. Deliberately NOT an
 * Alpine `x-data` component for the drag/resize hot path: Alpine
 * re-evaluates expressions/DOM on every reactive mutation, which fights a
 * 60fps drag loop. This module does direct DOM/CSS-transform manipulation
 * during drag and only talks to the server (via the shared `posPost`/
 * `posPatch`/`posDelete` client) on drag-end / resize-end / panel-save —
 * same "commit on end, not on every frame" shape the rest of the admin
 * bundle already uses for autosave-style inputs.
 */
import interact from 'interactjs';
import { posPost, posPatch, posDelete } from '../lib/http.js';

// A true HTTP PATCH with a multipart body never reaches PHP's $_FILES —
// PHP only auto-parses the multipart superglobals for POST. Laravel's
// own Blade forms sidestep this with method-spoofing (@method('PATCH')
// is actually a POST + hidden `_method` field); the image-upload call
// below does the same thing by hand since axios sends a real verb.
function postAsPatch(url, formData) {
    formData.append('_method', 'PATCH');
    return posPost(url, formData);
}

const PX_PER_MM = 3;
const DEFAULT_BOX_MM = { w: 30, h: 8 };

function init() {
    const root = document.getElementById('rtpl-canvas-app');
    if (! root) return;

    const templateId = root.dataset.templateId;
    const paperMm = parseFloat(root.dataset.paperMm);
    const printableMm = parseFloat(root.dataset.printableMm);
    const storeUrl = root.dataset.storeUrl;
    const updateUrlTemplate = root.dataset.updateUrlTemplate;
    const destroyUrlTemplate = root.dataset.destroyUrlTemplate;
    const previewUrl = root.dataset.previewUrl;
    const paperSizeUrl = root.dataset.paperSizeUrl;
    const labels = JSON.parse(root.dataset.labels);
    let elements = JSON.parse(root.dataset.elements);

    const surface = document.getElementById('rtpl-canvas-surface');
    const guide = document.getElementById('rtpl-printable-guide');
    const panel = document.getElementById('rtpl-property-panel');
    const statusEl = document.getElementById('rtpl-save-status');
    const previewFrame = document.getElementById('rtpl-preview-frame');

    surface.style.width = (paperMm * PX_PER_MM) + 'px';
    guide.style.left = (printableMm * PX_PER_MM) + 'px';

    let selectedId = null;

    function updateUrl(id) { return updateUrlTemplate.replace('__ID__', id); }
    function destroyUrl(id) { return destroyUrlTemplate.replace('__ID__', id); }

    function setStatus(text) {
        statusEl.textContent = text;
        if (text === labels.saved) {
            setTimeout(() => { if (statusEl.textContent === labels.saved) statusEl.textContent = ''; }, 1500);
        }
    }

    function refreshPreview() {
        previewFrame.src = previewUrl + '?t=' + Date.now();
    }

    function resizeSurfaceToFit() {
        let maxBottom = 200;
        elements.forEach((el) => {
            const h = (el.height || DEFAULT_BOX_MM.h);
            maxBottom = Math.max(maxBottom, (el.y + h) * PX_PER_MM + 40);
        });
        surface.style.height = maxBottom + 'px';
        guide.style.height = maxBottom + 'px';
    }

    function labelFor(el) {
        if (el.type === 'text' && el.config?.text) return el.config.text.slice(0, 40);
        return labels.types[el.type] || el.type;
    }

    function renderElementBox(el) {
        const box = document.createElement('div');
        box.className = 'rtpl-el';
        box.dataset.id = el.id;
        box.style.position = 'absolute';
        box.style.left = (el.x * PX_PER_MM) + 'px';
        box.style.top = (el.y * PX_PER_MM) + 'px';
        box.style.minWidth = '24px';
        box.style.minHeight = '18px';
        box.style.padding = '3px 6px';
        box.style.fontSize = Math.max(9, el.font_size) + 'px';
        box.style.fontWeight = el.is_bold ? 'bold' : 'normal';
        box.style.textAlign = el.align;
        box.style.border = '1px dashed #93c5fd';
        box.style.background = 'rgba(239,246,255,.85)';
        box.style.cursor = 'move';
        box.style.opacity = el.is_visible ? '1' : '0.4';
        box.style.userSelect = 'none';
        box.style.whiteSpace = 'nowrap';
        box.style.overflow = 'hidden';
        if (el.width) box.style.width = (el.width * PX_PER_MM) + 'px';
        if (el.height) box.style.height = (el.height * PX_PER_MM) + 'px';
        box.textContent = labelFor(el);
        box.dataset.x = 0;
        box.dataset.y = 0;
        return box;
    }

    function renderAll() {
        surface.querySelectorAll('.rtpl-el').forEach((n) => n.remove());
        elements.forEach((el) => surface.appendChild(renderElementBox(el)));
        resizeSurfaceToFit();
        wireInteractions();
        if (selectedId) selectElement(selectedId); else renderPanel(null);
    }

    function findEl(id) {
        return elements.find((e) => String(e.id) === String(id));
    }

    function selectElement(id) {
        selectedId = id;
        surface.querySelectorAll('.rtpl-el').forEach((n) => {
            n.style.borderColor = String(n.dataset.id) === String(id) ? '#2563eb' : '#93c5fd';
            n.style.borderWidth = String(n.dataset.id) === String(id) ? '2px' : '1px';
        });
        renderPanel(findEl(id));
    }

    async function saveField(id, field, value) {
        setStatus(labels.saving);
        try {
            await posPatch(updateUrl(id), { [field]: value });
            const el = findEl(id);
            if (el) el[field] = value;
            setStatus(labels.saved);
            refreshPreview();
        } catch (e) {
            setStatus(labels.saveFailed);
        }
    }

    async function saveConfig(id, patch) {
        setStatus(labels.saving);
        try {
            const { data } = await posPatch(updateUrl(id), patch);
            const el = findEl(id);
            if (el && data?.config !== undefined) el.config = data.config;
            setStatus(labels.saved);
            renderAll();
            refreshPreview();
        } catch (e) {
            setStatus(labels.saveFailed);
        }
    }

    function renderPanel(el) {
        if (! el) {
            panel.innerHTML = `<p class="text-muted text-xs">${labels.noSelection}</p>`;
            return;
        }

        const rows = [];
        rows.push(`<div style="font-weight:600; margin-bottom:8px;">${labelFor(el).replace(/</g, '&lt;')}</div>`);

        rows.push(fieldRow('font_size', 'number', el.font_size, { min: 6, max: 72 }));
        rows.push(fontFamilyRow(el.font_family));
        rows.push(alignRow(el.align));
        rows.push(checkboxRow('is_bold', el.is_bold, 'bold'));
        rows.push(checkboxRow('is_visible', el.is_visible, 'visible'));

        if (el.type === 'text') {
            rows.push(textareaRow(el.config?.text || ''));
        }
        if (el.type === 'image' || el.type === 'logo') {
            rows.push(imageRow(el));
        }
        if (el.type === 'items_table') {
            rows.push(columnsRow(el));
        }
        rows.push(deleteRow());

        panel.innerHTML = rows.join('');
        wirePanelInputs(el);
    }

    function fieldRow(name, type, value, attrs = {}) {
        const attrStr = Object.entries(attrs).map(([k, v]) => `${k}="${v}"`).join(' ');
        return `<label class="field" style="margin-bottom:8px;">
            <span class="field-label">${labelText(name)}</span>
            <input type="${type}" class="pos-input" data-field="${name}" value="${value ?? ''}" ${attrStr}>
        </label>`;
    }

    function labelText(name) {
        const map = { font_size: 'Font size' };
        return map[name] || name;
    }

    const FONT_LABELS = {
        dejavu_sans: 'Sans (Arabic-safe)',
        dejavu_serif: 'Serif',
        dejavu_mono: 'Monospace',
    };

    function fontFamilyRow(current) {
        const opts = Object.entries(FONT_LABELS).map(([v, label]) =>
            `<option value="${v}" ${v === current ? 'selected' : ''}>${label}</option>`).join('');
        return `<label class="field" style="margin-bottom:8px;">
            <span class="field-label">Font</span>
            <select class="pos-input" data-field="font_family">${opts}</select>
            <span class="text-muted text-xs">Arabic text always renders in the Arabic-safe font, regardless of this pick.</span>
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

    function textareaRow(value) {
        return `<label class="field" style="margin-bottom:8px;">
            <span class="field-label">Text</span>
            <textarea class="pos-input" data-field="text" rows="3" dir="auto">${escapeHtml(value)}</textarea>
            <button type="button" class="pos-btn pos-btn-sm pos-btn-primary mt-1" data-action="save-text">Save text</button>
        </label>`;
    }

    function imageRow(el) {
        const current = el.config?.image_path
            ? `<div class="mb-2"><img src="/storage/${el.config.image_path}" style="max-height:50px;"></div>`
            : '';
        return `<div class="field" style="margin-bottom:8px;">
            ${current}
            <span class="field-label">Image</span>
            <input type="file" class="pos-input" data-field="image" accept="image/png,image/jpeg,image/webp">
            <button type="button" class="pos-btn pos-btn-sm pos-btn-primary mt-1" data-action="save-image">Upload</button>
        </div>`;
    }

    // Fallback only for a field the admin has never added a column for
    // yet — once added, its real width comes from `current` (below) and
    // stays whatever they last set it to.
    const DEFAULT_COLUMN_WIDTH_MM = { name: 24, sku: 15, hsn: 12, qty: 10, unit_price: 12, line_total: 13 };
    const defaultLabel = (f) => f === 'name' ? 'Item' : (labels.columnsAvailable?.[f] || f);

    function columnsRow(el) {
        const available = ['name', 'sku', 'hsn', 'qty', 'unit_price', 'line_total'];
        const current = el.config?.columns || [];
        const currentByField = {};
        current.forEach((c) => { currentByField[c.field] = c; });
        const checks = available.map((f) => {
            const on = f === 'name' || !!currentByField[f];
            const disabled = f === 'name' ? 'disabled' : '';
            const width = currentByField[f]?.width_mm ?? DEFAULT_COLUMN_WIDTH_MM[f] ?? 12;
            const label = currentByField[f]?.label ?? defaultLabel(f);
            return `<div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                <label class="field-toggle" style="flex:0 0 20px; margin-bottom:0;" title="${escapeHtml(labels.columnsAvailable?.[f] || f)}">
                    <input type="checkbox" data-col="${f}" ${on ? 'checked' : ''} ${disabled}>
                </label>
                <input type="text" data-col-label="${f}" value="${escapeHtml(label)}"
                       class="pos-input" style="flex:1;" title="Column header text — what prints (data is still ${escapeHtml(labels.columnsAvailable?.[f] || f)})"
                       placeholder="${escapeHtml(defaultLabel(f))}">
                <input type="number" data-col-width="${f}" value="${width}" min="6" max="60" step="1"
                       class="pos-input" style="width:56px; flex:0 0 56px;" title="Column width (mm)">
            </div>`;
        }).join('');
        return `<div class="field" style="margin-bottom:8px;">
            <span class="field-label">Columns</span>
            <div class="text-muted text-xs" style="margin-bottom:4px;">Only checked columns print, in this order. Number on the right is that column's width in mm — make Item name wider than Qty, for example.</div>
            <div data-col-total-hint class="text-xs" style="margin-bottom:4px;"></div>
            ${checks}
            <label class="field-toggle" style="margin-top:6px;">
                <input type="checkbox" data-field="show_sku_subline" ${el.config?.show_sku_subline ? 'checked' : ''}>
                <span>Show SKU under item (barcode if no SKU)</span>
            </label>
            <label class="field-toggle">
                <input type="checkbox" data-field="show_barcode_subline" ${el.config?.show_barcode_subline ? 'checked' : ''}>
                <span>Show barcode under item (always, ignores SKU)</span>
            </label>
            <label class="field-toggle">
                <input type="checkbox" data-field="show_discount_subline" ${el.config?.show_discount_subline ? 'checked' : ''}>
                <span>Show discount under item</span>
            </label>
            <label class="field-toggle">
                <input type="checkbox" data-field="show_batch_subline" ${el.config?.show_batch_subline ? 'checked' : ''}>
                <span>Show batch under item</span>
            </label>
            <label class="field-toggle">
                <input type="checkbox" data-field="show_borders" ${el.config?.show_borders ? 'checked' : ''}>
                <span>Draw table borders (outer box + column lines)</span>
            </label>
            <label class="field-toggle">
                <input type="checkbox" data-field="show_row_dividers" ${el.config?.show_row_dividers ? 'checked' : ''}>
                <span>Divider line between rows only (no box, no column lines)</span>
            </label>
            <label class="field-toggle">
                <input type="checkbox" data-field="show_currency" ${el.config?.show_currency === false ? '' : 'checked'}>
                <span>Show currency symbol on price/total</span>
            </label>
            <label class="field" style="margin-top:6px; margin-bottom:0;">
                <span class="field-label">Gap between an item's sub-lines and the next item (mm)</span>
                <input type="number" data-field="subline_gap_mm" value="${el.config?.subline_gap_mm ?? 3.0}" min="0.5" max="20" step="0.5" class="pos-input" style="width:80px;">
                <span class="text-muted text-xs">Also the gap between two sub-lines on the same item (SKU/barcode/discount/batch).</span>
            </label>
            <button type="button" class="pos-btn pos-btn-sm pos-btn-primary mt-1" data-action="save-columns">Save columns</button>
        </div>`;
    }

    function deleteRow() {
        return `<button type="button" class="pos-btn pos-btn-sm pos-btn-danger mt-2" data-action="delete">${'Delete'}</button>`;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function wirePanelInputs(el) {
        panel.querySelectorAll('[data-field]').forEach((input) => {
            if (input.tagName === 'TEXTAREA' || input.dataset.field === 'image') return; // saved via explicit button
            const evt = input.type === 'checkbox' ? 'change' : (input.tagName === 'SELECT' ? 'change' : 'blur');
            input.addEventListener(evt, () => {
                const field = input.dataset.field;
                let value = input.type === 'checkbox' ? input.checked : input.value;
                if (input.type === 'number') value = parseInt(value, 10) || el.font_size;
                if (field === 'font_size' || field === 'font_family' || field === 'align' || field === 'is_bold' || field === 'is_visible') {
                    saveField(el.id, field, value).then(() => renderAll());
                }
            });
        });

        const saveTextBtn = panel.querySelector('[data-action="save-text"]');
        if (saveTextBtn) {
            saveTextBtn.addEventListener('click', () => {
                const text = panel.querySelector('[data-field="text"]').value;
                saveConfig(el.id, { text });
            });
        }

        const saveImageBtn = panel.querySelector('[data-action="save-image"]');
        if (saveImageBtn) {
            saveImageBtn.addEventListener('click', async () => {
                const fileInput = panel.querySelector('[data-field="image"]');
                if (! fileInput.files[0]) return;
                const form = new FormData();
                form.append('image', fileInput.files[0]);
                setStatus(labels.saving);
                try {
                    const { data } = await postAsPatch(updateUrl(el.id), form);
                    if (data?.config !== undefined) el.config = data.config;
                    setStatus(labels.saved);
                    renderAll();
                    refreshPreview();
                } catch (e) {
                    setStatus(labels.saveFailed);
                }
            });
        }

        const checkedColumnFields = () => ['name', ...Array.from(panel.querySelectorAll('[data-col]'))
            .filter((cb) => cb.checked && cb.dataset.col !== 'name')
            .map((cb) => cb.dataset.col)];
        const widthFor = (field) => parseFloat(panel.querySelector(`[data-col-width="${field}"]`)?.value) || DEFAULT_COLUMN_WIDTH_MM[field] || 12;
        const labelFor = (field) => panel.querySelector(`[data-col-label="${field}"]`)?.value.trim() || defaultLabel(field);
        const totalHint = panel.querySelector('[data-col-total-hint]');
        const refreshTotalHint = () => {
            if (! totalHint) return;
            const total = checkedColumnFields().reduce((sum, f) => sum + widthFor(f), 0);
            const over = total > printableMm;
            totalHint.textContent = `Columns total: ${total}mm of ~${printableMm}mm printable`;
            totalHint.style.color = over ? '#b91c1c' : '';
            totalHint.style.fontWeight = over ? '600' : '';
        };
        refreshTotalHint();
        panel.querySelectorAll('[data-col], [data-col-width]').forEach((input) => {
            input.addEventListener('input', refreshTotalHint);
            input.addEventListener('change', refreshTotalHint);
        });

        const saveColumnsBtn = panel.querySelector('[data-action="save-columns"]');
        if (saveColumnsBtn) {
            saveColumnsBtn.addEventListener('click', async () => {
                const fields = checkedColumnFields();
                const total = fields.reduce((sum, f) => sum + widthFor(f), 0);
                if (total > printableMm) {
                    window.alert(`Columns total ${total}mm, but this paper only prints about ${printableMm}mm wide. Narrow one or more columns before saving.`);
                    return;
                }
                const columns = fields.map((f) => ({ field: f, label: labelFor(f), width_mm: widthFor(f) }));
                const showSku = panel.querySelector('[data-field="show_sku_subline"]')?.checked || false;
                const showBarcode = panel.querySelector('[data-field="show_barcode_subline"]')?.checked || false;
                const showDiscount = panel.querySelector('[data-field="show_discount_subline"]')?.checked || false;
                const showBatch = panel.querySelector('[data-field="show_batch_subline"]')?.checked || false;
                const showBorders = panel.querySelector('[data-field="show_borders"]')?.checked || false;
                const showRowDividers = panel.querySelector('[data-field="show_row_dividers"]')?.checked || false;
                const showCurrency = panel.querySelector('[data-field="show_currency"]')?.checked || false;
                const sublineGapMm = parseFloat(panel.querySelector('[data-field="subline_gap_mm"]')?.value) || 3.0;
                const patch = { columns, show_sku_subline: showSku, show_barcode_subline: showBarcode, show_discount_subline: showDiscount, show_batch_subline: showBatch, show_borders: showBorders, show_row_dividers: showRowDividers, show_currency: showCurrency, subline_gap_mm: sublineGapMm };
                setStatus(labels.saving);
                try {
                    const { data } = await posPatch(updateUrl(el.id), patch);
                    if (data?.config !== undefined) el.config = data.config;
                    setStatus(labels.saved);
                    renderAll();
                    refreshPreview();
                } catch (e) {
                    setStatus(labels.saveFailed);
                    window.alert(e?.message || labels.saveFailed);
                }
            });
        }

        const deleteBtn = panel.querySelector('[data-action="delete"]');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', async () => {
                if (! window.confirm(labels.confirmDelete)) return;
                try {
                    await posDelete(destroyUrl(el.id));
                    elements = elements.filter((e) => String(e.id) !== String(el.id));
                    selectedId = null;
                    renderAll();
                    refreshPreview();
                } catch (e) {
                    setStatus(labels.saveFailed);
                }
            });
        }
    }

    function wireInteractions() {
        interact('.rtpl-el').unset();
        interact('.rtpl-el')
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
                        const id = target.dataset.id;
                        const el = findEl(id);
                        if (! el) return;
                        const dxMm = (parseFloat(target.dataset.x) || 0) / PX_PER_MM;
                        const dyMm = (parseFloat(target.dataset.y) || 0) / PX_PER_MM;
                        el.x = Math.max(0, el.x + dxMm);
                        el.y = Math.max(0, el.y + dyMm);
                        target.dataset.x = 0;
                        target.dataset.y = 0;
                        target.style.transform = '';
                        target.style.left = (el.x * PX_PER_MM) + 'px';
                        target.style.top = (el.y * PX_PER_MM) + 'px';
                        posPatch(updateUrl(id), { x: el.x, y: el.y }).then(() => {
                            setStatus(labels.saved);
                            refreshPreview();
                        }).catch(() => setStatus(labels.saveFailed));
                        resizeSurfaceToFit();
                    },
                },
            })
            .resizable({
                edges: { left: false, right: true, bottom: true, top: false },
                listeners: {
                    move(event) {
                        const target = event.target;
                        target.style.width = event.rect.width + 'px';
                        target.style.height = event.rect.height + 'px';
                    },
                    end(event) {
                        const target = event.target;
                        const id = target.dataset.id;
                        const el = findEl(id);
                        if (! el) return;
                        el.width = event.rect.width / PX_PER_MM;
                        el.height = event.rect.height / PX_PER_MM;
                        posPatch(updateUrl(id), { width: el.width, height: el.height }).then(() => {
                            setStatus(labels.saved);
                            refreshPreview();
                        }).catch(() => setStatus(labels.saveFailed));
                        resizeSurfaceToFit();
                    },
                },
            })
            .on('tap', (event) => {
                selectElement(event.currentTarget.dataset.id);
            });
    }

    document.querySelectorAll('[data-add-type]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const type = btn.dataset.addType;
            const offset = elements.length * 3;
            try {
                const { data } = await posPost(storeUrl, { type, x: 5 + offset, y: 5 + offset });
                elements.push(data);
                selectedId = data.id;
                renderAll();
                refreshPreview();
            } catch (e) {
                setStatus(labels.saveFailed);
            }
        });
    });

    const paperSizeSelect = document.getElementById('rtpl-paper-size');
    if (paperSizeSelect) {
        const previousValue = paperSizeSelect.value;
        paperSizeSelect.addEventListener('change', async () => {
            const paper_size = paperSizeSelect.value;
            const trySave = async (force) => {
                try {
                    await posPatch(paperSizeUrl, { paper_size, force });
                    // Reloads so the mm-to-px scaling, printable guide, and
                    // per-element canvas rendering all pick up the new
                    // paper/printable width from a fresh page load rather
                    // than trying to rescale everything in place.
                    window.location.reload();
                } catch (e) {
                    if (e?.status === 409) {
                        const msg = e.message || 'Some elements would print past the new paper\'s edge.';
                        if (window.confirm(msg + '\n\nSwitch anyway? You\'ll need to fix those elements afterward.')) {
                            await trySave(true);
                        } else {
                            paperSizeSelect.value = previousValue;
                        }
                    } else {
                        window.alert('Could not change paper size — check your connection and try again.');
                        paperSizeSelect.value = previousValue;
                    }
                }
            };
            await trySave(false);
        });
    }

    renderAll();
}

document.addEventListener('DOMContentLoaded', init);
