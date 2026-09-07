@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $action = $isEdit ? route('admin.roles.update', $role) : route('admin.roles.store');
    $selected = $selected ?? [];
    $nameVal = old('name', $isEdit ? $role->name : '');
    $descVal = old('description', $isEdit ? $role->description : '');
@endphp

<div class="page-wide"
     x-data="roleBuilder({ selected: {{ \Illuminate\Support\Js::from(array_map('strval', $selected)) }} })">
    {{-- AJAX via lib/ajax-form.js. --}}
    <form method="POST" action="{{ $action }}" data-ajax-form>
        @csrf
        @if ($isEdit) @method('PATCH') @endif

        {{-- Selected permissions are serialized from Alpine state so
             collapsed groups still submit. The checkboxes below are UI only. --}}
        <template x-for="id in selected" :key="id">
            <input type="hidden" name="permissions[]" :value="id">
        </template>

        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.roles.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('roles.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ $isEdit ? __('roles.edit.title') : __('roles.create.title') }}</h1>
                    <p class="page-sub">{{ $isEdit ? __('roles.edit.sub') : __('roles.create.sub') }}</p>
                </div>
            </div>
            <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                <x-icon name="check" class="w-4 h-4" />
                {{ $isEdit ? __('roles.actions.save') : __('roles.actions.create') }}
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-5 items-start">
            {{-- Identity + copy-from --}}
            <div class="card lg:sticky lg:top-4">
                <div class="card-body">
                    <div class="form-stack">
                        <label class="field">
                            <span class="field-label is-required">{{ __('roles.fields.name') }}</span>
                            @if ($isEdit && $role->is_system)
                                <input type="text" class="pos-input" value="{{ $role->name }}" disabled>
                                <input type="hidden" name="name" value="{{ $role->name }}">
                                <span class="badge badge-accent mt-2"><span class="badge-dot"></span>{{ __('roles.badges.system') }}</span>
                            @else
                                <input type="text" name="name" value="{{ $nameVal }}" maxlength="64" class="pos-input" placeholder="{{ __('roles.fields.name_ph') }}">
                            @endif
                            @error('name')<p class="field-error">{{ $message }}</p>@enderror
                        </label>

                        <label class="field">
                            <span class="field-label">{{ __('roles.fields.description') }}</span>
                            <textarea name="description" rows="3" class="pos-input" placeholder="{{ __('roles.fields.description_ph') }}">{{ $descVal }}</textarea>
                            @error('description')<p class="field-error">{{ $message }}</p>@enderror
                        </label>

                        <div class="role-selected-summary">
                            <span x-text="selectedCount"></span> {{ __('roles.matrix.selected_count', ['count' => '']) }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Permission matrix --}}
            <div class="space-y-4">
                @foreach ($groups as $groupName => $perms)
                    @php $ids = $perms->pluck('id')->all(); @endphp
                    <div class="card card-pad-0 role-group">
                        <div class="role-group-head">
                            <button type="button" class="role-group-toggle" @click="toggleOpen({{ \Illuminate\Support\Js::from($groupName) }})">
                                <span class="role-group-chev" :class="{ 'is-closed': !isOpen(@js($groupName)) }"><x-icon name="chevron" class="w-4 h-4" /></span>
                                <span class="role-group-name">{{ $groupName }}</span>
                                <span class="role-group-count" x-text="groupSelectedCount(@js($ids)) + ' / ' + {{ count($ids) }}"></span>
                            </button>
                            <label class="role-group-all">
                                <input type="checkbox"
                                       class="pos-check"
                                       @change="toggleGroup(@js($ids))"
                                       :checked="isGroupAll(@js($ids))"
                                       x-effect="$el.indeterminate = isGroupSome(@js($ids))">
                                <span>{{ __('roles.matrix.select_all') }}</span>
                            </label>
                        </div>

                        <ul class="role-perm-list" x-show="isOpen(@js($groupName))" x-cloak>
                            @foreach ($perms as $perm)
                                <li class="role-perm">
                                    <label class="role-perm-label">
                                        <input type="checkbox"
                                               class="pos-check"
                                               :checked="isSelected({{ $perm->id }})"
                                               @change="toggle({{ $perm->id }})">
                                        <span class="role-perm-text">
                                            <span class="role-perm-title">
                                                {{ $perm->label }}
                                                @if ($perm->is_dangerous)
                                                    <span class="role-perm-danger" title="{{ __('roles.matrix.dangerous_hint') }}">{{ __('roles.matrix.dangerous') }}</span>
                                                @endif
                                            </span>
                                            <span class="role-perm-key mono">{{ $perm->key }}</span>
                                            @if ($perm->description)
                                                <span class="role-perm-desc">{{ $perm->description }}</span>
                                            @endif
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </form>
</div>
