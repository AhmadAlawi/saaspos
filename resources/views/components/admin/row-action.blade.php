@props([
    'href'    => null,        // renders an <a> when set, else a <button>
    'icon'    => null,        // x-icon name
    'label'   => '',          // visible item text
    'variant' => 'default',   // default | danger
    'target'  => null,        // e.g. _blank for receipts/print
])

{{--
    A single item inside <x-admin.row-actions>. Renders an <a> (when `href`
    is given) or a <button>. Any extra attributes — @click, x-show, target,
    etc. — pass straight through, so confirm-dialog actions and conditional
    visibility work exactly as they did inline.
--}}
@php
    $classes = 'row-action' . ($variant === 'danger' ? ' row-action--danger' : '');
@endphp

@if ($href)
    <a href="{{ $href }}"
       @if ($target) target="{{ $target }}" @endif
       role="menuitem"
       {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <span class="row-action-icon"><x-icon :name="$icon" class="w-4 h-4" /></span>
        @endif
        <span class="row-action-label">{{ $label }}</span>
    </a>
@else
    <button type="button"
            role="menuitem"
            {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <span class="row-action-icon"><x-icon :name="$icon" class="w-4 h-4" /></span>
        @endif
        <span class="row-action-label">{{ $label }}</span>
    </button>
@endif
