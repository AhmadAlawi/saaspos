<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Public marketing landing page, shown at "/" ONLY when demo mode is on
 * (POS_DEMO_MODE=true). On a real customer install the root route never
 * reaches here — it redirects straight to login / dashboard (see
 * routes/web.php). The page pitches the product to a visitor who landed
 * on the public demo and points them at the seeded sample accounts.
 *
 * Branding (logo, app name, accent color) is read from the company
 * singleton so the demo reflects whatever the operator configured, with
 * a graceful fall back to config defaults during the pre-install window.
 */
class LandingController extends Controller
{
    public function index(): View
    {
        $company = $this->resolveCompany();

        return view('landing.index', [
            'company'       => $company,
            'appName'       => $company?->display_app_name ?: config('app.name'),
            'websiteUrl'    => $company?->website ?: config('pos.demo.website_url'),
            'purchaseUrl'   => config('pos.demo.purchase_url'),
            'extendedUrl'   => config('pos.demo.extended_url') ?: config('pos.demo.purchase_url'),
            'whatsappUrl'   => config('pos.demo.support_whatsapp'),
            'techStack'     => $this->techStack(),
            'paymentMethods'=> $this->paymentMethods(),
            'galleryShots'  => $this->sliderShots(),
            // Real product screenshots, used when present; the hero + showcase
            // fall back to a CSS mockup when they're not, so the page never
            // shows a broken image on a fresh install.
            'cashierShot'   => $this->screenshot('cashier'),
            'dashboardShot' => $this->screenshot('dashboard'),
            // Optional hero artwork dropped in images/landing/hero/. When a
            // monitor mockup is present the hero swaps to a device composition;
            // the barcode / receipt cut-outs float in as accents if supplied.
            // When present, this single image replaces the whole device
            // composition (monitor + tablet) — handy for previewing artwork.
            'heroMain'      => $this->findAsset('images/landing/hero/main-image', ['png', 'webp', 'jpg', 'svg']),
            'heroMonitor'   => $this->findAsset('images/landing/hero/monitor', ['png', 'webp', 'jpg', 'svg']),
            'heroTablet'    => $this->findAsset('images/landing/hero/tablet', ['png', 'webp', 'jpg', 'svg']),
            'heroBarcode'   => $this->findAsset('images/landing/hero/barcode', ['png', 'webp', 'svg']),
            'heroReceipt'   => $this->findAsset('images/landing/hero/receipt', ['png', 'webp', 'svg']),
        ]);
    }

    /**
     * The stack badges for the "built with" section. Each entry resolves an
     * optional real logo at public/images/landing/tech/<slug>.(svg|png|webp);
     * when none is present the view falls back to a styled wordmark, so the
     * section looks intentional with or without logo files.
     */
    private function techStack(): array
    {
        // `file` is the base name (no extension) under
        // images/landing/technologies/. findAsset() tries the common
        // formats, so a .png / .webp / .svg all resolve.
        $items = [
            ['slug' => 'laravel',     'label' => 'Laravel',      'file' => 'laravel'],
            ['slug' => 'php',         'label' => 'PHP',          'file' => 'php'],
            ['slug' => 'mysql',       'label' => 'MySQL',        'file' => 'mysql'],
            ['slug' => 'tailwindcss', 'label' => 'Tailwind CSS', 'file' => 'tailwind'],
            ['slug' => 'alpinejs',    'label' => 'Alpine.js',    'file' => 'alpline'],
            ['slug' => 'vite',        'label' => 'Vite',         'file' => 'vite-icon'],
        ];

        return array_map(function (array $i): array {
            $i['logo'] = $this->findAsset("images/landing/technologies/{$i['file']}", ['svg', 'webp', 'png', 'jpg']);

            return $i;
        }, $items);
    }

