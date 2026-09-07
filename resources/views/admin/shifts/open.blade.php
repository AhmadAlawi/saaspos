<x-admin-layout
    active="shifts"
    :title="__('shifts.open.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('shifts.title'), 'href' => route('admin.shifts.index')],
        ['label' => __('shifts.open.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.shifts.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('shifts.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('shifts.open.title') }}</h1>
                    <p class="page-sub">{{ $store->name }}</p>
                </div>
            </div>
        </div>

        {{-- Single-input form — constrain the card so it doesn't stretch
             across the full page-wide width. Matches the visual weight
             of other narrow drawers (refund form, payment-method picker). --}}
        <div class="max-w-xl">
            <div class="card">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('shifts.open.title') }}</div>
                    <div class="card-title-sub">{{ __('shifts.open.sub') }}</div>
                </div></div>
                <form method="POST" action="{{ route('admin.shifts.store') }}" class="card-body" data-ajax-form>
                    @csrf
                    <div class="form-stack">
                        {{-- Terminal (store-scoped). Required when the store has
                             any active terminals — one terminal = one open shift.
                             Hidden entirely for stores with no terminals yet. --}}
                        @if ($terminals->isNotEmpty())
                            <label class="field">
                                <span class="field-label is-required">{{ __('shifts.fields.terminal') }}</span>
                                <select name="terminal_id" class="pos-input" x-data="enhancedSelect()" required>
                                    <option value="">{{ __('shifts.fields.terminal_placeholder') }}</option>
                                    @foreach ($terminals as $t)
                                        <option value="{{ $t->id }}" @selected(old('terminal_id', $selectedTerminal) == $t->id)>{{ $t->name }}</option>
                                    @endforeach
                                </select>
                                <p class="field-help">{{ __('shifts.fields.terminal_help') }}</p>
                            </label>
                        @endif

                        <label class="field">
                            <span class="field-label is-required">{{ __('shifts.fields.opening_cash') }}</span>
                            <input type="number"
                                   name="opening_cash"
                                   value="{{ old('opening_cash', 0) }}"
                                   class="pos-input"
                                   step="0.0001"
                                   min="0"
                                   required
                                   autofocus>
                            <p class="field-help">{{ __('shifts.fields.opening_cash_help') }}</p>
                        </label>

                        <x-shifts.denomination-helper
                            :denominations="$denominations"
                            field="opening_denominations"
                            target="opening_cash" />

                        <label class="field">
                            <span class="field-label">{{ __('shifts.fields.notes') }}</span>
                            <textarea name="notes" rows="3" class="pos-input">{{ old('notes') }}</textarea>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 mt-6">
                        <a href="{{ route('admin.shifts.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">{{ __('shifts.actions.cancel') }}</a>
                        <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                            <x-icon name="plus" class="w-4 h-4" />
                            {{ __('shifts.actions.start_shift') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
