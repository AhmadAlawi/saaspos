<?php

namespace App\Services\Backup;

use App\Exceptions\InvalidBackupArchive;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Unzips a backup archive into a throwaway working directory under
 * `storage/app/restore-tmp/` and tears it down again afterwards.
 *
 * Encryption/decryption is intentionally out of scope for this slice — the
 * archives RunBackup produces today are unencrypted, and the manifest records
 * `encryption.encrypted = false`.
 */
class Extractor
{
    public function extract(string $zipPath): string
    {
        if (! is_file($zipPath)) {
            throw new InvalidBackupArchive(__('restore.errors.archive_missing'));
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new InvalidBackupArchive(__('restore.errors.archive_unreadable'));
        }

        $dir = $this->makeWorkDir();
        if (! $zip->extractTo($dir)) {
            $zip->close();
            $this->cleanup($dir);
            throw new InvalidBackupArchive(__('restore.errors.archive_unreadable'));
        }
        $zip->close();

        return $dir;
    }

    public function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }

    private function makeWorkDir(): string
    {
        $base = storage_path('app/restore-tmp');
        if (! is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        $dir = $base.'/'.Str::random(16);
        @mkdir($dir, 0775, true);

        return $dir;
    }
}
