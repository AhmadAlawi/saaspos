<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\UpdateCompanyProfile;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoyaltySettingsRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Loyalty program settings — enable/disable, earn rate, redeem rate.
 * Stored on `company` (see {@see \App\Actions\Customers\EarnLoyaltyPoints}
 * / {@see \App\Actions\Sales\CompleteSale}'s redemption block for how
 * these two rates get used). A normal business setting, gated on
 * `settings.update` — no credential involved here (unlike Apple Wallet's
 * page, which holds a real signing certificate).
 */
class LoyaltySettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        return view('admin.settings.loyalty', [
            'company' => Company::current() ?? new Company(),
        ]);
    }

    public function update(LoyaltySettingsRequest $request, UpdateCompanyProfile $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        $company = Company::current();
        abort_unless($company !== null, 404);

        $update($company, $request->loyaltyData());

        return $this->jsonOrRedirect(
            $request,
            __('settings.loyalty.flash.updated'),
            route('admin.settings.loyalty.edit'),
        );
    }
}
