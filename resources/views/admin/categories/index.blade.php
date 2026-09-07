<x-admin-layout
    active="categories"
    :title="__('categories.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('categories.crumb_parent'), 'href' => '/admin/products'],
        ['label' => __('categories.title')],
    ]">

    @php
        // Compact JSON snapshot of every row — feeds the in-memory lookup
        // the editor reads from when a row is clicked.
        $rowsForJs = $categories->map(fn ($c) => [
            'id'             => $c->id,
            'name'           => $c->name,
            'parent_id'      => $c->parent_id,
            'color'          => $c->color,
            'tax_group_id'   => $c->tax_group_id,
            'is_active'      => (bool) $c->is_active,
            'is_default'     => (bool) $c->is_default,
            'products_count' => (int) ($c->products_count ?? 0),
            'updated_at'     => $c->updated_at?->diffForHumans(),
        ])->values();

        // Default color for new rows — first option in the palette.
        $defaultColor = $colorChoices[0] ?? '#F97316';

        // The editor's initial state on page load:
        //   - validation error  → reopen on the broken form (`old()` input)
        //   - `?selected=X` URL → open that row in edit mode
        //   - `?new=1` URL      → open a blank new-row form
        //   - otherwise         → idle (empty "Select a category" card)
        if ($errors->any()) {
            $editingId = old('editing_id') ? (int) old('editing_id') : null;
            $initial = [
                'mode'         => $editingId ? 'edit' : 'new',
                'id'           => $editingId,
                'name'         => (string) old('name', ''),
                'parent_id'    => old('parent_id', '') === null ? '' : (string) old('parent_id', ''),
                'color'        => (string) old('color', $defaultColor),
                'tax_group_id' => old('tax_group_id', '') === null ? '' : (string) old('tax_group_id', ''),
                'is_active'    => (bool) old('is_active', false),
            ];
        } elseif ($selected) {
            $initial = [
                'mode'         => 'edit',
                'id'           => $selected->id,
                'name'         => $selected->name,
                'parent_id'    => $selected->parent_id ? (string) $selected->parent_id : '',
                'color'        => $selected->color ?: $defaultColor,
                'tax_group_id' => $selected->tax_group_id ? (string) $selected->tax_group_id : '',
                'is_active'    => (bool) $selected->is_active,
            ];
        } elseif ($isNew) {
            $initial = ['mode' => 'new', 'id' => null, 'name' => '', 'parent_id' => '', 'color' => $defaultColor, 'tax_group_id' => '', 'is_active' => true];
        } else {
            $initial = null;
        }
    @endphp

    <div class="page-wide"
         x-data="categoriesPage({
             storeUrl:             '{{ route('admin.categories.store') }}',
             updateUrlTemplate:    '{{ route('admin.categories.update', ['category' => '__ID__']) }}',
             deleteUrlTemplate:    '{{ route('admin.categories.destroy', ['category' => '__ID__']) }}',
             deleteInfoUrlTemplate:    '{{ route('admin.categories.delete-info', ['category' => '__ID__']) }}',
             deactivateCheckUrlTemplate: '{{ route('admin.categories.deactivate-check', ['category' => '__ID__']) }}',
             searchReplacementUrl: '{{ route('admin.categories.search-replacement') }}',
             reorderUrl:           '{{ route('admin.categories.reorder') }}',
             rows:                 {{ Js::from($rowsForJs) }},
             initial:              {{ Js::from($initial) }},
             defaultColor:         '{{ $defaultColor }}',
         })">

        {{-- Page header --}}
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('categories.title') }}</h1>
                <p class="page-sub">{{ __('categories.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                {{-- Table / Tree view toggle --}}
                <div class="seg" role="tablist" aria-label="{{ __('categories.view.aria') }}">
                    <button type="button"
                            role="tab"
                            :aria-selected="isTable"
                            :class="{ 'is-active': isTable }"
                            @click="view = 'table'">
                        <x-icon name="list" class="w-4 h-4" />
                        {{ __('categories.view.table') }}
                    </button>
                    <button type="button"
                            role="tab"
                            :aria-selected="isTree"
                            :class="{ 'is-active': isTree }"
                            @click="view = 'tree'">
                        <x-icon name="tree" class="w-4 h-4" />
                        {{ __('categories.view.tree') }}
                    </button>
                </div>

                {{-- Import — opens the CSV / XLSX upload wizard. --}}
                <a href="{{ route('admin.categories.import') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="upload" class="w-4 h-4" />
                    {{ __('categories.actions.import') }}
                </a>

                {{-- Export — small dropdown that links to the controller's
                     `export` action with format=csv or xlsx. Reuses the
                     shared `dropdown` Alpine factory for open/close. --}}
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('categories.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.categories.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('categories.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.categories.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('categories.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('categories.actions.new') }}
                </button>
            </div>
        </div>

        {{-- Flash + validation messages surface as toasts now (admin-layout). --}}

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            {{-- ── LEFT: list card ──────────────────────────────────── --}}
            @if ($categories->isEmpty())
                {{-- Truly empty state (no rows in the DB yet). The data-table
                     component handles the "search returns nothing" state itself. --}}
                <div class="card card-pad-0">
                    <div class="cat-empty">
                        <span class="cat-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                        <div class="cat-empty-title">{{ __('categories.list.empty_title') }}</div>
                        <div class="cat-empty-sub">{{ __('categories.list.empty_sub') }}</div>
                    </div>
                </div>
            @else
                <x-admin.data-table>
                    <x-slot:toolbarStart>
                        <div class="dt-toolbar-title">
                            {{ __('categories.list.title') }} (<span x-text="rowCount">{{ $categories->count() }}</span>)
                        </div>
                    </x-slot:toolbarStart>

                    <x-slot:toolbarInfo>
                        <div class="dt-toolbar-info" x-show="_canDrag">
                            {{ __('categories.list.drag_hint') }}
                        </div>
                    </x-slot:toolbarInfo>

                    <ul class="cat-list"
                        :class="{ 'dt-frozen': !isPristine && isTable, 'is-tree': isTree }">
                        @include('admin.categories._list', [
                            'categories'    => $categories,
                            'taxGroupNames' => $taxGroupNames,
                        ])
                    </ul>
                </x-admin.data-table>
            @endif

            {{-- ── RIGHT: sticky editor ─────────────────────────────── --}}
            <div class="card cat-editor">
                {{-- Idle: nothing selected. --}}
                <div class="cat-editor-empty" x-show="isIdle" x-cloak>
                    <span class="cat-editor-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                    <div class="cat-editor-empty-title">{{ __('categories.editor.empty_title') }}</div>
                    <div class="cat-editor-empty-sub">{{ __('categories.editor.empty_sub') }}</div>
                </div>

                {{-- Edit / new: one form, mode-aware via Alpine. --}}
                <template x-if="!isIdle">
                    <div>
                        <div class="cat-editor-head">
                            <span class="cat-tile cat-tile-lg"
                                  aria-hidden="true"
                                  :style="form.color ? `--cat-color: ${form.color}` : ''">
                                <x-icon name="tag" class="w-5 h-5" />
                            </span>
                            <div>
                                <div class="cat-editor-name"
                                     x-text="isEdit ? form.name || @js(__('categories.drawer.edit_title'))
                                                    : @js(__('categories.drawer.new_title'))"></div>
                                <div class="cat-editor-id">
                                    <template x-if="isEdit">
                                        <span>
                                            {{ __('categories.drawer.edit_id') }}
                                            <span class="mono" x-text="form.id"></span>
                                            <template x-if="rowMeta?.updated_at">
                                                <span>
                                                    · {{ __('categories.drawer.edit_updated') }}
                                                    <span x-text="rowMeta.updated_at"></span>
                                                </span>
                                            </template>
                                        </span>
                                    </template>
                                    <template x-if="isNew">
                                        <span>{{ __('categories.drawer.new_sub') }}</span>
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
                                {{-- Name --}}
                                <label class="field">
                                    <span class="field-label is-required">{{ __('categories.drawer.name') }}</span>
                                    <input type="text"
                                           name="name"
                                           x-model="form.name"
                                           class="pos-input"
                                           placeholder="{{ __('categories.drawer.name_placeholder') }}">
                                </label>

                                {{-- Parent — every category is a candidate at any depth.
                                     Options are visually indented by tree_depth; cyclic targets
                                     (self + descendants) are disabled via `syncParentDisabled`
                                     which is called on every reactive change of the edited row.
                                     Searchable via TomSelect; only 20 options visible at once,
                                     the rest appear when the user filters. --}}
                                <label class="field">
                                    <span class="field-label">{{ __('categories.drawer.parent') }}</span>
                                    <select x-data="enhancedSelect()"
                                            x-effect="syncParentDisabled(ts)"
                                            name="parent_id"
                                            x-model="form.parent_id"
                                            class="pos-input">
                                        <option value="">{{ __('categories.drawer.parent_none') }}</option>
                                        @foreach ($parents as $p)
                                            <option value="{{ $p->id }}" data-depth="{{ $p->tree_depth ?? 0 }}">{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                {{-- Tax rule — category-level tax has priority over product-level.
                                     "None" falls back to whatever the product carries. --}}
                                <label class="field">
                                    <span class="field-label">{{ __('categories.drawer.tax_group') }}</span>
                                    {{-- See parent select above for why `x-effect` is needed —
                                         TomSelect's visible chip doesn't follow Alpine x-model writes. --}}
                                    <select x-data="enhancedSelect()"
                                            x-effect="ts && ts.setValue(form.tax_group_id ?? '', true)"
                                            name="tax_group_id"
                                            x-model="form.tax_group_id"
                                            class="pos-input">
                                        <option value="">{{ __('categories.drawer.tax_group_none') }}</option>
                                        @foreach ($taxGroups as $tg)
                                            <option value="{{ $tg->id }}">{{ $tg->name }}{{ $tg->components_sum_rate !== null ? ' ('.rtrim(rtrim(number_format((float)$tg->components_sum_rate, 2), '0'), '.').'%)' : '' }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                {{-- Icon color — swatch picker. The list-row tile picks this up
                                     via `--cat-color`. Icon glyph is fixed (tag) for every row. --}}
                                <div class="field">
                                    <span class="field-label">{{ __('categories.drawer.color') }}</span>
                                    <input type="hidden" name="color" :value="form.color">
                                    <div class="cat-swatch-row">
                                        @foreach ($colorChoices as $hex)
                                            <button type="button"
                                                    class="cat-swatch"
                                                    :class="{ 'is-active': form.color === @js($hex) }"
                                                    style="--swatch-color: {{ $hex }};"
                                                    @click="form.color = {{ \Illuminate\Support\Js::from($hex) }}"
                                                    aria-label="{{ $hex }}"></button>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Active toggle --}}
                                <label class="field-toggle">
                                    {{-- Unchecked sentinel so a deselected toggle still posts a 0. --}}
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    <span>{{ __('categories.drawer.is_active') }}</span>
                                </label>
                            </div>

                            {{-- Stats — only when editing an existing row. --}}
                            <template x-if="isEdit">
                                <div class="cat-stats">
                                    <div class="cat-stat">
                                        <div class="cat-stat-label">{{ __('categories.drawer.stats_products') }}</div>
                                        <div class="cat-stat-value tnum"
                                             x-text="rowsById[form.id]?.products_count ?? 0"></div>
                                    </div>
                                    <div class="cat-stat">
                                        <div class="cat-stat-label">{{ __('categories.drawer.stats_revenue') }}</div>
                                        <div class="cat-stat-value tnum">—</div>
                                    </div>
                                </div>
                            </template>

                            <div class="cat-editor-actions">
                                <template x-if="isEdit">
                                    <button type="button"
                                            class="pos-btn pos-btn-sm pos-btn-danger"
                                            :disabled="submitting"
                                            @click="confirmDelete({{ \Illuminate\Support\Js::from(__('categories.actions.delete_confirm')) }})">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('categories.actions.delete') }}
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
                                        {{ __('categories.actions.discard') }}
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
                                                ? (isEdit ? @js(__('categories.actions_extra.saving'))
                                                          : @js(__('categories.actions_extra.creating')))
                                                : (isEdit ? @js(__('categories.actions.save'))
                                                          : @js(__('categories.actions.create')))
                                        "></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </template>

                {{-- Delete goes through `_ajaxDelete()` — no hidden form needed. --}}
            </div>
        </div>

        {{-- Delete-with-move modal — opened by openDeleteFlow() when the
             category being deleted has products attached. The picker is a
             remoteSelect bound to /admin/categories/search-replacement so
             the operator can choose any other category (the row being
             deleted is excluded server-side). Leave blank → falls back to
             the system default. --}}
        <div class="scrim overlay-host"
             x-show="delMove.open"
             x-cloak
             @click.self="closeDelMove()"
             @keydown.escape.window="if (delMove.open) closeDelMove()">
            <div class="modal-card"
                 role="alertdialog"
                 aria-modal="true"
                 :aria-labelledby="`del-move-title-${delMove.id}`">
                <div class="modal-body">
                    <div class="confirm-title" :id="`del-move-title-${delMove.id}`"
                         x-text="@js(__('categories.delete_dialog.title')).replace(':name', delMove.name)"></div>
                    <div class="confirm-msg mt-2"
                         x-text="@js(__('categories.delete_dialog.has_products')).replace(':count', delMove.count)"></div>

                    <label class="field mt-4">
                        <span class="field-label">{{ __('categories.delete_dialog.move_label') }}</span>
                        <template x-if="delMove.open">
                            <select class="pos-input"
                                    x-data="remoteSelect({
                                        url:            delMoveSearchUrl,
                                        exclude:        delMove.id,
                                        value:          delMove.replacementId,
                                        label:          delMove.replacementLabel,
                                        placeholder:    @js(__('categories.delete_dialog.move_placeholder')),
                                        dropdownParent: 'body',
                                    })"
                                    x-model="delMove.replacementId">
                                <option value="">—</option>
                                <template x-if="delMove.replacementId">
                                    <option :value="delMove.replacementId"
                                            :selected="true"
                                            x-text="delMove.replacementLabel"></option>
                                </template>
                            </select>
                        </template>
                        <p class="field-help"
                           x-show="delMove.defaultName"
                           x-text="@js(__('categories.delete_dialog.default_hint')).replace(':name', delMove.defaultName)"></p>
                    </label>
                </div>
                <div class="modal-foot">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            :disabled="delMove.submitting"
                            @click="closeDelMove()">
                        {{ __('categories.delete_dialog.cancel') }}
                    </button>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-danger"
                            :disabled="delMove.submitting"
                            @click="confirmDelMove()">
                        <svg x-show="delMove.submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        {{ __('categories.delete_dialog.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
