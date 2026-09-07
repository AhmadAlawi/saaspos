<x-admin-layout
    active="reports-hub"
    :title="__('reports.hub.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('reports.nav.section')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('reports.hub.title') }}</h1>
                <p class="page-sub">{{ __('reports.hub.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                @if (auth()->user()?->hasPermission('reports.schedule'))
                    <a href="{{ route('admin.reports.schedules.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                        <x-icon name="clock" class="w-4 h-4" />
                        {{ __('reports.schedule.nav') }}
                    </a>
                @endif
                <a href="{{ route('admin.reports.saved.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="star" class="w-4 h-4" />
                    {{ __('reports.saved.nav') }}
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {{-- Sales Summary --}}
            <a href="{{ route('admin.reports.sales.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="bar" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.sales_summary.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.sales_summary.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Sales by Product --}}
            <a href="{{ route('admin.reports.sales-by-product.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="box" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.sales_by_product.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.sales_by_product.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Sales by Cashier --}}
            <a href="{{ route('admin.reports.sales-by-cashier.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="user" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.sales_by_cashier.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.sales_by_cashier.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Sales by Payment Method --}}
            <a href="{{ route('admin.reports.sales-by-payment-method.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="card" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.sales_by_payment_method.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.sales_by_payment_method.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Sales by Category --}}
            <a href="{{ route('admin.reports.sales-by-category.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="tree" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.sales_by_category.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.sales_by_category.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Discounts --}}
            <a href="{{ route('admin.reports.discounts.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="tag" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.discounts.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.discounts.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Aged Receivables --}}
            <a href="{{ route('admin.reports.aged-receivables.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="cash" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.aged_receivables.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.aged_receivables.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Trial Balance --}}
            <a href="{{ route('admin.reports.trial-balance.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="calculator" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.trial_balance.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.trial_balance.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- General Ledger --}}
            <a href="{{ route('admin.reports.general-ledger.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="list" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.general_ledger.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.general_ledger.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Profit & Loss --}}
            <a href="{{ route('admin.reports.profit-and-loss.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="trending" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.pnl.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.pnl.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Balance Sheet --}}
            <a href="{{ route('admin.reports.balance-sheet.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="pie" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.balance_sheet.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.balance_sheet.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Cash Flow --}}
            <a href="{{ route('admin.reports.cash-flow.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="cash" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.cash_flow.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.cash_flow.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Top Customers --}}
            <a href="{{ route('admin.reports.top-customers.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="user" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.top_customers.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.top_customers.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Top Suppliers --}}
            <a href="{{ route('admin.reports.top-suppliers.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="truck" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.top_suppliers.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.top_suppliers.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Shifts by Cashier --}}
            <a href="{{ route('admin.reports.shifts-by-cashier.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="clock" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.shifts_by_cashier.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.shifts_by_cashier.sub') }}</p>
                    </div>
                </div>
            </a>

            {{-- Low Stock (cross-link to the Inventory module's report) --}}
            @can('viewAny', App\Models\StockLevel::class)
            <a href="{{ route('admin.inventory.low-stock.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="alert" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.low_stock.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.low_stock.sub') }}</p>
                    </div>
                </div>
            </a>
            @endcan

            {{-- Oversold (cross-link to the Inventory module's report) --}}
            @can('viewAny', App\Models\StockLevel::class)
            <a href="{{ route('admin.inventory.oversold.index') }}"
               class="card card-hover block p-5 group">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <x-icon name="alert" class="w-5 h-5 text-accent" />
                    </div>
                    <div>
                        <div class="font-semibold group-hover:text-accent transition-colors">
                            {{ __('reports.oversold.title') }}
                        </div>
                        <p class="text-sm fg-tertiary mt-0.5">{{ __('reports.oversold.sub') }}</p>
                    </div>
                </div>
            </a>
            @endcan
        </div>
    </div>
</x-admin-layout>
