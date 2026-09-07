<?php

namespace App\Actions\Demo;

use App\Actions\Settings\RunBackup;
use App\Models\BackupLog;
use App\Actions\Settings\RunRestore;
use Illuminate\Support\Facades\Storage;

/**
 * Resets the public demo back to a clean baseline.
 *
 * The demo is deliberately NOT write-restricted — visitors can ring up sales,
 * add products, create customers, anything — because a demo you can't touch
 * isn't a demo. The trade-off is junk accumulation, which this solves the way
 * every public demo does: wipe and restore a known-good snapshot on a schedule
 * (nightly, see routes/console.php).
 *
 * The baseline is an ordinary backup (type 'demo-baseline') captured once with
 * `php artisan pos:demo-snapshot` right after a fresh seed. Resetting just
 * restores the newest such backup via {@see RunRestore} — the same destructive
 * machinery the restore wizard and updater rollback already use, so there is no
 * second restore path to maintain. `backup_logs` is one of RunRestore's
 * preserved tables, so the baseline row survives the restore it drives and the
 * next night's reset finds it again.
 *
 * No-ops (returns null) when not in demo mode, or when no baseline has been
 * captured yet — so wiring the scheduler before snapshotting can never nuke a
 * real install or fail loudly on a fresh box.
 */
class ResetDemoData
{
    public function __construct(
        private readonly RunRestore $runRestore,
    ) {
    }

    public const BASELINE_TYPE = 'demo-baseline';

    /**
     * @return \App\Models\RestoreLog|null  the restore log, or null when skipped
     */
    public function __invoke(?int $userId = null)
    {
        if (! pos_is_demo()) {
            return null;
        }

        $baseline = $this->baseline();
        if ($baseline === null) {
            return null;
        }

        do_action('demo.before_reset', $baseline);

        $zipPath = Storage::disk($baseline->destination ?: 'local')->path($baseline->file_path);

        $log = ($this->runRestore)(
            $zipPath,
            'demo-reset',
            false,           // no pre-restore snapshot — the baseline IS the snapshot
            $userId,
            $baseline->id,
        );

        do_action('demo.after_reset', $log, $baseline);

        return $log;
    }

    /** The newest successful baseline snapshot, or null if none captured yet. */
    public function baseline(): ?BackupLog
    {
        return BackupLog::query()
            ->successful()
            ->where('type', self::BASELINE_TYPE)
            ->whereNotNull('file_path')
            ->latest('id')
            ->first();
    }

    /**
     * Capture the current database state as THE demo baseline. Run once, right
     * after seeding the demo with the data you want every reset to return to.
     * Marker reference for {@see RunBackup}'s type column.
     */
    public function capture(?int $userId = null): BackupLog
    {
        return app(RunBackup::class)(self::BASELINE_TYPE, $userId);
    }
}
