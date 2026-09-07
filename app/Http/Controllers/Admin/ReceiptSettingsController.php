<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\UpdateCompanyProfile;
use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReceiptSettingsRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Receipt template settings — the look and content of printed sale
 * receipts. Stored on the company row; read app-wide via app_receipt().
 * Gated by `settings.view` / `settings.update`.
 */
class ReceiptSettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        return view('admin.settings.receipt', [
            'company'    => Company::current() ?? new Company(),
            'paperSizes' => ReceiptSettingsRequest::PAPER_SIZES,
        ]);
    }

    public function update(ReceiptSettingsRequest $request, UpdateCompanyProfile $update): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        $company = Company::current();
        abort_unless($company !== null, 404);

        $update($company, $request->receiptData());
        forget_app_receipt();

        return $this->jsonOrRedirect(
            $request,
            __('settings.receipt.flash.updated'),
            route('admin.settings.receipt.edit'),
        );
    }
}
