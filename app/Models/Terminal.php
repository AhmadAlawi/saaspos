<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A physical checkout station (till / workstation) inside a store. Each
 * terminal carries its own hardware configuration — receipt printer,
 * label printer, cash drawer, barcode scanner — stored as JSON on
 * `default_printer_config` / `label_printer_config`.
 *
 * H1 (this slice) ships the terminal entity + management CRUD + the
 * picker helpers ({@see current_terminal()}). The hardware-config
 * wizard that populates the JSON columns lands with the print-bridge
 * slice (docs/features/hardware.md §4).
 */
class Terminal extends Model
{
    protected $fillable = [
        'store_id',
        'code',
        'name',
        'type',
        'default_printer_config',
        'label_printer_config',
        'cfd_config',
        'kiosk_config',
        'receipt_template_id',
        'last_seen_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_printer_config' => 'array',
            'label_printer_config'   => 'array',
            'cfd_config'             => 'array',
            'kiosk_config'           => 'array',
            'last_seen_at'           => 'datetime',
            'is_active'              => 'boolean',
        ];
    }

    /**
     * Whether the customer-facing display is switched on for this terminal.
     * Defaults to off — an unconfigured terminal has no second screen.
     */
    public function cfdEnabled(): bool
    {
        return (bool) ($this->cfd_config['enabled'] ?? false);
    }

    /**
     * The CFD config shaped for the terminal editor: attract-media paths are
     * resolved to {path, url} so the form can show a preview while still
     * submitting the stable path. Mirrors the branding-logo path→url split.
     *
     * @return array<string, mixed>
     */
    public function cfdConfigForEditor(): array
    {
        $cfg = $this->cfd_config ?? [];

        $media = collect($cfg['attract_media'] ?? [])
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->map(fn ($p) => [
                'path' => $p,
                'url'  => \Illuminate\Support\Facades\Storage::disk('public')->url($p),
            ])
            ->values()
            ->all();

        return [
            'enabled'         => (bool) ($cfg['enabled'] ?? false),
            'transport'       => $cfg['transport'] ?? 'same_machine',
            'welcome_text'    => $cfg['welcome_text'] ?? '',
            'thankyou_text'   => $cfg['thankyou_text'] ?? '',
            'attract_media'   => $media,
            'attract_seconds' => (int) ($cfg['attract_seconds'] ?? 8),
            'show_upi_qr'     => (bool) ($cfg['show_upi_qr'] ?? false),
        ];
    }

    /** How this terminal's display is reached: same_machine | separate_device. */
    public function cfdTransport(): string
    {
        return $this->cfd_config['transport'] ?? 'same_machine';
    }

    /* ── Self-ordering kiosk ─────────────────────────────────────── */

    /** True when this station is a customer-operated self-ordering kiosk. */
    public function isKiosk(): bool
    {
        return $this->type === 'kiosk';
    }

    /**
     * Whether the kiosk experience is switched on. A terminal is only a
     * live kiosk when it's typed `kiosk` AND its config is enabled — so
     * flipping the type back to `register` (or disabling it) instantly
     * closes the kiosk without losing the saved copy.
     */
    public function kioskEnabled(): bool
    {
        return $this->isKiosk() && (bool) ($this->kiosk_config['enabled'] ?? false);
    }

    /**
     * Ordering flavour: `checkout` (pay at kiosk) | `order` (pay at counter)
     * | `both` (the shopper chooses at the kiosk which one to use).
     */
    public function kioskMode(): string
    {
        $mode = $this->kiosk_config['mode'] ?? 'checkout';

        return in_array($mode, ['checkout', 'order', 'both'], true) ? $mode : 'checkout';
    }

    /** How the kiosk handles the shopper's details: off | optional | required. */
    public function kioskCustomerMode(): string
    {
        $m = $this->kiosk_config['customer'] ?? 'off';

        return in_array($m, ['off', 'optional', 'required'], true) ? $m : 'off';
    }

    /**
     * Category ids the kiosk grid is limited to. Empty = show everything.
     *
     * @return array<int, int>
     */
    public function kioskCategoryIds(): array
    {
        return collect($this->kiosk_config['category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->filter()->values()->all();
    }

    public function kioskAllowNote(): bool
    {
        return (bool) ($this->kiosk_config['allow_order_note'] ?? true);
    }

    public function kioskPinRequired(): bool
    {
        return (bool) ($this->kiosk_config['supervisor_pin_required'] ?? false);
    }

    /** Play a confirmation blip when the shopper adds something to the cart. */
    public function kioskSoundEnabled(): bool
    {
        return (bool) ($this->kiosk_config['sound_enabled'] ?? true);
    }

    /**
     * Prefix on the pickup code the customer is called by ("K" → K001).
     * Merchants running two kiosks often want them distinguishable (A / B),
     * or a word in their own language. Sanitised to letters + digits so it
     * stays shoutable and printable.
     */
    public function kioskPickupPrefix(): string
    {
        $raw = strtoupper(trim((string) ($this->kiosk_config['pickup_prefix'] ?? '')));
        $raw = preg_replace('/[^A-Z0-9]/', '', $raw) ?? '';

        return $raw !== '' ? substr($raw, 0, 4) : 'K';
    }

    /**
     * How long the thank-you screen stays up before resetting to attract.
     * It carries the pickup code and the receipt QR, so it has to outlast the
     * time it takes a shopper to find their phone and scan.
     */
    public function kioskThankyouSeconds(): int
    {
        return max(5, min(120, (int) ($this->kiosk_config['thankyou_seconds'] ?? 25)));
    }

    /**
     * The provider-less payment method (a UPI account with a stored `vpa`)
     * offered as "scan and pay at the machine". NULL = not offered.
     *
     * Unlike a gateway, a static UPI QR has no callback, so paying this way
     * PLACES the order with a claim for staff to verify — it never completes
     * a sale. See docs/features/kiosk-self-ordering.md §5.
     */
    public function kioskUpiMethodId(): ?int
    {
        $id = (int) ($this->kiosk_config['upi_method_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Payment-method ids the kiosk's "Pay now" is allowed to charge through.
     * Empty = every configured gateway (the customer picks on the scan page).
     * Exactly one = the scan skips the chooser and lands on that gateway.
     *
     * @return array<int, int>
     */
    public function kioskPaymentMethodIds(): array
    {
        return collect($this->kiosk_config['payment_method_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Attract-media image URLs for the idle slideshow (stored as paths,
     * resolved for display — mirrors the CFD's path→url split).
     *
     * @return array<int, string>
     */
    public function kioskAttractMedia(): array
    {
        return collect($this->kiosk_config['attract_media'] ?? [])
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->map(fn ($p) => \Illuminate\Support\Facades\Storage::disk('public')->url($p))
            ->values()->all();
    }

    /**
     * The kiosk config shaped for the terminal editor — flat defaults so a
     * fresh terminal (no config yet) still populates the form cleanly.
     * Mirrors {@see cfdConfigForEditor()}.
     *
     * @return array<string, mixed>
     */
    public function kioskConfigForEditor(): array
    {
        $cfg  = $this->kiosk_config ?? [];
        $mode = $cfg['mode'] ?? 'checkout';
        $cust = $cfg['customer'] ?? 'off';

        $media = collect($cfg['attract_media'] ?? [])
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->map(fn ($p) => [
                'path' => $p,
                'url'  => \Illuminate\Support\Facades\Storage::disk('public')->url($p),
            ])
            ->values()->all();

        return [
            'enabled'                 => (bool) ($cfg['enabled'] ?? false),
            'mode'                    => in_array($mode, ['checkout', 'order', 'both'], true) ? $mode : 'checkout',
            'welcome_text'            => $cfg['welcome_text'] ?? '',
            'thankyou_text'           => $cfg['thankyou_text'] ?? '',
            'idle_timeout_seconds'    => (int) ($cfg['idle_timeout_seconds'] ?? 60),
            'customer'                => in_array($cust, ['off', 'optional', 'required'], true) ? $cust : 'off',
            'category_ids'            => $this->kioskCategoryIds(),
            'payment_method_ids'      => $this->kioskPaymentMethodIds(),
            'upi_method_id'           => $this->kioskUpiMethodId(),
            'sound_enabled'           => $this->kioskSoundEnabled(),
            'thankyou_seconds'        => $this->kioskThankyouSeconds(),
            'pickup_prefix'           => $this->kioskPickupPrefix(),
            'allow_order_note'        => (bool) ($cfg['allow_order_note'] ?? true),
            'supervisor_pin_required' => (bool) ($cfg['supervisor_pin_required'] ?? false),
            'attract_media'           => $media,
            'attract_seconds'         => (int) ($cfg['attract_seconds'] ?? 8),
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<ReceiptTemplate, $this> */
    public function receiptTemplate(): BelongsTo
    {
        return $this->belongsTo(ReceiptTemplate::class);
    }

    /** @param Builder<Terminal> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @param Builder<Terminal> $q */
    public function scopeForStore(Builder $q, int $storeId): void
    {
        $q->where('store_id', $storeId);
    }

    /** Newest first across every admin list / export. */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderByDesc('id');
    }
}
