<x-admin-layout
    active="settings"
    :title="__('settings.email.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.email.title')],
    ]">

    @php
        $m = [
            'driver'       => old('mail_driver',       $company->mail_driver ?: 'smtp'),
            'host'         => old('mail_host',         (string) $company->mail_host),
            'port'         => old('mail_port',         (string) ($company->mail_port ?: '')),
            'username'     => old('mail_username',     (string) $company->mail_username),
            'encryption'   => old('mail_encryption',   (string) $company->mail_encryption),
            'from_address' => old('mail_from_address', (string) $company->mail_from_address),
            'from_name'    => old('mail_from_name',    (string) ($company->mail_from_name ?: $company->name)),
        ];
        $hasPassword = (bool) $company->mail_password;
    @endphp

    <div class="page-wide">
        @if (pos_is_demo())
            <div class="alert alert-warning mb-5">
                <span class="alert-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/></svg>
                </span>
                <div class="alert-body"><p class="alert-msg">{{ __('settings.demo.locked_banner') }}</p></div>
            </div>
        @endif

        {{-- AJAX via lib/ajax-form.js. --}}
        <form method="POST" action="{{ route('admin.settings.email.update') }}" data-ajax-form>
            @csrf
            @method('PATCH')
            {{-- On the demo, `disabled` makes the whole form read-only; `contents`
                 keeps the existing layout intact. --}}
            <fieldset @disabled(pos_is_demo()) class="contents">

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('settings.email.title') }}</h1>
                        <p class="page-sub">{{ __('settings.email.sub') }}</p>
                    </div>
                </div>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('settings.email.actions.save') }}
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
                {{-- Transport --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.email.sections.transport') }}</div>
                        <div class="card-title-sub">{{ __('settings.email.sections.transport_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field">
                                <span class="field-label">{{ __('settings.email.fields.driver') }}</span>
                                <select x-data="enhancedSelect()" name="mail_driver" class="pos-input">
                                    @foreach ($drivers as $d)
                                        <option value="{{ $d }}" @selected($m['driver'] === $d)>{{ __("settings.email.drivers.$d") }}</option>
                                    @endforeach
                                </select>
                                @error('mail_driver')<p class="field-error">{{ $message }}</p>@enderror
                            </label>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="field col-span-2">
                                    <span class="field-label">{{ __('settings.email.fields.host') }}</span>
                                    <input type="text" name="mail_host" value="{{ $m['host'] }}"
                                           class="pos-input" placeholder="{{ __('settings.email.fields.host_ph') }}">
                                    @error('mail_host')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('settings.email.fields.port') }}</span>
                                    <input type="number" name="mail_port" value="{{ $m['port'] }}"
                                           class="pos-input" placeholder="{{ __('settings.email.fields.port_ph') }}" min="1" max="65535">
                                    @error('mail_port')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="field">
                                    <span class="field-label">{{ __('settings.email.fields.username') }}</span>
                                    <input type="text" name="mail_username" value="{{ $m['username'] }}" class="pos-input" autocomplete="off">
                                    @error('mail_username')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('settings.email.fields.password') }}</span>
                                    <input type="password" name="mail_password" class="pos-input" autocomplete="new-password"
                                           placeholder="{{ $hasPassword ? '••••••••' : '' }}">
                                    <p class="field-help">{{ __('settings.email.fields.password_help') }}</p>
                                    @error('mail_password')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>

                            <label class="field">
                                <span class="field-label">{{ __('settings.email.fields.encryption') }}</span>
                                <select x-data="enhancedSelect()" name="mail_encryption" class="pos-input">
                                    <option value="" @selected($m['encryption'] === '')>{{ __('settings.email.fields.encryption_none') }}</option>
                                    @foreach ($encryptions as $e)
                                        <option value="{{ $e }}" @selected($m['encryption'] === $e)>{{ __("settings.email.encryptions.$e") }}</option>
                                    @endforeach
                                </select>
                                @error('mail_encryption')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>

                {{-- From --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.email.sections.from') }}</div>
                        <div class="card-title-sub">{{ __('settings.email.sections.from_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field">
                                <span class="field-label">{{ __('settings.email.fields.from_address') }}</span>
                                <input type="email" name="mail_from_address" value="{{ $m['from_address'] }}" class="pos-input">
                                @error('mail_from_address')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                            <label class="field">
                                <span class="field-label">{{ __('settings.email.fields.from_name') }}</span>
                                <input type="text" name="mail_from_name" value="{{ $m['from_name'] }}" class="pos-input">
                                @error('mail_from_name')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            </fieldset>
        </form>

        {{-- Test send — separate form so it doesn't collide with PATCH submit. --}}
        <div class="card mt-5">
            <div class="card-header"><div>
                <div class="card-title">{{ __('settings.email.sections.test') }}</div>
                <div class="card-title-sub">{{ __('settings.email.sections.test_sub') }}</div>
            </div></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.settings.email.test') }}" class="flex items-end gap-3 flex-wrap" data-ajax-form>
                    @csrf
                    <fieldset @disabled(pos_is_demo()) class="contents">
                    <label class="field grow shrink basis-[360px] min-w-[280px] max-w-[560px]">
                        <span class="field-label">{{ __('settings.email.fields.test_to') }}</span>
                        <input type="email" name="to" value="{{ auth()->user()->email ?? '' }}" class="pos-input" required>
                    </label>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-secondary">
                        <x-icon name="mail" class="w-4 h-4" />
                        {{ __('settings.email.actions.send_test') }}
                    </button>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
