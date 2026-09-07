<?php

namespace Database\Seeders;

use App\Actions\Suppliers\CreateSupplier;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo suppliers — 15 realistic records covering the UX states the
 * supplier list/detail screens need:
 *
 *   - Domestic wholesalers (IN, base currency)
 *   - International suppliers (USD / EUR / CNY default currencies)
 *   - GST-registered + composition (PAN-only) + tax-exempt
 *   - Long payment terms (Net 60), standard (Net 30), short (Net 7)
 *   - Inactive supplier (kept for history)
 *   - Pharmacy distributor (drug-schedule capable)
 *
 * Idempotent: skips if any row's notes start with `[DEMO]`.
 *
 *   /seed?class=SuppliersDemoSeeder
 *
 * Currency codes referenced (USD, EUR, CNY) must exist in `currencies`;
 * `CurrenciesSeeder` ships them so this runs after DatabaseSeeder.
 */
class SuppliersDemoSeeder extends Seeder
{
    public function run(CreateSupplier $create): void
    {
        $user = User::query()->orderBy('id')->first();
        if (! $user) {
            $this->command?->warn('Skipping — need a user (run the installer first).');
            return;
        }

        // Per-row idempotency — skip samples whose `name` already exists
        // so the seeder can be re-run after the fixture list is expanded.
        $existingNames = Supplier::query()
            ->where('notes', 'like', '[DEMO]%')
            ->pluck('name')
            ->all();

        $samples = [
            // 1 — Local wholesaler, GST-registered, Net 30
            [
                'name'                    => 'Krishna Wholesale Mart',
                'business_name'           => 'Krishna Wholesale Mart Private Limited',
                'contact_person'          => 'Mehul Krishna',
                'phone'                   => '+912832240011',
                'email'                   => 'sales@krishnawholesale.test',
                'gstin'                   => '24KRSHN1234M1Z3',
                'pan'                     => 'KRSHN1234M',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 30,
                'address_line1'           => 'Plot 22, Wholesale Market',
                'city'                    => 'Bhuj',
                'state'                   => 'Gujarat',
                'postal_code'             => '370001',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Primary FMCG distributor — weekly visits.',
            ],
            // 2 — Pharma distributor
            [
                'name'                    => 'Sanjivani Pharma Distributors',
                'business_name'           => 'Sanjivani Pharma Distributors LLP',
                'contact_person'          => 'Dr. Anita Mehta',
                'phone'                   => '+912226774455',
                'email'                   => 'orders@sanjivanipharma.test',
                'gstin'                   => '27SNJVN5678P1Z9',
                'pan'                     => 'SNJVN5678P',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 45,
                'address_line1'           => 'Unit 4A, Pharma Hub',
                'address_line2'           => 'Andheri East',
                'city'                    => 'Mumbai',
                'state'                   => 'Maharashtra',
                'postal_code'             => '400069',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Schedule-H + Schedule-X licensed wholesaler.',
            ],
            // 3 — Local stationery, composition scheme (PAN only)
            [
                'name'                    => 'Bharat Stationery Co.',
                'business_name'           => 'Bharat Stationery Co.',
                'contact_person'          => 'Ramesh Joshi',
                'phone'                   => '+918012345678',
                'email'                   => 'contact@bharatstationery.test',
                'pan'                     => 'AAAFB9876R',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 15,
                'address_line1'           => '14, Paper Lane',
                'city'                    => 'Ahmedabad',
                'state'                   => 'Gujarat',
                'postal_code'             => '380001',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Composition scheme — no GSTIN; PAN-only invoices.',
            ],
            // 4 — Electronics importer, USD
            [
                'name'                    => 'TechBridge Imports',
                'business_name'           => 'TechBridge International Pvt Ltd',
                'contact_person'          => 'Kavita Iyer',
                'phone'                   => '+918044556677',
                'email'                   => 'import@techbridge.test',
                'gstin'                   => '29TCHBR4567K1Z2',
                'pan'                     => 'TCHBR4567K',
                'default_currency_code'   => 'USD',
                'payment_terms_days'      => 60,
                'address_line1'           => '#42, Electronics City Phase 1',
                'city'                    => 'Bengaluru',
                'state'                   => 'Karnataka',
                'postal_code'             => '560100',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Electronics importer — USD invoices, LC payments.',
            ],
            // 5 — China supplier, CNY
            [
                'name'                    => 'Shenzhen Tech Source',
                'business_name'           => 'Shenzhen Tech Source Co., Ltd',
                'contact_person'          => 'Li Wei',
                'phone'                   => '+8675588001122',
                'email'                   => 'export@sztech-source.test',
                'tax_registration_number' => 'CN91440300MA5XYZ123',
                'default_currency_code'   => 'CNY',
                'payment_terms_days'      => 30,
                'address_line1'           => 'Building 5, Huaqiang North',
                'city'                    => 'Shenzhen',
                'state'                   => 'Guangdong',
                'postal_code'             => '518000',
                'country_code'            => 'CN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Direct from manufacturer — long lead times.',
            ],
            // 6 — European supplier, EUR
            [
                'name'                    => 'Bavaria Tools GmbH',
                'business_name'           => 'Bavaria Tools GmbH',
                'contact_person'          => 'Hans Müller',
                'phone'                   => '+498912345678',
                'email'                   => 'export@bavariatools.test',
                'tax_registration_number' => 'DE123456789',
                'default_currency_code'   => 'EUR',
                'payment_terms_days'      => 30,
                'address_line1'           => 'Industriestrasse 42',
                'city'                    => 'Munich',
                'state'                   => 'Bavaria',
                'postal_code'             => '80807',
                'country_code'            => 'DE',
                'is_active'               => true,
                'notes'                   => '[DEMO] Precision tools — pay via SEPA transfer.',
            ],
            // 7 — Local packaging supplier, Net 7
            [
                'name'                    => 'PackRight Solutions',
                'business_name'           => 'PackRight Solutions Pvt Ltd',
                'contact_person'          => 'Sunil Agarwal',
                'phone'                   => '+919823344556',
                'email'                   => 'orders@packright.test',
                'gstin'                   => '27PCKRT7890S1Z6',
                'pan'                     => 'PCKRT7890S',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 7,
                'address_line1'           => 'Sector 7, MIDC',
                'city'                    => 'Pune',
                'state'                   => 'Maharashtra',
                'postal_code'             => '411026',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Packaging materials — fast turnaround, Net 7.',
            ],
            // 8 — Cold storage supplier
            [
                'name'                    => 'FreshChain Logistics',
                'business_name'           => 'FreshChain Cold Logistics Private Limited',
                'contact_person'          => 'Priya Sharma',
                'phone'                   => '+911245556677',
                'email'                   => 'dispatch@freshchain.test',
                'gstin'                   => '06FRSCH3456L1Z8',
                'pan'                     => 'FRSCH3456L',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 30,
                'address_line1'           => 'Plot 18, Sonipat Industrial Area',
                'city'                    => 'Sonipat',
                'state'                   => 'Haryana',
                'postal_code'             => '131028',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Cold-chain — frozen and chilled items.',
            ],
            // 9 — Fashion garment, GST-registered
            [
                'name'                    => 'Surat Textile Hub',
                'business_name'           => 'Surat Textile Hub LLP',
                'contact_person'          => 'Bhavesh Patel',
                'phone'                   => '+912612234455',
                'email'                   => 'b2b@surattextile.test',
                'gstin'                   => '24SRTTX9012H1Z4',
                'pan'                     => 'SRTTX9012H',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 45,
                'address_line1'           => '102, Ring Road Mall',
                'city'                    => 'Surat',
                'state'                   => 'Gujarat',
                'postal_code'             => '395002',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Bulk fabric supplier — minimums apply.',
            ],
            // 10 — Local farmer cooperative
            [
                'name'                    => 'Kachchh Farmers FPO',
                'business_name'           => 'Kachchh Farmers Producer Organization',
                'contact_person'          => 'Jay Solanki',
                'phone'                   => '+919978001122',
                'email'                   => 'fpo@kachchhfarmers.test',
                'pan'                     => 'KFRMR1122F',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 0,
                'address_line1'           => 'Cooperative Bldg, Mundra Road',
                'city'                    => 'Bhuj',
                'state'                   => 'Gujarat',
                'postal_code'             => '370001',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] FPO — exempt + cash on delivery.',
            ],
            // 11 — Beverage distributor
            [
                'name'                    => 'CoolDrinks Distribution',
                'business_name'           => 'CoolDrinks Distribution Pvt Ltd',
                'contact_person'          => 'Rajat Khanna',
                'phone'                   => '+919567788990',
                'email'                   => 'b2b@cooldrinksdist.test',
                'gstin'                   => '07CLDRK6543K1Z2',
                'pan'                     => 'CLDRK6543K',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 14,
                'address_line1'           => 'Warehouse 7, Mayapuri',
                'city'                    => 'New Delhi',
                'state'                   => 'Delhi',
                'postal_code'             => '110064',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Beverages — daily morning delivery.',
            ],
            // 12 — Inactive (kept for history)
            [
                'name'                    => 'Legacy Suppliers Co.',
                'business_name'           => 'Legacy Suppliers Company',
                'contact_person'          => 'Vinod Tiwari',
                'phone'                   => '+912266554433',
                'email'                   => 'closed@legacysup.test',
                'pan'                     => 'LGCYS3456V',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 30,
                'address_line1'           => '8, Old Market Lane',
                'city'                    => 'Vadodara',
                'state'                   => 'Gujarat',
                'postal_code'             => '390001',
                'country_code'            => 'IN',
                'is_active'               => false,
                'notes'                   => '[DEMO] Inactive — relationship ended in 2024, kept for history.',
            ],
            // 13 — Cleaning supplies
            [
                'name'                    => 'CleanCare Industrial',
                'business_name'           => 'CleanCare Industrial Supplies Pvt Ltd',
                'contact_person'          => 'Asha Nair',
                'phone'                   => '+919004477889',
                'email'                   => 'orders@cleancare.test',
                'gstin'                   => '27CLNCR4321A1Z5',
                'pan'                     => 'CLNCR4321A',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 30,
                'address_line1'           => '5th Floor, Crystal Plaza',
                'city'                    => 'Thane',
                'state'                   => 'Maharashtra',
                'postal_code'             => '400607',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Janitorial + housekeeping supplies.',
            ],
            // 14 — Local stationery + office
            [
                'name'                    => 'OfficeKart Wholesale',
                'business_name'           => 'OfficeKart Wholesale LLP',
                'contact_person'          => 'Deepak Bansal',
                'phone'                   => '+911140506070',
                'email'                   => 'b2b@officekart.test',
                'gstin'                   => '07OFCKT2345D1Z7',
                'pan'                     => 'OFCKT2345D',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 21,
                'address_line1'           => '12, Nehru Place',
                'city'                    => 'New Delhi',
                'state'                   => 'Delhi',
                'postal_code'             => '110019',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Office supplies + consumables.',
            ],
            // 15 — Hardware wholesaler
            [
                'name'                    => 'BuildMate Hardware',
                'business_name'           => 'BuildMate Hardware Pvt Ltd',
                'contact_person'          => 'Mohan Pillai',
                'phone'                   => '+914844556677',
                'email'                   => 'sales@buildmate.test',
                'gstin'                   => '32BLDMT8765P1Z3',
                'pan'                     => 'BLDMT8765P',
                'default_currency_code'   => 'INR',
                'payment_terms_days'      => 30,
                'address_line1'           => 'Door 22, Tools Market',
                'city'                    => 'Kochi',
                'state'                   => 'Kerala',
                'postal_code'             => '682018',
                'country_code'            => 'IN',
                'is_active'               => true,
                'notes'                   => '[DEMO] Hand tools, fasteners, and small hardware.',
            ],
        ];

        $created = 0;
        $skipped = 0;
        foreach ($samples as $data) {
            if (in_array($data['name'], $existingNames, true)) {
                $skipped++;
                continue;
            }
            $supplier = $create($data, $user);
            $this->command?->info("Created supplier {$supplier->code} — {$supplier->name}.");
            $created++;
        }

        $this->command?->info("Suppliers demo: created {$created} new, skipped {$skipped} existing.");
    }
}
