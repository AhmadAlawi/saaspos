@extends('installer.layout')

@section('content')
    <div class="flex items-start gap-3">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full installer-error-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/></svg>
        </span>
        <div>
            <h2 class="text-[22px] font-semibold fg-primary tracking-[-0.01em]">{{ __('installer.error.heading') }}</h2>
            <p class="mt-2 text-[13.5px] fg-secondary leading-relaxed">
                @if (! empty($stepLabel))
                    {{ __('installer.error.during', ['step' => $stepLabel]) }}
                @else
                    {{ __('installer.error.subheading') }}
                @endif
            </p>
        </div>
    </div>

    {{-- The exact error so the customer can describe it / search docs. --}}
    <div class="alert alert-danger mt-6">
        <span class="alert-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/></svg>
        </span>
        <div class="alert-body">
            <div class="alert-msg mono break-words">{{ $message }}</div>
        </div>
    </div>

    {{-- Copy-paste diagnostic block for a support ticket (§10.1). --}}
    <div class="mt-6" x-data="{ copied: false, copy() {
            navigator.clipboard?.writeText(this.$refs.diag.value).then(() => {
                this.copied = true; setTimeout(() => this.copied = false, 2000);
            });
        } }">
        <div class="flex items-center justify-between">
            <span class="field-label">{{ __('installer.error.diagnostic_label') }}</span>
            <button type="button" class="text-[12px] font-medium accent hover:underline underline-offset-2" @click="copy()">
                <span x-show="!copied">{{ __('installer.error.copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('installer.error.copied') }}</span>
            </button>
        </div>
        <textarea x-ref="diag" readonly rows="8" spellcheck="false"
                  class="pos-input mono mt-2 text-[12px] leading-relaxed whitespace-pre overflow-x-auto">{{ $diagnostic }}</textarea>
        <p class="mt-2 text-[12px] fg-tertiary">{{ __('installer.error.diagnostic_hint') }}</p>
    </div>

    <div class="mt-8 flex flex-wrap items-center gap-3">
        <a href="{{ $retryUrl }}" class="pos-btn pos-btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>
            {{ __('installer.error.retry') }}
        </a>
        @if ($canStartOver)
            <a href="{{ route('install.welcome') }}" class="pos-btn pos-btn-ghost">{{ __('installer.error.start_over') }}</a>
        @endif
        <a href="https://infinitietech.com/support" target="_blank" rel="noopener"
           class="text-[13px] font-medium accent hover:underline underline-offset-2 ms-auto">
            {{ __('installer.footer.contact_support') }}
        </a>
    </div>
@endsection
