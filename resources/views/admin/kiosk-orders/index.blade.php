<x-admin-layout
    active="kiosk-orders"
    :title="__('kiosk-orders.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('kiosk-orders.crumb_parent')],
        ['label' => __('kiosk-orders.title')],
    ]">

    @php
        // Paid orders waiting to be handed over. Everything the cashier needs to
        // check the bag against the order — lines, tenders, totals — is embedded
        // here, so the modal opens with no round-trip and no page navigation.
        $collectForJs = $awaitingCollection->mapWithKeys(fn ($o) => [$o->id => [
            'id'          => $o->id,
            'code'        => $o->pickup_code,
            'number'      => $o->number,
            'note'        => $o->kiosk_note,
            'customer'    => $o->customer?->name,
            'phone'       => $o->customer?->phone,
            'paidAt'      => format_datetime($o->sale_datetime),
            'saleUrl'     => route('admin.sales.show', $o),
            'collectUrl'  => route('admin.kiosk-orders.collect', $o),
            'subtotal'    => format_money((float) $o->subtotal),
            'discount'    => (float) $o->discount_total > 0 ? format_money((float) $o->discount_total) : null,
            'tax'         => format_money((float) $o->tax_total),
            'total'       => format_money((float) $o->grand_total),
            'payments'    => $o->payments->map(fn ($p) => [
                'method' => $p->paymentMethod?->name ?? __('kiosk-orders.collect.unknown_method'),
                'amount' => format_money((float) $p->amount),
            ])->values(),
            'lines'       => $o->items->map(fn ($i) => [
                'id'        => $i->id,
                'name'      => $i->product_name_snapshot,
                'sku'       => $i->sku_snapshot,
                'qty'       => rtrim(rtrim(number_format((float) $i->quantity, 4, '.', ''), '0'), '.') ?: '0',
                'unitPrice' => format_money((float) $i->unit_price),
                'lineTotal' => format_money((float) $i->line_total),
            ])->values(),
        ]]);
    @endphp

    {{-- Server-paginated pending queue (see data-table-server.js). The
         take-payment panel + the collect queue below are unchanged. `initialTab`
         opens on whichever queue has work, so an empty "Pending" tab never hides
         a stack of orders waiting to be handed over. --}}
    <div class="page-wide" x-data="kioskOrdersPage({{ \Illuminate\Support\Js::from([
        'endpoint'         => route('admin.kiosk-orders.rows'),
        'perPage'          => $pendingPerPage,
        'total'            => $pendingTotal,
        'page'             => 1,
        'totalPages'       => $pendingTotalPages,
        'paymentMethods'   => $paymentMethods,
        'hasGateway'       => $hasGateway,
        'currency'         => app_currency()['code'],
        'sessionCreateUrl' => route('cashier.pos-sessions.create'),
        'sessionStatusUrl' => route('cashier.pos-sessions.status', ['uuid' => '__UUID__']),
        'sessionCancelUrl' => route('cashier.pos-sessions.cancel', ['uuid' => '__UUID__']),
        'initialTab'       => $pendingTotal === 0 && $awaitingCollection->isNotEmpty() ? 'collect' : 'pending',
        'collectOrders'    => $collectForJs,
        'claimConfirmTitle' => __('kiosk-orders.claim.confirm_title'),
        'claimConfirmBody'  => __('kiosk-orders.claim.confirm_body'),
        'claimConfirmLabel' => __('kiosk-orders.claim.confirm_label'),
    ]) }})">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">
                    {{ __('kiosk-orders.title') }}
                    <span class="prod-badge prod-badge-muted ms-2">{{ __('kiosk-orders.pending') }}: <span x-text="matchedCount.toLocaleString()">{{ number_format($pendingTotal) }}</span></span>
                </h1>
                <p class="page-sub">{{ __('kiosk-orders.sub') }}</p>
            </div>
            @if ($pendingTotal > 0)
                <div class="dropdown" x-data="dropdown">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('kiosk-orders.export.button') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                        <a href="{{ route('admin.kiosk-orders.export', ['format' => 'csv']) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('kiosk-orders.export.csv') }}</span>
                        </a>
                        <a href="{{ route('admin.kiosk-orders.export', ['format' => 'xlsx']) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('kiosk-orders.export.xlsx') }}</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        {{-- Two queues, two jobs. Stacked, the collection list sat below the
             fold and got missed; as tabs each is a deliberate destination. --}}
        <div class="tabs mb-5">
            <button type="button" class="tab" :class="{ 'is-active': tab === 'pending' }" @click="tab = 'pending'">
                {{ __('kiosk-orders.tabs.pending') }}
                <span class="tab-count" x-text="matchedCount.toLocaleString()">{{ number_format($pendingTotal) }}</span>
            </button>
            <button type="button" class="tab" :class="{ 'is-active': tab === 'collect' }" @click="tab = 'collect'">
                {{ __('kiosk-orders.tabs.collect') }}
                <span class="tab-count">{{ $awaitingCollection->count() }}</span>
            </button>
            {{-- History. The two queues above only ever hold OPEN work — an
                 order leaves them for good the moment it's paid, collected or
                 rejected, so without this tab there was nowhere to find it
                 again. Own Alpine scope: its own endpoint, filters and paging. --}}
            <button type="button" class="tab" :class="{ 'is-active': tab === 'all' }" @click="tab = 'all'">
                {{ __('kiosk-orders.tabs.all') }}
            </button>
        </div>

        <div class="card card-pad-0" x-show="tab === 'pending'" x-cloak>
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('kiosk-orders.list_title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($pendingTotal) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>

            @if ($pendingTotal === 0)
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="pos" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('kiosk-orders.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('kiosk-orders.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('kiosk-orders.columns.pickup') }}</th>
                            <th>{{ __('kiosk-orders.columns.placed_at') }}</th>
                            <th>{{ __('kiosk-orders.columns.customer') }}</th>
                            <th class="num">{{ __('kiosk-orders.columns.items') }}</th>
                            <th class="num">{{ __('kiosk-orders.columns.total') }}</th>
                            <th class="dt-actions-col">{{ __('kiosk-orders.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.kiosk-orders._pending_rows', ['orders' => $orders])
                    </tbody>
                </table>
                </div>
                <x-admin.dt-pager />
            @endif
        </div>

        {{-- ══════════ Paid at the kiosk, waiting to be collected ══════════
             The money is already in. These sales are `completed`, so they never
             appeared in the pending queue above — but the goods are still behind
             the counter and the customer is holding nothing but a pickup code.
             They clear from this list when staff hand the bag over. --}}
        <div class="card card-pad-0" x-show="tab === 'collect'" x-cloak>
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('kiosk-orders.collect.title') }} ({{ $awaitingCollection->count() }})
                    <span class="prod-badge prod-badge-positive ms-2">{{ __('kiosk-orders.collect.paid') }}</span>
                </div>
            </div>

            @if ($awaitingCollection->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="check" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('kiosk-orders.collect.empty_title') }}</div>
                    <div class="dt-empty-sub">{{ __('kiosk-orders.collect.empty_sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('kiosk-orders.columns.pickup') }}</th>
                            <th>{{ __('kiosk-orders.collect.paid_at') }}</th>
                            <th>{{ __('kiosk-orders.columns.customer') }}</th>
                            <th class="num">{{ __('kiosk-orders.columns.items') }}</th>
                            <th class="num">{{ __('kiosk-orders.columns.total') }}</th>
                            <th class="dt-actions-col">{{ __('kiosk-orders.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($awaitingCollection as $paid)
                            {{-- Whole row opens the detail modal, matching the
                                 click-to-open convention on every other list. --}}
                            <tr class="prod-row" @click="openCollect({{ $paid->id }})">
                                <td>
                                    <div class="font-semibold tnum">{{ $paid->pickup_code }}</div>
                                    <div class="text-[11.5px] fg-tertiary mono">{{ $paid->number }}</div>
                                    @if ($paid->kiosk_note)
                                        <div class="text-[11.5px] fg-secondary mt-0.5">“{{ $paid->kiosk_note }}”</div>
                                    @endif
                                </td>
                                <td class="fg-secondary">{{ optional($paid->sale_datetime)->diffForHumans() }}</td>
                                <td>{{ $paid->customer?->name ?? __('kiosk-orders.walk_in') }}</td>
                                <td class="num tnum fg-tertiary">{{ $paid->items_count }}</td>
                                <td class="num tnum">{{ format_money((float) $paid->grand_total) }}</td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                                                @click.stop="openCollect({{ $paid->id }})">
                                            {{ __('kiosk-orders.actions.view') }}
                                        </button>
                                        <form method="POST" action="{{ route('admin.kiosk-orders.collect', $paid) }}" @click.stop>
                                            @csrf
                                            <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                                <x-icon name="check" class="w-4 h-4" />
                                                {{ __('kiosk-orders.actions.collect') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

        {{-- ══════════ Every kiosk order, whatever became of it ══════════
             Nested Alpine scope with its own dataTableServer: the root
             component's table is already bound to the pending endpoint, and one
             scope can't drive two independent paginated tables. --}}
        <div x-show="tab === 'all'" x-cloak
             x-data="kioskAllOrdersPage({{ \Illuminate\Support\Js::from([
                 'endpoint'   => route('admin.kiosk-orders.all-rows'),
                 'perPage'    => $allPerPage,
                 'total'      => $allTotal,
                 'page'       => 1,
                 'totalPages' => $allTotalPages,
             ]) }})">

            <form method="GET" action="{{ route('admin.kiosk-orders.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('kiosk-orders.all.date_from') }}</span>
                    <input type="text" name="from" value="{{ $allFilters['from'] }}"
                           class="pos-input js-datepicker"
                           placeholder="{{ __('table.filter_date_from_placeholder') }}"
                           data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('kiosk-orders.all.date_to') }}</span>
                    <input type="text" name="to" value="{{ $allFilters['to'] }}"
                           class="pos-input js-datepicker"
                           placeholder="{{ __('table.filter_date_to_placeholder') }}"
                           data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('kiosk-orders.all.status') }}</span>
                    <select name="status" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('kiosk-orders.all.status_all') }}</option>
                        @foreach (['placed', 'held', 'completed', 'voided', 'partially_refunded', 'refunded'] as $st)
                            <option value="{{ $st }}" @selected($allFilters['status'] === $st)>{{ __('sales.statuses.'.$st) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" name="q" value="{{ $allFilters['q'] }}" class="pos-input"
                               placeholder="{{ __('kiosk-orders.all.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                </button>
            </form>

            <div class="card card-pad-0 mt-5">
                <div class="dt-toolbar">
                    <div class="dt-toolbar-title">
                        {{ __('kiosk-orders.all.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($allTotal) }}</span>)
                    </div>
                    <x-admin.dt-toolbar-actions :show-sort="false" />
                </div>

                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('kiosk-orders.columns.pickup') }}</th>
                            <th>{{ __('kiosk-orders.all.placed_at') }}</th>
                            <th>{{ __('kiosk-orders.columns.customer') }}</th>
                            <th>{{ __('kiosk-orders.all.status') }}</th>
                            <th class="num">{{ __('kiosk-orders.columns.items') }}</th>
                            <th class="num">{{ __('kiosk-orders.columns.total') }}</th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.kiosk-orders._all_rows', ['allOrders' => $allOrders])
                    </tbody>
                </table>
                </div>

                {{-- Empty state is Alpine-driven, not @if($allTotal): the date
                     filter defaults to today, so this list legitimately empties
                     and refills without a page load. --}}
                <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                    <span class="dt-empty-icon"><x-icon name="pos" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('kiosk-orders.all.empty_title') }}</div>
                    <div class="dt-empty-sub">{{ __('kiosk-orders.all.empty_sub') }}</div>
                </div>

                <x-admin.dt-pager />
            </div>
        </div>

        {{-- ══════════ Handover detail — read-only, opens over the queue ══════════
             The sale is already paid, so there is nothing to edit here. The
             cashier needs to check the bag against the lines and hand it over
             without losing their place in the queue. --}}
        <div class="scrim overlay-host" x-show="collect" x-cloak
             @click.self="closeCollect()" @keydown.escape.window="if (collect) closeCollect()">
            <div class="modal-card kio-panel" role="dialog" aria-modal="true">
                <template x-if="collect">
                    <div class="contents">
                        <div class="modal-head">
                            <div>
                                <div class="modal-title">
                                    <span class="tnum" x-text="collect.code"></span>
                                    <span class="prod-badge prod-badge-positive ms-2">{{ __('kiosk-orders.collect.paid') }}</span>
                                </div>
                                <div class="modal-sub">
                                    <span class="mono" x-text="collect.number"></span>
                                    <span> · </span>
                                    <span x-text="collect.customer || @js(__('kiosk-orders.walk_in'))"></span>
                                    <template x-if="collect.phone"><span> · <span x-text="collect.phone"></span></span></template>
                                </div>
                            </div>
                            <button type="button" class="modal-x" @click="closeCollect()" aria-label="{{ __('kiosk-orders.pay.cancel') }}">
                                <x-icon name="x" class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="modal-body kio-panel-body">
                            <template x-if="collect.note">
                                <x-alert type="info"><span x-text="collect.note"></span></x-alert>
                            </template>

                            <div class="kio-section-title">{{ __('kiosk-orders.pay.items') }}</div>
                            <ul class="kio-collect-lines">
                                <template x-for="line in collect.lines" :key="line.id">
                                    <li class="kio-collect-line">
                                        <span class="kio-collect-qty tnum" x-text="line.qty + '×'"></span>
                                        <span class="kio-collect-name">
                                            <span x-text="line.name"></span>
                                            <template x-if="line.sku">
                                                <span class="kio-collect-sku mono" x-text="line.sku"></span>
                                            </template>
                                        </span>
                                        <span class="kio-collect-each tnum" x-text="line.unitPrice"></span>
                                        <span class="kio-collect-amt tnum" x-text="line.lineTotal"></span>
                                    </li>
                                </template>
                            </ul>

                            <div class="kio-collect-totals">
                                <div class="kio-collect-row">
                                    <span>{{ __('kiosk-orders.collect.subtotal') }}</span>
                                    <span class="tnum" x-text="collect.subtotal"></span>
                                </div>
                                <template x-if="collect.discount">
                                    <div class="kio-collect-row">
                                        <span>{{ __('kiosk-orders.collect.discount') }}</span>
                                        <span class="tnum" x-text="'− ' + collect.discount"></span>
                                    </div>
                                </template>
                                <div class="kio-collect-row">
                                    <span>{{ __('kiosk-orders.collect.tax') }}</span>
                                    <span class="tnum" x-text="collect.tax"></span>
                                </div>
                                <div class="kio-collect-row is-total">
                                    <span>{{ __('kiosk-orders.collect.total') }}</span>
                                    <span class="tnum" x-text="collect.total"></span>
                                </div>
                            </div>

                            <div class="kio-section-title">{{ __('kiosk-orders.collect.tendered') }}</div>
                            <ul class="kio-collect-tenders">
                                <template x-for="(p, i) in collect.payments" :key="i">
                                    <li class="kio-collect-row">
                                        <span x-text="p.method"></span>
                                        <span class="tnum" x-text="p.amount"></span>
                                    </li>
                                </template>
                            </ul>

                            <div class="kio-collect-meta">
                                <span>{{ __('kiosk-orders.collect.paid_at') }}:</span>
                                <span x-text="collect.paidAt"></span>
                            </div>
                        </div>

                        <div class="modal-foot">
                            <a :href="collect.saleUrl" class="pos-btn pos-btn-sm pos-btn-ghost">
                                {{ __('kiosk-orders.collect.open_sale') }}
                            </a>
                            <form method="POST" :action="collect.collectUrl">
                                @csrf
                                <button type="submit" class="pos-btn pos-btn-primary">
                                    <x-icon name="check" class="w-4 h-4" />
                                    {{ __('kiosk-orders.actions.collect') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ══════════ Take-payment panel — a small cashier, in place ══════════ --}}
        <div class="scrim overlay-host" x-show="order" x-cloak
             @click.self="closePay()" @keydown.escape.window="if (order) closePay()">
            <div class="modal-card kio-panel" role="dialog" aria-modal="true">
                <template x-if="order">
                    <div class="contents">
                        <div class="modal-head">
                            <div>
                                <div class="modal-title" x-text="@js(__('kiosk-orders.pay.title')).replace(':code', order.code)"></div>
                                <div class="modal-sub">
                                    <span x-text="order.customer || @js(__('kiosk-orders.walk_in'))"></span>
                                    <template x-if="order.note"><span> · “<span x-text="order.note"></span>”</span></template>
                                </div>
                            </div>
                            <button type="button" class="modal-x" @click="closePay()" aria-label="{{ __('kiosk-orders.pay.cancel') }}">
                                <x-icon name="x" class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="modal-body kio-panel-body">
                            {{-- The shopper scanned the kiosk's static UPI QR and says they
                                 paid. There is no callback to confirm that, so the cashier is
                                 given the exact amount + the reference the QR embedded, and
                                 must acknowledge seeing it before this order can be settled. --}}
                            <template x-if="order.claim">
                                <div class="kio-claim">
                                    <div class="kio-claim-head">
                                        <x-icon name="alert" class="w-4 h-4" />
                                        <span x-text="@js(__('kiosk-orders.claim.title')).replace(':method', order.claim.method)"></span>
                                    </div>
                                    <div class="kio-claim-body">{{ __('kiosk-orders.claim.body') }}</div>
                                    <dl class="kio-claim-facts">
                                        <div>
                                            <dt>{{ __('kiosk-orders.claim.amount') }}</dt>
                                            <dd class="tnum" x-text="order.claim.amount"></dd>
                                        </div>
                                        <div x-show="order.claim.reference">
                                            <dt>{{ __('kiosk-orders.claim.reference') }}</dt>
                                            <dd class="mono" x-text="order.claim.reference"></dd>
                                        </div>
                                        <div>
                                            <dt>{{ __('kiosk-orders.claim.at') }}</dt>
                                            <dd x-text="order.claim.at"></dd>
                                        </div>
                                    </dl>
                                </div>
                            </template>

                            {{-- ── Lines: remove / change qty ── --}}
                            <div class="kio-section-title">{{ __('kiosk-orders.pay.items') }}</div>
                            <ul class="kio-lines">
                                <template x-for="line in lines" :key="line.id">
                                    <li class="kio-line">
                                        <div class="kio-line-name" x-text="line.name"></div>
                                        <div class="kio-step">
                                            <button type="button" @click="dec(line)" :disabled="line.qty <= 1" aria-label="−">
                                                <x-icon name="minus" class="w-4 h-4" />
                                            </button>
                                            <span class="kio-step-val tnum" x-text="line.qty"></span>
                                            <button type="button" @click="inc(line)" aria-label="+">
                                                <x-icon name="plus" class="w-4 h-4" />
                                            </button>
                                        </div>
                                        <div class="kio-line-amt tnum" x-text="$formatMoney(line.unitPrice * line.qty)"></div>
                                        <button type="button" class="kio-line-del" @click="removeLine(line)" aria-label="remove">
                                            <x-icon name="trash" class="w-4 h-4" />
                                        </button>
                                    </li>
                                </template>
                            </ul>
                            <div class="kio-empty" x-show="!lines.length">{{ __('kiosk-orders.errors.no_lines') }}</div>

                            {{-- ── Totals ── --}}
                            <div class="kio-totals">
                                <div class="kio-total-row"><span>{{ __('kiosk-orders.pay.subtotal') }}</span><span class="tnum" x-text="$formatMoney(subtotal)"></span></div>
                                <div class="kio-total-row"><span>{{ __('kiosk-orders.pay.tax') }}</span><span class="tnum" x-text="$formatMoney(tax)"></span></div>
                                <div class="kio-total-row is-grand"><span>{{ __('kiosk-orders.pay.amount_due') }}</span><span class="tnum" x-text="$formatMoney(total)"></span></div>
                            </div>

                            {{-- ── Committed tenders (split) ── --}}
                            <template x-if="payments.length">
                                <div class="kio-tenders">
                                    <template x-for="(p, i) in payments" :key="i">
                                        <div class="kio-tender">
                                            <span x-text="p.method_name"></span>
                                            <span class="tnum" x-text="$formatMoney(p.amount)"></span>
                                            <button type="button" class="kio-line-del" @click="dropPayment(i)" aria-label="remove">
                                                <x-icon name="x" class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </template>
                                    <div class="kio-total-row is-grand">
                                        <span>{{ __('kiosk-orders.pay.remaining') }}</span>
                                        <span class="tnum" x-text="$formatMoney(remaining)"></span>
                                    </div>
                                </div>
                            </template>

                            {{-- ── QR pane ── --}}
                            <template x-if="qrOpen">
                                <div class="kio-qr">
                                    <div class="kio-qr-card" x-show="qrImage"><img :src="qrImage" alt=""></div>
                                    <div class="kio-qr-text">
                                        <div class="font-semibold" x-text="qrStatus === 'starting' ? @js(__('kiosk-orders.pay.qr_starting')) : @js(__('kiosk-orders.pay.qr_scan'))"></div>
                                        <div class="fg-tertiary text-[12px]">{{ __('kiosk-orders.pay.qr_hint') }}</div>
                                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost mt-2" @click="cancelQr()">
                                            {{ __('kiosk-orders.pay.cancel') }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            {{-- ── Tender entry ── --}}
                            <div class="form-stack" x-show="!qrOpen && remaining > 0.0001">
                                <div class="kio-section-title mt-2">{{ __('kiosk-orders.pay.method') }}</div>

                                <label class="field">
                                    {{-- Rendered server-side: TomSelect reads the options when it
                                         initialises, which happens before any x-for child would run. --}}
                                    <select class="pos-input"
                                            x-data="enhancedSelect()"
                                            x-effect="syncOptions(methodId)"
                                            x-model="methodId">
                                        <option value="">{{ __('kiosk-orders.pay.method_placeholder') }}</option>
                                        @foreach ($paymentMethods as $m)
                                            <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                {{-- UPI: a `upi://pay?…` deep-link QR built from the method's
                                     stored VPA. The customer scans and pays in their own app;
                                     the counter confirms by typing the UTR below. --}}
                                <div class="kio-qr" x-show="upiQrDataUrl" x-cloak>
                                    <div class="kio-qr-card">
                                        <img :src="upiQrDataUrl" alt="{{ __('kiosk-orders.pay.upi_scan') }}">
                                    </div>
                                    <div class="kio-qr-text">
                                        <div class="font-semibold" x-text="@js(__('kiosk-orders.pay.upi_scan')).replace(':amount', $formatMoney(currentAmount))"></div>
                                        <div class="text-secondary mt-1">{{ __('kiosk-orders.pay.upi_hint') }}</div>
                                        <div class="text-tertiary mt-1 tnum" x-text="method?.vpa"></div>
                                    </div>
                                </div>

                                <label class="field" x-show="isCash" x-cloak>
                                    <span class="field-label">{{ __('kiosk-orders.pay.tendered') }}</span>
                                    <input type="number" step="0.01" min="0" x-model="tendered" class="pos-input tnum" inputmode="decimal">
                                </label>

                                <div class="kio-change" x-show="isCash && change > 0" x-cloak>
                                    <span>{{ __('kiosk-orders.pay.change') }}</span>
                                    <span class="tnum" x-text="$formatMoney(change)"></span>
                                </div>

                                <label class="field" x-show="needsReference" x-cloak>
                                    <span class="field-label is-required">{{ __('kiosk-orders.pay.reference') }}</span>
                                    <input type="text" x-model="reference" class="pos-input" maxlength="191">
                                </label>

                                <div class="flex items-center gap-2 flex-wrap">
                                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                                            :disabled="!canAddPayment" @click="addPayment()">
                                        <x-icon name="plus" class="w-4 h-4" />
                                        {{ __('kiosk-orders.pay.add_payment') }}
                                    </button>
                                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                                            x-show="hasGateway" @click="startQr()">
                                        <x-icon name="qr-code" class="w-4 h-4" />
                                        {{ __('kiosk-orders.pay.charge_qr') }}
                                    </button>
                                </div>
                            </div>

                            <div class="kio-error" x-show="error" x-cloak x-text="error"></div>
                        </div>

                        <div class="modal-foot">
                            <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="closePay()">
                                {{ __('kiosk-orders.pay.cancel') }}
                            </button>
                            <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                                    :disabled="!canConfirm" @click="confirm()">
                                <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                </svg>
                                <template x-if="!submitting"><x-icon name="check" class="w-4 h-4" /></template>
                                {{ __('kiosk-orders.pay.confirm') }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-admin-layout>
