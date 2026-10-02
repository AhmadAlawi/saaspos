<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CameraSettings;
use App\Models\Store;
use App\Models\Terminal;
use App\Services\Cameras\HikvisionClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin → Settings → Cameras (beta). Every branch has its own Hikvision
 * NVR on its own network — one row per store, not a single shared
 * device — and a store's NVR can cover several tills, so the channel
 * assignment lives per TERMINAL (`terminals.camera_channel`), not per
 * store. See {@see HikvisionClient}. Super-admin only, same reasoning
 * as {@see AuditLogController}: this holds real device credentials,
 * not a per-permission-gated business setting.
 */
class CameraSettingsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $stores = Store::query()->orderBy('name')->get(['id', 'name', 'code']);
        $configured = CameraSettings::query()->whereNotNull('store_id')->get()->keyBy('store_id');

        return view('admin.settings.cameras-index', [
            'stores'     => $stores,
            'configured' => $configured,
        ]);
    }

    public function edit(Request $request, Store $store): View
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $settings = CameraSettings::forStore($store->id);
        $terminals = Terminal::query()->where('store_id', $store->id)->orderBy('name')->get();

        // Best-effort — only meaningful once the connection above is
        // saved and reachable. A failure here just means the channel
        // grid shows "list unavailable" instead of blocking the page.
        $channels = null;
        $channelsError = null;
        if ($settings->exists) {
            try {
                $channels = (new HikvisionClient($settings))->listChannels();
            } catch (RuntimeException $e) {
                $channelsError = $e->getMessage();
            }
        }

        return view('admin.settings.cameras-edit', [
            'store'             => $store,
            'settings'          => $settings,
            'terminals'         => $terminals,
            'channels'          => $channels,
            'channelsError'     => $channelsError,
            // First terminal (if any) currently pointed at each channel
            // number — lets the grid show "this channel = that till"
            // without a lookup per card in the view.
            'terminalByChannel' => $terminals->filter(fn ($t) => $t->camera_channel)->keyBy('camera_channel'),
        ]);
    }

    public function update(Request $request, Store $store): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $data = $request->validate([
            'host'      => ['required', 'string', 'max:255'],
            'port'      => ['required', 'integer', 'min:1', 'max:65535'],
            'use_https' => ['sometimes', 'boolean'],
            'username'  => ['required', 'string', 'max:100'],
            // Blank means "keep the existing password" — never force a
            // re-type just to flip `is_active` or fix a typo'd host.
            'password'  => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $settings = CameraSettings::query()->where('store_id', $store->id)->first()
            ?? new CameraSettings(['store_id' => $store->id]);
        $settings->fill([
            'host'      => $data['host'],
            'port'      => $data['port'],
            'use_https' => $request->boolean('use_https'),
            'username'  => $data['username'],
            'is_active' => $request->boolean('is_active'),
        ]);
        if (filled($data['password'] ?? null)) {
            $settings->password = $data['password'];
        }
        $settings->save();

        return response()->json(['message' => "Camera settings saved for {$store->name}."]);
    }

    public function test(Request $request, Store $store): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);

        try {
            $info = (new HikvisionClient(CameraSettings::forStore($store->id)))->deviceInfo();
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => "Connected — {$info['model']} ({$info['name']})."]);
    }

    /**
     * One JPEG frame from `channel` (query param, not tied to any saved
     * assignment) — the live-view grid re-requests this per channel on
     * a timer so every camera on the NVR previews at once.
     */
    public function snapshot(Request $request, Store $store): JsonResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $channel = $request->integer('channel');
        if ($channel < 1) {
            return response()->json(['message' => 'Missing or invalid channel.'], 422);
        }

        try {
            $frame = (new HikvisionClient(CameraSettings::forStore($store->id)))->snapshot($channel);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response($frame['body'], 200, [
            'Content-Type'  => $frame['content_type'],
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Assign (or clear, with `channel: null`) which NVR channel covers
     * one terminal — the point of the live-view grid: pick the camera
     * you can visually confirm is pointed at that till.
     */
    public function assignChannel(Request $request, Store $store, Terminal $terminal): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);
        abort_unless($terminal->store_id === $store->id, 404);

        $data = $request->validate([
            'channel' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $terminal->camera_channel = $data['channel'] ?? null;
        $terminal->save();

        return response()->json([
            'message' => $terminal->camera_channel
                ? "Channel {$terminal->camera_channel} assigned to {$terminal->name}."
                : "Camera unassigned from {$terminal->name}.",
        ]);
    }
}
