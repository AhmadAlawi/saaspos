<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\ChangePinRequest;
use Illuminate\Http\JsonResponse;

/**
 * Self-service PIN change — every user has one PIN (used for discount/
 * refund approval identification and, on terminals with it enabled,
 * PIN login), and no admin gate should stand between them and rotating
 * it themselves. `ChangePinRequest` already re-checked the current PIN
 * before this ever runs.
 */
class ChangePinController extends Controller
{
    public function update(ChangePinRequest $request): JsonResponse
    {
        $request->user()->update(['pin' => $request->input('pin')]);

        return response()->json(['ok' => true, 'message' => __('account.pin.updated')]);
    }
}
