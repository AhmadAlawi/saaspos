{{--
    Table-view rows for the Roles list.

    Rendered inline by `admin.roles.index` (first page) and standalone by
    `RoleController@rows` (JSON `html`) for every subsequent page / search / sort.

    Expects: $roles (iterable of Role, with `permissions_count` and the
    `assignments` subquery-select present).
--}}
@foreach ($roles as $role)
    <tr data-dt-row
        data-dt-name="{{ $role->name }}"
        data-dt-id="{{ $role->id }}"
        data-dt-permissions="{{ $role->permissions_count }}"
        data-dt-users="{{ $role->assignments }}"
        class="prod-row"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.roles.edit', $role)) }}">
        <td>
            <div class="store-row-id">
                <div class="store-row-name-line">
                    <span class="role-row-name store-row-name">{{ $role->name }}</span>
                    @if ($role->is_system)
                        <span class="store-default-badge">{{ __('roles.badges.system') }}</span>
                    @endif
                </div>
                @if ($role->description)
                    <span class="role-row-desc store-row-code">{{ $role->description }}</span>
                @endif
            </div>
        </td>
        <td class="num tnum">{{ $role->permissions_count }}</td>
        <td class="num tnum">{{ $role->assignments }}</td>
        <td>
            <x-admin.row-actions>
                <x-admin.row-action :href="route('admin.roles.edit', $role)" icon="edit" :label="__('table.action.edit')" />
                <div class="row-action-sep"></div>
                <x-admin.row-action icon="trash" variant="danger" :label="__('roles.actions.delete')"
                    @click="$store.confirm.show({
                        title: {{ \Illuminate\Support\Js::from(__('roles.delete.title', ['name' => $role->name])) }},
                        message: {{ \Illuminate\Support\Js::from(__('roles.delete.message')) }},
                        intent: 'danger',
                        confirmLabel: {{ \Illuminate\Support\Js::from(__('roles.actions.delete')) }},
                        cancelLabel: {{ \Illuminate\Support\Js::from(__('roles.actions.cancel')) }},
                        onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.roles.destroy', $role)) }}),
                    })" />
            </x-admin.row-actions>
        </td>
    </tr>
@endforeach
