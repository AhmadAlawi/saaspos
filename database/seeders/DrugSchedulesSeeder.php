<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the `drug_schedules` table with the common regulatory codes
 * from the major regions a self-hosted customer is likely to install
 * in. Idempotent — keyed by `code`, so a re-run updates names /
 * descriptions but doesn't duplicate rows.
 *
 * Each install will typically prune to one country's set in the
 * Drug Schedules admin page (delete the rows that don't apply).
 */
class DrugSchedulesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $cursor = 0;

        foreach ($this->schedules() as $row) {
            DB::table('drug_schedules')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name'         => $row['name'],
                    'description'  => $row['description'] ?? null,
                    'country_code' => $row['country'] ?? null,
                    'sort_order'   => ++$cursor,
                    'is_active'    => true,
                    'updated_at'   => $now,
                    'created_at'   => $now,
                ],
            );
        }

        $count = DB::table('drug_schedules')->whereNull('deleted_at')->count();
        $this->command?->info("Seeded drug schedules ({$count} rows).");
    }

    /**
     * @return array<int, array{code:string, name:string, country?:string, description?:string}>
     */
    private function schedules(): array
    {
        return [
            // ── India ───────────────────────────────────────────
            ['code' => 'OTC', 'country' => 'IN', 'name' => 'Over the counter',
                'description' => 'Sold without prescription. Most general healthcare items.'],
            ['code' => 'H',   'country' => 'IN', 'name' => 'Schedule H',
                'description' => 'Prescription required — most antibiotics, sedatives, anti-anxiety drugs.'],
            ['code' => 'H1',  'country' => 'IN', 'name' => 'Schedule H1',
                'description' => 'Prescription + dispensing register entry — specific antibiotics & habit-forming drugs.'],
            ['code' => 'X',   'country' => 'IN', 'name' => 'Schedule X',
                'description' => 'Narcotic / psychotropic — 2-year record-keeping, special license required.'],
            ['code' => 'G',   'country' => 'IN', 'name' => 'Schedule G',
                'description' => 'Caution — to be used only under medical supervision.'],

            // ── United States (DEA) ─────────────────────────────
            ['code' => 'C-I',   'country' => 'US', 'name' => 'Schedule I',
                'description' => 'No accepted medical use, high abuse potential.'],
            ['code' => 'C-II',  'country' => 'US', 'name' => 'Schedule II',
                'description' => 'High abuse potential, medical use — opioids, methylphenidate, amphetamine.'],
            ['code' => 'C-III', 'country' => 'US', 'name' => 'Schedule III',
                'description' => 'Moderate abuse potential — buprenorphine, ketamine, anabolic steroids.'],
            ['code' => 'C-IV',  'country' => 'US', 'name' => 'Schedule IV',
                'description' => 'Low abuse potential — benzodiazepines, tramadol, zolpidem.'],
            ['code' => 'C-V',   'country' => 'US', 'name' => 'Schedule V',
                'description' => 'Lowest abuse potential — pregabalin, low-dose codeine cough syrups.'],

            // ── United Kingdom ──────────────────────────────────
            ['code' => 'GSL', 'country' => 'GB', 'name' => 'General Sales List',
                'description' => 'Sold from any retail outlet without a pharmacist.'],
            ['code' => 'P',   'country' => 'GB', 'name' => 'Pharmacy medicine',
                'description' => 'Sold only from registered pharmacies, under pharmacist supervision.'],
            ['code' => 'POM', 'country' => 'GB', 'name' => 'Prescription-only medicine',
                'description' => 'Dispensed only against a valid prescription.'],
            ['code' => 'CD',  'country' => 'GB', 'name' => 'Controlled drug',
                'description' => 'Misuse of Drugs Act controlled — additional safe-keeping & record requirements.'],

            // ── Generic fallback ────────────────────────────────
            ['code' => 'RX',  'country' => null, 'name' => 'Prescription required',
                'description' => 'Generic prescription-only label used when no country-specific code applies.'],
        ];
    }
}
