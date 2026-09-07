<?php

namespace App\Http\Requests\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /kiosk/place — a customer's self-ordering submission.
 *
 * The kiosk sends only product_id + quantity per line (never a price — the
 * server resolves that authoritatively in {@see \App\Actions\Sales\PlaceKioskOrder}).
 */
class PlaceKioskOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items'               => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id'  => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'    => ['required', 'numeric', 'gt:0'],
            'items.*.notes'       => ['nullable', 'string', 'max:255'],
            'note'                => ['nullable', 'string', 'max:255'],
            'customer_id'         => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name'       => ['nullable', 'string', 'max:120'],
            'customer_phone'      => ['nullable', 'string', 'max:32'],
            'local_uuid'          => ['nullable', 'string', 'max:36'],
            // The shopper says they paid by scanning the static UPI QR at the
            // machine. Advisory: the controller checks it against the
            // terminal's configured method, and staff verify the transfer
            // before the order is settled at the counter.
            'payment_claim_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
        ];
    }
}
