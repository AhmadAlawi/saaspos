<?php

namespace App\Services\Cameras;

use App\Models\CameraSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin ISAPI client for the company's shared Hikvision NVR (beta — see
 * {@see \App\Jobs\PrepareCameraClip}). Digest auth,
 * raw XML request/response — no SDK, this is the whole surface area
 * Hikvision's own ISAPI documentation exposes for recording search +
 * download:
 *
 *   POST /ISAPI/ContentMgmt/search    → find recorded segments in a
 *                                        time window on one channel
 *   POST /ISAPI/ContentMgmt/download  → stream one of those segments
 *
 * Every call throws {@see RuntimeException} on anything but a clean
 * response — connection refused (NVR unreachable / wrong network),
 * 401 (wrong credentials or the account lacks remote/ISAPI permission),
 * and non-2xx are all surfaced as one exception type so the controller
 * doesn't need to special-case them; the message is what differs.
 */
class HikvisionClient
{
    public function __construct(private readonly CameraSettings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->is_active
            && filled($this->settings->host)
            && filled($this->settings->username);
    }

    /** @return array{model: string, name: string} */
    public function deviceInfo(): array
    {
        $response = $this->request()->get($this->url('/ISAPI/System/deviceInfo'));
        $this->assertOk($response);

        $xml = simplexml_load_string((string) $response->body());
        if ($xml === false) {
            throw new RuntimeException('NVR returned an unreadable response.');
        }

        return [
            'model' => (string) ($xml->model ?? ''),
            'name'  => (string) ($xml->deviceName ?? ''),
        ];
    }

    /**
     * Every camera the NVR knows about — id + whatever name it was
     * given when it was added (often generic, "Camera 01", which is
     * the whole reason the live-view grid exists: names alone don't
     * tell you which physical till a channel covers).
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function listChannels(): array
    {
        $response = $this->request()->get($this->url('/ISAPI/ContentMgmt/InputProxy/channels'));
        $this->assertOk($response);

        $xml = simplexml_load_string((string) $response->body());
        if ($xml === false) {
            throw new RuntimeException('NVR returned an unreadable channel list.');
        }

        $channels = [];
        foreach ($xml->InputProxyChannel ?? [] as $ch) {
            $channels[] = [
                'id'   => (int) ($ch->id ?? 0),
                'name' => (string) ($ch->name ?? ''),
            ];
        }

        return $channels;
    }

    /**
     * A single current JPEG frame from `$channel`'s main stream —
     * ISAPI's snapshot endpoint, not a video stream. Good enough to
     * confirm "which physical camera is channel N" (point it at each
     * register in turn and see what shows up) without the much bigger
     * lift of transcoding the NVR's RTSP feed into something a browser
     * can play continuously. The live-view UI just re-requests this
     * every second or two with a cache-busting query param.
     *
     * @return array{body: string, content_type: string}
     */
    public function snapshot(int $channel): array
    {
        $trackId = $channel * 100 + 1;
        $response = $this->request()->get($this->url("/ISAPI/Streaming/channels/{$trackId}/picture"));
        $this->assertOk($response);

        return [
            'body'         => (string) $response->body(),
            'content_type' => $response->header('Content-Type') ?: 'image/jpeg',
        ];
    }

