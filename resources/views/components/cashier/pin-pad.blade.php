@props(['formPath', 'onComplete' => null])

{{--
    Reusable 6-digit PIN numpad for the cashier's touchscreen manager-
    approval modals (discount, refund) — replaces the old email+password
    fields, since those are painful to type on a till's touchscreen.

    `formPath` is the raw Alpine expression to write the digits into (e.g.
    "approvalForm.pin") — interpolated server-side since Blade props are
    plain strings, not bound values. `onComplete` is an optional Alpine
    statement run once the 6th digit is entered (e.g. "submitApproval()").
--}}
<div class="pin-pad"
     x-data="{
         digits: '',
         press(v) {
             if (this.digits.length >= 6) return;
             this.digits += v;
             {{ $formPath }} = this.digits;
             if (this.digits.length === 6) { {{ $onComplete ?: '' }} }
         },
         back() { this.digits = this.digits.slice(0, -1); {{ $formPath }} = this.digits; },
         clear() { this.digits = ''; {{ $formPath }} = this.digits; },
     }"
     {{-- The form value can be cleared from OUTSIDE this component (e.g.
          the caller resets the form on a failed/cancelled attempt) — that
          only flows one way (button presses write out to it), so watch
          for an external clear and mirror it back into `digits`, or the
          dots would stay stuck full after a wrong-PIN retry. --}}
     x-effect="if ({{ $formPath }} === '' && digits !== '') digits = ''">
    <div class="pin-pad-dots" role="status" :aria-label="digits.length + ' / 6'">
        <template x-for="i in 6" :key="i">
            <span class="pin-pad-dot" :class="{ 'is-filled': i <= digits.length }"></span>
        </template>
    </div>
    <div class="pin-pad-grid">
        <template x-for="n in [1,2,3,4,5,6,7,8,9]" :key="n">
            <button type="button" class="pin-pad-key" @click="press(String(n))" x-text="n"></button>
        </template>
        <button type="button" class="pin-pad-key pin-pad-key-muted" @click="clear()">{{ __('cashier.pin_pad.clear') }}</button>
        <button type="button" class="pin-pad-key" @click="press('0')">0</button>
        <button type="button" class="pin-pad-key pin-pad-key-muted" @click="back()" aria-label="{{ __('cashier.pin_pad.backspace') }}">⌫</button>
    </div>
</div>
