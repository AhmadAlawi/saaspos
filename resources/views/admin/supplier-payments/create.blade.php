<x-admin-layout
    active="supplier-payments"
    :title="__('supplier_payments.new')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('supplier_payments.crumb_parent')],
        ['label' => __('supplier_payments.title'), 'href' => route('admin.supplier-payments.index')],
        ['label' => __('supplier_payments.new')],
    ]">

    <div class="page-wide"
         x-data="supplierPaymentForm({
             openPurchasesUrlTemplate: '{{ route('admin.suppliers.open-purchases', ['supplier' => '__ID__']) }}',
             purchaseShowUrlTemplate:  '{{ route('admin.purchases.show', ['purchase' => '__ID__']) }}',
             suppliers:                {{ Js::from($suppliers->map(fn ($s) => [
                                            'id'                    => (string) $s->id,
                                            'default_currency_code' => $s->default_currency_code,
                                       ])->values()) }},
             initialSupplierId:        {{ Js::from(old('supplier_id', $supplierId)) }},
             initialStoreId:           {{ Js::from(old('store_id', $storeId ?? '')) }},
             initialMethodId:          {{ Js::from(old('payment_method_id', '')) }},
             initialPaymentDate:       {{ Js::from(old('payment_date', now()->toDateString())) }},
             initialReference:         {{ Js::from(old('reference', '')) }},
             initialNotes:             {{ Js::from(old('notes', '')) }},
             initialAllocationsRaw:    {{ Js::from(old('allocations', [])) }},
             initialPurchases:         {{ Js::from($openPurchases) }},
             initialMethodRequiresRef: {{ Js::from($methods->mapWithKeys(fn ($m) => [(string) $m->id => (bool) $m->requires_reference])) }},
         })">
        {{-- AJAX submit per the http-client memory rule. The factory's
             `submit($el)` posts via `posPost`, handles 422 by painting
             `.has-error` on the right fields + toasting, and on success
             follows the JSON `redirect` field. No page reload. --}}
        <form method="POST" action="{{ route('admin.supplier-payments.store') }}" novalidate
              @submit.prevent="submit($el)">
            @csrf

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.supplier-payments.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('supplier_payments.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('supplier_payments.new') }}</h1>
                        <p class="page-sub">{{ __('supplier_payments.new_sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.supplier-payments.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                        {{ __('supplier_payments.actions.discard') }}
                    </a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary"
                            :disabled="amount <= 0 || submitting"
                            :class="{ 'is-loading is-submitting': submitting }">
                        {{-- Spinner shown while the AJAX submit is in flight —
                             matches the global submit-loader markup so the look
                             is consistent across every primary action. --}}
                        <svg x-show="submitting" x-cloak class="animate-spin pos-btn-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <x-icon x-show="!submitting" name="check" class="w-4 h-4" />
                        <span x-text="submitting ? '{{ __('supplier_payments.actions.recording') }}' : '{{ __('supplier_payments.actions.record') }}'"></span>
                    </button>
                </div>
            </div>

            {{-- Arrived from the shift's cash-drawer panel: a cash payment here
                 comes out of the open till (RecordSupplierPayment logs a pay-out). --}}
            @if (! empty($fromShift) && ! empty($hasOpenShift))
                <x-alert type="info" class="mb-5">{{ __('supplier_payments.till.from_till_note') }}</x-alert>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
                {{-- LEFT: Header + Allocations table --}}
                <div class="space-y-5">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('supplier_payments.sections.header') }}</div>
                            <div class="card-title-sub">{{ __('supplier_payments.sections.header_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('supplier_payments.fields.supplier') }}</span>
                                        @php $selSup = (string) old('supplier_id', (string) ($supplierId ?? '')); @endphp
                                        {{-- When the user arrived from a purchase's "Record payment"
                                             CTA, lock the supplier picker to the one that PO belongs
                                             to. A hidden input carries the same value so the POST
                                             still sees `supplier_id`. --}}
                                        @if (! empty($lockSupplier))
                                            <input type="hidden" name="supplier_id" value="{{ $supplierId }}">
                                            <select name="supplier_id_display" class="pos-input"
                                                    x-data="enhancedSelect()"
                                                    disabled>
                                                @foreach ($suppliers as $s)
                                                    <option value="{{ $s->id }}" @selected((string) $s->id === $selSup)>{{ $s->name }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <select name="supplier_id" class="pos-input"
                                                    x-data="enhancedSelect()"
                                                    x-model="supplierId"
                                                    x-effect="onSupplierChange(supplierId)"
                                                    required>
                                                <option value="">—</option>
                                                @foreach ($suppliers as $s)
                                                    <option value="{{ $s->id }}" @selected((string) $s->id === $selSup)>{{ $s->name }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        @error('supplier_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('supplier_payments.fields.store') }}</span>
                                        @php $selStore = (string) old('store_id', (string) ($storeId ?? '')); @endphp
                                        <select name="store_id" class="pos-input"
                                                x-data="enhancedSelect()" x-model="storeId" required>
                                            <option value="">—</option>
                                            @foreach ($stores as $st)
                                                <option value="{{ $st->id }}" @selected((string) $st->id === $selStore)>{{ $st->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('store_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('supplier_payments.fields.date') }}</span>
                                        <input type="text" name="payment_date" x-model="paymentDate"
                                               class="pos-input js-datepicker" required>
                                        @error('payment_date')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('supplier_payments.fields.method') }}</span>
                                        <select name="payment_method_id" class="pos-input"
                                                x-data="enhancedSelect()" x-model="methodId" required>
                                            <option value="">—</option>
                                            @foreach ($methods as $m)
                                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('payment_method_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label" :class="{ 'is-required': requiresReference }">{{ __('supplier_payments.fields.reference') }}</span>
                                        <input type="text" name="reference" x-model="reference"
                                               class="pos-input mono" maxlength="191"
                                               :required="requiresReference">
                                        <p class="field-help">{{ __('supplier_payments.fields.reference_help') }}</p>
                                        @error('reference')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Allocations table — populated when a supplier is picked. --}}
                    <div class="card">
                        <div class="card-header flex items-center justify-between"><div>
                            <div class="card-title">{{ __('supplier_payments.sections.allocations') }}</div>
                            <div class="card-title-sub">{{ __('supplier_payments.sections.allocations_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <template x-if="!supplierId">
                                <p class="fg-tertiary text-sm">{{ __('supplier_payments.allocations.pick_supplier') }}</p>
                            </template>
                            <template x-if="supplierId && loading">
                                <p class="fg-tertiary text-sm">{{ __('supplier_payments.allocations.loading') }}</p>
                            </template>
                            <template x-if="supplierId && !loading && purchases.length === 0">
                                <p class="fg-tertiary text-sm">{{ __('supplier_payments.allocations.none') }}</p>
                            </template>

                            <template x-if="supplierId && !loading && purchases.length > 0">
                                <div>
                                    {{-- Auto-allocate row: type a total amount,
                                         hit the button, and we spread it across
                                         the oldest unpaid POs first. The user
                                         can still edit per-row amounts after. --}}
                                    <div class="pa-auto-bar">
                                        <label class="field pa-auto-input">
                                            <span class="field-label">{{ __('supplier_payments.allocations.auto_amount') }}</span>
                                            <input type="number" step="0.0001" min="0"
                                                   x-model="autoAmount"
                                                   class="pos-input tnum pos-input-compact"
                                                   placeholder="0.00"
                                                   data-no-validate>
                                        </label>
                                        <div class="pa-auto-actions">
                                            <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                                                    @click="autoAllocate()"
                                                    :disabled="!autoAmount || parseFloat(autoAmount) <= 0"
                                                    :title="@js(__('supplier_payments.allocations.auto_help'))">
                                                <x-icon name="check" class="w-3.5 h-3.5" />
                                                {{ __('supplier_payments.allocations.auto') }}
                                            </button>
                                            <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                                                    @click="clearAllocations()">
                                                <x-icon name="x" class="w-3.5 h-3.5" />
                                                {{ __('supplier_payments.allocations.clear') }}
                                            </button>
                                        </div>
                                    </div>
                                    <p class="field-help mb-3">{{ __('supplier_payments.allocations.auto_help') }}</p>
                                    <p class="field-help mb-3 fg-warning" x-show="autoLeftover > 0" x-cloak>
                                        {{ __('supplier_payments.allocations.auto_leftover') }}
                                        <span class="tnum" x-text="money(autoLeftover, displayCurrency)"></span>
                                    </p>

                                    <table class="dt-table pur-lines">
                                        <thead>
                                            <tr>
                                                <th>{{ __('supplier_payments.allocations.columns.number') }}</th>
                                                <th>{{ __('supplier_payments.allocations.columns.date') }}</th>
                                                <th class="num">{{ __('supplier_payments.allocations.columns.balance') }}</th>
                                                <th class="num">{{ __('supplier_payments.allocations.columns.amount') }}</th>
                                                <th class="dt-actions-col"><span class="sr-only">{{ __('supplier_payments.allocations.columns.actions') }}</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(p, i) in purchases" :key="p.id">
                                                <tr>
                                                    <td>
                                                        <a :href="purchaseShowUrlTemplate.replace('__ID__', p.id)"
                                                           target="_blank" rel="noopener"
                                                           class="link mono"
                                                           x-text="p.number"></a>
                                                    </td>
                                                    <td x-text="p.purchase_date"></td>
                                                    <td class="num tnum" x-text="money(p.balance_due, p.currency_code)"></td>
                                                    <td>
                                                        <input type="number" step="0.0001" min="0" :max="p._balance"
                                                               :name="`allocations[${i}][amount]`"
                                                               x-model="allocations[p.id]"
                                                               class="pos-input tnum pos-input-compact"
                                                               data-no-validate>
                                                        <input type="hidden" :name="`allocations[${i}][purchase_id]`" :value="p.id">
                                                    </td>
                                                    <td>
                                                        <button type="button" class="prod-default-btn"
                                                                @click="payInFull(p.id, p.balance_due)"
                                                                :title="@js(__('supplier_payments.allocations.pay_in_full'))"
                                                                :aria-label="@js(__('supplier_payments.allocations.pay_in_full'))">
                                                            <x-icon name="check" class="w-4 h-4" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                            @error('allocations')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- RIGHT: Total + Notes --}}
                <div class="space-y-5">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('supplier_payments.sections.total') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm fg-secondary">{{ __('supplier_payments.totals.amount') }}</span>
                                <span class="num tnum text-2xl font-semibold leading-none" x-text="money(amount, displayCurrency)"></span>
                            </div>
                            {{-- Credit line — appears when the typed total
                                 exceeds per-PO allocations; the leftover
                                 lands as supplier credit on submit. --}}
                            <div class="flex items-baseline justify-between gap-3 mt-3 pt-3 border-t"
                                 x-show="unallocatedCredit > 0" x-cloak>
                                <span class="text-sm fg-secondary">{{ __('supplier_payments.totals.credit') }}</span>
                                <span class="num tnum text-sm fg-positive font-semibold" x-text="money(unallocatedCredit, displayCurrency)"></span>
                            </div>
                            <input type="hidden" name="amount" :value="amount.toFixed(4)">
                            <p class="field-help mt-3" x-show="unallocatedCredit <= 0">{{ __('supplier_payments.totals.help') }}</p>
                            <p class="field-help mt-3" x-show="unallocatedCredit > 0" x-cloak>{{ __('supplier_payments.totals.credit_help') }}</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('supplier_payments.sections.notes') }}</div>
                        </div></div>
                        <div class="card-body">
                            <label class="field">
                                <span class="sr-only">{{ __('supplier_payments.fields.notes') }}</span>
                                <textarea name="notes" x-model="notes" rows="4" class="pos-input" maxlength="5000"></textarea>
                                @error('notes')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
