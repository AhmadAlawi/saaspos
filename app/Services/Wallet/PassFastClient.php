<?php

namespace App\Services\Wallet;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Alternative to {@see PassKitBuilder}'s raw-certificate signing —
 * PassFast (passfa.st) holds its OWN Apple Developer signing
 * credentials and signs the pass on your behalf via API, billed as
 * their own subscription. You never touch a certificate; you just call
 * their API with a secret key.
 *
 * Trade-off versus owning the certificate directly (see
 * AppleWalletSettingsController's doc-comment): the pass is signed
 * under PassFast's identity, not yours, and stops working if their
 * service or your subscription with them lapses. In exchange, they
 * also handle Apple's separate Wallet Web Service (device
 * registration + APNs) — so unlike the raw-certificate path, a points
 * balance CAN live-update on the customer's phone here via
 * {@see updateBalance()}, not just at re-download.
 *
 * Preferred over the raw-certificate path whenever both
 * `passfast_api_key` and `passfast_template_id` are configured (see
 * {@see \App\Http\Controllers\WalletPassController}) — PassFast is
 * generally the easier path to get working, so it wins when available.
 *
 * A pass TEMPLATE (storeCard layout: primary field for points,
 * secondary for member name, a Code128 barcode field) must be designed
 * once by hand in PassFast's own dashboard — there's no documented API
 * to create one, so `passfast_template_id` is a manually-copied value,
 * not something this app generates.
 */
class PassFastClient
{
    private const BASE_URL = 'https://api.passfa.st/functions/v1';

    public static function isConfigured(?Company $company = null): bool
    {
        $company ??= Company::current();

        return (bool) ($company?->passfast_api_key && $company?->passfast_template_id);
    }

    /**
     * Generates a signed `.pkpass` for this customer — PassFast returns
     * the binary file directly (default `wallet_type` is Apple-only).
     * `serialNumber` is the same opaque `wallet_pass_token` every other
     * wallet surface in this app already uses, so re-requesting the
     * same customer's pass always resolves to the same PassFast-side
     * record (their API treats a duplicate serial as a 409 unless
     * `get_or_create` is set).
     */
    public function generate(Customer $customer, string $serialNumber): string
    {
        $company = Company::current();
        if (! self::isConfigured($company)) {
            throw new RuntimeException('PassFast is not configured.');
        }

        $code = $customer->code ?? (string) $customer->id;

        $response = Http::withToken((string) $company->passfast_api_key)
            ->withHeaders($this->appIdHeader($company))
            ->timeout(15)
            ->post(self::BASE_URL.'/generate-pass', [
                'template_id'   => $company->passfast_template_id,
                'serial_number' => $serialNumber,
                'get_or_create' => true,
                // Field keys AND types match this exact template's
                // schema — confirmed against the real template (id in
                // company.passfast_template_id): `memberId` is the
                // barcode-bound data key (not `barcode`), and this
                // template's `points` field is typed as a string, not
                // a number — a real "points must be a string" 422
                // confirmed it, not assumed.
                'data'          => [
                    'memberName' => (string) $customer->name,
                    'points'     => (string) ((int) $customer->loyalty_points),
                    'memberId'   => 'MBR:'.$code,
                ],
            ]);

        if (! $response->successful() || $response->header('Content-Type') !== 'application/vnd.apple.pkpass') {
            throw new RuntimeException('PassFast could not generate this pass: '.$response->body());
        }

        return $response->body();
    }

    /**
     * Google Wallet counterpart to {@see generate()} — same request shape,
     * but `wallet_type: 'google'` makes PassFast respond with JSON
     * `{"save_url": "..."}` instead of binary `.pkpass` bytes (confirmed
     * against PassFast's own openapi.yaml). The returned URL is Google's
     * own hosted "save to Google Wallet" flow; callers should redirect to
     * it fresh each time rather than caching it.
     */
    public function generateGoogleSaveUrl(Customer $customer, string $serialNumber): string
    {
        $company = Company::current();
        if (! self::isConfigured($company)) {
            throw new RuntimeException('PassFast is not configured.');
        }

        $code = $customer->code ?? (string) $customer->id;

        $response = Http::withToken((string) $company->passfast_api_key)
            ->withHeaders($this->appIdHeader($company))
            ->timeout(15)
            ->post(self::BASE_URL.'/generate-pass', [
                'template_id'   => $company->passfast_template_id,
                'serial_number' => $serialNumber,
                'get_or_create' => true,
                'wallet_type'   => 'google',
                'data'          => [
                    'memberName' => (string) $customer->name,
                    'points'     => (string) ((int) $customer->loyalty_points),
                    'memberId'   => 'MBR:'.$code,
                ],
            ]);

        $saveUrl = $response->json('save_url');
        if (! $response->successful() || ! is_string($saveUrl) || $saveUrl === '') {
            throw new RuntimeException('PassFast could not generate this Google Wallet pass: '.$response->body());
        }

        return $saveUrl;
    }

    /**
     * Pushes an updated points balance to an already-issued pass —
     * PassFast forwards this to Apple's APNs so it shows up on the
     * customer's phone without them re-downloading anything. Best-
     * effort: called from the same hooks that already move points
     * ({@see \App\Actions\Customers\EarnLoyaltyPoints}, CompleteSale's
     * redemption block), never allowed to fail the sale itself.
     */
    public function updateBalance(Customer $customer, string $serialNumber): void
    {
        $company = Company::current();
        if (! self::isConfigured($company)) {
            return;
        }

        Http::withToken((string) $company->passfast_api_key)
            ->withHeaders($this->appIdHeader($company))
            ->timeout(15)
            ->patch(self::BASE_URL.'/manage-passes/serial/'.$serialNumber, [
                'data' => [
                    'points' => (string) ((int) $customer->loyalty_points),
                ],
            ]);
    }

    /** @return array<string, string> */
    private function appIdHeader(Company $company): array
    {
        return $company->passfast_app_id ? ['X-App-Id' => $company->passfast_app_id] : [];
    }
}
