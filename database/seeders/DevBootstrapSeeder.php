<?php

namespace Database\Seeders;

use App\Actions\Installer\AttachAdminToStore;
use App\Actions\Installer\CreateAdminUser;
use App\Actions\Installer\CreateCompanyAndStore;
use App\Actions\Installer\LockInstaller;
use App\Actions\Installer\SeedChartOfAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dev-only one-shot bootstrap. Stands in for the installer wizard while the
 * wizard UI is deferred.
 *
 * Hit /seed?class=DevBootstrapSeeder (route is gated by APP_DEBUG=true) and you
 * get a fully-provisioned app: lookup data + a Demo company + a Main store +
 * a super-admin account + the chart of accounts + the install lock file.
 *
 * Idempotent. Safe to re-run; existing rows are detected and skipped.
 *
 * Defaults:
 *     Admin   : Admin <admin@local> / password
 *     Company : Demo Co, India, INR, retail
 *     Store   : MAIN, Main Store, Asia/Kolkata
 *
 * Adjust the constants below or supersede this seeder once the real installer
 * comes back.
 */
class DevBootstrapSeeder extends Seeder
{
    private const ADMIN_NAME     = 'Admin';
    private const ADMIN_EMAIL    = 'admin@local';
    private const ADMIN_PASSWORD = 'password';

    private const COMPANY_NAME   = 'Demo Co';
    private const COUNTRY        = 'IN';
    private const CURRENCY       = 'INR';
    private const TIMEZONE       = 'Asia/Kolkata';
    private const INDUSTRY       = 'retail';

    private const STORE_NAME     = 'Main Store';
    private const STORE_CODE     = 'MAIN';
    private const STORE_ADDRESS  = 'Demo address, line 1';

    public function run(
        CreateAdminUser $createAdmin,
        CreateCompanyAndStore $createCompany,
        AttachAdminToStore $attach,
        SeedChartOfAccounts $seedCoa,
        LockInstaller $lock,
    ): void {
        // 1. Foundational lookup data (idempotent — uses updateOrInsert).
        $this->call([
            PermissionsSeeder::class,
            RolesSeeder::class,
            CurrenciesSeeder::class,
            LanguagesSeeder::class,
            UnitsSeeder::class,
            PaymentMethodsSeeder::class,
            ReturnReasonsSeeder::class,
            StockAdjustmentReasonsSeeder::class,
            CustomerGroupsSeeder::class,
        ]);

        // 2. Company + first store + admin user. Skip whichever already exist.
        $companyExists = DB::table('company')->exists();
        $storeId = DB::table('stores')->where('code', self::STORE_CODE)->value('id');
        $userId  = DB::table('users')->where('email', self::ADMIN_EMAIL)->value('id');

        if (! $userId) {
            $userId = ($createAdmin)([
                'name'     => self::ADMIN_NAME,
                'email'    => self::ADMIN_EMAIL,
                'password' => self::ADMIN_PASSWORD,
            ]);
            $this->command?->info("Created admin: ".self::ADMIN_EMAIL." / ".self::ADMIN_PASSWORD);
        } else {
            $this->command?->line("Admin already exists: ".self::ADMIN_EMAIL);
        }

        if (! $companyExists || ! $storeId) {
            $result = ($createCompany)([
                'company_name'       => self::COMPANY_NAME,
                'country_code'       => self::COUNTRY,
                'base_currency_code' => self::CURRENCY,
                'timezone'           => self::TIMEZONE,
                'industry'           => self::INDUSTRY,
                'store_name'         => self::STORE_NAME,
                'store_code'         => self::STORE_CODE,
                'store_address'      => self::STORE_ADDRESS,
            ]);
            $storeId = $result['store_id'];
            $this->command?->info("Created company + store: ".self::STORE_CODE);
        } else {
            $this->command?->line("Company + store already exist.");
        }

        ($attach)($userId, $storeId);

        // 3. Chart of accounts (skips itself gracefully if company isn't ready,
        //    but at this point company exists so it always runs through).
        ($seedCoa)();

        // 4. Drop the install lock file so EnsureInstalled stops gating the app.
        ($lock)();

        $this->command?->info('Dev bootstrap complete. Visit /login.');
    }
}
