<x-admin-layout
    active="products"
    :title="__('products.bulk_images.title')"
    :crumbs="[
        ['label' => __('products.crumb_parent')],
        ['label' => __('products.title'), 'href' => route('admin.products.index')],
        ['label' => __('products.bulk_images.title')],
    ]">

    {{-- Match declared filenames → products, then upload matched files in
         small batches. Behaviour in resources/js/admin/bulk-product-images.js. --}}
    <div class="page-wide"
         x-data="bulkProductImages({{ \Illuminate\Support\Js::from([
            'matchUrl'  => route('admin.products.bulk-images.match'),
            'storeUrl'  => route('admin.products.bulk-images.store'),
            'maxKb'     => $maxKb,
            'maxBatch'  => $maxBatch,
            'accept'    => $acceptMimes,
            'labels'    => [
                'summary'        => __('products.bulk_images.summary'),
                'matched_title'  => __('products.bulk_images.matched_title'),
                'unmatched_title'=> __('products.bulk_images.unmatched_title'),
                'upload_button'  => __('products.bulk_images.upload_button'),
                'uploading'      => __('products.bulk_images.uploading'),
                'done_summary'   => __('products.bulk_images.done_summary'),
                'reason_no_match'=> __('products.bulk_images.reason_no_match'),
                'reason_duplicate' => __('products.bulk_images.reason_duplicate'),
                'error_generic'  => __('products.bulk_images.error_generic'),
            ],
         ]) }})">

        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.products.index') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost"
                   aria-label="{{ __('products.actions.back_to_list') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('products.bulk_images.title') }}</h1>
                    <p class="page-sub">{{ __('products.bulk_images.sub') }}</p>
                </div>
            </div>
        </div>

        {{-- How it works --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title">{{ __('products.bulk_images.how_title') }}</div>
                <ol class="import-guide-list mt-2">
                    <li>{{ __('products.bulk_images.how_1') }}</li>
                    <li>{{ __('products.bulk_images.how_2') }}</li>
                    <li>{{ __('products.bulk_images.how_3') }}</li>
                </ol>

                @if ($pendingCount > 0)
                    <p class="page-sub mt-3">
                        {{ __('products.bulk_images.pending_hint', ['count' => number_format($pendingCount)]) }}
                    </p>
                @else
                    <p class="page-sub mt-3">{{ __('products.bulk_images.no_pending') }}</p>
                @endif
            </div>
        </div>

        {{-- Drop zone --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="bulk-img-drop"
                     :class="{ 'is-dragover': dragOver }"
                     @dragover.prevent="dragOver = true"
                     @dragleave.prevent="dragOver = false"
                     @drop.prevent="onDrop($event)"
                     @click="$refs.picker.click()"
                     role="button"
                     tabindex="0"
                     @keydown.enter="$refs.picker.click()"
                     @keydown.space.prevent="$refs.picker.click()">
                    <input type="file"
                           x-ref="picker"
                           class="sr-only"
                           multiple
                           :accept="acceptAttr"
                           @change="onPick($event)">
                    <span class="bulk-img-drop-icon"><x-icon name="image" class="w-6 h-6" /></span>
                    <div class="bulk-img-drop-title">{{ __('products.bulk_images.drop_title') }}</div>
                    <div class="bulk-img-drop-hint">{{ __('products.bulk_images.drop_hint', ['size' => $maxKb / 1024]) }}</div>
                </div>
            </div>
        </div>

        {{-- Matching spinner --}}
        <div class="card mb-4" x-show="phase === 'matching'" x-cloak>
            <div class="card-body flex items-center gap-3">
                <span class="inline-flex animate-spin"><x-icon name="refresh" class="w-4 h-4" /></span>
                <span>{{ __('products.bulk_images.matching') }}</span>
            </div>
        </div>

        {{-- Nothing matched --}}
        <template x-if="phase === 'ready' && matched.length === 0">
            <x-alert type="warning" class="mb-4">{{ __('products.bulk_images.nothing_matched') }}</x-alert>
        </template>

        {{-- Results / preview --}}
        <div class="card mb-4" x-show="phase === 'ready' || phase === 'uploading' || phase === 'done'" x-cloak>
            <div class="card-body">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <div class="card-title"
                         x-text="summaryText"></div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                class="pos-btn pos-btn-sm pos-btn-ghost"
                                x-show="unmatched.length > 0"
                                @click="downloadUnmatched()">
                            <x-icon name="download" class="w-4 h-4" />
                            {{ __('products.bulk_images.unmatched_download') }}
                        </button>
                        <button type="button"
                                class="pos-btn pos-btn-sm pos-btn-ghost"
                                @click="reset()">
                            {{ __('products.bulk_images.clear') }}
                        </button>
                    </div>
                </div>

                {{-- Matched list --}}
                <div x-show="matched.length > 0">
                    <div class="bulk-img-section-title"
                         x-text="matchedTitle"></div>
                    <ul class="bulk-img-list">
                        <template x-for="row in matched" :key="row.product_id">
                            <li class="bulk-img-row" :class="'is-' + row.status">
                                <span class="bulk-img-thumb">
                                    <template x-if="row.image_url">
                                        <img :src="row.image_url" alt="">
                                    </template>
                                    <template x-if="!row.image_url">
                                        <x-icon name="image" class="w-4 h-4" />
                                    </template>
                                </span>
                                <span class="bulk-img-meta">
                                    <span class="bulk-img-name" x-text="row.name"></span>
                                    <span class="bulk-img-sub">
                                        <span class="mono" x-text="row.sku"></span>
                                        <span>·</span>
                                        <span x-text="row.filename"></span>
                                        <span class="bulk-img-replace"
                                              x-show="row.has_image && row.status === 'ready'"
                                              x-cloak>{{ __('products.bulk_images.has_image_note') }}</span>
                                    </span>
                                </span>
                                <span class="bulk-img-status">
                                    <span x-show="row.status === 'uploading'" class="inline-flex animate-spin"><x-icon name="refresh" class="w-3.5 h-3.5" /></span>
                                    <span x-show="row.status === 'done'" class="bulk-img-ok"><x-icon name="check" class="w-4 h-4" /></span>
                                    <span x-show="row.status === 'error'" class="bulk-img-err" :title="row.error"><x-icon name="x" class="w-4 h-4" /></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Unmatched list --}}
                <div x-show="unmatched.length > 0" class="mt-4">
                    <div class="bulk-img-section-title" x-text="unmatchedTitle"></div>
                    <ul class="bulk-img-list bulk-img-list--muted">
                        <template x-for="row in unmatched" :key="row.filename">
                            <li class="bulk-img-row is-unmatched">
                                <span class="bulk-img-thumb"><x-icon name="image" class="w-4 h-4" /></span>
                                <span class="bulk-img-meta">
                                    <span class="bulk-img-name" x-text="row.filename"></span>
                                    <span class="bulk-img-sub" x-text="reasonLabel(row.reason)"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Upload action / progress --}}
                <div class="flex items-center justify-end gap-3 mt-4"
                     x-show="matched.length > 0">
                    <span class="page-sub" x-show="phase === 'uploading'" x-cloak x-text="uploadingText"></span>
                    <span class="page-sub bulk-img-done-text" x-show="phase === 'done'" x-cloak x-text="doneText"></span>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-primary"
                            x-show="phase !== 'done'"
                            :disabled="phase === 'uploading'"
                            @click="upload()">
                        <x-icon name="upload" class="w-4 h-4" />
                        <span x-text="uploadButtonText"></span>
                    </button>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            x-show="phase === 'done'"
                            x-cloak
                            @click="reset()">
                        {{ __('products.bulk_images.start_over') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
