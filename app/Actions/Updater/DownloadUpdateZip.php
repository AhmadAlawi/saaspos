<?php

namespace App\Actions\Updater;

use App\Services\Updater\UpdateClient;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Downloads a release's zip (and its detached signature, if any) into a fresh
 * per-version work dir under storage/app/updates/. Non-destructive — it only
 * fetches files; verification and application happen in later steps.
 *
 * The signature is taken from the release's sidecar `signature_url` (a file
 * containing the base64 signature), or an inline `signature` field if the feed
 * embeds it. Returns [zipPath, signatureBase64|null].
 */
class DownloadUpdateZip
{
    public function __construct(
        private readonly UpdateClient $client,
    ) {
    }

    /**
     * @param  array<string,mixed>  $release  a feed release object
     * @return array{0:string,1:?string}
     */
    public function __invoke(array $release): array
    {
        $url = (string) ($release['download_url'] ?? '');
        if ($url === '') {
            throw new RuntimeException(__('updates.errors.no_download_url'));
        }

        $version = (string) ($release['version'] ?? 'update');
        $dir     = storage_path('app/updates/'.(Str::slug($version) ?: 'update'));
        $zipPath = $dir.'/update.zip';

        if (! $this->client->download($url, $zipPath)) {
            throw new RuntimeException(__('updates.errors.download_failed'));
        }

        $signature = isset($release['signature']) ? (string) $release['signature'] : null;
        $sigUrl    = (string) ($release['signature_url'] ?? '');

        if (! $signature && $sigUrl !== '') {
            $sigPath = $dir.'/update.zip.sig';
            if ($this->client->download($sigUrl, $sigPath) && is_file($sigPath)) {
                $signature = trim((string) file_get_contents($sigPath));
            }
        }

        return [$zipPath, $signature !== '' ? $signature : null];
    }
}
