@extends('installer.layout')

@section('content')
    <h2 class="text-[22px] font-semibold fg-primary tracking-[-0.01em]">{{ __('installer.license.heading') }}</h2>
    <p class="mt-2 text-[13.5px] fg-secondary leading-relaxed">{{ __('installer.license.subheading') }}</p>

    @if (config('pos.license.dev_bypass'))
        <div class="alert alert-warning mt-6">
            <span class="alert-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/></svg>
            </span>
            <div class="alert-body">
                <div class="alert-title">{{ __('installer.license.dev_notice_title') }}</div>
                <div class="alert-msg">{{ __('installer.license.dev_notice') }}</div>
            </div>
        </div>
    @endif

    {{-- Online activation only. The offline-blob pane + route + service
         were removed because this build ships with online-only validation
         (the validator service is the single source of truth). --}}
    <form method="POST" action="{{ route('install.license.validate') }}" class="mt-8 space-y-6"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <label class="block">
            <span class="field-label">{{ __('installer.license.key_label') }}</span>
            <input type="text" name="license_key" autocomplete="off" required
                   value="{{ old('license_key') }}"
                   placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                   class="pos-input mono">
            <p class="mt-2 text-[12px] fg-tertiary">{{ __('installer.license.key_hint') }}</p>
        </label>

        <div class="flex justify-between">
            <a href="{{ route('install.requirements') }}" class="pos-btn pos-btn-ghost">
                {{ __('installer.back') }}
            </a>
            <x-installer.submit-button :busy-label="__('installer.license.validating')">
                {{ __('installer.license.validate') }}
            </x-installer.submit-button>
        </div>
    </form>
@endsection
