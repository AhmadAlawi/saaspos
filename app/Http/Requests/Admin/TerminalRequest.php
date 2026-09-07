<?php

namespace App\Http\Requests\Admin;

use App\Models\Terminal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/terminals (create) and PATCH /admin/terminals/{id}.
 * Terminal `code` is unique within its store (two stores can both have a
 * "POS-1"); the AJAX status toggle reuses this request, so `code`/`name`
 * stay `sometimes`-friendly via persistedAttributes().
 *
 * Terminals are managed storewise: a new terminal always belongs to the
 * active store, so `store_id` is injected server-side (never trusted from
 * the client) and a terminal's store is immutable once created.
 */
class TerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Force a new terminal onto the active store, ignoring any client-sent
     * `store_id`. Edits carry no store_id (the field is gone and the store
     * is immutable), so we leave those untouched.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->route('terminal')) {
            $this->merge(['store_id' => current_store_id() ?: default_store_id()]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $terminal = $this->route('terminal');
        $id       = $terminal instanceof Terminal ? $terminal->id : null;

        // The store a unique-code check scopes to: the submitted store on
        // create, or the terminal's existing store on edit/toggle (the
        // toggle PATCH doesn't resend store_id).
        $storeId = (int) ($this->input('store_id')
            ?: ($terminal instanceof Terminal ? $terminal->store_id : 0));

        return [
            'store_id' => [
                $id ? 'sometimes' : 'required',
                'integer',
                Rule::exists('stores', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('terminals', 'code')
                    ->where(fn ($q) => $q->where('store_id', $storeId))
                    ->ignore($id),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'receipt_template_id' => ['nullable', 'integer', 'exists:receipt_templates,id'],

            // Hardware config (only sent by the full editor, never by the
            // status-toggle PATCH — see persistedAttributes()).
            'printer_mode'        => ['sometimes', 'in:browser_print,webusb,network,none'],
            'paper_width'         => ['sometimes', 'in:58mm,80mm,a4'],
            'cut_paper'           => ['sometimes', 'boolean'],
            'open_drawer_on_cash' => ['sometimes', 'boolean'],
            'drawer_pin'          => ['sometimes', 'in:2,5'],
            'webusb_vendor_id'    => ['nullable', 'string', 'regex:/^0x[0-9a-fA-F]{1,4}$/'],
            'webusb_product_id'   => ['nullable', 'string', 'regex:/^0x[0-9a-fA-F]{1,4}$/'],

            // Customer-facing display (CFD) config — only sent by the full
            // editor (always carries `cfd_enabled` as a hidden 0 + checkbox 1),
            // never by the status-toggle PATCH — see persistedAttributes().
            'cfd_enabled'  => ['sometimes', 'boolean'],
            'cfd_welcome'  => ['nullable', 'string', 'max:120'],
            'cfd_thankyou' => ['nullable', 'string', 'max:120'],
            // Where the display lives (Slice 4): a second monitor on this
            // machine (BroadcastChannel) vs a separate tablet (server relay
            // + polling). See docs/features/customer-display.md §5.
            'cfd_transport' => ['sometimes', 'in:same_machine,separate_device'],
            // Attract-media slideshow (Slice 3): an ordered list of stored
            // image paths (uploaded via terminals.cfd-media) + how long each
            // slide shows. Count-capped so a runaway list can't bloat the JSON.
            'cfd_media'       => ['sometimes', 'array', 'max:12'],
            'cfd_media.*'     => ['string', 'max:255'],
            'attract_seconds' => ['sometimes', 'integer', 'between:3,30'],
            // Mirror the UPI payment QR onto the customer display so the
            // shopper can scan it from their own screen. See §6.
            'cfd_show_upi_qr' => ['sometimes', 'boolean'],

            // Self-ordering kiosk config — only sent by the full editor
            // (always carries `station_type` + a hidden `kiosk_enabled` 0 +
            // checkbox 1), never by the status-toggle PATCH. See
            // docs/features/kiosk-self-ordering.md §7.
            'station_type'       => ['sometimes', 'in:register,kiosk'],
            'kiosk_enabled'      => ['sometimes', 'boolean'],
            'kiosk_mode'         => ['sometimes', 'in:checkout,order,both'],
            'kiosk_welcome'      => ['nullable', 'string', 'max:120'],
            'kiosk_thankyou'     => ['nullable', 'string', 'max:120'],
            'kiosk_idle_timeout' => ['sometimes', 'integer', 'between:20,300'],
            // Slice 5 config polish.
            'kiosk_customer'        => ['sometimes', 'in:off,optional,required'],
            'kiosk_categories'      => ['sometimes', 'array', 'max:200'],
            'kiosk_categories.*'    => ['integer'],
            // Gateways the kiosk's "Pay now" may charge through. Empty = every
            // configured gateway (the shopper picks after scanning).
            'kiosk_payment_methods'   => ['sometimes', 'array', 'max:20'],
            'kiosk_payment_methods.*' => ['integer', 'exists:payment_methods,id'],
            // Static-QR (UPI) method offered as "scan and pay at the machine".
            'kiosk_upi_method'      => ['sometimes', 'nullable', 'integer', 'exists:payment_methods,id'],
            'kiosk_sound'           => ['sometimes', 'boolean'],
            'kiosk_thankyou_seconds' => ['sometimes', 'integer', 'between:5,120'],
            // Pickup-code prefix ("K" → K001). Letters/digits so it stays
            // shoutable across a counter and safe on a thermal printer.
            'kiosk_pickup_prefix'   => ['sometimes', 'nullable', 'string', 'max:4', 'regex:/^[A-Za-z0-9]*$/'],
            'kiosk_allow_note'      => ['sometimes', 'boolean'],
            'kiosk_pin_required'    => ['sometimes', 'boolean'],
            'kiosk_media'           => ['sometimes', 'array', 'max:12'],
            'kiosk_media.*'         => ['string', 'max:255'],
            'kiosk_attract_seconds' => ['sometimes', 'integer', 'between:3,30'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex'  => __('terminals.errors.code_format'),
            'code.unique' => __('terminals.errors.code_taken'),
        ];
    }

    /**
     * Map the validated payload to persisted columns. A blank code is
     * auto-derived from the name (uppercased, hyphenated) so the operator
     * never has to invent one.
     *
     * @return array<string, mixed>
     */
    public function persistedAttributes(): array
    {
        $data = $this->validated();

        // A terminal's store is set once at creation and never moved — drop
        // any store_id that rode in on an edit/toggle payload.
        if ($this->route('terminal')) {
            unset($data['store_id']);
        }

        if (empty($data['code'])) {
            $base = Str::upper(Str::slug($data['name'] ?? 'pos')) ?: 'POS';
            $data['code'] = Str::limit($base, 26, '').'-'.Str::upper(Str::random(4));
        } else {
            $data['code'] = trim($data['code']);
        }

        $data['is_active'] = $this->boolean('is_active', true);

        // Guarded like printer_mode/cfd_enabled/station_type below — the
        // AJAX status-toggle PATCH omits this field entirely, so a plain
        // toggle must never clear a saved assignment.
        if ($this->has('receipt_template_id')) {
            $data['receipt_template_id'] = $this->input('receipt_template_id') ?: null;
        } else {
            unset($data['receipt_template_id']);
        }

        // Strip the flat hardware fields back out of the column payload and
        // fold them into the `default_printer_config` JSON — but ONLY when
        // the editor actually sent them. The status-toggle PATCH omits
        // `printer_mode`, so a toggle never clobbers a saved hardware config.
        foreach (['printer_mode', 'paper_width', 'cut_paper', 'open_drawer_on_cash', 'drawer_pin', 'webusb_vendor_id', 'webusb_product_id'] as $k) {
            unset($data[$k]);
        }

        if ($this->has('printer_mode')) {
            $data['default_printer_config'] = $this->buildPrinterConfig();
        }

        // Fold the flat CFD fields into the `cfd_config` JSON — ONLY when the
        // editor sent them (the status-toggle PATCH omits `cfd_enabled`, so a
        // toggle never wipes a saved customer-display config).
        foreach (['cfd_enabled', 'cfd_welcome', 'cfd_thankyou', 'cfd_media', 'attract_seconds', 'cfd_transport'] as $k) {
            unset($data[$k]);
        }

        if ($this->has('cfd_enabled')) {
            $data['cfd_config'] = $this->buildCfdConfig();
        }

        // Fold the flat kiosk fields into `type` + `kiosk_config` — ONLY when
        // the editor sent them (the status-toggle PATCH omits `station_type`,
        // so a toggle never wipes a saved kiosk config).
        foreach (['station_type', 'kiosk_enabled', 'kiosk_mode', 'kiosk_welcome', 'kiosk_thankyou', 'kiosk_idle_timeout',
                  'kiosk_customer', 'kiosk_categories', 'kiosk_payment_methods', 'kiosk_upi_method',
                  'kiosk_sound', 'kiosk_thankyou_seconds', 'kiosk_pickup_prefix', 'kiosk_allow_note',
                  'kiosk_pin_required', 'kiosk_media', 'kiosk_attract_seconds'] as $k) {
            unset($data[$k]);
        }

        if ($this->has('station_type')) {
            $data['type'] = $this->input('station_type') === 'kiosk' ? 'kiosk' : 'register';
            $data['kiosk_config'] = $this->buildKioskConfig();
        }

        return $data;
    }

    /**
     * Build the `kiosk_config` JSON for the self-ordering kiosk. Blank
     * welcome / thank-you copy is stored as null so the kiosk falls back to
     * the translated defaults. See docs/features/kiosk-self-ordering.md §7.
     *
     * @return array<string, mixed>
     */
    private function buildKioskConfig(): array
    {
        $welcome  = trim((string) $this->input('kiosk_welcome'));
        $thankyou = trim((string) $this->input('kiosk_thankyou'));

        $mode = $this->input('kiosk_mode');
        $mode = in_array($mode, ['checkout', 'order', 'both'], true) ? $mode : 'checkout';

        $idle = (int) $this->input('kiosk_idle_timeout', 60);
        $idle = max(20, min(300, $idle));

        $customer = $this->input('kiosk_customer');
        $customer = in_array($customer, ['off', 'optional', 'required'], true) ? $customer : 'off';

        // Whitelisted category ids — deduped positive ints. Empty = all.
        $categories = collect($this->input('kiosk_categories', []))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        // Gateways "Pay now" may charge through. Empty = every configured
        // gateway; exactly one skips the chooser on the scan page.
        $methods = collect($this->input('kiosk_payment_methods', []))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        // Static-QR (UPI) method. Only a non-gateway method carrying a `vpa`
        // can render a `upi://pay?…` deep link, so anything else is dropped
        // rather than saved as a setting that would silently never appear.
        $upiId  = (int) $this->input('kiosk_upi_method', 0);
        $upi    = $upiId > 0 ? \App\Models\PaymentMethod::query()->find($upiId) : null;
        $upiOk  = $upi
            && ! $upi->isGatewayBacked()
            && $upi->is_active
            && $upi->upiVpa() !== null;

        // Attract media — keep only our own upload folder (shared with the
        // CFD), preserve order, cap the count.
        $media = collect($this->input('kiosk_media', []))
            ->filter(fn ($p) => is_string($p) && str_starts_with($p, 'cfd/'))
            ->take(12)->values()->all();

        $seconds = (int) $this->input('kiosk_attract_seconds', 8);
        $seconds = max(3, min(30, $seconds));

        return [
            'enabled'                 => $this->boolean('kiosk_enabled'),
            'mode'                    => $mode,
            'welcome_text'            => $welcome !== '' ? $welcome : null,
            'thankyou_text'           => $thankyou !== '' ? $thankyou : null,
            'idle_timeout_seconds'    => $idle,
            'customer'                => $customer,
            'category_ids'            => $categories,
            'payment_method_ids'      => $methods,
            'upi_method_id'           => $upiOk ? $upiId : null,
            'sound_enabled'           => $this->boolean('kiosk_sound', true),
            'thankyou_seconds'        => max(5, min(120, (int) $this->input('kiosk_thankyou_seconds', 25))),
            'pickup_prefix'           => $this->pickupPrefix(),
            'allow_order_note'        => $this->boolean('kiosk_allow_note', true),
            'supervisor_pin_required' => $this->boolean('kiosk_pin_required'),
            'attract_media'           => $media,
            'attract_seconds'         => $seconds,
        ];
    }

    /**
     * Pickup-code prefix, normalised. Blank falls back to "K" rather than an
     * empty prefix, so a code is never a bare number the customer might
     * confuse with a quantity or a price.
     */
    private function pickupPrefix(): string
    {
        $raw   = strtoupper(trim((string) $this->input('kiosk_pickup_prefix', '')));
        $clean = preg_replace('/[^A-Z0-9]/', '', $raw) ?? '';

        return $clean !== '' ? substr($clean, 0, 4) : 'K';
    }

    /** @return array<string, mixed> */
    private function buildPrinterConfig(): array
    {
        $mode  = $this->input('printer_mode', 'browser_print');
        $paper = $this->input('paper_width', '80mm');
        $open  = $this->boolean('open_drawer_on_cash');

        $receipt = [
            'mode'                => $mode,
            'paper_width'         => $paper,
            'cut_paper'           => $this->boolean('cut_paper'),
            'open_drawer_on_cash' => $open,
            'drawer_pin'          => ((int) $this->input('drawer_pin')) === 5 ? 5 : 2,
        ];

        // Stash the paired device descriptor so the cashier's print bridge
        // can reconnect to the same printer without re-prompting.
        $vid = $this->input('webusb_vendor_id');
        $pid = $this->input('webusb_product_id');
        if ($mode === 'webusb' && $vid && $pid) {
            $receipt['webusb_device_descriptor'] = [
                'vendor_id'  => $vid,
                'product_id' => $pid,
            ];
        }

        return [
            'receipt_printer' => $receipt,
            'cash_drawer'     => ['mode' => $open ? 'via_receipt_printer' : 'none'],
        ];
    }

    /**
     * Build the `cfd_config` JSON for the customer-facing display. Blank
     * welcome / thank-you copy is stored as null so the display falls back
     * to the translated defaults (see docs/features/customer-display.md §6).
     *
     * @return array<string, mixed>
     */
    private function buildCfdConfig(): array
    {
        $welcome  = trim((string) $this->input('cfd_welcome'));
        $thankyou = trim((string) $this->input('cfd_thankyou'));

        // Keep only paths under our own `cfd/` upload folder so a crafted
        // payload can't smuggle an arbitrary path that later resolves to a
        // surprising URL. Order is preserved (it's the slideshow order).
        $media = collect($this->input('cfd_media', []))
            ->filter(fn ($p) => is_string($p) && str_starts_with($p, 'cfd/'))
            ->take(12)
            ->values()
            ->all();

        $seconds = (int) $this->input('attract_seconds', 8);
        $seconds = max(3, min(30, $seconds));

        $transport = $this->input('cfd_transport');
        $transport = in_array($transport, ['same_machine', 'separate_device'], true)
            ? $transport
            : 'same_machine';

        return [
            'enabled'         => $this->boolean('cfd_enabled'),
            'transport'       => $transport,
            'welcome_text'    => $welcome !== '' ? $welcome : null,
            'thankyou_text'   => $thankyou !== '' ? $thankyou : null,
            'attract_media'   => $media,
            'attract_seconds' => $seconds,
            'show_upi_qr'     => $this->boolean('cfd_show_upi_qr'),
        ];
    }
}
