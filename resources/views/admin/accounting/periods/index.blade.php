<x-admin-layout
    active="fiscal-periods"
    :title="__('accounting.periods.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('accounting.nav.section')],
        ['label' => __('accounting.periods.title')],
    ]">

    <div class="page-wide">
        @if (session('status'))
            <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
        @endif
        @error('period')
            <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
        @enderror
        @error('close')
            <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
        @enderror

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('accounting.periods.title') }}</h1>
                <p class="page-sub">{{ __('accounting.periods.sub') }}</p>
            </div>
        </div>

        @forelse ($years as $year)
            @php
                $allLocked  = $year->periods->isNotEmpty() && $year->periods->every(fn ($p) => $p->is_locked);
                $canClose   = ! $year->is_locked && auth()->user()?->hasPermission('accounting.year_end_close');
            @endphp

            <div class="card card-pad-0 mb-6">
                <div class="fy-head">
                    <div class="fy-head-main">
                        <h2 class="fy-title">
                            {{ $year->name }}
                            @if ($year->is_locked)
                                <span class="prod-badge prod-badge-muted">{{ __('accounting.periods.closed') }}</span>
                            @else
                                <span class="prod-badge prod-badge-positive">{{ __('accounting.periods.open') }}</span>
                            @endif
                        </h2>
                        <p class="fy-range">
                            {{ $year->start_date?->format('d M Y') }} – {{ $year->end_date?->format('d M Y') }}
                            @if ($year->is_locked && $year->locked_at)
                                · {{ __('accounting.periods.year_closed_badge', ['at' => $year->locked_at->format('d M Y')]) }}
                            @endif
                        </p>
                    </div>

                    @if ($canClose)
                        <div class="fy-head-actions">
                            <span class="fy-hint {{ $allLocked ? 'fy-hint--ok' : '' }}">
                                {{ $allLocked ? __('accounting.periods.all_locked_hint') : __('accounting.periods.lock_all_hint') }}
                            </span>
                            <button type="button"
                                    x-data
                                    @disabled(! $allLocked)
                                    @click="$store.confirm.show({
                                        title: {{ Js::from(__('accounting.periods.close_year')) }},
                                        message: {{ Js::from(__('accounting.periods.close_year_confirm', ['year' => $year->name])) }},
                                        intent: 'danger',
                                        confirmLabel: {{ Js::from(__('accounting.periods.close_year')) }},
                                        cancelLabel: {{ Js::from(__('accounting.cancel')) }},
                                        onConfirm: () => $submitForm({{ Js::from(route('admin.accounting.years.close', $year)) }}),
                                    })"
                                    class="pos-btn pos-btn-sm pos-btn-primary">
                                <x-icon name="lock" class="w-4 h-4" />
                                {{ __('accounting.periods.close_year') }}
                            </button>
                        </div>
                    @endif
                </div>

                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('accounting.periods.period') }}</th>
                            <th>{{ __('accounting.periods.range') }}</th>
                            <th>{{ __('accounting.periods.status') }}</th>
                            <th class="dt-actions-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($year->periods as $period)
                            <tr>
                                <td>
                                    {{ $period->name }}
                                    @if ($period->id === $currentPeriodId)
                                        <span class="prod-badge prod-badge-warning ms-1">{{ __('accounting.periods.current') }}</span>
                                    @endif
                                </td>
                                <td class="fg-tertiary tnum">{{ $period->start_date?->format('d M') }} – {{ $period->end_date?->format('d M Y') }}</td>
                                <td>
                                    @if ($period->is_locked)
                                        <span class="prod-badge prod-badge-warning">{{ __('accounting.periods.locked') }}</span>
                                    @else
                                        <span class="prod-badge prod-badge-muted">{{ __('accounting.periods.open') }}</span>
                                    @endif
                                </td>
                                <td class="num">
                                    @if (! $period->is_locked && auth()->user()?->hasPermission('accounting.lock_period'))
                                        <button type="button"
                                                x-data
                                                @click="$store.confirm.show({
                                                    title: {{ Js::from(__('accounting.periods.lock')) }},
                                                    message: {{ Js::from(__('accounting.periods.lock_confirm', ['period' => $period->name])) }},
                                                    intent: 'warning',
                                                    confirmLabel: {{ Js::from(__('accounting.periods.lock')) }},
                                                    cancelLabel: {{ Js::from(__('accounting.cancel')) }},
                                                    onConfirm: () => $submitForm({{ Js::from(route('admin.accounting.periods.lock', $period)) }}),
                                                })"
                                                class="pos-btn pos-btn-xs pos-btn-ghost">
                                            <x-icon name="lock" class="w-4 h-4" />
                                            {{ __('accounting.periods.lock') }}
                                        </button>
                                    @elseif ($period->is_locked && ! $year->is_locked && auth()->user()?->hasPermission('accounting.unlock_period'))
                                        <button type="button"
                                                x-data
                                                @click="$store.confirm.show({
                                                    title: {{ Js::from(__('accounting.periods.unlock')) }},
                                                    message: {{ Js::from(__('accounting.periods.unlock_confirm', ['period' => $period->name])) }},
                                                    intent: 'warning',
                                                    confirmLabel: {{ Js::from(__('accounting.periods.unlock')) }},
                                                    cancelLabel: {{ Js::from(__('accounting.cancel')) }},
                                                    onConfirm: () => $submitForm({{ Js::from(route('admin.accounting.periods.unlock', $period)) }}),
                                                })"
                                                class="pos-btn pos-btn-xs pos-btn-ghost">
                                            <x-icon name="lock" class="w-4 h-4" />
                                            {{ __('accounting.periods.unlock') }}
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @empty
            <div class="card">
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="lock" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('accounting.periods.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('accounting.periods.empty.sub') }}</div>
                </div>
            </div>
        @endforelse
    </div>
</x-admin-layout>
