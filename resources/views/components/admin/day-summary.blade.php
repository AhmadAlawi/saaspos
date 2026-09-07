{{--
    Topbar "End of day" report. A button in the topbar opens a modal with the
    whole day's activity for the active store — sales & purchase summaries, a
    stock position, the cash/bank money-in vs money-out breakdown, and a profit
    snapshot with tax collected vs paid. Data is fetched on open (JSON) from
    DaySummaryController and formatted client-side. Built from the system card
    components; gated in the topbar by `reports.view_financial`.
--}}
<div class="dsum-wrap"
     x-data="daySummaryPanel(@js(['url' => route('admin.day-summary')]))"
     @keydown.escape.window="close">
    <button type="button"
            class="icon-btn"
            :class="{ 'is-open': open }"
            @click="toggle"
            :aria-expanded="open"
            data-tip="{{ __('admin.day_summary.title') }}"
            aria-label="{{ __('admin.day_summary.title') }}">
        <x-icon name="bar" class="w-[18px] h-[18px]" />
    </button>

    <div class="scrim overlay-host"
         x-show="open"
         x-cloak
         @click.self="close"
         role="dialog"
         aria-modal="true"
         aria-label="{{ __('admin.day_summary.title') }}">
        <div class="modal-card dsum-modal">
            <div class="dsum-head">
                <div>
                    <div class="dsum-title">{{ __('admin.day_summary.title') }}</div>
                    <div class="dsum-sub" x-text="data ? data.date : @js(__('admin.day_summary.today'))"></div>
                </div>
                <div class="dsum-head-actions">
                    <button type="button" class="icon-btn" @click="load" aria-label="{{ __('admin.day_summary.refresh') }}">
                        <span class="inline-flex" :class="{ 'dsum-spin': busy }"><x-icon name="refresh" class="w-4 h-4" /></span>
                    </button>
                    <button type="button" class="icon-btn" @click="close" aria-label="{{ __('admin.day_summary.close') }}">
                        <x-icon name="x" class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <div class="dsum-loading" x-show="busy && !data" x-cloak>{{ __('admin.day_summary.loading') }}</div>

            <template x-if="data">
                <div class="dsum-body">
                    {{-- Sales + Purchase summaries --}}
                    <div class="dsum-cols">
                        <section class="card">
                            <div class="card-header"><div class="card-title">{{ __('admin.day_summary.sales_summary') }}</div></div>
                            <div class="card-body dsum-rows">
                                <div class="dsum-row"><span>{{ __('admin.day_summary.transactions') }}</span><span class="tnum" x-text="data.sales.transactions"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.gross') }}</span><span class="tnum" x-text="money(data.sales.gross)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.discounts') }}</span><span class="tnum" x-text="money(data.sales.discounts)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.taxable') }}</span><span class="tnum" x-text="money(data.sales.taxable)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.tax') }}</span><span class="tnum" x-text="money(data.sales.tax)"></span></div>
                                <div class="dsum-row is-net"><span>{{ __('admin.day_summary.net_sales') }}</span><span class="tnum" x-text="money(data.sales.net)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.items_sold') }}</span><span class="tnum" x-text="qty(data.sales.items)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.returns') }}</span><span class="tnum" x-text="money(data.sales.returns)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.average_sale') }}</span><span class="tnum" x-text="money(data.sales.average)"></span></div>
                            </div>
                        </section>

                        <section class="card">
                            <div class="card-header"><div class="card-title">{{ __('admin.day_summary.purchase_summary') }}</div></div>
                            <div class="card-body dsum-rows">
                                <div class="dsum-row"><span>{{ __('admin.day_summary.transactions') }}</span><span class="tnum" x-text="data.purchases.transactions"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.gross_purchase') }}</span><span class="tnum" x-text="money(data.purchases.gross)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.discounts') }}</span><span class="tnum" x-text="money(data.purchases.discounts)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.taxable') }}</span><span class="tnum" x-text="money(data.purchases.taxable)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.tax') }}</span><span class="tnum" x-text="money(data.purchases.tax)"></span></div>
                                <div class="dsum-row is-net"><span>{{ __('admin.day_summary.net_purchase') }}</span><span class="tnum" x-text="money(data.purchases.net)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.items_purchased') }}</span><span class="tnum" x-text="qty(data.purchases.items)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.returns') }}</span><span class="tnum" x-text="money(data.purchases.returns)"></span></div>
                                <div class="dsum-row"><span>{{ __('admin.day_summary.average_purchase') }}</span><span class="tnum" x-text="money(data.purchases.average)"></span></div>
                            </div>
                        </section>
                    </div>

                    {{-- Stock position --}}
                    <section class="card dsum-mt">
                        <div class="card-header"><div class="card-title">{{ __('admin.day_summary.stock_summary') }}</div></div>
                        <div class="card-body dsum-rows">
                            <div class="dsum-row"><span>{{ __('admin.day_summary.stock_cost') }}</span><span class="tnum" x-text="money(data.stock.value_cost)"></span></div>
                            <div class="dsum-row"><span>{{ __('admin.day_summary.stock_retail') }}</span><span class="tnum" x-text="money(data.stock.value_retail)"></span></div>
                            <div class="dsum-row"><span>{{ __('admin.day_summary.stock_potential') }}</span><span class="tnum" x-text="money(data.stock.potential)"></span></div>
                            <div class="dsum-row"><span>{{ __('admin.day_summary.stock_units') }}</span><span class="tnum" x-text="qty(data.stock.units)"></span></div>
                            <div class="dsum-row"><span>{{ __('admin.day_summary.out_of_stock') }}</span><span class="tnum" x-text="data.stock.out_of_stock"></span></div>
                        </div>
                    </section>

                    {{-- Payment breakdown --}}
                    <section class="card dsum-mt card-pad-0">
                        <div class="card-header"><div class="card-title">{{ __('admin.day_summary.payment_breakdown') }}</div></div>
                        <table class="dsum-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>{{ __('admin.day_summary.cash') }}</th>
                                    <th>{{ __('admin.day_summary.bank') }}</th>
                                    <th>{{ __('admin.day_summary.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>{{ __('admin.day_summary.received') }}</th>
                                    <td class="tnum" x-text="money(data.payments.received.cash)"></td>
                                    <td class="tnum" x-text="money(data.payments.received.bank)"></td>
                                    <td class="tnum is-strong" x-text="money(data.payments.received.total)"></td>
                                </tr>
                                <tr>
                                    <th>{{ __('admin.day_summary.given') }}</th>
                                    <td class="tnum" x-text="money(data.payments.given.cash)"></td>
                                    <td class="tnum" x-text="money(data.payments.given.bank)"></td>
                                    <td class="tnum is-strong" x-text="money(data.payments.given.total)"></td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    {{-- Profit snapshot --}}
                    <section class="card dsum-mt">
                        <div class="card-header"><div class="card-title">{{ __('admin.day_summary.profit') }}</div></div>
                        <div class="card-body">
                            <div class="dsum-profit-line">
                                <span class="dsum-profit-label">{{ __('admin.day_summary.cogs') }}</span>
                                <span class="dsum-profit-val tnum" x-text="money(data.profit.cogs)"></span>
                            </div>
                            <div class="dsum-profit-line is-gross">
                                <span class="dsum-profit-label">{{ __('admin.day_summary.gross_profit') }}
                                    <span class="dsum-pct" x-text="'· ' + data.profit.gross_margin + '%'"></span></span>
                                <span class="dsum-profit-val tnum" x-text="money(data.profit.gross_profit)"></span>
                            </div>
                            <div class="dsum-profit-line is-net">
                                <span class="dsum-profit-label">{{ __('admin.day_summary.net_profit') }}
                                    <span class="dsum-pct" x-text="'· ' + data.profit.net_margin + '%'"></span></span>
                                <span class="dsum-profit-val tnum" x-text="money(data.profit.net_profit)"></span>
                            </div>
                            <div class="dsum-profit-foot">
                                <span>{{ __('admin.day_summary.tax_collected') }} <b class="tnum" x-text="money(data.profit.tax_collected)"></b></span>
                                <span>{{ __('admin.day_summary.tax_paid') }} <b class="tnum" x-text="money(data.profit.tax_paid)"></b></span>
                                <span>{{ __('admin.day_summary.expenses') }} <b class="tnum" x-text="money(data.profit.expenses)"></b></span>
                            </div>
                        </div>
                    </section>

                    {{-- Ops --}}
                    <div class="dsum-ops dsum-mt">
                        <div><span class="dsum-stat-v tnum" x-text="data.ops.new_customers"></span><span class="dsum-stat-l">{{ __('admin.day_summary.new_customers') }}</span></div>
                        <div><span class="dsum-stat-v tnum" x-text="data.ops.low_stock"></span><span class="dsum-stat-l">{{ __('admin.day_summary.low_stock') }}</span></div>
                        <div><span class="dsum-stat-v tnum" x-text="data.ops.cashiers_on_shift"></span><span class="dsum-stat-l">{{ __('admin.day_summary.cashiers') }}</span></div>
                    </div>

                    <a href="{{ route('admin.dashboard') }}" class="dsum-foot">{{ __('admin.day_summary.open_dashboard') }}</a>
                </div>
            </template>
        </div>
    </div>
</div>
