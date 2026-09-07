<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\UpdateCompanyProfile;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandingSettingsRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Branding & theme settings — app name, logos (light/dark/half), accent
 * color, favicon, and the default theme. Stored on the company row.
 */
class BrandingSettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        return view('admin.settings.branding', [
            'company'       => Company::current() ?? new Company(),
            'accentChoices' => BrandingSettingsRequest::ACCENT_CHOICES,
            'textChoices'   => BrandingSettingsRequest::TEXT_CHOICES,
        ]);
    }

    public function update(BrandingSettingsRequest $request, UpdateCompanyProfile $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.branding.edit'),
            );
        }

        $company = Company::current();
        abort_unless($company !== null, 404);

        $data = [
            'app_name'         => $request->input('app_name') ?: null,
            'footer_text'      => $request->input('footer_text') ?: null,
            'brand_color'      => $request->input('brand_color') ?: null,
            'brand_text_color' => $request->input('brand_text_color') ?: null,
            'theme_default'    => $request->input('theme_default', 'light'),
        ];

        // Each logo field follows the same three-way lifecycle:
        // new upload → replace stored file; _remove flag → delete; otherwise leave.
        $this->handleLogoField($request, $company, 'app_logo',          'app_logo_path',          $data);
        $this->handleLogoField($request, $company, 'app_logo_dark',     'app_logo_dark_path',     $data);
        $this->handleLogoField($request, $company, 'app_logo_half',     'app_logo_half_path',     $data);
        $this->handleLogoField($request, $company, 'app_logo_half_dark','app_logo_half_dark_path',$data);
        $this->handleLogoField($request, $company, 'favicon',           'favicon_path',           $data);

        ($update)($company, $data);

        return $this->jsonOrRedirect(
            $request,
            __('settings.branding.flash.updated'),
            route('admin.settings.branding.edit'),
        );
    }

    /** Upload / remove / keep a single logo column. */
    private function handleLogoField(
        BrandingSettingsRequest $request,
        Company $company,
        string $inputName,
        string $columnName,
        array &$data,
    ): void {
        if ($request->hasFile($inputName)) {
            $this->deleteStored($company->{$columnName});
            $data[$columnName] = $request->file($inputName)->store('company', 'public');
        } elseif ($request->boolean($inputName . '_remove')) {
            $this->deleteStored($company->{$columnName});
            $data[$columnName] = null;
        }
    }

    private function deleteStored(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
