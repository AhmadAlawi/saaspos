@props([
    'denominations' => [],
    'field' => 'opening_denominations',
    'target' => null,
])

{{-- Denomination helper (Slice C). Collapsible grid of note/coin counts
     that sums live and can push its total into the linked cash input.
     Count inputs submit natively via their own `name`. --}}
<div class="denom-helper"
     x-data="denominationHelper({
        denominations: @js(array_values($denominations)),
        field: @js($field),
        targetName: @js($target),
     })">
    <button type="button" class="denom-toggle" @click="expanded = !expanded" :aria-expanded="expanded">
        <x-icon name="chevron" class="w-4 h-4 denom-chevron" />
        <span>{{ __('shifts.denom.toggle') }}</span>
    </button>

    <div class="denom-body" x-show="expanded" x-cloak>
        <div class="denom-grid">
            <template x-for="d in denoms" :key="d">
                <div class="denom-row">
                    <span class="denom-face tnum" x-text="$formatMoney(d)"></span>
                    <span class="denom-x">×</span>
                    <input type="number" min="0" step="1" inputmode="numeric"
                           class="pos-input denom-count num tnum"
                           :name="field + '[' + d + ']'"
                           x-model.number="counts[d]"
                           placeholder="0">
                    <span class="denom-sub tnum" x-text="$formatMoney(subtotal(d))"></span>
                </div>
            </template>
        </div>

        <div class="denom-total">
            <span class="denom-total-label">{{ __('shifts.denom.total') }}</span>
            <span class="denom-total-value tnum" x-text="$formatMoney(total)"></span>
            <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="applyTotal()">
                {{ __('shifts.denom.use_total') }}
            </button>
        </div>
    </div>
</div>
