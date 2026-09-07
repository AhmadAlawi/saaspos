<x-admin-layout
    active="shifts"
    :title="__('shifts.close.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('shifts.title'), 'href' => route('admin.shifts.index')],
        ['label' => '#'.$shift->id, 'href' => route('admin.shifts.show', $shift)],
        ['label' => __('shifts.close.title')],
    ]">

    <div class="page-wide"
         x-data="{
             counted: 0,
             expected: @js((float) $totals['expected_cash']),
             reason: '',
             get variance() { return Number((this.counted - this.expected).toFixed(4)); },
         }">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.shifts.show', $shift) }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('shifts.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('shifts.close.title') }}</h1>
                    <p class="page-sub">
                        {{ __('shifts.fields.shift_id_value', ['n' => '#'.$shift->id]) }} ·
                        {{ $shift->cashier?->name }} ·
                        {{ __('shifts.fields.opened') }}: {{ format_datetime($shift->opened_at) }}
                    </p>
                </div>
            </div>
        </div>

        @if ((int) $shift->user_id !== (int) auth()->id())
            <div class="mb-5 flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                <x-icon name="alert" class="w-4 h-4 shrink-0" />
                <span>{{ __('shifts.close.force_banner', ['name' => $shift->cashier?->name ?? '—']) }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_380px] gap-5 items-start">
            {{-- LEFT: live x-report summary --}}
            <div class="space-y-5">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('shifts.sections.x_report') }}</div>
                        <div class="card-sub">{{ __('shifts.sections.x_report_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <x-shifts.totals-table :totals="$totals" />
                    </div>
                </div>
            </div>

            {{-- RIGHT: close form --}}
            <div class="card">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('shifts.close.title') }}</div>
                </div></div>
                <form method="POST" action="{{ route('admin.shifts.close', $shift) }}" class="card-body" data-ajax-form>
                    @csrf
                    <div class="form-stack">
                        <label class="field">
                            <span class="field-label is-required">{{ __('shifts.fields.closing_cash_counted') }}</span>
                            <input type="number"
                                   name="closing_cash_counted"
                                   x-model.number="counted"
                                   class="pos-input"
                                   step="0.0001"
                                   min="0"
                                   required
                                   autofocus>
                        </label>

                        <x-shifts.denomination-helper
                            :denominations="$denominations"
                            field="closing_denominations"
                            target="closing_cash_counted" />

                        <div class="bg-surface-subtle rounded-lg p-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-muted">{{ __('shifts.fields.expected_cash') }}</span>
                                <span class="mono tnum" x-text="$formatMoney(expected)"></span>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-muted">{{ __('shifts.fields.variance') }}</span>
                                <span class="mono tnum font-semibold"
                                      :class="variance === 0 ? '' : (variance > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400')"
                                      x-text="$formatMoney(variance)"></span>
                            </div>
                        </div>

                        <label class="field">
                            <span class="field-label">{{ __('shifts.fields.variance_reason') }}</span>
                            <select name="variance_reason" class="pos-input"
                                    x-data="enhancedSelect()"
                                    x-effect="ts && ts.setValue(reason ?? '', true)"
                                    x-model="reason">
                                <option value="">{{ __('shifts.variance_reasons.none') }}</option>
                                @foreach ($varianceReasons as $vr)
                                    <option value="{{ $vr->code }}">{{ $vr->name }}</option>
                                @endforeach
                            </select>
                            <p class="field-help">
                                <a href="{{ route('admin.shift-variance-reasons.index') }}"
                                   class="link" target="_blank">{{ __('shifts.fields.variance_reason_manage') }}</a>
                            </p>
                        </label>

                        <label class="field">
                            <span class="field-label">{{ __('shifts.fields.variance_notes') }}</span>
                            <textarea name="variance_notes" rows="2" class="pos-input"></textarea>
                        </label>

                        <label class="field">
                            <span class="field-label">{{ __('shifts.fields.notes') }}</span>
                            <textarea name="notes" rows="2" class="pos-input"></textarea>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 mt-6">
                        <a href="{{ route('admin.shifts.show', $shift) }}" class="pos-btn pos-btn-sm pos-btn-ghost">{{ __('shifts.actions.cancel') }}</a>
                        <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                            <x-icon name="check" class="w-4 h-4" />
                            {{ __('shifts.actions.close_shift') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
