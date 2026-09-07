<x-admin-layout
    active="sync-log"
    :title="$log->local_uuid"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('sync_log.crumb_parent')],
        ['label' => __('sync_log.title'), 'href' => route('admin.sync-log.index')],
        ['label' => \Illuminate\Support\Str::limit($log->local_uuid, 16)],
    ]">

    <div class="page-narrow">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.sync-log.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('sync_log.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title mono">{{ $log->local_uuid }}</h1>
                    <p class="page-sub">
                        @if ($log->isSuccess())
                            <span class="prod-badge prod-badge-positive">{{ __('sync_log.status.success') }}</span>
                        @elseif ($log->isConflict())
                            <span class="prod-badge prod-badge-warning">{{ __('sync_log.status.conflict') }}</span>
                        @else
                            <span class="prod-badge prod-badge-danger">{{ __('sync_log.status.failed') }}</span>
                        @endif
                        <span class="ms-2">{{ optional($log->synced_at)->toDateTimeString() }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-body">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="fg-tertiary">{{ __('sync_log.fields.entity') }}</dt>
                        <dd class="mono">{{ $log->entity }}</dd>
                    </div>
                    <div>
                        <dt class="fg-tertiary">{{ __('sync_log.fields.user') }}</dt>
                        <dd>{{ $log->user?->name ?: '—' }}</dd>
                    </div>
                    @if ($sale)
                        <div class="col-span-2">
                            <dt class="fg-tertiary">{{ __('sync_log.fields.sale') }}</dt>
                            <dd>
                                <a href="{{ route('admin.sales.show', $sale) }}" class="mono">
                                    {{ $sale->number }}
                                </a>
                                <span class="fg-tertiary ms-2">{{ __('sync_log.fields.grand_total') }}: {{ format_money($sale->grand_total) }}</span>
                            </dd>
                        </div>
                    @endif
                    @if ($log->result_message)
                        <div class="col-span-2">
                            <dt class="fg-tertiary">{{ __('sync_log.fields.message') }}</dt>
                            <dd>{{ $log->result_message }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div>
                <div class="card-title">{{ __('sync_log.fields.payload') }}</div>
            </div></div>
            <div class="card-body">
                <pre class="mono text-xs sync-log-payload">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
</x-admin-layout>
