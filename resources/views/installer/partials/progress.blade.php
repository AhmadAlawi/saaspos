@php
    // The License step (normally #3) is hidden when license enforcement is
    // off (config/pos.php → license.required) — the installer skips it — so
    // the indicator collapses and Database/Admin/Demo each shift down one.
    $licenseRequired = (bool) config('pos.license.required');

    $labels = [__('installer.steps.welcome'), __('installer.steps.requirements')];
    if ($licenseRequired) {
        $labels[] = __('installer.steps.license');
    }
    $labels[] = __('installer.steps.database');
    $labels[] = __('installer.steps.admin');
    $labels[] = __('installer.steps.demo');

    if (! $licenseRequired && $currentStep > 3) {
        $currentStep -= 1; // controllers still number License as 3; re-base.
    }

    $steps = [];
    foreach ($labels as $i => $label) {
        $steps[$i + 1] = $label;
    }

    $total = count($steps);
    $currentLabel = $steps[$currentStep] ?? '';
    $progressPct = $total > 1 ? (($currentStep - 1) / ($total - 1)) * 100 : 100;
@endphp

<div class="mb-2 flex items-baseline justify-between">
    <div class="eyebrow">
        {{ __('installer.step_x_of_y', ['current' => $currentStep, 'total' => $total]) }}
        <span class="fg-tertiary mx-1.5">·</span>
        <span class="fg-secondary normal-case tracking-normal text-[12px] font-semibold">{{ $currentLabel }}</span>
    </div>
    <div class="text-[11px] mono fg-tertiary">{{ $currentStep }}/{{ $total }}</div>
</div>

<div class="relative">
    {{-- Connector rail (background + filled portion) --}}
    <div class="absolute top-1/2 left-0 right-0 -translate-y-1/2 h-px bg-[var(--border-subtle)]"></div>
    <div class="absolute top-1/2 left-0 -translate-y-1/2 h-px transition-all duration-300 bg-[var(--accent)]"
         style="width: {{ $progressPct }}%"></div>

    {{-- Step dots --}}
    <ol class="relative flex items-center justify-between">
        @foreach ($steps as $num => $label)
            @php
                $isActive = $num == $currentStep;
                $isDone   = $num < $currentStep;
            @endphp
            <li class="relative" title="{{ $label }}">
                @if ($isDone)
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-[var(--accent)] text-[var(--accent-fg)]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="10" height="10"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                @elseif ($isActive)
                    <span class="block h-5 w-5 rounded-full installer-step-dot-active"></span>
                @else
                    <span class="block h-2.5 w-2.5 rounded-full mx-[5px] installer-step-dot-todo"></span>
                @endif
            </li>
        @endforeach
    </ol>
</div>
