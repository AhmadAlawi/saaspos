<x-admin-layout
    active="drug-schedules"
    :title="__('drug_schedules.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('drug_schedules.crumb_parent')],
        ['label' => __('drug_schedules.title')],
    ]">

    @php
        $rowsForJs = $rows->map(fn ($r) => [
            'id'           => $r->id,
            'code'         => $r->code,
            'name'         => $r->name,
            'description'  => $r->description,
            'country_code' => $r->country_code,
            'is_active'    => (bool) $r->is_active,
            'updated_at'   => $r->updated_at?->diffForHumans(),
        ])->values();

        if ($errors->any()) {
            $editingId = old('editing_id') ? (int) old('editing_id') : null;
            $initial = [
                'mode'         => $editingId ? 'edit' : 'new',
                'id'           => $editingId,
                'code'         => (string) old('code', ''),
                'name'         => (string) old('name', ''),
                'description'  => (string) old('description', ''),
                'country_code' => (string) old('country_code', ''),
                'is_active'    => (bool) old('is_active', false),
            ];
        } elseif ($selected) {
            $initial = [
                'mode'         => 'edit',
                'id'           => $selected->id,
                'code'         => $selected->code,
                'name'         => $selected->name,
                'description'  => (string) ($selected->description ?? ''),
                'country_code' => (string) ($selected->country_code ?? ''),
                'is_active'    => (bool) $selected->is_active,
            ];
        } elseif ($isNew) {
            $initial = ['mode' => 'new', 'id' => null, 'code' => '', 'name' => '', 'description' => '', 'country_code' => '', 'is_active' => true];
        } else {
            $initial = null;
        }
    @endphp

    <div class="page-wide"
         x-data="drugSchedulesPage({
             storeUrl:          '{{ route('admin.drug-schedules.store') }}',
             updateUrlTemplate: '{{ route('admin.drug-schedules.update', ['drugSchedule' => '__ID__']) }}',
             deleteUrlTemplate:          '{{ route('admin.drug-schedules.destroy', ['drugSchedule' => '__ID__']) }}',
             deleteInfoUrlTemplate:      '{{ route('admin.drug-schedules.delete-info', ['drugSchedule' => '__ID__']) }}',
             deactivateCheckUrlTemplate: '{{ route('admin.drug-schedules.deactivate-check', ['drugSchedule' => '__ID__']) }}',
             searchReplacementUrl: '{{ route('admin.drug-schedules.search-replacement') }}',
             lang: {{ Js::from(__('drug_schedules.delete_dialog')) }},
             rows:              {{ Js::from($rowsForJs) }},
             initial:           {{ Js::from($initial) }},
         })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('drug_schedules.title') }}</h1>
                <p class="page-sub">{{ __('drug_schedules.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('drug_schedules.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.drug-schedules.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('drug_schedules.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.drug-schedules.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('drug_schedules.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('drug_schedules.actions.new') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            @if ($rows->isEmpty())
                <div class="card card-pad-0">
                    <div class="ds-empty">
                        <span class="ds-empty-icon"><x-icon name="lock" class="w-5 h-5" /></span>
                        <div class="ds-empty-title">{{ __('drug_schedules.list.empty_title') }}</div>
                        <div class="ds-empty-sub">{{ __('drug_schedules.list.empty_sub') }}</div>
                    </div>
                </div>
            @else
                <x-admin.data-table>
                    <x-slot:toolbarStart>
                        <div class="dt-toolbar-title">
                            {{ __('drug_schedules.list.title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)
                        </div>
                    </x-slot:toolbarStart>

                    <ul class="ds-list">
                        @include('admin.drug-schedules._list', ['rows' => $rows])
                    </ul>
                </x-admin.data-table>
            @endif

            <div class="card ds-editor">
                <div class="ds-editor-empty" x-show="isIdle" x-cloak>
                    <span class="ds-editor-empty-icon"><x-icon name="lock" class="w-5 h-5" /></span>
                    <div class="ds-editor-empty-title">{{ __('drug_schedules.editor.empty_title') }}</div>
                    <div class="ds-editor-empty-sub">{{ __('drug_schedules.editor.empty_sub') }}</div>
                </div>

                <template x-if="!isIdle">
                    <div>
                        <div class="ds-editor-head">
                            <span class="ds-tile ds-tile-lg" aria-hidden="true"
                                  x-text="form.code || '—'"></span>
                            <div>
                                <div class="ds-editor-name"
                                     x-text="isEdit ? form.name || @js(__('drug_schedules.drawer.edit_title'))
                                                    : @js(__('drug_schedules.drawer.new_title'))"></div>
                                <div class="ds-editor-id">
                                    <template x-if="isEdit">
                                        <span>
                                            {{ __('drug_schedules.drawer.edit_id') }}
                                            <span class="mono" x-text="form.id"></span>
                                            <template x-if="rowMeta?.updated_at">
                                                <span>
                                                    · {{ __('drug_schedules.drawer.edit_updated') }}
                                                    <span x-text="rowMeta.updated_at"></span>
                                                </span>
                                            </template>
                                        </span>
                                    </template>
                                    <template x-if="isNew">
                                        <span>{{ __('drug_schedules.drawer.new_sub') }}</span>
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
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field">
                                        <span class="field-label is-required">{{ __('drug_schedules.drawer.code') }}</span>
                                        <input type="text"
                                               name="code"
                                               x-model="form.code"
                                               maxlength="16"
                                               class="pos-input mono"
                                               placeholder="{{ __('drug_schedules.drawer.code_placeholder') }}">
                                        <p class="field-help">{{ __('drug_schedules.drawer.code_hint') }}</p>
                                    </label>
                                    <label class="field">
                                        <span class="field-label">{{ __('drug_schedules.drawer.country_code') }}</span>
                                        <select name="country_code"
                                                x-data="enhancedSelect({ maxOptions: 300 })"
                                                x-model="form.country_code"
                                                class="pos-input">
                                            <option value="">{{ __('drug_schedules.drawer.country_code_placeholder') }}</option>
                                            @foreach ($countries as $code => $name)
                                                <option value="{{ $code }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>

                                <label class="field">
                                    <span class="field-label is-required">{{ __('drug_schedules.drawer.name') }}</span>
                                    <input type="text"
                                           name="name"
                                           x-model="form.name"
                                           maxlength="191"
                                           class="pos-input"
                                           placeholder="{{ __('drug_schedules.drawer.name_placeholder') }}">
                                </label>

                                <label class="field">
                                    <span class="field-label">{{ __('drug_schedules.drawer.description') }}</span>
                                    <textarea name="description"
                                              x-model="form.description"
                                              rows="3"
                                              class="pos-input"
                                              placeholder="{{ __('drug_schedules.drawer.description_placeholder') }}"></textarea>
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    <span>{{ __('drug_schedules.drawer.is_active') }}</span>
                                </label>
                            </div>

                            <div class="ds-editor-actions">
                                <template x-if="isEdit">
                                    <button type="button"
                                            class="pos-btn pos-btn-sm pos-btn-danger"
                                            :disabled="submitting"
                                            @click="confirmDelete({{ \Illuminate\Support\Js::from(__('drug_schedules.actions.delete_confirm')) }})">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('drug_schedules.actions.delete') }}
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
                                        {{ __('drug_schedules.actions.discard') }}
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
                                                ? (isEdit ? @js(__('drug_schedules.actions_extra.saving'))
                                                          : @js(__('drug_schedules.actions_extra.creating')))
                                                : (isEdit ? @js(__('drug_schedules.actions.save'))
                                                          : @js(__('drug_schedules.actions.create')))
                                        "></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </div>

        {{-- Delete-with-move modal — opened by confirmDeleteRow() when the
             schedule being deleted has products tagged with its code. The
             picker is a remoteSelect bound to /admin/drug-schedules/search-replacement
             (the row being deleted is excluded server-side). Leave blank →
             the schedule is cleared from those products (no default exists). --}}
        <div class="scrim overlay-host"
             x-show="delMove.open"
             x-cloak
             @click.self="closeDelMove()"
             @keydown.escape.window="if (delMove.open) closeDelMove()">
            <div class="modal-card"
                 role="alertdialog"
                 aria-modal="true"
                 :aria-labelledby="`ds-del-move-title-${delMove.id}`">
                <div class="modal-body">
                    <div class="confirm-title" :id="`ds-del-move-title-${delMove.id}`"
                         x-text="@js(__('drug_schedules.delete_dialog.title')).replace(':name', delMove.name)"></div>
                    <div class="confirm-msg mt-2"
                         x-text="@js(__('drug_schedules.delete_dialog.has_products')).replace(':count', delMove.count)"></div>

                    <label class="field mt-4">
                        <span class="field-label">{{ __('drug_schedules.delete_dialog.move_label') }}</span>
                        <template x-if="delMove.open">
                            <select class="pos-input"
                                    x-data="remoteSelect({
                                        url:            delMoveSearchUrl,
                                        exclude:        delMove.id,
                                        value:          delMove.replacementId,
                                        label:          delMove.replacementLabel,
                                        placeholder:    @js(__('drug_schedules.delete_dialog.move_placeholder')),
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
                        <p class="field-help">{{ __('drug_schedules.delete_dialog.clear_hint') }}</p>
                    </label>
                </div>
                <div class="modal-foot">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            :disabled="delMove.submitting"
                            @click="closeDelMove()">
                        {{ __('drug_schedules.delete_dialog.cancel') }}
                    </button>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-danger"
                            :disabled="delMove.submitting"
                            @click="confirmDelMove()">
                        <svg x-show="delMove.submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        {{ __('drug_schedules.delete_dialog.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
