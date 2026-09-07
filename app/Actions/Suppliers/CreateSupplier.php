<?php

namespace App\Actions\Suppliers;

use App\Actions\Concerns\FreesSoftDeletedUnique;
use App\Models\Supplier;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Persist a new supplier. The `code` is auto-generated when blank;
 * phone is normalised to digits-only with optional leading `+`.
 *
 * Hooks:
 *   - filter `supplier.fillable`      → adjust the input array
 *   - action `supplier.before_create` → ($data)
 *   - action `supplier.after_create`  → ($supplier)
 *
 * @param array<string, mixed> $data
 */
class CreateSupplier
{
    use FreesSoftDeletedUnique;

    public function __construct(private GenerateSupplierCode $generateCode) {}

    public function __invoke(array $data, ?User $creator = null): Supplier
    {
        $data = apply_filters('supplier.fillable', $data);
        $data['phone'] = PhoneNormalizer::normalize($data['phone'] ?? null);
        $data['email'] = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        $data['email'] = $data['email'] === '' ? null : $data['email'];

        do_action('supplier.before_create', $data);

        return DB::transaction(function () use ($data, $creator) {
            $code = trim((string) ($data['code'] ?? ''));
            $data['code'] = $code !== '' ? $code : ($this->generateCode)();

            if ($creator) {
                $data['created_by'] = $creator->id;
                $data['updated_by'] = $creator->id;
            }

            // Free a deleted supplier's code so the unique index doesn't 1062.
            $this->freeSoftDeletedUnique(Supplier::class, ['code' => $data['code']]);

            $supplier = Supplier::create($data);

            do_action('supplier.after_create', $supplier);

            return $supplier;
        });
    }
}
