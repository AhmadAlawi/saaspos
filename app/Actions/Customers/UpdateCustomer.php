<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Update an existing customer. Address rows are replaced wholesale on
 * each save — the editor is line-replace, simpler than per-row diff for
 * a typically-small address list.
 *
 * Derived balances (`outstanding_balance`, `store_credit_balance`,
 * `loyalty_points`) are NOT writable here — they're maintained by the
 * action layer (AddStoreCredit, EarnLoyaltyPoints, etc., once Sales
 * lands). Stripped from the payload if present.
 *
 * Hooks:
 *   - filter `customer.fillable`     → adjust the input array
 *   - action `customer.before_update`→ ($customer, $data)
 *   - action `customer.after_update` → ($customer)
 *
 * @param array<string, mixed> $data
 * @param array<int, array<string, mixed>> $addresses
 */
class UpdateCustomer
{
    public function __invoke(Customer $customer, array $data, array $addresses, ?User $updater = null): Customer
    {
        $data = apply_filters('customer.fillable', $data, $customer);

        // Strip derived balance columns — only their dedicated actions
        // can move them.
        unset($data['outstanding_balance'], $data['store_credit_balance'], $data['loyalty_points']);

        if (array_key_exists('phone', $data)) {
            $data['phone'] = PhoneNormalizer::normalize($data['phone']);
        }
        if (array_key_exists('whatsapp_phone', $data)) {
            $data['whatsapp_phone'] = PhoneNormalizer::normalize($data['whatsapp_phone']);
        }
        if (array_key_exists('email', $data)) {
            $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
            $data['email'] = $email === '' ? null : $email;
        }

        if ($updater) {
            $data['updated_by'] = $updater->id;
        }

        do_action('customer.before_update', $customer, $data);

        return DB::transaction(function () use ($customer, $data, $addresses) {
            $customer->update($data);

            $this->syncAddresses($customer, $addresses);

            do_action('customer.after_update', $customer);

            return $customer->refresh();
        });
    }

    /** @param array<int, array<string, mixed>> $addresses */
    private function syncAddresses(Customer $customer, array $addresses): void
    {
        $rows = array_values(array_filter($addresses, fn ($a) => $this->hasAnyContent($a)));

        $customer->addresses()->delete();

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
        // Respect the user's choice: if they unticked every default we
        // leave them all non-default — same reasoning as CreateCustomer.
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
