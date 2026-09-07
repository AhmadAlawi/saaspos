<x-admin-layout
    active="expenses"
    :title="__('expenses.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('expenses.crumb_parent')],
        ['label' => __('expenses.title')],
    ]">

    {{-- Server-paginated. x-data is page-wide so it wraps the summary cards it
         rewrites on every filter. Self-managed filter form → server mixin. --}}
    <div class="page-wide" x-data="expensesPage({{ \Illuminate\Support\Js::from([
        'endpoint'   => route('admin.expenses.rows'),
        'perPage'    => $perPage,
        'total'      => $total,
        'page'       => 1,
        'totalPages' => $totalPages,
    ]) }})">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('expenses.title') }}</h1>
                <p class="page-sub">{{ __('expenses.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                @can('export', App\Models\Expense::class)
                <div class="dropdown" x-data="dropdown">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="toggle()" :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('expenses.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel" x-show="open" x-cloak @click.outside="close()" @keydown.escape.window="close()">
                        <a href="{{ route('admin.expenses.export', ['format' => 'csv']) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('expenses.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.expenses.export', ['format' => 'xlsx']) }}" class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('expenses.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>
                @endcan

                @can('viewAny', App\Models\ExpenseCategory::class)
                    <a href="{{ route('admin.expense-categories.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                        <x-icon name="tag" class="w-4 h-4" />
                        {{ __('expense_categories.title') }}
                    </a>
                @endcan

                @can('create', App\Models\Expense::class)
                    <a href="{{ route('admin.expenses.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('expenses.new') }}
                    </a>
                @endcan
            </div>
        </div>

        <x-admin.summary-cards :cards="$summaryCards" />

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">
                    {{ __('expenses.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                </div>
                <x-admin.dt-toolbar-actions :show-sort="false" />
            </div>

            {{-- Self-managed filter form: every change calls applyFilters(). --}}
            <form method="GET" action="{{ route('admin.expenses.index') }}" class="inv-filter"
                  x-ref="filterForm" @submit.prevent="applyFilters()">
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('expenses.fields.category') }}</span>
                    <select name="category_id" class="pos-input" x-data="enhancedSelect()" @change="applyFilters()">
                        <option value="">{{ __('expenses.filter.all_categories') }}</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected((int) $filters['categoryId'] === (int) $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('expenses.filter.from') }}</span>
                    <input type="text" name="from" value="{{ $filters['from'] }}" class="pos-input js-datepicker" data-fp-submit-on-change="1" autocomplete="off">
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('expenses.filter.to') }}</span>
                    <input type="text" name="to" value="{{ $filters['to'] }}" class="pos-input js-datepicker" data-fp-submit-on-change="1" autocomplete="off">
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" class="pos-input"
                               placeholder="{{ __('expenses.filter.search') }}"
                               @input.debounce.400ms="applyFilters()">
                    </span>
                </label>
                <button type="button" class="inv-filter-reset" @click="resetFilters()"
                        title="{{ __('table.filter_reset') }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    <span>{{ __('table.filter_reset') }}</span>
                </button>
            </form>

            {{-- No expenses match the current filters. --}}
            <div class="dt-empty" x-show="dtReady && isEmpty" x-cloak>
                <span class="dt-empty-icon"><x-icon name="cash" class="w-5 h-5" /></span>
                <div class="dt-empty-title">{{ __('expenses.empty_state.title') }}</div>
                <div class="dt-empty-sub">{{ __('expenses.empty_state.sub') }}</div>
            </div>

            <div class="dt-scroll" x-show="!isEmpty">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('expenses.columns.number') }}</th>
                            <th>{{ __('expenses.columns.date') }}</th>
                            <th>{{ __('expenses.columns.category') }}</th>
                            <th>{{ __('expenses.columns.payment_method') }}</th>
                            <th>{{ __('expenses.columns.supplier') }}</th>
                            <th class="num">{{ __('expenses.columns.amount') }}</th>
                            <th class="dt-actions-col"><span class="sr-only">{{ __('expenses.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody data-dt-rows="table">
                        @include('admin.expenses._rows', ['expenses' => $expenses])
                    </tbody>
                </table>
            </div>
            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
