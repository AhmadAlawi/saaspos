<x-admin-layout
    active="units"
    :title="__('units.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('units.crumb_parent'), 'href' => '/admin/products'],
        ['label' => __('units.title')],
    ]">

    @php
        // Full set for the editor's rowsById + the conversion-name map (a page-2
        // unit can convert to a page-1 base unit); only the list is paged.
        $unitsForJs = $allUnits->map(fn ($u) => [
            'id'                => $u->id,
            'code'              => $u->code,
            'name'              => $u->name,
            'category'          => $u->category,
            'base_unit_id'      => $u->base_unit_id,
            'conversion_factor' => $u->conversion_factor !== null ? (string) $u->conversion_factor : null,
            'is_active'         => (bool) $u->is_active,
            'updated_at'        => $u->updated_at?->diffForHumans(),
        ])->values();

        $baseUnitNames = $allUnits->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->code.')']);

        if ($errors->any()) {
            $editingId = old('editing_id') ? (int) old('editing_id') : null;
            $initial = [
                'mode'              => $editingId ? 'edit' : 'new',
                'id'                => $editingId,
                'code'              => (string) old('code', ''),
                'name'              => (string) old('name', ''),
                'category'          => (string) old('category', $categories->first()?->slug ?? 'count'),
                'base_unit_id'      => old('base_unit_id', '') === null ? '' : (string) old('base_unit_id', ''),
                'conversion_factor' => (string) old('conversion_factor', ''),
                'is_active'         => (bool) old('is_active', false),
            ];
        } elseif ($selected) {
            $initial = [
                'mode'              => 'edit',
                'id'                => $selected->id,
                'code'              => $selected->code,
                'name'              => $selected->name,
                'category'          => $selected->category,
                'base_unit_id'      => $selected->base_unit_id ? (string) $selected->base_unit_id : '',
                'conversion_factor' => $selected->conversion_factor !== null ? (string) $selected->conversion_factor : '',
                'is_active'         => (bool) $selected->is_active,
            ];
        } elseif ($isNew) {
            $initial = [
                'mode'              => 'new',
                'id'                => null,
                'code'              => '',
                'name'              => '',
                'category'          => $categories->first()?->slug ?? 'count',
                'base_unit_id'      => '',
                'conversion_factor' => '',
                'is_active'         => true,
            ];
        } else {
            $initial = null;
        }
    @endphp

    <div class="page-wide"
         x-data="unitsPage({
             endpoint:          '{{ route('admin.units.rows') }}',
             perPage:           {{ $perPage }},
             total:             {{ $total }},
             page:              1,
             totalPages:        {{ $totalPages }},
             storeUrl:          '{{ route('admin.units.store') }}',
             updateUrlTemplate: '{{ route('admin.units.update', ['unit' => '__ID__']) }}',
             deleteUrlTemplate:          '{{ route('admin.units.destroy', ['unit' => '__ID__']) }}',
             deactivateCheckUrlTemplate: '{{ route('admin.units.deactivate-check', ['unit' => '__ID__']) }}',
             rows:              {{ Js::from($unitsForJs) }},
             initial:           {{ Js::from($initial) }},
             defaultCategory:   '{{ $categories->first()?->slug ?? 'count' }}',
         })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('units.title') }}</h1>
                <p class="page-sub">{{ __('units.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('units.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.units.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('units.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.units.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('units.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('admin.units.categories.index') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="archive" class="w-4 h-4" />
                    {{ __('units.actions.manage_categories') }}
                </a>

                <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('units.actions.new') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            @if ($total === 0)
                <div class="card card-pad-0">
                    <div class="unit-empty">
                        <span class="unit-empty-icon"><x-icon name="archive" class="w-5 h-5" /></span>
                        <div class="unit-empty-title">{{ __('units.list.empty_title') }}</div>
                        <div class="unit-empty-sub">{{ __('units.list.empty_sub') }}</div>
                    </div>
                </div>
            @else
                <x-admin.data-table>
                    <x-slot:toolbarStart>
                        <div class="dt-toolbar-title">
                            {{ __('units.list.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                        </div>
                    </x-slot:toolbarStart>

                    <ul class="unit-list">
                        @include('admin.units._list', [
                            'units'         => $units->load('baseUnit:id,code,name'),
                            'baseUnitNames' => $baseUnitNames,
                        ])
                    </ul>
                </x-admin.data-table>
            @endif

            <div class="card unit-editor">
                <div class="unit-editor-empty" x-show="isIdle" x-cloak>
                    <span class="unit-editor-empty-icon"><x-icon name="archive" class="w-5 h-5" /></span>
                    <div class="unit-editor-empty-title">{{ __('units.editor.empty_title') }}</div>
                    <div class="unit-editor-empty-sub">{{ __('units.editor.empty_sub') }}</div>
                </div>

                <template x-if="!isIdle">
                    <div>
                        <div class="unit-editor-head">
                            <span class="unit-tile unit-tile-lg" aria-hidden="true"
                                  x-text="form.code || '—'"></span>
                            <div>
                                <div class="unit-editor-name"
                                     x-text="isEdit ? form.name || @js(__('units.drawer.edit_title'))
                                                    : @js(__('units.drawer.new_title'))"></div>
                                <div class="unit-editor-id">
                                    <template x-if="isEdit">
                                        <span>
                                            {{ __('units.drawer.edit_id') }}
                                            <span class="mono" x-text="form.id"></span>
                                            <template x-if="rowMeta?.updated_at">
                                                <span>
                                                    · {{ __('units.drawer.edit_updated') }}
                                                    <span x-text="rowMeta.updated_at"></span>
                                                </span>
                                            </template>
                                        </span>
                                    </template>
                                    <template x-if="isNew">
                                        <span>{{ __('units.drawer.new_sub') }}</span>
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
                                {{-- Stacked, not 2-column — the 360px editor + the long
                                     "MEASUREMENT CATEGORY" label can't fit side-by-side
                                     with "SHORT CODE" without wrapping and staggering
                                     the input baseline. --}}
                                <label class="field">
                                    <span class="field-label is-required">{{ __('units.drawer.code') }}</span>
                                    <input type="text"
                                           name="code"
                                           x-model="form.code"
                                           maxlength="16"
                                           class="pos-input"
                                           placeholder="{{ __('units.drawer.code_placeholder') }}">
                                </label>

                                <label class="field">
                                    <span class="field-label is-required">{{ __('units.drawer.category') }}</span>
                                    {{-- `x-effect` pushes form.category INTO TomSelect whenever it
                                         changes. Without it, Alpine's x-model updates the underlying
                                         native <select> but TomSelect's visible chip stays on the
                                         old value — so switching to another unit row would show
                                         the previous unit's category.

                                         The `true` second arg is "silent" — it skips TomSelect's
                                         onChange callback, which would write back through x-model
                                         and create a reactive loop. --}}
                                    <select x-data="enhancedSelect()"
                                            x-effect="ts && ts.setValue(form.category, true)"
                                            name="category"
                                            x-model="form.category"
                                            class="pos-input">
                                        @foreach ($categories as $cat)
                                            <option value="{{ $cat->slug }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="field-label is-required">{{ __('units.drawer.name') }}</span>
                                    <input type="text"
                                           name="name"
                                           x-model="form.name"
                                           maxlength="64"
                                           class="pos-input"
                                           placeholder="{{ __('units.drawer.name_placeholder') }}">
                                </label>

                                <label class="field">
                                    <span class="field-label">{{ __('units.drawer.base_unit') }}</span>
                                    <select x-data="enhancedSelect()"
                                            x-effect="syncBaseSelect(ts)"
                                            name="base_unit_id"
                                            x-model="form.base_unit_id"
                                            class="pos-input">
                                        <option value="">{{ __('units.drawer.base_unit_none') }}</option>
                                        @foreach ($units as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->code }})</option>
                                        @endforeach
                                    </select>
                                    <p class="field-help">{{ __('units.drawer.base_unit_hint') }}</p>
                                </label>

                                <label class="field" x-show="!isBase" x-cloak>
                                    <span class="field-label is-required">{{ __('units.drawer.conversion_factor') }}</span>
                                    <input type="number"
                                           step="any"
                                           min="0"
                                           name="conversion_factor"
                                           x-model="form.conversion_factor"
                                           class="pos-input"
                                           placeholder="0">
                                    <p class="field-help">{{ __('units.drawer.conversion_factor_hint') }}</p>
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    <span>{{ __('units.drawer.is_active') }}</span>
                                </label>
                            </div>

                            <div class="unit-editor-actions">
                                <template x-if="isEdit">
                                    <button type="button"
                                            class="pos-btn pos-btn-sm pos-btn-danger"
                                            :disabled="submitting"
                                            @click="confirmDelete({{ \Illuminate\Support\Js::from(__('units.actions.delete_confirm')) }})">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('units.actions.delete') }}
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
                                        {{ __('units.actions.discard') }}
                                    </button>
                                    <button type="submit"
                                            class="pos-btn pos-btn-sm pos-btn-primary"
                                            :disabled="submitting">
                                        <svg x-show="submitting" x-cloak
                                             class="h-4 w-4 animate-spin"
                                             viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor"
                                                  d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                        </svg>
                                        <span x-text="
                                            submitting
                                                ? (isEdit ? @js(__('units.actions_extra.saving'))
                                                          : @js(__('units.actions_extra.creating')))
                                                : (isEdit ? @js(__('units.actions.save'))
                                                          : @js(__('units.actions.create')))
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
