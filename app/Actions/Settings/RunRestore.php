<?php

namespace App\Actions\Settings;

use App\Models\RestoreLog;
use App\Services\Backup\DatabaseRestorer;
use App\Services\Backup\Extractor;
use App\Services\Backup\FileRestorer;
use App\Services\Backup\ManifestValidator;
use Illuminate\Support\Facades\Artisan;

/**
 * Restores the install from a backup archive — the destructive twin of
 * {@see RunBackup}, and the rollback mechanism the updater leans on.
 *
 * Flow (see docs/features/backup-restore.md §6):
 *   1. extract the zip to a temp dir
 *   2. validate manifest + checksum + version compatibility (NON-destructive)
 *   3. take an optional pre-restore safety snapshot
 *   4. enter maintenance mode
 *   5. drop every table, then replay the dump
 *   6. restore the user file trees
 *   7. migrate forward (bridges an older-than-current backup)
 *   8. clear caches, exit maintenance mode
 *
 * Everything from step 5 onward is destructive; a failure there is reported
 * with the pre-restore snapshot available for recovery. Steps 1-3 failing
 * leave the live install untouched.
 *
 * Runs synchronously. Maintenance mode is skipped under the test runner so it
 * can't strand the rest of the suite behind a 503.
 */
class RunRestore
{
    /**
     * Operational/audit tables kept continuous across a restore: their live
     * rows overlay the backup's, so the backup/restore/update history — and
     * this restore's own log row — survive being reverted.
     */
    private const PRESERVE_TABLES = ['backup_logs', 'restore_logs', 'update_logs'];

    public function __construct(
        private readonly Extractor $extractor,
        private readonly ManifestValidator $validator,
        private readonly DatabaseRestorer $databaseRestorer,
        private readonly FileRestorer $fileRestorer,
        private readonly RunBackup $runBackup,
    ) {
    }

    /**
     * @param  string    $zipPath           absolute path to the backup archive
     * @param  string    $source            'upload' | 'local' (where it came from)
     * @param  bool      $preRestoreBackup  snapshot current data before replacing it
     * @param  int|null  $userId            initiating user
     * @param  int|null  $backupLogId       the backup_logs row, when restoring a known local backup
     */
    public function __invoke(
        string $zipPath,
        string $source = 'upload',
        bool $preRestoreBackup = true,
        ?int $userId = null,
        ?int $backupLogId = null,
    ): RestoreLog {
        do_action('restore.before_start', $zipPath, $source);

        $log = RestoreLog::create([
            'backup_log_id' => $backupLogId,
            'source'        => $source,
            'started_at'    => now(),
            'status'        => 'running',
            'created_by'    => $userId,
        ]);

        $useMaintenance = ! app()->runningUnitTests();
        $workDir        = null;

        try {
            // 1-2. Extract + validate. Both non-destructive: the live install
            // is untouched if either fails.
            $workDir = $this->extractor->extract($zipPath);
            $this->validator->validate($workDir);

            // 3. Safety snapshot of the current state before we replace it.
            if ($preRestoreBackup) {
                ($this->runBackup)('pre-restore', $userId);
                $log->update(['was_pre_restore_backup_created' => true]);
            }

            // 4. Everyone else waits while the data is swapped out. Snapshot the
            //    audit tables first so the history (and this restore's own log
            //    row) isn't reverted along with the rest of the database.
            $preserved = $this->databaseRestorer->snapshot(self::PRESERVE_TABLES);

            if ($useMaintenance) {
                Artisan::call('down');
            }

            // 5. Replace the database.
            $this->databaseRestorer->dropAllTables();
            $this->databaseRestorer->restore($workDir.'/database/dump.sql');

            // 6. Replace the user files.
            $this->fileRestorer->restore($workDir.'/storage/app/public', storage_path('app/public'));
            $this->fileRestorer->restore($workDir.'/public/uploads', public_path('uploads'));

            // 7. Bridge any schema gap (the backup may predate this code).
            //    Idempotent: a current-schema backup leaves nothing pending.
            Artisan::call('migrate', ['--force' => true]);

            // 7b. Re-apply the preserved audit rows on top of the restored data.
            $this->databaseRestorer->overlay($preserved);

            // 8. Caches, then back online.
            $this->clearCaches();
            if ($useMaintenance) {
                Artisan::call('up');
            }

            $this->extractor->cleanup($workDir);

            $log->update(['finished_at' => now(), 'status' => 'success']);
            do_action('restore.completed', $log);

            return $log->fresh();
        } catch (\Throwable $e) {
            if ($useMaintenance) {
                try {
                    Artisan::call('up');
                } catch (\Throwable) {
                }
            }
            if ($workDir) {
                try {
                    $this->extractor->cleanup($workDir);
                } catch (\Throwable) {
                }
            }

            $log->update([
                'finished_at'   => now(),
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            do_action('restore.failed', $log, $e);

            throw $e;
        }
    }

    private function clearCaches(): void
    {
        foreach (['cache:clear', 'config:clear', 'view:clear', 'route:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (\Throwable) {
                // Cache clearing is best-effort — never fail a successful restore over it.
            }
        }
    }
}
