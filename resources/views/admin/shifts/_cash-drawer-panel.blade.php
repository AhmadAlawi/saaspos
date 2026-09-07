{{-- Cash drawer activity panel — shown on an open shift only.
     Three small forms (pay-in / pay-out / drawer-open-no-sale), each
     gated by its own permission. data-ajax-form handles the POST so the
     page reloads via the controller's redirect on success, refreshing
     both the X-report numbers and the entries log. --}}

@php
    $canPayIn       = auth()->user()?->hasPermission('cash_drawer.pay_in')        ?? false;
    $canPayOut      = auth()->user()?->hasPermission('cash_drawer.pay_out')       ?? false;
    $canOpenDrw     = auth()->user()?->hasPermission('cash_drawer.open_no_sale')  ?? false;
    // Paying suppliers from the till reuses the supplier-payment form; gated by
    // the same permission as the admin supplier-payments screen.
    $canPaySupplier = auth()->user()?->hasPermission('suppliers.payments_record') ?? false;

    $tabs = collect([
        ['key' => 'pay_in',              'label' => __('cash_drawer.types.pay_in'),              'can' => $canPayIn],
        ['key' => 'pay_out',             'label' => __('cash_drawer.types.pay_out'),             'can' => $canPayOut],
        ['key' => 'pay_supplier',        'label' => __('cash_drawer.types.pay_supplier'),        'can' => $canPaySupplier],
        ['key' => 'drawer_open_no_sale', 'label' => __('cash_drawer.types.drawer_open_no_sale'), 'can' => $canOpenDrw],
    ])->filter(fn ($t) => $t['can'])->values();
    $defaultTab = $tabs->first()['key'] ?? null;
@endphp

@if ($tabs->isNotEmpty())
    <div class="card" x-data="{ tab: @js($defaultTab) }">
        <div class="card-header"><div>
            <div class="card-title">{{ __('cash_drawer.panel.title') }}</div>
            <div class="card-title-sub">{{ __('cash_drawer.panel.sub') }}</div>
        </div></div>
        <div class="card-body">
            {{-- Tab strip --}}
            <div class="flex items-center gap-1 mb-4 border-b border-default">
                @foreach ($tabs as $t)
                    <button type="button"
                            @click="tab = {{ \Illuminate\Support\Js::from($t['key']) }}"
                            :class="tab === @js($t['key'])
                                ? 'border-b-2 border-accent text-primary -mb-px'
                                : 'text-muted hover:text-primary'"
                            class="px-3 py-2 text-sm font-medium">
                        {{ $t['label'] }}
                    </button>
                @endforeach
            </div>

            {{-- Pay-in form --}}
            @if ($canPayIn)
                <form method="POST"
                      action="{{ route('admin.shifts.cash-drawer.record', $shift) }}"
                      data-ajax-form
                      x-show="tab === 'pay_in'"
                      x-cloak>
                    @csrf
                    <input type="hidden" name="type" value="pay_in">
                    <div class="form-stack">
                        <label class="field">
                            <span class="field-label is-required">{{ __('cash_drawer.fields.amount') }}</span>
                            <input type="number" name="amount" class="pos-input" step="0.0001" min="0" required>
                            <p class="field-help">{{ __('cash_drawer.help.pay_in_amount') }}</p>
                        </label>
                        <label class="field">
                            <span class="field-label is-required">{{ __('cash_drawer.fields.reason') }}</span>
                            <input type="text" name="reason" class="pos-input" maxlength="255" required>
                            <p class="field-help">{{ __('cash_drawer.help.pay_in_reason') }}</p>
                        </label>
                    </div>
                    <div class="flex items-center justify-end gap-2 mt-4">
                        <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                            <x-icon name="plus" class="w-4 h-4" />
                            {{ __('cash_drawer.actions.record_pay_in') }}
                        </button>
                    </div>
                </form>
            @endif

            {{-- Pay-out form --}}
            @if ($canPayOut)
                <form method="POST"
                      action="{{ route('admin.shifts.cash-drawer.record', $shift) }}"
                      data-ajax-form
                      x-show="tab === 'pay_out'"
                      x-cloak>
                    @csrf
                    <input type="hidden" name="type" value="pay_out">
                    <div class="form-stack">
                        <label class="field">
                            <span class="field-label is-required">{{ __('cash_drawer.fields.amount') }}</span>
                            <input type="number" name="amount" class="pos-input" step="0.0001" min="0" required>
                            <p class="field-help">{{ __('cash_drawer.help.pay_out_amount') }}</p>
                        </label>
                        <label class="field">
                            <span class="field-label is-required">{{ __('cash_drawer.fields.reason') }}</span>
                            <input type="text" name="reason" class="pos-input" maxlength="255" required>
                            <p class="field-help">{{ __('cash_drawer.help.pay_out_reason') }}</p>
                        </label>
                    </div>
                    <div class="flex items-center justify-end gap-2 mt-4">
                        <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                            <x-icon name="minus" class="w-4 h-4" />
                            {{ __('cash_drawer.actions.record_pay_out') }}
                        </button>
                    </div>
                </form>
            @endif

            {{-- Pay supplier — reuses the full supplier-payment form (supplier +
                 per-invoice allocation). Scoped to this shift's store; a cash
                 payment there records a pay-out against this shift automatically
                 (see RecordSupplierPayment). --}}
            @if ($canPaySupplier)
                <div x-show="tab === 'pay_supplier'" x-cloak>
                    <p class="field-help mb-4">{{ __('cash_drawer.help.pay_supplier') }}</p>
                    <div class="flex items-center justify-end">
                        <a href="{{ route('admin.supplier-payments.create', ['store_id' => $shift->store_id, 'from' => 'shift']) }}"
                           class="pos-btn pos-btn-sm pos-btn-primary">
                            <x-icon name="cash" class="w-4 h-4" />
                            {{ __('cash_drawer.actions.pay_supplier') }}
                        </a>
                    </div>
                </div>
            @endif

            {{-- Drawer-open-no-sale form. Fires the printer's cash-drawer
                 kick (WebUSB terminals only) AND records the audit entry —
                 see resources/js/admin/drawer-opener.js. --}}
            @if ($canOpenDrw)
                <form method="POST"
                      action="{{ route('admin.shifts.cash-drawer.record', $shift) }}"
                      x-data="drawerOpener({ recordUrl: '{{ route('admin.shifts.cash-drawer.record', $shift) }}' })"
                      @submit.prevent="open($event)"
                      x-show="tab === 'drawer_open_no_sale'"
                      x-cloak>
                    @csrf
                    <input type="hidden" name="type" value="drawer_open_no_sale">
                    <div class="form-stack">
                        <label class="field">
                            <span class="field-label is-required">{{ __('cash_drawer.fields.reason') }}</span>
                            <input type="text" name="reason" class="pos-input" maxlength="255" required>
                            <p class="field-help">{{ __('cash_drawer.help.drawer_open_reason') }}</p>
                        </label>
                    </div>
                    <div class="flex items-center justify-end gap-2 mt-4">
                        <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary" :disabled="busy">
                            <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                            <template x-if="!busy"><x-icon name="cash" class="w-4 h-4" /></template>
                            {{ __('cash_drawer.actions.record_drawer_open') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endif
