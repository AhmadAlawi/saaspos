<?php

namespace App\Actions\Settings;

use App\Actions\Demo\ResetDemoData;
use App\Models\BackupLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes backup files older than the configured retention window so the
 * backup folder doesn't grow forever. Called after every successful
 * RunBackup and from the scheduler tick. Returns the number of files
 * removed.
 *
 * The demo baseline (a 'demo-baseline' backup the nightly reset restores) is
 * NEVER pruned — it isn't a rotating backup, and deleting it would leave the
 * reset pointing at a missing archive.
 */
class PruneBackups
{
    public function __invoke(): int
    {
        $settings = app_backup();
        $disk     = Storage::disk(array_key_exists($settings['target_disk'], config('filesystems.disks', [])) ? $settings['target_disk'] : 'local');
        $cutoff   = Carbon::now()->subDays(max(1, (int) $settings['retention_days']))->timestamp;

        // The demo baseline zip(s) are protected — retention must not touch them.
        $protected = BackupLog::query()
            ->where('type', ResetDemoData::BASELINE_TYPE)
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->all();

        $deleted = 0;
        foreach ($disk->files('backups') as $file) {
            if (! str_ends_with($file, '.zip')) {
                continue;
            }
            if (in_array($file, $protected, true)) {
                continue;
            }
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }
        return $deleted;
    }
}
