<x-admin-layout
    active="settings"
    :title="__('updates.history.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('updates.title'), 'href' => route('admin.settings.updates.index')],
        ['label' => __('updates.history.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.settings.updates.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('updates.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('updates.history.title') }}</h1>
                    <p class="page-sub">{{ __('updates.history.sub') }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            @if ($logs->isEmpty())
                <div class="card-body">
                    <p class="fg-tertiary">{{ __('updates.history.empty') }}</p>
                </div>
            @else
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('updates.history.col_from') }}</th>
                            <th>{{ __('updates.history.col_to') }}</th>
                            <th>{{ __('updates.history.col_status') }}</th>
                            <th>{{ __('updates.history.col_started') }}</th>
                            <th>{{ __('updates.history.col_finished') }}</th>
                            <th>{{ __('updates.history.col_channel') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php
                                $statusClass = match ($log->status) {
                                    'success'     => 'text-[var(--positive)]',
                                    'failed'      => 'text-[var(--danger)]',
                                    'rolled_back' => 'text-[var(--warning)]',
                                    default       => 'fg-secondary',
                                };
                            @endphp
                            <tr>
                                <td class="mono">{{ $log->from_version }}</td>
                                <td class="mono">{{ $log->to_version }}</td>
                                <td><span class="{{ $statusClass }} font-medium">{{ __('updates.history.status.'.$log->status) }}</span></td>
                                <td>{{ format_datetime($log->started_at) }}</td>
                                <td>{{ $log->finished_at ? format_datetime($log->finished_at) : '—' }}</td>
                                <td>{{ $log->channel ?: '—' }}</td>
                            </tr>
                            @if ($log->error_message)
                                <tr>
                                    <td colspan="6" class="fg-tertiary text-sm">{{ $log->error_message }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-admin-layout>
