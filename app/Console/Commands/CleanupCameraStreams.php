<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Kills any {@see \App\Services\Cameras\HlsStreamManager} ffmpeg
 * process nobody's watching anymore, and deletes its segment
 * directory. "Nobody's watching" = the `last_viewed` marker file is
 * stale — `ensure()` touches it on every viewer request, so a live
 * viewer keeps it fresh; once they navigate away or close the tab,
 * nothing touches it again and it ages past the threshold. NOT the
 * playlist's own mtime — ffmpeg rewrites that every segment
 * regardless of whether anyone's watching.
 *
 * Scheduled every minute (routes/console.php) — same cadence as the
 * app's other lightweight sweeps.
 */
class CleanupCameraStreams extends Command
{
    protected $signature = 'cameras:cleanup-streams';
    protected $description = 'Stop HLS camera streams nobody is watching and remove their segment files.';

    private const STALE_SECONDS = 20;

    public function handle(): int
    {
        $root = storage_path('app/public/hls');
        if (! is_dir($root)) {
            return self::SUCCESS;
        }

        $stopped = 0;
        foreach (glob("{$root}/*", GLOB_ONLYDIR) as $dir) {
            $lastViewed = "{$dir}/last_viewed";
            $pidFile    = "{$dir}/ffmpeg.pid";

            $stale = ! is_file($lastViewed) || (time() - filemtime($lastViewed)) > self::STALE_SECONDS;
            if (! $stale) {
                continue;
            }

            if (is_file($pidFile)) {
                $pid = (int) trim((string) file_get_contents($pidFile));
                if ($pid > 0) {
                    // -9: ffmpeg doesn't need a graceful HLS finalize here —
                    // the segments are getting deleted regardless.
                    @shell_exec('kill -9 '.$pid.' 2>/dev/null');
                }
            }

            File::deleteDirectory($dir);
            $stopped++;
        }

        if ($stopped > 0) {
            $this->info("Stopped {$stopped} idle camera stream(s).");
        }

        return self::SUCCESS;
    }
}
