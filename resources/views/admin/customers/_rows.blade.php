{{--
    Table-view rows for the Customers list.

    Rendered two ways:
      - Inline by `admin.customers.index` for the first page.
      - Standalone by `CustomerController@rows` (JSON `html`) for every
        subsequent page / search / filter / sort, then swapped into the
        `<tbody>` by the `dataTableServer` mixin.

    Swapped in via innerHTML, so every Alpine directive here is re-wired by
    `Alpine.initTree()` after the swap. Runs inside the `customersIndexPage()`
    scope, so `rowState`, `togglingIds` and `toggleActive` all resolve.

    Expects: $customers (iterable of Customer, with `group` loaded).
--}}
@foreach ($customers as $c)
    {{-- Row click → edit (system-wide convention). View / toggle / delete
         live in the single actions column. --}}
    <tr class="prod-row" data-dt-row
        data-dt-name="{{ $c->name }}"
        data-dt-id="{{ $c->id }}"
        data-dt-active="{{ $c->is_active ? 1 : 0 }}"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.customers.edit', $c)) }}">
        <td class="mono">{{ $c->code }}</td>
        <td>
            <div class="store-row-name-line">
                <span class="store-row-name">{{ $c->name }}</span>
                @if ($c->is_business)
                    <span class="prod-badge prod-badge-muted">{{ __('customers.badges.business') }}</span>
                @endif
            </div>
        </td>
        <td class="mono">{{ $c->phone ? \App\Support\PhoneFormatter::pretty($c->phone) : '—' }}</td>
        <td>{{ $c->email ? $c->display_email : '—' }}</td>
        <td>{{ $c->group?->name ?: '—' }}</td>
        <td class="num tnum">{{ format_money($c->outstanding_balance) }}</td>
        <td class="num tnum">{{ format_money($c->store_credit_balance) }}</td>
        {{-- Status badge mirrors the live toggle state — bound to
             `rowState[id].is_active` so it flips in sync when the toggle is
             clicked, without a server round-trip. --}}
        <td>
            <span class="prod-badge"
                  :class="rowState[{{ $c->id }}]?.is_active ? 'prod-badge-positive' : 'prod-badge-muted'"
                  x-text="rowState[{{ $c->id }}]?.is_active
                            ? @js(__('customers.badges.active'))
                            : @js(__('customers.badges.inactive'))"></span>
        </td>
        <td>
            <div class="prod-row-actions">
                @can('update', $c)
                    {{-- Status toggle — same component as Products. --}}
                    <button type="button"
                            class="prod-status-btn"
                            :class="{ 'is-on': rowState[{{ $c->id }}]?.is_active }"
                            :aria-pressed="rowState[{{ $c->id }}]?.is_active ? 'true' : 'false'"
                            :title="rowState[{{ $c->id }}]?.is_active ? @js(__('customers.actions.tip_active_on')) : @js(__('customers.actions.tip_active_off'))"
                            :disabled="togglingIds.includes({{ $c->id }})"
                            @click.stop="toggleActive({{ $c->id }})"
                            aria-label="{{ __('customers.actions.toggle_active') }}">
                        <span class="prod-status-thumb" aria-hidden="true"></span>
                    </button>
                @endcan
                <x-admin.row-actions>
                    @can('view', $c)
                        <x-admin.row-action :href="route('admin.customers.show', $c)" icon="eye" :label="__('customers.actions.view')" />
                    @endcan
                    @can('update', $c)
                        <x-admin.row-action :href="route('admin.customers.edit', $c)" icon="edit" :label="__('table.action.edit')" />
                    @endcan
                    @can('delete', $c)
                        <div class="row-action-sep"></div>
                        <x-admin.row-action icon="trash" variant="danger" :label="__('customers.actions.delete')"
                            @click="$store.confirm.show({
                                title: {{ \Illuminate\Support\Js::from(__('customers.confirm_delete.title', ['name' => $c->name])) }},
                                message: {{ \Illuminate\Support\Js::from(__('customers.confirm_delete.message')) }},
                                intent: 'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('customers.confirm_delete.confirm')) }},
                                onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.customers.destroy', $c)) }}),
                            })" />
                    @endcan
                </x-admin.row-actions>
            </div>
        </td>
    </tr>
@endforeach
