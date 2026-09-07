<x-admin-layout
    active="settings"
    :title="__('settings.payment_methods.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.payment_methods.title')],
    ]">

    <div class="page-wide">
        <x-admin.demo-lock-banner>{{ __('settings.demo.locked_banner_generic') }}</x-admin.demo-lock-banner>

        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('settings.payment_methods.title') }}</h1>
                    <p class="page-sub">{{ __('settings.payment_methods.sub') }}</p>
                </div>
            </div>
        </div>

        <div class="card card-pad-0">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('settings.payment_methods.card_title') }}</div>
                    <div class="card-sub fg-tertiary text-xs mt-1">{{ __('settings.payment_methods.card_sub') }}</div>
                </div>
            </div>

            @if ($methods->isEmpty())
                <div class="card-body">
                    <div class="empty-state-title text-center text-sm fg-tertiary py-6">
                        {{ __('settings.payment_methods.empty') }}
                    </div>
                </div>
            @else
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('settings.payment_methods.col_name') }}</th>
                            <th>{{ __('settings.payment_methods.col_type') }}</th>
                            <th>{{ __('settings.payment_methods.col_config') }}</th>
                            <th class="num">{{ __('settings.payment_methods.col_requires_reference') }}</th>
                            <th class="num">{{ __('settings.payment_methods.col_opens_drawer') }}</th>
                            <th class="num">{{ __('settings.payment_methods.col_active') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($methods as $method)
                            @php $creds = $method->provider_credentials ?? []; @endphp
                            <tr>
                                <td>
                                    <div class="flex flex-col gap-1">
                                        <span class="font-semibold">{{ $method->name }}</span>
                                        <span class="mono fg-tertiary text-xs">{{ $method->code }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="badge badge-soft">
                                            {{ __('settings.payment_methods.types.' . $method->type, [], $method->type) }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if ($method->code === 'upi')
                                        <form method="POST"
                                              action="{{ route('admin.settings.payment-methods.upi', $method) }}"
                                              data-ajax-form
                                              class="pm-upi-form">
                                            @csrf
                                            @method('PATCH')
                                            {{-- Read-only on the public demo. --}}
                                            <fieldset @disabled(pos_is_demo()) class="contents">

                                            <div class="pm-upi-eyebrow">{{ __('settings.payment_methods.upi.section_title') }}</div>

                                            <div class="pm-upi-field">
                                                <div class="pm-upi-label-row">
                                                    <label for="upi-vpa-{{ $method->id }}" class="pm-upi-label">
                                                        {{ __('settings.payment_methods.upi.vpa_label') }}
                                                    </label>
                                                    <span class="pos-tip"
                                                          x-data="{ open: false }"
                                                          @mouseenter="open = true"
                                                          @mouseleave="open = false"
                                                          @focusin="open = true"
                                                          @focusout="open = false">
                                                        <button type="button" class="pos-tip-btn" tabindex="0" aria-label="{{ __('settings.payment_methods.upi.vpa_tip') }}">
                                                            <x-icon name="info" class="w-3.5 h-3.5" />
                                                        </button>
                                                        <span class="pos-tip-bubble" x-show="open" x-cloak>
                                                            {{ __('settings.payment_methods.upi.vpa_tip') }}
                                                        </span>
                                                    </span>
                                                </div>
                                                <input type="text"
                                                       id="upi-vpa-{{ $method->id }}"
                                                       name="vpa"
                                                       value="{{ $creds['vpa'] ?? '' }}"
                                                       placeholder="{{ __('settings.payment_methods.upi.vpa_placeholder') }}"
                                                       maxlength="191"
                                                       class="pos-input mono">
                                            </div>

                                            <div class="pm-upi-field">
                                                <div class="pm-upi-label-row">
                                                    <label for="upi-pn-{{ $method->id }}" class="pm-upi-label">
                                                        {{ __('settings.payment_methods.upi.payee_label') }}
                                                    </label>
                                                    <span class="pos-tip"
                                                          x-data="{ open: false }"
                                                          @mouseenter="open = true"
                                                          @mouseleave="open = false"
                                                          @focusin="open = true"
                                                          @focusout="open = false">
                                                        <button type="button" class="pos-tip-btn" tabindex="0" aria-label="{{ __('settings.payment_methods.upi.payee_tip') }}">
                                                            <x-icon name="info" class="w-3.5 h-3.5" />
                                                        </button>
                                                        <span class="pos-tip-bubble" x-show="open" x-cloak>
                                                            {{ __('settings.payment_methods.upi.payee_tip') }}
                                                        </span>
                                                    </span>
                                                </div>
                                                <input type="text"
                                                       id="upi-pn-{{ $method->id }}"
                                                       name="payee_name"
                                                       value="{{ $creds['payee_name'] ?? '' }}"
                                                       placeholder="{{ __('settings.payment_methods.upi.payee_placeholder') }}"
                                                       maxlength="99"
                                                       class="pos-input">
                                            </div>

                                            <div class="pm-upi-actions">
                                                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                                    <x-icon name="check" class="w-4 h-4" />
                                                    {{ __('settings.payment_methods.upi.save') }}
                                                </button>
                                            </div>
                                            </fieldset>
                                        </form>
                                    @else
                                        <span class="fg-tertiary text-xs">—</span>
                                    @endif
                                </td>
                                <td class="num">
                                    <form method="POST"
                                          action="{{ route('admin.settings.payment-methods.toggle-reference', $method) }}"
                                          data-ajax-form
                                          class="inline-flex justify-end">
                                        @csrf
                                        @method('PATCH')
                                        {{-- Hidden field so the toggle posts `0` when unchecked. --}}
                                        <input type="hidden" name="requires_reference" value="0">
                                        <label class="field-toggle"
                                               title="{{ __('settings.payment_methods.toggle_reference_aria') }}">
                                            <input type="checkbox"
                                                   name="requires_reference"
                                                   value="1"
                                                   @checked($method->requires_reference)
                                                   @disabled(pos_is_demo())
                                                   onchange="this.form.requestSubmit()">
                                        </label>
                                    </form>
                                </td>
                                <td class="num">
                                    <form method="POST"
                                          action="{{ route('admin.settings.payment-methods.toggle-drawer', $method) }}"
                                          data-ajax-form
                                          class="inline-flex justify-end">
                                        @csrf
                                        @method('PATCH')
                                        {{-- Hidden field so the toggle posts `0` when unchecked. --}}
                                        <input type="hidden" name="opens_cash_drawer" value="0">
                                        <label class="field-toggle"
                                               title="{{ __('settings.payment_methods.toggle_drawer_aria') }}">
                                            <input type="checkbox"
                                                   name="opens_cash_drawer"
                                                   value="1"
                                                   @checked($method->opens_cash_drawer)
                                                   @disabled(pos_is_demo())
                                                   onchange="this.form.requestSubmit()">
                                        </label>
                                    </form>
                                </td>
                                <td class="num">
                                    <form method="POST"
                                          action="{{ route('admin.settings.payment-methods.toggle', $method) }}"
                                          data-ajax-form
                                          class="inline-flex justify-end">
                                        @csrf
                                        @method('PATCH')
                                        {{-- Hidden field so the toggle posts `0` when unchecked. --}}
                                        <input type="hidden" name="is_active" value="0">
                                        <label class="field-toggle"
                                               title="{{ __('settings.payment_methods.toggle_aria') }}">
                                            <input type="checkbox"
                                                   name="is_active"
                                                   value="1"
                                                   @checked($method->is_active)
                                                   @disabled(pos_is_demo())
                                                   onchange="this.form.requestSubmit()">
                                        </label>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-admin-layout>
