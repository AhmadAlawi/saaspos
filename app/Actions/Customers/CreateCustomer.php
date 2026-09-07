<?php

namespace App\Actions\Customers;

use App\Actions\Concerns\FreesSoftDeletedUnique;
use App\Models\Customer;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Persist a new customer + the address rows passed alongside it. The
 * `code` is auto-generated when blank; phone and whatsapp_phone are
 * normalised to digits-only with optional leading `+`. Exactly one
 * address may be flagged as default; if multiple come in flagged, only
 * the first one wins.
 *
 * Hooks:
 *   - filter `customer.fillable`     → adjust the input array
 *   - action `customer.before_create`→ ($data)
 *   - action `customer.after_create` → ($customer)
 *
 * @param array<string, mixed> $data
 * @param array<int, array<string, mixed>> $addresses
 */
class CreateCustomer
{
    use FreesSoftDeletedUnique;

    public function __construct(private GenerateCustomerCode $generateCode) {}

    public function __invoke(array $data, array $addresses, ?User $creator = null): Customer
    {
        $data = apply_filters('customer.fillable', $data);
        $data['phone']          = PhoneNormalizer::normalize($data['phone'] ?? null);
        $data['whatsapp_phone'] = PhoneNormalizer::normalize($data['whatsapp_phone'] ?? null);
        $data['email']          = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        $data['email']          = $data['email'] === '' ? null : $data['email'];

        do_action('customer.before_create', $data);

        return DB::transaction(function () use ($data, $addresses, $creator) {
            $code = trim((string) ($data['code'] ?? ''));
            $data['code'] = $code !== '' ? $code : ($this->generateCode)();

            if ($creator) {
                $data['created_by'] = $creator->id;
                $data['updated_by'] = $creator->id;
            }
            if (! isset($data['first_store_id']) || $data['first_store_id'] === null) {
                $data['first_store_id'] = current_store_id() ?: default_store_id();
            }

            // Free a deleted customer's unique values (code / email / phone)
            // so the DB unique indexes don't 1062 when re-adding someone.
            $this->freeSoftDeletedUnique(Customer::class, [
                'code'           => $data['code'] ?? null,
                'email'          => $data['email'] ?? null,
                'phone'          => $data['phone'] ?? null,
                'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
            ]);

            $customer = Customer::create($data);

            $this->syncAddresses($customer, $addresses);

            do_action('customer.after_create', $customer);

            return $customer;
        });
    }

    /** @param array<int, array<string, mixed>> $addresses */
    private function syncAddresses(Customer $customer, array $addresses): void
    {
        // Drop completely-empty rows.
        $rows = array_values(array_filter($addresses, fn ($a) => $this->hasAnyContent($a)));
        if ($rows === []) {
            return;
        }

        $defaultSeen = false;
        foreach ($rows as $row) {
            $isDefault = ! $defaultSeen && (bool) ($row['is_default'] ?? false);
            if ($isDefault) {
                $defaultSeen = true;
            }
            $customer->addresses()->create([
                'label'        => $row['label']        ?? null,
                'line1'        => $row['line1']        ?? null,
                'line2'        => $row['line2']        ?? null,
                'city'         => $row['city']         ?? null,
                'state'        => $row['state']        ?? null,
                'postal_code'  => $row['postal_code']  ?? null,
                'country_code' => isset($row['country_code']) ? strtoupper(substr((string) $row['country_code'], 0, 2)) : null,
                'landmark'     => $row['landmark']     ?? null,
                'is_default'   => $isDefault,
            ]);
        }

        // Respect the user's choice: if no row was explicitly flagged
        // default, leave them all non-default. `Customer::defaultAddress()`
        // returns null in that case and downstream consumers already
        // handle null. Previously we force-promoted the first address,
        // which surprised users who saw "default" on a row they hadn't
        // ticked.
    }

    private function hasAnyContent(array $a): bool
    {
        foreach (['line1', 'line2', 'city', 'state', 'postal_code', 'country_code', 'landmark', 'label'] as $k) {
            if (! empty(trim((string) ($a[$k] ?? '')))) {
                return true;
            }
        }
        return false;
    }
}
