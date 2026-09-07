<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScaleSettingsRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Weighing-scale barcode settings — the EAN-13/UPC-A template the cashier
 * uses to decode scale-printed barcodes into a product + measured weight.
 * Stored as a single JSON column on `company`; read at the cashier via
 * Company::current()->scale(). Gated by `settings.view` / `settings.update`.
 */
class ScaleSettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        return view('admin.settings.scale', [
            'company'    => Company::current() ?? new Company(),
            'embedTypes' => ScaleSettingsRequest::EMBED_TYPES,
        ]);
    }

    public function update(ScaleSettingsRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        $company = Company::current();
        abort_unless($company !== null, 404);

        $company->forceFill(['scale_settings' => $request->scaleData()])->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.scale.flash.updated'),
            route('admin.settings.scale.edit'),
        );
    }
}
