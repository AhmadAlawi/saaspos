<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Pwa\AppIconGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Serves `GET /manifest.json` — the PWA web app manifest.
 *
 * This used to be a static file in `public/`, which meant an installed desktop
 * app always showed the generic placeholder artwork and the literal name "POS",
 * no matter what the store had branded itself as. The manifest is what the OS
 * reads at install time, so it has to be rendered from the company row.
 *
 * Public + auth-free: the browser fetches the manifest without credentials
 * (no `crossorigin` attribute on the <link>), and the install prompt can fire
 * on the login page before anyone has signed in. It exposes nothing sensitive —
 * the store's name, brand colour, and logo, all of which are already on screen.
 *
 * Falls back to the shipped `public/icons/*` artwork whenever a logo can't be
 * rendered (no logo uploaded, GD missing on a shared host, corrupt upload), so
 * the app always remains installable.
 */
class PwaManifestController extends Controller
{
    /** Used when the company has no brand colour set. */
    private const DEFAULT_THEME = '#3b82f6';

    public function __invoke(AppIconGenerator $icons): JsonResponse
    {
        // `Company::current()` must not explode on a fresh, un-migrated install
        // — the manifest is reachable before the installer has finished.
        $company = rescue(fn () => Company::current(), null, report: false);

        $name  = $company?->display_app_name ?: (string) config('pos.name', 'POS');
        $theme = $this->hexOrNull($company?->brand_color) ?? self::DEFAULT_THEME;

        // Generate (or reuse) the branded icon set once — null when we can't,
        // in which case both helpers below fall back to the shipped artwork.
        $iconUrls = $icons->urlsFor($company);

        $manifest = [
            'id'               => '/',
            'name'             => $name,
            'short_name'       => Str::limit($name, 12, ''),
            'description'      => __('pwa.description', ['app' => $name]),
            'start_url'        => '/',
            'scope'            => '/',
            'display'          => 'standalone',
            'background_color' => '#0a0a0a',
            'theme_color'      => $theme,
            'orientation'      => 'any',
            'icons'            => $this->icons($iconUrls),
            'screenshots'      => [
                [
                    'src'         => '/icons/screenshot-wide.png',
                    'sizes'       => '1280x800',
                    'type'        => 'image/png',
                    'form_factor' => 'wide',
                    'label'       => $name,
                ],
                [
                    'src'         => '/icons/screenshot-narrow.png',
                    'sizes'       => '720x1280',
                    'type'        => 'image/png',
                    'form_factor' => 'narrow',
                    'label'       => $name,
                ],
            ],
            'shortcuts' => $this->shortcuts($iconUrls),
        ];

        return response()
            ->json($manifest, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            // The correct media type; browsers accept application/json too, but
            // this is what the spec asks for.
            ->header('Content-Type', 'application/manifest+json')
            // Short cache: a re-branded store should see its new icon soon, but
            // we don't want to regenerate on every page view either.
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * The manifest `icons` array — the company logo when we could render it,
     * otherwise the generic artwork shipped in public/icons.
     *
     * @param  array{any: array<int, string>, maskable: string}|null  $urls
     * @return list<array<string, string>>
     */
    private function icons(?array $urls): array
    {
        if ($urls === null) {
            return [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ];
        }

        $icons = [];
        foreach ($urls['any'] as $size => $src) {
            $icons[] = ['src' => $src, 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any'];
        }
        $icons[] = [
            'src'     => $urls['maskable'],
            'sizes'   => AppIconGenerator::MASKABLE_SIZE.'x'.AppIconGenerator::MASKABLE_SIZE,
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ];

        return $icons;
    }

    /**
     * @param  array{any: array<int, string>, maskable: string}|null  $urls
     * @return list<array<string, mixed>>
     */
    private function shortcuts(?array $urls): array
    {
        $small = $urls['any'][96] ?? '/icons/icon-96.png';
        $icon  = [['src' => $small, 'sizes' => '96x96', 'type' => 'image/png']];

        return [
            [
                'name'       => __('pwa.shortcuts.dashboard'),
                'short_name' => __('pwa.shortcuts.dashboard_short'),
                'url'        => '/admin',
                'icons'      => $icon,
            ],
            [
                'name'       => __('pwa.shortcuts.sale'),
                'short_name' => __('pwa.shortcuts.sale_short'),
                'url'        => '/cashier',
                'icons'      => $icon,
            ],
        ];
    }

    /** Accept only a real hex colour — a junk value must not poison the manifest. */
    private function hexOrNull(?string $hex): ?string
    {
        $hex = trim((string) $hex);

        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex) ? $hex : null;
    }
}
