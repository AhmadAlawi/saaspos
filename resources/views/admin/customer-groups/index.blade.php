<x-admin-layout
    active="customer-groups"
    :title="__('customers.groups.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('customers.crumb_parent')],
        ['label' => __('customers.title'), 'href' => route('admin.customers.index')],
        ['label' => __('customers.groups.title')],
    ]">

    @php
        // Snapshot for the in-memory rowsById lookup — the FULL set (the editor
        // can open any group); only the rendered list is paged.
        $rowsForJs = $allRows->map(fn ($r) => [
            'id'                       => $r->id,
            'name'                     => $r->name,
            'default_discount_percent' => $r->default_discount_percent !== null ? (float) $r->default_discount_percent : null,
            'is_active'                => (bool) $r->is_active,
            'updated_at'               => $r->updated_at?->diffForHumans(),
        ])->values();

        if ($errors->any()) {
            $editingId = old('editing_id') ? (int) old('editing_id') : null;
            $initial = [
                'mode'       => $editingId ? 'edit' : 'new',
                'id'         => $editingId,
                'name'       => (string) old('name', ''),
                'discount'   => old('default_discount_percent'),
                'is_active'  => (bool) old('is_active', true),
            ];
        } elseif ($selected) {
            $initial = [
                'mode'       => 'edit',
                'id'         => $selected->id,
                'name'       => $selected->name,
                'discount'   => $selected->default_discount_percent,
                'is_active'  => (bool) $selected->is_active,
            ];
        } elseif ($isNew) {
            $initial = ['mode' => 'new', 'id' => null, 'name' => '', 'discount' => null, 'is_active' => true];
        } else {
            $initial = null;
        }
    @endphp

    <div class="page-wide"
         x-data="customerGroupsPage({
             endpoint:          '{{ route('admin.customer-groups.rows') }}',
             perPage:           {{ $perPage }},
             total:             {{ $total }},
             page:              1,
             totalPages:        {{ $totalPages }},
             storeUrl:          '{{ route('admin.customer-groups.store') }}',
             updateUrlTemplate: '{{ route('admin.customer-groups.update', ['customerGroup' => '__ID__']) }}',
             deleteUrlTemplate: '{{ route('admin.customer-groups.destroy', ['customerGroup' => '__ID__']) }}',
             rows:              {{ Js::from($rowsForJs) }},
             initial:           {{ Js::from($initial) }},
         })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('customers.groups.title') }}</h1>
                <p class="page-sub">{{ __('customers.groups.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('customers.groups.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.customer-groups.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('customers.groups.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.customer-groups.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('customers.groups.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('customers.groups.new') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            {{-- ── LEFT: list ──────────────────────────────────────────── --}}
            <div class="card card-pad-0">
                @if ($total === 0)
                    <div class="dt-empty">
                        <span class="dt-empty-icon"><x-icon name="customers" class="w-5 h-5" /></span>
                        <div class="dt-empty-title">{{ __('customers.groups.list_empty') }}</div>
                    </div>
                @else
                    <div class="dt-toolbar">
                        <div class="dt-toolbar-title">{{ __('customers.groups.list_title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)</div>
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
                                <th>{{ __('customers.groups.columns.name') }}</th>
                                <th class="num">{{ __('customers.groups.columns.discount') }}</th>
                                <th>{{ __('table.status') }}</th>
                                <th class="dt-actions-col"><span class="sr-only">{{ __('customers.columns.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="cg-list">
                            @include('admin.customer-groups._list', ['rows' => $rows])
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
                    <span class="rsn-editor-empty-icon"><x-icon name="customers" class="w-5 h-5" /></span>
                    <div class="rsn-editor-empty-title">{{ __('customers.groups.editor.empty_title') }}</div>
                    <div class="rsn-editor-empty-sub">{{ __('customers.groups.editor.empty_sub') }}</div>
                </div>

                <template x-if="!isIdle">
                    <div>
                        <div class="rsn-editor-head">
                            <div>
                                <div class="rsn-editor-name"
                                     x-text="isEdit ? (form.name || @js(__('customers.groups.editor.edit_title')))
                                                    : @js(__('customers.groups.editor.new_title'))"></div>
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
                                        <span>{{ __('customers.groups.editor.empty_sub') }}</span>
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
                                <label class="field" :class="{ 'has-error': fieldErrors.name }">
                                    <span class="field-label is-required">{{ __('customers.groups.fields.name') }}</span>
                                    <input type="text" name="name"
                                           x-model="form.name"
                                           @input="clearFieldError('name')"
                                           class="pos-input" required maxlength="100">
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.default_discount_percent }">
                                    <span class="field-label">{{ __('customers.groups.fields.discount') }}</span>
                                    <input type="number" step="0.01" min="0" max="100"
                                           name="default_discount_percent"
                                           x-model="form.discount"
                                           @input="clearFieldError('default_discount_percent')"
                                           class="pos-input tnum">
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    <span>{{ __('customers.groups.fields.is_active') }}</span>
                                </label>
                            </div>

                            <div class="rsn-editor-footer">
                                <template x-if="isEdit">
                                    <button type="button"
                                            @click="confirmDelete()"
                                            :disabled="submitting"
                                            class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('customers.actions.delete') }}
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
                                        {{ __('customers.actions.discard') }}
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
                                                ? (isEdit ? 'Saving…' : 'Creating…')
                                                : (isEdit ? @js(__('customers.actions.save')) : @js(__('customers.actions.create')))
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
