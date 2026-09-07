@props([
    'type' => 'info',   // info | success | warning | danger
])

{{--
    Inline page/card banner. Fills the `.alert` grid's three cells
    (icon · body · optional actions) — passing bare text straight into
    `.alert` drops it in the 20px icon column and wraps it one word per
    line, so always render through this component.

    Usage: <x-alert type="warning" class="mb-4">Message…</x-alert>
--}}
@php
    $icon = [
        'success' => 'check',
        'info'    => 'info',
        'warning' => 'alert',
        'danger'  => 'alert',
    ][$type] ?? 'info';
@endphp

<div {{ $attributes->merge(['class' => 'alert alert-'.$type]) }}>
    <span class="alert-icon"><x-icon :name="$icon" class="w-5 h-5" /></span>
    <div class="alert-body">
        <p class="alert-msg">{{ $slot }}</p>
    </div>
</div>
