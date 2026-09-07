<?php

namespace App\Http\Controllers\Installer;

use App\Actions\Installer\AttachAdminToStore;
use App\Actions\Installer\CreateAdminUser;
use App\Actions\Installer\CreateCompanyAndStore;
use App\Actions\Installer\SeedChartOfAccounts;
use App\Http\Controllers\Controller;
use App\Support\InstallState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function show()
    {
        $currencies = DB::table('currencies')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);

        return view('installer.admin', [
            'currentStep' => 5,
            'currencies'  => $currencies,
            'timezones'   => \DateTimeZone::listIdentifiers(),
            'countries'   => $this->countries(),
            'industries'  => ['retail' => 'Retail', 'pharmacy' => 'Pharmacy', 'supermarket' => 'Supermarket'],
        ]);
    }

    public function save(
        Request $request,
        CreateAdminUser $createAdmin,
        CreateCompanyAndStore $createCompany,
        AttachAdminToStore $attach,
        SeedChartOfAccounts $seedCoa,
    ) {
        // COA seed at the end is another Artisan::call bootstrap — lift the SAPI cap.
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        ignore_user_abort(true);

        $data = $request->validate([
            'admin_name'          => ['required', 'string', 'max:191'],
            'admin_email'         => ['required', 'email:rfc', 'max:191', 'unique:users,email'],
            'admin_password'      => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()->symbols()],
            'company_name'        => ['required', 'string', 'max:191'],
            'country_code'        => ['required', 'string', 'size:2'],
            'base_currency_code'  => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'timezone'            => ['required', 'string', 'max:64'],
            'industry'            => ['required', 'string', 'in:retail,pharmacy,supermarket'],
            'store_name'          => ['required', 'string', 'max:191'],
            'store_code'          => ['required', 'string', 'max:32'],
            'store_address'       => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data, $createAdmin, $createCompany, $attach) {
            $userId  = ($createAdmin)([
                'name'     => $data['admin_name'],
                'email'    => $data['admin_email'],
                'password' => $data['admin_password'],
            ]);

            $result = ($createCompany)([
                'company_name'        => $data['company_name'],
                'country_code'        => strtoupper($data['country_code']),
                'base_currency_code'  => strtoupper($data['base_currency_code']),
                'timezone'            => $data['timezone'],
                'industry'            => $data['industry'],
                'store_name'          => $data['store_name'],
                'store_code'          => strtoupper($data['store_code']),
                'store_address'       => $data['store_address'],
            ]);

            ($attach)($userId, $result['store_id']);
        });

        // Now that company exists, seed the COA.
        ($seedCoa)();

        InstallState::set('admin_created', true);

        return redirect()->route('install.demo');
    }

    /** @return array<string, string> */
    private function countries(): array
    {
        // Trimmed list — extend later. Keys are ISO 3166-1 alpha-2.
        return [
            'IN' => 'India',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'SG' => 'Singapore',
            'AU' => 'Australia',
            'CA' => 'Canada',
            'BD' => 'Bangladesh',
            'PK' => 'Pakistan',
            'LK' => 'Sri Lanka',
            'NP' => 'Nepal',
            'MY' => 'Malaysia',
            'ID' => 'Indonesia',
            'PH' => 'Philippines',
            'TH' => 'Thailand',
            'ZA' => 'South Africa',
            'NG' => 'Nigeria',
            'KE' => 'Kenya',
        ];
    }
}
