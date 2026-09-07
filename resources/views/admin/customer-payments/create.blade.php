<x-admin-layout
    active="customer-payments"
    :title="__('customer_payments.new')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('customer_payments.crumb_parent')],
        ['label' => __('customer_payments.title'), 'href' => route('admin.customer-payments.index')],
        ['label' => __('customer_payments.new')],
    ]">

    <div class="page-wide"
         x-data="customerPaymentForm({
             openSalesUrlTemplate:     '{{ route('admin.customers.open-sales', ['customer' => '__ID__']) }}',
             initialCustomerId:        {{ Js::from(old('customer_id', $customerId)) }},
             initialMethodId:          {{ Js::from(old('payment_method_id', '')) }},
             initialPaymentDate:       {{ Js::from(old('payment_date', now()->toDateString())) }},
             initialReference:         {{ Js::from(old('reference', '')) }},
             initialNotes:             {{ Js::from(old('notes', '')) }},
             initialAllocationsRaw:    {{ Js::from(old('allocations', [])) }},
             initialSales:             {{ Js::from($openSales) }},
             initialMethodRequiresRef: {{ Js::from($methods->mapWithKeys(fn ($m) => [(string) $m->id => (bool) $m->requires_reference])) }},
         })">
        <form method="POST" action="{{ route('admin.customer-payments.store') }}" novalidate
              @submit.prevent="submit($el)">
            @csrf

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.customer-payments.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('customer_payments.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('customer_payments.new') }}</h1>
                        <p class="page-sub">{{ __('customer_payments.new_sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.customer-payments.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                        {{ __('customer_payments.actions.discard') }}
                    </a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary"
                            :disabled="amount <= 0 || submitting"
                            :class="{ 'is-loading is-submitting': submitting }">
                        <svg x-show="submitting" x-cloak class="animate-spin pos-btn-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <x-icon x-show="!submitting" name="check" class="w-4 h-4" />
                        <span x-text="submitting ? '{{ __('customer_payments.actions.recording') }}' : '{{ __('customer_payments.actions.record') }}'"></span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
                <div class="space-y-5">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('customer_payments.sections.header') }}</div>
                            <div class="card-title-sub">{{ __('customer_payments.sections.header_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                <div class="grid grid-cols-1 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('customer_payments.fields.customer') }}</span>
                                        @php $selCust = (string) old('customer_id', (string) ($customerId ?? '')); @endphp
                                        <select name="customer_id" class="pos-input"
                                                x-data="enhancedSelect()"
                                                x-model="customerId"
                                                x-effect="onCustomerChange(customerId)"
                                                required>
                                            <option value="">—</option>
                                            @foreach ($customers as $c)
                                                {{-- Outstanding balance rides in `data-due` so the enhanced
                                                     select can render it in a distinct colour beside the name
                                                     (see select.js). The bare native <option> still reads as
                                                     just the name if JS is off. --}}
                                                <option value="{{ $c->id }}" @selected((string) $c->id === $selCust)
                                                    @if ((float) $c->outstanding_balance > 0) data-due="{{ __('customer_payments.fields.due') }} {{ format_money($c->outstanding_balance) }}" @endif>{{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('customer_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('customer_payments.fields.date') }}</span>
                                        <input type="text" name="payment_date" x-model="paymentDate"
                                               class="pos-input js-datepicker" required>
                                        @error('payment_date')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('customer_payments.fields.method') }}</span>
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
                                        <span class="field-label" :class="{ 'is-required': requiresReference }">{{ __('customer_payments.fields.reference') }}</span>
                                        <input type="text" name="reference" x-model="reference"
                                               class="pos-input mono" maxlength="191"
                                               :required="requiresReference">
                                        <p class="field-help">{{ __('customer_payments.fields.reference_help') }}</p>
                                        @error('reference')<p class="field-error">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Allocations table — populated when a customer is picked. --}}
                    <div class="card">
                        <div class="card-header flex items-center justify-between"><div>
                            <div class="card-title">{{ __('customer_payments.sections.allocations') }}</div>
                            <div class="card-title-sub">{{ __('customer_payments.sections.allocations_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <template x-if="!customerId">
                                <p class="fg-tertiary text-sm">{{ __('customer_payments.allocations.pick_customer') }}</p>
                            </template>
                            <template x-if="customerId && loading">
                                <p class="fg-tertiary text-sm">{{ __('customer_payments.allocations.loading') }}</p>
                            </template>
                            <template x-if="customerId && !loading && sales.length === 0">
                                <p class="fg-tertiary text-sm">{{ __('customer_payments.allocations.none') }}</p>
                            </template>

                            <template x-if="customerId && !loading && sales.length > 0">
                                <div>
                                    <div class="pa-auto-bar">
                                        <label class="field pa-auto-input">
                                            <span class="field-label">{{ __('customer_payments.allocations.auto_amount') }}</span>
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
                                                    :title="@js(__('customer_payments.allocations.auto_help'))">
                                                <x-icon name="check" class="w-3.5 h-3.5" />
                                                {{ __('customer_payments.allocations.auto') }}
                                            </button>
                                            <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                                                    @click="clearAllocations()">
                                                <x-icon name="x" class="w-3.5 h-3.5" />
                                                {{ __('customer_payments.allocations.clear') }}
                                            </button>
                                        </div>
                                    </div>
                                    <p class="field-help mb-3">{{ __('customer_payments.allocations.auto_help') }}</p>
                                    <p class="field-help mb-3 fg-warning" x-show="autoLeftover > 0" x-cloak>
                                        {{ __('customer_payments.allocations.auto_leftover') }}
                                        <span class="tnum" x-text="money(autoLeftover)"></span>
                                    </p>

                                    <table class="dt-table pur-lines">
                                        <thead>
                                            <tr>
                                                <th>{{ __('customer_payments.allocations.columns.number') }}</th>
                                                <th>{{ __('customer_payments.allocations.columns.date') }}</th>
                                                <th class="num">{{ __('customer_payments.allocations.columns.balance') }}</th>
                                                <th class="num">{{ __('customer_payments.allocations.columns.amount') }}</th>
                                                <th class="dt-actions-col"><span class="sr-only">{{ __('customer_payments.allocations.columns.actions') }}</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(s, i) in sales" :key="s.id">
                                                <tr>
                                                    <td class="mono" x-text="s.number"></td>
                                                    <td x-text="s.sale_date"></td>
                                                    <td class="num tnum" x-text="money(s.balance_due, s.currency_code)"></td>
                                                    <td>
                                                        <input type="number" step="0.0001" min="0" :max="s._balance"
                                                               :name="`allocations[${i}][amount]`"
                                                               x-model="allocations[s.id]"
                                                               class="pos-input tnum pos-input-compact"
                                                               data-no-validate>
                                                        <input type="hidden" :name="`allocations[${i}][sale_id]`" :value="s.id">
                                                    </td>
                                                    <td>
                                                        <button type="button" class="prod-default-btn"
                                                                @click="payInFull(s.id, s.balance_due)"
                                                                :title="@js(__('customer_payments.allocations.pay_in_full'))"
                                                                :aria-label="@js(__('customer_payments.allocations.pay_in_full'))">
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

                <div class="space-y-5">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('customer_payments.sections.total') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm fg-secondary">{{ __('customer_payments.totals.amount') }}</span>
                                <span class="num tnum text-2xl font-semibold leading-none" x-text="money(amount)"></span>
                            </div>
                            <div class="flex items-baseline justify-between gap-3 mt-3 pt-3 border-t"
                                 x-show="unallocatedCredit > 0" x-cloak>
                                <span class="text-sm fg-secondary">{{ __('customer_payments.totals.credit') }}</span>
                                <span class="num tnum text-sm fg-positive font-semibold" x-text="money(unallocatedCredit)"></span>
                            </div>
                            <input type="hidden" name="amount" :value="amount.toFixed(4)">
                            <p class="field-help mt-3" x-show="unallocatedCredit <= 0">{{ __('customer_payments.totals.help') }}</p>
                            <p class="field-help mt-3" x-show="unallocatedCredit > 0" x-cloak>{{ __('customer_payments.totals.credit_help') }}</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('customer_payments.sections.notes') }}</div>
                        </div></div>
                        <div class="card-body">
                            <label class="field">
                                <span class="sr-only">{{ __('customer_payments.fields.notes') }}</span>
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
