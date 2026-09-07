<x-admin-layout
    active="tax-classifications"
    :title="__('tax.classifications.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('tax.groups.title'), 'href' => route('admin.settings.tax.groups.index')],
        ['label' => __('tax.classifications.title')],
    ]">

    @php
        $rowsForJs = $rows->map(fn ($r) => [
            'id'          => $r->id,
            'name'        => $r->name,
            'slug'        => $r->slug,
            'description' => $r->description,
            'sort_order'  => $r->sort_order,
            'is_active'   => (bool) $r->is_active,
            'updated_at'  => $r->updated_at?->diffForHumans(),
        ])->values();

        if ($errors->any()) {
            $editingId = old('editing_id') ? (int) old('editing_id') : null;
            $initial = [
                'mode'        => $editingId ? 'edit' : 'new',
                'id'          => $editingId,
                'name'        => (string) old('name', ''),
                'slug'        => (string) old('slug', ''),
                'description' => (string) old('description', ''),
                'sort_order'  => (string) old('sort_order', '0'),
                'is_active'   => (bool) old('is_active', true),
            ];
        } elseif ($selected) {
            $initial = [
                'mode'        => 'edit',
                'id'          => $selected->id,
                'name'        => $selected->name,
                'slug'        => $selected->slug,
                'description' => (string) ($selected->description ?? ''),
                'sort_order'  => (string) $selected->sort_order,
                'is_active'   => (bool) $selected->is_active,
            ];
        } elseif ($isNew) {
            $initial = ['mode' => 'new', 'id' => null, 'name' => '', 'slug' => '', 'description' => '', 'sort_order' => '0', 'is_active' => true];
        } else {
            $initial = null;
        }
    @endphp

    <div class="page-wide"
         x-data="taxClassificationsPage({
             storeUrl:                   '{{ route('admin.settings.tax.classifications.store') }}',
             updateUrlTemplate:          '{{ route('admin.settings.tax.classifications.update', ['taxClassification' => '__ID__']) }}',
             deleteUrlTemplate:          '{{ route('admin.settings.tax.classifications.destroy', ['taxClassification' => '__ID__']) }}',
             deactivateCheckUrlTemplate: '{{ route('admin.settings.tax.classifications.deactivate-check', ['taxClassification' => '__ID__']) }}',
             rows:                       {{ Js::from($rowsForJs) }},
             initial:                    {{ Js::from($initial) }},
         })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('tax.classifications.title') }}</h1>
                <p class="page-sub">{{ __('tax.classifications.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('tax.classifications.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.settings.tax.classifications.export', ['format' => 'csv']) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('tax.classifications.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.tax.classifications.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item" @click="close()">
                            <span class="dropdown-item-label">{{ __('tax.classifications.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('admin.settings.tax.groups.index') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="arrow-left" class="w-4 h-4" />
                    {{ __('tax.groups.title') }}
                </a>

                <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('tax.classifications.actions.new') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            <div class="card card-pad-0">
                @if ($rows->isEmpty())
                    <div class="unit-empty">
                        <span class="unit-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                        <div class="unit-empty-title">{{ __('tax.classifications.list.empty_title') }}</div>
                        <div class="unit-empty-sub">{{ __('tax.classifications.list.empty_sub') }}</div>
                    </div>
                @else
                    <x-admin.data-table>
                        <x-slot:toolbarStart>
                            <div class="dt-toolbar-title">
                                {{ __('tax.classifications.list.title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                            </div>
                        </x-slot:toolbarStart>

                        <ul class="txcl-list unit-list">
                            @include('admin.settings.tax.classifications._list', ['rows' => $rows])
                        </ul>
                    </x-admin.data-table>
                @endif
            </div>

            <div class="card unit-editor">
                <div class="unit-editor-empty" x-show="isIdle" x-cloak>
                    <span class="unit-editor-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                    <div class="unit-editor-empty-title">{{ __('tax.classifications.editor.empty_title') }}</div>
                    <div class="unit-editor-empty-sub">{{ __('tax.classifications.editor.empty_sub') }}</div>
                </div>

                <template x-if="!isIdle">
                    <div>
                        <div class="unit-editor-head">
                            <span class="unit-tile unit-tile-lg" aria-hidden="true"
                                  x-text="form.slug ? form.slug.substring(0,2).toUpperCase() : '—'"></span>
                            <div>
                                <div class="unit-editor-name"
                                     x-text="isEdit ? form.name || @js(__('tax.classifications.editor.edit_title'))
                                                    : @js(__('tax.classifications.editor.new_title'))"></div>
                                <div class="unit-editor-id">
                                    <template x-if="isEdit">
                                        <span>
                                            {{ __('tax.classifications.editor.edit_id') }}
                                            <span class="mono" x-text="form.id"></span>
                                            <template x-if="rowMeta?.updated_at">
                                                <span>
                                                    · {{ __('tax.classifications.editor.edit_updated') }}
                                                    <span x-text="rowMeta.updated_at"></span>
                                                </span>
                                            </template>
                                        </span>
                                    </template>
                                    <template x-if="isNew">
                                        <span>{{ __('tax.classifications.editor.new_sub') }}</span>
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
                                    <span class="field-label is-required">{{ __('tax.classifications.fields.name') }}</span>
                                    <input type="text"
                                           name="name"
                                           x-model="form.name"
                                           @input="clearFieldError('name'); autoSlug()"
                                           class="pos-input"
                                           maxlength="100"
                                           placeholder="{{ __('tax.classifications.fields.name_placeholder') }}">
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.slug }">
                                    <span class="field-label is-required">{{ __('tax.classifications.fields.slug') }}</span>
                                    <input type="text"
                                           name="slug"
                                           x-model="form.slug"
                                           @input="clearFieldError('slug'); _slugManuallyEdited = true"
                                           class="pos-input mono"
                                           maxlength="50"
                                           placeholder="{{ __('tax.classifications.fields.slug_placeholder') }}">
                                    <p class="field-help">{{ __('tax.classifications.fields.slug_help') }}</p>
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.description }">
                                    <span class="field-label">{{ __('tax.classifications.fields.description') }}</span>
                                    <textarea name="description"
                                              x-model="form.description"
                                              @input="clearFieldError('description')"
                                              class="pos-input"
                                              rows="2"
                                              maxlength="255"
                                              placeholder="{{ __('tax.classifications.fields.description_placeholder') }}"></textarea>
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.sort_order }">
                                    <span class="field-label">{{ __('tax.classifications.fields.sort_order') }}</span>
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
                                    <span>{{ __('tax.classifications.fields.is_active') }}</span>
                                </label>
                            </div>

                            <div class="unit-editor-actions">
                                <template x-if="isEdit">
                                    <button type="button"
                                            class="pos-btn pos-btn-sm pos-btn-danger"
                                            :disabled="submitting"
                                            @click="confirmDelete({{ \Illuminate\Support\Js::from(__('tax.classifications.actions.delete_confirm')) }})">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('tax.classifications.actions.delete') }}
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
                                        {{ __('tax.classifications.actions.discard') }}
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
                                                ? (isEdit ? @js(__('tax.classifications.actions_extra.saving'))
                                                          : @js(__('tax.classifications.actions_extra.creating')))
                                                : (isEdit ? @js(__('tax.classifications.actions.save'))
                                                          : @js(__('tax.classifications.actions.create')))
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
