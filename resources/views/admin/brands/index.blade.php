<x-admin-layout
    active="brands"
    :title="__('brands.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('brands.crumb_parent'), 'href' => '/admin/products'],
        ['label' => __('brands.title')],
    ]">

    @php
        // The editor's rowsById covers EVERY brand (it can open any of them),
        // so it's built from the full set — only the rendered list is paged.
        $rowsForJs = $allBrands->map(fn ($b) => [
            'id'          => $b->id,
            'name'        => $b->name,
            'description' => $b->description,
            'logo_url'    => $b->logo_url,
            'is_active'   => (bool) $b->is_active,
            'updated_at'  => $b->updated_at?->diffForHumans(),
        ])->values();

        if ($errors->any()) {
            $editingId = old('editing_id') ? (int) old('editing_id') : null;
            $initial = [
                'mode'        => $editingId ? 'edit' : 'new',
                'id'          => $editingId,
                'name'        => (string) old('name', ''),
                'description' => (string) old('description', ''),
                'logo_url'    => $editingId ? optional($allBrands->firstWhere('id', $editingId))->logo_url : null,
                'is_active'   => (bool) old('is_active', false),
            ];
        } elseif ($selected) {
            $initial = [
                'mode'        => 'edit',
                'id'          => $selected->id,
                'name'        => $selected->name,
                'description' => (string) ($selected->description ?? ''),
                'logo_url'    => $selected->logo_url,
                'is_active'   => (bool) $selected->is_active,
            ];
        } elseif ($isNew) {
            $initial = ['mode' => 'new', 'id' => null, 'name' => '', 'description' => '', 'logo_url' => null, 'is_active' => true];
        } else {
            $initial = null;
        }
    @endphp

    <div class="page-wide"
         x-data="brandsPage({
             endpoint:             '{{ route('admin.brands.rows') }}',
             perPage:              {{ $perPage }},
             total:                {{ $total }},
             page:                 1,
             totalPages:           {{ $totalPages }},
             storeUrl:             '{{ route('admin.brands.store') }}',
             updateUrlTemplate:    '{{ route('admin.brands.update', ['brand' => '__ID__']) }}',
             deleteUrlTemplate:    '{{ route('admin.brands.destroy', ['brand' => '__ID__']) }}',
             deleteInfoUrlTemplate:      '{{ route('admin.brands.delete-info', ['brand' => '__ID__']) }}',
             deactivateCheckUrlTemplate: '{{ route('admin.brands.deactivate-check', ['brand' => '__ID__']) }}',
             searchReplacementUrl: '{{ route('admin.brands.search-replacement') }}',
             rows:                 {{ Js::from($rowsForJs) }},
             initial:              {{ Js::from($initial) }},
         })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('brands.title') }}</h1>
                <p class="page-sub">{{ __('brands.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('brands.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.brands.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('brands.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.brands.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('brands.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('brands.actions.new') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            @if ($total === 0)
                <div class="card card-pad-0">
                    <div class="brand-empty">
                        <span class="brand-empty-icon"><x-icon name="star" class="w-5 h-5" /></span>
                        <div class="brand-empty-title">{{ __('brands.list.empty_title') }}</div>
                        <div class="brand-empty-sub">{{ __('brands.list.empty_sub') }}</div>
                    </div>
                </div>
            @else
                <x-admin.data-table>
                    <x-slot:toolbarStart>
                        <div class="dt-toolbar-title">
                            {{ __('brands.list.title') }} (<span x-text="matchedCount.toLocaleString()">{{ number_format($total) }}</span>)
                        </div>
                    </x-slot:toolbarStart>

                    <ul class="brand-list">
                        @include('admin.brands._list', ['brands' => $brands])
                    </ul>
                </x-admin.data-table>
            @endif

            <div class="card brand-editor">
                <div class="brand-editor-empty" x-show="isIdle" x-cloak>
                    <span class="brand-editor-empty-icon"><x-icon name="star" class="w-5 h-5" /></span>
                    <div class="brand-editor-empty-title">{{ __('brands.editor.empty_title') }}</div>
                    <div class="brand-editor-empty-sub">{{ __('brands.editor.empty_sub') }}</div>
                </div>

                <template x-if="!isIdle">
                    <div>
                        <div class="brand-editor-head">
                            <span class="brand-tile brand-tile-lg" aria-hidden="true">
                                <template x-if="formHasLogo">
                                    <img :src="form.logo_url"
                                         alt=""
                                         class="brand-tile-img"
                                         @@error="_onLogoError(form.id)">
                                </template>
                                <template x-if="!formHasLogo">
                                    <span x-text="(form.name || '?').charAt(0).toUpperCase()"></span>
                                </template>
                            </span>
                            <div>
                                <div class="brand-editor-name"
                                     x-text="isEdit ? form.name || @js(__('brands.drawer.edit_title'))
                                                    : @js(__('brands.drawer.new_title'))"></div>
                                <div class="brand-editor-id">
                                    <template x-if="isEdit">
                                        <span>
                                            {{ __('brands.drawer.edit_id') }}
                                            <span class="mono" x-text="form.id"></span>
                                            <template x-if="rowMeta?.updated_at">
                                                <span>
                                                    · {{ __('brands.drawer.edit_updated') }}
                                                    <span x-text="rowMeta.updated_at"></span>
                                                </span>
                                            </template>
                                        </span>
                                    </template>
                                    <template x-if="isNew">
                                        <span>{{ __('brands.drawer.new_sub') }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <form method="POST"
                              :action="formAction"
                              class="card-body"
                              enctype="multipart/form-data"
                              novalidate
                              @submit.prevent="submitEditor($event)">
                            @csrf
                            <input type="hidden" name="_method" :value="isEdit ? 'PATCH' : 'POST'">
                            <input type="hidden" name="editing_id" :value="form.id || ''">

                            <div class="form-stack">
                                <label class="field">
                                    <span class="field-label is-required">{{ __('brands.drawer.name') }}</span>
                                    <input type="text"
                                           name="name"
                                           x-model="form.name"
                                           class="pos-input"
                                           placeholder="{{ __('brands.drawer.name_placeholder') }}">
                                </label>

                                {{-- Logo upload. The component owns its own preview state;
                                     the brandsPage factory calls .sync(url) on it via the
                                     `data-logo-upload` hook whenever the editor switches
                                     to a different brand. --}}
                                <div class="field" data-logo-upload>
                                    <span class="field-label">{{ __('brands.drawer.logo') }}</span>
                                    <x-admin.image-upload
                                        name="logo"
                                        :initial-url="null"
                                        :max-size-kb="1024" />
                                </div>

                                <label class="field">
                                    <span class="field-label">{{ __('brands.drawer.description') }}</span>
                                    <textarea name="description"
                                              x-model="form.description"
                                              class="pos-input"
                                              rows="3"
                                              placeholder="{{ __('brands.drawer.description_placeholder') }}"></textarea>
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    <span>{{ __('brands.drawer.is_active') }}</span>
                                </label>
                            </div>

                            <div class="brand-editor-actions">
                                <template x-if="isEdit">
                                    <button type="button"
                                            class="pos-btn pos-btn-sm pos-btn-danger"
                                            :disabled="submitting"
                                            @click="confirmDelete({{ \Illuminate\Support\Js::from(__('brands.actions.delete_confirm')) }})">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('brands.actions.delete') }}
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
                                        {{ __('brands.actions.discard') }}
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
                                                ? (isEdit ? @js(__('brands.actions_extra.saving'))
                                                          : @js(__('brands.actions_extra.creating')))
                                                : (isEdit ? @js(__('brands.actions.save'))
                                                          : @js(__('brands.actions.create')))
                                        "></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </div>

        {{-- Delete-with-move modal — see categories/index for the
             paired comment. Same shape, the picker hits
             /admin/brands/search-replacement instead. --}}
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
                         x-text="@js(__('brands.delete_dialog.title')).replace(':name', delMove.name)"></div>
                    <div class="confirm-msg mt-2"
                         x-text="@js(__('brands.delete_dialog.has_products')).replace(':count', delMove.count)"></div>

                    <label class="field mt-4">
                        <span class="field-label">{{ __('brands.delete_dialog.move_label') }}</span>
                        <template x-if="delMove.open">
                            <select class="pos-input"
                                    x-data="remoteSelect({
                                        url:            delMoveSearchUrl,
                                        exclude:        delMove.id,
                                        value:          delMove.replacementId,
                                        label:          delMove.replacementLabel,
                                        placeholder:    @js(__('brands.delete_dialog.move_placeholder')),
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
                           x-text="@js(__('brands.delete_dialog.default_hint')).replace(':name', delMove.defaultName)"></p>
                    </label>
                </div>
                <div class="modal-foot">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            :disabled="delMove.submitting"
                            @click="closeDelMove()">
                        {{ __('brands.delete_dialog.cancel') }}
                    </button>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-danger"
                            :disabled="delMove.submitting"
                            @click="confirmDelMove()">
                        <svg x-show="delMove.submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        {{ __('brands.delete_dialog.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
