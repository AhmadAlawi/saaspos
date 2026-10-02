<?php

namespace App\Http\Controllers;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\EnsureWalletPassToken;
use App\Actions\Sales\AttachCustomerToSale;
use App\Exceptions\SaleAlreadyClaimed;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleClaimLink;
use App\Services\Wallet\PassFastClient;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public, no-login self-service claim flow — `GET/POST claim/{token}`.
 * A walk-in customer (no customer attached at checkout) scans the CFD's
 * claim QR, lands here, fills in name + phone, and the system attaches
 * them to the already-completed sale + retroactively awards points.
 * Same trust model as {@see PublicReceiptController}: the opaque token
 * IS the authorisation, no auth middleware.
 */
class SaleClaimController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $link = SaleClaimLink::query()->where('token', $token)->first();
        abort_if($link === null, 404);

        // An already-claimed link just shows the receipt instead of the
        // form again — the customer clicking an old link shouldn't hit a
        // dead end, and there's no enumeration signal either way.
        if ($link->isClaimed()) {
            $sale = Sale::find($link->sale_id);
            abort_if($sale === null, 404);

            return redirect(route('receipt.public', ['token' => $sale->ensurePublicReceiptLink()->token]));
        }

        $sale = Sale::query()->find($link->sale_id);
        abort_if($sale === null || ! $sale->isCompleted() || $sale->customer_id, 404);

        return view('claim.form', [
            'token' => $token,
            'sale'  => $sale,
        ]);
    }

    public function store(Request $request, string $token, AttachCustomerToSale $attach): View|RedirectResponse
    {
        $link = SaleClaimLink::query()->where('token', $token)->first();
        abort_if($link === null || $link->isClaimed(), 404);

        $sale = Sale::query()->find($link->sale_id);
        abort_if($sale === null || ! $sale->isCompleted() || $sale->customer_id, 404);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $phone = PhoneNormalizer::normalize($data['phone']);

        $customer = $phone
            ? Customer::query()->where('phone', $phone)->first()
            : null;

        if (! $customer) {
            $customer = app(CreateCustomer::class)(
                ['name' => $data['name'], 'phone' => $phone],
                [],
            );
        }

        try {
            $sale = $attach->handle($link, $customer);
        } catch (SaleAlreadyClaimed $e) {
            abort(404);
        }

        $company = Company::current() ?? new Company();
        $walletPassUrl = null;
        $googleWalletPassUrl = null;
        if ($company->loyalty_enabled) {
            $passToken = app(EnsureWalletPassToken::class)->handle($customer);
            if ($company->apple_wallet_enabled) {
                $walletPassUrl = route('wallet.pass', ['token' => $passToken]);
            }
            if (PassFastClient::isConfigured($company)) {
                $googleWalletPassUrl = route('wallet.pass.google', ['token' => $passToken]);
            }
        }

        return view('claim.success', [
            'sale'                 => $sale,
            'customer'             => $customer->fresh(),
            'walletPassUrl'        => $walletPassUrl,
            'googleWalletPassUrl'  => $googleWalletPassUrl,
        ]);
    }
}
