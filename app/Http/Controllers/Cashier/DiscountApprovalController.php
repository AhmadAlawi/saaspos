<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Sales\DiscountApprovalToken;
use App\Services\Sales\ResolveManagerByPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * PIN identification for a discount (Checkout discounts, Slices 1-2,
 * extended for per-action identity logging). Every cashier-initiated
 * discount now needs a PIN typed on the cashier's numpad (no
 * username/email step — see {@see ResolveManagerByPin}) so the sale
 * records WHO applied it, not just which session was logged in.
 *
 * Under the store's `discount_threshold_percent`, ANY active user's PIN
 * is accepted — the point is identification, not authorization. Above
 * it, the resolved user must hold `sales.discount_above_threshold`
 * (a manager). Either way we hand back a short-lived signed token the
 * cashier includes in the ring-up; the server re-verifies it (and, for
 * the above-threshold case, re-checks the approver's live permission) in
 * {@see \App\Actions\Sales\CompleteSale}.
 *
 * Rate-limited per terminal/IP — a 6-digit PIN with no username is
 * low-entropy, so brute-forcing it must be expensive.
 */
class DiscountApprovalController extends Controller
{
    public function approve(Request $request, ResolveManagerByPin $resolve, DiscountApprovalToken $tokens): JsonResponse
    {
        // The requester must be a real cashier ringing up a sale.
        $this->authorize('create', Sale::class);

        $data = $request->validate([
            'pin'     => ['required', 'digits:6'],
            'percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $throttleKey = 'discount-approval:'.$request->ip().':'.$request->user()?->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json(['ok' => false, 'message' => __('sales.discount_approval.too_many')], 429);
        }
        RateLimiter::hit($throttleKey, 60);

        $storeId = (int) (current_store_id() ?: default_store_id());
        $percent = (float) ($data['percent'] ?? 100);
        $threshold = (float) (\App\Models\Store::find($storeId)?->discount_threshold_percent ?? 100);

        $identified = $resolve($data['pin'], $storeId);

        if (! $identified) {
            return response()->json(['ok' => false, 'message' => __('sales.discount_approval.invalid')], 422);
        }
        if ($percent > $threshold && ! $identified->hasPermission('sales.discount_above_threshold', $storeId)) {
            return response()->json(['ok' => false, 'message' => __('sales.discount_approval.not_authorized')], 422);
        }

        RateLimiter::clear($throttleKey);

        $token = $tokens->issue((int) $identified->id, $storeId, $percent);

        return response()->json([
            'ok'       => true,
            'token'    => $token,
            'approver' => $identified->name,
            'message'  => __('sales.discount_approval.approved', ['name' => $identified->name]),
        ]);
    }
}
