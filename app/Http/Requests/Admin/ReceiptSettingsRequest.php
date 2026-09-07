<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /admin/settings/receipt.
 */
class ReceiptSettingsRequest extends FormRequest
{
    public const PAPER_SIZES = ['58mm', '80mm', 'a4'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'receipt_paper_size'         => ['required', Rule::in(self::PAPER_SIZES)],
            'receipt_show_logo'          => ['sometimes', 'boolean'],
            'receipt_show_customer'      => ['sometimes', 'boolean'],
            'receipt_show_cashier'       => ['sometimes', 'boolean'],
            'receipt_show_tax_breakdown' => ['sometimes', 'boolean'],
            'receipt_show_barcode'       => ['sometimes', 'boolean'],
            'receipt_show_qr'            => ['sometimes', 'boolean'],
            'receipt_show_sku'           => ['sometimes', 'boolean'],
            'receipt_show_hsn'           => ['sometimes', 'boolean'],
            'receipt_show_hsn_summary'   => ['sometimes', 'boolean'],
            'receipt_header'             => ['nullable', 'string', 'max:500'],
            'receipt_footer'             => ['nullable', 'string', 'max:500'],
            'receipt_return_policy'      => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, mixed> */
    public function receiptData(): array
    {
        return [
            'receipt_paper_size'         => $this->input('receipt_paper_size'),
            'receipt_show_logo'          => $this->boolean('receipt_show_logo'),
            'receipt_show_customer'      => $this->boolean('receipt_show_customer'),
            'receipt_show_cashier'       => $this->boolean('receipt_show_cashier'),
            'receipt_show_tax_breakdown' => $this->boolean('receipt_show_tax_breakdown'),
            'receipt_show_barcode'       => $this->boolean('receipt_show_barcode'),
            'receipt_show_qr'            => $this->boolean('receipt_show_qr'),
            'receipt_show_sku'           => $this->boolean('receipt_show_sku'),
            'receipt_show_hsn'           => $this->boolean('receipt_show_hsn'),
            'receipt_show_hsn_summary'   => $this->boolean('receipt_show_hsn_summary'),
            'receipt_header'             => $this->input('receipt_header') ?: null,
            'receipt_footer'             => $this->input('receipt_footer') ?: null,
            'receipt_return_policy'      => $this->input('receipt_return_policy') ?: null,
        ];
    }
}
