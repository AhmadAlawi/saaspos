{{-- One row of the dashboard setup checklist.
     A done step is inert (a <div>, no link — nothing left to do); an
     outstanding step is a link straight to the page that completes it. --}}
@php($isDone = (bool) ($step['done'] ?? false))

<{{ $isDone ? 'div' : 'a' }}
    @if (! $isDone) href="{{ $step['url'] }}" @endif
    class="setup-step{{ $isDone ? ' is-done' : '' }}">

    <span class="setup-step-check">
        @if ($isDone)
            <x-icon name="check" class="w-3 h-3" />
        @endif
    </span>

    <span class="setup-step-body">
        <span class="setup-step-label">{{ $step['label'] }}</span>
        <span class="setup-step-desc">{{ $step['description'] }}</span>
    </span>

    @if ($isDone)
        <span class="setup-step-state">{{ __('admin.dashboard.setup.done') }}</span>
    @else
        <span class="setup-step-go">
            {{ __('admin.dashboard.setup.go') }}
            <x-icon name="chevron" class="w-3 h-3 -rotate-90 rtl:rotate-90" />
        </span>
    @endif
</{{ $isDone ? 'div' : 'a' }}>
