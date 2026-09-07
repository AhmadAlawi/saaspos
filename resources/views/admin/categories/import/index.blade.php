<x-admin-layout
    active="categories"
    :title="__('categories.import.title')"
    :crumbs="[
        ['label' => __('categories.crumb_parent')],
        ['label' => __('categories.title'), 'href' => route('admin.categories.index')],
        ['label' => __('categories.import.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.categories.index') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost"
                   aria-label="{{ __('categories.import.back') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('categories.import.title') }}</h1>
                    <p class="page-sub">{{ __('categories.import.sub') }}</p>
                </div>
            </div>
        </div>

        @if (session('error'))
            <x-alert type="danger" class="mb-4">{{ session('error') }}</x-alert>
        @endif

        {{-- Sample template + format guidance. --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="card-title">{{ __('categories.import.sample_title') }}</div>
                        <p class="card-title-sub">{{ __('categories.import.sample_sub') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <a href="{{ route('admin.categories.import.template', ['format' => 'csv']) }}"
                           class="pos-btn pos-btn-sm pos-btn-ghost">
                            <x-icon name="download" class="w-4 h-4" />
                            {{ __('categories.import.sample_csv') }}
                        </a>
                        <a href="{{ route('admin.categories.import.template', ['format' => 'xlsx']) }}"
                           class="pos-btn pos-btn-sm pos-btn-ghost">
                            <x-icon name="download" class="w-4 h-4" />
                            {{ __('categories.import.sample_xlsx') }}
                        </a>
                    </div>
                </div>

                <div class="import-guide">
                    <div class="import-guide-title">{{ __('categories.import.guide_title') }}</div>
                    <ul class="import-guide-list">
                        <li>{{ __('categories.import.guide_required') }}</li>
                        <li>{{ __('categories.import.guide_identity') }}</li>
                        <li>{{ __('categories.import.guide_parent') }}</li>
                        <li>{{ __('categories.import.guide_tax') }}</li>
                        <li>{{ __('categories.import.guide_color') }}</li>
                        <li>{{ __('categories.import.guide_bool') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST"
                      action="{{ route('admin.categories.import.preview') }}"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="form-stack">
                        <label class="field">
                            <span class="field-label is-required">{{ __('categories.import.upload_label') }}</span>
                            <x-admin.file-upload
                                name="file"
                                accept=".csv,.txt,.xlsx"
                                :max-size-kb="5120"
                                :hint="__('categories.import.upload_hint')" />
                            @error('file')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </label>

                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.categories.index') }}"
                               class="pos-btn pos-btn-sm pos-btn-ghost">
                                {{ __('categories.import.cancel') }}
                            </a>
                            <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                <x-icon name="upload" class="w-4 h-4" />
                                {{ __('categories.import.upload_button') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
