<x-admin-layout
    active="system-health"
    :title="__('system_health.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('system_health.crumb_parent')],
        ['label' => __('system_health.title')],
    ]">

    @php
        // status → presentation. `dot` colours the leading indicator; `badge`
        // styles the trailing pill (reusing the system-wide prod-badge styles).
        $statusMeta = [
            'ok'   => ['badge' => 'prod-badge-positive', 'label' => __('system_health.status.ok')],
            'warn' => ['badge' => 'prod-badge-warning',  'label' => __('system_health.status.warn')],
            'fail' => ['badge' => 'prod-badge-danger',   'label' => __('system_health.status.fail')],
        ];
        $overall = $statusMeta[$summary['status']] ?? $statusMeta['ok'];
        $overallTitle = __('system_health.overall.'.$summary['status']);
        $overallSub = match ($summary['status']) {
            'fail' => __('system_health.overall.fail_sub', [
                'fail' => $summary['fail'],
                'warn_extra' => $summary['warn'] > 0 ? __('system_health.overall.warn_extra', ['warn' => $summary['warn']]) : '',
            ]),
            'warn' => __('system_health.overall.warn_sub', ['warn' => $summary['warn']]),
            default => __('system_health.overall.ok_sub', ['count' => $summary['ok']]),
        };
    @endphp

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('system_health.title') }}</h1>
                <p class="page-sub">{{ __('system_health.sub') }}</p>
            </div>
            <div class="flex items-center gap-3" x-data="{ mode: 'collapsed' }">
                {{-- Segmented toggle: highlights the half matching the last
                     bulk action (starts on Collapse, since cards default closed). --}}
                <div class="sysh-seg" role="group" aria-label="{{ __('system_health.expand_all') }} / {{ __('system_health.collapse_all') }}">
                    <button type="button"
                            class="sysh-seg-btn"
                            :class="mode === 'expanded' ? 'is-active' : ''"
                            @click="mode = 'expanded'; $dispatch('sysh-expand-all')">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('system_health.expand_all') }}
                    </button>
                    <button type="button"
                            class="sysh-seg-btn"
                            :class="mode === 'collapsed' ? 'is-active' : ''"
                            @click="mode = 'collapsed'; $dispatch('sysh-collapse-all')">
                        <x-icon name="minus" class="w-4 h-4" />
                        {{ __('system_health.collapse_all') }}
                    </button>
                </div>
                <a href="{{ route('admin.settings.system-health') }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="refresh" class="w-4 h-4" />
                    {{ __('system_health.refresh') }}
                </a>
            </div>
        </div>

        {{-- Overall verdict --}}
        <div class="card mb-5">
            <div class="card-body flex items-center gap-3">
                <span class="sysh-overall-dot is-{{ $summary['status'] }}"></span>
                <div class="min-w-0">
                    <div class="sysh-overall-title">{{ $overallTitle }}</div>
                    <div class="sysh-overall-sub">{{ $overallSub }}</div>
                </div>
                <span class="prod-badge {{ $overall['badge'] }} ms-auto shrink-0">{{ $overall['label'] }}</span>
            </div>
        </div>

        {{-- One card per group — collapsed by default; the header toggles it,
             and the Expand/Collapse-all buttons broadcast window events. --}}
        @foreach ($groups as $group)
            @continue(empty($group['rows']))
            @php
                // Worst status among the group's rows, surfaced in the header
                // so the state is legible while the card is collapsed.
                $groupStatus = collect($group['rows'])->pluck('status');
                $worst = $groupStatus->contains('fail') ? 'fail' : ($groupStatus->contains('warn') ? 'warn' : 'ok');
                $worstMeta = $statusMeta[$worst];
            @endphp
            <div class="card card-pad-0 mb-5"
                 x-data="{ open: false }"
                 @sysh-expand-all.window="open = true"
                 @sysh-collapse-all.window="open = false">
                <button type="button"
                        class="sysh-group-header"
                        @click="open = ! open"
                        :aria-expanded="open ? 'true' : 'false'">
                    <x-icon name="chevron" class="sysh-group-chevron" ::class="open ? 'is-open' : ''" />
                    <span class="card-title">{{ $group['title'] }}</span>
                    <span class="sysh-group-count">{{ count($group['rows']) }}</span>
                    <span class="prod-badge {{ $worstMeta['badge'] }} ms-auto shrink-0">{{ $worstMeta['label'] }}</span>
                </button>
                <ul class="sysh-list" x-show="open" x-cloak>
                    @foreach ($group['rows'] as $row)
                        @php $meta = $statusMeta[$row['status']] ?? $statusMeta['ok']; @endphp
                        <li class="sysh-row">
                            <span class="sysh-dot is-{{ $row['status'] }}" aria-hidden="true"></span>
                            <div class="sysh-row-main">
                                <div class="sysh-row-label">{{ $row['label'] }}</div>
                                @if (! empty($row['hint']))
                                    <div class="sysh-row-hint">{{ $row['hint'] }}</div>
                                @endif
                            </div>
                            <span class="sysh-row-value mono">{{ $row['value'] }}</span>
                            <span class="prod-badge {{ $meta['badge'] }} shrink-0">{{ $meta['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        {{-- Danger zone — clear all sample/demo data. Super-admin only, hidden
             on the public demo (which has its own reset). --}}
        @if (auth()->user()?->is_super_admin && ! pos_is_demo())
            <div class="card sysh-danger mb-5">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('system_health.clear_sample.title') }}</div>
                        <div class="card-title-sub">{{ __('system_health.clear_sample.sub') }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <x-alert type="danger" class="mb-4">{{ __('system_health.clear_sample.warning') }}</x-alert>
                    <button type="button"
                            x-data
                            @click="$store.confirm.show({
                                title: {{ Js::from(__('system_health.clear_sample.confirm_title')) }},
                                message: {{ Js::from(__('system_health.clear_sample.confirm_message')) }},
                                intent: 'danger',
                                confirmLabel: {{ Js::from(__('system_health.clear_sample.confirm_button')) }},
                                cancelLabel: {{ Js::from(__('system_health.clear_sample.cancel')) }},
                                onConfirm: () => $submitForm({{ Js::from(route('admin.settings.system-health.clear-sample-data')) }}),
                            })"
                            class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger">
                        <x-icon name="trash" class="w-4 h-4" />
                        {{ __('system_health.clear_sample.button') }}
                    </button>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
