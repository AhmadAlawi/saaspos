<x-admin-layout
    active="settings"
    :title="__('settings.gateways.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.gateways.title')],
    ]">

    @php
        $stripe       = $rows['stripe'];
        $stripeCreds  = $stripe['creds'];
        $stripeMethod = $stripe['method'];

        $razorpay       = $rows['razorpay'];
        $razorpayCreds  = $razorpay['creds'];
        $razorpayMethod = $razorpay['method'];

        $paystack       = $rows['paystack'];
        $paystackCreds  = $paystack['creds'];
        $paystackMethod = $paystack['method'];

        $flutterwave       = $rows['flutterwave'];
        $flutterwaveCreds  = $flutterwave['creds'];
        $flutterwaveMethod = $flutterwave['method'];

        $mercadoPago       = $rows['mercado_pago'];
        $mercadoPagoCreds  = $mercadoPago['creds'];
        $mercadoPagoMethod = $mercadoPago['method'];
    @endphp

    <div class="page-wide"
         x-data="{
            active: window.location.hash?.replace('#', '') || @js($active),
            pick(code) {
                this.active = code;
                history.replaceState(null, '', '#' + code);
            },
         }"
         x-init="if (!window.location.hash) history.replaceState(null, '', '#' + active)">

        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('settings.gateways.title') }}</h1>
                    <p class="page-sub">{{ __('settings.gateways.sub') }}</p>
                </div>
            </div>
        </div>

        @if (pos_is_demo())
            <div class="alert alert-warning mb-5">
                <span class="alert-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/></svg>
                </span>
                <div class="alert-body"><p class="alert-msg">{{ __('settings.demo.locked_banner') }}</p></div>
            </div>
        @endif

        <div class="gateways-layout">
            {{-- Tabs sidebar — instant client-side switching via Alpine.
                 The URL hash mirrors `active` so a deep link
                 (.../payment-gateways#razorpay) survives a reload and
                 the back/forward buttons work. --}}
            <aside class="gateways-tabs">
                @foreach ($tabs as $tab)
                    @php
                        $method = $rows[$tab['code']]['method'] ?? null;
                        $isOn   = (bool) optional($method)->is_active;
                    @endphp
                    <button type="button"
                            class="gateways-tab {{ ! $tab['wired'] ? 'is-upcoming' : '' }}"
                            :class="active === @js($tab['code']) ? 'is-active' : ''"
                            @click="pick({{ \Illuminate\Support\Js::from($tab['code']) }})">
                        <span class="gateways-tab-label">{{ $tab['label'] }}</span>
                        @if ($tab['wired'])
                            <span class="gateways-tab-dot {{ $isOn ? 'is-on' : 'is-off' }}"></span>
                        @else
                            <span class="gateways-tab-soon">{{ __('settings.gateways.status.soon') }}</span>
                        @endif
                    </button>
                @endforeach
            </aside>

            <section class="gateways-pane">
                {{-- ── Stripe ───────────────────────────────────── --}}
                <div x-show="active === 'stripe'" x-cloak>
                    <form method="POST" action="{{ route('admin.settings.payment-gateways.update') }}" data-ajax-form>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="provider" value="stripe">

                        {{-- `disabled` locks the form on the demo; `contents` preserves layout. --}}
                        <fieldset @disabled(pos_is_demo()) class="contents">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">{{ __('settings.gateways.stripe.title') }}</div>
                                    <div class="card-sub fg-tertiary text-xs mt-1">{{ __('settings.gateways.stripe.sub') }}</div>
                                </div>
                                <div class="gateway-status-pill {{ $stripeMethod->is_active ? 'is-on' : 'is-off' }}">
                                    {{ $stripeMethod->is_active
                                        ? __('settings.gateways.status.enabled')
                                        : __('settings.gateways.status.disabled') }}
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-stack">
                                    <label class="field-toggle">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($stripeMethod->is_active)>
                                        <span>{{ __('settings.gateways.stripe.fields.is_active') }}</span>
                                    </label>
                                    <span class="field-help">{{ __('settings.gateways.stripe.fields.is_active_help') }}</span>

                                    <label class="field">
                                        <span class="field-label is-required">{{ __('settings.gateways.stripe.fields.mode') }}</span>
                                        <select name="mode" class="pos-input" x-data="enhancedSelect()">
                                            <option value="test" @selected(($stripeCreds['mode'] ?? 'test') === 'test')>{{ __('settings.gateways.stripe.fields.mode_test') }}</option>
                                            <option value="live" @selected(($stripeCreds['mode'] ?? 'test') === 'live')>{{ __('settings.gateways.stripe.fields.mode_live') }}</option>
                                        </select>
                                        <span class="field-help">{{ __('settings.gateways.stripe.fields.mode_help') }}</span>
                                    </label>

                                    <label class="field">
                                        <span class="field-label">{{ __('settings.gateways.stripe.fields.publishable_key') }}</span>
                                        <input type="text" name="publishable_key" class="pos-input mono"
                                               value="{{ $stripeCreds['publishable_key'] ?? '' }}"
                                               maxlength="191"
                                               placeholder="pk_test_...">
                                        <span class="field-help">{{ __('settings.gateways.stripe.fields.publishable_key_help') }}</span>
                                    </label>

                                    <label class="field" x-data="{ shown: false }">
                                        <span class="field-label">{{ __('settings.gateways.stripe.fields.secret_key') }}</span>
                                        <div class="secret-input-wrap">
                                            <input :type="shown ? 'text' : 'password'"
                                                   name="secret_key" class="pos-input mono"
                                                   value="{{ $stripeCreds['secret_key'] ?? '' }}"
                                                   maxlength="191"
                                                   placeholder="sk_test_..."
                                                   autocomplete="new-password">
                                            <button type="button"
                                                    class="secret-input-eye"
                                                    @click="shown = !shown"
                                                    :aria-label="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))"
                                                    :title="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))">
                                                <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                                <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                            </button>
                                        </div>
                                        <span class="field-help">{{ __('settings.gateways.stripe.fields.secret_key_help') }}</span>
                                    </label>

                                    <label class="field" x-data="{ shown: false }">
                                        <span class="field-label">{{ __('settings.gateways.stripe.fields.webhook_secret') }}</span>
                                        <div class="secret-input-wrap">
                                            <input :type="shown ? 'text' : 'password'"
                                                   name="webhook_secret" class="pos-input mono"
                                                   value="{{ $stripeCreds['webhook_secret'] ?? '' }}"
                                                   maxlength="191"
                                                   placeholder="whsec_..."
                                                   autocomplete="new-password">
                                            <button type="button"
                                                    class="secret-input-eye"
                                                    @click="shown = !shown"
                                                    :aria-label="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))"
                                                    :title="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))">
                                                <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                                <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                            </button>
                                        </div>
                                        <span class="field-help">
                                            {{ __('settings.gateways.stripe.fields.webhook_secret_help') }}
                                        </span>
                                    </label>

                                    {{-- Webhook setup reference panel —
                                         spells out the URL Stripe needs and
                                         the events to subscribe to. Saves
                                         the admin a round-trip to docs and
                                         prevents the common "I configured
                                         it but it's not firing" mistake of
                                         skipping `checkout.session.completed`. --}}
                                    <div class="gateway-webhook-card">
                                        <div class="gateway-webhook-row">
                                            <span class="gateway-webhook-label">{{ __('settings.gateways.stripe.fields.webhook_url') }}</span>
                                            <code class="mono gateway-webhook-value">{{ url('/webhooks/stripe') }}</code>
                                        </div>
                                        <div class="gateway-webhook-events">
                                            <div class="gateway-webhook-events-title">{{ __('settings.gateways.stripe.fields.webhook_events') }}</div>
                                            <div class="gateway-webhook-event-group">
                                                <span class="gateway-webhook-event-kind is-required">{{ __('settings.gateways.stripe.fields.webhook_events_required_label') }}</span>
                                                <code class="mono">{{ __('settings.gateways.stripe.fields.webhook_events_required') }}</code>
                                            </div>
                                            <div class="gateway-webhook-event-group">
                                                <span class="gateway-webhook-event-kind is-recommended">{{ __('settings.gateways.stripe.fields.webhook_events_recommended_label') }}</span>
                                                <code class="mono">{{ __('settings.gateways.stripe.fields.webhook_events_recommended') }}</code>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer flex justify-end gap-2">
                                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                    <x-icon name="check" class="w-4 h-4" />
                                    {{ __('settings.gateways.actions.save_provider', ['provider' => 'Stripe']) }}
                                </button>
                            </div>
                        </div>
                        </fieldset>
                    </form>
                </div>

                {{-- ── Razorpay ─────────────────────────────────── --}}
                <div x-show="active === 'razorpay'" x-cloak>
                    <form method="POST" action="{{ route('admin.settings.payment-gateways.update') }}" data-ajax-form>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="provider" value="razorpay">

                        <fieldset @disabled(pos_is_demo()) class="contents">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">{{ __('settings.gateways.razorpay.title') }}</div>
                                    <div class="card-sub fg-tertiary text-xs mt-1">{{ __('settings.gateways.razorpay.sub') }}</div>
                                </div>
                                <div class="gateway-status-pill {{ $razorpayMethod->is_active ? 'is-on' : 'is-off' }}">
                                    {{ $razorpayMethod->is_active
                                        ? __('settings.gateways.status.enabled')
                                        : __('settings.gateways.status.disabled') }}
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-stack">
                                    <label class="field-toggle">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($razorpayMethod->is_active)>
                                        <span>{{ __('settings.gateways.razorpay.fields.is_active') }}</span>
                                    </label>
                                    <span class="field-help">{{ __('settings.gateways.razorpay.fields.is_active_help') }}</span>

                                    <label class="field">
                                        <span class="field-label is-required">{{ __('settings.gateways.razorpay.fields.mode') }}</span>
                                        <select name="mode" class="pos-input" x-data="enhancedSelect()">
                                            <option value="test" @selected(($razorpayCreds['mode'] ?? 'test') === 'test')>{{ __('settings.gateways.razorpay.fields.mode_test') }}</option>
                                            <option value="live" @selected(($razorpayCreds['mode'] ?? 'test') === 'live')>{{ __('settings.gateways.razorpay.fields.mode_live') }}</option>
                                        </select>
                                        <span class="field-help">{{ __('settings.gateways.razorpay.fields.mode_help') }}</span>
                                    </label>

                                    <label class="field">
                                        <span class="field-label">{{ __('settings.gateways.razorpay.fields.key_id') }}</span>
                                        <input type="text" name="key_id" class="pos-input mono"
                                               value="{{ $razorpayCreds['key_id'] ?? '' }}"
                                               maxlength="191"
                                               placeholder="rzp_test_...">
                                        <span class="field-help">{{ __('settings.gateways.razorpay.fields.key_id_help') }}</span>
                                    </label>

                                    <label class="field" x-data="{ shown: false }">
                                        <span class="field-label">{{ __('settings.gateways.razorpay.fields.key_secret') }}</span>
                                        <div class="secret-input-wrap">
                                            <input :type="shown ? 'text' : 'password'"
                                                   name="key_secret" class="pos-input mono"
                                                   value="{{ $razorpayCreds['key_secret'] ?? '' }}"
                                                   maxlength="191"
                                                   autocomplete="new-password">
                                            <button type="button"
                                                    class="secret-input-eye"
                                                    @click="shown = !shown"
                                                    :aria-label="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))"
                                                    :title="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))">
                                                <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                                <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                            </button>
                                        </div>
                                        <span class="field-help">{{ __('settings.gateways.razorpay.fields.key_secret_help') }}</span>
                                    </label>

                                    <label class="field" x-data="{ shown: false }">
                                        <span class="field-label">{{ __('settings.gateways.razorpay.fields.webhook_secret') }}</span>
                                        <div class="secret-input-wrap">
                                            <input :type="shown ? 'text' : 'password'"
                                                   name="webhook_secret" class="pos-input mono"
                                                   value="{{ $razorpayCreds['webhook_secret'] ?? '' }}"
                                                   maxlength="191"
                                                   autocomplete="new-password">
                                            <button type="button"
                                                    class="secret-input-eye"
                                                    @click="shown = !shown"
                                                    :aria-label="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))"
                                                    :title="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))">
                                                <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                                <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                            </button>
                                        </div>
                                        <span class="field-help">
                                            {{ __('settings.gateways.razorpay.fields.webhook_secret_help') }}
                                        </span>
                                    </label>

                                    <label class="field-toggle">
                                        <input type="hidden" name="international_enabled" value="0">
                                        <input type="checkbox" name="international_enabled" value="1"
                                               @checked(! empty($razorpayCreds['international_enabled']))>
                                        <span>{{ __('settings.gateways.razorpay.fields.international') }}</span>
                                    </label>
                                    <span class="field-help">{{ __('settings.gateways.razorpay.fields.international_help') }}</span>

                                    <div class="gateway-webhook-card">
                                        <div class="gateway-webhook-row">
                                            <span class="gateway-webhook-label">{{ __('settings.gateways.razorpay.fields.webhook_url') }}</span>
                                            <code class="mono gateway-webhook-value">{{ url('/webhooks/razorpay') }}</code>
                                        </div>
                                        <div class="gateway-webhook-events">
                                            <div class="gateway-webhook-events-title">{{ __('settings.gateways.razorpay.fields.webhook_events') }}</div>
                                            <div class="gateway-webhook-event-group">
                                                <span class="gateway-webhook-event-kind is-required">{{ __('settings.gateways.razorpay.fields.webhook_events_required_label') }}</span>
                                                <code class="mono">{{ __('settings.gateways.razorpay.fields.webhook_events_required') }}</code>
                                            </div>
                                            <div class="gateway-webhook-event-group">
                                                <span class="gateway-webhook-event-kind is-recommended">{{ __('settings.gateways.razorpay.fields.webhook_events_recommended_label') }}</span>
                                                <code class="mono">{{ __('settings.gateways.razorpay.fields.webhook_events_recommended') }}</code>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer flex justify-end gap-2">
                                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                    <x-icon name="check" class="w-4 h-4" />
                                    {{ __('settings.gateways.actions.save_provider', ['provider' => 'Razorpay']) }}
                                </button>
                            </div>
                        </div>
                        </fieldset>
                    </form>
                </div>

                {{-- ── Paystack ─────────────────────────────────── --}}
                <div x-show="active === 'paystack'" x-cloak>
                    <form method="POST" action="{{ route('admin.settings.payment-gateways.update') }}" data-ajax-form>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="provider" value="paystack">

                        <fieldset @disabled(pos_is_demo()) class="contents">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">{{ __('settings.gateways.paystack.title') }}</div>
                                    <div class="card-sub fg-tertiary text-xs mt-1">{{ __('settings.gateways.paystack.sub') }}</div>
                                </div>
                                <div class="gateway-status-pill {{ $paystackMethod->is_active ? 'is-on' : 'is-off' }}">
                                    {{ $paystackMethod->is_active
                                        ? __('settings.gateways.status.enabled')
                                        : __('settings.gateways.status.disabled') }}
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-stack">
                                    <label class="field-toggle">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($paystackMethod->is_active)>
                                        <span>{{ __('settings.gateways.paystack.fields.is_active') }}</span>
                                    </label>
                                    <span class="field-help">{{ __('settings.gateways.paystack.fields.is_active_help') }}</span>

                                    <label class="field">
                                        <span class="field-label is-required">{{ __('settings.gateways.paystack.fields.mode') }}</span>
                                        <select name="mode" class="pos-input" x-data="enhancedSelect()">
                                            <option value="test" @selected(($paystackCreds['mode'] ?? 'test') === 'test')>{{ __('settings.gateways.paystack.fields.mode_test') }}</option>
                                            <option value="live" @selected(($paystackCreds['mode'] ?? 'test') === 'live')>{{ __('settings.gateways.paystack.fields.mode_live') }}</option>
                                        </select>
                                        <span class="field-help">{{ __('settings.gateways.paystack.fields.mode_help') }}</span>
                                    </label>

                                    <label class="field">
                                        <span class="field-label">{{ __('settings.gateways.paystack.fields.public_key') }}</span>
                                        <input type="text" name="public_key" class="pos-input mono"
                                               value="{{ $paystackCreds['public_key'] ?? '' }}"
                                               maxlength="191"
                                               placeholder="pk_test_...">
                                        <span class="field-help">{{ __('settings.gateways.paystack.fields.public_key_help') }}</span>
                                    </label>

                                    <label class="field" x-data="{ shown: false }">
                                        <span class="field-label">{{ __('settings.gateways.paystack.fields.secret_key') }}</span>
                                        <div class="secret-input-wrap">
                                            <input :type="shown ? 'text' : 'password'"
                                                   name="secret_key" class="pos-input mono"
                                                   value="{{ $paystackCreds['secret_key'] ?? '' }}"
                                                   maxlength="191"
                                                   placeholder="sk_test_..."
                                                   autocomplete="new-password">
                                            <button type="button"
                                                    class="secret-input-eye"
                                                    @click="shown = !shown"
                                                    :aria-label="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))"
                                                    :title="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))">
                                                <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                                <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                            </button>
                                        </div>
                                        <span class="field-help">{{ __('settings.gateways.paystack.fields.secret_key_help') }}</span>
                                    </label>

                                    <div class="gateway-webhook-card">
                                        <div class="gateway-webhook-row">
                                            <span class="gateway-webhook-label">{{ __('settings.gateways.paystack.fields.webhook_url') }}</span>
                                            <code class="mono gateway-webhook-value">{{ url('/webhooks/paystack') }}</code>
                                        </div>
                                        <p class="gateway-webhook-help">{{ __('settings.gateways.paystack.fields.webhook_help') }}</p>
                                        <div class="gateway-webhook-events">
                                            <div class="gateway-webhook-events-title">{{ __('settings.gateways.paystack.fields.webhook_events') }}</div>
                                            <div class="gateway-webhook-event-group">
                                                <span class="gateway-webhook-event-kind is-required">{{ __('settings.gateways.paystack.fields.webhook_events_required_label') }}</span>
                                                <code class="mono">{{ __('settings.gateways.paystack.fields.webhook_events_required') }}</code>
                                            </div>
                                            <div class="gateway-webhook-event-group">
                                                <span class="gateway-webhook-event-kind is-recommended">{{ __('settings.gateways.paystack.fields.webhook_events_recommended_label') }}</span>
                                                <code class="mono">{{ __('settings.gateways.paystack.fields.webhook_events_recommended') }}</code>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer flex justify-end gap-2">
                                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                    <x-icon name="check" class="w-4 h-4" />
                                    {{ __('settings.gateways.actions.save_provider', ['provider' => 'Paystack']) }}
                                </button>
                            </div>
                        </div>
                        </fieldset>
                    </form>
                </div>

                {{-- ── Flutterwave ──────────────────────────────── --}}
                <div x-show="active === 'flutterwave'" x-cloak>
                    <form method="POST" action="{{ route('admin.settings.payment-gateways.update') }}" data-ajax-form>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="provider" value="flutterwave">

                        <fieldset @disabled(pos_is_demo()) class="contents">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">{{ __('settings.gateways.flutterwave.title') }}</div>
                                    <div class="card-sub fg-tertiary text-xs mt-1">{{ __('settings.gateways.flutterwave.sub') }}</div>
                                </div>
                                <div class="gateway-status-pill {{ $flutterwaveMethod->is_active ? 'is-on' : 'is-off' }}">
                                    {{ $flutterwaveMethod->is_active
                                        ? __('settings.gateways.status.enabled')
                                        : __('settings.gateways.status.disabled') }}
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-stack">
                                    <label class="field-toggle">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($flutterwaveMethod->is_active)>
                                        <span>{{ __('settings.gateways.flutterwave.fields.is_active') }}</span>
                                    </label>
                                    <span class="field-help">{{ __('settings.gateways.flutterwave.fields.is_active_help') }}</span>

                                    <label class="field">
                                        <span class="field-label is-required">{{ __('settings.gateways.flutterwave.fields.mode') }}</span>
                                        <select name="mode" class="pos-input" x-data="enhancedSelect()">
                                            <option value="test" @selected(($flutterwaveCreds['mode'] ?? 'test') === 'test')>{{ __('settings.gateways.flutterwave.fields.mode_test') }}</option>
                                            <option value="live" @selected(($flutterwaveCreds['mode'] ?? 'test') === 'live')>{{ __('settings.gateways.flutterwave.fields.mode_live') }}</option>
                                        </select>
                                        <span class="field-help">{{ __('settings.gateways.flutterwave.fields.mode_help') }}</span>
                                    </label>

                                    <label class="field">
                                        <span class="field-label">{{ __('settings.gateways.flutterwave.fields.public_key') }}</span>
                                        <input type="text" name="public_key" class="pos-input mono"
                                               value="{{ $flutterwaveCreds['public_key'] ?? '' }}"
                                               maxlength="191"
                                               placeholder="FLWPUBK_TEST-...">
                                        <span class="field-help">{{ __('settings.gateways.flutterwave.fields.public_key_help') }}</span>
                                    </label>

                                    <label class="field" x-data="{ shown: false }">
                                        <span class="field-label">{{ __('settings.gateways.flutterwave.fields.secret_key') }}</span>
                                        <div class="secret-input-wrap">
                                            <input :type="shown ? 'text' : 'password'"
                                                   name="secret_key" class="pos-input mono"
                                                   value="{{ $flutterwaveCreds['secret_key'] ?? '' }}"
                                                   maxlength="191"
                                                   placeholder="FLWSECK_TEST-..."
                                                   autocomplete="new-password">
                                            <button type="button"
                                                    class="secret-input-eye"
                                                    @click="shown = !shown"
                                                    :aria-label="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))"
                                                    :title="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))">
                                                <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                                <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                            </button>
                                        </div>
                                        <span class="field-help">{{ __('settings.gateways.flutterwave.fields.secret_key_help') }}</span>
                                    </label>

                                    <label class="field" x-data="{ shown: false }">
                                        <span class="field-label">{{ __('settings.gateways.flutterwave.fields.webhook_secret') }}</span>
                                        <div class="secret-input-wrap">
                                            <input :type="shown ? 'text' : 'password'"
                                                   name="webhook_secret" class="pos-input mono"
                                                   value="{{ $flutterwaveCreds['webhook_secret'] ?? '' }}"
                                                   maxlength="191"
                                                   autocomplete="new-password">
                                            <button type="button"
                                                    class="secret-input-eye"
                                                    @click="shown = !shown"
                                                    :aria-label="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))"
                                                    :title="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))">
                                                <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                                <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                            </button>
                                        </div>
                                        <span class="field-help">
                                            {{ __('settings.gateways.flutterwave.fields.webhook_secret_help') }}
                                        </span>
                                    </label>

                                    <div class="gateway-webhook-card">
                                        <div class="gateway-webhook-row">
                                            <span class="gateway-webhook-label">{{ __('settings.gateways.flutterwave.fields.webhook_url') }}</span>
                                            <code class="mono gateway-webhook-value">{{ url('/webhooks/flutterwave') }}</code>
                                        </div>
                                        <p class="gateway-webhook-help">{{ __('settings.gateways.flutterwave.fields.webhook_help') }}</p>
                                        <div class="gateway-webhook-events">
                                            <div class="gateway-webhook-events-title">{{ __('settings.gateways.flutterwave.fields.webhook_events') }}</div>
                                            <div class="gateway-webhook-event-group">
                                                <span class="gateway-webhook-event-kind is-required">{{ __('settings.gateways.flutterwave.fields.webhook_events_required_label') }}</span>
                                                <code class="mono">{{ __('settings.gateways.flutterwave.fields.webhook_events_required') }}</code>
                                            </div>
                                            <div class="gateway-webhook-event-group">
                                                <span class="gateway-webhook-event-kind is-recommended">{{ __('settings.gateways.flutterwave.fields.webhook_events_recommended_label') }}</span>
                                                <code class="mono">{{ __('settings.gateways.flutterwave.fields.webhook_events_recommended') }}</code>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer flex justify-end gap-2">
                                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                    <x-icon name="check" class="w-4 h-4" />
                                    {{ __('settings.gateways.actions.save_provider', ['provider' => 'Flutterwave']) }}
                                </button>
                            </div>
                        </div>
                        </fieldset>
                    </form>
                </div>

                {{-- ── Mercado Pago ─────────────────────────────── --}}
                <div x-show="active === 'mercado_pago'" x-cloak>
                    <form method="POST" action="{{ route('admin.settings.payment-gateways.update') }}" data-ajax-form>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="provider" value="mercado_pago">

                        <fieldset @disabled(pos_is_demo()) class="contents">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">{{ __('settings.gateways.mercado_pago.title') }}</div>
                                    <div class="card-sub fg-tertiary text-xs mt-1">{{ __('settings.gateways.mercado_pago.sub') }}</div>
                                </div>
                                <div class="gateway-status-pill {{ $mercadoPagoMethod->is_active ? 'is-on' : 'is-off' }}">
                                    {{ $mercadoPagoMethod->is_active
                                        ? __('settings.gateways.status.enabled')
                                        : __('settings.gateways.status.disabled') }}
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-stack">
                                    <label class="field-toggle">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($mercadoPagoMethod->is_active)>
                                        <span>{{ __('settings.gateways.mercado_pago.fields.is_active') }}</span>
                                    </label>
                                    <span class="field-help">{{ __('settings.gateways.mercado_pago.fields.is_active_help') }}</span>

                                    <label class="field">
                                        <span class="field-label is-required">{{ __('settings.gateways.mercado_pago.fields.mode') }}</span>
                                        <select name="mode" class="pos-input" x-data="enhancedSelect()">
                                            <option value="test" @selected(($mercadoPagoCreds['mode'] ?? 'test') === 'test')>{{ __('settings.gateways.mercado_pago.fields.mode_test') }}</option>
                                            <option value="live" @selected(($mercadoPagoCreds['mode'] ?? 'test') === 'live')>{{ __('settings.gateways.mercado_pago.fields.mode_live') }}</option>
                                        </select>
                                        <span class="field-help">{{ __('settings.gateways.mercado_pago.fields.mode_help') }}</span>
                                    </label>

                                    <label class="field">
                                        <span class="field-label">{{ __('settings.gateways.mercado_pago.fields.public_key') }}</span>
                                        <input type="text" name="public_key" class="pos-input mono"
                                               value="{{ $mercadoPagoCreds['public_key'] ?? '' }}"
                                               maxlength="191"
                                               placeholder="TEST-... / APP_USR-...">
                                        <span class="field-help">{{ __('settings.gateways.mercado_pago.fields.public_key_help') }}</span>
                                    </label>

                                    <label class="field" x-data="{ shown: false }">
                                        <span class="field-label">{{ __('settings.gateways.mercado_pago.fields.access_token') }}</span>
                                        <div class="secret-input-wrap">
                                            <input :type="shown ? 'text' : 'password'"
                                                   name="access_token" class="pos-input mono"
                                                   value="{{ $mercadoPagoCreds['access_token'] ?? '' }}"
                                                   maxlength="191"
                                                   placeholder="TEST-... / APP_USR-..."
                                                   autocomplete="new-password">
                                            <button type="button"
                                                    class="secret-input-eye"
                                                    @click="shown = !shown"
                                                    :aria-label="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))"
                                                    :title="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))">
                                                <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                                <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                            </button>
                                        </div>
                                        <span class="field-help">{{ __('settings.gateways.mercado_pago.fields.access_token_help') }}</span>
                                    </label>

                                    <label class="field" x-data="{ shown: false }">
                                        <span class="field-label">{{ __('settings.gateways.mercado_pago.fields.webhook_secret') }}</span>
                                        <div class="secret-input-wrap">
                                            <input :type="shown ? 'text' : 'password'"
                                                   name="webhook_secret" class="pos-input mono"
                                                   value="{{ $mercadoPagoCreds['webhook_secret'] ?? '' }}"
                                                   maxlength="191"
                                                   autocomplete="new-password">
                                            <button type="button"
                                                    class="secret-input-eye"
                                                    @click="shown = !shown"
                                                    :aria-label="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))"
                                                    :title="shown ? @js(__('settings.gateways.hide_secret')) : @js(__('settings.gateways.show_secret'))">
                                                <x-icon name="eye" class="w-4 h-4" x-show="!shown" />
                                                <x-icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                                            </button>
                                        </div>
                                        <span class="field-help">
                                            {{ __('settings.gateways.mercado_pago.fields.webhook_secret_help') }}
                                        </span>
                                    </label>

                                    <div class="gateway-webhook-card">
                                        <div class="gateway-webhook-row">
                                            <span class="gateway-webhook-label">{{ __('settings.gateways.mercado_pago.fields.webhook_url') }}</span>
                                            <code class="mono gateway-webhook-value">{{ url('/webhooks/mercado_pago') }}</code>
                                        </div>
                                        <p class="gateway-webhook-help">{{ __('settings.gateways.mercado_pago.fields.webhook_help') }}</p>
                                        <div class="gateway-webhook-events">
                                            <div class="gateway-webhook-events-title">{{ __('settings.gateways.mercado_pago.fields.webhook_events') }}</div>
                                            <div class="gateway-webhook-event-group">
                                                <span class="gateway-webhook-event-kind is-required">{{ __('settings.gateways.mercado_pago.fields.webhook_events_required_label') }}</span>
                                                <code class="mono">{{ __('settings.gateways.mercado_pago.fields.webhook_events_required') }}</code>
                                            </div>
                                            <div class="gateway-webhook-event-group">
                                                <span class="gateway-webhook-event-kind is-recommended">{{ __('settings.gateways.mercado_pago.fields.webhook_events_recommended_label') }}</span>
                                                <code class="mono">{{ __('settings.gateways.mercado_pago.fields.webhook_events_recommended') }}</code>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer flex justify-end gap-2">
                                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                    <x-icon name="check" class="w-4 h-4" />
                                    {{ __('settings.gateways.actions.save_provider', ['provider' => 'Mercado Pago']) }}
                                </button>
                            </div>
                        </div>
                        </fieldset>
                    </form>
                </div>

                {{-- ── Upcoming providers (single shared template) ── --}}
                @foreach ($tabs as $tab)
                    @if (! $tab['wired'])
                        <div x-show="active === @js($tab['code'])" x-cloak>
                            <div class="card gateway-card-upcoming">
                                <div class="card-body gateways-pane-empty">
                                    <div class="gateways-pane-empty-icon">
                                        <x-icon name="card" class="w-10 h-10" />
                                    </div>
                                    <div class="gateways-pane-empty-title">{{ $tab['label'] }}</div>
                                    <div class="gateways-pane-empty-sub">{{ __('settings.gateways.upcoming') }}</div>
                                    <span class="gateway-status-pill is-soon mt-3">{{ __('settings.gateways.status.soon') }}</span>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </section>
        </div>
    </div>
</x-admin-layout>
