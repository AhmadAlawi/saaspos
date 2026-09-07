<x-admin-layout
    active="saved-reports"
    :title="__('reports.saved.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section'), 'href' => route('admin.reports.index')],
        ['label' => __('reports.saved.title')],
    ]">

    <div class="page-wide" x-data="{
            async removeSaved(id, name) {
                $store.confirm.show({
                    title: @js(__('reports.saved.delete.title')),
                    message: @js(__('reports.saved.delete.message')).replace(':name', name),
                    intent: 'danger',
                    confirmLabel: @js(__('reports.saved.delete.confirm')),
                    onConfirm: async () => {
                        try {
                            await $http.delete('/admin/reports/saved/' + id);
                            $store.toasts.push({ type: 'success', message: @js(__('reports.saved.delete.done')) });
                            window.location.reload();
                        } catch (e) {
                            $store.toasts.push({ type: 'error', message: e?.message || @js(__('reports.saved.errors.save_failed')) });
                        }
                    },
                });
            }
         }">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.saved.title') }}</h1>
                <p class="page-sub">{{ __('reports.saved.sub') }}</p>
            </div>
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('reports.saved.list_title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                </div>
                <x-admin.dt-toolbar-actions />
            </div>

            @if ($rows->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="star" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('reports.saved.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('reports.saved.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('reports.saved.columns.name') }}</th>
                            <th>{{ __('reports.saved.columns.report') }}</th>
                            <th>{{ __('reports.saved.columns.scope') }}</th>
                            <th>{{ __('reports.saved.columns.saved_by') }}</th>
                            <th class="dt-actions-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $sr = $row['model']; @endphp
                            <tr class="prod-row" data-dt-row @click="window.location.href = {{ \Illuminate\Support\Js::from($row['url']) }}">
                                <td class="font-medium">{{ $sr->name }}</td>
                                <td class="fg-tertiary">{{ $row['title'] }}</td>
                                <td>
                                    @if ($sr->is_shared)
                                        <span class="prod-badge prod-badge-positive">{{ __('reports.saved.scope_shared') }}</span>
                                    @else
                                        <span class="prod-badge prod-badge-muted">{{ __('reports.saved.scope_private') }}</span>
                                    @endif
                                </td>
                                <td class="fg-tertiary">{{ $sr->user?->name ?? '—' }}</td>
                                <td class="dt-actions-col" @click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ $row['url'] }}" class="pos-btn pos-btn-xs pos-btn-ghost">{{ __('reports.saved.open') }}</a>
                                        @if ($row['is_owner'])
                                            <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost text-rose-600 dark:text-rose-400"
                                                    @click="removeSaved({{ $sr->id }}, {{ \Illuminate\Support\Js::from($sr->name) }})">
                                                {{ __('reports.saved.delete.button') }}
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
