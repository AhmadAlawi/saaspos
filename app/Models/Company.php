<?php

namespace App\Models;

use App\Models\Concerns\MasksDemoEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The single company record (one install = one company). Holds identity,
 * address, logo, and base configuration. Created by the installer; edited
 * via Settings → Company profile.
 */
class Company extends Model
{
    use MasksDemoEmail;

    protected $table = 'company';

    protected $fillable = [
        'name', 'legal_name', 'tax_registration_number',
        'email', 'phone', 'website',
        'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country_code',
        'app_name',
        'logo_path', 'app_logo_path', 'app_logo_dark_path', 'app_logo_half_path', 'app_logo_half_dark_path',
        'brand_color', 'brand_text_color', 'favicon_path', 'theme_default', 'footer_text',
        'privacy_policy', 'terms_of_service',
        'timezone', 'date_format', 'time_format',
        'receipt_paper_size', 'receipt_show_logo', 'receipt_show_customer', 'receipt_show_cashier',
        'receipt_show_tax_breakdown', 'receipt_show_barcode', 'receipt_show_qr',
        'receipt_show_sku', 'receipt_show_hsn', 'receipt_show_hsn_summary',
        'receipt_header', 'receipt_footer', 'receipt_return_policy',
        'cashier_settings', 'scale_settings',
        'sale_number_format', 'hold_number_format', 'refund_number_format',
        'mail_driver', 'mail_host', 'mail_port', 'mail_username', 'mail_password',
        'mail_encryption', 'mail_from_address', 'mail_from_name',
        'backup_enabled', 'backup_frequency', 'backup_retention_days', 'backup_target_disk',
        'backup_include_uploads', 'backup_email_enabled', 'backup_last_run_at',
        'update_channel', 'update_auto_check', 'update_auto_install', 'update_last_checked_at',
        'update_available_version', 'update_available_release', 'update_pinned_version',
        'update_skipped_versions', 'update_last_feed_error',
        'license_status', 'license_type', 'license_buyer_name', 'license_buyer_email',
        'license_expires_at', 'license_support_until', 'license_last_checked_at', 'license_last_error',
        'plan_code', 'seat_limit', 'seats_used_cache', 'features', 'license_grace_until',
        'industry', 'industries_enabled',
        'base_currency_code', 'fiscal_year_start_month',
        'tax_registered', 'composition_scheme_enabled', 'composition_rate_percent',
        'auto_apply_markup_on_receive',
        'block_expired_batch_sale',
        'dashboard_setup_dismissed',
        'dashboard_setup_celebrated',
    ];

    protected function casts(): array
    {
        return [
            'industries_enabled'          => 'array',
            'tax_registered'              => 'boolean',
            'composition_scheme_enabled'  => 'boolean',
            'composition_rate_percent'    => 'decimal:4',
            'fiscal_year_start_month'     => 'integer',
            'receipt_show_logo'           => 'boolean',
            'receipt_show_customer'       => 'boolean',
            'receipt_show_cashier'        => 'boolean',
            'receipt_show_tax_breakdown'  => 'boolean',
            'receipt_show_barcode'        => 'boolean',
            'receipt_show_qr'             => 'boolean',
            'receipt_show_sku'            => 'boolean',
            'receipt_show_hsn'            => 'boolean',
            'receipt_show_hsn_summary'    => 'boolean',
            'mail_password'               => \App\Casts\SafeEncrypted::class,
            'mail_port'                   => 'integer',
            'dashboard_setup_dismissed'   => 'boolean',
            'dashboard_setup_celebrated'  => 'boolean',
            'backup_enabled'              => 'boolean',
            'backup_include_uploads'      => 'boolean',
            'backup_email_enabled'        => 'boolean',
            'backup_retention_days'       => 'integer',
            'backup_last_run_at'          => 'datetime',
            'update_auto_check'           => 'boolean',
            'update_auto_install'         => 'boolean',
            'update_last_checked_at'      => 'datetime',
            'update_available_release'    => 'array',
            'update_skipped_versions'     => 'array',
            'cashier_settings'            => 'array',
            'scale_settings'              => 'array',
            'license_expires_at'          => 'datetime',
            'license_support_until'       => 'datetime',
            'license_last_checked_at'     => 'datetime',
            'seat_limit'                  => 'integer',
            'seats_used_cache'            => 'integer',
            'features'                    => 'array',
            'license_grace_until'         => 'datetime',
            'auto_apply_markup_on_receive' => 'boolean',
            'block_expired_batch_sale'    => 'boolean',
        ];
    }

