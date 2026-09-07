<x-admin-layout
    active="products"
    :title="__('products.import.done_title')"
    :crumbs="[
        ['label' => __('products.crumb_parent')],
        ['label' => __('products.title'), 'href' => route('admin.products.index')],
        ['label' => __('products.import.title'), 'href' => route('admin.products.import')],
        ['label' => __('products.import.done_title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('products.import.done_title') }}</h1>
                <p class="page-sub">
                    {{ __('products.import.done_sub', [
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
                        <div class="prod-import-count-label">Created</div>
                    </div>
                    <div class="prod-import-count">
                        <div class="prod-import-count-value">{{ $summary['updated'] }}</div>
                        <div class="prod-import-count-label">Updated</div>
                    </div>
                    <div class="prod-import-count is-warning">
                        <div class="prod-import-count-value">{{ $summary['skipped'] }}</div>
                        <div class="prod-import-count-label">Skipped</div>
                    </div>
                </div>
            </div>
        </div>

        @if (!empty($summary['errors']))
            <div class="card mb-5">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('products.import.errors_heading') }} ({{ count($summary['errors']) }})</div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="prod-import-errors">
                        @foreach ($summary['errors'] as $err)
                            <li>
                                <span class="prod-import-error-row">Row {{ $err['row'] }}</span>
                                <span>{{ $err['message'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('admin.products.import') }}"
               class="pos-btn pos-btn-sm pos-btn-ghost">
                {{ __('products.import.title') }}
            </a>
            {{-- Natural next step: attach the photos declared in the Image column. --}}
            <a href="{{ route('admin.products.bulk-images') }}"
               class="pos-btn pos-btn-sm pos-btn-ghost">
                <x-icon name="image" class="w-4 h-4" />
                {{ __('products.bulk_images.action') }}
            </a>
            <a href="{{ route('admin.products.index') }}"
               class="pos-btn pos-btn-sm pos-btn-primary">
                {{ __('products.import.done_back') }}
            </a>
        </div>
    </div>
</x-admin-layout>
