<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the company-level settings the user configured in Settings to
 * the runtime so every existing config(...) / __() / Mail::from() caller
 * picks them up automatically:
 *
 *   - `config('app.name')`               ← `company.name`
 *   - `config('mail.default')` + smtp.*  ← `company.mail_*`
 *   - `config('mail.from.*')`            ← `company.mail_from_*`
 *
 * Cheap and crash-resistant: skips when the table doesn't exist (during
 * the very first install/migrate). One row read per request — uses the
 * Eloquent model so the encrypted `mail_password` decrypts via the cast.
 */
class ApplyCompanySettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $this->resolveCompany();
        if ($company !== null) {
            $this->applyAppName($company);
            $this->applyMail($company);
            $this->applyTimezone($company);
        }

        return $next($request);
    }

    private function resolveCompany(): ?Company
    {
        try {
            if (! Schema::hasTable('company')) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return Company::current();
    }

    private function applyAppName(Company $company): void
    {
        if ($company->name) {
            config(['app.name' => $company->name]);
        }
    }

    private function applyMail(Company $company): void
    {
        $driver = $company->mail_driver;
        if ($driver) {
            config(['mail.default' => $driver]);
        }

        if ($driver === 'smtp' || $company->mail_host) {
            config([
                'mail.mailers.smtp.host'       => $company->mail_host        ?: config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port'       => $company->mail_port        ?: config('mail.mailers.smtp.port'),
                'mail.mailers.smtp.username'   => $company->mail_username    ?: config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password'   => $company->mail_password    ?: config('mail.mailers.smtp.password'),
                'mail.mailers.smtp.encryption' => $company->mail_encryption  ?: config('mail.mailers.smtp.encryption'),
            ]);
        }

        if ($company->mail_from_address) {
            config(['mail.from.address' => $company->mail_from_address]);
        }
        if ($company->mail_from_name) {
            config(['mail.from.name' => $company->mail_from_name]);
        }

        // Drop any cached mailer instance so the next Mail::send() rebuilds
        // the transport using the freshly-applied config. Best-effort —
        // if the manager has been swapped (e.g. Mockery in a test), skip.
        if (app()->resolved('mail.manager')) {
            try {
                app('mail.manager')->forgetMailers();
            } catch (\Throwable $e) {
                // mocked or otherwise — config takes effect on next resolve
            }
        }
    }

    private function applyTimezone(Company $company): void
    {
        $tz = $company->timezone;
        if (! $tz) {
            return;
        }
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);
    }
}
