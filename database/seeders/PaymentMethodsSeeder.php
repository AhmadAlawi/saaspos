<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach ($this->methods() as $row) {
            DB::table('payment_methods')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name'                  => $row['name'],
                    'type'                  => $row['type'],
                    'provider'              => $row['provider'] ?? 'none',
                    'icon'                  => $row['icon'] ?? null,
                    'color'                 => $row['color'] ?? null,
                    'requires_reference'    => $row['requires_reference'] ?? false,
                    'opens_cash_drawer'     => $row['opens_cash_drawer'] ?? false,
                    'sort_order'            => $row['sort_order'],
                    'is_active'             => $row['is_active'] ?? true,
                    'updated_at'            => $now,
                    'created_at'            => $now,
                ],
            );
        }
    }

    private function methods(): array
    {
        return [
            ['code' => 'cash',          'name' => 'Cash',           'type' => 'cash',    'opens_cash_drawer' => true,  'icon' => 'wallet',         'color' => 'emerald', 'sort_order' => 1],
            ['code' => 'card',          'name' => 'Card',           'type' => 'card',    'opens_cash_drawer' => true, 'icon' => 'credit-card',    'color' => 'blue',    'sort_order' => 2],
            // upi / bank_transfer / cheque removed — GDuck only accepts cash and
            // card. Deliberately no longer seeded (was the actual cause of them
            // silently reactivating: their rows had no `is_active` key here, so
            // updateOrInsert() defaulted it back to true every time this seeder
            // ran). The existing payment_methods rows for these codes are left
            // in the DB as inactive so historical sales keep resolving fine.
            // Gateway-backed methods; disabled by default until the customer configures the gateway in Settings.
            ['code' => 'razorpay',      'name' => 'Razorpay',       'type' => 'digital', 'provider' => 'razorpay', 'requires_reference' => true, 'icon' => 'zap',  'color' => 'indigo', 'sort_order' => 10, 'is_active' => false],
            ['code' => 'phonepe',       'name' => 'PhonePe',        'type' => 'digital', 'provider' => 'phonepe',  'requires_reference' => true, 'icon' => 'zap',  'color' => 'purple','sort_order' => 11, 'is_active' => false],
            ['code' => 'stripe',        'name' => 'Stripe',         'type' => 'card',    'provider' => 'stripe',   'requires_reference' => true, 'icon' => 'zap',  'color' => 'sky',   'sort_order' => 12, 'is_active' => false],
        ];
    }
}
