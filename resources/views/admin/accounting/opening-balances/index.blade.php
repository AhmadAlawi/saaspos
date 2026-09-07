<x-admin-layout
    active="opening-balances"
    :title="__('accounting.opening.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('accounting.nav.section')],
        ['label' => __('accounting.opening.title')],
    ]">

    @php
        $sides   = [];
        $amounts = [];
        foreach ($groups as $type => $accts) {
            foreach ($accts as $a) {
                $sides[$a->id]   = in_array($a->type, ['asset', 'expense'], true) ? 'debit' : 'credit';
                $amounts[$a->id] = $prefill[$a->id] ?? '';
            }
        }
    @endphp

    <div class="page-wide"
         x-data="openingBalances({ amounts: {{ Js::from($amounts) }}, sides: {{ Js::from($sides) }} })">

        @if (session('success'))
            <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-4">{{ session('error') }}</x-alert>
        @endif

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('accounting.opening.title') }}</h1>
                <p class="page-sub">{{ __('accounting.opening.sub') }}</p>
            </div>
        </div>

        @unless ($canEdit)
            <x-alert type="warning" class="mb-4">{{ __('accounting.opening.locked_notice') }}</x-alert>
        @endunless

        <form method="POST" action="{{ route('admin.accounting.opening-balances.store') }}" data-ajax-form class="card card-pad-0">
            @csrf
            <input type="hidden" name="store_id" value="{{ current_store_id() }}">

            <div class="ob-head">
                <label class="field ob-date">
                    <span class="field-label">{{ __('accounting.opening.date_label') }}</span>
                    <input type="text" name="entry_date" value="{{ $entryDate }}"
                           class="pos-input js-datepicker" @disabled(! $canEdit)>
                </label>
            </div>

            <div class="ob-list">
                @foreach ($groups as $type => $accts)
                    <div class="ob-section">{{ __('accounting.opening.sections.'.$type) }}</div>
                    @foreach ($accts as $a)
                        @php $isDebit = in_array($a->type, ['asset', 'expense'], true); @endphp
                        <div class="ob-row">
                            <span class="ob-code mono">{{ $a->code }}</span>
                            <span class="ob-name">{{ $a->name }}</span>
                            <span class="ob-sidetag">{{ $isDebit ? __('accounting.opening.dr') : __('accounting.opening.cr') }}</span>
                            <div class="ob-amount">
                                <input type="number" min="0" step="0.01" inputmode="decimal"
                                       name="balances[{{ $a->id }}]"
                                       x-model="amounts[{{ $a->id }}]"
                                       @input="recompute()"
                                       class="pos-input tnum"
                                       @disabled(! $canEdit)>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>

            <div class="ob-summary">
                <div class="ob-summary-row">
                    <span>{{ __('accounting.opening.debit_total') }}</span>
                    <span class="tnum" x-text="$formatMoney(debitTotal)"></span>
                </div>
                <div class="ob-summary-row">
                    <span>{{ __('accounting.opening.credit_total') }}</span>
                    <span class="tnum" x-text="$formatMoney(creditTotal)"></span>
                </div>
                <div class="ob-summary-row ob-plug">
                    <span>{{ __('accounting.opening.plug_label') }}</span>
                    <span class="tnum">
                        <span x-show="balanced" class="ob-balanced">{{ __('accounting.opening.balanced') }}</span>
                        <span x-show="!balanced">
                            <span x-text="$formatMoney(plugAbs)"></span>
                            <span class="fg-tertiary" x-show="plug > 0">{{ __('accounting.opening.plug_credit') }}</span>
                            <span class="fg-tertiary" x-show="plug < 0">{{ __('accounting.opening.plug_debit') }}</span>
                        </span>
                    </span>
                </div>
                <p class="ob-plug-hint">{{ __('accounting.opening.plug_hint') }}</p>
            </div>

            @if ($canEdit)
                <div class="ob-footer">
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('accounting.opening.post') }}
                    </button>
                </div>
            @endif
        </form>
    </div>
</x-admin-layout>
