@props([
    'busyLabel' => null,
])

@php
    $busy = $busyLabel ?? __('installer.processing');
@endphp

<button type="submit"
        :disabled="submitting"
        {{ $attributes->class([
            'pos-btn pos-btn-primary',
            'disabled:cursor-not-allowed disabled:opacity-75',
        ]) }}>
    {{-- Spinner (shown while submitting) --}}
    <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
    </svg>
    <span x-text="submitting ? @js($busy) : null" x-show="submitting" x-cloak></span>
    <span x-show="!submitting">{{ $slot }}</span>
</button>
