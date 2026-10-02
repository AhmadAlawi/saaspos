<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CameraSettings;
use App\Models\Terminal;
use App\Services\Cameras\HikvisionClient;
use App\Services\Cameras\HlsStreamManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin → Cameras — live view for the currently selected store: every
 * terminal that has a camera assigned (Settings → Cameras) up top, plus
 * every raw channel the NVR reports below that (so the page is useful
 * before any terminal assignment exists yet, or if some channels were
 * never assigned to a terminal at all). Gated by the normal
 * `cameras.view` permission (assignable to any role), unlike the NVR
 * connection/channel-assignment settings, which stay super-admin-only
 * since those hold real device credentials.
 */
class CameraLiveController extends Controller
{
    public function index(Request $request): View
    {
        // enforce_store_access() short-circuits to whatever was passed
        // for super admins (no fallback) — resolve the default ourselves
        // first, same as TerminalController@index.
        $storeId = enforce_store_access($request->integer('store_id') ?: current_store_id() ?: default_store_id());
        abort_unless($request->user()?->hasPermission('cameras.view', $storeId), 403);

        $terminals = Terminal::query()
            ->where('store_id', $storeId)
            ->whereNotNull('camera_channel')
            ->orderBy('name')
            ->get();

        $settings = CameraSettings::forStore($storeId);
        $channels = null;
        $channelsError = null;
        if ($settings->exists) {
            try {
                $channels = (new HikvisionClient($settings))->listChannels();
            } catch (RuntimeException $e) {
                $channelsError = $e->getMessage();
            }
        }

        return view('admin.cameras.index', [
            'storeId'       => $storeId,
            'terminals'     => $terminals,
            'channels'      => $channels,
            'channelsError' => $channelsError,
            'nvrConfigured' => $settings->exists,
        ]);
    }

    public function snapshot(Request $request, Terminal $terminal): JsonResponse|\Illuminate\Http\Response
    {
        $storeId = enforce_store_access($terminal->store_id);
        abort_unless($request->user()?->hasPermission('cameras.view', $storeId), 403);

        if (! $terminal->camera_channel) {
            return response()->json(['message' => 'No camera assigned to this terminal.'], 422);
        }

        try {
            $frame = (new HikvisionClient(CameraSettings::forStore($terminal->store_id)))
                ->snapshot((int) $terminal->camera_channel);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response($frame['body'], 200, [
            'Content-Type'  => $frame['content_type'],
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Ensures a live RTSP→HLS stream is running for this terminal's
     * camera and returns its playlist URL — see
     * {@see HlsStreamManager}. The frontend calls this once to get the
     * URL, hands it to hls.js, then re-calls it every ~10s while the
     * player is open purely to keep the stream's idle clock from
     * expiring (CleanupCameraStreams kills anything nobody's
     * "ensuring" anymore).
     */
    public function stream(Request $request, Terminal $terminal): JsonResponse
    {
        $storeId = enforce_store_access($terminal->store_id);
        abort_unless($request->user()?->hasPermission('cameras.view', $storeId), 403);

        if (! $terminal->camera_channel) {
            return response()->json(['message' => 'No camera assigned to this terminal.'], 422);
        }

        try {
            $url = (new HlsStreamManager(CameraSettings::forStore($terminal->store_id)))
                ->ensure((int) $terminal->camera_channel);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['url' => $url]);
    }

    /** Same as snapshot(), but by raw channel number for the "all channels" grid. */
    public function channelSnapshot(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $storeId = enforce_store_access($request->integer('store_id') ?: current_store_id() ?: default_store_id());
        abort_unless($request->user()?->hasPermission('cameras.view', $storeId), 403);

        $channel = $request->integer('channel');
        if ($channel < 1) {
            return response()->json(['message' => 'Missing or invalid channel.'], 422);
        }

        try {
            $frame = (new HikvisionClient(CameraSettings::forStore($storeId)))->snapshot($channel);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response($frame['body'], 200, [
            'Content-Type'  => $frame['content_type'],
            'Cache-Control' => 'no-store',
        ]);
    }
}
