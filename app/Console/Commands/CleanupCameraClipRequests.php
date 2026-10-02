<?php

namespace App\Console\Commands;

use App\Models\CameraClipRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes prepared camera clips (and their DB rows) once
 * `expires_at` passes — kept for 24h from the moment
 * {@see \App\Jobs\PrepareCameraClip} finishes (or fails), then removed
 * to free disk space. Scheduled hourly (routes/console.php) — this
 * doesn't need minute-level precision the way the live-stream cleanup
 * does.
 */
class CleanupCameraClipRequests extends Command
{
    protected $signature = 'cameras:cleanup-clips';
    protected $description = 'Delete camera clip files/rows past their 24h expiry.';

    public function handle(): int
    {
        $expired = CameraClipRequest::query()->where('expires_at', '<', now())->get();

        foreach ($expired as $clip) {
            if ($clip->file_path) {
                Storage::disk('public')->delete($clip->file_path);
            }
            $clip->delete();
        }

        if ($expired->isNotEmpty()) {
            $this->info("Removed {$expired->count()} expired camera clip(s).");
        }

        return self::SUCCESS;
    }
}
