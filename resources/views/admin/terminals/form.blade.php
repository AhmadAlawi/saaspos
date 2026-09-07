@php $isEdit = $terminal !== null; @endphp

<x-admin-layout
    active="terminals"
    :title="$isEdit ? __('terminals.drawer.edit_title') : __('terminals.drawer.new_title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('terminals.crumb_parent')],
        ['label' => __('terminals.title'), 'href' => route('admin.terminals.index')],
        ['label' => $isEdit ? $terminal->name : __('terminals.actions.new')],
    ]">

    <div class="page-wide"
         x-data="terminalForm({
             mediaUploadUrl:  '{{ route('admin.terminals.cfd-media') }}',
             kioskUrl:        '{{ route('kiosk.index') }}',
             kioskCategories: {{ Js::from($kioskCategories->map(fn ($c) => ['id' => (string) $c->id, 'name' => $c->name])->values()) }},
             kioskPaymentMethods: {{ Js::from($kioskPaymentMethods->map(fn ($m) => ['id' => (string) $m->id, 'name' => $m->name])->values()) }},
             initial:         {{ Js::from($initial) }},
         })">

        <form method="POST"
              action="{{ $isEdit ? route('admin.terminals.update', $terminal) : route('admin.terminals.store') }}"
              @submit="submitting = true">
            @csrf
            @if ($isEdit)
                @method('PATCH')
            @endif

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.terminals.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('terminals.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">
                            {{ $isEdit ? $terminal->name : __('terminals.drawer.new_title') }}
                            @if ($store)
                                <span class="prod-badge prod-badge-muted ms-2">{{ $store->name }}</span>
                            @endif
                        </h1>
                        <p class="page-sub">{{ $isEdit ? __('terminals.drawer.edit_updated') . ' ' . $terminal->updated_at?->diffForHumans() : __('terminals.drawer.new_sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if ($isEdit)
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-danger"
                                @click="$store.confirm.show({
                                    title: @js(__('terminals.actions.delete')) + ' “{{ $terminal->name }}”?',
                                    message: @js(__('terminals.drawer.delete_confirm')),
                                    intent: 'danger',
                                    confirmLabel: @js(__('terminals.actions.delete')),
                                    onConfirm: () => $refs.deleteForm.submit(),
                                })">
                            <x-icon name="trash" class="w-4 h-4" />
                            {{ __('terminals.actions.delete') }}
                        </button>
                    @endif
                    <a href="{{ route('admin.terminals.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                        {{ __('terminals.actions.discard') }}
                    </a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary" :disabled="submitting">
                        <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <template x-if="!submitting"><x-icon name="check" class="w-4 h-4" /></template>
                        {{ $isEdit ? __('terminals.actions.save') : __('terminals.actions.create') }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

                {{-- ── Basics ─────────────────────────────────────── --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('terminals.drawer.basics') }}</div>
                        <div class="card-title-sub">{{ __('terminals.drawer.basics_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <div class="field">
                                <span class="field-label">{{ __('terminals.drawer.store') }}</span>
                                <div class="term-editor-store">
                                    <span class="prod-badge prod-badge-muted">{{ $store?->name ?? __('terminals.drawer.store_none') }}</span>
                                </div>
                                <span class="field-help">{{ __('terminals.drawer.store_help') }}</span>
                            </div>

                            <label class="field @error('name') has-error @enderror">
                                <span class="field-label is-required">{{ __('terminals.drawer.name') }}</span>
                                <input type="text" name="name" x-model="form.name" class="pos-input"
                                       placeholder="{{ __('terminals.drawer.name_placeholder') }}">
                            </label>

                            <label class="field @error('code') has-error @enderror">
                                <span class="field-label">{{ __('terminals.drawer.code') }}</span>
                                <input type="text" name="code" x-model="form.code" class="pos-input mono"
                                       maxlength="32" placeholder="{{ __('terminals.drawer.code_placeholder') }}">
                                <span class="field-help">{{ __('terminals.drawer.code_help') }}</span>
                            </label>

                            <label class="field-toggle">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                <span>{{ __('terminals.drawer.is_active') }}</span>
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('terminals.drawer.receipt_template') }}</span>
                                <select x-data="enhancedSelect({ value: form.receipt_template_id })" x-model="form.receipt_template_id" name="receipt_template_id" class="pos-input">
                                    <option value="">{{ __('terminals.drawer.receipt_template_default') }}</option>
                                    @foreach (($receiptTemplates ?? []) as $tpl)
                                        <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                                    @endforeach
                                </select>
                                <span class="field-help">{{ __('terminals.drawer.receipt_template_help') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- ── Hardware ───────────────────────────────────── --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('terminals.hw.title') }}</div>
                        <div class="card-title-sub">{{ __('terminals.hw.sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field">
                                <span class="field-label">{{ __('terminals.hw.mode') }}</span>
                                <select x-data="enhancedSelect({ value: form.printer_mode })" x-model="form.printer_mode" name="printer_mode" class="pos-input">
                                    <option value="browser_print">{{ __('terminals.hw.mode_browser') }}</option>
                                    <option value="webusb">{{ __('terminals.hw.mode_webusb') }}</option>
                                    <option value="network">{{ __('terminals.hw.mode_network') }}</option>
                                    <option value="none">{{ __('terminals.hw.mode_none') }}</option>
                                </select>
                                <span class="field-help">{{ __('terminals.hw.mode_help') }}</span>
                            </label>

                            <div class="field" x-show="form.printer_mode === 'webusb'" x-cloak>
                                <span class="field-label">{{ __('terminals.hw.pair') }}</span>
                                <div class="term-hw-pair">
                                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="pairPrinter()">
                                        <x-icon name="printer" class="w-4 h-4" />
                                        {{ __('terminals.hw.pair_btn') }}
                                    </button>
                                    <span class="term-hw-paired mono" x-text="form.webusb_label || @js(__('terminals.hw.not_paired'))"></span>
                                </div>
                                <input type="hidden" name="webusb_vendor_id"  :value="form.webusb_vendor_id || ''">
                                <input type="hidden" name="webusb_product_id" :value="form.webusb_product_id || ''">
                                <span class="field-help">{{ __('terminals.hw.pair_help') }}</span>
                            </div>

                            <label class="field" x-show="form.printer_mode !== 'none'" x-cloak>
                                <span class="field-label">{{ __('terminals.hw.paper') }}</span>
                                <select x-data="enhancedSelect({ value: form.paper_width })" x-model="form.paper_width" name="paper_width" class="pos-input">
                                    <option value="58mm">58 mm</option>
                                    <option value="80mm">80 mm</option>
                                    <option value="a4">A4</option>
                                </select>
                            </label>

                            <label class="field-toggle" x-show="form.printer_mode === 'webusb' || form.printer_mode === 'network'" x-cloak>
                                <input type="hidden" name="cut_paper" value="0">
                                <input type="checkbox" name="cut_paper" value="1" x-model="form.cut_paper">
                                <span>{{ __('terminals.hw.cut') }}</span>
                            </label>

                            <label class="field-toggle">
                                <input type="hidden" name="open_drawer_on_cash" value="0">
                                <input type="checkbox" name="open_drawer_on_cash" value="1" x-model="form.open_drawer_on_cash">
                                <span>{{ __('terminals.hw.drawer') }}</span>
                            </label>

                            <label class="field" x-show="form.open_drawer_on_cash" x-cloak>
                                <span class="field-label">{{ __('terminals.hw.drawer_pin') }}</span>
                                <select x-data="enhancedSelect({ value: form.drawer_pin })" x-model="form.drawer_pin" name="drawer_pin" class="pos-input">
                                    <option value="2">{{ __('terminals.hw.drawer_pin_2') }}</option>
                                    <option value="5">{{ __('terminals.hw.drawer_pin_5') }}</option>
                                </select>
                                <span class="field-help">{{ __('terminals.hw.drawer_pin_help') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- ── Customer display (CFD) ─────────────────────── --}}
                <div class="card lg:col-span-2">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('terminals.cfd.title') }}</div>
                        <div class="card-title-sub">{{ __('terminals.cfd.sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field-toggle">
                                <input type="hidden" name="cfd_enabled" value="0">
                                <input type="checkbox" name="cfd_enabled" value="1" x-model="form.cfd_enabled">
                                <span>{{ __('terminals.cfd.enabled') }}</span>
                            </label>
                            <span class="field-help">{{ __('terminals.cfd.enabled_help') }}</span>

                            <template x-if="form.cfd_enabled">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-3.5">
                                    <label class="field">
                                        <span class="field-label">{{ __('terminals.cfd.transport') }}</span>
                                        <select x-data="enhancedSelect({ value: form.cfd_transport })" x-model="form.cfd_transport" name="cfd_transport" class="pos-input">
                                            <option value="same_machine">{{ __('terminals.cfd.transport_same') }}</option>
                                            <option value="separate_device">{{ __('terminals.cfd.transport_separate') }}</option>
                                        </select>
                                        <span class="field-help" x-text="form.cfd_transport === 'separate_device'
                                            ? @js(__('terminals.cfd.transport_separate_help'))
                                            : @js(__('terminals.cfd.transport_same_help'))"></span>
                                    </label>

                                    <label class="field">
                                        <span class="field-label">{{ __('terminals.cfd.welcome') }}</span>
                                        <input type="text" name="cfd_welcome" x-model="form.cfd_welcome"
                                               class="pos-input" maxlength="120" placeholder="{{ __('terminals.cfd.welcome_placeholder') }}">
                                        <span class="field-help">{{ __('terminals.cfd.welcome_help') }}</span>
                                    </label>

                                    <label class="field">
                                        <span class="field-label">{{ __('terminals.cfd.thankyou') }}</span>
                                        <input type="text" name="cfd_thankyou" x-model="form.cfd_thankyou"
                                               class="pos-input" maxlength="120" placeholder="{{ __('terminals.cfd.thankyou_placeholder') }}">
                                    </label>

                                    <div class="field sm:col-span-2">
                                        <label class="field-toggle">
                                            <input type="hidden" name="cfd_show_upi_qr" value="0">
                                            <input type="checkbox" name="cfd_show_upi_qr" value="1" x-model="form.cfd_show_upi_qr">
                                            <span>{{ __('terminals.cfd.show_upi_qr') }}</span>
                                        </label>
                                        <span class="field-help">{{ __('terminals.cfd.show_upi_qr_help') }}</span>
                                    </div>

                                    <label class="field" x-show="form.attract_media.length > 0" x-cloak>
                                        <span class="field-label">{{ __('terminals.cfd.attract_seconds') }}</span>
                                        <input type="number" name="attract_seconds" x-model.number="form.attract_seconds"
                                               class="pos-input" min="3" max="30" step="1">
                                        <span class="field-help">{{ __('terminals.cfd.attract_seconds_help') }}</span>
                                    </label>

                                    <div class="field sm:col-span-2">
                                        <span class="field-label">{{ __('terminals.cfd.attract') }}</span>
                                        <div class="term-cfd-media">
                                            <template x-for="(m, i) in form.attract_media" :key="m.path">
                                                <div class="term-cfd-thumb">
                                                    <img :src="m.url" alt="">
                                                    <input type="hidden" name="cfd_media[]" :value="m.path">
                                                    <button type="button" class="term-cfd-thumb-x" @click="removeCfdMedia(i)"
                                                            :aria-label="@js(__('terminals.cfd.attract_remove'))">
                                                        <x-icon name="x" class="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            </template>
                                            <button type="button" class="term-cfd-add"
                                                    x-show="form.attract_media.length < 12"
                                                    :disabled="uploadingMedia" @click="pickCfdMedia()">
                                                <template x-if="!uploadingMedia"><x-icon name="plus" class="w-5 h-5" /></template>
                                                <svg x-show="uploadingMedia" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                                </svg>
                                            </button>
                                            <input type="file" x-ref="cfdMediaInput" class="hidden"
                                                   accept="image/jpeg,image/png,image/webp" multiple @change="onCfdMediaPick($event)">
                                        </div>
                                        <span class="field-help">{{ __('terminals.cfd.attract_help') }}</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- ── Self-ordering kiosk ────────────────────────── --}}
                <div class="card lg:col-span-2">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('terminals.kiosk.title') }}</div>
                        <div class="card-title-sub">{{ __('terminals.kiosk.sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field-toggle">
                                <input type="hidden" name="station_type" value="register">
                                <input type="checkbox" name="station_type" value="kiosk"
                                       :checked="form.station_type === 'kiosk'"
                                       @change="form.station_type = $event.target.checked ? 'kiosk' : 'register'">
                                <span>{{ __('terminals.kiosk.is_kiosk') }}</span>
                            </label>
                            <span class="field-help">{{ __('terminals.kiosk.is_kiosk_help') }}</span>

                            <template x-if="form.station_type === 'kiosk'">
                                <div class="form-stack">
                                    <label class="field-toggle">
                                        <input type="hidden" name="kiosk_enabled" value="0">
                                        <input type="checkbox" name="kiosk_enabled" value="1" x-model="form.kiosk_enabled">
                                        <span>{{ __('terminals.kiosk.enabled') }}</span>
                                    </label>
                                    <span class="field-help">{{ __('terminals.kiosk.enabled_help') }}</span>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-3.5">
                                        <label class="field">
                                            <span class="field-label">{{ __('terminals.kiosk.mode') }}</span>
                                            <select x-data="enhancedSelect({ value: form.kiosk_mode })" x-model="form.kiosk_mode" name="kiosk_mode" class="pos-input">
                                                <option value="checkout">{{ __('terminals.kiosk.mode_checkout') }}</option>
                                                <option value="order">{{ __('terminals.kiosk.mode_order') }}</option>
                                                <option value="both">{{ __('terminals.kiosk.mode_both') }}</option>
                                            </select>
                                            <span class="field-help" x-text="({
                                                checkout: {{ Js::from(__('terminals.kiosk.mode_checkout_help')) }},
                                                order: {{ Js::from(__('terminals.kiosk.mode_order_help')) }},
                                                both: {{ Js::from(__('terminals.kiosk.mode_both_help')) }},
                                            })[form.kiosk_mode] || ''"></span>
                                        </label>

                                        <label class="field">
                                            <span class="field-label">{{ __('terminals.kiosk.customer') }}</span>
                                            <select x-data="enhancedSelect({ value: form.kiosk_customer })" x-model="form.kiosk_customer" name="kiosk_customer" class="pos-input">
                                                <option value="off">{{ __('terminals.kiosk.customer_off') }}</option>
                                                <option value="optional">{{ __('terminals.kiosk.customer_optional') }}</option>
                                                <option value="required">{{ __('terminals.kiosk.customer_required') }}</option>
                                            </select>
                                            <span class="field-help">{{ __('terminals.kiosk.customer_help') }}</span>
                                        </label>

                                        <label class="field">
                                            <span class="field-label">{{ __('terminals.kiosk.welcome') }}</span>
                                            <input type="text" name="kiosk_welcome" x-model="form.kiosk_welcome"
                                                   class="pos-input" maxlength="120" placeholder="{{ __('terminals.kiosk.welcome_placeholder') }}">
                                        </label>

                                        <label class="field">
                                            <span class="field-label">{{ __('terminals.kiosk.thankyou') }}</span>
                                            <input type="text" name="kiosk_thankyou" x-model="form.kiosk_thankyou"
                                                   class="pos-input" maxlength="120" placeholder="{{ __('terminals.kiosk.thankyou_placeholder') }}">
                                        </label>

                                        <label class="field">
                                            <span class="field-label">{{ __('terminals.kiosk.idle') }}</span>
                                            <input type="number" name="kiosk_idle_timeout" x-model.number="form.kiosk_idle_timeout"
                                                   class="pos-input" min="20" max="300" step="5">
                                            <span class="field-help">{{ __('terminals.kiosk.idle_help') }}</span>
                                        </label>

                                        <label class="field">
                                            <span class="field-label">{{ __('terminals.kiosk.pickup_prefix') }}</span>
                                            <input type="text" name="kiosk_pickup_prefix" x-model="form.kiosk_pickup_prefix"
                                                   class="pos-input mono" maxlength="4" placeholder="K"
                                                   @input="form.kiosk_pickup_prefix = form.kiosk_pickup_prefix.toUpperCase().replace(/[^A-Z0-9]/g, '')">
                                            <span class="field-help">
                                                {{ __('terminals.kiosk.pickup_prefix_help', ['example' => '']) }}
                                                <span class="mono" x-text="(form.kiosk_pickup_prefix || 'K') + '001'"></span>
                                            </span>
                                        </label>

                                        <label class="field">
                                            <span class="field-label">{{ __('terminals.kiosk.thankyou_seconds') }}</span>
                                            <input type="number" name="kiosk_thankyou_seconds" x-model.number="form.kiosk_thankyou_seconds"
                                                   class="pos-input" min="5" max="120" step="5">
                                            <span class="field-help">{{ __('terminals.kiosk.thankyou_seconds_help') }}</span>
                                        </label>

                                        <label class="field" x-show="form.kiosk_attract_media.length > 0" x-cloak>
                                            <span class="field-label">{{ __('terminals.kiosk.attract_seconds') }}</span>
                                            <input type="number" name="kiosk_attract_seconds" x-model.number="form.kiosk_attract_seconds"
                                                   class="pos-input" min="3" max="30" step="1">
                                        </label>
                                    </div>

                                    <label class="field-toggle">
                                        <input type="hidden" name="kiosk_allow_note" value="0">
                                        <input type="checkbox" name="kiosk_allow_note" value="1" x-model="form.kiosk_allow_note">
                                        <span>{{ __('terminals.kiosk.allow_note') }}</span>
                                    </label>

                                    <div class="field">
                                        <span class="field-label">{{ __('terminals.kiosk.categories') }}</span>
                                        <span class="field-help">{{ __('terminals.kiosk.categories_help') }}</span>
                                        <div class="term-kiosk-cats" x-show="kioskCategories.length">
                                            <template x-for="cat in kioskCategories" :key="cat.id">
                                                <label class="term-kiosk-cat">
                                                    <input type="checkbox" class="pos-check" name="kiosk_categories[]" :value="cat.id" x-model="form.kiosk_categories">
                                                    <span x-text="cat.name"></span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Which gateways "Pay now" may charge through. Only
                                         meaningful when the kiosk actually takes payment,
                                         so it hides in pay-at-counter (`order`) mode. --}}
                                    <div class="field" x-show="form.kiosk_mode !== 'order'" x-cloak>
                                        <span class="field-label">{{ __('terminals.kiosk.payment_methods') }}</span>
                                        <span class="field-help">{{ __('terminals.kiosk.payment_methods_help') }}</span>

                                        <template x-if="kioskPaymentMethods.length">
                                            <div class="term-kiosk-cats">
                                                <template x-for="pm in kioskPaymentMethods" :key="pm.id">
                                                    <label class="term-kiosk-cat">
                                                        <input type="checkbox" class="pos-check" name="kiosk_payment_methods[]"
                                                               :value="pm.id" x-model="form.kiosk_payment_methods">
                                                        <span x-text="pm.name"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </template>

                                        <template x-if="!kioskPaymentMethods.length">
                                            <x-alert type="warning">{{ __('terminals.kiosk.payment_methods_none') }}</x-alert>
                                        </template>

                                        <span class="field-help" x-show="kioskPaymentMethods.length && kioskPaysAnyGateway" x-cloak>
                                            {{ __('terminals.kiosk.payment_methods_all') }}
                                        </span>
                                        <span class="field-help" x-show="kioskSkipsChooser" x-cloak>
                                            {{ __('terminals.kiosk.payment_methods_single') }}
                                        </span>
                                    </div>

                                    {{-- Static UPI QR. Not a gateway: nothing calls back to
                                         say the transfer landed, so paying this way places
                                         the order for staff to verify rather than completing
                                         a sale. See docs/features/kiosk-self-ordering.md §5.

                                         Always rendered (in pay-at-the-machine modes) even when
                                         no method qualifies — hiding it entirely left the admin
                                         with no way to discover the feature, or to learn WHY it
                                         wasn't on offer. --}}
                                    <div class="field" x-show="form.kiosk_mode !== 'order'" x-cloak>
                                        <span class="field-label">{{ __('terminals.kiosk.upi_method') }}</span>

                                        {{-- Options are rendered by Blade, NOT by `x-for`.
                                             `enhancedSelect` (TomSelect) snapshots the select's
                                             options when it initialises, which happens before
                                             Alpine's template has inserted any — so an x-for
                                             option list shows up as an empty dropdown. --}}
                                        @if ($kioskUpiMethods->isNotEmpty())
                                            <div>
                                                <select x-data="enhancedSelect({ value: form.kiosk_upi_method })"
                                                        x-model="form.kiosk_upi_method" name="kiosk_upi_method" class="pos-input">
                                                    <option value="">{{ __('terminals.kiosk.upi_method_none') }}</option>
                                                    @foreach ($kioskUpiMethods as $m)
                                                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                                                    @endforeach
                                                </select>
                                                <span class="field-help">{{ __('terminals.kiosk.upi_method_help') }}</span>
                                            </div>

                                        {{-- No non-gateway method carries a `vpa`, so there is
                                             nothing to encode into a `upi://pay?…` link. Say so,
                                             and point at the page that fixes it. --}}
                                        @else
                                            <div>
                                                <x-alert type="info">
                                                    {{ __('terminals.kiosk.upi_method_none_configured') }}
                                                    <a href="{{ route('admin.settings.payment-methods.edit') }}"
                                                       class="link" target="_blank" rel="noopener">
                                                        {{ __('terminals.kiosk.upi_method_configure') }}
                                                    </a>
                                                </x-alert>
                                                <span class="field-help">{{ __('terminals.kiosk.upi_method_help') }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    <label class="field-toggle">
                                        <input type="hidden" name="kiosk_sound" value="0">
                                        <input type="checkbox" name="kiosk_sound" value="1" x-model="form.kiosk_sound">
                                        <span>{{ __('terminals.kiosk.sound') }}</span>
                                    </label>
                                    <span class="field-help">{{ __('terminals.kiosk.sound_help') }}</span>

                                    <div class="field">
                                        <span class="field-label">{{ __('terminals.kiosk.attract') }}</span>
                                        <div class="term-cfd-media">
                                            <template x-for="(m, i) in form.kiosk_attract_media" :key="m.path">
                                                <div class="term-cfd-thumb">
                                                    <img :src="m.url" alt="">
                                                    <input type="hidden" name="kiosk_media[]" :value="m.path">
                                                    <button type="button" class="term-cfd-thumb-x" @click="removeKioskMedia(i)"
                                                            :aria-label="@js(__('terminals.kiosk.attract_remove'))">
                                                        <x-icon name="x" class="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            </template>
                                            <button type="button" class="term-cfd-add"
                                                    x-show="form.kiosk_attract_media.length < 12"
                                                    :disabled="uploadingKioskMedia" @click="pickKioskMedia()">
                                                <template x-if="!uploadingKioskMedia"><x-icon name="plus" class="w-5 h-5" /></template>
                                                <svg x-show="uploadingKioskMedia" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                                </svg>
                                            </button>
                                            <input type="file" x-ref="kioskMediaInput" class="hidden"
                                                   accept="image/jpeg,image/png,image/webp" multiple @change="onKioskMediaPick($event)">
                                        </div>
                                        <span class="field-help">{{ __('terminals.kiosk.attract_help') }}</span>
                                    </div>

                                    <label class="field-toggle">
                                        <input type="hidden" name="kiosk_pin_required" value="0">
                                        <input type="checkbox" name="kiosk_pin_required" value="1" x-model="form.kiosk_pin_required">
                                        <span>{{ __('terminals.kiosk.pin_required') }}</span>
                                    </label>
                                    <span class="field-help">{{ __('terminals.kiosk.pin_required_help') }}</span>

                                    <a :href="kioskOpenUrl" target="_blank" rel="noopener"
                                       class="pos-btn pos-btn-sm pos-btn-ghost self-start"
                                       x-show="isEdit && form.kiosk_enabled">
                                        <x-icon name="pos" class="w-4 h-4" />
                                        {{ __('terminals.kiosk.open') }}
                                    </a>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @if ($isEdit)
            <form method="POST" action="{{ route('admin.terminals.destroy', $terminal) }}" x-ref="deleteForm" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>
</x-admin-layout>
