<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\UpdateCompanyProfile;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PricingSettingsRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Pricing settings — currently exposes the auto-apply-markup-on-receive
 * toggle. Stored on `company`; consumed by {@see \App\Actions\Purchases\ReceivePurchase}
 * to decide whether to bump `products.selling_price` when receiving
 * goods at a new cost. Gated by `settings.view` / `settings.update`.
 */
class PricingSettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        return view('admin.settings.pricing', [
            'company' => Company::current() ?? new Company(),
        ]);
    }

    public function update(PricingSettingsRequest $request, UpdateCompanyProfile $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        $company = Company::current();
        abort_unless($company !== null, 404);

        $update($company, $request->pricingData());

        return $this->jsonOrRedirect(
            $request,
            __('settings.pricing.flash.updated'),
            route('admin.settings.pricing.edit'),
        );
    }
}