    /**
     * Cashier (POS) UI preferences with defaults filled in.
     *
     * Keep the surface small — every key here is a knob exposed on the
     * Cashier Settings page. Adding one means: (a) bump this default
     * map, (b) extend the request rules + form, (c) consume it from the
     * cashier-page Alpine factory or the Blade template.
     *
     * @return array{
     *     layout: string,
     *     tile_size: string,
     *     theme_default: string,
     *     show_tax_line: bool,
     *     show_from_prefix: bool,
     *     show_quick_picks: bool,
     *     sound_on_add: bool,
     *     default_category: string
     * }
     */
    public function cashier(): array
    {
        $stored = is_array($this->cashier_settings) ? $this->cashier_settings : [];
        return array_replace([
            'layout'           => 'focus',          // 'focus' (default) | 'beam' (cart right) | 'lane' (cart left) | 'counter'
            'tile_size'        => 'comfortable',   // 'compact' | 'comfortable' | 'spacious'
            'theme_default'    => 'auto',          // 'auto' | 'light' | 'dark' — overrides company.theme_default just for /cashier
            'show_tax_line'    => true,
            'show_from_prefix' => true,
            'show_quick_picks' => false,
            'sound_on_add'     => true,
            'default_category' => 'all',
            // Checkout stock policy: when true, sales may take stock below
            // zero (backorders / overselling) — but only for users holding
            // `sales.oversell`. Enforced server-side in CompleteSale.
            'allow_negative_stock' => false,
        ], $stored);
    }

    /**
     * Weighing-scale barcode template with defaults filled in.
     *
     * Scale-printed barcodes embed a PLU (item code) and a measured value
     * (weight or total price) inside an EAN-13/UPC-A. The layout differs by
     * scale brand, so every field is configurable — the cashier decodes a
     * scanned barcode positionally:
     *
     *   [prefix][plu(plu_length)][skip(value_offset)][value(value_length)]…
     *
     * The trailing digits (EAN check, internal price check) are ignored —
     * the hardware scanner already validates the EAN-13 check digit.
     *
     * @return array{
     *     enabled: bool,
     *     prefix: string,
     *     plu_length: int,
     *     value_offset: int,
     *     value_length: int,
     *     value_decimals: int,
     *     embed_type: string
     * }
     */
    public function scale(): array
    {
        $stored = is_array($this->scale_settings) ? $this->scale_settings : [];
        $merged = array_replace([
            'enabled'        => false,
            'prefix'         => '2',       // EAN-13 in-store flag digit(s)
            'plu_length'     => 5,         // digits of the item/PLU code
            'value_offset'   => 0,         // digits between PLU and value (internal check)
            'value_length'   => 5,         // digits of the embedded value
            'value_decimals' => 3,         // implied decimals (weight: 3 = grams→kg)
            'embed_type'     => 'weight',  // 'weight' | 'price'
        ], $stored);

        // Coerce the numeric knobs so a stray string from old JSON can't
        // poison the cashier's positional slicing.
        $merged['enabled']        = (bool) $merged['enabled'];
        $merged['prefix']         = (string) $merged['prefix'];
        $merged['plu_length']     = (int) $merged['plu_length'];
        $merged['value_offset']   = (int) $merged['value_offset'];
        $merged['value_length']   = (int) $merged['value_length'];
        $merged['value_decimals'] = (int) $merged['value_decimals'];
        $merged['embed_type']     = $merged['embed_type'] === 'price' ? 'price' : 'weight';

        return $merged;
    }

    /**
     * Resolved number-format template for the given number type.
     * Falls back to the system default when the admin hasn't configured
     * one — both produce the same output for the default `MAIN` store
     * so existing tests / receipts stay stable until an admin opts in.
     *
     * @param 'sale'|'hold'|'refund' $type
     */
    public function numberFormat(string $type): string
    {
        $defaults = [
            'sale'   => 'SALE-{store}-{Ym}-{seq:04}',
            'hold'   => 'HOLD-{store}-{Ym}-{seq:04}',
            'refund' => 'REFUND-{store}-{Ym}-{seq:04}',
        ];

        $stored = match ($type) {
            'sale'   => $this->sale_number_format,
            'hold'   => $this->hold_number_format,
            'refund' => $this->refund_number_format,
            default  => null,
        };

        return $stored !== null && trim((string) $stored) !== ''
            ? (string) $stored
            : ($defaults[$type] ?? $defaults['sale']);
    }

    /** The single company row (the installer guarantees one exists). */
    public static function current(): ?self
    {
        return static::query()->first();
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->favicon_path ? Storage::url($this->favicon_path) : null;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }

    public function getAppLogoUrlAttribute(): ?string
    {
        return $this->app_logo_path ? Storage::url($this->app_logo_path) : null;
    }

    public function getAppLogoDarkUrlAttribute(): ?string
    {
        return $this->app_logo_dark_path ? Storage::url($this->app_logo_dark_path) : null;
    }

    public function getAppLogoHalfUrlAttribute(): ?string
    {
        return $this->app_logo_half_path ? Storage::url($this->app_logo_half_path) : null;
    }

    public function getAppLogoHalfDarkUrlAttribute(): ?string
    {
        return $this->app_logo_half_dark_path ? Storage::url($this->app_logo_half_dark_path) : null;
    }

    /** Custom app name; falls back to config('app.name') if not set. */
    public function getDisplayAppNameAttribute(): string
    {
        return $this->app_name ?: config('app.name');
    }
}
