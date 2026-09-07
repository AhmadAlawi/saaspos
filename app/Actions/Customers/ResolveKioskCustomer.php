<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Support\PhoneNormalizer;

/**
 * Resolve a self-ordering kiosk shopper to a customer id from the name +
 * phone they typed (Slice 5). Finds an existing customer by normalised
 * phone, else creates a lightweight one. Returns null when nothing usable
 * was entered (so `off`/blank-optional flows stay walk-in).
 *
 * Phone is normalised with the same {@see PhoneNormalizer} CreateCustomer
 * uses, so the lookup matches how numbers are stored.
 */
class ResolveKioskCustomer
{
    public function __construct(private CreateCustomer $create) {}

    public function __invoke(?string $name, ?string $phone, ?User $staff = null): ?int
    {
        $name  = trim((string) $name);
        $phone = PhoneNormalizer::normalize($phone);

        if ($phone) {
            $existing = Customer::query()->where('phone', $phone)->first();
            if ($existing) {
                return (int) $existing->id;
            }
        }

        // Nothing to attach.
        if ($name === '' && ! $phone) {
            return null;
        }

        $customer = ($this->create)([
            'name'      => $name !== '' ? $name : ($phone ?: 'Kiosk customer'),
            'phone'     => $phone,
            'is_active' => true,
        ], [], $staff);

        return (int) $customer->id;
    }
}
