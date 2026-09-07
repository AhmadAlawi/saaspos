{{--
    Table-view rows for the Suppliers list.

    Rendered two ways:
      - Inline by `admin.suppliers.index` for the first page.
      - Standalone by `SupplierController@rows` (JSON `html`) for every
        subsequent page / search / filter / sort, then swapped into the
        `<tbody>` by the `dataTableServer` mixin.

    Runs inside the (aliased) `customersIndexPage()` scope, so `rowState`,
    `togglingIds` and `toggleActive` all resolve.

    Expects: $suppliers (iterable of Supplier).
--}}
@foreach ($suppliers as $s)
    <tr class="prod-row" data-dt-row
        data-dt-name="{{ $s->name }}"
        data-dt-id="{{ $s->id }}"
        data-dt-active="{{ $s->is_active ? 1 : 0 }}"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.suppliers.edit', $s)) }}">
        <td class="mono">{{ $s->code }}</td>
        <td>
            {{-- Name + business-name sub-line (mirrors the Stores row layout). --}}
            <div class="store-row-id">
                <div class="store-row-name-line">
                    <span class="store-row-name">{{ $s->name }}</span>
                </div>
                @if ($s->business_name)
                    <span class="store-row-code">{{ $s->business_name }}</span>
                @endif
            </div>
        </td>
        <td>{{ $s->contact_person ?: '—' }}</td>
        <td class="mono">{{ $s->phone ? \App\Support\PhoneFormatter::pretty($s->phone) : '—' }}</td>
        <td>{{ $s->city ?: '—' }}</td>
        <td class="num tnum">{{ format_money($s->outstanding_balance) }}</td>
        <td>
            <span class="prod-badge"
                  :class="rowState[{{ $s->id }}]?.is_active ? 'prod-badge-positive' : 'prod-badge-muted'"
                  x-text="rowState[{{ $s->id }}]?.is_active
                            ? @js(__('suppliers.badges.active'))
                            : @js(__('suppliers.badges.inactive'))"></span>
        </td>
        <td>
            <div class="prod-row-actions">
                @can('update', $s)
                    <button type="button"
                            class="prod-status-btn"
                            :class="{ 'is-on': rowState[{{ $s->id }}]?.is_active }"
                            :aria-pressed="rowState[{{ $s->id }}]?.is_active ? 'true' : 'false'"
                            :title="rowState[{{ $s->id }}]?.is_active ? @js(__('suppliers.actions.tip_active_on')) : @js(__('suppliers.actions.tip_active_off'))"
                            :disabled="togglingIds.includes({{ $s->id }})"
                            @click.stop="toggleActive({{ $s->id }})"
                            aria-label="{{ __('suppliers.actions.toggle_active') }}">
                        <span class="prod-status-thumb" aria-hidden="true"></span>
                    </button>
                @endcan
                <x-admin.row-actions>
                    @can('view', $s)
                        <x-admin.row-action :href="route('admin.suppliers.show', $s)" icon="eye" :label="__('suppliers.actions.view')" />
                    @endcan
                    @can('update', $s)
                        <x-admin.row-action :href="route('admin.suppliers.edit', $s)" icon="edit" :label="__('table.action.edit')" />
                    @endcan
                    @can('delete', $s)
                        <div class="row-action-sep"></div>
                        <x-admin.row-action icon="trash" variant="danger" :label="__('suppliers.actions.delete')"
                            @click="$store.confirm.show({
                                title: {{ \Illuminate\Support\Js::from(__('suppliers.confirm_delete.title', ['name' => $s->name])) }},
                                message: {{ \Illuminate\Support\Js::from(__('suppliers.confirm_delete.message')) }},
                                intent: 'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('suppliers.confirm_delete.confirm')) }},
                                onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.suppliers.destroy', $s)) }}),
                            })" />
                    @endcan
                </x-admin.row-actions>
            </div>
        </td>
    </tr>
@endforeach
