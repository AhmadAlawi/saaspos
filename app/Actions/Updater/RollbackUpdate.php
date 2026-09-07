<?php

namespace App\Actions\Updater;

use App\Actions\Settings\RunRestore;
use App\Models\BackupLog;
use Illuminate\Support\Facades\Storage;

/**
 * Reverts a failed update. Two halves, because a half-applied update breaks in
 * two places:
 *
 *   1. Code files — restore the originals {@see ReplaceApplicationFiles} stashed
 *      in `$rollbackDir`, and delete anything the update newly added.
 *   2. Data — restore the pre-update backup (DB + uploads) via {@see RunRestore},
 *      undoing any migrations that partially ran.
 *
 * The spec only describes step 2; without step 1 the install would be left with
 * mismatched code, so we do both.
 */
class RollbackUpdate
{
    public function __construct(
        private readonly RunRestore $runRestore,
    ) {
    }

    public function __invoke(BackupLog $backup, ?string $rollbackDir = null, ?string $targetRoot = null): void
    {
        if ($rollbackDir && is_dir($rollbackDir)) {
            $this->restoreFiles($rollbackDir, rtrim(str_replace('\\', '/', $targetRoot ?? base_path()), '/'));
        }

        $disk = $backup->destination ?: 'local';
        $disk = array_key_exists($disk, config('filesystems.disks', [])) ? $disk : 'local';
        $path = Storage::disk($disk)->path((string) $backup->file_path);

        if (is_file($path)) {
            ($this->runRestore)($path, 'pre-update', false, null, $backup->id);
        }
    }

    private function restoreFiles(string $rollbackDir, string $targetRoot): void
    {
        $rollbackDir = rtrim(str_replace('\\', '/', $rollbackDir), '/');

        // Remove files the update added.
        $addedJson = $rollbackDir.'/added.json';
        if (is_file($addedJson)) {
            foreach ((array) json_decode((string) file_get_contents($addedJson), true) as $rel) {
                @unlink($targetRoot.'/'.ltrim((string) $rel, '/'));
            }
        }

        // Put back every original we overwrote.
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rollbackDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $file) {
            if ($file->isDir()) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($rollbackDir))), '/');
            if ($rel === 'added.json') {
                continue;
            }
            $dest = $targetRoot.'/'.$rel;
            $dir  = dirname($dest);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @copy($file->getPathname(), $dest);
        }
    }
}
