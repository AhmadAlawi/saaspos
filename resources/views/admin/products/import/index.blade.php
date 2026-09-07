<x-admin-layout
    active="products"
    :title="__('products.import.title')"
    :crumbs="[
        ['label' => __('products.crumb_parent')],
        ['label' => __('products.title'), 'href' => route('admin.products.index')],
        ['label' => __('products.import.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.products.index') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost"
                   aria-label="{{ __('products.actions.back_to_list') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('products.import.title') }}</h1>
                    <p class="page-sub">{{ __('products.import.sub') }}</p>
                </div>
            </div>
        </div>

        @if (session('error'))
            <div class="pos-alert pos-alert-error mb-4">{{ session('error') }}</div>
        @endif

        {{-- Sample template + format guidance — gives the customer a
             ready-to-fill file so they never have to guess the columns. --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="card-title">{{ __('products.import.sample_title') }}</div>
                        <p class="card-title-sub">{{ __('products.import.sample_sub') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <a href="{{ route('admin.products.import.template', ['format' => 'csv']) }}"
                           class="pos-btn pos-btn-sm pos-btn-ghost">
                            <x-icon name="download" class="w-4 h-4" />
                            {{ __('products.import.sample_csv') }}
                        </a>
                        <a href="{{ route('admin.products.import.template', ['format' => 'xlsx']) }}"
                           class="pos-btn pos-btn-sm pos-btn-ghost">
                            <x-icon name="download" class="w-4 h-4" />
                            {{ __('products.import.sample_xlsx') }}
                        </a>
                    </div>
                </div>

                <div class="import-guide">
                    <div class="import-guide-title">{{ __('products.import.guide_title') }}</div>
                    <ul class="import-guide-list">
                        <li>{{ __('products.import.guide_required') }}</li>
                        <li>{{ __('products.import.guide_match') }}</li>
                        <li>{{ __('products.import.guide_sku') }}</li>
                        <li>{{ __('products.import.guide_bool') }}</li>
                        <li>{{ __('products.import.guide_money') }}</li>
                        <li>{{ __('products.import.guide_extra') }}</li>
                        <li>{{ __('products.import.guide_image') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST"
                      action="{{ route('admin.products.import.preview') }}"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="form-stack">
                        <label class="field">
                            <span class="field-label is-required">{{ __('products.import.upload_label') }}</span>
                            <x-admin.file-upload
                                name="file"
                                accept=".csv,.txt,.xlsx"
                                :max-size-kb="5120"
                                :hint="__('products.import.upload_hint')" />
                            @error('file')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </label>

                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.products.index') }}"
                               class="pos-btn pos-btn-sm pos-btn-ghost">
                                {{ __('products.import.cancel') }}
                            </a>
                            <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                <x-icon name="upload" class="w-4 h-4" />
                                {{ __('products.import.upload_button') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
