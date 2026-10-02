<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\ResolveSaleVideoWindow;
use App\Http\Controllers\Controller;
use App\Jobs\PrepareCameraClip;
use App\Models\CameraClipRequest;
use App\Models\CashierActivityLog;
use App\Models\Sale;
use App\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Beta: async "go fetch this footage" — for a completed sale (the
 * transaction's real cart-building window, see
 * {@see ResolveSaleVideoWindow}) or a single cashier-activity event
 * (drawer kick, an item added-then-removed, ±5s either side). Super-
 * admin only, same reasoning as the rest of the camera feature: reads
 * real device credentials, not gated by the normal permission system.
 *
 * One request per subject (DB unique constraint on subject_type +
 * subject_id) — revisiting the same invoice or activity-log row finds
 * the existing request via lookup() instead of re-queuing a duplicate.
 */
class CameraClipController extends Controller
{
    private const EVENT_WINDOW_SECONDS = 5;

    /** Does a request already exist for this subject? Read-only — a
     *  page calls this on load to show "already prepared/preparing"
     *  without triggering a new fetch just from being viewed. */
    public function lookup(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $data = $request->validate([
            'subject_type' => ['required', 'in:sale,activity'],
            'subject_id'   => ['required', 'integer'],
        ]);

        $clip = CameraClipRequest::query()
            ->where('subject_type', $data['subject_type'])
            ->where('subject_id', $data['subject_id'])
            ->first();

        return response()->json(['clip' => $clip ? $this->present($clip) : null]);
    }

    /** Creates (or returns the existing) clip request and queues it. */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $data = $request->validate([
            'subject_type' => ['required', 'in:sale,activity'],
            'subject_id'   => ['required', 'integer'],
        ]);

        $existing = CameraClipRequest::query()
            ->where('subject_type', $data['subject_type'])
            ->where('subject_id', $data['subject_id'])
            ->first();
        if ($existing && $existing->status !== CameraClipRequest::STATUS_FAILED) {
            return response()->json(['clip' => $this->present($existing)]);
        }
        if ($existing) {
            // A previous attempt failed (NVR down, no recording found,
            // etc.) — "request again" should actually retry, not just
            // hand back the same failure forever.
            $existing->update(['status' => CameraClipRequest::STATUS_PENDING, 'error_message' => null]);
            PrepareCameraClip::dispatch($existing->id);

            return response()->json(['clip' => $this->present($existing)]);
        }

        $attrs = $data['subject_type'] === 'sale'
            ? $this->attrsForSale((int) $data['subject_id'])
            : $this->attrsForActivity((int) $data['subject_id']);
        if (! $attrs) {
            return response()->json(['message' => 'No camera assigned for this record\'s terminal yet — assign one under Settings → Cameras.'], 422);
        }

        $clip = CameraClipRequest::create([
            ...$attrs,
            'subject_type' => $data['subject_type'],
            'subject_id'   => $data['subject_id'],
            'status'       => CameraClipRequest::STATUS_PENDING,
            'requested_by' => $request->user()->id,
        ]);

        PrepareCameraClip::dispatch($clip->id);

        return response()->json(['clip' => $this->present($clip)]);
    }

    /** Poll target while a clip is pending/processing. */
    public function show(Request $request, CameraClipRequest $clip): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);

        return response()->json(['clip' => $this->present($clip)]);
    }

    /** @return array{store_id:int, channel:int, window_start:string, window_end:string, label:string}|null */
    private function attrsForSale(int $saleId): ?array
    {
        $sale = Sale::find($saleId);
        $terminal = $sale?->terminal_id ? Terminal::find($sale->terminal_id) : null;
        if (! $sale || ! $terminal?->camera_channel) {
            return null;
        }

        [$start, $end] = app(ResolveSaleVideoWindow::class)->handle($sale);

        return [
            'store_id'     => $sale->store_id,
            'channel'      => (int) $terminal->camera_channel,
            'window_start' => $start,
            'window_end'   => $end,
            'label'        => "Invoice {$sale->number}",
        ];
    }

    /** @return array{store_id:int, channel:int, window_start:string, window_end:string, label:string}|null */
    private function attrsForActivity(int $activityId): ?array
    {
        $log = CashierActivityLog::find($activityId);
        $terminal = $log?->terminal_id ? Terminal::find($log->terminal_id) : null;
        if (! $log || ! $terminal?->camera_channel) {
            return null;
        }

        $eventTime = $log->created_at;

        return [
            'store_id'     => $log->store_id,
            'channel'      => (int) $terminal->camera_channel,
            'window_start' => $eventTime->clone()->subSeconds(self::EVENT_WINDOW_SECONDS),
            'window_end'   => $eventTime->clone()->addSeconds(self::EVENT_WINDOW_SECONDS),
            'label'        => ucfirst(str_replace('.', ' ', $log->action)),
        ];
    }

    private function present(CameraClipRequest $clip): array
    {
        return [
            'id'     => $clip->id,
            'status' => $clip->status,
            'label'  => $clip->label,
            'error'  => $clip->error_message,
            'url'    => $clip->status === CameraClipRequest::STATUS_READY && $clip->file_path
                ? Storage::disk('public')->url($clip->file_path)
                : null,
        ];
    }
}
