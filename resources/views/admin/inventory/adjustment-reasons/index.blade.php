<x-admin-layout
    active="adjustment-reasons"
    :title="__('inventory.reasons.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('inventory.nav.section')],
        ['label' => __('inventory.reasons.title')],
    ]">

    @php
        // In-memory row snapshot — the editor reads from this (the FULL set, so
        // it can open any reason). The server keeps it fresh on every response.
        $rowsForJs = $allRows->map(fn ($r) => [
            'id'         => $r->id,
            'code'       => $r->code,
            'name'       => $r->name,
            'sort_order' => (int) $r->sort_order,
            'is_active'  => (bool) $r->is_active,
            'updated_at' => $r->updated_at?->diffForHumans(),
        ])->values();

        // Editor state on initial load:
        //   - validation error → reopen on the broken form (`old()` input)
        //   - `?selected=X`    → open that row in edit mode
        //   - `?new=1`         → open a blank new-row form
        //   - otherwise        → idle (empty "Select a reason" card)
        if ($errors->any()) {
            $editingId = old('editing_id') ? (int) old('editing_id') : null;
            $initial = [
                'mode'       => $editingId ? 'edit' : 'new',
                'id'         => $editingId,
                'code'       => (string) old('code', ''),
                'name'       => (string) old('name', ''),
                'sort_order' => (int) old('sort_order', 0),
                'is_active'  => (bool) old('is_active', true),
            ];
        } elseif ($selected) {
            $initial = [
                'mode'       => 'edit',
                'id'         => $selected->id,
                'code'       => $selected->code,
                'name'       => $selected->name,
                'sort_order' => (int) $selected->sort_order,
                'is_active'  => (bool) $selected->is_active,
            ];
        } elseif ($isNew) {
            $initial = ['mode' => 'new', 'id' => null, 'code' => '', 'name' => '', 'sort_order' => 0, 'is_active' => true];
        } else {
            $initial = null;
        }
    @endphp

    <div class="page-wide"
         x-data="reasonsPage({
             endpoint:          '{{ route('admin.inventory.adjustment-reasons.rows') }}',
             perPage:           {{ $perPage }},
             total:             {{ $total }},
             page:              1,
             totalPages:        {{ $totalPages }},
             storeUrl:          '{{ route('admin.inventory.adjustment-reasons.store') }}',
             updateUrlTemplate: '{{ route('admin.inventory.adjustment-reasons.update', ['stockAdjustmentReason' => '__ID__']) }}',
             deleteUrlTemplate: '{{ route('admin.inventory.adjustment-reasons.destroy', ['stockAdjustmentReason' => '__ID__']) }}',
             rows:              {{ Js::from($rowsForJs) }},
             initial:           {{ Js::from($initial) }},
         })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('inventory.reasons.title') }}</h1>
                <p class="page-sub">{{ __('inventory.reasons.sub') }}</p>
            </div>
            <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                {{ __('inventory.reasons.new') }}
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            {{-- ── LEFT: list ──────────────────────────────────────────── --}}
            <div class="card card-pad-0">
                @if ($total === 0)
                    <div class="dt-empty">
                        <span class="dt-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                        <div class="dt-empty-title">{{ __('inventory.reasons.list.empty') }}</div>
                    </div>
                @else
                    <div class="dt-toolbar">
                        <div class="dt-toolbar-title">{{ __('inventory.reasons.list.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)</div>
                        {{-- Own search input (no `.debounce` — the mixin debounces the fetch). --}}
                        <div class="dt-search">
                            <span class="dt-search-icon"><x-icon name="search" class="w-4 h-4" /></span>
                            <input type="search" x-model="search"
                                   placeholder="{{ __('table.search_placeholder') }}"
                                   aria-label="{{ __('table.search_placeholder') }}">
                            <button type="button" class="dt-search-clear" x-show="isFiltered" x-cloak
                                    @click="clearSearch()" aria-label="{{ __('table.search_clear') }}">
                                <x-icon name="x" class="w-3.5 h-3.5" />
                            </button>
                        </div>
                        <x-admin.dt-toolbar-actions />
                    </div>
                    <div class="dt-scroll">
                    <table class="dt-table">
                        <thead>
                            <tr>
                                <th>{{ __('inventory.reasons.columns.code') }}</th>
                                <th>{{ __('inventory.reasons.columns.name') }}</th>
                                <th>{{ __('inventory.reasons.columns.sort_order') }}</th>
                                <th>{{ __('table.status') }}</th>
                                <th class="dt-actions-col"><span class="sr-only">{{ __('inventory.reasons.columns.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="rsn-list">
                            @include('admin.inventory.adjustment-reasons._list', ['rows' => $rows])
                        </tbody>
                    </table>
                    </div>
                    <x-admin.dt-pager />
                @endif
            </div>

            {{-- ── RIGHT: sticky editor ───────────────────────────────── --}}
            <div class="card rsn-editor">
                {{-- Idle: nothing selected. --}}
                <div class="rsn-editor-empty" x-show="isIdle" x-cloak>
                    <span class="rsn-editor-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                    <div class="rsn-editor-empty-title">{{ __('inventory.reasons.editor.empty_title') }}</div>
                    <div class="rsn-editor-empty-sub">{{ __('inventory.reasons.editor.empty_sub') }}</div>
                </div>

                {{-- Edit / new: one form, mode-aware via Alpine. --}}
                <template x-if="!isIdle">
                    <div>
                        <div class="rsn-editor-head">
                            <div>
                                <div class="rsn-editor-name"
                                     x-text="isEdit ? (form.name || @js(__('inventory.reasons.editor.edit_title')))
                                                    : @js(__('inventory.reasons.editor.new_title'))"></div>
                                <div class="rsn-editor-id">
                                    <template x-if="isEdit">
                                        <span>
                                            {{ __('inventory.reasons.editor.edit_id') }}
                                            <span class="mono" x-text="form.id"></span>
                                            <template x-if="rowMeta?.updated_at">
                                                <span> · {{ __('inventory.reasons.editor.edit_updated') }} <span x-text="rowMeta.updated_at"></span></span>
                                            </template>
                                        </span>
                                    </template>
                                    <template x-if="isNew">
                                        <span>{{ __('inventory.reasons.editor.new_sub') }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <form method="POST"
                              :action="formAction"
                              class="card-body"
                              novalidate
                              @submit.prevent="submitEditor($event)">
                            @csrf
                            <input type="hidden" name="_method" :value="isEdit ? 'PATCH' : 'POST'">
                            <input type="hidden" name="editing_id" :value="form.id || ''">

                            <div class="form-stack">
                                <label class="field" :class="{ 'has-error': fieldErrors.code }">
                                    <span class="field-label is-required">{{ __('inventory.reasons.fields.code') }}</span>
                                    <input type="text" name="code"
                                           x-model="form.code"
                                           @input="clearFieldError('code')"
                                           class="pos-input mono" required maxlength="32">
                                    <p class="field-help">{{ __('inventory.reasons.fields.code_help') }}</p>
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.name }">
                                    <span class="field-label is-required">{{ __('inventory.reasons.fields.name') }}</span>
                                    <input type="text" name="name"
                                           x-model="form.name"
                                           @input="clearFieldError('name')"
                                           class="pos-input" required maxlength="100">
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.sort_order }">
                                    <span class="field-label">{{ __('inventory.reasons.fields.sort_order') }}</span>
                                    <input type="number" name="sort_order"
                                           x-model.number="form.sort_order"
                                           @input="clearFieldError('sort_order')"
                                           class="pos-input" min="0" max="9999">
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    <span>{{ __('inventory.reasons.fields.is_active') }}</span>
                                </label>
                            </div>

                            <div class="rsn-editor-footer">
                                <template x-if="isEdit">
                                    <button type="button"
                                            @click="confirmDelete()"
                                            :disabled="submitting"
                                            class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('inventory.reasons.actions.delete') }}
                                    </button>
                                </template>
                                <template x-if="isNew">
                                    <span></span>
                                </template>

                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            @click="closeEditor()"
                                            :disabled="submitting"
                                            class="pos-btn pos-btn-sm pos-btn-ghost">
                                        {{ __('inventory.reasons.actions.discard') }}
                                    </button>
                                    <button type="submit"
                                            class="pos-btn pos-btn-sm pos-btn-primary"
                                            :disabled="submitting">
                                        <svg x-show="submitting" x-cloak
                                             class="h-4 w-4 animate-spin"
                                             viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                        </svg>
                                        <span x-text="
                                            submitting
                                                ? (isEdit ? @js(__('inventory.reasons.actions.saving'))
                                                          : @js(__('inventory.reasons.actions.creating')))
                                                : (isEdit ? @js(__('inventory.reasons.actions.save'))
                                                          : @js(__('inventory.reasons.actions.create')))
                                        "></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-admin-layout>
