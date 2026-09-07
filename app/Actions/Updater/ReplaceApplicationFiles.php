<?php

namespace App\Actions\Updater;

use RuntimeException;

/**
 * Copies the extracted release over the live install, skipping the protected
 * paths that hold customer data + local config (docs/features/updater-hooks.md
 * §5.2). Additive overwrite: files present on disk but absent from the release
 * are left in place (safer than a wholesale delete-then-copy).
 *
 * Crucially, every file it overwrites is first copied into `$rollbackDir`, and
 * every file it newly adds is recorded in `$rollbackDir/added.json`. That's
 * what makes the rollback real — restoring only the DB backup (as the spec
 * literally describes) would leave a half-replaced codebase behind.
 *
 * `$targetRoot` defaults to the live install but is injectable so tests run
 * against a sandbox and never touch the running app.
 */
class ReplaceApplicationFiles
{
    /**
     * @return int  number of files written
     */
    public function __invoke(string $sourceRoot, ?string $targetRoot = null, ?string $rollbackDir = null): int
    {
        $sourceRoot = $this->norm($sourceRoot);
        $targetRoot = $this->norm($targetRoot ?? base_path());
        $rollbackDir = $rollbackDir ? $this->norm($rollbackDir) : null;
        $protected  = $this->protectedPaths();

        $added = [];
        $count = 0;

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iter as $file) {
            $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($sourceRoot))), '/');
            if ($rel === '' || $this->isProtected($rel, $protected)) {
                continue;
            }

            $dest = $targetRoot.'/'.$rel;

            if ($file->isDir()) {
                if (! is_dir($dest)) {
                    @mkdir($dest, 0775, true);
                }
                continue;
            }

            if (is_file($dest)) {
                if ($rollbackDir) {
                    // Stash the current file for rollback. Moving it out (rather
                    // than copying) is safe — the release we're about to place
                    // carries its own copy of the new file.
                    $this->move($dest, $rollbackDir.'/'.$rel);
                }
            } else {
                $added[] = $rel;
            }

            if (! $this->move($file->getPathname(), $dest)) {
                throw new RuntimeException(__('updates.errors.replace_failed', ['file' => $rel]));
            }
            $count++;
        }

        if ($rollbackDir) {
            if (! is_dir($rollbackDir)) {
                @mkdir($rollbackDir, 0775, true);
            }
            file_put_contents($rollbackDir.'/added.json', json_encode($added));
        }

        return $count;
    }

    /**
     * Move a file into place. `rename()` is a metadata-only operation on the
     * same filesystem, so replacing a large tree (vendor/ with mPDF's thousands
     * of font files) takes seconds instead of the many minutes a byte-for-byte
     * copy of every file needs. Falls back to copy+unlink only when source and
     * destination are on different mounts — rare, since the extracted release
     * lives under storage/, which sits on the app's filesystem on virtually
     * every host.
     */
    private function move(string $src, string $dest): bool
    {
        $dir = dirname($dest);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (@rename($src, $dest)) {
            return true;
        }

        // Cross-device fallback.
        if (@copy($src, $dest)) {
            @unlink($src);

            return true;
        }

        return false;
    }

    /** @param string[] $protected */
    private function isProtected(string $rel, array $protected): bool
    {
        foreach ($protected as $p) {
            if ($rel === $p || str_starts_with($rel, $p.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    private function protectedPaths(): array
    {
        $paths = apply_filters('updater.protected_paths', [
            '.env',
            'storage',
            'public/uploads',
            'public/storage',
            'plugins',
            'database/database.sqlite',
        ]);

        return array_map(fn ($p) => trim(str_replace('\\', '/', (string) $p), '/'), (array) $paths);
    }

    private function norm(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
