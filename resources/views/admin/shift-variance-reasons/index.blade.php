<x-admin-layout
    active="shift-variance-reasons"
    :title="__('shift_variance_reasons.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('shifts.title'), 'href' => route('admin.shifts.index')],
        ['label' => __('shift_variance_reasons.title')],
    ]">

    @php
        $rowsForJs = $rows->map(fn ($r) => [
            'id'         => $r->id,
            'code'       => $r->code,
            'name'       => $r->name,
            'sort_order' => (int) $r->sort_order,
            'is_active'  => (bool) $r->is_active,
            'updated_at' => $r->updated_at?->diffForHumans(),
        ])->values();

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
             storeUrl:          '{{ route('admin.shift-variance-reasons.store') }}',
             updateUrlTemplate: '{{ route('admin.shift-variance-reasons.update', ['shiftVarianceReason' => '__ID__']) }}',
             deleteUrlTemplate: '{{ route('admin.shift-variance-reasons.destroy', ['shiftVarianceReason' => '__ID__']) }}',
             rows:              {{ Js::from($rowsForJs) }},
             initial:           {{ Js::from($initial) }},
         })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('shift_variance_reasons.title') }}</h1>
                <p class="page-sub">{{ __('shift_variance_reasons.sub') }}</p>
            </div>
            <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                {{ __('shift_variance_reasons.new') }}
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            <div class="card card-pad-0">
                @if ($rows->isEmpty())
                    <div class="dt-empty">
                        <span class="dt-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                        <div class="dt-empty-title">{{ __('shift_variance_reasons.list.empty') }}</div>
                    </div>
                @else
                    <div class="dt-toolbar">
                        <div class="dt-toolbar-title">{{ __('shift_variance_reasons.list.title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)</div>
                        <x-admin.dt-search />
                        <x-admin.dt-toolbar-actions />
                    </div>
                    <div class="dt-scroll">
                    <table class="dt-table">
                        <thead>
                            <tr>
                                <th>{{ __('shift_variance_reasons.columns.code') }}</th>
                                <th>{{ __('shift_variance_reasons.columns.name') }}</th>
                                <th>{{ __('shift_variance_reasons.columns.sort_order') }}</th>
                                <th>{{ __('table.status') }}</th>
                                <th class="dt-actions-col"><span class="sr-only">{{ __('shift_variance_reasons.columns.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="rsn-list">
                            @include('admin.shift-variance-reasons._list', ['rows' => $rows])
                        </tbody>
                    </table>
                    </div>
                    <x-admin.dt-pager />
                @endif
            </div>

            <div class="card rsn-editor">
                <div class="rsn-editor-empty" x-show="isIdle" x-cloak>
                    <span class="rsn-editor-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                    <div class="rsn-editor-empty-title">{{ __('shift_variance_reasons.editor.empty_title') }}</div>
                    <div class="rsn-editor-empty-sub">{{ __('shift_variance_reasons.editor.empty_sub') }}</div>
                </div>

                <template x-if="!isIdle">
                    <div>
                        <div class="rsn-editor-head">
                            <div>
                                <div class="rsn-editor-name"
                                     x-text="isEdit ? (form.name || @js(__('shift_variance_reasons.editor.edit_title')))
                                                    : @js(__('shift_variance_reasons.editor.new_title'))"></div>
                                <div class="rsn-editor-id">
                                    <template x-if="isEdit">
                                        <span>
                                            {{ __('shift_variance_reasons.editor.edit_id') }}
                                            <span class="mono" x-text="form.id"></span>
                                            <template x-if="rowMeta?.updated_at">
                                                <span> · {{ __('shift_variance_reasons.editor.edit_updated') }} <span x-text="rowMeta.updated_at"></span></span>
                                            </template>
                                        </span>
                                    </template>
                                    <template x-if="isNew">
                                        <span>{{ __('shift_variance_reasons.editor.new_sub') }}</span>
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
                                    <span class="field-label is-required">{{ __('shift_variance_reasons.fields.code') }}</span>
                                    <input type="text" name="code"
                                           x-model="form.code"
                                           @input="clearFieldError('code')"
                                           class="pos-input mono" required maxlength="32">
                                    <p class="field-help">{{ __('shift_variance_reasons.fields.code_help') }}</p>
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.name }">
                                    <span class="field-label is-required">{{ __('shift_variance_reasons.fields.name') }}</span>
                                    <input type="text" name="name"
                                           x-model="form.name"
                                           @input="clearFieldError('name')"
                                           class="pos-input" required maxlength="100">
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.sort_order }">
                                    <span class="field-label">{{ __('shift_variance_reasons.fields.sort_order') }}</span>
                                    <input type="number" name="sort_order"
                                           x-model.number="form.sort_order"
                                           @input="clearFieldError('sort_order')"
                                           class="pos-input" min="0" max="9999">
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    <span>{{ __('shift_variance_reasons.fields.is_active') }}</span>
                                </label>
                            </div>

                            <div class="rsn-editor-footer">
                                <template x-if="isEdit">
                                    <button type="button"
                                            @click="confirmDelete()"
                                            :disabled="submitting"
                                            class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('shift_variance_reasons.actions.delete') }}
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
                                        {{ __('shift_variance_reasons.actions.discard') }}
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
                                                ? (isEdit ? @js(__('shift_variance_reasons.actions.saving'))
                                                          : @js(__('shift_variance_reasons.actions.creating')))
                                                : (isEdit ? @js(__('shift_variance_reasons.actions.save'))
                                                          : @js(__('shift_variance_reasons.actions.create')))
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
