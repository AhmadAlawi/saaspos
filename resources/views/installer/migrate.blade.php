@extends('installer.layout')

@section('content')
    <h2 class="text-[22px] font-semibold fg-primary tracking-[-0.01em]">{{ __('installer.migrate.heading') }}</h2>
    <p class="mt-2 text-[13.5px] fg-secondary leading-relaxed">{{ __('installer.migrate.subheading') }}</p>

    <div class="mt-8"
         x-data="migrateRunner({
            stepUrl: @js(route('install.database.migrate.step')),
            labels: {
                preparing: @js(__('installer.migrate.preparing')),
                failedTitle: @js(__('installer.migrate.failed_title')),
            },
         })"
         x-init="run()">

        {{-- Progress bar --}}
        <div class="h-2.5 w-full overflow-hidden rounded-full bg-[var(--border-subtle)]">
            <div class="h-full rounded-full accent-bg transition-[width] duration-300 ease-out"
                 :style="`width: ${progress}%`"></div>
        </div>

        <div class="mt-3 flex items-center justify-between text-[12.5px]">
            <span class="fg-secondary" x-text="detail"></span>
            <span class="mono fg-tertiary" x-text="`${progress}%`"></span>
        </div>

        {{-- Error state --}}
        <template x-if="error">
            <div class="alert alert-danger mt-6">
                <span class="alert-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/></svg>
                </span>
                <div class="alert-body">
                    <div class="alert-title">{{ __('installer.migrate.failed_title') }}</div>
                    <div class="alert-msg" x-text="error"></div>
                </div>
            </div>
        </template>

        <div class="mt-8 flex justify-between" x-show="error" x-cloak>
            <a href="{{ route('install.database') }}" class="pos-btn pos-btn-ghost">{{ __('installer.back') }}</a>
            <button type="button" class="pos-btn pos-btn-primary" @click="run()">{{ __('installer.migrate.retry') }}</button>
        </div>
    </div>
@endsection
