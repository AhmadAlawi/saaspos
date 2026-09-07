<x-admin-layout
    active="journal"
    :title="__('accounting.journal.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('accounting.nav.section')],
        ['label' => __('accounting.journal.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('accounting.journal.title') }}</h1>
                <p class="page-sub">{{ __('accounting.journal.sub') }}</p>
            </div>
            @if (auth()->user()?->hasPermission('accounting.manual_entry'))
                <a href="{{ route('admin.accounting.journal.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('accounting.journal.new_entry') }}
                </a>
            @endif
        </div>

        <div class="card card-pad-0" x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">{{ __('accounting.journal.list_title') }} (<span x-text="rowCount">{{ $entries->count() }}</span>)</div>
                <x-admin.dt-toolbar-actions />
            </div>
            <form method="GET" action="{{ route('admin.accounting.journal.index') }}" class="inv-filter">
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('reports.filter.from') }}</span>
                    <input type="text" name="from" value="{{ $from->toDateString() }}" class="pos-input js-datepicker" data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-date">
                    <span class="field-label">{{ __('reports.filter.to') }}</span>
                    <input type="text" name="to" value="{{ $to->toDateString() }}" class="pos-input js-datepicker" data-fp-submit-on-change="1">
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('accounting.journal.filter.source') }}</span>
                    <select name="source" class="pos-input" x-data="enhancedSelect()" onchange="this.form.submit()">
                        <option value="">{{ __('accounting.journal.filter.all_sources') }}</option>
                        @foreach ($sources as $src)
                            <option value="{{ $src }}" @selected($source === $src)>{{ __('reports.general_ledger.sources.'.$src, [], $src) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-select">
                    <span class="field-label">{{ __('reports.filter.store') }}</span>
                    <select name="store_id" class="pos-input" x-data="enhancedSelect()" onchange="this.form.submit()">
                        <option value="">{{ __('reports.filter.store_all') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field inv-filter-search">
                    <span class="field-label">&nbsp;</span>
                    <span class="inv-search">
                        <span class="inv-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" x-model.debounce.150ms="search" data-no-live-search class="pos-input"
                               placeholder="{{ __('accounting.journal.filter.search') }}" @keydown.enter.prevent>
                    </span>
                </label>
                <x-admin.filter-reset route="admin.accounting.journal.index" />
            </form>

            @if ($entries->isEmpty())
                <div class="dt-empty">
                    <span class="dt-empty-icon"><x-icon name="note" class="w-5 h-5" /></span>
                    <div class="dt-empty-title">{{ __('accounting.journal.empty.title') }}</div>
                    <div class="dt-empty-sub">{{ __('accounting.journal.empty.sub') }}</div>
                </div>
            @else
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('accounting.journal.columns.date') }}</th>
                            <th>{{ __('accounting.journal.columns.number') }}</th>
                            <th>{{ __('accounting.journal.columns.source') }}</th>
                            <th>{{ __('accounting.journal.columns.description') }}</th>
                            <th class="num">{{ __('accounting.journal.columns.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr data-dt-row data-dt-name="{{ $entry->number.' '.$entry->description }}"
                                class="prod-row"
                                @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.accounting.journal.show', $entry)) }}">
                                <td class="fg-tertiary tnum">{{ $entry->entry_date?->format('d M Y') }}</td>
                                <td class="mono">{{ $entry->number }}</td>
                                <td><x-accounting.source-badge :source="$entry->source" /></td>
                                <td class="fg-tertiary">{{ $entry->description }}</td>
                                <td class="num tnum">{{ format_money($entry->lines_sum_debit ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <x-admin.dt-pager />
            @endif
        </div>
    </div>
</x-admin-layout>
