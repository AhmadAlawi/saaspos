<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Sales\RefundApprovalToken;
use App\Services\Sales\ResolveManagerByPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Manager approval for a refund made by a cashier who doesn't hold
 * `sales.refund` — same shape as {@see DiscountApprovalController}: a
 * manager types their 6-digit PIN on the cashier's numpad (resolved via
 * {@see ResolveManagerByPin}, no username/email step), and on success we
 * hand back a short-lived signed token capped at the refund total shown
 * on screen. {@see \App\Http\Controllers\Cashier\RefundController} and
 * {@see \App\Actions\Sales\RecordBlindReturn} re-verify it server-side.
 *
 * Rate-limited per cashier/IP — a 6-digit PIN with no username is
 * low-entropy, so brute-forcing it must be expensive.
 */
class RefundApprovalController extends Controller
{
    public function approve(Request $request, ResolveManagerByPin $resolve, RefundApprovalToken $tokens): JsonResponse
    {
        // The requester must be a real cashier session, even if they
        // themselves can't refund without this approval.
        $this->authorize('create', Sale::class);

        $data = $request->validate([
            'pin'    => ['required', 'digits:6'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $throttleKey = 'refund-approval:'.$request->ip().':'.$request->user()?->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json(['ok' => false, 'message' => __('sales.refund_approval.too_many')], 429);
        }
        RateLimiter::hit($throttleKey, 60);

        $storeId = current_store_id() ?: default_store_id();

        $manager = $resolve($data['pin'], (int) $storeId);

        if (! $manager) {
            return response()->json(['ok' => false, 'message' => __('sales.refund_approval.invalid')], 422);
        }
        if (! $manager->hasPermission('sales.refund', (int) $storeId)) {
            return response()->json(['ok' => false, 'message' => __('sales.refund_approval.not_authorized')], 422);
        }

        RateLimiter::clear($throttleKey);

        $token = $tokens->issue((int) $manager->id, (int) $storeId, (string) $data['amount']);

        return response()->json([
            'ok'       => true,
            'token'    => $token,
            'approver' => $manager->name,
            'message'  => __('sales.refund_approval.approved', ['name' => $manager->name]),
        ]);
    }
}
