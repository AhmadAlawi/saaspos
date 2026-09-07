<x-admin-layout
    active="unit-categories"
    :title="__('unit_categories.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('unit_categories.crumb_parent'), 'href' => '/admin/products'],
        ['label' => __('units.title'), 'href' => route('admin.units.index')],
        ['label' => __('unit_categories.title')],
    ]">

    @php
        $rowsForJs = $rows->map(fn ($r) => [
            'id'         => $r->id,
            'name'       => $r->name,
            'slug'       => $r->slug,
            'sort_order' => $r->sort_order,
            'is_active'  => (bool) $r->is_active,
            'updated_at' => $r->updated_at?->diffForHumans(),
        ])->values();

        if ($errors->any()) {
            $editingId = old('editing_id') ? (int) old('editing_id') : null;
            $initial = [
                'mode'       => $editingId ? 'edit' : 'new',
                'id'         => $editingId,
                'name'       => (string) old('name', ''),
                'slug'       => (string) old('slug', ''),
                'sort_order' => (string) old('sort_order', '0'),
                'is_active'  => (bool) old('is_active', true),
            ];
        } elseif ($selected) {
            $initial = [
                'mode'       => 'edit',
                'id'         => $selected->id,
                'name'       => $selected->name,
                'slug'       => $selected->slug,
                'sort_order' => (string) $selected->sort_order,
                'is_active'  => (bool) $selected->is_active,
            ];
        } elseif ($isNew) {
            $initial = ['mode' => 'new', 'id' => null, 'name' => '', 'slug' => '', 'sort_order' => '0', 'is_active' => true];
        } else {
            $initial = null;
        }
    @endphp

    <div class="page-wide"
         x-data="unitCategoriesPage({
             storeUrl:                   '{{ route('admin.units.categories.store') }}',
             updateUrlTemplate:          '{{ route('admin.units.categories.update', ['unitCategory' => '__ID__']) }}',
             deleteUrlTemplate:          '{{ route('admin.units.categories.destroy', ['unitCategory' => '__ID__']) }}',
             deactivateCheckUrlTemplate: '{{ route('admin.units.categories.deactivate-check', ['unitCategory' => '__ID__']) }}',
             rows:                       {{ Js::from($rowsForJs) }},
             initial:                    {{ Js::from($initial) }},
         })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('unit_categories.title') }}</h1>
                <p class="page-sub">{{ __('unit_categories.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('unit_categories.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.units.categories.export', ['format' => 'csv']) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('unit_categories.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.units.categories.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('unit_categories.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('admin.units.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="arrow-left" class="w-4 h-4" />
                    {{ __('units.title') }}
                </a>

                <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('unit_categories.actions.new') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            <div class="card card-pad-0">
                @if ($rows->isEmpty())
                    <div class="unit-empty">
                        <span class="unit-empty-icon"><x-icon name="archive" class="w-5 h-5" /></span>
                        <div class="unit-empty-title">{{ __('unit_categories.list.empty_title') }}</div>
                        <div class="unit-empty-sub">{{ __('unit_categories.list.empty_sub') }}</div>
                    </div>
                @else
                    <x-admin.data-table>
                        <x-slot:toolbarStart>
                            <div class="dt-toolbar-title">
                                {{ __('unit_categories.list.title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                            </div>
                        </x-slot:toolbarStart>

                        <ul class="unit-cat-list unit-list">
                            @include('admin.units.categories._list', ['rows' => $rows])
                        </ul>
                    </x-admin.data-table>
                @endif
            </div>

            <div class="card unit-editor">
                <div class="unit-editor-empty" x-show="isIdle" x-cloak>
                    <span class="unit-editor-empty-icon"><x-icon name="archive" class="w-5 h-5" /></span>
                    <div class="unit-editor-empty-title">{{ __('unit_categories.editor.empty_title') }}</div>
                    <div class="unit-editor-empty-sub">{{ __('unit_categories.editor.empty_sub') }}</div>
                </div>

                <template x-if="!isIdle">
                    <div>
                        <div class="unit-editor-head">
                            <span class="unit-tile unit-tile-lg" aria-hidden="true"
                                  x-text="form.slug ? form.slug.substring(0,2).toUpperCase() : '—'"></span>
                            <div>
                                <div class="unit-editor-name"
                                     x-text="isEdit ? form.name || @js(__('unit_categories.editor.edit_title'))
                                                    : @js(__('unit_categories.editor.new_title'))"></div>
                                <div class="unit-editor-id">
                                    <template x-if="isEdit">
                                        <span>
                                            {{ __('unit_categories.editor.edit_id') }}
                                            <span class="mono" x-text="form.id"></span>
                                            <template x-if="rowMeta?.updated_at">
                                                <span>
                                                    · {{ __('unit_categories.editor.edit_updated') }}
                                                    <span x-text="rowMeta.updated_at"></span>
                                                </span>
                                            </template>
                                        </span>
                                    </template>
                                    <template x-if="isNew">
                                        <span>{{ __('unit_categories.editor.new_sub') }}</span>
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
                                    <span class="field-label is-required">{{ __('unit_categories.fields.name') }}</span>
                                    <input type="text"
                                           name="name"
                                           x-model="form.name"
                                           @input="clearFieldError('name'); autoSlug()"
                                           class="pos-input"
                                           maxlength="64"
                                           placeholder="{{ __('unit_categories.fields.name_placeholder') }}">
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.slug }">
                                    <span class="field-label is-required">{{ __('unit_categories.fields.slug') }}</span>
                                    <input type="text"
                                           name="slug"
                                           x-model="form.slug"
                                           @input="clearFieldError('slug')"
                                           class="pos-input mono"
                                           maxlength="32"
                                           placeholder="{{ __('unit_categories.fields.slug_placeholder') }}">
                                    <p class="field-help">{{ __('unit_categories.fields.slug_help') }}</p>
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.sort_order }">
                                    <span class="field-label">{{ __('unit_categories.fields.sort_order') }}</span>
                                    <input type="number"
                                           name="sort_order"
                                           x-model="form.sort_order"
                                           @input="clearFieldError('sort_order')"
                                           class="pos-input"
                                           min="0"
                                           max="9999"
                                           placeholder="0">
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    <span>{{ __('unit_categories.fields.is_active') }}</span>
                                </label>
                            </div>

                            <div class="unit-editor-actions">
                                <template x-if="isEdit">
                                    <button type="button"
                                            class="pos-btn pos-btn-sm pos-btn-danger"
                                            :disabled="submitting"
                                            @click="confirmDelete({{ \Illuminate\Support\Js::from(__('unit_categories.actions.delete_confirm')) }})">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('unit_categories.actions.delete') }}
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
                                        {{ __('unit_categories.actions.discard') }}
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
                                                ? (isEdit ? @js(__('unit_categories.actions_extra.saving'))
                                                          : @js(__('unit_categories.actions_extra.creating')))
                                                : (isEdit ? @js(__('unit_categories.actions.save'))
                                                          : @js(__('unit_categories.actions.create')))
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
