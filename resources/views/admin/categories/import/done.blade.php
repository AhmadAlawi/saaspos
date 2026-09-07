<x-admin-layout
    active="categories"
    :title="__('categories.import.done_title')"
    :crumbs="[
        ['label' => __('categories.crumb_parent')],
        ['label' => __('categories.title'), 'href' => route('admin.categories.index')],
        ['label' => __('categories.import.title'), 'href' => route('admin.categories.import')],
        ['label' => __('categories.import.done_title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('categories.import.done_title') }}</h1>
                <p class="page-sub">
                    {{ __('categories.import.done_sub', [
                        'created' => $summary['created'],
                        'updated' => $summary['updated'],
                        'skipped' => $summary['skipped'],
                    ]) }}
                </p>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-body">
                <div class="prod-import-counts">
                    <div class="prod-import-count is-positive">
                        <div class="prod-import-count-value">{{ $summary['created'] }}</div>
                        <div class="prod-import-count-label">{{ __('categories.import.count_created') }}</div>
                    </div>
                    <div class="prod-import-count">
                        <div class="prod-import-count-value">{{ $summary['updated'] }}</div>
                        <div class="prod-import-count-label">{{ __('categories.import.count_updated') }}</div>
                    </div>
                    <div class="prod-import-count is-warning">
                        <div class="prod-import-count-value">{{ $summary['skipped'] }}</div>
                        <div class="prod-import-count-label">{{ __('categories.import.count_skipped') }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if (!empty($summary['errors']))
            <div class="card mb-5">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('categories.import.errors_heading') }} ({{ count($summary['errors']) }})</div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="prod-import-errors">
                        @foreach ($summary['errors'] as $err)
                            <li>
                                <span class="prod-import-error-row">{{ __('categories.import.error_row', ['row' => $err['row']]) }}</span>
                                <span>{{ $err['message'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('admin.categories.import') }}"
               class="pos-btn pos-btn-sm pos-btn-ghost">
                {{ __('categories.import.title') }}
            </a>
            <a href="{{ route('admin.categories.index') }}"
               class="pos-btn pos-btn-sm pos-btn-primary">
                {{ __('categories.import.done_back') }}
            </a>
        </div>
    </div>
</x-admin-layout>
