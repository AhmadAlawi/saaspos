<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NumberFormatRequest;
use App\Models\Company;
use App\Models\Store;
use App\Support\NumberFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Number-format settings — sale + hold number templates. Stored on the
 * company row; read everywhere via Company::numberFormat($type). Gated
 * by `settings.view` / `settings.update`.
 */
class NumberFormatController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        $company = Company::current() ?? new Company();
        $sampleStore = Store::query()->where('is_active', true)
            ->orderByDesc('is_default')->orderBy('name')->first()
            ?? new Store(['code' => 'MAIN']);

        return view('admin.settings.numbering', [
            'company'        => $company,
            'sampleStore'    => $sampleStore,
            'placeholders'   => NumberFormat::placeholders(),
            'defaultSale'    => 'SALE-{store}-{Ym}-{seq:04}',
            'defaultHold'    => 'HOLD-{store}-{Ym}-{seq:04}',
        ]);
    }

    public function update(NumberFormatRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        $company = Company::current();
        abort_unless($company !== null, 404);

        $company->forceFill($request->numberFormatData())->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.numbering.flash.updated'),
            route('admin.settings.numbering.edit'),
        );
    }
}