    /**
     * Payment method chips. Each resolves an optional logo at
     * images/landing/gateways/<slug>.(svg|png|webp|jpg); the view falls back
     * to a text chip until a logo is dropped in, so the operator can add
     * gateway images later without any code change.
     */
    private function paymentMethods(): array
    {
        $items = [
            ['slug' => 'cash',        'label' => 'Cash'],
            ['slug' => 'cards',       'label' => 'Cards'],
            ['slug' => 'stripe',      'label' => 'Stripe'],
            ['slug' => 'razorpay',    'label' => 'Razorpay'],
            ['slug' => 'paystack',    'label' => 'Paystack'],
            ['slug' => 'flutterwave', 'label' => 'Flutterwave'],
            ['slug' => 'mercadopago', 'label' => 'Mercado Pago'],
            ['slug' => 'qr',          'label' => 'QR'],
        ];

        return array_map(function (array $i): array {
            $i['logo'] = $this->findAsset("images/landing/gateways/{$i['slug']}", ['svg', 'webp', 'png', 'jpg']);

            return $i;
        }, $items);
    }

    /**
     * Auto-discover gallery screenshots dropped in images/landing/slider
     * (either the `public` storage disk or public/images/landing/slider).
     * Returns [['url' => …, 'label' => …], …] sorted by file name, so the
     * operator just uploads images and the gallery fills itself — no code
     * change per screenshot. Labels are humanised file names ("Stock levels").
     */
    private function sliderShots(): array
    {
        $dir   = 'images/landing/slider';
        $exts  = ['png', 'jpg', 'jpeg', 'webp'];
        $found = [];   // keyed by basename so the same file can't appear twice

        try {
            foreach (Storage::disk('public')->files($dir) as $path) {
                if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $exts, true)) {
                    $found[basename($path)] = $this->publicUrl('storage/' . $path);
                }
            }
        } catch (\Throwable) {
            // public disk unavailable — fall through to the public/ scan.
        }

        foreach (glob(public_path($dir . '/*')) ?: [] as $abs) {
            $name = basename($abs);
            if (! isset($found[$name]) && is_file($abs)
                && in_array(strtolower(pathinfo($abs, PATHINFO_EXTENSION)), $exts, true)) {
                $found[$name] = $this->publicUrl($dir . '/' . $name);
            }
        }

        uksort($found, 'strnatcasecmp');

        return array_map(fn (string $url, string $name): array => [
            'url'   => $url,
            'label' => pathinfo($name, PATHINFO_FILENAME),
        ], $found, array_keys($found));
    }

    /**
     * Build a public URL from a relative path, encoding each segment so file
     * names with spaces ("Drug Schedules.png") resolve correctly.
     */
    private function publicUrl(string $rel): string
    {
        return asset(implode('/', array_map('rawurlencode', explode('/', $rel))));
    }

    /**
     * Resolve a marketing screenshot to a URL, or null when none exists.
     * Checks the three places an operator might realistically drop it, so
     * it "just works" wherever the file landed:
     *   1. public/images/landing/<name>.<ext>            (recommended)
     *   2. the `public` storage disk (the storage symlink), e.g. via
     *      public/storage/images/landing/<name>.<ext>
     *   3. a real public/storage/images/landing/... folder (no symlink)
     * Accepts the common web formats (png / jpg / jpeg / webp).
     */
    private function screenshot(string $name): ?string
    {
        return $this->findAsset("images/landing/{$name}", ['png', 'jpg', 'jpeg', 'webp']);
    }

    /**
     * Resolve the first existing file for <base>.<ext> across the realistic
     * upload locations, returning its public URL or null. The `public`
     * storage disk maps to /storage, so a hit there resolves to
     * asset("storage/<rel>") — no Filesystem::url() needed.
     */
    private function findAsset(string $base, array $exts): ?string
    {
        foreach ($exts as $ext) {
            $rel = "{$base}.{$ext}";

            // 1. public/<rel>  (recommended)
            if (is_file(public_path($rel))) {
                return asset($rel);
            }

            // 2/3. the storage symlink — both the `public` disk and a literal
            //      public/storage/<rel> folder surface at /storage/<rel>.
            try {
                if (Storage::disk('public')->exists($rel) || is_file(public_path("storage/{$rel}"))) {
                    return asset("storage/{$rel}");
                }
            } catch (\Throwable) {
                if (is_file(public_path("storage/{$rel}"))) {
                    return asset("storage/{$rel}");
                }
            }
        }

        return null;
    }

    /**
     * Load the company singleton when the schema is ready. Mirrors the
     * pre-install guard used by AuthLayout::loadBranding() so a fresh
     * download (no `company` table yet) doesn't 500.
     */
    private function resolveCompany(): ?Company
    {
        try {
            if (! Schema::hasTable('company')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return Company::current();
    }
}
