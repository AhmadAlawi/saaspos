<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\UpdateCompanyProfile;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyProfileRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Company profile settings — the single company record's identity,
 * address, and logo. Gated by `settings.view` / `settings.update`.
 */
class CompanyProfileController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        return view('admin.settings.company', [
            'company'   => Company::current() ?? new Company(),
            'countries' => \App\Support\Countries::all(),
        ]);
    }

    public function update(CompanyProfileRequest $request, UpdateCompanyProfile $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.company.edit'),
            );
        }

        $company = Company::current();
        abort_unless($company !== null, 404);

        $data = $request->profileData();

        // Three logo lifecycles — same shape as Brand: new upload, remove, leave.
        if ($request->hasFile('logo')) {
            $this->deleteStoredLogo($company);
            $data['logo_path'] = $request->file('logo')->store('company', 'public');
        } elseif ($request->boolean('logo_remove')) {
            $this->deleteStoredLogo($company);
            $data['logo_path'] = null;
        }

        ($update)($company, $data);

        return $this->jsonOrRedirect(
            $request,
            __('settings.company.flash.updated'),
            route('admin.settings.company.edit'),
        );
    }

    private function deleteStoredLogo(Company $company): void
    {
        if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            Storage::disk('public')->delete($company->logo_path);
        }
    }
}
