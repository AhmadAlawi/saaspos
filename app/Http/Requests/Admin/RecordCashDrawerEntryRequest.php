<?php

namespace App\Http\Requests\Admin;

use App\Models\CashDrawerEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared request for the three cash-drawer routes — the `type` is
 * injected by the controller (path-based) so the same validator
 * handles pay-in, pay-out, and drawer-open-no-sale with branch-aware
 * rules.
 */
class RecordCashDrawerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $perm = match ($this->input('type')) {
            CashDrawerEntry::TYPE_PAY_IN              => 'cash_drawer.pay_in',
            CashDrawerEntry::TYPE_PAY_OUT             => 'cash_drawer.pay_out',
            CashDrawerEntry::TYPE_DRAWER_OPEN_NO_SALE => 'cash_drawer.open_no_sale',
            default                                   => null,
        };
        return $perm ? (bool) ($this->user()?->hasPermission($perm)) : false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = (string) $this->input('type');
        $isNoSale = $type === CashDrawerEntry::TYPE_DRAWER_OPEN_NO_SALE;

        return [
            'type'   => ['required', Rule::in([
                CashDrawerEntry::TYPE_PAY_IN,
                CashDrawerEntry::TYPE_PAY_OUT,
                CashDrawerEntry::TYPE_DRAWER_OPEN_NO_SALE,
            ])],
            'amount' => $isNoSale
                ? ['nullable']
                : ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'amount' => __('cash_drawer.fields.amount'),
            'reason' => __('cash_drawer.fields.reason'),
        ];
    }
}
