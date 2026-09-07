<?php

namespace App\Services\Pwa;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the PWA install icons from the company's uploaded app logo.
 *
 * The web manifest can't point at the raw upload: browsers want square PNGs at
 * declared sizes, and an installed app also needs a `maskable` variant (the OS
 * crops it to a circle / squircle, so the artwork must sit inside a ~80% safe
 * zone or the edges get shaved off). A logo is typically wide and transparent,
 * so we letterbox it onto a square canvas instead of stretching it.
 *
 * Output is written once to the public disk under `pwa-icons/`, named by a hash
 * of the source file. That makes each URL immutable — the browser (and the
 * service worker's stale-while-revalidate rule for `/storage/*`) can cache it
 * forever, and swapping the logo mints a fresh URL so the installed icon
 * actually updates instead of serving a stale one.
 *
 * Degrades to null whenever it can't do the job (no logo, GD missing on the
 * host, unreadable/corrupt image). The caller then falls back to the generic
 * icons shipped in `public/icons/`, so a shared-hosting box without GD still
 * installs as a PWA — just with the default artwork.
 */
class AppIconGenerator
{
    /** Sizes the manifest declares for `purpose: any`. */
    public const ANY_SIZES = [96, 192, 512];

    /** The maskable icon is always 512 — one size covers every launcher. */
    public const MASKABLE_SIZE = 512;

    /** Fraction of the maskable canvas the artwork may occupy (safe zone). */
    private const MASKABLE_SCALE = 0.8;

    private const DIR = 'pwa-icons';

    /**
     * Public URLs for the generated icon set, or null when we can't generate.
     *
     * @return array{any: array<int, string>, maskable: string}|null
     */
    public function urlsFor(?Company $company): ?array
    {
        $path = $company?->app_logo_path;

        if (! $path || ! extension_loaded('gd')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        // Hash the SOURCE, not the output — same logo, same URLs, no rework.
        $stamp = sha1($path.'|'.$disk->size($path).'|'.$disk->lastModified($path));
        $key   = substr($stamp, 0, 12);

        $any = [];
        foreach (self::ANY_SIZES as $size) {
            $url = $this->ensure($disk, $path, $key, $size, maskable: false);
            if ($url === null) {
                return null;   // partial sets are worse than the fallback
            }
            $any[$size] = $url;
        }

        $maskable = $this->ensure($disk, $path, $key, self::MASKABLE_SIZE, maskable: true, background: $company?->brand_color);
        if ($maskable === null) {
            return null;
        }

        return ['any' => $any, 'maskable' => $maskable];
    }

    /** Generate the icon if it isn't already on disk; return its public URL. */
    private function ensure(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $sourcePath,
        string $key,
        int $size,
        bool $maskable,
        ?string $background = null,
    ): ?string {
        $target = self::DIR."/{$key}-{$size}".($maskable ? '-maskable' : '').'.png';

        if ($disk->exists($target)) {
            return $disk->url($target);
        }

        $png = $this->render($disk->get($sourcePath), $size, $maskable, $background);
        if ($png === null) {
            return null;
        }

        $disk->put($target, $png);

        return $disk->url($target);
    }

    /**
     * Draw the logo, centred and aspect-preserved, onto a square PNG canvas.
     * Returns the encoded PNG bytes, or null if GD can't read the source.
     */
    private function render(string $sourceBytes, int $size, bool $maskable, ?string $background): ?string
    {
        $src = @imagecreatefromstring($sourceBytes);
        if ($src === false) {
            return null;
        }

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        if ($maskable) {
            // Launchers crop maskable icons, so a transparent background would
            // show the OS's own colour through the corners. Paint it solid.
            [$r, $g, $b] = $this->rgb($background) ?? [255, 255, 255];
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, $r, $g, $b, 0));
        } else {
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }

        imagealphablending($canvas, true);

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        // Contain (never stretch); maskable art shrinks into the safe zone.
        $box   = $maskable ? (int) round($size * self::MASKABLE_SCALE) : $size;
        $scale = min($box / $srcW, $box / $srcH);
        $dstW  = max(1, (int) round($srcW * $scale));
        $dstH  = max(1, (int) round($srcH * $scale));
        $dstX  = (int) round(($size - $dstW) / 2);
        $dstY  = (int) round(($size - $dstH) / 2);

        imagecopyresampled($canvas, $src, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);

        ob_start();
        imagepng($canvas, null, 9);
        $png = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($src);

        return $png !== '' ? $png : null;
    }

    /**
     * `#rrggbb` / `#rgb` → [r, g, b]. Null for anything we can't parse, so the
     * caller falls back to white rather than rendering a black square.
     *
     * @return array{0:int,1:int,2:int}|null
     */
    private function rgb(?string $hex): ?array
    {
        $hex = ltrim(trim((string) $hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
