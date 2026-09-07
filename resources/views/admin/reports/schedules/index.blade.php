<x-admin-layout
    active="scheduled-reports"
    :title="__('reports.schedule.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.schedule.title')],
    ]">

    <div class="page-wide" x-data="{
            busy: false,
            async post(url) {
                if (this.busy) return null;
                this.busy = true;
                try {
                    const res = await $http.post(url, {});
                    return res;
                } catch (e) {
                    $store.toasts.push({ type: 'error', message: e?.message || @js(__('reports.schedule.errors.action_failed')) });
                    return null;
                } finally {
                    this.busy = false;
                }
            },
            async runNow(id) {
                const res = await this.post('/admin/reports/schedules/' + id + '/run-now');
                if (! res) return;
                const ok = res.data?.status === 'success';
                $store.toasts.push({ type: ok ? 'success' : 'error', message: res.data?.message });
            },
            async toggle(id, activate) {
                const res = await this.post('/admin/reports/schedules/' + id + '/' + (activate ? 'resume' : 'pause'));
                if (res) window.location.reload();
            },
            async remove(id, name) {
                $store.confirm.show({
                    title: @js(__('reports.schedule.delete.title')),
                    message: @js(__('reports.schedule.delete.message')).replace(':name', name),
                    intent: 'danger',
                    confirmLabel: @js(__('reports.schedule.delete.confirm')),
                    onConfirm: async () => {
                        try {
                            await $http.delete('/admin/reports/schedules/' + id);
                            $store.toasts.push({ type: 'success', message: @js(__('reports.schedule.delete.done')) });
                            window.location.reload();
                        } catch (e) {
                            $store.toasts.push({ type: 'error', message: e?.message || @js(__('reports.schedule.errors.action_failed')) });
                        }
                    },
                });
            }
         }">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.schedule.title') }}</h1>
                <p class="page-sub">{{ __('reports.schedule.sub') }}</p>
            </div>
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('reports.schedule.list_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                </div>
                <x-admin.dt-toolbar-actions />
            </div>

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="clock" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.schedule.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.schedule.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.schedule.columns.name') }}</th>
                            <th>{{ __('reports.schedule.columns.report') }}</th>
                            <th>{{ __('reports.schedule.columns.cadence') }}</th>
                            <th>{{ __('reports.schedule.columns.recipients') }}</th>
                            <th>{{ __('reports.schedule.columns.next_run') }}</th>
                            <th>{{ __('reports.schedule.columns.last_run') }}</th>
                            <th class="dt-actions-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $s        = $row['model'];
                                $lastRun  = $row['last_run'];
                                $recipCount = count($s->recipients_email ?? []);
                            @endphp
                            <tr class="prod-row" data-dt-row>
                                <td class="font-medium">
                                    {{ $s->name }}
                                    <span class="prod-badge prod-badge-muted ms-1">{{ strtoupper($s->format) }}</span>
                                    @unless ($s->is_active)
                                        <span class="prod-badge prod-badge-negative ms-1">{{ __('reports.schedule.status_paused') }}</span>
                                    @endunless
                                </td>
                                <td class="fg-tertiary">{{ $row['title'] }}</td>
                                <td class="fg-tertiary">
                                    @switch($s->frequency)
                                        @case('weekly')
                                            {{ __('reports.schedule.cadence.weekly', ['day' => __('reports.schedule.days.'.$s->day_of_week), 'time' => \Illuminate\Support\Str::of($s->time_of_day)->beforeLast(':')]) }}
                                            @break
                                        @case('monthly')
                                            {{ __('reports.schedule.cadence.monthly', ['day' => $s->day_of_month, 'time' => \Illuminate\Support\Str::of($s->time_of_day)->beforeLast(':')]) }}
                                            @break
                                        @default
                                            {{ __('reports.schedule.cadence.daily', ['time' => \Illuminate\Support\Str::of($s->time_of_day)->beforeLast(':')]) }}
                                    @endswitch
                                </td>
                                <td class="fg-tertiary">{{ trans_choice('reports.schedule.recipient_count', $recipCount, ['count' => $recipCount]) }}</td>
                                <td class="fg-tertiary">
                                    @if ($s->is_active && $s->next_run_at)
                                        {{ $s->displayTime('next_run_at')?->format('d M Y, H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($lastRun)
                                        @if ($lastRun->status === 'success')
                                            <span class="prod-badge prod-badge-positive">{{ __('reports.schedule.run_ok') }}</span>
                                        @else
                                            <span class="prod-badge prod-badge-negative" title="{{ $lastRun->error_message }}">{{ __('reports.schedule.run_failed') }}</span>
                                        @endif
                                        <span class="fg-tertiary text-xs ms-1">{{ $lastRun->displayTime('run_at')?->format('d M, H:i') }}</span>
                                    @else
                                        <span class="fg-tertiary">{{ __('reports.schedule.never_run') }}</span>
                                    @endif
                                </td>
                                <td class="dt-actions-col" @click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost" @click="runNow({{ $s->id }})" :disabled="busy">
                                            {{ __('reports.schedule.run_now') }}
                                        </button>
                                        @if ($s->is_active)
                                            <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost" @click="toggle({{ $s->id }}, false)" :disabled="busy">
                                                {{ __('reports.schedule.pause') }}
                                            </button>
                                        @else
                                            <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost" @click="toggle({{ $s->id }}, true)" :disabled="busy">
                                                {{ __('reports.schedule.resume') }}
                                            </button>
                                        @endif
                                        @if ($row['is_owner'])
                                            <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost text-rose-600 dark:text-rose-400"
                                                    @click="remove({{ $s->id }}, {{ \Illuminate\Support\Js::from($s->name) }})">
                                                {{ __('reports.schedule.delete.button') }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <x-admin.dt-pager />
            @endif
        </div>
    </div>
</x-admin-layout>
