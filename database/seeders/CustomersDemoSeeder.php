<?php

namespace Database\Seeders;

use App\Actions\Customers\CreateCustomer;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo customers — 15 realistic records covering the UX states the
 * list/detail screens need:
 *
 *   - Individual retail (multiple cities + states)
 *   - WhatsApp opted out
 *   - B2B businesses with GSTIN + multi-address
 *   - Premium-group with default discount
 *   - Wholesale with credit limit
 *   - Inactive (kept for history)
 *   - International customer (non-IN country)
 *
 * Idempotent: skips if any row's notes start with `[DEMO]`.
 *
 *   /seed?class=CustomersDemoSeeder
 *
 * Depends on `CustomerGroupsSeeder` having run.
 */
class CustomersDemoSeeder extends Seeder
{
    public function run(CreateCustomer $create): void
    {
        $user   = User::query()->orderBy('id')->first();
        $groups = CustomerGroup::query()->pluck('id', 'name');

        if (! $user || $groups->isEmpty()) {
            $this->command?->warn('Skipping — need a user and the customer groups.');
            return;
        }

        // Per-row idempotency: skip any sample whose `name` already
        // exists. This lets the seeder be re-run after the row set is
        // expanded (the older fixture only created 5 customers; adding
        // more shouldn't require wiping the existing demo rows).
        $existingNames = Customer::query()
            ->where('notes', 'like', '[DEMO]%')
            ->pluck('name')
            ->all();

        $samples = [
            // 1 — Individual retail
            [
                'data' => [
                    'name'              => 'Anil Patel',
                    'phone'             => '+919876543210',
                    'whatsapp_phone'    => '+919876543210',
                    'email'             => 'anil@example.com',
                    'gender'            => 'male',
                    'customer_group_id' => $groups['Regular'] ?? null,
                    'is_active'         => true,
                    'notes'             => '[DEMO] Regular individual customer.',
                ],
                'addresses' => [[
                    'label' => 'Home', 'line1' => '12 Sunrise Apt', 'line2' => 'M.G. Road',
                    'city' => 'Bhuj', 'state' => 'Gujarat', 'postal_code' => '370001',
                    'country_code' => 'IN', 'is_default' => true,
                ]],
            ],
            // 2 — WhatsApp opted out
            [
                'data' => [
                    'name'              => 'Priya Shah',
                    'phone'             => '+919812345678',
                    'email'             => 'priya@example.com',
                    'gender'            => 'female',
                    'whatsapp_opt_out'  => true,
                    'customer_group_id' => $groups['Regular'] ?? null,
                    'is_active'         => true,
                    'notes'             => '[DEMO] Asked not to receive WhatsApp updates.',
                ],
                'addresses' => [],
            ],
            // 3 — B2B wholesale with multi-address
            [
                'data' => [
                    'name'                    => 'Mahesh Trading Co.',
                    'phone'                   => '+912832221234',
                    'email'                   => 'sales@maheshtrading.test',
                    'is_business'             => true,
                    'business_name'           => 'Mahesh Trading Company Private Limited',
                    'gstin'                   => '24ABCDE1234F1Z5',
                    'pan'                     => 'ABCDE1234F',
                    'customer_group_id'       => $groups['Wholesale'] ?? null,
                    'default_discount_percent'=> 12.5,
                    'credit_limit'            => 25000,
                    'is_active'               => true,
                    'notes'                   => '[DEMO] B2B wholesale customer with credit.',
                ],
                'addresses' => [
                    ['label' => 'Billing',  'line1' => 'Plot 14, Industrial Area', 'city' => 'Bhuj',
                     'state' => 'Gujarat', 'postal_code' => '370005', 'country_code' => 'IN', 'is_default' => true],
                    ['label' => 'Shipping', 'line1' => 'Warehouse 3, Hub Road',    'city' => 'Anjar',
                     'state' => 'Gujarat', 'postal_code' => '370110', 'country_code' => 'IN'],
                ],
            ],
            // 4 — Premium
            [
                'data' => [
                    'name'              => 'Rohan Kapoor',
                    'phone'             => '+919012345009',
                    'email'             => 'rohan@example.com',
                    'gender'            => 'male',
                    'customer_group_id' => $groups['Premium'] ?? null,
                    'default_discount_percent' => 8.0,
                    'is_active'         => true,
                    'notes'             => '[DEMO] Premium customer with VIP discount.',
                ],
                'addresses' => [[
                    'label' => 'Home', 'line1' => '7B Lakeview', 'city' => 'Ahmedabad',
                    'state' => 'Gujarat', 'postal_code' => '380015', 'country_code' => 'IN', 'is_default' => true,
                ]],
            ],
            // 5 — Inactive
            [
                'data' => [
                    'name'              => 'Old Account',
                    'phone'             => '+919999900099',
                    'is_active'         => false,
                    'customer_group_id' => $groups['Regular'] ?? null,
                    'notes'             => '[DEMO] Inactive — kept for history.',
                ],
                'addresses' => [],
            ],
            // 6 — Individual, Mumbai
            [
                'data' => [
                    'name'              => 'Neha Joshi',
                    'phone'             => '+919823456701',
                    'email'             => 'neha.joshi@example.com',
                    'gender'            => 'female',
                    'dob'               => '1992-03-18',
                    'customer_group_id' => $groups['Regular'] ?? null,
                    'is_active'         => true,
                    'notes'             => '[DEMO] Walk-in customer, prefers SMS receipts.',
                ],
                'addresses' => [[
                    'label' => 'Home', 'line1' => '503, Sea Breeze', 'line2' => 'Bandra West',
                    'city' => 'Mumbai', 'state' => 'Maharashtra', 'postal_code' => '400050',
                    'country_code' => 'IN', 'is_default' => true,
                ]],
            ],
            // 7 — Senior, Delhi
            [
                'data' => [
                    'name'              => 'Sunita Verma',
                    'phone'             => '+919811223344',
                    'gender'            => 'female',
                    'dob'               => '1958-11-02',
                    'customer_group_id' => $groups['Regular'] ?? null,
                    'is_active'         => true,
                    'notes'             => '[DEMO] Senior citizen — eligible for senior discount on review.',
                ],
                'addresses' => [[
                    'label' => 'Home', 'line1' => 'A-44, Saket', 'city' => 'New Delhi',
                    'state' => 'Delhi', 'postal_code' => '110017',
                    'country_code' => 'IN', 'is_default' => true,
                ]],
            ],
            // 8 — Pharmacy customer
            [
                'data' => [
                    'name'              => 'Vikram Rao',
                    'phone'             => '+919900112233',
                    'email'             => 'vikram.rao@example.com',
                    'gender'            => 'male',
                    'dob'               => '1985-07-22',
                    'customer_group_id' => $groups['Regular'] ?? null,
                    'is_active'         => true,
                    'notes'             => '[DEMO] Repeat pharmacy customer — monthly insulin pickups.',
                ],
                'addresses' => [[
                    'label' => 'Home', 'line1' => '14, 4th Cross', 'line2' => 'Jayanagar',
                    'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal_code' => '560011',
                    'country_code' => 'IN', 'is_default' => true,
                ]],
            ],
            // 9 — Business with PAN only
            [
                'data' => [
                    'name'                    => 'Bharat Electricals',
                    'phone'                   => '+912226778899',
                    'email'                   => 'accounts@bharatele.test',
                    'is_business'             => true,
                    'business_name'           => 'Bharat Electricals LLP',
                    'pan'                     => 'AAAFB1234C',
                    'customer_group_id'       => $groups['Wholesale'] ?? null,
                    'default_discount_percent'=> 7.5,
                    'credit_limit'            => 15000,
                    'is_active'               => true,
                    'notes'                   => '[DEMO] B2B — composition scheme supplier, no GSTIN.',
                ],
                'addresses' => [[
                    'label' => 'Office', 'line1' => 'Unit 8, MIDC', 'city' => 'Pune',
                    'state' => 'Maharashtra', 'postal_code' => '411019',
                    'country_code' => 'IN', 'is_default' => true,
                ]],
            ],
            // 10 — Young customer, walk-in
            [
                'data' => [
                    'name'              => 'Aarav Singh',
                    'phone'             => '+918765432109',
                    'gender'            => 'male',
                    'dob'               => '2001-04-09',
                    'customer_group_id' => $groups['Regular'] ?? null,
                    'is_active'         => true,
                    'notes'             => '[DEMO] Student — small frequent purchases.',
                ],
                'addresses' => [],
            ],
            // 11 — Premium, Hyderabad
            [
                'data' => [
                    'name'              => 'Lakshmi Reddy',
                    'phone'             => '+919008877665',
                    'email'             => 'lakshmi.reddy@example.com',
                    'gender'            => 'female',
                    'customer_group_id' => $groups['Premium'] ?? null,
                    'default_discount_percent' => 10.0,
                    'credit_limit'      => 5000,
                    'is_active'         => true,
                    'notes'             => '[DEMO] Premium — frequent buyer with store-credit history.',
                ],
                'addresses' => [[
                    'label' => 'Home', 'line1' => '8-2-293/82/A', 'line2' => 'Jubilee Hills',
                    'city' => 'Hyderabad', 'state' => 'Telangana', 'postal_code' => '500033',
                    'country_code' => 'IN', 'is_default' => true,
                ]],
            ],
            // 12 — International (US)
            [
                'data' => [
                    'name'              => 'Sarah Williams',
                    'phone'             => '+14155550123',
                    'email'             => 'sarah.williams@example.com',
                    'gender'            => 'female',
                    'customer_group_id' => $groups['Regular'] ?? null,
                    'is_active'         => true,
                    'notes'             => '[DEMO] International — ships orders to US address.',
                ],
                'addresses' => [[
                    'label' => 'Home', 'line1' => '742 Evergreen Terrace', 'city' => 'San Francisco',
                    'state' => 'CA', 'postal_code' => '94110',
                    'country_code' => 'US', 'is_default' => true,
                ]],
            ],
            // 13 — Prefers-not-to-say gender
            [
                'data' => [
                    'name'              => 'Alex Mathew',
                    'phone'             => '+919889977665',
                    'email'             => 'alex.mathew@example.com',
                    'gender'            => 'prefer_not_to_say',
                    'customer_group_id' => $groups['Regular'] ?? null,
                    'is_active'         => true,
                    'notes'             => '[DEMO] Privacy-conscious — minimal profile.',
                ],
                'addresses' => [],
            ],
            // 14 — B2B retail chain
            [
                'data' => [
                    'name'                    => 'Quick Mart Stores',
                    'phone'                   => '+914442221111',
                    'email'                   => 'procurement@quickmart.test',
                    'is_business'             => true,
                    'business_name'           => 'Quick Mart Retail Private Limited',
                    'gstin'                   => '33AABCQ1234M1Z1',
                    'pan'                     => 'AABCQ1234M',
                    'customer_group_id'       => $groups['Wholesale'] ?? null,
                    'default_discount_percent'=> 15.0,
                    'credit_limit'            => 100000,
                    'is_active'               => true,
                    'notes'                   => '[DEMO] Chain account — large monthly orders.',
                ],
                'addresses' => [
                    ['label' => 'HQ',       'line1' => '5th Floor, Olympia Tech Park', 'city' => 'Chennai',
                     'state' => 'Tamil Nadu', 'postal_code' => '600032', 'country_code' => 'IN', 'is_default' => true],
                    ['label' => 'Branch A', 'line1' => 'Store 12, Phoenix Mall',       'city' => 'Chennai',
                     'state' => 'Tamil Nadu', 'postal_code' => '600035', 'country_code' => 'IN'],
                ],
            ],
            // 15 — Loyal regular, multiple years
            [
                'data' => [
                    'name'              => 'Manoj Iyer',
                    'phone'             => '+917894561230',
                    'email'             => 'manoj.iyer@example.com',
                    'gender'            => 'male',
                    'dob'               => '1975-09-30',
                    'customer_group_id' => $groups['Premium'] ?? null,
                    'default_discount_percent' => 6.0,
                    'is_active'         => true,
                    'notes'             => '[DEMO] Loyal regular — first sale in 2019, weekly visits.',
                ],
                'addresses' => [[
                    'label' => 'Home', 'line1' => 'Flat 304, Sea Pearl',
                    'city' => 'Kochi', 'state' => 'Kerala', 'postal_code' => '682019',
                    'country_code' => 'IN', 'is_default' => true,
                ]],
            ],
        ];

        $created = 0;
        $skipped = 0;
        foreach ($samples as $s) {
            if (in_array($s['data']['name'], $existingNames, true)) {
                $skipped++;
                continue;
            }
            $customer = $create($s['data'], $s['addresses'], $user);
            $this->command?->info("Created customer {$customer->code} — {$customer->name}.");
            $created++;
        }

        $this->command?->info("Customers demo: created {$created} new, skipped {$skipped} existing.");
    }
}
