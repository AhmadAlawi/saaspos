<?php

namespace App\Http\Controllers;

use App\Actions\Customers\EnsureLoyaltyCode;
use App\Models\Customer;
use Illuminate\Contracts\View\View;

/**
 * Public, no-login printable/on-screen loyalty card — `GET card/{token}`.
 * The interim stand-in for {@see WalletPassController} while there's no
 * Apple Developer certificate to sign a real .pkpass: same barcode
 * format (`MBR:{code}`, scannable by the existing cashier barcode
 * scanner — see cashier-page.js's `_tryMemberLookup()`), just rendered
 * as an HTML page the customer can screenshot or print instead of
 * living in the Wallet app. Reuses `wallet_pass_token` as the same
 * opaque lookup token — no reason for two separate tokens per customer.
 */
class LoyaltyCardController extends Controller
{
    public function show(string $token): View
    {
        $customer = Customer::query()->where('wallet_pass_token', $token)->first();
        abort_if($customer === null, 404);

        $code = app(EnsureLoyaltyCode::class)->handle($customer);

        return view('loyalty.card', [
            'customer' => $customer,
            'code'     => $code,
        ]);
    }
}
