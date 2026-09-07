{{--
    Table-view rows for the Expenses list.

    Rendered inline by `admin.expenses.index` (first page) and standalone by
    `ExpenseController@rows` (JSON `html`) for every subsequent page / filter /
    search.

    Expects: $expenses (iterable of Expense, with category/paymentMethod/supplier
    loaded).
--}}
@foreach ($expenses as $e)
    <tr class="prod-row" data-dt-row
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.expenses.edit', $e)) }}">
        <td class="mono">{{ $e->number }}</td>
        <td>{{ optional($e->expense_date)->format('d M Y') }}</td>
        <td>{{ $e->category?->name ?: '—' }}</td>
        <td>{{ $e->paymentMethod?->name ?: '—' }}</td>
        <td>{{ $e->supplier?->name ?: '—' }}</td>
        <td class="num tnum">{{ format_money($e->total) }}</td>
        <td>
            <div class="prod-row-actions">
                <x-admin.row-actions>
                    @can('update', $e)
                        <x-admin.row-action :href="route('admin.expenses.edit', $e)" icon="edit" :label="__('table.action.edit')" />
                    @endcan
                    @can('delete', $e)
                        <div class="row-action-sep"></div>
                        <x-admin.row-action icon="trash" variant="danger" :label="__('expenses.actions.delete')"
                            @click="$store.confirm.show({
                                title: {{ \Illuminate\Support\Js::from(__('expenses.confirm_delete.title', ['number' => $e->number])) }},
                                message: {{ \Illuminate\Support\Js::from(__('expenses.confirm_delete.message')) }},
                                intent: 'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('expenses.confirm_delete.confirm')) }},
                                onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.expenses.destroy', $e)) }}),
                            })" />
                    @endcan
                </x-admin.row-actions>
            </div>
        </td>
    </tr>
@endforeach
