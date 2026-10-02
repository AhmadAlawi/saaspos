<?php

namespace App\Jobs;

use App\Models\CameraClipRequest;
use App\Models\CameraSettings;
use App\Services\Cameras\HikvisionClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Downloads + transcodes one {@see CameraClipRequest}'s window off the
 * request cycle — see the migration's doc-comment for why this is
 * queued instead of the page waiting on it directly. Moves the result
 * into `storage/app/public/camera-clips/{id}.mp4` (served as a normal
 * static file, same as the live-view HLS segments) and marks the
 * request `ready`; the file + row are deleted after 24h by
 * {@see \App\Console\Commands\CleanupCameraClipRequests}.
 */
class PrepareCameraClip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 280; // matches the queue-worker's --max-time in routes/console.php
    public int $tries = 1;     // a stuck/slow NVR pull isn't worth auto-retrying blind

    public function __construct(private readonly int $clipRequestId) {}

    public function handle(): void
    {
        $request = CameraClipRequest::find($this->clipRequestId);
        if (! $request || $request->status !== CameraClipRequest::STATUS_PENDING) {
            return; // deleted, or already handled
        }

        $request->update(['status' => CameraClipRequest::STATUS_PROCESSING]);

        try {
            $settings = CameraSettings::forStore($request->store_id);
            if (! $settings->exists) {
                throw new \RuntimeException('No camera configured for this store.');
            }

            $client = new HikvisionClient($settings);
            $matches = $client->search($request->channel, $request->window_start, $request->window_end);
            if (empty($matches)) {
                throw new \RuntimeException('No recording found for this time window.');
            }

            // Windows here are short (a sale's real transaction time, or
            // a ±5s event) — one match should cover it; if the NVR
            // split it across segment boundaries, the first is closest
            // to the actual start.
            $tmpPath = $client->downloadTranscodedClip($matches[0]['playback_uri']);

            $finalRelative = "camera-clips/{$request->id}.mp4";
            Storage::disk('public')->makeDirectory('camera-clips');
            rename($tmpPath, Storage::disk('public')->path($finalRelative));

            $request->update([
                'status'     => CameraClipRequest::STATUS_READY,
                'file_path'  => $finalRelative,
                'expires_at' => now()->addDay(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Camera clip preparation failed.', [
                'clip_request_id' => $request->id,
                'error'           => $e->getMessage(),
            ]);
            $request->update([
                'status'        => CameraClipRequest::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'expires_at'    => now()->addDay(), // still clean up the failed row eventually
            ]);
        }
    }
}
