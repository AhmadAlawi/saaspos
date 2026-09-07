@props([
    'sale',
    'label' => null,
    'referenceType' => 'Sale',
])

{{--
    <x-print-button :sale="$sale" />

    Drives the print bridge (WebUSB → browser-print fallback) for a sale
    receipt and records the outcome. Replaces a plain "open the receipt
    page" link with a one-click print that uses the terminal's configured
    printer when available. See docs/features/hardware.md §15.1.
--}}
<button type="button"
        x-data="printButton({
            payloadUrl:     '{{ route('admin.sales.print-payload', $sale) }}',
            logUrl:         '{{ route('admin.print-logs.store') }}',
            referenceType:  @js($referenceType),
            referenceId:    {{ $sale->id }},
            referenceLabel: @js($sale->number),
        })"
        @click="print()"
        :disabled="printing"
        {{ $attributes->merge(['class' => 'pos-btn pos-btn-sm pos-btn-ghost']) }}>
    <svg x-show="printing" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
    </svg>
    <template x-if="!printing">
        <x-icon name="receipt" class="w-4 h-4" />
    </template>
    <span>{{ $label ?? __('sales.actions.print') }}</span>
</button>
