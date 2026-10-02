<?php

namespace App\Services\Wallet;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Builds a signed `.pkpass` (Apple Wallet) loyalty card for one customer
 * — a storeCard-style pass showing their points balance, with a
 * Code128 barcode encoding `MBR:{customer.code}` so the cashier's
 * existing barcode scanner can scan-to-identify them at checkout (see
 * cashier-page.js's `_tryMemberLookup()`).
 *
 * Ships as a STATIC snapshot — the points figure only refreshes when
 * the customer re-downloads the pass. Live balance push-updates need
 * Apple's separate Wallet Web Service (device registration + APNs),
 * deliberately out of scope here; the barcode itself always resolves
 * to the live balance at the register regardless.
 *
 * Zip assembly mirrors {@see \App\Actions\Settings\RunBackup} exactly
 * (`ZipArchive` + `addFromString`, no third-party zip package).
 */
class PassKitBuilder
{
    public function build(Customer $customer, string $serialNumber): string
    {
        $company = Company::current();
        if (! $company?->apple_wallet_enabled) {
            throw new RuntimeException('Apple Wallet is not enabled.');
        }
        if (! $company->apple_team_id || ! $company->apple_pass_type_id) {
            throw new RuntimeException('Apple Wallet is not fully configured yet.');
        }
        if (! Storage::disk('local')->exists(\App\Http\Controllers\Admin\AppleWalletSettingsController::CERT_PATH)) {
            throw new RuntimeException('No Apple Wallet certificate has been uploaded yet.');
        }

        $files = [
            'pass.json'    => $this->buildPassJson($company, $customer, $serialNumber),
            'icon.png'     => $this->generateIcon(29),
            'icon@2x.png'  => $this->generateIcon(58),
            'icon@3x.png'  => $this->generateIcon(87),
        ];

        // Real company logo (top-left of the pass) when one's uploaded
        // under Settings → Branding — falls back to just the flat-color
        // icon above when there's no logo, or when it's an SVG (GD can't
        // rasterize those; Apple's pass images must be PNG anyway).
        if ($logo = $this->generateLogo(160, 50)) {
            $files['logo.png'] = $logo;
        }
        if ($logo2x = $this->generateLogo(320, 100)) {
            $files['logo@2x.png'] = $logo2x;
        }

        $manifest = [];
        foreach ($files as $name => $contents) {
            $manifest[$name] = sha1($contents);
        }
        $manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES);

        $signature = $this->sign($manifestJson, $company);

        $tmpZip = tempnam(sys_get_temp_dir(), 'pkpass-');
        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Failed to create the pass archive.');
        }
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->addFromString('manifest.json', $manifestJson);
        $zip->addFromString('signature', $signature);
        $zip->close();

        $bytes = file_get_contents($tmpZip);
        @unlink($tmpZip);

        return $bytes;
    }

    private function buildPassJson(Company $company, Customer $customer, string $serialNumber): string
    {
        $code = $customer->code ?? (string) $customer->id;

        $pass = [
            'formatVersion'      => 1,
            'passTypeIdentifier' => $company->apple_pass_type_id,
            'teamIdentifier'     => $company->apple_team_id,
            'serialNumber'       => $serialNumber,
            'organizationName'   => $company->name ?: config('app.name'),
            'description'        => 'Loyalty Card',
            'logoText'           => $company->name ?: config('app.name'),
            'backgroundColor'    => $this->hexToRgb($company->brand_color, 'rgb(20,20,20)'),
            'foregroundColor'    => 'rgb(255,255,255)',
            'labelColor'         => 'rgb(230,230,230)',
            'storeCard'          => [
                'primaryFields' => [
                    ['key' => 'points', 'label' => 'POINTS', 'value' => (int) $customer->loyalty_points],
                ],
                'secondaryFields' => [
                    ['key' => 'member', 'label' => 'MEMBER', 'value' => (string) $customer->name],
                ],
                'auxiliaryFields' => [
                    ['key' => 'code', 'label' => 'CODE', 'value' => $code],
                ],
            ],
            'barcodes' => [
                [
                    'format'          => 'PKBarcodeFormatCode128',
                    'message'         => 'MBR:'.$code,
                    'messageEncoding' => 'iso-8859-1',
                ],
            ],
        ];

        return json_encode($pass, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** The Apple-required square icon — the real company logo (from
     *  Settings → Branding), centered and scaled to fit on a
     *  brand-color background when one's uploaded; a flat brand-color
     *  square otherwise. Either way, always a valid PNG at the exact
     *  requested size, so the pass installs regardless of branding. */
    private function generateIcon(int $size): string
    {
        $im = imagecreatetruecolor($size, $size);
        imagesavealpha($im, true);
        [$r, $g, $b] = $this->brandColorRgbTriplet();
        $bg = imagecolorallocate($im, $r, $g, $b);
        imagefill($im, 0, 0, $bg);

        if ($logo = $this->loadCompanyLogoResource()) {
            // Fit within ~70% of the icon, leaving a margin so it
            // doesn't look cropped against the square edges.
            $maxDim = (int) round($size * 0.7);
            $this->compositeFitted($im, $logo, $size, $size, $maxDim, $maxDim);
            imagedestroy($logo);
        }

        ob_start();
        imagepng($im);
        $contents = ob_get_clean();
        imagedestroy($im);

        return $contents;
    }

    /** The optional wide `logo.png` shown top-left of the pass itself
     *  (distinct from `icon.png`, which is Wallet's app-icon-style
     *  thumbnail). Null when no company logo is uploaded or it's an
     *  unrasterizable format (SVG) — the caller simply omits the file,
     *  which Apple treats as fine since `logo.png` is optional. */
    private function generateLogo(int $width, int $height): ?string
    {
        $logo = $this->loadCompanyLogoResource();
        if (! $logo) {
            return null;
        }

        $im = imagecreatetruecolor($width, $height);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);

        $this->compositeFitted($im, $logo, $width, $height, $width, $height);
        imagedestroy($logo);

        ob_start();
        imagepng($im);
        $contents = ob_get_clean();
        imagedestroy($im);

        return $contents;
    }

    /** Scales `$src` to fit within `$maxW`x`$maxH` (preserving aspect
     *  ratio) and draws it centered onto `$canvas` (already `$canvasW`
     *  x `$canvasH`), alpha blended so a transparent-background logo
     *  composites cleanly over either a solid brand-color icon or a
     *  transparent logo.png canvas. */
    private function compositeFitted($canvas, $src, int $canvasW, int $canvasH, int $maxW, int $maxH): void
    {
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $scale = min($maxW / $srcW, $maxH / $srcH, 1);
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));
        $dstX = (int) round(($canvasW - $dstW) / 2);
        $dstY = (int) round(($canvasH - $dstH) / 2);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $src, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);
    }

    /**
     * Loads `company.logo_path` (Settings → Branding) as a GD image
     * resource, or null when there's no logo, the file's missing, or
     * it's a format GD can't rasterize (SVG — Apple's pass images must
     * be PNG anyway, so an SVG logo can't be used here regardless).
     *
     * @return \GdImage|null
     */
    private function loadCompanyLogoResource()
    {
        $company = Company::current();
        $path = $company?->logo_path;
        if (! $path) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($path);

        try {
            $im = @imagecreatefromstring($contents);
        } catch (\Throwable) {
            return null;
        }

        return $im ?: null;
    }

    /** @return array{0:int,1:int,2:int} */
    private function brandColorRgbTriplet(): array
    {
        $company = Company::current();
        $hex = ltrim((string) ($company?->brand_color ?: '#141414'), '#');
        if (strlen($hex) !== 6) {
            $hex = '141414';
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function hexToRgb(?string $hex, string $fallback): string
    {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) !== 6) {
            return $fallback;
        }

        return sprintf('rgb(%d,%d,%d)', hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    /**
     * PKCS#7 detached signature over the manifest, in DER — Apple
     * requires DER, but `openssl_pkcs7_sign()` only ever writes PEM
     * (base64, with `-----BEGIN PKCS7-----` headers and mail-style
     * headers above that), so the PEM block has to be extracted and
     * base64-decoded by hand. This is the one genuinely fragile step
     * in this pipeline — confirmed against a real .p12 during
     * verification, not just assumed to work.
     */
    private function sign(string $manifestJson, Company $company): string
    {
        $certPath = Storage::disk('local')->path(\App\Http\Controllers\Admin\AppleWalletSettingsController::CERT_PATH);
        $p12 = file_get_contents($certPath);
        if ($p12 === false) {
            throw new RuntimeException('Could not read the Apple Wallet certificate file.');
        }

        $password = (string) ($company->apple_cert_password ?? '');
        if (! openssl_pkcs12_read($p12, $certs, $password)) {
            throw new RuntimeException('Could not open the Apple Wallet certificate — check the password.');
        }

        $certResource = openssl_x509_read($certs['cert']);
        $keyResource  = openssl_pkey_get_private($certs['pkey']);

        $wwdrPath = resource_path('certs/AppleWWDRCAG4.pem');
        if (! is_file($wwdrPath)) {
            throw new RuntimeException('Apple WWDR intermediate certificate is missing from the app.');
        }

        $tmpIn  = tempnam(sys_get_temp_dir(), 'manifest-');
        $tmpOut = tempnam(sys_get_temp_dir(), 'signature-');
        file_put_contents($tmpIn, $manifestJson);

        $ok = openssl_pkcs7_sign(
            $tmpIn,
            $tmpOut,
            $certResource,
            $keyResource,
            [],
            PKCS7_BINARY | PKCS7_DETACHED,
            $wwdrPath,
        );

        @unlink($tmpIn);

        if (! $ok) {
            @unlink($tmpOut);
            throw new RuntimeException('Failed to sign the pass manifest.');
        }

        $pem = file_get_contents($tmpOut);
        @unlink($tmpOut);

        // Extract the base64 body between the PKCS7 markers and decode
        // to raw DER bytes — the actual bytes Apple's Wallet expects in
        // the `signature` file.
        if (! preg_match('/-----BEGIN PKCS7-----(.+?)-----END PKCS7-----/s', (string) $pem, $m)) {
            throw new RuntimeException('Unexpected signature format from openssl_pkcs7_sign().');
        }

        return base64_decode(str_replace(["\r", "\n"], '', $m[1]));
    }
}
