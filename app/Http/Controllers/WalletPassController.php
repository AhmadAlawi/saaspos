<?php

namespace App\Http\Controllers;

use App\Actions\Customers\EnsureLoyaltyCode;
use App\Models\Customer;
use App\Services\Wallet\PassFastClient;
use App\Services\Wallet\PassKitBuilder;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Public, no-login Apple Wallet pass download — `wallet/{token}.pkpass`.
 * Same trust model as {@see PublicReceiptController}: the opaque token
 * IS the credential, no auth middleware. 404s identically whether the
 * token is unknown or Wallet just isn't configured yet, so a bad token
 * can't be distinguished from "feature off" (no enumeration signal).
 */
class WalletPassController extends Controller
{
    public function show(string $token, PassKitBuilder $builder, PassFastClient $passFast): Response
    {
        $customer = Customer::query()->where('wallet_pass_token', $token)->first();
        abort_if($customer === null, 404);

        // The barcode needs a stable code BEFORE the pass is built —
        // generating it after would mean the very first download
        // encodes a placeholder that never matches what re-downloads
        // encode later.
        app(EnsureLoyaltyCode::class)->handle($customer);

        // PassFast wins when configured — it's the easier path to get
        // working (no certificate to source/upload/renew yourself) and
        // additionally supports live balance push-updates, which the
        // raw-certificate path doesn't attempt. Falls back to the
        // certificate builder otherwise.
        try {
            $bytes = PassFastClient::isConfigured()
                ? $passFast->generate($customer, $token)
                : $builder->build($customer, $token);
        } catch (RuntimeException $e) {
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type'        => 'application/vnd.apple.pkpass',
            'Content-Disposition' => 'attachment; filename="loyalty.pkpass"',
        ]);
    }

    /**
     * Google Wallet counterpart — PassFast returns a save-to-wallet URL,
     * not binary pass data, so this redirects straight to Google's own
     * hosted flow rather than proxying anything. Only PassFast can serve
     * Google Wallet (the raw-certificate path is Apple-only), so this
     * 404s outright when PassFast isn't configured.
     */
    public function showGoogle(string $token, PassFastClient $passFast): \Illuminate\Http\RedirectResponse
    {
        $customer = Customer::query()->where('wallet_pass_token', $token)->first();
        abort_if($customer === null, 404);

        app(EnsureLoyaltyCode::class)->handle($customer);

        try {
            $saveUrl = PassFastClient::isConfigured() ? $passFast->generateGoogleSaveUrl($customer, $token) : null;
        } catch (RuntimeException $e) {
            $saveUrl = null;
        }

        abort_if($saveUrl === null, 404);

        return redirect()->away($saveUrl);
    }
}
