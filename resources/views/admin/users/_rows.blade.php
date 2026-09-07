{{--
    Table-view rows for the Users list.

    Rendered inline by `admin.users.index` (first page) and standalone by
    `UserController@rows` (JSON `html`) for every subsequent page / search / sort.

    Expects: $users (iterable of User, with stores/defaultStore loaded).
--}}
@foreach ($users as $u)
    @php $isSelf = $u->id === auth()->id(); @endphp
    <tr data-dt-row
        data-dt-name="{{ $u->name }}"
        data-dt-id="{{ $u->id }}"
        class="prod-row"
        @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.users.edit', $u)) }}">
        <td>
            <div class="user-row-id">
                <span class="avatar">{{ $u->initials }}</span>
                <div>
                    <div class="user-row-name store-row-name">{{ $u->name }}</div>
                    <div class="user-row-email store-row-code">{{ $u->display_email }}</div>
                </div>
            </div>
        </td>
        <td>
            @if ($u->is_super_admin)
                <span class="prod-muted">{{ __('users.badges.super_admin') }}</span>
            @elseif ($u->stores->isEmpty())
                <span class="prod-muted">{{ __('users.list.no_stores') }}</span>
            @else
                <div class="user-store-chips">
                    @foreach ($u->stores as $s)
                        <span class="user-store-chip">{{ $s->name }}</span>
                    @endforeach
                </div>
            @endif
        </td>
        <td>
            <div class="user-status-cell">
                @if ($u->is_active)
                    <span class="prod-badge prod-badge-positive">{{ __('users.badges.active') }}</span>
                @else
                    <span class="prod-badge prod-badge-muted">{{ __('users.badges.inactive') }}</span>
                @endif
                @if ($isSelf)
                    <span class="badge badge-accent"><span class="badge-dot"></span>{{ __('users.badges.you') }}</span>
                @endif
            </div>
        </td>
        <td>
            <div class="prod-row-actions">
                @unless ($isSelf)
                    <form method="POST" action="{{ route('admin.users.toggle', $u) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $u->is_active ? '0' : '1' }}">
                        <button type="submit" class="prod-status-btn" :class="{ 'is-on': {{ $u->is_active ? 'true' : 'false' }} }"
                                @click.stop aria-label="{{ __('users.actions.deactivate') }}"
                                title="{{ $u->is_active ? __('users.actions.deactivate') : __('users.actions.activate') }}">
                            <span class="prod-status-thumb" aria-hidden="true"></span>
                        </button>
                    </form>
                @endunless

                <x-admin.row-actions>
                    <x-admin.row-action :href="route('admin.users.edit', $u)" icon="edit" :label="__('table.action.edit')" />
                    @unless ($isSelf)
                        <div class="row-action-sep"></div>
                        <x-admin.row-action icon="trash" variant="danger" :label="__('users.actions.delete')"
                            @click="$store.confirm.show({
                                title: {{ \Illuminate\Support\Js::from(__('users.delete.title', ['name' => $u->name])) }},
                                message: {{ \Illuminate\Support\Js::from(__('users.delete.message')) }},
                                intent: 'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('users.actions.delete')) }},
                                cancelLabel: {{ \Illuminate\Support\Js::from(__('users.actions.cancel')) }},
                                onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.users.destroy', $u)) }}),
                            })" />
                    @endunless
                </x-admin.row-actions>
            </div>
        </td>
    </tr>
@endforeach
