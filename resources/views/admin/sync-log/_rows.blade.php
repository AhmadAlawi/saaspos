{{--
    Table-view rows for the Sync log.

    Rendered inline by `admin.sync-log.index` (first page) and standalone by
    `SyncLogController@rows` (JSON `html`) for every subsequent page / filter /
    search. Read-only rows (row links to the log detail).

    Expects: $logs (iterable of SyncLog, with user loaded).
--}}
@foreach ($logs as $log)
    <tr class="prod-row" data-dt-row
        data-dt-name="{{ $log->local_uuid }}"
        data-dt-id="{{ $log->id }}"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.sync-log.show', $log)) }}">
        <td>{{ optional($log->synced_at)->toDateTimeString() ?: '—' }}</td>
        <td class="mono">{{ $log->entity }}</td>
        <td class="mono fg-tertiary text-xs">{{ \Illuminate\Support\Str::limit($log->local_uuid, 12) }}</td>
        <td>
            @if ($log->isSuccess())
                <span class="prod-badge prod-badge-positive">{{ __('sync_log.status.success') }}</span>
            @elseif ($log->isConflict())
                <span class="prod-badge prod-badge-warning">{{ __('sync_log.status.conflict') }}</span>
            @else
                <span class="prod-badge prod-badge-danger">{{ __('sync_log.status.failed') }}</span>
            @endif
        </td>
        <td>{{ $log->user?->name ?: '—' }}</td>
        <td class="fg-tertiary">{{ \Illuminate\Support\Str::limit($log->result_message ?: '—', 80) }}</td>
    </tr>
@endforeach
