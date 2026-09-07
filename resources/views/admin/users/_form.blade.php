@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $action = $isEdit ? route('admin.users.update', $user) : route('admin.users.store');
    $rows   = old('stores', $assigned ?? []);

    $v = fn (string $key, $default = null) => old($key, $isEdit ? data_get($user, $key) : $default);
@endphp

<div class="page-wide"
     x-data="{ ...userStoreRoles({ rows: {{ \Illuminate\Support\Js::from($rows) }} }), pwMode: '{{ old('password_mode', 'set') }}' }">
    {{-- AJAX via lib/ajax-form.js. --}}
    <form method="POST" action="{{ $action }}" data-ajax-form>
        @csrf
        @if ($isEdit) @method('PATCH') @endif

        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.users.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('users.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ $isEdit ? __('users.edit.title') : __('users.create.title') }}</h1>
                    <p class="page-sub">{{ $isEdit ? __('users.edit.sub') : __('users.create.sub') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.users.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                    {{ __('users.actions.discard') }}
                </a>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ $isEdit ? __('users.actions.save') : __('users.actions.create') }}
                </button>
            </div>
        </div>

        <div class="space-y-5">
            {{-- Profile --}}
            <div class="card">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('users.form.profile') }}</div>
                    <div class="card-title-sub">{{ __('users.form.profile_sub') }}</div>
                </div></div>
                <div class="card-body">
                    <div class="form-stack">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="field">
                                <span class="field-label is-required">{{ __('users.fields.name') }}</span>
                                <input type="text" name="name" value="{{ $v('name', '') }}" maxlength="191" class="pos-input" placeholder="{{ __('users.fields.name_ph') }}">
                                @error('name')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                            <label class="field">
                                <span class="field-label is-required">{{ __('users.fields.email') }}</span>
                                <input type="email" name="email" value="{{ $v('email', '') }}" maxlength="191" class="pos-input" placeholder="{{ __('users.fields.email_ph') }}">
                                @error('email')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="field">
                                <span class="field-label">{{ __('users.fields.phone') }}</span>
                                <input type="text" name="phone" value="{{ $v('phone', '') }}" maxlength="32" class="pos-input">
                                @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                            <label class="field">
                                <span class="field-label">{{ __('users.fields.locale') }}</span>
                                <input type="text" name="locale" value="{{ $v('locale', 'en') }}" maxlength="8" class="pos-input mono" placeholder="{{ __('users.fields.locale_ph') }}">
                                @error('locale')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Store access --}}
            <div class="card">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('users.form.access') }}</div>
                    <div class="card-title-sub">{{ __('users.form.access_sub') }}</div>
                </div></div>
                <div class="card-body">
                    <div class="form-stack">
                        <template x-for="(row, i) in rows" :key="row._uid">
                            <div class="user-access-row">
                                <select class="pos-input"
                                        x-data="enhancedSelect()"
                                        x-effect="ts && ts.setValue(row.store_id, true)"
                                        x-model="row.store_id"
                                        :name="`stores[${i}][store_id]`">
                                    <option value="">{{ __('users.fields.store') }}…</option>
                                    @foreach ($stores as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                                <select class="pos-input"
                                        x-data="enhancedSelect()"
                                        x-effect="ts && ts.setValue(row.role_id, true)"
                                        x-model="row.role_id"
                                        :name="`stores[${i}][role_id]`">
                                    <option value="">{{ __('users.fields.role') }}…</option>
                                    @foreach ($roles as $r)
                                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="prod-del-btn" @click="removeRow(i)" aria-label="{{ __('users.actions.remove') }}" title="{{ __('users.actions.remove') }}">
                                    <x-icon name="trash" class="w-4 h-4" />
                                </button>
                            </div>
                        </template>

                        @error('stores')<p class="field-error">{{ $message }}</p>@enderror

                        <div>
                            <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="addRow()">
                                <x-icon name="plus" class="w-4 h-4" />
                                {{ __('users.actions.add_store') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Security --}}
            <div class="card">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('users.form.security') }}</div>
                    <div class="card-title-sub">{{ __('users.form.security_sub') }}</div>
                </div></div>
                <div class="card-body">
                    <div class="form-stack">
                        @if ($isEdit)
                            <label class="field">
                                <span class="field-label">{{ __('users.fields.password') }}</span>
                                <x-admin.password-input name="password" autocomplete="new-password" placeholder="{{ __('users.fields.password_ph') }}" />
                                <p class="field-help">{{ __('users.fields.password_edit_help') }}</p>
                                @error('password')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        @else
                            <label class="field">
                                <span class="field-label">{{ __('users.fields.password_mode') }}</span>
                                <select name="password_mode" class="pos-input"
                                        x-data="enhancedSelect()"
                                        x-effect="ts && ts.setValue(pwMode, true)"
                                        x-model="pwMode">
                                    <option value="set">{{ __('users.fields.password_mode_set') }}</option>
                                    <option value="setup_link">{{ __('users.fields.password_mode_link') }}</option>
                                </select>
                            </label>
                            <label class="field" x-show="pwMode === 'set'" x-cloak>
                                <span class="field-label is-required">{{ __('users.fields.password') }}</span>
                                <x-admin.password-input name="password" autocomplete="new-password" placeholder="{{ __('users.fields.password_ph') }}" />
                                @error('password')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        @endif

                        <label class="field">
                            <span class="field-label">{{ __('users.fields.pin') }}</span>
                            <input type="text" name="pin" class="pos-input mono" inputmode="numeric"
                                   pattern="\d{6}" maxlength="6" autocomplete="off"
                                   placeholder="{{ __('users.fields.pin_ph') }}">
                            <p class="field-help">{{ __('users.fields.pin_help') }}</p>
                            @error('pin')<p class="field-error">{{ $message }}</p>@enderror
                        </label>

                        <label class="field-toggle">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $isEdit ? $user->is_active : true))>
                            <span>
                                <span class="block">{{ __('users.fields.is_active') }}</span>
                                <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('users.fields.is_active_help') }}</span>
                            </span>
                        </label>

                        @if ($canSuperAdmin)
                            <label class="field-toggle">
                                <input type="hidden" name="is_super_admin" value="0">
                                <input type="checkbox" name="is_super_admin" value="1" @checked((bool) old('is_super_admin', $isEdit ? $user->is_super_admin : false))>
                                <span>
                                    <span class="block">{{ __('users.fields.is_super_admin') }}</span>
                                    <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('users.fields.is_super_admin_help') }}</span>
                                </span>
                            </label>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
