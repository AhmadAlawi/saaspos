import { posPost } from '../lib/http.js';
import { KNOWN_PRINTER_FILTERS } from '../hardware/webusb-driver.js';

/**
 * Flatten a saved `receipt_printer` config block into the editor's form
 * fields (or sensible defaults when a terminal has no config yet).
 */
function configToForm(config) {
    const c = config || {};
    const d = c.webusb_device_descriptor || {};
    return {
        printer_mode:        c.mode || 'browser_print',
        paper_width:         c.paper_width || '80mm',
        cut_paper:           c.cut_paper !== false,
        open_drawer_on_cash: c.open_drawer_on_cash !== false,
        drawer_pin:          String(c.drawer_pin || 2),
        webusb_vendor_id:    d.vendor_id || '',
        webusb_product_id:   d.product_id || '',
        webusb_label:        (d.vendor_id && d.product_id) ? `${d.vendor_id}:${d.product_id}` : '',
    };
}

/** Flatten a saved `cfd_config` block into the editor's form fields. */
function cfdToForm(config) {
    const c = config || {};
    return {
        cfd_enabled:   !!c.enabled,
        cfd_transport: c.transport || 'same_machine',
        cfd_welcome:  c.welcome_text || '',
        cfd_thankyou: c.thankyou_text || '',
        attract_media:   Array.isArray(c.attract_media) ? c.attract_media : [],
        attract_seconds: c.attract_seconds || 8,
        cfd_show_upi_qr: !!c.show_upi_qr,
    };
}

/** Flatten `type` + `kiosk_config` into the editor's form fields. */
function kioskToForm(type, config) {
    const c = config || {};
    return {
        station_type:       type === 'kiosk' ? 'kiosk' : 'register',
        kiosk_enabled:      !!c.enabled,
        kiosk_mode:         ['checkout', 'order', 'both'].includes(c.mode) ? c.mode : 'checkout',
        kiosk_welcome:      c.welcome_text || '',
        kiosk_thankyou:     c.thankyou_text || '',
        kiosk_idle_timeout: c.idle_timeout_seconds || 60,
        kiosk_customer:        ['off', 'optional', 'required'].includes(c.customer) ? c.customer : 'off',
        kiosk_categories:      Array.isArray(c.category_ids) ? c.category_ids.map(String) : [],
        kiosk_payment_methods: Array.isArray(c.payment_method_ids) ? c.payment_method_ids.map(String) : [],
        kiosk_upi_method:      c.upi_method_id ? String(c.upi_method_id) : '',
        kiosk_sound:           c.sound_enabled !== false,
        kiosk_thankyou_seconds: c.thankyou_seconds || 25,
        kiosk_pickup_prefix:   c.pickup_prefix || 'K',
        kiosk_allow_note:      c.allow_order_note !== false,
        kiosk_pin_required:    !!c.supervisor_pin_required,
        kiosk_attract_media:   Array.isArray(c.attract_media) ? c.attract_media : [],
        kiosk_attract_seconds: c.attract_seconds || 8,
    };
}

/**
 * Alpine factory for the dedicated Terminal add/edit page.
 *
 * The form posts NORMALLY (no preventDefault) → the controller redirects
 * back to the list with a flash, exactly like the product editor. This
 * component only owns the reactive form state (conditional sections,
 * enable toggles), the AJAX media uploads + WebUSB pairing, and the submit
 * button's spinner. Validation errors bounce server-side and the admin
 * layout toasts them; `initial` is re-hydrated from `old()` so the form
 * (and its toggles) survive the round-trip.
 */
export function terminalForm({
    mediaUploadUrl = null,
    kioskUrl = null,
    kioskCategories = [],
    kioskPaymentMethods = [],
    initial = {},
} = {}) {
    return {
        form: {
            id:        initial.id ?? null,
            code:      initial.code ?? '',
            name:      initial.name ?? '',
            is_active: initial.is_active !== false,
            receipt_template_id: initial.receipt_template_id ?? '',
            ...configToForm(initial.config),
            ...cfdToForm(initial.cfd),
            ...kioskToForm(initial.type, initial.kiosk),
        },
        submitting: false,
        uploadingMedia: false,
        uploadingKioskMedia: false,
        kioskCategories,
        kioskPaymentMethods,

        get isEdit() { return !!this.form.id; },

        /** No gateway ticked = the shopper picks from every configured one
         *  after scanning; exactly one = the chooser step is skipped. */
        get kioskPaysAnyGateway() { return this.form.kiosk_payment_methods.length === 0; },
        get kioskSkipsChooser()   { return this.form.kiosk_payment_methods.length === 1; },

        /** Where the "Open kiosk" launcher points (this workstation must be
         *  bound to the kiosk terminal for it to inherit the config). */
        get kioskOpenUrl() { return kioskUrl || '#'; },

        /** Pair a WebUSB thermal printer — the browser remembers the grant
         *  per-origin; we store the descriptor so the print bridge reconnects. */
        async pairPrinter() {
            if (!navigator.usb) {
                this.$store.toasts.push({
                    type:    'warning',
                    message: 'WebUSB isn\'t supported in this browser. Use Chrome or Edge on desktop/Android.',
                });
                return;
            }
            try {
                const device = await navigator.usb.requestDevice({ filters: KNOWN_PRINTER_FILTERS });
                const hex = (n) => '0x' + n.toString(16).padStart(4, '0');
                this.form.webusb_vendor_id  = hex(device.vendorId);
                this.form.webusb_product_id = hex(device.productId);
                this.form.webusb_label = `${device.productName || 'Printer'} (${this.form.webusb_vendor_id}:${this.form.webusb_product_id})`;
            } catch (e) {
                // User dismissed the device picker — nothing to do.
            }
        },

        /* ── Customer-display attract media ─────────────────────── */
        pickCfdMedia() { this.$refs.cfdMediaInput?.click(); },

        async onCfdMediaPick(evt) {
            const files = Array.from(evt.target.files || []);
            evt.target.value = '';
            if (!files.length || !mediaUploadUrl) return;

            this.uploadingMedia = true;
            for (const file of files) {
                if (this.form.attract_media.length >= 12) break;
                const fd = new FormData();
                fd.append('image', file);
                try {
                    const { data } = await posPost(mediaUploadUrl, fd);
                    if (data?.path) this.form.attract_media.push({ path: data.path, url: data.url });
                } catch (e) {
                    this.$store.toasts.push({ type: 'error', message: e?.errors?.image?.[0] || e?.message || 'Upload failed.' });
                }
            }
            this.uploadingMedia = false;
        },

        removeCfdMedia(index) { this.form.attract_media.splice(index, 1); },

        /* ── Kiosk attract media (shares the CFD upload endpoint) ── */
        pickKioskMedia() { this.$refs.kioskMediaInput?.click(); },

        async onKioskMediaPick(evt) {
            const files = Array.from(evt.target.files || []);
            evt.target.value = '';
            if (!files.length || !mediaUploadUrl) return;

            this.uploadingKioskMedia = true;
            for (const file of files) {
                if (this.form.kiosk_attract_media.length >= 12) break;
                const fd = new FormData();
                fd.append('image', file);
                try {
                    const { data } = await posPost(mediaUploadUrl, fd);
                    if (data?.path) this.form.kiosk_attract_media.push({ path: data.path, url: data.url });
                } catch (e) {
                    this.$store.toasts.push({ type: 'error', message: e?.errors?.image?.[0] || e?.message || 'Upload failed.' });
                }
            }
            this.uploadingKioskMedia = false;
        },

        removeKioskMedia(index) { this.form.kiosk_attract_media.splice(index, 1); },
    };
}
