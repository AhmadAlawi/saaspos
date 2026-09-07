<x-admin-layout
    active="settings"
    :title="__('settings.legal_pages.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.legal_pages.title')],
    ]">

    <div class="page-wide"
         x-data="{ tab: new URLSearchParams(location.search).get('tab') === 'terms' ? 'terms' : 'privacy' }">
        <x-admin.demo-lock-banner>{{ __('settings.demo.locked_banner_generic') }}</x-admin.demo-lock-banner>

        <form method="POST" action="{{ route('admin.settings.legal-pages.update') }}" data-ajax-form>
            @csrf
            @method('PATCH')
            {{-- On the demo, `disabled` makes the whole form read-only; `contents`
                 keeps the existing layout intact. --}}
            <fieldset @disabled(pos_is_demo()) class="contents">

            <div class="page-header mb-6">
                <div>
                    <h1 class="page-title">{{ __('settings.legal_pages.title') }}</h1>
                    <p class="page-sub">{{ __('settings.legal_pages.sub') }}</p>
                </div>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('settings.legal_pages.save') }}
                </button>
            </div>

            <div class="card card-pad-0">
                <div class="legal-tabs">
                    <button type="button"
                            class="legal-tab"
                            :class="{ 'is-active': tab === 'privacy' }"
                            @click="tab = 'privacy'">
                        <x-icon name="lock" class="w-4 h-4" />
                        {{ __('settings.legal_pages.privacy_title') }}
                    </button>
                    <button type="button"
                            class="legal-tab"
                            :class="{ 'is-active': tab === 'terms' }"
                            @click="tab = 'terms'">
                        <x-icon name="receipt" class="w-4 h-4" />
                        {{ __('settings.legal_pages.terms_title') }}
                    </button>
                </div>

                <div class="card-body legal-body">
                    {{-- Both editors are mounted on page load so switching
                         tabs doesn't reset the user's pending edits in the
                         other one. Toggled via Alpine x-show. The hidden
                         input under each editor is what the form posts. --}}
                    <div x-show="tab === 'privacy'" x-cloak class="legal-pane">
                        <div class="legal-url-hint">
                            <span class="legal-url-label">{{ __('settings.legal_pages.public_url') }}</span>
                            <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="link mono">
                                {{ route('legal.privacy') }}
                            </a>
                        </div>
                        <label class="legal-field-label" for="privacy_policy">{{ __('settings.legal_pages.body') }}</label>
                        <x-admin.rich-text-editor
                            name="privacy_policy"
                            :initial="old('privacy_policy', $company->privacy_policy)" />
                        <p class="legal-field-help">{{ __('settings.legal_pages.help') }}</p>
                        @error('privacy_policy')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div x-show="tab === 'terms'" x-cloak class="legal-pane">
                        <div class="legal-url-hint">
                            <span class="legal-url-label">{{ __('settings.legal_pages.public_url') }}</span>
                            <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="link mono">
                                {{ route('legal.terms') }}
                            </a>
                        </div>
                        <label class="legal-field-label" for="terms_of_service">{{ __('settings.legal_pages.body') }}</label>
                        <x-admin.rich-text-editor
                            name="terms_of_service"
                            :initial="old('terms_of_service', $company->terms_of_service)" />
                        <p class="legal-field-help">{{ __('settings.legal_pages.help') }}</p>
                        @error('terms_of_service')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
            </fieldset>
        </form>
    </div>
</x-admin-layout>
