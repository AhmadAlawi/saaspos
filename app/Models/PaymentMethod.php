<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Payment method — cash / bank transfer / cheque / UPI / card etc.
 * Configured by the shop owner once, consumed by sales (POS checkout)
 * and supplier-payment flows.
 *
 * `requires_reference` drives a "Reference number" input that's
 * conditionally enforced (cheque #, UPI txn ID, card auth code).
 * `opens_cash_drawer` is a cashier-side concern — irrelevant here.
 *
 * This slice (supplier-payments) is the first consumer; the cashier
 * checkout slice will share the same model.
 */
class PaymentMethod extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'provider',
        'icon', 'color', 'provider_credentials',
        'requires_reference', 'opens_cash_drawer',
        'accounting_account_id', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'provider_credentials' => 'array',
            'requires_reference'   => 'boolean',
            'opens_cash_drawer'    => 'boolean',
            'is_active'            => 'boolean',
            'sort_order'           => 'integer',
        ];
    }

    /**
     * Values of `provider` that mean "no online gateway behind this method".
     *
     * The column DEFAULTS to the string `'none'` (not NULL), and the seeder
     * writes `'none'` explicitly — but rows created by hand can hold NULL or
     * ''. Anything asking "is this a gateway?" must accept all three, or a
     * plain Cash / UPI method silently fails to match.
     */
    public const NO_PROVIDER = [null, '', 'none'];

    /** @param Builder<PaymentMethod> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /**
     * Methods NOT backed by an online gateway — cash, cheque, bank transfer, a
     * static UPI QR. These are rung up by hand; gateway-backed methods go
     * through the QR-chooser and are never tendered directly.
     *
     * @param Builder<PaymentMethod> $q
     */
    public function scopeWithoutGateway(Builder $q): void
    {
        $q->where(fn ($w) => $w->whereNull('provider')->orWhereIn('provider', ['', 'none']));
    }

    /** True when this method hands off to an online payment gateway. */
    public function isGatewayBacked(): bool
    {
        return ! \in_array($this->provider, self::NO_PROVIDER, true);
    }

    /** The merchant's UPI Virtual Payment Address, when one is saved. */
    public function upiVpa(): ?string
    {
        $vpa = ($this->provider_credentials ?? [])['vpa'] ?? null;

        return \is_string($vpa) && $vpa !== '' ? $vpa : null;
    }
}
