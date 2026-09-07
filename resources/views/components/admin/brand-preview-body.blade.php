{{-- Self-contained mini-mockup for the branding preview. Uses token vars
     only (no global components) so it renders correctly inside the
     light/dark preview panes, which set their own base + accent tokens. --}}
<div class="bp-surface">
    <div class="bp-row">
        <span class="bp-nav is-active">
            <span class="bp-dot"></span>{{ __('settings.branding.preview.nav') }}
        </span>
        <span class="bp-badge">{{ __('settings.branding.preview.badge') }}</span>
    </div>
    <div class="bp-row bp-row-split">
        <button type="button" class="bp-btn" tabindex="-1">{{ __('settings.branding.preview.button') }}</button>
        <a class="bp-link" tabindex="-1">{{ __('settings.branding.preview.link') }}</a>
    </div>
    <div class="bp-row">
        <span class="bp-toggle" aria-hidden="true"><span class="bp-knob"></span></span>
        <span class="bp-toggle-label">{{ __('settings.branding.preview.toggle') }}</span>
    </div>
</div>
