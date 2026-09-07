@extends('installer.layout')

@section('content')
    <h2 class="text-[22px] font-semibold fg-primary tracking-[-0.01em]">{{ __('installer.database.heading') }}</h2>
    <p class="mt-2 text-[13.5px] fg-secondary leading-relaxed">{{ __('installer.database.subheading') }}</p>

    <form method="POST" action="{{ route('install.database.save') }}" class="mt-8 space-y-5"
          x-data="installerDbForm({ actionUrl: '{{ route('install.database.save') }}', genericError: @js(__('installer.database.unexpected_error')) })"
          @submit.prevent="submit($event)">
        @csrf

        {{-- Connection-test errors land here inline — the page is never
             reloaded, so the typed credentials stay put. --}}
        <div x-show="error" x-cloak class="alert alert-danger" role="alert">
            <span class="alert-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/></svg>
            </span>
            <div class="alert-body">
                <div class="alert-title" x-text="error"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="field-label">{{ __('installer.database.host') }}</span>
                <input type="text" name="host" required value="{{ old('host', $defaults['host']) }}" class="pos-input">
            </label>
            <label class="block">
                <span class="field-label">{{ __('installer.database.port') }}</span>
                <input type="number" name="port" required value="{{ old('port', $defaults['port']) }}" class="pos-input mono">
            </label>
        </div>

        <label class="block">
            <span class="field-label">{{ __('installer.database.name') }}</span>
            <input type="text" name="database" required value="{{ old('database', $defaults['database']) }}" class="pos-input">
            <p class="mt-2 text-[12px] fg-tertiary">{{ __('installer.database.name_hint') }}</p>
        </label>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="field-label">{{ __('installer.database.username') }}</span>
                <input type="text" name="username" required value="{{ old('username', $defaults['username']) }}" class="pos-input">
            </label>
            <label class="block">
                <span class="field-label">{{ __('installer.database.password') }}</span>
                <x-admin.password-input name="password" autocomplete="new-password" />
            </label>
        </div>

        <div class="alert alert-info">
            <span class="alert-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
            </span>
            <div class="alert-body">
                <div class="alert-title">{{ __('installer.database.progress_title') }}</div>
                <div class="alert-msg">{{ __('installer.database.progress_notice') }}</div>
            </div>
        </div>

        <div class="flex justify-between">
            <a href="{{ route('install.license') }}" class="pos-btn pos-btn-ghost">{{ __('installer.back') }}</a>
            <x-installer.submit-button :busy-label="__('installer.database.running')">
                {{ __('installer.database.save_and_continue') }}
            </x-installer.submit-button>
        </div>
    </form>
@endsection
