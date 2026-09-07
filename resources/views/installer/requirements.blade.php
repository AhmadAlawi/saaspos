@extends('installer.layout')

@section('content')
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-[22px] font-semibold fg-primary tracking-[-0.01em]">{{ __('installer.requirements.heading') }}</h2>
            <p class="mt-2 text-[13.5px] fg-secondary leading-relaxed">{{ __('installer.requirements.subheading') }}</p>
        </div>
        <a href="{{ route('install.requirements') }}" class="pos-btn pos-btn-sm shrink-0">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>
            {{ __('installer.requirements.recheck') }}
        </a>
    </div>

    <div class="mt-8 space-y-6">
        @foreach ($checks as $groupName => $rows)
            <section>
                <div class="eyebrow mb-3">{{ $groupName }}</div>
                <ul class="card card-pad-0">
                    @foreach ($rows as $check)
                        <li class="flex items-start gap-3 px-4 py-3 {{ ! $loop->last ? 'border-b' : '' }} border-subtle">
                            <span @class([
                                'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full',
                                'positive-soft' => $check['status'] === 'pass',
                                'warning-soft'  => $check['status'] === 'warn',
                                'danger-soft'   => $check['status'] === 'fail',
                            ])>
                                @if ($check['status'] === 'pass')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="11" height="11"><path d="M20 6 9 17l-5-5"/></svg>
                                @elseif ($check['status'] === 'warn')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="11" height="11"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/></svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="11" height="11"><path d="m18 6-12 12"/><path d="m6 6 12 12"/></svg>
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-[13px] font-medium fg-primary">{{ $check['label'] }}</p>
                                    <span class="text-[11.5px] mono fg-tertiary">{{ $check['value'] }}</span>
                                </div>
                                @if (! empty($check['hint']) && $check['status'] !== 'pass')
                                    <p class="mt-1 text-[12px] fg-tertiary leading-relaxed">{{ $check['hint'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>

    <div class="mt-8 flex justify-end">
        @if ($canContinue)
            <a href="{{ route('install.license') }}" class="pos-btn pos-btn-primary">
                {{ __('installer.continue') }}
            </a>
        @else
            <span class="pos-btn opacity-[.55] cursor-not-allowed">
                {{ __('installer.requirements.fix_required') }}
            </span>
        @endif
    </div>
@endsection
