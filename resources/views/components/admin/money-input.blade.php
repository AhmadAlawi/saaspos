@props(['symbol' => null])

{{--
    Money input — a `.pos-input` with a currency-symbol prefix.

    Reusable across pricing, per-store overrides, sales, purchases, etc.
    Pass-through attributes land on the <input>, so both server-rendered
    and Alpine-bound usages work. For Alpine binds use the full
    `x-bind:` / `x-model` syntax (NOT the `:` shorthand — Blade would try
    to evaluate `:name` as PHP on a component tag).

    Examples:
        <x-admin.money-input name="selling_price" value="{{ $val('selling_price') }}" />
        <x-admin.money-input x-model="v.cost_price" x-bind:name="`variants[${i}][cost_price]`" />
--}}
<div class="pos-money">
    <span class="pos-money-symbol" aria-hidden="true">{{ $symbol ?? currency_symbol() }}</span>
    <input type="number" step="0.0001" min="0"
           {{ $attributes->merge(['class' => 'pos-input tnum']) }}>
</div>
