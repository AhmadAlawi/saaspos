<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CashierSettingsRequest;
use App\Models\Category;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Cashier (POS) UI preferences — layout, tile size, totals visibility.
 * Stored as a single JSON column on `company`; read everywhere via
 * Company::current()->cashier(). Gated by `settings.view` / `settings.update`.
 */
class CashierSettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        return view('admin.settings.cashier', [
            'company'    => Company::current() ?? new Company(),
            'layouts'    => CashierSettingsRequest::LAYOUTS,
            'tileSizes'  => CashierSettingsRequest::TILE_SIZES,
            'themes'     => CashierSettingsRequest::THEMES,
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(CashierSettingsRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        $company = Company::current();
        abort_unless($company !== null, 404);

        $company->forceFill(['cashier_settings' => $request->cashierData()])->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.cashier.flash.updated'),
            route('admin.settings.cashier.edit'),
        );
    }
}
