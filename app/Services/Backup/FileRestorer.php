<?php

namespace App\Services\Backup;

/**
 * Copies a backed-up file tree (e.g. `storage/app/public`, `public/uploads`)
 * back onto the live filesystem, overwriting existing files.
 *
 * This overwrites rather than mirror-deletes: files present on disk but absent
 * from the backup are left alone. For the updater's rollback (same install,
 * minutes apart) that drift is negligible; a future slice can add strict
 * mirroring if cross-install restores need it.
 */
class FileRestorer
{
    /** @return int the number of files written */
    public function restore(string $sourceDir, string $targetDir): int
    {
        if (! is_dir($sourceDir)) {
            return 0;
        }
        if (! is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $count = 0;
        $iter  = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iter as $file) {
            if ($file->isDir()) {
                continue;
            }
            $rel  = substr($file->getPathname(), strlen($sourceDir) + 1);
            $dest = $targetDir.'/'.$rel;

            $destDir = dirname($dest);
            if (! is_dir($destDir)) {
                @mkdir($destDir, 0775, true);
            }
            copy($file->getPathname(), $dest);
            $count++;
        }

        return $count;
    }
}
