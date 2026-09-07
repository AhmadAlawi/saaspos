@extends('installer.layout')

@section('content')
    <h2 class="text-[22px] font-semibold fg-primary tracking-[-0.01em]">{{ __('installer.demo.heading') }}</h2>
    <p class="mt-2 text-[13.5px] fg-secondary leading-relaxed">{{ __('installer.demo.subheading') }}</p>

    @if (config('app.debug'))
        <div class="alert alert-info mt-6">
            <span class="alert-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
            </span>
            <div class="alert-body">
                <div class="alert-title">{{ __('installer.demo.stub_notice_title') }}</div>
                <div class="alert-msg">{{ __('installer.demo.stub_notice') }}</div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('install.complete') }}" class="mt-8 space-y-6"
          x-data="{ submitting: false, mode: 'none' }" @submit="submitting = true">
        @csrf

        <div class="space-y-2.5">
            @foreach (['full', 'minimal', 'none'] as $mode)
                <label class="card flex cursor-pointer items-start gap-3 p-4 transition"
                       :class="mode === '{{ $mode }}' ? 'ring-2' : 'hover:bg-[var(--bg-hover)]'"
                       :style="mode === '{{ $mode }}' ? 'border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft);' : ''">
                    <input type="radio" name="demo_mode" value="{{ $mode }}" @checked($mode === 'none') required x-model="mode"
                           class="pos-radio mt-1">
                    <div class="min-w-0">
                        <span class="block text-[13.5px] font-semibold fg-primary">{{ __("installer.demo.options.{$mode}.title") }}</span>
                        <span class="block text-[12.5px] fg-secondary mt-1 leading-relaxed">{{ __("installer.demo.options.{$mode}.description") }}</span>
                    </div>
                </label>
            @endforeach
        </div>

        <div class="flex justify-between">
            <a href="{{ route('install.admin') }}" class="pos-btn pos-btn-ghost">{{ __('installer.back') }}</a>
            <x-installer.submit-button :busy-label="__('installer.demo.finishing')">
                {{ __('installer.demo.finish') }}
            </x-installer.submit-button>
        </div>
    </form>
@endsection
