<?php

namespace App\Actions\Suppliers;

use App\Models\Supplier;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Update an existing supplier. The derived `outstanding_balance` is
 * not writable here — only the supplier-payment / purchase-receipt
 * actions move it. Stripped from the payload if present.
 *
 * Hooks:
 *   - filter `supplier.fillable`      → adjust the input array
 *   - action `supplier.before_update` → ($supplier, $data)
 *   - action `supplier.after_update`  → ($supplier)
 *
 * @param array<string, mixed> $data
 */
class UpdateSupplier
{
    public function __invoke(Supplier $supplier, array $data, ?User $updater = null): Supplier
    {
        $data = apply_filters('supplier.fillable', $data, $supplier);

        unset($data['outstanding_balance']);

        if (array_key_exists('phone', $data)) {
            $data['phone'] = PhoneNormalizer::normalize($data['phone']);
        }
        if (array_key_exists('email', $data)) {
            $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
            $data['email'] = $email === '' ? null : $email;
        }

        if ($updater) {
            $data['updated_by'] = $updater->id;
        }

        do_action('supplier.before_update', $supplier, $data);

        return DB::transaction(function () use ($supplier, $data) {
            $supplier->update($data);

            do_action('supplier.after_update', $supplier);

            return $supplier->refresh();
        });
    }
}
