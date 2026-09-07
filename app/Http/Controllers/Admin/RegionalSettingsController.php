<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\UpdateCompanyProfile;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegionalSettingsRequest;
use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Regional settings — the system-wide time zone + date/time display
 * formats. Stored on the company row; read app-wide via app_regional()
 * / format_date() / format_datetime(). Gated by `settings.view` /
 * `settings.update`.
 */
class RegionalSettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        // A stable sample so format options show a readable example.
        // A Saturday, with seconds, so weekday/AM-PM/seconds tokens all show.
        $sample = Carbon::create(2026, 1, 31, 14, 5, 9);

        $company    = Company::current() ?? new Company();
        $startMonth = (int) ($company->fiscal_year_start_month ?: 4);
        $months     = collect(range(1, 12))
            ->mapWithKeys(fn ($m) => [$m => Carbon::create(2000, $m, 1)->translatedFormat('F')])->all();
        $endMonth = $startMonth === 1 ? 12 : $startMonth - 1;

        return view('admin.settings.regional', [
            'company'         => $company,
            'timezones'       => timezone_identifiers_list(),
            'dateFormats'     => collect(RegionalSettingsRequest::DATE_FORMATS)
                ->mapWithKeys(fn ($f) => [$f => $sample->format($f)])->all(),
            'timeFormats'     => collect(RegionalSettingsRequest::TIME_FORMATS)
                ->mapWithKeys(fn ($f) => [$f => $sample->format($f)])->all(),
            'months'          => $months,
            'startMonth'      => $startMonth,
            'fyRange'         => $months[$startMonth].' – '.$months[$endMonth],
            'hasFiscalYears'  => \App\Models\FiscalYear::query()->exists(),
        ]);
    }

    public function update(RegionalSettingsRequest $request, UpdateCompanyProfile $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        $company = Company::current();
        abort_unless($company !== null, 404);

        ($update)($company, $request->regionalData());
        forget_app_regional();

        return $this->jsonOrRedirect(
            $request,
            __('settings.regional.flash.updated'),
            route('admin.settings.regional.edit'),
        );
    }
}
