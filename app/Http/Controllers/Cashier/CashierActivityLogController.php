<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\Cashier\RecordCashierActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\RecordCashierActivityRequest;
use Illuminate\Http\JsonResponse;

/**
 * Best-effort activity-log sink for the /cashier screen (docs/features/
 * hardware.md §9.5). Mirrors PrintController::log() — cheap, synchronous,
 * fire-and-forget from the client; never blocks or errors out a cashier
 * action just because logging failed.
 */
class CashierActivityLogController extends Controller
{
    public function store(RecordCashierActivityRequest $request, RecordCashierActivity $record): JsonResponse
    {
        $log = ($record)($request->validated(), $request);

        return response()->json(['ok' => true, 'id' => $log->id]);
    }
}
