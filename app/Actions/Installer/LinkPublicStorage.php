<?php

namespace App\Actions\Installer;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Create the `public/storage` → `storage/app/public` symlink.
 *
 * Uploaded files (brand logo, product images, attract media) are written to
 * `storage/app/public` and served from `/storage/…`. Without the symlink every
 * one of those URLs 404s, so a fresh install shows broken images the moment
 * the owner uploads a logo.
 *
 * Idempotent, and safe to call on every install / migrate. Never throws — a
 * host with `symlink()` in `disable_functions` should still finish installing;
 * the caller decides how loudly to complain.
 */
class LinkPublicStorage
{
    /** @return array{ok: bool, status: string, message: string|null} */
    public function __invoke(): array
    {
        $link = public_path('storage');

        // A symlink left behind by a move, a restore, or a zip upload can point
        // nowhere. `is_dir()` is false for a broken link, so this is the one
        // case we clear before re-linking — otherwise `storage:link` sees a
        // path that exists and declines.
        if (is_link($link) && ! is_dir($link)) {
            @unlink($link);
        }

        if (is_link($link) || is_dir($link)) {
            return $this->result(true, 'exists');
        }

        if (! function_exists('symlink')) {
            return $this->result(false, 'symlink_disabled');
        }

        // A relative link survives the app being moved between paths, which is
        // the norm on shared hosting. Fall back to an absolute one on hosts
        // where relative links aren't supported.
        $attempts = [
            ['--force' => true, '--relative' => true],
            ['--force' => true],
        ];

        $error = null;

        foreach ($attempts as $options) {
            try {
                Artisan::call('storage:link', $options);

                if (is_link($link) || is_dir($link)) {
                    return $this->result(true, 'linked');
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->result(false, 'failed', $error);
    }

    /** @return array{ok: bool, status: string, message: string|null} */
    private function result(bool $ok, string $status, ?string $message = null): array
    {
        if (! $ok) {
            Log::warning('Could not create the public/storage symlink.', [
                'status'  => $status,
                'message' => $message,
            ]);
        }

        return ['ok' => $ok, 'status' => $status, 'message' => $message];
    }
}
