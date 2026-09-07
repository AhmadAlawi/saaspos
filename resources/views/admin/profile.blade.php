<x-admin-layout
    active=""
    :title="__('account.title')"
    :crumbs="[
        ['label' => __('account.title')],
    ]">

    <div class="page-wide">
        {{-- Profile + preferences (one form, one save). AJAX via lib/ajax-form.js. --}}
        <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" data-ajax-form>
            @csrf
            @method('PATCH')

            <div class="page-header mb-6">
                <div>
                    <h1 class="page-title">{{ __('account.title') }}</h1>
                    <p class="page-sub">{{ __('account.sub') }}</p>
                </div>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('account.actions.save') }}
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
                {{-- Profile --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('account.sections.profile') }}</div>
                        <div class="card-title-sub">{{ __('account.sections.profile_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <x-admin.image-upload
                                name="avatar"
                                variant="inline"
                                :max-size-kb="8192"
                                :initial-url="$user->avatar_url"
                                :tile-label="__('account.fields.avatar')"
                                :tile-hint="__('account.fields.avatar_hint')" />

                            <label class="field">
                                <span class="field-label is-required">{{ __('account.fields.name') }}</span>
                                <input type="text" name="name" value="{{ old('name', $user->name) }}" maxlength="191" class="pos-input">
                                @error('name')<p class="field-error">{{ $message }}</p>@enderror
                            </label>

                            <label class="field">
                                <span class="field-label is-required">{{ __('account.fields.email') }}</span>
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="191" class="pos-input">
                                @error('email')<p class="field-error">{{ $message }}</p>@enderror
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('account.fields.phone') }}</span>
                                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="32" class="pos-input">
                                @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Preferences --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('account.sections.preferences') }}</div>
                        <div class="card-title-sub">{{ __('account.sections.preferences_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field">
                                <span class="field-label">{{ __('account.fields.language') }}</span>
                                <select name="locale" class="pos-input" x-data="enhancedSelect()">
                                    <option value="">{{ __('account.fields.language_system') }}</option>
                                    @foreach ($languages as $lang)
                                        <option value="{{ $lang->code }}" @selected(old('locale', $user->locale) === $lang->code)>{{ $lang->name }}</option>
                                    @endforeach
                                </select>
                                @error('locale')<p class="field-error">{{ $message }}</p>@enderror
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('account.fields.default_store') }}</span>
                                <select name="default_store_id" class="pos-input" x-data="enhancedSelect()">
                                    <option value="">{{ __('account.fields.default_store_none') }}</option>
                                    @foreach ($stores as $store)
                                        <option value="{{ $store->id }}" @selected((int) old('default_store_id', $user->default_store_id) === $store->id)>{{ $store->name }}</option>
                                    @endforeach
                                </select>
                                @error('default_store_id')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- Password (separate form) --}}
        <div class="card mt-5">
            <div class="card-header"><div>
                <div class="card-title">{{ __('account.sections.password') }}</div>
                <div class="card-title-sub">{{ __('account.sections.password_sub') }}</div>
            </div></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.profile.password') }}" data-ajax-form>
                    @csrf
                    @method('PUT')

                    <div class="form-stack max-w-[420px]">
                        <label class="field" x-data="{ shown: false }">
                            <span class="field-label is-required">{{ __('account.fields.current_password') }}</span>
                            <div class="secret-input-wrap">
                                <input :type="shown ? 'text' : 'password'"
                                       name="current_password"
                                       autocomplete="current-password"
                                       class="pos-input">
                                <button type="button"
                                        class="secret-input-eye"
                                        @click="shown = !shown"
                                        :aria-label="shown ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))"
                                        :title="shown ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))">
                                    <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                    <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                </button>
                            </div>
                            @error('current_password')<p class="field-error">{{ $message }}</p>@enderror
                        </label>

                        <label class="field" x-data="{ shown: false }">
                            <span class="field-label is-required">{{ __('account.fields.new_password') }}</span>
                            <div class="secret-input-wrap">
                                <input :type="shown ? 'text' : 'password'"
                                       name="password"
                                       autocomplete="new-password"
                                       class="pos-input">
                                <button type="button"
                                        class="secret-input-eye"
                                        @click="shown = !shown"
                                        :aria-label="shown ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))"
                                        :title="shown ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))">
                                    <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                    <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                </button>
                            </div>
                            @error('password')<p class="field-error">{{ $message }}</p>@enderror
                        </label>

                        <label class="field" x-data="{ shown: false }">
                            <span class="field-label is-required">{{ __('account.fields.confirm_password') }}</span>
                            <div class="secret-input-wrap">
                                <input :type="shown ? 'text' : 'password'"
                                       name="password_confirmation"
                                       autocomplete="new-password"
                                       class="pos-input">
                                <button type="button"
                                        class="secret-input-eye"
                                        @click="shown = !shown"
                                        :aria-label="shown ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))"
                                        :title="shown ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))">
                                    <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                    <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                </button>
                            </div>
                        </label>

                        <div>
                            <button type="submit" class="pos-btn pos-btn-sm pos-btn-secondary">
                                <x-icon name="lock" class="w-4 h-4" />
                                {{ __('account.actions.change_password') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
