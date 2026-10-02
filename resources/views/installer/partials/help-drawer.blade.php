{{--
    "Need help?" slide-in drawer (docs/features/installer.md §12). Opened from
    the footer link on every step. Pure Alpine — `helpOpen` lives on the body.
    FAQ covers the handful of issues that generate the most install tickets.
--}}

{{-- Backdrop --}}
<div x-show="helpOpen" x-cloak
     class="installer-help-scrim"
     @click="helpOpen = false"
     x-transition.opacity></div>

{{-- Panel --}}
<aside x-show="helpOpen" x-cloak
       class="installer-help-drawer"
       role="dialog" aria-modal="true"
       @keydown.escape.window="helpOpen = false"
       x-transition:enter="installer-help-enter"
       x-transition:enter-start="installer-help-enter-start"
       x-transition:enter-end="installer-help-enter-end">

    <div class="installer-help-head">
        <h3 class="text-[15px] font-semibold fg-primary">{{ __('installer.help.title') }}</h3>
        <button type="button" class="icon-btn" @click="helpOpen = false" aria-label="{{ __('installer.help.close') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="m18 6-12 12"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>

    <div class="installer-help-body">
        @foreach (['blank', 'db', 'rerun', 'license'] as $faq)
            <div class="installer-help-item">
                <div class="installer-help-q">{{ __('installer.help.'.$faq.'_q') }}</div>
                <div class="installer-help-a">{{ __('installer.help.'.$faq.'_a') }}</div>
            </div>
        @endforeach
    </div>

    <div class="installer-help-foot">
        <span class="fg-tertiary">{{ __('installer.footer.need_help') }}</span>
        <a href="https://infinitietech.com/support" target="_blank" rel="noopener"
           class="font-medium accent hover:underline underline-offset-2">{{ __('installer.footer.contact_support') }}</a>
    </div>
</aside>
