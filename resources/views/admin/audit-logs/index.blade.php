<x-admin-layout
    active="audit-logs"
    title="Audit Log"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => 'Audit Log'],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">Audit Log</h1>
                <p class="page-sub">Every create, edit, and delete across products, pricing, users, and other admin-managed records — who did it, and when. Visible to super admins only.</p>
            </div>
        </div>

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">Events ({{ number_format($rows->total()) }})</div>
            </div>

            <form method="GET" class="dt-toolbar" style="flex-wrap:wrap; gap:.5rem;">
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="pos-input pos-input-sm">
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="pos-input pos-input-sm">
                <select name="model" class="pos-input pos-input-sm">
                    <option value="">All models</option>
                    @foreach ($models as $m)
                        <option value="{{ $m }}" @selected($model === $m)>{{ class_basename($m) }}</option>
                    @endforeach
                </select>
                <select name="event" class="pos-input pos-input-sm">
                    <option value="">All events</option>
                    <option value="created" @selected($event === 'created')>Created</option>
                    <option value="updated" @selected($event === 'updated')>Updated</option>
                    <option value="deleted" @selected($event === 'deleted')>Deleted</option>
                </select>
                <select name="user_id" class="pos-input pos-input-sm">
                    <option value="">All users</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected($userId === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" value="{{ $search }}" placeholder="Record ID" class="pos-input pos-input-sm" style="width:120px;">
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">Filter</button>
            </form>

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <div class="dt-empty-title">No edits in this window</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>User</th>
                            <th>Model</th>
                            <th>Record</th>
                            <th>Event</th>
                            <th>Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="fg-tertiary">{{ $row->created_at->format('d M Y H:i:s') }}</td>
                                <td>{{ $row->user?->name ?? 'System' }}</td>
                                <td class="mono">{{ class_basename($row->auditable_type) }}</td>
                                <td class="fg-tertiary">#{{ $row->auditable_id }}</td>
                                <td>
                                    <span class="prod-badge prod-badge-muted">{{ ucfirst($row->event) }}</span>
                                </td>
                                <td class="fg-tertiary text-xs">
                                    @if ($row->event === 'updated')
                                        {{ collect($row->new_values)->map(fn ($v, $k) => "$k: ".json_encode($row->old_values[$k] ?? null)." → ".json_encode($v))->implode(', ') }}
                                    @elseif ($row->event === 'created')
                                        {{ \Illuminate\Support\Str::limit(collect($row->new_values)->map(fn ($v, $k) => "$k: $v")->implode(', '), 160) }}
                                    @else
                                        {{ \Illuminate\Support\Str::limit(collect($row->old_values)->map(fn ($v, $k) => "$k: $v")->implode(', '), 160) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <div class="mt-4">{{ $rows->links() }}</div>
            @endif
        </div>
    </div>
</x-admin-layout>
