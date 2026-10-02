<x-admin-layout
    active="activity-log"
    title="Activity Log"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => 'Activity Log'],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">Activity Log</h1>
                <p class="page-sub">Every cart edit, discount, hold/void, drawer kick, shift open/close, print, checkout, refund, login, and JS error — by type.</p>
            </div>
        </div>

        {{-- By-type counts for the current filter window --}}
        <div class="grid grid-cols-3 md:grid-cols-5 lg:grid-cols-9 gap-3 mb-5">
            @foreach ($types as $t)
                <a href="{{ route('admin.reports.activity-log.index', array_filter(['type' => $type === $t ? null : $t, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'store_id' => $storeId, 'user_id' => $userId])) }}"
                   class="card {{ $type === $t ? 'ring-2 ring-blue-500' : '' }}" style="text-decoration:none;">
                    <div class="card-body">
                        <div class="cust-kpi-label">{{ ucfirst($t) }}</div>
                        <div class="cust-kpi-value num tnum">{{ number_format($byType[$t] ?? 0) }}</div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">Events ({{ number_format($rows->total()) }})</div>
            </div>

            {{-- Filter bar — plain GET form, server-paginated (this table
                 can run to thousands of rows/day, unlike the other
                 reports' client-side-paginated Collections). --}}
            <form method="GET" class="dt-toolbar" style="flex-wrap:wrap; gap:.5rem;"
                  x-data="{ period: @js($period) }">
                {{-- The controller already resolves period presets via
                     ResolvesReportFilters (same trait every other report
                     uses) — this toolbar just never exposed the picker,
                     forcing every visit through raw from/to dates with no
                     quick "Today"/"Yesterday" shortcut. --}}
                <select name="period" class="pos-input pos-input-sm" x-model="period"
                        @change="$event.target.value !== 'custom' && $el.closest('form').submit()">
                    @foreach (\App\Http\Controllers\Admin\Concerns\ResolvesReportFilters::reportPeriodPresets() as $preset)
                        <option value="{{ $preset }}" @selected($period === $preset)>{{ __('reports.period.presets.'.$preset) }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="pos-input pos-input-sm" x-show="period === 'custom'" x-cloak>
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="pos-input pos-input-sm" x-show="period === 'custom'" x-cloak>
                <select name="type" class="pos-input pos-input-sm">
                    <option value="">All types</option>
                    @foreach ($types as $t)
                        <option value="{{ $t }}" @selected($type === $t)>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
                <select name="store_id" class="pos-input pos-input-sm">
                    <option value="">All stores</option>
                    @foreach ($stores as $s)
                        <option value="{{ $s->id }}" @selected($storeId === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                <select name="user_id" class="pos-input pos-input-sm">
                    <option value="">All users</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected($userId === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">Filter</button>
            </form>

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <div class="dt-empty-title">No activity in this window</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Type</th>
                            <th>Action</th>
                            <th>User</th>
                            <th>Store / Terminal</th>
                            <th>Reference</th>
                            <th>Detail</th>
                            @if (auth()->user()?->is_super_admin)
                                <th>Camera <span class="prod-badge prod-badge-muted">Beta</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="fg-tertiary">{{ $row->created_at->format('d M Y H:i:s') }}</td>
                                <td><span class="prod-badge prod-badge-muted">{{ $row->type }}</span></td>
                                <td class="mono">{{ $row->action }}</td>
                                <td>{{ $row->user?->name ?? '—' }}</td>
                                <td class="fg-tertiary">{{ $row->store?->name }}@if($row->terminal) / {{ $row->terminal->name }}@endif</td>
                                <td class="fg-tertiary">
                                    @if ($row->reference_type)
                                        {{ class_basename($row->reference_type) }} #{{ $row->reference_id }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="fg-tertiary text-xs">
                                    @if ($row->type === 'error')
                                        <span class="text-red-600 dark:text-red-400">{{ \Illuminate\Support\Str::limit($row->meta['message'] ?? '', 120) }}</span>
                                    @elseif ($row->meta)
                                        {{ \Illuminate\Support\Str::limit(collect($row->meta)->map(fn ($v, $k) => "$k: $v")->implode(', '), 120) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                @if (auth()->user()?->is_super_admin)
                                    <td style="min-width:180px;">
                                        @if ($row->terminal_id)
                                            <div x-data="cameraClipWidget({
                                                    subjectType: 'activity',
                                                    subjectId: {{ $row->id }},
                                                    lookupUrl: {{ \Illuminate\Support\Js::from(route('admin.camera-clips.lookup')) }},
                                                    storeUrl: {{ \Illuminate\Support\Js::from(route('admin.camera-clips.store')) }},
                                                 }">
                                                <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost" @click="open()">
                                                    <span x-text="expanded ? 'Hide' : 'Camera (±5s)'"></span>
                                                </button>
                                                <div x-show="expanded" class="mt-2">
                                                    <template x-if="!clip">
                                                        <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost" :disabled="loading" @click="request()">
                                                            <span x-show="!loading">Get video</span>
                                                            <span x-show="loading">Requesting…</span>
                                                        </button>
                                                    </template>
                                                    <template x-if="clip && (clip.status === 'pending' || clip.status === 'processing')">
                                                        <span class="fg-tertiary text-xs">Preparing…</span>
                                                    </template>
                                                    <template x-if="clip && clip.status === 'failed'">
                                                        <div>
                                                            <p class="field-error text-xs mb-1" x-text="clip.error || 'Failed.'"></p>
                                                            <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost" @click="request()">Retry</button>
                                                        </div>
                                                    </template>
                                                    <template x-if="clip && clip.status === 'ready'">
                                                        <div>
                                                            <video :src="clip.url" controls preload="none" style="width:220px; border-radius:var(--radius-md,8px); display:block; background:#000;"></video>
                                                            <a class="pos-btn pos-btn-xs pos-btn-ghost mt-1" :href="clip.url" download>⬇ Download</a>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        @else
                                            <span class="fg-tertiary text-xs">—</span>
                                        @endif
                                    </td>
                                @endif
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
