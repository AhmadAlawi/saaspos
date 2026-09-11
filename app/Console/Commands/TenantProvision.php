<?php

namespace App\Console\Commands;

use App\Actions\Installer\AttachAdminToStore;
use App\Actions\Installer\CreateAdminUser;
use App\Actions\Installer\CreateCompanyAndStore;
use App\Actions\Installer\LinkPublicStorage;
use App\Actions\Installer\LockInstaller;
use App\Actions\Installer\SeedChartOfAccounts;
use App\Actions\Installer\SeedInstallerEssentials;
use App\Support\InstallState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Headless equivalent of the installer wizard, for the SaaS provisioning
 * pipeline (SaaS conversion plan, Phase 3). A new customer's instance boots
 * from a freshly built image with no company/admin/store yet; the wizard's
 * web UI has no one to click through it, so this drives the exact same
 * Installer Actions the wizard uses — {@see CreateCompanyAndStore},
 * {@see CreateAdminUser}, {@see AttachAdminToStore}, etc. — from env vars
 * the provisioning pipeline templates into `.env` at deploy time.
 *
 * Deliberately NOT a generalization of `mecca:provision`: that command
 * copies one specific customer's real production data (fixtures fetched
 * from the legacy production site) for a one-time data migration, and has
 * nothing to do with provisioning a new, empty SaaS customer.
 *
 * Idempotent via the same {@see InstallState::isLocked()} guard the wizard
 * itself uses — safe to leave wired into every boot; a no-op after the
 * first successful run.
 *
 * Required env: TENANT_COMPANY_NAME, TENANT_ADMIN_EMAIL, TENANT_ADMIN_PASSWORD.
 * Optional (sensible defaults below): TENANT_COUNTRY_CODE, TENANT_CURRENCY_CODE,
 * TENANT_TIMEZONE, TENANT_INDUSTRY, TENANT_STORE_NAME, TENANT_STORE_CODE,
 * TENANT_STORE_ADDRESS, TENANT_ADMIN_NAME, TENANT_LOGO_URL (SaaS conversion
 * plan Phase 8 — the signup wizard's uploaded logo, fetched here the same
 * way {@see ProvisionMeccaMall::fetchToPublicDisk()} already fetches
 * images, just from an absolute URL instead of a relative production path).
 */
class TenantProvision extends Command
{
    protected $signature = 'tenant:provision';

    protected $description = 'One-time headless provisioning of a new SaaS tenant (company, store, admin) from env vars.';

    public function handle(
        SeedInstallerEssentials $seedEssentials,
        CreateCompanyAndStore $createCompanyAndStore,
        CreateAdminUser $createAdmin,
        AttachAdminToStore $attachAdmin,
        SeedChartOfAccounts $seedChartOfAccounts,
        LinkPublicStorage $linkStorage,
        LockInstaller $lockInstaller,
    ): int {
        if (InstallState::isLocked()) {
            $this->info('Already provisioned — skipping.');

            return self::SUCCESS;
        }

        $companyName = (string) env('TENANT_COMPANY_NAME', '');
        $adminEmail  = (string) env('TENANT_ADMIN_EMAIL', '');
        $adminPass   = (string) env('TENANT_ADMIN_PASSWORD', '');

        if ($companyName === '' || $adminEmail === '' || $adminPass === '') {
            $this->error('TENANT_COMPANY_NAME, TENANT_ADMIN_EMAIL, and TENANT_ADMIN_PASSWORD are required.');

            return self::FAILURE;
        }

        $essentials = ($seedEssentials)();
        if (! $essentials['ok']) {
            $this->error('Essential seeders failed: '.$essentials['output']);

            return self::FAILURE;
        }

        DB::transaction(function () use ($createCompanyAndStore, $createAdmin, $attachAdmin, $companyName, $adminEmail, $adminPass) {
            ['company_id' => $companyId, 'store_id' => $storeId] = ($createCompanyAndStore)([
                'company_name'       => $companyName,
                'country_code'       => (string) env('TENANT_COUNTRY_CODE', 'JO'),
                'base_currency_code' => (string) env('TENANT_CURRENCY_CODE', 'JOD'),
                'timezone'           => (string) env('TENANT_TIMEZONE', 'Asia/Amman'),
                'industry'           => (string) env('TENANT_INDUSTRY', 'retail'),
                'store_name'         => (string) env('TENANT_STORE_NAME', $companyName.' — Main'),
                'store_code'         => (string) env('TENANT_STORE_CODE', 'MAIN'),
                'store_address'      => (string) env('TENANT_STORE_ADDRESS', ''),
            ]);

            $adminId = ($createAdmin)([
                'name'     => (string) env('TENANT_ADMIN_NAME', 'Admin'),
                'email'    => $adminEmail,
                'password' => $adminPass,
            ]);

            ($attachAdmin)($adminId, $storeId);

            $this->fetchLogo($companyId);
        });

        ($seedChartOfAccounts)();
        ($linkStorage)();
        ($lockInstaller)();

        $this->info('Tenant provisioning complete.');

        return self::SUCCESS;
    }

    /**
     * Fetch the wizard's uploaded logo (from the license server's public
     * disk — this instance has no storage of its own yet) into
     * storage/app/public and set it as the new company's logo. Never
     * fatal — a fresh instance without a logo is fine; one that crashes
     * mid-provisioning over a missing/unreachable image is not.
     */
    private function fetchLogo(int $companyId): void
    {
        $url = trim((string) env('TENANT_LOGO_URL', ''));
        if ($url === '') {
            return;
        }

        $bytes = @file_get_contents($url);
        if ($bytes === false) {
            return;
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'png';
        $path = 'logos/'.Str::random(20).'.'.$extension;

        Storage::disk('public')->put($path, $bytes);

        DB::table('company')->where('id', $companyId)->update(['logo_path' => $path]);
    }
}
