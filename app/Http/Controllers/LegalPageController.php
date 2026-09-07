<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\View\View;

/**
 * Public read-only renderer for the company's privacy policy and terms
 * of service. Linked from the footer + provided to payment gateways
 * during onboarding (Razorpay/Paystack/Flutterwave require it).
 *
 * Returns a 404 when the merchant hasn't authored the corresponding
 * content yet so a stale gateway link can't surface an empty page.
 */
class LegalPageController extends Controller
{
    public function privacy(): View
    {
        $company = Company::current() ?? new Company();
        $body    = (string) ($company->privacy_policy ?? '');
        abort_if(trim($body) === '', 404);

        return view('legal.show', [
            'company' => $company,
            'title'   => __('settings.legal_pages.privacy_title'),
            'body'    => $body,
        ]);
    }

    public function terms(): View
    {
        $company = Company::current() ?? new Company();
        $body    = (string) ($company->terms_of_service ?? '');
        abort_if(trim($body) === '', 404);

        return view('legal.show', [
            'company' => $company,
            'title'   => __('settings.legal_pages.terms_title'),
            'body'    => $body,
        ]);
    }
}
