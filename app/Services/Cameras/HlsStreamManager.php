<?php

namespace App\Services\Cameras;

use App\Models\CameraSettings;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * RTSP → HLS live streaming (beta). Each (store, channel) pair gets its
 * own ffmpeg process pulling the NVR's RTSP feed and writing rolling
 * HLS segments to `storage/app/public/hls/{key}/` — served as plain
 * static files by nginx via the existing `public/storage` symlink, so
 * a viewer's `<video>` tag (via hls.js) never touches PHP once the
 * playlist URL is handed back.
 *
 * ffmpeg is launched fully detached (`nohup … &`) so it keeps running
 * after the PHP-FPM worker that started it finishes the request — it's
 * meant to keep feeding segments for as long as someone's watching,
 * independent of any one HTTP request's lifetime. Nothing here decides
 * when to STOP it; that's {@see \App\Console\Commands\CleanupCameraStreams},
 * which kills any stream nobody's watching anymore.
 *
 * "Nobody's watching" is tracked via a dedicated `last_viewed` marker
 * file, NOT the playlist's own mtime — ffmpeg rewrites `stream.m3u8`
 * itself every segment (`-hls_flags delete_segments`) whether or not
 * anyone's watching, so the playlist's mtime reflects "ffmpeg is
 * alive," not "someone is watching." Only `ensure()` touches the
 * marker, so its age is the actual viewer signal.
 *
 * These cameras shoot H.265/HEVC at up to 4K — `-c:v copy` (no
 * re-encoding) would be far cheaper, but HEVC-in-HLS only plays in
 * recent Safari; Chrome/Firefox/Edge won't touch it. So this actually
 * transcodes to H.264 at a capped resolution (`libx264`, `veryfast`
 * preset, scaled to 1280px wide) — real CPU cost per active stream,
 * unlike a pure repackage, which is why CleanupCameraStreams killing
 * anything nobody's watching matters here more than it would for a
 * cheap copy. Audio is dropped (`-an`) — a security-camera feed
 * doesn't need it, and Hikvision's usual G.711 isn't HLS-compatible
 * anyway.
 */
class HlsStreamManager
{
    private const SEGMENT_SECONDS = 2;
    private const PLAYLIST_SIZE   = 4;

    public function __construct(private readonly CameraSettings $settings) {}

    public function key(int $channel): string
    {
        return "store{$this->settings->store_id}-ch{$channel}";
    }

    /**
     * Starts ffmpeg for this channel if it isn't already running, and
     * returns the public playlist URL. Touches the `last_viewed`
     * marker on every call (even when already running) — that's the
     * actual "someone is watching" signal CleanupCameraStreams reads.
     */
    public function ensure(int $channel): string
    {
        if (! $this->settings->exists || ! $this->settings->host) {
            throw new RuntimeException('Camera integration is not configured for this store.');
        }

        $key = $this->key($channel);
        $dir = storage_path("app/public/hls/{$key}");
        $playlist = "{$dir}/stream.m3u8";
        $lastViewed = "{$dir}/last_viewed";

        if (is_file($playlist)) {
            touch($lastViewed);
            $this->waitForFreshSegment($playlist);

            return $this->publicUrl($key);
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        touch($lastViewed);

        $this->launchFfmpeg($channel, $dir);
        $this->waitForFreshSegment($playlist, startedFresh: true);

        return $this->publicUrl($key);
    }

    private function launchFfmpeg(int $channel, string $dir): void
    {
        $trackId = $channel * 100 + 1;
        $rtspUrl = sprintf(
            'rtsp://%s:%s@%s:%d/Streaming/Channels/%d',
            rawurlencode((string) $this->settings->username),
            rawurlencode((string) $this->settings->password),
            $this->settings->host,
            $this->settings->rtsp_port ?: 554,
            $trackId,
        );

        $playlist = "{$dir}/stream.m3u8";
        $log      = "{$dir}/ffmpeg.log";

        $cmd = sprintf(
            'ffmpeg -rtsp_transport tcp -i %s -an -vf %s -c:v libx264 -preset veryfast -tune zerolatency '
            .'-crf 26 -g 40 -f hls -hls_time %d -hls_list_size %d -hls_flags delete_segments+omit_endlist %s',
            escapeshellarg($rtspUrl),
            escapeshellarg('scale=1280:-2'),
            self::SEGMENT_SECONDS,
            self::PLAYLIST_SIZE,
            escapeshellarg($playlist),
        );

        // Fully detached — must keep running after this request ends.
        // See class doc-comment for why. PID saved so
        // CleanupCameraStreams can kill the exact process later.
        $pidFile = "{$dir}/ffmpeg.pid";
        $detached = sprintf('nohup %s > %s 2>&1 & echo $! > %s', $cmd, escapeshellarg($log), escapeshellarg($pidFile));
        shell_exec($detached);
    }

    /** Poll briefly for the first (or a refreshed) segment so the
     *  caller doesn't hand hls.js a URL that 404s on its first fetch. */
    private function waitForFreshSegment(string $playlist, bool $startedFresh = false): void
    {
        $deadline = microtime(true) + ($startedFresh ? 8.0 : 2.0);
        while (! is_file($playlist) && microtime(true) < $deadline) {
            usleep(200_000);
        }
    }

    private function publicUrl(string $key): string
    {
        return Storage::disk('public')->url("hls/{$key}/stream.m3u8");
    }
}
