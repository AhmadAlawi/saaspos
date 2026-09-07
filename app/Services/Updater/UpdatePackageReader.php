<?php

namespace App\Services\Updater;

use RuntimeException;
use ZipArchive;

/**
 * Reads the `update.json` manifest a manual-upload package must carry at its
 * root (or inside a single wrapper folder):
 *
 *   { "version": "1.0.4", "min_php": "8.2", "min_mysql": "8.0", "sha256": "…" }
 *
 * Strict by design: no manifest, no install. Only `version` is required;
 * `min_php` / `min_mysql` drive the pre-flight checks, `sha256` (optional) is
 * an integrity check (manual zips aren't signed — see InstallUpdate).
 */
class UpdatePackageReader
{
    /**
     * @return array{version:string, min_php:?string, min_mysql:?string, sha256:?string}
     */
    public function read(string $zipPath): array
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException(__('updates.errors.invalid_package'));
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException(__('updates.errors.archive_unreadable'));
        }

        $json = $zip->getFromName('update.json');
        if ($json === false) {
            $json = $this->findWrapped($zip);
        }
        $zip->close();

        if (! is_string($json)) {
            throw new RuntimeException(__('updates.errors.invalid_package'));
        }

        $data = json_decode($json, true);
        if (! is_array($data) || empty($data['version'])) {
            throw new RuntimeException(__('updates.errors.invalid_package'));
        }

        return [
            'version'   => (string) $data['version'],
            'min_php'   => isset($data['min_php']) ? (string) $data['min_php'] : null,
            'min_mysql' => isset($data['min_mysql']) ? (string) $data['min_mysql'] : null,
            'sha256'    => isset($data['sha256']) ? (string) $data['sha256'] : null,
        ];
    }

    /** Find `<wrapper>/update.json` when the zip wraps everything in one folder. */
    private function findWrapped(ZipArchive $zip): string|false
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && preg_match('#^[^/]+/update\.json$#', $name) === 1) {
                return $zip->getFromName($name);
            }
        }

        return false;
    }
}