    /**
     * Recorded segments for `$channel`'s main stream between `$from`
     * and `$to`. Hikvision's trackID convention: channel N, main
     * stream = N*100 + 1 (sub-stream would be N*100 + 2).
     *
     * `$from`/`$to` are sent as this NVR's OWN local wall-clock numbers
     * (Asia/Amman, no UTC conversion, despite the trailing `Z` the
     * device's URI format requires) — this NVR does not treat `Z` as a
     * UTC marker; sending UTC-converted values here was confirmed
     * against real footage to select the wrong recording by several
     * hours (device ignores the offset and reads the raw digits as its
     * own local time).
     *
     * This NVR records in large continuous chunks (~1.5-3 HOURS each,
     * confirmed against the real device) — a search always returns the
     * chunk's own full boundaries, not a clip near `$from`/`$to`.
     * `playback_uri`'s embedded `starttime`/`endtime` get rewritten
     * here to the requested window (clamped to the chunk's own
     * bounds) before handing it back, so `download()`/`play()` at
     * least ask the NVR for something closer to the actual
     * transaction instead of the whole multi-hour file — the device
     * doesn't honor this to the second (it snaps to its own internal
     * sub-boundaries, confirmed: a 2-minute request came back as
     * ~30 minutes), but it's a real, large reduction from the
     * multi-hour alternative, not a no-op.
     *
     * @return array<int, array{start: string, end: string, playback_uri: string}>
     */
    public function search(int $channel, Carbon $from, Carbon $to): array
    {
        $trackId = $channel * 100 + 1;
        $body = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <CMSearchDescription>
        <searchID>{$this->uuid()}</searchID>
        <trackList><trackID>{$trackId}</trackID></trackList>
        <timeSpanList>
        <timeSpan>
        <startTime>{$from->format('Y-m-d\TH:i:s\Z')}</startTime>
        <endTime>{$to->format('Y-m-d\TH:i:s\Z')}</endTime>
        </timeSpan>
        </timeSpanList>
        <maxResults>32</maxResults>
        <searchResultPosition>0</searchResultPosition>
        <metadataList><metadataDescriptor>//recordType.meta.std-cgi.com</metadataDescriptor></metadataList>
        </CMSearchDescription>
        XML;

        $response = $this->request()
            ->withBody($body, 'application/xml')
            ->post($this->url('/ISAPI/ContentMgmt/search'));
        $this->assertOk($response);

        $xml = simplexml_load_string((string) $response->body());
        if ($xml === false) {
            throw new RuntimeException('NVR returned an unreadable search response.');
        }

        $matches = [];
        foreach ($xml->matchList->searchMatchItem ?? [] as $item) {
            $segStart = (string) ($item->timeSpan->startTime ?? '');
            $segEnd   = (string) ($item->timeSpan->endTime ?? '');
            $rawUri   = (string) ($item->mediaSegmentDescriptor->playbackURI ?? '');

            $clampedStart = $segStart ? $from->clone()->max(Carbon::parse($segStart)) : $from;
            $clampedEnd   = $segEnd   ? $to->clone()->min(Carbon::parse($segEnd))     : $to;

            $matches[] = [
                'start'        => $clampedStart->toIso8601String(),
                'end'          => $clampedEnd->toIso8601String(),
                'playback_uri' => $this->clampPlaybackUri($rawUri, $clampedStart, $clampedEnd),
            ];
        }

        return $matches;
    }

    /** Rewrites the embedded `starttime`/`endtime` in a Hikvision playback
     *  URI — see search()'s doc-comment for why this matters. */
    private function clampPlaybackUri(string $uri, Carbon $start, Carbon $end): string
    {
        if ($uri === '') {
            return $uri;
        }

        $fmt = fn (Carbon $c) => $c->clone()->format('Ymd\THis\Z');

        return preg_replace(
            ['/starttime=[^&]*/', '/endtime=[^&]*/'],
            ['starttime='.$fmt($start), 'endtime='.$fmt($end)],
            $uri,
        );
    }

    /**
     * Streams a previously-found segment straight through — no
     * buffering, since a several-minute clip can run tens of MB and
     * this app's PHP-FPM workers have a real memory ceiling.
     *
     * Digest auth is done MANUALLY here (see digestHeader()) instead
     * of via `Http::withDigestAuth()`, which is what every other
     * method uses — confirmed against the real NVR that cURL's
     * built-in digest auto-retry does not fire correctly once
     * `stream => true` is also set (the exact same credentials that
     * work for every buffered request here, and via plain curl
     * outside this app entirely, come back 401 through
     * `withDigestAuth()` + `stream => true` together). Getting our own
     * challenge first and building the `Authorization` header by hand
     * sidesteps whatever that interaction is.
     *
     * @return array{stream: \Psr\Http\Message\StreamInterface, content_type: string}
     */
    public function downloadStream(string $playbackUri): array
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<downloadRequest><playbackURI>'.htmlspecialchars($playbackUri, ENT_XML1).'</playbackURI></downloadRequest>';

        $path = '/ISAPI/ContentMgmt/download';
        $challenge = $this->plainRequest()->post($this->url($path), []);
        $authHeader = $this->digestHeader($challenge, 'POST', $path);
        if (! $authHeader) {
            $this->assertOk($challenge);
            throw new RuntimeException('NVR did not challenge for digest auth as expected.');
        }

        $response = $this->plainRequest()
            ->withHeaders(['Authorization' => $authHeader])
            ->withOptions(['stream' => true])
            ->withBody($body, 'application/xml')
            ->post($this->url($path));
        $this->assertOk($response);

