<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Focus mode's "Open drawer" rail button — open to every active team
 * member (no `cash_drawer.open_no_sale` permission check, unlike the
 * admin shift page / overflow-menu version of this action), gated
 * instead on typing a valid 6-digit PIN. The PIN identifies WHO
 * performed it for the audit trail ({@see \App\Models\CashDrawerEntry::createdBy()})
 * — see {@see \App\Http\Controllers\Cashier\ShiftController::openDrawerWithPin()}.
 *
 * Rate-limited by IP, same shape as {@see \App\Http\Requests\Auth\PinLoginRequest}
 * — the key must never be the secret itself.
 */
class PinDrawerOpenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'digits:6'],
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'pin' => __('auth.throttle', ['seconds' => $seconds]),
        ]);
    }

    public function hitRateLimit(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    public function clearRateLimit(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return 'drawer-pin|'.$this->ip();
    }
}
