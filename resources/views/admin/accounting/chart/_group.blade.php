{{-- One chart-of-accounts group node: a header, its accounts, then its
     child groups (recursively). `$meta` carries per-account balance + guard
     flags. Rendered on first load AND returned as `tree_html` from the AJAX
     save/delete endpoints, so it must stand alone. --}}
@php $level = $level ?? 0; @endphp

<div class="coa-node" style="--coa-lvl: {{ $level }}">
    <div class="coa-group" @click="openGroupEdit({{ $group->id }})" title="{{ __('accounting.chart.groups.edit_hint') }}">
        <span class="coa-group-name">{{ $group->name }}</span>
        <span class="coa-group-type">{{ __('accounting.types.'.$group->type) }}</span>
        <span class="coa-group-edit"><x-icon name="edit" class="w-3.5 h-3.5" /></span>
    </div>

    @foreach ($group->accountsOrdered as $account)
        @php $m = $meta[$account->id] ?? ['balance' => '0', 'locked' => false, 'mapped' => false, 'can_delete' => false]; @endphp
        <div class="coa-acct{{ $account->is_active ? '' : ' is-inactive' }}"
             data-id="{{ $account->id }}"
             :class="{ 'is-selected': form.id === {{ $account->id }} }"
             @click="openEdit({{ $account->id }})">
            <span class="coa-acct-code mono">{{ $account->code }}</span>
            <span class="coa-acct-name">
                {{ $account->name }}
                @if ($account->is_system)
                    <span class="coa-tag" title="{{ __('accounting.chart.system_hint') }}">{{ __('accounting.chart.system') }}</span>
                @endif
                @if ($m['mapped'])
                    <span class="coa-tag coa-tag-mapped" title="{{ __('accounting.chart.mapped_hint') }}">{{ __('accounting.chart.mapped') }}</span>
                @endif
            </span>
            <span class="coa-acct-bal tnum">{{ format_money($m['balance']) }}</span>
            <span class="coa-acct-actions" @click.stop>
                <button type="button"
                        class="prod-status-btn"
                        :class="{ 'is-on': rowsById[{{ $account->id }}]?.is_active }"
                        :disabled="togglingIds.includes({{ $account->id }})"
                        @click="toggleActive({{ $account->id }})"
                        :title="rowsById[{{ $account->id }}]?.is_active ? {{ Js::from(__('table.active')) }} : {{ Js::from(__('table.inactive')) }}">
                    <span class="prod-status-thumb" aria-hidden="true"></span>
                </button>

                <x-admin.row-actions>
                    @if (auth()->user()?->hasPermission('accounting.chart.update'))
                        <x-admin.row-action icon="edit" :label="__('table.action.edit')" @click="openEdit({{ $account->id }})" />
                    @endif
                    <x-admin.row-action icon="list" :label="__('accounting.chart.show_gl')"
                        :href="route('admin.reports.general-ledger.index', ['account_id' => $account->id])" />
                    @if ($m['can_delete'] && auth()->user()?->hasPermission('accounting.chart.update'))
                        <div class="row-action-sep"></div>
                        <x-admin.row-action icon="trash" variant="danger" :label="__('accounting.chart.actions.delete')"
                            @click="confirmDeleteAccount({{ $account->id }})" />
                    @endif
                </x-admin.row-actions>
            </span>
        </div>
    @endforeach

    @foreach ($group->childrenRecursive as $child)
        @include('admin.accounting.chart._group', ['group' => $child, 'meta' => $meta, 'level' => $level + 1])
    @endforeach
</div>