        return [
            // The NVR reports "Opaque/data" here, not a real MIME type —
            // a browser `<video>` tag won't know what to do with that.
            // We know this endpoint only ever returns video regardless
            // of what it claims, so force it rather than trust it.
            'stream'       => $response->toPsrResponse()->getBody(),
            'content_type' => 'video/mp4',
        ];
    }

    /**
     * Fetches the clip and transcodes it to browser-playable H.264 —
     * these recordings export as H.265/HEVC, same as the live feed
     * (confirmed against the real device), which most browsers can't
     * decode. Downloads to a temp file first rather than piping
     * straight into ffmpeg: these clips run 100-500MB and the NVR
     * rounds a request to its own internal chunk length (confirmed:
     * asking for 1 minute came back ~7.5), so this can take a while
     * regardless — a temp file keeps the ffmpeg invocation simple and
     * lets Laravel's file response support HTTP Range (video seeking)
     * for free, which a raw piped stream wouldn't.
     *
     * Scaled to 480p — fast enough to matter (a 7.5-minute clip
     * transcodes in ~40s at this size, tested against the real
     * device) and small enough to actually download comfortably.
     *
     * @return string absolute path to the transcoded mp4 — caller
     *                deletes it after serving
     */
    // Observed real-world bitrate for this NVR's 2560x1440 HEVC stream:
    // roughly 330KB/s. 3x that as a safety margin (variable bitrate,
    // motion-heavy footage) covers the ACTUAL requested duration
    // without downloading however much extra the NVR feels like
    // sending — that's what was previously a flat 200MB regardless of
    // whether the transaction was 1 minute or 10.
    private const ESTIMATED_BYTES_PER_SECOND = 330 * 1024 * 3;
    private const MIN_DOWNLOAD_BYTES = 20 * 1024 * 1024;  // floor for very short clips (encoder overhead, short GOPs)
    private const MAX_DOWNLOAD_BYTES = 200 * 1024 * 1024; // absolute ceiling regardless of duration

    public function downloadTranscodedClip(string $playbackUri): string
    {
        // The app's default PHP_MAX_EXECUTION_TIME (120s) can be too
        // short for a large multi-hundred-MB clip's download+transcode
        // combined — this request is explicitly a "wait for it" action
        // (there's a loading state in the UI), not a normal page load.
        set_time_limit(300);

        $durationSeconds = $this->durationFromUri($playbackUri);
        $byteCap = $durationSeconds
            ? (int) min(self::MAX_DOWNLOAD_BYTES, max(self::MIN_DOWNLOAD_BYTES, $durationSeconds * self::ESTIMATED_BYTES_PER_SECOND))
            : self::MAX_DOWNLOAD_BYTES;

        $result = $this->downloadStream($playbackUri);
        $stream = $result['stream'];

        // The NVR's own reported size is not a reliable bound (confirmed
        // against the real device: identical-shaped requests for
        // different segments came back anywhere from ~120MB to a full
        // ~1GB file, regardless of how tight the requested time window
        // was) — hard-capping the download itself is what actually
        // keeps this finishing in reasonable time. Sized to the
        // ACTUAL requested duration above rather than a flat ceiling,
        // so a short transaction downloads (and transcodes) fast; a
        // longer one gets silently TRUNCATED rather than the request
        // hanging for however long the NVR feels like sending.
        $tmpIn = tempnam(sys_get_temp_dir(), 'hik_in_');
        $fp = fopen($tmpIn, 'wb');
        $written = 0;
        try {
            while (! $stream->eof() && $written < $byteCap) {
                $chunk = $stream->read(65536);
                fwrite($fp, $chunk);
                $written += strlen($chunk);
            }
        } finally {
            fclose($fp);
            // Close the underlying HTTP connection explicitly — we may
            // have stopped short of eof() (hit the byte cap), so it
            // wouldn't otherwise get released until GC gets to it.
            $stream->close();
        }

        $tmpOut = $tmpIn.'_out.mp4';
        // `-t` right after `-i` caps how much of the INPUT ffmpeg even
        // reads, not just the output — belt-and-suspenders with the
        // byte cap above: if the NVR's download still ran long inside
        // that byte budget, this stops the actual transcode work at
        // the real requested duration instead of processing whatever
        // extra footage came along with it.
        $cmd = sprintf(
            'ffmpeg -y -i %s %s -vf %s -c:v libx264 -preset veryfast -crf 28 -an -movflags faststart %s 2>&1',
            escapeshellarg($tmpIn),
            $durationSeconds ? '-t '.escapeshellarg((string) $durationSeconds) : '',
            escapeshellarg('scale=854:-2'),
            escapeshellarg($tmpOut),
        );
        exec($cmd, $_output, $exitCode);
        @unlink($tmpIn);

        if ($exitCode !== 0 || ! is_file($tmpOut)) {
            @unlink($tmpOut);
            throw new RuntimeException('Could not prepare this clip for playback.');
        }

        return $tmpOut;
    }

    /** Duration in seconds between the `starttime`/`endtime` embedded
     *  in a (clamped, see search()) playback URI, or null if either is
     *  missing/unparseable — callers fall back to the flat max cap. */
    private function durationFromUri(string $uri): ?int
    {
        if (! preg_match('/starttime=([^&]+)/', $uri, $s) || ! preg_match('/endtime=([^&]+)/', $uri, $e)) {
            return null;
        }

        try {
            $start = Carbon::createFromFormat('Ymd\THis\Z', $s[1]);
            $end   = Carbon::createFromFormat('Ymd\THis\Z', $e[1]);
        } catch (\Throwable) {
            return null;
        }

        // diffInSeconds() returns a SIGNED difference here (confirmed
        // against the real Carbon version this app runs) — $end->
        // diffInSeconds($start) came back NEGATIVE for a normal
        // start-before-end window, which silently failed the `> 0`
        // check below and returned null on every real request. That
        // disabled BOTH the ffmpeg `-t` cap and the duration-based byte
        // cap, so downloadTranscodedClip() fell back to streaming until
        // EOF or the flat 200MB ceiling — explains clips running to
        // 20+ minutes, starting from wherever the NVR's underlying
        // recording chunk happened to begin instead of the requested
        // window.
        $seconds = $start && $end ? abs($start->diffInSeconds($end)) : 0;

        return $seconds > 0 ? $seconds : null;
    }

    /** Builds a manual RFC 2617 `Authorization: Digest …` header from a
     *  401 challenge response, or null if the response isn't one. */
    private function digestHeader(Response $challenge, string $method, string $path): ?string
    {
        $wwwAuth = $challenge->header('WWW-Authenticate');
        if ($challenge->status() !== 401 || ! $wwwAuth || ! str_starts_with($wwwAuth, 'Digest ')) {
            return null;
        }

        $parts = [];
        foreach (explode(',', substr($wwwAuth, 7)) as $piece) {
            if (preg_match('/(\w+)=(?:"([^"]*)"|([^\s,]+))/', trim($piece), $m)) {
                $parts[$m[1]] = $m[2] !== '' ? $m[2] : ($m[3] ?? '');
            }
        }

        $realm = $parts['realm'] ?? '';
        $nonce = $parts['nonce'] ?? '';
        $qop   = $parts['qop'] ?? '';
        $opaque = $parts['opaque'] ?? null;

        $username = (string) $this->settings->username;
        $password = (string) $this->settings->password;
        $cnonce = Str::random(16);
        $nc = '00000001';

        $ha1 = md5("{$username}:{$realm}:{$password}");
        $ha2 = md5("{$method}:{$path}");
        $response = $qop
            ? md5("{$ha1}:{$nonce}:{$nc}:{$cnonce}:{$qop}:{$ha2}")
            : md5("{$ha1}:{$nonce}:{$ha2}");

        $header = sprintf(
            'Digest username="%s", realm="%s", nonce="%s", uri="%s", response="%s"',
            $username, $realm, $nonce, $path, $response,
        );
        if ($qop) {
            $header .= sprintf(', qop=%s, nc=%s, cnonce="%s"', $qop, $nc, $cnonce);
        }
        if ($opaque) {
            $header .= sprintf(', opaque="%s"', $opaque);
        }

        return $header;
    }

    /** Same timeouts as request(), no auth — used to fetch a fresh
     *  digest challenge before building the header by hand. */
    private function plainRequest(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Camera integration is not configured — set the NVR host/credentials in Settings → Cameras first.');
        }

        return Http::timeout(15)->connectTimeout(6);
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Camera integration is not configured — set the NVR host/credentials in Settings → Cameras first.');
        }

        return Http::withDigestAuth($this->settings->username, (string) $this->settings->password)
            ->timeout(15)
            ->connectTimeout(6);
    }

    private function url(string $path): string
    {
        $scheme = $this->settings->use_https ? 'https' : 'http';

        return "{$scheme}://{$this->settings->host}:{$this->settings->port}{$path}";
    }

    private function assertOk(Response $response): void
    {
        if ($response->status() === 401) {
            throw new RuntimeException('NVR rejected the configured credentials (401) — check the username/password, and that the account has remote/ISAPI permission enabled on the device.');
        }
        if ($response->failed()) {
            throw new RuntimeException("NVR request failed (HTTP {$response->status()}).");
        }
    }

    private function uuid(): string
    {
        return (string) Str::uuid();
    }
}
